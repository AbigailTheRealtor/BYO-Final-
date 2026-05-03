<?php

namespace Tests\Feature;

use App\Models\AgentDefaultProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Feature tests for the read-only Hire Me review page and counter/accept routing.
 *
 * Covers:
 *  (a) All four role preview pages render grouped bullet-point services and
 *      contain no input[type=checkbox] elements.
 *  (b) Accept POST creates listing + bid and redirects to the listing detail
 *      page, with services saved from the full preset even when services[] is
 *      absent from the POST body.
 *  (c) Counter POST creates listing + bid and redirects to the correct per-role
 *      view-counter URL containing the new bid_id.
 */
class HireAgentDirectReadOnlyReviewTest extends TestCase
{
    use DatabaseTransactions;

    private const COUNTER_ROUTES = [
        'buyer'    => 'buyer.hire.agent.auction.bid.view-counter',
        'seller'   => 'hire.seller.agent.auction.bid.view-counter',
        'landlord' => 'landlord.hire.agent.auction.bid.view-counter',
        'tenant'   => 'tenant.hire.agent.auction.bid.view-counter',
    ];

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAgent(): User
    {
        return User::factory()->asAgent()->create();
    }

    private function makeClient(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function makeProfile(
        User   $agent,
        string $role,
        string $propertyType = 'residential',
        array  $services = ['Service Alpha', 'Service Beta'],
        array  $extra = []
    ): AgentDefaultProfile {
        return AgentDefaultProfile::create([
            'user_id'       => $agent->id,
            'role_type'     => $role,
            'property_type' => $propertyType,
            'profile_data'  => array_merge(['services' => $services], $extra),
        ]);
    }

    private function previewUrl(int $agentId, string $role, string $propertyType = 'residential'): string
    {
        return route('hire.agent.direct.preview', [
            'agentId'      => $agentId,
            'role'         => $role,
            'propertyType' => $propertyType,
        ]);
    }

    private function confirmUrl(int $agentId, string $role, string $propertyType = 'residential'): string
    {
        return route('hire.agent.direct.confirm', [
            'agentId'      => $agentId,
            'role'         => $role,
            'propertyType' => $propertyType,
        ]);
    }

    /**
     * Load the preview page as a client, capture the session token, and return
     * both the preview response and the token needed for the confirm POST.
     */
    private function loadPreview(User $client, int $agentId, string $role, string $propertyType = 'residential'): array
    {
        $response = $this->actingAs($client)
            ->get($this->previewUrl($agentId, $role, $propertyType));
        $response->assertStatus(200);

        $token = session('hire_direct_token');
        $this->assertNotEmpty($token, 'Preview page must set hire_direct_token in session');

        return [$response, $token];
    }

    // =========================================================================
    // §1 — Preview page: no checkboxes, bullet-point service list
    // =========================================================================

    /**
     * @dataProvider allRolesProvider
     */
    public function test_preview_shows_bullet_list_and_no_checkboxes_for_all_roles(string $role): void
    {
        $agent  = $this->makeAgent();
        $client = $this->makeClient();

        $services = ['Unique Service One', 'Unique Service Two'];
        $this->makeProfile($agent, $role, 'residential', $services);

        [$response] = $this->loadPreview($client, $agent->id, $role);

        $html = $response->getContent();

        // No checkbox inputs anywhere on the page
        $this->assertStringNotContainsString(
            'type="checkbox"',
            $html,
            "Preview for role [{$role}] must not contain any checkbox inputs"
        );

        // Each service label must appear as text
        foreach ($services as $svc) {
            $this->assertStringContainsString($svc, $html, "Service [{$svc}] must appear in the preview for role [{$role}]");
        }

        // The read-only bullet list container must be present
        $this->assertStringContainsString('service-bullet-list', $html, "Read-only bullet list class must be present for role [{$role}]");
    }

    public function test_preview_shows_additional_other_services_as_bullet_list(): void
    {
        $agent  = $this->makeAgent();
        $client = $this->makeClient();

        $services      = ['Core Service'];
        $otherServices = ['Custom Other Service XYZ'];

        $this->makeProfile($agent, 'buyer', 'residential', $services, [
            'other_services' => $otherServices,
        ]);

        [$response] = $this->loadPreview($client, $agent->id, 'buyer');

        $html = $response->getContent();

        $this->assertStringNotContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('Custom Other Service XYZ', $html);
        $this->assertStringContainsString('service-bullet-list', $html);
    }

    public function test_preview_shows_counter_terms_note_under_compensation(): void
    {
        $agent  = $this->makeAgent();
        $client = $this->makeClient();

        $this->makeProfile($agent, 'seller', 'residential', ['List on MLS'], [
            'commission_type'   => 'percentage',
            'commission_amount' => '3',
        ]);

        [$response] = $this->loadPreview($client, $agent->id, 'seller');

        $response->assertSee(
            'Request Changes / Counter Terms',
            false
        );
    }

    public function test_preview_shows_accept_and_counter_buttons_for_non_owner(): void
    {
        $agent  = $this->makeAgent();
        $client = $this->makeClient();

        $this->makeProfile($agent, 'buyer', 'residential', ['Help buyer find home']);

        [$response] = $this->loadPreview($client, $agent->id, 'buyer');

        $html = $response->getContent();

        $this->assertStringContainsString('Accept &amp; Submit Hire Request', $html);
        $this->assertStringContainsString('Request Changes / Counter Terms', $html);
        $this->assertStringContainsString('hire-intent', $html);
    }

    public function test_owner_preview_disables_all_action_buttons(): void
    {
        $agent = $this->makeAgent();

        $this->makeProfile($agent, 'buyer', 'residential', ['Help buyer find home']);

        $response = $this->actingAs($agent)
            ->get($this->previewUrl($agent->id, 'buyer'));

        $response->assertStatus(200);

        $html = $response->getContent();

        // Owner preview mode must not show the submit button elements
        $this->assertStringNotContainsString('id="hire-direct-submit"', $html);
        $this->assertStringNotContainsString('id="hire-direct-counter"', $html);

        // Owner preview banner must appear
        $this->assertStringContainsString('You are previewing your own Direct Hire page', $html);
    }

    // =========================================================================
    // §2 — Accept POST: listing + bid created, redirects to listing detail
    // =========================================================================

    /**
     * @dataProvider allRolesProvider
     */
    public function test_accept_post_creates_listing_and_bid_redirects_to_detail(string $role): void
    {
        $agent  = $this->makeAgent();
        $client = $this->makeClient();

        $services = ['Full Preset Service One', 'Full Preset Service Two'];
        $this->makeProfile($agent, $role, 'residential', $services);

        [, $token] = $this->loadPreview($client, $agent->id, $role);

        $response = $this->actingAs($client)
            ->post($this->confirmUrl($agent->id, $role), [
                '_hire_token' => $token,
                'address'     => '123 Test Street, Miami, FL 33101',
                'intent'      => 'accept',
            ]);

        // Must redirect (not stay on page or error)
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location, 'Accept POST must redirect to a URL');

        // Resolve the listing that was created for this client+agent combination
        $listingClass = match ($role) {
            'buyer'    => \App\Models\BuyerAgentAuction::class,
            'seller'   => \App\Models\SellerAgentAuction::class,
            'landlord' => \App\Models\LandlordAgentAuction::class,
            'tenant'   => \App\Models\TenantAgentAuction::class,
        };
        $listing = $listingClass::where('user_id', $client->id)->latest()->first();
        $this->assertNotNull($listing, "Listing must be created for role [{$role}] after accept POST");

        // Assert exact redirect URL matches the per-role listing-detail route with the listing id.
        // This catches future misrouting regressions for the accept path.
        $expectedListingRoutes = [
            'buyer'    => 'buyer.view-auction',
            'seller'   => 'seller.agent.auction.detail',
            'landlord' => 'landlord.agent.auction.view',
            'tenant'   => 'tenant.agent.auction.view',
        ];
        $expectedUrl = route($expectedListingRoutes[$role], $listing->id);

        $this->assertEquals(
            $expectedUrl,
            $location,
            "Accept redirect for role [{$role}] must point to [{$expectedUrl}], got [{$location}]"
        );
    }

