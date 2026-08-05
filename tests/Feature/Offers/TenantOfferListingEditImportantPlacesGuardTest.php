<?php

namespace Tests\Feature\Offers;

use App\Http\Livewire\OfferListing\Tenant\TenantOfferListingEdit;
use App\Models\TenantAgentAuction;
use App\Models\TenantAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Important Places submit guard on the Tenant offer-listing EDIT flow.
 *
 * WHY THIS SUITE EXISTS, AND WHAT IT CORRECTS
 * -------------------------------------------
 * A `grep` for `assertImportantPlacesValid()` finds it in seven of the eight components that carry
 * Important Places, and NOT in {@see TenantOfferListingEdit} — which reads like a missing guard on
 * the one flow that lacks it.
 *
 * It is not missing. This component runs the identical check inline, in the same full-submit-only
 * position, and aborts the save:
 *
 *     $ipErrors = $this->importantPlacesService()->validate($this->important_places_json ?? '');
 *     if (!empty($ipErrors)) { $this->dispatchBrowserEvent('edit-validation-failed', ...); return; }
 *
 * What differs is only how the failure LEAVES the method. `assertImportantPlacesValid()` throws a
 * `ValidationException`; this component dispatches a browser event and returns. That is deliberate
 * and load-bearing: `TenantOfferListingEdit` is the only one of these components that wraps its
 * save in an explicit `DB::beginTransaction()`, so it aborts before the transaction opens rather
 * than throwing across it. Its siblings can throw safely because none of them opens one.
 *
 * So the guard is pinned HERE, by behaviour, rather than by the presence of a helper call. A test
 * that asserted `assertHasErrors('important_places_json')` — the shape used for the seven throwing
 * components — would fail against correct code, because no exception is ever raised on this path.
 * What matters is that the save does not happen.
 */
