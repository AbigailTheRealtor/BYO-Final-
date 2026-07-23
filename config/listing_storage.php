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
    | Dual-read (R2-D.1, HI-05A) — INERT by default
    |--------------------------------------------------------------------------
    |
    | Controls WHERE listing media/documents are READ from. Consumed only through
    | App\Support\Storage\ListingStorageReader; call sites must never branch on
    | these values directly.
    |
    | Both default to 'local', which preserves current behavior byte-for-byte:
    |   - private_read = 'local'        → stream from local disks only (the
    |                                     existing private → public legacy chain).
    |   - private_read = 'object_first' → try the private secondary FIRST, then
    |                                     fall back to the same local chain.
    | 'public_read' has the same semantics for the public URL resolver, but is
    | NOT consumed until R2-D.2 (the public URL centralization); it is declared
    | here so the read-side surface is defined in one place.
    |
    | 'read_prefixes' optionally scopes object_first reads to a comma-separated
    | list of relative-key prefixes (e.g. 'auction/documents,seller-disclosures').
    | Empty (default) = every prefix is in scope once object_first is selected.
    |
    | No selector, no dual-write, and no object-storage call happens while these
    | remain at their defaults.
    |
    */

    'private_read' => env('LISTING_PRIVATE_READ', 'local'),

    'public_read' => env('LISTING_PUBLIC_READ', 'local'),

    'read_prefixes' => env('LISTING_READ_PREFIXES', ''),

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
