<?php

namespace Tests\Feature\HireAgent;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M5.4 — the Hire Agent bid CTA, and the proposal privacy it sits next to.
 *
 * TWO SEPARATE CLAIMS, AND THE SECOND IS THE IMPORTANT ONE.
 *
 * The first is that the CTA offers the right action to the right viewer. The redesigned branch
 * decides STATE FIRST, THEN VIEWER — hired, pending, expired, owner, then agent/non-agent/guest —
 * and the two cases that were actually wrong before are the owner ones. Listing creation carries
 * no role middleware, so an `agent` user can own a landlord listing; the legacy gate asks only
 * "are you an agent?", so that owner was shown "Bid Now" while the bid component (BYA-H2 Rule B1)
 * had already decided to refuse the submission. A non-agent owner was told "Only agents can place
 * bids" about their own listing.
 *
 * The second claim is that no viewer but the owner receives proposal data. That is enforced
 * server-side by HireAgentProposalAccess, which the controller calls BEFORE deriving the view's
 * bid collection, so the view is handed only authorized rows and cannot leak one through markup.
 * These tests assert the OUTCOME in rendered HTML rather than trusting that layer, because the
 * point of a regression test here is to fail if someone re-widens the query, adds an aggregate,
 * or reintroduces a count. HireAgentDetailViewPrivacyTest covers the per-card identity rules;
 * this file covers the console as a whole and the amounts planted inside it.
 *
 * The planted amounts matter: asserting that a competing agent "sees no proposals" would pass
 * just as happily on a listing where no proposal data exists at all. Every rival fixture below
 * carries a distinctive amount so absence means withheld, not missing.
 */
class HireAgentBidCtaTest extends TestCase
{
    use DatabaseTransactions;

    /** Planted in the RIVAL's proposal. Must never reach anyone but the owner. */
    private const RIVAL_AMOUNT = '987654.32';

    /** Planted in the viewer's OWN proposal, where one exists. */
    private const OWN_AMOUNT = '111222.33';

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * @param string $ownerType user_type of the listing owner — 'seller' or 'agent'
     * @return array{owner: User, mine: User, rival: User, outsider: User, listing: LandlordAgentAuction}
     */
    private function scenario(string $ownerType = 'seller'): array
    {
        $owner    = User::factory()->create(['user_type' => $ownerType]);
        $mine     = User::factory()->create(['user_type' => 'agent']);
        $rival    = User::factory()->create(['user_type' => 'agent']);
        $outsider = User::factory()->create(['user_type' => 'seller']);

        $listing = LandlordAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Landlord bid-CTA listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $listing->saveMeta('address', '100 Test Street');
        $listing->saveMeta('budget', '4321');

        return compact('owner', 'mine', 'rival', 'outsider', 'listing');
    }

    /** Landlord bids hold their compensation in EAV meta, not a native column. */
    private function bid(LandlordAgentAuction $listing, User $agent, string $amount): LandlordAgentAuctionBid
    {
        $bid = LandlordAgentAuctionBid::forceCreate([
            'landlord_agent_auction_id' => $listing->id,
            'user_id'                   => $agent->id,
        ]);
        $bid->saveMeta('brokerage', $amount);

        return $bid;
    }

    private function url(LandlordAgentAuction $listing): string
    {
        return route('landlord.agent.auction.view', $listing->id);
    }

    private function enableRedesign(): void
    {
        config(['hire_agent_detail.redesign_enabled' => true]);
    }

    /** The sidebar column, where both the CTA and the proposal console live. */
    private function sidebar(string $html): string
    {
        return preg_match('/data-hire-agent-sidebar.*?(?=<\/div>\s*<\/div>\s*<\/div>)/s', $html, $m) ? $m[0] : $html;
    }

    // ── The CTA, state first ─────────────────────────────────────────────────

