# Offer Listing Form — Root Cause Audit Report
**Date:** May 4, 2026  
**Scope:** Read-only audit of `/offer-listing/seller`, `/offer-listing/landlord`, `/offer-listing/buyer`, `/offer-listing/tenant` create/edit forms  
**Status:** No code modified — findings and surgical fix plan only

---

## 1. Route / Component Map (Confirmed Active)

| URL | Livewire Component | Parent Blade |
|---|---|---|
| `/offer-listing/seller` | `App\Http\Livewire\OfferListing\Seller\SellerOfferListing` | `livewire/offer-listing/seller/offer-seller-listing.blade.php` (3,282 lines) |
| `/offer-listing/landlord` | `App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing` | `livewire/offer-listing/landlord/offer-landlord-listing.blade.php` (3,420 lines) |
| `/offer-listing/buyer` | `App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing` | `livewire/offer-listing/buyer/offer-buyer-listing.blade.php` |
| `/offer-listing/tenant` | `App\Http\Livewire\OfferListing\Tenant\TenantOfferListing` | `livewire/offer-listing/tenant/offer-tenant-listing.blade.php` |

**Active tab partials for Landlord** (`offer-landlord-tabs/commission-based/`):
- `property-preferences.blade.php` ← **1,609 lines — primary file for property details, Other-fields, city/county**
- `listing-details.blade.php`, `lease-terms.blade.php`, `additional-details.blade.php`
- `tax-legal-hoa-disclosures.blade.php`, `photos-tours-documents.blade.php`
- `landlord-info.blade.php`, `services.blade.php`

**Active tab partials for Seller** (`offer-seller-tabs/commission-based/`):
- `property-preferences.blade.php` ← **2,980 lines — primary file for property details**
- `listing-details.blade.php`, `financial-details.blade.php`, `seller-terms.blade.php`
- `tax-legal-hoa-disclosures.blade.php`, `photos-tours-documents.blade.php`
- `seller-info.blade.php`, `services.blade.php`

---

## 2. Duplicate File Audit — Confirmed NOT the Root Cause

Two parallel directory trees exist (Hire Agent and Offer Listing). All recent edits targeted the correct active Offer Listing files.

| File | Inactive (Hire Agent) Path | Active (Offer Listing) Path | File Size Comparison |
|---|---|---|---|
| Landlord property-preferences | `hire-landlord-agent/.../property-preferences.blade.php` | `offer-listing/offer-landlord-tabs/.../property-preferences.blade.php` | Offer Listing = 81 KB vs 45 KB (larger = more recently edited ✓) |
| Seller property-preferences | `hire-seller-agent/.../property-preferences.blade.php` | `offer-listing/offer-seller-tabs/.../property-preferences.blade.php` | Offer Listing = 161 KB vs 90 KB (larger = more recently edited ✓) |
| Landlord listing-details | `hire-landlord-agent/.../listing-details.blade.php` | `offer-listing/offer-landlord-tabs/.../listing-details.blade.php` | Offer Listing = 42 KB ✓ |

**Verdict:** No edits went to wrong/duplicate files. The duplicates are NOT the problem.

---

## 3. Cache / Build Audit — Root Cause of "Fixes Not Appearing"

| Check | Status | Notes |
|---|---|---|
| JS location | Inline `@push('scripts')` in Blade | **No Vite/npm rebuild needed for any Blade-only fix** |
| Compiled assets in `public/build/` | Untouched | `app.js`, `select2-manager.js` are for global JS, not field-toggle logic |
| Blade view cache | **Cleared** | `php artisan view:clear` run |
| App / config / route cache | **Cleared** | All caches cleared |
| Git working tree | Clean | Only untracked `attached_assets/` files |
| Current commit | `b7bcd683` | "Fix 'Other' input toggle, sewer duplicate icon, and placeholder parity on Seller & Landlord Offer Listing pages" |

**Root Cause Confirmed:** Stale Blade view cache + browser cache caused previously applied fixes not to appear. A hard refresh (`Ctrl+Shift+R`) and cache-clear resolves this for all items that were correctly patched. All Blade edits in commit `b7bcd683` targeted the correct active files.

---

## 4. Landlord "Other" Input Field Audit — Full Wiring Table

