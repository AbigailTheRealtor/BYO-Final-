<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\ListingPreference;
use App\Models\ListingPreferenceEvent;
use App\Models\ListingPreferenceReason;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The feature is OFF by default, and off means nothing happens.
 *
 * PHASE 2 REWROTE THIS FILE. Phase 1 asserted the subsystem was inert in every
 * respect; Phase 2 wires a real surface, so what must now be proven is narrower
 * and more important: **the shipped default is still off, and off is still
 * completely inert** — no route, no control, no write.
 */
class ListingPreferenceInertnessTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * The shipped default. Nothing in Phase 2 changed it, and a deployment that
     * supplies no variable gets no feature.
     *
     * @test
     */
    public function the_feature_ships_off(): void
    {
        $shipped = require config_path('listing_preferences.php');

        $this->assertFalse($shipped['enabled'], 'LISTING_PREFERENCES_ENABLED must default off');
        $this->assertFalse($shipped['guest_capture_enabled'], 'guest capture is a decided no, not a dial');
        $this->assertFalse($shipped['learning_enabled'], 'behavioural learning stays gated on its governance revision');
        $this->assertFalse($shipped['taste_dna_enabled'], 'LISTING_PREFERENCE_TASTE_DNA_ENABLED must default off');
        $this->assertFalse($shipped['taste_reranking_enabled'], 'LISTING_PREFERENCE_TASTE_RERANKING_ENABLED must default off');
    }

    /** @test */
    public function no_preference_rows_exist_by_default(): void
    {
        $this->assertSame(0, ListingPreference::count());
        $this->assertSame(0, ListingPreferenceReason::count());
        $this->assertSame(0, ListingPreferenceEvent::count());
    }

    /**
     * With the flag off the routes 404 and no control renders — the two halves
     * of "off", proven together, because a rendered control whose endpoint 404s
     * is worse than no control.
     *
     * @test
     */
    public function off_means_no_endpoint_and_no_control(): void
    {
        config()->set('listing_preferences.enabled', false);

        $user    = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->sellerListing();

        $this->actingAs($user)
            ->postJson('/listing-preferences', [
                'listing_type' => 'seller_agent',
                'listing_id'   => $listing->id,
                'state'        => 'save',
            ])
            ->assertNotFound();

        $html = \Illuminate\Support\Facades\Blade::render(
            '<x-listing-preference.control listing-type="seller_agent" :listing-id="$id" />',
            ['id' => $listing->id]
        );

        $this->assertSame('', trim($html));
        $this->assertSame(0, ListingPreference::count());
        $this->assertSame(0, ListingPreferenceEvent::count());
    }

    /**
     * Behavioural learning is not merely unbuilt — turning its flag on must
     * still do nothing, because there is no learner to start.
     *
     * @test
     */
    public function enabling_the_learning_flag_starts_nothing(): void
    {
        config()->set('listing_preferences.enabled', true);
        config()->set('listing_preferences.learning_enabled', true);

        $user    = User::factory()->create(['user_type' => 'buyer']);
        $listing = $this->sellerListing();

        $this->actingAs($user)
            ->postJson('/listing-preferences', [
                'listing_type' => 'seller_agent',
                'listing_id'   => $listing->id,
                'state'        => 'save',
                'reasons'      => ['updated_kitchen'],
            ])
            ->assertOk();

        // A preference was captured…
        $this->assertSame(1, ListingPreference::count());

        // …and nothing was derived from it. dna_scores is the store a learner
        // would write to, and it stays empty.
        $this->assertSame(0, \DB::table('dna_scores')->count());
    }

    /** @test */
    public function guest_capture_remains_impossible_even_if_its_flag_is_flipped(): void
    {
        config()->set('listing_preferences.enabled', true);
        config()->set('listing_preferences.guest_capture_enabled', true);

        $listing = $this->sellerListing();

        $this->postJson('/listing-preferences', [
            'listing_type' => 'seller_agent',
            'listing_id'   => $listing->id,
            'state'        => 'save',
        ])->assertStatus(401);

        $this->assertSame(0, ListingPreference::count(), 'no anonymous row, whatever the flag says');
    }

    /**
     * Every route is behind the feature gate — none was added outside the
     * middleware group by accident.
     *
     * @test
     */
    public function every_listing_preference_route_is_feature_gated(): void
    {
        $found = [];

        foreach (Route::getRoutes() as $route) {
            // Matches ListingPreferenceController AND the Phase 3B
            // MyListingPreferencesController — the management area is gated too.
            if (! str_contains((string) $route->getActionName(), 'ListingPreferencesController')
                && ! str_contains((string) $route->getActionName(), 'ListingPreferenceController')) {
                continue;
            }

            $found[] = (string) $route->getName();
            $this->assertContains(
                'listing-preferences',
                $route->gatherMiddleware(),
                "{$route->uri()} is not feature-gated"
            );
            $this->assertContains('auth', $route->gatherMiddleware(), "{$route->uri()} is not authenticated");
        }

        sort($found);

        // Phase 3B: the same four endpoints once per SURFACE (the surface is a
        // route default, never a request field), plus the customer's own
        // read-only management pages. Nothing else.
        $expected = [];
        foreach (['listing-preferences.', 'listing-preferences.account.', 'listing-preferences.virtual-drive.'] as $prefix) {
            foreach (['show', 'store', 'reasons', 'destroy'] as $action) {
                $expected[] = $prefix . $action;
            }
        }
        $expected[] = 'listing-preferences.mine.index';
        $expected[] = 'listing-preferences.mine.history';
        // Phase 4: "Your Home Taste", read-only, behind a SECOND gate as well.
        $expected[] = 'listing-preferences.mine.taste';
        sort($expected);

        $this->assertSame($expected, $found, 'expected exactly the per-surface endpoints and the management pages');
    }

    /**
     * The flags govern a customer-facing write path, which makes them safety
     * switches — and the deploy contract may never name one.
     *
     * @test
     */
    public function the_flags_are_not_in_the_required_production_flags_contract(): void
    {
        $contract = json_encode(config('required_production_flags', []));

        $this->assertStringNotContainsString('listing_preferences', (string) $contract);
        $this->assertStringNotContainsString('LISTING_PREFERENCES', (string) $contract);
        // Phase 4's env name is singular, so the line above cannot see it.
        $this->assertStringNotContainsString('LISTING_PREFERENCE_TASTE_DNA', (string) $contract);
        $this->assertStringNotContainsString('taste_dna', (string) $contract);
        $this->assertStringNotContainsString('LISTING_PREFERENCE_TASTE_RERANKING', (string) $contract);
        $this->assertStringNotContainsString('taste_reranking', (string) $contract);
    }

    private function sellerListing(): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id' => 900400,
            'address' => '15 Inert Way, St. Petersburg, FL 33701',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'property_type',
            'meta_value'              => 'Residential',
        ]);

        return $auction;
    }
}
