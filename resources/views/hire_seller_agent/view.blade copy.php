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

        .removeBold {
            font-weight: normal;
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

        table.table.counterAction button {
            padding: 0;
            margin: 0;
            width: auto;
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

                <!-- Description Box  -->
                <div class="card description">

                    <div class="d-flex flex-wrap justify-content-start">

                        @if (gettype(@$auction->get->photos) == 'array')
                            @foreach (@$auction->get->photos as $image)
                                <a href="{{ asset($image) }}" data-toggle="lightbox" data-gallery="example-gallery"
                                    style="display:block; width:200px; height: 200px; border:1px solid #e0e0e0; border-radius: 5px; overflow: hidden; margin:4px;background-color: #f2f2f2;">
                                    <img class="w-100" src="{{ asset($image) }}"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                </a>
                            @endforeach
                        @endif
                    </div>

                    <div class="card-header">
                        <h5>Description</h5>
                    </div>
                    <div class="card-body">
                        @php
                            $listingDate = isset($auction->get->listing_date)
                                ? \Illuminate\Support\Carbon::parse($auction->get->listing_date)->toDateString()
                                : null;
                            $expireDate = isset($auction->get->expiration_date)
                                ? \Illuminate\Support\Carbon::parse($auction->get->expiration_date)->toDateString()
                                : null;
                        @endphp
                        <p class="card-text">{{ @$auction->get->description }}</p>
                        <h4>Property Features: </h4>
                        @if (@$auction->get->working_with_agent != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    Is the seller currently represented by another agent?</span>
                                {{ @$auction->get->working_with_agent }}
                            </div>
                        @endif
                        @if (@$auction->get->city != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    City:</span>
                                {{ @$auction->get->city }}
                            </div>
                        @endif
                        @if (@$auction->get->county != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    County:</span>
                                {{ @$auction->get->county }}
                            </div>
                        @endif
                        @if (@$auction->get->state != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    State:</span>
                                {{ @$auction->get->state }}
                            </div>
                        @endif
                        @if ($listingDate != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    Listing Date:</span>
                                {{ $listingDate }}
                            </div>
                        @endif
                        @if ($expireDate != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    Expiration Date:</span>
                                {{ $expireDate }}
                            </div>
                        @endif
                        @if (@$auction->get->auction_type != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    Listing Type:</span>
                                {{ @$auction->get->auction_type }}
                            </div>
                        @endif
                        @if (@$auction->get->title_listing != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    Title of Listing:</span>
                                {{ @$auction->get->title_listing }}
                            </div>
                        @endif
                        @if (@$auction->get->bedrooms != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                    class="fw-bold">Bedrooms:</span>{{ @$auction->get->bedrooms == 'Other' ? '(Other)' : @$auction->get->bedrooms }}
                                @if (@$auction->get->bedrooms == 'Other')
                                    {{ @$auction->get->other_bedrooms }}
                                @endif
                            </div>
                        @endif
                        @if (@$auction->get->bathrooms != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                    class="fw-bold">Bathrooms:</span>{{ @$auction->get->bathrooms == 'Other' ? '(Other)' : @$auction->get->bathrooms }}
                                @if (@$auction->get->bathrooms == 'Other')
                                    {{ @$auction->get->other_bathrooms }}
                                @endif
                            </div>
                        @endif

                        @if (@$auction->get->property_type != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    Property Type:</span>
                                {{ @$auction->get->property_type }}
                            </div>
                        @endif

                        @if (@$auction->get->special_sale != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    Special Sale Provision: </span>
                                {{ @$auction->get->special_sale }}
                                @if (@$auction->get->special_sale == 'Assignment Contract (Wholesale properties)')
                                    <ul>
                                        <li style="font-size:16px;"> {{ @$auction->get->contribute_term }}</li>
                                        @if (@$auction->get->contribute_term == 'Yes')
                                            <span class="bg-secondary badge">
                                                {{ @$auction->get->commercialseller_contract_yes }}</span>
                                        @elseif(@$auction->get->contribute_term == 'No')
                                            <li style="font-size:16px;"> Is the seller looking to take over a buyer’s
                                                contract?</li>
                                            <span class="bg-secondary badge">
                                                {{ @$auction->get->custom_seller_contract_no }}</span>
                                        @endif
                                    </ul>
                                @elseif(@$auction->get->special_sale == 'Other')
                                    <br>
                                    <span class="bg-secondary badge">{{ @$auction->get->custom_special_sale }}</span>
                                @endif
                            </div>
                        @endif
                        @if (@$auction->get->prop_condition != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                    class="fw-bold">
                                    Property Condition: </span>
                                {{ @$auction->get->prop_condition }}
                            </div>
                            @if (@$auction->get->prop_condition == 'Other')
                                <span class="bg-secondary badge">{{ @$auction->get->custom_prop_condition }}</span>
                            @endif
                        @endif
                        @if (@$auction->get->heated_square != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Heated Sqft:</span>
                                {{ @$auction->get->heated_square }}
                            </div>
                        @endif
                        @if (@$auction->get->totalSqft != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold"> Total Sqft:</span>
                                {{ @$auction->get->totalSqft }}
                            </div>
                        @endif
                        @if (@$auction->get->sqft != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Sqft Heated Source:</span>{{ @$auction->get->sqft }}<br>
                                @if (@$auction->get->sqft == 'Other')
                                    {{ @$auction->get->other_heated }}
                                @endif
                            </div>
                        @endif
                        @if (@$auction->get->garageOptions != null && @$auction->get->garageOptions != 'null')
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Garage:
                                </span>{{ @$auction->get->garageOptions == 'Yes' ? '(Yes)' : @$auction->get->garageOptions }}
                                @if (@$auction->get->garageOptions == 'Yes')
                                    <br>
                                    {{ @$auction->get->garage }}
                                @endif
                            </div>
                        @endif
                        @if (@$auction->get->carportOptions != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Carport:
                                </span>{{ @$auction->get->carportOptions == 'Yes' ? '(Yes)' : @$auction->get->carportOptions }}
                                @if (@$auction->get->carportOptions == 'Yes')
                                    <br>
                                    {{ @$auction->get->carport }}
                                @endif

                            </div>
                        @endif
                        @if (@$auction->get->poolOptions != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Pool:
                                </span>{{ @$auction->get->poolOptions == 'Yes' ? '(Yes)' : @$auction->get->poolOptions }}
                                @if (@$auction->get->poolOptions == 'Yes')
                                    <br>
                                    <span class="bg-secondary badge">{{ @$auction->get->pool }}</span>
                                @endif
                            </div>
                        @endif
                        @if (@$auction->get->view != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">View: </span>
                                @foreach (@$auction->get->view as $item)
                                    @if (@$item != 'Other')
                                        <span class="badge bg-secondary">{{ $item }}</span>
                                    @endif
                                @endforeach
                                <span class="badge bg-secondary">{{ @$auction->get->otherView }}</span>
                            </div>
                        @endif
                        @if (
                            @$auction->get->garage_parking != '' &&
                                @$auction->get->garage_parking != null &&
                                @$auction->get->garage_parking != 'null')
                            <div class="col-md-4 col-6 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                    class="fw-bold">Garage/Parking Features:</span>
                                @if ($auction->get->garage_parking)
                                    {{ @$auction->get->garage_parking }}
                                @else
                                    {{ @$auction->get->other_garage }}
                                @endif
                            </div>
                        @endif
                        <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                            <span class="fw-bold">Total Acreage: </span>
                            @if (@$auction->get->total_acreage != null)
                                {{ @$auction->get->total_acreage }}
                            @endif
                        </div>
                        @if (@$auction->get->rent_include != null || @$auction->get->custom_rent != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Rent Includes:</span>
                                @if (@$auction->get->rent_include != null)
                                    {{ @$auction->get->rent_include }}
                                @else
                                    {{ @$auction->get->custom_rent }}
                                @endif
                            </div>
                        @endif
                        @if (@$auction->get->appliances != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Appliances:</span>
                                @if (gettype(@$auction->get->appliances) == 'array')
                                    @foreach (@$auction->get->appliances as $item)
                                        @if (@$item != 'Other')
                                            <span class="badge bg-secondary">{{ $item }}</span>
                                        @endif
                                    @endforeach
                                    <span class="badge bg-secondary">{{ @$auction->get->custom_appliances }}</span>
                                @endif
                            </div>
                        @endif
                        @if (@$auction->get->amenities != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Amenities and Property Features:</span>
                                @if (gettype(@$auction->get->amenities) == 'array')
                                    @foreach (@$auction->get->amenities as $item)
                                        @if ($item != 'Other')
                                            <span class="badge bg-secondary">{{ $item }}</span>
                                        @endif
                                    @endforeach
                                    <span class="badge bg-secondary">{{ $auction->get->otherAmenities }}</span>
                                @endif
                            </div>
                        @endif
                        @if (@$auction->get->purchase_of_business != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Purchase Includes:</span>
                                {{ @$auction->get->purchase_of_business }}
                            </div>
                        @endif
                        @if (@$auction->get->propertyLoc != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">55-and-Over Community?</span>
                                {{ @$auction->get->propertyLoc }}
                            </div>
                        @endif
                        @if (@$auction->get->occupant_type != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Occupancy Status:</span>
                                {{ @$auction->get->occupant_type }}
                            </div>
                        @endif
                        @if (
                            @$auction->get->prop_conditions != '' &&
                                @$auction->get->prop_conditions != null &&
                                @$auction->get->prop_conditions != 'null')
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                    class="fw-bold">Property
                                    Condition:</span>
                                @if (gettype(@$auction->get->prop_conditions) == 'array')
                                    <ul>

                                        @foreach (@$auction->get->prop_conditions as $prop_condition)
                                            <li style="font-size:16px;">{{ $prop_condition }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endif
                        @if (
                            @$auction->get->preferred_agent != '' &&
                                @$auction->get->preferred_agent != null &&
                                @$auction->get->preferred_agent != 'null')
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                    class="fw-bold">
                                    <span class="fw-bold"> Seller have a preferred agent that they want to notify to
                                        participate in the
                                        auction? </span>{{ @$auction->get->preferred_agent }}
                            </div>
                        @endif
                        @if (@$auction->get->agent_name != '' && @$auction->get->agent_name != null && @$auction->get->agent_name != 'null')
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                    class="fw-bold">
                                    Full Name:</span>{{ @$auction->get->agent_name }}
                            </div>
                        @endif
                        @if (@$auction->get->brokerage != '' && @$auction->get->brokerage != null && @$auction->get->brokerage != 'null')
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                    class="fw-bold">
                                    Brokerage:</span>{{ @$auction->get->brokerage }}
                            </div>
                        @endif
                        @if (@$auction->get->photo_url != '' && @$auction->get->photo_url != null && @$auction->get->photo_url != 'null')
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                    class="fw-bold">
                                    Listing link for Photos:</span>{{ @$auction->get->photo_url }}
                            </div>
                        @endif
                        @if (@$auction->get->need_cma != '' && @$auction->get->need_cma != null && @$auction->get->need_cma != 'null')
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                    class="fw-bold">
                                    Seller want to receive a Free property value analysis from agent?</span>
                                {{ @$auction->get->need_cma }}
                            </div>
                        @endif
                        @if (@$auction->get->need_cma != '' && @$auction->get->need_cma != null && @$auction->get->need_cma != 'null')
                            @if (@$auction->get->need_cma == 'Yes')
                                <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                        class="fw-bold">
                                        What is the address of your property? </span>{{ @$auction->get->cma_q1 }}
                                </div>
                            @endif
                            @if (@$auction->get->cma_q2 != '' && @$auction->get->cma_q2 != null && @$auction->get->cma_q2 != 'null')
                                <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                        class="fw-bold">What price do you want to list your property for?
                                    </span>{{ @$auction->get->cma_q2 }}
                                </div>
                            @endif
                            @if (@$auction->get->cma_q3 != '' && @$auction->get->cma_q3 != null && @$auction->get->cma_q3 != 'null')
                                <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i> <span
                                        class="fw-bold">Have you done any updates since purchasing the property?</span>
                                    {{ @$auction->get->cma_q3 }}
                                </div>
                            @endif
                            @if (@$auction->get->cma_q4 != '' && @$auction->get->cma_q4 != null && @$auction->get->cma_q4 != 'null')
                                <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i><span
                                        class="fw-bold"> Are there any known repairs that need to be completed before
                                        purchasing?</span>
                                    {{ @$auction->get->cma_q4 }}
                                </div>
                                @if (@$auction->get->cma_q5 != '' && @$auction->get->cma_q5 != null && @$auction->get->cma_q5 != 'null')
                                    <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                        <span class="fw-bold">For the most accurate CMA upload pictures of your property:
                                        </span>{{ @$auction->get->cma_q5 }}
                                    </div>
                                @endif
                            @endif
                            @if (
                                @$auction->get->preferred_agent != '' &&
                                    @$auction->get->preferred_agent != null &&
                                    @$auction->get->preferred_agent != 'null')
                                @if (@$auction->get->cma_q6 != '')
                                    <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                        <span class="fw-bold">For the
                                            most accurate CMA upload video of your property:
                                        </span>{{ @$auction->get->cma_q6 }}
                                    </div>
                                @endif
                            @endif
                        @endif
                        <hr />
                        <h4>Sale Terms: </h4>

                        @if (@$auction->get->sale_provision != '' && @$auction->get->sale_provision != 'null')
                            <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Special Sale
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
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Target Closing Date:</span>
                                {{ @$auction->get->target_closing_date }}
                            </div>
                        @endif
                        @if (@$auction->get->occupant_status != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Occupant Type:</span>
                                {{ @$auction->get->occupant_status }}
                            </div>
                        @endif
                        @if (@$auction->get->maximum_budget != null)
                            <div class="col-md-12 col-12 removeBold"><i class="fa-regular fa-check-square"></i>
                                <span class="fw-bold">Desired Sale Price:</span>
                                ${{ @$auction->get->maximum_budget }}
                            </div>
                        @endif

                        @if (@$auction->get->offered_financing != '' && @$auction->get->offered_financing != 'null')
                            <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Offered
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
                                Services the Seller Requests from Their Agent: 
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

                        @if (@$auction->get->additional_details != null)
                            <hr>
                            <div class="card-header">
                                <h4>Additional Details: </h4>
                            </div>

                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                                Additional Details:<span
                                    class="removeBold">{{ $auction->get->additional_details ?? '' }}</span>
                            </div>
                        @endif
                        <hr>


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
              <hr 

                        <div>
                            <h4>Seller's Info:</h4>
                            @if (@$auction->get->first_name != '' && @$auction->get->first_name != 'null')
                                <div class="row" style="flex-wrap: wrap;">
                                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> First Name:
                                        <span class="removeBold">{{ @$auction->get->first_name }}</span>
                                    </div>
                                </div>
                            @endif

                            <div class="row">
                                @if (!empty($auction->get->video))
                                    <div class="col-md-6 col-6 pt-2 fw-bold">Video:
                                        <span class="removeBold">
                                            <video autoplay muted loop playsinline controls style="width:100%; height:29vh;">
                                                <source src="{{ asset('storage/auction/videos/' . $auction->get->video) }}" type="video/mp4">
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
                                <p class="mb-0"><a href="{{ route('author', [$auser->id]) }}"><b>Seller
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
                            <a href="{{ route('author', [$auser->id]) }}"><button class="btn">View
                                    Profile</button></a>
                        </div>
                    </div>
                </div>
                <!-- End  -->
            </div>
            <div class="col-sm-12 col-md-4 col-lg-4 rightCol">
                <h1>{{ @$auction->address }}</h1>
                <hr>
                @inject('carbon', 'Carbon\Carbon')

                @php
                    if (@$auction->auction_length) {
                        $start = $carbon::now();
                        $end = $carbon::parse($data->get->expiration_date);
                        // Check the values of $start and $end for debugging
                        $diff = $end->diffInDays($start);
                        // Output the difference for debugging purposes
                    }
                @endphp
                @if (@$auction->auction_length)
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
                    $highest_bid_price = @$auction->bids->max('price_percent') ?? @$auction->get->ideal_price;
                    $highest_bid_price =
                        $highest_bid_price > @$auction->get->ideal_price
                            ? $highest_bid_price
                            : @$auction->get->ideal_price;
                    $highest_bidder = @$auction->bids->where('price_percent', $highest_bid_price)->first();
                    $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
                @endphp
                @if (@$auction->user_id != $auth_id)
                    <a href="{{ route('auction-chat', ['seller-agent', $auction->id]) }}"
                        class="btn btn-success w-100 mb-2"> <i class="fa-solid fa-paper-plane"></i> Send Message</a>
                @endif
                @if ($auth_id)
                    @if (in_array(auth()->user()->user_type, ['agent']))
                        <button class="btn w-100"
                            onclick="javascript:window.location='{{ route('add_seller_agent_bid', @$auction->id) }}';">
                            {{-- if wanna disabled after one bid then use this
              {{ $my_bid || @$auction->user_id == $auth_id ? 'disabled' : '' }} --}}
                            <span class="bid">Bid Now </span>
                            @php
                                $expectation = str_replace('k', '000', @$auction->get->expectation);
                            @endphp
                            @if (@$auction->is_sold)
                                <span class="badge bg-light float-end text-dark">{{ @$auction->auction_price }}</span>
                                <span class="badge bg-danger">Sold</span>
                            @else
                                <span
                                    class="badge bg-light float-end text-dark">{{ @$auction->get->custom_seller_specific_price ?: (@$auction->get->otherSellerPrice ?: @$expectation) }}</span>
                            @endif
                        </button>
                    @endif
                @else
                    <a href="{{ route('login') }}">
                        <button class="btn w-100">
                            <span class="bid">Login for Bid </span>
                            <span class="badge bg-light float-end text-dark">${{ $highest_bid_price }}</span>
                        </button>
                    </a>
                @endif
                <!-- Highest Bider   -->
                <div class="card higestBider">
                    <div class="card-body">
                        @if (@$auction->bids->count() > 0)
                            {{-- <p><b>{{ $highest_bidder->user->name ?? '' }}</b> is the highest bidder.</p> --}}
                        @else
                            <p>No one has bid on this auction.</p>
                        @endif
                        <div class="accordion" id="accordionExample">
                            <div class="accordion-item border-0">
                                @php
                                    $sortedBids = $auction->bids->sortByDesc('created_at');
                                @endphp
                                @foreach (@$sortedBids as $bid)
                                    <!-- Item loop -->
                                    @if ($bid->seller_counter_id == null)
                                        <div class="accordion" type="button" data-bs-toggle="collapse"
                                            data-bs-target="#item{{ @$bid->id }}" aria-expanded="true"
                                            aria-controls="item{{ @$bid->id }}">
                                            <div class="d-flex small accordion mr-0 text-center">
                                                <div class="col-1">
                                                    <span class="badge">{{ $loop->iteration }}</span>
                                                </div>
                                                <div class="col-4">
                                                    {{ @$bid->user->name }} </div>
                                                <div class="col-4 text-right">
                                                    ${{ @$bid->get->offering_price ?: @$bid->get->total_comission }}</div>
                                                <div class="col-2">
                                                    Terms↓
                                                </div>
                                            </div>
                                        </div>
                                        <div id="item{{ @$bid->id }}" class="accordion-collapse collapse"
                                            aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                            <div class="accordion-body">
                                                <div id="bidding_history_data">
                                                    <div>
                                                        <p class="fw-bold d-flex justify-content-between small">First Name:
                                                            <span class="removeBold">{{ @$bid->get->first_name }}</span>
                                                        </p>
                                                        <p class="fw-bold d-flex justify-content-between small">Seller
                                                            Agency Agreement Timeframe:
                                                            <span
                                                                class="removeBold">{{ @$bid->get->listing_terms }}</span>
                                                        </p>
                                                        <p class="fw-bold d-flex justify-content-between small">Commission
                                                            Offered:
                                                            <span
                                                                class="removeBold">{{ @$bid->get->total_comission ?: 'N/A' }}</span>
                                                        </p>
                                                        <div class="row" style="flex-wrap: wrap;">
                                                            @if (@$bid->get->services != '' && @$bid->get->services != null && @$bid->get->services != 'null')
                                                                <p class="fw-bold d-flex justify-content-between small">
                                                                    Services the Seller Requests from
                                                                    Their Agent</p>
                                                                <div class="col-md-12 col-12 removeBold ">
                                                                    <ul class="services">
                                                                        @if (gettype(@$bid->get->services) == 'array')
                                                                            @foreach (@$bid->get->services as $service)
                                                                                <li style="font-size:16px;">
                                                                                    &nbsp;{{ $service }}</li>
                                                                            @endforeach
                                                                        @else
                                                                            <li style="font-size:16px;">
                                                                                &nbsp;{{ @$auction->get->custom_services }}
                                                                            </li>
                                                                        @endif
                                                                    </ul>
                                                                </div>
                                                            @endif
                                                        </div>

                                                        @if (@$bid->get->card != '' && @$bid->get->card != 'null')
                                                            <a href="{{ asset(@$bid->get->card) }}" target="_blank"
                                                                style="width:200px; height:200px;display:block;">
                                                                <img src="{{ asset(@$bid->get->card) }}"
                                                                    style="width:100%; height:100%; object-fit: cover;">
                                                            </a>
                                                        @endif
                                                        @if (@$bid->get->virtual_buyer_presentation != '' && @$bid->get->virtual_buyer_presentation != 'null')
                                                            <video width="360" height="360" controls autoplay muted>
                                                                <source
                                                                    src="{{ asset(@$bid->get->virtual_buyer_presentation) }}"
                                                                    type="video/mp4">
                                                                Your browser does not support the video tag.
                                                            </video>
                                                        @endif

                                                        @if (@$bid->get->virtual_buyer_presentation_link)
                                                            <p>
                                                                <a href="{{ @$bid->get->virtual_buyer_presentation_link }}"
                                                                    class="btn btn-sm btn-primary" target="_blank">View
                                                                    Video</a>
                                                            </p>
                                                        @endif

                                                        @if (@$bid->get->note != '' && @$bid->get->note != 'null')
                                                            @if (is_array(@$bid->get->note))
                                                                <div class="row">
                                                                    @foreach ($bid->get->note as $item)
                                                                        <div class="col-md-6">
                                                                            <p
                                                                                class="d-flex justify-content-between small">
                                                                                <img src="{{ asset('auction/files/' . @$item->file_path) }}"
                                                                                    style="width:100%; height:150px; object-fit: cover;">
                                                                            </p>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <img src="{{ asset(@$bid->get->note) }}"
                                                                    style="width:100%; height:100%; object-fit: cover;">
                                                            @endif
                                                        @endif
                                                        @if (@$auction->user_id == $auth_id)
                                                            @if (!@$auction->is_sold)
                                                                <form action="{{ route('acceptSABid') }}"
                                                                    method="post">
                                                                    @csrf
                                                                    <input type="hidden" name="auction_id"
                                                                        value="{{ @$auction->id }}">
                                                                    <input type="hidden" name="bid_id"
                                                                        value="{{ @$bid->id }}">
                                                                    <div style="text-align: right;">
                                                                        <button type="submit"
                                                                            class="btn btn-success btn-sm">Accept</button>
                                                                    </div>
                                                                </form>
                                                            @endif
                                                        @endif

                                                        @auth
                                                            @if (auth()->user()->id == $bid->user->id ||
                                                                    (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin'))
                                                                @if (!@$auction->is_sold)
                                                                    <div class="form-group biddingOperations">
                                                                        @if (!$auction->sold)
                                                                            <form action="{{ route('sellerCounterBid') }}"
                                                                                method="Post">
                                                                                @csrf
                                                                                <input type="hidden" name="auction_id"
                                                                                    value="{{ $auction->id }}">
                                                                                <input type="hidden" name="bid_id"
                                                                                    value="{{ $bid->id }}">
                                                                                <input type="number" name="counterAmount"
                                                                                    class="form-control" required>

                                                                                <div class="d-flex gap-1">
                                                                                    <button type="submit"
                                                                                        class="btn btn-primary">
                                                                                        Counter Bid
                                                                                    </button>
                                                                                    <button type="button"
                                                                                        class="btn btn-primary"
                                                                                        data-bs-toggle="modal"
                                                                                        data-bs-target="#exampleModal-{{ isset($bid->id) ? $bid->id : '' }}">
                                                                                        Counter Terms
                                                                                    </button>
                                                                                </div>
                                                                            </form>
                                                                        @endif
                                                                        @php
                                                                            $allBids = App\Models\SellerAgentAuctionBid::where(
                                                                                'seller_counter_id',
                                                                                $bid->id,
                                                                            )
                                                                                ->orderByDesc('created_at')
                                                                                ->get();
                                                                        @endphp
                                                                        <div class="form-group">
                                                                            @foreach ($allBids as $key => $countBid)
                                                                                <form action="{{ route('acceptSABid') }}"
                                                                                    method="post">
                                                                                    @csrf
                                                                                    <input type="hidden" name="auction_id"
                                                                                        value="{{ $auction->id }}">
                                                                                    <input type="hidden" name="bid_id"
                                                                                        value="{{ $bid->id }}">
                                                                                </form>
                                                                            @endforeach
                                                                        </div>
                                                                        <div class="form-group">
                                                                            @if (!$auction->sold)
                                                                                <table class="table counterAction border-2">
                                                                                    <tbody>
                                                                                        @php
                                                                                            $maxCounter = '';
                                                                                        @endphp
                                                                                        @foreach ($allBids as $key => $countBid)
                                                                                            <tr>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="font-size: 15px;">
                                                                                                        {{ $bid->user->name }}
                                                                                                    </p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <p
                                                                                                        style="font-size: 15px;">
                                                                                                        {{ $countBid->price }}
                                                                                                    </p>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <form
                                                                                                        action="{{ route('acceptSABid') }}"
                                                                                                        method="post">
                                                                                                        @csrf
                                                                                                        <input type="hidden"
                                                                                                            name="auction_id"
                                                                                                            value="{{ $auction->id }}">
                                                                                                        <input type="hidden"
                                                                                                            name="bid_id"
                                                                                                            value="{{ $bid->id }}">
                                                                                                        <input type="hidden"
                                                                                                            name="counterPrice"
                                                                                                            value="{{ $countBid->price }}">
                                                                                                        @if (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin')
                                                                                                            <button
                                                                                                                type="submit"
                                                                                                                class="badge bg-success p-2 borderless">Accept</button>
                                                                                                        @endif
                                                                                                    </form>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <form
                                                                                                        action="{{ route('destroySellerCounter', $countBid->id) }}"
                                                                                                        method="post">
                                                                                                        @csrf
                                                                                                        @if (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin')
                                                                                                            <button
                                                                                                                type="submit"
                                                                                                                class="badge bg-danger p-2">Reject</button>
                                                                                                        @endif
                                                                                                    </form>
                                                                                                </td>
                                                                                            </tr>
                                                                                        @endforeach
                                                                                    </tbody>
                                                                                </table>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            @endauth
                                                        @endif

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                    <!-- End  -->
                                @endforeach

                            </div>

                        </div>
                    </div>
                </div>
                <button class="btn w-100 mt-0">
                    <span class="bid m-0"><i class="fa fa-user"></i> </span>
                </button>
                <!-- End  -->
                <!-- Social Details  -->
                <div class="p-4 card">
                    <p class="text-600">Share this link via</p>
                    <div class="qr-code" style="width: 100%; height:200px;">
                        {{ qr_code(route('seller.agent.auction.detail', @$auction->id), 200) }}
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
                <!-- End  -->
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

    <!-- Modal -->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
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
                                        {{-- <div class="d-flex align-items-baseline">
                                            <i class="fa fa-dollar"></i> --}}
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
                                                name="price_percent" id="price_percent" min="0" max="100"
                                                required placeholder="0.00" value="{{ old('price_percent') }}">
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
                                                        <input type="text" name="social_link[]" class="form-control">
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
                                    <label>Any details agent wants to share with the seller (Why should the Seller pick you
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
                    {{-- <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary">Save changes</button>
            </div> --}}
                </form>
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
