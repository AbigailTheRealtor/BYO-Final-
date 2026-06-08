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
     *
     * Rules enforced here:
     *  • Every target property MUST exist on the corresponding Livewire component.
     *  • Buyer / Tenant maps MUST NOT receive owner-disclosure fields
     *    (tax details, legal description, flood zone, HOA).
     *  • See MlsCoverageReporter::rejectedMappingsSection() for documented invalid candidates.
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
            // ── Core property fields ─────────────────────────────────────────
            'price'           => 'purchase_price',
            'bedrooms'        => 'bedrooms',
            'bathrooms'       => 'bathrooms',
            'heated_sqft'     => 'minimum_heated_square',
            'lot_dimensions'  => 'lot_dimensions',
            'lot_size_acres'  => 'total_acreage',
            'year_built'      => 'year_built',
            'zoning'          => 'zoning',
            'pool'            => 'pool_needed',
            'garage'          => 'garage_needed',
            'carport'         => 'carport_needed',
            'furnished'       => 'tenant_require',
            'air_conditioning' => '*air_conditioning',
            'appliances'      => '*appliances',
            'description'     => 'additional_details',
            // ── Address fields ────────────────────────────────────────────────
            'address'         => 'address',
            'city'            => 'property_city',
            'state'           => 'property_state',
            'zip'             => 'property_zip',
            'county'          => 'property_county',
            // ── Tax / Legal / Flood Zone (owner-side disclosures) ─────────────
            'tax_id'          => 'parcel_id',
            'tax_year'        => 'tax_year',
            'annual_taxes'    => 'annual_property_taxes',
            'legal_description' => 'legal_description',
            'flood_zone_code' => 'flood_zone_code',
            'additional_parcels'  => 'additional_parcels',
            'total_parcel_count'  => 'total_parcel_count',
            // ── HOA / CDD ─────────────────────────────────────────────────────
            'has_hoa'                 => 'has_hoa',
            'association_name'        => 'association_name',
            'association_fee_amount'  => 'association_fee_amount',
            'association_fee_frequency' => 'association_fee_frequency',
            'has_cdd'                 => 'has_cdd',
            'annual_cdd_fee'          => 'annual_cdd_fee',
            // NOTE: 'mls_number' intentionally omitted — property does not exist
            //       on SellerOfferListing (see Rejected Mapping Candidates).
        ];
    }

    // ─── Buyer ───────────────────────────────────────────────────────────────

    private static function buyer(): array
    {
        return [
            // Buyer should NOT receive: tax_id, tax_year, annual_taxes,
            // legal_description, flood_zone_code, price→purchase_price (seller field).
            // See MlsCoverageReporter::rejectedMappingsSection() for full rationale.
            'bedrooms'        => 'bedrooms',
            'bathrooms'       => 'bathrooms',
            'heated_sqft'     => 'minimum_heated_square',
            'pool'            => 'pool_needed',
            'garage'          => 'garage_needed',
            'carport'         => 'carport_needed',
            'furnished'       => 'tenant_require',
            'description'     => 'additional_details',
            'price'           => 'maximum_budget',
            'address'         => 'address',
            'city'            => 'property_city',
            'state'           => 'property_state',
            'zip'             => 'property_zip',
            'county'          => 'property_county',
            // NOTE: 'year_built' intentionally omitted — property does not exist
            //       on BuyerOfferListing (see Rejected Mapping Candidates).
        ];
    }

    // ─── Landlord ────────────────────────────────────────────────────────────

    private static function landlord(): array
    {
        return [
            // ── Core property fields ─────────────────────────────────────────
            'price'           => 'desired_rental_amount',
            'bedrooms'        => 'bedrooms',
            'bathrooms'       => 'bathrooms',
            'heated_sqft'     => 'minimum_heated_square',
            'pool'            => 'pool_needed',
            'garage'          => 'garage_needed',
            'carport'         => 'carport_needed',
            'furnished'       => 'tenant_require',
            'year_built'      => 'year_built',
            'zoning'          => 'zoning',
            'appliances'      => '*appliances',
            'air_conditioning' => '*air_conditioning',
            'rent_includes'   => '*rent_includes',
            'description'     => 'additional_details',
            // ── Rental-specific fields ────────────────────────────────────────
            'available_date'          => 'available_date',
            'lease_amount_frequency'  => 'lease_amount_frequency',
            'minimum_security_deposit' => 'security_deposit_amount',
            'terms_of_lease'          => '*terms_of_lease',
            'tenant_pays'             => '*tenant_pays',
            // ── Address fields ────────────────────────────────────────────────
            'address'         => 'address',
            'city'            => 'property_city',
            'state'           => 'property_state',
            'zip'             => 'property_zip',
            'county'          => 'property_county',
            // ── Tax / Legal / Flood Zone (owner-side disclosures) ─────────────
            'tax_id'          => 'parcel_id',
            'tax_year'        => 'tax_year',
            'annual_taxes'    => 'annual_property_taxes',
            'legal_description' => 'legal_description',
            'flood_zone_code' => 'flood_zone_code',
            'additional_parcels'  => 'additional_parcels',
            'total_parcel_count'  => 'total_parcel_count',
            // ── HOA / CDD ─────────────────────────────────────────────────────
            'has_hoa'                 => 'has_hoa',
            'association_name'        => 'association_name',
            'association_fee_amount'  => 'association_fee_amount',
            'association_fee_frequency' => 'association_fee_frequency',
            'has_cdd'                 => 'has_cdd',
            'annual_cdd_fee'          => 'annual_cdd_fee',
            // NOTE: 'application_fee' intentionally omitted — property does not exist
            //       on LandlordOfferListing (see Rejected Mapping Candidates).
            // NOTE: 'mls_number' intentionally omitted — property does not exist
            //       on LandlordOfferListing (see Rejected Mapping Candidates).
        ];
    }

    // ─── Tenant ──────────────────────────────────────────────────────────────

    private static function tenant(): array
    {
        return [
            // Tenant should NOT receive: price→desired_rental_amount (MLS price is
            // the landlord's asking rent, not a Tenant's desired amount),
            // nor any owner-side disclosure fields.
            // See MlsCoverageReporter::rejectedMappingsSection() for full rationale.
            'bedrooms'        => 'bedrooms',
            'bathrooms'       => 'bathrooms',
            'heated_sqft'     => 'minimum_heated_square',
            'pool'            => 'pool_needed',
            'garage'          => 'garage_needed',
            'carport'         => 'carport_needed',
            'furnished'       => 'tenant_require',
            'rent_includes'   => '*rent_includes',
            'description'     => 'additional_details',
            // NOTE: 'price' intentionally omitted — see Rejected Mapping Candidates.
        ];
    }

    // ─── Human-readable labels for the preview table ─────────────────────────

    public static function fieldLabels(): array
    {
        return [
            'mls_number'          => 'MLS Number',
            'price'               => 'Price / Rent',
            'bedrooms'            => 'Bedrooms',
            'bathrooms'           => 'Bathrooms',
            'heated_sqft'         => 'Heated Sq Ft',
            'lot_dimensions'      => 'Lot Dimensions',
            'lot_size_acres'      => 'Lot Acreage',
            'lot_size_sqft'       => 'Lot Size (Sq Ft)',
            'year_built'          => 'Year Built',
            'zoning'              => 'Zoning',
            'pool'                => 'Pool',
            'garage'              => 'Garage',
            'carport'             => 'Carport',
            'furnished'           => 'Furnished',
            'air_conditioning'    => 'A/C',
            'appliances'          => 'Appliances',
            'rent_includes'       => 'Rent Includes',
            'terms_of_lease'      => 'Terms of Lease',
            'tenant_pays'         => 'Tenant Pays',
            'description'         => 'Description',
            'tax_id'              => 'Tax / Parcel ID',
            'tax_year'            => 'Tax Year',
            'annual_taxes'        => 'Annual Property Taxes',
            'legal_description'   => 'Legal Description',
            'flood_zone_code'     => 'Flood Zone Code',
            'flood_zone_date'     => 'Flood Zone Date',
            'flood_zone_panel'    => 'Flood Zone Panel',
            'additional_parcels'  => 'Additional Parcels',
            'total_parcel_count'  => 'Total Parcel Count',
            'application_fee'     => 'Application Fee',
            'available_date'      => 'Available Date',
            'minimum_security_deposit' => 'Minimum Security Deposit',
            'lease_amount_frequency'   => 'Lease Amount Frequency',
            'address'             => 'Address',
            'city'                => 'City',
            'state'               => 'State',
            'zip'                 => 'ZIP',
            'county'              => 'County',
            'waterfront'          => 'Waterfront',
            'water_access'        => 'Water Access',
            'water_view'          => 'Water View',
            'heating'             => 'Heating',
            'interior_features'   => 'Interior Features',
            'directions'          => 'Directions',
            // HOA / CDD
            'has_hoa'                   => 'HOA / Association',
            'association_name'          => 'Association Name',
            'association_fee_amount'    => 'Association Fee',
            'association_fee_frequency' => 'Association Fee Frequency',
            'has_cdd'                   => 'Community Development District (CDD)',
            'annual_cdd_fee'            => 'CDD Annual Fee',
        ];
    }
}
