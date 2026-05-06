# Create Offer Listing — Manual QA Report

**Date:** 2026-05-06  
**Tester:** Automated Playwright agent (admin@exp.com, user_type=admin)  
**Scope:** All four Create Offer Listing flows — Seller, Landlord, Buyer, Tenant  
**Method:** Live end-to-end browser automation + corroborating static code analysis  
**Status:** ❌ All four flows contain blocking or critical bugs; no flow reached successful submission  

---

## Table of Contents

1. [Test Environment](#1-test-environment)
2. [Results Matrix](#2-results-matrix)
3. [Bug Register — P0 Blockers](#3-bug-register--p0-blockers)
4. [Bug Register — P1 Critical](#4-bug-register--p1-critical)
5. [What Passed (Positive Observations)](#5-what-passed-positive-observations)
6. [View Page Coverage](#6-view-page-coverage)
7. [Services & Broker Compensation — Architecture Audit](#7-services--broker-compensation--architecture-audit)
8. [Cross-Reference: Static Audit Findings F-01 to F-14](#8-cross-reference-static-audit-findings-f-01-to-f-14)
9. [Recommendations & Fix Priority](#9-recommendations--fix-priority)

---

## 1. Test Environment

| Item | Value |
|------|-------|
| Framework | Laravel 8.x + Livewire 2.x |
| Database | PostgreSQL |
| Test account | `admin@exp.com` / `12345678` (user_type = admin) |
| Gate | `offer-playoff` — admin always passes regardless of `allowed_user_ids` |
| Existing draft listings | Seller id=20, Landlord id=5, Tenant id=133 |
| Existing submitted listings | Seller id=25, Landlord id=9, Buyer id=26, Tenant id=134 |
| Screenshots | `/tmp/testing-screenshots/h95vCAm.jpeg` (Seller), `/tmp/testing-screenshots/qQsiUUa.jpeg` (Buyer), `/tmp/testing-screenshots/MJrf2jC.jpeg` (Landlord), `/tmp/testing-screenshots/aueC9i4.jpeg` (Tenant) |

**Routes tested:**

| Role | Create URL | Edit URL |
|------|-----------|----------|
| Seller | `/offer-listing/seller` | `/offer-listing/seller/edit/{id}` |
| Landlord | `/offer-listing/landlord` | `/offer-listing/landlord/edit/{id}` |
| Buyer | `/offer-listing/buyer` | `/offer-listing/buyer/edit/{id}` |
| Tenant | `/offer-listing/tenant` | `/offer-listing/tenant/edit/{id}` |

---

## 2. Results Matrix

Each row is a wizard step. Legend: ✅ Pass · ❌ Fail / Bug · ⚠️ Partial · — Not reached

| Step | Seller | Landlord | Buyer | Tenant |
|------|--------|----------|-------|--------|
| 1. Page load (no 500/403) | ✅ | ✅ | ✅ | ✅ |
| 2. Tab 1 fields visible & correct | ✅ | ✅ | ✅ | ✅ |
| 3. Tab 1 — required fields fillable | ✅ | ✅ | ❌ **BUG-QA-01** | ✅ |
| 4. Phone formatting (oninput) | ✅ | ✅ | — | ✅ |
| 5. Service type picker hidden/correct | ✅ | ✅ | ✅ | ✅ |
| 6. Tab nav → Listing Details | ✅ | ✅ | — | ✅ |
| 7. Auction Type conditional (Bidding Period length field) | ✅ | ✅ | — | ✅ |
| 8. Tab nav → Property Preferences | ✅ | ✅ | — | ✅ |
| 9. City autocomplete (Census API) | ✅ | ✅ | — | — |
| 10. County/State auto-population | ✅ | ✅ | — | — |
| 11. Property Type conditional fields | ✅ | ✅ | — | ✅ |
| 12. Tab nav → Terms tab (Seller/Lease/Purchasing) | ✅ | ✅ | — | ✅ |
| 13. Occupant Type conditional (Occupied Until) | ✅ | ✅ | — | — |
| 14. Tab nav → (Pre-Screening for Tenant) | — | — | — | ✅ |
| 15. Tenant pet conditional sub-fields | — | — | — | ✅ |
| 16. Tab nav → Additional Details | ✅ | ✅ | — | ✅ |
| 17. Tab nav → **Services** | ❌ **BUG-QA-02** | ❌ **BUG-QA-02** | — | ❌ **BUG-QA-03** |
| 18. Tab nav → **Broker Compensation** | ❌ **BUG-QA-02** | ❌ **BUG-QA-02** | — | ❌ **BUG-QA-03** |
| 19. Tab nav → Info / Credentials tab | ✅ | — | — | — |
| 20. Save Draft | ✅ | ❌ **BUG-QA-04** | — | — |
| 21. Resume Draft (edit URL) | ⚠️ Loads | — | — | — |
| 22. Values reload on resume | ✅ | — | — | — |
| 23. Full Submit | — | — | — | — |
| 24. View listing page | — | — | — | — |

> Buyer rows show "—" for most steps because the P0 blocker at Step 3 (current_status exception) halts all further interaction.

---

## 3. Bug Register — P0 Blockers

### BUG-QA-01 · P0 · Buyer — `$current_status` public property missing from component

**Severity:** P0 — Blocker. Entire Buyer Create Offer Listing form is unusable.  
**Found by:** Playwright automated test  
**Screenshot:** `/tmp/testing-screenshots/qQsiUUa.jpeg`

**Symptom:**  
Selecting any option from the "Buyer's Current Status" dropdown on the Buyer Info tab immediately triggers a Livewire `PublicPropertyNotFoundException`. The exception overlay covers the entire page and blocks all further interaction. The form cannot be submitted or drafted.

**Error message observed:**
```
Unable to set component data. Public property [$current_status] not found
on component: [offer-listing.buyer.buyer-offer-listing]
```

**Root cause:**  
The blade partial binds to the property via `wire:model` but the property is never declared in the component class.

| Location | Detail |
|----------|--------|
| Blade (binding) | `resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/buyer-info.blade.php` line 57: `wire:model="current_status"` |
| Component (missing declaration) | `app/Http/Livewire/OfferListing/Buyer/BuyerOfferListing.php` — no `public $current_status` anywhere in the file |

**Fix:** Add `public $current_status = '';` to BuyerOfferListing.php with the other public properties. Also add it to `saveMeta()` and `loadMeta()` calls so it persists and reloads correctly.

---

### BUG-QA-04 · P0 · Landlord — `title` column does not exist in `landlord_agent_auctions`

**Severity:** P0 — Blocker. Landlord Save Draft and Submit both fail with a SQL error.  
**Found by:** Playwright automated test (Save Draft click)  
**Screenshot:** `/tmp/testing-screenshots/MJrf2jC.jpeg`

**Symptom:**  
Clicking "Save Draft" on the Landlord wizard produces an error toast:

```
Error saving draft: SQLSTATE[42703]: Undefined column: 7
ERROR: column "title" of relation "landlord_agent_auctions" does not exist
SQL: insert into "landlord_agent_auctions" ... "title" ... returning "id"
```

**Root cause:**  
The `LandlordOfferListing` component treats the listing title as a native model column (`$auction->title = $this->listing_title`), but the `landlord_agent_auctions` table has no `title` column. In contrast, `seller_agent_auctions` and `buyer_agent_auctions` do have a native `title` column.

| Location | Detail |
|----------|--------|
| Component — save | `app/Http/Livewire/OfferListing/Landlord/LandlordOfferListing.php` line 1570: `$auction->title = $this->listing_title;` |
| Component — submit | Same file, line 3179: `$auction->title = $this->listing_title;` |
| Component — load | Same file, line 1604: `$this->listing_title = $auction->title;` (would fail silently on load if row existed) |
| Database | `landlord_agent_auctions` confirmed via `information_schema.columns`: no `title` column; columns are `id, user_id, auction_type, is_approved, is_draft, is_sold, sold_date, created_at, updated_at, display_bids, auction_ended, listing_id, referring_agent_id, referral_source_code, referral_captured_at, referral_locked` |

**Fix options (pick one, discuss with team):**
- **Option A — Add the column:** Run a migration to add `title VARCHAR NULL` to `landlord_agent_auctions`. Consistent with seller/buyer schema.
- **Option B — Use EAV:** Change all three lines to use `saveMeta('listing_title', $this->listing_title)` and `loadMeta('listing_title')`. Consistent with how Landlord stores all other data (all EAV, no native content columns). Option B is architecturally cleaner for Landlord.

The `pluck('title')` call at line 1048 (draft list modal) would also need updating if Option B is chosen.

---

## 4. Bug Register — P1 Critical

### BUG-QA-02 · P1 · Seller — Services and Broker Compensation tabs entirely absent

**Severity:** P1 — Critical. Sellers cannot configure services or broker compensation through the wizard.  
**Found by:** Playwright automated test (Services tab expected after Description tab; Documents & Disclosures appeared instead)  
**Screenshot:** `/tmp/testing-screenshots/h95vCAm.jpeg`

**Symptom:**  
After completing the Description (Additional Details) tab and clicking Next, the wizard advances to the "Documents & Disclosures" tab rather than the expected "Services" tab. There is no way to reach Services or Broker Compensation via the wizard navigation. A seller cannot select which services they want offered, and no broker compensation structure can be set through the UI.

**Root cause — tab navigation:**  
The Seller Full Service tab navigation (`resources/views/livewire/offer-listing/seller/offer-seller-listing.blade.php`, lines 866–1000) defines these tabs for a Residential (non-financial) listing:

| Index | Tab Label |
|-------|-----------|
| 0 | Listing Details |
| 1 | Property Details |
| 2 | Sale Terms |
| 3 | Description |
| 4 | Tax, Legal & HOA |
| 5 | Documents & Disclosures |
| 6 | Photos & Tours |
| 7 | Agent Credentials & Contact Info |
| 8 | AI Questions |

`Services` and `Broker Compensation` are not present in this list. The `getTabOrder()` JS function dynamically reads the DOM nav tabs, so it also never includes these tabs.

**Root cause — content panes:**  
The wizard blade tab-content section (lines 1123–1300) does not include any `@include` for `offer-seller-tabs.commission-based.services` or `offer-seller-tabs.commission-based.broker-compensation`. Both partial files **do exist** on disk:
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/services.blade.php` ✓
- `resources/views/livewire/offer-listing/offer-seller-tabs/commission-based/broker-compensation.blade.php` ✓

They are simply never wired into the Seller wizard blade.

**Impact:** All existing approved Seller listings (e.g., ids 22–25) were created without any Services or Broker Compensation data being captured through the wizard. Validation rules for these fields (if any) would have been bypassed.

---

### BUG-QA-03 · P1 · Tenant — Services and Broker Compensation tabs absent from wizard

**Severity:** P1 — Critical. Tenants cannot configure services or broker compensation.  
**Found by:** Playwright automated test (After Description tab, Next did not advance to Services; tab list confirmed to lack Services and Broker Compensation)  
**Screenshot:** `/tmp/testing-screenshots/aueC9i4.jpeg`

**Symptom:**  
On the Tenant wizard (`/offer-listing/tenant`), clicking Next from the Description tab does not navigate to Services. The tab navigation rendered in the browser contains only: Listing Details, Property Preferences, Leasing Terms, Pre-Screening, Description, Agent Credentials & Contact Info, AI Questions — **Services and Broker Compensation are absent**.

**Root cause — tab navigation (`$restTabs`):**  
In `resources/views/livewire/offer-listing/tenant/offer-tenant-listing.blade.php`, lines 1708–1718:

```php
if ($user_type === 'tenant') {
    $restTabs = [$firstRest, 'Pre-Screening', 'Description'];
} else {
    $restTabs = [$firstRest, 'Services', 'Description', 'Broker Compensation & Agency Agreement Terms'];
    // ...
}
```

The `tenant` branch omits `'Services'` and `'Broker Compensation & Agency Agreement Terms'`. Non-tenant roles (seller, buyer, landlord) correctly receive both tabs via the `else` branch.

**Root cause — content panes:**  
The shared-wizard content section (lines 1886–1922) includes Services for seller, buyer, and landlord but NOT for tenant:

```php
// Line 1886 — seller services ✓
// Line 1888 — buyer services ✓
// Line 1890 — landlord services ✓
// offer-tenant-tabs.commission-based.services — MISSING ✗

// Line 1918 — seller broker-compensation ✓
// Line 1920 — buyer broker-compensation ✓
// Line 1922 — landlord broker-compensation ✓
// offer-tenant-tabs.commission-based.broker-compensation — MISSING ✗
```

Both partial files exist on disk:
- `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/services.blade.php` ✓
- `resources/views/livewire/offer-listing/offer-tenant-tabs/commission-based/broker-compensation.blade.php` ✓

**Note:** The static audit Appendix B listed the expected Tenant tab count as 8, including Services and Broker Compensation. The actual rendered wizard has only 7 tabs.

---

### BUG-QA-05 · P1 · Buyer — Services pane empty; Broker Compensation pane absent

**Severity:** P1 — Critical (secondary bug behind the P0 `current_status` blocker; will surface once BUG-QA-01 is fixed).  
**Found by:** Static code analysis  

**Root cause:**  
The Buyer wizard blade (`resources/views/livewire/offer-listing/buyer/offer-buyer-listing.blade.php`) includes a `id="services"` tab pane and the Services nav tab, but the `@include` list inside that pane includes services partials for tenant, seller, and landlord only — not buyer:

```
Line 1016: offer-tenant-tabs.commission-based.services   ✓
Line 1018: offer-seller-tabs.commission-based.services   ✓
Line 1020: offer-landlord-tabs.commission-based.services ✓
           offer-buyer-tabs.commission-based.services     ✗ MISSING
```

When `$user_type = 'buyer'`, all three included partials are guarded by `@if($user_type === '...')` conditions that don't match, so the Services pane renders empty.

Additionally, no `@include` for `offer-buyer-tabs.commission-based.broker-compensation` was found in the Buyer blade. The `broker-compensation.blade.php` partial exists on disk but is not wired into the Buyer wizard.

**Files involved:**
- `resources/views/livewire/offer-listing/buyer/offer-buyer-listing.blade.php` — @include section around line 1016
- `resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/services.blade.php` ✓ (exists, not included)
- `resources/views/livewire/offer-listing/offer-buyer-tabs/commission-based/broker-compensation.blade.php` ✓ (exists, not included)

---

### BUG-QA-06 · P1 · Landlord — Services and Broker Compensation panes absent (secondary to P0)

**Severity:** P1 — Critical (secondary to BUG-QA-04; will surface after the SQL error is fixed).  
**Found by:** Static code analysis + Playwright (Playwright reached Photos & Tours tab by skipping expected Services/Broker Comp tabs)

**Root cause:**  
The Landlord wizard blade (`resources/views/livewire/offer-listing/landlord/offer-landlord-listing.blade.php`) contains JavaScript references to Services tab validation (`validateServicesTab()` at lines 2491, 2633) and `'#broker-compensation'` in the tab-order array (line 2932), but the actual `@include` section does not include `offer-landlord-tabs.commission-based.services` or `offer-landlord-tabs.commission-based.broker-compensation` content panes.

As a result, when the Next button's `getTabOrder()` dynamically reads the DOM, it does not find Services or Broker Compensation nav tab buttons (they are absent from the rendered `<ul class="nav nav-tabs">`). Both partial files exist on disk:
- `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/services.blade.php` ✓
- `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/broker-compensation.blade.php` ✓

---

## 5. What Passed (Positive Observations)

These items were confirmed to work correctly during the test runs:

| Check | Roles Verified |
|-------|---------------|
| Page load with no 500 / 403 errors | All 4 ✅ |
| `offer-playoff` gate — admin bypasses correctly | All 4 ✅ |
| Tab 1 (Info tab) renders with correct fields | All 4 ✅ |
| Phone number auto-formatting (`(NNN) NNN-NNNN`) | Seller, Landlord, Tenant ✅ |
| Service type picker correctly hidden (`d-none`) | Seller, Landlord ✅ |
| Service type picker — both cards shown (Buyer only) | Buyer ✅ |
| Service type picker — fully commented out (Tenant only) | Tenant ✅ |
| Auction Type → Bidding Period Length conditional | Seller, Landlord, Tenant ✅ |
| City autocomplete suggestions appear | Seller, Landlord ✅ |
| County/State auto-populate on city selection | Seller, Landlord ✅ |
| Property Type → Residential sub-fields appear | Seller, Landlord, Tenant ✅ |
| Occupant Type → Occupied Until conditional (Seller Sale Terms) | Seller ✅ |
| Occupant Type → Occupied Until conditional (Landlord Lease Terms) | Landlord ✅ |
| Pre-Screening tab exists (Tenant only) | Tenant ✅ |
| Tenant Pets → pet detail sub-fields conditional | Tenant ✅ |
| Save Draft succeeds (Seller) | Seller ✅ |
| Resume Draft — edit URL loads without error | Seller (id=20) ✅ |
| Resume Draft — values pre-populated on load | Seller ✅ |

---

## 6. View Page Coverage

None of the four Playwright tests reached the view page step because all four flows were stopped by bugs earlier in the wizard. View pages at `/offer/listing/view/{id}` were **not tested** in this QA run.

| Role | Listing ID | View URL | Status |
|------|-----------|---------|--------|
| Seller | 25 | `/offer/listing/view/25` | ❌ Not tested (wizard bug stopped run) |
| Landlord | 9 | `/offer/listing/view/9` | ❌ Not tested |
| Buyer | 26 | `/offer/listing/view/26` | ❌ Not tested |
| Tenant | 134 | `/offer/listing/view/134` | ❌ Not tested |

View page testing should be a separate QA pass once wizard bugs are resolved.

---

## 7. Services & Broker Compensation — Architecture Audit

All four role wizard blades are structured as shared wizards (each includes tab partials for all four roles behind `@if ($user_type === '...')` guards). All four roles have `services.blade.php` and `broker-compensation.blade.php` partials on disk. The problem is systematic: these partials are not being wired into the wizard tab navigation or tab content in most blades.

### @include presence matrix for Services and Broker Compensation

| Partial | Seller Blade | Landlord Blade | Buyer Blade | Tenant Blade |
|---------|:-----------:|:-------------:|:-----------:|:------------:|
| seller/services | ✅ in Tenant blade | — | ✅ (line 1018) | ✅ (line 1886) |
| seller/broker-compensation | — | — | — | ✅ (line 1918) |
| landlord/services | — | — | ✅ (line 1020) | ✅ (line 1890) |
| landlord/broker-compensation | — | — | — | ✅ (line 1922) |
| buyer/services | — | — | ❌ **Missing** | ✅ (line 1888) |
| buyer/broker-compensation | — | — | ❌ **Missing** | ✅ (line 1920) |
| tenant/services | — | — | ✅ (line 1016) | ❌ **Missing** |
| tenant/broker-compensation | — | — | — | ❌ **Missing** |

**Key observations:**

1. The **Tenant blade** is the most complete — it includes Services and Broker Compensation for seller, buyer, and landlord, but not for tenant itself.
2. The **Seller blade** includes services only for tenant (via the shared structure) but not for seller, buyer, or landlord.
3. The **Buyer blade** includes services for tenant, seller, and landlord but is missing buyer's own services and broker compensation.
4. The **Landlord blade** has JavaScript infrastructure expecting Services/Broker Comp tabs but no `@include` content for any role's services or broker compensation.

### Tab navigation presence matrix

| Nav Tab | Seller Wizard | Landlord Wizard | Buyer Wizard | Tenant Wizard |
|---------|:------------:|:--------------:|:------------:|:-------------:|
| Services | ❌ Absent | ❌ Absent | ⚠️ Pane exists, buyer partial not wired | ❌ Absent |
| Broker Compensation | ❌ Absent | ❌ Absent | ❌ Absent | ❌ Absent |

---

## 8. Cross-Reference: Static Audit Findings F-01 to F-14

The static code audit (`docs/create-offer-listing-full-audit.md`) catalogued 14 findings. Cross-referencing against live test results:

| Finding | Description | Live Test Status |
|---------|-------------|-----------------|
| F-01 | Typo: `$credit_scroe_rating` (Tenant) | Not tested (live); code confirmed. Low severity — consistent throughout. |
| F-02 | Mismatched `<h3>`/`<h4>` tags (Tenant Leasing Terms) | Not tested live. Cosmetic. |
| F-03 | Blade file name vs heading mismatch (Tenant Tab 3) | Not tested live. Cosmetic. |
| F-04 | Service type picker UI state inconsistency | ✅ Confirmed partially correct: Seller/Landlord hidden, Tenant commented out, Buyer shows both. |
| F-05 | Loading guard flag naming inconsistency | Not tested live. Low severity. |
| F-06 | Buyer uses `wire:model` vs Tenant uses `wire:model.defer` on services | Not testable (Buyer blocked by P0). |
| F-07 | Video file upload commented code remains (Landlord Info) | Not tested live. Dead code. |
| F-08 | Landlord listing-details shows only one service type card | ✅ Confirmed — service type picker area is single-card. |
| F-09 | `$isLoadingDraft` not present in Seller component | Draft resume worked despite this — confirmed no crash. |
| F-10 | Move-In Fund fields stored as both native + EAV (Landlord) | Not testable (Landlord blocked by P0). |
| F-11 | `$screening_concerns` declared without default (Tenant) | Not tested — Pre-Screening tab was navigable but submit not reached. |
| F-12 | Tenant listing-details has legacy landlord-scoped content | Not tested live. Dead code. |
| F-13 | buyer/tenant `additional-details.blade.php` files are identical | Not tested live. Informational. |
| F-14 | Buyer shows both service type cards (potential UX issue) | ✅ Confirmed — both cards visible. Buyer `limited_service` path untested. |

**New findings from live QA run (not in static audit):**

| ID | Finding | Severity |
|----|---------|---------|
| BUG-QA-01 | Buyer `$current_status` public property missing | P0 |
| BUG-QA-02 | Seller Services + Broker Compensation tabs absent (nav + @includes) | P1 |
| BUG-QA-03 | Tenant Services + Broker Compensation tabs absent (nav + @includes) | P1 |
| BUG-QA-04 | Landlord `title` column SQL error on Save Draft / Submit | P0 |
| BUG-QA-05 | Buyer Services partial missing from @includes; Broker Comp pane absent | P1 |
| BUG-QA-06 | Landlord Services + Broker Compensation @includes absent | P1 |

---

## 9. Recommendations & Fix Priority

### Immediate — Fix before any further QA

| Priority | Bug | Effort | Fix Description |
|----------|-----|--------|----------------|
| P0 #1 | BUG-QA-01 Buyer `$current_status` | XS | Add `public $current_status = '';` to `BuyerOfferListing.php`; wire into `saveMeta`/`loadMeta` |
| P0 #2 | BUG-QA-04 Landlord `title` column | S | Choose Option A (migration: add `title` column) or Option B (use EAV `saveMeta`); update lines 1048, 1570, 1604, 3179 |

### Next — Required for core workflow completeness

| Priority | Bug | Effort | Fix Description |
|----------|-----|--------|----------------|
| P1 #1 | BUG-QA-02 Seller: Services + Broker Comp absent | M | Add Services and Broker Compensation nav tabs to Seller blade (lines 866–1000); add the corresponding `@include` panes with appropriate `$activeTab` index values |
| P1 #2 | BUG-QA-03 Tenant: Services + Broker Comp absent | M | In Tenant blade `$restTabs` (line 1708): add `'Services'` and `'Broker Compensation & Agency Agreement Terms'` to the `tenant` branch; add corresponding `@include` calls for `offer-tenant-tabs.commission-based.services` and `.broker-compensation` |
| P1 #3 | BUG-QA-05 Buyer: Services partial missing | S | Add `@include('livewire.offer-listing.offer-buyer-tabs.commission-based.services')` inside the Buyer blade Services pane; add Broker Compensation pane + `@include` |
| P1 #4 | BUG-QA-06 Landlord: Services + Broker Comp panes absent | M | Add Services and Broker Compensation nav tabs to Landlord blade; wire in `@include` for landlord-specific partials |

### After P0+P1 fixes — Re-run this QA pass

Once all six bugs above are resolved:
1. Re-run all four Playwright flows end-to-end
2. Confirm Save Draft → Resume Draft cycle for all roles
3. Confirm full Submit for all roles
4. Test view page (`/offer/listing/view/{id}`) for all roles
5. Test Services and Broker Compensation field persistence specifically
6. Test limited_service path for Buyer (F-14 concern)

### Longer-term

- Consolidate the four wizard blades into a single shared blade (the Tenant blade is the closest to a complete shared template); eliminate the pattern of each blade duplicating @includes for all other roles
- Address static audit items F-01 through F-14 (mostly low-severity: typos, dead code, naming inconsistencies)
- Add a `public $current_status` audit across all four components to verify no other similar missing-property bugs remain

---

*Report generated from two rounds of parallel Playwright automation (Seller+Buyer, Landlord+Tenant) and corroborating static analysis of blade files and PHP components. Total partials examined: 36 blade files + 4 root blades + 4 Livewire Create components.*
