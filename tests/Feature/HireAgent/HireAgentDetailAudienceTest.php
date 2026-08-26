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
use App\Services\HireAgent\HireAgentDetailAudience;
use App\Services\HireAgent\HireAgentProposalAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The audience decision, on its own, before anything renders differently.
 *
 * This ships ahead of the UI that consumes it on purpose. The sections it will gate — Referral &
 * Cooperation Terms and Agent Credentials & Contact Info — render for every visitor today, so
 * gating them NARROWS what is published. A narrowing is an authorization change, and the
 * standing rule in docs/investigations/hire-agent-compensation-visibility-decision.md is that a
 * permission change ships as its own change with its own tests rather than inside a UI milestone.
 * This file is that change's tests; nothing on any page moves yet.
 *
 * THE TWO HALVES ARE TESTED AS TWO HALVES, because each is separately sufficient to break it:
 *
 *   · WHO IS AN AGENT. Three user_type values name agents, not one. A rule written as
 *     `user_type === 'agent'` looks right, passes a test that only ever mints an 'agent', and
 *     silently demotes every buyer_agent and seller_agent to a consumer. So all three are
 *     exercised by name, and the constant is checked against the database constraint that defines
 *     the vocabulary — the one place a fourth agent type would appear first.
 *
 *   · WHAT ATTACHES THEM TO THIS LISTING. Being an agent was explicitly ruled insufficient. The
 *     discriminating case is therefore the agent with NO relationship, and it is asserted for
 *     every role, because it is the one a bare user_type check would get wrong while every other
 *     case in this file still passed.
 *
 * DENIAL IS ASSERTED POSITIVELY ALONGSIDE ITS COMPLEMENT, always. "A stranger is not an agent
 * audience" passes just as happily when the method returns false for everybody, which is the
 * vacuous pass HireAgentDetailViewPrivacyTest records falling into for real. Every deny case here
 * has an allow case next to it built from the same fixture.
 */
class HireAgentDetailAudienceTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, array{0: string}> */
    public static function roles(): array
    {
        return [
            'seller'   => ['seller'],
            'buyer'    => ['buyer'],
            'landlord' => ['landlord'],
            'tenant'   => ['tenant'],
        ];
    }

    /** @return array<string, array{0: string}> */
    public static function agentUserTypes(): array
    {
        return [
            'agent'        => ['agent'],
            'buyer_agent'  => ['buyer_agent'],
            'seller_agent' => ['seller_agent'],
        ];
    }

    /**
     * The user types that are NOT agents.
     *
     * 'admin' is here deliberately. No administrator path exists on this page — the sibling
     * service grants administrators nothing — and an audience helper is the wrong place to invent
     * one. An admin reads this page as a consumer, and that is a decision rather than an oversight.
     *
     * @return array<string, array{0: string}>
     */
    public static function nonAgentUserTypes(): array
    {
        return [
            'buyer'  => ['buyer'],
            'seller' => ['seller'],
            'tenant' => ['tenant'],
            'admin'  => ['admin'],
        ];
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

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

    /**
     * forceCreate for the same reason the sibling suite gives: Landlord and Tenant guard mass
     * assignment while Seller and Buyer use `$guarded = []`, and the fixtures bend rather than the
     * production models.
     */
    private function makeListing(string $role, User $owner): Model
    {
        [$auctionClass] = $this->classesFor($role);

        return $auctionClass::forceCreate([
            'user_id'     => $owner->id,
            'title'       => ucfirst($role) . ' audience listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
    }

    private function makeProposal(string $role, Model $listing, User $agent): Model
    {
        [, $bidClass, $fk] = $this->classesFor($role);

        return $bidClass::forceCreate([$fk => $listing->id, 'user_id' => $agent->id]);
    }

    private function user(string $type): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    private function audience(): HireAgentDetailAudience
    {
        return app(HireAgentDetailAudience::class);
    }

    // ── Who is an agent USER ─────────────────────────────────────────────────

    /**
     * All three agent user_types are recognised.
     *
     * @dataProvider agentUserTypes
     */
    public function test_every_agent_user_type_is_recognised(string $type): void
    {
        $this->assertTrue(
            $this->audience()->isAgentUser($this->user($type)),
            "[{$type}] is an agent user_type and must be recognised as one."
        );
    }

    /** @dataProvider nonAgentUserTypes */
    public function test_non_agent_user_types_are_not_agents(string $type): void
    {
        $this->assertFalse(
            $this->audience()->isAgentUser($this->user($type)),
            "[{$type}] must not be treated as an agent."
        );
    }

    public function test_a_guest_is_not_an_agent_user(): void
    {
        $this->assertFalse($this->audience()->isAgentUser(null));
    }

    /**
     * The constant matches the database's own vocabulary.
     *
     * `users_user_type_check` is where a fourth agent type would appear first, and a type added
     * there but not here would be silently demoted to a consumer — the failure mode that is
     * invisible in every other test in this file, because a demoted agent simply sees the consumer
     * page and nothing errors.
     *
     * Asserted against the MIGRATION SOURCE rather than by querying the constraint, because the
     * constraint is Postgres-only and the suite runs on SQLite (CLAUDE.md), so a live query would
     * assert nothing here and would pass vacuously. The file is the fact available in this
     * environment.
     */
    public function test_the_agent_user_type_list_is_drawn_from_the_database_constraint(): void
    {
        $source = (string) file_get_contents(
            base_path('database/migrations/2026_04_29_000002_add_tenant_to_users_user_type_check.php')
        );

        $this->assertNotSame('', $source, 'The constraint migration must exist to be the source of truth.');

        preg_match("/user_type IN \(([^)]+)\)/", $source, $m);
        $this->assertNotEmpty($m, 'Could not read the permitted user_type list from the migration.');

        $permitted = array_map(
            fn ($v) => trim($v, " '"),
            explode(',', $m[1])
        );

        foreach (HireAgentDetailAudience::AGENT_USER_TYPES as $agentType) {
            $this->assertContains(
                $agentType,
                $permitted,
                "[{$agentType}] is not a storable user_type — the list has drifted from the schema."
            );
        }

        // The other direction: every permitted type is classified, so a NEW one cannot be added to
        // the schema and quietly land on the consumer side without this failing.
        $accountedFor = array_merge(
            HireAgentDetailAudience::AGENT_USER_TYPES,
            ['admin', 'buyer', 'seller', 'tenant'],
        );

        $this->assertSame(
            [],
            array_values(array_diff($permitted, $accountedFor)),
            'A user_type exists in the schema that this audience rule has never classified. '
            . 'Decide whether it is an agent — do not let it default to consumer by omission.'
        );
    }

    // ── The relationship half ────────────────────────────────────────────────

    /**
     * The discriminating case: an agent with no connection to this listing is a CONSUMER.
     *
     * This is what "a generic user_type check is not sufficient" means in practice, and it is the
     * only assertion here that a bare user_type rule would fail. Its complement — the same agent,
     * now with a proposal — is asserted immediately below from the same fixture, so this cannot
     * pass by the method being broken for everyone.
     *
     * @dataProvider roles
     */
    public function test_an_agent_with_no_relationship_to_the_listing_is_a_consumer(string $role): void
    {
        $listing   = $this->makeListing($role, $this->user('buyer'));
        $stranger  = $this->user('agent');

        $this->assertFalse(
            $this->audience()->isAgentAudience($stranger, $listing),
            "{$role}: an unconnected agent must not reach the agent audience."
        );
        $this->assertSame(
            HireAgentDetailAudience::AUDIENCE_PUBLIC,
            $this->audience()->audienceFor($stranger, $listing)
        );
    }

    /** @dataProvider roles */
    public function test_an_agent_who_submitted_a_proposal_is_the_agent_audience(string $role): void
    {
        $listing = $this->makeListing($role, $this->user('buyer'));
        $agent   = $this->user('agent');

        $this->assertFalse(
            $this->audience()->isAgentAudience($agent, $listing),
            "Precondition: {$role} agent starts unconnected."
        );

        $this->makeProposal($role, $listing, $agent);

        $this->assertTrue(
            $this->audience()->isAgentAudience($agent, $listing),
            "{$role}: submitting a proposal must qualify the agent."
        );
        $this->assertSame(
            HireAgentDetailAudience::AUDIENCE_AGENT,
            $this->audience()->audienceFor($agent, $listing)
        );
    }

    /**
     * A proposal on a DIFFERENT listing does not qualify.
     *
     * The relationship is to the request being read, not to the platform. Without this, an agent
     * who has bid anywhere would be an agent audience everywhere — which is the bare user_type
     * rule wearing a relationship's clothes.
     *
     * @dataProvider roles
     */
    public function test_a_proposal_on_another_listing_does_not_qualify(string $role): void
    {
        $owner = $this->user('buyer');
        $mine  = $this->makeListing($role, $owner);
        $other = $this->makeListing($role, $owner);
        $agent = $this->user('agent');

        $this->makeProposal($role, $other, $agent);

        $this->assertTrue(
            $this->audience()->isAgentAudience($agent, $other),
            "Precondition: {$role} agent qualifies on the listing they bid on."
        );
        $this->assertFalse(
            $this->audience()->isAgentAudience($agent, $mine),
            "{$role}: a proposal elsewhere must not qualify the agent here."
        );
    }

    /**
     * An agent who OWNS the request is the agent audience — the agent-posted referral.
     *
     * @dataProvider roles
     */
    public function test_an_agent_who_owns_the_listing_is_the_agent_audience(string $role): void
    {
        $agentOwner = $this->user('agent');
        $listing    = $this->makeListing($role, $agentOwner);

        $this->assertTrue(
            $this->audience()->isAgentAudience($agentOwner, $listing),
            "{$role}: an agent reading their own request must reach the agent audience."
        );
    }

    /**
     * A CONSUMER who owns the request reaches the OWNER tier, not the agent one.
     *
     * The complement of the test above, and the one that proves the agent half of the owner branch
     * is load-bearing rather than decorative. The owner tier is what gives the client Services and
     * Broker Compensation — the material they evaluate proposals against — without giving them the
     * agent-to-agent appendix.
     *
     * @dataProvider roles
     */
    public function test_a_client_who_owns_the_listing_is_the_owner_audience(string $role): void
    {
        $owner   = $this->user('buyer');
        $listing = $this->makeListing($role, $owner);

        $this->assertFalse(
            $this->audience()->isAgentAudience($owner, $listing),
            "{$role}: the client reading their own request is not an agent."
        );
        $this->assertTrue($this->audience()->isOwnerAudience($owner, $listing));
        $this->assertSame(
            HireAgentDetailAudience::AUDIENCE_OWNER,
            $this->audience()->audienceFor($owner, $listing),
            "{$role}: the client evaluating bids on their own request is the owner audience."
        );
    }

    /**
     * An AGENT who owns the request resolves to the wider tier, not the narrower one.
     *
     * They satisfy both branches — they own it, and they are an agent with a relationship to it —
     * and the order of the checks in audienceFor() is the only thing deciding which wins. Testing
     * ownership first would withhold the referral terms and credentials from the one viewer the
     * agent sections exist to serve, silently, on their own agent-to-agent listing.
     *
     * @dataProvider roles
     */
    public function test_an_agent_who_owns_the_listing_resolves_to_the_wider_agent_tier(string $role): void
    {
        $agentOwner = $this->user('agent');
        $listing    = $this->makeListing($role, $agentOwner);

        $this->assertTrue($this->audience()->isOwnerAudience($agentOwner, $listing), 'Precondition: they own it.');

        $this->assertSame(
            HireAgentDetailAudience::AUDIENCE_AGENT,
            $this->audience()->audienceFor($agentOwner, $listing),
            "{$role}: an agent-owned request must resolve to the agent tier, not the owner tier."
        );
    }

    /**
     * A viewer who owns nothing and proposed nothing is the public tier, whatever their account is.
     *
     * @dataProvider roles
     */
    public function test_an_unrelated_authenticated_viewer_is_the_public_audience(string $role): void
    {
        $listing  = $this->makeListing($role, $this->user('buyer'));
        $stranger = $this->user('buyer');

        $this->assertFalse($this->audience()->isOwnerAudience($stranger, $listing));
        $this->assertSame(
            HireAgentDetailAudience::AUDIENCE_PUBLIC,
            $this->audience()->audienceFor($stranger, $listing),
            "{$role}: a logged-in stranger reads the public page."
        );
    }

    /**
     * Owning ANOTHER listing does not make you this one's owner.
     *
     * @dataProvider roles
     */
    public function test_owning_another_listing_does_not_qualify(string $role): void
    {
        $person = $this->user('buyer');
        $mine   = $this->makeListing($role, $person);
        $theirs = $this->makeListing($role, $this->user('buyer'));

        $this->assertTrue($this->audience()->isOwnerAudience($person, $mine), 'Precondition.');
        $this->assertFalse($this->audience()->isOwnerAudience($person, $theirs));
        $this->assertSame(
            HireAgentDetailAudience::AUDIENCE_PUBLIC,
            $this->audience()->audienceFor($person, $theirs)
        );
    }

    /**
     * A non-agent cannot qualify by proposing either.
     *
     * @dataProvider roles
     */
    public function test_a_non_agent_with_a_proposal_is_still_a_consumer(string $role): void
    {
        $listing = $this->makeListing($role, $this->user('buyer'));
        $person  = $this->user('buyer');

        $this->makeProposal($role, $listing, $person);

        $this->assertFalse(
            $this->audience()->isAgentAudience($person, $listing),
            "{$role}: a proposal does not make a consumer an agent."
        );
    }

    /** All three agent types qualify through the relationship, not just 'agent'. */
    /** @dataProvider agentUserTypes */
    public function test_every_agent_user_type_can_reach_the_agent_audience(string $type): void
    {
        $listing = $this->makeListing('buyer', $this->user('buyer'));
        $agent   = $this->user($type);

        $this->makeProposal('buyer', $listing, $agent);

        $this->assertTrue(
            $this->audience()->isAgentAudience($agent, $listing),
            "[{$type}] must be able to reach the agent audience."
        );
    }

    // ── Deny is the fallthrough ──────────────────────────────────────────────

    /** @dataProvider roles */
    public function test_a_guest_is_a_consumer(string $role): void
    {
        $listing = $this->makeListing($role, $this->user('buyer'));

        $this->assertFalse($this->audience()->isAgentAudience(null, $listing));
        $this->assertSame(
            HireAgentDetailAudience::AUDIENCE_PUBLIC,
            $this->audience()->audienceFor(null, $listing)
        );
    }

    public function test_a_null_listing_yields_the_consumer_audience(): void
    {
        $this->assertFalse($this->audience()->isAgentAudience($this->user('agent'), null));
        $this->assertSame(
            HireAgentDetailAudience::AUDIENCE_PUBLIC,
            $this->audience()->audienceFor($this->user('agent'), null)
        );
    }

    /**
     * An unsaved listing is refused before any query runs.
     *
     * `hasMany()->exists()` on a keyless parent asks the database a question about a null foreign
     * key, and the answer is not one this rule should be interpreting.
     */
    public function test_an_unsaved_listing_yields_the_consumer_audience(): void
    {
        $this->assertFalse(
            $this->audience()->isAgentAudience($this->user('agent'), new BuyerAgentAuction())
        );
    }

    /**
     * A listing with a NULL owner does not make an agent its owner.
     *
     * A loose comparison here is how the four detail views once made every anonymous visitor the
     * owner of an unowned listing — the defect HireAgentProposalAccess::isListingOwner() was
     * written to close, reproduced in a second class that also compares owners.
     *
     * LANDLORD, NOT BUYER, and the choice is forced rather than arbitrary: `user_id` is nullable
     * on `landlord_agent_auctions` and `tenant_agent_auctions` and NOT NULL on the seller and
     * buyer tables, so an ownerless row is only constructible for two of the four roles. The
     * schema asymmetry CLAUDE.md describes for meta storage extends to this column too.
     */
    public function test_a_null_owner_does_not_match_any_viewer(): void
    {
        $listing = LandlordAgentAuction::forceCreate([
            'user_id'     => null,
            'title'       => 'Ownerless listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        $this->assertNull($listing->fresh()->user_id, 'Precondition: the row really has no owner.');

        $this->assertFalse($this->audience()->isAgentAudience($this->user('agent'), $listing));
    }

    // ── Ordering independence ────────────────────────────────────────────────

    /**
     * The answer does not depend on whether proposal narrowing has already run.
     *
     * The controllers call HireAgentProposalAccess::restrictLoadedProposals() on the same request,
     * and it REPLACES the loaded `bids` relation with the authorized subset. A rule reading that
     * relation would answer differently before and after — a real ordering bug that would look
     * like an intermittent one, since it only shows when the two calls are reordered.
     *
     * Asserted for a competing agent specifically: narrowing leaves them only their own proposal,
     * which is exactly the case where reading the loaded relation would coincidentally still work
     * for THEM while failing for the owner. Both are checked.
     *
     * @dataProvider roles
     */
    public function test_the_audience_is_unchanged_by_proposal_narrowing(string $role): void
    {
        $agentOwner = $this->user('agent');
        $listing    = $this->makeListing($role, $agentOwner);

        $bidder   = $this->user('agent');
        $stranger = $this->user('agent');

        $this->makeProposal($role, $listing, $bidder);

        foreach ([
            'owner'    => [$agentOwner, true],
            'bidder'   => [$bidder,     true],
            'stranger' => [$stranger,   false],
        ] as $label => [$viewer, $expected]) {
            $fresh = $listing->fresh();
            $fresh->load('bids');

            $before = $this->audience()->isAgentAudience($viewer, $fresh);

            app(HireAgentProposalAccess::class)->restrictLoadedProposals($viewer->id, $fresh);

            $after = $this->audience()->isAgentAudience($viewer, $fresh);

            $this->assertSame($expected, $before, "{$role}/{$label}: wrong answer before narrowing.");
            $this->assertSame($expected, $after, "{$role}/{$label}: narrowing changed the answer.");
        }
    }

    // ── The view is handed the answer ────────────────────────────────────────

    /** @return array{0: string, 1: class-string, 2: class-string, 3: string} */
    private function wiringFor(string $role): array
    {
        return match ($role) {
            'seller'   => ['seller.agent.auction.detail',  SellerAgentAuction::class,   SellerAgentAuctionBid::class,   'seller_agent_auction_id'],
            'buyer'    => ['buyer.view-auction',           BuyerAgentAuction::class,    BuyerAgentAuctionBid::class,    'buyer_agent_auction_id'],
            'landlord' => ['landlord.agent.auction.view',  LandlordAgentAuction::class, LandlordAgentAuctionBid::class, 'landlord_agent_auction_id'],
            'tenant'   => ['tenant.agent.auction.view',    TenantAgentAuction::class,   TenantAgentAuctionBid::class,   'tenant_agent_auction_id'],
        };
    }

    /**
     * All four controllers hand the view an audience, and it is the right one.
     *
     * Asserted through the rendered response's view data rather than by reading the controller,
     * because "the value was computed" and "the view received it" are different claims and only
     * the second one matters. Nothing renders from it yet, which is the point of this step.
     *
     * @dataProvider roles
     */
    public function test_all_four_controllers_hand_the_view_the_audience(string $role): void
    {
        [$route] = $this->wiringFor($role);

        $owner   = $this->user('buyer');
        $listing = $this->makeListing($role, $owner);
        $listing->saveMeta('workflow_type', 'hire_agent');
        if (in_array($role, ['seller', 'buyer'], true)) {
            $listing->address = '100 Shell Street';
            $listing->save();
        } else {
            $listing->saveMeta('address', '100 Shell Street');
        }

        $agent = $this->user('agent');

        // A guest reads the public page.
        $this->get(route($route, $listing->id))
            ->assertOk()
            ->assertViewHas('hlaAudience', HireAgentDetailAudience::AUDIENCE_PUBLIC);

        // The client reading their own request is the owner tier.
        $this->actingAs($owner)
            ->get(route($route, $listing->id))
            ->assertOk()
            ->assertViewHas('hlaAudience', HireAgentDetailAudience::AUDIENCE_OWNER);

        // An unconnected agent is still public — the discriminating case, through the wiring.
        $this->actingAs($agent)
            ->get(route($route, $listing->id))
            ->assertOk()
            ->assertViewHas('hlaAudience', HireAgentDetailAudience::AUDIENCE_PUBLIC);

        // Now attach them.
        $this->makeProposal($role, $listing, $agent);

        $this->actingAs($agent)
            ->get(route($route, $listing->id))
            ->assertOk()
            ->assertViewHas('hlaAudience', HireAgentDetailAudience::AUDIENCE_AGENT);
    }

    /**
     * Nothing renders from the audience yet, asserted as a fact about the code.
     *
     * This step is the authorization decision alone; the sections that consume it arrive later, so
     * the narrowing and the layout change never ship together. That is the property the sequencing
     * exists to protect and it deserves an assertion.
     *
     * A BYTE COMPARISON OF TWO RENDERED PAGES WAS TRIED FIRST AND IS THE WRONG PROBE. Every
     * relationship that flips the audience — owning the listing, having proposed on it — also
     * changes what HireAgentProposalAccess hands the view, so two viewers on opposite sides of the
     * audience line necessarily differ in their proposal markup. The diff is real and has nothing
     * to do with the audience, and a test that reads it as an audience change would be measuring
     * proposal privacy while claiming to measure this.
     *
     * So the claim is made where it is exact: no Blade file reads the variable. That is precisely
     * what "handed to the view, consumed by nothing" means, and unlike a rendered diff it cannot
     * be satisfied by coincidence.
     *
     * THE LIST GREW, DELIBERATELY. It shipped empty — the audience was decided and handed over
     * with nothing rendering from it, so the narrowing could not ride along inside a UI change.
     * Buyer is the first consumer, added with its migration; seller, landlord and tenant follow in
     * their own. A fourth entry appearing is the signal, not a failure to fix by widening this
     * list — the same contract HireAgentDetailRedesignFlagTest holds for the rollout flag.
     */
    public function test_the_audience_consumers_are_exactly_the_migrated_views(): void
    {
        $consumers = [];

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $item) {
            if ($item->isFile() && str_contains((string) file_get_contents($item->getPathname()), 'hlaAudience')) {
                $consumers[] = str_replace(base_path() . '/', '', $item->getPathname());
            }
        }

        sort($consumers);

        $this->assertSame(
            [
                'resources/views/hire_buyer_agent/view.blade.php',
                'resources/views/hire_landlord_agent/view.blade.php',
                // S1. Seller is the LAST role to join, so this list is now all four views and
                // stops distinguishing anybody. It is kept rather than retired because its job
                // was never "which roles have migrated" — it is that no OTHER file starts
                // reading the audience, and a fifth entry appearing here is the thing to catch.
                // Like the other three it PASSES the value and never compares it — asserted
                // below.
                'resources/views/hire_seller_agent/view.blade.php',
                'resources/views/hire_tenant_agent/view.blade.php',
            ],
            $consumers,
            'The set of views consuming the audience must stay known.'
        );
    }

    /**
     * The one consumer PASSES the audience and never tests it.
     *
     * Reading the value is fine; comparing it is not. A Blade file branching on the tier would be a
     * second opinion about a rule that already has an owner, and a nav bar is where such a drift
     * becomes a disclosure. The resolver exists so a view never has to ask.
     */
    public function test_the_consuming_view_passes_the_audience_without_testing_it(): void
    {
        $src = (string) file_get_contents(resource_path('views/hire_buyer_agent/view.blade.php'));

        $this->assertStringContainsString('$hlaAudience', $src, 'Precondition: it is consumed.');

        $this->assertDoesNotMatchRegularExpression(
            '/\$hlaAudience\s*(===|!==|==|!=)/',
            $src,
            'The view compared the audience directly instead of passing it to the resolver.'
        );

        foreach (['AUDIENCE_AGENT', 'AUDIENCE_OWNER', 'AUDIENCE_PUBLIC'] as $constant) {
            $this->assertStringNotContainsString(
                $constant,
                $src,
                "The view named [{$constant}], which is a tier decision living in markup."
            );
        }
    }

    /**
     * A characterisation of the inline user_type checks that already exist, so they cannot spread.
     *
     * The four views carry fourteen of these between them, in TWO KINDS that are easy to confuse
     * and must not be — counted separately here for that reason.
     *
     *   · OWNER-IS-AGENT (`$auction->user->user_type`), one per role. The Owner Info heading flip:
     *     it asks about the LISTING OWNER, not the viewer, and it is the exact predicate the Agent
     *     Credentials section will need. It is not replaced by this service, which answers a
     *     different question about a different person.
     *
     *   · VIEWER-IS-AGENT (`auth()->user()->user_type`), ten across the four. These gate proposal
     *     and bid-CTA markup. This is the bare check that was rejected as insufficient for the
     *     audience — but it governs who may ACT, not which sections are published, so replacing it
     *     changes bidding behaviour and belongs to its own change, not to this one.
     *
     * BOTH KINDS NAME ONLY 'agent', AND THAT IS A LATENT DEFECT THIS TEST RECORDS RATHER THAN
     * FIXES. `buyer_agent` and `seller_agent` are storable user_types that this application treats
     * as agents elsewhere, so today they get no bid CTA and their listings never say "Agent's
     * Info". Correcting either changes who can act or what a page says about a real user, so it is
     * deliberately not smuggled into an authorization-plumbing step. The new service is written
     * correctly from the start, which is why AGENT_USER_TYPES has three entries and these have one.
     *
     * The numbers are a lock, not a target. Any change to them should be intentional and should
     * update this list in the same commit.
     */
    public function test_the_inline_user_type_checks_are_the_known_set(): void
    {
        $ownerIsAgent  = [];
        $viewerIsAgent = [];

        foreach (['seller', 'buyer', 'landlord', 'tenant'] as $role) {
            $src = (string) file_get_contents(
                resource_path("views/hire_{$role}_agent/view.blade.php")
            );

            $ownerIsAgent[$role]  = preg_match_all('/\$auction->user->user_type/', $src);
            $viewerIsAgent[$role] = preg_match_all('/auth\(\)->user\(\)\)?->user_type/', $src);
        }

        $this->assertSame(
            ['seller' => 1, 'buyer' => 1, 'landlord' => 1, 'tenant' => 1],
            $ownerIsAgent,
            'The Owner Info heading flip is one per role. A second would be a duplicate opinion '
            . 'about the same question, in the same file.'
        );

        $this->assertSame(
            ['seller' => 3, 'buyer' => 2, 'landlord' => 2, 'tenant' => 3],
            $viewerIsAgent,
            'A view grew a new inline viewer-is-agent check. Section visibility must read the '
            . 'audience the controller hands it; if this is bid-CTA markup instead, update the '
            . 'count deliberately and say so.'
        );
    }
}
