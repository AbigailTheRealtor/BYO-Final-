<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Listing storage disk selectors (R2-A, HI-05A)
    |--------------------------------------------------------------------------
    |
    | Centralized indirection for WHICH filesystem disk backs listing media and
    | documents. Call sites must resolve disks through App\Support\Storage\
    | ListingStorageDisks rather than reading these values (or env) directly.
    |
    | Defaults preserve the current behavior exactly: the local 'public' and
    | 'private' disks. Flipping a selector to 's3_public' / 's3_private' (a
    | later phase, R2-B+) is what activates object storage. R2-A only introduces
    | and tests the seam; it does not activate it.
    |
    */

    'public_disk' => env('LISTING_PUBLIC_DISK', 'public'),

    'private_disk' => env('LISTING_PRIVATE_DISK', 'private'),

];
