# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to enhance transparency and efficiency in the real estate market. It supports various auction types (property, buyer, seller, landlord, tenant agent auctions) by providing functionalities for property listing, bid management, and agent interaction. The platform's core purpose is to streamline property sales through a transparent bidding system, aiming to modernize property transactions and offer clear market insights.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture
The platform is built on Laravel 8.x, PHP 8.2.23, and PostgreSQL. The frontend uses Laravel Mix with TailwindCSS, AlpineJS, and Livewire for dynamic content, complemented by Bootstrap for UI components. Location data is sourced from a U.S. Census-based local database.

The architecture emphasizes modularity and reusability, utilizing Livewire components for managing various agent auction types, handling form submissions, and data persistence. Drafts incorporate an append-only versioning system. Dynamic model resolution is used for auction models, and numeric inputs are sanitized. Configuration files centralize display logic, and helper services standardize data presentation. Conditional rendering of UI fields is based on user selections. PDF listing packets are generated using `barryvdh/laravel-dompdf`. Select2 and Livewire are integrated for dynamic multi-select fields with a JSON Bridge Pattern for data synchronization. Wizard navigation uses a delegation pattern for tab transitions and validation.

Edit forms feature a comprehensive validation strategy supporting "Save Draft" for partial saves and "Save Edit/Submit" for full validation. Immutability rules are enforced for critical fields through disabled inputs and server-side re-validation.

The non-agent client dashboard displays a "My Listings by Role" summary grid and a "Your Hired Agent" section showing `AcceptedBidSummary` records. The "My Listings" hub provides a consolidated view of all user-created listings, grouped by role.

The Dashboard Messaging system uses Blade and jQuery AJAX for real-time chat, storing messages in `auction_chat` and employing client-side polling for live updates.

Match score helpers (e.g., `TenantBidMatchScoreHelper`) use a logical field group approach to compare terms, with rules for cascading deactivation and composite value generation.

A Default Profile system allows agents to save and reuse bid profile data across all bid forms, managed by the `AgentBidMapperService`. The Phase-2 "Hire Me / Hire This Agent" direct entry flow enables clients to hire an agent, atomically creating a listing and an auto-bid within a database transaction, seeding bid fields from the agent's default profile.

Bid forms are implemented as Livewire wizards, with consistent bid display systems on view pages, including sortable bid accordion cards and private data modals. The Accepted Bid Summary System ensures consistent PDF cache invalidation.

A shared Blade component (`x-bid-detail-layout`) provides consistent UI chrome for all bid detail pages. The sidenav uses a role-scoped structure for navigation links.

The Agent Hire Listings Hub (`/agent/hire-listings`) provides an agent-only consolidated and filterable view of all four listing types belonging to the agent. A `workflow_type` meta key (`'hire_agent'` | `'offer'`) tracks listing type, and a `HasListingLifecycle` trait provides shared draft/publish functionality. Offer listings are managed by a separate `OfferAuction` Livewire component and model, with its own wizard and dedicated hub.

Agent-specific fields like `referral_percentage` and agent credentials (first name, last name, phone, email, brokerage, license, NAR ID) are managed via the existing meta key-value system and conditionally displayed in the UI based on user type.

The Profile Settings page provides a multi-section Bootstrap accordion for managing account information, profile details, preferences, privacy & security, and a delete account option.

The Phase-3 Agent Preset Management UI (`/agent/presets`) allows agents to create and manage default offer presets per role × property type. Presets store services, bio, credentials, and links in `AgentDefaultProfile.profile_data` (JSON). The `AgentPresetCatalog` service provides all service strings for all 14 combinations (buyer/seller × 5 property types, tenant/landlord × 2 property types). The `AgentPresetController` handles index, edit, and save actions. The edit form has four accordion sections: Services (always open), Agent Overview (open by default), Agent Credentials (collapsed), and Presentation & Links (collapsed). A "My Offer Presets" sidenav link is shown to all agent users.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: Utility-first CSS framework.
- **AlpineJS**: Lightweight JavaScript framework for reactive UI.
- **Laravel Mix**: Webpack wrapper for asset compilation.
- **U.S. Census Gazetteer Files**: Geographical data for location autofill.
- **Google Places API**: Used for address and postal code validation.
- **barryvdh/laravel-dompdf**: Library for generating PDFs.