<?php

namespace Tests\Feature\Canonical;

use App\Models\LandlordAgentAuction;
use App\Models\LandlordAgentAuctionMeta;
use App\Models\PropertyLocationDna;
use App\Models\SellerAgentAuction;
use App\Models\SellerAgentAuctionMeta;
use App\Models\User;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Canonical\Adapters\ByoListingAdapter;
use App\Services\Canonical\Adapters\MlsListingAdapter;
use App\Services\Canonical\CanonicalListing;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P0-5 — a BidYourOffer supply listing and an MLS record describing the SAME
 * property carry compatible canonical semantics.
 *
 * Compatible, not identical: the two sources store different things (a BYO row
 * has a workflow and no rent period; an MLS record has a native identity and
 * its own provenance). Those differences are asserted explicitly below rather
 * than hidden, so a later phase that closes one of them has to change this test.
 *
 * Each case pairs a real BYO row (through ByoListingAdapter) with a committed
 * Stellar fixture (through BridgePropertyCandidateAdapter → MlsListingAdapter),
 * both carrying the same property facts.
 */
class ByoMlsCanonicalParityTest extends TestCase
{
    use DatabaseTransactions;

    private const ADDRESS = [
        'line' => '123 Oak Lane', 'city' => 'Tampa', 'state' => 'FL', 'zip' => '33602', 'county' => 'Hillsborough',
        'lat' => 27.9506, 'lng' => -82.4572,
    ];

    /** @dataProvider pairs */
    public function test_equivalent_byo_and_mls_listings_share_canonical_semantics(
        string $role,
        string $byoType,
        string $fixture,
        array $facts,
        string $transaction,
        string $category,
    ): void {
        $byo = $this->byo($role, $byoType, $fixture, $facts);
        $mls = $this->mls($fixture, $facts);

        foreach ([$byo, $mls] as $c) {
            $this->assertTrue($c->isSupply());
            $this->assertSame($transaction, $c->transactionType());
            $this->assertSame($category, $c->propertyType());
            $this->assertSame('Active', $c->standardStatus());
        }

        $this->assertSame($facts['price'], $byo->listPrice());
        $this->assertSame($byo->listPrice(), $mls->listPrice());
        $this->assertSame($byo->bedrooms(), $mls->bedrooms());
        $this->assertSame($byo->bathrooms(), $mls->bathrooms());
        $this->assertSame($byo->livingAreaSqft(), $mls->livingAreaSqft());
        $this->assertSame($byo->yearBuilt(), $mls->yearBuilt());
        $this->assertSame($byo->hasPool(), $mls->hasPool());
        $this->assertSame($byo->hasGarage(), $mls->hasGarage());
        $this->assertSame($byo->lotAcreage(), $mls->lotAcreage());
        $this->assertSame($byo->get('property.waterfront'), $mls->get('property.waterfront'));
        $this->assertSame($byo->addressLine(), $mls->addressLine());
        $this->assertSame($byo->city(), $mls->city());
        $this->assertSame($byo->state(), $mls->state());
        $this->assertSame($byo->postalCode(), $mls->postalCode());
        $this->assertSame($byo->county(), $mls->county());
        $this->assertNotNull($byo->coordinates());
        $this->assertSame($byo->coordinates(), $mls->coordinates());

        // The fractional bathroom survives on both sides wherever the property has one.
        if (isset($facts['baths'])) {
            $this->assertSame($facts['baths'], $mls->bathrooms());
        }

        // ── Known, deliberate differences ───────────────────────────────────
        $this->assertSame('offer_listing', $byo->workflow(), 'a BYO row has a workflow');
        $this->assertNull($mls->workflow(), 'an MLS record has none');

        $this->assertNull($byo->leaseAmountFrequency(), 'BYO does not read its rent period yet');
        $this->assertSame($transaction === 'lease' ? 'monthly' : null, $mls->leaseAmountFrequency());

        $this->assertNull($byo->mlsNativeIdentity(), 'an MLS-linked BYO row carries no native identity in P0-5');
        $this->assertStringStartsWith('mls:stellar_bridge:', (string) $mls->mlsNativeIdentity());

        $this->assertSame("byo:{$role}_agent", $byo->fieldMeta('property.type')['source']);
        $this->assertSame('mls:stellar_bridge', $mls->fieldMeta('property.type')['source']);
        $this->assertSame(ByoListingAdapter::SOURCE_RELIABILITY, $byo->fieldMeta('property.type')['source_reliability']);
        $this->assertSame(MlsListingAdapter::SOURCE_RELIABILITY, $mls->fieldMeta('property.type')['source_reliability']);
    }

    public static function pairs(): array
    {
        $home = [
            'price' => 450000.0, 'beds' => 3, 'baths' => 2.5, 'area' => 1850, 'year' => 1998,
            'pool' => true, 'garage' => true, 'lot' => 0.25, 'waterfront' => true,
        ];

        return [
            'Residential Sale' => ['seller', 'Residential', 'residential', $home, 'sale', 'Residential'],
            'Residential Lease' => ['landlord', 'Residential Property', 'residential_lease',
                ['price' => 2750.0] + $home, 'lease', 'Residential'],
            // The Seller form shows a pool control on Income but no garage control.
            'Income' => ['seller', 'Income', 'income',
                ['price' => 610000.0, 'beds' => 8, 'baths' => 4.5, 'area' => 4200, 'year' => 1974, 'pool' => true, 'lot' => 0.4, 'waterfront' => true],
                'sale', 'Income'],
            'Commercial Sale' => ['seller', 'Commercial', 'commercial_sale',
                ['price' => 939000.0, 'area' => 2750, 'year' => 1976, 'lot' => 0.29, 'waterfront' => true],
                'sale', 'Commercial'],
            'Commercial Lease' => ['landlord', 'Commercial Property', 'commercial_lease',
                ['price' => 4750.0, 'area' => 2750, 'year' => 1976, 'lot' => 0.29, 'waterfront' => true],
                'lease', 'Commercial'],
            'Business Opportunity' => ['seller', 'Business', 'business_opportunity',
                ['price' => 950000.0, 'area' => 5612, 'year' => 1962, 'lot' => 7.25, 'waterfront' => true],
                'sale', 'Business'],
            'Vacant Land' => ['seller', 'Vacant Land', 'vacant_land',
                ['price' => 939000.0, 'lot' => 12.5],
                'sale', 'Vacant Land'],
        ];
    }

