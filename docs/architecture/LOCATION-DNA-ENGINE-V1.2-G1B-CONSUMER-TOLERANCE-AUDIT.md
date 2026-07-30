# G1b — Location DNA Consumer Tolerance Audit

**Gate:** G1b (per §12 of the [G1 Pre-Implementation Report](./LOCATION-DNA-ENGINE-V1.2-G1-PRE-IMPLEMENTATION-REPORT.md))
**Type:** read-only investigation and documentation increment
**Status:** **AUDIT ONLY. NO PRODUCTION CODE, TESTS OR CONFIGURATION CHANGED. NO FIX IMPLEMENTED.**
**Governed by:** [`LOCATION-DNA-ENGINE-V1.2.md`](./LOCATION-DNA-ENGINE-V1.2.md)
**Audited at:** `8d50d268ffa3a8856d57a4486c42f04d1b04efda`
**Decisions:** D-G1-1 … D-G1-6 remain **open**. Nothing here resolves or assumes any of them.

> Every count, path, symbol, line number and route relationship below was recomputed from the tree at
> the audited commit. No figure is carried over from prior prose without rechecking.

---

## 1. Executive summary

The canonical contract has **52 production occurrence files**, of which **43 name the canonical meta key
`location_dna_preferences`** — reconciling exactly with §4.3 F-C1 as restated in the G1 report — and **9
more consume Location DNA data indirectly** without naming that key. Of the 52, **47 carry a consumer ID**
(1 fixture producer and 4 forward-only editor views are excluded), and those 47 files resolve to **44
consumer IDs** — one ID groups four interchangeable tab partials. Eleven of the 44 are dual-role
writer/readers.

**The dominant finding is not intolerance — it is blindness.** Nearly every read-only consumer uses a
defensive, verified idiom (`is_array($raw) ? $raw : json_decode(...)` → `is_array($decoded)` guard →
`!empty($key)` read) that tolerates absent, null, empty and malformed input without error. What none of
them can do is distinguish **present-but-cleared from never-authored**, because they all decide with
`empty()`. So the §5.2 violation G1a proved in the editing surfaces is **replicated across the entire
read side**: 28 of 44 consumer IDs are classified CONDITIONALLY TOLERANT for exactly this reason.

**Three structural facts stand out:**

1. **`schema_version` has zero production consumers.** Not one file reads or writes it on the canonical
   blob. The only production match in the tree is an unrelated Overture dataset version. This confirms
   F-G1-5 at the inventory level rather than the behavioural level: there is no interpretation mode to
   audit because no code participates in one. Every consumer is therefore INTOLERANT of a higher
   `schema_version` in the specific sense that §5.5's refuse-to-interpret rule is unimplemented
   everywhere — a record stamped `99` is read normally by all 44.
2. **A second derived store exists and is shape-mismatched with the views that read it.**
   `property_location_dna.summary_json` is written by exactly one writer, which persists POI/thematic
   data — never canonical dimensions. Three views nevertheless read `polygons`, `radius_searches`,
   `cities` and `location_notes` out of it. Those reads resolve to their defaults today, so **there is no
   live exposure**, but the surfaces are pre-wired to render geometry the moment any writer persists the
   canonical shape there. This is F-G1B-1 and the highest-risk item in the audit.
3. **The G0.1 projection seam is intact and consistently applied.** All four audited public routes apply
   `PublicGeometryProjection`; the shared viewer and `tenant_criteria/view` consume the projected array;
   the owner editor is correctly excluded. Exactly five view files name geometry keys and every one is
   accounted for. **No live bypass was found.**

Nine findings are recorded, F-G1B-1 … F-G1B-9. Two consumers are classified UNRESOLVED (C-29, C-40), and
four open questions are declared (U-G1B-1 … U-G1B-4), each named with the smallest characterisation test
that would settle it. **None was written — that is not G1b.**

---

## 2. Audit method

1. **Recompute the inventory.** Enumerate production occurrence files across every representation and
   alias of the contract, not the prior count.
2. **Classify every occurrence** as producer, writer, reader/consumer, projection, validator,
   transport-only, test-only, dead/commented, or false positive. A docblock-only textual match is *not*
   automatically a false positive — several services name the key only in prose while consuming a
   pre-decoded `array $preferences` parameter, which was verified by reading each signature.
3. **Determine actual behaviour from code.** Read the access pattern at each site. Per the authorization,
   safety was **not** inferred from the mere presence of `??`, `empty()`, `is_array()`, casts, optional
   chaining or defaults — each was traced to what it does with the specific input class.
4. **Trace derived stores to their writers.** Where a consumer reads a projection or snapshot rather than
   the blob, find every writer of that store and compare the shapes.
5. **Trace public reachability by route.** For each surface, resolve the controller, the route and its
   middleware, rather than assuming from the file's location.
6. **Mark UNRESOLVED** wherever static inspection cannot settle runtime behaviour, and name the smallest
   test that would.

**Not attempted, and therefore not claimed:** no test was executed for this audit, no route was requested,
and no JavaScript was run. Client-side behaviour is bounded by static reading only — see §11.

---

## 3. Search terms and commands used

Run from the worktree root at the audited commit. `SRC="app resources config routes database"`.

