#!/usr/bin/env python3
"""Build and verify the real frontend -> nginx -> Bitrix/FPM -> disposable MySQL path.
No application .env, production database, mock API or licensed kernel files are copied into Git.
"""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import time
import uuid

ROOT = Path(__file__).resolve().parents[1]
IMAGE = "mcr.microsoft.com/playwright:v1.52.0-jammy"
LABEL = "rabit.browser_e2e"


def command(args, log=None, timeout=900):
    result = subprocess.run(args, capture_output=True, text=True, timeout=timeout)
    if log:
        Path(log).write_text(result.stdout + result.stderr)
    if result.returncode:
        detail = "see " + str(log) if log else result.stderr[-2000:]
        raise RuntimeError(f"Command failed ({result.returncode}): {' '.join(args[:4])}; {detail}")
    return result.stdout.strip()


def docker(*args, **kwargs):
    return command(["docker", *args], **kwargs)


def save(state):
    Path(state["report"], "state.json").write_text(json.dumps(state, indent=2) + "\n")


def owned(kind, name, owner):
    data = json.loads(docker(kind, "inspect", name))[0]
    labels = data.get("Config", {}).get("Labels") if kind == "container" else data.get("Labels")
    if (labels or {}).get(LABEL) != owner or not name.startswith(owner + "-"):
        raise RuntimeError("Refusing to remove a resource not owned by this E2E run: " + name)


def stop(state):
    errors = []
    for kind, flag in [("container", ["rm", "-f"]), ("network", ["network", "rm"]), ("volume", ["volume", "rm"])]:
        for name in reversed(state[kind + "s"]):
            try:
                owned(kind, name, state["id"])
                if kind == "container":
                    result = subprocess.run(["docker", "logs", name], capture_output=True, text=True)
                    Path(state["report"], name + ".log").write_text(result.stdout + result.stderr)
                docker(*flag, name)
            except Exception as error:
                errors.append(str(error))
    state["cleanupErrors"] = errors
    state["stopped"] = not errors
    save(state)
    if errors:
        raise RuntimeError("; ".join(errors))


