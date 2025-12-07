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

### Simplified Address & ZIP Code Auto-Population (December 7, 2025):
- **Street Address Field**: Simplified to a plain text input (no autocomplete) for Hire Seller/Landlord Agent listings
  - Renamed from "Property Address" to "Street Address"
  - Tooltip updated to clarify that City, County, State are entered separately
  - Removed Google Places API dependency for address autocomplete
- **ZIP Code Auto-Population from City**: When a city is selected, ZIP codes are automatically fetched from the local database and added as pills
  - Uses new `us_zip_codes` table with 27,500+ ZIP codes (covers most US cities)
  - `autoPopulateZipCodesFromCity()` method added to SellerAgentAuction.php and LandLordAgentAuction.php
  - Up to 20 ZIP codes auto-added per city selection
- **ZIP Code Database**:
  - New table: `us_zip_codes` with columns: zip_code, city, state_abbrev, state_name, county, latitude, longitude
  - New model: `App\Models\UsZipCode`
  - Seeder: `UsZipCodesSeeder` downloads from public dataset
  - 27,500+ ZIP codes loaded covering major US cities
- **No API Key Required**: City, County, State, and ZIP code auto-population all work from local database - no Google Places API needed

### Single-Value Location Fields for Seller/Landlord Listings (December 7, 2025):
- **Design Rationale**: Hire a Seller's Agent and Hire a Landlord's Agent are property-specific listings (one property = one location), so they use single-value fields instead of multi-select pill arrays
- **New Single-Value Properties** added to both SellerAgentAuction.php and LandLordAgentAuction.php:
  - `$city` - Single city value with autocomplete from local database
  - `$county` - Single county value with autocomplete
  - `$state` - Single state value with autocomplete
  - `$zipCode` - Single ZIP code value
- **Manual Edit Lock Flags**: Prevent auto-population from overriding user edits:
  - `$cityLocked`, `$countyLocked`, `$stateLocked`, `$zipLocked`
  - Set to `true` when user manually types in that field
  - Auto-population methods check these flags before setting values
- **Auto-Population Flow**:
  - When city is selected: County, State, and ZIP auto-fill if not locked
  - When county is selected: State auto-fills if not locked
  - `autoPopulateFromCity()` method queries UsCity model for county relationship
- **Template Updates**: Both property-preferences.blade.php templates updated:
  - Removed Unit Number field
  - Changed from pill-based multi-select to single text inputs
  - Field order: Street Address → City → County → State → ZIP Code
- **Legacy Support**: Old array properties (`$cities`, `$counties`, `$zipCodes`) kept for backwards compatibility with existing data
- **Contrast with Buyer/Tenant Listings**: These use multi-select pill arrays since they represent location preferences, not a single property address

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
