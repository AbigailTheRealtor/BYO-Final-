# Agent Offer Preset ↔ Bid Field Crosswalk Audit

**Scope:** All 14 role × property-type combinations  
**Produced:** 2026-06-12  
**Audit boundary:** Documentation only. No production code was modified.  
**Verification:** `php artisan view:cache` → "Blade templates cached successfully!" (no errors).

---

## Contents

1. [Purpose](#1-purpose)
2. [Role / Property-Type Combinations](#2-role--property-type-combinations)
3. [Match Status Definitions](#3-match-status-definitions)
4. [Field Safety Tier Definitions](#4-field-safety-tier-definitions)
5. [Section A — Preset Field Inventory](#5-section-a--preset-field-inventory)
6. [Section B — Bid Field Inventory](#6-section-b--bid-field-inventory)
7. [Section C — Master Crosswalk](#7-section-c--master-crosswalk)
8. [Section D — Auto-Populate Table](#8-section-d--auto-populate-table)
9. [Section E — Bid-Specific Field Table](#9-section-e--bid-specific-field-table)
10. [Section F — Do Not Auto-Populate Rules](#10-section-f--do-not-auto-populate-rules)
11. [Section G — Two-Way Compatibility Crosswalk](#11-section-g--two-way-compatibility-crosswalk)
12. [Section H — Quick Match vs Full Match Readiness](#12-section-h--quick-match-vs-full-match-readiness)
13. [Section I — Prioritized Implementation Plan (P1–P5)](#13-section-i--prioritized-implementation-plan)
14. [Appendix A — Bugs & Cross-Role Value Inconsistencies](#14-appendix-a--bugs--cross-role-value-inconsistencies)
15. [Appendix B — Coverage Checklist](#15-appendix-b--coverage-checklist)
16. [Appendix C — Source File Index](#16-appendix-c--source-file-index)

---

## 1. Purpose

Ground-truth field-level audit comparing every Agent Offer Preset field against every Agent Bid field for all 14 role/property-type combinations, produced before any auto-population implementation begins. No production code is changed.

The primary wiring pipeline is `AgentBidMapperService::mapFromProfile()`.

---

## 2. Role / Property-Type Combinations

| # | Role | Property Type | Bid Component |
|---|------|--------------|--------------|
| 1 | Seller | residential | `SellerAgentAuctionBid` |
| 2 | Seller | income | `SellerAgentAuctionBid` |
| 3 | Seller | commercial | `SellerAgentAuctionBid` |
| 4 | Seller | business | `SellerAgentAuctionBid` |
| 5 | Seller | vacant_land | `SellerAgentAuctionBid` |
| 6 | Buyer | residential | `BuyerAgentAuctionBid` |
| 7 | Buyer | income | `BuyerAgentAuctionBid` |
| 8 | Buyer | commercial | `BuyerAgentAuctionBid` |
| 9 | Buyer | business | `BuyerAgentAuctionBid` |
| 10 | Buyer | vacant_land | `BuyerAgentAuctionBid` |
| 11 | Landlord | residential | `LandlordAgentAuctionBid` |
| 12 | Landlord | commercial | `LandlordAgentAuctionBid` |
| 13 | Tenant | residential | `TenantAgentAuctionBid` |
| 14 | Tenant | commercial | `TenantAgentAuctionBid` |

---

## 3. Match Status Definitions

| Code | Meaning |
|------|---------|
| `EXACT` | Preset key = bid property name; stored value format is identical |
| `MAPPED` | Different key names but mapper correctly connects them |
| `VALUE_MISMATCH` | Same logical field; stored value format differs by role |
| `KEY_MISMATCH_BUG` | Mapper emits key X; component property is named Y — silent data loss |
| `PRESET_ONLY` | In `profile_data` but not emitted by mapper; never reaches bid form |
| `BID_ONLY` | In bid component; no preset source |
| `PARTIAL_ROLE` | Mapper emits for all roles; some bid components lack the property |
| `ROLE_INCONSISTENT` | All roles have the field but stored value format differs by role |
| `TYPO_SYMMETRIC` | Same typo in mapper + component + DB meta; consistent, safe, do not fix in isolation |
| `MISSING_BOTH` | Conceptual field not yet in either surface |
| `MUST_REVIEW` | Correctly mapped; auto-populates; sensitive — agent must review before submit |

---

## 4. Field Safety Tier Definitions

| Tier | Name | Behaviour on preset load | Example |
|------|------|--------------------------|---------|
| **T1** | Safe Default | Copy as-is; no review needed | `bio`, `first_name` |
| **T2** | Suggested-but-Editable | Pre-fill; agent reviews before submitting | `commission_structure`, `purchase_fee_percentage` |
| **T3** | Bid-Only | Never sourced from preset; leave blank or compute per bid | `gap_payment_amount`, `photo_enhancements` |
| **T4** | Missing Field | Needed but absent from both surfaces | geographic coverage in bids |
| **T5** | Must-Not-Save-Back | May be copied preset→bid; must never be written bid→preset | `referral_fee_percent` when deal-specific |

---

## 5. Section A — Preset Field Inventory

**Schema:** Role | Property Types | Section | Label | Input Name (blade `name=`) | Livewire Property (preset component) | DB Storage | Required? | Field Type | Notes

"All" in Role = Seller, Buyer, Landlord, Tenant. Property Types "All" = all combinations for that role.

### A.1 — Profile-Only Fields (not mapped to bid forms)

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Storage | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|-----------|:--------:|-----------|-------|
| All | All | Quick Highlights | Years of Experience | `years_experience` | `years_experience` | `profile_data['years_experience']` | No | number | PRESET_ONLY |
| All | All | Quick Highlights | Transactions (Last 12 mo.) | `transactions_last_12_months` | `transactions_last_12_months` | `profile_data[…]` | No | number | PRESET_ONLY |
| All | All | Quick Highlights | Avg. Response Time | `avg_response_time` | `avg_response_time` | `profile_data[…]` | No | text | PRESET_ONLY |
| All | All | Quick Highlights | Full-Time Agent? | `is_full_time` | `is_full_time` | `profile_data[…]` | No | select | PRESET_ONLY |
| All | All | Quick Highlights | Primary Areas Served | `primary_areas_served` | `primary_areas_served` | `profile_data[…]` | No | text | PRESET_ONLY |
| All | All | Areas Served | Cities Served | `cities_served` | `cities_served` | `profile_data[…]` | No | multi-select | PRESET_ONLY |
| All | All | Areas Served | Counties Served | `counties_served` | `counties_served` | `profile_data[…]` | No | multi-select | PRESET_ONLY |
| All | All | Areas Served | Neighborhoods | `neighborhoods_served` | `neighborhoods_served` | `profile_data[…]` | No | multi-select | PRESET_ONLY |
| All | All | Areas Served | Areas Notes | `areas_notes` | `areas_notes` | `profile_data[…]` | No | textarea | PRESET_ONLY |
| All | All | Social Proof | Review 1 | `review_1` | `review_1` | `profile_data[…]` | No | textarea | PRESET_ONLY |
| All | All | Social Proof | Review 2 | `review_2` | `review_2` | `profile_data[…]` | No | textarea | PRESET_ONLY |
| All | All | Social Proof | Review 3 | `review_3` | `review_3` | `profile_data[…]` | No | textarea | PRESET_ONLY |
| All | All | Social Proof | Awards & Recognition | `awards_recognition` | `awards_recognition` | `profile_data[…]` | No | textarea | PRESET_ONLY |
| All | All | Video Intro | Intro Video URL | `intro_video_url` | `intro_video_url` | `profile_data[…]` | No | url | PRESET_ONLY |
| All | All | Video Intro | Video Caption | `video_caption` | `video_caption` | `profile_data[…]` | No | text | PRESET_ONLY |
| All | All | Availability | Availability Status | `availability_status` | `availability_status` | `profile_data[…]` | No | select | PRESET_ONLY |
| All | All | Availability | Evenings Available | `evenings_available` | `evenings_available` | `profile_data[…]` | No | checkbox | PRESET_ONLY |
| All | All | Availability | Weekends Available | `weekends_available` | `weekends_available` | `profile_data[…]` | No | checkbox | PRESET_ONLY |
| All | All | Availability | Communication Style | `communication_style` | `communication_style` | `profile_data[…]` | No | select | PRESET_ONLY |
| All | All | Availability | Preferred Contact Method | `preferred_contact_method` | `preferred_contact_method` | `profile_data[…]` | No | select | PRESET_ONLY |

### A.2 — Agent Overview & Credentials (mapped to bid)

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Storage | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|-----------|:--------:|-----------|-------|
| All | All | Overview | About Agent | `bio` | `bio` | `profile_data['bio']` | Yes (bid) | textarea | T1 |
| All | All | Overview | Why Should You Be Hired | `why_hire_you` | `why_hire_you` | `profile_data['why_hire_you']` | Yes (bid) | textarea | T1 |
| All | All | Overview | What Sets You Apart | `what_sets_you_apart` | `what_sets_you_apart` | `profile_data['what_sets_you_apart']` | Yes (bid) | textarea | T1 |
| All | All | Overview | Marketing Strategy | `marketing_plan` | `marketing_plan` | `profile_data['marketing_plan']` | Yes (bid) | textarea | T1 |
| All | All | Overview | Year Licensed | `year_licensed` | `year_licensed` | `profile_data['year_licensed']` | Yes (bid) | number | T1 |
| All | All | Overview | Additional Details | `additional_details` | `additional_details` | `profile_data['additional_details']` | No | textarea | T1 |
| All | All | Credentials | First Name | `first_name` | `first_name` | `profile_data['first_name']` | Yes (bid) | text | T1; !empty() guard |
| All | All | Credentials | Last Name | `last_name` | `last_name` | `profile_data['last_name']` | Yes (bid) | text | T1 |
| All | All | Credentials | Phone | `phone` | `phone` | `profile_data['phone']` | Yes (bid) | text | T1 |
| All | All | Credentials | Email | `email` | `email` | `profile_data['email']` | Yes (bid) | email | T1 |
| All | All | Credentials | Brokerage | `brokerage` | `brokerage` | `profile_data['brokerage']` | Yes (bid) | text | T1 |
| All | All | Credentials | License No. | `license_no` | `license_no` | `profile_data['license_no']` | Yes (bid) | text | T1 |
| All | All | Credentials | NAR ID | `nar_id` | `nar_id` | `profile_data['nar_id']` | No | text | T1 |

### A.3 — Media, Links & Services (mapped to bid)

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Storage | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|-----------|:--------:|-----------|-------|
| All | All | Presentation | Presentation Link | `presentation_link` | `presentation_link` | `profile_data['presentation_link']` | No | url | T1 |
| All | All | Presentation | Presentation Upload Path | `presentation_upload_path` | `presentation_upload_path` | `profile_data['presentation_upload_path']` | No | file path | T1 |
| All | All | Presentation | Business Card Link | `business_card_link` | `business_card_link` | `profile_data['business_card_link']` | No | url | T1 |
| All | All | Presentation | Business Card Stored Path | `business_card_stored_path` | `business_card_stored_path` | `profile_data['business_card_stored_path']` | No | file path | T1 |
| All | All | Presentation | Business Card Upload Path | `business_card_upload_path` | `business_card_upload_path` | `profile_data['business_card_upload_path']` | No | file path | T1 |
| All | All | Presentation | Promo Materials | `promoMaterials` | `promoMaterials` | `profile_data['promoMaterials']` | No | JSON array | T1 |
| All | All | Overview | Review Links | `reviews_links[]` | `reviews_links` | `profile_data['reviews_links']` | No | array | T1 |
| All | All | Overview | Website Link | `website_link` | `website_link` | `profile_data['website_link']` | No | array | T1; stored as array |
| All | All | Overview | Social Media | `social_media[]` | `social_media` | `profile_data['social_media']` | No | array | T1 |
| All | All | Services | Services (catalog) | `services[]` | `services` | `profile_data['services']` | No | checkbox array | T1; filtered by catalog on bid load |
| All | All | Services | Other Services | `other_services[]` | `other_services` | `profile_data['other_services']` | No | text array | T1 |

### A.4 — Shared Agency Agreement (mapped to bid, all roles)

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Storage | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|-----------|:--------:|-----------|-------|
| All | All | Compensation | Protection Period (Days) | `protection_period` | `protection_period` | `profile_data['protection_period']` | No | number | T2 |
| All | All | Compensation | Early Termination Fee | `early_termination_fee_option` | `early_termination_fee_option` | `profile_data['early_termination_fee_option']` | No | select (yes/no) | T2; ⚠ Tenant stores `'Yes'/'No'`, others `'yes'/'no'` |
| All | All | Compensation | Early Termination Fee Amount | `early_termination_fee_amount` | `early_termination_fee_amount` | `profile_data['early_termination_fee_amount']` | No | currency | T2 |
| All | All | Compensation | Agency Agreement Timeframe | `agency_agreement_timeframe` | `agency_agreement_timeframe` | `profile_data['agency_agreement_timeframe']` | No | select | T2; T5 must-not-save-back |
| All | All | Compensation | Agency Agreement Custom | `agency_agreement_custom` | `agency_agreement_custom` | `profile_data['agency_agreement_custom']` | No | text | T2; T5 |
| All | All | Compensation | Interested in Lease-Option Agmt | `interested_lease_option_agreement` | `interested_lease_option_agreement` | `profile_data['interested_lease_option_agreement']` | No | select (Yes/No) | T2 |
| All | All | Compensation | Lease-Option Comp. Type | `lease_type` | `lease_type` | `profile_data['lease_type']` | No | select (%/$) | T2 |
| All | All | Compensation | Lease-Option Amount | `lease_value` | `lease_value` | `profile_data['lease_value']` | No | text | T2 |
| All | All | Compensation | Purchase Option Type | `purchase_type` | `purchase_type` | `profile_data['purchase_type']` | No | select (%/$) | T2 |
| All | All | Compensation | Purchase Option Amount | `purchase_value` | `purchase_value` | `profile_data['purchase_value']` | No | text | T2 |

### A.5 — Shared Compensation (mapped to bid, all roles)

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Storage | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|-----------|:--------:|-----------|-------|
| All | All | Compensation | Commission Structure | `commission_structure` | `commission_structure` | `profile_data['commission_structure']` | No | select | T2 |
| All | All | Compensation | Purchase Fee Type | `purchase_fee_type` | `purchase_fee_type` | `profile_data['purchase_fee_type']` | No | select | T2; ⚠ Seller=slug, Buyer/Tenant=full text |
| All | All | Compensation | Purchase Fee (Flat $) | `purchase_fee_flat` | `purchase_fee_flat` | `profile_data['purchase_fee_flat']` | No | currency | T2 |
| All | All | Compensation | Purchase Fee (%) | `purchase_fee_percentage` | `purchase_fee_percentage` | `profile_data['purchase_fee_percentage']` | No | number | T2 |
| All | All | Compensation | Purchase Fee (% Combo) | `purchase_fee_percentage_combo` | `purchase_fee_percentage_combo` | `profile_data['purchase_fee_percentage_combo']` | No | number | T2 |
| All | All | Compensation | Purchase Fee ($ Combo) | `purchase_fee_flat_combo` | `purchase_fee_flat_combo` | `profile_data['purchase_fee_flat_combo']` | No | currency | T2 |
| All | All | Compensation | Purchase Fee (Other) | `purchase_fee_other` | `purchase_fee_other` | `profile_data['purchase_fee_other']` | No | text | T2 |
| Seller/Buyer/Tenant | All | Compensation | Retainer Fee | `retainer_fee_option` | `retainer_fee_option` | `profile_data['retainer_fee_option']` | No | select | T2; ⚠ Tenant=`'Yes'`, others=`'yes'` |
| Seller/Buyer/Tenant | All | Compensation | Retainer Fee Amount | `retainer_fee_amount` | `retainer_fee_amount` | `profile_data['retainer_fee_amount']` | No | currency | T2 |
| Seller/Buyer/Tenant | All | Compensation | Retainer Fee Application | `retainer_fee_application` | `retainer_fee_application` | `profile_data['retainer_fee_application']` | No | select | T2; ⚠ Tenant=slug, others=full sentence |

### A.6 — Shared Tail (mapped to bid, all roles)

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Storage | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|-----------|:--------:|-----------|-------|
| All | All | Compensation | Brokerage Relationship | `brokerage_relationship` | `brokerage_relationship` | `profile_data['brokerage_relationship']` | No | select | T2 |
| All | All | Compensation | Additional Broker Details | `additional_details_broker` | `additional_details_broker` | `profile_data['additional_details_broker']` | No | textarea | T1 |
| All | All | Referral | Referral Fee % | `referral_fee_percent` | `referral_fee_percent` | `profile_data['referral_fee_percent']` | No | number | T2; T5 must-not-save-back |

### A.7 — Buyer/Tenant Lease Fee Sub-fields

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Storage | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|-----------|:--------:|-----------|-------|
| Buyer | All | Compensation | Interested in Lease Agreement | `interested_lease_option` | `interested_lease_option` | `profile_data['interested_lease_option']` | No | select (Yes/No) | T2 |
| Buyer/Tenant | All | Compensation | Lease Fee Type | `lease_fee_type` | `lease_fee_type` | `profile_data['lease_fee_type']` | No | select | T2; ⚠ Buyer=`'flat'`, Tenant=`'Flat Fee'` |
| Buyer/Tenant | All | Compensation | Lease Fee (Flat $) | `lease_fee_flat` | `lease_fee_flat` | `profile_data['lease_fee_flat']` | No | currency | T2 |
| Buyer/Tenant | All | Compensation | Lease Fee (% Gross Lease) | `lease_fee_percentage` | `lease_fee_percentage` | `profile_data['lease_fee_percentage']` | No | number | T2 |
| Buyer/Tenant | residential | Compensation | Lease Fee (% Monthly Rent) | `lease_fee_percentage_monthly_rent` | `lease_fee_percentage_monthly_rent` | `profile_data[…]` | No | number | T2; residential only |
| Buyer/Tenant | residential | Compensation | Lease Fee (# of Months) | `lease_fee_percentage_monthly_number` | `lease_fee_percentage_monthly_number` | `profile_data[…]` | No | number | T2 |
| Buyer/Tenant | residential | Compensation | Lease Fee (Flat+% Gross Combo $) | `lease_fee_flat_combo` | `lease_fee_flat_combo` | `profile_data['lease_fee_flat_combo']` | No | currency | T2 |
| Buyer/Tenant | residential | Compensation | Lease Fee (Flat+% Gross Combo %) | `lease_fee_percentage_combo` | `lease_fee_percentage_combo` | `profile_data['lease_fee_percentage_combo']` | No | number | T2 |
| Buyer/Tenant | commercial+ | Compensation | Lease Fee (% Net Aggregate) | `lease_fee_percentage_net` | `lease_fee_percentage_net` | `profile_data['lease_fee_percentage_net']` | No | number | T2; commercial+ |
| Buyer/Tenant | commercial+ | Compensation | Lease Fee (Flat+% Net Combo $) | `lease_fee_flat_combo_net` | `lease_fee_flat_combo_net` | `profile_data['lease_fee_flat_combo_net']` | No | currency | T2 |
| Buyer/Tenant | commercial+ | Compensation | Lease Fee (Flat+% Net Combo %) | `lease_fee_percentage_combo_net` | `lease_fee_percentage_combo_net` | `profile_data['lease_fee_percentage_combo_net']` | No | number | T2 |
| Buyer/Tenant | All | Compensation | Lease Fee (Other) | `lease_fee_other` | `lease_fee_other` | `profile_data['lease_fee_other']` | No | text | T2 |

### A.8 — Seller-Specific Compensation Fields

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Storage | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|-----------|:--------:|-----------|-------|
| Seller | income/commercial/business | Compensation | Nominal Consideration Fee | `nominal` | `nominal` | `profile_data['nominal']` | No | currency | T2 |
| Seller | All | Compensation | Commission Structure Type | `commission_structure_type` | `commission_structure_type` | `profile_data['commission_structure_type']` | No | select | T2 |
| Seller | All | Compensation | Buyer's Broker Fee (Flat $) | `commission_structure_type_fee_flat` | `commission_structure_type_fee_flat` | `profile_data[…]` | No | currency | T2 |
| Seller | All | Compensation | Buyer's Broker Fee (Flat Combo $) | `commission_structure_type_fee_flat_combo` | `commission_structure_type_fee_flat_combo` | `profile_data[…]` | No | currency | T2 |
| Seller | All | Compensation | Buyer's Broker Fee (%) | `commission_structure_type_fee_percentage` | `commission_structure_type_fee_percentage` | `profile_data[…]` | No | number | T2 |
| Seller | All | Compensation | Buyer's Broker Fee (% Combo) | `commission_structure_type_fee_percentage_combo` | `commission_structure_type_fee_percentage_combo` | `profile_data[…]` | No | number | T2 |
| Seller | All | Compensation | Buyer's Broker Fee (Other) | `commission_structure_type_fee_other` | `commission_structure_type_fee_other` | `profile_data[…]` | No | text | T2 |
| Seller | All | Compensation | Interested in Offering Lease | `interested_purchase_fee_type` | `interested_purchase_fee_type` | `profile_data['interested_purchase_fee_type']` | No | select (Yes/No) | T2 |
| Seller | All | Compensation | Seller's Broker Leasing Fee Type | `seller_leasing_fee_type` | `seller_leasing_fee_type` | `profile_data['seller_leasing_fee_type']` | No | select | T2; options differ res/inc/vl vs com/bus |
| Seller | residential/income/vacant_land | Compensation | Leasing Fee (% Each Rental Period) | `seller_leasing_gross_rental` | `seller_leasing_gross_rental` | `profile_data[…]` | No | number | T2 |
| Seller | residential/income/vacant_land | Compensation | Leasing Fee (% Gross Lease) | `seller_leasing_gross` | `seller_leasing_gross` | `profile_data['seller_leasing_gross']` | No | number | T2 |
| Seller | residential/income/vacant_land | Compensation | Leasing Fee (% First Month's Rent) | `seller_leasing_gross_month_rent` | `seller_leasing_gross_month_rent` | `profile_data[…]` | No | number | T2 |
| Seller | residential/income/vacant_land | Compensation | Leasing Fee (% Net Aggregate Rent) | `seller_leasing_gross_other` | `seller_leasing_gross_other` | `profile_data['seller_leasing_gross_other']` | No | number | T2 |
| Seller | commercial/business | Compensation | Leasing Fee (% Gross Rent) | `seller_leasing_gross_percentage` | `seller_leasing_gross_percentage` | `profile_data[…]` | No | number | T2 |
| Seller | All | Compensation | Leasing Fee (Flat $) | `seller_leasing_gross_purchase_fee_flat_amount` | `seller_leasing_gross_purchase_fee_flat_amount` | `profile_data[…]` | No | currency | T2 |
| Seller | All | Compensation | Leasing Fee (Other) | `seller_leasing_gross_purchase_fee_other` | `seller_leasing_gross_purchase_fee_other` | `profile_data[…]` | No | text | T2 |
| Seller | All | Compensation | Leasing Fee (% Rental — Each) | `seller_leasing_each_rental` | `seller_leasing_each_rental` | `profile_data['seller_leasing_each_rental']` | No | number | T2 |
| Seller | All | Compensation | Leasing Fee (# of Months) | `seller_leasing_gross_no_of_months` | `seller_leasing_gross_no_of_months` | `profile_data[…]` | No | number | T2 |
| Seller | All | Compensation | Leasing Fee (Flat+% Gross Combo $) | `seller_leasing_gross_flat_combo` | `seller_leasing_gross_flat_combo` | `profile_data[…]` | No | currency | T2 |
| Seller | All | Compensation | Leasing Fee (Flat+% Gross Combo %) | `seller_leasing_gross_percentage_combo` | `seller_leasing_gross_percentage_combo` | `profile_data[…]` | No | number | T2 |
| Seller | All | Compensation | Leasing Fee (Flat+% Net Combo $) | `seller_leasing_gross_flat_net_combo` | `seller_leasing_gross_flat_net_combo` | `profile_data[…]` | No | currency | T2 |
| Seller | All | Compensation | Leasing Fee (Flat+% Net Combo %) | `seller_leasing_gross_percentage_net_combo` | `seller_leasing_gross_percentage_net_combo` | `profile_data[…]` | No | number | T2 |
| Seller | All | Compensation | Sales Tax (Gross Rent) | `seller_leasing_gross_sales_tax_option_gross` | `seller_leasing_gross_sales_tax_option_gross` | `profile_data[…]` | No | select | T2 |
| Seller | All | Compensation | Sales Tax (First Month) | `seller_leasing_gross_sales_tax_first_month` | `seller_leasing_gross_sales_tax_first_month` | `profile_data[…]` | No | select | T2 |
| Seller | commercial/business | Compensation | Sales Tax (Flat Fee) | `seller_leasing_gross_sales_tax_flat_free_gross` | `seller_leasing_gross_sales_tax_flat_free_gross` | `profile_data[…]` | No | select | T2 |
| Seller | All | Compensation | Seller's Broker Share of Retained Deposits | `retained_deposits` | `retained_deposits` | `profile_data['retained_deposits']` | No | number | T2; Seller preset only; others discard |

### A.9 — Landlord-Specific Compensation Fields

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Storage | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|-----------|:--------:|-----------|-------|
| Landlord | residential | Compensation | Leasing Fee (% Rental Period) | `purchase_fee_rental_period` | `purchase_fee_rental_period` | `profile_data[…]` | No | number | T2 |
| Landlord | commercial | Compensation | Leasing Fee (% Net Aggregate Rent) | `purchase_fee_net_aggregate` | `purchase_fee_net_aggregate` | `profile_data[…]` | No | number | T2 |
| Landlord | commercial | Compensation | Leasing Fee (% Gross Rent) | `purchase_fee_gross_rent` | `purchase_fee_gross_rent` | `profile_data['purchase_fee_gross_rent']` | No | number | T2 |
| Landlord | commercial | Compensation | Leasing Fee (% Monthly) | `purchase_fee_monthly_percentage` | `purchase_fee_monthly_percentage` | `profile_data[…]` | No | number | T2 |
| Landlord | commercial | Compensation | Leasing Fee (# of Months) | `purchase_fee_months` | `purchase_fee_months` | `profile_data['purchase_fee_months']` | No | number | T2 |
| Landlord | commercial | Compensation | Sales Tax (Monthly) | `sales_tax_option_monthly` | `sales_tax_option_monthly` | `profile_data[…]` | No | select | T2 |
| Landlord | commercial | Compensation | Leasing Fee (Flat — Commercial) | `purchase_fee_flat_commercial` | `purchase_fee_flat_commercial` | `profile_data[…]` | No | currency | T2 |
| Landlord | commercial | Compensation | Sales Tax (Flat) | `sales_tax_option_flat` | `sales_tax_option_flat` | `profile_data['sales_tax_option_flat']` | No | select | T2 |
| Landlord | commercial | Compensation | Leasing Fee (Other — Commercial) | `purchase_fee_other_commercial` | `purchase_fee_other_commercial` | `profile_data[…]` | No | text | T2 |
| Landlord | commercial | Compensation | Leasing Fee (% Purchase Price) | `purchase_fee_purchase_price` | `purchase_fee_purchase_price` | `profile_data[…]` | No | number | T2 |
| Landlord | residential | Compensation | Tenant Broker Commission Structure | `tenant_broker_commission_structure` | `tenant_broker_commission_structure` | `profile_data[…]` | No | select | T2 |
| Landlord | residential | Compensation | Tenant Broker Fee Structure | `tenant_broker_fee_structure` | `tenant_broker_fee_structure` | `profile_data[…]` | No | select | T2 |
| Landlord | residential | Compensation | Tenant Broker Fee (%) | `tenant_broker_percentage` | `tenant_broker_percentage` | `profile_data[…]` | No | number | T2 |
| Landlord | residential | Compensation | Tenant Broker Fee (% Gross Lease) | `tenant_broker_gross_lease` | `tenant_broker_gross_lease` | `profile_data[…]` | No | number | T2 |
| Landlord | residential | Compensation | Tenant Broker Fee (% First Month) | `tenant_broker_first_month_rent` | `tenant_broker_first_month_rent` | `profile_data[…]` | No | number | T2 |
| Landlord | residential | Compensation | Tenant Broker Fee (Flat $) | `tenant_broker_flat_fee` | `tenant_broker_flat_fee` | `profile_data['tenant_broker_flat_fee']` | No | currency | T2 |
| Landlord | residential | Compensation | Tenant Broker Fee (Other) | `tenant_broker_other` | `tenant_broker_other` | `profile_data['tenant_broker_other']` | No | text | T2 |
| Landlord | residential/commercial | Compensation | Broker Fee Timing | `broker_fee_timing` | `broker_fee_timing` | `profile_data['broker_fee_timing']` | No | select | T2 |
| Landlord | residential | Compensation | Days from Rent Collected | `broker_fee_days_from_rent` | `broker_fee_days_from_rent` | `profile_data[…]` | No | number | T2 |
| Landlord | residential | Compensation | Days After Executed Lease | `broker_fee_days_after_lease` | `broker_fee_days_after_lease` | `profile_data[…]` | No | number | T2 |
| Landlord | residential | Compensation | Days After Tenant Rent Payment | `broker_fee_days_after_rent` | `broker_fee_days_after_rent` | `profile_data[…]` | No | number | T2 |
| Landlord | residential/commercial | Compensation | Broker Fee Timing (Other) | `broker_fee_timing_other` | `broker_fee_timing_other` | `profile_data[…]` | No | text | T2 |
| Landlord | residential/commercial | Compensation | Split Payment Due | `split_payment_due` | `split_payment_due` | `profile_data['split_payment_due']` | No | select | T2; ⚠ TYPO_SYMMETRIC stored value `'uponoccupancy'` |
| Landlord | residential/commercial | Compensation | Split Payment Due (Other) | `split_payment_due_other` | `split_payment_due_other` | `profile_data[…]` | No | text | T2 |
| Landlord | residential/commercial | Compensation | Days After Due Event | `broker_fee_days_after_due_event` | `broker_fee_days_after_due_event` | `profile_data[…]` | No | number | T2 |
| Landlord | residential/commercial | Compensation | Renewal Fee Type | `renewal_fee_type` | `renewal_fee_type` | `profile_data['renewal_fee_type']` | No | select | T2 |
| Landlord | residential/commercial | Compensation | Renewal Fee (%) | `renewal_fee_percentage` | `renewal_fee_percentage` | `profile_data[…]` | No | number | T2 |
| Landlord | residential/commercial | Compensation | Renewal Fee (% Gross Lease) | `renewal_fee_lease_value` | `renewal_fee_lease_value` | `profile_data['renewal_fee_lease_value']` | No | number | T2 |
| Landlord | residential/commercial | Compensation | Renewal Fee (% First Month) | `renewal_fee_first_month` | `renewal_fee_first_month` | `profile_data['renewal_fee_first_month']` | No | number | T2 |
| Landlord | residential/commercial | Compensation | Renewal Fee (Flat $) | `renewal_fee_flat_fee` | `renewal_fee_flat_fee` | `profile_data['renewal_fee_flat_fee']` | No | currency | T2; 🔴 KEY_MISMATCH_BUG — component property = `renewal_fee_flat_free` |
| Landlord | residential/commercial | Compensation | Renewal Fee (Custom) | `renewal_fee_custom` | `renewal_fee_custom` | `profile_data['renewal_fee_custom']` | No | text | T2 |
| Landlord | residential/commercial | Compensation | Renewal Fee Sales Tax (Lease Value) | `renewal_fee_sales_tax_lease_value` | `renewal_fee_sales_tax_lease_value` | `profile_data[…]` | No | select | T2 |
| Landlord | residential/commercial | Compensation | Renewal Fee (# of Months) | `renewal_fee_no_of_months` | `renewal_fee_no_of_months` | `profile_data['renewal_fee_no_of_months']` | No | number | T2 |
| Landlord | residential/commercial | Compensation | Renewal Fee Sales Tax (First Month) | `renewal_fee_sales_tax_first_month` | `renewal_fee_sales_tax_first_month` | `profile_data[…]` | No | select | T2 |
| Landlord | residential/commercial | Compensation | Renewal Fee Sales Tax (Flat) | `renewal_fee_sales_tax_flat_fee` | `renewal_fee_sales_tax_flat_fee` | `profile_data[…]` | No | select | T2 |
| Landlord | commercial | Compensation | Expansion Commission (%) | `expansion_commission_percentage` | `expansion_commission_percentage` | `profile_data[…]` | No | number | T2 |
| Landlord | residential/commercial | Compensation | Interested in Property Management | `interested_in_property_management` | `interested_in_property_management` | `profile_data[…]` | No | select (Yes/No) | T2 |
| Landlord | residential/commercial | Compensation | Property Mgmt Fee Type | `interested_in_property_management_fee` | `interested_in_property_management_fee` | `profile_data[…]` | No | select | T2 |
| Landlord | residential/commercial | Compensation | Property Mgmt Fee (% Gross Lease) | `interested_in_property_management_fee_gross_lease` | `interested_in_property_management_fee_gross_lease` | `profile_data[…]` | No | number | T2 |
| Landlord | residential/commercial | Compensation | Property Mgmt Fee (% Rental Period) | `interested_in_property_management_fee_rental_periord` | `interested_in_property_management_fee_rental_periord` | `profile_data[…]` | No | number | T2; ⚠ typo `periord` symmetric |
| Landlord | residential/commercial | Compensation | Property Mgmt Fee (Flat $) | `interested_in_property_management_fee_flate_free` | `interested_in_property_management_fee_flate_free` | `profile_data[…]` | No | currency | T2; ⚠ typo `flate_free` symmetric |
| Landlord | residential/commercial | Compensation | Property Mgmt Fee (Other) | `interested_in_property_management_fee_other` | `interested_in_property_management_fee_other` | `profile_data[…]` | No | text | T2 |
| Landlord | residential/commercial | Compensation | Interested in Selling | `interested_in_selling` | `interested_in_selling` | `profile_data['interested_in_selling']` | No | select (Yes/No) | T2 |
| Landlord | residential/commercial | Compensation | Selling Fee Type | `interested_in_selling_type` | `interested_in_selling_type` | `profile_data[…]` | No | select | T2 |
| Landlord | residential/commercial | Compensation | Selling Fee: Basis (Purchase Price) | `landlord_broker_purchase_price` | `landlord_broker_purchase_price` | `profile_data[…]` | No | select | T2 |
| Landlord | residential/commercial | Compensation | Selling Fee (% of Price) | `landlord_broker_percentage_price` | `landlord_broker_percentage_price` | `profile_data[…]` | No | number | T2 |
| Landlord | residential/commercial | Compensation | Selling Fee ($ Amount) | `landlord_broker_dollar_price` | `landlord_broker_dollar_price` | `profile_data[…]` | No | currency | T2 |
| Landlord | residential/commercial | Compensation | Selling Fee (Flat $) | `landlord_broker_flate_fee` | `landlord_broker_flate_fee` | `profile_data[…]` | No | currency | T2; ⚠ typo `flate_fee` symmetric |
| Landlord | residential/commercial | Compensation | Selling Fee (Other) | `landlord_broker_other` | `landlord_broker_other` | `profile_data['landlord_broker_other']` | No | text | T2 |

### A.10 — Tenant-Specific Compensation Fields

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Storage | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|-----------|:--------:|-----------|-------|
| Tenant | residential/commercial | Compensation | Interested in Purchasing | `interested_purchase_fee_type` | `interested_purchase_fee_type` | `profile_data[…]` | No | select (Yes/No) | T2 |
| Tenant | residential/commercial | Compensation | Tenant's Broker Purchase Fee Type | `purchase_fee_type` | `purchase_fee_type` | `profile_data['purchase_fee_type']` | No | select | T2; full text values (under Interested in Purchasing gate) |
| Tenant | residential/commercial | Compensation | Broker Fee Timing | `broker_fee_timing` | `broker_fee_timing` | `profile_data['broker_fee_timing']` | No | select | T2; options differ residential vs commercial |
| Tenant | residential | Compensation | Days from Rent Collected | `broker_fee_days_from_rent` | `broker_fee_days_from_rent` | `profile_data[…]` | No | number | T2 |
| Tenant | residential | Compensation | Days After Executed Lease | `broker_fee_days_after_lease` | `broker_fee_days_after_lease` | `profile_data[…]` | No | number | T2 |
| Tenant | residential | Compensation | Days After Tenant Rent Payment | `broker_fee_days_after_rent` | `broker_fee_days_after_rent` | `profile_data[…]` | No | number | T2 |
| Tenant | residential/commercial | Compensation | Broker Fee Timing (Other) | `broker_fee_timing_other` | `broker_fee_timing_other` | `profile_data[…]` | No | text | T2 |

---

## 6. Section B — Bid Field Inventory

**Schema:** Role | Property Types | Section | Label | Input Name (`wire:model=` / `name=`) | Livewire Property | DB Meta Key | Required? | Field Type | Notes

### B.1 — Required Fields (all roles)

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Meta Key | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|------------|:--------:|-----------|-------|
| All | All | Agent Overview | About Agent | `bio` | `bio` | `bio` | **Yes** | textarea | T1 |
| All | All | Agent Overview | Why Should You Be Hired | `why_hire_you` | `why_hire_you` | `why_hire_you` | **Yes** | textarea | T1 |
| All | All | Agent Overview | What Sets You Apart | `what_sets_you_apart` | `what_sets_you_apart` | `what_sets_you_apart` | **Yes** | textarea | T1 |
| All | All | Agent Overview | Marketing Strategy | `marketing_plan` | `marketing_plan` | `marketing_plan` | **Yes** | textarea | T1 |
| All | All | Agent Overview | Year Licensed | `year_licensed` | `year_licensed` | `year_licensed` | **Yes** | number | T1 |
| All | All | Agent Info | First Name | `first_name` | `first_name` | `first_name` | **Yes** | text | T1 |
| All | All | Agent Info | Last Name | `last_name` | `last_name` | `last_name` | **Yes** | text | T1 |
| All | All | Agent Info | Phone | `phone` | `phone` | `phone` | **Yes** | text | T1 |
| All | All | Agent Info | Email | `email` | `email` | `email` | **Yes** | email | T1 |
| All | All | Agent Info | Brokerage | `brokerage` | `brokerage` | `brokerage` | **Yes** | text | T1 |
| All | All | Agent Info | License No. | `license_no` | `license_no` | `license_no` | **Yes** | text | T1 |

### B.2 — Optional Standard Fields (all roles)

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Meta Key | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|------------|:--------:|-----------|-------|
| All | All | Agent Info | NAR ID | `nar_id` | `nar_id` | `nar_id` | No | text | T1 |
| All | All | Agent Overview | Additional Details | `additional_details` | `additional_details` | `additional_details` | No | textarea | T1 |
| All | All | Agent Overview | Review Links | `reviews_links[]` | `reviews_links` | `reviews_links` | No | array | T1 |
| All | All | Agent Overview | Website Link | `website_link` | `website_link` | `website_link` | No | array | T1 |
| All | All | Agent Overview | Social Media | `social_media[]` | `social_media` | `social_media` | No | array | T1 |
| All | All | Presentation | Presentation Link | `presentation_link` | `presentation_link` | `presentation_link` | No | url | T1 |
| All | All | Presentation | Business Card Link | `business_card_link` | `business_card_link` | `business_card_link` | No | url | T1 |
| All | All | Presentation | Business Card (Stored Path) | *(upload)* | `business_card_stored_path` | `business_card_stored_path` | No | file path | T1 |
| All | All | Presentation | Promo Materials | *(upload/link array)* | `promoMaterials` | `promoMaterials` | No | JSON array | T1 |
| All | All | Services | Services (catalog) | `services[]` | `services` | `services` | No | checkbox array | T1 |
| All | All | Services | Other Services | `other_services[]` | `other_services` | `other_services` | No | text array | T1 |
| All | All | Services | Other Services Enabled | *(toggle)* | `other_services_enabled` | `other_services_enabled` | No | bool | BID_ONLY; not in mapper |
| All | All | Referral | Referral Fee % | `referral_fee_percent` | `referral_fee_percent` | `referral_fee_percent` | No | number | T2; T5 |

### B.3 — Shared Broker Compensation Fields (all applicable roles)

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Meta Key | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|------------|:--------:|-----------|-------|
| All | All | Broker Comp. | Commission Structure | `commission_structure` | `commission_structure` | `commission_structure` | No | select | T2 |
| All | All | Broker Comp. | Purchase Fee Type | `purchase_fee_type` | `purchase_fee_type` | `purchase_fee_type` | No | select | T2; ⚠ slug/text differs |
| All | All | Broker Comp. | Purchase Fee (Flat $) | `purchase_fee_flat` | `purchase_fee_flat` | `purchase_fee_flat` | No | currency | T2 |
| All | All | Broker Comp. | Purchase Fee (%) | `purchase_fee_percentage` | `purchase_fee_percentage` | `purchase_fee_percentage` | No | number | T2 |
| All | All | Broker Comp. | Purchase Fee (% Combo) | `purchase_fee_percentage_combo` | `purchase_fee_percentage_combo` | `purchase_fee_percentage_combo` | No | number | T2 |
| All | All | Broker Comp. | Purchase Fee ($ Combo) | `purchase_fee_flat_combo` | `purchase_fee_flat_combo` | `purchase_fee_flat_combo` | No | currency | T2 |
| All | All | Broker Comp. | Purchase Fee (Other) | `purchase_fee_other` | `purchase_fee_other` | `purchase_fee_other` | No | text | T2 |
| All | All | Broker Comp. | Lease-Option Agreement | `interested_lease_option_agreement` | `interested_lease_option_agreement` | `interested_lease_option_agreement` | No | select (Yes/No) | T2 |
| All | All | Broker Comp. | Lease-Option Comp. Type | `lease_type` | `lease_type` | `lease_type` | No | select (%/$) | T2 |
| All | All | Broker Comp. | Lease-Option Amount | `lease_value` | `lease_value` | `lease_value` | No | text | T2 |
| All | All | Broker Comp. | Purchase Option Type | `purchase_type` | `purchase_type` | `purchase_type` | No | select (%/$) | T2 |
| All | All | Broker Comp. | Purchase Option Amount | `purchase_value` | `purchase_value` | `purchase_value` | No | text | T2 |
| All | All | Broker Comp. | Protection Period | `protection_period` | `protection_period` | `protection_period` | No | number | T2 |
| All | All | Broker Comp. | Early Termination Fee | `early_termination_fee_option` | `early_termination_fee_option` | `early_termination_fee_option` | No | select | T2; ⚠ value case |
| All | All | Broker Comp. | Early Termination Fee Amount | `early_termination_fee_amount` | `early_termination_fee_amount` | `early_termination_fee_amount` | No | currency | T2 |
| All | All | Broker Comp. | Agency Agreement Timeframe | `agency_agreement_timeframe` | `agency_agreement_timeframe` | `agency_agreement_timeframe` | No | select | T2; T5 |
| All | All | Broker Comp. | Agency Agreement Custom | `agency_agreement_custom` | `agency_agreement_custom` | `agency_agreement_custom` | No | text | T2; T5 |
| All | All | Broker Comp. | Brokerage Relationship | `brokerage_relationship` | `brokerage_relationship` | `brokerage_relationship` | No | select | T2 |
| All | All | Broker Comp. | Additional Broker Details | `additional_details_broker` | `additional_details_broker` | `additional_details_broker` | No | textarea | T1 |
| Seller/Buyer/Tenant | All | Broker Comp. | Retainer Fee | `retainer_fee_option` | `retainer_fee_option` | `retainer_fee_option` | No | select | T2; ⚠ Tenant `'Yes'` vs others `'yes'` |
| Seller/Buyer/Tenant | All | Broker Comp. | Retainer Fee Amount | `retainer_fee_amount` | `retainer_fee_amount` | `retainer_fee_amount` | No | currency | T2 |
| Seller/Buyer/Tenant | All | Broker Comp. | Retainer Fee Application | `retainer_fee_application` | `retainer_fee_application` | `retainer_fee_application` | No | select | T2; ⚠ slug/sentence differs |
| Seller | All | Broker Comp. | Retained Deposits (%) | `retained_deposits` | `retained_deposits` | `retained_deposits` | No | number | T2 |
| Seller/Tenant | All | Broker Comp. | Interested in Lease/Purchase | `interested_purchase_fee_type` | `interested_purchase_fee_type` | `interested_purchase_fee_type` | No | select (Yes/No) | T2 |

### B.4 — Buyer-Specific Bid Fields

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Meta Key | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|------------|:--------:|-----------|-------|
| Buyer | All | Broker Comp. | Interested in Lease Agreement | `interested_lease_option` | `interested_lease_option` | `interested_lease_option` | No | select (Yes/No) | T2 |
| Buyer | All | Broker Comp. | Lease Fee Type | `lease_fee_type` | `lease_fee_type` | `lease_fee_type` | No | select | T2; flat=`'flat'` slug |
| Buyer | All | Broker Comp. | Lease Fee (Flat $) | `lease_fee_flat` | `lease_fee_flat` | `lease_fee_flat` | No | currency | T2 |
| Buyer | All | Broker Comp. | Lease Fee (% Gross Lease) | `lease_fee_percentage` | `lease_fee_percentage` | `lease_fee_percentage` | No | number | T2 |
| Buyer | residential | Broker Comp. | Lease Fee (% Monthly Rent) | `lease_fee_percentage_monthly_rent` | `lease_fee_percentage_monthly_rent` | `lease_fee_percentage_monthly_rent` | No | number | T2 |
| Buyer/Tenant | residential | Broker Comp. | Lease Fee (# of Months) | `lease_fee_percentage_monthly_number` | `lease_fee_percentage_monthly_number` | `lease_fee_percentage_monthly_number` | No | number | T2 |
| Buyer | residential | Broker Comp. | Lease Fee (Flat+% Gross Combo $) | `lease_fee_flat_combo` | `lease_fee_flat_combo` | `lease_fee_flat_combo` | No | currency | T2 |
| Buyer | residential | Broker Comp. | Lease Fee (Flat+% Gross Combo %) | `lease_fee_percentage_combo` | `lease_fee_percentage_combo` | `lease_fee_percentage_combo` | No | number | T2 |
| Buyer | commercial+ | Broker Comp. | Lease Fee (% Net Aggregate) | `lease_fee_percentage_net` | `lease_fee_percentage_net` | `lease_fee_percentage_net` | No | number | T2 |
| Buyer | commercial+ | Broker Comp. | Lease Fee (Flat+% Net Combo $) | `lease_fee_flat_combo_net` | `lease_fee_flat_combo_net` | `lease_fee_flat_combo_net` | No | currency | T2 |
| Buyer | commercial+ | Broker Comp. | Lease Fee (Flat+% Net Combo %) | `lease_fee_percentage_combo_net` | `lease_fee_percentage_combo_net` | `lease_fee_percentage_combo_net` | No | number | T2 |
| Buyer | All | Broker Comp. | Lease Fee (Other) | `lease_fee_other` | `lease_fee_other` | `lease_fee_other` | No | text | T2 |
| Buyer | All | Broker Comp. | Appraisal Gap Amount | `gap_payment_amount` | `gap_payment_amount` | `gap_payment_amount` | No | currency | T3 BID_ONLY |
| Buyer | All | Broker Comp. | Appraisal Gap Type | *(toggle)* | `gap_payment_type` | `gap_payment_type` | No | toggle ($) | T3 BID_ONLY |
| Buyer | All | Broker Comp. | Down Payment Amount | `down_payment_amount` | `down_payment_amount` | `down_payment_amount` | No | currency | T3 BID_ONLY |
| Buyer | All | Broker Comp. | Down Payment Type | *(toggle)* | `down_payment_type` | `down_payment_type` | No | toggle ($) | T3 BID_ONLY |
| Buyer | All | Broker Comp. | Seller Financing Amount | `seller_financing_amount` | `seller_financing_amount` | `seller_financing_amount` | No | currency | T3 BID_ONLY |
| Buyer | All | Broker Comp. | Seller Financing Type | *(toggle)* | `seller_financing_type` | `seller_financing_type` | No | toggle ($) | T3 BID_ONLY |
| Buyer | All | Broker Comp. | Purchase Fee Flat Type Toggle | *(UI toggle)* | `purchase_fee_flat_type` | — | No | toggle ($) | T3 BID_ONLY; display only |
| Buyer | All | Broker Comp. | Lease Fee Flat Type Toggle | *(UI toggle)* | `lease_fee_flat_type` | — | No | toggle ($) | T3 BID_ONLY; display only |
| Buyer | All | Services | Total Flat Fee | *(calculated)* | `total_flat_fee` | — | No | number | T3 BID_ONLY; calculated |
| Buyer | All | Services | Total Marketing Fee | *(calculated)* | `total_marketing_fee` | — | No | number | T3 BID_ONLY; calculated |

### B.5 — Seller-Specific Bid Fields

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Meta Key | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|------------|:--------:|-----------|-------|
| Seller | income/commercial/business | Broker Comp. | Nominal Consideration Fee | `nominal` | `nominal` | `nominal` | No | currency | T2 |
| Seller | All | Broker Comp. | Buyer's Broker Commission Structure Type | `commission_structure_type` | `commission_structure_type` | `commission_structure_type` | No | select | T2 |
| Seller | All | Broker Comp. | Buyer's Broker Fee (Flat $) | `commission_structure_type_fee_flat` | `commission_structure_type_fee_flat` | `commission_structure_type_fee_flat` | No | currency | T2 |
| Seller | All | Broker Comp. | Buyer's Broker Fee (Flat Combo $) | `commission_structure_type_fee_flat_combo` | `commission_structure_type_fee_flat_combo` | `commission_structure_type_fee_flat_combo` | No | currency | T2 |
| Seller | All | Broker Comp. | Buyer's Broker Fee (%) | `commission_structure_type_fee_percentage` | `commission_structure_type_fee_percentage` | `commission_structure_type_fee_percentage` | No | number | T2 |
| Seller | All | Broker Comp. | Buyer's Broker Fee (% Combo) | `commission_structure_type_fee_percentage_combo` | `commission_structure_type_fee_percentage_combo` | `commission_structure_type_fee_percentage_combo` | No | number | T2 |
| Seller | All | Broker Comp. | Buyer's Broker Fee (Other) | `commission_structure_type_fee_other` | `commission_structure_type_fee_other` | `commission_structure_type_fee_other` | No | text | T2 |
| Seller | All | Broker Comp. | Seller's Broker Leasing Fee Type | `seller_leasing_fee_type` | `seller_leasing_fee_type` | `seller_leasing_fee_type` | No | select | T2 |
| Seller | residential/income/vacant_land | Broker Comp. | Leasing Fee (% Each Rental Period) | `seller_leasing_gross_rental` | `seller_leasing_gross_rental` | `seller_leasing_gross_rental` | No | number | T2 |
| Seller | residential/income/vacant_land | Broker Comp. | Leasing Fee (% Gross Lease) | `seller_leasing_gross` | `seller_leasing_gross` | `seller_leasing_gross` | No | number | T2 |
| Seller | residential/income/vacant_land | Broker Comp. | Leasing Fee (% First Month's Rent) | `seller_leasing_gross_month_rent` | `seller_leasing_gross_month_rent` | `seller_leasing_gross_month_rent` | No | number | T2 |
| Seller | residential/income/vacant_land | Broker Comp. | Leasing Fee (% Net Aggregate Rent) | `seller_leasing_gross_other` | `seller_leasing_gross_other` | `seller_leasing_gross_other` | No | number | T2 |
| Seller | commercial/business | Broker Comp. | Leasing Fee (% Gross Rent) | `seller_leasing_gross_percentage` | `seller_leasing_gross_percentage` | `seller_leasing_gross_percentage` | No | number | T2 |
| Seller | All | Broker Comp. | Leasing Fee (Flat $) | `seller_leasing_gross_purchase_fee_flat_amount` | `seller_leasing_gross_purchase_fee_flat_amount` | `seller_leasing_gross_purchase_fee_flat_amount` | No | currency | T2 |
| Seller | All | Broker Comp. | Leasing Fee (Other) | `seller_leasing_gross_purchase_fee_other` | `seller_leasing_gross_purchase_fee_other` | `seller_leasing_gross_purchase_fee_other` | No | text | T2 |
| Seller | All | Broker Comp. | Leasing Fee (% Each Rental — alt) | `seller_leasing_each_rental` | `seller_leasing_each_rental` | `seller_leasing_each_rental` | No | number | T2 |
| Seller | All | Broker Comp. | Leasing Fee (# of Months) | `seller_leasing_gross_no_of_months` | `seller_leasing_gross_no_of_months` | `seller_leasing_gross_no_of_months` | No | number | T2 |
| Seller | All | Broker Comp. | Leasing Fee (Flat+% Gross Combo $) | `seller_leasing_gross_flat_combo` | `seller_leasing_gross_flat_combo` | `seller_leasing_gross_flat_combo` | No | currency | T2 |
| Seller | All | Broker Comp. | Leasing Fee (Flat+% Gross Combo %) | `seller_leasing_gross_percentage_combo` | `seller_leasing_gross_percentage_combo` | `seller_leasing_gross_percentage_combo` | No | number | T2 |
| Seller | All | Broker Comp. | Leasing Fee (Flat+% Net Combo $) | `seller_leasing_gross_flat_net_combo` | `seller_leasing_gross_flat_net_combo` | `seller_leasing_gross_flat_net_combo` | No | currency | T2 |
| Seller | All | Broker Comp. | Leasing Fee (Flat+% Net Combo %) | `seller_leasing_gross_percentage_net_combo` | `seller_leasing_gross_percentage_net_combo` | `seller_leasing_gross_percentage_net_combo` | No | number | T2 |
| Seller | All | Broker Comp. | Sales Tax (Gross Rent) | `seller_leasing_gross_sales_tax_option_gross` | `seller_leasing_gross_sales_tax_option_gross` | `seller_leasing_gross_sales_tax_option_gross` | No | select | T2 |
| Seller | All | Broker Comp. | Sales Tax (First Month) | `seller_leasing_gross_sales_tax_first_month` | `seller_leasing_gross_sales_tax_first_month` | `seller_leasing_gross_sales_tax_first_month` | No | select | T2 |
| Seller | commercial/business | Broker Comp. | Sales Tax (Flat Fee) | `seller_leasing_gross_sales_tax_flat_free_gross` | `seller_leasing_gross_sales_tax_flat_free_gross` | `seller_leasing_gross_sales_tax_flat_free_gross` | No | select | T2 |
| Seller | All | Services | Show Enhancements | *(toggle)* | `showEnhancements` | — | No | bool | T3 BID_ONLY |
| Seller | All | Services | Show Custom Enhancement | *(toggle)* | `showCustomEnhancement` | — | No | bool | T3 BID_ONLY |
| Seller | All | Services | Photo Enhancements | `photo_enhancements[]` | `photo_enhancements` | `photo_enhancements` | No | array | T3 BID_ONLY; per-listing |
| Seller | All | Services | Custom Enhancement | `custom_enhancement` | `custom_enhancement` | `custom_enhancement` | No | text | T3 BID_ONLY; per-listing |

### B.6 — Landlord-Specific Bid Fields

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Meta Key | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|------------|:--------:|-----------|-------|
| Landlord | residential | Broker Comp. | Leasing Fee (% Rental Period) | `purchase_fee_rental_period` | `purchase_fee_rental_period` | `purchase_fee_rental_period` | No | number | T2 |
| Landlord | commercial | Broker Comp. | Leasing Fee (% Net Aggregate) | `purchase_fee_net_aggregate` | `purchase_fee_net_aggregate` | `purchase_fee_net_aggregate` | No | number | T2 |
| Landlord | commercial | Broker Comp. | Leasing Fee (% Gross Rent) | `purchase_fee_gross_rent` | `purchase_fee_gross_rent` | `purchase_fee_gross_rent` | No | number | T2 |
| Landlord | commercial | Broker Comp. | Leasing Fee (% Monthly) | `purchase_fee_monthly_percentage` | `purchase_fee_monthly_percentage` | `purchase_fee_monthly_percentage` | No | number | T2 |
| Landlord | commercial | Broker Comp. | Leasing Fee (# of Months) | `purchase_fee_months` | `purchase_fee_months` | `purchase_fee_months` | No | number | T2 |
| Landlord | commercial | Broker Comp. | Sales Tax (Monthly) | `sales_tax_option_monthly` | `sales_tax_option_monthly` | `sales_tax_option_monthly` | No | select | T2 |
| Landlord | commercial | Broker Comp. | Leasing Fee (Flat — Commercial) | `purchase_fee_flat_commercial` | `purchase_fee_flat_commercial` | `purchase_fee_flat_commercial` | No | currency | T2 |
| Landlord | commercial | Broker Comp. | Sales Tax (Flat) | `sales_tax_option_flat` | `sales_tax_option_flat` | `sales_tax_option_flat` | No | select | T2 |
| Landlord | commercial | Broker Comp. | Leasing Fee (Other — Commercial) | `purchase_fee_other_commercial` | `purchase_fee_other_commercial` | `purchase_fee_other_commercial` | No | text | T2 |
| Landlord | commercial | Broker Comp. | Leasing Fee (% Purchase Price) | `purchase_fee_purchase_price` | `purchase_fee_purchase_price` | `purchase_fee_purchase_price` | No | number | T2 |
| Landlord | residential | Broker Comp. | Tenant Broker Commission Structure | `tenant_broker_commission_structure` | `tenant_broker_commission_structure` | `tenant_broker_commission_structure` | No | select | T2 |
| Landlord | residential | Broker Comp. | Tenant Broker Fee Structure | `tenant_broker_fee_structure` | `tenant_broker_fee_structure` | `tenant_broker_fee_structure` | No | select | T2 |
| Landlord | residential | Broker Comp. | Tenant Broker Fee (%) | `tenant_broker_percentage` | `tenant_broker_percentage` | `tenant_broker_percentage` | No | number | T2 |
| Landlord | residential | Broker Comp. | Tenant Broker Fee (% Gross Lease) | `tenant_broker_gross_lease` | `tenant_broker_gross_lease` | `tenant_broker_gross_lease` | No | number | T2 |
| Landlord | residential | Broker Comp. | Tenant Broker Fee (% First Month) | `tenant_broker_first_month_rent` | `tenant_broker_first_month_rent` | `tenant_broker_first_month_rent` | No | number | T2 |
| Landlord | residential | Broker Comp. | Tenant Broker Fee (Flat $) | `tenant_broker_flat_fee` | `tenant_broker_flat_fee` | `tenant_broker_flat_fee` | No | currency | T2 |
| Landlord | residential | Broker Comp. | Tenant Broker Fee (Other) | `tenant_broker_other` | `tenant_broker_other` | `tenant_broker_other` | No | text | T2 |
| Landlord | residential/commercial | Broker Comp. | Broker Fee Timing | `broker_fee_timing` | `broker_fee_timing` | `broker_fee_timing` | No | select | T2 |
| Landlord | residential | Broker Comp. | Days from Rent Collected | `broker_fee_days_from_rent` | `broker_fee_days_from_rent` | `broker_fee_days_from_rent` | No | number | T2 |
| Landlord | residential | Broker Comp. | Days After Executed Lease | `broker_fee_days_after_lease` | `broker_fee_days_after_lease` | `broker_fee_days_after_lease` | No | number | T2 |
| Landlord | residential | Broker Comp. | Days After Tenant Rent Payment | `broker_fee_days_after_rent` | `broker_fee_days_after_rent` | `broker_fee_days_after_rent` | No | number | T2 |
| Landlord | residential/commercial | Broker Comp. | Broker Fee Timing (Other) | `broker_fee_timing_other` | `broker_fee_timing_other` | `broker_fee_timing_other` | No | text | T2 |
| Landlord | residential/commercial | Broker Comp. | Split Payment Due | `split_payment_due` | `split_payment_due` | `split_payment_due` | No | select | T2; ⚠ `'uponoccupancy'` |
| Landlord | residential/commercial | Broker Comp. | Split Payment Due (Other) | `split_payment_due_other` | `split_payment_due_other` | `split_payment_due_other` | No | text | T2 |
| Landlord | residential/commercial | Broker Comp. | Days After Due Event | `broker_fee_days_after_due_event` | `broker_fee_days_after_due_event` | `broker_fee_days_after_due_event` | No | number | T2 |
| Landlord | residential/commercial | Broker Comp. | Renewal Fee Type | `renewal_fee_type` | `renewal_fee_type` | `renewal_fee_type` | No | select | T2 |
| Landlord | residential/commercial | Broker Comp. | Renewal Fee (%) | `renewal_fee_percentage` | `renewal_fee_percentage` | `renewal_fee_percentage` | No | number | T2 |
| Landlord | residential/commercial | Broker Comp. | Renewal Fee (% Gross Lease) | `renewal_fee_lease_value` | `renewal_fee_lease_value` | `renewal_fee_lease_value` | No | number | T2 |
| Landlord | residential/commercial | Broker Comp. | Renewal Fee (% First Month) | `renewal_fee_first_month` | `renewal_fee_first_month` | `renewal_fee_first_month` | No | number | T2 |
| Landlord | residential/commercial | Broker Comp. | Renewal Fee (Flat $) | `renewal_fee_flat_free` | **`renewal_fee_flat_free`** | `renewal_fee_flat_free` | No | currency | T2; 🔴 KEY_MISMATCH_BUG — mapper emits `renewal_fee_flat_fee` |
| Landlord | residential/commercial | Broker Comp. | Renewal Fee (Custom) | `renewal_fee_custom` | `renewal_fee_custom` | `renewal_fee_custom` | No | text | T2 |
| Landlord | residential/commercial | Broker Comp. | Renewal Fee Sales Tax (Lease Value) | `renewal_fee_sales_tax_lease_value` | `renewal_fee_sales_tax_lease_value` | `renewal_fee_sales_tax_lease_value` | No | select | T2 |
| Landlord | residential/commercial | Broker Comp. | Renewal Fee (# of Months) | `renewal_fee_no_of_months` | `renewal_fee_no_of_months` | `renewal_fee_no_of_months` | No | number | T2 |
| Landlord | residential/commercial | Broker Comp. | Renewal Fee Sales Tax (First Month) | `renewal_fee_sales_tax_first_month` | `renewal_fee_sales_tax_first_month` | `renewal_fee_sales_tax_first_month` | No | select | T2 |
| Landlord | residential/commercial | Broker Comp. | Renewal Fee Sales Tax (Flat) | `renewal_fee_sales_tax_flat_fee` | `renewal_fee_sales_tax_flat_fee` | `renewal_fee_sales_tax_flat_fee` | No | select | T2 |
| Landlord | commercial | Broker Comp. | Expansion Commission (%) | `expansion_commission_percentage` | `expansion_commission_percentage` | `expansion_commission_percentage` | No | number | T2 |
| Landlord | residential/commercial | Broker Comp. | Interested in Property Management | `interested_in_property_management` | `interested_in_property_management` | `interested_in_property_management` | No | select (Yes/No) | T2 |
| Landlord | residential/commercial | Broker Comp. | Property Mgmt Fee Type | `interested_in_property_management_fee` | `interested_in_property_management_fee` | `interested_in_property_management_fee` | No | select | T2 |
| Landlord | residential/commercial | Broker Comp. | Property Mgmt Fee (% Gross Lease) | `interested_in_property_management_fee_gross_lease` | `interested_in_property_management_fee_gross_lease` | (same) | No | number | T2 |
| Landlord | residential/commercial | Broker Comp. | Property Mgmt Fee (% Rental Period) | `interested_in_property_management_fee_rental_periord` | `interested_in_property_management_fee_rental_periord` | (same) | No | number | T2; ⚠ typo symmetric |
| Landlord | residential/commercial | Broker Comp. | Property Mgmt Fee (Flat $) | `interested_in_property_management_fee_flate_free` | `interested_in_property_management_fee_flate_free` | (same) | No | currency | T2; ⚠ typo symmetric |
| Landlord | residential/commercial | Broker Comp. | Property Mgmt Fee (Other) | `interested_in_property_management_fee_other` | `interested_in_property_management_fee_other` | (same) | No | text | T2 |
| Landlord | residential/commercial | Broker Comp. | Interested in Selling | `interested_in_selling` | `interested_in_selling` | `interested_in_selling` | No | select (Yes/No) | T2 |
| Landlord | residential/commercial | Broker Comp. | Selling Fee Type | `interested_in_selling_type` | `interested_in_selling_type` | `interested_in_selling_type` | No | select | T2 |
| Landlord | residential/commercial | Broker Comp. | Selling Fee: Basis | `landlord_broker_purchase_price` | `landlord_broker_purchase_price` | `landlord_broker_purchase_price` | No | select | T2 |
| Landlord | residential/commercial | Broker Comp. | Selling Fee (% of Price) | `landlord_broker_percentage_price` | `landlord_broker_percentage_price` | `landlord_broker_percentage_price` | No | number | T2 |
| Landlord | residential/commercial | Broker Comp. | Selling Fee ($ Amount) | `landlord_broker_dollar_price` | `landlord_broker_dollar_price` | `landlord_broker_dollar_price` | No | currency | T2 |
| Landlord | residential/commercial | Broker Comp. | Selling Fee (Flat $) | `landlord_broker_flate_fee` | `landlord_broker_flate_fee` | `landlord_broker_flate_fee` | No | currency | T2; ⚠ typo symmetric |
| Landlord | residential/commercial | Broker Comp. | Selling Fee (Other) | `landlord_broker_other` | `landlord_broker_other` | `landlord_broker_other` | No | text | T2 |
| Landlord | residential/commercial | Broker Comp. | Lease Fee (Flat $) | `lease_fee_flat` | `lease_fee_flat` | `lease_fee_flat` | No | currency | T2; tenant flat-fee sub-field |
| Landlord | residential/commercial | Broker Comp. | Lease Fee Flat Type Toggle | *(UI toggle)* | `lease_fee_flat_type` | — | No | toggle | T3 BID_ONLY |
| Landlord | residential/commercial | Broker Comp. | Purchase Fee Flat Type Toggle | *(UI toggle)* | `purchase_fee_flat_type` | — | No | toggle | T3 BID_ONLY |
| Landlord | All | Services | Show Enhancements | *(toggle)* | `showEnhancements` | — | No | bool | T3 BID_ONLY |
| Landlord | All | Services | Show Custom Enhancement | *(toggle)* | `showCustomEnhancement` | — | No | bool | T3 BID_ONLY |
| Landlord | All | Services | Photo Enhancements | `photo_enhancements[]` | `photo_enhancements` | `photo_enhancements` | No | array | T3 BID_ONLY |
| Landlord | All | Services | Custom Enhancement | `custom_enhancement` | `custom_enhancement` | `custom_enhancement` | No | text | T3 BID_ONLY |

### B.7 — Tenant-Specific Bid Fields

| Role | Property Types | Section | Label | Input Name | Livewire Property | DB Meta Key | Required? | Field Type | Notes |
|------|---------------|---------|-------|-----------|------------------|------------|:--------:|-----------|-------|
| Tenant | All | Broker Comp. | Lease Fee Type | `lease_fee_type` | `lease_fee_type` | `lease_fee_type` | No | select | T2; flat=`'Flat Fee'` full text |
| Tenant | All | Broker Comp. | Lease Fee (Flat $) | `lease_fee_flat` | `lease_fee_flat` | `lease_fee_flat` | No | currency | T2 |
| Tenant | All | Broker Comp. | Lease Fee Flat Type Toggle | *(UI toggle)* | `lease_fee_flat_type` | — | No | toggle | T3 BID_ONLY |
| Tenant | All | Broker Comp. | Lease Fee (% Gross Lease) | `lease_fee_percentage` | `lease_fee_percentage` | `lease_fee_percentage` | No | number | T2 |
| Tenant | residential | Broker Comp. | Lease Fee (% Monthly Rent) | `lease_fee_percentage_monthly_rent` | `lease_fee_percentage_monthly_rent` | `lease_fee_percentage_monthly_rent` | No | number | T2 |
| Tenant | residential | Broker Comp. | Lease Fee (% Monthly # of Months) | `lease_fee_percentage_monthly_number` | `lease_fee_percentage_monthly_number` | `lease_fee_percentage_monthly_number` | No | number | T2 |
| Tenant | residential | Broker Comp. | Lease Fee (Flat+% Gross Combo $) | `lease_fee_flat_combo` | `lease_fee_flat_combo` | `lease_fee_flat_combo` | No | currency | T2 |
| Tenant | residential | Broker Comp. | Lease Fee (Flat+% Gross Combo %) | `lease_fee_percentage_combo` | `lease_fee_percentage_combo` | `lease_fee_percentage_combo` | No | number | T2 |
| Tenant | commercial | Broker Comp. | Lease Fee (% Net Aggregate) | `lease_fee_percentage_net` | `lease_fee_percentage_net` | `lease_fee_percentage_net` | No | number | T2 |
| Tenant | commercial | Broker Comp. | Lease Fee (Flat+% Net Combo $) | `lease_fee_flat_combo_net` | `lease_fee_flat_combo_net` | `lease_fee_flat_combo_net` | No | currency | T2 |
| Tenant | commercial | Broker Comp. | Lease Fee (Flat+% Net Combo %) | `lease_fee_percentage_combo_net` | `lease_fee_percentage_combo_net` | `lease_fee_percentage_combo_net` | No | number | T2 |
| Tenant | All | Broker Comp. | Lease Fee (Other) | `lease_fee_other` | `lease_fee_other` | `lease_fee_other` | No | text | T2 |
| Tenant | All | Broker Comp. | Broker Fee Timing | `broker_fee_timing` | `broker_fee_timing` | `broker_fee_timing` | No | select | T2 |
| Tenant | residential | Broker Comp. | Days from Rent Collected | `broker_fee_days_from_rent` | `broker_fee_days_from_rent` | `broker_fee_days_from_rent` | No | number | T2 |
| Tenant | residential | Broker Comp. | Days After Executed Lease | `broker_fee_days_after_lease` | `broker_fee_days_after_lease` | `broker_fee_days_after_lease` | No | number | T2 |
| Tenant | residential | Broker Comp. | Days After Tenant Rent Payment | `broker_fee_days_after_rent` | `broker_fee_days_after_rent` | `broker_fee_days_after_rent` | No | number | T2 |
| Tenant | All | Broker Comp. | Broker Fee Timing (Other) | `broker_fee_timing_other` | `broker_fee_timing_other` | `broker_fee_timing_other` | No | text | T2 |
| Tenant | All | Broker Comp. | Purchase Fee Flat Type Toggle | *(UI toggle)* | `purchase_fee_flat_type` | — | No | toggle | T3 BID_ONLY |
| Tenant | All | Services | Total Flat Fee | *(calculated)* | `total_flat_fee` | — | No | number | T3 BID_ONLY |
| Tenant | All | Services | Total Marketing Fee | *(calculated)* | `total_marketing_fee` | — | No | number | T3 BID_ONLY |
| Tenant | All | System | Is Bidding Period Listing | *(runtime)* | `isBiddingPeriodListing` | — | No | bool | T3 BID_ONLY |
| Tenant | All | Presentation | Existing Business Card | *(edit-mode)* | `existingBusinessCard` | — | No | file path | T3 BID_ONLY |
| Tenant | All | Presentation | Delete Existing Business Card | *(toggle)* | `deleteExistingBusinessCard` | — | No | bool | T3 BID_ONLY |
| Tenant | All | System | Deleted Files | *(runtime)* | `deletedFiles` | — | No | array | T3 BID_ONLY |

---

## 7. Section C — Master Crosswalk

**Columns:** Field Key | Role Scope | Status | Safety Tier | Preset Side | Bid Property | Recommended Behavior

### C.1 — Profile-Only Fields (20 keys)

| Field Key | Status | Tier | Recommended Behavior |
|-----------|--------|------|---------------------|
| `years_experience` | `PRESET_ONLY` | T1 | Profile/widget display only; never add to bid |
| `transactions_last_12_months` | `PRESET_ONLY` | T1 | Profile/widget only |
| `avg_response_time` | `PRESET_ONLY` | T1 | Profile/widget only |
| `is_full_time` | `PRESET_ONLY` | T1 | Profile/widget only |
| `primary_areas_served` | `PRESET_ONLY` | T1 | Profile/widget only |
| `cities_served` | `PRESET_ONLY` | T1 | Profile/widget only; consider adding to bid as display field (P2) |
| `counties_served` | `PRESET_ONLY` | T1 | Profile/widget only; consider adding to bid (P2) |
| `neighborhoods_served` | `PRESET_ONLY` | T1 | Profile/widget only |
| `areas_notes` | `PRESET_ONLY` | T1 | Profile/widget only |
| `review_1` | `PRESET_ONLY` | T1 | Profile/widget only |
| `review_2` | `PRESET_ONLY` | T1 | Profile/widget only |
| `review_3` | `PRESET_ONLY` | T1 | Profile/widget only |
| `awards_recognition` | `PRESET_ONLY` | T1 | Profile/widget only |
| `intro_video_url` | `PRESET_ONLY` | T1 | Profile/widget only |
| `video_caption` | `PRESET_ONLY` | T1 | Profile/widget only |
| `availability_status` | `PRESET_ONLY` | T1 | Profile/widget only |
| `evenings_available` | `PRESET_ONLY` | T1 | Profile/widget only |
| `weekends_available` | `PRESET_ONLY` | T1 | Profile/widget only |
| `communication_style` | `PRESET_ONLY` | T1 | Profile/widget only |
| `preferred_contact_method` | `PRESET_ONLY` | T1 | Profile/widget only |

### C.2 — Agent Overview & Credentials (13 keys, all roles)

| Field Key | Status | Tier | Roles | Preset Key | Bid Property | Recommended Behavior |
|-----------|--------|------|-------|-----------|-------------|---------------------|
| `bio` | `EXACT` | T1 | All | `bio` | `bio` | AUTO — copy as-is |
| `why_hire_you` | `EXACT` | T1 | All | `why_hire_you` | `why_hire_you` | AUTO |
| `what_sets_you_apart` | `EXACT` | T1 | All | `what_sets_you_apart` | `what_sets_you_apart` | AUTO |
| `marketing_plan` | `EXACT` | T1 | All | `marketing_plan` | `marketing_plan` | AUTO; editable per bid |
| `year_licensed` | `EXACT` | T1 | All | `year_licensed` | `year_licensed` | AUTO |
| `additional_details` | `EXACT` | T1 | All | `additional_details` | `additional_details` | AUTO |
| `first_name` | `EXACT` | T1 | All | `first_name` | `first_name` | AUTO with !empty() guard |
| `last_name` | `EXACT` | T1 | All | `last_name` | `last_name` | AUTO with !empty() guard |
| `phone` | `EXACT` | T1 | All | `phone` | `phone` | AUTO with !empty() guard |
| `email` | `EXACT` | T1 | All | `email` | `email` | AUTO with !empty() guard |
| `brokerage` | `EXACT` | T1 | All | `brokerage` | `brokerage` | AUTO with !empty() guard |
| `license_no` | `EXACT` | T1 | All | `license_no` | `license_no` | AUTO with !empty() guard |
| `nar_id` | `EXACT` | T1 | All | `nar_id` | `nar_id` | AUTO |

### C.3 — Media, Links & Services (10 keys, all roles)

| Field Key | Status | Tier | Roles | Preset Key | Bid Property | Recommended Behavior |
|-----------|--------|------|-------|-----------|-------------|---------------------|
| `presentation_link` | `EXACT` | T1 | All | `presentation_link` | `presentation_link` | AUTO |
| `presentation_upload_path` | `EXACT` | T1 | All | `presentation_upload_path` | `presentation_upload_path` | AUTO |
| `business_card_link` | `EXACT` | T1 | All | `business_card_link` | `business_card_link` | AUTO |
| `business_card_stored_path` | `EXACT` | T1 | All | `business_card_stored_path` | `business_card_stored_path` | AUTO |
| `business_card_upload_path` | `EXACT` | T1 | All | `business_card_upload_path` | `business_card_upload_path` | AUTO |
| `promoMaterials` | `EXACT` | T1 | All | `promoMaterials` | `promoMaterials` | AUTO |
| `reviews_links` | `EXACT` | T1 | All | `reviews_links` | `reviews_links` | AUTO |
| `website_link` | `EXACT` | T1 | All | `website_link` | `website_link` | AUTO |
| `social_media` | `EXACT` | T1 | All | `social_media` | `social_media` | AUTO |
| `services` | `EXACT` | T1 | All | `services` | `services` | AUTO; filterServicesToCurrentCatalog() applied |
| `other_services` | `EXACT` | T1 | All | `other_services` | `other_services` | AUTO |
| `other_services_enabled` | `BID_ONLY` | T3 | All | — | `other_services_enabled` | Do not auto-populate; component default |

### C.4 — Shared Agency Agreement (10 keys, all roles)

| Field Key | Status | Tier | Notes | Preset Key | Bid Property | Recommended Behavior |
|-----------|--------|------|-------|-----------|-------------|---------------------|
| `protection_period` | `EXACT` | T2 | All roles | `protection_period` | `protection_period` | PREFILL; agent reviews |
| `early_termination_fee_option` | `ROLE_INCONSISTENT` | T2 | Tenant stores `'Yes'/'No'`, others `'yes'/'no'` | `early_termination_fee_option` | `early_termination_fee_option` | PREFILL; normalize values in P1.2 |
| `early_termination_fee_amount` | `EXACT` | T2 | All roles | `early_termination_fee_amount` | `early_termination_fee_amount` | PREFILL |
| `agency_agreement_timeframe` | `EXACT` | T2/T5 | All roles; must-not-save-back | `agency_agreement_timeframe` | `agency_agreement_timeframe` | PREFILL; T5 — never write bid value back to preset |
| `agency_agreement_custom` | `EXACT` | T2/T5 | All roles | `agency_agreement_custom` | `agency_agreement_custom` | PREFILL; T5 |
| `interested_lease_option_agreement` | `EXACT` | T2 | All roles | `interested_lease_option_agreement` | `interested_lease_option_agreement` | PREFILL |
| `lease_type` | `EXACT` | T2 | All roles | `lease_type` | `lease_type` | PREFILL |
| `lease_value` | `EXACT` | T2 | All roles | `lease_value` | `lease_value` | PREFILL |
| `purchase_type` | `EXACT` | T2 | All roles | `purchase_type` | `purchase_type` | PREFILL |
| `purchase_value` | `EXACT` | T2 | All roles | `purchase_value` | `purchase_value` | PREFILL |

### C.5 — Shared Compensation (10 keys, all roles)

| Field Key | Status | Tier | Notes | Preset Key | Bid Property | Recommended Behavior |
|-----------|--------|------|-------|-----------|-------------|---------------------|
| `commission_structure` | `MUST_REVIEW` | T2 | All roles | `commission_structure` | `commission_structure` | PREFILL; flag for review |
| `purchase_fee_type` | `ROLE_INCONSISTENT` | T2 | Seller=slug, Buyer/Tenant=full text | `purchase_fee_type` | `purchase_fee_type` | PREFILL; normalize in P4.2 |
| `purchase_fee_flat` | `EXACT` | T2 | All roles | `purchase_fee_flat` | `purchase_fee_flat` | PREFILL |
| `purchase_fee_percentage` | `MUST_REVIEW` | T2 | All roles | `purchase_fee_percentage` | `purchase_fee_percentage` | PREFILL; review rate suitability |
| `purchase_fee_percentage_combo` | `EXACT` | T2 | All roles | `purchase_fee_percentage_combo` | `purchase_fee_percentage_combo` | PREFILL |
| `purchase_fee_flat_combo` | `EXACT` | T2 | All roles | `purchase_fee_flat_combo` | `purchase_fee_flat_combo` | PREFILL |
| `purchase_fee_other` | `EXACT` | T2 | All roles | `purchase_fee_other` | `purchase_fee_other` | PREFILL |
| `retainer_fee_option` | `ROLE_INCONSISTENT` | T2 | Tenant=`'Yes'`, Seller/Buyer=`'yes'`; Landlord discards | `retainer_fee_option` | `retainer_fee_option` | PREFILL; normalize in P1.2 |
| `retainer_fee_amount` | `EXACT` | T2 | Seller/Buyer/Tenant; Landlord discards | `retainer_fee_amount` | `retainer_fee_amount` | PREFILL |
| `retainer_fee_application` | `ROLE_INCONSISTENT` | T2 | Tenant=slug, others=full sentence; Landlord discards | `retainer_fee_application` | `retainer_fee_application` | PREFILL; normalize in P1.2 |

### C.6 — Shared Tail (3 keys, all roles)

| Field Key | Status | Tier | Notes | Preset Key | Bid Property | Recommended Behavior |
|-----------|--------|------|-------|-----------|-------------|---------------------|
| `brokerage_relationship` | `EXACT` | T2 | All roles | `brokerage_relationship` | `brokerage_relationship` | PREFILL |
| `additional_details_broker` | `EXACT` | T1 | All roles | `additional_details_broker` | `additional_details_broker` | AUTO |
| `referral_fee_percent` | `EXACT` | T2/T5 | All roles; deal-specific; T5 | `referral_fee_percent` | `referral_fee_percent` | PREFILL; T5 — never write back |

### C.7 — Partial/Inconsistent Fields

| Field Key | Status | Tier | Roles | Mapper Key | Bid Property | Issue | Recommended Behavior |
|-----------|--------|------|-------|-----------|-------------|-------|---------------------|
| `retained_deposits` | `PARTIAL_ROLE` | T2 | Seller only | `retained_deposits` | `retained_deposits` | Mapper emits for all; Buyer/Landlord/Tenant discard | PREFILL Seller; fix mapper comment |
| `interested_purchase_fee_type` | `EXACT` | T2 | Seller (lease gate), Tenant (purchase gate) | `interested_purchase_fee_type` | `interested_purchase_fee_type` | Shared key, different semantic role | PREFILL |
| `interested_lease_option` | `PARTIAL_ROLE` | T2 | Buyer only | `interested_lease_option` | `interested_lease_option` | Tenant does not declare property | PREFILL Buyer |
| `lease_fee_percentage_monthly_number` | `EXACT` | T2 | Buyer/Tenant residential | `lease_fee_percentage_monthly_number` | `lease_fee_percentage_monthly_number` | PREFILL; residential only |

### C.8 — Buyer/Tenant Lease Fee Sub-fields (12 keys)

| Field Key | Status | Tier | Notes | Preset Key | Bid Property | Recommended Behavior |
|-----------|--------|------|-------|-----------|-------------|---------------------|
| `lease_fee_type` | `ROLE_INCONSISTENT` | T2 | Buyer flat=`'flat'`; Tenant flat=`'Flat Fee'` | `lease_fee_type` | `lease_fee_type` | PREFILL; normalize in P1.3 |
| `lease_fee_flat` | `EXACT` | T2 | Buyer/Tenant (and Landlord tenant sub) | `lease_fee_flat` | `lease_fee_flat` | PREFILL |
| `lease_fee_percentage` | `EXACT` | T2 | Buyer/Tenant | `lease_fee_percentage` | `lease_fee_percentage` | PREFILL |
| `lease_fee_percentage_monthly_rent` | `EXACT` | T2 | Buyer/Tenant residential | `lease_fee_percentage_monthly_rent` | `lease_fee_percentage_monthly_rent` | PREFILL |
| `lease_fee_flat_combo` | `EXACT` | T2 | Buyer/Tenant residential | `lease_fee_flat_combo` | `lease_fee_flat_combo` | PREFILL |
| `lease_fee_percentage_combo` | `EXACT` | T2 | Buyer/Tenant residential | `lease_fee_percentage_combo` | `lease_fee_percentage_combo` | PREFILL |
| `lease_fee_percentage_net` | `EXACT` | T2 | Buyer/Tenant commercial+ | `lease_fee_percentage_net` | `lease_fee_percentage_net` | PREFILL |
| `lease_fee_flat_combo_net` | `EXACT` | T2 | Buyer/Tenant commercial+ | `lease_fee_flat_combo_net` | `lease_fee_flat_combo_net` | PREFILL |
| `lease_fee_percentage_combo_net` | `EXACT` | T2 | Buyer/Tenant commercial+ | `lease_fee_percentage_combo_net` | `lease_fee_percentage_combo_net` | PREFILL |
| `lease_fee_other` | `EXACT` | T2 | Buyer/Tenant | `lease_fee_other` | `lease_fee_other` | PREFILL |

### C.9 — Seller-Specific Fields (26 keys)

| Field Key | Status | Tier | Preset Key | Bid Property | Recommended Behavior |
|-----------|--------|------|-----------|-------------|---------------------|
| `nominal` | `EXACT` | T2 | `nominal` | `nominal` | PREFILL; income/commercial/business |
| `commission_structure_type` | `EXACT` | T2 | `commission_structure_type` | `commission_structure_type` | PREFILL |
| `commission_structure_type_fee_flat` | `EXACT` | T2 | same | same | PREFILL |
| `commission_structure_type_fee_flat_combo` | `EXACT` | T2 | same | same | PREFILL |
| `commission_structure_type_fee_percentage` | `MUST_REVIEW` | T2 | same | same | PREFILL; review % |
| `commission_structure_type_fee_percentage_combo` | `MUST_REVIEW` | T2 | same | same | PREFILL; review % |
| `commission_structure_type_fee_other` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_fee_type` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_rental` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_month_rent` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_other` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_percentage` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_purchase_fee_flat_amount` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_purchase_fee_other` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_each_rental` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_no_of_months` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_flat_combo` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_percentage_combo` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_flat_net_combo` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_percentage_net_combo` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_sales_tax_option_gross` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_sales_tax_first_month` | `EXACT` | T2 | same | same | PREFILL |
| `seller_leasing_gross_sales_tax_flat_free_gross` | `EXACT` | T2 | same | same | PREFILL; commercial/business |
| `retained_deposits` | `PARTIAL_ROLE` | T2 | `retained_deposits` | `retained_deposits` | PREFILL Seller only |

### C.10 — Landlord-Specific Fields (43 keys)

| Field Key | Status | Tier | Notes | Preset Key | Bid Property | Recommended Behavior |
|-----------|--------|------|-------|-----------|-------------|---------------------|
| `purchase_fee_rental_period` | `EXACT` | T2 | Landlord residential | same | same | PREFILL |
| `purchase_fee_net_aggregate` | `EXACT` | T2 | Landlord commercial | same | same | PREFILL |
| `purchase_fee_gross_rent` | `EXACT` | T2 | Landlord commercial | same | same | PREFILL |
| `purchase_fee_monthly_percentage` | `EXACT` | T2 | Landlord commercial | same | same | PREFILL |
| `purchase_fee_months` | `EXACT` | T2 | Landlord commercial | same | same | PREFILL |
| `sales_tax_option_monthly` | `EXACT` | T2 | Landlord commercial | same | same | PREFILL |
| `purchase_fee_flat_commercial` | `EXACT` | T2 | Landlord commercial | same | same | PREFILL |
| `sales_tax_option_flat` | `EXACT` | T2 | Landlord commercial | same | same | PREFILL |
| `purchase_fee_other_commercial` | `EXACT` | T2 | Landlord commercial | same | same | PREFILL |
| `purchase_fee_purchase_price` | `EXACT` | T2 | Landlord commercial | same | same | PREFILL |
| `tenant_broker_commission_structure` | `EXACT` | T2 | Landlord residential | same | same | PREFILL |
| `tenant_broker_fee_structure` | `EXACT` | T2 | Landlord residential | same | same | PREFILL |
| `tenant_broker_percentage` | `EXACT` | T2 | Landlord residential | same | same | PREFILL |
| `tenant_broker_gross_lease` | `EXACT` | T2 | Landlord residential | same | same | PREFILL |
| `tenant_broker_first_month_rent` | `EXACT` | T2 | Landlord residential | same | same | PREFILL |
| `tenant_broker_flat_fee` | `EXACT` | T2 | Landlord residential | same | same | PREFILL |
| `tenant_broker_other` | `EXACT` | T2 | Landlord residential | same | same | PREFILL |
| `broker_fee_timing` | `EXACT` | T2 | Landlord + Tenant | same | same | PREFILL |
| `broker_fee_days_from_rent` | `EXACT` | T2 | Landlord + Tenant | same | same | PREFILL |
| `broker_fee_days_after_lease` | `EXACT` | T2 | Landlord + Tenant | same | same | PREFILL |
| `broker_fee_days_after_rent` | `EXACT` | T2 | Landlord + Tenant | same | same | PREFILL |
| `broker_fee_timing_other` | `EXACT` | T2 | Landlord + Tenant | same | same | PREFILL |
| `split_payment_due` | `TYPO_SYMMETRIC` | T2 | Stored value `'uponoccupancy'` | same | same | PREFILL; do not fix typo in isolation |
| `split_payment_due_other` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `broker_fee_days_after_due_event` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `renewal_fee_type` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `renewal_fee_percentage` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `renewal_fee_lease_value` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `renewal_fee_first_month` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `renewal_fee_flat_fee` (mapper) | `KEY_MISMATCH_BUG` | T2 | 🔴 Component property = `renewal_fee_flat_free` | `renewal_fee_flat_fee` | `renewal_fee_flat_free` | **Fix P1.1 before enabling PREFILL** |
| `renewal_fee_custom` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `renewal_fee_sales_tax_lease_value` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `renewal_fee_no_of_months` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `renewal_fee_sales_tax_first_month` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `renewal_fee_sales_tax_flat_fee` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `expansion_commission_percentage` | `EXACT` | T2 | Landlord commercial | same | same | PREFILL |
| `interested_in_property_management` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `interested_in_property_management_fee` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `interested_in_property_management_fee_gross_lease` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `interested_in_property_management_fee_rental_periord` | `TYPO_SYMMETRIC` | T2 | ⚠ typo `periord` | same | same | PREFILL |
| `interested_in_property_management_fee_flate_free` | `TYPO_SYMMETRIC` | T2 | ⚠ typo `flate_free` | same | same | PREFILL |
| `interested_in_property_management_fee_other` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `interested_in_selling` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `interested_in_selling_type` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `landlord_broker_purchase_price` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `landlord_broker_percentage_price` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `landlord_broker_dollar_price` | `EXACT` | T2 | Landlord | same | same | PREFILL |
| `landlord_broker_flate_fee` | `TYPO_SYMMETRIC` | T2 | ⚠ typo | same | same | PREFILL |
| `landlord_broker_other` | `EXACT` | T2 | Landlord | same | same | PREFILL |

### C.11 — Bid-Only Fields (19 keys)

| Field Key | Status | Tier | Component(s) | Recommended Behavior |
|-----------|--------|------|-------------|---------------------|
| `gap_payment_amount` | `BID_ONLY` | T3 | Buyer | Do not auto-populate |
| `gap_payment_type` | `BID_ONLY` | T3 | Buyer | Do not auto-populate |
| `down_payment_amount` | `BID_ONLY` | T3 | Buyer | Do not auto-populate |
| `down_payment_type` | `BID_ONLY` | T3 | Buyer | Do not auto-populate |
| `seller_financing_amount` | `BID_ONLY` | T3 | Buyer | Do not auto-populate |
| `seller_financing_type` | `BID_ONLY` | T3 | Buyer | Do not auto-populate |
| `purchase_fee_flat_type` (UI toggle) | `BID_ONLY` | T3 | Buyer/Landlord/Tenant | Do not auto-populate |
| `lease_fee_flat_type` (UI toggle) | `BID_ONLY` | T3 | Buyer/Landlord/Tenant | Do not auto-populate |
| `total_flat_fee` | `BID_ONLY` | T3 | Buyer/Tenant | Calculated; do not populate |
| `total_marketing_fee` | `BID_ONLY` | T3 | Buyer/Tenant | Calculated; do not populate |
| `photo_enhancements` | `BID_ONLY` | T3 | Seller/Landlord | Per-listing; do not populate |
| `custom_enhancement` | `BID_ONLY` | T3 | Seller/Landlord | Per-listing; do not populate |
| `showEnhancements` | `BID_ONLY` | T3 | Seller/Landlord | UI toggle; do not populate |
| `showCustomEnhancement` | `BID_ONLY` | T3 | Seller/Landlord | UI toggle; do not populate |
| `other_services_enabled` | `BID_ONLY` | T3 | All | Component runtime flag |
| `isBiddingPeriodListing` | `BID_ONLY` | T3 | Tenant | Runtime flag |
| `existingBusinessCard` | `BID_ONLY` | T3 | Tenant | Edit-mode state |
| `deleteExistingBusinessCard` | `BID_ONLY` | T3 | Tenant | Edit-mode flag |
| `deletedFiles` | `BID_ONLY` | T3 | Tenant | Runtime list |

---

## 8. Section D — Auto-Populate Table

**Columns:** Bid Field | Preset Source | Auto-Pop Rec. | Editable per Bid | Rationale

Recommendation: `AUTO` = copy silently; `PREFILL` = copy but flag for review; `NO` = do not auto-populate.

| Bid Field | Preset Source Key | Rec. | Editable | Rationale |
|-----------|-----------------|:----:|:--------:|-----------|
| `bio` | `bio` | AUTO | Yes | Static agent identity |
| `why_hire_you` | `why_hire_you` | AUTO | Yes | Static pitch |
| `what_sets_you_apart` | `what_sets_you_apart` | AUTO | Yes | Static pitch |
| `marketing_plan` | `marketing_plan` | AUTO | Yes | May be customised per property |
| `year_licensed` | `year_licensed` | AUTO | Yes | Static credential |
| `additional_details` | `additional_details` | AUTO | Yes | |
| `first_name`…`nar_id` (7 fields) | same keys | AUTO | Yes | Credentials; !empty() guard |
| `presentation_link` | `presentation_link` | AUTO | Yes | Standard material |
| `presentation_upload_path` | same | AUTO | Yes | |
| `business_card_link` | same | AUTO | Yes | |
| `business_card_stored_path` | same | AUTO | Yes | |
| `business_card_upload_path` | same | AUTO | Yes | |
| `promoMaterials` | `promoMaterials` | AUTO | Yes | |
| `reviews_links` | `reviews_links` | AUTO | Yes | |
| `website_link` | `website_link` | AUTO | Yes | |
| `social_media` | `social_media` | AUTO | Yes | |
| `services` | `services` | AUTO | Yes | Catalog-filtered on assignment |
| `other_services` | `other_services` | AUTO | Yes | |
| `additional_details_broker` | `additional_details_broker` | AUTO | Yes | |
| `protection_period` | `protection_period` | PREFILL | Yes | Client may negotiate |
| `early_termination_fee_option` | `early_termination_fee_option` | PREFILL | Yes | Normalize case first (P1.2) |
| `early_termination_fee_amount` | `early_termination_fee_amount` | PREFILL | Yes | Amount may vary |
| `agency_agreement_timeframe` | `agency_agreement_timeframe` | PREFILL | Yes | T5 — must not write back |
| `agency_agreement_custom` | `agency_agreement_custom` | PREFILL | Yes | T5 |
| `interested_lease_option_agreement` | `interested_lease_option_agreement` | PREFILL | Yes | Preference |
| `lease_type` | `lease_type` | PREFILL | Yes | |
| `lease_value` | `lease_value` | PREFILL | Yes | |
| `purchase_type` | `purchase_type` | PREFILL | Yes | |
| `purchase_value` | `purchase_value` | PREFILL | Yes | |
| `commission_structure` | `commission_structure` | PREFILL | Yes | Core contract term |
| `purchase_fee_type` | `purchase_fee_type` | PREFILL | Yes | Normalize slug/text (P4.2) |
| `purchase_fee_flat` | `purchase_fee_flat` | PREFILL | Yes | Review amount |
| `purchase_fee_percentage` | `purchase_fee_percentage` | PREFILL | Yes | Review rate suitability |
| `purchase_fee_percentage_combo` | same | PREFILL | Yes | |
| `purchase_fee_flat_combo` | same | PREFILL | Yes | |
| `purchase_fee_other` | same | PREFILL | Yes | |
| `retainer_fee_option` | `retainer_fee_option` | PREFILL | Yes | Normalize case (P1.2) |
| `retainer_fee_amount` | `retainer_fee_amount` | PREFILL | Yes | |
| `retainer_fee_application` | `retainer_fee_application` | PREFILL | Yes | Normalize slug/sentence (P1.2) |
| `brokerage_relationship` | `brokerage_relationship` | PREFILL | Yes | Legal disclosure |
| `referral_fee_percent` | `referral_fee_percent` | PREFILL | Yes | T5; deal-specific |
| `interested_lease_option` (Buyer) | `interested_lease_option` | PREFILL | Yes | |
| `lease_fee_type` (Buyer/Tenant) | `lease_fee_type` | PREFILL | Yes | Normalize flat value (P1.3) |
| `lease_fee_flat` | `lease_fee_flat` | PREFILL | Yes | |
| `lease_fee_percentage` | `lease_fee_percentage` | PREFILL | Yes | |
| `lease_fee_percentage_monthly_rent` | same | PREFILL | Yes | |
| `lease_fee_percentage_monthly_number` | same | PREFILL | Yes | Buyer/Tenant residential |
| `lease_fee_flat_combo` | same | PREFILL | Yes | |
| `lease_fee_percentage_combo` | same | PREFILL | Yes | |
| `lease_fee_percentage_net` | same | PREFILL | Yes | |
| `lease_fee_flat_combo_net` | same | PREFILL | Yes | |
| `lease_fee_percentage_combo_net` | same | PREFILL | Yes | |
| `lease_fee_other` | `lease_fee_other` | PREFILL | Yes | |
| `nominal` | `nominal` | PREFILL | Yes | Seller only |
| All `commission_structure_type_*` (6) | same | PREFILL | Yes | Seller only |
| `interested_purchase_fee_type` | same | PREFILL | Yes | Gate field |
| All `seller_leasing_*` (18) | same | PREFILL | Yes | Seller only |
| `retained_deposits` | `retained_deposits` | PREFILL | Yes | Seller only |
| All Landlord compensation fields (43) except `renewal_fee_flat_fee` | same | PREFILL | Yes | After P1.1 fix |
| `renewal_fee_flat_fee` → `renewal_fee_flat_free` | — | **NO** until P1.1 | — | Key mismatch bug; silently discarded |
| `gap_payment_amount/type` | — | NO | — | BID_ONLY; per-listing |
| `down_payment_amount/type` | — | NO | — | BID_ONLY |
| `seller_financing_amount/type` | — | NO | — | BID_ONLY |
| `photo_enhancements` | — | NO | — | BID_ONLY; per-listing |
| `custom_enhancement` | — | NO | — | BID_ONLY |
| `total_flat_fee` | — | NO | — | Calculated |
| `total_marketing_fee` | — | NO | — | Calculated |
| `other_services_enabled` | — | NO | — | Runtime flag |
| All UI toggle fields (`*_flat_type`) | — | NO | — | Display-only |
| `isBiddingPeriodListing` | — | NO | — | Listing runtime flag |

---

## 9. Section E — Bid-Specific Field Table

Fields that are per-bid only and must **never** be sourced from or written back to a preset.

| Field | Component(s) | Category | Must-Not-Overwrite Reason |
|-------|-------------|----------|--------------------------|
| `gap_payment_amount` | Buyer | Property-specific | Appraisal gap differs per property value |
| `gap_payment_type` | Buyer | Property-specific | Display toggle for above |
| `down_payment_amount` | Buyer | Client-specific | Buyer's personal financial capacity for this deal |
| `down_payment_type` | Buyer | Client-specific | |
| `seller_financing_amount` | Buyer | Negotiable | Negotiated with specific seller's willingness |
| `seller_financing_type` | Buyer | Negotiable | |
| `photo_enhancements` | Seller/Landlord | Property-specific | Chosen based on THIS property's photo conditions |
| `custom_enhancement` | Seller/Landlord | Property-specific | Written for THIS property |
| `total_flat_fee` | Buyer/Tenant | Calculated | Runtime sum; not a preference |
| `total_marketing_fee` | Buyer/Tenant | Calculated | Runtime sum |
| `other_services_enabled` | All | UI state | Component toggle; no persistent meaning |
| `purchase_fee_flat_type` (toggle) | Buyer/Landlord/Tenant | UI state | Display-only |
| `lease_fee_flat_type` (toggle) | Buyer/Landlord/Tenant | UI state | Display-only |
| `showEnhancements` | Seller/Landlord | UI state | Runtime toggle |
| `showCustomEnhancement` | Seller/Landlord | UI state | Runtime toggle |
| `isBiddingPeriodListing` | Tenant | Listing runtime | Set from listing; not an agent preference |
| `existingBusinessCard` | Tenant | Edit-mode state | Populated from existing bid record in edit mode |
| `deleteExistingBusinessCard` | Tenant | Edit-mode flag | Edit-mode action flag |
| `deletedFiles` | Tenant | Runtime list | Tracks files to delete on save |

---

## 10. Section F — Do Not Auto-Populate Rules

### Category 1 — Property-Specific

Fields whose correct value depends on the specific property being listed. A preset value for these fields reflects the agent's standard practice, not any particular property.

**Examples:**
- `photo_enhancements` — selected based on this property's photo quality and type
- `custom_enhancement` — written text about this property
- `marketing_plan` free-text that references a specific property address or condition
- `additional_details` / `additional_details_broker` text that names the property
- `services` sub-selections that only make sense for a specific property type (e.g., staging for a vacant residential, but not for commercial)

**Rule:** Never auto-populate any field whose value would reference or depend on the specific property address, condition, or unique characteristics.

---

### Category 2 — Client-Specific

Fields whose value depends on the particular buyer, seller, tenant, or landlord in this transaction.

**Examples:**
- `down_payment_amount` — the buyer's personal down payment capacity
- `seller_financing_amount` — agreed with this specific seller
- `gap_payment_amount` — set based on buyer's willingness for this deal
- `retainer_fee_amount` when reduced from the standard due to a specific client relationship
- `agency_agreement_timeframe` when the client has requested a non-standard term

**Rule:** Never auto-populate values that were negotiated with or set for a previous specific client. These must always start blank or trigger agent review.

---

### Category 3 — Time-Sensitive

Fields whose validity may expire or change over time. A preset saved months ago may have stale values.

**Examples:**
- `agency_agreement_timeframe` — brokerage standards may have changed
- `protection_period` — regulatory norms may have shifted
- `early_termination_fee_amount` — dollar amounts become outdated
- `purchase_fee_percentage` — market commission rates shift over time
- Any `nominal` fee set during a specific market period

**Rule:** All numeric amount and duration fields must be PREFILL (not AUTO) so the agent confirms the value on every bid.

---

### Category 4 — Promotional

Fields containing time-limited offers or campaign-specific terms that the agent ran during a particular period.

**Examples:**
- `nominal` fee waiver offered during a promotional campaign
- `lease_fee_other` with text describing a special offer
- `retainer_fee_application` configured as "free retainer this month"
- `additional_details_broker` containing promotional language

**Rule:** If the preset was saved during an active promotion, those terms must not silently apply to future bids. Mark promotional fields as PREFILL with a visible "promotion may have ended" notice.

---

### Category 5 — Negotiable

Fields where the agent deliberately tailors their offer to compete for a specific listing. The preset is a starting point, not the final offer.

**Examples:**
- `purchase_fee_percentage` — an agent may lower their commission to win a high-value listing
- `commission_structure_type_fee_percentage` — co-broke percentage adjusted competitively
- `gap_payment_amount` — set per-listing to signal buyer commitment
- `referral_fee_percent` — varies per referring party agreement
- `lease_fee_percentage` — adjusted based on lease length and market conditions

**Rule:** All commission-rate and percentage fields must be PREFILL (never AUTO) and must be clearly labelled as editable in the UI.

---

### Category 6 — Legal/Contractual

Fields that become part of a legally binding agreement and require the agent's conscious review before submission.

**Examples:**
- `brokerage_relationship` — agency relationship type with disclosure obligations
- `agency_agreement_timeframe` — sets the contractual duration
- `early_termination_fee_option` + `early_termination_fee_amount` — contractual penalty clause
- `retained_deposits` — determines broker's share of forfeited deposit
- `referral_fee_percent` — referral agreement term
- `retainer_fee_application` — determines how the retainer is applied to the final fee
- `protection_period` — defines the post-termination protection window

**Rule:** No legal/contractual field may be marked AUTO. All must be PREFILL. After implementation, consider displaying a "Please verify these terms before submitting" banner on the broker compensation tab when any preset-loaded values are present.

---

## 11. Section G — Two-Way Compatibility Crosswalk

The match score helpers (`*BidMatchScoreHelper`) implement bid-vs-bid scoring by comparing a **consumer's baseline bid** against an **agent's submitted bid**. Every scored field can originate from the agent's preset.

**Direction codes:**
- `A→C` = agent offers this; consumer evaluates against their preference
- `C→A` = consumer requests this; agent's preset must cover it  
- `Mutual` = both sides specify a value that must align

### G.1 — Seller Role

| Score Field | Direction | Preset Source | Scoreable | Notes |
|-------------|-----------|--------------|:---------:|-------|
| `services` | A→C | `services` | ✓ | |
| `purchase_fee_type` + sub-fields | A→C | `purchase_fee_type` | ✓ | |
| `nominal` | A→C | `nominal` | ✓ | income/commercial/business |
| `commission_structure` | A→C | `commission_structure` | ✓ | |
| `commission_structure_type` | A→C | `commission_structure_type` | ✓ | |
| `seller_leasing_fee_type` + sub-fields | A→C | `seller_leasing_fee_type` | ✓ | |
| `early_termination_fee_option` | Mutual | `early_termination_fee_option` | ✓ | |
| `early_termination_fee_amount` | Mutual | `early_termination_fee_amount` | ✓ | |
| `retainer_fee_option` | A→C | `retainer_fee_option` | ✓ | |
| `retainer_fee_amount` | A→C | `retainer_fee_amount` | ✓ | |
| `retainer_fee_application` | A→C | `retainer_fee_application` | ✓ | |
| `protection_period` | Mutual | `protection_period` | ✓ | |
| `agency_agreement_timeframe` | Mutual | `agency_agreement_timeframe` | ✓ | |
| `brokerage_relationship` | A→C | `brokerage_relationship` | ✓ | |

### G.2 — Buyer Role

| Score Field | Direction | Preset Source | Scoreable | Notes |
|-------------|-----------|--------------|:---------:|-------|
| `services` | A→C | `services` | ✓ | |
| `commission_structure` | A→C | `commission_structure` | ✓ | |
| `purchase_fee_type` + sub-fields | A→C | `purchase_fee_type` | ✓ | |
| `lease_fee_type` + sub-fields | A→C | `lease_fee_type` | ✓ | |
| `protection_period` | Mutual | `protection_period` | ✓ | |
| `early_termination_fee_option` | Mutual | `early_termination_fee_option` | ✓ | |
| `early_termination_fee_amount` | Mutual | `early_termination_fee_amount` | ✓ | |
| `retainer_fee_option` | A→C | `retainer_fee_option` | ✓ | |
| `retainer_fee_amount` | A→C | `retainer_fee_amount` | ✓ | |
| `retainer_fee_application` | A→C | `retainer_fee_application` | ✓ | |
| `agency_agreement_timeframe` | Mutual | `agency_agreement_timeframe` | ✓ | |
| `brokerage_relationship` | A→C | `brokerage_relationship` | ✓ | |
| `gap_payment_amount` | C→A | **No preset** | ✗ | BID_ONLY |
| `down_payment_amount` | C→A | **No preset** | ✗ | BID_ONLY |
| `seller_financing_amount` | C→A | **No preset** | ✗ | BID_ONLY |

### G.3 — Landlord Role

| Score Field | Direction | Preset Source | Scoreable | Notes |
|-------------|-----------|--------------|:---------:|-------|
| `services` | A→C | `services` | ✓ | |
| `purchase_fee_type` + all sub-fields | A→C | `purchase_fee_type` | ✓ | |
| `broker_fee_timing` + sub-fields | A→C | `broker_fee_timing` | ✓ | |
| `renewal_fee_type` + sub-fields | A→C | `renewal_fee_type` | ✓ | Flat sub-field blocked by KEY_MISMATCH_BUG |
| `protection_period` | Mutual | `protection_period` | ✓ | |
| `early_termination_fee_option` | Mutual | `early_termination_fee_option` | ✓ | |
| `early_termination_fee_amount` | Mutual | `early_termination_fee_amount` | ✓ | |
| `agency_agreement_timeframe` | Mutual | `agency_agreement_timeframe` | ✓ | |
| `brokerage_relationship` | A→C | `brokerage_relationship` | ✓ | |
| `interested_in_property_management` | A→C | `interested_in_property_management` | ✓ | In `LOGICAL_FIELD_GROUPS`; scored |
| `interested_in_selling` | A→C | `interested_in_selling` | ✓ | In `LOGICAL_FIELD_GROUPS`; `interested_in_selling_type` conditional on `'Yes'` |

### G.4 — Tenant Role

| Score Field | Direction | Preset Source | Scoreable | Notes |
|-------------|-----------|--------------|:---------:|-------|
| `services` | A→C | `services` | ✓ | |
| `commission_structure` | A→C | `commission_structure` | ✓ | |
| `lease_fee_type` + sub-fields | A→C | `lease_fee_type` | ✓ | |
| `purchase_fee_type` + sub-fields (if purchasing) | A→C | `purchase_fee_type` | ✓ | |
| `protection_period` | Mutual | `protection_period` | ✓ | |
| `early_termination_fee_option` | Mutual | `early_termination_fee_option` | ✓ | |
| `early_termination_fee_amount` | Mutual | `early_termination_fee_amount` | ✓ | |
| `retainer_fee_option` | A→C | `retainer_fee_option` | ✓ | |
| `retainer_fee_amount` | A→C | `retainer_fee_amount` | ✓ | |
| `retainer_fee_application` | A→C | `retainer_fee_application` | ✓ | |
| `agency_agreement_timeframe` | Mutual | `agency_agreement_timeframe` | ✓ | |
| `brokerage_relationship` | A→C | `brokerage_relationship` | ✓ | |
| `broker_fee_timing` + sub-fields | A→C | `broker_fee_timing` | ✓ | |

### G.5 — Fields Missing from Both Surfaces (MISSING_BOTH)

| Missing Concept | Direction | Why Valuable |
|----------------|-----------|-------------|
| Agent geographic coverage on bid | A→C | Consumer verifies agent covers their area |
| Agent specialty certifications (ABR, SRES, etc.) | A→C | Consumer may require certified agent |
| Agent's preferred listing term | Mutual | Reduces friction on agency agreement negotiation |
| Agent capacity (max concurrent clients) | A→C | Consumer gauges agent bandwidth |
| Consumer max commission tolerance | C→A | Enables pre-filter before full scoring |

---

## 12. Section H — Quick Match vs Full Match Readiness

### H.1 — Mode Definitions

| Mode | Description |
|------|-------------|
| **Quick Match** | Small high-signal field set; fast initial sort |
| **Full Match** | All scored fields; detailed side-by-side |

### H.2 — Readiness Table (per field)

| Field / Group | Role | Quick Match | Full Match | Preset Present | Bid Present | Mapper Wired | Issues |
|---------------|------|:-----------:|:----------:|:--------------:|:-----------:|:------------:|--------|
| `services` catalog | All | ✓ | ✓ | ✓ | ✓ | ✓ | None |
| `commission_structure` | All | ✓ | ✓ | ✓ | ✓ | ✓ | None |
| `purchase_fee_type` | All | ✓ | ✓ | ✓ | ✓ | ✓ | ⚠ Slug vs text by role |
| `purchase_fee_percentage` | All | ✓ | ✓ | ✓ | ✓ | ✓ | None |
| `purchase_fee_flat` | All | — | ✓ | ✓ | ✓ | ✓ | None |
| `lease_fee_type` | Buyer/Tenant | ✓ | ✓ | ✓ | ✓ | ✓ | ⚠ flat value differs |
| `lease_fee_percentage` | Buyer/Tenant | — | ✓ | ✓ | ✓ | ✓ | None |
| `protection_period` | All | ✓ | ✓ | ✓ | ✓ | ✓ | None |
| `agency_agreement_timeframe` | All | ✓ | ✓ | ✓ | ✓ | ✓ | None |
| `brokerage_relationship` | All | ✓ | ✓ | ✓ | ✓ | ✓ | None |
| `early_termination_fee_option` | All | — | ✓ | ✓ | ✓ | ✓ | ⚠ case differs by role |
| `retainer_fee_option` | Sel/Buy/Ten | — | ✓ | ✓ | ✓ | ✓ | ⚠ case differs by role |
| `renewal_fee_type` | Landlord | — | ✓ | ✓ | ✓ | ✓ | ⚠ Flat sub-field key mismatch bug |
| `renewal_fee_flat_fee` / `renewal_fee_flat_free` | Landlord | — | ✗ | ✓ | ✓ | ✗ bug | 🔴 KEY_MISMATCH_BUG — P1.1 required |
| `broker_fee_timing` + sub-fields | Landlord/Tenant | — | ✓ | ✓ | ✓ | ✓ | None |
| `nominal` | Seller | — | ✓ | ✓ | ✓ | ✓ | None |
| `commission_structure_type` | Seller | — | ✓ | ✓ | ✓ | ✓ | None |
| `seller_leasing_fee_type` | Seller | — | ✓ | ✓ | ✓ | ✓ | None |
| `tenant_broker_commission_structure` | Landlord | — | ✓ | ✓ | ✓ | ✓ | None |
| `expansion_commission_percentage` | Landlord | — | ✓ | ✓ | ✓ | ✓ | None |
| `interested_in_property_management` | Landlord | — | ✓ | ✓ | ✓ | ✓ | None; fully wired in LOGICAL_FIELD_GROUPS |
| `interested_in_selling` + `interested_in_selling_type` | Landlord | — | ✓ | ✓ | ✓ | ✓ | None; conditional on `interested_in_selling = 'Yes'` |
| `gap_payment_amount` | Buyer | — | ✗ | ✗ | ✓ | ✗ | BID_ONLY; no preset |
| `down_payment_amount` | Buyer | — | ✗ | ✗ | ✓ | ✗ | BID_ONLY |
| `seller_financing_amount` | Buyer | — | ✗ | ✗ | ✓ | ✗ | BID_ONLY |
| Agent geographic coverage | All | ✗ | ✗ | Partial | ✗ | ✗ | MISSING_BOTH for bid |
| Agent certifications | All | ✗ | ✗ | ✗ | ✗ | ✗ | MISSING_BOTH |

**Quick Match summary:** 9 of 11 quick-match fields are ready. 2 are partial (⚠ value format issues).  
**Full Match summary:** 18 of 27 field groups ready; 1 blocked by KEY_MISMATCH_BUG; 3 BID_ONLY by design; 2 missing from both surfaces; 4 with value-format issues to address.

---

## 13. Section I — Prioritized Implementation Plan

### P1 — Auto-populate existing bid fields from existing preset fields

The foundation already exists (`findAndMap()` + component `mount()`). P1 activates it cleanly.

| Task | Files Affected | Risk | Notes |
|------|---------------|------|-------|
| **P1.1** Fix `renewal_fee_flat_fee` vs `renewal_fee_flat_free` key mismatch | `AgentBidMapperService.php:171`, `LandlordAgentAuctionBid.php` (property, saveMeta, EAV load), DB migration for existing meta rows | **Medium** | Must ship before auto-populate; blocks Landlord renewal flat fee |
| **P1.2** Normalize `early_termination_fee_option`, `retainer_fee_option`, `retainer_fee_application` stored-value case | `edit.blade.php` Tenant section, `TenantAgentAuctionBid.php`, data migration for existing Tenant meta rows | **Low** | Choose canonical `'yes'/'no'` |
| **P1.3** Normalize `lease_fee_type` flat value for Tenant | `edit.blade.php` Tenant section, `TenantAgentAuctionBid.php`, data migration | **Low** | Choose `'Flat Fee'` (full text) as canonical or `'flat'` slug |
| **P1.4** Add PREFILL banner UI to all four bid components | Broker comp tab blade templates (all four roles) | **Low** | Show "Loaded from your preset — please review" when preset applied |
| **P1.5** Gate T5 fields from save-back | `AgentPresetController.php` `save()` or wherever bid data might flow to preset | **Low** | Prevent `agency_agreement_timeframe`, `referral_fee_percent` from being overwritten |
| **P1.6** *(Already done)* `interested_in_property_management` + `interested_in_selling` are already in Landlord score helper `LOGICAL_FIELD_GROUPS` — no action required | — | **None** | Verified in `LandlordBidMatchScoreHelper.php` lines 143–192 |

**Estimated effort:** 2–3 days

---

### P2 — Add missing preset fields to bid forms

Some preset concepts would improve bid completeness if surfaced in the bid.

| Task | New Field(s) | Files Affected | Risk | Notes |
|------|-------------|---------------|------|-------|
| **P2.1** Surface `cities_served` / `counties_served` as read-only display on bid | Display field in bid overview tab blade | **Low** | Marketing stat; pre-filled from preset, not editable in bid |
| **P2.2** Surface `years_experience` as display-only on bid overview | Bid overview tab blade | **Very Low** | |
| **P2.3** Add `other_services_enabled` to mapper and preset | `AgentBidMapperService.php`, preset controller | **Very Low** | Currently only a bid runtime flag |
| **P2.4** Add Landlord `retainer_fee_option/amount/application` as bid properties | `LandlordAgentAuctionBid.php`, Landlord broker comp blade | **Medium** | Mapper already emits; component needs to declare and save |

**Estimated effort:** 2–3 days

---

### P3 — Bid-specific fields confirmation

| Task | Files Affected | Risk | Notes |
|------|-------------|------|-------|
| **P3.1** Confirm `gap_payment_amount/type`, `down_payment_amount/type`, `seller_financing_amount/type` are permanently BID_ONLY | Code comment in `BuyerAgentAuctionBid.php` | **None** | Document as T3; no preset counterpart needed |
| **P3.2** Confirm `photo_enhancements`, `custom_enhancement` are permanently BID_ONLY | Code comment in `SellerAgentAuctionBid.php`, `LandlordAgentAuctionBid.php` | **None** | Per-listing |
| **P3.3** *(Optional)* Add "Agent capacity" field to preset + bid + score helper | `edit.blade.php`, bid components, mapper, score helpers | **Medium** | Enables richer Quick Match |

**Estimated effort:** 0.5 day (documentation) + optional 1–2 days for P3.3

---

### P4 — Two-way compatibility scoring improvements

| Task | Files Affected | Risk | Notes |
|------|-------------|------|-------|
| **P4.1** Fix all value-format inconsistencies (P1.2 + P1.3) | (same as P1.2/P1.3) | **Medium** | Required before preset-based scoring is meaningful |
| **P4.2** Normalize `purchase_fee_type` Seller slug to canonical | `AgentBidMapperService.php`, `SellerAgentAuctionBid.php` or score helper normalization layer | **Low** | Seller stores `'percentage'`; others use full text |
| **P4.3** Add consumer-side preference fields for Buyer `gap_payment` / `down_payment` / `seller_financing` to listing form | `BuyerAgentAuction` model + listing blade + migration | **High** | New schema required |
| **P4.4** *(Already done)* `interested_in_property_management` and `interested_in_selling` scoring is already implemented in `LandlordBidMatchScoreHelper.php` — no action required | — | **None** | Verified in `LOGICAL_FIELD_GROUPS` and `SCORED_FIELDS` constant |

**Estimated effort:** 3–5 days

---

### P5 — Quick Match / Full Match mode split

| Task | Files Affected | Risk | Notes |
|------|-------------|------|-------|
| **P5.1** Define Quick Match field whitelist per role in score helpers | All four `*BidMatchScoreHelper.php` | **Low** | Subset of existing scored fields |
| **P5.2** Add `$mode` parameter to score methods | All four `*BidMatchScoreHelper.php` | **Medium** | API change; test coverage required |
| **P5.3** Display Quick Match score in bid list views | Bid list blade templates | **Low** | UI only |
| **P5.4** Surface Quick Match / Full Match score in bid detail and PDF | `bid-detail-layout` component, PDF template | **Medium** | Requires score persistence |
| **P5.5** Add geographic/certification fields to Quick Match after P2 | Score helpers | **Low** | Depends on P2 completion |

**Estimated effort:** 4–7 days (depends on P2 and test coverage depth)

---

## 14. Appendix A — Bugs & Cross-Role Value Inconsistencies

### A.1 🔴 Critical — `renewal_fee_flat_fee` vs `renewal_fee_flat_free`

| Layer | Key Used |
|-------|----------|
| `AgentBidMapperService.php` line 171 (emitted key) | `renewal_fee_flat_fee` |
| `LandlordAgentAuctionBid.php` public property | `renewal_fee_flat_free` |
| `LandlordAgentAuctionBid.php` `saveMeta()` | `renewal_fee_flat_free` |
| `LandlordAgentAuctionBid.php` EAV load | `renewal_fee_flat_free` |
| `LandlordBidMatchScoreHelper.php` field group | `renewal_fee_flat_free` |

**Effect:** When a Landlord preset is applied to a bid form, the renewal flat fee value is silently dropped. The bid form field stays empty even when the agent has saved a value.

**Fix:** Choose one spelling (recommend `renewal_fee_flat_fee`). Update `LandlordAgentAuctionBid.php` property declaration, `saveMeta`, and EAV load. Update `LandlordBidMatchScoreHelper.php` field group. Add a DB migration to rename existing `renewal_fee_flat_free` meta keys.

---

### A.2 ⚠ `early_termination_fee_option` Case Differs by Role

| Role | Yes stored as | No stored as |
|------|--------------|-------------|
| Seller | `'yes'` | `'no'` |
| Buyer | `'yes'` | `'no'` |
| Landlord | `'yes'` | `'no'` |
| Tenant | `'Yes'` | `'No'` |

Tenant score helper uses `baseline_active_when => ['early_termination_fee_option' => 'Yes']`. Correct within Tenant scope, but cross-role fallback would fail.

---

### A.3 ⚠ `retainer_fee_option` Case + `retainer_fee_application` Format Differ

| Role | `retainer_fee_option` Yes | `retainer_fee_application` credit value |
|------|--------------------------|----------------------------------------|
| Seller | `'yes'` | `'Applied toward final compensation'` |
| Buyer | `'yes'` | `'Applied toward final compensation'` |
| Tenant | `'Yes'` | `'applied'` |
| Landlord | No property declared | — |

---

### A.4 ⚠ `lease_fee_type` Flat Value Differs: Buyer `'flat'` vs Tenant `'Flat Fee'`

Documented in `config/agent_preset_compensation.php`. Buyer uses slug `'flat'`; Tenant uses full text `'Flat Fee'`.

---

### A.5 ⚠ `purchase_fee_type` Format Differs: Seller Slug vs Buyer/Tenant Full Text

Seller stores `'percentage'`, `'flat'`, `'combo'`, `'other'`. Buyer and Tenant store `'Percentage of the Total Purchase Price'` etc. Documented in `edit.blade.php` comment.

---

### A.6 ⚠ `retained_deposits` Mapper Comment Says "All Roles" but Only Seller Declares Property

Mapper line 204 is in the "All roles" comment section. Only `SellerAgentAuctionBid` declares `$retained_deposits`. Buyer/Landlord/Tenant silently discard.

---

### A.7 ⚠ Established Symmetric Typos — Do Not Fix in Isolation

| Typo Key | Correct Spelling | All Layers Affected |
|----------|-----------------|-------------------|
| `interested_in_property_management_fee_rental_periord` | `…_period` | mapper, component, DB |
| `interested_in_property_management_fee_flate_free` | `…_flat_fee` | mapper, component, DB |
| `landlord_broker_flate_fee` | `…_flat_fee` | mapper, component, DB |
| `split_payment_due` stored value `'uponoccupancy'` | `'upon_occupancy'` | DB stored values only |

These are consistent across all layers. Fix as one coordinated migration if ever corrected.

---

## 15. Appendix B — Coverage Checklist

### B.1 — Preset Fields: Total Count by Category

| Category | Field Count |
|----------|------------|
| Profile-only (not mapped) | 20 |
| Agent overview & credentials | 13 |
| Media / links / services | 11 |
| Shared agency agreement | 10 |
| Shared compensation | 10 |
| Shared tail | 3 |
| Buyer/Tenant lease fee sub-fields | 12 |
| Seller-specific compensation | 26 |
| Landlord-specific compensation | 43 |
| Tenant-specific compensation | 7 |
| **Total preset fields inventoried** | **155** |

### B.2 — Bid Fields: Total Count by Category

| Category | Field Count |
|----------|------------|
| Required all-roles fields | 11 |
| Optional standard all-roles fields | 13 |
| Shared broker compensation all-roles | 24 |
| Buyer-specific | 22 |
| Seller-specific | 30 |
| Landlord-specific | 57 |
| Tenant-specific | 16 |
| **Total bid fields inventoried** | **173** |

### B.3 — Master Crosswalk: Field Coverage Summary

| Status | Count |
|--------|-------|
| `EXACT` | 96 |
| `MAPPED` | 0 |
| `VALUE_MISMATCH` | 0 |
| `KEY_MISMATCH_BUG` | 1 |
| `PRESET_ONLY` | 20 |
| `BID_ONLY` | 19 |
| `PARTIAL_ROLE` | 2 |
| `ROLE_INCONSISTENT` | 5 |
| `TYPO_SYMMETRIC` | 4 |
| `MISSING_BOTH` | 5 |
| `MUST_REVIEW` | 4 |
| **Total unique field keys audited** | **156** |

### B.4 — Coverage by Role/Property-Type Combination

| # | Role | Property Type | Preset Fields | Bid Fields | Mapper Wired | Known Issues |
|---|------|--------------|:--------------:|:----------:|:------------:|-------------|
| 1 | Seller | residential | 102 | 117 | ✓ (all except 20 profile-only) | purchase_fee_type slug |
| 2 | Seller | income | 103 | 118 | ✓ | + nominal |
| 3 | Seller | commercial | 103 | 118 | ✓ | + nominal; leasing fee options differ |
| 4 | Seller | business | 103 | 118 | ✓ | + nominal |
| 5 | Seller | vacant_land | 102 | 117 | ✓ | |
| 6 | Buyer | residential | 92 | 112 | ✓ | lease_fee_type `'flat'` slug; ETF case |
| 7 | Buyer | income | 91 | 110 | ✓ | commercial+ lease fee options |
| 8 | Buyer | commercial | 91 | 110 | ✓ | |
| 9 | Buyer | business | 91 | 110 | ✓ | |
| 10 | Buyer | vacant_land | 91 | 110 | ✓ | |
| 11 | Landlord | residential | 114 | 127 | ✓ except renewal flat | KEY_MISMATCH_BUG; split_payment_due typo |
| 12 | Landlord | commercial | 113 | 126 | ✓ except renewal flat | KEY_MISMATCH_BUG; expansion fee |
| 13 | Tenant | residential | 87 | 105 | ✓ | lease_fee_type `'Flat Fee'`; ETF/retainer case |
| 14 | Tenant | commercial | 86 | 103 | ✓ | commercial lease fee options |

> Field counts include all applicable fields (shared + role-specific), excluding profile-only fields that are never mapped to bids. Counts differ slightly by property type based on which sections are conditionally rendered.

---

## 16. Appendix C — Source File Index

| File | Role in Audit |
|------|--------------|
| `app/Services/AgentBidMapperService.php` | Canonical preset→bid mapping (132 emitted keys) |
| `app/Models/AgentDefaultProfile.php` | Preset data model; `profile_data` JSON |
| `app/Http/Controllers/AgentPresetController.php` | `PROFILE_FIELDS` constant; save/load |
| `config/agent_preset_compensation.php` | Dropdown option values per role/property-type |
| `resources/views/agent-presets/edit.blade.php` | Preset editor UI; all input names and sections |
| `app/Http/Livewire/Seller/SellerAgentAuctionBid.php` | Seller bid component; all public properties |
| `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php` | Buyer bid component |
| `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php` | Landlord bid component |
| `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php` | Tenant bid component |
| `app/Helpers/SellerBidMatchScoreHelper.php` | Seller score field groups |
| `app/Helpers/BuyerBidMatchScoreHelper.php` | Buyer score field groups |
| `app/Helpers/LandlordBidMatchScoreHelper.php` | Landlord score field groups |
| `app/Helpers/TenantBidMatchScoreHelper.php` | Tenant score field groups |
