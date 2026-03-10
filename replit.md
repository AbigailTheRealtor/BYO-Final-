# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to revolutionize property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline real estate transactions by offering robust functionalities for listing properties, managing bids, and facilitating agent interactions, ultimately enhancing transparency and efficiency in the real estate market.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
The platform leverages Laravel Mix with TailwindCSS and AlpineJS for a responsive user interface, complemented by Livewire for dynamic content and real-time updates. Bootstrap is used for managing tab navigation. Location autofill functionalities utilize a U.S. Census-based local database. Listing displays feature unique IDs and pill badges for clear categorization. A "Broker Compensation & Agency Agreement Terms" form standardizes agent workflows to ensure consistency.

### Technical Implementations
The platform is built on Laravel 8.x, PHP 8.2.23, and PostgreSQL, with Node.js v20 for asset compilation. A shared `TenantAgentAuction.php` Livewire component processes all agent type form submissions, handling submission, draft saving/loading, and metadata persistence. Drafts utilize an append-only versioning system. Model resolution dynamically selects the correct auction model. Numeric inputs are sanitized, and configuration files centralize display logic. The `ServicesFormatter` groups buyer agent services into canonical categories, while `ListingDisplayHelper` standardizes field formatting and display across listing view pages. Conditional rendering of fields is managed based on user selections and listing types. PDF listing packets are generated using `barryvdh/laravel-dompdf`, driven by dedicated field map classes and a universal Blade template. Select2 and Livewire integration handles dynamic and static multi-select fields, using `wire:ignore` where appropriate and providing robust initialization and repair mechanisms. A JSON Bridge Pattern synchronizes data for certain buyer multi-select fields between Select2 and Livewire properties. Wizard navigation employs a delegation pattern to manage tab transitions and validation across Livewire re-renders, with a dedicated banner for displaying validation errors. Address fields dynamically show/hide based on data presence. File uploads (photos/videos) utilize `wire:key` for stabilization and event delegation for validation. Video links are embedded as iframes for YouTube/Vimeo or displayed as clickable links for other URLs. Required field validations and select2 stabilization for property-type-dependent fields are carefully managed to ensure data integrity and user experience.

### System Design Choices
The architecture prioritizes modularity, clear separation of concerns, and a database-first approach utilizing local database solutions. The system is optimized for production deployment, and the existing database schema for fees is immutable, with display-only formatting updates.

## Bug Fixes Completed

### Session 5 (Hire Seller Agent Form - Layout Bug & Appliances Other)
1. **Major blank space on Sale Terms tab / all subsequent tabs empty** — Root cause: The closing `</div>` for the Assignment Contract Alpine.js `x-show` wrapper was trapped inside `@if ($sale_provision_assignment === 'No')` in `seller-terms.blade.php`. Since that condition is false by default, the Alpine div was never closed, and its `x-show="false"` swallowed all content including all subsequent tab panes (Services, Additional Details, Broker Compensation, Seller Information). Fixed by moving `</div>` to after `@endif`.
2. **Appliances Included → Other does not show custom input** — The `@if ($showOtherAppliances)` conditional removed the `#other_appliances` element entirely from the DOM, so the existing jQuery `.show()`/`.hide()` JS handler could not find it. Fixed by always rendering the element with `style="display: block/none"` matching the "View → Other" pattern.
3. **Multiple missing `@php` array variables in property-preferences.blade.php** — Added `$property_items_seller` (property styles with class tags), `$property_condition_seller`, `$bedroomsRes`, `$acreageRes`, `$tenant_require`, `$preferences`, `$purchasing_props`, `$unit_types`, `$garage_parking_spaces`, and `$non_negotialble_terms_landlord` to the `@php` block to prevent "Undefined variable" errors when changing Property Type.
4. **Missing `$showEnhancements`, `$showCustomEnhancement`, `$showOpenHouseInput` properties** — Added these three boolean Livewire properties to `SellerAgentAuction.php` to prevent errors when `services.blade.php` re-renders after a Livewire update.

