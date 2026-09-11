#!/usr/bin/env python3
"""W04 offline shell/Compose checks. Fake Docker handles publisher calls; no Swarm mutations."""
import json
import os
from pathlib import Path
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]


def check(condition, message):
    if not condition:
        raise RuntimeError(message)


def run(args, env, cwd=ROOT):
    return subprocess.run(args, cwd=cwd, env=env, text=True, capture_output=True)


def verify_publisher(directory):
    fake_bin = directory / "bin"
    fake_bin.mkdir()
    fake = fake_bin / "docker"
    fake.write_text(r"""#!/usr/bin/env python3
import json, os
from pathlib import Path
import sys
args = sys.argv[1:]
with open(os.environ["FAKE_CALLS"], "a") as output:
    output.write(json.dumps(args) + "\n")
if args[:1] == ["info"]:
    print(os.environ.get("FAKE_SWARM", "active") if "LocalNodeState" in args[-1] else os.environ.get("FAKE_MANAGER", "true"))
elif len(args) >= 2 and args[0] in ("config", "secret") and args[1] == "inspect":
    sys.exit(0 if os.environ.get("FAKE_EXISTS") == "yes" else 1)
elif len(args) == 4 and args[0] in ("config", "secret") and args[1] == "create":
    if not Path(args[3]).is_file():
        sys.exit(12)
    print("fixture-object-id")
else:
    sys.exit(13)
""")
    fake.chmod(0o700)
    script = ROOT / "deploy/swarm-publish-runtime.sh"
    checked = []
    for name in ["missing-optional", "primary-and-legacy", "immutable", "missing-required", "not-manager", "inactive-swarm", "unreadable-required"]:
        case = directory / name
        (case / "secrets with spaces").mkdir(parents=True)
        (case / "legacy").mkdir()
        backend = case / "backend.env"
        backend.write_text("FIXTURE_ONLY=not-a-real-config\n")
        required = case / "secrets with spaces" / "required_key"
        if name != "missing-required":
            required.write_text("SENTINEL_SECRET_BYTES_NEVER_LOG\n")
        if name == "unreadable-required":
            required.chmod(0)
        if name == "primary-and-legacy":
            (case / "secrets with spaces" / "optional_primary").write_text("SENTINEL_SECRET_BYTES_NEVER_LOG\n")
            (case / "legacy" / "optional_legacy").write_text("SENTINEL_SECRET_BYTES_NEVER_LOG\n")
        calls_file = case / "calls.jsonl"
        output = case / "runtime.env"
        env = {
            "PATH": str(fake_bin) + os.pathsep + os.environ["PATH"],
            "HOME": str(case),
            "VERSION": "fixture42",
            "BACKEND_ENV_FILE": str(backend),
            "SECRETS_DIR": str(case / "secrets with spaces"),
            "LEGACY_SECRET_DIR": str(case / "legacy"),
            "REQUIRED_SECRET_NAMES": "required_key",
            "OPTIONAL_SECRET_NAMES": "optional_primary optional_legacy",
            "OUTPUT_ENV_FILE": str(output),
            "FAKE_CALLS": str(calls_file),
        }
        if name == "immutable":
            env["FAKE_EXISTS"] = "yes"
        if name == "not-manager":
            env["FAKE_MANAGER"] = "false"
        if name == "inactive-swarm":
            env["FAKE_SWARM"] = "inactive"
        result = run(["bash", str(script)], env)
        calls = [json.loads(line) for line in calls_file.read_text().splitlines()]
        creates = [call for call in calls if len(call) > 1 and call[1] == "create"]
        successful = name in ("missing-optional", "primary-and-legacy", "immutable")
        check((result.returncode == 0) == successful, name + ": wrong exit status")
        check("SENTINEL_SECRET_BYTES_NEVER_LOG" not in result.stdout + result.stderr, "Fixture secret leaked")
        if not successful:
            check(not creates, name + ": mutation attempted after failed preflight")
            check(not output.exists(), name + ": output written after failure")
        else:
            check(output.is_file(), name + ": output mapping missing")
            check("[swarm-runtime]" not in result.stdout, name + ": diagnostic text entered stdout")
            for call in creates:
                check(Path(call[3]).is_file(), name + ": log text was used as a source path")
            lines = output.read_text()
            if name == "missing-optional":
                check("OPTIONAL_" not in lines, "Missing optional secret was registered")
                check("Optional secret file not found" in result.stderr, "Skipped secret diagnostic missing")
            if name == "primary-and-legacy":
                secret_paths = {call[3] for call in creates if call[0] == "secret"}
                check(secret_paths == {
                    str(required),
                    str(case / "secrets with spaces" / "optional_primary"),
                    str(case / "legacy" / "optional_legacy"),
                }, "Primary/legacy source priority or path quoting failed")
            if name == "immutable":
                check(not creates, "Existing immutable objects were recreated")
        required.chmod(0o600) if required.exists() else None
        checked.append(name)
    return checked


