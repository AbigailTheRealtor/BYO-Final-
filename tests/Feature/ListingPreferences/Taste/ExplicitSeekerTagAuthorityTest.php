<?php

namespace Tests\Feature\ListingPreferences\Taste;

use App\Models\BridgeProperty;
use App\Models\BuyerAgentAuction;
use App\Models\SmartTagAssignment;
use App\Models\User;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Bridge\LazyImportResult;
use App\Services\ListingPreferences\Taste\TasteRerankOutcome;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\ListingPreferences\Taste\TasteConfidence;
use App\Support\ListingPreferences\Taste\TasteDimension;
use App\Support\ListingPreferences\Taste\TasteDirection;
use App\Support\ListingPreferences\Taste\TasteDnaDeriver;
use App\Support\ListingPreferences\Taste\TasteDnaReranker;
use App\Support\ListingPreferences\Taste\TasteListingFacts;
use App\Support\ListingPreferences\Taste\TasteProfile;
use App\Support\ListingPreferences\Taste\TasteRerankCandidate;
use App\Support\ListingPreferences\Taste\TasteSignal;
use App\Support\ListingPreferences\Taste\TasteSource;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Explicit seeker Smart Tags now carry authority in the Match DNA score. Does
 * that make Phase 5's explicit-tag bypass removable?
 *
 * The real scorer produces the base scores and the real bounded reranker is
 * given the strongest Taste it can act on, pulling AGAINST the explicit pick.
 * The answer these tests pin: a pick that opens a gap of 3+ points is never
 * overridden — but a pick can open a smaller gap (one pick among many, or one
 * pick beside the structured amenities), and there Taste's ±1.25 CAN reorder
 * a listing lacking an explicit pick above one that has it. So the bypass
 * stays, and these tests say why.
 */
