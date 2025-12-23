# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed for transparent property sales through bidding. It supports various auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline the real estate transaction process by providing robust listing, bidding, and agent interaction functionalities.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
- **Frontend**: Utilizes Laravel Mix with TailwindCSS and AlpineJS for a modern and responsive user interface.
- **Dynamic Content**: Extensive use of Livewire for dynamic form elements, auto-population features, and real-time UI updates, minimizing full page reloads.
- **Tab Navigation**: Implemented Bootstrap tab system for organizing complex forms.
  - **CRITICAL RULE**: Tab IDs must NEVER be generated directly from labels. All tab navigation must use the `$safeSlug` function (lowercase, alphanumeric + dashes only, no special characters like `&`). The same slug must be used for both the nav `data-bs-target` and the tab-pane `id`. This applies to all wizard flows (Tenant, Buyer, Seller, Landlord) in both Create and Edit blades.
- **Location Autocomplete**: Replaced Google Places API with a U.S. Census-based local database for states, counties, and cities to provide efficient and cost-effective location lookups. Auto-populates state and county fields based on city selection.
- **Listing ID Display**: Unique listing IDs are prominently displayed as badges on listing view pages.
- **Pill Badges**: Enhanced styling for delete buttons on colored pill badges for better visibility.

### Technical Implementations
- **Framework**: Laravel 8.x.
- **Database**: PostgreSQL is the primary database. Database migrations are managed by Laravel, including checks for existing tables to handle optional features gracefully.
- **PHP Version**: 8.2.23.
- **Node.js**: v20, used for frontend asset compilation via Laravel Mix.
- **Unique Listing IDs**: Implemented a `HasListingId` trait to automatically generate unique, prefixed listing IDs (e.g., `TAA-XXXXXXXX`) for all auction types, ensuring data integrity and easy identification.
- **Location Data**: Integrated a comprehensive U.S. Census-based location database (`us_states`, `us_counties`, `us_cities`) for all location-based autocomplete and auto-population features, significantly reducing reliance on external APIs.
- **Dynamic Form Handling**: Livewire components dynamically manage form fields, including conditional rendering using Blade `@if` directives with `wire:key` for reliable DOM morphing, especially for complex financing options and property preferences.
- **Form Enhancements**:
    - **Hire Agent Listings**: Standardized input fields, added required field indicators, phone auto-formatting, and updated tooltips.
    - **Financing Types**: Expanded options for Assumable Financing, Cryptocurrency, Exchange/Trade, NFT, Seller Financing, Lease Option, and Lease Purchase with detailed sub-fields and conditional logic.
    - **"Other" fields**: Implemented dynamic visibility for "Other" text inputs across various selections (e.g., business type, property items, conditions, bedrooms, bathrooms, assets).

### System Design Choices
- **Modularity**: Utilizes Laravel's modular structure for features, with Livewire components handling specific UI interactions.
- **Database-First Approach**: Prioritizes local database solutions for core functionalities like location services to ensure performance, reliability, and reduce external dependencies.
- **Clear Separation of Concerns**: Frontend assets compiled via Laravel Mix, backend logic in Laravel controllers and Livewire components, and data persistence with PostgreSQL.
- **Deployment Ready**: Configuration includes optimized Composer installs, NPM builds, and Laravel caching for production environments.

## External Dependencies
- **PostgreSQL**: Primary database for the application.
- **TailwindCSS**: CSS framework for styling.
- **AlpineJS**: Lightweight JavaScript framework for interactive UI.
- **Laravel Mix**: Webpack wrapper for compiling frontend assets.
- **U.S. Census Gazetteer Files**: Used for seeding the local `us_states`, `us_counties`, and `us_cities` databases.
- **Google Places API**: Retained for specific address and postal code validation, but no longer used for general location autocomplete.

## Recent Changes (December 2025)

### Agent Bid Display Enhancements (Tenant Agent Listings)
- **Agent Overview & Qualifications**: Added Licensed Since, Marketing Strategy, What Sets This Agent Apart, and Services Offered sections to the Private Compensation modal
- **Label Standardization**: Updated all labels to use colons (e.g., "Why Hire This Agent:")
- **Visit Website Link**: Made null-safe with URL normalization (adds https:// if missing) and security attributes
- **Review Links**: Fixed label from plural to singular ("Review Links:")
- **Broker Compensation Section**: Reformatted with proper A-F subsections:
  - A) Tenant's Broker Compensation
  - B) Purchase Fee Details
  - C) Lease-Option Details
  - D) Legal Terms
  - E) Brokerage Relationship
  - F) Additional Terms / Additional Details
- **Services Offered Display**: Added category subtitles with emoji prefixes in correct order for Residential and Commercial property types
- **Business Card Display**: Larger preview (450px), clickable full-size view, View Full Size and Download buttons
- **Marketing Materials Display**: Improved with Open Link and Download buttons, better styling, "Not provided" message when empty

### Agent Bid Privacy & Anonymity (December 2025)
- **Agent Anonymity**: Replaced agent names with "Agent 1", "Agent 2", etc. based on bid submission order
- **Last Bidder Display**: Changed "X is the last bidder" to "Agent X was the last bidder"
- **Compliance Notice Removed**: Removed the "No Broker Compensation, Agency Agreement Terms..." alert from public view
- **Private Fields Hidden**: Licensed Since, Marketing Strategy, What Sets This Agent Apart, About Agent, Why Hire This Agent are now only visible in the private modal for listing owner
- **Public Bid Cards**: Show only Services count, Commission Structure type, and Lease Fee Type - no dollar amounts, retainers, or protection periods
- **Gated Full Terms**: "View Full Services & Broker Compensation Terms" button only visible to listing creator, others see "Private — visible only to listing creator"
- **Bid Status Badges**: Active/Countered/Accepted/Rejected status badges with color coding (blue/yellow/green/red)
- **Controller Null Safety**: Added 404 handling and null safety to TenantAgentAuctionController::view()

### Agent Bid Management Features (December 2025)
- **Edit Bid**: Agents can edit their own bids via Edit button on listing page or dashboard (only if auction not expired, bid not accepted/rejected)
- **Withdraw Bid**: Agents can withdraw their own bids with confirmation dialog (only if auction not expired, bid not accepted/rejected)
- **Bid Submission Lock**: Server-side validation prevents bid submission after auction expiration or when listing is sold
- **Counter-Bid Prefill**: Counter bid form now loads data from latest counter bid or original bid for easier editing
- **Dashboard Enhancements**: Tenant agent dashboard shows bid status badges (Active/Countered/Accepted/Rejected) and per-bid actions (Edit/Withdraw)

### Public Bid Card UI Redesign (December 2025)
- **Card Layout**: Matches screenshot design exactly with proper spacing and dividers
- **Header Row**: "Agent X" (bold, dark blue) on left, status (colored text) on right, horizontal divider below
- **Offered Services Row**: "Offered Services: X Services" count including Additional Services entries, horizontal divider below
- **Broker Compensation Summary**: Header + two fields only:
  - "Tenant's Broker Commission Structure:" with value
  - "Tenant's Broker Commission Fee:" with formatted value (e.g., "Flat Fee: $1,000")
- **View Full Terms Link**: Blue text link at bottom, only visible to listing owner
- **Privacy Enforced**: Non-owners see "Private — visible only to listing creator"
- **Removed**: Accordion expand/collapse behavior - content now always visible in card format