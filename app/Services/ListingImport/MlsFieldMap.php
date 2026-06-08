<?php

namespace App\Services\ListingImport;

class MlsFieldMap
{
    /**
     * Maps canonical import field keys to Livewire component public property names,
     * keyed by role ('seller', 'buyer', 'landlord', 'tenant').
     *
     * Array-type target properties are flagged with a leading '*' so
     * applyImportedFields() knows to split the value on commas.
     */
    public static function forRole(string $role): array
    {
        return match ($role) {
            'seller'   => self::seller(),
            'buyer'    => self::buyer(),
            'landlord' => self::landlord(),
            'tenant'   => self::tenant(),
            default    => [],
        };
    }

    // ─── Seller ──────────────────────────────────────────────────────────────

    private static function seller(): array
    {
        return [
            'mls_number'      => 'mls_number',
            'price'           => 'purchase_price',
            'bedrooms'        => 'bedrooms',
            'bathrooms'       => 'bathrooms',
            'heated_sqft'     => 'minimum_heated_square',
            'lot_dimensions'  => 'lot_dimensions',
            'lot_size_acres'  => 'total_acreage',
            'year_built'      => 'year_built',
            'pool'            => 'pool_needed',
            'garage'          => 'garage_needed',
            'carport'         => 'carport_needed',
            'furnished'       => 'tenant_require',
            'air_conditioning' => '*air_conditioning',
            'appliances'      => '*appliances',
            'description'     => 'additional_details',
            'tax_id'          => 'tax_id',
        ];
    }

    // ─── Buyer ───────────────────────────────────────────────────────────────

    private static function buyer(): array
    {
        return [
            'bedrooms'        => 'bedrooms',
            'bathrooms'       => 'bathrooms',
            'heated_sqft'     => 'minimum_heated_square',
            'pool'            => 'pool_needed',
            'garage'          => 'garage_needed',
            'carport'         => 'carport_needed',
            'furnished'       => 'tenant_require',
            'description'     => 'additional_details',
            'year_built'      => 'year_built',
            'price'           => 'maximum_budget',
            'address'         => 'address',
            'city'            => 'property_city',
            'state'           => 'property_state',
            'zip'             => 'property_zip',
            'county'          => 'property_county',
        ];
    }

    // ─── Landlord ────────────────────────────────────────────────────────────

    private static function landlord(): array
    {
        return [
            'mls_number'      => 'mls_number',
            'price'           => 'desired_rental_amount',
            'bedrooms'        => 'bedrooms',
            'bathrooms'       => 'bathrooms',
            'heated_sqft'     => 'minimum_heated_square',
            'pool'            => 'pool_needed',
            'garage'          => 'garage_needed',
            'carport'         => 'carport_needed',
            'furnished'       => 'tenant_require',
            'available_date'  => 'available_date',
            'application_fee' => 'application_fee',
            'appliances'      => '*appliances',
            'rent_includes'   => '*rent_includes',
            'description'     => 'additional_details',
            'address'         => 'address',
            'city'            => 'property_city',
            'state'           => 'property_state',
            'zip'             => 'property_zip',
            'county'          => 'property_county',
            'year_built'      => 'year_built',
        ];
    }

    // ─── Tenant ──────────────────────────────────────────────────────────────

    private static function tenant(): array
    {
        return [
            'bedrooms'        => 'bedrooms',
            'bathrooms'       => 'bathrooms',
            'heated_sqft'     => 'minimum_heated_square',
            'pool'            => 'pool_needed',
            'garage'          => 'garage_needed',
            'carport'         => 'carport_needed',
            'furnished'       => 'tenant_require',
            'rent_includes'   => '*rent_includes',
            'description'     => 'additional_details',
            'price'           => 'desired_rental_amount',
        ];
    }

    // ─── Human-readable labels for the preview table ─────────────────────────

    public static function fieldLabels(): array
    {
        return [
            'mls_number'      => 'MLS Number',
            'price'           => 'Price / Rent',
            'bedrooms'        => 'Bedrooms',
            'bathrooms'       => 'Bathrooms',
            'heated_sqft'     => 'Heated Sq Ft',
            'lot_dimensions'  => 'Lot Dimensions',
            'lot_size_acres'  => 'Lot Acreage',
            'year_built'      => 'Year Built',
            'pool'            => 'Pool',
            'garage'          => 'Garage',
            'carport'         => 'Carport',
            'furnished'       => 'Furnished',
            'air_conditioning' => 'A/C',
            'appliances'      => 'Appliances',
            'rent_includes'   => 'Rent Includes',
            'description'     => 'Description',
            'tax_id'          => 'Tax / Parcel ID',
            'application_fee' => 'Application Fee',
            'available_date'  => 'Available Date',
            'address'         => 'Address',
            'city'            => 'City',
            'state'           => 'State',
            'zip'             => 'ZIP',
            'county'          => 'County',
        ];
    }
}
