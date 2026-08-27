<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\TenantAgentAuction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The SECONDARY action surfaces — sharing, messaging and the guest bid CTA — across all four roles.
 *
 * ── WHAT DRIFTED ─────────────────────────────────────────────────────────────────────────────
 *
 * The redesign moved sharing and messaging into the Quick Actions band, and the legacy surfaces
 * they replace are meant to be suppressed while the flag is on. Two of those suppressions were
 * only ever applied to some roles:
 *
 *   · the legacy QR / "Share this link via" card was guarded on seller and landlord and NOT on
 *     buyer or tenant, so those two rendered a second share panel — its own QR, its own social
 *     row, its own `.js-copy-link` — below the detail content;
 *   · the sidebar Send Message button was guarded on landlord only, so seller, buyer and tenant
 *     rendered the same action twice, once in the band and once in the sidebar.
 *
 * Separately the guest bid CTA existed in two spellings: landlord's redesign branch emitted an
 * `x-viho.button`, while the other three still emitted the legacy `<button class="btn w-100">`,
 * which is why the sidebar cards did not line up. Buyer and tenant also wrote `${{ …->budget }}`
 * with no guard, so a listing carrying no budget rendered a dangling "$".
 *
 * ── WHAT THIS FILE ASSERTS, AND WHAT IT DELIBERATELY DOES NOT ────────────────────────────────
 *
 * It asserts that each secondary action exists in exactly ONE place per flag state, and that the
 * four roles draw the guest CTA through one component. It does NOT assert that the four roles
 * offer the same ACTIONS: role-specific authorization is tested here as an expected DIFFERENCE
 * rather than normalised away, because "make the screenshots match" is precisely how an
 * authorization branch gets deleted for a visual reason.
 *
 * Assertions are on semantic hooks — the component's own `viho-btn` classes, `.hla-cta-amount`,
 * `.hla-quick-share`, `.js-copy-link` — rather than on copy, so rewording a label does not fail
 * a test about where an action lives.
 */
class HireAgentSecondaryActionParityTest extends TestCase
{
    use DatabaseTransactions;

    private const ROLES = ['seller', 'buyer', 'landlord', 'tenant'];

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @return array{0:class-string<Model>,1:string,2:string} model, view route, chat slug */
    private function wiringFor(string $role): array
    {
        return match ($role) {
            'seller'   => [SellerAgentAuction::class,   'seller.agent.auction.detail', 'seller-agent'],
            'buyer'    => [BuyerAgentAuction::class,    'buyer.view-auction',          'buyer-agent'],
            'landlord' => [LandlordAgentAuction::class, 'landlord.agent.auction.view', 'landlord-agent'],
            'tenant'   => [TenantAgentAuction::class,   'tenant.agent.auction.view',   'tenant-agent'],
        };
    }

    /**
     * A published listing for one role.
     *
     * `$budget` is a parameter rather than a constant because the absent-amount case is one of the
     * things under test: passing null is how a listing that carries no budget is expressed, and
     * that is the shape that used to produce the dangling "$".
     */
    private function makeListing(string $role, ?string $budget = '654321'): Model
    {
        [$auctionClass] = $this->wiringFor($role);

        $owner = User::factory()->create(['user_type' => 'seller']);

        $attributes = [
            'user_id'     => $owner->id,
            'title'       => ucfirst($role) . ' secondary-action listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];
        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = '100 Owner Street';
        }

        $listing = $auctionClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Owner Street');
        }

        // Seller's controller redirects anything that looks like an Offer Listing.
        $listing->saveMeta('workflow_type', 'hire_agent');

        foreach ([
            'listing_title'   => ucfirst($role) . ' listing title',
            'property_type'   => 'Residential Property',
            'auction_type'    => 'Traditional',
            'expiration_date' => now()->addDays(30)->toDateTimeString(),
        ] as $k => $v) {
            $listing->saveMeta($k, $v);
        }

