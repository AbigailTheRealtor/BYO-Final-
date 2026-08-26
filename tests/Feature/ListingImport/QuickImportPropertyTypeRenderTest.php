<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\User;
use App\Support\Listing\PropertyTypeVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The imported property type must be BYO vocabulary by the time a Blade sees it.
 *
 * THE DEFECT THIS CLOSES
 * ----------------------
 * The canonical Landlord Leasing Terms partial gates its conditional sections on
 * an EXACT match: `$property_type === 'Commercial Property'` (40 fields) or
 * `=== 'Residential Property'` (5 fields). MLS Quick Import carried the feed's
 * own wording — "Residential Lease", "Commercial Lease" — which matches neither,
 * so those sections never rendered. The properties existed on the component the
 * whole time, which is exactly why the earlier parity tests passed: they proved
 * the FIELDS existed, not that the SECTIONS rendered.
 *
 * Every test here therefore asserts against rendered output. A test that only
 * checked `property_exists()` would pass again on the day this breaks again.
 */
class QuickImportPropertyTypeRenderTest extends TestCase
{
    use DatabaseTransactions;

    private User $seller;
    private User $landlord;

    /**
     * Fields ABSENT with the raw RESO value and PRESENT once it is normalised,
     * for a residential lease.
     *
     * Measured against real rendered output, not read off the Blade: several
     * sections sit behind a SECOND gate (the landlord's `leasing_spaces` choice,
     * a storage selection), so a statically-derived list is wrong in both
     * directions.
     *
     * @var list<string>
     */
    private const RESIDENTIAL_ONLY = [
        'leasing_space_details',
        'other_rent_include',
        'utilities',
    ];

