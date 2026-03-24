# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to streamline property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to enhance transparency and efficiency in the real estate market by offering functionalities for listing properties, managing bids, and facilitating agent interactions.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## REQUIRED WORKFLOW BEFORE EVERY FIX

Before editing anything, first confirm:

1. The exact route/URL being tested
2. Which flow type it is: **dedicated create**, **shared create**, or **shared edit**
3. The exact active Livewire component
4. The exact PHP class
5. The exact Blade view
6. The included partials
7. The exact files to edit for that path only

Do not start coding until this is confirmed. Respond with:

- **Route:**
- **Flow type:** dedicated create / shared create / shared edit
- **Livewire component:**
- **PHP class:**
- **Blade view:**
- **Included partials:**
- **Files to edit:**

---

## Confirmed Architecture Map

### Three path types exist for each role:

| Flow type | Description |
|---|---|
| **Dedicated create** | Role-specific component used when the logged-in user is that role type |
| **Shared create** | `TenantAgentAuction` component serving any role via URL parameter |
| **Shared edit** | `TenantAgentAuctionEdit` component for all roles |

---

### Tenant

- **Primary create path:** `/hire/agent/auction/tenant` → `TenantAgentAuction` → `tenant-agent-auction.blade.php`
- **Partials:** `tenant-agent-auction-tabs/commission-based/` (listing-details, property-details, leasing-terms, services, additional-details)
- **Edit path:** `/hire/agent/auction/edit/{id}/tenant` → `TenantAgentAuctionEdit` → `tenant-agent-auction-edit.blade.php`
- **Note:** Tenant has no separate dedicated component — `TenantAgentAuction` IS the primary

---

### Buyer

- **Dedicated create path (buyer-type users):** `/buyer/add-auction` → `BuyerAgentAuction` → `hire-buyer-agent.blade.php`
- **Shared create path (other user types):** `/hire/agent/auction/buyer` → `TenantAgentAuction` → `tenant-agent-auction.blade.php`
- **Partials:** `hire-buyer-agent/buyer-agent-auction-tabs/commission-based/` (listing-details, property-preferences, purchasing-terms, services, additional-details)
- **Edit path:** `/hire/agent/auction/edit/{id}/buyer` → `TenantAgentAuctionEdit` → `tenant-agent-auction-edit.blade.php`
- **PHP classes:** `app/Http/Livewire/HireBuyerAgent/BuyerAgentAuction.php` (dedicated), `TenantAgentAuction.php` (shared)

---

### Seller

- **Dedicated create path (seller-type users):** `/hire/agent/seller` → `SellerAgentAuction` → `hire-seller-agent.blade.php`
- **Shared create path (other user types, e.g. tenant accounts):** `/hire/agent/auction/seller` → `TenantAgentAuction` → `tenant-agent-auction.blade.php`
- **Partials (both paths use same partials):** `hire-seller-agent/seller-agent-auction-tabs/commission-based/` (listing-details, property-preferences, **seller-terms**, services, additional-details)
- **Edit path:** `/hire/agent/auction/edit/{id}/seller` → `TenantAgentAuctionEdit` → `tenant-agent-auction-edit.blade.php`
- **PHP classes:** `app/Http/Livewire/HireSellerAgent/SellerAgentAuction.php` (dedicated), `TenantAgentAuction.php` (shared)
- **Nav links:** Header for seller-type users → dedicated path. Header tenant dropdown + dashboard → shared path.

---

### Landlord

