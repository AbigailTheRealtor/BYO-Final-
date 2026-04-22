@extends('layouts.main')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/choices.min.css') }}">
<style>
    .choices__list {
        z-index: 999;
    }

    .wizard-steps-progress {
        height: 5px;
        width: 100%;
        background-color: #CCC;
        position: absolute;
        top: 0;
        left: 0;
    }

    .steps-progress-percent {
        height: 100%;
        width: 0%;
        background-color: #11b7cf;
    }

    .wizard-step {
        display: none;
    }

    .wizard-step.active {
        display: block;
    }

    label.warning {
        color: #f00;
    }

    ::placeholder {
        color: #cacaca !important;
        opacity: 1;
        /* Firefox */
    }

    :-ms-input-placeholder {
        /* Internet Explorer 10-11 */
        color: #cacaca !important;
    }

    ::-ms-input-placeholder {
        /* Microsoft Edge */
        color: #cacaca !important;
    }

    .hide_arrow::-webkit-outer-spin-button,
    .hide_arrow::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    /* Firefox */
    .hide_arrow {
        -moz-appearance: textfield;
    }

    .input-cover {
        align-items: center;
        position: relative;
    }

    .input-cover .input-icon {
        position: absolute;
        left: 10px;
        font-size: 30px;
        color: #11b7cf;
    }

    .input-cover .form-control {
        padding-left: 50px;
    }

    .form-control {
        min-height: 50px;
    }

    .form-group {
        margin-top: 15px;
    }

    .options-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-start;
    }

    .option-container {
        align-items: center;
        cursor: pointer;
        display: flex;
        flex-direction: row;
        align-items: center;
        padding: 15px;
        margin: 10px;
        margin-left: 0;
        margin-bottom: 0;
    }

    .option-container.active {
        border-color: #006e9f;
        color: #006e9f;
    }

    .option-container .option-icon {
        font-size: 40px;
        color: #11b7cf;
    }

    .option-container .option-text {
        padding-left: 10px;
    }

    .text-error {
        width: 100%;
    }

    .text-error {
        border-color: rgba(var(--bs-danger-rgb), var(--bs-text-opacity)) !important;
    }

    .grid-picker {
        width: 100%;
        height: 0px;
        visibility: hidden;
    }

    .wizard-steps-progress {
        overflow: hidden;
    }

    .cityState #state,
    .countyState #state {
        display: none;
        ;
    }
