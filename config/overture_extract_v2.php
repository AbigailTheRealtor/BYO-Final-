<?php

/*
|--------------------------------------------------------------------------
| Overture corpus v2 — extraction recipe (overture-extract-v2)
|--------------------------------------------------------------------------
|
| The offline recipe that turns one pinned Overture Places release into the normalized v2 corpus
| rows a later import (PR 4) will load. Read ONLY through
| App\Services\Spatial\OvertureExtractV2\OvertureExtractV2Config, which validates every entry at
| load time and refuses a malformed file.
|
| NOTHING HERE TOUCHES A DATABASE OR THE NETWORK. The DuckDB SQL in scripts/overture-v2/ is run by
| hand against the public bucket and writes a raw NDJSON file; `corpus:extract-overture-v2` reads
| that file and decides every row locally. The SQL applies the bounding box and nothing else, so
| every eligibility decision — and every rejection — is made and counted here, never pre-filtered.
|
| Design: docs/spatial/overture-corpus-v2-design.md (§2 eligibility, §4 status, §5 fields,
| decision 8 spill-over). Recipe and accounting: docs/spatial/overture-v2-extraction-recipe.md.
|
| No env() anywhere: this is data, identical on every host.
*/

return [

    // The recipe's own version. Independent of the corpus version, the taxonomy map version and
    // the chain-registry version, each of which is pinned separately in the output manifest.
    'recipe_version' => 'overture-extract-v2',

    // Pinned release. Moving to a newer release is a reviewed edit here AND in the SQL, which
    // OvertureExtractV2SqlManifestTest keeps in agreement.
    'release' => '2026-08-19.0',

    // Florida box, [west, south, east, north], applied as CONTAINMENT of each row's own bbox
    // (identical to v1 and to PR 0). It is a rectangle, not the state boundary: the measured GA/AL
    // spill-over is kept and tallied by region (v2 design decision 8), never silently trimmed.
    'bbox' => [
        'west'  => -87.63,
        'south' => 24.40,
        'east'  => -79.97,
        'north' => 31.00,
    ],

    // Inclusive floor: 0.90 itself is eligible.
    'confidence_min' => 0.90,

    // operating_status: `open` and NULL are eligible (NULL is kept as UNKNOWN, never coerced to
    // open); `permanently_closed` is excluded; anything else is excluded until mapped here.
    'operating_status' => [
        'eligible_explicit' => ['open'],
        'eligible_unknown_is_null' => true,
        'excluded' => ['permanently_closed'],
    ],

    /*
    | SUPPLEMENTARY LANE — separate from the corpus, never part of it.
    |
    | A row whose taxonomy token is NOT one of the 16 import tokens (or is null) never enters the
    | base corpus. It may enter this lane — for matcher analysis only — when it passes the same
    | confidence / status gates AND the diagnostic selector below. A supplementary row carries NO
    | canonical category. It becomes materializable only through a rescue lane, and a rescue lane
    | exists only where the chain registry itself declares a source-category rescue for that exact
    | chain and token (OvertureExtractV2Config fails closed on any disagreement).
    */
    'supplementary' => [

        // The census diagnostic selector (docs/spatial/overture-chain-registry-census.md §1),
        // applied to lower(name + ' ' + brand), unanchored. A row carrying ANY non-blank brand QID
        // is also selected — for diagnostics only: a QID never makes a row base, gives it no
        // category and never makes it materializable. Pinned in the manifest.
        'diagnostic_selector' => [
            'name_brand_patterns' => [
                'publix', 'aldi', 'winn.?dixie', 'whole foods', 'trader joe', 'wal.?mart', 'target',
                'walgreens', 'cvs', 'starbucks', '7.?eleven', 'seven eleven', 'wawa', 'race.?trac',
                'speedway', 'shell', 'mcdonald', 'taco bell', 'chick.?fil.?a', 'wendy', 'burger king',
            ],
            'any_brand_wikidata' => true,
            'evidence' => 'M: reproduces the 2,800 + 162 supplementary rows of the chain-registry-v2 census exactly',
        ],

        // Explicit rescue lanes. Each MUST equal a `source_category_rescues` entry of the named
        // chain in config/poi_chain_registry.php — same chain, same token, same target category.
        // Candidacy is by TOKEN (any selected row under the lane's token, whatever its name); the
        // chain matcher decides admission offline, with strong identity and every registry
        // exclusion still applied, and the verdict is written on each row: only `rescued` rows are
        // materializable.
        'rescue_lanes' => [
            'cvs_shopping' => [
                'chain' => 'cvs',
                'source_category' => 'shopping',
                'as_category' => 'drugstore',
                'identity' => 'strong',
                'materialization' => 'eligible_if_matched',
                'reason' => 'CVS storefronts classified by Overture as `shopping` (150 real storefronts in the census); v2 chain-registry decision 1',
            ],
        ],
    ],
];
