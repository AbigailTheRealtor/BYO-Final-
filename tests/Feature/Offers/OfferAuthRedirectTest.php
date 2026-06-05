<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
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
}
