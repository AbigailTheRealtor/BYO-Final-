<?php

namespace Database\Seeders;

use App\Services\Spatial\OvertureCategoryMap;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2A (B1).
 *
 * Seeds the FIRST-SLICE `place_category_mappings` rows (SSOT §7.2 / §8.2):
 * Overture primary category → canonical category_key. 8 source categories,
 * many-to-one onto 7 canonical keys (fitness_center + gym → gym).
 *
 * GUARDED + NOT REGISTERED — same posture as SpatialFirstSliceCategorySeeder.
 * FK target place_categories must be seeded first:
 *   php artisan db:seed --class=Database\\Seeders\\SpatialFirstSliceCategorySeeder      --database=pgsql_spatial
 *   php artisan db:seed --class=Database\\Seeders\\SpatialOvertureCategoryMappingSeeder --database=pgsql_spatial
 *
 * Batch 2A does NOT run this against PostGIS — authored, not applied.
 */
class SpatialOvertureCategoryMappingSeeder extends Seeder
{
    private const CONNECTION = 'pgsql_spatial';

    /** The seed rows — pure, derived from the taxonomy SSOT. Exposed for tests. */
    public static function rows(): array
    {
        return (new OvertureCategoryMap())->mappingRows();
    }

    public function run(): void
    {
        $this->guardSpatialConnection();

        $conn = DB::connection(self::CONNECTION);
        foreach (self::rows() as $row) {
            $conn->table('place_category_mappings')->upsert(
                [[
                    'source'          => $row['source'],
                    'source_category' => $row['source_category'],
                    'category_key'    => $row['category_key'],
                ]],
                ['source', 'source_category'],
                ['category_key']
            );
        }
    }

    /** Fail closed unless the resolved connection is a configured pgsql target. */
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
                . "Refusing to seed place_category_mappings anywhere but the PostGIS cluster."
            );
        }
    }
}
