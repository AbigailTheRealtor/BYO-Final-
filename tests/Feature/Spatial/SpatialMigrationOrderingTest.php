<?php

namespace Tests\Feature\Spatial;

use Tests\TestCase;

/**
 * B1.2 — the spatial migrations run (and roll back) in an order that respects
 * every foreign-key dependency (plan §2 table order, §7 rollback strategy).
 *
 * FK edges that constrain ordering:
 *   • place_categories  →  place_category_mappings, places   (parent first)
 *   • boundaries        →  boundaries_parts                  (parent first)
 * Rollback is the reverse, so children drop before their parents.
 */
class SpatialMigrationOrderingTest extends TestCase
{
    /** Ordinal-sorted spatial migration basenames. */
    private function orderedNames(): array
    {
        $files = glob(base_path('database/migrations/spatial') . '/*_*.php');
        sort($files); // ordinal filename order == migrator run order
        return array_map(fn ($f) => basename($f, '.php'), $files);
    }

    /**
     * Position of the migration whose logical name ends with $needle. Suffix match
     * (not substring) so `create_boundaries` does not collide with
     * `create_boundaries_parts`, nor `create_place_categories` with `..._mappings`.
     */
    private function posOf(array $names, string $needle): int
    {
        foreach ($names as $i => $n) {
            if (str_ends_with($n, $needle)) {
                return $i;
            }
        }
        $this->fail("No spatial migration matches [{$needle}].");
    }

    /** @test */
    public function there_are_exactly_thirteen_migrations_in_the_documented_order(): void
    {
        $expected = [
            'spatial_core_enable_extensions',
            'spatial_core_create_place_categories',
            'spatial_core_create_place_category_mappings',
            'spatial_core_create_places',
            'spatial_core_create_place_authority_links',
            'spatial_core_create_boundaries',
            'spatial_core_create_boundaries_parts',
            'spatial_core_create_listing_locations',
            'spatial_core_create_addresses',
            'spatial_core_create_isochrone_cache',
            'spatial_core_create_corpus_imports',

            // Extends `addresses` (09) with corpus versioning + the dedupe key.
            // Must sort after it: it alters a table 09 creates.
            'spatial_core_version_address_corpus',

            // Indexes the column set the AddressPoint rung resolves on. Sorts
            // after the migration that adds `corpus_version` — it indexes a
            // column that migration creates.
            'spatial_core_index_address_lookup',
        ];

        $actual = array_map(
            fn ($n) => preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $n),
            $this->orderedNames()
        );

        $this->assertSame($expected, $actual);
    }

    /** @test */
    public function extensions_run_first(): void
    {
        // btree_gist must exist before migration 04 builds the composite GiST.
        $this->assertSame(0, $this->posOf($this->orderedNames(), 'enable_extensions'));
    }

    /** @test */
    public function foreign_key_parents_migrate_before_their_children(): void
    {
        $n = $this->orderedNames();

        $this->assertLessThan($this->posOf($n, 'create_place_category_mappings'), $this->posOf($n, 'create_place_categories'));
        $this->assertLessThan($this->posOf($n, 'create_places'), $this->posOf($n, 'create_place_categories'));
        $this->assertLessThan($this->posOf($n, 'create_boundaries_parts'), $this->posOf($n, 'create_boundaries'));
    }

    /** @test */
    public function rollback_reverses_the_order_so_children_drop_before_parents(): void
    {
        $rollback = array_reverse($this->orderedNames());

        $this->assertLessThan($this->posOf($rollback, 'create_place_categories'), $this->posOf($rollback, 'create_place_category_mappings'));
        $this->assertLessThan($this->posOf($rollback, 'create_place_categories'), $this->posOf($rollback, 'create_places'));
        $this->assertLessThan($this->posOf($rollback, 'create_boundaries'), $this->posOf($rollback, 'create_boundaries_parts'));
    }

    /** @test */
    public function every_migration_declares_the_connection_and_the_guard(): void
    {
        foreach (glob(base_path('database/migrations/spatial') . '/*_*.php') as $file) {
            $src = file_get_contents($file);
            $base = basename($file);

            $this->assertStringContainsString("protected \$connection = 'pgsql_spatial';", $src,
                "{$base} must pin the pgsql_spatial connection");
            $this->assertStringContainsString('guardSpatialConnection', $src,
                "{$base} must call the fail-closed guard");
        }
    }

    /** @test */
    public function table_migrations_drop_with_cascade_on_rollback(): void
    {
        foreach (glob(base_path('database/migrations/spatial') . '/*_*.php') as $file) {
            $src = file_get_contents($file);

            if (str_contains($file, 'enable_extensions')) {
                // Extensions down() is a deliberate no-op (plan §7) — must NOT drop extensions.
                $this->assertStringNotContainsString('DROP EXTENSION', $src,
                    'The extensions migration must never DROP EXTENSION on rollback.');
                continue;
            }

            // A migration that ALTERS a table it did not create must not drop
            // it. `version_address_corpus` extends `addresses`; the table is
            // owned by `create_addresses`, and that migration's down() is what
            // drops it. Rolling back an extension must remove the extension,
            // not the table underneath it — so the rule inverts here, and the
            // assertion inverts with it rather than being skipped.
            if (str_contains($src, 'ADD COLUMN IF NOT EXISTS')) {
                $this->assertStringNotContainsString('DROP TABLE', $src,
                    basename($file) . ' alters a table it does not own; it must never DROP TABLE.');
                $this->assertStringContainsString('DROP COLUMN IF EXISTS', $src,
                    basename($file) . ' must reverse the columns it added.');
                continue;
            }

            // An index-only migration owns an index and nothing else. Same rule
            // as above, one level down: reverse exactly what you created, and
            // never reach for the table you merely indexed.
            if (str_contains($src, 'CREATE INDEX') && ! str_contains($src, 'CREATE TABLE')) {
                $this->assertStringNotContainsString('DROP TABLE', $src,
                    basename($file) . ' only indexes a table it does not own; it must never DROP TABLE.');
                $this->assertStringContainsString('DROP INDEX IF EXISTS', $src,
                    basename($file) . ' must drop the index it created.');
                continue;
            }

            $this->assertStringContainsString('DROP TABLE IF EXISTS', $src,
                basename($file) . ' must drop its table on rollback.');
        }
    }
}
