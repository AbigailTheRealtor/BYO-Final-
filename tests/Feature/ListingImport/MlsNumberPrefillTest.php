<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Buyer\BuyerOfferListing;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use App\Models\BridgeProperty;
use App\Models\User;
use App\Services\ListingImport\MlsListingPrefillService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MLS # → Bridge → preview → apply, end to end through the real Livewire
 * components.
 *
 *   importListingByMlsNumber()  → BridgeListingLookupService::lookupByMlsNumber()
 *                               → MlsListingPrefillService (facts-only)
 *                               → $importPreviewData
 *   applyImportedFields()       → Livewire props + mls_listing_key meta
 *
 * The Bridge row is seeded into the local cache so these exercise the real
 * lookup path (local-first) without any HTTP; the tests that specifically care
 * about the API leg fake HTTP instead.
 */
class MlsNumberPrefillTest extends TestCase
{
    use DatabaseTransactions;

    private const MLS  = 'PHPUNIT-PREFILL-A4567890';
    private const KEY  = 'PHPUNIT-PREFILL-STELLAR-KEY-1';
    private const CITY = 'PhpunitPrefillCity';

    private User $sellerUser;
    private User $landlordUser;

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

        $this->sellerUser   = User::factory()->create(['user_type' => 'seller']);
        $this->landlordUser = User::factory()->create(['user_type' => 'seller']);
    }

    /**
     * A cached Bridge row whose raw_json carries every restricted field a real
     * Stellar record would — so the facts-only assertions run against data that
     * genuinely contains what must not leak.
     */
    private function seedBridgeProperty(array $overrides = []): BridgeProperty
    {
        $raw = [
            'ListingKey'           => self::KEY,
            'ListingId'            => self::MLS,
            'PublicRemarks'        => 'RESTRICTED_PUBLIC_REMARKS charming pool home',
            'PrivateRemarks'       => 'RESTRICTED_PRIVATE_REMARKS gate code 4455',
            'ShowingInstructions'  => 'RESTRICTED_SHOWING appointment only',
            'ListAgentFullName'    => 'RESTRICTED_AGENT Jane Agent',
            'ListAgentEmail'       => 'RESTRICTED_AGENTEMAIL jane@example.com',
            'ListAgentDirectPhone' => 'RESTRICTED_AGENTPHONE 8135550100',
            'ListOfficeName'       => 'RESTRICTED_BROKER Acme Realty',
            'ListOfficePhone'      => 'RESTRICTED_BROKERPHONE 8135550199',
            'Media'                => ['RESTRICTED_PHOTO https://cdn.example.com/a.jpg'],
        ];

        return BridgeProperty::create(array_merge([
            'listing_key'             => self::KEY,
            'listing_id'              => self::MLS,
            'standard_status'         => 'Active',
            'mls_status'              => 'Active',
            'property_type'           => 'Residential',
            'property_sub_type'       => 'Single Family Residence',
            'list_price'              => 459000,
            'unparsed_address'        => '123 Main St, ' . self::CITY . ', FL 33601',
            'city'                    => self::CITY,
            'state_or_province'       => 'FL',
            'postal_code'             => '33601',
            'county_or_parish'        => 'Hillsborough',
            'bedrooms_total'          => 4,
            'bathrooms_total_integer' => 3,
            'living_area'             => 2450,
            'lot_size_sqft'           => 8712,
            'year_built'              => 1998,
            'latitude'                => 27.9506,
            'longitude'               => -82.4572,
            'raw_json'                => json_encode($raw),
        ], $overrides));
    }

    /** @return array<string,string> canonical_key => value */
    private function previewByKey(array $preview): array
    {
        $out = [];
        foreach ($preview as $row) {
            $out[$row['canonical_key']] = $row['value'];
        }
        return $out;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SELLER: MLS # → preview → apply
    // ═══════════════════════════════════════════════════════════════════════

    public function test_seller_mls_number_populates_preview_without_touching_the_form(): void
    {
        $this->seedBridgeProperty();

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $component->assertSet('importError', '');

        $preview = $this->previewByKey($component->get('importPreviewData'));
        $this->assertNotEmpty($preview, 'MLS # lookup must stage a preview');

        $this->assertSame('123 Main St, ' . self::CITY . ', FL 33601', $preview['address']);
        $this->assertSame(self::CITY,     $preview['city']);
        $this->assertSame('FL',           $preview['state']);
        $this->assertSame('33601',        $preview['zip']);
        $this->assertSame('Hillsborough', $preview['county']);
        $this->assertSame('4',            $preview['bedrooms']);

        // The form itself must still be untouched — review comes first.
        $component->assertSet('address', '');
        $component->assertSet('property_city', '');
        $component->assertSet('bedrooms', '');
    }

    public function test_seller_apply_populates_form_props(): void
    {
        $this->seedBridgeProperty();

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $keys = array_keys($this->previewByKey($component->get('importPreviewData')));
        $component->call('applyImportedFields', $keys, []);

        $component->assertSet('address',         '123 Main St, ' . self::CITY . ', FL 33601');
        $component->assertSet('property_city',   self::CITY);
        $component->assertSet('property_state',  'FL');
        $component->assertSet('property_zip',    '33601');
        $component->assertSet('property_county', 'Hillsborough');
        $component->assertSet('bedrooms',        '4');
        $component->assertSet('bathrooms',       '3');
        $component->assertSet('year_built',      '1998');
        $component->assertSet('maximum_budget',  '459000');
        $component->assertSet('property_type',   'Residential');
        $component->assertSet('importSuccess',   true);
        $component->assertSet('showImportModal', false);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // LANDLORD: MLS # → preview → apply
    // ═══════════════════════════════════════════════════════════════════════

    public function test_landlord_apply_populates_form_props(): void
    {
        $this->seedBridgeProperty();

        $component = Livewire::actingAs($this->landlordUser)
            ->test(LandlordOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $component->assertSet('importError', '');

        $keys = array_keys($this->previewByKey($component->get('importPreviewData')));
        $component->call('applyImportedFields', $keys, []);

        $component->assertSet('address',         '123 Main St, ' . self::CITY . ', FL 33601');
        $component->assertSet('property_city',   self::CITY);
        $component->assertSet('property_state',  'FL');
        $component->assertSet('property_zip',    '33601');
        $component->assertSet('property_county', 'Hillsborough');
        $component->assertSet('bedrooms',        '4');
        $component->assertSet('year_built',      '1998');
        // Landlord routes price to the rental amount, not a sale price.
        $component->assertSet('desired_rental_amount', '459000');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COORDINATES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider roleComponentProvider
     */
    public function test_bridge_coordinates_reach_property_lat_lng(string $componentClass, string $userProp): void
    {
        $this->seedBridgeProperty();

        $component = Livewire::actingAs($this->{$userProp})
            ->test($componentClass)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $preview = $this->previewByKey($component->get('importPreviewData'));
        $this->assertSame('27.9506',  $preview['latitude']);
        $this->assertSame('-82.4572', $preview['longitude']);

        $component->call('applyImportedFields', array_keys($preview), []);

        $component->assertSet('property_lat', '27.9506');
        $component->assertSet('property_lng', '-82.4572');
        $component->assertSet('mlsBridgeCoordinatesAvailable', true);
    }

    public static function roleComponentProvider(): array
    {
        return [
            'seller'   => [SellerOfferListing::class,   'sellerUser'],
            'landlord' => [LandlordOfferListing::class, 'landlordUser'],
        ];
    }

    public function test_listing_without_coordinates_does_not_claim_to_have_them(): void
    {
        $this->seedBridgeProperty(['latitude' => null, 'longitude' => null]);

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $preview = $this->previewByKey($component->get('importPreviewData'));

        $this->assertArrayNotHasKey('latitude',  $preview);
        $this->assertArrayNotHasKey('longitude', $preview);
        $component->assertSet('mlsBridgeCoordinatesAvailable', false);
    }

    /**
     * A zeroed coordinate column is an unset value, not Null Island. Importing
     * it would be worse than importing nothing, because a populated
     * property_lat also suppresses the geocoding fallback.
     */
    public function test_zeroed_coordinates_are_not_imported(): void
    {
        $this->seedBridgeProperty(['latitude' => 0, 'longitude' => 0]);

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $preview = $this->previewByKey($component->get('importPreviewData'));

        $this->assertArrayNotHasKey('latitude',  $preview);
        $this->assertArrayNotHasKey('longitude', $preview);
        $component->assertSet('mlsBridgeCoordinatesAvailable', false);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FACTS ONLY
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider restrictedMarkerProvider
     */
    public function test_restricted_fields_never_reach_preview_or_form(string $marker, string $what): void
    {
        $this->seedBridgeProperty();

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $preview = $component->get('importPreviewData');
        $this->assertStringNotContainsString($marker, json_encode($preview), "{$what} leaked into the preview");

        $component->call('applyImportedFields', array_keys($this->previewByKey($preview)), []);

        foreach (['address', 'property_city', 'additional_details', 'property_state'] as $prop) {
            $this->assertStringNotContainsString(
                $marker,
                (string) $component->get($prop),
                "{$what} leaked into form prop {$prop}"
            );
        }

        $this->assertStringNotContainsString(
            $marker,
            (string) $component->get('mlsImportSnapshotJson'),
            "{$what} leaked into the persisted import snapshot"
        );
    }

    public static function restrictedMarkerProvider(): array
    {
        return [
            'public remarks'  => ['RESTRICTED_PUBLIC_REMARKS',  'PublicRemarks'],
            'private remarks' => ['RESTRICTED_PRIVATE_REMARKS', 'PrivateRemarks'],
            'showing instr.'  => ['RESTRICTED_SHOWING',         'ShowingInstructions'],
            'agent name'      => ['RESTRICTED_AGENT',           'listing agent'],
            'agent email'     => ['RESTRICTED_AGENTEMAIL',      'agent email'],
            'agent phone'     => ['RESTRICTED_AGENTPHONE',      'agent phone'],
            'broker name'     => ['RESTRICTED_BROKER',          'broker'],
            'broker phone'    => ['RESTRICTED_BROKERPHONE',     'broker phone'],
            'photos'          => ['RESTRICTED_PHOTO',           'media/photos'],
        ];
    }

    /**
     * Record handles are carried in the data but are never offered as an
     * editable preview row — there is no form input for either, and an opaque
     * RESO key in a review table invites someone to uncheck the one value the
     * coordinate lookup depends on.
     */
    public function test_record_handles_are_not_shown_as_preview_rows(): void
    {
        $this->seedBridgeProperty();

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $preview = $this->previewByKey($component->get('importPreviewData'));

        foreach (MlsListingPrefillService::NON_PREVIEW_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $preview);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ERRORS
    // ═══════════════════════════════════════════════════════════════════════

    public function test_blank_mls_number_is_rejected_before_any_lookup(): void
    {
        Http::fake();

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', '   ')
            ->call('importListingByMlsNumber');

        $this->assertStringContainsString('enter an MLS #', $component->get('importError'));
        $this->assertEmpty($component->get('importPreviewData'));
        Http::assertNothingSent();
    }

    public function test_genuine_no_match_says_not_found(): void
    {
        Http::fake(['*' => Http::response(['value' => []], 200)]);

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', 'PHPUNIT-NO-SUCH-LISTING')
            ->call('importListingByMlsNumber');

        $error = $component->get('importError');
        $this->assertStringContainsString("couldn't find a listing", $error);
        $this->assertStringNotContainsString('connect', $error);
    }

    /**
     * The distinction that motivated BridgeLookupResult: a provider outage must
     * not be reported to the user as "your listing does not exist".
     *
     * @dataProvider unavailableScenarioProvider
     */
    public function test_bridge_unavailable_says_connection_problem_not_not_found(callable $arrange): void
    {
        $arrange();

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', 'PHPUNIT-UNAVAILABLE')
            ->call('importListingByMlsNumber');

        $error = $component->get('importError');
        $this->assertStringContainsString("couldn't connect", $error);
        $this->assertStringNotContainsString("couldn't find", $error);
    }

    public static function unavailableScenarioProvider(): array
    {
        return [
            'no credentials' => [function () {
                config(['bridge.dataset' => null, 'bridge.token' => null]);
                Http::fake();
            }],
            'auth failure' => [function () {
                Http::fake(['*' => Http::response('', 401)]);
            }],
            'server error' => [function () {
                Http::fake(['*' => Http::response('', 500)]);
            }],
            'timeout' => [function () {
                Http::fake(function () {
                    throw new \Illuminate\Http\Client\ConnectionException('timed out');
                });
            }],
        ];
    }

    /**
     * No error message may carry a token, dataset name, status line or URL.
     */
    public function test_error_messages_never_expose_configuration_or_provider_detail(): void
    {
        config(['bridge.dataset' => 'SECRET_DATASET_NAME', 'bridge.token' => 'SECRET_TOKEN_VALUE']);
        Http::fake(['*' => Http::response('provider said: invalid access_token SECRET_TOKEN_VALUE', 401)]);

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', 'PHPUNIT-LEAKCHECK')
            ->call('importListingByMlsNumber');

        $error = $component->get('importError');

        $this->assertStringNotContainsString('SECRET_TOKEN_VALUE',  $error);
        $this->assertStringNotContainsString('SECRET_DATASET_NAME', $error);
        $this->assertStringNotContainsString('401',                 $error);
        $this->assertStringNotContainsString('bridgedataoutput',    $error);
        $this->assertStringNotContainsString('access_token',        $error);
    }

    public function test_malformed_response_does_not_throw(): void
    {
        Http::fake(['*' => Http::response(['unexpected' => 'shape'], 200)]);

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', 'PHPUNIT-MALFORMED')
            ->call('importListingByMlsNumber');

        $this->assertNotSame('', $component->get('importError'));
        $this->assertEmpty($component->get('importPreviewData'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FEATURE FLAG
    // ═══════════════════════════════════════════════════════════════════════

    public function test_flag_off_makes_the_action_inert_and_sends_nothing(): void
    {
        config(['mls_direct_import.prefill_enabled' => false]);
        $this->seedBridgeProperty();
        Http::fake();

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $this->assertEmpty($component->get('importPreviewData'), 'flag OFF must not import anything');
        $this->assertSame('MLS # import is not available.', $component->get('importError'));
        $component->assertSet('address', '');
        Http::assertNothingSent();
    }

    /**
     * @dataProvider roleComponentProvider
     */
    public function test_availability_helper_tracks_the_flag(string $componentClass, string $userProp): void
    {
        config(['mls_direct_import.prefill_enabled' => true]);
        $on = Livewire::actingAs($this->{$userProp})->test($componentClass);
        $this->assertTrue($on->instance()->mlsNumberImportAvailable());

        config(['mls_direct_import.prefill_enabled' => false]);
        $off = Livewire::actingAs($this->{$userProp})->test($componentClass);
        $this->assertFalse($off->instance()->mlsNumberImportAvailable());
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ROLE ISOLATION
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Buyer and Tenant list search criteria across many areas, not one property.
     * The MLS # entry point must be unavailable to them even with the flag ON.
     *
     * @dataProvider buyerTenantProvider
     */
    public function test_buyer_and_tenant_never_get_mls_number_import(string $componentClass, string $userType): void
    {
        config(['mls_direct_import.prefill_enabled' => true]);
        $this->seedBridgeProperty();
        Http::fake();

        $user = User::factory()->create(['user_type' => $userType]);

        $component = Livewire::actingAs($user)->test($componentClass);
        $this->assertFalse($component->instance()->mlsNumberImportAvailable());

        $component->set('importMlsNumber', self::MLS)->call('importListingByMlsNumber');

        $this->assertEmpty($component->get('importPreviewData'));
        Http::assertNothingSent();
    }

    public static function buyerTenantProvider(): array
    {
        return [
            'buyer'  => [BuyerOfferListing::class,  'buyer'],
            'tenant' => [TenantOfferListing::class, 'tenant'],
        ];
    }

    /**
     * The URL/text importer is a separate mechanism and is not gated by the
     * Bridge flag — turning Bridge off must not disable it for anyone.
     */
    public function test_url_text_importer_still_works_with_the_bridge_flag_off(): void
    {
        config(['mls_direct_import.prefill_enabled' => false]);

        $raw = implode("\n", [
            'City: Tampa',
            'County: Hillsborough',
            'State: FL',
            'Zip: 33610',
            'Bedrooms: 4',
            'Year Built: 1998',
        ]);

        $component = Livewire::actingAs($this->sellerUser)
            ->test(SellerOfferListing::class)
            ->set('importRawText', $raw)
            ->call('importListingFromUrl');

        $component->assertSet('importError', '');
        $preview = $this->previewByKey($component->get('importPreviewData'));

        $this->assertSame('Tampa', $preview['city']);
        $this->assertSame('4',     $preview['bedrooms']);
    }
}
