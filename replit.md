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
