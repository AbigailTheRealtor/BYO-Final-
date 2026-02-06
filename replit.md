# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to revolutionize property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline real estate transactions by offering robust functionalities for listing properties, managing bids, and facilitating agent interactions, ultimately enhancing transparency and efficiency in the real estate market.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
The platform utilizes Laravel Mix with TailwindCSS and AlpineJS for a responsive and dynamic user interface. Livewire is central to handling dynamic content, enabling real-time UI updates and auto-population. Tab navigation is managed using Bootstrap, featuring safe slug generation for tab IDs. Location autofill leverages a cost-effective, U.S. Census-based local database. Listing displays feature unique IDs as badges and pill badges for actions. A "Broker Compensation & Agency Agreement Terms" form serves as the canonical UI source for all Agent workflows, ensuring consistency in field structure, input types, conditional logic, and formatting.

### Technical Implementations
Built on Laravel 8.x with PHP 8.2.23 and PostgreSQL, the platform uses Node.js v20 for asset compilation.

**CRITICAL - Shared Livewire Component Architecture**: The `TenantAgentAuction.php` component (`app/Http/Livewire/TenantAgentAuction.php`) is the **primary shared component** that processes form submissions for **ALL agent types**: Buyer, Seller, Landlord, and Tenant. This component handles:
- Form submission via `store()` method
- Draft saving via `saveDraft()` method  
- Draft loading via `loadDraft()` method (with fallback to search all models)
- Metadata persistence via `saveAllMetadata()` method
- The `user_type` property determines which model/table is used (HireBuyerAgentAuction, HireSellerAgentAuction, HireLandLordAgentAuction, HireTenantAgentAuction)

**CRITICAL - Mount Parameter Order**: The `mount($user_type = null, $listingId = null)` method receives `user_type` as the FIRST parameter because Livewire maps route parameters positionally. The route `/hire/agent/auction/{user_type?}` passes user_type as the first segment, so it must be the first mount parameter. Changing this order will break draft discovery for non-tenant agent types.

**Model Resolution Pattern**: All methods that interact with auction models (store, saveDraft, loadDraft, hasDrafts, getDrafts) use a consistent match statement to resolve the correct model class based on user_type:
```php
$modelClass = match ($this->user_type) {
    'tenant'   => HireTenantAgentAuction::class,
    'landlord' => HireLandLordAgentAuction::class,
    'buyer'    => HireBuyerAgentAuction::class,
    'seller'   => HireSellerAgentAuction::class,
    default    => HireTenantAgentAuction::class,
};
```
The loadDraft() method includes a fallback that searches all models if the draft isn't found in the primary table, ensuring backward compatibility for legacy drafts.

**When adding new fields for ANY agent type** (including Buyer), you MUST add:
1. Public property declaration in `TenantAgentAuction.php`
2. `saveMeta()` call in `saveAllMetadata()` method of `TenantAgentAuction.php`
3. Field loading in `loadDraftData()` method of `TenantAgentAuction.php`
4. Wire:model binding in the Blade view

**NOTE**: Files in `app/Http/Livewire/HireBuyerAgent/` (BuyerAgentAuction.php, BuyerAgentAuctionEdit.php) are OLD/LEGACY files and should NOT be edited. The active component is `TenantAgentAuction.php`

**LEGACY FILES - DO NOT EDIT** (applies to ALL agent types):
- `app/Http/Livewire/HireBuyerAgent/` - Old Buyer files
- `app/Http/Livewire/HireSellerAgent/` - Old Seller files (if exists)  
- `app/Http/Livewire/HireLandlordAgent/` - Old Landlord files (if exists)
- Any standalone agent-specific Livewire components outside of `TenantAgentAuction.php`

