<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Overture corpus v2 — 2/3: normalized imported places (`overture_v2_places`).
 *
 * One row per imported `overture-extract-v2` record, keyed `(corpus_version, source_ref)` so the
 * same Overture place can never be imported twice into one corpus. Only MATERIALIZATION
 * CANDIDATES are stored, and the database enforces which those are
 * (`overture_v2_places_lane_contract`):
 *
 *   base           policy `corpus`, a canonical `category_key`, no rescue fields.
 *   supplementary  policy `rescued`, verdict `admitted`, role `rescue_candidate`, NO
 *                  `category_key` (the rescue's target stays in `rescued_as_category`) and every
 *                  rescue field present.
 *
 * A `matcher_only` row (diagnostic, or a refused rescue candidate) cannot be stored at all; the
 * importer counts those in `overture_v2_corpora` instead. Address columns are internal (dedup and
 * site identity), never a display contract. Registry version and rule hash live on the corpus row
 * this references and on every membership.
 */
class SpatialOvertureV2CreatePlaces extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        $conn->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS overture_v2_places (
              id                     bigserial PRIMARY KEY,
              corpus_version         text NOT NULL REFERENCES overture_v2_corpora (corpus_version),
              source                 text NOT NULL,
              source_ref             text NOT NULL,
              source_release         text NOT NULL,
              extract_recipe_version text NOT NULL,
              taxonomy_map_version   text NOT NULL,
              lane                   text NOT NULL,
              supplementary_role     text,
              materialization_policy text NOT NULL,
              rescue_verdict         text,
              rescued_lane           text,
              rescued_chain          text,
              rescued_as_category    text,
              rescued_format         text,
              source_category        text,
              category_key           text,
              legacy_category        text,
              basic_category         text,
              name                   text,
              brand_name             text,
              brand_wikidata         text,
              confidence             double precision NOT NULL,
              operating_status       text,
              operating_status_known boolean NOT NULL,
              geom                   geography(Point,4326) NOT NULL,
              address_freeform       text,
              address_locality       text,
              address_postcode       text,
              address_region         text,
              address_country        text,
              eligibility            text NOT NULL,
              CONSTRAINT overture_v2_places_source_ref UNIQUE (corpus_version, source_ref),
              CONSTRAINT overture_v2_places_id_corpus UNIQUE (id, corpus_version),
              CONSTRAINT overture_v2_places_source CHECK (source = 'overture' AND source_ref <> ''),
              CONSTRAINT overture_v2_places_confidence CHECK (confidence >= 0 AND confidence <= 1),
              CONSTRAINT overture_v2_places_status_known CHECK (operating_status_known = (operating_status IS NOT NULL)),
              -- COALESCE(..., false): a CHECK passes when its expression is NULL, so a
              -- supplementary row with a NULL role or verdict would otherwise satisfy the
              -- second branch by being unknown. Unknown is a violation here, never a pass.
              CONSTRAINT overture_v2_places_lane_contract CHECK (COALESCE(
                (
                  lane = 'base' AND materialization_policy = 'corpus'
                  AND eligibility = 'base_category_eligible'
                  AND category_key IS NOT NULL AND supplementary_role IS NULL
                  AND rescue_verdict IS NULL AND rescued_lane IS NULL AND rescued_chain IS NULL
                  AND rescued_as_category IS NULL AND rescued_format IS NULL
                ) OR (
                  lane = 'supplementary' AND materialization_policy = 'rescued'
                  AND eligibility = 'supplementary_selector'
                  AND category_key IS NULL AND supplementary_role = 'rescue_candidate'
                  AND rescue_verdict = 'admitted' AND rescued_lane IS NOT NULL AND rescued_chain IS NOT NULL
                  AND rescued_as_category IS NOT NULL AND rescued_format IS NOT NULL
                ),
                false
              ))
            )
        SQL);

        // Spatial predicate / KNN work over the imported points (the later site builder).
        $conn->statement('CREATE INDEX IF NOT EXISTS overture_v2_places_geom ON overture_v2_places USING gist (geom)');
        // Per-corpus, per-category scans (acceptance tallies, the site builder's category pass).
        $conn->statement('CREATE INDEX IF NOT EXISTS overture_v2_places_category ON overture_v2_places (corpus_version, category_key)');
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS overture_v2_places CASCADE');
    }

    private function guardSpatialConnection(): void
    {
        $name = $this->getConnection();
        $conf = config("database.connections.{$name}");

        if (empty($conf) || (empty($conf['url']) && empty($conf['host']))) {
            throw new \RuntimeException(
                "[overture-v2/spatial] Connection [{$name}] is not configured. Set SPATIAL_DATABASE_URL "
                . "(or SPATIAL_PGHOST/SPATIAL_PGDATABASE), then run: "
                . "php artisan migrate --path=database/migrations/spatial --database=pgsql_spatial"
            );
        }

        $driver = DB::connection($name)->getDriverName();
        if ($driver !== 'pgsql') {
            throw new \RuntimeException(
                "[overture-v2/spatial] Connection [{$name}] resolves to driver [{$driver}], not 'pgsql'. "
                . "Refusing to execute PostGIS DDL. Use --database=pgsql_spatial."
            );
        }
    }
}
