# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to revolutionize property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline real estate transactions by offering robust functionalities for listing properties, managing bids, and facilitating agent interactions, ultimately enhancing transparency and efficiency in the real estate market.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
The platform uses Laravel Mix with TailwindCSS and AlpineJS for a responsive UI, complemented by Livewire for dynamic content and real-time updates. Bootstrap manages tab navigation. Location autofill uses a U.S. Census-based local database. Listing displays feature unique IDs and pill badges. A "Broker Compensation & Agency Agreement Terms" form standardizes agent workflows for consistency.

### Technical Implementations
The platform is built on Laravel 8.x, PHP 8.2.23, and PostgreSQL, with Node.js v20 for asset compilation. A shared `TenantAgentAuction.php` Livewire component processes all agent type form submissions, handling submission, draft saving/loading, and metadata persistence through a dynamic `user_type` property. Drafts use an append-only versioning system with `draft_version`, `parent_draft_id`, and `draft_payload_hash` for tracking. Model resolution dynamically selects the correct auction model via a `match` statement. Numeric inputs are sanitized with `stripCommas()` before database storage. Configuration files, like `config/seller-financing-config.php`, centralize display logic. `ServicesFormatter` groups buyer agent services into canonical categories, normalizing strings to match configuration. `ListingDisplayHelper` standardizes field formatting and display across listing view pages, using methods like `normalizeListDeduped()` and `formatYesParenthetical()`. Display rules vary for single vs. multiple selections, "Other" values, and specific field types, using either plain text or pill badges. Conditional rendering of fields is managed based on user selections and listing types, with specific rules for Seller and Landlord views regarding property conditions, financing details, and pet information. Submit button validation on edit blades skips hidden fields. `ListingDownloadController` generates PDF listing packets using `barryvdh/laravel-dompdf`, driven by dedicated field map classes for each listing type, rendered through a universal Blade template. Buyer Agent Sync Events (`buyer-agent-select2-sync`) are dispatched for Livewire to hydrate Select2 fields. Select2 and Livewire integration handles dynamic and static multi-select fields, using `wire:ignore` where appropriate and providing robust initialization and repair mechanisms in `select2-stable.js` and `select2-manager.js`. A JSON Bridge Pattern is implemented for certain buyer multi-select fields to ensure data synchronization between Select2 and Livewire properties, using hidden JSON inputs and PHP hooks for decoding.

#### Seller Agent Auction - Key Implementation Notes
- **Acceptable Exchange Item** (`exchange_item`): Multi-select field stored as JSON array in metadata. Property type is `array` (default `[]`) in both `SellerAgentAuction.php` and `SellerAgentAuctionEdit.php`. Save logic normalizes to JSON. Load logic handles backwards-compatible decoding of legacy single-string values.
- **Photo/Video persistence**: Save methods use `!is_string()` guard — existing filenames (strings) loaded from DB skip the upload/save branch, preventing overwrites. Both `photo` and `video` properties are loaded from metadata in `loadAuctionData()`.
- **Financing field display**: Occupancy Requirement and Timing of Transfer use `text_or_other` format (plain text, not badges). Property Condition uses inline rendering (not `normalizeList`) to preserve "None" values.
- **Landlord edit tabs**: Tab navigation is purely client-side (no `wire:click` for `setActiveTab`). Active tab is persisted in `sessionStorage` and restored after Livewire re-renders. Navigation lock prevents double-clicks. Next/Back buttons use Bootstrap Tab API directly.

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