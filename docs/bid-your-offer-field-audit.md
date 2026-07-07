# Bid Your Offer — Field Audit

**Status:** Documentation / audit only. No code, migrations, config, or fields were added, changed, or removed to produce this document.
**Date:** 2026-07-01
**Scope:** Every field currently implemented in the **Bid Your Offer** (Offer Listing) flows, so it can later be compared against the Stellar MLS data-entry forms to decide which fields are missing for Property DNA, Buyer DNA, Tenant DNA, Location DNA, lifestyle tags, matching, Ask AI, search, marketing, and target-audience intelligence.

Flows audited:

- **Seller Offer Listings** — Residential, Income, Commercial Sale, Business Opportunity, Vacant Land
- **Buyer Criteria / Buyer Offer Listings** — Residential, Income, Commercial Sale, Business Opportunity, Vacant Land
- **Landlord Offer Listings** — Residential Rental, Commercial Lease
- **Tenant Criteria / Tenant Offer Listings** — Residential Rental, Commercial Lease

> **What "Bid Your Offer" maps to in code.** These are the `OfferListing` Livewire components: `app/Http/Livewire/OfferListing/{Seller,Buyer,Landlord,Tenant}/…OfferListing.php` (create/draft) and `…OfferListingEdit.php` (edit). They are stamped `workflow_type = 'offer_listing'` (as opposed to the older `hire_agent` flow). A consumer posts a listing/criteria and agents bid to represent them.

---

## How to read this document

The per-field tables use these columns (agents used compact headers — the mapping is below):

| Column | Meaning |
|---|---|
| Flow | Seller / Buyer / Landlord / Tenant Offer Listing |
| Property Type | Residential / Income / Commercial / Business / Vacant Land / Residential Rental / Commercial Lease (or "All"/"Shared") |
| Wizard Tab / Section | The tab and sub-section where the field is collected |
| Field Label | User-facing label |
| Field Key / DB Column (or Meta Key) | Livewire property and the DB target (native column vs `*_metas` meta_key) |
| Input Type | text, select, multiselect/select2, checkbox, radio, date, file, number, textarea, hidden, repeater |
| Options / Allowed Values | Enumerated options where applicable |
| Required? | **DB** = server-enforced, **UI** = client asterisk only, **No** |
| Saves to DB? | Yes / No / Partial (+ native vs meta) |
| Autopopulates on Edit? | Yes / No / Partial |
| Displays on Public Listing? | Yes / No / Partial |
| Used in Matching? | Yes / No (bid-matching only — see cross-cutting note) |
| Used in Ask AI? | Yes / Partial / No |
| Used in DNA? | Location DNA / Property DNA / Buyer DNA / Tenant DNA — Yes / No / "would" (labeled DNA but not triggered) |
| Current Metadata / Tags | Existing tag intent |
| Notes / Issues | Wiring gaps, "Other" companions, key mismatches, etc. |

"**Needs verification**" marks anything not confirmed in code.

---

## Cross-cutting facts (verified, apply to all flows)

These were confirmed directly and govern several columns throughout.

### Matching is agent↔consumer bid-matching, not property↔property
`*BidMatchScoreHelper` classes score an **agent's bid** against the consumer listing's **broker-compensation / agency terms + services** baseline. Per `config/match_scoring.php`:

- **Active dimensions:** `services` (35%) and `terms` (35%) only.
- **Configured but DISABLED:** `service_area` (15%), `experience` (10%), `availability` (5%), `compatibility` (0%). All `enabled => false`.
- Weights sum to 100. `service_area` is additionally `inactive_for_roles => ['seller']`.

**Consequence:** No consumer *property-criteria* field (price, beds, baths, sqft, location, features, financing, pets, HOA, flood, etc.) contributes to the match score today. Property criteria instead feed (a) the **search/filter** query (`bedrooms`, `bathrooms`, `property_type`, `title`, `price`) and (b) the **BYA compatibility engine**, which is kill-switched by default (`config/bya_compatibility.php`: `BYA_COMPATIBILITY_KILL_SWITCH` defaults `true`, `BYA_COMPATIBILITY_GA_ENABLED` defaults `false`). Rows below therefore mark property-criteria fields "Matching? = No" (with compatibility/search noted where relevant).

### Ask AI is a formal, per-role field registry
`app/Services/AskAi/` contains a full subsystem: `AskAiFieldQuestionRegistryService` (a ~3,589-line registry mapping every FAQ/field key per role to canonical context paths, labels, and sample questions), `AskAiKnowledgeSnapshotBuilderService` (builds a KB snapshot on draft-save and submit), `AskAiContextBuilderService` (maps listing meta → canonical facts), `AskAiFaqEnrichmentService` (synced by `php artisan …` `SyncFaqAnswers`). The user-authored **Listing AI FAQ** (`listing_ai_faq` meta; keys from `config/ai_faq_{role}.php`) is the primary answer source. "Used in Ask AI? = Yes" below means the field key appears in that facts map or FAQ registry.

### DNA systems (separate from each other)
- **Location DNA** (`app/Services/LocationDna/…`, job `ComputeLocationDna`) — geospatial enrichment keyed off `address`/lat/lng/city/state (POI, flood zone, school district, commute). Runs for Seller and Landlord offer listings when an address is present.
- **Property DNA** (`PropertyDnaGenerator`) — AI property personality/marketing profile; a `PropertyAuctionDnaObserver` dispatches it for the seller flow.
- **Buyer/Tenant DNA** (`BuyerTenantDnaGenerator`) — reads the **`buyer_criteria_auctions` / tenant criteria** tables, **not** `buyer_agent_auctions`. The Buyer Offer Listing flow does **not** dispatch it → Buyer DNA is effectively **unwired** for offer listings even though several Tab-1 fields are code-labeled "Property DNA Phase C." Marked "No (not triggered)" in the Buyer section.

### Storage model per role (confirmed)
- **Seller** — Offer Listing flow writes **everything via EAV meta** (`seller_agent_auction_metas`); only `title` and `address` are written to native columns (contradicts the CLAUDE.md description of the older Hire flow — see Seller note).
- **Buyer** — native content columns exist but are legacy/unused; the flow writes `title` native + **everything else as EAV meta** (`buyer_agent_auction_metas`).
- **Landlord** — **EAV meta** (`landlord_agent_auction_metas`); `title` and `auction_type` are dual-written (native + meta).
- **Tenant** — **EAV meta** (`tenant_agent_auction_metas`); `listing_title` dual-written to native `title`.

---

<!-- ============================================================= -->

## SELLER OFFER LISTINGS

**Flow:** Seller posts a property; agents bid to represent. Components: `SellerOfferListing.php` (create) / `SellerOfferListingEdit.php` (edit). Wizard shell `offer-seller-listing.blade.php` includes tabs in this order: **listing-details → property-preferences (Property Details) → financial-details → additional-details → seller-terms (Sale Terms) → broker-compensation → tax-legal-hoa-disclosures → documents-disclosures → photos-tours-documents → seller-info**, plus `shared.ai-questions-input` and `partials.location-dna-agent-panel`.

**Property-type values (code):** `Residential`, `Income`, `Commercial`, `Business`, `Vacant Land`. (Display labels "Commercial Sale"/"Business Opportunity" map to code `Commercial`/`Business`. The dropdown option "Opportunity" is commented out.)

**CRITICAL persistence finding (contradicts CLAUDE.md):** This flow does **not** use native columns as CLAUDE.md describes for the Hire flow. It writes **everything through EAV meta** (`saveMeta()` → `seller_agent_auction_metas`) and reads via the `$auction->get->{key}` accessor. The **only native columns written** are `user_id`, `title` (= `listing_title`), `address`, `is_draft`, `is_approved`, `is_sold`, `is_paid` (+ auto id/timestamps/`listing_id`). Native columns `sqft, min_price, financings, photos, bedroom_id, bathroom_id, city_id, county_id, video_file, audio_file, need_cma, referring_agent_id` are largely unused. So for nearly every field: **Saves to DB = Yes (EAV meta)**.

**Shared vs. conditional:** Contact, listing meta, address, broker compensation, sale terms, financing sub-sections, tax/legal/HOA, disclosures, photos, and generic property attributes (waterfront/water/interior/view) are **shared across all 5 types** (always rendered). Property-type differences are gated in `property-preferences` and `financial-details`. To avoid a ~1,500-row document, shared fields are tabulated once under **Seller — Shared**; each per-type section lists only the type-gated fields and inherits the shared table.

**Matching note:** `SellerBidMatchScoreHelper` scores agent bids using only the broker-compensation "terms" groups + service catalogs + `other_services[]` + `photo_enhancements[]`. No property attribute participates. `service_area` is inactive for seller.

### Seller — Shared (All Property Types)

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key / DB Column | Input Type | Options / Allowed Values | Required? | Saves to DB? | Autopopulates on Edit? | Displays on Public Listing? | Used in Matching? | Used in Ask AI? | Used in DNA? | Current Metadata / Tags | Notes / Issues |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Seller Offer | All | seller-info | First Name | `first_name` | text | — | Yes | Yes (meta) | Yes | Yes | No | No | No | Contact PII | Public per privacy notice |
| Seller Offer | All | seller-info | Last Name | `last_name` | text | — | Yes | Yes (meta) | Yes | Yes (admin-only per notice) | No | No | No | Contact PII | Notice says admin-only, but referenced in view |
| Seller Offer | All | seller-info | Phone Number | `phone_number` | tel (JS format) | — | Yes | Yes (meta) | Yes | Yes | No | No | No | Contact PII | Inquiry-email recipient fallback |
| Seller Offer | All | seller-info | Email Address | `email` | email | — | Yes | Yes (meta) | Yes | Yes | No | No | No | Contact PII | — |
| Seller Offer | All | seller-info | Seller's Current Status | `current_status` | select | First-Time Seller; Selling Primary Residence; Selling Secondary/Vacation Home; Selling Investment Property; Relocating and Need to Sell; Already Under Contract with Buyer; Listing Expired or Canceled; Investor – Selling One or More Properties | No | Yes (meta) | Yes | No (excluded) | No | No | No | — | Explicitly excluded from public view |
| Seller Offer | All | seller-info | Personal Photo | `photo` | file (image) | — | No | Yes (meta) | Yes (meta only) | No (excluded) | No | No | Partial | Media | Native `photo` column ignored; read from meta |
| Seller Offer | All | seller-info | Personal Video Link | `video_link` | url | — | No | Yes (meta) | Yes | No (excluded) | No | No | Yes (DNA `has_video_tour`) | Media | Video upload removed from UI (DB preserved) |
| Seller Offer | All | seller-info | Agent Brokerage | `agent_brokerage` | text | — | No | Yes (meta) | Yes | Yes | No | No | No | Agent cred | Rendered via agent-credentials partial (agent-created) |
| Seller Offer | All | seller-info | Agent License Number | `agent_license_number` | text | — | No | Yes (meta) | Yes | Yes | No | No | No | Agent cred | — |
| Seller Offer | All | seller-info | NAR Member ID | `agent_nar_member_id` | text | — | No | Yes (meta) | Yes | Yes | No | No | No | Agent cred | — |
| Seller Offer | All | listing-details | Listing Status | `listing_status` | radio | Active (default); Pending; Expired (disabled/auto) | No | Yes (meta) | Yes | Yes | No | No | No | Lifecycle | Drives `status` accessor |
| Seller Offer | All | listing-details | Listing Title | `listing_title` → native `title` | text | — | Yes | Yes (native `title`) | Yes | Yes | No | No | No | Core | Only field besides address on native table |
| Seller Offer | All | listing-details | Listing Date | `listing_date` | date | — | Yes | Yes (meta) | Yes | Yes | No | No | No | Core | Defaults to now() in mount |
| Seller Offer | All | listing-details | Expiration Date | `expiration_date` | date | — | Yes | Yes (meta) | Yes | Yes | No | No | No | Lifecycle | Drives Expired status |
| Seller Offer | All | listing-details | Listing Type | `auction_type` | select | Bidding Period; Traditional | Yes | Yes (meta) | Yes | Yes | No | No | No | Core | Legacy Offer-Listing identifier key |
| Seller Offer | All | listing-details | Bidding Period Length | `auction_time` | select | 1/3/5/7/10/14 Days | Yes if Bidding Period | Yes (meta) | Yes | Yes | No | No | No | Core | Hidden unless Bidding Period; feeds "ending soon" sort |
| Seller Offer | All | property-preferences (Address) | Street Address | `address` → native `address` | text (Google autocomplete) | — | Yes | Yes (native `address`) | Yes | Yes | No | Yes (`address`) | Yes (Location DNA geocode) | Core/geo | Falls back to title/'TBD' if empty |
| Seller Offer | All | property-preferences | Unit / Apt / Suite | `unit_address` | text | — | No | Yes (meta) | Yes | Yes | No | No | No | Geo | Rule `nullable\|max:100` |
| Seller Offer | All | property-preferences | (hidden) Latitude | `property_lat` | hidden | — | No | Yes (meta) | Yes | Yes | No | No | Yes (Location DNA) | Geo | — |
| Seller Offer | All | property-preferences | (hidden) Longitude | `property_lng` | hidden | — | No | Yes (meta) | Yes | Yes | No | No | Yes (Location DNA) | Geo | — |
| Seller Offer | All | property-preferences | (hidden) Google Place ID | `google_place_id` | hidden | — | No | Yes (meta) | Yes | No | No | No | No | Geo | — |
| Seller Offer | All | property-preferences | City | `property_city` | text (autocomplete) | — | Yes | Yes (meta) | Yes | Yes | No | No | Yes (Location DNA) | Geo | — |
| Seller Offer | All | property-preferences | State | `property_state` | text | — | Yes | Yes (meta) | Yes | Yes | No | No | Yes (Location DNA) | Geo | — |
| Seller Offer | All | property-preferences | ZIP Code | `property_zip` | text (max 10) | — | Yes | Yes (meta) | Yes | Yes | No | No | No | Geo | — |
| Seller Offer | All | property-preferences | County | `property_county` | text | — | Yes | Yes (meta) | Yes | No | No | No | No | Geo | — |
| Seller Offer | All | property-preferences | Property Type | `property_type` | select | Residential; Income; Commercial; Business; Vacant Land | Yes | Yes (meta) | Yes | Yes | No | Yes | Yes (DNA `property_type`) | Core gate | Search filter meta key |
| Seller Offer | All | property-preferences | Property Style | `property_items` | select (per-type list) | Per-type (see per-type tables) | Yes | Yes (meta, JSON) | Yes | Yes | No | Yes | Yes (DNA `property_style`) | Core | Options filtered by property_type |
| Seller Offer | All | property-preferences | Other Property Style | `other_property_items` | text | — | If style=Other | Yes (meta) | Yes | Yes | No | No | No | Other-pair | pairs with `property_items` |
| Seller Offer | All | property-preferences | Waterfront | `waterfront` | select | Yes; No | No | Yes (meta) | Yes | Yes (implied) | No | Yes | No | Y/N | Always shown |
| Seller Offer | All | property-preferences | Water Access | `water_access` | multiselect (Select2) | Bay/Harbor; Bayou; Beach; Canal-Freshwater; Canal-Saltwater; Creek; Gulf/Ocean; Intracoastal Waterway; Lake; Pond; River; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | Multiselect+Other | `wire:ignore` JS-persisted |
| Seller Offer | All | property-preferences | Other Water Access | `other_water_access` | text | — | No | Yes (meta) | Yes | No | No | No | No | Other-pair | — |
| Seller Offer | All | property-preferences | Water View | `water_view` | multiselect | Bay/Harbor-Full; Bay/Harbor-Partial; Canal; Creek/Stream; Gulf/Ocean-Full; Gulf/Ocean-Partial; Intracoastal Waterway; Lake; Pond; River; Other | No | Yes (meta, JSON) | Yes | Yes (implied) | No | Yes | No | Multiselect+Other | — |
| Seller Offer | All | property-preferences | Other Water View | `other_water_view` | text | — | No | Yes (meta) | Yes | No | No | No | No | Other-pair | — |
| Seller Offer | All | property-preferences | Water Frontage | `water_frontage` | text | — | No | Yes (meta) | Yes | Yes (implied) | No | No | No | — | — |
| Seller Offer | All | property-preferences | Waterfront Feet | `waterfront_feet` | number | — | No | Yes (meta) | Yes | Yes (implied) | No | Yes (`waterfront_feet`) | No | — | — |
| Seller Offer | All | property-preferences | Interior Features | `interior_features` | multiselect | Ceiling Fan(s); Crown Molding; Eat-in Kitchen; Fireplace; High Ceilings; …; Walk-In Closet(s); Wet Bar; Window Treatments; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | Multiselect+Other | Always shown |
| Seller Offer | All | property-preferences | Other Interior Features | `other_interior_features` | text | — | No | Yes (meta) | Yes | No | No | No | No | Other-pair | — |
| Seller Offer | All | property-preferences | View | `view_preference` | multiselect | Beach; City; Garden; Golf Course; Greenbelt; Mountain(s); Park; Pool; Tennis Court; Trees/Woods; Water; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | Yes (DNA `view_preference`) | Multiselect+Other | Computed getter `isOtherVisible` |
| Seller Offer | All | property-preferences | Other View | `other_preferences` | text | — | No | Yes (meta) | Yes | No (excluded) | No | No | No | Other-pair | — |
| Seller Offer | All | property-preferences | Total Acreage | `total_acreage` | select | `config(property_types.acreage_options)` | No | Yes (meta) | Yes | Yes | No | Yes | Yes (DNA `total_acreage`) | — | Shown all types |
| Seller Offer | All | additional-details | Property Description | `additional_details` | textarea | — | No | Yes (meta) | Yes | Yes | No | Yes (`additional_details`) | No | Description | Placeholder varies by type; only field in tab |
| Seller Offer | All | seller-terms (Special) | Special Sale Provision | `sale_provision` | multiselect | Assignment Contract; Auction; Bank Owned/REO; Government Owned; Probate Listing; Short Sale; None; Other | Yes | Yes (meta, **raw array, not JSON-encoded**) | Yes | Yes | No | Yes | Yes (DNA `sale_provision_type`) | Multiselect+Other | Saved raw array — note |
| Seller Offer | All | seller-terms | Other Special Sale Provision | `sale_provision_other` | text | — | No | Yes (meta) | Yes | Yes | No | No | No | Other-pair | — |
| Seller Offer | All | seller-terms | Seller Under Contract for Assignment | `sale_provision_assignment` | select | Yes; No | No | Yes (meta) | Yes | Yes | No | No | No | Y/N | Shown if Assignment Contract |
| Seller Offer | All | seller-terms | Assignment Fee to Broker (type) | `assignment_fee_type` | select | $ Flat Fee; % of Contract Assignment Value | No | Yes (meta) | Yes (default '$') | Yes | No | No | No | Fee | — |
| Seller Offer | All | seller-terms | Assignment Fee Amount | `assignment_fee_amount` | text/number | — | No | Yes (meta) | Yes | Yes | No | No | No | Fee | — |
| Seller Offer | All | seller-terms | Target Closing Timeframe | `target_closing_date` | select | ASAP; Within 1–6 Months; Over 6 Months; Flexible/Open-Ended | Yes | Yes (meta) | Yes | Yes | No | Yes | Yes (DNA `move_in_timing`) | Core | Not in draft-hash payload |
| Seller Offer | All (≠ Vacant Land) | seller-terms | Occupant Type | `occupant_status` | select | Owner; Tenant; Vacant | Yes (≠VL) | Yes (meta) | Yes | Yes | No | Yes | Yes (DNA `occupant_status`) | — | Not in draft-hash payload |
| Seller Offer | All (≠ Vacant Land) | seller-terms | Occupied Until | `occupant_tenant` | date | — | No | Yes (meta) | Yes | Yes | No | No | No | — | Shown if occupant=Tenant |
| Seller Offer | Bidding Period | seller-terms | Starting Price / Opening Bid | `starting_price` | text ($) | — | No | Yes (meta) | Yes | Yes | No | No | No | Price | Bidding Period only |
| Seller Offer | Bidding Period | seller-terms | Reserve Price | `reserve_price` | text ($) | — | No | Yes (meta) | Yes | Yes | No | No | No | Price | — |
| Seller Offer | Bidding Period | seller-terms | Buy Now Price | `buy_now_price` | text ($) | — | No | Yes (meta) | Yes | Yes | No | Yes (`buy_now_price`) | No | Price | — |
| Seller Offer | Non-Bidding | seller-terms | Desired Sale Price | `maximum_budget` | text ($) | — | Yes (non-Bidding) | Yes (meta) | Yes | Yes | No | Yes (→ asking_price) | No | Price | **Key mismatch:** view reads `desired_sale_price`; component key is `maximum_budget` — verify price shows |
| Seller Offer | All | seller-terms | Offered Financing/Currency | `offered_financing` | multiselect | Assumable; Cash; Conventional; FHA; Jumbo; VA; No-Doc; Non-QM; USDA; Cryptocurrency; Exchange/Trade; Lease Option; Lease Purchase; NFT; Seller Financing; Other | No (error span only) | Yes (meta, JSON) | Yes | Yes | No | Yes | Yes (DNA `offered_financing_types`) | Multiselect+Other | Drives conditional sub-sections |
| Seller Offer | All | seller-terms | Other Financing/Currency | `other_financing` | text | — | No | Yes (meta) | Yes | Yes | No | No | No | Other-pair | — |

**Shared — seller-terms financing sub-sections** (conditionally shown by `offered_financing`; all save to meta, autopopulate on edit, display publicly, not matched):

| Property Type | Section | Field Label | Field Key | Input Type | Options | Notes |
|---|---|---|---|---|---|---|
| All | Assumable | Offered Assumable Terms | `assumable_terms` | text | — | DNA `has_assumable_loan` |
| All | Assumable | Type of Loan | `assumable_loan_type` | select | FHA; VA; USDA | not in draft-hash |
| All | Assumable | Interest Rate | `max_assumable_rate` | text (%) | — | DNA `has_assumable_loan` |
| All | Assumable | Monthly Payment (P&I) | `max_monthly_payment` | text ($) | — | — |
| All | Assumable | Monthly Escrow/Impounds | `assumable_monthly_escrow` | text ($) | — | — |
| All | Assumable | Outstanding Balance | `outstanding_balance` | text ($) | — | — |
| All | Assumable | Down Payment Gap (type/amt) | `gap_payment_type` / `gap_payment_amount` | select/text | flat($); percent(%) | default '$' |
| All | Assumable | Loan Term Remaining | `assumable_loan_term_remaining` | text | — | — |
| All | Assumable | Date Loan Originated | `assumable_loan_origination_date` | text | — | — |
| All | Assumable | Loan Servicer/Lender | `assumable_loan_servicer` | text | — | — |
| All | Assumable | Assumption Fee (type/amt) | `assumable_fee_type` / `assumable_fee_amount` | select/text | $; % | default '$' |
| All | Assumable | Assumption Fee Responsibility | `assumption_fee_responsibility` | select | Buyer; Seller; Split | — |
| All | Assumable | Occupancy Requirement (+Other) | `assumable_occupancy_requirement` / `assumable_occupancy_other` | select/text | Primary Residence (FHA); Primary Residence–60 Days (VA); No Occupancy Restriction; Other | Ask AI; Other-pair |
| All | Crypto | Acceptable Cryptocurrency | `cryptocurrency_type` | text | — | Ask AI |
| All | Crypto | % paid with Crypto / % with Cash | `crypto_percentage` / `cash_percentage_crypto` | text (%) | — | Ask AI |
| All | Crypto | Exchange/Conversion Method | `crypto_exchange_method` | text | — | not in draft-hash; Ask AI |
| All | Crypto | Custodian/Wallet | `crypto_custodian_wallet` | text | — | not in draft-hash |
| All | Crypto | Transaction Fees Responsibility | `crypto_transaction_fees` | select | Buyer; Seller; Split | not in draft-hash |
| All | Crypto | Timing of Transfer (+Other) | `crypto_transfer_timing` / `crypto_transfer_timing_other` | select/text | At Contract Signing; At Closing; Other | not in draft-hash |
| All | Exchange/Trade | Acceptable Exchange Item (+Other) | `exchange_item` / `other_exchange_item` | multiselect/text | Another Home; Artwork; Boat; Jewelry; Motorhome; Vehicle; Other | — |
| All | Exchange/Trade | Estimated Value | `exchange_item_value` | text ($) | — | — |
| All | Exchange/Trade | Acceptable Condition | `exchange_item_condition` | select | New; Excellent; Very Good; Good; Fair; Repair; Salvage | — |
| All | Exchange/Trade | Additional Cash Required | `additional_cash` | text ($) | — | — |
| All | Exchange/Trade | Value Determination | `value_determination` | text | — | — |
| All | Exchange/Trade | Transfer Method/Logistics | `exchange_transfer_method` | text | — | — |
| All | Exchange/Trade | Liens Disclosure (+Details) | `exchange_liens_disclosure` / `exchange_liens_details` | select/text | Yes; No | `exchange_liens` toggle orphaned (not hydrated/saved) |
| All | Exchange/Trade | Inspection/Verification Rights | `exchange_inspection_rights` | select | Yes; No | — |
| All | Lease Option | Offering Price / Monthly Payment / Duration | `lease_option_price` / `lease_option_payment` / `lease_option_duration` | text/number | — | DNA `has_lease_option` |
| All | Lease Option | Offered Option Fee (+Amt) | `has_option_fee` / `option_fee_amount` | select/text | Yes; No | — |
| All | Lease Option | Option Fee Credit (+%) | `lease_option_fee_credit` / `lease_option_fee_credit_percentage` | select/text | Yes; No; Partial | **Create: declared but NOT persisted (orphaned); Edit hydrates. Verify round-trip** |
| All | Lease Option | Conditions/Terms/Maint/Extension | `lease_option_conditions` / `lease_option_terms` / `lease_option_maintenance` / `lease_option_extension_terms` | text/select | Maint: Seller; Tenant-Buyer; Shared | maint/extension orphaned in create-save |
| All | Lease Option (seller_ variants) | duplicate hydrated-on-edit props | `seller_lease_option_fee_credit`, `_percent`, `seller_lease_option_maintenance`, `_extension_terms` | — | — | duplicate seller_* variants |
| All | Lease Purchase | Price/Payment/Duration | `lease_purchase_price` / `lease_purchase_payment` / `lease_purchase_duration` | text/number | — | DNA `has_lease_purchase` |
| All | Lease Purchase | Rent Credit (+Amount) | `lease_purchase_rent_credit` / `lease_purchase_rent_credit_amount` | select/text | Yes; No; Partial | orphaned in create-save; verify |
| All | Lease Purchase | Deposit | `lease_purchase_deposit` | text ($) | — | orphaned in create-save |
| All | Lease Purchase | Conditions/Terms/Maint/Extension | `lease_purchase_conditions` / `lease_purchase_terms` / `lease_purchase_maintenance` / `lease_purchase_extension_terms` | text/select | Maint: Seller; Tenant-Buyer; Shared | maint/extension orphaned in create-save |
| All | Lease Purchase (seller_ variants) | duplicate hydrated-on-edit props | `seller_lease_purchase_rent_credit`(+`_type` def '$', `_amount`), `seller_lease_purchase_deposit`, `_maintenance`, `_extension_terms`, `lease_purchase_option_fee`(+`_amount`) | — | — | seller_* variants |
| All | NFT | Acceptable NFT | `nft_description` | text | — | — |
| All | NFT | % as NFT / % as Cash | `nft_percentage` / `cash_percentage_nft` | number (%) | — | — |
| All | NFT | Valuation/Transfer Method | `nft_valuation_method` / `nft_transfer_method` | text | — | **orphaned in create-save (declared, not persisted); verify** |
| All | NFT | Gas Fees Responsibility | `nft_gas_fees` | select | Buyer; Seller; Split | **orphaned in create-save; verify** |
| All | Seller Financing | Purchase Price | `purchase_price` | text ($) | — | — |
| All | Seller Financing | Down Payment (type/amt) | `down_payment_type` / `down_payment_amount` | select/text | %; $ | default '%' |
| All | Seller Financing | Seller Financing (type/amt) | `seller_financing_type` / `seller_financing_amount` / `seller_down_payment_amount` | select/text | %; $ | DNA `has_seller_financing`; `seller_down_payment_amount` not in draft-hash |
| All | Seller Financing | Interest Rate / Loan Duration | `interest_rate` / `loan_duration` | number/text | — | — |
| All | Seller Financing | Prepayment Penalty (+Amt) | `prepayment_penalty` / `prepayment_penalty_amount` | select/text | Yes; No | `prepayment_penalty` toggle excluded from public view |
| All | Seller Financing | Balloon Payment (+Amt/Date) | `balloon_payment` / `balloon_payment_amount` / `balloon_payment_date` | select/text | Yes; No | `balloon_payment` toggle orphaned in create-save |
| All | Seller Financing | Amortization Type (+Other) | `seller_amortization_type` / `seller_amortization_other` | select/text | Fully Amortizing; Interest-Only; Other | not in draft-hash |
| All | Seller Financing | Payment Frequency (+Other) | `seller_payment_frequency` / `seller_payment_frequency_other` | select/text | Monthly; Bi-Weekly; Quarterly; Annually; Other | not in draft-hash |
| All | Seller Financing | Late Payment Fee | `seller_late_fee_amount` | text | — | not in draft-hash |

