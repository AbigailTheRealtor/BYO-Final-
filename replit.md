# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed for transparent property sales through bidding. It supports various auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline the real estate transaction process by providing robust listing, bidding, and agent interaction functionalities, enhancing transparency and efficiency in real estate transactions.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## Critical UI/Data Safeguards

### Canonical UI Source (MANDATORY)
The **Tenant-created "Broker Compensation & Agency Agreement Terms" form** (Residential & Commercial) is the **canonical reference** for all Agent workflows.

All Agent workflows must match this UI exactly:
- **Agent Bids**
- **Agent Counter-Bids**
- **Agent Bid Edits**

This includes:
- Identical field structure
- Identical input types (static flat-fee inputs, percent inputs only when applicable)
- Identical conditional logic
- Identical formatting and value handling

**DO NOT:**
- Introduce alternate input controls (e.g., $/% toggles) in Agent flows
- Change labels, field keys, or data structure
- Deviate from the Tenant form in any way

**Treat the Tenant form as the single source of truth.**

### Database Schema Safeguard (MANDATORY)
- **DO NOT** remove, rename, or repurpose existing database fields
- **DO NOT** change primary key ID column types
- Changes must be **UI-alignment only** unless explicitly instructed otherwise
- All fee format updates are **display-only** - no storage, save logic, or database schema modifications

## System Architecture

### UI/UX Decisions
- **Frontend Frameworks**: Uses Laravel Mix with TailwindCSS and AlpineJS for a responsive and modern interface.
- **Dynamic Content**: Leverages Livewire extensively for dynamic forms, real-time UI updates, and auto-population, minimizing page reloads.
- **Tab Navigation**: Implements a Bootstrap tab system for form organization, enforcing safe slug generation for tab IDs (`$safeSlug` function).
- **Location Autocomplete**: Utilizes a U.S. Census-based local database for states, counties, and cities, replacing Google Places API for cost-effectiveness and efficiency, with auto-population of related fields.
- **Listing Display**: Unique listing IDs are prominently displayed as badges. Pill badges for actions like delete are styled for enhanced visibility.

### Technical Implementations
- **Framework & Language**: Laravel 8.x with PHP 8.2.23.
- **Database**: PostgreSQL is the primary database, with Laravel migrations managing schema and handling optional features.
- **Frontend Tooling**: Node.js v20 is used for asset compilation via Laravel Mix.
- **Unique Listing IDs**: A `HasListingId` trait automatically generates unique, prefixed IDs (e.g., `TAA-XXXXXXXX`) for various auction types.
- **Location Data**: Integrated U.S. Census-based `us_states`, `us_counties`, and `us_cities` databases for all location-based functionalities.
- **Dynamic Form Handling**: Livewire components manage dynamic form fields, including conditional rendering and support for complex financing options and property preferences using Blade `@if` with `wire:key`.
- **Form Enhancements**: Standardized input fields, added required field indicators, phone auto-formatting, and dynamic visibility for "Other" text inputs across various selections. Expanded financing options with detailed sub-fields and conditional logic.

### System Design Choices
- **Modularity**: Adopts Laravel's modular structure for features, with Livewire components handling specific UI interactions.
- **Database-First Approach**: Prioritizes local database solutions for core services like location to ensure performance, reliability, and reduced external API dependency.
- **Clear Separation of Concerns**: Distinct roles for frontend asset compilation (Laravel Mix), backend logic (Laravel controllers, Livewire components), and data persistence (PostgreSQL).
- **Deployment Ready**: Configuration includes optimizations for production environments, such as Composer installs, NPM builds, and Laravel caching.

## External Dependencies
- **PostgreSQL**: Primary database.
- **TailwindCSS**: CSS framework.
- **AlpineJS**: JavaScript framework for UI interactivity.
- **Laravel Mix**: Used for compiling frontend assets.
- **U.S. Census Gazetteer Files**: Source for seeding local geographical databases (`us_states`, `us_counties`, `us_cities`).
- **Google Places API**: Used for specific address and postal code validation.
### Bootstrap Accordion Fix (December 2025)
- **Root Cause**: Bootstrap data-api conflicts caused accordion flash/close issue
- **Fix Applied**: Replaced Bootstrap `data-bs-toggle="collapse"` with custom JavaScript toggle using `e.stopPropagation()` and manual display toggling
- **Custom Accordion Pattern**: For bid accordions, use `.bid-accordion-header` class with `data-target` attribute and custom JS handler

