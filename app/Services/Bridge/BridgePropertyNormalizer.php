<?php

namespace App\Services\Bridge;

use App\Models\BridgeProperty;
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
     * Normalise a raw API record and upsert it into bridge_properties.
     *
     * Returns an UpsertResult DTO carrying:
     *  - isNew          — true when no row existed for this listing_key before the upsert.
     *  - addressChanged — true when unparsed_address or postal_code differs from the
     *                     stored value (including the null-to-non-null case so that the
     *                     first meaningful address arrival always triggers DNA dispatch).
     *  - model          — the persisted BridgeProperty instance after the upsert.
     *
     * Returns null if the record is missing a ListingKey (same as normalize()).
     */
    public function upsert(array $record): ?UpsertResult
    {
        $normalized = $this->normalize($record);

        if ($normalized === null) {
            return null;
        }

        $listingKey = $normalized['listing_key'];
        $upsertData = array_diff_key($normalized, ['listing_key' => true]);

        $existing = BridgeProperty::where('listing_key', $listingKey)->first();

        $isNew = ($existing === null);

        $addressChanged = false;
        if (!$isNew) {
            $incomingAddress    = $normalized['unparsed_address'] ?? null;
            $incomingPostalCode = $normalized['postal_code'] ?? null;
            // Round to 7 d.p. to match the decimal(10,7) column precision so that
            // "27.9506" and "27.9506000" compare as equal after a round-trip.
            $incomingLat = isset($normalized['latitude'])
                ? round((float) $normalized['latitude'], 7)
                : null;
            $incomingLng = isset($normalized['longitude'])
                ? round((float) $normalized['longitude'], 7)
                : null;

            $storedAddress    = $existing->unparsed_address;
            $storedPostalCode = $existing->postal_code;
            $storedLat = $existing->latitude  !== null ? round((float) $existing->latitude,  7) : null;
            $storedLng = $existing->longitude !== null ? round((float) $existing->longitude, 7) : null;

            // Treat null-to-non-null as changed so that the first meaningful
            // address or coordinate arrival always triggers DNA dispatch.
            // Latitude/longitude are included because MLS feeds can correct
            // coordinates without changing the address text — stale DNA would
            // otherwise never be refreshed.
            $addressChanged = ($incomingAddress    !== $storedAddress)
                || ($incomingPostalCode !== $storedPostalCode)
                || ($incomingLat        !== $storedLat)
                || ($incomingLng        !== $storedLng);
        }

        $model = BridgeProperty::updateOrCreate(
            ['listing_key' => $listingKey],
            $upsertData,
        );

        return new UpsertResult(
            isNew: $isNew,
            addressChanged: $addressChanged,
            model: $model,
        );
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
