#!/usr/bin/env python3
"""Validate wave dependencies and independent readiness; no network or Git writes."""
from copy import deepcopy
from pathlib import Path
import json
import re


def validate_graph(plan):
    assert plan["version"] == 2 and plan["planStatus"] == "active"
    waves = plan["waves"]
    by_id = {wave["id"]: wave for wave in waves}
    assert len(by_id) == len(waves), "Duplicate wave ID"
    assert all(re.fullmatch(r"[A-N][1-9]\d*", wid) for wid in by_id), "Invalid wave ID"
    policy = plan["mergePolicy"]
    assert policy["baseBranch"] == "main" and policy["dependenciesMustBeMerged"]
    assert policy["verifyMainPlusOwnDiff"] and policy["decisionGatesMustBeResolved"]
    assert policy["stackedPullRequests"] is False
    assert policy["browserE2ERequired"] and policy["userFacingVisualCheckRequired"]
    merged = {wave["id"] for wave in waves if wave["deliveryState"] == "merged"}
    assert merged == set(plan["baseline"]["mergedWaves"]), "Baseline/state mismatch"
    accepted = set(plan["acceptedImplementationDecisions"])
    assigned, legacy = {}, set()
    for wave in waves:
        wid = wave["id"]
        deps = wave["dependsOn"]
        assert len(deps) == len(set(deps)), ("Duplicate dependency", wid)
        assert set(deps) <= by_id.keys() and wid not in deps, ("Unknown/self dependency", wid)
        assert wave["unlocks"] == [other["id"] for other in waves if wid in other["dependsOn"]], ("Stale unlocks", wid)
        assert wave["deliveryState"] in {"merged", "review", "inProgress", "planned"}
        assert all(re.fullmatch(r"W\d{2}", item) for item in wave["legacyIds"])
        legacy.update(wave["legacyIds"])
        assert all(re.fullmatch(r"D\d{2}", item) for item in wave["decisionGates"])
        for key in ["inputBoundary", "outputBoundary", "mergeCheck"]:
            assert isinstance(wave[key], str) and wave[key].strip(), ("Missing boundary", wid, key)
        for eid in wave["endpointIds"]:
            assert eid not in assigned, ("Duplicate primary owner", eid)
            assigned[eid] = wid
        if wave["deliveryState"] in {"review", "inProgress"}:
            assert set(deps) <= merged, ("Work depends on unmerged wave", wid)
            assert set(wave["decisionGates"]) <= accepted, ("Work depends on open decision", wid)
    assert legacy == {f"W{i:02}" for i in range(35)}, "Missing legacy mapping"
    assert len(assigned) == plan["endpointCount"], "Primary endpoint coverage"
    ancestors, visiting = {}, set()

    def visit(wid):
        assert wid not in visiting, ("Dependency cycle", wid)
        if wid in ancestors:
            return ancestors[wid]
        visiting.add(wid)
        result = set()
        for dep in by_id[wid]["dependsOn"]:
            result.add(dep)
            result.update(visit(dep))
        visiting.remove(wid)
        ancestors[wid] = result
        return result

    for wid in by_id:
        visit(wid)
    for wave in waves:
        for eid in wave.get("verifiesEndpointIds", []):
            assert eid in assigned, ("Unknown verification endpoint", eid)
            assert wave["id"] not in ancestors[assigned[eid]], ("Verification required before implementation", wave["id"], eid)
    ready = [wave["id"] for wave in waves if wave["id"] not in merged
             and set(wave["dependsOn"]) <= merged and set(wave["decisionGates"]) <= accepted]
    return {"waves": len(waves), "endpoints": len(assigned), "legacyWaves": len(legacy), "readyFromMain": ready,
            "blocked": [wave["id"] for wave in waves if wave["id"] not in merged and wave["id"] not in ready]}


def negative_checks(plan):
    scenarios = []
    def rejects(label, mutate):
        broken = deepcopy(plan)
        mutate(broken)
        try:
            validate_graph(broken)
        except (AssertionError, KeyError):
            scenarios.append(label)
        else:
            raise AssertionError("Invalid fixture accepted: " + label)
    def wave(data, wid):
        return next(item for item in data["waves"] if item["id"] == wid)
    def cycle(data):
        wave(data, "C1")["dependsOn"].append("C2")
        if wave(data, "C1")["deliveryState"] != "merged":
            wave(data, "C1")["deliveryState"] = "planned"
        for item in data["waves"]:
            item["unlocks"] = [other["id"] for other in data["waves"] if item["id"] in other["dependsOn"]]
    rejects("cycle", cycle)
    rejects("unknown dependency", lambda data: wave(data, "E1")["dependsOn"].append("Z9"))
    merged_ids = {item["id"] for item in plan["waves"] if item["deliveryState"] == "merged"}
    blocked_id = next(item["id"] for item in plan["waves"] if item["id"] not in merged_ids and not set(item["dependsOn"]) <= merged_ids)
    ready_id = next(item["id"] for item in plan["waves"] if item["id"] not in merged_ids and set(item["dependsOn"]) <= merged_ids)
    rejects("unmerged dependency started", lambda data: wave(data, blocked_id).update(deliveryState="inProgress"))
    rejects("open decision started", lambda data: wave(data, ready_id).update(deliveryState="inProgress", decisionGates=["D99"]))
    rejects("stale unlocks", lambda data: wave(data, "C1").update(unlocks=[]))
    rejects("duplicate endpoint owner", lambda data: wave(data, "E1")["endpointIds"].append("ACC-01"))
    rejects("missing legacy mapping", lambda data: wave(data, "B1").update(legacyIds=[]))
    rejects("stacked PR permitted", lambda data: data["mergePolicy"].update(stackedPullRequests=True))
    rejects("browser gate disabled", lambda data: data["mergePolicy"].update(browserE2ERequired=False))
    rejects("visual gate disabled", lambda data: data["mergePolicy"].update(userFacingVisualCheckRequired=False))
    return scenarios


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument("plan", type=Path)
    args = parser.parse_args()
    document = json.loads(args.plan.read_text())
    result = validate_graph(document)
    result["negativeFixtures"] = negative_checks(document)
    print(json.dumps(result, ensure_ascii=False, indent=2))
