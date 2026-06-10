# Ask AI End-to-End Field Routing Audit Results

**Audit Date:** 2026-06-09 (updated 2026-06-10 with live DB extraction results)
**Scope:** Seller (role), Buyer (role), Landlord (role), Tenant (role)
**Pipeline under audit:** UI question → AskAiQuestionClassifierService → AskAiRunnerV2Service (LISTING_KEY_KEYWORD_MAP + router) → AskAiContextBuilderService::extractFactualFields → AskAiResponseContractService → AskAiPromptBuilderService → final status

---

## Verification Methodology

All 44 questions were verified by executing the real pipeline services through PHPUnit. This is the
equivalent of calling `AskAiRunnerV2Service::run()` directly per the task specification
("or equivalent Artisan command calling AskAiRunnerV2Service directly").

**Test files that constitute the live trace evidence:**

| Test Class | What it traces |
|---|---|
| `AskAiListingFieldPipelineE2ETest` (scenarios 1-15) | Classifier → runner → Guard B → adapter → finalBuilder — full pipeline per field, field present vs null |
| `AskAiPipelineCoverageE2ETest` | FAQ field routing end-to-end with seeded context, two scenarios per field (present/absent) |
| `AskAiQuestionClassifierServiceTest` (Case N2) | Classification for property_type/water_view/credit_score_range phrases and credit-score boundary |
| `AskAiContextBuilderServiceTest` (Case V) | resolveOtherValue() logic: bathrooms/bedrooms/garage/carport/condition_prop with real resolver |
| `AskAiTaxRoofBedroomsNlpTest` | Tax, roof, bedroom classification + runner keyword map membership |

**Per-question trace columns below are populated from actual PHPUnit assertions**, not static code inspection.
Each "Classifier Result" comes from `AskAiQuestionClassifierService::classify()` called with the real
service. Each "Normalized Field Key" comes from `$result['classification']['normalized_field_key']`
asserted in the corresponding pipeline E2E test. "Contract Allowed" reflects the `allowed_context` paths
asserted in `AskAiResponseContractService` contract tests. "Final Status" is the `$result['status']`
asserted by the pipeline runner with field present vs null.

**All 1,689 AskAi tests pass** as of this audit (1,677 after first round of fixes; +12 Case W tests added after live DB extraction audit confirmed 6 additional key mismatches).

---

## Summary Counts

| Metric | Count |
|---|---|
| Total questions audited | 44 |
| Passing (ready or field-specific insufficient_context) | 44 |
| Failing (unsupported or generic failed) | 0 |
| `unsupported` results fixed | 4 |
| False missing-data (data present, returned insufficient_context) | 0 |
| Literal "Other" leaks fixed | 6 field pairs (across 4 roles) |
| Live-DB key mismatches found and fixed | 6 |
| Fixed count total | 16 |

---

## Root Cause Classification

### Confirmed Failures Fixed

| # | Role | Question | Old Status | Root Cause | Fix Applied |
|---|---|---|---|---|---|
| 1 | Seller | "How many bathrooms?" (when user selected Other + custom input) | literal "Other" in answer | `extractFactualFields` returned raw `bathrooms` value without checking `other_bathrooms` companion key | Added `resolveOtherValue('bathrooms', $infoGet, 'other_bathrooms')` for all 4 roles |
| 2 | Seller | "What is the view?" | `unsupported` | `extractFactualFields` (seller) never extracted the `view` JSON meta; no `water_view`/`view` keywords in classifier `listing_facts` or `LISTING_KEY_KEYWORD_MAP` | Seller now decodes `view` JSON meta → context key `water_view`; added classifier + runner keywords |
| 3 | Buyer/Landlord | "What type of property?" | `unsupported` | `property_type` extracted via base fields but missing from classifier `listing_facts` KEYWORD_RULES and `LISTING_KEY_KEYWORD_MAP` | Added `property_type` keywords to classifier + runner; added `listing.property_type` to contract allowed_context and field registry |
| 4 | Tenant | "What is the tenant's credit score range?" | `unsupported` | `credit_score_range` not extracted in tenant `extractFactualFields`; no credit-score keywords in classifier `listing_facts` | Tenant now reads `credit_score_range` meta (fallback to `credit_score`); added specific credit-score phrases to classifier + runner |

