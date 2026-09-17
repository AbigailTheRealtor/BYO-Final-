<?php

namespace Tests\Feature\Stellar;

use App\Models\BridgeProperty;
use App\Models\BuyerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\CriteriaListingResolver;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use App\Support\Listing\ListingFlag;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The five P0 match-correctness defects, pinned.
 *
 * Each test states the WRONG behaviour it exists to prevent, so a future change
 * that reintroduces one fails here with an explanation rather than a diff.
 *
 * P0-1  Property condition must never be compared to RESO PropertySubType.
 * P0-2  A rent must never be compared to a monthly budget without its period.
 * P0-3  Legacy Buyer Criteria must not be offered as matchable.
 * P0-4  Legacy Tenant Criteria must not be offered as matchable.
 * P0-5  An association fee must never be read as monthly without its frequency.
 */
class MatchCorrectnessP0Test extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function skipUnless(string ...$tables): void
    {
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }
    }

    private function makeUser(string $type = 'buyer'): int
    {
        return DB::table('users')->insertGetId([
            'first_name'  => 'P0',
            'last_name'   => 'Test',
            'name'        => 'P0 Test',
            'short_id'    => 'P0' . uniqid(),
            'user_name'   => 'p0_' . uniqid(),
            'email'       => 'p0-' . uniqid() . '@example.com',
            'password'    => bcrypt('password'),
            'user_type'   => $type,
            'is_approved' => true,
            'is_super'    => false,
            'is_deleted'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function makeBuyerOfferListing(int $userId, array $meta = []): BuyerAgentAuction
    {
        $id = DB::table('buyer_agent_auctions')->insertGetId([
            'user_id'         => $userId,
            'title'           => 'P0 buyer offer listing',
            'is_approved'     => 'true',
            'is_sold'         => 'false',
            'is_paid'         => '0',
            'is_draft'        => false,
            'referral_locked' => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $auction = BuyerAgentAuction::findOrFail($id);
        $auction->saveMeta('workflow_type', 'offer_listing');

        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return $auction->fresh();
    }

    private function makeTenantOfferListing(int $userId, array $meta = []): TenantAgentAuction
    {
        $id = DB::table('tenant_agent_auctions')->insertGetId([
            'user_id'         => $userId,
            'is_approved'     => true,
            'is_draft'        => false,
            'is_sold'         => false,
            'auction_ended'   => false,
            'referral_locked' => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $auction = TenantAgentAuction::findOrFail($id);
        $auction->saveMeta('workflow_type', 'offer_listing');

        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return $auction->fresh();
    }

    private function bridgeListing(array $overrides = []): BridgeProperty
    {
        $key = 'P0-' . uniqid();
        $raw = $overrides['__raw'] ?? ['IDXParticipationYN' => true];
        unset($overrides['__raw']);

        DB::table('bridge_properties')->insert(array_merge([
            'listing_key'       => $key,
            'listing_id'        => 'P0-LID-' . uniqid(),
            'standard_status'   => 'Active',
            'property_type'     => 'Residential',
            'list_price'        => 400000,
            'city'              => 'Tampa',
            'state_or_province' => 'FL',
            'postal_code'       => '33614',
            'raw_json'          => json_encode($raw),
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $overrides));

        return BridgeProperty::where('listing_key', $key)->firstOrFail();
    }

    /** A minimal valid payload; overrides win. */
    private function payload(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ], $overrides));
    }

    // =========================================================================
    // P0-1 — property condition is not a property sub-type
    // =========================================================================

    /** @test */
    public function buyer_condition_preference_does_not_become_a_property_sub_type(): void
    {
        $this->skipUnless('buyer_agent_auctions', 'buyer_agent_auction_metas');

        $userId  = $this->makeUser('buyer');
        $listing = $this->makeBuyerOfferListing($userId, [
            'property_type'        => 'residential',
            'condition_prop_buyer' => json_encode(['Updated/Renovated', 'Partially Updated']),
        ]);

        $data = (new BuyerOfferListingCriteriaLoader())->loadById($listing->id, [$userId]);

        $this->assertNotNull($data, 'The buyer offer listing should load.');

        // The defect: these condition words were sent as property_sub_types and
        // compared against RESO PropertySubType, which they can never equal.
        $this->assertSame(
            [],
            $data['property_sub_types'],
            'Property condition must not be loaded as a property sub-type preference.'
        );

        // The preference is preserved under its own concept, not discarded.
        $this->assertSame(
            ['Updated/Renovated', 'Partially Updated'],
            $data['property_conditions'],
            'The condition preference must still be carried, under its own key.'
        );
    }

    /** @test */
    public function tenant_condition_preference_does_not_become_a_property_sub_type(): void
    {
        $this->skipUnless('tenant_agent_auctions', 'tenant_agent_auction_metas');

        $userId  = $this->makeUser('tenant');
        $listing = $this->makeTenantOfferListing($userId, [
            'property_type'        => 'Residential Property',
            'condition_prop_buyer' => json_encode(['Older but Clean']),
        ]);

        $data = (new TenantOfferListingCriteriaLoader())->loadById($listing->id, [$userId]);

        $this->assertNotNull($data);
        $this->assertSame([], $data['property_sub_types']);
        $this->assertSame(['Older but Clean'], $data['property_conditions']);
    }

    /** @test */
    public function stating_a_condition_preference_no_longer_costs_property_type_points(): void
    {
        $this->skipUnless('bridge_properties');

        $listing = $this->bridgeListing(['property_sub_type' => 'Single Family Residence']);
        $scorer  = new BuyerMatchScorer();

        $noPreference   = $scorer->score($listing, $this->payload());
        $withCondition  = $scorer->score($listing, $this->payload([
            'property_conditions' => ['Updated/Renovated'],
        ]));

        // Before the fix, expressing a condition preference filled property_sub_types
        // with words that could never match, dropping the sub-type score from the
        // neutral 2 to 0 — answering the question made every listing score worse.
        $this->assertSame(
            $noPreference->categoryScores['property_type'],
            $withCondition->categoryScores['property_type'],
            'A condition preference must not change the property-type score.'
        );
    }

    /** @test */
    public function property_sub_type_remains_an_independent_matchable_concept(): void
    {
        $this->skipUnless('bridge_properties');

        $listing = $this->bridgeListing(['property_sub_type' => 'Condominium']);
        $scorer  = new BuyerMatchScorer();

        $matching = $scorer->score($listing, $this->payload([
            'property_sub_types' => ['Condominium'],
        ]));
        $missing = $scorer->score($listing, $this->payload([
            'property_sub_types' => ['Townhouse'],
        ]));

        $this->assertGreaterThan(
            $missing->categoryScores['property_type'],
            $matching->categoryScores['property_type'],
            'PropertySubType must still score independently when a real sub-type is supplied.'
        );
    }

    // =========================================================================
    // P0-2 — rent is frequency-aware
    // =========================================================================

    /** A lease payload with a $3,000/month ceiling. */
    private function tenantPayload(): BuyerCriteriaPayload
    {
        return $this->payload([
            'property_types' => ['Residential Lease'],
            'max_price'      => 3000,
        ]);
    }

    private function leaseListing(float $listPrice, ?string $frequency): BridgeProperty
    {
        $raw = ['IDXParticipationYN' => true];
        if ($frequency !== null) {
            $raw['LeaseAmountFrequency'] = $frequency;
        }

        return $this->bridgeListing([
            'property_type' => 'Residential Lease',
            'list_price'    => $listPrice,
            '__raw'         => $raw,
        ]);
    }

    /** @test */
    public function a_monthly_rent_within_budget_still_scores(): void
    {
        $this->skipUnless('bridge_properties');

        $result = (new BuyerMatchScorer())->score(
            $this->leaseListing(2500, 'Monthly'),
            $this->tenantPayload()
        );

        $this->assertGreaterThan(0, $result->categoryScores['price']);
    }

    /**
     * The headline defect: a raw ListPrice under the budget that is NOT a monthly
     * figure must not be treated as affordable.
     *
     * @test
     * @dataProvider overBudgetOnceConvertedProvider
     */
    public function a_non_monthly_rent_is_not_accepted_just_because_the_raw_number_is_low(
        float $listPrice,
        string $frequency,
        float $actualMonthly
    ): void {
        $this->skipUnless('bridge_properties');

        $result = (new BuyerMatchScorer())->score(
            $this->leaseListing($listPrice, $frequency),
            $this->tenantPayload()
        );

        $this->assertSame(
            0,
            $result->categoryScores['price'],
            sprintf(
                '$%s %s is about $%s/month against a $3,000 budget — it must not earn price points.',
                number_format($listPrice),
                $frequency,
                number_format($actualMonthly)
            )
        );
    }

    public function overBudgetOnceConvertedProvider(): array
    {
        return [
            // Each raw ListPrice is BELOW the $3,000 budget, and each is over budget
            // once its stated period is applied. A raw comparison accepts all three.
            'weekly' => [2500.0, 'Weekly', 10833.0],
            'daily'  => [500.0,  'Daily',  15208.0],
        ];
    }

    /** @test */
    public function an_annual_rent_is_converted_down_rather_than_compared_raw(): void
    {
        $this->skipUnless('bridge_properties');

        // $36,000/year is exactly $3,000/month — within a $3,000 budget. The raw
        // number is twelve times the budget, so a raw comparison would reject it.
        $result = (new BuyerMatchScorer())->score(
            $this->leaseListing(36000, 'Annually'),
            $this->tenantPayload()
        );

        $this->assertGreaterThan(
            0,
            $result->categoryScores['price'],
            'An annual rent within budget once converted must not be rejected on its raw figure.'
        );
    }

    /**
     * @test
     * @dataProvider unknownLeasePeriodProvider
     */
    public function a_rent_with_no_usable_period_earns_no_price_credit(?string $frequency): void
    {
        $this->skipUnless('bridge_properties');

        $result = (new BuyerMatchScorer())->score(
            $this->leaseListing(2500, $frequency),
            $this->tenantPayload()
        );

        $this->assertSame(
            0,
            $result->categoryScores['price'],
            'An unverifiable rent period must earn no credit — it must not be assumed monthly.'
        );
    }

    public function unknownLeasePeriodProvider(): array
    {
        return [
            'seasonal'          => ['Seasonal'],
            'missing'           => [null],
            'unrecognised'      => ['Per Fortnight'],
            'term not a period' => ['12 Months'],
        ];
    }

    /** @test */
    public function a_tenant_who_named_no_budget_is_not_penalised_for_a_missing_rent_period(): void
    {
        $this->skipUnless('bridge_properties');

        // No max_price: there is no figure the listing could be misjudged against,
        // so the dimension keeps its neutral "no preference" answer.
        $noBudget = $this->payload(['property_types' => ['Residential Lease']]);

        $result = (new BuyerMatchScorer())->score($this->leaseListing(2500, null), $noBudget);

        $this->assertGreaterThan(
            0,
            $result->categoryScores['price'],
            'A seeker with no stated budget must not lose price points to a missing period.'
        );
    }

    /** @test */
    public function an_unknown_rent_period_is_reported_to_the_seeker(): void
    {
        $this->skipUnless('bridge_properties');

        $criteria = $this->tenantPayload();
        $scored   = (new BuyerMatchScorer())->score($this->leaseListing(2500, 'Seasonal'), $criteria);
        $built    = (new BuyerMatchResultBuilder())->build($scored, $criteria);

        $fields = array_column($built->missingData, 'field');

        $this->assertContains(
            'LeaseAmountFrequency',
            $fields,
            'A seeker must be told the advertised rent may not be a monthly figure.'
        );
    }

    /** @test */
    public function a_sale_search_price_ceiling_is_unchanged(): void
    {
        $this->skipUnless('bridge_properties');

        // Regression guard: the lease conversion must not touch buyer/sale scoring.
        $listing = $this->bridgeListing(['list_price' => 400000]);
        $result  = (new BuyerMatchScorer())->score($listing, $this->payload(['max_price' => 500000]));

        $this->assertGreaterThan(0, $result->categoryScores['price']);
    }

    /** @test */
    public function the_sql_pre_filter_keeps_an_affordable_annual_rental_in_the_candidate_set(): void
    {
        $this->skipUnless('bridge_properties');

        $key = $this->leaseListing(36000, 'Annually')->listing_key;

        $candidates = (new BuyerMatchQueryBuilder())
            ->build($this->tenantPayload())
            ->get()
            ->pluck('listing_key')
            ->all();

        $this->assertContains(
            $key,
            $candidates,
            'A $36,000/yr ($3,000/mo) rental must survive the coarse SQL ceiling on a $3,000 budget.'
        );
    }

    /** @test */
    public function the_sql_ceiling_is_not_widened_for_a_sale_search(): void
    {
        $this->skipUnless('bridge_properties');

        $key = $this->bridgeListing(['list_price' => 900000])->listing_key;

        $candidates = (new BuyerMatchQueryBuilder())
            ->build($this->payload(['max_price' => 500000]))
            ->get()
            ->pluck('listing_key')
            ->all();

        $this->assertNotContains(
            $key,
            $candidates,
            'A sale listing far above budget must still be excluded by SQL.'
        );
    }

    /** @test */
    public function is_lease_search_classifies_the_property_types_it_is_given(): void
    {
        $this->assertTrue($this->payload(['property_types' => ['Residential Lease']])->isLeaseSearch());
        $this->assertTrue($this->payload(['property_types' => ['Commercial Lease']])->isLeaseSearch());

        $this->assertFalse($this->payload(['property_types' => ['Residential']])->isLeaseSearch());
        $this->assertFalse($this->payload(['property_types' => ['Commercial Sale']])->isLeaseSearch());

        // Unclassified or mixed must not be treated as a lease — that would apply a
        // rent conversion to what may be a purchase price.
        $this->assertFalse($this->payload(['property_types' => ['Nonsense Type']])->isLeaseSearch());
        $this->assertFalse(
            $this->payload(['property_types' => ['Residential Lease', 'Residential']])->isLeaseSearch()
        );
    }

    // =========================================================================
    // P0-5 — HOA fee is frequency-aware
    // =========================================================================

    private function hoaListing(?float $fee, ?string $frequency): BridgeProperty
    {
        $raw = ['IDXParticipationYN' => true];
        if ($frequency !== null) {
            $raw['AssociationFeeFrequency'] = $frequency;
        }

        return $this->bridgeListing([
            'association_fee'   => $fee,
            'association_yn'    => true,
            'tax_annual_amount' => 0,
            '__raw'             => $raw,
        ]);
    }

    /** A payload with a $300/month total-burden ceiling. */
    private function burdenPayload(): BuyerCriteriaPayload
    {
        return $this->payload(['max_monthly_total_burden' => 300]);
    }

    /**
     * @test
     * @dataProvider hoaFrequencyProvider
     */
    public function an_association_fee_is_compared_at_its_true_monthly_value(
        float $fee,
        string $frequency,
        bool $shouldBeWithinCeiling
    ): void {
        $this->skipUnless('bridge_properties');

        $result = (new BuyerMatchScorer())->score(
            $this->hoaListing($fee, $frequency),
            $this->burdenPayload()
        );

        if ($shouldBeWithinCeiling) {
            $this->assertGreaterThan(
                0,
                $result->categoryScores['financial'],
                sprintf('$%s %s is within a $300/month ceiling once converted.', number_format($fee), $frequency)
            );
        } else {
            $this->assertSame(
                0,
                $result->categoryScores['financial'],
                sprintf('$%s %s exceeds a $300/month ceiling once converted.', number_format($fee), $frequency)
            );
        }
    }

    public function hoaFrequencyProvider(): array
    {
        return [
            // The defect: $2,400 billed annually is $200/month, not $2,400/month.
            'annual within ceiling'     => [2400.0, 'Annually', true],
            'quarterly within ceiling'  => [600.0,  'Quarterly', true],
            'semi-annual within'        => [1500.0, 'Semi-Annually', true],
            'monthly within ceiling'    => [150.0,  'Monthly', true],
            'monthly over ceiling'      => [900.0,  'Monthly', false],
            'annual over ceiling'       => [9000.0, 'Annually', false],
        ];
    }

    /**
     * @test
     * @dataProvider unknownHoaFrequencyProvider
     */
    public function an_association_fee_with_no_usable_frequency_is_not_assumed_monthly(?string $frequency): void
    {
        $this->skipUnless('bridge_properties');

        $scorer = new BuyerMatchScorer();

        // A fee that WOULD breach the ceiling if read as monthly.
        $unknown = $scorer->score($this->hoaListing(900.0, $frequency), $this->burdenPayload());
        $monthly = $scorer->score($this->hoaListing(900.0, 'Monthly'), $this->burdenPayload());

        $this->assertSame(
            0,
            $monthly->categoryScores['financial'],
            'Sanity: $900/month does breach a $300/month ceiling.'
        );

        $this->assertNotSame(
            $monthly->categoryScores['financial'],
            $unknown->categoryScores['financial'],
            'An unknown billing period must not be scored as though it were monthly.'
        );
    }

    public function unknownHoaFrequencyProvider(): array
    {
        return [
            'missing'      => [null],
            'bi-monthly'   => ['Bi-Monthly'],
            'one-time'     => ['One-Time'],
            'unrecognised' => ['Other'],
        ];
    }

    /** @test */
    public function an_unknown_hoa_billing_period_is_reported_to_the_buyer(): void
    {
        $this->skipUnless('bridge_properties');

        $criteria = $this->burdenPayload();
        $scored   = (new BuyerMatchScorer())->score($this->hoaListing(900.0, null), $criteria);
        $built    = (new BuyerMatchResultBuilder())->build($scored, $criteria);

        $this->assertContains(
            'AssociationFeeFrequency',
            array_column($built->missingData, 'field'),
            'A buyer must be told the fee could not be compared to their monthly ceiling.'
        );
    }

    /** @test */
    public function a_listing_with_no_association_fee_is_unaffected(): void
    {
        $this->skipUnless('bridge_properties');

        // No fee at all: the burden is tax-only and must still score, exactly as before.
        $result = (new BuyerMatchScorer())->score(
            $this->hoaListing(null, null),
            $this->burdenPayload()
        );

        $this->assertGreaterThan(0, $result->categoryScores['financial']);
    }

    // =========================================================================
    // P0-3 / P0-4 — legacy criteria are not offered as matchable
    // =========================================================================

    /** @test */
    public function legacy_criteria_types_are_declared_retired(): void
    {
        $this->assertTrue(CriteriaListingResolver::isRetiredLegacyType('buyer'));
        $this->assertTrue(CriteriaListingResolver::isRetiredLegacyType('tenant'));

        $this->assertFalse(CriteriaListingResolver::isRetiredLegacyType('buyer_offer'));
        $this->assertFalse(CriteriaListingResolver::isRetiredLegacyType('tenant_offer'));
    }

    /** @test */
    public function a_legacy_buyer_criteria_record_is_never_offered_for_selection(): void
    {
        $this->skipUnless('buyer_criteria_auctions', 'buyer_agent_auctions');

        $userId = $this->makeUser('buyer');

        DB::table('buyer_criteria_auctions')->insert([
            'user_id'     => $userId,
            'buyer_id'    => $userId,
            'title'       => 'Legacy buyer criteria',
            'max_price'   => 400000,
            'is_approved' => true,
            'is_sold'     => false,
            'is_paid'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $user  = \App\Models\User::findOrFail($userId);
        $items = (new CriteriaListingResolver())->resolveAccessible($user);

        $this->assertNotContains(
            'buyer',
            array_column($items, 'type'),
            'A legacy buyer criteria record cannot produce a match and must not be selectable.'
        );
    }

    /** @test */
    public function a_legacy_tenant_criteria_record_is_never_offered_for_selection(): void
    {
        $this->skipUnless('tenant_criteria_auctions');

        $userId = $this->makeUser('tenant');

        DB::table('tenant_criteria_auctions')->insert([
            'user_id'     => $userId,
            'is_approved' => true,
            'is_sold'     => false,
            'is_paid'     => false,
            'is_draft'    => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $user  = \App\Models\User::findOrFail($userId);
        $items = (new CriteriaListingResolver())->resolveAccessible($user);

        $this->assertNotContains(
            'tenant',
            array_column($items, 'type'),
            'Legacy tenant criteria resolve to for-sale inventory with no budget and must not be selectable.'
        );
    }

    /**
     * Every reader of a criteria type must refuse a retired one, not just the
     * results page. The property-detail match context is the third reader, and it
     * receives the type straight from a query string.
     *
     * @test
     * @dataProvider retiredOrAbsentCriteriaTypeProvider
     */
    public function the_property_detail_match_context_refuses_a_retired_or_unknown_type(string $type): void
    {
        $this->skipUnless('bridge_properties', 'buyer_criteria_auctions');

        $listing = $this->bridgeListing();

        $userId = $this->makeUser('buyer');
        $legacyId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $userId,
            'buyer_id'    => $userId,
            'title'       => 'Legacy buyer criteria',
            'max_price'   => 400000,
            'is_approved' => true,
            'is_sold'     => false,
            'is_paid'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $user = \App\Models\User::findOrFail($userId);

        $context = app(\App\Services\Stellar\PropertyMatchContextService::class)
            ->resolve($listing, $type, $legacyId, $user);

        $this->assertNull(
            $context,
            "A '{$type}' criteria type must not resolve a match context — it cannot produce a correct score."
        );
    }

    public function retiredOrAbsentCriteriaTypeProvider(): array
    {
        return [
            // 'buyer' is both the retired legacy type AND the controller's default
            // when the query string omits criteria_type.
            'retired buyer'  => ['buyer'],
            'retired tenant' => ['tenant'],
            'unknown'        => ['something_else'],
        ];
    }

    /** @test */
    public function an_offer_listing_record_is_still_offered_for_selection(): void
    {
        $this->skipUnless('buyer_agent_auctions', 'buyer_agent_auction_metas');

        $userId = $this->makeUser('buyer');
        $this->makeBuyerOfferListing($userId, ['property_type' => 'residential']);

        $user  = \App\Models\User::findOrFail($userId);
        $items = (new CriteriaListingResolver())->resolveAccessible($user);

        $this->assertContains(
            'buyer_offer',
            array_column($items, 'type'),
            'Retiring the legacy types must not remove the modern ones.'
        );
    }

    /**
     * The user-facing consequence of the retirement: a seeker holding ONLY legacy
     * records is shown the ordinary "create your criteria" empty state, pointed at
     * the modern Offer Listing flows — not an error, and not a results page built
     * from a profile that cannot match.
     *
     * @test
     */
    public function a_user_with_only_legacy_criteria_is_prompted_to_create_a_modern_profile(): void
    {
        $this->skipUnless('bridge_properties', 'buyer_criteria_auctions');

        // Inventory must exist or the page short-circuits before criteria are read.
        $this->bridgeListing();

        $userId = $this->makeUser('buyer');
        DB::table('buyer_criteria_auctions')->insert([
            'user_id'     => $userId,
            'buyer_id'    => $userId,
            'title'       => 'Legacy buyer criteria',
            'max_price'   => 400000,
            'is_approved' => true,
            'is_sold'     => false,
            'is_paid'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $user     = \App\Models\User::findOrFail($userId);
        $response = $this->actingAs($user)->get('/stellar/buyer/results');

        $response->assertOk();
        $response->assertViewHas('results', null);
        $response->assertViewHas('criteriaList', []);
        // The prompt points at the modern flow, which is the only one that can match.
        $response->assertViewHas('buyerCriteriaAddUrl', url('/offer-listing/buyer'));
    }

    /** @test */
    public function the_results_page_refuses_a_hand_typed_legacy_criteria_type(): void
    {
        $this->skipUnless('bridge_properties', 'buyer_criteria_auctions');

        // Inventory must exist, or the page short-circuits on an empty-inventory state
        // before criteria are considered and the assertion would prove nothing.
        $this->bridgeListing();

        $userId = $this->makeUser('buyer');
        $legacyId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $userId,
            'buyer_id'    => $userId,
            'title'       => 'Legacy buyer criteria',
            'max_price'   => 400000,
            'is_approved' => true,
            'is_sold'     => false,
            'is_paid'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $user = \App\Models\User::findOrFail($userId);

        $response = $this->actingAs($user)->get(
            '/stellar/buyer/results?criteria_type=buyer&criteria_id=' . $legacyId
        );

        $response->assertOk();
        $response->assertViewHas('results', null);
        $response->assertViewHas('emptyState');
    }
}
