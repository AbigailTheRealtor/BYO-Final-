# Agent Preset Edit Form – Field Audit (Task #366)

**Scope:** Every field on `/agent/presets/{role}/{propertyType}/edit` across all roles
(buyer, seller, landlord, tenant) and all property types (residential, income, commercial,
business, vacant_land).

**Method:** Code audit of controller, model, blade template, and direct database
verification via `artisan tinker` + e2e tests.

---

## Root Causes Found & Fixed

### Bug 1 — `@selected` Blade directive not compiled (Laravel 8 compat) — **ALL SELECTS**

**File:** `app/Providers/AppServiceProvider.php`

`@selected()` and `@checked()` are **Laravel 9** Blade directives. This app runs
**Laravel 8.83.29**. Every `@selected()` call in the blade was emitted literally into
HTML (e.g., `<option value="Yes" @selected(true)>Yes</option>`). Browsers ignore
unrecognised attributes, so no select ever reloaded its saved value.

**69 `@selected()` occurrences** in `edit.blade.php` were all broken.

**Fix:** Registered `@selected` and `@checked` as custom Blade directives in
`AppServiceProvider::boot()`:

```php
Blade::directive('selected', function ($expression) {
    return "<?php echo ($expression) ? 'selected' : ''; ?>";
});
Blade::directive('checked', function ($expression) {
    return "<?php echo ($expression) ? 'checked' : ''; ?>";
});
```

**Verified:** e2e test confirmed all select fields reload correctly after fix.

---

### Bug 2 — `transactions_last_12_months` stores 0 instead of null when blank

**File:** `app/Http/Controllers/AgentPresetController.php`

```php
// Before (broken): '' !== null is true → (int)'' = 0 stored
'transactions_last_12_months' => $request->input('transactions_last_12_months') !== null
    ? (int) $request->input('transactions_last_12_months')
    : null,

// After (fixed): filled() returns false for '' and null
'transactions_last_12_months' => $request->filled('transactions_last_12_months')
    ? (int) $request->input('transactions_last_12_months')
    : null,
```

---

## Field-by-Field Audit Table

Legend: ✅ Correct | 🐛 Bug (fixed) | 🗑️ Legacy (not in form)

### Section: Services

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Services checkboxes | `services[]` | `services` | `services` | `@checked(in_array(...))` | ✅ |
| Other/Custom services | `other_services[]` | `other_services` | `other_services` | `value=` on dynamic rows | ✅ |

### Section: Referral Fee

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Willing to Pay Referral Fee? | `referral_fee_willing` | `referral_fee_willing` | `referral_fee_willing` | `@selected(... === $optVal)` | ✅ (post-fix) |
| Referral Fee % | `referral_fee_percent` | `referral_fee_percent` | `referral_fee_percentage` | `value=` | ✅ |
| Referral Fee Notes | `referral_fee_notes` | `referral_fee_notes` | `referral_fee_notes` | textarea | ✅ |

### Section: Agent Overview

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Bio | `bio` | `bio` | `bio` | textarea | ✅ |
| Why Hire You | `why_hire_you` | `why_hire_you` | `why_hire_you` | textarea | ✅ |
| What Sets You Apart | `what_sets_you_apart` | `what_sets_you_apart` | `what_sets_you_apart` | textarea | ✅ |
| Marketing Plan | `marketing_plan` | `marketing_plan` | `marketing_plan` | textarea | ✅ |
| Additional Details | `additional_details` | `additional_details` | `additional_details` | textarea | ✅ |

### Section: Agent Credentials

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| First Name | `first_name` | `first_name` | `first_name` | `value=` | ✅ |
| Last Name | `last_name` | `last_name` | `last_name` | `value=` | ✅ |
| Email | `email` | `email` | `email` | `value=` | ✅ |
| Phone | `phone` | `phone` | `phone` | `value=` | ✅ |
| Brokerage | `brokerage` | `brokerage` | `brokerage` | `value=` | ✅ |
| License No. | `license_no` | `license_no` | `license_no` | `value=` | ✅ |
| NAR ID | `nar_id` | `nar_id` | `nar_id` | `value=` | ✅ |
| Year Licensed | `year_licensed` | `year_licensed` | `year_licensed` | `value=` | ✅ |

