# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to enhance transparency and efficiency in the real estate market. It supports various auction types (property, buyer, seller, landlord, tenant agent auctions) by providing functionalities for property listing, bid management, and agent interaction. The platform's core purpose is to streamline property sales through a transparent bidding system.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture
The platform is built on Laravel 8.x, PHP 8.2.23, and PostgreSQL. The frontend utilizes Laravel Mix with TailwindCSS, AlpineJS, and Livewire for dynamic content, complemented by Bootstrap for UI components like tab navigation. Location data is sourced from a U.S. Census-based local database.

The architecture emphasizes modularity and reusability, with a shared `TenantAgentAuction.php` Livewire component managing various agent auction types, handling form submissions, draft saving, and metadata persistence. Drafts incorporate an append-only versioning system. Dynamic model resolution is employed for selecting auction models, and numeric inputs are sanitized. Configuration files centralize display logic, and helpers like `ServicesFormatter` and `ListingDisplayHelper` standardize data presentation. Conditional rendering of UI fields is based on user selections and listing types. PDF listing packets are generated using `barryvdh/laravel-dompdf`, utilizing dedicated field maps and a universal Blade template. Select2 and Livewire are integrated for dynamic multi-select fields, with a JSON Bridge Pattern ensuring data synchronization. Wizard navigation uses a delegation pattern for tab transitions and validation.

Edit forms feature a comprehensive validation strategy with "Save Draft" (allowing partial saves) and "Save Edit/Submit" (requiring full validation) actions. Immutability rules are enforced for critical fields through disabled inputs and server-side re-validation. Server-side PHP validation acts as a safety net. Conditional visibility for seller sale terms is managed by specific functions ensuring UI updates and server synchronization.

A bidding period timer is implemented for various listing views. Buyer agent search filters for approved, non-draft listings.

Match score helpers (`TenantBidMatchScoreHelper`, `BuyerBidMatchScoreHelper`, `SellerBidMatchScoreHelper`, `LandlordBidMatchScoreHelper`) use a logical field group approach, defining 14-17 groups for terms matching. Key rules include cascade deactivation for child groups when a parent field is negative, and composite value generation for comparing database sub-fields.

A Default Profile system allows agents to save and reuse bid profile data (bio, marketing plan, etc.) across all four bid forms. These profiles are stored in the `agent_default_profiles` table, with dedicated helper methods for retrieval and updates. All bid forms are Livewire components that auto-load matching default profiles on mount.

The Seller Agent Bid form is a 6-tab Livewire wizard with partials for different commission types. The Seller Agent bid view page implements a consistent bid display system with sortable bid accordion cards, Traditional vs. Bidding Period visibility rules, and a Private Data Modal for each bid, including Accept/Reject actions for the listing owner. Similar robust systems are implemented for Landlord and Buyer bid views, including match score helpers, accepted bid summary services, and notifications.

The Accepted Bid Summary System ensures consistent PDF cache invalidation across all four roles when summary HTML is regenerated.

The shared `x-bid-detail-layout` Blade component owns the header/body/footer chrome for all four bid detail pages (Buyer, Seller, Landlord, Tenant), eliminating duplication. It resolves a `$ms_*` variable mapping for the Tenant match score panel and handles the `listing_id` fallback for display codes.

The Profile Settings page (`/settings`) uses a clean 5-section Bootstrap accordion (proper `<button type="button">` triggers, no `data-bs-parent` for independent open/close). Sections: Account Information, Profile Details (with avatar picker), Preferences (contact method + best time to contact), Privacy & Security (password change with current-password verification). A permanently-visible Delete Account danger zone sits below the form with a `confirm()` dialog guard. The `saveSettings` controller uses `$request->has()` guards to prevent null-overwriting of fields not present in the submission. A `deleteAccount` controller method soft-deletes the user (`is_deleted=1`) and logs them out. Route: `POST /settings/delete-account` (name: `settings.delete-account`). CSS overrides scoped to `.mySettings` and pushed via `@stack('styles')` neutralize the global `myAccountGlobal.css` button rules. New meta fields: `preferred_contact_method`, `best_time_to_contact`.

## External Dependencies
- **PostgreSQL**: Primary relational database for data storage.
- **TailwindCSS**: Utility-first CSS framework for styling.
- **AlpineJS**: Lightweight JavaScript framework for reactive UI.
- **Laravel Mix**: Webpack wrapper for asset compilation.
- **U.S. Census Gazetteer Files**: Provides geographical data for location autofill.
- **Google Places API**: Used for address and postal code validation.
- **barryvdh/laravel-dompdf**: Library for generating PDFs from HTML.