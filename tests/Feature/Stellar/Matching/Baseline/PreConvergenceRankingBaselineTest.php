<?php

namespace Tests\Feature\Stellar\Matching\Baseline;

use App\Models\BuyerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\BuyerResultViewMapper;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\Matching\PreConvergenceMatchingFixtures;
use Tests\TestCase;

/**
 * P1-A0 — result ORDER on the live results path, and the loader → payload →
 * service composition for each live criteria type.
 *
 * CHARACTERIZATION OF CURRENT BEHAVIOR — NOT DESIRED BUSINESS LOGIC.
 *
 * What is and is not pinned about order, deliberately:
 *   · PINNED: results are sorted by total_score DESC, and the view mapper keeps
 *     that order. Every case here has DISTINCT scores, so the order is a property
 *     of the engine, not of the database.
 *   · NOT PINNED: the order of EQUAL scores. usort() is stable, so ties keep the
 *     SQL order, and the SQL ORDER BY (|list_price − reference|) has no tie-break
 *     and is absent entirely when the seeker gave no price (plan D-4 — the absence
 *     itself is pinned in PreConvergenceKnownDefectCharacterizationTest). Pinning a
 *     tie order here would pin incidental database row order.
 */
class PreConvergenceRankingBaselineTest extends TestCase
{
    use DatabaseTransactions;
    use PreConvergenceMatchingFixtures;

    public function test_results_are_ordered_by_total_score_descending_and_the_mapper_keeps_that_order(): void
    {
        // Same property, prices stepping away from the ideal → strictly falling price
        // score; one variant also sits outside the requested city (no location points).
        $this->storeBaselineFixture('residential', ['ListPrice' => 200000], 'exact');
        $this->storeBaselineFixture('residential', ['ListPrice' => 230000], 'plus15');
        $this->storeBaselineFixture('residential', ['ListPrice' => 260000], 'plus30');
        $this->storeBaselineFixture('residential', ['ListPrice' => 300000], 'plus50');
        $this->storeBaselineFixture('residential', ['ListPrice' => 200000, 'City' => 'GULFPORT'], 'exact_other_city');

        $payload = $this->baselinePayload([
            'preferred_cities' => ['ST PETERSBURG', 'GULFPORT'],
            'ideal_price'      => 200000,
        ]);
        $results = $this->baselineMatchService()->match($payload, 200, 'buyer');

        $got = $results->map(fn ($r) => [$r->listingKey, $r->totalScore])->all();
        $this->assertSame(
            [
                [self::baselineKey('residential', 'exact'), 63],
                [self::baselineKey('residential', 'exact_other_city'), 63],
                [self::baselineKey('residential', 'plus15'), 60],
                [self::baselineKey('residential', 'plus30'), 57],
                [self::baselineKey('residential', 'plus50'), 53],
            ],
            // The only tie (63/63) is broken by SQL order here, which is |price − ideal|
            // then unspecified — the two are sorted so the assertion does not depend on it.
            self::sortTiesByKey($got)
        );

        $cards = (new BuyerResultViewMapper())->map($results);
        $this->assertSame(
            $results->map(fn ($r) => $r->listingKey)->all(),
            array_column($cards, 'listing_key'),
            'the results page renders in engine order'
        );
    }

    public function test_the_price_reference_for_sql_ordering_is_ideal_else_85_percent_of_max(): void
    {
        $ideal = (new BuyerMatchQueryBuilder())->build($this->baselinePayload(['preferred_state' => 'FL', 'ideal_price' => 300000, 'max_price' => 500000]));
        $max   = (new BuyerMatchQueryBuilder())->build($this->baselinePayload(['preferred_state' => 'FL', 'max_price' => 500000]));

        $this->assertSame('(list_price IS NULL), ABS(list_price - ?)', $ideal->getQuery()->orders[0]['sql']);
        $this->assertContains(300000, $ideal->getQuery()->getBindings());
        $this->assertContains(425000, $max->getQuery()->getBindings(), 'max 500,000 × 0.85');
    }

    // =========================================================================
    // Loader → payload → service: the two live criteria types end to end.
    // =========================================================================

