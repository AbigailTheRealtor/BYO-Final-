<?php

namespace Tests\Feature\Product;

use App\Models\User;
use App\Support\Product\ProductSurfaceCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Direct HTTP access across the product boundary — the part hiding a link cannot do.
 *
 * Every assertion below is a real request through the whole middleware stack, not a
 * check of a rendered link. A refused surface answers 404 to everyone, signed in or
 * not; an allowed surface is handed on to whatever authorization it always had.
 *
 * READING THE ASSERTIONS
 * ----------------------
 *   404              the product gate refused it.
 *   302 to /login    the product gate allowed it and `auth` took over.
 * The second is the important one: it proves a Hire Agent route reached its own
 * authorization rather than being quietly swallowed by the isolation.
 */
class ProductRouteGateTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * BidYourOffer-only surfaces, one per family named in the isolation contract.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function bidYourOfferSurfaces(): array
    {
        return [
            'seller offer listing'     => ['get',  '/offer-listing/seller'],
            'buyer offer listing'      => ['get',  '/offer-listing/buyer'],
            'landlord offer listing'   => ['get',  '/offer-listing/landlord'],
            'tenant offer listing'     => ['get',  '/offer-listing/tenant'],
            'seller offer listing edit'=> ['get',  '/offer-listing/seller/edit/1'],
            // NOTE: /offer-listing/{role}/view/{id} is deliberately absent. It aborts
            // 404 on its own for a listing that does not exist, so asserting 404 there
            // would pass in BOTH products and prove nothing. The whole `offer-listing/*`
            // prefix is pinned instead by ProductSurfaceContractTest.
            'seller mls quick import'  => ['get',  '/offer-listing/seller/import-mls'],
            'browse listings'          => ['get',  '/search/properties-auctions'],
            'browse seller listings'   => ['get',  '/search/seller-listings'],
            'browse rental properties' => ['get',  '/search/rental-properties'],
            'showing requests'         => ['get',  '/my-showings/manage'],
            'showing request store'    => ['post', '/showings'],
            'mls match check'          => ['get',  '/match-check'],
            'stellar mls results'      => ['get',  '/stellar/buyer/results'],
            'legacy add listing'       => ['get',  '/add-listing'],
            'legacy property listing'  => ['get',  '/property/listing/view/1'],
            'offer playoff create'     => ['get',  '/offer/listing/sale'],
            'offer playoff offers'     => ['get',  '/offers'],
            'buyer criteria auctions'  => ['get',  '/criteria/auctions'],
            'landlord auction'         => ['get',  '/landlord/auction/view/1'],
            'tenant criteria auctions' => ['get',  '/tenant/criteria/auctions'],
        ];
    }

    /**
     * BidYourAgent surfaces that must survive the isolation untouched.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function bidYourAgentSurfaces(): array
    {
        return [
            'seller hire agent'        => ['get',  '/hire/agent/seller'],
            'seller hire agent list'   => ['get',  '/hire/agent/seller/list'],
            'seller accept bid'        => ['post', '/hire/agent/seller/bid/accept'],
            'seller reject bid'        => ['post', '/hire/agent/seller/bid/reject'],
            'buyer hire agent'         => ['get',  '/buyer/add-auction'],
            'buyer accept bid'         => ['post', '/buyer/hire/agent/auction/bid/accept'],
            'landlord hire agent'      => ['get',  '/landlord/hire/agent/auction'],
            'landlord accept bid'      => ['post', '/landlord/hire/agent/auction/bid/accept'],
            'tenant hire agent'        => ['get',  '/hire/agent/auction/tenant'],
            'tenant accept bid'        => ['post', '/tenant/hire/agent/auction/bid/accept'],
            'agent seller bid form'    => ['get',  '/agent/seller/bid/add/1'],
            'agent buyer bid form'     => ['get',  '/buyer/agent/auction/bid/1'],
            'agent landlord bid form'  => ['get',  '/landlord/agent/auction/bid/1'],
            'agent tenant bid form'    => ['get',  '/tenant/agent/auction/bid/1'],
            'agent hire listings hub'  => ['get',  '/agent/hire-listings'],
            'agent hire leads'         => ['get',  '/agent/hire-leads'],
            'hire agent marketplace'   => ['get',  '/search/seller-agent-needed'],
            'accepted bid summary'     => ['get',  '/accepted-bid-summary/1'],
            'shared account settings'  => ['get',  '/settings'],
            'shared messages'          => ['get',  '/messages'],
            'shared dashboard'         => ['get',  '/dashboard'],
        ];
    }

    /**
     * @test
     * @dataProvider bidYourOfferSurfaces
     */
    public function bidyouragent_mode_refuses_bidyouroffer_surfaces_over_http(string $verb, string $uri): void
    {
        config(['products.active' => 'bidyouragent']);

        $this->{$verb}($uri)->assertNotFound();
    }

    /**
     * @test
     * @dataProvider bidYourOfferSurfaces
     */
    public function bidyouragent_mode_refuses_them_for_a_signed_in_user_too(string $verb, string $uri): void
    {
        config(['products.active' => 'bidyouragent']);

        $user = User::factory()->create(['user_type' => 'seller']);

        $this->actingAs($user)->{$verb}($uri)->assertNotFound();
    }

    /**
     * @test
     * @dataProvider bidYourAgentSurfaces
     */
    public function bidyouragent_mode_leaves_hire_agent_surfaces_to_their_own_authorization(string $verb, string $uri): void
    {
        config(['products.active' => 'bidyouragent']);

        $response = $this->{$verb}($uri);

        $this->assertNotSame(
            404,
            $response->getStatusCode(),
            "{$verb} {$uri} was refused by the product gate. It is a BidYourAgent surface and must reach its own authorization."
        );
    }

    /**
     * @test
     * @dataProvider bidYourOfferSurfaces
     */
    public function the_default_platform_still_serves_every_bidyouroffer_surface(string $verb, string $uri): void
    {
        // The regression guard. BidYourAgent isolation must not disable BidYourOffer
        // for the deployment that serves both, which is what production serves today.
        config(['products.active' => null, 'products.hosts' => []]);

        $response = $this->{$verb}($uri);

        $this->assertNotSame(
            404,
            $response->getStatusCode(),
            "{$verb} {$uri} 404s on the combined platform. Product isolation has leaked into the default."
        );
    }

    /** @test */
    public function the_gate_refuses_and_never_grants(): void
    {
        config(['products.active' => 'bidyouragent']);

        // A Hire Agent route the gate allows is still behind auth. Product visibility
        // and authorization are separate layers, and isolation must not weaken one by
        // satisfying the other.
        $this->get('/hire/agent/seller')->assertRedirect(route('login'));
        $this->get('/agent/hire-listings')->assertRedirect(route('login'));
        $this->get('/settings')->assertRedirect(route('login'));
    }

    /** @test */
    public function an_agent_account_cannot_reach_offer_listing_bidding_in_bidyouragent_mode(): void
    {
        config(['products.active' => 'bidyouragent']);

        $agent = User::factory()->create(['user_type' => 'agent']);

        $this->actingAs($agent)->get('/agent/offer-listings')->assertNotFound();
        $this->actingAs($agent)->get('/search/seller-listings')->assertNotFound();
        $this->actingAs($agent)->get('/offer-listing/seller')->assertNotFound();
    }

    /** @test */
    public function the_location_dna_address_lookup_is_served_in_both_products(): void
    {
        // Hire Buyer / Hire Tenant (BidYourAgent) and Create Offer / criteria
        // (BidYourOffer) all reach it through the same map-input partial.
        $this->assertSame(
            ProductSurfaceCatalog::SHARED,
            ProductSurfaceCatalog::dispositionFor('POST', 'location/address-lookup')
        );

        config(['products.active' => 'bidyouragent']);
        $this->post('/location/address-lookup', ['address' => '1 Main St'])->assertRedirect(route('login'));

        config(['products.active' => null, 'products.hosts' => []]);
        $this->post('/location/address-lookup', ['address' => '1 Main St'])->assertRedirect(route('login'));
    }

    /** @test */
    public function explore_is_refused_in_bidyouragent_mode_even_with_its_own_flag_on(): void
    {
        // Explore's own flag defaulting off is what hid that it was never classified.
        // Its 404 has to come from the product gate, not from the rollout switch.
        config(['explore.enabled' => true, 'products.active' => 'bidyouragent']);

        $this->get('/explore')->assertNotFound();
        $this->get('/api/explore/listings')->assertNotFound();
        $this->get('/api/explore/listings/ABC123')->assertNotFound();

        config(['products.active' => null, 'products.hosts' => []]);

        $this->assertNotSame(404, $this->get('/explore')->getStatusCode());
    }
}