class TenantOfferListingEditImportantPlacesGuardTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantUser(): User
    {
        return User::factory()->create(['user_type' => 'tenant']);
    }

    /**
     * `tenant_agent_auctions` stores its extended fields as EAV meta — there is no `address`
     * column, which is the schema asymmetry the Buyer fixtures do not have to think about.
     */
    private function makeTenantAuction(User $user, array $meta = []): TenantAgentAuction
    {
        $auction = (new TenantAgentAuction())->forceFill([
            'user_id'     => $user->id,
            'title'       => 'Test Tenant Listing',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $auction->save();

        $meta = array_merge([
            'user_type'      => 'tenant',
            'workflow_type'  => 'offer_listing',
            'property_items' => '[]',
        ], $meta);

        $rows = [];

        foreach ($meta as $key => $value) {
            $rows[] = [
                'tenant_agent_auction_id' => $auction->id,
                'meta_key'                => $key,
                'meta_value'              => $value,
            ];
        }

        TenantAgentAuctionMeta::insert($rows);

        return $auction;
    }

    /** A row with every field the validator requires. */
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

    /**
     * A STARTED but incomplete row — a type was chosen and the address left blank.
     *
     * A fully empty row is not this: the normaliser drops those before validation, which is what
     * lets a user add a blank row and walk away without being shouted at.
     */
    private function partialRow(): array
    {
        return $this->completeRow(['address' => '']);
    }

    /**
     * Mount the edit component with exactly the fields the full-submit validation demands, so a
     * blocked save can only be the Important Places guard and not an unrelated required field.
     *
     * `state` and `counties` are deliberately NOT set. This component's full-submit check does not
     * require them, and setting `state` would fire `updatedState()`, which reaches a reference-model
     * lookup built on `ILIKE` — Postgres-only syntax that SQLite rejects outright, and a
     * pre-existing incompatibility this suite has no business dragging in.
     */
    private function editComponent(User $owner, TenantAgentAuction $auction)
    {
        return Livewire::actingAs($owner)
            ->test(TenantOfferListingEdit::class, ['auctionId' => $auction->id, 'user_type' => 'tenant'])
            ->set('listing_title', 'Test Tenant Listing')
            ->set('listing_date', '2026-01-01')
            ->set('expiration_date', '2026-12-31')
            ->set('meeting_Preference', 'Virtual')
            ->set('first_name', 'Test')
            ->set('last_name', 'Tenant')
            ->set('phone_number', '5551234567')
            ->set('email', 'tenant@example.com');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · DRAFT SAVE STILL SUCCEEDS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * A partial row must survive a draft save untouched.
     *
     * This is the half that is easy to break by "tightening" validation: a user halfway through
     * entering a place hits Save Draft, and a guard that ignored `$_isDraftSave` would refuse the
     * save and lose the rest of their edits along with it.
     */
    public function test_draft_save_succeeds_with_an_incomplete_important_place(): void
    {
        $owner   = $this->makeTenantUser();
        $auction = $this->makeTenantAuction($owner);

        $this->editComponent($owner, $auction)
            ->set('important_places_json', json_encode([$this->partialRow()]))
            ->call('saveDraftOnly')
            ->assertHasNoErrors();

        $this->assertSame(
            1,
            (int) $auction->fresh()->is_draft,
            'A draft save must still land, even with a half-finished Important Place.'
        );
    }

    /** And a complete row round-trips through the draft path into its own meta key. */
    public function test_draft_save_persists_a_complete_important_place(): void
    {
        $owner   = $this->makeTenantUser();
        $auction = $this->makeTenantAuction($owner);

        $this->editComponent($owner, $auction)
            ->set('important_places_json', json_encode([$this->completeRow()]))
            ->call('saveDraftOnly');

        $saved = json_decode($auction->fresh()->info('important_places_json'), true) ?? [];

        $this->assertCount(1, $saved);
        $this->assertSame('Work', $saved[0]['type']);
        $this->assertSame('123 Main St, Tampa, FL', $saved[0]['address']);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · FULL SUBMIT BLOCKS AN INCOMPLETE ROW
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The guard, asserted by its EFFECT rather than by its mechanism.
     *
     * The component returns before `DB::beginTransaction()`, so the listing is never published:
     * `is_draft` does not flip to 0. Asserting on a thrown validation error would be asserting the
     * wrong thing entirely — this path does not throw, by design.
     */
    public function test_full_submit_is_blocked_by_an_incomplete_important_place(): void
    {
        $owner   = $this->makeTenantUser();
        $auction = $this->makeTenantAuction($owner);

        $auction->is_draft = 1;
        $auction->save();

        $this->editComponent($owner, $auction)
            ->set('important_places_json', json_encode([$this->partialRow()]))
            ->call('update');

        $this->assertSame(
            1,
            (int) $auction->fresh()->is_draft,
            'A partial Important Place must abort the full submit — the listing must not publish.'
        );
    }

    /** The abort happens before any write, so previously stored rows are left alone. */
    public function test_a_blocked_submit_leaves_stored_important_places_untouched(): void
    {
        $owner    = $this->makeTenantUser();
        $original = json_encode([$this->completeRow(['address' => '900 Original Ave, Tampa, FL'])]);
        $auction  = $this->makeTenantAuction($owner, ['important_places_json' => $original]);

        $this->editComponent($owner, $auction)
            ->set('important_places_json', json_encode([$this->partialRow()]))
            ->call('update');

        $this->assertSame(
            $original,
            $auction->fresh()->info('important_places_json'),
            'A blocked submit must not overwrite what was already stored.'
        );
    }

    /** The complement: a complete row does NOT block, so the guard is not simply always-on. */
    public function test_full_submit_proceeds_with_a_complete_important_place(): void
    {
        $owner   = $this->makeTenantUser();
        $auction = $this->makeTenantAuction($owner);

        $auction->is_draft = 1;
        $auction->save();

        $this->editComponent($owner, $auction)
            ->set('important_places_json', json_encode([$this->completeRow()]))
            ->call('update');

        $this->assertSame(
            0,
            (int) $auction->fresh()->is_draft,
            'A complete Important Place must not stand in the way of publishing.'
        );
    }
}