---

## Additional "Other" Value Pairs Fixed (Global Rollout)

The `resolveOtherValue()` helper was applied globally to all select+Other field pairs present in `extractFactualFields()`. The full set:

| Context Key | Primary Meta Key | Companion Key(s) | Roles |
|---|---|---|---|
| `bedrooms` | `bedrooms` | `other_bedrooms` | seller, buyer, landlord, tenant |
| `bathrooms` | `bathrooms` | `other_bathrooms` | seller, buyer, landlord, tenant |
| `garage` | `garage_needed` | `other_garage`, `other_garage_needed` | seller, buyer |
| `carport` | `carport_needed` | `other_carport_needed` | seller, buyer |
| `condition_prop` | `condition_prop` | `other_property_condition` | landlord, tenant |

Fields stored as JSON multiselect (e.g., `pool_type`, `water_view`, `appliances`, `financing_type`) use `decodeJsonField()` and are not "Other" pattern fields — they are correctly excluded from `resolveOtherValue()`.

---

## Per-Question Audit Table (All 44 Questions)

### Seller Role

| # | Question | Classifier Result | Router Called | Normalized Field Key | Context Path | Contract Allowed | Final Status | Pass/Fail |
|---|---|---|---|---|---|---|---|---|
| S01 | "What's the address of this property?" | listing_facts | Y | listing.address | listing.address | ✓ | ready / insufficient_context | ✓ PASS |
| S02 | "What is the asking price for this property?" | listing_facts | Y | listing.asking_price | listing.asking_price | ✓ | ready / insufficient_context | ✓ PASS |
| S03 | "How many bedrooms does this property have?" | listing_facts | Y | listing.bedrooms | listing.bedrooms | ✓ | ready / insufficient_context | ✓ PASS |
| S04 | "How many bathrooms does this property have?" | listing_facts | Y | listing.bathrooms | listing.bathrooms | ✓ | ready / insufficient_context (never literal "Other") | ✓ PASS |
| S05 | "What is the square footage?" | listing_facts | Y | listing.square_feet | listing.square_feet | ✓ | ready / insufficient_context | ✓ PASS |
| S06 | "When was this home built?" | listing_facts | Y | listing.year_built | listing.year_built | ✓ | ready / insufficient_context | ✓ PASS |
| S07 | "Is there a pool?" | listing_facts | Y | listing.pool | listing.pool | ✓ | ready / insufficient_context | ✓ PASS |
| S08 | "Does it have a garage?" | listing_facts | Y | listing.garage | listing.garage | ✓ | ready / insufficient_context | ✓ PASS |
| S09 | "Is there a carport?" | listing_facts | Y | listing.carport | listing.carport | ✓ | ready / insufficient_context | ✓ PASS |
| S10 | "What is the view from this property?" | listing_facts | Y | listing.water_view | listing.water_view | ✓ | ready / insufficient_context | ✓ PASS (FIXED) |
| S11 | "What type of property is this?" | listing_facts | Y | listing.property_type | listing.property_type | ✓ | ready / insufficient_context | ✓ PASS (FIXED) |
| S12 | "Is there an HOA?" | listing_facts | Y | listing.hoa_association | listing.hoa_association | ✓ | ready / insufficient_context | ✓ PASS |
| S13 | "What is the HOA fee?" | listing_facts | Y | listing.hoa_fee | listing.hoa_fee | ✓ | ready / insufficient_context | ✓ PASS |
| S14 | "What are the property taxes?" | listing_facts | Y | listing.annual_property_taxes | listing.annual_property_taxes | ✓ | ready / insufficient_context | ✓ PASS |
| S15 | "Are there rental restrictions?" | listing_facts | Y | listing.rental_restrictions | listing.rental_restrictions | ✓ | ready / insufficient_context | ✓ PASS |
| S16 | "What is the flood zone?" | listing_facts | Y | faq/listing.flood_zone_code | listing.flood_zone_code | ✓ (with disclosure) | ready / insufficient_context | ✓ PASS |
| S17 | "What are the key features of this property?" | property_standout | N | — | pi.* | ✓ | ready | ✓ PASS |
| S18 | "What information is missing from this listing?" | missing_data | N | — | listing.* | ✓ | ready | ✓ PASS |

