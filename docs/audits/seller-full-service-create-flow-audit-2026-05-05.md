# Seller Offer Listing Full-Service — Read-Only Audit Report
**Path:** `/offer-listing/seller` | **Service type:** `full_service` (commission-based)
**Date:** 2026-05-05 | **Status:** Audit complete — NO code changes made.

---

## 1. Active Partials Inventory

All 10 partials live under `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/`.
No limited-service partials are referenced in the commission-based flow.

| # | Partial File | Tab Label | Tab Index (no Financial) | Tab Index (with Financial) |
|---|---|---|---|---|
| 1 | `listing-details.blade.php` | Listing Details | 0 | 0 |
| 2 | `property-preferences.blade.php` | Property Details | 1 | 1 |
| 3 | `financial-details.blade.php` | Financial Details | — | 2 (Income/Commercial/Business only) |
| 4 | `seller-terms.blade.php` | Sale Terms | 2 | 3 |
| 5 | `additional-details.blade.php` | Additional Details | 3 | 4 |
| 6 | `tax-legal-hoa-disclosures.blade.php` | Tax, Legal, HOA & Disclosures | 4 | 5 |
| 7 | `photos-tours-documents.blade.php` | Photos & Tours | 5 | 6 |
| 8 | `seller-info.blade.php` | Seller Info | 6 | 7 |
| 9 | `services.blade.php` | Services (commission-based) | — | — (within tab pane) |
| 10 | `broker-compensation.blade.php` | Broker Compensation | — | — (within tab pane) |

Shared partials also rendered:
- `livewire.offer-listing.shared.ai-questions-input` (AI Questions — last tab)
- `livewire.partials.agent-credentials` (conditional: replaces seller-info when `user_type === 'agent'`)

---

## 2. Issue Group Audit (PASS / FAIL)

---

### Issue Group 1 — "Other" Reveal Patterns
**Verdict: PARTIAL FAIL**

#### Inventory of all "Other" reveal patterns

| Field | Trigger element | Reveal element | Mechanism | Server-side guard |
|---|---|---|---|---|
| View | `#view_preference` (Select2, wire:ignore) | `#other_preferences` (text input) | JS `$('#view_preference').on('change', ...)` → `.show()/.hide()` | `style="display: {{ ... }}"` (pp:1243) |
| Appliances | `#appliances` (Select2, wire:ignore) | `#other_appliances` form-group | JS `$('#appliances').on('change', ...)` | `style="display: {{ ($showOtherAppliances ...) }}"` (pp:1010) |
| Garage/Parking | `#garage_parking_spaces_option_landlord` (Select2, wire:ignore) | `#other_garage_parking_spaces_option_landlord` | JS `.on('change', ...)` → `style.display` | `style="{{ collect(...)->contains('Other') ? '' : 'display: none;' }}"` (pp:1172) |
| Included Assets | `#included_assets` (Select2, wire:ignore) | `.other_assets` | JS `.on('change', ...)` → `.show()/.hide()` | `style="{{ ... ? 'display:block;' : 'display:none;' }}"` (pp:1457) |
| Non-Negotiable Amenities | `#non_negotiable_amenities` (Select2, wire:ignore) | `.other_non_negotiable_amenities` | JS `attachAmenitiesDropdownListener()` + `$('#non_negotiable_amenities').on('change', ...)` | `style="{{ ... ? '' : 'display:none;' }}"` (pp:1310) |
| Offered Financing | `#offered_financing` (Select2, wire:ignore) | 8 sub-sections (`#seller-financing-*-section`) | `$(document).on('change', '#offered_financing', ...)` delegated → `applyFinancingVisibility()` | `style="display: {{ in_array('...', $offered_financing ?? []) ? 'block' : 'none' }}"` (seller-terms:653+) |
| Sale Provision | `#sale_provision` (Select2, wire:ignore) | `#seller-provision-*-section` | `$(document).on('change', '#sale_provision', ...)` delegated → `applyProvisionVisibility()` | `style="display: {{ in_array('...', ...) }}"` |
| HOA Fee Includes | `#association_fee_includes` (Select2, wire:ignore) | `#hoa-fee-includes-other-section` | `$(document).on('change', '#association_fee_includes', ...)` | `style="display: {{ in_array('Other', ...) ? 'block' : 'none' }}"` (tax-legal:516) |
| HOA Amenities | `#association_amenities` (Select2, wire:ignore) | `#hoa-amenities-other-section` | `$(document).on('change', '#association_amenities', ...)` | `style="display: {{ in_array('Other', ...) ? 'block' : 'none' }}"` (tax-legal:553) |
| Additional Documents | `#additional_documents` (Select2, wire:ignore) | `#additional-documents-other-section` | `$(document).on('change', '#additional_documents', ...)` | `style="display: {{ in_array('Other', ...) ? 'block' : 'none' }}"` |
| Exchange Item | `#exchange_item` (Select2, wire:ignore) | `other_exchange_item` input | `@if (is_array($exchange_item) ? in_array('Other', ...) : ...)` — pure Livewire @if | No JS needed; @this.set() triggers re-render |
| Reason for Sale | `reason_for_sale` (wire:model select) | reveal div | `@if ($reason_for_sale === 'Other')` — pure Livewire | No JS; standard morph |
| Assumable Occupancy | `assumable_occupancy_requirement` (wire:model) | text input | `@if ((...) === 'Other')` — pure Livewire (seller-terms:641) | Standard morph |
| Crypto Transfer Timing | `crypto_transfer_timing` (wire:model) | text input | `@if ((...) === 'Other')` — pure Livewire (seller-terms:779) | Standard morph |
| Exchange Liens | `exchange_liens` (wire:model) | text input | `@if ((...) === 'Yes')` — pure Livewire (seller-terms:939) | Standard morph |
| HOA Association Type | `association_type` (wire:model) | text input | `@if ($association_type === 'Other')` — pure Livewire (tax-legal:356) | Standard morph |
| HOA Fee Frequency | `association_fee_frequency` (wire:model) | text input | `@if ($association_fee_frequency === 'Other')` — pure Livewire (tax-legal:428) | Standard morph |
| HOA Min Lease Period | `min_lease_period` (wire:model) | text input | `@if ($min_lease_period === 'Other')` — pure Livewire (tax-legal:630) | Standard morph |