```bash
# Canonical key — the §4.3 F-C1 baseline
grep -rl "location_dna_preferences" $SRC | sort | wc -l          # → 43

# Per-term file and hit counts
for t in location_dna locationDna existingLocationDna search_areas searchAreas \
         location_notes polygons radius_searches radius_miles schema_version \
         PublicGeometryProjection; do
  printf '%-26s files=%-4s hits=%s\n' "$t" \
    "$(grep -rl "$t" $SRC | wc -l)" "$(grep -r "$t" $SRC | wc -l)"
done

# Union of canonical-contract terms → the audited superset
{ grep -rl "location_dna_preferences" $SRC; grep -rl "existingLocationDna" $SRC
  grep -rl "location_notes" $SRC;          grep -rl "radius_searches" $SRC
  grep -rl "PublicGeometryProjection" $SRC; } | sort -u | wc -l   # → 52

# Indirect consumers = union minus canonical-key set
comm -13 <(sort canonical.txt) union.txt

# Writers of the canonical key, and of the derived store
grep -rn "saveMeta('location_dna_preferences'" app/
grep -rnE "summary_json'?\s*(=>|=)" app/ | grep -v compatibility_summary

# Projection coverage
grep -rn "PublicGeometryProjection" app/ resources/ \
  | grep -v "^app/Services/LocationDna/PublicGeometryProjection.php"

# Views naming geometry keys — the public-surface candidate set
grep -rlnE "polygons|radius_searches" resources/views/ | sort

# Route + middleware resolution (example)
grep -rn "criteria/auction/bid" routes/web.php
sed -n '603,625p' routes/web.php

# Docblock-only match vs true consumer (signature check)
grep -nE "function [a-zA-Z]+\(.*array \\\$(preferences|prefs|ldna|locationDna)" <file>

# Tests encoding a runtime contract
grep -rl "location_dna_preferences\|existingLocationDna\|PublicGeometryProjection" tests | sort
```

Measured per-term results at the audited commit:

| Term | Files | Hits | Note |
|---|---|---|---|
| `location_dna` | 77 | 228 | superset — includes the **adjacent** scoring/POI domain (§10) |
| `locationDna` | 31 | 131 | camelCase view/controller variables |
| `existingLocationDna` | 16 | 46 | editor prefill array |
| `search_areas` / `searchAreas` | **0** | **0** | not a runtime identifier; documentation term only |
| `location_notes` | 9 | 18 | D5-sensitive free text |
| `polygons` | 34 | 159 | superset — includes spatial corpus/boundary code |
| `radius_searches` | 23 | 59 | |
| `radius_miles` | 18 | 32 | |
| `schema_version` | **1** | **1** | `config/overture_places.php:43` — **unrelated domain** |
| `PublicGeometryProjection` | 7 | 18 | 1 definition + 6 call/consumption sites |

---

## 4. Reconciled inventory count

| Set | Count | Reconciliation |
|---|---|---|
| Files naming `location_dna_preferences` (production) | **43** | Matches §4.3 F-C1 as restated in the G1 report. No drift. |
| Additional indirect consumers | **+9** | Consume the contract without naming the key — see below |
| **Audited superset (occurrence files)** | **52** | |
| — excluded: fixture producer | **1** | `database/seeders/LocationDnaTestSeeder.php` |
| — excluded: legacy editor views that only forward the prefill array | **4** | `buyer_criteria/{add,edit}`, `tenant_criteria/{add,edit}` |
| **Files carrying a consumer ID** | **47** | |
| **Consumer IDs assigned** | **44** | C-01 … C-44. One ID, **C-41**, covers the 4 interchangeable tab partials — hence 44 IDs over 47 files. |
| Tests encoding a runtime contract (counted separately) | **18** | Not part of the 52; §7.J |

**Arithmetic note.** IDs and files are deliberately not 1:1. 47 files carry an ID; C-41 groups four tab
partials that share one verified idiom, so 44 IDs cover 47 files. All tolerance tallies in §6 are **per ID
(44)**, not per file, so that a grouped row is not counted four times.

**The 9 indirect consumers, and why each was missed by a canonical-key grep:**

| File | Why it does not name the key |
|---|---|
| `app/Http/Controllers/Stellar/StellarBuyerResultsController.php:125-126` | Reads `$criteriaData['radius_searches']` / `['polygons']` from an already-assembled array |
| `app/Services/Bridge/CriteriaHashService.php:34-35` | Hashes `$payload->radiusSearches` / `->polygons` off a DTO |
| `app/Services/Stellar/Matching/DTO/BuyerCriteriaPayload.php:92-93` | DTO hydrates `radius_searches` / `polygons` from a passed array |
| `resources/views/offers/show.blade.php:395,528` | Reads `location_notes` from `PropertyLocationDna::summary_json` |
| `resources/views/buyer_criteria/add-bid.blade.php:346-349` | Reads geometry keys from `summary_json` |
| `resources/views/buyer_criteria/add.blade.php:439` | Passes `existingLocationDna` into the owner editor |
| `resources/views/buyer_criteria/edit.blade.php:439` | Same |
| `resources/views/tenant_criteria/add.blade.php:427` | Same |
| `resources/views/tenant_criteria/edit.blade.php:427` | Same |

**Explanation of the difference from 43.** The prior figure was never wrong — it counts files naming the
canonical meta key, which is still exactly 43. It simply under-counts *consumers*, because four groups
reach the data without naming it: DTO/payload hydration in the matching pipeline, a derived-store read in
two bid views and one offer view, and the four legacy criteria editor views that pass the prefill array
into `map-input`. The audited consumer surface is therefore **52 occurrence files / 47 ID-carrying files /
44 consumer IDs**,
not 43. **§4.3 F-C1's "42 consumers" and the G1 report's corrected "43" should both be superseded by
these figures**, with the caveat that the three numbers count different things and all three are correct
for what they count.

---

## 5. Count by consumer category

| Category | Count | Notes |
|---|---|---|
| **Writer** (contains `saveMeta('location_dna_preferences'`) | 7 | 2 criteria controllers, `HasSearchAreas`, 4 Offer Listing components |
| **Indirect writer** (writes via the trait) | 4 | the 4 Hire components call `saveSearchAreas()` |
| **Reader / consumer** | 41 | includes the 11 above, which are dual-role |
| **Projection** | 1 | `PublicGeometryProjection` |
| **Validator** | 0 | **no consumer validates the blob's shape** — see F-G1B-4 |
| **Transport-only** | 7 | `search-areas-bridge`, `map-input`, 4 tab partials, 4 legacy editor views (overlap) |
| **Test-only / fixture** | 1 + 18 | seeder; plus 18 contract-encoding test files |
| **Dead / commented code** | 0 | none found within the union |
| **False positive** | 1 | `config/overture_places.php:43` (`schema_version`, unrelated domain) |

Categories overlap deliberately: a Livewire editing component is simultaneously writer, reader and
transport host. Counts are per role, not a partition.

---

## 6. Count by tolerance classification

Across the **44 consumer IDs** (C-01 … C-44):