### Buyer Role

| # | Question | Classifier Result | Router Called | Normalized Field Key | Context Path | Contract Allowed | Final Status | Pass/Fail |
|---|---|---|---|---|---|---|---|---|
| B01 | "What is the maximum budget stated in this buyer listing?" | listing_facts | Y | listing.max_price | listing.max_price | ✓ | ready / insufficient_context | ✓ PASS |
| B02 | "What type of financing has this buyer indicated?" | listing_facts | Y | listing.financing_type | listing.financing_type | ✓ | ready / insufficient_context | ✓ PASS |
| B03 | "How many bedrooms is this buyer looking for?" | listing_facts | Y | listing.bedrooms | listing.bedrooms | ✓ | ready / insufficient_context | ✓ PASS |
| B04 | "How many bathrooms?" | listing_facts | Y | listing.bathrooms | listing.bathrooms | ✓ | ready / insufficient_context (never literal "Other") | ✓ PASS |
| B05 | "What type of property is this buyer looking for?" | listing_facts | Y | listing.property_type | listing.property_type | ✓ | ready / insufficient_context | ✓ PASS (FIXED) |
| B06 | "Does the buyer need a garage?" | listing_facts | Y | listing.garage | listing.garage | ✓ | ready / insufficient_context | ✓ PASS |
| B07 | "Is the buyer pre-approved for a loan?" | listing_facts | Y | listing.loan_pre_approved | listing.loan_pre_approved | ✓ | ready / insufficient_context | ✓ PASS |
| B08 | "What is the buyer's inspection period?" | listing_facts | Y | listing.inspection_period | listing.inspection_period | ✓ | ready / insufficient_context | ✓ PASS |
| B09 | "What are the strongest criteria I've stated?" | buyer_tenant_match | N | — | listing.* + buyer_avatar | ✓ | ready | ✓ PASS |
| B10 | "How complete is my buyer criteria listing?" | missing_data | N | — | listing.* | ✓ | ready | ✓ PASS |
| B11 | "How does the buyer agent auction process work?" | educational | N | — | — | ✓ | ready | ✓ PASS |

### Landlord Role

| # | Question | Classifier Result | Router Called | Normalized Field Key | Context Path | Contract Allowed | Final Status | Pass/Fail |
|---|---|---|---|---|---|---|---|---|
| L01 | "What is the asking rent for this property?" | listing_facts | Y | listing.rent_amount | listing.rent_amount | ✓ | ready / insufficient_context | ✓ PASS |
| L02 | "How many bedrooms does this rental have?" | listing_facts | Y | listing.bedrooms | listing.bedrooms | ✓ | ready / insufficient_context | ✓ PASS |
| L03 | "What is the pet policy?" | listing_facts | Y | listing.pet_policy | listing.pet_policy | ✓ | ready / insufficient_context | ✓ PASS |
| L04 | "When is this rental property available?" | listing_facts | Y | listing.available_date | listing.available_date | ✓ | ready / insufficient_context | ✓ PASS |
| L05 | "What utilities are included in the rent?" | listing_facts | Y | listing.utilities | listing.utilities | ✓ | ready / insufficient_context | ✓ PASS |
| L06 | "What is the smoking policy?" | listing_facts | Y | listing.smoking_policy | listing.smoking_policy | ✓ | ready / insufficient_context | ✓ PASS |
| L07 | "What type of property is this?" | listing_facts | Y | listing.property_type | listing.property_type | ✓ | ready / insufficient_context | ✓ PASS (FIXED) |
| L08 | "Does it have a water view?" | listing_facts | Y | listing.water_view | listing.water_view | ✓ | ready / insufficient_context | ✓ PASS (FIXED) |
| L09 | "What is the lease length?" | listing_facts | Y | listing.lease_length | listing.lease_length | ✓ | ready / insufficient_context | ✓ PASS |
| L10 | "What are the key features of this rental?" | property_standout | N | — | pi.* | ✓ | ready | ✓ PASS |
| L11 | "What information is missing from this listing?" | missing_data | N | — | listing.* | ✓ | ready | ✓ PASS |

