<?php

namespace Tests\Feature\HireAgent;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionBid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M5.5 — the Hire Agent proposal console.
 *
 * THREE SEPARATE CLAIMS, AND THEY ARE NOT THE SAME CLAIM.
 * ------------------------------------------------------
 * It matters which test proves what here, because the milestone deliberately did NOT touch the
 * privacy mechanism and a reader should not come away thinking it did.
 *
 *   1. WITHHOLDING is server-side and unchanged. HireAgentProposalAccess narrows $auction->bids in
 *      the controller before the view runs. That claim belongs to
 *      HireAgentBidCtaTest::test_the_view_is_handed_only_authorized_proposals, which asserts
 *      against the collection the view is handed rather than against rendered HTML — mutation
 *      testing in M5.4 showed it is the ONLY test that fails when the narrowing is removed. This
 *      file does not re-prove it and must not be trusted to.
 *
 *   2. The CONSOLE CONTAINER is a display decision layered on top of (1). An empty
 *      `card higestBider` used to render for every viewer the access layer handed nothing —
 *      guest, competing agent, unrelated user, administrator. It disclosed no data; it was
 *      residue, and M5.3/M5.4 made it conspicuous by clearing everything around it. The tests
 *      below assert it is gone for exactly those viewers and present for exactly the two who have
 *      something to see. If this guard were deleted, no proposal data would leak — which is why
 *      it is allowed to be a Blade condition at all.
 *
 *   3. The CARD EXTRACTION is a mechanical move. The proposal card left the view for a partial;
 *      the per-card gates went with it verbatim. Asserted here by rendering, not by reading
 *      source, except where the assertion IS about source (the `continue` guard, which cannot
 *      live in the partial).
 *
 * FLAG DISCIPLINE. Every assertion about the redesigned treatment enables the flag explicitly.
 * The legacy page is asserted unchanged with the flag off, because that is the state production
 * runs in and the state a reviewer will check first.
 */
class HireAgentProposalConsoleTest extends TestCase
{
    use RefreshDatabase;

    /** Planted in the RIVAL's proposal — must never reach anyone but the owner. */
    private const RIVAL_AMOUNT = '987654.32';

    /** Planted in the viewer's OWN proposal. */
    private const OWN_AMOUNT = '111222.33';

    /** The console container's class, unchanged since long before M5 and used as its marker. */
    private const CONSOLE = 'higestBider';

    // ── Fixtures ─────────────────────────────────────────────────────────────

