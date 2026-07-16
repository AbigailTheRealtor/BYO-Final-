<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1.2 migration 07/11 — boundaries_parts + index (SSOT §7.2, E-24).
 *
 * ST_Subdivide does not accept geography, so boundaries are subdivided at IMPORT
 * time on geometry and stored back as geography here. FK → boundaries (06).
 * Additive, guarded, no consumers.
 */
class SpatialCoreCreateBoundariesParts extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        $conn->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS boundaries_parts (
              boundary_id bigint NOT NULL REFERENCES boundaries,
              geom        geography(Polygon,4326) NOT NULL
            )
        SQL);

        $conn->statement(
            'CREATE INDEX IF NOT EXISTS boundaries_parts_geom ON boundaries_parts USING gist (geom)'
        );
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS boundaries_parts CASCADE');
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