#### Specific failures found

**FAIL 1.1 — Blank `<option value="">` in `#offered_financing` multi-select**
- **File:** `seller-terms.blade.php` line 357
- **Code:** `<option value=""></option>` is the first child of the `#offered_financing` `<select>`
- **Root cause:** Copy from a single-select pattern applied to a multi-select. In a Select2 multi-select, a blank value option renders a selectable blank entry in the dropdown list. When selected, it passes an empty string into the `offered_financing` array, corrupting the value and causing `in_array()` checks and `applyFinancingVisibility()` to evaluate blank as a key.
- **Impact:** User sees and can select a blank option. If selected, an empty string is stored in `offered_financing[]`, confusing all downstream financing-section visibility logic.

**FAIL 1.2 — Non-Negotiable Amenities "Other" toggle uses native `addEventListener` without cleanup**
- **File:** `offer-seller-listing.blade.php` lines 2124–2137 (`attachAmenitiesDropdownListener()`) and line 2140 (re-attaches inside `Livewire.hook('message.processed', ...)`).
- **Root cause:** `attachAmenitiesDropdownListener()` adds a native `addEventListener('change', ...)` on `#non_negotiable_amenities` on every `message.processed` cycle without removing the previous listener. The Select2 `change` handler added at line 1827 only calls `@this.set()` — it does NOT call `toggleOtherAmenities`. So the reveal depends entirely on the native listener. After N Livewire round-trips, N handlers are attached, each calling `toggleOtherAmenities` — this is harmless for display but is a memory leak and signals a structural inconsistency with the other reveal patterns which use delegated `$(document).on('change', ...)`.
- **Impact:** Minor functional leak; no visible UI defect but increases memory footprint on long sessions.

---

### Issue Group 2 — Textarea Heights
**Verdict: FAIL**

**File:** `offer-seller-listing.blade.php` line 57
```css
.form-control { min-height: 50px; }
```
This rule applies globally to **all** `.form-control` elements, including `<textarea>` fields. Textareas with `rows="2"` (which normally render at ~52px) are only marginally affected, but single-line `<input>` controls gain forced height that conflicts with font size on some browsers.

The deeper problem: `<textarea>` elements used in the flow do not have explicit `height` or `rows` values that override the global `min-height`. Affected textareas:

