<?php

namespace Tests\Feature\Console;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaVersionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Stage E0 — ldna:rerank-all --from-cache --only-stale.
 *
 * Recomputes rankings from stored candidates (no API call) for listings whose
 * scoring version is stale, and re-stamps them to the current scoring version.
 */
class LdnaRerankAllTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'seller_agent_auction';
    private const LISTING_ID   = 880101;

    protected function setUp(): void
    {
        parent::setUp();
        PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)->where('listing_id', self::LISTING_ID)->delete();
        PropertyLocationDna::where('listing_type', self::LISTING_TYPE)->where('listing_id', self::LISTING_ID)->delete();

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Main St',
            'source_city'    => 'Tampa',
            'source_state'   => 'FL',
            'geocoded_lat'   => 27.95,
            'geocoded_lng'   => -82.45,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);
    }

    private function seedStandardRow(string $category, float $rating, int $reviews): void
    {
        PropertyLocationPoi::create([
            'listing_type'         => self::LISTING_TYPE,
            'listing_id'           => self::LISTING_ID,
            'poi_category'         => $category,
            'rank'                 => 1,
            'poi_name'             => ucfirst($category) . ' Place',
            'poi_lat'              => 27.96,
            'poi_lng'              => -82.46,
            'source_lat'           => 27.95,
            'source_lng'           => -82.45,
            'distance_miles'       => 0.7,
            'rating'               => $rating,
            'user_ratings_total'   => $reviews,
            'types_json'           => [],
            'data_source'          => 'google_places',
            'status'               => 'found',
            'pois_fetch_version'   => (new LocationDnaVersionService())->fetchVersion(), // current fetch
            'pois_scoring_version' => 'stale-scoring', // deliberately stale
        ]);
    }

    public function test_recomputes_stale_listings_from_cache_and_restamps_scoring_version(): void
    {
        $this->seedStandardRow('school', 4.5, 120);
        $this->seedStandardRow('restaurant', 4.8, 500);

        $this->artisan('ldna:rerank-all --from-cache --only-stale')->assertSuccessful();

        $current = (new LocationDnaVersionService())->scoringVersion();

        // No row retains the stale scoring version; each carries the current one + a score.
        $rows = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)->get();

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame($current, $row->pois_scoring_version, "Category {$row->poi_category} not re-stamped");
            $this->assertNotNull($row->ranking_score);
        }

        $this->assertDatabaseMissing('property_location_pois', [
            'listing_type'         => self::LISTING_TYPE,
            'listing_id'           => self::LISTING_ID,
            'pois_scoring_version' => 'stale-scoring',
        ]);
    }

    public function test_requires_from_cache_flag(): void
    {
        $this->artisan('ldna:rerank-all --only-stale')->assertFailed();
    }
}
