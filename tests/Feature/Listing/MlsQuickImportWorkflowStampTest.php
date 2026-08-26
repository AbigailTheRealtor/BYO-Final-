<?php

namespace Tests\Feature\Listing;

use App\Services\Listing\ListingWorkflowResolver;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\ListingImport\QuickImport\MlsQuickImportResult;
use App\Support\Listing\ListingWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Listing\Concerns\MakesWorkflowListings;
use Tests\TestCase;

/**
 * The writer that created the defect must stamp every draft it makes.
 *
 * Exercises the REAL {@see MlsQuickImportDraftWriter} resolved from the container — no
 * double, no stub, and no skip. The class is reachable only from Create Offer Listing, so
 * every row it produces is an Offer Listing; until this change it said so nowhere, and a
 * draft with no product identity is a draft the Hire Agent picker will happily list.
 *
 * Media is deliberately empty in these fixtures: the gallery sync is not what is under
 * test, and an import that carries no media is the writer's own documented no-op path.
 */
class MlsQuickImportWorkflowStampTest extends TestCase
{
    use DatabaseTransactions;
    use MakesWorkflowListings;

    private MlsQuickImportDraftWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        ListingWorkflow::forgetSchemaMemo();

        $this->writer = app(MlsQuickImportDraftWriter::class);
    }

    private function result(string $key = 'TB8528949', string $mls = '8528949'): MlsQuickImportResult
    {
        return MlsQuickImportResult::found(
            facts:      ['bedrooms' => '3', 'bathrooms' => '2', 'year_built' => '1998'],
            media:      [],
            headline:   ['address' => '2142 BRADFORD STREET UNIT 308'],
            details:    [],
            listingKey: $key,
            mlsNumber:  $mls,
            mlsStatus:  'Active',
        );
    }

    /** Guards against the test silently becoming a no-op. */
    public function test_the_real_writer_is_under_test(): void
    {
        $this->assertInstanceOf(MlsQuickImportDraftWriter::class, $this->writer);
        $this->assertSame(
            MlsQuickImportDraftWriter::class,
            get_class($this->writer),
            'this test must exercise the production class, not a subclass or a double'
        );
    }

    public function test_seller_quick_import_draft_is_stamped_offer_listing(): void
    {
        $user = $this->makeUser();

        $draft = $this->writer->materialise('seller', $user->id, $this->result());

        $this->assertNotNull($draft);
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $draft->getAttribute(ListingWorkflow::COLUMN),
            'the native discriminator must be written');
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $draft->info(ListingWorkflow::META_KEY),
            'the legacy EAV key must be written too, for unported readers');
        $this->assertSame(ListingWorkflow::OFFER_LISTING,
            app(ListingWorkflowResolver::class)->resolve($draft));
        $this->assertTrue((bool) $draft->is_draft);
    }

    public function test_landlord_quick_import_draft_is_stamped_offer_listing(): void
    {
        $user = $this->makeUser();

        $draft = $this->writer->materialise('landlord', $user->id, $this->result('TB999', '999'));

        $this->assertNotNull($draft);
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $draft->getAttribute(ListingWorkflow::COLUMN));
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $draft->info(ListingWorkflow::META_KEY));
        $this->assertSame(ListingWorkflow::OFFER_LISTING,
            app(ListingWorkflowResolver::class)->resolve($draft));
    }

    /**
     * The stamp lands in the INSERT, not in a later UPDATE.
     *
     * A row that exists for even one statement without a product identity is a row the
     * other product's picker could enumerate.
     */
    public function test_the_stamp_is_present_on_the_row_as_first_persisted(): void
    {
        $user = $this->makeUser();

        $draft = $this->writer->materialise('seller', $user->id, $this->result());

        $raw = \Illuminate\Support\Facades\DB::table('seller_agent_auctions')
            ->where('id', $draft->id)
            ->value(ListingWorkflow::COLUMN);

        $this->assertSame(ListingWorkflow::OFFER_LISTING, $raw);
    }

    /** Re-importing resumes the same draft rather than creating a second one. */
    public function test_reimport_resumes_the_same_stamped_draft(): void
    {
        $user = $this->makeUser();

        $first  = $this->writer->materialise('seller', $user->id, $this->result());
        $second = $this->writer->materialise('seller', $user->id, $this->result());

        $this->assertSame($first->id, $second->id, 'a re-import must resume, not duplicate');
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $second->getAttribute(ListingWorkflow::COLUMN));
    }

    /**
     * A pre-fix, unstamped quick-import draft is REPAIRED by a re-import.
     *
     * It is already classifiable from provenance, so the writer finds it; giving it the
     * explicit stamp on the way past is how the population of legacy rows shrinks without
     * anyone touching data by hand.
     */
    public function test_reimport_repairs_a_legacy_unstamped_draft(): void
    {
        $user = $this->makeUser();

        $legacy = $this->makeUnstamped('seller', $user->id, true, [
            'mls_quick_import' => '1',
            'mls_listing_key'  => 'TB8528949',
        ]);

        $this->assertNull($legacy->getAttribute(ListingWorkflow::COLUMN));

        $resumed = $this->writer->materialise('seller', $user->id, $this->result());

        $this->assertSame($legacy->id, $resumed->id, 'the legacy draft should be the one resumed');
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $resumed->getAttribute(ListingWorkflow::COLUMN));
    }

    /**
     * A CONFLICTING row is not adopted.
     *
     * A quick-imported draft that a Hire wizard later resumed and re-saved carries both
     * products' fingerprints. Re-importing must not write into it as though it were a
     * clean Offer Listing draft — it starts a fresh draft and leaves the damaged row for
     * the inventory to surface.
     */
    public function test_conflicting_row_is_not_adopted_by_a_reimport(): void
    {
        $user = $this->makeUser();

        $damaged = $this->makeUnstamped('seller', $user->id, true, [
            'mls_quick_import' => '1',
            'mls_listing_key'  => 'TB8528949',
            'service_type'     => 'full_service',
        ]);

        $fresh = $this->writer->materialise('seller', $user->id, $this->result());

        $this->assertNotSame($damaged->id, $fresh->id,
            'a conflicting row must not be silently re-adopted and written to');
        $this->assertSame(ListingWorkflow::OFFER_LISTING, $fresh->getAttribute(ListingWorkflow::COLUMN));
        $this->assertTrue(
            \App\Models\SellerAgentAuction::whereKey($damaged->id)->exists(),
            'the damaged row is left intact for the inventory, not deleted'
        );
    }

    /** A role the feature is not built for still resolves to no model and no row. */
    public function test_unsupported_role_creates_nothing(): void
    {
        $user = $this->makeUser();

        $this->assertNull($this->writer->materialise('buyer', $user->id, $this->result()));
        $this->assertNull($this->writer->materialise('tenant', $user->id, $this->result()));
    }

    /**
     * END-TO-END: the imported draft does not reach the Hire picker.
     *
     * The stamp is only worth having if it changes what the pickers do, so this asserts
     * the outcome rather than the mechanism.
     */
    public function test_imported_draft_is_invisible_to_the_hire_picker_and_visible_to_the_offer_picker(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $draft = $this->writer->materialise('seller', $user->id, $this->result());

        $hireIds = collect(\Livewire\Livewire::test(\App\Http\Livewire\HireSellerAgent\SellerAgentAuction::class)
            ->instance()->getDrafts())->pluck('id')->all();
        $offerIds = collect(\Livewire\Livewire::test(\App\Http\Livewire\OfferListing\Seller\SellerOfferListing::class)
            ->instance()->getDrafts())->pluck('id')->all();

        $this->assertNotContains($draft->id, $hireIds);
        $this->assertContains($draft->id, $offerIds);
    }
}
