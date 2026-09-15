<?php

namespace Tests\Feature\Stellar;

use App\Models\BridgeProperty;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerCriteriaAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Bridge\LazyImportResult;
use App\Services\Stellar\BuyerCriteriaLoader;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\BuyerResultViewMapper;
use App\Services\Stellar\MatchCheck\MatchReportFactory;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\BuyerMatchService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Services\Stellar\PropertyMatchContextService;
use App\Services\Stellar\TenantCriteriaLoader;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use App\Support\Geo\GreatCircleDistance;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Important Places reach MLS matching: each MLS listing is measured, straight-line and locally, from
 * its own coordinate to each of the client's private Important Places, and judged against the miles
 * the client asked for — through the ONE matcher Buyer and Tenant share.
 *
 * What this pins:
 *  - inside passes, outside fails that requirement, several places are judged independently;
 *  - a listing with no MLS coordinate is "distance unavailable", never a match, and earns nothing;
 *  - places SCORE the candidates the existing geography selected: they exclude nothing and add nothing;
 *  - Buyer and Tenant, Criteria and Offer Listing, all carry the places into the same matcher;
 *  - no output — mapped result, rendered card, detail-page context, Match Check report — says where a
 *    place is, and the stored place is untouched by matching.
 *
 * Fixed coordinates on one meridian (there the distance is exactly R·Δφ). No network: the lazy MLS
 * import is mocked, and the matcher never makes a request of its own.
 */
class ImportantPlaceMlsMatchingTest extends TestCase
{
    use DatabaseTransactions;

    private const LNG      = -81.3792;
    private const WORK_LAT = 28.5383;

    private const WORK_ADDRESS = '400 S Orange Ave, Orlando, FL 32801';

    /** Latitude $miles north (negative = south) of the Work place, on the fixed meridian. */
    private static function fromWork(float $miles): float
    {
        return self::WORK_LAT + $miles * 180 / (M_PI * GreatCircleDistance::EARTH_RADIUS_MILES);
    }

