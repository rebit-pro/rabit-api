#!/usr/bin/env python3
"""Build and verify the real frontend -> nginx -> Bitrix/FPM -> disposable MySQL path.
No application .env, production database, mock API or licensed kernel files are copied into Git.
Independent checks run at the same time; every browser group gets its own fresh stand.
"""
import argparse
from concurrent.futures import ThreadPoolExecutor, wait
import datetime
import hashlib
import json
import os
from pathlib import Path
import signal
import subprocess
import sys
import threading
import time
import uuid

ROOT = Path(__file__).resolve().parents[1]
IMAGE = "mcr.microsoft.com/playwright:v1.52.0-jammy"
RABBITMQ_IMAGE = "rabbitmq:3.13-management"
LABEL = "rabit.browser_e2e"
# Kept between runs: npm checks every tarball against the lockfile, PHPStan validates its result cache itself.
NPM_CACHE = "rabit-e2e-npm-cache"
PHPSTAN_CACHE = "rabit-e2e-phpstan-cache"
LIVE = ROOT / "frontend/e2e/live"
# Independent browser groups; files inside a group keep the name order. The live Playwright config reads the same file.
GROUPS = json.loads((LIVE / "groups.json").read_text())
# The live config ignores the heavy media bench unless E2E_MEDIA_BENCH is set.
BENCH = "zz-media-bench"
# MySQL checks after the browser: script, file recorded by the browser, success marker, specs that produce the data.
VERIFIERS = [
    ("verify-storefront.php", None, "E4 integration passed", ["zzz-handoff", "zzzz-storefront"]),
    # #26: HND-06 on 1000 staff requests keeps a fixed SQL count; the set is rolled back after the measurement.
    ("verify-handoff.php", None, "F1 list integration passed", ["zzz-handoff"]),
    # The browser records the secrets it received so the verifier can prove none of them is stored in clear text.
    ("verify-orders.php", "e5-orders.json", "E5 integration passed", ["zzzzz-orders", "zzzzzz-transfers", "zzzzzzzz-payment-costs"]),
    ("verify-links.php", None, "F2 integration passed", ["zzzz-links"]),
    # D3: the browser leaves one untransferred staff request for the injected-failure check on MySQL.
    ("verify-transfers.php", "d3-transfers.json", "D3 integration passed", ["zzzzzz-transfers"]),
    # B4: only SHA-256 of the links is stored, and the letter to the staff member created by the B2 spec holds the live one.
    ("verify-access.php", None, "B4 access integration passed", ["staff", "zz-access"]),
    # B3: avatar rows and files match the browser steps.
    ("verify-avatar.php", None, "B3 avatar integration passed", ["zz-avatar"]),
    # E6: schema, CHECK limits and migration replay after the browser switched the payment cost policy back off.
    ("verify-payment-costs.php", None, "E6 payment cost integration passed", ["zzzzzzzz-payment-costs"]),
]
# Production images have no Xdebug; the development one would try to reach a debugger on every PHP request.
PHP_ENV = ["--env", "XDEBUG_MODE=off"]
# DS-12: a test-only organizer contact for the «Помощь» section of the profile.
SUPPORT = ["--env", "MOREFOTO_SUPPORT_NAME=Организатор E2E", "--env", "MOREFOTO_SUPPORT_EMAIL=support@example.invalid", "--env", "MOREFOTO_SUPPORT_PHONE=+7 900 000-00-00"]
LOCK = threading.RLock()
FAILED = threading.Event()
STARTED = time.monotonic()


class Cancelled(RuntimeError):
    """A stage stopped because another stage of the same run had already failed."""


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
    with LOCK:
        Path(state["report"], "state.json").write_text(json.dumps(state, indent=2) + "\n")


def exists(kind, name):
    return 0 == subprocess.run(["docker", kind, "inspect", name], capture_output=True).returncode


def owned(kind, name, owner):
    data = json.loads(docker(kind, "inspect", name))[0]
    labels = data.get("Config", {}).get("Labels") if kind == "container" else data.get("Labels")
    if (labels or {}).get(LABEL) != owner or not name.startswith(owner + "-"):
        raise RuntimeError("Refusing to remove a resource not owned by this E2E run: " + name)


