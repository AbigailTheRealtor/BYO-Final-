# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to revolutionize property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline real estate transactions by offering robust functionalities for listing properties, managing bids, and facilitating agent interactions, ultimately enhancing transparency and efficiency in the real estate market.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
The platform utilizes Laravel Mix with TailwindCSS and AlpineJS for a responsive and dynamic user interface. Livewire is central to handling dynamic content, enabling real-time UI updates and auto-population to minimize page reloads. Tab navigation is managed using Bootstrap, featuring safe slug generation for tab IDs. Location autofill leverages a cost-effective, U.S. Census-based local database. Listing displays feature unique IDs as badges and pill badges for actions. A "Broker Compensation & Agency Agreement Terms" form serves as the canonical UI source for all Agent workflows, ensuring consistency in field structure, input types, conditional logic, and formatting.

### Technical Implementations
Built on Laravel 8.x with PHP 8.2.23 and PostgreSQL, the platform uses Node.js v20 for asset compilation via Laravel Mix. Unique listing IDs are generated using a `HasListingId` trait. Location data is sourced from U.S. Census databases, with city-to-county mappings corrected via a dedicated seeder. Dynamic forms are managed by Livewire components, handling conditional rendering, financing options, and property preferences. Form enhancements include standardized input fields, required indicators, phone auto-formatting, and dynamic visibility for "Other" text inputs. Fee displays are formatted for presentation without altering storage. Defensive guards are implemented in Livewire methods to prevent crashes. Bid behavior differentiates between "Traditional" and "Bidding Period" listings, affecting timers and bid visibility. A service display normalizes stored strings, and agent bid fields are auto-filled for new bids. Agent information is preserved during edits, and agents can view all sections of their own bids. Action buttons are timer-locked for "Bidding Period" listings. Conditional field re-rendering in Livewire utilizes `wire:key` attributes for stability. Edit mode preserves uploaded files with thumbnails and allows validated deletions. An `AcceptedBidSummaryService` auto-generates comprehensive summaries upon bid acceptance, which can be e-signed by both parties, with signatures capturing typed name, timestamp, and IP.

A Match Score system rates agent bids against tenant baselines, displayed with color-coding. A notification system covers bid lifecycle events (`bid_received`, `bid_countered`, `bid_rejected`). Agents can view counter terms from their dashboard. The "My Bids" interface uses static cards with color-coded status badges and context-specific action buttons. The Tenant Dashboard features a "Pending Agent Bids" section, and the tenant sidebar dynamically displays "Agent Bids" with a pending bid count. For "Bidding Period" listings, agents can view anonymized competing bids, limited to Broker Compensation, Offered Services, and Match Scores. Competing bids auto-display inline on the listing page after an agent submits a bid. Bid modifications trigger `bid_modified` notifications for tenants. A dedicated preview page allows tenants to view full bid details with mismatch highlighting and action buttons. Landlord Agent fields are standardized across all views. The platform supports cross-profile listing for all agent types (Tenant, Seller, Buyer, Landlord), with dedicated tables and models for each. Draft functionality is supported across all agent types, with a `draftLoaded` browser event for syncing select values. Owner-specific visibility ensures listing owners can view their unapproved listings while others see only approved ones.

**Listing Date Editability**: Listing Date is now editable for all 4 agent types (Tenant, Buyer, Seller, Landlord). The field auto-fills with today's date on first creation but can be manually changed. When loading a saved draft, the saved listing date is preserved and users can edit it. The `readonly` attribute was removed from Tenant and Buyer listing date inputs. All Livewire mount() methods initialize listing_date to today's date before loadDraft() is called, ensuring drafts use their saved values. The "Actual Listings" query in UserController does not filter by listing_date, so drafts with older dates remain visible.

**Listing Type Separation**: Each listing type uses its own dedicated table and model: TenantAgentAuction (tenant_agent_auctions), BuyerAgentAuction (buyer_agent_auctions), SellerAgentAuction (seller_agent_auctions), LandlordAgentAuction (landlord_agent_auctions). UserController::author() queries the correct model based on tab type parameter (0=Tenant, 1=Seller, 2=Buyer, 3=Landlord). No cross-mapping between listing types.

**Submit Redirects (All 4 Agent Types)**: Upon successful Submit/Publish, users are redirected to the correct detail page for their listing type:
- Tenant → `tenant.agent.auction.view`
- Seller → `seller.agent.auction.detail`
- Buyer → `buyer.view-auction`
- Landlord → `landlord.agent.auction.view`

Save Draft does NOT redirect but displays a confirmation message including the Listing ID (e.g., "Draft saved (Listing ID: ABC-12345)"). TenantAgentAuction.php handles cross-profile submissions dynamically using a match() statement for both model selection and redirect routing based on user_type.

**Tab Visibility (All 4 Agent Types)**: Owners can see their own drafts in all tabs (Tenant, Seller, Buyer, Landlord), while non-owners only see published, approved listings. UserController::author() applies conditional filtering: `is_sold=false` always, plus `is_draft=false AND is_approved=1` only for non-owners. Temporary server-side logging tracks save/submit events and tab queries for debugging.