### Combined Fee Display Format (December 2025)
- **Helper Functions**: Added `$fmtMoney`, `$fmtPercent`, `$joinParts`, `$basisText` at top of view.blade.php
- **Combined Format**: Fees now display as "$2,323 + 3% of Gross Lease Value" instead of separate lines
- **Sections Updated**: Tenant's Broker Commission Fee, Purchase Fee, Lease-Option Details, Termination Fee
- **No Storage Changes**: Display-only formatting, no database schema or save logic changes
- **Currency Normalization**: Uses `preg_replace('/[^0-9.]/', '', $value)` to strip all non-numeric characters safely

### Static USD Flat Fee Inputs (December 2025)
- **UI Change**: Flat-fee amount inputs now use static USD prefix ($) instead of $/% dropdown toggle
- **Affected Fields**: lease_fee_flat, purchase_fee_flat in broker-compensation.blade.php
- **No Logic Changes**: Fee type selection still controlled by main dropdown (lease_fee_type, purchase_fee_type)
- **Formatting**: Uses formatWithCommas() for consistent currency display
- **Backend Unchanged**: lease_fee_flat_type and purchase_fee_flat_type still default to '$' in Livewire component

### Edit Bid Submit Fix (December 2025)
- **Root Cause**: JavaScript `updateSaveButton()` disabled submit button when hidden tabs failed validation (e.g., file inputs lacking files)
- **Fix Applied**: Made `updateSaveButton()` a no-op, allowing form submission to reach Livewire for proper server-side validation
- **Transaction Handling Fixed**: Added `DB::beginTransaction()` and `DB::commit()` to wrap the submit() method's database operations
- **Submit Button Updated**: Changed button to use `wire:loading.attr="disabled"` for visual feedback during save
- **Error Logging Added**: Added `Log::error()` in catch block for debugging

### Livewire File Upload Fix (December 2025)
- **PHP Upload Limits Increased**: Workflow command now passes `-d upload_max_filesize=50M -d post_max_size=55M -d memory_limit=256M -d max_file_uploads=50`
- **Livewire Config Updated**: Set `disk => 'local'`, `directory => 'livewire-tmp'`, `rules => ['file', 'max:51200']` in config/livewire.php
- **Storage Directory Created**: Created `storage/app/livewire-tmp` with proper permissions for temporary file uploads
- **Storage Link**: Ensured `php artisan storage:link` is run to connect public/storage to storage/app/public
- **Validation Rules Already Correct**: Components have proper validation for `promoMaterials.*.files` (array) and `promoMaterials.*.files.*` (file with mimes/max)
- **Blade Error Display Already Correct**: Both `@error('promoMaterials.$idx.files')` and `@error('promoMaterials.$idx.files.*')` present

### Livewire Studly() Crash Fix (January 2026)
- **Root Cause**: Livewire DOM morphing briefly removes select elements during re-render, creating empty binding entries in update payloads with NULL property names
- **Error**: `Too few arguments to function Str::studly()` when selecting broker_fee_timing options
- **Fix Applied**: Added defensive guards in `updated($propertyName)` methods to return early if property name is empty
- **Files Fixed**: TenantAgentAuction.php, TenantAgentAuctionEdit.php
- **Additional Fix**: Added `wire:key` to broker_fee_timing select elements (Residential and Commercial) to help Livewire track them during DOM diffing
- **Pattern**: Always check `if (empty($propertyName)) { return; }` before calling `validateOnly()`

### Traditional vs Bidding Period Listing Behavior (January 2026)
- **Listing Type Detection**: Uses `$auction->get->auction_type` to determine listing type
  - `$isTraditionalListing`: True when auction_type is "traditional" or empty (default behavior)
  - `$isBiddingPeriodListing`: True when auction_type is "bidding period"
- **Timer Display Rules**:
  - Traditional: Timer is hidden completely
  - Bidding Period: Timer displays countdown, shows "Bidding Ended" when expired
- **Accept/Counter/Reject Button Gating**:
  - Traditional: Actions always available immediately (no timer restriction)
  - Bidding Period: Actions locked with "Actions unlock when bidding period ends" message until timer expires
  - After timer ends: Actions become available
