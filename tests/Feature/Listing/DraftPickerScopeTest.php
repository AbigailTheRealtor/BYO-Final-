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
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * The draft picker must offer only THIS product's drafts.
 *
 * Both products share one table per role. `getDrafts()` and `$hasDrafts` previously asked
 * only "does this user own a row with is_draft = true", so the Hire Agent "Load Saved
 * Draft" modal listed the user's Offer Listing drafts and vice versa. Clicking one is how
 * an Offer Listing draft came to open inside the Hire Seller's Agent wizard.
 *
 * Every case creates BOTH products' drafts for the SAME owner, so a picker that returns
 * the wrong one fails on identity, not on emptiness. A test that created only the
 * opposite product's draft would pass against a picker that returned nothing at all.
 */
class DraftPickerScopeTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    protected function setUp(): void
    {
        parent::setUp();
        ListingWorkflow::forgetSchemaMemo();
    }

    /**
     * role => [hire component, offer component, extra mount params]
     *
     * @return array<string,array{0:string,1:string,2:string,3:array}>
     */
    public function productProvider(): array
    {
        return [
            'seller'   => ['seller',   HireSeller::class,   OfferSeller::class,   []],
            'buyer'    => ['buyer',    HireBuyer::class,    OfferBuyer::class,    []],
            'landlord' => ['landlord', HireLandlord::class, OfferLandlord::class, []],
            'tenant'   => ['tenant',   HireTenant::class,   OfferTenant::class,   ['user_type' => 'tenant']],
        ];
    }

    /** @dataProvider productProvider */
    public function test_hire_picker_excludes_offer_listing_drafts(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $hireDraft  = $this->makeListing($role, ListingWorkflow::HIRE_AGENT, $user->id);
        $offerDraft = $this->makeListing($role, ListingWorkflow::OFFER_LISTING, $user->id);

        $component = Livewire::test($hireComponent, $params);
        $ids = collect($component->instance()->getDrafts())->pluck('id')->all();

        $this->assertContains($hireDraft->id, $ids, "Hire picker must still offer its own {$role} draft");
        $this->assertNotContains($offerDraft->id, $ids, "Hire picker must NOT offer an Offer Listing {$role} draft");
    }

    /** @dataProvider productProvider */
    public function test_offer_picker_excludes_hire_agent_drafts(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $hireDraft  = $this->makeListing($role, ListingWorkflow::HIRE_AGENT, $user->id);
        $offerDraft = $this->makeListing($role, ListingWorkflow::OFFER_LISTING, $user->id);

        $component = Livewire::test($offerComponent, $params);
        $ids = collect($component->instance()->getDrafts())->pluck('id')->all();

        $this->assertContains($offerDraft->id, $ids, "Offer picker must still offer its own {$role} draft");
        $this->assertNotContains($hireDraft->id, $ids, "Offer picker must NOT offer a Hire Agent {$role} draft");
    }

    /**
     * $hasDrafts follows the same boundary as getDrafts().
     *
     * Asserted separately because it drives whether the modal opens at all: a picker with
     * a correct list but a flag computed from the unscoped query would still pop a "you
     * have saved drafts" modal for a user whose only drafts belong to the other product.
     */
    /** @dataProvider productProvider */
    public function test_has_drafts_flag_is_product_scoped(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        // ONLY an Offer Listing draft exists.
        $this->makeListing($role, ListingWorkflow::OFFER_LISTING, $user->id);

        $hire = Livewire::test($hireComponent, $params);
        $this->assertFalse($hire->get('hasDrafts'),
            "Hire {$role} must not report drafts when the user's only draft is an Offer Listing");

        $offer = Livewire::test($offerComponent, $params);
        $this->assertTrue($offer->get('hasDrafts'),
            "Offer {$role} must report its own draft");
    }

    /**
     * A row nobody can classify is offered by NEITHER product.
     *
     * This is the fail-closed rule the resume guard also applies. Showing an
     * unclassifiable draft in one picker and refusing to open it would be worse than not
     * offering it, and offering it to whichever screen asked first is the original bug.
     */
    /** @dataProvider productProvider */
    public function test_unclassifiable_draft_appears_in_neither_picker(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $orphan = $this->makeUnstamped($role, $user->id);

        $hireIds  = collect(Livewire::test($hireComponent, $params)->instance()->getDrafts())->pluck('id')->all();
        $offerIds = collect(Livewire::test($offerComponent, $params)->instance()->getDrafts())->pluck('id')->all();

        $this->assertNotContains($orphan->id, $hireIds);
        $this->assertNotContains($orphan->id, $offerIds);
    }

    /**
     * A legacy MLS Quick Import draft — the "seller draft 123" shape — is offered by the
     * OFFER picker and refused by the HIRE picker, on provenance alone.
     */
    public function test_legacy_quick_import_draft_is_offered_only_by_the_offer_picker(): void
    {
        foreach (['seller' => [HireSeller::class, OfferSeller::class],
                  'landlord' => [HireLandlord::class, OfferLandlord::class]] as $role => [$hireC, $offerC]) {
            $user = $this->makeUser();
            $this->actingAs($user);

            $draft = $this->makeLegacyQuickImportDraft($role, $user->id);

            $hireIds  = collect(Livewire::test($hireC)->instance()->getDrafts())->pluck('id')->all();
            $offerIds = collect(Livewire::test($offerC)->instance()->getDrafts())->pluck('id')->all();

            $this->assertNotContains($draft->id, $hireIds,
                "{$role}: a quick-imported Offer Listing draft must never reach the Hire picker");
            $this->assertContains($draft->id, $offerIds,
                "{$role}: it must still be resumable from its own product");
        }
    }

    /** Another user's drafts are never listed, product notwithstanding. */
    public function test_picker_never_crosses_owners(): void
    {
        $owner   = $this->makeUser();
        $stranger = $this->makeUser();

        $ownersDraft = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $owner->id);

        $this->actingAs($stranger);

        $ids = collect(Livewire::test(HireSeller::class)->instance()->getDrafts())->pluck('id')->all();

        $this->assertNotContains($ownersDraft->id, $ids);
    }
}
