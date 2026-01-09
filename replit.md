# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform for transparent property sales through bidding. It supports various auction types (property, buyer, seller, landlord, and tenant agent auctions) and aims to streamline real estate transactions by providing robust listing, bidding, and agent interaction functionalities, enhancing transparency and efficiency.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
- **Frontend Frameworks**: Laravel Mix with TailwindCSS and AlpineJS for a responsive interface.
- **Dynamic Content**: Livewire for dynamic forms, real-time UI updates, and auto-population, minimizing page reloads.
- **Tab Navigation**: Bootstrap tab system with safe slug generation for tab IDs.
- **Location Autocomplete**: U.S. Census-based local database for states, counties, and cities, replacing Google Places API for cost-effectiveness.
- **Listing Display**: Unique listing IDs as badges; pill badges for actions.
- **Canonical UI Source**: The "Broker Compensation & Agency Agreement Terms" form is the canonical reference for all Agent workflows (Bids, Counter-Bids, Edits), ensuring identical field structure, input types, conditional logic, and formatting.

### Technical Implementations
- **Framework & Language**: Laravel 8.x with PHP 8.2.23.
- **Database**: PostgreSQL with Laravel migrations.
- **Frontend Tooling**: Node.js v20 for asset compilation via Laravel Mix.
- **Unique Listing IDs**: `HasListingId` trait generates unique, prefixed IDs (e.g., `TAA-XXXXXXXX`).
- **Location Data**: Integrated U.S. Census-based `us_states`, `us_counties`, and `us_cities` databases. City-to-county mappings corrected using authoritative Census Bureau place data (national_place2020.txt) via `FixCityCountyMappings` seeder.
- **Dynamic Form Handling**: Livewire components manage dynamic fields, conditional rendering, financing options, and property preferences.
- **Form Enhancements**: Standardized input fields, required indicators, phone auto-formatting, and dynamic visibility for "Other" text inputs.
- **Fee Display**: Fees display in a combined format (e.g., "$2,323 + 3% of Gross Lease Value") as display-only formatting without storage or schema changes.
- **Flat Fee Inputs**: Flat-fee amount inputs use static USD prefix ($) instead of a $/% dropdown toggle.
- **Error Handling**: Defensive guards in Livewire `updated($propertyName)` methods to prevent `Str::studly()` crashes with empty property names.
- **Bid Behavior**: Differentiates behavior between "Traditional" and "Bidding Period" listings for timer display, action button availability, and bid visibility.
- **Service Display**: Normalizes curly apostrophes in stored service strings to match config values using a `$normalizeStr` function.
- **Agent Bid Auto-fill**: `broker_fee_timing` fields are auto-filled in `TenantAgentAuctionBid::mount()` for new and edited bids.
- **Agent Info Preservation**: Agent profile seeding is gated to new bids only; edit mode loads agent info from bid data with fallbacks.
- **Agent Self-View**: Agents viewing their own bid see all 6 sections (Agent Overview & Qualifications, Broker Compensation & Agency Agreement Terms, Additional Details, Offered Services, Agent Presentation & Marketing Materials, Agent Credentials & Contact Information).
- **Timer-Based Action Locking**: Accept/Counter/Reject buttons are locked until the bidding period timer ends for "Bidding Period" listings.
- **Livewire Str::studly() Crash Fix**: All `updated($propertyName)` methods include an empty guard (`if (empty($propertyName)) { return; }`) to prevent crashes when Livewire passes blank property names. Applied to: TenantAgentAuction.php, TenantAgentAuctionEdit.php, TenantAgentAuctionBid.php, BuyerAgentAuctionBid.php, LandlordAgentAuctionBid.php.
- **Conditional Field Re-rendering**: All conditional sections in broker-compensation.blade.php use `wire:key` attributes via the `$safeKey` helper function (e.g., `wire:key="{{ $safeKey('purchase-fee-section', $interested_purchase_fee_type) }}"`). The $safeKey helper generates stable, sanitized keys by converting input values to lowercase and removing non-alphanumeric characters. This ensures proper Livewire re-rendering when controlling field values change for "Yes/No" dropdowns and "Other" options. For dynamic arrays like other_services, use simple index-based keys (e.g., `wire:key="other_service_{{ $i }}"`).
- **Edit Bid File Preservation**: In edit mode, existing uploaded files (promoMaterials) are preserved and displayed with thumbnails/icons. Users can delete individual files via trash button. File deletions are validated for ownership (only files belonging to the current bid) and directory bounds before removal from storage.
- **Accepted Bid Summary System**: When a Hire a Tenant bid is accepted (directly or via counter-bid), an Accepted Bid Summary is auto-generated using the `AcceptedBidSummaryService`. The summary includes: (1) Parties info, (2) Listing/Criteria Details, (3) Services grouped by category, (4) Broker Compensation & Agency Agreement Terms, (5) Additional Details, (6) Important Notice, (7) Platform Referral Disclosure, (8) Signature Acknowledgement. Summary generation failures are logged but don't block bid acceptance.
- **E-Signature Acknowledgement**: Both tenant and agent can e-sign the Accepted Bid Summary. Signatures capture typed name, timestamp, and IP address. The signed PDF is generated only after both parties have acknowledged. Stored in `accepted_bid_summaries` table.
- **Match Score System**: Agent bids display a Match Score comparing their terms to the tenant's baseline (original terms or latest counter). Compares Broker Compensation fields and Offered Services (including other_services). Scores are color-coded: Green (≥80%), Yellow (≥50%), Red (<50%).
- **Notification System**: Comprehensive notifications for bid lifecycle events: `bid_received` (agent submits), `bid_countered` (either party counters), `bid_rejected` (tenant rejects). Dashboard displays up to 20 unread notifications with icons (gavel, exchange-alt, times-circle, check-circle).
- **View Counter Terms Page**: Agents can view counter terms directly from My Bids page or dashboard notifications via `/tenant/hire/agent/auction/bid/{bid_id}/view-counter`. Displays tenant's counter offer with broker compensation terms, requested services, and action buttons (Accept, Reject, Counter Back).
- **My Bids Interface**: Static cards with color-coded status badges (Active=blue, Countered=warning, Accepted=green, Rejected=danger). Context-specific action buttons: View Summary (accepted), View Counter (countered), View Listing (rejected), Visit Listing (active).
- **Tenant Dashboard Pending Bids**: Tenants see a dedicated "Pending Agent Bids" section on their dashboard showing all active/countered bids across their listings. Table includes Agent name, Listing, Status, Submitted date, and action buttons (Accept/Counter/Reject for active bids, "Awaiting" indicator for countered bids).
- **Tenant Sidebar Agent Bids**: For tenant users, the sidebar displays "Agent Bids" instead of "My Bid" (since tenants don't make bids, only agents do). Links directly to the agent-bids view with a dynamic badge showing pending bid count. For non-tenants, the original "My Bid" tab with full tab navigation is preserved.
- **Bidding Period Transparency**: For Bidding Period listings, agents who have submitted a bid can view anonymized competing bids. Visibility is limited to Broker Compensation & Agency Agreement Terms, Offered Services, and Match Scores only. Agent identities are anonymized using random numbers (1-999) stored in `bidding_period_agent_mappings` table. Match Scores are calculated comparing each bid to the viewer's own bid (with "Compared to Your Bid" label). Updated bids show an "Updated" badge (no timestamps). Disclosure notices appear on both listing creation (Bidding Period only) and bid submission forms. Traditional listings remain fully private with no agent-to-agent visibility.
- **Inline Competing Bids Display**: Competing bids auto-display directly on the listing page once an agent submits a valid bid (no "View Competing Bids" button). Each card shows: anonymous label (Agent 1/2/3...), overall Match Score badge, Broker Compensation match % + X/Y fields matched, Services match % + X/Y services matched, and "Compared to Your Bid" footer. "Last bidder" callouts are hidden for Bidding Period listings to avoid timing hints. Submit-to-view rule enforced: locked message shown until bid is submitted.
- **Bid Modification Notifications**: When agents edit their active bids, tenants receive `bid_modified` notifications alerting them to review the updated bid terms.
- **Full Agent Bid Preview Page**: Dedicated preview page at `/tenant/agent-bids/{bidId}` for tenants to view complete bid details before taking action. Displays all six sections (Agent Overview & Qualifications, Broker Compensation with mismatch highlighting, Offered Services with matched/extra/missing breakdown, Additional Details, Agent Presentation & Promotional Materials, Agent Credentials & Contact Information). Includes Match Score with detailed breakdown comparing bid to listing terms. Action buttons (Accept/Counter/Reject) are conditionally displayed based on bid status. Only the listing owner can access the preview (authorization enforced in controller).
- **Landlord Agent Field Ordering**: Broker Compensation & Agency Agreement Terms fields are displayed in a standardized order across all Hire a Landlord's Agent views (listing creation, agent bids, listing display). The canonical order is: 1) Landlord's Broker Lease Fee, 2) Tenant's Broker Commission, 3) Payment Timing, 4) Lease Renewal/Extension, 5) Expansion Commission, 6) Property Management, 7) Lease-Option, 8) Interested in Selling, 9) Protection Period, 10) Early Termination, 11) Agency Agreement Timeframe, 12) Brokerage Relationship, 13) Additional Terms. Config at `config/landlord-comp-terms.php` defines canonical order arrays. Blade partials extracted to `partials/` subdirectories for maintainability.
- **Cross-Profile Listing Support**: Tenant users can create listings for all agent types (Tenant, Seller, Buyer, Landlord). Dashboard tabs show all four listing types under: "Hiring Tenant's Agent" (type=0), "Hiring Seller's Agent" (type=1), "Hiring Buyer's Agent" (type=2), "Hiring Landlord's Agent" (type=3). Listings filter by user_id to show only the logged-in user's listings.
- **Seller Agent Listing Database Structure**: `seller_agent_auctions` table requires `title` and `is_draft` columns. `seller_agent_auction_metas` table stores listing metadata with foreign key to `seller_agent_auctions`. Migrations added: `create_seller_agent_auction_metas_table`, `add_missing_columns_to_seller_agent_auctions_table`.
- **Owner-Specific Listing Visibility**: UserController::author() uses `Auth::id() === $user->id` check to allow listing owners to see their own unapproved listings, while other visitors only see approved listings. This maintains moderation for public views while ensuring owners can find their recently submitted listings.
- **Seller Agent Workflow Enhancements**: SellerAgentAuction Livewire component includes: (1) Tooltip close behavior with Escape key and click-outside dismissal, (2) HTML bullet points for Brokerage Relationship tooltip matching Buyer format, (3) Separate $applianceOptions array to prevent Livewire property conflicts with Select2, (4) handleUpdatePreference Livewire listener for syncing view_preference updates, (5) Full persistence for 8 Assumable sub-fields (monthly_escrow, loan_terms, loan_origination_date, servicer, assumption_fee, release_of_liability_fee, occupancy_requirement, occupancy_requirement_other), (6) zip_code field persistence in saveAllMetadata and loadDraft methods.

### System Design Choices
- **Modularity**: Laravel's modular structure with Livewire components for UI interactions.
- **Database-First Approach**: Prioritizes local database solutions for core services like location.
- **Clear Separation of Concerns**: Distinct roles for frontend asset compilation, backend logic, and data persistence.
- **Deployment Ready**: Configuration includes optimizations for production environments.
- **Database Schema Safeguard**: Existing database fields, primary key ID types, and storage logic for fees are immutable unless explicitly instructed. All fee format updates are display-only.

## External Dependencies
- **PostgreSQL**: Primary database.
- **TailwindCSS**: CSS framework.
- **AlpineJS**: JavaScript framework for UI interactivity.
- **Laravel Mix**: Used for compiling frontend assets.
- **U.S. Census Gazetteer Files**: Source for seeding local geographical databases (`us_states`, `us_counties`, `us_cities`).
- **Google Places API**: Used for specific address and postal code validation.