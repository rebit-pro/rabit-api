#!/usr/bin/env python3
"""Synthetic MySQL dump/restore rehearsal in two disposable volumes; no network or ports."""
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import time
import uuid


def check(condition, message):
    if not condition:
        raise RuntimeError(message)


def docker(*args, input=None, allow_fail=False):
    result = subprocess.run(["docker", *args], input=input, text=True, capture_output=True)
    if not allow_fail:
        check(result.returncode == 0, "Disposable MySQL command failed: " + result.stderr)
    return result


def sql(container, query):
    return docker("exec", "-i", container, "mysql", "--defaults-extra-file=/fixture/client.cnf",
                  "--batch", "--skip-column-names", input=query).stdout.strip()


def main():
    token = "rabit-w04-" + uuid.uuid4().hex[:12]
    containers = [token + "-source", token + "-restore"]
    volumes = [name + "-data" for name in containers]
    created_volumes = []
    started = time.monotonic()
    with tempfile.TemporaryDirectory(prefix="rabit-w04-restore-") as temporary:
        fixture = Path(temporary)
        fixture.chmod(0o755)
        password = uuid.uuid4().hex
        (fixture / "root-password").write_text(password)
        (fixture / "root-password").chmod(0o444)
        (fixture / "client.cnf").write_text("[client]\nuser=root\npassword=" + password + "\nprotocol=socket\n")
        (fixture / "client.cnf").chmod(0o600)
        upload = fixture / "source-upload"
        upload.mkdir()
        content = b"W04 synthetic file; not a user upload.\n"
        (upload / "fixture.txt").write_bytes(content)
        digest = hashlib.sha256(content).hexdigest()
        try:
            for name, volume in zip(containers, volumes):
                docker("volume", "create", "--label", "rabit.fixture=w04", volume)
                created_volumes.append(volume)
                docker("run", "-d", "--name", name, "--network", "none",
                       "--mount", "type=volume,source=" + volume + ",target=/var/lib/mysql",
                       "--mount", "type=bind,source=" + str(fixture) + ",target=/fixture,readonly",
                       "--env", "MYSQL_ROOT_PASSWORD_FILE=/fixture/root-password",
                       "mysql:8.0", "--skip-networking", "--event-scheduler=OFF")
            for name in containers:
                for attempt in range(90):
                    probe = docker("exec", name, "mysql", "--defaults-extra-file=/fixture/client.cnf",
                                   "--batch", "--skip-column-names", "-e", "SELECT 1", allow_fail=True)
                    if probe.returncode == 0 and probe.stdout.strip() == "1":
                        # The image initializes with a temporary server; wait for its final server.
                        command = docker("exec", name, "sh", "-c", 'test "$(cat /proc/1/comm)" = mysqld', allow_fail=True)
                        if command.returncode == 0:
                            break
                    time.sleep(0.5)
                else:
                    raise RuntimeError("Disposable MySQL did not become ready")
            source, restored = containers
            schema = """
CREATE DATABASE w04_fixture CHARACTER SET utf8mb4;
USE w04_fixture;
CREATE TABLE fixture_owner (id INT PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE fixture_file (id INT PRIMARY KEY, owner_id INT NOT NULL, relative_path VARCHAR(64) NOT NULL, sha256 CHAR(64) NOT NULL, FOREIGN KEY (owner_id) REFERENCES fixture_owner(id)) ENGINE=InnoDB;
CREATE TABLE fixture_history (version VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE fixture_audit (file_id INT NOT NULL) ENGINE=InnoDB;
CREATE TRIGGER fixture_file_insert AFTER INSERT ON fixture_file FOR EACH ROW INSERT INTO fixture_audit VALUES (NEW.id);
CREATE PROCEDURE fixture_file_count() SELECT COUNT(*) FROM fixture_file;
CREATE EVENT fixture_disabled_event ON SCHEDULE EVERY 1 DAY DISABLE DO INSERT INTO fixture_history VALUES ('event-must-stay-disabled');
INSERT INTO fixture_owner VALUES (42);
INSERT INTO fixture_history VALUES ('synthetic-w04-only');
"""
            sql(source, schema)
            sql(source, "INSERT INTO w04_fixture.fixture_file VALUES (7,42,'fixture.txt','" + digest + "');")
            dump_path = fixture / "backup.sql"
            with dump_path.open("wb") as output:
                result = subprocess.run(["docker", "exec", source, "mysqldump",
                    "--defaults-extra-file=/fixture/client.cnf", "--single-transaction",
                    "--routines", "--triggers", "--events", "--set-gtid-purged=OFF",
                    "--no-tablespaces", "--databases", "w04_fixture"], stdout=output, stderr=subprocess.PIPE)
            dump_path.chmod(0o600)
            check(result.returncode == 0, "Synthetic database dump failed")
            # No fixture writes run during the snapshot; event scheduler remains disabled.
            backup_upload = fixture / "backup-upload"
            shutil.copytree(upload, backup_upload)
            check(sql(restored, "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='w04_fixture';") == "0", "Restore target was not empty")
            sql(restored, dump_path.read_text())
            restored_upload = fixture / "restored-upload"
            shutil.copytree(backup_upload, restored_upload)
            docker("stop", source)
            row = sql(restored, "SELECT f.owner_id,f.relative_path,f.sha256 FROM w04_fixture.fixture_file f JOIN w04_fixture.fixture_owner o ON o.id=f.owner_id WHERE f.id=7;")
            check(row == "42\tfixture.txt\t" + digest, "Restored row/ownership/hash differs")
            check(hashlib.sha256((restored_upload / "fixture.txt").read_bytes()).hexdigest() == digest, "Restored file bytes differ")
            check(sql(restored, "SELECT version FROM w04_fixture.fixture_history;") == "synthetic-w04-only", "Fixture migration history differs")
            check(sql(restored, "CALL w04_fixture.fixture_file_count();") == "1", "Routine was not restored")
            check(sql(restored, "SELECT status FROM information_schema.events WHERE event_schema='w04_fixture';") == "DISABLED", "Event definition/status was not restored")
            sql(restored, "INSERT INTO w04_fixture.fixture_file VALUES (8,42,'second.txt','" + digest + "');")
            check(sql(restored, "SELECT COUNT(*) FROM w04_fixture.fixture_audit;") == "2", "Trigger/data was not restored")
            print(json.dumps({
                "status": "PASS", "databaseEngine": sql(restored, "SELECT VERSION();"),
                "separateNamedVolumes": True, "emptyRestoreTarget": True,
                "dumpFlags": ["single-transaction", "routines", "triggers", "events", "set-gtid-purged=OFF", "no-tablespaces"],
                "verified": ["row-and-owner", "file-sha256", "fixture-migration-history", "routine", "trigger", "disabled-event", "source-stopped-before-assertions"],
                "elapsedSeconds": round(time.monotonic() - started, 2),
                "syntheticSchemaOnly": True, "realBitrixDatabase": False,
                "portsPublished": False, "networkUsed": False, "productionUsed": False,
            }, indent=2))
        finally:
            for name in containers:
                docker("rm", "-f", name, allow_fail=True)
            for volume in created_volumes:
                label = docker("volume", "inspect", "--format", '{{ index .Labels "rabit.fixture" }}', volume)
                check(volume.startswith(token + "-") and label.stdout.strip() == "w04", "Refusing cleanup outside this fixture")
                docker("volume", "rm", volume)


if __name__ == "__main__":
    main()