- **Dedicated create path (landlord-type users):** `/landlord/hire/agent/auction` → `LandLordAgentAuction` → `hire-landlord-agent.blade.php`
- **Dedicated edit path (landlord-type users):** `/landlord/hire/agent/auction/edit/{id}` → `LandLordAgentAuctionEdit` → `hire-landlord-agent-edit.blade.php`
- **Shared create path (other user types):** `/hire/agent/auction/landlord` → `TenantAgentAuction` → `tenant-agent-auction.blade.php`
- **Shared edit path (confirmed URL from browser):** `/hire/agent/auction/edit/{id}/landlord` → `TenantAgentAuctionEdit` → `tenant-agent-auction-edit.blade.php`
- **Partials (both create paths use same partials):** `hire-landlord-agent/landlord-agent-auction-tabs/commission-based/` (listing-details, property-preferences, lease-terms, services)
- **PHP classes:** `app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php` (dedicated), `TenantAgentAuction.php` (shared create), `TenantAgentAuctionEdit.php` (shared edit)
- **Desired Lease Term field:** uses class `lease_term_options` (NO id attribute) — selector must be `.lease_term_options`, never `#desired_lease_length`

---

## Legacy File Rule

Only these should be treated as legacy — do NOT confuse alternate active flows with legacy:
- `*.blade copy.php` files
- `*.blade copy 2.php` files
- Files with explicit deprecation comments

Active files that look similar but serve different paths (e.g. `hire-seller-agent.blade.php` vs `tenant-agent-auction.blade.php`) are NOT legacy — both are active.

---

## System Architecture

### UI/UX Decisions
The platform uses Laravel Mix with TailwindCSS and AlpineJS for a responsive UI, complemented by Livewire for dynamic content. Bootstrap handles tab navigation. Location autofill uses a U.S. Census-based local database. Listings feature unique IDs and pill badges. A "Broker Compensation & Agency Agreement Terms" form standardizes agent workflows.

### Technical Implementations
The platform is built on Laravel 8.x, PHP 8.2.23, and PostgreSQL, with Node.js v20 for asset compilation. A shared `TenantAgentAuction.php` Livewire component processes all agent type form submissions, managing submission, draft saving/loading, and metadata persistence. Drafts use an append-only versioning system. Model resolution dynamically selects the correct auction model. Numeric inputs are sanitized, and configuration files centralize display logic. The `ServicesFormatter` groups buyer agent services into canonical categories, while `ListingDisplayHelper` standardizes field formatting. Conditional rendering of fields is managed based on user selections and listing types. PDF listing packets are generated using `barryvdh/laravel-dompdf`, driven by dedicated field map classes and a universal Blade template. Select2 and Livewire integration handles dynamic and static multi-select fields, using `wire:ignore` where appropriate and providing robust initialization and repair mechanisms. A JSON Bridge Pattern synchronizes data for certain buyer multi-select fields between Select2 and Livewire properties. Wizard navigation employs a delegation pattern for tab transitions and validation across Livewire re-renders. Address fields dynamically show/hide based on data presence. File uploads (photos/videos) utilize `wire:key` for stabilization and event delegation for validation. Video links are embedded as iframes or displayed as clickable links. Required field validations and select2 stabilization for property-type-dependent fields are carefully managed.

### Seller Sale Terms Conditional Visibility (both paths)
`applySellerProvisionVisibility()` and `applySellerFinancingVisibility()` handle immediate show/hide for all dependent sections. Both functions exist in `hire-seller-agent.blade.php` (dedicated path) and `tenant-agent-auction.blade.php` (shared path). The `@this.set()` / `debouncedSet()` pattern syncs values to Livewire so server-side re-renders keep sections visible.