    // ── Builders ────────────────────────────────────────────────────────────

    private function mls(string $fixture, array $facts): CanonicalListing
    {
        $raw = json_decode((string) file_get_contents(base_path("tests/fixtures/mls/bridge/{$fixture}.json")), true);

        $raw = array_merge($raw, [
            'StandardStatus'        => 'Active',
            'ListPrice'             => $facts['price'],
            'BedroomsTotal'         => $facts['beds'] ?? null,
            'BathroomsTotalDecimal' => $facts['baths'] ?? null,
            'LivingArea'            => $facts['area'] ?? null,
            'YearBuilt'             => $facts['year'] ?? null,
            'PoolPrivateYN'         => $facts['pool'] ?? null,
            'GarageYN'              => $facts['garage'] ?? null,
            'LotSizeAcres'          => $facts['lot'] ?? null,
            'WaterfrontYN'          => $facts['waterfront'] ?? null,
            'UnparsedAddress'       => self::ADDRESS['line'],
            'City'                  => self::ADDRESS['city'],
            'StateOrProvince'       => self::ADDRESS['state'],
            'PostalCode'            => self::ADDRESS['zip'],
            'CountyOrParish'        => self::ADDRESS['county'],
            'Latitude'              => self::ADDRESS['lat'],
            'Longitude'             => self::ADDRESS['lng'],
        ]);

        $c = (new MlsListingAdapter())->fromCandidate((new BridgePropertyCandidateAdapter())->fromRecord($raw));
        $this->assertNotNull($c);

        return $c;
    }

    private function byo(string $role, string $byoType, string $fixture, array $facts): CanonicalListing
    {
        $feedType = json_decode((string) file_get_contents(base_path("tests/fixtures/mls/bridge/{$fixture}.json")), true)['PropertyType'];

        $yesNo = static fn (?bool $v): ?string => $v === null ? null : ($v ? 'Yes' : 'No');

        $meta = array_filter([
            'property_type'            => $byoType,
            // An MLS-linked BYO row: the feed's price and status of record.
            'mls_listing_key'          => 'PARITY-' . $fixture,
            'mls_standard_status'      => 'Active',
            'mls_list_price'           => (string) $facts['price'],
            'mls_source_property_type' => $feedType,
            'bedrooms'                 => isset($facts['beds']) ? (string) $facts['beds'] : null,
            'bathrooms'                => isset($facts['baths']) ? (string) $facts['baths'] : null,
            'minimum_heated_square'    => isset($facts['area']) ? (string) $facts['area'] : null,
            'year_built'               => isset($facts['year']) ? (string) $facts['year'] : null,
            'pool_needed'              => $yesNo($facts['pool'] ?? null),
            'garage_needed'            => $yesNo($facts['garage'] ?? null),
            'total_acreage'            => isset($facts['lot']) ? (string) $facts['lot'] : null,
            'waterfront'               => $yesNo($facts['waterfront'] ?? null),
            'address'                  => self::ADDRESS['line'],
            'property_city'            => self::ADDRESS['city'],
            'property_state'           => self::ADDRESS['state'],
            'property_zip'             => self::ADDRESS['zip'],
            'property_county'          => self::ADDRESS['county'],
        ], static fn ($v) => $v !== null);

        [$model, $metaModel, $fk, $type] = $role === 'seller'
            ? [SellerAgentAuction::class, SellerAgentAuctionMeta::class, 'seller_agent_auction_id', 'seller_agent']
            : [LandlordAgentAuction::class, LandlordAgentAuctionMeta::class, 'landlord_agent_auction_id', 'landlord_agent'];

        $row = $model::unguarded(fn () => $model::create([
            'user_id'     => User::factory()->create()->id,
            'title'       => 'Canonical P0-5 parity',
            'is_draft'    => false,
            'is_approved' => true,
            'is_sold'     => false,
        ]));

        $rows = [[$fk => $row->id, 'meta_key' => 'workflow_type', 'meta_value' => 'offer_listing']];
        foreach ($meta as $key => $value) {
            $rows[] = [$fk => $row->id, 'meta_key' => $key, 'meta_value' => $value];
        }
        DB::table((new $metaModel())->getTable())->insert($rows);

        // The exact coordinate BYO may be measured from, through the ladder's gate.
        PropertyLocationDna::create([
            'listing_type'      => $type,
            'listing_id'        => $row->id,
            'source_address'    => self::ADDRESS['line'],
            'source_city'       => self::ADDRESS['city'],
            'source_county'     => self::ADDRESS['county'],
            'source_state'      => self::ADDRESS['state'],
            'source_zip'        => self::ADDRESS['zip'],
            'geocoded_lat'      => self::ADDRESS['lat'],
            'geocoded_lng'      => self::ADDRESS['lng'],
            'geocode_status'    => 'geocoded',
            'geocode_source'    => 'saved_meta',
            'geocode_provider'  => 'bridge_mls',
            'geocode_precision' => 'rooftop',
            'geocoded_at'       => now(),
        ]);

        return (new ByoListingAdapter())->fromModel($row->fresh(), $type, $row->id);
    }
}
