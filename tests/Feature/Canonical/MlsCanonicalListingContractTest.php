<?php

namespace Tests\Feature\Canonical;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Services\Bridge\MlsCanonicalListingResolver;
use App\Services\Canonical\Adapters\MlsListingAdapter;
use App\Services\Canonical\CanonicalListing;
use App\Services\Canonical\CanonicalListingResolver;
use App\Services\Canonical\CanonicalListingVocabulary as V;
use App\Support\Listing\MlsListingLink;
use App\Support\Listing\MlsProvider;
use App\Support\ListingPreferences\ListingPreferenceSubjectRef;
use App\Support\SmartTags\SmartTagListingRef;
use App\Support\SmartTags\SmartTagListingType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P0-5 — an MLS record projected onto the canonical listing vocabulary.
 *
 * Driven by the seven committed Bridge/Stellar fixtures, one per supported
 * category, through the REAL path: raw record → BridgePropertyCandidateAdapter →
 * PropertyCandidate → MlsListingAdapter. Every expected value below is read off
 * the fixture file, so a wrong mapping fails against the feed's actual shape.
 */
class MlsCanonicalListingContractTest extends TestCase
{
    use DatabaseTransactions;

    // ── The seven categories ────────────────────────────────────────────────

    /** @dataProvider categories */
    public function test_each_stellar_category_projects_its_factual_values(string $fixture, array $expected): void
    {
        $c = $this->fromFixture($fixture);

        $this->assertNotNull($c);
        $this->assertTrue($c->isSupply());
        $this->assertFalse($c->isDemand());
        $this->assertSame(V::MLS_LISTING_TYPE, $c->listingType());

        $this->assertSame($expected['transaction'], $c->transactionType());
        $this->assertSame('Active', $c->standardStatus());
        $this->assertSame($expected['type'], $c->propertyType());
        $this->assertSame($expected['price'], $c->listPrice());
        $this->assertSame($expected['frequency'], $c->leaseAmountFrequency());
        $this->assertSame($expected['beds'], $c->bedrooms());
        $this->assertSame($expected['baths'], $c->bathrooms());
        $this->assertSame($expected['area'], $c->livingAreaSqft());
        $this->assertSame($expected['year'], $c->yearBuilt());
        $this->assertSame($expected['pool'], $c->hasPool());
        $this->assertSame($expected['garage'], $c->hasGarage());
        $this->assertSame($expected['lot'], $c->lotAcreage());
        $this->assertSame($expected['waterfront'], $c->get('property.waterfront'));
        $this->assertSame($expected['city'], $c->city());
        $this->assertSame('FL', $c->state());
        $this->assertSame($expected['zip'], $c->postalCode());
        $this->assertSame($expected['coords'], $c->coordinates());

        // An MLS record has no BidYourOffer workflow.
        $this->assertFalse($c->has(V::LISTING_WORKFLOW));
    }

