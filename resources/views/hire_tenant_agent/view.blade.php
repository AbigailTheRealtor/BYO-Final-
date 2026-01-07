@extends('layouts.main')

{{-- Combined Fee Display Helper Functions (display-only, no storage changes) --}}
@php
  $fmtMoney = function($v) {
    if ($v === null || $v === '') return null;
    $raw = preg_replace('/[^0-9.]/', '', (string)$v);
    if ($raw === '' || !is_numeric($raw)) return null;
    return '$' . number_format((float)$raw, 0);
  };

  $fmtPercent = function($v) {
    if ($v === null || $v === '') return null;
    $raw = preg_replace('/[^0-9.]/', '', (string)$v);
    if ($raw === '' || !is_numeric($raw)) return null;
    $num = (float)$raw;
    return (floor($num) == $num ? (string)(int)$num : (string)$num) . '%';
  };

  $joinParts = function($parts) {
    $parts = array_values(array_filter($parts, fn($p) => $p !== null && $p !== ''));
    return count($parts) ? implode(' + ', $parts) : null;
  };

  $basisText = function($basis) {
    return $basis ? ('of ' . $basis) : null;
  };
@endphp

@push('styles')
<!-- //Listing Description css  -->
<link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
<style>
    /* Chrome, Safari, Edge, Opera */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    /* Firefox */
    input[type=number] {
        -moz-appearance: textfield;
    }

    .fa-dollar,
    .fa-percent {
        padding: 0 20px;
        background: #facd34;
        color: #fff;
        border: 0;
        font-weight: 700 !important;
        line-height: 39px !important;
        margin-right: -5px;
        z-index: 1;
        border-radius: 3px 0 0 3px;
    }

    .form-control,
    .form-select {
        border-radius: 0.25rem;
        box-shadow: inset 0 1px 2px 0 rgb(66 71 112 / 12%);
        border-radius: 0.25rem;
        background-color: #fafafb;
        margin-bottom: 15px;
    }

    /* Section Title Hierarchy - Larger, bold, spaced, more prominent */
    .card-header h4,
    .section-title {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
        color: #0f1a24;
    }

    /* SECTION HEADER BAR — shorter + true vertical centering */
    .card-header.section-header {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start;
        padding: 12px 18px !important;
        min-height: 0 !important;
        margin-top: 1.25rem;
    }

    /* SECTION TITLE TEXT — remove default heading spacing */
    .section-header .section-title {
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1 !important;
        display: block;
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #0f1a24;
    }

    /* Services section - extra breathing room before header */
    .services-section-header {
        margin-top: 0.75rem !important;
    }

    hr {
        margin-top: 1.25rem;
        margin-bottom: 0.5rem;
    }

    /* Field row styling - improved line-height for scan-readability */
    .col-md-12.col-12.pt-2.fw-bold {
        line-height: 1.6;
        padding-top: 0.6rem !important;
        padding-bottom: 0.2rem;
    }

    .field-row {
        padding: 0.5rem 0;
        font-size: 0.95rem;
        line-height: 1.6;
    }

    .field-label {
        font-weight: 600;
        color: #34465c;
    }

    .field-value {
        font-weight: normal;
        color: #34465c;
    }

    /* Broker Compensation subsection headers - breathing room */
    h5.mt-3.mb-2 {
        padding-top: 0.75rem;
        margin-top: 1rem !important;
    }

    /* Fix blank space under section headers - reduce gap to first content */
    .card-body {
        padding-top: 12px !important;
    }

    .card-body > :first-child {
        margin-top: 0 !important;
    }

    /* Broker Compensation section text - match other section text color */
    .broker-compensation-section,
    .broker-compensation-section p,
    .broker-compensation-section .col-md-12,
    .broker-compensation-section .fw-bold {
        color: #34465c !important;
    }

    ul {
        --icon-size: 1em;
        --gutter: .5em;
        padding: 0 0 0 calc(var(--icon-size) + 2em);
    }

    ul li {
        padding-left: var(--gutter);
        color: #34465c;
    }

    ul:not(.services) li::marker {
        content: "\f101";
        /* FontAwesome Unicode */
        font-family: FontAwesome;
        font-size: var(--icon-size);
        /* color: #006e9f; */
        color: #11b7cf;
    }

    /* Services section - Tighter spacing and indentation */
    ul.services {
        list-style: none !important;
        padding-left: 1.2em;
        margin-top: 0.35rem;
        margin-bottom: 0.5rem;
    }

    ul.services li {
        padding: 0.15rem 0;
        color: #34465c;
        position: relative;
        padding-left: 0;
        list-style: none !important;
        line-height: 1.4;
    }

    ul.services li::marker {
        content: none !important;
    }

    ul.services li::before {
        content: "•";
        position: absolute;
        left: -0.9em;
        color: #34465c;
        font-size: 1.1em;
    }

    /* Service category title styling */
    .service-category-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-top: 1rem;
        margin-bottom: 0.5rem;
    }

    .removeBold {
        font-weight: normal;
    }

    /* Base button style */
    .btn-custom {
        width: 100% !important;
        color: white !important;
        border: none;
        padding: 10px 20px;
        min-width: 120px;
        font-weight: 500;
        border-radius: 4px;
        cursor: pointer;
        text-align: center;
        display: inline-block;
    }

    .biding-btn {
        width: 31.5%;
    }

    /* Accept (green) - always solid green background */
    .btn-accept {
        background-color: #28a745 !important;
        color: #ffffff !important;
    }

    .btn-accept:hover {
        background-color: #218838 !important;
    }

    /* Reject (red) - always solid red background */
    .btn-reject {
        background-color: #dc3545 !important;
        color: #ffffff !important;
    }

    .btn-reject:hover {
        background-color: #c82333 !important;
    }

    /* Counter (blue) - always solid blue background, same size as Reject */
    .btn-counter {
        background-color: #0d6efd !important;
        color: #ffffff !important;
    }

    .btn-counter:hover {
        background-color: #0b5ed7 !important;
    }

    .view-btn {
        padding: 6px !important;
    }

    .services-offered {
        padding: 23px !important;
    }

    /* Left column content - vertically centered with symmetrical padding */
    .leftCol .card.description .card-body {
        padding-top: 1.75rem;
        padding-bottom: 1.75rem;
    }

    .leftCol .card.description {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }

    @media screen and (max-width: 800px) {
        .accordion-body-padding {
            padding: 7px !important;
        }

        .alert-font {
            font-size: 10px;
        }

        .counter-font {
            font-size: 15px;
        }
    }
    
    /* Bid card accordion chevron rotation (custom JS toggle) */
    .bid-accordion-header .bid-chevron {
        transition: transform 0.3s ease;
    }
    .bid-accordion-header:hover {
        background-color: #f8f9fa !important;
    }
    
    /* Fix white space below bid cards - ensure collapse content uses natural height */
    .card.higestBider .accordion-item > .card.mb-3 {
        margin-bottom: 0.75rem;
    }
    .card.higestBider .accordion-item > .card.mb-3 > .collapse {
        height: auto;
    }
    
    /* Bid action buttons - matched sizing for Edit/Withdraw */
    .bid-action-btn {
        min-width: 140px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.875rem;
        border: none !important;
        box-shadow: none;
    }
    .bid-action-btn:hover {
        opacity: 0.9;
    }