### Edit-Form Validation Parity & Immutable Fields
All shared-edit routes (`/hire/agent/auction/edit/{id}/{role}`) use `TenantAgentAuctionEdit` / `tenant-agent-auction-edit.blade.php`. Key rules implemented:
- **Three action buttons:** "Save Draft" (grey/outline) → `saveDraftOnly()`, no validation, no redirect, green toast; "Save Edit" (blue) and "Submit" (green) → `doSaveEditWithSync()`, full validation, redirect on success.
- **`doSaveEditWithSync()`** runs `editGetInvalidItemsFull()` (checks all tabs) before calling `update()`. Both "Save Edit" and "Submit" use this path.
- **`doSaveDraftWithSync()`** only syncs Select2 then calls `saveDraftOnly()` — skips all required-field validation so partial saves are always allowed.
- **`editGetInvalidItemsFull()`** validates required fields on ALL tabs. It uses `.closest('.tab-pane')` DOM traversal to distinguish Bootstrap tab hiding (ignored) from conditional blade-hidden fields (respected).
- **PHP server-side validation in `update()`** — checks `listing_title`, `listing_date`, `expiration_date`, `meeting_Preference` as a bypass safety net. Dispatches `edit-validation-failed` browser event if any are missing; skipped when `$_isDraftSave` is true.
- **`saveDraftOnly()`** — sets `$_isDraftSave = true`, calls `update()` (which skips PHP validation and redirect), then resets the flag. Dispatches `draft-saved` browser event on success.
- **`$isEditMode = true`** is set in the shared-edit blade BEFORE the `@if ($user_type === ...)` branch so it applies to all 4 roles' listing-details includes. Do not move it back inside the tenant-only branch.
- **Locked fields** — `Listing Type` and `Current Representation Status with Broker` — render as disabled text inputs with a red lock notice when `$isEditMode` is set. Each has a `.locked-field-overlay` div (transparent, z-index 2, inset 0) so clicks can be intercepted even though the underlying input is disabled.
- **Locked field click handler** — a global `click` listener on `.locked-field-overlay` shows a `.locked-click-notice` red alert below the field with the field-specific message. Auto-hides after 5 s.
- **Server-side immutability** — `working_with_agent`: re-read from DB before saving; `auction_type` / `auction_time`: commented out of `saveMeta()` call so client submissions are ignored.
- **PHP safety-net validation** (non-draft only) checks: `listing_title`, `listing_date`, `expiration_date`, `meeting_Preference`, `first_name`, `last_name`, `phone_number`, `email`. Dispatches `edit-validation-failed` browser event on any missing field.
- **`getAllRequiredFields()` JS function** uses `CURRENT_USER_TYPE` to build the correct info-tab selector (`#{role}-information`) so First Name/Last Name/Phone/Email are validated on edit submit for all 4 roles.
- **No Save Draft button on edit pages** — removed from `tenant-agent-auction-edit.blade.php`. Save Draft remains on create pages only.
- **Caution:** Avoid putting `@directive` keywords (e.g. `@if`, `@this`) inside `<script>` comments — Blade processes them everywhere and will emit a parse error. Rephrase or use `@@`.

### Bidding Period Timer (Seller / Buyer / Landlord)
All three listing-view pages (`hire_seller_agent/view.blade.php`, `hire_landlord_agent/view.blade.php`, `buyerAgentAuctionDetail.blade.php`) now mirror the Tenant reference implementation for timer logic:
- `$listingType`, `$isTraditionalListing`, `$isBiddingPeriodListing` are derived from `$auction->get->auction_type`.
- `$expiration` is calculated from `auction_time` duration only when `$isBiddingPeriodListing` is true. Otherwise, `expiration_date` is used for listing lifecycle only.
- `$isBiddingTimerActive` gates the visible countdown widget; `$canTakeAction` gates action buttons.
- The countdown timer (`div.time` / `timer-d` / `timer-h` etc.) is wrapped in `@if ($isBiddingPeriodListing)` — Traditional listings render neither the timer nor "Bidding Ended".
- "Bidding Ended" alert only shows inside the Bidding Period branch when the period has elapsed.
- `.bak` copies of all three files were made before editing.
- Tenant view (`hire_tenant_agent/view.blade.php`) is the source of truth; do not change it.

### System Design Choices
The architecture emphasizes modularity, clear separation of concerns, and a database-first approach utilizing local database solutions. The system is optimized for production deployment, and the existing database schema for fees is immutable, with display-only formatting updates.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: Utility-first CSS framework.
- **AlpineJS**: Lightweight JavaScript framework.
- **Laravel Mix**: Wrapper for Webpack to compile assets.
- **U.S. Census Gazetteer Files**: Source for geographical data.
- **Google Places API**: Used for address and postal code validation.
- **barryvdh/laravel-dompdf**: Library for PDF generation from HTML in Laravel.