    public static function categories(): array
    {
        $row = static fn (array $o): array => $o + [
            'frequency' => null, 'beds' => null, 'baths' => null, 'area' => null, 'year' => null,
            'pool' => null, 'garage' => null, 'lot' => null,
        ];

        return [
            'Residential Sale' => ['residential', $row([
                'transaction' => 'sale', 'type' => 'Residential', 'price' => 184900.0,
                'beds' => 2, 'baths' => 1.5, 'area' => 775.0, 'year' => 1991,
                'waterfront' => null, 'city' => 'ST PETERSBURG', 'zip' => '33710',
                'coords' => ['lat' => 27.788945, 'lng' => -82.735144],
            ])],
            'Residential Lease' => ['residential_lease', $row([
                'transaction' => 'lease', 'type' => 'Residential', 'price' => 3495.0, 'frequency' => 'monthly',
                'beds' => 1, 'baths' => 1.5, 'area' => 1077.0, 'year' => 1986, 'pool' => true, 'garage' => true,
                'lot' => 1.98, 'waterfront' => true, 'city' => 'NEW SMYRNA BEACH', 'zip' => '32169',
                'coords' => ['lat' => 28.975115, 'lng' => -80.856282],
            ])],
            'Income' => ['income', $row([
                'transaction' => 'sale', 'type' => 'Income', 'price' => 184900.0,
                'beds' => 2, 'baths' => 1.5, 'area' => 775.0, 'year' => 1991,
                'waterfront' => null, 'city' => 'ST PETERSBURG', 'zip' => '33710',
                'coords' => ['lat' => 27.788945, 'lng' => -82.735144],
            ])],
            'Commercial Sale' => ['commercial_sale', $row([
                'transaction' => 'sale', 'type' => 'Commercial', 'price' => 939000.0,
                'area' => 2750.0, 'year' => 1976, 'lot' => 0.29,
                'waterfront' => null, 'city' => 'ST PETERSBURG', 'zip' => '33707',
                'coords' => ['lat' => 27.760206, 'lng' => -82.737983],
            ])],
            'Commercial Lease' => ['commercial_lease', $row([
                'transaction' => 'lease', 'type' => 'Commercial', 'price' => 475.0, 'frequency' => 'monthly',
                'area' => 2750.0, 'year' => 1976, 'lot' => 0.29,
                'waterfront' => null, 'city' => 'ST PETERSBURG', 'zip' => '33707',
                'coords' => ['lat' => 27.760206, 'lng' => -82.737983],
            ])],
            'Business Opportunity' => ['business_opportunity', $row([
                'transaction' => 'sale', 'type' => 'Business', 'price' => 950000.0,
                'area' => 5612.0, 'year' => 1962, 'lot' => 7.25,
                'waterfront' => true, 'city' => 'WIMAUMA', 'zip' => '33598',
                'coords' => ['lat' => 27.645884, 'lng' => -82.354367],
            ])],
            'Vacant Land' => ['vacant_land', $row([
                'transaction' => 'sale', 'type' => 'Vacant Land', 'price' => 939000.0,
                'lot' => 12.5, 'waterfront' => null, 'city' => 'ST PETERSBURG', 'zip' => '33707',
                'coords' => ['lat' => 27.760206, 'lng' => -82.737983],
            ])],
        ];
    }

    /** @dataProvider fixtureNames */
    public function test_every_emitted_key_is_declared_typed_and_provenanced(string $fixture): void
    {
        $raw = $this->fixture($fixture);
        $c   = $this->fromRecord($raw);

        $this->assertNotSame([], $c->all());

        foreach ($c->all() as $key => $value) {
            $this->assertTrue(V::isDeclared($key), "undeclared key {$key}");
            $this->assertSame(V::SIDE_SUPPLY, V::sideOf($key), "{$key} is not a supply fact");
            $this->assertTrue(V::valueMatchesType($key, $value), "{$key} has the wrong type");
            $this->assertDoesNotMatchRegularExpression('/stellar|bridge|mls/i', $key);

            $meta = $c->fieldMeta($key);
            $this->assertSame('mls:stellar_bridge', $meta['source']);
            $this->assertSame(MlsListingAdapter::SOURCE_RELIABILITY, $meta['source_reliability']);
            $this->assertArrayHasKey($meta['source_field'], $raw, "{$key} names a field the feed record does not have");
            $this->assertStringNotContainsString('_', $meta['source_field'], "{$key}'s source field is a column name, not a RESO field");
            $this->assertSame(
                \Carbon\CarbonImmutable::parse($raw['ModificationTimestamp'])->utc()->startOfSecond()->toIso8601String(),
                $meta['freshness'],
            );
        }
    }

    public static function fixtureNames(): array
    {
        return array_map(static fn (string $f): array => [$f], [
            'residential' => 'residential', 'residential_lease' => 'residential_lease', 'income' => 'income',
            'commercial_sale' => 'commercial_sale', 'commercial_lease' => 'commercial_lease',
            'business_opportunity' => 'business_opportunity', 'vacant_land' => 'vacant_land',
        ]);
    }

