#!/usr/bin/env python3
"""Overture v2 operator load - compare the loaded sample back to the extraction (import fidelity).

Reads the JSON lines printed by ../sql/sample_fidelity.sql and the normalized extraction files
(base.ndjson, supplementary.ndjson). For every sampled row it compares each field to the NDJSON row
with the same source_ref and prints PASS/FAIL per stratum. Exits non-zero on any mismatch, on a
stratum with no sampled row, or on a source_ref missing from the extraction.

Standard library only. Opens no database connection and reads no credential: it compares two local
files. No fuzzy matching and no interpretation - equality, with one stated exception: coordinates
are compared to 1e-9 degrees (about 0.1 mm), because they pass through a float-to-text binding on
the way into PostGIS.

    python3 compare_sample.py --sample sample.jsonl --extract-dir <dir with base/supplementary.ndjson>
"""
import argparse
import json
import math
import os
import sys

REGISTRY_VERSION = "chain-registry-v2"
REGISTRY_RULE_HASH = "b5920a1c73199a0d8e030e5281018baa763438eb7ad02aee76f7ba3cbc0b151f"
EXPECTED_STRATA = {
    "category:grocery_store", "category:coffee_shop", "category:pharmacy", "category:convenience_store",
    "category:fast_food_restaurant", "category:gas_station", "category:department_store",
    "category:superstore", "rescued_cvs", "membership:fuel", "membership:store_in_target",
    "membership:storefront_unconfirmed",
}
EXACT_FIELDS = (
    "lane", "materialization_policy", "source", "source_release", "extract_recipe_version",
    "taxonomy_map_version", "name", "category_key", "source_category", "brand_name", "brand_wikidata",
    "operating_status", "rescued_chain", "rescued_format",
)
ADDRESS_FIELDS = ("freeform", "locality", "postcode", "region", "country")
COORD_TOLERANCE = 1e-9


def load_sample(path):
    rows = []
    with open(path, encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if line:
                rows.append(json.loads(line))
    return rows


def index_extraction(extract_dir, wanted):
    found = {}
    for name in ("base.ndjson", "supplementary.ndjson"):
        with open(os.path.join(extract_dir, name), encoding="utf-8") as fh:
            for line in fh:
                row = json.loads(line)
                if row.get("source_ref") in wanted:
                    found[row["source_ref"]] = row
    return found


def compare(db, src):
    problems = []
    for f in EXACT_FIELDS:
        if db.get(f) != src.get(f):
            problems.append(f"{f}: db={db.get(f)!r} extract={src.get(f)!r}")
    if float(db["confidence"]) != float(src["confidence"]):
        problems.append(f"confidence: db={db['confidence']!r} extract={src['confidence']!r}")
    for axis in ("lon", "lat"):
        if not math.isclose(float(db[axis]), float(src[axis]), rel_tol=0.0, abs_tol=COORD_TOLERANCE):
            problems.append(f"{axis}: db={db[axis]!r} extract={src[axis]!r}")
    for f in ADDRESS_FIELDS:
        if (db.get("address") or {}).get(f) != (src.get("address") or {}).get(f):
            problems.append(f"address.{f}: differs")
    for m in db.get("memberships") or []:
        if m.get("registry_version") != REGISTRY_VERSION or m.get("registry_rule_hash") != REGISTRY_RULE_HASH:
            problems.append(f"membership {m.get('brand_key')}: registry differs from the contract")
    return problems


def stratum_requirement(stratum, db):
    ms = db.get("memberships") or []
    if stratum == "membership:fuel" and not any(m.get("role") == "fuel" for m in ms):
        return "no fuel membership on the sampled row"
    if stratum == "membership:store_in_target" and not any(m.get("format_key") == "store_in_target" for m in ms):
        return "no store_in_target membership on the sampled row"
    if stratum == "membership:storefront_unconfirmed" and not any(
            m.get("storefront_status") == "storefront_unconfirmed" for m in ms):
        return "no storefront_unconfirmed membership on the sampled row"
    if stratum == "rescued_cvs" and (db.get("lane") != "supplementary" or db.get("rescued_chain") != "cvs"):
        return "sampled row is not a rescued CVS row"
    return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--sample", required=True)
    ap.add_argument("--extract-dir", required=True)
    args = ap.parse_args()

    sample = load_sample(args.sample)
    failures = 0
    seen = {row.get("stratum") for row in sample}
    for missing in sorted(EXPECTED_STRATA - seen):
        print(f"FAIL {missing}: stratum absent from the sample output")
        failures += 1

    wanted = {row["source_ref"] for row in sample if row.get("source_ref")}
    extraction = index_extraction(args.extract_dir, wanted)

    for row in sample:
        stratum = row.get("stratum")
        ref = row.get("source_ref")
        if not ref:
            print(f"FAIL {stratum}: no loaded row in this stratum")
            failures += 1
            continue
        src = extraction.get(ref)
        if src is None:
            print(f"FAIL {stratum}: {ref} is not in the extraction files")
            failures += 1
            continue
        problems = compare(row, src)
        requirement = stratum_requirement(stratum, row)
        if requirement:
            problems.append(requirement)
        if problems:
            failures += 1
            print(f"FAIL {stratum} {ref}")
            for p in problems:
                print(f"     {p}")
        else:
            print(f"PASS {stratum} {ref}")

    print("SAMPLE FIDELITY OK" if failures == 0 else f"SAMPLE FIDELITY FAILED ({failures})")
    return 0 if failures == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
