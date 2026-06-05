<?php

namespace Tests\Feature\Offers;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Feature tests for Landlord offer listing entry points.
 *
 * Verifies that all four CTA placements (Hero, Interaction Hub,
 * Sticky Sidebar, Mobile Bottom Bar) are present on the Landlord
 * offer listing view and that each form correctly targets
 * route('offers.store') with role=landlord.
 */
class LandlordOfferEntryTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private LandlordAgentAuction $auction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['user_type' => 'buyer']);

        $this->auction = LandlordAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'Test Landlord Offer Listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $this->auction->id,
            'meta_key'                  => 'workflow_type',
            'meta_value'                => 'offer_listing',
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
     * Return the rendered page body, asserting HTTP 200 first.
     */
    private function getPageBody(): string
    {
        $response = $this->actingAs($this->user)
            ->get(route('offer.listing.landlord.view', $this->auction->id));

        $response->assertStatus(200);

        return $response->getContent();
    }

    /**
     * Extract a snippet of $body starting at the first occurrence of $htmlTag.
     * $htmlTag must include angle-bracket syntax (e.g. '<div class="lol-hero-ctas">')
     * so it matches HTML, not the CSS definition block.
     */
    private function snippetAfterTag(string $body, string $htmlTag, int $length = 3000): ?string
    {
        $pos = strpos($body, $htmlTag);
        if ($pos === false) {
            return null;
        }
        return substr($body, $pos, $length);
    }

    // ── Test 1: Page loads with HTTP 200 ─────────────────────────────────────

    public function test_landlord_offer_listing_view_returns_200(): void
    {
        $this->actingAs($this->user)
            ->get(route('offer.listing.landlord.view', $this->auction->id))
            ->assertStatus(200);
    }

    // ── Test 2: Hero CTA — Submit Application form inside lol-hero-ctas ───────

    public function test_hero_section_contains_submit_application_cta(): void
    {
        $body = $this->getPageBody();

        // Search for the opening HTML div tag (angle brackets won't appear in CSS)
        $heroSnippet = $this->snippetAfterTag($body, '<div class="lol-hero-ctas">');
        $this->assertNotNull($heroSnippet, 'Hero CTA wrapper (<div class="lol-hero-ctas">) not found in HTML.');

        $this->assertStringContainsString(
            route('offers.store'),
            $heroSnippet,
            'Hero section must contain a form targeting offers.store.'
        );
        $this->assertStringContainsString(
            'name="role" value="landlord"',
            $heroSnippet,
            'Hero section must contain role=landlord hidden input.'
        );
        $this->assertStringContainsString(
            'Submit Application',
            $heroSnippet,
            'Hero section must contain "Submit Application" button label.'
        );
    }

    // ── Test 3: Hub CTA — Submit Application card inside lol-interaction-hub ──

    public function test_interaction_hub_contains_submit_application_card(): void
    {
        $body = $this->getPageBody();

        // id="lol-interaction-hub" is unique to the HTML element
        $hubSnippet = $this->snippetAfterTag($body, 'id="lol-interaction-hub"', 5000);
        $this->assertNotNull($hubSnippet, 'Interaction Hub (id="lol-interaction-hub") not found in HTML.');

        $this->assertStringContainsString(
            route('offers.store'),
            $hubSnippet,
            'Interaction Hub must contain a form targeting offers.store.'
        );
        $this->assertStringContainsString(
            'name="role" value="landlord"',
            $hubSnippet,
            'Interaction Hub must contain role=landlord hidden input.'
        );
        $this->assertStringContainsString(
            'Submit Application',
            $hubSnippet,
            'Interaction Hub must contain "Submit Application" card label.'
        );
    }

    // ── Test 4: Sidebar CTA — Submit Application form inside lol-sticky-card ──

    public function test_sticky_sidebar_contains_submit_application_cta(): void
    {
        $body = $this->getPageBody();

        // The sticky card HTML div starts with class="lol-sticky-card"
        $sidebarSnippet = $this->snippetAfterTag($body, '<div class="lol-sticky-card">', 4000);
        $this->assertNotNull($sidebarSnippet, 'Sticky sidebar (<div class="lol-sticky-card">) not found in HTML.');

        $this->assertStringContainsString(
            route('offers.store'),
            $sidebarSnippet,
            'Sticky sidebar must contain a form targeting offers.store.'
        );
        $this->assertStringContainsString(
            'name="role" value="landlord"',
            $sidebarSnippet,
            'Sticky sidebar must contain role=landlord hidden input.'
        );
        $this->assertStringContainsString(
            'Submit Application',
            $sidebarSnippet,
            'Sticky sidebar must contain "Submit Application" button label.'
        );
    }

    // ── Test 5: Mobile Bar CTA — Apply form inside lol-mobile-bar ─────────────

    public function test_mobile_bar_contains_offer_cta(): void
    {
        $body = $this->getPageBody();

        // The mobile bar div starts with class="lol-mobile-bar d-lg-none"
        $barSnippet = $this->snippetAfterTag($body, '<div class="lol-mobile-bar', 2000);
        $this->assertNotNull($barSnippet, 'Mobile bottom bar (<div class="lol-mobile-bar...") not found in HTML.');

        $this->assertStringContainsString(
            route('offers.store'),
            $barSnippet,
            'Mobile bar must contain a form targeting offers.store.'
        );
        $this->assertStringContainsString(
            'name="role" value="landlord"',
            $barSnippet,
            'Mobile bar must contain role=landlord hidden input.'
        );
        $this->assertStringContainsString(
            'lol-mobile-bar-offer',
            $barSnippet,
            'Mobile bar offer button must carry the lol-mobile-bar-offer class.'
        );
        $this->assertStringContainsString(
            'Apply',
            $barSnippet,
            'Mobile bar must contain "Apply" button label.'
        );
    }

    // ── Test 6: Original Hero actions are preserved alongside the new CTA ─────

    public function test_hero_section_still_contains_original_actions(): void
    {
        $body = $this->getPageBody();

        $heroSnippet = $this->snippetAfterTag($body, '<div class="lol-hero-ctas">');
        $this->assertNotNull($heroSnippet, 'Hero CTA wrapper not found.');

        $this->assertStringContainsString(
            'lolShowingModal',
            $heroSnippet,
            'Hero section must still contain the Schedule Showing button.'
        );
        $this->assertStringContainsString(
            'lolQuestionModal',
            $heroSnippet,
            'Hero section must still contain the Ask a Question button.'
        );
    }

    // ── Test 7: Original Sidebar actions are preserved alongside the new CTA ──

    public function test_sticky_sidebar_still_contains_original_actions(): void
    {
        $body = $this->getPageBody();

        $sidebarSnippet = $this->snippetAfterTag($body, '<div class="lol-sticky-card">', 4000);
        $this->assertNotNull($sidebarSnippet, 'Sticky sidebar not found.');

        $this->assertStringContainsString(
            'lolQuestionModal',
            $sidebarSnippet,
            'Sticky sidebar must still contain the Ask a Question button.'
        );
        $this->assertStringContainsString(
            'lolShowingModal',
            $sidebarSnippet,
            'Sticky sidebar must still contain the Schedule Showing button.'
        );
        $this->assertStringContainsString(
            'lolAiModal',
            $sidebarSnippet,
            'Sticky sidebar must still contain the Ask AI button.'
        );
    }

    // ── Test 8: Mobile bar has exactly ONE Ask AI button (no duplicate) ────────

    public function test_mobile_bar_has_exactly_one_ask_ai_button(): void
    {
        $body = $this->getPageBody();

        $barStart = strpos($body, '<div class="lol-mobile-bar');
        $this->assertNotFalse($barStart, 'Mobile bar opening tag not found.');

        // Find the closing </div> of the mobile bar (first one after its opening)
        $barEnd = strpos($body, '</div>', $barStart);
        $this->assertNotFalse($barEnd, 'Mobile bar closing tag not found.');

        $barHtml = substr($body, $barStart, $barEnd - $barStart);

        $count = substr_count($barHtml, 'lolAiModal');

        $this->assertEquals(
            1,
            $count,
            "Mobile bar must contain exactly 1 Ask AI button (lolAiModal); found {$count}."
        );
    }

    // ── Test 9: Hero CTA contains all original buttons alongside the offer form ─

    public function test_hero_cta_div_contains_form_and_all_original_buttons(): void
    {
        $body = $this->getPageBody();

        $heroStart = strpos($body, '<div class="lol-hero-ctas">');
        $this->assertNotFalse($heroStart, 'Hero CTA div not found.');

        // Grab up to the closing </div> of the hero-ctas wrapper
        $heroEnd = strpos($body, '</div>', $heroStart);
        $heroSnippet = substr($body, $heroStart, $heroEnd - $heroStart + 6);

        // New CTA
        $this->assertStringContainsString(route('offers.store'), $heroSnippet,
            'Hero CTA must contain offers.store form.');

        // Original CTAs — all three must still be present
        $this->assertStringContainsString('lolShowingModal', $heroSnippet,
            'Hero must still have Schedule Showing button.');
        $this->assertStringContainsString('lolQuestionModal', $heroSnippet,
            'Hero must still have Ask a Question button.');
        $this->assertStringContainsString('lolShareHeroBtn', $heroSnippet,
            'Hero must still have Share button.');
    }

    // ── Test 10: POST to offers.store with role=landlord is not a 404 ─────────

    public function test_post_to_offers_store_with_landlord_role_is_not_404(): void
    {
        $offerAuction = OfferAuction::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAsAllowedUser()
            ->post(route('offers.store'), [
                'offer_auction_id' => $offerAuction->id,
                'role'             => 'landlord',
            ]);

        $this->assertNotEquals(
            404,
            $response->status(),
            'POST to offers.store with role=landlord must not return 404.'
        );

        $offer = Offer::where('offer_auction_id', $offerAuction->id)
            ->where('role', 'landlord')
            ->where('user_id', $this->user->id)
            ->latest()
            ->first();

        $this->assertNotNull($offer, 'An Offer draft with role=landlord should have been created.');
        $response->assertRedirect(route('offers.show', $offer));
    }
}
