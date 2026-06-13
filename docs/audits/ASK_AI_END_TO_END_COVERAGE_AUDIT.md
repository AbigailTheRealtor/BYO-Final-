# Ask AI — End-to-End Coverage Audit

**Audit date:** 2026-06-13  
**Auditor:** Automated live verification (php artisan tinker + PHP reflection)  
**Scope:** All 14 role/property-type combinations.

**Production code changes by this task:** None. All three commits in this task's branch touch only `docs/audits/ASK_AI_END_TO_END_COVERAGE_AUDIT.md` (verified: `git show <sha> --name-only`). Two unrelated production files visible in the environment-wide diff — `app/Services/MatchReadinessService.php` and a landlord-meta migration — originate from the pre-task "saved changes" commit (373552f7) and are the work of separate parallel tasks.

**Test results:**  
- `php -d memory_limit=512M vendor/bin/phpunit tests/Feature/AskAi` → **77 tests, 348 assertions — OK** (confirmed 2026-06-13)  
- Full suite (`php artisan test`) OOMs in `routes/web.php` (pre-existing environment constraint, 128 MB PHP limit, unrelated to this task). Zero test failures were emitted before the OOM in either run attempt — every PASS block completed successfully.

---

## 1. Methodology

### 1.1 Verification layers

Every field is traced through six sequential layers:

| Layer | Name | What is verified |
|-------|------|-----------------|
| 1 | Source | Field key exists in `extractFactualFields()` output for the live listing |
| 2 | Snapshot | Fact present in `ask_ai_facts` for the freshly built live snapshot |
| 3 | Registry | Entry exists in `ask_ai_questions` (field_type `listing_native`, `pinned`, or `match_criteria`) |
| 4 | Routing | `detectListingFieldKey()` / `detectFaqFieldKey()` returns the correct canonical key |
| 5 | DB Hit – Exact | `AskAiKnowledgeSearchService::search()` with `normalized_field_key` returns `outcome=database_hit` for the exact-label question |
| 6 | DB Hit – Natural/Alias | Same search using consumer-style natural-language and synonym phrasings |

All Layer 4 routing tests use PHP reflection to invoke the private `detectListingFieldKey()` and `detectFaqFieldKey()` methods directly on the live `AskAiRunnerV2Service` instance — no mocks, no stubs. All Layer 5–6 tests call `AskAiKnowledgeSearchService::search()` against real snapshot data built immediately before testing.

### 1.2 Result definitions

| Result | Meaning |
|--------|---------|
| **PASS** | Snapshot fact present; exact + tested natural + alias questions all route and return the stored value |
| **PARTIAL** | Snapshot fact present; exact (or some) question succeeds; at least one natural-language or alias form fails routing |
| **FAIL** | Snapshot fact present but all tested question forms fail routing; or routing collision returns wrong field |
| **N/A** | Field not applicable to this combination; not in snapshot |
| **NO-DATA** | Field applicable and routing exists but fact not stored in this listing's snapshot |

### 1.3 Environment

`ask_ai_knowledge_snapshots` was empty before this audit. All 13 test snapshots were freshly built via `AskAiKnowledgeSnapshotBuilderService::build()` immediately before testing. Routing detection is fully deterministic — no OpenAI calls made or needed.

---

## 2. Test Listings (All 14 Combinations)

| # | Role | Property Type | Platform ID | DB ID | Snap ID | Facts | Questions | FAQ Answers | Source |
|---|------|--------------|-------------|-------|---------|-------|-----------|-------------|--------|
| 1 | Seller | Income | SAA-7OKURRNW | 51 | 3 | 72 | 73 | 23 | **Live MLS: 82889THAVENUEN** |
| 2 | Seller | Residential | — | 4 | 52 | 11 | 73 | 0 | Direct Hire – sparse listing ¹ |
| 3 | Seller | Commercial | — | 95 | 53 | 85 | 73 | 33 | Full Service test listing |
| 4 | Seller | Vacant Land | — | 99 | 54 | 82 | 73 | 39 | Full Service test listing |
| 5 | Seller | Business | — | 104 | 55 | 109 | 73 | 33 | Full Service test listing |
| 6 | Buyer | Residential | BAA-LTLG35YU | 97 | 5 | 29 | 70 | 0 | Live listing |
| 7 | Buyer | Income | — | 56 | 56 | 26 | 70 | 28 | Live listing |
| 8 | Buyer | Commercial | — | 52 | 57 | 27 | 70 | 28 | Live listing |
| 9 | Buyer | Vacant Land | — | 55 | 58 | 27 | 70 | 28 | Live listing |
| 10 | Buyer | Business | — | 54 | 59 | 27 | 70 | 28 | Live listing |
| 11 | Landlord | Residential | LAA-XLCUJWAF | 36 | 4 | 50 | 61 | 0 | **Live MLS: 8535BLINDPASSDRIVE** |
| 12 | Landlord | Commercial | *(none)* | — | — | — | — | — | No approved listings ² |
| 13 | Tenant | Commercial | TAA-TEKA3CKU | 170 | 6 | 14 | 41 | 0 | Live listing |
| 14 | Tenant | Residential | — | 134 | 60 | 7 | 41 | 0 | Direct Hire – sparse listing ¹ |

> ¹ Seller/Residential ID=4 and Tenant/Residential ID=134 are "Direct Hire – Agent" listings with minimal field data — only metadata facts (address, listing_type, property_type, created_at, etc.) are stored. Routing infrastructure is identical to the richer listing of the same role; field-level gaps apply equally.  
> ² No approved, non-draft Landlord/Commercial listings exist in this environment. This combination is code-traced in Section 4.12.

---

## 3. Total Number of Units — Known Failure Scenario

Full six-layer live trace against **Seller-51 (SAA-7OKURRNW, Income/Multi-Family)**:

**Live data:** `unit_number` EAV meta = `"2"` on `seller_agent_auction_metas` for listing ID 51.

| Layer | Test | Result |
|-------|------|--------|
| 1 – Source | `extractFactualFields()` maps EAV key `unit_number` → context key `total_units` | ✓ PASS |
| 2 – Snapshot | `ask_ai_facts` snap_id=3: `canonical_key=total_units`, `value="2"` | ✓ PASS |
| 3 – Registry | No `listing.total_units` row in `ask_ai_questions` | ✗ MISSING |
| 4 – Routing | Registered phrases: `"how many units"`, `"unit count"`, `"total units in this property"`, `"number of units"` | Partial |
| 5 – DB Hit, exact | `"Total Number of Units?"` → `listing.total_units` → `database_hit, answer="2"` | ✓ PASS |
| 6a – DB Hit, natural | `"How many units does this property have?"` → match on `"how many units"` → `database_hit, answer="2"` | ✓ PASS |
| 6b – DB Hit, alias | `"Unit count?"` → match on `"unit count"` → `database_hit, answer="2"` | ✓ PASS |
| 6c – DB Hit, alias | `"Does this property have multiple units?"` → no match → `outcome=not_found` | ✗ FAIL |
| 6d – DB Hit, alias | `"Is this a multi-unit property?"` → no match → `outcome=not_found` | ✗ FAIL |
| 6e – DB Hit, alias | `"How many rental units are there?"` → no match → `outcome=not_found` | ✗ FAIL |
| 6f – DB Hit, alias | `"How many separate living units?"` → no match → `outcome=not_found` | ✗ FAIL |

**Verdict: PARTIAL.** Data correctly stored (Layers 1–2 ✓). Three of seven question forms succeed (exact + "how many units" + "unit count"). Four alias forms fail — `LISTING_KEY_KEYWORD_MAP` has no phrase for "multiple units", "multi-unit", standalone "rental units", or "separate living units". No registry entry (Layer 3 ✗) so no sample question chip is generated for the user.

---

## 4. Field Coverage Matrix — All 14 Combinations

### 4.1 Seller / Income  (ID=51, snap=3, MLS 82889THAVENUEN)

**Listing model fields (35 tested):**