    /**
     * @return array{owner: User, mine: User, rival: User, outsider: User, admin: User,
     *               stranger: User, listing: LandlordAgentAuction}
     */
    private function scenario(): array
    {
        $owner    = User::factory()->create(['user_type' => 'seller']);
        $mine     = User::factory()->create(['user_type' => 'agent']);
        $rival    = User::factory()->create(['user_type' => 'agent']);
        $stranger = User::factory()->create(['user_type' => 'agent']);   // an agent who has not bid
        $outsider = User::factory()->create(['user_type' => 'seller']);  // authenticated, unrelated
        $admin    = User::factory()->create(['user_type' => 'admin']);

        $listing = LandlordAgentAuction::forceCreate([
            'user_id'     => $owner->id,
            'title'       => 'Landlord proposal-console listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $listing->saveMeta('address', '100 Test Street');
        $listing->saveMeta('budget', '4321');

        return compact('owner', 'mine', 'rival', 'stranger', 'outsider', 'admin', 'listing');
    }

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

    /** Render as $viewer (null = guest) and return the HTML. */
    private function render(LandlordAgentAuction $listing, ?User $viewer): string
    {
        if ($viewer) {
            $this->actingAs($viewer);
        } else {
            app('auth')->forgetGuards();
        }

        return $this->get($this->url($listing))->assertOk()->getContent();
    }

    // ── (2) The console container is absent for viewers with nothing to see ──

    /**
     * The four unauthorized classes, asserted together because the rule is one rule and splitting
     * it into four near-identical methods would invite one of them being updated alone.
     *
     * `stranger` is an agent who has not bid; `rival` in this fixture HAS bid, so it is asserted
     * separately below where a planted amount can prove absence means withheld.
     */
    public function test_the_console_is_absent_for_every_viewer_with_no_visible_proposal(): void
    {
        $s = $this->scenario();
        $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $this->enableRedesign();

        $cases = [
            'guest'                    => null,
            'agent who has not bid'    => $s['stranger'],
            'unrelated authenticated'  => $s['outsider'],
            'administrator'            => $s['admin'],
        ];

        foreach ($cases as $label => $viewer) {
            $this->assertStringNotContainsString(
                self::CONSOLE,
                $this->render($s['listing'], $viewer),
                "The proposal console must not exist in the DOM for a {$label}."
            );
        }
    }

    /**
     * A competing agent — one who HAS a proposal on this listing, so the access layer hands them
     * exactly one row and the console renders. What must be absent is the rival's card, not the
     * container. Stated explicitly so nobody later "fixes" the console away for them.
     */
    public function test_a_competing_agent_gets_the_console_for_their_own_proposal_only(): void
    {
        $s = $this->scenario();
        $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);
        $this->enableRedesign();

        $html = $this->render($s['listing'], $s['mine']);

        $this->assertStringContainsString(self::CONSOLE, $html, 'Their own proposal earns the console.');
        $this->assertStringContainsString(self::OWN_AMOUNT, $html, 'Their own amount is theirs to see.');
        $this->assertStringNotContainsString(self::RIVAL_AMOUNT, $html, 'A rival amount must never render.');
    }

    /** The owner keeps the console even with nothing in it — the empty state lives there. */
    public function test_the_console_is_present_for_the_owner_with_no_proposals(): void
    {
        $s = $this->scenario();
        $this->enableRedesign();

        $html = $this->render($s['listing'], $s['owner']);

        $this->assertStringContainsString(self::CONSOLE, $html);
        $this->assertStringContainsString('No agents have submitted a bid yet.', $html);
    }

    public function test_the_console_is_present_for_the_owner_with_proposals(): void
    {
        $s = $this->scenario();
        $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);
        $this->enableRedesign();

        $html = $this->render($s['listing'], $s['owner']);

        $this->assertStringContainsString(self::CONSOLE, $html);
        $this->assertStringContainsString(self::OWN_AMOUNT, $html);
        $this->assertStringContainsString(self::RIVAL_AMOUNT, $html, 'The owner reviews everything.');
    }

    /**
     * The guard is presentation, and this is the test that says so.
     *
     * With the flag ON and the console gone from a competing agent's page, the SERVER-SIDE
     * decision must be unchanged: the owner is still handed both proposals and the outsider is
     * still handed none. If someone ever "optimises" the guard into the controller, this fails.
     */
    public function test_the_guard_changes_display_only_and_not_what_the_view_is_handed(): void
    {
        $s = $this->scenario();
        $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);
        $this->enableRedesign();

