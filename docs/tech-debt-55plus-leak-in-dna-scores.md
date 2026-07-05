# Tech Debt — 55+ / senior-community data leaks into `dna_scores` (generation-side)

**Type:** Compliance-sensitive technical debt (Fair Housing / HOPA)
**Opened:** 2026-07-05 (per Matching V2 Slice 2B decision **OD-4**)
**Owner decision:** Do **not** fix inside Slice 2B (read-only consumption). Track and remediate generation-side separately.
**Severity:** Medium — age data persisted in a matching artifact. No consumer exposure today (Matching V2 is flag-off and backend-only), so not currently user-visible, but must be resolved before any consumer-facing surfacing of `dna_scores` explanations/inputs.

## What

`LockAndLeaveScoreService::scoreDemand()` folds a searcher's 55+ status into the persisted `dna_scores` row for `score_key='lock_and_leave', side='demand'` (buyer_agent / tenant_agent subjects). Concretely, when `demand.age_targeted === true`:

- **value:** `+15` is added to the numeric `lock_and_leave` score.
- **inputs_json:** writes `age_targeted => true`.
- **explanation:** writes the literal string `"55+ targeted"`.

File: `app/Services/Dna/Scores/LockAndLeaveScoreService.php` (`scoreDemand()`, approx. lines 122–167; `age_targeted` read ~line 126, value bump ~lines 157–160, inputs ~line 133, explanation ~line 165).

## Why it matters

Age is a proxy for familial status (a Fair Housing protected characteristic; 55+ communities are a HOPA *exemption*, not a general filter). Persisting it — especially as free-text `"55+ targeted"` in an `explanation` that a future consumer-facing feature might render — is a compliance-sensitive surface. It also couples a lifestyle score to a legal attribute.

## Why Slice 2B does NOT depend on it

Slice 2B's `SeniorCommunityComplianceGate` reads the authoritative `leasing_55_plus` meta directly (via `OnPlatformCandidateAttributeResolver`), **never** the `lock_and_leave` value / `inputs_json` / `explanation`. Removing or reworking this leak will not affect Slice 2B behavior.

## Suggested remediation (generation-side, separate change)

Options for the owning team to weigh:
1. Stop persisting `age_targeted` and the `"55+ targeted"` explanation string; keep the 55+ influence (if desired) as an opaque, non-attributable component, or drop it from `lock_and_leave` entirely and let the dedicated compliance gate own 55+.
2. If the signal is genuinely needed for lifestyle scoring, move it to a clearly-scoped, access-controlled field rather than the general `inputs_json`/`explanation`, and scrub the human-readable string.

## Acceptance

- No age/55+ token appears in `dna_scores.explanation`.
- `inputs_json` no longer carries `age_targeted` (or carries it only under an explicit, reviewed compliance policy).
- Regression test asserting the scrub.

## Related

- Scope: `docs/matching-v2-consumption-slice-2b-narrowing-compliance-scope.md` §1.4, OD-4.
- Consumer of the authoritative source (not the leak): `app/Services/Dna/Relevance/Narrowers/SeniorCommunityComplianceGate.php`.
