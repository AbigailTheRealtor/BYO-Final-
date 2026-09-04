<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Seller\SellerOfferListing;
use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapter;
use App\Services\Location\Coordinates\CoordinatePrecision;
use App\Services\Location\Coordinates\CoordinateSource;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\LocationDna\LocationDnaGeocodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The ListingKey → meta → coordinate-ladder connection.
 *
 * BridgeMlsCoordinatesAdapter — rung 2 of the ladder that already runs on every
 * Seller/Landlord save — finds a feed record by `listing_key` and by nothing
 * else. Before this feature nothing in production ever wrote that key, so the
 * rung returned `no_mls_listing_key` on every real save and was, in effect,
 * dead code.
 *
 * These tests prove the key now gets written by a Bridge import, and that the
 * pre-existing adapter resolves the MLS's own coordinate from it. That is the
 * whole Location DNA payoff of this feature, and it is one meta key wide — so it
 * is worth asserting end to end rather than trusting that the two halves meet.
 */
class MlsListingKeyCoordinateActivationTest extends TestCase
{
    use DatabaseTransactions;

    private const MLS  = 'PHPUNIT-KEYACT-A7654321';
    private const KEY  = 'PHPUNIT-KEYACT-STELLAR-KEY';
    private const CITY = 'PhpunitKeyActCity';

    private const LAT = 27.9506;
    private const LNG = -82.4572;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Http::fake(); // nothing here should ever reach the network

        // The Member/Office/OpenHouse enrichment is switched OFF for this test,
        // and only for this test. It legitimately makes secondary Bridge requests
        // during an import — that is the point of it — while everything this file
        // asserts is that the COORDINATE rung resolves from the stored ListingKey
        // with no network at all. Leaving enrichment on would make
        // Http::assertNothingSent() fail for a reason unrelated to coordinates,
        // and weakening that assertion would cost the guarantee the file exists
        // for. Enrichment has its own tests.
        config(['mls_related_resources.enabled' => false]);

