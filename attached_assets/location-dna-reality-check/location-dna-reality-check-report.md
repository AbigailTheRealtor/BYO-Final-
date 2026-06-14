# Location DNA Reality Check Audit Report

**Date:** 2026-06-14  
**Auditor:** Agent (automated — no application code modified)  
**Scope:** Location Preferences Map section on Buyer Criteria and Tenant Criteria — Create, Edit, and Public View pages (6 pages total)

---

## Overall Audit Verdict: FAILED

All six pages FAIL at runtime. No page was reachable and functional for the intended user role during the audit.

---

## Screenshot Index

All six screenshots are authentic live captures from the running application — no server-rendered HTML was used.

| Filename | Route Accessed | What the Live App Returned |
|----------|---------------|---------------------------|
| `buyer-create-map.png` | `GET /buyer-agent/auction/add` | Login redirect (auth middleware) |
| `tenant-create-map.png` | `GET /tenant/criteria/auction/add` | Login redirect (auth middleware) |
| `buyer-edit-map.png` | `GET /buyer-agent/auction/edit/{id}` | Login redirect (auth middleware) |
| `tenant-edit-map.png` | `GET /tenant/criteria/auction/edit/{id}` | Login redirect (auth middleware) |
| `buyer-public-map.png` | `GET /criteria/view/1` | HTTP 500 — `ErrorException: Attempt to read property "id" on null` at `buyer_criteria/view.blade.php:253` |
| `tenant-public-map.png` | `GET /tenant/criteria/auction/view/1` | HTTP 500 — `ErrorException: Attempt to read property "id" on null` at `tenant_criteria/view.blade.php:108` |

---

## Page-by-Page Verdicts

### Page 1: Buyer Criteria Create — **FAIL**

**Route:** `GET /buyer-agent/auction/add`  
**Runtime observation:** The route requires authentication. When accessed without a session, the server redirects to `/login`. Screenshot `buyer-create-map.png` shows the Bid Your Offer Sign In page — the live server's response. The create page itself was not reachable without an authenticated session.

**Infrastructure blocker (code inspection):** Even if authenticated, any submission would crash. The `buyer_criteria_auction_metas` table does not exist in this environment. The `saveMeta()` call after the main record save immediately throws `SQLSTATE[42P01]: Undefined table: relation "buyer_criteria_auction_metas" does not exist`. Confirmed: `buyer_criteria_auctions` contains 0 rows — no submission has ever succeeded.

---

### Page 2: Tenant Criteria Create — **FAIL**

**Route:** `GET /tenant/criteria/auction/add`  
**Runtime observation:** The route requires authentication. Live response for unauthenticated access is a redirect to `/login`. Screenshot `tenant-create-map.png` shows the Sign In page.

**Infrastructure blocker (code inspection):** The `tenant_criteria_auctions` table does not exist in this environment. No CREATE migration exists — only ALTER migrations (each with a `Schema::hasTable()` guard and a note that the table has no standalone CREATE migration). Any authenticated submission would throw `SQLSTATE[42P01]: Undefined table: relation "tenant_criteria_auctions"` before any data is persisted. Zero rows exist in this table.

---

### Page 3: Buyer Criteria Edit — **FAIL**

**Route:** `GET /buyer-agent/auction/edit/{id}`  
**Runtime observation:** The route requires authentication. Live response for unauthenticated access is a redirect to `/login`. Screenshot `buyer-edit-map.png` shows the Sign In page.

**Infrastructure blocker (code inspection):** Even if authenticated, `BuyerCriteriaAuction::findOrFail($id)` triggers the model's `$with = ['meta']` eager-load, which queries `buyer_criteria_auction_metas` — a table that does not exist — and crashes. Additionally, there are 0 buyer criteria records to edit.

---

### Page 4: Tenant Criteria Edit — **FAIL**

**Route:** `GET /tenant/criteria/auction/edit/{id}`  
**Runtime observation:** The route requires authentication. Live response for unauthenticated access is a redirect to `/login`. Screenshot `tenant-edit-map.png` shows the Sign In page.

**Infrastructure blocker (code inspection):** The `tenant_criteria_auctions` table does not exist. Any authenticated visit would crash immediately. Zero records exist.

---

### Page 5: Buyer Criteria Public View — **FAIL**

**Route:** `GET /criteria/view/1`  
**Runtime observation:** The route is publicly accessible (no auth middleware). Live response: HTTP 500. Screenshot `buyer-public-map.png` shows the Laravel exception page:

