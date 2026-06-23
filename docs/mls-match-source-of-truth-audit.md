# MLS Match Source of Truth Audit

**Date:** June 23, 2026  
**Scope:** Read-only audit — no production behavior changed.  
**Purpose:** Establish an evidence-backed picture of what data source drives "Your Matched Listings" today, and where the gaps are between the legacy criteria tables and the current Offer Listing forms.

---

## 1. What Powers "Your Matched Listings" Today

### Entry Point → Result Set — Full Class/Method Chain

```
GET /stellar/buyer/results
  └─ Route::get (routes/web.php:180, name: stellar.buyer.results)
       └─ StellarBuyerResultsController::index()
            ├─ CriteriaListingResolver::resolveAllowedUserIds($user)
            │     Reads: users.user_type, user_agents (agent_id, user_id)
            │
            ├─ CriteriaListingResolver::resolveAccessible($user)
            │     Queries: buyer_criteria_auctions (is_approved=true, is_sold=false)
            │     Queries: tenant_criteria_auctions (is_approved=true, is_sold=false)
            │     Returns: array of {id, type:'buyer'|'tenant', label, created_at}
            │
            ├─ StellarBuyerResultsController::loadCriteriaById($type, $id, $allowedUserIds)
            │     if type='tenant': TenantCriteriaLoader::loadById($id, $allowedUserIds)
            │       Queries: tenant_criteria_auctions (id, user_id IN [$allowedUserIds],
            │                                          is_approved=true, is_sold=false)
            │       EAV reads via TenantCriteriaAuction::info($key)
            │     else: BuyerCriteriaLoader::loadById($id, $allowedUserIds)
            │       Queries: buyer_criteria_auctions (id, user_id IN [$allowedUserIds],
            │                                         is_approved=true, is_sold=false)
            │       EAV reads via BuyerCriteriaAuction::info($key)
            │     Returns: flat array → BuyerCriteriaPayload::__construct()
            │
            ├─ BuyerMatchService::match(BuyerCriteriaPayload $criteria, candidateCap=200)
            │     ├─ BuyerMatchQueryBuilder::build()
            │     │     Queries: bridge_properties
            │     │     Filters: standard_status='Active', property_type, list_price,
            │     │              bedrooms_total, bathrooms_total_integer, senior_community_yn,
            │     │              geographic bounding boxes (city/postal_code/county_or_parish/
            │     │              lat+lng radius/polygon bbox)
            │     ├─ PHP IDX gate: filter on raw_json.IDXParticipationYN
            │     ├─ BuyerMatchScorer::scoreAll()
            │     │     Scores each BridgeProperty against BuyerCriteriaPayload (100 pts total)
            │     └─ BuyerMatchResultBuilder::buildAll()
            │           Builds why_this_matches, tradeoffs, caution_flags, missing_data blocks
            │
            └─ BuyerResultViewMapper::map()
                  Returns Blade-safe arrays (raw_json and Tier-6 PII stripped)
                  → view('stellar.buyer.results', [...])
```

**Database tables touched:**

| Table | Purpose |
|---|---|
| `users` | Authenticate user; determine user_type for agent check |
| `user_agents` | For agents: resolve client user IDs |
| `buyer_criteria_auctions` | **Source of buyer criteria** (legacy table) |
| `buyer_criteria_auction_metas` | EAV meta for buyer criteria |
| `tenant_criteria_auctions` | **Source of tenant criteria** (legacy table) |
| `tenant_criteria_auction_metas` | EAV meta for tenant criteria |
| `bridge_properties` | MLS inventory being matched against |

---

## 2. Which Listing Type Is Being Matched

**The matcher reads exclusively from legacy criteria tables.**