| Textarea | File | Line | rows attr |
|---|---|---|---|
| `legal_description` | `listing-details.blade.php` | ~196 | none (default) |
| `additional_parcel_ids` | `listing-details.blade.php` | ~217 | none |
| `association_approval_process` | `tax-legal-hoa-disclosures.blade.php` | 465 | `rows="2"` |
| `additional_lease_restrictions` | `tax-legal-hoa-disclosures.blade.php` | 647 | `rows="2"` |
| `pet_restrictions_detail` | `tax-legal-hoa-disclosures.blade.php` | 680 | `rows="2"` |
| `special_assessment_description` | `tax-legal-hoa-disclosures.blade.php` | ~880 | `rows="2"` |

**Root cause:** The `min-height: 50px` was intended for `<input>` controls to maintain touch-friendly tap targets. It was applied to `.form-control` instead of `input.form-control`, unintentionally catching textareas.

---

### Issue Group 3 — Icon Issues
**Verdict: PARTIAL FAIL**

#### How icons are injected
`addIconsToInputs()` (main blade lines 2777–2787) runs on page load and every `Livewire.hook('message.processed', ...)`. For each `.has-icon` element it:
1. Reads `data-icon` attribute
2. Checks `parent.querySelector(':scope > .input-icon')` to avoid duplicates
3. Injects `<i class="input-icon {iconClass}">` before the input

#### Issues found

**FAIL 3.1 — `input-icon2` class injected into non-Select2 contexts**
- Multiple Select2 selects use `data-icon="fa-solid fa-plug input-icon2"` (e.g., `#appliances` at pp:1000, `#view_preference` at pp:1230, `#non_negotiable_amenities` at pp:1289). The class `input-icon2` is appended directly to the icon element by `addIconsToInputs()`, producing `<i class="input-icon fa-solid fa-plug input-icon2">`. Select2 replaces the native `<select>` with its own container, leaving the injected `<i>` before the Select2 container. The `input-icon2` CSS must position this icon inside the Select2 container — if that CSS rule is missing or uses a selector expecting a specific DOM structure, the icon is orphaned and invisible or mispositioned.
- **File / line:** `offer-seller-listing.blade.php:2783` (icon creation), various partials for `data-icon` values.

**FAIL 3.2 — Duplicate icon injection risk after `removeWizardEventListeners()`**
- `removeWizardEventListeners()` (main blade lines 1732–1741) replaces Next/Back button DOM nodes via `cloneNode(true)`. This does NOT affect `.input-cover > .input-icon` elements. However, `addIconsToInputs()` is called on every `message.processed`, and the guard `parent.querySelector(':scope > .input-icon')` correctly prevents re-injection as long as the parent element is stable (not replaced by morphdom). When Livewire morphdom replaces an `.input-cover` div (e.g., when `$property_type` changes and a conditional block re-renders), the icon is lost and re-injected on the next `message.processed`. This is expected behavior but means a brief icon flash on each Livewire round-trip for morphed sections.

**PASS — Operating Statement Available icon:** Confirmed present at `financial-details.blade.php` line 104–106 (`<span class="ms-2" data-bs-toggle="tooltip"...><i class="fa-solid fa-circle-info"></i></span>`). Earlier note in scratchpad was in error.

---

### Issue Group 4 — Placeholder Gaps
**Verdict: PARTIAL FAIL**

**FAIL 4.1 — `#offered_financing` blank option acts as a false placeholder**
- **File:** `seller-terms.blade.php` line 357: `<option value=""></option>` (no text content)
- A Select2 multi-select should not have a blank `<option value="">` at all — the `placeholder` option in `$('#offered_financing').select2({ placeholder: "Select offered financing" })` (main blade:1754) handles the empty-state display. The blank `<option>` in the markup becomes a selectable blank entry. (Also noted under Issue Group 1.)

**FAIL 4.2 — `purchase_fee_percentage` input (Broker Compensation) lacks icon**
- **File:** `broker-compensation.blade.php` line 77–79
- `<input type="number" wire:model="purchase_fee_percentage" class="form-control" placeholder="...">` — no `has-icon` class and no `data-icon` attribute, while its sibling `purchase_fee_flat` (line 70) does not either — but this is inside a `.input-cover` with a `<span class="input-group-text-seller">$</span>`. The number input for percentage has no `%` suffix and no icon. Minor UX inconsistency.

