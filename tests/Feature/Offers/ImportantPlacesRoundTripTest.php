<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 9C — Important Places save/edit/draft round-trip through the Buyer offer-listing
 * components. Data lives in the ADDITIVE `important_places_json` meta key; the legacy
 * commute fields are neither read nor written here.
 *
 * BuyerOfferListingEdit / BuyerOfferListing are exercised as the representative role/flow.
 */
class ImportantPlacesRoundTripTest extends TestCase
{
    use DatabaseTransactions;

    private function makeBuyerUser(): User
    {
        return User::factory()->create(['user_type' => 'buyer']);
    }

    private function makeBuyerAuction(User $user): BuyerAgentAuction
    {
        $auction = BuyerAgentAuction::create([
            'user_id'     => $user->id,
            'address'     => '',
            'title'       => 'Test Buyer Listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);

        BuyerAgentAuctionMeta::create([
            'buyer_agent_auction_id' => $auction->id,
            'meta_key'               => 'workflow_type',
            'meta_value'             => 'offer_listing',
        ]);

        return $auction;
    }

    private function completeRow(array $overrides = []): array
    {
        return array_merge([
            'type'           => 'Work',
            'type_other'     => '',
            'address'        => '123 Main St, Tampa, FL',
            'lat'            => 27.95,
            'lng'            => -82.45,
            'distance_pref'  => 'miles',
            'distance_value' => 5,
            'travel_mode'    => 'driving',
        ], $overrides);
    }

    /** A blob/discrete pair that satisfies the required counties/state validation on submit. */
    private function editComponent(User $owner, BuyerAgentAuction $auction)
    {
        return Livewire::actingAs($owner)
            ->test(BuyerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('state', 'Florida')
            ->set('counties', ['Pinellas County, FL']);
    }

    public function test_complete_rows_persist_and_reload_on_edit_submit(): void
    {
        $owner   = $this->makeBuyerUser();
        $auction = $this->makeBuyerAuction($owner);

        $json = json_encode([$this->completeRow(), $this->completeRow([
            'type' => 'Other', 'type_other' => 'Sailing club', 'distance_pref' => 'minutes', 'distance_value' => 20,
        ])]);

        $this->editComponent($owner, $auction)
            ->set('important_places_json', $json)
            ->call('update')
            ->assertHasNoErrors();

        $stored = json_decode($auction->fresh()->info('important_places_json'), true);
        $this->assertCount(2, $stored);
        $this->assertSame('Work', $stored[0]['type']);
        $this->assertSame('Sailing club', $stored[1]['type_other']);
        $this->assertSame('minutes', $stored[1]['distance_pref']);

        // Reload a fresh component — the partial prefill array is hydrated from the meta.
        $reloaded = Livewire::actingAs($owner)
            ->test(BuyerOfferListingEdit::class, ['auctionId' => $auction->id]);

        $prefill = $reloaded->instance()->existingImportantPlaces;
        $this->assertCount(2, $prefill);
        $this->assertSame('123 Main St, Tampa, FL', $prefill[0]['address']);
    }

    public function test_fully_empty_rows_are_dropped_on_save(): void
    {
        $owner   = $this->makeBuyerUser();
        $auction = $this->makeBuyerAuction($owner);

        $json = json_encode([
            $this->completeRow(),
            ['type' => '', 'type_other' => '', 'address' => '', 'distance_value' => ''],
        ]);

        $this->editComponent($owner, $auction)
            ->set('important_places_json', $json)
            ->call('update');

        $stored = json_decode($auction->fresh()->info('important_places_json'), true);
        $this->assertCount(1, $stored);
    }

    public function test_partial_row_blocks_full_submit_and_leaves_meta_untouched(): void
    {
        $owner   = $this->makeBuyerUser();
        $auction = $this->makeBuyerAuction($owner);

        // Seed a valid stored value so we can prove the invalid submit does NOT overwrite it.
        $auction->saveMeta('important_places_json', json_encode([$this->completeRow(['address' => '9 Oak Ave'])]));

        // Address entered but no distance → partially complete → submit must abort.
        $partial = json_encode([['type' => 'School', 'address' => '1 School Rd', 'distance_value' => '']]);

        $this->editComponent($owner, $auction)
            ->set('important_places_json', $partial)
            ->call('update');

        $stored = json_decode($auction->fresh()->info('important_places_json'), true);
        $this->assertCount(1, $stored);
        $this->assertSame('9 Oak Ave', $stored[0]['address']); // unchanged — save was blocked
    }

    public function test_partial_rows_are_preserved_on_draft(): void
    {
        $owner = $this->makeBuyerUser();

        // Draft path runs saveAllMetadata (→ saveImportantPlaces) WITHOUT the submit-time
        // validation, so a partially-completed row is kept for the user to finish later.
        $partial = json_encode([['type' => 'Gym/Fitness', 'address' => '77 Fit Way', 'distance_value' => '']]);

        $component = Livewire::actingAs($owner)
            ->test(BuyerOfferListing::class)
            ->set('listing_title', 'Draft With Important Place')
            ->set('important_places_json', $partial)
            ->call('saveDraft');

        $listingId = $component->get('listingId');
        $this->assertNotNull($listingId);

        $draft  = BuyerAgentAuction::find($listingId);
        $stored = json_decode($draft->info('important_places_json'), true);
        $this->assertCount(1, $stored);
        $this->assertSame('77 Fit Way', $stored[0]['address']);
        $this->assertNull($stored[0]['distance_value']); // partial row preserved
    }

    public function test_commute_fields_are_untouched_by_important_places(): void
    {
        $owner   = $this->makeBuyerUser();
        $auction = $this->makeBuyerAuction($owner);

        // Pre-existing commute meta must survive an Important Places save unchanged.
        $auction->saveMeta('commute_destination_zip', '33701');
        $auction->saveMeta('max_commute_minutes', '30');
        $auction->saveMeta('commute_mode', 'driving');

        $this->editComponent($owner, $auction)
            ->set('commute_destination_zip', '33701')
            ->set('max_commute_minutes', '30')
            ->set('commute_mode', 'driving')
            ->set('important_places_json', json_encode([$this->completeRow()]))
            ->call('update');

        $fresh = $auction->fresh();
        $this->assertSame('33701', $fresh->info('commute_destination_zip'));
        $this->assertSame('30', $fresh->info('max_commute_minutes'));
        $this->assertSame('driving', $fresh->info('commute_mode'));
        $this->assertNotEmpty(json_decode($fresh->info('important_places_json'), true));
    }
}
