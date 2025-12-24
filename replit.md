# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed for transparent property sales through bidding. It supports various auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline the real estate transaction process by providing robust listing, bidding, and agent interaction functionalities, enhancing transparency and efficiency in real estate transactions.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

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

### Livewire File Upload Fix (December 2025)
- **PHP Upload Limits Increased**: Workflow command now passes `-d upload_max_filesize=50M -d post_max_size=55M -d memory_limit=256M -d max_file_uploads=50`
- **Livewire Config Updated**: Set `disk => 'local'`, `directory => 'livewire-tmp'`, `rules => ['file', 'max:51200']` in config/livewire.php
- **Storage Directory Created**: Created `storage/app/livewire-tmp` with proper permissions for temporary file uploads
- **Storage Link**: Ensured `php artisan storage:link` is run to connect public/storage to storage/app/public
- **Validation Rules Already Correct**: Components have proper validation for `promoMaterials.*.files` (array) and `promoMaterials.*.files.*` (file with mimes/max)
- **Blade Error Display Already Correct**: Both `@error('promoMaterials.$idx.files')` and `@error('promoMaterials.$idx.files.*')` present