```
ErrorException
Attempt to read property "id" on null
(View: .../resources/views/buyer_criteria/view.blade.php)
Stack trace frame: resources/views/buyer_criteria/view.blade.php:253
```

**Code bug confirmed at runtime:** Line 253 of `buyer_criteria/view.blade.php` reads:

```php
@if ($auction->user_id == auth()->user()->id)
```

`auth()->user()` returns `null` for unauthenticated visitors, causing the null property access. This is a live crash for any unauthenticated public visitor. The correct guard — `auth()->check() && $auction->user_id == auth()->id()` — is used on line 262 of the same file but was not applied to line 253.

This crash occurs even after the DB infrastructure is fixed, for all public visitors who are not logged in.

---

### Page 6: Tenant Criteria Public View — **FAIL**

**Route:** `GET /tenant/criteria/auction/view/1`  
**Runtime observation:** The route is publicly accessible. Live response: HTTP 500. Screenshot `tenant-public-map.png` shows the Laravel exception page:

```
ErrorException
Attempt to read property "id" on null
(View: .../resources/views/tenant_criteria/view.blade.php)
Stack trace frame: resources/views/tenant_criteria/view.blade.php:108
```

**Code bug confirmed at runtime:** Line 108 of `tenant_criteria/view.blade.php` has the same null-auth crash:

```php
@if ($auction->user_id == auth()->user()->id)
```

Same fix required: `@if (auth()->check() && $auction->user_id == auth()->id())`.

---

## Root Causes Summary

### 1. Missing Database Tables (Infrastructure — all 6 pages)

Four tables are absent. There are no `CREATE TABLE` migration files for any of them. This means authenticated users also cannot create or view Buyer/Tenant Criteria records.

| Missing Table | Effect |
|--------------|--------|
| `buyer_criteria_auction_metas` | Buyer criteria saves, loads, and eager-loads crash |
| `tenant_criteria_auctions` | Entire Tenant Criteria system non-functional |
| `tenant_criteria_auction_metas` | Would fail once the main table is created |
| `tenant_criteria_auction_bids` | Tenant public view crashes loading bids |

### 2. Null-Auth Crash on Both Public View Pages (Code Bug)

Both `buyer_criteria/view.blade.php:253` and `tenant_criteria/view.blade.php:108` call `auth()->user()->id` without an `auth()->check()` guard. This crashes for every unauthenticated visitor regardless of DB state.

---

## Code-Level Observations (Code Inspection — Not Runtime Verified)

The following were found via code inspection of the blade templates and controllers. They were **not observable at runtime** during this audit because all pages were blocked by the failures above. These are provided for completeness; they become verifiable only after the blockers above are fixed.

| Item | File / Location | Finding |
|------|----------------|---------|
| `map-input` partial included on Buyer Create | `buyer_criteria/add.blade.php:439` | `@include('partials.location-dna.map-input', ...)` present |
| `map-input` partial included on Tenant Create | `tenant_criteria/add.blade.php:427` | Same partial included |
| `map-input` partial included on Buyer Edit | `buyer_criteria/edit.blade.php:439` | `$existingLocationDna` passed in |
| `map-input` partial included on Tenant Edit | `tenant_criteria/edit.blade.php:427` | `$existingLocationDna` passed in |
| Google Maps API load with `libraries=places,drawing` | Buyer add/edit lines 8315, 8327; Tenant add/edit lines 3480, 3498 | Correct library combination for drawing tools |
| JSON validation before `saveMeta()` | Both controllers | `json_last_error() === JSON_ERROR_NONE` guard present |
| `existingLocationDna` passed from controller on edit | Buyer controller lines 401–402; Tenant controller lines 322–323 | Controller reads `info('location_dna_preferences')` and passes result |
| `<x-location-dna-map>` on Buyer Public View | `buyer_criteria/view.blade.php:273` | Component present with props |
| `<x-location-dna-map>` on Tenant Public View | `tenant_criteria/view.blade.php:128` | Component present with props |

---

## Required Fixes Before Re-Audit

1. **Write and run CREATE migrations** for all four missing tables.

2. **Fix null-auth crash in both public view pages:**

   `resources/views/buyer_criteria/view.blade.php` line 253 — replace:
   ```php
   @if ($auction->user_id == auth()->user()->id)
   ```
   with:
   ```php
   @if (auth()->check() && $auction->user_id == auth()->id())
   ```

   `resources/views/tenant_criteria/view.blade.php` line 108 — same replacement.

3. **Re-audit all 6 pages** with an authenticated session once the DB and code bugs are fixed, to verify the Location Preferences Map section renders, drawing tools function, and save/reload works end-to-end.
