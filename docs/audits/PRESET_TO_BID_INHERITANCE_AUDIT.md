# Preset → Bid Inheritance Audit

**Purpose:** Reference document for every field saved in an agent preset, whether it flows into bid forms, whether it auto-loads, and whether it is editable on the bid form.

**Date:** 2026-06-16  
**Scope:** All four bid roles — Seller, Buyer, Landlord, Tenant

---

## How Auto-Load Works

All four bid Livewire components (`SellerAgentAuctionBid`, `BuyerAgentAuctionBid`, `LandlordAgentAuctionBid`, `TenantAgentAuctionBid`) call `AgentBidMapperService::findAndMap($userId, $role, $propertyType)` in `mount()`.

If a matching preset exists (with role-default fallback), every key returned by `mapFromProfile()` is assigned to the corresponding component property. `mapCompatibilityFromProfile()` is called separately and its result is merged into `$compatibility_agent_response`. Agents can edit every pre-filled value before submitting.

**Source files:**
- `app/Services/AgentBidMapperService.php` — `mapFromProfile()` maps preset → bid fields; `mapCompatibilityFromProfile()` handles the nested compatibility section
- `app/Http/Controllers/AgentPresetController.php` — `save()` validates and persists all preset fields; `PROFILE_FIELDS` constant lists the "profile-only / safe-to-propagate" keys
- `resources/views/agent-presets/edit.blade.php` — 2,424-line preset editor
- `app/Http/Livewire/Seller/SellerAgentAuctionBid.php`
- `app/Http/Livewire/Buyer/BuyerAgentAuctionBid.php`
- `app/Http/Livewire/Landlord/LandlordAgentAuctionBid.php`
- `app/Http/Livewire/Tenant/TenantAgentAuctionBid.php`

---

## Section 1 — Services

Preset editor section: **Services** (fa-list-ul)

| Field (profile_data key) | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `services` (array) | Yes — all 4 roles | Yes | Yes | Filtered through `filterServicesToCurrentCatalog()` on load |
| `other_services` (array) | Yes — all 4 roles | Yes | Yes | Custom services added by agent |

---

## Section 2 — Agent Overview

Preset editor section: **Agent Overview** (fa-user)

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `bio` | Yes — all 4 roles | Yes | Yes | "About Agent" in bid form |
| `why_hire_you` | Yes — all 4 roles | Yes | Yes | |
| `what_sets_you_apart` | Yes — all 4 roles | Yes | Yes | |
| `marketing_plan` | Yes — all 4 roles | Yes | Yes | |
| `additional_details` | Yes — all 4 roles | Yes | Yes | |

---

## Section 3 — Agent Credentials

Preset editor section: **Agent Credentials** (fa-id-card)

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `first_name` | Yes — all 4 roles | Yes (non-empty guard) | Yes | Applied only when preset value is non-empty |
| `last_name` | Yes — all 4 roles | Yes (non-empty guard) | Yes | |
| `phone` | Yes — all 4 roles | Yes (non-empty guard) | Yes | |
| `email` | Yes — all 4 roles | Yes (non-empty guard) | Yes | |
| `brokerage` | Yes — all 4 roles | Yes (non-empty guard) | Yes | |
| `license_no` | Yes — all 4 roles | Yes (non-empty guard) | Yes | |
| `nar_id` | Yes — all 4 roles | Yes (non-empty guard) | Yes | |
| `year_licensed` | Yes — all 4 roles | Yes | Yes | Bid form label is "Year Licensed" |

---

## Section 4 — Links & Media

Preset editor section: **Links & Media** (fa-link)

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `presentation_link` | Yes — all 4 roles | Yes | Yes | |
| `presentation_upload_path` | Yes — all 4 roles | Yes | Yes | Carried as stored path; new upload replaces it |
| `business_card_link` | Yes — all 4 roles | Yes | Yes | |
| `business_card_stored_path` / `business_card_upload_path` | Yes — all 4 roles | Yes | Yes | Mapper emits both keys; bid components use `$business_card_stored_path` |
| `reviews_links` (array) | Yes — all 4 roles | Yes | Yes | Split from textarea in preset; rendered as repeater in bid |
| `website_link` (array) | Yes — all 4 roles | Yes | Yes | |
| `social_media` (array) | Yes — all 4 roles | Yes | Yes | |
| `promoMaterials` (array) | Yes — all 4 roles | Yes | Yes | Stored as JSON array; bid component uses `$promoMaterials` |