    private static function work(float $requiredMiles = 3): array
    {
        return [
            'type' => 'Work', 'type_other' => '', 'address' => self::WORK_ADDRESS,
            'lat' => self::WORK_LAT, 'lng' => self::LNG,
            'distance_pref' => 'miles', 'distance_value' => $requiredMiles, 'travel_mode' => 'driving',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('bridge_properties')) {
            $this->markTestSkipped('bridge_properties table does not exist in this environment.');
        }
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function service(): BuyerMatchService
    {
        $lazyImport = $this->createMock(LazyBridgeImportService::class);
        $lazyImport->method('importForCriteria')->willReturn(LazyImportResult::cached(0));

        return new BuyerMatchService(new BuyerMatchQueryBuilder(), new BuyerMatchScorer(), new BuyerMatchResultBuilder(), $lazyImport);
    }

    private function criteria(string $role, array $places, array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => [$role === 'tenant' ? 'Residential Lease' : 'Residential'],
            'is_55_plus_eligible' => false,
            'preferred_cities'    => ['Orlando'],
            'important_places'    => $places,
        ], $overrides));
    }

    private function listing(string $key, string $role, ?float $lat, ?float $lng, array $overrides = []): string
    {
        DB::table('bridge_properties')->insert(array_merge([
            'listing_key'             => $key,
            'listing_id'              => 'LID-' . $key,
            'standard_status'         => 'Active',
            'property_type'           => $role === 'tenant' ? 'Residential Lease' : 'Residential',
            'list_price'              => $role === 'tenant' ? 2500 : 400000,
            'city'                    => 'Orlando',
            'state_or_province'       => 'FL',
            'postal_code'             => '32801',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'senior_community_yn'     => false,
            'latitude'                => $lat,
            'longitude'               => $lng,
            'unparsed_address'        => '1 Listing Way',
            'raw_json'                => json_encode(['IDXParticipationYN' => true]),
            'created_at'              => now(),
            'updated_at'              => now(),
        ], $overrides));

        return $key;
    }

    /** @return array<string, BuyerMatchResult> keyed by listing key */
    private function match(string $role, array $places, array $criteriaOverrides = []): array
    {
        return $this->service()->match($this->criteria($role, $places, $criteriaOverrides), 200, $role)
            ->keyBy('listingKey')->all();
    }

    /** Three Orlando listings: 2.1 mi from Work, 5.4 mi from Work, and one with no MLS coordinate. */
    private function seedListings(string $role): array
    {
        $p = strtoupper($role) . '-' . uniqid();

        return [
            'near'       => $this->listing("{$p}-NEAR", $role, self::fromWork(2.1), self::LNG),
            'far'        => $this->listing("{$p}-FAR", $role, self::fromWork(-5.4), self::LNG),
            'unlocated'  => $this->listing("{$p}-NOCOORD", $role, null, null),
        ];
    }

    public static function roles(): array
    {
        return ['buyer' => ['buyer'], 'tenant' => ['tenant']];
    }

    // ── A / B / C / F: inside, outside, missing coordinate — for both roles ──

    /** @dataProvider roles */
    public function test_inside_passes_outside_fails_and_an_unlocated_listing_is_unavailable(string $role): void
    {
        $keys    = $this->seedListings($role);
        $results = $this->match($role, [self::work(3)]);

        // bridge_properties stores latitude as decimal(10,7), as production does; rounding to seven
        // places moves a distance by a few millionths of a mile, so this is the storage precision.
        [$near] = $results[$keys['near']]->importantPlaceMatches;
        $this->assertSame('Work', $near['label']);
        $this->assertEqualsWithDelta(2.1, $near['actual_miles'], 1e-4);
        $this->assertTrue($near['matches']);

        [$far] = $results[$keys['far']]->importantPlaceMatches;
        $this->assertEqualsWithDelta(5.4, $far['actual_miles'], 1e-4);
        $this->assertFalse($far['matches'], 'Outside the requested distance fails that requirement');

        [$unlocated] = $results[$keys['unlocated']]->importantPlaceMatches;
        $this->assertSame('distance_unavailable', $unlocated['status']);
        $this->assertNull($unlocated['matches'], 'No MLS coordinate is never classified as meeting the requirement');
    }

    /** @dataProvider roles */
    public function test_a_met_requirement_raises_location_and_an_unmet_or_unmeasured_one_earns_nothing(string $role): void
    {
        $keys = $this->seedListings($role);
        $with = $this->match($role, [self::work(3)]);

        // City match (6) + full proximity from the one met requirement (18) = the 24-pt location cap.
        $this->assertSame(24, $with[$keys['near']]->categoryScores['location']);
        $this->assertSame(6, $with[$keys['far']]->categoryScores['location']);
        $this->assertSame(6, $with[$keys['unlocated']]->categoryScores['location']);

        // The same listings with no Important Places: only the listing that met one moved.
        $without = $this->match($role, []);
        $this->assertSame(6, $without[$keys['near']]->categoryScores['location']);
        $this->assertSame($without[$keys['far']]->totalScore, $with[$keys['far']]->totalScore);
        $this->assertSame($without[$keys['unlocated']]->totalScore, $with[$keys['unlocated']]->totalScore);
    }

    /** @dataProvider roles */
    public function test_important_places_neither_exclude_nor_add_candidates(string $role): void
    {
        $keys = $this->seedListings($role);
        // A Tampa listing sitting right next to the Work place: outside the client's cities.
        $tampa = $this->listing(strtoupper($role) . '-TAMPA-' . uniqid(), $role, self::fromWork(0.5), self::LNG, ['city' => 'Tampa', 'postal_code' => '33602']);

        $with    = array_keys($this->match($role, [self::work(3)]));
        $without = array_keys($this->match($role, []));

        sort($with);
        sort($without);
        $this->assertSame($without, $with, 'Important Places score candidates; they never change which candidates there are');
        $this->assertContains($keys['far'], $with, 'Failing a requirement does not exclude the listing');
        $this->assertNotContains($tampa, $with, 'Being near a place does not add a listing the geography did not select');
    }

    // ── D: several places, independently ─────────────────────────────────────

    public function test_several_places_are_judged_independently(): void
    {
        $keys   = $this->seedListings('buyer');
        $school = array_merge(self::work(3), ['type' => 'School', 'lat' => self::fromWork(2.1 + 2.4)]);
        $publix = array_merge(self::work(2), ['type' => 'Other', 'type_other' => 'Publix', 'lat' => self::fromWork(2.1 - 1.2)]);
        $family = array_merge(self::work(5), ['type' => 'Family/Friends', 'lat' => self::fromWork(2.1 + 7.8)]);

        $rows = $this->match('buyer', [self::work(5), $school, $publix, $family])[$keys['near']]->importantPlaceMatches;

        $this->assertSame(['Work', 'School', 'Publix', 'Family/Friends'], array_column($rows, 'label'));
        $this->assertSame([2.1, 2.4, 1.2, 7.8], array_map(fn ($r) => round($r['actual_miles'], 1), $rows));
        $this->assertSame([true, true, true, false], array_column($rows, 'matches'));
    }

    // ── M: one distance contract for Buyer and Tenant ────────────────────────

    public function test_buyer_and_tenant_measure_the_same_listing_identically(): void
    {
        $buyer  = $this->seedListings('buyer');
        $tenant = $this->seedListings('tenant');

        $this->assertSame(
            $this->match('buyer', [self::work(3)])[$buyer['near']]->importantPlaceMatches,
            $this->match('tenant', [self::work(3)])[$tenant['near']]->importantPlaceMatches
        );
    }

    // ── H / I: nothing published says where a place is ───────────────────────

    public function test_the_mapped_result_and_the_rendered_card_carry_no_place_address_or_coordinate(): void
    {
        $keys   = $this->seedListings('buyer');
        $result = $this->match('buyer', [self::work(3)])[$keys['near']];

        $card = (new BuyerResultViewMapper())->mapOne($result);
        $this->assertSame([[
            'type' => 'Work', 'label' => 'Work', 'status' => 'within', 'matches' => true,
            'actual_display' => '2.1 mi', 'required_display' => 'within 3 mi',
        ]], $card['important_places']);

        $html = Blade::render('<x-stellar.buyer-result-card :card="$card" />', ['card' => $card]);
        $this->assertStringContainsString('Location match', $html);
        $this->assertStringContainsString('2.1 mi', $html);
        $this->assertStringContainsString('within 3 mi', $html);

        // The listing's own coordinate legitimately shares this meridian, so the check is on what only
        // the place has: its address and its latitude.
        foreach (['mapped result' => json_encode($card), 'rendered card' => $html] as $where => $output) {
            foreach ([self::WORK_ADDRESS, '400 S Orange', (string) self::WORK_LAT] as $needle) {
                $this->assertStringNotContainsString($needle, $output, "{$where} must not carry [{$needle}]");
            }
        }
    }

    public function test_the_detail_page_context_and_the_match_check_report_carry_rows_not_places(): void
    {
        $keys    = $this->seedListings('buyer');
        $listing = BridgeProperty::where('listing_key', $keys['near'])->firstOrFail();
        $data    = [
            'property_types' => ['Residential'], 'is_55_plus_eligible' => false,
            'preferred_cities' => ['Orlando'], 'important_places' => [self::work(3)],
        ];

        $buyerLoader = $this->createMock(BuyerCriteriaLoader::class);
        $buyerLoader->method('loadById')->willReturn($data);
        $context = (new PropertyMatchContextService(
            $buyerLoader,
            $this->createMock(TenantCriteriaLoader::class),
            $this->createMock(BuyerOfferListingCriteriaLoader::class),
            $this->createMock(TenantOfferListingCriteriaLoader::class),
            new BuyerMatchScorer(),
            new BuyerResultViewMapper(),
        ))->resolve($listing, 'buyer', 1, User::factory()->make(['id' => 1]));

        $this->assertSame('within 3 mi', $context['important_places'][0]['required_display']);

        $scored = (new BuyerMatchScorer())->score($listing, new BuyerCriteriaPayload($data));
        $report = (new MatchReportFactory())->fromDetailed(
            (new BuyerMatchResultBuilder())->buildDetailed($scored, new BuyerCriteriaPayload($data)),
            1, 'buyer', 'bridge', '2026-09-11T00:00:00Z'
        );
        $this->assertSame('2.1 mi', $report->toArray()['important_places'][0]['actual_display']);

        foreach (['detail context' => json_encode($context), 'match check report' => json_encode($report->toArray())] as $where => $json) {
            foreach ([self::WORK_ADDRESS, (string) self::WORK_LAT, '"lat"', '"lng"', 'address'] as $needle) {
                $this->assertStringNotContainsString($needle, $json, "{$where} must not carry [{$needle}]");
            }
        }
    }

    // ── Loaders: all four carry the places; J / K: storage untouched ─────────

    public function test_all_four_criteria_loaders_hand_the_matcher_the_stored_places(): void
    {
        foreach (['buyer_criteria_auctions', 'tenant_criteria_auctions', 'buyer_agent_auctions', 'tenant_agent_auctions'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }

        $owner  = User::factory()->create();
        $stored = [self::work(3)];

        $records = [
            'buyer criteria'       => [$this->buyerCriteria($owner, $stored), fn ($id) => (new BuyerCriteriaLoader())->loadById($id, [$owner->id])],
            'tenant criteria'      => [$this->tenantCriteria($owner, $stored), fn ($id) => (new TenantCriteriaLoader())->loadById($id, [$owner->id])],
            'buyer offer listing'  => [$this->buyerOfferListing($owner, $stored), fn ($id) => (new BuyerOfferListingCriteriaLoader())->loadById($id, [$owner->id])],
            'tenant offer listing' => [$this->tenantOfferListing($owner, $stored), fn ($id) => (new TenantOfferListingCriteriaLoader())->loadById($id, [$owner->id])],
        ];

        $keys = $this->seedListings('buyer');
        $listing = BridgeProperty::where('listing_key', $keys['near'])->firstOrFail();

        foreach ($records as $surface => [$record, $load]) {
            $before = $record->fresh()->info('important_places_json');

            $loaded = $load($record->id);
            $this->assertNotNull($loaded, "{$surface}: the loader must return a payload");
            $this->assertEquals($stored, $loaded['important_places'], "{$surface}: the matcher receives the stored places");
            $this->assertSame(self::WORK_ADDRESS, $loaded['important_places'][0]['address'], "{$surface}: the exact place reaches the matcher");

            $payload = new BuyerCriteriaPayload($loaded);
            $rows    = (new BuyerMatchScorer())->score($listing, $payload)->importantPlaceMatches;
            $this->assertTrue($rows[0]['matches'], "{$surface}: the place is measured");

            // J / K — matching reads the place; it never rewrites or drops it. Byte-for-byte.
            $after = $record->fresh()->info('important_places_json');
            $this->assertSame($before, $after, "{$surface}: stored place unchanged by matching");
            $this->assertEquals($stored, json_decode($after, true), "{$surface}: the stored place is still the exact one");
        }
    }

    private function buyerCriteria(User $owner, array $places): BuyerCriteriaAuction
    {
        $id = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id' => $owner->id, 'buyer_id' => $owner->id, 'title' => 'IP matching fixture', 'max_price' => 500000,
            'is_approved' => true, 'is_sold' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $record = BuyerCriteriaAuction::findOrFail($id);
        $record->saveMeta('property_types', json_encode(['Residential']));
        $record->saveMeta('important_places_json', json_encode($places));

        return $record->fresh();
    }

    private function tenantCriteria(User $owner, array $places): TenantCriteriaAuction
    {
        $id = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id' => $owner->id, 'is_approved' => true, 'is_sold' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $record = TenantCriteriaAuction::findOrFail($id);
        $record->saveMeta('property_type', 'Residential Property');
        $record->saveMeta('cities', json_encode(['Orlando']));
        $record->saveMeta('important_places_json', json_encode($places));

        return $record->fresh();
    }

    private function buyerOfferListing(User $owner, array $places): BuyerAgentAuction
    {
        $record = BuyerAgentAuction::forceCreate([
            'user_id' => $owner->id, 'title' => 'IP matching fixture', 'is_draft' => false, 'is_approved' => true, 'is_sold' => false,
        ]);
        $record->saveMeta('workflow_type', 'offer_listing');
        $record->saveMeta('property_type', 'Residential');
        $record->saveMeta('important_places_json', json_encode($places));

        return $record->fresh();
    }

    private function tenantOfferListing(User $owner, array $places): TenantAgentAuction
    {
        $id = DB::table('tenant_agent_auctions')->insertGetId([
            'user_id' => $owner->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false,
            'auction_ended' => false, 'referral_locked' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $record = TenantAgentAuction::findOrFail($id);
        $record->saveMeta('workflow_type', 'offer_listing');
        $record->saveMeta('rental_purpose', 'residential');
        $record->saveMeta('important_places_json', json_encode($places));

        return $record->fresh();
    }
}