### Section: Presentation & Links

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Presentation Link | `presentation_link` | `presentation_link` | `presentation_link` | `value=` | ✅ |
| Presentation Upload | `presentation_upload` | `presentation_upload` | `presentation_upload_path` | path shown separately | ✅ |
| Business Card Link | `business_card_link` | `business_card_link` | `business_card_link` | `value=` | ✅ |
| Business Card Upload | `business_card_upload` | `business_card_upload` | `business_card_upload_path` | path shown separately | ✅ |
| Website / Profile Links | `website_link_raw` | `website_link_raw` | `website_link` (array) | `implode("\n", ...)` in textarea | ✅ |
| Social Media Links | `social_media_raw` | `social_media_raw` | `social_media` (array) | `implode("\n", ...)` in textarea | ✅ |
| Reviews Links | `reviews_links_raw` | `reviews_links_raw` | `reviews_links` (array) | `implode("\n", ...)` in textarea | ✅ |
| Nominal / Display Name | `nominal` | `nominal` | `nominal` | `value=` | ✅ |

### Section: Quick Highlights

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Years of Experience | `years_experience` | `years_experience` | `years_experience` | `value=` | ✅ |
| Transactions (Last 12 Mo.) | `transactions_last_12_months` | `transactions_last_12_months` | `transactions_last_12_months` | `value=` | 🐛 Fixed (Bug 2) |
| Avg. Response Time | `avg_response_time` | `avg_response_time` | `avg_response_time` | `value=` | ✅ |
| Full-Time Agent? | `is_full_time` | `is_full_time` | `is_full_time` | `@selected(... === 'Yes/No')` | 🐛 Fixed (Bug 1) |
| Primary Areas Served | `primary_areas_served` | `primary_areas_served` | `primary_areas_served` | `value=` | ✅ |

### Section: Areas Served

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Cities Served | `cities_served` | `cities_served` | `cities_served` | `value=` | ✅ |
| Counties Served | `counties_served` | `counties_served` | `counties_served` | `value=` | ✅ |
| Neighborhoods Served | `neighborhoods_served` | `neighborhoods_served` | `neighborhoods_served` | `value=` | ✅ |
| Areas Notes | `areas_notes` | `areas_notes` | `areas_notes` | textarea | ✅ |

### Section: Social Proof

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Client Review 1 | `review_1` | `review_1` | `review_1` | textarea | ✅ |
| Client Review 2 | `review_2` | `review_2` | `review_2` | textarea | ✅ |
| Client Review 3 | `review_3` | `review_3` | `review_3` | textarea | ✅ |
| Awards & Recognition | `awards_recognition` | `awards_recognition` | `awards_recognition` | textarea | ✅ |

### Section: Video Intro

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Intro Video URL | `intro_video_url` | `intro_video_url` | `intro_video_url` | `value=` | ✅ |
| Video Caption | `video_caption` | `video_caption` | `video_caption` | `value=` | ✅ |

### Section: Availability & Service Style

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Availability Status | `availability_status` | `availability_status` | `availability_status` | `@selected(... === $optVal)` | 🐛 Fixed (Bug 1) |
| Evenings Available? | `evenings_available` | `evenings_available` | `evenings_available` | `@selected(... === 'Yes/No')` | 🐛 Fixed (Bug 1) |
| Weekends Available? | `weekends_available` | `weekends_available` | `weekends_available` | `@selected(... === 'Yes/No')` | 🐛 Fixed (Bug 1) |
| Communication Style | `communication_style` | `communication_style` | `communication_style` | `value=` | ✅ |
| Preferred Contact Method | `preferred_contact_method` | `preferred_contact_method` | `preferred_contact_method` | `@selected(... === $optVal)` | 🐛 Fixed (Bug 1) |