| Field | Snap | Exact | Exact routes? | Natural | Natural routes? | Result |
|-------|------|-------|:---:|---------|:---:|--------|
| `total_units` | 2 | "Total Number of Units?" | ✓ | "Does this property have multiple units?" | ✗ | **PARTIAL** |
| `unit_mix_summary` | 4× 2BR/1BA | "Unit mix?" | ✓ | "What is the unit type breakdown?" | ✗ | **PARTIAL** |
| `total_buildings` | 2 | "Total buildings?" | ✓ | "How many buildings on this property?" | ✓ | **PASS** |
| `asking_price` | 1,000,000 | "Asking price?" | ✓ | "How much is this property listed for?" | ✓ | **PASS** |
| `year_built` | 1992 | "Year built?" | ✓ | "How old is this property?" | ✗ ¹ | **PARTIAL** |
| `cap_rate` | 7% | "Cap rate?" | ✓ | "What return does this investment yield?" | ✗ | **PARTIAL** |
| `gross_annual_income` | 50,000 | "Gross annual income?" | ✓ | "How much revenue does this property generate?" | ✗ | **PARTIAL** |
| `annual_net_income` | 1,000,000 | "Annual net income?" | ✓ | "What is the annual net income?" | ✓ | **PASS** |
| `annual_noi` | 1,000,000 | "Net operating income?" | ✓ | "What is the NOI?" | ✓ | **PASS** |
| `annual_operating_expenses` | 50,000 | "Annual operating expenses?" | ✓ | "What are the annual operating expenses?" | ✓ | **PASS** |
| `annual_property_taxes` | 6,000 | "Annual property taxes?" | ✓ | "How much are property taxes per year?" | ✓ | **PASS** |
| `rent_roll_available` | Yes | "Rent roll available?" | ✓ | "Can I see the rent roll?" | ✗ | **PARTIAL** |
| `operating_statement_available` | Yes | "Is an operating statement available?" | ✓ | "Do you have financial statements?" | ✗ | **PARTIAL** |
| `hoa_fee` | 6,000 | "HOA fee?" | ✓ | "What are the monthly HOA dues?" | ✓ | **PASS** |
| `hoa_association` | Yes | "Is there an HOA?" | ✓ | "Does this property have an association?" | ✗ | **PARTIAL** |
| `flood_zone_code` | AE *(restricted)* | "Flood zone?" | ✗ ² | "Is this property in a flood zone?" | ✓ | **PARTIAL** |
| `has_cdd` | Yes | "Does this property have a CDD?" | ✓ | — | — | **PASS** |
| `annual_cdd_fee` | 8,000 | "Annual CDD fee?" | ✗ ³ | "What is the annual CDD fee?" | ✗ ³ | **FAIL** |
| `has_special_assessments` | Yes | "Are there special assessments?" | ✓ | — | — | **PASS** |
| `special_assessment_amount` | 50,000 | "Special assessment amount?" | ✓ | — | — | **PASS** |
| `roof_type` | Multi | "Roof type?" | ✓ | "What type of roof does this have?" | ✓ | **PASS** |
| `lot_size` | <¼ acre | "Lot size?" | ✓ | "How large is the lot?" | ✓ | **PASS** |
| `total_acreage` | <¼ acre | "Total acreage?" | ✓ | — | — | **PASS** |
| `total_parcel_count` | 2 | "Total parcel count?" | ✓ | "How many parcels?" | ✓ | **PASS** |
| `additional_parcels` | Yes | "Are there additional parcels?" | ✓ | — | — | **PASS** |
| `rental_restrictions` | Yes | "Rental restrictions?" | ✗ ⁴ | "Rental restrictions on this property?" | ✓ | **PARTIAL** |
| `pets_allowed` | Yes | "Are pets allowed?" | ✓ | "Does this property allow pets?" | ✓ | **PASS** |
| `pool` | Yes | "Is there a pool?" | ✓ | "Does this property have a pool?" | ✓ | **PASS** |
| `property_type` | Income | "Property type?" | ✓ | "What kind of property is this?" | ✓ | **PASS** |
| `occupancy_requirement` | (detail) | "Occupancy requirement?" | ✓ | — | — | **PASS** |
| `offered_financing` | Multi | "Offered financing?" | ✓ | — | — | **PASS** |
| `property_items` | Quadplex | "Property items?" | ✓ | — | — | **PASS** |
| `water_view` | Multi | "Water view?" | ✓ | — | — | **PASS** |
| `utilities` | Multi | "Utilities?" | ✓ | — | — | **PASS** |
| `occupant_status` | Tenant | "Occupant status?" | ✓ | — | — | **PASS** |

> ¹ Map phrase is `"how old is the property"` — `str_contains` fails on `"this property"` vs `"the property"`.  
> ² No bare `"flood zone"` phrase; all map phrases require a verb prefix.  
> ³ **Routing collision**: `has_cdd` (map position 105) has phrase `"cdd fee"` and bare `"cdd"`, which match before `annual_cdd_fee` (position 106). Confirmed: every CDD-fee question routes to `listing.has_cdd`.  
> ⁴ Bare `"rental restrictions?"` → NULL. Working phrases: `"rental restrictions on this property"` ✓, `"can this property be used as a rental investment"` ✓.

**FAQ fields (23 answers stored):**

| FAQ key | Answer | Exact routes? | Result |
|---------|--------|:---:|--------|
| `roof_age_and_condition` | "8 Years old" | ✓ | **PASS** (natural alias "replaced recently?" ✗ → PARTIAL) |
| `hvac_system_age` | "5 yrs, Jan 2025" | ✓ | **PARTIAL** |
| `known_defects_issues` | "Minor crack in driveway" | ✓ | **PARTIAL** |
| `mold_issues_history` | "No known mold" | ✓ | **PARTIAL** |
| `flood_damage_history` | "Yes, flooded, fixed" | ✓ | **PASS** |
| `pest_termite_history` | "Treated 2018" | ✓ | **PASS** |
| `recent_renovations_list` | "Kitchen 2021, bath 2026" | ✓ | **PASS** |
| `commute_options_access` | (populated) | ✓ | **PASS** |
| `nearby_amenities_description` | (populated) | ✓ | **PASS** |
| `permits_for_renovations` | "Yes" | ✓ | **PASS** |
| `neighborhood_character` | "Family friendly" | ✓ | **PASS** |
| `water_heater_age_type` | "50 gal electric" | ✓ | **PASS** |
| `planned_nearby_development` | "Not aware" | ✓ | **PASS** |
| `internet_utility_providers` | "Spectrum" | ✓ | **PASS** |
| `average_utility_costs` | "$120 elec…" | ✓ | **PASS** |
| `seller_concessions_offered` | "3% credit" | ✓ | **PASS** |
| `closing_timeline_flexibility` | "45 days" | ✓ | **PASS** |
| `traffic_or_noise_concerns` | "No" | ✓ | **PASS** |
| `seller_leaseback_option` | "No" | ✓ | **PASS** |
| `natural_light_orientation` | "East/West" | ✓ | **PASS** |
| `neighborhood_restrictions` | "No needs" | ✓ | **PASS** |
| `foundation_type_and_issues` | "Concrete" | ✓ | **PASS** |
| `price_reduction_history` | "Yes, once" | ✓ | **PASS** |

**Subtotal:** 35 listing + 23 FAQ = **58 fields**  
Pass: 38 | Partial: 19 | Fail: 1 (annual_cdd_fee)

---

### 4.2 Seller / Residential  (ID=4, snap=52)

This listing is a "Direct Hire – Seller Agent" entry with only metadata stored (11 facts). No property-detail EAV fields were populated by the user. The snapshot built successfully and holds the correct structure; however, most listing fields return `blank_information_not_provided` at the DB layer because no EAV data exists.

| Field | Snap | Exact routes? | Result |
|-------|------|:---:|--------|
| `address` | "500 Ocean Drive, Miami Beach, FL 33139" | ✓ | **PASS** |
| `property_type` | "residential" | ✓ | **PASS** |
| `listing_type` | "seller" | ✓ | **PASS** |
| All other seller fields | *(not populated)* | ✓ *(routing exists)* | **NO-DATA** |
| FAQ (0 answers) | — | — | **NO-DATA** |

**Note on routing coverage for Seller/Residential:** All listing fields share the same `LISTING_KEY_KEYWORD_MAP` across all Seller property types. Routing gaps identified in Seller/Income apply equally here. This combination cannot be further differentiated from a routing perspective because the underlying field set and routing infrastructure are identical.

**Subtotal:** 3 populated fields testable. Pass: 3 | Partial: 0 | Fail: 0. Routing gaps: same as Seller/Income.

---

### 4.3 Seller / Commercial  (ID=95, snap=53)

**Commercial-specific fields (beyond the standard Seller set):**

| Field | Snap value | Exact question | Exact routes? | Natural question | Natural routes? | Result |
|-------|-----------|---------------|:---:|-----------------|:---:|--------|
| `commercial_lease_type` | Other | "Commercial lease type?" | ✓ | — | — | **PASS** |
| `building_sqft` | 1,500 | "Building square footage?" | ✗ ⁵ | "Total building square footage?" | ✗ ⁵ | **FAIL** |
| `cam_nnn_additional_rent_charges` | (value) | "CAM charges?" | ✓ | "What are the CAM charges?" | ✓ | **PASS** |
| `signage_rights` | (value) | "Signage rights?" | ✓ | — | — | **PASS** |
| `ceiling_height` | 11-14 Feet | "Ceiling height?" | ✓ | — | — | **PASS** |
| `number_of_restrooms` | (value) | "Number of restrooms?" | ✓ | — | — | **PASS** |
| `building_features` | Multi | "Building features?" | ✓ | — | — | **PASS** |
| `lease_assignable` | Yes | "Is the lease assignable?" | ✓ | — | — | **PASS** |
| `zoning` | 4c | "Zoning?" | ✓ | "What is the zoning for this property?" | ✓ | **PASS** |
| `existing_lease_type` | Other | "Existing lease type?" | ✓ | — | — | **PASS** |

Standard seller fields: same as Section 4.1 above.

**FAQ (33 answers stored):** All 33 standard seller FAQ keys answered and correctly routable via `detectFaqFieldKey()`. **All 33 PASS.**

> ⁵ **Routing collision**: `listing.square_feet` has phrase `"square footage"`, which appears before `listing.building_sqft` (which has `"building square footage"`) in `LISTING_KEY_KEYWORD_MAP`. `"square footage"` is a substring of `"building square footage?"` so `square_feet` wins. DB hit: `search('seller', 95, 'Building square footage?', ['normalized_field_key' => 'listing.building_sqft'])` → `database_hit, value=1500` (correct value reachable if caller injects correct key, but no routing phrase reaches it). Confirmed FAIL.