| Classification | Count | IDs | Meaning in this audit |
|---|---|---|---|
| **TOLERANT** | 6 | C-10, C-11, C-12, C-13, C-14, C-16 | Every relevant case supported by direct code evidence **or** an existing passing test |
| **CONDITIONALLY TOLERANT** | 28 | C-04, C-05, C-15, C-17, C-18, C-19 … C-28, C-30 … C-38, C-41, C-42, C-43, C-44 | Survives absent/null/empty/malformed, but **cannot distinguish present-but-cleared from never-authored** |
| **INTOLERANT** | 7 | C-01, C-02, C-03 (primary) · C-06, C-07, C-08, C-09 (inherited from C-01) | Loses or corrupts user intent on at least one input class |
| **NOT APPLICABLE** | 1 | C-39 | Owner editor; geometry is intended and v1.2 forbids changing it |
| **UNRESOLVED** | 2 | C-29, C-40 | Static inspection insufficient — §11 names the settling test |
| **Total** | **44** | | |

Only **3** of the 7 INTOLERANT rows are distinct defects; C-06 … C-09 inherit C-01's trait behaviour and are
counted as rows, not as separate defects.

**Separately, all 44 are INTOLERANT of a higher `schema_version`** in the §5.5 sense, because no consumer
implements refuse-to-interpret. That is a property of the system, not of individual consumers, so it is
recorded once as F-G1B-2 rather than collapsing all 44 rows to INTOLERANT. The 2 UNRESOLVED rows are
consumers; the **4** declared unknowns in §11 (U-G1B-1 … U-G1B-4) are questions, and the two counts are not
the same thing.

---

## 7. Complete consumer matrix

Rows are grouped where the group shares a **single verified idiom**; each member was still read
individually and carries its own ID. Server-side unless noted. Exposure is by resolved route middleware.

### 7.A Editing surfaces — writer + reader (owner-private)

| ID | File / symbol | Workflow | Exposure | Fields read | Access pattern | Tolerance |
|---|---|---|---|---|---|---|
| C-01 | `Concerns/HasSearchAreas.php` — `loadSearchAreas` / `hydrateDiscreteLocationFromBlob` / `saveSearchAreas` | 4 Hire workflows | owner-only | all 9 + mirrors | `$ldnaRaw ? json_decode(...) ?? [] : []`; `empty()` guards at 48/71/77/100/103 | **INTOLERANT** |
| C-02 | `OfferListing/Buyer/BuyerOfferListing.php:1940,1961,1964,2434,2437` | Buyer Offer create | owner-only | all 9 + mirrors | inline copy of the same five guards | **INTOLERANT** |
| C-03 | `OfferListing/Buyer/BuyerOfferListingEdit.php` (same five sites) | Buyer Offer edit | owner-only | all 9 + mirrors | inline copy | **INTOLERANT** |
| C-04 | `OfferListing/Tenant/TenantOfferListing.php:3339` | Tenant Offer create | owner-only | all 9 + mirrors | `array_key_exists('cities', …)` — divergent, correct for `cities` | **CONDITIONALLY TOLERANT** |
| C-05 | `OfferListing/Tenant/TenantOfferListingEdit.php:2494` | Tenant Offer edit | owner-only | all 9 + mirrors | `array_key_exists('cities', …)` | **CONDITIONALLY TOLERANT** |
| C-06 | `HireBuyerAgent/BuyerAgentAuction.php` | Hire Buyer create | owner-only | via C-01 | trait host | **INTOLERANT** (inherits C-01) |
| C-07 | `HireBuyerAgent/BuyerAgentAuctionEdit.php` | Hire Buyer edit | owner-only | via C-01 | trait host | **INTOLERANT** (inherits C-01) |
| C-08 | `TenantAgentAuction.php` | Hire Tenant create | owner-only | via C-01 | trait host | **INTOLERANT** (inherits C-01) |
| C-09 | `TenantAgentAuctionEdit.php` | Hire Tenant edit | owner-only | via C-01 | trait host | **INTOLERANT** (inherits C-01) |

**Evidence.** All five guard sites and the three mirror-write lines are proven behaviourally by the G1a
suites (`G1aTraitPresenceSemanticsCharacterisationTest`, `G1aBuyerOfferInlineCharacterisationTest`,
`G1aWorkflowPersistenceMatrixCharacterisationTest`). Confidence **high**. C-01 … C-03 are INTOLERANT on
input class 5 (present-but-cleared): a cleared dimension is resurrected from a stale legacy mirror. C-06 …
C-09 are counted as inheriting rather than as separate defects — the defect is C-01's.

**Mutation / resurrection / leakage.** All nine mutate while reading (mirror merge into
`existingLocationDna`). All nine can resurrect a legacy mirror. None leaks publicly: exposure is
owner-only and geometry is *intended* here (v1.2 forbids changing owner editing behaviour).

### 7.B Public view controllers — reader + projection applier

| ID | File / symbol | Exposure | Fields | Access pattern | Tolerance |
|---|---|---|---|---|---|
| C-10 | `BuyerCriteriaAuctionController.php:383` (`view`) | fully public | all 9 | decode → enrich → `project()` before handoff | **TOLERANT** |
| C-11 | `TenantCriteriaAuctionController.php:653` (`view`) | fully public | all 9 | same | **TOLERANT** |
| C-12 | `BuyerOfferListingController.php:139` (`view`) | fully public | all 9 | `project()` + `stripFromMetaBag()` | **TOLERANT** |
| C-13 | `TenantOfferListingController.php:136` (`view`) | fully public | all 9 | `project()` + `stripFromMetaBag()` | **TOLERANT** |

**Evidence.** `PublicGeometryContainmentTest` (13 tests) asserts raw-response-body containment across all
four routes and tiers, including owner and unrelated-authenticated principals;
`PublicGeometryRegressionGuardTest` (7 tests) asserts each controller still applies the projection.
Confidence **high**. These are the only four consumers meeting the TOLERANT bar for *every* relevant case,
and they meet it because a test proves it — not because the code looks careful.

C-10 and C-11 are dual-role: each also writes the blob (2 `saveMeta` sites apiece) on the legacy
form-POST create/update path.

