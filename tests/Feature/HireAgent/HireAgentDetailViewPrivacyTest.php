<?php

namespace Tests\Feature\HireAgent;

use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionBid;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionBid;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionBid;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Milestone 2, first checkpoint — the disclosure half of Hire Agent proposal privacy.
 *
 * HireAgentProposalAccessTest proves the authorization rule. This file proves the rule
 * actually reaches the four Hire Agent detail pages, from two directions:
 *
 *   1. WHAT THE CONTROLLER HANDS THE VIEW. Asserted against $response->viewData('auction'),
 *      which is the honest test of "no competing data is returned and later hidden by Blade".
 *      A page that withheld nothing but rendered nothing would pass an HTML-only test and
 *      still be one markup edit away from leaking.
 *   2. WHAT ACTUALLY REACHES THE HTML. Asserted against the per-bid card marker
 *      (`privateDataModal<id>`), the competitor's planted fee amount, and the specific copy
 *      Milestone 2 removed.
 *
 * Both directions run for all four roles. The routes are public (no auth middleware), so the
 * guest case is a real reachable state, not a hypothetical.
 *
 * EVERY absence assertion here is paired with a positive control, because an absence test on a
 * page that renders nothing is worthless. Writing this file the first time proved the point:
 * the original version planted the rival's *name* and asserted a competitor could not see it —
 * and it passed instantly, for the wrong reason. These pages alias bids as "Agent N" and never
 * print a real name to anybody, so the assertion could not fail. The controls caught it.
 *
 * @see docs/investigations/hire-agent-listing-framework-implementation-plan.md §2.3
 */
class HireAgentDetailViewPrivacyTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Amount planted in the COMPETITOR's record. It must never reach a competing agent.
     *
     * There is deliberately no planted *name* token: these pages alias bids as "Agent N" and
     * never print an agent's real name to anyone, so asserting a name's absence would pass
     * regardless of the code. Competing identity is asserted through the per-card marker
     * instead — see test_competing_agent_is_not_served_the_rival_proposal_card().
     */
    private const RIVAL_BROKERAGE = '987654.32';

    /** Copy removed by Milestone 2. None of it may come back, for any viewer. */
    private const REMOVED_COPY = [
        'was the last bidder',
        'Submit your bid to view competing bids',
        'Competing bids are visible below',
        'Competing Bids (',
        'View Full Services & Broker Compensation Terms',
    ];

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * @return array{owner: User, mine: User, rival: User, listing: Model, myBid: Model, rivalBid: Model, url: string}
     */
    private function scenario(string $role): array
    {
        $owner = User::factory()->create(['user_type' => 'seller', 'name' => 'ListingOwnerPerson']);
        $mine  = User::factory()->create(['user_type' => 'agent',  'name' => 'MyOwnAgentPerson']);
        $rival = User::factory()->create(['user_type' => 'agent',  'name' => 'RivalAgentPerson']);

        [$auctionClass, $bidClass, $fk, $routeName] = $this->wiringFor($role);

        $listing = $this->makeListing($auctionClass, $owner->id, $role);

        $myBid    = $this->makeBid($bidClass, $fk, $listing->id, $mine->id, '111.11', $role);
        $rivalBid = $this->makeBid($bidClass, $fk, $listing->id, $rival->id, self::RIVAL_BROKERAGE, $role);

        return [
            'owner'    => $owner,
            'mine'     => $mine,
            'rival'    => $rival,
            'listing'  => $listing,
            'myBid'    => $myBid,
            'rivalBid' => $rivalBid,
            'url'      => route($routeName, $listing->id),
        ];
    }

    /**
     * CLAUDE.md's schema asymmetry is load-bearing for these fixtures: `address` exists natively
     * on seller_agent_auctions / buyer_agent_auctions but NOT on the Landlord/Tenant tables,
     * which carry it as EAV meta. forceCreate is used because the Landlord/Tenant models guard
     * mass assignment; those models are out of scope for this checkpoint.
     */
    private function makeListing(string $auctionClass, int $ownerId, string $role): Model
    {
        $attributes = [
            'user_id'     => $ownerId,
            'title'       => ucfirst($role) . ' hire-agent listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ];

        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['address'] = '100 Test Street';
        }

        $listing = $auctionClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $listing->saveMeta('address', '100 Test Street');
        }

        return $listing;
    }

    /**
     * Plant the amount where that role actually stores it. `brokerage` is a native column on the
     * Seller/Buyer bid tables only; the Landlord/Tenant bid tables hold compensation in EAV meta.
     * Planting it in the wrong place would make the "no competing amount" assertions pass
     * vacuously — the amount would simply not exist anywhere for the view to leak.
     */
    private function makeBid(string $bidClass, string $fk, int $listingId, int $userId, string $amount, string $role): Model
    {
        $attributes = [$fk => $listingId, 'user_id' => $userId];

        if (in_array($role, ['seller', 'buyer'], true)) {
            $attributes['brokerage'] = $amount;
        }

        $bid = $bidClass::forceCreate($attributes);

        if (! in_array($role, ['seller', 'buyer'], true)) {
            $bid->saveMeta('purchase_fee_type', 'Flat Fee');
            $bid->saveMeta('purchase_fee_flat', $amount);
        }

        return $bid;
    }

    /** @return array{0: class-string, 1: class-string, 2: string, 3: string} */
    private function wiringFor(string $role): array
    {
        return match ($role) {
            'seller' => [
                SellerAgentAuction::class, SellerAgentAuctionBid::class,
                'seller_agent_auction_id', 'seller.agent.auction.detail',
            ],
            'buyer' => [
                BuyerAgentAuction::class, BuyerAgentAuctionBid::class,
                'buyer_agent_auction_id', 'buyer.view-auction',
            ],
            'landlord' => [
                LandlordAgentAuction::class, LandlordAgentAuctionBid::class,
                'landlord_agent_auction_id', 'landlord.agent.auction.view',
            ],
            'tenant' => [
                TenantAgentAuction::class, TenantAgentAuctionBid::class,
                'tenant_agent_auction_id', 'tenant.agent.auction.view',
            ],
        };
    }

    public function roles(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    /** The bid ids the response actually handed the view. */
    private function servedBidIds($response): array
    {
        $auction = $response->viewData('auction');

        return $auction->bids->pluck('id')->sort()->values()->all();
    }

    // ── The owner keeps full review ──────────────────────────────────────────

    /** @dataProvider roles */
    public function test_owner_is_served_every_proposal(string $role): void
    {
        $s = $this->scenario($role);

        $response = $this->actingAs($s['owner'])->get($s['url']);
        $response->assertOk();

        $this->assertSame(
            collect([$s['myBid']->id, $s['rivalBid']->id])->sort()->values()->all(),
            $this->servedBidIds($response),
            "The {$role} owner must still be served the full proposal set — accept/reject/counter depend on it."
        );
    }

    /** @dataProvider roles */
    public function test_owner_sees_the_empty_state_when_no_proposals_exist(string $role): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        [$auctionClass, , , $routeName] = $this->wiringFor($role);

        $listing = $this->makeListing($auctionClass, $owner->id, $role);

        $this->actingAs($owner)->get(route($routeName, $listing->id))
            ->assertOk()
            ->assertSee('No agents have submitted a bid yet.');
    }

    /**
     * The empty state is a count disclosure, so it is owner-only. A competing agent must not
     * learn "nobody has bid" any more than they may learn "four agents have bid".
     *
     * @dataProvider roles
     */
    public function test_empty_state_is_not_shown_to_a_non_owner(string $role): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $outside = User::factory()->create(['user_type' => 'agent']);
        [$auctionClass, , , $routeName] = $this->wiringFor($role);

        $listing = $this->makeListing($auctionClass, $owner->id, $role);

        $this->actingAs($outside)->get(route($routeName, $listing->id))
            ->assertOk()
            ->assertDontSee('No agents have submitted a bid yet.');
    }

    // ── An agent keeps their own proposal ────────────────────────────────────

    /** @dataProvider roles */
    public function test_agent_is_served_only_their_own_proposal(string $role): void
    {
        $s = $this->scenario($role);

        $response = $this->actingAs($s['mine'])->get($s['url']);
        $response->assertOk();

        $this->assertSame(
            [$s['myBid']->id],
            $this->servedBidIds($response),
            "A {$role} agent must be served exactly their own proposal — the competitor row must never arrive."
        );
    }

    // ── A competing agent learns nothing ────────────────────────────────────

    /**
     * The load-bearing HTML assertion.
     *
     * A rendered proposal card carries a per-bid modal keyed by the bid id
     * (`privateDataModal<id>`). Its presence is therefore an exact, role-independent proxy for
     * "this viewer was shown this proposal" — content, amount, alias, activity and position in
     * the list all travel inside that card. Probing the four pages confirms the asymmetry is
     * real and not vacuous: the owner's HTML contains the rival's marker twice (trigger +
     * modal) and a competing agent's contains it zero times, in every role.
     *
     * Note on identity: these pages never print an agent's real name — bids are alias-labelled
     * ("Agent 1", "Agent 2") by design, and the real name lives on the separate bid-detail
     * surfaces. So asserting the absence of a planted name would pass whatever the code did.
     * The card marker is asserted instead, because it is the thing that actually varies.
     *
     * @dataProvider roles
     */
    public function test_competing_agent_is_not_served_the_rival_proposal_card(string $role): void
    {
        $s = $this->scenario($role);

        $response = $this->actingAs($s['mine'])->get($s['url']);
        $response->assertOk();

        $response->assertDontSee('privateDataModal' . $s['rivalBid']->id, false);

        // …while the viewer's own card is still there. Privacy must not cost an agent
        // sight of their own proposal.
        $response->assertSee('privateDataModal' . $s['myBid']->id, false);
    }

    /**
     * Positive control for the assertion above. Without this, a page that rendered no cards at
     * all would satisfy every absence test in this file.
     *
     * @dataProvider roles
     */
    public function test_owner_is_served_the_rival_proposal_card(string $role): void
    {
        $s = $this->scenario($role);

        $this->actingAs($s['owner'])->get($s['url'])
            ->assertOk()
            ->assertSee('privateDataModal' . $s['rivalBid']->id, false);
    }

    /**
     * Defence-in-depth on the amount specifically. The per-card assertion above is the primary
     * proof; this pins the compensation figure on its own, so a future partial that printed a
     * fee outside a bid card would still be caught.
     *
     * @dataProvider roles
     */
    public function test_competing_agent_never_sees_rival_amount(string $role): void
    {
        $s = $this->scenario($role);

        $response = $this->actingAs($s['mine'])->get($s['url']);
        $response->assertOk();

        // Raw and money-formatted renderings of the same figure.
        $response->assertDontSee(self::RIVAL_BROKERAGE);
        $response->assertDontSee('987,654.32');
        $response->assertDontSee('987654');
    }

    /**
     * Positive control for the amount, asserted on the one role whose fee display these minimal
     * fixtures actually reach.
     *
     * Being explicit about why this is not a four-role test: each role resolves its fee display
     * from a different `purchase_fee_type` vocabulary (the Landlord branch matches 'Flat Fee',
     * the Seller branch matches lowercase 'flat', and so on), so a single fixture shape only
     * lights up one of them. Rather than assert a four-role positive control that would quietly
     * be vacuous for three of them, this proves the amount pathway is renderable at all — and
     * the per-card assertions above carry the four-role guarantee.
     */
    public function test_owner_page_renders_the_rival_amount_for_landlord(): void
    {
        $s = $this->scenario('landlord');

        $response = $this->actingAs($s['owner'])->get($s['url']);
        $response->assertOk();

        $this->assertStringContainsString(
            '987,654.32',
            $response->getContent(),
            'The Landlord owner page must render the rival fee, otherwise the amount-absence '
            . 'assertions would have nothing to detect.'
        );
    }

    /** @dataProvider roles */
    public function test_competing_agent_sees_no_removed_disclosure_copy(string $role): void
    {
        $s = $this->scenario($role);

        $response = $this->actingAs($s['mine'])->get($s['url']);
        $response->assertOk();

        foreach (self::REMOVED_COPY as $copy) {
            $response->assertDontSee($copy);
        }
    }

    /**
     * An agent who has not bid is the case the old "submit to view" rule was built around:
     * they were shown the existence and count of competing bids as an inducement. They must
     * now be served nothing at all.
     *
     * @dataProvider roles
     */
    public function test_agent_who_has_not_bid_is_served_nothing(string $role): void
    {
        $s       = $this->scenario($role);
        $noBidder = User::factory()->create(['user_type' => 'agent']);

        $response = $this->actingAs($noBidder)->get($s['url']);
        $response->assertOk();

        $this->assertSame([], $this->servedBidIds($response), "A non-bidding {$role} agent must be served an empty set.");
        $response->assertDontSee('privateDataModal' . $s['rivalBid']->id, false);
        $response->assertDontSee('privateDataModal' . $s['myBid']->id, false);
        $response->assertDontSee(self::RIVAL_BROKERAGE);

        foreach (self::REMOVED_COPY as $copy) {
            $response->assertDontSee($copy);
        }
    }

    /** @dataProvider roles */
    public function test_guest_is_served_nothing(string $role): void
    {
        $s = $this->scenario($role);

        $response = $this->get($s['url']);
        $response->assertOk();

        $this->assertSame([], $this->servedBidIds($response), "A guest must be served no {$role} proposals.");
        $response->assertDontSee('privateDataModal' . $s['rivalBid']->id, false);
        $response->assertDontSee(self::RIVAL_BROKERAGE);
    }

    /**
     * An administrator gets exactly what any other non-owner gets. This checkpoint adds no
     * administrator path; the test exists so that adding one cannot happen by accident.
     *
     * @dataProvider roles
     */
    public function test_administrator_is_served_nothing_by_this_checkpoint(string $role): void
    {
        $s     = $this->scenario($role);
        $admin = User::factory()->create(['user_type' => 'admin']);

        $response = $this->actingAs($admin)->get($s['url']);
        $response->assertOk();

        $this->assertSame([], $this->servedBidIds($response));
        $response->assertDontSee('privateDataModal' . $s['rivalBid']->id, false);
        $response->assertDontSee(self::RIVAL_BROKERAGE);
    }

    /**
     * Owner review is symmetric with the privacy rule: the owner may see the rival's data,
     * so a test suite that only ever asserted absence could pass against a page that shows
     * nobody anything. This pins the positive case.
     *
     * @dataProvider roles
     */
    public function test_owner_does_see_rival_data_that_competitors_cannot(string $role): void
    {
        $s = $this->scenario($role);

        $response = $this->actingAs($s['owner'])->get($s['url']);
        $response->assertOk();

        $this->assertContains(
            $s['rivalBid']->id,
            $this->servedBidIds($response),
            "The {$role} owner must be served the rival proposal — otherwise these tests would pass on a blank page."
        );

        // POSITIVE CONTROL. The absence assertions elsewhere in this file are only meaningful
        // if the rival's card is something this page can render at all.
        $response->assertSee('privateDataModal' . $s['rivalBid']->id, false);
    }

    // ── Create Offer is untouched ────────────────────────────────────────────

    /**
     * Milestone 2 is Hire Agent only. Create Offer keeps its own, separately-audited
     * competing-bid feed, which is governed by PublicOfferFeedService rather than by
     * HireAgentProposalAccess. If this checkpoint had reached into Create Offer, the shared
     * partial or its two include sites would be the first casualty.
     */
    public function test_create_offer_competing_bid_feed_is_left_intact(): void
    {
        $partial = resource_path('views/offer-listing/partials/_competing-bids.blade.php');

        $this->assertFileExists($partial, 'The Create Offer competing-bids partial must not be removed by this checkpoint.');

        $body = file_get_contents($partial);
        $this->assertStringContainsString('PublicOfferFeedService', $body);
        $this->assertStringContainsString('$canViewBidFeed', $body);

        foreach (['seller', 'landlord'] as $role) {
            $view = file_get_contents(resource_path("views/offer-listing/{$role}/view.blade.php"));
            $this->assertStringContainsString(
                "@include('offer-listing.partials._competing-bids'",
                $view,
                "The Create Offer {$role} view must still include the competing-bids partial."
            );
        }
    }

    /**
     * The inverse of what this test asserted at the first checkpoint.
     *
     * At the first checkpoint the four Hire detail views stopped calling the legacy
     * competing-bids stack, but the stack itself was deliberately left standing, and this test
     * asserted its survival so that "stopped before deletion" was checkable rather than a claim
     * in a commit message. The second checkpoint is that deletion, so the assertion inverts:
     * the components must now be gone.
     *
     * Structural absence only. The reachability half — that the retired URLs 404 for every
     * viewer rather than redirecting — lives in HireAgentCompetingBidsRetirementTest.
     *
     * Absence is asserted on FILES, not via class_exists(). class_exists() is the wrong probe
     * for a deleted class: Composer's optimized classmap still maps the old FQCN to its path
     * until `composer dump-autoload` is re-run, so the call tries to include a file that is no
     * longer there and raises an ErrorException instead of cleanly returning false. That would
     * fail this test for a build-artifact reason rather than a code reason, and — worse — it
     * would keep failing after someone "fixed" the deletion. The file is the fact; the classmap
     * is a cache of it.
     */
    public function test_legacy_competing_bid_components_are_retired(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Services/CompetingBidsService.php'),
            'CompetingBidsService must be deleted by this checkpoint.'
        );
        $this->assertFileDoesNotExist(
            app_path('Http/Controllers/CompetingBidsController.php'),
            'CompetingBidsController must be deleted by this checkpoint.'
        );
        $this->assertFileDoesNotExist(
            app_path('Models/BiddingPeriodAgentMapping.php'),
            'BiddingPeriodAgentMapping must be deleted by this checkpoint.'
        );
        $this->assertFileDoesNotExist(
            resource_path('views/tenant_agent/competing_bids.blade.php'),
            'The dedicated competing-bids view must be deleted by this checkpoint.'
        );

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($r) => $r->getActionName())
            ->filter(fn ($a) => str_contains($a, 'CompetingBidsController'));

        $this->assertCount(
            0,
            $routes,
            'No route may still point at CompetingBidsController.'
        );
    }

    /**
     * The table outlives the model. Dropping `bidding_period_agent_mappings` is a schema change
     * and was explicitly out of scope for this checkpoint, so the migration stays and the table
     * is still built. Only the Eloquent model and its single caller are gone.
     */
    public function test_bidding_period_agent_mapping_table_is_retained(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasTable('bidding_period_agent_mappings'),
            'The mapping table must NOT be dropped by this checkpoint — only the model was deleted.'
        );

        $this->assertFileExists(
            database_path('migrations/2026_01_07_053518_create_bidding_period_agent_mappings_table.php'),
            'The mapping table migration must be retained.'
        );
    }
}