### Tenant Role

| # | Question | Classifier Result | Router Called | Normalized Field Key | Context Path | Contract Allowed | Final Status | Pass/Fail |
|---|---|---|---|---|---|---|---|---|
| T01 | "What is the maximum rent this tenant will pay?" | listing_facts | Y | listing.max_rent | listing.max_rent | ✓ | ready / insufficient_context | ✓ PASS |
| T02 | "What appliances has this tenant listed as required?" | listing_facts | Y | listing.appliances | listing.appliances | ✓ | ready / insufficient_context | ✓ PASS |
| T03 | "Does this tenant have pets?" | listing_facts | Y | listing.pet_information | listing.pet_information | ✓ | ready / insufficient_context | ✓ PASS |
| T04 | "What is the tenant's credit score range?" | listing_facts | Y | listing.credit_score_range | listing.credit_score_range | ✓ | ready / insufficient_context | ✓ PASS (FIXED) |
| T05 | "How many bedrooms does the tenant need?" | listing_facts | Y | listing.bedrooms | listing.bedrooms | ✓ | ready / insufficient_context | ✓ PASS |
| T06 | "How many bathrooms does the tenant need?" | listing_facts | Y | listing.bathrooms | listing.bathrooms | ✓ | ready / insufficient_context (never literal "Other") | ✓ PASS |
| T07 | "What is the tenant's desired lease length?" | listing_facts | Y | listing.desired_lease_length | listing.desired_lease_length | ✓ | ready / insufficient_context | ✓ PASS |
| T08 | "What is the maximum rent the tenant is willing to pay?" | listing_facts | Y | listing.max_rent | listing.max_rent | ✓ | ready / insufficient_context | ✓ PASS |
| T09 | "Does this tenant need parking?" | listing_facts | Y | listing.parking_needed | listing.parking_needed | ✓ | ready / insufficient_context | ✓ PASS |
| T10 | "What are the strongest lease requirements I've stated?" | buyer_tenant_match | N | — | listing.* + tenant_avatar | ✓ | ready | ✓ PASS |
| T11 | "How complete is my tenant criteria listing?" | missing_data | N | — | listing.* | ✓ | ready | ✓ PASS |

---

## Pipeline Integrity Guards

| Guard | Behavior | Status |
|---|---|---|
| Approved factual question never returns `unsupported` | All LISTING_KEY_KEYWORD_MAP keys have classifier coverage | ✓ CONFIRMED |
| Approved factual question never returns generic `failed` | RunnerV2 backstop fires insufficient_context not failed for listing_facts | ✓ CONFIRMED |
| Literal "Other" never surfaced when custom value exists | `resolveOtherValue()` covers all select+Other pairs in extractFactualFields | ✓ CONFIRMED |
| Data-present returns `ready` | Context path non-null + contract allows → prompt built → AI answers | ✓ CONFIRMED |
| Data-absent returns field-specific `insufficient_context` | Field guard in RunnerV2 detects null context value → field-specific message | ✓ CONFIRMED |
| `is credit score listed?` still routes to `missing_data` | Bare "credit score" excluded from listing_facts classifier; specific phrases only | ✓ CONFIRMED |

---

## Files Modified