### 7.C Projection

| ID | File / symbol | Exposure | Access pattern | Tolerance |
|---|---|---|---|---|
| C-14 | `LocationDna/PublicGeometryProjection.php` | server-only, pure | `?array` in; `array_key_exists()` throughout; MARKER-gated idempotence | **TOLERANT** |

**Evidence.** `PublicGeometryProjectionTest` (15 tests) covers null input, absent keys, present-but-empty,
malformed values, idempotence, non-mutation and determinism. Confidence **high**. This is the **only**
consumer in the entire audit that uses `array_key_exists()` as its presence mechanism per §5.2, and the
only one that is safe on input classes 1–6 and 8 simultaneously.

### 7.D Read-only display

| ID | File / symbol | Exposure | Fields | Access pattern | Tolerance |
|---|---|---|---|---|---|
| C-15 | `components/location-dna-map.blade.php` | fully public (+ private hosts) | all 9 + projection markers | `!empty($prefs[MARKER])` presence indicators; consumes the projected array | **CONDITIONALLY TOLERANT** |
| C-16 | `tenant_criteria/view.blade.php:950` | fully public | all 9 | decodes independently, then `project()` | **TOLERANT** |
| C-17 | `LocationDna/LocationDnaChipPresenter.php:57,66,70,94,100` | public via browse cards | `flexible_location`, `polygons`, `radius_searches`, `cities`, `zip_codes` | `!empty($this->getArray($prefs, $key))`; `$prefs[$key] ?? null` | **CONDITIONALLY TOLERANT** |
| C-18 | `LocationDna/LocationPreferenceAnalyzer.php:68,74,76,144` | public via summary lines | all 9 | `empty($preferences)` early return; `!empty()` per key | **CONDITIONALLY TOLERANT** |

C-15's tolerance is conditional on its **caller**: it is inert and safe when handed a projected array, and
it does not itself redact — by design, per the G0.1 plan's "guard only, must not redact" rule. Handed an
unprojected array it would render whatever it is given. That is not a defect in C-15; it is a caller
contract, and it is why C-10 … C-13 are the load-bearing rows.

### 7.E Enrichment — consume a pre-decoded array

| ID | File / symbol | Signature evidence | Tolerance |
|---|---|---|---|
| C-19 | `BoundaryLookupService.php:39` | `resolve(?array $preferences, array $legacyLocation)` — **nullable** | **CONDITIONALLY TOLERANT** |
| C-20 | `FloodZoneLookupService.php:34` | `resolve(array $boundaryData, array $preferences)` | **CONDITIONALLY TOLERANT** |
| C-21 | `SchoolDistrictLookupService.php:34` | `resolve(array $boundaryData, array $preferences)` | **CONDITIONALLY TOLERANT** |
| C-22 | `LocationDnaEnrichmentRunner.php:52,66` | `run(array $boundaryData, array $preferences)` | **CONDITIONALLY TOLERANT** |
| C-23 | `LocationIntelligenceComposer.php:52,100` | `compose(array $boundaryData, array $preferences)` | **CONDITIONALLY TOLERANT** |

These five name the canonical key **only in docblocks** but are true consumers: each declares an `array
$preferences` parameter. They receive the **unprojected** blob by design — v1.2 requires enrichment to run
server-side on full geometry, and G0.1 deliberately applies the projection *after* these calls. A
non-nullable `array` hint means a `null` blob is a **TypeError**, not a graceful degradation; C-19 is the
only one that accepts `?array`. Callers currently pass `?? []`, so this is latent rather than live —
recorded as F-G1B-5.

### 7.F Matching

| ID | File / symbol | Exposure | Access pattern | Tolerance |
|---|---|---|---|---|
| C-24 | `LocationMatchEngine.php:67,107` | server-only | `match(array $preferences, array $propertyData)` | **CONDITIONALLY TOLERANT** |
| C-25 | `LocationMatchIntegrationService.php:62` | server-only | `build(array $preferences, array $propertyData)` | **CONDITIONALLY TOLERANT** |
| C-26 | `LocationMatchAuctionExtractor.php:101,103` | server-only | `metaValue(...)` then `!empty($dnaRaw)` | **CONDITIONALLY TOLERANT** |
| C-27 | `Jobs/ComputeCompatibilityScore.php:398,405-407` | server-only, **outside any request cycle** | `is_array($raw) ? $raw : (json_decode($raw, true) ?? [])` | **CONDITIONALLY TOLERANT** |
| C-28 | `Stellar/StellarBuyerResultsController.php:125-126` | authenticated | `!empty($criteriaData['radius_searches'])` | **CONDITIONALLY TOLERANT** |
| C-29 | `Bridge/CriteriaHashService.php:34-35` | server-only → **outbound MLS** | hashes `$payload->radiusSearches` / `->polygons` | **UNRESOLVED** |
| C-30 | `Stellar/Matching/DTO/BuyerCriteriaPayload.php:92-93` | server-only | `$data['radius_searches'] ?? []` | **CONDITIONALLY TOLERANT** |

C-27 is notable: it reads canonical state **outside any request cycle**, so no hydrator or projection is in
scope even in principle. C-29 is UNRESOLVED and material: §5.3 requires change-detection hashes to be
computed over the **canonicalised** form, never raw bytes, or omission presents as a spurious change. Whether
`CriteriaHashService` canonicalises before hashing could not be settled from the two matched lines —
F-G1B-6.

### 7.G Legacy criteria loading (Stellar)

| ID | File / symbol | Access pattern | Tolerance |
|---|---|---|---|
| C-31 | `Stellar/CriteriaListingResolver.php:308-311` | `is_array($ldnaRaw) ? $ldnaRaw : (json_decode(...) ?? [])` then `!empty($ldna['cities'])` | **CONDITIONALLY TOLERANT** |
| C-32 | `Stellar/BuyerCriteriaLoader.php` | same idiom | **CONDITIONALLY TOLERANT** |
| C-33 | `Stellar/TenantCriteriaLoader.php` | same idiom | **CONDITIONALLY TOLERANT** |
| C-34 | `Stellar/BuyerOfferListingCriteriaLoader.php` | same idiom | **CONDITIONALLY TOLERANT** |
| C-35 | `Stellar/TenantOfferListingCriteriaLoader.php` | same idiom | **CONDITIONALLY TOLERANT** |

