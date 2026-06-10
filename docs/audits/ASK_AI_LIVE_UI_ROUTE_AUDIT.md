# Ask AI — Live UI Route Audit
**Date:** June 2026
**Scope:** All four listing roles (seller, buyer, landlord, tenant) via both API entry points
(`AskAiApiController` → `/api/ask-ai` and `AskAiListingQuestionController` → listing view pages).

---

## 1. Controller Route Inventory

| Route | Controller | Method | Auth | Notes |
|---|---|---|---|---|
| `POST /api/ask-ai` | `AskAiApiController` | `ask()` | Sanctum | Used by React/JS chips |
| `POST /listing/{type}/{id}/ask` | `AskAiListingQuestionController` | `ask()` | Web session | Used by Blade typed-input form |

Both controllers call `AskAiRunnerV2Service::run($role, $listingId, $question)` identically.
No divergence exists between chip-originated and typed-input-originated questions.

---

## 2. Pipeline Entry Points — Confirmed Unified

Both routes funnel into the same `AskAiRunnerV2Service::run()` call with the same argument
signature. The runner handles all routing, context assembly, Guard B logic, adapter calls,
and fallback logic internally.

**Conclusion:** Chip vs. typed-input parity is guaranteed at the service layer.
No controller-level divergence was found.

---

## 3. Confirmed EAV Meta Key Mismatches (June 2026 Live-DB Audit)

The following mismatches were identified by querying real listing rows (IDs 87, 97, 121, 71, 170)
and comparing actual meta keys in the DB against the keys read by `extractFactualFields()` /
`AskAiContextBuilderService::buildForListing()`.

| # | Role | Context Field | Wrong Key (was) | Correct Key (now) | Status |
|---|---|---|---|---|---|
| 1 | buyer | `financing_type` | `offered_financing` | `financing_type` (cascade `offered_financing`) | **FIXED** |
| 2 | seller | `square_feet` | `minimum_heated_square` only | cascade: `minimum_heated_square` → `heated_square_footage` → `heated_square` | **FIXED** |
| 3 | buyer | `square_feet` | `minimum_heated_square` only | same cascade as seller | **FIXED** |
| 4 | landlord | `square_feet` | `minimum_heated_square` only | same cascade | **FIXED** |
| 5 | tenant | `max_rent` (budget) | `budget` only | `budget` → `maximum_budget` cascade; context key is `max_rent`, not `rental_budget` | **pre-existing fix** |
| 6 | landlord | `utilities` | `utilities` only | `property_utilities` → `utilities` cascade | **pre-existing fix** |
| 7 | tenant | `desired_lease_length` | `tenant_desired_lease_length` | `desired_lease_length` → `lease_for` cascade | **pre-existing fix** |
| 8 | all roles | `water_view` / `view` | `water_view`, `view` | `view_preference` (JSON multiselect) | **pre-existing fix** |

---

## 4. Additional Fix: "Other" Token Leak in Multi-Select JSON Arrays

**Problem:** `AskAiContextBuilderService::decodeJsonField()` joined JSON arrays verbatim.
Arrays like `["Washer","Dryer","Other"]` produced `"Washer, Dryer, Other"` — the literal
`"Other"` token (a UI sentinel for a custom-entry field) leaked into the AI prompt context.

**Fix:** `decodeJsonField()` now strips any array element whose trimmed, lowercased value
equals `"other"` before joining. Arrays that reduce to empty return `null`.

**Affected fields:** Any JSON-multiselect meta key, including `pool_type`, `appliances`,
`property_items`, `pet_species_allowed`, `view_preference`, `financing_type`.

---

## 5. Guard B — Null-Field Short-Circuit

When a listing.* field is identified by the classifier but is null in the assembled context,
`AskAiRunnerV2Service` fires Guard B before calling the OpenAI adapter:

```
status  = 'insufficient_context'
success = false
answer  = '<Field label> has not been provided for this listing.'
```

Guard B relies on `deriveFieldLabel()` to produce a human-readable label from the canonical
`listing.*` path. Every listing.* path that appears in `LISTING_KEY_KEYWORD_MAP` has a
corresponding entry in the `deriveFieldLabel()` map. This is validated structurally by
`AskAiApprovedFieldCoverageHarnessTest`.