**FAIL 4.3 — `seller_leasing_fee_type` for Commercial/Business missing "Other" option**
- **File:** `broker-compensation.blade.php` line 294: `{{-- <option value="other">Other</option> --}}` — the "Other" option for the Seller Leasing Fee Type is commented out for Commercial/Business, but the Residential/Income branch at line 283 includes it. This leaves Commercial/Business sellers with no "Other" escape hatch for custom leasing fee structures.

---

### Issue Group 5 — HOA Layout
**Verdict: PASS**

The HOA section (`tax-legal-hoa-disclosures.blade.php`) is well-structured:
- Association Fee Amount and Fee Frequency are correctly placed in a Bootstrap `.row` with two `.col-md-6` columns (lines 381–426).
- The `@if ($association_fee_frequency === 'Other')` reveal (line 428) is positioned outside and below the `.row` — correct, as the Other input spans full width.
- Association Type "Other" reveal (line 356) sits below its parent form-group — correct.
- All HOA sub-sections are wrapped inside `@if ($has_hoa === 'Yes')` — correct conditional guard.
- No unclosed `<div>` tags found in the HOA section.

---

### Issue Group 6 — Pet Restriction Duplication
**Verdict: PARTIAL FAIL**

Two separate pet-related field groups exist:

**Location A — Property Preferences tab**
- File: `property-preferences.blade.php` lines 1327–1411
- Condition: `@if (in_array($property_type, ['Residential', 'Income']))`
- Fields: `pets` (Pets Allowed? Yes/No), `number_of_pets`, `type_of_pets`, `weight_of_pets`, `breed_restrictions`
- Label on `breed_restrictions` field: **"Pet Restrictions"** (line 1400)
- Purpose: Seller's personal pet policy for the property

**Location B — Tax, Legal, HOA & Disclosures tab**
- File: `tax-legal-hoa-disclosures.blade.php` lines 652–683
- Condition: Inside `@if ($has_hoa === 'Yes')` only
- Fields: `pet_restrictions` (Yes/No/Not Applicable/Unknown), `pet_restrictions_detail` (textarea)
- Purpose: HOA-imposed pet restrictions

**Issues:**
- The label "Pet Restrictions" on `breed_restrictions` in Location A (pp:1400) conflicts with the "Pet Restrictions" label on the `pet_restrictions` select in Location B (tax-legal:654). A user filling out both tabs will see identically-labelled questions asking about pets in two different tabs with no indication they are different in scope.
- `breed_restrictions` (Location A) stores free-text breed restrictions as a Seller preference; `pet_restrictions_detail` (Location B) stores HOA-mandated restrictions. These should be clearly distinguished. The current label "Pet Restrictions" for `breed_restrictions` should be "Breed/Pet Restrictions (Seller's Policy)" to differentiate.
- Data is stored in separate model properties (`breed_restrictions` vs `pet_restrictions_detail`), so there is no data collision — only a UX confusion risk.

---

### Issue Group 7 — Document Disclosure Layout
**Verdict: PASS**

All file upload disclosure blocks in `tax-legal-hoa-disclosures.blade.php` (lines 690–1018) follow a consistent, self-contained pattern:
```blade
<div class="form-group">
    <select wire:model="xxx_available" ...>...</select>
</div>
@if ($xxx_available === 'Yes')
    <div class="form-group mt-3 border-start border-2 border-light ps-3">
        <input type="file" wire:model="xxx_file" ...>
        ...
    </div>
@endif
```
- No unclosed `<div>` elements were found.
- Upload spinners (`wire:loading wire:target="xxx_file"`) and `@error` directives are present on each upload block.
- The `wire:model` on file inputs correctly uses `WithFileUploads` (confirmed by `use WithFileUploads` in `SellerOfferListing.php` line 16).

---

### Issue Group 8 — Navigation and JavaScript Patterns
**Verdict: FAIL**

This is the most severe issue group. Multiple structural bugs exist.

