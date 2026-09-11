#!/usr/bin/env python3
"""Verify the frozen W00 inventory using Python 3 standard library only.

Reads local JSON/Markdown; never writes files, imports frontend code, executes
Postman scripts, or makes HTTP requests. This is a baseline consistency check,
not a live API test or a complete Postman schema/runtime validator.
"""
import argparse
import hashlib
import json
import re
import sys
from pathlib import Path

ORIGINAL_COUNTS = {
    "AUTH": 4, "ACC": 6, "ORG": 12, "MED": 7, "COM": 13, "HND": 12,
    "PAY": 9, "FIL": 8, "SUP": 6, "SET": 7, "PRD": 7, "SHP": 5, "INF": 1,
}
ORIGINAL_IDS = {
    f"{prefix}-{number:02d}"
    for prefix, count in ORIGINAL_COUNTS.items()
    for number in range(1, count + 1)
}
PLATFORM_IDS = {"SHR-01", "NTF-01"}
EXPECTED_IDS = ORIGINAL_IDS | PLATFORM_IDS
EXPECTED_WAVES = [f"W{number:02d}" for number in range(35)]
FILES = (
    "endpoints.json", "backend-waves.md",
    "postman/MoreFoto.postman_collection.json",
    "postman/MoreFoto.backend-waves.postman_collection.json",
)
SOURCE_POSTS = {
    "AUTH-01": "/auth/login", "AUTH-02": "/auth/logout",
    "AUTH-03": "/auth/register/request-code", "AUTH-04": "/auth/register/confirm",
    "SHR-01": "/share/file/upload/", "NTF-01": "/lead",
}


class VerificationError(Exception):
    pass


def check(condition, message):
    if not condition:
        raise VerificationError(message)


def endpoint_id(item):
    return item["name"].split(" · ", 1)[0]


def check_guard(item, label):
    pre = [event for event in item.get("event", []) if event["listen"] == "prerequest"]
    check(len(pre) == 1, f"{label}: expected one platform guard")
    check(pre[0]["script"]["type"] == "text/javascript", f"{label}: guard type")
    lines = [line.strip() for line in pre[0]["script"]["exec"]]
    check(len(lines) == 5, f"{label}: unexpected guard structure")
    # Pin the complete control flow, not presence of keywords in arbitrary JS.
    check(lines[0] == "if (pm.environment.get('allow_platform_requests') !== 'true') {",
          f"{label}: guard must require the exact string true")
    check(lines[1] == "pm.request.url.update('rabit-request-blocked:');",
          f"{label}: block network URL before skip/throw")
    check(lines[2] == (
        "if (pm.execution && typeof pm.execution.skipRequest === 'function') "
        "pm.execution.skipRequest();"
    ), f"{label}: guard must support runtimes without skipRequest")
    check(re.fullmatch(r"throw new Error\('[^'\\\r\n]*'\);", lines[3]) is not None,
          f"{label}: guard must throw after blocking URL")
    check(lines[4] == "}", f"{label}: unexpected code after platform guard")


def read_waves(markdown):
    sections = list(re.finditer(
        r'^<a id="(w\d{2})"></a>\n## (W\d{2})\. ([^\n]+)\n'
        r'(.*?)(?=^<a id="w\d{2}"></a>|\Z)', markdown, re.M | re.S
    ))
    check([match[2] for match in sections] == EXPECTED_WAVES,
          "Markdown must contain ordered W00-W34 sections")
    waves = {}
    for match in sections:
        anchor, wave_id, title, body = match.groups()
        check(anchor == wave_id.lower(), f"{wave_id}: anchor mismatch")
        primary = re.search(r"^\*\*Первичные REST:\*\* ([^\n]+)$", body, re.M)
        check(primary is not None, f"{wave_id}: missing primary endpoint list")
        verification = re.search(r"^\*\*Повторная проверка:\*\* ([^\n]+)$", body, re.M)
        primary_ids = re.findall(r"\b[A-Z]+-\d{2}\b", primary[1])
        verify_ids = re.findall(r"\b[A-Z]+-\d{2}\b", verification[1]) if verification else []
        if verification and verification[1] == (
            "все 99 операций реестра, с разделением platform/frontend/product."
        ):
            verify_ids = sorted(EXPECTED_IDS)
        check(len(primary_ids) == len(set(primary_ids)), f"{wave_id}: duplicate primary IDs")
        check(len(verify_ids) == len(set(verify_ids)), f"{wave_id}: duplicate regression IDs")
        check(set(primary_ids + verify_ids) <= EXPECTED_IDS, f"{wave_id}: unknown endpoint")
        waves[wave_id] = {"title": title, "primary": primary_ids, "verify": verify_ids}
    return waves


