# Bid Your Offer - Laravel Real Estate Auction Platform

## Overview
Bid Your Offer is a Laravel-based real estate auction platform designed to streamline property sales through a transparent bidding system. It supports multiple auction types including property, buyer, seller, landlord, and tenant agent auctions. The platform aims to enhance transparency and efficiency in the real estate market by offering functionalities for listing properties, managing bids, and facilitating agent interactions.

## User Preferences
I prefer detailed explanations. Ask before making major changes.

## System Architecture
The platform utilizes Laravel 8.x, PHP 8.2.23, and PostgreSQL. For the frontend, it integrates Laravel Mix with TailwindCSS and AlpineJS for a responsive UI, complemented by Livewire for dynamic content. Bootstrap is used for tab navigation. Location autofill is powered by a U.S. Census-based local database.

The core architecture uses a shared `TenantAgentAuction.php` Livewire component to handle form submissions for various agent types, managing submission, draft saving, and metadata persistence. Drafts employ an append-only versioning system. Dynamic model resolution is used to select the correct auction model based on context. Numeric inputs are sanitized, and configuration files centralize display logic. The `ServicesFormatter` groups buyer agent services, and `ListingDisplayHelper` standardizes field formatting. Conditional rendering of fields is managed based on user selections and listing types. PDF listing packets are generated using `barryvdh/laravel-dompdf` with dedicated field map classes and a universal Blade template. Select2 and Livewire are integrated for dynamic and static multi-select fields, utilizing `wire:ignore` for robust initialization and repair. A JSON Bridge Pattern synchronizes data between Select2 and Livewire for certain buyer multi-select fields. Wizard navigation uses a delegation pattern for tab transitions and validation. Address fields dynamically show/hide based on data presence. File uploads (photos/videos) utilize `wire:key` for stabilization and event delegation for validation.

Edit forms implement a comprehensive validation strategy with "Save Draft" and "Save Edit/Submit" actions. `doSaveEditWithSync()` performs full validation across all tabs, while `doSaveDraftWithSync()` allows partial saves by skipping required-field validation. Immutability rules are enforced for critical fields like `Listing Type` and `Current Representation Status with Broker` through disabled inputs with visual lock indicators and server-side re-reading from the database. Server-side PHP validation acts as a safety net for non-draft submissions. Conditional visibility for seller sale terms is handled by `applySellerProvisionVisibility()` and `applySellerFinancingVisibility()` functions, ensuring immediate UI updates and server-side synchronization.

A bidding period timer is implemented for seller, buyer, and landlord listing views, deriving active status from `auction_type` and `auction_time`. The buyer agent search functionality filters records based on `is_approved` and `is_draft` statuses, ensuring only approved, non-draft listings are visible.

The system design emphasizes modularity, separation of concerns, and a database-first approach, optimized for production deployment. The existing database schema for fees is immutable, with display-only formatting updates.

## External Dependencies
- **PostgreSQL**: Primary relational database.
- **TailwindCSS**: Utility-first CSS framework.
- **AlpineJS**: Lightweight JavaScript framework.
- **Laravel Mix**: Wrapper for Webpack to compile assets.
- **U.S. Census Gazetteer Files**: Source for geographical data.
- **Google Places API**: Used for address and postal code validation.
- **barryvdh/laravel-dompdf**: Library for PDF generation from HTML in Laravel.