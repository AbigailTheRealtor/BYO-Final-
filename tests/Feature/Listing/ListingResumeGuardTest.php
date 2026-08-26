<?php

namespace Tests\Feature\Listing;

use App\Http\Livewire\HireBuyerAgent\BuyerAgentAuction as HireBuyer;
use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction as HireLandlord;
use App\Http\Livewire\HireSellerAgent\SellerAgentAuction as HireSeller;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing as OfferBuyer;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing as OfferLandlord;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing as OfferSeller;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing as OfferTenant;
use App\Http\Livewire\TenantAgentAuction as HireTenant;
use App\Support\Listing\ListingResumeGuard;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * Resume paths must check owner, role, product and draft-state — all four, before hydration.
 *
 * Owning the row used to be the only requirement, so an Offer Listing draft, a published
 * listing, and a draft belonging to a different role's table were all accepted and
 * hydrated into whichever wizard was asked.
 */
class ListingResumeGuardTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    protected function setUp(): void
    {
        parent::setUp();
        ListingWorkflow::forgetSchemaMemo();
    }

    /** @return array<string,array{0:string,1:string,2:string,3:array}> */
    public function productProvider(): array
    {
        return [
            'seller'   => ['seller',   HireSeller::class,   OfferSeller::class,   []],
            'buyer'    => ['buyer',    HireBuyer::class,    OfferBuyer::class,    []],
            'landlord' => ['landlord', HireLandlord::class, OfferLandlord::class, []],
            'tenant'   => ['tenant',   HireTenant::class,   OfferTenant::class,   ['user_type' => 'tenant']],
        ];
    }

    /** Mount a component with a listing id, returning true when it was refused. */
    private function refusesMount(string $component, array $params): bool
    {
        try {
            Livewire::test($component, $params);
        } catch (HttpException $e) {
            return true;
        }

        return false;
    }

    // ── PRODUCT ISOLATION ──────────────────────────────────────────────────────

    /** @dataProvider productProvider */
    public function test_offer_listing_draft_is_refused_by_the_hire_wizard(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->withoutExceptionHandling();

        $offerDraft = $this->makeListing($role, ListingWorkflow::OFFER_LISTING, $user->id);

        $mountParams = $params + ['listingId' => $offerDraft->id];

        if ($role === 'tenant') {
            // TenantAgentAuction redirects with a flash rather than aborting, so the
            // assertion is on what did NOT get hydrated.
            //
            // DELIBERATELY NOT ASSERTED ON `listingId`: Livewire's test harness assigns
            // mount parameters straight onto matching public properties, so that value
            // reports the harness rather than the component's decision. `isDraft` is set
            // only by loadDraft(), which is exactly what the guard must prevent reaching.
            $component = Livewire::test($hireComponent, $mountParams);
            $this->assertFalse((bool) $component->get('isDraft'),
                'tenant Hire wizard must not hydrate an Offer Listing draft');

            return;
        }

        $this->assertTrue($this->refusesMount($hireComponent, $mountParams),
            "Hire {$role} must refuse an Offer Listing draft");
    }

    /** @dataProvider productProvider */
    public function test_hire_draft_is_refused_by_the_offer_wizard(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->withoutExceptionHandling();

        $hireDraft = $this->makeListing($role, ListingWorkflow::HIRE_AGENT, $user->id);

        $mountParams = $params + ['listingId' => $hireDraft->id];

        if ($role === 'tenant') {
            $component = Livewire::test($offerComponent, $mountParams);
            $this->assertNotSame('hydrated-from-hire', (string) $component->get('listing_title'));

            return;
        }

        $this->assertTrue($this->refusesMount($offerComponent, $mountParams),
            "Offer {$role} must refuse a Hire Agent draft");
    }

    // ── ROLE ISOLATION ─────────────────────────────────────────────────────────

    /**
     * THE HEADLINE CASE.
     *
     * `/hire/agent/auction/tenant/{sellerDraftId}` must reject. It must not search
     * seller_agent_auctions, must not find the Seller row, must not rewrite user_type,
     * and must not hydrate Seller data.
     */
    public function test_tenant_route_with_a_seller_draft_id_rejects_and_never_rewrites_user_type(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $sellerDraft = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true, [
            'listing_title' => 'SELLER ONLY PAYLOAD',
            'user_type'     => 'seller',
        ]);

        $component = Livewire::test(HireTenant::class, [
            'user_type' => 'tenant',
            'listingId' => $sellerDraft->id,
        ]);

        $this->assertSame('tenant', $component->get('user_type'),
            'the role in the route is authoritative — it must never be rewritten to seller');
        $this->assertNotSame('SELLER ONLY PAYLOAD', $component->get('listing_title'),
            'no Seller data may be hydrated');
        $this->assertFalse((bool) $component->get('isDraft'),
            'loadDraft() must never have run for a foreign-role id');
    }

    /** The same boundary on the Offer Listing multi-role wizard. */
    public function test_offer_tenant_route_with_a_seller_offer_draft_id_rejects(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $sellerDraft = $this->makeListing('seller', ListingWorkflow::OFFER_LISTING, $user->id, true, [
            'listing_title' => 'SELLER ONLY PAYLOAD',
        ]);

        $component = Livewire::test(OfferTenant::class, [
            'user_type' => 'tenant',
            'listingId' => $sellerDraft->id,
        ]);

        $this->assertSame('tenant', $component->get('user_type'));
        $this->assertNotSame('SELLER ONLY PAYLOAD', $component->get('listing_title'));
    }

    /** Every meaningful wrong-role pairing on the shared Hire wizard. */
    public function test_wrong_role_combinations_all_reject(): void
    {
        $roles = ListingWorkflow::ROLES;

        foreach ($roles as $routeRole) {
            foreach ($roles as $rowRole) {
                if ($routeRole === $rowRole) {
                    continue;
                }

                $user = $this->makeUser();
                $this->actingAs($user);

                $row = $this->makeListing($rowRole, ListingWorkflow::HIRE_AGENT, $user->id, true, [
                    'listing_title' => "PAYLOAD-{$rowRole}",
                ]);

                $component = Livewire::test(HireTenant::class, [
                    'user_type' => $routeRole,
                    'listingId' => $row->id,
                ]);

                $this->assertSame($routeRole, $component->get('user_type'),
                    "route role {$routeRole} must survive a {$rowRole} row id");
                $this->assertNotSame("PAYLOAD-{$rowRole}", $component->get('listing_title'),
                    "{$routeRole} route must not hydrate {$rowRole} data");
            }
        }
    }

    // ── DRAFT STATE ────────────────────────────────────────────────────────────

    /** @dataProvider productProvider */
    public function test_published_listing_is_refused_by_a_draft_resume_route(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->withoutExceptionHandling();

        $published = $this->makeListing($role, ListingWorkflow::HIRE_AGENT, $user->id, false, [
            'listing_title' => 'PUBLISHED PAYLOAD',
        ]);

        $mountParams = $params + ['listingId' => $published->id];

        if ($role === 'tenant') {
            $component = Livewire::test($hireComponent, $mountParams);
            $this->assertNotSame('PUBLISHED PAYLOAD', $component->get('listing_title'));

            return;
        }

        $this->assertTrue($this->refusesMount($hireComponent, $mountParams),
            "a published listing must not resume into the {$role} draft wizard");
    }

    /**
     * The EDIT route still accepts a published listing — that is its job.
     *
     * Without this, "require is_draft" would have quietly broken every legitimate edit of
     * a live listing, and the draft-state tests above would still have passed.
     */
    public function test_edit_route_still_accepts_a_published_listing_of_the_right_product(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $published = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, false);

        $auction = ListingResumeGuard::resolve(
            \App\Models\SellerAgentAuction::class,
            $published->id,
            ListingWorkflow::HIRE_AGENT,
            'seller',
            false
        );

        $this->assertNotNull($auction, 'the edit route must still resolve a published listing');
        $this->assertSame($published->id, $auction->id);
    }

    // ── OWNERSHIP (unchanged behaviour, re-pinned) ─────────────────────────────

    public function test_another_users_draft_is_refused(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();

        $draft = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $owner->id);

        $this->actingAs($stranger);

        $this->assertNull(ListingResumeGuard::resolve(
            \App\Models\SellerAgentAuction::class, $draft->id, ListingWorkflow::HIRE_AGENT, 'seller', true
        ));
        $this->assertSame(ListingResumeGuard::DENY_MISSING, ListingResumeGuard::lastDenyReason());
    }

    // ── FAIL-CLOSED CLASSIFICATIONS ────────────────────────────────────────────

    public function test_unclassified_row_is_refused_for_both_products(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $orphan = $this->makeUnstamped('seller', $user->id);

        foreach (ListingWorkflow::ALL as $workflow) {
            $this->assertNull(ListingResumeGuard::resolve(
                \App\Models\SellerAgentAuction::class, $orphan->id, $workflow, 'seller', true
            ), "unclassified row must be refused for {$workflow}");
            $this->assertSame(ListingResumeGuard::DENY_UNCLASSIFIED, ListingResumeGuard::lastDenyReason());
        }
    }

    public function test_conflicting_row_is_refused_for_both_products(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $row = $this->makeUnstamped('seller', $user->id, true, [
            'mls_quick_import' => '1',
            'service_type'     => 'full_service',
        ]);

        foreach (ListingWorkflow::ALL as $workflow) {
            $this->assertNull(ListingResumeGuard::resolve(
                \App\Models\SellerAgentAuction::class, $row->id, $workflow, 'seller', true
            ));
            $this->assertSame(ListingResumeGuard::DENY_CONFLICTING, ListingResumeGuard::lastDenyReason());
        }
    }

    public function test_ambiguous_row_is_refused(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $row = $this->makeUnstamped('seller', $user->id, true, [
            ListingWorkflow::META_KEY => 'not_a_workflow',
        ]);

        $this->assertNull(ListingResumeGuard::resolve(
            \App\Models\SellerAgentAuction::class, $row->id, ListingWorkflow::HIRE_AGENT, 'seller', true
        ));
        $this->assertSame(ListingResumeGuard::DENY_AMBIGUOUS, ListingResumeGuard::lastDenyReason());
    }

    public function test_guard_refuses_an_unrecognised_expected_workflow_or_role(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $draft = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id);

        $this->assertNull(ListingResumeGuard::resolve(
            \App\Models\SellerAgentAuction::class, $draft->id, 'offer', 'seller', true
        ));
        $this->assertSame(ListingResumeGuard::DENY_UNKNOWN_WORKFLOW, ListingResumeGuard::lastDenyReason());

        $this->assertNull(ListingResumeGuard::resolve(
            \App\Models\SellerAgentAuction::class, $draft->id, ListingWorkflow::HIRE_AGENT, 'landlord', true
        ));
        $this->assertSame(ListingResumeGuard::DENY_ROLE_MISMATCH, ListingResumeGuard::lastDenyReason());
    }

    // ── URL BYPASS (server-side, before hydration) ─────────────────────────────

    /**
     * Hand-crafted URLs are refused by the server, not by the absence of a link.
     */
    public function test_direct_url_with_a_wrong_product_id_does_not_render_the_listing(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $offerDraft = $this->makeListing('seller', ListingWorkflow::OFFER_LISTING, $user->id, true, [
            'listing_title' => 'OFFER ONLY PAYLOAD',
        ]);

        $response = $this->get("/hire/agent/auction/seller/{$offerDraft->id}");

        $this->assertNotSame(200, $response->getStatusCode(), 'the wrong-product URL must not render a 200 page');
        $response->assertDontSee('OFFER ONLY PAYLOAD', false);
    }

    public function test_direct_url_with_a_wrong_role_id_does_not_render_the_listing(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $sellerDraft = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true, [
            'listing_title' => 'SELLER ONLY PAYLOAD',
        ]);

        $response = $this->get("/hire/agent/auction/tenant/{$sellerDraft->id}");

        $response->assertDontSee('SELLER ONLY PAYLOAD', false);
    }
}
