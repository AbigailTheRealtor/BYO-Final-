# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
This is a Laravel-based real estate auction platform that enables transparent bidding for property sales. The application was successfully imported and configured to run in the Replit environment.

## Project Structure
- **Framework**: Laravel 8.x
- **Frontend**: Laravel Mix (Webpack) with TailwindCSS and AlpineJS
- **Database**: PostgreSQL (configured for Replit)
- **PHP Version**: 8.2.23
- **Node.js**: v20

## Recent Changes (December 5, 2025)
- Migrated database from MySQL to PostgreSQL
- Updated .env file with PostgreSQL connection details (DB_HOST=helium, DB_DATABASE=heliumdb)
- Updated config/database.php to use PGHOST/PGDATABASE environment variables as fallbacks
- Fixed database migrations (added table existence checks for optional features)
- Built frontend assets using Laravel Mix
- Configured Laravel server to run on port 5000
- Added default settings to database (title, favicon, logo)
- Set up deployment configuration
- Fixed notification JavaScript errors with guard checks for non-logged-in users
- Added Mix assets (css/app.css, js/app.js) to master.blade.php layout
- Fixed JavaScript error in bootstrap.js (changed Vite syntax to Laravel Mix for Pusher)
- Added Send Message button to all hire agent listing types (tenant, landlord, seller)
- Fixed title font size and styling for hire agent listings (teal color, 1.5rem)
- Fixed accordion structure for bid details across all hire agent views

### Hire a Tenant Agent Feature Updates:
- Tab 1 Listing Details: Updated tooltips to reference "Agent's Offered Services and Broker Compensation & Agency Agreement Terms"
- Tab 2 Property Preferences: Changed label to "Garage/Parking Features Needed", removed pool type emojis
- Tab 5 Services: Renamed section header to "Property Alerts & Matching"
- Tab 7 Broker Compensation: Renamed to "Broker Compensation & Agency Agreement Terms", added static $ symbol for flat fee, comma formatting for dollar amounts, added Lease-Option section headers and tooltips
- Tab 8 Tenant Information: Added required field indicators, phone auto-formatting, removed video upload section
- Agent Bids Tab 1: Added required field indicators to About Agent, Why Hire, What Sets You Apart, Marketing Strategy, Year Licensed
- Agent Bids Tab 2: Renamed to "Agent Credentials & Contact Info", added required field indicators, phone auto-formatting
- Agent Bids Tab 5: Removed video upload section from agent presentation files for tenant, landlord, and buyer

### Unique Listing ID Feature:
- Added `listing_id` column to all auction tables (tenant_agent_auctions, landlord_agent_auctions, buyer_agent_auctions, seller_agent_auctions, property_auctions, buyer_criteria_auctions, tenant_criteria_auctions, landlord_auctions)
- Created `HasListingId` trait in `app/Traits/HasListingId.php` for auto-generating unique listing IDs
- Format: PREFIX-XXXXXXXX (e.g., TAA-AB12CD34 for Tenant Agent Auctions, LAA-XY78ZW90 for Landlord Agent Auctions)
- Listing IDs are displayed in the listing view page as a badge
- All auction models updated to use the HasListingId trait

### Location Autocomplete Feature (December 7, 2025):
- Created U.S. Census-based location database with normalized tables:
  - `us_states`: 56 states/territories with FIPS codes and abbreviations
  - `us_counties`: 3,232 counties with FIPS codes linked to states (from 2024 Census Gazetteer)
  - `us_cities`: 32,141 cities/places with FIPS codes linked to states (from 2024 Census Gazetteer)
- Created models: `App\Models\UsState`, `App\Models\UsCounty`, `App\Models\UsCity`
- Updated Livewire components to use database-driven autocomplete instead of Google Places API:
  - `TenantAgentAuction.php`: Updated `getPlaceSuggestions()` to use database for states, counties, and cities
  - `BuyerAgentAuction.php`: Updated `getPlaceSuggestions()` to use database for states, counties, and cities
- Database search uses ILIKE for case-insensitive PostgreSQL matching
- Google Places API still used for address/postal code lookups only (retained for specific address validation)
- Removed emojis from Property Type dropdown across all hire agent listings
- Database seeders auto-download Census Gazetteer files: `UsStatesSeeder`, `UsCountiesExpandedSeeder`, `UsCitiesExpandedSeeder`
- Unique indexes added on fips_code (counties) and name+state (cities) to prevent duplicates
- Removed Acceptable ZIP Codes field from Hire a Buyer's Agent and Hire a Tenant's Agent listings

### Auto-Population & UI Enhancements (December 7, 2025):
- **State Auto-Population**: When a city or county is selected, the state field auto-populates with the FULL state name (e.g., "Florida") if currently empty
- **County Auto-Population**: When a city is selected, its associated county is automatically added to the "Acceptable Counties" pill list (e.g., selecting "St. Petersburg, FL" adds "Pinellas County, FL")
- **Pill Badge Styling**: Enhanced CSS for `.btn-close-white` class to ensure delete (X) button is visible as white on colored badge backgrounds
- **Case-Insensitive Duplicate Check**: Counties are checked case-insensitively to prevent duplicates with different casing
- **Florida City-County Mappings**: Major Florida cities (111+ cities) manually linked to their correct counties including:
  - Pinellas County: St. Petersburg, Clearwater, Largo, Pinellas Park, etc.
  - Hillsborough County: Tampa, Brandon, Plant City, etc.
  - Orange County: Orlando, Winter Park, Apopka, etc.
  - Miami-Dade County: Miami, Miami Beach, Hialeah, etc.
  - Broward County: Fort Lauderdale, Hollywood, Coral Springs, etc.
  - Palm Beach County: West Palm Beach, Boca Raton, Delray Beach, etc.
  - And other major Florida counties