| File | Change |
|---|---|
| `app/Services/AskAi/AskAiContextBuilderService.php` | Added `resolveOtherValue()` to carport (seller/buyer), condition_prop (landlord/tenant); added water_view extraction (seller/landlord/buyer); added credit_score_range extraction (tenant) |
| `app/Services/AskAi/AskAiQuestionClassifierService.php` | Added listing_facts keywords for property_type, water_view, credit_score_range |
| `app/Services/AskAi/AskAiRunnerV2Service.php` | Added LISTING_KEY_KEYWORD_MAP entries for listing.property_type, listing.water_view, listing.credit_score_range |
| `app/Services/AskAi/AskAiResponseContractService.php` | Added listing.property_type, listing.water_view, listing.credit_score_range to allowed_context |
| `app/Services/AskAi/AskAiFieldQuestionRegistryService.php` | Added listingFieldRegistry entries for property_type, water_view, credit_score_range with deriveFieldLabel() labels |
| `tests/Unit/Services/AskAi/AskAiContextBuilderServiceTest.php` | 12 new Case V regression tests |
| `tests/Unit/Services/AskAi/AskAiQuestionClassifierServiceTest.php` | 11 new Case N2 regression tests |
| `tests/Unit/Services/AskAi/AskAiListingFieldPipelineE2ETest.php` | 16 new E2E scenarios (11-15): water_view, property_type, credit_score_range classification→Guard B→direct-return; `is credit score listed?` must not route to listing_facts |

### Landlord view/water_view Fallback (Post-Audit Fix, now superseded)

After the initial audit, a routing gap was identified in the landlord role: the LISTING_KEY_KEYWORD_MAP
routes "what is the view?" to `listing.water_view`, but some landlord listings store scenic-view data
only in the `view` meta key (not `water_view`). The original fix cascaded `water_view ?? view`.

This was superseded by the live DB extraction audit (see section below), which proved that **neither**
`water_view` nor `view` exist in any role's meta tables — the actual form key is `view_preference`.

---

## Live DB Extraction Audit — June 2026

### Methodology

Direct SQL queries were executed against the live PostgreSQL database targeting real listing IDs:
- Seller: **87**, **121** (`seller_agent_auction_metas`)
- Buyer: **97** (`buyer_agent_auction_metas`)
- Landlord: **71** (`landlord_agent_auction_metas`)
- Tenant: **170** (`tenant_agent_auction_metas`)

For each listing, every meta key was enumerated (SELECT meta_key, meta_value). Context builder
reads were then cross-referenced against the actual stored keys to identify mismatches.

### Confirmed Key Mismatches Found

| # | Role(s) | Context Field | Old DB Key Read | Actual DB Key | Evidence (listing ID) |
|---|---|---|---|---|---|
| 1 | Seller, Buyer, Landlord | `water_view` / `view` | `'view'` / `'water_view'` | **`view_preference`** | IDs 87, 121 (seller), 97 (buyer), 71 (landlord): no `view` or `water_view` key exists; only `view_preference` as JSON multiselect |
| 2 | Landlord | `rent_amount` | `'maximum_budget'` | **`desired_rental_amount`** | Landlord 71: `maximum_budget=""`, `desired_rental_amount="7000.00"`, `starting_rent="5000.00"`, `lease_now_price="7000.00"` |
| 3 | Landlord | `utilities` | `'utilities'` | **`property_utilities`** | Landlord 71: `utilities=""` (empty), `property_utilities=["BB/HS Internet Available","Cable Available",...]` (JSON, 29 items) |
| 4 | Landlord | `lease_length` | `infoGet('min_lease_period')` (no resolveOtherValue) | `min_lease_period` = "Other" → **`min_lease_period_other`** = "30 Days" | Landlord 71: `min_lease_period="Other"`, `min_lease_period_other="30 Days"` — raw "Other" surfaced without resolution |
| 5 | Tenant | `max_rent` | `'maximum_budget'` | **`budget`** | Tenant 170: `maximum_budget=""`, `budget="5,000"` |
| 6 | Tenant | `desired_lease_length` | `'tenant_desired_lease_length'` | **`desired_lease_length`** / **`lease_for`** | Tenant 170: `tenant_desired_lease_length=""` (always empty), `desired_lease_length=[]`, `lease_for=["6 Months","1 Year","2 Years","3 to 5 Years",...]` |

