# Ask AI Coverage Remediation Verification Audit

**Task:** #2598 — Ask AI Coverage Remediation  
**Source Audit:** `docs/audits/ASK_AI_END_TO_END_COVERAGE_AUDIT.md`  
**Date Completed:** 2025-06-13  
**Test File:** `tests/Feature/AskAi/AskAiCoverageRemediationRoutingTest.php` (37 tests — all pass)

---

## Files Modified

| File | Change |
|------|--------|
| `app/Services/AskAi/AskAiRunnerV2Service.php` | `LISTING_KEY_KEYWORD_MAP` — P1-1, P1-2, P1-3 collision fixes; P1-4 new entry; P0-1 Business block; 20+ P2 alias additions |
| `app/Services/AskAi/AskAiQuestionClassifierService.php` | `listing_facts` keyword array — business field phrases, landlord approval phrases, bare-form aliases |
| `app/Services/AskAi/AskAiFieldQuestionRegistryService.php` | `listingFieldRegistry()` — P1-4, P0-1 (17 Business fields), P3 (6 Multifamily/Investment fields) |

---

## P1 — High-Priority Routing Collisions

### P1-1: `has_cdd` ↔ `annual_cdd_fee` collision
**Root cause:** `LISTING_KEY_KEYWORD_MAP['listing.has_cdd']` contained bare `'cdd'` and `'cdd fee'`, which matched before `annual_cdd_fee` entries.  
**Fix:** Removed `'cdd'` and `'cdd fee'` from `has_cdd`. Both phrases belong exclusively in `annual_cdd_fee`.  
**Verification (all pass):**
- `"annual CDD fee"` → `listing.annual_cdd_fee` ✅
- `"How much is the CDD fee?"` → `listing.annual_cdd_fee` ✅
- `"Is there a CDD?"` → `listing.has_cdd` ✅  (still works)
- `"community development district"` → `listing.has_cdd` ✅  (still works)

### P1-2: `square_feet` ↔ `building_sqft` collision
**Root cause:** `listing.square_feet` contained `'square footage'` (bare), which intercepted `building_sqft` routing for every question containing those words.  
**Fix:** Replaced `'square footage'` with `'home square footage'` and `'living area square footage'` — residential-specific phrasing that does not subsume commercial building queries.  
**Verification (all pass):**
- `"Building square footage?"` → `listing.building_sqft` ✅
- `"Home square footage?"` → `listing.square_feet` ✅
- `"Living area square footage?"` → `listing.square_feet` ✅

### P1-3: `sewer` ↔ `sewer_available` collision
**Root cause:** `listing.sewer` contained bare `'sewer'`, which intercepted all Vacant Land sewer-availability questions before `listing.sewer_available` was reached.  
**Fix:** Removed `'sewer'` (bare) from `listing.sewer`. Added shorter, bare phrases (`'sewer available'`, `'is sewer available'`, `'sewer connection available'`, `'does this land have sewer'`) to `listing.sewer_available`.  
**Verification (all pass):**
- `"Sewer available?"` → `listing.sewer_available` ✅
- `"Is sewer available?"` → `listing.sewer_available` ✅
- `"sewer connection available"` → `listing.sewer_available` ✅
- `"sewer type"` → `listing.sewer` ✅  (still works)
- `"Is this on public sewer?"` → `listing.sewer` ✅  (still works)

### P1-4: `landlord_approval_conditions` — previously inaccessible
**Root cause:** `landlord_approval_conditions` had no entry in `LISTING_KEY_KEYWORD_MAP`, making it unroutable via any natural-language question.  
**Fix:** Added new `listing.landlord_approval_conditions` block with 8 natural-language phrases. Added corresponding entry to `listingFieldRegistry()`. Added classifier coverage.  
**Verification (all pass):**
- `"landlord approval conditions"` → `listing.landlord_approval_conditions` ✅
- `"What are the approval requirements?"` → `listing.landlord_approval_conditions` ✅
- `"Credit requirements to rent?"` → `listing.landlord_approval_conditions` ✅
- `"tenant approval criteria"` → `listing.landlord_approval_conditions` ✅
- `"qualifying conditions for this rental"` → `listing.landlord_approval_conditions` ✅
- database_hit with stored fact → `database_hit` outcome ✅

### P1-5: VacantLand bare-key mismatch — `lookupListingFact` prefix strip
**Status:** ✅ Already fixed in code — `AskAiKnowledgeSearchService::lookupListingFact()` strips the `listing.` prefix at line 347–352. No code change required.