| Table | Model | Read by Matcher? |
|---|---|---|
| `buyer_criteria_auctions` | `BuyerCriteriaAuction` | **YES** — primary buyer source |
| `tenant_criteria_auctions` | `TenantCriteriaAuction` | **YES** — primary tenant source |
| `buyer_agent_auctions` | `BuyerAgentAuction` | **NO** |
| `tenant_agent_auctions` | `TenantAgentAuction` | **NO** |

The modern user workflow at `/offer-listing/buyer` writes to `buyer_agent_auctions` via the `BuyerOfferListing` Livewire component (`HireBuyerAgentAuction` alias). The modern workflow at `/offer-listing/tenant/*` writes to `tenant_agent_auctions` via the `TenantOfferListing` Livewire component (`HireTenantAgentAuction` alias).

**None of these records are ever read by `CriteriaListingResolver`, `BuyerCriteriaLoader`, or `TenantCriteriaLoader`.**

---

## 3. Buyer Field Mapping Audit

The Buyer Offer Listing Livewire component (`BuyerOfferListing`) saves EAV meta to `buyer_agent_auction_metas`. The matcher reads from `buyer_criteria_auction_metas` via `BuyerCriteriaLoader`. The form fields and their EAV keys are therefore in different tables — even where key names happen to align, no data flows through.