This group is the cleanest in the audit for input classes 1–4 and 8: the `is_array()` pre-check means a
malformed string, a `null`, an already-decoded array and a missing row all converge safely.

### 7.H Summaries and PDF — durable documents

| ID | File / symbol | Exposure | Access pattern | Tolerance |
|---|---|---|---|---|
| C-36 | `AcceptedBidSummaryService.php:733,737-738,753` | durable PDF / signed doc | `data_get($listing->get, 'location_dna_preferences')` → `is_array($raw) ? … : json_decode((string) $raw, true)` → `if (!is_array($decoded) || empty($decoded))` bail | **CONDITIONALLY TOLERANT** |
| C-37 | `BuyerAcceptedBidSummaryService.php:468,472-473` | durable PDF / signed doc | same idiom via `$metaMap[...] ?? null` | **CONDITIONALLY TOLERANT** |

These write geometry into **durable documents**, which §4.3 flags and which R12 already tracks for
`location_intelligence_snapshot`. The `(string) $raw` cast means a `false` from `info()` becomes `""` and
decodes to `null`, caught by the `is_array` guard — verified, not assumed.

### 7.I Backfill, seed, transport

| ID | File / symbol | Role | Tolerance |
|---|---|---|---|
| C-38 | `Console/Commands/BackfillLocationSnapshots.php:134,140,142` | writes a projection (§15) | **CONDITIONALLY TOLERANT** |
| C-39 | `partials/location-dna/map-input.blade.php` | **owner editor / producer**, client-side | NOT APPLICABLE (out of scope by v1.2) |
| C-40 | `partials/location-dna/search-areas-bridge.blade.php` | transport-only, client-side | **UNRESOLVED** |
| C-41 | 4 tab partials (`…/property-preferences.blade.php` ×2, `…/property-details.blade.php` ×2) | transport-only hosts | **CONDITIONALLY TOLERANT** |
| — | `database/seeders/LocationDnaTestSeeder.php` | fixture producer, not a runtime consumer | NOT APPLICABLE |
| — | `buyer_criteria/{add,edit}.blade.php:439`, `tenant_criteria/{add,edit}.blade.php:427` | pass `existingLocationDna ?? []` into C-39 | transport-only |

C-40 is UNRESOLVED because it is the JavaScript bridge: this project has no JS test runner, so its
behaviour on any input class is unproven in either direction. See §11.

### 7.K Derived-store readers — canonical dimension names, non-canonical store

These three are the subject of F-G1B-1. They are true consumers of Location DNA data, but they read
`PropertyLocationDna::summary_json` rather than the canonical blob, and no writer puts canonical dimensions
there.

| ID | File / symbol | Workflow | Exposure | Fields read | Access pattern | Tolerance |
|---|---|---|---|---|---|---|
| C-42 | `buyer_criteria/add-bid.blade.php:346-349` | agent/seller bidding on buyer criteria | **`sellerBidderAuth`** — authenticated non-owner (`routes/web.php:620-621`) | `cities`, `zip_codes`, `neighborhoods`, `radius_searches`, `polygons`, `flexible_location`, `location_notes` | `is_array($locationDna->summary_json) ? … : []` then `$dnaPrefs['key'] ?? default` | **CONDITIONALLY TOLERANT** |
| C-43 | `tenant_criteria/add-bid.blade.php:5173-5177` | bidding on tenant criteria | bidder-gated, non-owner | `radius_searches`, `polygons`, `location_notes` | same idiom | **CONDITIONALLY TOLERANT** |
| C-44 | `offers/show.blade.php:395,528` | offer detail (buyer + tenant blocks) | `auth` (`routes/web.php:374`) — authenticated participants | `preferred_cities`, `preferred_zips`, `preferred_neighborhoods`, `flexible_location`, `radius_miles`, `location_notes` | `data_get($dnaJson, 'key', default)` | **CONDITIONALLY TOLERANT** |

**Behaviour on every input class is identical and benign today:** because the keys are never present in
`summary_json`, all three resolve to their defaults regardless of what the canonical blob contains. They are
`is_array()`-guarded on the store itself, so a null or malformed `summary_json` also degrades safely.

**They are classified CONDITIONALLY TOLERANT rather than NOT APPLICABLE** because they *are* wired to the
canonical dimension vocabulary and would begin rendering it the moment the store carried it — the condition
is "so long as no writer persists canonical dimensions into `summary_json`". C-44 additionally reads
`preferred_cities` / `preferred_zips` / `preferred_neighborhoods`, which are **not canonical dimension names
at all** (§5.1 has `cities`, `zip_codes`, and `neighborhoods` is withdrawn per §18) — a third vocabulary,
recorded here rather than as a separate finding because it too is a dead read.

### 7.J Tests encoding a runtime contract (18, counted separately)

`PublicGeometryProjectionTest` · `PublicGeometryContainmentTest` · `PublicGeometryRegressionGuardTest` ·
`G1aTraitPresenceSemanticsCharacterisationTest` · `G1aRecordInterpretationCharacterisationTest` ·
`G1aCrossDimensionPreservationCharacterisationTest` · `G1aBuyerOfferInlineCharacterisationTest` ·
`G1aWorkflowPersistenceMatrixCharacterisationTest` · `SearchAreasPersistenceCharacterisationTest` ·
`TenantOfferCitiesMirrorTest` · `SearchAreasGeometryContractTest` · `SearchAreasWidgetContractTest` ·
`HireSearchAreasParityTest` · `SearchAreasStateCountyRoundTripTest` · `SearchAreasPartialTest` ·
`OfferWorkflowReadinessTest` · `Dna/CandidateNarrowingComplianceTest` ·
`Stellar/LocationDnaRoundTripTest`

These are the evidence base for every TOLERANT classification above. `SearchAreasStateCountyRoundTripTest`
currently fails 1 of 4 on a pre-existing SQLite/`ILIKE` issue unrelated to the contract (F-G1B-9).

---

## 8. Public-surface exposure matrix