---

## Section 5 — Quick Highlights ⚠️ PROFILE-ONLY

Preset editor section: **Quick Highlights** (fa-star)

These fields appear on the public Hire Me page / agent widget. They are **not** present in any bid form component and are **not** in `mapFromProfile()`.

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `years_experience` | **No** | N/A | N/A | Profile display only |
| `transactions_last_12_months` | **No** | N/A | N/A | Profile display only; stored as integer |
| `avg_response_time` | **No** | N/A | N/A | Profile display only |
| `is_full_time` | **No** | N/A | N/A | Profile display only |
| `primary_areas_served` | **No** | N/A | N/A | Profile display only |

---

## Section 6 — Areas Served ⚠️ PROFILE-ONLY

Preset editor section: **Areas Served** (fa-map-marker)

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `cities_served` | **No** | N/A | N/A | Profile display only |
| `counties_served` | **No** | N/A | N/A | Profile display only |
| `neighborhoods_served` | **No** | N/A | N/A | Profile display only |
| `areas_notes` | **No** | N/A | N/A | Profile display only |

---

## Section 7 — Testimonials / Reviews ⚠️ PROFILE-ONLY

Preset editor section: **Testimonials** (fa-quote-left)

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `review_1` | **No** | N/A | N/A | Profile display only |
| `review_2` | **No** | N/A | N/A | Profile display only |
| `review_3` | **No** | N/A | N/A | Profile display only |
| `awards_recognition` | **No** | N/A | N/A | Profile display only |

---

## Section 8 — Video Introduction ⚠️ PROFILE-ONLY

Preset editor section: **Video Introduction** (fa-play-circle)

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `intro_video_url` | **No** | N/A | N/A | Profile display only; distinct from `$video_upload` which IS a bid-form field (file upload, not preset-sourced) |
| `video_caption` | **No** | N/A | N/A | Profile display only |

---

## Section 9 — Availability ⚠️ PROFILE-ONLY

Preset editor section: **Availability** (fa-calendar)

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `availability_status` | **No** | N/A | N/A | Profile display only |
| `evenings_available` | **No** | N/A | N/A | Profile display only |
| `weekends_available` | **No** | N/A | N/A | Profile display only |
| `communication_style` | **No** | N/A | N/A | Plain-text field; distinct from `compatibility_preferences[communication_preferences]` which IS inherited (see Section 11) |
| `preferred_contact_method` | **No** | N/A | N/A | Profile display only |

---

## Section 10 — Broker Compensation & Agreement Terms

Preset editor section: **Broker Compensation & Agreement Terms** (fa-dollar-sign)

All fields in this section are handled by `mapFromProfile()` and flow to the appropriate bid components based on role.

### Shared (all / most roles)

| Field | Present in Bid? | Auto-load? | Editable? |
|---|---|---|---|
| `protection_period` | Yes | Yes | Yes |
| `early_termination_fee_option` | Yes | Yes | Yes |
| `early_termination_fee_amount` | Yes | Yes | Yes |
| `agency_agreement_timeframe` | Yes | Yes | Yes |
| `agency_agreement_custom` | Yes | Yes | Yes |
| `interested_lease_option_agreement` | Yes | Yes | Yes |
| `lease_type` | Yes | Yes | Yes |
| `lease_value` | Yes | Yes | Yes |
| `purchase_type` | Yes | Yes | Yes |
| `purchase_value` | Yes | Yes | Yes |
| `commission_structure` | Yes | Yes | Yes |
| `purchase_fee_type` | Yes | Yes | Yes |
| `purchase_fee_flat` | Yes | Yes | Yes |
| `purchase_fee_percentage` | Yes | Yes | Yes |
| `purchase_fee_percentage_combo` | Yes | Yes | Yes |
| `purchase_fee_flat_combo` | Yes | Yes | Yes |
| `purchase_fee_other` | Yes | Yes | Yes |
| `retainer_fee_option` | Yes | Yes | Yes |
| `retainer_fee_amount` | Yes | Yes | Yes |
| `retainer_fee_application` | Yes | Yes | Yes |
| `brokerage_relationship` | Yes | Yes | Yes |
| `additional_details_broker` | Yes | Yes | Yes |
| `retained_deposits` | Yes (Seller) | Yes | Yes |
| `referral_fee_percent` | Yes | Yes | Yes |