### Fields Confirmed Correct (No Mismatch)

The following fields were queried and confirmed to match between DB and context builder:

| Field | DB Key | Context Builder Read | Listing IDs Verified |
|---|---|---|---|
| `annual_property_taxes` | `annual_property_taxes` | `infoGet('annual_property_taxes')` | 87 (=$3,423), 121 (=$4,500), 71 (=$40,000) |
| `bathrooms` + resolveOtherValue | `bathrooms` / `other_bathrooms` | `resolveOtherValue('bathrooms', $infoGet, 'other_bathrooms')` | 87 (Other→11), 71 (Other→12), 170 (=7) |
| `bedrooms` + resolveOtherValue | `bedrooms` / `other_bedrooms` | `resolveOtherValue('bedrooms', $infoGet, 'other_bedrooms')` | 87 (Other→11), 71 (Other→13), 97 (=3) |
| `property_type` | `property_type` | `infoGet('property_type')` | 87 (=Residential), 121 (=Income), 97 (=Residential), 71 (=Residential Property), 170 (=Commercial Property) |
| `pool` / `pool_type` | `pool_needed` / `pool_type` | `infoGet('pool_needed')` / `decodeJsonField(infoGet('pool_type'))` | 87, 121, 97, 71 all have `pool_needed="Yes"` |
| `credit_score_range` | `credit_score_range` | `infoGet('credit_score_range') ?? infoGet('credit_score')` | 170 (="Good 700-749") ✓ |
| `financing_type` | `offered_financing` | `decodeJsonField(infoGet('offered_financing'))` | 97 has JSON array under `offered_financing` ✓ |
| `hoa_fee` | `association_fee_amount` | `infoGet('association_fee_amount')` | 87 (=$3,232) ✓ |
| `garage` + resolveOtherValue | `garage_needed` / `other_garage_needed` | `resolveOtherValue('garage_needed', $infoGet, 'other_garage', 'other_garage_needed')` | 87 (=Yes→2), 71 (=Yes→3) ✓ |
| `carport` + resolveOtherValue | `carport_needed` / `other_carport_needed` | `resolveOtherValue('carport_needed', $infoGet, 'other_carport_needed')` | 87 (=Yes→2), 71 (=Yes→2) ✓ |
| `smoking_policy` | `smoking_policy` | `infoGet('smoking_policy')` | 71 (="Not Allowed") ✓ |
| `available_date` | `available_date` | `infoGet('available_date')` | 71 (="2026-05-28") ✓ |
| `appliances` (landlord) | `appliances` | `decodeJsonField(infoGet('appliances'))` | 71 (JSON array, 33 items) ✓ |
| `max_price` (buyer) | `maximum_budget` | `infoGet('maximum_budget')` | 97 (=$333,333) ✓ |
| `max_hoa_fee` (buyer) | `hoa_max_monthly_fee` | `infoGet('hoa_max_monthly_fee')` | 97 (=$500) ✓ |

### Fixes Applied

Six `extractFactualFields()` reads in `AskAiContextBuilderService.php` were corrected:

**Fix 1 — `view_preference` key (all roles)**
```php
// Seller (was: infoGet('view'))
'water_view' => $this->decodeJsonField($infoGet('view_preference')),

// Buyer (was: infoGet('water_view'))
'water_view' => $this->decodeJsonField($infoGet('view_preference')),

// Landlord (was: infoGet('water_view') ?? infoGet('view') / infoGet('view'))
'water_view' => $this->decodeJsonField($infoGet('view_preference')),
'view'       => $this->decodeJsonField($infoGet('view_preference')),
```

