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
    @php
        $auth_id = auth()->user() ? auth()->user()->id : 0;
    @endphp
    <!-- Gallery Start Here  -->
    <div class="container listingDescription">
        <div class="row">
            <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
                <div class="card description">
                    <div class="card-header">
                        <h4>Listing Details: </h5>
                    </div>
                    <div class="card-body">
                        <div class="row" style="flex-wrap: wrap;">
                            @if (@$auction->get->listing_title != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Listing Title
                                    <span class="removeBold">{{ @$auction->get->listing_title }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->working_with_agent != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Current Representation Status with Broker?
                                    <span class="removeBold">{{ @$auction->get->working_with_agent }}</span>
                                </div>
                            @endif


                            @if (@$auction->get->desired_agent_hire_date != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Desired Agent Hire Date:
                                    <span class="removeBold">{{ @$auction->get->desired_agent_hire_date }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->listing_date != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Listing Date:
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

                            @if (@$auction->get->county != null)
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Need a
                                    tenant’s agent for a property:
                                    @if (gettype(@$auction->get->cities) == 'array')
                                        @foreach (@$auction->get->cities as $item)
                                            <span class="removeBold">{{ $item }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endif
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Acceptable
                                Property
                                Styles :<span class="removeBold">({{ @$auction->get->property_type }})</span><br>
                                @if (gettype(@$auction->get->property_items) == 'array')
                                    @foreach (@$auction->get->property_items as $item)
                                        <span class="removeBold badge bg-secondary">{{ $item }}</span>
                                    @endforeach
                                @endif
                            </div>
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Acceptable
                                Property
                                Conditions:
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
                            @if (
                                @$auction->get->garageOptions != null &&
                                    @$auction->get->garageOptions != 'null' &&
                                    @$auction->get->garageOptions != '')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Garage
                                    Needed<span class="removeBold">({{ @$auction->get->garageOptions }})</span>:
                                    @if (@$auction->get->garageOptions == 'Yes' || @$auction->get->garageOptions == 'Optional')
                                        <span class="removeBold"> {{ @$auction->get->custom_garage }}</span>
                                    @else
                                        <span class="removeBold"> {{ @$auction->get->garageOptions }}</span>
                                    @endif
                                </div>
                            @endif
                            @if (
                                @$auction->get->carportOptions != null &&
                                    @$auction->get->carportOptions != 'null' &&
                                    @$auction->get->carportOptions != '')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Carport Needed<span class="removeBold">({{ @$auction->get->carportOptions }})</span>:
                                    @if (@$auction->get->carportOptions == 'Yes')
                                        <span class="removeBold"> {{ @$auction->get->custom_carport }}</span>
                                    @else
                                        <span class="removeBold"> {{ @$auction->get->carportOptions }}</span>
                                    @endif
                                </div>
                            @endif

                            @if (@$auction->get->minimum_heated_square != null && @$auction->get->minimum_heated_square != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Minimum Sqft Needed:
                                    <span class="removeBold">
                                        {{ @$auction->get->minimum_heated_square != '' ? @$auction->get->minimum_heated_square : '' }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->minimum_net_leasable_square != null && @$auction->get->minimum_net_leasable_square != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Minimum Net Leasable
                                    Sqft Needed:
                                    <span class="removeBold">
                                        {{ @$auction->get->minimum_net_leasable_square != '' ? @$auction->get->minimum_net_leasable_square : '' }}</span>
                                </div>
                            @endif
                            @if (@$auction->get->garageOption != null && @$auction->get->garageOption != 'null')
                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Garage/Parking
                                    Features Needed:
                                    <span class="removeBold">
                                        ({{ @$auction->get->garageOption }})
                                    </span><br>
                                    @if (@$auction->get->garageOption == 'Yes')
                                        @foreach (@$auction->get->garage_parking as $item)
                                            <span class="removeBold badge bg-secondary">
                                                {{ @$item }}
                                            </span>
                                        @endforeach
                                        @if (!empty($auction->get->other_services))
                                            <span class="removeBold badge bg-secondary">
                                                @foreach ($auction->get->other_services as $other_service)
                                                    <br>{{ $other_service }}
                                                @endforeach

                                            </span>
                                        @endif
                                    @endif
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
                                    Preference Needed:
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
                                EEligibility/Interest in Leasing in 55-and-Over Communities:
                                <span class="removeBold">
                                    {{ @$auction->get->leasing_55_plus != '' ? @$auction->get->leasing_55_plus : '' }}</span>
                            </div>

                            @if (@$auction->get->non_negotiable_amenities != null || $auction->get->other_non_negotiable_amenities != null)

                                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                    Non-Negotiable
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




                            @if (@$auction->get->pets != null)
                            <div class="col-md-12 col-12 pt-2 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Pets Allowed:</span>
                                {{ @$auction->get->pets }}
                            </div>
                            @endif

                            @if (@$auction->get->pets === "Yes" && @$auction->get->number_of_pets != null)
                            <div class="col-md-12 col-12 pt-2 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Number of Pets Allowed:</span>
                                {{ @$auction->get->number_of_pets }}
                            </div>
                            @endif


                                                        @if (@$auction->get->pets === "Yes" && @$auction->get->type_of_pets != null)
                                                        <div class="col-md-12 col-12 pt-2 removeBold"><i class="fa-regular fa-check-square"></i>
                                                            <span class="fw-bold">Acceptable Pet Types:</span>
                                                            {{ @$auction->get->type_of_pets }}
                                                        </div>
                                                        @endif

                        </div>
                        <hr>
                        <div class="card-header">
                            <h4>Sale Terms: </h4>
                        </div>

                         @if (@$auction->get->sale_provision != '' && @$auction->get->sale_provision != 'null')
                            <div class="col-md-12 col-12 pt-2  fw-bold"><i class="fa-regular fa-check-square"></i> Special Sale
                                Provision:
                                @if (gettype(@$auction->get->sale_provision) == 'array')
                                    @foreach ($auction->get->sale_provision as $sale)
                                        @if ($sale != 'Other')
                                            <span class="removeBold badge bg-secondary">
                                                {{ $sale }}
                                            </span>
                                        @else
                                            <br>
                                            <ul class="leasing">
                                                <li style="font-size:16px;">
                                                    <span class="removeBold">
                                                        {{ $auction->get->sale_provision_other }}</span>
                                                </li>
                                            </ul>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        @endif

                        @if (@$auction->get->target_closing_date != null)
                            <div class="col-md-12 col-12 pt-2 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Target Closing Date:</span>
                                {{ @$auction->get->target_closing_date }}
                            </div>
                        @endif
                        @if (@$auction->get->occupant_status != null)
                            <div class="col-md-12 col-12 pt-2 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Occupant Type:</span>
                                {{ @$auction->get->occupant_status }}
                            </div>
                        @endif
                        @if (@$auction->get->maximum_budget != null)
                            <div class="col-md-12 col-12 pt-2 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Desired Sale Price:</span>
                                ${{ @$auction->get->maximum_budget }}
                            </div>
                        @endif

                        @if (@$auction->get->offered_financing != '' && @$auction->get->offered_financing != 'null')
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Offered
                                Financing/Currency:
                                @if (gettype(@$auction->get->offered_financing) == 'array')
                                    @foreach ($auction->get->offered_financing as $financing)
                                        @if ($financing != 'Other')
                                            <span class="removeBold badge bg-secondary">
                                                {{ $financing }}
                                            </span>
                                        @else
                                            <br>
                                            <ul class="leasing">
                                                <li style="font-size:16px;">
                                                    <span class="removeBold">
                                                        {{ $auction->get->other_financing }}</span>
                                                </li>
                                            </ul>
                                        @endif
                                    @endforeach
                                @endif
                            </div>
                        @endif



                        <hr>

                        <div class="card-header">
                            <h4>Services: </h4>
                        </div>

                        <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Services the
                            Seller Requests from Their Agent:

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
                                Seller Requests from Their Agent:
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

                        @if (@$auction->get->commission_structure != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Tenant's Broker Commission Structure:
                                <span class="removeBold">
                                    {{ $auction->get->commission_structure ?? '' }}</span>
                            </div>
                        @endif
                        @if (@$auction->get->lease_fee_type != null)
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Tenant's Broker Lease Fee:
                                <span class="removeBold">
                                    {{ $auction->get->lease_fee_type ?? '' }}</span>
                            </div>
                        @endif

                        @if (@$auction->get->lease_fee_flat != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->lease_fee_flat }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->lease_fee_percentage != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->lease_fee_percentage }}%</li>
                            </ul>
                        @endif
                        @if (@$auction->get->lease_fee_percentage_monthly_rent != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->lease_fee_percentage_monthly_rent }}%</li>
                            </ul>
                        @endif
                        @if (@$auction->get->lease_fee_flat_combo != null)
                            <ul>
                                <li style="font-size: 16px;">${{ $auction->get->lease_fee_percentage_monthly_rent }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->lease_fee_percentage_combo != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->lease_fee_percentage_combo }}%</li>
                            </ul>
                        @endif
                        @if (@$auction->get->lease_fee_percentage_net != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->lease_fee_percentage_net }}%</li>
                            </ul>
                        @endif
                        @if (@$auction->get->lease_fee_flat_combo_net != null)
                            <ul>
                                <li style="font-size: 16px;">${{ $auction->get->lease_fee_flat_combo_net }}</li>
                            </ul>
                        @endif
                        @if (@$auction->get->lease_fee_percentage_combo_net != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->lease_fee_percentage_combo_net }}%</li>
                            </ul>
                        @endif
                        @if (@$auction->get->lease_fee_other != null)
                            <ul>
                                <li style="font-size: 16px;">{{ $auction->get->lease_fee_other }}%</li>
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
                            <h4>Seller Info </h4>
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
        @if (@$auction->user_id != $auth_id)
        <a href="{{ route('auction-chat', ['tenant-agent', $auction->id]) }}" class="btn btn-success w-100 mb-2">
            <i class="fa-solid fa-paper-plane"></i> Send Message
        </a>
        @endif


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
                            <p><b>{{ $lowest_bidder->user->first_name ?? '' }}</b> is the lowest bidder.</p>
                            {{-- <p><b>{{ $lowest_bidder->user->name ?? '' }}</b> is the lowest bidder.</p> --}}
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
                                                    <p class="d-flex justify-content-between  align-items-center small"
                                                        style="color: #333;">
                                                        <span>
                                                            Agent First Name:
                                                        </span>
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.first_name', '') }}
                                                        </span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        Year Agent Got Licensed:
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.year_licensed', '') }}
                                                        </span>
                                                    </p>

                                                    <!-- Why Hire You -->
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        Why Should You Be Hired as Their Agent?
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.why_hire_you', '') }}
                                                        </span>
                                                    </p>

                                                    <!-- What Sets You Apart -->
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        What Sets You Apart From Other Agents?
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.what_sets_you_apart', '') }}
                                                        </span>
                                                    </p>

                                                    <!-- Marketing Strategy -->
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        What Is Your Marketing Strategy?
                                                    </p>
                                                    <p class="d-flex justify-content-between small"
                                                        style="font-size:large; color: #333;">
                                                        <span class="fw-normal" style="font-size: 16px; color: #555;">
                                                            {{ data_get($bid, 'get.marketing_plan', '') }}
                                                        </span>
                                                    </p>

                                                    <!-- Services Offered -->
                                                    @php $servicesList = (array) data_get($bid,'get.services',[]); @endphp
                                                    @if (!empty($servicesList))
                                                        <div>
                                                            <label style="font-size: large;">Services Offered by the
                                                                Agent:</label>
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

                                                    @php $otherServicesList = (array) data_get($bid,'get.other_services',[]); @endphp
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

                                                                            @if ($showCounterActions)
                                                                                <div
                                                                                    class="counter-response-buttons mt-3 pt-3 border-top">
                                                                                    <h6>Respond to this Counter Offer:</h6>
                                                                                    <div
                                                                                        class="d-flex gap-3 flex-wrap justify-content-between">
                                                                                        <form class="d-inline"
                                                                                            action="{{ route('tenant.hire.agent.auction.counter.bid.accept') }}"
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
                                                                                            action="{{ route('tenant.hire.agent.auction.counter.bid.reject') }}"
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
                                                                                            action="{{ route('tenant.hire.agent.auction.bid.counter') }}"
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

                                                    {{-- Main Bid Actions --}}
                                                    @php
                                                        $rawState = data_get($bid, 'accepted', '0');
                                                        $state = in_array($rawState, [null, 0, '0'], true)
                                                            ? '0'
                                                            : (string) $rawState;
                                                        $isOwnerRow = data_get($auction, 'user_id') == $auth_id;

                                                        // Names for messages
                                                        $ownerFirst = data_get($auction, 'user.first_name', '');
                                                        $ownerLast = data_get($auction, 'user.last_name', '');
                                                        $agentFirst = data_get($bid, 'user.first_name', '');
                                                        $agentLast = data_get($bid, 'user.last_name', '');

                                                        // For main bid accepted/rejected: actor is ALWAYS owner
                                                        $ownerId = data_get($auction, 'user_id');
                                                    @endphp

                                                    <div class="d-flex justify-content-between flex-wrap gap-2 mt-3">
                                                        {{-- When main bid pending, OWNER can Accept/Reject/Counter --}}
                                                        @if ($state === '0' && $isOwnerRow && !data_get($auction, 'is_sold'))
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
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Scan Qr Code"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
                                <!-- Message  -->
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
        </div>
    </div>
@endsection
@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous"
        referrerpolicy="no-referrer"></script>
    @if (@$auction->get->auction_length_days > 0)
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
    @endif
@endpush
