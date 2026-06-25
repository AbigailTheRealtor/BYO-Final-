@extends('layouts.main')

@section('title', ($property['address'] ?? 'Property Detail') . ' — Stellar MLS')

@section('content')
<div class="container-fluid py-3" style="max-width:1140px;">

    {{-- =====================================================================
         Back navigation + status badges
    ===================================================================== --}}
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Results
        </a>
        @if($property['mls_status'])
            <span class="badge bg-secondary">{{ $property['mls_status'] }}</span>
        @endif
        @if($property['new_construction'])
            <span class="badge bg-success">New Construction</span>
        @endif
        @if($property['waterfront'])
            <span class="badge bg-info text-dark">Waterfront</span>
        @endif
        @if($property['senior_community'])
            <span class="badge bg-warning text-dark">55+ Community</span>
        @endif
    </div>

    {{-- =====================================================================
         Photo carousel
    ===================================================================== --}}
    <div class="mb-4">
        <x-stellar.property-photo-carousel
            :photos="$property['photos']"
            :address="$property['address']"
        />
    </div>

    {{-- =====================================================================
         Price + address header
    ===================================================================== --}}
    <div class="mb-3">
        <div class="d-flex align-items-baseline gap-3 flex-wrap">
            @if($property['price_display'])
                <span class="fw-bold text-dark" style="font-size:1.9rem;line-height:1.1;">
                    {{ $property['price_display'] }}
                </span>
            @else
                <span class="text-muted fst-italic" style="font-size:1.4rem;">Price not available</span>
            @endif
            @if($property['price_reduced'] && $property['original_list_price'])
                <span class="text-muted" style="font-size:.9rem;">
                    <s>${{ number_format($property['original_list_price'], 0) }}</s>
                    <span class="badge bg-danger ms-1">Price reduced</span>
                </span>
            @endif
        </div>

        @if($property['address'])
            <div class="fw-semibold text-dark mt-1" style="font-size:1.1rem;">
                {{ $property['address'] }}
            </div>
        @endif
        @if($property['city'] || $property['state'] || $property['postal_code'])
            <div class="text-muted">
                {{ implode(', ', array_filter([$property['city'], $property['state']])) }}
                {{ $property['postal_code'] }}
            </div>
        @endif
        @if($property['subdivision'])
            <div class="text-muted" style="font-size:.875rem;">
                <i class="fas fa-map-pin me-1"></i>{{ $property['subdivision'] }}
                @if($property['county'])
                    &mdash; {{ $property['county'] }} County
                @endif
            </div>
        @endif
    </div>

    {{-- =====================================================================
         Specs bar
    ===================================================================== --}}
    <div class="d-flex flex-wrap gap-3 align-items-center pb-3 mb-4 border-bottom" style="font-size:.95rem;">
        @if($property['beds'] !== null)
            <div>
                <i class="fas fa-bed me-1 text-secondary"></i>
                <strong>{{ $property['beds'] }}</strong> bed{{ $property['beds'] != 1 ? 's' : '' }}
            </div>
        @endif

        @if($property['baths_total'] !== null)
            <div>
                <i class="fas fa-bath me-1 text-secondary"></i>
                @if($property['baths_full'] !== null && $property['baths_half'])
                    <strong>{{ $property['baths_full'] }}</strong> full +
                    <strong>{{ $property['baths_half'] }}</strong> half
                @else
                    <strong>{{ $property['baths_total'] }}</strong> bath{{ $property['baths_total'] != 1 ? 's' : '' }}
                @endif
            </div>
        @endif

        @if($property['sqft_display'])
            <div>
                <i class="fas fa-vector-square me-1 text-secondary"></i>
                <strong>{{ $property['sqft_display'] }}</strong> sqft
            </div>
        @endif

        @if($property['lot_size_acres'])
            <div>
                <i class="fas fa-ruler-combined me-1 text-secondary"></i>
                <strong>{{ $property['lot_size_acres'] }}</strong> acres
            </div>
        @endif

        @if($property['year_built'])
            <div>
                <i class="fas fa-calendar-alt me-1 text-secondary"></i>
                Built <strong>{{ $property['year_built'] }}</strong>
            </div>
        @endif

        @if($property['property_type'])
            <div>
                <i class="fas fa-home me-1 text-secondary"></i>
                {{ $property['property_type'] }}
                @if($property['property_sub_type'])
                    &mdash; {{ $property['property_sub_type'] }}
                @endif
            </div>
        @endif
    </div>

    {{-- =====================================================================
         Two-column layout: content left, sidebar right
    ===================================================================== --}}
    <div class="row g-4">

        {{-- ===== LEFT COLUMN: description + features ===== --}}
        <div class="col-12 col-lg-8">

            {{-- Description --}}
            @if($property['public_remarks'])
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                        <h5 class="mb-0 fw-semibold" style="font-size:1rem;">
                            <i class="fas fa-align-left me-2 text-primary"></i>Description
                        </h5>
                    </div>
                    <div class="card-body pt-2" style="font-size:.9rem;line-height:1.7;white-space:pre-line;">{{ $property['public_remarks'] }}</div>
                </div>
            @endif

            {{-- Interior features --}}
            @php
                $interiorGroups = array_filter([
                    'Appliances'    => $property['appliances'],
                    'Interior'      => $property['interior_features'],
                    'Flooring'      => $property['flooring'],
                    'Laundry'       => $property['laundry'],
                    'Cooling'       => $property['cooling'],
                    'Heating'       => $property['heating'],
                    'Fireplace'     => $property['fireplace_features'],
                    'Windows'       => $property['window_features'],
                    'Security'      => $property['security'],
                ]);
            @endphp

            @if(!empty($interiorGroups))
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                        <h5 class="mb-0 fw-semibold" style="font-size:1rem;">
                            <i class="fas fa-couch me-2 text-primary"></i>Interior
                        </h5>
                    </div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            @foreach($interiorGroups as $label => $items)
                                <div class="col-12 col-sm-6">
                                    <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.05em;">{{ $label }}</div>
                                    @foreach($items as $item)
                                        <div style="font-size:.875rem;">{{ $item }}</div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Exterior + structure --}}
            @php
                $exteriorGroups = array_filter([
                    'Exterior'          => $property['exterior_features'],
                    'Pool'              => $property['pool_features'],
                    'Spa'               => $property['spa_features'],
                    'Patio / Porch'     => $property['patio_porch'],
                    'Construction'      => $property['construction_materials'],
                    'Roof'              => $property['roof'],
                    'Foundation'        => $property['foundation'],
                    'Parking'           => $property['parking_features'],
                    'Other Structures'  => $property['other_structures'],
                    'Accessibility'     => $property['accessibility'],
                ]);
            @endphp

            @if(!empty($exteriorGroups))
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                        <h5 class="mb-0 fw-semibold" style="font-size:1rem;">
                            <i class="fas fa-tree me-2 text-primary"></i>Exterior &amp; Structure
                        </h5>
                    </div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            @foreach($exteriorGroups as $label => $items)
                                <div class="col-12 col-sm-6">
                                    <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.05em;">{{ $label }}</div>
                                    @foreach($items as $item)
                                        <div style="font-size:.875rem;">{{ $item }}</div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Utilities --}}
            @php
                $utilityGroups = array_filter([
                    'Utilities'     => $property['utilities'],
                    'Sewer'         => $property['sewer'],
                    'Water Source'  => $property['water_source'],
                ]);
            @endphp

            @if(!empty($utilityGroups))
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                        <h5 class="mb-0 fw-semibold" style="font-size:1rem;">
                            <i class="fas fa-bolt me-2 text-primary"></i>Utilities
                        </h5>
                    </div>
                    <div class="card-body pt-2">
                        <div class="row g-3">
                            @foreach($utilityGroups as $label => $items)
                                <div class="col-12 col-sm-6">
                                    <div class="text-uppercase text-muted fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.05em;">{{ $label }}</div>
                                    @foreach($items as $item)
                                        <div style="font-size:.875rem;">{{ $item }}</div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Community features --}}
            @if(!empty($property['community_features']))
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                        <h5 class="mb-0 fw-semibold" style="font-size:1rem;">
                            <i class="fas fa-users me-2 text-primary"></i>Community
                        </h5>
                    </div>
                    <div class="card-body pt-2">
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($property['community_features'] as $feat)
                                <span class="badge bg-light text-secondary border" style="font-size:.8rem;">{{ $feat }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>{{-- /col-lg-8 --}}

        {{-- ===== RIGHT SIDEBAR: key facts, HOA, schools, office ===== --}}
        <div class="col-12 col-lg-4">

            {{-- Key facts --}}
            @php
                $facts = array_filter([
                    'Status'           => $property['mls_status'],
                    'Days on Market'   => $property['days_on_market'] !== null ? $property['days_on_market'] . ' days' : null,
                    'Type'             => trim(implode(' — ', array_filter([$property['property_type'], $property['property_sub_type']]))) ?: null,
                    'Year Built'       => $property['year_built'],
                    'Stories'          => $property['stories'],
                    'Levels'           => $property['levels'],
                    'Garage'           => $property['garage']
                        ? ($property['garage_spaces'] ? $property['garage_spaces'] . '-car garage' : 'Yes')
                        : null,
                    'Carport'          => $property['carport_spaces'] ? $property['carport_spaces'] . '-space carport' : null,
                    'Pool'             => $property['pool'] ? 'Yes' : null,
                    'Spa'              => $property['spa'] ? 'Yes' : null,
                    'Waterfront'       => $property['waterfront'] ? 'Yes' : null,
                    'Water View'       => $property['water_view'] ? 'Yes' : null,
                    'View'             => !empty($property['view']) ? implode(', ', $property['view']) : null,
                    'Lot'              => $property['lot_size_acres']
                        ? $property['lot_size_acres'] . ' acres'
                        : ($property['lot_size_sqft'] ? number_format($property['lot_size_sqft']) . ' sqft' : null),
                    'Pets Allowed'     => $property['pets_allowed'],
                    'Senior 55+'       => $property['senior_community'] ? 'Yes' : null,
                    'CDD'              => $property['cdd'] ? 'Yes' : null,
                    'New Construction'  => $property['new_construction'] ? 'Yes' : null,
                ]);
            @endphp

            @if(!empty($facts))
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                        <h5 class="mb-0 fw-semibold" style="font-size:1rem;">
                            <i class="fas fa-circle-info me-2 text-primary"></i>Key Facts
                        </h5>
                    </div>
                    <div class="card-body pt-1 pb-2">
                        @foreach($facts as $label => $value)
                            <div class="d-flex justify-content-between align-items-baseline
                                        py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <span class="text-muted" style="font-size:.8rem;">{{ $label }}</span>
                                <span class="fw-semibold text-end" style="font-size:.875rem;max-width:60%;">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- HOA / Fees / Taxes --}}
            @if($property['hoa'] || $property['tax_annual'] || $property['cdd'])
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                        <h5 class="mb-0 fw-semibold" style="font-size:1rem;">
                            <i class="fas fa-dollar-sign me-2 text-primary"></i>Fees &amp; Taxes
                        </h5>
                    </div>
                    <div class="card-body pt-1 pb-2">

                        @if($property['hoa'])
                            <div class="d-flex justify-content-between align-items-baseline py-2 border-bottom">
                                <span class="text-muted" style="font-size:.8rem;">HOA Fee</span>
                                <span class="fw-semibold" style="font-size:.875rem;">
                                    {{ $property['hoa_fee_display'] ?? '—' }}
                                    @if($property['hoa_frequency'])
                                        <span class="text-muted fw-normal">/ {{ $property['hoa_frequency'] }}</span>
                                    @endif
                                </span>
                            </div>

                            @if($property['hoa_name'])
                                <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                                    <span class="text-muted flex-shrink-0 me-2" style="font-size:.8rem;">HOA Name</span>
                                    <span class="text-end" style="font-size:.8rem;max-width:65%;">{{ $property['hoa_name'] }}</span>
                                </div>
                            @endif

                            @if(!empty($property['hoa_amenities']))
                                <div class="py-2 border-bottom">
                                    <div class="text-muted mb-1" style="font-size:.8rem;">HOA Amenities</div>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($property['hoa_amenities'] as $amenity)
                                            <span class="badge bg-light text-secondary border" style="font-size:.75rem;">{{ $amenity }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif

                        @if($property['tax_annual'])
                            <div class="d-flex justify-content-between align-items-baseline py-2 {{ $property['cdd'] ? 'border-bottom' : '' }}">
                                <span class="text-muted" style="font-size:.8rem;">Annual Tax</span>
                                <span class="fw-semibold" style="font-size:.875rem;">{{ $property['tax_annual_display'] }}</span>
                            </div>
                        @endif

                        @if($property['cdd'])
                            <div class="d-flex justify-content-between align-items-baseline py-2">
                                <span class="text-muted" style="font-size:.8rem;">CDD</span>
                                <span class="fw-semibold" style="font-size:.875rem;">Yes</span>
                            </div>
                        @endif

                    </div>
                </div>
            @endif

            {{-- Schools --}}
            @if($property['school_elementary'] || $property['school_middle'] || $property['school_high'])
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                        <h5 class="mb-0 fw-semibold" style="font-size:1rem;">
                            <i class="fas fa-school me-2 text-primary"></i>Schools
                        </h5>
                    </div>
                    <div class="card-body pt-1 pb-2">
                        @if($property['school_elementary'])
                            <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                                <span class="text-muted flex-shrink-0 me-2" style="font-size:.8rem;">Elementary</span>
                                <span class="text-end" style="font-size:.875rem;max-width:65%;">{{ $property['school_elementary'] }}</span>
                            </div>
                        @endif
                        @if($property['school_middle'])
                            <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                                <span class="text-muted flex-shrink-0 me-2" style="font-size:.8rem;">Middle</span>
                                <span class="text-end" style="font-size:.875rem;max-width:65%;">{{ $property['school_middle'] }}</span>
                            </div>
                        @endif
                        @if($property['school_high'])
                            <div class="d-flex justify-content-between align-items-start py-2">
                                <span class="text-muted flex-shrink-0 me-2" style="font-size:.8rem;">High School</span>
                                <span class="text-end" style="font-size:.875rem;max-width:65%;">{{ $property['school_high'] }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Listing office (IDX-permitted — name only) --}}
            @if($property['list_office_name'])
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                        <h5 class="mb-0 fw-semibold" style="font-size:1rem;">
                            <i class="fas fa-building me-2 text-primary"></i>Listing Office
                        </h5>
                    </div>
                    <div class="card-body py-2" style="font-size:.875rem;">
                        {{ $property['list_office_name'] }}
                    </div>
                </div>
            @endif

            {{-- IDX copyright notice --}}
            <p class="text-muted mb-4" style="font-size:.7rem;line-height:1.5;">
                Listing information provided by Stellar MLS via Bridge Data Output &copy; {{ date('Y') }}.
                Data is deemed reliable but not guaranteed. All rights reserved.
                IDX information is provided exclusively for personal, non-commercial use.
            </p>

        </div>{{-- /col-lg-4 --}}

    </div>{{-- /row --}}

</div>
@endsection
