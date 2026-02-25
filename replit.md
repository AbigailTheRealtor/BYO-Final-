# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to revolutionize property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline real estate transactions by offering robust functionalities for listing properties, managing bids, and facilitating agent interactions, ultimately enhancing transparency and efficiency in the real estate market.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
The platform uses Laravel Mix with TailwindCSS and AlpineJS for a responsive UI, complemented by Livewire for dynamic content and real-time updates. Bootstrap manages tab navigation. Location autofill uses a U.S. Census-based local database. Listing displays feature unique IDs and pill badges. A "Broker Compensation & Agency Agreement Terms" form standardizes agent workflows for consistency.

### Technical Implementations
The platform is built on Laravel 8.x, PHP 8.2.23, and PostgreSQL, with Node.js v20 for asset compilation.

**Shared Livewire Component Architecture**: A single `TenantAgentAuction.php` Livewire component processes form submissions for all agent types (Buyer, Seller, Landlord, Tenant), handling submission, draft saving/loading, and metadata persistence. The `user_type` property dynamically determines the data model.

**Draft Management**: Drafts use an append-only versioning system where each save creates a new record, tracked by `draft_version`, `parent_draft_id`, and `draft_payload_hash`. Drafts are loaded via dedicated routes and validated for existence and ownership. The `$isLoadingData` flag prevents unintended resets during draft loading.

**Model Resolution**: A `match` statement dynamically resolves the correct auction model based on `user_type` for all data interactions.

**Field Handling**: New fields are added as public properties, saved via `saveMeta()`, and loaded in `loadDraftData()`. All numeric inputs are sanitized by `stripCommas()` before database storage. Property-type specific fields are conditionally displayed and persisted.

**Configuration-Driven Features**: `config/seller-financing-config.php` centralizes seller financing term ordering and display logic, ensuring consistent presentation across forms and views.

**View Helpers and Components**: A `ListingDisplayHelper` standardizes field formatting and display across all listing view pages. Key helper methods include: `normalizeListDeduped()` (dedup + ½→1/2 normalization), `stripStateSuffix()` (removes ", FL"/", XX" from cities/counties), `isNoneNa()` (filters None/N/A values from display), `formatYesParenthetical()` (combines Yes + detail like "Yes (Low Credit)"). Reusable Blade components like `accordion.blade.php`, `pills.blade.php`, and `kv-row.blade.php` enhance UI consistency. Services are displayed grouped by category, showing only selected items.

**Display Rules**: 1 selection = plain text, 2+ selections = pill badges/chips. "Other" values are resolved to custom text via `normalizeList()`. None/N/A values are filtered out. Buyer "Acceptable Property Styles" always renders as pill badges (multi-select). "½ Duplex" is normalized to "1/2 Duplex" at display-time. Cities/counties strip state suffixes (", FL"). Financing sub-answers follow the 1=text/2+=chips rule. "Pet Types:" is the standardized label across all listing types. Financing pills are sorted in a standardized order (Assumable, Cash, Conventional, Cryptocurrency, Exchange/Trade, FHA, Jumbo, Lease Option, Lease Purchase, No-Doc, Non-QM, NFT, Seller Financing, USDA, VA) with "Other" always last. Conventional loan types (FHA/Jumbo/VA/No-Doc/Non-QM/USDA) group under a shared header showing Pre-Approved status. "Current Representation Status with Broker:" uses colon (not question mark). Section headers: "Property Preferences:", "Purchasing Terms:", "Financing Details:", "Broker Compensation & Agency Agreement Terms:". Non-Negotiable Amenities parent Yes/No renders as plain text with child items following 1=text/2+=chips rule.

**Listing Download (Field-Map-Driven)**: `ListingDownloadController` generates complete PDF listing packets using `barryvdh/laravel-dompdf`. Each listing type (Seller, Buyer, Landlord, Tenant) has a dedicated field map class in `app/Exports/ListingFieldMaps/` that defines all sections, field labels, field keys, and "Other" text pairings. `ListingPdfDataBuilder` combines the meta data with the field map to produce structured section/row arrays. `ListingExportFormatter` handles value formatting (money, percent, lists) and "Other" text resolution. A single universal Blade template (`listing-download/packet.blade.php`) renders all listing types.

### System Design Choices
The architecture prioritizes modularity, clear separation of concerns, and a database-first approach utilizing local database solutions. The system is optimized for production deployment, and existing database schema for fees is immutable, with display-only formatting updates.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: CSS framework.
- **AlpineJS**: JavaScript framework.
- **Laravel Mix**: Asset compilation.
- **U.S. Census Gazetteer Files**: Geographical data source.
- **Google Places API**: Address and postal code validation.
- **barryvdh/laravel-dompdf**: PDF generation.