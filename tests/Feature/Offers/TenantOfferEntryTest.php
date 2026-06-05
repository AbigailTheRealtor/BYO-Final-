<?php

namespace Tests\Feature\Offers;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TenantOfferEntryTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private TenantAgentAuction $auction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['user_type' => 'seller']);

        $this->auction = TenantAgentAuction::forceCreate([
            'user_id'     => $this->user->id,
            'title'       => 'Test Tenant Criteria Listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        TenantAgentAuctionMeta::create([
            'tenant_agent_auction_id' => $this->auction->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'offer_listing',
        ]);
    }

    private function actingAsAllowedUser(): static
    {
        $this->app['config']->set(
            'offer.playoff_access.allowed_user_ids',
            [$this->user->id]
        );

        return $this->actingAs($this->user);
    }

    /**
     * Extract a slice of the response body starting at the first occurrence of
     * $marker, up to $length bytes. Returns false when the marker is absent.
     */
    private function sliceAfter(string $body, string $marker, int $length = 2000): string|false
    {
        $pos = strpos($body, $marker);
        if ($pos === false) {
            return false;
        }
        return substr($body, $pos, $length);
    }

    // ── Test 1: View renders 200 ─────────────────────────────────────────────

    public function test_tenant_listing_view_renders_200(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.tenant.view', $this->auction->id));

        $response->assertStatus(200);
    }

    // ── Test 2: Each of the four sections contains its own offers.store form ─

    public function test_each_cta_section_contains_an_offers_store_form_with_role_tenant(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.tenant.view', $this->auction->id));

        $response->assertStatus(200);

        $body     = $response->getContent();
        $storeUrl = route('offers.store');

        // ── Hero section: <div class="tcl-hero-ctas"> ────────────────────
        // Use the HTML attribute form to skip the CSS definition earlier in the page.
        $heroSlice = $this->sliceAfter($body, 'class="tcl-hero-ctas"', 2000);
        $this->assertNotFalse($heroSlice,
            'Hero CTA container (class="tcl-hero-ctas") was not found in the response.');
        $this->assertStringContainsString($storeUrl, $heroSlice,
            'Hero CTA section must contain a form POSTing to offers.store.');
        $this->assertStringContainsString('value="tenant"', $heroSlice,
            'Hero CTA form must include a role=tenant hidden input.');
        $this->assertStringContainsString('Respond to Tenant Criteria', $heroSlice,
            'Hero CTA must display the label "Respond to Tenant Criteria".');

        // ── Interaction Hub card: id="tcl-interaction-hub" ───────────────
        $hubSlice = $this->sliceAfter($body, 'id="tcl-interaction-hub"', 3500);
        $this->assertNotFalse($hubSlice,
            'Interaction Hub container (id="tcl-interaction-hub") was not found in the response.');
        $this->assertStringContainsString($storeUrl, $hubSlice,
            'Interaction Hub must contain a form POSTing to offers.store.');
        $this->assertStringContainsString('value="tenant"', $hubSlice,
            'Interaction Hub form must include a role=tenant hidden input.');
        $this->assertStringContainsString('Respond to Tenant Criteria', $hubSlice,
            'Interaction Hub must have a "Respond to Tenant Criteria" card label.');

        // ── Sticky Sidebar: class="tcl-sticky-card" ───────────────────────
        $sidebarSlice = $this->sliceAfter($body, 'class="tcl-sticky-card"', 2500);
        $this->assertNotFalse($sidebarSlice,
            'Sticky sidebar container (class="tcl-sticky-card") was not found in the response.');
        $this->assertStringContainsString($storeUrl, $sidebarSlice,
            'Sticky sidebar must contain a form POSTing to offers.store.');
        $this->assertStringContainsString('value="tenant"', $sidebarSlice,
            'Sticky sidebar form must include a role=tenant hidden input.');
        $this->assertStringContainsString('Respond to Tenant Criteria', $sidebarSlice,
            'Sticky sidebar CTA must display the label "Respond to Tenant Criteria".');

        // ── Mobile Bottom Bar: class="tcl-mobile-bar … ───────────────────
        // Match the opening of the HTML element (not the CSS rule) by targeting
        // the attribute syntax including the opening quote.
        $mobileSlice = $this->sliceAfter($body, 'class="tcl-mobile-bar', 2000);
        $this->assertNotFalse($mobileSlice,
            'Mobile bar container (class="tcl-mobile-bar …) was not found in the response.');
        $this->assertStringContainsString($storeUrl, $mobileSlice,
            'Mobile bar must contain a form POSTing to offers.store.');
        $this->assertStringContainsString('value="tenant"', $mobileSlice,
            'Mobile bar form must include a role=tenant hidden input.');
        $this->assertStringContainsString('>Respond<', $mobileSlice,
            'Mobile bar CTA must display the submit label "Respond".');
    }

    // ── Test 3: offers.store route appears exactly four times (one per CTA) ─

    public function test_tenant_listing_view_contains_offers_store_action_exactly_four_times(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.tenant.view', $this->auction->id));

        $response->assertStatus(200);

        $body     = $response->getContent();
        $storeUrl = route('offers.store');
        $count    = substr_count($body, $storeUrl);

        $this->assertSame(4, $count,
            "The offers.store URL must appear exactly 4 times in the response (one per CTA); found {$count}.");
    }

    // ── Test 4: POST to offers.store with role=tenant creates Offer, redirects

    public function test_post_to_offers_store_with_tenant_role_redirects_to_offers_show(): void
    {
        $offerAuction = OfferAuction::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAsAllowedUser()
            ->post(route('offers.store'), [
                'offer_auction_id' => $offerAuction->id,
                'role'             => 'tenant',
            ]);

        $offer = Offer::where('offer_auction_id', $offerAuction->id)
            ->where('role', 'tenant')
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();

        $this->assertNotNull($offer,
            'An Offer draft with role=tenant should have been created.');

        $response->assertRedirect(route('offers.show', $offer));
    }
}