### Buyer / Tenant — Lease Fee

| Field | Present in Bid? | Auto-load? | Editable? |
|---|---|---|---|
| `interested_lease_option` | Yes | Yes | Yes |
| `lease_fee_type` | Yes | Yes | Yes |
| `lease_fee_flat` | Yes | Yes | Yes |
| `lease_fee_percentage` | Yes | Yes | Yes |
| `lease_fee_percentage_monthly_rent` | Yes | Yes | Yes |
| `lease_fee_percentage_monthly_number` | Yes | Yes | Yes |
| `lease_fee_flat_combo` | Yes | Yes | Yes |
| `lease_fee_percentage_combo` | Yes | Yes | Yes |
| `lease_fee_percentage_net` | Yes | Yes | Yes |
| `lease_fee_flat_combo_net` | Yes | Yes | Yes |
| `lease_fee_percentage_combo_net` | Yes | Yes | Yes |
| `lease_fee_other` | Yes | Yes | Yes |

### Seller-specific

| Field | Present in Bid? | Auto-load? | Editable? |
|---|---|---|---|
| `nominal` | Yes | Yes | Yes |
| `commission_structure_type` | Yes | Yes | Yes |
| `commission_structure_type_fee_flat` | Yes | Yes | Yes |
| `commission_structure_type_fee_flat_combo` | Yes | Yes | Yes |
| `commission_structure_type_fee_percentage` | Yes | Yes | Yes |
| `commission_structure_type_fee_percentage_combo` | Yes | Yes | Yes |
| `commission_structure_type_fee_other` | Yes | Yes | Yes |
| `interested_purchase_fee_type` | Yes | Yes | Yes |
| `seller_leasing_fee_type` | Yes | Yes | Yes |
| `seller_leasing_gross` | Yes | Yes | Yes |
| `seller_leasing_gross_rental` | Yes | Yes | Yes |
| `seller_leasing_gross_month_rent` | Yes | Yes | Yes |
| `sales_tax_option_gross` | Yes | Yes | Yes |
| `seller_leasing_gross_other` | Yes | Yes | Yes |
| `seller_leasing_gross_percentage` | Yes | Yes | Yes |
| `seller_leasing_gross_purchase_fee_flat_amount` | Yes | Yes | Yes |
| `seller_leasing_gross_purchase_fee_other` | Yes | Yes | Yes |
| `seller_leasing_each_rental` | Yes | Yes | Yes |
| `seller_leasing_gross_no_of_months` | Yes | Yes | Yes |
| `seller_leasing_gross_flat_combo` | Yes | Yes | Yes |
| `seller_leasing_gross_percentage_combo` | Yes | Yes | Yes |
| `seller_leasing_gross_flat_net_combo` | Yes | Yes | Yes |
| `seller_leasing_gross_percentage_net_combo` | Yes | Yes | Yes |
| `seller_leasing_gross_sales_tax_first_month` | Yes | Yes | Yes |
| `seller_leasing_gross_sales_tax_option_gross` | Yes | Yes | Yes |
| `seller_leasing_gross_sales_tax_flat_free_gross` | Yes | Yes | Yes |

### Landlord — Residential Lease Fee

| Field | Present in Bid? | Auto-load? | Editable? |
|---|---|---|---|
| `purchase_fee_rental_period` | Yes | Yes | Yes |

### Landlord — Commercial Lease Fee

| Field | Present in Bid? | Auto-load? | Editable? |
|---|---|---|---|
| `purchase_fee_net_aggregate` | Yes | Yes | Yes |
| `purchase_fee_gross_rent` | Yes | Yes | Yes |
| `purchase_fee_monthly_percentage` | Yes | Yes | Yes |
| `purchase_fee_months` | Yes | Yes | Yes |
| `sales_tax_option_monthly` | Yes | Yes | Yes |
| `purchase_fee_flat_commercial` | Yes | Yes | Yes |
| `sales_tax_option_flat` | Yes | Yes | Yes |
| `purchase_fee_other_commercial` | Yes | Yes | Yes |
| `purchase_fee_purchase_price` | Yes | Yes | Yes |
| `expansion_commission_percentage` | Yes | Yes | Yes |