def start(args):
    identity = "rabit-e2e-" + uuid.uuid4().hex[:12]
    report = ROOT / "api/var/e2e" / identity
    report.mkdir(parents=True)
    state = {"id": identity, "report": str(report), "source": str(ROOT), "containers": [], "networks": [], "volumes": [], "stopped": False}
    save(state)
    try:
        kernel = Path(args.kernel).resolve()
        vendor = Path(args.vendor).resolve()
        for source in [kernel / "modules/main/install/mysql/install.sql", kernel / "routing_index.php", vendor / "autoload.php", ROOT / "frontend/package-lock.json"]:
            if not source.is_file():
                raise RuntimeError("Missing dependency: " + str(source))
        for image in [IMAGE, args.php_cli, args.php_fpm, args.nginx, args.mysql, "nginx:1.29-alpine"]:
            docker("image", "inspect", image)
        label = LABEL + "=" + identity
        for suffix in ["private", "browser"]:
            name = identity + "-" + suffix
            docker("network", "create", "--label", label, *(["--internal"] if suffix == "private" else []), name)
            state["networks"].append(name)
            save(state)
        for suffix in ["runtime", "node", "vendor"]:
            name = identity + "-" + suffix
            docker("volume", "create", "--label", label, name)
            state["volumes"].append(name)
            save(state)
        state["private"] = identity + "-private"
        state["node"] = identity + "-node"
        frontend = ROOT / "frontend"
        (ROOT / "api/vendor").mkdir(exist_ok=True)
        state["nodeArgs"] = ["--cpus", "2", "--memory", "3g", "--memory-swap", "3g", "--mount", f"type=bind,source={frontend},target=/app", "--mount", f"type=volume,source={state['node']},target=/app/node_modules", "--workdir", "/app"]
        print("Installing locked frontend dependencies", flush=True)
        docker("run", "--rm", *state["nodeArgs"], IMAGE, "npm", "ci", "--no-audit", "--no-fund", log=report / "npm-ci.log")
        for script in ["check", "test:commerce"]:
            print("Frontend " + script, flush=True)
            docker("run", "--rm", "--network", "none", *state["nodeArgs"], IMAGE, "npm", "run", script, log=report / (script.replace(':', '-') + ".log"))
        print("Building frontend with the real API enabled", flush=True)
        docker("run", "--rm", "--network", "none", *state["nodeArgs"], "--env", "VITE_API_MOCKS_ENABLED=false", "--env", "VITE_API_URL=", IMAGE, "npm", "run", "build", log=report / "build.log")
        name = identity + "-mysql"
        docker("run", "--detach", "--name", name, "--label", label, "--network", state["private"], "--network-alias", "rabit-w02-mysql", "--memory", "1g", "--memory-swap", "1g", "--tmpfs", "/var/lib/mysql:rw,size=512m", "--env", "MYSQL_ALLOW_EMPTY_PASSWORD=yes", "--env", "MYSQL_ROOT_HOST=%", "--env", "MYSQL_DATABASE=rabit_w02", args.mysql, "--skip-log-bin")
        state["containers"].append(name)
        save(state)
        for _ in range(60):
            result = subprocess.run(["docker", "exec", name, "mysqladmin", "--host=127.0.0.1", "ping", "--silent"], capture_output=True)
            if result.returncode == 0:
                break
            time.sleep(1)
        else:
            raise RuntimeError("Disposable MySQL did not become ready")
        fixture = ROOT / "api/tools/e2e"
        mounts = ["--mount", f"type=bind,source={ROOT / 'api'},target=/app,readonly", "--mount", f"type=volume,source={identity}-vendor,target=/app/vendor", "--mount", f"type=bind,source={kernel / 'modules'},target=/kernel/modules,readonly", "--mount", f"type=bind,source={kernel / 'routing_index.php'},target=/kernel/routing_index.php,readonly", "--mount", f"type=volume,source={identity}-runtime,target=/runtime"]
        print("Preparing isolated Composer autoload for this checkout", flush=True)
        docker("run", "--rm", "--network", "none", "--entrypoint", "sh", *mounts,
               "--mount", f"type=bind,source={vendor},target=/seed/vendor,readonly", "--workdir", "/app",
               args.php_cli, "-c", "cp -a /seed/vendor/. /app/vendor/ && composer dump-autoload --no-scripts --no-plugins",
               log=report / "composer-autoload.log")
        print("Backend lint, static analysis and PHPUnit", flush=True)
        for name, check in [
            ("php-lint", ["php", "vendor/bin/phplint"]),
            ("phpstan", ["php", "vendor/bin/phpstan", "analyse", "--no-progress", "--debug", "--memory-limit=1G"]),
            ("phpunit", ["php", "vendor/bin/phpunit", "--colors=never"]),
        ]:
            docker("run", "--rm", "--network", "none", "--cpus", "2", "--memory", "1536m", "--memory-swap", "1536m", "--tmpfs", "/app/var:rw,size=256m", "--entrypoint", "php", *mounts, "--workdir", "/app", args.php_cli, *check[1:], log=report / (name + ".log"))
        print("Installing real Bitrix schema and fixture accounts", flush=True)
        output = docker("run", "--rm", "--network", state["private"], "--user", "0", "--entrypoint", "php", *mounts, "--workdir", "/app", args.php_cli, "-d", "short_open_tag=1", "-d", "date.timezone=UTC", "tools/e2e/prepare.php", log=report / "prepare.log")
        if "fixture ready" not in output:
            raise RuntimeError("Fixture bootstrap did not complete; see prepare.log")
        name = identity + "-fpm"
        docker("run", "--detach", "--name", name, "--label", label, "--network", state["private"], "--network-alias", "api-php-fpm", "--user", "0", "--entrypoint", "php-fpm", *mounts, "--env", "APP_ENV=test", "--env", "APP_DEBUG=0", "--env", "REBIT_GEETEST_ENABLED=0", "--env", "REBIT_GEETEST_BYPASS=1", args.php_fpm, "-y", "/app/tools/e2e/fpm.conf")
        state["containers"].append(name)
        save(state)
        # Reuse the application's actual nginx routing and header forwarding configuration.
        config = (ROOT / "api/docker/common/nginx/conf.d/default.conf").read_text().replace("root /app/public;", "root /runtime/public;")
        (report / "backend.conf").write_text(config)
        name = identity + "-backend"
        docker("run", "--detach", "--name", name, "--label", label, "--network", state["private"], "--network-alias", "backend", *mounts, "--mount", f"type=bind,source={report / 'backend.conf'},target=/etc/nginx/conf.d/default.conf,readonly", "--mount", f"type=bind,source={ROOT / 'api/docker/common/nginx/auth.conf'},target=/etc/nginx/auth.conf,readonly", args.nginx)
        state["containers"].append(name)
        save(state)
        frontend_config = (frontend / "docker/production/nginx/conf.d/default.conf").read_text()
        frontend_config = frontend_config.replace("${API_UPSTREAM}", "http://backend").replace("${API_HOST}", "backend")
        frontend_config = frontend_config.replace("    location = /health {", '''    location = /__e2e {
        default_type application/json;
        return 200 '{"fixture":"rabit-real-e2e"}';
    }
    location = /health {''')
        (report / "frontend.conf").write_text(frontend_config)
        name = identity + "-frontend"
        docker("create", "--name", name, "--label", label, "--network", identity + "-browser", "--publish", "127.0.0.1::80", "--mount", f"type=bind,source={frontend / 'dist'},target=/usr/share/nginx/html,readonly", "--mount", f"type=bind,source={report / 'frontend.conf'},target=/etc/nginx/conf.d/default.conf,readonly", "nginx:1.29-alpine")
        state["containers"].append(name)
        save(state)
        docker("network", "connect", "--alias", "frontend", state["private"], name)
        docker("start", name)
        for _ in range(10):
            ports = json.loads(docker("container", "inspect", name))[0]["NetworkSettings"]["Ports"].get("80/tcp")
            if ports:
                break
            time.sleep(1)
        else:
            raise RuntimeError("Frontend port was not published")
        state["url"] = "http://localhost:" + ports[0]["HostPort"]
        state["commit"] = command(["git", "-C", str(ROOT), "rev-parse", "HEAD"])
        state["lockSha256"] = hashlib.sha256((frontend / "package-lock.json").read_bytes()).hexdigest()
        state["createdAt"] = datetime.datetime.now(datetime.timezone.utc).isoformat()
        save(state)
        print("Browser: " + state["url"], flush=True)
        print("State: " + str(report / "state.json"), flush=True)
        return state
    except BaseException:
        stop(state)
        raise


