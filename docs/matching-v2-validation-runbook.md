# Matching V2 — Pre-C7 Validation Runbook (via `matching:preview`)

**Status:** Proposed plan — no code. Execute in **staging/dev**, never production.
**Goal:** Judge real match quality + compliance across every direction and edge case, then decide whether **C7 = persistence/caching** or **C7 = Match Check exposure**.

`matching:preview {listingType} {listingId}` is read-only and force-enables Matching V2 in-process, so `MATCHING_V2_ENABLED` can stay OFF. It does **not** enable generation — see Phase 0.

---

## Phase 0 — Prerequisite: give the engine something to rank

The pipeline reads `dna_scores`. If generation has never run, every preview returns "no candidates" or "all undetermined." So first, in a **non-production** environment:

1. Enable generation for the run: `DNA_SCORES_GENERATION_ENABLED=true` (staging only).
2. Backfill a curated corpus (all four `*_agent` roles): `php artisan dna:generate-scores` (bulk; idempotent `updateOrCreate`). If it supports scoping, generate a representative few hundred listings; otherwise generate all and sample.
3. Confirm rows exist and are the current versions:
   ```
   php artisan tinker --execute="echo App\Models\DnaScore::count().' rows; '.App\Models\DnaScore::distinct('score_key')->pluck('score_key')->implode(',');"
   ```
   Expect non-zero, with keys incl. `pet_friendliness`, `lock_and_leave` (V2), `waterfront_lifestyle`, `location_*`.
4. Leave `MATCHING_V2_ENABLED=false` (preview force-enables in-process).

**Prod note:** do NOT enable generation or the flag in production to run this; validation is a staging exercise.

---

## Phase 1 — Build the subject roster (read-only)

Pick real subjects that actually have DNA on the right side, and are live offer-listings. Example selection queries (adapt in tinker/psql, read-only):

- **Scored demand subjects** (buyer/tenant): `SELECT DISTINCT listing_type, listing_id FROM dna_scores WHERE side='demand' AND listing_type IN ('buyer_agent','tenant_agent') LIMIT 25;`
- **Scored property subjects** (seller/landlord): same with `side='property'` and `('seller_agent','landlord_agent')`.
- **55+ listings** (candidates for compliance tests): auctions with `leasing_55_plus` meta = Yes.
- **55+-eligible vs non-eligible seekers:** buyer/tenant auctions with `leasing_55_plus` meta Yes vs No/absent.
- **Low-DNA subjects:** subjects with only 1–2 distinct `score_key`s (sparse): `SELECT listing_type, listing_id, COUNT(DISTINCT score_key) c FROM dna_scores GROUP BY 1,2 HAVING COUNT(DISTINCT score_key) <= 2;`

Record ~5 subjects per category into a roster file so runs are repeatable.

---

## Phase 2 — Validation matrix (exact commands + pass/fail rubric)

Capture machine output for review and diffing: append `--json` and redirect, e.g.
`php artisan matching:preview buyer_agent 12345 --json > out/buyer-12345.json`.

### A. Buyer → listings (DemandToListings)
```
php artisan matching:preview buyer_agent <BUYER_ID> --limit=25
php artisan matching:preview buyer_agent <BUYER_ID> --json > out/A-buyer-<BUYER_ID>.json
```
**Pass:** `direction=DemandToListings`; matches are `seller_agent`/`landlord_agent` only; best-first by tier→value; `candidates_considered` > 0; tiers look plausible (strong overlaps rank above weak). **Fail:** demand-type candidates appear; ordering inverted; determined=0 despite scored candidates.

### B. Tenant → listings (DemandToListings)
```
php artisan matching:preview tenant_agent <TENANT_ID> --limit=25 --json > out/B-tenant-<TENANT_ID>.json
```
**Pass:** same as A; confirms the tenant loader path and rental listings surface. **Watch:** commercial-lease vs residential coherence.

### C. Listing → demand (ListingToDemands)
```
php artisan matching:preview seller_agent   <SELLER_ID>   --limit=25 --json > out/C-seller-<SELLER_ID>.json
php artisan matching:preview landlord_agent <LANDLORD_ID> --limit=25 --json > out/C-landlord-<LANDLORD_ID>.json
```
**Pass:** `direction=ListingToDemands`; matches are `buyer_agent`/`tenant_agent` only; the reverse direction produces symmetric, sensible tiers.

### D. Mixed seller_agent / landlord_agent candidate pools
Use a buyer/tenant whose eligible pool spans both property types.
```
php artisan matching:preview buyer_agent <BUYER_ID> --json | jq '[.matches[].listing_type] | unique'
```
**Pass:** both `seller_agent` and `landlord_agent` appear, each match carries the **correct** `listing_type`, and no two rows are ambiguous even when `listing_id` values collide across types (the C6 fix). **Fail:** a listing_id shows the wrong type, or types are missing.

