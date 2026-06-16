# Matching Engine Phase 1 — Final Implementation Report

**Date:** 2026-06-16
**Task:** Build 4 / Matching Engine Phase 1
**Status:** Complete — all new dimensions `enabled => false` (no behavioral regression)

---

## Objective

Expand all four `*BidMatchScoreHelper` classes to support five scoring dimensions instead of two, while keeping the three new dimensions switched off so that existing overall scores are unchanged in production.

---

## Dimensions Summary

| Dimension | Key in config | Weight | Enabled |
|---|---|---|---|
| Services | `services` | 35 | true |
| Terms | `terms` | 35 | true |
| Service Area | `service_area` | 15 | **false** |
| Experience | `experience` | 10 | **false** |
| Availability / Communication | `availability` | 5 | **false** |
| Compatibility | `compatibility` | 0 | **false** |

> **Total weight = 100.** Compatibility carries 0 weight (retained as a deferred dimension).
> When all three new dims are disabled, `computeWeightedOverall` reduces to `(35·s + 35·t)/70 = (s+t)/2`, which is identical to the legacy formula — **zero behavioral regression**.

---

## Files Created

### `app/Traits/AgentMatchSubScorer.php`
Shared trait mixed into all four helpers. Provides:

| Method | Purpose |
|---|---|
| `scoreServiceArea()` | City/county overlap between agent's served areas and client's requested location. Pure; no DB calls. |
| `scoreExperience()` | Years licensed (70% weight, capped at 20 yr) + transactions in last 12 months (30% weight, capped at 30 txn). |
| `scoreAvailability()` | Communication method match/mismatch (50% weight) + scheduling sub-score (evenings/weekends/active status, 50%). |
| `scoreCompatibility()` | Always returns 0 (deferred — agent-side data absent; schema reconciliation required before scoring). |
| `computeWeightedOverall()` | Config-driven weighted average over enabled dimensions. Neutral values (saScore=50, expScore=0, availScore=80) are applied when new dimensions are disabled, ensuring they have zero effect on overall. |
| Private location helpers | `resolveClientCities()`, `resolveClientCounties()`, `resolveAgentCities()`, `resolveAgentCounties()`, `computeOverlap()` |

### `app/Services/AgentMatchExplanationBuilder.php`
Pure transformation service. Takes a raw `calculate()` result + optional `$agentProfileData` and returns:
```php
[
    'label'   => '87% Match',
    'reasons' => [
        'Services: most requested services covered (additional services also offered)',
        'Terms: compensation terms largely aligned (some terms differ)',
        'Experience: 12 years as a licensed agent; active in the past year with recent closings',
        'Availability: actively taking new clients; available evenings; prefers Email',
        'Serves: Tampa, St. Pete and Hillsborough county',
    ],
]
```
No DB calls, no side effects. Qualitative language only — no raw fractions or counts are exposed.
Reason lines for Experience, Availability, and Service Area are omitted entirely when those dimensions
are `enabled => false` in config. When `$agentProfileData` is null, those lines are also omitted.

### `tests/Unit/AgentMatchPhase1Test.php`
27 unit tests covering:
- Config weight integrity (sum = 100)
- Backward-compatibility (null profile data → same overall as legacy)
- `computeWeightedOverall` formula correctness
- `scoreServiceArea` — overlap, no-data neutral, agent-has-no-areas zero, Seller FK-only columns, resolved name keys
- `scoreExperience` — null fields, 70/30 weight split, cap enforcement, max case = 100
- `scoreAvailability` — exact match, mismatch, "Any" method, missing client method neutral
- `scoreCompatibility` always 0 on all four helpers
- `AgentMatchExplanationBuilder` — label format, service/terms/experience/availability reasons, null profile omits new reasons
- All four helpers return new Phase 1 keys (`service_area_score`, `experience_score`, `availability_score`, `compatibility_score`)

---

## Files Modified

### `app/Helpers/*BidMatchScoreHelper.php` (all four)
**Changes in each:**
1. Added `use AgentMatchSubScorer;`
2. `calculate()` signature: added `?array $agentProfileData = null` as 5th parameter
3. Compute block before `return`: calls `scoreServiceArea()`, `scoreExperience()`, `scoreAvailability()`, `scoreCompatibility()`
4. Overall formula: replaced `round(($services_percent + $terms_percent) / 2)` with `computeWeightedOverall(...)`
5. Return array: added keys `service_area_score`, `experience_score`, `availability_score`, `compatibility_score`

### `app/Services/CompetingBidsService.php`
- Added `use App\Models\AgentDefaultProfile;`
- `calculateMatchScore()`: loads the **competing** agent's profile via `AgentDefaultProfile::findForAgentWithFallback()` and passes it as 5th arg to `TenantBidMatchScoreHelper::calculate()`

### `app/Services/ScoreBreakdownService.php`
- `breakdown()`: added `?array $agentProfileData = null` as 5th parameter; passed through to the role-appropriate helper and to `AgentMatchExplanationBuilder`

### `app/Http/Livewire/{Seller,Buyer,Landlord,Tenant}AgentAuctionBid.php` (all four)
- Added `public array $agentProfileData = [];` property (reserved for future use; not populated in mount() to avoid unnecessary loading)

### `resources/views/agent_biding_listing/{seller,buyer,landlord,tenant}.blade.php` (all four)
- Before each `calculate()` call: loads auth agent's profile data
- Passes profile data as 5th arg to each `calculate()` call

---

## Backward-Compatibility Guarantee

The formula `computeWeightedOverall` reduces to `(svc + terms) / 2` when all new dimensions are disabled:

```
enabled_sum = 35 (services) + 35 (terms) = 70
numerator   = 35·svcScore + 35·termsScore
overall     = round(numerator / 70) = round((svc + terms) / 2)   ✓
```

This is mathematically identical to the previous hard-coded formula.

---

## Enabling a Dimension in the Future

To turn on any new dimension:

1. In `config/match_scoring.php`, set `'enabled' => true` for the desired key.
2. Ensure agent profiles contain the relevant `profile_data` keys (see field list below).
3. Verify overall scores with the unit tests.

**Profile data keys consumed per dimension:**

| Dimension | Keys read from `AgentDefaultProfile.profile_data` |
|---|---|
| Service Area | `cities_served`, `counties_served` |
| Experience | `year_licensed`, `transactions_last_12_months` |
| Availability | `availability_status`, `evenings_available`, `weekends_available`, `preferred_contact_method` |
| Compatibility | _(deferred — no keys yet)_ |

**Listing / baseline keys consumed per dimension:**

| Dimension | Keys read from listing/baseline data |
|---|---|
| Service Area | `cities` (JSON array), `counties` (JSON array), `city_name`, `county_name` (Seller only) |
| Availability | `client_preferred_comm_method` |

---

## Architecture Decisions

1. **Trait, not base class.** All four helpers already extend nothing; mixing in a trait avoids a potentially breaking inheritance change and preserves each helper's independence.
2. **Pure trait — no DB queries.** Seller's `city_id`/`county_id` are integer FKs. Rather than adding a DB call inside `scoreServiceArea()`, the call site is expected to enrich `$baselineData` with `city_name`/`county_name` strings when it has the resolved values. Without them, the method returns the neutral score (50).
3. **Neutral values, not zero, for missing profile data.** `saScore=50` (neutral) prevents a sparse profile from severely penalising an otherwise strong bid when the dimension eventually gets enabled.
4. **`$agentProfileData` is fully optional.** The 5th argument defaults to `null`. All legacy callers (zero arguments changed at the call site) continue to work without modification.