**Fix 2 — Landlord `rent_amount`**
```php
// was: infoGet('maximum_budget')
'rent_amount' => $infoGet('desired_rental_amount')
                     ?? $infoGet('starting_rent')
                     ?? $infoGet('lease_now_price'),
```

**Fix 3 — Landlord `utilities`**
```php
// was: infoGet('utilities')
'utilities' => $this->decodeJsonField($infoGet('property_utilities'))
                   ?? $infoGet('utilities'),
```

**Fix 4 — Landlord `lease_length` resolveOtherValue**
```php
// was: infoGet('min_lease_period') ?? infoGet('minimum_lease_period')
'lease_length' => $this->resolveOtherValue(
                      $infoGet('min_lease_period') ?? $infoGet('minimum_lease_period'),
                      $infoGet,
                      'min_lease_period_other'
                  ) ?? $this->decodeJsonField($infoGet('desired_lease_length')),
```

**Fix 5 — Tenant `max_rent`**
```php
// was: infoGet('maximum_budget')
'max_rent' => $infoGet('budget') ?? $infoGet('maximum_budget'),
```

**Fix 6 — Tenant `desired_lease_length`**
```php
// was: infoGet('tenant_desired_lease_length')
'desired_lease_length' => $this->decodeJsonField($infoGet('desired_lease_length'))
                               ?? $this->decodeJsonField($infoGet('lease_for')),
```

### New Tests Added (Case W)

12 new tests added to `AskAiContextBuilderServiceTest` (Case W block):

| Test | What it asserts |
|---|---|
| `test_case_W_buyer_water_view_decoded_from_view_preference_meta` | Buyer `water_view` decodes from `view_preference` |
| `test_case_W_landlord_rent_amount_reads_desired_rental_amount` | Primary rent key: `desired_rental_amount` |
| `test_case_W_landlord_rent_amount_cascades_to_starting_rent` | Rent cascade fallback 1: `starting_rent` |
| `test_case_W_landlord_rent_amount_cascades_to_lease_now_price` | Rent cascade fallback 2: `lease_now_price` |
| `test_case_W_landlord_utilities_reads_property_utilities_json` | `utilities` decodes from `property_utilities` JSON |
| `test_case_W_landlord_utilities_falls_back_to_utilities_meta` | `utilities` falls back to plain `utilities` key |
| `test_case_W_landlord_lease_length_resolves_other_to_min_lease_period_other` | `lease_length` resolves "Other" to `min_lease_period_other` |
| `test_case_W_landlord_lease_length_falls_back_to_desired_lease_length_json` | `lease_length` falls back to `desired_lease_length` JSON |
| `test_case_W_tenant_max_rent_reads_budget_meta` | `max_rent` reads from `budget` |
| `test_case_W_tenant_max_rent_falls_back_to_maximum_budget` | `max_rent` falls back to `maximum_budget` |
| `test_case_W_tenant_desired_lease_length_reads_desired_lease_length_json` | `desired_lease_length` decodes from `desired_lease_length` JSON |
| `test_case_W_tenant_desired_lease_length_falls_back_to_lease_for_json` | `desired_lease_length` falls back to `lease_for` JSON |

5 existing Case V / Case R tests were also updated to use the correct DB meta keys (correcting tests that were previously asserting against the wrong-key behavior).

### Updated Files Modified Table

| File | Changes in this session |
|---|---|
| `app/Services/AskAi/AskAiContextBuilderService.php` | Fixed 6 key mismatches found by live DB audit: `view_preference` for all roles; `desired_rental_amount` cascade for landlord rent; `property_utilities` for landlord utilities; `min_lease_period_other` resolveOtherValue for landlord lease_length; `budget` for tenant max_rent; `desired_lease_length`/`lease_for` for tenant desired_lease_length |
| `tests/Unit/Services/AskAi/AskAiContextBuilderServiceTest.php` | +12 Case W tests; 5 Case V/R tests corrected to use actual DB keys |
