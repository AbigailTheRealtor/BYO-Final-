<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Models\BridgeProperty;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Master Phase 1 across all seven Create Offer property types.
 *
 * Bridge serves one Property resource for every property type, so there is one
 * normalized candidate and one role architecture behind all seven forms. What
 * differs is which inputs a given property type renders — and that difference is
 * the thing these tests are about.
 *
 * THE FAILURE MODE BEING GUARDED
 * ------------------------------
 * A Livewire property exists for every property type; the blade decides whether
 * the user ever sees an input for it. `property_exists()` cannot tell those
 * apart, so before MlsFieldMap::propertyTypeApplicability() existed the importer
 * would happily offer "Pool: No" while the user was creating a Vacant Land
 * listing, and applying it wrote a value into state that form never displays.
 * An import the user cannot see is an import the user cannot correct.
 *
 * The four Tax / Legal / HOA fields are the opposite case and are asserted as
 * such: their tab carries no property_type conditional at all, so they are
 * legitimate for every type and must appear for every type.
 */
class MlsPhaseOnePropertyTypeTest extends TestCase
{
    use DatabaseTransactions;

    private const MLS = 'PHPUNIT-PHASE1-TYPES-001';
    private const KEY = 'PHPUNIT-PHASE1-TYPES-KEY-1';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();

        config([
            'mls_direct_import.prefill_enabled' => true,
            'mls_direct_import.prefill_roles'   => ['seller', 'landlord'],
            'bridge.dataset'                    => 'phpunit_dataset',
            'bridge.token'                      => 'phpunit-token',
        ]);

