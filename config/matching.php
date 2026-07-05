<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Matching Engine V2 (Beyond-MLS §F6 consumption)
    |--------------------------------------------------------------------------
    |
    | Master gate for Matching V2 — the read-only consumption layer over the
    | dna_scores production artifact. Default OFF and INDEPENDENT of DNA score
    | generation (config dna_scores.generation_enabled) and the compatibility
    | engine (config bya_compatibility.*).
    |
    | Matching V2 is a PURE READ-ONLY CONSUMER of dna_scores: it never
    | regenerates, modifies, or writes back into any generation table. When this
    | flag is off, the consumption services return an inert (empty) result.
    |
    */

    'v2_enabled' => env('MATCHING_V2_ENABLED', false),

];
