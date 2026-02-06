# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to revolutionize property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline real estate transactions by offering robust functionalities for listing properties, managing bids, and facilitating agent interactions, ultimately enhancing transparency and efficiency in the real estate market.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
The platform utilizes Laravel Mix with TailwindCSS and AlpineJS for a responsive and dynamic user interface. Livewire is central to handling dynamic content, enabling real-time UI updates and auto-population. Tab navigation is managed using Bootstrap. Location autofill leverages a cost-effective, U.S. Census-based local database. Listing displays feature unique IDs and pill badges for actions. A "Broker Compensation & Agency Agreement Terms" form serves as the canonical UI source for all Agent workflows, ensuring consistency in field structure, input types, conditional logic, and formatting.

### Technical Implementations
Built on Laravel 8.x with PHP 8.2.23 and PostgreSQL, the platform uses Node.js v20 for asset compilation.

**Shared Livewire Component Architecture**: The `TenantAgentAuction.php` component (`app/Http/Livewire/TenantAgentAuction.php`) is the **primary shared component** that processes form submissions for **ALL agent types**: Buyer, Seller, Landlord, and Tenant. This component handles form submission, draft saving/loading, and metadata persistence. The `user_type` property determines which model/table is used for data operations. The `mount($user_type = null, $listingId = null)` method requires `user_type` as the first parameter due to Livewire's positional route parameter mapping.

**Model Resolution Pattern**: All methods interacting with auction models use a `match` statement to resolve the correct model class based on `user_type`. A fallback mechanism in `loadDraft()` searches all models if a draft isn't found in the primary table.

**Adding New Fields**: When adding new fields for any agent type, they must be declared as public properties, saved via `saveMeta()`, loaded in `loadDraftData()`, and bound in the Blade view within `TenantAgentAuction.php`. Legacy agent-specific Livewire components (e.g., `app/Http/Livewire/HireBuyerAgent/`) should NOT be edited. All agent forms now use `TenantAgentAuction.php` and `TenantAgentAuctionEdit.php`.

Unique listing IDs are generated via a `HasListingId` trait. Location data is sourced from U.S. Census databases. Dynamic forms, managed by Livewire, handle conditional rendering, financing options, and property preferences with standardized inputs. Fee displays are formatted for presentation only. Bid behavior differentiates between "Traditional" and "Bidding Period" listings. Agent bid fields are auto-filled, and agent information is preserved during edits. The system supports cross-profile listing for all agent types (Tenant, Seller, Buyer, Landlord) with dedicated tables and models, including draft functionality. Owner-specific visibility ensures unapproved listings are visible to owners.

**View Preference "Other" Visibility**: All four agent types use Livewire computed properties (`getIsOtherVisibleProperty()`, `getIsOtherNonNegotiableVisibleProperty()`) to automatically toggle visibility of "Other" text fields based on array values, ensuring consistent behavior across draft save/load cycles.

**Select2 Draft Sync**: Buyer forms emit a `buyer-agent-select2-sync` browser event during draft load to hydrate Select2 multiselect elements via JavaScript listeners. A global `window.financingSyncInProgress` flag prevents Livewire sync during Select2 hydration.

**Financing Follow-Up Field Persistence**: The `updatedOfferedFinancing()` method uses a selective reset mechanism. It tracks previous financing selections and only resets fields for removed financing types, ensuring dependent fields persist correctly across various workflows. The `$isLoadingData` flag prevents resets during initial draft/edit load.

**Draft Load Protection Pattern**: The `$isLoadingData` flag in `BuyerAgentAuction.php` protects dependent field values from being reset during draft loading. All Livewire `updated*` hooks that reset dependent fields must check this flag.

**Meta Field Parity**: Create and Edit Livewire components must maintain identical field lists for saving and loading. New meta fields require public property definition, `saveMeta()` calls, and defensive null-guarded hydration in both components.

**Numeric Field Storage**: All numeric input fields use a `stripCommas()` helper method to remove comma formatting before database storage, ensuring clean numeric data while allowing formatted display.

**Tab Navigation Guard Pattern**: Both Create and Edit wizards use an `isNavigating` guard flag to prevent double-firing of tab navigation events. Navigation uses `bootstrap.Tab.getOrCreateInstance().show()` and is governed by ID-based tab order.

**Services Snapshot & Canonicalization**: `TenantServicesCatalog.php` uses a `canon()` helper method to normalize smart quotes to straight quotes for accurate `in_array()` comparisons, while preserving original display text.

**Property-Type Field Enforcement**: Residential-only fields (e.g., pool, ADU, pets) are conditionally displayed and persisted, ensuring they are not present for commercial property types.

All listing types have independent submission redirects. Tab visibility ensures owners see their drafts. Validation rules are synchronized between frontend and backend. Listing display views are normalized for consistency, including formatting, section styling, and "Broker Compensation & Agency Agreement Terms" sections. "Other" options are cleaned, occupant types displayed, and comprehensive sub-questions integrated. Lease-Option compensation and form structures are standardized. Phone numbers auto-format while stripping non-digits for database storage. Brokerage Relationship sections use disc bullet points.

**Landlord Agent "Other" Visibility Pattern**: The `LandLordAgentAuction.php` component uses visibility flag properties (`$is_other_tenant_pay_visible`, etc.) to toggle "Other" custom text fields. Select2 change handlers call Livewire update methods to set these flags. During draft loading, flags are initialized from loaded array values. The view page filters "Other" from displayed lists and renders custom text from meta fields. Photo deletion is handled by `deletePhoto()`.

**Landlord View Page Location Fields**: The landlord listing view page displays location fields (Acceptable Cities, Counties, State, Zip Code) as the first fields under Property Details, stripping state abbreviations from city/county names and displaying them semicolon-separated. This matches the tenant view pattern.

**Landlord View Page Photo Enhancements**: The photo enhancements display section in the landlord view is rendered independently of the `@if ($hasServices)` block, with defensive parsing for JSON, array, and null values.

### System Design Choices
The architecture emphasizes modularity through Laravel's structure and Livewire components. A database-first approach prioritizes local database solutions for core services. Clear separation of concerns is maintained between frontend, backend, and data persistence. The system is deployment-ready with production environment optimizations. Existing database schema and storage logic for fees are immutable, with fee format updates being display-only.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: CSS framework.
- **AlpineJS**: JavaScript framework for declarative UI.
- **Laravel Mix**: Asset compilation tool.
- **U.S. Census Gazetteer Files**: Source for geographical data.
- **Google Places API**: Used for specific address and postal code validation.