**FAIL 8.1 — Livewire.hook accumulation (critical)**
- **File:** `offer-seller-listing.blade.php`
- **Root cause:** `initializeFullService()` is called inside `Livewire.hook('message.processed', ...)` (line 2830) with a 300ms debounce. Each invocation of `initializeFullService()` registers **new** `Livewire.hook('message.processed', ...)` listeners at lines 1894, 1987, 2037, 2140, and 2176 — 5 additional hook registrations per call. Livewire hooks are never removed. After N Livewire round-trips (300ms apart), each inner hook fires N times.
- **After 10 interactions:** Each `message.processed` fires 50+ inner handlers. Each handler calls `attachAuctionDropdownListener()`, `toggleGarageOptions()`, `toggleSpaceInput(...)`, `attachAmenitiesDropdownListener()`, `attachBedroomsDropdownListener()`. The `attach*Listener()` functions add a new native `addEventListener` each call without cleaning up the old one.
- **Impact:** Progressive performance degradation, memory leak, and potential race conditions where multiple handlers set conflicting Livewire state.

**FAIL 8.2 — Back button fires twice**
- **File:** `offer-seller-listing.blade.php` lines 1242 and 2712–2719
- The Back button (line 1242) has an inline `onclick` handler containing the full back-navigation logic.
- A separate delegated `document.addEventListener('click', ...)` handler (line 2712) also catches `.wizard-step-back` clicks and calls `window._wizardBackHandler` (line 2716).
- `removeWizardEventListeners()` (line 1732) replaces button nodes via `cloneNode(true)`, which preserves the inline `onclick` on the clone. The delegated handler is on `document` and survives.
- **Result:** A Back button click fires both the inline onclick and the delegated `_wizardBackHandler`, potentially advancing two tabs back or causing double Livewire `setActiveTab()` calls.

**FAIL 8.3 — `getAllRequiredFields()` queries wrong tab IDs for seller full_service**
- **File:** `offer-seller-listing.blade.php` lines 2856–2869
- The `tabSelector` for `full_service` hardcodes: `['#listing-details', '#property-details', '#sale-terms', '#services', '#additional-details', '#broker-compensation', '#tenant-info']`
- In the seller full_service flow, `#services` (as a tab pane ID) does not exist — the services content is rendered inside the seller-terms or within the wizard but not as a tab pane with id="services". `#broker-compensation` is a section within a tab pane, not a tab pane. `#tenant-info` does not exist in the seller flow.
- **Impact:** The save button enable/disable logic (`validateAllTabsStrictly()` called on submit) silently skips non-existent panes, meaning required fields in the actual seller tabs (e.g., `#sale-terms`, `#tax-legal-hoa-disclosures`) are not included in the strict validation scan.

**FAIL 8.4 — `DOMContentLoaded` listeners inside `initializeFullService()` never fire**
- **File:** `offer-seller-listing.blade.php` lines 1889 and 2210
- `document.addEventListener('DOMContentLoaded', () => { attachAuctionDropdownListener(); })` and similar calls are inside `initializeFullService()`, which is called after the DOM is already ready (on `livewire:load` and `message.processed`). `DOMContentLoaded` has already fired; these listeners are dead code.
- The actual `attachAuctionDropdownListener()` call that works is the direct call at line 1884 (outside the DOMContentLoaded wrapper) and via `Livewire.hook('message.processed', ...)`.

**FAIL 8.5 — sessionStorage tab restore fights user navigation**
- **File:** `offer-seller-listing.blade.php` lines 2837–2843
- On every `message.processed`, if `seller_create_active_tab` is in sessionStorage and the stored tab ID differs from the currently active tab, `_manualTabSwitch` is called to restore the stored tab. This means: if a user clicks a tab directly (which stores the new ID in sessionStorage via the `shown.bs.tab` listener at line 1748), and then a Livewire update fires (e.g., from an auto-save or field change), the `message.processed` handler may switch the tab back to the sessionStorage value. The race window is small but real: if sessionStorage writes from line 1748 haven't completed before the Livewire response arrives, the restore at line 2841 will redirect to the wrong tab.

---

### Issue Group 9 — Miscellaneous Code-Quality Issues
**Verdict: FAIL**

**FAIL 9.1 — Malformed `<option>` HTML in broker-compensation**
- **File:** `broker-compensation.blade.php` lines 286–289
```html
<option value="Percentage of Net Aggregate Rent">Percentage of Net Aggregate Rent
<option value="Percentage of Gross Rent">Percentage of Gross Rent </option>
</option>
```
The first `<option>` tag is never closed before the second `<option>` opens. The browser auto-corrects by treating the second `<option>` as a sibling, but this is invalid HTML and will fail strict validation.

