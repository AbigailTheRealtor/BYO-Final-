<?php

namespace Database\Seeders;

use App\Services\Spatial\OvertureCategoryMap;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2A (B1).
 *
 * Seeds the FIRST-SLICE canonical `place_categories` rows (SSOT §7.2) into the
 * dedicated PostGIS connection.
 *
 * GUARDED + NOT REGISTERED (owner decision):
 *   • Targets ONLY the `pgsql_spatial` connection and FAILS CLOSED when it is
 *     not a configured pgsql target — so it can never write to the app DB or
 *     the SQLite test suite.
 *   • Deliberately NOT added to DatabaseSeeder, so `db:seed` and the default
 *     test suite never invoke it. Run it explicitly, only once a cluster
 *     exists (a later Class-2 concern):
 *       php artisan db:seed --class=Database\\Seeders\\SpatialFirstSliceCategorySeeder --database=pgsql_spatial
 *   • Batch 2A does NOT run this against PostGIS — it is authored, not applied.
 *
 * rank_strategy = 'confidence' and exclusion_rules = NULL for every row.
 */
class SpatialFirstSliceCategorySeeder extends Seeder
{
    private const CONNECTION = 'pgsql_spatial';

    /**
     * The seed rows — pure, derived from the taxonomy SSOT. Exposed for the
     * shape test; identical to what run() upserts.
     */
    public static function rows(): array
    {
        return (new OvertureCategoryMap())->categoryRows();
    }

    public function run(): void
    {
        $this->guardSpatialConnection();

        $conn = DB::connection(self::CONNECTION);
        foreach (self::rows() as $row) {
            $conn->table('place_categories')->upsert(
                [[
                    'category_key'    => $row['category_key'],
                    'label'           => $row['label'],
                    'thematic_block'  => $row['thematic_block'],
                    'base_source'     => $row['base_source'],
                    'rank_strategy'   => $row['rank_strategy'],
                    'exclusion_rules' => $row['exclusion_rules'],
                    'enabled'         => $row['enabled'],
                ]],
                ['category_key'],
                ['label', 'thematic_block', 'base_source', 'rank_strategy', 'exclusion_rules', 'enabled']
            );
        }
    }

    /**
     * Fail closed unless the resolved connection is a configured PostgreSQL
     * target. Mirrors the B1.2 spatial-migration guard so seeding can never
     * touch the application database.
     */
    private function guardSpatialConnection(): void
    {
        $name = self::CONNECTION;
        $conf = config("database.connections.{$name}");

        if (empty($conf) || (empty($conf['url']) && empty($conf['host']))) {
            throw new \RuntimeException(
                "[Batch 2A/spatial] Connection [{$name}] is not configured. This seeder targets the "
                . "dedicated PostGIS cluster ONLY. Set SPATIAL_* and run with --database={$name}."
            );
        }

        $driver = DB::connection($name)->getDriverName();
        if ($driver !== 'pgsql') {
            throw new \RuntimeException(
                "[Batch 2A/spatial] Connection [{$name}] resolves to driver [{$driver}], not 'pgsql'. "
                . "Refusing to seed place_categories anywhere but the PostGIS cluster."
            );
        }
    }
}