    public function test_accept_post_saves_full_preset_services_when_no_services_in_body(): void
    {
        $agent  = $this->makeAgent();
        $client = $this->makeClient();

        $services = ['Alpha Service', 'Beta Service', 'Gamma Service'];
        $this->makeProfile($agent, 'buyer', 'residential', $services);

        [, $token] = $this->loadPreview($client, $agent->id, 'buyer');

        // POST with no services[] in body — simulates the read-only page
        $response = $this->actingAs($client)
            ->post($this->confirmUrl($agent->id, 'buyer'), [
                '_hire_token' => $token,
                'address'     => '456 Oak Ave, Tampa, FL 33601',
                'intent'      => 'accept',
            ]);

        $response->assertRedirect();

        // Verify the bid was created with the full preset services
        $bidModel = \App\Models\BuyerAgentAuctionBid::where('user_id', $agent->id)
            ->latest()
            ->first();

        $this->assertNotNull($bidModel, 'Bid must be created for buyer role');

        $savedServices = $bidModel->info('services');
        $decoded = is_string($savedServices) ? json_decode($savedServices, true) : $savedServices;

        $this->assertIsArray($decoded, 'Saved services must be a JSON-encoded array');
        foreach ($services as $svc) {
            $this->assertContains($svc, $decoded, "Full preset service [{$svc}] must be saved on the bid");
        }
    }

