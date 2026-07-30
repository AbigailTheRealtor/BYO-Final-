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
use App\Services\HireAgent\HireAgentProposalAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Milestone 2, first checkpoint — the authorization half of Hire Agent proposal privacy.
 *
 * HireAgentProposalAccess is the authoritative access layer. Every assertion here is about
 * the SERVICE, not about rendered markup: the disclosure half lives in
 * HireAgentDetailViewPrivacyTest. Splitting them is deliberate — a privacy rule that is only
 * enforced in Blade is one refactor away from leaking, so the rule has to hold at a layer
 * the view cannot bypass.
 *
 * The matrix below is asserted for all four roles, because CLAUDE.md's role symmetry means a
 * rule proven for Seller says nothing about Landlord (native columns vs EAV meta, and four
 * independent controllers).
 *
 * @see docs/investigations/hire-agent-listing-framework-implementation-plan.md §2.2
 */
class HireAgentProposalAccessTest extends TestCase
{
    use DatabaseTransactions;

    private HireAgentProposalAccess $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = $this->app->make(HireAgentProposalAccess::class);
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * One listing with two competing agent proposals, for the given role.
     *
     * @return array{owner: User, agentA: User, agentB: User, listing: Model, bidA: Model, bidB: Model}
     */
    private function scenario(string $role): array
    {
        $owner  = User::factory()->create(['user_type' => 'seller']);
        $agentA = User::factory()->create(['user_type' => 'agent']);
        $agentB = User::factory()->create(['user_type' => 'agent']);

        [$auctionClass, $bidClass, $fk] = $this->classesFor($role);

        // forceCreate: the Landlord/Tenant models guard mass assignment while Seller/Buyer use
        // `$guarded = []`. Those models are out of scope for this checkpoint, so the fixtures
        // bend rather than the production code.
        $listing = $auctionClass::forceCreate([
            'user_id'     => $owner->id,
            'title'       => ucfirst($role) . ' hire-agent listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $bidA = $bidClass::forceCreate([$fk => $listing->id, 'user_id' => $agentA->id]);
        $bidB = $bidClass::forceCreate([$fk => $listing->id, 'user_id' => $agentB->id]);

        return compact('owner', 'agentA', 'agentB', 'listing', 'bidA', 'bidB');
    }

    /** @return array{0: class-string, 1: class-string, 2: string} */
    private function classesFor(string $role): array
    {
        return match ($role) {
            'seller'   => [SellerAgentAuction::class,   SellerAgentAuctionBid::class,   'seller_agent_auction_id'],
            'buyer'    => [BuyerAgentAuction::class,    BuyerAgentAuctionBid::class,    'buyer_agent_auction_id'],
            'landlord' => [LandlordAgentAuction::class, LandlordAgentAuctionBid::class, 'landlord_agent_auction_id'],
            'tenant'   => [TenantAgentAuction::class,   TenantAgentAuctionBid::class,   'tenant_agent_auction_id'],
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

    // ── Owner may review the full proposal set ───────────────────────────────

    /** @dataProvider roles */
    public function test_listing_owner_may_review_all_proposals(string $role): void
    {
        $s = $this->scenario($role);

        $this->assertTrue(
            $this->access->canReviewAllProposals($s['owner']->id, $s['listing']),
            "The {$role} listing owner must be able to review all proposals."
        );
    }

    /** @dataProvider roles */
    public function test_listing_owner_sees_every_proposal(string $role): void
    {
        $s = $this->scenario($role);

        $visible = $this->access->visibleProposals($s['owner']->id, $s['listing']);

        $this->assertEqualsCanonicalizing(
            [$s['bidA']->id, $s['bidB']->id],
            $visible->pluck('id')->all(),
            "The {$role} owner's review set must contain every submitted proposal."
        );
    }

    /** @dataProvider roles */
    public function test_listing_owner_may_view_each_individual_proposal(string $role): void
    {
        $s = $this->scenario($role);

        foreach (['bidA', 'bidB'] as $key) {
            $this->assertTrue(
                $this->access->canViewProposal($s['owner']->id, $s['listing'], $s[$key]),
                "The {$role} owner must be able to open proposal {$key}."
            );
        }
    }

    // ── A submitting agent may view their own proposal ────────────────────────

    /** @dataProvider roles */
    public function test_submitting_agent_may_view_their_own_proposal(string $role): void
    {
        $s = $this->scenario($role);

        $this->assertTrue(
            $this->access->canViewProposal($s['agentA']->id, $s['listing'], $s['bidA']),
            "A {$role} agent must be able to view their own proposal."
        );
    }

    /** @dataProvider roles */
    public function test_submitting_agent_sees_only_their_own_proposal(string $role): void
    {
        $s = $this->scenario($role);

        $visible = $this->access->visibleProposals($s['agentA']->id, $s['listing']);

        $this->assertSame(
            [$s['bidA']->id],
            $visible->pluck('id')->all(),
            "A {$role} agent's visible set must be exactly their own proposal — no competitor rows."
        );
    }

    // ── A competing agent gets nothing ───────────────────────────────────────

    /** @dataProvider roles */
    public function test_competing_agent_cannot_view_another_agents_proposal(string $role): void
    {
        $s = $this->scenario($role);

        $this->assertFalse(
            $this->access->canViewProposal($s['agentA']->id, $s['listing'], $s['bidB']),
            "A {$role} agent must not be able to view a competitor's proposal."
        );
        $this->assertFalse(
            $this->access->canViewProposal($s['agentB']->id, $s['listing'], $s['bidA']),
            "Denial must be symmetric for {$role}."
        );
    }

    /** @dataProvider roles */
    public function test_competing_agent_may_not_review_all_proposals(string $role): void
    {
        $s = $this->scenario($role);

        $this->assertFalse(
            $this->access->canReviewAllProposals($s['agentA']->id, $s['listing']),
            "A {$role} agent must never hold whole-set review rights."
        );
    }

    /** @dataProvider roles */
    public function test_agent_with_no_proposal_sees_an_empty_set(string $role): void
    {
        $s        = $this->scenario($role);
        $stranger = User::factory()->create(['user_type' => 'agent']);

        $this->assertTrue(
            $this->access->visibleProposals($stranger->id, $s['listing'])->isEmpty(),
            "A {$role} agent who has not bid must see an empty proposal set — not a count, not a summary."
        );
        $this->assertFalse($this->access->canViewProposal($stranger->id, $s['listing'], $s['bidA']));
        $this->assertFalse($this->access->canViewProposal($stranger->id, $s['listing'], $s['bidB']));
    }

    // ── Deny by default ──────────────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_guest_is_denied(string $role): void
    {
        $s = $this->scenario($role);

        $this->assertFalse($this->access->canReviewAllProposals(null, $s['listing']));
        $this->assertFalse($this->access->canViewProposal(null, $s['listing'], $s['bidA']));
        $this->assertTrue($this->access->visibleProposals(null, $s['listing'])->isEmpty());
    }

    /** @dataProvider roles */
    public function test_unrelated_third_party_is_denied(string $role): void
    {
        $s      = $this->scenario($role);
        $stranger = User::factory()->create(['user_type' => 'buyer']);

        $this->assertFalse($this->access->canReviewAllProposals($stranger->id, $s['listing']));
        $this->assertFalse($this->access->canViewProposal($stranger->id, $s['listing'], $s['bidA']));
        $this->assertTrue($this->access->visibleProposals($stranger->id, $s['listing'])->isEmpty());
    }

    /**
     * No administrator access is added by this checkpoint. Requirement 6 names administrators,
     * but no administrator review path exists today and the authorization preserves current
     * owner-only access rather than widening it. If someone later grants admins access, it must
     * be a deliberate, reviewed change — not a silent side effect. This test is the tripwire.
     *
     * @dataProvider roles
     */
    public function test_administrator_is_not_granted_access_by_this_checkpoint(string $role): void
    {
        $s     = $this->scenario($role);
        $admin = User::factory()->create(['user_type' => 'admin']);

        $this->assertFalse(
            $this->access->canReviewAllProposals($admin->id, $s['listing']),
            'This checkpoint must not add administrator review access.'
        );
        $this->assertFalse($this->access->canViewProposal($admin->id, $s['listing'], $s['bidA']));
        $this->assertTrue($this->access->visibleProposals($admin->id, $s['listing'])->isEmpty());
    }

    /**
     * A proposal belonging to a DIFFERENT listing must be refused even for the owner of the
     * listing being asked about. Without this the service would authorize by "is this my
     * listing" alone and a caller could pass any bid id through it.
     *
     * @dataProvider roles
     */
    public function test_proposal_from_another_listing_is_refused_even_for_the_owner(string $role): void
    {
        $mine  = $this->scenario($role);
        $other = $this->scenario($role);

        $this->assertFalse(
            $this->access->canViewProposal($mine['owner']->id, $mine['listing'], $other['bidA']),
            "A proposal from another {$role} listing must never be authorized against this one."
        );
    }

    /** @dataProvider roles */
    public function test_null_proposal_is_refused(string $role): void
    {
        $s = $this->scenario($role);

        $this->assertFalse($this->access->canViewProposal($s['owner']->id, $s['listing'], null));
    }

    public function test_null_listing_is_refused(): void
    {
        $user = User::factory()->create(['user_type' => 'seller']);

        $this->assertFalse($this->access->canReviewAllProposals($user->id, null));
        $this->assertFalse($this->access->canViewProposal($user->id, null, null));
        $this->assertTrue($this->access->visibleProposals($user->id, null)->isEmpty());
    }

    // ── The loaded relation is narrowed, not merely re-read ──────────────────

    /**
     * restrictLoadedProposals is the seam the controllers use. After it runs, the loaded
     * relation itself must hold only authorized rows — this is what makes "no competing data
     * is returned and later hidden by Blade" true rather than aspirational.
     *
     * @dataProvider roles
     */
    public function test_restrict_loaded_proposals_narrows_the_relation_for_an_agent(string $role): void
    {
        $s = $this->scenario($role);

        [$auctionClass] = $this->classesFor($role);
        $listing = $auctionClass::with('bids')->find($s['listing']->id);

        $this->assertCount(2, $listing->bids, 'Sanity: both proposals load before restriction.');

        $this->access->restrictLoadedProposals($s['agentA']->id, $listing);

        $this->assertSame(
            [$s['bidA']->id],
            $listing->bids->pluck('id')->all(),
            "After restriction the loaded {$role} relation must hold only the viewer's own proposal."
        );
    }

    /** @dataProvider roles */
    public function test_restrict_loaded_proposals_empties_the_relation_for_a_stranger(string $role): void
    {
        $s        = $this->scenario($role);
        $stranger = User::factory()->create(['user_type' => 'agent']);

        [$auctionClass] = $this->classesFor($role);
        $listing = $auctionClass::with('bids')->find($s['listing']->id);

        $this->access->restrictLoadedProposals($stranger->id, $listing);

        $this->assertTrue(
            $listing->bids->isEmpty(),
            "A {$role} stranger's loaded relation must be empty, so no count survives either."
        );
    }

    /** @dataProvider roles */
    public function test_restrict_loaded_proposals_preserves_the_full_set_for_the_owner(string $role): void
    {
        $s = $this->scenario($role);

        [$auctionClass] = $this->classesFor($role);
        $listing = $auctionClass::with('bids')->find($s['listing']->id);

        $this->access->restrictLoadedProposals($s['owner']->id, $listing);

        $this->assertEqualsCanonicalizing(
            [$s['bidA']->id, $s['bidB']->id],
            $listing->bids->pluck('id')->all(),
            "Owner review must survive restriction for {$role} — this is the path accept/reject/counter uses."
        );
    }

    /**
     * The viewer's own-proposal signals the views depend on ($my_bid, $userHasBid) must still
     * resolve from the restricted relation. If restriction broke these, an agent would lose
     * sight of their own bid — the opposite of the intent.
     *
     * @dataProvider roles
     */
    public function test_own_proposal_signals_survive_restriction(string $role): void
    {
        $s = $this->scenario($role);

        [$auctionClass] = $this->classesFor($role);
        $listing = $auctionClass::with('bids')->find($s['listing']->id);

        $this->access->restrictLoadedProposals($s['agentA']->id, $listing);

        // $my_bid
        $this->assertNotNull(
            $listing->bids->where('user_id', $s['agentA']->id)->first(),
            "\$my_bid must still resolve for a {$role} agent after restriction."
        );
        // $userHasBid
        $this->assertTrue($listing->bids->where('user_id', $s['agentA']->id)->isNotEmpty());
        // and nothing about the competitor
        $this->assertTrue($listing->bids->where('user_id', $s['agentB']->id)->isEmpty());
    }
}
