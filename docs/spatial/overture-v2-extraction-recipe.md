# Overture corpus v2 — extraction recipe and accounting (`overture-extract-v2`)

**Status:** IMPLEMENTED, OFFLINE, INERT. This is PR 3 of `overture-corpus-v2-design.md` §11: the
reproducible recipe, the normalized output contract, the rejection accounting and the
supplementary / rescue lane. **Nothing here creates a table, loads a row, materializes or
de-duplicates a site, answers a query or activates Overture.** Those are later PRs.

| Pin | Value |
|---|---|
| Recipe version | `overture-extract-v2` (`config/overture_extract_v2.php`) |
| Overture release | `2026-08-19.0`, theme `places`, type `place` — never moved silently |
| Bounding box | `[-87.63, 24.40, -79.97, 31.00]` (west, south, east, north) |
| Taxonomy | `taxonomy.primary` via `OvertureTaxonomyMapV2`, `overture-taxonomy-v2.0` |
| Chain registry (offline census only) | `chain-registry-v2`, rule hash `b5920a1c73199a0d…`, `chain-match-precedence-v2`, `chain-name-norm-v1` |

The recipe version, release, taxonomy version and registry version/hash are recorded separately in
every manifest; none of them is folded into another.

## 1. How it runs

```
duckdb -c ".read scripts/overture-v2/count_release_rows.sql"      # whole-release row count
duckdb -c ".read scripts/overture-v2/extract_bbox_raw.sql"        # → overture_v2_raw_bbox.ndjson
php artisan corpus:extract-overture-v2 \
    --input=overture_v2_raw_bbox.ndjson --output-dir=<scratch> --release-row-count=<N>
```

* **The SQL applies the bounding box and nothing else.** Every other decision — confidence, status,
  taxonomy, the supplementary selector — is made by the pure normalizer, so every rejection is
  counted rather than disappearing in a `WHERE` clause (the v1 "fully accounted" artefact, v2 design
  §2). `OvertureExtractV2SqlManifestTest` asserts the SQL's release and box equal the config, that
  its `WHERE` names no other field, and that its projection is exactly the normalizer's field set.
* **The application never needs the network.** The SQL is run by hand against the public bucket
  (anonymously; it blanks ambient AWS credentials, which otherwise hijack the read). The command reads
  one local file, refuses production with no override, touches no database, and writes three files:
  `base.ndjson`, `supplementary.ndjson`, `manifest.json`. A line that is not a JSON object, or a row
  whose fields differ from the projection, aborts the run with nothing written.
* **The chain-registry census always runs** — it is what resolves every rescue candidate (§4), so
  there is no option to skip it.
* **Writes are checked and staged.** Each file is written under a temporary name and renamed into
  place only after every write succeeded, the manifest last: a manifest on disk means both lanes
  beside it are complete. A failed write exits non-zero and leaves no output.
* Raw extracts and outputs are scratch artefacts. **None is committed.**

## 2. Bounding-box semantics

Containment of each row's own `bbox` in the rectangle (identical to v1 and PR 0), applied in SQL; the
normalizer re-checks the point, inclusively, and would reject a stray row as `outside_bounding_box`.
It is a rectangle, **not** the Florida boundary: the measured GA/AL spill-over is kept and tallied by
region (v2 design decision 8), never trimmed.

## 3. Eligibility, in check order — one outcome per row

| # | Check | Outcome |
|---|---|---|
| 1 | `id` missing / blank | `missing_source_id` |
| 2 | `id` appears more than once | `duplicate_source_id` — **every** copy, whatever the input order |
| 3 | a text field is not text / null | `malformed_field` |
| 4 | not a finite WGS84 `POINT` | `malformed_geometry` |
| 5 | point outside the box | `outside_bounding_box` |
| 6 | confidence missing / non-numeric / outside [0, 1] | `malformed_confidence` |
| 7 | confidence `< 0.90` (0.90 itself is eligible) | `confidence_below_floor` |
| 8 | status `permanently_closed` / any status not mapped | `status_permanently_closed` / `status_unrecognised` |
| 9 | `taxonomy.primary` ∈ the 16 import tokens | **BASE**, with its canonical key |
| 10 | else selected by the diagnostic selector | **SUPPLEMENTARY** |
| — | else | `taxonomy_null_not_supplementary` / `taxonomy_not_allowlisted_not_supplementary` |

