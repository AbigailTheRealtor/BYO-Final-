# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to revolutionize property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to streamline real estate transactions by offering robust functionalities for listing properties, managing bids, and facilitating agent interactions, ultimately enhancing transparency and efficiency in the real estate market.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
The platform leverages Laravel Mix with TailwindCSS and AlpineJS for a responsive user interface, complemented by Livewire for dynamic content and real-time updates. Bootstrap is used for managing tab navigation. Location autofill functionalities utilize a U.S. Census-based local database. Listing displays feature unique IDs and pill badges for clear categorization. A "Broker Compensation & Agency Agreement Terms" form standardizes agent workflows to ensure consistency.

### Technical Implementations
The platform is built on Laravel 8.x, PHP 8.2.23, and PostgreSQL, with Node.js v20 for asset compilation. A shared `TenantAgentAuction.php` Livewire component processes all agent type form submissions, handling submission, draft saving/loading, and metadata persistence. Drafts utilize an append-only versioning system. Model resolution dynamically selects the correct auction model. Numeric inputs are sanitized, and configuration files centralize display logic. The `ServicesFormatter` groups buyer agent services into canonical categories, while `ListingDisplayHelper` standardizes field formatting and display across listing view pages. Conditional rendering of fields is managed based on user selections and listing types. PDF listing packets are generated using `barryvdh/laravel-dompdf`, driven by dedicated field map classes and a universal Blade template. Select2 and Livewire integration handles dynamic and static multi-select fields, using `wire:ignore` where appropriate and providing robust initialization and repair mechanisms. A JSON Bridge Pattern synchronizes data for certain buyer multi-select fields between Select2 and Livewire properties. Wizard navigation employs a delegation pattern to manage tab transitions and validation across Livewire re-renders, with a dedicated banner for displaying validation errors. Address fields dynamically show/hide based on data presence. File uploads (photos/videos) utilize `wire:key` for stabilization and event delegation for validation. Video links are embedded as iframes for YouTube/Vimeo or displayed as clickable links for other URLs. Required field validations and select2 stabilization for property-type-dependent fields are carefully managed to ensure data integrity and user experience.

### System Design Choices
The architecture prioritizes modularity, clear separation of concerns, and a database-first approach utilizing local database solutions. The system is optimized for production deployment, and the existing database schema for fees is immutable, with display-only formatting updates.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: Utility-first CSS framework.
- **AlpineJS**: Lightweight JavaScript framework.
- **Laravel Mix**: Wrapper for Webpack to compile assets.
- **U.S. Census Gazetteer Files**: Source for geographical data.
- **Google Places API**: Used for address and postal code validation.
- **barryvdh/laravel-dompdf**: Library for PDF generation from HTML in Laravel.