    // =========================================================================
    // §3 — Counter POST: listing + bid created, redirects to per-role counter
    // =========================================================================

    /**
     * @dataProvider allRolesProvider
     */
    public function test_counter_post_creates_listing_and_bid_redirects_to_view_counter(string $role): void
    {
        $agent  = $this->makeAgent();
        $client = $this->makeClient();

        $services = ['Counter Test Service'];
        $this->makeProfile($agent, $role, 'residential', $services);

        [, $token] = $this->loadPreview($client, $agent->id, $role);

        $response = $this->actingAs($client)
            ->post($this->confirmUrl($agent->id, $role), [
                '_hire_token' => $token,
                'address'     => '789 Pine Rd, Orlando, FL 32801',
                'intent'      => 'counter',
            ]);

        // Must redirect
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertNotNull($location, "Counter POST for role [{$role}] must redirect");

        // The bid_id in the redirect URL must correspond to a real bid created for this agent
        $bidClass = match ($role) {
            'buyer'    => \App\Models\BuyerAgentAuctionBid::class,
            'seller'   => \App\Models\SellerAgentAuctionBid::class,
            'landlord' => \App\Models\LandlordAgentAuctionBid::class,
            'tenant'   => \App\Models\TenantAgentAuctionBid::class,
        };

        $bid = $bidClass::where('user_id', $agent->id)->latest()->first();
        $this->assertNotNull($bid, "Bid must be created for role [{$role}] after counter POST");

        // Assert exact redirect URL matches the per-role view-counter route with the new bid_id.
        // This catches any miswiring of COUNTER_ROUTES — if the route name were wrong for a role,
        // route() would throw RouteNotFoundException and the test would fail with a clear error.
        $expectedCounterRoutes = [
            'buyer'    => 'buyer.hire.agent.auction.bid.view-counter',
            'seller'   => 'hire.seller.agent.auction.bid.view-counter',
            'landlord' => 'landlord.hire.agent.auction.bid.view-counter',
            'tenant'   => 'tenant.hire.agent.auction.bid.view-counter',
        ];

        $expectedUrl = route($expectedCounterRoutes[$role], ['bid_id' => $bid->id]);

        $this->assertEquals(
            $expectedUrl,
            $location,
            "Counter redirect for role [{$role}] must point to [{$expectedUrl}], got [{$location}]"
        );
    }

    public function test_counter_post_saves_full_preset_services_on_bid(): void
    {
        $agent  = $this->makeAgent();
        $client = $this->makeClient();

        $services = ['Preset Svc X', 'Preset Svc Y'];
        $this->makeProfile($agent, 'seller', 'residential', $services);

        [, $token] = $this->loadPreview($client, $agent->id, 'seller');

        $this->actingAs($client)
            ->post($this->confirmUrl($agent->id, 'seller'), [
                '_hire_token' => $token,
                'address'     => '321 Elm Blvd, Jacksonville, FL 32099',
                'intent'      => 'counter',
            ]);

        $bid = \App\Models\SellerAgentAuctionBid::where('user_id', $agent->id)->latest()->first();
        $this->assertNotNull($bid, 'Seller bid must be created on counter POST');

        $decoded = json_decode($bid->info('services'), true);
        $this->assertIsArray($decoded);
        foreach ($services as $svc) {
            $this->assertContains($svc, $decoded, "Service [{$svc}] must be saved on seller bid after counter POST");
        }
    }

    // =========================================================================
    // Data providers
    // =========================================================================

    public static function allRolesProvider(): array
    {
        return [
            'buyer'    => ['buyer'],
            'seller'   => ['seller'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }
}