def stop_jobs(state):
    """Removes this run's one-off containers (checks, fixtures, browser); services are left to stop()."""
    listed = subprocess.run(["docker", "ps", "--all", "--filter", f"label={LABEL}={state['id']}", "--format", "{{.Names}}"],
                            capture_output=True, text=True)
    for name in listed.stdout.split():
        if name not in state["containers"] and name.startswith(state["id"] + "-"):
            subprocess.run(["docker", "rm", "--force", name], capture_output=True)


def stop(state):
    stop_jobs(state)  # One-off containers may still use the run's networks and volumes.
    errors = []

    def remove(kind, flag, name):
        try:
            if not exists(kind, name):
                return
            owned(kind, name, state["id"])
            if kind == "container":
                result = subprocess.run(["docker", "logs", name], capture_output=True, text=True)
                Path(state["report"], name + ".log").write_text(result.stdout + result.stderr)
            docker(*flag, name)
        except Exception as error:
            errors.append(str(error))
    # Kinds go in order, since networks and volumes are removable only when no container uses them.
    for kind, flag in [("container", ["rm", "-f"]), ("network", ["network", "rm"]), ("volume", ["volume", "rm"])]:
        with ThreadPoolExecutor(8) as pool:
            for name in state[kind + "s"]:
                pool.submit(remove, kind, flag, name)
    state["cleanupErrors"] = errors
    state["stopped"] = not errors
    save(state)
    if errors:
        raise RuntimeError("; ".join(errors))


def stage(state, name, action):
    """Runs one gate stage; its duration and result are recorded also on failure or cancellation."""
    if FAILED.is_set():
        raise Cancelled("Not started after an earlier failure: " + name)
    record = {"name": name, "startedAt": round(time.monotonic() - STARTED, 1), "status": "running"}
    with LOCK:
        state["stages"].append(record)
        save(state)
    print("-> " + name, flush=True)
    started, status = time.monotonic(), "failed"
    try:
        result = action()
        status = "passed"
        return result
    except BaseException as error:
        if FAILED.is_set():
            status = "cancelled"
            if isinstance(error, Exception):
                raise Cancelled("Stopped after an earlier failure: " + name) from error
            raise
        if not isinstance(error, Exception):
            status = "cancelled"
        FAILED.set()
        stop_jobs(state)  # Fail fast: the gate is already red.
        raise
    finally:
        with LOCK:
            record.update(status=status, seconds=round(time.monotonic() - started, 1))
            save(state)
        print(f"{status} {name} ({record['seconds']} s)", flush=True)


def parallel(state, *actions):
    """Runs independent actions at once and re-raises the first real failure after all of them have stopped."""
    pool = ThreadPoolExecutor(len(actions))
    futures = [pool.submit(action) for action in actions]
    try:
        wait(futures)
    except BaseException:
        FAILED.set()
        stop_jobs(state)
        raise
    finally:
        pool.shutdown()
    errors = [future.exception() for future in futures if future.exception()]
    if errors:
        raise next((error for error in errors if not isinstance(error, Cancelled)), errors[0])


def job(state, name, *args, log=None, timeout=900):
    """Runs a labelled one-off container that a failure elsewhere or a cancellation can stop."""
    return docker("run", "--rm", "--name", state["id"] + "-" + name, "--label", LABEL + "=" + state["id"], *args, log=log, timeout=timeout)


def register(state, kind, name):
    # Recorded before creation, so cleanup also covers a resource whose creation was interrupted.
    with LOCK:
        state[kind + "s"].append(name)
        save(state)
    return name


def service(state, name, *args):
    register(state, "container", name)
    docker("run", "--detach", "--name", name, "--label", LABEL + "=" + state["id"], *args)


def ready(check, message):
    for _ in range(60):
        if FAILED.is_set():
            raise Cancelled(message)
        if 0 == subprocess.run(["docker", "exec", *check], capture_output=True).returncode:
            return
        time.sleep(1)
    raise RuntimeError(message)


def php_mounts(state, stand=None):
    kernel = Path(state["kernel"])
    mounts = ["--mount", f"type=bind,source={ROOT / 'api'},target=/app,readonly",
              "--mount", f"type=volume,source={state['vendor']},target=/app/vendor",
              "--mount", f"type=bind,source={kernel / 'modules'},target=/kernel/modules,readonly",
              "--mount", f"type=bind,source={kernel / 'routing_index.php'},target=/kernel/routing_index.php,readonly"]
    return mounts + (["--mount", f"type=volume,source={stand['runtime']},target=/runtime"] if stand else [])