    public function test_bathrooms_come_from_the_decimal_total_never_the_rounded_integer(): void
    {
        $raw = $this->fixture('residential');
        $this->assertSame(2, $raw['BathroomsTotalInteger']);
        $this->assertSame(1.5, $raw['BathroomsTotalDecimal']);

        $candidate = (new BridgePropertyCandidateAdapter())->fromRecord($raw);
        $this->assertSame(2, $candidate->bathrooms, 'the existing rounded field keeps its meaning');
        $this->assertSame(1.5, $candidate->bathroomsTotalDecimal);

        $this->assertSame(1.5, $this->fromRecord($raw)->bathrooms());
        $this->assertSame('BathroomsTotalDecimal', $this->fromRecord($raw)->fieldMeta(V::PROPERTY_BATHROOMS)['source_field']);

        // No decimal total: absent, never back-filled from the rounded integer.
        $this->assertFalse($this->fromRecord(['BathroomsTotalDecimal' => null] + $raw)->has(V::PROPERTY_BATHROOMS));
    }

    // ── Unknown stays unknown ───────────────────────────────────────────────

    /** @dataProvider unknowns */
    public function test_unknown_or_unsafe_source_values_are_absent(string $fixture, array $overrides, string $key): void
    {
        $c = $this->fromRecord(array_merge($this->fixture($fixture), $overrides));

        $this->assertFalse($c->has($key), "{$key} must be absent, not guessed");
    }

    public static function unknowns(): array
    {
        return [
            'unrecognised property type → no type'        => ['residential', ['PropertyType' => 'Farm'], V::PROPERTY_TYPE],
            'unrecognised property type → no transaction' => ['residential', ['PropertyType' => 'Farm'], V::LISTING_TRANSACTION_TYPE],
            'unrecognised status'                         => ['residential', ['StandardStatus' => 'Hibernating'], V::LISTING_STANDARD_STATUS],
            'MlsStatus is never a fallback'               => ['residential', ['StandardStatus' => null, 'MlsStatus' => 'Sold'], V::LISTING_STANDARD_STATUS],
            'zero price'                                  => ['residential', ['ListPrice' => 0], V::LISTING_LIST_PRICE],
            'lease with no period'                        => ['residential_lease', ['LeaseAmountFrequency' => null], V::LISTING_LEASE_AMOUNT_FREQUENCY],
            'lease with an unrecognised period'           => ['residential_lease', ['LeaseAmountFrequency' => 'Fortnightly-ish'], V::LISTING_LEASE_AMOUNT_FREQUENCY],
            'lease length is not a rent period'           => ['residential_lease', ['LeaseAmountFrequency' => '12 Months'], V::LISTING_LEASE_AMOUNT_FREQUENCY],
            'a sale never carries a period'               => ['residential', ['LeaseAmountFrequency' => 'Monthly'], V::LISTING_LEASE_AMOUNT_FREQUENCY],
            'quarter bathroom'                            => ['residential', ['BathroomsTotalDecimal' => 2.25], V::PROPERTY_BATHROOMS],
            'non-numeric bathrooms'                       => ['residential', ['BathroomsTotalDecimal' => 'two'], V::PROPERTY_BATHROOMS],
            'zero bedrooms'                               => ['residential', ['BedroomsTotal' => 0], V::PROPERTY_BEDROOMS],
            'zero lot acreage'                            => ['residential', ['LotSizeAcres' => 0], 'property.lot_acreage'],
            'year out of range'                           => ['residential', ['YearBuilt' => 1066], V::PROPERTY_YEAR_BUILT],
            'lone latitude'                               => ['residential', ['Longitude' => null], V::LOCATION_LATITUDE],
            'Null Island'                                 => ['residential', ['Latitude' => 0, 'Longitude' => 0], V::LOCATION_LONGITUDE],
            'out-of-range coordinate'                     => ['residential', ['Latitude' => 123.0], V::LOCATION_LATITUDE],
            'blank city'                                  => ['residential', ['City' => '  '], V::LOCATION_CITY],
        ];
    }

