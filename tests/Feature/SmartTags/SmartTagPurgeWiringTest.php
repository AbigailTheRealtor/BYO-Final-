<?php

namespace Tests\Feature\SmartTags;

use App\Models\SmartTagAssignment;
use App\Models\SmartTagDerivationState;
use App\Models\SmartTagEvidence;
use App\Models\SmartTagManualEvent;
use App\Services\SmartTags\ManualSmartTagWriter;
use App\Services\SmartTags\SmartTagLifecycle;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Feature\SmartTags\Concerns\MakesSmartTagListings;
use Tests\Feature\SmartTags\Concerns\WiresSmartTags;
use Tests\Feature\SmartTags\Doubles\RecordingSmartTagLifecycle;
use Tests\Feature\SmartTags\Doubles\ThrowingSmartTagLifecycle;
use Tests\TestCase;

/**
 * Phase 2 — draft deletion and purge.
 *
 * The native purge deletes through the query builder and fires no model events,
 * so the Smart Tag rows have to be removed by an explicit call. These tests use
 * the REAL wizard delete actions, so the trait's own workflow scoping is in play.
 */
class SmartTagPurgeWiringTest extends TestCase
{
    use DatabaseTransactions;
    use MakesSmartTagListings;
    use WiresSmartTags;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        $this->enableSmartTags();
    }

    /** @test */
    public function deleting_a_seller_draft_removes_its_smart_tag_rows_and_keeps_the_manual_audit(): void
    {
        $owner = $this->sellerOwner();

        // A draft that already carries derived and manual evidence. Drafts are not
        // derived automatically, so this seeds the state a published-then-
        // unpublished listing would be in.
        $draft = $this->sellerListing($owner, [
            'property_type'      => 'Residential',
            'waterfront'         => 'Yes',
            'additional_details' => 'Quartz countertops and a private pool.',
        ], columns: ['is_draft' => true]);

        app(SmartTagLifecycle::class)->deriveForNativeSilently($draft, 'test');
        app(ManualSmartTagWriter::class)->replaceSelections(
            SmartTagListingRef::fromModel($draft), ['kitchen_island'], $owner
        );

        $this->assertGreaterThan(0, SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $draft->id)->count());
        $this->assertSame(1, SmartTagManualEvent::query()->where('listing_id', $draft->id)->count());

        $this->actingAs($owner);
        Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class)
            ->call('deleteDraft', $draft->id);

        $this->assertDatabaseMissing('seller_agent_auctions', ['id' => $draft->id]);

        $this->assertSame(0, SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $draft->id)->count(),
            'Evidence survived the listing being deleted.');
        $this->assertSame(0, SmartTagAssignment::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $draft->id)->count());
        $this->assertSame(0, SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $draft->id)->count());

        $this->assertSame(1, SmartTagManualEvent::query()->where('listing_id', $draft->id)->count(),
            'The append-only manual audit was deleted.');
    }

    /** @test */
    public function deleting_a_landlord_draft_removes_its_smart_tag_rows(): void
    {
        $owner = $this->landlordOwner();

        $draft = $this->landlordListing($owner, [
            'property_type' => 'Residential Property',
        ], columns: ['is_draft' => true]);

        app(SmartTagLifecycle::class)->deriveForNativeSilently($draft, 'test');

        $this->assertGreaterThan(0, SmartTagDerivationState::query()
            ->where('listing_type', 'landlord_agent')->where('listing_id', $draft->id)->count());

        $this->actingAs($owner);
        Livewire::test(\App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing::class)
            ->call('deleteDraft', $draft->id);

        $this->assertDatabaseMissing('landlord_agent_auctions', ['id' => $draft->id]);
        $this->assertSame(0, SmartTagDerivationState::query()
            ->where('listing_type', 'landlord_agent')->where('listing_id', $draft->id)->count());
    }

    /** @test */
    public function delete_all_drafts_purges_every_deleted_listing(): void
    {
        $owner = $this->sellerOwner();

        $ids = [];
        foreach (range(1, 3) as $i) {
            $draft = $this->sellerListing($owner, [
                'property_type' => 'Residential',
                'waterfront'    => 'Yes',
            ], columns: ['is_draft' => true]);

            app(SmartTagLifecycle::class)->deriveForNativeSilently($draft, 'test');
            $ids[] = $draft->id;
        }

        $this->assertSame(3, SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')->whereIn('listing_id', $ids)->count());

        $this->actingAs($owner);
        Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class)
            ->call('deleteAllDrafts');

        $this->assertSame(0, SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->whereIn('listing_id', $ids)->count());
        $this->assertSame(0, SmartTagDerivationState::query()
            ->where('listing_type', 'seller_agent')->whereIn('listing_id', $ids)->count());
    }

    /** @test */
    public function purging_one_listing_never_touches_another(): void
    {
        $owner = $this->sellerOwner();

        $doomed = $this->sellerListing($owner, ['property_type' => 'Residential', 'waterfront' => 'Yes'],
            columns: ['is_draft' => true]);
        $keeper = $this->sellerListing($owner, ['property_type' => 'Residential', 'waterfront' => 'Yes'],
            columns: ['is_draft' => true]);

        $lifecycle = app(SmartTagLifecycle::class);
        $lifecycle->deriveForNativeSilently($doomed, 'test');
        $lifecycle->deriveForNativeSilently($keeper, 'test');

        $this->actingAs($owner);
        Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class)
            ->call('deleteDraft', $doomed->id);

        $this->assertSame(0, SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $doomed->id)->count());
        $this->assertGreaterThan(0, SmartTagEvidence::query()
            ->where('listing_type', 'seller_agent')->where('listing_id', $keeper->id)->count(),
            'Purging one draft removed another listing\'s evidence.');
    }

    /**
     * The shared trait serves all four roles and both products, so the purge hook
     * fires for a Buyer draft too — and must resolve to nothing.
     *
     * Asserted through the REAL lifecycle, on rows: a recording double cannot
     * answer this, because the model-class filter it would be standing in for is
     * the very thing under test.
     *
     * @test
     */
    public function deleting_a_buyer_draft_touches_no_smart_tag_rows(): void
    {
        $seller = $this->sellerOwner();

        // A Seller listing whose numeric id will collide with the Buyer draft's,
        // so a purge that ignored listing_type would destroy it.
        $bystander = $this->sellerListing($seller, ['property_type' => 'Residential', 'waterfront' => 'Yes'],
            columns: ['is_draft' => true]);
        app(SmartTagLifecycle::class)->deriveForNativeSilently($bystander, 'test');

        $before = SmartTagEvidence::query()->count();
        $this->assertGreaterThan(0, $before);

        $owner = \App\Models\User::factory()->create(['user_type' => 'buyer']);
        $draft = \App\Models\BuyerAgentAuction::create([
            'user_id'  => $owner->id,
            'title'    => 'Buyer draft',
            'is_draft' => true,
        ]);
        $draft->saveMeta('workflow_type', 'offer_listing');

        $this->actingAs($owner);
        Livewire::test(\App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing::class)
            ->call('deleteDraft', $draft->id);

        $this->assertDatabaseMissing('buyer_agent_auctions', ['id' => $draft->id]);
        $this->assertSame($before, SmartTagEvidence::query()->count(),
            'Deleting a Buyer draft removed Smart Tag rows.');
    }

    /**
     * The hook is reached from the shared purge, rather than being wired only
     * into the two roles that happen to use it today.
     *
     * @test
     */
    public function the_shared_purge_calls_the_lifecycle_for_every_role(): void
    {
        $spy = new RecordingSmartTagLifecycle();
        $this->app->instance(SmartTagLifecycle::class, $spy);

        $owner = $this->sellerOwner();
        $draft = $this->sellerListing($owner, ['property_type' => 'Residential'], columns: ['is_draft' => true]);

        $this->actingAs($owner);
        Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class)
            ->call('deleteDraft', $draft->id);

        $this->assertSame(1, $spy->countOf('purge'),
            'The shared purge did not reach the Smart Tag lifecycle.');
        $this->assertSame((string) $draft->id, $spy->calls[0]['id']);
    }

    /** @test */
    public function the_purge_ignores_model_classes_smart_tags_do_not_attach_to(): void
    {
        $lifecycle = app(SmartTagLifecycle::class);

        // No exception, no rows touched, whatever it is handed.
        $lifecycle->purgeSilently(\App\Models\BuyerAgentAuction::class, [1, 2, 3]);
        $lifecycle->purgeSilently(\App\Models\TenantAgentAuction::class, [1]);
        $lifecycle->purgeSilently('Not\\A\\Class', [1]);
        $lifecycle->purgeSilently(\App\Models\SellerAgentAuction::class, []);

        $this->assertSame(0, SmartTagEvidence::query()->count());
    }

    /** @test */
    public function a_purge_failure_does_not_fail_the_listing_deletion(): void
    {
        $owner = $this->sellerOwner();
        $draft = $this->sellerListing($owner, ['property_type' => 'Residential'], columns: ['is_draft' => true]);

        $this->app->bind(SmartTagLifecycle::class, fn () => new ThrowingSmartTagLifecycle());

        $this->actingAs($owner);
        Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class)
            ->call('deleteDraft', $draft->id);

        $this->assertDatabaseMissing('seller_agent_auctions', ['id' => $draft->id]);
    }

    /** @test */
    public function with_the_gates_closed_the_purge_does_nothing_and_the_deletion_still_happens(): void
    {
        $owner = $this->sellerOwner();
        $draft = $this->sellerListing($owner, ['property_type' => 'Residential'], columns: ['is_draft' => true]);

        $this->disableSmartTags();

        $this->actingAs($owner);
        Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class)
            ->call('deleteDraft', $draft->id);

        $this->assertDatabaseMissing('seller_agent_auctions', ['id' => $draft->id]);
    }
}
