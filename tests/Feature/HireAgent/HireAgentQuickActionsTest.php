<?php

namespace Tests\Feature\HireAgent;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M5.3 — the Hire Agent Quick Actions band.
 *
 * Three claims, in increasing order of how much they matter.
 *
 * The flag is real: with HIRE_AGENT_DETAIL_REDESIGN_ENABLED off there is no band and no copy
 * handler, and the sidebar controls the band replaces are still there.
 *
 * Nothing is duplicated: with the flag on, Send Message, Share and Copy Link each exist in exactly
 * ONE place on the page. A band that adds a second Send Message button is not a redesign, it is a
 * second thing to keep in sync.
 *
 * AND THE ONE THAT PROTECTS A VIEWER: the band offers no owner-only or agent-only workflow, to
 * anybody. This is asserted for four distinct viewers — guest, competing agent, unrelated
 * authenticated user, and the listing owner — because the failure being guarded against is a tile
 * that leaks for one class of viewer and not another. The owner case is the important one and the
 * easiest to get wrong: the owner CAN open proposals, so a "View Proposals" tile would look
 * correct on their page, and would then need a condition that the other three viewers depend on
 * being right. The rule adopted instead is that the band carries no such tile at all, which is
 * what these assertions pin. A tile advertises that a workflow exists and what it is called; that
 * is a disclosure even when the route behind it is protected.
 */
class HireAgentQuickActionsTest extends TestCase
{
    use DatabaseTransactions;

    /** Vocabulary that must never appear in the band, for any viewer. */
    private const FORBIDDEN_IN_BAND = [
        'Proposal', 'proposal', 'Bid', 'bid', 'Edit Listing', 'Compensation', 'compensation',
    ];

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /** @return array{owner: User, rival: User, outsider: User, listing: LandlordAgentAuction} */
    private function scenario(): array
    {
        $owner    = User::factory()->create(['user_type' => 'seller']);
        $rival    = User::factory()->create(['user_type' => 'agent']);
        $outsider = User::factory()->create(['user_type' => 'seller']);

        $listing = LandlordAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Landlord quick-actions listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $listing->saveMeta('address', '100 Test Street');

        // A real proposal exists, so "the owner can open proposals" is a true statement on this
        // page rather than a vacuous one — without it, asserting the band offers no proposal
        // action would pass for the wrong reason.
        LandlordAgentAuctionBid::forceCreate([
            'landlord_agent_auction_id' => $listing->id,
            'user_id'                   => $rival->id,
        ]);

        return compact('owner', 'rival', 'outsider', 'listing');
    }

    private function url(LandlordAgentAuction $listing): string
    {
        return route('landlord.agent.auction.view', $listing->id);
    }

    private function enableRedesign(): void
    {
        config(['hire_agent_detail.redesign_enabled' => true]);
    }

    /** Just the band. Assertions about what the band NAMES must be scoped to the band. */
    private function band(string $html): string
    {
        return preg_match('/<section\b[^>]*data-viho-quick-actions\b.*?<\/section>/is', $html, $m) ? $m[0] : '';
    }

    // ── The flag ─────────────────────────────────────────────────────────────

    public function test_the_flag_off_page_has_no_band_and_keeps_the_sidebar_controls(): void
    {
        $this->assertFalse(config('hire_agent_detail.redesign_enabled'), 'Precondition: flag off.');

        $s    = $this->scenario();
        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-viho-quick-actions', $html, 'No band with the flag off.');
        $this->assertStringNotContainsString('data-hla-copy-link', $html, 'No copy handler with the flag off.');
        $this->assertSame('', $this->band($html));

        // The controls the band would replace are untouched.
        $this->assertStringContainsString('Send Message', $html, 'The sidebar message button stays.');
        $this->assertStringContainsString('js-copy-link', $html, 'The sidebar share card stays.');
        $this->assertStringContainsString('Share this link via', $html);
    }