def check_scripts():
    """`npm run check` split into the scripts it chains, so they run concurrently; any other form runs whole."""
    parts = [part.split() for part in json.loads((ROOT / "frontend/package.json").read_text())["scripts"]["check"].split("&&")]
    return [part[2] for part in parts] if all(3 == len(part) and ["npm", "run"] == part[:2] for part in parts) else ["check"]


def frontend_checks(state):
    report = Path(state["report"])
    stage(state, "npm ci", lambda: job(state, "npm-ci", *state["nodeArgs"], "--mount", f"type=volume,source={NPM_CACHE},target=/root/.npm",
                                        IMAGE, "npm", "ci", "--no-audit", "--no-fund", "--prefer-offline", log=report / "npm-ci.log"))
    # Types are checked once by `check`; the bundle is used only after every check of the gate has passed.
    real_api = ["--env", "VITE_API_MOCKS_ENABLED=false", "--env", "VITE_API_URL="]
    scripts = [(script, []) for script in check_scripts()] + [("test:commerce", []), ("build-only", real_api)]
    parallel(state, *[lambda script=script, env=env: stage(state, "npm run " + script, lambda: job(
        state, script.replace(":", "-"), "--network", "none", *state["nodeArgs"], *env, IMAGE, "npm", "run", script,
        log=report / (script.replace(":", "-") + ".log"))) for script, env in scripts])


def backend_checks(state, args, vendor):
    report = Path(state["report"])
    capability_check = "if (!function_exists('imagewebp')) { fwrite(STDERR, 'GD WebP support is required.\\n'); exit(1); }"
    for role, image in [("php-cli", args.php_cli), ("php-fpm", args.php_fpm)]:
        stage(state, role + " WebP capability", lambda: job(state, role + "-capability", "--network", "none", *PHP_ENV, "--entrypoint", "php",
                                                            image, "-r", capability_check, log=report / (role + "-capability.log")))
    stage(state, "composer autoload", lambda: job(
        state, "composer", "--network", "none", *PHP_ENV, "--entrypoint", "sh", *php_mounts(state),
        "--mount", f"type=bind,source={vendor},target=/seed/vendor,readonly", "--workdir", "/app",
        args.php_cli, "-c", "cp -a /seed/vendor/. /app/vendor/ && composer dump-autoload --no-scripts --no-plugins",
        log=report / "composer-autoload.log"))
    limits = ["--network", "none", "--cpus", "2", "--memory", "1536m", "--memory-swap", "1536m", "--tmpfs", "/app/var:rw,size=256m", *PHP_ENV]
    checks = [
        ("php-lint", [], ["vendor/bin/phplint"]),
        # Same paths and rules as phpstan.neon; the result cache survives the run, --debug would disable it.
        ("phpstan", ["--mount", f"type=volume,source={PHPSTAN_CACHE},target=/app/var/phpstan"],
         ["vendor/bin/phpstan", "analyse", "--configuration=tools/e2e/phpstan.neon", "--no-progress", "--memory-limit=1G", "-vv"]),
        ("phpunit", [], ["vendor/bin/phpunit", "--colors=never"]),
    ]
    parallel(state, *[lambda name=name, extra=extra, check=check: stage(state, name, lambda: job(
        state, name, *limits, *extra, "--entrypoint", "php", *php_mounts(state), "--workdir", "/app", args.php_cli, *check,
        log=report / (name + ".log"))) for name, extra, check in checks])


def services(state, stand, mysql):
    def run():
        name = stand["prefix"]
        service(state, name + "-mysql", "--network", stand["network"], "--network-alias", "rabit-w02-mysql", "--memory", "1g", "--memory-swap", "1g",
                "--tmpfs", "/var/lib/mysql:rw,size=512m", "--env", "MYSQL_ALLOW_EMPTY_PASSWORD=yes", "--env", "MYSQL_ROOT_HOST=%",
                "--env", "MYSQL_DATABASE=rabit_w02", mysql, "--skip-log-bin")
        service(state, name + "-rabbitmq", "--network", stand["network"], "--network-alias", "rabbitmq", "--memory", "512m", "--memory-swap", "512m",
                "--env", "RABBITMQ_DEFAULT_USER=rebit", "--env", "RABBITMQ_DEFAULT_PASS=rebit", "--env", "RABBITMQ_DEFAULT_VHOST=rebit", RABBITMQ_IMAGE)
        ready([name + "-mysql", "mysqladmin", "--host=127.0.0.1", "ping", "--silent"], "Disposable MySQL did not become ready")
        ready(["--user", "rabbitmq", name + "-rabbitmq", "rabbitmq-diagnostics", "-q", "ping"], "Disposable RabbitMQ did not become ready")
    stage(state, stand["name"] + ": MySQL and RabbitMQ", run)