- **Agent Bid Visibility Rules**:
  - Traditional: Agents can ONLY see their own bids; other agents' bids are completely hidden
  - Bidding Period: Agents can see anonymous summaries of all bids (Agent 1, Agent 2, etc.)
  - Listing Owner: Always sees all bids regardless of listing type
- **Bid Summary Privacy**:
  - Traditional: "Agent N was the last bidder" message hidden from agents (shows "Bid information is private")
  - Bidding Period: Summary visible to all agents
- **View Full Terms Link**:
  - Listing Owner or Bid Owner: Full access to modal with all details
  - Other agents on Bidding Period: Shows "Full terms visible only to bid creator"
- **Key Variables**: `$isTraditionalListing`, `$isBiddingPeriodListing`, `$isBiddingTimerActive`, `$canTakeAction`, `$canSeeBidSummary`
- **Files Modified**: resources/views/hire_tenant_agent/view.blade.php

### Services Display Normalization Fix (January 2026)
- **Root Cause**: Curly apostrophes (U+2018/U+2019) in stored service strings didn't match straight apostrophes in config
- **Fix Applied**: Added `$normalizeStr` function using explicit UTF-8 byte sequences for curly quote replacement:
  - `\xE2\x80\x98` (U+2018 left single quote) → `'`
  - `\xE2\x80\x99` (U+2019 right single quote) → `'`
  - `\xE2\x80\x9C` (U+201C left double quote) → `"`
  - `\xE2\x80\x9D` (U+201D right double quote) → `"`
- **Rendering**: Services display in canonical category order from config; original stored text preserved in display
- **Unmapped Services**: Services not found in config appear in "Additional / Unmapped Services" section
- **Files Modified**: resources/views/hire_tenant_agent/view.blade.php

### Agent Bid Auto-Fill Fix (January 2026)
- **Issue**: broker_fee_timing fields not auto-filled when agent creates/edits a bid
- **Fix Applied**: Added broker_fee_timing fields to TenantAgentAuctionBid::mount() for both new bids and edit mode
- **Fields Added**: broker_fee_timing_res, broker_fee_timing_days_res, broker_fee_timing_comm, broker_fee_timing_days_comm
- **Behavior**: New bids inherit values from listing; existing bids load saved values
- **Files Modified**: app/Http/Livewire/Tenant/TenantAgentAuctionBid.php

### Edit Bid Agent Info Preservation Fix (January 2026)
- **Issue**: Phone Number, Brokerage, and Real Estate License # fields were being cleared when editing a bid
- **Root Cause**: Profile seeding ran before edit mode detection, overwriting Livewire properties with empty profile values
- **Fix Applied**: Gated profile seeding to new bids only (`!$this->isEditMode`); in edit mode, load agent info directly from bid data with fallback to profile
- **Robust Check**: Uses `isset($bidData->field) && trim($bidData->field) !== ''` to ensure non-empty bid values are preserved
- **Files Modified**: app/Http/Livewire/Tenant/TenantAgentAuctionBid.php

### Agent Self-View Full Bid Feature (January 2026)
- **Requirement**: When an agent views their OWN bid, they should see all 6 sections (matching what listing creator sees)
- **Button Label Change**: "View Full Services & Broker Compensation Terms" → "View Full Bid" (for bid owner only)
- **Sections Now Visible to Bid Owner**:
  1. Agent Overview & Qualifications (About, Why Hire, What Sets Apart, Marketing Strategy, Links)
  2. Broker Compensation & Agency Agreement Terms (already visible)
  3. Additional Details (already visible)
  4. Offered Services (already visible)
  5. Agent Presentation & Marketing Materials (already visible)
  6. Agent Credentials & Contact Information (First Name, Last Name, Phone, Email, Brokerage, License #, NAR ID)
- **Self-View Detection**: Uses existing `$isBidOwner` variable: `data_get($bid, 'user_id') == $auth_id`
- **Visibility Gate Change**: `@if ($isListingOwner)` → `@if ($isListingOwner || $isBidOwner)` for sections 1 and 6
- **No Schema Changes**: Display-only UI changes, no database/logic modifications
- **Files Modified**: resources/views/hire_tenant_agent/view.blade.php