def check_collection(collection, records, modules, waves, by_wave):
    check(collection["info"]["schema"] ==
          "https://schema.postman.com/json/collection/v2.1.0/collection.json",
          "Expected Postman collection v2.1")
    check(not collection.get("event"), "Unexpected collection-level scripts")
    folders = collection["item"]
    expected_names = (
        [f"{wid} — {wave['title']}" for wid, wave in waves.items()]
        if by_wave else [f"{module[0]} — {module[1]}" for module in modules.values()]
    )
    check([folder["name"] for folder in folders] == expected_names,
          "Collection folder names/order differ from registry or wave plan")
    found = {}
    for index, folder in enumerate(folders):
        check(not folder.get("event"), f"{folder['name']}: unexpected folder-level scripts")
        items = folder["item"]
        if by_wave:
            wave_id = EXPECTED_WAVES[index]
            check([endpoint_id(item) for item in items] == waves[wave_id]["primary"],
                  f"{wave_id}: primary endpoint order/coverage differs")
        for item in items:
            eid = endpoint_id(item)
            check(eid in records and eid not in found, f"Unknown/duplicate operation: {eid}")
            record = records[eid]
            if not by_wave:
                check(folder["name"].startswith(modules[record["module"]][0] + " — "),
                      f"{eid}: wrong module folder")
            request = item["request"]
            check(request["method"] == record["method"], f"{eid}: method differs")
            route = re.sub(r"\{([a-z_]+)\}", lambda m: "{{" + m[1] + "}}", record["path"])
            prefix = "{{base_url}}" + ("" if eid == "INF-01" else "/{{api_prefix}}")
            raw_path = request["url"]["raw"].split("?", 1)[0]
            check(raw_path == prefix + route, f"{eid}: URL differs from registry")
            check(request["url"]["host"] == ["{{base_url}}"], f"{eid}: unexpected host")
            check(raw_path == "{{base_url}}/" + "/".join(request["url"]["path"]),
                  f"{eid}: raw/structured URL mismatch (including trailing slash)")
            check(record["scope"] in request["description"] and
                  record["implementationWave"] in request["description"],
                  f"{eid}: missing scope/wave description")
            if eid in PLATFORM_IDS:
                check_guard(item, ("waves" if by_wave else "modules") + "/" + eid)
            found[eid] = item
    check(set(found) == EXPECTED_IDS, "Collection must contain exactly the 99 baseline IDs")
    return found


def verify(snapshot):
    raw = {name: (snapshot / name).read_bytes() for name in FILES}
    registry = json.loads(raw["endpoints.json"])
    collection = json.loads(raw[FILES[2]])
    wave_collection = json.loads(raw[FILES[3]])
    waves = read_waves(raw["backend-waves.md"].decode("utf-8"))
    records = registry["endpoints"]
    by_id = {record["id"]: record for record in records}
    check(len(records) == len(by_id) == 99 and set(by_id) == EXPECTED_IDS,
          "Registry must retain the original 97 IDs plus SHR-01/NTF-01, without duplicates")
    check(len({(record["method"], record["path"]) for record in records}) == 99,
          "Duplicate registry method/path")
    check(registry["canonicalPrefix"] == "/api/v1", "Unexpected canonical prefix")
    backend = registry["backend"]
    check(backend["slug"] == "rabit-api" and backend["name"] == "RaBit API",
          "Unexpected backend identity")
    check(backend["baseModules"] == ["rebit.share", "rebit.auth"] and
          backend["retainedModules"] == ["rebit.notification", "rebit.leadhunter"],
          "Unexpected retained module inventory")
    check(len(registry["modules"]) == 15, "Expected 15 module/platform inventory groups")
    check(backend["businessModulesStatus"] == "proposed", "Future modules must remain proposed")
    for scope, expected in (
        ("platformInventory", PLATFORM_IDS), ("inheritedAuth", {"AUTH-03", "AUTH-04"}),
        ("frontendInventory", {"INF-01"}), ("product", EXPECTED_IDS - PLATFORM_IDS -
         {"AUTH-03", "AUTH-04", "INF-01"}),
    ):
        check({record["id"] for record in records if record["scope"] == scope} == expected,
              f"Incorrect {scope} scope")
    existing = {record["id"] for record in records
                if record["status"] in {"К", "Н", "Ф"} and record["id"] != "INF-01"}
    check(existing == set(SOURCE_POSTS), "Expected exactly six current backend source routes")
    for eid, path in SOURCE_POSTS.items():
        check(by_id[eid]["method"] == "POST" and by_id[eid]["path"] == path,
              f"{eid}: current backend route differs")
    for eid in PLATFORM_IDS:
        check(by_id[eid]["status"] == "Ф" and by_id[eid]["idem"] is False,
              f"{eid}: source inventory is not verified delivery or idempotency")
    primary = [eid for wave in waves.values() for eid in wave["primary"]]
    check(len(primary) == len(set(primary)) == 99 and set(primary) == EXPECTED_IDS,
          "Wave plan must assign each endpoint exactly once")
    for eid, record in by_id.items():
        assigned = [wid for wid, wave in waves.items() if eid in wave["primary"]]
        regressions = [wid for wid, wave in waves.items() if eid in wave["verify"]]
        check(assigned == [record["implementationWave"]] and
              regressions == record["verificationWaves"], f"{eid}: wave metadata differs")
        check(all(EXPECTED_WAVES.index(wid) >= EXPECTED_WAVES.index(assigned[0])
                  for wid in regressions), f"{eid}: regression precedes primary wave")
    modular = check_collection(collection, by_id, registry["modules"], waves, False)
    grouped = check_collection(wave_collection, by_id, registry["modules"], waves, True)
    check(modular == grouped, "Module/wave request items differ (including scripts/examples)")
    return {
        "result": "passed", "endpoints": 99, "originalEndpoints": 97,
        "platformEndpoints": 2, "currentBackendSourceRoutes": 6,
        "moduleFolders": 15, "waveFolders": 35, "platformGuards": 4,
        "postmanScriptsExecuted": False, "liveApiRequestsExecuted": False,
        "sha256": {name: hashlib.sha256(data).hexdigest() for name, data in raw.items()},
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--snapshot", type=Path, default=(
        Path(__file__).resolve().parent.parent / "docs/waves/w00/snapshot"
    ), help="Snapshot directory (default: repository docs/waves/w00/snapshot)")
    args = parser.parse_args()
    try:
        result = verify(args.snapshot)
    except (VerificationError, OSError, ValueError, KeyError, TypeError, IndexError) as error:
        print(json.dumps({"result": "failed", "error": str(error)}, ensure_ascii=False),
              file=sys.stderr)
        return 1
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
