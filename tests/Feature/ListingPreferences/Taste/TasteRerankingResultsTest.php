<?php

namespace Tests\Feature\ListingPreferences\Taste;

use App\Models\BridgeProperty;
use App\Models\BuyerAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\SmartTagAssignment;
use App\Models\TenantAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Services\ListingPreferences\Taste\TasteRerankOutcome;
use App\Services\Stellar\Matching\BuyerMatchService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 5 through the real Stellar results route: the real controller, mapper,
 * view, Taste DNA service and preference writer — with ONLY the matcher's output
 * pinned, so every base score in these tests is exact and the assertions are
 * about ordering and nothing else.
 *
 * `TasteRerankingPipelineTest` runs the REAL matcher to prove the 100-point
 * scores themselves do not move.
 */
class TasteRerankingResultsTest extends TestCase
{
    use DatabaseTransactions;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flags(true, true, true);
    }

    // ---------------------------------------------------------------- flags

    /** @test */
    public function any_one_flag_off_leaves_the_exact_existing_order_and_says_nothing(): void
    {
        foreach ([[false, true, true], [true, false, true], [true, true, false]] as $flags) {
            $this->flags(...$flags);
            [$user, $criteria] = $this->scenario();

            $response = $this->results($user, $criteria);

            $this->assertSame(['A', 'B', 'C'], $this->order($response), json_encode($flags));
            $this->assertSame(TasteRerankOutcome::INACTIVE, $response->viewData('tasteStatus'));
            $response->assertDontSee('data-taste-rerank', false);
        }
    }

    // --------------------------------------------------------- personalized

    /** @test */
    public function strong_taste_reorders_near_tied_results_under_best_match(): void
    {
        [$user, $criteria] = $this->scenario();

        $response = $this->results($user, $criteria);

        $this->assertSame(['B', 'A', 'C'], $this->order($response));
        $this->assertSame(TasteRerankOutcome::PERSONALIZED, $response->viewData('tasteStatus'));

        $response->assertSee('data-taste-rerank="personalized"', false);
        $response->assertSee('Personalized with', false);
        $response->assertSee('Show standard Best Match order', false);
        $response->assertSee('Natural Light is a reason you have picked when Saving homes.', false);
    }

    /** @test */
    public function scores_membership_and_total_are_identical_with_personalization_on_and_off(): void
    {
        [$user, $criteria] = $this->scenario();

        $on = $this->results($user, $criteria);

        $this->flags(true, true, false);
        $off = $this->results($user, $criteria);

        $this->assertNotSame($this->order($on), $this->order($off), 'the fixture must actually reorder');
        $this->assertSame($this->scores($off), $this->scores($on), 'every listing keeps its exact base score');
        $this->assertSame($off->viewData('total'), $on->viewData('total'));
        $this->assertEqualsCanonicalizing(
            array_column($off->viewData('mapPins'), 'id'),
            array_column($on->viewData('mapPins'), 'id'),
            'map membership is unchanged'
        );
    }

    /** @test */
    public function a_materially_better_match_is_not_leapfrogged_through_the_page(): void
    {
        [$user, $criteria] = $this->scenario(aScore: 93, bScore: 90);

        $this->assertSame(['A', 'B', 'C'], $this->order($this->results($user, $criteria)));
    }

    // ------------------------------------------------------------ bypasses

    /** @test */
    public function standard_best_match_is_one_click_away_and_restores_the_exact_order(): void
    {
        [$user, $criteria] = $this->scenario();

        $response = $this->results($user, $criteria, ['taste' => 'off']);

        $this->assertSame(['A', 'B', 'C'], $this->order($response));
        $this->assertSame(TasteRerankOutcome::OPTED_OUT, $response->viewData('tasteStatus'));
        $response->assertSee('Personalize with Your Home Taste', false);
        $response->assertDontSee('data-taste-rerank-explanation', false);

        $urls = $response->viewData('tasteToggleUrls');
        $this->assertStringContainsString('taste=off', $urls['standard']);
        $this->assertStringNotContainsString('taste=', $urls['personalized']);
        $this->assertStringContainsString('criteria_id=' . $criteria, $urls['personalized']);
    }

    /**
     * Stellar results offer NO sort control today (audited: only criteria_type,
     * criteria_id and page are read). Any explicit sort a request carries —
     * including the names the architecture doc plans — is left untouched.
     *
     * @test
     */
    public function any_explicit_sort_bypasses_taste_entirely(): void
    {
        [$user, $criteria] = $this->scenario();

        foreach (['price_asc', 'price_desc', 'newest', 'oldest', 'distance', 'best_price_fit'] as $sort) {
            $response = $this->results($user, $criteria, ['sort' => $sort]);

            $this->assertSame(['A', 'B', 'C'], $this->order($response), $sort);
            $this->assertSame(TasteRerankOutcome::EXPLICIT_SORT, $response->viewData('tasteStatus'));
            $response->assertDontSee('data-taste-rerank', false);
        }

        // Best Match named explicitly is still Best Match.
        $this->assertSame(['B', 'A', 'C'], $this->order($this->results($user, $criteria, ['sort' => 'best_match'])));
    }

    // --------------------------------------------------------- no evidence

    /** @test */
    public function no_profile_or_insufficient_evidence_leaves_the_order_alone(): void
    {
        [$user, $criteria] = $this->scenario(historyHomes: 0);
        $response = $this->results($user, $criteria);
        $this->assertSame(['A', 'B', 'C'], $this->order($response));
        $this->assertSame(TasteRerankOutcome::NO_SIGNALS, $response->viewData('tasteStatus'));
        $response->assertDontSee('data-taste-rerank', false);

        // One Save is one choice, never a ranking signal.
        [$user, $criteria] = $this->scenario(historyHomes: 1);
        $this->assertSame(['A', 'B', 'C'], $this->order($this->results($user, $criteria)));
    }

    // ------------------------------------------------------------ isolation

    /** @test */
    public function another_customers_taste_never_reorders_my_results(): void
    {
        [$other, ] = $this->scenario();
        [$me, $criteria] = $this->scenario(historyHomes: 0);

        $this->assertNotSame($other->id, $me->id);
        $this->assertSame(['A', 'B', 'C'], $this->order($this->results($me, $criteria)));
    }

    /** @test */
    public function an_agent_viewing_a_client_search_gets_the_standard_order(): void
    {
        [$client, $criteria] = $this->scenario();

        $agent = User::factory()->create(['user_type' => 'agent']);
        DB::table('user_agents')->insert(['agent_id' => $agent->id, 'user_id' => $client->id, 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->results($agent, $criteria);

        $this->assertSame(['A', 'B', 'C'], $this->order($response));
        $this->assertSame(TasteRerankOutcome::INACTIVE, $response->viewData('tasteStatus'));
    }

    /** @test */
    public function rental_taste_never_reorders_purchase_results_and_vice_versa(): void
    {
        // Strong TENANT history, viewing BUYER results: nothing moves.
        [$user, $criteria] = $this->scenario(historyRole: SeekerRole::Tenant);
        $this->assertSame(['A', 'B', 'C'], $this->order($this->results($user, $criteria)));
    }

    /** @test */
    public function a_tenant_is_personalized_on_rental_results_from_rental_history(): void
    {
        [$user, $criteria] = $this->scenario(role: SeekerRole::Tenant);

        $response = $this->results($user, $criteria, ['criteria_type' => 'tenant_offer']);

        $this->assertSame(['B', 'A', 'C'], $this->order($response));
        $this->assertSame(TasteRerankOutcome::PERSONALIZED, $response->viewData('tasteStatus'));
    }

    /** @test */
    public function a_buyer_viewing_rental_criteria_is_not_personalized(): void
    {
        // Market is decided by the criteria; the viewer's role must agree.
        [$user, ] = $this->scenario();
        $tenantCriteria = $this->tenantCriteria($user);

        $response = $this->results($user, $tenantCriteria, ['criteria_type' => 'tenant_offer']);

        $this->assertSame(['A', 'B', 'C'], $this->order($response));
        $this->assertSame(TasteRerankOutcome::INACTIVE, $response->viewData('tasteStatus'));
    }

    // ------------------------------------------------------------- identity

    /** @test */
    public function one_home_seen_as_bridge_and_as_its_byo_listing_counts_once(): void
    {
        // Three history homes → established → B (90) passes A (91). The same
        // three choices where one home is ALSO saved through its MLS-linked BYO
        // listing are still three homes, never four.
        [$user, $criteria, $history] = $this->scenario();
        $this->saveLinkedByo($user, $history[0]);

        $this->assertSame(['B', 'A', 'C'], $this->order($this->results($user, $criteria)));

        // Two distinct homes, one of them saved through BOTH surfaces, is two
        // homes: emerging, which cannot cross a one-point gap.
        [$user, $criteria, $history] = $this->scenario(historyHomes: 2);
        $this->saveLinkedByo($user, $history[0]);

        $this->assertSame(['A', 'B', 'C'], $this->order($this->results($user, $criteria)));
    }

    /** @test */
    public function homes_sharing_an_address_are_never_grouped_into_one(): void
    {
        [$user, $criteria] = $this->scenario(historyHomes: 3, sameAddress: true);

        // Three distinct listing keys at one street line are three homes.
        $this->assertSame(['B', 'A', 'C'], $this->order($this->results($user, $criteria)));
    }

    // ------------------------------------------------------ current state

    /** @test */
    public function a_listing_the_customer_passed_is_never_hidden(): void
    {
        [$user, $criteria, , $rows] = $this->scenario();

        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::Bridge, (int) $rows['C']->id),
            ListingPreferenceState::Pass, [],
        );

        $order = $this->order($this->results($user, $criteria));

        $this->assertContains('C', $order);
        $this->assertCount(3, $order);
    }

    // ----------------------------------------------------------- pagination

    /** @test */
    public function pagination_personalizes_across_pages_without_losing_or_duplicating_listings(): void
    {
        // 45 listings, all tied at 80, except the personalized one sits at the
        // END of the matcher's order (page 3) and must reach page 1.
        [$user, $criteria] = $this->scenario(historyHomes: 3, candidates: 0);

        $rows = [];
        for ($i = 0; $i < 45; $i++) {
            $rows[] = [$this->bridge("P{$i}", $i === 44 ? ['natural_light', 'updated_kitchen'] : []), 80];
        }
        $this->stubMatcher($rows);

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $response = $this->results($user, $criteria, ['page' => $page]);
            $this->assertSame(45, $response->viewData('total'));
            $seen = array_merge($seen, $this->order($response));

            if ($page === 1) {
                $this->assertSame('P44', $this->order($response)[0], 'page 1 is the top of the personalized order');
            }
        }

        $this->assertCount(45, $seen);
        $this->assertCount(45, array_unique($seen), 'no listing appears on two pages');
        $this->assertEqualsCanonicalizing(array_map(fn ($i) => "P{$i}", range(0, 44)), $seen, 'no listing disappears');
    }

    // ---------------------------------------------------------- performance

    /** @test */
    public function query_count_does_not_grow_with_the_number_of_results(): void
    {
        $count = function (int $candidates): int {
            [$user, $criteria] = $this->scenario(candidates: 0);

            $rows = [];
            for ($i = 0; $i < $candidates; $i++) {
                $rows[] = [$this->bridge("Q{$candidates}-{$i}", $i % 2 === 0 ? ['natural_light'] : []), 80];
            }
            $this->stubMatcher($rows);
            $this->app->forgetInstance(\App\Support\ListingPreferences\ListingPreferencePrefetch::class);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $response = $this->results($user, $criteria);
            DB::disableQueryLog();

            $this->assertSame(TasteRerankOutcome::PERSONALIZED, $response->viewData('tasteStatus'));

            return count(DB::getQueryLog());
        };

        $this->assertSame($count(10), $count(150), 'reranking 150 results costs the same queries as reranking 10');
    }

    // ============================================================== helpers

    private function flags(bool $prefs, bool $taste, bool $rerank): void
    {
        config()->set('listing_preferences.enabled', $prefs);
        config()->set('listing_preferences.taste_dna_enabled', $taste);
        config()->set('listing_preferences.taste_reranking_enabled', $rerank);
    }

    /**
     * A customer with a Taste history and a criteria record, and three results:
     * A (aScore, no tags), B (bScore, natural light + updated kitchen), C (70).
     *
     * @return array{0: User, 1: int, 2: list<BridgeProperty>, 3: array<string, BridgeProperty>}
     */
    private function scenario(
        int $aScore = 91,
        int $bScore = 90,
        int $historyHomes = 3,
        SeekerRole $role = SeekerRole::Buyer,
        ?SeekerRole $historyRole = null,
        bool $sameAddress = false,
        int $candidates = 3,
    ): array {
        $user     = User::factory()->create(['user_type' => $role->value]);
        $criteria = $role === SeekerRole::Tenant ? $this->tenantCriteria($user) : $this->buyerCriteria($user);
        $lease    = $role === SeekerRole::Tenant;

        $history = [];
        for ($i = 0; $i < $historyHomes; $i++) {
            $home = $this->bridge('H' . $i, [], $lease, $sameAddress ? '1 Same Street' : null);
            app(ListingPreferenceWriter::class)->setState(
                (int) $user->id, $historyRole ?? $role,
                new SmartTagListingRef(SmartTagListingType::Bridge, (int) $home->id),
                ListingPreferenceState::Save, ['natural_light', 'updated_kitchen'],
            );
            $history[] = $home;
        }

        $rows = [];
        if ($candidates > 0) {
            $rows = [
                'A' => $this->bridge('A', [], $lease),
                'B' => $this->bridge('B', ['natural_light', 'updated_kitchen'], $lease),
                'C' => $this->bridge('C', ['natural_light'], $lease),
            ];
            $this->stubMatcher([[$rows['A'], $aScore], [$rows['B'], $bScore], [$rows['C'], 70]]);
        }

        return [$user, $criteria, $history, $rows];
    }

    /** @param list<string> $tags */
    private function bridge(string $label, array $tags, bool $lease = false, ?string $street = null): BridgeProperty
    {
        $this->n++;

        $row = BridgeProperty::create([
            'provider'                => 'stellar_bridge',
            'listing_key'             => "TR-{$label}-{$this->n}-" . uniqid(),
            'listing_id'              => "TRL-{$this->n}-" . uniqid(),
            'standard_status'         => 'Active',
            'property_type'           => $lease ? 'Residential Lease' : 'Residential',
            'list_price'              => $lease ? 2000 : 400000,
            'city'                    => 'Orlando',
            'state_or_province'       => 'FL',
            'unparsed_address'        => $street ?? "{$this->n} Rerank Road",
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'raw_json'                => json_encode(['IDXParticipationYN' => true, 'LeaseAmountFrequency' => 'Monthly']),
        ]);

        // The label rides on listing_id's prefix for readable assertions.
        $row->forceFill(['listing_id' => $label])->save();

        foreach ($tags as $tag) {
            SmartTagAssignment::create([
                'listing_type' => 'bridge', 'listing_id' => $row->id, 'tag_key' => $tag,
                'context' => $lease ? 'residential.lease' : 'residential.sale', 'state' => 'present', 'winning_source' => 'structured_mls',
            ]);
        }

        return $row;
    }

    /** Pin the matcher's output: these rows, these scores, in this order. */
    private function stubMatcher(array $rows): void
    {
        $results = collect(array_map(static fn (array $r): BuyerMatchResult => new BuyerMatchResult(
            listingKey:     (string) $r[0]->listing_key,
            totalScore:     $r[1],
            categoryScores: ['location' => 0, 'price' => 0, 'size' => 0, 'property_type' => 0, 'amenities' => 0, 'financial' => 0, 'lifestyle' => 0, 'non_residential' => 0],
            listing:        $r[0],
        ), $rows));

        $this->app->instance(BuyerMatchService::class, new class($results) extends BuyerMatchService {
            public function __construct(private Collection $stub)
            {
            }

            public function match(BuyerCriteriaPayload $criteria, int $candidateCap = 200, string $role = 'buyer'): Collection
            {
                return $this->stub;
            }
        });
    }

    private function saveLinkedByo(User $user, BridgeProperty $home): void
    {
        $byo = SellerAgentAuction::create(['user_id' => 904000, 'address' => 'Linked', 'is_approved' => 1, 'is_draft' => false, 'is_archived' => 0]);
        SellerAgentAuctionMeta::create(['seller_agent_auction_id' => $byo->id, 'meta_key' => 'property_type', 'meta_value' => 'Residential']);
        SellerAgentAuctionMeta::create(['seller_agent_auction_id' => $byo->id, 'meta_key' => MlsQuickImportDraftWriter::META_LISTING_KEY, 'meta_value' => $home->listing_key]);

        app(ListingPreferenceWriter::class)->setState(
            (int) $user->id, SeekerRole::Buyer,
            new SmartTagListingRef(SmartTagListingType::SellerAgent, (int) $byo->id),
            ListingPreferenceState::Save, ['natural_light', 'updated_kitchen'],
        );
    }

    private function buyerCriteria(User $user): int
    {
        $id = DB::table('buyer_agent_auctions')->insertGetId([
            'user_id' => $user->id, 'title' => 'Rerank', 'is_approved' => 'true', 'is_sold' => 'false',
            'is_paid' => '0', 'is_draft' => false, 'referral_locked' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $auction = BuyerAgentAuction::findOrFail($id);
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'residential', 'preferred_cities' => json_encode(['Orlando']),
            'maximum_budget' => '600000', 'bedrooms' => '2', 'bathrooms' => '1'] as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        return $id;
    }

    private function tenantCriteria(User $user): int
    {
        $id = DB::table('tenant_agent_auctions')->insertGetId([
            'user_id' => $user->id, 'is_approved' => true, 'is_draft' => false, 'is_sold' => false,
            'auction_ended' => false, 'referral_locked' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $auction = TenantAgentAuction::findOrFail($id);
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'Residential Property', 'cities' => json_encode(['Orlando']),
            'maximum_budget' => '3000', 'bedrooms' => '2', 'bathrooms' => '1'] as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        return $id;
    }

    private function results(User $user, int $criteria, array $query = [])
    {
        $query += ['criteria_type' => 'buyer_offer', 'criteria_id' => $criteria];

        $this->app->forgetInstance(\App\Support\ListingPreferences\ListingPreferencePrefetch::class);

        $response = $this->actingAs($user)->get(route('stellar.buyer.results', $query));
        $response->assertOk();

        return $response;
    }

    /** @return list<string> the rendered page's listing labels, in order */
    private function order($response): array
    {
        $results = $response->viewData('results') ?? [];

        $labels = BridgeProperty::query()
            ->whereIn('listing_key', array_column($results, 'listing_key'))
            ->pluck('listing_id', 'listing_key');

        return array_map(static fn (array $card): string => (string) $labels[$card['listing_key']], $results);
    }

    /** @return array<string, int> listing label => base score */
    private function scores($response): array
    {
        $out = [];
        foreach ($this->order($response) as $i => $label) {
            $out[$label] = (int) $response->viewData('results')[$i]['total_score'];
        }
        ksort($out);

        return $out;
    }
}