---

## 6. Direct-Return Fallback (OpenAI Unavailable)

When the adapter fails but the listing field is populated, the runner surfaces the raw
field value directly (`status = 'ready'`, `success = true`) rather than propagating a
generic error. This covers transient OpenAI outages without data loss.

---

## 7. Classifier Coverage Gap Fix + `listing.rental_budget` Key Removal (June 2026)

A second round of audit confirmed that the canonical listing.* keys in `LISTING_KEY_KEYWORD_MAP`
had insufficient classifier phrases, and that one key (`listing.rental_budget`) was backed
by a non-existent context field.

### 7a. Classifier phrase additions

The following phrase categories were added to the `listing_facts` rule set in
`AskAiQuestionClassifierService`:

| Category | Representative phrases added |
|---|---|
| Tenant budget | `tenant max rent`, `maximum rental budget`, `tenant's rental budget` |
| Property description | `property description`, `listing description` |
| Property condition | `condition of the rental`, `rental property condition` |
| Carport | `carport`, `is there a carport` |
| Association amenities | `association amenities`, `community association offer` |
| Lease renewal | `renewal option available`, `is lease renewal an option` |
| Rental restrictions | `rental restrictions on this property`, `property rental restriction rules` |
| Subletting | `subletting policy`, `subletting rules` |
| Parking terms | `parking terms for this rental`, `parking included in rent` |
| Buyer pre-approval | `buyer pre-approved for a loan`, `loan pre-approval status` |
| Inspection / contingencies | `inspection period`, `home inspection contingency`, `financing contingency` |
| Smoking policy | `does this unit allow smoking`, `is this a smoke-free unit`, `smoke-free unit` |
| Buy-it-now price | `buy it now price`, `fixed buy-now price` |
| Pet policy | `pet rules`, `is this pet-friendly`, `pet fee amount` |
| Lease terms | `existing lease terms on this property`, `is there a tenant currently leasing` |
| Tenant pet details | `tenant pet details`, `tenant pet type and size` |
| Tenant lease duration | `tenant preferred lease duration` |

### 7b. `listing.rental_budget` key removal

**Root cause:** `listing.rental_budget` was added to `LISTING_KEY_KEYWORD_MAP` but pointed
to `ctx['listing']['rental_budget']` which never exists. The context builder for the tenant
role stores the maximum budget under `ctx['listing']['max_rent']` (cascade:
`infoGet('budget') ?? infoGet('maximum_budget')`). With an empty `allowed_context`, OpenAI
hallucinated a "not available" error even when the DB had a populated value.

**Fix:** `listing.rental_budget` was removed from `LISTING_KEY_KEYWORD_MAP`,
`deriveFieldLabel()`, and `AskAiResponseContractService` allowed_context. Its keywords
(`tenant rental budget`, `tenant's rental budget`, `maximum rental budget`, etc.) were merged
into the pre-existing `listing.max_rent` entry. The harness data-set `rental_budget` now
tests `listing.max_rent` with those question phrases, maintaining 200 total assertions.

**Verified live:** Tenant listing 170 question "What is the tenant's rental budget?" now
correctly routes to `listing.max_rent`, finds `ctx['listing']['max_rent'] = '5,000'`,
passes it to OpenAI as allowed_context, and returns `status: ready` with the correct value.

**Result:** 49 canonical listing.* keys in `LISTING_KEY_KEYWORD_MAP` (down from 50).
All 49 now route deterministically. Validated by
`AskAiApprovedFieldCoverageHarnessTest` (200 tests, 0 failures).

---

## 8. Test Coverage for This Audit

### Regression test file (EAV key mismatches + new classifier phrases):
```
tests/Unit/Services/AskAi/AskAiLiveUiRegressionTest.php
```

### Full field coverage harness (200 tests, 50 data-sets × 4 assertions each):
```
tests/Unit/Services/AskAi/AskAiApprovedFieldCoverageHarnessTest.php
```
Note: 50 data-sets cover 49 unique canonical keys because `rental_budget_alt` tests
`listing.max_rent` with alternative question phrasing.

Run with:
```bash
./vendor/bin/phpunit --filter AskAiLiveUiRegression
./vendor/bin/phpunit tests/Unit/Services/AskAi/AskAiApprovedFieldCoverageHarnessTest.php
```

