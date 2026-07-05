<?php

namespace Tests\Unit\Services\LocationDna;

use App\Models\PropertyLocationPoi;
use Tests\TestCase;

/**
 * Verifies the Stage E0 row-level version columns on property_location_pois:
 * present, nullable, and mass-assignable. Lives alongside the other DB-backed
 * Location DNA tests so the harness's SQLite :memory: forcing engages cleanly.
 */
class PropertyLocationPoiVersionColumnsTest extends TestCase
{
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
