# Seller Offer Listing — Full-Service Create Flow
# Phase 1 Audit Report (Commission-Based Path Only)
**Date:** 2026-05-04  
**Scope:** Read-only. No code changes made.  
**Path audited:** `service_type = full_service`, `user_type = seller`

---

## Section 1 — Files Involved

### Primary wrapper
| File | Lines | Role |
|---|---|---|
| `resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php` | 3,336 | Master wrapper: tab nav, all JS/Select2 init, wizard handlers, form submit |
| `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` | 3,634 | PHP Livewire component: all public properties, `store()`, `setActiveTab()`, validation |
| `app/Http/Livewire/OfferListing/Seller/SellerOfferListingEdit.php` | — | Edit-mode variant (not audited in depth; mirrors create component) |

### Commission-based tab partials (all under `offer-seller-tabs/commission-based/`)
| Partial | Tab Label | Approx. Lines |
|---|---|---|
| `listing-details.blade.php` | Listing Details (tab 0) | ~400 |
| `property-preferences.blade.php` | Property Details (tab 1) | 2,975 |
| `financial-details.blade.php` | Financial Details (tab 2, conditional) | ~600 |
| `seller-terms.blade.php` | Sale Terms | 2,061 |
| `additional-details.blade.php` | Additional Details | ~350 |
| `tax-legal-hoa-disclosures.blade.php` | Tax, Legal, HOA & Disclosures | 1,032 |
| `photos-tours-documents.blade.php` | Photos & Tours | ~300 |
| `seller-info.blade.php` | Seller Info | ~200 |
| `services.blade.php` | Services | ~500 |
| `broker-compensation.blade.php` | Broker Compensation | 844 |

### Tab index map (full_service, seller user type)
```
0  Listing Details
1  Property Details
2  Financial Details  ← only if property_type ∈ {Income, Commercial, Business}
saleTermsIdx (2 or 3)  Sale Terms
additionalDetailsIdx    Additional Details
taxLegalIdx             Tax, Legal, HOA & Disclosures
photosIdx               Photos & Tours
sellerInfoIdx           Seller Info
aiIdx                   AI FAQ  (last tab)
```
The variable indices (`$saleTermsIdx`, etc.) are computed in a `@php` block in `offer-seller-listing.blade.php` lines 840–849. There is no dedicated "Services" or "Broker Compensation" tab in the tab nav strip — these are included inside the Sale Terms or Seller Info tab panes (to be confirmed), or they may be separate `<div class="tab-pane">` targets that are not listed in the `<ul class="nav nav-tabs">`. The tab-pane IDs `#services` and `#broker-compensation` appear in form validation logic (lines 2454, 2584) confirming they exist as panes even if not explicitly in the audited nav strip section.

---

## Section 2 — Broken or Fragile "Other" Custom Inputs

There are four distinct patterns used for "Other" conditional input reveal. Two are solid; two have fragility risks.

### Pattern A — JS-only toggle on `wire:ignore` Select2 (correct pattern)
Fields handled cleanly: `#appliances` → `#other_appliances`, `#view_preference` → `#other_preferences`, `#included_assets` → `.other_assets`, `#garage_parking_spaces_option_landlord` → `#other_garage_parking_spaces_option_landlord`.

All four have:
- A `message.processed` hook that re-applies visibility after every Livewire re-render.
- The initial inline `style` attribute is set from PHP at first render.
- The JS `change` handler calls `@this.set(prop, values, false)` (no re-render flag) and immediately toggles the DOM.

**Risk:** The `other_appliances` div's initial `style` is controlled by the PHP property `$showOtherAppliances` (line 1010), not by checking `$appliances` directly. `$showOtherAppliances` is a separate boolean property (declared at PHP line 204). If it gets out of sync with the actual `$appliances` array on a draft reload or page refresh, the initial render could show/hide incorrectly. The `message.processed` hook corrects it after each re-render but there is a 1-frame flash on load.

### Pattern B — Blade `style` initial + JS toggle (MLS multi-select fields, correct)
All MLS multi-select "Other" wrappers in `property-preferences.blade.php` (lines 1848–2348): `roof_type`, `exterior_construction`, `foundation`, `heating_and_fuel`, `air_conditioning`, `road_frontage`, `road_surface_type`, `utilities`, `water`, `sewer`, `electrical_service`, `building_features`, `licenses`.

