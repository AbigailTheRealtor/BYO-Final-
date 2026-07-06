# Matching V2 — 55+ Leak Remediation in `LockAndLeaveScoreService` — Scope Proposal

**Status:** Draft for review / approval — **no code yet**
**Date:** 2026-07-05
**Type:** Generation-side change (writes `dna_scores`). Independent of the read-only Matching V2 consumption slices (C1–C4).
**Precondition it unblocks:** any UI/API surfacing of Matching V2 explanations or score inputs.
**Tracked debt this closes:** `docs/tech-debt-55plus-leak-in-dna-scores.md` (Slice 2B, OD-4).

---

## 0. The leak, precisely

`LockAndLeaveScoreService::scoreDemand()` (`app/Services/Dna/Scores/LockAndLeaveScoreService.php`) folds a searcher's 55+ status into the persisted `dna_scores` row (`score_key='lock_and_leave'`, `side='demand'`) in **three** places:

| Line | Leak |
|---|---|
| 126 | reads `demand.age_targeted` |
| 131 | `+25` **data_completeness** when age is present |
| 133 | writes `age_targeted => $age55` into `$inputs` → persisted to `inputs_json` |
| 157–160 | `+15` to **value** and appends the literal `"55+ targeted"` → persisted to `explanation` |

Confirmed by investigation:
- **`scoreProperty()` never touches age** — only structure/HOA/acreage/amenities/condition. No change needed there.
- **Nothing downstream reads the persisted `inputs_json.age_targeted`.** The only reader of the canonical `demand.age_targeted` is this service itself; the only writer is `ByoListingAdapter` (`demand.age_targeted => ['leasing_55_plus','bool']`). Removing the service's use of it breaks no consumer.
- **The 2B compliance gate does NOT depend on this leak** — `SeniorCommunityComplianceGate` reads the `leasing_55_plus` meta directly via `OnPlatformCandidateAttributeResolver`, never the score's value/inputs/explanation.
- **This environment has 0 persisted `dna_scores` rows** (generation is gated off by `DNA_SCORES_GENERATION_ENABLED`, default false), so there is nothing to clean up *here*; cleanup only matters in any environment where generation has run.

---

## 1. Exactly what needs to change

Single file: `LockAndLeaveScoreService::scoreDemand()`, plus a version bump. **Recommended approach = Option B (full decouple)** — see §3 for the A/B decision.

Under Option B:

1. **Remove** `$age55 = $listing->get('demand.age_targeted')` (line 126).
2. **Remove** the `age_targeted` key from `$inputs` (line 133) → `inputs_json` will carry only `current_status` and `purchase_purpose`.
3. **Remove** the `if ($age55 === true) { $value += 15; $clauses[] = '55+ targeted'; }` block (lines 157–160) → value and explanation no longer reflect age.
4. **Rebalance data_completeness** over the two remaining objective, self-declared signals so it still sums to 100 (proposed `purchase_purpose 55` + `current_status 45`; exact split is **R-2**). Removes the `+25` age dimension (line 131).
5. **Bump the version** `LOCK_AND_LEAVE_V1 → LOCK_AND_LEAVE_V2` (constant `VERSION`). This versions regenerated rows and makes any lingering V1 row identifiable for cleanup/verification.
6. **Docblock**: keep the Fair-Housing note (§F3) and reword so it no longer implies age is an input; the "retiree/downsizer intent" signals stay (they come from `current_status`/`purchase_purpose`, not age).

