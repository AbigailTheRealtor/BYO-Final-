<?php

namespace Tests\Feature\Security;

use App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction as LandlordHireComponent;
use App\Http\Livewire\HireSellerAgent\SellerAgentAuction as SellerHireComponent;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Phase 1 — Seller / Landlord Hire Agent ownership regression tests.
 *
 * S1 (Livewire takeover). Both Hire Agent wizards resolved the row to write to
 * with an unscoped `Model::find($this->listingId)` and then assigned
 * `user_id = Auth::id()`. `listingId` is a public Livewire property, i.e. fully
 * client-controlled in the hydration payload, so any authenticated user could
 * point it at a victim's listing and both overwrite its contents AND transfer
 * ownership of the row to themselves.
 *
 * S2 (legacy controller endpoints). The same unscoped-find-then-reassign shape
 * in SellerAgentAuctionController::updateSellerAgentHireAuction() (POST
 * hire/agent/seller/update) and LandlordAgentAuctionController::update()
 * (POST landlord/hire/agent/auction/edit/{id}). Both routes carry
 * `web | auth | verified`, so guests are already turned away at the middleware;
 * the exposure was to any *authenticated* user, which is what these tests drive.
 *
 * Every attack case asserts three things, not just the refusal:
 *   1. the operation is rejected with 403;
 *   2. the victim row still belongs to the victim (no ownership transfer);
 *   3. the victim's data is byte-for-byte unchanged (no partial write).
 *
 * A refusal that still mutated the victim's row would be a failed fix, so
 * asserting only on the status code would be too weak.
 */
class SellerLandlordHireAgentOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    private const VICTIM_TITLE   = 'Victim Hire Agent Listing';
    private const VICTIM_ADDRESS = '1 Victim Way';

    protected function setUp(): void
    {
        parent::setUp();
        // CSRF is irrelevant to authorization; disable only that middleware so the
        // endpoint tests exercise ownership, not token state.
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /**
     * Assert the callable fails closed with a 403 rather than performing the write.
     *
     * withoutExceptionHandling() is required for the Livewire paths: Livewire's
     * test harness dispatches through the HTTP kernel, so with the default handler
     * in place the abort(403) is rendered into a response and never reaches the
     * test. (The write is still refused either way — the victim-intact assertions
     * that follow each call prove that independently — but the status code is only
     * observable with the handler disabled.)
     */
    private function assertForbids(callable $operation): void
    {
        $this->withoutExceptionHandling();

        try {
            $operation();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode(), 'Ownership failures must surface as 403');

            return;
        }

        $this->fail('Expected a 403 HttpException — the unauthorized operation was allowed through.');
    }

    private function sellerVictimListing(User $victim): SellerAgentAuction
    {
        $listing = SellerAgentAuction::forceCreate([
            'user_id'     => $victim->id,
            'title'       => self::VICTIM_TITLE,
            'address'     => self::VICTIM_ADDRESS,
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]);
        $listing->saveMeta('workflow_type', 'hire_agent');
        $listing->saveMeta('first_name', 'Victoria');

        return $listing->fresh();
    }

    private function landlordVictimListing(User $victim): LandlordAgentAuction
    {
        $listing = LandlordAgentAuction::forceCreate([
            'user_id'  => $victim->id,
            'title'    => self::VICTIM_TITLE,
            'is_draft' => false,
        ]);
        $listing->saveMeta('workflow_type', 'hire_agent');
        $listing->saveMeta('first_name', 'Victoria');
        $listing->saveMeta('address', self::VICTIM_ADDRESS);

        return $listing->fresh();
    }

    /** The victim's row must be untouched: same owner, same title, same metadata. */
    private function assertSellerVictimIntact(SellerAgentAuction $listing, User $victim): void
    {
        $fresh = $listing->fresh();

        $this->assertNotNull($fresh, 'Victim listing must still exist');
        $this->assertSame($victim->id, $fresh->user_id, 'Victim must still own the listing — no ownership transfer');
        $this->assertSame(self::VICTIM_TITLE, $fresh->title, 'Victim listing title must be unchanged');
        $this->assertSame(self::VICTIM_ADDRESS, $fresh->address, 'Victim listing address must be unchanged');
        $this->assertSame('Victoria', $fresh->info('first_name'), 'Victim metadata must be unchanged');
    }

    private function assertLandlordVictimIntact(LandlordAgentAuction $listing, User $victim): void
    {
        $fresh = $listing->fresh();

        $this->assertNotNull($fresh, 'Victim listing must still exist');
        $this->assertSame($victim->id, $fresh->user_id, 'Victim must still own the listing — no ownership transfer');
        $this->assertSame(self::VICTIM_TITLE, $fresh->title, 'Victim listing title must be unchanged');
        $this->assertSame(self::VICTIM_ADDRESS, $fresh->info('address'), 'Victim listing address meta must be unchanged');
        $this->assertSame('Victoria', $fresh->info('first_name'), 'Victim metadata must be unchanged');
    }

    /** Minimum valid Seller submission (see the Phase 0 characterization test). */
    private function sellerFormWithValidData()
    {
        return Livewire::test(SellerHireComponent::class)
            ->set('listing_title', 'Attacker Supplied Title')
            ->set('property_type', 'Residential')
            // The street address is validated on full submit (ValidStreetAddress),
            // so a form that is meant to be otherwise-valid must carry a real one.
            ->set('address', '123 Main St')
            ->call('selectStateSuggestion', 'Florida')
            ->set('first_name', 'Mallory')
            ->set('last_name', 'Attacker')
            ->set('phone_number', '8135550999')
            ->set('email', 'mallory@example.test')
            ->set('current_status', 'Ready to Sell')
            ->set('compatibility_preferences.seller_specific.communication_style', 'Email')
            ->set('compatibility_preferences.seller_specific.negotiation_style', 'Collaborative')
            ->set('compatibility_preferences.seller_specific.primary_transaction_goal', 'Highest Price')
            ->set('compatibility_preferences.seller_specific.representation_priorities', ['Market Knowledge'])
            ->set('compatibility_preferences.seller_specific.preferred_agent_working_style', 'Proactive');
    }

    private function landlordFormWithValidData()
    {
        return Livewire::test(LandlordHireComponent::class)
            ->set('listing_title', 'Attacker Supplied Title')
            // The street address is validated on full submit (ValidStreetAddress),
            // so a form that is meant to be otherwise-valid must carry a real one.
            ->set('address', '123 Main St')
            ->set('first_name', 'Mallory')
            ->set('last_name', 'Attacker')
            ->set('phone_number', '8135550999')
            ->set('email', 'mallory@example.test')
            ->set('desired_lease_length', ['12 Months'])
            ->set('compatibility_preferences.landlord_specific.communication_style', 'Phone')
            ->set('compatibility_preferences.landlord_specific.negotiation_style', 'Firm')
            ->set('compatibility_preferences.landlord_specific.primary_leasing_goal', 'Maximum Rent')
            ->set('compatibility_preferences.landlord_specific.representation_priorities', ['Tenant Screening'])
            ->set('compatibility_preferences.landlord_specific.preferred_agent_working_style', 'Hands On');
    }

    // =====================================================================
    // S1 — SELLER LIVEWIRE
    // =====================================================================

    public function test_seller_owner_can_publish_and_then_update_their_own_listing(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        $this->actingAs($owner);

        $this->sellerFormWithValidData()->call('store');

        $listing = SellerAgentAuction::where('user_id', $owner->id)->firstOrFail();

        // Resume the same listing and submit again — the owner update path must
        // still work after the fix, writing to the SAME row rather than a new one.
        $this->sellerFormWithValidData()
            ->set('listing_title', 'Owner Updated Title')
            ->set('listingId', $listing->id)
            ->call('store');

        $this->assertSame(
            1,
            SellerAgentAuction::where('user_id', $owner->id)->count(),
            'Owner update must reuse the existing row, not create a second one'
        );
        $this->assertSame('Owner Updated Title', $listing->fresh()->title);
        $this->assertSame($owner->id, $listing->fresh()->user_id);
    }

    public function test_seller_owner_can_save_a_draft_on_their_own_listing(): void
    {
        $owner = User::factory()->create(['user_type' => 'seller']);
        $this->actingAs($owner);

        $listing = SellerAgentAuction::forceCreate([
            'user_id'  => $owner->id,
            'title'    => 'Owner Draft',
            'address'  => 'Owner Address',
            'is_draft' => true,
        ]);

        Livewire::test(SellerHireComponent::class)
            ->set('listingId', $listing->id)
            ->set('listing_title', 'Owner Draft Updated')
            ->call('saveDraft');

        $this->assertSame('Owner Draft Updated', $listing->fresh()->title, 'Owner draft save must still work');
        $this->assertSame($owner->id, $listing->fresh()->user_id);
    }

    public function test_seller_attacker_cannot_publish_into_a_victim_listing(): void
    {
        $victim   = User::factory()->create(['user_type' => 'seller']);
        $attacker = User::factory()->create(['user_type' => 'seller']);
        $listing  = $this->sellerVictimListing($victim);

        $this->actingAs($attacker);

        $this->assertForbids(function () use ($listing) {
            $this->sellerFormWithValidData()
                ->set('listingId', $listing->id)
                ->call('store');
        });

        $this->assertSellerVictimIntact($listing, $victim);
        $this->assertSame(
            0,
            SellerAgentAuction::where('user_id', $attacker->id)->count(),
            'A rejected takeover must not silently create a listing for the attacker either'
        );
    }

    public function test_seller_attacker_cannot_save_a_draft_into_a_victim_listing(): void
    {
        $victim   = User::factory()->create(['user_type' => 'seller']);
        $attacker = User::factory()->create(['user_type' => 'seller']);
        $listing  = $this->sellerVictimListing($victim);

        $this->actingAs($attacker);

        $this->assertForbids(function () use ($listing) {
            Livewire::test(SellerHireComponent::class)
                ->set('listingId', $listing->id)
                ->set('listing_title', 'Stolen By Attacker')
                ->call('saveDraft');
        });

        $this->assertSellerVictimIntact($listing, $victim);
    }

    public function test_seller_attacker_cannot_mount_a_victim_listing(): void
    {
        $victim   = User::factory()->create(['user_type' => 'seller']);
        $attacker = User::factory()->create(['user_type' => 'seller']);
        $listing  = $this->sellerVictimListing($victim);

        $this->actingAs($attacker);

        $this->assertForbids(function () use ($listing) {
            Livewire::test(SellerHireComponent::class, ['listingId' => $listing->id]);
        });

        $this->assertSellerVictimIntact($listing, $victim);
    }

    /**
     * Defence in depth: store() must refuse on its own, not merely because
     * hydrate() ran first. Calling the method on a bare instance bypasses the
     * Livewire lifecycle entirely, so only the in-method guard can stop it.
     */
    public function test_seller_store_refuses_a_foreign_listing_without_the_hydrate_hook(): void
    {
        $victim   = User::factory()->create(['user_type' => 'seller']);
        $attacker = User::factory()->create(['user_type' => 'seller']);
        $listing  = $this->sellerVictimListing($victim);

        $this->actingAs($attacker);

        $component             = new SellerHireComponent();
        $component->listingId  = $listing->id;

        $this->assertForbids(fn () => $component->store());
        $this->assertSellerVictimIntact($listing, $victim);
    }

    public function test_seller_save_draft_refuses_a_foreign_listing_without_the_hydrate_hook(): void
    {
        $victim   = User::factory()->create(['user_type' => 'seller']);
        $attacker = User::factory()->create(['user_type' => 'seller']);
        $listing  = $this->sellerVictimListing($victim);

        $this->actingAs($attacker);

        $component            = new SellerHireComponent();
        $component->listingId = $listing->id;

        $this->assertForbids(fn () => $component->saveDraft());
        $this->assertSellerVictimIntact($listing, $victim);
    }

    // =====================================================================
    // S1 — LANDLORD LIVEWIRE
    // =====================================================================

    public function test_landlord_owner_can_publish_and_then_update_their_own_listing(): void
    {
        $owner = User::factory()->create(['user_type' => 'landlord']);
        $this->actingAs($owner);

        $this->landlordFormWithValidData()->call('store');

        $listing = LandlordAgentAuction::where('user_id', $owner->id)->firstOrFail();

        $this->landlordFormWithValidData()
            ->set('listing_title', 'Owner Updated Title')
            ->set('listingId', $listing->id)
            ->call('store');

        $this->assertSame(
            1,
            LandlordAgentAuction::where('user_id', $owner->id)->count(),
            'Owner update must reuse the existing row, not create a second one'
        );
        $this->assertSame('Owner Updated Title', $listing->fresh()->title);
        $this->assertSame($owner->id, $listing->fresh()->user_id);
    }

    public function test_landlord_owner_can_save_a_draft_on_their_own_listing(): void
    {
        $owner = User::factory()->create(['user_type' => 'landlord']);
        $this->actingAs($owner);

        $listing = LandlordAgentAuction::forceCreate([
            'user_id'  => $owner->id,
            'title'    => 'Owner Draft',
            'is_draft' => true,
        ]);

        Livewire::test(LandlordHireComponent::class)
            ->set('listingId', $listing->id)
            ->set('listing_title', 'Owner Draft Updated')
            ->call('saveDraft');

        $this->assertSame('Owner Draft Updated', $listing->fresh()->title, 'Owner draft save must still work');
        $this->assertSame($owner->id, $listing->fresh()->user_id);
    }

    public function test_landlord_attacker_cannot_publish_into_a_victim_listing(): void
    {
        $victim   = User::factory()->create(['user_type' => 'landlord']);
        $attacker = User::factory()->create(['user_type' => 'landlord']);
        $listing  = $this->landlordVictimListing($victim);

        $this->actingAs($attacker);

        $this->assertForbids(function () use ($listing) {
            $this->landlordFormWithValidData()
                ->set('listingId', $listing->id)
                ->call('store');
        });

        $this->assertLandlordVictimIntact($listing, $victim);
        $this->assertSame(
            0,
            LandlordAgentAuction::where('user_id', $attacker->id)->count(),
            'A rejected takeover must not silently create a listing for the attacker either'
        );
    }

    public function test_landlord_attacker_cannot_save_a_draft_into_a_victim_listing(): void
    {
        $victim   = User::factory()->create(['user_type' => 'landlord']);
        $attacker = User::factory()->create(['user_type' => 'landlord']);
        $listing  = $this->landlordVictimListing($victim);

        $this->actingAs($attacker);

        $this->assertForbids(function () use ($listing) {
            Livewire::test(LandlordHireComponent::class)
                ->set('listingId', $listing->id)
                ->set('listing_title', 'Stolen By Attacker')
                ->call('saveDraft');
        });

        $this->assertLandlordVictimIntact($listing, $victim);
    }

    public function test_landlord_attacker_cannot_mount_a_victim_listing(): void
    {
        $victim   = User::factory()->create(['user_type' => 'landlord']);
        $attacker = User::factory()->create(['user_type' => 'landlord']);
        $listing  = $this->landlordVictimListing($victim);

        $this->actingAs($attacker);

        $this->assertForbids(function () use ($listing) {
            Livewire::test(LandlordHireComponent::class, ['listingId' => $listing->id]);
        });

        $this->assertLandlordVictimIntact($listing, $victim);
    }

    public function test_landlord_store_refuses_a_foreign_listing_without_the_hydrate_hook(): void
    {
        $victim   = User::factory()->create(['user_type' => 'landlord']);
        $attacker = User::factory()->create(['user_type' => 'landlord']);
        $listing  = $this->landlordVictimListing($victim);

        $this->actingAs($attacker);

        $component            = new LandlordHireComponent();
        $component->listingId = $listing->id;

        $this->assertForbids(fn () => $component->store());
        $this->assertLandlordVictimIntact($listing, $victim);
    }

    public function test_landlord_save_draft_refuses_a_foreign_listing_without_the_hydrate_hook(): void
    {
        $victim   = User::factory()->create(['user_type' => 'landlord']);
        $attacker = User::factory()->create(['user_type' => 'landlord']);
        $listing  = $this->landlordVictimListing($victim);

        $this->actingAs($attacker);

        $component            = new LandlordHireComponent();
        $component->listingId = $listing->id;

        $this->assertForbids(fn () => $component->saveDraft());
        $this->assertLandlordVictimIntact($listing, $victim);
    }

    // =====================================================================
    // S2 — LEGACY SELLER ENDPOINT (POST hire/agent/seller/update)
    // =====================================================================

    private function sellerEndpointPayload(int $id, string $address): array
    {
        return [
            'id'             => $id,
            'address'        => $address,
            'auction_type'   => 'Traditional',
            'auction_length' => '30 days',
            'first_name'     => 'Payload',
        ];
    }

    public function test_seller_legacy_endpoint_still_works_for_the_owner(): void
    {
        $owner   = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->sellerVictimListing($owner);

        $response = $this->actingAs($owner)
            ->post(route('updateSellerAgentHireAuction'), $this->sellerEndpointPayload($listing->id, '99 Owner Street'));

        $response->assertRedirect();

        $fresh = $listing->fresh();
        $this->assertSame('99 Owner Street', $fresh->address, 'Owner must still be able to update their own listing');
        $this->assertSame('Payload', $fresh->info('first_name'), 'Owner update must still write metadata');
        $this->assertSame($owner->id, $fresh->user_id);
    }

    public function test_seller_legacy_endpoint_rejects_a_foreign_authenticated_user(): void
    {
        $victim   = User::factory()->create(['user_type' => 'seller']);
        $attacker = User::factory()->create(['user_type' => 'seller']);
        $listing  = $this->sellerVictimListing($victim);

        $this->actingAs($attacker)
            ->post(route('updateSellerAgentHireAuction'), $this->sellerEndpointPayload($listing->id, '66 Attacker Street'))
            ->assertForbidden();

        $this->assertSellerVictimIntact($listing, $victim);
    }

    /**
     * Guests are stopped one layer earlier, by the route's `auth` middleware
     * (redirect to login), so they never reach the ownership scope. Asserted
     * anyway to pin the outer layer in place: if `auth` were ever dropped from
     * this legacy route, the ownership scope would still refuse (Auth::id() is
     * null, so no row matches) — but this test would change shape and say so.
     */
    public function test_seller_legacy_endpoint_rejects_a_guest(): void
    {
        $victim  = User::factory()->create(['user_type' => 'seller']);
        $listing = $this->sellerVictimListing($victim);

        $this->post(route('updateSellerAgentHireAuction'), $this->sellerEndpointPayload($listing->id, '66 Guest Street'))
            ->assertRedirect(route('login'));

        $this->assertSellerVictimIntact($listing, $victim);
    }

    // =====================================================================
    // S2 — LEGACY LANDLORD ENDPOINT (POST landlord/hire/agent/auction/edit/{id})
    // =====================================================================

    private function landlordEndpointPayload(string $address): array
    {
        return [
            'address'        => $address,
            'auction_type'   => 'Traditional',
            'auction_length' => '30 days',
            'first_name'     => 'Payload',
        ];
    }

    /**
     * The owner must pass the new ownership guard — i.e. NOT be met with a 403.
     *
     * This asserts authorization only, deliberately not a successful update: this
     * legacy endpoint cannot complete for anyone, owner included. Its body sets
     * `$auction->auction_length`, and `landlord_agent_auctions` has no such column
     * (columns: id, user_id, auction_type, is_approved, is_draft, is_sold,
     * sold_date, timestamps, display_bids, auction_ended, listing_id, referral_*,
     * title, is_archived). Every call therefore throws inside the try, hits
     * DB::rollBack(), and redirects back. That breakage is PRE-EXISTING and
     * entirely separate from ownership — Phase 1 does not touch it. Asserting a
     * successful write here would fail for a reason that has nothing to do with
     * the security fix; asserting "not 403" is the honest ownership contract.
     */
    public function test_landlord_legacy_endpoint_does_not_reject_the_owner(): void
    {
        $owner   = User::factory()->create(['user_type' => 'landlord']);
        $listing = $this->landlordVictimListing($owner);

        $response = $this->actingAs($owner)->post(
            route('landlord.hire.agent.auction.update', ['id' => $listing->id]),
            $this->landlordEndpointPayload('99 Owner Street')
        );

        $this->assertNotSame(403, $response->getStatusCode(), 'The owner must not be blocked by the ownership guard');
        $this->assertSame($owner->id, $listing->fresh()->user_id, 'Owner retains ownership');
    }

    /**
     * Records the pre-existing breakage referenced above, so it is visible rather
     * than silently absorbed by the test that only checks authorization. If this
     * ever starts passing a write through, the endpoint was repaired elsewhere and
     * the owner test above should be upgraded to assert the write.
     */
    public function test_landlord_legacy_endpoint_is_inert_for_everyone_pre_existing(): void
    {
        $owner   = User::factory()->create(['user_type' => 'landlord']);
        $listing = $this->landlordVictimListing($owner);

        $this->actingAs($owner)->post(
            route('landlord.hire.agent.auction.update', ['id' => $listing->id]),
            $this->landlordEndpointPayload('99 Owner Street')
        );

        $this->assertSame(
            self::VICTIM_ADDRESS,
            $listing->fresh()->info('address'),
            'PRE-EXISTING: the landlord legacy endpoint rolls back on the missing auction_length column'
        );
    }

    public function test_landlord_legacy_endpoint_rejects_a_foreign_authenticated_user(): void
    {
        $victim   = User::factory()->create(['user_type' => 'landlord']);
        $attacker = User::factory()->create(['user_type' => 'landlord']);
        $listing  = $this->landlordVictimListing($victim);

        $this->actingAs($attacker)->post(
            route('landlord.hire.agent.auction.update', ['id' => $listing->id]),
            $this->landlordEndpointPayload('66 Attacker Street')
        )->assertForbidden();

        $this->assertLandlordVictimIntact($listing, $victim);
    }

    /** As with the seller endpoint, guests are stopped by `auth` before the guard. */
    public function test_landlord_legacy_endpoint_rejects_a_guest(): void
    {
        $victim  = User::factory()->create(['user_type' => 'landlord']);
        $listing = $this->landlordVictimListing($victim);

        $this->post(
            route('landlord.hire.agent.auction.update', ['id' => $listing->id]),
            $this->landlordEndpointPayload('66 Guest Street')
        )->assertRedirect(route('login'));

        $this->assertLandlordVictimIntact($listing, $victim);
    }
}
