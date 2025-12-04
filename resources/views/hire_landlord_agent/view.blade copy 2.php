@extends('layouts.main')
@push('styles')
    <!-- //Listing Description css  -->
    <link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">

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
    @php
        $auth_id = auth()->user() ? auth()->user()->id : 0;
    @endphp

    <div class="container listingDescription">
        <div class="row">
            <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
                <div class="card description">
                    <div class="card-header">
                        <h4 style="margin-left: 15px; margin-top: 10px;">Listing Details: </h5>
                    </div>
                    <div class="card-body">
                        <div class="row" style="flex-wrap: wrap;">
                            @if (@$auction->get->listing_title != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Listing Title
                                    <span class="removeBold">{{ @$auction->get->listing_title }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->working_with_agent != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Current Representation Status with Broker?
                                    <span class="removeBold">{{ @$auction->get->working_with_agent }}</span>
                                </div>
                            @endif

                            @if (@$auction->get->desired_agent_hire_date != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Desired Agent Hire Date:
                                    <span class="removeBold">{{ @$auction->get->desired_agent_hire_date }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->listing_date != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Listing Date:
                                    <span class="removeBold">{{ @$auction->get->listing_date }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->expiration_date != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Expiration Date:
                                    <span class="removeBold">{{ @$auction->get->expiration_date }}
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
                                    Auction Length:
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
                            <h4>Property Details: </h4>
                        </div>

                        <div class="row" style="flex-wrap: wrap;">

                            @if (@$auction->get->address != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Property Address:

                                    <span class="removeBold">{{ @$auction->get->address }}</span>

                                </div>
                            @endif
                            @if (@$auction->get->cities != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> City:
                                    @if (gettype(@$auction->get->cities) == 'array')
                                        @foreach (@$auction->get->cities as $item)
                                            <span class="removeBold">{{ $item }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                            @if (@$auction->get->counties != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    County:
                                    @if (gettype(@$auction->get->counties) == 'array')
                                        @foreach (@$auction->get->counties as $item)
                                            <span class="removeBold">{{ $item }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                            @if (@$auction->get->zip_code != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> ZIP
                                    Code:
                                    <span class="removeBold">{{ @$auction->get->zip_code }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->state != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> State:
                                    <span class="removeBold">{{ @$auction->get->state }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->property_type != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Property Type:
                                    <span class="removeBold">{{ @$auction->get->property_type }}</span>
                                </div>
                            @endif

                            @if (@$auction->get->property_items != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i
                                        class="fa-regular fa-check-square"></i>Property Style:
                                    <span class="removeBold">{{ @$auction->get->property_items }}</span>
                                </div>
                            @endif

                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Property
                                Conditions:
                                @if (gettype(@$auction->get->condition_prop) == 'array')
                                    @foreach (array_filter(@$auction->get->condition_prop) as $item)
                                        <span class="removeBold"> {{ $item }}</span>
                                        @if ($item == 'Other')
                                            <span class="removeBold"> {{ @$auction->get->other_property_condition }}</span>
                                        @endif
                                    @endforeach
                                @endif

                            </div>

                            @if (@$auction->get->bedrooms != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Bedrooms
                                    :
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
                                    Bathrooms
                                    :
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
                                    Heated SqFt:
                                    <span class="removeBold">
                                        {{ @$auction->get->minimum_heated_square != '' ? @$auction->get->minimum_heated_square : '' }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->minimum_leaseable != null && @$auction->get->minimum_leaseable != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Net Leasable SqFt:
                                    <span class="removeBold">
                                        {{ @$auction->get->minimum_leaseable != '' ? @$auction->get->minimum_leaseable : '' }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->total_square_feet != null && @$auction->get->total_square_feet != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Total SqFt:
                                    <span class="removeBold">
                                        {{ @$auction->get->total_square_feet != '' ? @$auction->get->total_square_feet : '' }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->sqft_heated_source != null && @$auction->get->sqft_heated_source != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    SqFt Heated Source:
                                    <span class="removeBold">
                                        {{ @$auction->get->sqft_heated_source != '' ? @$auction->get->sqft_heated_source : '' }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->total_acreage != null && @$auction->get->total_acreage != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Total Acreage:
                                    <span class="removeBold">
                                        {{ @$auction->get->total_acreage != '' ? @$auction->get->total_acreage : '' }}</span>
                                </div>
                            @endif

                            @if (@$auction->get->appliances != null && @$auction->get->appliances != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Appliances Included:

                                    @foreach (@$auction->get->appliances as $appliance)
                                        <span class="removeBold badge bg-secondary">
                                            {{ @$appliance }}
                                        </span>
                                    @endforeach

                                </div>
                            @endif

                            @if (@$auction->get->tenant_require != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Furnishings
                                    Needed:
                                    <span class="removeBold">

                                        <span
                                            class="removeBold badge bg-secondary">{{ @$auction->get->tenant_require }}</span>

                                    </span>
                                </div>
                            @endif
                            @if (@$auction->get->carport_needed != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Carport Needed:
                                    <span class="removeBold">
                                        <span
                                            class="removeBold badge bg-secondary">{{ @$auction->get->carport_needed }}</span>

                                    </span>
                                </div>
                            @endif
                            @if (@$auction->get->other_carport_needed != '')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Number of Carport Spaces Needed:
                                    <span class="removeBold">
                                        <span
                                            class="removeBold badge bg-secondary">{{ @$auction->get->other_carport_needed }}</span>

                                    </span>
                                </div>
                            @endif
                            @if (@$auction->get->garage_needed != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Garage Needed:
                                    <span class="removeBold">
                                        <span
                                            class="removeBold badge bg-secondary">{{ @$auction->get->garage_needed }}</span>

                                    </span>
                                </div>
                            @endif
                            @if (@$auction->get->other_garage_needed != '')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Number of Garage Spaces Needed:
                                    <span class="removeBold">
                                        <span
                                            class="removeBold badge bg-secondary">{{ @$auction->get->other_garage_needed }}</span>

                                    </span>
                                </div>
                            @endif

                            @if (@$auction->get->pool_needed != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Pool
                                    Needed
                                    :<span class="removeBold">({{ @$auction->get->pool_needed }})</span>
                                    <span class="removeBold">
                                    </span>
                                </div>
                            @endif
                            @if (@$auction->get->view_preference != null || @$auction->get->other_preferences != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> View
                                    :
                                    @foreach (@$auction->get->view_preference as $item)
                                        <span class="removeBold badge bg-secondary">
                                            {{ @$item }}

                                        </span>
                                    @endforeach
                                    @if (@$auction->get->other_preferences)
                                        <span class="removeBold">({{ @$auction->get->other_preferences }})</span>
                                    @endif
                                </div>
                            @endif
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Age-Restricted Community:
                                <span class="removeBold">
                                    {{ @$auction->get->leasing_55_plus != '' ? @$auction->get->leasing_55_plus : '' }}</span>
                            </div>

                            @if (@$auction->get->non_negotiable_amenities != null || $auction->get->other_non_negotiable_amenities != null)

                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Amenities and Property Features

                                    @if (gettype(@$auction->get->non_negotiable_amenities) == 'array')
                                        @foreach (@$auction->get->non_negotiable_amenities as $item)
                                            <span class="removeBold badge bg-secondary">
                                                {{ @$item }}
                                            </span>
                                        @endforeach
                                    @elseif(@$auction->get->other_non_negotiable_amenities)
                                        <span
                                            class="removeBold">({{ @$auction->get->other_non_negotiable_amenities }})</span>
                                    @endif

                                </div>
                            @endif

                            @if (@$auction->get->pets)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Pets:
                                    <span class="removeBold">
                                        {{ @$auction->get->pets }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->pets == 'Yes')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Number of Pets:
                                    <span class="removeBold">
                                        {{ @$auction->get->number_of_pets != '' ? @$auction->get->number_of_pets : '' }}</span>
                                </div>
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Type
                                    of Pets:
                                    <span class="removeBold">
                                        {{ @$auction->get->type_of_pets != '' ? @$auction->get->type_of_pets : '' }}</span>
                                </div>

                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Weight of Pets (lbs):
                                    <span class="removeBold">
                                        {{ @$auction->get->weight_of_pets != '' ? @$auction->get->weight_of_pets : '' }}</span>
                                </div>
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Pet
                                    Restrictions:
                                    <span class="removeBold">
                                        {{ @$auction->get->breed_restrictions != '' ? @$auction->get->breed_restrictions : '' }}</span>
                                </div>
                            @endif

                        </div>
                        <hr>
                        <div class="card-header">
                            <h4>Leasing Terms: </h4>
                        </div>

                        @if (@$auction->get->occupant_status != '' && @$auction->get->occupant_status != 'null')
                            <div class="row" style="flex-wrap: wrap;">
                                <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Occupant Type:
                                    <span class="removeBold">{{ @$auction->get->occupant_status }}</span>
                                </div>
                            </div>
                        @endif
                        @if (@$auction->get->occupant_tenant != '' && @$auction->get->occupant_tenant != 'null')
                            <div class="row" style="flex-wrap: wrap;">
                                <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Occupied Until:
                                    <span class="removeBold">{{ @$auction->get->occupant_tenant }}</span>
                                </div>
                            </div>
                        @endif
                        @if (@$auction->get->leasing_spaces != '' && @$auction->get->leasing_spaces != 'null')
                            <div class="row" style="flex-wrap: wrap;">
                                <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Leasing Space:
                                    <span class="removeBold">{{ @$auction->get->leasing_spaces }}</span>
                                </div>
                            </div>
                        @endif

                        @if (@$auction->get->restrictions != '' && @$auction->get->restrictions != 'null')
                            <ul>
                                <li style="font-size: 16px;"><span class="fw-bold">Restrictions
                                        Include:</span>{{ $auction->get->restrictions }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->maintenance_by != '' && @$auction->get->maintenance_by != 'null')
                            <ul style="margin-top: -18px">
                                <li style="font-size: 16px;"><span class="fw-bold"> Maintenance and Repairs Are Handled
                                        By:</span>{{ $auction->get->maintenance_by }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->maintenance_response_time != '' && @$auction->get->maintenance_response_time != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold"> Maintenance Response
                                        Time:</span>{{ $auction->get->maintenance_response_time }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->storage_space_res_both != '' && @$auction->get->storage_space_res_both != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">Storage Space
                                        Size:</span>{{ $auction->get->storage_space_res_both }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->guests_allowed != '' && @$auction->get->guests_allowed != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">Guests
                                        are:</span>{{ $auction->get->guests_allowed }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->common_areas_access != '' && @$auction->get->common_areas_access != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">Shared Areas
                                        Available:</span>{{ $auction->get->common_areas_access }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->utilities != '' && @$auction->get->utilities != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span
                                        class="fw-bold">Utilities:</span>{{ $auction->get->utilities }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->common_areas_cleaning != '' && @$auction->get->common_areas_cleaning != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">Common Area
                                        Maintenance:</span>{{ $auction->get->common_areas_cleaning }}</li>
                            </ul>
                        @endif

                        @if (@$auction->get->storage_space_res_single != '' && @$auction->get->storage_space_res_single != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">Storage Space
                                        Size:</span>{{ $auction->get->storage_space_res_single }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->bathroom_facilities != '' && @$auction->get->bathroom_facilities != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">Bathroom
                                        Facilities:</span>{{ $auction->get->bathroom_facilities }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->room_size != '' && @$auction->get->room_size != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">Approximate Room
                                        Size:</span>{{ $auction->get->room_size }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->shared_amenities != '' && @$auction->get->shared_amenities != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">Shared Amenities
                                        Include:</span>{{ $auction->get->shared_amenities }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->building_hours != '' && @$auction->get->building_hours != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">Building
                                        Hours:</span>{{ $auction->get->building_hours }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->access_24_7 != '' && @$auction->get->access_24_7 != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">24/7 Access
                                        Available:</span>{{ $auction->get->access_24_7 }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->zoning_allows != '' && @$auction->get->zoning_allows != 'null')
                            <ul style="margin-top: -18px">

                                <li style="font-size: 16px;"><span class="fw-bold">Zoning
                                        Allows:</span>{{ $auction->get->zoning_allows }}</li>
                            </ul>
                        @endif

                        @php
                            $tenantPays = is_string($auction->get->tenant_pays)
                                ? json_decode($auction->get->tenant_pays, true)
                                : $auction->get->tenant_pays;

                            $ownerPays = is_string($auction->get->owner_pays)
                                ? json_decode($auction->get->owner_pays, true)
                                : $auction->get->owner_pays;

                            $termsOfLease = is_string($auction->get->terms_of_lease)
                                ? json_decode($auction->get->terms_of_lease, true)
                                : $auction->get->terms_of_lease;
                        @endphp

                        @if (!empty($tenantPays))
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                <i class="fa-regular fa-check-square"></i> Tenant Pays:
                                <ul>
                                    @foreach ($tenantPays as $tenant_pay)
                                        <li style="font-size: 16px;">{{ $tenant_pay }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (!empty($ownerPays))
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                <i class="fa-regular fa-check-square"></i> Owner Pays:
                                <ul>
                                    @foreach ($ownerPays as $owner_pay)
                                        <li style="font-size: 16px;">{{ $owner_pay }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (!empty($termsOfLease))
                            <div class="col-md-12 col-12 pt-2 fw-bold">
                                <i class="fa-regular fa-check-square"></i> Terms of Lease:
                                <ul>
                                    @foreach ($termsOfLease as $lease)
                                        <li style="font-size: 16px;">{{ $lease }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (@$auction->get->desired_rental_amount != '' && @$auction->get->desired_rental_amount != 'null')
                            <div class="row" style="flex-wrap: wrap;">
                                <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Desired Rental
                                    Amount:
                                    <span class="removeBold">${{ @$auction->get->desired_rental_amount }}</span>
                                </div>
                            </div>
                        @endif
                        @if (@$auction->get->lease_amount_frequency != '' && @$auction->get->lease_amount_frequency != 'null')
                            <div class="row" style="flex-wrap: wrap;">
                                <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Lease Amount
                                    Frequency:
                                    <span class="removeBold">{{ @$auction->get->lease_amount_frequency }}</span>
                                </div>
                            </div>
                        @endif

                        <hr>

                        <div class="card-header">
                            <h4>Services: </h4>
                        </div>

                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Services the
                            landlord Requests from Their Agent:

                            <ul>
                                @if (!empty($auction->get->services))
                                    @if (!empty($auction->get->services))
                                        @foreach ($auction->get->services as $service)
                                            <li style="font-size: 16px;">
                                                {{ $service }}
                                            </li>
                                        @endforeach
                                    @endif

                                @endif
                            </ul>

                        </div>

                        @if (@$auction->get->other_services != null)
                            <hr>

                            <div class="card-header">
                                <h4>Other Services: </h4>
                            </div>
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Other
                                Services
                                the
                                landlord Requests from Their Agent:
                                <ul>

                                    @if (!empty($auction->get->other_services))
                                        @foreach ($auction->get->other_services as $other_service)
                                            <li style="font-size: 16px;">
                                                {{ $other_service }}
                                            </li>
                                        @endforeach
                                    @endif
                                </ul>

                            </div>
                        @endif
                        <hr>
                        @if (@$auction->get->additional_details != null)
                            <div class="card-header">
                                <h4>Additional Details: </h4>
                            </div>

                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Additional Details:<span
                                    class="removeBold">{{ $auction->get->additional_details ?? '' }}</span>
                            </div>
                        @endif

                        <hr />
                        <div class="card-header">
                            <h4>Broker Compensation: </h4>
                        </div>

                        @if (@$auction->get->purchase_fee_type != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Landlord’s Broker Lease Fee:
                                <span class="removeBold">
                                    {{ $auction->get->purchase_fee_type ?? '' }}</span>
                            </div>
                        @endif

                        @if (@$auction->get->purchase_fee_type === 'Flat Fee' && @$auction->get->purchase_fee_flat_type != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->purchase_fee_flat_type }}</li>
                            </ul>
                        @endif
                        @if (
                            @$auction->get->purchase_fee_type === 'Percentage of the Rent Due Each Rental Period' &&
                                @$auction->get->purchase_fee_rental_period != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->purchase_fee_rental_period }}%</li>
                            </ul>
                        @endif
                        @if (
                            @$auction->get->purchase_fee_type === 'Percentage of the Gross Lease Value' &&
                                @$auction->get->purchase_fee_percentage_combo != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->purchase_fee_percentage_combo }}%</li>
                            </ul>
                        @endif
                        @if (
                            @$auction->get->purchase_fee_type === 'Percentage of the First Month’s Rent' &&
                                @$auction->get->purchase_fee_flat_combo != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->purchase_fee_flat_combo }} %</li>
                            </ul>
                        @endif
                        @if (
                            @$auction->get->purchase_fee_type === 'Percentage of the Net Aggregate Rent' &&
                                @$auction->get->purchase_fee_net_aggregate != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->purchase_fee_net_aggregate }} %</li>
                            </ul>
                        @endif
                        @if (
                            @$auction->get->purchase_fee_type === 'Percentage of the Gross Rent' &&
                                @$auction->get->purchase_fee_gross_rent != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->purchase_fee_gross_rent }} %</li>
                            </ul>
                        @endif
                        @if (
                            @$auction->get->purchase_fee_type === 'Percentage of the Gross Rent' &&
                                @$auction->get->sales_tax_option_gross != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->sales_tax_option_gross }} %</li>
                            </ul>
                        @endif
                        @if (
                            @$auction->get->purchase_fee_type === 'Percentage of Month’s Rent' &&
                                @$auction->get->purchase_fee_monthly_percentage != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->purchase_fee_monthly_percentage }} %</li>
                            </ul>
                        @endif
                        @if (@$auction->get->purchase_fee_type === 'Percentage of Month’s Rent' && @$auction->get->purchase_fee_months != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->purchase_fee_months }} %</li>
                            </ul>
                        @endif
                        @if (
                            @$auction->get->purchase_fee_type === 'Percentage of Month’s Rent' &&
                                @$auction->get->sales_tax_option_monthly != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->sales_tax_option_monthly }} %</li>
                            </ul>
                        @endif

                        @if (@$purchase_fee_type === 'other' && @$auction->get->purchase_fee_other != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->purchase_fee_other }}</li>
                            </ul>
                        @endif
                        @if (@$purchase_fee_type === 'other' && @$auction->get->purchase_fee_other_commercial != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->purchase_fee_other_commercial }}</li>
                            </ul>
                        @endif

                        @if (@$auction->get->protection_period != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Protection Period Timeframe (Days):
                                <span class="removeBold">
                                    {{ $auction->get->protection_period ?? '' }}</span>
                            </div>
                        @endif
                        @if (@$auction->get->early_termination_fee_option != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Early Termination Fee:
                                <span class="removeBold">
                                    {{ $auction->get->early_termination_fee_option ?? '' }}</span>
                            </div>
                        @endif
                        @if (@$auction->get->early_termination_fee_amount != null)
                            <ul>
                                <li style="font-size: 16px;">${{ $auction->get->early_termination_fee_amount }}</li>
                            </ul>
                        @endif

                        @if (@$auction->get->agency_agreement_timeframe != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Tenant Agency Agreement Timeframe:
                                <span class="removeBold">
                                    {{ $auction->get->agency_agreement_timeframe ?? '' }}</span>
                            </div>
                        @endif
                        @if (@$auction->get->agency_agreement_custom != null)
                            <ul>
                                <li style="font-size: 16px;">${{ $auction->get->agency_agreement_custom }}</li>
                            </ul>
                        @endif

                        @if (@$auction->get->brokerage_relationship != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Acceptable Brokerage Relationship:
                                <span class="removeBold">
                                    {{ $auction->get->brokerage_relationship ?? '' }}</span>
                            </div>
                        @endif

                        <hr />
                        <div class="card-header">
                            <h4>Landlord’s Info </h4>
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
                <h1>{{ @$auction->title }}</h1>
                <hr>
                @inject('carbon', 'Carbon\Carbon')
                @php
                    if (@$auction->get->auction_length_days > 0) {
                        $start = $carbon::now();
                        $end = $carbon::parse(@$auction->created_at)->addDays(@$auction->get->auction_length_days);
                        $diff = $end->diffInDays($start);
                    }
                @endphp
                @if (@$auction->get->auction_length_days > 0)
                    @php
                        $diff_d = $diff;
                        $diff_H = $start->diff($end)->format('%H');
                        $diff_I = $start->diff($end)->format('%I');
                        $diff_S = $start->diff($end)->format('%S');
                    @endphp
                    <div class="time d-flex justify-content-between text-center flex-wrap pb-2">
                        <div>
                            <h5><b class="timer-d"> {{ $diff_d }} </b></h5>
                            <h6 class="opacity-50">Days</h6>
                        </div>
                        <div>
                            <h5><b class="timer-h"> {{ $diff_H }} </b></h5>
                            <h6 class="opacity-50">Hrs</h6>
                        </div>
                        <div>
                            <h5><b class="timer-m"> {{ $diff_I }} </b></h5>
                            <h6 class="opacity-50">Mins</h6>
                        </div>
                        <div>
                            <h5><b class="timer-s"> {{ $diff_S }} </b></h5>
                            <h6 class="opacity-50">Secs</h6>
                        </div>
                    </div>
                @endif
                @php
                    $lowest_bid_price = @$auction->bids->min('brokerage') ?? @$auction->get->concession;
                    $lowest_bid_price =
                        $lowest_bid_price < @$auction->get->concession ? $lowest_bid_price : @$auction->get->concession;
                    $lowest_bidder = @$auction->bids->where('brokerage', $lowest_bid_price)->first();
                    $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
                @endphp
                @if (@$auction->user_id != $auth_id)
                    <a href="{{ route('auction-chat', ['tenant-agent', $auction->id]) }}"
                        class="btn btn-success w-100 mb-2">
                        <i class="fa-solid fa-paper-plane"></i> Send Message</a>
                @endif
                @if ($auth_id)
                    @if (in_array(auth()->user()->user_type, ['agent']))
                        <button class="btn w-100"
                            onclick="javascript:window.location='{{ route('agent.landlord.agent.auction.bid', @$auction->id) }}';"
                            {{-- {{ $my_bid || @$auction->user_id == $auth_id ? 'disabled' : '' }} --}}>
                            <span class="bid">Bid Now </span>
                            {{-- <span class="badge bg-light float-end text-dark">${{ @$auction->get->budget }}</span> --}}
                            @if (@$auction->sold)
                                <span class="badge bg-danger">Sold</span>
                            @endif
                        </button>
                    @endif
                @else
                    <a href="{{ route('login') }}">
                        <button class="btn w-100">
                            <span class="bid m-0">Login for Bid </span>
                            <span class="badge bg-light float-end text-dark">{{ @$auction->get->buyer_budget }}</span>
                        </button>
                    </a>
                @endif

                <div class="card higestBider">
                    <div class="card-body card-body-padding">
                        @if ($lowest_bidder)
                            <p><b>{{ $lowest_bidder->user->first_name ?? '' }}</b> is the last bidder.</p>
                        @else
                            <p>No one has bid on this auction.</p>
                        @endif
                        <div class="accordion" id="accordionExample">
                            <div class="accordion-item border-0">

                                @foreach (@$auction->bids as $bid)
                                    <!-- Item loop -->
                                    <div class="accordion" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#item{{ data_get($bid, 'id') }}" aria-expanded="true"
                                        aria-controls="item{{ data_get($bid, 'id') }}">
                                        <div class="d-flex small accordion mr-0 text-center">
                                            <div class="col-1">
                                                <span class="badge">{{ $loop->iteration }}</span>
                                            </div>
                                            <div class="col-4">
                                                {{ data_get($bid, 'user.first_name', '') }}
                                            </div>
                                            <div class="col-4 text-right">
                                                {{ data_get($bid, 'get.agent_fee', data_get($bid, 'agent_fee', '')) }}
                                            </div>
                                            <div class="col-2 d-flex">
                                                Terms↓
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

                                                    <!-- Services Offered -->
                                                    @php $servicesList = (array) data_get($bid,'get.services',[]); @endphp
                                                    @if (!empty($servicesList))
                                                        <div>
                                                            <label style="font-size: large;"> Services Offered:</label>
                                                            <ul class="services services-offered">
                                                                @foreach ($servicesList as $service)
                                                                    @if ($service == 'Other')
                                                                        @continue
                                                                    @endif
                                                                    <li class="alert-font"
                                                                        style="font-size: 16px; margin-top:15px;">
                                                                        {{ $service }}</li>
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
                                                                                data_get($bid, 'get.purchase_fee_type') ||
                                                                                data_get($bid, 'get.interested_lease_option_agreement') ||
                                                                                data_get($bid, 'get.protection_period') ||
                                                                                data_get($bid, 'get.early_termination_fee_option') ||
                                                                                data_get($bid, 'get.retainer_fee_option') ||
                                                                                data_get($bid, 'get.agency_agreement_timeframe') ||
                                                                                data_get($bid, 'get.brokerage_relationship') ||
                                                                                data_get($bid, 'get.interested_in_selling') ||
                                                                                data_get($bid, 'get.broker_fee_timing') ||
                                                                                data_get($bid, 'get.renewal_fee_type') ||
                                                                                data_get($bid, 'get.expansion_commission_percentage') ||
                                                                                data_get($bid, 'get.tenant_broker_commission_structure') ||
                                                                                data_get($bid, 'get.interested_in_property_management'))
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

                                                                                <!-- Purchase Fee Type (Landlord's Broker Lease Fee) -->
                                                                                @if (data_get($bid, 'get.purchase_fee_type'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Landlord's Broker Lease Fee
                                                                                        </div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.purchase_fee_type') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Residential Property Purchase Fee Amounts -->
                                                                                @if (data_get($bid, 'get.purchase_fee_type') === 'Flat Fee' && data_get($bid, 'get.purchase_fee_flat'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Flat
                                                                                            Fee Amount</div>
                                                                                        <div class="text-muted">
                                                                                            @if (data_get($bid, 'get.purchase_fee_flat_type') === '$')
                                                                                                ${{ number_format(data_get($bid, 'get.purchase_fee_flat'), 2) }}
                                                                                            @else
                                                                                                {{ data_get($bid, 'get.purchase_fee_flat') }}%
                                                                                            @endif
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the Rent Due Each Rental Period' &&
                                                                                        data_get($bid, 'get.purchase_fee_rental_period'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Percentage of Rent Due Each
                                                                                            Rental Period</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.purchase_fee_rental_period') }}%
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the Gross Lease Value' &&
                                                                                        data_get($bid, 'get.purchase_fee_percentage_combo'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Percentage of Gross Lease Value
                                                                                        </div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.purchase_fee_percentage_combo') }}%
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the First Month\'s Rent' &&
                                                                                        data_get($bid, 'get.purchase_fee_flat_combo'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Percentage of First Month's Rent
                                                                                        </div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.purchase_fee_flat_combo') }}%
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Commercial Property Purchase Fee Amounts -->
                                                                                @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the Net Aggregate Rent' &&
                                                                                        data_get($bid, 'get.purchase_fee_net_aggregate'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Percentage of Net Aggregate Rent
                                                                                        </div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.purchase_fee_net_aggregate') }}%
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of the Gross Rent' &&
                                                                                        data_get($bid, 'get.purchase_fee_gross_rent'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Percentage of Gross Rent</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.purchase_fee_gross_rent') }}%
                                                                                        </div>
                                                                                    </div>
                                                                                    @if (data_get($bid, 'get.sales_tax_option_gross'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Sales Tax</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.sales_tax_option_gross') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif
                                                                                @endif

                                                                                @if (data_get($bid, 'get.purchase_fee_type') === 'Percentage of Month\'s Rent' &&
                                                                                        data_get($bid, 'get.purchase_fee_monthly_percentage'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Percentage of Month's Rent</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.purchase_fee_monthly_percentage') }}%
                                                                                        </div>
                                                                                    </div>
                                                                                    @if (data_get($bid, 'get.purchase_fee_months'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Number of Months</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.purchase_fee_months') }}
                                                                                                months
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif
                                                                                    @if (data_get($bid, 'get.sales_tax_option_monthly'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Sales Tax</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.sales_tax_option_monthly') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif
                                                                                @endif

                                                                                @if (data_get($bid, 'get.purchase_fee_type') === 'Flat Fee' && data_get($bid, 'get.purchase_fee_flat_commercial'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Flat
                                                                                            Fee Amount</div>
                                                                                        <div class="text-muted">
                                                                                            ${{ number_format(data_get($bid, 'get.purchase_fee_flat_commercial'), 2) }}
                                                                                        </div>
                                                                                    </div>
                                                                                    @if (data_get($bid, 'get.sales_tax_option_flat'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Sales Tax</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.sales_tax_option_flat') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif
                                                                                @endif

                                                                                @if (data_get($bid, 'get.purchase_fee_type') === 'other' && data_get($bid, 'get.purchase_fee_other'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Other
                                                                                            Lease Fee</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.purchase_fee_other') }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                @if (data_get($bid, 'get.purchase_fee_type') === 'other' && data_get($bid, 'get.purchase_fee_other_commercial'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Other
                                                                                            Lease Fee</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.purchase_fee_other_commercial') }}
                                                                                        </div>
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
                                                                                                        ${{ number_format(data_get($bid, 'get.lease_value'), 2) }}
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
                                                                                                        ${{ number_format(data_get($bid, 'get.purchase_value'), 2) }}
                                                                                                    @endif
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Interested in Selling Section -->
                                                                                @if (data_get($bid, 'get.interested_in_selling') === 'Yes')
                                                                                    <div class="mt-4 pt-3 border-top">
                                                                                        <h6 class="mb-3"
                                                                                            style="color: #049399; font-weight: 600;">
                                                                                            Purchase Fee Details</h6>

                                                                                        @if (data_get($bid, 'get.interested_in_selling_type'))
                                                                                            <div class="mb-3">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Purchase Fee Type</div>
                                                                                                <div class="text-muted">
                                                                                                    {{ data_get($bid, 'get.interested_in_selling_type') }}
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif

                                                                                        @if (data_get($bid, 'get.interested_in_selling_type') === 'Percentage of the Total Purchase Price' &&
                                                                                                data_get($bid, 'get.landlord_broker_purchase_price'))
                                                                                            <div class="mb-3">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Purchase Percentage
                                                                                                </div>
                                                                                                <div class="text-muted">
                                                                                                    {{ data_get($bid, 'get.landlord_broker_purchase_price') }}%
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif

                                                                                        @if (data_get($bid, 'get.interested_in_selling_type') === 'Percentage of the Total Purchase Price + Flat Fee')
                                                                                            @if (data_get($bid, 'get.landlord_broker_percentage_price'))
                                                                                                <div class="mb-2">
                                                                                                    <div class="fw-semibold"
                                                                                                        style="color: #049399;">
                                                                                                        Purchase Percentage
                                                                                                    </div>
                                                                                                    <div
                                                                                                        class="text-muted">
                                                                                                        {{ data_get($bid, 'get.landlord_broker_percentage_price') }}%
                                                                                                    </div>
                                                                                                </div>
                                                                                            @endif
                                                                                            @if (data_get($bid, 'get.landlord_broker_dollar_price'))
                                                                                                <div class="mb-3">
                                                                                                    <div class="fw-semibold"
                                                                                                        style="color: #049399;">
                                                                                                        Purchase Flat Fee
                                                                                                    </div>
                                                                                                    <div
                                                                                                        class="text-muted">
                                                                                                        ${{ number_format(data_get($bid, 'get.landlord_broker_dollar_price'), 2) }}
                                                                                                    </div>
                                                                                                </div>
                                                                                            @endif
                                                                                        @endif

                                                                                        @if (data_get($bid, 'get.interested_in_selling_type') === 'Flat Fee' && data_get($bid, 'get.landlord_broker_flate_fee'))
                                                                                            <div class="mb-3">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Purchase Flat Fee</div>
                                                                                                <div class="text-muted">
                                                                                                    @if (data_get($bid, 'get.lease_fee_flat_type') === '$')
                                                                                                        ${{ number_format(data_get($bid, 'get.landlord_broker_flate_fee'), 2) }}
                                                                                                    @else
                                                                                                        {{ data_get($bid, 'get.landlord_broker_flate_fee') }}%
                                                                                                    @endif
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif

                                                                                        @if (data_get($bid, 'get.interested_in_selling_type') === 'Other' && data_get($bid, 'get.landlord_broker_other'))
                                                                                            <div class="mb-3">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Other Purchase Fee</div>
                                                                                                <div class="text-muted">
                                                                                                    {{ data_get($bid, 'get.landlord_broker_other') }}
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Payment Timing for Broker Fees -->
                                                                                @if (data_get($bid, 'get.broker_fee_timing'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Payment
                                                                                            Timing for Broker Fees</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.broker_fee_timing') }}
                                                                                        </div>
                                                                                    </div>

                                                                                    @if (data_get($bid, 'get.broker_fee_timing') === 'from_rent' && data_get($bid, 'get.broker_fee_days_from_rent'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Calendar Days to Pay Balance
                                                                                            </div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.broker_fee_days_from_rent') }}
                                                                                                days
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.broker_fee_timing') === 'after_lease' && data_get($bid, 'get.broker_fee_days_after_lease'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Calendar Days to Pay After
                                                                                                Executed Lease</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.broker_fee_days_after_lease') }}
                                                                                                days
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.broker_fee_timing') === 'after_rent' && data_get($bid, 'get.broker_fee_days_after_rent'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Calendar Days to Pay After
                                                                                                Tenant Rent Payment</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.broker_fee_days_after_rent') }}
                                                                                                days
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.broker_fee_timing') === 'Other' && data_get($bid, 'get.broker_fee_timing_other'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Custom Payment Arrangement
                                                                                            </div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.broker_fee_timing_other') }}
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.broker_fee_timing') === '50% due upon execution, 50% due upon commencement of agreement' &&
                                                                                            data_get($bid, 'get.broker_fee_days_after_due_event'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Calendar Days to Pay Second
                                                                                                Installment</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.broker_fee_days_after_due_event') }}
                                                                                                days
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif
                                                                                @endif

                                                                                <!-- Lease Renewal/Extension Fee -->
                                                                                @if (data_get($bid, 'get.renewal_fee_type'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">Lease
                                                                                            Renewal/Extension Fee</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.renewal_fee_type') }}
                                                                                        </div>
                                                                                    </div>

                                                                                    <!-- Residential Renewal Fees -->
                                                                                    @if (data_get($bid, 'get.renewal_fee_type') === 'Percentage of the Rent Due Each Rental Period' &&
                                                                                            data_get($bid, 'get.renewal_fee_percentage'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Percentage of Rent Due Each
                                                                                                Rental Period</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.renewal_fee_percentage') }}%
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.renewal_fee_type') === 'Percentage of the Gross Lease Value' &&
                                                                                            data_get($bid, 'get.renewal_fee_lease_value'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Percentage of Gross Lease
                                                                                                Value</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.renewal_fee_lease_value') }}%
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.renewal_fee_type') === 'Percentage of the First Month\'s Rent' &&
                                                                                            data_get($bid, 'get.renewal_fee_first_month'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Percentage of First Month's
                                                                                                Rent</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.renewal_fee_first_month') }}%
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.renewal_fee_type') === 'Flat Fee' && data_get($bid, 'get.renewal_fee_flat_free'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Flat Fee Amount</div>
                                                                                            <div class="text-muted">
                                                                                                ${{ number_format(data_get($bid, 'get.renewal_fee_flat_free'), 2) }}
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    <!-- Commercial Renewal Fees -->
                                                                                    @if (data_get($bid, 'get.renewal_fee_type') === 'Percentage of the Net Aggregate Rent' &&
                                                                                            data_get($bid, 'get.renewal_fee_percentage'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Percentage of Net Aggregate
                                                                                                Rent</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.renewal_fee_percentage') }}%
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.renewal_fee_type') === 'Percentage of the Gross Rent' &&
                                                                                            data_get($bid, 'get.renewal_fee_lease_value'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Percentage of Gross Rent
                                                                                            </div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.renewal_fee_lease_value') }}%
                                                                                            </div>
                                                                                        </div>
                                                                                        @if (data_get($bid, 'get.renewal_fee_sales_tax_lease_value'))
                                                                                            <div class="mb-2">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Sales Tax</div>
                                                                                                <div class="text-muted">
                                                                                                    {{ data_get($bid, 'get.renewal_fee_sales_tax_lease_value') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.renewal_fee_type') === 'Percentage of Month\'s Rent' &&
                                                                                            data_get($bid, 'get.renewal_fee_first_month'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Percentage of Month's Rent
                                                                                            </div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.renewal_fee_first_month') }}%
                                                                                            </div>
                                                                                        </div>
                                                                                        @if (data_get($bid, 'get.renewal_fee_no_of_months'))
                                                                                            <div class="mb-2">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Number of Months</div>
                                                                                                <div class="text-muted">
                                                                                                    {{ data_get($bid, 'get.renewal_fee_no_of_months') }}
                                                                                                    months
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif
                                                                                        @if (data_get($bid, 'get.renewal_fee_sales_tax_first_month'))
                                                                                            <div class="mb-2">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Sales Tax</div>
                                                                                                <div class="text-muted">
                                                                                                    {{ data_get($bid, 'get.renewal_fee_sales_tax_first_month') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.renewal_fee_type') === 'Flat Fee' && data_get($bid, 'get.renewal_fee_flat_free'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Flat Fee Amount</div>
                                                                                            <div class="text-muted">
                                                                                                ${{ number_format(data_get($bid, 'get.renewal_fee_flat_free'), 2) }}
                                                                                            </div>
                                                                                        </div>
                                                                                        @if (data_get($bid, 'get.renewal_fee_sales_tax_flat_fee'))
                                                                                            <div class="mb-2">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Sales Tax</div>
                                                                                                <div class="text-muted">
                                                                                                    {{ data_get($bid, 'get.renewal_fee_sales_tax_flat_fee') === 'including' ? 'Including Sales Tax' : 'Excluding Sales Tax' }}
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.renewal_fee_type') === 'other' && data_get($bid, 'get.renewal_fee_custom'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Custom Renewal Fee</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.renewal_fee_custom') }}
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif
                                                                                @endif

                                                                                <!-- Expansion Commission for Lease Amendment (Commercial only) -->
                                                                                @if (data_get($bid, 'get.expansion_commission_percentage'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Expansion Commission for Lease
                                                                                            Amendment</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.expansion_commission_percentage') }}%
                                                                                            of original commission
                                                                                        </div>
                                                                                    </div>
                                                                                @endif

                                                                                <!-- Tenant's Broker Commission Structure -->
                                                                                @if (data_get($bid, 'get.tenant_broker_commission_structure'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Tenant's Broker Commission Fee
                                                                                        </div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.tenant_broker_commission_structure') }}
                                                                                        </div>
                                                                                    </div>

                                                                                    @if (data_get($bid, 'get.tenant_broker_fee_structure'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Tenant's Broker Commission
                                                                                                Fee Structure</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.tenant_broker_fee_structure') }}
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.tenant_broker_percentage'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Percentage of Rent Due Each
                                                                                                Rental Period</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.tenant_broker_percentage') }}%
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.tenant_broker_gross_lease'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Percentage of Gross Lease
                                                                                                Value</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.tenant_broker_gross_lease') }}%
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.tenant_broker_first_month_rent'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Percentage of First Month's
                                                                                                Rent</div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.tenant_broker_first_month_rent') }}%
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.tenant_broker_flat_fee'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Flat Fee Amount</div>
                                                                                            <div class="text-muted">
                                                                                                ${{ number_format(data_get($bid, 'get.tenant_broker_flat_fee'), 2) }}
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif

                                                                                    @if (data_get($bid, 'get.tenant_broker_other'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Other Commission Arrangement
                                                                                            </div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.tenant_broker_other') }}
                                                                                            </div>
                                                                                        </div>
                                                                                    @endif
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
                                                                                            {{ data_get($bid, 'get.early_termination_fee_option') === 'yes' ? 'Yes' : 'No' }}
                                                                                        </div>
                                                                                    </div>

                                                                                    @if (data_get($bid, 'get.early_termination_fee_option') === 'yes' && data_get($bid, 'get.early_termination_fee_amount'))
                                                                                        <div class="mb-3">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Termination Fee Amount</div>
                                                                                            <div class="text-muted">
                                                                                                ${{ number_format(data_get($bid, 'get.early_termination_fee_amount'), 2) }}
                                                                                            </div>
                                                                                        </div>
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

                                                                                <!-- Property Management -->
                                                                                @if (data_get($bid, 'get.interested_in_property_management'))
                                                                                    <div class="mb-3">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Interested in Property
                                                                                            Management</div>
                                                                                        <div class="text-muted">
                                                                                            {{ data_get($bid, 'get.interested_in_property_management') === 'yes' ? 'Yes' : 'No' }}
                                                                                        </div>
                                                                                    </div>

                                                                                    @if (data_get($bid, 'get.interested_in_property_management') === 'yes' &&
                                                                                            data_get($bid, 'get.interested_in_property_management_fee'))
                                                                                        <div class="mb-2">
                                                                                            <div class="fw-semibold"
                                                                                                style="color: #049399;">
                                                                                                Property Management Fee Type
                                                                                            </div>
                                                                                            <div class="text-muted">
                                                                                                {{ data_get($bid, 'get.interested_in_property_management_fee') }}
                                                                                            </div>
                                                                                        </div>

                                                                                        @if (data_get($bid, 'get.interested_in_property_management_fee') === 'Percentage of the Gross Lease Value' &&
                                                                                                data_get($bid, 'get.interested_in_property_management_fee_gross_lease'))
                                                                                            <div class="mb-2">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Percentage of Gross
                                                                                                    Lease Value</div>
                                                                                                <div class="text-muted">
                                                                                                    {{ data_get($bid, 'get.interested_in_property_management_fee_gross_lease') }}%
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif

                                                                                        @if (data_get($bid, 'get.interested_in_property_management_fee') === 'Percentage of the Rent Due Each Rental Period' &&
                                                                                                data_get($bid, 'get.interested_in_property_management_fee_rental_periord'))
                                                                                            <div class="mb-2">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Percentage of Rent Due
                                                                                                    Each Rental Period</div>
                                                                                                <div class="text-muted">
                                                                                                    {{ data_get($bid, 'get.interested_in_property_management_fee_rental_periord') }}%
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif

                                                                                        @if (data_get($bid, 'get.interested_in_property_management_fee') === 'Flat Fee' &&
                                                                                                data_get($bid, 'get.interested_in_property_management_fee_flate_free'))
                                                                                            <div class="mb-2">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Flat Fee Amount</div>
                                                                                                <div class="text-muted">
                                                                                                    ${{ number_format(data_get($bid, 'get.interested_in_property_management_fee_flate_free'), 2) }}
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif

                                                                                        @if (data_get($bid, 'get.interested_in_property_management_fee') === 'Other' &&
                                                                                                data_get($bid, 'get.interested_in_property_management_fee_other'))
                                                                                            <div class="mb-2">
                                                                                                <div class="fw-semibold"
                                                                                                    style="color: #049399;">
                                                                                                    Other Property
                                                                                                    Management Fee</div>
                                                                                                <div class="text-muted">
                                                                                                    {{ data_get($bid, 'get.interested_in_property_management_fee_other') }}
                                                                                                </div>
                                                                                            </div>
                                                                                        @endif
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
                                                        $counterBids = \App\Models\LandlordCounterBidding::with(
                                                            'meta',
                                                            'user',
                                                        )
                                                            ->where('landlord_agent_auction_id', data_get($bid, 'id'))
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

                                                    @if ($counterBids->count() > 0)
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
                                                                            @if (!empty($allMeta['commission_structure']))
                                                                                <div class="mb-2"><strong>Commission
                                                                                        Structure:</strong>
                                                                                    {{ $allMeta['commission_structure'] }}
                                                                                </div>
                                                                            @endif
                                                                            @if (!empty($allMeta['lease_fee_type']))
                                                                                <div class="mb-2"><strong>Lease Fee
                                                                                        Type:</strong>
                                                                                    {{ $allMeta['lease_fee_type'] }}</div>
                                                                            @endif
                                                                            @if (!empty($allMeta['lease_fee_flat']))
                                                                                <div class="mb-2"><strong>Lease Flat
                                                                                        Fee:</strong>
                                                                                    ${{ number_format($allMeta['lease_fee_flat'], 2) }}
                                                                                </div>
                                                                            @endif
                                                                            @if (!empty($allMeta['lease_fee_percentage']))
                                                                                <div class="mb-2"><strong>Lease
                                                                                        Percentage:</strong>
                                                                                    {{ $allMeta['lease_fee_percentage'] }}%
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
                                                                                                style="background: #f1f5f9; color: #111; padding: 6px 12px;
                                                                                                    border-radius: 8px; font-size: 12px; border: 1px solid #ddd;">
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
                                                                                                style="background: #f1f5f9; color: #111; padding: 6px 12px;
                                                                                                    border-radius: 8px; font-size: 12px; border: 1px solid #ddd;">
                                                                                                {{ $other_service }}
                                                                                            </span>
                                                                                        @endforeach
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- Counter actions (only when both pending & viewer is the other party) -->
                                                                            @if ($showCounterActions)
                                                                                <div
                                                                                    class="counter-response-buttons mt-3 pt-3 border-top">
                                                                                    <h6>Respond to this Counter Offer:</h6>
                                                                                    <div
                                                                                        class="d-flex gap-3 flex-wrap justify-content-between">
                                                                                        <form class="d-inline"
                                                                                            action="{{ route('landlord.hire.agent.auction.counter.bid.accept') }}"
                                                                                            method="post">
                                                                                            @csrf
                                                                                            <input type="hidden"
                                                                                                name="auction_id"
                                                                                                value="{{ data_get($auction, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="bid_id"
                                                                                                value="{{ data_get($bid, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="counter_bid_id"
                                                                                                value="{{ data_get($counterBid, 'id') }}">
                                                                                            <button type="submit"
                                                                                                class="btn-custom btn-accept"
                                                                                                style="font-size:16px">Accept</button>
                                                                                        </form>

                                                                                        <form class="d-inline"
                                                                                            action="{{ route('landlord.hire.agent.auction.counter.bid.reject') }}"
                                                                                            method="post">
                                                                                            @csrf
                                                                                            <input type="hidden"
                                                                                                name="auction_id"
                                                                                                value="{{ data_get($auction, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="bid_id"
                                                                                                value="{{ data_get($bid, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="counter_bid_id"
                                                                                                value="{{ data_get($counterBid, 'id') }}">
                                                                                            <button type="submit"
                                                                                                class="btn-custom btn-reject"
                                                                                                style="font-size:16px">Reject</button>
                                                                                        </form>

                                                                                        <form class="d-inline"
                                                                                            action="{{ route('landlord.agent.add.counter-bid') }}"
                                                                                            method="post">
                                                                                            @csrf
                                                                                            <input type="hidden"
                                                                                                name="auction_id"
                                                                                                value="{{ data_get($auction, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="bid_id"
                                                                                                value="{{ data_get($bid, 'id') }}">
                                                                                            <input type="hidden"
                                                                                                name="counter_bid_id"
                                                                                                value="{{ data_get($counterBid, 'id') }}">
                                                                                            <button type="submit"
                                                                                                class="btn-custom btn-counter"
                                                                                                style="font-size:16px">Counter</button>
                                                                                        </form>
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            <!-- ===== Counter footer status (names for OTHER PARTY only) ===== -->
                                                                            <div class="mt-3 pt-3 border-top">
                                                                                @if ($counterState === 'accepted')
                                                                                    @if (Auth::id() == $actorUserId)
                                                                                        {{-- Actor sees simple message --}}
                                                                                        <div
                                                                                            class="alert alert-success  mb-0 py-1 small">
                                                                                            ✅
                                                                                            This counter bid has been
                                                                                            accepted.
                                                                                        </div>
                                                                                    @else
                                                                                        {{-- Other party (creator) sees actor name --}}
                                                                                        <div
                                                                                            class="alert alert-success mb-0 py-1 small">
                                                                                            ✅
                                                                                            {{ trim($actorFirst . ' ' . $actorLast) }}
                                                                                            accepted the counter bid.</div>
                                                                                    @endif
                                                                                @elseif ($counterState === 'rejected')
                                                                                    @if (Auth::id() == $actorUserId)
                                                                                        <div
                                                                                            class="alert alert-danger mb-0 py-1 small">
                                                                                            ❌ This
                                                                                            counter bid has been rejected.
                                                                                        </div>
                                                                                    @else
                                                                                        <div
                                                                                            class="alert alert-danger mb-0 py-1 alert-font">
                                                                                            ❌
                                                                                            {{ trim($actorFirst . ' ' . $actorLast) }}
                                                                                            rejected the counter bid.</div>
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
                                                                            <!-- ===== /Counter footer status ===== -->
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif



                                                    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                                                        {{-- When main bid pending, OWNER can Accept/Reject/Counter --}}
                                                        @if ($state === '0' && $isOwnerRow && !data_get($auction, 'is_sold'))
                                                            <div class="biding-btn">
                                                                <form
                                                                    action="{{ route('landlord.hire.agent.auction.bid.accept') }}"
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
                                                                    action="{{ route('landlord.hire.agent.auction.bid.reject') }}"
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
                                                                    action="{{ route('landlord.agent.add.counter-bid') }}"
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

                                                        <!-- ===== Main bid footer status (names for OTHER PARTY only) ===== -->
                                                        @if ($state === 'accepted')
                                                            @if (Auth::id() == $ownerId)
                                                                {{-- Owner (actor) sees simple message --}}
                                                                <div
                                                                    class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                                                    ✅ This bid
                                                                    has been accepted.</div>
                                                            @else
                                                                {{-- Agent (other party) sees owner's name --}}
                                                                <div
                                                                    class="alert alert-success mt-2 w-100 mb-0 py-1 small">
                                                                    ✅
                                                                    {{ trim($ownerFirst . ' ' . $ownerLast) }} accepted
                                                                    the
                                                                    bid.</div>
                                                            @endif
                                                        @elseif ($state === 'rejected')
                                                            @if (Auth::id() == $ownerId)
                                                                <div class="alert alert-danger mt-2 w-100 mb-0 py-1 small">
                                                                    ❌ This bid
                                                                    has been rejected.</div>
                                                            @else
                                                                <div class="alert alert-danger mt-2 w-100 mb-0 py-1 small">
                                                                    ❌
                                                                    {{ trim($ownerFirst . ' ' . $ownerLast) }} rejected
                                                                    the
                                                                    bid.</div>
                                                            @endif
                                                        @elseif ($state === '0')
                                                            @if (data_get($bid, 'user_id') == Auth::id())
                                                                <div
                                                                    class="alert alert-secondary mt-2 w-100 mb-0 py-1 small">
                                                                    ⏳
                                                                    Waiting for response from
                                                                    {{ trim($ownerFirst . ' ' . $ownerLast) }}...</div>
                                                            @else
                                                                <div class="alert alert-light mt-2 w-100 mb-0 py-1 small">⏳
                                                                    Bid from
                                                                    {{ trim($agentFirst . ' ' . $agentLast) }} is pending.
                                                                </div>
                                                            @endif
                                                        @endif
                                                        <!-- ===== /Main bid footer status ===== -->
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
    <!-- Recommmended Section  -->
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
                                    data-bs-trigger="hover focus" data-bs-placement="top"
                                    data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
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

    <!-- Modal -->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('saveSABid') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                    <div class="modal-body">
                        <div class="d-flex justify-content-between mb-2">
                            <div style="font-size:18px; font-weight:bold;">
                                ${{ number_format(@$auction->min_price, 2, '.', ',') }}</div>
                            <div style="color: rgba(0,0,0,0.5);">Price Now</div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row form-group">
                                    <div class="col-md-6">
                                        <label>Full Name: <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" required
                                            value="{{ old('name') }}" />
                                        @if ($errors->has('name'))
                                            <div class="small error">{{ $errors->first('name') }}</div>
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <label>Phone Number: <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="phone"
                                            value="{{ old('phone') }}" required />
                                        @if ($errors->has('phone'))
                                            <div class="small error">{{ $errors->first('phone') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="row form-group">
                                    <div class="col-md-6">
                                        <label>Email: <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="email"
                                            value="{{ old('email') }}" required />
                                        @if ($errors->has('email'))
                                            <div class="small error">{{ $errors->first('email') }}</div>
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <label>Brokerage: <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="brokerage" id="brokerage"
                                            required value="{{ old('brokerage') }}">
                                        {{-- </div> --}}
                                        @if ($errors->has('brokerage'))
                                            <div class="small error">{{ $errors->first('brokerage') }}</div>
                                        @endif
                                    </div>
                                    <div class="col-md-6 d-none">
                                        <label>MLS ID: <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="mls_id"
                                            value="{{ old('mls_id') }}" />
                                        @if ($errors->has('mls_id'))
                                            <div class="small error">{{ $errors->first('mls_id') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="row form-group">
                                    <div class="col-md-6">
                                        <label>Real Estate License #: <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="license_no" required
                                            value="{{ old('license_no') }}" />
                                        @if ($errors->has('license_no'))
                                            <div class="small error">{{ $errors->first('license_no') }}</div>
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <label>Commission Offered %: <span class="text-danger">*</span></label>
                                        <div class="d-flex align-items-baseline">
                                            <i class="fa fa-percent"></i>
                                            <input type="number" class="form-control border-start-0"
                                                name="price_percent" id="price_percent" min="0"
                                                max="100" required placeholder="0.00"
                                                value="{{ old('price_percent') }}">
                                        </div>
                                        @if ($errors->has('price_percent'))
                                            <div class="small error">{{ $errors->first('price_percent') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="row form-group d-none">
                                    <div class="col-md-6">
                                        <label>County the agent is in:</label>
                                        <select class="form-control" name="county_id">
                                            @foreach ($counties as $county)
                                                <option value="{{ $county->id }}"
                                                    {{ old('county_id') == $county->id ? 'selected' : '' }}>
                                                    {{ $county->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Offering Price $: <span class="text-danger">*</span></label>
                                        <div class="d-flex align-items-baseline">
                                            <i class="fa fa-dollar"></i>
                                            <input type="number" class="form-control border-start-0" name="price"
                                                id="price" min="0" placeholder="0.00"
                                                value="{{ old('price') }}">
                                        </div>
                                        @if ($errors->has('price'))
                                            <div class="small error">{{ $errors->first('price') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="row form-group">
                                    <div class="col-md-6">
                                        <label>Reviews link:</label>
                                        <input type="text" class="form-control" name="reviews_link"
                                            value="{{ old('reviews_link') }}" />
                                    </div>
                                    <div class="col-md-6">
                                        <label>Website link:</label>
                                        <input type="text" class="form-control" name="website_link"
                                            value="{{ old('website_link') }}" />
                                    </div>
                                </div>
                                <div class="row form-group">
                                    <div class="col-md-12 mb-2">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th colspan="2" class="text-center">Social Media Platforms</th>
                                                </tr>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Link</th>
                                                </tr>
                                            </thead>
                                            <tbody class="social-links">
                                                <tr>
                                                    <td>
                                                        <select name="socialType[]" class="form-select">
                                                            <option value="Facebook">Facebook</option>
                                                            <option value="YouTube">YouTube</option>
                                                            <option value="LinkedIn">LinkedIn</option>
                                                            <option value="Twitter">Twitter</option>
                                                            <option value="Instagram">Instagram</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="social_link[]"
                                                            class="form-control">
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="2" class="text-right">
                                                        <a class="btn btn-primary add-row">Add New Row</a>
                                                    </th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Any details agent wants to share with the seller (Why should the Seller pick
                                        you
                                        as their agent):</label>
                                    <textarea class="form-control" name="additional_details">{{ old('additional_details') }}</textarea>
                                </div>
                                <div class="form-group">
                                    <label>Contract Listing Terms (Time amount for listing agreement):</label>
                                    <textarea class="form-control" name="listing_terms">{{ old('listing_terms') }}</textarea>
                                </div>
                                <div class="form-group d-none">
                                    <label>Why should the Seller pick you as their agent?</label>
                                    <textarea class="form-control" name="why_seller_pick_me">{{ old('why_seller_pick_me') }}</textarea>
                                </div>
                                <div class="form-group">
                                    <label>Services offered to Seller: </label>
                                    <textarea class="form-control" name="services_description">{{ old('services_description') }}</textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group d-none">
                                    <div>
                                        <label>Video:</label>
                                        <div class="d-flex align-items-baseline">
                                            <input type="file" class="form-control" name="video">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-12 mb-2">
                                    <label>Video URL: (Virtual Listing Presentation)</label>
                                    <div class="d-flex align-items-baseline">
                                        <input type="text" class="form-control" name="video_url">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Audio:</label>
                                    <div class="d-flex align-items-baseline">
                                        <input type="file" class="form-control" name="audio">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Note:</label>
                                    <div class="d-flex align-items-baseline">
                                        <input type="file" class="form-control" name="note">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Comparative Market Analysis:</label>
                                    <div class="d-flex align-items-baseline">
                                        <input type="file" class="form-control" name="market_analysis">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <button type="submit" class="btn btn-secondary">Bid Now</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
    <!-- Toastr JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>

    <script>
        // Toastr Options
        toastr.options = {
            "closeButton": true,
            "positionClass": "toast-top-center",
            "preventDuplicates": true,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": 0,
            "extendedTimeOut": 0,
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut",
            "toastClass": "custom-toast"
        };

        // Function to display the Toastr toast notification and confirm before form submission
        function showToast() {
            // Define custom HTML content for the toast message with "Yes" and "No" buttons
            var toastContent =
                '<div><span>Are you sure you want to reject this bid?</span><br><br>' +
                '<div class="d-flex justify-content-between"><button type="button" class="btn btn-danger rounded" onclick="rejectBid()">Confirm</button>' +
                '<button type="button" class="btn btn-secondary border-radius-3" onclick="toastr.clear()">Cancel</button></div></div>';

            // Display custom Toastr notification with HTML content
            toastr.clear(); // Clear any existing toastr notifications
            toastr.info(toastContent, '', {
                closeButton: true,
                timeOut: 0,
                extendedTimeOut: 0
            });
        }

        // Function to handle "Yes" button click
        function rejectBid() {
            // Submit the form or perform any other action
            $('#deleteForm').submit();
        }
    </script>
    {{--
    @if ($auction->get->auction_length_days > 0)
        <script>
            var durations = '{{ $diff_d }}d{{ $diff_H }}h{{ $diff_I }}m{{ $diff_S }}s';
            $('.timer-d').timer({
                countdown: true,
                duration: durations,
                format: '%d'
            });
            $('.timer-h').timer({
                countdown: true,
                duration: durations,
                format: '%h'
            });
            $('.timer-m').timer({
                countdown: true,
                duration: durations,
                format: '%m'
            });
            $('.timer-s').timer({
                countdown: true,
                duration: durations,
                format: '%s'
            });
        </script>
    @endif --}}
    <script>
        $(document).ready(function() {
            const auctionEnded = {{ $auction->auction_ended }};
            let createdAt = new Date("{{ $auction->created_at }}").getTime();
            let auctionLength = {{ $auction->auction_length }} * 24 * 60 * 60 * 1000;
            let auctionEndTime = createdAt + auctionLength;

            console.log('time', {
                createdAt,
                auctionLength,
                auctionEndTime
            });

            if (auctionEnded) return;

            console.log('auction_continues');

            function checkAndEndAuction() {
                let now = new Date().getTime();
                if (now >= auctionEndTime) {
                    $('#countdown').html("Auction Ended");
                    endAuctionAutomatically();
                }
            }

            function endAuctionAutomatically() {
                $.ajax({
                    url: "/hire/agent/auction/end/{{ $auction->id }}",
                    type: "POST",
                    headers: {
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        alert(response.message);
                        location.reload();
                    },
                    error: function(xhr) {
                        console.error("Error:", xhr.responseText);
                    }
                });
            }


            checkAndEndAuction();
        })
    </script>
    <script src="{{ asset('js/lightbox.js') }}"></script>
    <script>
        import Lightbox from "bs5-lightbox";
        const options = {
            keyboard: true,
            size: "fullscreen",
        };

        document.querySelectorAll(".my-lightbox-toggle").forEach((el) =>
            el.addEventListener("click", (e) => {
                e.preventDefault();
                const lightbox = new Lightbox(el, options);
                lightbox.show();
            })
        );
        $(function() {
            $('.add-row').on('click', function() {
                var socialRow =
                    `<tr>
        <td>
            <select name="socialType[]" class="form-select">
                <option value="Facebook">Facebook</option>
                <option value="YouTube">YouTube</option>
                <option value="LinkedIn">LinkedIn</option>
                <option value="Twitter">Twitter</option>
                <option value="Instagram">Instagram</option>
            </select>
        </td>
        <td>
            <input type="text" name="social_link[]" class="form-control">
        </td>
    </tr>`;
                $('.social-links').append(socialRow);
            });
        });
    </script>
@endpush
