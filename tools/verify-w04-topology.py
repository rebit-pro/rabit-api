#!/usr/bin/env python3
"""Run the real Docker CLI formatter against a synthetic read-only Unix Engine API."""
from copy import deepcopy
from http.server import BaseHTTPRequestHandler
import json
import os
from pathlib import Path
import re
import shutil
from socketserver import UnixStreamServer
import subprocess
import tempfile
import threading

ROOT = Path(__file__).resolve().parents[1]


def check(condition, message):
    if not condition:
        raise RuntimeError(message)


class FixtureServer(UnixStreamServer):
    scenario = None
    requests = None


class EngineHandler(BaseHTTPRequestHandler):
    def log_message(self, *_args):
        pass

    def reject_mutation(self):
        self.server.requests.append((self.command, self.path))
        self.send_error(405)

    do_POST = reject_mutation
    do_PUT = reject_mutation
    do_DELETE = reject_mutation

    def do_GET(self):
        path = re.sub(r"^/v[0-9.]+", "", self.path).split("?", 1)[0]
        self.server.requests.append(("GET", path))
        scenario = self.server.scenario
        if path == "/info":
            data = {"Swarm": scenario["swarm"]}
        elif path == "/nodes":
            data = scenario["nodes"]
        elif path.startswith("/nodes/"):
            data = next((node for node in scenario["nodes"] if node["ID"] == path.split("/")[-1]), None)
        else:
            data = None
        encoded = json.dumps(data if data is not None else {"message": "fixture endpoint not found"}).encode()
        self.send_response(200 if data is not None else 404)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(encoded)))
        self.end_headers()
        self.wfile.write(encoded)


def main():
    docker = shutil.which("docker")
    check(docker is not None, "Docker CLI is required")
    local_id, foreign_id = "localfixture00000000000000", "otherfixture00000000000000"
    node = {"ID": local_id, "Version": {"Index": 1},
            "Spec": {"Role": "manager", "Availability": "active", "Labels": {"db": "db"}},
            "Status": {"State": "ready"},
            "Description": {"Hostname": "fixture-local"},
            "ManagerStatus": {"Leader": True, "Reachability": "reachable", "Addr": "127.0.0.1:2377"}}
    base = {"swarm": {"LocalNodeState": "active", "ControlAvailable": True, "NodeID": local_id}, "nodes": [node]}
    cases = {name: deepcopy(base) for name in [
        "single-local-db-node", "multi-node", "foreign-db-node",
        "missing-db-label", "drained-node", "down-node", "worker-target", "inactive-swarm",
    ]}
    other = deepcopy(node)
    other["ID"] = foreign_id
    cases["multi-node"]["nodes"].append(other)
    cases["foreign-db-node"]["nodes"] = [other]
    cases["missing-db-label"]["nodes"][0]["Spec"]["Labels"] = {}
    cases["drained-node"]["nodes"][0]["Spec"]["Availability"] = "drain"
    cases["down-node"]["nodes"][0]["Status"]["State"] = "down"
    cases["worker-target"]["swarm"]["ControlAvailable"] = False
    cases["inactive-swarm"]["swarm"]["LocalNodeState"] = "inactive"
    with tempfile.TemporaryDirectory(prefix="rabit-w04-topology-") as temporary:
        fixture = Path(temporary)
        wrapper = fixture / "docker"
        wrapper.write_text("""#!/usr/bin/env python3
import os, sys
args = sys.argv[1:]
if args[:2] != ["--host", "unix:///var/run/docker.sock"]:
    raise SystemExit("Guard must explicitly address the local socket")
os.environ.pop("DOCKER_CONTEXT", None)
os.environ["DOCKER_API_VERSION"] = "1.43"
os.execv(os.environ["REAL_DOCKER"], ["docker", "--host", "unix://" + os.environ["FIXTURE_SOCKET"], *args[2:]])
""")
        wrapper.chmod(0o700)
        socket = fixture / "engine.sock"
        server = FixtureServer(str(socket), EngineHandler)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        results = []
        try:
            for name, scenario in cases.items():
                server.scenario, server.requests = scenario, []
                env = {"PATH": str(fixture) + os.pathsep + os.environ["PATH"],
                       "HOME": str(fixture), "REAL_DOCKER": docker, "FIXTURE_SOCKET": str(socket)}
                result = subprocess.run(["bash", str(ROOT / "deploy/swarm-verify-local-data-node.sh")], env=env, text=True, capture_output=True)
                successful = name == "single-local-db-node"
                check((result.returncode == 0) == successful, name + ": unexpected guard result: " + result.stderr)
                check(result.stdout == "", name + ": diagnostics entered stdout")
                check(all(method == "GET" for method, _ in server.requests), "Mutating Engine API request")
                check(bool(server.requests), "Real CLI did not call fixture Engine API")
                results.append(name)
        finally:
            server.shutdown()
            server.server_close()
            thread.join()
    makefile = (ROOT / "Makefile").read_text()
    guard_line = "ssh $(REMOTE) -p $(PORT) 'bash -s' < deploy/swarm-verify-local-data-node.sh"
    check("deploy: deploy-check-env\n\t" + guard_line in makefile, "Deploy must check topology before transfers/mutations")
    check("rollback: guard-HOST guard-STACK_NAME\n\t" + guard_line in makefile, "Rollback topology guard missing")
    check(makefile.count("export DOCKER_HOST=unix:///var/run/docker.sock && unset DOCKER_CONTEXT") == 2, "Release commands may follow a different Docker context")
    print(json.dumps({"status": "PASS", "scenarios": results, "realDockerCliFormatting": True,
                      "syntheticUnixEngineApi": True, "readOnlyEngineRequests": True,
                      "liveDockerDaemonUsed": False, "productionUsed": False}, indent=2))


if __name__ == "__main__":
    main()
