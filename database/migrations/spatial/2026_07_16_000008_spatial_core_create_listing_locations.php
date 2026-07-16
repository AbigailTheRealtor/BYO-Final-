<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1.2 migration 08/11 — listing_locations + index (SSOT §7.2).
 *
 * Supply side: one point per listing. Replaces scattered decimal lat/lng columns.
 * Independent table. Additive, guarded, no consumers (no reads, no backfill here).
 */
class SpatialCoreCreateListingLocations extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        $conn->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS listing_locations (
              listing_type   text   NOT NULL,
              listing_id     bigint NOT NULL,
              geom           geography(Point,4326) NOT NULL,
              geocode_source text,
              PRIMARY KEY (listing_type, listing_id)
            )
        SQL);

        $conn->statement(
            'CREATE INDEX IF NOT EXISTS listing_locations_geom ON listing_locations USING gist (geom)'
        );
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS listing_locations CASCADE');
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
