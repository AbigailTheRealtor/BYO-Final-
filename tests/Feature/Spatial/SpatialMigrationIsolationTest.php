<?php

namespace Tests\Feature\Spatial;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * B1.2 — the spatial migrations are invisible to the default migrate run and to
 * the SQLite test suite. This is the structural guarantee behind "additive, no
 * consumers, no change to default migration behavior."
 *
 * The mechanism is Laravel's own migrator: getMigrationFiles() globs
 * `$path.'/*_*.php'` (non-recursive), so files under database/migrations/spatial/
 * cannot be picked up by a run over database/migrations. These tests pin that.
 */
class SpatialMigrationIsolationTest extends TestCase
{
    /** Every table the 11 spatial migrations create. NONE may exist under SQLite. */
    private const SPATIAL_TABLES = [
        'place_categories', 'place_category_mappings', 'places', 'place_authority_links',
        'boundaries', 'boundaries_parts', 'listing_locations', 'addresses',
        'isochrone_cache', 'corpus_imports',
    ];

    /** @test */
    public function the_spatial_subdirectory_holds_the_eleven_migrations(): void
    {
        $spatial = glob(base_path('database/migrations/spatial') . '/*_*.php');
        $this->assertCount(11, $spatial, 'Expected exactly 11 spatial migrations.');
    }

    /** @test */
    public function the_default_migration_glob_excludes_the_spatial_subdirectory(): void
    {
        // Exactly the glob Migrator::getMigrationFiles() uses for the default path.
        $default = glob(base_path('database/migrations') . '/*_*.php');
        $spatial = glob(base_path('database/migrations/spatial') . '/*_*.php');

        $this->assertNotEmpty($spatial);
        foreach ($spatial as $file) {
            $this->assertNotContains($file, $default,
                'A spatial migration leaked into the default migration path glob.');
        }
    }

    /** @test */
    public function the_migrator_does_not_enumerate_spatial_migrations_on_the_default_path(): void
    {
        $files = app('migrator')->getMigrationFiles([base_path('database/migrations')]);

        foreach (array_keys($files) as $name) {
            $this->assertStringNotContainsString('spatial_core', $name,
                "Migrator picked up a spatial migration [{$name}] on the default path.");
        }
    }

    /** @test */
    public function no_postgis_table_exists_under_the_sqlite_test_suite(): void
    {
        // The suite runs on SQLite in-memory and has migrated the default path.
        $this->assertSame('sqlite', Schema::getConnection()->getDriverName());

        foreach (self::SPATIAL_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table),
                "Spatial table [{$table}] must not exist under SQLite — no PostGIS DDL may run here.");
        }
    }
}
