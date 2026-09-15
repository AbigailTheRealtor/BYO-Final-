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

    // ── Test 2: The right-column actions hold the offers.store form ─────────
    //
    // The page uses the Criteria page family: one action column on the right. The hero CTA row,
    // the Quick Actions grid, the sticky sidebar and the mobile bar each carried a copy of this
    // form; all four are gone and the form lives once, in class="tcl-actions".

    public function test_the_right_column_actions_contain_an_offers_store_form_with_role_tenant(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.tenant.view', $this->auction->id));

        $response->assertStatus(200);

        $body     = $response->getContent();
        $storeUrl = route('offers.store');

        // The HTML attribute form skips the CSS definition earlier in the page.
        $actions = $this->sliceAfter($body, 'class="tcl-actions"', 1500);
        $this->assertNotFalse($actions, 'Right-column actions (class="tcl-actions") were not found in the response.');
        $this->assertStringContainsString($storeUrl, $actions, 'The actions must contain a form POSTing to offers.store.');
        $this->assertStringContainsString('name="role" value="tenant"', $actions, 'The form must include role=tenant.');
        $this->assertStringContainsString('name="listing_type" value="tenant_criteria"', $actions, 'The form must include listing_type=tenant_criteria.');
        $this->assertStringContainsString('Respond to Tenant Criteria', $actions, 'The CTA must read "Respond to Tenant Criteria".');

        foreach (['class="tcl-hero-ctas"', 'id="tcl-interaction-hub"', 'class="tcl-sticky-card"', 'class="tcl-mobile-bar'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $body, "The legacy {$legacy} container must not render.");
        }
    }

    // ── Test 3: offers.store route appears exactly once ─────────────────────

    public function test_tenant_listing_view_contains_offers_store_action_exactly_once(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.tenant.view', $this->auction->id));

        $response->assertStatus(200);

        $body     = $response->getContent();
        $storeUrl = route('offers.store');
        $count    = substr_count($body, $storeUrl);

        $this->assertSame(1, $count,
            "The offers.store URL must appear exactly once (the right-column Respond form); found {$count}.");
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
