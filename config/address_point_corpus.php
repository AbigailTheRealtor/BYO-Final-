<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Address point corpus
    |--------------------------------------------------------------------------
    |
    | Gates AddressPointCoordinateAdapter — the ladder rung that answers from
    | our own imported address-point corpus in `pgsql_spatial.addresses`.
    |
    | Default OFF. The corpus is empty (0 rows) and no importer exists yet, so
    | an enabled rung would query a table that cannot answer, once per
    | resolution, forever. Off is not a placeholder here; it is the correct
    | state until an import has actually been loaded and verified.
    |
    */

    'enabled' => (bool) env('ADDRESS_POINT_CORPUS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Pinned corpus version
    |--------------------------------------------------------------------------
    |
    | Which `corpus_version` the rung reads. Deliberately explicit rather than
    | "whatever the ledger says is active": two corpus versions can coexist by
    | design — that is what makes a new import verifiable before it is trusted —
    | and a rung that followed activation automatically would start serving new
    | coordinates the instant a ledger row flipped, with no deploy and no diff.
    |
    | Null means no corpus is pinned, and the rung reports itself unavailable.
    | An enabled flag with no version selected therefore stays inert rather than
    | guessing which pile of rows to read.
    |
    */

    'corpus_version' => env('ADDRESS_POINT_CORPUS_VERSION') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    |
    | The corpus lives in the spatial database, never the application one. Named
    | here so the rung cannot accidentally read an `addresses` table that some
    | other connection happens to have.
    |
    */

    'connection' => 'pgsql_spatial',

    /*
    |--------------------------------------------------------------------------
    | Match ceiling
    |--------------------------------------------------------------------------
    |
    | How many corpus rows one lookup line may pull back before the rung stops
    | reading. Rows sharing a normalized line are units of one building; a
    | handful settles the question of whether they agree on a point, and a
    | pathological address should not be able to load a page's worth of rows
    | into memory to reach the same answer.
    |
    */

    'max_matches' => (int) env('ADDRESS_POINT_CORPUS_MAX_MATCHES', 25),

];