    public function test_the_flag_on_page_renders_the_band_with_the_three_approved_tiles(): void
    {
        $this->enableRedesign();

        $s    = $this->scenario();
        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();
        $band = $this->band($html);

        $this->assertNotSame('', $band, 'The band must render.');
        $this->assertStringContainsString('Send Message', $band);
        $this->assertStringContainsString('Share Listing', $band);
        $this->assertStringContainsString('Copy Link', $band);
        $this->assertSame(3, substr_count($band, 'class="viho-action-tile"'), 'Exactly the three approved tiles.');
    }

    /** The band is full-width above the grid, which is what the new shell slot is for. */
    public function test_the_band_renders_before_the_grid_not_inside_the_main_column(): void
    {
        $this->enableRedesign();

        $s    = $this->scenario();
        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $band = strpos($html, 'data-viho-quick-actions');
        $main = strpos($html, 'data-hire-agent-main');

        $this->assertNotFalse($band);
        $this->assertNotFalse($main);
        $this->assertLessThan($main, $band, 'The band belongs above the grid, not inside the main column.');
    }

    // ── Nothing is duplicated ────────────────────────────────────────────────

    public function test_the_replaced_sidebar_controls_are_suppressed_when_the_band_is_on(): void
    {
        $this->enableRedesign();

        $s    = $this->scenario();
        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Send Message'), 'Send Message must exist exactly once.');
        $this->assertStringNotContainsString('js-copy-link', $html, 'The legacy copy button is gone.');
        $this->assertStringNotContainsString('Share this link via', $html, 'The legacy share card is gone.');
        // Matched with `="` so this counts the rendered attribute only. The bare token also appears
        // in the handler's own selector and getAttribute call, which are not copy controls.
        $this->assertSame(1, substr_count($html, 'data-hla-copy-link="'), 'Exactly one copy control.');
    }

    // ── The claim that protects a viewer ─────────────────────────────────────

    /**
     * No viewer is offered an owner-only or agent-only workflow through the band.
     *
     * @dataProvider viewerProvider
     */
    public function test_the_band_offers_no_restricted_workflow_to_any_viewer(string $viewer): void
    {
        $this->enableRedesign();

        $s = $this->scenario();

        if ($viewer !== 'guest') {
            $this->actingAs($s[$viewer]);
        }

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();
        $band = $this->band($html);

        $this->assertNotSame('', $band, "The band renders for {$viewer}.");

        foreach (self::FORBIDDEN_IN_BAND as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $band,
                "The Quick Actions band must not name '{$needle}' to {$viewer}. Proposals and Edit "
                . 'Listing are listing-owner-only and the bid CTA is agent-only; naming one in a '
                . 'public band discloses that the workflow exists regardless of who may open it.'
            );
        }

        // Positively: the band is the same three public/authenticated tiles for everyone. The band
        // does not vary by viewer at all, which is the simplest form of "cannot leak".
        $this->assertSame(3, substr_count($band, 'class="viho-action-tile"'));
    }

    public static function viewerProvider(): array
    {
        return [
            'guest'                  => ['guest'],
            'competing agent'        => ['rival'],
            'unrelated authenticated'=> ['outsider'],
            'listing owner'          => ['owner'],
        ];
    }

    /**
     * The owner really can reach proposals on this page — so the assertion above is not vacuous.
     *
     * Without this, "the band names no proposal" would pass just as happily on a page where
     * proposals do not exist for anyone, which is the trap HireAgentDetailViewPrivacyTest
     * documents falling into for real.
     */
    public function test_the_owner_still_reaches_proposals_outside_the_band(): void
    {
        $this->enableRedesign();

        $s = $this->scenario();
        $this->actingAs($s['owner']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringNotContainsString('Proposal', $this->band($html), 'Not via the band.');
        $this->assertMatchesRegularExpression(
            '/proposal/i',
            str_replace($this->band($html), '', $html),
            'The owner still reaches proposals through the page itself.'
        );
    }
}
