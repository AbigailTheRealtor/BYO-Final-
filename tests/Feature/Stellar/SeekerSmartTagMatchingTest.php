<?php

namespace Tests\Feature\Stellar;

use App\Models\BridgeProperty;
use App\Models\BuyerAgentAuction;
use App\Models\SmartTagAssignment;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Bridge\LazyImportResult;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceReader;
use App\Services\SmartTags\Seeker\SmartTagSeekerPreferenceWriter;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\BuyerResultViewMapper;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\SmartTags\Seeker\ListingSmartTagIndex;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\BuyerMatchService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use App\Support\SmartTags\SmartTagConfig;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSeekerPreferenceGate;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seeker Smart Tags in the Stellar Match DNA score.
 *
 * The picker says "Property Features You Want … Optional … we will use them when
 * we look for a match", so the picks are a PREFERENCE: scored inside the existing
 * 10-pt Amenities category, never a filter, never above 100.
 */
class SeekerSmartTagMatchingTest extends TestCase
{
    use DatabaseTransactions;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        config()->set('smart_tags_wiring.seeker_preferences_enabled', true);
        config()->set('smart_tags_wiring.seeker_matching_enabled', true);
    }

    protected function tearDown(): void
    {
        SmartTagTaxonomy::flush();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ scoring

    /** @test */
    public function one_selected_tag_that_the_listing_has_earns_the_full_amenities_category(): void
    {
        $home = $this->bridge(['quartz_countertops']);

        $with    = $this->score($home, ['quartz_countertops']);
        $without = $this->score($home, []);

        $this->assertSame(10, $with->categoryScores['amenities']);
        $this->assertSame($without->totalScore, $with->totalScore, 'no amenity expressed is already the neutral 10');
        $this->assertSame(1, $with->seekerFeatureMatch->matchedCount());
    }

    /** @test */
    public function one_selected_tag_that_the_listing_lacks_earns_nothing_and_is_explained(): void
    {
        $home = $this->bridge(['granite_countertops']);

        $result = $this->build($home, ['quartz_countertops']);

        $this->assertSame(0, $result->categoryScores['amenities']);
        $this->assertSame($this->score($home, [])->totalScore - 10, $result->totalScore);
        $this->assertContains('Does not list: Quartz Countertops', array_column($result->tradeoffs, 'label'));
    }

    /** @test */
    public function several_selected_tags_share_the_allocation(): void
    {
        $home = $this->bridge(['quartz_countertops', 'updated_kitchen', 'gas_range']);

        $result = $this->score($home, ['quartz_countertops', 'updated_kitchen', 'natural_light', 'private_pool']);

        $this->assertSame(5, $result->categoryScores['amenities'], '2 of 4 picks = half the category');
    }

    /** @test */
    public function the_picks_are_one_amenity_among_the_structured_ones(): void
    {
        $home = $this->bridge(['quartz_countertops'], ['pool_private_yn' => true, 'garage_yn' => true, 'waterfront_yn' => true, 'view_yn' => true]);

        // Pool 4 + garage 3 + waterfront 2 + view 1 all earned, picks 4 × 0 → 10 × 10/14.
        $result = $this->score($home, ['natural_light'], ['wants_pool' => true, 'wants_garage' => true, 'wants_waterfront' => true, 'wants_any_view' => true]);

        $this->assertSame((int) round(10 * 10 / 14), $result->categoryScores['amenities']);
    }

    /** @test */
    public function selecting_every_feature_can_never_exceed_the_category_or_the_total(): void
    {
        $all = array_keys(SmartTagTaxonomy::forContext(SmartTagContext::ResidentialSale, SmartTagTaxonomy::SURFACE_SEEKER));
        $this->assertGreaterThan(50, count($all));

        $everything = $this->bridge($all, ['pool_private_yn' => true, 'garage_yn' => true, 'waterfront_yn' => true, 'view_yn' => true]);
        $nothing    = $this->bridge(['gas_range']);

        foreach ([$everything, $nothing] as $home) {
            $result = $this->score($home, $all, ['wants_pool' => true, 'wants_garage' => true, 'wants_waterfront' => true, 'wants_any_view' => true]);

            $this->assertLessThanOrEqual(10, $result->categoryScores['amenities']);
            $this->assertLessThanOrEqual(100, $result->totalScore);
            $this->assertGreaterThanOrEqual(0, $result->totalScore);
        }

        $this->assertSame(10, $this->score($everything, $all)->categoryScores['amenities']);
        // One of many picks moves the category by one share, not by a point per tag.
        $oneOfAll = $this->score($this->bridge([$all[0]]), $all);
        $this->assertSame((int) round(10 / count($all)), $oneOfAll->categoryScores['amenities']);
    }

    /** @test */
    public function no_selection_is_exactly_the_pre_feature_score_and_reads_nothing(): void
    {
        $home = $this->bridge(['quartz_countertops', 'private_pool'], ['pool_private_yn' => true]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $legacy = (new BuyerMatchScorer())->score($home, $this->payload(['wants_pool' => true]));
        $empty  = $this->scoreOne($home, $this->payload(['wants_pool' => true, 'seeker_smart_tags' => []]));

        $this->assertSame([], $this->assignmentQueries(DB::getQueryLog()));
        DB::disableQueryLog();

        $this->assertSame($legacy->categoryScores, $empty->categoryScores);
        $this->assertSame($legacy->totalScore, $empty->totalScore);
        $this->assertNull($empty->seekerFeatureMatch);
    }

    /** @test */
    public function a_listing_with_no_tag_data_is_not_a_match_and_says_why(): void
    {
        $home = $this->bridge([]);

        $result = $this->build($home, ['quartz_countertops']);

        $this->assertSame(0, $result->categoryScores['amenities']);
        $this->assertFalse($result->seekerFeatureMatch->hasListingData);
        $this->assertContains(
            'Feature details not available — your selected features could not be checked for this home',
            array_column($result->missingData, 'label'),
        );
        $this->assertNotContains('Does not list: Quartz Countertops', array_column($result->tradeoffs, 'label'));
    }

    /** @test */
    public function only_present_assignments_count(): void
    {
        $home = $this->bridge(['gas_range']);
        SmartTagAssignment::create([
            'listing_type' => 'bridge', 'listing_id' => $home->id, 'tag_key' => 'private_pool',
            'context' => 'residential.sale', 'state' => 'absent', 'winning_source' => 'structured_mls',
        ]);

        $this->assertSame(0, $this->score($home, ['private_pool'])->categoryScores['amenities']);
    }

    // --------------------------------------------------------------- governance

    /** @test */
    public function natural_light_is_scored(): void
    {
        $home = $this->bridge(['natural_light']);

        $this->assertSame(10, $this->score($home, ['natural_light'])->categoryScores['amenities']);
    }

    /** @test */
    public function excluded_pending_and_inactive_tags_never_contribute(): void
    {
        $home = $this->bridge(['accessible_features', 'playground', 'guest_suite', 'quartz_countertops']);
        $baseline = $this->score($home, []);

        foreach (['accessible_features', 'playground', 'guest_suite'] as $key) {
            $result = $this->score($home, [$key]);
            $this->assertSame($baseline->categoryScores, $result->categoryScores, $key);
            $this->assertNull($result->seekerFeatureMatch, $key);
        }

        // A tag retired AFTER a seeker picked it stops contributing to new calculations.
        config()->set('smart_tags.tags.quartz_countertops.status', 'retired');
        SmartTagTaxonomy::flush();

        $retired = $this->score($home, ['quartz_countertops']);
        $this->assertSame($baseline->categoryScores, $retired->categoryScores);
        $this->assertNull($retired->seekerFeatureMatch);
    }

    /** @test */
    public function the_gate_off_means_no_picks_reach_matching(): void
    {
        [$user, $criteriaId] = $this->buyerOffer(['quartz_countertops']);

        $on = app(BuyerOfferListingCriteriaLoader::class)->loadById($criteriaId, [$user->id]);
        $this->assertSame(['quartz_countertops'], $on['seeker_smart_tags']);

        config()->set('smart_tags_wiring.seeker_preferences_enabled', false);
        $off = app(BuyerOfferListingCriteriaLoader::class)->loadById($criteriaId, [$user->id]);
        $this->assertSame([], $off['seeker_smart_tags']);
    }

    /** @test */
    public function stored_picks_are_re_projected_against_the_current_policy_at_match_time(): void
    {
        [$user, $criteriaId] = $this->buyerOffer(['quartz_countertops', 'accessible_features', 'guest_suite', 'loading_dock']);

        $criteria = app(BuyerOfferListingCriteriaLoader::class)->loadById($criteriaId, [$user->id]);

        // Non-selectable, pending and inapplicable (commercial) picks are refused.
        $this->assertSame(['quartz_countertops'], $criteria['seeker_smart_tags']);
    }

    // --------------------------------------------------------------- isolation

    /** @test */
    public function buyer_and_tenant_picks_stay_on_their_own_records(): void
    {
        [$buyer, $buyerId] = $this->buyerOffer(['quartz_countertops']);
        [$tenant, $tenantId] = $this->tenantOffer(['updated_kitchen']);

        // A row under the OTHER subject type carrying this record's numeric id must not leak.
        $this->pick('tenant_offer_listing', $buyerId, $buyer, 'private_pool', 'residential.lease');
        $this->assertSame(['quartz_countertops'], app(BuyerOfferListingCriteriaLoader::class)->loadById($buyerId, [$buyer->id])['seeker_smart_tags']);

        DB::table('smart_tag_seeker_preferences')->where('subject_type', 'tenant_offer_listing')->where('tag_key', 'private_pool')->delete();
        $this->pick('buyer_offer_listing', $tenantId, $tenant, 'private_pool', 'residential.sale');
        $this->assertSame(['updated_kitchen'], app(TenantOfferListingCriteriaLoader::class)->loadById($tenantId, [$tenant->id])['seeker_smart_tags']);
    }

    /** @test */
    public function a_tenant_can_only_score_lease_context_tags(): void
    {
        $saleOnly = array_values(array_diff(
            array_keys(SmartTagTaxonomy::forContext(SmartTagContext::ResidentialSale, SmartTagTaxonomy::SURFACE_SEEKER)),
            array_keys(SmartTagTaxonomy::forContext(SmartTagContext::ResidentialLease, SmartTagTaxonomy::SURFACE_SEEKER)),
        ));
        $this->assertNotEmpty($saleOnly);

        [$tenant, $tenantId] = $this->tenantOffer([$saleOnly[0], 'updated_kitchen']);

        $criteria = app(TenantOfferListingCriteriaLoader::class)->loadById($tenantId, [$tenant->id]);

        $this->assertSame(['updated_kitchen'], $criteria['seeker_smart_tags']);
    }

    /** @test */
    public function a_tenant_search_scores_lease_listings_by_their_own_tags(): void
    {
        $rental = $this->bridge(['updated_kitchen'], [], lease: true);
        $payload = new BuyerCriteriaPayload([
            'property_types' => ['Residential Lease'], 'is_55_plus_eligible' => false, 'seeker_smart_tags' => ['updated_kitchen'],
        ]);

        $this->assertSame(10, $this->scoreOne($rental, $payload)->categoryScores['amenities']);
    }

    /** @test */
    public function byo_assignments_never_stand_in_for_a_bridge_candidate(): void
    {
        $home = $this->bridge([]);

        foreach (['seller_agent', 'landlord_agent'] as $type) {
            SmartTagAssignment::create([
                'listing_type' => $type, 'listing_id' => $home->id, 'tag_key' => 'quartz_countertops',
                'context' => 'residential.sale', 'state' => 'present', 'winning_source' => 'manual_listing_owner',
            ]);
        }

        $result = $this->score($home, ['quartz_countertops']);

        $this->assertSame(0, $result->categoryScores['amenities']);
        $this->assertFalse($result->seekerFeatureMatch->hasListingData);
    }


    // ------------------------------------------------------------ rollout gate

    /** @test */
    public function matching_needs_the_picker_gate_and_its_own_gate(): void
    {
        foreach ([[true, true, true], [true, false, false], [false, true, false], [false, false, false]] as [$picker, $matching, $expected]) {
            config()->set('smart_tags_wiring.seeker_preferences_enabled', $picker);
            config()->set('smart_tags_wiring.seeker_matching_enabled', $matching);
            $this->assertSame($expected, SmartTagSeekerPreferenceGate::matchingEnabled(), json_encode([$picker, $matching]));
        }

        config()->set('smart_tags_wiring.seeker_preferences_enabled', true);
        foreach (['true', '1', 1, 'on', 'yes', null, '', 'off'] as $notABoolean) {
            config()->set('smart_tags_wiring.seeker_matching_enabled', $notABoolean);
            $this->assertFalse(SmartTagSeekerPreferenceGate::matchingEnabled(), var_export($notABoolean, true));
        }
    }

    /** @test */
    public function with_matching_off_the_picker_still_saves_and_nothing_is_scored(): void
    {
        config()->set('smart_tags_wiring.seeker_matching_enabled', false);
        [$user, $criteriaId] = $this->buyerOffer([]);

        // The picker's own write path, unchanged.
        app(SmartTagSeekerPreferenceWriter::class)->replaceSelections(
            BuyerAgentAuction::findOrFail($criteriaId), ['quartz_countertops', 'natural_light'], $user->id,
        );

        $this->assertSame(
            ['quartz_countertops', 'natural_light'],
            app(SmartTagSeekerPreferenceReader::class)->keysFor(BuyerAgentAuction::findOrFail($criteriaId)),
        );
        $this->assertSame([], app(BuyerOfferListingCriteriaLoader::class)->loadById($criteriaId, [$user->id])['seeker_smart_tags']);
    }

    /** @test */
    public function with_matching_off_every_result_is_identical_and_no_listing_tag_is_read(): void
    {
        $this->bridge(['quartz_countertops'], ['pool_private_yn' => true]);
        $this->bridge(['granite_countertops']);
        $this->bridge([]);

        config()->set('smart_tags_wiring.seeker_matching_enabled', false);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $picked = $this->service()->match($this->payload(['wants_pool' => true, 'seeker_smart_tags' => ['quartz_countertops', 'natural_light']]));
        $log    = DB::getQueryLog();
        DB::disableQueryLog();

        $legacy = $this->service()->match($this->payload(['wants_pool' => true]));

        $this->assertSame([], $this->assignmentQueries($log));
        $this->assertSame(
            $legacy->map(fn ($r) => [$r->toArray(), $r->seekerFeatureMatch])->all(),
            $picked->map(fn ($r) => [$r->toArray(), $r->seekerFeatureMatch])->all(),
        );
    }

    // ----------------------------------------------- present / absent / unknown

    /**
     * THE RULE: unknown earns what known-absent earns — nothing — and only the
     * explanation differs. Never more than absent, never credit like present.
     *
     * @test
     */
    public function present_absent_and_unknown_are_three_explained_states_and_two_scores(): void
    {
        $present = $this->build($this->bridge(['quartz_countertops']), ['quartz_countertops']);
        $absent  = $this->build($this->bridge(['gas_range']), ['quartz_countertops']);
        $unknown = $this->build($this->bridge([]), ['quartz_countertops']);

        $this->assertSame(10, $present->categoryScores['amenities']);
        $this->assertSame(0, $absent->categoryScores['amenities']);
        $this->assertSame(0, $unknown->categoryScores['amenities']);
        $this->assertSame($absent->totalScore, $unknown->totalScore);
        $this->assertGreaterThan($unknown->totalScore, $present->totalScore);

        $this->assertTrue($absent->seekerFeatureMatch->hasListingData);
        $this->assertFalse($unknown->seekerFeatureMatch->hasListingData);

        $this->assertContains('Does not list: Quartz Countertops', array_column($absent->tradeoffs, 'label'));
        $this->assertSame([], array_column($absent->missingData, 'label'));
        $this->assertNotContains('Does not list: Quartz Countertops', array_column($unknown->tradeoffs, 'label'));
        $this->assertContains('Feature details not available — your selected features could not be checked for this home',
            array_column($unknown->missingData, 'label'));
    }

    /** @test */
    public function the_structured_amenities_already_score_an_unreported_feature_as_nothing(): void
    {
        // The precedent the rule follows, pinned: pool_private_yn unknown earns what false earns.
        $unknown = $this->scoreOne($this->bridge([], ['pool_private_yn' => null]), $this->payload(['wants_pool' => true]));
        $absent  = $this->scoreOne($this->bridge([], ['pool_private_yn' => false]), $this->payload(['wants_pool' => true]));

        $this->assertSame(0, $unknown->categoryScores['amenities']);
        $this->assertSame($absent->categoryScores, $unknown->categoryScores);
    }

    // ----------------------------------------------------------- deduplication

    /** @test */
    public function the_structured_pool_criterion_and_the_pool_tag_are_one_preference(): void
    {
        $pool   = $this->bridge(['private_pool'], ['pool_private_yn' => true]);
        $noPool = $this->bridge(['gas_range'], ['pool_private_yn' => false]);

        foreach ([$pool, $noPool] as $home) {
            $criterionOnly = $this->score($home, [], ['wants_pool' => true]);
            $both          = $this->score($home, ['private_pool'], ['wants_pool' => true]);

            $this->assertSame($criterionOnly->categoryScores, $both->categoryScores, 'the tag adds nothing beside the criterion');
            $this->assertSame($criterionOnly->totalScore, $both->totalScore);
            $this->assertNull($both->seekerFeatureMatch, 'the tag left the pick set entirely');
        }

        // Alone, each still works.
        $this->assertSame(10, $this->score($pool, [], ['wants_pool' => true])->categoryScores['amenities']);
        $this->assertSame(10, $this->score($pool, ['private_pool'])->categoryScores['amenities']);
        $this->assertSame(0, $this->score($noPool, ['private_pool'])->categoryScores['amenities']);
    }

    /** @test */
    public function unrelated_picks_still_count_beside_a_deduplicated_one(): void
    {
        $withQuartz    = $this->bridge(['private_pool', 'quartz_countertops'], ['pool_private_yn' => true]);
        $withoutQuartz = $this->bridge(['private_pool'], ['pool_private_yn' => true]);

        // Pool 4 (criterion) + picks 4 × (quartz only) → 8 of 8, and 4 of 8.
        $a = $this->score($withQuartz, ['private_pool', 'quartz_countertops'], ['wants_pool' => true]);
        $b = $this->score($withoutQuartz, ['private_pool', 'quartz_countertops'], ['wants_pool' => true]);

        $this->assertSame(10, $a->categoryScores['amenities']);
        $this->assertSame(5, $b->categoryScores['amenities']);
        $this->assertSame(['quartz_countertops'], $b->seekerFeatureMatch->selectedKeys);
    }

    /** @test */
    public function garage_waterfront_new_construction_and_pets_are_deduplicated_too(): void
    {
        $home = $this->bridge(['garage', 'waterfront', 'new_construction'], ['garage_yn' => true, 'waterfront_yn' => true, 'new_construction_yn' => true]);

        $criteria = ['wants_garage' => true, 'wants_waterfront' => true, 'wants_new_construction' => true];
        $this->assertSame(
            $this->score($home, [], $criteria)->categoryScores,
            $this->score($home, ['garage', 'waterfront', 'new_construction'], $criteria)->categoryScores,
        );

        // A structured "No" to new construction is not the preference the tag expresses; Lifestyle
        // scores only `true`, so the tag stays.
        $this->assertSame(['new_construction'], BuyerMatchScorer::scoredSeekerTags($this->payload(['wants_new_construction' => false, 'seeker_smart_tags' => ['new_construction']])));

        $lease = new BuyerCriteriaPayload(['property_types' => ['Residential Lease'], 'is_55_plus_eligible' => false,
            'wants_pet_friendly' => true, 'seeker_smart_tags' => ['pets_allowed', 'updated_kitchen']]);
        $this->assertSame(['updated_kitchen'], BuyerMatchScorer::scoredSeekerTags($lease));
    }

    /** @test */
    public function every_equivalence_is_a_live_tag_derived_from_the_column_its_criterion_scores(): void
    {
        $columns = ['private_pool' => 'pool_private_yn', 'garage' => 'garage_yn', 'waterfront' => 'waterfront_yn', 'new_construction' => 'new_construction_yn'];
        $rules   = collect(SmartTagConfig::sources()['bridge']['rules'] ?? [])->filter(fn ($r) => isset($r['tag']))->keyBy('tag');
        $this->assertNotEmpty($rules);

        foreach (BuyerMatchScorer::STRUCTURED_TAG_EQUIVALENTS as $tag => [$property, $when]) {
            $this->assertTrue(SmartTagTaxonomy::get($tag)?->isSeekerSelectable() === true, $tag);
            $this->assertTrue(property_exists(BuyerCriteriaPayload::class, $property), $property);
            $this->assertContains($when, ['expressed', 'true']);

            if (isset($columns[$tag])) {
                $this->assertSame($columns[$tag], $rules[$tag]['column'] ?? null, "{$tag} must be derived from {$columns[$tag]}");
            }
        }

        // Pinned NON-equivalences: a narrower or different feature is its own preference.
        foreach (['water_view', 'heated_pool', 'community_pool', 'oversized_garage', 'carport', 'solar_power'] as $distinct) {
            $this->assertArrayNotHasKey($distinct, BuyerMatchScorer::STRUCTURED_TAG_EQUIVALENTS);
        }
    }

    /**
     * DELIBERATE, NOT ACCIDENTAL: `water_view` is not the structured "any view" criterion.
     * Any view is broad (a golf or city view satisfies it); a water-view pick is a narrower
     * request, so it stays in the pick set beside the criterion and tells the two homes apart.
     *
     * @test
     */
    public function water_view_is_a_narrower_preference_than_any_view_and_is_not_deduplicated(): void
    {
        $payload = $this->payload(['wants_any_view' => true, 'seeker_smart_tags' => ['water_view']]);
        $this->assertSame(['water_view'], BuyerMatchScorer::scoredSeekerTags($payload));
        $this->assertArrayNotHasKey('water_view', BuyerMatchScorer::STRUCTURED_TAG_EQUIVALENTS);

        $waterView = $this->bridge(['water_view'], ['view_yn' => true, 'water_view_yn' => true]);
        $golfView  = $this->bridge(['golf_course_view'], ['view_yn' => true, 'water_view_yn' => false]);

        // Both satisfy "any view" (1 of 1); only one satisfies the narrower pick (4 of 4).
        $this->assertSame(10, $this->scoreOne($waterView, $payload)->categoryScores['amenities']);
        $this->assertSame((int) round(10 * 1 / 5), $this->scoreOne($golfView, $payload)->categoryScores['amenities']);
    }

    // ------------------------------------------------ the P1-A facts architecture

    /**
     * scoreFacts() — and every category rule behind it — reads facts only. With picks
     * and a listing's Smart Tag facts in hand it runs no query at all, and needs no row.
     *
     * @test
     */
    public function the_scorer_core_scores_smart_tag_facts_without_any_query(): void
    {
        $home    = $this->bridge(['quartz_countertops'], ['pool_private_yn' => true]);
        $payload = $this->payload(['wants_pool' => true, 'seeker_smart_tags' => ['quartz_countertops', 'natural_light']]);

        $facts = \App\Services\Bridge\BridgeListingMatchFactsBuilder::build($home)->withSmartTags(
            ListingSmartTagIndex::forCandidates([$home], $payload)->factsFor($home)
        );

        DB::enableQueryLog();
        DB::flushQueryLog();
        $score = (new BuyerMatchScorer())->scoreFacts($facts, $payload);
        $log   = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $log, 'scoreFacts() must not touch the database');
        $this->assertSame(['quartz_countertops'], $score->seekerFeatureMatch->matchedKeys);
        // Pool 4/4 + picks 4 × ½ → 6 of 8.
        $this->assertSame((int) round(10 * 6 / 8), $score->categoryScores['amenities']);
    }

    /**
     * The Bridge entry point adapts only: without Smart Tag facts handed to it, it reads none,
     * and the picks are unknown — no credit, worded as "could not be checked", never a match.
     *
     * @test
     */
    public function score_without_smart_tag_facts_reads_nothing_and_treats_the_picks_as_unknown(): void
    {
        $home    = $this->bridge(['quartz_countertops']);
        $payload = $this->payload(['seeker_smart_tags' => ['quartz_countertops']]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $bare = (new BuyerMatchScorer())->score($home, $payload);
        $log  = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $log);
        $this->assertFalse($bare->seekerFeatureMatch->hasListingData);
        $this->assertSame(0, $bare->categoryScores['amenities']);
        $this->assertSame(10, $this->scoreOne($home, $payload)->categoryScores['amenities']);
    }

    /**
     * Every production caller of the Bridge entry point hands it the listing's Smart Tag facts,
     * read through the one index, so the results page, the property-detail match context and
     * both Match Check steps score from the same facts.
     *
     * @test
     */
    public function every_production_score_call_supplies_the_listings_smart_tag_facts(): void
    {
        $root  = dirname(__DIR__, 3);
        $calls = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (! str_contains($code, 'BuyerMatchScorer')) {
                continue;
            }
            // A call on a BuyerMatchScorer instance whose first argument is a listing row.
            preg_match_all('/(?:scorer|Scorer\(\)\)|buyerScorer|\$this)->score\(\s*\$listing\b[^;]*;/', $code, $m);
            foreach ($m[0] as $call) {
                $calls[substr($file->getPathname(), strlen($root) + 1)][] = $call;
            }
        }

        $files = array_keys($calls);
        sort($files);

        $this->assertSame([
            'app/Services/Stellar/MatchCheck/MatchCheckOrchestrator.php',
            'app/Services/Stellar/MatchCheck/MatchCheckScorer.php',
            'app/Services/Stellar/Matching/BuyerMatchScorer.php',
            'app/Services/Stellar/PropertyMatchContextService.php',
        ], $files, 'a new caller of BuyerMatchScorer::score() must be reviewed here');

        foreach ($calls as $file => $found) {
            foreach ($found as $call) {
                $this->assertMatchesRegularExpression(
                    '/ListingSmartTagIndex::forCandidates\(\[\$listing\], \$\w+\)->factsFor\(\$listing\)|\$tags->factsFor\(\$listing\)/',
                    $call,
                    "{$file} scores a listing without its Smart Tag facts: {$call}"
                );
            }
        }
    }

    // ---------------------------------------------------------------- pipeline

    /** @test */
    public function membership_is_unchanged_and_the_scores_only_move_within_amenities(): void
    {
        $a = $this->bridge(['quartz_countertops']);
        $b = $this->bridge(['granite_countertops']);
        $c = $this->bridge([]);

        $without = $this->service()->match($this->payload());
        $with    = $this->service()->match($this->payload(['seeker_smart_tags' => ['quartz_countertops']]));

        $keys = static fn ($results): array => $results->pluck('listingKey')->sort()->values()->all();
        $this->assertSame($keys($without), $keys($with));

        $byKey = $with->keyBy('listingKey');
        $this->assertSame(10, $byKey[$a->listing_key]->categoryScores['amenities']);
        $this->assertSame(0, $byKey[$b->listing_key]->categoryScores['amenities']);
        $this->assertSame(0, $byKey[$c->listing_key]->categoryScores['amenities']);
        $this->assertSame($a->listing_key, $with->first()->listingKey);
    }

    /** @test */
    public function the_batch_path_and_the_single_listing_path_agree(): void
    {
        $home    = $this->bridge(['quartz_countertops', 'natural_light']);
        $payload = $this->payload(['seeker_smart_tags' => ['quartz_countertops', 'private_pool', 'natural_light']]);

        $batch  = (new BuyerMatchScorer())->scoreAll([$home], $payload)[0];
        $single = $this->scoreOne($home, $payload);

        $this->assertSame($batch->categoryScores, $single->categoryScores);
        $this->assertSame($batch->totalScore, $single->totalScore);
    }

    /** @test */
    public function equal_feature_scores_keep_the_matchers_order(): void
    {
        $homes = [];
        for ($i = 0; $i < 6; $i++) {
            $homes[] = $this->bridge(['quartz_countertops']);
        }

        $payload = $this->payload(['seeker_smart_tags' => ['quartz_countertops']]);
        $first   = $this->service()->match($payload)->pluck('listingKey')->all();
        $second  = $this->service()->match($payload)->pluck('listingKey')->all();

        $this->assertSame($first, $second);
        $this->assertSame(array_map(static fn ($h) => $h->listing_key, $homes), $first);
    }

    /** @test */
    public function tag_reads_do_not_grow_with_the_number_of_candidates(): void
    {
        $count = function (int $candidates): int {
            DB::table('bridge_properties')->delete();
            for ($i = 0; $i < $candidates; $i++) {
                $this->bridge($i % 2 === 0 ? ['quartz_countertops'] : ['gas_range']);
            }

            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->service()->match($this->payload(['seeker_smart_tags' => ['quartz_countertops', 'natural_light']]));
            $log = DB::getQueryLog();
            DB::disableQueryLog();

            return count($this->assignmentQueries($log)) * 1000 + count($log);
        };

        $small = $count(3);
        $large = $count(60);

        $this->assertSame(2, intdiv($small, 1000), 'two assignment reads for the whole candidate set');
        $this->assertSame($small, $large, 'no query grows with the candidates');
    }

    // ------------------------------------------------------------- presentation

    /** @test */
    public function the_card_names_features_in_words_and_exposes_no_key_weight_or_ai(): void
    {
        $home = $this->bridge(['quartz_countertops', 'natural_light']);

        $result = $this->build($home, ['quartz_countertops', 'natural_light', 'private_pool']);
        $card   = app(BuyerResultViewMapper::class)->mapOne($result);
        $json   = json_encode($card);

        $why = array_column($card['why_this_matches'], 'label');
        // Named in the seeker's own selection order.
        $this->assertContains('Has 2 of your 3 selected features: Quartz Countertops and ' . SmartTagTaxonomy::get('natural_light')->label, $why);
        $this->assertContains('Does not list: Private Pool', array_column($card['tradeoffs'], 'label'));

        foreach (['quartz_countertops', 'natural_light', 'private_pool', 'seeker_features', 'selected_features', 'smart_tag', 'Smart Tag'] as $internal) {
            $this->assertStringNotContainsString($internal, $json, $internal);
        }
        $this->assertDoesNotMatchRegularExpression('/\bAI\b|artificial intelligence/i', $json);
    }

    // ------------------------------------------------------------------ helpers

    /** @param list<string> $tags */
    private function bridge(array $tags, array $columns = [], bool $lease = false): BridgeProperty
    {
        $this->n++;

        $row = BridgeProperty::create(array_merge([
            'provider'                => 'stellar_bridge',
            'listing_key'             => sprintf('STM-%03d-%s', $this->n, uniqid()),
            'listing_id'              => "STM-{$this->n}-" . uniqid(),
            'standard_status'         => 'Active',
            'property_type'           => $lease ? 'Residential Lease' : 'Residential',
            'list_price'              => $lease ? 2000 : 400000,
            'city'                    => 'Orlando',
            'state_or_province'       => 'FL',
            'postal_code'             => '32801',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'senior_community_yn'     => false,
            'raw_json'                => json_encode(['IDXParticipationYN' => true, 'LeaseAmountFrequency' => 'Monthly']),
        ], $columns));

        foreach ($tags as $tag) {
            SmartTagAssignment::create([
                'listing_type' => 'bridge', 'listing_id' => $row->id, 'tag_key' => $tag,
                'context' => $lease ? 'residential.lease' : 'residential.sale', 'state' => 'present', 'winning_source' => 'structured_mls',
            ]);
        }

        return $row;
    }

    private function payload(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
            'preferred_cities'    => ['Orlando'],
        ], $overrides));
    }

    private function score(BridgeProperty $home, array $tags, array $criteria = [])
    {
        return $this->scoreOne($home, $this->payload($criteria + ['seeker_smart_tags' => $tags]));
    }

    /**
     * The single-listing path exactly as the production surfaces take it: the listing's Smart
     * Tag facts read beside the row, then the pure scorer.
     */
    private function scoreOne(BridgeProperty $home, BuyerCriteriaPayload $payload)
    {
        return (new BuyerMatchScorer())->score($home, $payload, ListingSmartTagIndex::forCandidates([$home], $payload)->factsFor($home));
    }

    private function build(BridgeProperty $home, array $tags)
    {
        $payload = $this->payload(['seeker_smart_tags' => $tags]);

        return (new BuyerMatchResultBuilder())->build($this->scoreOne($home, $payload), $payload);
    }

    private function service(): BuyerMatchService
    {
        $lazy = $this->createMock(LazyBridgeImportService::class);
        $lazy->method('importForCriteria')->willReturn(LazyImportResult::cached(0));

        return new BuyerMatchService(new BuyerMatchQueryBuilder(), new BuyerMatchScorer(), new BuyerMatchResultBuilder(), $lazy);
    }

    /** @return list<string> */
    private function assignmentQueries(array $log): array
    {
        return array_values(array_filter(
            array_column($log, 'query'),
            static fn (string $sql): bool => str_contains($sql, 'smart_tag_assignments'),
        ));
    }

    /** @return array{0: User, 1: int} */
    private function buyerOffer(array $picks): array
    {
        $user = User::factory()->create(['user_type' => 'buyer']);

        $id = DB::table('buyer_agent_auctions')->insertGetId([
            'user_id' => $user->id, 'title' => 'Seeker tags', 'is_approved' => 'true', 'is_sold' => 'false',
            'is_paid' => '0', 'is_draft' => false, 'referral_locked' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $auction = BuyerAgentAuction::findOrFail($id);
        // Exactly what the Buyer wizard's select stores.
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'Residential', 'preferred_cities' => json_encode(['Orlando']),
            'maximum_budget' => '600000'] as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        foreach ($picks as $tag) {
            $this->pick('buyer_offer_listing', $id, $user, $tag, 'residential.sale');
        }

        return [$user, $id];
    }

    /** @return array{0: User, 1: int} */
    private function tenantOffer(array $picks): array
    {
        $user = User::factory()->create(['user_type' => 'tenant']);

        $id = DB::table('tenant_agent_auctions')->insertGetId([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false,
            'auction_ended' => false, 'referral_locked' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $auction = TenantAgentAuction::findOrFail($id);
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'Residential Property', 'cities' => json_encode(['Orlando']),
            'maximum_budget' => '3000'] as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        foreach ($picks as $tag) {
            $this->pick('tenant_offer_listing', $id, $user, $tag, 'residential.lease');
        }

        return [$user, $id];
    }

    private function pick(string $subjectType, int $subjectId, User $user, string $tag, string $context): void
    {
        DB::table('smart_tag_seeker_preferences')->insert([
            'subject_type' => $subjectType, 'subject_id' => $subjectId, 'user_id' => $user->id,
            'seeker_role' => str_starts_with($subjectType, 'tenant') ? 'tenant' : 'buyer', 'tag_key' => $tag,
            'context' => $context, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
