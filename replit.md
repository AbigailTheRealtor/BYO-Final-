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

### System Design Choices
The architecture emphasizes modularity through Laravel's structure and Livewire components. A database-first approach prioritizes local database solutions for core services like location. Clear separation of concerns is maintained between frontend, backend, and data persistence. The system is deployment-ready with production environment optimizations. Existing database schema and storage logic for fees are immutable, with fee format updates being display-only.

## External Dependencies
- **PostgreSQL**: The primary relational database.
- **TailwindCSS**: CSS framework for styling.
- **AlpineJS**: JavaScript framework for declarative UI.
- **Laravel Mix**: Tool for compiling frontend assets.
- **U.S. Census Gazetteer Files**: Source for geographical data (states, counties, cities).
- **Google Places API**: Used for specific address and postal code validation.