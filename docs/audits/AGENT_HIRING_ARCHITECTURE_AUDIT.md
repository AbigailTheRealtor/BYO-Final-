# Agent Hiring Workflow Architecture Audit

> **Created:** 2026-06-16  
> **Status:** Authoritative — single source of truth for field ownership in the agent hiring ecosystem.  
> **Companion document:** `AGENT_PRESET_DRIVEN_BIDDING_ARCHITECTURE.md` (covers preset→bid mapping mechanics; this document covers ownership classification).

---

## Table of Contents

1. [Overview and Purpose](#overview-and-purpose)
2. [Layer Definitions](#layer-definitions)
3. [Profile-Owned Fields (users + user_meta)](#profile-owned-fields)
4. [Preset-Owned Fields (agent_default_profiles.profile_data)](#preset-owned-fields)
5. [Listing-Owned Fields (per role)](#listing-owned-fields)
6. [Bid-Owned Fields (per role)](#bid-owned-fields)
7. [Cross-Layer Duplication Inventory](#cross-layer-duplication-inventory)
8. [Foundational Ownership Decisions (Decision Log)](#decision-log)
9. [Master Field Classification Table](#master-field-classification-table)
10. [Summary Ownership Matrix](#summary-ownership-matrix)
11. [Hire Me Flow Annotation](#hire-me-flow-annotation)
12. [Proof and Quantitative Summary](#proof-and-quantitative-summary)

---

## Overview and Purpose

The BidYourOffer platform has five distinct storage layers in the agent hiring ecosystem:

| Layer | Primary Table | Storage Model |
|---|---|---|
| **Profile** | `users` + `user_meta` | Native columns + EAV |
| **Preset** | `agent_default_profiles` | JSONB `profile_data` |
| **Listing** | `{role}_agent_auctions` + `{role}_agent_auction_metas` | Native columns + EAV |
| **Bid** | `{role}_agent_auction_bids` + `{role}_agent_auction_bid_metas` | Native columns + EAV |
| **Accepted Summary** | `accepted_bid_summaries` | Snapshot (out of scope) |

Fields have been added to multiple layers over time without a coordination policy, producing three classes of duplication:

- **Intentional / snapshot** – A value is copied from one layer to another at a specific point in time (e.g., `services` on the bid is a snapshot of what was agreed).
- **Pre-fill / transit** – A value flows one-way from a higher-authority layer to seed a lower layer (e.g., preset → bid form). The destination is not authoritative; it is merely pre-filled.
- **Accidental / unresolved** – A value exists in two places with no clear owner, no documented copy direction, and no consistency guarantee. These are called out explicitly below.

This document resolves all three classes for every field in the system.

---

## Layer Definitions

### Classification Keywords

| Classification | Meaning |
|---|---|
| **Profile** | Permanent per-agent identity. Survives role/property-type changes. |
| **Preset** | Reusable per-role+property-type defaults. The agent's public offer template. |
| **Listing** | Client-created or Hire-Me-created engagement record. Captures what the *client* wants. |
| **Bid** | Agent-submitted proposal. Captures what the *agent* offers for a specific listing. |
| **Shared** | Intentionally duplicated across two or more layers; rationale documented below. |
| **Deprecated** | Exists in the DB but is no longer used by live code; should be migrated or removed. |

---

## Profile-Owned Fields

**Source of truth:** `users` table (native columns) and `user_meta` EAV table.

These fields describe the agent's permanent, role-agnostic identity. They exist once per agent and are not scoped to a specific preset, listing, or bid.

### Native `users` Table Columns (agent-relevant)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `first_name` | string | |
| `middle_name` | string | Added 2024 |
| `last_name` | string | |
| `name` | string | Legacy full-name field (first + last concatenated on creation) |
| `short_id` | string unique | Public-facing shareable ID for Hire Me URLs |
| `user_name` | string | |
| `email` | string unique | |
| `user_type` | enum | `admin`, `buyer`, `seller`, `buyer_agent`, `seller_agent`, `agent`, `tenant` |
| `phone` | string | |
| `phone_number` | string | Added 2024 (legacy field; use `phone`) |
| `mls_id` | string | |
| `website` | string | |
| `description` | text | |
| `avatar` | string | File path |
| `cover_photo` | string | File path |
| `language` | string | |
| `country_id` | integer | |
| `state_id` | integer | |
| `city_id` | integer | |
| `address1` | text | |
| `address2` | text | |
| `town` | string | |
| `zip` | string | |
| `is_approved` | boolean | |
| `is_super` | boolean | |
| `is_deleted` | boolean | |
| `referred_by_agent_id` | bigint FK | Referral tracking |
| `referral_source_code` | string | Referral tracking |
| `referral_captured_at` | timestamp | Referral tracking |

**Important:** `brokerage`, `license_no`, `nar_id`, and `year_licensed` are **not** native columns on `users`. Despite being referenced as `$user->brokerage` in Livewire components, these properties return `null` from the users table. Their canonical values live in `user_meta` EAV (keys: `brokerage`, `license_no`, `nar_id`, `year_licensed`) or in the agent's preset `profile_data`.

### `user_meta` EAV Keys (agent-relevant)

| Key | Category | Notes |
|---|---|---|
| `brokerage` | Identity | Not in `users` table; always read from user_meta or preset |
| `license_no` | Identity | Not in `users` table |
| `nar_id` | Identity | Not in `users` table |
| `year_licensed` | Credentials | |
| `bio` | Profile content | Agent's self-description |
| `why_hire_you` | Profile content | |
| `what_sets_you_apart` | Profile content | |
| `marketing_plan` | Profile content | |
| `years_experience` | Experience | |
| `transactions_last_12_months` | Experience | |
| `avg_response_time` | Availability | |
| `is_full_time` | Availability | |
| `primary_areas_served` | Geography | |
| `cities_served` | Geography | |
| `counties_served` | Geography | |
| `neighborhoods_served` | Geography | |
| `areas_notes` | Geography | |
| `services` | Services | JSON; agent's general service offering |
| `other_services` | Services | JSON; custom/additional services |
| `intro_video_url` | Media | |
| `video_caption` | Media | |
| `presentation_link` | Media | |
| `business_card_link` | Media | |
| `website_link` | Media | |
| `social_media` | Social proof | JSON array |
| `reviews_links` | Social proof | JSON array |
| `review_1`, `review_2`, `review_3` | Social proof | Testimonial text |
| `availability_status` | Availability | |
| `evenings_available` | Availability | |
| `weekends_available` | Availability | |
| `communication_style` | Compatibility | |
| `preferred_contact_method` | Compatibility | |
| `brokerage_relationship` | Agency | |

**Total Profile-owned fields (users + user_meta): 56**

---

## Preset-Owned Fields

**Source of truth:** `agent_default_profiles` table, `profile_data` JSONB column.

Presets are scoped to `(user_id, role_type, property_type)`. One row per combination. A special `property_type = '__default__'` acts as a role-level fallback. All field values below are stored as JSON keys within `profile_data`.

The **save-scope selector** (`profile_save_scope`: `current_preset`, `current_role`, `all_roles`) applies to `PROFILE_FIELDS` only — the fields listed under "Public Profile / Identity" below. Compensation and services are always preset-scoped.

### A. Public Profile / Identity Fields (`PROFILE_FIELDS` in `AgentPresetController`)

These fields are subject to the save-scope propagation logic. When an agent saves a preset with `current_role` scope, these values overwrite the same keys in every preset for that role.

| Key | Category |
|---|---|
| `bio` | Profile content |
| `why_hire_you` | Profile content |
| `what_sets_you_apart` | Profile content |
| `marketing_plan` | Profile content |
| `additional_details` | Profile content |
| `year_licensed` | Credentials |
| `first_name` | Identity |
| `last_name` | Identity |
| `phone` | Identity |
| `email` | Identity |
| `brokerage` | Identity |
| `license_no` | Identity |
| `nar_id` | Identity |
| `brokerage_relationship` | Agency |
| `presentation_link` | Media |
| `presentation_upload_path` | Media |
| `business_card_link` | Media |
| `business_card_upload_path` | Media |
| `reviews_links` | Social proof |
| `website_link` | Media |
| `social_media` | Social proof |
| `years_experience` | Experience |
| `transactions_last_12_months` | Experience |
| `primary_areas_served` | Geography |
| `avg_response_time` | Availability |
| `is_full_time` | Availability |
| `cities_served` | Geography |
| `counties_served` | Geography |
| `neighborhoods_served` | Geography |
| `areas_notes` | Geography |
| `review_1` | Social proof |
| `review_2` | Social proof |
| `review_3` | Social proof |
| `awards_recognition` | Social proof |
| `intro_video_url` | Media |
| `video_caption` | Media |
| `availability_status` | Availability |
| `evenings_available` | Availability |
| `weekends_available` | Availability |
| `communication_style` | Compatibility |
| `preferred_contact_method` | Compatibility |

### B. Services Fields (preset-scoped, NOT subject to save-scope propagation)

| Key | Notes |
|---|---|
| `services` | JSON array; strings from `AgentPresetCatalog::getServices($role, $propertyType)` |
| `other_services` | JSON array; free-form custom service strings |

### C. Compensation / Agency Agreement Fields (preset-scoped, NOT in `PROFILE_FIELDS`)

These are never propagated across roles or property types by the save-scope selector. They must be set per-preset explicitly.

**Shared (all roles):**

| Key | Description |
|---|---|
| `protection_period` | Post-termination protection window |
| `early_termination_fee_option` | Yes / No |
| `early_termination_fee_amount` | Dollar amount if early_termination_fee_option = Yes |
| `agency_agreement_timeframe` | Duration (e.g., "3 Months") |
| `agency_agreement_custom` | Free-text when timeframe = "Other" |
| `interested_lease_option_agreement` | Yes / No |
| `lease_type` | percent / flat |
| `lease_value` | Numeric |
| `purchase_type` | percent / flat |
| `purchase_value` | Numeric |
| `commission_structure` | E.g., "Purchase Fee" |
| `purchase_fee_type` | Fee-type selector |
| `purchase_fee_flat` | Flat dollar amount |
| `purchase_fee_percentage` | Percentage |
| `purchase_fee_percentage_combo` | Combo percentage |
| `purchase_fee_flat_combo` | Combo flat |
| `purchase_fee_other` | Free text |
| `retainer_fee_option` | Yes / No |
| `retainer_fee_amount` | Dollar amount |
| `retainer_fee_application` | Applied toward / in addition to |
| `retained_deposits` | Yes / No |
| `referral_fee_percent` | Numeric 0–100 |
| `additional_details_broker` | Free text |

**Seller-specific:**

| Key | Description |
|---|---|
| `nominal` | Yes / No |
| `commission_structure_type` | Buyer broker sub-type |
| `commission_structure_type_fee_flat` | |
| `commission_structure_type_fee_flat_combo` | |
| `commission_structure_type_fee_percentage` | |
| `commission_structure_type_fee_percentage_combo` | |
| `commission_structure_type_fee_other` | |
| `interested_purchase_fee_type` | Yes / No |
| `seller_leasing_fee_type` | |
| `seller_leasing_gross` | |
| `seller_leasing_gross_rental` | |
| `seller_leasing_gross_month_rent` | |
| `sales_tax_option_gross` | |
| `seller_leasing_gross_other` | |
| `seller_leasing_gross_percentage` | |
| `seller_leasing_gross_purchase_fee_flat_amount` | |
| `seller_leasing_gross_purchase_fee_other` | |
| `seller_leasing_each_rental` | |
| `seller_leasing_gross_no_of_months` | |
| `seller_leasing_gross_flat_combo` | |
| `seller_leasing_gross_percentage_combo` | |
| `seller_leasing_gross_flat_net_combo` | |
| `seller_leasing_gross_percentage_net_combo` | |
| `seller_leasing_gross_sales_tax_first_month` | |
| `seller_leasing_gross_sales_tax_option_gross` | |
| `seller_leasing_gross_sales_tax_flat_free_gross` | |

**Buyer/Tenant-specific:**

| Key | Description |
|---|---|
| `interested_lease_option` | Yes / No for Lease Agreement |
| `lease_fee_type` | Fee-type selector |
| `lease_fee_flat` | |
| `lease_fee_percentage` | |
| `lease_fee_percentage_monthly_rent` | |
| `lease_fee_percentage_monthly_number` | |
| `lease_fee_flat_combo` | |
| `lease_fee_percentage_combo` | |
| `lease_fee_percentage_net` | |
| `lease_fee_flat_combo_net` | |
| `lease_fee_percentage_combo_net` | |
| `lease_fee_other` | |

**Landlord-specific (residential):**

| Key | Description |
|---|---|
| `purchase_fee_rental_period` | |
| `tenant_broker_commission_structure` | |
| `tenant_broker_fee_structure` | |
| `tenant_broker_percentage` | |
| `tenant_broker_gross_lease` | |
| `tenant_broker_first_month_rent` | |
| `tenant_broker_flat_fee` | |
| `tenant_broker_other` | |

**Landlord-specific (commercial):**

| Key | Description |
|---|---|
| `purchase_fee_net_aggregate` | |
| `purchase_fee_gross_rent` | |
| `purchase_fee_monthly_percentage` | |
| `purchase_fee_months` | |
| `sales_tax_option_monthly` | |
| `purchase_fee_flat_commercial` | |
| `sales_tax_option_flat` | |
| `purchase_fee_purchase_price` | |
| `purchase_fee_other_commercial` | |
| `expansion_commission_percentage` | |

**Landlord-specific (fee timing):**

| Key | Description |
|---|---|
| `broker_fee_timing` | |
| `broker_fee_days_from_rent` | |
| `broker_fee_days_after_lease` | |
| `broker_fee_days_after_rent` | |
| `broker_fee_timing_other` | |
| `split_payment_due` | |
| `split_payment_due_other` | |
| `broker_fee_days_after_due_event` | |

**Landlord-specific (renewal/expansion):**

| Key | Description |
|---|---|
| `renewal_fee_type` | |
| `renewal_fee_percentage` | |
| `renewal_fee_lease_value` | |
| `renewal_fee_first_month` | |
| `renewal_fee_flat_fee` | (canonical; alias `renewal_fee_flat_free` exists in old records) |
| `renewal_fee_custom` | |
| `renewal_fee_sales_tax_lease_value` | |
| `renewal_fee_no_of_months` | |
| `renewal_fee_sales_tax_first_month` | |
| `renewal_fee_sales_tax_flat_fee` | |

**Landlord-specific (property management):**

| Key | Description |
|---|---|
| `interested_in_property_management` | |
| `interested_in_property_management_fee` | |
| `interested_in_property_management_fee_gross_lease` | |
| `interested_in_property_management_fee_rental_periord` | ⚠️ typo in column name |
| `interested_in_property_management_fee_flate_free` | ⚠️ typo in column name |
| `interested_in_property_management_fee_other` | |

**Landlord-specific (interested in selling):**

| Key | Description |
|---|---|
| `interested_in_selling` | |
| `interested_in_selling_type` | |
| `landlord_broker_purchase_price` | |
| `landlord_broker_percentage_price` | |
| `landlord_broker_dollar_price` | |
| `landlord_broker_flate_fee` | ⚠️ typo in key name |
| `landlord_broker_other` | |

**Tenant/Landlord (fee timing — Tenant uses subset of Landlord timing keys):**

| Key | Tenant | Landlord |
|---|---|---|
| `broker_fee_timing` | ✓ | ✓ |
| `broker_fee_days_from_rent` | ✓ | ✓ |
| `broker_fee_days_after_lease` | ✓ | ✓ |
| `broker_fee_days_after_rent` | ✓ | ✓ |
| `broker_fee_timing_other` | ✓ | ✓ |

### D. Compatibility Preferences (preset-scoped, sub-object)

| Key | Sections |
|---|---|
| `compatibility_preferences` | Object containing 7 sections: `communication_preferences`, `negotiation_approach`, `guidance_style`, `collaboration_preferences`, `transaction_strategy`, `representation_philosophy`, `representation_priorities` |

**Total Preset-owned fields: ~41 identity/profile keys + 2 service keys + ~100 compensation keys + 7 compatibility sections ≈ 150 distinct keys**

---

## Listing-Owned Fields

**Source of truth:** Four `{role}_agent_auctions` tables + their `_metas` EAV tables.

Listings describe what the *client* wants. Created by the client directly (via the Offer Listing form) or automatically by `HireAgentDirectController::commitListingAndBid()` on behalf of a client during the Hire Me flow.

### Schema Asymmetry: Native vs EAV

| Role | Native Columns | EAV-only |
|---|---|---|
| Seller | Many legacy columns (address, sqft, financings, etc.) + modern meta | Yes — all modern fields |
| Buyer | Many legacy columns (title, address, property_type_id, additional_details, etc.) + modern meta | Yes — all modern fields |
| Landlord | Minimal (id, user_id, title, auction_type, status flags) | Almost entirely EAV |
| Tenant | Minimal (id, user_id, title, auction_type, status flags) | Almost entirely EAV |

### Common Listing Native Columns (all roles where applicable)

| Column | Seller | Buyer | Landlord | Tenant | Notes |
|---|---|---|---|---|---|
| `id` | ✓ | ✓ | ✓ | ✓ | PK |
| `user_id` | ✓ | ✓ | ✓ | ✓ | FK to client (or agent in Hire Me flow) |
| `address` | ✓ | ✓ | – | – | Native only for Seller/Buyer |
| `title` | ✓ | ✓ | ✓ | ✓ | Added 2026 for Landlord/Tenant |
| `auction_type` | ✓ | ✓ | ✓ | ✓ | |
| `auction_length` | ✓ | ✓ | – | – | |
| `is_approved` | ✓ | ✓ | ✓ | ✓ | |
| `is_draft` | ✓ | ✓ | ✓ | ✓ | |
| `is_sold` | ✓ | ✓ | ✓ | ✓ | |
| `sold_date` | ✓ | ✓ | ✓ | ✓ | |
| `auction_ended` | – | – | ✓ | ✓ | |
| `referring_agent_id` | ✓ | ✓ | ✓ | ✓ | Referral tracking |
| `referral_source_code` | ✓ | ✓ | ✓ | ✓ | Referral tracking |
| `referral_locked` | ✓ | ✓ | ✓ | ✓ | Referral tracking |

### Listing EAV Meta Keys

**System / lifecycle (all roles):**

| Key | Description |
|---|---|
| `workflow_type` | `hire_agent` (Hire Me) or `offer_listing` (regular form) |
| `listing_status` | `Active`, `Pending`, `Hired Agent`, `Expired` |
| `service_type` | `full_service` / `limited_service` |
| `auction_type` | `Traditional` / `Auction (Timer)` / etc. |
| `property_type` | `residential`, `commercial`, `income`, `business`, `vacant_land` |
| `expiration_date` | Date string |
| `hire_me_flow` | `1` when created via Hire Me |
| `is_draft` | EAV shadow for landlord/tenant (redundant with native column) |

**Client-requested services (all roles):**

| Key | Description |
|---|---|
| `services` | JSON array — services the client selected from the agent's preset |
| `other_services` | JSON array — custom services |
| `other_services_enabled` | `0` / `1` |
| `client_custom_services` | JSON array — free-text services the client typed themselves |
| `client_additional_requested` | Free text — client's additional requests (accept flow) |

**Client identity (Hire Me flow only, all roles):**

| Key | Description |
|---|---|
| `client_name` | Full name (synthesized from first+last) |
| `client_phone` | |
| `client_email` | |
| `client_preferred_comm_method` | |
| `client_preferred_comm_method_other` | |
| `client_top_priority` | |
| `client_top_priority_other` | |

**Client property/search (Hire Me, role-specific):**

| Key | Role | Description |
|---|---|---|
| `client_property_address` | Seller, Landlord | |
| `client_property_city` | Seller, Landlord | |
| `client_property_state` | Seller, Landlord | |
| `client_property_zip` | Seller, Landlord | |
| `client_desired_sale_price` | Seller | |
| `client_timeline_to_sell` | Seller | |
| `client_motivation_level` | Seller | |
| `client_areas_of_interest` | Buyer, Tenant | |
| `client_target_purchase_price` | Buyer | |
| `client_timeline_to_purchase` | Buyer | |
| `client_financing_status` | Buyer | |
| `client_financing_status_other` | Buyer | |
| `client_estimated_down_payment` | Buyer | |
| `client_desired_monthly_rent` | Landlord | |
| `client_availability_date` | Landlord | |
| `client_occupancy_status` | Landlord | |
| `client_flexibility` | Landlord | |
| `client_max_monthly_lease_price` | Tenant | |
| `client_desired_lease_length` | Tenant | |
| `client_move_in_date` | Tenant | |
| `client_number_of_occupants` | Tenant | |
| `client_household_monthly_income` | Tenant | |

**Compensation terms requested by client (Seller and Buyer listing forms — CONTESTED, see Decision Log §6.1):**

These keys appear in `seller_agent_auction_metas` and `buyer_agent_auction_metas` because the Seller/Buyer listing creation Livewire components collect and save compensation preferences that are then pre-filled onto agent bids. This pattern does NOT appear in Landlord/Tenant listings.

| Key | Seller Listing | Buyer Listing |
|---|---|---|
| `purchase_fee_type` | ✓ | ✓ |
| `purchase_fee_flat` | ✓ | ✓ |
| `purchase_fee_percentage` | ✓ | ✓ |
| `commission_structure` | ✓ | ✓ |
| `protection_period` | ✓ | ✓ |
| `early_termination_fee_option` | ✓ | ✓ |
| `early_termination_fee_amount` | ✓ | ✓ |
| `retainer_fee_option` | ✓ | ✓ |
| `retainer_fee_amount` | ✓ | ✓ |
| `retainer_fee_application` | ✓ | ✓ |
| `agency_agreement_timeframe` | ✓ | ✓ |
| `brokerage_relationship` | ✓ | ✓ |
| `referral_percentage` | ✓ | ✓ |
| + all seller_leasing_* sub-fields | ✓ | – |

> ⚠️ **Key name inconsistency:** The listing stores `referral_percentage`; the bid stores `referral_fee_percent`. These are the same semantic value with different key names.

**Additional listing EAV (property-level, from listing creation forms):**

These exist for regular Offer Listing forms and vary by role. Refer to the individual Livewire listing form components (`SellerOfferListing`, `BuyerOfferListing`, etc.) for the complete set. Common examples include: `maximum_budget`, `desired_lease_length`, `property_size`, `min_bedrooms`, `min_bathrooms`, `location_preference`, `max_hoa_fee`, `pet_preference`, `description`, `photos`, `property_photos`, `additional_details`.

**Total Listing-owned fields: ~15 native columns + ~50 EAV meta keys (system + client contact + compensation), plus role-specific property/criteria keys (varies; ~30–60 per role)**

---

## Bid-Owned Fields

**Source of truth:** Four `{role}_agent_auction_bids` tables + their `_metas` EAV tables.

Bids describe what the *agent* proposes for a specific listing. An agent submits a bid on a client's listing (or one is auto-created by `HireAgentDirectController`).

### Schema Asymmetry: Native vs EAV

| Role | Native Bid Columns | Storage Model |
|---|---|---|
| Seller | Rich legacy schema (name, brokerage float, price, price_percent, etc.) | Legacy native + modern EAV |
| Buyer | Rich legacy schema (name, brokerage float, price, price_percent, etc.) | Legacy native + modern EAV |
| Landlord | Minimal (id, user_id, accepted, accepted_date, counter_id) | Entirely EAV for content |
| Tenant | Minimal (id, user_id, accepted, accepted_date) | Entirely EAV for content |

### Legacy Native Bid Columns (Seller and Buyer — Deprecated)

These existed before the EAV meta system was added. Modern code writes to EAV meta instead. These native columns are **Deprecated** — no Livewire bid component writes them via modern submit paths. They should eventually be migrated to EAV-only.

| Column | Seller Bid | Buyer Bid | Canonical EAV Key |
|---|---|---|---|
| `name` | ✓ | ✓ | `first_name` + `last_name` |
| `brokerage` (float) | ✓ | ✓ | `brokerage` (string) in meta |
| `license_no` | ✓ | ✓ | `license_no` in meta |
| `phone` | ✓ | ✓ | `phone` in meta |
| `email` | ✓ | ✓ | `email` in meta |
| `mls_id` | ✓ | ✓ | Not in modern EAV |
| `county_id` | ✓ | ✓ | Not in modern EAV |
| `price` / `price_percent` | ✓ | ✓ | `purchase_fee_flat` / `purchase_fee_percentage` |
| `reviews_link` | ✓ | ✓ | `reviews_links` (JSON) in meta |
| `website_link` | ✓ | ✓ | `website_link` (JSON) in meta |
| `additional_details` | ✓ | ✓ | `additional_details` in meta |
| `services_description` | ✓ | ✓ | `services` (JSON) in meta |
| `why_seller_pick_me` | ✓ | ✓ | `why_hire_you` in meta |
| `video` / `video_url` | ✓ | ✓ | `presentation_link` in meta |
| `listing_terms` | ✓ | – | `additional_details_broker` in meta |
| `market_analysis` | ✓ | – | Dropped |
| `note` | ✓ | ✓ | Not in modern EAV |
| `other` (json) | ✓ | ✓ | Not in modern EAV |
| `accepted` | ✓ | ✓ | Native column (retained) |
| `accepted_date` | ✓ | ✓ | Native column (retained) |
| `credit_offer` | – | ✓ | Not in modern EAV |

### Modern EAV Bid Meta Keys

All modern bid data is stored in EAV meta tables. All four roles share the following meta keys (written by `AgentBidMapperService::mapFromProfile()` and the bid form `submit()` methods).

**Agent overview:**

| Key | Description |
|---|---|
| `bio` | Agent's about text |
| `why_hire_you` | |
| `what_sets_you_apart` | |
| `marketing_plan` | |
| `year_licensed` | |
| `additional_details` | |

**Agent identity (credentials stamped onto bid at submission time):**

| Key | Description |
|---|---|
| `first_name` | |
| `last_name` | |
| `phone` | |
| `email` | |
| `brokerage` | String (vs deprecated native float column) |
| `license_no` | |
| `nar_id` | |

**Media:**

| Key | Description |
|---|---|
| `presentation_link` | URL |
| `presentation_upload_path` | File path |
| `business_card_link` | URL |
| `business_card_stored_path` | File path |
| `business_card_upload_path` | File path (upload slot) |
| `reviews_links` | JSON array |
| `website_link` | JSON array |
| `social_media` | JSON array |
| `promoMaterials` | JSON array of objects |

**Services (snapshot of agreed services):**

| Key | Description |
|---|---|
| `services` | JSON array — agent's offered services (from preset, filtered to catalog) |
| `other_services` | JSON array — custom services |
| `other_services_enabled` | `0` / `1` |
| `client_custom_services` | JSON array — services the client requested beyond the preset |
| `hire_me_auto_bid` | `1` when auto-created by Hire Me flow |

**Compatibility:**

| Key | Description |
|---|---|
| `compatibility_agent_response` | JSON object with 7 section arrays |

**Compensation (all roles, shared — see Preset-owned §C for full key list):**

All compensation keys from the Preset layer are also written to Bid meta at submission time. The bid is the final agreed (or proposed) set of compensation values. See [Decision Log §6.1](#d1-commission--fee-fields) for ownership reasoning.

**Hire Me client contact info (duplicated on bid with `counter_` prefix):**

| Key | Description |
|---|---|
| `counter_client_name` | Client's name |
| `counter_client_phone` | |
| `counter_client_email` | |
| `counter_property_address` | Seller / Landlord |
| `counter_property_city` | |
| `counter_property_state` | |
| `counter_property_zip` | |
| `counter_desired_sale_price` | Seller |
| `counter_timeline_to_sell` | Seller |
| `counter_motivation_level` | Seller |
| `counter_areas_of_interest` | Buyer / Tenant |
| `counter_target_purchase_price` | Buyer |
| `counter_timeline_to_purchase` | Buyer |
| `counter_financing_status` | Buyer |
| `counter_financing_status_other` | Buyer |
| `counter_estimated_down_payment` | Buyer |
| `counter_desired_monthly_rent` | Landlord |
| `counter_availability_date` | Landlord |
| `counter_occupancy_status` | Landlord |
| `counter_flexibility` | Landlord |
| `counter_max_monthly_lease_price` | Tenant |
| `counter_desired_lease_length` | Tenant |
| `counter_move_in_date` | Tenant |
| `counter_number_of_occupants` | Tenant |
| `counter_household_monthly_income` | Tenant |
| `counter_preferred_comm_method` | All roles |
| `counter_preferred_comm_method_other` | All roles |
| `counter_top_priority` | All roles |
| `counter_top_priority_other` | All roles |

> ⚠️ **Duplication note:** The Hire Me flow saves client contact data in BOTH listing meta (`client_*` prefix) and bid meta (`counter_*` prefix). This is intentional: the listing captures what the client submitted during the hire form; the bid captures the same data using the `counter_*` convention established by the Counter Terms form — so that existing accept/reject/counter code works without modification.

**Total Bid-owned fields: ~7 native (active) + ~5 deprecated native + ~100+ EAV meta keys**

---

## Cross-Layer Duplication Inventory

### Category A: Intentional / Snapshot Duplication (Justified)

| Field(s) | Layers | Rationale |
|---|---|---|
| `services`, `other_services` | Preset → Listing → Bid | Each layer captures the services state at a different transaction point. Preset = agent's reusable offer. Listing = what the client accepted when engaging. Bid = what the agent agreed to provide for this specific engagement. Snapshots are required because the preset can change after the listing is created. |
| `services`, `other_services` | Listing + Bid | Listing holds what the *client* selected; Bid holds what the *agent* agreed to provide. In the Hire Me flow both are set identically at creation time from the client's confirmed selection. In the regular flow the listing holds what the client requested, and the agent may offer a different (filtered) subset. |
| Agent identity fields (`first_name`, `last_name`, `phone`, `email`, `brokerage`, `license_no`, `nar_id`) | Profile → Preset → Bid | Profile is the permanent record. Preset pre-fills the bid form. Bid stamps the values at submission time so the bid record is self-contained and not broken if the agent later changes their profile. |
| All compensation fields | Preset → Bid | Preset is the reusable default. Bid is the per-engagement instance. The agent may modify compensation on the bid without affecting their preset. |
| Client contact fields (`client_*` / `counter_*`) | Listing + Bid | See note under Bid-Owned Fields. The `counter_*` key convention is required for compatibility with the Counter Terms flow. |

### Category B: Pre-fill Transit (Listing → Bid, Seller and Buyer only)

| Field(s) | Layers | Classification | Decision |
|---|---|---|---|
| All compensation fields (`purchase_fee_type`, `commission_structure`, `protection_period`, etc.) | Seller/Buyer Listing meta → Bid form pre-fill → Bid meta | **Shared (pre-fill transit)** | The listing stores the client's *requested* compensation terms; the bid form pre-fills these so the agent sees what the client expects, then the agent submits their own values. The canonical bid-time values live on the bid, not the listing. The listing copy is a "requested terms" snapshot, not an authority. |
| `referral_percentage` (listing) vs `referral_fee_percent` (bid) | Seller/Buyer Listing meta + Bid meta | **Shared with key name inconsistency** | Same semantic field, different keys. The listing uses `referral_percentage`; the bid uses `referral_fee_percent`. Requires a rename migration to unify. |

### Category C: Accidental / Unjustified Duplication

| Field(s) | Layers | Problem | Recommended Resolution |
|---|---|---|---|
| `avg_response_time` | Profile (user_meta) + Preset (profile_data) | Exists in both without a clear ownership policy. Preset edit UI saves it in `profile_data`; there is no code that synchronizes it back to user_meta or vice versa. | **Preset wins.** The preset editor is the agent's intent for a specific role/property type. user_meta `avg_response_time` should be treated as a global fallback and eventually consolidated. |
| `is_full_time` | Profile (user_meta) + Preset (profile_data) | Same as above. | **Preset wins** per role/property-type scope. |
| `communication_style` | Profile (user_meta) + Preset (profile_data) | Same. | **Preset wins.** |
| `preferred_contact_method` | Profile (user_meta) + Preset (profile_data) | Same. | **Preset wins.** |
| `cities_served`, `counties_served`, `neighborhoods_served`, `areas_notes` | Profile (user_meta) + Preset (profile_data) | Same. | **Preset wins** (role/property scoped). |
| `brokerage_relationship` | Profile (user_meta) + Preset (profile_data) | Same. | **Preset wins.** |
| `reviews_links`, `review_1/2/3`, `awards_recognition` | Profile (user_meta) + Preset (profile_data) | Testimonials are global to the agent but editable per preset in the UI. | **Preset wins** (per the save-scope propagation for profile fields). user_meta copies are legacy. |
| Legacy native columns on Seller/Buyer bid tables | Native bid columns + EAV meta | Modern code writes EAV; legacy columns are never read by Livewire components. | **EAV wins.** Native columns are Deprecated. Schedule removal migration. |

---

## Decision Log

### D1: Commission / Fee Fields — Where Does Compensation Live? {#d1-commission--fee-fields}

**Current State:** Compensation fields exist in three layers simultaneously:
1. **Preset** — the agent's reusable template
2. **Listing** (Seller/Buyer only) — the client's requested terms, pre-filled onto bid form
3. **Bid** — the agent's final proposed terms

**Problem:** No document previously defined which layer is authoritative, making it ambiguous whether to read compensation from a listing or a bid when displaying accepted terms.

**Decision:** **Bid owns the canonical compensation values.** The bid record holds the compensation as the agent agreed to it at submission time, potentially modified from the listing's requested terms. The listing's compensation fields are "client-requested" not "agreed-to."

**Rationale:** The compensation on the bid is the agent's proposal. The client accepts or counters it. The AcceptedBidSummary (the final accepted record) is derived from the bid, not the listing. Reading compensation from the bid is therefore always correct.

**Migration Path:** No code change required. The current code already reads compensation from bids for display and for AcceptedBidSummary generation. The listing compensation fields serve only as pre-fill seeds and should be labeled as such in any UI displaying them.

---

### D2: Services — Listing or Bid? {#d2-services}

**Current State:** `services` and `other_services` exist in both listing meta and bid meta.

**Problem:** Which copy represents what was "agreed" and which is "requested"?

**Decision:** **Both copies are intentional.** The listing's `services` = what the client accepted from the agent's preset menu. The bid's `services` = what the agent agreed to provide. In the Hire Me flow these are identical at creation time. In the regular listing flow the agent may add or remove services compared to what the listing requested.

**Rationale:** The bid is the agent's commitment. The listing is the client's request. They are semantically different even when the values are the same. Using the bid copy for display in bid detail views is correct. Using the listing copy for "what this listing is asking for" display is also correct.

**Migration Path:** None — current usage is correct. Document the semantic distinction in any future feature tickets involving services display.

---

### D3: Negotiation / Compatibility Style — Preset or Profile? {#d3-negotiation-style}

**Current State:** `communication_style`, `preferred_contact_method`, and `compatibility_preferences` exist in both user_meta (Profile layer) and preset `profile_data` (Preset layer). The preset editor UI writes to `profile_data`. The bid form reads from `profile_data` via `AgentBidMapperService`.

**Problem:** Are negotiation style / compatibility preferences permanent per-agent identity (Profile), or reusable per-role defaults (Preset)?

**Decision:** **Preset owns compatibility preferences.** They are role-contextual — an agent may have different communication styles for seller vs. buyer engagements. The Preset layer is scoped to `(role, property_type)` and is thus the correct home for these fields.

**Rationale:** An agent who is "collaborative" for buyer clients but "decisive" for seller clients needs the per-preset scoping. Permanently storing compatibility preferences in Profile would collapse this distinction. The `save_scope = current_role` or `all_roles` propagation in the preset editor already handles the common case of agents who want the same style across all presets.

**Migration Path:** The Profile-layer copies in user_meta (`communication_style`, `preferred_contact_method`) are accidental duplicates. Stop writing to user_meta for these fields. Do not delete them immediately — legacy records may rely on them — but treat Preset as the authority for all new code.

---

### D4: Response Time / Availability — Bid or Preset? {#d4-response-time}

**Current State:** `avg_response_time`, `availability_status`, `evenings_available`, and `weekends_available` exist in Preset `profile_data`. They are displayed on the public Hire Me preview page. They are included in `PROFILE_FIELDS` (subject to save-scope propagation). They are **NOT** written to bid meta by `AgentBidMapperService::mapFromProfile()` — they are absent from the mapper's output.

**Problem:** The agent advertises their response time on the Hire Me page, but this data is never stamped onto the bid. A future developer could not read `avg_response_time` from a bid record.

**Decision:** **Preset owns availability/response-time as a public-display field. It should also be stamped onto the bid.** Currently it is not, which is a gap.

**Rationale:** Response time is an offer-time signal (the agent is committing to how quickly they will respond for this engagement). It belongs on the bid as a committed value, not just in the preset as a display-only default. Until the mapper is updated, the current behavior is display-only.

**Migration Path:** Add `avg_response_time`, `availability_status`, `evenings_available`, and `weekends_available` to `AgentBidMapperService::mapFromProfile()`. This is a one-line change per field in the mapper and ensures Hire Me auto-bids and preset-prefilled bid forms capture the agent's availability commitment.

---

### D5: Communication Preferences — Both Layers or Preset-Only? {#d5-communication-preferences}

**Current State:** `preferred_contact_method` / `communication_style` exist in Preset. The bid form has `compatibility_agent_response` (a 7-section object including `communication_preferences`) which is written to bid meta. The Preset `compatibility_preferences` object maps to `compatibility_agent_response` on the bid via `AgentBidMapperService::mapCompatibilityFromProfile()`.

**Problem:** Should communication preferences live in both Preset and Bid, or Preset-only with the bid inheriting?

**Decision:** **Both — intentional snapshot.** The Preset holds the agent's default communication style for this role/property type. The bid stamps a copy so the bid record is self-contained and not broken if the agent updates their preset later. This is the same snapshot pattern applied to services and compensation.

**Rationale:** Once a client accepts a bid, the communication preferences the agent committed to at bid time should be preserved for the engagement. Tying to the live Preset would allow agents to retroactively change their committed preferences.

**Migration Path:** None. Current design is correct. Ensure `mapCompatibilityFromProfile()` is called in all bid mount() paths (including Hire Me auto-bid creation).

> **Current gap identified:** The Hire Me auto-bid creation in `commitListingAndBid()` does NOT call `mapCompatibilityFromProfile()`. Compatibility preferences from the preset are not stamped onto Hire Me auto-bids. Only the fields in `mapFromProfile()` are written. This means Hire Me bids lack compatibility data.

---

### D6: Unjustified Duplication — Current Inventory {#d6-unjustified-duplication}

**Current State:** Several fields exist in both `user_meta` (Profile) and preset `profile_data` (Preset) without a defined ownership policy.

**Problem:** If both copies exist and code reads from either inconsistently, display can differ based on code path.

**Decision:** **Preset wins for all profile-content fields that appear in the Preset editor UI.** These are: `avg_response_time`, `is_full_time`, `communication_style`, `preferred_contact_method`, `cities_served`, `counties_served`, `neighborhoods_served`, `areas_notes`, `brokerage_relationship`, `reviews_links`, `review_1/2/3`, `awards_recognition`, `years_experience`, `transactions_last_12_months`.

**Rationale:** The Preset editor is the agent's intentional, current-state management UI. The `user_meta` copies are from an older code path and may not reflect the agent's most recent edits. Any code that reads these fields for display in bid forms, the Hire Me preview, or bid detail views should read from `profile_data` via the Preset layer.

**Migration Path:** Audit all controllers and views that read directly from `user_meta` for these fields and redirect them to `AgentDefaultProfile::findForAgentWithFallback()` → `profile_data`. Do not delete `user_meta` keys immediately.

---

## Master Field Classification Table

The following tables classify every major field category per role. Fields that exist in multiple layers are classified by their **primary owner** (authoritative source), with secondary layers noted.

### All Roles — Cross-Role Fields

| Field Key | Primary Owner | Secondary Layer(s) | Classification | Notes |
|---|---|---|---|---|
| `first_name`, `last_name` | Profile (users) | Preset, Bid | **Shared** | Preset pre-fills bid; bid snapshots at submit time |
| `email` | Profile (users) | Preset, Bid | **Shared** | Same pattern |
| `phone` | Profile (users) | Preset, Bid | **Shared** | Same pattern |
| `mls_id` | Profile (users native) | – | **Profile** | Not in bid EAV |
| `brokerage` | user_meta (string) | Preset, Bid | **Shared** | Native bid column (float) is Deprecated |
| `license_no` | user_meta | Preset, Bid | **Shared** | |
| `nar_id` | user_meta | Preset, Bid | **Shared** | |
| `year_licensed` | user_meta | Preset, Bid | **Shared** | |
| `short_id` | Profile (users native) | – | **Profile** | Hire Me URL key |
| `avatar` | Profile (users native) | – | **Profile** | |
| `bio` | Preset (profile_data) | Bid | **Shared** | Bio is role-scoped in preset |
| `why_hire_you` | Preset | Bid | **Shared** | |
| `what_sets_you_apart` | Preset | Bid | **Shared** | |
| `marketing_plan` | Preset | Bid | **Shared** | |
| `additional_details` | Preset | Bid | **Shared** | |
| `services` | Preset (template) | Listing, Bid | **Shared** | Each layer is a snapshot; see D2 |
| `other_services` | Preset (template) | Listing, Bid | **Shared** | Same |
| `compatibility_preferences` | Preset | Bid | **Shared** | Snapshot at bid submit; Hire Me gap noted |
| `avg_response_time` | Preset | — (gap) | **Preset** | Not currently stamped to bid — see D4 |
| `availability_status` | Preset | — (gap) | **Preset** | Same gap |
| `evenings_available` | Preset | — (gap) | **Preset** | Same gap |
| `weekends_available` | Preset | — (gap) | **Preset** | Same gap |
| `years_experience` | Preset | – | **Preset** | Display-only; not stamped to bid |
| `transactions_last_12_months` | Preset | – | **Preset** | Display-only |
| `primary_areas_served` | Preset | – | **Preset** | Display-only |
| `cities_served` | Preset | – | **Preset** | |
| `counties_served` | Preset | – | **Preset** | |
| `neighborhoods_served` | Preset | – | **Preset** | |
| `areas_notes` | Preset | – | **Preset** | |
| `presentation_link` | Preset | Bid | **Shared** | |
| `business_card_link` | Preset | Bid | **Shared** | |
| `reviews_links` | Preset | Bid | **Shared** | |
| `website_link` | Preset | Bid | **Shared** | |
| `social_media` | Preset | Bid | **Shared** | |
| `promoMaterials` | Preset | Bid | **Shared** | |
| `review_1/2/3` | Preset | – | **Preset** | Not stamped to bid |
| `awards_recognition` | Preset | – | **Preset** | Not stamped to bid |
| All compensation fields | Preset (default) | Listing (requested), Bid (agreed) | **Shared** | See D1; Bid is canonical at engagement time |
| `brokerage_relationship` | Preset | Bid | **Shared** | |
| `additional_details_broker` | Preset | Bid | **Shared** | |
| `retained_deposits` | Preset | Bid | **Shared** | |
| `referral_fee_percent` | Preset | Bid | **Shared** | Key name differs in listing (`referral_percentage`) |
| `workflow_type` | Listing | – | **Listing** | System metadata |
| `listing_status` | Listing | – | **Listing** | System metadata |
| `expiration_date` | Listing | – | **Listing** | System metadata |
| `hire_me_flow` | Listing | Bid (`hire_me_auto_bid`) | **Listing** | Bid flag is a derivative marker |
| `client_name`, `client_phone`, etc. | Listing (`client_*`) | Bid (`counter_*`) | **Listing** | Bid copy is intentional for counter-flow compatibility |
| `client_custom_services` | Listing | Bid | **Shared** | Client-authored; not from preset |

### Role-Specific Fields

| Field Key | Role | Primary Owner | Classification | Notes |
|---|---|---|---|---|
| `nominal` (nominal fee) | Seller | Preset → Bid | **Shared** | |
| `commission_structure_type` | Seller | Preset → Bid | **Shared** | Buyer broker compensation |
| `interested_purchase_fee_type` | Seller, Tenant | Preset → Bid | **Shared** | |
| `seller_leasing_*` (all sub-fields) | Seller | Preset → Bid | **Shared** | Leasing commission |
| `interested_lease_option` | Buyer, Tenant | Preset → Bid | **Shared** | |
| `lease_fee_*` (all sub-fields) | Buyer, Tenant | Preset → Bid | **Shared** | |
| `purchase_fee_rental_period` | Landlord | Preset → Bid | **Shared** | Residential lease sub-field |
| `purchase_fee_net_aggregate`, `purchase_fee_gross_rent`, etc. | Landlord | Preset → Bid | **Shared** | Commercial lease sub-fields |
| `tenant_broker_*` (all sub-fields) | Landlord | Preset → Bid | **Shared** | |
| `broker_fee_timing`, `split_payment_due`, etc. | Landlord, Tenant | Preset → Bid | **Shared** | |
| `renewal_fee_*` (all sub-fields) | Landlord | Preset → Bid | **Shared** | |
| `expansion_commission_percentage` | Landlord | Preset → Bid | **Shared** | |
| `interested_in_property_management*` | Landlord | Preset → Bid | **Shared** | ⚠️ typo in key names |
| `interested_in_selling*` | Landlord | Preset → Bid | **Shared** | |
| `landlord_broker_*` | Landlord | Preset → Bid | **Shared** | |

### Deprecated Fields

| Field Key / Column | Location | Reason | Migration Recommendation |
|---|---|---|---|
| `name` | Seller/Buyer bid native column | Replaced by `first_name` + `last_name` in EAV | Remove after confirming no reads |
| `brokerage` (float) | Seller/Buyer bid native column | Replaced by `brokerage` (string) in EAV | Remove after confirming no reads |
| `license_no`, `phone`, `email` | Seller/Buyer bid native columns | Replaced by EAV equivalents | Remove after confirming no reads |
| `price`, `price_percent` | Seller/Buyer bid native columns | Replaced by `purchase_fee_flat`/`purchase_fee_percentage` in EAV | Remove after confirming no reads |
| `services_description` | Seller/Buyer bid native columns | Replaced by `services` JSON in EAV | Remove |
| `why_seller_pick_me` | Seller/Buyer bid native columns | Replaced by `why_hire_you` in EAV | Remove |
| `reviews_link` | Seller/Buyer bid native columns | Replaced by `reviews_links` JSON in EAV | Remove |
| `website_link` | Seller/Buyer bid native columns | Replaced by `website_link` JSON in EAV | Remove |
| `video`, `video_url`, `audio` | Seller/Buyer bid native columns | Replaced by `presentation_link` in EAV | Remove |
| `market_analysis`, `note`, `other` | Seller/Buyer bid native columns | No EAV equivalent; data orphaned | Evaluate for migration or removal |
| `mls_id`, `county_id` | Seller/Buyer bid native columns | Never written by modern code | Remove |
| `credit_offer` | Buyer bid native column | No EAV equivalent | Evaluate for removal |
| `avg_response_time`, `communication_style`, etc. | user_meta | Superseded by Preset copies; see D6 | Stop writing; treat as legacy fallback |
| `renewal_fee_flat_free` | Landlord bid meta | Typo of `renewal_fee_flat_fee`; renamed by 2026-06 migration | Use canonical `renewal_fee_flat_fee` only |

---

## Summary Ownership Matrix

Rows = ownership layers. Columns = field categories. Each cell answers: which fields in this category live here, and how many.

| Layer | Identity | Compensation | Services | Terms/Agreement | Compatibility | Media | Social Proof | Availability |
|---|---|---|---|---|---|---|---|---|
| **Profile** | `id`, `first_name`, `last_name`, `email`, `phone`, `short_id`, `avatar`, `mls_id`, `user_type`, address fields (~15) | – | Global `services`/`other_services` in user_meta (legacy) | – | `communication_style`, `preferred_contact_method` (superseded by Preset) | `website`, `cover_photo` | `review_1/2/3` (superseded) | `avg_response_time` (superseded) |
| **Preset** | Identity fields pre-filled in profile_data (`brokerage`, `license_no`, `nar_id`, `year_licensed`, `first_name`, `last_name`, `phone`, `email`) — ~8 | All compensation fields per role/property-type — ~100 keys | `services`, `other_services` (role-specific catalog items) | `agency_agreement_timeframe`, `protection_period`, `brokerage_relationship` | `compatibility_preferences` (7-section object), `communication_style`, `preferred_contact_method` | `presentation_link`, `business_card_link`, `website_link`, `social_media`, `intro_video_url`, `promoMaterials` — ~8 keys | `review_1/2/3`, `reviews_links`, `awards_recognition`, `years_experience`, `transactions_last_12_months` | `avg_response_time`, `availability_status`, `evenings_available`, `weekends_available`, `is_full_time` |
| **Listing** | Client identity: `client_name`, `client_phone`, `client_email` | Client-requested compensation (Seller/Buyer only): `purchase_fee_type`, `commission_structure`, `referral_percentage`, and related ~30 keys | Client-selected services: `services`, `other_services`, `client_custom_services` | `service_type`, `auction_type`, `expiration_date`, `workflow_type`, `listing_status` | – | – | – | – |
| **Bid** | Agent identity snapshot: `first_name`, `last_name`, `email`, `phone`, `brokerage`, `license_no`, `nar_id` — 7 keys | Agreed compensation: all ~100 preset compensation keys written at bid submit | Agent-offered services: `services`, `other_services`, `client_custom_services` | `agency_agreement_timeframe`, `protection_period`, `brokerage_relationship`, `additional_details_broker` | `compatibility_agent_response` (7 sections) | `presentation_link`, `business_card_link`, `reviews_links`, `website_link`, `social_media`, `promoMaterials` | `bio`, `why_hire_you`, `what_sets_you_apart`, `marketing_plan` (social proof / pitch) | ⚠️ MISSING — `avg_response_time` not currently stamped |

---

## Hire Me Flow Annotation

This section walks through the `HireAgentDirectController` → `AgentBidMapperService` → listing creation → bid creation sequence, annotating field origins and destinations.

### Flow Steps

```
Client visits GET /hire/{agentShortId}/{role}/{propertyType}
  → resolves agent by short_id
  → redirects to GET /hire/agent/direct/{agentId}/{role}/{propertyType}
  → HireAgentDirectController::show()
```

**Step 1: Preview page rendering (`show()`)**

| Data | Origin | Used For |
|---|---|---|
| `$agent` | Profile (users table) | Display: agent name, avatar |
| `$profile` | Preset (agent_default_profiles) | Exact role+property_type lookup; no fallback |
| `$mapped` | Preset → AgentBidMapperService::mapFromProfile() | Display: compensation terms on preview page |
| `$agentServices` | Preset (profile_data['services']) | Display: services list |
| `$otherServices` | Preset (profile_data['other_services']) | Display: custom services |
| `$submitToken` | System-generated nonce | CSRF-equivalent token for confirmation form |

**Step 2: Client confirms, chooses Accept or Counter intent (`confirm()`)**

| Data | Origin | Persisted To |
|---|---|---|
| `services` | Client POST (intersection with preset list) | Session `hire_direct_pending.services` |
| `other_services` | Client POST (intersection with preset list) | Session `hire_direct_pending.other_services` |
| `client_custom_services` | Client POST (free text) | Session `hire_direct_pending.client_custom_services` |
| `additional_requested` | Client POST | Session `hire_direct_pending.additional_requested` |
| `intent` | Client POST | Session `hire_direct_pending.flow` |

**Step 3: Acknowledgment page + contact form submission (`acknowledge()` + `acknowledgeSubmit()`)**

| Data | Origin | Persisted To |
|---|---|---|
| Client contact fields | Client POST (validated) | Session, then → Listing meta + Bid meta |
| Role-specific client data | Client POST | Session, then → Listing meta (`client_*`) + Bid meta (`counter_*`) |
| `counter_comp` overrides | Counter flow only | Session `hire_direct_pending.counter_comp` |

**Step 4: Database commit (`commitListingAndBid()`)**

```
DB::transaction()
  ├── 1. Create listing record
  │     Origin: System defaults (is_approved=true, is_draft=false)
  │
  ├── 2. Save listing meta
  │     Origin   → Destination
  │     System   → workflow_type, listing_status, service_type, auction_type, expiration_date, hire_me_flow
  │     Preset   → services, other_services, other_services_enabled  [CLIENT-SELECTED SUBSET]
  │     Client   → client_name, client_phone, client_email
  │     Client   → client_property_address/city/state/zip (Seller/Landlord)
  │     Client   → client_areas_of_interest (Buyer/Tenant)
  │     Client   → role-specific fields (desired_sale_price, timeline, etc.)
  │     Client   → client_additional_requested, client_custom_services
  │
  ├── 3. Create bid record
  │     Origin: System (agent.id, listing.id)
  │
  └── 4. Save bid meta
        Origin   → Destination
        Preset   → ALL fields from AgentBidMapperService::mapFromProfile()
                   [~100 keys: overview, credentials, media, compensation]
        Profile  → first_name, last_name, phone, email, brokerage, license_no, nar_id
                   [fallback: if preset value is empty, use users table value]
        Preset   → services, other_services [CLIENT-SELECTED SUBSET]
        System   → hire_me_auto_bid = '1'
        Client   → client_custom_services
        Client   → counter_client_name, counter_client_phone, counter_client_email
        Client   → counter_property_address/city/state/zip (Seller/Landlord)
        Client   → counter_areas_of_interest (Buyer/Tenant)
        Client   → role-specific counter_* fields
```

### Fields Lost in the Flow (Known Gaps)

| Field | Collected? | Persisted? | Gap Description |
|---|---|---|---|
| `avg_response_time` | ✓ Preset | ✗ Bid meta | Not in `AgentBidMapperService::mapFromProfile()` — never stamped onto Hire Me bids or regular bid forms |
| `availability_status` | ✓ Preset | ✗ Bid meta | Same gap |
| `evenings_available` | ✓ Preset | ✗ Bid meta | Same gap |
| `weekends_available` | ✓ Preset | ✗ Bid meta | Same gap |
| `compatibility_preferences` | ✓ Preset | ✗ Hire Me bid meta | `mapCompatibilityFromProfile()` is not called in `commitListingAndBid()`. Compatibility data stamped onto bids submitted via the Livewire form but NOT auto-bids. |
| `years_experience` | ✓ Preset | ✗ Bid meta | Display-only; not considered a commitment field |
| `transactions_last_12_months` | ✓ Preset | ✗ Bid meta | Display-only |
| `review_1/2/3`, `awards_recognition` | ✓ Preset | ✗ Bid meta | Display-only |
| `intro_video_url`, `video_caption` | ✓ Preset | ✗ Bid meta | Not mapped |
| Client's `client_first_name`, `client_last_name` | ✓ Collected | ✗ Split keys | Synthesized into `client_name` (listing) and `counter_client_name` (bid); split first/last not persisted separately |

---

## Proof and Quantitative Summary

| Category | Count | Notes |
|---|---|---|
| Profile-owned fields (users native) | 28 | Including referral and identity columns |
| Profile-owned fields (user_meta EAV) | 27 | |
| **Total Profile-owned** | **55** | |
| Preset-owned fields (profile_data keys) — identity/profile | 41 | Subject to save-scope propagation |
| Preset-owned fields — services | 2 | `services`, `other_services` |
| Preset-owned fields — compensation (shared) | 23 | All roles |
| Preset-owned fields — compensation (Seller-specific) | 26 | |
| Preset-owned fields — compensation (Buyer/Tenant-specific) | 11 | |
| Preset-owned fields — compensation (Landlord-specific) | 37 | |
| Preset-owned fields — compatibility | 1 (object with 7 sections) | |
| **Total Preset-owned** | **~141 distinct keys** | Many compensation keys overlap across roles |
| Listing-owned fields (native columns per role, averaged) | 12 | Varies by role (Seller/Buyer more; Landlord/Tenant minimal) |
| Listing-owned fields (EAV meta keys — system) | 8 | |
| Listing-owned fields (EAV meta keys — client identity) | 7 | Hire Me flow |
| Listing-owned fields (EAV meta keys — client role-specific) | ~6 per role | |
| Listing-owned fields (EAV meta keys — compensation, Seller/Buyer) | ~34 | Only these two roles |
| **Total Listing-owned** | **~75 distinct keys across all roles** | |
| Bid-owned fields (native active) | 5 | user_id, FK to listing, accepted, accepted_date, counter_id (Landlord) |
| Bid-owned fields (deprecated native) | ~17 | Seller/Buyer legacy columns |
| Bid-owned fields (EAV meta — agent overview) | 6 | |
| Bid-owned fields (EAV meta — agent identity) | 7 | |
| Bid-owned fields (EAV meta — media) | 9 | |
| Bid-owned fields (EAV meta — services) | 5 | |
| Bid-owned fields (EAV meta — compensation) | ~100 | Same as Preset compensation keys |
| Bid-owned fields (EAV meta — client contact, Hire Me) | 30 | counter_* keys |
| **Total Bid-owned** | **~165 distinct keys** | |
| **Shared fields (intentional duplication)** | **~110** | Services (3), identity (7), compensation (~100) |
| **Deprecated fields** | **~22** | Legacy native bid columns + superseded user_meta keys |
| **Unjustified duplication count (resolved by this audit)** | **~10 key families** | Profile vs Preset overlap; resolved by "Preset wins" |

---

*End of audit. Questions about this document: trace any field to its source files using the "Relevant files" list in the original task, or grep the codebase for the field key.*