    public function test_an_eligible_agent_is_offered_the_bid_cta(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $this->actingAs($s['mine']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringContainsString('Bid Now', $html);
        $this->assertStringContainsString(
            route('agent.landlord.agent.auction.bid', $s['listing']->id),
            $html,
            'The existing bid route must be preserved.'
        );
    }

    public function test_an_agent_who_already_bid_sees_their_own_state_not_a_cta(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $this->actingAs($s['mine']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringContainsString('You have already placed a bid', $html);
        $this->assertStringContainsString('Bid Already Placed', $html);
        $this->assertStringNotContainsString('Bid Now', $html);
    }

    /** The headline fix: an owner gets no CTA — not a button, not a disabled state. */
    public function test_the_listing_owner_is_offered_no_bid_cta(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $this->actingAs($s['owner']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringNotContainsString('Bid Now', $html);
        $this->assertStringNotContainsString('Bid Already Placed', $html);
        $this->assertStringNotContainsString('Log in to bid', $html);
        $this->assertStringNotContainsString(
            'Only agents can place bids',
            $html,
            'The owner must not be told they are the wrong role on their own listing.'
        );
    }

    /**
     * An owner who is ALSO an agent gets no CTA either.
     *
     * This is the case the legacy gate got wrong: it asks only whether the viewer is an agent, so
     * this viewer was shown "Bid Now" for an action the server refuses (BYA-H2 Rule B1).
     */
    public function test_an_owner_who_is_also_an_agent_is_offered_no_bid_cta(): void
    {
        $this->enableRedesign();
        $s = $this->scenario('agent');
        $this->actingAs($s['owner']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'Bid Now',
            $html,
            'An agent who owns the listing must not be offered a bid the server will refuse.'
        );
        $this->assertStringNotContainsString(
            route('agent.landlord.agent.auction.bid', $s['listing']->id),
            $html
        );
    }

    /** And the server still refuses it, independently of what the page shows. */
    public function test_the_owner_self_bid_is_refused_server_side(): void
    {
        $s = $this->scenario('agent');
        $this->actingAs($s['owner']);

        $this->assertSame(
            0,
            LandlordAgentAuctionBid::where('landlord_agent_auction_id', $s['listing']->id)
                ->where('user_id', $s['owner']->id)
                ->count(),
            'Precondition: the owner has no bid.'
        );

        // Rule B1 lives in the bid component, which the CTA links to. Reaching the form is
        // allowed; submitting a self-bid is what is refused, and no bid row may result.
        $this->get(route('agent.landlord.agent.auction.bid', $s['listing']->id));

        $this->assertSame(
            0,
            LandlordAgentAuctionBid::where('landlord_agent_auction_id', $s['listing']->id)
                ->where('user_id', $s['owner']->id)
                ->count(),
            'No self-bid may be created for the listing owner.'
        );
    }

    public function test_an_authenticated_non_agent_is_told_only_agents_can_bid(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $this->actingAs($s['outsider']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringContainsString('Only agents can place bids', $html);
        $this->assertStringNotContainsString('Bid Now', $html);
    }

    public function test_a_guest_receives_the_login_path_only(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringContainsString('Log in to bid', $html);
        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringNotContainsString('Bid Now', $html);
    }

    /**
     * Listing state outranks viewer, which is the whole point of the reordering.
     *
     * @dataProvider closedListingProvider
     */
    public function test_a_closed_listing_shows_its_state_and_no_cta(string $meta, string $value, string $expected): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $s['listing']->saveMeta($meta, $value);
        $this->actingAs($s['mine']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringContainsString($expected, $html);
        $this->assertStringNotContainsString('Bid Now', $html, 'A closed listing takes no new bids.');
    }

    public static function closedListingProvider(): array
    {
        return [
            'pending' => ['listing_status', 'Pending', 'This listing is pending'],
            'hired'   => ['listing_status', 'Hired Agent', 'An agent has been hired'],
            'expired' => ['expiration_date', '2020-01-01', 'This listing has expired'],
        ];
    }

    /** A guest on an expired listing is told it expired, not invited to log in and bid. */
    public function test_a_guest_on_an_expired_listing_sees_the_expiry_not_a_login_cta(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $s['listing']->saveMeta('expiration_date', '2020-01-01');

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringContainsString('This listing has expired', $html);
        $this->assertStringNotContainsString('Log in to bid', $html);
    }

    // ── Proposal privacy, asserted on rendered HTML ──────────────────────────

    /** The owner reviews every proposal on their own listing. */
    public function test_the_owner_sees_all_proposals(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $mine  = $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $rival = $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);
        $this->actingAs($s['owner']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringContainsString('privateDataModal' . $mine->id, $html);
        $this->assertStringContainsString('privateDataModal' . $rival->id, $html);
        $this->assertStringContainsString(self::RIVAL_AMOUNT, $html, 'The owner may see the amounts.');
    }

    /** A submitting agent sees their own proposal and nothing of the rival's. */
    public function test_a_submitting_agent_sees_only_their_own_proposal(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $mine  = $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $rival = $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);
        $this->actingAs($s['mine']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringContainsString('privateDataModal' . $mine->id, $html);
        $this->assertStringNotContainsString('privateDataModal' . $rival->id, $html);
        $this->assertStringNotContainsString(self::RIVAL_AMOUNT, $html);
        $this->assertStringNotContainsString($s['rival']->name, $html);
    }

    /**
     * A competing agent receives nothing about the rival proposal.
     *
     * "Competing" here means an agent who has bid, viewing a page that also carries someone
     * else's bid — the realistic case, and the one where a count or a rank leaks most easily.
     */
    public function test_a_competing_agent_receives_no_rival_proposal_data(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $rival = $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);
        $this->actingAs($s['mine']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringNotContainsString('privateDataModal' . $rival->id, $html);
        $this->assertStringNotContainsString(self::RIVAL_AMOUNT, $html);
        $this->assertStringNotContainsString('987654', $html);
        $this->assertStringNotContainsString($s['rival']->name, $html);
    }

    /** An agent who has NOT bid learns nothing about anyone's proposal. */
    public function test_an_agent_who_has_not_bid_receives_no_proposal_data(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $mine  = $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $rival = $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);
        $stranger = User::factory()->create(['user_type' => 'agent']);
        $this->actingAs($stranger);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringNotContainsString('privateDataModal' . $mine->id, $html);
        $this->assertStringNotContainsString('privateDataModal' . $rival->id, $html);
        $this->assertStringNotContainsString(self::RIVAL_AMOUNT, $html);
        $this->assertStringNotContainsString(self::OWN_AMOUNT, $html);
    }

    /** An unrelated authenticated user receives nothing. */
    public function test_an_unrelated_authenticated_user_receives_no_proposal_data(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $mine  = $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $rival = $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);
        $this->actingAs($s['outsider']);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringNotContainsString('privateDataModal' . $mine->id, $html);
        $this->assertStringNotContainsString('privateDataModal' . $rival->id, $html);
        $this->assertStringNotContainsString(self::RIVAL_AMOUNT, $html);
    }

    /** A guest receives nothing. */
    public function test_a_guest_receives_no_proposal_data(): void
    {
        $this->enableRedesign();
        $s = $this->scenario();
        $mine  = $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $rival = $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);

        $html = $this->get($this->url($s['listing']))->assertOk()->getContent();

        $this->assertStringNotContainsString('privateDataModal' . $mine->id, $html);
        $this->assertStringNotContainsString('privateDataModal' . $rival->id, $html);
        $this->assertStringNotContainsString(self::RIVAL_AMOUNT, $html);
        $this->assertStringNotContainsString(self::OWN_AMOUNT, $html);
    }

    /**
     * The narrowing is server-side, not markup-deep.
     *
     * Asserted against the collection the view is actually handed, so this fails if someone
     * re-widens the query even while the markup still happens to hide the extra rows.
     */
    public function test_the_view_is_handed_only_authorized_proposals(): void
    {
        $s = $this->scenario();
        $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);

        $ids = function (?User $viewer) use ($s): array {
            if ($viewer) {
                $this->actingAs($viewer);
            } else {
                app('auth')->forgetGuards();
            }

            $response = $this->get($this->url($s['listing']))->assertOk();
            $auction  = $response->original->getData()['auction'];

            return $auction->bids->pluck('user_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        };

        $this->assertSame([(int) $s['mine']->id, (int) $s['rival']->id], $ids($s['owner']), 'Owner: all.');
        $this->assertSame([(int) $s['mine']->id], $ids($s['mine']), 'Submitting agent: own only.');
        $this->assertSame([], $ids($s['outsider']), 'Unrelated authenticated: none.');
        $this->assertSame([], $ids(null), 'Guest: none.');
    }

    // ── Dead markup ──────────────────────────────────────────────────────────

    /** The `sold` branch could never render — there is no such column or accessor. */
    public function test_the_dead_sold_branch_is_gone(): void
    {
        $source = file_get_contents(base_path('resources/views/hire_landlord_agent/view.blade.php'));

        $this->assertStringNotContainsString('$auction->sold', $source);
    }

    /** The stranded icon-only button is suppressed behind the flag, and kept without it. */
    public function test_the_stranded_empty_button_is_suppressed_behind_the_flag(): void
    {
        $s = $this->scenario();

        $legacy = $this->get($this->url($s['listing']))->assertOk()->getContent();
        $this->assertStringContainsString('bid m-0', $legacy, 'Untouched with the flag off.');

        $this->enableRedesign();
        $redesigned = $this->get($this->url($s['listing']))->assertOk()->getContent();
        $this->assertStringNotContainsString('bid m-0', $redesigned);
    }
}