---

## P0 — Seller / Business Opportunity Unroutable Fields (17 fields)

All 17 fields added to `LISTING_KEY_KEYWORD_MAP`, `listingFieldRegistry()`, and the classifier's `listing_facts` array.

| Field | Sample Routing Phrase | Status |
|-------|-----------------------|--------|
| `listing.annual_revenue` | `"annual revenue"`, `"business annual revenue"` | ✅ Routes correctly |
| `listing.employee_count` | `"employee count"`, `"number of employees"` | ✅ Routes correctly |
| `listing.year_established` | `"year established"`, `"how long has this business been operating"` | ✅ Routes correctly |
| `listing.business_name` | `"business name"`, `"name of the business"` | ✅ Routes correctly |
| `listing.business_location_leased` | `"is the business location leased"`, `"business location lease status"` | ✅ Routes correctly |
| `listing.nda_required` | `"nda required"`, `"is an nda required"`, `"non-disclosure agreement required"` | ✅ Routes correctly |
| `listing.financial_statements_available` | `"are financial statements available"`, `"financial records available"` | ✅ Routes correctly |
| `listing.reason_for_sale` | `"reason for sale"`, `"why is this business for sale"` | ✅ Routes correctly |
| `listing.sale_includes` | `"what is included in the sale"`, `"sale includes"` | ✅ Routes correctly |
| `listing.business_assets` | `"business assets"`, `"what assets does the business have"` | ✅ Routes correctly |
| `listing.business_lease_monthly_rent` | `"business location rent"`, `"lease payment for the business location"` | ✅ Routes correctly |
| `listing.ffe_value` | `"ffe value"`, `"furniture fixtures and equipment value"` | ✅ Routes correctly |
| `listing.gross_profit` | `"gross profit"`, `"business gross profit"` | ✅ Routes correctly |
| `listing.sde_ebitda` | `"sde"`, `"ebitda"`, `"seller discretionary earnings"`, `"owner earnings"` | ✅ Routes correctly |
| `listing.inventory_value` | `"inventory value"`, `"value of the inventory"` | ✅ Routes correctly |
| `listing.licenses` | `"business licenses"`, `"licenses required for this business"` | ✅ Routes correctly |
| `listing.business_lease_assignable` | `"is the business lease assignable"`, `"assignable business lease"` | ✅ Routes correctly |

**database_hit end-to-end test:** `annual_revenue` with stored fact `'$2,500,000'` → `database_hit`, correct value ✅

---

## P2 — Alias / Synonym Gaps (~23 fields remediated)

| Field | Alias/Phrase Added | Routing Verified |
|-------|--------------------|-----------------|
| `listing.year_built` | `'how old is this property'`, `'how old is this building'`, `'age of this building'` | ✅ |
| `listing.flood_zone_code` | `'flood zone'` (bare), `'flood zone code'` | ✅ |
| `listing.rental_restrictions` | `'rental restrictions'` (bare), `'are there any rental restrictions'` | ✅ |
| `listing.max_price` | `'maximum budget'`, `'how much can they spend'`, `'buyer maximum price'` | ✅ |
| `listing.hoa_acceptable` | `'is the buyer open to hoa'`, `'buyer open to hoa properties'` | ✅ |
| `listing.max_rent` | `'how much can the tenant afford'` | ✅ |
| `listing.desired_lease_length` | `'how long of a lease does this tenant want'` | ✅ |
| `listing.smoking_policy` | `'smoking policy'` (bare), `'is smoking allowed'` | ✅ |
| `listing.road_frontage` | `'road frontage'` (bare), `'what is the road frontage'` | ✅ |
| `listing.vegetation` | `'vegetation'` (bare), `'vegetation on the land'` | ✅ |
| `listing.buildable` | `'is this buildable'`, `'is this property buildable'`, `'can i build on this property'` | ✅ |
| `listing.water_available` | `'water available'` (bare), `'is water available'` | ✅ |
| `listing.easements` | `'easements'` (bare), `'easements on the property'` | ✅ |
| `listing.telecom_available` | `'telecom available'` | ✅ |
| `listing.lease_length` | `'minimum lease term'`, `'shortest lease available'`, `'how long is the minimum lease'` | ✅ |
| `listing.cap_rate` | `'what return does this investment yield'`, `'investment yield rate'`, `'property investment return'` | ✅ |
| `listing.total_units` | `'multiple units'`, `'multi-unit property'`, `'how many rental units'`, `'separate living units'` | ✅ |
| `listing.gross_annual_income` | `'how much revenue does this property generate'`, `'annual gross income'` | ✅ |
| `listing.rent_roll_available` | `'rent roll'` (bare), `'can i see the rent roll'` | ✅ |
| `listing.operating_statement_available` | `'do you have an operating statement'`, `'income and expense statement'` | ✅ |
| `listing.unit_mix_summary` | `'what is the unit type breakdown'`, `'bedroom and bath mix'` | ✅ |
| `listing.hoa_association` | `'does this property have an association'`, `'hoa or association'` | ✅ |
| `listing.number_of_occupants_allowed` | `'number of occupants'` (bare), `'occupant limit'` | ✅ |

