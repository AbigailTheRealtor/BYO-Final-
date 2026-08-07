<?php

namespace Tests\Feature\HireAgent;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CHARACTERIZATION — Seller & Landlord "Hire Agent" create-to-publish.
 *
 * These tests record what the two Hire Agent wizards do TODAY. They are a
 * baseline, not a specification: nothing here is an endorsement of the current
 * behaviour, and no application code was changed to make them pass.
 *
 * Scope (mirrored for both roles):
 *   • an authenticated owner can mount the role-specific component
 *   • a valid submission publishes a row (is_draft = 0)
 *   • `workflow_type` meta is stamped `hire_agent`
 *   • `user_id` is the authenticated submitter
 *   • role-specific metadata survives the write
 *   • a clearly incomplete submission is rejected and writes nothing
 *
 * DELIBERATELY NOT CHARACTERISED — cross-user ownership
 * ----------------------------------------------------
 * Both `store()` implementations resolve an existing row with a bare
 * `Model::find($this->listingId)` and then assign `user_id = Auth::id()`,
 * with no ownership check. Every test below therefore exercises the
 * **create** path only (`listingId === null`) as its own owner. There is no
 * assertion anywhere in this file that a non-owner may write to, or take
 * ownership of, another user's listing — recording that as "expected"
 * behaviour would bless the defect and make the fix look like a regression.
 * The re-save path is left uncovered on purpose, for the ownership fix to
 * cover with tests that assert refusal.
 *
 * SQLITE NOTE
 * -----------
 * `state` is set through the component's own `selectStateSuggestion()` — the
 * real autocomplete-selection path — rather than `->set('state', ...)`.
 * `->set()` fires `updatedState()`, which runs a `... ILIKE ...` lookup that
 * PostgreSQL accepts and SQLite rejects as a syntax error. That limitation is
 * pre-existing (it is what fails 7 tests in
 * tests/Feature/Offers/CreateEditParityRegressionTest.php) and is a property of
 * the harness, not of the flow under test.
 */
class SellerLandlordHireAgentCreatePublishCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    private const SELLER_COMPONENT   = \App\Http\Livewire\HireSellerAgent\SellerAgentAuction::class;
    private const LANDLORD_COMPONENT = \App\Http\Livewire\HireLandLordAgent\LandLordAgentAuction::class;

    private function owner(string $userType): User
    {
        return User::factory()->create(['user_type' => $userType]);
    }

    /** Minimum field set the Seller wizard accepts on full submit. */
    private function sellerComponentWithValidData()
    {
        return Livewire::test(self::SELLER_COMPONENT)
            ->set('listing_title', 'Characterization Seller Listing')
            ->set('property_type', 'Residential')
            // The street address is validated on full submit (ValidStreetAddress),
            // so the minimum accepted field set now includes a real street address.
            ->set('address', '123 Main St')
            ->call('selectStateSuggestion', 'Florida')
            ->set('first_name', 'Sam')
            ->set('last_name', 'Seller')
            ->set('phone_number', '8135550100')
            ->set('email', 'sam.seller@example.test')
            ->set('current_status', 'Ready to Sell')
            ->set('compatibility_preferences.seller_specific.communication_style', 'Email')
            ->set('compatibility_preferences.seller_specific.negotiation_style', 'Collaborative')
            ->set('compatibility_preferences.seller_specific.primary_transaction_goal', 'Highest Price')
            ->set('compatibility_preferences.seller_specific.representation_priorities', ['Market Knowledge'])
            ->set('compatibility_preferences.seller_specific.preferred_agent_working_style', 'Proactive');
    }

    /** Minimum field set the Landlord wizard accepts on full submit. */
    private function landlordComponentWithValidData()
    {
        return Livewire::test(self::LANDLORD_COMPONENT)
            ->set('listing_title', 'Characterization Landlord Listing')
            // The street address is validated on full submit (ValidStreetAddress),
            // so the minimum accepted field set now includes a real street address.
            ->set('address', '123 Main St')
            ->set('first_name', 'Lee')
            ->set('last_name', 'Landlord')
            ->set('phone_number', '8135550200')
            ->set('email', 'lee.landlord@example.test')
            ->set('desired_lease_length', ['12 Months'])
            ->set('compatibility_preferences.landlord_specific.communication_style', 'Phone')
            ->set('compatibility_preferences.landlord_specific.negotiation_style', 'Firm')
            ->set('compatibility_preferences.landlord_specific.primary_leasing_goal', 'Maximum Rent')
            ->set('compatibility_preferences.landlord_specific.representation_priorities', ['Tenant Screening'])
            ->set('compatibility_preferences.landlord_specific.preferred_agent_working_style', 'Hands On');
    }

    // ── Mount ────────────────────────────────────────────────────────────────

    public function test_seller_owner_can_mount_the_hire_agent_component(): void
    {
        $this->actingAs($this->owner('seller'));

        Livewire::test(self::SELLER_COMPONENT)
            ->assertSet('workflow_type', 'hire_agent')
            ->assertSet('user_type', 'seller')
            ->assertSet('service_type', 'full_service')
            ->assertSet('listingId', null)
            ->assertSet('isDraft', false);
    }

    public function test_landlord_owner_can_mount_the_hire_agent_component(): void
    {
        $this->actingAs($this->owner('landlord'));

        Livewire::test(self::LANDLORD_COMPONENT)
            ->assertSet('user_type', 'landlord')
            ->assertSet('service_type', 'full_service')
            ->assertSet('listingId', null)
            ->assertSet('isDraft', false);
    }

    // ── Publish: row is created, published, and owned by the submitter ───────

    public function test_seller_valid_submission_publishes_a_listing_owned_by_the_submitter(): void
    {
        $owner = $this->owner('seller');
        $this->actingAs($owner);

        $this->sellerComponentWithValidData()->call('store');

        $listing = SellerAgentAuction::where('title', 'Characterization Seller Listing')->first();

        $this->assertNotNull($listing, 'Seller full submit must create a listing row');
        $this->assertSame($owner->id, $listing->user_id, 'Listing must be owned by the authenticated submitter');
        $this->assertFalse((bool) $listing->is_draft, 'store() publishes — is_draft must be 0');
        $this->assertTrue((bool) $listing->is_approved, 'Seller store() marks the listing approved');
        $this->assertFalse((bool) $listing->is_sold);
    }

    public function test_landlord_valid_submission_publishes_a_listing_owned_by_the_submitter(): void
    {
        $owner = $this->owner('landlord');
        $this->actingAs($owner);

        $this->landlordComponentWithValidData()->call('store');

        $listing = LandlordAgentAuction::where('title', 'Characterization Landlord Listing')->first();

        $this->assertNotNull($listing, 'Landlord full submit must create a listing row');
        $this->assertSame($owner->id, $listing->user_id, 'Listing must be owned by the authenticated submitter');
        $this->assertFalse((bool) $listing->is_draft, 'store() publishes — is_draft must be 0');
    }

    // ── workflow_type stamp ─────────────────────────────────────────────────

    public function test_seller_submission_stamps_workflow_type_hire_agent(): void
    {
        $this->actingAs($this->owner('seller'));

        $this->sellerComponentWithValidData()->call('store');

        $listing = SellerAgentAuction::where('title', 'Characterization Seller Listing')->firstOrFail();

        $this->assertDatabaseHas('seller_agent_auction_metas', [
            'seller_agent_auction_id' => $listing->id,
            'meta_key'                => 'workflow_type',
            'meta_value'              => 'hire_agent',
        ]);
    }

    public function test_landlord_submission_stamps_workflow_type_hire_agent(): void
    {
        $this->actingAs($this->owner('landlord'));

        $this->landlordComponentWithValidData()->call('store');

        $listing = LandlordAgentAuction::where('title', 'Characterization Landlord Listing')->firstOrFail();

        $this->assertDatabaseHas('landlord_agent_auction_metas', [
            'landlord_agent_auction_id' => $listing->id,
            'meta_key'                  => 'workflow_type',
            'meta_value'                => 'hire_agent',
        ]);
    }

    // ── Role-specific metadata survives the write ───────────────────────────

    public function test_seller_role_specific_metadata_survives_the_write(): void
    {
        $this->actingAs($this->owner('seller'));

        $this->sellerComponentWithValidData()->call('store');

        $listing = SellerAgentAuction::where('title', 'Characterization Seller Listing')->firstOrFail();

        $expected = [
            'user_type'      => 'seller',
            'service_type'   => 'full_service',
            'property_type'  => 'Residential',
            'state'          => 'Florida',
            'current_status' => 'Ready to Sell',
            'first_name'     => 'Sam',
            'last_name'      => 'Seller',
            'email'          => 'sam.seller@example.test',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame(
                $value,
                $listing->info($key),
                "Seller meta '{$key}' must survive the write"
            );
        }
    }

    public function test_landlord_role_specific_metadata_survives_the_write(): void
    {
        $this->actingAs($this->owner('landlord'));

        $this->landlordComponentWithValidData()->call('store');

        $listing = LandlordAgentAuction::where('title', 'Characterization Landlord Listing')->firstOrFail();

        $expected = [
            'user_type'    => 'landlord',
            'service_type' => 'full_service',
            'first_name'   => 'Lee',
            'last_name'    => 'Landlord',
            'email'        => 'lee.landlord@example.test',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame(
                $value,
                $listing->info($key),
                "Landlord meta '{$key}' must survive the write"
            );
        }

        $this->assertSame(
            ['12 Months'],
            json_decode((string) $listing->info('desired_lease_length'), true),
            'Landlord desired_lease_length must round-trip as JSON'
        );
    }

    // ── Validation rejects a clearly incomplete submission ──────────────────

    public function test_seller_incomplete_submission_is_rejected_and_writes_nothing(): void
    {
        $this->actingAs($this->owner('seller'));

        $before = SellerAgentAuction::count();

        Livewire::test(self::SELLER_COMPONENT)
            ->set('listing_title', '')
            ->call('store')
            ->assertHasErrors([
                'listing_title' => 'required',
                'property_type' => 'required',
                'state'         => 'required',
                'first_name'    => 'required',
                'last_name'     => 'required',
                'phone_number'  => 'required',
                'email'         => 'required',
                'current_status' => 'required',
            ]);

        $this->assertSame($before, SellerAgentAuction::count(), 'A rejected seller submit must not create a row');
    }

    public function test_landlord_incomplete_submission_is_rejected_and_writes_nothing(): void
    {
        $this->actingAs($this->owner('landlord'));

        $before = LandlordAgentAuction::count();

        Livewire::test(self::LANDLORD_COMPONENT)
            ->set('listing_title', '')
            ->call('store')
            ->assertHasErrors([
                'first_name'           => 'required',
                'last_name'            => 'required',
                'phone_number'         => 'required',
                'email'                => 'required',
                'desired_lease_length' => 'required',
            ]);

        $this->assertSame($before, LandlordAgentAuction::count(), 'A rejected landlord submit must not create a row');
    }
}