class ExplicitSeekerTagAuthorityTest extends TestCase
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

    /** @test */
    public function a_material_explicit_advantage_is_never_overridden_by_maximum_taste(): void
    {
        // B's InteriorFeatures were read and name no quartz: a checked miss, not unknown.
        $a = $this->bridge(['white_cabinets'], [], ['InteriorFeatures' => ['Quartz Counters']]);
        $b = $this->bridge(['natural_light', 'updated_kitchen'], [], ['InteriorFeatures' => ['Walk-In Closet(s)']]);

        $payload = $this->payload(['quartz_countertops']);
        [$scoreA, $scoreB] = $this->scores([$a, $b], $payload);

        $this->assertGreaterThanOrEqual(3, $scoreA - $scoreB, 'one pick with no other amenity expressed is worth the whole category');

        $result = TasteDnaReranker::rerank($this->tasteAgainstA(), [
            new TasteRerankCandidate('A', $scoreA, new TasteListingFacts(tagKeys: ['quartz_countertops', 'white_cabinets'])),
            new TasteRerankCandidate('B', $scoreB, new TasteListingFacts(tagKeys: ['natural_light', 'updated_kitchen'])),
        ]);

        $this->assertSame(['A', 'B'], $result->orderedKeys);
        $this->assertSame(TasteDnaReranker::MAX_INFLUENCE, $result->influence('B')->adjustment, 'Taste pushed B as hard as it can');
        $this->assertSame(-TasteDnaReranker::MAX_INFLUENCE, $result->influence('A')->adjustment, 'and A as hard as it can — and still could not cross');
    }

    /**
     * WHY THE BYPASS STAYS. The same explicit request, spread across many picks,
     * leaves a one-point lead that maximum Taste can overturn.
     *
     * @test
     */
    public function a_small_explicit_advantage_can_be_overturned_so_the_bypass_remains(): void
    {
        // Eight picks. A has three of them; B has two — the two Taste loves.
        $picks = ['quartz_countertops', 'white_cabinets', 'gas_range', 'natural_light', 'updated_kitchen', 'private_pool', 'fireplace', 'walk_in_closet'];
        $aTags = ['quartz_countertops', 'white_cabinets', 'gas_range'];
        $bTags = ['natural_light', 'updated_kitchen'];

        // Checkable picks only. A: quartz, cabinets, gas range present; pool and fireplace a
        // structured No, walk-in closet not in its populated InteriorFeatures → 3 of 6; natural
        // light and updated kitchen unknown. B: natural light and updated kitchen present;
        // quartz, fireplace and walk-in closet checked and not listed → 2 of 5; cabinets, gas
        // range and pool unknown (no rule / no Appliances / no pool column).
        $a = $this->bridge(['white_cabinets'], ['pool_private_yn' => false],
            ['InteriorFeatures' => ['Quartz Counters'], 'Appliances' => ['Range Gas'], 'FireplaceYN' => false]);
        $b = $this->bridge($bTags, ['pool_private_yn' => null], ['InteriorFeatures' => ['Split Bedroom']]);
        [$scoreA, $scoreB] = $this->scores([$a, $b], $this->payload($picks));

        // 10 × 3/6 against 10 × 2/5: A leads, by less than three points.
        $this->assertGreaterThan($scoreB, $scoreA);
        $this->assertLessThan(3, $scoreA - $scoreB);

        $result = TasteDnaReranker::rerank($this->tasteAgainstA($aTags), [
            new TasteRerankCandidate('A', $scoreA, new TasteListingFacts(tagKeys: $aTags)),
            new TasteRerankCandidate('B', $scoreB, new TasteListingFacts(tagKeys: $bTags)),
        ]);

        $this->assertSame(['B', 'A'], $result->orderedKeys,
            'a listing with FEWER explicit picks overtook one with more — why explicit picks still bypass Taste');
    }

    /**
     * The smallest SINGLE-pick lead: one pick beside all four structured amenities
     * is 4 of 14 amenity weight (about 2.9 points), which rounds to the 3-point
     * floor here — protected, but only just. Many picks go below it (above).
     *
     * @test
     */
    public function one_pick_beside_every_structured_amenity_reaches_the_three_point_floor(): void
    {
        $structured = ['pool_private_yn' => true, 'garage_yn' => true, 'waterfront_yn' => true, 'view_yn' => true];
        $a = $this->bridge([], $structured, ['InteriorFeatures' => ['Quartz Counters']]);
        $b = $this->bridge(['natural_light', 'updated_kitchen'], $structured, ['InteriorFeatures' => ['Walk-In Closet(s)'], 'Appliances' => ['Range Gas']]);

        $payload = $this->payload(['quartz_countertops'], ['wants_pool' => true, 'wants_garage' => true, 'wants_waterfront' => true, 'wants_any_view' => true]);
        [$scoreA, $scoreB] = $this->scores([$a, $b], $payload);

        $this->assertSame(3, $scoreA - $scoreB);

        $result = TasteDnaReranker::rerank($this->tasteAgainstA(['quartz_countertops']), [
            new TasteRerankCandidate('A', $scoreA, new TasteListingFacts(tagKeys: ['quartz_countertops'])),
            new TasteRerankCandidate('B', $scoreB, new TasteListingFacts(tagKeys: ['natural_light', 'updated_kitchen', 'gas_range'])),
        ]);

        $this->assertSame(['A', 'B'], $result->orderedKeys);
    }

    /**
     * End to end, with the real matcher: the pick raises the matching home in the
     * score itself, and the page still bypasses Taste for that search.
     *
     * @test
     */
    public function the_results_page_scores_the_pick_and_still_bypasses_taste(): void
    {
        config()->set('smart_tags_wiring.seeker_preferences_enabled', true);
        config()->set('listing_preferences.enabled', true);
        config()->set('listing_preferences.taste_dna_enabled', true);
        config()->set('listing_preferences.taste_reranking_enabled', true);

        $lazy = $this->createMock(LazyBridgeImportService::class);
        $lazy->method('importForCriteria')->willReturn(LazyImportResult::cached(0));
        $this->app->instance(LazyBridgeImportService::class, $lazy);

        $plain = $this->bridge([], [], ['InteriorFeatures' => ['Walk-In Closet(s)'], 'Appliances' => ['Range Gas']]);
        $match = $this->bridge([], [], ['InteriorFeatures' => ['Quartz Counters']]);

        $user = User::factory()->create(['user_type' => 'buyer']);
        $criteriaId = DB::table('buyer_agent_auctions')->insertGetId([
            'user_id' => $user->id, 'title' => 'Authority', 'is_approved' => 'true', 'is_sold' => 'false',
            'is_paid' => '0', 'is_draft' => false, 'referral_locked' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $auction = BuyerAgentAuction::findOrFail($criteriaId);
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'Residential', 'preferred_cities' => json_encode(['Orlando'])] as $k => $v) {
            $auction->saveMeta($k, $v);
        }
        DB::table('smart_tag_seeker_preferences')->insert([
            'subject_type' => 'buyer_offer_listing', 'subject_id' => $criteriaId, 'user_id' => $user->id,
            'seeker_role' => 'buyer', 'tag_key' => 'quartz_countertops', 'context' => 'residential.sale',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('stellar.buyer.results', ['criteria_type' => 'buyer_offer', 'criteria_id' => $criteriaId]));
        $response->assertOk();

        $cards = $response->viewData('results');
        $this->assertSame([$match->listing_key, $plain->listing_key], array_column($cards, 'listing_key'));
        $this->assertSame(10, $cards[0]['total_score'] - $cards[1]['total_score']);
        $this->assertSame(TasteRerankOutcome::EXPLICIT_CRITERIA, $response->viewData('tasteStatus'));
        $response->assertSee('Has 1 of your 1 selected features: Quartz Countertops');
    }

    // ------------------------------------------------------------------ helpers

    /**
     * The strongest Taste the reranker acts on, all of it against A: stated,
     * established likes for what B has and dislikes for what A has.
     */
    private function tasteAgainstA(array $aTags = ['quartz_countertops', 'white_cabinets']): TasteProfile
    {
        $signals = [
            $this->signal('natural_light', TasteDirection::Positive),
            $this->signal('updated_kitchen', TasteDirection::Positive),
        ];
        foreach ($aTags as $tag) {
            if (! in_array($tag, ['natural_light', 'updated_kitchen'], true)) {
                $signals[] = $this->signal($tag, TasteDirection::Negative);
            }
        }

        return new TasteProfile(1, SeekerRole::Buyer, $signals, 6, 6, TasteDnaDeriver::RULES_VERSION);
    }

    private function signal(string $key, TasteDirection $direction): TasteSignal
    {
        $positive = $direction === TasteDirection::Positive;

        return new TasteSignal(
            dimension: TasteDimension::SmartTag, key: $key, label: $key, direction: $direction,
            confidence: TasteConfidence::Established, strength: 3.0, agreement: 1.0,
            positiveWeight: $positive ? 3.0 : 0.0, negativeWeight: $positive ? 0.0 : 3.0, uncertainWeight: 0.0,
            supportCount: 3, saveCount: $positive ? 3 : 0, maybeCount: 0, passCount: $positive ? 0 : 3,
            firstAt: null, lastAt: null, sources: [TasteSource::StatedReason],
        );
    }

    /** @return array{0: int, 1: int} */
    private function scores(array $homes, BuyerCriteriaPayload $payload): array
    {
        $results = (new BuyerMatchScorer())->scoreAll($homes, $payload);

        return [$results[0]->totalScore, $results[1]->totalScore];
    }

    private function payload(array $picks, array $criteria = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload($criteria + [
            'property_types' => ['Residential'], 'is_55_plus_eligible' => false,
            'preferred_cities' => ['Orlando'], 'seeker_smart_tags' => $picks,
        ]);
    }

    /**
     * @param list<string>              $tags    planted present rows (tags no Bridge rule can derive)
     * @param array<string, mixed>|null $raw     RESO fields; when given, the row goes through the REAL
     *                                           derivation first, so a populated field the rules read
     *                                           makes a non-listed pick a CHECKED miss, not unknown
     */
    private function bridge(array $tags, array $columns = [], ?array $raw = null): BridgeProperty
    {
        $this->n++;

        $row = BridgeProperty::create(array_merge([
            'provider' => 'stellar_bridge', 'listing_key' => "ESA-{$this->n}-" . uniqid(), 'listing_id' => "ESA-{$this->n}",
            'standard_status' => 'Active', 'property_type' => 'Residential', 'list_price' => 400000,
            'city' => 'Orlando', 'state_or_province' => 'FL', 'postal_code' => '32801',
            'bedrooms_total' => 3, 'bathrooms_total_integer' => 2, 'living_area' => 1800, 'senior_community_yn' => false,
            'raw_json' => json_encode(array_merge(['IDXParticipationYN' => true], $raw ?? [])),
        ], $columns));

        if ($raw !== null) {
            app(\App\Services\SmartTags\SmartTagDerivationService::class)->deriveBridge($row);
        }

        foreach (array_diff($tags, SmartTagAssignment::query()->where('listing_type', 'bridge')->where('listing_id', $row->id)->pluck('tag_key')->all()) as $tag) {
            SmartTagAssignment::create([
                'listing_type' => 'bridge', 'listing_id' => $row->id, 'tag_key' => $tag,
                'context' => 'residential.sale', 'state' => 'present', 'winning_source' => 'structured_mls',
            ]);
        }

        return $row;
    }
}
