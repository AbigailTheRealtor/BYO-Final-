@extends('layouts.main')
@section('content')
@php
    $d = $data;
    $role = $d['role'];
    $isSeller   = $role === 'seller';
    $isBuyer    = $role === 'buyer';
    $isLandlord = $role === 'landlord';
    $isTenant   = $role === 'tenant';

    $fmt = function($v) { if (is_array($v)) return count($v) ? implode(', ', $v) : null; return $v !== '' && $v !== null ? $v : null; };
    $fmtMoney = function($v) {
        if ($v === '' || $v === null) return null;
        $n = (float) str_replace(',', '', $v);
        return '$' . number_format($n, 2, '.', ',');
    };
    $fmtDate = function($v) {
        if (!$v) return null;
        try { return \Carbon\Carbon::parse($v)->format('F j, Y'); } catch(\Exception $e) { return $v; }
    };
    $fmtArr = fn($v) => (is_array($v) && count($v)) ? implode(', ', $v) : null;
    $fmtBool = fn($v) => in_array($v, [1, '1', 'true', true, 'yes', 'Yes'], true) ? 'Yes' : (in_array($v, [0, '0', 'false', false, 'no', 'No'], true) ? 'No' : null);

    $otLabels = ['sale' => 'Sale', 'rental' => 'Rental', 'lease' => 'Lease'];
    $ot = $d['offer_type'];
    $ofv = \App\Helpers\OfferListingViewHelper::class;

    /*
     * Meta fallback helper.
     *
     * Usage: $getMeta('some_key')
     *
     * Resolution order:
     *   1. $data array  – structured, already-decoded value (preferred)
     *   2. raw $meta    – full EAV payload from the DB; JSON-decoded automatically
     *
     * This lets new/unmapped meta keys surface in the view without any
     * controller changes, while keeping $data as the authoritative source
     * for every key that has already been explicitly mapped.
     */
    $getMeta = function(string $key, $default = null) use ($d, $meta) {
        if (array_key_exists($key, $d) && $d[$key] !== '' && $d[$key] !== null && $d[$key] !== [] && $d[$key] !== '[]') {
            return $d[$key];
        }
        $raw = $meta[$key] ?? null;
        if ($raw === null || $raw === '' || $raw === '[]' || $raw === '{}') return $default;
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return (is_array($decoded) && empty($decoded)) ? $default : $decoded;
        }
        return $raw;
    };
@endphp

