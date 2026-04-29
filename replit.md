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

The Phase-3 Agent Preset Management UI (`/agent/presets`) allows agents to create and manage default offer presets per role × property type. Presets store services, bio, credentials, and links in `AgentDefaultProfile.profile_data` (JSON). The `AgentPresetCatalog` service provides all service strings for all 14 combinations (buyer/seller × 5 property types, tenant/landlord × 2 property types) — fully synced to match the listing creation service view partials for all combinations. The `AgentPresetController` handles index, edit, and save actions. The edit form has four accordion sections: Services (always open), Agent Overview (open by default), Agent Credentials (collapsed), and Presentation & Links (collapsed). The Services section includes an "Additional Services" subsection at the bottom (after the service checklist grid) where agents can add, remove, and save any number of free-text custom services (stored as `other_services` array in `profile_data`, max 500 chars each). These are applied to bid forms alongside the catalog-checked services via `AgentBidMapperService`. A "My Offer Presets" sidenav link is shown to all agent users. The preset edit page shows an "Open Hire Me Page" button next to "Back to All Presets": disabled before first save, active link once a preset exists. After saving, the controller redirects to the same edit URL with `?saved=1`, which triggers a green success alert containing a "Preview Hire Me Page" link pointing to `/hire/{agentShortId}/{role}/{propertyType}`.

The Phase-4 Clean Public Hire Me URL layer adds `/hire/{agentShortId}/{role}/{propertyType?}` as a clean shareable route (named `hire.agent.public`). It resolves the agent by the `users.short_id` field (hex-only, URL-safe, 100% agent coverage) and redirects to the existing internal `hire.agent.direct.preview` route — no controller logic is duplicated. A `->where('agentShortId', '[0-9a-f]+')` constraint prevents conflicts with all existing `/hire/agent/...`, `/hire/seller/...` routes. The `showPublic()` method lives in `HireAgentDirectController`. Preset cards on the index page display the clean URL path, Copy Link, Open Link, and Copy Embed Code actions — all gated to usable presets (services > 0).

The Phase-5 Widget/Embed layer adds a lightweight, **public, auth-free** read-only widget at `/widget/hire/{agentShortId}/{role}/{propertyType}` (named `hire.agent.widget`). Served by `WidgetController::show()`, it resolves the agent and preset, returns a standalone HTML card (no `@extends`, no nav/header) with agent name, role, property type, brokerage, service count, and a "Hire This Agent" CTA linking to the clean public URL via `target="_top"`. Invalid/useless presets return a 404 "unavailable" card. Both views set `X-Frame-Options: ALLOWALL` so any external site can embed them. The embed code is a simple `<iframe>` snippet surfaced via the "Copy Embed Code" button on usable preset cards.

The **My Referrals** agent page (`/agent/my-referrals`, named `agent.my-referrals`) is served by `AgentReferralPageController` and provides a full, filterable referral activity view for agents. It shows summary metric cards (Clicks, Signups, Listings, Hires) drawn from the stored `agent_referral_links` counters, plus a per-row activity table built by merging four read-only queries: `referral_visits` (clicks, LEFT JOIN users on `visitor_user_id`), `users` (signups via `referred_by_agent_id`), all five listing tables (listings via `referring_agent_id`), and `accepted_bid_summaries` (hires via `referring_agent_id`). A `?stage=` GET parameter filters to clicks/signups/listings/hires or shows all. A "My Referrals" sidebar item appears in the agent-only "Referral Dashboard" sidenav section. The page is strictly read-only and does not touch any attribution logic or counters.

## Test-Safe Schema Baseline

The following conventions are in place so that `User::factory()->create()` and feature tests work reliably without `RefreshDatabase` failures:

- **`users.user_type` check constraint** includes `'agent'` (added by migration `2026_04_29_000001_add_agent_to_users_user_type_check`). The base `create_users_table` migration also reflects this so `migrate:fresh` on a blank DB is correct.
- **`UserObserver::creating()`** no longer sets `phone_number` (the DB column is `phone`; `phone_number` column does not exist until migration `2024_10_15_130859` runs).
- **`UserFactory`** provides all NOT NULL columns directly or via observer/DB defaults. States: `asAgent()`, `asAdmin()`, `asBuyerAgent()`, `asSellerAgent()`. Supply `short_id` as an override to `create()` when deterministic URLs are needed.
- **Five `2026_04_28` migrations** that lacked `hasTable`/`hasColumn` guards have been hardened. Two target tables with no CREATE migration (`tenant_criteria_auctions`, `landlord_auctions`) — these migrations silently skip when those tables do not exist.
- **Feature tests** use `DatabaseTransactions` (not `RefreshDatabase`) because several migrations are still pending; `DatabaseTransactions` wraps each test in a single transaction (including DDL in PostgreSQL) and rolls everything back on teardown.
- **Tables migrated for the test environment** (were pending): `user_meta`, `agent_default_profiles`, `settings`, `notifications`.

## Development Accounts

All accounts use password `12345678`. Managed by `database/seeders/UserSeeder.php` (idempotent — safe to re-run anytime). To restore after a DB reset: `php artisan db:seed --class=UserSeeder`.

| Email | user_type | Purpose |
|---|---|---|
| admin@exp.com | admin | Admin panel |
| seller@exp.com | seller | Seller listings |
| seller_agent@exp.com | seller_agent | Seller agent bids |
| buyer@exp.com | buyer | Buyer listings |
| buyer_agent@exp.com | buyer_agent | Buyer agent bids |
| tenant@exp.com | tenant | Tenant criteria listings |
| john@exp.com | agent | General agent account |
| johnlong@exp.com | agent | Agent profile / Hire Me page testing |
| abigailbaschuk@gmail.com | agent | Agent preset & direct hire testing |

## Database Reset — Known Risk & Safeguards

**Root cause (April 2026):** Replit's managed PostgreSQL container (`helium:5432`) was cold-restarted between sessions, wiping all rows. The table schema was re-created by subsequent migration runs, but no seed data was preserved. No destructive commands (`migrate:fresh`, `db:wipe`) were run in code or shell history.

**Safeguards in place:**
- `UserSeeder` uses `firstOrCreate()` — idempotent, never duplicates rows.
- `scripts/post-merge.sh` runs `php artisan db:seed --class=UserSeeder` automatically after every code merge, so dev accounts survive merges even after a DB reset.
- If the DB is wiped manually or by a platform restart, run `php artisan db:seed --class=UserSeeder` to restore all dev accounts.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: Utility-first CSS framework.
- **AlpineJS**: Lightweight JavaScript framework for reactive UI.
- **Laravel Mix**: Webpack wrapper for asset compilation.
- **U.S. Census Gazetteer Files**: Geographical data for location autofill.
- **Google Places API**: Used for address and postal code validation.
- **barryvdh/laravel-dompdf**: Library for generating PDFs.