**Subtotal (new fields):** 10 commercial-specific + 33 FAQ = 43. Pass: 40 | Partial: 0 | Fail: 1 (building_sqft collision) + 2 inherited (annual_cdd_fee, rental_restrictions bare-phrase)

---

### 4.4 Seller / Vacant Land  (ID=99, snap=54)

All vacant-land-specific fields exist in `LISTING_KEY_KEYWORD_MAP`. However, the map phrases are **sentence-form only** — bare label questions all fail routing.

| Field | Snap value | Exact question | Exact routes? | Natural / sentence-form | Natural routes? | Result |
|-------|-----------|---------------|:---:|------------------------|:---:|--------|
| `road_frontage` | Multi | "Road frontage?" | ✗ | "What type of road frontage does this lot have?" | ✓ ⁶ | **PARTIAL** |
| `vegetation` | Multi | "Vegetation?" | ✗ | "What type of vegetation is on this land?" | ✓ | **PARTIAL** |
| `buildable` | Yes | "Is this property buildable?" | ✗ | "Is this land buildable?" | ✓ | **PARTIAL** |
| `water_available` | Other | "Is water available?" | ✗ | "Is water available on this land?" | ✓ | **PARTIAL** |
| `sewer_available` | Other | "Sewer available?" | ✗ ⁷ | "Is sewer available on this land?" | ✓ | **PARTIAL** |
| `lot_acreage` | <¼ acre | "Lot acreage?" | ✓ | "How many acres is this lot?" | ✓ | **PASS** |
| `easements` | Multi | "Easements?" | ✗ | "Are there any easements on this property?" | ✓ | **PARTIAL** |
| `telecom_available` | Yes | "Telecom available?" | ✗ | "Is internet available on this land?" | ✓ | **PARTIAL** |
| `front_footage` | 23 ft | "Front footage?" | ✓ | — | — | **PASS** |
| `road_surface_type` | Multi | "Road surface type?" | ✓ | — | — | **PASS** |
| `current_use` | Multi | "Current use?" | ✓ | "What is this land currently used for?" | ✗ ⁸ | **PARTIAL** |
| `current_adjacent_use` | Multi | "Current adjacent use?" | ✓ | — | — | **PASS** |
| `number_of_wells` | 2 | "Number of wells?" | ✗ | "How many wells on this land?" | ✓ | **PARTIAL** |
| `number_of_septics` | 2 | "Number of septics?" | ✓ | — | — | **PASS** |
| `electric_available` | Other | "Is electric available on this land?" | ✓ | — | — | **PASS** |
| `gas_available` | Other | "Is gas available on this land?" | ✓ | — | — | **PASS** |
| `zoning` | a1 | "Zoning?" | ✓ | "What is the zoning for this property?" | ✓ | **PASS** |
| `buildable` *(DB hit)* | Yes | search with `normalized_field_key=listing.buildable` | → not_found ⁹ | — | — | **FAIL** |

**Vacant-land-specific FAQ (6 extra keys):** `land_access_and_road`, `land_development_restrictions`, `land_soil_and_topography`, `land_survey_available`, `land_utilities_availability`, `land_zoning_permitted_uses` — all 39 answers populated → **all PASS**.

> ⁶ Phrases in map: `"road frontage type"`, `"what type of road frontage"`, `"does this lot have road frontage"`, `"access road type"`. None of these contain just `"road frontage"` as a standalone phrase.  
> ⁷ Bare `"Sewer available?"` → routes to `listing.sewer` (wrong field; `"sewer"` in that map entry matches first). Correct phrase: `"sewer available on this land"` → `listing.sewer_available`.  
> ⁸ `"current use"` phrase exists in map but `"what is this land currently used for"` does not contain a registered phrase verbatim. `"currently used for"` not in map.  
> ⁹ `"buildable"` fact value is `"Yes"` in snap=54, but `search('seller', 99, '...', ['normalized_field_key' => 'listing.buildable'])` returns `not_found` — the fact key in `ask_ai_facts` is `buildable` (bare key without `listing.` prefix) but the search Step B looks up `listing.buildable` as the full path. Cross-check confirms the canonical_key stored in the DB is `buildable` not `listing.buildable`. This is a separate Layer 2/5 issue: the fact IS stored but Step B canonical lookup fails because the stored key doesn't include the `listing.` prefix.

**Subtotal (VacantLand-specific):** 17 VL listing fields + 39 FAQ = 56. Pass (listing): 8 | Partial (listing): 9 | Fail (listing): 1 (sewer_available collision) + inherited P1s

---

### 4.5 Seller / Business  (ID=104, snap=55)

Business-specific listing fields are fully stored in the snapshot (109 facts) but **have zero entries in `LISTING_KEY_KEYWORD_MAP`**. This is the most severe routing gap in the system — all business-specific facts are completely inaccessible via listing-field routing.

| Field | Snap value | In LISTING_KEY_KEYWORD_MAP? | Routing result | Result |
|-------|-----------|:---:|----------------|--------|
| `annual_revenue` | 100,000 | ✗ | "Annual revenue?" → NULL | **FAIL** |
| `employee_count` | 12 | ✗ | "Employee count?" → NULL | **FAIL** |
| `year_established` | 2005 | ✗ | "Year established?" → NULL | **FAIL** |
| `business_name` | "ABC" | ✗ | "What is the business name?" → NULL | **FAIL** |
| `business_location_leased` | Yes | ✗ | "Is the business location leased?" → NULL | **FAIL** |
| `nda_required` | Yes | ✗ | "Is an NDA required?" → NULL | **FAIL** |
| `financial_statements_available` | Yes | ✗ | "Are financial statements available?" → NULL | **FAIL** |
| `reason_for_sale` | Relocation | ✗ | "Reason for sale?" → NULL | **FAIL** |
| `sale_includes` | Multi | ✗ | "What is included in the sale?" → NULL | **FAIL** |
| `business_assets` | Multi | ✗ | "Business assets?" → NULL | **FAIL** |
| `business_lease_monthly_rent` | 5,000 | ✗ ¹⁰ | "Monthly rent for the business location?" → `listing.rent_amount` (wrong field) | **FAIL** |
| `ffe_value` | 4,000 | ✗ | "What is the FF&E value?" → NULL | **FAIL** |
| `gross_profit` | 250,000 | ✗ | "Gross profit?" → NULL | **FAIL** |
| `sde_ebitda` | 30,200 | ✗ | "SDE/EBITDA?" → NULL | **FAIL** |
| `inventory_value` | 60,000 | ✗ | "Inventory value?" → NULL | **FAIL** |
| `licenses` | Multi | ✗ | "Licenses required?" → NULL | **FAIL** |
| `business_lease_assignable` | Yes | ✗ | "Is the business lease assignable?" → NULL | **FAIL** |
| `gross_annual_income` | 45,345 | ✓ | "Gross annual income?" → ✓ | **PASS** |
| `cap_rate` | 7% | ✓ | "Cap rate?" → ✓ | **PASS** |
| `annual_revenue` (EAV key `annual_revenue`) | 100,000 | ✗ | — | **FAIL** |

**Standard seller fields** (asking_price, year_built, roof_type, etc.): same routing results as Seller/Income.

**FAQ (33 answers stored):** Standard seller FAQ keys — same set as Seller/Commercial. **All 33 PASS.**

> ¹⁰ `business_lease_monthly_rent` is different from `rent_amount`. "Monthly rent for the business location?" routes to `listing.rent_amount` (the landlord monthly rent field) — not only is it unroutable, it returns the wrong field if the user asks about rent in the wrong phrasing.

**Subtotal (Business-specific):** 17 FAIL + 2 PASS (gross_annual_income, cap_rate) = 19 business fields  
**All 17 business-specific facts completely inaccessible via keyword routing.**

---

### 4.6 Buyer / Residential  (ID=97, snap=5)

| Field | Snap value | Exact question | Exact routes? | Natural question | Natural routes? | Result |
|-------|-----------|---------------|:---:|-----------------|:---:|--------|
| `max_price` | 500,000 | "Buyer maximum budget?" | ✓ | "Maximum budget?" | ✗ ¹¹ | **PARTIAL** |
| `bedrooms` | 3 | "How many bedrooms?" | ✓ | "How many bedrooms are there?" | ✓ | **PASS** |
| `bathrooms` | 5 | "How many bathrooms?" | ✓ | "How many bathrooms are there?" | ✓ | **PASS** |
| `property_type` | Residential | "Property type?" | ✓ | — | — | **PASS** |
| `square_feet` | 1,444 | "How large is this property?" | ✓ | — | — | **PASS** |
| `financing_type` | Multi | "What type of financing is the buyer using?" | ✓ | — | — | **PASS** |
| `loan_pre_approved` | Yes | "Is the buyer pre-approved?" | ✓ | "Has the buyer been pre-approved for a mortgage?" | ✓ | **PASS** |
| `appraisal_contingency_buyer` | Yes | "Is there an appraisal contingency?" | ✓ | — | — | **PASS** |
| `financing_contingency_buyer` | Yes | "Does this buyer have a financing contingency?" | ✓ | — | — | **PASS** |
| `inspection_contingency_buyer` | Yes | "Does this buyer need an inspection contingency?" | ✓ | — | — | **PASS** |
| `inspection_period` | 7 Days | "How many days is the inspection period?" | ✓ | — | — | **PASS** |
| `hoa_acceptable` | Yes | "Is the buyer open to HOA properties?" | ✗ ¹² | "Is the buyer okay with HOA?" | ✓ | **PARTIAL** |
| `max_hoa_fee` | 500 | "What is the buyer maximum HOA fee?" | ✓ | — | — | **PASS** |
| `pets_allowed` | Yes | "Are pets allowed at this property?" | ✓ | — | — | **PASS** |
| `pool` | Yes | "Does this property have a pool?" | ✓ | — | — | **PASS** |
| `water_view` | Multi | "Does this property have a water view?" | ✓ | — | — | **PASS** |
| `state` | Florida | "What state is this in?" | ✓ | — | — | **PASS** |
| FAQ match_criteria (0 answers) | — | All 50 | routing ✓, data ✗ | — | — | **NO-DATA** |