    public function test_buyer_offer_listing_criteria_match_end_to_end(): void
    {
        $this->storeAllBaselineFixtures();
        $userId = $this->makeUser('buyer');
        $offer  = $this->makeOfferListing(BuyerAgentAuction::class, 'buyer_agent_auctions', $userId, [
            'property_type'            => 'residential',
            'location_dna_preferences' => json_encode(['cities' => ['St Petersburg'], 'state' => 'FL']),
            'maximum_budget'           => '250000',
            'bedrooms'                 => '2',
            'bathrooms'                => '2',
            'pool_needed'              => 'No',
        ]);

        $data = (new BuyerOfferListingCriteriaLoader())->loadById($offer->id, [$userId]);
        $this->assertNotNull($data);

        $payload = new BuyerCriteriaPayload($data);
        $results = $this->baselineMatchService()->match($payload, 200, 'buyer');

        $this->assertSame(
            [[self::baselineKey('residential'), 47]],
            $results->map(fn ($r) => [$r->listingKey, $r->totalScore])->all(),
            'buyer offer listing: Residential, FL, under $250k with 2/2'
        );
        // CHARACTERIZATION, two current behaviours visible in one score:
        //  · location 0 — the loader normalises the typed city to 'St Petersburg'
        //    (only 'St.' with a dot becomes 'Saint'), the feed stores 'ST PETERSBURG',
        //    and the scorer's city match is exact; the listing is selected through the
        //    state tier alone.
        //  · amenities 0 — pool_needed = No reaches the payload as wants_pool = false,
        //    which earns nothing on a home with no pool (plan D-1).
        $this->assertSame(
            ['location' => 0, 'price' => 20, 'size' => 15, 'property_type' => 7, 'amenities' => 0, 'financial' => 5, 'lifestyle' => 0, 'non_residential' => 0],
            $results->first()->categoryScores
        );
        $this->assertFalse($payload->wantsPool);
    }

    public function test_tenant_offer_listing_criteria_match_end_to_end(): void
    {
        $this->storeAllBaselineFixtures();
        $userId = $this->makeUser('tenant');
        $offer  = $this->makeOfferListing(TenantAgentAuction::class, 'tenant_agent_auctions', $userId, [
            'property_type'            => 'Residential',
            'location_dna_preferences' => json_encode(['cities' => ['New Smyrna Beach'], 'state' => 'FL']),
            'maximum_budget'           => '3600',
            'desired_lease_length'     => '1 Year',
        ]);

        $data = (new TenantOfferListingCriteriaLoader())->loadById($offer->id, [$userId]);
        $this->assertNotNull($data);
        $this->assertSame(['Residential Lease'], $data['property_types'], 'a tenant Residential criteria searches rentals');

        $payload = new BuyerCriteriaPayload($data);
        $results = $this->baselineMatchService()->match($payload, 200, 'tenant');

        $this->assertSame(
            [self::baselineKey('residential_lease')],
            $results->map(fn ($r) => $r->listingKey)->all()
        );
        // CHARACTERIZATION: the tenant offer loader passes the city through as typed
        // ('New Smyrna Beach'); the feed stores 'NEW SMYRNA BEACH'. The scorer's city
        // comparison is exact, so the listing earns 0 location points and is selected
        // only through the state tier. $3,495 against a $3,600 budget is within 10% of
        // the ceiling → 15 of 20 price points.
        $this->assertSame(
            ['location' => 0, 'price' => 15, 'size' => 15, 'property_type' => 7, 'amenities' => 10, 'financial' => 5, 'lifestyle' => 2, 'non_residential' => 0],
            $results->first()->categoryScores
        );
    }

    // =========================================================================

    /** @param list<array{string,int}> $rows @return list<array{string,int}> */
    private static function sortTiesByKey(array $rows): array
    {
        usort($rows, fn ($a, $b) => [$b[1], $a[0]] <=> [$a[1], $b[0]]);

        return $rows;
    }

    private function makeUser(string $type): int
    {
        return DB::table('users')->insertGetId([
            'first_name'  => 'P1A0',
            'last_name'   => 'Baseline',
            'name'        => 'P1A0 Baseline',
            'short_id'    => 'P1A0' . uniqid(),
            'user_name'   => 'p1a0_' . uniqid(),
            'email'       => 'p1a0-' . uniqid() . '@example.com',
            'password'    => bcrypt('password'),
            'user_type'   => $type,
            'is_approved' => true,
            'is_super'    => false,
            'is_deleted'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** @param class-string<BuyerAgentAuction|TenantAgentAuction> $model */
    private function makeOfferListing(string $model, string $table, int $userId, array $meta): BuyerAgentAuction|TenantAgentAuction
    {
        $id = DB::table($table)->insertGetId(($table === 'buyer_agent_auctions' ? ['title' => 'P1-A0 buyer offer listing'] : []) + [
            'user_id'         => $userId,
            'is_approved'     => true,
            'is_sold'         => false,
            'is_draft'        => false,
            'referral_locked' => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $auction = $model::findOrFail($id);
        $auction->saveMeta('workflow_type', 'offer_listing');
        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return $auction->fresh();
    }
}
