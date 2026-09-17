<?php

namespace Tests\Feature\ListingPreferences;

use App\Models\ListingPreference;
use App\Models\ListingPreferenceEvent;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The HTTP boundary: the feature flag, authentication, and everything the
 * browser is not allowed to decide.
 */
class ListingPreferenceHttpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('listing_preferences.enabled', true);
    }

    // ── feature flag ────────────────────────────────────────────────────────

    /**
     * OFF means the routes do not exist — a disabled feature is invisible, not
     * an endpoint that refuses.
     *
     * @test
     */
    public function every_route_404s_while_the_feature_is_off(): void
    {
        config()->set('listing_preferences.enabled', false);

        $user    = $this->buyer();
        $listing = $this->sellerListing();

        $this->actingAs($user)
            ->postJson('/listing-preferences', $this->payload($listing) + ['state' => 'save'])
            ->assertNotFound();

        $this->actingAs($user)
            ->deleteJson('/listing-preferences', $this->payload($listing))
            ->assertNotFound();

        $this->assertSame(0, ListingPreference::count());
        $this->assertSame(0, ListingPreferenceEvent::count(), 'a disabled feature writes nothing');
    }

    /** @test */
    public function the_flag_is_fail_closed_for_a_malformed_value(): void
    {
        config()->set('listing_preferences.enabled', 'yes-please');

        $this->actingAs($this->buyer())
            ->postJson('/listing-preferences', $this->payload($this->sellerListing()) + ['state' => 'save'])
            ->assertNotFound();
    }

    // ── authentication ──────────────────────────────────────────────────────

    /** @test */
    public function a_guest_write_is_refused_and_creates_nothing(): void
    {
        $listing = $this->sellerListing();

        $this->postJson('/listing-preferences', $this->payload($listing) + ['state' => 'save'])
            ->assertStatus(401);

        $this->assertSame(0, ListingPreference::count(), 'no anonymous preference row is ever created');
        $this->assertSame(0, ListingPreferenceEvent::count());
    }

    /** @test */
    public function an_account_that_is_not_a_seeker_is_refused(): void
    {
        $agent   = User::factory()->create(['user_type' => 'agent']);
        $listing = $this->sellerListing();

        $this->actingAs($agent)
            ->postJson('/listing-preferences', $this->payload($listing) + ['state' => 'save'])
            ->assertStatus(403)
            ->assertJsonPath('reason', 'not_a_buyer_or_tenant');

        $this->assertSame(0, ListingPreference::count());
    }

    /**
     * A buyer does not express rental preferences. The listing's own context
     * decides, resolved server-side.
     *
     * @test
     */
    public function a_buyer_is_refused_on_a_lease_listing(): void
    {
        $landlord = $this->landlordListing();

        $this->actingAs($this->buyer())
            ->postJson('/listing-preferences', [
                'listing_type' => 'landlord_agent',
                'listing_id'   => $landlord->id,
                'state'        => 'save',
            ])
            ->assertStatus(403)
            ->assertJsonPath('reason', 'seeker_role_does_not_cover_this_listing');
    }

    /**
     * CSRF and session protection.
     *
     * Asserted STRUCTURALLY rather than by expecting a 419: Laravel's
     * VerifyCsrfToken short-circuits whenever `runningUnitTests()` is true, so
     * an in-process request can never produce one and a test that expected it
     * would be proving something about the test harness, not the route.
     *
     * What actually matters is that every write sits in the `web` group — which
     * carries VerifyCsrfToken, StartSession and the encrypted-cookie stack —
     * behind `auth`, and is not in the CSRF exception list.
     *
     * @test
     */
    public function the_write_routes_sit_behind_web_csrf_and_auth(): void
    {
        $writes = ['store' => 'POST', 'reasons' => 'POST', 'destroy' => 'DELETE'];

        foreach ($writes as $name => $method) {
            $route = \Illuminate\Support\Facades\Route::getRoutes()
                ->getByName("listing-preferences.{$name}");

            $this->assertNotNull($route, "listing-preferences.{$name} is not registered");
            $this->assertContains($method, $route->methods());

            $middleware = $route->gatherMiddleware();

            $this->assertContains('web', $middleware, "{$name} must be in the web group (CSRF + session)");
            $this->assertContains('auth', $middleware, "{$name} must require authentication");
            $this->assertContains('listing-preferences', $middleware, "{$name} must be feature-gated");
            $this->assertContains(
                'throttle:listing-preference-write',
                $middleware,
                "{$name} must be rate limited"
            );
        }

        // Nothing here is excused from CSRF.
        $except = (new \ReflectionClass(\App\Http\Middleware\VerifyCsrfToken::class))
            ->newInstanceWithoutConstructor();
        $property = (new \ReflectionClass($except))->getProperty('except');
        $property->setAccessible(true);

        foreach ((array) $property->getValue($except) as $pattern) {
            $this->assertStringNotContainsString('listing-preference', (string) $pattern);
        }
    }

    // ── nothing is trusted from the browser ─────────────────────────────────

    /** @test */
    public function the_subject_key_cannot_be_supplied_by_the_request(): void
    {
        $mine     = $this->sellerListing();
        $listing  = $this->sellerListing();

        $this->actingAs($this->buyer())
            ->postJson('/listing-preferences', [
                'listing_type' => 'seller_agent',
                'listing_id'   => $listing->id,
                'state'        => 'save',
                // An attempt to point the preference at another property.
                'subject_key'  => "byo:seller_agent:{$mine->id}",
            ])
            ->assertOk();

        $stored = ListingPreference::first();

        $this->assertSame(
            "byo:seller_agent:{$listing->id}",
            $stored->subject_key,
            'the subject key is derived from the listing, never accepted from the request'
        );
    }

    /** @test */
    public function the_seeker_role_cannot_be_supplied_by_the_request(): void
    {
        $listing = $this->sellerListing();

        $this->actingAs($this->buyer())
            ->postJson('/listing-preferences', $this->payload($listing) + [
                'state'       => 'save',
                'seeker_role' => 'tenant',
            ])
            ->assertOk();

        $this->assertSame('buyer', ListingPreference::first()->seeker_role);
    }

    /** @test */
    public function the_user_id_cannot_be_supplied_by_the_request(): void
    {
        $me    = $this->buyer();
        $other = $this->buyer();
        $listing = $this->sellerListing();

        $this->actingAs($me)
            ->postJson('/listing-preferences', $this->payload($listing) + [
                'state'   => 'save',
                'user_id' => $other->id,
            ])
            ->assertOk();

        $this->assertSame((int) $me->id, (int) ListingPreference::first()->user_id);
        $this->assertSame(0, ListingPreference::where('user_id', $other->id)->count());
    }

    /** @test */
    public function one_user_cannot_mutate_another_users_preference(): void
    {
        $owner   = $this->buyer();
        $other   = $this->buyer();
        $listing = $this->sellerListing();

        $this->actingAs($owner)
            ->postJson('/listing-preferences', $this->payload($listing) + ['state' => 'save'])
            ->assertOk();

        // The other account clearing "the same listing" touches only its own
        // (non-existent) preference.
        $this->actingAs($other)
            ->deleteJson('/listing-preferences', $this->payload($listing))
            ->assertOk();

        $this->assertSame(1, ListingPreference::where('user_id', $owner->id)->count());
        $this->assertSame('save', ListingPreference::where('user_id', $owner->id)->first()->state);
    }

    /** @test */
    public function an_unknown_listing_type_is_rejected_by_validation(): void
    {
        $this->actingAs($this->buyer())
            ->postJson('/listing-preferences', [
                'listing_type' => 'buyer_agent',
                'listing_id'   => 1,
                'state'        => 'save',
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function an_unknown_state_is_rejected_by_validation(): void
    {
        $this->actingAs($this->buyer())
            ->postJson('/listing-preferences', $this->payload($this->sellerListing()) + ['state' => 'cleared'])
            ->assertStatus(422);
    }

    // ── the happy paths, over HTTP ──────────────────────────────────────────

    /** @test */
    public function state_reasons_and_clear_work_end_to_end(): void
    {
        $user    = $this->buyer();
        $listing = $this->sellerListing();

        $this->actingAs($user)
            ->postJson('/listing-preferences', $this->payload($listing) + ['state' => 'save'])
            ->assertOk()
            ->assertJsonPath('state', 'save');

        $this->actingAs($user)
            ->postJson('/listing-preferences/reasons', $this->payload($listing) + [
                'reasons' => ['updated_kitchen', 'natural_light', 'accessible_features'],
            ])
            ->assertOk()
            ->assertJsonPath('reasons.0.key', 'updated_kitchen')
            ->assertJsonPath('reasons.1.key', 'natural_light')
            // Refused and REPORTED, never silently dropped. `accessible_features`
            // is a Smart Tag key with deliberately no reason chip, so it is not
            // in the vocabulary at all — a stronger refusal than being an
            // ineligible chip, and it never reaches storage either way.
            ->assertJsonPath('rejected.accessible_features', 'unknown_reason_key');

        $this->actingAs($user)
            ->getJson('/listing-preferences?' . http_build_query($this->payload($listing)))
            ->assertOk()
            ->assertJsonPath('state', 'save');

        $this->actingAs($user)
            ->deleteJson('/listing-preferences', $this->payload($listing))
            ->assertOk()
            ->assertJsonPath('state', null)
            ->assertJsonPath('cleared', true);

        $this->assertSame(0, ListingPreference::count());
        $this->assertNull(ListingPreferenceEvent::orderByDesc('id')->first()->to_state);
    }

    /** @test */
    public function reasons_for_a_listing_with_no_current_state_are_refused(): void
    {
        $this->actingAs($this->buyer())
            ->postJson('/listing-preferences/reasons', $this->payload($this->sellerListing()) + [
                'reasons' => ['updated_kitchen'],
            ])
            ->assertStatus(409);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function buyer(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function payload(SellerAgentAuction $listing): array
    {
        return ['listing_type' => 'seller_agent', 'listing_id' => $listing->id];
    }

    private function sellerListing(): SellerAgentAuction
    {
        $auction = SellerAgentAuction::create([
            'user_id' => 900200,
            'address' => '11 Http Way, St. Petersburg, FL 33701',
        ]);

        SellerAgentAuctionMeta::create([
            'seller_agent_auction_id' => $auction->id,
            'meta_key'                => 'property_type',
            'meta_value'              => 'Residential',
        ]);

        return $auction;
    }

    private function landlordListing(): \App\Models\LandlordAgentAuction
    {
        // Landlord listings store their detail via EAV meta, not native
        // columns — the documented schema asymmetry.
        $auction = \App\Models\LandlordAgentAuction::create(['user_id' => 900201]);

        \App\Models\LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'property_type',
            'meta_value'                => 'Residential Property',
        ]);

        return $auction;
    }
}
