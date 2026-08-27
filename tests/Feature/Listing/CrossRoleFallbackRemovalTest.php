<?php

namespace Tests\Feature\Listing;

use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing as OfferTenant;
use App\Http\Livewire\TenantAgentAuction as HireTenant;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * `loadDraft()` must not search the other three role tables.
 *
 * THIS TARGETS THE METHOD DIRECTLY, ON PURPOSE.
 * `loadDraft` is a public Livewire method, so it is callable from the client independently
 * of `mount()`. Driving this through mount() alone would not prove the fix: at the audited
 * baseline mount() happened to look the id up in the route role's own model first and
 * redirect on a miss, so a mount-only test passes with the fallback still fully present.
 * The fallback lived one level down, in loadDraft(), and only a direct call reaches it.
 *
 * At the baseline this test fails: loadDraft() walked the other three tables, found the
 * Seller row, set $foundUserType = 'seller', rewrote $this->user_type and hydrated Seller
 * data into a component the route said was Tenant.
 */
class CrossRoleFallbackRemovalTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    protected function setUp(): void
    {
        parent::setUp();
        ListingWorkflow::forgetSchemaMemo();
    }

    public function test_hire_load_draft_does_not_find_a_seller_row_from_the_tenant_role(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $sellerDraft = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true, [
            'listing_title' => 'SELLER ONLY PAYLOAD',
            'user_type'     => 'seller',
        ]);

        $component = Livewire::test(HireTenant::class, ['user_type' => 'tenant'])
            ->call('loadDraft', $sellerDraft->id);

        $component->assertSet('user_type', 'tenant');
        $this->assertNotSame('SELLER ONLY PAYLOAD', $component->get('listing_title'),
            'no Seller data may be hydrated into a Tenant component');
    }

    /** The same for every other wrong-role pairing on the shared Hire wizard. */
    public function test_hire_load_draft_refuses_every_wrong_role_pairing(): void
    {
        foreach (ListingWorkflow::ROLES as $routeRole) {
            foreach (ListingWorkflow::ROLES as $rowRole) {
                if ($routeRole === $rowRole) {
                    continue;
                }

                $user = $this->makeUser();
                $this->actingAs($user);

                $row = $this->makeListing($rowRole, ListingWorkflow::HIRE_AGENT, $user->id, true, [
                    'listing_title' => "PAYLOAD-{$rowRole}",
                    'user_type'     => $rowRole,
                ]);

                $component = Livewire::test(HireTenant::class, ['user_type' => $routeRole])
                    ->call('loadDraft', $row->id);

                $this->assertSame($routeRole, $component->get('user_type'),
                    "{$routeRole} route must not be relabelled to {$rowRole}");
                $this->assertNotSame("PAYLOAD-{$rowRole}", $component->get('listing_title'),
                    "{$routeRole} must not hydrate {$rowRole} data");
            }
        }
    }

    /** The Offer Listing multi-role wizard carried an identical fallback. */
    public function test_offer_load_draft_does_not_find_a_seller_row_from_the_tenant_role(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $sellerDraft = $this->makeListing('seller', ListingWorkflow::OFFER_LISTING, $user->id, true, [
            'listing_title' => 'SELLER ONLY PAYLOAD',
            'user_type'     => 'seller',
        ]);

        $component = Livewire::test(OfferTenant::class, ['user_type' => 'tenant'])
            ->call('loadDraft', $sellerDraft->id);

        $component->assertSet('user_type', 'tenant');
        $this->assertNotSame('SELLER ONLY PAYLOAD', $component->get('listing_title'));
    }

    /**
     * A miss in the expected role's own table stays a miss — it is not replaced with a
     * different heuristic search.
     */
    public function test_a_miss_in_the_expected_role_table_is_simply_a_miss(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $component = Livewire::test(HireTenant::class, ['user_type' => 'tenant'])
            ->call('loadDraft', 999999);

        $component->assertSet('user_type', 'tenant');
        $this->assertEmpty($component->get('listing_title'));
    }

    /**
     * The right role still loads its own draft — the fix is not "refuse everything".
     *
     * Asserted on `isDraft` rather than on `listing_title`: loadDraft() reads the title
     * from the native `title` COLUMN, and `tenant_agent_auctions` has no such column (the
     * schema asymmetry in CLAUDE.md — tenant and landlord keep that in meta). `isDraft` is
     * set from the loaded record and is the cleanest evidence that hydration ran.
     */
    public function test_the_correct_role_still_loads_its_own_draft(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $tenantDraft = $this->makeListing('tenant', ListingWorkflow::HIRE_AGENT, $user->id, true, [
            'listing_title' => 'TENANT PAYLOAD',
            'user_type'     => 'tenant',
        ]);

        $component = Livewire::test(HireTenant::class, ['user_type' => 'tenant'])
            ->call('loadDraft', $tenantDraft->id);

        $component->assertSet('user_type', 'tenant');
        $this->assertTrue((bool) $component->get('isDraft'),
            'the legitimate same-role resume must still hydrate');
    }

    /** …and on a role whose table does carry a title column, the data really lands. */
    public function test_the_correct_role_hydrates_real_data_on_seller(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $sellerDraft = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true, [
            'user_type' => 'seller',
        ], ['title' => 'SELLER TITLE']);

        $component = Livewire::test(HireTenant::class, ['user_type' => 'seller'])
            ->call('loadDraft', $sellerDraft->id);

        $component->assertSet('user_type', 'seller');
        $this->assertSame('SELLER TITLE', $component->get('listing_title'));
    }
}