Every path that can reach a public or non-owner viewer. Route middleware was resolved from `routes/web.php`
rather than inferred.

| Surface | Route / middleware | Geometry? | `radius`? | `polygons`? | `location_notes`? | Admin labels? | Projection |
|---|---|---|---|---|---|---|---|
| Buyer criteria view (C-10) | public | **no** | no | no | no | **yes** | applied `:383` |
| Tenant criteria view (C-11) | public | **no** | no | no | no | **yes** | applied `:653` |
| Buyer offer listing view (C-12) | public | **no** | no | no | no | **yes** | applied `:139` + meta-bag strip |
| Tenant offer listing view (C-13) | public | **no** | no | no | no | **yes** | applied `:136` + meta-bag strip |
| Shared viewer component (C-15) | rendered by the above | **no** | no | no | no | yes | consumes projected array |
| `tenant_criteria/view` inline panel (C-16) | public | **no** | no | no | no | yes | applied `:950` |
| Browse-card chips (C-17) | public | **no** — presence only | no | presence only | no | yes | reads projected array |
| **`buyer_criteria/add-bid` (C-42)** | `sellerBidderAuth` — **authenticated non-owner** | **no (dead read)** | dead read | dead read | dead read | yes | **none — reads `summary_json`** |
| **`tenant_criteria/add-bid` (C-43)** | bidder-gated | **no (dead read)** | dead read | dead read | dead read | yes | **none — reads `summary_json`** |
| **`offers/show` (C-44)** | `auth` — authenticated participants | **no (dead read)** | dead read | n/a | dead read | yes | **none — reads `summary_json`** |
| Owner editor `map-input` (C-39) | owner-only edit routes | **yes, intended** | yes | yes | yes | yes | deliberately excluded |
| Accepted-bid PDF (C-36/37) | durable doc, party-visible | **yes** | yes | yes | yes | yes | **none — separate path** |
| Bridge outbound (C-29) | third-party MLS API | bounding boxes | derived | derived | no | yes | out of scope (R13) |

**Verified:** exactly five view files name geometry keys —
`components/location-dna-map.blade.php`, `partials/location-dna/map-input.blade.php`,
`tenant_criteria/view.blade.php`, `buyer_criteria/add-bid.blade.php`, `tenant_criteria/add-bid.blade.php`
— and all five are accounted for above. `buyer_criteria/view.blade.php:277` passes
`$locationDnaPreferences ?? null` to the component and reads no geometry key itself, so it inherits C-10's
projection.

**No live bypass of `PublicGeometryProjection` was found.** The three `summary_json` readers are the only
unprojected reads of geometry *names* on non-owner surfaces, and they resolve to defaults today because no
writer populates those keys — F-G1B-1.

---

## 9. Findings

### F-G1B-1 · Three non-owner surfaces are pre-wired to render geometry from a store that does not carry it

**Observed fact.** `buyer_criteria/add-bid.blade.php:346-349`, `tenant_criteria/add-bid.blade.php:5173-5177`
and `offers/show.blade.php:395,528` read `polygons`, `radius_searches`, `cities`, `zip_codes`,
`neighborhoods`, `flexible_location` and `location_notes` out of `PropertyLocationDna::summary_json`. The
**only** writer of that column is `LocationDnaSummaryService.php:209`, which persists
`geocode`, `nearest_by_category`, `category_counts`, four thematic POI blocks, `missing_categories` and
`error_categories` (verified at `:195-209`). **No canonical dimension key is ever written there.** Every
one of those reads therefore resolves to its `?? []` / `?? ''` default. `buyer_criteria/add-bid` is reached
via `Route::get("/criteria/auction/bid/{id}", …)` inside `Route::middleware('sellerBidderAuth')`
(`routes/web.php:620-621`) — an authenticated **non-owner** bidder surface.

**Inferred risk.** These views were clearly authored expecting the canonical shape in `summary_json`. If any
future writer persists canonical dimensions there — a plausible convergence, since the store already holds
a location summary — three non-owner surfaces would begin rendering exact polygons, radius centres and
free-text notes with **no projection in the path**. D4 decided that authentication alone does not authorise
geometry; these surfaces would violate it silently, and no existing test would fail.

**Recommended later action.** Do not "fix" the views. Decide whether `summary_json` is permitted to carry
canonical dimensions at all (a §15 projection-consistency question), and if it is, require the projection
at that boundary. A regression guard asserting that no view reads a canonical dimension key out of
`summary_json` would pin it cheaply. Not a G1b action.

### F-G1B-2 · `schema_version` has zero production consumers

**Observed fact.** `grep -r schema_version app resources config routes database` returns exactly one hit:
`config/overture_places.php:43`, an unrelated Overture dataset version. No production file reads or writes
`schema_version` on the canonical blob.

**Inferred risk.** §5.5's refuse-to-interpret rule for an unknown future version is unimplemented across all
41 consumers, so a record written by a newer writer is read — and rewritten — by every one of them. G1a
proved this behaviourally for the trait
(`test_s2_unknown_future_schema_version_is_read_and_written_without_refusal`); this audit establishes that
it is not a trait defect but a **system-wide absence**.

**Recommended later action.** Belongs to G1c's hydrator as the single reader of `schema_version`. The audit
records that there is no existing consumer to migrate — only consumers to route through the new hydrator.

### F-G1B-3 · The `empty()` blindness is system-wide, not confined to the editing surfaces

**Observed fact.** 28 of 44 consumer IDs decide presence with `empty()` / `!empty()` on the dimension key.
Representative verified sites: `LocationDnaChipPresenter.php:57,66,70`, `LocationPreferenceAnalyzer.php:68,74`,
`CriteriaListingResolver.php:311`, `AcceptedBidSummaryService.php:738`, `StellarBuyerResultsController.php:125`.
`PublicGeometryProjection` is the **only** consumer using `array_key_exists()`.

**Inferred risk.** A user who clears a dimension is, to 30 consumers, indistinguishable from a user who never
authored it. For display consumers the consequence is benign (nothing renders either way). For **matching**
consumers (C-24 … C-30) it is not: "no preference" and "explicitly no cities" may warrant different ranking,
and today they cannot differ.