        $served = function (?User $viewer) use ($s): array {
            if ($viewer) {
                $this->actingAs($viewer);
            } else {
                app('auth')->forgetGuards();
            }

            $response = $this->get($this->url($s['listing']))->assertOk();

            return $response->original->getData()['auction']->bids
                ->pluck('user_id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        };

        $this->assertSame(
            collect([$s['mine']->id, $s['rival']->id])->map(fn ($v) => (int) $v)->sort()->values()->all(),
            $served($s['owner']),
            'Owner: still handed every proposal with the redesign on.'
        );
        $this->assertSame([(int) $s['mine']->id], $served($s['mine']), 'Agent: still handed their own.');
        $this->assertSame([], $served($s['outsider']), 'Unrelated: still handed none.');
        $this->assertSame([], $served(null), 'Guest: still handed none.');
    }

    // ── The legacy page is untouched ─────────────────────────────────────────

    /**
     * Flag off: the empty console renders for everyone, exactly as it did before M5.5.
     *
     * This is the M5 discipline — a change to what is visible today ships behind the flag — and
     * it is also the honest statement of what production currently does.
     */
    public function test_the_legacy_page_still_renders_the_console_for_every_viewer(): void
    {
        $s = $this->scenario();

        foreach ([null, $s['stranger'], $s['outsider'], $s['admin'], $s['owner']] as $viewer) {
            $this->assertStringContainsString(
                self::CONSOLE,
                $this->render($s['listing'], $viewer),
                'With the redesign off nothing about the console changes.'
            );
        }
    }

    // ── (3) The extraction ───────────────────────────────────────────────────

    /**
     * The `continue` guard cannot move into the partial: Blade compiles each view to its own
     * function, so `continue` there is a fatal error rather than a skipped card. Keeping it in the
     * loop is also stronger — an unauthorized row never reaches the partial at all.
     *
     * Asserted against source because that is what the claim is about.
     */
    public function test_the_authorization_guard_stayed_in_the_loop_not_the_partial(): void
    {
        $parent  = file_get_contents(base_path('resources/views/hire_landlord_agent/view.blade.php'));
        $partial = file_get_contents(base_path('resources/views/hire_landlord_agent/partials/proposal_card.blade.php'));

        $this->assertStringContainsString(
            'if (! $isListingOwner && ! $isBidOwner) { continue; }',
            $parent,
            'The skip guard belongs to the loop in the parent view.'
        );
        $this->assertStringNotContainsString(
            'continue;',
            $partial,
            'A `continue` inside an included view is a fatal error, not a skipped card.'
        );
        $this->assertStringContainsString(
            "@include('hire_landlord_agent.partials.proposal_card')",
            $parent
        );
    }

    /** The per-card gates moved with the card and still gate. */
    public function test_the_extracted_card_keeps_its_own_owner_or_author_gates(): void
    {
        $partial = file_get_contents(base_path('resources/views/hire_landlord_agent/partials/proposal_card.blade.php'));

        $this->assertStringContainsString('$isListingOwner || $isBidOwner', $partial);
    }

    // ── (4) Consolidated derived state ───────────────────────────────────────

    /**
     * The counter-term collection was queried TWICE per proposal — the same query, once at the top
     * of the card and once again beside the counter history. One query now.
     *
     * Counted by shape rather than by table, because the card legitimately issues other
     * counter-term queries: the `latestOwnerCounter` lookup and the winner-alert scan are both
     * `first()` calls and carry a limit. The full ordered collection is the one that was
     * duplicated, and it is uniquely identifiable by having no limit.
     */
    public function test_the_counter_term_collection_is_queried_once_per_proposal(): void
    {
        $s = $this->scenario();
        $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);
        $this->bid($s['listing'], $s['rival'], self::RIVAL_AMOUNT);

        $this->actingAs($s['owner']);
        DB::enableQueryLog();
        $this->get($this->url($s['listing']))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $collectionQueries = collect($queries)
            ->pluck('query')
            ->filter(fn ($q) => str_contains($q, 'landlord_counter_terms')
                && str_contains(strtolower($q), 'order by')
                && ! str_contains(strtolower($q), 'limit'))
            ->count();

        $this->assertSame(
            2,
            $collectionQueries,
            'Two visible proposals must produce two counter-term collection queries, not four.'
        );
    }

    /**
     * The five copies of derived proposal state are gone, and the ones that were dead are named
     * here so a future reader knows their absence is deliberate rather than an oversight.
     */
    public function test_the_duplicated_derived_state_is_gone(): void
    {
        $partial = file_get_contents(base_path('resources/views/hire_landlord_agent/partials/proposal_card.blade.php'));

        // The second, loose ownership test — the last of the copies M5.4 removed elsewhere.
        $this->assertStringNotContainsString('$isOwnerRow = data_get($auction', $partial);
        // The global role check that was shadowed by a same-named local meaning something else.
        $this->assertStringNotContainsString(
            "\$isAgent = \$auth_id && auth()->user()",
            $partial
        );
        // Exactly one counter-term collection query in the source.
        $this->assertSame(
            1,
            substr_count($partial, 'LandlordCounterTerm::with'),
            'The counter-term collection is fetched once per card.'
        );
    }