- Helper methods added to both TenantAgentAuction.php and BuyerAgentAuction.php:
  - `autoPopulateFromCity()`: Queries UsState model for full state name, queries UsCity for county_id and adds county
  - `autoPopulateStateFromCounty()`: Queries UsState model for full state name when county is selected
  - `countyExistsIgnoreCase()`: Case-insensitive duplicate check
  - `extractStateFromLocationString()` / `extractNameFromLocationString()`: String parsing helpers
- **Known Limitation**: Cities spanning multiple counties only associate with their primary county due to data model constraints (us_cities has single county_id)

### Tab Navigation Fix for All Hire Agent Pages (December 8, 2025):
- **Fixed Tab-Pane ID Mismatch**: Bootstrap tab navigation targets (e.g., `#buyer-information`) didn't match tab-pane IDs (`id="tenant-info"`) - copy-paste errors from tenant agent template
- **Files Fixed**:
  - hire-buyer-agent.blade.php: Changed `id="tenant-info"` to `id="buyer-information"` (line 911)
  - hire-seller-agent.blade.php: Changed `id="tenant-info"` to `id="seller-information"` (line 911)
  - hire-landlord-agent.blade.php: Changed `id="tenant-info"` to `id="landlord-information"` (line 1125)
- Updated comments from "Tenant Info Tab" to appropriate user type (Buyer/Seller/Landlord Info Tab)
- **Fixed Invalid CSS Selector Bug (Root Cause of Missing Tabs)**:
  - Tab name "Broker Compensation & Agency Agreement Terms" in tenant-agent-auction.blade.php contained ampersand (`&`)
  - When converted to CSS ID (`#broker-compensation-&-agency-agreement-terms`), this created an invalid selector
  - Bootstrap's Tab controller threw error: `Element.querySelector: '#broker-compensation-&-agency-agreement-terms' is not a valid selector`
  - This error broke Bootstrap tab initialization, causing tabs 2-6 to not function for Income Property
  - Fixed by changing tab name to "Broker Compensation" (line 1687 in tenant-agent-auction.blade.php)
  - Full title "Broker Compensation & Agency Agreement Terms" retained as H3 header inside tab content

### Income Property Tabs Fix (December 9, 2025):
- **Root Cause Found**: Unclosed `<div>` tag in property-preferences.blade.php
  - Line 936 opened `<div class="form-group">` for "Acceptable Number of Units" section
  - Lines 955-958 had dead code: empty `<div></div>` tags
  - The form-group div was never closed, causing all subsequent tab content to be swallowed
  - This resulted in: Tab 2 showing huge gap at bottom, Tabs 3-7 appearing blank for Income only
- **Fix Applied**: Removed empty `<div></div>` dead code and added proper `</div>` closing tag
  - File: `resources/views/livewire/hire-buyer-agent/buyer-agent-auction-tabs/commission-based/property-preferences.blade.php`
  - Verified: 135 opening `<div` tags now match 135 closing `</div>` tags
- **Result**: All 7 tabs now render correctly for Buyer → Full Service → Income property type

### Property Location Fields for Hire Seller/Landlord Agent (December 8, 2025):
- **New Required Fields**: Added City*, State*, ZIP Code* immediately after Street Address
  - City field has autocomplete from local us_cities database (32,141 cities)
  - State field displays full state name (e.g., "Florida")
  - ZIP Code field for property's postal code
- **Auto-Population Flow**: When City is selected from autocomplete:
  - State auto-populates with full state name
  - County auto-populates (stored in property_county)
  - ZIP code suggests first matching ZIP from database
- **Implementation**:
  - New properties: `property_city`, `property_state`, `property_zip`, `property_county`
  - New methods: `updatedPropertyCity()`, `selectPropertyCitySuggestion()`, `autoPopulateFromPropertyCity()`
  - Updated `getPlaceSuggestions()` to use 100% local database (zero Google Places API calls)
- **Database**: All 32,141 cities have valid state_id relationships for accurate state lookup
- **No API Key Required**: All location autocomplete and auto-population uses local database only

## Database
- PostgreSQL database configured and connected
- Core migrations completed successfully
- Default settings added to allow application to run
- Some optional feature migrations (landlord/tenant auctions) were skipped due to missing base tables

## Environment Configuration
Key environment variables set:
- `DB_CONNECTION=pgsql`
- `DB_HOST=helium`
- `DB_PORT=5432`
- `DB_DATABASE=heliumdb`
- `CACHE_DRIVER=file`
- `SESSION_DRIVER=file`
- `APP_URL` configured for Replit domain

## Workflow
The Laravel development server runs on port 5000 with the command:
```
php artisan serve --host=0.0.0.0 --port=5000
```

## Deployment
Configured for VM deployment with the following build steps:
1. Composer install (production, optimized)
2. NPM install and build
3. Laravel caching (config, routes, views)

## Known Issues
- Some optional feature migrations for landlord/tenant auctions were skipped as their base tables don't exist
- Minor JavaScript notification errors (401) on homepage when not logged in (expected behavior)

## Next Steps
Users can:
- Sign up / Sign in
- Create property listings
- Participate in auctions
- Search for agents and listings
