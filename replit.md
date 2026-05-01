# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to enhance transparency and efficiency in the real estate market. It supports various auction types (property, buyer, seller, landlord, tenant agent auctions) by providing functionalities for property listing, bid management, and agent interaction. The platform's core purpose is to streamline property sales through a transparent bidding system, aiming to modernize property transactions and offer clear market insights.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## GLOBAL BUILD RULE — Limited Service Is Out of Scope

**Do NOT modify Limited Service listing flows unless explicitly requested.**

Limited Service / flat-fee / legacy paths are frozen. All listing edits, field changes, tab changes, UI changes, uploads, MLS fields, and validation changes apply to **Full Service only**.

Do not edit:
- Limited Service listing blades or tab partials
- Limited Service Livewire logic or panes
- Flat-fee / limited-service paths
- Any commented-out Limited Service code or legacy limited-service UI

If a shared file affects both Full Service and Limited Service:
- Do not blindly edit it.
- Isolate the change to Full Service only.
- If isolation is not possible, stop and report before making any changes.

Before implementing any listing change, confirm:
1. Which files are being edited
2. Whether any Limited Service file/path is touched
3. How the change is isolated to Full Service only

## System Architecture
The platform is built on Laravel 8.x, PHP 8.2.23, and PostgreSQL, utilizing Laravel Mix with TailwindCSS, AlpineJS, and Livewire for dynamic frontend components. Bootstrap is used for UI elements. Location data is sourced from a U.S. Census-based local database.

The architecture emphasizes modularity and reusability, with Livewire components managing various agent auction types, form submissions, and data persistence. Drafts incorporate an append-only versioning system. Dynamic model resolution is used for auction models, and numeric inputs are sanitized. Configuration files centralize display logic, and helper services standardize data presentation. Conditional rendering of UI fields is based on user selections. PDF listing packets are generated using `barryvdh/laravel-dompdf`. Select2 and Livewire are integrated for dynamic multi-select fields with a JSON Bridge Pattern for data synchronization. Wizard navigation uses a delegation pattern for tab transitions and validation.

Edit forms feature a comprehensive validation strategy supporting "Save Draft" for partial saves and "Save Edit/Submit" for full validation. Immutability rules are enforced for critical fields through disabled inputs and server-side re-validation.

The non-agent client dashboard displays "My Listings by Role" and "Your Hired Agent" sections. The "My Listings" hub provides a consolidated view of all user-created listings, grouped by role. The Dashboard Messaging system uses Blade and jQuery AJAX for real-time chat with client-side polling.

Match score helpers (e.g., `TenantBidMatchScoreHelper`) use a logical field group approach to compare terms. A Default Profile system allows agents to save and reuse bid profile data across all bid forms, managed by the `AgentBidMapperService`. The "Hire Me / Hire This Agent" direct entry flow enables clients to hire an agent, atomically creating a listing and an auto-bid, seeding bid fields from the agent's default profile.

Bid forms are implemented as Livewire wizards, with consistent bid display systems on view pages, including sortable bid accordion cards and private data modals. The Accepted Bid Summary System ensures consistent PDF cache invalidation. A shared Blade component (`x-bid-detail-layout`) provides consistent UI chrome for bid detail pages. The sidenav uses a role-scoped structure for navigation links.

The Seller and Landlord Offer Listing forms (Full Service only) include a Photos/Tours/Documents tab with multi-photo upload (up to 50 photos enforced server-side), drag-and-drop reorder via SortableJS, Move Up/Down fallback buttons, a cover-photo badge on the first photo, and helper text. Photo filenames are stored as a JSON array in the `property_photos` meta key. Methods `reorderPhotos`, `movePhotoUp`, and `movePhotoDown` are implemented on `SellerOfferListing` and `LandlordOfferListing` Livewire components. The shared blade partial is null-safe for limited-service edit contexts where those properties are absent.

The Seller and Landlord Offer Listing forms (Full Service only) include a "Tax, Legal, HOA & Disclosures" tab inserted at index 4 (full-service only). The tab contains five card groups: Tax/Legal/Parcel, Flood Zone, CDD/Special Assessments, Structured HOA/Association, and Documents & Disclosures. All fields persist via EAV `saveMeta`/`loadDraft` in `LandlordOfferListing.php` and `SellerOfferListing.php`. The three Select2 multi-select fields (`association_fee_includes`, `association_amenities`, `additional_documents`) are wired into `syncLandlordSelect2BeforeSave()` (landlord) and the equivalent seller sync function. Landlord-specific adaptations: "Association Approval Required for Tenancy" (not Purchase), "Landlord Disclosure Available" (not Seller). Full-service Landlord wizard tab order: 0=Listing Details, 1=Property Details, 2=Leasing Terms, 3=Additional Details, 4=Tax/Legal/HOA/Disclosures, 5=Photos/Tours/Documents, 6=Agent Credentials, 7=AI Questions. Limited Service tab order is unchanged (4=Photos, 5=Agent Credentials, 6=AI Questions). Tab partial: `resources/views/livewire/offer-listing/offer-landlord-tabs/commission-based/tax-legal-hoa-disclosures.blade.php`.

