<?php

namespace Tests\Feature\Listing;

use App\Http\Livewire\HireSellerAgent\SellerAgentAuction as HireSeller;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing as OfferSeller;
use App\Models\SellerAgentAuction;
use App\Support\Listing\ListingResumeGuard;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * What happens to a row that carries NO product identity: nothing does.
 *
 * THE RULE, STATED ONCE
 * ---------------------
 * A row in one of the four shared `*_agent_auctions` tables that carries no workflow
 * stamp is refused by BOTH products on EVERY surface — resume, edit, pickers,
 * `$hasDrafts`, single delete, bulk delete, direct URL, direct Livewire invocation.
 *
 * WHY NOT A CALIBRATED EXCEPTION
 * ------------------------------
 * An earlier revision allowed unclassified rows through edit routes and through an
 * explicit `deleteDraft(id)`, arguing that a row making no product claim cannot be the
 * wrong product and that refusing it would only strand owners.
 *
 * The first half is false because the tables are shared: the row HAS a product, we merely
 * cannot prove which. Allowing it on both products' edit routes means one of those two
 * acceptances is always serving the other product's record. The second half describes a
 * real cost, but the wrong remedy — "we cannot identify it, so let whichever wizard asked
 * have it" resolves missing evidence by asking the attacker-controlled input. Ownership
 * proves WHO. It does not prove WHAT.
 *
 * The delete case was the sharper one: `deleteDraft(id)` hard-deletes the row and all of
 * its meta, and the id is client input.
 *
 * ACCEPTED CONSEQUENCE
 * --------------------
 * A genuinely unclassifiable historical row is unreachable through both products. It is
 * not mutated, not guessed at and not deleted — it simply waits for a product-neutral
 * recovery path that this change does not build.
 * {@see \App\Console\Commands\ListingsWorkflowInventory} enumerates them.
 *
 * @see \Tests\Feature\Listing\StrictUnclassifiedPolicyTest for the four-role component-level
 *      matrix. This file is the guard-level statement of the same rule.
 */
class UnclassifiedLegacyRowPolicyTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    protected function setUp(): void
    {
        parent::setUp();
        ListingWorkflow::forgetSchemaMemo();
    }

    private function metaCount(int $listingId): int
    {
        $relation = (new SellerAgentAuction())->meta();

        return DB::table($relation->getRelated()->getTable())
            ->where($relation->getForeignKeyName(), $listingId)
            ->count();
    }

    // ── REFUSED ON EVERY PATH ──────────────────────────────────────────────────

    public function test_draft_resume_refuses_an_unclassified_row(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $orphan = $this->makeUnstamped('seller', $user->id, true);

        $this->assertNull(ListingResumeGuard::resolve(
            SellerAgentAuction::class, $orphan->id, ListingWorkflow::HIRE_AGENT, 'seller', true
        ));
        $this->assertSame(ListingResumeGuard::DENY_UNCLASSIFIED, ListingResumeGuard::lastDenyReason());
    }

    /**
     * The edit shape is refused too — this is the assertion that reverses the old policy.
     *
     * `mustBeDraft: false` is the only thing that distinguishes an edit call from a resume
     * call at this boundary. It used to flip the answer; it must now be irrelevant to the
     * workflow question.
     */
    public function test_edit_route_refuses_an_unclassified_row(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $legacy = $this->makeUnstamped('seller', $user->id, false);

        $this->assertNull(ListingResumeGuard::resolve(
            SellerAgentAuction::class, $legacy->id, ListingWorkflow::HIRE_AGENT, 'seller', false
        ), 'an unstamped row must not be editable — its product is unproven, not absent');
        $this->assertSame(ListingResumeGuard::DENY_UNCLASSIFIED, ListingResumeGuard::lastDenyReason());
    }

    /** Neither product's edit route may claim it — that is the whole point. */
    public function test_neither_products_edit_route_accepts_an_unclassified_row(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $legacy = $this->makeUnstamped('seller', $user->id, false);

        foreach (ListingWorkflow::ALL as $workflow) {
            $this->assertNull(ListingResumeGuard::resolve(
                SellerAgentAuction::class, $legacy->id, $workflow, 'seller', false
            ), "edit as {$workflow} must be refused");
        }
    }

    public function test_bulk_delete_never_sweeps_up_an_unclassified_row(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $orphan = $this->makeUnstamped('seller', $user->id, true, ['listing_title' => 'legacy']);

        Livewire::test(HireSeller::class)->call('deleteAllDrafts');
        Livewire::test(OfferSeller::class)->call('deleteAllDrafts');

        $this->assertTrue(SellerAgentAuction::whereKey($orphan->id)->exists(),
            'a sweep must never take a row the user did not name');
    }

    /**
     * A single explicit delete cannot remove it either.
     *
     * This is the second assertion that reverses the old policy, and the more important
     * one: the operation being refused is a hard delete of the row and all of its meta,
     * authorised by nothing more than an id the client supplied.
     */
    public function test_single_explicit_delete_cannot_remove_an_unclassified_draft(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $orphan     = $this->makeUnstamped('seller', $user->id, true, ['listing_title' => 'legacy']);
        $metaBefore = $this->metaCount((int) $orphan->id);
        $this->assertGreaterThan(0, $metaBefore, 'fixture must have meta to lose');

        Livewire::test(HireSeller::class)->call('deleteDraft', $orphan->id);
        Livewire::test(OfferSeller::class)->call('deleteDraft', $orphan->id);

        $this->assertTrue(SellerAgentAuction::whereKey($orphan->id)->exists(),
            'an unstamped draft must not be destroyable from either wizard');
        $this->assertSame($metaBefore, $this->metaCount((int) $orphan->id),
            'and its meta must survive both attempts');
    }

    // ── THE ROW IS REFUSED, NOT ALTERED ────────────────────────────────────────

    /**
     * Refusal is inert. A rejected row is never stamped, repaired or guessed at on the way
     * out — that would be the resolver's judgement being written back as fact by whichever
     * product happened to knock.
     */
    public function test_a_refusal_does_not_mutate_the_row(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $orphan     = $this->makeUnstamped('seller', $user->id, true, ['listing_title' => 'legacy']);
        $metaBefore = $this->metaCount((int) $orphan->id);

        Livewire::test(HireSeller::class)->call('deleteDraft', $orphan->id);
        ListingResumeGuard::resolve(SellerAgentAuction::class, $orphan->id, ListingWorkflow::HIRE_AGENT, 'seller', false);

        $after = SellerAgentAuction::find($orphan->id);

        $this->assertNotNull($after);
        $this->assertSame($metaBefore, $this->metaCount((int) $orphan->id));
        $this->assertFalse(ListingWorkflow::isValid($after->info(ListingWorkflow::META_KEY) ?: null),
            'the row must still carry no EAV workflow stamp');

        if (ListingWorkflow::columnAvailable(SellerAgentAuction::class)) {
            $this->assertNull($after->getAttribute(ListingWorkflow::COLUMN),
                'the row must still carry no native workflow stamp');
        }
    }

    // ── STILL REFUSED, FOR THE OTHER REASONS ───────────────────────────────────

    /**
     * Ambiguous and conflicting rows remain refused, and keep their own deny reasons.
     *
     * They now share the outcome with unclassified rows but not the diagnosis: an
     * inventory still has to tell "nobody stamped this" from "two write paths disagreed".
     */
    public function test_conflicting_and_ambiguous_rows_are_refused_with_their_own_reasons(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $conflicting = $this->makeUnstamped('seller', $user->id, false, [
            'mls_quick_import' => '1',
            'service_type'     => 'full_service',
        ]);
        $ambiguous = $this->makeUnstamped('seller', $user->id, false, [
            ListingWorkflow::META_KEY => 'not_a_workflow',
        ]);

        $this->assertNull(ListingResumeGuard::resolve(
            SellerAgentAuction::class, $conflicting->id, ListingWorkflow::HIRE_AGENT, 'seller', false
        ));
        $this->assertSame(ListingResumeGuard::DENY_CONFLICTING, ListingResumeGuard::lastDenyReason());

        $this->assertNull(ListingResumeGuard::resolve(
            SellerAgentAuction::class, $ambiguous->id, ListingWorkflow::HIRE_AGENT, 'seller', false
        ));
        $this->assertSame(ListingResumeGuard::DENY_AMBIGUOUS, ListingResumeGuard::lastDenyReason());
    }

    /** Ownership and role are checked before the workflow, and independently of it. */
    public function test_ownership_and_role_are_still_checked_first(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();

        $legacy = $this->makeUnstamped('seller', $owner->id, false);

        $this->actingAs($stranger);

        $this->assertNull(ListingResumeGuard::resolve(
            SellerAgentAuction::class, $legacy->id, ListingWorkflow::HIRE_AGENT, 'seller', false
        ), 'a stranger must not reach a legacy row either');
        $this->assertSame(ListingResumeGuard::DENY_MISSING, ListingResumeGuard::lastDenyReason());

        $this->actingAs($owner);

        $this->assertNull(ListingResumeGuard::resolve(
            SellerAgentAuction::class, $legacy->id, ListingWorkflow::HIRE_AGENT, 'tenant', false
        ), 'a role mismatch is refused before the table is even queried');
        $this->assertSame(ListingResumeGuard::DENY_ROLE_MISMATCH, ListingResumeGuard::lastDenyReason());
    }

    /** A single delete still refuses a draft that positively claims the other product. */
    public function test_single_delete_still_refuses_a_declared_other_product_draft(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $offer = $this->makeListing('seller', ListingWorkflow::OFFER_LISTING, $user->id, true, ['listing_title' => 'offer']);

        Livewire::test(HireSeller::class)->call('deleteDraft', $offer->id);

        $this->assertTrue(SellerAgentAuction::whereKey($offer->id)->exists());
    }

    /**
     * The strict rule did not simply disable everything.
     *
     * Without a positive case, every assertion in this file would still pass against a
     * guard that returned null unconditionally.
     */
    public function test_a_properly_stamped_row_still_resolves(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $hire = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true);

        $resolved = ListingResumeGuard::resolve(
            SellerAgentAuction::class, $hire->id, ListingWorkflow::HIRE_AGENT, 'seller', true
        );

        $this->assertNotNull($resolved);
        $this->assertSame((int) $hire->id, (int) $resolved->id);
    }
}
