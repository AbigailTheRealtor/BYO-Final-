@extends('layouts.main')

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

    ul {
        --icon-size: 1em;
        --gutter: .5em;
        padding: 0 0 0 calc(var(--icon-size) + 2em);
    }

    ul li {
        padding-left: var(--gutter);
        color: #34465c;
    }

    ul li::marker {
        content: "\f101";
        /* FontAwesome Unicode */
        font-family: FontAwesome;
        font-size: var(--icon-size);
        /* color: #006e9f; */
        color: #11b7cf;
    }

    ul.services li {
        padding-left: var(--gutter);
        color: #34465c;
        list-style: none;
        /* Remove default list style */
        position: relative;
        /* Set position relative for ::before pseudo-element */
    }

    ul.services li::before {
        content: "\f101";
        /* FontAwesome icon content */
        font-family: FontAwesome;
        font-size: var(--icon-size);
        /* Set the desired icon size */
        position: absolute;
        /* Position the icon */
        left: -1.5em;
        /* Adjust the icon position */
        color: #11b7cf;
        /* Set the icon color */
    }

    .removeBold {
        font-weight: normal;
    }

    /* Base button style */
    .btn-custom {
        width: 100% !important;
        color: white;
        border: none;
    }

    .biding-btn {
        width: 31.5%;
    }

    /* Accept (green) */
    .btn-accept {
        background-color: #28a745;
    }

    .btn-accept:hover {
        background-color: #218838;
    }

    /* Reject (red) */
    .btn-reject {
        background-color: #dc3545;
    }

    .btn-reject:hover {
        background-color: #c82333;
    }

    /* Counter (orange) */
    .btn-counter {
        background-color: #f0ad4e;
        color: #212529;
        /* dark text for better contrast */
    }

    .btn-counter:hover {
        background-color: #ec971f;
    }

    .view-btn {
        padding: 6px !important;
    }

    .services-offered {
        padding: 23px !important;
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
                <div class="card-header">
                    <h4 style="margin-left: 15px; margin-top: 10px;">Listing Details: </h5>
                </div>
                <div class="card-body">
                    <div class="row" style="flex-wrap: wrap;">
                        {{-- Listing Status removed from here - now only shown as badge above Listing ID in header --}}
                        @if (@$auction->get->listing_title != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                            Listing Title:
                            <span class="removeBold">{{ @$auction->get->listing_title }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->working_with_agent != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                            Current Representation Status with Broker:
                            <span class="removeBold">{{ @$auction->get->working_with_agent }}</span>
                        </div>
                        @endif

                        @if (@$auction->get->desired_agent_hire_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                            Desired Agent Hire Date:
                            <span class="removeBold">
                                {{ date('F j, Y', strtotime(@$auction->get->desired_agent_hire_date)) }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->listing_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                            Listing Date:
                            <span
                                class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->listing_date)) }}</span>
                        </div>
                        @endif
                        @if (@$auction->get->expiration_date != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                            Expiration Date:
                            <span class="removeBold">{{ date('F j, Y', strtotime(@$auction->get->expiration_date)) }}
                            </span>
                        </div>
                        @endif
                        @if (@$auction->get->auction_type != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                            Listing Type:
                            <span class="removeBold"> {{ @$auction->get->auction_type }}
                            </span>
                        </div>
                        @endif

                        @if (@$auction->get->auction_time != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                            Bidding Period Length:
                            <span class="removeBold"> {{ @$auction->get->auction_time }}
                            </span>
                        </div>
                        @endif
                        @if (@$auction->get->agent_bid_visibility != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                            Agent Bid Visibility Preference:
                            <span class="removeBold"> {{ @$auction->get->agent_bid_visibility }}
                            </span>
                        </div>
                        @endif
                        @if (@$auction->get->meeting_Preference != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                            Meeting Preference:
                            <span class="removeBold"> {{ @$auction->get->meeting_Preference }}
                            </span>
                        </div>
                        @endif

                    </div>
                    <hr>
                    <div class="card-header">
                        <h4>Property Preferences: </h4>
                    </div>

                    <div class="row" style="flex-wrap: wrap;">

                        @if (@$auction->get->cities != null)
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Acceptable Cities:
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
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                            Zip Code:

                            @foreach ($auction->get->zipCodes as $zip)
                            <span class="removeBold badge bg-secondary">
                                {{ @$zip }}
                            </span>
                            @endforeach

                        </div>
                        @endif
                        {{-- @if (@$auction->get->state != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> State:
                                    <span class="removeBold">{{ @$auction->get->state }}</span>
                    </div>
                    @endif --}}

                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                        Acceptable Property Type:
                        <span class="removeBold">{{ @$auction->get->property_type }}</span>
                    </div>
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                        Acceptable Property Styles:
                        @if (gettype(@$auction->get->property_items) == 'array')
                        @foreach (@$auction->get->property_items as $item)
                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                        @endforeach
                        @endif
                    </div>
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                        Acceptable Leasing
                        Space:
                        <span class="removeBold">{{ $auction->get->leasing_space }}

                        </span>
                    </div>
                    @endif
                    @if (@$auction->get->bedrooms != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                        Minimum Sqft Needed:
                        <span class="removeBold">

                            {{ str_replace(',', '', $auction->get->minimum_heated_square ?? '') }}

                        </span>
                    </div>
                    @endif
                    @if (@$auction->get->minimum_leaseable != null && @$auction->get->minimum_leaseable != 'null')
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                        Furnishings Needed:
                        <span class="removeBold badge bg-secondary">{{ @$auction->get->tenant_require }}</span>
                    </div>
                    @endif

                    {{-- Carport Needed with conditional spaces --}}
                    @if (@$auction->get->carport_needed != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                        Carport Needed:
                        <span class="removeBold">{{ @$auction->get->carport_needed }}</span>
                    </div>
                    @if (@$auction->get->carport_needed === 'Yes' && !empty(@$auction->get->other_carport_needed) && @$auction->get->other_carport_needed !== 'null')
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                        Number of Carport Spaces Needed:
                        <span class="removeBold">{{ @$auction->get->other_carport_needed }}</span>
                    </div>
                    @endif
                    @endif

                    {{-- Garage Needed with conditional spaces (Residential) --}}
                    @if (@$auction->get->property_type === 'Residential Property' && @$auction->get->garage_needed != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                        Garage Needed:
                        <span class="removeBold">{{ @$auction->get->garage_needed }}</span>
                    </div>
                    @if (@$auction->get->garage_needed === 'Yes' && !empty(@$auction->get->other_garage_needed) && @$auction->get->other_garage_needed !== 'null')
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                        <i class="fa-regular fa-check-square"></i>
                        Pool Needed:
                        <span class="removeBold">Yes ({{ ucwords($poolTypeList) }})</span>
                    </div>
                    @elseif (in_array(optional($auction->get)->pool_needed, ['No', 'Optional'], true))
                    <div class="col-md-12 col-12 pt-2 fw-bold">
                        <i class="fa-regular fa-check-square"></i>
                        Pool Needed:
                        <span class="removeBold">{{ optional($auction->get)->pool_needed }}</span>
                    </div>
                    @endif

                    @if (@$auction->get->view_preference != null || @$auction->get->other_preferences != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> View
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
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                        Age-Restricted Community:
                        <span class="removeBold">{{ @$auction->get->leasing_55_plus }}</span>
                    </div>
                    @endif

                    @if (@$auction->get->non_negotiable_amenities != null || @$auction->get->other_non_negotiable_amenities != null)
                    <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                <div class="card-header">
                    <h4>Leasing Terms: </h4>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Maximum
                    Monthly Lease Price:
                    <span class="removeBold">
                        ${{ str_replace(',', '', $auction->get->budget ?? '') }}</span>
                </div>

                @if (@$auction->get->lease_for != null || $auction->get->other_lease_for != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Offered
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
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Offered
                    Lease Date:
                    <span class="removeBold">
                        @if (@$auction->get->lease_date)
                        {{ date('F j, Y', strtotime(@$auction->get->lease_date)) }}
                        @endif
                    </span>
                </div>

                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Leasing
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
                <div class="card-header">
                    <h4>Pre-Screening: </h4>
                </div>
                @if ($auction->get->number_occupant)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Number
                    of Occupants:
                    <span class="removeBold">
                        {{ $auction->get->number_occupant ?? '' }}</span>
                </div>
                @endif
                @if ($auction->get->monthly_income)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Estimated Monthly Net Household Income:
                    <span class="removeBold">

                        ${{ str_replace(',', '', $auction->get->monthly_income ?? '') }}

                    </span>
                </div>
                @endif
                @if (@$auction->get->pets)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Pets:
                    <span class="removeBold">
                        {{ @$auction->get->pets }}</span>
                </div>
                @endif
                @if (@$auction->get->pets == 'Yes')
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Number
                    of Pets:
                    <span class="removeBold">
                        {{ @$auction->get->number_of_pets != '' ? @$auction->get->number_of_pets : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Type of
                    Pets:
                    <span class="removeBold">
                        {{ @$auction->get->type_of_pets != '' ? @$auction->get->type_of_pets : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Breed of
                    Pets:
                    <span class="removeBold">
                        {{ @$auction->get->breed_of_pets != '' ? @$auction->get->breed_of_pets : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Weight
                    of Pets (lbs):
                    <span class="removeBold">
                        {{ @$auction->get->weight_of_pets != '' ? @$auction->get->weight_of_pets : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Service
                    Animal:
                    <span class="removeBold">
                        {{ @$auction->get->service_animal != '' ? @$auction->get->service_animal : '' }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Emotional Support Animal:
                    <span class="removeBold">
                        {{ @$auction->get->support_animal != '' ? @$auction->get->support_animal : '' }}</span>
                </div>
                @endif

                @if (@$auction->get->screening_concerns != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                <div class="card-header">
                    <h4>Services: </h4>
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
                <div class="card-header">
                    <h4>Additional Details: </h4>
                </div>

                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Additional Details:<span class="removeBold">
                        {{ $auction->get->additional_details ?? '' }}</span>
                </div>
                @endif

                <hr />
                <div class="card-header">
                    <h4>Broker Compensation & Agency Agreement Terms</h4>
                </div>

                <!-- Tenant’s Broker Compensation Sub-section -->
                <h5 class="mt-3 mb-2"><strong>Tenant’s Broker Compensation:</strong></h5>

                @if (@$auction->get->commission_structure != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Tenant’s Broker Lease Fee:
                    <span class="removeBold">
                        @if (@$auction->get->lease_fee_type === 'other')
                            {{ $auction->get->lease_fee_other ?? '' }}
                        @else
                            {{ $auction->get->lease_fee_type ?? '' }}
                        @endif
                    </span>
                </div>
                @endif

                @if (@$auction->get->lease_fee_type === 'Flat Fee' && @$auction->get->lease_fee_flat != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Flat Fee Amount:
                    <span class="removeBold">${{ number_format((float)str_replace(',', '', $auction->get->lease_fee_flat), 0) }}</span>
                </div>
                @endif

                @if (@$auction->get->lease_fee_type === 'Percentage of the Gross Lease Value' && @$auction->get->lease_fee_percentage != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Percentage Amount:
                    <span class="removeBold">{{ $auction->get->lease_fee_percentage }}%</span>
                </div>
                @endif

                @if (@$auction->get->lease_fee_type === 'Percentage of Monthly Rent' && @$auction->get->lease_fee_percentage_monthly_rent != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Percentage Amount:
                    <span class="removeBold">{{ $auction->get->lease_fee_percentage_monthly_rent }}%</span>
                </div>
                @endif

                @if (@$auction->get->lease_fee_type === 'Flat Fee + Percentage of the Gross Lease Value' && @$auction->get->lease_fee_flat_combo != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Flat Fee Amount:
                    <span class="removeBold">${{ number_format((float)str_replace(',', '', $auction->get->lease_fee_flat_combo), 0) }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Percentage Amount:
                    <span class="removeBold">{{ $auction->get->lease_fee_percentage_combo }}%</span>
                </div>
                @endif

                @if (@$auction->get->lease_fee_type === 'Percentage of the Net Aggregate Rent' && @$auction->get->lease_fee_percentage_net != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Percentage Amount:
                    <span class="removeBold">{{ $auction->get->lease_fee_percentage_net }}%</span>
                </div>
                @endif

                @if (@$auction->get->lease_fee_type === 'Flat Fee + Percentage of the Net Aggregate Rent' && @$auction->get->lease_fee_flat_combo_net != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Flat Fee Amount:
                    <span class="removeBold">${{ number_format((float)str_replace(',', '', $auction->get->lease_fee_flat_combo_net), 0) }}</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Percentage Amount:
                    <span class="removeBold">{{ $auction->get->lease_fee_percentage_combo_net }}%</span>
                </div>
                @endif

                @if (@$auction->get->broker_fee_timing != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Calendar Days to Pay:
                    <span class="removeBold">{{ @$auction->get->broker_fee_days_from_rent ?? @$auction->get->broker_fee_days_after_lease ?? @$auction->get->broker_fee_days_after_rent ?? '' }}</span>
                </div>
                @endif

                <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                <!-- Purchase Fee Details Sub-section -->
                <h5 class="mt-3 mb-2"><strong>Purchase Fee Details:</strong></h5>

                @if (@$auction->get->interested_purchase_fee_type != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Interested in Purchasing a Property:
                    <span class="removeBold">{{ $auction->get->interested_purchase_fee_type ?? '' }}</span>
                </div>
                @endif

                @if (@$auction->get->interested_purchase_fee_type === 'Yes' && @$auction->get->purchase_fee_type != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Purchase Fee Type:
                    <span class="removeBold">
                        @if (@$auction->get->purchase_fee_type === 'other')
                            {{ $auction->get->purchase_fee_other ?? '' }}
                        @else
                            {{ $auction->get->purchase_fee_type ?? '' }}
                        @endif
                    </span>
                </div>
                @endif

                @if (@$auction->get->purchase_fee_type === 'Flat Fee' && @$auction->get->purchase_fee_flat != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Flat Fee Amount:
                    <span class="removeBold">${{ number_format((float)str_replace(',', '', $auction->get->purchase_fee_flat), 0) }}</span>
                </div>
                @endif

                @if (@$auction->get->purchase_fee_type === 'Percentage of the Total Purchase Price' && @$auction->get->purchase_fee_percentage != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Purchase Percentage:
                    <span class="removeBold">{{ $auction->get->purchase_fee_percentage }}%</span>
                </div>
                @endif

                @if (@$auction->get->purchase_fee_type === 'Percentage of the Total Purchase Price + Flat Fee' && @$auction->get->purchase_fee_percentage_combo != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Purchase Percentage:
                    <span class="removeBold">{{ $auction->get->purchase_fee_percentage_combo }}%</span>
                </div>
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Additional Flat Fee:
                    <span class="removeBold">${{ number_format((float)str_replace(',', '', $auction->get->purchase_fee_flat_combo ?? '0'), 0) }}</span>
                </div>
                @endif

                <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                <!-- Lease-Option Details Sub-section -->
                <h5 class="mt-3 mb-2"><strong>Lease-Option Details:</strong></h5>

                @if (@$auction->get->interested_lease_option_agreement != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Interested in a Lease-Option Agreement:
                    <span class="removeBold">{{ $auction->get->interested_lease_option_agreement ?? '' }}</span>
                </div>
                @endif

                @if (@$auction->get->interested_lease_option_agreement === 'Yes')
                @if (@$auction->get->lease_value != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Protection Period Timeframe:
                    <span class="removeBold">{{ $auction->get->protection_period ?? '' }} Days</span>
                </div>
                @endif

                @if (@$auction->get->early_termination_fee_option != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Early Termination Fee:
                    <span class="removeBold">{{ $auction->get->early_termination_fee_option ?? '' }}</span>
                </div>
                @endif

                @if (@$auction->get->early_termination_fee_option === 'Yes' && @$auction->get->early_termination_fee_amount != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Termination Fee Amount:
                    <span class="removeBold">${{ number_format((float)str_replace(',', '', $auction->get->early_termination_fee_amount), 0) }}</span>
                </div>
                @endif

                @if (@$auction->get->retainer_fee_option != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Retainer Fee:
                    <span class="removeBold">{{ $auction->get->retainer_fee_option ?? '' }}</span>
                </div>
                @endif

                @if (@$auction->get->retainer_fee_option === 'Yes' && @$auction->get->retainer_fee_amount != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Retainer Fee Amount:
                    <span class="removeBold">${{ number_format((float)str_replace(',', '', $auction->get->retainer_fee_amount), 0) }}</span>
                </div>
                @endif

                @if (@$auction->get->retainer_fee_option === 'Yes' && @$auction->get->retainer_fee_application != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Retainer Fee Application:
                    <span class="removeBold">
                        {{ $auction->get->retainer_fee_application === 'applied' ? 'Applied toward final compensation' : 'Charged in addition to final compensation' }}
                    </span>
                </div>
                @endif

                @if (@$auction->get->agency_agreement_timeframe != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
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
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Acceptable Brokerage Relationship:
                    <span class="removeBold">{{ $auction->get->brokerage_relationship ?? '' }}</span>
                </div>
                @endif

                @if (@$auction->get->additional_details_broker != null)
                <div class="col-12 my-3"><hr style="border-top: 1px solid #ccc;"></div>

                <!-- Additional Terms Sub-section (Separate from Brokerage Relationship) -->
                <h5 class="mt-3 mb-2"><strong>Additional Terms:</strong></h5>

                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Additional Terms Details:
                    <span class="removeBold">{{ $auction->get->additional_details_broker }}</span>
                </div>
                @endif

                <hr />
                <div class="card-header">
                    <h4>Tenant’s Info </h4>
                </div>
                @if (!empty($auction->get->first_name))
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> First
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
    <hr>

    @inject('carbon', 'Carbon\Carbon')

    @php
    // 🕒 Auction start time (when auction began)
    $start_time = $auction->get->created_at ?? $auction->created_at ?? $carbon::now();

    // 🔹 Get auction_time value
    $auction_time = trim($auction->get->auction_time ?? '');
    $useAuctionTime = !empty($auction_time) && strtolower($auction_time) !== 'null';

    if ($useAuctionTime) {
    // 🔸 CASE 1: Use auction_time (e.g. "14 Days", "2 Weeks", "5 Hours")
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
    } else {
    // 🔸 CASE 2: Fallback to expiration_date (Traditional)
    $expiration = !empty($auction->get->expiration_date)
    ? $carbon::parse($auction->get->expiration_date)
    : null;
    }

    // 🧾 Determine if expired
    $isExpired = $expiration ? $carbon::now()->gte($expiration) : false;

    // ⏱ Calculate remaining time if not expired
    if ($expiration && !$isExpired) {
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


        {{-- ⏳ Countdown Timer --}}
        @if ($expiration && !$isExpired)
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
        @else
        <div class="alert alert-warning text-center mt-2 mb-0 p-2">
            <strong>Bidding Ended</strong>
        </div>
        @endif



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

        <div class="card higestBider">
            <div class="card-body card-body-padding">
                @if ($lowest_bidder)
                <p><b>{{ $lowest_bidder->user->first_name ?? '' }}</b> is the last bidder.</p>
                {{-- <p><b>{{ $lowest_bidder->user->name ?? '' }}</b> is the lowest bidder.</p> --}}
                @else
                <p>No one has bid on this auction.</p>
                @endif
                <div class="accordion" id="accordionExample">
                    <div class="accordion-item border-0">

                        @foreach (@$auction->bids as $bid)
                        <!-- Item loop -->
                        <div class="accordion-header" style="cursor: pointer;" role="button" data-bs-toggle="collapse"
                            data-bs-target="#item{{ data_get($bid, 'id') }}" aria-expanded="false"
                            aria-controls="item{{ data_get($bid, 'id') }}">
                            <div class="d-flex small mr-0 text-center p-2 border rounded mb-1" style="background: #f8f9fa;">
                                <div class="col-1">
                                    <span class="badge bg-primary">{{ $loop->iteration }}</span>
                                </div>
                                <div class="col-5">
                                    {{ data_get($bid, 'user.first_name', '') }}
                                </div>
                                <div class="col-3 text-right">
                                    {{ data_get($bid, 'get.agent_fee', data_get($bid, 'agent_fee', '')) }}
                                </div>
                                <div class="col-3 d-flex justify-content-end">
                                    <span class="text-primary">Terms ↓</span>
                                </div>
                            </div>
                        </div>

                        <div id="item{{ data_get($bid, 'id') }}" class="accordion-collapse collapse"
                            aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                            <div class="accordion-body-padding" style="padding: 20px;">
                                <div id="bidding_history_data">
                                    <div>
                                        <!-- Agent Information -->
                                        {{-- <p class="d-flex justify-content-between  align-items-center small"
                                                        style="color: #333;">
                                                        <span>
                                                            Agent First Name:
                                                        </span>
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.first_name', '') }}
                                        </span>
                                        </p> --}}
                                        <p class="d-flex justify-content-between small"
                                            style="font-size:large; color: #333;">
                                            Licensed Since:
                                            <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                {{ data_get($bid, 'get.year_licensed', '') }}
                                            </span>
                                        </p>
                                        <p class="d-flex justify-content-between small"
                                            style="font-size:large; color: #333;">
                                            Marketing Strategy:
                                            <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                {{ data_get($bid, 'get.marketing_plan', '') }}
                                            </span>
                                        </p>
                                        <p class="d-flex justify-content-between small"
                                            style="font-size:large; color: #333;">
                                            What Sets This Agent Apart:
                                            <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                {{ data_get($bid, 'get.what_sets_you_apart', '') }}
                                            </span>
                                        </p>

                                        {{-- <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        Why Should You Be Hired as Their Agent?
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.why_hire_you', '') }}
                                        </span>
                                        </p> --}}

                                        <!-- Services Offered -->
                                        @php $servicesList = (array) data_get($bid,'get.services',[]); @endphp
                                        @if (!empty($servicesList))
                                        <div>
                                            <label style="font-size: large;">
                                                Services Offered:</label>
                                            <ul class="services services-offered">
                                                @foreach ($servicesList as $service)
                                                @if ($service == 'Other')
                                                @continue
                                                @endif
                                                <li class="alert-font"
                                                    style="font-size: 16px; margin-top:15px;">
                                                    {{ $service }}
                                                </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        @endif

                                        {{-- @php $otherServicesList = (array) data_get($bid,'get.other_services',[]); @endphp
                                                    @if (!empty($otherServicesList))
                                                        <div>
                                                            <label style="font-size: large;">Other Services Offered by the
                                                                Agent:</label>
                                                            <ul class="services services-offered">
                                                                @foreach ($otherServicesList as $service)
                                                                    <li style="font-size: 16px; margin-top:15px;">
                                                                        {{ $service }}</li>
                                        @endforeach
                                        </ul>
                                    </div>
                                    @endif --}}

                                    <!-- PRIVATE DATA SECTION - Only visible to listing owner -->
                                    @if (data_get($auction, 'user_id') == $auth_id)
                                    <!-- Button to trigger modal -->
                                    <div class="text-center">
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                            data-bs-target="#privateDataModal{{ data_get($bid, 'id') }}"
                                            style="margin-top: -25px; padding: 12px 30px; width: 100%; background: #049399; border: none; border-radius: 8px; font-weight: 600; color: white;">
                                            <i class="fa fa-lock me-2"></i> View Private Compensation &
                                            Agreement Terms
                                        </button>
                                    </div>

                                    <!-- Private Data Modal -->
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

                                                    <!-- 1. Agent Overview & Qualifications -->
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
                                                                style="color: #049399;">About Agent
                                                            </div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.bio') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Why Hire You -->
                                                        @if (data_get($bid, 'get.why_hire_you'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Why Should
                                                                You Be Hired</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.why_hire_you') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Reviews Links -->
                                                        @if (data_get($bid, 'get.reviews_links'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Reviews
                                                                Links</div>
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
                                                                style="color: #049399;">Website
                                                                Link</div>
                                                            <div>
                                                                <a href="{{ data_get($bid, 'get.website_link') }}"
                                                                    target="_blank"
                                                                    class="text-primary text-decoration-none">
                                                                    <i
                                                                        class="fa fa-globe me-1"></i>
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
                                                                Media</div>
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

                                                    </div>

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
                                                            <i
                                                                class="fa fa-handshake me-2"></i>Broker
                                                            Compensation & Agency Agreement Terms
                                                        </h6>

                                                        <!-- Commission Structure -->
                                                        @if (data_get($bid, 'get.commission_structure'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Commission Structure</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.commission_structure') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Lease Fee Type -->
                                                        @if (data_get($bid, 'get.lease_fee_type'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Lease
                                                                Fee Type</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.lease_fee_type') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Lease Fee Amounts -->
                                                        @if (data_get($bid, 'get.lease_fee_type') === 'Flat Fee' && data_get($bid, 'get.lease_fee_flat'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Flat
                                                                Fee Amount</div>
                                                            <div class="text-muted">
                                                                @if (data_get($bid, 'get.lease_fee_flat_type') === '$')
                                                                ${{ number_format((float)data_get($bid, 'get.lease_fee_flat'), 2) }}
                                                                @else
                                                                {{ data_get($bid, 'get.lease_fee_flat') }}%
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.lease_fee_type') === 'Percentage of the Gross Lease Value' &&
                                                        data_get($bid, 'get.lease_fee_percentage'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Percentage of Gross Lease Value
                                                            </div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.lease_fee_percentage') }}%
                                                            </div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.lease_fee_type') === 'Percentage of Monthly Rent' &&
                                                        data_get($bid, 'get.lease_fee_percentage_monthly_rent'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Percentage of Monthly Rent</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.lease_fee_percentage_monthly_rent') }}%
                                                            </div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.lease_fee_type') === 'Flat Fee + Percentage of the Gross Lease Value')
                                                        @if (data_get($bid, 'get.lease_fee_flat_combo'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Flat Fee Portion</div>
                                                            <div class="text-muted">
                                                                ${{ number_format((float)data_get($bid, 'get.lease_fee_flat_combo'), 2) }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @if (data_get($bid, 'get.lease_fee_percentage_combo'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Percentage Portion</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.lease_fee_percentage_combo') }}%
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if (data_get($bid, 'get.lease_fee_type') === 'Percentage of the Net Aggregate Rent' &&
                                                        data_get($bid, 'get.lease_fee_percentage_net'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Percentage of Net Aggregate Rent
                                                            </div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.lease_fee_percentage_net') }}%
                                                            </div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.lease_fee_type') === 'Flat Fee + Percentage of the Net Aggregate Rent')
                                                        @if (data_get($bid, 'get.lease_fee_flat_combo_net'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Flat Fee Portion</div>
                                                            <div class="text-muted">
                                                                ${{ number_format((float)data_get($bid, 'get.lease_fee_flat_combo_net'), 2) }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @if (data_get($bid, 'get.lease_fee_percentage_combo_net'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Percentage Portion</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.lease_fee_percentage_combo_net') }}%
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if (data_get($bid, 'get.lease_fee_type') === 'other' && data_get($bid, 'get.lease_fee_other'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Other
                                                                Lease Fee</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.lease_fee_other') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Purchase Fee Section -->
                                                        @if (data_get($bid, 'get.interested_purchase_fee_type') === 'Yes')
                                                        <div class="mt-4 pt-3 border-top">
                                                            <h6 class="mb-3"
                                                                style="color: #049399; font-weight: 600;">
                                                                Purchase Fee Details</h6>

                                                            @if (data_get($bid, 'get.purchase_fee_type'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">
                                                                    Purchase Fee Type</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.purchase_fee_type') }}
                                                                </div>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.purchase_fee_type') === 'Flat Fee' && data_get($bid, 'get.purchase_fee_flat'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">
                                                                    Purchase Flat Fee</div>
                                                                <div class="text-muted">
                                                                    @if (data_get($bid, 'get.purchase_fee_flat_type') === '$')
                                                                    ${{ number_format((float)data_get($bid, 'get.purchase_fee_flat'), 2) }}
                                                                    @else
                                                                    {{ data_get($bid, 'get.purchase_fee_flat') }}%
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the Total Purchase Price' &&
                                                            data_get($bid, 'get.purchase_fee_percentage'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">
                                                                    Purchase Percentage
                                                                </div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.purchase_fee_percentage') }}%
                                                                </div>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the Total Purchase Price + Flat Fee')
                                                            @if (data_get($bid, 'get.purchase_fee_percentage_combo'))
                                                            <div class="mb-2">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">
                                                                    Purchase Percentage
                                                                </div>
                                                                <div
                                                                    class="text-muted">
                                                                    {{ data_get($bid, 'get.purchase_fee_percentage_combo') }}%
                                                                </div>
                                                            </div>
                                                            @endif
                                                            @if (data_get($bid, 'get.purchase_fee_flat_combo'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">
                                                                    Purchase Flat Fee
                                                                </div>
                                                                <div
                                                                    class="text-muted">
                                                                    ${{ number_format((float)data_get($bid, 'get.purchase_fee_flat_combo'), 2) }}
                                                                </div>
                                                            </div>
                                                            @endif
                                                            @endif

                                                            @if (data_get($bid, 'get.purchase_fee_type') === 'other' && data_get($bid, 'get.purchase_fee_other'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">
                                                                    Other Purchase Fee</div>
                                                                <div class="text-muted">
                                                                    {{ data_get($bid, 'get.purchase_fee_other') }}
                                                                </div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Lease Option Agreement -->
                                                        @if (data_get($bid, 'get.interested_lease_option_agreement') === 'Yes')
                                                        <div class="mt-4 pt-3 border-top">
                                                            <h6 class="mb-3"
                                                                style="color: #049399; font-weight: 600;">
                                                                Lease-Option Agreement Details
                                                            </h6>

                                                            @if (data_get($bid, 'get.lease_value'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">
                                                                    Lease Option
                                                                    Compensation</div>
                                                                <div class="text-muted">
                                                                    @if (data_get($bid, 'get.lease_type') === 'percent')
                                                                    {{ data_get($bid, 'get.lease_value') }}%
                                                                    @else
                                                                    ${{ number_format((float)data_get($bid, 'get.lease_value'), 2) }}
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.purchase_value'))
                                                            <div class="mb-3">
                                                                <div class="fw-semibold"
                                                                    style="color: #049399;">
                                                                    Purchase Option
                                                                    Compensation</div>
                                                                <div class="text-muted">
                                                                    @if (data_get($bid, 'get.purchase_type') === 'percent')
                                                                    {{ data_get($bid, 'get.purchase_value') }}%
                                                                    @else
                                                                    ${{ number_format((float)data_get($bid, 'get.purchase_value'), 2) }}
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Protection Period -->
                                                        @if (data_get($bid, 'get.protection_period'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Protection Period</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.protection_period') }}
                                                                days
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Early Termination Fee -->
                                                        @if (data_get($bid, 'get.early_termination_fee_option'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Early
                                                                Termination Fee</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.early_termination_fee_option') }}
                                                            </div>
                                                        </div>

                                                        @if (data_get($bid, 'get.early_termination_fee_option') === 'Yes' && data_get($bid, 'get.early_termination_fee_amount'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Termination Fee Amount</div>
                                                            <div class="text-muted">
                                                                ${{ number_format((float)data_get($bid, 'get.early_termination_fee_amount'), 2) }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Retainer Fee -->
                                                        @if (data_get($bid, 'get.retainer_fee_option'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Retainer Fee</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.retainer_fee_option') }}
                                                            </div>
                                                        </div>

                                                        @if (data_get($bid, 'get.retainer_fee_option') === 'Yes')
                                                        @if (data_get($bid, 'get.retainer_fee_amount'))
                                                        <div class="mb-2">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Retainer Fee Amount
                                                            </div>
                                                            <div class="text-muted">
                                                                ${{ number_format((float)data_get($bid, 'get.retainer_fee_amount'), 2) }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        @if (data_get($bid, 'get.retainer_fee_application'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Retainer Fee Application
                                                            </div>
                                                            <div class="text-muted">
                                                                @if (data_get($bid, 'get.retainer_fee_application') === 'applied')
                                                                Applied toward final
                                                                compensation
                                                                @else
                                                                Charged in addition
                                                                to final
                                                                compensation
                                                                @endif
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif
                                                        @endif

                                                        <!-- Agency Agreement Timeframe -->
                                                        @if (data_get($bid, 'get.agency_agreement_timeframe'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">Agency
                                                                Agreement Timeframe</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.agency_agreement_timeframe') }}
                                                            </div>
                                                        </div>

                                                        @if (data_get($bid, 'get.agency_agreement_timeframe') === 'Other' && data_get($bid, 'get.agency_agreement_custom'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Custom Timeframe</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.agency_agreement_custom') }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Brokerage Relationship -->
                                                        @if (data_get($bid, 'get.brokerage_relationship'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Brokerage Relationship</div>
                                                            <div class="text-muted">
                                                                {{ data_get($bid, 'get.brokerage_relationship') }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif

                                                    <!-- 3. Additional Terms & Details -->
                                                    @if (data_get($bid, 'get.additional_details_broker') || data_get($bid, 'get.additional_details'))
                                                    <div class="mb-5">
                                                        <h6 class="mb-3"
                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i
                                                                class="fa fa-file-contract me-2"></i>Additional
                                                            Terms & Details
                                                        </h6>

                                                        <!-- Additional Terms -->
                                                        @if (data_get($bid, 'get.additional_details_broker'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Additional Terms</div>
                                                            <div class="text-muted"
                                                                style="font-style: italic;">
                                                                {{ data_get($bid, 'get.additional_details_broker') }}
                                                            </div>
                                                        </div>
                                                        @endif

                                                        <!-- Additional Details -->
                                                        @if (data_get($bid, 'get.additional_details'))
                                                        <div class="mb-3">
                                                            <div class="fw-semibold"
                                                                style="color: #049399;">
                                                                Additional Details</div>
                                                            <div class="text-muted"
                                                                style="font-style: italic;">
                                                                {{ data_get($bid, 'get.additional_details') }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif

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
                                                                Business Card</div>

                                                            @if (data_get($bid, 'get.business_card_link'))
                                                            <div class="mb-2">
                                                                @php
                                                                $businessCardLink = data_get(
                                                                $bid,
                                                                'get.business_card_link',
                                                                );
                                                                // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                if (
                                                                !empty(
                                                                $businessCardLink
                                                                ) &&
                                                                !str_starts_with(
                                                                $businessCardLink,
                                                                'http://',
                                                                ) &&
                                                                !str_starts_with(
                                                                $businessCardLink,
                                                                'https://',
                                                                )
                                                                ) {
                                                                $businessCardLink =
                                                                'https://' .
                                                                $businessCardLink;
                                                                }
                                                                @endphp
                                                                <a href="{{ $businessCardLink }}"
                                                                    target="_blank"
                                                                    class="text-primary text-decoration-none">
                                                                    <i
                                                                        class="fa fa-external-link-alt me-1"></i>
                                                                    View Business Card
                                                                </a>
                                                            </div>
                                                            @endif

                                                            @if (data_get($bid, 'get.business_card'))
                                                            <div class="mb-2">
                                                                <div class="fw-medium mb-1"
                                                                    style="color: #049399;">
                                                                    Uploaded Business Card:
                                                                </div>
                                                                @if (is_string(data_get($bid, 'get.business_card')))
                                                                @php
                                                                $businessCardPath = data_get(
                                                                $bid,
                                                                'get.business_card',
                                                                );
                                                                $businessCardExtension = pathinfo(
                                                                $businessCardPath,
                                                                PATHINFO_EXTENSION,
                                                                );
                                                                @endphp

                                                                @if (in_array(strtolower($businessCardExtension), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                                                <img src="{{ asset('storage/' . $businessCardPath) }}"
                                                                    style="max-width: 300px; max-height: 200px; border-radius: 6px; border: 1px solid #e0e0e0;"
                                                                    alt="Business Card"
                                                                    class="img-fluid">
                                                                @else
                                                                <div
                                                                    class="d-flex align-items-center text-muted">
                                                                    <i
                                                                        class="fa fa-file me-2"></i>
                                                                    <div>
                                                                        <div>
                                                                            Business
                                                                            Card
                                                                            File
                                                                        </div>
                                                                        <small>{{ $businessCardExtension }}
                                                                            file</small>
                                                                    </div>
                                                                    <a href="{{ asset('storage/' . $businessCardPath) }}"
                                                                        target="_blank"
                                                                        class="btn btn-sm btn-outline-primary ms-2">
                                                                        <i
                                                                            class="fa fa-download"></i>
                                                                    </a>
                                                                </div>
                                                                @endif
                                                                @else
                                                                <div
                                                                    class="text-muted">
                                                                    <i
                                                                        class="fa fa-id-card me-1"></i>
                                                                    Business card
                                                                    uploaded
                                                                </div>
                                                                @endif
                                                            </div>
                                                            @endif
                                                        </div>
                                                        @endif

                                                        <!-- Marketing Materials Section -->
                                                        @if (data_get($bid, 'get.promoMaterials'))
                                                        <div>
                                                            <div class="fw-semibold mb-2"
                                                                style="color: #049399;">
                                                                Marketing Materials</div>

                                                            @foreach (data_get($bid, 'get.promoMaterials') as $index => $material)
                                                            @if (!empty($material['type']) || !empty($material['link']) || !empty($material['files']))
                                                            <div
                                                                class="mb-3 p-3 border rounded">
                                                                @if (!empty($material['type']))
                                                                <div class="fw-medium mb-2"
                                                                    style="color: #049399;">
                                                                    {{ $material['type'] }}
                                                                    @if ($material['type'] === 'Other' && !empty($material['other']))
                                                                    -
                                                                    {{ $material['other'] }}
                                                                    @endif
                                                                </div>
                                                                @endif

                                                                @if (!empty($material['link']))
                                                                <div
                                                                    class="mb-2">
                                                                    @php
                                                                    $materialLink =
                                                                    $material[
                                                                    'link'
                                                                    ];
                                                                    // Agar link mein http:// ya https:// nahi hai toh add karo
                                                                    if (
                                                                    !empty(
                                                                    $materialLink
                                                                    ) &&
                                                                    !str_starts_with(
                                                                    $materialLink,
                                                                    'http://',
                                                                    ) &&
                                                                    !str_starts_with(
                                                                    $materialLink,
                                                                    'https://',
                                                                    )
                                                                    ) {
                                                                    $materialLink =
                                                                    'https://' .
                                                                    $materialLink;
                                                                    }
                                                                    @endphp
                                                                    <a href="{{ $materialLink }}"
                                                                        target="_blank"
                                                                        class="text-primary text-decoration-none">
                                                                        <i
                                                                            class="fa fa-external-link-alt me-1"></i>
                                                                        View
                                                                        Material
                                                                        Link
                                                                    </a>
                                                                </div>
                                                                @endif

                                                                @if (!empty($material['files']))
                                                                <div
                                                                    class="mb-2">
                                                                    <div class="fw-medium mb-1"
                                                                        style="color: #049399;">
                                                                        Uploaded
                                                                        Files:</div>
                                                                    <div
                                                                        class="row">
                                                                        @foreach ($material['files'] as $fileIndex => $filePath)
                                                                        @if (is_string($filePath))
                                                                        @php
                                                                        $fileExtension = pathinfo(
                                                                        $filePath,
                                                                        PATHINFO_EXTENSION,
                                                                        );
                                                                        $fileName = basename(
                                                                        $filePath,
                                                                        );
                                                                        $imageExtensions = [
                                                                        'jpg',
                                                                        'jpeg',
                                                                        'png',
                                                                        'gif',
                                                                        'webp',
                                                                        ];
                                                                        $isImage = in_array(
                                                                        strtolower(
                                                                        $fileExtension,
                                                                        ),
                                                                        $imageExtensions,
                                                                        );
                                                                        @endphp

                                                                        <div
                                                                            class="col-md-6 col-lg-4 mb-2">
                                                                            <div
                                                                                class="border rounded p-2 d-flex align-items-center">
                                                                                @if ($isImage)
                                                                                <img src="{{ asset('storage/' . $filePath) }}"
                                                                                    style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 10px;"
                                                                                    alt="Marketing Material">
                                                                                @else
                                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-2"
                                                                                    style="width: 60px; height: 60px;">
                                                                                    <i
                                                                                        class="fa fa-file text-muted"></i>
                                                                                </div>
                                                                                @endif
                                                                                <div
                                                                                    class="flex-grow-1">
                                                                                    <div
                                                                                        class="small text-truncate">
                                                                                        {{ $fileName }}
                                                                                    </div>
                                                                                    <small
                                                                                        class="text-muted">{{ strtoupper($fileExtension) }}
                                                                                        file</small>
                                                                                </div>
                                                                                <a href="{{ asset('storage/' . $filePath) }}"
                                                                                    target="_blank"
                                                                                    class="btn btn-sm btn-outline-primary ms-1">
                                                                                    <i
                                                                                        class="fa fa-eye"></i>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                        @else
                                                                        <div
                                                                            class="col-md-6 col-lg-4 mb-2">
                                                                            <div
                                                                                class="border rounded p-2 d-flex align-items-center">
                                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-2"
                                                                                    style="width: 60px; height: 60px;">
                                                                                    <i
                                                                                        class="fa fa-file text-muted"></i>
                                                                                </div>
                                                                                <div
                                                                                    class="flex-grow-1">
                                                                                    <div
                                                                                        class="small">
                                                                                        File
                                                                                        {{ $fileIndex + 1 }}
                                                                                    </div>
                                                                                    <small
                                                                                        class="text-muted">Uploaded
                                                                                        file</small>
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
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif

                                                    <!-- 5. Agent Information -->
                                                    <div class="mb-4">
                                                        <h6 class="mb-3"
                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                            <i
                                                                class="fa fa-address-card me-2"></i>Agent
                                                            Information
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

                                                </div>
                                                <div class="modal-footer"
                                                    style="background: #fafafa; border-top: 1px solid #e0e0e0; padding: 20px;">
                                                    <div class="w-100 mb-3 p-3 text-center"
                                                        style="background: #e8f4f5; border-radius: 6px; color: #049399;">
                                                        <i class="fa fa-shield me-2"></i>
                                                        <strong>Confidential:</strong> This information
                                                        is private and only visible to you as the
                                                        listing owner.
                                                    </div>
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
                                    @else
                                    <!-- Public or other agents -->
                                    <div class="alert alert-warning mt-3 p-3 text-center"
                                        style="border-radius: 6px; background: #e8f4f5; color: #049399; border: none;">
                                        <i class="fa fa-lock me-2"></i> <strong>Private
                                            Information:</strong> Broker compensation terms and
                                        additional details are only visible to the listing owner.
                                    </div>
                                    @endif
                                    @endif

                                    <!-- Counter Bids -->

                                    @php
                                    $counterBids = \App\Models\TenantCounterBidding::with(
                                    'meta',
                                    'user',
                                    )
                                    ->where('tenant_agent_auction_bid_id', data_get($bid, 'id'))
                                    ->orderBy('created_at', 'desc')
                                    ->get();
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
                                    <div class="counter-bids-section mt-4">
                                        <!-- Counter Bids Accordion Header -->
                                        <div class="accordion" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#counterBids{{ data_get($bid, 'id') }}"
                                            aria-expanded="false"
                                            aria-controls="counterBids{{ data_get($bid, 'id') }}">
                                            <div
                                                class="d-flex justify-content-between align-items-center flex-wrap p-2 border rounded">
                                                <h5 class="mb-0" style="color: #2c3e50;">Counter
                                                    Bidding History</h5>
                                                <div class="d-flex align-items-center">
                                                    <span
                                                        class="badge bg-secondary me-2">{{ $counterBids->count() }}
                                                        counter offers</span>
                                                    <span class="accordion-arrow">↓</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Counter Bids Accordion Content -->
                                        <div id="counterBids{{ data_get($bid, 'id') }}"
                                            class="accordion-collapse collapse"
                                            aria-labelledby="counterBidsHeading{{ data_get($bid, 'id') }}">
                                            <div
                                                class="accordion-body p-3 border border-top-0 rounded-bottom counter-font">
                                                @foreach ($counterBids as $counterBid)
                                                @php
                                                // Roles
                                                $isOwner =
                                                data_get($auction, 'user_id') ==
                                                $auth_id;
                                                $isAgent =
                                                data_get($bid, 'user_id') == $auth_id;
                                                $isCounterFromOwner =
                                                $counterBid->user_id ==
                                                data_get($auction, 'user_id');
                                                $isCounterFromAgent =
                                                $counterBid->user_id ==
                                                data_get($bid, 'user_id');

                                                // States
                                                $rawBidState = data_get(
                                                $bid,
                                                'accepted',
                                                '0',
                                                );
                                                $bidState = in_array(
                                                $rawBidState,
                                                [null, 0, '0'],
                                                true,
                                                )
                                                ? '0'
                                                : (string) $rawBidState;

                                                $rawCounterState = data_get(
                                                $counterBid,
                                                'accepted',
                                                '0',
                                                );
                                                $counterState = in_array(
                                                $rawCounterState,
                                                [null, 0, '0', 'pending'],
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

                                                    @php $allMeta = $counterBid->getAllMeta(); @endphp
                                                    @if (
                                                    !empty($allMeta['commission_structure']) ||
                                                    !empty($allMeta['lease_fee_type']) ||
                                                    !empty($allMeta['interested_purchase_fee_type']) ||
                                                    !empty($allMeta['interested_lease_option_agreement']) ||
                                                    !empty($allMeta['protection_period']) ||
                                                    !empty($allMeta['early_termination_fee_option']) ||
                                                    !empty($allMeta['retainer_fee_option']) ||
                                                    !empty($allMeta['agency_agreement_timeframe']) ||
                                                    !empty($allMeta['brokerage_relationship']))
                                                    <div class="mb-4">
                                                        <h6 class="mb-3"
                                                            style="font-weight: 600; border-bottom: 1px solid #ddd; padding-bottom: 8px;">
                                                            Broker Compensation
                                                        </h6>

                                                        <!-- Commission Structure -->
                                                        @if (!empty($allMeta['commission_structure']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Tenant’s
                                                                Broker Commission
                                                                Structure:</span>
                                                            {{ $allMeta['commission_structure'] }}
                                                        </div>
                                                        @endif

                                                        <!-- Lease Fee Type -->
                                                        @if (!empty($allMeta['lease_fee_type']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Tenant’s
                                                                Broker Lease Fee:</span>
                                                            {{ $allMeta['lease_fee_type'] }}
                                                        </div>
                                                        @endif

                                                        <!-- Lease Fee Amounts -->
                                                        @if (!empty($allMeta['lease_fee_flat']) && $allMeta['lease_fee_type'] === 'Flat Fee')
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Flat
                                                                Fee Amount:</span>
                                                            @if (!empty($allMeta['lease_fee_flat_type']) && $allMeta['lease_fee_flat_type'] === '$')
                                                            ${{ number_format($allMeta['lease_fee_flat'], 2) }}
                                                            @else
                                                            {{ $allMeta['lease_fee_flat'] }}%
                                                            @endif
                                                        </div>
                                                        @endif

                                                        @if (!empty($allMeta['lease_fee_percentage']) && $allMeta['lease_fee_type'] === 'Percentage of the Gross Lease Value')
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Percentage
                                                                of Gross Lease Value:</span>
                                                            {{ $allMeta['lease_fee_percentage'] }}%
                                                        </div>
                                                        @endif

                                                        @if (
                                                        !empty($allMeta['lease_fee_percentage_monthly_rent']) &&
                                                        $allMeta['lease_fee_type'] === 'Percentage of Monthly Rent')
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Percentage
                                                                of Monthly Rent:</span>
                                                            {{ $allMeta['lease_fee_percentage_monthly_rent'] }}%
                                                        </div>
                                                        @endif

                                                        @if ($allMeta['lease_fee_type'] === 'Flat Fee + Percentage of the Gross Lease Value')
                                                        @if (!empty($allMeta['lease_fee_flat_combo']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Flat
                                                                Fee Portion:</span>
                                                            ${{ number_format($allMeta['lease_fee_flat_combo'], 2) }}
                                                        </div>
                                                        @endif
                                                        @if (!empty($allMeta['lease_fee_percentage_combo']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Percentage
                                                                Portion:</span>
                                                            {{ $allMeta['lease_fee_percentage_combo'] }}%
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if (
                                                        !empty($allMeta['lease_fee_percentage_net']) &&
                                                        $allMeta['lease_fee_type'] === 'Percentage of the Net Aggregate Rent')
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Percentage
                                                                of Net Aggregate
                                                                Rent:</span>
                                                            {{ $allMeta['lease_fee_percentage_net'] }}%
                                                        </div>
                                                        @endif

                                                        @if ($allMeta['lease_fee_type'] === 'Flat Fee + Percentage of the Net Aggregate Rent')
                                                        @if (!empty($allMeta['lease_fee_flat_combo_net']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Flat
                                                                Fee Portion:</span>
                                                            ${{ number_format($allMeta['lease_fee_flat_combo_net'], 2) }}
                                                        </div>
                                                        @endif
                                                        @if (!empty($allMeta['lease_fee_percentage_combo_net']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Percentage
                                                                Portion:</span>
                                                            {{ $allMeta['lease_fee_percentage_combo_net'] }}%
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if (!empty($allMeta['lease_fee_other']) && $allMeta['lease_fee_type'] === 'other')
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Other
                                                                Lease Fee:</span>
                                                            {{ $allMeta['lease_fee_other'] }}
                                                        </div>
                                                        @endif

                                                        <!-- Interested in Purchasing -->
                                                        @if (!empty($allMeta['interested_purchase_fee_type']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Interested
                                                                in Purchasing a
                                                                Property:</span>
                                                            {{ $allMeta['interested_purchase_fee_type'] }}
                                                        </div>
                                                        @endif

                                                        <!-- Purchase Fee Section -->
                                                        @if (!empty($allMeta['interested_purchase_fee_type']) && $allMeta['interested_purchase_fee_type'] === 'Yes')
                                                        @if (!empty($allMeta['purchase_fee_type']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Tenant’s
                                                                Broker Purchase
                                                                Fee:</span>
                                                            {{ $allMeta['purchase_fee_type'] }}
                                                        </div>
                                                        @endif

                                                        @if (!empty($allMeta['purchase_fee_flat']) && $allMeta['purchase_fee_type'] === 'Flat Fee')
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Flat
                                                                Fee Amount:</span>
                                                            @if (!empty($allMeta['purchase_fee_flat_type']) && $allMeta['purchase_fee_flat_type'] === '$')
                                                            ${{ number_format($allMeta['purchase_fee_flat'], 2) }}
                                                            @else
                                                            {{ $allMeta['purchase_fee_flat'] }}%
                                                            @endif
                                                        </div>
                                                        @endif

                                                        @if (
                                                        !empty($allMeta['purchase_fee_percentage']) &&
                                                        $allMeta['purchase_fee_type'] === 'Percentage of the Total Purchase Price')
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Percentage
                                                                of Total Purchase
                                                                Price:</span>
                                                            {{ $allMeta['purchase_fee_percentage'] }}%
                                                        </div>
                                                        @endif

                                                        @if ($allMeta['purchase_fee_type'] === 'Percentage of the Total Purchase Price + Flat Fee')
                                                        @if (!empty($allMeta['purchase_fee_percentage_combo']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Percentage
                                                                Portion:</span>
                                                            {{ $allMeta['purchase_fee_percentage_combo'] }}%
                                                        </div>
                                                        @endif
                                                        @if (!empty($allMeta['purchase_fee_flat_combo']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Flat
                                                                Fee Portion:</span>
                                                            ${{ number_format($allMeta['purchase_fee_flat_combo'], 2) }}
                                                        </div>
                                                        @endif
                                                        @endif

                                                        @if (!empty($allMeta['purchase_fee_other']) && $allMeta['purchase_fee_type'] === 'other')
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Other
                                                                Purchase Fee:</span>
                                                            {{ $allMeta['purchase_fee_other'] }}
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Lease Option Agreement -->
                                                        @if (!empty($allMeta['interested_lease_option_agreement']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Interested
                                                                in a Lease-Option
                                                                Agreement:</span>
                                                            {{ $allMeta['interested_lease_option_agreement'] }}
                                                        </div>
                                                        @endif

                                                        @if (!empty($allMeta['interested_lease_option_agreement']) && $allMeta['interested_lease_option_agreement'] === 'Yes')
                                                        @if (!empty($allMeta['lease_value']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Lease
                                                                Option
                                                                Compensation:</span>
                                                            @if (!empty($allMeta['lease_type']) && $allMeta['lease_type'] === 'percent')
                                                            {{ $allMeta['lease_value'] }}%
                                                            @else
                                                            ${{ number_format($allMeta['lease_value'], 2) }}
                                                            @endif
                                                        </div>
                                                        @endif

                                                        @if (!empty($allMeta['purchase_value']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Purchase
                                                                Option
                                                                Compensation:</span>
                                                            @if (!empty($allMeta['purchase_type']) && $allMeta['purchase_type'] === 'percent')
                                                            {{ $allMeta['purchase_value'] }}%
                                                            @else
                                                            ${{ number_format($allMeta['purchase_value'], 2) }}
                                                            @endif
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Protection Period -->
                                                        @if (!empty($allMeta['protection_period']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Protection
                                                                Period Timeframe:</span>
                                                            {{ $allMeta['protection_period'] }}
                                                            days
                                                        </div>
                                                        @endif

                                                        <!-- Early Termination Fee -->
                                                        @if (!empty($allMeta['early_termination_fee_option']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Early
                                                                Termination Fee:</span>
                                                            {{ $allMeta['early_termination_fee_option'] }}
                                                        </div>
                                                        @endif

                                                        @if (!empty($allMeta['early_termination_fee_amount']) && $allMeta['early_termination_fee_option'] === 'Yes')
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Termination
                                                                Fee Amount:</span>
                                                            ${{ number_format($allMeta['early_termination_fee_amount'], 2) }}
                                                        </div>
                                                        @endif

                                                        <!-- Retainer Fee -->
                                                        @if (!empty($allMeta['retainer_fee_option']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Retainer
                                                                Fee:</span>
                                                            {{ $allMeta['retainer_fee_option'] }}
                                                        </div>
                                                        @endif

                                                        @if (!empty($allMeta['retainer_fee_option']) && $allMeta['retainer_fee_option'] === 'Yes')
                                                        @if (!empty($allMeta['retainer_fee_amount']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Retainer
                                                                Fee Amount:</span>
                                                            ${{ number_format($allMeta['retainer_fee_amount'], 2) }}
                                                        </div>
                                                        @endif

                                                        @if (!empty($allMeta['retainer_fee_application']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;">
                                                            <span
                                                                style="font-size: 13px; font-weight: 600;">Retainer
                                                                Fee Application:</span>
                                                            @if ($allMeta['retainer_fee_application'] === 'applied')
                                                            Applied toward final
                                                            compensation
                                                            @else
                                                            Charged in addition to
                                                            final compensation
                                                            @endif
                                                        </div>
                                                        @endif
                                                        @endif

                                                        <!-- Agency Agreement Timeframe -->
                                                        @if (!empty($allMeta['agency_agreement_timeframe']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Tenant
                                                                Agency Agreement
                                                                Timeframe:</span>
                                                            {{ $allMeta['agency_agreement_timeframe'] }}
                                                        </div>
                                                        @endif

                                                        @if (!empty($allMeta['agency_agreement_custom']) && $allMeta['agency_agreement_timeframe'] === 'Other')
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Custom
                                                                Timeframe:</span>
                                                            {{ $allMeta['agency_agreement_custom'] }}
                                                        </div>
                                                        @endif

                                                        <!-- Brokerage Relationship -->
                                                        @if (!empty($allMeta['brokerage_relationship']))
                                                        <div class="mb-1"
                                                            style="font-size: 12px;"><span
                                                                style="font-size: 13px; font-weight: 600;">Acceptable
                                                                Brokerage
                                                                Relationship:</span>
                                                            {{ $allMeta['brokerage_relationship'] }}
                                                        </div>
                                                        @endif

                                                        <!-- Additional Terms -->
                                                        @if (!empty($allMeta['additional_details_broker']))
                                                        <div class="mt-3">
                                                            <div
                                                                style="font-size: 13px; font-weight: 600; margin-bottom: 5px;">
                                                                Additional Terms:
                                                            </div>
                                                            <div style="font-size: 12px;">
                                                                {{ $allMeta['additional_details_broker'] }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @endif

                                                    <!-- Services Offered -->
                                                    @php
                                                    $services = is_string(
                                                    $allMeta['services'],
                                                    )
                                                    ? json_decode(
                                                    $allMeta['services'],
                                                    true,
                                                    )
                                                    : $allMeta['services'];
                                                    @endphp

                                                    @if (!empty($services))
                                                    <div style="margin-top: 20px;">
                                                        <label
                                                            style="font-size: 18px; font-weight: bold; display: block; margin-bottom: 10px;">
                                                            Services:
                                                        </label>
                                                        <div
                                                            style="display: flex; flex-wrap: wrap; gap: 10px;">
                                                            @foreach ($services as $service)
                                                            @if ($service == 'Other')
                                                            @continue
                                                            @endif
                                                            <span
                                                                style="background: #f1f5f9; color: #111; padding: 6px 12px; border-radius: 8px; font-size: 12px; border: 1px solid #ddd;">
                                                                {{ $service }}
                                                            </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    @endif

                                                    @php
                                                    $other_services = is_string(
                                                    $allMeta['other_services'],
                                                    )
                                                    ? json_decode(
                                                    $allMeta['other_services'],
                                                    true,
                                                    )
                                                    : $allMeta['other_services'];
                                                    @endphp

                                                    @if (!empty($other_services))
                                                    <div style="margin-top: 20px;">
                                                        <label
                                                            style="font-size: 18px; font-weight: bold; display: block; margin-bottom: 10px;">
                                                            Other Services:
                                                        </label>
                                                        <div
                                                            style="display: flex; flex-wrap: wrap; gap: 10px;">
                                                            @foreach ($other_services as $other_service)
                                                            <span
                                                                style="background: #f1f5f9; color: #111; padding: 6px 12px; border-radius: 8px; font-size: 12px; border: 1px solid #ddd;">
                                                                {{ $other_service }}
                                                            </span>
                                                            @endforeach
                                                        </div>
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

                                    {{-- Main Bid Actions (keep this section as is) --}}
                                    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                                        @if ($state === '0' && $isOwnerRow && !data_get($auction, 'is_sold'))

                                        @if ($isExpired)
                                        {{-- 🔹 Show expired message if auction expired --}}
                                        <div class="alert alert-warning text-center mt-2 mb-0 p-2 ml-2" style="margin-left: 20px;">
                                            <strong>Bidding/Counter Period Ended</strong>
                                        </div>
                                        @else
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

                    @if ($auction->bids->count() > 0)
                    <div class="alert alert-warning mt-3 p-2 small">
                        <strong> 🛡️ Compliance Note: </strong> No Broker Compensation, Agency Agreement
                        Terms,
                        or Counter Offers are ever displayed publicly. These must remain private to avoid
                        antitrust/commission advertising issues.
                    </div>
                    @endif

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
@endpush