The Agent Hire Listings Hub (`/agent/hire-listings`) provides an agent-only consolidated and filterable view of all four listing types belonging to the agent. A `workflow_type` meta key (`'hire_agent'` | `'offer'`) tracks listing type, and a `HasListingLifecycle` trait provides shared draft/publish functionality. Offer listings are managed by a separate `OfferAuction` Livewire component and model, with its own wizard and dedicated hub. Agent-specific fields like `referral_percentage` and agent credentials are managed via the existing meta key-value system and conditionally displayed.

The Profile Settings page provides a multi-section Bootstrap accordion for managing account information, profile details, preferences, privacy & security, and account deletion. The Agent Preset Management UI (`/agent/presets`) allows agents to create and manage default offer presets per role × property type. Presets store services, bio, credentials, and links in `AgentDefaultProfile.profile_data` (JSON). The `AgentPresetCatalog` service provides all service strings, fully synced to match listing creation service view partials. The edit form has four accordion sections. An "Additional Services" subsection allows agents to add free-text custom services. These are applied to bid forms alongside catalog-checked services via `AgentBidMapperService`. A "My Offer Presets" sidenav link is shown to all agent users. The preset edit page includes an "Open Hire Me Page" button and a "Preview Hire Me Page" link upon saving.

A clean public Hire Me URL layer adds `/hire/{agentShortId}/{role}/{propertyType?}` as a shareable route, resolving the agent by `users.short_id` and redirecting to the existing internal `hire.agent.direct.preview` route. Preset cards on the index page display the clean URL path, Copy Link, Open Link, and Copy Embed Code actions, gated to usable presets.

A lightweight, public, auth-free read-only widget is available at `/widget/hire/{agentShortId}/{role}/{propertyType}`. Served by `WidgetController::show()`, it returns a standalone HTML card with agent details and a "Hire This Agent" CTA linking to the clean public URL. Invalid presets return a 404 "unavailable" card. Both widget views set `X-Frame-Options: ALLOWALL` for embedding.

The My Referrals agent page (`/agent/my-referrals`) provides a full, filterable referral activity view for agents. It shows summary metric cards (Clicks, Signups, Listings, Hires) drawn from `agent_referral_links` counters, plus a per-row activity table built by merging four read-only queries: `referral_visits`, `users` (signups), all five listing tables, and `accepted_bid_summaries`. A `?stage=` GET parameter filters activity. A "My Referrals" sidebar item appears in the agent-only "Referral Dashboard" sidenav section.

Phase 5 — Client-Requested Custom Services: The hire preview page (`resources/views/hire-agent-direct/preview.blade.php`) includes a new "Additional Services You'd Like to Request" section with a textarea (`name="client_custom_services"`, newline-separated). `HireAgentDirectController::confirm()` parses the textarea into a deduplicated array (max 50 entries), saves it as JSON to listing meta key `client_custom_services` AND bid meta key `client_custom_services`. The four hire listing detail views (`hire_seller_agent/view.blade.php`, `hire_landlord_agent/view.blade.php`, `hire_tenant_agent/view.blade.php`, `buyerAgentAuctionDetail.blade.php`) display a read-only "📋 Client Requested Services" bullet list after the agent's "✍️ Additional Services" block. `client_custom_services` is strictly isolated: never merged into `services[]` or `other_services[]`, never included in match scoring, never touched by counter/accept/reject logic. A pre-existing crash (`str_starts_with` receiving an array for `website_link`) was also fixed in `partials/bid_detail_body/seller.blade.php`, `partials/bid_detail_body/landlord.blade.php`, `hire_tenant_agent/view.blade.php`, and `tenant_agent/bid_preview.blade.php` — `AgentBidMapperService` maps `website_link` as an array, and all four views now coerce it to a string before calling `str_starts_with`.

Schema Architecture Note — Listing Table Asymmetry: `seller_agent_auctions` and `buyer_agent_auctions` have native `address` and `title` columns. `landlord_agent_auctions` and `tenant_agent_auctions` do NOT — all data for these models is stored via EAV meta. `HireAgentDirectController::confirm()` uses `Schema::hasColumn()` to guard column assignments and falls back to `saveMeta()` for address/title on landlord/tenant. Two previously missing meta tables were created via migration: `landlord_agent_auction_metas` (FK: `landlord_agent_auction_id`) and `landlord_agent_auction_bid_metas` (FK: `landlord_agent_auction_bid_id`). These were silently absent from the DB schema; their corresponding Eloquent models (`LandlordAgentAuctionMeta`, `LandlordAgentAuctionBidMeta`) already existed. Any future code that calls `saveMeta()` on `LandlordAgentAuction` or `LandlordAgentAuctionBid` instances now works correctly.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: Utility-first CSS framework.
- **AlpineJS**: Lightweight JavaScript framework for reactive UI.
- **Laravel Mix**: Webpack wrapper for asset compilation.
- **U.S. Census Gazetteer Files**: Geographical data for location autofill.
- **Google Places API**: Used for address and postal code validation.
- **barryvdh/laravel-dompdf**: Library for generating PDFs.