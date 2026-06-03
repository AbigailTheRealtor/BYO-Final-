<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MyOffersDashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('offers.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_offers_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('offers.index'));

        $response->assertOk();
    }

    public function test_user_sees_only_their_own_offers(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $myOffer    = Offer::factory()->create(['user_id' => $user->id]);
        $otherOffer = Offer::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('offers.index'));

        $response->assertOk();
        $response->assertViewHas('offers', function ($offers) use ($myOffer, $otherOffer) {
            return $offers->contains('id', $myOffer->id)
                && !$offers->contains('id', $otherOffer->id);
        });
        $response->assertSee('<td>' . $myOffer->id . '</td>', false);
        $response->assertDontSee('<td>' . $otherOffer->id . '</td>', false);
    }

    public function test_offers_table_contains_required_columns(): void
    {
        $user = User::factory()->create();

        Offer::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('offers.index'));

        $response->assertOk();
        $response->assertSee('Offer ID');
        $response->assertSee('Status');
        $response->assertSee('Role');
        $response->assertSee('Parent Offer ID');
        $response->assertSee('Created At');
        $response->assertSee('Expires At');
        $response->assertSee('View Offer');
    }
}
