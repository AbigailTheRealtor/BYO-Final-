<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListingEdit;
use App\Models\BuyerAgentAuction;
use App\Models\BuyerAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 9B-2 — Preferred State / Counties bridge between the discrete meta and the
 * Search Areas `location_dna_preferences` blob.
 *
 *  - PREFILL: on load, discrete `state` / `counties` meta seed the blob (so the
 *    Search Areas partial pre-populates) when the blob lacks them.
 *  - WRITE-BACK: on save, a blob carrying `state` / `counties` mirrors back into the
 *    discrete meta (read by Ask AI, public views, the match engine). Non-empty guards
 *    keep it backward compatible — an empty blob value never wipes a discrete value.
 *
 * Phase 9B-3 additionally removes the discrete "Acceptable State / Acceptable Counties"
 * UI — the Search Areas blob is now the sole editing surface. The component must therefore
 * hydrate $state / $counties from the blob *before* validation so submit still passes with
 * no discrete UI supplying them. `test_submit_succeeds_with_state_and_counties_supplied_
 * only_by_blob` proves that.
 *
 * BuyerOfferListingEdit is exercised as the representative role/flow.
 *
 * SQLITE NOTE
 * -----------
 * The discrete `state` is seeded with `->call('selectStateSuggestion', ...)`, not
 * `->set('state', ...)`. `->set()` fires Livewire's `updatedState()` hook, which runs
 * `getPlaceSuggestions()` — a `UsState::where('name', 'ILIKE', ...)` typeahead lookup.
 * `ILIKE` is PostgreSQL syntax that production speaks and SQLite rejects outright
 * ("General error: 1 near \"ILIKE\": syntax error"), so the suite's `:memory:` harness
 * cannot execute it. That is a property of the harness, not of the flow under test, and
 * the same avoidance is already used by
 * tests/Feature/HireAgent/SellerLandlordHireAgentCreatePublishCharacterizationTest.php
 * and tests/Feature/Offers/TenantOfferListingEditImportantPlacesGuardTest.php.
 *
 * Nothing is skipped or weakened by the swap. `selectStateSuggestion()` is the component
 * method the real UI calls when a user picks a state from the dropdown, and it assigns
 * `$this->state` to exactly the value passed — so the component reaches the state this
 * test needs (a discrete value that disagrees with the blob) by the MORE production-
 * faithful of the two routes. `->set()` simulated the keystroke that *precedes* the pick;
 * the typeahead query it triggers is not this test's subject and is covered where it
 * belongs. Every assertion below still runs against the real `update()` write-back path.
 */
class SearchAreasStateCountyRoundTripTest extends TestCase
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

    /** Write-back: a Search Areas blob carrying state/counties mirrors into discrete meta. */
    public function test_blob_state_and_counties_write_back_to_discrete_meta(): void
    {
        $owner   = $this->makeBuyerUser();
        $auction = $this->makeBuyerAuction($owner);

        $blob = json_encode([
            'cities'   => [],
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL', 'Hillsborough County, FL'],
        ]);

        // Discrete fields hold *different* values (as the still-present duplicate UI
        // supplies them and validation requires them pre-save); the blob must win.
        //
        // `state` is seeded through the component's own `selectStateSuggestion()` — the
        // real autocomplete-selection path — rather than `->set('state', ...)`. See the
        // SQLITE NOTE on the class docblock for why.
        Livewire::actingAs($owner)
            ->test(BuyerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->call('selectStateSuggestion', 'Texas')
            ->set('counties', ['Travis County, TX'])
            ->set('location_dna_preferences_json', $blob)
            ->call('update');

        $fresh = $auction->fresh();
        $this->assertEquals('Florida', $fresh->info('state'));

        $counties = json_decode($fresh->info('counties'), true) ?? [];
        $this->assertContains('Pinellas County, FL', $counties);
        $this->assertContains('Hillsborough County, FL', $counties);
        $this->assertNotContains('Travis County, TX', $counties);
    }

    /** Write-back guard: an empty blob state never wipes an existing discrete state. */
    public function test_empty_blob_state_does_not_wipe_discrete_state(): void
    {
        $owner   = $this->makeBuyerUser();
        $auction = $this->makeBuyerAuction($owner);

        BuyerAgentAuctionMeta::create([
            'buyer_agent_auction_id' => $auction->id,
            'meta_key'               => 'state',
            'meta_value'             => 'Georgia',
        ]);

        // Blob present but state empty (user never touched Preferred State).
        Livewire::actingAs($owner)
            ->test(BuyerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('location_dna_preferences_json', json_encode(['cities' => [], 'state' => '']))
            ->call('update');

        $this->assertEquals('Georgia', $auction->fresh()->info('state'));
    }

    /**
     * 9B-3: with the discrete Acceptable State / Counties UI removed, state & counties
     * reach the component ONLY through the Search Areas blob. Submit must still pass the
     * required-field validation (pre-validation hydration seeds $state/$counties from the
     * blob) and persist the mirrored discrete meta.
     */
    public function test_submit_succeeds_with_state_and_counties_supplied_only_by_blob(): void
    {
        $owner   = $this->makeBuyerUser();
        $auction = $this->makeBuyerAuction($owner);

        $blob = json_encode([
            'cities'   => [],
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
        ]);

        // Discrete $state / $counties are deliberately left unset — the removed UI no
        // longer supplies them; only the Search Areas blob does.
        $component = Livewire::actingAs($owner)
            ->test(BuyerOfferListingEdit::class, ['auctionId' => $auction->id])
            ->set('location_dna_preferences_json', $blob)
            ->call('update');

        $component->assertHasNoErrors(['counties', 'state']);

        $fresh = $auction->fresh();
        $this->assertEquals('Florida', $fresh->info('state'));

        $counties = json_decode($fresh->info('counties'), true) ?? [];
        $this->assertContains('Pinellas County, FL', $counties);
    }

    /** Prefill: discrete state/counties seed the in-memory blob when it lacks them. */
    public function test_discrete_state_and_counties_prefill_the_blob_on_load(): void
    {
        $owner   = $this->makeBuyerUser();
        $auction = $this->makeBuyerAuction($owner);

        BuyerAgentAuctionMeta::insert([
            ['buyer_agent_auction_id' => $auction->id, 'meta_key' => 'state',    'meta_value' => 'Georgia'],
            ['buyer_agent_auction_id' => $auction->id, 'meta_key' => 'counties', 'meta_value' => json_encode(['Cobb County, GA'])],
        ]);

        $component = Livewire::actingAs($owner)
            ->test(BuyerOfferListingEdit::class, ['auctionId' => $auction->id]);

        $ldna = $component->instance()->existingLocationDna;

        $this->assertEquals('Georgia', $ldna['state'] ?? null);
        $this->assertContains('Cobb County, GA', $ldna['counties'] ?? []);
    }
}
