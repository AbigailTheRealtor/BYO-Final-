<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1.2 migration 03/11 — place_category_mappings (SSOT §7.2 / §8.2).
 *
 * Source taxonomy → canonical category. Data, never code. FK → place_categories,
 * so migration 02 MUST precede this. Additive, guarded, no consumers.
 */
class SpatialCoreCreatePlaceCategoryMappings extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();

        DB::connection($this->getConnection())->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS place_category_mappings (
              source          text NOT NULL,
              source_category text NOT NULL,
              category_key    text NOT NULL REFERENCES place_categories,
              PRIMARY KEY (source, source_category)
            )
        SQL);
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS place_category_mappings CASCADE');
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
