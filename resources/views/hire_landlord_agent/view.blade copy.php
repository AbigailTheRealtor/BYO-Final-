@extends('layouts.main')
@push('styles')
<!-- //Listing Description css  -->
<link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
<!-- Toastr CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">
<style>
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

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

    ul.leasing {
        --icon-size: 0.8em;
        --gutter: .5em;
        padding: 0 0 0 calc(var(--icon-size) + 1em);
        margin-bottom: 5px;
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
</style>
@endpush

@section('content')
@php
$auth_id = auth()->user() ? auth()->user()->id : 0;
@endphp
<div class="container listingDescription">
    <div class="row">
        <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center justify-content-left">
                    @if ($auction->is_approved == 1)
                    <span class="badge bg-primary me-2">Active</span>
                    @endif
                    @if ($auction->is_approved == 0)
                    <span class="badge bg-warning me-2">Pending</span>
                    @endif
                    @if ($auction->is_sold == 1)
                    <span class="badge bg-success">Hired</span>
                    @endif
                </div>
                {{-- @if ($auction->user_id == auth()->user()->id)
                        <div class="d-flex justify-content-end align-content-center">
                            <a href="{{route('landlord.hire.agent.auction.edit', $auction->id)}}" class="btn btn-success btn-sm px-3 mb-3 me-2"><i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing</a>

            </div>
            @endif --}}
        </div>
        @if (gettype(@$auction->get->photos) == 'array')
        <div class="d-flex flex-wrap justify-content-start">
            @foreach (@$auction->get->photos as $image)
            <a href="{{ asset($image) }}" data-toggle="lightbox" data-gallery="example-gallery"
                style="display:block; width:200px; height: 200px; border:1px solid #e0e0e0; border-radius: 5px; overflow: hidden; margin:4px;background-color: #f2f2f2;">
                <img class="w-100" src="{{ asset($image) }}"
                    style="width: 100%; height: 100%; object-fit: cover;">
            </a>
            @endforeach
        </div>
        @endif
        <div class="card description mt-2">
            <div class="card-body">
                <h4>Property Features: </h4>
                @if (@$auction->get->city != '' && @$auction->get->city != 'null')
                <div class="row" style="flex-wrap: wrap;">
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> City:
                        <span class="removeBold">{{ @$auction->get->city }}</span>
                    </div>
                </div>
                @endif
                @if (@$auction->get->county != '' && @$auction->get->county != 'null')
                <div class="row" style="flex-wrap: wrap;">
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> County:
                        <span class="removeBold">{{ @$auction->get->county }}</span>
                    </div>
                </div>
                @endif
                @if (@$auction->get->state != '' && @$auction->get->state != 'null')
                <div class="row" style="flex-wrap: wrap;">
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> State:
                        <span class="removeBold">{{ @$auction->get->state }}</span>
                    </div>
                </div>
                @endif
                @if (@$auction->get->listing_date != '' && @$auction->get->listing_date != 'null')
                @php
                $formatedListingDate = Carbon\Carbon::parse($auction->get->listing_date)->format(
                'd-m-Y',
                );
                @endphp
                <div class="row" style="flex-wrap: wrap;">
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Listing Date:
                        <span class="removeBold">{{ $formatedListingDate }}</span>
                    </div>
                </div>
                @endif
                @if (@$auction->get->expiration_date != '' && @$auction->get->expiration_date != 'null')
                @php
                $formatedExpirationDate = Carbon\Carbon::parse($auction->get->expiration_date)->format(
                'd-m-Y',
                );
                @endphp
                <div class="row" style="flex-wrap: wrap;">
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Expiration Date:
                        <span class="removeBold">{{ $formatedExpirationDate }}</span>
                    </div>
                </div>
                @endif
                @if (isset($auction->get->auction_type))
                <div class="row" style="flex-wrap: wrap;">
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Listing Type:
                        <span class="removeBold">{{ $auction->get->auction_type }}</span>
                    </div>
                </div>
                @endif

                @if (@$auction->get->custom_bathrooms != '' && @$auction->get->custom_bathrooms != 'null')
                <div class="col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Bathrooms:
                    <span class="removeBold">{{ @$auction->get->custom_bathrooms }}
                </div>
                @else
                <div class="col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Bathrooms:
                    <span class="removeBold">{{ @$auction->get->bathrooms }}
                </div>
                @endif

                @if (@$auction->get->property_type != '' && @$auction->get->property_type != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Property Style:
                    <span class="removeBold">
                        ({{ @$auction->get->property_type }})
                    </span><br>

                </div>
                @endif
                @if (@$auction->get->prop_condition != '' && @$auction->get->prop_condition != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Property
                    Condition:
                    @if (@$auction->get->prop_condition != 'Other')
                    <span class="removeBold">
                        {{ @$auction->get->prop_condition }}
                    </span>
                    @else
                    <br>
                    <ul class="leasing">
                        <li style="font-size:16px;">
                            <span class="removeBold"> {{ $auction->get->custom_property_condition }}</span>
                        </li>
                    </ul>
                    @endif
                </div>
                @endif
                <div class="row" style="flex-wrap: wrap;">
                    @if (@$auction->get->custom_bedrooms != '' && @$auction->get->custom_bedrooms != 'null')
                    <div class="col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Bedrooms:
                        <span class="removeBold">{{ @$auction->get->custom_bedrooms }}
                    </div>
                    @elseif(@$auction->get->bedrooms != '' && @$auction->get->bedrooms != 'null' && @$auction->get->bedrooms != null)
                    <div class="col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Bedrooms:
                        <span class="removeBold">{{ @$auction->get->bedrooms }}
                    </div>
                    @endif
                </div>

                @if (@$auction->get->heated_square_footage != '' && @$auction->get->heated_square_footage != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Heated Sqft:
                    <span class="removeBold">
                        {{ @$auction->get->heated_square_footage }}
                    </span>
                </div>
                @endif
                @if (isset($auction->get->totalSqft))
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Total Sqft:
                    <span class="removeBold">
                        {{ @$auction->get->totalSqft }}
                    </span>
                </div>
                @endif
                @if (@$auction->get->net_leasable_square_footage != '' && @$auction->get->net_leasable_square_footage != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Net Leaseable
                    Sqft:
                    <span class="removeBold">
                        {{ @$auction->get->net_leasable_square_footage }}
                    </span>
                </div>
                @endif
                @if (@$auction->get->heated_sqft != '' && @$auction->get->heated_sqft != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Sqft Heated
                    Source:
                    <span class="removeBold">
                        @if (@$auction->get->heated_sqft != 'Other')
                        {{ @$auction->get->heated_sqft }}
                        @else
                        {{ @$auction->get->other_heated_sqft }}
                        @endif
                    </span>
                </div>
                @endif
                @if (@$auction->get->total_acreage != '' && @$auction->get->total_acreage != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Total Acreage:
                    <span class="removeBold">
                        {{ @$auction->get->total_acreage }}
                    </span>
                </div>
                @endif

                @if (@$auction->get->appliances != '' && @$auction->get->appliances != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Appliances:
                    @if (gettype(@$auction->get->appliances) == 'array')
                    @foreach ($auction->get->appliances as $appliance)
                    @if ($appliance != 'Other')
                    <span class="removeBold badge bg-secondary">
                        {{ $appliance }}
                    </span>
                    @else
                    <br>
                    <ul class="leasing">
                        <li style="font-size:16px;">
                            <span class="removeBold"> {{ $auction->get->otherAppliances }}</span>
                        </li>
                    </ul>
                    @endif
                    @endforeach
                    @endif
                </div>
                @endif
                @if (isset($auction->get->furnishings) && is_array($auction->get->furnishings))
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Furnishings:
                    @foreach ($auction->get->furnishings as $item)
                    <span class="removeBold">
                        {{ $item . ',' }}
                    </span>
                    @endforeach
                </div>
                @endif
                @if (@$auction->get->garageOptions != '' && @$auction->get->garageOptions != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Garage:<span class="removeBold">({{ $auction->get->garageOptions }})</span>
                    <span class="removeBold">
                        @if ($auction->get->garageOptions == 'Yes')
                        {{ $auction->get->custom_garage }}
                        @endif
                    </span>
                </div>
                @endif
                @if (@$auction->get->carportOptions != '' && @$auction->get->carportOptions != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Carport:<span
                        class="removeBold">({{ $auction->get->carportOptions }})</span>
                    <span class="removeBold">
                        @if ($auction->get->carportOptions == 'Yes')
                        {{ $auction->get->custom_carport }}
                        @endif
                    </span>
                </div>
                @elseif(@$auction->get->carportOptions != '' && @$auction->get->carportOptions != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Carport:
                    <span class="removeBold">
                        {{ $auction->get->carportOptions }}
                    </span>
                </div>
                @endif
                @if (@$auction->get->poolOptions != '' && @$auction->get->poolOptions != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Pool:
                    <span
                        class="removeBold">({{ @$auction->get->poolOptions == 'Yes' ? @$auction->get->poolOptions : 'No' }})</span>
                    <span class="removeBold">
                        @if (@$auction->get->poolOptions == 'Yes')
                        {{ $auction->get->pool }}
                        @endif
                    </span>
                </div>
                @endif
                @if (@$auction->get->view != '' && @$auction->get->view != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> View:
                    @foreach ($auction->get->view as $item)
                    @if (@$item != 'Other')
                    <span class="removeBold badge bg-secondary">
                        {{ $item }}
                    </span>
                    @else
                    <br>
                    <ul class="leasing">
                        <li style="font-size:16px;">
                            <span class="removeBold"> {{ $auction->get->viewOther }}</span>
                        </li>
                    </ul>
                    @endif
                    @endforeach
                </div>
                @endif
                @if (@$auction->get->parkingOptions != '' && @$auction->get->parkingOptions != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i>
                    Garage/Parking Features:
                    @if (@$auction->get->parking != 'Other')
                    <span class="removeBold badge bg-secondary">
                        {{ $auction->get->parking }}
                    </span>
                    @else
                    <br>
                    <ul class="leasing">
                        <li style="font-size:16px;">
                            <span class="removeBold"> {{ $auction->get->parkingOther }}</span>
                        </li>
                    </ul>
                    @endif
                </div>
                @endif
                @if (@$auction->get->tenantPays != '' && @$auction->get->tenantPays != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Tenant Pays:
                    @foreach ($auction->get->tenantPays as $item)
                    @if (@$item != 'Other')
                    <span class="removeBold badge bg-secondary">
                        {{ $item }}
                    </span>
                    @else
                    <br>
                    <ul class="leasing">
                        <li style="font-size:16px;">
                            <span class="removeBold"> {{ $auction->get->otherTenantPays }}</span>
                        </li>
                    </ul>
                    @endif
                    @endforeach
                </div>
                @endif
                @if (@$auction->get->ownerPays != '' && @$auction->get->ownerPays != 'null')
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Owner Pays:
                    @foreach ($auction->get->ownerPays as $item)
                    @if (@$item != 'Other')
                    <span class="removeBold badge bg-secondary">
                        {{ $item }}
                    </span>
                    @else
                    <br>
                    <ul class="leasing">
                        <li style="font-size:16px;">
                            <span class="removeBold"> {{ $auction->get->otherOwnerPays }}</span>
                        </li>
                    </ul>
                    @endif
                    @endforeach
                </div>
                @endif

                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Amenities and
                    Property Features:
                    @if (gettype(@$auction->get->non_negotiable_amenities) == 'array')
                    @foreach ($auction->get->non_negotiable_amenities as $amenitie)
                    @if ($amenitie != 'Other')
                    <span class="removeBold badge bg-secondary">
                        {{ $amenitie }}
                    </span>
                    @else
                    <br>
                    <ul class="leasing">
                        <li style="font-size:16px;">
                            <span class="removeBold"> {{ $auction->get->other_non_negotiable_amenities }}</span>
                        </li>
                    </ul>
                    @endif
                    @endforeach
                    @endif
                </div>
                @if (@$auction->get->leasing_55_plus != '' && @$auction->get->leasing_55_plus != 'null' && @$auction->get->leasing_55_plus != null)
                <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> 55-and-Over
                    Community:
                    <span class="removeBold">
                        {{ @$auction->get->leasing_55_plus }}
                    </span>
                </div>
                @endif
                @if (@$auction->get->termLease != '' && @$auction->get->termLease != 'null')
                <div class="row" style="flex-wrap: wrap;">
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Terms of Lease:
                        @foreach (@$auction->get->termLease as $item)
                        <span class="removeBold">${{ $item }}</span>
                        @if ($item == 'Other')
                        <span
                            class="d-inline-block removeBold">${{ @$auction->get->termLeaseOther }}</span>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endif
                <hr>
                <h5>Leasing Terms:</h5>
                @if (@$auction->get->occupant_status != '' && @$auction->get->occupant_status != 'null')
                <div class="row" style="flex-wrap: wrap;">
                    <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Occupant Type:
                        <span class="removeBold">{{ @$auction->get->occupant_status }}</span>
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
                    Landlord Requests from Their Agent:

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
                    Services the Landlord Requests from Their Agent:
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

                <hr />



                <div class="card-body">
                    <h4>Landlord’s Info:</h4>
                    @if (@$auction->get->first_name != '' && @$auction->get->first_name != 'null')
                    <div class="row" style="flex-wrap: wrap;">
                        <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> First
                            Name:
                            <span class="removeBold">{{ @$auction->get->first_name }}</span>
                        </div>
                    </div>
                    @endif
                    <small>(Only the first name will show on listings until an agent gets hired.)</small>
                    @if ($auction->is_sold)
                    @if (@$auction->get->last_name != '' && @$auction->get->last_name != 'null')
                    <div class="row" style="flex-wrap: wrap;">
                        <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Last Name:
                            <span class="removeBold">{{ @$auction->get->last_name }}</span>
                        </div>
                    </div>
                    @endif
                    @if (@$auction->get->email != '' && @$auction->get->email != 'null')
                    <div class="row" style="flex-wrap: wrap;">
                        <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Email:
                            <span class="removeBold">{{ @$auction->get->email }}</span>
                        </div>
                    </div>
                    @endif
                    @if (@$auction->get->phone != '' && @$auction->get->phone != 'null')
                    <div class="row" style="flex-wrap: wrap;">
                        <div class="col-12 fw-bold"> <i class="fa-regular fa-check-square"></i> Phone
                            Number:
                            <span class="removeBold">{{ @$auction->get->phone }}</span>
                        </div>
                    </div>
                    @endif
                    @endif

                    @if ($auction->get->auction_type !== null && $auction->get->auction_type == 'Auction Listing')
                    <div class="disclaimer mt-4">
                        <h5>Legal Disclaimer:</h5>
                        <p>For Timed Listings, the landlord must wait until the timer has ended before selecting
                            an agent to accept, counter, or reject. The landlord may choose to end the timer
                            early only if an agent matches the "Hire Now" terms. These terms include the
                            landlord's preferences for:</p>
                        <ul>
                            <li>The Listing Agreement timeframe</li>
                            <li>The Listing Broker’s commission</li>
                            <li>The Tenant’s Broker Commission Structure</li>
                            <li>The Tenant’s Broker Commission</li>
                            <li>The payment timing for broker fees</li>
                            <li>The early termination fee</li>
                            <li>The protection period</li>
                            <li>The broker compensation agreement for new lease/renewal with a tenant placed by
                                the broker</li>
                            <li>The new lease/renewal fee to the Listing Broker</li>
                            <li>The services requested by the landlord from their agent</li>
                        </ul>
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
                        <p class="mb-0 opacity-50">{{ $auser->first_name }} • last online 5
                            days ago.</p>
                        <p class="mb-0 opacity-50">({{ $auser->user_name }})</p>
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
        @if (@$auction->get->titleListing != null)
        <div class="col-md-12 col-12 fw-bold"><i class="fa-regular fa-check-square"></i> Title of the
            listing:
            <h1>{{ @$auction->get->titleListing }}</h1>
        </div>
        @endif
        {{-- <h1>{{ @$auction->address }}</h1> --}}
        <hr>
        @inject('carbon', 'Carbon\Carbon')
        @php
        if ($auction->auction_length > 0) {
        $start = $carbon::now();
        $end = $carbon::parse(@$auction->created_at)->addDays(@$auction->auction_length);
        $diff = $start->diffInDays($end, false);
        }
        @endphp
        @if ($auction->auction_length > 0)
        @php
        $diff_d = $diff < 0 ? 0 : $diff;
            $diff_H=$diff < 0 ? 0 : $start->diff($end)->format('%H');
            $diff_I = $diff < 0 ? 0 : $start->diff($end)->format('%I');
                $diff_S = $diff < 0 ? 0 : $start->diff($end)->format('%S');
                    @endphp
                    <div id="countdown" class="time d-flex justify-content-between text-center flex-wrap pb-2">
                        @if ($auction->auction_ended)
                        <div class="d-flex justify-content-center align-items-center w-100">
                            <h4 class="text-success">Auction Ended</h4>
                        </div>
                        @else
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
                        @endif
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
                    <a href="{{ route('auction-chat', ['landlord-agent', $auction->id]) }}"
                        class="btn btn-success w-100 mb-2">
                        <i class="fa-solid fa-paper-plane"></i> Send Message</a>
                    @endif
                    @if ($auth_id)
                    @if (in_array(auth()->user()->user_type, ['agent']))
                    <button class="btn w-100"
                        onclick="javascript:window.location='{{ route('landlord.agent.auction.bid.add', @$auction->id) }}';"
                        {{-- {{ $my_bid || @$auction->user_id == $auth_id ? 'disabled' : '' }} --}}>
                        <span class="bid">Bid Now </span>
                        <span
                            class="badge bg-light float-end text-dark">${{ @$auction->get->custom_expectation == '' ? @$auction->get->expectation : @$auction->get->custom_expectation }}</span>
                        @if (@$auction->sold)
                        <span class="badge bg-danger">Sold</span>
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
                        @if ($auction->user_id == auth()->user()->id && $auction->bids->count() > 0)
                        <div class="d-flex align-items-baseline justify-content-center">
                            @if ($auction->display_bids == 0)
                            <form
                                action="{{ route('landlord.agent.auction.bids.visibility', ['id' => $auction->id, 'vis' => 'show']) }}"
                                method="post">
                                @csrf
                                <button class="btn bg-success btn-sm px-3 mb-3 mt-0">Show Bids</button>
                            </form>
                            @else
                            <form
                                action="{{ route('landlord.agent.auction.bids.visibility', ['id' => $auction->id, 'vis' => 'hide']) }}"
                                method="post">
                                @csrf
                                <button class="btn bg-danger btn-sm px-3 mb-3 mt-0">Hide Bids</button>
                            </form>
                            @endif
                        </div>
                        @endif
                        <div class="card-body">
                            @if (@$auction->bids->count() > 0)
                            @else
                            <p>No one has bid on this auction.</p>
                            @endif
                            <div class="accordion" id="accordionExample">
                                <div class="accordion-item border-0">
                                    @if ($auction->display_bids == 1 || $auction->user_id == auth()->user()->id)
                                    @foreach ($bids as $bid)
                                    <!-- Item loop -->
                                    <div class="accordion" type="button" data-bs-toggle="collapse"
                                        data-bs-target="#item{{ @$bid->id }}" aria-expanded="true"
                                        aria-controls="item{{ @$bid->id }}">
                                        <div class="d-flex small accordion mr-0 text-center">
                                            <div class="col-1">
                                                <span class="badge">{{ $loop->iteration }}</span>
                                            </div>
                                            <div class="col-4">
                                                {{ $bid->get->first_name . ' ' . $bid->get->last_name }}
                                            </div>
                                            <div class="col-4 text-right">
                                                ${{ @$bid->get->offering_price }}
                                            </div>
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
                                                    <div
                                                        class="d-flex justify-content-center align-items-center w-100">
                                                        <a href="{{ route('landlord.agent.auction.bid.view', $bid->id) }}"
                                                            style="color: blue">
                                                            View <i class="fa fa-eye"></i>
                                                        </a>
                                                    </div>
                                                    <table class="table table-bordered">
                                                        <tbody>
                                                            <tr>
                                                                <th class="small">First Name:</th>
                                                                <td class="small">{{ @$bid->get->first_name }}</td>
                                                            </tr>
                                                            <tr>
                                                                <th class="small">Landlord Agency Agreement Timeframe:
                                                                </th>
                                                                <td>{{ @$bid->get->listing_terms }}</td>
                                                            </tr>
                                                            @if (isset($bid->get->broker_compensation))
                                                            <tr>
                                                                <th class="small">Listing Broker’s Commission:
                                                                </th>
                                                                <td>{{ $bid->get->broker_compensation . '(' . $bid->get->compensation_percent . ')' }}
                                                                </td>
                                                            </tr>
                                                            @endif
                                                            @if (isset($bid->get->handle_compensation))
                                                            <tr>
                                                                <th class="small">Tenant’s Broker Commission
                                                                    Structure: </th>
                                                                <td>{{ $bid->get->handle_compensation }}</td>
                                                            </tr>
                                                            @endif
                                                            @if (isset($bid->get->compensation_amount))
                                                            <tr>
                                                                <th class="small">Tenant’s Broker Commission:</th>
                                                                <td>{{ $bid->get->compensation_amount . '(' . $bid->get->compensation_tenant_broker . ')' }}
                                                                </td>
                                                            </tr>
                                                            @endif
                                                            @if (isset($bid->get->payment_timing))
                                                            <tr>
                                                                <th class="small">Payment Timing for Broker Fees:
                                                                </th>
                                                                <td>{{ $bid->get->payment_timing . '(' . $bid->get->payment_timing_days . ')' }}
                                                                </td>
                                                            </tr>
                                                            @endif
                                                            @if (isset($bid->get->early_termination))
                                                            <tr>
                                                                <th class="small">Early Termination Fee:</th>
                                                                <td>{{ $bid->get->early_termination . '(' . $bid->get->early_termination_amount . ')' }}
                                                                </td>
                                                            </tr>
                                                            @endif
                                                            @if (isset($bid->get->protection_period))
                                                            <tr>
                                                                <th class="small">Protection Period:</th>
                                                                <td>{{ $bid->get->protection_period . '(' . $bid->get->protection_period_days . ')' }}
                                                                </td>
                                                            </tr>
                                                            @endif
                                                            @if (isset($bid->get->compensation_new_lease))
                                                            <tr>
                                                                <th class="small">Broker compensation agreement
                                                                    for new lease/renewal with tenant placed by
                                                                    Broker:</th>
                                                                <td>{{ $bid->get->compensation_new_lease }}</td>
                                                            </tr>
                                                            @endif
                                                            @if (isset($bid->get->compensation_new_lease_percent))
                                                            <tr>
                                                                <th class="small">New Lease/Renewal Fee to Listing
                                                                    Broker:</th>
                                                                <td>{{ $bid->get->compensation_new_lease_percent . '(' . $bid->get->compensation_new_lease_amount . ')' }}
                                                                </td>
                                                            </tr>
                                                            @endif

                                                            @if (@$bid->get->services)
                                                            <tr>
                                                                <th class="small">Services Offered by the Agent:
                                                                </th>
                                                                <td class="services">
                                                                    @foreach (@$bid->get->services as $service)
                                                                    @if ($service == 'Other')
                                                                    @continue
                                                                    @endif
                                                                    <li
                                                                        style="font-size: 16px; margin-top:15px;">
                                                                        {{ $service }}
                                                                    </li>
                                                                    @endforeach
                                                                    @if (@$bid->get->other_services != '' && @$bid->get->other_services != 'null')
                                                                    <li
                                                                        style="font-size: 16px; margin-top:15px;">
                                                                        {{ @$bid->get->other_services }}
                                                                    </li>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                                @if ($bid->accepted == 2)
                                                <div
                                                    class="d-flex justify-content-center align-items-center bg-white mb-4">
                                                    <h4 class="text-danger">Rejected</h4>
                                                </div>
                                                @elseif ($bid->accepted != 2)
                                                <div class="form-group d-flex justify-content-space gap-1">
                                                    @if (
                                                    (($auction->auction_type == 'Auction Listing' && $auction->auction_ended) ||
                                                    $auction->auction_type == 'Traditional Listing') &&
                                                    $auction->user_id == $auth_id &&
                                                    !$auction->is_sold)
                                                    <form
                                                        action="{{ route('landlord.hire.agent.auction.bid.accept', $bid->id) }}"
                                                        method="post">
                                                        @csrf
                                                        <input type="hidden" name="auction_id"
                                                            value="{{ $auction->id }}">
                                                        <input type="hidden" name="bid_id"
                                                            value="{{ $bid->id }}">
                                                        <button type="submit"
                                                            class="btn btn-success btn-sm">Accept</button>
                                                    </form>
                                                    <form
                                                        action="{{ route('landlord.hire.agent.auction.bid.reject') }}"
                                                        id="deleteForm" method="post">
                                                        @csrf
                                                        <input type="hidden" name="auction_id"
                                                            value="{{ $auction->id }}">
                                                        <input type="hidden" name="bid_id"
                                                            value="{{ $bid->id }}">
                                                        <button type="button"
                                                            style="background-color:#da2a43"
                                                            class="btn btn-danger btn-sm"
                                                            onclick="showToast()">Reject</button>
                                                    </form>
                                                    @endif
                                                </div>
                                                @else
                                                <div
                                                    class="d-flex justify-content-center align-items-center bg-white mb-4">
                                                    <h4 class="text-success">Accepted</h4>
                                                </div>
                                                @endif
                                                @auth
                                                <div class="form-group biddingOperations">
                                                    @if (
                                                    !$auction->sold &&
                                                    (auth()->user()->id == $bid->user_id ||
                                                    (auth()->user()->user_type == 'agent' || auth()->user()->user_type == 'admin')))
                                                    <form
                                                        action="{{ route('landlord.agent.add.counter-bid', $bid->id) }}"
                                                        method="get">
                                                        <div class="d-flex gap-1">
                                                            <button type="submit" class="btn btn-primary">Counter
                                                                Bid</button>
                                                        </div>
                                                    </form>
                                                    @endif
                                                    @php
                                                    $allBids = App\Models\LandlordAgentAuctionBid::where(
                                                    'counter_id',
                                                    $bid->id,
                                                    )
                                                    ->with('meta')
                                                    ->orderByDesc('created_at')
                                                    ->get();
                                                    @endphp
                                                    <div class="form-group">
                                                        @if (!$auction->sold)
                                                        <h5>Counter Bids</h5>
                                                        <hr>
                                                        @if (isset($allBids) && count($allBids) > 0)
                                                        @foreach ($allBids as $key => $countBid)
                                                        <div>
                                                            <div
                                                                class="d-flex justify-content-center align-items-center w-100">
                                                                <a href="{{ route('landlord.agent.auction.bid.view', $bid->id) }}"
                                                                    style="color: blue">
                                                                    View <i class="fa fa-eye"></i>
                                                                </a>
                                                            </div>
                                                            <table class="table table-bordered">
                                                                <tbody>
                                                                    <tr>
                                                                        <th class="small">First Name:</th>
                                                                        <td class="small">
                                                                            {{ $countBid->get->first_name }}
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th class="small">Landlord Agency
                                                                            Agreement Timeframe:</th>
                                                                        <td>{{ $countBid->get->listing_terms }}
                                                                        </td>
                                                                    </tr>
                                                                    @if (isset($countBid->get->broker_compensation))
                                                                    <tr>
                                                                        <th class="small">Listing
                                                                            Broker’s Commission:</th>
                                                                        <td>{{ $countBid->get->broker_compensation . '(' . $countBid->get->compensation_percent . ')' }}
                                                                        </td>
                                                                    </tr>
                                                                    @endif
                                                                    @if (isset($countBid->get->handle_compensation))
                                                                    <tr>
                                                                        <th class="small">Tenant’s
                                                                            Broker Commission Structure:
                                                                        </th>
                                                                        <td>{{ $countBid->get->handle_compensation }}
                                                                        </td>
                                                                    </tr>
                                                                    @endif
                                                                    @if (isset($countBid->get->compensation_amount))
                                                                    <tr>
                                                                        <th class="small">Tenant’s
                                                                            Broker Commission:</th>
                                                                        <td>{{ $countBid->get->compensation_amount . '(' . $countBid->get->compensation_tenant_broker . ')' }}
                                                                        </td>
                                                                    </tr>
                                                                    @endif
                                                                    @if (isset($countBid->get->payment_timing))
                                                                    <tr>
                                                                        <th class="small">Payment
                                                                            Timing for Broker Fees:</th>
                                                                        <td>{{ $countBid->get->payment_timing . '(' . $countBid->get->payment_timing_days . ')' }}
                                                                        </td>
                                                                    </tr>
                                                                    @endif
                                                                    @if (isset($countBid->get->early_termination))
                                                                    <tr>
                                                                        <th class="small">Early
                                                                            Termination Fee:</th>
                                                                        <td>{{ $countBid->get->early_termination . '(' . $countBid->get->early_termination_amount . ')' }}
                                                                        </td>
                                                                    </tr>
                                                                    @endif
                                                                    @if (isset($countBid->get->protection_period))
                                                                    <tr>
                                                                        <th class="small">Protection
                                                                            Period:</th>
                                                                        <td>{{ $countBid->get->protection_period . '(' . $countBid->get->protection_period_days . ')' }}
                                                                        </td>
                                                                    </tr>
                                                                    @endif
                                                                    @if (isset($countBid->get->compensation_new_lease))
                                                                    <tr>
                                                                        <th class="small">Broker
                                                                            compensation agreement for
                                                                            new lease/renewal with
                                                                            tenant placed by Broker:
                                                                        </th>
                                                                        <td>{{ $countBid->get->compensation_new_lease }}
                                                                        </td>
                                                                    </tr>
                                                                    @endif
                                                                    @if (isset($countBid->get->compensation_new_lease_percent))
                                                                    <tr>
                                                                        <th class="small">New
                                                                            Lease/Renewal Fee to Listing
                                                                            Broker:</th>
                                                                        <td>{{ $countBid->get->compensation_new_lease_percent . '(' . $countBid->get->compensation_new_lease_amount . ')' }}
                                                                        </td>
                                                                    </tr>
                                                                    @endif

                                                                    @if ($countBid->get->services)
                                                                    <tr>
                                                                        <th class="small">Services
                                                                            Offered by the Agent:</th>
                                                                        <td class="services">
                                                                            @foreach ($countBid->get->services as $service)
                                                                            @if ($service == 'Other')
                                                                            @continue
                                                                            @endif
                                                                            <li
                                                                                style="font-size: 16px; margin-top:15px;">
                                                                                {{ $service }}
                                                                            </li>
                                                                            @endforeach
                                                                            @if ($countBid->get->other_services != '' && $countBid->get->other_services != 'null')
                                                                            <li
                                                                                style="font-size: 16px; margin-top:15px;">
                                                                                {{ $countBid->get->other_services }}
                                                                            </li>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                    @endif
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        @if ($countBid->accepted == 2)
                                                        <div
                                                            class="d-flex justify-content-center align-items-center bg-white mb-4">
                                                            <h4 class="text-danger">Rejected</h4>
                                                        </div>
                                                        @elseif ($countBid->accepted == 0)
                                                        <div
                                                            class="form-group d-flex justify-content-space gap-1">
                                                            @if (
                                                            (($auction->auction_type == 'Auction Listing' && $auction->auction_ended) ||
                                                            $auction->auction_type == 'Traditional Listing') &&
                                                            $auction->user_id == $auth_id &&
                                                            !$auction->is_sold)
                                                            <form
                                                                action="{{ route('landlord.hire.agent.auction.bid.accept') }}"
                                                                method="post">
                                                                @csrf
                                                                <input type="hidden"
                                                                    name="auction_id"
                                                                    value="{{ $auction->id }}">
                                                                <input type="hidden"
                                                                    name="bid_id"
                                                                    value="{{ $countBid->id }}">
                                                                <button type="submit"
                                                                    class="btn btn-success btn-sm">Accept</button>
                                                            </form>
                                                            <form
                                                                action="{{ route('landlord.hire.agent.auction.bid.reject') }}"
                                                                id="deleteForm" method="post">
                                                                @csrf
                                                                <input type="hidden"
                                                                    name="auction_id"
                                                                    value="{{ $auction->id }}">
                                                                <input type="hidden"
                                                                    name="bid_id"
                                                                    value="{{ $countBid->id }}">
                                                                <button type="button"
                                                                    style="background-color:#da2a43"
                                                                    class="btn btn-danger btn-sm"
                                                                    onclick="showToast()">Reject</button>
                                                            </form>
                                                            @endif
                                                        </div>
                                                        @else
                                                        <div
                                                            class="d-flex justify-content-center align-items-center bg-white mb-4">
                                                            <h4 class="text-success">Accepted</h4>
                                                        </div>
                                                        @endif
                                                        <hr>
                                                        @endforeach
                                                        @else
                                                        <div
                                                            class="d-flex justify-content-center align-items-center w-100">
                                                            <h6>No Counterbid</h6>
                                                        </div>
                                                        @endif
                                                        @endif
                                                    </div>
                                                </div>
                                                @endauth
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                    @else
                                    <div class="alert alert-danger text-center" role="alert">Bids are hidden!</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="btn w-100 mt-0">
                        <span class="bid m-0"><i class="fa fa-user"></i> </span>
                    </button>
                    <!-- Social Details  -->
                    <div class="p-4 card">
                        <p class="text-600">Share this link via</p>
                        <div class="qr-code" style="width: 100%; height:200px;">
                            {{ qr_code(route('landlord.agent.auction.view', @$auction->id), 200) }}
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
        const auctionEnded = {
            {
                $auction - > auction_ended
            }
        };
        let createdAt = new Date("{{ $auction->created_at }}").getTime();
        let auctionLength = {
            {
                $auction - > auction_length
            }
        }* 24 * 60 * 60 * 1000;
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