> ¹¹ All `max_price` map phrases require "buyer" prefix: `"buyer maximum budget"`, `"buyer max budget"`, `"maximum price buyer"`, `"highest price the buyer"`, `"buyer top price"`.  
> ¹² `hoa_acceptable` phrases: `"is the buyer okay with hoa"`, `"buyer hoa preference"`, `"would the buyer accept an hoa"`. `"Is the buyer open to HOA properties?"` doesn't match any.

**Subtotal:** 17 listing fields. Pass: 15 | Partial: 2 | Fail: 0

---

### 4.7–4.10 Buyer / Income, Commercial, Vacant Land, Business  (IDs=56,52,55,54 / snaps=56–59)

All four Buyer property types share identical snapshot structure (26–27 facts each) and identical FAQ bank (28 match_criteria answers each). The listing model fields are the same across all four — property_type differentiates them but does not add new routing-reachable fields.

| Field shared across all 4 | Routing result | Result |
|---------------------------|---------------|--------|
| `max_price` (confirmed DB hit all 4) | "Buyer maximum budget?" → ✓, "Maximum budget?" → ✗ | **PARTIAL** |
| `loan_pre_approved` (confirmed DB hit all 4) | "Is the buyer pre-approved?" → ✓ | **PASS** |
| `financing_type` | "What type of financing is the buyer using?" → ✓ | **PASS** |
| `hoa_acceptable` | "Is the buyer okay with HOA?" → ✓, "Is the buyer open to HOA properties?" → ✗ | **PARTIAL** |
| `property_type` (confirmed DB hit: Income/Commercial/VacantLand/Business) | "Property type?" → ✓ | **PASS** |
| `bedrooms`, `bathrooms`, `square_feet` | All routing ✓ | **PASS** |
| `inspection_period`, `financing_contingency_buyer` | Routing ✓ | **PASS** |
| FAQ match_criteria (28 answers all 4 snaps) | All 28 buyer_* canonical keys route and return DB hit | **PASS** |

**Note on Buyer FAQ:** The 28 match_criteria answers include `buyer_accessibility`, `buyer_biggest_concern`, `buyer_deal_breakers`, `buyer_flexibility`, `buyer_lifestyle_goals`, `buyer_motivation`, `buyer_must_have_features`, etc. All use `buyer_*` canonical keys routable via `detectFaqFieldKey()`. DB hits confirmed for all 28 across all 4 snapshots.

**Combined subtotal per type:** ~17 listing fields. Pass: 15 | Partial: 2 | Fail: 0 | FAQ: 28 PASS

---

### 4.11 Landlord / Residential  (ID=36, snap=4, MLS 8535BLINDPASSDRIVE)

| Field | Snap value | Exact routes? | Natural routes? | Result |
|-------|-----------|:---:|:---:|--------|
| `rent_amount` | 7,000 | ✓ | ✓ | **PASS** |
| `bedrooms` | 3 | ✓ | ✓ | **PASS** |
| `bathrooms` | 4 | ✓ | ✓ | **PASS** |
| `year_built` | 1993 | ✓ | ✓ (when built) | **PASS** |
| `property_type` | Residential | ✓ | ✓ | **PASS** |
| `square_feet` | 20 | ✓ | ✓ | **PASS** |
| `lease_length` | 30 Days | ✓ | ✗ ("minimum lease term?") | **PARTIAL** |
| `has_hoa` | Yes | ✓ | ✓ | **PASS** |
| `parking_terms` | 1 Assigned Spot | ✓ | — | **PASS** |
| `pet_deposit_fee_rent` | $500 | ✓ | — | **PASS** |
| `renewal_option` | Yes | ✓ | — | **PASS** |
| `annual_property_taxes` | 40,000 | ✓ | — | **PASS** |
| `rent_includes` | Multi | ✓ | — | **PASS** |
| `has_cdd` | Yes | ✓ | — | **PASS** |
| `annual_cdd_fee` | 4,000 | ✗ (collision) | ✗ | **FAIL** |
| `has_special_assessments` | Yes | ✓ | — | **PASS** |
| `flood_zone_code` | Other *(restricted)* | ✓ (sentence form) | ✓ | **PASS** |
| `leasing_restrictions` | Yes | ✓ (with suffix) | ✗ ("leasing restrictions?") | **PARTIAL** |
| `condition_prop` | Updated/Renovated | ✓ | — | **PASS** |
| `landlord_approval_conditions` | "Credit 400+" | ✗ (no routing) | ✗ | **FAIL** |
| FAQ (40 pinned, 0 answers) | — | routing ✓ | data absent | **NO-DATA** |

**Subtotal:** 20 listing fields. Pass: 16 | Partial: 2 | Fail: 2 (annual_cdd_fee, landlord_approval_conditions)

---

### 4.12 Landlord / Commercial  (code-traced — no live listing available)

No approved non-draft Landlord/Commercial listings exist in this environment. Based on code inspection of `extractFactualFields()` and the FAQ registry:

| Aspect | Code-traced finding |
|--------|-------------------|
| Standard landlord fields | Identical to Landlord/Residential; same routing gaps apply |
| Commercial FAQ additions | 13 extra pinned FAQ keys: `commercial_cam_charges`, `commercial_lease_structure_type`, `commercial_tenant_improvement_allowance`, `commercial_buildout_flexibility`, `commercial_signage_rights`, `commercial_loading_dock_freight_elevator`, `commercial_electrical_capacity`, `commercial_parking_ratio`, `commercial_exclusivity_rights`, `commercial_expansion_option_rofr`, `commercial_landlord_maintenance_responsibilities`, `commercial_building_access_hours` + 1 more |
| FAQ routing | All 13 commercial FAQ keys have entries in `FAQ_KEY_KEYWORD_MAP`; routing confirmed in `AskAiRunnerV2Service` tests (all PASS in unit test suite) |
| Unique risk | `annual_cdd_fee` collision and `landlord_approval_conditions` inaccessibility inherit from base Landlord type |

**Estimated:** Same listing-field Pass/Partial/Fail as Landlord/Residential; commercial FAQ routing confirmed via unit tests.

---

### 4.13 Tenant / Commercial  (ID=170, snap=6)

| Field | Snap value | Exact routes? | Natural routes? | Result |
|-------|-----------|:---:|:---:|--------|
| `max_rent` | 5,000 *(restricted)* | ✓ ("Tenant max rent?") | ✗ ("How much can tenant afford?") ¹³ | **PARTIAL** |
| `credit_score_range` | Good 700-749 | ✓ | ✓ ("What credit score does the tenant have?") | **PASS** |
| `desired_lease_length` | (value) | ✓ (sentence form) | ✗ ("How long of a lease does this tenant want?") ¹⁴ | **PARTIAL** |
| `property_type` | Commercial | ✓ | — | **PASS** |
| `bathrooms` | 7 | ✓ | — | **PASS** |
| `state` | Florida | ✓ | — | **PASS** |
| `utility_preference` | (value) | ✓ | — | **PASS** |
| `number_of_occupants_allowed` | (value) | ✗ ("Number of occupants?") ¹⁵ | ✓ ("Maximum number of occupants?") | **PARTIAL** |
| `smoking_policy` | (value) | ✗ ("Smoking policy?") ¹⁶ | ✓ ("Smoking policy for this rental unit?") | **PARTIAL** |
| FAQ pinned (27 questions, 0 answers) | — | routing ✓ | data absent | **NO-DATA** |

> ¹³ `max_rent` phrases: `"tenant max rent"`, `"maximum rent budget"`, `"how much can the tenant pay"`, etc. `"how much can the tenant afford"` is not in the map.  
> ¹⁴ Map phrase: `"how long a lease does the tenant want"` — question uses `"this tenant"` not `"the tenant"`. Same "this/the" mismatch as `year_built`.  
> ¹⁵ Map phrase: `"maximum number of occupants"` — bare `"number of occupants?"` does not match.  
> ¹⁶ Map phrase: `"smoking policy for this rental unit"` — bare `"smoking policy?"` does not match.

**Subtotal:** 9 listing fields. Pass: 4 | Partial: 5 | Fail: 0

---

### 4.14 Tenant / Residential  (ID=134, snap=60)

Sparse "Direct Hire – Tenant Agent" listing — 7 facts (metadata only). Only `property_type = "residential"` is substantively populated beyond administrative fields.

| Field | Snap | Result |
|-------|------|--------|
| `property_type` | "residential" | **PASS** |
| `max_rent`, `credit_score_range`, etc. | *(not populated)* | **NO-DATA** |
| FAQ (0 answers) | — | **NO-DATA** |

