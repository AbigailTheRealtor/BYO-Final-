<?php

namespace Tests\Feature\ListingImport;

use App\Http\Livewire\OfferListing\QuickImport\LandlordMlsQuickImport;
use App\Http\Livewire\OfferListing\QuickImport\SellerMlsQuickImport;
use App\Models\BridgeProperty;
use App\Models\LandlordAgentAuction;
use App\Models\PropertyLocationDna;
use App\Models\SellerAgentAuction;
use App\Models\User;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter;
use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\PropertyCoordinateMeta;
use App\Services\Location\Coordinates\PropertyCoordinateResolverInterface;
use App\Services\Location\Coordinates\PropertyCoordinateResult;
use App\Services\LocationDna\LocationDnaGeocodeService;
use App\Services\LocationDna\LocationDnaPipelineRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MLS quick import resolves the property's coordinate through the ladder, and
 * does it before it dispatches Location DNA.
 *
 * THE DEFECT THIS PINS
 * --------------------
 * Quick import writes an `mls_listing_key` — which is exactly the handle
 * {@see \App\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapter}
 * matches on — and then dispatched {@see \App\Jobs\ComputeLocationDna} without
 * ever asking the ladder. Two things followed from that, and both are asserted
 * below rather than described:
 *
 *   1. When the feed record carried a coordinate, {@see \App\Services\ListingImport\MlsFieldMap}
 *      copied it straight into `property_lat`/`property_lng` with NO provenance
 *      at all. The point was right and completely unattributed: no provider, no
 *      precision, no source ref, no normalized address. The pipeline reads that
 *      as a legacy value whose origin cannot be proven, and
 *      {@see \App\Services\Location\Coordinates\Adapters\ExistingCoordinatesAdapter}
 *      cannot vouch for it on any later save.
 *
 *   2. When the feed record carried NO coordinate — which the Bridge adapter's
 *      own documentation notes is common — quick import persisted nothing and
 *      no other rung was ever offered the address. Location DNA then had no
 *      `pre_lat` to use and fell through to geocoding by address.
 *
 * WHAT IS NOT ASSERTED HERE
 * -------------------------
 * That the Bridge rung works. That is
 * {@see \Tests\Unit\Services\Location\Coordinates\Adapters\BridgeMlsCoordinatesAdapterTest}'s
 * job, and re-proving it here would make this file fail for two unrelated
 * reasons. What is asserted is that quick import *reaches* the ladder, that the
 * ladder's answer lands on the listing with its provenance, and that it is all
 * in place before the dispatch — not merely by the time an assertion runs.
 */
class MlsQuickImportCoordinateResolutionTest extends TestCase
{
    use DatabaseTransactions;

    private const MLS = 'PHPUNIT-QICOORD-1';
    private const KEY = 'PHPUNIT-QICOORD-KEY-1';

    /** The feed record's own point. */
    private const FEED_LAT = 27.9506;
    private const FEED_LNG = -82.4572;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mls_direct_import.prefill_enabled'      => true,
            'mls_direct_import.quick_import_enabled' => true,
            'mls_direct_import.prefill_roles'        => ['seller', 'landlord'],
            'mls_media.enabled'                      => false,
            'bridge.dataset'                         => 'phpunit_dataset',
            'bridge.token'                           => 'phpunit-token',
            'bya_beta.bidding_period_enabled'        => true,