<div class="container-fluid py-4" style="max-width:960px;">

    {{-- Breadcrumb & Header --}}
    <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <a href="{{ $d['hub_route'] }}" class="text-muted small text-decoration-none">
                <i class="fa-solid fa-arrow-left me-1"></i>My Offer Listings
            </a>
            <h4 class="fw-bold mb-0 mt-1">
                <i class="fa-solid fa-file-lines me-2" style="color:#049399;"></i>
                {{ $d['title'] ?: ('Offer Listing #' . $d['id']) }}
            </h4>
            <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                <code class="small" style="color:#049399;">{{ $d['listing_id'] }}</code>
                <span class="badge bg-{{ $d['status_class'] }}">{{ $d['status_label'] }}</span>
                @if($ot)
                <span class="badge bg-info text-dark">{{ $otLabels[$ot] ?? ucfirst($ot) }}</span>
                @endif
                <span class="badge bg-secondary text-capitalize">{{ $role }}</span>
            </div>
        </div>
        <a href="{{ $d['edit_route'] }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing
        </a>
    </div>

    {{-- Section 1: Listing Details --}}
    @php $ofv::section('fa-solid fa-list-check', 'Listing Details'); @endphp
    @php $ofv::row('Service Type', $fmt(ucwords(str_replace('_', ' ', $d['service_type'])))); @endphp
    @php $ofv::row('Auction / Listing Type', $fmt($d['auction_type'])); @endphp
    @php $ofv::row('Offer Type', $fmt($ot ? ($otLabels[$ot] ?? ucfirst($ot)) : null)); @endphp
    @php $ofv::row('Listing Date', $fmtDate($d['listing_date'])); @endphp
    @php $ofv::row('Desired Agent Hire Date', $fmtDate($d['desired_agent_hire_date'])); @endphp
    @php $ofv::row('Expiration Date', $fmtDate($d['expiration_date'] ?? $d['listing_expiration'])); @endphp
    @if($d['auction_type'] === 'Bidding Period' && $d['auction_time'])
    @php $ofv::row('Auction Time', $d['auction_time']); @endphp
    @endif
    @php $ofv::row('Currently Working With Agent', $fmt($d['working_with_agent'])); @endphp
    @php $ofv::row('Meeting Preference', $fmt($d['meeting_preference'])); @endphp
    @php $ofv::row('Agent Bid Visibility', $fmt($d['agent_bid_visibility'])); @endphp
    @php $ofv::row('Listing Status', $fmt($d['listing_status'])); @endphp
    @php $ofv::sectionEnd(); @endphp
    {{-- 2. PROPERTY DETAILS                                           --}}
    
    @php $ofv::section('fa-solid fa-home', 'Property Details'); @endphp

    {{-- Location --}}
    @if($d['property_address'] || $d['property_city'] || $d['property_state'])
    <div class="col-12">
        <div class="text-muted small mb-1">Property Location</div>
        <div class="fw-semibold">
            {{ $d['property_address'] ?: '' }}
            @if($d['property_city'] || $d['property_state'] || $d['property_zip'])
                <span class="text-muted fw-normal">{{ $d['property_address'] ? ', ' : '' }}{{ implode(', ', array_filter([$d['property_city'], $d['property_state'], $d['property_zip']])) }}</span>
            @endif
            @if($d['property_county'])
                <span class="text-muted fw-normal small d-block">{{ $d['property_county'] }} County</span>
            @endif
        </div>
    </div>
    @endif

    @if(!empty($d['cities']))
    @php $ofv::tags('Target Cities', $d['cities']); @endphp
    @endif
    @if(!empty($d['counties']))
    @php $ofv::tags('Target Counties', $d['counties']); @endphp
    @endif
    @if(!empty($d['zip_codes']))
    @php $ofv::tags('Target Zip Codes', $d['zip_codes']); @endphp
    @endif
    @if($d['state'] && empty($d['property_state']))
    @php $ofv::row('State', $d['state']); @endphp
    @endif

    @php $ofv::row('Property Type', $fmt(ucwords(str_replace('_', ' ', $d['property_type'])))); @endphp
    @if(!empty($d['property_items']))
    @php $ofv::tags('Property Subtype(s)', $d['property_items']); @endphp
    @endif
    @php $ofv::row('Other Property Type', $fmt($d['other_property_items'])); @endphp

    @if($isSeller || $isLandlord)
    @php $ofv::row('Property Condition', $fmt($d['condition_prop'])); @endphp
    @php $ofv::row('Other Condition', $fmt($d['other_property_condition'])); @endphp
    @endif
    @if($isBuyer || $isTenant)
    @if(!empty($d['condition_prop_buyer']))
    @php $ofv::tags('Desired Property Condition', $d['condition_prop_buyer']); @endphp
    @endif
    @endif

    @php $ofv::row('Bedrooms', $fmt($getMeta('bedrooms') ?? $getMeta('other_bedrooms'))); @endphp
    @php $ofv::row('Bathrooms', $fmt($getMeta('bathrooms') ?? $getMeta('other_bathrooms'))); @endphp
    @php $ofv::row('Min. Heated Sq Ft', $d['minimum_heated_square'] ? number_format((float)$d['minimum_heated_square']) . ' sq ft' : null); @endphp
    @php $ofv::row('Total Sq Ft', $d['total_square_feet'] ? number_format((float)$d['total_square_feet']) . ' sq ft' : null); @endphp
    @php $ofv::row('Sq Ft Source', $fmt($d['sqft_heated_source'])); @endphp
    @php $ofv::row('Min. Leaseable Sq Ft', $d['minimum_leaseable'] ? number_format((float)$d['minimum_leaseable']) . ' sq ft' : null); @endphp
    @php $ofv::row('Min. Acreage', $fmt($d['min_acreage'])); @endphp
    @php $ofv::row('Total Acreage', $fmt($d['total_acreage'])); @endphp
    @php $ofv::row('Lot Dimensions', $fmt($d['lot_dimensions'])); @endphp
    @php $ofv::row('Front Footage', $fmt($d['front_footage'])); @endphp
    @php $ofv::row('Year Built', $fmt($d['year_built'])); @endphp
    @php $ofv::row('Zoning', $fmt($d['zoning'])); @endphp

    @if($isSeller || $isLandlord)
    @php $ofv::row('Leasing Space', $fmt($d['leasing_space'])); @endphp
    @php $ofv::row('Leasing Space (Property)', $fmt($d['leasing_space_property'])); @endphp
    @php $ofv::row('Occupant Status', $fmt($d['occupant_status'])); @endphp
    @php $ofv::row('Occupant / Tenant', $fmt($d['occupant_tenant'])); @endphp
    @php $ofv::row('Occupancy Status', $fmt($d['occupancy_status'])); @endphp
    @php $ofv::row('Occupied Until', $fmtDate($d['occupied_until'])); @endphp
    @endif

    @if($isBuyer || $isTenant)
    @if(!empty($d['leasing_spaces_tenant']))
    @php $ofv::tags('Desired Leasing Space', $d['leasing_spaces_tenant']); @endphp
    @endif
    @php $ofv::row('Leasing Space', $fmt($d['leasing_space'])); @endphp
    @php $ofv::row('Other Leasing Spaces', $fmt($d['leasing_spaces'])); @endphp
    @php $ofv::row('55+ Community', $fmt($d['leasing_55_plus'])); @endphp
    @endif
    @if($isSeller || $isLandlord)
    @php $ofv::row('55+ Community', $fmt($d['leasing_55_plus'])); @endphp
    @endif

    {{-- Garage / Parking / Pool / Views --}}
    @php $ofv::row('Garage', $fmt($d['garage_needed'])); @endphp
    @php $ofv::row('Other Garage', $fmt($d['other_garage_needed'])); @endphp
    @php $ofv::row('Garage Spaces', $fmt($d['garage_parking_spaces'])); @endphp
    @php $ofv::row('Garage Spaces Option', $fmt($getMeta('garage_parking_spaces_option') ?? $getMeta('garage_parking_spaces_option_buyer'))); @endphp
    @php $ofv::row('Carport', $fmt($d['carport_needed'])); @endphp
    @php $ofv::row('Other Carport', $fmt($d['other_carport_needed'])); @endphp
    @php $ofv::row('Parking', $fmt($d['parking_needed'])); @endphp
    @php $ofv::row('Pool', $fmt($d['pool_needed'])); @endphp
    @if(!empty($d['pool_type']))
    @php $ofv::tags('Pool Type', $d['pool_type']); @endphp
    @endif
    @if(!empty($d['view_preference']))
    @php $ofv::tags('View Preferences', $d['view_preference']); @endphp
    @endif
    @php $ofv::row('Other Preferences', $fmt($d['other_preferences'])); @endphp

    @if(!empty($d['appliances']))
    @php $ofv::tags('Appliances', $d['appliances']); @endphp
    @endif
    @php $ofv::row('Other Appliances', $fmt($getMeta('other_appliances') ?? $getMeta('appliances_other'))); @endphp
    @if(!empty($d['non_negotiable_amenities']))
    @php $ofv::tags('Non-Negotiable Amenities', $d['non_negotiable_amenities']); @endphp
    @endif
    @php $ofv::row('Other Required Amenities', $fmt($d['other_non_negotiable_amenities'])); @endphp
    @php $ofv::row('Property Criteria', $fmt($d['property_criteria'])); @endphp
    @php $ofv::row('Unit Size', $fmt($d['unit_size'])); @endphp
    @php $ofv::row('Other Unit Size', $fmt($d['unit_size_other'])); @endphp
    @php $ofv::row('Budget', $d['budget'] ? '$' . number_format((float)str_replace(',', '', $d['budget'])) : null); @endphp
    @php $ofv::row('Preference Details', $fmt($d['preferance_details'])); @endphp

    {{-- MLS / Property Systems (seller/landlord) --}}
    @if($isSeller || $isLandlord || $isTenant)
    @if(!empty($d['roof_type'])) @php $ofv::tags('Roof Type', $d['roof_type']); @endphp @endif
    @php $ofv::row('Other Roof Type', $fmt($d['other_roof_type'])); @endphp
    @if(!empty($d['exterior_construction'])) @php $ofv::tags('Exterior Construction', $d['exterior_construction']); @endphp @endif
    @php $ofv::row('Other Exterior Construction', $fmt($d['other_exterior_construction'])); @endphp
    @if(!empty($d['foundation'])) @php $ofv::tags('Foundation', $d['foundation']); @endphp @endif
    @php $ofv::row('Other Foundation', $fmt($d['other_foundation'])); @endphp
    @if(!empty($d['heating_and_fuel'])) @php $ofv::tags('Heating & Fuel', $d['heating_and_fuel']); @endphp @endif
    @php $ofv::row('Other Heating & Fuel', $fmt($getMeta('other_heating_and_fuel') ?? $getMeta('other_heating_fuel'))); @endphp
    @if(!empty($d['heating_fuel'])) @php $ofv::tags('Heating Fuel', $d['heating_fuel']); @endphp @endif
    @if(!empty($d['air_conditioning'])) @php $ofv::tags('Air Conditioning', $d['air_conditioning']); @endphp @endif
    @php $ofv::row('Other Air Conditioning', $fmt($d['other_air_conditioning'])); @endphp
    @if(!empty($d['floor_covering'])) @php $ofv::tags('Floor Covering', $d['floor_covering']); @endphp @endif
    @php $ofv::row('Other Floor Covering', $fmt($d['other_floor_covering'])); @endphp
    @if(!empty($d['laundry_features'])) @php $ofv::tags('Laundry Features', $d['laundry_features']); @endphp @endif
    @php $ofv::row('Other Laundry Features', $fmt($d['other_laundry_features'])); @endphp
    @if(!empty($d['security_features'])) @php $ofv::tags('Security Features', $d['security_features']); @endphp @endif
    @php $ofv::row('Other Security Features', $fmt($d['other_security_features'])); @endphp
    @php $ofv::row('Bathroom Facilities', $fmt($d['bathroom_facilities'])); @endphp
    @if(!empty($d['water'])) @php $ofv::tags('Water', $d['water']); @endphp @endif
    @php $ofv::row('Other Water', $fmt($d['other_water'])); @endphp
    @if(!empty($d['sewer'])) @php $ofv::tags('Sewer', $d['sewer']); @endphp @endif
    @php $ofv::row('Other Sewer', $fmt($d['other_sewer'])); @endphp
    @if(!empty($d['utilities'])) @php $ofv::tags('Utilities', $d['utilities']); @endphp @endif
    @php $ofv::row('Other Utilities', $fmt($d['other_utilities'])); @endphp
    @if(!empty($d['property_utilities'])) @php $ofv::tags('Property Utilities', $d['property_utilities']); @endphp @endif
    @php $ofv::row('Other Property Utilities', $fmt($d['other_property_utilities'])); @endphp
    @if(!empty($d['road_frontage'])) @php $ofv::tags('Road Frontage', $d['road_frontage']); @endphp @endif
    @php $ofv::row('Other Road Frontage', $fmt($d['other_road_frontage'])); @endphp
    @if(!empty($d['road_surface_type'])) @php $ofv::tags('Road Surface Type', $d['road_surface_type']); @endphp @endif
    @php $ofv::row('Other Road Surface Type', $fmt($d['other_road_surface_type'])); @endphp
    @if(!empty($d['electrical_service'])) @php $ofv::tags('Electrical Service', $d['electrical_service']); @endphp @endif
    @php $ofv::row('Other Electrical Service', $fmt($d['other_electrical_service'])); @endphp
    @php $ofv::row('Ceiling Height', $fmt($d['ceiling_height'])); @endphp
    @if(!empty($d['building_features'])) @php $ofv::tags('Building Features', $d['building_features']); @endphp @endif
    @php $ofv::row('Other Building Features', $fmt($getMeta('other_building_features') ?? $getMeta('other_building_features_txt'))); @endphp
    @php $ofv::row('Water Meters', $fmt($d['number_water_meters'])); @endphp
    @php $ofv::row('Electric Meters', $fmt($d['number_electric_meters'])); @endphp
    @php $ofv::row('Gas Meters', $fmt($d['number_gas_meters'])); @endphp
    @if(!empty($d['current_use'])) @php $ofv::tags('Current Use', $d['current_use']); @endphp @endif
    @php $ofv::row('Other Current Use', $fmt($d['other_current_use'])); @endphp
    @if(!empty($d['current_adjacent_use'])) @php $ofv::tags('Adjacent Use', $d['current_adjacent_use']); @endphp @endif
    @php $ofv::row('Other Adjacent Use', $fmt($d['other_current_adjacent_use'])); @endphp
    @if(!empty($d['fences'])) @php $ofv::tags('Fences', $d['fences']); @endphp @endif
    @php $ofv::row('Other Fences', $fmt($d['other_fences'])); @endphp
    @if(!empty($d['vegetation'])) @php $ofv::tags('Vegetation', $d['vegetation']); @endphp @endif
    @php $ofv::row('Other Vegetation', $fmt($d['other_vegetation'])); @endphp
    @php $ofv::row('Buildable', $fmt($d['buildable'])); @endphp
    @if(!empty($d['easements'])) @php $ofv::tags('Easements', $d['easements']); @endphp @endif
    @php $ofv::row('Other Easements', $fmt($d['other_easements'])); @endphp
    @php $ofv::row('Number of Wells', $fmt($d['number_of_wells'])); @endphp
    @php $ofv::row('Number of Septics', $fmt($d['number_of_septics'])); @endphp
    @php $ofv::row('Building Hours', $fmt($d['building_hours'])); @endphp
    @php $ofv::badge('24/7 Access', $fmtBool($d['access_24_7'])); @endphp
    @php $ofv::row('Room Size', $fmt($d['room_size'])); @endphp
    @php $ofv::badge('Storage Included', $fmtBool($d['included_storage_space'])); @endphp
    @php $ofv::row('Storage Space', $fmt($d['storage_space'])); @endphp
    @php $ofv::badge('Incl. Storage (Residential Both)', $fmtBool($d['included_storage_space_res_both'])); @endphp
    @php $ofv::row('Storage Details (Res. Both)', $fmt($d['storage_space_res_both'] ?? '')); @endphp
    @php $ofv::badge('Incl. Storage (Residential Single)', $fmtBool($d['included_storage_space_res_single'])); @endphp
    @php $ofv::row('Storage Details (Res. Single)', $fmt($d['storage_space_res_single'] ?? '')); @endphp
    @php $ofv::badge('Incl. Storage (Commercial Entire)', $fmtBool($d['included_storage_space_com_entire'])); @endphp
    @php $ofv::row('Storage Details (Com. Entire)', $fmt($d['storage_space_com_entire'] ?? '')); @endphp
    @php $ofv::badge('Incl. Storage (Commercial Single)', $fmtBool($d['included_storage_space_com_single'])); @endphp
    @php $ofv::row('Storage Details (Com. Single)', $fmt($d['storage_space_com_single'] ?? '')); @endphp
    @endif
    @if($isLandlord || $isTenant)
    {{-- Commercial space details --}}
    @php $ofv::row('Space Type', $fmtArr($d['space_type']) ?? $fmt(is_string($d['space_type']) ? $d['space_type'] : null)); @endphp
    @php $ofv::row('Other Space Type', $fmt($d['other_space_type'])); @endphp
    @php $ofv::row('Space Classification', $fmtArr($d['space_classification']) ?? $fmt(is_string($d['space_classification']) ? $d['space_classification'] : null)); @endphp
    @php $ofv::row('Other Space Classification', $fmt($d['other_space_classification'])); @endphp
    @if(!empty($d['space_features'])) @php $ofv::tags('Space Features', $d['space_features']); @endphp @endif
    @php $ofv::row('Office / Retail Sq Ft', $d['office_retail_sqft'] ? number_format((float)$d['office_retail_sqft']) . ' sq ft' : null); @endphp
    @php $ofv::row('Flex Space Sq Ft', $d['flex_space_sqft'] ? number_format((float)$d['flex_space_sqft']) . ' sq ft' : null); @endphp
    @php $ofv::row('Conference Rooms', $fmt($d['number_of_conference_rooms'])); @endphp
    @php $ofv::row('Offices', $fmt($d['number_of_offices'])); @endphp
    @php $ofv::row('Restrooms', $fmt($d['number_of_restrooms'])); @endphp
    @php $ofv::row('Total Buildings', $fmt($d['total_buildings'])); @endphp
    @php $ofv::row('Total Units on Property', $fmt($d['total_units_on_property'])); @endphp
    @php $ofv::row('Neighboring Tenants', $fmt($d['neighboring_tenants'])); @endphp
    @php $ofv::row('Shared Amenities', $fmt($d['shared_amenities'])); @endphp
    @php $ofv::row('Common Areas Access', $fmt($d['common_areas_access'])); @endphp
    @php $ofv::row('Common Areas Cleaning', $fmt($d['common_areas_cleaning'])); @endphp
    @endif
    @if(!empty($d['zoning_allows']))
    @php $ofv::row('Zoning Allows', $fmt($d['zoning_allows'])); @endphp
    @endif
    @if(!empty($d['additional_parcel_ids']))
    @php $ofv::row('Additional Parcel IDs', $fmt($d['additional_parcel_ids'])); @endphp
    @endif
    @if(!empty($d['additional_parcels']))
    @php $ofv::row('Additional Parcels', $fmt($d['additional_parcels'])); @endphp
    @endif
    @php $ofv::row('Total Parcel Count', $fmt($d['total_parcel_count'])); @endphp

    {{-- Multi-unit / Income Property Details --}}
    @if($d['real_estate_purchase'])
    @php $ofv::row('Real Estate Purchase', $fmt($d['real_estate_purchase'])); @endphp
    @endif
    @php $ofv::row('Number of Units', $fmt($getMeta('number_of_unit') ?? $getMeta('number_of_units') ?? $getMeta('unit_number'))); @endphp
    @php $ofv::row('Other No. of Units', $fmt($d['number_of_unit_other'])); @endphp
    @if(!empty($d['number_of_unit_type'])) @php $ofv::tags('No. of Unit Types', $d['number_of_unit_type']); @endphp @endif
    @php $ofv::row('Other Unit Type', $fmt($d['number_of_unit_type_other'])); @endphp
    @php $ofv::row('Unit Number', $fmt($d['unit_number'])); @endphp
    @php $ofv::row('Unit Buildings', $fmt($d['unit_buildings'])); @endphp

    @php $hasValidUnitRows = !empty($d['unit_type_configurations']) && collect($d['unit_type_configurations'])->contains(fn($uc) => is_array($uc) && !empty(array_filter($uc, fn($v) => $v !== '' && $v !== null))); @endphp
    @if($hasValidUnitRows)
    <div class="col-12">
        <div class="text-muted small mb-1">Unit Type Configurations</div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 small">
                <thead class="table-light"><tr><th>Beds</th><th>Baths</th><th>Sq Ft</th><th>Expected Rent</th><th>Count</th><th>Other Spaces</th></tr></thead>
                <tbody>
                @foreach($d['unit_type_configurations'] as $uc)
                    @if(is_array($uc))
                    <tr>
                        <td>{{ $uc['beds_unit'] ?? '-' }}</td>
                        <td>{{ $uc['baths_unit'] ?? '-' }}</td>
                        <td>{{ isset($uc['total_square_feet']) ? number_format((float)$uc['total_square_feet']) : '-' }}</td>
                        <td>{{ isset($uc['expected_rent']) && $uc['expected_rent'] ? '$'.number_format((float)$uc['expected_rent']) : '-' }}</td>
                        <td>{{ $uc['number_occupied'] ?? '-' }}</td>
                        <td>{{ $uc['other_spaces'] ?? '-' }}</td>
                    </tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Business property fields --}}
    @if($d['business_name'])
    @php $ofv::row('Business Name', $d['business_name']); @endphp
    @php $ofv::row('Year Established', $fmt($d['year_established'])); @endphp
    @endif
    @if(!empty($d['licenses'])) @php $ofv::tags('Licenses', $d['licenses']); @endphp @endif
    @php $ofv::row('Other Licenses', $fmt($d['other_licenses'])); @endphp
    @if(!empty($d['sale_includes'])) @php $ofv::tags('Sale Includes', $d['sale_includes']); @endphp @endif
    @php $ofv::row('Other Sale Includes', $fmt($d['other_sale_includes'])); @endphp
    @if(!empty($d['business_assets'])) @php $ofv::tags('Business Assets', $d['business_assets']); @endphp @endif
    @php $ofv::row('Other Business Type', $fmt($d['other_business_type'])); @endphp
    @if(!empty($d['assets'])) @php $ofv::tags('Assets', $d['assets']); @endphp @endif
    @php $ofv::row('Other Assets', $fmt($d['assets_other'])); @endphp

    @php $ofv::sectionEnd(); @endphp
    {{-- 3. SALE / PURCHASE / LEASE TERMS (role-specific)             --}}
    

    @if($isSeller)
    {{-- Seller Sale Terms --}}
    @php $ofv::section('fa-solid fa-handshake', 'Sale Terms'); @endphp
    @if(!empty($d['sale_provision'])) @php $ofv::tags('Sale Provisions', $d['sale_provision']); @endphp @endif
    @php $ofv::row('Other Sale Provisions', $fmt($d['sale_provision_other'])); @endphp
    @php $ofv::row('Assignment Contract', $fmt($d['sale_provision_assignment'])); @endphp
    @if($d['sale_provision_assignment'] === 'Yes' || $d['assignment_fee_amount'])
    @php $ofv::row('Assignment Fee', $d['assignment_fee_amount'] ? ($d['assignment_fee_type'] ?: '$') . number_format((float)str_replace(',', '', $d['assignment_fee_amount'])) : null); @endphp
    @php $ofv::row('Buyer to Sell Contract', $fmt($d['buyer_sell_contract'])); @endphp
    @endif
    @php $ofv::row('Asking / Starting Price', $d['starting_price'] ? '$' . number_format((float)str_replace(',', '', $d['starting_price'])) : null); @endphp
    @php $ofv::row('Reserve Price', $d['reserve_price'] ? '$' . number_format((float)str_replace(',', '', $d['reserve_price'])) : null); @endphp
    @php $ofv::row('Buy Now Price', $d['buy_now_price'] ? '$' . number_format((float)str_replace(',', '', $d['buy_now_price'])) : null); @endphp
    @php $ofv::row('Maximum Budget', $d['maximum_budget'] ? '$' . number_format((float)str_replace(',', '', $d['maximum_budget'])) : null); @endphp
    @php $ofv::row('Target Closing Date', $fmt($d['target_closing_date'])); @endphp
    @php $ofv::row('Occupant Status', $fmt($d['occupant_status'])); @endphp
    {{-- Seller Sale Terms Questions --}}
    @php $ofv::row('Purchase Type', $fmt($d['purchase_type'])); @endphp
    @php $ofv::row('Initial Deposit Requested', $fmt($d['initial_deposit_requested'])); @endphp
    @php $ofv::row('Additional Deposit Requested', $fmt($d['additional_deposit_requested'])); @endphp
    @php $ofv::row('Initial Deposit Timeframe', $fmt($d['initial_deposit_timeframe'])); @endphp
    @php $ofv::row('Initial Deposit Timeframe (Other)', $fmt($d['initial_deposit_timeframe_other'])); @endphp
    @php $ofv::row('Additional Deposit Timeframe', $fmt($d['additional_deposit_timeframe'])); @endphp
    @php $ofv::row('Additional Deposit Timeframe (Other)', $fmt($d['additional_deposit_timeframe_other'])); @endphp
    @php $ofv::row('Escrow Agent Preference', $fmt($d['escrow_agent_preference'])); @endphp
    @php $ofv::row('Preferred Inspection Period', $d['preferred_inspection_period'] ? $d['preferred_inspection_period'] . ' days' : null); @endphp
    @php $ofv::row('Appraisal Contingency Preference', $fmt($d['appraisal_contingency_preference'])); @endphp
    @php $ofv::row('Financing Contingency Preference', $fmt($d['financing_contingency_preference'])); @endphp
    @php $ofv::row('Sale of Buyer\'s Property Contingency', $fmt($d['sale_of_buyer_property_contingency'])); @endphp
    @php $ofv::badge('Seller Contribution / Credit Offered', $fmtBool($d['seller_contribution_credit_offered'])); @endphp
    @php $ofv::row('Seller Contribution Details', $fmt($d['seller_contribution_amount_details'])); @endphp
    @php $ofv::row('Possession Preference', $fmt($d['possession_preference'])); @endphp
    @php $ofv::row('Possession Details', $fmt($d['possession_details'])); @endphp
    @php $ofv::row('Included Personal Property', $fmt($d['included_personal_property'])); @endphp
    @php $ofv::row('Excluded Items', $fmt($d['excluded_items'])); @endphp
    @php $ofv::badge('Home Warranty Offered', $fmtBool($d['home_warranty_offered'])); @endphp
    @php $ofv::row('Home Warranty Details', $fmt($d['home_warranty_amount_details'])); @endphp
    @php $ofv::row('HOA / Condo Association Terms', $fmt($d['hoa_condo_association_terms'])); @endphp
    @php $ofv::row('Additional Sale Terms', $fmt($d['additional_seller_sale_terms'])); @endphp
    @php $ofv::sectionEnd(); @endphp
    @endif

    @if($isBuyer)
    {{-- Buyer Purchase Terms --}}
    @php $ofv::section('fa-solid fa-circle-dollar-to-slot', 'Purchase Terms & Budget'); @endphp
    @if(!empty($d['sale_provision'])) @php $ofv::tags('Purchase Type', $d['sale_provision']); @endphp @endif
    @php $ofv::row('Assignment Interest', $fmt($d['sale_provision_assignment'])); @endphp
    @if($d['assignment_fee_amount'])
    @php $ofv::row('Assignment Fee', ($d['assignment_fee_type'] ?: '$') . number_format((float)str_replace(',', '', $d['assignment_fee_amount']))); @endphp
    @endif
    @php $ofv::row('Maximum Budget', $d['maximum_budget'] ? '$' . number_format((float)str_replace(',', '', $d['maximum_budget'])) : null); @endphp
    @php $ofv::row('Purchase Price', $d['purchase_price'] ? '$' . number_format((float)str_replace(',', '', $d['purchase_price'])) : null); @endphp
    @php $ofv::row('Target Closing Date', $fmt($d['target_closing_date'])); @endphp
    @php
        $emdType = $getMeta('earnest_money_type') ?: '$';
        $emdAmt = $d['earnest_money_amount'] ?? null;
        if ($emdAmt) {
            $emdVal = (float)str_replace(',', '', $emdAmt);
            $emdDisplay = ($emdType === '%')
                ? number_format($emdVal, 2, '.', ',') . '%'
                : '$' . number_format($emdVal);
        } else {
            $emdDisplay = null;
        }
        $ofv::row('Earnest Money', $emdDisplay);
    @endphp
    @php $ofv::row('Earnest Money Timing', $fmt($d['earnest_money_timing'])); @endphp
    @php
        // Due Diligence / Inspection — primary: due_diligence_yn meta key (new flow).
        // Legacy fallback: infer "Yes" from inspection_period_days for older records.
        $dueDiligenceYN = $getMeta('due_diligence_yn') ?: ($d['inspection_period_days'] ? 'Yes' : null);
        $ofv::row('Due Diligence / Inspection', $fmt($dueDiligenceYN));

        // Inspection Period Duration — primary: inspection_period_other (custom text from new flow).
        // Secondary: inspection_period_days numeric value (new flow native column).
        // Legacy: inspection_period_days is also the legacy column used by old records.
        $inspPeriodOther = $getMeta('inspection_period_other') ?: null;
        $inspPeriodDays  = $d['inspection_period_days'] ?: null;
        // Only append " days" when the stored value is purely numeric (legacy integer column).
        // New-flow dropdown values like "5 Days" or "Negotiable" are stored as text and rendered as-is.
        $inspDisplay = $inspPeriodOther ?: ($inspPeriodDays
            ? (is_numeric($inspPeriodDays) ? $inspPeriodDays . ' days' : $inspPeriodDays)
            : null);
        if ($inspDisplay) { $ofv::row('Inspection Period Duration', $fmt($inspDisplay)); }
    @endphp
    @php $ofv::badge('Inspection Contingency', $fmtBool($d['inspection_contingency_buyer'])); @endphp
    @php
        // Appraisal Contingency — new Yes/No field; render days when Yes
        $appraisalVal = $d['appraisal_contingency_buyer'] ?? null;
        $ofv::badge('Appraisal Contingency', $fmtBool($appraisalVal));
        $appraisalDays = $getMeta('appraisal_contingency_days') ?: null;
        if ($appraisalVal === 'Yes' && $appraisalDays) {
            $ofv::row('Appraisal Contingency Period', $appraisalDays . ' days');
        }
    @endphp
    @php $ofv::badge('Financing Contingency', $fmtBool($d['financing_contingency_buyer'])); @endphp
    @php $ofv::row('Financing Contingency Days', $d['financing_contingency_days_buyer'] ? $d['financing_contingency_days_buyer'] . ' days' : null); @endphp
    @php $ofv::badge('Seller Contribution', $fmtBool($d['seller_contribution'])); @endphp
    @php $ofv::row('Seller Contribution Details', $fmt($d['seller_contribution_details'])); @endphp
    @php
        $possessionDisplay = ($d['possession_preference'] === 'Other')
            ? ($getMeta('possession_preference_other') ?: null)
            : $d['possession_preference'];
        $ofv::row('Possession Preference', $fmt($possessionDisplay));
    @endphp
    @php $ofv::row('Possession Details', $fmt($d['possession_details'])); @endphp
    @php $ofv::badge('Home Warranty Requested', $fmtBool($d['home_warranty_requested'])); @endphp
    @php $ofv::row('Home Warranty Details', $fmt($d['home_warranty_details'])); @endphp
    @php $ofv::badge('As-Is Purchase', $fmtBool($d['as_is_purchase'])); @endphp
    @php $ofv::row('Property Inclusions', $fmt($d['property_inclusions'])); @endphp
    @php $ofv::row('Property Exclusions', $fmt($d['property_exclusions'])); @endphp
    @php $ofv::row('Closing Cost Responsibility', $fmt($d['closing_cost_responsibility'])); @endphp
    @php $ofv::row('Additional Purchase Terms', $fmt($d['additional_purchase_terms'])); @endphp
    @php $ofv::sectionEnd(); @endphp
    @endif

    @if($isLandlord)
    {{-- Landlord Lease Terms --}}
    @php $ofv::section('fa-solid fa-file-signature', 'Lease Terms'); @endphp
    @php $ofv::row('Desired Rental Amount', $d['desired_rental_amount'] ? '$' . number_format((float)str_replace(',', '', $d['desired_rental_amount'])) : null); @endphp
    @php $ofv::row('Starting Rent', $d['starting_rent'] ? '$' . number_format((float)str_replace(',', '', $d['starting_rent'])) : null); @endphp
    @php $ofv::row('Reserve Rent', $d['reserve_rent'] ? '$' . number_format((float)str_replace(',', '', $d['reserve_rent'])) : null); @endphp
    @php $ofv::row('Lease Now Price', $d['lease_now_price'] ? '$' . number_format((float)str_replace(',', '', $d['lease_now_price'])) : null); @endphp
    @php $ofv::row('Rent Frequency', $fmt($d['lease_amount_frequency'])); @endphp
    @if(!empty($d['desired_lease_length'])) @php $ofv::tags('Desired Lease Length', $d['desired_lease_length']); @endphp @endif
    @php $ofv::row('Lease Type', $fmt($d['lease_type'])); @endphp
    @php $ofv::row('Other Lease Type', $fmt($getMeta('lease_type_other') ?? $getMeta('other_lease_type'))); @endphp
    @php $ofv::row('Custom Lease Term', $fmt($d['custom_lease_term'])); @endphp
    @if(!empty($d['rent_includes'])) @php $ofv::tags('Rent Includes', $d['rent_includes']); @endphp @endif
    @php $ofv::row('Other Rent Includes', $fmt($d['other_rent_include'])); @endphp
    @if(!empty($d['terms_of_lease'])) @php $ofv::tags('Lease Terms', $d['terms_of_lease']); @endphp @endif
    @if(!empty($d['tenant_pays'])) @php $ofv::tags('Tenant Pays', $d['tenant_pays']); @endphp @endif
    @php $ofv::row('Other Tenant Pays', $fmt($getMeta('tenant_pays_other') ?? $getMeta('other_tenant_pays'))); @endphp
    @if(!empty($d['owner_pays'])) @php $ofv::tags('Owner Pays', $d['owner_pays']); @endphp @endif
    @php $ofv::row('Other Owner Pays', $fmt($getMeta('owner_pays_other') ?? $getMeta('other_owner_pays'))); @endphp
    @php $ofv::row('Occupant Types', $fmt($d['occupant_types'])); @endphp
    @php $ofv::row('Occupant Types (Tenant)', $fmt($d['occupant_types_tenant'])); @endphp
    @php $ofv::row('Lease Available Date', $fmtDate($d['lease_available_date'])); @endphp
    @php $ofv::row('Security Deposit Amount', $fmt($d['security_deposit_amount'] ?: $d['security_deposit_required'])); @endphp
    @php $ofv::badge('Last Month Rent Required', $fmtBool($d['last_month_rent_required'])); @endphp
    @php $ofv::row('Total Move-In Funds Required', $d['total_move_in_funds_required'] ? '$' . number_format((float)str_replace(',', '', $d['total_move_in_funds_required'])) : null); @endphp
    @php $ofv::row('Pet Policy', $fmt($d['pet_policy'])); @endphp
    @php $ofv::row('Pet Deposit / Fee', $fmt($d['pet_deposit_fee_rent'])); @endphp
    @php $ofv::row('Pet Deposit Amount', $fmtMoney($getMeta('pet_deposit_amount'))); @endphp
    @php $ofv::row('Pet Monthly Fee', $fmtMoney($getMeta('pet_monthly_fee'))); @endphp
    @php $ofv::row('Pet Rent', $fmtMoney($getMeta('pet_rent'))); @endphp
    @php $ofv::row('Pet Fee', $fmtMoney($getMeta('pet_fee'))); @endphp
    @php $ofv::row('Pet Information', $fmt($d['pet_information'])); @endphp
    @php $ofv::badge('Guests Allowed', $fmtBool($d['guests_allowed'])); @endphp
    @php $ofv::row('Max Occupants Allowed', $fmt($d['number_of_occupants_allowed'])); @endphp
    @php $ofv::row('Parking Terms', $fmt($d['parking_terms'])); @endphp
    @php $ofv::row('Other Parking', $fmt($d['other_parking_space_wrapper'])); @endphp
    @php $ofv::row('Maintenance Responsibility', $fmt($d['ll_maintenance_responsibility'])); @endphp
    @php $ofv::row('Maintenance Handler', $fmt($d['maintenance_handler'])); @endphp
    @php $ofv::row('Maintenance Response Time', $fmt($d['maintenance_response_time'])); @endphp
    @php $ofv::badge('Renewal Option', $fmtBool($d['renewal_option_offered'])); @endphp
    @php $ofv::row('Renewal Option Details', $fmt($d['renewal_option_details'])); @endphp
    @php $ofv::row('Restrictions', $fmt($d['restrictions'])); @endphp
    @php $ofv::row('Approval Conditions', $fmt($d['landlord_approval_conditions'])); @endphp
    {{-- Commercial lease specifics --}}
    @php $ofv::row('Commercial Lease Type', $fmt($d['commercial_lease_type'])); @endphp
    @php $ofv::row('Other Commercial Lease Type', $fmt($d['commercial_lease_type_other'])); @endphp
    @php $ofv::row('CAM / NNN Rent Charges', $fmt($d['cam_nnn_additional_rent_charges'])); @endphp
    @php $ofv::row('Gross % Rent', $d['gross_percentage_rent'] ? $d['gross_percentage_rent'] . '%' : null); @endphp
    @php $ofv::row('Net Aggregate Rent', $d['net_aggregate_rent'] ? '$' . number_format((float)str_replace(',', '', $d['net_aggregate_rent'])) : null); @endphp
    @php $ofv::row('Monthly % Rent', $fmt($d['month_percentage_rent'])); @endphp
    @php $ofv::row('No. of Months', $fmt($d['no_of_months'])); @endphp
    @php $ofv::row('Commercial Parking Terms', $fmt($d['commercial_parking_terms'])); @endphp
    @php $ofv::row('Commercial Approval Conditions', $fmt($d['commercial_approval_conditions'])); @endphp
    @php $ofv::badge('Signage Rights', $fmtBool($d['signage_rights'])); @endphp
    @php $ofv::row('Permitted Use / Restrictions', $fmt($d['permitted_use_restrictions'])); @endphp
    @php $ofv::row('Rent Escalation Terms', $fmt($d['rent_escalation_terms'])); @endphp
    @php $ofv::row('Buildout / Tenant Improvement', $fmt($getMeta('buildout_tenant_improvement_request') ?? $getMeta('tenant_improvement_buildout_terms'))); @endphp
    @php $ofv::row('Personal Guarantee', $fmt($d['personal_guarantee_requirement'])); @endphp
    @php $ofv::row('Split Payment Due', $fmt($d['split_payment_due'])); @endphp
    @php $ofv::row('Split Payment Due (Other)', $fmt($d['split_payment_due_other'])); @endphp
    @php $ofv::row('Maintenance By', $fmt($d['maintenance_by'])); @endphp
    @php $ofv::row('Other Lease Term', $fmt($d['other_lease_term'])); @endphp
    @php $ofv::row('Additional Lease Terms', $fmt($d['additional_landlord_lease_terms'])); @endphp
    @php $ofv::sectionEnd(); @endphp
    @endif

    @if($isTenant)
    {{-- Tenant Desired Lease Terms --}}
    @php $ofv::section('fa-solid fa-key', 'Desired Lease Terms'); @endphp
    @php $ofv::row('Desired Rent', $d['desired_rent'] ? '$' . number_format((float)str_replace(',', '', $d['desired_rent'])) : ($d['desired_rental_amount_tenant'] ? '$' . number_format((float)str_replace(',', '', $d['desired_rental_amount_tenant'])) : null)); @endphp
    @php $ofv::row('Maximum Budget', $d['maximum_budget'] ? '$' . number_format((float)str_replace(',', '', $d['maximum_budget'])) : ($d['budget'] ? '$' . number_format((float)str_replace(',', '', $d['budget'])) : null)); @endphp
    @php $ofv::row('Security Deposit Budget', $d['security_deposit_budget'] ? '$' . number_format((float)str_replace(',', '', $d['security_deposit_budget'])) : null); @endphp
    @php $ofv::row('Rent Frequency', $fmt($d['lease_amount_frequency'])); @endphp
    @if(!empty($d['desired_lease_length'])) @php $ofv::tags('Desired Lease Length', $d['desired_lease_length']); @endphp @endif
    @php $ofv::row('Tenant Desired Lease Length', $fmt($d['tenant_desired_lease_length'])); @endphp
    @php $ofv::row('Lease Type', $fmt($d['lease_type'])); @endphp
    @php $ofv::row('Other Lease Type', $fmt($d['lease_type_other'])); @endphp
    @php $ofv::row('Custom Lease Term', $fmt($d['custom_lease_term'])); @endphp
    @if(!empty($d['rent_includes'])) @php $ofv::tags('Utilities/Items Included in Rent', $d['rent_includes']); @endphp @endif
    @php $ofv::row('Other Rent Includes', $fmt($d['other_rent_include'])); @endphp
    @if(!empty($d['terms_of_lease'])) @php $ofv::tags('Lease Terms', $d['terms_of_lease']); @endphp @endif
    @if(!empty($d['tenant_pays'])) @php $ofv::tags('Tenant Pays', $d['tenant_pays']); @endphp @endif
    @php $ofv::row('Other Tenant Pays', $fmt($getMeta('tenant_pays_other') ?? $getMeta('other_tenant_pays'))); @endphp
    @if(!empty($d['owner_pays'])) @php $ofv::tags('Owner Pays', $d['owner_pays']); @endphp @endif
    @php $ofv::row('Other Owner Pays', $fmt($getMeta('owner_pays_other') ?? $getMeta('other_owner_pays'))); @endphp
    @if(!empty($d['lease_for'])) @php $ofv::tags('Lease For', $d['lease_for']); @endphp @endif
    @php $ofv::row('Other Lease For', $fmt($d['other_lease_for'])); @endphp
    @php $ofv::row('Move-In Date', $fmtDate($d['lease_date'])); @endphp
    @php $ofv::row('Desired Move-In By', $fmtDate($d['lease_by'])); @endphp
    @php $ofv::badge('First Month Rent Available', $fmtBool($d['first_month_rent_available'])); @endphp
    @php $ofv::badge('Last Month Rent Available', $fmtBool($d['last_month_rent_available'])); @endphp
    @php $ofv::row('Move-In Funds Available', $d['move_in_funds_available'] ? '$' . number_format((float)str_replace(',', '', $d['move_in_funds_available'])) : null); @endphp
    @php $ofv::row('Utility Preference', $fmt($d['utility_preference'])); @endphp
    @php $ofv::row('Maintenance Preference', $fmt($d['maintenance_preference'])); @endphp
    @php $ofv::row('Maintenance Response Time', $fmt($d['maintenance_response_time'])); @endphp
    @php $ofv::badge('Renewal Option Requested', $fmtBool($d['renewal_option_requested'])); @endphp
    @php $ofv::row('Restrictions', $fmt($d['restrictions'])); @endphp
    @php $ofv::row('Tenant Conditions', $fmt($d['tenant_conditions'])); @endphp
    {{-- Commercial lease preferences --}}
    @php $ofv::row('Commercial Lease Type Preference', $fmt($d['commercial_lease_type_preference'])); @endphp
    @php $ofv::row('CAM / NNN Preference', $fmt($d['cam_nnn_preference'])); @endphp
    @php $ofv::row('Commercial Parking Needs', $fmt($d['commercial_parking_access_needs'])); @endphp
    @php $ofv::row('Intended Business Use', $fmt($d['intended_business_use'])); @endphp
    @php $ofv::row('Business Type', $fmt($getMeta('business_type') ?? $getMeta('business_type_selected'))); @endphp
    @php $ofv::badge('Signage Request', $fmtBool($d['signage_request'])); @endphp
    @php $ofv::row('Personal Guarantee Preference', $fmt($d['personal_guarantee_preference'])); @endphp
    @php $ofv::row('Rent Escalation Preference', $fmt($d['rent_escalation_preference'])); @endphp
    @php $ofv::row('Buildout / Tenant Improvement Request', $fmt($d['buildout_tenant_improvement_request'])); @endphp
    @php $ofv::row('Lease Option Consideration', $fmt($d['lease_option_consideration'])); @endphp
    @php $ofv::row('Additional Lease Terms', $fmt($d['additional_tenant_lease_terms'])); @endphp
    @php $ofv::sectionEnd(); @endphp
    @endif

    {{-- ── Financing Terms (shared: seller, buyer, tenant, some landlord) ── --}}
    @php
    $hasFinancing = !empty($d['offered_financing']) || $d['purchase_price'] || $d['seller_financing_amount'] || $d['assumable_terms'] || !empty($d['exchange_item']) || $d['lease_option_price'] || $d['lease_purchase_price'] || $d['cryptocurrency_type'] || $d['nft_description'];
    @endphp
    @if($hasFinancing && ($isSeller || $isBuyer || $isTenant))
    @php $ofv::section('fa-solid fa-coins', 'Financing Terms'); @endphp
    @if(!empty($d['offered_financing'])) @php $ofv::tags('Financing Method(s) Offered', $d['offered_financing']); @endphp @endif
    @php $ofv::row('Other Financing', $fmt($d['other_financing'])); @endphp
    @php $ofv::badge('Pre-Approved', $fmtBool($d['pre_approved'])); @endphp
    @php $ofv::row('Pre-Approval Amount', $d['pre_approval_amount'] ? '$' . number_format((float)str_replace(',', '', $d['pre_approval_amount'])) : null); @endphp
    @php $ofv::row('Down Payment', $d['down_payment_amount'] ? ($d['down_payment_type'] ?? '$') . number_format((float)str_replace(',', '', $d['down_payment_amount'])) : null); @endphp
    @php $ofv::row('Cash Budget', $d['cash_budget'] ? '$' . number_format((float)str_replace(',', '', $d['cash_budget'])) : null); @endphp

    {{-- Seller Financing --}}
    @if($d['seller_financing_amount'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-2">Seller Financing</div></div>
    @php $ofv::row('Loan Amount', ($d['seller_financing_type'] ?? '$') . number_format((float)str_replace(',', '', $d['seller_financing_amount']))); @endphp
    @php $ofv::row('Interest Rate', $d['interest_rate'] ? $d['interest_rate'] . '%' : null); @endphp
    @php $ofv::row('Loan Duration', $d['loan_duration'] ? $d['loan_duration'] . ' years' : null); @endphp
    @php $ofv::row('Amortization Type', $fmt($d['seller_amortization_type'])); @endphp
    @php $ofv::row('Other Amortization Type', $fmt($d['seller_amortization_other'])); @endphp
    @php $ofv::row('Payment Frequency', $fmt($d['seller_payment_frequency'])); @endphp
    @php $ofv::row('Other Payment Frequency', $fmt($d['seller_payment_frequency_other'])); @endphp
    @php $ofv::row('Late Fee', $fmt($d['seller_late_fee_amount'])); @endphp
    @php $ofv::row('Prepayment Penalty', $fmtBool($d['prepayment_penalty'])); @endphp
    @php $ofv::row('Prepayment Penalty Amount', $d['prepayment_penalty_amount'] ? '$' . number_format((float)str_replace(',', '', $d['prepayment_penalty_amount'])) : null); @endphp
    @php $ofv::row('Balloon Payment Amount', $d['balloon_payment_amount'] ? '$' . number_format((float)str_replace(',', '', $d['balloon_payment_amount'])) : null); @endphp
    @php $ofv::row('Balloon Payment Date', $fmt($d['balloon_payment_date'])); @endphp
    @endif

    {{-- Assumable --}}
    @if($d['assumable_terms'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-2">Assumable Loan</div></div>
    @php $ofv::row('Assumable Terms', $fmt($d['assumable_terms'])); @endphp
    @php $ofv::row('Loan Type', $fmt($d['assumable_loan_type'])); @endphp
    @php $ofv::row('Outstanding Balance', $d['outstanding_balance'] ? '$' . number_format((float)str_replace(',', '', $d['outstanding_balance'])) : null); @endphp
    @php $ofv::row('Max Assumable Rate', $d['max_assumable_rate'] ? $d['max_assumable_rate'] . '%' : null); @endphp
    @php $ofv::row('Monthly Escrow', $d['assumable_monthly_escrow'] ? '$' . number_format((float)str_replace(',', '', $d['assumable_monthly_escrow'])) : null); @endphp
    @php $ofv::row('Loan Term Remaining', $fmt($d['assumable_loan_term_remaining'])); @endphp
    @php $ofv::row('Origination Date', $fmtDate($d['assumable_loan_origination_date'])); @endphp
    @php $ofv::row('Loan Servicer', $fmt($d['assumable_loan_servicer'])); @endphp
    @php $ofv::row('Assumption Fee Type', $fmt($d['assumable_fee_type'])); @endphp
    @php $ofv::row('Assumption Fee', $d['assumable_fee_amount'] ? '$' . number_format((float)str_replace(',', '', $d['assumable_fee_amount'])) : null); @endphp
    @php $ofv::row('Occupancy Requirement', $fmt($d['assumable_occupancy_requirement'])); @endphp
    @php $ofv::row('Occupancy Requirement (Other)', $fmt($d['assumable_occupancy_other'])); @endphp
    @php $ofv::row('Max Monthly Payment', $d['max_monthly_payment'] ? '$' . number_format((float)str_replace(',', '', $d['max_monthly_payment'])) : null); @endphp
    @php $ofv::row('Gap Payment', $d['gap_payment_amount'] ? '$' . number_format((float)str_replace(',', '', $d['gap_payment_amount'])) : null); @endphp
    @php $ofv::row('Gap Payment Type', $fmt($d['gap_payment_type'])); @endphp
    @endif

    {{-- Exchange / Trade --}}
    @if(!empty($d['exchange_item']))
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-2">Exchange / Trade</div></div>
    @php $ofv::tags('Exchange Item(s)', $d['exchange_item']); @endphp
    @php $ofv::row('Other Exchange Item', $fmt($d['other_exchange_item'])); @endphp
    @php $ofv::row('Item Value', $d['exchange_item_value'] ? '$' . number_format((float)str_replace(',', '', $d['exchange_item_value'])) : null); @endphp
    @php $ofv::row('Item Condition', $fmt($d['exchange_item_condition'])); @endphp
    @php $ofv::row('Additional Cash', $d['additional_cash'] ? '$' . number_format((float)str_replace(',', '', $d['additional_cash'])) : null); @endphp
    @php $ofv::row('Value Determination', $fmt($d['value_determination'])); @endphp
    @php $ofv::row('Transfer Method', $fmt($d['exchange_transfer_method'])); @endphp
    @php $ofv::row('Liens on Item', $fmtBool($d['exchange_liens'])); @endphp
    @php $ofv::row('Lien Details', $fmt($d['exchange_liens_details'])); @endphp
    @php $ofv::row('Lien Disclosure', $fmt($d['exchange_liens_disclosure'])); @endphp
    @php $ofv::row('Inspection Rights', $fmtBool($d['exchange_inspection_rights'])); @endphp
    @endif

    {{-- Lease Option --}}
    @if($d['lease_option_price'] || $d['interested_lease_option'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-2">Lease Option</div></div>
    @php $ofv::row('Lease Option Price', $d['lease_option_price'] ? '$' . number_format((float)str_replace(',', '', $d['lease_option_price'])) : null); @endphp
    @php $ofv::row('Terms', $fmt($d['lease_option_terms'])); @endphp
    @php $ofv::row('Duration', $fmt($d['lease_option_duration'])); @endphp
    @php $ofv::row('Monthly Payment', $d['lease_option_payment'] ? '$' . number_format((float)str_replace(',', '', $d['lease_option_payment'])) : null); @endphp
    @php $ofv::row('Conditions', $fmt($d['lease_option_conditions'])); @endphp
    @php $ofv::row('Option Fee Required', $fmtBool($d['has_option_fee'])); @endphp
    @php $ofv::row('Option Fee Amount', $d['option_fee_amount'] ? '$' . number_format((float)str_replace(',', '', $d['option_fee_amount'])) : null); @endphp
    @php $ofv::row('Rent Credit', $fmt($getMeta('seller_lease_option_fee_credit') ?? $getMeta('lease_option_fee_credit'))); @endphp
    @php $ofv::row('Maintenance Responsibility', $fmt($getMeta('seller_lease_option_maintenance') ?? $getMeta('lease_option_maintenance'))); @endphp
    @php $ofv::row('Extension Terms', $fmt($getMeta('seller_lease_option_extension_terms') ?? $getMeta('lease_option_extension_terms'))); @endphp
    @endif

    {{-- Lease Purchase --}}
    @if($d['lease_purchase_price'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-2">Lease Purchase</div></div>
    @php $ofv::row('Purchase Price', '$' . number_format((float)str_replace(',', '', $d['lease_purchase_price']))); @endphp
    @php $ofv::row('Terms', $fmt($d['lease_purchase_terms'])); @endphp
    @php $ofv::row('Duration', $fmt($d['lease_purchase_duration'])); @endphp
    @php $ofv::row('Monthly Payment', $d['lease_purchase_payment'] ? '$' . number_format((float)str_replace(',', '', $d['lease_purchase_payment'])) : null); @endphp
    @php $ofv::row('Conditions', $fmt($d['lease_purchase_conditions'])); @endphp
    @php $ofv::row('Rent Credit', $fmt($d['lease_purchase_rent_credit'])); @endphp
    @php $ofv::row('Rent Credit Amount', $d['lease_purchase_rent_credit_amount'] ? '$' . number_format((float)str_replace(',', '', $d['lease_purchase_rent_credit_amount'])) : null); @endphp
    @php $ofv::row('Deposit', $d['lease_purchase_deposit'] ? '$' . number_format((float)str_replace(',', '', $d['lease_purchase_deposit'])) : null); @endphp
    @php $ofv::row('Maintenance Responsibility', $fmt($d['lease_purchase_maintenance'])); @endphp
    @php $ofv::row('Extension Terms', $fmt($d['lease_purchase_extension_terms'])); @endphp
    @endif
    {{-- Seller Lease-Purchase specific --}}
    @if($d['seller_lease_purchase_deposit'] || $d['seller_lease_purchase_maintenance'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-2">Seller Lease-Purchase Specifics</div></div>
    @php $ofv::row('Seller LP Deposit', $d['seller_lease_purchase_deposit'] ? '$' . number_format((float)str_replace(',', '', $d['seller_lease_purchase_deposit'])) : null); @endphp
    @php $ofv::row('Seller LP Maintenance', $fmt($d['seller_lease_purchase_maintenance'])); @endphp
    @php $ofv::row('Seller LP Rent Credit', $fmt($d['seller_lease_purchase_rent_credit'])); @endphp
    @php $ofv::row('Seller LP Rent Credit Amount', $d['seller_lease_purchase_rent_credit_amount'] ? '$' . number_format((float)str_replace(',', '', $d['seller_lease_purchase_rent_credit_amount'])) : null); @endphp
    @php $ofv::row('Seller LP Rent Credit Type', $fmt($d['seller_lease_purchase_rent_credit_type'])); @endphp
    @php $ofv::row('Seller LP Extension Terms', $fmt($d['seller_lease_purchase_extension_terms'])); @endphp
    @php $ofv::row('Seller Lease Option Fee Credit %', $fmt($d['seller_lease_option_fee_credit_percent'])); @endphp
    @endif

    {{-- Cryptocurrency --}}
    @if($d['cryptocurrency_type'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-2">Cryptocurrency</div></div>
    @php $ofv::row('Crypto Type', $fmt($d['cryptocurrency_type'])); @endphp
    @php $ofv::row('Crypto Percentage', $d['crypto_percentage'] ? $d['crypto_percentage'] . '%' : null); @endphp
    @php $ofv::row('Cash Percentage', $d['cash_percentage_crypto'] ? $d['cash_percentage_crypto'] . '%' : null); @endphp
    @php $ofv::row('Transfer Timing', $fmt($d['crypto_transfer_timing'])); @endphp
    @php $ofv::row('Transfer Timing (Other)', $fmt($d['crypto_transfer_timing_other'])); @endphp
    @php $ofv::row('Exchange Method', $fmt($d['crypto_exchange_method'])); @endphp
    @php $ofv::row('Custodian Wallet', $fmt($d['crypto_custodian_wallet'])); @endphp
    @php $ofv::row('Transaction Fees', $fmt($d['crypto_transaction_fees'])); @endphp
    @endif

    {{-- NFT --}}
    @if($d['nft_description'] || $d['nft_percentage'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-2">NFT</div></div>
    @php $ofv::row('NFT Description', $fmt($d['nft_description'])); @endphp
    @php $ofv::row('NFT Percentage', $d['nft_percentage'] ? $d['nft_percentage'] . '%' : null); @endphp
    @php $ofv::row('Cash Percentage', $d['cash_percentage_nft'] ? $d['cash_percentage_nft'] . '%' : null); @endphp
    @php $ofv::row('Valuation Method', $fmt($d['nft_valuation_method'])); @endphp
    @php $ofv::row('Transfer Method', $fmt($d['nft_transfer_method'])); @endphp
    @php $ofv::row('Gas Fees', $fmt($d['nft_gas_fees'])); @endphp
    @endif

    @php $ofv::sectionEnd(); @endphp
    @endif
    {{-- 4. FINANCIAL DETAILS (Income / Commercial / Business)         --}}
    
    @php
    $hasFinancialDetails = $d['gross_annual_income'] || $d['annual_revenue'] || $d['price_per_sqft'] || $d['minimum_annual_net_income'];
    @endphp
    @if($hasFinancialDetails)
    @php $ofv::section('fa-solid fa-chart-line', 'Financial Details'); @endphp
    @php $ofv::row('Min. Annual Net Income', $d['minimum_annual_net_income'] ? '$' . number_format((float)str_replace(',', '', $d['minimum_annual_net_income'])) : null); @endphp
    @php $ofv::row('Min. Cap Rate', $d['minimum_cap_rate'] ? $d['minimum_cap_rate'] . '%' : null); @endphp

    @if($d['gross_annual_income'] || $d['annual_operating_expenses'])
    <div class="col-12"><div class="fw-semibold small text-muted">Income Property</div></div>
    @php $ofv::row('Gross Annual Income', $d['gross_annual_income'] ? '$' . number_format((float)str_replace(',', '', $d['gross_annual_income'])) : null); @endphp
    @php $ofv::row('Annual Operating Expenses', $d['annual_operating_expenses'] ? '$' . number_format((float)str_replace(',', '', $d['annual_operating_expenses'])) : null); @endphp
    @php $ofv::badge('Rent Roll Available', $fmtBool($d['rent_roll_available'])); @endphp
    @php $ofv::badge('Operating Statement Available', $fmtBool($d['operating_statement_available'])); @endphp
    @endif

    @if($d['price_per_sqft'] || $d['existing_lease_type'])
    <div class="col-12"><div class="fw-semibold small text-muted">Commercial Property</div></div>
    @php $ofv::row('Price Per Sq Ft', $d['price_per_sqft'] ? '$' . number_format((float)str_replace(',', '', $d['price_per_sqft']), 2) : null); @endphp
    @php $ofv::row('Existing Lease Type', $fmt($d['existing_lease_type'])); @endphp
    @php $ofv::row('Lease Expiration', $fmtDate($d['lease_expiration'])); @endphp
    @php $ofv::badge('Lease Assignable', $fmtBool($d['lease_assignable'])); @endphp
    @endif

    @if($d['annual_revenue'])
    <div class="col-12"><div class="fw-semibold small text-muted">Business / Operating Financials</div></div>
    @php $ofv::row('Annual Revenue', $d['annual_revenue'] ? '$' . number_format((float)str_replace(',', '', $d['annual_revenue'])) : null); @endphp
    @php $ofv::row('Gross Profit', $d['gross_profit'] ? '$' . number_format((float)str_replace(',', '', $d['gross_profit'])) : null); @endphp
    @php $ofv::row('SDE / EBITDA', $d['sde_ebitda'] ? '$' . number_format((float)str_replace(',', '', $d['sde_ebitda'])) : null); @endphp
    @php $ofv::row('Inventory Value', $d['inventory_value'] ? '$' . number_format((float)str_replace(',', '', $d['inventory_value'])) : null); @endphp
    @php $ofv::row('FF&E Value', $d['ffe_value'] ? '$' . number_format((float)str_replace(',', '', $d['ffe_value'])) : null); @endphp
    @php $ofv::row('Reason for Sale', $fmt($d['reason_for_sale'])); @endphp
    @php $ofv::row('Other Reason for Sale', $fmt($d['other_reason_for_sale'])); @endphp
    @php $ofv::row('Employee Count', $fmt($d['employee_count'])); @endphp
    @php $ofv::badge('Financial Statements Available', $fmtBool($d['financial_statements_available'])); @endphp
    @php $ofv::badge('Tax Returns Available', $fmtBool($d['tax_returns_available'])); @endphp
    @php $ofv::badge('NDA Required', $fmtBool($d['nda_required'])); @endphp
    @endif

    @php $ofv::sectionEnd(); @endphp
    @endif
    {{-- 5. ADDITIONAL DETAILS (pre-screening & notes)                 --}}
    
    @php
    $hasPrescreen = $d['pets'] || !empty($d['credit_scroe_rating']) || $d['prior_eviction'] || $d['monthly_income'] || $d['number_occupant'] || $d['screening_concerns'];
    $hasAddlDetails = $d['additional_details'] || $d['preferance_details'] || $hasPrescreen;
    @endphp
    @if($hasAddlDetails || !empty($d['tenant_require']))
    @php $ofv::section('fa-solid fa-circle-info', 'Additional Details'); @endphp
    @if(!empty($d['tenant_require']))
    @php $ofv::tags('Tenant Requirements', $d['tenant_require']); @endphp
    @endif
    @if($d['listing_title'])
    @php $ofv::row('Listing Title', $d['listing_title']); @endphp
    @endif
    @if($d['additional_details'])
    <div class="col-12">
        <div class="text-muted small mb-1">Additional Details</div>
        <div class="border rounded p-3 bg-light small" style="white-space:pre-wrap;">{{ $d['additional_details'] }}</div>
    </div>
    @endif

    {{-- Pre-screening / applicant info --}}
    @if($hasPrescreen)
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-1">Pre-Screening Information</div></div>
    @php $ofv::row('Pets', $fmt($d['pets'])); @endphp
    @if($d['pets'] && strtolower($d['pets']) !== 'no')
    @php $ofv::row('Number of Pets', $fmt($d['number_of_pets'])); @endphp
    @php $ofv::row('Breed of Pets', $fmt($d['breed_of_pets'])); @endphp
    @php $ofv::row('Type of Pets', $fmt($d['type_of_pets'])); @endphp
    @php $ofv::row('Weight of Pets', $fmt($d['weight_of_pets'])); @endphp
    @php $ofv::row('Has Breed Restrictions', $fmtBool($d['has_breed_restrictions'])); @endphp
    @php $ofv::row('Breed Restrictions', $fmt($d['breed_restrictions'])); @endphp
    @endif
    @php $ofv::row('Service Animal', $fmtBool($d['service_animal'])); @endphp
    @php $ofv::row('Emotional Support Animal', $fmtBool($d['emotional_support_animal'])); @endphp
    @php $ofv::row('Support Animal', $fmtBool($d['support_animal'])); @endphp
    @if(!empty($d['credit_scroe_rating'])) @php $ofv::tags('Credit Score Rating', $d['credit_scroe_rating']); @endphp @endif
    @php $ofv::row('Prior Eviction', $fmtBool($d['prior_eviction'])); @endphp
    @if($d['prior_eviction'] && in_array($d['prior_eviction'], ['Yes', '1', 'true', 1], true))
    @php $ofv::row('Eviction Explanation', $fmt($d['eviction_explanation'])); @endphp
    @endif
    @php $ofv::row('Prior Felony', $fmtBool($d['prior_felony'])); @endphp
    @if($d['prior_felony'] && in_array($d['prior_felony'], ['Yes', '1', 'true', 1], true))
    @php $ofv::row('Felony Explanation', $fmt($d['prior_felony_explanation'])); @endphp
    @endif
    @php $ofv::row('Monthly Income', $d['monthly_income'] ? '$' . number_format((float)str_replace(',', '', $d['monthly_income'])) : null); @endphp
    @php $ofv::row('Number of Occupants', $fmt($getMeta('number_occupant') ?? $getMeta('number_of_occupants'))); @endphp
    @php $ofv::badge('Rental History Disclosure', $fmtBool($d['screening_concerns'])); @endphp
    @php $ofv::row('Disclosure Details', $fmt($d['screening_concerns_explanation'])); @endphp
    @endif
    @php $ofv::row('Interested in Property Management', $fmtBool($d['interested_in_property_management'])); @endphp
    @php $ofv::row('Property Management Fee', $fmt($d['interested_in_property_management_fee'])); @endphp
    @php $ofv::row('Interested in Selling', $fmtBool($d['interested_in_selling'])); @endphp
    @php $ofv::row('Interested in Selling Type', $fmt($d['interested_in_selling_type'])); @endphp
    @php $ofv::sectionEnd(); @endphp
    @endif
    {{-- 6. TAX, LEGAL, HOA & DISCLOSURES (Seller only)               --}}
    
    @if(($isSeller || $isLandlord) && ($d['parcel_id'] || $d['annual_property_taxes'] || $d['has_hoa'] || $d['seller_disclosure_available'] || $d['landlord_disclosure_available'] || $d['flood_zone_code']))
    @php $ofv::section('fa-solid fa-scale-balanced', 'Tax, Legal, HOA & Disclosures'); @endphp

    {{-- Tax / Legal --}}
    @php $ofv::row('Parcel ID', $fmt($d['parcel_id'])); @endphp
    @php $ofv::row('Tax Year', $fmt($d['tax_year'])); @endphp
    @php $ofv::row('Annual Property Taxes', $d['annual_property_taxes'] ? '$' . number_format((float)str_replace(',', '', $d['annual_property_taxes'])) : null); @endphp
    @php $ofv::row('Legal Description', $fmt($d['legal_description']), 'col-12'); @endphp
    @php $ofv::row('Flood Zone Code', $fmt($d['flood_zone_code'])); @endphp
    @php $ofv::badge('Flood Insurance Required', $fmtBool($d['flood_insurance_required'])); @endphp
    @php $ofv::row('Flood Zone Panel', $fmt($d['flood_zone_panel'])); @endphp
    @php $ofv::badge('Community Development District (CDD)', $fmtBool($d['has_cdd'])); @endphp
    @php $ofv::row('Annual CDD Fee', $d['annual_cdd_fee'] ? '$' . number_format((float)str_replace(',', '', $d['annual_cdd_fee'])) : null); @endphp
    @php $ofv::badge('Special Assessments', $fmtBool($d['has_special_assessments'])); @endphp
    @php $ofv::row('Special Assessment Amount', $d['special_assessment_amount'] ? '$' . number_format((float)str_replace(',', '', $d['special_assessment_amount'])) : null); @endphp
    @php $ofv::row('Special Assessment Description', $fmt($d['special_assessment_description'])); @endphp

    {{-- HOA --}}
    @if($d['has_hoa'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-1">HOA / Association</div></div>
    @php $ofv::badge('Has HOA / Association', $fmtBool($d['has_hoa'])); @endphp
    @php $ofv::row('Association Type', $fmt($d['association_type'])); @endphp
    @php $ofv::row('Other Association Type', $fmt($d['association_type_other'])); @endphp
    @php $ofv::row('Association Name', $fmt($d['association_name'])); @endphp
    @php $ofv::row('Association Fee', $d['association_fee_amount'] ? '$' . number_format((float)str_replace(',', '', $d['association_fee_amount'])) : null); @endphp
    @php $ofv::row('Fee Frequency', $fmt($d['association_fee_frequency'])); @endphp
    @php $ofv::row('Other Fee Frequency', $fmt($d['association_fee_frequency_other'])); @endphp
    @php $ofv::badge('Association Approval Required', $fmtBool($d['association_approval_required'])); @endphp
    @php $ofv::row('Approval Process', $fmt($d['association_approval_process'])); @endphp
    @php $ofv::row('Application Fee', $d['association_application_fee'] ? '$' . number_format((float)str_replace(',', '', $d['association_application_fee'])) : null); @endphp
    @if(!empty($d['association_fee_includes'])) @php $ofv::tags('Fee Includes', $d['association_fee_includes']); @endphp @endif
    @php $ofv::row('Other Fee Includes', $fmt($d['association_fee_includes_other'])); @endphp
    @if(!empty($d['association_amenities'])) @php $ofv::tags('Association Amenities', $d['association_amenities']); @endphp @endif
    @php $ofv::row('Other Amenities', $fmt($d['association_amenities_other'])); @endphp
    @php $ofv::row('Leasing Restrictions', $fmt($d['leasing_restrictions'])); @endphp
    @php $ofv::row('Min. Lease Period', $fmt($d['min_lease_period'])); @endphp
    @php $ofv::row('Other Min. Lease Period', $fmt($d['min_lease_period_other'])); @endphp
    @php $ofv::row('Max Leases Per Year', $fmt($d['max_leases_per_year'])); @endphp
    @php $ofv::row('Additional Lease Restrictions', $fmt($d['additional_lease_restrictions'])); @endphp
    @php $ofv::row('Pet Restrictions', $fmt($d['pet_restrictions'])); @endphp
    @php $ofv::row('Pet Restriction Details', $fmt($d['pet_restrictions_detail'])); @endphp
    @endif
    @php $ofv::row('Flood Zone Code (Other)', $fmt($d['flood_zone_code_other'])); @endphp
    @php $ofv::row('Other Document Type', $fmt($d['other_document_type'])); @endphp

    {{-- Disclosure Checklist --}}
    @php
    $disclosures = [
        'Seller Disclosure'    => $d['seller_disclosure_available'],
        'Landlord Disclosure'  => $d['landlord_disclosure_available'],
        'Survey'               => $d['survey_available'],
        'Inspection Report'    => $d['inspection_report_available'],
        'HOA / Condo Docs'     => $d['hoa_condo_docs_available'],
        'Flood Disclosure'     => $d['flood_disclosure_available'],
        'Lead-Based Paint'     => $d['lead_based_paint_disclosure'],
        'Environmental Report' => $d['environmental_report_available'],
    ];
    $docFiles = [
        'Seller Disclosure'    => $d['seller_disclosure_file_path'],
        'Survey'               => $d['survey_file_path'],
        'Inspection Report'    => $d['inspection_report_file_path'],
        'HOA / Condo Docs'     => $d['hoa_condo_docs_file_path'],
        'Flood Disclosure'     => $d['flood_disclosure_file_path'],
        'Lead-Based Paint'     => $d['lead_based_paint_file_path'],
        'Environmental Report' => $d['environmental_report_file_path'],
    ];
    $hasAnyFile = array_filter($docFiles);
    $hasDisclosureSection = !empty(array_filter($disclosures, fn($v) => $v !== '' && $v !== null))
                         || $hasAnyFile
                         || !empty($d['additional_documents']);
    @endphp
    @if($hasDisclosureSection)
    <div class="col-12">
        <div class="text-muted small mb-2 border-top pt-2 mt-1">Documents & Disclosures</div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($disclosures as $dname => $dval)
            @if($dval !== '' && $dval !== null)
            @php
            $yes = in_array($dval, [1, '1', 'true', true, 'yes', 'Yes'], true);
            $cls = $yes ? 'bg-success' : 'bg-secondary';
            @endphp
            <span class="badge {{ $cls }}">{{ $dname }}: {{ $yes ? 'Available' : 'Not Available' }}</span>
            @endif
            @endforeach
        </div>

        {{-- Uploaded disclosure document download links --}}
        @if($hasAnyFile)
        <div class="text-muted small mb-1">Download Uploaded Documents</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach($docFiles as $dname => $dpath)
            @if($dpath)
            <a href="{{ Storage::url($dpath) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="fa-solid fa-file-arrow-down me-1"></i>{{ $dname }}
            </a>
            @endif
            @endforeach
        </div>
        @endif

        @if(!empty($d['additional_documents']))
        <div class="text-muted small mb-1 mt-2">Additional Documents</div>
        <div class="d-flex flex-wrap gap-1">
            @foreach($d['additional_documents'] as $addlDoc)
            @if($addlDoc)
            <span class="badge bg-light text-dark border">{{ $addlDoc }}</span>
            @endif
            @endforeach
        </div>
        @endif
    </div>
    @endif

    @php $ofv::sectionEnd(); @endphp
    @endif
    {{-- 7. BROKER COMPENSATION & SERVICES                             --}}
    
    @php
    $hasBrokerSection = $d['commission_structure'] || $d['lease_fee_type'] || $d['purchase_fee_type'] || $d['protection_period'] || $d['brokerage_relationship'] || !empty($d['services']) || !empty($d['services_snapshot']);
    @endphp
    @if($hasBrokerSection)
    @php $ofv::section('fa-solid fa-handshake-angle', 'Broker Compensation & Services'); @endphp

    @php $ofv::row('Commission Structure', $fmt($d['commission_structure'])); @endphp
    @php $ofv::row('Brokerage Relationship', $fmt($d['brokerage_relationship'])); @endphp
    @php $ofv::row('Agency Agreement Timeframe', $fmt($d['agency_agreement_timeframe'])); @endphp
    @php $ofv::row('Agency Agreement (Custom)', $fmt($d['agency_agreement_custom'])); @endphp
    @php $ofv::row('Protection Period', $fmt($d['protection_period'])); @endphp
    @php $ofv::badge('Early Termination Fee', $fmtBool($d['early_termination_fee_option'])); @endphp
    @php $ofv::row('Early Termination Fee Amount', $d['early_termination_fee_amount'] ? '$' . number_format((float)str_replace(',', '', $d['early_termination_fee_amount'])) : null); @endphp
    @php $ofv::badge('Retainer Fee', $fmtBool($d['retainer_fee_option'])); @endphp
    @php $ofv::row('Retainer Fee Amount', $d['retainer_fee_amount'] ? '$' . number_format((float)str_replace(',', '', $d['retainer_fee_amount'])) : null); @endphp
    @php $ofv::row('Retainer Application', $fmt($d['retainer_fee_application'])); @endphp
    @php $ofv::row('Broker Fee Timing', $fmt($d['broker_fee_timing'])); @endphp

    @if($d['lease_fee_type'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-1">Lease Fee</div></div>
    @php $ofv::row('Lease Fee Type', $fmt($d['lease_fee_type'])); @endphp
    @php $ofv::row('Flat Fee', $d['lease_fee_flat'] ? '$' . number_format((float)str_replace(',', '', $d['lease_fee_flat'])) : null); @endphp
    @php $ofv::row('Percentage', $d['lease_fee_percentage'] ? $d['lease_fee_percentage'] . '%' : null); @endphp
    @php $ofv::row('Months', $fmt($d['lease_fee_months'])); @endphp
    @php $ofv::row('Other', $fmt($d['lease_fee_other'])); @endphp
    @endif

    @if($d['purchase_fee_type'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-1">Purchase Fee</div></div>
    @php $ofv::row('Purchase Fee Type', $fmt($d['purchase_fee_type'])); @endphp
    @php $ofv::row('Percentage', $d['purchase_fee_percentage'] ? $d['purchase_fee_percentage'] . '%' : null); @endphp
    @php $ofv::row('Flat Fee', $d['purchase_fee_flat'] ? '$' . number_format((float)str_replace(',', '', $d['purchase_fee_flat'])) : null); @endphp
    @php $ofv::row('Other', $fmt($d['purchase_fee_other'])); @endphp
    @endif

    @if($d['lease_option_fee_type'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-1">Lease-Option Fee</div></div>
    @php $ofv::row('Type', $fmt($d['lease_option_fee_type'])); @endphp
    @php $ofv::row('Flat Fee', $d['lease_option_fee_flat'] ? '$' . number_format((float)str_replace(',', '', $d['lease_option_fee_flat'])) : null); @endphp
    @php $ofv::row('Percentage', $d['lease_option_fee_percentage'] ? $d['lease_option_fee_percentage'] . '%' : null); @endphp
    @php $ofv::row('Other', $fmt($d['lease_option_fee_other'])); @endphp
    @endif

    @if($d['renewal_fee_type'])
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-1">Renewal Fee</div></div>
    @php $ofv::row('Type', $fmt($d['renewal_fee_type'])); @endphp
    @php $ofv::row('Flat Fee', $d['renewal_fee_flat_free'] ? '$' . number_format((float)str_replace(',', '', $d['renewal_fee_flat_free'])) : null); @endphp
    @php $ofv::row('First Month Rent', $fmtBool($d['renewal_fee_first_month'])); @endphp
    @php $ofv::row('Lease Value %', $fmt($d['renewal_fee_lease_value'])); @endphp
    @php $ofv::row('No. of Months', $fmt($d['renewal_fee_no_of_months'])); @endphp
    @endif

    @if(!empty($d['services_snapshot']) || !empty($d['services']))
    <div class="col-12"><div class="fw-semibold small text-muted border-top pt-2 mt-1">Services</div></div>
    @if(!empty($d['services_snapshot']))
    <div class="col-12">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 small">
                <thead class="table-light"><tr><th>Service</th><th>Category</th><th>Fee</th></tr></thead>
                <tbody>
                @foreach($d['services_snapshot'] as $svc)
                @if(is_array($svc))
                <tr>
                    <td>{{ $svc['label'] ?? $svc['name'] ?? '-' }}</td>
                    <td>{{ $svc['category'] ?? '-' }}</td>
                    <td>{{ isset($svc['fee']) && $svc['fee'] ? '$' . number_format((float)str_replace(',', '', $svc['fee'])) : ($svc['fee_type'] ?? '-') }}</td>
                </tr>
                @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @elseif(!empty($d['services']))
    @php $ofv::tags('Selected Services', $d['services']); @endphp
    @endif
    @if(!empty($d['flat_fee_services'])) @php $ofv::tags('Flat-Fee Services', $d['flat_fee_services']); @endphp @endif
    @if(!empty($d['other_services'])) @php $ofv::tags('Other Services', $d['other_services']); @endphp @endif
    @endif

    @php $ofv::row('Additional Broker Notes', $fmt($d['additional_details_broker'])); @endphp

    @php $ofv::sectionEnd(); @endphp
    @endif
    {{-- 7b. MEETING / SHOWING DETAILS                                  --}}
    @php
    $hasMeetingDetails = $d['meeting_details_first_name'] || $d['meeting_details_last_name'] || $d['meeting_details_meeting_date'] || $d['meeting_details_email'] || $d['meeting_details_phone'];
    @endphp
    @if($hasMeetingDetails)
    @php $ofv::section('fa-solid fa-calendar-days', 'Meeting / Showing Details'); @endphp
    @php
    $meetingName = trim(($d['meeting_details_first_name'] ?? '') . ' ' . ($d['meeting_details_last_name'] ?? ''));
    if ($meetingName) echo '<div class="col-md-6"><div class="text-muted small mb-1">Contact Name</div><div class="fw-semibold">' . e($meetingName) . '</div></div>';
    @endphp
    @php $ofv::row('Email', $fmt($d['meeting_details_email'])); @endphp
    @php $ofv::row('Phone', $fmt($d['meeting_details_phone'])); @endphp
    @php $ofv::row('Meeting Date', $fmtDate($d['meeting_details_meeting_date'])); @endphp
    @php $ofv::row('Meeting Time', $fmt($d['meeting_details_meeting_time'])); @endphp
    @php $ofv::row('Time Zone', $fmt($d['meeting_details_time_zone'])); @endphp
    @php $ofv::row('Instructions', $fmt($d['meeting_details_instructions']), 'col-12'); @endphp
    @php $ofv::row('Additional Notes', $fmt($d['meeting_details_additional_details']), 'col-12'); @endphp
    @php $ofv::sectionEnd(); @endphp
    @endif
    {{-- 8. PHOTOS, TOURS & DOCUMENTS                                  --}}
    
    @php
    $hasMedia = !empty($d['property_photos']) || $d['video_tour_url'] || $d['virtual_tour_url'] || $d['agent_video'] || $d['listing_documents'];
    @endphp
    @if($hasMedia)
    @php $ofv::section('fa-solid fa-images', 'Photos, Tours & Documents'); @endphp

    @if(!empty($d['property_photos']))
    <div class="col-12">
        <div class="text-muted small mb-2">Property Photos</div>
        <div class="row g-2">
            @foreach($d['property_photos'] as $photo)
            @if($photo)
            <div class="col-6 col-md-3">
                <a href="{{ Storage::url($photo) }}" target="_blank">
                    <img src="{{ Storage::url($photo) }}" alt="Property Photo" class="img-fluid rounded shadow-sm" style="width:100%;height:140px;object-fit:cover;">
                </a>
            </div>
            @endif
            @endforeach
        </div>
    </div>
    @endif

    @if($d['listing_documents'])
    <div class="col-md-6">
        <div class="text-muted small mb-1">Listing Documents</div>
        <a href="{{ Storage::url('auction/documents/' . $d['listing_documents']) }}" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-file-arrow-down me-1"></i>Download Listing Documents
        </a>
    </div>
    @endif

    @if($d['video_tour_url'])
    <div class="col-md-6">
        <div class="text-muted small mb-1">Video Tour</div>
        <a href="{{ $d['video_tour_url'] }}" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-video me-1"></i>Watch Video Tour
        </a>
    </div>
    @endif

    @if($d['virtual_tour_url'])
    <div class="col-md-6">
        <div class="text-muted small mb-1">3D / Virtual Tour</div>
        <a href="{{ $d['virtual_tour_url'] }}" target="_blank" class="btn btn-sm btn-outline-info">
            <i class="fa-solid fa-cube me-1"></i>View Virtual Tour
        </a>
    </div>
    @endif

    @if($d['agent_video'])
    <div class="col-md-6">
        <div class="text-muted small mb-1">Agent Video</div>
        <a href="{{ Storage::url('auction/videos/' . $d['agent_video']) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-film me-1"></i>Watch Agent Video
        </a>
    </div>
    @endif

    @php $ofv::sectionEnd(); @endphp
    @endif
    {{-- 8. AGENT CREDENTIALS & CONTACT INFO                           --}}
    
    @if($d['first_name'] || $d['last_name'] || $d['email'] || $d['phone_number'])
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold py-3" style="border-bottom:1px solid #f0f0f0;">
            <i class="fa-solid fa-id-card me-2" style="color:#049399;"></i>Agent Credentials &amp; Contact Info
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-start">
                @if($d['agent_photo'])
                <div class="col-auto">
                    <img src="{{ Storage::url('auction/images/' . $d['agent_photo']) }}"
                         alt="Agent Photo"
                         class="rounded-circle shadow-sm"
                         style="width:80px;height:80px;object-fit:cover;">
                </div>
                @endif
                <div class="col">
                    <div class="row g-3">
                        @php $ofv::row('Name', trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?: null); @endphp
                        @if($d['phone_number'])
                        <div class="col-md-4">
                            <div class="text-muted small mb-1">Phone</div>
                            <div>
                                @php
                                $raw = preg_replace('/\D/', '', $d['phone_number']);
                                $formatted = strlen($raw) === 10 ? '(' . substr($raw,0,3) . ') ' . substr($raw,3,3) . '-' . substr($raw,6) : $d['phone_number'];
                                @endphp
                                <a href="tel:{{ $d['phone_number'] }}" class="text-decoration-none">{{ $formatted }}</a>
                            </div>
                        </div>
                        @endif
                        @if($d['email'])
                        <div class="col-md-4">
                            <div class="text-muted small mb-1">Email</div>
                            <div><a href="mailto:{{ $d['email'] }}" class="text-decoration-none">{{ $d['email'] }}</a></div>
                        </div>
                        @endif
                        @php $ofv::row('Brokerage', $fmt($d['agent_brokerage'])); @endphp
                        @php $ofv::row('License #', $fmt($d['agent_license_number'])); @endphp
                        @php $ofv::row('NAR Member ID', $fmt($d['agent_nar_member_id'])); @endphp
                        @if(!$isBuyer) @php $ofv::row('Current Status', $fmt($d['current_status'])); @endphp @endif
                        @if($d['video_link'])
                        <div class="col-md-4">
                            <div class="text-muted small mb-1">Agent Video Link</div>
                            <div><a href="{{ $d['video_link'] }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-play me-1"></i>Watch</a></div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Footer actions ── --}}
    <div class="d-flex gap-2 justify-content-end mt-2 mb-5">
        <a href="{{ $d['hub_route'] }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to My Offer Listings
        </a>
        <a href="{{ $d['edit_route'] }}" class="btn btn-sm text-white" style="background:#049399;">
            <i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing
        </a>
    </div>

</div>
@endsection