These use: `style="{{ is_array($field) && in_array('Other', $field) ? '' : 'display:none;' }}"` for initial render, and `initializeMlsPropertyMultiSelects()` for JS change handling. This is the correct pattern.

### Pattern C — Livewire eager `wire:model` + Blade `@if` re-render (works but causes round-trips)
Fields: `association_type Other` input, `association_fee_frequency Other` input (in `tax-legal-hoa-disclosures.blade.php`), `seller_amortization_type Other` (seller-terms line 1643), `seller_payment_frequency Other` (line 1674), `initial_deposit_timeframe Other` (line 1752), `additional_deposit_timeframe Other` (line 1803), `agency_agreement_timeframe Other` (broker-compensation line 760), `carport_needed Yes` → `other-carport-needed` (property-preferences line 1059), `garage_needed Yes` → `other-garage-needed` (line 1093).

These all use `wire:model` (eager, not `.defer`) on the parent select, which triggers a Livewire server round-trip on every change. The `@if` conditional then re-renders. This is **functionally correct** but causes a noticeable UI flash/delay (~200–400 ms round-trip) on every selection change. Worse, the `carport_needed` and `garage_needed` selects also have a redundant JS handler (`toggleSpaceInput`) that manipulates `d-none` classes — this dual mechanism can race with the Livewire re-render and produce brief layout flickers.

### Pattern D — Livewire eager + JS toggle (fragile double-bind)
`other_non_negotiable_amenities` uses a Blade `@if (!in_array('Other', ...)) d-none @endif` class at initial render (line 1310) AND a JS `toggleOtherAmenities()` function that toggles `d-none` on the Select2 `change` event. The JS is attached to the raw `<select>` element (`#non_negotiable_amenities`) which is inside a `wire:ignore` block — so `@this.set` is called but the Blade-rendered class can be re-applied after the next Livewire re-render, overwriting what JS set. If `$non_negotiable_amenities` does not yet contain "Other" in PHP state (e.g., the `false` flag was used in `@this.set`), the re-render will add `d-none` back even though "Other" is visually selected.

**Finding D is a confirmed fragile race condition.**

### Summary of "Other" input issues
| Field | Pattern | Risk Level |
|---|---|---|
| Appliances → `other_appliances` | A + `$showOtherAppliances` bool | Low (mitigated by hook) |
| View → `other_preferences` | A | None |
| Assets → `other_assets` | A | None |
| Garage/Parking → other input | A | None |
| Amenities → `other_non_negotiable_amenities` | D (double-bind) | **Medium — race condition** |
| `association_type` Other | C | Low (round-trip flash) |
| `association_fee_frequency` Other | C | Low |
| `seller_amortization_type` Other | C | Low |
| `seller_payment_frequency` Other | C | Low |
| `initial_deposit_timeframe` Other | C | Low |
| `additional_deposit_timeframe` Other | C | Low |
| `agency_agreement_timeframe` Other | C | Low |
| `carport_needed` / `garage_needed` | C + redundant JS | Low-medium (flicker) |
| All MLS "Other" wrappers | B | None |

---

## Section 3 — Input Sizing Inconsistencies

### 3.1 Textarea inline styles vs. standard form-control
The following `<textarea>` elements in `tax-legal-hoa-disclosures.blade.php` use bare inline styles instead of utility classes:

```
additional_parcel_ids   rows="3"  style="padding: 10px; font-size: 16px;"   line 104
legal_description       rows="3"  style="padding: 10px; font-size: 16px;"   line 118
tax_year                (input)   style="padding-left: 12px;"               line 42
annual_property_taxes   (input)   style="padding-left: 12px;"               line 56
total_parcel_count      (input)   style="padding-left: 12px;"               line 93
```

These do not use the `input-cover` / `has-icon` pattern. `tax_year` and `annual_property_taxes` are bare inputs without an `input-cover` wrapper, while other fields on the same tab use `<div class="input-cover">`. This causes inconsistent icon injection (the `addIconsToInputs()` function looks for `.has-icon` inside `.input-cover`; bare inputs skip the icon entirely).