**FAIL 9.2 — `@error('purchase_fee_*')` wildcard not valid Blade syntax**
- **File:** `broker-compensation.blade.php` line 110
- `@error('purchase_fee_*')` — Blade's `@error` directive does not support wildcards. This will never match any validation error key. The intent is to show errors for `purchase_fee_flat`, `purchase_fee_percentage`, etc. — each would need its own `@error` directive.
- **Impact:** Purchase fee validation errors from the backend are silently swallowed and never displayed.

**FAIL 9.3 — Duplicate checkbox `id` attributes across property types in services.blade.php**
- **File:** `services.blade.php` — Residential block (line 52) and Income block (line 232) both render checkboxes for service strings like `'Provide professional property photography'`. `Str::slug($service)` produces the same slug, yielding `id="media-provide-professional-property-photography"` in both blocks. When both property types are rendered (they are in `@if ($property_type == 'Residential')` / `@if ($property_type == 'Income')` separate blocks, so only one renders at a time), this is not active simultaneously. **Status: PASS** when only one block renders; the duplicate IDs only matter if both blocks were ever simultaneously in the DOM, which they are not due to the `@if` guards.

**FAIL 9.4 — `Livewire.emit('serviceTypeChanged', serviceType)` uses deprecated Livewire v3 API**
- **File:** `offer-seller-listing.blade.php` line 1729
- `Livewire.emit()` was deprecated in Livewire v3 (replaced by `$dispatch()`). If the codebase has migrated to Livewire v3, this emit silently fails — no event is dispatched to the component. However, the codebase also uses `Livewire.hook('message.processed', ...)` which is a Livewire v2 hook (v3 uses `Livewire.hook('commit', ...)`). The stack appears to be a v2/v3 hybrid, meaning some hooks may not fire at all depending on the actual runtime version.
- **File:** Also `Livewire.emit('updateModel', ...)` at main blade line 2021 — same issue.

---

## 3. Root-Cause Summary Table

| # | Issue | Root Cause | File | Line(s) |
|---|---|---|---|---|
| RC-1 | Blank option in offered_financing multi-select | Single-select pattern copied to multi-select without removing blank option | `seller-terms.blade.php` | 357 |
| RC-2 | Global `min-height: 50px` on `.form-control` catches textareas | CSS rule targets class instead of `input.form-control` | `offer-seller-listing.blade.php` | 57 |
| RC-3 | Livewire.hook accumulation inside `initializeFullService()` | Hooks registered inside a function that is itself called from a hook, with no deregistration | `offer-seller-listing.blade.php` | 1894, 1987, 2037, 2140, 2176 |
| RC-4 | Back button fires twice | Inline onclick + delegated document listener both respond to same button click | `offer-seller-listing.blade.php` | 1242, 2712–2719 |
| RC-5 | `getAllRequiredFields()` wrong tab IDs for seller | Tab selector array copied from tenant/landlord flow, not updated for seller | `offer-seller-listing.blade.php` | 2856–2869 |
| RC-6 | `DOMContentLoaded` listeners inside `initializeFullService()` dead | Handlers registered after DOM ready; event never fires again | `offer-seller-listing.blade.php` | 1889, 2210 |
| RC-7 | `attachAmenitiesDropdownListener()` leaks native listeners | Reattaches native change listener on every `message.processed` without removing previous | `offer-seller-listing.blade.php` | 2124–2140 |
| RC-8 | Malformed `</option>` in broker-compensation | Missing closing tag before new `<option>` opens | `broker-compensation.blade.php` | 286–289 |
| RC-9 | `@error('purchase_fee_*')` wildcard | Blade @error does not support wildcards | `broker-compensation.blade.php` | 110 |
| RC-10 | Pet restriction label confusion | `breed_restrictions` labeled "Pet Restrictions" — identical label to HOA pet restrictions field | `property-preferences.blade.php` | 1400 |
| RC-11 | `Livewire.emit()` deprecated in v3 | Livewire v2 API used in possible v3 runtime | `offer-seller-listing.blade.php` | 1729, 2021 |
| RC-12 | "Other" option commented out for Commercial/Business leasing fee | Option was disabled and comment not removed | `broker-compensation.blade.php` | 294–295 |
| RC-13 | sessionStorage restore races with user tab navigation | `message.processed` handler restores saved tab before sessionStorage updates propagate | `offer-seller-listing.blade.php` | 2837–2843 |