    /**
     * The same for a commercial lease — the bulk of what the defect hid:
     * owner-pays, tenant-pays, terms of lease, rent escalation, CAM/NNN,
     * commercial lease type, permitted use, signage, personal guarantee and
     * tenant-improvement terms.
     *
     * @var list<string>
     */
    private const COMMERCIAL_ONLY = [
        'restrictions',
        'rent_escalation_terms',
        'owner_pays',
        'tenant_pays',
        'terms_of_lease',
        'cam_nnn_additional_rent_charges',
        'commercial_lease_type',
        'permitted_use_restrictions',
        'signage_rights',
        'custom_lease_term',
        'other_owner_pays',
        'personal_guarantee_requirement',
        'tenant_improvement_buildout_terms',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'bridge.dataset'                         => 'phpunit_dataset',
            'bridge.token'                           => 'phpunit-token',
        ]);

        $this->seller   = User::factory()->create(['user_type' => 'seller']);
        $this->landlord = User::factory()->create(['user_type' => 'landlord']);
    }

    private function seedRecord(string $mls, string $propertyType): void
    {
        BridgeProperty::create([
            'listing_key'       => $mls . '-KEY',
            'listing_id'        => $mls,
            'standard_status'   => 'Active',
            'mls_status'        => 'Active',
            'property_type'     => $propertyType,
            'list_price'        => 3200,
            'unparsed_address'  => '1 Test Street',
            'city'              => 'TAMPA',
            'state_or_province' => 'FL',
            'postal_code'       => '33601',
            'raw_json'          => json_encode([
                'ListingKey'      => $mls . '-KEY',
                'ListingId'       => $mls,
                'PropertyType'    => $propertyType,
                'UnparsedAddress' => '1 Test Street',
            ]),
        ]);
    }

    /** Drive a landlord quick import to the terms step and return the component. */
    private function landlordToTerms(string $mls, ?string $forceType = null): \Livewire\Testing\TestableLivewire
    {
        // $forceType REPRODUCES the defect: it puts the raw RESO wording back on
        // the component, so the "before" half of a rescue assertion is measured
        // rather than assumed.
        $component = Livewire::actingAs($this->landlord)
            ->test(LandlordMlsQuickImport::class)
            ->set('mlsNumber', $mls)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional');

        if ($forceType !== null) {
            $component->set('property_type', $forceType);
        }

        return $component->call('continueToTerms');
    }

    // ─── Residential ─────────────────────────────────────────────────────────

    /**
     * @test
     *
     * A Residential Lease record must reach the Blade as 'Residential Property'.
     */
    public function a_residential_lease_becomes_residential_property(): void
    {
        $this->seedRecord('QI-RES-1', 'Residential Lease');

        $this->landlordToTerms('QI-RES-1')
            ->assertSet('step', 'terms')
            ->assertSet('property_type', 'Residential Property');
    }

    /**
     * @test
     *
     * …and the residential-only sections actually RENDER. This is the assertion
     * the previous suite was missing.
     */
    public function the_residential_only_sections_render_after_normalisation(): void
    {
        $this->seedRecord('QI-RES-2', 'Residential Lease');

        $this->landlordToTerms('QI-RES-2')->assertSeeHtml(self::RESIDENTIAL_ONLY[0]);

        $after = $this->landlordToTerms('QI-RES-2');

        foreach (self::RESIDENTIAL_ONLY as $field) {
            $after->assertSeeHtml($field);
        }

        // …and each is genuinely rescued BY the normalisation: put the raw RESO
        // value back and every one disappears again. Without this half the
        // assertions above would still pass on a partial with no conditionals.
        $before = $this->landlordToTerms('QI-RES-2', 'Residential Lease');

        foreach (self::RESIDENTIAL_ONLY as $field) {
            $before->assertDontSeeHtml($field);
        }
    }

    /**
     * @test
     *
     * THE REGRESSION GUARD. If the raw RESO value is ever carried into the
     * component again, this fails — which is precisely how the defect escaped.
     */
    public function the_raw_reso_property_type_never_reaches_the_component(): void
    {
        foreach (['Residential Lease', 'Commercial Lease', 'Residential Income'] as $i => $resoType) {
            $mls = 'QI-RAW-' . $i;
            $this->seedRecord($mls, $resoType);

            $held = $this->landlordToTerms($mls)->get('property_type');

            $this->assertNotSame(
                $resoType,
                $held,
                "Quick Import carried the raw RESO value '{$resoType}' into the component. "
                . 'The canonical Leasing Terms partial compares it for exact equality against '
                . "'Residential Property' / 'Commercial Property', so this silently hides "
                . 'whole conditional sections.'
            );

            $this->assertContains($held, ['Residential Property', 'Commercial Property']);
        }
    }

    // ─── Commercial ──────────────────────────────────────────────────────────

    /**
     * @test
     *
     * A Commercial Lease record must reach the Blade as 'Commercial Property'.
     */
    public function a_commercial_lease_becomes_commercial_property(): void
    {
        $this->seedRecord('QI-COM-1', 'Commercial Lease');

        $this->landlordToTerms('QI-COM-1')
            ->assertSet('step', 'terms')
            ->assertSet('property_type', 'Commercial Property');
    }

    /**
     * @test
     *
     * …and the commercial-only sections render. These forty fields — utilities,
     * maintenance responsibility and response time, rent escalation, owner-pays,
     * terms of lease, CAM/NNN, commercial lease type, 24/7 access, storage,
     * permitted use, signage — were the bulk of what the defect hid.
     */
    public function the_commercial_only_sections_render_after_normalisation(): void
    {
        $this->seedRecord('QI-COM-2', 'Commercial Lease');

        $after = $this->landlordToTerms('QI-COM-2');

        foreach (self::COMMERCIAL_ONLY as $field) {
            $after->assertSeeHtml($field);
        }

        $before = $this->landlordToTerms('QI-COM-2', 'Commercial Lease');

        foreach (self::COMMERCIAL_ONLY as $field) {
            $before->assertDontSeeHtml($field);
        }
    }

    /**
     * @test
     *
     * The gate is real in both directions: a residential import must NOT show
     * commercial lease terms. Without this, a test that simply asserted
     * "everything renders" would pass on a partial with no conditionals at all.
     */
    public function a_residential_import_does_not_show_commercial_lease_terms(): void
    {
        $this->seedRecord('QI-RES-3', 'Residential Lease');

        $component = $this->landlordToTerms('QI-RES-3');

        foreach (['cam_nnn_additional_rent_charges', 'commercial_lease_type', 'tenant_improvement_buildout_terms'] as $field) {
            $component->assertDontSeeHtml($field);
        }
    }

    // ─── Seller ──────────────────────────────────────────────────────────────

    /**
     * @test
     *
     * Seller uses the short form, and its partial gates on 'Vacant Land'.
     */
    public function a_seller_import_is_normalised_to_the_short_form(): void
    {
        $this->seedRecord('QI-SELL-1', 'Residential');

        Livewire::actingAs($this->seller)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', 'QI-SELL-1')
            ->call('findListing')
            ->call('acceptProperty')
            ->assertSet('property_type', 'Residential');
    }

    // ─── Storage + provenance ────────────────────────────────────────────────

    /**
     * @test
     *
     * The listing STORES the BYO value, so Edit and the published page drive the
     * same conditionals — while the feed's own wording survives as provenance.
     */
    public function the_stored_value_is_canonical_and_the_source_is_preserved(): void
    {
        $this->seedRecord('QI-PROV-1', 'Residential Lease');

        $component = $this->landlordToTerms('QI-PROV-1');
        $meta      = LandlordAgentAuction::find($component->get('listingId'))->get;

        $this->assertSame('Residential Property', (string) $meta->property_type);
        $this->assertSame('Residential Lease', (string) $meta->mls_source_property_type);
    }

    // ─── The vocabulary itself ───────────────────────────────────────────────

    /**
     * @test
     *
     * Idempotent: a value already in BYO vocabulary survives a second pass. A
     * resumed draft is normalised again, and must not drift.
     */
    public function normalisation_is_idempotent(): void
    {
        foreach ([
            ['Residential Property', 'landlord'],
            ['Commercial Property', 'landlord'],
            ['Residential', 'seller'],
            ['Vacant Land', 'seller'],
            ['Business', 'seller'],
        ] as [$value, $role]) {
            $this->assertSame($value, PropertyTypeVocabulary::forRole($value, $role));
        }
    }

    /** @test */
    public function the_vocabulary_translates_the_known_reso_forms(): void
    {
        foreach ([
            ['Residential Lease', 'landlord', 'Residential Property'],
            ['Commercial Lease',  'landlord', 'Commercial Property'],
            ['Commercial Sale',   'landlord', 'Commercial Property'],
            ['Residential',       'seller',   'Residential'],
            ['Commercial Sale',   'seller',   'Commercial'],
            ['Business Opportunity', 'seller', 'Business'],
            ['Residential Income', 'seller',  'Residential'],
            ['Vacant Land',       'seller',   'Vacant Land'],
        ] as [$in, $role, $expected]) {
            $this->assertSame($expected, PropertyTypeVocabulary::forRole($in, $role), "{$in} / {$role}");
        }
    }

    /** @test */
    public function an_unrecognised_property_type_passes_through_untouched(): void
    {
        $this->assertSame('Houseboat', PropertyTypeVocabulary::forRole('Houseboat', 'landlord'));
    }
}
