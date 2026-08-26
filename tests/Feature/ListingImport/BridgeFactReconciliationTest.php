<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\MlsFieldMap;
use App\Services\ListingImport\MlsListingPrefillService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bridge facts with a canonical destination actually reach that destination.
 *
 * WHAT THIS CLOSES
 * ----------------
 * The field matrix for TB8528949 found twenty populated Bridge fields in the
 * state "a BidYourOffer field exists, an MlsFieldMap target exists, and the
 * value is fetched and then discarded". The block was in two places at once:
 * PropertyCandidate carried no property for them, so even permitting the key
 * would have read null.
 *
 * These tests drive the real quick-import flow against a seeded Bridge record
 * and read the real listing meta, so they fail if any link in that chain —
 * adapter, allow-list, field map, draft writer — stops carrying a fact.
 *
 * WHAT IS DELIBERATELY NOT TESTED HERE
 * ------------------------------------
 * The licensing boundary itself. MlsListingPrefillServiceTest pins the
 * allow-list's exact contents and the restricted-field exclusions; this file
 * assumes that boundary and tests what happens inside it.
 */
class BridgeFactReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY = 'PHPUNIT-RECON-KEY';
    private const MLS = 'PHPUNIT-RECON-MLS';

    private User $seller;
    private User $landlord;

    /**
     * The RESO fields this reconciliation added, and the value each carries on
     * the seeded record.
     *
     * @var array<string, mixed>
     */
    private const BRIDGE_FACTS = [
        'Appliances'            => ['Dryer', 'Microwave', 'Range', 'Refrigerator', 'Washer'],
        'ConstructionMaterials' => ['Block', 'Stucco'],
        'Cooling'               => ['Mini-Split Unit(s)'],
        'Heating'               => ['Central', 'Other'],
        'FoundationDetails'     => ['Slab'],
        'InteriorFeatures'      => ['Cathedral Ceiling(s)', 'Open Floorplan'],
        'Roof'                  => ['Shingle'],
        'Sewer'                 => ['Public Sewer'],
        'Utilities'             => ['Cable Available', 'Public'],
        'WaterSource'           => ['Public'],
        'WaterfrontFeatures'    => ['Pond'],
        'ParcelNumber'          => '322916106750020308',
        'TaxLegalDescription'   => 'BRADFORD ACRES CONDO PHASE II BLDG 3, UNIT 308',
        'TaxYear'               => '2025',
        'BuildingAreaTotal'     => 480,
        'STELLAR_FloodZoneCode' => 'X',
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

    private function seedRecord(array $rawOverrides = [], string $propertyType = 'Residential'): BridgeProperty
    {
        $raw = array_merge([
            'ListingKey'     => self::KEY,
            'ListingId'      => self::MLS,
            'PropertyType'   => $propertyType,
            'UnparsedAddress' => '2142 BRADFORD STREET UNIT 308',

            // Restricted content rides along on every record and must never land.
            'PublicRemarks'     => 'RESTRICTED_PUBLIC_REMARKS charming condo',
            'PrivateRemarks'    => 'RESTRICTED_PRIVATE_REMARKS gate code 4455',
            'ListAgentFullName' => 'RESTRICTED_AGENT Jane Agent',
            'ListOfficeName'    => 'RESTRICTED_BROKER Acme Realty',
        ], self::BRIDGE_FACTS, $rawOverrides);

        return BridgeProperty::create([
            'listing_key'      => self::KEY,
            'listing_id'       => self::MLS,
            'standard_status'  => 'Active',
            'mls_status'       => 'Active',
            'property_type'    => $propertyType,
            'list_price'       => 100000,
            'unparsed_address' => '2142 BRADFORD STREET UNIT 308',
            'city'             => 'CLEARWATER',
            'state_or_province' => 'FL',
            'postal_code'      => '33760',
            'raw_json'         => json_encode($raw),
        ]);
    }

    private function importAs(User $user, string $component): object
    {
        $test = Livewire::actingAs($user)
            ->test($component)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty');

        return (object) ['test' => $test, 'listingId' => $test->get('listingId')];
    }

    /**
     * @test
     *
     * Seller: every reconciled fact lands in its canonical meta key.
     */
    public function seller_quick_import_populates_the_reconciled_canonical_facts(): void
    {
        $this->seedRecord();

        $r    = $this->importAs($this->seller, SellerMlsQuickImport::class);
        $meta = SellerAgentAuction::find($r->listingId)->get;

        // Multi-selects arrive as arrays, deterministically ordered as the feed
        // sent them.
        $this->assertSame(['Dryer', 'Microwave', 'Range', 'Refrigerator', 'Washer'], $this->asList($meta->appliances));
        $this->assertSame(['Block', 'Stucco'], $this->asList($meta->exterior_construction));
        $this->assertSame(['Mini-Split Unit(s)'], $this->asList($meta->air_conditioning));
        $this->assertSame(['Central', 'Other'], $this->asList($meta->heating_and_fuel));
        $this->assertSame(['Slab'], $this->asList($meta->foundation));
        $this->assertSame(['Cathedral Ceiling(s)', 'Open Floorplan'], $this->asList($meta->interior_features));
        $this->assertSame(['Shingle'], $this->asList($meta->roof_type));
        $this->assertSame(['Public Sewer'], $this->asList($meta->sewer));
        $this->assertSame(['Cable Available', 'Public'], $this->asList($meta->utilities));
        $this->assertSame(['Public'], $this->asList($meta->water));
        $this->assertSame(['Pond'], $this->asList($meta->water_access));

        // Scalars.
        $this->assertSame('322916106750020308', (string) $meta->parcel_id);
        $this->assertSame('BRADFORD ACRES CONDO PHASE II BLDG 3, UNIT 308', (string) $meta->legal_description);
        $this->assertSame('2025', (string) $meta->tax_year);
        $this->assertSame('X', (string) $meta->flood_zone_code);

        // Seller-only target.
        $this->assertSame('480', (string) $meta->total_square_feet);
    }

    /**
     * @test
     *
     * Landlord: the same facts land, through the landlord's own map. Proves the
     * reconciliation is not Seller-shaped.
     */
    public function landlord_quick_import_populates_the_reconciled_canonical_facts(): void
    {
        $this->seedRecord(propertyType: 'Residential Lease');

        $r    = $this->importAs($this->landlord, LandlordMlsQuickImport::class);
        $meta = LandlordAgentAuction::find($r->listingId)->get;

        $this->assertSame(['Dryer', 'Microwave', 'Range', 'Refrigerator', 'Washer'], $this->asList($meta->appliances));
        $this->assertSame(['Block', 'Stucco'], $this->asList($meta->exterior_construction));
        $this->assertSame(['Shingle'], $this->asList($meta->roof_type));
        $this->assertSame(['Public Sewer'], $this->asList($meta->sewer));
        $this->assertSame(['Public'], $this->asList($meta->water));

        // The landlord map routes utilities to its own property.
        $this->assertSame(['Cable Available', 'Public'], $this->asList($meta->property_utilities));

        $this->assertSame('322916106750020308', (string) $meta->parcel_id);
        $this->assertSame('2025', (string) $meta->tax_year);
        $this->assertSame('X', (string) $meta->flood_zone_code);
    }

    /**
     * @test
     *
     * ROLE APPLICABILITY. `building_size_sqft` has a Seller target and no
     * Landlord one, so the landlord listing must simply not receive it — the
     * draft writer skips a canonical key the role's map does not contain. The
     * role split is enforced by the map, not duplicated in the allow-list.
     */
    public function a_seller_only_target_is_not_written_to_a_landlord_listing(): void
    {
        $this->assertArrayHasKey('building_size_sqft', MlsFieldMap::forRole('seller'));
        $this->assertArrayNotHasKey('building_size_sqft', MlsFieldMap::forRole('landlord'));

        $this->seedRecord(propertyType: 'Residential Lease');

        $r    = $this->importAs($this->landlord, LandlordMlsQuickImport::class);
        $meta = LandlordAgentAuction::find($r->listingId)->get;

        $this->assertEmpty($meta->total_square_feet ?? '');
    }

    /**
     * @test
     *
     * THE TERMS BOUNDARY. Sale Terms and Leasing Terms are the user's statement
     * of how they intend to transact. An MLS fact must not silently answer one.
     */
    public function mls_facts_do_not_populate_the_canonical_terms_surfaces(): void
    {
        $this->seedRecord(['OccupantType' => 'Tenant']);

        $r    = $this->importAs($this->seller, SellerMlsQuickImport::class);
        $meta = SellerAgentAuction::find($r->listingId)->get;

        // OccupantType is a real, populated MLS fact and occupant_status is a
        // real canonical field — but it belongs to the Sale Terms tab, so the
        // import leaves it for the seller to answer.
        $this->assertEmpty($meta->occupant_status ?? '');

        // No sale term is touched by the import at all.
        foreach ([
            'offered_financing', 'initial_deposit_requested', 'possession_preference',
            'inspection_contingency_preference', 'included_personal_property',
            'additional_seller_sale_terms', 'payment_interest_rate',
        ] as $term) {
            $this->assertEmpty($meta->{$term} ?? '', "MLS import wrote the sale term {$term}.");
        }
    }

    /** @test */
    public function mls_facts_do_not_populate_landlord_leasing_terms(): void
    {
        $this->seedRecord(propertyType: 'Residential Lease');

        $r    = $this->importAs($this->landlord, LandlordMlsQuickImport::class);
        $meta = LandlordAgentAuction::find($r->listingId)->get;

        foreach ([
            'smoking_policy', 'subletting_policy', 'security_deposit_amount',
            'renewal_option_offered', 'rent_escalation_terms',
            'additional_landlord_lease_terms', 'occupant_status',
        ] as $term) {
            $this->assertEmpty($meta->{$term} ?? '', "MLS import wrote the leasing term {$term}.");
        }
    }

    /**
     * @test
     *
     * MISSING FIELDS LEAVE SAFE DEFAULTS. A sparse record must not produce
     * fabricated values or empty-string noise in meta.
     */
    public function absent_bridge_fields_are_omitted_rather_than_invented(): void
    {
        // A record carrying none of the reconciled facts.
        $this->seedRecord(array_fill_keys(array_keys(self::BRIDGE_FACTS), null));

        $r    = $this->importAs($this->seller, SellerMlsQuickImport::class);
        $meta = SellerAgentAuction::find($r->listingId)->get;

        foreach (['appliances', 'roof_type', 'parcel_id', 'tax_year', 'flood_zone_code', 'legal_description'] as $key) {
            $this->assertEmpty($meta->{$key} ?? '', "{$key} was invented from an absent Bridge field.");
        }
    }

    /**
     * @test
     *
     * EMPTY AND BLANK MEMBERS. A feed that sends ["Slab", "", null] must not
     * produce a trailing empty option in a multi-select.
     */
    public function empty_list_members_are_dropped(): void
    {
        $this->seedRecord([
            'FoundationDetails' => ['Slab', '', null, '  '],
            'Roof'              => [],
        ]);

        $r    = $this->importAs($this->seller, SellerMlsQuickImport::class);
        $meta = SellerAgentAuction::find($r->listingId)->get;

        $this->assertSame(['Slab'], $this->asList($meta->foundation));
        $this->assertEmpty($meta->roof_type ?? '', 'An empty list must be omitted, not stored as [""].');
    }

    /**
     * @test
     *
     * SINGLE-VALUED LIST FIELDS. Some records send a bare string where the
     * schema allows a list; it must still arrive as a one-member selection.
     */
    public function a_scalar_sent_for_a_list_field_becomes_a_single_selection(): void
    {
        $this->seedRecord(['Roof' => 'Shingle']);

        $r    = $this->importAs($this->seller, SellerMlsQuickImport::class);
        $meta = SellerAgentAuction::find($r->listingId)->get;

        $this->assertSame(['Shingle'], $this->asList($meta->roof_type));
    }

    /**
     * @test
     *
     * RE-IMPORT DOES NOT OVERWRITE. The established rule: the user may already
     * have corrected something the feed got wrong, and a refresh that reverted
     * their correction would be worse than not refreshing.
     */
    public function re_importing_does_not_overwrite_a_corrected_value(): void
    {
        $this->seedRecord();

        $first   = $this->importAs($this->seller, SellerMlsQuickImport::class);
        $listing = SellerAgentAuction::find($first->listingId);

        // The user corrects the feed.
        $listing->saveMeta('parcel_id', 'USER-CORRECTED-PARCEL');
        $listing->saveMeta('roof_type', ['Metal']);

        // …and imports the same record again.
        $second = $this->importAs($this->seller, SellerMlsQuickImport::class);

        $this->assertSame($first->listingId, $second->listingId, 'The same record should resume the same draft.');

        $meta = SellerAgentAuction::find($second->listingId)->fresh()->get;

        $this->assertSame('USER-CORRECTED-PARCEL', (string) $meta->parcel_id);
        $this->assertSame(['Metal'], $this->asList($meta->roof_type));
    }

    /**
     * @test
     *
     * PROVENANCE SURVIVES. The record handles are what let the coordinate ladder
     * and the refresh path find this property's feed record later.
     */
    public function provenance_and_source_identifiers_remain_intact(): void
    {
        $this->seedRecord();

        $r    = $this->importAs($this->seller, SellerMlsQuickImport::class);
        $meta = SellerAgentAuction::find($r->listingId)->get;

        $this->assertSame(self::MLS, (string) $meta->mls_number);
        $this->assertNotEmpty($meta->mls_listing_key ?? '');
        $this->assertSame('bridge', (string) ($meta->mls_provider ?? ''));
    }

    /**
     * @test
     *
     * The restricted content that rides along on every record still never lands,
     * however many facts were added beside it.
     */
    public function restricted_content_is_still_excluded(): void
    {
        $this->seedRecord();

        $r        = $this->importAs($this->seller, SellerMlsQuickImport::class);
        $listing  = SellerAgentAuction::find($r->listingId);
        $stored   = json_encode($listing->meta()->pluck('meta_value', 'meta_key')->toArray());

        foreach ([
            'RESTRICTED_PUBLIC_REMARKS',
            'RESTRICTED_PRIVATE_REMARKS',
            'RESTRICTED_AGENT',
            'RESTRICTED_BROKER',
        ] as $marker) {
            $this->assertStringNotContainsString($marker, (string) $stored);
        }
    }

    /**
     * @test
     *
     * Every canonical key the allow-list can emit has a real destination on at
     * least one role. A key with no map entry anywhere would be a fact fetched
     * and silently dropped — the exact defect this reconciliation closed.
     */
    public function every_allow_listed_key_has_a_destination_on_some_role(): void
    {
        $seller   = MlsFieldMap::forRole('seller');
        $landlord = MlsFieldMap::forRole('landlord');

        // Keys that legitimately have no FORM target. Each is carried for a
        // documented non-form purpose, and each omission is written down at the
        // point it is made:
        //
        //   mls_number / mls_listing_key — record handles. No input exists; they
        //       are persisted as meta so the coordinate ladder's Bridge rung and
        //       the refresh path can find this record later.
        //   latitude / longitude — resolved together by the coordinate pipeline,
        //       never offered as editable fields.
        //   lot_size_sqft — MlsFieldMap omits it deliberately: the Seller and
        //       Landlord forms express lot size in ACRES (lot_size_acres →
        //       total_acreage) and no Livewire property accepts square feet.
        //       Mapping it would need a unit conversion, which is a product
        //       decision rather than a mapping entry.
        //   property_sub_type / mls_status — classification shown on the
        //       confirmation and review screens and carried into provenance;
        //       neither has an editable form field.
        $carriedAsMeta = [
            'mls_number', 'mls_listing_key',
            'latitude', 'longitude',
            'lot_size_sqft',
            'property_sub_type', 'mls_status',
        ];

        $orphans = [];

        foreach (MlsListingPrefillService::ALLOWED_FIELDS as $property => $key) {
            if (in_array($key, $carriedAsMeta, true)) {
                continue;
            }

            if (! isset($seller[$key]) && ! isset($landlord[$key])) {
                $orphans[] = "{$property} => {$key}";
            }
        }

        $this->assertSame(
            [],
            $orphans,
            'These allow-listed keys reach no form field on any role and are not on the '
            . 'documented carry-only list, so the fact is fetched and silently dropped: '
            . implode(', ', $orphans)
        );
    }

    /** @return list<string> */
    private function asList(mixed $stored): array
    {
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored  = is_array($decoded) ? $decoded : array_map('trim', explode(',', $stored));
        }

        return array_values(array_filter((array) ($stored ?? []), static fn ($v) => $v !== '' && $v !== null));
    }
}