    /**
     * The stored Yes/No columns cannot tell "No" from "not stated": the normalizer
     * stores a feed null as false. So a false is never published as a fact —
     * only a true, which that defect cannot produce.
     */
    public function test_a_stored_false_is_not_trusted_but_a_yes_is(): void
    {
        $commercial = $this->fixture('commercial_sale');
        $this->assertNull($commercial['PoolPrivateYN'], 'the feed did not state a pool');
        $this->assertFalse(
            (new BridgePropertyCandidateAdapter())->fromRecord($commercial)->pool,
            'yet the candidate reads false — the defect this rule exists for'
        );
        $this->assertFalse($this->fromRecord($commercial)->has(V::PROPERTY_POOL));

        // An explicit feed "No" is indistinguishable from that, so it is unknown too.
        $this->assertFalse($this->fromRecord(['PoolPrivateYN' => false] + $this->fixture('residential'))->has(V::PROPERTY_POOL));

        $yes = $this->fromRecord(['PoolPrivateYN' => true, 'GarageYN' => true, 'WaterfrontYN' => true] + $this->fixture('residential'));
        $this->assertTrue($yes->hasPool());
        $this->assertTrue($yes->hasGarage());
        $this->assertTrue($yes->get('property.waterfront'));
    }

    public function test_recognised_status_spelling_and_period_normalization(): void
    {
        $this->assertSame('Canceled', $this->fromRecord(['StandardStatus' => 'Cancelled'] + $this->fixture('residential'))->standardStatus());
        $this->assertSame('Pending', $this->fromRecord(['StandardStatus' => 'Pending'] + $this->fixture('residential'))->standardStatus());

        $lease = $this->fixture('residential_lease');
        $this->assertSame('weekly', $this->fromRecord(['LeaseAmountFrequency' => 'Weekly'] + $lease)->leaseAmountFrequency());
        $this->assertSame('seasonal', $this->fromRecord(['LeaseAmountFrequency' => 'Seasonal'] + $lease)->leaseAmountFrequency());
        $this->assertSame('annually', $this->fromRecord(['LeaseAmountFrequency' => 'Annually'] + $lease)->leaseAmountFrequency());

        // The price is carried as the feed states it; a weekly rent is never converted.
        $this->assertSame(3495.0, $this->fromRecord(['LeaseAmountFrequency' => 'Weekly'] + $lease)->listPrice());
    }

    public function test_a_withheld_address_is_preserved_in_the_internal_read_model(): void
    {
        $c = $this->fromRecord(['InternetAddressDisplayYN' => false] + $this->fixture('residential'));

        $this->assertSame('6817 STONES THROW CIRCLE N UNIT 17208', $c->addressLine(), 'preservation is not display');
    }

    // ── Native identity ─────────────────────────────────────────────────────

    public function test_the_native_identity_is_provider_scoped_and_matches_the_preference_subject_key(): void
    {
        $row = $this->storeFixture('residential');
        $c   = (new MlsCanonicalListingResolver())->forBridgeProperty($row);

        $this->assertSame($row->id, $c->listingId());
        $this->assertSame(MlsProvider::StellarBridge, $c->mlsProvider());
        $this->assertSame('FIXTURE-BCC360244E3A', $c->mlsListingKey());
        $this->assertSame('mls:stellar_bridge:FIXTURE-BCC360244E3A', $c->mlsNativeIdentity());

        $subject = ListingPreferenceSubjectRef::mls(
            new SmartTagListingRef(SmartTagListingType::Bridge, $row->id),
            MlsProvider::StellarBridge,
            'FIXTURE-BCC360244E3A',
        );
        $this->assertSame($subject->subjectKey, $c->mlsNativeIdentity());

        $this->assertSame(SmartTagListingType::Bridge->value, V::MLS_LISTING_TYPE);
    }

    public function test_the_same_listing_key_under_another_provider_is_another_listing(): void
    {
        $stellar = $this->storeFixture('residential', ['ListingKey' => 'SHARED-KEY']);
        $other   = $this->storeFixture('residential_lease', ['ListingKey' => 'SHARED-KEY'], provider: 'other_mls');

        $resolver = new MlsCanonicalListingResolver();

        $resolved = $resolver->forNativeKey(MlsProvider::StellarBridge, 'SHARED-KEY');
        $this->assertSame($stellar->id, $resolved->listingId(), 'the key resolves within its provider, never globally');
        $this->assertSame('sale', $resolved->transactionType());

        // The row whose provider we cannot name gets no canonical listing at all —
        // it is not read as Stellar's by elimination.
        $this->assertNull($resolver->forBridgeProperty($other));

        // And the bare key is ambiguous, so it links to nothing.
        $this->assertNull(MlsListingLink::providerForListingKey('SHARED-KEY'));
    }

