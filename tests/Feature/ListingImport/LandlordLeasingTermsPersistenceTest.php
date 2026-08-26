<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListingEdit;
use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Canonical Landlord Leasing Terms survive a save on every flow that offers them.
 *
 * Unlike Seller, this suite is not closing a data-loss bug — Landlord Create and
 * Edit were measured before the extraction and agreed completely. It exists to
 * hold that agreement in place now that all three flows write through one shared
 * routine, and to prove empirically what the regex-based audit could only
 * suggest.
 */
class LandlordLeasingTermsPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    /** @var array<string, string> */
    private const ANSWERS = [
        // ── Rent & core lease ───────────────────────────────────────────────
        'desired_rental_amount'   => '2,400',
        'lease_amount_frequency'  => 'Monthly',
        'utilities'               => 'Included in Rent',
        'leasing_spaces'          => 'Entire Property',
        'lease_available_date'    => '2027-01-15',
        'available_date'          => '2027-01-15',
        'custom_lease_term'       => '18 months',
        'other_lease_term'        => 'Renewable annually',

        // ── Deposits & move-in ──────────────────────────────────────────────
        'security_deposit_amount'      => '4800',
        'last_month_rent_required'     => 'Yes',
        'total_move_in_funds_required' => '9,600',

        // ── Renewal / escalation ────────────────────────────────────────────
        'renewal_option_offered' => 'Yes',
        'renewal_option_details' => 'One 12-month renewal at CPI',
        'rent_escalation_terms'  => '3% annually',

        // ── Policies ────────────────────────────────────────────────────────
        'smoking_policy'    => 'No Smoking',
        'subletting_policy' => 'Not Permitted',
        'guests_allowed'    => 'Yes',
        'restrictions'      => 'No commercial vehicles',

        // ── Occupancy ───────────────────────────────────────────────────────
        'occupant_status' => 'Tenant',
        'occupant_tenant' => 'Lease expires March 2027',

        // ── Maintenance & utilities ─────────────────────────────────────────
        'maintenance_by'                => 'Landlord',
        'll_maintenance_responsibility' => 'Landlord handles structural',
        'maintenance_response_time'     => '48 hours',
        'other_owner_pays'              => 'Roof repairs',
        'other_tenant_pays'             => 'Internet',
        'other_rent_include'            => 'Lawn care',

        // ── Pet fee (canonicalised against pet_fee_type) ────────────────────
        'pet_fee_type'   => 'Monthly Pet Fee',
        'pet_fee_amount' => '350',

        // ── Commercial ──────────────────────────────────────────────────────
        'commercial_lease_type'             => 'NNN',
        'commercial_lease_type_other'       => 'Modified gross',
        'cam_nnn_additional_rent_charges'   => '4.50',
        'commercial_parking_terms'          => '4 reserved spaces',
        'permitted_use_restrictions'        => 'Retail only',
        'personal_guarantee_requirement'    => 'Required',
        'tenant_improvement_buildout_terms' => 'Landlord contributes $20/sqft',
        'signage_rights'                    => 'Facade signage permitted',
        'zoning_allows'                     => 'C-2',
        'building_hours'                    => '7am-9pm',
        'common_areas_access'               => 'Shared lobby',
        'common_areas_cleaning'             => 'Landlord',
        'neighboring_tenants'               => 'Cafe and pharmacy',
        'bathroom_facilities'               => 'Private',
        'room_size'                         => '1,200 sqft',
        'space_features'                    => 'Corner unit',
        'shared_amenities'                  => 'Conference room',
        'commercial_approval_conditions'    => 'Board approval',
        'access_24_7'                       => 'Yes',

        // ── Storage ─────────────────────────────────────────────────────────
        'storage_space_res_single'          => 'Yes',
        'included_storage_space_res_single' => 'Included',
        'storage_space_res_both'            => 'Yes',
        'included_storage_space_res_both'   => 'Included',
        'storage_space_com_single'          => 'Yes',
        'included_storage_space_com_single' => 'Included',
        'storage_space_com_entire'          => 'Yes',
        'included_storage_space_com_entire' => 'Included',

        // ── Bidding-period rent pricing ─────────────────────────────────────
        'starting_rent'   => '2,200',
        'reserve_rent'    => '2,300',
        'lease_now_price' => '2,900',

        // ── Free text ───────────────────────────────────────────────────────
        'additional_landlord_lease_terms' => 'Landlord prefers a January start',
    ];

    /**
     * The five money fields, whose commas are stripped on the way in.
     *
     * security_deposit_amount is deliberately NOT here: the canonical
     * implementation stores it raw. That is arguably inconsistent for a currency
     * field, but Create and Edit agree on it, so there is no drift to repair and
     * changing it would alter manual behaviour. Recorded, not "fixed".
     */
    private const EXPECTED_MONEY = [
        'desired_rental_amount'        => '2400',
        'total_move_in_funds_required' => '9600',
        'starting_rent'                => '2200',
        'reserve_rent'                 => '2300',
        'lease_now_price'              => '2900',
    ];

    /**
     * The only two multi-selects on the landlord surface, stored as JSON.
     *
     * utilities and leasing_spaces read like multi-selects but the canonical tab
     * binds each to a single <select> and stores it raw — checked rather than
     * assumed, and left exactly as the canonical implementation has it.
     */
    private const ARRAY_ANSWERS = [
        'terms_of_lease' => ['1 Year'],
        'owner_pays'     => ['Taxes', 'Insurance'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['user_type' => 'landlord']);
    }

    private function makeAuction(array $meta = []): LandlordAgentAuction
    {
        $auction = LandlordAgentAuction::create([
            'user_id'     => $this->user->id,
            'title'       => 'Leasing Terms Persistence',
            'is_draft'    => true,
            'is_approved' => false,
        ]);

        LandlordAgentAuctionMeta::create([
            'landlord_agent_auction_id' => $auction->id,
            'meta_key'                  => 'workflow_type',
            'meta_value'                => 'offer_listing',
        ]);

        foreach ($meta as $key => $value) {
            LandlordAgentAuctionMeta::create([
                'landlord_agent_auction_id' => $auction->id,
                'meta_key'                  => $key,
                'meta_value'                => (string) $value,
            ]);
        }

        return $auction;
    }

    private function saveThrough(string $componentClass, LandlordAgentAuction $auction): void
    {
        $test = Livewire::actingAs($this->user)->test($componentClass);

        foreach (self::ANSWERS as $field => $value) {
            $test->set($field, $value);
        }

        foreach (self::ARRAY_ANSWERS as $field => $value) {
            $test->set($field, $value);
        }

        $component = $test->instance();
        $save      = new ReflectionMethod($component, 'saveAllMetadata');
        $save->setAccessible(true);
        $save->invoke($component, $auction);
    }

    private function assertEverythingStored(LandlordAgentAuction $auction, string $context): void
    {
        $meta = $auction->fresh()->get;

        foreach (self::ANSWERS as $field => $value) {
            $expected = self::EXPECTED_MONEY[$field] ?? $value;

            $this->assertSame(
                $expected,
                (string) ($meta->{$field} ?? ''),
                "{$context}: canonical Leasing Term '{$field}' did not survive the save."
            );
        }

        foreach (self::ARRAY_ANSWERS as $field => $value) {
            $this->assertSame(
                $value,
                $this->asList($meta->{$field} ?? null),
                "{$context}: multi-select '{$field}' did not survive the save."
            );
        }
    }

    /** @return list<string> */
    private function asList(mixed $stored): array
    {
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return array_values((array) ($stored ?? []));
    }

    /** @test */
    public function manual_create_persists_every_canonical_leasing_term(): void
    {
        $auction = $this->makeAuction();
        $this->saveThrough(LandlordOfferListing::class, $auction);
        $this->assertEverythingStored($auction, 'manual Create');
    }

    /** @test */
    public function manual_edit_persists_every_canonical_leasing_term(): void
    {
        $auction = $this->makeAuction();
        $this->saveThrough(LandlordOfferListingEdit::class, $auction);
        $this->assertEverythingStored($auction, 'manual Edit');
    }

    /**
     * @test
     *
     * Create and Edit produce identical meta from identical answers — the parity
     * claim stated as an experiment rather than an assertion about source code.
     */
    public function create_and_edit_store_identical_values(): void
    {
        $viaCreate = $this->makeAuction();
        $viaEdit   = $this->makeAuction();

        $this->saveThrough(LandlordOfferListing::class, $viaCreate);
        $this->saveThrough(LandlordOfferListingEdit::class, $viaEdit);

        $create = $viaCreate->fresh()->get;
        $edit   = $viaEdit->fresh()->get;

        foreach (LandlordOfferListing::landlordLeasingTermsFields() as $field) {
            $a = $create->{$field} ?? '';
            $b = $edit->{$field} ?? '';

            if (is_array($a) || is_array($b)) {
                $this->assertSame($this->asList($a), $this->asList($b), "Create and Edit disagree about '{$field}'.");

                continue;
            }

            $this->assertSame((string) $a, (string) $b, "Create and Edit disagree about '{$field}'.");
        }
    }

    /**
     * @test
     *
     * The pet fee pair is derived from pet_fee_type rather than stored raw, so a
     * "None" selection cannot leave a contradictory figure behind. This is the
     * one non-trivial transform on the landlord surface and it must survive the
     * move into the shared routine.
     */
    public function the_pet_fee_pair_is_canonicalised_not_stored_raw(): void
    {
        $auction = $this->makeAuction();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->set('pet_fee_type', 'No Pet Fee')
            ->set('pet_fee_amount', '350')
            ->set('pet_fee_other', 'ignored')
            ->instance();

        $save = new ReflectionMethod($component, 'saveAllMetadata');
        $save->setAccessible(true);
        $save->invoke($component, $auction);

        $meta = $auction->fresh()->get;

        $this->assertSame('No Pet Fee', (string) $meta->pet_fee_type);
        $this->assertSame('', (string) ($meta->pet_fee_amount ?? ''), 'A "No Pet Fee" selection must not keep an amount.');
        $this->assertSame('', (string) ($meta->pet_fee_other ?? ''));
    }

    /**
     * @test
     *
     * Resuming a draft rehydrates the terms, and resaving does not blank them.
     * This is the delayed data loss Seller suffered, checked for here even though
     * the audit found no gap — a passing test is the proof, not the audit.
     */
    public function resuming_and_resaving_a_draft_does_not_blank_the_terms(): void
    {
        $seed = [
            'smoking_policy'                  => 'No Smoking',
            'subletting_policy'               => 'Not Permitted',
            'maintenance_response_time'       => '48 hours',
            'rent_escalation_terms'           => '3% annually',
            'commercial_lease_type'           => 'NNN',
            'access_24_7'                     => 'Yes',
            'additional_landlord_lease_terms' => 'Landlord prefers a January start',
            'utilities'                       => 'Included in Rent',
            'owner_pays'                      => json_encode(['Taxes']),
            'terms_of_lease'                  => json_encode(['1 Year']),
        ];

        $auction = $this->makeAuction($seed);

        $test = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        // Rehydrated onto the form…
        $test->assertSet('smoking_policy', 'No Smoking')
            ->assertSet('subletting_policy', 'Not Permitted')
            ->assertSet('maintenance_response_time', '48 hours')
            ->assertSet('access_24_7', 'Yes');

        $this->assertSame('Included in Rent', $test->get('utilities'));
        $this->assertSame(['Taxes'], $test->get('owner_pays'));
        $this->assertSame(['1 Year'], $test->get('terms_of_lease'));

        // …and a save that touches nothing keeps them.
        $component = $test->instance();
        $save      = new ReflectionMethod($component, 'saveAllMetadata');
        $save->setAccessible(true);
        $save->invoke($component, $auction);

        $meta = $auction->fresh()->get;

        $this->assertSame('No Smoking', (string) $meta->smoking_policy);
        $this->assertSame('Not Permitted', (string) $meta->subletting_policy);
        $this->assertSame('48 hours', (string) $meta->maintenance_response_time);
        $this->assertSame('Yes', (string) $meta->access_24_7);
        $this->assertSame('Included in Rent', (string) $meta->utilities);
        $this->assertSame(['Taxes'], $this->asList($meta->owner_pays));
        $this->assertSame(['1 Year'], $this->asList($meta->terms_of_lease));
    }

    /**
     * @test
     *
     * A record predating the extraction still loads: partial meta, no invented
     * values, no failure.
     */
    public function a_record_predating_the_extraction_still_loads(): void
    {
        $auction = $this->makeAuction([
            'desired_rental_amount' => '2400',
            'smoking_policy'        => 'No Smoking',
        ]);

        $test = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->call('loadDraft', $auction->id);

        $test->assertSet('desired_rental_amount', '2400')
            ->assertSet('smoking_policy', 'No Smoking');

        // Never-stored fields come back empty and are not invented. Asserted as
        // "empty" rather than strictly '' because the canonical hydration writes
        // null for an absent meta row while the declared default is '' — both are
        // falsy, both render as an empty control, and normalising that would be a
        // change to manual behaviour rather than a test fix.
        $this->assertEmpty($test->get('rent_escalation_terms'));
        $this->assertEmpty($test->get('commercial_lease_type'));
        $this->assertEmpty($test->get('utilities'));
        $this->assertSame([], $test->get('owner_pays'));
    }
}