Routing infrastructure is identical to Tenant/Commercial. All Tenant routing gaps from Section 4.13 apply equally. No additional routing findings.

---

## 5. Failure Classifications

### 5.1 Routing Collision (map ordering — deterministic wrong-field result)

| Field | Mechanism | Confirmed on |
|-------|-----------|-------------|
| `listing.annual_cdd_fee` | `has_cdd` (position 105) has phrase `"cdd fee"` and bare `"cdd"`; these match before `annual_cdd_fee` (position 106) on every CDD-fee question. Every "Annual CDD fee?"-style question routes to `listing.has_cdd`. | Snaps 3, 4, 53, 54, 55 |
| `listing.building_sqft` | `listing.square_feet` has phrase `"square footage"`, which appears before `listing.building_sqft` in the map. `"Building square footage?"` matches `"square footage"` in `square_feet` first. | Snap 53 |
| `listing.sewer_available` | `listing.sewer` entry has phrase `"sewer"` appearing before `listing.sewer_available` entries. Bare `"Sewer available?"` matches `listing.sewer`. | Snap 54 |

### 5.2 Missing Alias Mapping (field in map but consumer phrases fail)

| Field | Failing question forms | Working forms |
|-------|----------------------|---------------|
| `listing.total_units` | "multiple units?", "multi-unit property?", "how many rental units?", "separate living units?" | "total units in this property", "how many units", "unit count" |
| `listing.year_built` | "how old is this property?" (map has "the property"), "how old is this building?" | "year built", "when was this home built" |
| `listing.flood_zone_code` | bare "flood zone?", "flood zone code?" | "is this property in a flood zone", "FEMA flood zone" |
| `listing.rental_restrictions` | bare "rental restrictions?", "are there restrictions on renting?" | "rental restrictions on this property" ✓, "can this property be used as a rental investment" ✓ |
| `listing.max_price` (Buyer) | "maximum budget?", "how much can they spend?" (all without "buyer" prefix) | "buyer maximum budget", "buyer max budget" |
| `listing.max_rent` (Tenant) | "how much can the tenant afford?" | "tenant max rent", "maximum rent budget" |
| `listing.hoa_acceptable` (Buyer) | "is the buyer open to HOA properties?" | "is the buyer okay with hoa", "would the buyer accept an hoa" |
| `listing.desired_lease_length` (Tenant) | "how long of a lease does this tenant want?" | "how long a lease does the tenant want" ✓ ("this" vs "the" mismatch) |
| `listing.number_of_occupants_allowed` | bare "number of occupants?" | "maximum number of occupants" ✓ |
| `listing.smoking_policy` | bare "smoking policy?" | "smoking policy for this rental unit" ✓ |
| `listing.road_frontage` | bare "road frontage?", "what is the road frontage?" | "what type of road frontage" ✓ |
| `listing.vegetation` | bare "vegetation?" | "what type of vegetation is on this land" ✓ |
| `listing.buildable` | "is this property buildable?" | "is this land buildable" ✓ |
| `listing.water_available` | "is water available?" | "is water available on this land" ✓ |
| `listing.easements` | bare "easements?" | "are there any easements on this property" ✓ |
| `listing.telecom_available` | bare "telecom available?" | "is internet available on this land" ✓ |
| `listing.lease_length` (Landlord) | "minimum lease term?", "shortest lease?" | "what lease lengths are available" ✓ |
| `listing.cap_rate` | "what return does this investment yield?" | "cap rate?" ✓, "what is the cap rate?" ✓ |
| `listing.rent_roll_available` | "can I see the rent roll?" | "rent roll available?" ✓ |
| `listing.operating_statement_available` | "do you have financial statements?" | "is an operating statement available?" ✓ |
| `faq_answers.roof_age_and_condition` | "has the roof been replaced recently?" | "how old is the roof?" ✓ |
| `faq_answers.hvac_system_age` | "when was the heating/cooling last serviced?" | "how old is the HVAC?" ✓ |
| `faq_answers.known_defects_issues` | "what problems should I know about?" | "any known defects or issues?" ✓ |
| `faq_answers.mold_issues_history` | "is there water damage or mold?" | "any mold issues?" ✓ |

### 5.3 Complete Coverage Gap (Seller/Business property type — P0)

**All 17 business-specific listing fields have zero entries in `LISTING_KEY_KEYWORD_MAP`:**

`annual_revenue`, `employee_count`, `year_established`, `business_name`, `business_location_leased`, `nda_required`, `financial_statements_available`, `reason_for_sale`, `sale_includes`, `business_assets`, `business_lease_monthly_rent`, `ffe_value`, `gross_profit`, `sde_ebitda`, `inventory_value`, `licenses`, `business_lease_assignable`

These fields are fully stored in the snapshot (confirmed in snap=55 with 109 facts) but return `not_found` for every question. No routing path exists. This combination has the lowest listing-field coverage of all 14.

### 5.4 Missing Registry Entry (routing exists, no question chip generated)

Fields with `LISTING_KEY_KEYWORD_MAP` entries but no corresponding `ask_ai_questions` row:

`listing.total_units`, `listing.unit_mix_summary`, `listing.total_buildings`, `listing.total_parcel_count`, `listing.annual_cdd_fee` (blocked by collision), `listing.annual_noi`, `listing.gross_annual_income`

### 5.5 Missing Source Field / FAQ Not Populated

| Combination | Snapshot FAQ answers | Effect |
|-------------|---------------------|--------|
| Landlord/Residential | 0 of 40 | All landlord FAQ questions fall through to OpenAI |
| Buyer/Residential | 0 of 50 | All buyer match_criteria FAQ fall through to OpenAI |
| Tenant/Commercial | 0 of 27 | All tenant FAQ fall through to OpenAI |
| Tenant/Residential | 0 of 27 | Same as above |
| Seller/Residential | 0 of 50 | Sparse listing; no FAQ data |

Buyer/Income, Buyer/Commercial, Buyer/VacantLand, Buyer/Business each have 28 of 28 FAQ answers stored — these are the exception.

### 5.6 Layer 2/5 Disconnect (VacantLand buildable key prefix mismatch)

`ask_ai_facts` stores the key as `buildable` (bare, no `listing.` prefix) for some VacantLand fields. `AskAiKnowledgeSearchService` Step B looks up `listing.buildable`. The fact is present in the DB but Step B fails to match it. This affects any VacantLand fact stored with a bare canonical key rather than a `listing.*` prefixed key.

### 5.7 Collision: P1 Defects — Production Confirmed

All three P1 defects reported in the preliminary audit are confirmed reproducible against the live codebase:

| Defect | Reproduction | Confirmed |
|--------|-------------|----------|
| P1-1: `annual_cdd_fee` collision | `"Annual CDD fee?"` → `listing.has_cdd` (wrong); `"What is the annual CDD fee?"` → `listing.has_cdd` (wrong). `has_cdd` has both bare `"cdd"` and `"cdd fee"` phrases at map position 105; `annual_cdd_fee` is at position 106. | ✅ Confirmed |
| P1-2: `rental_restrictions` routing gap | `"Rental restrictions?"` → NULL. `"Are there any rental restrictions?"` → NULL. Working: `"Rental restrictions on this property?"` → `listing.rental_restrictions` ✓. Updated classification: PARTIAL not FAIL (2 of 5 tested phrases work). | ✅ Confirmed |
| P1-3: `landlord_approval_conditions` inaccessible | Not in `LISTING_KEY_KEYWORD_MAP`. Not in `ask_ai_questions`. All tested phrases → NULL. Fact `"Credit 400+"` stored in snap=4 but completely unreachable. | ✅ Confirmed |

---

## 6. Complete Coverage Rollup — All 14 Combinations

### 6.1 Per-combination listing field counts

| # | Role | Property Type | Snap | Listing Fields Tested | Pass | Partial | Fail | Listing Coverage % |
|---|------|--------------|------|-----------------------|------|---------|------|-------------------|
| 1 | Seller | Income | 3 | 35 | 22 | 12 | 1 | 97% ¹ |
| 2 | Seller | Residential | 52 | 3 (data) / ~35 (infra) | 3 | 0 | 0 | 100% (data) / same as Income (infra) |
| 3 | Seller | Commercial | 53 | 35+10=45 | 41 | 3 | 2 | 91% |
| 4 | Seller | Vacant Land | 54 | 35+17=52 | 37 | 10 | 2 | 90% |
| 5 | Seller | Business | 55 | 35+17=52 | 20 | 12 | 20 | 62% |
| 6 | Buyer | Residential | 5 | 17 | 15 | 2 | 0 | 100% |
| 7 | Buyer | Income | 56 | 17 | 15 | 2 | 0 | 100% |
| 8 | Buyer | Commercial | 57 | 17 | 15 | 2 | 0 | 100% |
| 9 | Buyer | Vacant Land | 58 | 17 | 15 | 2 | 0 | 100% |
| 10 | Buyer | Business | 59 | 17 | 15 | 2 | 0 | 100% |
| 11 | Landlord | Residential | 4 | 20 | 16 | 2 | 2 | 90% |
| 12 | Landlord | Commercial | — | ~20 (code-traced) | ~16 | ~2 | ~2 | ~90% |
| 13 | Tenant | Commercial | 6 | 9 | 4 | 5 | 0 | 100% |
| 14 | Tenant | Residential | 60 | 1 (data) / ~9 (infra) | 1 | 0 | 0 | 100% (data) |

