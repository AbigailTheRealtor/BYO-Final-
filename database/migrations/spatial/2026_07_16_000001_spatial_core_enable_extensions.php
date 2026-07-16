<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 1b (B1.2), migration 01/11.
 *
 * PostGIS extensions for the spatial core (SSOT §7.1).
 *
 * Runs ONLY on the dedicated `pgsql_spatial` connection, ONLY via:
 *   php artisan migrate --path=database/migrations/spatial --database=pgsql_spatial
 *
 * Additive, guarded, no consumers. Version pins are asserted, not assumed
 * (SIA-D39 / E-49 — verified installed on Crunchy Bridge in Stage 0b):
 *   postgis 3.6.3 · btree_gist 1.7 · pg_trgm 1.6
 *
 * btree_gist is REQUIRED before migration 04 can build the composite
 * gist(category_key, geom) index (SSOT §7.3 / A2 / E-5).
 */
class SpatialCoreEnableExtensions extends Migration
{
    protected $connection = 'pgsql_spatial';

    /** Pinned per SIA-D39 / E-49 (measured baseline, Stage 0b). */
    private const PINS = [
        'postgis'    => '3.6.3',
        'btree_gist' => '1.7',
        'pg_trgm'    => '1.6',
    ];

    public function up(): void
    {
        $this->guardSpatialConnection();
        $conn = DB::connection($this->getConnection());

        $conn->statement('CREATE EXTENSION IF NOT EXISTS postgis');
        $conn->statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        $conn->statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $rows = $conn->select(
            "SELECT extname, extversion FROM pg_extension "
            . "WHERE extname IN ('postgis','btree_gist','pg_trgm')"
        );

        $installed = [];
        foreach ($rows as $r) {
            $installed[$r->extname] = $r->extversion;
        }

        foreach (self::PINS as $ext => $want) {
            $have = $installed[$ext] ?? null;
            if ($have !== $want) {
                throw new \RuntimeException(
                    "[B1.2/spatial] Extension [{$ext}] is at version [" . ($have ?? 'absent') . "] "
                    . "but SIA-D39/E-49 pins [{$want}]. Refusing to proceed — the spatial "
                    . "baseline must match the measured Stage 0b versions exactly."
                );
            }
        }
    }

    public function down(): void
    {
        $this->guardSpatialConnection();
        // Deliberate NO-OP (plan §7). Dropping the postgis extension with CASCADE would
        // destroy every geography column in the batch and any object depending on it.
        // On a dedicated spatial database there is no benefit to removing the extensions.
    }

    /**
     * Fail closed unless the resolved connection is a configured PostgreSQL target.
     * Identical guard is inlined in every spatial migration so each is independently
     * safe to run (matches this repo's self-contained-migration convention).
     */
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