Not changed: `scoreProperty()`, `ByoListingAdapter` (the canonical `demand.age_targeted` mapping stays — it is in-memory only, never persisted after this change, and remains a legitimate canonical field used by the 2B gate's *meta* path; removing it is optional future cleanup, **R-4**), the generator/persistence layer, and any schema (no migration — no column changes).

---

## 2. Existing `dna_scores` cleanup / backfill

**Is cleanup needed?** Only in environments where generation has already run. Every V1 `demand` + `lock_and_leave` row persisted the leak: `inputs_json.age_targeted` (true *or* false), and — for 55+ demands — the `"55+ targeted"` explanation clause and the `+15` in `value`. Production most likely has **zero** such rows (generation flag off), but dev/staging may.

**Mechanism (R-3):**

- **Primary — regeneration (recommended).** Generation is idempotent: `SymmetricScoreDnaGenerator` does `updateOrCreate` on the natural key `(listing_type, listing_id, score_key, side)`. Re-running the existing bulk command `php artisan dna:generate-scores` after deploy overwrites `value` (no +15), `inputs_json` (no `age_targeted`), `explanation` (no "55+"), and `version` (→ V2) for every regenerated listing. This is the natural source-of-truth path and needs no new code.
- **Stragglers.** Rows for listings that no longer regenerate (deleted/inactive) would keep V1 leaked content. Two options: (a) a tiny idempotent command `dna:scrub-lock-and-leave-age` that targets `WHERE score_key='lock_and_leave' AND side='demand' AND version='LOCK_AND_LEAVE_V1'` and either deletes them (they regenerate on next save) or strips `age_targeted`/"55+"; or (b) accept them as unreachable and rely on the verification query. Recommend (a) as a small safety net.
- **Not a schema migration** — this is data, not DDL. If coded, it is an artisan command, not a `database/migrations` file.

**Verification (part of the change):** a query/assertion that, post-remediation, there are **zero** `demand`+`lock_and_leave` rows whose `inputs_json` contains `age_targeted` and **zero** whose `explanation` contains `55`. Because this env has 0 rows, the verification is trivially green here and becomes meaningful wherever generation has run.

**Operational note:** running `dna:generate-scores` / the scrub in each environment is a **deploy step**, not part of the code commit.

---

## 3. Preserving Lock-and-Leave matching quality without persisting 55+ data — the A/B decision (R-1)

The genuine drivers of lock-and-leave *demand* are the self-declared, non-protected signals already scored: **purchase purpose** (`second/seasonal/vacation` → +40) and **current status** (`snowbird/retiree/downsizing/relocating` → +30). The 55+ flag was only a **+15 proxy** that, in practice, co-occurs with those very signals (a 55+ buyer is typically also "retiree"/"downsizing" status). So the real predictive content is largely retained without age.

| | **Option A — keep influence, scrub breadcrumbs** | **Option B — remove age from the computation (recommended)** |
|---|---|---|
| value | keeps `+15` (age still embedded in the number) | value from purpose/status only |
| inputs_json | drop `age_targeted` | drop `age_targeted` |
| explanation | drop `"55+ targeted"` | drop `"55+ targeted"` |
| Quality | identical values | near-identical (age was a weak, redundant proxy; genuine drivers preserved) |
| Legal posture | age still *used* in matching; a persisted value that embeds a protected proxy | age fully decoupled from the score; 55+ handled solely by the dedicated 2B compliance gate |
| Explainability (§F5) | **broken** — value carries an unexplained `+15` not reconcilable with the (scrubbed) inputs/explanation | consistent — value fully reconciles with inputs/explanation |

**Recommendation: Option B.** It is the only option that literally removes 55+ from *value, inputs, and explanation*, keeps explanation/value reconcilable, and cleanly assigns all 55+ handling to the compliance gate we already built. Matching quality is preserved because the actual lifestyle signals remain; the discarded +15 was a proxy for signals the score already captures. Option A leaves a protected attribute inside the persisted number and creates an explainability mismatch, so it is not recommended.

---

## 4. Tests needed

**Update existing:**
- `tests/Unit/Dna/ScalarScoreBatchTest::test_lock_and_leave_demand_from_seasonal_intent` — currently asserts `value=100`, `completeness=100` with `age_targeted=true` (20+40+30+**15**). Update to Option-B expectations: `value=90`, `completeness=100` (rebalanced), and add assertions that `explanation` has no `"55+"` and `inputs` has no `age_targeted`.

**Add (unit, `LockAndLeaveScoreService`):**
- **Age-invariance (core compliance assertion):** two demand listings identical in `purchase_purpose` + `current_status` but differing in `demand.age_targeted` (`true` / `false` / absent) produce **identical** `value`, `data_completeness`, `explanation`, and `inputs`.
- `explanation` never contains `55`, `55+`, or `age`.
- `inputs` keys are exactly `{current_status, purchase_purpose}`.
- `version === 'LOCK_AND_LEAVE_V2'`.
- completeness weights: both present → 100; only purpose → 55; only status → 45 (per the chosen R-2 split); neither → null/0.

**Add (persistence / generation, feature):** with generation enabled in-test, generate for a 55+ `buyer_agent`/`tenant_agent` offer-listing and assert the persisted `DnaScore` (`lock_and_leave`,`demand`) row has `inputs_json` without `age_targeted`, `explanation` without `55`, and `version='LOCK_AND_LEAVE_V2'` — proving the persisted artifact is clean end-to-end.

**Add (optional forward guard):** a compliance invariant test that scans every configured scalar score service's output (`explanation` + `inputs`) for protected-class tokens (`55`, `age`, etc.) and fails if any appear — prevents re-introduction in future scores.

**If a scrub command is added (R-3a):** idempotency + read-safety test (already-clean rows unchanged; V1 leaked rows scrubbed/removed; safe to run twice).

---

## 5. One isolated commit?

**Yes — one isolated commit.** The service edit + version bump + updated/added tests (+ optional straggler scrub command) are one cohesive generation-side remediation, independent of the read-only consumption slices. Suggested tag: `§MatchingV2 C5` (or a `dna-generation`-scoped message). The **data backfill execution** (`dna:generate-scores` / scrub per environment) is an operational deploy step performed after merge, not part of the commit. If the owner prefers the scrub command reviewed on its own, it can be split into a second commit — but a single commit is cleanest and my recommendation.

---

## 6. Owner decisions

| ID | Decision | Options | Recommendation |
|---|---|---|---|
| **R-1** | Age influence on the score. | A) keep `+15`, scrub only inputs/explanation. B) remove age from the computation entirely. | **B** — fully decouples the protected attribute; keeps value/explanation reconcilable; quality preserved via the real drivers. |
| **R-2** | data_completeness rebalance after dropping the age dimension. | (i) rebalance to sum 100 (e.g. purpose 55 / status 45). (ii) keep 40/35 (max 75). | **(i)** — preserves "100 = fully known" and F4 parity with the property side. |
| **R-3** | Existing-row cleanup mechanism. | regeneration-only · regeneration + straggler scrub command · data migration. | **Regeneration + optional straggler scrub command**; no schema migration. |
| **R-4** | Canonical `demand.age_targeted` mapping in `ByoListingAdapter`. | leave · remove. | **Leave** — in-memory only, never persisted after this change, still a valid canonical field. |
| **R-5** | Version bump `V1 → V2`. | yes · no. | **Yes** — provenance + staleness detection for cleanup/verification. |

---

## 7. Out of scope

- Any change to `scoreProperty()` or other scalar scores (pet, waterfront, location bridge).
- Removing/altering the `leasing_55_plus` meta or the 2B compliance gate (the legal enforcement path — unchanged).
- UI/API surfacing of explanations/inputs (this remediation is a *precondition* for that, separately scoped).
- Enabling generation or Matching V2 in production.
- Schema/migration changes.
- Broader Fair-Housing audit of other scores beyond the optional forward-guard test.

---

## 8. Summary of the ask

Approve **R-1 (Option B)** and **R-2…R-5**. On approval this ships as one isolated generation-side commit: the `scoreDemand()` edit (drop age from value/inputs/explanation, rebalance completeness), the `V2` version bump, the updated + new tests in §4, and (optionally) a straggler scrub command — with the data backfill run as a post-merge deploy step. **No code until approved.**