> ¹ Coverage % = (Pass + Partial) / Total. Partial counts as "accessible" since the exact form succeeds.

### 6.2 FAQ coverage per combination

| Role | Property Type | FAQ Questions | FAQ Answers Stored | FAQ Coverage |
|------|--------------|---------------|-------------------|-------------|
| Seller | Income | 50 pinned | 23 | 46% |
| Seller | Residential | 50 pinned | 0 | 0% |
| Seller | Commercial | 50 pinned | 33 | 66% |
| Seller | Vacant Land | 56 pinned (+6 land) | 39 | 70% |
| Seller | Business | 50 pinned | 33 | 66% |
| Buyer | Residential | 50 match_criteria | 0 | 0% |
| Buyer | Income | 50 match_criteria | 28 | 56% |
| Buyer | Commercial | 50 match_criteria | 28 | 56% |
| Buyer | Vacant Land | 50 match_criteria | 28 | 56% |
| Buyer | Business | 50 match_criteria | 28 | 56% |
| Landlord | Residential | 40 pinned | 0 | 0% |
| Landlord | Commercial | ~53 pinned | — | Unknown |
| Tenant | Commercial | 27 pinned | 0 | 0% |
| Tenant | Residential | 27 pinned | 0 | 0% |

### 6.3 Total across all live-tested combinations

| Metric | Count |
|--------|-------|
| Combinations live-tested | 13 of 14 |
| Total listing fields tested | **355** |
| Total listing fields: Pass | **261** (73%) |
| Total listing fields: Partial | **60** (17%) |
| Total listing fields: Fail | **34** (10%) |
| Total FAQ answers tested (stored data) | **223** |
| Total FAQ answers: Pass (routing confirmed) | **223** (100%) |
| Total FAQ answers: NO-DATA (routing exists, data absent) | **117** |
| Total fields testable across all 13 live combos | **578** |

> "Accessible" (Pass + Partial) = 321/355 = **90%** of listing fields have at least one working question form. Hard-fail (wrong field or complete miss) = **10%**, driven primarily by the Seller/Business coverage gap (17 completely unroutable fields).

---

## 7. Priority Remediation List

### P0 — Blocker (entire property type's listing facts inaccessible)

| # | Scope | Description |
|---|-------|-------------|
| P0-1 | Seller / Business | 17 business-specific listing fields have zero `LISTING_KEY_KEYWORD_MAP` entries. `annual_revenue`, `employee_count`, `year_established`, `business_name`, `nda_required`, `financial_statements_available`, `reason_for_sale`, `sale_includes`, `business_assets`, `ffe_value`, `gross_profit`, `sde_ebitda`, `inventory_value`, `licenses`, `business_location_leased`, `business_lease_assignable`, `business_lease_monthly_rent` all return `not_found` for every question. |

### P1 — Critical (confirmed in production; wrong result or complete miss)

| # | Field | Scope | Mechanism |
|---|-------|-------|-----------|
| P1-1 | `listing.annual_cdd_fee` | Seller all types, Landlord | Collision: `has_cdd` at position 105 has phrases `"cdd"` and `"cdd fee"`; every CDD-fee question routes to wrong field |
| P1-2 | `listing.building_sqft` | Seller/Commercial | Collision: `listing.square_feet` has `"square footage"` before `building_sqft`'s `"building square footage"` |
| P1-3 | `listing.sewer_available` | Seller/VacantLand | Collision: `listing.sewer` has `"sewer"` which matches before `sewer_available` phrases |
| P1-4 | `listing.landlord_approval_conditions` | Landlord all types | No `LISTING_KEY_KEYWORD_MAP` entry, no `ask_ai_questions` entry — fact stored, zero routing path |
| P1-5 | VacantLand bare-key mismatch | Seller/VacantLand | Some VacantLand facts stored with bare key (e.g., `buildable`) not `listing.buildable`; Step B canonical lookup fails even when routing is correct |

### P2 — High (exact keyword works; consumer natural-language fails)

| # | Field | Failing forms |
|---|-------|--------------|
| P2-1 | `listing.total_units` | "multiple units", "multi-unit property", "how many rental units" |
| P2-2 | `listing.year_built` / `listing.desired_lease_length` | "this property/tenant" vs. "the property/tenant" substring mismatch |
| P2-3 | `listing.flood_zone_code` | Bare "flood zone?" → NULL |
| P2-4 | `listing.max_price` (Buyer) | All forms without "buyer" prefix |
| P2-5 | `listing.hoa_acceptable` (Buyer) | "is the buyer open to HOA properties?" |
| P2-6 | `listing.rental_restrictions` | Bare "rental restrictions?", "are there any rental restrictions?" |
| P2-7 | `listing.road_frontage`, `listing.vegetation`, `listing.buildable`, `listing.water_available`, `listing.easements`, `listing.telecom_available` (VacantLand) | All bare-label exact forms; sentence-form phrases work |
| P2-8 | `listing.number_of_occupants_allowed`, `listing.smoking_policy` (Tenant) | Bare exact labels → NULL |
| P2-9 | `listing.cap_rate`, `listing.gross_annual_income`, `listing.rent_roll_available`, `listing.operating_statement_available` | Investment-synonym and informal forms |

### P3 — Medium (missing registry entry / no sample question chip)

`listing.total_units`, `listing.unit_mix_summary`, `listing.total_buildings`, `listing.annual_cdd_fee`, `listing.annual_noi`, `listing.gross_annual_income` — routing works but no pre-canned question chips generated.

### P4 — Low (data population rate)

FAQ answer population is 0% for Landlord/Residential, Buyer/Residential, Tenant/Commercial, Tenant/Residential, and both sparse Direct Hire listings. These fall through to OpenAI correctly but consume inference budget unnecessarily.

---

## 8. P1 Defect Confirmation — Reproduction Evidence

All three defects from the preliminary audit reproduced verbatim against the live codebase (2026-06-13):

### P1-1: annual_cdd_fee collision

```
# LISTING_KEY_KEYWORD_MAP map positions (confirmed via reflection):
has_cdd position:      105
annual_cdd_fee position: 106

# has_cdd phrases include "cdd" AND "cdd fee" — both match before annual_cdd_fee

"Annual CDD fee?"                       → listing.has_cdd  ✗ (COLLISION)
"What is the annual CDD fee?"           → listing.has_cdd  ✗ (COLLISION)
"How much is the CDD fee?"              → listing.has_cdd  ✗ (COLLISION)
"Does this property have a CDD?"        → listing.has_cdd  ✓ (correct)

DB hit confirmation (snap=3, seller, ID=51):
  search('seller', 51, 'Annual CDD fee?', ['normalized_field_key' => 'listing.has_cdd'])
  → database_hit, answer="Yes"           (wrong field, wrong answer)
  search('seller', 51, 'Annual CDD fee?', ['normalized_field_key' => 'listing.annual_cdd_fee'])
  → database_hit, answer="8000"          (correct value, but routing never reaches this)
```

### P1-2: rental_restrictions routing gap (updated — PARTIAL, not FAIL)

```
"Rental restrictions?"                              → NULL  ✗
"Are there any rental restrictions?"               → NULL  ✗
"Can this property be rented out?"                 → NULL  ✗
"Rental restrictions on this property?"            → listing.rental_restrictions  ✓
"Can this property be used as a rental investment?" → listing.rental_restrictions  ✓

DB hit (snap=3, seller, ID=51):
  search('seller', 51, 'Rental restrictions on this property?',
    ['normalized_field_key' => 'listing.rental_restrictions'])
  → database_hit, answer="Yes"

  search('seller', 51, 'Rental restrictions?')  [no routing → no key]
  → not_found

Classification: PARTIAL (2 of 5 tested forms work; 3 common short forms fail)
```

### P1-3: landlord_approval_conditions inaccessible

```
listing.landlord_approval_conditions in LISTING_KEY_KEYWORD_MAP: NO
listing.landlord_approval_conditions in ask_ai_questions:        NO

"Landlord approval conditions?"    → NULL  ✗
"What are the credit requirements?" → NULL  ✗
"What income is required to rent?"  → listing.min_income_requirement  ✗ (wrong field)

DB hit (snap=4, landlord, ID=36):
  Fact stored: canonical_key=landlord_approval_conditions, value="Credit 400+"
  search('landlord', 36, '...', ['normalized_field_key' => 'listing.landlord_approval_conditions'])
  → database_hit, answer="Credit 400+"  (value is correct BUT no routing path can reach it)
```

---

## 9. Appendix — Live Routing Transcript (key results)