**ALWAYS USE**: `TenantAgentAuction.php` and `TenantAgentAuctionEdit.php` for Buyer, Seller, Landlord, and Tenant agent forms. Unique listing IDs are generated via a `HasListingId` trait. Location data is sourced from U.S. Census databases. Dynamic forms are managed by Livewire components, handling conditional rendering, financing options, and property preferences with standardized input fields, required indicators, phone auto-formatting, and dynamic visibility. Fee displays are formatted for presentation without altering storage. Defensive guards are implemented in Livewire methods. Bid behavior differentiates between "Traditional" and "Bidding Period" listings, affecting timers and bid visibility. Agent bid fields are auto-filled, and agent information is preserved during edits. The system supports cross-profile listing for all agent types (Tenant, Seller, Buyer, Landlord), with dedicated tables and models for each. Draft functionality is supported across all agent types. Owner-specific visibility ensures listing owners can view their unapproved listings.

**View Preference "Other" Visibility**: All four agent types (Buyer, Seller, Landlord, Tenant) use Livewire computed properties (`getIsOtherVisibleProperty()`, `getIsOtherNonNegotiableVisibleProperty()`) to automatically toggle visibility of "Other" text fields based on array values. This ensures consistent behavior across draft save/load cycles without manual state management. Blade files reference `$this->is_other_visible` which invokes the computed getter.

**Select2 Draft Sync**: Buyer forms emit a `buyer-agent-select2-sync` browser event during draft load containing all Select2 multiselect array values (view_preference, non_negotiable_amenities, offered_financing, services, etc.). JavaScript listeners hydrate the Select2 elements with `.val([...]).trigger('change')` to sync DOM state with Livewire. A global `window.financingSyncInProgress` flag prevents Livewire sync during Select2 hydration to avoid triggering the `updatedOfferedFinancing()` reset logic.

**Financing Follow-Up Field Persistence**: The `updatedOfferedFinancing()` method in both Create and Edit Livewire components uses a smart selective reset mechanism:
1. `$previousOfferedFinancing` property tracks the previous financing type selections
2. When financing types change, only fields for REMOVED types are reset (using `financingFieldMap`)
3. The `$isLoadingData` flag prevents reset during initial draft/edit load (updates snapshot before returning)
4. The `financingFieldMap` maps each financing type to its dependent fields (70+ fields across 9 financing types)
5. If financing values are unchanged (re-sync), no reset occurs
This ensures follow-up fields (amortization type, payment frequency, cryptocurrency wallet, NFT transfer method, etc.) persist correctly across all 5 property types during create → draft → reload → edit → publish flows.

**Draft Load Protection Pattern**: The `$isLoadingData` flag in BuyerAgentAuction.php protects dependent field values from being reset during draft loading. All Livewire `updated*` hooks that reset dependent fields must check this flag and return early if true. Protected hooks include: `updatedSaleProvision()`, `updatedSaleProvisionAssignment()`, `updatedBuyerSellContract()`, `updatedPurchaseFeeType()`, `updatedLeaseFeeType()`, and `updatedOfferedFinancing()`. The flag is set at the start of `loadDraft()` and cleared at the end, ensuring hooks function normally for user interactions after loading completes.

**Meta Field Parity**: Create and Edit Livewire components must maintain identical field lists for both saving (saveAllMetadata) and loading (loadDraftData/loadAuctionData). When adding new meta fields, ensure they are: (1) defined as public properties in both components, (2) saved via saveMeta() in both components, (3) loaded with defensive null-guarded hydration patterns in both components. Example pattern for array fields: `$raw = $auction->get->field ?? null; $this->field = $raw ? (is_string($raw) ? json_decode($raw, true) ?? [] : (array)$raw) : [];`

**Numeric Field Storage (stripCommas)**: All numeric input fields (budgets, amounts, fees, prices, percentages, square footage, rates) use a `stripCommas()` helper method in both Create and Edit Livewire components to remove comma formatting before database storage. This ensures clean numeric data storage while allowing comma-formatted display in forms. The helper handles null/empty values gracefully: `protected function stripCommas($value) { if ($value === null || $value === '') { return $value; } return str_replace(',', '', $value); }`. Applied to 40+ fields including: maximum_budget, cash_budget, pre_approval_amount, purchase_price, interest_rate, lease_fee_flat, purchase_fee_flat, all limited service fees, etc.