</style>
@endpush
@section('content')
@php
$yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
@endphp
<div class="container pt-5 pb-5">
    <div class="card">
        <div class="row">
            <div class="col-12 p-4">
                <form class="p-4 pt-0 mainform" action="{{ route('tenant.hire.agent.auction') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @push('scripts')
                    <script>
                        $(function() {
                            $('#working_with_agent').change(function() {
                                var val = $(this).val();
                                if (val == "Yes") {
                                    $('.wizard-step-next').attr("disabled", "disabled");
                                    $('.yes_message').text(
                                        "This is a service for tenants who are not currently working with a licensed agent."
                                    );
                                } else {
                                    $('.wizard-step-next').removeAttr("disabled");
                                    $('.yes_message').text("");
                                }
                            });
                            
                        });
                    </script>
                    @endpush


                    <div class="container">
                        <h4 class="title">
                            Hire Tenant's Agent auction
                        </h4>
                        <div class="wizard-steps-progress">
                            <div class="steps-progress-percent"></div>
                        </div>
                        {{-- slide 1  --}}
                        <div class="wizard-step" data-step="1">
                            <div class="form-group">
                                <label class="fw-bold">Is the tenant currently represented by another agent?</label>
                                <select class="grid-picker" name="working_with_agent" id="working_with_agent" style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $yes_or_no)
                                    <option value="{{ $yes_or_no['name'] }}" data-target="{{ $yes_or_no['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                        {{ $yes_or_no['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="yes_message text-danger" style="font-size:18px; font-weight:bold;"></label>
                            </div>
                        </div>
                        {{-- slide 1  --}}

                        {{-- Slide 2 --}}
                        <div class="wizard-step" data-step="2">
                            <h4>Please provide the cities, counties, and state pertaining to the real estate location that the tenant
                                intends to lease a property:</h4>
                            <div class="form-group">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Cities:</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="input-cover input-cover-0 cityState"><label for="cities" class="input-icon"><i class="fa-solid fa-flag-usa "></i></label><input type="text" name="cities[]" placeholder="City" data-type="cities" id="cities" class="form-control  search_places" data-icon="" data-msg-required="Please enter city" required></div>

                                            </td>
                                        </tr>
                                        <tr class="city_btn_row">
                                            <td><button type="button" class="btn btn-secondary btn-sm w-100" onclick="add_city_row();"><i class="fa-solid fa-plus"></i> Add New
                                                    Row</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="form-group">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Counties:</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="input-cover input-cover-0 countyState"><label for="counties" class="input-icon"><i class="fa-solid fa-flag-usa "></i></label><input type="text" name="counties[]" placeholder="County" data-type="counties" id="counties" class="form-control  search_places" data-icon="fa-solid fa-city" data-msg-required="Please enter County" required></div>

                                            </td>
                                        </tr>
                                        <tr class="county_btn_row">
                                            <td><button type="button" class="btn btn-secondary btn-sm w-100" onclick="add_county_row();"><i class="fa-solid fa-plus"></i> Add New
                                                    Row</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">State:</label>
                                <input type="text" name="state" data-type="states" placeholder="State" id="state" class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa" data-msg-required="Please enter state" required>
                            </div>

                        </div>
                        {{-- Slide 2 --}}
                        {{-- Slide 3 --}}
                        <div class="wizard-step" data-step="3">
                            <div class="form-group">
                                <label for="address" class="fw-bold">Listing Date:</label>
                                <input type="date" name="listing_date" id="listing_date" class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days" data-msg-required="Please enter listing date" min="{{ date('Y-m-d') }}" required>
                            </div>

                            <div class="form-group">
                                <label for="address" class="fw-bold">Expiration Date:</label>
                                <input type="date" name="expiration_date" id="expiration_date" class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days" data-msg-required="Please enter expiration date" min="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        {{-- Slide 3 --}}
                        {{-- Slide 4 --}}
                        <div class="wizard-step" data-step="4">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Listing Type:
                                </label>
                                <div>
                                    @php
                                    $auction_types = [['name' => 'Auction (Timer)', 'icon' => '<i class="fa-regular fa-clock"></i>', 'target' => ''], ['name' => 'Traditional (No Timer)', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>', 'target' => '']];
                                    @endphp
                                    <select name="auction_type" id="auction_type" class="grid-picker" style="justify-content: flex-start;" onchange="changeAuctionType(this.value);" required>
                                        <option value=""></option>
                                        @foreach ($auction_types as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row " style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group auction_length_cover">
                                <label class="fw-bold">
                                    Auction Length:
                                </label>
                                <div>
                                    @php
                                    $auction_lengths = [['name' => '1 Day', 'class' => 'normal-length'], ['name' => '3 Days', 'class' => 'normal-length'], ['name' => '5 Days', 'class' => 'normal-length'], ['name' => '7 Days', 'class' => 'normal-length'], ['name' => '10 Days', 'class' => 'normal-length'], ['name' => '14 Days', 'class' => 'normal-length'], ['name' => '21 Days', 'class' => 'normal-length'], ['name' => '30 Days', 'class' => 'normal-length'], ['name' => '45 Days', 'class' => 'normal-length'], ['name' => '60 Days', 'class' => 'normal-length'], ['name' => '75 Days', 'class' => 'normal-length'], ['name' => '90 Days', 'class' => 'normal-length'], ['name' => 'No time limit', 'class' => 'traditional-length']];
                                    @endphp
                                    <select name="auction_length" id="auction_length" class="auction_length grid-picker" style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($auction_lengths as $item)
                                        <option value="{{ $item['name'] }}" data-target="" class="card flex-row  {{ $item['class'] }}" style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                        </div>
                        {{-- Slide 4 --}}
                        {{-- Slide 5 --}}
                        <div class="wizard-step" data-step="5">
                            <div class="form-group">
                                <label for="listing_title" class="fw-bold">Title of Listing:</label>
                                <input type="text" name="listing_title" id="listing_title" placeholder="Enter Title of Listing" class="form-control has-icon" data-icon="fa-solid fa-qrcode" data-msg-required="Please enter Listing  Title" required>
                            </div>
                        </div>
                        {{-- Slide 5 --}}
                        {{-- Slide 6 --}}
                        <div class="wizard-step" data-step="6">
                            @php
                            $property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];

                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Please select the property styles in which the tenant is interested in leasing:
                                </label>
                                <select class="grid-picker" name="property_type" id="property_type" onchange="changePropertyType(this.value);check_hoa();property_questions();">
                                    <option value="">Select</option>
                                    @foreach ($property_types as $row_pt)
                                    <option value="{{ $row_pt['name'] }}" class="card flex-column" style="width:calc(24% - 10px);" data-icon='<i class="fa-solid fa-hotel"></i>'>
                                        {{ $row_pt['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <div>
                                    @php
                                    $property_items = [
                                    ['name' => 'Apartments', 'class' => 'residential-length'],
                                    ['name' => 'Single Family Residence', 'class' => 'residential-length'],
                                    ['name' => 'Townhouse', 'class' => 'residential-length'],
                                    ['name' => 'Villa', 'class' => 'residential-length'],
                                    ['name' => 'Condominium', 'class' => 'residential-length'],
                                    ['name' => 'Condo-Hotel', 'class' => 'residential-length'],
                                    ['name' => '½ Duplex', 'class' => 'residential-length'],
                                    ['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                                    ['name' => 'Farm', 'class' => 'residential-length'],
                                    ['name' => 'Garage Condo', 'class' => 'residential-length'],
                                    ['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
                                    ['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
                                    ['name' => 'Modular Home', 'class' => 'residential-length'],
                                    ['name' => '1/3 Triplex', 'class' => 'residential-length'],
                                    ['name' => ' 1/4 Quadplex', 'class' => 'residential-length'],
                                    ['name' => 'Duplex', 'class' => 'income-length'],
                                    ['name' => 'Triplex', 'class' => 'income-length'],
                                    ['name' => 'Quadplex', 'class' => 'income-length'],
                                    ['name' => 'Five or More (Residential units)', 'class' => 'income-length'],
                                    ['name' => 'Agriculture', 'class' => 'commercial-length'],
                                    ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                                    ['name' => 'Business', 'class' => 'commercial-length'],

                                    ['name' => 'Five or More ', 'class' => 'commercial-length'],
                                    ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                                    ['name' => 'Industrial', 'class' => 'commercial-length'],
                                    ['name' => 'Mixed Use', 'class' => 'commercial-length'],
                                    ['name' => 'Office', 'class' => 'commercial-length'],
                                    ['name' => 'Restaurant', 'class' => 'commercial-length'],
                                    ['name' => 'Retail', 'class' => 'commercial-length'],
                                    ['name' => 'Unimproved Land', 'class' => 'residential-length'],
                                    ['name' => 'Warehouse', 'class' => 'commercial-length'],
                                    ];
                                    @endphp
                                    <select name="property_items" id="property_items" class="property_items grid-picker" style="justify-content: flex-start;" multiple required>
                                        <option value=""></option>
                                        @foreach ($property_items as $item)
                                        <option value="{{ $item['name'] }}" data-target="" class="card flex-row {{ $item['class'] }}" style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="7">
                            @php
                            $lease = [['name' => 'Entire Property', 'target' => ''], ['name' => 'Single Room', 'target' => ''], ['name' => 'Open to Leasing Either the Entire Property or Single Room', 'target' => '']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Is the tenant looking to lease the entire property, a single room, or is the
                                    tenant open to leasing either the entire property or a single room?</label>
                                <select class="grid-picker" name="tenant_lease" id="bedrooms" style="" required>
                                    <option value="">Select</option>
                                    </option>
                                    @foreach ($lease as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="8">
                            @php
                            $property_condition = [['name' => 'New Construction', 'target' => ''], ['name' => 'Completely Updated: No updates needed.', 'target' => ''], ['name' => 'Semi-updated: Needs minor updates.', 'target' => ''], ['name' => 'Not Updated: Requires a complete update.', 'target' => ''], ['name' => 'Open to any type of property condition.', 'target' => ''], ['name' => 'Other ', 'target' => '.other_property_condition']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Please select the property conditions in which the tenant is interested in
                                    leasing: </label>
                                <select class="grid-picker" name="condition_prop" id="condition_prop" style="" required>
                                    <option value="">Select</option>
                                    @foreach ($property_condition as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                                <div class="form-group other_property_condition d-none">
                                    <label class="fw-bold" for="other_property_condition">Acceptable Property Condition:</label>
                                    <input type="number" name="other_property_condition" id="other_property_condition" placeholder="" class="form-control has-icon" data-icon="" data-msg-required="Please enter Property Condition" required>
                                </div>
                            </div>
                        </div>

                        {{-- Slide 6 --}}
                        @php
                        $bedrooms = [['name' => '1', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Other', 'target' => '.other_bedrooms']];

                        // residential bedrooms
                        $bedroomsRes = [['name' => '1', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Other', 'target' => '.other_bedrooms_res']];

                        $bathrooms = [['name' => '1', 'target' => ''], ['name' => '1.5', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '2.5', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '3.5', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '4.5', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Other', 'target' => '.other_bathrooms']];
                        @endphp
                        <div class="wizard-step" data-step="9">
                            {{-- <h4>Property Characteristics and Amenities:</h4> --}}
                            <div class="form-group commercial_hide">
                                <label class="fw-bold">How many bedrooms does the tenant need?</label>
                                <select class="grid-picker" name="bedrooms" id="bedrooms" style="justify-content: center;" required>
                                    <option value="">Select</option>
                                    @foreach ($bedrooms as $bedroom)
                                    <option value="{{ $bedroom['name'] }}" data-target="{{ $bedroom['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-solid fa-bed"></i>'>
                                        {{ $bedroom['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group other_bedrooms d-none">
                                <label class="fw-bold" for="other_bedrooms">How many bedrooms does the tenant need?</label>
                                <input type="number" name="other_bedrooms" id="other_bedrooms" placeholder="Custom Bedrooms" class="form-control has-icon" data-icon="fa-solid fa-bed" data-msg-required="Please enter Custom Bedrooms" required>
                            </div>
                            {{-- Resdintial bedrooms --}}

                            <div class="form-group bedroomRes">
                                <label class="fw-bold"> How many bathrooms does the tenant need? </label>
                                <select class="grid-picker" name="bathrooms" id="bedrooms" style="" required>
                                    <option value="">Select</option>
                                    @foreach ($bedroomsRes as $bedroomRes)
                                    <option value="{{ $bedroomRes['name'] }}" data-target="{{ $bedroomRes['target'] }}" class="card flex-column" style="width:calc(10% - 10px);" data-icon='<i class="fa-solid fa-bed"></i>'>
                                        {{ $bedroomRes['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group other_bedrooms_res d-none">
                                <label class="fw-bold" for="other_bedrooms">How many bathrooms does the tenant need? </label>
                                <input type="number" name="other_bathrooms" id="other_bedrooms_res" placeholder="Custom Bedrooms" class="form-control has-icon" data-icon="fa-solid fa-bed" data-msg-required="Please enter Custom Bedrooms" required>
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">How many bathrooms does the tenant need?</label>
                                <select class="grid-picker" name="bathrooms" id="bathrooms" style="justify-content: center;" required>
                                    <option value="">Select</option>
                                    @foreach ($bathrooms as $bathroom)
                                    <option value="{{ $bathroom['name'] }}" data-target="{{ $bathroom['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-solid fa-bath"></i>'>
                                        {{ $bathroom['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group other_bathrooms d-none">
                                <label class="fw-bold" for="other_bathrooms">How many bathrooms does the tenant need?
                                </label>
                                <input type="number" name="other_bathrooms" id="other_bathrooms" placeholder="Custom Bathrooms" class="form-control has-icon" data-icon="fa-solid fa-bath" data-msg-required="Please enter Custom Bathrooms" required>
                            </div>
                        </div>
                        {{-- 14 Jul 2023 --}}


                        <div class="wizard-step" data-step="10">
                            <div class="form-group  removeCommercial">
                                <label class="fw-bold">What is the minimum heated square footage required?</label>
                                <input type="number" name="minimum_heated_square" id="other_bathrooms" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                            </div>
                            <div class="form-group removeResidential">
                                <label class="fw-bold">What is the minimum net leasable square footage required?</label>
                                <input type="number" name="minimum_net_leasable_square" id="other_bathrooms" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                            </div>
                        </div>
                        {{-- 14 Jul 2023 --}}
                        {{-- 14 Jul 2023 --}}
                        <div class="wizard-step" data-step="11">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Does the tenant require furnished, optional, partial, turnkey, or unfurnished accommodations?
                                </label>
                                @php
                                $tenant_require = [['target' => '', 'name' => 'Furnished'], ['target' => '', 'name' => 'Optional'], ['target' => '', 'name' => 'Partial'], ['target' => '', 'name' => 'Turnkey'], ['target' => '', 'name' => 'Unfurnished']];
                                @endphp
                                <select name="tenant_require" id="tenant_require" class="grid-picker" style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($tenant_require as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="12">
                            <span class="resFields">
                                @php
                                $acreageRes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ acres', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
                                @endphp

                                <div class="form-group ">
                                    <label class="fw-bold">What is the minimum total acreage required?</label>
                                    <select class="grid-picker" name="total_acreage" id="total_acreage" style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($acreageRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </span>
                        </div>
                        <div class="wizard-step" data-step="13">

                            @php
                            $garageOptions = [['target' => '.garageYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '.garageNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'], ['target' => '.garageOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle']];
                            @endphp
                            <div class="row align-items-end mt-4">
                                <div class="col-md-12">
                                    <label class="fw-bold" for="heated_sqft">Garage Needed?</label>
                                    <div class="select2-parent">
                                        <select name="garageOptions" class="grid-picker" id="" required>
                                            @foreach ($garageOptions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column " style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                            @endforeach
                                        </select>
                                        <div class="form-group garageYes d-none">
                                            <label class="fw-bold" for="heated_sqft">How many garage spaces? </label>
                                            <input type="number" name="custom_garage" id="total_acreage" class="form-control has-icon hide_arrow" data-icon="" required>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            @php
                            $carportOptions = [['target' => '.carportOptionsYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '.carportOptionsNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'], ['target' => '.carportOptionsOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle']];
                            @endphp
                            <div class="row align-items-end mt-4">
                                <div class="col-md-12">
                                    <labal class="fw-bold" for="heated_sqft">Carport Needed?</labal>
                                    <div class="select2-parent">
                                        <select name="carportOptions" class="grid-picker" id="" required>
                                            @foreach ($carportOptions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column " style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                            @endforeach
                                        </select>

                                        <div class="form-group carportOptionsYes d-none">
                                            <label class="fw-bold" for="heated_sqft">How many carport spaces? </label>
                                            <input type="number" name="custom_carport" id="total_acreage" class="form-control has-icon hide_arrow" data-icon="" required>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="form-group removeResidential">
                                @php
                                $garageOption = [['target' => '.garageOptionYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '.garageOptionNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'], ['target' => '.garageOptionOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle']];
                                @endphp
                                <label class="fw-bold">Garage/Parking Features Needed?</label>
                                <select name="garageOption" class="grid-picker" id="garage" style="justify-content: flex-start;" required>
                                    @foreach ($garageOption as $item)
                                    <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>' data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                                @php
                                $garageParking = [['name' => '1 to 5 Spaces', 'target' => ''], ['name' => '6 to 12 Spaces', 'target' => ''], ['name' => '13 to 18 Spaces', 'target' => ''], ['name' => '19 to 30 Spaces', 'target' => ''], ['name' => 'Airplane Hangar', 'target' => ''], ['name' => 'Common', 'target' => ''], ['name' => 'Curb Parking', 'target' => ''], ['name' => 'Deeded', 'target' => ''], ['name' => 'Electric Vehicle Charging Station(s)', 'target' => ''], ['name' => 'Ground Level', 'target' => ''], ['name' => 'Lighted', 'target' => ''], ['name' => 'Over 30 Spaces', 'target' => ''], ['name' => 'RV Parking', 'target' => ''], ['name' => 'Secured', 'target' => ''], ['name' => 'Under Building', 'target' => ''], ['name' => 'Underground', 'target' => ''], ['name' => 'Valet', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.other_garage']];
                                @endphp
                                <div class="form-group d-none garageOptionYes"">
                    <select class=" grid-picker" name="garage_parking" style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($garageParking as $total_acreage)
                                    <option value="{{ $item['name'] }}" data-target="{{ $total_acreage['target'] }}" class="card flex-column" style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                        {{ $total_acreage['name'] }}
                                    </option>
                                    @endforeach
                                    </select>
                                    <div class="form-group other_garage d-none">
                                        <label class="fw-bold" for="other_garage">Garage/Parking Features :</label>
                                        <input type="number" name="other_garage" class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- laskdfj --}}
                        <div class="wizard-step" data-step="14">

                            @php
                            $poolOptions = [['target' => '.poolYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '.poolNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'], ['target' => '.poolOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle']];
                            @endphp
                            <div class="row align-items-end mt-4 removeCommercial">
                                <div class="col-md-12">
                                    <label class="fw-bold" for="heated_sqft">Pool Needed?</label>
                                    <div class="select2-parent">
                                        <select name="poolOptions" class="grid-picker" id="" required>
                                            @foreach ($poolOptions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column " style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>

                                </div>
                            </div>
                            @php
                            $prefrenceOptions = [['target' => '.preferenceYesIncome', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '.preferenceNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'], ['target' => '.preferenceOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle']];
                            @endphp
                            <div class="row align-items-end mt-4">
                                <div class="col-md-12">
                                    <label class="fw-bold" for="heated_sqft">View Preference Needed? </label>
                                    <div class="select2-parent">
                                        <select name="prefrenceOptions" class="grid-picker" id="" required>
                                            @foreach ($prefrenceOptions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column " style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                @php
                                $ifYesPreference = [['target' => '', 'name' => 'City', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'Garden', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'Golf Course', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'Greenbelt', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'Mountain(s)', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'Park', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'Tennis Court', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'Trees/Woods', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'Water', 'icon' => 'fa-regular fa-check-circle'], ['target' => '.preferenceNo', 'name' => 'Beach', 'icon' => 'fa-regular fa-circle-xmark'], ['target' => '.preferenceOtherIncome', 'name' => ' Other', 'icon' => 'fa-regular fa-check-circle']];
                                @endphp
                                <div class="select2-parent preferenceYesIncome d-none">
                                    <select name="prefrenceYes" class="grid-picker" id="" required>
                                        @foreach ($ifYesPreference as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column " style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group preferenceOtherIncome d-none">
                                        <label class="fw-bold" for="heated_sqft">View Preference Needed:</label>
                                        <input type="number" name="preferenceOther" id="total_acreage" class="form-control has-icon hide_arrow" data-icon="" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="15">
                            @php
                            $petOptions = [['target' => '.petYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '.petNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                            @endphp
                            <div class="row align-items-end mt-4">
                                <div class="col-md-12">
                                    <labal class="fw-bold" for="heated_sqft">Does the tenant have a pet?</labal>
                                    <div class="select2-parent">
                                        <select name="petOptions" class="grid-picker" id="" required>
                                            @foreach ($petOptions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column " style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group petYes d-none">
                                        <div class="form-group">
                                            <label class="fw-bold" for="heated_sqft">How many pets does the buyer have?</label>
                                            <input type="text" name="petsNumber" id="total_acreage" class="form-control has-icon hide_arrow" data-icon="" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="fw-bold" for="heated_sqft">What type of pet(s) does the buyer have?</label>
                                            <input type="text" name="petsType" id="total_acreage" class="form-control has-icon hide_arrow" data-icon="" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="fw-bold" for="heated_sqft">What breed(s) are the pet(s)?</label>
                                            <input type="text" name="petsBreed" id="total_acreage" class="form-control has-icon hide_arrow" data-icon="" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="fw-bold" for="heated_sqft">What is the weight of the pet(s)?</label>
                                            <input type="text" name="petsWeight" id="total_acreage" class="form-control has-icon hide_arrow" data-icon="" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        @php
                                        $purchasing_props = [['target' => '.purchasingPropsYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '.purchasingPropsNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                                        @endphp
                                        <label class="fw-bold" for="heated_sqft">Is the tenant eligible or interested in leasing a
                                            property in 55+ and over communities? </label>
                                        <div class="select2-parent">
                                            <select name="purchasing_props" class="grid-picker" id="" required>
                                                @foreach ($purchasing_props as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column " style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        {{-- 14 Jul 2023 --}}
                        {{-- 14 Jul 2023 --}}
                        <div class="wizard-step" data-step="16">
                            <span class="commercialFields">
                                {{-- for commertial --}}
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Are there any non-negotiable amenities or property features that the tenant is seeking?
                                    </label>
                                    @php
                                    $non_negotialble = [['target' => '.negotiable_terms_yes', 'name' => 'Yes'], ['target' => '', 'name' => 'No']];
                                    @endphp
                                    <select name="has_non_negotiable_terms" id="has_non_negotiable_terms" class="grid-picker" style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group  negotiable_terms_yes">
                                    <label class="fw-bold">
                                        What are the non-negotiable amenities or property features that the tenant is seeking?
                                    </label>
                                    @php
                                    $non_negotialble_terms_commercial = [
                                    ['target' => '', 'name' => 'Parking Spaces'],
                                    ['target' => '', 'name' => 'Loading Dock'],
                                    ['target' => '', 'name' => 'Warehouse Space'],
                                    ['target' => '', 'name' => 'Office Space'],
                                    ['target' => '', 'name' => 'Conference Room'],
                                    ['target' => '', 'name' => 'Kitchenette/Break Room'],
                                    ['target' => '', 'name' => 'Restrooms'],
                                    ['target' => '', 'name' => 'Elevator'],
                                    ['target' => '', 'name' => 'Handicap Accessibility'],
                                    ['target' => '', 'name' => 'Security System'],
                                    ['target' => '', 'name' => 'On-site Maintenance'],
                                    ['target' => '', 'name' => 'On-site Management'],
                                    ['target' => '', 'name' => 'Outdoor Space/Garde'],
                                    ['target' => '', 'name' => 'Signage Opportunities'],
                                    ['target' => '', 'name' => 'High-Speed Internet'],
                                    ['target' => '', 'name' => 'Utilities Included'],
                                    ['target' => '', 'name' => 'HVAC System'],
                                    ['target' => '', 'name' => 'Natural Lighting'],
                                    ['target' => '', 'name' => 'Storage Space'],
                                    ['target' => '', 'name' => 'Open Floor Plan'],
                                    ['target' => '', 'name' => 'Retail Frontage'],
                                    ['target' => '', 'name' => 'Restaurant Space'],
                                    ['target' => '', 'name' => 'Industrial Features'],
                                    ['target' => '', 'name' => 'Flexibility for Renovations'],
                                    ['target' => '', 'name' => 'Common Areas'],
                                    ['target' => '', 'name' => 'Business Center'],
                                    ['target' => '', 'name' => 'Gym/Fitness Facilities'],
                                    ['target' => '', 'name' => 'Lounge Area'],
                                    ['target' => '', 'name' => 'Reception Area'],
                                    ['target' => '', 'name' => 'Security Guard'],
                                    ['target' => '', 'name' => 'Fire Safety Systems'],
                                    ['target' => '', 'name' => 'Green Building Certification'],
                                    ['target' => '', 'name' => 'Access to Public Transportation'],
                                    ['target' => '', 'name' => 'Proximity to Highways'],
                                    ['target' => '', 'name' => 'Visibility from Main Road'],
                                    ['target' => '.negotiable_terms_commercial', 'name' => 'Other'],
                                    ];
                                    @endphp
                                    <select name="has_non_negotiable_terms" id="negotiable_terms" class="grid-picker" style="justify-content: flex-start;" multiple required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble_terms_commercial as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group negotiable_terms_commercial d-none">
                                    <label class="fw-bold" for="custom_non_negotiable_terms"> What are the non-negotiable amenities or
                                        property
                                        features that the tenant is seeking?
                                    </label>
                                    <input type="text" name="custom_non_negotiable_terms" id="custom_non_negotiable_terms" placeholder="Non Negotiable Terms" class="form-control" data-icon="fa-solid fa-bath" required>
                                </div>
                            </span>
                        </div>
                        {{-- 14 Jul 2023 --}}
                        <div class="wizard-step" data-step="17">
                            <span class="resFields">
                                <div class="form-group residential_and_income">
                                    <label class="fw-bold">
                                        Are there any non-negotiable amenities or property features that the tenant is seeking?

                                    </label>
                                    @php
                                    $non_negotialble = [['target' => '.negotiable_terms', 'name' => 'Yes'], ['target' => '', 'name' => 'No']];
                                    @endphp
                                    <select name="has_non_negotiable_terms" id="has_non_negotiable_terms" class="grid-picker" style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group residential_and_income negotiable_terms">
                                    <label class="fw-bold">
                                        What are the non-negotiable amenities or property features that the tenant is seeking?
                                    </label>
                                    @php
                                    $non_negotialble_terms_residential = [
                                    ['target' => '', 'name' => 'Garage'],
                                    ['target' => '', 'name' => 'Carport'],
                                    ['target' => '', 'name' => 'Pool'],
                                    ['target' => '', 'name' => 'Waterfront'],
                                    ['target' => '', 'name' => 'In-Unit Laundry'],
                                    ['target' => '', 'name' => 'On-site Laundry'],
                                    ['target' => '', 'name' => 'Washer and Dryer Hookup'],
                                    ['target' => '', 'name' => 'Washer and Dryer'],
                                    ['target' => '', 'name' => 'Covered Carport'],
                                    ['target' => '', 'name' => 'First Floor Unit'],
                                    ['target' => '', 'name' => 'Elevator'],
                                    ['target' => '', 'name' => 'Pet Friendly'],
                                    ['target' => '', 'name' => 'Balcony/Patio'],
                                    ['target' => '', 'name' => 'Fitness Center/Gym'],
                                    ['target' => '', 'name' => 'Central Heating'],
                                    ['target' => '', 'name' => 'Central Air Conditioning'],
                                    ['target' => '', 'name' => 'Fireplace'],
                                    ['target' => '', 'name' => 'Walk-in Closet'],
                                    ['target' => '', 'name' => 'Hardwood Floors'],
                                    ['target' => '', 'name' => 'Tile Floors'],
                                    ['target' => '', 'name' => 'Carpet Floors '],
                                    ['target' => '', 'name' => 'Security System'],
                                    ['target' => '', 'name' => 'Gated Community'],
                                    ['target' => '', 'name' => 'HOA Community'],
                                    ['target' => '', 'name' => '55 and Over Community'],
                                    ['target' => '', 'name' => 'Specific School District'],
                                    ['target' => '', 'name' => 'Accessibility Features'],
                                    ['target' => '', 'name' => 'On-site Maintenance'],
                                    ['target' => '', 'name' => 'On-site Management'],
                                    ['target' => '', 'name' => 'Outdoor Space'],
                                    ['target' => '', 'name' => 'Playground'],
                                    ['target' => '', 'name' => 'Clubhouse'],
                                    ['target' => '', 'name' => 'Storage Space'],
                                    ['target' => '', 'name' => 'Study/Den/Office'],
                                    ['target' => '', 'name' => 'Updated Kitchen'],
                                    ['target' => '', 'name' => 'Updated Bathroom'],
                                    ['target' => '.negotiable_terms_res', 'name' => 'Other'],
                                    ];
                                    @endphp
                                    <select name="non_negotiable_terms" id="negotiable_terms" class="grid-picker" style="justify-content: flex-start;" multiple required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble_terms_residential as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group negotiable_terms_res d-none">
                                    <label class="fw-bold" for="custom_negotiable_terms"> What are the non-negotiable amenities or
                                        property
                                        features that the tenant is seeking?
                                    </label>
                                    <input type="text" name="custom_negotiable_terms" id="custom_negotiable_terms" placeholder="Non Negotiable Terms" class="form-control" data-icon="fa-solid fa-bath" required>
                                </div>
                            </span>
                        </div>
                        {{-- 14 Jul 2023  --}}
                        <div class="wizard-step" data-step="18">
                            <div class="form-group">
                                <label class="fw-bold">
                                    How much is the tenant’s budget?
                                </label>
                                <input type="number" name="budget" id="budget" placeholder="" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                            </div>

                        </div>
                        {{-- 14 Jul 2023  --}}
                        {{-- 14 Jul 2023  --}}
                        <div class="wizard-step" data-step="19">
                            <div class="form-group">
                                <label for="address" class="fw-bold"> When is the tenant looking to lease by?</label>
                                <input type="date" name="lease_by" id="lease_by" class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days" data-msg-required="When is the tenant looking to lease by?" required>
                            </div>

                        </div>
                        {{-- 14 Jul 2023  --}}
                        {{-- 14 Jul 2023  --}}
                        <div class="wizard-step" data-step="20">
                            <div class="form-group">
                                <label class="fw-bold">
                                    How long would the tenant like to lease for?
                                </label>
                                @php
                                $lease_for = [['target' => '', 'name' => '3 months'], ['target' => '', 'name' => '6 months'], ['target' => '', 'name' => '9 months'], ['target' => '', 'name' => '1 year'], ['target' => '', 'name' => '2 years'], ['target' => '', 'name' => '3-5 years'], ['target' => '', 'name' => '5+ years'], ['target' => '.custom_lease_for', 'name' => 'Other']];
                                @endphp
                                <select name="lease_for" id="lease_for" class="grid-picker" style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($lease_for as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_lease_for d-none">
                                <label class="fw-bold" for="custom_lease_for">How long would the tenant like to lease
                                    for?</label>
                                <input type="text" name="custom_lease_for" id="custom_lease_for" placeholder="" class="form-control" data-icon="fa-solid fa-bath" required>
                            </div>

                        </div>
                        {{-- 14 Jul 2023  --}}
                        {{-- 14 Jul 2023  --}}
                        <div class="wizard-step" data-step="21">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Does the tenant have a pet, and if so, how many and what type of pet?
                                </label>
                                @php
                                $has_pets = [['target' => '', 'name' => 'Yes, 1 Dog'], ['target' => '', 'name' => 'Yes, 1 Cat'], ['target' => '', 'name' => 'Yes, 2+ Dogs'], ['target' => '', 'name' => 'Yes, 2+ Cats'], ['target' => '', 'name' => 'No'], ['target' => '.custom_has_pets', 'name' => 'Other']];
                                @endphp
                                <select name="has_pets" id="has_pets" class="grid-picker" style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($has_pets as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_has_pets d-none">
                                <label for="custom_has_pets" class="fw-bold">Does the tenant have a pet, and if so, how many and what
                                    type of pet?</label>
                                <input type="text" name="custom_has_pets" id="custom_has_pets" placeholder="" class="form-control" data-icon="fa-solid fa-bath" required>
                            </div>

                        </div>
                        {{-- 14 Jul 2023  --}}
                        {{-- 14 Jul 2023  --}}
                        <div class="wizard-step" data-step="22">
                            <div class="form-group">
                                <label class="fw-bold">
                                    What is the tenant's credit score rating?
                                </label>
                                @php
                                $credit_score = [['target' => '', 'name' => 'Poor'], ['target' => '', 'name' => 'Fair'], ['target' => '', 'name' => 'Good'], ['target' => '', 'name' => 'Excellent']];
                                @endphp
                                <select name="credit_score" id="credit_score" class="grid-picker" style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($credit_score as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>



                        </div>
                        {{-- 14 Jul 2023  --}}
                        {{-- 14 Jul 2023  --}}
                        <div class="wizard-step" data-step="23">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Has the tenant been evicted within the last 7 years?
                                </label>
                                @php
                                $evicted = [['target' => '.custom_evicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                                @endphp
                                <select name="evicted" id="evicted" class="grid-picker" style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($evicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_evicted d-none">
                                <label class="fw-bold" for="custom_evicted">Explain when and why:</label>
                                <input type="text" name="custom_evicted" id="custom_evicted" placeholder="Explain when and why" class="form-control" data-icon="fa-solid fa-bath" required>
                            </div>

                        </div>
                        {{-- 14 Jul 2023  --}}
                        {{-- 14 Jul 2023  --}}
                        <div class="wizard-step" data-step="24">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Has the tenant been convicted of a felony within the last 7 years?
                                </label>
                                @php
                                $convicted = [['target' => '.custom_convicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                                @endphp
                                <select name="convicted" id="convicted" class="grid-picker" style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($convicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_convicted d-none">
                                <label class="fw-bold" for="custom_convicted">Explain when and what for:</label>
                                <input type="text" name="custom_convicted" id="custom_convicted" placeholder="Explain when and what for" class="form-control" data-icon="fa-solid fa-bath" required>
                            </div>

                        </div>
                        {{-- 14 Jul 2023  --}}
                        <div class="wizard-step" data-step="25">
                            <div class="form-group">
                                <label class="fw-bold">
                                    What is the tenant's monthly net household income?
                                </label>
                                <input type="number" name="monthly_income" id="monthly_income" placeholder="" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                            </div>

                        </div>
                        <div class="wizard-step" data-step="26">
                            <div class="form-group">
                                <label class="fw-bold">
                                    What is the timeframe offered to the agent in the Tenant Agency agreement?
                                </label>
                                @php
                                $tenant_terms = [['target' => '', 'name' => '1 month'], ['target' => '', 'name' => '2 months'], ['target' => '', 'name' => '3 months'], ['target' => '', 'name' => '4 months'], ['target' => '', 'name' => '5 months'], ['target' => '', 'name' => '6 months'], ['target' => '', 'name' => '9 months'], ['target' => '', 'name' => '12 months'], ['target' => '.custom_tenant_terms', 'name' => 'Other']];
                                @endphp
                                <select name="tenant_terms" id="tenant_terms" class="grid-picker" style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($tenant_terms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_tenant_terms d-none">
                                <label class="fw-bold" for="custom_tenant_terms">What is the timeframe offered to the agent in the
                                    Tenant Agency agreement?</label>
                                <input type="text" name="custom_tenant_terms" id="custom_tenant_terms" placeholder="" class="form-control" data-icon="fa-solid fa-bath" required>
                            </div>

                        </div>
                        <div class="wizard-step" data-step="27">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Would the tenant be willing to pay a finder’s fee to the agent for their assistance in finding them a
                                    property?
                                </label>
                                @php
                                $find_property = [['target' => '.costom_find_property', 'id' => 'costom_find_property', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark', 'id' => '']];
                                @endphp
                                <select name="find_property" id="find_property" class="grid-picker" style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($find_property as $item)
                                    <option value="{{ $item['name'] }}" id="{{ $item['id'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group costom_find_property d-none">
                                <label class="fw-bold" for="costom_find_property">What is the offered finder’s fee the tenant is
                                    willing to pay the agent for their assistance in finding them a property?</label>
                                @php
                                $property_fee = [['target' => '.costom_property_fee', 'name' => "1 month's rent"], ['target' => '', 'name' => "50% of one month's rent"], ['target' => '', 'name' => '10% of the value of the lease'], ['target' => '', 'name' => '6% of the value of the lease'], ['target' => '', 'name' => 'Negotiable'], ['target' => '.Other_property_fee', 'name' => 'Other']];
                                @endphp
                                <select name="property_fee" class="grid-picker" style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($property_fee as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column" style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-circle-mark" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                                <div class="form-group Other_property_fee d-none">
                                    <label class="fw-bold" for="custom_convicted">What is the offered fee the tenant is willing to pay
                                        the agent for their assistance in finding them a property? </label>
                                    <br>
                                    <div class="form-group">
                                        <label class="fw-bold">$ </label>
                                        <input type="text" name="Other_property_fee" id="Other_property_fee" placeholder="" class="form-control" data-icon="fa-solid fa-bath" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="fw-bold">% of the value of the lease</label>
                                        <input type="text" name="Other_property_value" id="Other_property_value" placeholder="" class="form-control" data-icon="fa-solid fa-bath" required>
                                    </div>
                                    Note:Typically, a landlord pays their listing agent a commission, which is then split with a tenant's
                                    agent.
                                    However, in some areas, this commission is not evenly divided and can be as low as $25 to $50. As a
                                    result, some agents do not represent tenants due to this issue, or they may charge a tenant a finder’s
                                    fee.
                                </div>
                            </div>

                        </div>
                        <div class="wizard-step" data-step="28">
                            <div class="form-group">
                                <label class="fw-bold" for="important_aspects">What are the most important aspects the tenant will
                                    consider when hiring a real estate agent?</label>
                                <textarea class="form-control" type="text" name="important_aspects" rows="5" id="important_aspects" required></textarea>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="additional_details">What additional details would the tenant like to share
                                    with the agent?</label>
                                <textarea class="form-control" type="text" name="additional_details" rows="5" id="additional_details" required></textarea>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="29">
                            @php
                            $services_data = [
                            ['target' => '', 'name' => 'List the tenant’s property on the platform BidYourOffer.com.'],
                            ['target' => '', 'name' => "Send prompt email notifications containing properties that meet the tenant's criteria as soon as they are listed, ensuring access to the most up-to-date listings."],
                            ['target' => '', 'name' => 'Market the tenant’s property on various groups, pages, and affiliates, along with a QR code or listing link that leads to the listing on BidYourOffer.com.'],
                            ['target' => '', 'name' => 'Promote the tenant’s property on social media platforms with a QR code or listing link that leads to the listing on BidYourOffer.com.'],
                            ['target' => '', 'name' => 'Schedule and accompany the tenant on property viewings and showings.'],
                            ['target' => '', 'name' => 'Schedule video tours of the tenant’s preferred properties.'],
                            ['target' => '', 'name' => "Assist with the tenant's rental application process, including providing guidance and support."],
                            ['target' => '', 'name' => 'Assist with the negotiation of lease terms, including rental price, lease duration, and any additional clauses or provisions.'],
                            ['target' => '', 'name' => 'Coordinate and oversee the move-in process, including inspections and key handovers.'],
                            ['target' => '', 'name' => 'Provide resources and recommendations for local amenities, such as schools, parks, and healthcare facilities.'],
                            ['target' => '', 'name' => 'Facilitate communication between the tenant and the landlord or property management company.'],
                            ['target' => '', 'name' => 'Offer resources and information on tenant rights and responsibilities.'],
                            ['target' => '', 'name' => 'Provide guidance on lease renewal options and negotiate rent adjustments if necessary.'],
                            ['target' => '.other_services', 'name' => 'Other - Add additional services as needed.'],
                            ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">What services would the tenant like to request from an agent?</label>
                                <select class="grid-picker" name="services[]" id="services" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($services_data as $service)
                                    <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}" class="card flex-row" style="width:calc(100% - 0px);" data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                                        {{ $service['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group other_services d-none">
                                <label class="fw-bold ">What services would the tenant like to request from tha agent?</label>
                                <input type="text" name="other_services" id="" class="form-control">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between form-group mt-4">
                            <div>
                                <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                            </div>
                            <div>
                                <button type="button" class="wizard-step-next btn btn-success btn-lg text-600" style="display: none;">Next</button>
                                <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600" style="display: none;">Save</button>
                            </div>
                        </div>

                        {{-- 14 Jul 2023 --}}
                        {{-- 14 Jul 2023 --}}

                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<template class="city_temp">
    <tr>

        <td>
            <div class="form-group">
                <div class="input-cover input-cover-0"><label for="cities" class="input-icon"><i class="fa-solid fa-flag-usa "></i></label><input type="text" name="cities[]" placeholder="City" data-type="cities" class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city" data-msg-required="Please enter city"></div>
            </div>
        </td>
    </tr>
</template>
<template class="county_temp">
    <tr>
        <td>
            <div class="input-cover input-cover-0"><label for="county" class="input-icon"><i class="fa-solid fa-flag-usa "></i></label><input type="text" id="county" name="counties[]" placeholder="County" data-type="counties" class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city" data-msg-required="Please enter County"></div>
        </td>
    </tr>
</template>
@endsection
@push('scripts')
<script>
    function add_county_row() {
        var county_row = $('.county_temp').html();
        $('.county_btn_row').before(county_row);
        initialize();
    }

    function add_city_row() {
        var city_row = $('.city_temp').html();
        $('.city_btn_row').before(city_row);
        initialize();
    }

    /* function add_state_row() {
        var state_row = $('.state_temp').html();
        $('.state_btn_row').before(state_row);
        initialize();
    } */
</script>
<script>
    function changeAuctionType(v) {
        if (v == "Auction (Timer)") {
            $('.auction_length').val("");
            $('.auction_length').parent().children('.option-container').removeClass('active');
            $('.traditional-length').hide();
            $('.normal-length').show();
            $('.auction_length_cover').show();

        } else {
            $('.auction_length').val("");
            $('.auction_length').parent().children('.option-container').removeClass('active');
            $('.traditional-length').show();
            $('.normal-length').hide();
            $('.auction_length_cover').hide();
        }
    }
    // document.getElementById('auction_type').change();
    $(function() {
        changeAuctionType("Auction (Timer)");
    });
</script>
<script>
    $('.exchange_trade_for').on('change', function() {
        var items = $(this).val();
        if (items == "Another home") {
            $('.custom_trade').addClass('d-none');

            $('.item_values').removeClass('d-none');
        } else if (items == "Vehicles") {
            $('.custom_trade').addClass('d-none');
            $('.item_values').removeClass('d-none');
        } else if (items == "Boats") {
            $('.custom_trade').addClass('d-none');
            $('.item_values').removeClass('d-none');
        } else if (items == "Motorhomes") {
            $('.custom_trade').addClass('d-none');
            $('.item_values').removeClass('d-none');
        } else if (items == "Artwork") {
            $('.custom_trade').addClass('d-none');
            $('.item_values').removeClass('d-none');
        } else if (items == "Jewelry") {
            $('.custom_trade').addClass('d-none');
            $('.item_values').removeClass('d-none');
        } else if (items == "Other") {
            $('.item_values').addClass('d-none');
            $('.custom_trade').removeClass('d-none');
        }
    });
    // function exchange_trade_for(p) {
    //     var selectedValue = selectElement.value;
    //     var selectedOption = selectElement.options[selectElement.selectedIndex];
    //     var target = selectedOption.getAttribute('data-target');

    //     alert('Selected Value: ' + selectedValue + '\nTarget: ' + target);
    // }
    // $(function() {
    //     exchange_trade_for("");
    //     });
</script>
<script>
    function changePropertyType(p) {
        if (p == "Residential Property") {
            $('.property_items').val("");
            $('.property_items').parent().children('.option-container').removeClass('active');
            $('.residential-length').show();
            $('.income-length').hide();
            $('.commercial-length').hide();
            $('.business_type_next').addClass('d-none');
            $('.business_type_next_hide').removeClass('d-none');
            $('.hide_vacant').addClass('d-none');
            $('.road_frontage_next_hide').removeClass('d-none');
            // $('.residential_and_income_hide').addClass('d-none');
            $('.residential_and_income').removeClass('d-none');
            $('.for_income_only').addClass('d-none');
            $('.for_residential_only').removeClass('d-none');
            $('.residential_hide').removeClass('d-none');
            $('.business-length').hide();
            $('.bedroomRes').removeClass('d-none');
            $('.commercial_hide').addClass('d-none');
            $('.commercial_remove').removeClass('d-none');
            $('.removeResidential').addClass('d-none');
            $('.removeCommercial').removeClass('d-none');
            $('.resFields').each(function() {
                $(this).find('select, input ,textarea').prop('disabled', false);
            });
            $('.commercialFields').each(function() {
                $(this).find('select, input ,textarea').prop('disabled', true);
            });


        } else if (p == "Income Property") {
            $('.property_items').val("");
            $('.property_items').parent().children('.option-container').removeClass('active');
            $('.residential-length').hide();
            $('.income-length').show();
            $('.commercial-length').hide();
            $('.residential_hide').removeClass('d-none');
            $('.residential_remove').remove();
            $('.vacant_land-length').hide();
            $('.business-length').hide();

        } else if (p == "Commercial Property") {
            $('.property_items').val("");
            $('.property_items').parent().children('.option-container').removeClass('active');
            $('.residential-length').hide();
            $('.income-length').hide();
            $('.commercial-length').show();
            $('.business_type_next').removeClass('d-none');
            $('.business_type_next_hide').addClass('d-none');
            $('.residential_and_income').addClass('d-none');
            $('.residential_hide').addClass('d-none');
            $('.vacant_land-length').hide();
            $('.business-length').hide();
            $('.commercial_hide').removeClass('d-none');
            $('.bedroomRes').addClass('d-none');
            $('.removeCommercial').addClass('d-none');
            $('.removeResidential').removeClass('d-none');
            $('.commercialFields').each(function() {
                $(this).find('select, input ,textarea').prop('disabled', false);
            });
            $('.resFields').each(function() {
                $(this).find('select, input ,textarea').prop('disabled', true);
            });

        } else if (p == "Vacant Land (Current Use)") {
            $('.property_items').val("");
            $('.property_items').parent().children('.option-container').removeClass('active');
            $('.residential-length').hide();
            $('.income-length').hide();
            $('.commercial-length').hide();
            $('.business_type_next').removeClass('d-none');
            $('.business_type_next_hide').addClass('d-none');
            $('.residential_and_income').addClass('d-none');
            $('.residential_hide').addClass('d-none');
            $('.vacant_land-length').show();
            $('.business-length').hide();

        } else if (p == "Business Opportunity") {
            $('.property_items').val("");
            $('.property_items').parent().children('.option-container').removeClass('active');
            $('.residential-length').hide();
            $('.income-length').hide();
            $('.commercial-length').hide();
            $('.business_type_next').removeClass('d-none');
            $('.business_type_next_hide').addClass('d-none');
            $('.residential_and_income').addClass('d-none');
            $('.residential_hide').addClass('d-none');
            $('.vacant_land-length').hide();
            $('.business-length').show();
        } else {
            $('.property_items').val("");
            $('.property_items').parent().children('.option-container').removeClass('active');
            $('.residential-length').hide();
            $('.income-length').hide();
            $('.commercial-length').hide();
            $('.business_type_next').addClass('d-none');
            $('.business_type_next_hide').removeClass('d-none');
            $('.hide_vacant').addClass('d-none');
            $('.road_frontage_next_hide').removeClass('d-none');
            $('.vacant_land-length').hide();
            $('.business-length').hide();

        }
    }
    // document.getElementById('auction_type').change();
    $(function() {
        changePropertyType("");
    });
</script>
<script>
    function changePropertyStyle(p) {
        if (p == "Vacant Land") {
            // alert('ok');
            $('.property_style_next_hide').addClass('d-none');
            $('.road_frontage_next_hide').addClass('d-none');
            $('.business_opportunity_show').addClass('d-none');
            $('.vacant_land_show').removeClass('d-none');
            $('.hide_vacant').removeClass('d-none');
            $('.show_vacant').removeClass('d-none');
            $('.vacant_land_hide').remove();
            // $('.remove_business_opportunity').remove();
            $('.business_opportunity_remove').show();

        } else {
            $('.vacant_land_show').addClass('d-none');
            $('.hide_vacant').addClass('d-none');
            $('.road_frontage_next_hide').removeClass('d-none');
            $('.business_opportunity_show').removeClass('d-none');
            $('#remove_pets_question').remove();
            // $('.vacant_land_remove').remove();
            $('.show_vacant').addClass('d-none');
        }
    }
    // document.getElementById('auction_type').change();
    $(function() {
        changePropertyStyle("");
    });
</script>
<script>
    function changeWaterView(w) {
        if (w == "Yes" || w == "Optional") {
            $('.water_view_residential_and_income').show();
        } else {
            $('.water_view_residential_and_income').hide();
        }
    }

    function changeWaterAccess(w) {
        if (w == "Yes" || w == "Optional") {
            $('.water_access_residentail').show();
        } else {
            $('.water_access_residentail').hide();
        }
    }

    function changeWaterExtras(w) {
        if (w == "Yes" || w == "Optional") {
            $('.water_extras_residential_and_income').show();
        } else {
            $('.water_extras_residential_and_income').hide();
        }
    }

    function changeWaterFrontage(w) {
        if (w == "Yes" || w == "Optional") {
            $('.water_frontage_residential_and_income').show();
        } else {
            $('.water_frontage_residential_and_income').hide();
        }
    }

    function changeViewPrefrence(w) {
        if (w == "Yes" || w == "Optional") {
            $('.water_view_preference_residential_and_income').show();
        } else {
            $('.water_view_preference_residential_and_income').hide();
        }
    }
    $(function() {
        changeWaterView("");
        changeWaterAccess("");
        changeWaterExtras("");
        changeWaterFrontage("");
        changeViewPrefrence("");
    });
</script>
<script>
    $(function() {
        $('.has-icon').each(function(i) {
            var cover = `<div class="input-cover input-cover-${i}"></div>`;
            $(this).before(cover);
            $(this).appendTo(`.input-cover-${i}`);
            var iconClass = $(this).data('icon');
            var id = $(this).attr('id');
            var htm = `<label for="${id}" class="input-icon"><i class="${iconClass} " ></i></label>`;
            $(this).before(htm);
        });

        $('.grid-picker').each(function(index, elm) {
            var st = $(elm).attr('style');
            var html =
                `<div class="options-container options-container-${index}" style="${st}"></div>`;
            $(elm).after(html);
            $(elm).appendTo(`.options-container-${index}`);
            $(elm).children('option').each(function(i) {
                var val = $(this).val();
                if (val != "") {
                    var text = $(this).text();
                    var classes = $(this).attr('class');
                    var styles = $(this).attr('style') || "";
                    var icon = $(this).data('icon') || "";
                    var selected = $(this).attr('selected') || "";
                    var target = $(this).data('target') || "";
                    selected = selected && "active";
                    icon = icon && icon + " ";
                    var htm = `<div onclick="checkselect(this);" style="${styles}" class="${classes} ${selected} option-container" data-index="${i}" data-target="${target}">
                        <div class="option-icon">${icon}</div>
                        <div class="option-text">${text}</div>
                        </div>`;
                    $(`.options-container-${index}`).append(htm);
                }
                // console.log(val, text, icon, classes);
            });
            // console.log(html);
            // html += `</div>`;
            // $(elm).after(html);
            /* $('.option-container').on('click', function(index, elm) {
                // alert("ok");
                var ind = $(elm).data(index);
                var op = $(elm).parent().html();
                console.log(op);
            }); */
        });



    });

    function checkselect(elm) {
        var i = $(elm).data('index');
        var mult = $(elm).parent().children('select').attr('multiple') || false;
        // console.log(mult);
        if (mult == false) {
            var option = $(elm).parent().children('select').children(`option:eq(${i})`);
            var ov = option.val();
            $(elm).parent().children('.option-container').removeClass('active');
            $(elm).addClass('active');
            $(elm).parent().children('select').val(ov);
        } else {
            $(elm).toggleClass('active');
            var option = $(elm).parent().children('select').children(`option:eq(${i})`);
            var ov = option.val();
            var vals = $(elm).parent().children('select').val();
            if (vals.includes(ov)) {
                option.removeAttr('selected');
            } else {
                option.attr('selected', 'selected');
            }
        }

        // console.log(op);
        var v = $(elm).parent().children('select').val();
        $(elm).parent().children('select').trigger('change');
        //   console.log(v);
        check_custom();
    }

    function check_custom() {
        $('.option-container').each(function(i, elm) {
            var target = $(elm).data('target') || "";
            var is_active = $(elm).hasClass('active');
            if (target != "") {
                //   console.log("is_active", is_active, target);
                if (is_active) {
                    $(target).removeClass("d-none");
                } else {
                    $(target).addClass("d-none");
                }
            }
        });
        // setTimeout(check_custom, 500);
    }
</script>
<script>
    $(function() {
        StepWizard.init();
    });

    var StepWizard = {
        init: function() {
            StepWizard.total_steps = $('.wizard-step').length;

            // var v = $(".mainform").validate({
            //   errorClass: "text-error text-danger w-100",
            //   onkeyup: false,
            //   onfocusout: false,
            // });
            var v = $('.mainform').validate({ // initialize the plugin
                rules: {
                    working_with_agent: {
                        required: true,
                    },
                    property_type: {
                        required: true,
                    },
                    cities: {
                        required: true,
                    },
                    listing_date: {
                        required: true,
                    },
                    expiration_date: {
                        required: true,
                    },
                    auction_type: {
                        required: true,
                    },
                    auction_length: {
                        required: true,
                    },
                    listing_title: {
                        required: true,
                    },
                    listing_title: {
                        required: true,
                    },
                    tenant_lease: {
                        required: true,
                    },
                    condition_prop: {
                        required: true,
                    },
                    bedrooms: {
                        required: true,
                    },
                    bathrooms: {
                        required: true,
                    },
                    minimum_heated_square: {
                        required: true,
                    },
                    tenant_require: {
                        required: true,
                    },
                    total_acreage: {
                        required: true,
                    },
                    garageOptions: {
                        required: true,
                    },
                    carportOptions: {
                        required: true,
                    },
                    poolOptions: {
                        required: true,
                    },
                    prefrenceOptions: {
                        required: true,
                    },
                    petOptions: {
                        required: true,
                    },
                    purchasing_props: {
                        required: true,
                    },
                    non_negotiable_terms: {
                        required: true,
                    },
                    budget: {
                        required: true,
                    },
                    lease_by: {
                        required: true,
                    },
                    lease_for: {
                        required: true,
                    },
                    has_pets: {
                        required: true,
                    },
                    credit_score: {
                        required: true,
                    },
                    evicted: {
                        required: true,
                    },
                    convicted: {
                        required: true,
                    },
                    tenant_terms: {
                        required: true,
                    },
                    find_property: {
                        required: true,
                    },
                    important_aspects: {
                        required: true,
                    },
                    additional_details: {
                        required: true,
                    },
                    services: {
                        required: true,
                    },
                },
                messages: {
                    working_with_agent: {
                        //   required: '' // Change 'alskdjf' to your desired error message
                    }
                },
                errorPlacement: function(error, element) {
                    error.insertAfter(element.parent());
                    // error.insertBefore(element);
                },
                invalidHandler: function(form, validator) {
                    var errors = validator.numberOfInvalids();
                    if (errors) {
                        var firstError = $(validator.errorList[0].element);
                        $('html, body').animate({
                            scrollTop: firstError.offset().top - 100 // Adjust scroll position if needed
                        }, 500);
                        firstError.focus();
                    }
                }
            });



            StepWizard.setStep();
            property_type;
            $('#property_type').on('change', function() {
                property_type = $(this).val();
                // Count the remaining steps without removing them
            });
            $('.wizard-step-next').click(function(e) {

                //   alert(StepWizard.currentStep);
                console.log(StepWizard.currentStep)

                if (v.form()) {
                    if ($('.wizard-step.active').next().is('.wizard-step')) {

                        // $('.wizard-step.active').removeClass('active').next().addClass('active');
                        $('.wizard-step.active').removeClass('active');
                        if (StepWizard.currentStep == 7 && property_type ==
                            'Income Property') {
                            StepWizard.nextStep = 10;
                            StepWizard.backStep = 7;
                        } else if (StepWizard.currentStep == 15 && property_type ==
                            'Residential Property') {
                            StepWizard.nextStep = 17;
                            StepWizard.backStep = 15;
                        } else if (StepWizard.currentStep == 24 && property_type ==
                            'Business Opportunity') {
                            StepWizard.nextStep = 26;
                            StepWizard.backStep = 24;
                        } else if (StepWizard.currentStep == 16 && (property_type == 'Commercial Property')) {
                            // Exclude Step 11 from the condition
                            StepWizard.nextStep = 18;
                            StepWizard.backStep = 16;
                        } else if (StepWizard.currentStep == 4 && (property_type == 'Business Opportunity')) {
                            StepWizard.nextStep = 20;
                            StepWizard.backStep = 4;
                        } else if (StepWizard.currentStep == 4 && property_type ==
                            'Vacant Land (Current Use)'
                        ) {
                            StepWizard.nextStep = 33;
                            StepWizard.backStep = 4;
                        } else {
                            StepWizard.backStep = StepWizard.currentStep;
                        }
                        $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
                        StepWizard.setStep();
                        if (
                            StepWizard.currentStep == 29 &&
                            (property_type == 'Residential Property' || property_type ==
                                'Income Property')
                        ) {
                            $('.wizard-step-next').hide();
                            $('.wizard-step-finish').show();
                        }
                        if (
                            StepWizard.currentStep == 32 &&
                            (property_type == 'Commercial Property' || property_type ==
                                'Business Opportunity')
                        ) {
                            $('.wizard-step-next').hide();
                            $('.wizard-step-finish').show();
                        }
                    }
                }

            });

            $('.wizard-step-back').click(function(e) {
                if ($('.wizard-step.active').prev().is('.wizard-step')) {

                    $('.wizard-step.active').removeClass('active');

                    $('[ data-step="' + StepWizard.backStep + '"]').addClass("active");
                    StepWizard.setStep();
                    if (StepWizard.currentStep == 10 && property_type ==
                        'Income Property') {

                        StepWizard.backStep = 7;
                    } else if (StepWizard.currentStep == 17 && property_type ==
                        'Residential Property') {

                        StepWizard.backStep = 15;
                    } else if (StepWizard.currentStep == 26 && property_type ==
                        'Business Opportunity') {

                        StepWizard.backStep = 24;
                    } else if (StepWizard.currentStep == 18 && property_type ==
                        'Commercial Property') {
                        StepWizard.backStep = 16;
                    } else if (StepWizard.currentStep == 20 && property_type ==
                        'Business Opportunity') {
                        StepWizard.backStep = 4;
                    } else if (StepWizard.currentStep == 33 && property_type ==
                        'Vacant Land (Current Use)'
                    ) {

                        StepWizard.backStep = 4;
                    } else {
                        StepWizard.backStep = StepWizard.currentStep - 1;
                    }
                }
            });
            // Assuming the code provided is within a function or a document.ready block

            $('.wizard-step-finish').click(function(e) {

                //Remove All the SLides Except THe Vacant Land
                if (property_type === 'Vacant Land (Current Use)') {
                    var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                        return parseInt($(this).attr('data-step')) >= 5 && parseInt($(this)
                            .attr('data-step')) <= 32;
                    });
                    $stepsToRemove.each(function() {
                        $(this).closest('div[data-step]').remove();
                    });
                }
                //Remove All the SLides Except THe Residential and Commercial Property
                //   if (property_type == 'Residential Property' || property_type == 'Income Property') {
                //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                //       return parseInt($(this).attr('data-step')) >= 25 && parseInt($(this)
                //         .attr('data-step')) <= 43;
                //     });

                //     $stepsToRemove.each(function() {
                //       $(this).closest('div[data-step]').remove();
                //     });
                //   }
                //Remove All the SLides Except THe Commercial and Business Opportunity
                //   if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
                //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                //       var stepValue = parseInt($(this).attr('data-step'));
                //       return (stepValue >= 5 && stepValue <= 29) || (stepValue >= 33 &&
                //         stepValue <= 43);
                //     });

                //     $stepsToRemove.each(function() {
                //       $(this).closest('div[data-step]').remove();
                //     });
                //   }
                //Remove All the SLides Except THe Commercial and Business Opportunity
                // Submitting The Form After Removing the Extra slide to get rid of null Data
                $('.mainform').submit();
            });

        },
        setStep: function() {
            if ($('.wizard-step.active').length == 0) {
                $('.wizard-step').first().addClass('active');
            }
            if ($('.wizard-step.active').prev().is('.wizard-step')) {
                $('.wizard-step-back').show();
            } else {
                $('.wizard-step-back').hide();
            }

            if ($('.wizard-step.active').next().is('.wizard-step')) {
                $('.wizard-step-next').show();
                $('.wizard-step-finish').hide();
            } else {

                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
            }
            $('.wizard-step').each(function(i, element) {
                var k = i + 1;
                if ($(element).hasClass('active')) {
                    StepWizard.currentStep = k;
                    StepWizard.data_step = k;
                    StepWizard.nextStep = k + 1;
                }
            });
            StepWizard.stepChanged();
        },
        stepChanged: function() {
            // var comp = 0;

            // if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 19) {
            //     // Calculate progress for Residential and Income property steps (5 to 19)
            //     comp = 20 + (((StepWizard.currentStep - 5) / (19 - 5)) * 80);
            // } else if (StepWizard.currentStep >= 20 && StepWizard.currentStep <= 32) {
            //     // Calculate progress for Commercial and Business opportunity steps (20 to 32)
            //     comp = 20 + (((StepWizard.currentStep - 20) / (32 - 20)) * 80);
            // } else if (StepWizard.currentStep >= 33 && StepWizard.currentStep <= 43) {
            //     // Calculate progress for Vacant land steps (33 to 43)
            //     comp = 20 + (((StepWizard.currentStep - 33) / (43 - 33)) * 80);
            // } else {
            //     // Default progress calculation for other steps
            //     comp = ((StepWizard.currentStep - 1) / 8) * 20;
            // }


            // $('.steps-progress-percent').animate({
            //     width: comp.toFixed(0) + '%',
            // });
            var comp = 0;
            if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 29 && property_type ==
                'Residential Property') {
                comp = 20 + (((StepWizard.currentStep - 5) / (29 - 5)) * 80);
            } else if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 29 && property_type ==
                'Commercial Property') {
                comp = 20 + (((StepWizard.currentStep - 5) / (29 - 5)) * 80);
                // alert('ehrer')
                //   comp = 93;
                //   if (StepWizard.currentStep == 21) {
                //     comp = 95
                //   }
                //   if (StepWizard.currentStep == 22) {
                //     comp = 97
                //   }
                //   if (StepWizard.currentStep == 23) {
                //     comp = 98
                //   }
                //   if (StepWizard.currentStep == 24) {
                //     comp = 100
                //   }
                // alert(StepWizard.currentStep)

            } else if (StepWizard.currentStep >= 23 && StepWizard.currentStep <= 32) {
                // Set progress to 100% for steps 23 to 32 (inclusive)
                comp = 100;
            } else if (StepWizard.currentStep >= 33 && StepWizard.currentStep <= 43) {
                // Calculate progress for steps 33 to 43 (inclusive)
                comp = 100 + (((StepWizard.currentStep - 33) / (43 - 33)) * 100);
            } else {
                // Default progress calculation for other steps
                comp = ((StepWizard.currentStep - 1) / 4) * 20;
            }

            $('.steps-progress-percent').animate({
                width: comp.toFixed(0) + '%',
            });
        },

        currentStep: 1,
        nextStep: 2,
        bsckStep: 1,
        total_steps: 0,
        data_step: 1,

    };
    $(document).on('change', '#need_water_view', function() {
        // alert('ok');

        var selectedValue = $(this).val();

        if (selectedValue == 'No') {
            $('#prefer_water_view').hide();
            $('#extra_water').hide();

        }

        if (selectedValue == 'No') {
            $('#prefer_water_view').hide();
            $('#extra_water').hide();

        }


        if (selectedValue == 'Yes') {
            $('#prefer_water_view').show();
            $('#extra_water').show();

        }
        //    alert(selectedValue);
    });
</script>

<script>
    // google.maps.event.addDomListener(window, 'load', initialize);
    function initialize() {
        var inputField = document.getElementsByClassName('search_places');

        for (var i = 0; i < inputField.length; i++) {
            var t = inputField[i].dataset.type;
            console.log(t);
            if (t === "cities") {
                var options = {
                    types: ['(cities)'],
                    componentRestrictions: {
                        country: "us"
                    },
                };
            } else if (t === "states") {
                var options = {
                    types: ['administrative_area_level_1'],
                    componentRestrictions: {
                        country: "us"
                    },
                };
            } else if (t === "address") {
                var options = {
                    types: [],
                    componentRestrictions: {
                        country: "us"
                    },
                };
            } else {
                var options = {
                    types: ['administrative_area_level_2'],
                    componentRestrictions: {
                        country: "us"
                    },
                };
            }

            google.maps.event.addDomListener(inputField[i], 'keydown', function(e) {
                if (e.keyCode == 13) {
                    if (e.preventDefault) {
                        e.preventDefault();
                    } else {
                        // Since the google event handler framework does not handle early IE versions, we have to do it by our self.: -(
                        e.cancelBubble = true;
                        e.returnValue = false;
                    }
                }
            });

            var autocomplete = new google.maps.places.Autocomplete(inputField[i], options);

            autocomplete.addListener('place_changed', function(e) {
                var place = autocomplete.getPlace();
                if (place) {
                    console.log("place", place);
                    // place variable will have all the information you are looking for.
                    var lat = place.geometry['location'].lat();
                    var lng = place.geometry['location'].lng();
                    if (t == "counties") {
                        $('#lat').val(lat);
                        $('#long').val(lng);
                    }
                }
            });
        }
    }
    $('#costom_find_property').click(function() {})
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
</script>
@endpush