---

## 9. Live DB Trace — Final Verification (June 2026, gpt-4o live)

Validated against real listing IDs: Seller 87, Seller 121, Buyer 97, Landlord 71, Tenant 170.

| Role | ID | Question | Expected Key | Raw DB Value | Context Value | Detected Key | Status |
|---|---|---|---|---|---|---|---|
| seller | 87 | What is the property type? | property_type | Residential | Residential | listing.property_type | **ready** |
| seller | 87 | Is there a water view? | water_view | `["Beach","City",...]` | Beach, City, ... | listing.water_view | **ready** |
| seller | 87 | How many bathrooms does this home have? | bathrooms | Other→11 | 11 | listing.bathrooms | **ready** |
| seller | 87 | What is the asking price? | asking_price | 1000000.00 | 1000000.00 | listing.asking_price | **ready** |
| seller | 121 | What is the property type? | property_type | Income | Income | listing.property_type | **ready** |
| seller | 121 | Is there a water view? | water_view | `["Beach","City",...]` | Beach, City, ... | listing.water_view | **ready** |
| buyer | 97 | What is the property type? | property_type | Residential | Residential | listing.property_type | **ready** |
| buyer | 97 | Is there a water view? | water_view | `["Beach","City",...]` | Beach, City, ... | listing.water_view | **ready** |
| buyer | 97 | What financing type is the buyer using? | financing_type | `["Assumable","Cash",...]` | Assumable, Cash, ... | listing.financing_type | **ready** |
| buyer | 97 | How many bathrooms? | bathrooms | 5 | 5 | listing.bathrooms | **ready** |
| landlord | 71 | What is the property type? | property_type | Residential Property | Residential Property | listing.property_type | **ready** |
| landlord | 71 | Is there a water view? | water_view | `["Beach","City",...]` | Beach, City, ... | listing.water_view | **ready** |
| landlord | 71 | How many bathrooms does this property have? | bathrooms | Other→12 | 12 | listing.bathrooms | **ready** |
| landlord | 71 | What is the monthly rent? | rent_amount | 7000.00 | 7000.00 | listing.rent_amount | **ready** |
| landlord | 71 | What lease lengths are available? | lease_length | Other→30 Days | 30 Days | listing.lease_length | **ready** |
| tenant | 170 | What is the property type? | property_type | Commercial Property | Commercial Property | listing.property_type | **ready** |
| tenant | 170 | What is the tenant's rental budget? | max_rent | 5,000 | 5,000 | listing.max_rent | **ready** |
| tenant | 170 | What is the tenant max rent? | max_rent | 5,000 | 5,000 | listing.max_rent | **ready** |
| tenant | 170 | What is the credit score range? | credit_score_range | Good 700-749 | Good 700-749 | listing.credit_score_range | **ready** |
| tenant | 170 | What is the tenant preferred lease duration? | desired_lease_length | `["6 Months","1 Year",...]` | 6 Months, 1 Year, ... | listing.desired_lease_length | **ready** |
| tenant | 170 | How many bathrooms? | bathrooms | 7 | 7 | listing.bathrooms | **ready** |

**All 21 live trace rows: `status: ready`.** Both rental budget questions (rows 17–18) now
correctly route to `listing.max_rent` with populated context value `5,000`.

---

## 10. Browser Route Parity — Confirmed

Both entry points call identical runner signatures:

| Route | Controller | Runner call |
|---|---|---|
| `POST /ask-ai/ask` (web) | `AskAiApiController@ask` | `$this->runner->run($listingType, $listingId, $question)` |
| `POST /ask-ai/listing-question` (web) | `AskAiListingQuestionController@run` | `$this->runner->run($listingType, $listingId, $question)` |
| `POST /api/ask-ai/ask` (API, Sanctum) | `AskAiApiController@ask` | same as web route above |

The response shape differs by design:
- `AskAiApiController` maps `ready → answered` and uses key `answer_text`
- `AskAiListingQuestionController` passes through `ready` and uses key `answer`

The underlying runner result and OpenAI answer are identical.
Structural parity is pinned by `AskAiApiTest::test_web_route_calls_runner_with_same_params_as_existing_pipeline` (✓ passing).
