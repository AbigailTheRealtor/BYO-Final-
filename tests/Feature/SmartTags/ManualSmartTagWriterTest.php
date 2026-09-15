<?php

namespace Tests\Feature\SmartTags;

use App\Models\SmartTagAssignment;
use App\Models\SmartTagEvidence;
use App\Models\SmartTagManualEvent;
use App\Services\SmartTags\ManualSmartTagWriter;
use App\Services\SmartTags\SmartTagAuthorizationDecision as Decision;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagSelectionResult as R;
use App\Support\SmartTags\SmartTagTaxonomy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\SmartTags\Concerns\MakesSmartTagListings;
use Tests\TestCase;

/**
 * Seller/Landlord manual Smart Tags: canonical keys only, applicable only,
 * owner only, and deselection means unknown.
 */
class ManualSmartTagWriterTest extends TestCase
{
    use DatabaseTransactions;
    use MakesSmartTagListings;

    private ManualSmartTagWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        $this->writer = app(ManualSmartTagWriter::class);
    }

    /** @test */
    public function an_owner_selects_canonical_keys_which_are_stored_as_manual_evidence(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);
        $ref = SmartTagListingRef::fromModel($listing);

        $result = $this->writer->replaceSelections($ref, ['kitchen_island', 'quartz_countertops'], $owner);

        $this->assertTrue($result->saved);
        $this->assertSame(['kitchen_island', 'quartz_countertops'], $result->added);

        $rows = SmartTagEvidence::query()->where('listing_type', 'seller_agent')->where('listing_id', $listing->id)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame('manual_listing_owner', $row->source);
            $this->assertSame('present', $row->state);
            $this->assertSame((int) $owner->id, $row->set_by_user_id);
            $this->assertSame('residential.sale', $row->context);
        }

        $this->assertSame(2, SmartTagManualEvent::query()->where('listing_id', $listing->id)->where('action', 'selected')->count());
        $this->assertEqualsCanonicalizing(['kitchen_island', 'quartz_countertops'],
            SmartTagAssignment::query()->where('listing_id', $listing->id)->pluck('tag_key')->all());
    }

    /** @test */
    public function arbitrary_and_wrong_context_manual_keys_are_rejected(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Vacant Land']);

        $result = $this->writer->replaceSelections(SmartTagListingRef::fromModel($listing),
            ['Custom Awesome Tag', 'gourmet_kitchen', 'updated_kitchen', 'loading_dock', 'cleared_land', 'family_friendly'], $owner);

        $this->assertTrue($result->saved);
        $this->assertSame(['cleared_land'], $result->selected);
        $this->assertSame(R::REASON_NOT_A_KEY, $result->rejected['Custom Awesome Tag']);
        $this->assertSame(R::REASON_UNKNOWN_KEY, $result->rejected['gourmet_kitchen']);
        $this->assertSame(R::REASON_NOT_APPLICABLE, $result->rejected['updated_kitchen']);
        $this->assertSame(R::REASON_NOT_APPLICABLE, $result->rejected['loading_dock']);
        $this->assertSame(R::REASON_PROHIBITED, $result->rejected['family_friendly']);

        $this->assertSame(['cleared_land'], SmartTagEvidence::query()->where('listing_id', $listing->id)->pluck('tag_key')->all());
    }

    /** @test */
    public function unauthorized_users_cannot_change_another_listings_tags(): void
    {
        $owner = $this->makeOwner();
        $intruder = $this->makeOwner('agent');
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);
        $ref = SmartTagListingRef::fromModel($listing);

        $this->writer->replaceSelections($ref, ['kitchen_island'], $owner);

        $asIntruder = $this->writer->replaceSelections($ref, ['breakfast_bar'], $intruder);
        $asGuest = $this->writer->replaceSelections($ref, [], null);

        $this->assertFalse($asIntruder->saved);
        $this->assertSame(Decision::NOT_OWNER, $asIntruder->refusal);
        $this->assertFalse($asGuest->saved);
        $this->assertSame(Decision::UNAUTHENTICATED, $asGuest->refusal);

        $this->assertSame(['kitchen_island'], SmartTagEvidence::query()->where('listing_id', $listing->id)->pluck('tag_key')->all());
        $this->assertSame(1, SmartTagManualEvent::query()->where('listing_id', $listing->id)->count());
    }

    /** @test */
    public function hire_agent_rows_archived_rows_missing_rows_and_bridge_rows_are_refused(): void
    {
        $owner = $this->makeOwner();

        $hire = $this->sellerListing($owner, ['property_type' => 'Residential'], 'hire_agent');
        $archived = $this->landlordListing($owner, ['property_type' => 'Residential Property'], 'offer_listing', ['is_archived' => true]);

        $this->assertSame(Decision::NOT_OFFER_LISTING,
            $this->writer->replaceSelections(SmartTagListingRef::fromModel($hire), ['kitchen_island'], $owner)->refusal);
        $this->assertSame(Decision::ARCHIVED,
            $this->writer->replaceSelections(SmartTagListingRef::fromModel($archived), ['kitchen_island'], $owner)->refusal);
        $this->assertSame(Decision::NOT_FOUND,
            $this->writer->replaceSelections(new SmartTagListingRef(\App\Support\SmartTags\SmartTagListingType::SellerAgent, 999999), ['kitchen_island'], $owner)->refusal);
        $this->assertSame(Decision::NOT_OWNER_EDITABLE,
            $this->writer->replaceSelections(new SmartTagListingRef(\App\Support\SmartTags\SmartTagListingType::Bridge, 1), ['kitchen_island'], $owner)->refusal);

        $this->assertSame(0, SmartTagEvidence::query()->count());
    }

    /** @test */
    public function a_listing_without_a_supported_property_type_changes_nothing(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);
        $ref = SmartTagListingRef::fromModel($listing);
        $this->writer->replaceSelections($ref, ['kitchen_island'], $owner);

        $listing->saveMeta('property_type', '');

        $result = $this->writer->replaceSelections($ref, [], $owner);
        $this->assertFalse($result->saved);
        $this->assertSame(R::REASON_NO_CONTEXT, $result->refusal);
        $this->assertSame(['kitchen_island'], SmartTagEvidence::query()->where('listing_id', $listing->id)->pluck('tag_key')->all());
    }

    /** @test */
    public function deselecting_means_unknown_not_absent(): void
    {
        $owner = $this->makeOwner('landlord');
        $listing = $this->landlordListing($owner, ['property_type' => 'Residential Property']);
        $ref = SmartTagListingRef::fromModel($listing);

        $this->writer->replaceSelections($ref, ['stainless_appliances', 'walk_in_closet'], $owner);
        $result = $this->writer->replaceSelections($ref, ['walk_in_closet'], $owner);

        $this->assertSame(['stainless_appliances'], $result->removed);
        $this->assertFalse(SmartTagAssignment::query()->where('listing_id', $listing->id)->where('tag_key', 'stainless_appliances')->exists(),
            'A deselected tag has no assignment at all');
        $this->assertSame(0, SmartTagAssignment::query()->where('listing_id', $listing->id)->where('state', 'absent')->count());
        $this->assertSame(1, SmartTagManualEvent::query()->where('listing_id', $listing->id)->where('action', 'deselected')->count());
    }

    /** @test */
    public function a_tag_answered_by_an_authoritative_property_details_field_cannot_be_manually_selected(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential', 'waterfront' => 'No']);

        $result = $this->writer->replaceSelections(SmartTagListingRef::fromModel($listing), ['waterfront', 'kitchen_island'], $owner);

        $this->assertSame(['kitchen_island'], $result->selected);
        $this->assertSame(R::REASON_ANSWERED_BY_PROPERTY_DETAILS, $result->rejected['waterfront']);
        $this->assertFalse(SmartTagEvidence::query()->where('listing_id', $listing->id)->where('tag_key', 'waterfront')->exists());
    }

    /** @test */
    public function a_previously_selected_tag_is_pruned_once_property_details_answer_it(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);
        $ref = SmartTagListingRef::fromModel($listing);

        $this->writer->replaceSelections($ref, ['waterfront'], $owner);
        $listing->saveMeta('waterfront', 'Yes');

        $result = $this->writer->replaceSelections($ref, ['waterfront'], $owner);

        $this->assertSame(['waterfront'], $result->removed);
        $this->assertSame(1, SmartTagManualEvent::query()->where('listing_id', $listing->id)
            ->where('action', SmartTagManualEvent::ACTION_PRUNED_ANSWERED_BY_STRUCTURE)->count());
    }

    /** @test */
    public function manual_plus_structured_evidence_still_produces_one_canonical_assignment(): void
    {
        $owner = $this->makeOwner();
        $listing = $this->sellerListing($owner, ['property_type' => 'Residential']);
        $ref = SmartTagListingRef::fromModel($listing);

        SmartTagEvidence::query()->create([
            'listing_type' => 'seller_agent', 'listing_id' => $listing->id, 'tag_key' => 'updated_kitchen', 'context' => 'residential.sale',
            'source' => 'structured_native_listing', 'state' => 'present', 'confidence' => 90,
        ]);

        $this->writer->replaceSelections($ref, ['updated_kitchen'], $owner);

        $this->assertSame(2, SmartTagEvidence::query()->where('listing_id', $listing->id)->where('tag_key', 'updated_kitchen')->count());
        $assignments = SmartTagAssignment::query()->where('listing_id', $listing->id)->where('tag_key', 'updated_kitchen')->get();
        $this->assertCount(1, $assignments);
        $this->assertSame('structured_native_listing', $assignments[0]->winning_source);
    }

    /** @test */
    public function one_owners_selections_do_not_affect_another_listing(): void
    {
        $a = $this->makeOwner();
        $b = $this->makeOwner();
        $listingA = $this->sellerListing($a, ['property_type' => 'Residential']);
        $listingB = $this->sellerListing($b, ['property_type' => 'Residential']);

        $this->writer->replaceSelections(SmartTagListingRef::fromModel($listingA), ['kitchen_island'], $a);
        $this->writer->replaceSelections(SmartTagListingRef::fromModel($listingB), ['breakfast_bar'], $b);
        $this->writer->replaceSelections(SmartTagListingRef::fromModel($listingA), [], $a);

        $this->assertSame([], SmartTagEvidence::query()->where('listing_id', $listingA->id)->pluck('tag_key')->all());
        $this->assertSame(['breakfast_bar'], SmartTagEvidence::query()->where('listing_id', $listingB->id)->pluck('tag_key')->all());
    }
}