    public function test_an_unrecognised_provider_fails_closed(): void
    {
        $row = $this->storeFixture('residential', ['ListingKey' => 'ALIEN-1'], provider: 'other_mls');

        $this->assertNull((new BridgePropertyCandidateAdapter())->fromModel($row)->mlsProvider);
        $this->assertNull((new MlsCanonicalListingResolver())->forBridgeProperty($row));
        $this->assertNull(MlsListingLink::providerForListingKey('ALIEN-1'), 'one row, provider unrecognised');
    }

    public function test_a_blank_listing_key_fails_closed(): void
    {
        $this->assertNull((new MlsListingAdapter())->fromCandidate(
            (new BridgePropertyCandidateAdapter())->fromRecord(['ListingKey' => null] + $this->fixture('residential'))
        ));
        $this->assertNull((new MlsCanonicalListingResolver())->forNativeKey(MlsProvider::StellarBridge, '   '));
        $this->assertNull(MlsListingLink::providerForListingKey(''));
    }

    public function test_a_missing_row_resolves_to_nothing_and_never_reaches_the_network(): void
    {
        $this->assertNull((new MlsCanonicalListingResolver())->forNativeKey(MlsProvider::StellarBridge, 'NOT-HELD'));
    }

    public function test_listing_link_resolves_exactly_one_recognised_row(): void
    {
        $this->storeFixture('residential', ['ListingKey' => 'ONE-ROW']);

        $this->assertSame(MlsProvider::StellarBridge, MlsListingLink::providerForListingKey('ONE-ROW'));
        $this->assertSame(['ONE-ROW'], array_keys(MlsListingLink::providersForListingKeys(['ONE-ROW', 'ABSENT', ''])));
    }

    public function test_the_native_reference_needs_both_halves(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CanonicalListing(V::MLS_LISTING_TYPE, 1, [], [], null, 'KEY-ONLY');
    }

    public function test_the_native_reference_rejects_a_provider_without_a_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CanonicalListing(V::MLS_LISTING_TYPE, 1, [], [], MlsProvider::StellarBridge, ' ');
    }

    // ── Nothing is activated ────────────────────────────────────────────────

    public function test_the_byo_resolver_still_does_not_support_mls_rows(): void
    {
        $resolver = app(CanonicalListingResolver::class);

        $this->assertFalse($resolver->supports(V::MLS_LISTING_TYPE));
        $this->assertFalse($resolver->supports('bridge'));
        $this->assertNull($resolver->resolve('bridge', $this->storeFixture('residential')->id));
    }

    public function test_resolving_an_mls_listing_dispatches_nothing_and_writes_nothing(): void
    {
        $row = $this->storeFixture('residential');

        Bus::fake();
        Queue::fake();

        $writes = 0;
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql) === 1) {
                $writes++;
            }
        });

        $resolver = new MlsCanonicalListingResolver();
        $this->assertNotNull($resolver->forBridgeProperty($row));
        $this->assertNotNull($resolver->forNativeKey(MlsProvider::StellarBridge, (string) $row->listing_key));

        Bus::assertNothingDispatched();
        Queue::assertNothingPushed();
        $this->assertSame(0, $writes);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function fixture(string $slug): array
    {
        return json_decode((string) file_get_contents(base_path("tests/fixtures/mls/bridge/{$slug}.json")), true);
    }

    private function fromFixture(string $slug): ?CanonicalListing
    {
        return $this->fromRecord($this->fixture($slug));
    }

    private function fromRecord(array $raw): CanonicalListing
    {
        $c = (new MlsListingAdapter())->fromCandidate((new BridgePropertyCandidateAdapter())->fromRecord($raw));
        $this->assertNotNull($c);

        return $c;
    }

    private function storeFixture(string $slug, array $overrides = [], string $provider = 'stellar_bridge'): BridgeProperty
    {
        $columns = (new BridgePropertyNormalizer())->normalize(array_merge($this->fixture($slug), $overrides));
        $columns['provider'] = $provider;

        return BridgeProperty::create($columns);
    }
}