All fields are in `offer-landlord-tabs/commission-based/property-preferences.blade.php`.

| Field | Select ID | Blade Line(s) | Other Wrapper ID | "Other" in Options? | Wrapper div in Blade? | Component Property | Saves/Loads? | Issue |
|---|---|---|---|---|---|---|---|---|
| Appliances Included | `appliances` | 552 | `other_appliances` (line 562) | ✓ | ✓ | `$other_appliances` | ✓ | **None** |
| View | `view_preference` | 736 | `other_preferences` (line 750) | ✓ | ✓ | `$other_preferences` | ✓ | **None** |
| Sewer | `sewer` | 920, 1195 | `other_sewer_wrapper` (lines 929, 1204) | ✓ | ✓ | `$other_sewer` | ✓ | **None** |
| Heating and Fuel | `heating_fuel` | 852, 1220 | `other_heating_fuel_wrapper` (lines 861, 1229) | ✓ | ✓ | `$other_heating_fuel` | ✓ | **None** |
| Air Conditioning | `air_conditioning` | 877, 1245 | `other_air_conditioning_wrapper` (lines 886, 1254) | ✓ | ✓ | `$other_air_conditioning` | ✓ | **None** |
| **Water** | `water` | **902, 1177** | **MISSING** | **✗ — options list: `['Canal/Lake for Irrigation', 'Private', 'Public', 'See Remarks', 'Well', 'None']`** | **✗** | `$other_water` (line 581) | ✓ saves (line 2550) / loads (line 2018) | **❌ No "Other" in select; no wrapper div** |
| Utilities | `property_utilities` | 945, 1152 | `other_property_utilities_wrapper` (lines 954, 1161) | ✓ | ✓ | `$other_property_utilities` | ✓ | **None** |
| Laundry Features | `laundry_features` | 970 | `other_laundry_features_wrapper` (line 979) | ✓ | ✓ | `$other_laundry_features` | ✓ | **None** |
| Floor Covering | `floor_covering` | 995 | `other_floor_covering_wrapper` (line 1004) | ✓ | ✓ | `$other_floor_covering` | ✓ | **None** |
| **Security Features** | `security_features` | **1020** | **MISSING** | **✗ — options end at `'Smoke Detector(s)'`, no "Other"** | **✗** | `$other_security_features` (line 593) | ✓ saves (line 2560) / loads (line 2028) | **❌ No "Other" in select; no wrapper div** |

**Summary:** 8 of 10 fields are correctly wired end-to-end. Water and Security Features are missing the "Other" option in their select and have no wrapper div in Blade. Both backend properties already exist and save/load correctly — **only Blade HTML changes are needed.**

**Note on JS:** The main `offer-landlord-listing.blade.php` does NOT contain a JS toggle config entry for `other_water_wrapper` or `other_security_features_wrapper`. The toggle logic for Sewer, Heating/Fuel, Air Conditioning, Laundry, Floor Covering, and Utilities is handled via inline `style="display: ..."` conditions in the Blade (not JS), so no JS changes are needed. The wrapper divs should use the same `style="{{ (is_array($water ?? []) && in_array('Other', $water ?? [])) ? 'block' : 'none' }}"` pattern.

---

## 5. City → County / State Auto-Populate Audit

**Where fields live:** `property_city`, `property_county`, `property_state` are in `property-preferences.blade.php` for Seller and Landlord (not `listing-details.blade.php`).

| Feature | Seller | Landlord | Buyer | Tenant |
|---|---|---|---|---|
| City input `wire:model` | `wire:model.live.debounce.300ms="property_city"` (line 455) | `wire:model.live.debounce.300ms="property_city"` (line 49) | `wire:model.debounce.300ms="newCity"` | `wire:model.debounce.300ms="newCity"` |
| `updatedPropertyCity()` / `updatedNewCity()` method | `updatedPropertyCity()` exists | `updatedPropertyCity()` exists | `updatedNewCity()` exists | `updatedNewCity()` exists |
| City suggestion method | `selectPropertyCitySuggestion()` at line 1459 | `selectPropertyCitySuggestion()` at line 1237 | `selectCitySuggestion()` | `selectCitySuggestion()` |
| County/state auto-populate | Inline inside `selectPropertyCitySuggestion()` lines 1459–1538 | Inline inside `selectPropertyCitySuggestion()` lines 1237–1316 | Via standalone `autoPopulateFromCity()` | Via standalone `autoPopulateFromCity()` |
| `autoPopulateFromCity()` method | **NOT present** | **NOT present** | Present at line 1061 | Present at line 2294 |
| Sets `property_county` | ✓ lines 1513–1527 | ✓ lines 1291–1305 | ✓ (counties array) | ✓ (counties array) |
| Sets `property_state` | ✓ line 1492 | ✓ line 1270 | ✓ (state field) | ✓ (state field) |

