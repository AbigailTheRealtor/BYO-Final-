<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B1.2 migration 09/11 — addresses + 3 indexes (SSOT §7.2 / §11).
 *
 * Owned address corpus, serving BOTH geocoding and typeahead. The trigram GIN
 * index requires pg_trgm (migration 01). Independent table. Additive, guarded,
 * no consumers (Phase 4 wires typeahead; nothing reads this yet).
 */
class SpatialCoreCreateAddresses extends Migration
{
    protected $connection = 'pgsql_spatial';

    public function up(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        $conn->statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS addresses (
              id            bigserial PRIMARY KEY,
              source        text NOT NULL,
              number        text, street text, unit text,
              city          text, state text, postcode text,
              normalized    text NOT NULL,
              geom          geography(Point,4326) NOT NULL,
              precision     text NOT NULL
            )
        SQL);

        $conn->statement('CREATE INDEX IF NOT EXISTS addresses_geom     ON addresses USING gist (geom)');
        $conn->statement('CREATE INDEX IF NOT EXISTS addresses_trgm     ON addresses USING gin  (normalized gin_trgm_ops)');
        $conn->statement('CREATE INDEX IF NOT EXISTS addresses_city_zip ON addresses (state, city, postcode)');
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        DB::connection($this->getConnection())->statement('DROP TABLE IF EXISTS addresses CASCADE');
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
