<?php

namespace Tests\Feature\Stellar\Matching\Baseline;

use App\Models\BuyerAgentAuction;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Bridge\LazyImportResult;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-A0 — where Smart Tags and Location DNA do (and do not) touch live matching today.
 *
 * CHARACTERIZATION OF CURRENT BEHAVIOR — NOT DESIRED BUSINESS LOGIC.
 *
 * Today neither subsystem feeds a score: matching reads the MLS row's own
 * coordinates and raw fields, never `smart_tag_assignments`, never the seeker's
 * Smart Tag picks, and never `property_location_dna`. What the results path DOES
 * do is ask the lazy importer to import with Smart Tag derivation OFF while
 * leaving its Location DNA dispatch at the importer's default. A later phase that
 * starts consuming either subsystem must change these tests on purpose.
 */
class PreConvergenceIntegrationBoundaryBaselineTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    public function test_the_results_path_imports_with_smart_tag_derivation_off_and_dna_dispatch_at_default(): void
    {
        $this->storeBaselineFixture('residential');
        $seen = null;

        $lazy = $this->createMock(LazyBridgeImportService::class);
        $lazy->expects($this->once())->method('importForCriteria')
            ->willReturnCallback(function (...$args) use (&$seen) {
                $seen = $args;

                return LazyImportResult::cached(0);
            });

        $this->baselineMatchService($lazy)->match($this->baselinePayload(['preferred_cities' => ['ST PETERSBURG']]), 200, 'tenant');

        $this->assertSame('tenant', $seen[1], 'role is passed through');
        $this->assertNull($seen[2], 'no page ceiling override');
        $this->assertNull($seen[3], 'no record ceiling override');
        $this->assertNull($seen[4], 'no provider admission callback');
        $this->assertTrue($seen[5], 'CURRENT: dispatchDna left at the importer default (true)');
        $this->assertFalse($seen[6], 'deriveSmartTags: false');
    }

    public function test_smart_tag_assignments_currently_do_not_affect_score_or_explanation(): void
    {
        $listing = $this->storeBaselineFixture('residential');
        $payload = $this->baselinePayload([
            'preferred_cities' => ['ST PETERSBURG'],
            'wants_pool' => true, 'wants_waterfront' => true, 'community_feature_keywords' => ['Pool'],
        ]);

        $before = $this->projectScoredListing($listing, $payload);

        foreach (['private_pool', 'waterfront', 'community_pool'] as $tag) {
            DB::table('smart_tag_assignments')->insert([
                'listing_type' => 'bridge', 'listing_id' => $listing->id, 'tag_key' => $tag,
                'context' => 'residential.sale', 'state' => 'present', 'winning_source' => 'structured_mls',
                'has_conflict' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->assertSame($before, $this->projectScoredListing($listing, $payload), 'a resolved "private_pool" tag earns no pool points');
        $this->assertSame(0, $before['score']['category_scores']['amenities']);
    }

    public function test_seeker_smart_tag_picks_currently_do_not_reach_the_criteria_payload(): void
    {
        $userId = DB::table('users')->insertGetId([
            'first_name' => 'P1A0', 'last_name' => 'Tags', 'name' => 'P1A0 Tags', 'short_id' => 'P1A0T' . uniqid(),
            'user_name' => 'p1a0t_' . uniqid(), 'email' => 'p1a0t-' . uniqid() . '@example.com',
            'password' => bcrypt('password'), 'user_type' => 'buyer', 'is_approved' => true,
            'is_super' => false, 'is_deleted' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = DB::table('buyer_agent_auctions')->insertGetId([
            'user_id' => $userId, 'title' => 'P1-A0 tags', 'is_approved' => true, 'is_sold' => false,
            'is_draft' => false, 'referral_locked' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $offer = BuyerAgentAuction::findOrFail($id);
        $offer->saveMeta('workflow_type', 'offer_listing');
        $offer->saveMeta('property_type', 'residential');
        $offer->saveMeta('location_dna_preferences', json_encode(['state' => 'FL']));

        $loader = new BuyerOfferListingCriteriaLoader();
        $before = $loader->loadById($id, [$userId]);

        DB::table('smart_tag_seeker_preferences')->insert([
            'subject_type' => 'buyer_offer_listing', 'subject_id' => $id, 'user_id' => $userId,
            'seeker_role' => 'buyer', 'tag_key' => 'private_pool', 'context' => 'residential.sale',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame($before, $loader->loadById($id, [$userId]));
    }

    public function test_location_dna_rows_currently_do_not_affect_scoring_and_nothing_is_dispatched(): void
    {
        Bus::fake();

        // No STELLAR_FloodZoneCode on the feed record…
        $listing = $this->storeBaselineFixture('residential', ['STELLAR_FloodZoneCode' => null], 'noflood');
        $payload = $this->baselinePayload([
            'preferred_cities' => ['ST PETERSBURG'],
            'radius_searches'  => [['lat' => (float) $listing->latitude, 'lng' => (float) $listing->longitude, 'radius_miles' => 2]],
        ]);
        $before = $this->projectScoredListing($listing, $payload);

        // …while Location DNA holds a DIFFERENT geocoded point and a flood summary.
        DB::table('property_location_dna')->insert([
            'listing_type' => 'bridge', 'listing_id' => $listing->id,
            'geocoded_lat' => (float) $listing->latitude + 0.5, 'geocoded_lng' => (float) $listing->longitude + 0.5,
            'geocode_status' => 'success',
            'summary_json' => json_encode(['flood_zone' => ['zone' => 'AE', 'sfha' => true]]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $after = $this->projectScoredListing($listing, $payload);
        $this->assertSame($before, $after, 'matching measures from bridge_properties coordinates only');
        $this->assertSame(24, $after['score']['category_scores']['location'], '18 radius (listing at the centre) + 6 city = the 24-point Phase-1 cap; a Location DNA point 0.5° away changes nothing');
        $this->assertContains(
            'flood_zone_data_absent',
            array_column($after['explain']['caution_flags'], 'type'),
            'the flood caution reads the raw feed field, not Location DNA'
        );

        $this->baselineMatchService()->match($payload, 200, 'buyer');
        (new BuyerMatchResultBuilder())->buildDetailed((new BuyerMatchScorer())->score($listing, $payload), $payload);
        Bus::assertNothingDispatched();
    }
}