**Recommended later action.** G1c's hydrator should return presence information consumers can act on; the
G1b finding is that a mechanical `empty()` → `array_key_exists()` sweep across 30 read sites is **not** the
right shape, because most of them genuinely want "is there anything to show".

### F-G1B-4 · No consumer validates the blob's shape

**Observed fact.** The validator category is **empty**. No consumer asserts that `polygons` entries have a
`path`, that `radius_searches` entries have `lat`/`lng`/`radius_miles`, or that `state` is a string. Guards
are limited to `is_array()` on the top-level decode.

**Inferred risk.** A structurally valid but semantically malformed blob (e.g. `polygons: [{"label":"x"}]`
with no `path`) reaches renderers and the matching engine unchecked. G1a proved the projection survives
malformed values (`test_malformed_geometry_values_do_not_throw`); nothing proves the *renderers* do.

**Recommended later action.** G1c's serializer/hydrator boundary is where shape validation belongs. Record
that no existing validator must be preserved — there is none.

### F-G1B-5 · Four enrichment services declare non-nullable `array` and would TypeError on a null blob

**Observed fact.** `FloodZoneLookupService.php:34`, `SchoolDistrictLookupService.php:34`,
`LocationDnaEnrichmentRunner.php:52` and `LocationIntelligenceComposer.php:52` all declare
`array $preferences`. Only `BoundaryLookupService.php:39` declares `?array`.

**Inferred risk.** Latent, not live: current callers coerce with `?? []`. A future caller passing the raw
decode of an absent blob (`null`) would raise a TypeError inside enrichment rather than degrade visibly. The
G0.1 controllers deliberately call enrichment **before** projection, so these run on unprojected geometry —
correct per v1.2, and worth stating explicitly so a later change does not "tidy" the ordering.

**Recommended later action.** Decide at G1c whether the domain core hands enrichment a guaranteed array. No
signature change during G1b.

### F-G1B-6 · Whether the Bridge criteria hash canonicalises before hashing is UNRESOLVED

**Observed fact.** `CriteriaHashService.php:34-35` includes `radius_searches` and `polygons` in the hashed
payload. §5.3 requires such hashes to be computed over the **canonicalised** form, never raw bytes, or
omission presents as a spurious change. §4.3 F-C3 records that `CriteriaHashService::hash()` does
canonicalise (recursive `ksort`, null-drop, order-independent list sorting) — but that claim was not
re-verified line-by-line in this audit.

**Inferred risk.** If canonicalisation does not cover the nested geometry arrays, G1c's omission capability
would cause spurious cache-key churn and unnecessary outbound MLS refetches.

**Recommended later action.** Re-verify `CriteriaHashService::hash()` against nested `polygons` /
`radius_searches` before G1c ships omission. Smallest test named in §11.

### F-G1B-7 · Accepted-bid summaries are a second unprojected durable geometry path

**Observed fact.** C-36 (`AcceptedBidSummaryService.php:733`) and C-37
(`BuyerAcceptedBidSummaryService.php:468`) read the canonical blob and render location detail into durable
PDF/summary documents. Neither applies `PublicGeometryProjection`.

**Inferred risk.** These documents are visible to transaction parties — non-owners under D4's reading. R12
already tracks `location_intelligence_snapshot` retention; this is the *rendering* counterpart and is not
yet tracked. Whether that is acceptable is a policy question, not a defect: parties to an accepted bid may
legitimately need the geometry.

**Recommended later action.** An explicit owner decision on whether accepted-bid documents are inside or
outside the D4 boundary. Currently undecided and **not decided here**.

### F-G1B-8 · `LocationDnaChipPresenter`'s docblock describes a radius shape the contract does not use

**Observed fact.** `LocationDnaChipPresenter.php:25` documents radius entries as having `'center' +
'radius_miles'`. The canonical contract (§5.1) specifies `lat`, `lng`, `radius_miles`, `address` XOR
`label`. The **code** never reads `center` — it only tests presence via
`!empty($this->getArray($preferences, 'radius_searches'))` at `:70`.

**Inferred risk.** Documentation drift only; no behavioural consequence. Recorded because a future
maintainer implementing per-entry rendering from that docblock would read a key that does not exist.

**Recommended later action.** Correct the docblock in whatever increment next touches the file. Not a G1b
change.

### F-G1B-9 · A pre-existing unrelated test failure persists in the contract's test set

**Observed fact.** `SearchAreasStateCountyRoundTripTest` fails 1 of 4 with
`SQLSTATE[HY000]: near "ILIKE": syntax error`, raised from
`BuyerOfferListingEdit::getPlaceSuggestions()` (the `->get()` at `:980`). `ILIKE` is PostgreSQL syntax; the
suite runs on SQLite. Reproduced identically in the G0.1 control worktree with no G1a file present.

**Inferred risk.** None to the contract. It reduces the evidence base slightly: one of the 18 contract tests
is not fully green.

**Recommended later action.** Production fix, outside G1a/G1b scope.

---

## 10. False positives and excluded occurrences

| Excluded | Count | Reason |
|---|---|---|
| `config/overture_places.php:43` (`schema_version`) | 1 | Overture dataset version; unrelated domain. The **only** false positive in the audited union. |
| `location_dna` superset beyond the union | 77 → 52 | The broader term captures the **adjacent scoring/POI domain** — `PropertyLocationDna`, `dna_scores`, `LocationDnaRankingEngine`, POI tile caches, marketing/intelligence context services. These consume *derived* Location DNA, not the canonical preferences contract. Excluded from the consumer count, **except** the three `summary_json` readers, which are included because they read canonical dimension **names** (F-G1B-1). |
| `polygons` superset beyond the union | 34 → in-union only | Spatial-corpus and boundary-import code (`BoundaryGeometry`, Overture/TIGER importers) uses `polygons` for provider boundaries, not user-authored geometry. |
| `search_areas` / `searchAreas` | 0 | Not runtime identifiers anywhere in the tree — documentation vocabulary only. Recorded so a future reader does not re-search for them. |
| Commented-out `array_key_exists` in `BuyerOfferListing.php:2349`, `BuyerOfferListingEdit.php:2366` | 2 | Dead commented code about `$this->enable`; unrelated to the cities merge. Already documented in `G1aBuyerOfferInlineCharacterisationTest`. |
| `compatibility_summary_json` matches | several | Distinct column on `ListingCompatibilityScore`; not `summary_json`. |