### Section: Broker Compensation — Buyer (all property types)

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Commission Structure | `commission_structure` | `commission_structure` | `commission_structure` | `@selected($curCommStr === $optVal)` | 🐛 Fixed (Bug 1) |
| Purchase Fee Type | `purchase_fee_type` | `purchase_fee_type` | `purchase_fee_type` | `@selected($curPurchFee === $optVal)` | 🐛 Fixed (Bug 1) |
| Purchase Fee Flat | `purchase_fee_flat` | `purchase_fee_flat` | `purchase_fee_flat` | `value=` | ✅ |
| Purchase Fee % | `purchase_fee_percentage` | `purchase_fee_percentage` | `purchase_fee_percentage` | `value=` | ✅ |
| Purchase Fee % + Flat (combo %) | `purchase_fee_percentage_combo` | same | same | `value=` | ✅ |
| Purchase Fee % + Flat (combo $) | `purchase_fee_flat_combo` | same | same | `value=` | ✅ |
| Purchase Fee Other | `purchase_fee_other` | same | same | `value=` | ✅ |
| Interested in Lease Option | `interested_lease_option` | same | same | `@selected(... === 'Yes/No')` | 🐛 Fixed (Bug 1) |
| Lease Fee Type | `lease_fee_type` | same | same | `@selected($curLeaseFee === $optVal)` | 🐛 Fixed (Bug 1) |
| Lease Fee Flat | `lease_fee_flat` | same | same | `value=` | ✅ |
| Lease Fee % (monthly rent) | `lease_fee_percentage_monthly_rent` | same | same | `value=` | ✅ |
| Lease Fee % (gross lease) | `lease_fee_percentage` | same | same | `value=` | ✅ |
| Lease Fee Flat + % (combo $) | `lease_fee_flat_combo` | same | same | `value=` | ✅ |
| Lease Fee Flat + % (combo %) | `lease_fee_percentage_combo` | same | same | `value=` | ✅ |
| Acceptable Brokerage Relationship | `brokerage_relationship` | same | same | `@selected($curBrokRelat === $optVal)` | 🐛 Fixed (Bug 1) |
| Agency Agreement Timeframe | `agency_agreement_timeframe` | same | same | `@selected($aatCur === $optVal)` | 🐛 Fixed (Bug 1) |
| Agency Agreement Custom | `agency_agreement_custom` | same | same | `value=` (shown when 'custom' selected) | ✅ |
| Agency Agreement Notes (Additional Terms) | `additional_details_broker` | same | same | textarea | ✅ |

### Section: Broker Compensation — Seller (all property types)

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Commission Structure | `commission_structure` | same | same | `@selected($curSellerCommStr === $optVal)` | 🐛 Fixed (Bug 1) |
| Listing Fee Type | `listing_fee_type` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Listing Fee Flat | `listing_fee_flat` | same | same | `value=` | ✅ |
| Listing Fee % | `listing_fee_percentage` | same | same | `value=` | ✅ |
| Buyer's Agent Commission Offered | `buyers_agent_commission_offered` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Buyer Agent Fee Type | `buyer_agent_fee_type` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Buyer Agent Fee Flat | `buyer_agent_fee_flat` | same | same | `value=` | ✅ |
| Buyer Agent Fee % | `buyer_agent_fee_percentage` | same | same | `value=` | ✅ |
| Brokerage Relationship | `brokerage_relationship` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Listing Agreement Timeframe | `listing_agreement_timeframe` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Listing Agreement Other | `listing_agreement_other` | same | same | `value=` | ✅ |
| Additional Terms | `additional_details_broker` | same | same | textarea | ✅ |

### Section: Broker Compensation — Landlord (all property types)

| Field | HTML `name` | Request key | Stored key | Blade reload | Status |
|---|---|---|---|---|---|
| Commission Structure | `tenant_broker_commission_structure` | same | same | `@selected($curTbCommStr === $optVal)` | 🐛 Fixed (Bug 1) |
| Tenant Broker Fee Structure | `tenant_broker_fee_structure` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Fee % | `tenant_broker_percentage` | same | same | `value=` | ✅ |
| Fee (Gross Lease %) | `tenant_broker_gross_lease` | same | same | `value=` | ✅ |
| Fee (First Month Rent) | `tenant_broker_first_month_rent` | same | same | `value=` | ✅ |
| Fee (Flat $) | `tenant_broker_flat_fee` | same | same | `value=` | ✅ |
| Fee Other | `tenant_broker_other` | same | same | `value=` | ✅ |
| Broker Fee Timing | `broker_fee_timing` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Broker Fee Timing Other | `broker_fee_timing_other` | same | same | `value=` | ✅ |
| Early Termination Fee | `early_termination_fee_option` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Early Termination Fee Amount | `early_termination_fee_amount` | same | same | `value=` | ✅ |
| Renewal Fee Type | `renewal_fee_type` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Security Deposit Policy | `security_deposit_policy` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Retainer Fee | `retainer_fee_option` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Retainer Fee Amount | `retainer_fee_amount` | same | same | `value=` | ✅ |
| Retainer Applied To | `retainer_fee_application` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Brokerage Relationship | `brokerage_relationship` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Agency Agreement Timeframe | `agency_agreement_timeframe` | same | same | `@selected(...)` | 🐛 Fixed (Bug 1) |
| Agency Agreement Custom | `agency_agreement_custom` | same | same | `value=` | ✅ |
| Additional Terms | `additional_details_broker` | same | same | textarea | ✅ |

