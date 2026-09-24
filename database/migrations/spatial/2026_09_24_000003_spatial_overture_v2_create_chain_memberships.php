<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Overture corpus v2 — 3/3: chain memberships (`overture_v2_chain_memberships`).
 *
 * The chain-registry matcher's verdict for an imported place, one row per (place, chain). A place
 * may hold several memberships — a declared, evidenced co-brand (v2 decision 3c) — so the key is
 * `(place_id, brand_key)`, never `place_id` alone. Each row's registry version and rule hash must be
 * its own corpus's (`overture_v2_chain_memberships_registry`), so one corpus can never hold
 * memberships produced by two registries. `role` / `storefront_status` / `format_key` are persisted as the matcher
 * reported them (`storefront_unconfirmed` for a department, `fuel` for a fuel row,
 * `store_in_target` for a host-store format); whether a SITE is a storefront, fuel-only or
 * co-branded is decided later over all of a site's members, by the site builder. No site,
 * grouping or de-duplication exists here.
 *
 * `(place_id, corpus_version)` references the place's own pair, so a membership can never point
 * across corpora.
 */
class SpatialOvertureV2CreateChainMemberships extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        $conn->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS overture_v2_chain_memberships (
              id                           bigserial PRIMARY KEY,
              corpus_version               text NOT NULL,
              place_id                     bigint NOT NULL,
              brand_key                    text NOT NULL,
              role                         text NOT NULL,
              format_key                   text NOT NULL,
              match_method                 text NOT NULL,
              storefront_status            text NOT NULL,
              co_brand_with                text[] NOT NULL DEFAULT '{}',
              rescued_from_source_category text,
              registry_version             text NOT NULL,
              registry_rule_hash           text NOT NULL,
              CONSTRAINT overture_v2_chain_memberships_place
                FOREIGN KEY (place_id, corpus_version)
                REFERENCES overture_v2_places (id, corpus_version) ON DELETE CASCADE,
              CONSTRAINT overture_v2_chain_memberships_registry
                FOREIGN KEY (corpus_version, registry_version, registry_rule_hash)
                REFERENCES overture_v2_corpora (corpus_version, registry_version, registry_rule_hash),
              CONSTRAINT overture_v2_chain_memberships_unique UNIQUE (place_id, brand_key),
              CONSTRAINT overture_v2_chain_memberships_role CHECK (
                (role = 'storefront' AND storefront_status = 'storefront')
                OR (role = 'department' AND storefront_status = 'storefront_unconfirmed')
                OR (role = 'fuel' AND storefront_status = 'fuel')
              ),
              CONSTRAINT overture_v2_chain_memberships_rule_hash CHECK (registry_rule_hash ~ '^[0-9a-f]{64}$')
            )
        SQL);

        // "Every member of chain X in corpus V" — the site builder's and any brand census's scan.
        $conn->statement('CREATE INDEX IF NOT EXISTS overture_v2_chain_memberships_brand ON overture_v2_chain_memberships (corpus_version, brand_key)');
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS overture_v2_chain_memberships CASCADE');
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