### 3.2 Inline padding on `included_personal_property` and `excluded_items`
In `seller-terms.blade.php` lines 1977 and 1993:
```html
style="padding-left:40px;"
```
These `<input>` elements have `data-icon` attributes and `class="form-control has-icon"` but the `padding-left:40px` is hardcoded inline. The icon is injected by `addIconsToInputs()` which prepends an `<i>` tag inside the `.input-cover` wrapper — the icon rendering will work but the padding override conflicts with whatever the CSS `.has-icon` class may apply. If the global `.has-icon` rule changes, these fields will be misaligned.

### 3.3 `additional_details_broker` textarea (broker-compensation.blade.php, line 840)
```html
<textarea wire:model="additional_details_broker" class="form-control mt-2" rows="3"
    placeholder="Enter any additional terms"></textarea>
```
No `input-cover` wrapper, no `has-icon`, no icon. Visually inconsistent with the rest of the broker-compensation tab which uses the standard wrapper pattern.

### 3.4 `retainer_fee_amount` error span placement (broker-compensation.blade.php, line 692)
```html
<div class="input-group">
    <span class="input-group-text">$</span>
    <input ...>
    <span class="error mt-2" id="retainer_fee_amount_error"></span>  ← inside input-group
</div>
```
The `.error` span is inside the Bootstrap `input-group` div, causing it to render inline with the input rather than below it. This is a structural layout bug.

### 3.5 `retained_deposits` field (broker-compensation.blade.php, line 731)
```html
<div class="input-group">
    <input type="number" wire:model="retained_deposits" class="form-control" ...>
    <span class="input-group-text">%</span>
</div>
```
Uses Bootstrap `input-group` with a trailing `%` span — correct pattern — but there is no `input-cover` wrapper and no `data-icon`. Other similar percentage-suffix fields in the same file use `.form-control has-icon` with `input-cover`. This is inconsistent.

---

## Section 4 — Icon and Spacing Issues

### 4.1 HTML typo: broken `class` attribute on Carport label
`property-preferences.blade.php` line 1039:
```html
<label c lass="fw-bold">Carport:
```
There is a space inserted inside `class` (`c lass` instead of `class`). This breaks the `fw-bold` CSS class on the "Carport:" label — it will not render bold and the browser will ignore the malformed attribute silently.

### 4.2 Tooltip icon placed inside label vs. outside (inconsistent pattern)
Some fields place the tooltip `<span>` inside the `<label>` tag:
```html
<label class="fw-bold">Balloon Payment Due Date:
    <span class="ms-2" data-bs-toggle="tooltip" ...>
```
(seller-terms.blade.php line 1611)

Other fields place it after `</label>`:
```html
<label class="fw-bold">Carport:</label>
<span class="ms-2" data-bs-toggle="tooltip" ...>
```
This inconsistency is cosmetic but affects click targets: clicking the label text triggers the associated input only when `<label for>` is correct, and tooltip activation scope differs depending on which element is clicked.

### 4.3 Missing `input-cover` wrappers on conditional sub-fields
Sub-fields revealed by "Other" or "Yes" selections in `seller-terms.blade.php` (lines 1595–1607 `balloon_payment_amount`, lines 1563–1569 `prepayment_penalty_amount`) use:
```html
<div class="form-group mt-2">
    <div class="input-cover">
        <span class="input-group-text-seller">$</span>
        <input ...>  ← no data-icon, no has-icon
    </div>
</div>
```
No `data-icon` attribute means `addIconsToInputs()` injects nothing. The `$` prefix is present but the icon slot is blank, leaving a visual gap compared to the primary fields that show both a leading icon and the `$` prefix.

### 4.4 `balloon_payment_amount` and `prepayment_penalty_amount` — missing labels
Both sub-fields that appear when `balloon_payment === 'Yes'` and `prepayment_penalty === 'Yes'` respectively have **no `<label>` element**. The parent group has the label ("Balloon Payment:" / "Prepayment Penalty:"), but the amount sub-field is unlabeled. Accessibility and clarity concern.

---

## Section 5 — Offered Financing Placeholder Issue

**Location:** `seller-terms.blade.php`, the `#offered_financing` Select2 element.  
**JS initialization** (`offer-seller-listing.blade.php` line 1752–1766):
```javascript
$('#offered_financing').select2({
    placeholder: "Select offered financing",
    allowClear: true,
});
```

**Root cause:** Select2's `placeholder` option only displays when the native `<select>` has a blank first `<option value="">` entry. The `offered_financing` select in `seller-terms.blade.php` is built as a `wire:ignore` multi-select. Inspecting the blade, the options loop starts directly with named financing types — there is **no blank `<option value="">` element** inside the `<select>` tag.