**Buyer Form - Validation Rules**: Counties are REQUIRED while Cities are optional. The frontend validation uses a browser event synchronization pattern where Livewire dispatches `buyer-counties-updated`, `buyer-auction-type-changed`, and `buyer-state-init` events to maintain synchronized state in `window.buyerState`. All four `checkFormValidity` functions (create/edit, full/limited service) use this authoritative state object rather than DOM queries. Split validation logic applies: Traditional listings skip `auction_time` validation, while Bidding Period listings require it. Backend validation in `store()` and `update()` methods enforces the same rules, ensuring frontend/backend alignment.

**Listing Display Normalization (Jan 2026)**: All listing display views (Seller, Buyer, Landlord) are normalized to match the Tenant view as the "gold standard." Each view includes:
- Formatting helpers (`$fmtMoney`, `$fmtPercent`, `$joinParts`, `$basisText`) for consistent currency/percentage display
- CSS classes (`section-header`, `section-title`) for uniform section header styling
- Services sections with property-type-aware groupings (Residential vs Commercial categories) using emoji category headers (📣, 🔍, 🏡/🏢, 📝, 📋, 💡) and round bullet points
- Broker Compensation sections renamed to "Broker Compensation & Agency Agreement Terms" with type-specific labels (Seller's, Buyer's, Landlord's)
- Value-first broker compensation display format: "6% of Total Purchase Price" instead of showing type and value separately
- Combo fee format: "2% of Total Purchase Price + $5,000 flat" (not "2% + $5,000 of Total Purchase Price")
- Hide-if-empty logic and "Other" option handling matching the Tenant pattern
- Character encoding uses curly apostrophes (U+2019) for exact matching with Livewire form data

The Buyer view (buyerAgentAuctionDetail.blade.php) includes property-type-aware categories for Residential, Income, Commercial, and Business property types. Agent bids and counter-bids also follow the same categorized services display and value-first broker compensation format.

**Seller View Normalization (Jan 2026)**: The Seller listing detail view is fully normalized to match platform standards:
- "Other" option cleanup: Filters literal "Other" from View, Amenities, Property Features arrays; shows custom text as regular badges (not in parentheses)
- Occupant Type display: Shows "Occupied Until" with date in "F j, Y" format when Occupant Type is "Tenant"
- Offered Financing/Currency: Displays comprehensive sub-questions for Assumable (terms, loan type, interest rate, monthly payment, balance, lender approval, down payment, remaining term, loan servicer, assumption fee), Seller Financing (amount, interest rate, term), Lease Option (price, payment, duration, fee, fee credit), Cryptocurrency (coin type, accepted percentage), and NFT (if offered)
- Broker Compensation section: Organized into 6 subsections with horizontal dividers: Seller's Broker Compensation, Lease Terms (lease agreement interest, leasing fee), Lease-Option Terms (interest, compensation values), Legal Terms (protection period, early termination, retainer, retained deposits, agency timeframe), Brokerage Relationship, Additional Terms
- Case-insensitive, null-safe comparisons: Uses `in_array(strtolower($value ?? ''), ['yes'])` pattern for Yes/No field checks

**Lease-Option Compensation Standardization (Jan 2026)**: All 4 agent type views (Tenant, Buyer, Seller, Landlord) display Lease-Option compensation values in standardized format:
- Percentages show "X% of Total Purchase Price" (not bare "X%")
- Flat amounts show "$X,XXX" currency format
- Consistent labels: "Compensation (When Option Is Created)" and "Compensation (If Purchase Option Is Exercised)"

**Text Normalization**: Removed "the" from broker compensation phrases across all views:
- "of Total Purchase Price" (not "of the Total Purchase Price")
- "of Gross Lease Value" (not "of the Gross Lease Value")

**Buyer Broker Compensation Structure (Jan 2026)**: The Buyer view Broker Compensation section uses the same label:value pair format as Tenant and Landlord views, organized into 6 labeled subsections with dividers across main listing, agent bid, and counter-bid contexts:
1. Buyer's Broker Compensation (commission structure, purchase fee)
2. Buyer's Broker Lease Fee (interested in lease, lease fee value-first)
3. Lease-Option Details (lease-option interest, compensation amounts)
4. Legal Terms (protection period, early termination fee, retainer fee, agency timeframe)
5. Brokerage Relationship
6. Additional Terms
Each field uses `<div class="col-md-12 col-12 pt-2 fw-bold">Label: <span class="removeBold">Value</span></div>` format matching Tenant/Landlord views. Raw stored values are displayed without transformation (only quote stripping applied).

### System Design Choices
The architecture emphasizes modularity through Laravel's structure and Livewire components. A database-first approach prioritizes local database solutions for core services like location. Clear separation of concerns is maintained between frontend, backend, and data persistence. The system is deployment-ready with production environment optimizations. Existing database schema and storage logic for fees are immutable, with fee format updates being display-only.

## External Dependencies
- **PostgreSQL**: The primary relational database.
- **TailwindCSS**: CSS framework for styling.
- **AlpineJS**: JavaScript framework for declarative UI.
- **Laravel Mix**: Tool for compiling frontend assets.
- **U.S. Census Gazetteer Files**: Source for geographical data (states, counties, cities).
- **Google Places API**: Used for specific address and postal code validation.