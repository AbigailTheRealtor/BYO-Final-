<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferAuthRedirectTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unauthenticated_request_to_offers_show_redirects_to_login(): void
    {
        $offer = Offer::factory()->create(['status' => 'draft']);

        $response = $this->get(route('offers.show', $offer));

        $response->assertRedirect(route('login'));
    }

    // ── Test: Wildcard allows any authenticated user ──────────────────────────

    public function test_wildcard_allows_any_authenticated_user_to_post_to_offers_store(): void
    {
        $user = User::factory()->create(['user_type' => 'seller']);

        $this->app['config']->set('offer.playoff_access.allowed_user_ids', '*');

        $offerAuction = OfferAuction::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->post(route('offers.store'), [
                'offer_auction_id' => $offerAuction->id,
                'role'             => 'seller',
            ]);

        $this->assertNotEquals(
            403,
            $response->status(),
            'With allowed_user_ids = "*", any authenticated user must not receive a 403.'
        );
    }

    // ── Test: Restricted list blocks non-listed user with 403 ─────────────────

    public function test_restricted_list_blocks_non_listed_user_with_403(): void
    {
        $user = User::factory()->create(['user_type' => 'seller']);

        $this->app['config']->set('offer.playoff_access.allowed_user_ids', [20]);

        $response = $this->actingAs($user)
            ->postJson(route('offers.store'), [
                'offer_auction_id' => 1,
                'role'             => 'seller',
            ]);

        $response->assertStatus(403);
    }

    // ── Test: Admin bypasses the gate regardless of allowed_user_ids ──────────

    public function test_admin_bypasses_gate_regardless_of_allowed_user_ids(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);

        $this->app['config']->set('offer.playoff_access.allowed_user_ids', [20]);

        $offerAuction = OfferAuction::factory()->create(['user_id' => $admin->id]);

        $response = $this->actingAs($admin)
            ->post(route('offers.store'), [
                'offer_auction_id' => $offerAuction->id,
                'role'             => 'seller',
            ]);

        $this->assertNotEquals(
            403,
            $response->status(),
            'Admin must bypass the offer-playoff gate regardless of allowed_user_ids.'
        );
    }
}