**Result:** When no financing option is selected, the Select2 widget shows an empty field with no placeholder text, rather than the intended "Select offered financing" hint. Users who have not chosen any financing option see a blank widget with no guidance.

**Confirmed missing blank option:** The `offered_financing` select uses the same `wire:ignore` + JS pattern as the `appliances` and `sale_provision` selects. Looking at `sale_provision` in `seller-terms.blade.php` line ~29 (from prior read): the options loop there also has no blank option in the markup itself, but Select2's `allowClear` gives a visual "x". The issue is specifically the **placeholder text not appearing on empty state**.

---

## Section 6 — Association / HOA Layout Issues

### 6.1 Association type "Other" — server round-trip only
`tax-legal-hoa-disclosures.blade.php` uses `wire:model` (eager) on `association_type`. The conditional text input for the custom association type is inside `@if ($association_type === 'Other')` — requires a full Livewire server round-trip (typically 200–500 ms) before the field appears. During that round-trip the page partially re-renders causing a visible layout shift.

### 6.2 Association Fee Frequency "Other" — same pattern
`association_fee_frequency` uses the same pattern. The `@if ($association_fee_frequency === 'Other')` block for custom frequency renders only after server re-render.

### 6.3 HOA fee breakdown — checkbox/number pairs layout
The HOA fee breakdown section generates multiple `<div class="form-check">` + `<input type="number">` pairs for each fee category (e.g., Cable TV, Ground Maintenance, Insurance, etc.). These are rendered as a flat vertical list. There is no CSS grid or column layout applied, so on wider viewports the list wastes horizontal space and looks sparse.

### 6.4 `association_fee_includes` and `association_amenities` Select2 re-init timing
Both selects are initialized in `initializeFullService()` at the top level, and their "Other" toggle is applied in the `message.processed` hook via:
```javascript
$('#hoa-fee-includes-other-section').toggle(fiData.includes('Other'));
$('#hoa-amenities-other-section').toggle(amData.includes('Other'));
```
However, `association_fee_includes` and `association_amenities` are inside a Livewire-rendered section that can be hidden/shown based on `association_yn` — if the HOA card re-renders after the user selects "Yes" to having an HOA, the Select2 instances may not be re-initialized (because `!$el.hasClass('select2-hidden-accessible')` check prevents double-init but does not account for DOM replacement). **Risk: Select2 may fail to re-initialize if the HOA section is hidden then re-shown.**

---

## Section 7 — Pet Restrictions — Duplicate Field Across Two Tabs

### 7.1 Two distinct pet restriction fields bound to two different model properties
**Tab: Property Details** (`property-preferences.blade.php` lines 1399–1410):
```html
<label class="fw-bold">Pet Restrictions</label>
<input type="text" wire:model.defer="breed_restrictions" ...
    placeholder="Enter pet restrictions (e.g., No Pit Bulls)">
```
Model property: `$breed_restrictions` (single-line text input)

**Tab: Tax, Legal, HOA & Disclosures** (`tax-legal-hoa-disclosures.blade.php`):
A separate `pet_restrictions` field is present (confirmed by prior read showing `wire:model="pet_restrictions"` in that file) — a textarea in the HOA/disclosures section.
Model property: `$pet_restrictions`

### 7.2 Impact
- A seller filling out the form will encounter "Pet Restrictions" in Property Details (under Pets Allowed → Yes) and a separate pet restrictions field in the HOA & Disclosures tab.
- These are different properties. The data is saved separately. Neither field cross-references the other.
- On the listing display, depending on which property is rendered, one may be silently ignored.
- The tooltip for the Property Details field says "Enter any pet restrictions the Seller requires. Include any HOA or insurance-related restrictions if applicable." — this is misleading because there is a dedicated HOA restrictions field on a different tab.

### 7.3 The "Pets Allowed" gate
The `breed_restrictions` field (Property Details) is only shown when `$pets === 'Yes'`. If a Seller selects "No" for pets, they cannot fill in breed_restrictions — but HOA-mandated breed restrictions (e.g., no pit bulls even if pets allowed by HOA) still exist and can only be filled in the HOA tab's `pet_restrictions` field. This creates an asymmetry.

---

## Section 8 — Documents & Disclosures Formatting

