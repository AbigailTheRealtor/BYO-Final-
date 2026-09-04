<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Overture Corpus POI Adapter — local corpus reads for Location DNA
    |--------------------------------------------------------------------------
    |
    | Configuration for `OvertureCorpusPoiAdapter`, the POI provider that answers
    | "what is near this coordinate?" from the Overture places corpus we already
    | host on the `pgsql_spatial` cluster, instead of from a paid external API.
    |
    | TWO GATES, AND THEY ARE NOT REDUNDANT
    | -------------------------------------
    | `enabled` here is the ADAPTER's gate: may this class read the corpus at all.
    | `config/location_providers.php` carries the REGISTRY's gate: is this provider
    | selected as the base for `poi.default`. Both must agree before a corpus read
    | happens, and they answer different questions — one is a capability, the other
    | is a routing decision. Turning the provider off in the capability map should
    | not require editing this file, and silencing the adapter (say, while the
    | cluster is being maintained) should not require editing the capability map.
    |
    */

    /*
    | Master gate for the adapter. Default false.
    |
    | Off is the correct state until the corpus has been verified against the
    | pinned version below in the target environment. An enabled adapter with an
    | unreachable cluster costs one failed connection per category per listing and
    | reports every category `not_found` — which looks exactly like a thin corpus.
    */
    'enabled' => (bool) env('OVERTURE_CORPUS_POI_ENABLED', false),

    /*
    | The `corpus_version` this adapter reads, pinned explicitly.
    |
    | NOT "whatever corpus_imports says is active", for the same reason
    | `address_point_corpus.corpus_version` is not: two corpus versions coexisting
    | is what lets a new import be verified before it is trusted, and an adapter
    | that followed the ledger would start serving different POIs the moment a row
    | flipped — with no deploy and no diff to point at afterwards.
    |
    | Both this and `enabled` must be set. An enabled adapter with no version
    | pinned reports itself unavailable rather than guessing which import to serve.
    */
    'corpus_version' => env('OVERTURE_CORPUS_POI_VERSION') ?: null,

    /*
    | The database connection holding the corpus. The same dedicated PostGIS
    | connection `address_point_corpus` and `spatial_gate2_corpus` read; defined in
    | config/database.php and inert when the SPATIAL_* variables are absent.
    |
    | Deliberately not overridable by env: pointing POI reads at a different
    | cluster is a code change, and the test suite substitutes a connection by
    | binding config directly rather than by setting an environment variable.
    */
    'connection' => 'pgsql_spatial',

    /*
    | The corpus table and the columns the KNN read depends on. Config rather than
    | literals so the SSOT §7.2 column names live beside the other corpus configs,
    | and so a test can point the adapter at a fixture table. Every value is
    | whitelisted to a `[a-z_][a-z0-9_]*` token before it reaches SQL — see
    | `OvertureCorpusPoiAdapter::identifier()`.
    */
    'table'            => 'places',
    'category_column'  => 'category_key',
    'geom_column'      => 'geom',
    'centroid_column'  => 'centroid',

    /*
    | Supported region, as a single ISO-3166-2 subdivision code.
    |
    | The loaded corpus is Florida only (`overture-2026-06-17.0-fl`, bbox
    | `config/overture_places.php` regions.florida). A coordinate outside the
    | supported region gets NO candidates — never a Florida answer for an
    | out-of-state property, which would be a wrong result reported as success.
    |
    | This is a list so a second state's import does not require a code change,
    | but it must only ever name a region the corpus actually covers.
    */
    'regions' => ['US-FL'],

    /*
    | Latitude/longitude envelope per supported region, in EPSG:4326 degrees.
    | Mirrors `config/overture_places.php` regions.florida — the same box the
    | extract was filtered to, so the adapter declines exactly the coordinates the
    | corpus cannot contain a row for.
    |
    | A bounding box is deliberately coarse: it admits a little water and a sliver
    | of Georgia and Alabama. That is the safe direction — a coordinate just inside
    | the box simply finds no nearby row and returns nothing, whereas a box drawn
    | tight to the coastline would decline real waterfront addresses.
    */
    'region_bounds' => [
        'US-FL' => ['west' => -87.63, 'south' => 24.40, 'east' => -79.97, 'north' => 31.00],
    ],

    /*
    | KNN over-fetch floor and factor (SIA-D40).
    |
    | The index orders by `<->`, which is a SPHERE distance on the geography type.
    | Near-equidistant neighbours can swap under it (E-50). So the read over-fetches
    | with the index, then re-ranks the over-fetched rows on the exact spheroidal
    | `ST_Distance(..., true)` and truncates. The floor of 20 matches
    | `LocationDnaPoiDistanceService::MAX_RESULTS_TO_EXAMINE`, which is how many
    | candidates that service examines per category anyway.
    */
    'overfetch_floor'  => 20,
    'overfetch_factor' => 2,

    /*
    | Hard ceiling on rows one lookup may return, applied after the re-rank.
    | A backstop against a caller passing an absurd limit, not a tuning dial —
    | `LocationDnaPoiDistanceService` persists at most 10 per category and the
    | buyer/tenant path asks for 5.
    */
    'max_results' => 20,

    /*
    | Default search radius in miles when a caller supplies none, and the hard
    | ceiling applied to any radius a caller does supply.
    |
    | The production Location DNA path issues a `rankby=distance` style query with
    | no radius at all, so `default_radius_miles` is what bounds it here. 25 matches
    | `location_dna.poi.max_radius_miles`, the ceiling the buyer/tenant path already
    | enforces, so the two paths cannot disagree about how far "nearby" reaches.
    */
    'default_radius_miles' => 25,
    'max_radius_miles'     => 25,

];
