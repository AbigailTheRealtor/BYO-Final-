---
name: BYA compatibility comparison — exact string equality, no normalizer
description: ByaCompatibilityComparisonService uses (string)$a === (string)$b at scoring time; consumer and agent blade option values must be byte-for-byte identical for a "same" result.
---

## Rule
`ByaCompatibilityComparisonService::resolveRelationship()` uses **case-sensitive exact string equality** for scalars and sorted-array equality for multi-select arrays. There is no normalizer rewriting strings between consumer and agent values at scoring time. `ByaNormalizationService` and `ByaAgentResponseNormalizationService` only standardize structure (trait key routing, slot shape) — they do not translate option string values.

**Why:** The comparison service was designed as a pure comparison layer. "similar" is reserved for future governance-defined similarity tables; Phase I never emits it.

**How to apply:** Any new consumer or agent compatibility form field must use identical `value=""` strings on both sides for the options that should score `same`. Display labels can differ freely — only the stored value attribute matters. Always cross-check consumer blade `value=""` against agent blade `value=""` when adding or modifying compatibility options.

## Known structural mismatches (Phase I — always `different`/`unknown`, not fixable by string alignment)

| Dimension | Root cause |
|-----------|-----------|
| `communication_style` (Landlord, Tenant) | Consumer single scalar vs. agent multi-select array — type mismatch |
| `communication_frequency` (Seller, Buyer) | Consumer raw key `communication_style` stores frequency-philosophy strings, not cadence labels |
| `advisor_expectation` (all roles) | Consumer = desired involvement level; agent = guidance style — different concepts |
| `decision_speed` (all roles) | Consumer = urgency/flexibility; agent = pace style — different concepts |
| `transaction_guidance_level` (all roles) | Consumer = their own decision style; agent = how they support decisions |
| `personality_style` (Seller) | Scalar past-experience string vs. multi-select philosophy array |

## Option value fixes applied (stored value → agent-aligned value; display label unchanged)
- Seller `preferred_contact_method`: `"Text/SMS"` → `"Text Message"`
- Seller `response_time_expectation`: `"Same Day"` → `"Same Business Day"`
- Buyer `preferred_contact_method`: `"In-Person"` → `"In-Person Meeting"`
- Landlord `preferred_contact_method` (frequency): `"Weekly Check-Ins"` → `"Weekly"`, `"Only Major Milestones"` → `"At Key Milestones"`, `"Only When I Ask"` → `"As Needed"`
- Tenant `contact_frequency`: `"Daily"` → `"Daily Updates"`, `"Every few days"` → `"Every Few Days"`, `"Only on major updates"` → `"At Key Milestones"`, `"As needed"` → `"As Needed"`

Full parity audit lives in `docs/agent-bid-field-map.md`.
