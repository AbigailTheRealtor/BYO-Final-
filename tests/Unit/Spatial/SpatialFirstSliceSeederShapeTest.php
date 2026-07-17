<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\OvertureCategoryMap;
use Database\Seeders\SpatialFirstSliceCategorySeeder;
use Database\Seeders\SpatialOvertureCategoryMappingSeeder;
use PHPUnit\Framework\TestCase;

/**
 * Batch 2A (B1/B3) — the guarded seeders expose exactly the taxonomy SSOT row
 * shape, and reconcile with each other. This is a DATA-SHAPE test only; it
 * never runs the seeders (which fail closed off the PostGIS cluster).
 */
class SpatialFirstSliceSeederShapeTest extends TestCase
{
    /** @test */
    public function category_seeder_rows_equal_the_taxonomy_registry(): void
    {
        $this->assertSame(
            (new OvertureCategoryMap())->categoryRows(),
            SpatialFirstSliceCategorySeeder::rows()
        );
    }

    /** @test */
    public function mapping_seeder_rows_equal_the_taxonomy_mappings(): void
    {
        $this->assertSame(
            (new OvertureCategoryMap())->mappingRows(),
            SpatialOvertureCategoryMappingSeeder::rows()
        );
    }

    /** @test */
    public function category_seed_rows_have_the_place_categories_column_shape(): void
    {
        foreach (SpatialFirstSliceCategorySeeder::rows() as $row) {
            $this->assertSame(
                ['category_key', 'label', 'thematic_block', 'base_source', 'rank_strategy', 'exclusion_rules', 'enabled'],
                array_keys($row)
            );
            $this->assertSame('confidence', $row['rank_strategy']);
            $this->assertNull($row['exclusion_rules']);
        }
    }

    /** @test */
    public function mapping_seed_rows_have_the_place_category_mappings_column_shape(): void
    {
        foreach (SpatialOvertureCategoryMappingSeeder::rows() as $row) {
            $this->assertSame(['source', 'source_category', 'category_key'], array_keys($row));
            $this->assertSame('overture', $row['source']);
        }
    }

    /** @test */
    public function every_mapping_target_exists_among_the_category_rows(): void
    {
        $categoryKeys = array_column(SpatialFirstSliceCategorySeeder::rows(), 'category_key');
        foreach (SpatialOvertureCategoryMappingSeeder::rows() as $row) {
            $this->assertContains($row['category_key'], $categoryKeys,
                "mapping target {$row['category_key']} has no place_categories row (FK would fail)");
        }
    }
}