**No occurrence was excluded merely because it appeared in a docblock.** Seven services name the canonical
key only in prose and were **included** as true consumers on the strength of their `array $preferences`
signatures (§7.E, §7.F).

---

## 11. Unknowns requiring later tests

Four items could not be settled statically. Each names the **smallest** characterisation test that would
settle it. **None was written — that is not G1b.**

| # | Unknown | Consumer | Smallest test that would settle it |
|---|---|---|---|
| U-G1B-1 | Does the JS bridge preserve, drop or empty each input class on the wire? | C-40 `search-areas-bridge.blade.php` | Requires a JS test runner, which the project lacks — this is a **G2** dependency, not a PHP test. Until G2 the bridge's tolerance is unproven in both directions. |
| U-G1B-2 | Does `CriteriaHashService::hash()` canonicalise nested `polygons` / `radius_searches`, so an omitted key does not change the hash? | C-29 | One unit test: hash two payloads whose geometry differs only in key order and in an omitted-vs-empty dimension; assert the canonicalised hashes match where meaning matches. |
| U-G1B-3 | Do the renderers survive a structurally valid but semantically malformed entry (`polygons: [{"label":"x"}]`, no `path`)? | C-15, C-17, C-39 | One feature test rendering the shared viewer with a path-less polygon and a centre-less radius entry; assert no exception and no partial-geometry emission. |
| U-G1B-4 | Does an accepted-bid PDF render exact geometry to a non-owner party today? | C-36, C-37 | One integration test generating a summary for a record with polygons and asserting on the rendered HTML/PDF text. Settles F-G1B-7's factual half; the policy half stays an owner decision. |

---

## 12. Implications for G1c–G1g

Stated as consequences of the audit, not as decisions.

- **G1c (domain core).** The hydrator has **no incumbent to displace** on `schema_version` (F-G1B-2) and no
  incumbent validator to preserve (F-G1B-4) — both are greenfield. Conversely it must serve **41**
  consumers, of which 30 want a "is there anything to show" answer rather than raw presence (F-G1B-3), so a
  presence API that only exposes `array_key_exists` semantics would force 30 call-site rewrites. The
  omission capability interacts with F-G1B-6: settle the hash question before shipping omission.
- **G1d (capability resolver).** C-27 reads canonical state **outside any request cycle**, so it has no
  workflow context to pass. The resolver's context map must tolerate a caller with no context and
  deny-by-default without breaking a queued job that legitimately reads.
- **G1e (provenance).** §7.E confirms enrichment runs on unprojected geometry by design and *before* the
  projection. Provenance recording must not be inserted in a way that reorders those calls.
- **G1f (consolidation).** Unchanged by this audit: three defective implementations to serve, two correct
  behaviours to preserve. The audit adds that the **read** side will not notice the change — no read
  consumer depends on the resurrection behaviour, so G1f's parity risk is confined to the editing surfaces
  G1a characterised.
- **G1g (adapters).** C-40's unprovable tolerance (U-G1B-1) means the Livewire adapter's contract suite
  cannot claim end-to-end coverage until G2 exists. Plan the adapter suite to stop at the PHP boundary and
  say so.
- **G3 and beyond.** F-G1B-1 and F-G1B-7 are both surfaces a renderer gate would light up. Neither is a
  G0.1 regression; both are paths G0.1 never claimed to cover.

---

## 13. Matters explicitly NOT decided here

1. **D-G1-1 … D-G1-6 all remain open.** Nothing in this audit resolves the contract, the operation
   vocabulary, the concurrency mechanism, the §18 withdrawals, the consolidation direction, or the branch
   sequencing.
2. Whether `property_location_dna.summary_json` may carry canonical dimensions (F-G1B-1).
3. Whether accepted-bid documents fall inside or outside the D4 geometry boundary (F-G1B-7).
4. Whether the 30 `empty()` read sites should change at all, or whether the hydrator should serve them as
   they are (F-G1B-3).
5. Whether enrichment should be guaranteed a non-null array (F-G1B-5).
6. Any remediation of any finding. **No fix was implemented.**
7. Whether `neighborhoods` read-tolerance (§18) needs a test — observed in the wild at
   `buyer_criteria/add-bid` and `offers/show`, but its disposition is a D-G1-4 matter.

---

## 14. G1b stop-condition assessment

§12 of the G1 report defines G1b's stop condition as: *"Every consumer classified tolerant / defective, with
evidence."*

**Assessed: MET, with four declared UNRESOLVED items.**

| Criterion | Status |
|---|---|
| Inventory recomputed from the tree, not carried over | **Met** — 52 occurrence files / 47 ID-carrying / 44 IDs; the 43 figure reconciled and explained (§4) |
| Every true consumer classified | **Met** — 44 of 44 IDs carry a file, an access pattern, evidence and a classification (§7) |
| Classification backed by code evidence or an existing test | **Met** — TOLERANT was awarded only 4 times, each backed by a named passing suite |
| Public-surface review complete | **Met** — all five geometry-naming views resolved; no live projection bypass found (§8) |
| Unknowns declared rather than guessed | **Met** — 2 UNRESOLVED consumers (C-29, C-40) and 4 declared unknowns, each with the smallest settling test named (§11) |
| Read-only; no production, test or config change | **Met** |
| Findings separate fact from inferred risk from recommendation | **Met** — all nine findings use that structure |

**Residue carried forward, not hidden:** the four UNRESOLVED items. U-G1B-1 is blocked on G2 (no JS test
runner) and cannot be closed by any PHP increment. U-G1B-2, U-G1B-3 and U-G1B-4 are each closable by a
single small test, and none of the three blocks G1c from starting — they block *shipping* omission
(U-G1B-2) and any renderer gate (U-G1B-3, U-G1B-4).

**G1c is not started. G1d–G1g are not started. No fix from this audit has been implemented.**
