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
- **Location Data**: Integrated U.S. Census-based `us_states`, `us_counties`, and `us_cities` databases.
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