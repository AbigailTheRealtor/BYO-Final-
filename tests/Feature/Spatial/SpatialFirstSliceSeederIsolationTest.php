<?php

namespace Tests\Feature\Spatial;

use Database\Seeders\SpatialFirstSliceCategorySeeder;
use Database\Seeders\SpatialOvertureCategoryMappingSeeder;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Batch 2A (B1/B3) — the guarded seeders are invisible to the default
 * db:seed / SQLite suite, and FAIL CLOSED rather than writing to any
 * non-PostGIS connection. This is the structural guarantee behind
 * "authored, not applied; no PostGIS in Batch 2A".
 */
class SpatialFirstSliceSeederIsolationTest extends TestCase
{
    /** @test */
    public function the_default_database_seeder_does_not_register_the_spatial_seeders(): void
    {
        $source = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString('SpatialFirstSliceCategorySeeder', $source);
        $this->assertStringNotContainsString('SpatialOvertureCategoryMappingSeeder', $source);
    }

    /** @test */
    public function the_category_seeder_fails_closed_off_the_spatial_cluster(): void
    {
        // SPATIAL_* are unset → pgsql_spatial is inert → the guard throws rather
        // than writing place_categories to the SQLite app database.
        $this->expectException(\RuntimeException::class);
        (new SpatialFirstSliceCategorySeeder())->run();
    }

    /** @test */
    public function the_mapping_seeder_fails_closed_off_the_spatial_cluster(): void
    {
        $this->expectException(\RuntimeException::class);
        (new SpatialOvertureCategoryMappingSeeder())->run();
    }

    /** @test */
    public function no_spatial_table_is_created_by_attempting_to_seed(): void
    {
        $this->assertSame('sqlite', Schema::getConnection()->getDriverName());

        try {
            (new SpatialFirstSliceCategorySeeder())->run();
        } catch (\RuntimeException $e) {
            // expected — fail closed
        }

        $this->assertFalse(Schema::hasTable('place_categories'),
            'seeding must never create place_categories on the app/SQLite database');
    }
}
