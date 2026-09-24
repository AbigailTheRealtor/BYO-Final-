<?php

/*
|--------------------------------------------------------------------------
| Overture corpus v2 — import contracts
|--------------------------------------------------------------------------
|
| What `corpus:import-overture-v2` is allowed to import, one entry per v2 corpus version. Read ONLY
| through App\Services\Spatial\OvertureV2Import\OvertureV2ImportContract, which validates every
| entry. The importer refuses any corpus version not declared here, and refuses an extraction
| whose manifest, files or current code disagree with ANY field of the entry: nothing is written.
|
| A contract binds the expected counts and checksums to ONE corpus version, so a future corpus is
| a new reviewed entry here, never a relaxed check. The counts are the reproduced
| `overture-extract-v2` figures (docs/spatial/overture-v2-extraction-recipe.md §6).
|
| DECLARING A CONTRACT IS NOT ACTIVATION. Nothing but the importer reads this file. It does not
| pin Location DNA, does not touch `overture_corpus_poi` / `location_providers`, and an imported
| corpus is `ready`, never `active` — there is no `active` state in the v2 schema at all.
|
| No env() anywhere: this is data, identical on every host.
*/

return [

    'import_contracts' => [

        'overture-2026-08-19.0-fl-r2' => [
            'source_release' => '2026-08-19.0',
            'extract_recipe_version' => 'overture-extract-v2',
            'taxonomy_map_version' => 'overture-taxonomy-v2.0',
            'registry_version' => 'chain-registry-v2',
            'registry_rule_hash' => 'b5920a1c73199a0d8e030e5281018baa763438eb7ad02aee76f7ba3cbc0b151f',
            'base_rows' => 52566,
            'supplementary_rows' => 2962,
            'matcher_analysis_rows' => 55528,
            'base_sha256' => 'bb9e77c13790897155f847fa3a7ed48067930cf08257e227bee60bf96c91a168',
            'supplementary_sha256' => 'edeed1435d707912dedd08d642cf1088e669cf4dc329ea3248a289be3e91c8e4',
        ],
    ],
];