---

## 4. Four-Stage Fix Plan

### Stage 1 — Critical Bugs (Fix First — Blocks Correct Submission)

These bugs cause data corruption, silent validation failures, or prevent correct form submission.

**S1.1 — Fix blank option in `#offered_financing`**
- **File:** `seller-terms.blade.php` line 357
- **Action:** Remove `<option value=""></option>`. The Select2 `placeholder` config handles empty-state display.

**S1.2 — Fix `getAllRequiredFields()` tab IDs for seller full_service**
- **File:** `offer-seller-listing.blade.php` lines 2856–2869
- **Action:** Replace the hardcoded `tabSelector` for `full_service` with the actual seller tab pane IDs: `['#listing-details', '#property-details', '#financial-details', '#sale-terms', '#additional-details', '#tax-legal-hoa-disclosures', '#photos-tours-documents', '#seller-information']`. Guard `#financial-details` with an existence check (`document.querySelector('#financial-details')`) since it only renders for certain property types.

**S1.3 — Fix `@error('purchase_fee_*')` wildcard**
- **File:** `broker-compensation.blade.php` line 110
- **Action:** Replace with individual `@error('purchase_fee_flat')`, `@error('purchase_fee_percentage')`, `@error('purchase_fee_flat_combo')` directives inside their respective `@if` blocks, or consolidate using a named error bag.

**S1.4 — Fix malformed `<option>` HTML**
- **File:** `broker-compensation.blade.php` lines 286–289
- **Action:** Add closing `</option>` after `Percentage of Net Aggregate Rent` text.

---

### Stage 2 — Structural JS Bugs (Fix Second — Causes Degradation Over Time)

These bugs do not block initial use but worsen with each user interaction.

**S2.1 — Eliminate Livewire.hook accumulation**
- **File:** `offer-seller-listing.blade.php` lines 1894, 1987, 2037, 2140, 2176
- **Action:** Move all `Livewire.hook('message.processed', ...)` registrations OUT of `initializeFullService()` and into the top-level `@push('scripts')` block, guarded by a single `if (!window.__innerHooksBound)` flag. Each hook should call the specific init/toggle function directly. This ensures hooks are registered once regardless of how many times `initializeFullService()` is called.

**S2.2 — Fix Back button double-fire**
- **File:** `offer-seller-listing.blade.php` lines 1242 and 2712–2719
- **Action:** Remove the inline `onclick` from the Back button (line 1242) entirely. The delegated `document.addEventListener('click', ...)` handler for `.wizard-step-back` at line 2712 already calls `window._wizardBackHandler`. The button should have no inline handler; the delegated handler is sufficient and survives DOM replacement.

**S2.3 — Fix `attachAmenitiesDropdownListener()` listener leak**
- **File:** `offer-seller-listing.blade.php` lines 2124–2140
- **Action:** Either (a) convert to a `$(document).on('change', '#non_negotiable_amenities', ...)` delegated handler matching the pattern of `#offered_financing` and `#sale_provision`, or (b) add a `data-amenities-bound` flag on the element and skip attachment if already bound. Option (a) is the correct architectural match.

**S2.4 — Remove dead `DOMContentLoaded` listeners inside `initializeFullService()`**
- **File:** `offer-seller-listing.blade.php` lines 1889 and 2210
- **Action:** Remove the `document.addEventListener('DOMContentLoaded', ...)` wrappers. Call `attachAuctionDropdownListener()` and `attachConditionDropdownListener()` directly (they are already called directly on adjacent lines). The DOMContentLoaded wrappers are dead code.

**S2.5 — Fix sessionStorage tab-restore race**
- **File:** `offer-seller-listing.blade.php` lines 2837–2843
- **Action:** Only restore from sessionStorage if the current active tab pane is not visible (`!_tabTrigger.classList.contains('active')`). The existing guard (line 2840) already checks this — but `_manualTabSwitch` is called without also calling `setActiveTab` on the Livewire component. Add a Livewire component call after the manual switch to keep PHP `$activeTab` in sync: `Livewire.find(...).call('setActiveTab', tabIndex)`.