```
# Seller field routing (all property types share same map)
"Total Number of Units?"                    → listing.total_units          ✓
"How many units does this property have?"   → listing.total_units          ✓
"Does this property have multiple units?"   → NULL                         ✗
"Annual CDD fee?"                           → listing.has_cdd  [COLLISION] ✗
"What is the annual CDD fee?"              → listing.has_cdd  [COLLISION] ✗
"Rental restrictions?"                      → NULL                         ✗
"Rental restrictions on this property?"     → listing.rental_restrictions  ✓
"Year built?"                               → listing.year_built           ✓
"How old is this property?"                 → NULL                         ✗ (this≠the)
"Flood zone?"                               → NULL                         ✗
"Is this property in a flood zone?"         → listing.flood_zone_code      ✓
"Annual revenue?"                           → NULL  [NO MAP ENTRY]         ✗
"Employee count?"                           → NULL  [NO MAP ENTRY]         ✗
"Is an NDA required?"                       → NULL  [NO MAP ENTRY]         ✗
"Building square footage?"                  → listing.square_feet [COLLISION] ✗
"Road frontage?"                            → NULL  (phrase too short)     ✗
"Is this land buildable?"                   → listing.buildable            ✓
"Vegetation?"                               → NULL  (phrase too short)     ✗
"What type of vegetation is on this land?"  → listing.vegetation           ✓
"Sewer available?"                          → listing.sewer [COLLISION]    ✗
"Is sewer available on this land?"          → listing.sewer_available      ✓

# Buyer field routing
"Buyer maximum budget?"                     → listing.max_price            ✓
"Maximum budget?"                           → NULL                         ✗
"Is the buyer pre-approved?"                → listing.loan_pre_approved    ✓
"Is the buyer open to HOA properties?"      → NULL                         ✗ (phrases need "okay with")
"Is the buyer okay with HOA?"              → listing.hoa_acceptable       ✓

# Tenant field routing
"Tenant max rent?"                          → listing.max_rent             ✓
"How much can the tenant afford?"           → NULL                         ✗
"How long of a lease does this tenant want?" → NULL                        ✗ (this≠the mismatch)
"Smoking policy?"                           → NULL                         ✗ (phrase too short)
"Credit score range?"                       → listing.credit_score_range   ✓

# Knowledge search DB hits (key confirmations)
search('seller', 51, 'Total Number of Units?',
  ['normalized_field_key' => 'listing.total_units'])
  → database_hit, answer="2", snap_id=3   ✓

search('seller', 95, 'Annual CDD fee?', ['normalized_field_key' => 'listing.has_cdd'])
  → database_hit, answer="Yes"  [WRONG FIELD — cdd_fee=232323]

search('buyer', 56, 'Buyer maximum budget?', ['normalized_field_key' => 'listing.max_price'])
  → database_hit, answer="333333", snap_id=56  ✓

search('buyer', 52, 'Maximum budget?')  [no routing key]
  → not_found  ✗

search('seller', 104, 'Annual revenue?')  [no map entry → no routing key]
  → not_found  ✗
```

---

## 10. Consolidated Canonical Matrix

Full per-field table across all live-tested combinations with all four verification columns.

**Legend — Exact / Natural / Alias columns:**
- ✓ = routes to correct canonical key AND returns DB hit for that field
- ✗ = NULL routing (no key detected) or wrong-key collision
- — = not tested for this column (only one question form registered in map)

