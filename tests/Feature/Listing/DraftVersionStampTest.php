<?php

namespace Tests\Feature\Listing;

use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Services\Listing\ListingWorkflowResolver;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * A NEW ROW CREATED BY "SAVE AS NEW VERSION" CARRIES BOTH HALVES OF ITS IDENTITY.
 *
 * WHY THIS PATH NEEDS ITS OWN TEST
 * --------------------------------
 * Every other new-row path in the wizards acquires its product identity from
 * `saveAllMetadata()`, which calls {@see ListingWorkflow::stamp()} and writes the native
 * column and the legacy EAV key together. The draft-versioning path in
 * {@see TenantOfferListingEdit::saveDraft()} is the one that does not: it CLONES the
 * source row's meta instead of re-running saveAllMetadata.
 *
 * That made it the only writer in the application that produced a HALF-STAMPED row. The
 * clone loop copied the source's `workflow_type` meta across, so the legacy key was
 * populated and nothing looked wrong — but the native `workflow_type` column, which the
 * migrations introduce as the durable SSOT, was never written. Two consequences, both
 * latent rather than visible:
 *
 *   1. Between `$newDraft->save()` and the clone loop the row existed with NO identity at
 *      all. An exception anywhere in between — and the loop issues one write per meta row
 *      — left a permanently unclassified row which, under the strict fail-closed policy,
 *      its own owner can then neither open nor delete.
 *   2. The NOT NULL enforcement migration that migration 000001's header defers to a later
 *      change is gated on an inventory reporting a zero remainder. A writer still emitting
 *      NULL-column rows would keep that remainder permanently non-zero.
 *
 * The assertions below are deliberately on the STORED ROW rather than on the resolver's
 * verdict: the resolver reads the EAV key when the column is null, so it answered
 * "offer_listing" for the broken row too. Asserting only `resolve()` would have passed
 * against the defect. The column is checked directly, by name.
 */
class DraftVersionStampTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    protected function setUp(): void
    {
        parent::setUp();
        ListingWorkflow::forgetSchemaMemo();
    }

    /**
     * The versioned copy is stamped in the column, not merely in the cloned meta.
     */
    public function test_a_versioned_offer_draft_carries_the_native_column(): void
    {
        $user   = $this->makeUser();
        $source = $this->makeListing('tenant', ListingWorkflow::OFFER_LISTING, $user->id, true, [
            'listing_title' => 'v1',
            'user_type'     => 'tenant',
        ]);

        Livewire::actingAs($user)
            ->test(TenantOfferListingEdit::class, ['auctionId' => $source->id, 'user_type' => 'tenant'])
            ->call('saveDraft');

        $new = ListingWorkflow::modelClassForRole('tenant')::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $source->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($new, 'saveDraft() must have produced a new version row');

        $this->assertSame(
            ListingWorkflow::OFFER_LISTING,
            $new->getAttribute(ListingWorkflow::COLUMN),
            'the versioned row must carry the native workflow_type column, not just cloned meta'
        );

        $this->assertSame(
            ListingWorkflow::OFFER_LISTING,
            $new->info(ListingWorkflow::META_KEY),
            'and the legacy EAV key too — stamp() writes both or neither'
        );
    }

    /**
     * It resolves cleanly, and as the RIGHT product.
     *
     * A half-stamped row still resolved before the fix, so this is not the assertion that
     * catches the defect — it is the control proving the fix did not introduce a
     * native/EAV disagreement, which the resolver would report as CONFLICTING and which
     * would strand the new version just as badly as no stamp at all.
     */
    public function test_the_versioned_row_resolves_as_offer_listing_without_conflict(): void
    {
        $user   = $this->makeUser();
        $source = $this->makeListing('tenant', ListingWorkflow::OFFER_LISTING, $user->id, true, [
            'listing_title' => 'v1',
            'user_type'     => 'tenant',
        ]);

        Livewire::actingAs($user)
            ->test(TenantOfferListingEdit::class, ['auctionId' => $source->id, 'user_type' => 'tenant'])
            ->call('saveDraft');

        $new = ListingWorkflow::modelClassForRole('tenant')::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $source->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($new);

        $classification = app(ListingWorkflowResolver::class)->classify($new);

        $this->assertTrue($classification->isResolved(), $classification->reason);
        $this->assertTrue($classification->isOfferListing());
    }

    /**
     * A source whose column was never backfilled still yields a fully-stamped version.
     *
     * This is the realistic production shape: rows that pre-date the migration carry the
     * EAV key only. Cloning propagates exactly that state, so without an explicit stamp
     * every future version of every legacy draft would inherit the half-stamp forever.
     * The fix re-asserts identity rather than trusting the clone, and this proves it.
     */
    public function test_a_legacy_eav_only_source_still_produces_a_fully_stamped_version(): void
    {
        $user = $this->makeUser();

        // EAV identity only — exactly a pre-migration wizard-saved row.
        $source = $this->makeUnstamped('tenant', $user->id, true, [
            ListingWorkflow::META_KEY => ListingWorkflow::OFFER_LISTING,
            'listing_title'           => 'legacy v1',
            'user_type'               => 'tenant',
        ]);

        $this->assertNull(
            $source->getAttribute(ListingWorkflow::COLUMN),
            'fixture precondition: the source must have no native stamp'
        );

        Livewire::actingAs($user)
            ->test(TenantOfferListingEdit::class, ['auctionId' => $source->id, 'user_type' => 'tenant'])
            ->call('saveDraft');

        $new = ListingWorkflow::modelClassForRole('tenant')::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $source->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($new, 'saveDraft() must have produced a new version row');
        $this->assertSame(
            ListingWorkflow::OFFER_LISTING,
            $new->getAttribute(ListingWorkflow::COLUMN),
            'the half-stamp must not propagate to the new version'
        );
    }
}
