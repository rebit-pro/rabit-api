"""#141: the media worker writes previews that PHP-FPM (www-data) deletes, so it must never run as root.
Run: python3 -m unittest discover -s tools/tests -v
"""
from pathlib import Path
import subprocess
import tempfile
import unittest

import yaml

ROOT = Path(__file__).resolve().parents[2]


def service(compose, name):
    return yaml.safe_load((ROOT / compose).read_text())["services"][name]


class MediaWorkerUser(unittest.TestCase):
    def test_production_and_dev_workers_drop_to_www_data(self):
        # J1: the files worker writes archives that nginx (uid 1000) serves, so it drops to www-data as well.
        for compose, name in [(c, n) for c in ["docker-compose-production.yml", "docker-compose.yml"] for n in ["api-media-consumer", "api-files-consumer"]]:
            with self.subTest(compose=compose, service=name):
                worker = service(compose, name)
                self.assertEqual("www-data", worker["environment"]["APP_RUN_AS_USER"])
                self.assertNotIn("user", worker, "root preparation of the entrypoint needs root; it drops privileges itself")

    def test_other_cli_services_keep_root(self):
        for name in ["api-cron", "api-audit-consumer", "api-notification-consumer", "api-support-consumer"]:
            with self.subTest(service=name):
                self.assertNotIn("APP_RUN_AS_USER", service("docker-compose-production.yml", name).get("environment") or {})

    def entrypoint(self, uid, name, run_as=None):
        """Runs the real entrypoint with `id` and `setpriv` replaced by stubs; no /app, so the root preparation is a no-op."""
        with tempfile.TemporaryDirectory() as stubs:
            for tool, body in [("id", f'[ "$1" = "-u" ] && echo {uid} || echo {name}'), ("setpriv", 'echo "setpriv $*"')]:
                Path(stubs, tool).write_text("#!/bin/sh\n" + body + "\n")
                Path(stubs, tool).chmod(0o755)
            env = {"PATH": stubs + ":/usr/bin:/bin", **({"APP_RUN_AS_USER": run_as} if run_as else {})}
            return subprocess.run(["sh", str(ROOT / "api/docker/common/php/docker-entrypoint.sh"), "echo", "worker"],
                                  capture_output=True, text=True, env=env)

    @unittest.skipIf(Path("/app").exists(), "the entrypoint would prepare a real /app")
    def test_entrypoint_drops_privileges_only_when_asked(self):
        self.assertEqual("worker", self.entrypoint(0, "root").stdout.strip())
        dropped = self.entrypoint(0, "root", "www-data")
        self.assertEqual("setpriv --reuid=www-data --regid=www-data --init-groups echo worker", dropped.stdout.strip())
        self.assertEqual("worker", self.entrypoint(1000, "www-data", "www-data").stdout.strip())
        foreign = self.entrypoint(1000, "nobody", "www-data")
        self.assertEqual((1, ""), (foreign.returncode, foreign.stdout))
        self.assertIn("Cannot switch from nobody to APP_RUN_AS_USER=www-data", foreign.stderr)

    def test_e2e_worker_runs_as_www_data_and_is_checked(self):
        runner = (ROOT / "tools/run-browser-e2e.py").read_text()
        self.assertIn('service(state, name + "-media", *network, "--user", "www-data", *cli,', runner)
        self.assertIn('service(state, name + "-files", *network, "--user", "www-data", *cli,', runner)
        self.assertIn('for worker in ["media", "files"]:', runner)
        self.assertIn('"www-data" != docker("exec", name + "-" + worker, "stat", "-c", "%U", "/proc/1")', runner)
        # The E4 fixture renders previews through the same handler; a root-owned <xx> directory would block the worker.
        self.assertIn('docker("exec", "--user", "www-data", fpm, "php", "/app/tools/e2e/prepare-storefront.php"', runner)


if __name__ == "__main__":
    unittest.main()
