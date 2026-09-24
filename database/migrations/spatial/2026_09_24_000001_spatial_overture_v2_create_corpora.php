<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Overture corpus v2 — 1/3: the corpus ledger (`overture_v2_corpora`).
 *
 * One row per v2 corpus version: what was imported (every source, recipe, taxonomy and registry
 * pin, the extraction checksums and counts) and how far the import got. Design:
 * docs/spatial/overture-v2-corpus-schema.md.
 *
 * SIDE BY SIDE WITH v1, NEVER THROUGH IT. v1's `places` / `corpus_imports` are not altered, not
 * written and not referenced; Location DNA reads `places` only, so nothing here can reach it.
 *
 * `status` is `preparing` → `ready` | `failed`. There is deliberately NO `active` state: import
 * and activation are separate decisions, and nothing in this table can say a corpus is live.
 * `ready` is guarded by the database itself — it cannot be set unless every imported count
 * equals its expected count and the import finished.
 */
class SpatialOvertureV2CreateCorpora extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();

        DB::connection($this->getConnection())->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS overture_v2_corpora (
              corpus_version                    text PRIMARY KEY,
              status                            text NOT NULL,
              -- The importer run that owns the current `preparing` attempt. A run can only lock,
              -- finish or fail the attempt carrying its own token, so two racing importers can
              -- never finish or fail each other's run.
              import_run                        text NOT NULL,
              source                            text NOT NULL DEFAULT 'overture',
              source_release                    text NOT NULL,
              extract_recipe_version            text NOT NULL,
              taxonomy_map_version              text NOT NULL,
              registry_version                  text NOT NULL,
              registry_rule_hash                text NOT NULL,
              registry_match_precedence_version text NOT NULL,
              registry_normalizer_version       text NOT NULL,
              manifest_sha256                   text NOT NULL,
              base_sha256                       text NOT NULL,
              supplementary_sha256              text NOT NULL,
              input_file_sha256                 text,
              base_rows                         integer NOT NULL,
              supplementary_rows                integer NOT NULL,
              matcher_analysis_rows             integer NOT NULL,
              diagnostic_rows                   integer NOT NULL,
              rescue_admitted_rows              integer NOT NULL,
              rescue_refused_rows               integer NOT NULL,
              expected_memberships              integer NOT NULL,
              imported_base_rows                integer,
              imported_rescued_rows             integer,
              imported_memberships              integer,
              manifest                          jsonb NOT NULL,
              started_at                        timestamptz NOT NULL,
              finished_at                       timestamptz,
              failure_reason                    text,
              -- Anchor for the memberships: each one must carry its own corpus's registry.
              CONSTRAINT overture_v2_corpora_registry UNIQUE (corpus_version, registry_version, registry_rule_hash),
              CONSTRAINT overture_v2_corpora_status CHECK (status IN ('preparing', 'ready', 'failed')),
              CONSTRAINT overture_v2_corpora_source CHECK (source = 'overture'),
              CONSTRAINT overture_v2_corpora_rule_hash CHECK (registry_rule_hash ~ '^[0-9a-f]{64}$'),
              CONSTRAINT overture_v2_corpora_lanes CHECK (
                matcher_analysis_rows = base_rows + supplementary_rows
                AND supplementary_rows = diagnostic_rows + rescue_admitted_rows + rescue_refused_rows
              ),
              -- COALESCE(..., false): a CHECK passes when its expression is NULL, and
              -- `NULL = base_rows` is NULL, so without it a `ready` row with no imported
              -- counts at all would be accepted. Unknown is a violation here, never a pass.
              CONSTRAINT overture_v2_corpora_ready CHECK (COALESCE(
                status <> 'ready' OR (
                  finished_at IS NOT NULL AND failure_reason IS NULL
                  AND imported_base_rows = base_rows
                  AND imported_rescued_rows = rescue_admitted_rows
                  AND imported_memberships = expected_memberships
                ),
                false
              )),
              CONSTRAINT overture_v2_corpora_failed CHECK (status <> 'failed' OR failure_reason IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS overture_v2_corpora CASCADE');
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