### Session 4 (Hire Seller Agent Form - Missing Properties & Blade Variables)
1. **Form 500 error on load** — `listing-details.blade.php` was missing the `$auction_lengths_seller` `@php` block definition. Added it to match the landlord form pattern.
2. **Missing Livewire public properties** — `SellerAgentAuction.php` was missing many public properties that the blade templates referenced via `wire:model`, `@if`, and `$this->reset()` calls, causing `PropertyNotFoundException` and "Undefined variable" errors. Added: `$cityFieldVisible`, `$stateFieldVisible`, `$zipCodeFieldVisible`, `$balloon_payment`, `$occupant_status`, `$occupant_tenant`, `$business_type`, `$other_business_type`, `$target_closing_date`, `$exchange_liens`, `$seller_down_payment_amount`, `$seller_late_fee_amount`, `$assumable_loan_type`, `$outstanding_balance`, `$lender_approval_required`, `$seller_amortization_type`, `$seller_amortization_other`, `$seller_payment_frequency`, `$seller_payment_frequency_other`, `$crypto_transfer_timing`, `$crypto_exchange_method`, `$crypto_custodian_wallet`, `$crypto_transaction_fees`, and many lease option/purchase/NFT related properties.
3. **Missing blade array definitions in seller-terms.blade.php** — `$occupant_types_seller`, `$seller_property` (sale provision options), and `$financing_options_seller` arrays were not defined anywhere accessible to the blade. Added them to the `@php` block at the top of `seller-terms.blade.php`.
4. **Missing `$business_type` array in property-preferences.blade.php** — The `$business_type` variable was used in both `wire:model` (component property, string) and `@foreach` (options array). Added the options array to the `@php` block in `property-preferences.blade.php` to match the tenant form pattern.

### Session 3 (Hire Tenant Agent Form - Property Styles & Lease Term Persistence)
1. **Acceptable Property Styles shows "No results found"** — Fixed by using `wire:ignore` on property-details.blade.php input-cover to prevent Livewire from destroying Select2. When property_type changes, JavaScript function `rebuildPropertyItemsOptions()` rebuilds options from embedded JSON data, then reinits Select2.
2. **Acceptable Property Styles flashes when other fields are edited** — Fixed by narrowing Select2 re-init trigger: only reinitializes when `property_type` actually changes, not on every Livewire update.
3. **Offered Lease Term selections not persisting** — Fixed by removing `defer: true` flag from safeLivewireSet calls (using immediate `defer: false` instead) to ensure selections sync to Livewire immediately. Applied same narrow re-init guard based on property_type changes.
4. **Edit blade lease_for using defer flag** — Updated tenant-agent-auction-edit.blade.php to use `defer: false` and apply same narrow property_type guard.

### Session 2 (Hire Tenant Agent Form - Final Round)
1. **Submit button flash** — Added event listeners (shown.bs.tab, message.processed) to call _updateNextSubmitButtons() reliably.
2. **Empty section headings on listing** — Wrapped 4 section headings with conditional checks in view.blade.php so they only show if content exists.
3. **Offered Lease Term (previous session)** — Already functional via `wire:ignore` wrappers; normalizeListDeduped() in view correctly reads and displays selected lease terms.

### Session 1 (Previous Fixes)
- Commercial Other bathrooms bug (removed Residential-only wrapper)
- Property Styles greyed out (removed wire:ignore from property_items wrapper to allow Blade re-render)
- Non-negotiable amenities flash (added wire:ignore + JS disabled state management)
- Submit button always clickable (removed disabled attr)
- lease_for false positive in validation (DOM fallback + wire:ignore on Residential wrapper)
- Cross-role safety (scoped Livewire validation to tenant/landlord only)
- Removed validation-debug yellow box
- Fixed initSelect2LeaseFor() re-initialization via message.processed hook
- Property items immediate sync (safeLivewireSet) with message.processed re-init

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: Utility-first CSS framework.
- **AlpineJS**: Lightweight JavaScript framework.
- **Laravel Mix**: Wrapper for Webpack to compile assets.
- **U.S. Census Gazetteer Files**: Source for geographical data.
- **Google Places API**: Used for address and postal code validation.
- **barryvdh/laravel-dompdf**: Library for PDF generation from HTML in Laravel.