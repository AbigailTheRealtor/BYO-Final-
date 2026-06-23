<?php

namespace App\Services\Bridge;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BridgePropertyNormalizer
{
    /**
     * Normalise a raw Bridge API property record into the array shape expected
     * by BridgeProperty::updateOrCreate().
     *
     * Returns null if the record is missing a ListingKey (and logs a warning).
     */
    public function normalize(array $record): ?array
    {
        $listingKey = $record['ListingKey'] ?? null;

        if (empty($listingKey)) {
            Log::warning('BridgePropertyNormalizer: Skipping record missing ListingKey.', [
                'record_snippet' => array_slice($record, 0, 3),
            ]);
            return null;
        }

        $modTs = isset($record['ModificationTimestamp'])
            ? Carbon::parse($record['ModificationTimestamp'])->toDateTimeString()
            : null;

        $petsRaw     = $record['PetsAllowed'] ?? null;
        $petsAllowed = is_array($petsRaw)
            ? ($petsRaw[0] ?? null)
            : (is_string($petsRaw) ? $petsRaw : null);

        return [
            'listing_key' => $listingKey,

            'listing_id'              => $record['ListingId'] ?? null,
            'standard_status'         => $record['StandardStatus'] ?? null,
            'property_type'           => $record['PropertyType'] ?? null,
            'list_price'              => isset($record['ListPrice']) ? (float) $record['ListPrice'] : null,
            'unparsed_address'        => $record['UnparsedAddress'] ?? null,
            'city'                    => $record['City'] ?? null,
            'state_or_province'       => $record['StateOrProvince'] ?? null,
            'postal_code'             => $record['PostalCode'] ?? null,
            'bedrooms_total'          => isset($record['BedroomsTotal']) ? (int) $record['BedroomsTotal'] : null,
            'bathrooms_total_integer' => isset($record['BathroomsTotalInteger']) ? (int) $record['BathroomsTotalInteger'] : null,
            'living_area'             => isset($record['LivingArea']) ? (int) $record['LivingArea'] : null,
            'modification_timestamp'  => $modTs,
            'raw_json'                => json_encode($record),
            'imported_at'             => now(),

            // Phase 1 native column promotions
            'latitude'             => isset($record['Latitude']) ? (float) $record['Latitude'] : null,
            'longitude'            => isset($record['Longitude']) ? (float) $record['Longitude'] : null,
            'county_or_parish'     => $record['CountyOrParish'] ?? null,
            'property_sub_type'    => $record['PropertySubType'] ?? null,
            'mls_status'           => $record['MlsStatus'] ?? null,
            'year_built'           => isset($record['YearBuilt']) ? (int) $record['YearBuilt'] : null,
            'association_fee'      => isset($record['AssociationFee']) ? (float) $record['AssociationFee'] : null,
            'tax_annual_amount'    => isset($record['TaxAnnualAmount']) ? (float) $record['TaxAnnualAmount'] : null,
            'lot_size_sqft'        => ($v = $record['LotSizeSquareFeet'] ?? null) !== null ? (int) $v : null,
            'pets_allowed'         => $petsAllowed,

            'senior_community_yn'  => $this->filterBool($record, 'SeniorCommunityYN'),
            'garage_yn'            => $this->filterBool($record, 'GarageYN'),
            'pool_private_yn'      => $this->filterBool($record, 'PoolPrivateYN'),
            'waterfront_yn'        => $this->filterBool($record, 'WaterfrontYN'),
            'association_yn'       => $this->filterBool($record, 'AssociationYN'),
            'new_construction_yn'  => $this->filterBool($record, 'NewConstructionYN'),
            'view_yn'              => $this->filterBool($record, 'ViewYN'),
            'water_view_yn'        => $this->filterBool($record, 'STELLAR_WaterViewYN'),
            'cdd_yn'               => $this->filterBool($record, 'STELLAR_CDDYN'),
        ];
    }

    /**
     * Return true/false for a known boolean API field, or null if the key is absent.
     */
    public function filterBool(array $record, string $key): ?bool
    {
        if (!array_key_exists($key, $record)) {
            return null;
        }
        return filter_var($record[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