**Shared — seller-terms "Seller's Purchase Terms"** (always shown; save to meta; autopopulate; display; not matched):

| Field Label | Field Key | Input Type | Options | Ask AI |
|---|---|---|---|---|
| Initial Deposit (type/amt) | `initial_deposit_type` / `initial_deposit_requested` | select/text | $; % | Yes (amt) |
| Initial Deposit Timeframe (+Other) | `initial_deposit_timeframe` / `initial_deposit_timeframe_other` | select/text | Within 1/3/5/7/10/14 Days; At Closing; Other | Yes |
| Additional Deposit (type/amt) | `additional_deposit_type` / `additional_deposit_requested` | select/text | $; % | Yes (amt) |
| Additional Deposit Timeframe (+Other) | `additional_deposit_timeframe` / `additional_deposit_timeframe_other` | select/text | (same list) | Yes |
| Escrow Agent Preference | `escrow_agent_preference` | text | — | Yes |
| Inspection Contingency (+Period) | `inspection_contingency_preference` / `preferred_inspection_period` | select/number | Accepted; Not Accepted; Negotiable; Not Applicable | Yes |
| Appraisal Contingency (+Period) | `appraisal_contingency_preference` / `appraisal_contingency_period` | select/number | (same SELLER opts) | Yes |
| Financing Contingency (+Period) | `financing_contingency_preference` / `financing_contingency_period` | select/number | (same) | Yes |
| Sale of Buyer's Property Contingency (+Period) | `sale_of_buyer_property_contingency` / `sale_of_buyer_property_period` | select/number | (same) | Yes |
| Seller Contribution/Credit Offered (+Details) | `seller_contribution_credit_offered` / `seller_contribution_amount_details` | select/text | Yes; No | Yes |
| Possession Preference (+Details) | `possession_preference` / `possession_details` | select/text | At Closing; Day After Closing; Seller Rent Back; Negotiable; Other | display |
| Included Personal Property | `included_personal_property` | text | — | display |
| Excluded Items | `excluded_items` | text | — | display |
| Home Warranty Offered (+Details) | `home_warranty_offered` / `home_warranty_amount_details` | select/text | Yes; No | Yes (offered) |
| Additional HOA/Association Notes | `hoa_condo_association_terms` | text | — | display |
| Additional Seller Sale Terms | `additional_seller_sale_terms` | textarea | — | display |

**Shared — seller-terms "Estimated Payment Assumptions"** (collapsible; save to meta; NOT in draft-hash; consumed by mortgage calculator `buildCalcData`; panel does not auto-open on edit — `showPaymentAssumptions` orphaned):
`payment_down_payment_pct`, `payment_interest_rate`, `payment_loan_term`, `payment_annual_property_taxes`, `payment_monthly_insurance`, `payment_pmi_rate`, `payment_hoa_fee_amount`, `payment_hoa_fee_frequency` (Monthly/Quarterly/Annually), `payment_show_buydown_options` (checkbox, default true).

**Shared — broker-compensation tab** (shared component; in the Seller flow ONLY the "Buyer's Broker Commission Structure" group renders — the rest is wrapped in `@if($user_type !== 'seller')` and is HIDDEN, though still validated & matched):