</style>
@endpush
@section('content')
<!-- DEBUG: Hire Tenant Actual Listing Display -->
@php
$auth_id = auth()->user() ? auth()->user()->id : 0;
@endphp
<!-- Gallery Start Here  -->
<div class="container listingDescription">
    <div class="row">
        <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
            <div class="card description">
                <div class="card-header section-header">
                    <h4 class="section-title">Listing Details:</h4>
                </div>
                <div class="card-body">
                    <div class="row" style="flex-wrap: wrap;">
                        {{-- Listing Status removed from here - now only shown as badge above Listing ID in header --}}
                        @if (@$auction->get->listing_title != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Listing Title:
                            <span class="removeBold">{{ @$auction->get->listing_title }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->working_with_agent != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Current Representation Status with Broker:
                            <span class="removeBold">{{ @$auction->get->working_with_agent }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->desired_agent_hire_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Desired Agent Hire Date:
                            <span class="removeBold">
                                {{ date('F j, Y', strtotime(@$auction->get->desired_agent_hire_date)) }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->listing_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Listing Date:
                            <span
                                class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->listing_date)) }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->expiration_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Expiration Date:
                            <span class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->expiration_date)) }}
                            </span>
                        </div>
                        @endif
                        @if (@$auction->get->auction_type != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Listing Type:
                            <span class="removeBold"> {{ @$auction->get->auction_type }}
                            </span>
                        </div>
                        @endif

                        @if (@$auction->get->auction_time != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Bidding Period Length:
                            <span class="removeBold"> {{ @$auction->get->auction_time }}
                            </span>
                        </div>
                        @endif
                        @if (@$auction->get->meeting_Preference != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Meeting Preference:
                            <span class="removeBold"> {{ @$auction->get->meeting_Preference }}
                            </span>
                        </div>
                        @endif

                    </div>
                    <hr>
                    <div class="card-header section-header">
                        <h4 class="section-title">Property Preferences: </h4>
                    </div>

                    <div class="row" style="flex-wrap: wrap;">

                        @if (@$auction->get->cities != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"> Acceptable Cities:
                            @if (gettype(@$auction->get->cities) == 'array')
                            @php
                                $cleanCities = array_map(function($city) {
                                    return preg_replace('/,\s*[A-Z]{2}$/', '', trim($city));
                                }, @$auction->get->cities);
                            @endphp
                            <span class="removeBold">{{ implode('; ', $cleanCities) }}</span>
                            @endif
                        </div>
                        @endif
                        @if (@$auction->get->counties != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable Counties:
                            @if (gettype(@$auction->get->counties) == 'array')
                            @php
                                $cleanCounties = array_map(function($county) {
                                    return preg_replace('/,\s*[A-Z]{2}$/', '', trim($county));
                                }, @$auction->get->counties);
                            @endphp
                            <span class="removeBold">{{ implode('; ', $cleanCounties) }}</span>
                            @endif
                        </div>
                        @endif
                        @if (@$auction->get->states != null || @$auction->get->state != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Acceptable State:
                            <span class="removeBold">
                                @if (is_array(@$auction->get->states))
                                    {{ implode('; ', @$auction->get->states) }}
                                @elseif (@$auction->get->state)
                                    {{ @$auction->get->state }}
                                @endif
                            </span>
                        </div>
                        @endif
                        @if (!empty($auction->get->zipCodes) && is_array($auction->get->zipCodes))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Zip Code:

                            @foreach ($auction->get->zipCodes as $zip)
                            <span class="removeBold badge bg-secondary">
                                {{ @$zip }}
                            </span>
                            @endforeach

                        </div>
                        @endif
                        {{-- @if (@$auction->get->state != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"> State:
                                    <span class="removeBold">{{ @$auction->get->state }}</span>
                    </div>
                    @endif --}}

                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Acceptable Property Type:
                        <span class="removeBold">{{ @$auction->get->property_type }}</span>
                    </div>
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Acceptable Property Styles:
                        @if (gettype(@$auction->get->property_items) == 'array')
                        @foreach (@$auction->get->property_items as $item)
                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                        @endforeach
                        @endif
                    </div>
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Acceptable Property Conditions:
                        @if (gettype(@$auction->get->condition_prop_buyer) == 'array')
                        @foreach (array_filter(@$auction->get->condition_prop_buyer) as $item)
                        <span class="removeBold"> {{ $item }}</span>
                        @if ($item == 'Other')
                        <span class="removeBold"> {{ @$auction->get->other_property_condition }}</span>
                        @endif
                        @endforeach
                        @endif

                    </div>
                    @if (@$auction->get->condition_prop != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Acceptable Leasing
                        Space:
                        <span class="removeBold">{{ $auction->get->leasing_space }}

                        </span>
                    </div>
                    @endif
                    @if (@$auction->get->bedrooms != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Minimum Bedrooms
                        Needed:
                        <span class="removeBold">
                            @if (@$auction->get->bedrooms != 'Other')
                            {{ $auction->get->bedrooms }}
                            @elseif(@$auction->get->bedrooms == 'Other')
                            {{ @$auction->get->other_bedrooms }}
                            @endif
                        </span>
                    </div>
                    @endif
                    @if (@$auction->get->bathrooms != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Minimum Bathrooms
                        Needed:
                        <span class="removeBold">
                            @if (@$auction->get->bathrooms != 'Other')
                            {{ $auction->get->bathrooms }}
                            @elseif(@$auction->get->bathrooms == 'Other')
                            {{ @$auction->get->other_bathrooms }}
                            @endif
                        </span>
                    </div>
                    @endif

                    @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null')
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Minimum Sqft Needed:
                        <span class="removeBold">

                            {{ str_replace(',', '', $auction->get->minimum_heated_square ?? '') }}

                        </span>
                    </div>
                    @endif
                    @if (@$auction->get->minimum_leaseable != null && @$auction->get->minimum_leaseable != 'null')
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Minimum Net Leasable
                        Sqft Needed:
                        <span class="removeBold">
                            {{ number_format((int)str_replace(',', '', $auction->get->minimum_leaseable ?? '0')) }}
                        </span>
                    </div>
                    @endif

                    {{-- Garage/Parking Features Needed (Commercial only) - IMMEDIATELY after Min Net Leasable --}}
                    @if (@$auction->get->property_type === 'Commercial Property')
                        @if (@$auction->get->garage_parking_spaces != null && @$auction->get->garage_parking_spaces != '')
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Garage/Parking Features Needed:
                            <span class="removeBold">{{ @$auction->get->garage_parking_spaces }}</span>
                        </div>
                        @endif
                        @php
                            $garageParkingOptions = @$auction->get->garage_parking_spaces_option;
                            if (is_string($garageParkingOptions)) {
                                $garageParkingOptions = json_decode($garageParkingOptions, true) ?? [];
                            }
                            $garageParkingOptions = is_array($garageParkingOptions) ? array_filter($garageParkingOptions) : [];
                        @endphp
                        @if (!empty($garageParkingOptions))
                        <div class="col-md-12 col-12 pt-2 fw-bold">
                            Garage/Parking Features:
                            @foreach ($garageParkingOptions as $feature)
                                @if ($feature !== 'Other' && !empty($feature))
                                <span class="removeBold badge bg-secondary">{{ $feature }}</span>
                                @endif
                            @endforeach
                            @if (!empty(@$auction->get->other_parking_space_wrapper))
                            <span class="removeBold badge bg-secondary">{{ @$auction->get->other_parking_space_wrapper }}</span>
                            @endif
                        </div>
                        @endif
                    @endif

                    {{-- Furnishings Needed (Residential only) --}}
                    @if (@$auction->get->property_type === 'Residential Property' && @$auction->get->tenant_require != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Furnishings Needed:
                        <span class="removeBold badge bg-secondary">{{ @$auction->get->tenant_require }}</span>
                    </div>
                    @endif

                    {{-- Carport Needed with conditional spaces --}}
                    @if (@$auction->get->carport_needed != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Carport Needed:
                        <span class="removeBold">{{ @$auction->get->carport_needed }}</span>
                    </div>
                    @if (@$auction->get->carport_needed === 'Yes' && !empty(@$auction->get->other_carport_needed) && @$auction->get->other_carport_needed !== 'null')
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Number of Carport Spaces Needed:
                        <span class="removeBold">{{ @$auction->get->other_carport_needed }}</span>
                    </div>
                    @endif
                    @endif

                    {{-- Garage Needed with conditional spaces (Residential) --}}
                    @if (@$auction->get->property_type === 'Residential Property' && @$auction->get->garage_needed != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Garage Needed:
                        <span class="removeBold">{{ @$auction->get->garage_needed }}</span>
                    </div>
                    @if (@$auction->get->garage_needed === 'Yes' && !empty(@$auction->get->other_garage_needed) && @$auction->get->other_garage_needed !== 'null')
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Number of Garage Spaces Needed:
                        <span class="removeBold">{{ @$auction->get->other_garage_needed }}</span>
                    </div>
                    @endif
                    @endif

                    @php
                    // Normalize pool_type to an array of key => bool
                    $poolTypeRaw = optional($auction->get)->pool_type;
                    if (is_string($poolTypeRaw)) {
                    $poolTypeRaw = json_decode($poolTypeRaw, true);
                    }
                    if (is_object($poolTypeRaw)) {
                    $poolTypeRaw = (array) $poolTypeRaw;
                    }
                    $poolTypeRaw = is_array($poolTypeRaw) ? $poolTypeRaw : [];

                    // Keep only truthy entries and join their keys
                    $poolTypeList = collect($poolTypeRaw)
                    ->filter(fn($v) => $v === true || $v === 1 || $v === '1' || $v === 'true')
                    ->keys()
                    ->implode(', ');
                    @endphp

                    @if (optional($auction->get)->pool_needed === 'Yes' && $poolTypeList !== '')
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        
                        Pool Needed:
                        <span class="removeBold">Yes ({{ ucwords($poolTypeList) }})</span>
                    </div>
                    @elseif (in_array(optional($auction->get)->pool_needed, ['No', 'Optional'], true))
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        
                        Pool Needed:
                        <span class="removeBold">{{ optional($auction->get)->pool_needed }}</span>
                    </div>
                    @endif

                    @if (@$auction->get->view_preference != null || @$auction->get->other_preferences != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold"> View
                        Preference Needed:
                        @if (is_array(@$auction->get->view_preference))
                        @foreach (@$auction->get->view_preference as $item)
                            @if ($item !== 'Other')
                            <span class="removeBold badge bg-secondary">{{ @$item }}</span>
                            @endif
                        @endforeach
                        @endif
                        @if (!empty(@$auction->get->other_preferences))
                        <span class="removeBold badge bg-secondary">{{ @$auction->get->other_preferences }}</span>
                        @endif
                    </div>
                    @endif
                    @if (@$auction->get->property_type === 'Residential Property' && !empty(@$auction->get->leasing_55_plus))
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Age-Restricted Community:
                        <span class="removeBold">{{ @$auction->get->leasing_55_plus }}</span>
                    </div>
                    @endif

                    @if (@$auction->get->non_negotiable_amenities != null || @$auction->get->other_non_negotiable_amenities != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        Non-Negotiable Amenities and Property Features:
                        @if (is_array(@$auction->get->non_negotiable_amenities))
                        @foreach (@$auction->get->non_negotiable_amenities as $item)
                            @if ($item !== 'Other')
                            <span class="removeBold badge bg-secondary">{{ @$item }}</span>
                            @endif
                        @endforeach
                        @endif
                        @if (!empty(@$auction->get->other_non_negotiable_amenities))
                        <span class="removeBold badge bg-secondary">{{ @$auction->get->other_non_negotiable_amenities }}</span>
                        @endif
                    </div>
                    @endif
                </div>
                <hr>
                <div class="card-header section-header">
                    <h4 class="section-title">Leasing Terms: </h4>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"> Maximum
                    Monthly Lease Price:
                    <span class="removeBold">
                        ${{ str_replace(',', '', $auction->get->budget ?? '') }}</span>
                </div>

                @if (@$auction->get->lease_for != null || $auction->get->other_lease_for != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"> Offered
                    Lease Term:

                    <ul>
                        @if (@$auction->get->lease_for)
                        @foreach ($auction->get->lease_for as $lease)
                            @if ($lease !== 'Other')
                            <li style="font-size: 16px;">{{ $lease }}</li>
                            @endif
                        @endforeach
                        @endif
                        @if (!empty($auction->get->other_lease_for))
                        <li style="font-size: 16px;">{{ $auction->get->other_lease_for }}</li>
                        @endif
                    </ul>

                </div>
                @endif
                <div class="col-md-12 col-12 pt-2 fw-bold"> Offered
                    Lease Date:
                    <span class="removeBold">
                        @if (@$auction->get->lease_date)
                        {{ date('F j, Y', strtotime(@$auction->get->lease_date)) }}
                        @endif
                    </span>
                </div>

                <div class="col-md-12 col-12 pt-2 fw-bold"> Leasing
                    Space:

                </div>

                @if (!empty($auction->get->leasing_spaces_tenant))
                <ul>
                    @foreach ($auction->get->leasing_spaces_tenant as $leasing_space)
                    <li style="font-size: 16px;">
                        {{ $leasing_space }}
                    </li>
                    @endforeach
                </ul>
                @endif
                <hr>
                <div class="card-header section-header">
                    <h4 class="section-title">Pre-Screening: </h4>
                </div>
                @if ($auction->get->number_occupant)
                <div class="col-md-12 col-12 pt-2 fw-bold"> Number
                    of Occupants:
                    <span class="removeBold">
                        {{ $auction->get->number_occupant ?? '' }}</span>
                </div>
                @endif
                @if ($auction->get->monthly_income)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Estimated Monthly Net Household Income:
                    <span class="removeBold">

                        ${{ str_replace(',', '', $auction->get->monthly_income ?? '') }}

                    </span>
                </div>
                @endif
                @if (@$auction->get->pets)
                <div class="col-md-12 col-12 pt-2 fw-bold"> Pets:
                    <span class="removeBold">
                        {{ @$auction->get->pets }}</span>
                </div>
                @endif
                @if (@$auction->get->pets == 'Yes')
                <div class="col-md-12 col-12 pt-2 fw-bold"> Number
                    of Pets:
                    <span class="removeBold">
                        {{ @$auction->get->number_of_pets != '' ? @$auction->get->number_of_pets : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"> Type of
                    Pets:
                    <span class="removeBold">
                        {{ @$auction->get->type_of_pets != '' ? @$auction->get->type_of_pets : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"> Breed of
                    Pets:
                    <span class="removeBold">
                        {{ @$auction->get->breed_of_pets != '' ? @$auction->get->breed_of_pets : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"> Weight
                    of Pets (lbs):
                    <span class="removeBold">
                        {{ @$auction->get->weight_of_pets != '' ? @$auction->get->weight_of_pets : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"> Service
                    Animal:
                    <span class="removeBold">
                        {{ @$auction->get->service_animal != '' ? @$auction->get->service_animal : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Emotional Support Animal:
                    <span class="removeBold">
                        {{ @$auction->get->support_animal != '' ? @$auction->get->support_animal : '' }}</span>
                </div>
                @endif

                @if (@$auction->get->screening_concerns != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Screening Concerns That May Affect Rental Approval:
                    <span class="removeBold">
                        {{ $auction->get->screening_concerns ?? '' }}</span>
                </div>
                @endif
                @if (@$auction->get->screening_concerns == 'Yes' && @$auction->get->screening_concerns_explanation)
                <ul>

                    <li style="font-size: 16px;">
                        {{ @$auction->get->screening_concerns_explanation != '' ? @$auction->get->screening_concerns_explanation : '' }}
                    </li>
                </ul>
                @endif

                <hr>

                @php
                // Check if services exist before showing the section
                $hasServices = !empty(@$auction->get->services) || !empty(@$auction->get->other_services);
                @endphp

                @if ($hasServices)
                <div class="card-header section-header services-section-header">
                    <h4 class="section-title">Services: </h4>
                </div>

                @php
                // Define service categories based on property type
                $isResidential = @$auction->get->property_type === 'Residential Property';
                $isCommercial = @$auction->get->property_type === 'Commercial Property';

                // Residential service categories (exact match with listing creation form)
                $residentialCategories = [
                    '📢 Tenant Criteria Marketing & Promotion' => [
                        'Create a branded flyer summarizing the Tenant’s rental criteria',
                        'Post the Tenant’s rental criteria on Craigslist under the "Real Estate Wanted" section',
                        'Share the Tenant’s rental criteria on Nextdoor in Neighborhood or Community Groups',
                        'Promote the Tenant’s rental criteria on Facebook in Rental or Housing Groups',
                        'Share the Tenant’s rental criteria on Instagram using posts, stories, or reels',
                        'Promote the Tenant’s rental criteria on LinkedIn in Real Estate or Housing Groups',
                        'Upload a TikTok video summarizing the Tenant’s rental criteria',
                        'Upload a YouTube video summarizing the Tenant’s rental criteria',
                        'Launch a mass email campaign promoting the Tenant’s rental criteria',
                        'Distribute branded postcards or flyers in the Tenant’s preferred neighborhoods',
                        'Launch hyperlocal digital ads targeting the Tenant’s preferred rental areas',
                    ],
                    '🔍 Property Search, Alerts & Matching' => [
                        'Send email alerts with new listings from the MLS that match the Tenant’s rental criteria',
                        'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant’s rental criteria',
                        'Communicate with the Landlord’s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
                        'Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit',
                    ],
                    '🏡 Property Showings & Virtual Tours' => [
                        'Schedule and attend property showings with the Tenant',
                        'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
                        'Preview properties on behalf of the Tenant upon request',
                        'Provide factual observations on property layout and condition',
                    ],
                    '📝 Tenant Application Support' => [
                        'Provide the Tenant with application instructions or links to an online rental application platform',
                        'Gather and organize required supporting documents (e.g., identification, income verification, reference letters)',
                        'Submit complete and organized application packages to the Landlord’s Agent, Landlord, or Property Manager for review',
                        'Answer questions about the application process, screening timelines, and required documentation',
                    ],
                    '📃 Lease Preparation & Execution' => [
                        'Review lease offers and assist the Tenant in preparing questions or requested changes',
                        'Coordinate lease negotiation with the Landlord’s Agent, Landlord, or Property Manager',
                        'Assist with completing required lease disclosures and reviewing key lease terms',
                        'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
                    ],
                    '🚚 Move-In Support & Coordination' => [
                        'Coordinate move-in date and key handoff logistics with the Landlord’s Agent, Landlord or Property Manager',
                        'Confirm completion of any agreed-upon pre-move-in cleaning or repairs',
                        'Provide a utility setup checklist and local provider resources',
                        'Share a move-in checklist for documentation and property condition review',
                        'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods',
                    ],
                    '💡 Leasing Strategy & Guidance' => [
                        'Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions',
                        'Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)',
                        'Provide general guidance on Tenant rights and Landlord responsibilities under state law',
                        'Provide general guidance on lease clauses, payment terms, and renewal options',
                    ],
                ];

                // Commercial service categories (exact match with listing creation form)
                $commercialCategories = [
                    '📢 Tenant Criteria Marketing & Promotion' => [
                        'Create a branded flyer summarizing the Tenant’s leasing criteria',
                        'Post the Tenant’s leasing criteria on Craigslist under the "Office/Commercial" or "Retail" section',
                        'Promote the Tenant’s leasing criteria on Facebook in Commercial Leasing or Business Groups',
                        'Share the Tenant’s leasing criteria on Instagram using posts, stories, or reels',
                        'Promote the Tenant’s leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups',
                        'Upload a TikTok video summarizing the Tenant’s leasing criteria',
                        'Upload a YouTube video summarizing the Tenant’s leasing criteria',
                        'Launch a mass email campaign promoting the Tenant’s leasing criteria',
                        'Distribute branded postcards or flyers in the Tenant’s preferred neighborhoods',
                        'Launch hyperlocal digital ads targeting the Tenant’s preferred leasing areas',
                    ],
                    '🔍 Property Search, Alerts & Matching' => [
                        'Send listing alerts from commercial platforms (e.g., LoopNet, Crexi, CoStar, or local MLS) that match the Tenant’s leasing criteria',
                        'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant’s rental criteria',
                        'Communicate with the Landlord’s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
                        'Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment',
                    ],
                    '🏢 Property Showings & Virtual Tours' => [
                        'Schedule and attend property tours with the Tenant',
                        'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
                        'Preview properties on behalf of the Tenant upon request',
                        'Provide factual notes on layout, access, parking, visibility, and other operational considerations',
                    ],
                    '📝 Tenant Application Support' => [
                        'Provide the Tenant with application instructions or links to online platforms',
                        'Gather and organize required supporting documents (e.g., business licenses, financials, references)',
                        'Submit complete and organized application packages to the Landlord’s Agent, Landlord, or Property Manager',
                    ],
                    '📃 Lease Preparation, LOI & Execution' => [
                        'Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant’s business needs and proposed terms',
                        'Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)',
                        'Coordinate with the Landlord’s Agent, Landlord or Property Manager to finalize lease terms',
                        'Review lease drafts and coordinate revisions through appropriate channels',
                        'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
                        'Track required deposits, rent commencement, and key lease dates to ensure move-in readiness',
                    ],
                    '🚚 Move-In Support & Coordination' => [
                        'Coordinate move-in date and key handoff logistics with the Landlord, Landlord’s Agent, or Property Manager',
                        'Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout',
                        'Provide a utility setup checklist and local provider resources',
                        'Share a move-in checklist for documentation and property condition review',
                        'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods',
                    ],
                    '💡 Leasing Strategy & Guidance' => [
                        'Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends',
                        'Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences',
                        'Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law',
                        'Provide general guidance on lease clauses, escalation terms, and space usage considerations',
                    ],
                ];

                $categories = $isCommercial ? $commercialCategories : $residentialCategories;
                $allServices = is_array(@$auction->get->services) ? $auction->get->services : [];
                $otherServices = is_array(@$auction->get->other_services) ? $auction->get->other_services : [];
                @endphp

                <div class="col-md-12 col-12 pt-2">
                    @foreach ($categories as $categoryName => $categoryServices)
                        @php
                            $matchedServices = array_filter($allServices, function($service) use ($categoryServices) {
                                return in_array($service, $categoryServices);
                            });
                        @endphp
                        @if (!empty($matchedServices))
                        <div class="mt-3">
                            <strong>{{ $categoryName }}</strong>
                            <ul class="services">
                                @foreach ($matchedServices as $service)
                                <li style="font-size: 16px;">{{ $service }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    @endforeach

                    @if (!empty($otherServices))
                    <div class="mt-3">
                        <strong>✍️ Additional Services</strong>
                        <ul class="services">
                            @foreach ($otherServices as $other_service)
                            <li style="font-size: 16px;">{{ $other_service }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
                @endif
                <hr>
                @if (@$auction->get->additional_details != null)
                <div class="card-header section-header">
                    <h4 class="section-title">Additional Details: </h4>
                </div>

                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Additional Details:<span class="removeBold">
                        {{ $auction->get->additional_details ?? '' }}</span>
                </div>
                @endif

                <hr />
                <div class="card-header section-header">
                    <h4 class="section-title">Broker Compensation & Agency Agreement Terms</h4>
                </div>

                <!-- Tenant’s Broker Compensation Sub-section -->
                <h5 class="mt-3 mb-2"><strong>Tenant’s Broker Compensation:</strong></h5>
                <div class="broker-compensation-section">

                @if (@$auction->get->commission_structure != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Tenant’s Broker Commission Structure:
                    <span class="removeBold">
                        @php
                            $commissionDisplay = @$auction->get->commission_structure;
                            if ($commissionDisplay === 'Included in Offer') {
                                $commissionDisplay = 'Requested From Landlord in the Offer';
                            } elseif ($commissionDisplay === 'Out-of-Pocket Payment') {
                                $commissionDisplay = 'Tenant Pays Out-of-Pocket';
                            }
                        @endphp
                        {{ $commissionDisplay }}
                    </span>
                </div>
                @endif

@if (@$auction->get->lease_fee_type != null)
                @php
                    // Build combined Tenant's Broker Commission Fee display for listing
                    $listingLeaseFeeType = @$auction->get->lease_fee_type ?? '';
                    $listingLeaseFeeCombined = '—';
                    
                    if ($listingLeaseFeeType === 'Flat Fee' && @$auction->get->lease_fee_flat) {
                        $listingLeaseFeeCombined = $fmtMoney(@$auction->get->lease_fee_flat);
                    } elseif ($listingLeaseFeeType === 'Percentage of the Gross Lease Value' && @$auction->get->lease_fee_percentage) {
                        $listingLeaseFeeCombined = $fmtPercent(@$auction->get->lease_fee_percentage) . ' of Gross Lease Value';
                    } elseif ($listingLeaseFeeType === 'Percentage of Monthly Rent' && @$auction->get->lease_fee_percentage_monthly_rent) {
                        $display = $fmtPercent(@$auction->get->lease_fee_percentage_monthly_rent) . ' of Monthly Rent';
                        if (@$auction->get->lease_fee_percentage_monthly_number) {
                            $display .= ' x ' . @$auction->get->lease_fee_percentage_monthly_number . ' Months';
                        }
                        $listingLeaseFeeCombined = $display;
                    } elseif ($listingLeaseFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                        $listingLeaseFeeCombined = $joinParts([
                            $fmtMoney(@$auction->get->lease_fee_flat_combo),
                            @$auction->get->lease_fee_percentage_combo ? ($fmtPercent(@$auction->get->lease_fee_percentage_combo) . ' of Gross Lease Value') : null,
                        ]) ?? '—';
                    } elseif ($listingLeaseFeeType === 'Percentage of the Net Aggregate Rent' && @$auction->get->lease_fee_percentage_net) {
                        $listingLeaseFeeCombined = $fmtPercent(@$auction->get->lease_fee_percentage_net) . ' of Net Aggregate Rent';
                    } elseif ($listingLeaseFeeType === 'Flat Fee + Percentage of the Net Aggregate Rent') {
                        $listingLeaseFeeCombined = $joinParts([
                            $fmtMoney(@$auction->get->lease_fee_flat_combo_net),
                            @$auction->get->lease_fee_percentage_combo_net ? ($fmtPercent(@$auction->get->lease_fee_percentage_combo_net) . ' of Net Aggregate Rent') : null,
                        ]) ?? '—';
                    } elseif (strtolower($listingLeaseFeeType) === 'other' && @$auction->get->lease_fee_other) {
                        $listingLeaseFeeCombined = @$auction->get->lease_fee_other;
                    } elseif ($listingLeaseFeeType) {
                        $listingLeaseFeeCombined = $listingLeaseFeeType;
                    }
                @endphp
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Tenant's Broker Commission Fee:
                    <span class="removeBold">{{ $listingLeaseFeeCombined }}</span>
                </div>
                @endif

                @if (@$auction->get->broker_fee_timing != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Payment Timing for Broker Fees:
                    <span class="removeBold">
                        @if (@$auction->get->broker_fee_timing === 'other')
                            {{ $auction->get->broker_fee_timing_other ?? '' }}
                        @else
                            {{ $auction->get->broker_fee_timing ?? '' }}
                        @endif
                    </span>
                </div>
                @endif

                @if (@$auction->get->broker_fee_days_from_rent != null || @$auction->get->broker_fee_days_after_lease != null || @$auction->get->broker_fee_days_after_rent != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Calendar Days to Pay:
                    <span class="removeBold">{{ @$auction->get->broker_fee_days_from_rent ?? @$auction->get->broker_fee_days_after_lease ?? @$auction->get->broker_fee_days_after_rent ?? '' }}</span>
                </div>
                @endif

                <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                <!-- Purchase Fee Details Sub-section -->
                <h5 class="mt-3 mb-2"><strong>Purchase Fee Details:</strong></h5>

                @if (@$auction->get->interested_purchase_fee_type != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Interested in Purchasing a Property:
                    <span class="removeBold">{{ $auction->get->interested_purchase_fee_type ?? '' }}</span>
                </div>
                @endif

@if (@$auction->get->interested_purchase_fee_type === 'Yes' && @$auction->get->purchase_fee_type != null)
                @php
                    // Build combined Purchase Fee display for listing
                    $listingPurchaseFeeType = @$auction->get->purchase_fee_type ?? '';
                    $listingPurchaseFeeCombined = '—';
                    
                    if ($listingPurchaseFeeType === 'Flat Fee') {
                        $listingPurchaseFeeCombined = $fmtMoney(@$auction->get->purchase_fee_flat) ?? '—';
                    } elseif ($listingPurchaseFeeType === 'Percentage of the Total Purchase Price') {
                        $pct = @$auction->get->purchase_fee_percentage;
                        $listingPurchaseFeeCombined = $pct ? ($fmtPercent($pct) . ' of Total Purchase Price') : '—';
                    } elseif ($listingPurchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                        $listingPurchaseFeeCombined = $joinParts([
                            $fmtMoney(@$auction->get->purchase_fee_flat_combo),
                            @$auction->get->purchase_fee_percentage_combo ? ($fmtPercent(@$auction->get->purchase_fee_percentage_combo) . ' of Total Purchase Price') : null,
                        ]) ?? '—';
                    } elseif ($listingPurchaseFeeType === 'other') {
                        $listingPurchaseFeeCombined = @$auction->get->purchase_fee_other ?? '—';
                    }
                @endphp
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Purchase Fee:
                    <span class="removeBold">{{ $listingPurchaseFeeCombined }}</span>
                </div>
                @endif

                <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                <!-- Lease-Option Details Sub-section -->
                <h5 class="mt-3 mb-2"><strong>Lease-Option Details:</strong></h5>

                @if (@$auction->get->interested_lease_option_agreement != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Interested in a Lease-Option Agreement:
                    <span class="removeBold">{{ $auction->get->interested_lease_option_agreement ?? '' }}</span>
                </div>
                @endif

                @if (@$auction->get->interested_lease_option_agreement === 'Yes')
                @if (@$auction->get->lease_value != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Compensation Type (When Option Is Created):
                    <span class="removeBold">
                        @if (@$auction->get->lease_type === 'percent')
                            {{ $auction->get->lease_value }}%
                        @else
                            ${{ number_format((float)str_replace(',', '', $auction->get->lease_value), 0) }}
                        @endif
                    </span>
                </div>
                @endif

                @if (@$auction->get->purchase_value != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Compensation Type (If Purchase Option is Exercised):
                    <span class="removeBold">
                        @if (@$auction->get->purchase_type === 'percent')
                            {{ $auction->get->purchase_value }}%
                        @else
                            ${{ number_format((float)str_replace(',', '', $auction->get->purchase_value), 0) }}
                        @endif
                    </span>
                </div>
                @endif
                @endif

                <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                <!-- Legal Terms Sub-section -->
                <h5 class="mt-3 mb-2"><strong>Legal Terms:</strong></h5>

                @if (@$auction->get->protection_period != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Protection Period Timeframe:
                    <span class="removeBold">{{ $auction->get->protection_period ?? '' }} Days</span>
                </div>
                @endif

                @if (@$auction->get->early_termination_fee_option != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Early Termination Fee:
                    <span class="removeBold">{{ $auction->get->early_termination_fee_option ?? '' }}</span>
                </div>
                @endif

                @if (@$auction->get->early_termination_fee_option === 'Yes' && @$auction->get->early_termination_fee_amount != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Termination Fee Amount:
                    <span class="removeBold">${{ number_format((float)str_replace(',', '', $auction->get->early_termination_fee_amount), 0) }}</span>
                </div>
                @endif

                @if (@$auction->get->retainer_fee_option != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Retainer Fee:
                    <span class="removeBold">{{ $auction->get->retainer_fee_option ?? '' }}</span>
                </div>
                @endif

                @if (@$auction->get->retainer_fee_option === 'Yes' && @$auction->get->retainer_fee_amount != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Retainer Fee Amount:
                    <span class="removeBold">${{ number_format((float)str_replace(',', '', $auction->get->retainer_fee_amount), 0) }}</span>
                </div>
                @endif

                @if (@$auction->get->retainer_fee_option === 'Yes' && @$auction->get->retainer_fee_application != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Retainer Fee Application:
                    <span class="removeBold">
                        {{ $auction->get->retainer_fee_application === 'applied' ? 'Applied toward final compensation' : 'Charged in addition to final compensation' }}
                    </span>
                </div>
                @endif

                @if (@$auction->get->agency_agreement_timeframe != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Tenant Agency Agreement Timeframe:
                    <span class="removeBold">
                        {{ $auction->get->agency_agreement_timeframe === 'Other' ? $auction->get->agency_agreement_custom : $auction->get->agency_agreement_timeframe }}
                    </span>
                </div>
                @endif

                <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                <!-- Brokerage Relationship Sub-section -->
                <h5 class="mt-3 mb-2"><strong>Brokerage Relationship:</strong></h5>

                @if (@$auction->get->brokerage_relationship != null)
                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Acceptable Brokerage Relationship:
                    <span class="removeBold">{{ $auction->get->brokerage_relationship ?? '' }}</span>
                </div>
                @endif

                @if (@$auction->get->additional_details_broker != null)
                <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                <!-- Additional Terms Sub-section (Separate from Brokerage Relationship) -->
                <h5 class="mt-3 mb-2"><strong>Additional Terms:</strong></h5>

                <div class="col-md-12 col-12 pt-2 fw-bold">
                    Additional Terms:
                    <span class="removeBold">{{ $auction->get->additional_details_broker }}</span>
                </div>
                @endif

                </div> <!-- end broker-compensation-section -->
                <hr />
                <div class="card-header section-header">
                    <h4 class="section-title">Tenant’s Info </h4>
                </div>
                @if (!empty($auction->get->first_name))
                <div class="col-md-12 col-12 pt-2 fw-bold"> First
                    Name:
                    <span class="removeBold">
                        {{ $auction->get->first_name }}
                    </span>
                </div>
                @endif

                <div class="row">
                    {{-- @if (isset($auction->get->video))
                                <div class="col-md-6 col-6 pt-2 fw-bold">Video:
                                    <span class="removeBold">
                                        <video controls style="width:100%;height:29vh;">
                                            <source src="{{ asset('storage/auction/videos/' . $auction->get->video) }}"
                    type="video/mp4">
                    Your browser does not support the video tag.
                    </video>
                    </span>
                </div>
                @endif --}}

                @if (!empty($auction->get->video))
                <div class="col-md-6 col-6 pt-2 fw-bold">Video:
                    <span class="removeBold">
                        <video autoplay muted loop playsinline controls style="width:100%; height:29vh;">
                            <source src="{{ asset('storage/auction/videos/' . $auction->get->video) }}"
                                type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </span>
                </div>
                @endif

                @if (isset($auction->get->photo))
                <div class="col-md-6 col-6 pt-2 fw-bold">Photo:
                    <span class="removeBold">
                        <img src="{{ asset('storage/auction/images/' . $auction->get->photo) }}"
                            style="width:100%;height:29vh;" />
                    </span>
                </div>
                @endif

                @if (!empty($auction->get->video_link))
                @if (filter_var($auction->get->video_link, FILTER_VALIDATE_URL))
                @php
                $videoLink = $auction->get->video_link;
                @endphp

                @if (strpos($videoLink, 'youtube.com') !== false || strpos($videoLink, 'youtu.be') !== false)
                @php
                // Convert YouTube URL to embed format
                if (strpos($videoLink, 'watch?v=') !== false) {
                $youtubeEmbedUrl = str_replace('watch?v=', 'embed/', $videoLink);
                } elseif (strpos($videoLink, 'youtu.be/') !== false) {
                $videoId = basename(parse_url($videoLink, PHP_URL_PATH));
                $youtubeEmbedUrl = "https://www.youtube.com/embed/{$videoId}";
                } else {
                $youtubeEmbedUrl = $videoLink;
                }

                // Add autoplay + mute parameters (mute avoids browser block)
                $youtubeEmbedUrl .=
                (strpos($youtubeEmbedUrl, '?') === false ? '?' : '&') .
                'autoplay=1&mute=1';
                @endphp

                <div class="col-md-6 col-6 pt-2 fw-bold">Video Link:
                    <span class="removeBold">
                        <iframe width="100%" height="315" src="{{ $youtubeEmbedUrl }}"
                            frameborder="0"
                            allow="autoplay; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen>
                        </iframe>
                    </span>
                </div>
                @elseif (strpos($videoLink, 'vimeo.com') !== false)
                @php
                // Extract Vimeo video ID from any kind of URL (e.g. /channels/staffpicks/1120141041)
                preg_match('/vimeo\.com\/(?:.*\/)?(\d+)/', $videoLink, $matches);
                $vimeoVideoId =
                $matches[1] ?? basename(parse_url($videoLink, PHP_URL_PATH));

                // Vimeo autoplay embed URL
                $vimeoEmbedUrl = "https://player.vimeo.com/video/{$vimeoVideoId}?autoplay=1&muted=1";
                @endphp

                <div class="col-md-6 col-6 pt-2 fw-bold">Video Link:
                    <span class="removeBold">
                        <iframe src="{{ $vimeoEmbedUrl }}" width="100%" height="315"
                            frameborder="0"
                            allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
                            allowfullscreen>
                        </iframe>
                    </span>
                </div>
                @endif
                @endif
                @endif

            </div>
        </div>
    </div>
    @inject('auctionUser', 'App\Models\User')
    @php
    $auser = $auctionUser::find(@$auction->user_id);
    @endphp
    <!-- Review  -->
    <div class="card review">
        <div class="card-body d-flex align-items-center">
            <div class="left d-flex align-items-center">
                <img class="w-25"
                    src="{{ $auser->avatar ? asset('images/avatar/' . $auser->avatar) : 'https://ppt1080.b-cdn.net/images/avatar/none.png' }}"
                    alt="">
                <div>
                    <p class="mb-0"><a href="{{ route('author', [$auser->id]) }}"><b>User
                                Details</b></a><span></span>
                        <span class="start opacity-50">
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                        </span>
                    </p>
                    <p class="mb-0">...</p>
                    <p class="mb-0 opacity-50">{{ $auser->name }} • last online 5 days ago.</p>
                </div>
            </div>
            <div class="right text-center">
                <a href="{{ route('author', [$auser->id]) }}"><button class="btn">Message</button></a>
                <a href="{{ route('author', [$auser->id]) }}"><button class="btn view-btn">View
                        Profile</button></a>
            </div>

        </div>
    </div>
</div>
<div class="col-sm-12 col-md-4 col-lg-4 rightCol">
    <h1 style="font-size: 1.5rem; font-weight: bold; color: #049399; line-height: 1.3;">{{ @$auction->title }}</h1>
    @if(@$auction->status)
    <div class="mb-2">
        @php
            $statusColors = [
                'Active' => 'bg-success',
                'Pending' => 'bg-warning text-dark',
                'Hired Agent' => 'bg-info',
                'Expired' => 'bg-danger',
            ];
            $statusClass = $statusColors[@$auction->status] ?? 'bg-secondary';
        @endphp
        <span class="badge {{ $statusClass }}" style="font-size: 0.9rem;">Status: {{ @$auction->status }}</span>
    </div>
    @endif
    @if(@$auction->listing_id)
    <div class="mb-2">
        <span class="badge bg-secondary" style="font-size: 0.9rem;">Listing ID: {{ @$auction->listing_id }}</span>
    </div>
    @endif

    @php
        $auth_id = auth()->id();
        $isOwnerOfListing = ($auth_id && $auth_id == data_get($auction, 'user_id'));
        $listingUserType = strtolower(trim($auction->get->user_type ?? 'tenant'));
        $isTenantListing = in_array($listingUserType, ['tenant', '']);
        $hasAcceptedBid = $auction->bids->where('accepted', 'accepted')->count() > 0;
    @endphp
    @if($isOwnerOfListing && $isTenantListing && !$hasAcceptedBid)
    <div class="mb-3">
        <a href="{{ route('hire.agent.auction.edit', ['auctionId' => $auction->id, 'user_type' => 'tenant']) }}" 
           class="btn btn-outline-primary btn-sm">
            <i class="fa fa-edit me-1"></i> Edit Listing
        </a>
    </div>
    @endif
    <hr>

    @inject('carbon', 'Carbon\Carbon')

    @php
    // 🔹 Determine listing type: Traditional vs Bidding Period
    $listingType = trim($auction->get->auction_type ?? '');
    $isTraditionalListing = (strtolower($listingType) === 'traditional' || empty($listingType));
    $isBiddingPeriodListing = (strtolower($listingType) === 'bidding period');
    
    // 🕒 Auction start time (when auction began)
    $start_time = $auction->get->created_at ?? $auction->created_at ?? $carbon::now();

    // 🔹 Get auction_time value
    $auction_time = trim($auction->get->auction_time ?? '');
    $useAuctionTime = !empty($auction_time) && strtolower($auction_time) !== 'null';

    if ($useAuctionTime && $isBiddingPeriodListing) {
    // 🔸 CASE 1: Use auction_time (e.g. "14 Days", "2 Weeks", "5 Hours") for Bidding Period
    $auction_duration = $auction_time;
    $duration_parts = explode(' ', trim($auction_duration)); // e.g. ['14', 'Days']
    $duration_value = (int) ($duration_parts[0] ?? 0);
    $duration_unit = strtolower($duration_parts[1] ?? 'days');

    // 🧠 Convert unit into Carbon duration
    switch ($duration_unit) {
    case 'day':
    case 'days':
    $expiration = $carbon::parse($start_time)->addDays($duration_value);
    break;
    case 'hour':
    case 'hours':
    $expiration = $carbon::parse($start_time)->addHours($duration_value);
    break;
    case 'week':
    case 'weeks':
    $expiration = $carbon::parse($start_time)->addWeeks($duration_value);
    break;
    case 'minute':
    case 'minutes':
    $expiration = $carbon::parse($start_time)->addMinutes($duration_value);
    break;
    default:
    $expiration = $carbon::parse($start_time)->addDays($duration_value);
    break;
    }
    } elseif ($isTraditionalListing) {
    // 🔸 CASE 2: Traditional listing - use expiration_date if set, otherwise no expiration
    $expiration = !empty($auction->get->expiration_date)
    ? $carbon::parse($auction->get->expiration_date)
    : null;
    } else {
    // 🔸 CASE 3: Fallback
    $expiration = !empty($auction->get->expiration_date)
    ? $carbon::parse($auction->get->expiration_date)
    : null;
    }

    // 🧾 Determine if expired (for Bidding Period) or listing expiration (for Traditional)
    $isExpired = $expiration ? $carbon::now()->gte($expiration) : false;
    
    // 🔹 For Bidding Period: timer active means actions are locked
    // For Traditional: actions are always unlocked (no timer restriction)
    $isBiddingTimerActive = $isBiddingPeriodListing && $expiration && !$isExpired;
    $canTakeAction = $isTraditionalListing || ($isBiddingPeriodListing && $isExpired);

    // ⏱ Calculate remaining time if not expired (only for Bidding Period)
    if ($isBiddingPeriodListing && $expiration && !$isExpired) {
    $now = $carbon::now();
    $diff_d = $now->diffInDays($expiration);
    $diff_H = $now->diff($expiration)->format('%H');
    $diff_I = $now->diff($expiration)->format('%I');
    $diff_S = $now->diff($expiration)->format('%S');
    }
    @endphp


    {{-- 💰 Bid Info --}}
    @php
    $lowest_bid_price = @$auction->bids->min('brokerage') ?? @$auction->get->concession;
    $lowest_bid_price =
    $lowest_bid_price < @$auction->get->concession ? $lowest_bid_price : @$auction->get->concession;
        $lowest_bidder = @$auction->bids->where('brokerage', $lowest_bid_price)->first();
        $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
        @endphp


        {{-- 📩 Message Button --}}
        <a href="{{ route('auction-chat', ['tenant-agent', $auction->id]) }}" class="btn btn-success w-100 mb-2">
            <i class="fa-solid fa-paper-plane"></i> Send Message
        </a>


        {{-- ⏳ Countdown Timer - Only shown for Bidding Period listings --}}
        @if ($isBiddingPeriodListing)
            @if ($isBiddingTimerActive)
            <div class="time d-flex justify-content-between text-center flex-wrap pb-2"
                data-expiration="{{ $expiration->toIso8601String() }}">
                <div>
                    <h5><b class="timer-d">{{ $diff_d }}</b></h5>
                    <h6 class="opacity-50">Days</h6>
                </div>
                <div>
                    <h5><b class="timer-h">{{ $diff_H }}</b></h5>
                    <h6 class="opacity-50">Hrs</h6>
                </div>
                <div>
                    <h5><b class="timer-m">{{ $diff_I }}</b></h5>
                    <h6 class="opacity-50">Mins</h6>
                </div>
                <div>
                    <h5><b class="timer-s">{{ $diff_S }}</b></h5>
                    <h6 class="opacity-50">Secs</h6>
                </div>
            </div>
            @elseif ($isExpired)
            <div class="alert alert-warning text-center mt-2 mb-0 p-2">
                <strong>Bidding Ended</strong>
            </div>
            @endif
        @endif
        {{-- Traditional listings: No timer displayed --}}



        @php
        $userHasBid = $auction->bids->where('user_id', $auth_id)->isNotEmpty();
        @endphp

        {{-- 🔹 Bid Button --}}
        @if ($auth_id && in_array(auth()->user()->user_type, ['agent']))
        @if (!$isExpired)
        @if ($userHasBid)
        {{-- User already placed a bid --}}
        <div class="alert alert-info text-center mb-2">
            <i class="fa fa-check-circle"></i> You have already placed a bid
        </div>
        <button class="btn w-100 btn-secondary" disabled>
            <span class="bid">Bid Already Placed</span>
            <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
        </button>

        {{-- Optional: Allow editing their bid --}}
        <!-- <button class="btn w-100 btn-outline-primary mt-2"
                onclick="window.location='{{ route('agent.tenant.agent.auction.bid', @$auction->id) }}';">
                <i class="fa fa-edit"></i> Edit Your Bid
            </button> -->
        @else
        {{-- User can place a bid --}}
        <button class="btn w-100 bid-btn"
            onclick="window.location='{{ route('agent.tenant.agent.auction.bid', @$auction->id) }}';">
            <span class="bid">Bid Now</span>
            <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
        </button>
        @endif

        @endif

        @if (@$auction->sold)
        <span class="badge bg-danger w-100 mt-2">Sold</span>
        @endif
        @elseif(!$auth_id)
        <a href="{{ route('login') }}">
            <button class="btn w-100">
                <span class="bid m-0">Login to Bid</span>
                <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span>
            </button>
        </a>
        @else
        <div class="alert alert-secondary text-center">
            Only agents can place bids
        </div>
        @endif

        @php
            // Create anonymous agent mapping based on bid submission order
            $bidsByOrder = $auction->bids->sortBy('created_at')->values();
            $agentNumberMap = [];
            foreach ($bidsByOrder as $index => $orderedBid) {
                $agentNumberMap[$orderedBid->id] = $index + 1;
            }
            // Find the last bidder's anonymous number
            $lastBidderNumber = null;
            if ($lowest_bidder) {
                $lastBidderNumber = $agentNumberMap[$lowest_bidder->id] ?? null;
            }
            // Check if current user is the listing owner
            $isListingOwner = ($auth_id == data_get($auction, 'user_id'));
            // Check if current user is an agent
            $isAgentViewer = $auth_id && in_array(auth()->user()->user_type ?? '', ['agent']);
            // For Traditional listings, agents should not see other agents' bid info
            $canSeeBidSummary = $isListingOwner || !$isAgentViewer || $isBiddingPeriodListing;
        @endphp
        
        {{-- Last Bidder Info - Outside the card --}}
        @if ($canSeeBidSummary)
            @if ($lowest_bidder && $lastBidderNumber)
            <p class="mb-3"><b>Agent {{ $lastBidderNumber }}</b> was the last bidder.</p>
            @else
            <p class="mb-3">No one has bid on this auction.</p>
            @endif
        @else
            {{-- Traditional listing - agents cannot see bid summary --}}
            <p class="text-muted mb-3"><i class="fa fa-lock me-1"></i> Bid information is private for traditional listings.</p>
        @endif
        
        {{-- 🔹 Agent Visibility Info Messages --}}
        @php
            // $isAgentViewer already defined above
            $otherBidsExist = $auction->bids->where('user_id', '!=', $auth_id)->count() > 0;
        @endphp
        @if ($isAgentViewer && !$isListingOwner)
            @if ($isTraditionalListing && $otherBidsExist)
            <div class="alert alert-info small mb-3 py-2">
                <i class="fa fa-lock me-1"></i> <strong>Traditional Listing:</strong> You can only view your own bid. Other agents' bids remain private.
            </div>
            @elseif ($isBiddingPeriodListing && !$isExpired && $userHasBid && $otherBidsExist)
            <a href="{{ route('agent.tenant.agent.auction.competing-bids', $auction->id) }}" class="btn btn-outline-info w-100 mb-3">
                <i class="fa fa-users me-2"></i>View Competing Bids
            </a>
            <div class="alert alert-info small mb-3 py-2">
                <i class="fa fa-eye me-1"></i> <strong>Bidding Period:</strong> You can view anonymized competing bids (Broker Terms, Services, Match Scores only).
            </div>
            @elseif ($isBiddingPeriodListing && !$isExpired && !$userHasBid)
            <div class="alert alert-warning small mb-3 py-2">
                <i class="fa fa-info-circle me-1"></i> <strong>Submit to View:</strong> Submit your bid to view competing bids.
            </div>
            @endif
        @endif

        <div class="card higestBider" id="bids-section">
            <div class="card-body card-body-padding">
                <div class="accordion" id="accordionExample">
                    <div class="accordion-item border-0">

                        @foreach (@$auction->bids as $bid)
                        @php
                            $agentNumber = $agentNumberMap[$bid->id] ?? $loop->iteration;
                            $bidState = data_get($bid, 'accepted', 'active');
                            // Check for counter bids from both sources (agent counters and tenant counters)
                            $hasAgentCounterBids = \App\Models\TenantCounterBidding::where('tenant_agent_auction_bid_id', data_get($bid, 'id'))->exists();
                            $hasTenantCounterBids = \App\Models\TenantCounterTerm::where('tenant_agent_auction_id', data_get($bid, 'id'))->exists();
                            $hasCounterBids = $hasAgentCounterBids || $hasTenantCounterBids;
                            $bidStatusLabel = match($bidState) {
                                'accepted' => 'Accepted',
                                'rejected' => 'Rejected',
                                'countered' => 'Countered',
                                default => $hasCounterBids ? 'Countered' : 'Active',
                            };
                            $bidStatusColor = match($bidState) {
                                'accepted' => '#28a745',
                                'rejected' => '#dc3545',
                                'countered' => '#ffc107',
                                default => $hasCounterBids ? '#ffc107' : '#1a4a6e',
                            };
                            $servicesList = (array) data_get($bid,'get.services',[]);
                            $additionalServices = (array) data_get($bid,'get.other_services',[]);
                            $totalServicesCount = count(array_filter($servicesList, fn($s) => $s !== 'Other')) + count($additionalServices);
                            $isBidOwner = (data_get($bid, 'user_id') == $auth_id);
                            $bidAccepted = data_get($bid, 'accepted');
                            $canEditWithdraw = $isBidOwner && !$isExpired && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected';
                            
                            // 🔹 Agent Bid Visibility Logic:
                            // - Traditional: Agents can ONLY see their own bids (not other agents' bids)
                            // - Bidding Period: Agents can see anonymous summaries of all bids ONLY if they have submitted a bid first (submit-to-view rule)
                            // - Listing Owner: Always sees all bids
                            $isAgent = $auth_id && in_array(auth()->user()->user_type ?? '', ['agent']);
                            $canViewBid = $isListingOwner || $isBidOwner || ($isBiddingPeriodListing && $isAgent && $userHasBid);
                            // Skip rendering this bid if agent cannot view it
                            if (!$canViewBid && $isAgent) {
                                continue;
                            }
                            
                            $commissionStructure = data_get($bid, 'get.commission_structure', 'Not specified');
                            $leaseFeeType = data_get($bid, 'get.lease_fee_type', '');
                            $leaseFeeFlat = data_get($bid, 'get.lease_fee_flat', '');
                            $leaseFeePercentage = data_get($bid, 'get.lease_fee_percentage', '');
                            $leaseFeePercentageMonthlyRent = data_get($bid, 'get.lease_fee_percentage_monthly_rent', '');
                            $leaseFeePercentageMonthlyNumber = data_get($bid, 'get.lease_fee_percentage_monthly_number', '');
                            $leaseFeeFlatCombo = data_get($bid, 'get.lease_fee_flat_combo', '');
                            $leaseFeePercentageCombo = data_get($bid, 'get.lease_fee_percentage_combo', '');
                            $leaseFeePercentageNet = data_get($bid, 'get.lease_fee_percentage_net', '');
                            $leaseFeeFlatComboNet = data_get($bid, 'get.lease_fee_flat_combo_net', '');
                            $leaseFeePercentageComboNet = data_get($bid, 'get.lease_fee_percentage_combo_net', '');
                            $leaseFeeOther = data_get($bid, 'get.lease_fee_other', '');
                            
                            // Build commission fee display using helper functions (combined format: $2,323 + 3% of Gross Lease Value)
                            $commissionFeeDisplay = '—';
                            if ($leaseFeeType === 'Flat Fee' && $leaseFeeFlat) {
                                $commissionFeeDisplay = $fmtMoney($leaseFeeFlat);
                            } elseif ($leaseFeeType === 'Percentage of the Gross Lease Value' && $leaseFeePercentage) {
                                $commissionFeeDisplay = $fmtPercent($leaseFeePercentage) . ' of Gross Lease Value';
                            } elseif ($leaseFeeType === 'Percentage of Monthly Rent' && $leaseFeePercentageMonthlyRent) {
                                $display = $fmtPercent($leaseFeePercentageMonthlyRent) . ' of Monthly Rent';
                                if ($leaseFeePercentageMonthlyNumber) {
                                    $display .= ' x ' . $leaseFeePercentageMonthlyNumber . ' Months';
                                }
                                $commissionFeeDisplay = $display;
                            } elseif ($leaseFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                                $commissionFeeDisplay = $joinParts([
                                    $fmtMoney($leaseFeeFlatCombo),
                                    $leaseFeePercentageCombo ? ($fmtPercent($leaseFeePercentageCombo) . ' of Gross Lease Value') : null,
                                ]) ?? '—';
                            } elseif ($leaseFeeType === 'Percentage of the Net Aggregate Rent' && $leaseFeePercentageNet) {
                                $commissionFeeDisplay = $fmtPercent($leaseFeePercentageNet) . ' of Net Aggregate Rent';
                            } elseif ($leaseFeeType === 'Flat Fee + Percentage of the Net Aggregate Rent') {
                                $commissionFeeDisplay = $joinParts([
                                    $fmtMoney($leaseFeeFlatComboNet),
                                    $leaseFeePercentageComboNet ? ($fmtPercent($leaseFeePercentageComboNet) . ' of Net Aggregate Rent') : null,
                                ]) ?? '—';
                            } elseif (strtolower($leaseFeeType) === 'other' && $leaseFeeOther) {
                                $commissionFeeDisplay = $leaseFeeOther;
                            } elseif ($leaseFeeType) {
                                $commissionFeeDisplay = $leaseFeeType;
                            }
                            
                            // Build Purchase Fee combined display
                            $purchaseFeeDisplay = '—';
                            $purchaseFeeType = data_get($bid, 'get.purchase_fee_type', '');
                            if ($purchaseFeeType === 'Flat Fee') {
                                $purchaseFeeDisplay = $fmtMoney(data_get($bid, 'get.purchase_fee_flat')) ?? '—';
                            } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price') {
                                $pct = data_get($bid, 'get.purchase_fee_percentage');
                                $purchaseFeeDisplay = $pct ? ($fmtPercent($pct) . ' of Total Purchase Price') : '—';
                            } elseif ($purchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                                $purchaseFeeDisplay = $joinParts([
                                    $fmtMoney(data_get($bid, 'get.purchase_fee_flat_combo')),
                                    data_get($bid, 'get.purchase_fee_percentage_combo') ? ($fmtPercent(data_get($bid, 'get.purchase_fee_percentage_combo')) . ' of Total Purchase Price') : null,
                                ]) ?? '—';
                            } elseif ($purchaseFeeType === 'other') {
                                $purchaseFeeDisplay = data_get($bid, 'get.purchase_fee_other') ?? '—';
                            }
                            
                            // Build Lease-Option combined display
                            $leaseOptionCreatedDisplay = '—';
                            $leaseOptionExercisedDisplay = '—';
                            if (data_get($bid, 'get.interested_lease_option_agreement') === 'Yes') {
                                $leaseType = data_get($bid, 'get.lease_type');
                                $leaseValue = data_get($bid, 'get.lease_value');
                                if ($leaseType === 'percent' && $leaseValue) {
                                    $leaseOptionCreatedDisplay = $fmtPercent($leaseValue);
                                } elseif ($leaseValue) {
                                    $leaseOptionCreatedDisplay = $fmtMoney($leaseValue) ?? '—';
                                }
                                
                                $purchaseType = data_get($bid, 'get.purchase_type');
                                $purchaseValue = data_get($bid, 'get.purchase_value');
                                if ($purchaseType === 'percent' && $purchaseValue) {
                                    $leaseOptionExercisedDisplay = $fmtPercent($purchaseValue);
                                } elseif ($purchaseValue) {
                                    $leaseOptionExercisedDisplay = $fmtMoney($purchaseValue) ?? '—';
                                }
                            }
                            
                            // Termination Fee display
                            $terminationFeeDisplay = '—';
                            if (data_get($bid, 'get.early_termination_fee_option') === 'Yes') {
                                $terminationFeeDisplay = $fmtMoney(data_get($bid, 'get.early_termination_fee_amount')) ?? '—';
                            }
                            
                            // === MATCH SCORE CALCULATION (for bid card and modal) ===
                            // Get current bid data
                            $currentBidData = (array) data_get($bid, 'get', []);
                            
                            // Determine baseline based on viewer
                            $baselineData = [];
                            $baselineLabel = '';
                            
                            // Get the listing owner's user ID for filtering
                            $listingOwnerUserId = $auction->user_id;
                            
                            // Get the latest counter from tenant (listing owner) ONLY
                            $latestTenantCounter = $bid->counterTerms()
                                ->where('user_id', $listingOwnerUserId)
                                ->orderBy('created_at', 'desc')
                                ->first();
                            
                            if ($isListingOwner) {
                                // Tenant viewing Agent's bid - baseline is Tenant's latest counter or original listing
                                if ($latestTenantCounter) {
                                    $baselineData = $latestTenantCounter->getAllMeta();
                                    $baselineLabel = 'Your Latest Counter';
                                } else {
                                    // Use original listing Broker Compensation fields
                                    $baselineData = [
                                        'commission_structure' => data_get($auction, 'get.commission_structure'),
                                        'lease_fee_type' => data_get($auction, 'get.lease_fee_type'),
                                        'payment_timing' => data_get($auction, 'get.broker_fee_timing'),
                                        'days_to_pay' => data_get($auction, 'get.broker_fee_days_from_rent') ?? data_get($auction, 'get.broker_fee_days_after_lease'),
                                        'interested_purchase_fee_type' => data_get($auction, 'get.interested_purchase_fee_type'),
                                        'purchase_fee_type' => data_get($auction, 'get.purchase_fee_type'),
                                        'interested_lease_option_agreement' => data_get($auction, 'get.interested_lease_option_agreement'),
                                        'lease_type' => data_get($auction, 'get.lease_type'),
                                        'lease_value' => data_get($auction, 'get.lease_value'),
                                        'purchase_type' => data_get($auction, 'get.purchase_type'),
                                        'purchase_value' => data_get($auction, 'get.purchase_value'),
                                        'protection_period' => data_get($auction, 'get.protection_period'),
                                        'early_termination_fee_option' => data_get($auction, 'get.early_termination_fee_option'),
                                        'early_termination_fee_amount' => data_get($auction, 'get.early_termination_fee_amount'),
                                        'retainer_fee_option' => data_get($auction, 'get.retainer_fee_option'),
                                        'retainer_fee_amount' => data_get($auction, 'get.retainer_fee_amount'),
                                        'retainer_fee_application' => data_get($auction, 'get.retainer_fee_application'),
                                        'agency_agreement_timeframe' => data_get($auction, 'get.agency_agreement_timeframe'),
                                        'brokerage_relationship' => data_get($auction, 'get.brokerage_relationship'),
                                        'services' => data_get($auction, 'get.services'),
                                        'other_services' => data_get($auction, 'get.other_services'),
                                    ];
                                    $baselineLabel = 'Your Original Terms';
                                }
                            } else {
                                // Agent viewing their own bid - baseline is their own bid (100% match)
                                $baselineData = $currentBidData;
                                $baselineLabel = 'Your Bid';
                            }
                            
                            // Broker Compensation fields to compare
                            $brokerFields = [
                                'commission_structure', 'lease_fee_type', 'payment_timing', 'days_to_pay',
                                'interested_purchase_fee_type', 'purchase_fee_type', 'interested_lease_option_agreement',
                                'lease_type', 'lease_value', 'purchase_type', 'purchase_value', 'protection_period',
                                'early_termination_fee_option', 'early_termination_fee_amount', 'retainer_fee_option',
                                'retainer_fee_amount', 'retainer_fee_application', 'agency_agreement_timeframe', 'brokerage_relationship',
                                'lease_fee_flat', 'lease_fee_percentage', 'lease_fee_percentage_monthly_rent', 'lease_fee_percentage_monthly_number',
                                'lease_fee_flat_combo', 'lease_fee_percentage_combo', 'lease_fee_percentage_net',
                                'lease_fee_flat_combo_net', 'lease_fee_percentage_combo_net', 'lease_fee_other',
                                'purchase_fee_flat', 'purchase_fee_percentage', 'purchase_fee_flat_combo', 'purchase_fee_percentage_combo', 'purchase_fee_other',
                                'flat_fee_amount', 'percent_gross_lease', 'purchase_flat_fee_amount', 'purchase_percent_value',
                            ];
                            
                            // Normalize function for comparison
                            $normalizeForMatch = function($v) {
                                if (is_null($v) || $v === '') return '';
                                if (is_array($v) || is_object($v)) return json_encode($v);
                                $v = trim((string) $v);
                                return preg_replace('/[\s$,%]/', '', strtolower($v));
                            };
                            
                            // Calculate Broker Compensation Match
                            $brokerMatched = 0;
                            $brokerTotal = 0;
                            $brokerMismatches = [];
                            
                            foreach ($brokerFields as $field) {
                                $currentVal = $currentBidData[$field] ?? null;
                                $baselineVal = $baselineData[$field] ?? null;
                                if (!empty($currentVal) || !empty($baselineVal)) {
                                    $brokerTotal++;
                                    if ($normalizeForMatch($currentVal) === $normalizeForMatch($baselineVal)) {
                                        $brokerMatched++;
                                    } else {
                                        $brokerMismatches[$field] = true;
                                    }
                                }
                            }
                            
                            $brokerScore = $brokerTotal > 0 ? round(($brokerMatched / $brokerTotal) * 100) : 100;
                            
                            // === OFFERED SERVICES COMPARISON (includes other_services) ===
                            // Parse main services
                            $currentServices = $currentBidData['services'] ?? [];
                            if (is_string($currentServices)) {
                                $currentServices = json_decode($currentServices, true) ?? [];
                            }
                            $currentServices = is_array($currentServices) ? array_values(array_filter($currentServices)) : [];
                            
                            // Parse other_services (additional custom services) for current bid
                            $currentOtherServices = $currentBidData['other_services'] ?? [];
                            if (is_string($currentOtherServices)) {
                                $currentOtherServices = json_decode($currentOtherServices, true) ?? [];
                            }
                            $currentOtherServices = is_array($currentOtherServices) ? array_values(array_filter($currentOtherServices, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                            
                            // Combine main services + other_services for current bid
                            $currentServices = array_merge($currentServices, $currentOtherServices);
                            
                            // Parse baseline services
                            $baselineServices = $baselineData['services'] ?? [];
                            if (is_string($baselineServices)) {
                                $baselineServices = json_decode($baselineServices, true) ?? [];
                            }
                            $baselineServices = is_array($baselineServices) ? array_values(array_filter($baselineServices)) : [];
                            
                            // Parse other_services for baseline
                            $baselineOtherServices = $baselineData['other_services'] ?? [];
                            if (is_string($baselineOtherServices)) {
                                $baselineOtherServices = json_decode($baselineOtherServices, true) ?? [];
                            }
                            $baselineOtherServices = is_array($baselineOtherServices) ? array_values(array_filter($baselineOtherServices, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                            
                            // Combine main services + other_services for baseline
                            $baselineServices = array_merge($baselineServices, $baselineOtherServices);
                            
                            $normalizeService = function($s) {
                                $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
                                $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
                                return strtolower(trim($s));
                            };
                            
                            $currentNorm = array_map($normalizeService, $currentServices);
                            $baselineNorm = array_map($normalizeService, $baselineServices);
                            
                            $allServices = array_unique(array_merge($currentNorm, $baselineNorm));
                            $servicesTotal = count($allServices);
                            $servicesMatched = 0;
                            $serviceMismatches = [];
                            
                            foreach ($allServices as $svc) {
                                $inCurrent = in_array($svc, $currentNorm);
                                $inBaseline = in_array($svc, $baselineNorm);
                                if ($inCurrent === $inBaseline) {
                                    $servicesMatched++;
                                } else {
                                    $serviceMismatches[$svc] = true;
                                }
                            }
                            
                            $servicesScore = $servicesTotal > 0 ? round(($servicesMatched / $servicesTotal) * 100) : 100;
                            
                            // === TOTAL MATCH SCORE ===
                            $hasBroker = $brokerTotal > 0;
                            $hasServices = $servicesTotal > 0;
                            
                            if ($hasBroker && $hasServices) {
                                $totalScore = round(($brokerScore + $servicesScore) / 2);
                            } elseif ($hasBroker) {
                                $totalScore = $brokerScore;
                            } elseif ($hasServices) {
                                $totalScore = $servicesScore;
                            } else {
                                $totalScore = 100;
                            }
                            
                            // Score color based on percentage
                            $getScoreColor = function($score) {
                                if ($score >= 80) return '#28a745';
                                if ($score >= 50) return '#ffc107';
                                return '#dc3545';
                            };
                            
                            $totalScoreColor = $getScoreColor($totalScore);
                        @endphp
                        
                        <!-- Bid Card - Collapsible Accordion Design -->
                        <div class="card mb-3" style="border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                            
                            <!-- A) Card Header - Clickable to expand/collapse (using custom JS toggle) -->
                            <div class="card-header d-flex justify-content-between align-items-center bid-accordion-header" 
                                 style="cursor: pointer; background: #fff; border-bottom: 1px solid #e0e0e0; padding: 15px 20px;"
                                 data-target="bidCollapse-{{ data_get($bid, 'id') }}"
                                 aria-expanded="false">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa fa-chevron-down bid-chevron" style="transition: transform 0.3s; color: #1a3a5c;"></i>
                                    <h5 class="mb-0" style="font-weight: 700; color: #1a3a5c; font-size: 1.4rem;">Agent {{ $agentNumber }}</h5>
                                </div>
                                <span style="font-weight: 600; color: {{ $bidStatusColor }}; font-size: 1.1rem;">{{ $bidStatusLabel }}</span>
                            </div>
                            
                            <!-- Collapsible Content - Default collapsed (custom toggle, no Bootstrap) -->
                            <div class="bid-collapse-content" id="bidCollapse-{{ data_get($bid, 'id') }}" style="display: none;">
                            <div class="card-body" style="padding: 20px;">
                                
                                <hr style="margin: 0 0 15px 0; border-color: #e0e0e0;">
                                
                                <!-- B) Offered Services Count Row -->
                                <p class="mb-0" style="font-size: 1.1rem; color: #1a3a5c;">
                                    <span style="font-weight: 600;">Offered Services:</span> {{ $totalServicesCount }} Services
                                </p>
                                <hr style="margin: 15px 0; border-color: #e0e0e0;">
                                
                                <!-- B2) Match Score Summary (Compact Display on Bid Card) -->
                                @if ($isListingOwner)
                                <div class="match-score-summary mb-3 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 8px; border: 1px solid #dee2e6;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span style="font-weight: 600; color: #1a3a5c; font-size: 1rem;">
                                            <i class="fa fa-chart-pie me-2"></i>Match Score
                                        </span>
                                        <span class="badge" style="background: {{ $totalScoreColor }}; font-size: 1rem; padding: 6px 12px;">
                                            {{ $totalScore }}%
                                        </span>
                                    </div>
                                    <div class="row g-2 small">
                                        <div class="col-6">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Broker Compensation:</span>
                                                <span style="color: {{ $getScoreColor($brokerScore) }}; font-weight: 600;">{{ $brokerScore }}%</span>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.8rem;">{{ $brokerMatched }}/{{ $brokerTotal }} matched</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Offered Services:</span>
                                                <span style="color: {{ $getScoreColor($servicesScore) }}; font-weight: 600;">{{ $servicesScore }}%</span>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.8rem;">{{ $servicesMatched }}/{{ $servicesTotal }} matched</div>
                                        </div>
                                    </div>
                                    <div class="mt-2 small text-muted">
                                        <i class="fa fa-info-circle me-1"></i>Compared to: {{ $baselineLabel }}
                                    </div>
                                </div>
                                @endif
                                
                                <!-- C) Broker Compensation Summary Section -->
                                <h6 style="font-weight: 600; color: #1a3a5c; font-size: 1.15rem; margin-bottom: 12px;">Broker Compensation Summary:</h6>
                                
                                <div class="mb-2">
                                    <p class="mb-1" style="font-size: 1rem; color: #333;">
                                        <span style="font-weight: 600;">Tenant's Broker Commission Structure:</span>
                                    </p>
                                    <p class="mb-0" style="font-size: 1rem; color: #555;">{{ $commissionStructure }}</p>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="mb-1" style="font-size: 1rem; color: #333;">
                                        <span style="font-weight: 600;">Tenant's Broker Commission Fee:</span>
                                    </p>
                                    <p class="mb-0" style="font-size: 1rem; color: #555;">{{ $commissionFeeDisplay }}</p>
                                </div>
                                
                                <!-- D) View Full Terms Link - visibility rules by listing type and user -->
                                @if ($isListingOwner || $isBidOwner)
                                {{-- Listing Owner or Bid Owner: Full access --}}
                                @if ($isBiddingPeriodListing && $isBiddingTimerActive && $isListingOwner && !$isBidOwner)
                                {{-- Bidding Period active: Disable View Bid for listing owner --}}
                                <span style="color: #999; font-size: 1rem; font-weight: 500; cursor: not-allowed;" 
                                      title="Bids can be viewed when the bidding period ends.">
                                    <i class="fa fa-lock me-1"></i> View Full Bid
                                </span>
                                <div class="text-muted small mt-1">
                                    <i class="fa fa-clock me-1"></i> Bids can be viewed when the bidding period ends.
                                </div>
                                @else
                                <a href="#" data-bs-toggle="modal" data-bs-target="#privateDataModal{{ data_get($bid, 'id') }}"
                                   style="color: #1a4a6e; text-decoration: none; font-size: 1rem; font-weight: 500;">
                                    View Full Bid
                                </a>
                                @endif
                                @elseif ($isBiddingPeriodListing && $isAgentViewer && !$isBidOwner)
                                {{-- Bidding Period: Agent viewing another agent's bid - show limited view button --}}
                                <a href="#" data-bs-toggle="modal" data-bs-target="#limitedBidModal{{ data_get($bid, 'id') }}"
                                   style="color: #1a4a6e; text-decoration: none; font-size: 1rem; font-weight: 500;">
                                    View Full Services & Broker Compensation Terms
                                </a>
                                @else
                                {{-- Default: Private message --}}
                                <span style="color: #888; font-style: italic; font-size: 0.95rem;">
                                    <i class="fa fa-lock me-1"></i> Private — visible only to listing creator
                                </span>
                                @endif
                                
                                <!-- Edit/Withdraw Actions for Bid Owner - Same row, matched sizing -->
                                @if ($canEditWithdraw)
                                <div class="d-flex gap-2 mt-3 justify-content-end align-items-center">
                                    <a href="{{ route('agent.tenant.agent.auction.bid', $auction->id) }}?edit={{ data_get($bid, 'id') }}" 
                                       class="btn btn-primary bid-action-btn">
                                        <i class="fa fa-edit me-1"></i> Edit Bid
                                    </a>
                                    <form action="{{ route('tenant.hire.agent.auction.bid.withdraw') }}" method="POST" 
                                          onsubmit="return confirm('Are you sure you want to withdraw your bid? This action cannot be undone.');"
                                          class="d-inline">
                                        @csrf
                                        <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                        <button type="submit" class="btn btn-danger bid-action-btn">
                                            <i class="fa fa-times-circle me-1"></i> Withdraw Bid
                                        </button>
                                    </form>
                                </div>
                                @elseif ($isBidOwner && $isExpired)
                                <div class="mt-3">
                                    <span class="text-muted small">
                                        <i class="fa fa-clock me-1"></i> Bidding has ended — edit/withdraw unavailable
                                    </span>
                                </div>
                                @elseif ($isBidOwner && ($bidAccepted === 'accepted' || $bidAccepted === 'rejected'))
                                <div class="mt-3">
                                    <span class="text-muted small">
                                        <i class="fa fa-lock me-1"></i> Bid {{ $bidAccepted }} — edit/withdraw unavailable
                                    </span>
                                    @if($bidAccepted === 'accepted')
                                    @php
                                        $bidOwnerSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))->first();
                                    @endphp
                                    @if($bidOwnerSummary)
                                    <div class="d-flex gap-2 flex-wrap mt-2">
                                        <a href="{{ route('accepted-bid-summary.view', $bidOwnerSummary->id) }}" class="btn btn-outline-primary btn-sm">
                                            <i class="fa fa-file-alt me-1"></i> View Accepted Bid Summary
                                        </a>
                                        @if(!$bidOwnerSummary->isAgentSigned())
                                        <a href="{{ route('accepted-bid-summary.sign-form', $bidOwnerSummary->id) }}" class="btn btn-primary btn-sm">
                                            <i class="fa fa-signature me-1"></i> Agent: E-Sign Acknowledgement
                                        </a>
                                        @endif
                                        @if($bidOwnerSummary->isFullySigned())
                                        <a href="{{ route('accepted-bid-summary.download-pdf', $bidOwnerSummary->id) }}" class="btn btn-success btn-sm">
                                            <i class="fa fa-download me-1"></i> Download Signed PDF
                                        </a>
                                        @endif
                                    </div>
                                    @endif
                                    @endif
                                </div>
                                @endif
                                
                            </div>
                            </div> {{-- End of collapse div --}}
                        </div>
                        
                        @if ($isListingOwner || $isBidOwner)

                                    <!-- Private Data Modal - visible to listing owner OR bid owner (agent) -->
                                    <div class="modal fade"
                                        id="privateDataModal{{ data_get($bid, 'id') }}"
                                        tabindex="-1"
                                        aria-labelledby="privateDataModalLabel{{ data_get($bid, 'id') }}"
                                        aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content"
                                                style="border-radius: 10px; border: none;">
                                                <div class="modal-header text-white"
                                                    style="background: #049399; border-bottom: none; padding: 20px;">
                                                    <h5 class="modal-title"
                                                        id="privateDataModalLabel{{ data_get($bid, 'id') }}"
                                                        style="font-weight: 600;">
                                                        <i class="fa fa-lock me-2"></i> Private
                                                        Compensation & Agreement Terms
                                                    </h5>
                                                </div>
                                                <div class="modal-body"
                                                    style="background: #fafafa; padding: 25px;">

                                                    {{-- ========== MATCH SCORE PANEL (uses pre-calculated values) ========== --}}
                                                    <div class="match-score-panel mb-4 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border: 1px solid #dee2e6;">
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <h6 class="mb-0" style="color: #1a3a5c; font-weight: 600;">
                                                                <i class="fa fa-chart-pie me-2"></i>Match Score
                                                            </h6>
                                                            <span class="badge" style="background: {{ $getScoreColor($totalScore) }}; font-size: 1.1rem; padding: 8px 16px;">
                                                                {{ $totalScore }}% Match
                                                            </span>
                                                        </div>
                                                        <p class="small text-muted mb-3">
                                                            Comparing to: <strong>{{ $baselineLabel }}</strong>
                                                        </p>
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $getScoreColor($brokerScore) }};">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span class="small fw-semibold">Broker Compensation</span>
                                                                        <span class="badge" style="background: {{ $getScoreColor($brokerScore) }};">{{ $brokerScore }}%</span>
                                                                    </div>
                                                                    <div class="small text-muted mt-1">
                                                                        {{ $brokerMatched }}/{{ $brokerTotal }} fields match
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="p-2 bg-white rounded" style="border-left: 4px solid {{ $getScoreColor($servicesScore) }};">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <span class="small fw-semibold">Offered Services</span>
                                                                        <span class="badge" style="background: {{ $getScoreColor($servicesScore) }};">{{ $servicesScore }}%</span>
                                                                    </div>
                                                                    <div class="small text-muted mt-1">
                                                                        {{ $servicesMatched }}/{{ $servicesTotal }} services match
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    {{-- ========== END MATCH SCORE PANEL ========== --}}

                                                    <!-- 1. Agent Overview & Qualifications - visible to listing owner or bid owner -->
                                                    @if ($isListingOwner || $isBidOwner)
                                                    <div class="mb-5">
                                                        <h6 class="mb-3"
                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa fa-user-tie me-2"></i>Agent
                                                            Overview & Qualifications
                                                        </h6>

                                                        <!-- About Agent -->
                                                        @if (data_get($bid, 'get.bio'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">About Agent:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.bio') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Why Hire This Agent -->
                                                        @if (data_get($bid, 'get.why_hire_you'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Why Hire This Agent:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.why_hire_you') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- What Sets This Agent Apart -->
                                                        @if (data_get($bid, 'get.what_sets_you_apart'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">What Sets This Agent Apart:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.what_sets_you_apart') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Marketing Strategy -->
                                                        @if (data_get($bid, 'get.marketing_plan'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Marketing Strategy:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.marketing_plan') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Review Links -->
                                                        @if (data_get($bid, 'get.reviews_links'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Review Links:</div>
                                                            <div>
                                                                @foreach (data_get($bid, 'get.reviews_links') as $reviewLink)
                                                                @if (!empty($reviewLink->url))
                                                                <div class="mb-1">
                                                                    <a href="https://{{ $reviewLink->url }}"
                                                                        target="_blank"
                                                                        class="text-primary text-decoration-none">
                                                                        <i
                                                                            class="fa fa-external-link-alt me-1"></i>
                                                                        {{ !empty($reviewLink->text) ? $reviewLink->text : $reviewLink->url }}
                                                                    </a>
                                                                </div>
                                                                @endif
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        @endif
                                                        <!-- Website Link -->
                                                        @if (data_get($bid, 'get.website_link'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Website Link:</div>
                                                            <div>
                                                                @php
                                                                    $websiteLink = data_get($bid, 'get.website_link');
                                                                    if (!empty($websiteLink) && !str_starts_with($websiteLink, 'http://') && !str_starts_with($websiteLink, 'https://')) {
                                                                        $websiteLink = 'https://' . $websiteLink;
                                                                    }
                                                                @endphp
                                                                <a href="{{ $websiteLink }}"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="text-primary text-decoration-none">
                                                                    <i class="fa fa-globe me-1"></i>
                                                                    Visit Website
                                                                </a>
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Social Media Platforms -->
                                                        @if (data_get($bid, 'get.social_media'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Social
                                                                Media Platforms:</div>
                                                            <div>
                                                                @foreach (data_get($bid, 'get.social_media') as $social)
                                                                @php
                                                                // Convert object to array
                                                                $socialArray = (array) $social;
                                                                @endphp
                                                                @if (!empty($socialArray['platform']) && !empty($socialArray['url']))
                                                                <div class="mb-1">
                                                                    @php
                                                                    $socialUrl =
                                                                    $socialArray[
                                                                    'url'
                                                                    ];
                                                                    // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                    if (
                                                                    !empty(
                                                                    $socialUrl
                                                                    ) &&
                                                                    !str_starts_with(
                                                                    $socialUrl,
                                                                    'http://',
                                                                    ) &&
                                                                    !str_starts_with(
                                                                    $socialUrl,
                                                                    'https://',
                                                                    )
                                                                    ) {
                                                                    $socialUrl =
                                                                    'https://' .
                                                                    $socialUrl;
                                                                    }
                                                                    @endphp
                                                                    <a href="{{ $socialUrl }}"
                                                                        target="_blank"
                                                                        class="text-primary text-decoration-none">
                                                                        <i
                                                                            class="fab fa-{{ strtolower($socialArray['platform']) }} me-1"></i>
                                                                        @if (!empty($socialArray['text']))
                                                                        {{ $socialArray['text'] }}
                                                                        @else
                                                                        {{ $socialArray['platform'] }}
                                                                        @endif
                                                                    </a>
                                                                </div>
                                                                @endif
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Licensed Year -->
                                                        @if (data_get($bid, 'get.year_licensed'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Licensed Year:</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.year_licensed') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                    </div>
                                                    @endif
                                                    {{-- End of Agent Overview section (listing owner or bid owner) --}}

                                                    <!-- 2. Broker Compensation & Agency Agreement Terms -->
                                                    @if (data_get($bid, 'get.commission_structure') ||
                                                    data_get($bid, 'get.lease_fee_type') ||
                                                    data_get($bid, 'get.interested_purchase_fee_type') ||
                                                    data_get($bid, 'get.interested_lease_option_agreement') ||
                                                    data_get($bid, 'get.protection_period') ||
                                                    data_get($bid, 'get.early_termination_fee_option') ||
                                                    data_get($bid, 'get.retainer_fee_option') ||
                                                    data_get($bid, 'get.agency_agreement_timeframe') ||
                                                    data_get($bid, 'get.brokerage_relationship'))
                                                    <div class="mb-5">
                                                        <h6 class="mb-3"
                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                                                        </h6>

                                                        @php
                                                        // Mismatch highlighting style for Match Score
                                                        $mismatchStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545;';
                                                        $mismatchBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.7rem; vertical-align: middle;">Mismatch</span>';
                                                        @endphp
                                                        
                                                        <!-- A) Tenant's Broker Compensation -->
                                                        @if (data_get($bid, 'get.commission_structure') || data_get($bid, 'get.lease_fee_type') || data_get($bid, 'get.payment_timing') || data_get($bid, 'get.days_to_pay'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Tenant's Broker Compensation</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (data_get($bid, 'get.commission_structure'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['commission_structure']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Tenant's Broker Commission Structure:</span> {{ data_get($bid, 'get.commission_structure') }}{!! isset($brokerMismatches['commission_structure']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.lease_fee_type'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['lease_fee_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Tenant's Broker Commission Fee:</span> {{ $commissionFeeDisplay }}{!! isset($brokerMismatches['lease_fee_type']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.payment_timing'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['payment_timing']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Payment Timing for Broker Fees:</span> {{ data_get($bid, 'get.payment_timing') }}{!! isset($brokerMismatches['payment_timing']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.days_to_pay'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['days_to_pay']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Calendar Days To Pay:</span> {{ data_get($bid, 'get.days_to_pay') }}{!! isset($brokerMismatches['days_to_pay']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- B) Purchase Fee Details -->
                                                        @if (data_get($bid, 'get.interested_purchase_fee_type'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Purchase Fee Details</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Interested in Purchasing a Property:</span> {{ data_get($bid, 'get.interested_purchase_fee_type') }}{!! isset($brokerMismatches['interested_purchase_fee_type']) ? $mismatchBadge : '' !!}</li>
                                                                @if (data_get($bid, 'get.interested_purchase_fee_type') === 'Yes' && $purchaseFeeDisplay !== '—')
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['purchase_fee_type']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Purchase Fee:</span> {{ $purchaseFeeDisplay }}{!! isset($brokerMismatches['purchase_fee_type']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- C) Lease-Option Details -->
                                                        @if (data_get($bid, 'get.interested_lease_option_agreement'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Details</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Interested in a Lease-Option Agreement:</span> {{ data_get($bid, 'get.interested_lease_option_agreement') }}{!! isset($brokerMismatches['interested_lease_option_agreement']) ? $mismatchBadge : '' !!}</li>
                                                                @if (data_get($bid, 'get.interested_lease_option_agreement') === 'Yes')
                                                                    @if ($leaseOptionCreatedDisplay !== '—')
                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Compensation (When Option Is Created):</span> {{ $leaseOptionCreatedDisplay }}{!! isset($brokerMismatches['lease_type']) || isset($brokerMismatches['lease_value']) ? $mismatchBadge : '' !!}</li>
                                                                    @endif
                                                                    @if ($leaseOptionExercisedDisplay !== '—')
                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Compensation (If Purchase Option Exercised):</span> {{ $leaseOptionExercisedDisplay }}{!! isset($brokerMismatches['purchase_type']) || isset($brokerMismatches['purchase_value']) ? $mismatchBadge : '' !!}</li>
                                                                    @endif
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- D) Legal Terms -->
                                                        @if (data_get($bid, 'get.protection_period') || data_get($bid, 'get.early_termination_fee_option') || data_get($bid, 'get.retainer_fee_option') || data_get($bid, 'get.agency_agreement_timeframe'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Legal Terms</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (data_get($bid, 'get.protection_period'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['protection_period']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Protection Period Timeframe:</span> {{ data_get($bid, 'get.protection_period') }} days{!! isset($brokerMismatches['protection_period']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.early_termination_fee_option'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_option']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Early Termination Fee:</span> {{ data_get($bid, 'get.early_termination_fee_option') }}{!! isset($brokerMismatches['early_termination_fee_option']) ? $mismatchBadge : '' !!}</li>
                                                                    @if ($terminationFeeDisplay !== '—')
                                                                    <li class="mb-1" style="{{ isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Termination Fee Amount:</span> {{ $terminationFeeDisplay }}{!! isset($brokerMismatches['early_termination_fee_amount']) ? $mismatchBadge : '' !!}</li>
                                                                    @endif
                                                                @endif
                                                                @if (data_get($bid, 'get.retainer_fee_option'))
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_option']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Retainer Fee:</span> {{ data_get($bid, 'get.retainer_fee_option') }}{!! isset($brokerMismatches['retainer_fee_option']) ? $mismatchBadge : '' !!}</li>
                                                                    @if (data_get($bid, 'get.retainer_fee_option') === 'Yes')
                                                                        @if (data_get($bid, 'get.retainer_fee_amount'))
                                                                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_amount']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Retainer Fee Amount:</span> ${{ number_format((float)data_get($bid, 'get.retainer_fee_amount'), 2) }}{!! isset($brokerMismatches['retainer_fee_amount']) ? $mismatchBadge : '' !!}</li>
                                                                        @endif
                                                                        @if (data_get($bid, 'get.retainer_fee_application'))
                                                                        <li class="mb-1" style="{{ isset($brokerMismatches['retainer_fee_application']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Retainer Fee Application:</span> 
                                                                            @if (data_get($bid, 'get.retainer_fee_application') === 'applied')
                                                                            Applied toward final compensation
                                                                            @else
                                                                            Charged in addition to final compensation
                                                                            @endif
                                                                            {!! isset($brokerMismatches['retainer_fee_application']) ? $mismatchBadge : '' !!}
                                                                        </li>
                                                                        @endif
                                                                    @endif
                                                                @endif
                                                                @if (data_get($bid, 'get.agency_agreement_timeframe'))
                                                                @php
                                                                    $agencyTimeframe = data_get($bid, 'get.agency_agreement_timeframe');
                                                                    $agencyTimeframeCustom = data_get($bid, 'get.agency_agreement_custom');
                                                                    $isOtherTimeframe = is_string($agencyTimeframe) && strtolower(trim($agencyTimeframe)) === 'other';
                                                                    $agencyTimeframeDisplay = $isOtherTimeframe ? ($agencyTimeframeCustom ?: 'Other') : ($agencyTimeframe ?: '');
                                                                @endphp
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Tenant Agency Agreement Timeframe:</span> {{ $agencyTimeframeDisplay }}{!! isset($brokerMismatches['agency_agreement_timeframe']) ? $mismatchBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- E) Brokerage Relationship -->
                                                        @if (data_get($bid, 'get.brokerage_relationship'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Brokerage Relationship</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1" style="{{ isset($brokerMismatches['brokerage_relationship']) ? $mismatchStyle : '' }}"><span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ data_get($bid, 'get.brokerage_relationship') }}{!! isset($brokerMismatches['brokerage_relationship']) ? $mismatchBadge : '' !!}</li>
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- F) Additional Terms / Additional Details -->
                                                        @if (data_get($bid, 'get.additional_details_broker') || data_get($bid, 'get.additional_details'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">F) Additional Terms / Additional Details</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (data_get($bid, 'get.additional_details_broker'))
                                                                <li class="mb-1"><span class="fw-semibold">Additional Terms:</span> {{ data_get($bid, 'get.additional_details_broker') }}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.additional_details'))
                                                                <li class="mb-1"><span class="fw-semibold">Additional Details:</span> {{ data_get($bid, 'get.additional_details') }}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif

                                                    <!-- 3. Offered Services -->
                                                    @php
                                                    // Use the SAME service categories as the listing display (lines 767-872)
                                                    // IMPORTANT: Use the exact same category definitions from the listing - copied verbatim to preserve apostrophe characters
                                                    $bidPropType = @$auction->get->property_type ?? 'Residential Property';
                                                    $bidIsResidential = $bidPropType === 'Residential Property';
                                                    $bidIsCommercial = $bidPropType === 'Commercial Property';

                                                    // Use the exact same categories defined in the listing (lines 767-872)
                                                    // This is the single source of truth - same arrays used by the listing display
                                                    $bidResidentialCategories = [
                                                    '📢 Tenant Criteria Marketing & Promotion' => [
                                                        'Create a branded flyer summarizing the Tenant’s rental criteria',
                                                        'Post the Tenant’s rental criteria on Craigslist under the "Real Estate Wanted" section',
                                                        'Share the Tenant’s rental criteria on Nextdoor in Neighborhood or Community Groups',
                                                        'Promote the Tenant’s rental criteria on Facebook in Rental or Housing Groups',
                                                        'Share the Tenant’s rental criteria on Instagram using posts, stories, or reels',
                                                        'Promote the Tenant’s rental criteria on LinkedIn in Real Estate or Housing Groups',
                                                        'Upload a TikTok video summarizing the Tenant’s rental criteria',
                                                        'Upload a YouTube video summarizing the Tenant’s rental criteria',
                                                        'Launch a mass email campaign promoting the Tenant’s rental criteria',
                                                        'Distribute branded postcards or flyers in the Tenant’s preferred neighborhoods',
                                                        'Launch hyperlocal digital ads targeting the Tenant’s preferred rental areas',
                                                    ],
                                                    '🔍 Property Search, Alerts & Matching' => [
                                                        'Send email alerts with new listings from the MLS that match the Tenant’s rental criteria',
                                                        'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant’s rental criteria',
                                                        'Communicate with the Landlord’s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
                                                        'Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit',
                                                    ],
                                                    '🏡 Property Showings & Virtual Tours' => [
                                                        'Schedule and attend property showings with the Tenant',
                                                        'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
                                                        'Preview properties on behalf of the Tenant upon request',
                                                        'Provide factual observations on property layout and condition',
                                                    ],
                                                    '📝 Tenant Application Support' => [
                                                        'Provide the Tenant with application instructions or links to an online rental application platform',
                                                        'Gather and organize required supporting documents (e.g., identification, income verification, reference letters)',
                                                        'Submit complete and organized application packages to the Landlord’s Agent, Landlord, or Property Manager for review',
                                                        'Answer questions about the application process, screening timelines, and required documentation',
                                                    ],
                                                    '📃 Lease Preparation & Execution' => [
                                                        'Review lease offers and assist the Tenant in preparing questions or requested changes',
                                                        'Coordinate lease negotiation with the Landlord’s Agent, Landlord, or Property Manager',
                                                        'Assist with completing required lease disclosures and reviewing key lease terms',
                                                        'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
                                                    ],
                                                    '🚚 Move-In Support & Coordination' => [
                                                        'Coordinate move-in date and key handoff logistics with the Landlord’s Agent, Landlord or Property Manager',
                                                        'Confirm completion of any agreed-upon pre-move-in cleaning or repairs',
                                                        'Provide a utility setup checklist and local provider resources',
                                                        'Share a move-in checklist for documentation and property condition review',
                                                        'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods',
                                                    ],
                                                    '💡 Leasing Strategy & Guidance' => [
                                                        'Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions',
                                                        'Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)',
                                                        'Provide general guidance on Tenant rights and Landlord responsibilities under state law',
                                                        'Provide general guidance on lease clauses, payment terms, and renewal options',
                                                    ],
                ];

                                                    $bidCommercialCategories = [
                                                    '📢 Tenant Criteria Marketing & Promotion' => [
                                                        'Create a branded flyer summarizing the Tenant’s leasing criteria',
                                                        'Post the Tenant’s leasing criteria on Craigslist under the "Office/Commercial" or "Retail" section',
                                                        'Promote the Tenant’s leasing criteria on Facebook in Commercial Leasing or Business Groups',
                                                        'Share the Tenant’s leasing criteria on Instagram using posts, stories, or reels',
                                                        'Promote the Tenant’s leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups',
                                                        'Upload a TikTok video summarizing the Tenant’s leasing criteria',
                                                        'Upload a YouTube video summarizing the Tenant’s leasing criteria',
                                                        'Launch a mass email campaign promoting the Tenant’s leasing criteria',
                                                        'Distribute branded postcards or flyers in the Tenant’s preferred neighborhoods',
                                                        'Launch hyperlocal digital ads targeting the Tenant’s preferred leasing areas',
                                                    ],
                                                    '🔍 Property Search, Alerts & Matching' => [
                                                        'Send listing alerts from commercial platforms (e.g., LoopNet, Crexi, CoStar, or local MLS) that match the Tenant’s leasing criteria',
                                                        'Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant’s rental criteria',
                                                        'Communicate with the Landlord’s Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions',
                                                        'Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment',
                                                    ],
                                                    '🏢 Property Showings & Virtual Tours' => [
                                                        'Schedule and attend property tours with the Tenant',
                                                        'Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs',
                                                        'Preview properties on behalf of the Tenant upon request',
                                                        'Provide factual notes on layout, access, parking, visibility, and other operational considerations',
                                                    ],
                                                    '📝 Tenant Application Support' => [
                                                        'Provide the Tenant with application instructions or links to online platforms',
                                                        'Gather and organize required supporting documents (e.g., business licenses, financials, references)',
                                                        'Submit complete and organized application packages to the Landlord’s Agent, Landlord, or Property Manager',
                                                    ],
                                                    '📃 Lease Preparation, LOI & Execution' => [
                                                        'Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant’s business needs and proposed terms',
                                                        'Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)',
                                                        'Coordinate with the Landlord’s Agent, Landlord or Property Manager to finalize lease terms',
                                                        'Review lease drafts and coordinate revisions through appropriate channels',
                                                        'Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties',
                                                        'Track required deposits, rent commencement, and key lease dates to ensure move-in readiness',
                                                    ],
                                                    '🚚 Move-In Support & Coordination' => [
                                                        'Coordinate move-in date and key handoff logistics with the Landlord, Landlord’s Agent, or Property Manager',
                                                        'Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout',
                                                        'Provide a utility setup checklist and local provider resources',
                                                        'Share a move-in checklist for documentation and property condition review',
                                                        'Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods',
                                                    ],
                                                    '💡 Leasing Strategy & Guidance' => [
                                                        'Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends',
                                                        'Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences',
                                                        'Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law',
                                                        'Provide general guidance on lease clauses, escalation terms, and space usage considerations',
                                                    ],
                ];

                                                    $bidCategories = $bidIsCommercial ? $bidCommercialCategories : $bidResidentialCategories;
                                                    
                                                    // Flatten function to extract all service strings from nested arrays/objects
                                                    $flattenBidServices = function($data) use (&$flattenBidServices) {
                                                        $result = [];
                                                        if (is_array($data) || is_object($data)) {
                                                            foreach ((array)$data as $value) {
                                                                if (is_string($value) && !empty(trim($value)) && $value !== 'Other') {
                                                                    $result[] = trim($value);
                                                                } elseif (is_array($value) || is_object($value)) {
                                                                    $result = array_merge($result, $flattenBidServices($value));
                                                                }
                                                            }
                                                        } elseif (is_string($data) && !empty(trim($data)) && $data !== 'Other') {
                                                            $result[] = trim($data);
                                                        }
                                                        return $result;
                                                    };
                                                    
                                                    // Parse services - handle both array and JSON string formats
                                                    $rawBidServices = data_get($bid, 'get.services', []);
                                                    if (is_string($rawBidServices) && !empty($rawBidServices)) {
                                                        $decoded = json_decode($rawBidServices, true);
                                                        $parsedBidServices = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                                                    } elseif (is_array($rawBidServices) || is_object($rawBidServices)) {
                                                        $parsedBidServices = $rawBidServices;
                                                    } else {
                                                        $parsedBidServices = [];
                                                    }
                                                    // Flatten nested arrays to get simple list of service strings
                                                    $bidAllServices = array_unique($flattenBidServices($parsedBidServices));
                                                    
                                                    // Parse other_services - handle both array and JSON string formats
                                                    $rawBidOtherServices = data_get($bid, 'get.other_services', []);
                                                    if (is_string($rawBidOtherServices) && !empty($rawBidOtherServices)) {
                                                        $decoded = json_decode($rawBidOtherServices, true);
                                                        $bidOtherServices = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                                                    } elseif (is_array($rawBidOtherServices) || is_object($rawBidOtherServices)) {
                                                        $bidOtherServices = (array)$rawBidOtherServices;
                                                    } else {
                                                        $bidOtherServices = [];
                                                    }
                                                    // Filter out empty strings from other_services
                                                    $bidOtherServices = array_filter($bidOtherServices, fn($s) => is_string($s) && !empty(trim($s)));
                                                    $bidOtherServices = array_values($bidOtherServices);
                                                    
                                                    $hasAnyBidServices = !empty($bidAllServices) || !empty($bidOtherServices);
                                                    @endphp
                                                    
                                                    @php
                                                    // Service ADDED by agent (green - bonus services they offer)
                                                    $svcAddedStyle = 'background-color: #d4edda; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #28a745;';
                                                    $svcAddedBadge = '<span class="badge bg-success ms-2" style="font-size: 0.65rem; vertical-align: middle;">Extra Service Offered</span>';
                                                    
                                                    // Missing service style (was in baseline, but agent didn't include - RED)
                                                    $svcMissingStyle = 'background-color: #ffe6e6; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #dc3545; text-decoration: line-through; color: #721c24;';
                                                    $svcMissingBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.65rem; vertical-align: middle;">Not Offered by Agent</span>';
                                                    
                                                    // Check if a service exists in baseline (normalized comparison)
                                                    $checkServiceInBaseline = function($service) use ($baselineNorm, $normalizeService) {
                                                        $normService = $normalizeService($service);
                                                        return in_array($normService, $baselineNorm);
                                                    };
                                                    
                                                    // Check if a service exists in current bid (normalized comparison)
                                                    $checkServiceInBid = function($service) use ($currentNorm, $normalizeService) {
                                                        $normService = $normalizeService($service);
                                                        return in_array($normService, $currentNorm);
                                                    };
                                                    
                                                    // Get services in baseline but NOT in current bid (missing services)
                                                    $missingServices = [];
                                                    foreach ($baselineServices as $baselineSvc) {
                                                        if (!$checkServiceInBid($baselineSvc)) {
                                                            $missingServices[] = $baselineSvc;
                                                        }
                                                    }
                                                    @endphp
                                                    
                                                    <div class="mb-5">
                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa fa-clipboard-list me-2"></i>Offered Services
                                                        </h6>
                                                        
                                                        @if ($hasAnyBidServices)
                                                            @foreach ($bidCategories as $bidCategoryName => $bidCategoryServices)
                                                                @php
                                                                    $bidMatchedServices = array_filter($bidAllServices, function($service) use ($bidCategoryServices) {
                                                                        return in_array($service, $bidCategoryServices);
                                                                    });
                                                                @endphp
                                                                @if (!empty($bidMatchedServices))
                                                                <div class="mb-3">
                                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $bidCategoryName }}</div>
                                                                    <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem;">
                                                                        @foreach ($bidMatchedServices as $bidService)
                                                                            @php $svcInBaseline = $checkServiceInBaseline($bidService); @endphp
                                                                            <li style="font-size: 0.9rem; margin-bottom: 4px; {{ !$svcInBaseline ? $svcAddedStyle : '' }}">{{ $bidService }}{!! !$svcInBaseline ? $svcAddedBadge : '' !!}</li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                                @endif
                                                            @endforeach

                                                            @if (!empty($bidOtherServices))
                                                            <div class="mb-3">
                                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                                <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem;">
                                                                    @foreach ($bidOtherServices as $bidOtherService)
                                                                        @php $svcInBaseline = $checkServiceInBaseline($bidOtherService); @endphp
                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; {{ !$svcInBaseline ? $svcAddedStyle : '' }}">{{ $bidOtherService }}{!! !$svcInBaseline ? $svcAddedBadge : '' !!}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                            @endif
                                                            
                                                            {{-- Show services that were in baseline but agent didn't include --}}
                                                            @if ($isListingOwner && !empty($missingServices))
                                                            <div class="mt-4 p-3" style="background-color: #ffe6e6; border-radius: 8px; border: 1px solid #dc3545;">
                                                                <div class="fw-bold mb-2" style="color: #721c24; font-size: 0.95rem;">
                                                                    <i class="fa fa-times-circle me-2"></i>Services You Requested But Agent Didn't Include ({{ count($missingServices) }})
                                                                </div>
                                                                <ul class="mb-0" style="padding-left: 1.2rem;">
                                                                    @foreach ($missingServices as $missingSvc)
                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px; {{ $svcMissingStyle }}">{{ $missingSvc }}{!! $svcMissingBadge !!}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                            @endif
                                                        @else
                                                        <div class="text-muted" style="font-style: italic;">No services selected for this bid.</div>
                                                        @endif
                                                    </div>

                                                    <!-- 4. Agent Presentation & Promotional Materials -->
                                                    @if (data_get($bid, 'get.presentation_link') ||
                                                    data_get($bid, 'get.video_upload') ||
                                                    data_get($bid, 'get.business_card_link') ||
                                                    data_get($bid, 'get.business_card') ||
                                                    data_get($bid, 'get.promoMaterials'))
                                                    <div class="mb-5">
                                                        <h6 class="mb-3"
                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i
                                                                class="fa fa-chart-line me-2"></i>Agent
                                                            Presentation & Promotional Materials
                                                        </h6>

                                                        <!-- Virtual Presentation Section -->
                                                        @if (data_get($bid, 'get.presentation_link') || data_get($bid, 'get.video_upload'))
                                                        <div class="mb-4">
                                                            <div class="fw-semibold mb-2"
                                                                style="color: #049399;">Virtual
                                                                Agent Presentation</div>

                                                            @if (data_get($bid, 'get.presentation_link'))
                                                            <div class="mb-2">
                                                                @php
                                                                $presentationLink = data_get(
                                                                $bid,
                                                                'get.presentation_link',
                                                                );
                                                                // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                if (
                                                                !empty(
                                                                $presentationLink
                                                                ) &&
                                                                !str_starts_with(
                                                                $presentationLink,
                                                                'http://',
                                                                ) &&
                                                                !str_starts_with(
                                                                $presentationLink,
                                                                'https://',
                                                                )
                                                                ) {
                                                                $presentationLink =
                                                                'https://' .
                                                                $presentationLink;
                                                                }
                                                                @endphp
                                                                <a href="{{ $presentationLink }}"
                                                                    target="_blank"
                                                                    class="text-primary text-decoration-none">
                                                                    <i
                                                                        class="fa fa-external-link-alt me-1"></i>
                                                                    Watch Presentation
                                                                </a>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.video_upload'))
                                                            <div class="mb-2">
                                                                <div class="fw-medium mb-1"
                                                                    style="color: #049399;">
                                                                    Uploaded Video:</div>
                                                                @if (is_string(data_get($bid, 'get.video_upload')))
                                                                <video controls
                                                                    style="width: 100%; max-width: 400px; border-radius: 6px; background: #000;">
                                                                    <source
                                                                        src="{{ asset('storage/' . data_get($bid, 'get.video_upload')) }}"
                                                                        type="video/mp4">
                                                                    Your browser does
                                                                    not support the
                                                                    video tag.
                                                                </video>
                                                                @else
                                                                <div
                                                                    class="text-muted">
                                                                    <i
                                                                        class="fa fa-video me-1"></i>
                                                                    Video file uploaded
                                                                </div>
                                                                @endif
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Business Card Section -->
                                                        @if (data_get($bid, 'get.business_card_link') || data_get($bid, 'get.business_card'))
                                                        <div class="mb-4">
                                                            <div class="fw-semibold mb-2"
                                                                style="color: #049399;">
                                                                Business Card:</div>

                                                            @if (data_get($bid, 'get.business_card_link'))
                                                            <div class="mb-3">
                                                                @php
                                                                    $businessCardLink = data_get($bid, 'get.business_card_link');
                                                                    if (!empty($businessCardLink) && !str_starts_with($businessCardLink, 'http://') && !str_starts_with($businessCardLink, 'https://')) {
                                                                        $businessCardLink = 'https://' . $businessCardLink;
                                                                    }
                                                                @endphp
                                                                <a href="{{ $businessCardLink }}"
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    class="btn btn-outline-primary btn-sm">
                                                                    <i class="fa fa-external-link-alt me-1"></i>
                                                                    View Business Card (Link)
                                                                </a>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.business_card'))
                                                            <div class="mb-2">
                                                                @if (is_string(data_get($bid, 'get.business_card')))
                                                                @php
                                                                    $businessCardPath = data_get($bid, 'get.business_card');
                                                                    $businessCardExtension = pathinfo($businessCardPath, PATHINFO_EXTENSION);
                                                                    $businessCardUrl = asset('storage/' . $businessCardPath);
                                                                @endphp

                                                                @if (in_array(strtolower($businessCardExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                                                <div class="business-card-preview mb-2">
                                                                    <a href="{{ $businessCardUrl }}" target="_blank" rel="noopener noreferrer" title="Click to view full size">
                                                                        <img src="{{ $businessCardUrl }}"
                                                                            style="max-width: 450px; width: 100%; height: auto; border-radius: 8px; border: 2px solid #e0e0e0; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                                                                            alt="Business Card"
                                                                            class="img-fluid">
                                                                    </a>
                                                                </div>
                                                                <div class="d-flex gap-2 mt-2">
                                                                    <a href="{{ $businessCardUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                                                        <i class="fa fa-expand me-1"></i> View Full Size
                                                                    </a>
                                                                    <a href="{{ $businessCardUrl }}" download class="btn btn-outline-success btn-sm">
                                                                        <i class="fa fa-download me-1"></i> Download
                                                                    </a>
                                                                </div>
                                                                @else
                                                                <div class="d-flex align-items-center p-3 border rounded bg-light">
                                                                    <i class="fa fa-file-alt fa-2x text-muted me-3"></i>
                                                                    <div class="flex-grow-1">
                                                                        <div class="fw-medium">Business Card File</div>
                                                                        <small class="text-muted">{{ strtoupper($businessCardExtension) }} file</small>
                                                                    </div>
                                                                    <a href="{{ $businessCardUrl }}" download class="btn btn-outline-primary btn-sm">
                                                                        <i class="fa fa-download me-1"></i> Download
                                                                    </a>
                                                                </div>
                                                                @endif
                                                                @else
                                                                <div class="text-muted">
                                                                    <i class="fa fa-id-card me-1"></i>
                                                                    Business card uploaded
                                                                </div>
                                                                @endif
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Marketing Materials Section -->
                                                        @if (data_get($bid, 'get.promoMaterials'))
                                                        @php
                                                            $hasAnyMaterials = false;
                                                            $promoMaterialsRaw = data_get($bid, 'get.promoMaterials', []);
                                                            // Normalize: ensure we have an array of arrays (not stdClass)
                                                            $promoMaterialsNormalized = [];
                                                            if (is_array($promoMaterialsRaw) || is_object($promoMaterialsRaw)) {
                                                                foreach($promoMaterialsRaw as $m) {
                                                                    // Convert stdClass to array
                                                                    $mArr = is_object($m) ? (array) $m : (is_array($m) ? $m : []);
                                                                    $promoMaterialsNormalized[] = $mArr;
                                                                    if (!empty($mArr['type']) || !empty($mArr['link']) || !empty($mArr['files'])) {
                                                                        $hasAnyMaterials = true;
                                                                    }
                                                                }
                                                            }
                                                        @endphp
                                                        <div>
                                                            <div class="fw-semibold mb-2"
                                                                style="color: #049399;">
                                                                Marketing Materials:</div>

                                                            @if ($hasAnyMaterials)
                                                            @foreach ($promoMaterialsNormalized as $index => $material)
                                                            @php
                                                                $matType = data_get($material, 'type', '');
                                                                $matOther = data_get($material, 'other', '');
                                                                $matLink = data_get($material, 'link', '');
                                                                $matFiles = data_get($material, 'files', []);
                                                                // Normalize files array too
                                                                if (is_object($matFiles)) { $matFiles = (array) $matFiles; }
                                                            @endphp
                                                            @if (!empty($matType) || !empty($matLink) || !empty($matFiles))
                                                            <div class="mb-3 p-3 border rounded bg-light">
                                                                @if (!empty($matType))
                                                                <div class="fw-medium mb-2" style="color: #049399; font-size: 1rem;">
                                                                    <i class="fa fa-folder-open me-1"></i>
                                                                    {{ $matType }}
                                                                    @if ($matType === 'Other' && !empty($matOther))
                                                                    - {{ $matOther }}
                                                                    @endif
                                                                </div>
                                                                @endif

                                                                @if (!empty($matLink))
                                                                <div class="mb-2">
                                                                    @php
                                                                        $materialLink = $matLink;
                                                                        if (!empty($materialLink) && !str_starts_with($materialLink, 'http://') && !str_starts_with($materialLink, 'https://')) {
                                                                            $materialLink = 'https://' . $materialLink;
                                                                        }
                                                                    @endphp
                                                                    <a href="{{ $materialLink }}"
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        class="btn btn-outline-primary btn-sm">
                                                                        <i class="fa fa-external-link-alt me-1"></i>
                                                                        Open Link
                                                                    </a>
                                                                </div>
                                                                @endif

                                                                @if (!empty($matFiles))
                                                                <div class="mb-2">
                                                                    <div class="fw-medium mb-2" style="color: #34465c; font-size: 0.9rem;">Uploaded Files:</div>
                                                                    <div class="row g-2">
                                                                        @foreach ($matFiles as $fileIndex => $filePath)
                                                                        @if (is_string($filePath))
                                                                        @php
                                                                            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
                                                                            $fileName = basename($filePath);
                                                                            $fileUrl = asset('storage/' . $filePath);
                                                                            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                                                                            $isImage = in_array(strtolower($fileExtension), $imageExtensions);
                                                                        @endphp

                                                                        <div class="col-md-6 mb-2">
                                                                            <div class="border rounded p-2 bg-white d-flex align-items-center">
                                                                                @if ($isImage)
                                                                                <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer">
                                                                                    <img src="{{ $fileUrl }}"
                                                                                        style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 10px;"
                                                                                        alt="Marketing Material">
                                                                                </a>
                                                                                @else
                                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-2"
                                                                                    style="width: 60px; height: 60px;">
                                                                                    <i class="fa fa-file fa-lg text-muted"></i>
                                                                                </div>
                                                                                @endif
                                                                                <div class="flex-grow-1 overflow-hidden">
                                                                                    <div class="small text-truncate fw-medium">{{ $fileName }}</div>
                                                                                    <small class="text-muted">{{ strtoupper($fileExtension) }} file</small>
                                                                                </div>
                                                                                <div class="d-flex gap-1 ms-2">
                                                                                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" title="View">
                                                                                        <i class="fa fa-eye"></i>
                                                                                    </a>
                                                                                    <a href="{{ $fileUrl }}" download class="btn btn-sm btn-outline-success" title="Download">
                                                                                        <i class="fa fa-download"></i>
                                                                                    </a>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        @endif
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                                @endif
                                                            </div>
                                                            @endif
                                                            @endforeach
                                                            @else
                                                            <div class="text-muted"><i class="fa fa-info-circle me-1"></i> Not provided</div>
                                                            @endif
                                                        </div>
                                                        @else
                                                        <div class="mb-4">
                                                            <div class="fw-semibold mb-2" style="color: #049399;">Marketing Materials:</div>
                                                            <div class="text-muted"><i class="fa fa-info-circle me-1"></i> Not provided</div>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif

                                                    <!-- 5. Agent Credentials and Contact Information - visible to listing owner or bid owner -->
                                                    @if ($isListingOwner || $isBidOwner)
                                                    <div class="mb-4">
                                                        <h6 class="mb-3"
                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i
                                                                class="fa fa-address-card me-2"></i>Agent
                                                            Credentials and Contact Information
                                                        </h6>

                                                        <div class="row">
                                                            <!-- First Name -->
                                                            @if (data_get($bid, 'get.first_name'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">First
                                                                    Name</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.first_name') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- Last Name -->
                                                            @if (data_get($bid, 'get.last_name'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Last
                                                                    Name</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.last_name') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- Phone Number -->
                                                            @if (data_get($bid, 'get.phone'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Phone
                                                                    Number</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.phone') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- Email -->
                                                            @if (data_get($bid, 'get.email'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Email
                                                                </div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.email') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- Brokerage -->
                                                            @if (data_get($bid, 'get.brokerage'))
                                                            <div class="col-12 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">
                                                                    Brokerage</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.brokerage') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- License Number -->
                                                            @if (data_get($bid, 'get.license_no'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">Real
                                                                    Estate License #</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.license_no') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            <!-- NAR Member ID -->
                                                            @if (data_get($bid, 'get.nar_id'))
                                                            <div class="col-md-6 mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">NAR
                                                                    Member ID</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.nar_id') }}
                                                                </div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @endif
                                                    {{-- End of Agent Credentials section (listing owner or bid owner) --}}

                                                </div>
                                                <div class="modal-footer"
                                                    style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px;">
                                                    <div class="w-100 mb-3 p-3 text-center"
                                                        style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                                                        <i class="fa fa-shield me-2"></i>
                                                        <strong>Confidential:</strong> This information
                                                        is private and only visible to you{{ $isListingOwner ? ' as the listing owner' : '' }}.
                                                    </div>
                                                    
                                                    @if ($isListingOwner && $bidAccepted !== 'accepted' && $bidAccepted !== 'rejected')
                                                        @php
                                                            // Traditional: show if not expired; Bidding Period: show when timer ends
                                                            $showActionButtons = ($isTraditionalListing && !$isExpired) || ($isBiddingPeriodListing && $isExpired);
                                                        @endphp
                                                        @if ($showActionButtons)
                                                        {{-- Traditional (not expired) OR Bidding Period (timer ended): show buttons --}}
                                                        <div class="d-flex gap-3 justify-content-center align-items-center w-100 mb-3" style="flex-wrap: nowrap;">
                                                            <form action="{{ route('tenant.hire.agent.auction.bid.accept') }}" method="POST" style="margin: 0;"
                                                                  onsubmit="return confirm('Are you sure you want to accept this bid? This will reject all other bids.');">
                                                                @csrf
                                                                <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                                                <button type="submit" class="btn btn-success" style="padding: 10px 20px; font-size: 0.95rem; background-color: #28a745 !important; border-color: #28a745 !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                                    <i class="fa fa-check me-1"></i> Accept Bid
                                                                </button>
                                                            </form>
                                                            
                                                            <a href="{{ route('tenant.counter-terms', ['id' => data_get($bid, 'id')]) }}" 
                                                               class="btn btn-primary" style="padding: 10px 20px; font-size: 0.95rem; background-color: #0d6efd !important; border-color: #0d6efd !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                                                <i class="fa fa-exchange-alt me-1"></i> Counter Bid
                                                            </a>
                                                            
                                                            <form action="{{ route('tenant.hire.agent.auction.bid.reject') }}" method="POST" style="margin: 0;"
                                                                  onsubmit="return confirm('Are you sure you want to reject this bid?');">
                                                                @csrf
                                                                <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                                                                <button type="submit" class="btn btn-danger" style="padding: 10px 20px; font-size: 0.95rem; background-color: #dc3545 !important; border-color: #dc3545 !important; color: #fff !important; min-width: 130px; height: 42px; display: inline-flex; align-items: center; justify-content: center;">
                                                                    <i class="fa fa-times me-1"></i> Reject Bid
                                                                </button>
                                                            </form>
                                                        </div>
                                                        @elseif ($isBiddingPeriodListing && !$canTakeAction)
                                                        {{-- Bidding Period: timer still active, actions locked --}}
                                                        <div class="w-100 mb-3 p-2 text-center" style="background: #fff3cd; border-radius: 6px; color: #856404;">
                                                            <i class="fa fa-clock me-1"></i> Actions unlock when the bidding period ends.
                                                        </div>
                                                        @elseif ($isTraditionalListing && $isExpired)
                                                        {{-- Traditional listing has expired --}}
                                                        <div class="w-100 mb-3 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
                                                            <i class="fa fa-clock me-1"></i> Listing has expired - no further actions available. You can extend the expiration date by editing the listing.
                                                        </div>
                                                        @endif
                                                    @elseif ($isListingOwner && $bidAccepted === 'accepted')
                                                    <div class="w-100 mb-3 p-2 text-center" style="background: #d4edda; border-radius: 6px; color: #155724;">
                                                        <i class="fa fa-check-circle me-1"></i> This bid has been accepted
                                                    </div>
                                                    @php
                                                        $acceptedBidSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))->first();
                                                    @endphp
                                                    @if($acceptedBidSummary)
                                                    <div class="d-flex gap-2 flex-wrap justify-content-center mt-2 mb-3">
                                                        <a href="{{ route('accepted-bid-summary.view', $acceptedBidSummary->id) }}" class="btn btn-outline-primary btn-sm">
                                                            <i class="fa fa-file-alt me-1"></i> View Accepted Bid Summary
                                                        </a>
                                                        @if(!$acceptedBidSummary->isTenantSigned())
                                                        <a href="{{ route('accepted-bid-summary.sign-form', $acceptedBidSummary->id) }}" class="btn btn-primary btn-sm">
                                                            <i class="fa fa-signature me-1"></i> Tenant: E-Sign Acknowledgement
                                                        </a>
                                                        @endif
                                                        @if($acceptedBidSummary->isFullySigned())
                                                        <a href="{{ route('accepted-bid-summary.download-pdf', $acceptedBidSummary->id) }}" class="btn btn-success btn-sm">
                                                            <i class="fa fa-download me-1"></i> Download Signed PDF
                                                        </a>
                                                        @endif
                                                    </div>
                                                    @endif
                                                    @elseif ($isListingOwner && $bidAccepted === 'rejected')
                                                    <div class="w-100 mb-3 p-2 text-center" style="background: #f8d7da; border-radius: 6px; color: #721c24;">
                                                        <i class="fa fa-times-circle me-1"></i> This bid has been rejected
                                                    </div>
                                                    @elseif ($isListingOwner && $isExpired)
                                                    <div class="w-100 mb-3 p-2 text-center" style="background: #ffc107; border-radius: 6px; color: #856404;">
                                                        <i class="fa fa-clock me-1"></i> Auction has expired - no further actions available
                                                    </div>
                                                    @endif
                                                    
                                                    <button type="button" class="btn btn-secondary"
                                                        data-bs-dismiss="modal"
                                                        style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @else
                                    <!-- For non-owners, show appropriate message -->
                                    @if ($auth_id && data_get($bid, 'user_id') == $auth_id)
                                    <!-- Agent viewing their own bid -->
                                    <div class="alert alert-info mt-3 p-3 text-center"
                                        style="border-radius: 6px; background: #e8f4f5; color: #049399; border: none;">
                                        <i class="fa fa-eye me-2"></i> <strong>Your Private
                                            Terms:</strong> You can see your full compensation terms
                                        and additional details in your bid management dashboard.
                                    </div>
                                    @endif
                                    @endif

                                    {{-- Limited Modal for Bidding Period: Agent viewing another agent's bid (Anonymous) --}}
                                    @if ($isBiddingPeriodListing && $isAgentViewer && !$isBidOwner && !$isListingOwner)
                                    <div class="modal fade"
                                        id="limitedBidModal{{ data_get($bid, 'id') }}"
                                        tabindex="-1"
                                        aria-labelledby="limitedBidModalLabel{{ data_get($bid, 'id') }}"
                                        aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content" style="border-radius: 10px; border: none; position: relative; overflow: visible;">
                                                <button type="button" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; right: 5px; top: -12px; z-index: 1055; background: #333; border: 2px solid #fff; color: #fff; font-size: 1rem; line-height: 1; font-weight: bold; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
                                                    &times;
                                                </button>
                                                <div class="modal-header text-white" style="background: #049399; border-bottom: none; padding: 15px 20px; border-radius: 10px 10px 0 0;">
                                                    <h5 class="modal-title" id="limitedBidModalLabel{{ data_get($bid, 'id') }}" style="font-weight: 600;">
                                                        <i class="fa fa-handshake me-2"></i> Services & Broker Compensation Terms
                                                    </h5>
                                                </div>
                                                <div class="modal-body" style="background: #fafafa; padding: 25px;">

                                                    {{-- Anonymous Notice --}}
                                                    <div class="alert mb-4" style="background: #e8f4f5; color: #049399; border: none; border-radius: 6px;">
                                                        <i class="fa fa-user-secret me-2"></i>
                                                        <strong>Anonymous Bid:</strong> Agent identity is not displayed to other agents.
                                                    </div>

                                                    {{-- Section 1: Broker Compensation & Agency Agreement Terms --}}
                                                    @if (data_get($bid, 'get.commission_structure') ||
                                                    data_get($bid, 'get.lease_fee_type') ||
                                                    data_get($bid, 'get.interested_purchase_fee_type') ||
                                                    data_get($bid, 'get.interested_lease_option_agreement') ||
                                                    data_get($bid, 'get.protection_period') ||
                                                    data_get($bid, 'get.early_termination_fee_option') ||
                                                    data_get($bid, 'get.retainer_fee_option') ||
                                                    data_get($bid, 'get.agency_agreement_timeframe') ||
                                                    data_get($bid, 'get.brokerage_relationship'))
                                                    <div class="mb-5">
                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                                                        </h6>

                                                        {{-- A) Tenant's Broker Compensation --}}
                                                        @if (data_get($bid, 'get.commission_structure') || data_get($bid, 'get.lease_fee_type') || data_get($bid, 'get.payment_timing') || data_get($bid, 'get.days_to_pay'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Tenant's Broker Compensation</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (data_get($bid, 'get.commission_structure'))
                                                                <li class="mb-1"><span class="fw-semibold">Tenant's Broker Commission Structure:</span> {{ data_get($bid, 'get.commission_structure') }}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.lease_fee_type'))
                                                                <li class="mb-1"><span class="fw-semibold">Tenant's Broker Commission Fee:</span> {{ $commissionFeeDisplay }}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.payment_timing'))
                                                                <li class="mb-1"><span class="fw-semibold">Payment Timing for Broker Fees:</span> {{ data_get($bid, 'get.payment_timing') }}</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.days_to_pay'))
                                                                <li class="mb-1"><span class="fw-semibold">Calendar Days To Pay:</span> {{ data_get($bid, 'get.days_to_pay') }}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- B) Purchase Fee Details --}}
                                                        @if (data_get($bid, 'get.interested_purchase_fee_type'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Purchase Fee Details</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1"><span class="fw-semibold">Interested in Purchasing a Property:</span> {{ data_get($bid, 'get.interested_purchase_fee_type') }}</li>
                                                                @if (data_get($bid, 'get.interested_purchase_fee_type') === 'Yes' && $purchaseFeeDisplay !== '—')
                                                                <li class="mb-1"><span class="fw-semibold">Purchase Fee:</span> {{ $purchaseFeeDisplay }}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- C) Lease-Option Details --}}
                                                        @if (data_get($bid, 'get.interested_lease_option_agreement'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Details</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1"><span class="fw-semibold">Interested in a Lease-Option Agreement:</span> {{ data_get($bid, 'get.interested_lease_option_agreement') }}</li>
                                                                @if (data_get($bid, 'get.interested_lease_option_agreement') === 'Yes')
                                                                    @if ($leaseOptionCreatedDisplay !== '—')
                                                                    <li class="mb-1"><span class="fw-semibold">Compensation (When Option Is Created):</span> {{ $leaseOptionCreatedDisplay }}</li>
                                                                    @endif
                                                                    @if ($leaseOptionExercisedDisplay !== '—')
                                                                    <li class="mb-1"><span class="fw-semibold">Compensation (If Purchase Option Exercised):</span> {{ $leaseOptionExercisedDisplay }}</li>
                                                                    @endif
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- D) Legal Terms --}}
                                                        @if (data_get($bid, 'get.protection_period') || data_get($bid, 'get.early_termination_fee_option') || data_get($bid, 'get.retainer_fee_option') || data_get($bid, 'get.agency_agreement_timeframe'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Legal Terms</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (data_get($bid, 'get.protection_period'))
                                                                <li class="mb-1"><span class="fw-semibold">Protection Period Timeframe:</span> {{ data_get($bid, 'get.protection_period') }} days</li>
                                                                @endif
                                                                @if (data_get($bid, 'get.early_termination_fee_option'))
                                                                <li class="mb-1"><span class="fw-semibold">Early Termination Fee:</span> {{ data_get($bid, 'get.early_termination_fee_option') }}</li>
                                                                    @if ($terminationFeeDisplay !== '—')
                                                                    <li class="mb-1"><span class="fw-semibold">Termination Fee Amount:</span> {{ $terminationFeeDisplay }}</li>
                                                                    @endif
                                                                @endif
                                                                @if (data_get($bid, 'get.retainer_fee_option'))
                                                                <li class="mb-1"><span class="fw-semibold">Retainer Fee:</span> {{ data_get($bid, 'get.retainer_fee_option') }}</li>
                                                                    @if (data_get($bid, 'get.retainer_fee_option') === 'Yes')
                                                                        @if (data_get($bid, 'get.retainer_fee_amount'))
                                                                        <li class="mb-1"><span class="fw-semibold">Retainer Fee Amount:</span> ${{ number_format((float)data_get($bid, 'get.retainer_fee_amount'), 2) }}</li>
                                                                        @endif
                                                                        @if (data_get($bid, 'get.retainer_fee_application'))
                                                                        <li class="mb-1"><span class="fw-semibold">Retainer Fee Application:</span> 
                                                                            @if (data_get($bid, 'get.retainer_fee_application') === 'applied')
                                                                            Applied toward final compensation
                                                                            @else
                                                                            Charged in addition to final compensation
                                                                            @endif
                                                                        </li>
                                                                        @endif
                                                                    @endif
                                                                @endif
                                                                @if (data_get($bid, 'get.agency_agreement_timeframe'))
                                                                @php
                                                                    $limitedAgencyTimeframe = data_get($bid, 'get.agency_agreement_timeframe');
                                                                    $limitedAgencyTimeframeCustom = data_get($bid, 'get.agency_agreement_custom');
                                                                    $limitedIsOtherTimeframe = is_string($limitedAgencyTimeframe) && strtolower(trim($limitedAgencyTimeframe)) === 'other';
                                                                    $limitedAgencyTimeframeDisplay = $limitedIsOtherTimeframe ? ($limitedAgencyTimeframeCustom ?: 'Other') : ($limitedAgencyTimeframe ?: '');
                                                                @endphp
                                                                <li class="mb-1"><span class="fw-semibold">Tenant Agency Agreement Timeframe:</span> {{ $limitedAgencyTimeframeDisplay }}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        {{-- E) Brokerage Relationship --}}
                                                        @if (data_get($bid, 'get.brokerage_relationship'))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Brokerage Relationship</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                <li class="mb-1"><span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ data_get($bid, 'get.brokerage_relationship') }}</li>
                                                            </ul>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif

                                                    {{-- Section 2: Offered Services --}}
                                                    @php
                                                    // Define service categories for limited modal
                                                    $limitedPropType = @$auction->get->property_type ?? 'Residential Property';
                                                    $limitedIsCommercial = $limitedPropType === 'Commercial Property';

                                                    $limitedResidentialCategories = [
                                                        "📢 Tenant Criteria Marketing & Promotion" => [
                                                            "Create a branded flyer summarizing the Tenant's rental criteria",
                                                            "Post the Tenant's rental criteria on Craigslist under the \"Real Estate Wanted\" section",
                                                            "Share the Tenant's rental criteria on Nextdoor in Neighborhood or Community Groups",
                                                            "Promote the Tenant's rental criteria on Facebook in Rental or Housing Groups",
                                                            "Share the Tenant's rental criteria on Instagram using posts, stories, or reels",
                                                            "Promote the Tenant's rental criteria on LinkedIn in Real Estate or Housing Groups",
                                                            "Upload a TikTok video summarizing the Tenant's rental criteria",
                                                            "Upload a YouTube video summarizing the Tenant's rental criteria",
                                                            "Launch a mass email campaign promoting the Tenant's rental criteria",
                                                            "Distribute branded postcards or flyers in the Tenant's preferred neighborhoods",
                                                            "Launch hyperlocal digital ads targeting the Tenant's preferred rental areas",
                                                        ],
                                                        "🔍 Property Search, Alerts & Matching" => [
                                                            "Send email alerts with new listings from the MLS that match the Tenant's rental criteria",
                                                            "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria",
                                                            "Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
                                                            "Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit",
                                                        ],
                                                        "🏡 Property Showings & Virtual Tours" => [
                                                            "Schedule and attend property showings with the Tenant",
                                                            "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                            "Preview properties on behalf of the Tenant upon request",
                                                            "Provide factual observations on property layout and condition",
                                                        ],
                                                        "📝 Tenant Application Support" => [
                                                            "Provide the Tenant with application instructions or links to an online rental application platform",
                                                            "Gather and organize required supporting documents (e.g., identification, income verification, reference letters)",
                                                            "Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager for review",
                                                            "Answer questions about the application process, screening timelines, and required documentation",
                                                        ],
                                                        "📃 Lease Preparation & Execution" => [
                                                            "Review lease offers and assist the Tenant in preparing questions or requested changes",
                                                            "Coordinate lease negotiation with the Landlord's Agent, Landlord, or Property Manager",
                                                            "Assist with completing required lease disclosures and reviewing key lease terms",
                                                            "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                                                        ],
                                                        "🚚 Move-In Support & Coordination" => [
                                                            "Coordinate move-in date and key handoff logistics with the Landlord's Agent, Landlord or Property Manager",
                                                            "Confirm completion of any agreed-upon pre-move-in cleaning or repairs",
                                                            "Provide a utility setup checklist and local provider resources",
                                                            "Share a move-in checklist for documentation and property condition review",
                                                            "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods",
                                                        ],
                                                        "💡 Leasing Strategy & Guidance" => [
                                                            "Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions",
                                                            "Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)",
                                                            "Provide general guidance on Tenant rights and Landlord responsibilities under state law",
                                                            "Provide general guidance on lease clauses, payment terms, and renewal options",
                                                        ],
                                                    ];

                                                    $limitedCommercialCategories = [
                                                        "📢 Tenant Criteria Marketing & Promotion" => [
                                                            "Create a branded flyer summarizing the Tenant's leasing criteria",
                                                            "Post the Tenant's leasing criteria on Craigslist under the \"Office/Commercial\" or \"Retail\" section",
                                                            "Promote the Tenant's leasing criteria on Facebook in Commercial Leasing or Business Groups",
                                                            "Share the Tenant's leasing criteria on Instagram using posts, stories, or reels",
                                                            "Promote the Tenant's leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                                                            "Upload a TikTok video summarizing the Tenant's leasing criteria",
                                                            "Upload a YouTube video summarizing the Tenant's leasing criteria",
                                                            "Launch a mass email campaign promoting the Tenant's leasing criteria",
                                                            "Distribute branded postcards or flyers in the Tenant's preferred neighborhoods",
                                                            "Launch hyperlocal digital ads targeting the Tenant's preferred leasing areas",
                                                        ],
                                                        "🔍 Property Search, Alerts & Matching" => [
                                                            "Send listing alerts from commercial platforms (e.g., LoopNet, Crexi, CoStar, or local MLS) that match the Tenant's leasing criteria",
                                                            "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria",
                                                            "Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
                                                            "Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment",
                                                        ],
                                                        "🏢 Property Showings & Virtual Tours" => [
                                                            "Schedule and attend property tours with the Tenant",
                                                            "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                            "Preview properties on behalf of the Tenant upon request",
                                                            "Provide factual notes on layout, access, parking, visibility, and other operational considerations",
                                                        ],
                                                        "📝 Tenant Application Support" => [
                                                            "Provide the Tenant with application instructions or links to online platforms",
                                                            "Gather and organize required supporting documents (e.g., business licenses, financials, references)",
                                                            "Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager",
                                                        ],
                                                        "📃 Lease Preparation, LOI & Execution" => [
                                                            "Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant's business needs and proposed terms",
                                                            "Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)",
                                                            "Coordinate with the Landlord's Agent, Landlord or Property Manager to finalize lease terms",
                                                            "Review lease drafts and coordinate revisions through appropriate channels",
                                                            "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                                                            "Track required deposits, rent commencement, and key lease dates to ensure move-in readiness",
                                                        ],
                                                        "🚚 Move-In Support & Coordination" => [
                                                            "Coordinate move-in date and key handoff logistics with the Landlord, Landlord's Agent, or Property Manager",
                                                            "Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout",
                                                            "Provide a utility setup checklist and local provider resources",
                                                            "Share a move-in checklist for documentation and property condition review",
                                                            "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods",
                                                        ],
                                                        "💡 Leasing Strategy & Guidance" => [
                                                            "Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends",
                                                            "Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences",
                                                            "Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law",
                                                            "Provide general guidance on lease clauses, escalation terms, and space usage considerations",
                                                        ],
                                                    ];

                                                    $limitedCategories = $limitedIsCommercial ? $limitedCommercialCategories : $limitedResidentialCategories;
                                                    
                                                    // Flatten function
                                                    $flattenLtd = function($data) use (&$flattenLtd) {
                                                        $result = [];
                                                        if (is_array($data) || is_object($data)) {
                                                            foreach ((array)$data as $value) {
                                                                if (is_string($value) && !empty(trim($value)) && $value !== 'Other') {
                                                                    $result[] = trim($value);
                                                                } elseif (is_array($value) || is_object($value)) {
                                                                    $result = array_merge($result, $flattenLtd($value));
                                                                }
                                                            }
                                                        } elseif (is_string($data) && !empty(trim($data)) && $data !== 'Other') {
                                                            $result[] = trim($data);
                                                        }
                                                        return $result;
                                                    };
                                                    
                                                    // Parse services
                                                    $rawLtdServices = data_get($bid, 'get.services', []);
                                                    if (is_string($rawLtdServices) && !empty($rawLtdServices)) {
                                                        $decoded = json_decode($rawLtdServices, true);
                                                        $parsedLtdServices = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                                                    } elseif (is_array($rawLtdServices) || is_object($rawLtdServices)) {
                                                        $parsedLtdServices = $rawLtdServices;
                                                    } else {
                                                        $parsedLtdServices = [];
                                                    }
                                                    $ltdAllServices = array_unique($flattenLtd($parsedLtdServices));
                                                    
                                                    // Parse other_services
                                                    $rawLtdOther = data_get($bid, 'get.other_services', []);
                                                    if (is_string($rawLtdOther) && !empty($rawLtdOther)) {
                                                        $decoded = json_decode($rawLtdOther, true);
                                                        $ltdOtherServices = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                                                    } elseif (is_array($rawLtdOther) || is_object($rawLtdOther)) {
                                                        $ltdOtherServices = (array)$rawLtdOther;
                                                    } else {
                                                        $ltdOtherServices = [];
                                                    }
                                                    $ltdOtherServices = array_filter($ltdOtherServices, fn($s) => is_string($s) && !empty(trim($s)));
                                                    $ltdOtherServices = array_values($ltdOtherServices);
                                                    
                                                    $hasLtdServices = !empty($ltdAllServices) || !empty($ltdOtherServices);
                                                    @endphp
                                                    
                                                    <div class="mb-4">
                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa fa-clipboard-list me-2"></i>Offered Services
                                                        </h6>
                                                        
                                                        @if ($hasLtdServices)
                                                            @foreach ($limitedCategories as $catName => $catServices)
                                                                @php
                                                                    $matchedSvcs = array_filter($ltdAllServices, function($svc) use ($catServices) {
                                                                        return in_array($svc, $catServices);
                                                                    });
                                                                @endphp
                                                                @if (!empty($matchedSvcs))
                                                                <div class="mb-3">
                                                                    <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $catName }}</div>
                                                                    <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem;">
                                                                        @foreach ($matchedSvcs as $svc)
                                                                            <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $svc }}</li>
                                                                        @endforeach
                                                                    </ul>
                                                                </div>
                                                                @endif
                                                            @endforeach

                                                            @if (!empty($ltdOtherServices))
                                                            <div class="mb-3">
                                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                                <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem;">
                                                                    @foreach ($ltdOtherServices as $otherSvc)
                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $otherSvc }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                            @endif
                                                        @else
                                                        <div class="text-muted" style="font-style: italic;">No services selected for this bid.</div>
                                                        @endif
                                                    </div>

                                                </div>
                                                <div class="modal-footer" style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px;">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background: #6c757d; border: none; border-radius: 6px; padding: 8px 20px;">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    {{-- End of Limited Modal for Bidding Period --}}

                                    <!-- Counter Bids -->

                                    @php
                                    // Load counter bids from both sources:
                                    // 1. TenantCounterBidding (legacy/agent counters)
                                    $legacyCounterBids = \App\Models\TenantCounterBidding::with('meta', 'user')
                                        ->where('tenant_agent_auction_bid_id', data_get($bid, 'id'))
                                        ->get();
                                    
                                    // 2. TenantCounterTerm (tenant counter offers) - bid ID stored in tenant_agent_auction_id field
                                    $tenantCounterTerms = \App\Models\TenantCounterTerm::with('meta', 'user')
                                        ->where('tenant_agent_auction_id', data_get($bid, 'id'))
                                        ->get();
                                    
                                    // Merge and sort by created_at descending
                                    $counterBids = $legacyCounterBids->concat($tenantCounterTerms)
                                        ->sortByDesc('created_at')
                                        ->values();
                                    @endphp

                                    @php
                                    $rawState = data_get($bid, 'accepted', '0');
                                    $state = in_array($rawState, [null, 0, '0'], true)
                                    ? '0'
                                    : (string) $rawState;
                                    $isOwnerRow = data_get($auction, 'user_id') == $auth_id;

                                    $ownerFirst = data_get($auction, 'user.first_name', '');
                                    $ownerLast = data_get($auction, 'user.last_name', '');
                                    $agentFirst = data_get($bid, 'user.first_name', '');
                                    $agentLast = data_get($bid, 'user.last_name', '');

                                    $ownerId = data_get($auction, 'user_id');

                                    // Add access control for counter bids
                                    $isListingOwner = data_get($auction, 'user_id') == $auth_id;
                                    $isBidOwner = data_get($bid, 'user_id') == $auth_id;
                                    $showCounterBids = $isListingOwner || $isBidOwner;
                                    @endphp

                                    {{-- Counter Bidding Section - Only visible to listing owner and bidding agent --}}
                                    @if ($showCounterBids && $counterBids->count() > 0)
                                    <div class="counter-bids-section mt-4" id="counter-section-{{ $bid->id }}">
                                        <!-- Counter Bids Accordion Header -->
                                        <div class="counter-bids-toggle" 
                                            style="cursor: pointer;"
                                            onclick="event.stopPropagation(); var target = document.getElementById('counterBids{{ data_get($bid, 'id') }}'); var arrow = this.querySelector('.counter-arrow'); if(target.style.display === 'none' || target.style.display === '') { target.style.display = 'block'; arrow.style.transform = 'rotate(180deg)'; } else { target.style.display = 'none'; arrow.style.transform = 'rotate(0deg)'; }">
                                            <div
                                                class="d-flex justify-content-between align-items-center flex-wrap p-2 border rounded">
                                                <h5 class="mb-0" style="color: #2c3e50;">Counter
                                                    Bidding History</h5>
                                                <div class="d-flex align-items-center">
                                                    <span
                                                        class="badge bg-secondary me-2">{{ $counterBids->count() }}
                                                        counter offers</span>
                                                    <span class="counter-arrow" style="transition: transform 0.3s;">↓</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Counter Bids Accordion Content -->
                                        <div id="counterBids{{ data_get($bid, 'id') }}"
                                            class="counter-bids-content"
                                            style="display: none;"
                                            aria-labelledby="counterBidsHeading{{ data_get($bid, 'id') }}">
                                            <div
                                                class="accordion-body p-3 border border-top-0 rounded-bottom counter-font">
                                                @foreach ($counterBids as $counterBid)
                                                @php
                                                // Roles - use Auth::id() directly to ensure correct scope
                                                $currentUserId = Auth::id();
                                                $isOwner = data_get($auction, 'user_id') == $currentUserId;
                                                $isAgent = data_get($bid, 'user_id') == $currentUserId;
                                                $isCounterFromOwner = $counterBid->user_id == data_get($auction, 'user_id');
                                                $isCounterFromAgent = $counterBid->user_id == data_get($bid, 'user_id');

                                                // States
                                                $rawBidState = data_get(
                                                $bid,
                                                'accepted',
                                                '0',
                                                );
                                                $bidState = in_array(
                                                $rawBidState,
                                                [null, 0, '0', 'no', 'pending'],
                                                true,
                                                )
                                                ? '0'
                                                : (string) $rawBidState;

                                                // Check status field (tenant_counter_terms uses 'status' column)
                                                $rawCounterState = data_get(
                                                $counterBid,
                                                'status',
                                                data_get($counterBid, 'accepted', '0'),
                                                );
                                                $counterState = in_array(
                                                $rawCounterState,
                                                [null, 0, '0', 'no', 'pending'],
                                                true,
                                                )
                                                ? '0'
                                                : (string) $rawCounterState;

                                                // Actions visibility (other party, both pending)
                                                $showCounterActions = false;
                                                if (
                                                $bidState === '0' &&
                                                $counterState === '0'
                                                ) {
                                                if ($isOwner && $isCounterFromAgent) {
                                                $showCounterActions = true;
                                                }
                                                if ($isAgent && $isCounterFromOwner) {
                                                $showCounterActions = true;
                                                }
                                                }

                                                // Names
                                                $ownerFirst = data_get(
                                                $auction,
                                                'user.first_name',
                                                '',
                                                );
                                                $ownerLast = data_get(
                                                $auction,
                                                'user.last_name',
                                                '',
                                                );
                                                $agentFirst = data_get(
                                                $bid,
                                                'user.first_name',
                                                '',
                                                );
                                                $agentLast = data_get(
                                                $bid,
                                                'user.last_name',
                                                '',
                                                );

                                                // For counter accepted/rejected: actor is ALWAYS the other party (not the creator)
                                                $actorUserId = $isCounterFromOwner
                                                ? data_get($bid, 'user_id')
                                                : data_get($auction, 'user_id');
                                                $actorFirst = $isCounterFromOwner
                                                ? $agentFirst
                                                : $ownerFirst;
                                                $actorLast = $isCounterFromOwner
                                                ? $agentLast
                                                : $ownerLast;

                                                // Creator names (for "pending" other-party view)
                                                $creatorFirst = data_get(
                                                $counterBid,
                                                'user.first_name',
                                                '',
                                                );
                                                $creatorLast = data_get(
                                                $counterBid,
                                                'user.last_name',
                                                '',
                                                );
                                                @endphp

                                                <div
                                                    class="counter-bid-card mb-3 p-3 border rounded mt-2">
                                                    <div
                                                        class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                                        <h6 class="mb-0">
                                                            @if ($counterBid->user_id == Auth::id())
                                                            Your Counter Offer
                                                            @else
                                                            Counter Offer from
                                                            {{ data_get($counterBid, 'user.first_name') }}
                                                            {{ data_get($counterBid, 'user.last_name') }}
                                                            @endif
                                                        </h6>
                                                        <small
                                                            class="text-muted">{{ optional($counterBid->created_at)->format('M j, Y g:i A') }}</small>
                                                    </div>

                                                    @php 
                                                    $allMeta = $counterBid->getAllMeta();
                                                    
                                                    // === COMPARISON HELPER: Check if counter value differs from original ===
                                                    // Access original bid data via the 'get' accessor (object from meta table)
                                                    $isChanged = function($counterVal, $origKey) use ($bid) {
                                                        $origVal = data_get($bid, 'get.' . $origKey, null);
                                                        // Normalize both values for comparison
                                                        $normalizeVal = function($v) {
                                                            if (is_null($v) || $v === '') return '';
                                                            if (is_array($v) || is_object($v)) {
                                                                return json_encode($v);
                                                            }
                                                            $v = trim((string) $v);
                                                            // Strip currency symbols and whitespace for numeric comparison
                                                            return preg_replace('/[\s$,%]/', '', strtolower($v));
                                                        };
                                                        return $normalizeVal($counterVal) !== $normalizeVal($origVal);
                                                    };
                                                    
                                                    // CSS classes for changed fields
                                                    $changedStyle = 'background-color: #fff3cd; padding: 2px 6px; border-radius: 4px; border-left: 3px solid #ffc107;';
                                                    $changedBadge = '<span class="badge bg-warning text-dark ms-2" style="font-size: 0.7rem; vertical-align: middle;">Changed</span>';
                                                    
                                                    // Helper functions for counter bid display formatting
                                                    $counterFmtMoney = function($val) {
                                                        if (empty($val)) return '';
                                                        // Strip non-numeric chars (except . and -) before casting
                                                        $cleaned = preg_replace('/[^0-9.\-]/', '', $val);
                                                        return '$' . number_format((float) $cleaned, 2);
                                                    };
                                                    $counterFmtPercent = function($val) {
                                                        if (empty($val)) return '';
                                                        // Strip non-numeric chars (except . and -) before casting
                                                        $cleaned = preg_replace('/[^0-9.\-]/', '', $val);
                                                        return rtrim(rtrim(number_format((float) $cleaned, 2), '0'), '.') . '%';
                                                    };
                                                    
                                                    // Build combined commission fee display
                                                    $counterLeaseFeeType = $allMeta['lease_fee_type'] ?? '';
                                                    $counterCommissionFeeDisplay = '—';
                                                    if ($counterLeaseFeeType === 'Flat Fee' && !empty($allMeta['lease_fee_flat'])) {
                                                        $counterCommissionFeeDisplay = $counterFmtMoney($allMeta['lease_fee_flat']);
                                                    } elseif ($counterLeaseFeeType === 'Percentage of the Gross Lease Value' && !empty($allMeta['lease_fee_percentage'])) {
                                                        $counterCommissionFeeDisplay = $counterFmtPercent($allMeta['lease_fee_percentage']) . ' of Gross Lease Value';
                                                    } elseif ($counterLeaseFeeType === 'Percentage of Monthly Rent' && !empty($allMeta['lease_fee_percentage_monthly_rent'])) {
                                                        $display = $counterFmtPercent($allMeta['lease_fee_percentage_monthly_rent']) . ' of Monthly Rent';
                                                        if (!empty($allMeta['lease_fee_percentage_monthly_number'])) {
                                                            $display .= ' x ' . $allMeta['lease_fee_percentage_monthly_number'] . ' Months';
                                                        }
                                                        $counterCommissionFeeDisplay = $display;
                                                    } elseif ($counterLeaseFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                                                        $parts = [];
                                                        if (!empty($allMeta['lease_fee_flat_combo'])) {
                                                            $parts[] = $counterFmtMoney($allMeta['lease_fee_flat_combo']);
                                                        }
                                                        if (!empty($allMeta['lease_fee_percentage_combo'])) {
                                                            $parts[] = $counterFmtPercent($allMeta['lease_fee_percentage_combo']) . ' of Gross Lease Value';
                                                        }
                                                        $counterCommissionFeeDisplay = implode(' + ', $parts) ?: '—';
                                                    } elseif ($counterLeaseFeeType === 'Percentage of the Net Aggregate Rent' && !empty($allMeta['lease_fee_percentage_net'])) {
                                                        $counterCommissionFeeDisplay = $counterFmtPercent($allMeta['lease_fee_percentage_net']) . ' of Net Aggregate Rent';
                                                    } elseif ($counterLeaseFeeType === 'Flat Fee + Percentage of the Net Aggregate Rent') {
                                                        $parts = [];
                                                        if (!empty($allMeta['lease_fee_flat_combo_net'])) {
                                                            $parts[] = $counterFmtMoney($allMeta['lease_fee_flat_combo_net']);
                                                        }
                                                        if (!empty($allMeta['lease_fee_percentage_combo_net'])) {
                                                            $parts[] = $counterFmtPercent($allMeta['lease_fee_percentage_combo_net']) . ' of Net Aggregate Rent';
                                                        }
                                                        $counterCommissionFeeDisplay = implode(' + ', $parts) ?: '—';
                                                    } elseif ($counterLeaseFeeType === 'other' && !empty($allMeta['lease_fee_other'])) {
                                                        $counterCommissionFeeDisplay = $allMeta['lease_fee_other'];
                                                    }
                                                    
                                                    // Build combined purchase fee display
                                                    $counterPurchaseFeeType = $allMeta['purchase_fee_type'] ?? '';
                                                    $counterPurchaseFeeDisplay = '—';
                                                    if ($counterPurchaseFeeType === 'Flat Fee' && !empty($allMeta['purchase_fee_flat'])) {
                                                        $counterPurchaseFeeDisplay = $counterFmtMoney($allMeta['purchase_fee_flat']);
                                                    } elseif ($counterPurchaseFeeType === 'Percentage of the Total Purchase Price' && !empty($allMeta['purchase_fee_percentage'])) {
                                                        $counterPurchaseFeeDisplay = $counterFmtPercent($allMeta['purchase_fee_percentage']) . ' of Total Purchase Price';
                                                    } elseif ($counterPurchaseFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                                                        $parts = [];
                                                        if (!empty($allMeta['purchase_fee_flat_combo'])) {
                                                            $parts[] = $counterFmtMoney($allMeta['purchase_fee_flat_combo']);
                                                        }
                                                        if (!empty($allMeta['purchase_fee_percentage_combo'])) {
                                                            $parts[] = $counterFmtPercent($allMeta['purchase_fee_percentage_combo']) . ' of Total Purchase Price';
                                                        }
                                                        $counterPurchaseFeeDisplay = implode(' + ', $parts) ?: '—';
                                                    } elseif ($counterPurchaseFeeType === 'other' && !empty($allMeta['purchase_fee_other'])) {
                                                        $counterPurchaseFeeDisplay = $allMeta['purchase_fee_other'];
                                                    }
                                                    
                                                    // Build lease-option displays
                                                    $counterLeaseOptionCreatedDisplay = '—';
                                                    $counterLeaseOptionExercisedDisplay = '—';
                                                    if (!empty($allMeta['interested_lease_option_agreement']) && $allMeta['interested_lease_option_agreement'] === 'Yes') {
                                                        if (!empty($allMeta['lease_value'])) {
                                                            $counterLeaseOptionCreatedDisplay = (!empty($allMeta['lease_type']) && $allMeta['lease_type'] === 'percent') 
                                                                ? $counterFmtPercent($allMeta['lease_value']) 
                                                                : $counterFmtMoney($allMeta['lease_value']);
                                                        }
                                                        if (!empty($allMeta['purchase_value'])) {
                                                            $counterLeaseOptionExercisedDisplay = (!empty($allMeta['purchase_type']) && $allMeta['purchase_type'] === 'percent')
                                                                ? $counterFmtPercent($allMeta['purchase_value'])
                                                                : $counterFmtMoney($allMeta['purchase_value']);
                                                        }
                                                    }
                                                    
                                                    // Build termination fee display
                                                    $counterTerminationFeeDisplay = '—';
                                                    if (!empty($allMeta['early_termination_fee_option']) && $allMeta['early_termination_fee_option'] === 'Yes' && !empty($allMeta['early_termination_fee_amount'])) {
                                                        $counterTerminationFeeDisplay = $counterFmtMoney($allMeta['early_termination_fee_amount']);
                                                    }
                                                    @endphp
                                                    
                                                    @if (
                                                    !empty($allMeta['commission_structure']) ||
                                                    !empty($allMeta['lease_fee_type']) ||
                                                    !empty($allMeta['interested_purchase_fee_type']) ||
                                                    !empty($allMeta['interested_lease_option_agreement']) ||
                                                    !empty($allMeta['protection_period']) ||
                                                    !empty($allMeta['early_termination_fee_option']) ||
                                                    !empty($allMeta['retainer_fee_option']) ||
                                                    !empty($allMeta['agency_agreement_timeframe']) ||
                                                    !empty($allMeta['brokerage_relationship']) ||
                                                    !empty($allMeta['additional_details_broker']) ||
                                                    !empty($allMeta['additional_details']))
                                                    <div class="mb-5">
                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms
                                                        </h6>

                                                        <!-- A) Tenant's Broker Compensation -->
                                                        @if (!empty($allMeta['commission_structure']) || !empty($allMeta['lease_fee_type']) || !empty($allMeta['payment_timing']) || !empty($allMeta['days_to_pay']))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">A) Tenant's Broker Compensation</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (!empty($allMeta['commission_structure']))
                                                                @php $structChanged = $isChanged($allMeta['commission_structure'], 'commission_structure'); @endphp
                                                                <li class="mb-1" style="{{ $structChanged ? $changedStyle : '' }}"><span class="fw-semibold">Tenant's Broker Commission Structure:</span> {{ $allMeta['commission_structure'] }}{!! $structChanged ? $changedBadge : '' !!}</li>
                                                                @endif
                                                                @if (!empty($allMeta['lease_fee_type']))
                                                                @php $feeChanged = $isChanged($allMeta['lease_fee_type'], 'lease_fee_type'); @endphp
                                                                <li class="mb-1" style="{{ $feeChanged ? $changedStyle : '' }}"><span class="fw-semibold">Tenant's Broker Commission Fee:</span> {{ $counterCommissionFeeDisplay }}{!! $feeChanged ? $changedBadge : '' !!}</li>
                                                                @endif
                                                                @if (!empty($allMeta['payment_timing']))
                                                                @php $timingChanged = $isChanged($allMeta['payment_timing'], 'payment_timing'); @endphp
                                                                <li class="mb-1" style="{{ $timingChanged ? $changedStyle : '' }}"><span class="fw-semibold">Payment Timing for Broker Fees:</span> {{ $allMeta['payment_timing'] }}{!! $timingChanged ? $changedBadge : '' !!}</li>
                                                                @endif
                                                                @if (!empty($allMeta['days_to_pay']))
                                                                @php $daysChanged = $isChanged($allMeta['days_to_pay'], 'days_to_pay'); @endphp
                                                                <li class="mb-1" style="{{ $daysChanged ? $changedStyle : '' }}"><span class="fw-semibold">Calendar Days To Pay:</span> {{ $allMeta['days_to_pay'] }}{!! $daysChanged ? $changedBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- B) Purchase Fee Details -->
                                                        @if (!empty($allMeta['interested_purchase_fee_type']))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">B) Purchase Fee Details</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @php $purchaseIntChanged = $isChanged($allMeta['interested_purchase_fee_type'], 'interested_purchase_fee_type'); @endphp
                                                                <li class="mb-1" style="{{ $purchaseIntChanged ? $changedStyle : '' }}"><span class="fw-semibold">Interested in Purchasing a Property:</span> {{ $allMeta['interested_purchase_fee_type'] }}{!! $purchaseIntChanged ? $changedBadge : '' !!}</li>
                                                                @if ($allMeta['interested_purchase_fee_type'] === 'Yes' && $counterPurchaseFeeDisplay !== '—')
                                                                @php $purchaseFeeChanged = $isChanged($allMeta['purchase_fee_type'] ?? '', 'purchase_fee_type'); @endphp
                                                                <li class="mb-1" style="{{ $purchaseFeeChanged ? $changedStyle : '' }}"><span class="fw-semibold">Purchase Fee:</span> {{ $counterPurchaseFeeDisplay }}{!! $purchaseFeeChanged ? $changedBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- C) Lease-Option Details -->
                                                        @if (!empty($allMeta['interested_lease_option_agreement']))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">C) Lease-Option Details</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @php $leaseOptIntChanged = $isChanged($allMeta['interested_lease_option_agreement'], 'interested_lease_option_agreement'); @endphp
                                                                <li class="mb-1" style="{{ $leaseOptIntChanged ? $changedStyle : '' }}"><span class="fw-semibold">Interested in a Lease-Option Agreement:</span> {{ $allMeta['interested_lease_option_agreement'] }}{!! $leaseOptIntChanged ? $changedBadge : '' !!}</li>
                                                                @if ($allMeta['interested_lease_option_agreement'] === 'Yes')
                                                                    @if ($counterLeaseOptionCreatedDisplay !== '—')
                                                                    @php $leaseCreatedChanged = $isChanged($allMeta['lease_value'] ?? '', 'lease_value'); @endphp
                                                                    <li class="mb-1" style="{{ $leaseCreatedChanged ? $changedStyle : '' }}"><span class="fw-semibold">Compensation (When Option Is Created):</span> {{ $counterLeaseOptionCreatedDisplay }}{!! $leaseCreatedChanged ? $changedBadge : '' !!}</li>
                                                                    @endif
                                                                    @if ($counterLeaseOptionExercisedDisplay !== '—')
                                                                    @php $leaseExercisedChanged = $isChanged($allMeta['purchase_value'] ?? '', 'purchase_value'); @endphp
                                                                    <li class="mb-1" style="{{ $leaseExercisedChanged ? $changedStyle : '' }}"><span class="fw-semibold">Compensation (If Purchase Option Exercised):</span> {{ $counterLeaseOptionExercisedDisplay }}{!! $leaseExercisedChanged ? $changedBadge : '' !!}</li>
                                                                    @endif
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- D) Legal Terms -->
                                                        @if (!empty($allMeta['protection_period']) || !empty($allMeta['early_termination_fee_option']) || !empty($allMeta['retainer_fee_option']) || !empty($allMeta['agency_agreement_timeframe']))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">D) Legal Terms</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (!empty($allMeta['protection_period']))
                                                                @php $protectionChanged = $isChanged($allMeta['protection_period'], 'protection_period'); @endphp
                                                                <li class="mb-1" style="{{ $protectionChanged ? $changedStyle : '' }}"><span class="fw-semibold">Protection Period Timeframe:</span> {{ $allMeta['protection_period'] }} days{!! $protectionChanged ? $changedBadge : '' !!}</li>
                                                                @endif
                                                                @if (!empty($allMeta['early_termination_fee_option']))
                                                                @php $termOptChanged = $isChanged($allMeta['early_termination_fee_option'], 'early_termination_fee_option'); @endphp
                                                                <li class="mb-1" style="{{ $termOptChanged ? $changedStyle : '' }}"><span class="fw-semibold">Early Termination Fee:</span> {{ $allMeta['early_termination_fee_option'] }}{!! $termOptChanged ? $changedBadge : '' !!}</li>
                                                                    @if ($counterTerminationFeeDisplay !== '—')
                                                                    @php $termAmtChanged = $isChanged($allMeta['early_termination_fee_amount'] ?? '', 'early_termination_fee_amount'); @endphp
                                                                    <li class="mb-1" style="{{ $termAmtChanged ? $changedStyle : '' }}"><span class="fw-semibold">Termination Fee Amount:</span> {{ $counterTerminationFeeDisplay }}{!! $termAmtChanged ? $changedBadge : '' !!}</li>
                                                                    @endif
                                                                @endif
                                                                @if (!empty($allMeta['retainer_fee_option']))
                                                                @php $retainerOptChanged = $isChanged($allMeta['retainer_fee_option'], 'retainer_fee_option'); @endphp
                                                                <li class="mb-1" style="{{ $retainerOptChanged ? $changedStyle : '' }}"><span class="fw-semibold">Retainer Fee:</span> {{ $allMeta['retainer_fee_option'] }}{!! $retainerOptChanged ? $changedBadge : '' !!}</li>
                                                                    @if ($allMeta['retainer_fee_option'] === 'Yes')
                                                                        @if (!empty($allMeta['retainer_fee_amount']))
                                                                        @php $retainerAmtChanged = $isChanged($allMeta['retainer_fee_amount'], 'retainer_fee_amount'); @endphp
                                                                        <li class="mb-1" style="{{ $retainerAmtChanged ? $changedStyle : '' }}"><span class="fw-semibold">Retainer Fee Amount:</span> {{ $counterFmtMoney($allMeta['retainer_fee_amount']) }}{!! $retainerAmtChanged ? $changedBadge : '' !!}</li>
                                                                        @endif
                                                                        @if (!empty($allMeta['retainer_fee_application']))
                                                                        @php $retainerAppChanged = $isChanged($allMeta['retainer_fee_application'], 'retainer_fee_application'); @endphp
                                                                        <li class="mb-1" style="{{ $retainerAppChanged ? $changedStyle : '' }}"><span class="fw-semibold">Retainer Fee Application:</span> 
                                                                            @if ($allMeta['retainer_fee_application'] === 'applied')
                                                                            Applied toward final compensation
                                                                            @else
                                                                            Charged in addition to final compensation
                                                                            @endif
                                                                            {!! $retainerAppChanged ? $changedBadge : '' !!}
                                                                        </li>
                                                                        @endif
                                                                    @endif
                                                                @endif
                                                                @if (!empty($allMeta['agency_agreement_timeframe']))
                                                                @php
                                                                    $metaTimeframe = $allMeta['agency_agreement_timeframe'] ?? '';
                                                                    $metaTimeframeCustom = $allMeta['agency_agreement_custom'] ?? '';
                                                                    $isMetaOther = is_string($metaTimeframe) && strtolower(trim($metaTimeframe)) === 'other';
                                                                    $metaTimeframeDisplay = $isMetaOther ? ($metaTimeframeCustom ?: 'Other') : ($metaTimeframe ?: '');
                                                                    $timeframeChanged = $isChanged($allMeta['agency_agreement_timeframe'], 'agency_agreement_timeframe');
                                                                @endphp
                                                                <li class="mb-1" style="{{ $timeframeChanged ? $changedStyle : '' }}"><span class="fw-semibold">Tenant Agency Agreement Timeframe:</span> {{ $metaTimeframeDisplay }}{!! $timeframeChanged ? $changedBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- E) Brokerage Relationship -->
                                                        @if (!empty($allMeta['brokerage_relationship']))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">E) Brokerage Relationship</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @php $brokerageChanged = $isChanged($allMeta['brokerage_relationship'], 'brokerage_relationship'); @endphp
                                                                <li class="mb-1" style="{{ $brokerageChanged ? $changedStyle : '' }}"><span class="fw-semibold">Acceptable Brokerage Relationship:</span> {{ $allMeta['brokerage_relationship'] }}{!! $brokerageChanged ? $changedBadge : '' !!}</li>
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        <!-- F) Additional Terms / Additional Details -->
                                                        @if (!empty($allMeta['additional_details_broker']) || !empty($allMeta['additional_details']))
                                                        <div class="mb-4">
                                                            <h6 class="mb-2" style="color: #049399; font-weight: 600;">F) Additional Terms / Additional Details</h6>
                                                            <ul class="list-unstyled ps-3 mb-0">
                                                                @if (!empty($allMeta['additional_details_broker']))
                                                                @php $addTermsChanged = $isChanged($allMeta['additional_details_broker'], 'additional_details_broker'); @endphp
                                                                <li class="mb-1" style="{{ $addTermsChanged ? $changedStyle : '' }}"><span class="fw-semibold">Additional Terms:</span> {{ $allMeta['additional_details_broker'] }}{!! $addTermsChanged ? $changedBadge : '' !!}</li>
                                                                @endif
                                                                @if (!empty($allMeta['additional_details']))
                                                                @php $addDetailsChanged = $isChanged($allMeta['additional_details'], 'additional_details'); @endphp
                                                                <li class="mb-1" style="{{ $addDetailsChanged ? $changedStyle : '' }}"><span class="fw-semibold">Additional Details:</span> {{ $allMeta['additional_details'] }}{!! $addDetailsChanged ? $changedBadge : '' !!}</li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif

                                                    <!-- Services Offered -->
                                                    @php
                                                    $services = is_string($allMeta['services'] ?? '')
                                                        ? json_decode($allMeta['services'], true) ?? []
                                                        : (array) ($allMeta['services'] ?? []);
                                                    
                                                    // Normalize function to handle curly/straight apostrophe differences
                                                    // Using chr() to avoid encoding issues with curly quotes
                                                    $normalizeStr = function($s) {
                                                        // Replace Unicode curly quotes with ASCII equivalents
                                                        // U+2018 ('), U+2019 ('), U+201C ("), U+201D (")
                                                        $search = [
                                                            "\xE2\x80\x98", // U+2018 left single quotation mark
                                                            "\xE2\x80\x99", // U+2019 right single quotation mark
                                                            "\xE2\x80\x9C", // U+201C left double quotation mark
                                                            "\xE2\x80\x9D", // U+201D right double quotation mark
                                                        ];
                                                        $replace = ["'", "'", '"', '"'];
                                                        return str_replace($search, $replace, $s);
                                                    };
                                                    
                                                    // Create normalized lookup for selected services
                                                    $selectedNormalized = [];
                                                    foreach ($services as $svc) {
                                                        $selectedNormalized[$normalizeStr($svc)] = $svc;
                                                    }
                                                    
                                                    $bidPropertyType = $allMeta['property_type'] ?? @$auction->get->property_type ?? 'Residential Property';

                                                    $residentialServicesConfig = [
                                                        'Tenant Criteria Marketing & Promotion' => [
                                                            "Create a branded flyer summarizing the Tenant's rental criteria",
                                                            "Post the Tenant's rental criteria on Craigslist under the \"Real Estate Wanted\" section",
                                                            "Share the Tenant's rental criteria on Nextdoor in Neighborhood or Community Groups",
                                                            "Promote the Tenant's rental criteria on Facebook in Rental or Housing Groups",
                                                            "Share the Tenant's rental criteria on Instagram using posts, stories, or reels",
                                                            "Promote the Tenant's rental criteria on LinkedIn in Real Estate or Housing Groups",
                                                            "Upload a TikTok video summarizing the Tenant's rental criteria",
                                                            "Upload a YouTube video summarizing the Tenant's rental criteria",
                                                            "Launch a mass email campaign promoting the Tenant's rental criteria",
                                                            "Distribute branded postcards or flyers in the Tenant's preferred neighborhoods",
                                                            "Launch hyperlocal digital ads targeting the Tenant's preferred rental areas"
                                                        ],
                                                        'Property Search, Alerts & Matching' => [
                                                            "Send email alerts with new listings from the MLS that match the Tenant's rental criteria",
                                                            "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria",
                                                            "Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
                                                            "Evaluate properties with the Tenant and provide insights on pricing, lease terms, and overall fit"
                                                        ],
                                                        'Property Showings & Virtual Tours' => [
                                                            "Schedule and attend property showings with the Tenant",
                                                            "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                            "Preview properties on behalf of the Tenant upon request",
                                                            "Provide factual observations on property layout and condition"
                                                        ],
                                                        'Tenant Application Support' => [
                                                            "Provide the Tenant with application instructions or links to an online rental application platform",
                                                            "Gather and organize required supporting documents (e.g., identification, income verification, reference letters)",
                                                            "Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager for review",
                                                            "Answer questions about the application process, screening timelines, and required documentation"
                                                        ],
                                                        'Lease Preparation & Execution' => [
                                                            "Review lease offers and assist the Tenant in preparing questions or requested changes",
                                                            "Coordinate lease negotiation with the Landlord's Agent, Landlord, or Property Manager",
                                                            "Assist with completing required lease disclosures and reviewing key lease terms",
                                                            "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties"
                                                        ],
                                                        'Move-In Support & Coordination' => [
                                                            "Coordinate move-in date and key handoff logistics with the Landlord's Agent, Landlord or Property Manager",
                                                            "Confirm completion of any agreed-upon pre-move-in cleaning or repairs",
                                                            "Provide a utility setup checklist and local provider resources",
                                                            "Share a move-in checklist for documentation and property condition review",
                                                            "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods"
                                                        ],
                                                        'Leasing Strategy & Guidance' => [
                                                            "Provide a Rental Market Analysis (RMA) with pricing insights based on comparable rentals, neighborhood trends, and current market conditions",
                                                            "Advise on lease types and structures (e.g., month-to-month, annual, furnished, lease-option)",
                                                            "Provide general guidance on Tenant rights and Landlord responsibilities under state law",
                                                            "Provide general guidance on lease clauses, payment terms, and renewal options"
                                                        ]
                                                    ];

                                                    $commercialServicesConfig = [
                                                        'Tenant Criteria Marketing & Promotion' => [
                                                            "Create a branded flyer summarizing the Tenant's leasing criteria",
                                                            "Post the Tenant's leasing criteria on Craigslist under the \"Office/Commercial\" or \"Retail\" section",
                                                            "Promote the Tenant's leasing criteria on Facebook in Commercial Leasing or Business Groups",
                                                            "Share the Tenant's leasing criteria on Instagram using posts, stories, or reels",
                                                            "Promote the Tenant's leasing criteria on LinkedIn in Professional, Real Estate, or Commercial Investment Groups",
                                                            "Upload a TikTok video summarizing the Tenant's leasing criteria",
                                                            "Upload a YouTube video summarizing the Tenant's leasing criteria",
                                                            "Launch a mass email campaign promoting the Tenant's leasing criteria",
                                                            "Distribute branded postcards or flyers in the Tenant's preferred neighborhoods",
                                                            "Launch hyperlocal digital ads targeting the Tenant's preferred leasing areas"
                                                        ],
                                                        'Property Search, Alerts & Matching' => [
                                                            "Send listing alerts from commercial platforms (e.g., LoopNet, Crexi, CoStar, or local MLS) that match the Tenant's leasing criteria",
                                                            "Search for off-market, pre-market, withdrawn, canceled, or expired properties that meet the Tenant's rental criteria",
                                                            "Communicate with the Landlord's Agent, Landlord, or Property Manager to confirm availability, lease terms, and showing instructions",
                                                            "Evaluate properties for layout efficiency, building specs, logistics, zoning fit, and operational alignment"
                                                        ],
                                                        'Property Showings & Virtual Tours' => [
                                                            "Schedule and attend property tours with the Tenant",
                                                            "Coordinate or conduct virtual showings via live video or pre-recorded walkthroughs",
                                                            "Preview properties on behalf of the Tenant upon request",
                                                            "Provide factual notes on layout, access, parking, visibility, and other operational considerations"
                                                        ],
                                                        'Tenant Application Support' => [
                                                            "Provide the Tenant with application instructions or links to online platforms",
                                                            "Gather and organize required supporting documents (e.g., business licenses, financials, references)",
                                                            "Submit complete and organized application packages to the Landlord's Agent, Landlord, or Property Manager"
                                                        ],
                                                        'Lease Preparation, LOI & Execution' => [
                                                            "Draft or assist with preparing a Letter of Intent (LOI) summarizing the Tenant's business needs and proposed terms",
                                                            "Assist with negotiating rent, CAM, lease term, TI allowance, exclusivity clauses, renewal options, and other provisions (as permitted under the agency agreement)",
                                                            "Coordinate with the Landlord's Agent, Landlord or Property Manager to finalize lease terms",
                                                            "Review lease drafts and coordinate revisions through appropriate channels",
                                                            "Assist with in-person or electronic lease signing, including e-signature setup and secure delivery of executed lease documents, addenda, and disclosures to all parties",
                                                            "Track required deposits, rent commencement, and key lease dates to ensure move-in readiness"
                                                        ],
                                                        'Move-In Support & Coordination' => [
                                                            "Coordinate move-in date and key handoff logistics with the Landlord, Landlord's Agent, or Property Manager",
                                                            "Confirm completion of any agreed-upon pre-move-in repairs, cleaning, or buildout",
                                                            "Provide a utility setup checklist and local provider resources",
                                                            "Share a move-in checklist for documentation and property condition review",
                                                            "Confirm required move-in payments and assist the Tenant with tracking amounts due, deadlines, and accepted payment methods"
                                                        ],
                                                        'Leasing Strategy & Guidance' => [
                                                            "Provide a Comparative Lease Market Analysis (CLMA) with pricing insights, comps, and vacancy trends",
                                                            "Advise on lease types and structures (e.g., NNN, Modified Gross, Full Service) with general explanations of differences",
                                                            "Provide general guidance on Tenant rights and Landlord responsibilities under commercial leasing law",
                                                            "Provide general guidance on lease clauses, escalation terms, and space usage considerations"
                                                        ]
                                                    ];

                                                    $servicesConfig = ($bidPropertyType === 'Commercial Property') ? $commercialServicesConfig : $residentialServicesConfig;
                                                    
                                                    // Build normalized config flat map for unmapped detection
                                                    $configFlatNormalized = [];
                                                    foreach ($servicesConfig as $catServices) {
                                                        foreach ($catServices as $svc) {
                                                            $configFlatNormalized[$normalizeStr($svc)] = true;
                                                        }
                                                    }
                                                    
                                                    // Find unmapped services (services in listing not in config)
                                                    $unmappedServices = [];
                                                    foreach ($services as $svc) {
                                                        if (!isset($configFlatNormalized[$normalizeStr($svc)]) && $svc !== 'Other') {
                                                            $unmappedServices[] = $svc;
                                                        }
                                                    }
                                                    @endphp

                                                    @php
                                                    // Add emoji prefixes to category names (matching original bid format)
                                                    $categoryEmojis = [
                                                        'Tenant Criteria Marketing & Promotion' => '📢',
                                                        'Property Search, Alerts & Matching' => '🔍',
                                                        'Property Showings & Virtual Tours' => '🏡',
                                                        'Tenant Application Support' => '📝',
                                                        'Lease Preparation & Execution' => '📃',
                                                        'Lease Preparation, LOI & Execution' => '📃',
                                                        'Move-In Support & Coordination' => '🚚',
                                                        'Leasing Strategy & Guidance' => '📊',
                                                    ];
                                                    
                                                    $other_services = is_string($allMeta['other_services'] ?? '')
                                                        ? json_decode($allMeta['other_services'], true) ?? []
                                                        : ($allMeta['other_services'] ?? []);
                                                    $other_services = array_filter($other_services ?? [], fn($s) => is_string($s) && !empty(trim($s)));
                                                    
                                                    $hasAnyCounterServices = !empty($services) || !empty($other_services) || !empty($unmappedServices);
                                                    @endphp

                                                    @if ($hasAnyCounterServices)
                                                    <div class="mb-5">
                                                        <h6 class="mb-3" style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i class="fa fa-clipboard-list me-2"></i>Offered Services
                                                        </h6>
                                                        
                                                        @foreach ($servicesConfig as $category => $catServices)
                                                            @php
                                                            $selectedInCat = array_filter($catServices, fn($s) => isset($selectedNormalized[$normalizeStr($s)]));
                                                            $categoryEmoji = $categoryEmojis[$category] ?? '';
                                                            @endphp
                                                            @if (count($selectedInCat) > 0)
                                                            <div class="mb-3">
                                                                <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">{{ $categoryEmoji }} {{ $category }}</div>
                                                                <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem;">
                                                                    @foreach ($catServices as $service)
                                                                        @php $serviceNorm = $normalizeStr($service); @endphp
                                                                        @if (isset($selectedNormalized[$serviceNorm]))
                                                                        <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $selectedNormalized[$serviceNorm] }}</li>
                                                                        @endif
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                            @endif
                                                        @endforeach

                                                        @if (!empty($unmappedServices))
                                                        <div class="mb-3">
                                                            <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Unmapped Services</div>
                                                            <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem;">
                                                                @foreach ($unmappedServices as $service)
                                                                <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $service }}</li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                        @endif

                                                        @if (!empty($other_services))
                                                        <div class="mb-3">
                                                            <div class="fw-bold" style="color: #34465c; font-size: 0.95rem;">✍️ Additional Services</div>
                                                            <ul class="services mb-0" style="margin-top: 0.25rem; padding-left: 1.2rem;">
                                                                @foreach ($other_services as $otherService)
                                                                <li style="font-size: 0.9rem; margin-bottom: 4px;">{{ $otherService }}</li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif

                                                    <!-- Counter actions (only when both pending & viewer is the other party) -->
                                                    @inject('carbon', 'Carbon\Carbon')

                                                    @php
                                                    // Step 1: Get auction_time and check if it's not empty
                                                    $auctionTime = data_get($auction->get, 'auction_time');

                                                    // Step 2: Base date is $auction->created_at (not from get)
                                                    $baseDate = $carbon::parse($auction->created_at);

                                                    // Step 3: Calculate expiration based on conditions
                                                    if (!empty($auctionTime) && $auctionTime !== "" && $auctionTime !== null) {
                                                    // Extract number from auction_time (e.g., "10 Days" -> 10)
                                                    preg_match('/\d+/', $auctionTime, $matches);
                                                    $days = isset($matches[0]) ? (int)$matches[0] : 0;

                                                    $expiration = $days > 0
                                                    ? $baseDate->copy()->addDays($days)
                                                    : null;
                                                    } else {
                                                    // Use expiration_date from get if auction_time is empty
                                                    $expirationDate = data_get($auction->get, 'expiration_date');
                                                    $expiration = !empty($expirationDate)
                                                    ? $carbon::parse($expirationDate)
                                                    : null;
                                                    }

                                                    // Step 4: Check if expired
                                                    $isExpired = $expiration ? $carbon::now()->gte($expiration) : false;
                                                    @endphp

                                                    {{-- Step 6: Display Actions or Expired Message --}}
                                                    @if ($showCounterActions)
                                                    <div class="counter-response-buttons mt-3 pt-3 border-top">
                                                        <h6>Respond to this Counter Offer:</h6>

                                                        @if ($isExpired)
                                                        {{-- 🔹 If expired --}}
                                                        <div class="alert alert-warning text-center mt-2 mb-0 p-2" style="font-size: 15px">
                                                            <strong>Bidding/Counter Period Ended</strong>
                                                        </div>
                                                        @else
                                                        {{-- 🔹 Active Actions --}}
                                                        <div class="d-flex gap-3 flex-wrap justify-content-between">
                                                            {{-- Accept --}}
                                                            <form class="d-inline" action="{{ route('tenant.hire.agent.auction.counter.bid.accept') }}" method="post">
                                                                @csrf
                                                                <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                                <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                <input type="hidden" name="counter_bid_id" value="{{ data_get($counterBid, 'id') }}">
                                                                <button type="submit" class="btn-custom btn-accept" style="font-size:16px">Accept</button>
                                                            </form>

                                                            {{-- Reject --}}
                                                            <form class="d-inline" action="{{ route('tenant.hire.agent.auction.counter.bid.reject') }}" method="post">
                                                                @csrf
                                                                <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                                <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                <input type="hidden" name="counter_bid_id" value="{{ data_get($counterBid, 'id') }}">
                                                                <button type="submit" class="btn-custom btn-reject" style="font-size:16px">Reject</button>
                                                            </form>

                                                            {{-- Counter --}}
                                                            <form class="d-inline" action="{{ route('tenant.hire.agent.auction.bid.counter') }}" method="post">
                                                                @csrf
                                                                <input type="hidden" name="auction_id" value="{{ data_get($auction, 'id') }}">
                                                                <input type="hidden" name="bid_id" value="{{ data_get($bid, 'id') }}">
                                                                <input type="hidden" name="counter_bid_id" value="{{ data_get($counterBid, 'id') }}">
                                                                <button type="submit" class="btn-custom btn-counter" style="font-size:16px">Counter</button>
                                                            </form>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif




                                                    <!-- Counter footer status -->
                                                    <div class="mt-3 pt-3 border-top">
                                                        @if ($counterState === 'accepted')
                                                        @if (Auth::id() == $actorUserId)
                                                        <div
                                                            class="alert alert-success mb-0 py-1 small">
                                                            ✅ This counter bid has been
                                                            accepted.
                                                        </div>
                                                        @else
                                                        <div
                                                            class="alert alert-success mb-0 py-1 small">
                                                            ✅
                                                            {{ trim($actorFirst . ' ' . $actorLast) }}
                                                            accepted the counter bid.
                                                        </div>
                                                        @endif
                                                        @elseif ($counterState === 'rejected')
                                                        @if (Auth::id() == $actorUserId)
                                                        <div
                                                            class="alert alert-danger mb-0 py-1 small">
                                                            ❌ This counter bid has been
                                                            rejected.
                                                        </div>
                                                        @else
                                                        <div
                                                            class="alert alert-danger mb-0 py-1 alert-font">
                                                            ❌
                                                            {{ trim($actorFirst . ' ' . $actorLast) }}
                                                            rejected the counter bid.
                                                        </div>
                                                        @endif
                                                        @elseif ($counterState === '0')
                                                        @if ($counterBid->user_id == Auth::id())
                                                        <div
                                                            class="alert alert-secondary mb-0 py-1 small">
                                                            ⏳ Waiting for response from
                                                            {{ $isCounterFromOwner ? trim($agentFirst . ' ' . $agentLast) : trim($ownerFirst . ' ' . $ownerLast) }}...
                                                        </div>
                                                        @else
                                                        <div class="alert alert-light mb-0 py-1 small"
                                                            style="font-size:13px;">
                                                            ⏳ Counter bid from
                                                            {{ trim($creatorFirst . ' ' . $creatorLast) }}
                                                            is pending.
                                                        </div>
                                                        @endif
                                                        @endif
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    @elseif (!$showCounterBids && $counterBids->count() > 0)
                                    {{-- Show private message for non-authorized users --}}
                                    <div class="alert alert-info mt-3 p-2 small">
                                        <i class="fa fa-lock"></i> <strong>Private Counter
                                            Bids:</strong>
                                        Counter bidding history exists but is only visible to the
                                        listing owner and the bidding agent.
                                    </div>
                                    @endif

                                    {{-- Main Bid Actions - Traditional vs Bidding Period --}}
                                    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                                        @if ($state === '0' && $isOwnerRow && !data_get($auction, 'is_sold'))

                                        @if ($isBiddingPeriodListing && $isBiddingTimerActive)
                                        {{-- 🔹 Bidding Period: Timer still active - actions locked --}}
                                        <div class="alert alert-info text-center mt-2 mb-0 p-2 w-100" style="margin-left: 20px;">
                                            <i class="fa fa-clock me-1"></i> <strong>Actions unlock when the bidding period ends.</strong>
                                        </div>
                                        @elseif ($isTraditionalListing && $isExpired)
                                        {{-- 🔹 Traditional: Listing expired --}}
                                        <div class="alert alert-warning text-center mt-2 mb-0 p-2 ml-2" style="margin-left: 20px;">
                                            <strong>Listing Expired</strong>
                                        </div>
                                        @elseif ($isBiddingPeriodListing && $isExpired)
                                        {{-- 🔹 Bidding Period: Timer ended - show actions --}}
                                        <div class="biding-btn">
                                            <form
                                                action="{{ route('tenant.hire.agent.auction.bid.accept') }}"
                                                method="post">
                                                @csrf
                                                <input type="hidden" name="auction_id"
                                                    value="{{ data_get($auction, 'id') }}">
                                                <input type="hidden" name="bid_id"
                                                    value="{{ data_get($bid, 'id') }}">
                                                <button type="submit"
                                                    class="btn-custom btn-accept">Accept</button>
                                            </form>
                                        </div>
                                        <div class="biding-btn">
                                            <form
                                                action="{{ route('tenant.hire.agent.auction.bid.reject') }}"
                                                method="post">
                                                @csrf
                                                <input type="hidden" name="auction_id"
                                                    value="{{ data_get($auction, 'id') }}">
                                                <input type="hidden" name="bid_id"
                                                    value="{{ data_get($bid, 'id') }}">
                                                <button type="submit"
                                                    class="btn-custom btn-reject">Reject</button>
                                            </form>
                                        </div>
                                        <div class="biding-btn">
                                            <form
                                                action="{{ route('tenant.hire.agent.auction.bid.counter') }}"
                                                method="post">
                                                @csrf
                                                <input type="hidden" name="auction_id"
                                                    value="{{ data_get($auction, 'id') }}">
                                                <input type="hidden" name="bid_id"
                                                    value="{{ data_get($bid, 'id') }}">
                                                <button type="submit"
                                                    class="btn-custom btn-counter">Counter</button>
                                            </form>
                                        </div>
                                        @elseif ($isTraditionalListing)
                                        {{-- 🔹 Traditional: Actions always available immediately --}}
                                        <div class="biding-btn">
                                            <form
                                                action="{{ route('tenant.hire.agent.auction.bid.accept') }}"
                                                method="post">
                                                @csrf
                                                <input type="hidden" name="auction_id"
                                                    value="{{ data_get($auction, 'id') }}">
                                                <input type="hidden" name="bid_id"
                                                    value="{{ data_get($bid, 'id') }}">
                                                <button type="submit"
                                                    class="btn-custom btn-accept">Accept</button>
                                            </form>
                                        </div>
                                        <div class="biding-btn">
                                            <form
                                                action="{{ route('tenant.hire.agent.auction.bid.reject') }}"
                                                method="post">
                                                @csrf
                                                <input type="hidden" name="auction_id"
                                                    value="{{ data_get($auction, 'id') }}">
                                                <input type="hidden" name="bid_id"
                                                    value="{{ data_get($bid, 'id') }}">
                                                <button type="submit"
                                                    class="btn-custom btn-reject">Reject</button>
                                            </form>
                                        </div>
                                        <div class="biding-btn">
                                            <form
                                                action="{{ route('tenant.hire.agent.auction.bid.counter') }}"
                                                method="post">
                                                @csrf
                                                <input type="hidden" name="auction_id"
                                                    value="{{ data_get($auction, 'id') }}">
                                                <input type="hidden" name="bid_id"
                                                    value="{{ data_get($bid, 'id') }}">
                                                <button type="submit"
                                                    class="btn-custom btn-counter">Counter</button>
                                            </form>
                                        </div>
                                        @endif
                                        @endif

                                        @if ($state === 'accepted')
                                        @if (Auth::id() == $ownerId)
                                        <div
                                            class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                            ✅ This bid has been accepted.
                                        </div>
                                        @else
                                        <div
                                            class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                            ✅ {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted
                                            the bid.
                                        </div>
                                        @endif
                                        @php
                                            $agentAcceptedBidSummary = \App\Models\AcceptedBidSummary::where('accepted_bid_id', data_get($bid, 'id'))->first();
                                        @endphp
                                        @if($agentAcceptedBidSummary && (Auth::id() == $ownerId || data_get($bid, 'user_id') == Auth::id()))
                                        <div class="d-flex gap-1 flex-wrap mt-2">
                                            <a href="{{ route('accepted-bid-summary.view', $agentAcceptedBidSummary->id) }}" class="btn btn-outline-primary btn-sm py-1 px-2">
                                                <i class="fa fa-file-alt me-1"></i> View Summary
                                            </a>
                                            @if(data_get($bid, 'user_id') == Auth::id() && !$agentAcceptedBidSummary->isAgentSigned())
                                            <a href="{{ route('accepted-bid-summary.sign-form', $agentAcceptedBidSummary->id) }}" class="btn btn-primary btn-sm py-1 px-2">
                                                <i class="fa fa-signature me-1"></i> E-Sign
                                            </a>
                                            @endif
                                            @if($agentAcceptedBidSummary->isFullySigned())
                                            <a href="{{ route('accepted-bid-summary.download-pdf', $agentAcceptedBidSummary->id) }}" class="btn btn-success btn-sm py-1 px-2">
                                                <i class="fa fa-download me-1"></i> PDF
                                            </a>
                                            @endif
                                        </div>
                                        @endif
                                        @elseif ($state === 'rejected')
                                        @if (Auth::id() == $ownerId)
                                        <div class="alert alert-danger mt-2 w-100 mb-0 py-1 small">
                                            ❌ This bid has been rejected.
                                        </div>
                                        @else
                                        <div class="alert alert-danger mt-2 w-100 mb-0 py-1 small">
                                            ❌ {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected
                                            the bid.
                                        </div>
                                        @endif
                                        @elseif ($state === '0')
                                        @if (data_get($bid, 'user_id') == Auth::id())
                                        <div
                                            class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
                                            ⏳ Waiting for response from
                                            {{ trim($ownerFirst . ' ' . $ownerLast) }}...
                                        </div>
                                        @else
                                        <div class="alert alert-light mt-2 w-100 mb-0 py-1 small">
                                            ⏳ Bid from {{ trim($agentFirst . ' ' . $agentLast) }}
                                            is pending.
                                        </div>
                                        @endif
                                        @endif
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach


                </div>
            </div>
        </div>
</div>
<button class="btn w-100 mt-0">
    <span class="bid m-0"><i class="fa fa-user"></i> </span>
</button>
<div class="p-4 card">
    <p class="text-600">Share this link via</p>
    <div class="qr-code" style="width: 100%; height:200px;">
        {{ qr_code(route('tenant.agent.view.auction.view', @$auction->id), 200) }}
    </div>
    <div class="card-social">
        <ul class="icons">
            <a href="">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="">
                <i class="fab fa-twitter"></i>
            </a>
            <a href="">
                <i class="fab fa-instagram"></i>
            </a>
            <a href="">
                <i class="fab fa-pinterest"></i>
            </a>
            <a href="">
                <i class="fab fa-linkedin"></i>
            </a>
        </ul>
        <p class="small opacity-8">Or copy link</p>
        <div class="field">
            <i class="fa fa-link"></i>
            <input type="text" readonly="" id="copylink"
                value="https://bidyouroffer.com/listing/534-pinellas-bayway-s-204-tierra-verde-fl-33715-4/">
            <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                style="min-width:60px;">Copy</button>
        </div>
    </div>
</div>
</div>
</div>
</div>
<hr>
<div class="container buyerOfferContentDetails">
    <h3 class="text-600 mb-4">Recommended For You</h3>
    <div class="cardsDetails row  justify-content-start">
        <!-- Card 1 -->
        <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
            <div class="card ">
                <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
                    class="card-img-top" alt="...">
                <div class="card-body pb-2 pt-2">
                    <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
                    <div class="houseDetails mb-1">
                        <span>
                            <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                    src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}" alt="bed icon"
                                    width="15"><b>
                                    4</b></span>
                            <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                    src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}" alt="bed icon"
                                    width="15"><b>
                                    2</b></span>
                            <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                    src="{{ asset('assets/fontawesome/svgs/thin/ruler-triangle.svg') }}"
                                    alt="bed icon" width="15"><b> 1,643 </b>Sq Ft</span>
                        </span>
                        - House for sale
                    </div>
                    <p class="card-text mb-1"><span class="badge bg-secondary">land/lots</span> <span
                            class="float-end"><span><b>MLS ID</b></span> <span>#12345</span></span></p>
                    <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg><b>28d 03:15:29</b></p>
                </div>
                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-6 left">
                            <!-- Barcode  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Scan Qr Code"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                </path>
                            </svg>
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Send Message"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                </path>
                            </svg>
                            <!-- FAvourite  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Add Favorites"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                </path>
                            </svg>
                        </div>
                        <div class="col-6 right text-end">
                            <b>$1,000</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Card 2 -->
        <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
            <div class="card ">
                <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
                    class="card-img-top" alt="...">
                <div class="card-body">
                    <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
                    <div class="houseDetails">
                        <span>
                            <span><b>4</b> bds</span>
                            <span><b>2</b> ba</span>
                            <span><b>1,643</b> sqft</span>
                        </span>
                        - House for sale
                    </div>
                    <p class="card-text"><span class="badge bg-secondary">land/lots</span> <span
                            class="float-end"><span><b>MLS
                                    ID</b></span> <span>#12345</span></span></p>
                </div>
                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-6 left">
                            <!-- Barcode  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                data-bs-trigger="hover focus" data-bs-placement="top"
                                data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                </path>
                            </svg>
                            <!-- Message  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                data-bs-trigger="hover focus" data-bs-placement="top"
                                data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                </path>
                            </svg>
                            <!-- FAvourite  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                data-bs-trigger="hover focus" data-bs-placement="top"
                                data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                </path>
                            </svg>
                        </div>
                        <div class="col-6 right text-end">
                            <b>$1,000</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


{{-- 🧠 Timer Script --}}
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js"
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>

{{-- Custom Bid Accordion Toggle (bypasses Bootstrap to avoid double-toggle conflicts) --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.bid-accordion-header').forEach(function(header) {
        header.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var targetId = this.getAttribute('data-target');
            var content = document.getElementById(targetId);
            var chevron = this.querySelector('.bid-chevron');
            var isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            if (isExpanded) {
                content.style.display = 'none';
                this.setAttribute('aria-expanded', 'false');
                if (chevron) chevron.style.transform = 'rotate(0deg)';
            } else {
                content.style.display = 'block';
                this.setAttribute('aria-expanded', 'true');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
            }
        });
    });
});
</script>

@if ($expiration && !$isExpired)
<script>
    $(document).ready(function() {
        const exp = $('.time').data('expiration');
        const expTime = new Date(exp).getTime();
        const now = new Date().getTime();
        const diffSec = Math.floor((expTime - now) / 1000);
        if (diffSec <= 0) return;

        const durations = Math.floor(diffSec / 86400) + "d" +
            Math.floor((diffSec % 86400) / 3600) + "h" +
            Math.floor((diffSec % 3600) / 60) + "m" +
            (diffSec % 60) + "s";

        $('.timer-d').timer({
            countdown: true,
            duration: durations,
            format: '%d',
            callback: onTimerEnd
        });
        $('.timer-h').timer({
            countdown: true,
            duration: durations,
            format: '%h',
            callback: onTimerEnd
        });
        $('.timer-m').timer({
            countdown: true,
            duration: durations,
            format: '%m',
            callback: onTimerEnd
        });
        $('.timer-s').timer({
            countdown: true,
            duration: durations,
            format: '%s',
            callback: onTimerEnd
        });

        function onTimerEnd() {
            $('.timer-d, .timer-h, .timer-m, .timer-s').timer('remove');
            $('.time').html("<div class='w-100 text-center text-danger fw-bold'>Bidding Ended</div>");
            $('.bid-btn').fadeOut(300, function() {
                $(this).after(
                    "<div class='alert alert-warning text-center mt-2 mb-0 p-2'><strong>Bidding Ended</strong></div>"
                );
            });
        }
    });
</script>
@endif

{{-- Auto-scroll logic for notification view parameter --}}
<script>
    $(document).ready(function() {
        const urlParams = new URLSearchParams(window.location.search);
        const viewSection = urlParams.get('view');
        const bidId = urlParams.get('bid_id');
        
        if (viewSection) {
            setTimeout(function() {
                let targetElement = null;
                
                if (viewSection === 'bids') {
                    targetElement = document.getElementById('bids-section');
                } else if (viewSection === 'counter' && bidId) {
                    targetElement = document.getElementById('counter-section-' + bidId);
                    if (targetElement) {
                        const toggleEl = targetElement.querySelector('.counter-bids-toggle');
                        if (toggleEl) {
                            toggleEl.click();
                        }
                    }
                }
                
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    targetElement.style.boxShadow = '0 0 10px 3px rgba(26, 74, 110, 0.3)';
                    setTimeout(function() {
                        targetElement.style.boxShadow = '';
                    }, 2000);
                }
            }, 500);
        }
    });
</script>
@endpush
