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
            // NOTE: 'price' maps to 'maximum_budget' (the "Desired Sale Price" input on
            //       Sale Terms tab, wire:model="maximum_budget"), NOT 'purchase_price'.
            //       purchase_price is a sub-field inside the Seller Financing section only.
            'price'           => 'maximum_budget',
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
            // ── Property characteristics ──────────────────────────────────────
            'heating_fuel'        => '*heating_and_fuel',
            'roof_type'           => '*roof_type',
            'exterior_construction' => '*exterior_construction',
            'foundation'          => '*foundation',
            'water'               => '*water',
            'sewer'               => '*sewer',
            'utilities'           => '*utilities',
            'sqft_heated_source'  => 'sqft_heated_source',
            // ── Tax / Legal / Flood Zone (owner-side disclosures) ─────────────
            'tax_id'          => 'parcel_id',
            'tax_year'        => 'tax_year',
            'annual_taxes'    => 'annual_property_taxes',
            'legal_description' => 'legal_description',
            'flood_zone_code' => 'flood_zone_code',
            'flood_zone_panel' => 'flood_zone_panel',
            'flood_insurance_required' => 'flood_insurance_required',
            'additional_parcels'  => 'additional_parcels',
            'total_parcel_count'  => 'total_parcel_count',
            // ── Special Assessments ───────────────────────────────────────────
            'has_special_assessments'       => 'has_special_assessments',
            'special_assessment_amount'     => 'special_assessment_amount',
            'special_assessment_description' => 'special_assessment_description',
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
            // legal_description, flood_zone_code, or owner-disclosure fields.
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
            // NOTE: 'address', 'city', 'state', 'zip', 'county' intentionally omitted —
            //       BuyerOfferListing uses a preference-based multi-city/county search
            //       model (newCity[], newCounty[], state), not a single address field.
            //       The blade files have no wire:model bindings for these properties
            //       on any buyer tab, so importing them would silently discard the data.
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
            'lot_size_acres'  => 'total_acreage',
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
            // ── Property characteristics ──────────────────────────────────────
            'heating_fuel'        => '*heating_fuel',
            'water'               => '*water',
            'sewer'               => '*sewer',
            // NOTE: LandlordOfferListing has two properties: $utilities (string, legacy)
            //       and $property_utilities (array, the multi-select). The MLS import
            //       targets the multi-select array property, hence the '*' prefix.
            'utilities'           => '*property_utilities',
            'sqft_heated_source'  => 'sqft_heated_source',
            // ── Tax / Legal / Flood Zone (owner-side disclosures) ─────────────
            'tax_id'          => 'parcel_id',
            'tax_year'        => 'tax_year',
            'annual_taxes'    => 'annual_property_taxes',
            'legal_description' => 'legal_description',
            'flood_zone_code' => 'flood_zone_code',
            'flood_zone_panel' => 'flood_zone_panel',
            'flood_insurance_required' => 'flood_insurance_required',
            'additional_parcels'  => 'additional_parcels',
            'total_parcel_count'  => 'total_parcel_count',
            // ── Special Assessments ───────────────────────────────────────────
            'has_special_assessments'       => 'has_special_assessments',
            'special_assessment_amount'     => 'special_assessment_amount',
            'special_assessment_description' => 'special_assessment_description',
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
            'sqft_heated_source' => 'sqft_heated_source',
            'pool'            => 'pool_needed',
            'garage'          => 'garage_needed',
            'carport'         => 'carport_needed',
            'furnished'       => 'tenant_require',
            'rent_includes'   => '*rent_includes',
            'description'     => 'additional_details',
            // ── Address fields ────────────────────────────────────────────────
            'address'         => 'address',
            'city'            => 'property_city',
            'state'           => 'property_state',
            'zip'             => 'property_zip',
            'county'          => 'property_county',
            // NOTE: 'price' intentionally omitted — see Rejected Mapping Candidates.
        ];
    }

    // ─── Universal base field map ─────────────────────────────────────────────

    /**
     * Eligibility map for the "Previewed (Y/N)" column in MlsCoverageReporter.
     *
     * Lists the canonical keys that are universally applicable across all (or most)
     * roles with no role-specific or property-type dependency.  These are fields
     * whose MLS values are meaningful regardless of listing type:
     * structural characteristics (beds/baths/sqft/pool/garage/carport/furnished),
     * the public description, and physical location (address through county).
     *
     * Role-specific keys (price, year_built, HOA, flood zone, lease terms, tax
     * disclosures, etc.) are intentionally absent — their values are either only
     * relevant once the role is known or they map to different Livewire properties
     * per role.
     *
     * *** PREVIEW / IMPORT MAPPING AUTHORITY ***
     * This map is NOT used to populate the import preview.  The role-specific
     * MlsFieldMap::forRole() is and must remain the sole authoritative source for
     * what appears in the preview and what gets written to component properties.
     * This map is consumed ONLY by MlsCoverageReporter::buildRows() to derive the
     * "Previewed (Y/N)" column value.
     */
    public static function universalBaseMap(): array
    {
        return [
            // Core structural fields — identical target property on all four roles
            'bedrooms'    => 'bedrooms',
            'bathrooms'   => 'bathrooms',
            'heated_sqft' => 'minimum_heated_square',
            'pool'        => 'pool_needed',
            'garage'      => 'garage_needed',
            'carport'     => 'carport_needed',
            'furnished'   => 'tenant_require',
            'description' => 'additional_details',
            // Address fields — identical target property on seller/landlord/tenant
            // (buyer is intentionally excluded from address mapping by design; the
            //  guard in importListingFromUrl remains: property_exists($this, $propName))
            'address'     => 'address',
            'city'        => 'property_city',
            'state'       => 'property_state',
            'zip'         => 'property_zip',
            'county'      => 'property_county',
        ];
    }

    /**
     * Returns just the canonical key names from the universal base map.
     *
     * @return string[]
     */
    public static function universalBaseKeys(): array
    {
        return array_keys(self::universalBaseMap());
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
            'heating_fuel'        => 'Heating & Fuel',
            'roof_type'           => 'Roof Type',
            'exterior_construction' => 'Exterior Construction',
            'foundation'          => 'Foundation',
            'water'               => 'Water',
            'sewer'               => 'Sewer',
            'utilities'           => 'Utilities',
            'sqft_heated_source'  => 'Sq Ft Heated Source',
            'flood_insurance_required' => 'Flood Insurance Required',
            'interior_features'   => 'Interior Features',
            'directions'          => 'Directions',
            // HOA / CDD
            'has_hoa'                   => 'HOA / Association',
            'association_name'          => 'Association Name',
            'association_fee_amount'    => 'Association Fee',
            'association_fee_frequency' => 'Association Fee Frequency',
            'has_cdd'                   => 'Community Development District (CDD)',
            'annual_cdd_fee'            => 'CDD Annual Fee',
            // Special Assessments
            'has_special_assessments'        => 'Special Assessments',
            'special_assessment_amount'      => 'Special Assessment Amount',
            'special_assessment_description' => 'Special Assessment Description',
        ];
    }
}
