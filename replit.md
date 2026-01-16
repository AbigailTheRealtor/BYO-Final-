# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to revolutionize property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline real estate transactions by offering robust functionalities for listing properties, managing bids, and facilitating agent interactions, ultimately enhancing transparency and efficiency in the real estate market.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
The platform utilizes Laravel Mix with TailwindCSS and AlpineJS for a responsive and dynamic user interface. Livewire is central to handling dynamic content, enabling real-time UI updates and auto-population. Tab navigation is managed using Bootstrap, featuring safe slug generation for tab IDs. Location autofill leverages a cost-effective, U.S. Census-based local database. Listing displays feature unique IDs as badges and pill badges for actions. A "Broker Compensation & Agency Agreement Terms" form serves as the canonical UI source for all Agent workflows, ensuring consistency in field structure, input types, conditional logic, and formatting.

### Technical Implementations
Built on Laravel 8.x with PHP 8.2.23 and PostgreSQL, the platform uses Node.js v20 for asset compilation. Unique listing IDs are generated via a `HasListingId` trait. Location data is sourced from U.S. Census databases. Dynamic forms are managed by Livewire components, handling conditional rendering, financing options, and property preferences with standardized input fields, required indicators, phone auto-formatting, and dynamic visibility. Fee displays are formatted for presentation without altering storage. Defensive guards are implemented in Livewire methods. Bid behavior differentiates between "Traditional" and "Bidding Period" listings, affecting timers and bid visibility. Agent bid fields are auto-filled, and agent information is preserved during edits. The system supports cross-profile listing for all agent types (Tenant, Seller, Buyer, Landlord), with dedicated tables and models for each. Draft functionality is supported across all agent types. Owner-specific visibility ensures listing owners can view their unapproved listings.

**View Preference "Other" Visibility**: All four agent types (Buyer, Seller, Landlord, Tenant) use Livewire computed properties (`getIsOtherVisibleProperty()`, `getIsOtherNonNegotiableVisibleProperty()`) to automatically toggle visibility of "Other" text fields based on array values. This ensures consistent behavior across draft save/load cycles without manual state management. Blade files reference `$this->is_other_visible` which invokes the computed getter.

**Select2 Draft Sync**: Buyer forms emit a `buyer-agent-select2-sync` browser event during draft load containing all Select2 multiselect array values (view_preference, non_negotiable_amenities, offered_financing, services, etc.). JavaScript listeners hydrate the Select2 elements with `.val([...]).trigger('change')` to sync DOM state with Livewire.

**Meta Field Parity**: Create and Edit Livewire components must maintain identical field lists for both saving (saveAllMetadata) and loading (loadDraftData/loadAuctionData). When adding new meta fields, ensure they are: (1) defined as public properties in both components, (2) saved via saveMeta() in both components, (3) loaded with defensive null-guarded hydration patterns in both components. Example pattern for array fields: `$raw = $auction->get->field ?? null; $this->field = $raw ? (is_string($raw) ? json_decode($raw, true) ?? [] : (array)$raw) : [];`

**Numeric Field Storage (stripCommas)**: All numeric input fields (budgets, amounts, fees, prices, percentages, square footage, rates) use a `stripCommas()` helper method in both Create and Edit Livewire components to remove comma formatting before database storage. This ensures clean numeric data storage while allowing comma-formatted display in forms. The helper handles null/empty values gracefully: `protected function stripCommas($value) { if ($value === null || $value === '') { return $value; } return str_replace(',', '', $value); }`. Applied to 40+ fields including: maximum_budget, cash_budget, pre_approval_amount, purchase_price, interest_rate, lease_fee_flat, purchase_fee_flat, all limited service fees, etc.

All listing types (Tenant, Buyer, Seller, Landlord) use dedicated tables and models and have independent submission redirects to their respective detail pages. Tab visibility ensures owners see their drafts across all tabs, while non-owners only see published, approved listings. Validation rules are synchronized between frontend and backend, with conditional logic for different auction types. Listing display views (Seller, Buyer, Landlord) are normalized to match the Tenant view, including consistent formatting, section styling, categorized services, and "Broker Compensation & Agency Agreement Terms" sections with value-first display formats. "Other" options are cleaned up, occupant types are clearly displayed, and comprehensive sub-questions for offered financing/currency are integrated. Lease-Option compensation and form structures are standardized across all agent types, with conditional currency symbols and input formatting. Service text for commercial listings is normalized, and a display-layer fallback maps legacy data. Buyer Broker Compensation structure is standardized, and phone numbers auto-format while stripping non-digits for database storage. Brokerage Relationship sections consistently use disc bullet points.

### System Design Choices
The architecture emphasizes modularity through Laravel's structure and Livewire components. A database-first approach prioritizes local database solutions for core services like location. Clear separation of concerns is maintained between frontend, backend, and data persistence. The system is deployment-ready with production environment optimizations. Existing database schema and storage logic for fees are immutable, with fee format updates being display-only.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: CSS framework.
- **AlpineJS**: JavaScript framework for declarative UI.
- **Laravel Mix**: Asset compilation tool.
- **U.S. Census Gazetteer Files**: Source for geographical data.
- **Google Places API**: Used for specific address and postal code validation.