def open_stand(state, args, stand, notification):
    report = Path(state["report"], stand["name"])
    report.mkdir(exist_ok=True)
    name, network = stand["prefix"], ["--network", stand["network"]]
    php = ["--user", "0", *PHP_ENV, "--entrypoint", "php", *php_mounts(state, stand), "--workdir", "/app"]
    amqp = ["--env", "MESSENGER_TRANSPORT_DSN=amqp://rebit:rebit@rabbitmq:5672/rebit"]
    media = ["--env", "MOREFOTO_PRIVATE_MEDIA_PATH=/runtime/private/media", "--env", "MOREFOTO_PUBLIC_PREVIEW_PATH=/runtime/public/upload/morefoto/previews",
             "--env", "MOREFOTO_PUBLIC_PREVIEW_URL=/upload/morefoto/previews"]

    def fixture():
        output = job(state, stand["name"] + "-prepare", *network, *php, args.php_cli, "-d", "short_open_tag=1", "-d", "date.timezone=UTC",
                     "tools/e2e/prepare.php", log=report / "prepare.log")
        if "fixture ready" not in output:
            raise RuntimeError("Fixture bootstrap did not complete; see prepare.log")
    stage(state, stand["name"] + ": real Bitrix schema and fixture accounts", fixture)

    def durable_notification():
        output = job(state, stand["name"] + "-notification", *network, *php, *amqp, args.php_cli, "-d", "short_open_tag=1", "-d", "date.timezone=UTC",
                     "tools/e2e/verify-notification.php", log=report / "notification.log")
        if "Notification H1 integration passed" not in output:
            raise RuntimeError("Notification integration did not complete; see notification.log")
    if notification:
        stage(state, stand["name"] + ": durable Notification contract on real MySQL", durable_notification)

    def serve():
        service(state, name + "-fpm", *network, "--network-alias", "api-php-fpm", "--user", "0", *PHP_ENV, "--entrypoint", "php-fpm",
                *php_mounts(state, stand), "--env", "APP_ENV=test", "--env", "APP_DEBUG=0", "--env", "REBIT_GEETEST_ENABLED=0",
                "--env", "REBIT_GEETEST_BYPASS=1", *amqp, *media, "--env", "MOREFOTO_CHECKOUT_ENABLED=1", *SUPPORT, args.php_fpm, "-y", "/app/tools/e2e/fpm.conf")
        service(state, name + "-media", *network, *php, *amqp, *media, args.php_cli, "tools/e2e/consume-media.php")
        service(state, name + "-backend", *network, "--network-alias", "backend", *php_mounts(state, stand),
                "--mount", f"type=bind,source={Path(state['report'], 'backend.conf')},target=/etc/nginx/conf.d/default.conf,readonly",
                "--mount", f"type=bind,source={ROOT / 'api/docker/common/nginx/auth.conf'},target=/etc/nginx/auth.conf,readonly", args.nginx)
        frontend = register(state, "container", name + "-frontend")
        docker("create", "--name", frontend, "--label", LABEL + "=" + state["id"], "--network", state["browserNetwork"], "--publish", "127.0.0.1::80",
               "--mount", f"type=bind,source={ROOT / 'frontend/dist'},target=/usr/share/nginx/html,readonly",
               "--mount", f"type=bind,source={Path(state['report'], 'frontend.conf')},target=/etc/nginx/conf.d/default.conf,readonly", "nginx:1.29-alpine")
        docker("network", "connect", "--alias", "frontend", stand["network"], frontend)
        docker("start", frontend)
        time.sleep(1)
        if not json.loads(docker("container", "inspect", name + "-media"))[0]["State"]["Running"]:
            raise RuntimeError("Disposable media worker did not stay running")
        for _ in range(10):
            ports = json.loads(docker("container", "inspect", frontend))[0]["NetworkSettings"]["Ports"].get("80/tcp")
            if ports:
                break
            time.sleep(1)
        else:
            raise RuntimeError("Frontend port was not published")
        with LOCK:
            stand["url"] = "http://localhost:" + ports[0]["HostPort"]
    stage(state, stand["name"] + ": FPM, media worker and nginx", serve)


