<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1.2 migration 05/11 — place_authority_links (SSOT §7.2 / §8.2).
 *
 * Resolved authority ↔ corpus pairings, persisted so a refresh does not
 * re-litigate them. Independent table. Additive, guarded, no consumers.
 */
class SpatialCoreCreatePlaceAuthorityLinks extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();

        DB::connection($this->getConnection())->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS place_authority_links (
              authority_source text NOT NULL,
              authority_ref    text NOT NULL,
              place_source     text NOT NULL,
              place_source_ref text NOT NULL,
              match_method     text NOT NULL,
              match_score      numeric(4,3),
              reviewed_by      text,
              PRIMARY KEY (authority_source, authority_ref)
            )
        SQL);
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS place_authority_links CASCADE');
    }

    private function guardSpatialConnection(): void
    {
        $name = $this->getConnection();
        $conf = config("database.connections.{$name}");

        if (empty($conf) || (empty($conf['url']) && empty($conf['host']))) {
            throw new \RuntimeException(
                "[B1.2/spatial] Connection [{$name}] is not configured. Set SPATIAL_DATABASE_URL "
                . "(or SPATIAL_PGHOST/SPATIAL_PGDATABASE), then run: "
                . "php artisan migrate --path=database/migrations/spatial --database=pgsql_spatial"
            );
        }

        $driver = DB::connection($name)->getDriverName();
        if ($driver !== 'pgsql') {
            throw new \RuntimeException(
                "[B1.2/spatial] Connection [{$name}] resolves to driver [{$driver}], not 'pgsql'. "
                . "Refusing to execute PostGIS DDL. Use --database=pgsql_spatial."
            );
        }
    }
}