**Operating status.** `open` and NULL are eligible; `permanently_closed` and any unseen value are not.
The match is exact (`Open` is unseen). NULL is kept as **unknown** — `operating_status_known = false`
— and never coerced to open.

**Base category set — exactly the 16 tokens of `OvertureTaxonomyMapV2`:** `restaurant`, `gas_station`,
`gym`, `grocery_store`, `coffee_shop`, `pharmacy`, `shopping_mall` (→ canonical `shopping_center`),
`convenience_store`, `fast_food_restaurant`, `cafe`, `burger_restaurant`, `department_store`,
`chicken_restaurant`, `drugstore`, `taco_restaurant`, `superstore`. The legacy token `shopping_center`
fails. `shopping`, `doctors_office`, `atm`, liquor, bakery, photo, eyewear and every other token are
**not** base categories. `taxonomy.primary` alone classifies: `categories.primary` and
`basic_category` are carried as provenance and never read as a fallback.

## 4. The supplementary lane — beside the corpus, never part of it

A supplementary row passed checks 1–8, is not a base token, and is selected by the **census
diagnostic selector** (`config/overture_extract_v2.php`): a chain-like name or brand (21 declared
patterns over `lower(name + ' ' + brand)`), or any non-blank brand QID. It carries **no canonical
category**, so it can never be read as a Location DNA or brand-search row of its own. The selector is
pinned in the manifest (`recipe.diagnostic_selector`), so editing a pattern changes the pins even if
`recipe_version` is not bumped.

**The broad `any brand QID` clause feeds diagnostics only.** A QID selects a row into this lane and
does nothing else: it never makes a row base, never gives it a category, and never makes it
materializable. On the 2026-08-19.0 data 731 rows are selected by a QID alone, and every one is
`diagnostic` / `matcher_only`.

**Candidacy is by TOKEN; admission is by the chain matcher, and the verdict is written on the row.**

| Role | When | `rescue_verdict` | `materialization_policy` |
|---|---|---|---|
| `diagnostic` | its token is claimed by no rescue lane | `not_candidate` | `matcher_only` — forever |
| `rescue_candidate` | its token is claimed by a rescue lane | `admitted` | `rescued` |
| `rescue_candidate` | ″ | `refused` | `matcher_only` |

Every row whose token a lane claims is a candidate **whatever its name** — a Winn-Dixie filed under
`shopping` is a `cvs_shopping` candidate exactly as a CVS is. So the role never means "rescued". The
normalizer emits a candidate as `pending`; the census runs the chain matcher and records `admitted`
(with `rescued_lane`, `rescued_chain`, `rescued_as_category` and `rescued_format`) or `refused`. A
written file never contains `pending`. **`rescued` is the only supplementary policy a later import may
materialize**, and it may do so from the file — it never re-derives the verdict, and it can never read
a token-level label as an admission. A verdict holds only under the registry that produced it: an
import must compare the manifest's `chain_registry.rule_hash` with the current registry and refuse
(or re-run this recipe) on a mismatch. The census aborts the run, rather than counting, when a base row
produces a rescued membership, a diagnostic row matches, a candidate's membership is not under a lane
its own record names, or a candidate produces more than one membership.

**Rescue lanes mirror the chain registry exactly, both ways**, and `OvertureExtractV2Config` fails
closed otherwise: every lane names a chain whose `source_category_rescues` declares that exact token
and target category, identity must be `strong`, the token may not be an import token or canonical
key, and every registry rescue must have a lane. There is no generic "rescue any category" path, and
an unknown key anywhere in the supplementary configuration is refused.

**The one lane in v2 — `cvs_shopping`:** chain `cvs`, source token `shopping`, target `drugstore`,
strong identity. On the real data it has 228 candidates: **150 admitted** (147 `store`, 3
`store_in_target` — CVS inside Target keeps its narrow host rule), **78 refused**. The refused are CVS
Beauty, MinuteClinic and CVS specialty / Coram infusion (CVS sub-entities the registry excludes), and
non-CVS names under the same token (Winn-Dixie, Walmart Vision & Glasses, Publix, 7-Eleven, …).

## 5. Normalized output contract (`OvertureV2Record::toArray()`, fixed key order)