        $this->user = User::factory()->create(['user_type' => 'seller']);
    }

    /**
     * One Bridge record carrying every Phase 1 fact, seeded into the local cache
     * so the real lookup path runs with no HTTP. Deliberately the same record for
     * all seven types — proving one candidate feeds every form.
     */
    private function seedBridgeRecord(): BridgeProperty
    {
        return BridgeProperty::create([
            'listing_key'             => self::KEY,
            'listing_id'              => self::MLS,
            'standard_status'         => 'Active',
            'mls_status'              => 'Active',
            'property_type'           => 'Residential',
            'property_sub_type'       => 'Single Family Residence',
            'list_price'              => 459000,
            'unparsed_address'        => '77 Phase One Way, Tampa, FL 33601',
            'city'                    => 'Tampa',
            'state_or_province'       => 'FL',
            'postal_code'             => '33601',
            'county_or_parish'        => 'Hillsborough',
            'bedrooms_total'          => 4,
            'bathrooms_total_integer' => 3,
            'living_area'             => 2450,
            'year_built'              => 1998,
            'latitude'                => 27.9506,
            'longitude'               => -82.4572,
            'tax_annual_amount'       => 5400.00,
            'association_yn'          => true,
            'association_fee'         => 250.00,
            'cdd_yn'                  => false,
            'pool_private_yn'         => true,
            'garage_yn'               => true,
            'waterfront_yn'           => false,
            'raw_json'                => json_encode([
                'ListingKey'        => self::KEY,
                'ListingId'         => self::MLS,
                'PublicRemarks'     => 'RESTRICTED_REMARKS lovely home',
                'ListAgentFullName' => 'RESTRICTED_AGENT Jane Agent',
                'ListOfficeName'    => 'RESTRICTED_BROKER Acme Realty',
                'Media'             => ['RESTRICTED_PHOTO https://cdn.example.com/a.jpg'],
            ]),
        ]);
    }

    /** @return array<string,string> canonical_key => value */
    private function previewFor(string $componentClass, string $propertyType): array
    {
        $component = Livewire::actingAs($this->user)
            ->test($componentClass)
            ->set('property_type', $propertyType)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $component->assertSet('importError', '');

        $out = [];
        foreach ($component->get('importPreviewData') as $row) {
            $out[$row['canonical_key']] = $row['value'];
        }

        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // The type-neutral four — every type, both roles
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider allSevenPropertyTypes
     */
    public function test_tax_hoa_and_cdd_facts_are_offered_for_every_property_type(
        string $role,
        string $componentClass,
        string $propertyType
    ): void {
        $this->seedBridgeRecord();

        $preview = $this->previewFor($componentClass, $propertyType);

        $this->assertSame('5400',  $preview['annual_taxes'] ?? null, "annual taxes missing for {$role}/{$propertyType}");
        $this->assertSame('Yes',   $preview['has_hoa'] ?? null,      "HOA missing for {$role}/{$propertyType}");
        $this->assertSame('250',   $preview['association_fee_amount'] ?? null, "fee missing for {$role}/{$propertyType}");
        $this->assertSame('No',    $preview['has_cdd'] ?? null,      "CDD missing for {$role}/{$propertyType}");
        $this->assertSame('No',    $preview['waterfront'] ?? null,   "waterfront missing for {$role}/{$propertyType}");
    }

    /**
     * The existing factual core must be unchanged for every type — this is an
     * additive expansion, and a regression here means Phase 1 broke what shipped.
     *
     * @dataProvider allSevenPropertyTypes
     */
    public function test_the_pre_existing_core_still_imports_for_every_property_type(
        string $role,
        string $componentClass,
        string $propertyType
    ): void {
        $this->seedBridgeRecord();

        $preview = $this->previewFor($componentClass, $propertyType);

        $this->assertSame('77 Phase One Way, Tampa, FL 33601', $preview['address'] ?? null);
        $this->assertSame('Tampa',        $preview['city'] ?? null);
        $this->assertSame('FL',           $preview['state'] ?? null);
        $this->assertSame('33601',        $preview['zip'] ?? null);
        $this->assertSame('Hillsborough', $preview['county'] ?? null);
        $this->assertSame('459000',       $preview['price'] ?? null);
        $this->assertSame('27.9506',      $preview['latitude'] ?? null);
        $this->assertSame('-82.4572',     $preview['longitude'] ?? null);
    }

    /**
     * No property type may bypass the facts-only boundary.
     *
     * @dataProvider allSevenPropertyTypes
     */
    public function test_restricted_content_never_reaches_any_property_type(
        string $role,
        string $componentClass,
        string $propertyType
    ): void {
        $this->seedBridgeRecord();

        $encoded = json_encode($this->previewFor($componentClass, $propertyType));

        foreach (['RESTRICTED_REMARKS', 'RESTRICTED_AGENT', 'RESTRICTED_BROKER', 'RESTRICTED_PHOTO'] as $marker) {
            $this->assertStringNotContainsString($marker, $encoded, "{$marker} leaked on {$role}/{$propertyType}");
        }
    }

    public static function allSevenPropertyTypes(): array
    {
        return [
            'Seller — Residential'          => ['seller', SellerOfferListing::class, 'Residential'],
            'Seller — Income'               => ['seller', SellerOfferListing::class, 'Income'],
            'Seller — Commercial Sale'      => ['seller', SellerOfferListing::class, 'Commercial'],
            'Seller — Business Opportunity' => ['seller', SellerOfferListing::class, 'Business'],
            'Seller — Vacant Land'          => ['seller', SellerOfferListing::class, 'Vacant Land'],
            'Landlord — Residential Rental' => ['landlord', LandlordOfferListing::class, 'Residential Property'],
            'Landlord — Commercial Lease'   => ['landlord', LandlordOfferListing::class, 'Commercial Property'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Type-gated: pool and garage
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider typesThatRenderPool
     */
    public function test_pool_is_offered_where_the_form_renders_it(string $componentClass, string $propertyType): void
    {
        $this->seedBridgeRecord();

        $preview = $this->previewFor($componentClass, $propertyType);

        $this->assertSame('Yes', $preview['pool'] ?? null, "pool should be offered for {$propertyType}");
    }

    public static function typesThatRenderPool(): array
    {
        return [
            'Seller Residential'  => [SellerOfferListing::class, 'Residential'],
            'Seller Income'       => [SellerOfferListing::class, 'Income'],
            'Landlord Residential' => [LandlordOfferListing::class, 'Residential Property'],
        ];
    }

    /**
     * @dataProvider typesThatHidePool
     */
    public function test_pool_is_withheld_where_the_form_never_shows_it(string $componentClass, string $propertyType): void
    {
        $this->seedBridgeRecord();

        $preview = $this->previewFor($componentClass, $propertyType);

        $this->assertArrayNotHasKey(
            'pool',
            $preview,
            "{$propertyType} renders no pool input, so offering one would write invisible state"
        );
    }

    public static function typesThatHidePool(): array
    {
        return [
            'Seller Commercial'  => [SellerOfferListing::class, 'Commercial'],
            'Seller Business'    => [SellerOfferListing::class, 'Business'],
            'Seller Vacant Land' => [SellerOfferListing::class, 'Vacant Land'],
            'Landlord Commercial' => [LandlordOfferListing::class, 'Commercial Property'],
        ];
    }

    public function test_garage_is_offered_only_for_residential(): void
    {
        $this->seedBridgeRecord();

        $this->assertSame(
            'Yes',
            $this->previewFor(SellerOfferListing::class, 'Residential')['garage'] ?? null
        );
        $this->assertSame(
            'Yes',
            $this->previewFor(LandlordOfferListing::class, 'Residential Property')['garage'] ?? null
        );
    }

    /**
     * @dataProvider typesThatHideGarage
     */
    public function test_garage_is_withheld_for_every_other_type(string $componentClass, string $propertyType): void
    {
        $this->seedBridgeRecord();

        $this->assertArrayNotHasKey('garage', $this->previewFor($componentClass, $propertyType));
    }

    public static function typesThatHideGarage(): array
    {
        return [
            'Seller Income'       => [SellerOfferListing::class, 'Income'],
            'Seller Commercial'   => [SellerOfferListing::class, 'Commercial'],
            'Seller Business'     => [SellerOfferListing::class, 'Business'],
            'Seller Vacant Land'  => [SellerOfferListing::class, 'Vacant Land'],
            'Landlord Commercial' => [LandlordOfferListing::class, 'Commercial Property'],
        ];
    }

    /**
     * Opening the modal before choosing a type is normal — plenty of users will
     * import first and classify second. An unset type must stay permissive, or
     * the import silently shrinks for exactly those users.
     */
    public function test_an_unset_property_type_withholds_nothing(): void
    {
        $this->seedBridgeRecord();

        $preview = $this->previewFor(SellerOfferListing::class, '');

        $this->assertSame('Yes', $preview['pool'] ?? null);
        $this->assertSame('Yes', $preview['garage'] ?? null);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Apply — the gate governs writes, not just display
    // ═══════════════════════════════════════════════════════════════════════

    public function test_applying_writes_the_phase_one_facts_to_the_seller_form(): void
    {
        $this->seedBridgeRecord();

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->set('property_type', 'Residential')
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber')
            ->call('applyImportedFields', [
                'annual_taxes', 'has_hoa', 'association_fee_amount', 'has_cdd', 'pool', 'garage', 'waterfront',
            ]);

        $component->assertSet('annual_property_taxes', '5400');
        $component->assertSet('has_hoa', 'Yes');
        $component->assertSet('association_fee_amount', '250');
        $component->assertSet('has_cdd', 'No');
        $component->assertSet('pool_needed', 'Yes');
        $component->assertSet('garage_needed', 'Yes');
        $component->assertSet('waterfront', 'No');
    }

    public function test_applying_writes_the_phase_one_facts_to_the_landlord_form(): void
    {
        $this->seedBridgeRecord();

        $component = Livewire::actingAs($this->user)
            ->test(LandlordOfferListing::class)
            ->set('property_type', 'Residential Property')
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber')
            ->call('applyImportedFields', [
                'annual_taxes', 'has_hoa', 'association_fee_amount', 'has_cdd', 'pool', 'garage', 'waterfront',
            ]);

        $component->assertSet('annual_property_taxes', '5400');
        $component->assertSet('has_hoa', 'Yes');
        $component->assertSet('association_fee_amount', '250');
        $component->assertSet('has_cdd', 'No');
        $component->assertSet('pool_needed', 'Yes');
        $component->assertSet('garage_needed', 'Yes');
        $component->assertSet('waterfront', 'No');
    }

    /**
     * The gate must hold on the write path too. A hand-crafted Livewire call
     * naming a key the preview withheld must not be able to set it — the preview
     * is the authority, and applyImportedFields only honours what it staged.
     */
    public function test_a_withheld_field_cannot_be_applied_by_naming_it(): void
    {
        $this->seedBridgeRecord();

        $component = Livewire::actingAs($this->user)
            ->test(SellerOfferListing::class)
            ->set('property_type', 'Vacant Land')
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber')
            ->call('applyImportedFields', ['pool', 'garage', 'annual_taxes']);

        $component->assertSet('pool_needed', '');
        $component->assertSet('garage_needed', '');
        // …while the legitimate field on the same call still lands.
        $component->assertSet('annual_property_taxes', '5400');
    }

    /**
     * Pets is normalized faithfully now, but is NOT mapped: pet_policy has no
     * wire:model binding on any Create Offer tab. If someone adds it to the
     * allow-list without wiring the form, this fails.
     */
    public function test_pets_are_not_imported_while_pet_policy_has_no_form_binding(): void
    {
        $this->seedBridgeRecord();

        $preview = $this->previewFor(LandlordOfferListing::class, 'Residential Property');

        $this->assertArrayNotHasKey('pets_allowed', $preview);
    }
}
