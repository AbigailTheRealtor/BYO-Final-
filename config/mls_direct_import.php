<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seller/Landlord MLS # prefill (MLS Direct Import — Phase 3)
    |--------------------------------------------------------------------------
    |
    | Master gate for the Seller/Landlord "Import by MLS #" entry point on the
    | Create Offer Listing forms. Default OFF: the input is not rendered and the
    | Livewire action returns early, so the feature is inert until the owner
    | enables it.
    |
    | This flag governs the BRIDGE MLS # lookup ONLY. The pre-existing
    | URL / raw-text importer (MlsListingImportService) is deliberately NOT
    | gated by it and keeps working in every environment regardless of this
    | value — the two import mechanisms are independent by design.
    |
    | Deliberately NOT the Match Check flag. `mls_match_check.enabled` gates a
    | Buyer/Tenant scoring page that never writes a form; this gates a
    | Seller/Landlord write path into a listing. One switch governing both would
    | mean enabling a read-only report also enabled form population.
    |
    | Enabling this requires Bridge credentials to be present — see
    | config/bridge.php. With the flag ON and credentials absent, the lookup
    | reports "MLS data service unavailable" rather than "listing not found".
    |
    */

    'prefill_enabled' => env('MLS_DIRECT_IMPORT_PREFILL_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Roles the MLS # prefill applies to
    |--------------------------------------------------------------------------
    |
    | Seller and Landlord only, and this is not a rollout dial — it is a
    | statement of what the feature IS. Buyer and Tenant listings describe
    | search criteria across many areas, not one property, so there is no
    | property whose facts could be prefilled into them. Adding a role here
    | without building the corresponding field mapping would surface an input
    | that silently imports nothing.
    |
    */

    'prefill_roles' => ['seller', 'landlord'],

    /*
    |--------------------------------------------------------------------------
    | Quick-import flow
    |--------------------------------------------------------------------------
    |
    | The shortened Seller/Landlord path: enter an MLS #, have the property
    | portion of the listing built for you, answer only the BidYourOffer
    | transaction questions, review, publish.
    |
    | Separate from `prefill_enabled` because they are different surfaces with
    | different blast radii. `prefill_enabled` adds an input to a form the user
    | is already filling in; this adds a whole creation path that writes a draft
    | listing. Both must be on for the flow to be reachable — this is an
    | additional gate, never a replacement one.
    |
    | Deliberately NOT tied to config/mls_media.php. The flow's core promise is
    | delivered by the facts alone and is useful with the photo gallery switched
    | off, which is what lets the media licence be settled on its own timetable
    | without holding up the rest of the feature.
    |
    | Role scope is `prefill_roles` above — one list, so the two surfaces cannot
    | drift into disagreeing about which roles the feature exists for.
    |
    */

    'quick_import_enabled' => env('MLS_DIRECT_IMPORT_QUICK_IMPORT_ENABLED', false),

];