### Section: Broker Compensation — Tenant (all property types)

Same fields as Landlord, sharing the same blade partial. All `@selected` bindings
fixed by Bug 1 polyfill. Role-specific notes:

- `early_termination_fee_option`: stores `'Yes'`/`'No'` (capitalized) — blade
  comparison uses `=== 'Yes'`/`=== 'No'` — correct.
- `retainer_fee_option`: same capitalized 'Yes'/'No' convention.
- `broker_fee_timing` Other value for tenant commercial: `'other'` (lowercase) —
  blade `data-cp-values="other"` matches stored value — correct.

### Sticky Save Bar

| Field | HTML `name` | Saves? | Notes |
|---|---|---|---|
| Save Scope | `profile_save_scope` | ✅ | Does not persist to DB (scopes the save operation) |

---

## Scope Propagation Audit

### `current_preset` (default)
- Saves only to the specific `role_type` + `property_type` record.
- No propagation to other presets.
- **Status:** ✅ Correct.

### `current_role`
- Propagates ALL submitted form fields (using HTTP request key presence) to every
  other property-type preset for the same role.
- File-upload paths (`presentation_upload_path`, `business_card_upload_path`) are
  excluded — each preset manages its own files independently.
- Missing property-type presets are **created** during propagation (upsert).
- Cross-role isolation enforced: only `role_type === $role` records are touched.
- **Status:** ✅ Correct.

### `all_roles`
- Propagates only `PROFILE_FIELDS` (safe public-profile keys: bio, credentials,
  links, highlights, areas, social proof, video, availability) to all existing presets.
- Compensation, services, and agreement-term fields are intentionally excluded.
- Only updates **existing** presets (does not create new ones across roles).
- **Status:** ✅ Correct by design.

---

## Legacy Controller Fields (Not in Blade)

These appear in the controller's mapping but have no blade inputs. They always save as
`''`. Not a bug — orphaned from previous form revisions.

| Field | Status |
|---|---|
| `purchase_fee_purchase_price` | 🗑️ Legacy |
| `seller_leasing_each_rental` | 🗑️ Legacy |
| `commission_structure_type_fee_flat_combo` | 🗑️ Legacy |
| `commission_structure_type_fee_percentage_combo` | 🗑️ Legacy |

---

## Test Evidence

**e2e test (buyer/residential):** All 9 select/input fields confirmed reloading:
- `is_full_time` → "Yes" ✅
- `availability_status` → "Actively Taking New Clients" ✅
- `evenings_available` → "Yes" ✅
- `weekends_available` → "No" ✅
- `communication_style` → "Proactive communicator" ✅
- `preferred_contact_method` → "Text Message" ✅
- `commission_structure` → "Buyer Pays Out-of-Pocket" ✅
- `purchase_fee_type` → "Flat Fee" ✅
- `purchase_fee_flat` → "5000" ✅

**e2e test (seller/residential):** Cross-role reload confirmed:
- `availability_status` → "Actively Taking New Clients" ✅
- `is_full_time` → "No" ✅
- `commission_structure` → first valid option reloaded ✅
- `evenings_available` → "No" ✅

**e2e test (landlord/residential):** Cross-role reload confirmed:
- `availability_status` → "Actively Taking New Clients" ✅
- `weekends_available` → "Yes" ✅
- `is_full_time` → "Yes" ✅

**Full 14-combination matrix (DB save + blade condition):**

| Role | Property Type | Save | Reload @selected |
|---|---|---|---|
| buyer | residential | PASS | SELECTED ✅ |
| buyer | income | PASS | SELECTED ✅ |
| buyer | commercial | PASS | SELECTED ✅ |
| buyer | business | PASS | SELECTED ✅ |
| buyer | vacant_land | PASS | SELECTED ✅ |
| seller | residential | PASS | SELECTED ✅ |
| seller | income | PASS | SELECTED ✅ |
| seller | commercial | PASS | SELECTED ✅ |
| seller | business | PASS | SELECTED ✅ |
| seller | vacant_land | PASS | SELECTED ✅ |
| landlord | residential | PASS | SELECTED ✅ |
| landlord | commercial | PASS | SELECTED ✅ |
| tenant | residential | PASS | SELECTED ✅ |
| tenant | commercial | PASS | SELECTED ✅ |

**Deployment note:** `scripts/post-merge.sh` already includes `php artisan view:clear`
(line 8), ensuring compiled views are refreshed after every merge so the new `@selected`
directive is always active.