### Landlord — Tenant Broker Commission (Residential)

| Field | Present in Bid? | Auto-load? | Editable? |
|---|---|---|---|
| `tenant_broker_commission_structure` | Yes | Yes | Yes |
| `tenant_broker_fee_structure` | Yes | Yes | Yes |
| `tenant_broker_percentage` | Yes | Yes | Yes |
| `tenant_broker_gross_lease` | Yes | Yes | Yes |
| `tenant_broker_first_month_rent` | Yes | Yes | Yes |
| `tenant_broker_flat_fee` | Yes | Yes | Yes |
| `tenant_broker_other` | Yes | Yes | Yes |

### Landlord + Tenant — Broker Fee Timing

| Field | Present in Bid? | Auto-load? | Editable? |
|---|---|---|---|
| `broker_fee_timing` | Yes | Yes | Yes |
| `broker_fee_days_from_rent` | Yes | Yes | Yes |
| `broker_fee_days_after_lease` | Yes | Yes | Yes |
| `broker_fee_days_after_rent` | Yes | Yes | Yes |
| `broker_fee_timing_other` | Yes | Yes | Yes |

### Landlord — Split Payment Due

| Field | Present in Bid? | Auto-load? | Editable? |
|---|---|---|---|
| `split_payment_due` | Yes | Yes | Yes |
| `split_payment_due_other` | Yes | Yes | Yes |
| `broker_fee_days_after_due_event` | Yes | Yes | Yes |

### Landlord — Lease Renewal / Extension Fee

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `renewal_fee_type` | Yes | Yes | Yes | |
| `renewal_fee_percentage` | Yes | Yes | Yes | |
| `renewal_fee_lease_value` | Yes | Yes | Yes | |
| `renewal_fee_first_month` | Yes | Yes | Yes | |
| `renewal_fee_flat_fee` | Yes | Yes | Yes | Legacy key `renewal_fee_flat_free` also read as fallback |
| `renewal_fee_custom` | Yes | Yes | Yes | |
| `renewal_fee_sales_tax_lease_value` | Yes | Yes | Yes | |
| `renewal_fee_no_of_months` | Yes | Yes | Yes | |
| `renewal_fee_sales_tax_first_month` | Yes | Yes | Yes | |
| `renewal_fee_sales_tax_flat_fee` | Yes | Yes | Yes | |

### Landlord — Property Management

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `interested_in_property_management` | Yes | Yes | Yes | |
| `interested_in_property_management_fee` | Yes | Yes | Yes | |
| `interested_in_property_management_fee_gross_lease` | Yes | Yes | Yes | |
| `interested_in_property_management_fee_rental_periord` | Yes | Yes | Yes | Intentional typo in DB column name |
| `interested_in_property_management_fee_flate_free` | Yes | Yes | Yes | Intentional typo in DB column name |
| `interested_in_property_management_fee_other` | Yes | Yes | Yes | |

### Landlord — Interested in Selling

| Field | Present in Bid? | Auto-load? | Editable? | Notes |
|---|---|---|---|---|
| `interested_in_selling` | Yes | Yes | Yes | |
| `interested_in_selling_type` | Yes | Yes | Yes | |
| `landlord_broker_purchase_price` | Yes | Yes | Yes | |
| `landlord_broker_percentage_price` | Yes | Yes | Yes | |
| `landlord_broker_dollar_price` | Yes | Yes | Yes | |
| `landlord_broker_flate_fee` | Yes | Yes | Yes | Intentional typo in DB column name |
| `landlord_broker_other` | Yes | Yes | Yes | |

---

## Section 11 — Working Style & Compatibility

Preset editor section: **Working Style & Compatibility** (fa-handshake-simple)

These fields are stored as a nested `compatibility_preferences` key in `profile_data`. They are extracted by `AgentBidMapperService::mapCompatibilityFromProfile()` and merged into `$compatibility_agent_response` on all four bid components during `mount()`. They are saved to the bid record via `$bid->saveCompatibilityPreferences()` on submission. They are editable in the "Working Style & Compatibility" tab of the bid form.