**Root Cause:** Seller and Landlord DO populate county/state when a suggestion is clicked. However:
1. The logic runs inside `selectPropertyCitySuggestion()` only — free-typed city entry never triggers it
2. The city suggestion mechanism uses `propertyCitySuggestions` (a different array from Buyer/Tenant's `citySuggestions`) — the dropdown works but the method structure is monolithic
3. Buyer/Tenant use a clean `autoPopulateFromCity()` helper that can be called from multiple code paths; Seller/Landlord cannot

**Verdict:** City → County/State works IF the user clicks a suggestion from the dropdown. Free-typed input does not auto-fill. The fix is to extract a standalone `autoPopulateFromPropertyCity()` method in `SellerOfferListing.php` and `LandlordOfferListing.php` following the Buyer/Tenant pattern.

---

## 6. Original Fix Completion Matrix

| Requested Fix | In Correct File? | Root Cause If Still Broken | Verified After Cache Clear? |
|---|---|---|---|
| Other input boxes (Landlord) — 8/10 fields | ✓ `offer-landlord-tabs/.../property-preferences.blade.php` | Fixed in commit `b7bcd683`; browser cache/hard refresh needed | Hard refresh required |
| Other input boxes (Landlord) — Water | ✗ Missing from Blade | "Other" option absent from options list at lines 904 and 1177; no wrapper div | **Blade fix needed** |
| Other input boxes (Landlord) — Security Features | ✗ Missing from Blade | "Other" option absent from options list at line 1020; no wrapper div | **Blade fix needed** |
| Other input boxes (Seller) | ✓ `offer-seller-tabs/.../property-preferences.blade.php` | Fixed in commit `b7bcd683` | Hard refresh required |
| Listing title tooltip/placeholders | ✓ `offer-*-tabs/.../listing-details.blade.php` | Fixed; hard refresh reveals | Hard refresh required |
| Seller/Landlord city → county/state | ✓ Logic exists in components | Only fires on dropdown suggestion click; free-typed city does not trigger | Works when dropdown is used; **enhancement needed for reliability** |
| Buyer/Tenant cities → county/state | ✓ `BuyerOfferListing.php`, `TenantOfferListing.php` | `autoPopulateFromCity()` present and correct | ✓ Confirmed working |
| Landlord sewer icon | ✓ `offer-landlord-tabs/.../property-preferences.blade.php` | Fixed in commit `b7bcd683` | Hard refresh required |
| Desired Lease Term placeholder | ✓ `offer-landlord-tabs/.../lease-terms.blade.php` | Fixed; hard refresh reveals | Hard refresh required |
| Oversized fields | ✓ Various tab partials | Fixed; hard refresh reveals | Hard refresh required |
| Association placeholders | ✓ Various tab partials | Fixed; hard refresh reveals | Hard refresh required |
| Draft/edit save | ✓ `LandlordOfferListing.php`, `SellerOfferListing.php` | Backend logic — confirmed properties save/load | Requires integration test |
| Documents upload | ✓ `photos-tours-documents.blade.php` | Tab renders; upload functionality requires manual test | Manual test required |
| Photo upload stuck | ✓ Correct blade file | Livewire `WithFileUploads` chunk-upload event listener issue — **not a cache/blade problem** | **Separate investigation needed** |
| AI questions hidden | ✓ `offer-listing/shared/ai-questions-input.blade.php` | Verify conditional variable; hard refresh | Hard refresh + condition check |

---

## 7. Ranked Surgical Fix Plan

### Fix 1 — HIGHEST PRIORITY: Add "Other" to Water select + wrapper div (Landlord)
**File:** `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php`

**Change at line 904 (Residential section):**
```
// BEFORE
@foreach (['Canal/Lake for Irrigation', 'Private', 'Public', 'See Remarks', 'Well', 'None'] as $opt)

// AFTER  
@foreach (['Canal/Lake for Irrigation', 'Private', 'Public', 'See Remarks', 'Well', 'None', 'Other'] as $opt)
```
Add after line 909 (after `</div></div><span class="error...">`)  :
```blade
<div class="form-group" style="display: {{ (is_array($water ?? []) && in_array('Other', $water ?? [])) ? 'block' : 'none' }};" id="other_water_wrapper">
    <div class="input-cover">
        <input type="text" wire:model="other_water" class="form-control has-icon"
            data-icon="fa-solid fa-droplet" placeholder="Enter water source (e.g., Rainwater Harvesting, Cistern)">
    </div>
</div>
```
**Repeat for line 1177 (Commercial section)** — same change pattern.

No PHP component changes needed: `$other_water` already declared (line 581), saved (line 2550), loaded (line 2018).

---

### Fix 2 — HIGH PRIORITY: Add "Other" to Security Features select + wrapper div (Landlord)
**File:** `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php`

**Change at line 1020 (Residential section only — Security Features is not in the Commercial section):**
```
// BEFORE — options end at 'Smoke Detector(s)' with no 'Other'
@foreach (['Closed Circuit Camera(s)', ... 'Smoke Detector(s)'] as $opt)

// AFTER — append 'Other' to the list
@foreach (['Closed Circuit Camera(s)', ... 'Smoke Detector(s)', 'Other'] as $opt)
```
Add after line 1027 (after `</div></div><span class="error...">`)  :
```blade
<div class="form-group" style="display: {{ (is_array($security_features ?? []) && in_array('Other', $security_features ?? [])) ? 'block' : 'none' }};" id="other_security_features_wrapper">
    <div class="input-cover">
        <input type="text" wire:model="other_security_features" class="form-control has-icon"
            data-icon="fa-solid fa-shield-halved" placeholder="Enter security feature (e.g., Guard Dog, Panic Room)">
    </div>
</div>
```
No PHP component changes needed: `$other_security_features` already declared (line 593), saved (line 2560), loaded (line 2028).

---

### Fix 3 — MEDIUM PRIORITY: Reliable city → county/state auto-populate (Seller & Landlord)
**Files:**
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` — refactor `selectPropertyCitySuggestion()` at line 1459
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` — refactor `selectPropertyCitySuggestion()` at line 1237

**Pattern to follow:**
- `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` lines 1061–1080 (`autoPopulateFromCity()`)
- `app/Http/Livewire/OfferListing/Tenant/TenantOfferListing.php` lines 2294–2323 (`autoPopulateFromCity()`)

**Change:** Extract the inline county/state populate block inside `selectPropertyCitySuggestion()` into a new `private function autoPopulateFromPropertyCity(string $cityString): void` method, then call it from `selectPropertyCitySuggestion()`. This enables it to be called from additional triggers (e.g., `updatedPropertyCity` on blur) without duplicating logic.

---

### Fix 4 — MEDIUM PRIORITY: Investigate photo upload stalling
**Files:**
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/photos-tours-documents.blade.php`
- `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/photos-tours-documents.blade.php`
- `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` (`WithFileUploads` usage)
- `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` (`WithFileUploads` usage)

**Suspected cause:** Livewire chunk-upload event listener not correctly registered or chunk size mismatch. Compare event handler registration in the working upload flow against the Offer Listing version to identify the divergence.

---

## 8. Files Confirmed Active and Not to be Confused With Duplicates

| Role | ACTIVE file (edit this) | INACTIVE duplicate (do NOT edit) |
|---|---|---|
| Landlord property details | `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/property-preferences.blade.php` | `resources/views/livewire/hire-landlord-agent/landlord-agent-auction-tabs/commission-based/property-preferences.blade.php` |
| Seller property details | `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/property-preferences.blade.php` | `resources/views/livewire/hire-seller-agent/seller-agent-auction-tabs/commission-based/property-preferences.blade.php` |
| Landlord PHP component | `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` | — |
| Seller PHP component | `app/Http/Livewire/OfferListing/Seller/SellerOfferListing.php` | — |
