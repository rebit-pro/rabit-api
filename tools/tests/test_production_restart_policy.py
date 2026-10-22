"""OPS 18.10.2026: services exit cleanly on host shutdown, so only `any` brings them back after a reboot.
Run: python3 -m unittest discover -s tools/tests -v
"""
from pathlib import Path
import unittest

import yaml

ROOT = Path(__file__).resolve().parents[2]


class ProductionRestartPolicy(unittest.TestCase):
    def test_every_service_restarts_after_a_clean_exit(self):
        services = yaml.safe_load((ROOT / "docker-compose-production.yml").read_text())["services"]
        for name, service in services.items():
            policy = (service.get("deploy") or {}).get("restart_policy")
            if policy is None:
                continue  # Swarm defaults to `any` without a limit.
            with self.subTest(service=name):
                self.assertEqual("any", policy.get("condition", "any"))
                self.assertNotIn("max_attempts", policy)

    def test_rabbitmq_keeps_its_node_across_tasks(self):
        # The node name follows the hostname; it must come from the deploy, like the volume that holds the node.
        compose = (ROOT / "docker-compose-production.yml").read_text()
        rabbitmq = yaml.safe_load(compose)["services"]["rabbitmq"]
        self.assertRegex(rabbitmq["hostname"], r"^\$\{RABBITMQ_HOSTNAME:\?.+\}$")
        makefile = (ROOT / "Makefile").read_text()
        self.assertIn("\tguard-RABBITMQ_HOSTNAME \\\n", makefile)
        self.assertIn("RABBITMQ_HOSTNAME=%s\\n", makefile)


if __name__ == "__main__":
    unittest.main()
