<?php

namespace Tests\Support\Matching;

use App\Services\Canonical\CanonicalListing;
use App\Services\Canonical\CanonicalListingVocabulary as V;
use App\Services\Stellar\Matching\ListingMatchResidualFacts;
use App\Support\Listing\MlsProvider;

/**
 * P1-B — a record from an imaginary second MLS, and its test-only translation.
 *
 * The record is deliberately ALIEN: square metres, prices in cents, its own status
 * word (`ON_MARKET`), asset / deal codes instead of a property type, and no
 * `STELLAR_*` key and no RESO field name anywhere. If the match engine's input can
 * be built from this, scoring does not depend on Stellar's shape.
 *
 * There is no fake ingestion here — no normalizer, no candidate adapter, no table.
 * toCanonical() builds the CanonicalListing through its CONSTRUCTOR (the one real
 * MlsProvider case, since a second case is a later decision; a different ListingKey;
 * local id 0; provenance naming no real provider) and toResidual() builds the
 * residual directly. That is exactly the work a real second provider's adapter and
 * residual reader would do, and nothing more.
 *
 * RESIDENTIAL_TWIN states the same property as the committed Stellar residential
 * fixture (tests/fixtures/mls/bridge/residential.json), in this provider's terms.
 */
final class FakeSecondMlsRecord
{
    public const PROVENANCE_SOURCE = 'second_mls_fixture';

    private const SQFT_PER_M2 = 10.7639;

    /** @var array<string,mixed> */
    public const RESIDENTIAL_TWIN = [
        'ref'    => 'SMX-0000001',
        'state'  => 'ON_MARKET',
        'asset'  => 'RES',
        'deal'   => 'BUY',
        'ask_cents' => 18490000,
        'rent_period' => null,

        'geo'  => ['y' => 27.788945, 'x' => -82.735144],
        'addr' => ['town' => 'ST PETERSBURG', 'region' => 'FL', 'post' => '33710', 'district' => 'Pinellas'],

        'floor_m2' => 72.0,
        'gross_m2' => 72.0,
        'plot_m2'  => 0.0,
        'built'    => 1991,
        'subkind'  => 'Condominium',

        'has' => [
            'pool' => false, 'garage' => false, 'waterfront' => false,
            'view' => false, 'water_view' => false,
        ],

        'hoa'              => ['member' => true, 'fee_cents' => null, 'period' => 'Monthly'],
        'tax_cents'        => 269280,
        'special_district' => false,
        'new_build'        => false,

        'pets'       => 'Cats OK, Dogs OK, Yes',
        'community'  => ['Clubhouse', 'Pool', 'Tennis Court(s)'],
        'amenities'  => ['Clubhouse', 'Elevator(s)', 'Pickleball Court(s)', 'Pool', 'Tennis Court(s)'],
        'green'      => [],
        'green_cert' => [],
        'lease_term' => null,

        'days_listed'       => 4,
        'flood_designation' => 'X',
        'schools'           => [],
    ];

    /** This provider's codes, translated into the canonical category / transaction / status words. */
    private const ASSET = ['RES' => 'Residential', 'INC' => 'Income', 'COM' => 'Commercial', 'BIZ' => 'Business', 'LND' => 'Vacant Land'];
    private const DEAL  = ['BUY' => 'sale', 'LET' => 'lease'];
    private const STATE = ['ON_MARKET' => V::STATUS_ACTIVE, 'UNDER_OFFER' => V::STATUS_PENDING];

    /**
     * @param array<string,mixed> $record
     * @param string $provenanceSource the `source` every field's provenance names
     */
    public static function toCanonical(array $record, string $provenanceSource = self::PROVENANCE_SOURCE): CanonicalListing
    {
        $fields = [];
        $meta   = [];

        $put = static function (string $key, mixed $value, string $sourceField) use (&$fields, &$meta, $provenanceSource): void {
            if ($value === null) {
                return;
            }

            $fields[$key] = $value;
            $meta[$key]   = ['source' => $provenanceSource, 'source_field' => $sourceField, 'source_reliability' => 'fixture', 'freshness' => null];
        };

        $transaction = self::DEAL[$record['deal']] ?? null;

        $put(V::LISTING_TRANSACTION_TYPE, $transaction, 'deal');
        $put(V::LISTING_STANDARD_STATUS, self::STATE[$record['state']] ?? null, 'state');
        $put(V::LISTING_LIST_PRICE, $record['ask_cents'] > 0 ? $record['ask_cents'] / 100 : null, 'ask_cents');
        if ($transaction === 'lease') {
            $put(V::LISTING_LEASE_AMOUNT_FREQUENCY, $record['rent_period'], 'rent_period');
        }

        $put(V::PROPERTY_TYPE, self::ASSET[$record['asset']] ?? null, 'asset');
        $put(V::PROPERTY_LIVING_AREA_SQFT, $record['floor_m2'] > 0 ? round($record['floor_m2'] * self::SQFT_PER_M2) : null, 'floor_m2');
        $put(V::PROPERTY_YEAR_BUILT, $record['built'], 'built');
        // Canonical publishes a Yes/No only when it is yes, whoever the provider is.
        $put(V::PROPERTY_POOL, $record['has']['pool'] ? true : null, 'has.pool');
        $put(V::PROPERTY_GARAGE, $record['has']['garage'] ? true : null, 'has.garage');
        $put('property.waterfront', $record['has']['waterfront'] ? true : null, 'has.waterfront');

        $put(V::LOCATION_CITY, $record['addr']['town'], 'addr.town');
        $put(V::LOCATION_STATE, $record['addr']['region'], 'addr.region');
        $put(V::LOCATION_POSTAL_CODE, $record['addr']['post'], 'addr.post');
        $put(V::LOCATION_COUNTY, $record['addr']['district'], 'addr.district');
        $put(V::LOCATION_LATITUDE, $record['geo']['y'], 'geo.y');
        $put(V::LOCATION_LONGITUDE, $record['geo']['x'], 'geo.x');

        return new CanonicalListing(V::MLS_LISTING_TYPE, 0, $fields, $meta, MlsProvider::current(), $record['ref']);
    }

    /** @param array<string,mixed> $record */
    public static function toResidual(array $record): ListingMatchResidualFacts
    {
        $sqft = static fn (?float $m2): ?int => $m2 === null ? null : (int) round($m2 * self::SQFT_PER_M2);
        $money = static fn (?int $cents): ?string => $cents === null ? null : number_format($cents / 100, 2, '.', '');

        return new ListingMatchResidualFacts(
            lotSizeSqft:       $sqft($record['plot_m2']),
            buildingAreaTotal: $record['gross_m2'] === null ? null : (float) $sqft($record['gross_m2']),

            propertySubType: $record['subkind'],

            view:      $record['has']['view'],
            waterView: $record['has']['water_view'],

            associationFee:          $money($record['hoa']['fee_cents']),
            associationFeeFrequency: $record['hoa']['period'],
            association:             $record['hoa']['member'],
            taxAnnualAmount:         $money($record['tax_cents']),
            cdd:                     $record['special_district'],

            newConstruction:               $record['new_build'],
            petsAllowed:                   $record['pets'],
            communityFeatures:             $record['community'],
            associationAmenities:          $record['amenities'],
            greenEnergyEfficient:          $record['green'],
            greenBuildingVerificationType: $record['green_cert'],
            leaseTerm:                     $record['lease_term'],

            daysOnMarket:    $record['days_listed'],
            floodZoneStated: $record['flood_designation'] !== null,
            schoolsListed:   $record['schools'] !== [],
        );
    }
}