---

### Stage 3 — UX Defects (Fix Third — Affects User Experience)

**S3.1 — Fix textarea `min-height`**
- **File:** `offer-seller-listing.blade.php` line 57
- **Action:** Change `.form-control { min-height: 50px; }` to `input.form-control { min-height: 50px; }`. This preserves the touch-target behavior for inputs while letting textareas size naturally from their `rows` attribute.

**S3.2 — Fix pet restriction label confusion**
- **File:** `property-preferences.blade.php` line 1400
- **Action:** Change label text from `"Pet Restrictions"` to `"Breed / Pet Restrictions (Seller's Policy)"` to distinguish from the HOA-level `"Pet Restrictions"` field on the Tax, Legal, HOA & Disclosures tab.

**S3.3 — Restore "Other" option for Commercial/Business leasing fee**
- **File:** `broker-compensation.blade.php` lines 294–295
- **Action:** Uncomment `{{-- <option value="other">Other</option> --}}` for the `seller_leasing_fee_type` Commercial/Business branch, matching the Residential/Income branch which already includes "Other".

**S3.4 — Clarify `#other_appliances` JS target**
- **File:** `offer-seller-listing.blade.php` line 2090
- **Action:** The JS targets `$('#other_appliances').closest('.form-group').show()` — but `#other_appliances` is an `<input>`, not the form-group. The selector chain works because the input is nested inside `.form-group`. A more explicit target `$('#other_appliances').parent().parent()` or giving the form-group wrapper an explicit ID (`id="other_appliances_wrapper"`) would make intent clearer and more resilient to template restructuring.

---

### Stage 4 — Code Quality / Modernization (Fix Last — No Visible Impact)

**S4.1 — Replace deprecated `Livewire.emit()` calls**
- **File:** `offer-seller-listing.blade.php` lines 1729 and 2021
- **Action:** Replace `Livewire.emit('serviceTypeChanged', serviceType)` with `Livewire.dispatch('serviceTypeChanged', { serviceType })` (v3 syntax). Replace `Livewire.emit('updateModel', ...)` with the appropriate `$dispatch` call or eliminate if unused. Confirm which Livewire version is in production before choosing syntax.

**S4.2 — Remove duplicate `initializeFullService()` Select2 init for `#offered_financing`**
- **File:** `offer-seller-listing.blade.php` lines 1751–1765 (inside `initializeFullService()`)
- Both `initializeFullService()` AND the `$(document).on('change', '#offered_financing', ...)` delegated handler (line 1426) exist simultaneously. The direct `$('#offered_financing').on('change', ...)` inside `initializeFullService()` (line 1758, guarded by `data-of-change-bound`) is redundant with the document-level delegated handler. Remove the non-delegated handler registration from `initializeFullService()` and rely solely on the delegated handler for consistency with all other Select2 fields.

**S4.3 — Add `wire:key` to services checkboxes**
- **File:** `services.blade.php` — all `@foreach` checkbox loops
- Add `wire:key="{{ $property_type }}-{{ Str::slug($service) }}"` to each checkbox `<div>` to ensure Livewire morphdom correctly patches checkboxes without losing checked state when the component re-renders.

---

## 5. Issue Group Summary

| Issue Group | Verdict | Severity | Stage |
|---|---|---|---|
| 1. "Other" reveal patterns | PARTIAL FAIL | High (blank option), Low (listener leak) | S1.1, S2.3 |
| 2. Textarea heights | FAIL | Medium | S3.1 |
| 3. Icon issues | PARTIAL FAIL | Low | S3.4 |
| 4. Placeholder gaps | PARTIAL FAIL | Medium (missing errors), Low (icon) | S1.3, S3.3 |
| 5. HOA layout | PASS | — | — |
| 6. Pet restriction duplication | PARTIAL FAIL | Medium | S3.2 |
| 7. Document disclosure layout | PASS | — | — |
| 8. Navigation/JS patterns | FAIL | Critical | S1.2, S2.1–S2.5 |
| 9. Miscellaneous code quality | FAIL | High (RC-8, RC-9), Low (RC-11) | S1.3, S1.4, S4.1 |
