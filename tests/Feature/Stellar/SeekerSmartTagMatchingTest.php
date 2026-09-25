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
        // These tests exercise scoring, not per-context activation: activate every
        // context. BridgeSmartTagCoverageBackfillTest pins the activation list itself.
        config()->set('smart_tags_wiring.seeker_matching_contexts', array_map(
            static fn (\App\Support\SmartTags\SmartTagContext $c) => $c->value,
            \App\Support\SmartTags\SmartTagContext::cases(),
        ));
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
    public function one_selected_tag_the_listing_was_checked_for_and_lacks_earns_nothing_and_is_explained(): void
    {
        // InteriorFeatures populated, and the governed rule that could name Quartz did not.
        $home = $this->derived(['InteriorFeatures' => ['Granite Counters']]);

        $result = $this->build($home, ['quartz_countertops']);

        $this->assertSame(0, $result->categoryScores['amenities']);
        $this->assertSame($this->score($home, [])->totalScore - 10, $result->totalScore);
        $this->assertContains('Does not list: Quartz Countertops', array_column($result->tradeoffs, 'label'));
    }

    /** @test */
    public function several_selected_tags_share_the_allocation_over_checkable_picks_only(): void
    {
        $home = $this->derived(['InteriorFeatures' => ['Quartz Counters'], 'Appliances' => ['Range Gas']], ['pool_private_yn' => false]);

        // quartz + gas range present, pool known absent (a structured No), updated_kitchen has no
        // structured Bridge rule: unknown, in neither numerator nor denominator → 2 of 3.
        $result = $this->score($home, ['quartz_countertops', 'updated_kitchen', 'gas_range', 'private_pool']);

        $this->assertSame((int) round(10 * 2 / 3), $result->categoryScores['amenities']);
        $this->assertSame(['updated_kitchen'], $result->seekerFeatureMatch->unknownKeys());
        $this->assertSame(['private_pool'], $result->seekerFeatureMatch->knownAbsentKeys);
    }

    /** @test */
    public function the_picks_are_one_amenity_among_the_structured_ones(): void
    {
        $columns = ['pool_private_yn' => true, 'garage_yn' => true, 'waterfront_yn' => true, 'view_yn' => true];
        $criteria = ['wants_pool' => true, 'wants_garage' => true, 'wants_waterfront' => true, 'wants_any_view' => true];

        // Pool 4 + garage 3 + waterfront 2 + view 1 all earned, a CHECKED pick 4 × 0 → 10 × 10/14.
        $checked = $this->derived(['InteriorFeatures' => ['Walk-In Closet(s)']], $columns);
        $this->assertSame((int) round(10 * 10 / 14), $this->score($checked, ['quartz_countertops'], $criteria)->categoryScores['amenities']);

        // An UNCHECKABLE pick is not an expressed amenity on this listing: exactly the structured score.
        $unknown = $this->derived([], $columns);
        $this->assertSame(
            $this->score($unknown, [], $criteria)->categoryScores,
            $this->score($unknown, ['quartz_countertops'], $criteria)->categoryScores,
        );
    }

    /**
     * Every seeker-selectable residential tag at once, under the per-tag checkability model.
     *
     * @test
     */
    public function selecting_every_feature_can_never_exceed_the_category_or_the_total(): void
    {
        $all = array_keys(SmartTagTaxonomy::forContext(SmartTagContext::ResidentialSale, SmartTagTaxonomy::SURFACE_SEEKER));
        $this->assertGreaterThan(50, count($all));
        $columns = ['pool_private_yn' => true, 'garage_yn' => true, 'waterfront_yn' => true, 'view_yn' => true];
        $criteria = ['wants_pool' => true, 'wants_garage' => true, 'wants_waterfront' => true, 'wants_any_view' => true];

        $everything = $this->bridge($all, $columns);
        $nothing    = $this->bridge(['gas_range']);
        $realistic  = $this->derived([
            'InteriorFeatures' => ['Quartz Counters', 'Walk-In Closet(s)'],
            'Appliances'       => ['Range Gas'],
            'Flooring'         => ['Tile'],
            'CommunityFeatures'=> ['Clubhouse'],
        ], ['pool_private_yn' => false]);

        foreach ([$everything, $nothing, $realistic] as $home) {
            foreach ([[], $criteria] as $with) {
                $result = $this->score($home, $all, $with);

                $this->assertLessThanOrEqual(10, $result->categoryScores['amenities']);
                $this->assertLessThanOrEqual(100, $result->totalScore);
                $this->assertGreaterThanOrEqual(0, $result->totalScore);
            }
        }

        $this->assertSame(10, $this->score($everything, $all)->categoryScores['amenities']);

        // A derived listing: every pick is present, a checked miss, or unknown — and only the
        // first two count. Tags with no structured Bridge rule are always unknown.
        $match = $this->score($realistic, $all)->seekerFeatureMatch;
        $this->assertSame(count($all), count($match->matchedKeys) + count($match->knownAbsentKeys) + count($match->unknownKeys()));
        $this->assertContains('quartz_countertops', $match->matchedKeys);
        $this->assertContains('granite_countertops', $match->knownAbsentKeys, 'InteriorFeatures was populated');
        $this->assertContains('updated_kitchen', $match->unknownKeys(), 'no structured Bridge rule');
        $this->assertContains('natural_light', $match->unknownKeys(), 'never derivable');
        $this->assertSame(
            (int) round(10 * $match->matchedCount() / $match->checkableCount()),
            $this->score($realistic, $all)->categoryScores['amenities'],
        );

        // One present pick among many unknown ones is 1 of 1 checkable, not 1 of 124.
        $this->assertSame(10, $this->score($this->bridge([$all[0]]), $all)->categoryScores['amenities']);
    }

    /** @test */
    public function no_selection_is_exactly_the_pre_feature_score_and_reads_nothing(): void
    {
        $home = $this->bridge(['quartz_countertops', 'private_pool'], ['pool_private_yn' => true]);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $legacy = (new BuyerMatchScorer())->score($home, $this->payload(['wants_pool' => true]));
        $empty  = $this->scoreOne($home, $this->payload(['wants_pool' => true, 'seeker_smart_tags' => []]));

        $this->assertSame([], $this->smartTagQueries(DB::getQueryLog()));
        DB::disableQueryLog();

        $this->assertSame($legacy->categoryScores, $empty->categoryScores);
        $this->assertSame($legacy->totalScore, $empty->totalScore);
        $this->assertNull($empty->seekerFeatureMatch);
    }

    /** @test */
    public function a_listing_with_no_tag_data_keeps_its_historical_score_and_says_why(): void
    {
        $home = $this->bridge([]);

        $result = $this->build($home, ['quartz_countertops']);

        $this->assertSame($this->score($home, [])->categoryScores, $result->categoryScores, 'no bonus and no penalty');
        $this->assertFalse($result->seekerFeatureMatch->hasCheckablePicks());
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

    // ------------------------------------------------- per-context activation

    /**
     * Matching is activated ONE CONTEXT AT A TIME, as each context's Bridge tag
     * coverage is verified. A seeker in a context not yet activated gets no picks
     * scored — exactly the pre-feature score — rather than every listing earning
     * nothing for want of tags we have not derived.
     *
     * @test
     */
    public function only_an_activated_context_has_its_picks_scored(): void
    {
        [$buyer, $buyerId] = $this->buyerOffer(['quartz_countertops']);
        [$tenant, $tenantId] = $this->tenantOffer(['updated_kitchen']);

        config()->set('smart_tags_wiring.seeker_matching_contexts', ['residential.lease']);

        $this->assertSame([], app(BuyerOfferListingCriteriaLoader::class)->loadById($buyerId, [$buyer->id])['seeker_smart_tags'],
            'residential.sale is not activated, so the buyer\'s picks must not be scored.');
        $this->assertSame(['updated_kitchen'], app(TenantOfferListingCriteriaLoader::class)->loadById($tenantId, [$tenant->id])['seeker_smart_tags']);
    }

    /** @test */
    public function an_empty_or_unrecognised_context_list_activates_nothing(): void
    {
        [$buyer, $buyerId] = $this->buyerOffer(['quartz_countertops']);

        foreach ([[], [''], ['Residential'], ['residential_sale'], ['RESIDENTIAL.SALE'], 'residential.sale', null] as $contexts) {
            config()->set('smart_tags_wiring.seeker_matching_contexts', $contexts);

            $this->assertFalse(SmartTagSeekerPreferenceGate::matchingEnabledFor(SmartTagContext::ResidentialSale), var_export($contexts, true));
            $this->assertSame([], app(BuyerOfferListingCriteriaLoader::class)->loadById($buyerId, [$buyer->id])['seeker_smart_tags'], var_export($contexts, true));
        }
    }

    /** @test */
    public function an_activated_context_still_needs_both_matching_gates(): void
    {
        config()->set('smart_tags_wiring.seeker_matching_contexts', ['residential.sale']);
        $this->assertTrue(SmartTagSeekerPreferenceGate::matchingEnabledFor(SmartTagContext::ResidentialSale));
        $this->assertFalse(SmartTagSeekerPreferenceGate::matchingEnabledFor(null));

        config()->set('smart_tags_wiring.seeker_matching_enabled', false);
        $this->assertFalse(SmartTagSeekerPreferenceGate::matchingEnabledFor(SmartTagContext::ResidentialSale));

        config()->set('smart_tags_wiring.seeker_matching_enabled', true);
        config()->set('smart_tags_wiring.seeker_preferences_enabled', false);
        $this->assertFalse(SmartTagSeekerPreferenceGate::matchingEnabledFor(SmartTagContext::ResidentialSale));
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

        $this->assertSame($this->score($home, [])->categoryScores, $result->categoryScores);
        $this->assertFalse($result->seekerFeatureMatch->hasCheckablePicks());
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

        $this->assertSame([], $this->smartTagQueries($log), 'no assignment, state or evidence read');
        $this->assertSame(
            $legacy->map(fn ($r) => [$r->toArray(), $r->seekerFeatureMatch])->all(),
            $picked->map(fn ($r) => [$r->toArray(), $r->seekerFeatureMatch])->all(),
        );
    }

    // ----------------------------------------------- present / absent / unknown

    /**
     * THE RULE: present earns, a CHECKED miss earns nothing, and unknown is left out — the
     * listing keeps exactly the score it would have had with no pick. Only a governed
     * structured rule reading a populated field can turn "no row" into a miss.
     *
     * @test
     */
    public function present_known_absent_and_unknown_are_three_explained_states(): void
    {
        $presentHome = $this->derived(['InteriorFeatures' => ['Quartz Counters']]);
        $absentHome  = $this->derived(['InteriorFeatures' => ['Walk-In Closet(s)']]);
        $unknownHome = $this->derived([]);

        $present = $this->build($presentHome, ['quartz_countertops']);
        $absent  = $this->build($absentHome, ['quartz_countertops']);
        $unknown = $this->build($unknownHome, ['quartz_countertops']);

        $this->assertSame(10, $present->categoryScores['amenities']);
        $this->assertSame(0, $absent->categoryScores['amenities']);
        $this->assertSame($this->score($unknownHome, [])->categoryScores, $unknown->categoryScores, 'unknown is exactly the historical score');
        $this->assertGreaterThan($absent->totalScore, $unknown->totalScore);

        $this->assertSame(['quartz_countertops'], $absent->seekerFeatureMatch->knownAbsentKeys);
        $this->assertFalse($unknown->seekerFeatureMatch->hasCheckablePicks());

        $this->assertContains('Does not list: Quartz Countertops', array_column($absent->tradeoffs, 'label'));
        $this->assertSame([], array_column($absent->missingData, 'label'));
        $this->assertNotContains('Does not list: Quartz Countertops', array_column($unknown->tradeoffs, 'label'));
        $this->assertContains('Feature details not available — your selected features could not be checked for this home',
            array_column($unknown->missingData, 'label'));
    }

    /** @test */
    public function an_explicit_stored_absent_is_a_known_miss_even_without_a_current_derivation(): void
    {
        $home = $this->bridge(['gas_range']);
        SmartTagAssignment::create([
            'listing_type' => 'bridge', 'listing_id' => $home->id, 'tag_key' => 'private_pool',
            'context' => 'residential.sale', 'state' => 'absent', 'winning_source' => 'structured_mls',
        ]);

        $result = $this->build($home, ['private_pool']);

        $this->assertSame(0, $result->categoryScores['amenities']);
        $this->assertContains('Does not list: Private Pool', array_column($result->tradeoffs, 'label'));
    }

    /**
     * An unrelated present tag says nothing about a selected one: the old "has any tag → miss"
     * rule is gone.
     *
     * @test
     */
    public function an_unrelated_present_tag_never_makes_an_uncheckable_pick_a_miss(): void
    {
        $home = $this->derived(['Cooling' => ['Central Air']]);
        $this->assertContains('central_air', $this->presentTagsOf($home));

        foreach (['updated_kitchen', 'quartz_countertops'] as $pick) {
            $result = $this->build($home, [$pick]);

            $this->assertSame($this->score($home, [])->categoryScores, $result->categoryScores, $pick);
            $this->assertSame([], $result->seekerFeatureMatch->knownAbsentKeys, $pick);
            $this->assertSame([], preg_grep('/^Does not list/', array_column($result->tradeoffs, 'label')), $pick);
        }
    }

    /** @test */
    public function a_populated_field_is_only_evidence_when_the_derivation_is_current(): void
    {
        $home = $this->derived(['InteriorFeatures' => ['Walk-In Closet(s)']]);
        $this->assertSame(['quartz_countertops'], $this->score($home, ['quartz_countertops'])->seekerFeatureMatch->knownAbsentKeys);

        // The MLS row changed after it was tagged: the stored assignments no longer describe it.
        $home->raw_json = json_encode(array_merge(json_decode($home->raw_json, true), ['InteriorFeatures' => ['Walk-In Closet(s)', 'Wet Bar']]));
        $home->save();
        $this->assertSame([], $this->score($home->fresh(), ['quartz_countertops'])->seekerFeatureMatch->knownAbsentKeys);

        // Never derived at all: unknown.
        $never = $this->bridge([], ['raw_json' => json_encode(['IDXParticipationYN' => true, 'InteriorFeatures' => ['Walk-In Closet(s)']])]);
        $this->assertFalse($this->score($never, ['quartz_countertops'])->seekerFeatureMatch->hasCheckablePicks());
    }

    /** @test */
    public function one_present_and_one_unknown_pick_score_over_the_present_one(): void
    {
        $home = $this->derived(['InteriorFeatures' => ['Quartz Counters']]);

        $result = $this->build($home, ['quartz_countertops', 'updated_kitchen']);

        $this->assertSame(10, $result->categoryScores['amenities'], '1 of 1 checkable, not 1 of 2');
        $this->assertSame(['updated_kitchen'], $result->seekerFeatureMatch->unknownKeys());
        $this->assertNotContains('Does not list: ' . SmartTagTaxonomy::get('updated_kitchen')->label, array_column($result->tradeoffs, 'label'));
        $this->assertContains('Some selected features could not be checked: ' . SmartTagTaxonomy::get('updated_kitchen')->label,
            array_column($result->missingData, 'label'));
    }

    /** @test */
    public function one_known_absent_and_one_unknown_pick_score_over_the_absent_one(): void
    {
        $home = $this->derived(['InteriorFeatures' => ['Walk-In Closet(s)']]);

        $result = $this->build($home, ['quartz_countertops', 'updated_kitchen']);

        $this->assertSame(0, $result->categoryScores['amenities'], '0 of 1 checkable');
        $this->assertContains('Does not list: Quartz Countertops', array_column($result->tradeoffs, 'label'));
        foreach (array_column($result->tradeoffs, 'label') as $label) {
            $this->assertStringNotContainsString(SmartTagTaxonomy::get('updated_kitchen')->label, $label);
        }
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
        $pool   = $this->derived([], ['pool_private_yn' => true]);
        $noPool = $this->derived([], ['pool_private_yn' => false]);

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
        $withQuartz    = $this->derived(['InteriorFeatures' => ['Quartz Counters']], ['pool_private_yn' => true]);
        $withoutQuartz = $this->derived(['InteriorFeatures' => ['Walk-In Closet(s)']], ['pool_private_yn' => true]);

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

        $waterView = $this->derived([], ['view_yn' => true, 'water_view_yn' => true]);
        $golfView  = $this->derived([], ['view_yn' => true, 'water_view_yn' => false]);

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
        // natural_light is unknown on this never-derived row: pool 4/4 + picks 4 × 1/1 → 8 of 8.
        $this->assertSame(['natural_light'], $score->seekerFeatureMatch->unknownKeys());
        $this->assertSame(10, $score->categoryScores['amenities']);
    }

    /**
     * The Bridge entry point adapts only: without Smart Tag facts handed to it, it reads none,
     * and the picks are unknown — the historical score, worded as "could not be checked".
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
        $this->assertFalse($bare->seekerFeatureMatch->hasCheckablePicks());
        $this->assertSame((new BuyerMatchScorer())->score($home, $this->payload())->categoryScores, $bare->categoryScores);
        $this->assertSame(['quartz_countertops'], $this->scoreOne($home, $payload)->seekerFeatureMatch->matchedKeys);
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
        $a = $this->derived(['InteriorFeatures' => ['Quartz Counters']]);
        $b = $this->derived(['InteriorFeatures' => ['Granite Counters']]);
        $c = $this->derived([]);

        $without = $this->service()->match($this->payload());
        $with    = $this->service()->match($this->payload(['seeker_smart_tags' => ['quartz_countertops']]));

        $keys = static fn ($results): array => $results->pluck('listingKey')->sort()->values()->all();
        $this->assertSame($keys($without), $keys($with));

        $byKey = $with->keyBy('listingKey');
        $this->assertSame(10, $byKey[$a->listing_key]->categoryScores['amenities']);
        $this->assertSame(0, $byKey[$b->listing_key]->categoryScores['amenities'], 'checked, and not listed');
        $this->assertSame(10, $byKey[$c->listing_key]->categoryScores['amenities'], 'could not be checked: historical score');
        $this->assertSame($a->listing_key, $with->first()->listingKey);
    }

    /** @test */
    public function the_batch_path_and_the_single_listing_path_agree(): void
    {
        // Present, known absent and unknown on one listing, among other candidates.
        $home    = $this->derived(['InteriorFeatures' => ['Quartz Counters']], ['pool_private_yn' => false]);
        $others  = [$this->derived(['InteriorFeatures' => ['Walk-In Closet(s)']]), $this->bridge(['natural_light'])];
        $payload = $this->payload(['seeker_smart_tags' => ['quartz_countertops', 'private_pool', 'natural_light', 'granite_countertops', 'updated_kitchen']]);

        $batchAll = (new BuyerMatchScorer())->scoreAll(array_merge([$home], $others), $payload);

        foreach (array_merge([$home], $others) as $i => $row) {
            $single = $this->scoreOne($row, $payload);
            $this->assertEquals($batchAll[$i]->seekerFeatureMatch, $single->seekerFeatureMatch, "facts differ for candidate {$i}");
            $this->assertSame($batchAll[$i]->categoryScores, $single->categoryScores);
            $this->assertSame($batchAll[$i]->totalScore, $single->totalScore);
        }

        $match = $batchAll[0]->seekerFeatureMatch;
        $this->assertSame(['quartz_countertops'], $match->matchedKeys);
        $this->assertSame(['private_pool', 'granite_countertops'], $match->knownAbsentKeys);
        $this->assertSame(['natural_light', 'updated_kitchen'], $match->unknownKeys());
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
                $i % 2 === 0
                    ? $this->derived(['InteriorFeatures' => ['Quartz Counters']])
                    : $this->derived(['InteriorFeatures' => ['Walk-In Closet(s)']]);
            }

            DB::enableQueryLog();
            DB::flushQueryLog();
            $this->service()->match($this->payload(['seeker_smart_tags' => ['quartz_countertops', 'natural_light']]));
            $log = DB::getQueryLog();
            DB::disableQueryLog();

            return count($this->smartTagQueries($log)) * 1000 + count($log);
        };

        $small = $count(3);
        $large = $count(60);

        // One assignment read, then derivation states and structured evidence for the rows with
        // an unresolved pick — three Smart Tag reads for the whole candidate set.
        $this->assertSame(3, intdiv($small, 1000), 'three Smart Tag reads for the whole candidate set');
        $this->assertSame($small, $large, 'no query grows with the candidates');
    }

    // ------------------------------------------------------------- presentation

    /** @test */
    public function the_card_names_features_in_words_and_exposes_no_key_weight_or_ai(): void
    {
        $home = $this->derived(['InteriorFeatures' => ['Quartz Counters']], ['pool_private_yn' => false]);
        SmartTagAssignment::create([
            'listing_type' => 'bridge', 'listing_id' => $home->id, 'tag_key' => 'natural_light',
            'context' => 'residential.sale', 'state' => 'present', 'winning_source' => 'manual_listing_owner',
        ]);

        $result = $this->build($home, ['quartz_countertops', 'natural_light', 'private_pool', 'updated_kitchen']);
        $card   = app(BuyerResultViewMapper::class)->mapOne($result);
        $json   = json_encode($card);

        $why = array_column($card['why_this_matches'], 'label');
        $updated = SmartTagTaxonomy::get('updated_kitchen')->label;
        // Named in the seeker's own selection order.
        // Updated Kitchen could not be checked, so the ratio is over the three checked picks.
        $this->assertContains('Matches 2 of 3 checked selected features: Quartz Countertops and ' . SmartTagTaxonomy::get('natural_light')->label, $why);
        $this->assertContains('Does not list: Private Pool', array_column($card['tradeoffs'], 'label'));
        foreach (array_column($card['tradeoffs'], 'label') as $label) {
            $this->assertStringNotContainsString($updated, $label, 'an unknown feature is never "not listed"');
        }
        $this->assertStringContainsString('Some selected features could not be checked: ' . $updated, $json);

        foreach (['quartz_countertops', 'natural_light', 'private_pool', 'seeker_features', 'selected_features', 'smart_tag', 'Smart Tag'] as $internal) {
            $this->assertStringNotContainsString($internal, $json, $internal);
        }
        $this->assertDoesNotMatchRegularExpression('/\bAI\b|artificial intelligence/i', $json);
    }

    /**
     * The match sentence uses the scoring denominator — checked picks — and never states a ratio
     * over picks nobody could check.
     *
     * @test
     */
    public function the_match_sentence_counts_only_checked_picks(): void
    {
        $home = $this->derived(['InteriorFeatures' => ['Quartz Counters'], 'Appliances' => ['Range Gas']], ['pool_private_yn' => false]);
        $labels = static fn ($result): array => [
            'why'     => array_column($result->whyThisMatches, 'label'),
            'trade'   => array_column($result->tradeoffs, 'label'),
            'missing' => array_column($result->missingData, 'label'),
        ];
        $updated = SmartTagTaxonomy::get('updated_kitchen')->label;
        $gas     = SmartTagTaxonomy::get('gas_range')->label;

        // All picks checkable: the familiar N of M over every pick.
        $all = $labels($this->build($home, ['quartz_countertops', 'gas_range', 'private_pool']));
        $this->assertContains("Has 2 of your 3 selected features: Quartz Countertops and {$gas}", $all['why']);
        $this->assertContains('Does not list: Private Pool', $all['trade']);
        $this->assertSame([], preg_grep('/could not be checked/', $all['missing']));

        // Present + known miss + unknown: the denominator is present + known miss only.
        $mixed = $labels($this->build($home, ['quartz_countertops', 'private_pool', 'updated_kitchen']));
        $this->assertContains('Matches 1 of 2 checked selected features: Quartz Countertops', $mixed['why']);
        $this->assertContains('Does not list: Private Pool', $mixed['trade']);
        $this->assertContains("Some selected features could not be checked: {$updated}", $mixed['missing']);

        // One present + many unknown: never "1 of <everything selected>".
        $many = array_merge(['quartz_countertops'], array_values(array_filter(
            array_keys(SmartTagTaxonomy::forContext(SmartTagContext::ResidentialSale, SmartTagTaxonomy::SURFACE_SEEKER)),
            static fn (string $key): bool => ! \App\Services\SmartTags\Seeker\BridgeSmartTagCheckability::hasStructuredCapability($key, SmartTagContext::ResidentialSale),
        )));
        $this->assertGreaterThan(10, count($many));
        $one = $labels($this->build($home, $many));
        $this->assertContains('Matches 1 checked selected feature: Quartz Countertops', $one['why']);
        $this->assertSame([], preg_grep('/\bof (your )?' . count($many) . '\b/', $one['why']));
        $this->assertSame([], preg_grep('/^Does not list/', $one['trade']));

        // All unknown: no ratio at all, only the unavailable message.
        $none = $labels($this->build($this->derived([]), ['quartz_countertops', 'updated_kitchen']));
        $this->assertSame([], preg_grep('/selected feature/', $none['why']));
        $this->assertSame([], preg_grep('/^Does not list/', $none['trade']));
        $this->assertContains('Feature details not available — your selected features could not be checked for this home', $none['missing']);

        // An unknown tag is never under "Does not list", in any of the above.
        foreach ([$all, $mixed, $one, $none] as $set) {
            foreach ($set['trade'] as $label) {
                $this->assertStringNotContainsString($updated, $label);
            }
        }
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

    /**
     * A Bridge row whose tags come from the REAL governed derivation of its structured fields —
     * current derivation state included — so "field populated but not listed" is observable.
     *
     * @param array<string, mixed> $raw     RESO fields merged into raw_json
     * @param array<string, mixed> $columns bridge_properties columns
     */
    private function derived(array $raw, array $columns = [], bool $lease = false): BridgeProperty
    {
        $row = $this->bridge([], array_merge($columns, [
            'raw_json' => json_encode(array_merge(['IDXParticipationYN' => true, 'LeaseAmountFrequency' => 'Monthly'], $raw)),
        ]), $lease);

        app(\App\Services\SmartTags\SmartTagDerivationService::class)->deriveBridge($row);

        return $row->fresh();
    }

    /** @return list<string> */
    private function presentTagsOf(BridgeProperty $row): array
    {
        return SmartTagAssignment::query()->where('listing_type', 'bridge')->where('listing_id', $row->id)
            ->where('state', 'present')->orderBy('tag_key')->pluck('tag_key')->all();
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

    /** @return list<string> every read of a Smart Tag table */
    private function smartTagQueries(array $log): array
    {
        return array_values(array_filter(
            array_column($log, 'query'),
            static fn (string $sql): bool => (bool) preg_match('/smart_tag_(assignments|evidence|derivation_states)/', $sql),
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
