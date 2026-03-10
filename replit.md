# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to streamline property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to enhance transparency and efficiency in the real estate market by offering functionalities for listing properties, managing bids, and facilitating agent interactions.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture

### UI/UX Decisions
The platform uses Laravel Mix with TailwindCSS and AlpineJS for a responsive UI, complemented by Livewire for dynamic content. Bootstrap handles tab navigation. Location autofill uses a U.S. Census-based local database. Listings feature unique IDs and pill badges. A "Broker Compensation & Agency Agreement Terms" form standardizes agent workflows.

### Technical Implementations
The platform is built on Laravel 8.x, PHP 8.2.23, and PostgreSQL, with Node.js v20 for asset compilation. A shared `TenantAgentAuction.php` Livewire component processes all agent type form submissions, managing submission, draft saving/loading, and metadata persistence. Drafts use an append-only versioning system. Model resolution dynamically selects the correct auction model. Numeric inputs are sanitized, and configuration files centralize display logic. The `ServicesFormatter` groups buyer agent services into canonical categories, while `ListingDisplayHelper` standardizes field formatting. Conditional rendering of fields is managed based on user selections and listing types. PDF listing packets are generated using `barryvdh/laravel-dompdf`, driven by dedicated field map classes and a universal Blade template. Select2 and Livewire integration handles dynamic and static multi-select fields, using `wire:ignore` where appropriate and providing robust initialization and repair mechanisms. A JSON Bridge Pattern synchronizes data for certain buyer multi-select fields between Select2 and Livewire properties. Wizard navigation employs a delegation pattern for tab transitions and validation across Livewire re-renders. Address fields dynamically show/hide based on data presence. File uploads (photos/videos) utilize `wire:key` for stabilization and event delegation for validation. Video links are embedded as iframes or displayed as clickable links. Required field validations and select2 stabilization for property-type-dependent fields are carefully managed.

### System Design Choices
The architecture emphasizes modularity, clear separation of concerns, and a database-first approach utilizing local database solutions. The system is optimized for production deployment, and the existing database schema for fees is immutable, with display-only formatting updates.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: Utility-first CSS framework.
- **AlpineJS**: Lightweight JavaScript framework.
- **Laravel Mix**: Wrapper for Webpack to compile assets.
- **U.S. Census Gazetteer Files**: Source for geographical data.
- **Google Places API**: Used for address and postal code validation.
- **barryvdh/laravel-dompdf**: Library for PDF generation from HTML in Laravel.