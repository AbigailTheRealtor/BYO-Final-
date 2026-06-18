---
name: Ask AI Truth Source Contract
description: CANONICAL_SOURCE_MAP, conflictDetect(), _sources, SYNTHESIS_REQUIRED_KEYS, synthesis gate (unconditional), content-level contract coercion, and end-to-end golden QA runner tests.
---

# Ask AI Truth Source Contract

## CANONICAL_SOURCE_MAP
`AskAiContextBuilderService::CANONICAL_SOURCE_MAP` is a `public const` array documenting the authoritative EAV/native source for every Ask AI context key, per role.

- `'native:column'` = native DB column; `'meta_key'` = EAV; `['k1','k2']` = cascade.
- Landlord utilities is `['utilities','property_utilities']` — UI-view key first for conflict detection; context builder reads `property_utilities` first.

## _sources key
`buildForListing()` returns `'_sources' => CANONICAL_SOURCE_MAP[$canonical] ?? []` at the **top level** of the result (not inside `$ctx['listing']`). Accessing it: `$ctx['_sources']['asking_price']`.

## conflictDetect()
`AskAiContextBuilderService::conflictDetect(string $canonicalKey, mixed $contextValue, mixed $uiValue): array`
- Normalises both sides: lowercase trim.
- Treats `'[]'` and `'{}'` as empty string (EAV stores empty JSON arrays; decodeJsonField() returns null).
- Both-empty → `conflict=false`.
- Returns `['conflict'=>bool, 'context_value', 'ui_value', 'canonical_key']`.

## SYNTHESIS_REQUIRED_KEYS
Private const in `AskAiRunnerV2Service`. Covers three categories:
1. **Paired/flag fields**: `listing.seller_credit_offered`, `listing.seller_credit_amount`
2. **List-membership check fields**: `listing.lease_terms`, `listing.terms_of_lease`
3. **JSON/comma-separated array fields**: `listing.interior_features`, `listing.appliances`, `listing.roof_type`, `listing.exterior_construction`, `listing.heating_and_fuel`, `listing.heating_fuel`, `listing.air_conditioning`, `listing.sale_provision`, `listing.offered_financing`, `listing.hoa_fee_includes`, `listing.utilities`
4. **Policy fields**: `listing.pet_policy`, `listing.rental_restrictions`, `listing.rental_restrictions_description`

Sets `$trace['synthesis_required'] = true` before Guard A/B. Does NOT change Guard B behaviour — only attaches the trace flag.

**Scalar-safe fields must NOT appear here** (asking_price, year_built, sqft, rent_amount, etc.) — their raw values are meaningful answers.

## Synthesis Gate (listing.* direct-return fallback) — UNCONDITIONAL
In the runner's listing.* direct-return fallback: after the quality-rewrite attempt, if `$normalizedFieldKey` is in `SYNTHESIS_REQUIRED_KEYS`, the gate fires **unconditionally** — regardless of whether `isResponseDegraded()` returns true or false. Sets `$trace['synthesis_gate_fired'] = true` and returns `insufficient_context` (outcome_category: `synthesis_gate_insufficient_context`).

**Why unconditional:** A quality-rewrite may add terminal punctuation, making the raw value appear "non-degraded". But the result is still a raw data echo (e.g. "No pets allowed." is still raw policy data, not a synthesized answer). Pairing the gate with `isResponseDegraded()` let these through — the new gate blocks ALL synthesis-required field echoes when OpenAI fails.

**Key routing constraint:** "will landlord accept a 4 month lease" classifies as **unsupported** (not listing_facts) — it never reaches detectListingFieldKey in the full runner. End-to-end synthesis gate tests should use questions that classify as listing_facts, e.g. "what appliances are included" → listing.appliances.

## Contract Coercion: coerceToContractStatus() — TWO LAYERS
`AskAiFinalResponseBuilderService::coerceToContractStatus(array $finalResponse): array`

Now enforces **two layers**:
1. **Content-level** (checked first): `status='ready'` + `isResponseDegraded(answer)` → coerced to `insufficient_context`. Sets `_pre_coercion_reason='degraded_answer_text'`. Belt-and-suspenders guard for any 'ready' response whose answer is degraded.
2. **Status-level**: non-contract statuses (`failed`, `unsupported`, unknown) → `insufficient_context`. Sets `_pre_coercion_status` for diagnostics.

Contract statuses: `ready`, `insufficient_context`, `blocked`.

## contractFormOf / assertContractForm
`AskAiFinalResponseBuilderService::contractFormOf(array $finalResponse, bool $synthesisHint): string`
Returns one of: `'direct_fact'` | `'synthesis'` | `'insufficient_context'` | `'refusal'`.

`AskAiFinalResponseBuilderService::assertContractForm(array $response, string $expected, bool $synthesisHint): void`
Throws `\LogicException` on mismatch — for use in golden QA tests.

## Lease-term routing order
`listing.lease_terms` entry in `LISTING_KEY_KEYWORD_MAP` is evaluated **before** `listing.lease_length`. "month lease" and acceptance phrases live in `listing.lease_terms` so "will landlord accept a 4 month lease?" routes to `terms_of_lease`, not `min_lease_period` (via reflection/detectListingFieldKey). Regression guard: "what lease lengths are available" remains in `listing.lease_length`.

**Why:** Without this ordering, lease-acceptance questions return min_lease_period instead of the actual accepted term list.

## Knowledge search answer_source value
`AskAiKnowledgeSearchService` returns `answer_source='database'` (not `'knowledge_snapshot'`) for all DB-backed hits. Tests asserting source metadata must use `'database'`.

## Golden QA test
`tests/Feature/AskAi/AskAiGoldenQaSuiteTest.php` — 70 tests across 20 sections. Includes:
- §1-6: structural assertions (CANONICAL_SOURCE_MAP, _sources, conflictDetect, contractFormOf)
- §7-9: routing regression + real DB spot-checks for seller 121 and landlord 71
- §10-16: SYNTHESIS_REQUIRED_KEYS, synthesis gate, buyer/tenant context, regression scenarios, _sources
- §17: Field alignment integration harness (data-provider, normalizeEavForComparison helper)
- §18: coerceToContractStatus() full coverage
- §19: Synthesis-required key completeness
- §20: End-to-end runner tests (§20A: unconditional gate structural proof; §20B: full runner → listing.appliances synthesis gate fires; §20C: Phase 4 snapshot database_hit; §20D: content-level coercion of degraded ready answer)