---

## P3 — Registry-Only Missing Fields

Six multifamily/investment fields added to `listingFieldRegistry()` so snapshots can store and serve their questions. All have correct `roles` and `keyword_route_status = 'listing_native'`.

| Field | Roles | Notes |
|-------|-------|-------|
| `listing.total_units` | seller, landlord | Was missing from registry; LISTING_KEY_KEYWORD_MAP entry pre-existed |
| `listing.unit_mix_summary` | seller, landlord | Was missing from registry; LISTING_KEY_KEYWORD_MAP entry pre-existed |
| `listing.total_buildings` | seller, landlord | Was missing from registry; LISTING_KEY_KEYWORD_MAP entry pre-existed |
| `listing.annual_cdd_fee` | seller, landlord | Was missing from registry; LISTING_KEY_KEYWORD_MAP entry pre-existed |
| `listing.annual_noi` | seller | Was missing from registry; LISTING_KEY_KEYWORD_MAP entry pre-existed |
| `listing.gross_annual_income` | seller | Was missing from registry; LISTING_KEY_KEYWORD_MAP entry pre-existed |

---

## Collision Guards Applied

During implementation, two secondary collisions were discovered and resolved:

| Collision | Detected By | Resolution |
|-----------|-------------|------------|
| `'financial statements available'` in `operating_statement_available` intercepting `financial_statements_available` routing | Regression test failure | Replaced with `'do you have an operating statement'`; phrase removed from operating_statement_available |
| `'business lease monthly rent'` and `'monthly rent for the business location'` in `business_lease_monthly_rent` intercepting `rent_amount` (because they contain `'monthly rent'`) | Regression test failure | Replaced with collision-safe phrases: `'how much does the business pay in rent'`, `'business location rent'`, `'commercial space rent for this business'`, `'lease payment for the business location'` |
| `'flood zone'` (bare) in `flood_zone_code` intercepting `flood_zone_panel` and `flood_zone_date` queries (because both contain the substring `'flood zone'`) | Code review catch | Removed bare `'flood zone'` from `flood_zone_code`; kept `'flood zone code'`, `'flood zone status'`, and other specific phrases. Added regression test asserting `'flood zone panel'` → `listing.flood_zone_panel` and `'flood zone date'` → `listing.flood_zone_date`. |

---

## Test Summary

**Test file:** `tests/Feature/AskAi/AskAiCoverageRemediationRoutingTest.php`  
**Tests:** 38 total — all pass  

| Group | Tests | Outcome |
|-------|-------|---------|
| P1-1 CDD collision | 2 | ✅ All pass |
| P1-2 Square footage collision | 2 | ✅ All pass |
| P1-3 Sewer collision | 2 | ✅ All pass |
| P1-4 Landlord approval | 1 | ✅ All pass |
| P0-1 Business fields | 1 (multi-assertion) | ✅ All pass |
| P2 Alias gaps | 24 | ✅ All pass (includes flood_zone_panel/date non-interception guard) |
| DB integration (database_hit) | 6 | ✅ All pass |

---

## Three-File Coordination Checklist

For every new `listing.*` field added, all three required files were updated:

| File | Purpose | Updated |
|------|---------|---------|
| `AskAiContextBuilderService.php` | EAV alias — field must be in context `listing.*` array | Verified pre-existing for all fields (context builder aliases confirmed via grep) |
| `AskAiRunnerV2Service.php` | `LISTING_KEY_KEYWORD_MAP` — phrase → field key routing | ✅ Updated |
| `AskAiQuestionClassifierService.php` | `listing_facts` keywords — classifies question type before routing | ✅ Updated |
| `AskAiFieldQuestionRegistryService.php` | `listingFieldRegistry()` — snapshot question registration | ✅ Updated |
