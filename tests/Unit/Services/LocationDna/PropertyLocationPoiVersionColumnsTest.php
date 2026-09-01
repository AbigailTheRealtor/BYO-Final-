<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationPoi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Verifies the Stage E0 row-level version columns on property_location_pois:
 * present, nullable, and mass-assignable. Lives alongside the other DB-backed
 * Location DNA tests so the harness's SQLite :memory: forcing engages cleanly.
 *
 * WHY `DatabaseTransactions` IS LOAD-BEARING HERE
 * -----------------------------------------------
 * It is not decoration, and it is not about rollback. `Tests\TestCase::setUpTraits()`
 * builds the SQLite `:memory:` schema — `artisan migrate --force`, once per process —
 * ONLY for tests that declare this trait. Without it nothing migrates, and both tests
 * below died on `no such table: property_location_pois` before reaching an assertion.
 *
 * The file previously carried a `setUp()` that deleted rows for its fixture ids,
 * described as "self-healing ... even if a prior run committed against a shared
 * database". That premise no longer holds and should not be re-introduced: PR #102
 * pinned the suite to SQLite `:memory:` (tests/bootstrap.php blanks DATABASE_URL and
 * every SPATIAL_ and PG_ prefixed variable before the autoloader), so there is no
 * shared database to inherit rows from, and the wrapping transaction discards this
 * test's own writes. A hand-rolled cleanup would only have masked the missing schema —
 * which is exactly what it did, by being the statement that raised first.
 *
 * The table itself is created by 2026_05_31_000007_create_property_location_pois_table
 * and the two columns by 2026_07_05_000002_add_version_columns_to_property_location_pois_table;
 * tests/Feature/ModelTableExistenceTest.php independently proves the table builds.
 */
class PropertyLocationPoiVersionColumnsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_version_columns_are_mass_assignable_and_persist(): void
    {
        $poi = PropertyLocationPoi::create([
            'listing_type'         => 'seller',
            'listing_id'           => 990001,
            'poi_category'         => 'school',
            'rank'                 => 1,
            'data_source'          => 'google_places',
            'status'               => 'completed',
            'pois_fetch_version'   => str_repeat('a', 64),
            'pois_scoring_version' => str_repeat('b', 64),
        ]);

        $fresh = PropertyLocationPoi::find($poi->id);

        $this->assertSame(str_repeat('a', 64), $fresh->pois_fetch_version);
        $this->assertSame(str_repeat('b', 64), $fresh->pois_scoring_version);
    }

    public function test_version_columns_default_to_null(): void
    {
        $poi = PropertyLocationPoi::create([
            'listing_type' => 'seller',
            'listing_id'   => 990002,
            'poi_category' => 'park',
            'rank'         => 1,
            'data_source'  => 'google_places',
            'status'       => 'completed',
        ]);

        $fresh = PropertyLocationPoi::find($poi->id);

        $this->assertNull($fresh->pois_fetch_version);
        $this->assertNull($fresh->pois_scoring_version);
    }
}