`lane`, `supplementary_role`, `rescue_lanes`, `materialization_policy`, `rescue_verdict`,
`rescued_lane`, `rescued_chain`, `rescued_as_category`, `rescued_format` (all five null on a base row;
`rescued_*` set only when admitted, and `rescued_as_category` is kept apart from `category_key`),
`source` (`overture`),
`source_ref` (Overture id), `source_release`, `extract_recipe_version`, `taxonomy_map_version`,
`source_category` (raw `taxonomy.primary`), `category_key` (canonical; null in the supplementary lane),
`legacy_category` (`categories.primary`, provenance), `basic_category` (provenance), `name`,
`brand_name`, `brand_wikidata`, `confidence`, `operating_status` (raw), `operating_status_known`,
`lon`, `lat`, `geometry_type` (`POINT`), `address` {`freeform`, `locality`, `postcode`, `region`,
`country`}, `eligibility` (`base_category_eligible` / `supplementary_selector`).

**Address is internal** — for de-duplication and site identity, never a display contract. Overture
places carry **no separate house-number or street field**; the street line is inside `freeform`, which
is kept verbatim rather than guessed apart.

Files are sorted byte-wise (`strcmp`, never PHP's numeric-aware `<=>`) — base by (`category_key`,
`source_ref`), supplementary by (`source_category`, `source_ref`) — with fixed JSON flags, so the
SHA-256 in the manifest is a function of the input rows alone. The chain registry's version, rule hash,
precedence version and name-normalizer version are recorded in the manifest (`chain_registry`), beside
the recipe pins.

## 6. Real-data reproduction (2026-09-24)

| Stage | Rows |
|---|--:|
| Release rows (`count_release_rows.sql`) | 73,631,092 |
| Outside the bounding box (SQL) | 72,385,167 |
| **Bounding-box input** | **1,245,925** |
| missing / duplicate id, malformed field or geometry, outside box | 0 |
| Valid identity + geometry, in box | 1,245,925 |
| `confidence_below_floor` | 489,699 |
| Confidence eligible | 756,226 |
| `status_permanently_closed` / `status_unrecognised` | 12,451 / 0 |
| Confidence + status eligible | 743,775 |
| **BASE corpus** (16 tokens) | **52,566** |
| Supplementary candidates (not a base token) | 691,209 |
| `taxonomy_null_not_supplementary` | 17,078 |
| `taxonomy_not_allowlisted_not_supplementary` | 671,169 |
| **SUPPLEMENTARY** (selected) | **2,962** (228 `rescue_candidate` · 2,734 `diagnostic`) |
| — rescue verdicts | 150 `admitted` (`rescued`) · 78 `refused` (`matcher_only`) |
| **Matcher-analysis input** = base + supplementary | **55,528** — not a corpus count |

Every bounding-box row is in exactly one of base, supplementary or one rejection (fully accounted).
Base by region: FL 51,610 · GA 839 · AL 102 · other 4 · none 11 — PR 0's FL 51,610 · GA/AL 941 ·
other/null 15, exactly. Base status: 47,522 explicit `open`, 5,044 unknown.

**Matcher census, offline, over the 55,528:** 11,082 memberships (10,932 from base, 150 rescued via
`cvs_shopping`), 0 ambiguous rows, 0 co-branded memberships, per-chain counts identical to the
validated census (`overture-chain-registry-census.md` §8). The 55,528 ids are exactly the census
input's, and the base ids exactly its eligible set.

**Determinism.** The same 1,245,925 rows in source order, shuffled and reversed produce byte-identical
`base.ndjson` (`bb9e77c13790897155f847fa3a7ed48067930cf08257e227bee60bf96c91a168`) and
`supplementary.ndjson` (`edeed1435d707912dedd08d642cf1088e669cf4dc329ea3248a289be3e91c8e4`) and manifests
identical except for the input file's own hash. Reproduced with DuckDB 1.5.5 (Python 3.11) reading the
public bucket anonymously.

## 7. What this does not do

No migration, table, seed, load, materialization, de-duplication, radius query, runtime consumer,
Location DNA change, Ask AI / Buyer / Tenant / Smart Tags change, activation or deploy. The chain
matcher runs here only as an offline transform, for accounting and to record each rescue verdict;
the registry's structural guard names this recipe's three files as its only permitted non-runtime
consumers.