| Visibility | Field Label | Field Key | Input Type | Options | Required? | Matching? | Notes |
|---|---|---|---|---|---|---|---|
| **Visible** | Buyer's Broker Commission Structure | `commission_structure` | select | Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission; Seller to Pay Buyer's Broker Separately; No Compensation Offered to the Buyer's Broker | No | **Yes** (terms) | Only always-visible broker field |
| Visible | Buyer's Broker Commission Fee (type) | `commission_structure_type` | select | Percentage of the Total Purchase Price; Flat Fee; other | Cond. required | **Yes** | shown when structure set |
| Visible | — Flat / % / combo / Other sub-values | `commission_structure_type_fee_flat`, `_fee_percentage`, `_fee_percentage_combo`, `_fee_flat_combo`, `_fee_other` | text/number | — | Cond. | Partial | combo option commented out |
| **Hidden** | Seller's Broker Purchase Fee (type + subs) | `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_percentage`, `purchase_fee_percentage_combo`, `purchase_fee_flat_combo`, `purchase_fee_other` | select/text/number | percentage; flat; combo; other | Cond. required (rules) | **Yes** (grp #1) | **Disconnect: matched & validated but hidden from seller UI** |
| Hidden (Income/Comm/Business) | Nominal Consideration Fee | `nominal` | text ($) | — | No | **Yes** (grp #2) | cleared unless type∈{Income,Commercial,Business} |
| Hidden | Interested in Offering a Lease Agreement | `interested_purchase_fee_type` | select | Yes; No | No | **Yes** (grp #5 parent) | gates seller-leasing group |
| Hidden | Seller's Broker Leasing Fee (type + ~20 subs) | `seller_leasing_fee_type` + `seller_leasing_gross*`/`sales_tax*` set | select/text/number | Res/Income/VL: % Rent Due Each Period; % Gross Lease Value; % First Month's Rent; Flat Fee; other. Commercial/Business: % Net Aggregate Rent; % Gross Rent; % Month's Rent; Flat Fee | Cond. required (rules) | **Yes** (grp #5a) | cleared on property-type change (create only) |
| Hidden | Interested in Lease-Option Agreement | `interested_lease_option_agreement` | select | Yes; No | No | **Yes** (grp #6 parent) | — |
| Hidden | Lease-Option creation fee (type/value) | `lease_type` / `lease_value` | select/text | percent; flat | No | **Yes** (grp #6a) | defaults 'percent' |
| Hidden | Purchase-option exercise fee (type/value) | `purchase_type` / `purchase_value` | select/text | percent; flat | No | **Yes** (grp #6b) | — |
| Hidden | Protection Period Timeframe (Days) | `protection_period` | number | — | No | **Yes** (grp #13) | — |
| Hidden | Early Termination Fee (+Amount) | `early_termination_fee_option` / `early_termination_fee_amount` | select/text | yes; no | No | **Yes** (grps #7/#8) | — |
| Hidden | Retainer Fee (+Amount +Application) | `retainer_fee_option` / `retainer_fee_amount` / `retainer_fee_application` | select/text | yes; no / Applied Toward…; Charged in Addition… | No | **Yes** (grps #9/#10/#11) | — |
| Hidden | Seller's Broker's Share of Retained Deposits | `retained_deposits` | number (%) | — | No | **Yes** (grp #12) | — |
| Hidden | Seller Agency Agreement Timeframe (+Custom) | `agency_agreement_timeframe` / `agency_agreement_custom` | select/text | 3/6/9/12 Months; Other | No | **Yes** (grp #14) | — |
| Hidden | Acceptable Brokerage Relationship | `brokerage_relationship` | select | Transaction Broker; Single Agent; Dual Agency; No Brokerage Relationship | No | **Yes** (grp #15) | Legacy Offer-Listing identifier key |
| Hidden | Additional Terms | `additional_details_broker` | textarea | — | No | No | — |
| (referral) | Referral Fee (%) | `referral_fee_percent` | — | — | — | **Yes** (grp #16) | native referral columns exist; agent-created only |

**Shared — tax-legal-hoa-disclosures tab** (all save to meta; autopopulate; display publicly; not matched):

| Field Label | Field Key | Input Type | Options | Ask AI |
|---|---|---|---|---|
| Parcel ID / Folio Number | `parcel_id` | text | — | Yes |
| Tax Year | `tax_year` | text | — | Yes |
| Annual Property Taxes | `annual_property_taxes` | text ($) | — | Yes |
| Additional Parcels Included | `additional_parcels` | select | Yes; No; Unknown | Yes |
| Total Number of Parcels | `total_parcel_count` | number (min 2) | — | Yes |
| Additional Parcel IDs | `additional_parcel_ids` | textarea | — | display |
| Legal Description | `legal_description` | textarea | — | Yes |
| Flood Zone Designation (+Other) | `flood_zone_code` / `flood_zone_code_other` | select/text | X; AE; A; AH; AO; VE; V; D; Unknown; Other | Yes |
| Flood Insurance Required | `flood_insurance_required` | select | Yes; No; Unknown | Yes |
| FEMA Panel/Map Number | `flood_zone_panel` | text | — | Yes |
| Flood Zone Determination Date | `flood_zone_date` | text | — | Yes |
| Community Development District (CDD) | `has_cdd` | select | Yes–subject to CDD; No; Unknown | Yes |
| Annual CDD Fee | `annual_cdd_fee` | text ($) | — | Yes |
| Special Assessments | `has_special_assessments` | select | Yes–outstanding/pending; No; Unknown | Yes |
| Special Assessment Amount / Description | `special_assessment_amount` / `special_assessment_description` | text/textarea | — | Yes |
| Is there an HOA/Community Association? | `has_hoa` | select | Yes; No; Unknown | Yes |
| Association Type (+Other) | `association_type` / `association_type_other` | select/text | HOA; Condominium; POA; Co-op; Community; Master; Commercial; Other | display |
| Association Name | `association_name` | text | — | Yes |
| Association Fee Amount | `association_fee_amount` | text ($) | — | Yes |
| Fee Frequency (+Other) | `association_fee_frequency` / `association_fee_frequency_other` | select/text | Monthly; Bi-Monthly; Quarterly; Semi-Annually; Annually; One-Time; Other | Yes |
| Association Approval Required (+Process +App Fee) | `association_approval_required` / `association_approval_process` / `association_application_fee` | select/textarea/text | Yes; No; Unknown | Yes |
| Fee Includes (+Other) | `association_fee_includes` / `association_fee_includes_other` | multiselect/text | Cable TV; Common Area Maint.; Community Pool; …; Water; Other | Yes |
| Association Amenities (+Other) | `association_amenities` / `association_amenities_other` | multiselect/text | Basketball Court; Clubhouse; …; Waterfront Access; Other | display |
| Leasing/Rental Restrictions | `leasing_restrictions` | select | Yes; No; Not Applicable; Unknown | Yes |
| Min Lease Period (+Other) | `min_lease_period` / `min_lease_period_other` | select/text | 1 Week … 2 Years; Other | display |
| Max Leases Per Year | `max_leases_per_year` | number | — | display |
| Additional Leasing Restrictions | `additional_lease_restrictions` | textarea | — | display |
| Pet Restrictions (+Detail) | `pet_restrictions` / `pet_restrictions_detail` (also hidden `breed_restrictions`) | text | — | Yes (`pet_restrictions`) |

**Shared — documents-disclosures tab:**

| Field Label | Field Key | Input Type | Options | Saves | Notes |
|---|---|---|---|---|---|
| Document rows (repeatable) | `doc_rows[]` (`{type, custom_type, description, file_path, original_name}`) | file+select+text | 26 types incl. Appraisal Report; Seller Disclosure; Other | Yes (meta, JSON) | Alpine `wire:ignore`; custom_type when type=Other |
| (legacy) individual disclosure flags | `seller_disclosure_available`, `survey_available`, `inspection_report_available`, `hoa_condo_docs_available`, `flood_disclosure_available`, `lead_based_paint_disclosure`, `environmental_report_available` | — | — | Yes (meta) | `seller_disclosure_available` = legacy identifier key |
| disclosure file paths (7) | `*_file_path` | path strings | — | Yes (meta) | temp `*_file` uploads not persisted directly |
| additional documents | `additional_documents[]`, `other_document_type`, `listing_documents` | file/text | — | Yes (meta) | `listing_documents` = legacy identifier key |

**Shared — photos-tours-documents tab:**

| Field Label | Field Key | Input Type | Options | Saves | Public | Notes |
|---|---|---|---|---|---|---|
| Property Photos | `propertyPhotos` (via `newPropertyPhotos`) → meta `property_photos` | file (multi, max 50) | jpg/jpeg/png/webp | Yes (meta, JSON) | Yes | reorder/cover; legacy identifier key |
| Virtual Tour URL | `videoTourUrl` → meta `video_tour_url` | url | — | Yes (meta) | Yes | **Label/key inversion** |
| 3D Tour URL | `virtualTourUrl` → meta `virtual_tour_url` | url | — | Yes (meta) | Yes | binds "3D" label to `virtualTourUrl` |

**Shared — Ask AI KB & Services (wizard shell):**

| Field Label | Field Key | Input | Saves | Ask AI | Matching | Notes |
|---|---|---|---|---|---|---|
| Listing AI FAQ answers | `listing_ai_faq.{key}` | textarea (keys from `config/ai_faq_seller.php`) | meta `listing_ai_faq` (JSON) | **Yes (KB)** | No | universal + per-type question groups |
| Services / Other services / Photo enhancements | `enable`, `fees`, `other_services`, `custom_services`, `photo_enhancements`, `custom_enhancement` | checkbox/fee | meta (each) | Partial | **Yes (services + photo_enhancements + other_services)** | core matching input |

### Seller — Residential

Inherits all Shared fields. `financial-details` renders nothing for Residential. Type-gated fields:

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key | Input Type | Options / Allowed Values | Required? | Saves to DB? | Autopop.? | Public? | Matching? | Ask AI? | DNA? | Notes / Issues |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Seller Offer | Residential | property-preferences | Property Style | `property_items` | select | ½ Duplex; 1/3 Triplex; 1/4 Quadplex; Condo-Hotel; Condominium; Dock-Rackominium; Farm; Garage Condo; Manufactured Home-Post 1977; Mobile Home-Pre 1976; Modular Home; Single Family Residence; Townhouse; Villa | Yes | Yes (meta) | Yes | Yes | No | Yes | Yes | — |
| Seller Offer | Residential | property-preferences | Property Condition | `condition_prop` | select | `$property_condition_seller` (config) | Yes (≠VL) | Yes (meta) | Yes | Yes | No | No | Yes (DNA `property_condition`) | — |
| Seller Offer | Residential | property-preferences | Bedrooms (+Other) | `bedrooms` / `other_bedrooms` | select/number | 1–10; Other | Yes | Yes (meta) | Yes | Yes | No | Yes | Yes (DNA `bedrooms`) | search filter key |
| Seller Offer | Residential/Business/Commercial | property-preferences | Bathrooms (+Other) | `bathrooms` / `other_bathrooms` | select/number | `config(property_types.bathroom_options)`; Other | Yes | Yes (meta) | Yes | Yes | No | Yes | Yes (DNA `bathrooms`) | search filter key |
| Seller Offer | Residential/Business/Commercial | property-preferences | Heated SqFt | `minimum_heated_square` | text | — | No | Yes (meta) | Yes | Yes | No | Yes | Yes (DNA `minimum_sqft`) | — |
| Seller Offer | Residential/etc (≠VL) | property-preferences | Total SqFt | `total_square_feet` | text | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Residential/etc (≠VL) | property-preferences | SqFt Heated Source | `sqft_heated_source` | select | Appraisal; Builder; Measured; Owner Provided; Public Records | No | Yes (meta) | Yes | Yes | No | No | No | not in draft-hash |
| Seller Offer | Residential/etc | property-preferences | Appliances Included (+Other) | `appliances` / `other_appliances` | multiselect/text | Bar Fridge; …; Wine Refrigerator; None; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | `showOtherAppliances` derived |
| Seller Offer | Residential | property-preferences | Carport (+Spaces) | `carport_needed` / `other_carport_needed` | select/number | Yes; No | No | Yes (meta) | Yes | Yes | No | Yes | Yes (DNA `has_carport`) | — |
| Seller Offer | Residential | property-preferences | Garage (+Spaces) | `garage_needed` / `other_garage_needed` | select/number | Yes; No | No | Yes (meta) | Yes | Yes | No | Yes | Yes (DNA `has_garage`) | — |
| Seller Offer | Residential or Income | property-preferences | Pool (+Type Private/Community) | `pool_needed` / `pool_type.private` / `pool_type.community` | select/checkbox | Yes; No | No | Yes (meta, JSON) | Yes | Yes | No | Yes | Yes (DNA `has_pool`) | — |
| Seller Offer | Residential | property-preferences | Age-Restricted Community | `leasing_55_plus` | select | Not Age-Restricted; 55+ Community; 62+ Community | No | Yes (meta) | Yes | Yes | No | No | Yes (DNA `is_55_plus`) | — |
| Seller Offer | Residential/Income | property-preferences | Amenities & Property Features (+Other) | `non_negotiable_amenities` / `other_non_negotiable_amenities` | multiselect/text | Residential list: Accessibility Features; Balcony/Patio; …; Waterfront; Other | No | Yes (meta, JSON) | Yes | Yes | No | No | No | computed getter |
| Seller Offer | Residential/Income | property-preferences (Pets) | Pets Allowed (+count/types/weight) | `pets` / `number_of_pets` / `type_of_pets` / `weight_of_pets` | select/number/text | Yes; No | No | Yes (meta) | Yes | Yes | No | Yes | Yes (DNA `pets_allowed`) | — |
| Seller Offer | Residential/Income | property-preferences | Pet Restrictions (HIDDEN `display:none`) | `breed_restrictions` | text | — | No | Yes (meta) | Yes | Yes | No | No | No | Intentionally hidden in UI |
| Seller Offer | Residential & Income | property-preferences (Construction) | Year Built | `year_built` | number (1800–2100) | — | No (rule `digits:4`) | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Residential & Income | property-preferences | Roof Type (+Other) | `roof_type` / `other_roof_type` | multiselect/text | Built-Up; Cement; Concrete; Membrane; Metal; Roof Over; Shake; Shingle; Slate; Tile; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Residential & Income | property-preferences | Exterior Construction (+Other) | `exterior_construction` / `other_exterior_construction` | multiselect/text | Asbestos; Block; Brick; …; Wood Siding; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Residential & Income | property-preferences | Foundation (+Other) | `foundation` / `other_foundation` | multiselect/text | Basement; Block; Brick/Mortar; …; Stilt/On Piling; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Residential & Income | property-preferences | Heating and Fuel (+Other) | `heating_and_fuel` / `other_heating_and_fuel` | multiselect/text | Baseboard; Central; Electric; …; Zoned; None; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Residential & Income | property-preferences | Air Conditioning (+Other) | `air_conditioning` / `other_air_conditioning` | multiselect/text | Central Air; Humidity Control; Mini-Split; Wall/Window; Zoned; None; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Residential & Income | property-preferences | Water (+Other) | `water` / `other_water` | multiselect/text | Canal/Lake For Irrigation; Private; Public; Well; Well Required; None; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Residential & Income | property-preferences | Sewer (+Other) | `sewer` / `other_sewer` | multiselect/text | Aerobic Septic; PEP-Holding Tank; Private Sewer; Public Sewer; Septic Needed; Septic Tank; None; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Residential & Income | property-preferences | Utilities (+Other) | `utilities` / `other_utilities` | multiselect/text | BB/HS Internet Available; Cable Available; …; Water Connected; None; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | — |

#### Summary – Seller Residential
- **Total fields found:** ~250 (≈200 shared + ~30 Residential-gated; financial-details renders nothing).
- **Required:** first/last name, phone, email, listing_title, listing_date, expiration_date, auction_type, auction_time (if Bidding), property_type, property_items, condition_prop, bedrooms, bathrooms, sale_provision, target_closing_date, occupant_status, maximum_budget (non-Bidding), address, property_city/state/zip/county + conditional broker/leasing fee sub-fields.
- **Conditional:** all financing sub-sections, pool/pets, carport/garage spaces, contingency periods, HOA/CDD/assessment sub-fields, broker-fee sub-values.
- **Saved to DB:** nearly all via EAV meta; only `title`+`address` native.
- **Displayed publicly:** most; excluded — current_status, photo, video_link, state, prepayment_penalty toggle, other_preferences, listing_ai_faq.
- **Used in matching:** broker-comp term groups + services/photo_enhancements only. No property attributes.
- **Used in Ask AI:** ~90 keys incl. beds/baths/sqft/pool/garage/appliances/interior/roof/HOA/flood/tax/financing/warranty + FAQ.
- **Suitable for metadata tagging:** property_type, property_items, condition_prop, bedrooms, bathrooms, minimum_heated_square, offered_financing, sale_provision, view_preference, waterfront, pool_needed, leasing_55_plus, target_closing_date.
- **Incomplete/disconnected:** hidden-but-matched broker fee group; orphaned NFT/lease-option/lease-purchase props; `showPaymentAssumptions` won't auto-open on edit; undeclared dynamic `cities`.
- **May need review:** `maximum_budget` vs `desired_sale_price` key mismatch; Edit lacks conditional-clearing hooks; photo/video read from meta while native columns ignored.

### Seller — Income

Inherits Shared + Residential-shared construction systems / pool / pets / amenities (rendered for `Residential || Income`) + Income-specific fields + Income financial-details.

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key | Input Type | Options / Allowed Values | Required? | Saves? | Autopop.? | Public? | Match? | Ask AI? | DNA? | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Seller Offer | Income | property-preferences | Property Style | `property_items` | select | Duplex; Five or More; Quadplex; Triplex | Yes | Yes (meta) | Yes | Yes | No | Yes | Yes | — |
| Seller Offer | Income | property-preferences | Appliances (+Other) | `appliances`/`other_appliances` | multiselect | (as Residential) | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Income | property-preferences | Amenities & Features (+Other) | `non_negotiable_amenities`/`other_…` | multiselect | Residential list | No | Yes (meta) | Yes | Yes | No | No | No | — |
| Seller Offer | Income | property-preferences | Pool / Pets | (as Residential) | — | — | No | Yes (meta) | Yes | Yes | No | Yes | Yes | shown Residential\|\|Income |
| Seller Offer | Income | property-preferences (Construction) | Year Built / Roof / Exterior / Foundation / Heating / A/C / Water / Sewer / Utilities | (same keys as Residential) | multiselect/number | (same lists) | No | Yes (meta) | Yes | Yes | No | Yes | No | rendered Residential\|\|Income |
| Seller Offer | Income | property-preferences | Total Number of Units | `unit_number` | number | — | No | Yes (meta) | Yes | Yes | No | Yes (→total_units) | No | — |
| Seller Offer | Income | property-preferences | Total Number of Buildings | `unit_buildings` | number | — | No | Yes (meta) | Yes | Yes | No | Yes (→total_buildings) | No | — |
| Seller Offer | Income | property-preferences | Unit Type Configurations (repeater) | `unit_type_configurations[]` | repeater | per-row: `unit_type` (1 Bed/1 Bath; …; Studio; Other), `beds_unit`, `baths_unit`, `garage_spaces`, `carport_spaces`, `other_spaces`, `number_of_units`, `number_occupied`, `expected_rent`, `unit_type_description`, `sqft_heated` | No | Yes (meta, JSON) | Yes (if non-empty) | Yes | No | Yes (→unit_mix_summary) | No | only dynamic add/remove group; legacy single-unit block commented out |
| Seller Offer | Income | property-preferences | Number of Water Meters | `number_water_meters` | number | — | No | Yes (meta) | Yes | Yes | No | No | No | rule `integer\|min:0` |
| Seller Offer | Income | property-preferences | Number of Electric Meters | `number_electric_meters` | number | — | No | Yes (meta) | Yes | Yes | No | No | No | — |
| Seller Offer | Income | property-preferences | Property Condition | `condition_prop` | select | config list | Yes | Yes (meta) | Yes | Yes | No | No | Yes | — |
| Seller Offer | Income | financial-details | Annual Net Income | `minimum_annual_net_income` | text ($) | — | No | Yes (meta) | Yes | Yes | No | Yes (→annual_noi) | No | — |
| Seller Offer | Income | financial-details | Cap Rate | `minimum_cap_rate` | text (%) | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Income | financial-details | Gross Annual Income | `gross_annual_income` | text ($) | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Income | financial-details | Annual Operating Expenses | `annual_operating_expenses` | text ($) | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Income | financial-details | Rent Roll Available | `rent_roll_available` | select | Yes; No | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Income | financial-details | Operating Statement Available | `operating_statement_available` | select | Yes; No | No | Yes (meta) | Yes | Yes | No | Yes | No | — |

#### Summary – Seller Income
- **Total fields:** ~265. **Required:** as Residential minus bedrooms (Residential-only); financial-details all optional.
- **Conditional:** unit_type_configurations repeater, pool/pets, all shared conditionals.
- **Saved:** all via meta. **Public:** units, unit mix, financials display.
- **Matching:** broker/services only. **Ask AI:** rich — units, unit_mix_summary, NOI, cap rate, gross income, op-ex, rent roll, operating statement.
- **Metadata-suitable:** property_items, unit_number, unit_buildings, minimum_cap_rate, minimum_annual_net_income.
- **Incomplete/disconnected:** hidden-but-matched broker fee; legacy commented single-unit block; unit_type_configurations hydrates only if non-empty.
- **Needs review:** bathrooms/heated-sqft gated to Residential/Business/Commercial (NOT top-level Income), yet Income units capture per-unit beds/baths — verify intentional.

### Seller — Commercial Sale (code `Commercial`)

Inherits Shared + Commercial construction block + garage/parking + Commercial financial-details. Match catalog resolves via `str_contains('commercial')`.

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key | Input Type | Options / Allowed Values | Required? | Saves? | Autopop.? | Public? | Match? | Ask AI? | DNA? | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Seller Offer | Commercial | property-preferences | Property Style | `property_items` | select | Agriculture; Assembly Building; Business; Five or More; Hotel/Motel; Industrial; Mixed Use; Office; Restaurant; Retail; Warehouse | Yes | Yes (meta) | Yes | Yes | No | Yes | Yes | same list as Business |
| Seller Offer | Commercial | property-preferences | Business Type (+Other) | `business_type` / `other_business_type` | select/text | Aeronautical; Agriculture; …; Warehouse; Wholesale; Other | No | Yes (meta) | Yes | Yes | No | No | No | container JS `d-none` toggled |
| Seller Offer | Commercial | property-preferences | Bathrooms (+Other) | `bathrooms`/`other_bathrooms` | select/number | config; Other | Yes | Yes (meta) | Yes | Yes | No | Yes | Yes | Res/Business/Commercial |
| Seller Offer | Commercial | property-preferences | Heated SqFt / Total SqFt / SqFt Source | `minimum_heated_square` / `total_square_feet` / `sqft_heated_source` | text/select | — / Appraisal;Builder;Measured;Owner Provided;Public Records | No | Yes (meta) | Yes | Yes | No | Yes | Yes | — |
| Seller Offer | Commercial | property-preferences | Appliances (+Other) | `appliances`/`other_appliances` | multiselect | (as Residential) | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | property-preferences | Garage/Parking Features (+Other) | `garage_parking_spaces_option` / `other_parking_space_wrapper` | multiselect/text | 1 to 5 Spaces; 6 to 12; …; Airplane Hangar; EV Charging Station(s); Valet; None; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes (→parking) | No | Business/Commercial |
| Seller Offer | Commercial | property-preferences | Amenities & Features (+Other) | `non_negotiable_amenities` / `other_…` | multiselect | Commercial list: Access to Public Transportation; Business Center; …; Warehouse Space; Other | No | Yes (meta) | Yes | Yes | No | No | No | — |
| Seller Offer | Commercial | property-preferences | Included Property/Business Assets (+Other) | `business_assets` / `assets_other` | multiselect/text | FF&E; Advertising Materials; Contract Rights; Leases; Licenses; Rights under Agreement; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | Business/Commercial/Income |
| Seller Offer | Commercial | property-preferences | Property Condition | `condition_prop` | select | config | Yes | Yes (meta) | Yes | Yes | No | No | Yes | — |
| Seller Offer | Commercial | property-preferences (Construction) | Year Built / Zoning | `year_built` / `zoning` | number/text | — | No | Yes (meta) | Yes | Yes | No | Yes (zoning) | No | — |
| Seller Offer | Commercial | property-preferences | Roof/Exterior/Foundation (+Other) | `roof_type`/`exterior_construction`/`foundation` | multiselect | (same lists) | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | property-preferences | Road Frontage (+Other) | `road_frontage` / `other_road_frontage` | multiselect | Access Road; Alley; …; State Road; Turn Lanes; None; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | property-preferences | Road Surface Type (+Other) | `road_surface_type` / `other_…` | multiselect | Asphalt; Brick; Chip And Seal; …; Unimproved; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | property-preferences | Utilities/Water/Sewer (+Other) | `utilities`/`water`/`sewer` | multiselect | Commercial utilities list; (water/sewer same) | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | property-preferences | Heating/A-C (+Other) | `heating_and_fuel`/`air_conditioning` | multiselect | + Central Building/Central Individual / + A/C Office Only | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | property-preferences | Electrical Service (+Other) | `electrical_service` / `other_…` | multiselect | 1 Phase (3-Wire); 3 Phase; 110/220/440 Volts; Separate Meter; None; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | property-preferences | Ceiling Height | `ceiling_height` | select | Under 8 ft; 8–10; 11–14; 15–18; 19–22; Over 22 ft | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | property-preferences | Building Features (+Other) | `building_features` / `other_…` | multiselect | Bathrooms; Clear Span; Columns; …; Truck Well; Waiting Room; Other | No | Yes (meta) | Yes | Yes | No | Yes (drives `furnished`) | No | — |
| Seller Offer | Commercial | financial-details | Annual Net Income / Cap Rate | `minimum_annual_net_income` / `minimum_cap_rate` | text | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | financial-details | Price Per Square Foot | `price_per_sqft` | text ($) | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | financial-details | Existing Lease Type (+Other) | `existing_lease_type` / `other_lease_type` | select/text | NNN; NN; Net; Gross; Modified Gross; Absolute Net; Ground Lease; No Existing Lease; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | financial-details | Lease Expiration Date | `lease_expiration` | date | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Commercial | financial-details | Lease Assignable to Buyer | `lease_assignable` | select | Yes; No; Negotiable | No | Yes (meta) | Yes | Yes | No | Yes | No | — |

#### Summary – Seller Commercial Sale
- **Total fields:** ~270. **Required:** shared required + property_items, bathrooms, condition_prop.
- **Conditional:** business_type (JS d-none), garage/parking, financial-details block, all shared conditionals.
- **Saved:** all via meta. **Public:** construction, zoning, ceiling height, building features, lease details, cap rate, price/sqft.
- **Matching:** Commercial services catalog + broker terms only. **Ask AI:** parking, NOI, price/sqft, lease type/expiration/assignable, zoning, building_features (→furnished), utilities/water/sewer.
- **Metadata-suitable:** property_items, business_type, existing_lease_type, price_per_sqft, minimum_cap_rate, zoning, ceiling_height.
- **Incomplete/disconnected:** `business_type` JS-toggled (not in draft-hash); hidden-but-matched broker fee; commercial seller-leasing-fee block has no "Other" option.
- **Needs review:** `condition_prop` required for Commercial but may not apply conceptually — confirm.

### Seller — Business Opportunity (code `Business`)

Inherits Shared + Business construction/systems + garage/parking + the largest financial-details block.

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key | Input Type | Options / Allowed Values | Required? | Saves? | Autopop.? | Public? | Match? | Ask AI? | DNA? | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Seller Offer | Business | property-preferences | Property Style | `property_items` | select | Agriculture; Assembly Building; Business; Five or More; Hotel/Motel; Industrial; Mixed Use; Office; Restaurant; Retail; Warehouse | Yes | Yes (meta) | Yes | Yes | No | Yes | Yes | — |
| Seller Offer | Business | property-preferences | Business & Real Estate Purchase Requirements | `real_estate_purchase` | select | Real Estate Building and Business; Business Only | Yes | Yes (meta) | Yes | Yes | No | Yes | No | Business-required |
| Seller Offer | Business | property-preferences | Business Type (+Other) | `business_type` / `other_business_type` | select/text | (long `$business_type` list) | No | Yes (meta) | Yes | Yes | No | Yes | No | JS d-none |
| Seller Offer | Business | property-preferences | Bathrooms / Heated SqFt / Total SqFt / Source | `bathrooms`/`minimum_heated_square`/`total_square_feet`/`sqft_heated_source` | select/text | — | Yes (bath) | Yes (meta) | Yes | Yes | No | Yes | Yes | — |
| Seller Offer | Business | property-preferences | Appliances (+Other) | `appliances`/`other_appliances` | multiselect | (as Residential) | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | property-preferences | Garage/Parking Features (+Other) | `garage_parking_spaces_option`/`other_parking_space_wrapper` | multiselect | (as Commercial) | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | property-preferences | Amenities & Features (+Other) | `non_negotiable_amenities`/`other_…` | multiselect | Commercial list | No | Yes (meta) | Yes | Yes | No | No | No | — |
| Seller Offer | Business | property-preferences | Included Assets (+Other) | `business_assets`/`assets_other` | multiselect | FF&E; Advertising Materials; Contract Rights; Leases; Licenses; Rights under Agreement; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | property-preferences (Construction) | Business Name | `business_name` | text | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | property-preferences | Year Established | `year_established` | number | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | property-preferences | Licenses (+Other) | `licenses` / `other_licenses` | multiselect | Beer/Wine; Liquor; Off Site; On Site; None; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | property-preferences | Sale Includes (+Other) | `sale_includes` / `other_sale_includes` | multiselect | Business; Equipment/Fixtures; Furniture; Goodwill; Inventory; Land; Lease Agreement; Liquor License; Parking Lot; Signage; Training; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | property-preferences | Year Built / Zoning / Roof / Exterior / Foundation / Utilities / Water / Sewer / Heating / A-C / Electrical Service (+Others) | (same keys) | multiselect/number/text | (same lists) | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | property-preferences | Property Condition | `condition_prop` | select | config | Yes | Yes (meta) | Yes | Yes | No | No | Yes | — |
| Seller Offer | Business | financial-details | Annual Net Income / Cap Rate | `minimum_annual_net_income` / `minimum_cap_rate` | text | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | financial-details | Annual Revenue | `annual_revenue` | text ($) | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | financial-details | Gross Profit / SDE / EBITDA | `gross_profit` / `sde_ebitda` | text ($) | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | financial-details | Inventory Value / FF&E Value | `inventory_value` / `ffe_value` | text ($) | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | financial-details | Reason for Sale (+Other) | `reason_for_sale` / `other_reason_for_sale` | select/text | Retirement; Relocation; Health Reasons; Pursuing Other Opportunities; Partnership Dissolution; Financial Reasons; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | financial-details | Number of Employees | `employee_count` | number | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Business | financial-details | Financial Statements / Tax Returns Available | `financial_statements_available` / `tax_returns_available` | select | Yes; No | No | Yes (meta) | Yes | Yes | No | Yes | No | Yes/No — richer AI meaning wanted |
| Seller Offer | Business | financial-details | NDA Required to Access Financials | `nda_required` | select | Yes; No | No | Yes (meta) | Yes | Yes | No | Yes | No | Yes/No — semantic ("confidential financials gated by NDA") |
| Seller Offer | Business | financial-details | Is Business Location Leased? | `business_location_leased` | select | Yes; No; Not Applicable | No | Yes (meta) | Yes | Yes | No | Yes | No | gates lease sub-fields |
| Seller Offer | Business | financial-details | Underlying Lease: Monthly Rent / Expiration / Renewal / Assignable / Additional Terms | `business_lease_monthly_rent` / `business_lease_expiration` / `business_lease_renewal_options` / `business_lease_assignable` / `business_lease_additional_terms` | text/date/select | Renewal: Yes;No;Unknown / Assignable: Yes;No;Subject to Landlord Approval;Unknown | No | Yes (meta) | Yes | Yes | No | Yes | No | shown if location leased=Yes |

#### Summary – Seller Business Opportunity
- **Total fields:** ~290 (largest financial block). **Required:** shared + property_items, real_estate_purchase, bathrooms, condition_prop.
- **Conditional:** business lease sub-fields (if leased=Yes), business_type, garage/parking, all shared conditionals.
- **Saved:** all via meta. **Public:** business_name, revenue/profit/SDE/EBITDA, inventory/FF&E, licenses, sale_includes, employee_count, lease details.
- **Matching:** Business services catalog + broker terms only. **Ask AI:** near-complete business profile.
- **Metadata-suitable:** business_type, real_estate_purchase, annual_revenue, sde_ebitda, reason_for_sale, sale_includes.
- **Incomplete/disconnected:** hidden-but-matched broker fee; `business_type` not in draft-hash; nominal fee applies (Business).
- **Needs review:** `nda_required`/`financial_statements_available` Yes/No need richer AI semantics.

### Seller — Vacant Land

Inherits Shared. **No** condition_prop (skipped for VL), **no** occupant_status, **no** financial-details block, **no** amenities/appliances (gated to ≠VL). Dedicated VL property-preferences block:

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key | Input Type | Options / Allowed Values | Required? | Saves? | Autopop.? | Public? | Match? | Ask AI? | DNA? | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Seller Offer | Vacant Land | property-preferences | Property Style | `property_items` | select | Agricultural; Billboard Site; Business; Cattle; Commercial; Farm; Fishery; Highway Frontage; Horses; Industrial; Land Fill; Livestock; Mixed Use; Multi Family; Nursery; Orchard; Pasture; Poultry; Ranch; Residential; Retail; Row Crops; Sod Farm; Subdivision; Timber; Tracts; Trans/Cell Tower; Tree Farm; Unimproved Land; Well Field; Other | Yes | Yes (meta) | Yes | Yes | No | Yes | Yes | — |
| Seller Offer | Vacant Land | property-preferences | Current Use (+Other) | `current_use` / `other_current_use` | multiselect/text | Agricultural; Commercial; Industrial; Recreational; Residential; Timber; Other | No | Yes (meta, JSON) | Yes | Yes | No | Yes (VL+Business gated) | Yes (via property) | — |
| Seller Offer | Vacant Land | property-preferences | Current Adjacent Use (+Other) | `current_adjacent_use` / `other_…` | multiselect | Church; Commercial; Industrial; Mobile Home Park; Multi-Family; Park; Professional Office; Residential; Retail; School; Vacant; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Vacant Land | property-preferences | Zoning | `zoning` | text | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Vacant Land | property-preferences | Water/Sewer/Electric/Gas/Telecom Available (+Other each) | `water_available`/`sewer_available`/`electric_available`/`gas_available`/`telecom_available` (+`_other`) | select/text | Yes; No; Unknown; Nearby–Available but not connected; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Vacant Land | property-preferences | Lot Dimensions | `lot_dimensions` | text | — | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Vacant Land | property-preferences | Front Footage | `front_footage` | text | — | No | Yes (meta) | Yes | Yes | No | Yes | No | rule `numeric\|min:0` |
| Seller Offer | Vacant Land | property-preferences | Road Frontage (+Other) | `road_frontage` / `other_…` | multiselect | (commercial road-frontage list) | No | Yes (meta) | Yes | Yes | No | Yes (VL+Business) | No | — |
| Seller Offer | Vacant Land | property-preferences | Road Surface Type (+Other) | `road_surface_type` / `other_…` | multiselect | (same list) | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Vacant Land | property-preferences | Utilities (+Other) | `utilities` / `other_utilities` | multiselect | VL utilities list (BB/HS Internet; Cable; Electrical Nearby; …; Utility Pole; Water Nearby; Other) | No | Yes (meta) | Yes | Yes | No | No | No | — |
| Seller Offer | Vacant Land | property-preferences | Water / Sewer (+Other) | `water`/`sewer` | multiselect | (same lists) | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Vacant Land | property-preferences | Number of Wells / Septics | `number_of_wells` / `number_of_septics` | number | — | No | Yes (meta) | Yes | Yes | No | Yes | No | rule `integer\|min:0` |
| Seller Offer | Vacant Land | property-preferences | Fences (+Other) | `fences` / `other_fences` | multiselect | Board; Chain Link; Cross Fenced; Fenced; Split Rail; Vinyl; Wire; Wood; None; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Vacant Land | property-preferences | Vegetation (+Other) | `vegetation` / `other_vegetation` | multiselect | Brush; Cleared; Crop; Oak Trees; Partially Wooded; Pasture; Timber; Trees/Wooded; None; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Vacant Land | property-preferences | Buildable | `buildable` | select | Yes; No | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Vacant Land | property-preferences | Easements (+Other) | `easements` / `other_easements` | multiselect | Access Road; Drainage; Electric; Telephone; Utilities; Water; None; Other | No | Yes (meta) | Yes | Yes | No | Yes | No | — |
| Seller Offer | Vacant Land | property-preferences | Total Acreage / Min Acreage | `total_acreage` / `min_acreage` | select/— | acreage_options | No | Yes (meta) | Yes | Yes | No | Yes | Yes (`total_acreage`) | — |

#### Summary – Seller Vacant Land
- **Total fields:** ~230 (shared minus condition/occupant/appliances/amenities/financial + VL block).
- **Required:** first/last name, phone, email, listing_title, listing_date, expiration_date, auction_type (+auction_time if Bidding), property_type, property_items, sale_provision, target_closing_date, maximum_budget (non-Bidding), address/city/state/zip/county. (No condition_prop / occupant_status.)
- **Conditional:** VL availability `_other` pairs, all shared financing/HOA/broker conditionals.
- **Saved:** all via meta. **Public:** acreage, lot dimensions, zoning, buildable, current/adjacent use, utility availability, wells/septics, fences, vegetation, easements.
- **Matching:** VL services catalog + broker terms only. **Ask AI:** VL-gated block + shared VL+Business (current_use, road_frontage).
- **Metadata-suitable:** property_items, zoning, buildable, current_use, total_acreage, utility-availability set.
- **Incomplete/disconnected:** hidden-but-matched broker fee; VL `utilities` not in Ask AI map (verify); condition/occupant intentionally omitted.
- **Needs review:** confirm VL price uses `maximum_budget` (non-Bidding) vs `desired_sale_price` public-view key.

### Seller — cross-flow issues (all property types)
1. **Broker Purchase Fee / Seller Leasing Fee / retainer / termination / agency-agreement / brokerage-relationship fields are HIDDEN in the seller UI** (`@if($user_type !== 'seller')`) yet validated and are the primary **matching** baseline. Only `commission_structure`/`commission_structure_type` are user-visible → terms match may run on empty baselines. **Needs verification** whether these are populated for agent-created listings.
2. **Price key mismatch:** component saves `maximum_budget`; public view + calculator read `desired_sale_price` (with fallbacks). Verify sale price renders.
3. **Edit flow lacks conditional-clearing hooks** present in create → stale fee/leasing data after property-type/fee-type change on edit.
4. **Orphaned declared props** (never persisted in create-save): `nft_valuation_method`, `nft_transfer_method`, `nft_gas_fees`, `custom_enhancement`, `exchange_liens`, `meeting_Preference`, `photo_enhancements`, `openHouseCount`, `other_services_enabled`, several `lease_option_*`/`lease_purchase_*` maintenance/extension/credit fields, `balloon_payment` toggle. Edit hydrates some from meta.
5. **`showPaymentAssumptions`** panel does not auto-open on edit despite saved `payment_*` values.
6. **Undeclared dynamic `cities`/`newCity`/`citySuggestions`** — legacy/dead for seller (single `property_city` authoritative).
7. **DNA listing_type inconsistency:** observer dispatches `ComputePropertyDnaProfile::dispatch('seller', …)` and `ComputeLocationDna::dispatch('seller', …)`, while `store()` dispatches `ComputeLocationDna::dispatch('seller_agent', …)`. **Needs verification** both normalize to the same rows (controller uses `listing_type='seller_agent'`).
8. **Native columns unused:** `sqft, min_price, financings, photos, bedroom_id, bathroom_id, city_id, county_id, need_cma, video_file, audio_file, referring_agent_id` bypassed (data in meta). `config/match_scoring.php` assumes seller location in `city_id`/`county_id` native columns, but this flow writes `property_city`/`property_county` meta — a mismatch if seller service-area scoring is activated.

<!-- ============================================================= -->

## BUYER CRITERIA / BUYER OFFER LISTINGS

**Scope & storage.** Components `BuyerOfferListing` (create/draft) / `BuyerOfferListingEdit` (edit), rendering 7 tabs: **0 Listing Details · 1 Property Preferences · 2 Description · 3 Purchasing Terms · 4 Broker Compensation & Agency Agreement Terms · 5 AI Questions (Ask-AI KB) · 6 Agent Credentials & Contact Info**. Although `buyer_agent_auctions` has native content columns (`address, auction_type, concession, financing_currency, financing_approved, need_lender, preapproval_amount, additional_details, other, cash_budget, crypto_budget`), the save path writes **only `title, user_id, is_draft, is_approved, is_sold, is_paid` to native columns; everything else is EAV meta** (`buyer_agent_auction_metas`) via `saveMeta()`. Native content columns are legacy/unused. `listing_id` via `HasListingId`; referral columns set by `ReferralLinkService`, not the form.

**Server-side validation is minimal.** `store()` enforces only: `counties` (required|array|min:1), `state` (required|string), `auction_time` (required if `auction_type === 'Bidding Period'`), plus `assertImportantPlacesValid()`. Every other red-asterisk "required" is **client-side only**. Below, `Required?` = **DB** (server), **UI** (asterisk only), or **No**.

**Shared vs conditional.** Tabs 0, 2, 3, 4, 5, 6 render identically for all 5 types (except two cosmetic Broker-Comp tweaks for Residential). **All property-type differences live in Tab 1 (Property Preferences).** Location fields, Property Type/Style, Total Acreage, View Preference, and the "Property DNA Phase C" block (Purchase Purpose, Commute, HOA, Flood Zone) render for **all** types.

**Wiring notes (throughout):**
- **Matching** (`BuyerBidMatchScoreHelper`) scores buyer↔agent (services 35 + terms 35 enabled; others disabled). **No buyer property-criteria field affects the match score.**
- **Ask AI** = `AskAiKnowledgeSnapshotBuilderService::buildSilently('buyer', id)` on draft-save and submit + user-authored `listing_ai_faq`.
- **DNA**: `BuyerTenantDnaGenerator` reads `buyer_criteria_auctions` (a different table) via an observer this flow never triggers → **DNA is effectively NOT wired for offer-listing buyers**, even though Tab-1 "Purchase Purpose / Commute / HOA / Flood Zone" are code-labeled "Property DNA Phase C." Marked "No (not triggered)".
- **Public display** = `offer-listing/buyer/view.blade.php`; sections content-gated (show if populated), never property-type-gated. Broker-comp, meeting/showing, Search-Areas and Important-Places meta are **not** rendered publicly.

### Buyer — Shared Fields (All 5 Property Types — Tabs 0, 2, 3, 4, 5, 6 + shared parts of Tab 1)

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key / DB Column | Input Type | Options / Allowed Values | Required? | Saves to DB? | Autopop on Edit? | Public? | Matching? | Ask AI? | DNA? | Metadata/Tags | Notes / Issues |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Buyer | ALL | 0 Listing Details | Listing Status | `listing_status` (meta) | radio | Active/Pending/Expired | No | Yes | Yes | Yes | No | Partial | No | Good | Draft is separate flag |
| Buyer | ALL | 0 Listing Details | Listing Title | `title` (native) + `listing_title` | text | free | UI | Yes (native `title`) | Yes | Yes | No | Yes | No | Good | Only native column actually used |
| Buyer | ALL | 0 Listing Details | Listing Date | `listing_date` (meta) | date | — | UI | Yes | Yes | Yes | No | No | No | Good | Defaults to today |
| Buyer | ALL | 0 Listing Details | Expiration Date | `expiration_date` (meta) | date | — | UI | Yes | Yes | Yes | Yes | No | No | Good | Drives `status`=Expired |
| Buyer | ALL | 0 Listing Details | Listing Type | `auction_type` (meta) | select | Traditional / Bidding Period | UI (DB if Bidding) | Yes | Yes | Yes | No | No | No | Good | Forced "Traditional" when `bya_beta.bidding_period_enabled` off |
| Buyer | ALL | 0 Listing Details | Bidding Period Length | `auction_time` (meta) | select | period options | **DB (if Bidding Period)** | Yes | Yes | Yes (timer) | No | No | No | Good | Cleared unless Bidding Period |
| Buyer | ALL | 1 Prop Pref / Location | Acceptable Counties | `counties` (meta, JSON) | autocomplete tags | US counties | **DB (min 1)** | Yes | Yes | Yes | No | Only if service_area enabled (disabled) | No | Good | Mirrored from Search-Areas blob |
| Buyer | ALL | 1 Prop Pref / Location | Acceptable State | `state` (meta) | autocomplete | US states | **DB** | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 1 Prop Pref / Search Areas | Search Areas (LDNA map) | `location_dna_preferences` (meta) ← `location_dna_preferences_json` | map widget/JSON | cities/counties/state blob | No | Yes | Yes | Map component only | No | No | No | Blob | Also syncs `cities` meta; not a raw public field |
| Buyer | ALL | 1 Prop Pref / Important Places | Important Places | `important_places_json` (meta) | repeatable rows | label/address/etc | Partial-row guard (DB) | Yes | Yes | No | No | No | No | Blob | `HasImportantPlaces` trait; not shown publicly |
| Buyer | ALL | 1 Prop Pref | Acceptable Property Type | `property_type` (meta) | select | Residential/Income/Commercial/Business/Vacant Land | UI | Yes | Yes | Yes | Yes | No (only selects services catalog) | No (would) | Good | Drives all Tab-1 conditionals |
| Buyer | ALL | 1 Prop Pref | Acceptable Property Style | `property_items` (meta, JSON) | select2 multi | per-type option lists | UI | Yes | Yes | Yes | Yes | No | Yes (style) | No (would) | Good | Options gated by type |
| Buyer | ALL | 1 Prop Pref | Other Property Style | `other_property_items` | text | free | No | Yes | Yes | Yes | No | No | No | Good | Companion; auto-adds "Other" to items |
| Buyer | ALL | 1 Prop Pref | Minimum Total Acreage | `total_acreage` (meta) | select | acreage ranges | No | Yes | Yes | Yes | No | Yes | No | Good | Shown for all types |
| Buyer | ALL | 1 Prop Pref | View Preference | `view_preference` (meta, JSON) | select2 multi | preference list + Other | No | Yes | Yes | Yes | No | Yes (→water_view) | No (would) | Good | — |
| Buyer | ALL | 1 Prop Pref | Other Preferences | `other_preferences` | text | free | No | Yes | Yes | Yes | No | No | No | Good | Companion |
| Buyer | ALL | 1 Prop Pref / DNA Phase C | Purchase Purpose | `purchase_purpose` (meta) | select | Primary Residence/Vacation/Second Home/Investment/Business Use/Development/Other | No | Yes | Yes | Yes | No | Yes | **No (not triggered)** | Good tag | Labeled DNA but generator reads a different table |
| Buyer | ALL | 1 Prop Pref / DNA Phase C | Purchase Purpose Other | `purchase_purpose_other` | text | free | No | Yes | Yes | Partial | No | No | No | Good | Companion |
| Buyer | ALL | 1 Prop Pref / DNA Phase C | Work/School ZIP | `commute_destination_zip` | text | ZIP | No | Yes | Yes | Yes | No | Yes | No (skip) | Good | Feeds Location-DNA commute, not BuyerTenantDna |
| Buyer | ALL | 1 Prop Pref / DNA Phase C | Max Commute Minutes | `max_commute_minutes` | number | minutes | No | Yes | Yes | Yes | No | Yes | No (skip) | Good | — |
| Buyer | ALL | 1 Prop Pref / DNA Phase C | Commute Mode | `commute_mode` | select | Drive/Transit/Walk/Bike/Remote | No | Yes | Yes | Yes | No | Yes | No | Good | — |
| Buyer | ALL | 1 Prop Pref / DNA Phase C | HOA Acceptance | `hoa_acceptance` | select | Yes/No/Flexible | No | Yes | Yes | Yes | No | Yes | No (skip) | Good | Yes/No — richer AI meaning possible |
| Buyer | ALL | 1 Prop Pref / DNA Phase C | Max HOA Monthly Fee | `hoa_max_monthly_fee` | number | $ | No | Yes | Yes | Yes | No | Yes | No | Good | Shown when HOA Yes/Flexible |
| Buyer | ALL | 1 Prop Pref / DNA Phase C | Flood Zone Preference | `flood_zone_tolerance` (JSON) | select2 multi | No Preference/Outside/Affordable Ins/Any/Other | No | Yes | Yes | Yes | No | Yes | No | Good | — |
| Buyer | ALL | 1 Prop Pref / DNA Phase C | Flood Zone Other | `flood_zone_tolerance_other` | text | free | No | Yes | Yes | Partial | No | No | No | Good | Companion |
| Buyer | ALL | 2 Description | Buyer Description | `additional_details` (meta) | textarea | free | No | Yes | Yes | Yes | No | Yes (→description) | No | Good | Primary narrative for Ask AI |
| Buyer | ALL | 3 Purchasing Terms | Acceptable Special Sale Provisions | `sale_provision` (JSON) | select2 multi | provisions list + Other | No | Yes | Yes | Yes | No | No | No | Good | `updatedSaleProvision` resets dependents |
| Buyer | ALL | 3 Purchasing Terms | Sale Provision Other | `sale_provision_other` | text | free | No | Yes | Yes | Yes | No | No | No | Good | Companion |
| Buyer | ALL | 3 Purchasing Terms | Buyer Open to Assignment Contract | `sale_provision_assignment` | select | Yes/No | No | Yes | Yes | Yes | No | No | No | Good | Yes/No |
| Buyer | ALL | 3 Purchasing Terms | Assignment Fee Type / Amount | `assignment_fee_type` / `assignment_fee_amount` | select/number | $ (Flat) / % of Assignment Value | No | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 3 Purchasing Terms | Buyer Sell Contract | `buyer_sell_contract` | select | (assignment sub) | No | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 3 Purchasing Terms | Target Closing Timeframe | `target_closing_date` | select | ASAP / Within N Months | No | Yes | Yes | Yes | No | Yes (→closing_date) | No | Good | — |
| Buyer | ALL | 3 Purchasing Terms | Maximum Budget | `maximum_budget` (meta) | text $ | — | UI | Yes | Yes | Yes (hero) | No | Yes (→max_price) | Yes (budget) | Good | Hero price on public page |
| Buyer | ALL | 3 Purchasing Terms | Offered Financing/Currency | `offered_financing` (JSON) | select2 multi | Cash/Seller Financing/Assumable/Exchange-Trade/Lease Option/Lease Purchase/Cryptocurrency/NFT/Other | UI | Yes | Yes | Yes (badges) | No | Yes (→financing_type via legacy) | No (would) | Good | Drives financing sub-blocks; `updatedOfferedFinancing` smart-reset |
| Buyer | ALL | 3 Purchasing Terms | Other Financing | `other_financing` | text | free | No | Yes | Yes | Yes | No | No | No | Good | Companion |
| Buyer | ALL | 3 PT / Cash | Buyer Pre-Approved | `pre_approved` | select | Yes/No | No | Yes | Yes | Yes (badge) | No | Yes | No (has_preapproval would) | Good | Shown when Cash |
| Buyer | ALL | 3 PT / Cash | Pre-Approval Amount / Cash Budget | `pre_approval_amount` / `cash_budget` | number $ | — | No | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 3 PT / Assumable | Interested in Assumable | `assumable_interest` | select | Yes/No | No | Yes | Yes | Yes | No | Yes | No (would) | Good | `updatedAssumableInterest` clears sub-fields |
| Buyer | ALL | 3 PT / Assumable | Max Interest Rate / Max Monthly / Bridge Cash / Fee Responsibility | `assumable_max_interest_rate`, `assumable_max_monthly_payment`, `assumable_bridge_gap_cash`, `assumption_fee_responsibility` | number/select | — | No | Yes | Yes | Yes | No | No | Partial (would) | Good | Shown when Assumable=Yes |
| Buyer | ALL | 3 PT / Seller Financing | Purchase Price, Down Payment (+type), Seller Financing Amt (+type), Interest Rate, Loan Duration, Prepayment Penalty(+amt), Balloon(+amt/date), Amortization(+other), Payment Frequency(+other), Late Fee | `purchase_price`, `down_payment_amount`/`down_payment_type`, `seller_financing_amount`/`seller_financing_type`, `interest_rate`, `loan_duration`, `prepayment_penalty`(+`_amount`), `balloon_payment`(+`_amount`/`_date`), `seller_amortization_type`(+`_other`), `seller_payment_frequency`(+`_other`), `seller_late_fee_amount` | text/select | — | No | Yes | Yes | Partial (subset shown) | No | Partial (would) | Good | Shown when Seller Financing selected |
| Buyer | ALL | 3 PT / Exchange-Trade | Exchange Item(+other), Value, Condition, Additional Cash, Value Determination, Transfer Method, Liens(+details), Inspection Rights | `exchange_item`, `other_exchange_item`, `exchange_item_value`, `exchange_item_condition`, `additional_cash`, `value_determination`, `exchange_transfer_method`, `exchange_liens`(+`_details`), `exchange_inspection_rights` | mixed | — | No | Yes | Yes | Partial | No | No | No | Good | Shown when Exchange/Trade |
| Buyer | ALL | 3 PT / Lease Option | price, payment, duration, option fee(+amount), fee credit(+%), conditions, terms, maintenance, extension | `lease_option_price`, `lease_option_payment`, `lease_option_duration`, `has_option_fee`, `option_fee_amount`, `lease_option_fee_credit`(+`_percentage`), `lease_option_conditions`, `lease_option_terms`, `lease_option_maintenance`, `lease_option_extension_terms` | mixed | — | No | Yes | Yes | Partial | No | Yes (interest flag) | No (would) | Good | `interested_lease_option` interplay |
| Buyer | ALL | 3 PT / Lease Purchase | price, payment, duration, rent credit(+amt), deposit, conditions, terms, option fee(+amt), maintenance, extension | `lease_purchase_price`, `lease_purchase_payment`, `lease_purchase_duration`, `lease_purchase_rent_credit`(+`_amount`), `lease_purchase_deposit`, `lease_purchase_conditions`, `lease_purchase_terms`, `lease_purchase_option_fee`(+`_amount`), `lease_purchase_maintenance`, `lease_purchase_extension_terms` | mixed | — | No | Yes | Yes | Partial | No | Yes (has_lease_purchase) | No (would) | Good | — |
| Buyer | ALL | 3 PT / Crypto | type, % crypto, % cash, exchange method, wallet, fees, transfer timing(+other) | `cryptocurrency_type`, `crypto_percentage`, `cash_percentage_crypto`, `crypto_exchange_method`, `crypto_custodian_wallet`, `crypto_transaction_fees`, `crypto_transfer_timing`(+`_other`) | mixed | — | No | Yes | Yes | Partial | No | No | No | Good | Shown when Cryptocurrency |
| Buyer | ALL | 3 PT / NFT | description, % NFT, % cash, valuation, transfer, gas fees | `nft_description`, `nft_percentage`, `cash_percentage_nft`, `nft_valuation_method`, `nft_transfer_method`, `nft_gas_fees` | mixed | — | No | Yes | Yes | Yes | No | No | No | Good | Shown when NFT |
| Buyer | ALL | 3 Purchasing Terms | Earnest Money Amount (+type) / Timing | `earnest_money_amount`, `earnest_money_type`, `earnest_money_timing` | number/toggle/select | $ / % | No | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 3 Purchasing Terms | Due Diligence (Y/N) | `due_diligence_yn` | select | Yes/No | No | Yes | Yes | (drives inspection) | No | No | No | Good | Back-compat inferred from inspection_period_days |
| Buyer | ALL | 3 Purchasing Terms | Inspection Contingency (+period) | `inspection_contingency_buyer`, `inspection_contingency_period` (legacy `inspection_period_days`/`_other`) | select/number | Included/Waived/Negotiable | No | Yes | Yes | Yes | No | Yes (inspection_period) | No | Good | Consolidated field w/ legacy fallback |
| Buyer | ALL | 3 Purchasing Terms | Appraisal Contingency (+days) | `appraisal_contingency_buyer`, `appraisal_contingency_days` | select/number | Included/Waived/Negotiable | No | Yes | Yes | Yes (via ContingencyOptionHelper) | No | Yes | No | Good | Legacy "Waived" preserved |
| Buyer | ALL | 3 Purchasing Terms | Financing Contingency (+period) | `financing_contingency_buyer`, `financing_contingency_period` (legacy `financing_contingency_days_buyer`) | select/number | Included/Waived/Negotiable | No | Yes | Yes | Yes | No | Yes | No | Good | Dual-write to legacy key |
| Buyer | ALL | 3 Purchasing Terms | Home Sale Contingency (+period/address/date/under-contract/details) | `home_sale_contingency`, `home_sale_contingency_period`, `_address`, `_date`, `_under_contract`, `_details` | mixed | — | No | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 3 Purchasing Terms | Seller Contribution (+details) | `seller_contribution`, `seller_contribution_details` | select/text | — | No | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 3 Purchasing Terms | Possession Preference (+other/details) | `possession_preference`, `possession_preference_other`, `possession_details` | select/text | — | No | Yes | Yes | Yes | No | No | No | Good | Other companion |
| Buyer | ALL | 3 Purchasing Terms | Home Warranty Requested (+details) | `home_warranty_requested`, `home_warranty_details` | select/text | Yes/No | No | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 3 Purchasing Terms | As-Is Purchase | `as_is_purchase` | select | Yes/No | No | Yes | Yes | Yes | No | No | No | Good | Yes/No |
| Buyer | ALL | 3 Purchasing Terms | Property Inclusions / Exclusions | `property_inclusions`, `property_exclusions` | text | free | No | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 3 Purchasing Terms | Closing Cost Responsibility | `closing_cost_responsibility` | select | — | No | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 3 Purchasing Terms | Additional Purchase Terms | `additional_purchase_terms` | textarea | free | No | Yes | Yes | Yes | No | No | No | Good | — |
| Buyer | ALL | 4 Broker Comp | Commission Structure | `commission_structure` | select | Buyer Pays Out-of-Pocket / Requested From Seller | No | Yes | Yes | **No (not shown publicly)** | **Yes (term grp 1)** | No | No | Good | Broker comp never public |
| Buyer | ALL | 4 Broker Comp | Buyer's Broker Purchase Fee | `purchase_fee_type` + `purchase_fee_percentage`/`_flat`/`_percentage_combo`/`_flat_combo`/`_other` | select+inputs | %/flat/combo/other | No | Yes | Partial (see note) | No | **Yes (term grp 2)** | No | No | Good | `updatedPurchaseFeeType` resets; combos not all reloaded in create loadDraft |
| Buyer | ALL | 4 Broker Comp | Interested in Lease Agreement | `interested_lease_option` | select | Yes/No | No | Yes | Yes | No | **Yes (term grp 3)** | No | No | Good | Gate for lease-fee block |
| Buyer | ALL | 4 Broker Comp | Buyer's Broker Lease Fee | `lease_fee_type` + `lease_fee_flat`/`_percentage`/`_percentage_monthly_rent`/`_percentage_monthly_number`/`_flat_combo`/`_percentage_combo`/`_percentage_net`/`_flat_combo_net`/`_percentage_combo_net`/`_other` | select+inputs | options differ Residential vs other | No | Yes | Partial | No | **Yes (term grp 4)** | No | No | Good | Residential vs non-Residential option labels |
| Buyer | ALL | 4 Broker Comp | Lease Agreement Follow-up | `interested_lease_option_agreement` | select | Yes/No | No | Yes | Yes | No | **Yes (term grp 5)** | No | No | Good | Gates lease_type/purchase_type |
| Buyer | ALL | 4 Broker Comp | Lease Type / Value | `lease_type`(+`lease_type_other`), `lease_value` | select/input | percent/flat | No | Yes | **Partial (not in create loadDraft)** | No | **Yes (term grp 6)** | No | No | Needs verification | Confirm reload in BuyerOfferListingEdit |
| Buyer | ALL | 4 Broker Comp | Purchase Type / Value | `purchase_type`, `purchase_value`, `purchase_pice_commercial`, `purchase_fee_flat_exercised` | select/input | percent/flat | No | Yes | **Partial (not in create loadDraft)** | No | **Yes (term grp 7)** | No | No | Needs verification | `purchase_pice_commercial` misspelled key |
| Buyer | ALL | 4 Broker Comp | Protection Period | `protection_period` | number | days | No | Yes | Yes | No | **Yes (term grp 8)** | No | No | Good | — |
| Buyer | ALL | 4 Broker Comp | Early Termination Fee (option+amount) | `early_termination_fee_option`, `early_termination_fee_amount` | select/number | Yes/No | No | Yes | Yes | No | **Yes (grp 9/10)** | No | No | Good | — |
| Buyer | ALL | 4 Broker Comp | Retainer Fee (option+amount+application) | `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application` | select/number | Yes/No | No | Yes | Yes | No | **Yes (grp 11-13)** | No | No | Good | — |
| Buyer | ALL | 4 Broker Comp | Agency Agreement Timeframe (+custom) | `agency_agreement_timeframe`, `agency_agreement_custom` | select/text | — | No | Yes | Yes | No | **Yes (grp 14)** | No | No | Good | — |
| Buyer | ALL | 4 Broker Comp | Brokerage Relationship | `brokerage_relationship` | select | Transaction Broker / Single Agent / Dual Agency / No Relationship | No | Yes | Yes | No | **Yes (grp 15)** | No | No | Good | Representation preference |
| Buyer | ALL | 4 Broker Comp | Additional Broker Details | `additional_details_broker` | textarea | free | No | Yes | **Partial (not in create loadDraft)** | No | No | No | No | Needs verification | — |
| Buyer | ALL | 4 Broker Comp | Lease Option Fee (combos) | `lease_option_fee_type`/`_flat`/`_percentage`/`_other`/`_flat_combo`/`_percentage_combo`, `lease_option_consideration` | inputs | — | No | Yes | Partial | No | Partial (term grp 4) | No | No | Needs verification | Combo/consideration not all reloaded in create |
| Buyer | ALL | 5 AI Questions (KB) | Listing Ask-AI FAQ | `listing_ai_faq` (meta, JSON) | KB form | keys from `config/ai_faq_buyer.php` (universal + per-type group) | No | Yes | Yes | Via Ask AI | No | **Yes (answers)** | No | Excellent | User-authored KB; primary Ask-AI answer source |
| Buyer | ALL | 6 Contact | First Name | `first_name` | text | free | UI | Yes | Yes | **Yes** | No | No | No | PII | Public per privacy notice |
| Buyer | ALL | 6 Contact | Last Name | `last_name` | text | free | UI | Yes | Yes | **Yes (ISSUE)** | No | No | No | PII | Notice says admin-only, but view renders it w/ no auth gate |
| Buyer | ALL | 6 Contact | Phone Number | `phone_number` | tel | digits | UI | Yes | Yes | **Yes (ISSUE)** | No | No | No | PII | Notice says admin-only — publicly rendered |
| Buyer | ALL | 6 Contact | Email | `email` | email | — | UI | Yes | Yes | **Yes (ISSUE)** | No | No | No | PII | Same PII exposure concern |
| Buyer | ALL | 6 Contact | Personal Photo / Video Link | `photo` / `video_link` | file / url | image; YouTube/Vimeo | No | Yes | Yes | Yes (embed) | No | No | No | Media | Video upload UI removed; link kept |
| Buyer | ALL (agent users) | 6 Contact | Agent Brokerage / License / NAR ID | `agent_brokerage`, `agent_license_number`, `agent_nar_member_id` | text | — | No | Yes (agent only) | Yes | Yes | No | No | No | Good | Saved only when `user_type=agent` |

### Buyer — Residential

Shared fields apply. Residential-only / conditional Property-Preferences:

| Flow | Property Type | Tab/Section | Field Label | Field Key | Input | Options | Required? | Saves? | Autopop? | Public? | Matching? | Ask AI? | DNA? | Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Buyer | Residential | 1 Prop Pref | Minimum Bedrooms | `bedrooms`(+`other_bedrooms`) | select+num | 1–10/Other | UI | Yes | Yes | Yes | No | Yes | No (would) | Good | **Residential only** |
| Buyer | Residential | 1 Prop Pref | Minimum Bathrooms | `bathrooms`(+`other_bathrooms`) | select+num | 1–10/Other | UI | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Comm/Business |
| Buyer | Residential | 1 Prop Pref | Minimum Heated SqFt | `minimum_heated_square` | text | — | No | Yes | Yes | Yes | No | Yes(square_feet) | No (would) | Good | Res/Comm/Business |
| Buyer | Residential | 1 Prop Pref | Acceptable Property Conditions | `condition_prop_buyer`(+`other_property_condition`) | select2 multi | conditions/Other | No | Yes | Yes | Yes | No | No | No (would) | Good | All except Vacant Land |
| Buyer | Residential | 1 Prop Pref | Carport Needed (+spaces) | `carport_needed`(+`other_carport_needed`) | select+num | Yes/No/Optional | No | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Income |
| Buyer | Residential | 1 Prop Pref | Garage Needed (+spaces) | `garage_needed`(+`other_garage_needed`) | select+num | Yes/No/Optional | No | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Income |
| Buyer | Residential | 1 Prop Pref | Pool Needed (+type) | `pool_needed`, `pool_type`{private,community} | select+checkbox | Yes/No/Optional | No | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Income |
| Buyer | Residential | 1 Prop Pref | Age-Restricted Community | `leasing_55_plus` | select | 55+/62+ options | No | Yes | Yes | Yes | No | Yes | No (would) | Good | **Residential only** |
| Buyer | Residential | 1 Prop Pref | Non-Negotiable Amenities (+other) | `non_negotiable_amenities`(+`other_non_negotiable_amenities`) | select2 multi | residential-length list/Other | No | Yes | Yes | Yes | No | Yes | No | Good | All except Vacant Land |
| Buyer | Residential | 1 Prop Pref | Pets (+Number/Types/Breed/Weight/Service/ESA) | `pets`,`number_of_pets`,`type_of_pets`,`breed_of_pets`,`weight_of_pets`,`service_animal`,`emotional_support_animal` | select/text | Yes/No | No | Yes | Yes | Partial | No | Yes (pets*) | No (has_pets would) | Good | Residential + Income only |

#### Summary – Buyer Residential
- **Total fields:** ~150+ (7 tabs; Residential renders the most Tab-1 fields). **Required (DB):** 3 (`counties`, `state`, +`auction_time` if Bidding). **Required (UI):** listing_title, listing_date, expiration_date, auction_type, property_type, property_items, bedrooms, bathrooms, maximum_budget, offered_financing, first/last/phone/email.
- **Conditional:** bedrooms, bathrooms, heated sqft, conditions, carport, garage, pool, 55+, amenities, pets (Residential subset) + all financing sub-blocks.
- **Saved:** essentially all (EAV). **Public:** property criteria, financing, features, purchase terms, description, contact (no broker comp / meeting / search-areas).
- **Matching:** broker-comp/terms + services only. **Ask AI:** broad facts map + `listing_ai_faq`.
- **Metadata-suitable:** property_type, property_items, conditions, bed/bath, pool, garage, view, financing, purchase_purpose, HOA, flood zone.
- **Incomplete/disconnected:** DNA not triggered; `min_acreage`, `minimum_leaseable`, `number_of_unit`, `preferance_details`, `tenant_require`, `property_criteria`, `condition_prop` (legacy) saved with no active Residential UI.
- **May need review:** PII (last name/email/phone) publicly rendered despite privacy notice; several broker-comp secondary fields not reloaded in create `loadDraft`.

### Buyer — Income

Shared fields apply. Income-specific / conditional Property-Preferences:

| Flow | Property Type | Tab/Section | Field Label | Field Key | Input | Options | Required? | Saves? | Autopop? | Public? | Matching? | Ask AI? | DNA? | Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Buyer | Income | 1 Prop Pref | Acceptable Property Conditions (+other) | `condition_prop_buyer`(+`other_property_condition`) | select2 multi | conditions | No | Yes | Yes | Yes | No | No | No (would) | Good | All except Vacant Land |
| Buyer | Income | 1 Prop Pref | Carport Needed (+spaces) | `carport_needed`(+`other_carport_needed`) | select+num | Yes/No/Optional | No | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Income |
| Buyer | Income | 1 Prop Pref | Garage Needed (+spaces) | `garage_needed`(+`other_garage_needed`) | select+num | Yes/No/Optional | No | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Income |
| Buyer | Income | 1 Prop Pref | Pool Needed (+type) | `pool_needed`,`pool_type` | select+chk | Yes/No/Optional | No | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Income |
| Buyer | Income | 1 Prop Pref | Non-Negotiable Amenities (+other) | `non_negotiable_amenities`(+other) | select2 multi | residential-length list | No | Yes | Yes | Yes | No | Yes | No | Good | Uses residential option set |
| Buyer | Income | 1 Prop Pref | Pets (full section) | `pets`,`number_of_pets`,`type_of_pets`,`breed_of_pets`,`weight_of_pets`,`service_animal`,`emotional_support_animal` | select/text | Yes/No | No | Yes | Yes | Partial | No | Yes | No (has_pets would) | Good | Res/Income |
| Buyer | Income | 1 Prop Pref | Required Property/Business Assets (+other) | `assets`(JSON)(+`assets_other`) | select2 multi | Goodwill/FF&E/…/Other | No | Yes | Yes | No | No | No | No | Good | Income/Comm/Business |
| Buyer | Income | 1 Prop Pref | Acceptable Number of Units (+other) | `unit_size`(+`unit_size_other`) | select+text | 1-4/5-10/10+/Other | No | Yes | Yes | Yes | No | No | No | Good | **Income only** |
| Buyer | Income | 1 Prop Pref | Acceptable Unit Type (+other) | `number_of_unit_type`(JSON)(+`number_of_unit_type_other`) | select2 multi | unit types/Other | No | Yes | Yes | Yes | No | No | No | Good | **Income only** |
| Buyer | Income | 1 Prop Pref | Minimum Annual Net Income | `minimum_annual_net_income` | text $ | — | No | Yes | Yes | Yes | No | No | No | Good | Income/Comm/Business |
| Buyer | Income | 1 Prop Pref | Minimum Cap Rate | `minimum_cap_rate` | text % | — | No | Yes | Yes | Yes | No | Yes | No | Good | Income/Comm/Business |

Income has **no** bedrooms/bathrooms/heated-sqft fields (those are Res or Comm/Business).

#### Summary – Buyer Income
- **Total:** shared + ~11 conditional Tab-1 fields. **Required (DB):** 3. **Required (UI):** standard set (no bed/bath asterisk).
- **Conditional:** conditions, carport, garage, pool, amenities, full pets, assets, unit size, unit type, net income, cap rate.
- **Saved:** all EAV. **Public:** yes (units, net income, cap rate surface when populated). **Matching:** terms/services only. **Ask AI:** cap rate, units, conditions via facts + KB income group. **DNA:** not triggered.
- **Incomplete/disconnected:** `number_of_unit`/`_other` UI commented out (saved, no input); `preferance_details` Income block behind `@if(false)` (dead).
- **May need review:** same PII concern; income financials not used in matching.

### Buyer — Commercial

Shared fields apply. Commercial-specific / conditional Property-Preferences:

| Flow | Property Type | Tab/Section | Field Label | Field Key | Input | Options | Required? | Saves? | Autopop? | Public? | Matching? | Ask AI? | DNA? | Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Buyer | Commercial | 1 Prop Pref | Acceptable Property Conditions (+other) | `condition_prop_buyer`(+other) | select2 multi | conditions | No | Yes | Yes | Yes | No | No | No (would) | Good | All except Vacant Land |
| Buyer | Commercial | 1 Prop Pref | Minimum Bathrooms (+other) | `bathrooms`(+`other_bathrooms`) | select+num | 1–10/Other | UI | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Comm/Business |
| Buyer | Commercial | 1 Prop Pref | Minimum Heated SqFt | `minimum_heated_square` | text | — | No | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Comm/Business |
| Buyer | Commercial | 1 Prop Pref | Garage/Parking Features (+options/other) | `garage_parking_spaces`, `garage_parking_spaces_option`(JSON), `other_parking_space_wrapper` | select+multi+text | Yes/No | No | Yes | Yes | Yes | No | Yes(garage_spaces) | No | Good | Comm/Business |
| Buyer | Commercial | 1 Prop Pref | Non-Negotiable Amenities (+other) | `non_negotiable_amenities`(+other) | select2 multi | commercial-length list | No | Yes | Yes | Yes | No | Yes | No | Good | Commercial option set |
| Buyer | Commercial | 1 Prop Pref | Required Property/Business Assets (+other) | `assets`(+`assets_other`) | select2 multi | asset list/Other | No | Yes | Yes | No | No | No | No | Good | Income/Comm/Business |
| Buyer | Commercial | 1 Prop Pref | Minimum Annual Net Income | `minimum_annual_net_income` | text $ | — | No | Yes | Yes | Yes | No | No | No | Good | Income/Comm/Business |
| Buyer | Commercial | 1 Prop Pref | Minimum Cap Rate | `minimum_cap_rate` | text % | — | No | Yes | Yes | Yes | No | Yes | No | Good | Income/Comm/Business |

Commercial has **no** bedrooms, carport, garage-needed, pool, 55+, or pets fields.

#### Summary – Buyer Commercial
- **Total:** shared + ~8 conditional. **Required (DB):** 3. **Required (UI):** + bathrooms asterisk.
- **Conditional:** conditions, bathrooms, heated sqft, garage/parking features, amenities (commercial set), assets, net income, cap rate.
- **Saved/Public:** yes. **Matching:** terms/services. **Ask AI:** sqft/bath/cap rate + KB commercial group. **DNA:** not triggered.
- **Incomplete/disconnected:** `minimum_leaseable` UI commented out (saved, no input); `leasing_space` displayed publicly but has no create-form input (legacy).
- **May need review:** PII exposure; commercial financials not in matching.

### Buyer — Business (Business Opportunity)

Shared fields apply. Business-specific / conditional Property-Preferences:

| Flow | Property Type | Tab/Section | Field Label | Field Key | Input | Options | Required? | Saves? | Autopop? | Public? | Matching? | Ask AI? | DNA? | Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Buyer | Business | 1 Prop Pref | Business Type (+other) | `business_type_selected`(JSON)(+`other_business_type`) | select2 multi | business type list/Other | No | Yes | **Partial** (`other_business_type` not in create loadDraft) | No | No | Yes(business_type_preference) | No | Good | **Business only**; shown inline under Property Type |
| Buyer | Business | 1 Prop Pref | Acceptable Property Conditions (+other) | `condition_prop_buyer`(+other) | select2 multi | conditions | No | Yes | Yes | Yes | No | No | No (would) | Good | All except Vacant Land |
| Buyer | Business | 1 Prop Pref | Minimum Bathrooms (+other) | `bathrooms`(+`other_bathrooms`) | select+num | 1–10/Other | UI | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Comm/Business |
| Buyer | Business | 1 Prop Pref | Minimum Heated SqFt | `minimum_heated_square` | text | — | No | Yes | Yes | Yes | No | Yes | No (would) | Good | Res/Comm/Business |
| Buyer | Business | 1 Prop Pref | Garage/Parking Features (+options/other) | `garage_parking_spaces`, `garage_parking_spaces_option`, `other_parking_space_wrapper` | select+multi+text | Yes/No | No | Yes | Yes | Yes | No | Yes | No | Good | Comm/Business |
| Buyer | Business | 1 Prop Pref | Non-Negotiable Amenities (+other) | `non_negotiable_amenities`(+other) | select2 multi | commercial-length list | No | Yes | Yes | Yes | No | Yes | No | Good | Commercial option set |
| Buyer | Business | 1 Prop Pref | Business & Real Estate Purchase Requirements | `real_estate_purchase` | select | Real Estate Building and Business / Business Only | UI | Yes | Yes | Yes | No | No | No | Good | **Business only**; UI-required |
| Buyer | Business | 1 Prop Pref | Required Property/Business Assets (+other) | `assets`(+`assets_other`) | select2 multi | asset list/Other | No | Yes | Yes | No | No | No | No | Good | Income/Comm/Business |
| Buyer | Business | 1 Prop Pref | Minimum Annual Net Income | `minimum_annual_net_income` | text $ | — | No | Yes | Yes | Yes | No | No | No | Good | Income/Comm/Business |
| Buyer | Business | 1 Prop Pref | Minimum Cap Rate | `minimum_cap_rate` | text % | — | No | Yes | Yes | Yes | No | Yes | No | Good | Income/Comm/Business |

#### Summary – Buyer Business
- **Total:** shared + ~10 conditional. **Required (DB):** 3. **Required (UI):** + bathrooms, business & real-estate requirements.
- **Conditional:** business type, conditions, bathrooms, heated sqft, garage/parking, amenities, real_estate_purchase, assets, net income, cap rate.
- **Saved:** all. **Public:** yes. **Matching:** terms/services. **Ask AI:** `business_type_selected` (business_type_preference) + KB business group. **DNA:** not triggered.
- **Incomplete/disconnected:** `other_business_type` not reloaded in create `loadDraft` (verify Edit); `business_type_selected_json` mirror prop.
- **May need review:** PII exposure; `real_estate_purchase` is a strong metadata tag not used in matching.

### Buyer — Vacant Land

Shared fields apply. Vacant Land is the sparsest type:

| Flow | Property Type | Tab/Section | Field Label | Field Key | Input | Options | Required? | Saves? | Autopop? | Public? | Matching? | Ask AI? | DNA? | Tags | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Buyer | Vacant Land | 1 Prop Pref | Acceptable Property Style | `property_items`(+`other_property_items`) | select2 multi | vacant-land-length list (Agricultural…Well Field/Other) | UI | Yes | Yes | Yes | No | Yes(style) | No (would) | Good | Largest option list |
| Buyer | Vacant Land | 1 Prop Pref | Minimum Total Acreage | `total_acreage` | select | acreage ranges | No | Yes | Yes | Yes | No | Yes | No | Good | Shared, most relevant here |
| Buyer | Vacant Land | 1 Prop Pref | View Preference (+other) | `view_preference`(+`other_preferences`) | select2 multi | — | No | Yes | Yes | Yes | No | Yes | No (would) | Good | Shared |

**Explicitly hidden for Vacant Land:** Property Conditions, Bedrooms, Bathrooms, Heated SqFt, Carport, Garage, Pool, 55+, Non-Negotiable Amenities, Assets, Net Income, Cap Rate. DNA Phase C block still renders.

#### Summary – Buyer Vacant Land
- **Total:** shared (Tabs 0,2,3,4,5,6) + minimal Tab-1 (property type/style, total acreage, view preference, DNA Phase C). **Required (DB):** 3. **Required (UI):** property_type, property_items, listing/contact basics.
- **Conditional:** essentially none beyond property style; most property-detail fields suppressed.
- **Saved:** all EAV. **Public:** style, acreage, view, financing, terms surface when populated. **Matching:** terms/services. **Ask AI:** KB land group + style/acreage facts. **DNA:** not triggered.
- **Incomplete/disconnected:** `min_acreage` prop exists/saved but has **no UI input anywhere** (public view reads `min_acreage`/`total_acreage`) — only `total_acreage` is captured. Land-specific KB questions exist (`land_*`) but no structured land fields (zoning, utilities, topography) as form inputs — captured only as free-text KB answers.
- **May need review:** land buyers cannot specify zoning/utilities/access as structured fields; PII exposure applies.

### Buyer — cross-cutting findings (all types)
- **Component props saved with NO active create-form UI (dead/legacy):** `min_acreage`, `minimum_leaseable` (blade commented), `number_of_unit`/`_other` (commented), `preferance_details` (Income block behind `@if(false)`), `tenant_require` (furnishings UI commented), `property_criteria`, `condition_prop` (legacy single-value; only array `condition_prop_buyer` used), `leasing_space` (no input, displayed publicly), `unit_number` (saved, not in create loadDraft), plus shared marketing/services/showing/meeting/Limited-Service props (`list_criteria*`, `market_groups*`, `schedule_showings*`, `attend_showings*`, `provide_virtual_tours*`, `assist_application*`, `fees`, `enable`, `custom_services`, `person_meeting`, `meeting_details_*`, `service_completion_*`) — persisted but not rendered in buyer commission-based tabs.
- **Autopopulate gaps (create loadDraft):** several broker-comp secondary fields written by `saveAllMetadata` but not re-read in the create component's `loadDraft`: `lease_type`/`lease_value`, `purchase_type`/`purchase_value`/`purchase_pice_commercial`/`purchase_fee_flat_exercised`, `lease_fee_flat_combo_net`/`_percentage_combo_net`/`_percentage_monthly_number`/`_percentage_net`, `lease_option_consideration`, `lease_option_fee_flat_combo`/`_percentage_combo`, `additional_details_broker`, `other_business_type`, `unit_number`. **Needs verification** against `BuyerOfferListingEdit::mount()` (primary edit path).
- **PII exposure (ISSUE):** privacy notice states last name / email / phone are admin-only, but `offer-listing/buyer/view.blade.php` renders `last_name`, `email`, `phone_number` (and agent brokerage/license) to any viewer passing the draft/archived gate — no owner/auth gate.
- **DNA disconnect (ISSUE):** "Property DNA Phase C — Buyer Tier 1" fields (`purchase_purpose`, `commute_*`, `hoa_*`, `flood_zone_tolerance`) and all DNA-consumed keys are read by `BuyerTenantDnaGenerator` from `buyer_criteria_auctions`; this offer-listing flow (`buyer_agent_auctions`) never dispatches `ComputeBuyerTenantDnaProfile`. Only the Ask AI snapshot builds on save.
- **Yes/No fields wanting richer AI meaning:** `pre_approved`, `assumable_interest`, `interested_lease_option`, `interested_lease_option_agreement`, `as_is_purchase`, `sale_provision_assignment`, `home_warranty_requested`, `pets`, `service_animal`, `emotional_support_animal`, `garage_needed`/`carport_needed`/`pool_needed` (Yes/No/Optional), `hoa_acceptance` (Yes/No/Flexible).

<!-- ============================================================= -->

## LANDLORD OFFER LISTINGS

**Architecture (governs every row).**
- **Storage:** `landlord_agent_auctions` has only native columns `id, display_bids, user_id, title, auction_type, auction_ended, is_approved, is_draft, is_sold, sold_date, timestamps, referring_agent_id, referral_source_code, referral_captured_at, referral_locked, is_archived`. **Every other field is EAV meta** (`landlord_agent_auction_metas`) via `saveMeta()`. `title` and `auction_type` are stored **both** native **and** meta.
- **Workflow stamp:** create writes `saveMeta('workflow_type','offer_listing')`.
- **Unconditional save:** `saveAllMetadata()` writes **all ~546 meta keys for every listing regardless of property type** — no property-type branching on save. Residential-only fields persist (blank) on Commercial and vice-versa. Property-type gating exists **only in the Blade views**.
- **Autopopulate on Edit:** `loadAuctionData()` rehydrates ~552 props via `$auction->get->{key}` — effectively everything autopopulates. `property_type` normalized to `Residential Property` / `Commercial Property`.
- **Property-type values:** stored value is `Residential Property` / `Commercial Property` (NOT "Residential Rental"/"Commercial Lease" — those are marketing labels). Partials gate `=== 'Residential Property'`; broker-compensation gates via `str_contains(…,'residential'/'commercial')`.
- **Matching:** `LandlordBidMatchScoreHelper` scores agent bids against the **broker-compensation/agency baseline + services + photo_enhancements only**. Property/lease/applicant/tax/HOA/location fields are **NOT used in matching**.
- **DNA:** only **Location DNA** runs (queued `ComputeLocationDna('landlord_agent', id)` when `address` present). No per-field AI-DNA generator invoked in this flow.
- **Not wired here:** `referral_fee_percent` (match group 17, only populated by the agent Hire flow) and Important Places (Buyer/Tenant only) are absent from Landlord Offer Listing.
- **Server-side publish validation is thin** (`LandlordPublishValidation`): only `first_name`, `last_name`, `phone_number`, `email` (required), `desired_lease_length` (required array min 1), `auction_time` (required if Bidding Period), `unit_address` (nullable), `roof_type`/`exterior_construction`/`foundation` (+`other_*`) as nullable `in:` arrays. All other asterisked/required fields are **HTML-only, NOT server-enforced**.

**Shared (both types):** contact/agent info, listing meta, address, property_type/style/condition, bathrooms, total sqft, total acreage, appliances, waterfront/water access/view/frontage, interior features, view preference, parking_terms, all lease pricing + new landlord lease terms, all applicant-requirements, all tax/legal/flood/CDD/HOA, all documents/photos/tours, all broker-compensation + agency terms, plus utilities/heating/AC/water/sewer.
**Conditional — Residential-only:** bedrooms, heated sqft, furnishings, carport, garage, pool, 55+, pets block, laundry/floor/security features, roof/exterior/foundation, rent_includes, residential leasing-details, residential broker-comp partials. **Commercial-only:** net-leasable sqft, garage/parking features multiselect, zoning, buildings/units/office-retail/flex sqft, road surface, electrical service, ceiling height, building features, meters, space type/classification, restrooms/offices/conference rooms, tenant_pays/owner_pays/terms_of_lease, full commercial lease-terms block (NNN/CAM, escalation, TI, permitted use, signage, guarantee).

### Landlord — Residential Rental

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key / Meta Key / DB Column | Input Type | Options / Allowed Values | Required? | Saves to DB (native/meta)? | Autopop. on Edit? | Public? | Matching? | Ask AI? | DNA? | Metadata / Tags | Notes / Issues |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Landlord Offer | Residential | Listing Details | Listing Status | `listing_status` | radio | Active(default), Pending, Expired(disabled) | No | meta | Yes | Yes | No | No | No | status | Drives `getStatusAttribute` |
| " | Residential | Listing Details | Listing Title | `listing_title` | text | — | UI (client) | meta `title` + native `title` | Yes | Yes | No | No | No | title | Native + meta duplicate |
| " | Residential | Listing Details | Listing Date | `listing_date` | date | — | UI (client) | meta | Yes | No | No | No | No | date | Offer-listing discriminator key |
| " | Residential | Listing Details | Expiration Date | `expiration_date` | date | — | UI (client) | meta | Yes | Yes | No | No | No | date | Drives "Expired" status |
| " | Residential | Listing Details | Listing Type | `auction_type` | select | Bidding Period, Traditional | UI (client) | meta + native | Yes | Yes | No | No | No | mode | Legacy 'Auction'→'Bidding Period' on load |
| " | Residential | Listing Details | Bidding Period Length | `auction_time` | select | 1/3/5/7/10/14 Days | **DB if Bidding Period** | meta | Yes | Partial (sort) | No | No | No | auction | Only enforced field beyond contact/lease-length |
| " | Residential | Contact | First / Last Name | `first_name` / `last_name` | text | — | **DB** | meta | Yes | Yes | No | No | No | contact | — |
| " | Residential | Contact | Phone / Email | `phone_number` / `email` | text/email | — | **DB** | meta | Yes | Yes | No | No | No | contact | — |
| " | Residential | Contact | Personal Photo / Video Link | `photo` / `video_link` | file/url | — | No | meta | Yes | Yes (agent card) | No | No | No | media | — |
| " | Residential | Contact (agent) | Brokerage / License / NAR ID | `agent_brokerage`, `agent_license_number`, `agent_nar_member_id` | text | — | No | meta (each) | Yes | Yes | No | No | No | agent creds | Agent-created only |
| " | Residential | Address | Street Address | `address` | text | — | UI (client) | meta | Yes | Yes | **Yes → Location DNA** | Yes | **Yes (address)** | location | Triggers ComputeLocationDna |
| " | Residential | Address | Unit / Apt / Suite | `unit_address` | text | — | nullable (server max:100) | meta | Yes | Yes | No | No | No | location | — |
| " | Residential | Address | lat / lng / place id | `property_lat`, `property_lng`, `google_place_id` | hidden | — | No | meta (each) | Yes | Yes(map) | No | No | Yes (geo) | geo | — |
| " | Residential | Address | City / State / ZIP / County | `property_city`, `property_state`, `property_zip`, `property_county` | text (city=autocomplete) | — | UI (client) | meta (each) | Yes | Yes | Yes → Loc DNA | Yes (zip,city) | Yes | location | — |
| " | Residential | Address (legacy) | Acceptable State / ZIP / Counties | `state`, `zip_code`, `zipCodes`, `counties` | text/badges | — | No | meta (each; arrays JSON) | Yes | Partial | No | No | No | legacy-geo | Visibility-flag gated, not property-type |
| " | Residential | Property Type & Style | Property Type | `property_type` | select | Residential Property, Commercial Property | UI (client) | meta | Yes (normalized) | Yes | No | Yes | No | gating | The gating control |
| " | Residential | Property Type & Style | Property Style | `property_items` | select | residential styles (config) | UI (client) | meta | Yes | Yes | No | Yes | No | type | Options filtered by property_type |
| " | Residential | Property Type & Style | Property Condition (+Other) | `condition_prop` (+`other_property_condition`) | select | New Construction; Updated/Renovated; Partially Updated; Older but Clean & Well Maintained | UI (client) | meta | Yes | Yes | No | Yes | No | condition | other_* companion largely dead (commented) |
| " | Residential | Beds/Baths/SqFt | Bedrooms (+Other) | `bedrooms`, `other_bedrooms` | select/number | config incl. Other | UI (client) | meta | Yes | Yes | No | Yes | No | size | **Residential-only**; search filters on it |
| " | Residential | Beds/Baths/SqFt | Bathrooms (+Other) | `bathrooms`, `other_bathrooms` | select/number | config incl. Other | UI (client) | meta | Yes | Yes | No | Yes | No | size | Both types |
| " | Residential | Beds/Baths/SqFt | Heated SqFt | `minimum_heated_square` | text numeric | — | UI (client) | meta | Yes | Yes | No | Yes (square_feet) | No | size | **Residential-only** |
| " | Residential | Beds/Baths/SqFt | Total SqFt | `total_square_feet` | text numeric | — | No | meta | Yes | No | No | No | No | size | Not in Ask AI map/view |
| " | Residential | Beds/Baths/SqFt | SqFt Heated Source | `sqft_heated_source` | select | Appraisal, Builder, Measured, Owner Provided, Public Records | No | meta | Yes | Yes | No | Yes | No | size | — |
| " | Residential | Beds/Baths/SqFt | Total Acreage | `total_acreage` | select | config acreage | No | meta | Yes | Yes | No | No | No | size | — |
| " | Residential | Appliances | Appliances (+Other) | `appliances`, `other_appliances`, `appliances_other` | select2 multi + text | config incl. Other | No | meta (each; array) | Yes | Yes | No | Yes (appliances) | No | features | dual other keys |
| " | Residential | Furnishings | Furnishings | `tenant_require` | select | config `tenant_require` | No | meta | Yes | Yes | No | No | No | features | **Residential-only**; discriminator key |
| " | Residential | Parking | Carport (+#spaces) | `carport_needed`, `other_carport_needed` | select/number | Yes/No | No | meta (each) | Yes | Yes | No | No | No | parking | **Residential-only** |
| " | Residential | Parking | Garage (+#spaces) | `garage_needed`, `other_garage_needed` | select/number | Yes/No | No | meta (each) | Yes | Yes | No | No | No | parking | **Residential-only** |
| " | Residential | Parking | Parking Terms | `parking_terms` | textarea | — | No | meta | Yes | Yes | No | Yes | No | parking | Both types |
| " | Residential | Waterfront | Waterfront | `waterfront` | select | Yes/No | No | meta | Yes | Yes | No | Yes | No | water | Both |
| " | Residential | Waterfront | Water Access (+Other) | `water_access`, `other_water_access` | select2 multi + text | Bay/Harbor, Bayou, Beach, Canal-Fresh/Salt, Creek, Gulf/Ocean, Intracoastal, Lake, Pond, River, Other | No | meta (each) | Yes | Yes | No | Yes | No | water | — |
| " | Residential | Waterfront | Water View (+Other) | `water_view`, `other_water_view` | select2 multi + text | (Full/Partial variants) | No | meta (each) | Yes | **No** | No | Yes (view alias) | No | water | Displayed via view alias only |
| " | Residential | Waterfront | Water Frontage / Waterfront Feet | `water_frontage`, `waterfront_feet` | text/number | — | No | meta (each) | Yes | frontage:Yes / feet:No | No | No | No | water | — |
| " | Residential | Interior | Interior Features (+Other) | `interior_features`, `other_interior_features` | select2 multi + text | Ceiling Fans, Crown Molding, …, Walk-In Closet(s), Wet Bar, Window Treatments, Other | No | meta (each) | Yes | Yes | No | Yes | No | features | Both |
| " | Residential | Pool/View | Pool (+Type) | `pool_needed`, `pool_type`{private/community} | select/checkboxes | Yes/No | No | meta (each) | Yes | Yes | No | No | No | features | **Residential-only** |
| " | Residential | Pool/View | View (+Other) | `view_preference`, `other_preferences` | select2 multi + text | config incl. Other | No | meta (each) | Yes | Yes | No | Yes (view alias) | No | features | — |
| " | Residential | Age-restricted | Age-Restricted Community | `leasing_55_plus` | select | config `purchasing_props` | No | meta | Yes | Yes | No | Yes | No | features | **Residential-only** |
| " | Residential | Amenities | Amenities & Features (+Other) | `non_negotiable_amenities`, `other_non_negotiable_amenities` | select2 multi + text | residential config | No | meta (each) | Yes | Yes | No | No | No | features | Options gated by type |
| " | Residential | MLS Details (Res) | Year Built | `year_built` | number | 1800–current | No | meta | Yes | Yes | No | Yes | No | structural | — |
| " | Residential | MLS Details (Res) | Lot Dimensions | `lot_dimensions` | text | — | No | meta | Yes | Yes | No | Yes | No | structural | — |
| " | Residential | MLS Details (Res) | Roof Type (+Other) | `roof_type`, `other_roof_type` | select2 multi + text | Built-Up, Cement, Concrete, Membrane, Metal, Roof Over, Shake, Shingle, Slate, Tile, Other | nullable+in: (server) | meta (each) | Yes | Yes | No | Yes | No | structural | Only MLS field with server `in:` rule |
| " | Residential | MLS Details (Res) | Exterior Construction (+Other) | `exterior_construction`, `other_exterior_construction` | select2 multi + text | Asbestos, Block, Brick, …, Wood Siding, Other | nullable+in: (server) | meta (each) | Yes | Yes | No | Yes | No | structural | — |
| " | Residential | MLS Details (Res) | Foundation (+Other) | `foundation`, `other_foundation` | select2 multi + text | Basement, Block, Brick/Mortar, Concrete Perimeter, Crawlspace, Pillar/Post/Pier, Slab, Stem Wall, Stilt/On Piling, Other | nullable+in: (server) | meta (each) | Yes | Yes | No | Yes | No | structural | — |
| " | Residential | MLS Details (Res) | Heating & Fuel / Air Conditioning / Water / Sewer / Utilities (+Others) | `heating_fuel`, `air_conditioning`, `water`, `sewer`, `property_utilities` (+`other_*`) | select2 multi + text | standard MLS lists | No | meta (each) | Yes | **No (not in view)** | No | Yes | No | utilities | Rendered both branches |
| " | Residential | MLS Details (Res) | Laundry / Floor Covering / Security Features (+Others) | `laundry_features`, `floor_covering`, `security_features` (+`other_*`) | select2 multi + text | standard lists | No | meta (each) | Yes | **No** | No | No | No | features | **Residential-only**, not in view/AI |
| " | Residential | Pets (Res) | Pets Allowed | `pets` | select | Yes/No | No | meta | Yes | Yes | No | Yes (pet_policy primary) | No | pets | **Residential-only**; `pet_policy` meta confirmed always empty |
| " | Residential | Pets (Res) | Number / Types / Max Weight / Restrictions | `number_of_pets`, `type_of_pets`, `weight_of_pets`, `breed_restrictions` | number/text | — | No | meta (each) | Yes | Yes | No | Partial | No | pets | shown when pets=Yes; `breed_of_pets`, `has_breed_restrictions`, `service_animal`, `support_animal` commented-out (dead) but still saved/displayed |
| " | Residential | Lease Terms / Occupancy | Occupant Type / Occupied Until / Leasing Space | `occupant_status`, `occupant_tenant`, `leasing_spaces` | select/date | Owner/Tenant/Vacant; leasing: ADU/Entire Property/Single Room | UI (client) | meta (each) | Yes | Yes | No | No | No | occupancy | ADU is Residential-only |
| " | Residential | Lease Terms (Res detail) | Restrictions / Maintenance By / Response Time / Storage / Guests / Shared Areas / Utilities / Common-area Cleaning / Bathroom Facilities / Room Size | `restrictions`, `maintenance_by`, `maintenance_response_time`, `included_storage_space_res_both`, `storage_space_res_both`, `guests_allowed`, `common_areas_access`, `utilities`, `common_areas_cleaning`, `included_storage_space_res_single`, `storage_space_res_single`, `bathroom_facilities`, `room_size` | select/text | maintenance_by: Landlord/Property Manager/Real Estate Agent/Tenant; utilities: Included/Split/Individually Metered; guests: Allowed/Not; bathroom: Private/Shared | No | meta (each) | Yes | Yes | No | Yes (subset) | No | occupancy | **Residential-only**; sub-blocks by Entire/ADU vs Single Room |
| " | Residential | Lease Terms / Pricing | Starting Rent / Reserve Rent / Lease Now Price | `starting_rent`, `reserve_rent`, `lease_now_price` | text money | — | No | meta (each) | Yes | Yes | No | Yes (rent_amount cascade) | No | pricing | Shown only if Bidding Period |
| " | Residential | Lease Terms / Pricing | Desired Lease Price | `desired_rental_amount` | text money | — | UI+client-error (non-Bidding) | meta | Yes | Yes (price col) | No | Yes (rent_amount) | No | pricing | discriminator; drives search `price` |
| " | Residential | Lease Terms / Pricing | Lease Amount Frequency | `lease_amount_frequency` | select | Annually, Monthly, Daily, Weekly, Seasonal | UI (client) | meta | Yes | Yes | No | Yes | No | pricing | discriminator key |
| " | Residential | Lease Terms | Desired Lease Term (+Other) | `desired_lease_length`, `other_lease_term` | select2 multi + text | 3/6/9 Months, 1/2 Years, Month-to-Month, +Other | **DB (array min 1)** | meta (each) | Yes | Yes | No | Yes (lease_length cascade) | No | lease | Only property field enforced server-side |
| " | Residential | Lease Terms | Lease Available Date / Available Date | `lease_available_date` / `available_date` | date | — | No | meta (each) | Yes | Yes | No | No/Yes | No | lease | distinct dates |
| " | Residential | Lease Terms | Security Deposit Amount | `security_deposit_amount` | text money | — | No | meta | Yes | Yes | No | Yes | No | lease | **Rendered twice on tab (duplicate wire:model)** |
| " | Residential | Lease Terms | Last Month Rent Required | `last_month_rent_required` | select | Yes/No/Negotiable | No | meta | Yes | Yes | No | Yes | No | lease | — |
| " | Residential | Lease Terms | Total Move-In Funds Required | `total_move_in_funds_required` | text money | — | No | meta | Yes | Yes | No | Yes | No | lease | — |
| " | Residential | Lease Terms | Pet Deposit / Monthly Fee / Rent / Fee | `pet_deposit_amount`, `pet_monthly_fee`, `pet_rent`, `pet_fee` | text money | — | No | meta (each) | Yes | Yes | No | deposit+monthly: Yes | No | pets | Always visible (financial) |
| " | Residential | Lease Terms | Maintenance Responsibility | `ll_maintenance_responsibility` | textarea | — | No | meta | Yes | Yes | No | Yes | No | lease | — |
| " | Residential | Lease Terms | Renewal Option Offered / Details | `renewal_option_offered`, `renewal_option_details` | select/textarea | Yes/No/Negotiable | No | meta (each) | Yes | Yes | No | Yes | No | lease | Details if Yes/Neg |
| " | Residential | Lease Terms | Additional Landlord Lease Terms | `additional_landlord_lease_terms` | textarea | — | No | meta | Yes | Yes | No | Yes | No | lease | — |
| " | Residential | Lease Terms | Smoking Policy | `smoking_policy` | select | Allowed, Not Allowed, Designated Areas Only | No | meta | Yes | Yes | No | Yes | No | policy | — |
| " | Residential | Lease Terms | Subletting Policy | `subletting_policy` | select | Not Allowed, Allowed with Approval, Allowed | No | meta | Yes | Yes | No | Yes | No | policy | — |
| " | Residential | Lease Terms | Rent Includes (+Other) | `rent_includes`, `other_rent_include` | checkbox cards + text | Cable TV, Electricity, Gas, …, Water, None, Other | No | meta (each; array) | Yes | Yes | No | Yes | No | lease | **Residential-only** |
| " | Residential | Additional Details | Rental Description | `additional_details` | textarea | — | No | meta | Yes | Yes | No | Yes (description) | No | description | Placeholder switches by type |
| " | Residential | Applicant Requirements | Minimum Credit Score (+custom) | `min_credit_score`, `custom_credit_score_requirement` | select+text | No requirement, Below 500, 500–549…800+, Other | No | meta (each) | Yes | Yes | No | Yes | No | screening | custom if Other |
| " | Residential | Applicant Requirements | Income Qualification Method (+fixed/custom) | `income_qualification_method`, `min_monthly_income_fixed`, `custom_income_requirement` | select+text | No requirement, 2x/2.5x/3x Rent, Fixed Monthly Income, Other | No | meta (each) | Yes | Yes | No | Yes | No | screening | — |
| " | Residential | Applicant Requirements | Employment / Eviction / Bankruptcy (+custom each) | `employment_requirement`, `eviction_history_requirement`, `bankruptcy_requirement` (+`custom_*`) | select+text | (see options in blade) | No | meta (each) | Yes | Yes | No | Yes | No | screening | — |
| " | Residential | Applicant Requirements | Minimum Monthly Income Requirement | `min_income_requirement` | text money | — | No | meta | Yes | Yes | No | Yes | No | screening | Moved here from Lease tab |
| " | Residential | Applicant Requirements | Number of Occupants Allowed | `number_of_occupants_allowed` | number | — | No | meta | Yes | Yes | No | Yes | No | screening | — |
| " | Residential | Applicant Requirements | Landlord Approval Conditions | `landlord_approval_conditions` | textarea | — | No | meta | Yes | Yes | No | Yes | No | screening | — |
| " | Residential | Applicant Requirements | Credit Score Flexibility | `credit_score_flexibility` | select | No additional flexibility, Strict, Case-by-case, Compensating factors | No | meta | Yes | Yes | No | No | No | screening | — |
| " | Residential | Applicant Requirements | Pet Policy (screening) (+restrictions) | `pet_policy_requirement`, `pet_restrictions`, `custom_pet_policy_requirement` | select2 multi + text | Dogs/Cats/Small/Large/Exotic allowed, No pets, Case-by-case | No | meta (each; array) | Yes | Yes | No | No | No | screening | select2 JS-synced |
| " | Residential | Applicant Requirements | Smoking / Criminal Background / Prior Landlord Reference / Employment / Income Verification / Preferred Move-In Timeframe (+custom where noted) | `smoking_policy_requirement`, `criminal_background_requirement`, `reference_requirement`, `employment_verification_requirement`, `income_verification_requirement`, `preferred_move_in_timeframe` (+`custom_*`) | select+text | (see blade) | No | meta (each) | Yes | Yes | No | No | No | screening | Separate from lease `smoking_policy` |
| " | Residential | Applicant Requirements | Est. Water/Sewer/Trash, Electric, Internet, Cable | `est_water_sewer_trash`, `est_electric`, `est_internet`, `est_cable` | text money | — | No | meta (each) | Yes | Yes | No | Yes (all four) | No | utilities-cost | — |
| " | Residential | Broker Compensation | Landlord's Broker Commission Structure | `commission_structure` | select | Landlord Pays Broker Directly, Deducted from First Month's Rent, Deducted from Lease Proceeds, Other | No | meta | Yes | No | No | No | No | broker | Parent gated str_contains 'residential' |
| " | Residential | Broker Comp | Landlord's Broker Lease Fee (+subs) | `purchase_fee_type`, `purchase_fee_flat`, `purchase_fee_rental_period`, `purchase_fee_percentage_combo`, `purchase_fee_flat_combo`, `purchase_fee_other` | select/number/text | % Rent Due Each Period, % Gross Lease Value, % First Month's Rent, Flat Fee, Other | No | meta (each) | Yes | No | **Yes (group 1)** | No | No | broker | **Residential-only** partial |
| " | Residential | Broker Comp | Payment Timing (+days/other) | `broker_fee_timing`, `broker_fee_days_from_rent`, `broker_fee_days_after_lease`, `broker_fee_days_after_rent`, `broker_fee_timing_other`, `split_payment_due`(+`_other`), `broker_fee_days_after_due_event` | select/number/text | Deducted from Rent, Paid Within Days After Lease, Paid Within Days of Rent Payment, Other | No | meta (each) | Yes | No | **Yes (group 2)** | No | No | broker | **Residential-only** partial |
| " | Residential | Broker Comp | Renewal/Extension Fee (+subs) | `renewal_fee_type`, `renewal_fee_percentage`, `renewal_fee_lease_value`, `renewal_fee_first_month`, `renewal_fee_flat_free`, `renewal_fee_custom`, `renewal_fee_no_of_months` | select/number/text | % Rent Due Period, % Gross Lease Value, % First Month's Rent, Flat Fee, Other | No | meta (each) | Yes | No | **Yes (group 3)** | No | No | broker | **Residential-only** |
| " | Residential | Broker Comp | Protection Period (Days) | `protection_period` | number | — | No | meta | Yes | No | **Yes (group 11)** | No | No | broker | **Residential-only** select gate |
| " | Residential | Broker Comp | Early Termination Fee (+amount) | `early_termination_fee_option`, `early_termination_fee_amount` | select/text | yes/no | amount @error | meta (each) | Yes | No | **Yes (groups 12/13)** | No | No | broker | Select Residential-only |
| " | Residential | Broker Comp | Tenant's Broker Commission Structure + Fee (+subs) | `tenant_broker_commission_structure`, `tenant_broker_fee_structure`, `tenant_broker_percentage`, `tenant_broker_gross_lease`, `tenant_broker_first_month_rent`, `tenant_broker_flat_fee`, `tenant_broker_other` | select/number/text | % Rent Due Period, % Gross Lease Value, % First Month's Rent, Flat fee, Other | No | meta (each) | Yes | No | **Yes (groups 5a/5b)** | No | No | broker | Both types (option lists differ) |
| " | Residential | Broker Comp | Agency Agreement Timeframe (+custom) | `agency_agreement_timeframe`, `agency_agreement_custom` | select/text | 3/6/9/12 Months, Other | No | meta (each) | Yes | No | **Yes (group 14)** | No | No | broker | Both |
| " | Residential | Broker Comp | Acceptable Brokerage Relationship | `brokerage_relationship` | select | Transaction Broker, Single Agent, Dual Agency, No Brokerage Relationship | No | meta | Yes | No | **Yes (group 15)** | No | No | broker | Both |
| " | Residential | Broker Comp | Interested in Selling + type (+subs) | `interested_in_selling`, `interested_in_selling_type`, `landlord_broker_purchase_price`, `landlord_broker_percentage_price`, `landlord_broker_dollar_price`, `landlord_broker_flate_fee`, `landlord_broker_other` | select/number/text | Yes/No; % Total Purchase Price, % + Flat Fee, Flat Fee, Other | No | meta (each) | Yes | No | **Yes (groups 9/10)** | No | No | broker | Both |
| " | Residential | Broker Comp | Interested in Lease-Option + comp | `interested_lease_option_agreement`, `lease_type`, `lease_value`, `purchase_type`, `purchase_value` | select/text | Yes/No; percent/flat | No | meta (each) | Yes | No | **Yes (groups 6/7/8)** | No | No | broker | Both |
| " | Residential | Broker Comp | Interested in Property Mgmt + fee (+subs) | `interested_in_property_management`, `interested_in_property_management_fee`, `interested_in_property_management_fee_gross_lease`, `interested_in_property_management_fee_rental_periord`, `interested_in_property_management_fee_flate_free`, `interested_in_property_management_fee_other` | select/number/text | yes/no; % Gross Lease, % Rent Due Period, Flat Fee, Other | No | meta (each) | Yes | No | **Yes (group 16)** | No | No | broker | Both; misspelled keys `periord`/`flate_free` |
| " | Residential | Broker Comp | Additional Terms | `additional_details_broker` | textarea | — | No | meta | Yes | No | **No (excluded by design)** | No | No | broker | — |
| " | Residential | Broker Comp | Expansion Commission / Retainer Fee | `expansion_commission_percentage` (+`expansion_*`) / `retainer_fee_option`, `retainer_fee_amount`, `retainer_fee_application` | — | — | No | meta (each) | Yes | No | expansion: **Yes (group 4)**; retainer: No | No | No | broker | **Partials commented out — UI inactive; keys saved blank** |
| " | Residential | Tax/Legal/HOA | Parcel ID / Tax Year / Annual Taxes | `parcel_id`, `tax_year`, `annual_property_taxes` | text | — | No | meta (each) | Yes | Yes | No | Yes (all) | No | tax | — |
| " | Residential | Tax/Legal/HOA | Additional Parcels (+count/ids) | `additional_parcels`, `total_parcel_count`, `additional_parcel_ids` | select/number/textarea | Yes/No/Unknown | No | meta (each) | Yes | Yes | No | Yes (all) | No | tax | count/ids if Yes |
| " | Residential | Tax/Legal/HOA | Legal Description | `legal_description` | textarea | — | No | meta | Yes | Yes | No | Yes | No | legal | — |
| " | Residential | Tax/Legal/HOA | Flood Zone (+Other) / Insurance / Panel / Date | `flood_zone_code`(+`_other`), `flood_insurance_required`, `flood_zone_panel`, `flood_zone_date` | select+text | X, AE, A, AH, AO, VE, V, D, Unknown, Other; Yes/No/Unknown | No | meta (each) | Yes | Yes | No | Yes (all) | No | flood | — |
| " | Residential | Tax/Legal/HOA | CDD (+annual fee) | `has_cdd`, `annual_cdd_fee` | select+text | Yes/No/Unknown | No | meta (each) | Yes | Yes | No | Yes (both) | No | assessments | — |
| " | Residential | Tax/Legal/HOA | Special Assessments (+amount/desc) | `has_special_assessments`, `special_assessment_amount`, `special_assessment_description` | select/text/textarea | Yes/No/Unknown | No | meta (each) | Yes | Yes | No | Yes (all) | No | assessments | — |
| " | Residential | Tax/Legal/HOA | HOA present + Association block | `has_hoa`, `association_type`(+`_other`), `association_name`, `association_fee_amount`, `association_fee_frequency`(+`_other`), `association_approval_required`, `association_approval_process`, `association_application_fee`, `association_fee_includes`(+`_other`), `association_amenities`(+`_other`), `leasing_restrictions`, `min_lease_period`(+`_other`), `max_leases_per_year`, `additional_lease_restrictions` | select/text/select2 | (see blade) | No | meta (each) | Yes | Yes | No | Yes (subset) | No | hoa | select2 JS-synced |
| " | Residential | Documents & Disclosures | Property Documents (repeatable rows) | `landlord_doc_rows` (+`landlordDocFileUpload`, `landlordDocFileIndex`) | Alpine repeater + file | type dropdown incl. Landlord Disclosure, Survey, Inspection Report, HOA/Condo Docs, Flood Disclosure, Lead-Based Paint, Environmental Report, …, Other | No | meta `landlord_doc_rows` (array); files → storage | Yes | Partial | No | No | No | documents | individual `*_available` props exist but tab exposes only repeater |
| " | Residential | Photos & Tours | Property Photos / Virtual Tour URL / 3D Tour URL | `newPropertyPhotos`/`propertyPhotos`, `videoTourUrl`, `virtualTourUrl` | file / url | jpg/jpeg/png/webp, ≤50, ≤50MB | No | meta `property_photos`, `video_tour_url`, `virtual_tour_url` | Yes | Yes (gallery) | No | No | No | media | discriminator key |
| " | Residential | Ask AI KB | AI FAQ answers | `listing_ai_faq.{key}` | textarea | questions from `config/ai_faq_landlord.php` gated by property type | No | meta `listing_ai_faq` (JSON) + snapshot | Yes | Via Ask AI | No | **Yes (KB)** | No | ai-kb | Universal + residential group questions |
| " | Residential | Services (shell) | Services / Other services / Photo enhancements | `enable`, `fees`, `other_services`, `custom_services`, `photo_enhancements`, `custom_enhancement`, per-service `*_fee` | checkbox/fee | landlord residential service catalog (config) | No | meta (each) | Yes | Partial | **Yes (services + photo_enhancements + other_services)** | No | No | services | Core matching input |
| " | Residential | Hidden/meta | working_with_agent, desired_agent_hire_date, service_completion_*, meeting_details_*, person_meeting, understand_terms, agent_bid_visibility, total_flat_fee, total_marketing_fee | (keys) | mixed/hidden | — | No | meta (each) | Yes | Partial | No | No | No | ops | Auction/meeting scaffolding |
| " | Residential | Unused-but-saved | Buyer/Seller/financing/exchange/crypto/NFT props | `maximum_budget`, `offered_financing`, `down_payment_*`, `seller_financing_*`, `exchange_item*`, `cryptocurrency_type`, `nft_*`, `sale_provision*`, etc. | — | — | No | meta (each) | Yes | Partial | No | No | No | cross-role bleed | Present & saved but not part of Landlord UI (shared component scaffolding) — **disconnected** |

#### Summary – Landlord Residential Rental
- **Total fields:** ~250+ distinct props saved (546 saveMeta keys incl. fees/enable/cross-role scaffolding). Residential-facing UI ≈ 150 meaningful fields.
- **Required (server-enforced):** 6 (first_name, last_name, phone_number, email, desired_lease_length, auction_time-if-Bidding). ~15 more HTML/asterisk client-only.
- **Conditional:** extensive Residential-only set; occupancy sub-blocks by leasing_spaces; pricing by auction_type; residential broker-comp partials.
- **Saved:** all via EAV meta (only `title`, `auction_type` also native).
- **Displayed publicly:** ~215 keys. Non-displayed: heating_fuel, air_conditioning, water, sewer, property_utilities, laundry_features, floor_covering, security_features, total_square_feet, water_view (direct).
- **Matching:** broker-comp/agency groups + services + photo_enhancements + other_services only.
- **Ask AI:** ~90 landlord keys mapped.
- **Metadata-suitable:** property_type, bedrooms, bathrooms, rent_amount, condition, pet_policy, smoking_policy, appliances, interior_features, waterfront, flood_zone, HOA, lease_length, screening fields.
- **Incomplete/disconnected:** expansion_commission & retainer_fee partials commented out (keys saved blank); dead pet props; duplicate `security_deposit_amount` on tab; cross-role buyer/seller/finance/crypto props saved but never collected; MLS physical fields saved+AI-mapped but not shown publicly.
- **May need review:** thin server validation vs many HTML-required fields; misspelled meta keys `..._rental_periord`, `..._flate_free`; `pet_policy` meta always empty (UI uses `pets`).

### Landlord — Commercial Lease

Shares all contact/listing/address/tax-legal-HOA/documents/photos/broker-comp/agency/applicant-requirement/AI-KB/services rows above (identical wiring). Commercial-specific rows follow; Residential-only rows are **NOT shown** for Commercial (but meta keys still written blank).

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key / Meta Key | Input Type | Options / Allowed Values | Required? | Saves to DB (native/meta)? | Autopop.? | Public? | Matching? | Ask AI? | DNA? | Metadata / Tags | Notes / Issues |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Landlord Offer | Commercial | Property Type & Style | Property Style | `property_items` | select | commercial styles (config) | UI (client) | meta | Yes | Yes | No | Yes | No | type | Options gated Commercial |
| " | Commercial | Beds/Baths/SqFt | Bathrooms (+Other) | `bathrooms`, `other_bathrooms` | select/number | config incl. Other | UI (client) | meta (each) | Yes | Yes | No | Yes | No | size | Only bed/bath field shown |
| " | Commercial | Beds/Baths/SqFt | Net Leasable SqFt | `minimum_leaseable` | text numeric | — | UI (client) | meta | Yes | Yes | No | Yes (square_feet cascade) | No | size | **Commercial-only** |
| " | Commercial | Parking | Garage/Parking Features (+Other) | `garage_parking_spaces_option`, `other_parking_space_wrapper` | select2 multi + text | config incl. Other | No | meta (each) | Yes | Yes | No | No | No | parking | **Commercial-only** |
| " | Commercial | MLS Details (Comm) | Zoning | `zoning` | text | — | No | meta | Yes | Yes | No | Yes | No | structural | **Commercial-only** |
| " | Commercial | MLS Details (Comm) | Total Buildings / Units / Office-Retail SqFt / Flex SqFt | `total_buildings`, `total_units_on_property`, `office_retail_sqft`, `flex_space_sqft` | number | — | No | meta (each) | Yes | **No (not in view)** | No | Yes (all) | No | structural | **Commercial-only**; AI-mapped, not displayed |
| " | Commercial | MLS Details (Comm) | Road Surface Type (+Other) | `road_surface_type`, `other_road_surface_type` | select2 multi + text | Asphalt, Brick, Chip And Seal, Concrete, Dirt, Gravel, Limerock, Paved, Unimproved, Other | No | meta (each) | Yes | **No** | No | Yes | No | structural | Commercial-only |
| " | Commercial | MLS Details (Comm) | Electrical Service (+Other) | `electrical_service`, `other_electrical_service` | select2 multi + text | 100/150/200+ Amp, 3 Phase, 110/220/440 Volts, Generator, Generator Hook-Up, Separate Meters, Other | No | meta (each) | Yes | **No** | No | Yes | No | utilities | Commercial-only |
| " | Commercial | MLS Details (Comm) | Ceiling Height | `ceiling_height` | select | 8–9, 10–15, 16–22, 23+ Feet, Varied | No | meta | Yes | **No** | No | Yes | No | structural | Commercial-only |
| " | Commercial | MLS Details (Comm) | Building Features (+Other) | `building_features`, `other_building_features` | select2 multi + text | Bathrooms, Clear Span, …, Truck Well, Waiting Room, Other | No | meta (each) | Yes | **No** | No | Yes | No | features | Commercial-only |
| " | Commercial | MLS Details (Comm) | Electric/Water/Gas Meters | `number_electric_meters`, `number_water_meters`, `number_gas_meters` | number | — | No | meta (each) | Yes | **No** | No | Yes (all) | No | structural | Commercial-only |
| " | Commercial | MLS Details (Comm) | Space Type / Classification | `space_type`, `space_classification` | select2 multi | Type: Gray Shell, New, Re Let, Sub Let, Vanilla Shell; Class: A, B, C, D | No | meta (each) | Yes | **No** | No | Yes (both) | No | structural | Commercial-only |
| " | Commercial | MLS Details (Comm) | Restrooms / Offices / Conference Rooms | `number_of_restrooms`, `number_of_offices`, `number_of_conference_rooms` | number | — | No | meta (each) | Yes | **No** | No | Yes (all) | No | structural | Commercial-only |
| " | Commercial | MLS Details (Comm) | Utilities / Water / Sewer / Heating / AC (+Others) | `property_utilities`, `water`, `sewer`, `heating_fuel`, `air_conditioning` (+`other_*`) | select2 multi + text | same lists as Residential MLS | No | meta (each) | Yes | **No** | No | Yes | No | utilities | Rendered in Commercial branch too |
| " | Commercial | Lease Terms / Occupancy | Occupant Type / Occupied Until / Leasing Space | `occupant_status`, `occupant_tenant`, `leasing_spaces` | select/date | Owner/Tenant/Vacant; Entire Property/Single Room (no ADU) | UI (client) | meta (each) | Yes | Yes | No | No | No | occupancy | ADU hidden for Commercial |
| " | Commercial | Lease Terms (Comm detail) | Restrictions / Maintenance / Storage / Shared Amenities / Building Hours / 24-7 Access / Zoning Allows / Space Features / Neighboring Tenants (+ Single-Room set) | `restrictions`, `maintenance_by`, `maintenance_response_time`, `included_storage_space_com_entire`, `storage_space_com_entire`, `shared_amenities`, `building_hours`, `access_24_7`, `zoning_allows`, `space_features`, `neighboring_tenants`, `guests_allowed`, `common_areas_access`, `utilities`, `common_areas_cleaning`, `included_storage_space_com_single`, `storage_space_com_single`, `bathroom_facilities`, `room_size` | select/text | access_24_7: Yes/No; utilities: Included/Split/Individually Metered | No | meta (each) | Yes | Yes | No | Yes (subset) | No | occupancy | **Commercial-only**; a dead commented ADU block references `maintenance_handler`, `included_storage_space`, `storage_space` |
| " | Commercial | Lease Terms (Comm) | Tenant Pays (+Other) | `tenant_pays`, `other_tenant_pays`, `tenant_pays_other` | checkbox cards + text | Association Fees, Capital Expenses, CAM, Condo Fees, Electricity, Gas, Liability/Property Insurance, Parking Fee, Pro-Rated, Property Taxes, Reserves, Sewer, Trash, Water, None, Other | No | meta (each; array) | Yes | Yes | No | Yes (tenant_pays) | No | lease-comm | **Commercial-only** |
| " | Commercial | Lease Terms (Comm) | Owner Pays (+Other) | `owner_pays`, `other_owner_pays`, `owner_pays_other` | select2 multi + text | config `$ownerPays` | No | meta (each) | Yes | Yes | No | No | No | lease-comm | **Commercial-only**; select2 JS-synced |
| " | Commercial | Lease Terms (Comm) | Terms of Lease (+custom) | `terms_of_lease`, `custom_lease_term`, `other_lease_term` | select2 multi + text | Absolute (Triple) Net, Gross Lease, Gross Percentages, Ground Lease, Lease Option, Modified Gross, Net Lease, Net Net, Pass Throughs, Purchase Option, Renewal Option, Sale-Leaseback, Seasonal, Special Available (CLO), Varied Terms, Other | No | meta (each; array) | Yes | Yes | No | Yes (lease_terms) | No | lease-comm | **Commercial-only** |
| " | Commercial | Lease Terms / Pricing | Desired Lease Price / Frequency / Term | `desired_rental_amount`, `lease_amount_frequency`, `desired_lease_length` | text/select/select2 | Frequency: Annually, Monthly; Term: 6 Months, 1/2 Years, 3-5 Years, 6+ Years, Month-to-Month, +Other | price+freq UI; **term DB** | meta (each) | Yes | Yes | No | Yes | No | pricing | Option sets differ from Residential |
| " | Commercial | Lease Terms (Comm) | Commercial Lease Type (+Other) | `commercial_lease_type`, `commercial_lease_type_other` | select+text | Gross Lease, Modified Gross Lease, Triple Net / NNN, Full Service Gross, Percentage Rent, Other | No | meta (each) | Yes | Yes | No | Yes | No | lease-comm | **Commercial-only** |
| " | Commercial | Lease Terms (Comm) | CAM/NNN Additional Rent / Rent Escalation / TI Buildout / Permitted Use / Signage Rights / Commercial Parking / Personal Guarantee / Approval Conditions | `cam_nnn_additional_rent_charges`, `rent_escalation_terms`, `tenant_improvement_buildout_terms`, `permitted_use_restrictions`, `signage_rights`, `commercial_parking_terms`, `personal_guarantee_requirement`, `commercial_approval_conditions` | textarea/select | Personal Guarantee: Required, Not Required, Negotiable | No | meta (each) | Yes | Yes | No | Yes | No | lease-comm | **Commercial-only** (parking distinct from `parking_terms`) |
| " | Commercial | Lease Terms (shared) | Lease Available/Security Deposit/Last Month/Move-In/Available Date/Pet financials/Maintenance/Renewal/Additional Terms/Smoking/Subletting | (same keys as Residential shared rows) | mixed | (see Residential) | No | meta (each) | Yes | Yes | No | Yes (per AI map) | No | lease | **rent_includes NOT shown for Commercial** |
| " | Commercial | Broker Comp (Comm branch) | Tenant's Broker Commission Fee options | `tenant_broker_fee_structure` (+`tenant_broker_percentage`, `tenant_broker_gross_lease`, `tenant_broker_flat_fee`, `tenant_broker_other`) | select/number/text | **% Net Aggregate Rent, % Gross Rent**, Flat fee, Other | No | meta (each) | Yes | No | **Yes (groups 5a/5b)** | No | No | broker | Commercial branch of shared partial |

All other broker-compensation/agency, applicant-requirement, tax/legal/HOA, documents, photos, AI-KB and services rows are identical to the Residential table. The **Residential-only broker-comp partials** (landlord_broker_lease_fee, payment_timing, lease_renewal_extension, protection_period, early_termination select) are gated `=== 'Residential Property'` → do **not** render for Commercial, though the matching helper still expects their keys; on a Commercial listing those match groups are simply empty.

#### Summary – Landlord Commercial Lease
- **Total fields:** same ~546 meta keys saved; Commercial-facing UI ≈ 140 meaningful fields (adds ~30 commercial-specific, drops the residential-only set).
- **Required:** same 6 server-enforced; HTML/asterisk client set differs (net-leasable sqft instead of bedrooms/heated sqft; no pool/pets/furnishings gates).
- **Conditional:** net-leasable sqft, garage/parking, zoning, buildings/units/office-retail/flex sqft, road surface, electrical service, ceiling height, building features, meters, space type/classification, restrooms/offices/conference rooms, tenant_pays/owner_pays/terms_of_lease, full commercial lease-terms block — all Commercial-only.
- **Saved:** all EAV meta (title/auction_type also native).
- **Displayed publicly:** commercial lease-terms + zoning/year_built/building_hours/access_24_7/zoning_allows/shared_amenities/neighboring_tenants/space_features shown; **the commercial MLS building-detail block (buildings, units, office/retail/flex sqft, road surface, electrical, ceiling, building features, meters, space type/class, restrooms/offices/conference) is saved + AI-mapped but NOT displayed** — a disconnect.
- **Matching:** identical broker-comp/services logic; Residential-only broker partials never populate on Commercial listings.
- **Ask AI:** full commercial block mapped.
- **Metadata-suitable:** property_type, commercial_lease_type, zoning, net-leasable/office-retail/flex sqft, space classification, lease_terms, tenant_pays, rent_amount, permitted use.
- **Incomplete/disconnected:** commercial MLS detail fields not shown publicly; residential broker-comp partials absent for Commercial while match groups expect them; dead commented ADU block; cross-role scaffolding props saved but unused; commented expansion/retainer partials.
- **May need review:** whether the commercial MLS building-detail block should surface on the public listing; parity of broker-comp partials for Commercial (only tenant_broker_commission has a commercial branch).

<!-- ============================================================= -->

## TENANT CRITERIA / TENANT OFFER LISTINGS

**Flow architecture.** Components `TenantOfferListing` (create, ~5,254 lines) / `TenantOfferListingEdit` (edit, ~4,041 lines). It is a **role-shared mega-component** carrying ~400 public props, most never rendered for `user_type === 'tenant'`. Tenant rendering is gated by `$user_type === 'tenant'` and by `$property_type` (`Residential Property` vs `Commercial Property`).

**Storage.** Everything is written as **EAV meta** in `tenant_agent_auction_metas` via `saveMeta()`. Native columns on `tenant_agent_auctions`: `id, user_id, title, auction_type, is_approved, is_draft, is_sold, sold_date, auction_ended, timestamps`, plus referral columns and `is_archived` (referenced in controller; add-migration **needs verification**). `listing_title` is saved **both** as meta and mirrored to native `title`. Stamped `workflow_type = 'offer_listing'`.

**Wizard tabs (commission-based only):** listing-details, property-details, additional-details, leasing-terms, pre-screening, broker-compensation, tenant-info, plus the shared ai-questions-input partial and Location-DNA map partial.

**Flat-fee tabs are NOT wired into this flow.** `offer-tenant-tabs/flat-fee/*.blade.php` exist on disk but are not `@include`d anywhere in the Tenant Offer Listing wizard shell (grep returns zero references) — they belong to the flat-fee "hire services" variant and are documented as **orphaned relative to the offer-listing flow**.

**Broker-compensation heavily suppressed for tenant.** For a Tenant Offer Listing only **Commission Structure** and **Tenant's Broker Lease Fee** (+ dynamic sub-inputs) render; payment timing, purchase fee, lease-option comp, protection period, early-termination, retainer, agency timeframe, brokerage relationship and additional broker terms are hidden (props/meta still exist and save empty).

**Matching.** `TenantBidMatchScoreHelper` scores agent bids against the tenant's broker-compensation criteria; only `commission_structure` and `lease_fee_*` are meaningfully populated in the offer flow. Tenant property criteria feed the BYA compatibility engine (kill-switched) and `searchOfferListings` filters (`bedrooms`, `bathrooms`, `property_type`, `title`).

**"Other" companions:** `other_property_condition`, `other_bedrooms`, `other_bathrooms`, `other_preferences` (view), `other_non_negotiable_amenities`, `other_lease_for`, `other_parking_space_wrapper`, `lease_fee_other`; `rental_purpose` "Other" has **no** companion field.

### Tenant — Residential Rental (property_type = "Residential Property")

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key / Meta Key | Input Type | Options / Allowed Values | Required? | Saves to DB (native/meta)? | Autopop. on Edit? | Public? | Matching? | Ask AI? | DNA? | Metadata / Tags | Notes / Issues |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Tenant Offer | Residential | Listing Details | Listing Status | `listing_status` | radio | Active / Pending / Expired(auto) | No (defaults Active) | meta | Yes | Yes (status badge) | No | Partial | No | status | Expired disabled/auto |
| Tenant Offer | Residential | Listing Details | Listing Title | `listing_title` → also native `title` | text | free text ≤255 | **Yes** | meta + native `title` | Yes | Yes | No | Yes | Maybe | title | Dual-write meta+column |
| Tenant Offer | Residential | Listing Details | Listing Date | `listing_date` | date | — | **Yes** | meta | Yes | Likely | No | Partial | No | date | required\|date |
| Tenant Offer | Residential | Listing Details | Expiration Date | `expiration_date` | date | — | **Yes** | meta | Yes | Yes (ending-soon sort) | No | Partial | No | date | Drives Expired status |
| Tenant Offer | Residential | Listing Details | Listing Type (auction) | `auction_type` | select | Bidding Period / Traditional | **Yes** | meta + native `auction_type` | Yes | Yes | No | No | No | auction | Hidden→Traditional if `bya_beta.bidding_period_enabled` false |
| Tenant Offer | Residential | Listing Details | Bidding Period Length | `auction_time` | select | 1/3/5/7/10/14 Days | Cond. (req if Bidding Period) | meta | Yes | Yes (ending-soon) | No | No | No | auction | Conditional |
| Tenant Offer | Residential | Listing Details | Desired Agent Hire Date | `desired_agent_hire_date` | (server rule) | date | **Yes** (rule) | meta | Yes | Needs verification | No | No | No | date | Required in rules() but no visible tenant input in these tabs — **possible orphan/legacy require; verify UI** |
| Tenant Offer | Residential | Property Prefs | Acceptable Counties | `counties` (array) | autocomplete pills | free/typeahead | **Yes** (`array\|min:1`) | meta (JSON) | Yes | Yes | Compatibility (location) | Yes | Yes (LDNA) | location | Fallback UI; mirrored from Search-Areas blob |
| Tenant Offer | Residential | Property Prefs | Acceptable State | `state` | autocomplete | US states | **Yes** | meta | Yes | Yes | Compatibility | Yes | Yes | location | Auto-fills from county |
| Tenant Offer | Residential | Property Prefs | Search Areas (map blob) | `location_dna_preferences_json` → `location_dna_preferences` | hidden JSON (map) | polygon/area JSON | No | meta | Yes | Yes (boundary/flood/school compose) | Compatibility | Yes | **Yes (Location DNA)** | geo blob | Injected via `message.sent` hook; write-back to counties/state |
| Tenant Offer | Residential | Property Prefs | Important Places | `important_places_json` → `saveImportantPlaces` | hidden JSON | place rows | No | meta (Important Places key) | Yes | Needs verification | No | Partial | Yes (LDNA) | geo | Empty value meaningful (clears rows); method not shown in main file — **verify meta key** |
| Tenant Offer | Residential | Property Prefs | Acceptable Property Type | `property_type` | select | Residential Property / Commercial Property | **Yes** | meta | Yes | Yes | Search filter + compatibility | Yes | Yes | prop-type | Drives all conditional fields |
| Tenant Offer | Residential | Property Prefs | Acceptable Property Styles | `property_items` (array) | select2 multi | residential list | No | meta | Yes | Likely | Compatibility | Yes | Maybe | style | + `other_property_items` |
| Tenant Offer | Residential | Property Prefs | Acceptable Property Conditions | `condition_prop_buyer` (array) + `_json` | select2 multi | Updated/Renovated, Partially Updated, Older but Clean, No Preference | No | meta | Yes | Likely | Compatibility | Partial | Maybe | condition | `other_property_condition` companion (hidden) |
| Tenant Offer | Residential | Property Prefs | Minimum Bedrooms | `bedrooms` | select | list + Other | **Yes** (Residential) | meta | Yes | Yes | Search filter + compatibility | Yes | Maybe | beds | `other_bedrooms` companion |
| Tenant Offer | Residential | Property Prefs | Minimum Bathrooms | `bathrooms` | select | list + Other | **Yes** | meta | Yes | Yes | Search filter + compatibility | Yes | Maybe | baths | `other_bathrooms` companion |
| Tenant Offer | Residential | Property Prefs | Minimum Heated SqFt | `minimum_heated_square` | text(num) | — | No | meta | Yes | Likely | Compatibility | Partial | Maybe | sqft | Residential-only |
| Tenant Offer | Residential | Property Prefs | Furnishings Needed | `tenant_require` | select | furnished/optional/partial/turnkey/unfurnished | No | meta | Yes | Likely | Compatibility | Partial | Maybe | furnishing | Residential-only |
| Tenant Offer | Residential | Property Prefs | Carport Needed (+#spaces) | `carport_needed` / `other_carport_needed` | select/number | Yes/No/Optional | No / Cond. | meta (each) | Yes | Likely | Compatibility | Partial | No | parking | Yes/No → richer AI meaning needed |
| Tenant Offer | Residential | Property Prefs | Garage Needed (+#spaces) | `garage_needed` / `other_garage_needed` | select/number | Yes/No/Optional | No / Cond. | meta (each) | Yes | Likely | Compatibility | Partial | No | parking | — |
| Tenant Offer | Residential | Property Prefs | Pool Needed (+Type) | `pool_needed` / `pool_type` (private/community) | select/checkboxes | Yes/No/Optional | No / Cond. | meta (JSON) | Yes | Likely | Compatibility | Partial | No | pool | Residential-only |
| Tenant Offer | Residential | Property Prefs | View Preference | `view_preference` (array) | select2 multi | preferences list + Other | No | meta | Yes | Likely | Compatibility | Partial | No | view | `other_preferences` companion |
| Tenant Offer | Residential | Property Prefs | Age-Restricted Community | `leasing_55_plus` | select | purchasing_props list | No | meta | Yes | Likely | Compatibility | Partial | No | 55+ | Residential-only |
| Tenant Offer | Residential | Property Prefs | Non-Negotiable Amenities | `non_negotiable_amenities` (array) | select2 multi | residential list + Other | No | meta | Yes | Likely | Compatibility | Partial | Maybe | amenities | `other_non_negotiable_amenities` companion |
| Tenant Offer | Residential | Property Prefs → Commute | Work/School ZIP / Max Minutes / Mode | `commute_destination_zip`, `max_commute_minutes`, `commute_mode` | text/number/select | Drive/Transit/Walk/Bike/Remote | No | meta (each) | Yes | Needs verification | Commute score | Partial | Yes (commute) | commute | Feeds commute scoring |
| Tenant Offer | Residential | Property Prefs | Rental Purpose | `rental_purpose` | select | Primary/Vacation/Temporary/Student/Corporate/Other | No | meta | Yes | Likely | No | Partial | Maybe | purpose | "Other" has no companion free-text |
| Tenant Offer | Residential | Property Prefs | Accessibility Requirements | `accessibility_requirements` | textarea | free text | No | meta | Yes | Likely | No | Yes | Maybe | a11y | Free text |
| Tenant Offer | Residential | Leasing Terms | Maximum Monthly Lease Price | `budget` | text($) | — | **Yes** | meta | Yes | **Yes** | Compatibility (max_rent) | **Yes** (`max_rent`→budget) | Maybe | rent/budget | Ask AI maps `max_rent`=[budget,maximum_budget] |
| Tenant Offer | Residential | Leasing Terms | Offered Lease Term | `lease_for` (array) | select2 multi | lease_for_res list + Other | **Yes** (`array\|min:1`) | meta | Yes | **Yes** | Compatibility | Yes | Maybe | lease-term | `other_lease_for` companion |
| Tenant Offer | Residential | Leasing Terms | Offered Lease Date | `lease_date` | date | — | **Yes** | meta | Yes | Likely | No | Partial | No | date | — |
| Tenant Offer | Residential | Leasing Terms | Leasing Space | `leasing_spaces_tenant` (array) | select2 multi | acceptable_leasing_space list | **Yes** (`array\|min:1`) | meta | Yes | Likely | Compatibility | Partial | Maybe | space | — |
| Tenant Offer | Residential | Leasing Terms | Security Deposit Budget | `security_deposit_budget` | text($) | — | No | meta | Yes | **Yes** | No | Partial | Maybe | deposit | **Legacy-detection key** (controller guard) |
| Tenant Offer | Residential | Leasing Terms | Move-In Funds Available | `move_in_funds_available` | text($) | — | No | meta | Yes | Likely | No | Partial | Maybe | funds | — |
| Tenant Offer | Residential | Leasing Terms | First / Last Month Rent Available | `first_month_rent_available` / `last_month_rent_available` | select | Yes/No/Negotiable | No | meta (each) | Yes | Likely | No | Partial | No | funds | Yes/No/Neg |
| Tenant Offer | Residential | Leasing Terms | Utility / Maintenance Preference | `utility_preference` / `maintenance_preference` | text | free text | No | meta (each) | Yes | Likely | No | Partial | Maybe | utilities | — |
| Tenant Offer | Residential | Leasing Terms | Renewal Option Requested (+Details) | `renewal_option_requested` / `renewal_option_details` | select/textarea | Yes/No/Negotiable | No / Cond. | meta (each) | Yes | **Yes**/Likely | No | Partial | No | renewal | Details if Yes/Neg |
| Tenant Offer | Residential | Leasing Terms | Tenant Conditions / Additional Tenant Lease Terms | `tenant_conditions` / `additional_tenant_lease_terms` | textarea/text | free text | No | meta (each) | Yes | Likely | No | Yes | Maybe | conditions | — |
| Tenant Offer | Residential | Leasing Terms | Total Move-In Budget (Upfront) | `move_in_budget_upfront` | text($) | — | No | meta | Yes | Likely | No | Partial | Maybe | funds | Phase D Tier 2/3 |
| Tenant Offer | Residential | Leasing Terms | Earliest / Latest Move-In Date | `move_in_date_earliest` / `move_in_date_latest` | date | — | No | meta (each) | Yes | Likely | No | Partial | No | date | — |
| Tenant Offer | Residential | Pre-Screening | Number of Occupants | `number_occupant` | number | — | **Yes** | meta | Yes | **Yes** | Compatibility | Partial | Maybe | occupants | Note: separate `number_of_occupants` prop/meta also exists (flat-fee side) |
| Tenant Offer | Residential | Pre-Screening | Est. Monthly Net Household Income | `monthly_income` | text($) | — | **Yes** | meta | Yes | **Yes** (referenced) | Compatibility (income ratio) | Partial | Maybe | income | Sensitive; verify public gating |
| Tenant Offer | Residential | Pre-Screening | Pets (+ Number/Types/Breed/Weight/Service/ESA) | `pets`, `number_of_pets`, `type_of_pets`, `breed_of_pets`, `weight_of_pets`, `service_animal`, `support_animal` | select/number/text | Yes/No | No / Cond. | meta (each) | Yes | **Yes**/Likely | Compatibility | Yes/Partial | No | pets | Residential-only; also `emotional_support_animal` legacy dup |
| Tenant Offer | Residential | Pre-Screening | Rental History Disclosure (+Explanation) | `screening_concerns` / `screening_concerns_explanation` | select/text | Yes/No | **Yes** / Cond. | meta (each) | Yes | **Yes**/Likely | Compatibility | Yes | No | screening | Yes/No → richer AI meaning needed; companion free-text |
| Tenant Offer | Residential | Pre-Screening | Credit Score Range | `credit_score_range` | select | Excellent 750+/Good/Fair/Below 650/Prefer not | No | meta | Yes | **Yes** | Compatibility | Yes (`credit_score_range`) | No | credit | Offer-flow key = `credit_score_range` (NOT the misspelled `credit_scroe_rating` used by agent-auction flow) |
| Tenant Offer | Residential | Pre-Screening | Smoking Preference | `smoking_preference` | select | Non-Smoking/Smoking Allowed/No Preference | No | meta | Yes | **Yes** | Compatibility | Yes | No | smoking | Phase D T-07 |
| Tenant Offer | Residential | Additional Details | Tenant Description | `additional_details` | textarea | free text | No | meta | Yes | **Yes** | No | **Yes** (primary AI source) | Maybe | description | Feeds Ask AI FAQ enrichment |
| Tenant Offer | Residential | Broker Compensation | Broker Commission Structure | `commission_structure` | select | Out-of-Pocket / Included in Offer | No | meta | Yes | Likely | **Yes** (bid match) | Partial | No | comp | One of only 2 visible broker fields for tenant |
| Tenant Offer | Residential | Broker Compensation | Broker Lease Fee Type (+ dynamic subs) | `lease_fee_type` (+ `lease_fee_flat`, `lease_fee_percentage`, `lease_fee_percentage_monthly_rent`, `lease_fee_flat_combo`, `lease_fee_percentage_combo`, `lease_fee_other`) | select + inputs | Flat Fee / % Monthly Rent / % Gross Lease / Flat+% Gross / Other | No / Cond. | meta (each) | Yes | Likely | **Yes** (bid match) | Partial | No | comp | `lease_fee_other` companion |
| Tenant Offer | Residential | Tenant Info | First Name | `first_name` | text | — | **Yes** | meta | Yes | Yes (first name only) | No | No | No | contact | Public shows first name only |
| Tenant Offer | Residential | Tenant Info | Last Name / Phone / Email | `last_name`, `phone_number`, `email` | text/tel/email | — | **Yes** | meta (each) | Yes | No (admin only) | No | No | No | contact | Private |
| Tenant Offer | Residential | Tenant Info | Personal Photo / Video Link | `photo` / `video_link` → embed | file/url | image / YouTube/Vimeo | No | meta (each) | Yes | Yes | No | No | No | media | `embedUrl` derived |
| Tenant Offer | Residential | AI Questions partial | Listing AI FAQ | `listing_ai_faq` | repeater | Q/A pairs | No | meta | Yes | Yes (Ask AI) | No | **Yes** | No | faq | Feeds Ask AI knowledge base |
| Tenant Offer | Residential | System (hidden) | Workflow Type / User Type / Referral Percentage | `workflow_type`, `user_type`, `referral_percentage` | hidden | offer_listing / tenant / — | auto | meta (each) | Yes | No | referral: Yes (referral dim) | No | No | system | referral only saved if creator is agent |

#### Summary – Tenant Residential Rental
- **Total fields:** ~70 tenant-relevant (of ~400 shared props).
- **Required:** ~13 — listing_title, listing_date, expiration_date, auction_type, desired_agent_hire_date (rule-only, UI unclear), state, property_type, counties, bedrooms, bathrooms, budget, lease_for, lease_date, leasing_spaces_tenant, number_occupant, monthly_income, screening_concerns, first/last name, phone, email (auction_time conditionally).
- **Conditional:** pet detail block (pets=Yes), pool_type, other_* companions, renewal_option_details, screening_concerns_explanation, carport/garage counts, auction_time.
- **Saved:** all EAV meta; `listing_title` also native `title`.
- **Displayed publicly:** confirmed in `view.blade.php`: budget, bedrooms, bathrooms, lease_for, renewal_option, screening_concerns, monthly_income, credit_score_range, smoking_preference, security_deposit_budget, number_occupant, pets, additional_details, property_type, first_name, photo, video; most others "Likely".
- **Matching:** commission_structure + lease_fee_* (agent-bid helper); property criteria feed compatibility engine (kill-switched) + search filters.
- **Ask AI:** strong — additional_details, listing_ai_faq, budget(max_rent), credit_score_range, smoking_preference, lease_for, pets, screening_concerns, tenant_conditions mapped in `AskAiContextBuilderService`.
- **Metadata-suitable:** location (counties/state/LDNA blob), property_type, beds/baths, budget, lease term, pets, credit range, smoking.
- **Incomplete/disconnected:** flat-fee tab set not wired; `desired_agent_hire_date` required in rules() with no visible tenant input; duplicate props (`number_of_occupants` vs `number_occupant`, `support_animal` vs `emotional_support_animal`); Important Places meta key not visible in main component (`saveImportantPlaces` defined elsewhere — verify).
- **May need review:** Yes/No fields (carport/garage/pool/pets/screening/first-last month) want richer semantic tagging; public visibility of `monthly_income`/`number_occupant` should be confirmed for privacy.

### Tenant — Commercial Lease (property_type = "Commercial Property")

Shares all Listing-Details, Location, Leasing-budget/date, Pre-Screening (income/occupants/screening/credit/smoking), Additional-Details, Broker-Compensation and Tenant-Info fields above. Differences vs Residential:

| Flow | Property Type | Wizard Tab / Section | Field Label | Field Key / Meta Key | Input Type | Options / Allowed Values | Required? | Saves to DB (native/meta)? | Autopop.? | Public? | Matching? | Ask AI? | DNA? | Metadata / Tags | Notes / Issues |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Tenant Offer | Commercial | Property Prefs | Acceptable Property Styles | `property_items` (array) | select2 multi | commercial list | No | meta | Yes | Likely | Compatibility | Partial | Maybe | style | Commercial option set |
| Tenant Offer | Commercial | Property Prefs | Minimum Net Leasable SqFt | `minimum_leaseable` | text(num) | — | No | meta | Yes | Likely | Compatibility | Partial | Maybe | sqft | **Commercial-only** (replaces heated sqft) |
| Tenant Offer | Commercial | Property Prefs | Garage/Parking Features Needed (+options) | `garage_parking_spaces` / `garage_parking_spaces_option` (array) | select / select2 multi | Yes/No; features + Other | No / Cond. | meta (each) | Yes | Likely | Compatibility | Partial | No | parking | **Commercial-only**; `other_parking_space_wrapper` companion |
| Tenant Offer | Commercial | Property Prefs | Non-Negotiable Amenities | `non_negotiable_amenities` | select2 multi | commercial list + Other | No | meta | Yes | Likely | Compatibility | Partial | Maybe | amenities | Commercial placeholder differs |
| Tenant Offer | Commercial | Leasing Terms | Offered Lease Term | `lease_for` (array) | select2 multi | lease_for_com list + Other | **Yes** | meta | Yes | Yes | Compatibility | Yes | Maybe | lease-term | Commercial option set |
| Tenant Offer | Commercial | Leasing Terms | Commercial Lease Type Preference | `commercial_lease_type_preference` | select | Gross/Net/NNN/Modified Gross/Absolute Net/Percentage/Negotiable | No | meta | Yes | **Yes** | No | Yes (`commercial_lease_type`) | Maybe | lease-structure | **Commercial-only** |
| Tenant Offer | Commercial | Leasing Terms | CAM/NNN Preference / Rent Escalation Preference / Buildout-TI Request / Signage Request / Commercial Parking Needs | `cam_nnn_preference`, `rent_escalation_preference`, `buildout_tenant_improvement_request`, `signage_request`, `commercial_parking_access_needs` | text | free text | No | meta (each) | Yes | Likely | No | Partial | Maybe | NNN/buildout/access | **Commercial-only** |
| Tenant Offer | Commercial | Leasing Terms | Intended Business Use | `intended_business_use` | text | free text | No | meta | Yes | **Yes** | No | Yes | Maybe | use-type | **Commercial-only** (use type / zoning intent) |
| Tenant Offer | Commercial | Leasing Terms | Personal Guarantee Preference | `personal_guarantee_preference` | select | Yes/No/Negotiable | No | meta | Yes | Likely | No | Partial | No | guarantee | **Commercial-only** |
| Tenant Offer | Commercial | Leasing Terms | Commercial Approval Conditions | `commercial_approval_conditions` | textarea | free text | No | meta | Yes | Likely | No | Partial | Maybe | approval | **Commercial-only** |
| Tenant Offer | Commercial | Broker Compensation | Broker Lease Fee Type (Commercial subs) | `lease_fee_type` (+ `lease_fee_percentage_net`, `lease_fee_flat_combo_net`, `lease_fee_percentage_combo_net`) | select + inputs | Flat Fee / % Net Aggregate Rent / Flat+% Net Aggregate / Other | No / Cond. | meta (each) | Yes | Likely | Yes (bid match) | Partial | No | comp | Commercial option set differs |

**Fields NOT rendered for Commercial** (Residential-only): Minimum Heated SqFt, Furnishings, Carport Needed, Garage Needed (Yes/No/Optional variants), Pool Needed/Type, Age-Restricted Community, Minimum Bedrooms, full Pets block. **Bathrooms** still renders for Commercial (not wrapped in a property_type guard), though only *required* for Residential per rules().

#### Summary – Tenant Commercial Lease
- **Total fields:** ~70 shared core + 17 commercial-specific/variant fields.
- **Required:** same core set EXCEPT bedrooms/bathrooms are NOT required (rules() requires them only for Residential). state, property_type, counties, budget, lease_for, lease_date, leasing_spaces_tenant, number_occupant, monthly_income, screening_concerns, contact fields still required.
- **Conditional:** garage_parking_spaces_option (Yes), lease_fee net sub-inputs, other_* companions.
- **Saved:** all EAV; `listing_title`→native `title`.
- **Displayed publicly:** intended_business_use, commercial_lease_type_preference (+ core budget/lease_for/screening/etc.).
- **Matching:** commission_structure + lease_fee_* (agent bid); location + property criteria via compatibility engine (kill-switched).
- **Ask AI:** commercial_lease_type (mapped), intended_business_use, additional_details, listing_ai_faq, budget, credit_score_range, smoking_preference.
- **Metadata-suitable:** lease structure (Gross/Net/NNN), intended business use, net leasable sqft, CAM/NNN pref, rent escalation, TI request.
- **Incomplete/disconnected:** Bathrooms renders for Commercial with no obvious purpose (not required, not guarded) — **possible UI leak**; commercial free-text fields (cam_nnn, escalation, buildout, signage, parking, approval) are UI+meta only with NO structured matching/DNA wiring; zoning has no dedicated field (captured only via intended_business_use / commercial_approval_conditions free text).
- **May need review:** commercial free-text preferences should be considered for structured capture to enable matching/AI; personal_guarantee (Yes/No/Neg) needs richer AI semantics.

<!-- ============================================================= -->

# Master Findings

Synthesized across all four flows. Field keys are shown as stored (meta key or native column).

### 1. Fields shared across multiple flows
- **Listing meta:** `listing_status`, `listing_title`/`title`, `listing_date`, `expiration_date`, `auction_type`, `auction_time` — all four flows.
- **Contact/agent:** `first_name`, `last_name`, `phone_number`, `email`, `photo`, `video_link`, `agent_brokerage`, `agent_license_number`, `agent_nar_member_id` — all four.
- **Property core:** `property_type`, `property_items` (+`other_property_items`), `condition_prop`/`condition_prop_buyer`, `bathrooms`, `minimum_heated_square`, `total_acreage`, `view_preference` (+`other_preferences`), `waterfront`/water access/view — Seller/Buyer/Landlord (+Tenant subset).
- **Broker compensation & agency terms:** `commission_structure`, purchase/lease-fee families, `protection_period`, `early_termination_fee_*`, `retainer_fee_*`, `agency_agreement_timeframe`(+custom), `brokerage_relationship`, `additional_details_broker` — all four (this is the **only** field group that drives matching).
- **Financing families** (Cash / Assumable / Seller Financing / Exchange-Trade / Lease Option / Lease Purchase / Crypto / NFT) — Seller & Buyer (offered on sale side, requested on buy side).
- **Tax/legal/HOA/flood/CDD** block — Seller & Landlord (identical key set).
- **Location:** `address`/`property_city`/`property_state`/`property_zip`/`property_county` (Seller, Landlord) vs `counties`/`state` + Search-Areas blob (Buyer, Tenant).
- **Ask AI KB:** `listing_ai_faq` — all four. **Services:** `enable`/`fees`/`other_services`/`custom_services`/`photo_enhancements` — all four.
- **Referral:** `referring_agent_id`, `referral_source_code`, `referral_captured_at`, `referral_locked` (native, all four); `referral_percentage`/`referral_fee_percent` (agent-created only).

### 2. Fields unique to Seller
Sale-side terms: `sale_provision`(+other/assignment/`assignment_fee_*`), `target_closing_date`, seller purchase terms (`initial_deposit_*`, `additional_deposit_*`, `escrow_agent_preference`, `*_contingency_preference`, `sale_of_buyer_property_contingency`, `seller_contribution_credit_offered`, `possession_preference`, `included_personal_property`, `excluded_items`, `home_warranty_offered`, `additional_seller_sale_terms`), Estimated Payment Assumptions (`payment_*`), `current_status`, `occupant_status`/`occupant_tenant`, seller pricing (`starting_price`/`reserve_price`/`buy_now_price`/`maximum_budget` as desired sale price), `doc_rows`, disclosure flags.

### 3. Fields unique to Buyer
Purchasing terms & contingencies from the buyer side (`inspection/appraisal/financing_contingency_buyer`, `home_sale_contingency*`, `earnest_money_*`, `due_diligence_yn`, `closing_cost_responsibility`, `additional_purchase_terms`, `property_inclusions`/`exclusions`), buyer financing preferences (`pre_approved`/`pre_approval_amount`/`cash_budget`, `assumable_interest`, `assumption_fee_responsibility`), "Property DNA Phase C" block (`purchase_purpose`, `commute_*`, `hoa_acceptance`/`hoa_max_monthly_fee`, `flood_zone_tolerance`), Important Places (`important_places_json`), income-type `unit_size`/`number_of_unit_type`, `real_estate_purchase` (Business), `minimum_cap_rate`/`minimum_annual_net_income`.

### 4. Fields unique to Landlord
Landlord lease offer terms: `starting_rent`/`reserve_rent`/`lease_now_price`/`desired_rental_amount`, `lease_amount_frequency`, `desired_lease_length`(+other), `lease_available_date`/`available_date`, `security_deposit_amount`, `last_month_rent_required`, `total_move_in_funds_required`, `pet_deposit_amount`/`pet_monthly_fee`/`pet_rent`/`pet_fee`, `ll_maintenance_responsibility`, `renewal_option_offered`/`_details`, `smoking_policy`, `subletting_policy`, `rent_includes`; **applicant/pre-screening requirements** (`min_credit_score`, `income_qualification_method`, `employment_requirement`, `eviction_history_requirement`, `bankruptcy_requirement`, `criminal_background_requirement`, `reference_requirement`, `*_verification_requirement`, `preferred_move_in_timeframe`, `number_of_occupants_allowed`, `landlord_approval_conditions`, `est_*` utility costs); occupancy sub-blocks; landlord broker groups (`interested_in_selling`, `interested_lease_option_agreement`, `interested_in_property_management`).

### 5. Fields unique to Tenant
Tenant-side leasing requests: `budget` (max rent), `lease_for`(+other), `lease_date`, `leasing_spaces_tenant`, `security_deposit_budget`, `move_in_funds_available`, `first_month_rent_available`/`last_month_rent_available`, `utility_preference`, `maintenance_preference`, `renewal_option_requested`/`_details`, `tenant_conditions`, `additional_tenant_lease_terms`, `move_in_budget_upfront`, `move_in_date_earliest`/`_latest`; **pre-screening self-disclosure** (`number_occupant`, `monthly_income`, `credit_score_range`, `screening_concerns`(+explanation), `smoking_preference`, full pet block); `rental_purpose`, `accessibility_requirements`; tenant broker lease fee (`lease_fee_*`).

### 6. Fields unique to Residential
`bedrooms`(+other), `leasing_55_plus` (age-restricted), pool (`pool_needed`/`pool_type`), carport/garage (`*_needed`+spaces), furnishings (`tenant_require`), residential construction systems (`roof_type`, `exterior_construction`, `foundation`, `heating_and_fuel`/`heating_fuel`, `air_conditioning`, `water`, `sewer`, `utilities`/`property_utilities`), `laundry_features`, `floor_covering`, `security_features` (Landlord), pets block, `rent_includes` (Landlord), ADU leasing space option.

### 7. Fields unique to Income
`unit_number` (total units), `unit_buildings`, `unit_type_configurations[]` repeater (per-unit beds/baths/rent/sqft/occupancy), `number_water_meters`/`number_electric_meters` (Seller); `gross_annual_income`, `annual_operating_expenses`, `rent_roll_available`, `operating_statement_available` (Seller financial-details); Buyer `unit_size`/`number_of_unit_type`. `minimum_cap_rate`/`minimum_annual_net_income` shared with Commercial/Business.

### 8. Fields unique to Commercial Sale
`zoning`, `ceiling_height`, `electrical_service`, `building_features`, `road_frontage`/`road_surface_type`, `price_per_sqft`, `existing_lease_type`(+other), `lease_expiration`, `lease_assignable`, `business_type`(+other), `business_assets`, `garage_parking_spaces_option` (Seller). Landlord Commercial adds `total_buildings`/`total_units_on_property`/`office_retail_sqft`/`flex_space_sqft`, `space_type`/`space_classification`, `number_of_restrooms`/`_offices`/`_conference_rooms`, meters.

### 9. Fields unique to Business Opportunity
`business_name`, `year_established`, `real_estate_purchase`, `licenses`(+other), `sale_includes`(+other), `annual_revenue`, `gross_profit`, `sde_ebitda`, `inventory_value`, `ffe_value`, `reason_for_sale`(+other), `employee_count`, `financial_statements_available`, `tax_returns_available`, `nda_required`, `business_location_leased` + underlying-lease sub-fields (`business_lease_monthly_rent`/`_expiration`/`_renewal_options`/`_assignable`/`_additional_terms`). Buyer adds `business_type_selected`(+other).

### 10. Fields unique to Vacant Land
`current_use`(+other), `current_adjacent_use`(+other), `zoning`, `*_available` utility flags (`water_available`/`sewer_available`/`electric_available`/`gas_available`/`telecom_available`), `lot_dimensions`, `front_footage`, `number_of_wells`/`number_of_septics`, `fences`(+other), `vegetation`(+other), `buildable`, `easements`(+other), `min_acreage`. (Buyer VL is sparse — only style/acreage/view are structured; land specifics live in free-text KB.)

### 11. Fields unique to Residential Rental (Landlord/Tenant)
Landlord: full residential lease-detail occupancy sub-blocks (`restrictions`, `maintenance_by`, `common_areas_*`, `bathroom_facilities`, `room_size`, storage), `rent_includes`, pet-financials. Tenant: `furnishings`, pet block, `first_month_rent_available`/`last_month_rent_available`, residential lease term option set.

### 12. Fields unique to Commercial Lease (Landlord/Tenant)
Landlord: `tenant_pays`/`owner_pays`/`terms_of_lease`, `commercial_lease_type`, `cam_nnn_additional_rent_charges`, `rent_escalation_terms`, `tenant_improvement_buildout_terms`, `permitted_use_restrictions`, `signage_rights`, `commercial_parking_terms`, `personal_guarantee_requirement`, `commercial_approval_conditions`, `building_hours`, `access_24_7`, `zoning_allows`, `space_features`, `neighboring_tenants`, `shared_amenities`. Tenant: `commercial_lease_type_preference`, `cam_nnn_preference`, `rent_escalation_preference`, `buildout_tenant_improvement_request`, `intended_business_use`, `signage_request`, `commercial_parking_access_needs`, `personal_guarantee_preference`, `commercial_approval_conditions`, `minimum_leaseable`.

### 13. Fields that look important for future DNA metadata
- **Location DNA (already wired for Seller/Landlord):** `address`, `property_lat`/`property_lng`, `property_city`/`property_state`/`property_zip`/`property_county`, commute (`commute_destination_zip`/`max_commute_minutes`/`commute_mode`), Search-Areas blob (`location_dna_preferences`), Important Places.
- **Property DNA:** `property_type`, `property_items`, `condition_prop`, `bedrooms`, `bathrooms`, `minimum_heated_square`, `view_preference`, `waterfront`, pool, garage/carport, `leasing_55_plus`, `offered_financing`, `sale_provision`, `total_acreage`.
- **Buyer/Tenant DNA (NOT currently triggered from offer listings):** `purchase_purpose`, budget/`maximum_budget`/`budget`, financing preferences, `hoa_acceptance`, `flood_zone_tolerance`, beds/baths ranges, pets, `credit_score_range`, `smoking_preference`, `rental_purpose`, lifestyle amenities.
- **Lifestyle/marketing tags:** view, waterfront, pool, 55+, pets, smoking, walkability/commute, amenities lists, `intended_business_use`, lease structure.

### 14. Fields that look missing or weak based on current implementation
- **Buyer/Tenant DNA not wired:** offer-listing buyer/tenant data never dispatches `BuyerTenantDnaGenerator` (reads a different table). The "Property DNA Phase C" buyer block is collected but unused for DNA.
- **Matching is compensation-only:** no property/price/location/feature field affects match score today (service_area/experience/availability/compatibility all disabled; compatibility engine kill-switched).
- **Structured land attributes for Buyers:** Vacant Land buyers can't specify zoning/utilities/topography as structured fields (free-text KB only).
- **Structured commercial-lease preferences for Tenants:** cam_nnn/escalation/buildout/signage/parking are free text — no structured capture for matching/AI.
- **Zoning as a first-class field** is missing on the Tenant commercial side (only inferred from `intended_business_use`).
- **Seller service-area scoring** can't run: helper expects `city_id`/`county_id` native columns, but the flow writes `property_city`/`property_county` meta.
- **Richer meaning for Yes/No fields** (see item 16) — currently bare strings.

### 15. Fields with save/edit/display inconsistencies
- **Seller price key mismatch:** component saves `maximum_budget`; public view/calculator read `desired_sale_price`. **Verify sale price renders.**
- **Seller orphaned props** (declared, not persisted in create-save): `nft_valuation_method`, `nft_transfer_method`, `nft_gas_fees`, several `lease_option_*`/`lease_purchase_*` maint/extension/credit fields, `balloon_payment` toggle, `exchange_liens`, `custom_enhancement`, `photo_enhancements`, `openHouseCount`. `showPaymentAssumptions` panel doesn't auto-open on edit. Edit lacks the create-side conditional-clearing hooks.
- **Buyer autopopulate gaps in create `loadDraft`:** `lease_type`/`lease_value`, `purchase_type`/`purchase_value`/`purchase_pice_commercial`, `lease_fee_*_net`, `lease_option_consideration`, `additional_details_broker`, `other_business_type`, `unit_number` — **verify against `BuyerOfferListingEdit::mount()`**. Legacy props saved with no active UI (`min_acreage`, `minimum_leaseable`, `number_of_unit`, `preferance_details`, `tenant_require`, `condition_prop`, `leasing_space`).
- **Buyer PII exposure (ISSUE):** privacy notice says last name/email/phone are admin-only, but the public buyer view renders them with no auth gate.
- **Landlord:** MLS physical/commercial building-detail fields are saved + Ask-AI-mapped but **not displayed** publicly; `security_deposit_amount` rendered twice on the tab; `pet_policy` meta always empty (UI uses `pets`); expansion/retainer partials commented out but keys saved blank; dead pet props still saved/displayed; misspelled keys (`..._rental_periord`, `..._flate_free`).
- **Tenant:** flat-fee tabs orphaned (not included); `desired_agent_hire_date` required in rules() with no visible input; duplicate props (`number_of_occupants`/`number_occupant`, `support_animal`/`emotional_support_animal`); Bathrooms renders for Commercial with no gate/purpose; Important Places meta key not visible in main component.
- **Cross-role bleed (Landlord/Tenant mega-components):** buyer/seller/finance/crypto/NFT props are saved (blank) on every listing regardless of role.

### 16. Fields with "Other" companion text handling
Confirmed "Other" + free-text companion pairs (document both when comparing to Stellar):
- **Seller:** `property_items`/`other_property_items`, `water_access`/`other_water_access`, `water_view`/`other_water_view`, `interior_features`/`other_interior_features`, `view_preference`/`other_preferences`, `sale_provision`/`sale_provision_other`, `offered_financing`/`other_financing`, `appliances`/`other_appliances`, `roof_type`/`other_roof_type`, `exterior_construction`/…, `foundation`/…, `heating_and_fuel`/…, `air_conditioning`/…, `water`/…, `sewer`/…, `utilities`/…, `business_type`/`other_business_type`, `business_assets`/`assets_other`, `licenses`/`other_licenses`, `sale_includes`/`other_sale_includes`, `current_use`/`other_current_use`, `current_adjacent_use`/…, `fences`/…, `vegetation`/…, `easements`/…, `road_frontage`/…, `road_surface_type`/…, `flood_zone_code`/`_other`, `association_type`/`_other`, `association_fee_frequency`/`_other`, contingency/timeframe `_other` pairs, VL `*_available`/`_other`.
- **Buyer:** `property_items`/`other_property_items`, `condition_prop_buyer`/`other_property_condition`, `bedrooms`/`other_bedrooms`, `bathrooms`/`other_bathrooms`, `non_negotiable_amenities`/`other_non_negotiable_amenities`, `view_preference`/`other_preferences`, `sale_provision`/`sale_provision_other`, `offered_financing`/`other_financing`, `exchange_item`/`other_exchange_item`, `possession_preference`/`possession_preference_other`, `assets`/`assets_other`, `unit_size`/`unit_size_other`, `number_of_unit_type`/`_other`, `business_type_selected`/`other_business_type`, `garage_parking_spaces_option`/`other_parking_space_wrapper`, `purchase_purpose`/`purchase_purpose_other`, `flood_zone_tolerance`/`flood_zone_tolerance_other`, `lease_type`/`lease_type_other`.
- **Landlord:** `condition_prop`/`other_property_condition`, `appliances`/`other_appliances`+`appliances_other`, `water_access`/`_other`, `water_view`/`_other`, `interior_features`/`_other`, `view_preference`/`other_preferences`, `non_negotiable_amenities`/`_other`, `roof_type`/`_other`, `exterior_construction`/`_other`, `foundation`/`_other`, heating/AC/water/sewer/utilities `_other`, `laundry_features`/`_other`, `floor_covering`/`_other`, `security_features`/`_other`, `rent_includes`/`other_rent_include`, `desired_lease_length`/`other_lease_term`, all applicant `custom_*` companions, `flood_zone_code`/`_other`, `association_type`/`_other`, `association_fee_frequency`/`_other`, `association_fee_includes`/`_other`, `association_amenities`/`_other`, `min_lease_period`/`_other`; Commercial: `garage_parking_spaces_option`/`other_parking_space_wrapper`, `road_surface_type`/`_other`, `electrical_service`/`_other`, `building_features`/`_other`, `tenant_pays`/`other_tenant_pays`+`tenant_pays_other`, `owner_pays`/`other_owner_pays`+`owner_pays_other`, `terms_of_lease`/`custom_lease_term`+`other_lease_term`, `commercial_lease_type`/`_other`.
- **Tenant:** `property_items`/`other_property_items`, `condition_prop_buyer`/`other_property_condition`, `bedrooms`/`other_bedrooms`, `bathrooms`/`other_bathrooms`, `view_preference`/`other_preferences`, `non_negotiable_amenities`/`other_non_negotiable_amenities`, `lease_for`/`other_lease_for`, `garage_parking_spaces_option`/`other_parking_space_wrapper`, `lease_fee_type`/`lease_fee_other`; **exception:** `rental_purpose` "Other" has no companion field.

### 17. Fields that should likely become lifestyle/property metadata tags later
- **Property/lifestyle:** `waterfront`, water access/view, `view_preference`, `pool_needed`, `leasing_55_plus` (age-restricted), pets (allowed + service/ESA), `smoking_policy`/`smoking_preference`, garage/carport, `interior_features`, `non_negotiable_amenities`, `total_acreage`, `buildable` (land), `current_use`/`current_adjacent_use` (land), `intended_business_use` (commercial).
- **Financial/tag:** `offered_financing` types, `sale_provision` types, `minimum_cap_rate`, `commercial_lease_type`/`terms_of_lease`, `purchase_purpose`, `rental_purpose`.
- **Location intelligence:** commute (zip/minutes/mode), Search-Areas blob, flood zone, HOA presence/fee, school/POI proximity (Location DNA output).
- **Screening/target-audience:** `credit_score_range`, `income_qualification_method`, `pet_policy_requirement`, `min_credit_score`, `real_estate_purchase` (business vs building), `occupant_status`.
- **Yes/No → richer semantics for AI** (convert to labeled enums with reasons): `nda_required`, `financial_statements_available`, `as_is_purchase`, `pre_approved`, `assumable_interest`, `hoa_acceptance`, `personal_guarantee_*`, `first_month_rent_available`/`last_month_rent_available`, `screening_concerns`, `carport_needed`/`garage_needed`/`pool_needed`.

---

# Files Reviewed

**Livewire components**
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php`, `…/SellerOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php`, `…/BuyerOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php`, `…/LandlordOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php`, `…/TenantOfferListingEdit.php`
- `app/Http/Livewire/OfferListing/Concerns/{HasImportantPlaces,HasMlsImport,LandlordPublishValidation,SellerPublishValidation}.php`

**Blade wizard views**
- `resources/views/livewire/offer-listing/{seller,buyer,landlord,tenant}/offer-*-listing.blade.php` (+ `-edit`)
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/*.blade.php` (seller-info, listing-details, property-preferences, financial-details, additional-details, seller-terms, broker-compensation, tax-legal-hoa-disclosures, documents-disclosures, photos-tours-documents)
- `resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/*.blade.php` (buyer-info, listing-details, property-preferences, purchasing-terms, additional-details, broker-compensation)
- `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/*.blade.php` + `partials/*` (all 16)
- `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/*.blade.php` + `flat-fee/*` (orphaned, reviewed for completeness)
- `resources/views/livewire/offer-listing/shared/{ai-questions-input,mls-import-modal}.blade.php`, `…/shared/partials/ai-question-field.blade.php`

**Public detail views**
- `resources/views/offer-listing/{seller,buyer,landlord,tenant}/view.blade.php`

**Models**
- `app/Models/{SellerAgentAuction,BuyerAgentAuction,BuyerAgentAuctionMeta,LandlordAgentAuction,LandlordAgentAuctionMeta,TenantAgentAuction,TenantAgentAuctionMeta,OfferAuction}.php`

**Migrations**
- `create_seller_agent_auctions_table`, `create_seller_agent_auction_metas_table`, `add_missing_columns_to_seller_agent_auctions_table`, `add_referral_columns_to_seller_agent_auctions_table`
- `create_buyer_agent_auctions_table`, `create_buyer_agent_auction_metas_table`, `add_is_draft…`, `add_referral_columns…`, `add_listing_id_to_auctions_tables`
- `create_landlord_agent_auctions_table`, `create_landlord_agent_auction_metas_table`, `add_display_bids…`, `add_auction_ended…`, `add_referral_columns…`, `add_title…`, `add_is_archived_to_agent_auction_tables`
- `create_tenant_agent_auctions_table`, `create_tenant_agent_auction_metas_table`, `add_referral_columns_to_tenant_agent_auctions_table`, `add_title_to_tenant_agent_auctions_table`

**Controllers**
- `app/Http/Controllers/{SellerOfferListingController,BuyerOfferListingController,LandlordOfferListingController,TenantOfferListingController}.php`
- `app/Http/Controllers/AskAiListingQuestionController.php`

**Matching / scoring**
- `app/Helpers/{SellerBidMatchScoreHelper,BuyerBidMatchScoreHelper,LandlordBidMatchScoreHelper,TenantBidMatchScoreHelper}.php`, `app/Traits/AgentMatchSubScorer.php`
- `config/match_scoring.php`, `config/bya_compatibility.php`

**Config**
- `config/{seller,buyer,landlord,tenant}_services_order.php`, `config/agent_preset_compensation.php`, `config/ai_faq_buyer.php` (and per-role `ai_faq_*` referenced)

**Ask AI**
- `app/Services/AskAi/{AskAiFieldQuestionRegistryService,AskAiKnowledgeSnapshotBuilderService,AskAiContextBuilderService,AskAiFaqEnrichmentService}.php`, `app/Console/Commands/SyncFaqAnswers.php`, `app/Services/AskAi/Snapshot/BuyerSnapshotBuilder.php`

**DNA / observers**
- `app/Services/Dna/{PropertyDnaGenerator,BuyerTenantDnaGenerator}.php`, `app/Observers/Dna/PropertyAuctionDnaObserver.php`, `app/Services/LocationDna/*` (referenced), job `ComputeLocationDna`

> Items marked "Needs verification" in the tables above were not confirmed in code and should be checked before acting on them.

---

# Recommended Next Audit Step

The next step is to **compare this Bid Your Offer field audit against the uploaded Stellar MLS data-entry forms** — Residential, Income, Commercial Sale, Business Opportunity, Vacant Land, Rental, and Commercial Lease (`attached_assets/*_Data_Entry_Form_*.pdf`). For each Stellar form field, determine whether an equivalent already exists in the corresponding Bid Your Offer flow (matching on label + field key + property type above), and produce a gap list of missing/weak fields to add for Property DNA, Buyer DNA, Tenant DNA, Location DNA, lifestyle tags, matching, Ask AI, search, marketing, and target-audience intelligence — prioritizing the "missing or weak" (item 14), "DNA metadata" (item 13), and "lifestyle/property tags" (item 17) findings above.
