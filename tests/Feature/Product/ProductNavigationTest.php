<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * What the rendered pages offer, per product.
 *
 * Assertions are on ROUTES, not on copy: a link is checked by the href it carries,
 * so renaming a button cannot make one of these pass or fail. The refusal side is
 * covered by ProductRouteGateTest; this class is about dead ends — a BidYourAgent
 * deployment must not show a door it will then slam.
 */
class ProductNavigationTest extends TestCase
{
    use DatabaseTransactions;

    private function bidYourAgent(): void
    {
        config(['products.active' => 'bidyouragent', 'products.hosts' => []]);
    }

    private function combined(): void
    {
        config(['products.active' => null, 'products.hosts' => []]);
    }

    /** @test */
    public function the_guest_home_page_offers_hire_agent_and_not_browse_listings(): void
    {
        $this->bidYourAgent();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee(route('searchListing'), false);
        $response->assertSee(route('register'), false);
    }

    /** @test */
    public function the_guest_home_page_is_unchanged_on_the_combined_platform(): void
    {
        $this->combined();

        $this->get('/')->assertOk()->assertSee(route('searchListing'), false);
    }

    /** @test */
    public function the_public_header_drops_browse_listings_in_bidyouragent_mode(): void
    {
        $this->bidYourAgent();

        // The header renders on every public page; the FAQ is the cheapest one to render.
        $response = $this->get('/faq');

        $response->assertOk();
        $response->assertDontSee(route('searchListing'), false);
    }

    /** @test */
    public function the_mobile_primary_action_never_points_at_the_legacy_listing_wizard(): void
    {
        $this->bidYourAgent();

        $response = $this->get('/faq');

        $response->assertOk();
        // The global "+" pointed at add-listing for everyone, signed out included.
        $response->assertDontSee(route('add-listing'), false);
        // A signed-out visitor is offered registration rather than a login wall.
        $response->assertSee(route('register'), false);
    }

    /** @test */
    public function the_combined_platform_keeps_the_legacy_plus_action(): void
    {
        $this->combined();

        $this->get('/faq')->assertOk()->assertSee(route('add-listing'), false);
    }

    /**
     * @test
     * @dataProvider consumerRoles
     */
    public function the_dashboard_offers_hire_agent_and_no_offer_listing_creation(string $userType, string $hireRoute): void
    {
        $this->bidYourAgent();

        $user     = User::factory()->create(['user_type' => $userType]);
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();

        // Gone: every Create Offer Listing entry point, and the MLS search surfaces.
        foreach (['offer.listing.seller', 'offer.listing.buyer', 'offer.listing.landlord',
                  'stellar.buyer.results'] as $name) {
            $response->assertDontSee(route($name), false);
        }

        // Still there: this role's own Hire Agent entry point.
        $response->assertSee($hireRoute, false);
    }

    /**
     * @test
     * @dataProvider consumerRoles
     */
    public function the_combined_dashboard_still_offers_offer_listing_creation(string $userType, string $hireRoute): void
    {
        $this->combined();

        $user     = User::factory()->create(['user_type' => $userType]);
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee(route('offer.listing.seller'), false);
        $response->assertSee($hireRoute, false);
    }

    /** @test */
    public function the_agent_dashboard_drops_offer_listings_and_keeps_hire_agent(): void
    {
        $this->bidYourAgent();

        $agent    = User::factory()->create(['user_type' => 'agent']);
        $response = $this->actingAs($agent)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee(route('offer.listing.seller'), false);
        $response->assertDontSee(route('stellar.buyer.results'), false);
        $response->assertSee(route('agent.hire-listings'), false);
    }

    /** @test */
    public function the_seller_sidenav_drops_property_listings_and_showing_requests(): void
    {
        $this->bidYourAgent();

        $seller = User::factory()->create(['user_type' => 'seller']);

        // My Listings renders the sidenav for a signed-in consumer.
        $response = $this->actingAs($seller)->get('/my-listings');

        $response->assertOk();
        $response->assertDontSee(route('myAuctions'), false);
        $response->assertDontSee(route('showings.manage'), false);
        $response->assertDontSee(route('offer.listing.seller.searchListing'), false);
        $response->assertSee(route('hireSellerAgentHireAuctions'), false);
    }

    /** @test */
    public function the_combined_seller_sidenav_keeps_them(): void
    {
        $this->combined();

        $seller   = User::factory()->create(['user_type' => 'seller']);
        $response = $this->actingAs($seller)->get('/my-listings');

        $response->assertOk();
        $response->assertSee(route('myAuctions'), false);
        $response->assertSee(route('showings.manage'), false);
    }

    /** @test */
    public function the_hire_agent_marketplace_search_menu_offers_no_offer_listing_tabs(): void
    {
        $this->bidYourAgent();

        // A public Hire Agent marketplace page — the one place the shared search menu
        // put the four Offer Listing tabs directly beside the Hire Agent ones.
        $response = $this->get('/search/seller-agent-needed');

        $response->assertOk();
        $response->assertDontSee(route('offer.listing.seller.searchListing'), false);
        $response->assertDontSee(route('offer.listing.landlord.searchListing'), false);
        $response->assertSee(route('seller.agent.searchListing'), false);
    }

    /** @test */
    public function the_sign_in_and_registration_bottom_bar_has_no_dead_links_and_offers_hire_agent(): void
    {
        // /register is where the guest "Hire Agent" call to action lands. On a phone,
        // both auth pages drew their own bottom bar of relative `*.html` hrefs to files
        // that do not exist — the global "+" among them — so every tap was a 404. They
        // now render the same bar, and the same product-aware "+", as the other layouts.
        $this->bidYourAgent();

        foreach (['/login', '/register'] as $uri) {
            $response = $this->get($uri);

            $response->assertOk();

            foreach (['addListing.html', 'sellerWork.html', 'sellerWorkAgent.html', 'buyerWork.html', 'buyerWorkAgent.html'] as $dead) {
                $response->assertDontSee('href="' . $dead . '"', false);
            }

            $response->assertSee('href="' . route('register') . '" class="add-listing"', false);
            $response->assertDontSee(route('add-listing'), false);
            $response->assertSee(route('sellerWorks'), false);
            $response->assertSee(route('buyerWorksAgent'), false);
        }
    }

    /** @test */
    public function the_sign_in_and_registration_plus_follows_the_combined_platform(): void
    {
        $this->combined();

        foreach (['/login', '/register'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('href="' . route('add-listing') . '" class="add-listing"', false);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function consumerRoles(): array
    {
        return [
            'seller'   => ['seller',   '/hire/agent/seller'],
            'buyer'    => ['buyer',    '/buyer/add-auction'],
            'landlord' => ['landlord', '/landlord/hire/agent/auction'],
            'tenant'   => ['tenant',   '/hire/agent/auction/tenant'],
        ];
    }
}
