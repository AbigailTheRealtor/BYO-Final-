<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spatial Intelligence Platform — Phase 2 Batch 2D Part C1
    | Cross-source authority linking (place_authority_links)
    |--------------------------------------------------------------------------
    |
    | Cluster-free authoring config for the offline authority↔corpus linker.
    | It NEVER opens a PostGIS connection, reads NO SPATIAL_* secrets, downloads
    | nothing, and imports no data. Live linking (ST_DWithin + pg_trgm similarity
    | against the loaded `places` table) is deferred to the Class-2 phase — see
    | spikes/phase-2-batch-2d-part-c1-authority-linking/sql/link_authority.sql.
    |
    */

    /*
    | Spatial match radius, in METRES. SSOT §8.2 (verbatim): "Match on
    | ST_DWithin(150 m) + normalised-name trigram similarity >= 0.6". This value
    | is transcribed from the SSOT, not invented.
    */
    'match_radius_m' => 150,

    /*
    | Minimum normalised-name trigram similarity for an automatic link, in [0,1].
    | SSOT §8.2 ("similarity >= 0.6"). Transcribed, not invented.
    */
    'name_similarity_min' => 0.60,

    /*
    | Name-normalisation contract (Part C1, decision D1 — an AUTHORED convention;
    | the SSOT names "normalised-name" but does not define the rule):
    |   lowercase → transliterate common Unicode accents to ASCII →
    |   replace punctuation / non-alphanumerics with spaces → collapse whitespace
    |   → trim → generate pg_trgm-spec trigrams.
    | The offline trigram similarity is a documented approximation of PostgreSQL
    | pg_trgm (decision D2); the authoritative rule is the Class-2 SQL manifest.
    */
    'normalization' => 'lowercase|translit-ascii|punct-to-space|collapse-ws|trim',
];