> **Important distinction:** `communication_style` (a plain-text field in Section 9 / Availability) is NOT the same as `compatibility_preferences[communication_preferences]`. Only the latter flows into bids.

| Sub-section | Fields | Present in Bid? | Auto-load? | Editable? |
|---|---|---|---|---|
| `communication_preferences` | `agent_communication_channels` (multi-select), `agent_communication_frequency`, `agent_response_time_commitment`, `agent_communication_notes`, `agent_availability_notes` | Yes — all 4 roles | Yes | Yes |
| `negotiation_approach` | `agent_negotiation_style`, `agent_negotiation_notes` | Yes — all 4 roles | Yes | Yes |
| `guidance_style` | `agent_guidance_level`, `agent_guidance_notes` | Yes — all 4 roles | Yes | Yes |
| `collaboration_preferences` | `agent_collaboration_style`, `agent_availability_windows` | Yes — all 4 roles | Yes | Yes |
| `transaction_strategy` | `agent_transaction_pace`, `agent_strategy_experience` (multi-select), `agent_strategy_notes` | Yes — all 4 roles | Yes | Yes |
| `representation_philosophy` | `agent_decision_support_style`, `agent_risk_posture`, `agent_representation_philosophy` (multi-select), `agent_philosophy_narrative`, `agent_philosophy_notes` | Yes — all 4 roles | Yes | Yes |
| `representation_priorities` | `agent_representation_priorities` (multi-select) | Yes — all 4 roles | Yes | Yes |

---

## Summary

### Bid-Inherited Fields

These fields are saved in the preset and automatically loaded into the bid form on `mount()`. Agents can edit all of them before submitting.

| Category | Fields |
|---|---|
| Services | `services[]`, `other_services[]` |
| Agent Overview | `bio`, `why_hire_you`, `what_sets_you_apart`, `marketing_plan`, `additional_details` |
| Agent Credentials | `first_name`, `last_name`, `phone`, `email`, `brokerage`, `license_no`, `nar_id`, `year_licensed` |
| Links & Media | `presentation_link`, `presentation_upload_path`, `business_card_link`, `business_card_stored_path`, `reviews_links`, `website_link`, `social_media`, `promoMaterials` |
| Broker Compensation & Agreement Terms | ~100 fields covering all role-specific compensation and agreement terms (see Section 10) |
| Working Style & Compatibility | All 7 sub-sections of `compatibility_preferences` (via `mapCompatibilityFromProfile`) |

### Profile-Only Fields

These fields are saved in the preset and shown on the public Hire Me page / agent widget, but are **not** sent to bid forms.

| Preset Section | Fields |
|---|---|
| Quick Highlights | `years_experience`, `transactions_last_12_months`, `avg_response_time`, `is_full_time`, `primary_areas_served` |
| Areas Served | `cities_served`, `counties_served`, `neighborhoods_served`, `areas_notes` |
| Testimonials | `review_1`, `review_2`, `review_3`, `awards_recognition` |
| Video Introduction | `intro_video_url`, `video_caption` |
| Availability | `availability_status`, `evenings_available`, `weekends_available`, `communication_style`, `preferred_contact_method` |

---

## Key Distinctions

| Field | Present in Bid? | Auto-load? | Editable? | Details |
|---|---|---|---|---|
| About Agent (`bio`) | Yes | Yes | Yes | All 4 roles |
| Negotiation Style (`negotiation_approach`) | Yes | Yes | Yes | Lives in `compatibility_preferences`; stored in `$compatibility_agent_response` on bid |
| Communication Style — compatibility (`communication_preferences`) | Yes | Yes | Yes | The nested compatibility section flows to bids |
| Communication Style — plain text (`communication_style` field) | **No** | N/A | N/A | The flat Availability-section field does NOT flow to bids |
| Testimonials (`review_1`/`review_2`/`review_3`, `awards_recognition`) | **No** | N/A | N/A | Profile-only; shown on Hire Me page only |
| Video Intro (`intro_video_url`, `video_caption`) | **No** | N/A | N/A | Profile-only; `$video_upload` on bids is a separate file-upload field, not preset-sourced |