def verify_compose(directory):
    env_file = directory / "compose.env"
    values = {
        "REGISTRY": "fixture.invalid", "IMAGE_TAG": "w04-fixture",
        "MYSQL_PASSWORD": "fixture-only", "MYSQL_ROOT_PASSWORD": "fixture-only",
        "REBIT_ENCRYPTION_KEY": "fixture-only",
        "MYSQL_VOLUME_NAME": "existing_mysql_fixture",
        "RABBITMQ_VOLUME_NAME": "existing_rabbitmq_fixture",
        "RUNTIME_DATA_DIR": "/existing-runtime-fixture",
        "BACKEND_ENV_CONFIG_NAME": "web_config_fixture",
        "CRON_ENV_CONFIG_NAME": "cron_config_fixture",
    }
    for key in ["ENCRYPTION_KEY", "GEETEST_CAPTCHA_KEY", "MYSQL_PASSWORD", "MYSQL_ROOT_PASSWORD", "SMTP_PASSWORD", "RABBITMQ_PASSWORD", "TELEGRAM_BOT_TOKEN"]:
        values["REBIT_" + key + "_SECRET_NAME"] = "fixture_" + key.lower()
    env = {"PATH": os.environ["PATH"], "HOME": str(directory), "LANG": "C.UTF-8"}

    def config(filename, missing=None):
        env_file.write_text("".join(key + "=" + value + "\n" for key, value in values.items() if key != missing))
        result = run(["docker", "compose", "--profile", "*", "--env-file", str(env_file), "-f", str(ROOT / filename), "config", "--format", "json"], env)
        if missing:
            check(result.returncode != 0, filename + ": missing " + missing + " was accepted")
            return None
        check(result.returncode == 0, filename + ": config failed: " + result.stderr)
        return json.loads(result.stdout)

    for filename in ["docker-compose.yml", "docker-compose-production.yml"]:
        config(filename, "MYSQL_VOLUME_NAME")
        config(filename, "RABBITMQ_VOLUME_NAME")
        model = config(filename)
        for key, expected in [("api-mysql", "existing_mysql_fixture"), ("rabbitmq-data", "existing_rabbitmq_fixture")]:
            check(model["volumes"][key]["external"] is True, filename + ": volume must be external")
            check(model["volumes"][key]["name"] == expected, filename + ": volume changed with project name")
        check("app:audit:consume" in " ".join(model["services"]["api-audit-consumer"]["command"]), "Audit handler command changed")
        check("supercronic" in " ".join(model["services"]["api-cron"]["command"]), "LeadHunter cron service missing")
        if filename == "docker-compose-production.yml":
            check(model["services"]["api-audit-consumer"]["deploy"]["replicas"] == 0, "Audit consumer enabled by default")
            check(model["services"]["api-cron"]["configs"][0]["source"] == "rebit_cron_env", "Cron config merged into web")
            check(model["configs"]["rebit_cron_env"]["name"] == "cron_config_fixture", "Cron runtime config lost")
            check(model["services"]["api-cron"]["deploy"]["replicas"] == 1, "LeadHunter cron replica changed")
            for service in ["api", "api-php-fpm", "api-cron", "api-audit-consumer"]:
                for mount in model["services"][service]["volumes"]:
                    if mount["type"] == "bind":
                        check(mount["source"].startswith("/existing-runtime-fixture/"), "Runtime bind moved")
            config(filename, "RUNTIME_DATA_DIR")
            config(filename, "CRON_ENV_CONFIG_NAME")
        else:
            check(model["services"]["api-audit-consumer"]["profiles"] == ["audit"], "Audit profile missing")
    makefile = (ROOT / "Makefile").read_text()
    check("--prune" not in makefile, "Deployment may prune other legacy stack services")
    check("&& { env |" in makefile and "|| true; }" in makefile, "Optional environment export masks previous deployment failures")
    cron = (ROOT / "api/docker/common/cron/crontab").read_text()
    check("*/5 * * * * cd /app/public && php local/bin/bitrix-console app:leadhunter:scan" in cron, "LeadHunter scan cadence changed")
    guarded = run(["make", "ENV_FILE=/dev/null", "deploy-check-env", "HOST=fixture.invalid", "BUILD_NUMBER=fixture", "REGISTRY=fixture.invalid", "IMAGE_TAG=fixture"], env)
    check(guarded.returncode != 0 and "STACK_NAME" in guarded.stdout, "Deployment has an implicit stack name")
    synthetic_token = "SYNTHETIC_TOKEN_W04_MUST_NOT_ENTER_SSH"
    deploy_preview = run([
        "make", "--dry-run", "ENV_FILE=/dev/null", "deploy",
        "HOST=fixture.invalid", "BUILD_NUMBER=fixture", "REGISTRY=fixture.invalid", "IMAGE_TAG=fixture",
        "STACK_NAME=fixture", "RUNTIME_DATA_DIR=/fixture",
        "MYSQL_VOLUME_NAME=fixture_mysql", "RABBITMQ_VOLUME_NAME=fixture_rabbitmq",
        "CRON_ENV_CONFIG_NAME=fixture_cron", "TOKEN_GIT_HUB=" + synthetic_token,
    ], env)
    check(deploy_preview.returncode == 0, "Deployment preview failed")
    check(synthetic_token not in deploy_preview.stdout + deploy_preview.stderr,
          "Registry token entered deployment command arguments/output")
    check("docker login" not in deploy_preview.stdout, "Deploy must use preconfigured registry credentials")
    migration = run(["make", "ENV_FILE=/dev/null", "api-migrate-deploy"], env)
    check(migration.returncode != 0, "Unsafe production migration shortcut remains enabled")
    return ["development", "production", "missing-volume-guards", "runtime-and-cron-guards", "legacy-stack-preservation", "registry-token-not-in-deploy-dry-run"]


def main():
    with tempfile.TemporaryDirectory(prefix="rabit-w04-runtime-") as temporary:
        directory = Path(temporary)
        result = {
            "status": "PASS",
            "publisherScenarios": verify_publisher(directory),
            "composeScenarios": verify_compose(directory),
            "realSecretFilesRead": False,
            "swarmMutationsExecuted": False,
            "servicesStarted": False,
        }
        print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