    // ── (5) Compensation is untouched ────────────────────────────────────────

    /**
     * THE COMPENSATION GATE IS GONE, BECAUSE THE SECTION IT GUARDED IS.
     *
     * This asserted that `@if (Auth::check())` still wrapped the landlord listing's Broker
     * Compensation section, because M5.5 was forbidden from answering the open
     * compensation-visibility question and had to leave the gate exactly as it found it.
     *
     * That question is now closed, and not by widening or narrowing the gate: Broker Compensation
     * is a negotiation term an agent proposes on a bid, so it is not a listing section at any
     * audience. The section and its bare authentication gate went together.
     *
     * INVERTED RATHER THAN DELETED. The gate returning would mean the section returned with it,
     * which is exactly the regression this file is positioned to catch on the landlord view.
     */
    public function test_the_listing_compensation_section_and_its_gate_are_gone(): void
    {
        $view = file_get_contents(base_path('resources/views/hire_landlord_agent/view.blade.php'));

        $this->assertStringNotContainsString(
            '@if (Auth::check()) {{-- broker compensation: hidden from anonymous visitors --}}',
            $view,
            'The bare authentication gate returned, which means the listing section did too.'
        );

        $this->assertStringNotContainsString(
            'id="hla-section-compensation"',
            $view,
            'Broker Compensation is a proposal term and must not be a listing section.'
        );

        $this->assertStringNotContainsString(
            'id="hla-section-services"',
            $view,
            'Services is a proposal term and must not be a listing section.'
        );
    }

    // ── (6) VIHO chrome, redesigned treatment only ───────────────────────────

    /**
     * Asserted against the rendered class ATTRIBUTE, not the bare class name. The VIHO stylesheet
     * is pushed into <head> on both treatments, so `viho-empty-state` appears in the document as a
     * CSS selector whether or not anything renders it — an absence assertion on the bare name
     * would pass for the wrong reason, or in this case fail for one.
     */
    public function test_the_redesigned_empty_state_uses_the_shared_primitive(): void
    {
        $s = $this->scenario();

        $legacy = $this->render($s['listing'], $s['owner']);
        $this->assertStringContainsString('<p>No agents have submitted a bid yet.</p>', $legacy);
        $this->assertStringNotContainsString('class="viho-empty-state"', $legacy);

        $this->enableRedesign();
        $redesigned = $this->render($s['listing'], $s['owner']);
        $this->assertStringContainsString('class="viho-empty-state"', $redesigned);
        $this->assertStringContainsString('No agents have submitted a bid yet.', $redesigned);
        $this->assertStringNotContainsString('<p>No agents have submitted a bid yet.</p>', $redesigned);
    }

    public function test_the_redesigned_status_chip_uses_the_shared_primitive(): void
    {
        $s = $this->scenario();
        $this->bid($s['listing'], $s['mine'], self::OWN_AMOUNT);

        $legacy = $this->render($s['listing'], $s['owner']);
        $this->assertStringNotContainsString('class="viho-badge', $legacy);
        $this->assertStringContainsString('font-weight: 600; color: #1a4a6e', $legacy, 'Legacy inline chip.');

        $this->enableRedesign();
        $redesigned = $this->render($s['listing'], $s['owner']);

        // An undecided proposal reads "Active", which maps to the informational tone.
        $this->assertStringContainsString('class="viho-badge viho-badge-info viho-badge-pill"', $redesigned);
        $this->assertStringContainsString('Active', $redesigned);
    }
}