### 8.1 Livewire round-trip on each disclosure Yes/No toggle
`tax-legal-hoa-disclosures.blade.php` uses `@if ($fieldName === 'Yes')` Blade conditionals to show document upload blocks for each disclosure item (e.g., Seller's Property Disclosure, Lead-Based Paint, HOA Documents). All parent selects use `wire:model` (eager), so toggling any disclosure triggers a full Livewire round-trip and re-render. With ~10 disclosure fields, a user checking all of them will trigger 10 sequential server requests.

### 8.2 `additional_documents` Select2 "Other" — correct JS pattern
The `#additional-documents-other-section` toggle is handled correctly via JS in `initializeFullService()`:
```javascript
var adData = $addDocs.val() || [];
$('#additional-documents-other-section').toggle(adData.includes('Other'));
```
And re-applied in `message.processed`. No issues here.

### 8.3 Document upload blocks — no `input-cover` wrappers
The `<input type="file">` elements inside each disclosure upload block do not use the `input-cover` + `has-icon` pattern. They use bare Bootstrap `form-control` or custom upload styling. This is expected for file inputs but creates visual inconsistency with the rest of the form.

### 8.4 Inline `style` on textarea fields in HOA section
`special_assessment_description` and `additional_lease_restrictions` textareas (from prior reads of `tax-legal-hoa-disclosures.blade.php`) use:
```html
style="padding: 10px; font-size: 16px;"
```
Same inline style inconsistency as noted in Section 3.1.

---

## Section 9 — Tooltip Gaps

Fields confirmed to have **no tooltip icon** despite handling non-obvious data:

| Field | Tab | Model Property | Issue |
|---|---|---|---|
| First Name | Seller Info | `first_name` | No tooltip icon on label |
| Last Name | Seller Info | `last_name` | No tooltip icon on label |
| Tax Year | Tax/Legal/HOA | `tax_year` | No `input-cover`, no icon, but tooltip IS present on label — however input uses bare inline `style` without `input-cover` wrapper |
| `balloon_payment_amount` | Sale Terms | `balloon_payment_amount` | No label, no tooltip on amount sub-field |
| `prepayment_penalty_amount` | Sale Terms | `prepayment_penalty_amount` | No label, no tooltip on amount sub-field |
| `seller_contribution_amount_details` | Sale Terms | `seller_contribution_amount_details` | No label, no tooltip on sub-field (revealed when "Yes" selected) |
| `possession_details` | Sale Terms | `possession_details` | No label, no tooltip on sub-field |
| `seller_home_warranty_amount_details` | Sale Terms | (wire:model) | No label, no tooltip (sub-field of Home Warranty) |
| `custom_enhancement` | Services | `custom_enhancement` | No `data-icon`, no `input-cover`, no tooltip — plain `<input class="form-control">` |
| `additional_details_broker` textarea | Broker Compensation | `additional_details_broker` | Tooltip IS present on label, but textarea has no `input-cover` wrapper |
| `agency_agreement_custom` | Broker Compensation | `agency_agreement_custom` | No label, no tooltip on the "Other" text input revealed when "Other" timeframe is selected |

---

## Section 10 — Placeholder Gaps and Inconsistencies

| Field | Current Placeholder | Issue |
|---|---|---|
| `balloon_payment_date` (seller-terms, line 1618) | `"Enter balloon payment date (e.g., 5 Years)"` | The field is labeled "Balloon Payment Due Date" but the placeholder suggests a duration ("5 Years") rather than an actual date (e.g., "MM/DD/YYYY" or "January 2030"). Type is `text` — no date picker. |
| `business_name` (property-preferences, line 2362) | `"Enter business name"` | Missing example format — should clarify legal name vs. DBA (e.g., "Enter legal business name (e.g., Sunrise Bakery LLC)"). |
| `loan_duration` (seller-terms, line 1537) | `"Enter loan duration in years (e.g., 30)"` | Input type is `text` (line 1536) but it should logically be `number`. |
| `seller_late_fee_amount` (seller-terms, line 1695) | Long compound placeholder — acceptable | Input type is `text`, not `number`. Mixed usage with the `validateInput` / `reformatNumber` handlers which expect numeric input. A freeform text field here is ambiguous. |
| `retainer_fee_application` select (broker-compensation, line 706) | `"Select application method"` | The select has no `input-cover` wrapper and no `data-icon` — no icon will be injected even though it sits adjacent to an `input-cover`-wrapped field. |
| `custom_enhancement` (services.blade.php, line 82) | `"Enter photo enhancement requests"` | No `input-cover`, no `data-icon`, no `has-icon` class — orphaned field with inconsistent styling. |
| `other_carport_needed` (property-preferences, line 1064) | `"Enter number of carport spaces (e.g., 1) "` | Trailing space in placeholder string. |
| `number_of_unit_other` (property-preferences, line 1518) | `"Enter units type (e.g., 4)"` | Label says "Units Type" but it's a `type="number"` asking for a count — placeholder and label are misleading. Should say "Enter number of units (e.g., 4)". |
| `unit_type_description` (property-preferences, line 1621) | `"Enter a brief description of this unit type (e.g., Upstairs 2/1 with balcony)"` | Good placeholder, but this field is inside the old `{{-- ... --}}` commented-out block (lines 1626–1626 show `@endif --}}`). The new code at line 1779 also has `unit_type_description` with a correct placeholder. No issue in production. |

---

## Section 11 — Navigation and Submit Logic Issues

### 11.1 Tab index computation
Tab indices are computed with a PHP `@php` block (lines 840–849). The `Financial Details` tab (index 2) is inserted **only** for `$property_type ∈ {Income, Commercial, Business}`. When `$property_type` changes (which happens on tab 0 — Listing Details), the computed indices `$saleTermsIdx`, etc., update on the next Livewire re-render.

**Risk:** If the user selects a property type that triggers Financial Details (e.g., Income), the tab buttons are re-rendered with new indices. However, `sessionStorage.setItem('seller_create_active_tab', _nId)` stores the pane `id` string (e.g., `"#sale-terms"`), not the index. The tab restoration on `message.processed` (line 2988) uses `_manualTabSwitch(_savedTabId)` which looks up by `data-bs-target` — this is index-independent and correctly survives the tab insertion.

**No bug found** in tab restoration. But the `setActiveTab(index)` call on line 2601 passes a numeric index to the PHP component. If the index is stale (e.g., user advances from tab 2 when Financial Details is absent), the PHP `$activeTab` will be set to the wrong index. This desynchronization between JS DOM tabs and PHP `$activeTab` is cosmetic (it only affects which tab is `.active` on first render after a server round-trip) but can cause subtle re-hydration bugs.

### 11.2 `setActiveTab` causes server round-trip on every tab navigation
Every tab click fires `_nComp.call('setActiveTab', _curIdx + 1)` (line 2601), which makes a Livewire XHR call to update `$activeTab` on the PHP component. This is necessary for server-side tab awareness (e.g., to include the correct partial on fresh page load) but causes a ~200 ms server delay after every tab advance.

### 11.3 `checkFormValidity()` is duplicated verbatim in both `initializeFullService()` and `initializeLimitedService()`
The entire `checkFormValidity`, `isElementVisible`, `_wizardNextHandler`, `_wizardBackHandler`, and form-listener block is copy-pasted identically (lines 2374–2643 and 2649–2892). Changes to one copy are not automatically reflected in the other. This is a maintenance risk.

### 11.4 Submit button (`wizard-step-finish`) disable/enable logic
The save button is disabled when `checkFormValidity()` returns false. The check iterates all `[required]` fields in all `.tab-pane` elements and tests `isElementVisible(field)`. 

**Known gap:** `isElementVisible` checks computed style `display: none`, `d-none` class, etc., but does **not** check if an ancestor is a `tab-pane` that is not `.active`. All tab panes are in the DOM simultaneously (Bootstrap tab behavior); a hidden inactive tab pane has `display: none` at the CSS level but Bootstrap's inactive pane may not have it applied via inline style — it's applied by the `.tab-pane` CSS rule (which `getComputedStyle` would catch). This seems correct. However, `getComputedStyle` is called at the time `checkFormValidity` runs — for fields inside `wire:ignore` sections whose DOM is manipulated by Select2, `[required]` may not be set at all (the `<select>` is replaced by a Select2 UI). This means required Select2 fields are **not validated** by `checkFormValidity`.

**Impact:** A user can potentially reach Submit with no financing type, no sale provision, no appliances selected — all of which are Select2 widgets — because they have no `[required]` attribute detectable in the DOM.

### 11.5 No per-tab validation for Select2 fields in the "Next" wizard handler
`_wizardNextHandler` queries `currentTabContent.querySelectorAll('input[required], select[required], textarea[required]')` — this correctly finds native form controls with `required`. But Select2-wrapped selects (like `#offered_financing`, `#sale_provision`, `#appliances`) are `wire:ignore` selects that may or may not have `required` on the underlying `<select>`. If the underlying `<select>` has `required` AND is hidden by Select2 (which replaces it with a `<span>`), the browser's native required validation is suppressed. The JS required-check only evaluates `.value` on the native element. For a multi-select that has been Select2-ified, the native element's `.value` is the first selected item or empty string.

**Impact:** Multi-required Select2 fields can be bypassed silently on "Next" press.

### 11.6 `wire:submit.prevent="store"` — all-or-nothing submit
The `<form id="create-auction-form" wire:submit.prevent="store">` wraps all tab panes. There is no per-tab save; the entire form is submitted at once via the final Submit button. If a user navigates directly to the last tab (all tabs are accessible via the tab strip directly), they can click Submit without filling in earlier tabs — JavaScript validation only runs on "Next" button; it does not re-validate all prior tabs on Submit.

**Impact:** The `store()` PHP method performs its own server-side validation, so data integrity is maintained at the PHP layer. But the UX is broken — a user who jumps tabs and submits may receive generic PHP validation errors without clear field-level navigation back to the offending tab.

---

## Section 12 — Recommended Staged Fix Plan

### Stage 1 — Critical Bugs (fix immediately)
Priority: prevents data loss or silent validation bypass.

| # | Issue | File(s) | Action |
|---|---|---|---|
| 1.1 | `retainer_fee_amount` error span inside `input-group` | `broker-compensation.blade.php` line 692 | Move `<span class="error">` to after the closing `</div>` of the `input-group` |
| 1.2 | HTML typo `c lass` on Carport label | `property-preferences.blade.php` line 1039 | Fix to `class="fw-bold"` |
| 1.3 | `offered_financing` Select2 placeholder not showing | `seller-terms.blade.php` | Add `<option value=""></option>` as first child of the `#offered_financing` `<select>` |
| 1.4 | Select2 required fields not validated on Next/Submit | `offer-seller-listing.blade.php` | Extend `_wizardNextHandler` to check Select2 widget values via `$(selector).val()` for designated required multi-selects |
| 1.5 | Duplicate `checkFormValidity` / wizard handlers | `offer-seller-listing.blade.php` | Extract shared wizard logic to a single `initializeWizardHandlers()` function called by both `initializeFullService()` and `initializeLimitedService()` |

### Stage 2 — UX / Fragility Fixes (high priority)
Priority: prevents confusing UI behavior that users report.

| # | Issue | File(s) | Action |
|---|---|---|---|
| 2.1 | `other_non_negotiable_amenities` double-bind race condition | `property-preferences.blade.php` + wrapper JS | Remove Blade `d-none` class from initial render; use only the inline `style` attribute driven by the PHP array check, matching the Pattern B approach used for MLS fields |
| 2.2 | Pet Restrictions duplicate fields | `property-preferences.blade.php` + `tax-legal-hoa-disclosures.blade.php` | Decide canonical field: merge `breed_restrictions` and `pet_restrictions` into one property; remove the duplicate; add a cross-reference note on the remaining field's tooltip |
| 2.3 | `balloon_payment_amount` and `prepayment_penalty_amount` missing labels | `seller-terms.blade.php` | Add `<label class="fw-bold">` for each sub-field (e.g., "Penalty Amount:", "Balloon Amount:") and add a tooltip |
| 2.4 | All sub-field reveals (seller contribution, possession, home warranty) missing labels/tooltips | `seller-terms.blade.php` | Add label + tooltip to each of these four sub-fields |
| 2.5 | `carport_needed` / `garage_needed` dual toggle (Pattern C + redundant JS) | `property-preferences.blade.php` + wrapper JS | Remove the `toggleSpaceInput` JS handler and use only the `wire:model` + `@if` pattern, OR convert to Pattern B (Blade inline style + JS only, no re-render) |

### Stage 3 — Consistency / Polish (medium priority)
Priority: reduces visual debt and tooltip/placeholder gaps.

| # | Issue | File(s) | Action |
|---|---|---|---|
| 3.1 | Textarea inline `style="padding: 10px; font-size: 16px;"` | `tax-legal-hoa-disclosures.blade.php` (and any other partials) | Replace with a shared CSS class (e.g., `.form-textarea-standard`) defined globally; remove all inline `style` from textareas |
| 3.2 | `tax_year`, `annual_property_taxes`, `total_parcel_count` missing `input-cover` wrappers | `tax-legal-hoa-disclosures.blade.php` | Wrap in `<div class="input-cover">` and add `has-icon` + `data-icon` to match surrounding fields |
| 3.3 | `included_personal_property` and `excluded_items` hardcoded `padding-left:40px` | `seller-terms.blade.php` | Remove inline style; let `addIconsToInputs()` handle icon spacing via the standard `.input-cover` CSS |
| 3.4 | `additional_details_broker` textarea missing wrapper | `broker-compensation.blade.php` | Add `<div class="input-cover">` wrapper and consider a `fa-file-lines` icon |
| 3.5 | `custom_enhancement` input missing `input-cover`, `has-icon`, icon | `services.blade.php` | Wrap in standard `input-cover`, add `data-icon="fa-solid fa-wand-magic-sparkles"` |
| 3.6 | `retainer_fee_application` select missing `input-cover` wrapper | `broker-compensation.blade.php` | Wrap in `<div class="input-cover">`, add icon |
| 3.7 | `retained_deposits` uses Bootstrap `input-group` inconsistently | `broker-compensation.blade.php` | Standardize to `input-cover` + suffix pattern or document the `input-group` exception |
| 3.8 | Tooltip inside vs. outside `<label>` inconsistency | All partials | Establish one pattern (recommend: after `</label>`, not inside it) and apply consistently |
| 3.9 | First Name / Last Name missing tooltips in Seller Info | `seller-info.blade.php` | Add tooltips explaining that this is the Seller's contact name as it will appear on the listing |
| 3.10 | `balloon_payment_date` placeholder says "e.g., 5 Years" | `seller-terms.blade.php` | Fix placeholder to reflect an actual date format: `"Enter date (e.g., June 2030 or 06/2030)"` |
| 3.11 | `loan_duration` field type is `text` not `number` | `seller-terms.blade.php` | Change `type="text"` to `type="number"` with `min="1"` and `max="50"` |
| 3.12 | `number_of_unit_other` misleading label/placeholder | `property-preferences.blade.php` | Fix label to "Number of Units" and placeholder to "Enter number of units (e.g., 4)" |

### Stage 4 — Performance / Architecture (lower priority)
Priority: reduces server round-trips and code duplication.

| # | Issue | File(s) | Action |
|---|---|---|---|
| 4.1 | `association_type`, `association_fee_frequency` trigger full re-renders on change | `tax-legal-hoa-disclosures.blade.php` | Convert `wire:model` (eager) to `wire:ignore` + JS change handler + `@this.set(prop, val, false)`, with Blade `style` initial state for "Other" sub-fields |
| 4.2 | `setActiveTab` fires an XHR on every tab nav | wrapper JS | Investigate whether `$activeTab` needs to be PHP-side or can be tracked purely in `sessionStorage`. If PHP-side tab tracking is only needed for draft/reload, defer the `setActiveTab` call to form submission or explicit save, not every click |
| 4.3 | `$showOtherAppliances` PHP boolean out of sync with JS | `SellerOfferListing.php` + `property-preferences.blade.php` | Remove `$showOtherAppliances` PHP property entirely; replace Blade `style="{{ ($showOtherAppliances ...) ? 'block' : 'none' }}"` with `style="{{ is_array($appliances) && in_array('Other', $appliances) ? 'block' : 'none' }}"` (direct array check, matching Pattern B) |
| 4.4 | HOA section Select2 may fail re-init after HOA "Yes/No" toggle | `tax-legal-hoa-disclosures.blade.php` + wrapper JS | Add a check in `message.processed` to re-initialize `#association_fee_includes` and `#association_amenities` Select2 instances whenever they are present but not yet initialized |
| 4.5 | Submit-without-prior-tab-validation gap | `offer-seller-listing.blade.php` | On `.wizard-step-finish` click, run validation across all tabs (not just the current one) and scroll to the first offending tab if validation fails |

---

*End of audit. No code changes were made during this review.*
