<?php

namespace Tests\Feature\Listing;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as HireBuyer;
use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuctionEdit as HireBuyerEdit;
use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction as HireLandlord;
use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuctionEdit as HireLandlordEdit;
use App\Http\Livewire\HireSellerAgent\SellerAgentAuction as HireSeller;
use App\Http\Livewire\HireSellerAgent\SellerAgentAuctionEdit as HireSellerEdit;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing as OfferBuyer;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit as OfferBuyerEdit;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing as OfferLandlord;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit as OfferLandlordEdit;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing as OfferSeller;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListingEdit as OfferSellerEdit;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing as OfferTenant;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit as OfferTenantEdit;
use App\Http\Livewire\TenantAgentAuction as HireTenant;
use App\Http\Livewire\TenantAgentAuctionEdit as HireTenantEdit;
use App\Support\Listing\ListingResumeGuard;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * UNCLASSIFIED FAILS CLOSED. EVERYWHERE. NO ROUTE-KIND EXCEPTION.
 *
 * WHY THE EARLIER CALIBRATION WAS WRONG
 * -------------------------------------
 * The first implementation refused an unclassified row on draft resume, in the pickers and
 * in Delete All, but ACCEPTED it on ordinary edit routes and on an explicit
 * `deleteDraft(id)`. The argument was that a row making no product claim cannot be "the
 * wrong product", so refusing it closed no hole.
 *
 * That argument does not survive the fact that the four `*_agent_auctions` tables are
 * SHARED. A row with no stamp is not a row with no product — it is a row whose product we
 * cannot prove. It belongs to exactly one of Hire Agent and Create Offer Listing, and
 * accepting it on both products' edit routes means one of those two acceptances is
 * serving another product's record into this product's wizard. The absence of a claim is
 * not neutrality; it is missing evidence, and missing evidence must not be resolved by
 * asking which route happened to be dialled.
 *
 * The delete half was worse. `deleteDraft(id)` is a hard delete of the row AND its meta,
 * and "the user named the id" is not proof of product identity — the id is client input,
 * and under the old exception a Hire wizard would destroy an unclassified row that may
 * well have been an Offer Listing draft.
 *
 * SO THE POLICY IS NOW UNIFORM:
 *
 *   UNCLASSIFIED  → refuse (no evidence)
 *   AMBIGUOUS     → refuse (evidence present, none decisive)
 *   CONFLICTING   → refuse (decisive evidence, disagreeing)
 *   unknown value → refuse
 *
 * on resume, edit, pickers, `$hasDrafts`, `deleteDraft(id)`, `deleteAllDrafts()`, direct
 * URL access and direct Livewire invocation alike.
 *
 * The cost is real and accepted: a genuinely unclassifiable historical row is unreachable
 * through both products until an administrator, product-neutral recovery path exists. That
 * is the deliberate trade — see ListingsWorkflowInventory for the inventory of such rows,
 * and the checkpoint's follow-up note. Stranding a row is recoverable; silently serving one
 * product's record into the other's wizard, and hard-deleting it from there, is not.
 *
 * @see \Tests\Feature\Listing\UnclassifiedLegacyRowPolicyTest for the guard-level statement
 *      of the same rule.
 */
class StrictUnclassifiedPolicyTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    protected function setUp(): void
    {
        parent::setUp();
        ListingWorkflow::forgetSchemaMemo();
    }

    /**
     * role, hire picker/create component, offer picker/create component, mount params.
     *
     * @return array<string,array{0:string,1:string,2:string,3:array}>
     */
    public function roleProvider(): array
    {
        return [
            'seller'   => ['seller',   HireSeller::class,   OfferSeller::class,   []],
            'buyer'    => ['buyer',    HireBuyer::class,    OfferBuyer::class,    []],
            'landlord' => ['landlord', HireLandlord::class, OfferLandlord::class, []],
            'tenant'   => ['tenant',   HireTenant::class,   OfferTenant::class,   ['user_type' => 'tenant']],
        ];
    }

    /**
     * role, hire EDIT component, offer EDIT component, extra mount params.
     *
     * The two tenant edit components take the role in the URL, which is why the params
     * differ per row rather than being uniform.
     *
     * @return array<string,array{0:string,1:string,2:string,3:array}>
     */
    public function editProvider(): array
    {
        return [
            'seller'   => ['seller',   HireSellerEdit::class,   OfferSellerEdit::class,   []],
            'buyer'    => ['buyer',    HireBuyerEdit::class,    OfferBuyerEdit::class,    []],
            'landlord' => ['landlord', HireLandlordEdit::class, OfferLandlordEdit::class, []],
            'tenant'   => ['tenant',   HireTenantEdit::class,   OfferTenantEdit::class,   ['user_type' => 'tenant']],
        ];
    }

    private function metaCount(string $role, int $listingId): int
    {
        $modelClass = ListingWorkflow::modelClassForRole($role);
        $relation   = (new $modelClass())->meta();

        return DB::table($relation->getRelated()->getTable())
            ->where($relation->getForeignKeyName(), $listingId)
            ->count();
    }

    private function exists(string $role, int $id): bool
    {
        $modelClass = ListingWorkflow::modelClassForRole($role);

        return $modelClass::query()->whereKey($id)->exists();
    }

    /**
     * The production Hire Agent edit URL for a role.
     *
     * All four roles route to the one multi-role component; the role in the path is what
     * tells it which table to edit, and the guard treats that as authoritative.
     */
    private function hireEditUrl(string $role, int $id): string
    {
        return "/hire/agent/auction/edit/{$id}/{$role}";
    }

    /** The production Create Offer Listing edit URL for a role. */
    private function offerEditUrl(string $role, int $id): string
    {
        return "/offer-listing/{$role}/edit/{$id}";
    }

    /**
     * Mount a component directly and report whether it refused with a 404.
     *
     * `withoutExceptionHandling()` is load-bearing. With the handler in place Livewire
     * converts the guard's `abort(404)` into a response the test harness then trips over
     * with an unrelated "Undefined array key fingerprint" — an exception, but not one that
     * distinguishes a refusal from a crash. Turning the handler off lets the real
     * NotFoundHttpException surface so this can assert the actual outcome.
     */
    private function componentRefuses(string $component, int $id, array $extra): bool
    {
        $this->withoutExceptionHandling();

        try {
            Livewire::test($component, array_merge(['auctionId' => $id], $extra));
        } catch (NotFoundHttpException $e) {
            return true;
        } finally {
            $this->withExceptionHandling();
        }

        return false;
    }

    // ── EDIT ───────────────────────────────────────────────────────────────────

    /**
     * The guard itself refuses an unclassified row on an EDIT-shaped call, for BOTH
     * products and all four roles.
     *
     * `mustBeDraft: false` is what makes this the edit shape. Under the old calibration
     * that argument alone flipped the answer to "accepted"; it no longer does anything to
     * the workflow question.
     *
     * @dataProvider roleProvider
     */
    public function test_edit_shaped_guard_call_refuses_an_unclassified_row(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $modelClass = ListingWorkflow::modelClassForRole($role);
        $legacy     = $this->makeUnstamped($role, $user->id, false);

        foreach (ListingWorkflow::ALL as $workflow) {
            $this->assertNull(
                ListingResumeGuard::resolve($modelClass, $legacy->id, $workflow, $role, false),
                "an unclassified row must not be editable as {$workflow}"
            );
            $this->assertSame(
                ListingResumeGuard::DENY_UNCLASSIFIED,
                ListingResumeGuard::lastDenyReason(),
                'the refusal must be attributed to the missing workflow identity'
            );
        }
    }

    /**
     * The REAL Hire Agent edit URL 404s on an unclassified row, for all four roles.
     *
     * Driven over HTTP rather than by mounting the component, because "a user hand-edits
     * the id in the address bar" is the actual attack and the route is the actual surface.
     *
     * @dataProvider roleProvider
     */
    public function test_hire_agent_edit_url_refuses_an_unclassified_row(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $legacy = $this->makeUnstamped($role, $user->id, false, ['listing_title' => 'legacy']);

        $this->get($this->hireEditUrl($role, (int) $legacy->id))
            ->assertNotFound();
    }

    /**
     * The REAL Offer Listing edit URL 404s on the very same shape of row.
     *
     * Paired with the test above deliberately: one record, both products' edit URLs, and
     * at most one of the two acceptances the old policy granted could ever have been right.
     *
     * @dataProvider roleProvider
     */
    public function test_offer_listing_edit_url_refuses_an_unclassified_row(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $legacy = $this->makeUnstamped($role, $user->id, false, ['listing_title' => 'legacy']);

        $this->get($this->offerEditUrl($role, (int) $legacy->id))
            ->assertNotFound();
    }

    /**
     * The two edit URLs still serve their OWN product — and still refuse each other's.
     *
     * This is the control that stops every assertion above from being satisfied by a route
     * that 404s unconditionally, and at the same time it re-pins the cross-product boundary
     * at the HTTP layer for all four roles.
     *
     * @dataProvider roleProvider
     */
    public function test_the_edit_urls_still_serve_their_own_product_only(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $hire  = $this->makeListing($role, ListingWorkflow::HIRE_AGENT,    $user->id, false, ['listing_title' => 'hire']);
        $offer = $this->makeListing($role, ListingWorkflow::OFFER_LISTING, $user->id, false, ['listing_title' => 'offer']);

        $this->get($this->hireEditUrl($role, (int) $hire->id))->assertOk();
        $this->get($this->offerEditUrl($role, (int) $offer->id))->assertOk();

        $this->get($this->hireEditUrl($role, (int) $offer->id))->assertNotFound();
        $this->get($this->offerEditUrl($role, (int) $hire->id))->assertNotFound();
    }

    /**
     * The per-role Hire edit components refuse it too, when invoked directly.
     *
     * These three carry no route of their own — the routed Hire edit surface is the
     * multi-role component — so an HTTP test cannot reach them. They are still Livewire
     * components a payload can name, which is exactly why they are asserted separately.
     */
    public function test_unrouted_hire_edit_components_refuse_an_unclassified_row(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $components = [
            'seller'   => HireSellerEdit::class,
            'buyer'    => HireBuyerEdit::class,
            'landlord' => HireLandlordEdit::class,
        ];

        foreach ($components as $role => $component) {
            $legacy = $this->makeUnstamped($role, $user->id, false, ['listing_title' => 'legacy']);

            $this->assertTrue(
                $this->componentRefuses($component, (int) $legacy->id, []),
                "{$component} must refuse a row with no workflow identity"
            );
        }
    }

    /**
     * An id typed into the URL is refused BEFORE the component hydrates anything.
     *
     * The positive control in the second half is what makes the first half mean something:
     * it proves the same call shape DOES load the row when the workflow is known, so the
     * refusal is the guard acting and not the fixture being unloadable.
     */
    public function test_tampered_url_id_is_refused_before_hydration(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $legacy = $this->makeUnstamped('seller', $user->id, false, ['listing_title' => 'SECRET-LEGACY-TITLE']);

        $this->assertTrue(
            $this->componentRefuses(HireSellerEdit::class, (int) $legacy->id, []),
            'a hand-edited id pointing at an unclassified row must 404'
        );

        // POSITIVE CONTROL — same component, same call shape, a stamped row: it hydrates.
        $stamped = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, false, [
            'listing_title' => 'STAMPED-TITLE',
        ]);

        $component = Livewire::test(HireSellerEdit::class, ['auctionId' => $stamped->id]);

        $this->assertSame((int) $stamped->id, (int) $component->get('auctionId'),
            'the control must actually hydrate, or the refusal above proves nothing');
    }

    // ── DRAFT RESUME ───────────────────────────────────────────────────────────

    /**
     * Draft resume refuses an unclassified draft for both products, all four roles.
     *
     * @dataProvider roleProvider
     */
    public function test_draft_resume_refuses_an_unclassified_draft(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $modelClass = ListingWorkflow::modelClassForRole($role);
        $orphan     = $this->makeUnstamped($role, $user->id, true);

        foreach (ListingWorkflow::ALL as $workflow) {
            $this->assertNull(
                ListingResumeGuard::resolve($modelClass, $orphan->id, $workflow, $role, true),
                "an unclassified draft must not resume into {$workflow}"
            );
            $this->assertSame(ListingResumeGuard::DENY_UNCLASSIFIED, ListingResumeGuard::lastDenyReason());
        }
    }

    // ── SINGLE EXPLICIT DELETE ─────────────────────────────────────────────────

    /**
     * `deleteDraft(id)` from the HIRE wizard leaves an unclassified draft — and its meta —
     * completely intact.
     *
     * Meta is asserted separately because `purgeListingRows()` deletes the meta rows with a
     * raw `DB::table()->delete()` in the same transaction. A regression that spared the
     * listing row but still took its meta would be silent data loss behind a green row
     * assertion.
     *
     * @dataProvider roleProvider
     */
    public function test_hire_delete_draft_cannot_remove_an_unclassified_draft(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $orphan     = $this->makeUnstamped($role, $user->id, true, ['listing_title' => 'orphan']);
        $metaBefore = $this->metaCount($role, (int) $orphan->id);
        $this->assertGreaterThan(0, $metaBefore, 'fixture must have meta to lose');

        Livewire::test($hireComponent, $params)->call('deleteDraft', $orphan->id);

        $this->assertTrue($this->exists($role, (int) $orphan->id),
            'an unclassified draft must survive an explicit Hire delete');
        $this->assertSame($metaBefore, $this->metaCount($role, (int) $orphan->id),
            'its meta must survive too');
    }

    /**
     * …and the same from the OFFER LISTING wizard.
     *
     * @dataProvider roleProvider
     */
    public function test_offer_delete_draft_cannot_remove_an_unclassified_draft(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $orphan     = $this->makeUnstamped($role, $user->id, true, ['listing_title' => 'orphan']);
        $metaBefore = $this->metaCount($role, (int) $orphan->id);
        $this->assertGreaterThan(0, $metaBefore);

        Livewire::test($offerComponent, $params)->call('deleteDraft', $orphan->id);

        $this->assertTrue($this->exists($role, (int) $orphan->id),
            'an unclassified draft must survive an explicit Offer Listing delete');
        $this->assertSame($metaBefore, $this->metaCount($role, (int) $orphan->id));
    }

    /**
     * The strict rule did not break the ordinary case.
     *
     * Without this, every assertion above could be satisfied by a `deleteDraft()` that
     * simply never deletes anything.
     */
    public function test_a_correctly_stamped_draft_is_still_deletable(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $draft = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true, [
            'listing_title' => 'mine',
        ]);

        Livewire::test(HireSeller::class)->call('deleteDraft', $draft->id);

        $this->assertFalse($this->exists('seller', (int) $draft->id),
            'a draft this product owns must still be deletable');
        $this->assertSame(0, $this->metaCount('seller', (int) $draft->id));
    }

    // ── THE OTHER THREE UNKNOWN SHAPES ─────────────────────────────────────────

    /**
     * AMBIGUOUS, CONFLICTING and an unrecognised workflow value are refused on every path.
     *
     * These were already refused before the strict correction; they are re-asserted here so
     * that the four unknown shapes are pinned in one place and cannot drift apart.
     */
    public function test_ambiguous_conflicting_and_unknown_are_refused_on_every_path(): void
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

        $cases = [
            [$conflicting, ListingResumeGuard::DENY_CONFLICTING],
            [$ambiguous,   ListingResumeGuard::DENY_AMBIGUOUS],
        ];

        foreach ($cases as [$row, $expectedReason]) {
            foreach (ListingWorkflow::ALL as $workflow) {
                foreach ([true, false] as $mustBeDraft) {
                    $this->assertNull(ListingResumeGuard::resolve(
                        \App\Models\SellerAgentAuction::class, $row->id, $workflow, 'seller', $mustBeDraft
                    ));
                    // A published row hits the draft check first on the resume shape, so
                    // only the edit shape can attribute the refusal to the workflow.
                    if (! $mustBeDraft) {
                        $this->assertSame($expectedReason, ListingResumeGuard::lastDenyReason());
                    }
                }
            }
        }

        // An unrecognised expected-workflow string matches nothing rather than everything.
        $good = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, false);

        $this->assertNull(ListingResumeGuard::resolve(
            \App\Models\SellerAgentAuction::class, $good->id, 'not_a_workflow', 'seller', false
        ));
        $this->assertSame(ListingResumeGuard::DENY_UNKNOWN_WORKFLOW, ListingResumeGuard::lastDenyReason());
    }

    /**
     * There is no way left to ask the guard to accept an unclassified row.
     *
     * The old escape hatch was a `$allowUnclassified` parameter that defaulted to
     * "whatever `$mustBeDraft` is not". Removing the branch is not enough on its own — a
     * later caller could re-introduce the same hole by passing an extra argument — so the
     * parameter's absence from the signature is asserted directly.
     */
    public function test_the_guard_exposes_no_unclassified_escape_hatch(): void
    {
        foreach (['resolve', 'resolveOrFail'] as $method) {
            $params = array_map(
                fn (\ReflectionParameter $p) => $p->getName(),
                (new \ReflectionMethod(ListingResumeGuard::class, $method))->getParameters()
            );

            $this->assertNotContains('allowUnclassified', $params,
                "ListingResumeGuard::{$method}() must not offer a way to opt out of the rule");
        }

        $source = file_get_contents((new \ReflectionClass(ListingResumeGuard::class))->getFileName());

        $this->assertStringNotContainsString('allowUnclassified', $source,
            'no residual opt-out may remain in the guard');
    }
}
