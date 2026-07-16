<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1.2 migration 02/11 — place_categories (SSOT §7.2).
 *
 * Extensible taxonomy; adding a POI category is an INSERT, not a code change
 * (SIP-P14). FK target for place_category_mappings (03) and places (04), so it
 * MUST precede both. Additive, guarded, no consumers.
 */
class SpatialCoreCreatePlaceCategories extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();

        DB::connection($this->getConnection())->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS place_categories (
              category_key    text PRIMARY KEY,
              label           text NOT NULL,
              thematic_block  text,
              base_source     text NOT NULL,
              rank_strategy   text NOT NULL,
              exclusion_rules jsonb,
              enabled         boolean NOT NULL DEFAULT true
            )
        SQL);
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS place_categories CASCADE');
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
