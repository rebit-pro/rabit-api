"""Unit checks of the E2E runner logic that needs no Docker: prune decisions, owner check, labels and timeouts.
Run: python3 -m unittest discover -s tools/tests -v
"""
import datetime
import importlib.util
import os
from pathlib import Path
import unittest
from unittest import mock

SPEC = importlib.util.spec_from_file_location("runner", Path(__file__).resolve().parents[1] / "run-browser-e2e.py")
runner = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(runner)

RUN = "rabit-e2e-0123456789ab"
NOW = datetime.datetime(2026, 9, 25, 12, 0, tzinfo=datetime.timezone.utc)
HOUR = datetime.timedelta(hours=1)


def resource(kind="container", suffix="mysql", running=False, age=HOUR * 48, owner=None, run=RUN):
    return {"kind": kind, "name": run + "-" + suffix, "running": running, "created": NOW - age, "owner": owner, "report": None}


def stand(**container):
    """A stopped stand of three kinds of resources, like the leftovers of 12–13.09."""
    return [resource(**container), resource("network", "private"), resource("volume", "runtime")]


class Verdict(unittest.TestCase):
    def verdict(self, resources, alive=lambda owner: None, run=RUN):
        return runner.verdict(run, resources, NOW, 2 * HOUR, alive)

    def test_abandoned_stopped_run_without_owner_label_is_removed(self):
        self.assertIsNone(self.verdict(stand()))

    def test_running_container_always_keeps_the_run(self):
        self.assertIn("running containers", self.verdict(stand(running=True), alive=lambda owner: False))

    def test_live_owner_keeps_a_run_without_containers(self):
        # start() creates networks and volumes before any container, e.g. while images build.
        resources = [resource("network", "browser", owner="o"), resource("volume", "node", owner="o")]
        self.assertIn("still alive", self.verdict(resources, alive=lambda owner: True))

    def test_dead_owner_is_removed_only_when_old_enough(self):
        self.assertIsNone(self.verdict(stand(owner="o"), alive=lambda owner: False))
        young = stand(owner="o") + [resource("volume", "vendor", age=HOUR / 2)]
        self.assertIn("younger than 2 h", self.verdict(young, alive=lambda owner: False))

    def test_unknown_owner_relies_on_age(self):
        self.assertIn("younger", self.verdict([resource(age=HOUR, owner="o")]))

    def test_foreign_names_and_labels_are_never_removed(self):
        self.assertIn("do not belong", self.verdict(stand(), run="rabit-a8-1789211965"))
        self.assertIn("do not belong", self.verdict(stand(), run=""))
        self.assertIn("do not belong", self.verdict(stand() + [resource("volume", "x", run="rabit-e2e-ffffffffffff")]))

    def test_unknown_creation_time_keeps_the_run(self):
        resources = stand()
        resources[1]["created"] = None
        self.assertEqual("creation time unknown", self.verdict(resources))


class Owner(unittest.TestCase):
    def test_this_process_is_alive(self):
        self.assertIs(True, runner.owner_alive(runner.process_identity()))

    def test_gone_or_reused_pid_is_dead(self):
        host, namespace, pid, started = runner.process_identity().rsplit(":", 3)
        self.assertIs(False, runner.owner_alive(":".join([host, namespace, pid, str(int(started) + 1)])))
        self.assertIs(False, runner.owner_alive(":".join([host, namespace, "999999999", started])))

    def test_other_host_or_namespace_cannot_be_checked(self):
        host, namespace, pid, started = runner.process_identity().rsplit(":", 3)
        self.assertIsNone(runner.owner_alive(":".join(["another-host", namespace, pid, started])))
        self.assertIsNone(runner.owner_alive(":".join([host, "[1]", pid, started])))
        self.assertIsNone(runner.owner_alive("garbage"))


class Labels(unittest.TestCase):
    def test_new_state_labels_report_and_owner(self):
        state = {"id": RUN, "report": "/r", "owner": "h:[1]:2:3"}
        self.assertEqual(["--label", "rabit.browser_e2e=" + RUN, "--label", "rabit.browser_e2e.report=/r",
                          "--label", "rabit.browser_e2e.owner=h:[1]:2:3"], runner.labels(state))

    def test_state_of_an_older_runner_keeps_working(self):
        self.assertEqual(["--label", "rabit.browser_e2e=" + RUN, "--label", "rabit.browser_e2e.report=/r"],
                         runner.labels({"id": RUN, "report": "/r"}))


class CreatedAt(unittest.TestCase):
    def test_docker_formats(self):
        expected = datetime.datetime(2026, 9, 12, 12, 13, 36, tzinfo=datetime.timezone.utc)
        self.assertEqual(expected, runner.created_at("2026-09-12T12:13:36.561087154Z"))
        self.assertEqual(expected, runner.created_at("2026-09-12T12:13:36Z"))
        self.assertEqual(expected, runner.created_at("2026-09-12T15:13:36+03:00"))
        self.assertIsNone(runner.created_at("12.09.2026"))
        self.assertIsNone(runner.created_at(None))


class Prune(unittest.TestCase):
    def test_apply_removes_only_abandoned_runs_in_dependency_order(self):
        live = "rabit-e2e-aaaaaaaaaaaa"
        runs = {RUN: stand(), live: [resource(running=True, run=live), resource("volume", "node", run=live)]}
        calls = []
        with mock.patch.object(runner, "inventory", return_value=runs), \
                mock.patch.object(runner, "docker", side_effect=lambda *args, **kwargs: calls.append(args)), \
                mock.patch("builtins.print"):
            runner.prune(True, 2)
        self.assertEqual([("rm", RUN + "-mysql"), ("network", "rm", RUN + "-private"), ("volume", "rm", RUN + "-runtime")], calls)

    def test_dry_run_removes_nothing(self):
        with mock.patch.object(runner, "inventory", return_value={RUN: stand()}), \
                mock.patch.object(runner, "docker") as docker, mock.patch("builtins.print"):
            runner.prune(False, 2)
        docker.assert_not_called()


class Groups(unittest.TestCase):
    def test_groups_and_verifiers_are_consistent(self):
        runner.validate(list(runner.GROUPS))

    def test_f2_prepares_first_and_reports_later_in_one_group(self):
        needed = next(specs for script, _, _, specs in runner.VERIFIERS if "verify-links.php" == script)
        group = next(names for names in runner.GROUPS.values() if set(needed) <= set(names))
        self.assertEqual("z-links-preparation", sorted(group)[0])
        self.assertLess(sorted(group).index("z-links-preparation"), sorted(group).index("zzzz-links"))


if __name__ == "__main__":
    unittest.main()
