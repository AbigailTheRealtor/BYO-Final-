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

    /*
    |--------------------------------------------------------------------------
    | Dual-write (R2-B.1, HI-05A) — INERT by default
    |--------------------------------------------------------------------------
    |
    | When 'dual_write' is true, ListingStorageWriter mirrors each new listing
    | write to the paired object-storage "secondary" disk (best-effort; the
    | local primary stays authoritative). Default false preserves byte-for-byte
    | current behavior. Reads are unaffected (dual-read is a later phase); no
    | existing files are migrated by this flag.
    |
    */

    'dual_write' => env('STORAGE_DUAL_WRITE', false),

    'private_secondary_disk' => env('LISTING_PRIVATE_SECONDARY_DISK', 's3_private'),

    'public_secondary_disk' => env('LISTING_PUBLIC_SECONDARY_DISK', 's3_public'),

    /*
    |--------------------------------------------------------------------------
    | Migration (R2-C, HI-05A) — used only by `listing-storage:migrate`
    |--------------------------------------------------------------------------
    |
    | Non-destructive copy of existing local listing storage to the paired
    | object-storage secondary disks, preserving exact relative keys. These keys
    | are excluded from migration by default: operational metadata and the
    | tracked .gitignore placeholders. Prefix match is applied to the relative
    | key on each disk.
    |
    */

    'migration' => [
        'exclude_prefixes' => [
            '_backfill-manifests',
            '_migration-manifests',
        ],
        'exclude_basenames' => [
            '.gitignore',
        ],
    ],

];