| Form Field / UI Concept | Form Saves EAV Key | Table Written | Matcher Reads EAV Key | Table Read | Match Status |
|---|---|---|---|---|---|
| **Preferred Cities** | `location_dna_preferences` → `cities` sub-key | buyer_agent_auction_metas | `location_dna_preferences` → `cities` | buyer_criteria_auction_metas | **WRONG TABLE** |
| **ZIP Codes** | `location_dna_preferences` → `zip_codes` sub-key | buyer_agent_auction_metas | `location_dna_preferences` → `zip_codes` | buyer_criteria_auction_metas | **WRONG TABLE** |
| **Counties** | `counties` (JSON) | buyer_agent_auction_metas | `preferred_counties` | buyer_criteria_auction_metas | **WRONG TABLE + key mismatch** |
| **Acceptable State** | `state` | buyer_agent_auction_metas | Not read by matcher | — | **GAP (matcher has no state filter)** |
| **Radius Searches** | `location_dna_preferences` → `radius_searches` sub-key | buyer_agent_auction_metas | `location_dna_preferences` → `radius_searches` | buyer_criteria_auction_metas | **WRONG TABLE** |
| **Drawn Polygons** | `location_dna_preferences` → `polygons` sub-key | buyer_agent_auction_metas | `location_dna_preferences` → `polygons` | buyer_criteria_auction_metas | **WRONG TABLE** |
| **Flexible Location** | Implicit in LDNA blob absence | buyer_agent_auction_metas | Inferred from empty location keys | buyer_criteria_auction_metas | **WRONG TABLE** |
| **Commute ZIP** | `commute_destination_zip` | buyer_agent_auction_metas | Not read by `BuyerCriteriaLoader` or scorer | — | **GAP (unread even in legacy flow)** |
| **Max Commute Minutes** | `max_commute_minutes` | buyer_agent_auction_metas | Not read by `BuyerCriteriaLoader` or scorer | — | **GAP (unread even in legacy flow)** |
| **Commute Mode** | `commute_mode` | buyer_agent_auction_metas | Not read by `BuyerCriteriaLoader` or scorer | — | **GAP (unread even in legacy flow)** |
| **Property Type** | `property_type` (scalar string) | buyer_agent_auction_metas | `property_types` (JSON array) | buyer_criteria_auction_metas | **WRONG TABLE + key/format mismatch** |
| **Property Sub-types** | `condition_prop_buyer` (JSON) | buyer_agent_auction_metas | `property_sub_types` | buyer_criteria_auction_metas | **WRONG TABLE + key mismatch** |
| **Bedrooms (min)** | `bedrooms` | buyer_agent_auction_metas | `min_bedrooms` (EAV) or native `bedrooms` | buyer_criteria_auctions | **WRONG TABLE + key mismatch** |
| **Bathrooms (min)** | `bathrooms` | buyer_agent_auction_metas | `min_bathrooms` (EAV) or native `bathrooms` | buyer_criteria_auctions | **WRONG TABLE + key mismatch** |
| **Min Square Feet** | `minimum_heated_square` | buyer_agent_auction_metas | `min_sqft` (EAV) or native `sqft` | buyer_criteria_auctions | **WRONG TABLE + key mismatch** |
| **Max Square Feet** | Not saved by Buyer Offer Listing | — | `max_sqft` | buyer_criteria_auction_metas | **GAP (form doesn't collect)** |
| **Flood Zone Preference** | `flood_zone_tolerance` (JSON) | buyer_agent_auction_metas | Not read by `BuyerCriteriaLoader` or scorer | — | **GAP (unread even in legacy flow)** |
| **HOA Preference** | `hoa_acceptance` | buyer_agent_auction_metas | `hoa_preference` (EAV) or native `hoa_fee_requirement` | buyer_criteria_auctions | **WRONG TABLE + key mismatch** |
| **HOA Max Monthly Fee** | `hoa_max_monthly_fee` | buyer_agent_auction_metas | `max_monthly_hoa` (EAV) or native `max_hoa_fee` | buyer_criteria_auctions | **WRONG TABLE + key mismatch** |
| **Pool Preference** | `pool_needed` | buyer_agent_auction_metas | `wants_pool` (EAV) or native `pool` | buyer_criteria_auctions | **WRONG TABLE + key mismatch** |
| **Garage Preference** | `garage_needed` | buyer_agent_auction_metas | `wants_garage` (EAV) or native `garage` | buyer_criteria_auctions | **WRONG TABLE + key mismatch** |
| **View Preference** | `view_preference` (JSON) | buyer_agent_auction_metas | `wants_any_view`, `wants_water_view` | buyer_criteria_auction_metas | **WRONG TABLE + key mismatch** |
| **Max Budget** | `maximum_budget` | buyer_agent_auction_metas | `max_price` (EAV) or native `max_price` | buyer_criteria_auctions | **WRONG TABLE + key mismatch** |
| **55+ Eligibility** | `leasing_55_plus` | buyer_agent_auction_metas | `is_55_plus_eligible` (EAV) or native `old_community` | buyer_criteria_auctions | **WRONG TABLE + key mismatch** |
| **Year Built Min** | Not saved by Buyer Offer Listing | — | `year_built_min` | buyer_criteria_auction_metas | **GAP (form doesn't collect)** |
| **Year Built Max** | Not saved by Buyer Offer Listing | — | `year_built_max` | buyer_criteria_auction_metas | **GAP (form doesn't collect)** |
| **Pet Friendly** | `pets` (pet detail fields) | buyer_agent_auction_metas | `wants_pet_friendly` (EAV) or native `pets_allowed` | buyer_criteria_auctions | **WRONG TABLE + key mismatch** |

**Summary:** Every single field has a "WRONG TABLE" status. Even fields where the EAV key names happen to match (e.g., `location_dna_preferences`) are stored in the wrong table and therefore never read by the matcher.

---

## 4. Tenant Field Mapping Audit

The Tenant Offer Listing Livewire component (`TenantOfferListing`) saves EAV meta to `tenant_agent_auction_metas`. The matcher reads from `tenant_criteria_auction_metas` via `TenantCriteriaLoader`.

| Form Field / UI Concept | Form Saves EAV Key | Table Written | Matcher Reads EAV Key | Table Read | Match Status |
|---|---|---|---|---|---|
| **Preferred Cities** | `location_dna_preferences` → `cities` (via LDNA blob) | tenant_agent_auction_metas | `cities` (separate EAV key) | tenant_criteria_auction_metas | **WRONG TABLE + structure mismatch** |
| **ZIP Codes** | `zipCodes` (JSON) | tenant_agent_auction_metas | Not read (`preferred_zip_codes` hardcoded to `[]`) | — | **WRONG TABLE + unread by TenantCriteriaLoader** |
| **Counties** | `counties` (JSON) | tenant_agent_auction_metas | `counties` | tenant_criteria_auction_metas | **WRONG TABLE** (key matches!) |
| **Radius Searches** | `location_dna_preferences` → `radius_searches` | tenant_agent_auction_metas | `location_dna_preferences` → `radius_searches` | tenant_criteria_auction_metas | **WRONG TABLE** |
| **Drawn Polygons** | `location_dna_preferences` → `polygons` | tenant_agent_auction_metas | `location_dna_preferences` → `polygons` | tenant_criteria_auction_metas | **WRONG TABLE** |
| **Property Type** | `property_type` (scalar) | tenant_agent_auction_metas | `property_type` (scalar) | tenant_criteria_auction_metas | **WRONG TABLE** (key/format match) |
| **Bedrooms** | `bedrooms` | tenant_agent_auction_metas | `bedrooms` / `custom_bedrooms` | tenant_criteria_auction_metas | **WRONG TABLE** (key matches) |
| **Bathrooms** | `bathrooms` | tenant_agent_auction_metas | `bathrooms` / `custom_bathrooms` | tenant_criteria_auction_metas | **WRONG TABLE** (key matches) |
| **Min Square Feet** | `minimum_heated_square` | tenant_agent_auction_metas | `minimum_sqft_needed` | tenant_criteria_auction_metas | **WRONG TABLE + key mismatch** |
| **Pool Preference** | `pool_needed` | tenant_agent_auction_metas | `pool` | tenant_criteria_auction_metas | **WRONG TABLE + key mismatch** |
| **Garage Preference** | `garage_needed` | tenant_agent_auction_metas | `garage` | tenant_criteria_auction_metas | **WRONG TABLE + key mismatch** |
| **Water View** | `view_preference` (JSON array) | tenant_agent_auction_metas | `has_water_view` (boolean) | tenant_criteria_auction_metas | **WRONG TABLE + key mismatch** |
| **Monthly Budget** | `budget` / `maximum_budget` | tenant_agent_auction_metas | `monthly_price` | tenant_criteria_auction_metas | **WRONG TABLE + key mismatch** |
| **Pets** | `pets` (has detail fields: type, breed, weight) | tenant_agent_auction_metas | `has_pets` (boolean) | tenant_criteria_auction_metas | **WRONG TABLE + key mismatch** |
| **Commute ZIP** | `commute_destination_zip` | tenant_agent_auction_metas | Not read by `TenantCriteriaLoader` | — | **GAP (unread even in legacy flow)** |
| **HOA Preference** | Not collected on Tenant form | — | Not read by `TenantCriteriaLoader` | — | **N/A** |
| **Flood Zone** | Not collected on Tenant form | — | Not read by `TenantCriteriaLoader` | — | **N/A** |
| **Desired Lease Length** | `desired_lease_length` (JSON) | tenant_agent_auction_metas | Not read by `TenantCriteriaLoader` | — | **GAP (unread even in legacy flow)** |
| **Credit Score Range** | `credit_score_range` | tenant_agent_auction_metas | Not read by `TenantCriteriaLoader` | — | **GAP (unread even in legacy flow)** |
| **Rent Includes** | `rent_includes` (JSON) | tenant_agent_auction_metas | Not read by `TenantCriteriaLoader` | — | **GAP (unread even in legacy flow)** |
| **Terms of Lease** | `terms_of_lease` (JSON) | tenant_agent_auction_metas | Not read by `TenantCriteriaLoader` | — | **GAP (unread even in legacy flow)** |
| **Tenant Qualifications** | `prior_eviction`, `prior_felony`, `monthly_income`, `number_occupant` | tenant_agent_auction_metas | Not read by matcher | — | **GAP (form-only, no match consumption)** |

---

## 5. Legacy vs. Current Gap Analysis

### Fields the current forms collect but the matcher always ignores (even in the legacy flow)

These gaps exist in `BuyerCriteriaLoader` and `TenantCriteriaLoader` regardless of which table is read — the matcher's payload simply doesn't include these dimensions:

**Buyer gaps (fields collected by form, absent from BuyerCriteriaPayload):**
- `commute_destination_zip`, `max_commute_minutes`, `commute_mode` — Saved as EAV; `BuyerCriteriaLoader` reads these keys but they are absent from the returned array and therefore absent from `BuyerCriteriaPayload`. The scorer has no commute scoring dimension.
- `flood_zone_tolerance` — Saved by form; no equivalent in `BuyerCriteriaPayload` or any scoring category.
- `year_built_min`, `year_built_max` — Consumed by the scorer but not collected by the current Buyer Offer Listing form (the legacy criteria form collected them).
- `max_sqft` — Consumed by the scorer but not collected by the current Buyer Offer Listing form.

**Tenant gaps (fields collected by form, absent from TenantCriteriaPayload):**
- `desired_lease_length`, `terms_of_lease`, `rent_includes` — Leasing term preferences saved by form; `TenantCriteriaLoader` output has no leasing dimension.
- `credit_score_range`, `monthly_income`, `prior_eviction`, `prior_felony` — Tenant qualification fields saved by form; not consumed by matcher.
- `zipCodes` — Saved by tenant form but `TenantCriteriaLoader` hardcodes `preferred_zip_codes: []`.
- `commute_destination_zip`, `max_commute_minutes` — Saved by tenant form; not read by `TenantCriteriaLoader`.

### Fields the matcher expects that the current forms no longer populate in the same way

| Matcher Expects | Legacy Form Key | Current Form Key | Delta |
|---|---|---|---|
| `preferred_counties` (buyer) | `preferred_counties` in buyer_criteria_auctions | `counties` in buyer_agent_auctions | Key rename AND table change |
| `property_types` (JSON array) | `property_types` in buyer_criteria_auctions | `property_type` (scalar) in buyer_agent_auctions | Type change AND table change |
| `min_sqft` (buyer) | `min_sqft` or native `sqft` in buyer_criteria_auctions | `minimum_heated_square` in buyer_agent_auctions | Key rename AND table change |
| `wants_pool` (buyer) | `wants_pool` or native `pool` in buyer_criteria_auctions | `pool_needed` in buyer_agent_auctions | Key rename AND table change |
| `wants_garage` (buyer) | `wants_garage` or native `garage` | `garage_needed` | Key rename AND table change |
| `max_price` (buyer) | native column or `max_price` EAV | `maximum_budget` EAV | Key rename AND table change |
| `hoa_preference` | native `hoa_fee_requirement` or `hoa_preference` EAV | `hoa_acceptance` | Key rename AND table change |
| `minimum_sqft_needed` (tenant) | `minimum_sqft_needed` in tenant_criteria_auctions | `minimum_heated_square` in tenant_agent_auctions | Key rename AND table change |
| `pool` (tenant) | `pool` in tenant_criteria_auctions | `pool_needed` in tenant_agent_auctions | Key rename AND table change |
| `has_water_view` (tenant) | `has_water_view` in tenant_criteria_auctions | `view_preference` (JSON array) | Type change AND table change |
| `monthly_price` (tenant) | `monthly_price` in tenant_criteria_auctions | `budget` / `maximum_budget` in tenant_agent_auctions | Key rename AND table change |
| `has_pets` (tenant) | `has_pets` in tenant_criteria_auctions | `pets` + detail fields in tenant_agent_auctions | Key rename AND table change |

---

## 6. Route Flow Diagram

```
MODERN USER WORKFLOW (currently disconnected from match results)
───────────────────────────────────────────────────────────────
/offer-listing/buyer                    /offer-listing/tenant/{user_type?}
        │                                          │
BuyerOfferListing (Livewire)            TenantOfferListing (Livewire)
        │                                          │
        ▼                                          ▼
buyer_agent_auctions                  tenant_agent_auctions
buyer_agent_auction_metas             tenant_agent_auction_metas
        │                                          │
        │              NOT CONNECTED               │
        └─────────────────┬────────────────────────┘
                          │
                          ✗ (no bridge exists)


LEGACY CRITERIA WORKFLOW (what the matcher actually reads)
──────────────────────────────────────────────────────────
/buyer-agent/auction/add              /tenant/criteria/auction/add
(or similar legacy routes)            (or similar legacy routes)
        │                                          │
Legacy form / wizard                  Legacy form / wizard
        │                                          │
        ▼                                          ▼
buyer_criteria_auctions               tenant_criteria_auctions
buyer_criteria_auction_metas          tenant_criteria_auction_metas
        │                                          │
        └──────────────┬───────────────────────────┘
                       │
                       ▼
        CriteriaListingResolver::resolveAccessible()
        [checks Schema::hasTable, queries both tables]
                       │
                       ▼
        BuyerCriteriaLoader::loadById()  ─or─  TenantCriteriaLoader::loadById()
        [reads EAV meta, maps to flat array]
                       │
                       ▼
              BuyerCriteriaPayload
                       │
                       ▼
            BuyerMatchService::match()
            ├─ BuyerMatchQueryBuilder → bridge_properties (SQL)
            ├─ IDX gate (PHP filter)
            ├─ BuyerMatchScorer::scoreAll()
            └─ BuyerMatchResultBuilder::buildAll()
                       │
                       ▼
         BuyerResultViewMapper::map()
                       │
                       ▼
         GET /stellar/buyer/results → Blade view
```

**Key observation:** The `StellarBuyerResultsController` `emptyView` helper references both legacy add URLs (`/buyer-agent/auction/add` and `/tenant/criteria/auction/add`) as the `buyerCriteriaAddUrl` and `tenantCriteriaAddUrl` CTAs. This confirms the controller was built against the legacy criteria workflow and has never been updated to point to the current offer listing routes.

---

## 7. Profile Audit

### Which user profile types are supported in the active match flow

| Profile Type | Supported by CriteriaListingResolver | Active Workflow |
|---|---|---|
| **Non-agent buyer** (owns `buyer_criteria_auctions` record) | YES — `resolveAllowedUserIds` returns `[$user->id]`; `BuyerCriteriaAuction::whereIn('user_id', ...)` finds it | **Legacy only.** Must have created a record via the old buyer criteria form, not the Offer Listing form. |
| **Tenant user** (owns `tenant_criteria_auctions` record) | YES — same pattern for `TenantCriteriaAuction`, guarded by `Schema::hasTable('tenant_criteria_auctions')` | **Legacy only.** Must have created a record via the old tenant criteria form. |
| **Agent** | YES — `resolveAllowedUserIds` also plucks client IDs from `user_agents` table via `agent_id = $user->id` | **Legacy only** for the same reason. |
| **Modern buyer** (created listing at `/offer-listing/buyer`) | **NO** — record is in `buyer_agent_auctions`, never queried | **Disconnected.** User sees empty state or "no criteria." |
| **Modern tenant** (created listing at `/offer-listing/tenant/*`) | **NO** — record is in `tenant_agent_auctions`, never queried | **Disconnected.** User sees empty state or "no criteria." |

### Empty state behavior for modern users

A user who created their buyer profile via `/offer-listing/buyer` and navigates to `/stellar/buyer/results` will fall into the `count($criteriaList) === 0` branch. If they are not an agent, the controller returns `emptyView('no_criteria')` — showing them "Your buyer profile isn't complete yet" even though they have a complete buyer offer listing on record. This is a silent failure with no diagnostic message.

---

## 8. Recommendation

**Adopt Option B: make the current Offer Listing tables the authoritative source of truth.**

### Rationale

The modern Buyer and Tenant Offer Listing forms (`BuyerOfferListing`, `TenantOfferListing`) are the active user-facing workflows. They produce far richer data than the legacy criteria forms (commute preferences, financing terms, lease structure, tenant qualifications, flood zone tolerance, etc.). Any new feature development — including the MLS matching improvements planned in subsequent tasks — should target data that is actively collected and maintained.

The legacy `buyer_criteria_auctions` and `tenant_criteria_auctions` tables are passive: the criteria add routes (`/buyer-agent/auction/add`, `/tenant/criteria/auction/add`) are still registered but new development has shifted to the Offer Listing wizard. Continuing to build on the legacy tables means building on a shrinking data set.

### What this requires

1. **New loaders** (`BuyerOfferListingCriteriaLoader`, `TenantOfferListingCriteriaLoader`) that read from `buyer_agent_auctions` / `tenant_agent_auctions` and map EAV keys to the flat array expected by `BuyerCriteriaPayload`. Key renames required (e.g., `counties` → `preferred_counties`, `pool_needed` → `wants_pool`, `maximum_budget` → `max_price`, `minimum_heated_square` → `min_sqft`, `hoa_acceptance` → `hoa_preference`, `hoa_max_monthly_fee` → `max_monthly_hoa`).

2. **Updated `CriteriaListingResolver`** to query `buyer_agent_auctions` and `tenant_agent_auctions` instead of (or in addition to) the legacy tables, using the `workflow_type = 'offer_listing'` meta key as the filter to distinguish offer listing records from hire-agent records stored in the same tables.

3. **Updated add/edit CTAs** in `StellarBuyerResultsController` to point to `/offer-listing/buyer` and `/offer-listing/tenant/tenant` instead of the legacy routes.

4. **EAV key alignment**: either normalize the offer listing form's EAV keys to match what the loader expects, or handle the mapping in the new loaders. The second approach is lower-risk (no form changes required).

5. **Backward compatibility**: Existing `buyer_criteria_auctions` and `tenant_criteria_auctions` records can continue to work during a transition period by keeping the legacy loaders active alongside the new ones and merging the `criteriaList` from both.

### What Option C (shared payload from both) would cost

Option C requires maintaining two parallel data extraction paths indefinitely. Given that the legacy tables are not being actively populated by new users, the overhead of merging is not justified. A clean cutover to Option B with a transition window is simpler and more maintainable.

---

## 9. Final Verdict

> **CONCERN**

MLS matching is currently connected **only to legacy criteria auctions** (`buyer_criteria_auctions` / `tenant_criteria_auctions`). Users who create their buyer or tenant profile through the modern `/offer-listing/buyer` and `/offer-listing/tenant` workflows — which are the current, active, user-facing paths — receive an **empty state** ("Your buyer profile isn't complete yet") when they visit the Matched Listings page, regardless of how completely they filled out their offer listing.

There is a **complete table-level disconnect** between the modern offer listing persistence layer and the MLS matching pipeline. No data written by `BuyerOfferListing` or `TenantOfferListing` reaches `BuyerMatchService`. This is not a configuration issue or a missing field — it is a structural gap: the loaders query entirely different database tables than the ones the forms write to.

Additionally, even in the legacy flow, the matcher does not consume several fields that both the legacy and modern forms collect: commute preferences (ZIP, minutes, mode), flood zone tolerance, desired lease terms, and tenant qualification data. These represent secondary gaps that persist regardless of which table is designated as the source of truth.

**Immediate next step recommended:** Implement updated loaders that read from `buyer_agent_auctions` / `tenant_agent_auctions`, using `workflow_type = 'offer_listing'` as the discriminator, with the EAV key remapping documented in Section 5 above.