**Tab Navigation Guard Pattern**: Both Create (`tenant-agent-auction.blade.php`) and Edit (`tenant-agent-auction-edit.blade.php`) wizards use an `isNavigating` guard flag in their `goToNextTab()` / `goToNextEditTab()` functions to prevent double-firing from multiple event listeners. Both use ID-based tab order derived from DOM nav-links via `getTabOrder()` / `getEditTabOrder()`, and navigate using `bootstrap.Tab.getOrCreateInstance().show()` (NOT `.click()`) to avoid triggering `wire:click` handlers on nav-link buttons. The guard is set at navigation start and released after a 100ms timeout.

**Services Snapshot & Canonicalization**: TenantServicesCatalog.php uses a `canon()` helper method to normalize smart quotes (curly apostrophes) to straight quotes for comparison, while preserving the display text with curly quotes. This ensures `in_array()` checks work correctly when comparing user-selected services against the canonical catalog. The Blade form labels use curly apostrophes ('), and the catalog strings match exactly.

**Property-Type Field Enforcement**: Certain fields are Residential-only (pool_needed, pool_type, carport_needed, carport_spaces, ADU in leasing_spaces_tenant, all pets fields). Commercial property types must not display or persist these. ADU is filtered: (1) UI hides the option for Commercial, (2) Edit component mount removes ADU from saved data if property_type is Commercial, (3) Display view filters ADU from output for Commercial listings.

All listing types (Tenant, Buyer, Seller, Landlord) use dedicated tables and models and have independent submission redirects to their respective detail pages. Tab visibility ensures owners see their drafts across all tabs, while non-owners only see published, approved listings. Validation rules are synchronized between frontend and backend, with conditional logic for different auction types. Listing display views (Seller, Buyer, Landlord) are normalized to match the Tenant view, including consistent formatting, section styling, categorized services, and "Broker Compensation & Agency Agreement Terms" sections with value-first display formats. "Other" options are cleaned up, occupant types are clearly displayed, and comprehensive sub-questions for offered financing/currency are integrated. Lease-Option compensation and form structures are standardized across all agent types, with conditional currency symbols and input formatting. Service text for commercial listings is normalized, and a display-layer fallback maps legacy data. Buyer Broker Compensation structure is standardized, and phone numbers auto-format while stripping non-digits for database storage. Brokerage Relationship sections consistently use disc bullet points.

**Landlord Agent "Other" Visibility Pattern**: The `LandLordAgentAuction.php` component (`app/Http/Livewire/HireLandLordAgent/LandLordAgentAuction.php`) uses visibility flag properties (`$is_other_tenant_pay_visible`, `$is_other_owner_pays_visible`, `$is_rent_include_visible`, `$showOtherAppliances`) to toggle "Other" custom text fields. Select2 change handlers call Livewire update methods (`updateTenantPays`, `updateOwnerPays`, `updateRentIncludes`, `updateAppliances`) which set these flags. During draft loading, flags are initialized from loaded array values using `in_array('Other', $array)`. The select elements require `id` attributes (`id="tenant_pays"`, `id="owner_pays"`, `id="rent_includes"`, `id="appliances"`) for jQuery Select2 targeting. The view page (`resources/views/hire_landlord_agent/view.blade.php`) filters "Other" from displayed lists and renders custom text from `other_tenant_pays`, `other_owner_pays`, `other_rent_include`, `other_appliances`, `custom_lease_term`, and `other_lease_term` meta fields. The `deletePhoto()` method handles photo deletion from storage and metadata.

### System Design Choices
The architecture emphasizes modularity through Laravel's structure and Livewire components. A database-first approach prioritizes local database solutions for core services like location. Clear separation of concerns is maintained between frontend, backend, and data persistence. The system is deployment-ready with production environment optimizations. Existing database schema and storage logic for fees are immutable, with fee format updates being display-only.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: CSS framework.
- **AlpineJS**: JavaScript framework for declarative UI.
- **Laravel Mix**: Asset compilation tool.
- **U.S. Census Gazetteer Files**: Source for geographical data.
- **Google Places API**: Used for specific address and postal code validation.