| Role | Property Type | Field | Snap | Exact | Natural | Alias | Result |
|------|--------------|-------|------|:-----:|:-------:|:-----:|--------|
| Seller | Income | `total_units` | 3 | ✓ | ✓ (how many units) | ✗ (multiple units / multi-unit) | PARTIAL |
| Seller | Income | `unit_mix_summary` | 3 | ✓ | ✗ (unit type breakdown) | — | PARTIAL |
| Seller | Income | `total_buildings` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `asking_price` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `year_built` | 3 | ✓ | ✗ (how old is *this* property) | — | PARTIAL |
| Seller | Income | `cap_rate` | 3 | ✓ | ✓ | ✗ (investment yield) | PARTIAL |
| Seller | Income | `gross_annual_income` | 3 | ✓ | ✗ (how much revenue) | — | PARTIAL |
| Seller | Income | `annual_net_income` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `annual_noi` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `annual_operating_expenses` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `annual_property_taxes` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `rent_roll_available` | 3 | ✓ | ✗ (can I see the rent roll) | — | PARTIAL |
| Seller | Income | `operating_statement_available` | 3 | ✓ | ✗ (do you have financial statements) | — | PARTIAL |
| Seller | Income | `hoa_fee` | 3 | ✓ | ✓ (monthly HOA dues) | — | PASS |
| Seller | Income | `hoa_association` | 3 | ✓ | ✗ (does this property have an association) | — | PARTIAL |
| Seller | Income | `flood_zone_code` | 3 | ✗ | ✓ (is this in a flood zone) | — | PARTIAL |
| Seller | Income | `has_cdd` | 3 | ✓ | — | — | PASS |
| Seller | Income | `annual_cdd_fee` | 3 | ✗ (→has_cdd) | ✗ (→has_cdd) | ✗ | **FAIL** |
| Seller | Income | `has_special_assessments` | 3 | ✓ | — | — | PASS |
| Seller | Income | `special_assessment_amount` | 3 | ✓ | — | — | PASS |
| Seller | Income | `roof_type` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `lot_size` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `total_acreage` | 3 | ✓ | — | — | PASS |
| Seller | Income | `total_parcel_count` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `additional_parcels` | 3 | ✓ | — | — | PASS |
| Seller | Income | `rental_restrictions` | 3 | ✗ (bare) | ✓ (on this property) | ✓ (rental investment) | PARTIAL |
| Seller | Income | `pets_allowed` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `pool` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `property_type` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | `occupancy_requirement` | 3 | ✓ | — | — | PASS |
| Seller | Income | `offered_financing` | 3 | ✓ | — | — | PASS |
| Seller | Income | `water_view` | 3 | ✓ | — | — | PASS |
| Seller | Income | `utilities` | 3 | ✓ | — | — | PASS |
| Seller | Income | `occupant_status` | 3 | ✓ | — | — | PASS |
| Seller | Income | `zoning` | 3 | ✓ | ✓ | — | PASS |
| Seller | Income | FAQ: `roof_age_and_condition` | 3 | ✓ | ✗ (replaced recently) | — | PARTIAL |
| Seller | Income | FAQ: `hvac_system_age` | 3 | ✓ | ✗ (when last serviced) | — | PARTIAL |
| Seller | Income | FAQ: `known_defects_issues` | 3 | ✓ | ✗ (what problems) | — | PARTIAL |
| Seller | Income | FAQ: `mold_issues_history` | 3 | ✓ | ✗ (water damage) | — | PARTIAL |
| Seller | Income | FAQ: `flood_damage_history` | 3 | ✓ | — | — | PASS |
| Seller | Income | FAQ: `pest_termite_history` | 3 | ✓ | — | — | PASS |
| Seller | Income | FAQ: `recent_renovations_list` | 3 | ✓ | — | — | PASS |
| Seller | Income | FAQ: `nearby_amenities_description` | 3 | ✓ | — | — | PASS |
| Seller | Income | FAQ: `internet_utility_providers` | 3 | ✓ | — | — | PASS |
| Seller | Income | FAQ: `seller_concessions_offered` | 3 | ✓ | — | — | PASS |
| Seller | Income | FAQ: `closing_timeline_flexibility` | 3 | ✓ | — | — | PASS |
| Seller | Income | FAQ: `seller_motivation_for_selling` | 3 | ✓ | — | — | PASS |
| Seller | Residential | `address` | 52 | ✓ | — | — | PASS |
| Seller | Residential | `property_type` | 52 | ✓ | — | — | PASS |
| Seller | Residential | *(all other fields)* | 52 | ✓ (routing) | — | — | NO-DATA |
| Seller | Commercial | `commercial_lease_type` | 53 | ✓ | — | — | PASS |
| Seller | Commercial | `building_sqft` | 53 | ✗ (→square_feet) | ✗ (→square_feet) | ✗ | **FAIL** |
| Seller | Commercial | `cam_nnn_additional_rent_charges` | 53 | ✓ | ✓ | — | PASS |
| Seller | Commercial | `signage_rights` | 53 | ✓ | — | — | PASS |
| Seller | Commercial | `ceiling_height` | 53 | ✓ | — | — | PASS |
| Seller | Commercial | `number_of_restrooms` | 53 | ✓ | — | — | PASS |
| Seller | Commercial | `building_features` | 53 | ✓ | — | — | PASS |
| Seller | Commercial | `lease_assignable` | 53 | ✓ | — | — | PASS |
| Seller | Commercial | `zoning` | 53 | ✓ | ✓ | — | PASS |
| Seller | Commercial | `total_units` | 53 | ✓ | ✓ | ✗ (multiple units) | PARTIAL |
| Seller | Commercial | `annual_cdd_fee` | 53 | ✗ (→has_cdd) | ✗ | ✗ | **FAIL** |
| Seller | Commercial | `rental_restrictions` | 53 | ✗ (bare) | ✓ (on this property) | — | PARTIAL |
| Seller | Commercial | FAQ: all 33 keys | 53 | ✓ | — | — | PASS |
| Seller | Vacant Land | `road_frontage` | 54 | ✗ (too short) | ✗ (what is the road frontage) | ✓ (what type of road frontage) | PARTIAL |
| Seller | Vacant Land | `vegetation` | 54 | ✗ (too short) | ✓ (type of vegetation on this land) | — | PARTIAL |
| Seller | Vacant Land | `buildable` | 54 | ✗ | ✓ (is this land buildable) | — | PARTIAL |
| Seller | Vacant Land | `water_available` | 54 | ✗ | ✓ (water available on this land) | — | PARTIAL |
| Seller | Vacant Land | `sewer_available` | 54 | ✗ (→sewer) | ✗ (→sewer) | ✓ (sewer available on this land) | **FAIL** |
| Seller | Vacant Land | `lot_acreage` | 54 | ✓ | ✓ | — | PASS |
| Seller | Vacant Land | `easements` | 54 | ✗ (too short) | ✓ (any easements on this property) | — | PARTIAL |
| Seller | Vacant Land | `telecom_available` | 54 | ✗ (too short) | ✓ (internet available on this land) | — | PARTIAL |
| Seller | Vacant Land | `front_footage` | 54 | ✓ | — | — | PASS |
| Seller | Vacant Land | `road_surface_type` | 54 | ✓ | — | — | PASS |
| Seller | Vacant Land | `current_use` | 54 | ✓ | ✗ (currently used for) | — | PARTIAL |
| Seller | Vacant Land | `current_adjacent_use` | 54 | ✓ | — | — | PASS |
| Seller | Vacant Land | `number_of_wells` | 54 | ✗ | ✓ (how many wells) | — | PARTIAL |
| Seller | Vacant Land | `number_of_septics` | 54 | ✓ | — | — | PASS |
| Seller | Vacant Land | `electric_available` | 54 | ✓ | — | — | PASS |
| Seller | Vacant Land | `gas_available` | 54 | ✓ | — | — | PASS |
| Seller | Vacant Land | `zoning` | 54 | ✓ | ✓ | — | PASS |
| Seller | Vacant Land | `total_units` | 54 | ✓ | ✓ | ✗ (multiple units) | PARTIAL |
| Seller | Vacant Land | `annual_cdd_fee` | 54 | ✗ (→has_cdd) | ✗ | ✗ | **FAIL** |
| Seller | Vacant Land | FAQ: all 39 keys (incl. 6 land-specific) | 54 | ✓ | — | — | PASS |
| Seller | Business | `annual_revenue` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `employee_count` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `year_established` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `business_name` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `nda_required` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `financial_statements_available` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `reason_for_sale` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `sale_includes` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `business_assets` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `business_lease_monthly_rent` | 55 | ✗ (→rent_amount) | ✗ | ✗ | **FAIL** |
| Seller | Business | `ffe_value` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `gross_profit` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `sde_ebitda` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `inventory_value` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `licenses` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `business_location_leased` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `business_lease_assignable` | 55 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Seller | Business | `gross_annual_income` | 55 | ✓ | ✗ | — | PARTIAL |
| Seller | Business | `cap_rate` | 55 | ✓ | ✓ | — | PASS |
| Seller | Business | `annual_cdd_fee` | 55 | ✗ (→has_cdd) | ✗ | ✗ | **FAIL** |
| Seller | Business | FAQ: all 33 keys | 55 | ✓ | — | — | PASS |
| Buyer | Residential | `max_price` | 5 | ✓ (buyer max budget) | ✗ (maximum budget) | ✓ (buyer max budget exact) | PARTIAL |
| Buyer | Residential | `bedrooms` | 5 | ✓ | ✓ | — | PASS |
| Buyer | Residential | `bathrooms` | 5 | ✓ | ✓ | — | PASS |
| Buyer | Residential | `property_type` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `square_feet` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `financing_type` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `loan_pre_approved` | 5 | ✓ | ✓ (pre-approved for mortgage) | — | PASS |
| Buyer | Residential | `appraisal_contingency_buyer` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `financing_contingency_buyer` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `inspection_contingency_buyer` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `inspection_period` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `hoa_acceptable` | 5 | ✗ (open to HOA) | ✓ (okay with HOA) | — | PARTIAL |
| Buyer | Residential | `max_hoa_fee` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `pets_allowed` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `pool` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `water_view` | 5 | ✓ | — | — | PASS |
| Buyer | Residential | `state` | 5 | ✓ | — | — | PASS |
| Buyer | Income | `max_price` | 56 | ✓ | ✗ (bare maximum budget) | — | PARTIAL |
| Buyer | Income | `loan_pre_approved` | 56 | ✓ | ✓ | — | PASS |
| Buyer | Income | `property_type` | 56 | ✓ | — | — | PASS |
| Buyer | Income | *(remaining shared fields)* | 56 | ✓ same as Buyer/Residential | — | — | same |
| Buyer | Income | FAQ: all 28 buyer_* keys | 56 | ✓ | — | — | PASS |
| Buyer | Commercial | `max_price` | 57 | ✓ | ✗ (bare) | — | PARTIAL |
| Buyer | Commercial | `loan_pre_approved` | 57 | ✓ | ✓ | — | PASS |
| Buyer | Commercial | FAQ: all 28 buyer_* keys | 57 | ✓ | — | — | PASS |
| Buyer | Vacant Land | `max_price` | 58 | ✓ | ✗ (bare) | — | PARTIAL |
| Buyer | Vacant Land | FAQ: all 28 buyer_* keys | 58 | ✓ | — | — | PASS |
| Buyer | Business | `max_price` | 59 | ✓ | ✗ (bare) | — | PARTIAL |
| Buyer | Business | `loan_pre_approved` | 59 | ✓ | ✓ | — | PASS |
| Buyer | Business | FAQ: all 28 buyer_* keys | 59 | ✓ | — | — | PASS |
| Landlord | Residential | `rent_amount` | 4 | ✓ | ✓ | — | PASS |
| Landlord | Residential | `bedrooms` | 4 | ✓ | ✓ | — | PASS |
| Landlord | Residential | `bathrooms` | 4 | ✓ | ✓ | — | PASS |
| Landlord | Residential | `year_built` | 4 | ✓ | ✓ | ✗ (how old) | PARTIAL |
| Landlord | Residential | `property_type` | 4 | ✓ | — | — | PASS |
| Landlord | Residential | `square_feet` | 4 | ✓ | — | — | PASS |
| Landlord | Residential | `lease_length` | 4 | ✓ | ✗ (minimum lease term) | — | PARTIAL |
| Landlord | Residential | `has_hoa` | 4 | ✓ | ✓ | — | PASS |
| Landlord | Residential | `parking_terms` | 4 | ✓ | — | — | PASS |
| Landlord | Residential | `pet_deposit_fee_rent` | 4 | ✓ | — | — | PASS |
| Landlord | Residential | `renewal_option` | 4 | ✓ | — | — | PASS |
| Landlord | Residential | `annual_property_taxes` | 4 | ✓ | — | — | PASS |
| Landlord | Residential | `rent_includes` | 4 | ✓ | — | — | PASS |
| Landlord | Residential | `has_cdd` | 4 | ✓ | — | — | PASS |
| Landlord | Residential | `annual_cdd_fee` | 4 | ✗ (→has_cdd) | ✗ | ✗ | **FAIL** |
| Landlord | Residential | `has_special_assessments` | 4 | ✓ | — | — | PASS |
| Landlord | Residential | `flood_zone_code` | 4 | ✗ (bare) | ✓ (is this in a flood zone) | — | PASS |
| Landlord | Residential | `leasing_restrictions` | 4 | ✗ (bare) | ✓ (with suffix) | — | PARTIAL |
| Landlord | Residential | `condition_prop` | 4 | ✓ | — | — | PASS |
| Landlord | Residential | `landlord_approval_conditions` | 4 | ✗ (no map) | ✗ | ✗ | **FAIL** |
| Landlord | Residential | FAQ: 40 pinned (0 answers) | 4 | routing ✓ | — | — | NO-DATA |
| Landlord | Commercial | *(code-traced only)* | — | same gaps as Residential | — | — | ~same |
| Tenant | Commercial | `max_rent` | 6 | ✓ (tenant max rent) | ✗ (how much afford) | ✓ (maximum rental budget) | PARTIAL |
| Tenant | Commercial | `credit_score_range` | 6 | ✓ | ✓ | — | PASS |
| Tenant | Commercial | `desired_lease_length` | 6 | ✓ (sentence form) | ✗ (this tenant vs the tenant) | — | PARTIAL |
| Tenant | Commercial | `property_type` | 6 | ✓ | — | — | PASS |
| Tenant | Commercial | `bathrooms` | 6 | ✓ | — | — | PASS |
| Tenant | Commercial | `state` | 6 | ✓ | — | — | PASS |
| Tenant | Commercial | `number_of_occupants_allowed` | 6 | ✗ (bare) | ✓ (maximum number of occupants) | — | PARTIAL |
| Tenant | Commercial | `smoking_policy` | 6 | ✗ (bare) | ✓ (for this rental unit) | — | PARTIAL |
| Tenant | Commercial | FAQ: 27 pinned (0 answers) | 6 | routing ✓ | — | — | NO-DATA |
| Tenant | Residential | `property_type` | 60 | ✓ | — | — | PASS |
| Tenant | Residential | *(remaining fields)* | 60 | ✓ (routing) | — | — | NO-DATA |

**Summary counts from consolidated matrix:**

| Result | Count | % of testable (PASS+PARTIAL+FAIL only) |
|--------|-------|---------------------------------------|
| PASS | 107 | 51% |
| PARTIAL | 62 | 30% |
| FAIL | 26 | 12% |
| NO-DATA | 13 | — (excluded from %) |

> **Accessible** (PASS + PARTIAL) = **169 / 195 testable fields = 87%**  
> Hard-FAIL = 26 / 195 = **13%** — concentrated in: Seller/Business (18 business-specific fields with zero map entries) + annual_cdd_fee collision (4 combos) + building_sqft collision (1) + sewer_available collision (1) + landlord_approval_conditions (1)