        config([
            'mls_direct_import.prefill_enabled' => true,
            'mls_direct_import.prefill_roles'   => ['seller', 'landlord'],
            'bridge.dataset'                    => 'phpunit_dataset',
            'bridge.token'                      => 'phpunit-token',
        ]);
    }

    private function seedBridgeProperty(array $overrides = []): BridgeProperty
    {
        return BridgeProperty::create(array_merge([
            'listing_key'       => self::KEY,
            'listing_id'        => self::MLS,
            'standard_status'   => 'Active',
            'property_type'     => 'Residential',
            'list_price'        => 459000,
            'unparsed_address'  => '123 Main St, ' . self::CITY . ', FL 33601',
            'city'              => self::CITY,
            'state_or_province' => 'FL',
            'postal_code'       => '33601',
            'county_or_parish'  => 'Hillsborough',
            'bedrooms_total'    => 4,
            'latitude'          => self::LAT,
            'longitude'         => self::LNG,
            'raw_json'          => json_encode(['ListingKey' => self::KEY]),
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // The key is persisted
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Apply-time path: the listing already exists, so the key is written
     * immediately rather than waiting for the next save.
     */
    public function test_seller_apply_persists_mls_listing_key_to_meta(): void
    {
        $this->seedBridgeProperty();

        $user    = User::factory()->create(['user_type' => 'seller']);
        $auction = SellerAgentAuction::create(['user_id' => $user->id]);

        $component = Livewire::actingAs($user)
            ->test(SellerOfferListing::class)
            ->set('listingId', $auction->id)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $keys = array_map(fn ($r) => $r['canonical_key'], $component->get('importPreviewData'));
        $component->call('applyImportedFields', $keys, []);

        $this->assertSame(
            self::KEY,
            SellerAgentAuction::find($auction->id)->fresh()->info('mls_listing_key'),
            'the RESO ListingKey must be persisted so the coordinate ladder can use it'
        );
    }

    public function test_landlord_apply_persists_mls_listing_key_to_meta(): void
    {
        $this->seedBridgeProperty();

        $user    = User::factory()->create(['user_type' => 'seller']);
        $auction = LandlordAgentAuction::create(['user_id' => $user->id]);

        $component = Livewire::actingAs($user)
            ->test(LandlordOfferListing::class)
            ->set('listingId', $auction->id)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $keys = array_map(fn ($r) => $r['canonical_key'], $component->get('importPreviewData'));
        $component->call('applyImportedFields', $keys, []);

        $this->assertSame(
            self::KEY,
            LandlordAgentAuction::find($auction->id)->fresh()->info('mls_listing_key')
        );
    }

    /**
     * Save-time path: the import happened on a brand-new draft that had no ID
     * yet, so the key rides in the snapshot and is written by saveSnapshotMeta().
     * This is the branch a real "create listing" flow actually takes.
     *
     * Driven through saveDraft() — the actual button a user presses — rather
     * than by reaching for the protected hook. saveDraft() creates the auction
     * row, then runs saveAllMetadata() → saveSnapshotMeta(), which is precisely
     * the production sequence this test exists to pin. Calling the hook directly
     * would prove the hook works while leaving the thing that has to call it
     * untested.
     */
    public function test_key_written_at_save_time_when_import_preceded_the_listing_id(): void
    {
        $this->seedBridgeProperty();

        $user = User::factory()->create(['user_type' => 'seller']);

        // No listingId set — mirrors an import on an unsaved draft.
        $component = Livewire::actingAs($user)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $keys = array_map(fn ($r) => $r['canonical_key'], $component->get('importPreviewData'));
        $component->call('applyImportedFields', $keys, []);

        $this->assertNull(
            $component->get('listingId'),
            'precondition: no listing id at apply time, so nothing could have been persisted yet'
        );
        $this->assertSame(
            0,
            SellerAgentAuction::where('user_id', $user->id)->count(),
            'precondition: the import alone must not create a listing'
        );

        // The user saves the listing. This is the real path to saveSnapshotMeta().
        $component->call('saveDraft');

        $this->assertNull(
            session('error'),
            'saveDraft swallows exceptions into a flashed error — a silent failure must not read as a pass'
        );

        $listingId = $component->get('listingId');
        $this->assertNotNull($listingId, 'saveDraft must have created the listing');

        $this->assertSame(
            self::KEY,
            SellerAgentAuction::find($listingId)->info('mls_listing_key'),
            'the RESO ListingKey must be persisted by the ordinary save, not only by the apply-time path'
        );
    }

    /**
     * The URL/text importer has no ListingKey to offer, and must not invent one.
     * An absent key correctly leaves the Bridge rung silent.
     */
    public function test_url_text_import_writes_no_listing_key(): void
    {
        $user    = User::factory()->create(['user_type' => 'seller']);
        $auction = SellerAgentAuction::create(['user_id' => $user->id]);

        $component = Livewire::actingAs($user)
            ->test(SellerOfferListing::class)
            ->set('listingId', $auction->id)
            ->set('importRawText', "City: Tampa\nState: FL\nZip: 33610\nBedrooms: 4")
            ->call('importListingFromUrl');

        $keys = array_map(fn ($r) => $r['canonical_key'], $component->get('importPreviewData'));
        $component->call('applyImportedFields', $keys, []);

        $this->assertFalse($auction->fresh()->info('mls_listing_key'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // The adapter resolves from the persisted key
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * The payoff: with the key persisted, the pre-existing rung returns the
     * MLS's own published coordinate — no geocoder, no Google, no network.
     */
    public function test_persisted_key_lets_the_bridge_rung_resolve_the_coordinate(): void
    {
        $this->seedBridgeProperty();

        $user    = User::factory()->create(['user_type' => 'seller']);
        $auction = SellerAgentAuction::create(['user_id' => $user->id]);

        $component = Livewire::actingAs($user)
            ->test(SellerOfferListing::class)
            ->set('listingId', $auction->id)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $keys = array_map(fn ($r) => $r['canonical_key'], $component->get('importPreviewData'));
        $component->call('applyImportedFields', $keys, []);

        $storedKey = SellerAgentAuction::find($auction->id)->fresh()->info('mls_listing_key');

        // Hand the stored key to the ladder rung exactly as
        // PropertyCoordinatePersistenceService::addressFor() does.
        $result = (new BridgeMlsCoordinatesAdapter())->resolve(new PropertyAddress(
            address:       '123 Main St',
            city:          self::CITY,
            county:        'Hillsborough',
            state:         'FL',
            zip:           '33601',
            listingType:   'seller_agent',
            listingId:     $auction->id,
            mlsListingKey: (string) $storedKey,
        ));

        $this->assertTrue($result->isResolved(), 'the Bridge rung must resolve once a ListingKey is present');
        $this->assertEqualsWithDelta(self::LAT, $result->latitude,  0.0000001);
        $this->assertEqualsWithDelta(self::LNG, $result->longitude, 0.0000001);
        $this->assertSame(CoordinatePrecision::Parcel, $result->precision);
        $this->assertSame(CoordinateSource::Mls,       $result->source);
        $this->assertSame('bridge_mls',                $result->provider);

        Http::assertNothingSent();
    }

    /**
     * The before picture, pinned: with no key the rung is silent. This is what
     * every production listing looked like prior to this feature, and it is
     * still the correct behaviour for a listing that was not MLS-imported.
     */
    public function test_without_a_key_the_bridge_rung_stays_silent(): void
    {
        $this->seedBridgeProperty();

        $result = (new BridgeMlsCoordinatesAdapter())->resolve(new PropertyAddress(
            address: '123 Main St',
            city:    self::CITY,
            state:   'FL',
            zip:     '33601',
        ));

        $this->assertFalse($result->isResolved());
        $this->assertSame('no_mls_listing_key', $result->reason);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // No Google
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * A Bridge import that carried coordinates must not geocode. The MLS already
     * published this property's position; sending its address to a geocoder
     * would spend a request to obtain a worse answer.
     *
     * Both suppression points are exercised through production entry points: the
     * apply-time one via applyImportedFields(), and the save-time one via
     * saveDraft(), which is what reaches mlsGeocodeSaveTimeFallback().
     *
     * The geocoder is asserted on directly rather than only through
     * Http::assertNothingSent(), because mlsGeocodeAddress() swallows every
     * Throwable — a geocode attempt that failed for an unrelated reason would
     * send no request and would otherwise read as "correctly suppressed".
     */
    public function test_bridge_import_with_coordinates_suppresses_geocoding(): void
    {
        $this->seedBridgeProperty();

        $geocodeAttempted = false;
        $geocoder = \Mockery::mock(LocationDnaGeocodeService::class);
        $geocoder->shouldReceive('geocodeForListing')
            ->andReturnUsing(function () use (&$geocodeAttempted) {
                $geocodeAttempted = true;
                return ['success' => false, 'lat' => null, 'lng' => null];
            });
        $this->app->instance(LocationDnaGeocodeService::class, $geocoder);

        $user = User::factory()->create(['user_type' => 'seller']);

        // No listingId — the draft is created by the save below, which is the
        // flow in which the save-time fallback would otherwise fire.
        $component = Livewire::actingAs($user)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        $keys = array_map(fn ($r) => $r['canonical_key'], $component->get('importPreviewData'));
        $component->call('applyImportedFields', $keys, []);

        $component->assertSet('mlsBridgeCoordinatesAvailable', true);
        $component->assertSet('property_lat', (string) self::LAT);

        $this->assertFalse($geocodeAttempted, 'apply must not geocode when the MLS supplied coordinates');

        // Save-time fallback must also decline.
        $component->call('saveDraft');

        $this->assertNull(session('error'), 'saveDraft must not have failed silently');
        $this->assertFalse(
            $geocodeAttempted,
            'the save-time fallback must not geocode when the MLS supplied coordinates'
        );

        // The MLS coordinate survives the save, and the key that lets the Bridge
        // rung re-derive it is stored alongside.
        $listingId = $component->get('listingId');
        $saved     = SellerAgentAuction::find($listingId);

        $this->assertSame((string) self::LAT, (string) $saved->info('property_lat'));
        $this->assertSame((string) self::LNG, (string) $saved->info('property_lng'));
        $this->assertSame(self::KEY, $saved->info('mls_listing_key'));

        // No geocoding provider — Google or otherwise — was contacted.
        Http::assertNothingSent();
    }

    /**
     * The case that isolates the Bridge guard: the user unchecked the coordinate
     * rows in the preview, so `property_lat` is empty at save time.
     *
     * This matters because the save-time fallback has a pre-existing "only
     * geocode when property_lat is empty" check, which in the ordinary import
     * suppresses geocoding on its own. Here that check does NOT fire — the
     * component genuinely has no coordinate — and the only thing standing
     * between this save and a geocoder request is
     * `mlsBridgeCoordinatesAvailable`.
     *
     * Suppressing it is the correct call rather than a missed opportunity: the
     * listing carries `mls_listing_key`, so the coordinate ladder's Bridge rung
     * resolves the MLS's own published position on this very save. Geocoding as
     * well would substitute an inferred point for an authoritative one the user
     * never asked us to discard.
     */
    public function test_declined_coordinate_rows_still_suppress_geocoding(): void
    {
        $this->seedBridgeProperty();

        $geocodeAttempted = false;
        $geocoder = \Mockery::mock(LocationDnaGeocodeService::class);
        $geocoder->shouldReceive('geocodeForListing')
            ->andReturnUsing(function () use (&$geocodeAttempted) {
                $geocodeAttempted = true;
                return ['success' => false, 'lat' => null, 'lng' => null];
            });
        $this->app->instance(LocationDnaGeocodeService::class, $geocoder);

        $user = User::factory()->create(['user_type' => 'seller']);

        $component = Livewire::actingAs($user)
            ->test(SellerOfferListing::class)
            ->set('importMlsNumber', self::MLS)
            ->call('importListingByMlsNumber');

        // Apply everything EXCEPT the coordinates — the user unchecked them.
        $keys = array_values(array_filter(
            array_map(fn ($r) => $r['canonical_key'], $component->get('importPreviewData')),
            fn ($k) => $k !== 'latitude' && $k !== 'longitude'
        ));
        $this->assertNotEmpty($keys, 'the preview must still offer non-coordinate rows');

        $component->call('applyImportedFields', $keys, []);

        // Precondition: no coordinate on the component, so the pre-existing
        // "already has a coordinate" check cannot be what suppresses geocoding.
        $component->assertSet('property_lat', '');
        $component->assertSet('mlsBridgeCoordinatesAvailable', true);

        $component->call('saveDraft');

        $this->assertNull(session('error'), 'saveDraft must not have failed silently');
        $this->assertFalse(
            $geocodeAttempted,
            'a Bridge import must not geocode even when the user declined the coordinate rows'
        );

        // The ListingKey is still stored, which is what lets the Bridge rung
        // supply the coordinate the user declined to import by hand.
        $saved = SellerAgentAuction::find($component->get('listingId'));
        $this->assertSame(self::KEY, $saved->info('mls_listing_key'));

        Http::assertNothingSent();
    }

    /**
     * Positive control for the two suppression tests above, and a regression
     * guard for the pre-existing URL/text importer in one.
     *
     * A URL/text import supplies an address but never a coordinate and never a
     * ListingKey, so `mlsBridgeCoordinatesAvailable` stays false and the
     * save-time fallback SHOULD geocode. Asserting that it does is what gives
     * the "must not geocode" assertions their meaning — without this, every one
     * of them would pass just as happily if the geocoder had become unreachable
     * for some unrelated reason.
     *
     * It also pins that this feature did not change what the older importer does
     * at save time.
     */
    public function test_url_text_import_without_coordinates_still_geocodes(): void
    {
        $geocodeAttempted = false;
        $geocoder = \Mockery::mock(LocationDnaGeocodeService::class);
        $geocoder->shouldReceive('geocodeForListing')
            ->andReturnUsing(function () use (&$geocodeAttempted) {
                $geocodeAttempted = true;
                return ['success' => true, 'lat' => 28.1, 'lng' => -82.1, 'place_id' => null];
            });
        $this->app->instance(LocationDnaGeocodeService::class, $geocoder);

        $user = User::factory()->create(['user_type' => 'seller']);

        $component = Livewire::actingAs($user)
            ->test(SellerOfferListing::class)
            ->set('importRawText', "Address: 123 Main St\nCity: Tampa\nState: FL\nZip: 33610\nBedrooms: 4")
            ->call('importListingFromUrl');

        $keys = array_map(fn ($r) => $r['canonical_key'], $component->get('importPreviewData'));
        $component->call('applyImportedFields', $keys, []);

        $component->assertSet('mlsBridgeCoordinatesAvailable', false);
        $component->assertSet('property_lat', '');

        $component->call('saveDraft');

        $this->assertNull(session('error'), 'saveDraft must not have failed silently');
        $this->assertTrue(
            $geocodeAttempted,
            'the save-time geocoding fallback must still run for a non-Bridge import — '
            . 'if this fails, the suppression tests above prove nothing'
        );
    }
}