### E. 55+ compliance (the load-bearing legal check)
Run each with the gate's env knobs to prove it holds in all modes.
```
# Non-eligible seeker must NOT receive senior-restricted listings:
php artisan matching:preview buyer_agent <NON_ELIGIBLE_BUYER_ID> --json > out/E1.json
# Eligible seeker SHOULD receive senior listings:
php artisan matching:preview buyer_agent <ELIGIBLE_BUYER_ID> --json > out/E2.json
# Reverse: a senior-restricted listing must NOT surface non-eligible seekers:
php artisan matching:preview seller_agent <SENIOR_LISTING_ID> --json > out/E3.json
# Gate must run even with optional hard filters OFF (default) AND ON:
MATCHING_V2_HARD_FILTERS_ENABLED=true  php artisan matching:preview buyer_agent <NON_ELIGIBLE_BUYER_ID> --json > out/E4.json
# Conservative policy on unknown senior data:
MATCHING_V2_SENIOR_UNKNOWN_POLICY=closed php artisan matching:preview buyer_agent <NON_ELIGIBLE_BUYER_ID> --json > out/E5.json
```
**Pass (hard requirement):** in E1/E3/E4, cross-reference every returned `listing_id` against known senior-restricted ids — **zero** senior-restricted listings for a non-eligible seeker (and vice-versa); E2 includes them; E5 excludes unknown-senior candidates (fail-closed). **Any violation here blocks all downstream C7 work.**

### F. No-DNA / low-DNA
```
php artisan matching:preview buyer_agent <NO_DNA_SUBJECT_ID> --json > out/F1.json   # subject has no dna_scores
php artisan matching:preview buyer_agent <LOW_DNA_SUBJECT_ID> --json > out/F2.json  # 1–2 score keys
```
**Pass:** F1 → `determined_count=0`, `undetermined_count = candidates_considered`, no crash, `isEmpty` true. F2 → mostly lower tiers / higher `undetermined`; the result degrades gracefully rather than fabricating confident matches.

### G. High- vs low-confidence matches
Inspect `--json` per-match `confidence` and `coverage` (from the §F6 `MatchTierResult`).
```
php artisan matching:preview buyer_agent <RICH_SUBJECT_ID> --json | jq '.matches[0]'
```
**Pass:** fully-scored subject+candidate with strong overlap → Exact/Strong tier, high value, high confidence/coverage. Sparse pairs → Similar/Opportunity, low confidence. **Fail:** high tier assigned on thin coverage (over-confidence), or confidence not tracking data completeness.

### H. Truncation / scale
Pick a subject with a large eligible pool.
```
php artisan matching:preview buyer_agent <BIG_POOL_ID> --json | jq '{considered:.candidates_considered,truncated:.candidate_pool_truncated}'
php artisan matching:preview buyer_agent <BIG_POOL_ID> --cap=50  --json | jq '.candidate_pool_truncated'
php artisan matching:preview buyer_agent <BIG_POOL_ID> --cap=500 --json | jq '.candidate_pool_truncated'
```
**Pass:** `candidate_pool_truncated=true` when the pool exceeds the cap; the flag is honest; results stay stable/deterministic as cap grows.

---

## Phase 3 — Cross-cutting checks (run on a handful from each category)

- **Determinism:** run any preview twice, `diff` the two `--json` files → **identical**. Non-determinism is a bug.
  ```
  php artisan matching:preview buyer_agent <ID> --json > a.json; php artisan matching:preview buyer_agent <ID> --json > b.json; diff a.json b.json
  ```
- **Read-only:** snapshot counts before/after a batch of previews → unchanged.
  ```
  php artisan tinker --execute="echo App\Models\DnaScore::count();"   # before and after a run of previews
  ```
- **Latency (feeds the C7 decision):** time a representative preview and note pool size.
  ```
  time php artisan matching:preview buyer_agent <BIG_POOL_ID> --cap=200
  ```

---

## Phase 4 — Evidence & review rubric

- Keep every `--json` under `out/` named by scenario; review as a batch.
- Score each result on: **direction correct · types correct · ordering sensible · compliance clean · confidence tracks completeness · edges graceful · deterministic**.
- Tally: any **compliance** miss = blocker; quality issues cluster into "generation/scoring" vs "narrowing" vs "ranking" so remediation lands in the right layer.

---

## Phase 5 — C7 decision matrix (what these runs should decide)

| Observation from Phases 2–3 | Implication for C7 |
|---|---|
| Compliance violation anywhere (Phase E) | **Neither.** Fix the gate/generation first; re-run E. |
| Quality gaps (bad tiers, over-confidence, wrong ordering) | **Neither.** Fix the responsible layer (scoring/narrowing/ranking); re-validate. |
| Quality good **and** per-preview latency high / pools large / recompute expensive | **C7 = persistence/caching.** Materialize/rank offline so reads are cheap and stable before any UI. |
| Quality good **and** latency already low per subject | Caching optional; **Match Check exposure** becomes viable — but still gate on compliance sign-off and likely a thin result cache. |
| Truncation frequently true at the default cap | Favor **persistence/caching** (and revisit cap/marketplace-scale strategy) before exposure. |

**Recommended sequencing:** run Phase 0–1 setup, then E (compliance) as the gate, then A–D + F–H for quality, then Phase 3. Decide C7 from the matrix. My prior recommendation stands — if quality is clean, **C7 = persistence/caching before exposure** — but these runs are what should confirm it with real numbers rather than assumption.

---

## What I need from you to proceed to execution

- Confirmation this runs in **staging** with generation temporarily enabled there.
- A go-ahead to (optionally) turn this runbook into a small **read-only harness command** (e.g. `matching:validate` that runs the roster and writes the `out/` JSON + a summary) — that would be a tiny additive, read-only C6.1, only if you want the batch automated. Otherwise we execute the commands above by hand.
