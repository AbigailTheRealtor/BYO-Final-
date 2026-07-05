<?php

namespace Tests\Feature\Console;

use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaVersionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Stage E0 — ldna:stamp-versions backfill command.
 */
class LdnaStampVersionsTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 880001;

    protected function setUp(): void
    {
        parent::setUp();
        PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)->delete();
    }

    private function seedRow(string $category): PropertyLocationPoi
    {
        return PropertyLocationPoi::create([
            'listing_type'  => self::LISTING_TYPE,
            'listing_id'    => self::LISTING_ID,
            'poi_category'  => $category,
            'rank'          => 1,
            'poi_name'      => 'Some Place',
            'source_lat'    => 27.95,
            'source_lng'    => -82.45,
            'data_source'   => 'google_places',
            'status'        => 'found',
            // pois_fetch_version / pois_scoring_version left NULL (pre-backfill state)
        ]);
    }

    public function test_stamps_null_versions_to_current(): void
    {
        $this->seedRow('school');
        $this->seedRow('park');

        $this->artisan('ldna:stamp-versions')->assertSuccessful();

        $versions = new LocationDnaVersionService();
        $rows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)->get();

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame($versions->fetchVersion(), $row->pois_fetch_version);
            $this->assertSame($versions->scoringVersion(), $row->pois_scoring_version);
        }
    }

    public function test_is_idempotent_on_second_run(): void
    {
        $this->seedRow('school');

        $this->artisan('ldna:stamp-versions')->assertSuccessful();
        $stampedOnce = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)->first()->pois_fetch_version;

        // Second run: already-stamped rows are excluded (NULL filter) and unchanged.
        $this->artisan('ldna:stamp-versions')->assertSuccessful();
        $stampedTwice = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)->first()->pois_fetch_version;

        $this->assertSame($stampedOnce, $stampedTwice);
        $this->assertNotNull($stampedTwice);
    }
}
