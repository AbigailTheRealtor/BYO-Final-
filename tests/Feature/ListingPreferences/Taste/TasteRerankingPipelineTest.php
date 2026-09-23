<?php

namespace Tests\Feature\ListingPreferences\Taste;

use App\Models\BridgeProperty;
use App\Models\BuyerAgentAuction;
use App\Models\SmartTagAssignment;
use App\Models\User;
use App\Services\ListingPreferences\ListingPreferenceWriter;
use App\Services\ListingPreferences\Taste\TasteRerankOutcome;
use App\Support\ListingPreferences\ListingPreferencePrefetch;
use App\Support\ListingPreferences\ListingPreferenceState;
use App\Support\ListingPreferences\SeekerRole;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 100-point Match DNA score is IDENTICAL with personalization on and off —
 * proven through the REAL matcher, scorer and result builder, not a stub.
 *
 * Seeded so the matcher produces genuine ties that Taste then breaks, which is
 * what makes "the scores did not move" meaningful: the order DID move.
 */
class TasteRerankingPipelineTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function the_real_matchers_scores_and_membership_are_identical_on_and_off(): void
    {
        config()->set('listing_preferences.enabled', true);
        config()->set('listing_preferences.taste_dna_enabled', true);

        $user     = User::factory()->create(['user_type' => 'buyer']);
        $criteria = $this->buyerCriteria($user);

        // Six identical listings: the matcher scores them the same, so its order
        // among them is the tie order. The LAST one carries the tags the
        // customer Saves for.
        $keys = [];
        for ($i = 0; $i < 6; $i++) {
            $row    = $this->listing("PIPE-{$i}");
            $keys[] = $row->listing_key;

            if ($i === 5) {
                foreach (['natural_light', 'updated_kitchen'] as $tag) {
                    SmartTagAssignment::create([
                        'listing_type' => 'bridge', 'listing_id' => $row->id, 'tag_key' => $tag,
                        'context' => 'residential.sale', 'state' => 'present', 'winning_source' => 'structured_mls',
                    ]);
                }
            }
        }

        // A different listing, scored lower, so the list is not all ties.
        $this->listing('PIPE-LOW', ['list_price' => 590000, 'bedrooms_total' => 2]);

        // Taste history on three other homes outside the search area.
        for ($i = 0; $i < 3; $i++) {
            $home = $this->listing("HIST-{$i}", ['city' => 'Nowhere']);
            app(ListingPreferenceWriter::class)->setState(
                (int) $user->id, SeekerRole::Buyer,
                new SmartTagListingRef(SmartTagListingType::Bridge, (int) $home->id),
                ListingPreferenceState::Save, ['natural_light', 'updated_kitchen'],
            );
        }

        config()->set('listing_preferences.taste_reranking_enabled', false);
        $off = $this->results($user, $criteria);

        config()->set('listing_preferences.taste_reranking_enabled', true);
        $on = $this->results($user, $criteria);

        $this->assertSame(TasteRerankOutcome::INACTIVE, $off->viewData('tasteStatus'));
        $this->assertSame(TasteRerankOutcome::PERSONALIZED, $on->viewData('tasteStatus'));

        $offCards = $off->viewData('results');
        $onCards  = $on->viewData('results');

        // It genuinely reordered…
        $this->assertNotSame(array_column($offCards, 'listing_key'), array_column($onCards, 'listing_key'));
        $this->assertSame($keys[5], $onCards[0]['listing_key']);

        // …and every listing kept its exact score, category bars and display.
        $this->assertSame($this->byKey($offCards), $this->byKey($onCards));
        $this->assertSame($off->viewData('total'), $on->viewData('total'));
    }

    /** @return array<string, array<string, mixed>> listing key => the score-bearing fields */
    private function byKey(array $cards): array
    {
        $out = [];
        foreach ($cards as $card) {
            $out[$card['listing_key']] = [
                'total_score'   => $card['total_score'],
                'score_display' => $card['score_display'],
                'category_bars' => $card['category_bars'],
            ];
        }
        ksort($out);

        return $out;
    }

    private function listing(string $key, array $overrides = []): BridgeProperty
    {
        return BridgeProperty::create(array_merge([
            'provider'                => 'stellar_bridge',
            'listing_key'             => $key . '-' . uniqid(),
            'listing_id'              => $key,
            'standard_status'         => 'Active',
            'property_type'           => 'Residential',
            'list_price'              => 400000,
            'city'                    => 'Orlando',
            'state_or_province'       => 'FL',
            'postal_code'             => '32801',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'senior_community_yn'     => false,
            'raw_json'                => json_encode(['IDXParticipationYN' => true]),
        ], $overrides));
    }

    private function buyerCriteria(User $user): int
    {
        $id = DB::table('buyer_agent_auctions')->insertGetId([
            'user_id' => $user->id, 'title' => 'Pipeline', 'is_approved' => 'true', 'is_sold' => 'false',
            'is_paid' => '0', 'is_draft' => false, 'referral_locked' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $auction = BuyerAgentAuction::findOrFail($id);
        foreach (['workflow_type' => 'offer_listing', 'property_type' => 'residential', 'preferred_cities' => json_encode(['Orlando']),
            'maximum_budget' => '600000', 'bedrooms' => '2', 'bathrooms' => '1'] as $k => $v) {
            $auction->saveMeta($k, $v);
        }

        return $id;
    }

    private function results(User $user, int $criteria)
    {
        $this->app->forgetInstance(ListingPreferencePrefetch::class);

        $response = $this->actingAs($user)->get(route('stellar.buyer.results', ['criteria_type' => 'buyer_offer', 'criteria_id' => $criteria]));
        $response->assertOk();

        return $response;
    }
}