        // Seller's CTA reads maximum_budget — the field its own approved fix pointed it at — while
        // buyer, tenant and landlord read budget. Both are planted so each role sees a value in
        // its OWN column and no test accidentally proves that one role reads another's field.
        if ($budget !== null) {
            $listing->saveMeta('budget', $budget);
            $listing->saveMeta('maximum_budget', $budget);
        }

        return $listing->fresh();
    }

    private function renderAsGuest(string $role, Model $listing): string
    {
        [, $route] = $this->wiringFor($role);

        auth()->logout();
        $this->app->get('auth')->forgetGuards();
        $this->assertGuest();

        return $this->get(route($route, $listing->id))->assertOk()->getContent();
    }

    private function renderAs(string $role, Model $listing, User $viewer): string
    {
        [, $route] = $this->wiringFor($role);

        return $this->actingAs($viewer)->get(route($route, $listing->id))->assertOk()->getContent();
    }

    private function enableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => true,
            'hire_agent_detail.redesign_roles'   => self::ROLES,
        ]);
    }

    private function disableRedesign(): void
    {
        config([
            'hire_agent_detail.redesign_enabled' => false,
            'hire_agent_detail.redesign_roles'   => ['landlord'],
        ]);
    }

    /**
     * The document with its <style> blocks removed.
     *
     * Load-bearing for every count below. The shared stylesheet names `.js-copy-link`,
     * `.qr-code` and `.hla-quick-share` in its own rules and ships in BOTH flag states, so a
     * whole-document count reports the CSS, not the page, and would pass no matter what markup
     * rendered.
     */
    private function markup(string $html): string
    {
        return preg_replace('/<style\b.*?<\/style>/is', '', $html);
    }

    private function occurrences(string $html, string $needle): int
    {
        return substr_count($this->markup($html), $needle);
    }

    // ── 1-4: the legacy QR / share card ──────────────────────────────────────

    /**
     * @dataProvider roleProvider
     */
    public function test_redesign_on_suppresses_the_legacy_share_card(string $role): void
    {
        $this->enableRedesign();

        $html = $this->renderAsGuest($role, $this->makeListing($role));

        $this->assertSame(0, $this->occurrences($html, 'Share this link via'), "[$role] legacy share card must be suppressed.");
        $this->assertSame(0, $this->occurrences($html, 'class="qr-code"'), "[$role] legacy QR block must be suppressed.");
        $this->assertSame(0, $this->occurrences($html, 'js-copy-link'), "[$role] legacy copy-link control must be suppressed.");
    }

    /**
     * @dataProvider roleProvider
     */
    public function test_redesign_off_preserves_the_legacy_share_card(string $role): void
    {
        $this->disableRedesign();

        $html = $this->renderAsGuest($role, $this->makeListing($role));

        $this->assertSame(1, $this->occurrences($html, 'Share this link via'), "[$role] flag off must keep the legacy share card.");
        $this->assertSame(1, $this->occurrences($html, 'class="qr-code"'), "[$role] flag off must keep the legacy QR block.");
        $this->assertGreaterThanOrEqual(1, $this->occurrences($html, 'js-copy-link'), "[$role] flag off must keep the legacy copy control.");
    }

    // ── 5: the redesigned share surface survives ─────────────────────────────

    /**
     * @dataProvider roleProvider
     */
    public function test_redesign_on_keeps_exactly_one_quick_actions_share_surface(string $role): void
    {
        $this->enableRedesign();

        $html = $this->renderAsGuest($role, $this->makeListing($role));

        $this->assertSame(
            1,
            $this->occurrences($html, 'hla-quick-share'),
            "[$role] the Quick Actions share row must render exactly once."
        );
    }

    // ── 6-7: messaging lives in one place ────────────────────────────────────

    /**
     * The sidebar's Send Message button is gone under the redesign; the band's tile remains.
     *
     * ASSERTED ON THE SIDEBAR BUTTON ITSELF, not on the chat route and not on the words "Send
     * Message", and both exclusions were learned from this test failing:
     *
     *   · the chat ROUTE is also the owner card's "Message" link, which is a different surface
     *     with its own reason to exist — counting the route called that a duplicate and would
     *     have pushed a real control off the page to make a number read 1;
     *   · the WORDS appear twice more on buyer as `data-bs-content="Send Message"` popovers on
     *     unrelated bid cards, which are tooltips, not ways to open a conversation.
     *
     * The button's own class list is the thing the guard governs, so it is the thing asserted.
     *
     * @dataProvider roleProvider
     */
    public function test_redesign_on_suppresses_the_sidebar_message_button(string $role): void
    {
        $this->enableRedesign();

        $html = $this->renderAsGuest($role, $this->makeListing($role));

        $this->assertSame(
            0,
            $this->occurrences($html, 'class="btn btn-success w-100 mb-2"'),
            "[$role] the sidebar Send Message button must be suppressed — the band carries it."
        );
        $this->assertStringContainsString(
            'viho-action-tile',
            $this->markup($html),
            "[$role] the Quick Actions band must still render its tiles."
        );
    }

    /**
     * And the capability is not withdrawn: flag off, the sidebar button is still the one surface.
     *
     * @dataProvider roleProvider
     */
    public function test_redesign_off_keeps_the_sidebar_message_button(string $role): void
    {
        $this->disableRedesign();

        $listing = $this->makeListing($role);
        [, , $chatSlug] = $this->wiringFor($role);

        $html = $this->renderAsGuest($role, $listing);

        $this->assertSame(
            1,
            $this->occurrences($html, 'class="btn btn-success w-100 mb-2"'),
            "[$role] flag off must keep the legacy sidebar message button."
        );
        $this->assertGreaterThanOrEqual(
            1,
            $this->occurrences($html, route('auction-chat', [$chatSlug, $listing->id])),
            "[$role] and it must still point at the conversation."
        );
    }

    // ── 8, 13: the guest CTA presentation ────────────────────────────────────

    /**
     * @dataProvider roleProvider
     */
    public function test_redesign_on_draws_the_guest_cta_through_the_shared_component(string $role): void
    {
        $this->enableRedesign();

        $html = $this->markup($this->renderAsGuest($role, $this->makeListing($role)));

        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bviho-btn\b[^"]*\bviho-btn-primary\b[^"]*\bviho-btn-block\b/',
            $html,
            "[$role] the guest CTA must render through the shared VIHO button."
        );
        $this->assertStringNotContainsString(
            '<span class="bid m-0">Login to Bid</span>',
            $html,
            "[$role] the legacy hand-written CTA must not render under the redesign."
        );
    }

    /**
     * @dataProvider roleProvider
     */
    public function test_redesign_off_keeps_the_legacy_guest_cta(string $role): void
    {
        $this->disableRedesign();

        $html = $this->markup($this->renderAsGuest($role, $this->makeListing($role)));

        $this->assertStringContainsString(
            '<span class="bid m-0">Login to Bid</span>',
            $html,
            "[$role] flag off must keep the legacy CTA markup exactly."
        );
        $this->assertStringNotContainsString(
            'hla-cta-amount',
            $html,
            "[$role] the redesign's amount companion must not leak into the legacy CTA."
        );
    }

    // ── 10-11: amount semantics ──────────────────────────────────────────────

    /**
     * A listing with no budget renders NO amount — and in particular no bare currency symbol.
     *
     * The regex is the point of the test: `>$<` is what the old unguarded `${{ …->budget }}`
     * collapsed to, and it is invisible to any assertion phrased as "does not contain the
     * amount", because there was no amount.
     *
     * @dataProvider roleProvider
     */
    public function test_an_absent_amount_renders_nothing_rather_than_a_dangling_currency_symbol(string $role): void
    {
        $this->enableRedesign();

        $html = $this->markup($this->renderAsGuest($role, $this->makeListing($role, null)));

        $this->assertStringNotContainsString(
            'hla-cta-amount',
            $html,
            "[$role] no amount is known, so the companion must not be emitted at all."
        );
        $this->assertDoesNotMatchRegularExpression(
            '/>\s*\$\s*</',
            $html,
            "[$role] a dangling '\$' with no figure must never render."
        );
    }

    /** Seller's approved maximum_budget CTA value still reaches the page, formatted. */
    public function test_seller_keeps_its_maximum_budget_cta_amount(): void
    {
        $this->enableRedesign();

        $listing = $this->makeListing('seller', '550000');
        $html    = $this->markup($this->renderAsGuest('seller', $listing));

        $this->assertMatchesRegularExpression(
            '/class="hla-cta-amount"[^>]*>\s*\$550,000\s*</',
            $html,
            'Seller must still present its maximum_budget-derived CTA amount.'
        );
    }

    /** Buyer and tenant read their OWN budget column — no substitution, no invention. */
    public function test_buyer_and_tenant_present_their_own_budget_when_present(): void
    {
        $this->enableRedesign();

        foreach (['buyer', 'tenant'] as $role) {
            $html = $this->markup($this->renderAsGuest($role, $this->makeListing($role, '2000')));

            $this->assertMatchesRegularExpression(
                '/class="hla-cta-amount"[^>]*>\s*\$2,000\s*</',
                $html,
                "[$role] must present its own budget value in the CTA companion."
            );
        }
    }

    // ── 9, 12: role-specific authorization is a DIFFERENCE, not drift ────────

    /**
     * Landlord's authenticated non-agent branch is unchanged — an explanatory notice, not a CTA.
     *
     * This is the assertion that stops a future "make the four sidebars identical" pass from
     * quietly handing a bid control to someone who may not bid.
     */
    public function test_landlord_still_tells_an_authenticated_non_agent_that_only_agents_may_bid(): void
    {
        $this->enableRedesign();

        $listing = $this->makeListing('landlord');
        $viewer  = User::factory()->create(['user_type' => 'seller']);

        $html = $this->markup($this->renderAs('landlord', $listing, $viewer));

        $this->assertStringContainsString('Only agents can place bids', $html);
        $this->assertStringNotContainsString(
            'viho-btn-block',
            $html,
            'A viewer who may not bid must be offered no bid button at all.'
        );
    }

    /**
     * The guest CTA is still reached only from the guest branch.
     *
     * An authenticated non-agent must not be shown the log-in-to-bid control on any role: that
     * would be the visual-parity change broadening who is invited to bid.
     *
     * @dataProvider roleProvider
     */
    public function test_the_guest_cta_is_not_offered_to_an_authenticated_viewer(string $role): void
    {
        $this->enableRedesign();

        $listing = $this->makeListing($role);
        $viewer  = User::factory()->create(['user_type' => 'seller']);

        $html = $this->markup($this->renderAs($role, $listing, $viewer));

        $this->assertStringNotContainsString(
            'Log in to bid',
            $html,
            "[$role] an authenticated viewer must not be shown the guest log-in CTA."
        );
    }

    // ── The sidebar leftovers ────────────────────────────────────────────────

    /**
     * An empty proposal console is not drawn.
     *
     * `<div class="card higestBider">` rendered unconditionally on seller, buyer and tenant, so
     * every viewer the access layer hands zero proposals — a guest here — got an empty bordered
     * bar under the CTA. Landlord already guarded it; the other three now use landlord's own
     * condition. A guest is the strongest case to assert: they can never be authorized to see a
     * proposal, so the console can never be legitimate for them.
     *
     * @dataProvider roleProvider
     */
    public function test_a_guest_is_not_shown_an_empty_proposal_console(string $role): void
    {
        $this->enableRedesign();

        $html = $this->renderAsGuest($role, $this->makeListing($role));

        $this->assertSame(0, $this->occurrences($html, 'higestBider'), "[$role] no empty console for a guest.");
        $this->assertSame(0, $this->occurrences($html, 'id="bids-section"'), "[$role] and no empty console container.");
    }

    /**
     * The icon-only orphan button is not drawn.
     *
     * A full-width button carrying one user icon — no label, no handler, no destination. Seller
     * and landlord already suppressed it, and seller's note warns it must go WITH the share card
     * because guarding the card alone leaves the button standing on its own. That is exactly what
     * happened on buyer and tenant when their card was guarded, which is how visual QA saw it.
     *
     * @dataProvider roleProvider
     */
    public function test_no_icon_only_orphan_button_is_drawn(string $role): void
    {
        $this->enableRedesign();

        $html = $this->markup($this->renderAsGuest($role, $this->makeListing($role)));

        $this->assertDoesNotMatchRegularExpression(
            '/<button class="btn w-100 mt-0">\s*<span class="bid m-0"><i class="fa-solid fa-user"><\/i>\s*<\/span>\s*<\/button>/s',
            $html,
            "[$role] the icon-only button must not render under the redesign."
        );
    }

    /**
     * The guest sidebar card holds the CTA, its optional amount, and nothing else.
     *
     * The strongest statement of the whole pass, and the one that would catch a leftover nobody
     * has thought of yet: whatever else changes, a guest's redesigned sidebar card contains no
     * further control at all.
     *
     * @dataProvider roleProvider
     */
    public function test_the_guest_sidebar_card_carries_only_the_cta_and_its_amount(string $role): void
    {
        $this->enableRedesign();

        $html = $this->markup($this->renderAsGuest($role, $this->makeListing($role)));

        $card = null;
        if (preg_match('/data-hire-agent-sidebar-card[^>]*>(.*?)<\/div>\s*<\/div>/s', $html, $m)) {
            $card = $m[1];
        }
        $this->assertNotNull($card, "[$role] the sidebar card must render.");

        $this->assertSame(
            1,
            preg_match_all('/<(?:a|button)\b/', $card),
            "[$role] exactly one control belongs in a guest's sidebar card — the CTA."
        );
        $this->assertStringNotContainsString('<hr', $card, "[$role] no bare rule inside the card.");
        $this->assertStringNotContainsString('higestBider', $card, "[$role] no console inside the card.");
    }

    /**
     * Flag off, all three leftovers are still exactly where they were.
     *
     * Each is suppressed rather than deleted precisely because it is on the live legacy page
     * today, and nothing here is allowed to change that page.
     *
     * @dataProvider roleProvider
     */
    public function test_redesign_off_preserves_the_legacy_sidebar_markup(string $role): void
    {
        $this->disableRedesign();

        $html = $this->markup($this->renderAsGuest($role, $this->makeListing($role)));

        $this->assertStringContainsString('higestBider', $html, "[$role] flag off keeps the console container.");
        $this->assertMatchesRegularExpression(
            '/<button class="btn w-100 mt-0">\s*<span class="bid m-0"><i class="fa-solid fa-user"><\/i>\s*<\/span>\s*<\/button>/s',
            $html,
            "[$role] flag off keeps the icon-only button exactly as it renders today."
        );
    }

    /**
     * The console guard is a DISPLAY decision and must not withhold a real proposal.
     *
     * The listing owner may review the whole set, so the console — and its empty state — must
     * still reach them even before anyone has bid. This is the assertion that stops the guard
     * being tightened into something that hides proposals.
     *
     * @dataProvider roleProvider
     */
    public function test_the_listing_owner_still_receives_the_proposal_console(string $role): void
    {
        $this->enableRedesign();

        $listing = $this->makeListing($role);
        $owner   = User::find($listing->user_id);

        $html = $this->markup($this->renderAs($role, $listing, $owner));

        $this->assertStringContainsString(
            'higestBider',
            $html,
            "[$role] the owner must still get the console, empty state and all."
        );
    }

    public function roleProvider(): array
    {
        return array_combine(
            self::ROLES,
            array_map(static fn ($r) => [$r], self::ROLES)
        );
    }
}