def environment(images):
    git = ["git", "-C", str(ROOT)]
    memory = next(int(line.split()[1]) // 1024 for line in Path("/proc/meminfo").read_text().splitlines() if line.startswith("MemTotal:"))
    return {
        "commit": command([*git, "rev-parse", "HEAD"]),
        # Paths and a digest identify uncommitted code without copying it into the report.
        "dirtyPaths": [line[3:] for line in subprocess.run([*git, "status", "--porcelain"], capture_output=True, text=True).stdout.splitlines()],
        "diffSha256": hashlib.sha256(subprocess.run([*git, "diff", "HEAD", "--binary"], capture_output=True).stdout).hexdigest(),
        "lockSha256": hashlib.sha256((ROOT / "frontend/package-lock.json").read_bytes()).hexdigest(),
        "images": images,
        "cpus": os.cpu_count(),
        "memoryMiB": memory,
    }


def create():
    identity = "rabit-e2e-" + uuid.uuid4().hex[:12]
    report = ROOT / "api/var/e2e" / identity
    report.mkdir(parents=True)
    state = {"id": identity, "report": str(report), "source": str(ROOT), "stages": [], "stands": [], "results": {},
             "containers": [], "networks": [], "volumes": [], "stopped": False}
    save(state)
    return state


def abort(state):
    """Cleanup after a failure; its own errors are printed so that they never hide the failure itself."""
    try:
        stop(state)
    except Exception as error:
        print("Cleanup failed: " + str(error), file=sys.stderr)


def start(state, args, stands):
    identity, report = state["id"], Path(state["report"])
    builds = []
    for role, option in [("php-cli", "php_cli"), ("php-fpm", "php_fpm")]:
        if getattr(args, option) is None:
            setattr(args, option, f"rabit-api-e2e-{role}:local")
            builds.append(lambda role=role, tag=getattr(args, option): stage(state, f"build checkout {role} image", lambda: docker(
                "build", "--tag", tag, "--file", str(ROOT / f"api/docker/development/{role}/Dockerfile"), str(ROOT / "api/docker"),
                log=report / f"{role}-build.log", timeout=1800)))
    if builds:
        parallel(state, *builds)
    kernel = Path(args.kernel).resolve()
    vendor = Path(args.vendor).resolve()
    state["kernel"] = str(kernel)
    for source in [kernel / "modules/main/install/mysql/install.sql", kernel / "routing_index.php", vendor / "autoload.php", ROOT / "frontend/package-lock.json"]:
        if not source.is_file():
            raise RuntimeError("Missing dependency: " + str(source))
    images = {image: docker("image", "inspect", "--format", "{{.Id}}", image)
              for image in [IMAGE, args.php_cli, args.php_fpm, args.nginx, args.mysql, "nginx:1.29-alpine", RABBITMQ_IMAGE]}
    state["environment"] = environment(images)
    label = LABEL + "=" + identity
    state["browserNetwork"] = register(state, "network", identity + "-browser")
    docker("network", "create", "--label", label, state["browserNetwork"])
    for name, groups in stands:
        stand = {"name": name, "prefix": identity + "-" + name, "groups": groups,
                 "network": register(state, "network", f"{identity}-{name}-private"), "runtime": register(state, "volume", f"{identity}-{name}-runtime")}
        docker("network", "create", "--label", label, "--internal", stand["network"])
        docker("volume", "create", "--label", label, stand["runtime"])
        state["stands"].append(stand)
    for suffix in ["node", "vendor"]:
        state[suffix] = register(state, "volume", identity + "-" + suffix)
        docker("volume", "create", "--label", label, state[suffix])
    frontend = ROOT / "frontend"
    (ROOT / "api/vendor").mkdir(exist_ok=True)
    (frontend / "var").mkdir(exist_ok=True)
    state["nodeArgs"] = ["--cpus", "2", "--memory", "3g", "--memory-swap", "3g", "--mount", f"type=bind,source={frontend},target=/app",
                         "--mount", f"type=volume,source={state['node']},target=/app/node_modules", "--workdir", "/app"]
    # Reuse the application's actual nginx routing and header forwarding configuration.
    (report / "backend.conf").write_text((ROOT / "api/docker/common/nginx/conf.d/default.conf").read_text().replace("root /app/public;", "root /runtime/public;"))
    frontend_config = (frontend / "docker/production/nginx/conf.d/default.conf").read_text()
    frontend_config = frontend_config.replace("${API_UPSTREAM}", "http://backend").replace("${API_HOST}", "backend")
    frontend_config = frontend_config.replace("    location = /health {", '''    location = /__e2e {
        default_type application/json;
        return 200 '{"fixture":"rabit-real-e2e"}';
    }
    location = /health {''')
    (report / "frontend.conf").write_text(frontend_config)
    save(state)
    parallel(state, lambda: frontend_checks(state), lambda: backend_checks(state, args, vendor),
             *[lambda stand=stand: services(state, stand, args.mysql) for stand in state["stands"]])
    parallel(state, *[lambda stand=stand, first=0 == index: open_stand(state, args, stand, first) for index, stand in enumerate(state["stands"])])
    state["createdAt"] = datetime.datetime.now(datetime.timezone.utc).isoformat()
    save(state)
    for stand in state["stands"]:
        print(f"Browser {stand['name']}: {stand['url']}", flush=True)
    print("State: " + str(report / "state.json"), flush=True)


def check_results(group, path):
    """Accepts a group only when every one of its spec files ran and each test passed at the first attempt."""
    results = json.loads(path.read_text())
    stats, files = results["stats"], {}

    def count(suite, file):
        files[file] = files.get(file, 0) + sum(len(spec["tests"]) for spec in suite.get("specs", []))
        for child in suite.get("suites", []):
            count(child, file)
    for suite in results["suites"]:
        count(suite, suite["file"])
    expected = {name + ".spec.ts" for name in GROUPS[group] if BENCH != name or os.environ.get("E2E_MEDIA_BENCH")}
    if results["errors"] or stats["unexpected"] or stats["skipped"] or stats["flaky"] or set(files) != expected or sum(files.values()) != stats["expected"]:
        raise RuntimeError(f"Browser group {group} incomplete: " + json.dumps({"stats": stats, "files": files, "expectedFiles": sorted(expected)}))
    return {"passed": stats["expected"], "seconds": round(stats["duration"] / 1000, 1), "files": files}


def test_stand(state, stand):
    if state["stopped"]:
        raise RuntimeError("The E2E fixture has been stopped")
    owned("network", stand["network"], state["id"])
    report = Path(state["report"], stand["name"])
    fpm = stand["prefix"] + "-fpm"
    var = report / "var"
    var.mkdir(exist_ok=True)

    def storefront():
        docker("exec", fpm, "php", "/app/tools/e2e/prepare-storefront.php", log=report / "storefront-fixture.log")
        docker("cp", fpm + ":/runtime/e4-fixture.json", str(var / "e4-fixture.json"))
    stage(state, stand["name"] + ": E4 gallery fixture through internal lifecycle", storefront)
    bench = [arg for name in ("E2E_MEDIA_BENCH", "E2E_MEDIA_BENCH_COUNT") if os.environ.get(name) for arg in ("--env", name + "=" + os.environ[name])]
    for group in stand["groups"]:
        output = report / group
        output.mkdir(exist_ok=True)

        def browser():
            # Fixture files and reports belong to the stand and group: concurrent groups never share mutable paths.
            job(state, f"{stand['name']}-browser-{group}", "--network", "container:" + stand["prefix"] + "-frontend", "--shm-size=1g", *state["nodeArgs"],
                "--mount", f"type=bind,source={var},target=/app/var", "--mount", f"type=bind,source={output},target=/app/reports/e2e-live",
                "--env", "E2E_BASE_URL=http://127.0.0.1", *bench, IMAGE, "npm", "run", "test:e2e:live", "--", "--project", group, log=output / "browser.log")
            return check_results(group, output / "results.json")
        result = stage(state, f"{stand['name']}: real browser group {group}", browser)
        with LOCK:
            state["results"][group] = result
            save(state)
        for script, file, marker, specs in VERIFIERS:
            if set(specs) <= set(GROUPS[group]):
                def verify():
                    if file:
                        docker("cp", str(var / file), fpm + ":/runtime/" + file)
                    if marker not in docker("exec", fpm, "php", "/app/tools/e2e/" + script, log=report / script.replace(".php", ".log")):
                        raise RuntimeError(script + " did not complete")
                stage(state, f"{stand['name']}: {script}", verify)


def test_live(state):
    parallel(state, *[lambda stand=stand: test_stand(state, stand) for stand in state["stands"]])
    passed = {group: result["passed"] for group, result in state["results"].items()}
    with LOCK:
        state["scope"] = "full gate" if sorted(passed) == sorted(GROUPS) else "PARTIAL run, not a full gate"
        save(state)
    print(f"Browser scenarios passed: {sum(passed.values())} {passed} ({state['scope']})", flush=True)


def summary(state):
    for record in state["stages"]:
        print(f"{record['startedAt']:7.1f} s  {record.get('seconds', 0):6.1f} s  {record['status']:9}  {record['name']}", flush=True)
    print(f"Total: {round(time.monotonic() - STARTED, 1)} s", flush=True)


def validate(groups):
    specs = sorted(path.name[:-len(".spec.ts")] for path in LIVE.glob("*.spec.ts"))
    if sorted(name for names in GROUPS.values() for name in names) != specs:
        raise RuntimeError("frontend/e2e/live/groups.json must list every live spec file exactly once")
    if not groups or set(groups) - set(GROUPS):
        raise RuntimeError("Unknown E2E group; available: " + ", ".join(GROUPS))
    for script, _, _, needed in VERIFIERS:
        if not any(set(needed) <= set(names) for names in GROUPS.values()):
            raise RuntimeError(f"{script} needs {', '.join(needed)} in one browser group")


def main():
    for signum in (signal.SIGTERM, signal.SIGHUP):
        signal.signal(signum, lambda number, frame: sys.exit(128 + number))  # Cleanup runs as after Ctrl+C.
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("action", choices=["run", "up", "test", "down"], default="run", nargs="?")
    parser.add_argument("--state", type=Path)
    parser.add_argument("--kernel", default=os.environ.get("E2E_KERNEL_ROOT", "/home/user/rebit-p2p/api/public/bitrix"))
    parser.add_argument("--vendor", default=os.environ.get("E2E_VENDOR_ROOT", "/home/user/rebit-p2p/api/vendor"))
    parser.add_argument("--php-cli", default=os.environ.get("E2E_PHP_CLI_IMAGE"))
    parser.add_argument("--php-fpm", default=os.environ.get("E2E_PHP_FPM_IMAGE"))
    parser.add_argument("--nginx", default=os.environ.get("E2E_NGINX_IMAGE", "rabit-api-nginx:20260911-074507"))
    parser.add_argument("--mysql", default=os.environ.get("E2E_MYSQL_IMAGE", "mysql:8.0"))
    parser.add_argument("--groups", default=os.environ.get("E2E_GROUPS") or ",".join(GROUPS), help="browser groups; a subset is not a full gate")
    parser.add_argument("--single-stand", action="store_true", default="1" == os.environ.get("E2E_STANDS"),
                        help="run the groups one after another on one stand (E2E_STANDS=1)")
    args = parser.parse_args()
    if args.action in ["test", "down"]:
        if not args.state:
            parser.error("--state is required")
        state = json.loads(args.state.read_text())
        report = Path(state["report"]).resolve()
        if not report.is_relative_to(ROOT / "api/var/e2e") or Path(state["source"]).resolve() != ROOT:
            raise RuntimeError("State does not belong to this checkout")
        if "down" == args.action:
            stop(state)
            return
        try:
            test_live(state)
        finally:
            save(state)
            summary(state)
        return
    groups = [group.strip() for group in args.groups.split(",") if group.strip()]
    validate(groups)
    state = create()
    try:
        start(state, args, [("all", groups)] if "up" == args.action or args.single_stand else [(group, [group]) for group in groups])
        if "run" == args.action:
            test_live(state)
    except BaseException:
        abort(state)
        raise
    else:
        if "run" == args.action:
            stop(state)
    finally:
        summary(state)


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