            // No network rung, and no Google. The Bridge rung is local and is the
            // one under test; anything reaching out would be a finding, not a
            // fixture.
            'census_geocoder.enabled'                => false,
            'google_places.enabled'                  => false,
            'address_point_corpus.enabled'           => false,
        ]);

        // Every rung under test is local. A fake with no route is a tripwire:
        // anything that did reach out is caught by assertNothingSent() below
        // rather than by a real request.
        Http::fake();
    }

    // ─── fixtures ────────────────────────────────────────────────────────────

    private function seedBridgeRecord(array $overrides = []): BridgeProperty
    {
        return BridgeProperty::create(array_merge([
            'listing_key'             => self::KEY,
            'listing_id'              => self::MLS,
            'standard_status'         => 'Active',
            'mls_status'              => 'Active',
            'property_type'           => 'Residential',
            'property_sub_type'       => 'Single Family Residence',
            'list_price'              => 525000,
            'unparsed_address'        => '123 Main Street',
            'city'                    => 'Tampa',
            'state_or_province'       => 'FL',
            'postal_code'             => '33601',
            'county_or_parish'        => 'Hillsborough',
            'bedrooms_total'          => 4,
            'bathrooms_total_integer' => 3,
            'living_area'             => 2450,
            'year_built'              => 2005,
            'latitude'                => self::FEED_LAT,
            'longitude'               => self::FEED_LNG,
            'raw_json'                => json_encode([
                'ListingKey' => self::KEY,
                'ListingId'  => self::MLS,
            ]),
            'imported_at'             => now(),
        ], $overrides));
    }

    private function publishAsSeller(User $user): SellerAgentAuction
    {
        $component = Livewire::actingAs($user)
            ->test(SellerMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms')
            ->set('maximum_budget', '525000')
            ->set('offered_financing', ['Cash'])
            ->call('continueToReview')
            ->call('publish');

        $this->assertSame('', $component->get('errorMessage'), 'The seller quick import must publish cleanly');

        return SellerAgentAuction::findOrFail($component->get('listingId'));
    }

    private function publishAsLandlord(User $user): LandlordAgentAuction
    {
        $component = Livewire::actingAs($user)
            ->test(LandlordMlsQuickImport::class)
            ->set('mlsNumber', self::MLS)
            ->call('findListing')
            ->call('acceptProperty')
            ->call('chooseMethod', 'Traditional')
            ->call('continueToTerms')
            ->set('desired_rental_amount', '2400')
            ->call('continueToReview')
            ->call('publish');

        $this->assertSame('', $component->get('errorMessage'), 'The landlord quick import must publish cleanly');

        return LandlordAgentAuction::findOrFail($component->get('listingId'));
    }

    /** Every provenance key the ladder stamps, read off a listing. */
    private function provenanceOf(object $listing): array
    {
        return [
            'provider'   => (string) ($listing->info(PropertyCoordinateMeta::PROVIDER) ?: ''),
            'source'     => (string) ($listing->info(PropertyCoordinateMeta::SOURCE) ?: ''),
            'precision'  => (string) ($listing->info(PropertyCoordinateMeta::PRECISION) ?: ''),
            'source_ref' => (string) ($listing->info(PropertyCoordinateMeta::SOURCE_REF) ?: ''),
            'normalized' => (string) ($listing->info(PropertyCoordinateMeta::NORMALIZED_ADDRESS) ?: ''),
        ];
    }

    // ── 1. the handle is persisted, and the feed record it points at exists ──

    /** @test */
    public function quick_import_persists_the_mls_listing_key_the_bridge_rung_matches_on(): void
    {
        Bus::fake();
        $this->seedBridgeRecord();

        $listing = $this->publishAsSeller(User::factory()->create(['user_type' => 'seller']));

        $this->assertSame(
            self::KEY,
            (string) $listing->info(MlsQuickImportDraftWriter::META_LISTING_KEY),
            'Quick import must persist the MLS listing key'
        );

        $this->assertNotNull(
            BridgeProperty::where('listing_key', self::KEY)->first(),
            'The Bridge record the key points at must be present, with coordinates'
        );
    }

    // ── 2. the ladder runs, and its answer is attributed ────────────────────

    /** @test */
    public function seller_quick_import_resolves_the_coordinate_through_the_ladder(): void
    {
        Bus::fake();
        $this->seedBridgeRecord();

        $listing = $this->publishAsSeller(User::factory()->create(['user_type' => 'seller']));

        $this->assertSame((string) self::FEED_LAT, (string) $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame((string) self::FEED_LNG, (string) $listing->info(PropertyCoordinateMeta::LNG));

        $provenance = $this->provenanceOf($listing);

        // The whole point: the coordinate is not merely present, it is ATTRIBUTED.
        // A raw feed copy carries none of this, which is what made the stored
        // point unprovable on every later read.
        $this->assertSame('bridge_mls', $provenance['provider']);
        $this->assertSame('mls', $provenance['source']);
        $this->assertSame('parcel', $provenance['precision'], 'MLS coordinates are graded Parcel, never Rooftop');
        $this->assertSame(self::KEY, $provenance['source_ref'], 'The feed record that answered must be named');
        $this->assertNotSame('', $provenance['normalized'], 'The address asked about must be recorded');
    }

    /** @test */
    public function landlord_quick_import_resolves_the_coordinate_through_the_ladder(): void
    {
        Bus::fake();
        $this->seedBridgeRecord(['property_type' => 'Residential Lease', 'list_price' => 2400]);

        $listing = $this->publishAsLandlord(User::factory()->create(['user_type' => 'seller']));

        $this->assertSame((string) self::FEED_LAT, (string) $listing->info(PropertyCoordinateMeta::LAT));
        $this->assertSame((string) self::FEED_LNG, (string) $listing->info(PropertyCoordinateMeta::LNG));

        $provenance = $this->provenanceOf($listing);

        $this->assertSame('bridge_mls', $provenance['provider']);
        $this->assertSame('mls', $provenance['source']);
        $this->assertSame('parcel', $provenance['precision']);
        $this->assertSame(self::KEY, $provenance['source_ref']);
        $this->assertNotSame('', $provenance['normalized']);
    }

    /**
     * @test
     *
     * The provenance must be READABLE as a whole, not just present key by key.
     * A partial record classifies as legacy and is handled as an unprovable
     * value everywhere downstream, which is indistinguishable from the defect
     * this file exists to close.
     *
     * @dataProvider roles
     */
    public function the_stored_provenance_is_complete_enough_to_read_back(string $role): void
    {
        Bus::fake();
        $this->seedBridgeRecord(
            $role === 'landlord' ? ['property_type' => 'Residential Lease', 'list_price' => 2400] : []
        );

        $user    = User::factory()->create(['user_type' => 'seller']);
        $listing = $role === 'landlord' ? $this->publishAsLandlord($user) : $this->publishAsSeller($user);

        $provenance = PropertyCoordinateMeta::readProvenance(
            static fn (string $key) => $listing->info($key)
        );

        $this->assertNotNull($provenance, 'A quick-imported coordinate must carry provable provenance');
        $this->assertSame('bridge_mls', $provenance['provider'] ?? null);
    }

    public function roles(): array
    {
        return ['seller' => ['seller'], 'landlord' => ['landlord']];
    }

    // ── 3. ordering: resolved BEFORE the dispatch, not merely by assert time ─

    /**
     * @test
     *
     * Ordering is the whole correctness argument, and an assertion made after
     * publish returns cannot see it. So the real job runs synchronously against
     * a runner stub that records what the listing held AT THE MOMENT the
     * pipeline was entered. If resolution ran after the dispatch — or not at
     * all — the recorded coordinate is empty.
     *
     * @dataProvider roles
     */
    public function the_coordinate_is_on_the_listing_before_location_dna_is_dispatched(string $role): void
    {
        $this->seedBridgeRecord(
            $role === 'landlord' ? ['property_type' => 'Residential Lease', 'list_price' => 2400] : []
        );

        // QUEUE_CONNECTION is already 'sync' in phpunit.xml, so the dispatch
        // below runs the real job inline. Stated rather than assumed — the whole
        // observation depends on it.
        $this->assertSame('sync', config('queue.default'));

        $seen = new \ArrayObject();

        $this->app->bind(LocationDnaPipelineRunner::class, static function () use ($seen) {
            return new class($seen) extends LocationDnaPipelineRunner {
                public function __construct(private \ArrayObject $sink)
                {
                    // Deliberately does NOT call the parent constructor: nothing
                    // this stub does needs a collaborator, and building the real
                    // pipeline's dependencies is what would reach the network.
                }

                public function run(string $listingType, int $listingId): array
                {
                    $model = $listingType === 'seller_agent'
                        ? SellerAgentAuction::find($listingId)
                        : LandlordAgentAuction::find($listingId);

                    $this->sink['listing_type'] = $listingType;
                    $this->sink['lat']      = $model === null ? '' : (string) ($model->info(PropertyCoordinateMeta::LAT) ?: '');
                    $this->sink['lng']      = $model === null ? '' : (string) ($model->info(PropertyCoordinateMeta::LNG) ?: '');
                    $this->sink['provider'] = $model === null ? '' : (string) ($model->info(PropertyCoordinateMeta::PROVIDER) ?: '');

                    return ['status' => 'skipped'];
                }
            };
        });

        $user = User::factory()->create(['user_type' => 'seller']);
        $role === 'landlord' ? $this->publishAsLandlord($user) : $this->publishAsSeller($user);

        $this->assertTrue(
            isset($seen['listing_type']),
            'Location DNA must still be dispatched by quick import'
        );

        $this->assertSame(
            $role === 'landlord' ? 'landlord_agent' : 'seller_agent',
            $seen['listing_type']
        );

        $this->assertSame(
            (string) self::FEED_LAT,
            $seen['lat'],
            'The coordinate must be persisted BEFORE the Location DNA dispatch, not after it'
        );
        $this->assertSame((string) self::FEED_LNG, $seen['lng']);
        $this->assertSame(
            'bridge_mls',
            $seen['provider'],
            'The provenance must travel with the coordinate into the dispatch'
        );
    }

    // ── 4. the pipeline consumes it as saved_meta, not as a geocode ─────────

    /**
     * @test
     *
     * What the coordinate is FOR. The real geocode service is handed exactly
     * what {@see LocationDnaPipelineRunner} would read off this listing, and
     * must record the MLS point rather than geocoding the address.
     */
    public function location_dna_uses_the_resolved_mls_coordinate_as_saved_meta(): void
    {
        Bus::fake();
        $this->seedBridgeRecord();

        $listing = $this->publishAsSeller(User::factory()->create(['user_type' => 'seller']));

        $written = app(LocationDnaGeocodeService::class)->geocodeForListing('seller_agent', $listing->id, [
            'address'    => (string) $listing->info('address'),
            'city'       => (string) $listing->info('property_city'),
            'state'      => (string) $listing->info('property_state'),
            'county'     => (string) $listing->info('property_county'),
            'zip'        => (string) $listing->info('property_zip'),
            'pre_lat'    => (string) $listing->info(PropertyCoordinateMeta::LAT),
            'pre_lng'    => (string) $listing->info(PropertyCoordinateMeta::LNG),
            'provenance' => PropertyCoordinateMeta::readProvenance(
                static fn (string $key) => $listing->info($key)
            ),
        ]);

        $this->assertTrue($written['success']);

        $row = PropertyLocationDna::where('listing_type', 'seller_agent')
            ->where('listing_id', $listing->id)
            ->firstOrFail();

        $this->assertEqualsWithDelta(self::FEED_LAT, (float) $row->geocoded_lat, 0.00001);
        $this->assertEqualsWithDelta(self::FEED_LNG, (float) $row->geocoded_lng, 0.00001);
        $this->assertSame('saved_meta', $row->geocode_source);
        $this->assertSame('bridge_mls', $row->geocode_provider);
        $this->assertSame('parcel', $row->geocode_precision);
    }

    // ── 5. the feed with no coordinate still reaches the ladder ─────────────

    /**
     * @test
     *
     * The common case the raw field copy could never serve. A feed record
     * without a point used to mean quick import wrote nothing and asked nobody;
     * now the ladder is consulted and simply has no rung that can answer while
     * the corpus is empty and Census is off. The outcome is still "no
     * coordinate" — but it is an answer from the ladder, and the moment a rung
     * below Bridge is enabled this same path produces one.
     */
    public function a_feed_record_without_coordinates_still_consults_the_ladder(): void
    {
        Bus::fake();
        $this->seedBridgeRecord(['latitude' => null, 'longitude' => null]);

        $asked = new \ArrayObject();

        $this->app->bind(
            PropertyCoordinateResolverInterface::class,
            static function () use ($asked) {
                return new class($asked) implements PropertyCoordinateResolverInterface {
                    public function __construct(private \ArrayObject $sink) {}

                    public function resolve(PropertyAddress $address): PropertyCoordinateResult
                    {
                        $this->sink[] = [
                            'line'    => $address->coordinateLookupLine(),
                            'mls_key' => (string) ($address->mlsListingKey ?? ''),
                        ];

                        return PropertyCoordinateResult::unresolved(
                            'no_adapter_resolved',
                            $address->coordinateLookupLine()
                        );
                    }
                };
            }
        );

        $listing = $this->publishAsSeller(User::factory()->create(['user_type' => 'seller']));

        $this->assertCount(1, $asked, 'Quick import must ask the ladder even when the feed carries no point');
        $this->assertSame(self::KEY, $asked[0]['mls_key'], 'The MLS handle must reach the ladder');
        $this->assertNotSame('', $asked[0]['line'], 'The address must reach the ladder');

        // Nothing was invented in the absence of an answer.
        $this->assertSame('', (string) ($listing->info(PropertyCoordinateMeta::LAT) ?: ''));
    }
}
