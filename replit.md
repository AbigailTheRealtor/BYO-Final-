# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform that aims to bring transparency and efficiency to the real estate market. It facilitates various auction types (property, buyer, seller, landlord, tenant agent auctions) by providing robust features for property listing, bid management, and agent interaction. The platform's core purpose is to streamline property transactions through a transparent bidding system, offering clear market insights and modernizing the property sales process.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture
The platform is built using Laravel 8.x, PHP 8.2.23, and PostgreSQL. Frontend development leverages Laravel Mix with TailwindCSS, AlpineJS, and Livewire for dynamic components, complemented by Bootstrap for UI elements. Location data is sourced from a U.S. Census-based local database.

The architecture emphasizes modularity and reusability, utilizing Livewire components for managing different agent auction types, form submissions, and data persistence. Key features include an append-only versioning system for drafts, dynamic model resolution for auction models, and numeric input sanitization. Display logic is centralized in configuration files, with helper services standardizing data presentation. UI fields render conditionally based on user selections. PDF listing packets are generated using `barryvdh/laravel-dompdf`. Select2 and Livewire are integrated for dynamic multi-select fields via a JSON Bridge Pattern. Wizard navigation uses a delegation pattern for tab transitions and validation, with comprehensive validation for both partial "Save Draft" and full "Save Edit/Submit" operations. Immutability for critical fields is enforced through disabled inputs and server-side re-validation.

The non-agent client dashboard provides "My Listings by Role" and "Your Hired Agent" sections, offering a consolidated view of user-created listings. The Dashboard Messaging system facilitates real-time chat via Blade and jQuery AJAX. Match score helpers (e.g., `TenantBidMatchScoreHelper`) compare terms using a logical field group approach. An Agent Default Profile system, managed by `AgentBidMapperService`, allows agents to save and reuse bid profile data. A "Hire Me / Hire This Agent" direct entry flow enables clients to hire an agent, automatically creating a listing and an auto-bid based on the agent's default profile.

Bid forms are implemented as Livewire wizards, featuring consistent bid display systems on view pages, including sortable bid accordion cards and private data modals. The Accepted Bid Summary System ensures consistent PDF cache invalidation, using a shared Blade component (`x-bid-detail-layout`) for consistent UI. The sidenav uses a role-scoped structure for navigation links.

Seller and Landlord Offer Listing forms (Full Service only) include a "Photos/Tours/Documents" tab supporting multi-photo upload (up to 50 photos), drag-and-drop reordering with SortableJS, and cover photo designation. Photo filenames are stored as a JSON array in the `property_photos` meta key. A "Tax, Legal, HOA & Disclosures" tab (Full Service only) includes sections for Tax/Legal/Parcel, Flood Zone, CDD/Special Assessments, Structured HOA/Association, and Documents & Disclosures. Fields persist via EAV `saveMeta`/`loadDraft`. Conditional file uploads are supported, with temporary properties and corresponding persisted properties for various disclosure documents. File uploads are stored on the public disk under `{role}-disclosures/{id}/{type}/`.

The Agent Hire Listings Hub (`/agent/hire-listings`) provides an agent-only filterable view of all four listing types, tracked by a `workflow_type` meta key. Offer listings are managed by a separate `OfferAuction` Livewire component and model. Agent-specific fields like `referral_percentage` are managed via the meta key-value system and conditionally displayed.

Profile Settings offer a multi-section Bootstrap accordion for managing account information. The Agent Preset Management UI (`/agent/presets`) allows agents to create and manage default offer presets per role and property type, storing services, bio, credentials, and links in `AgentDefaultProfile.profile_data` (JSON). Custom services can be added by agents. A clean public Hire Me URL layer (`/hire/{agentShortId}/{role}/{propertyType?}`) provides shareable links, resolving agents by `users.short_id`. A lightweight, public, auth-free read-only widget (`/widget/hire/{agentShortId}/{role}/{propertyType}`) provides embeddable agent details with a "Hire This Agent" CTA.

The My Referrals agent page (`/agent/my-referrals`) provides a filterable view of referral activity, including summary metric cards and a per-row activity table merged from `referral_visits`, `users`, listing tables, and `accepted_bid_summaries`. Client-Requested Custom Services are supported in the hire preview page, saved as JSON to `client_custom_services` meta keys for listings and bids, and displayed as read-only bullet points. Schema architecture for listing tables notes asymmetry where `seller_agent_auctions` and `buyer_agent_auctions` have native columns, while `landlord_agent_auctions` and `tenant_agent_auctions` store data via EAV meta. Missing meta tables (`landlord_agent_auction_metas`, `landlord_agent_auction_bid_metas`) have been added.

U.S. geography data for City/County/State auto-population uses `UsCity`, `UsState`, `UsCounty`, and `UsZipCode` models.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: Utility-first CSS framework.
- **AlpineJS**: Lightweight JavaScript framework for reactive UI.
- **Laravel Mix**: Webpack wrapper for asset compilation.
- **U.S. Census Gazetteer Files**: Geographical data for location autofill.
- **Google Places API**: Used for address and postal code validation.
- **barryvdh/laravel-dompdf**: Library for generating PDFs.