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
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * Deleting drafts in one product must not touch the other product's rows OR their meta.
 *
 * `deleteAllDrafts()` selected ids by `user_id` + `is_draft` and then removed the matching
 * `*_metas` rows with a raw `DB::table()->delete()`. Because both products share the
 * table, a Hire "Delete All Drafts" destroyed the user's Offer Listing drafts, and the raw
 * meta delete meant no Eloquent event could have intercepted it — the boundary has to be
 * enforced when the ids are CHOSEN, which is what these tests pin.
 *
 * Meta rows are asserted separately from listing rows on purpose: a fix that spared the
 * listing row but still purged its meta would leave a draft that opens empty, which is
 * data loss wearing a passing test.
 */
class DraftDeleteIsolationTest extends TestCase
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

    /** @dataProvider productProvider */
    public function test_hire_delete_all_leaves_offer_listing_drafts_and_meta_intact(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $hireDraft  = $this->makeListing($role, ListingWorkflow::HIRE_AGENT,    $user->id, true, ['listing_title' => 'hire']);
        $offerDraft = $this->makeListing($role, ListingWorkflow::OFFER_LISTING, $user->id, true, ['listing_title' => 'offer']);

        $offerMetaBefore = $this->metaCount($role, $offerDraft->id);
        $this->assertGreaterThan(0, $offerMetaBefore, 'fixture must have meta to lose');

        Livewire::test($hireComponent, $params)->call('deleteAllDrafts');

        $this->assertFalse($this->exists($role, $hireDraft->id), 'the Hire draft should be gone');
        $this->assertTrue($this->exists($role, $offerDraft->id), 'the Offer Listing draft must survive');
        $this->assertSame($offerMetaBefore, $this->metaCount($role, $offerDraft->id),
            'the Offer Listing draft must keep every one of its meta rows');
    }

    /** @dataProvider productProvider */
    public function test_offer_delete_all_leaves_hire_drafts_and_meta_intact(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $hireDraft  = $this->makeListing($role, ListingWorkflow::HIRE_AGENT,    $user->id, true, ['listing_title' => 'hire']);
        $offerDraft = $this->makeListing($role, ListingWorkflow::OFFER_LISTING, $user->id, true, ['listing_title' => 'offer']);

        $hireMetaBefore = $this->metaCount($role, $hireDraft->id);
        $this->assertGreaterThan(0, $hireMetaBefore);

        Livewire::test($offerComponent, $params)->call('deleteAllDrafts');

        $this->assertFalse($this->exists($role, $offerDraft->id), 'the Offer Listing draft should be gone');
        $this->assertTrue($this->exists($role, $hireDraft->id), 'the Hire draft must survive');
        $this->assertSame($hireMetaBefore, $this->metaCount($role, $hireDraft->id));
    }

    /** @dataProvider productProvider */
    public function test_hire_delete_single_refuses_an_offer_listing_draft(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $offerDraft = $this->makeListing($role, ListingWorkflow::OFFER_LISTING, $user->id, true, ['listing_title' => 'offer']);
        $metaBefore = $this->metaCount($role, $offerDraft->id);

        Livewire::test($hireComponent, $params)->call('deleteDraft', $offerDraft->id);

        $this->assertTrue($this->exists($role, $offerDraft->id));
        $this->assertSame($metaBefore, $this->metaCount($role, $offerDraft->id));
    }

    /** @dataProvider productProvider */
    public function test_offer_delete_single_refuses_a_hire_draft(
        string $role, string $hireComponent, string $offerComponent, array $params
    ): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        $hireDraft  = $this->makeListing($role, ListingWorkflow::HIRE_AGENT, $user->id, true, ['listing_title' => 'hire']);
        $metaBefore = $this->metaCount($role, $hireDraft->id);

        Livewire::test($offerComponent, $params)->call('deleteDraft', $hireDraft->id);

        $this->assertTrue($this->exists($role, $hireDraft->id));
        $this->assertSame($metaBefore, $this->metaCount($role, $hireDraft->id));
    }

    /**
     * A PUBLISHED listing is not deletable through the draft delete path.
     *
     * `deleteDraft()` checked ownership and nothing else, so a published listing id was
     * accepted and hard-deleted along with all of its meta.
     */
    public function test_published_listing_cannot_be_deleted_through_delete_draft(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $published = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, false, ['listing_title' => 'live']);

        Livewire::test(HireSeller::class)->call('deleteDraft', $published->id);

        $this->assertTrue($this->exists('seller', $published->id), 'a published listing must not be deletable here');
        $this->assertGreaterThan(0, $this->metaCount('seller', $published->id));
    }

    /** Delete-all never reaches a published listing either. */
    public function test_delete_all_drafts_spares_published_listings(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $draft     = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, true);
        $published = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $user->id, false);

        Livewire::test(HireSeller::class)->call('deleteAllDrafts');

        $this->assertFalse($this->exists('seller', $draft->id));
        $this->assertTrue($this->exists('seller', $published->id));
    }

    /** Another user's draft of the same product is untouched. */
    public function test_delete_never_crosses_owners(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();

        $victim = $this->makeListing('seller', ListingWorkflow::HIRE_AGENT, $owner->id, true, ['listing_title' => 'victim']);

        $this->actingAs($stranger);

        Livewire::test(HireSeller::class)->call('deleteDraft', $victim->id);
        Livewire::test(HireSeller::class)->call('deleteAllDrafts');

        $this->assertTrue($this->exists('seller', $victim->id));
        $this->assertGreaterThan(0, $this->metaCount('seller', $victim->id));
    }

    /**
     * An unclassifiable draft is deleted by neither product.
     *
     * It fails closed everywhere else, and a delete path that still swept it up would be
     * the one place the fail-closed rule turned into data loss.
     */
    public function test_unclassifiable_draft_is_deleted_by_neither_product(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $orphan = $this->makeUnstamped('seller', $user->id, true, ['listing_title' => 'orphan']);

        Livewire::test(HireSeller::class)->call('deleteAllDrafts');
        Livewire::test(OfferSeller::class)->call('deleteAllDrafts');

        $this->assertTrue($this->exists('seller', $orphan->id));
        $this->assertGreaterThan(0, $this->metaCount('seller', $orphan->id));
    }
}