def test_live(state):
    if state["stopped"]:
        raise RuntimeError("The E2E fixture has been stopped")
    owned("network", state["private"], state["id"])
    print("Running real browser E2E", flush=True)
    docker("run", "--rm", "--network", "container:" + state["id"] + "-frontend", "--shm-size=1g", *state["nodeArgs"], "--env", "E2E_BASE_URL=http://127.0.0.1", IMAGE, "npm", "run", "test:e2e:live", log=Path(state["report"], "browser.log"))
    results = json.loads((ROOT / "frontend/reports/e2e-live/results.json").read_text())
    stats = results["stats"]
    if stats["unexpected"] or stats["skipped"] or stats["expected"] < 25:
        raise RuntimeError("Browser gate incomplete: " + json.dumps(stats))
    state["browser"] = stats
    save(state)
    print("Browser scenarios passed: " + str(stats["expected"]), flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["run", "up", "test", "down"], default="run", nargs="?")
    parser.add_argument("--state", type=Path)
    parser.add_argument("--kernel", default=os.environ.get("E2E_KERNEL_ROOT", "/home/user/rebit-p2p/api/public/bitrix"))
    parser.add_argument("--vendor", default=os.environ.get("E2E_VENDOR_ROOT", "/home/user/rebit-p2p/api/vendor"))
    parser.add_argument("--php-cli", default=os.environ.get("E2E_PHP_CLI_IMAGE", "rabit-api-php-fpm:20260911-074507"))
    parser.add_argument("--php-fpm", default=os.environ.get("E2E_PHP_FPM_IMAGE", "rabit-api-php-fpm:20260911-074507"))
    parser.add_argument("--nginx", default=os.environ.get("E2E_NGINX_IMAGE", "rabit-api-nginx:20260911-074507"))
    parser.add_argument("--mysql", default=os.environ.get("E2E_MYSQL_IMAGE", "mysql:8.0"))
    args = parser.parse_args()
    if args.action in ["test", "down"]:
        if not args.state:
            parser.error("--state is required")
        state = json.loads(args.state.read_text())
        report = Path(state["report"]).resolve()
        if not report.is_relative_to(ROOT / "api/var/e2e") or Path(state["source"]).resolve() != ROOT:
            raise RuntimeError("State does not belong to this checkout")
        stop(state) if args.action == "down" else test_live(state)
        return
    state = start(args)
    if args.action == "run":
        try:
            test_live(state)
        finally:
            stop(state)


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
