<?php

namespace Tests\Feature\Explore\Concerns;

use App\Models\BridgeProperty;

/**
 * Builds Bridge/Stellar records shaped like the ones this dataset actually
 * returns, so the Explore tests exercise the real field names rather than a
 * convenient approximation.
 *
 * The defaults mirror tests/fixtures/mls/bridge/residential.json: the display
 * flags present and true, Media as an array of objects with MediaURL and Order,
 * an unbranded tour URL, a ParcelNumber and a UnitNumber.
 */
trait MakesExploreListings
{
    /** St. Petersburg, inside the default Explore camera. */
    protected const LAT = 27.7676;
    protected const LNG = -82.6403;

    /**
     * @param  array<string,mixed>  $overrides   native column overrides
     * @param  array<string,mixed>  $rawOverrides  raw_json overrides (null value removes the key)
     */
    protected function makeListing(array $overrides = [], array $rawOverrides = []): BridgeProperty
    {
        static $sequence = 0;
        $sequence++;

        $columns = array_merge([
            'listing_key'             => 'LK' . str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'listing_id'              => 'MLS' . $sequence,
            'standard_status'         => 'Active',
            'mls_status'              => 'Active',
            'property_type'           => 'Residential',
            'property_sub_type'       => 'Condominium',
            'list_price'              => 525000,
            'unparsed_address'        => '123 Example Street',
            'city'                    => 'St Petersburg',
            'state_or_province'       => 'FL',
            'postal_code'             => '33701',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1850,
            'latitude'                => self::LAT,
            'longitude'               => self::LNG,
            'imported_at'             => now(),
        ], $overrides);

        $raw = [
            'ListingKey'                          => $columns['listing_key'],
            'ListingId'                           => $columns['listing_id'],
            'StandardStatus'                      => $columns['standard_status'],
            'MlsStatus'                           => $columns['mls_status'],
            'PropertyType'                        => $columns['property_type'],
            'PropertySubType'                     => $columns['property_sub_type'],
            'ListPrice'                           => $columns['list_price'],
            'UnparsedAddress'                     => $columns['unparsed_address'],
            'City'                                => $columns['city'],
            'StateOrProvince'                     => $columns['state_or_province'],
            'PostalCode'                          => $columns['postal_code'],
            'UnitNumber'                          => '4B',
            'ParcelNumber'                        => '18311685538017' . $sequence,
            'Latitude'                            => $columns['latitude'],
            'Longitude'                           => $columns['longitude'],
            'IDXParticipationYN'                  => true,
            'InternetEntireListingDisplayYN'      => true,
            'InternetAddressDisplayYN'            => true,
            'InternetAutomatedValuationDisplayYN' => true,
            'InternetConsumerCommentYN'           => true,
            'VirtualTourURLUnbranded'             => 'https://tours.example.com/' . $columns['listing_key'],
            'PhotosCount'                         => 3,
            'Media'                               => [
                ['MediaKey' => 'M1', 'MediaURL' => 'https://cdn.example.com/1.jpg', 'Order' => 1, 'MediaCategory' => 'Photo'],
                ['MediaKey' => 'M2', 'MediaURL' => 'https://cdn.example.com/2.jpg', 'Order' => 2, 'MediaCategory' => 'Photo'],
                ['MediaKey' => 'M3', 'MediaURL' => 'https://cdn.example.com/3.jpg', 'Order' => 3, 'MediaCategory' => 'Photo'],
            ],
        ];

        foreach ($rawOverrides as $key => $value) {
            if ($value === null) {
                unset($raw[$key]);
                continue;
            }

            $raw[$key] = $value;
        }

        $columns['raw_json'] = json_encode($raw);

        return BridgeProperty::create($columns);
    }

    /** A Residential Lease record: ListPrice IS the monthly rent. */
    protected function makeRental(array $overrides = [], array $rawOverrides = []): BridgeProperty
    {
        return $this->makeListing(
            array_merge([
                'property_type' => 'Residential Lease',
                'list_price'    => 2750,
            ], $overrides),
            array_merge(['LeaseAmountFrequency' => 'Monthly'], $rawOverrides),
        );
    }

    /** A bbox comfortably containing the default coordinates. */
    protected function bboxAroundDefault(): string
    {
        return implode(',', [self::LAT - 0.05, self::LNG - 0.05, self::LAT + 0.05, self::LNG + 0.05]);
    }

    /** A bbox nowhere near the default coordinates. */
    protected function bboxElsewhere(): string
    {
        return '28.40,-81.45,28.50,-81.35'; // Orlando
    }
}
