@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.css">

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

        ::-ms-browse {
            height: 50px;
        }

        ::-webkit-file-upload-button {
            height: 50px;
        }

        input[type=file]::file-selector-button {
            height: 50px;
        }
    </style>
@endpush
@section('content')
    {{-- @if ($errors->any())
<h4>{{$errors->first()}}</h4>
@endif --}}
    @php
        $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
    @endphp
    <div class="container pt-5 pb-5">
        <div class="card">
            <div class="row">
                <div class="col-12 p-4">
                    <form class="p-4 pt-0 validate mainform" action="{{ route('updateSellerAgentHireAuction') }}"
                        method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="id" value="{{ @$auction->id }}">
                        @push('scripts')
                            <script>
                                $(function() {
                                    $('#working_with_agent').change(function() {
                                        var val = $(this).val();
                                        if (val == "Yes") {
                                            $('.wizard-step-next').attr("disabled", "disabled");
                                            $('.yes_message').text(
                                                "This service is for sellers who are not currently working with a licensed agent."
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
                                Edit Hire Seller's Agent auction
                            </h4>
                            <div class="wizard-steps-progress">
                                <div class="steps-progress-percent"></div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">Does the seller have an active listing agreement with an agent?
                                    </label>
                                    <select class="grid-picker" name="working_with_agent" id="working_with_agent"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $yes_or_no)
                                            <option value="{{ $yes_or_no['name'] }}"
                                                {{ selected($yes_or_no['name'], @$auction->get->working_with_agent) }}
                                                data-target="{{ $yes_or_no['target'] }}" class="card flex-row fw-bold"
                                                style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                                {{ $yes_or_no['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="yes_message text-danger"
                                        style="font-size:18px; font-weight:bold;"></label>
                                </div>
                            </div>

                            <div class="wizard-step">
                                <label class="fw-bold">Please provide the property's complete address, along with the city,
                                    county, and
                                    state, pertaining to the real estate asset that the seller intends to place on the
                                    market.</label>
                                <div class="form-group">
                                    <label class="fw-bold">Address</label>
                                    <input type="text" name="address"
                                        placeholder="199 Florida A1A, Satellite Beach, FL, USA" id="form_title"
                                        class="form-control search_places has-icon" data-type="address" required
                                        value="{{ @$auction->get->address }}" data-icon="fa-solid fa-location-dot" />
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">State</label>
                                    <input type="text" name="state" data-type="states" placeholder="Florida, USA"
                                        id="state" class="form-control has-icon search_places"
                                        value="{{ @$auction->get->state }}" data-icon="fa-solid fa-flag-usa"
                                        data-msg-required="Please enter state" required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">City</label>
                                    <input type="text" name="city" data-type="cities" placeholder="St. Petersburg, FL"
                                        id="city" value="{{ @$auction->get->city }}"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                                        data-msg-required="Please enter city" required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">County</label>
                                    <input type="text" name="county" data-type="counties" placeholder="Pinellas County"
                                        id="county" value="{{ @$auction->get->county }}"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                                        data-msg-required="Please enter county" required>
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Auction Type:
                                    </label>
                                    <div>
                                        @php
                                            $auction_types = [['name' => 'Auction (Timer)', 'icon' => '<i class="fa-regular fa-clock"></i>', 'target' => ''], ['name' => 'Traditional (No Timer)', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>', 'target' => '']];
                                        @endphp
                                        <select name="auction_type" id="auction_type" class="grid-picker"
                                            style="justify-content: flex-start;" onchange="changeAuctionType(this.value);"
                                            required>
                                            <option value=""></option>
                                            @foreach ($auction_types as $item)
                                                <option value="{{ $item['name'] }}"
                                                    {{ selected($item['name'], @$auction->get->auction_type) }}
                                                    data-target="{{ $item['target'] }}" class="card flex-row fw-bold"
                                                    style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
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
                                        <select name="auction_length" id="auction_length" class="auction_length grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($auction_lengths as $item)
                                                <option value="{{ $item['name'] }}"
                                                    {{ selected($item['name'], @$auction->get->auction_length) }}
                                                    data-target="" class="card flex-row fw-bold {{ $item['class'] }}"
                                                    style="width:calc(33.33% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                            </div>

                            <div class="wizard-step">
                                @php
                                    $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Property Types:</label>
                                    <select class="grid-picker" name="property_type" id="property_type"
                                        onchange="changePropertyType(this.value);" required>
                                        <option value="">Select</option>
                                        @foreach ($property_types as $row_pt)
                                            <option value="{{ $row_pt['name'] }}"
                                                {{ selected($row_pt['name'], @$auction->get->property_type) }}
                                                class="card flex-column fw-bold" style="width:calc(24% - 10px);"
                                                data-icon='<i class="fa-solid fa-hotel"></i>'>
                                                {{ $row_pt['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <div>
                                        @php
                                            $property_items = [
                                                ['name' => 'Single Family Residence', 'class' => 'residential-length'],
                                                ['name' => 'Townhouse', 'class' => 'residential-length'],
                                                ['name' => 'Villa', 'class' => 'residential-length'],
                                                ['name' => 'Condominium', 'class' => 'residential-length'],
                                                ['name' => 'Condo-Hotel', 'class' => 'residential-length'],
                                                ['name' => '½ Duplex', 'class' => 'residential-length'],
                                                ['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                                                ['name' => 'Farm', 'class' => 'residential-length'],
                                                ['name' => 'Garage Condo', 'class' => 'residential-length'],
                                                ['name' => 'Manufactured Home', 'class' => 'residential-length'],
                                                ['name' => 'Mobile Home', 'class' => 'residential-length'],
                                                ['name' => 'Modular Home', 'class' => 'residential-length'],
                                                ['name' => 'Duplex', 'class' => 'income-length'],
                                                ['name' => 'Triplex', 'class' => 'income-length'],
                                                ['name' => 'Quadplex', 'class' => 'income-length'],
                                                ['name' => 'Five or More (Residential units)', 'class' => 'income-length'],
                                                ['name' => 'Agriculture', 'class' => 'commercial-length'],
                                                ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                                                ['name' => 'Business', 'class' => 'commercial-length'],
                                                ['name' => 'Five or More (Residential units)', 'class' => 'commercial-length'],
                                                ['name' => 'Five or More (Commercial units)', 'class' => 'commercial-length'],
                                                ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                                                ['name' => 'Industrial', 'class' => 'commercial-length'],
                                                ['name' => 'Mixed Use', 'class' => 'commercial-length'],
                                                ['name' => 'Office', 'class' => 'commercial-length'],
                                                ['name' => 'Restaurant', 'class' => 'commercial-length'],
                                                ['name' => 'Retail', 'class' => 'commercial-length'],
                                                ['name' => 'Unimproved Land', 'class' => 'commercial-length'],
                                                ['name' => 'Warehouse', 'class' => 'commercial-length'],
                                            ];
                                        @endphp
                                        <select name="property_items" id="property_items"
                                            class="property_items grid-picker" style="justify-content: flex-start;"
                                            multiple required>
                                            <option value=""></option>
                                            @foreach ($property_items as $item)
                                                <option value="{{ $item['name'] }}"
                                                    {{ selected_in($item['name'], @$auction->get->property_items) }}
                                                    data-target="" class="card flex-row fw-bold {{ $item['class'] }}"
                                                    style="width:calc(33.33% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            @php
                                $bedrooms = [['name' => '1', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Other', 'target' => '.custom_bedrooms']];
                                $bathrooms = [['name' => '1', 'target' => ''], ['name' => '1.5', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '2.5', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '3.5', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '4.5', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Other', 'target' => '.custom_bathrooms']];
                            @endphp
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">How many bedrooms does the property have?</label>
                                    <select class="grid-picker" name="bedrooms" id="bedrooms"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($bedrooms as $bedroom)
                                            <option value="{{ $bedroom['name'] }}"
                                                data-target="{{ $bedroom['target'] }}"
                                                {{ selected($bedroom['name'], @$auction->get->bedrooms) }}
                                                class="card flex-column fw-bold" style="width:calc(10% - 10px);"
                                                data-icon='<i class="fa-solid fa-bed"></i>'>
                                                {{ $bedroom['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_bedrooms @if (@$auction->get->custom_bedrooms == '' || @$auction->get->custom_bedrooms == 'null') d-none @endif ">
                                    <label>Bedrooms</label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Bedrooms" name="custom_bedrooms" data-icon="fa fa-bed"
                                        id="custom_bedrooms" required value="{{ @$auction->get->custom_bedrooms }}" />
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">How many bathrooms does the property have?</label>
                                    <select class="grid-picker" name="bathrooms" id="bathrooms"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($bathrooms as $bathroom)
                                            <option value="{{ $bathroom['name'] }}"
                                                {{ selected($bathroom['name'], @$auction->get->bathrooms) }}
                                                data-target="{{ $bathroom['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(10% - 10px);"
                                                data-icon='<i class="fa-solid fa-bath"></i>'>
                                                {{ $bathroom['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_bathrooms @if (@$auction->get->custom_bathrooms == '' || @$auction->get->custom_bathrooms == 'null') d-none @endif ">
                                    <label>Bathrooms</label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Bathrooms" name="custom_bathrooms"
                                        data-icon="fa-solid fa-bath" id="custom_bathrooms" required
                                        value="{{ @$auction->get->custom_bathrooms }}" />
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        How much do you think your property will sell for? *
                                    </label>
                                    @php
                                        $expectations = [['name' => '100k or less'], ['name' => '100k-200k'], ['name' => '200k-300k'], ['name' => '300k-400k'], ['name' => '400k-500k'], ['name' => '500k-600k'], ['name' => '600k-700k'], ['name' => '700k-800k'], ['name' => '800-900k'], ['name' => '900k-1 million'], ['name' => '1 million +'], ['name' => '2 million +'], ['name' => '3 million +'], ['name' => '4 million +'], ['name' => '5 million +'], ['name' => '10 million +']];
                                    @endphp
                                    <select name="expectation" id="expectation" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($expectations as $item)
                                            <option value="{{ $item['name'] }}" data-target=""
                                                {{ selected($item['name'], @$auction->get->expectation) }}
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="wizard-step">

                                @php
                                    $prop_conditions = [['name' => 'Not Updated: Requires a complete update.'], ['name' => 'Tear Down: Requires complete demolition and reconstruction.'], ['name' => 'Semi-updated, needs minor updates: Partially updated, but requires additional minor updates.'], ['name' => 'Move-in Ready, Completely Updated: Fully updated and ready for occupancy.']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Select the property conditions: *</label>
                                    <select class="grid-picker" name="prop_conditions[]" multiple id="prop_conditions"
                                        required>
                                        <option value="">Select</option>
                                        @foreach ($prop_conditions as $item)
                                            <option value="{{ $item['name'] }}" class="card flex-row fw-bold"
                                                {{ selected_in($item['name'], @$auction->get->prop_conditions) }}
                                                style="width:calc(50% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Special sale: *
                                    </label>
                                    @php
                                        $special_sales = [['name' => 'Regular Sale'], ['name' => 'Pre-Construction'], ['name' => 'Currently Being Built'], ['name' => 'New Construction'], ['name' => 'REO/Bank Owned'], ['name' => 'Assignment Contract (Wholesale properties)'], ['name' => 'Short Sale'], ['name' => 'Probate'], ['name' => 'Other']];
                                    @endphp
                                    <div class="select2-parent">
                                        <select name="special_sale" id="special_sale" class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($special_sales as $item)
                                                <option value="{{ $item['name'] }}" data-target=""
                                                    {{ selected($item['name'], @$auction->get->special_sale) }}
                                                    class="card flex-row fw-bold" style="width:calc(50% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-7px;"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Timeframe for selling? *
                                    </label>
                                    @php
                                        $timeframes = [['name' => 'ASAP', 'target' => ''], ['name' => '1-3 months', 'target' => ''], ['name' => '3-6 months', 'target' => ''], ['name' => '6-9 months', 'target' => ''], ['name' => '9-12 months', 'target' => ''], ['name' => 'More than 12 months', 'target' => '']];
                                    @endphp
                                    <select name="selling_timeframe" id="selling_timeframe" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($timeframes as $item)
                                            <option value="{{ $item['name'] }}" data-target=""
                                                {{ selected($item['name'], @$auction->get->selling_timeframe) }}
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group prompt_response" style="display: none;">
                                    <p class="text-danger" value="{{ @$auction->get->prompt_response }}">This is a
                                        service designed for sellers who are seeking to sell
                                        their property within three months or less.</p>
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Listing Terms: *
                                    </label>
                                    @php
                                        $listing_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '12 months', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_listing_terms']];
                                    @endphp
                                    <select name="listing_term" id="listing_term" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($listing_terms as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                {{ selected($item['name'], @$auction->get->listing_term) }}
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div
                                    class="form-group custom_listing_terms @if (@$auction->get->custom_listing_terms == '' || @$auction->get->custom_listing_terms == 'null') d-none @endif ">
                                    <label>Listing Terms:</label>
                                    <input type="text" class="form-control has-icon" placeholder="Write Custom Terms"
                                        name="custom_listing_terms" data-icon="fa-regular fa-square-check"
                                        id="custom_listing_terms" required
                                        value="{{ @$auction->get->custom_listing_terms }}" />
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Total Offered Commission: *
                                    </label>
                                    @php
                                        $offered_commissions = [['name' => '5%', 'target' => ''], ['name' => '5.5%', 'target' => ''], ['name' => '6%', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_offered_commission']];
                                    @endphp
                                    <select name="offered_commission" id="offered_commission" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($offered_commissions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                {{ selected($item['name'], @$auction->get->offered_commission) }}
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div
                                    class="form-group custom_offered_commission @if (@$auction->get->custom_offered_commission == '' || @$auction->get->custom_offered_commission == 'null') d-none @endif ">
                                    <label>Write Custom Commission *</label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Commission" name="custom_offered_commission"
                                        data-icon="fa-regular fa-square-check" id="custom_offered_commission" required
                                        value="{{ @$auction->get->custom_offered_commission }}" />
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Please select the Real Estate services that the seller would like to request from
                                        the agent:
                                    </label>
                                    @php
                                        $services_data = [];
                                        $services_data[] = ['target' => '', 'name' => 'List property on platform Bid Your Offer platform'];
                                        $services_data[] = ['target' => '', 'name' => 'List Property on the MLS'];
                                        $services_data[] = ['target' => '', 'name' => 'List property on the major Real Estate websites including Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more.'];
                                        $services_data[] = ['target' => '', 'name' => 'Marketing to many groups, pages, and affiliates.'];
                                        $services_data[] = ['target' => '', 'name' => 'Marketing on social media platforms'];
                                        $services_data[] = ['target' => '', 'name' => 'Professional Photos'];
                                        $services_data[] = ['target' => '', 'name' => 'Professional Video or 3D tour'];
                                        $services_data[] = ['target' => '', 'name' => 'Neighborhood marketing with a QR code or listing link that leads to the listing on BidYourOffer.com platform'];
                                        $services_data[] = ['target' => '', 'name' => 'Virtual Staging'];
                                        $services_data[] = ['target' => '', 'name' => 'Home Staging (Upgraded Seller paid service)'];
                                        $services_data[] = ['target' => '', 'name' => 'Open House'];
                                        $services_data[] = ['target' => '', 'name' => 'Property gets emailed to Buyers searching for homes that fit their criteria the moment the property gets listed directly through the MLS.'];
                                        $services_data[] = ['target' => '', 'name' => 'Buyer rebate of .5% to help with Buyer’s closing costs'];
                                        $services_data[] = ['target' => '.custom_services', 'name' => 'Other'];
                                    @endphp
                                    <select name="services[]" id="services" multiple class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($services_data as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                {{ selected_in($item['name'], @$auction->get->services) }}
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-hand-point-right" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_services @if (@$auction->get->custom_services == '' || @$auction->get->custom_services == 'null') d-none @endif ">
                                    <label>Write Custom Services *</label>
                                    <select class="grid-picker" name="special_offer_prior_eviction"
                                        id="special_offer_prior_eviction" style="justify-content: flex-start;">
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $yes_or_no)
                                            <option value="{{ $yes_or_no['name'] }}"
                                                {{ @$auction->get->special_offer_prior_eviction == $yes_or_no['name'] ? 'selected' : '' }}
                                                data-target="" class="card flex-row fw-bold pt-0 pb-0"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                                {{ $yes_or_no['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">Does the seller have a preferred agent whom they would like to
                                        notify to participate in the auction?</label>
                                    <select class="grid-picker" name="preferred_agent" id="preferred_agent"
                                        style="justify-content: flex-start;">
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $yes_or_no)
                                            <option value="{{ $yes_or_no['name'] }}"
                                                {{ @$auction->get->preferred_agent == $yes_or_no['name'] ? 'selected' : '' }}
                                                data-target="" class="card flex-row fw-bold pt-0 pb-0"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                                {{ $yes_or_no['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group explains @if (@$auction->get->explains == '') d-none @endif">
                                    <h4>Please enter the first name, last name, brokerage, phone number, and email address
                                        of the preferred agent(s):</h4>
                                    <div class="form-group">
                                        <label class="fw-bold">First name:</label>
                                        <input type="text" name="first_name" placeholder="Enter first name"
                                            id="first_name" class="form-control has-icon" data-icon="fa-solid fa-user"
                                            data-msg-required="Please enter first name" required
                                            value="{{ @$auction->get->first_name }}" />
                                    </div>
                                    <div class="form-group">
                                        <label class="fw-bold">Last name:</label>
                                        <input type="text" name="last_name" placeholder="Enter last name"
                                            id="last_name" class="form-control has-icon" data-icon="fa-solid fa-user"
                                            data-msg-required="Please enter last name" required
                                            value="{{ @$auction->get->last_name }}" />
                                    </div>
                                    <div class="form-group">
                                        <label class="fw-bold">Brokerage:</label>
                                        <input type="text" name="brokerage" data-type="brokerages"
                                            placeholder="Enter brokerage" id="brokerage" class="form-control has-icon"
                                            data-icon="fa-solid fa-house" data-msg-required="Please enter brokerage"
                                            required value="{{ @$auction->get->brokerage }}" />
                                    </div>
                                    <div class="form-group">
                                        <label class="fw-bold">Cell phone #:</label>
                                        <input type="text" name="phone" data-type="phones"
                                            placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                            data-icon="fa-solid fa-phone" data-msg-required="Please enter phone number"
                                            required value="{{ @$auction->get->phone }}" />
                                    </div>
                                    <div class="form-group">
                                        <label class="fw-bold">Email:</label>
                                        <input type="text" name="email" data-type="emails"
                                            placeholder="Enter your email address" id="email"
                                            class="form-control has-icon" data-icon="fa-solid fa-envelope"
                                            data-msg-required="Please enter email address"
                                            value="{{ @$auction->get->email }}" />
                                    </div>
                                </div>
                            </div>

                            @push('scripts')
                                <script>
                                    function cma_questions() {
                                        var want_analysis = $('#need_cma').val();
                                        // alert(want_analysis);
                                        if (want_analysis == 'Yes') {
                                            var temp = $('.questions_temp').html();
                                            $('.cma_questions').after(temp);
                                            initialize();
                                        } else {
                                            $('.cma_questions_').remove();
                                        }
                                    }
                                </script>
                            @endpush

                            <div class="wizard-step cma_questions">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Would the seller like to receive a free property value analysis from the agent?
                                    </label>
                                    <select name="need_cma" id="need_cma" class="grid-picker"
                                        style="justify-content: flex-start;" onchange="cma_questions();" required>
                                        <option value=""></option>
                                        @foreach ($yes_or_nos as $item)
                                            <option value="{{ $item['name'] }}" data-target=""
                                                {{ selected($item['name'], @$auction->get->need_cma) }}
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>

                            @if (@$auction->get->need_cma == 'Yes')
                                {{-- <div class="wizard-step cma_questions_">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            What is the address of your property? (County and State included so we can
                                            notify agents by county)
                                        </label>
                                        <input type="text" name="cma_q1" id="cma_q1"
                                            class="form-control search_places has-icon"
                                            data-icon="fa-solid fa-location-dot" data-type="address"
                                            placeholder="County and State included so we can notify agents by county"
                                            required value="{{ @$auction->get->cma_q1 }}">
                                    </div>
                                </div> --}}

                                {{-- <div class="wizard-step cma_questions_">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            What price do you want to list your property for?
                                        </label>
                                        <input type="text" name="cma_q2" id="cma_q2" class="form-control"
                                            required value="{{ @$auction->get->cma_q2 }}">
                                    </div>
                                </div> --}}

                                <div class="wizard-step cma_questions_">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            Have you done any updates since purchasing the property?
                                        </label>
                                        <input type="text" name="cma_q3" id="cma_q3" class="form-control"
                                            required value="{{ @$auction->get->cma_q3 }}">
                                    </div>
                                </div>

                                <div class="wizard-step cma_questions_">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            Are there any known repairs that need to be completed before purchasing?
                                        </label>
                                        <input type="text" name="cma_q4" id="cma_q4" class="form-control"
                                            required value="{{ @$auction->get->cma_q4 }}">
                                    </div>
                                </div>

                                {{-- <div class="wizard-step cma_questions_">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            For the most accurate CMA upload pictures of your property:
                                        </label>
                                        <input type="text" name="cma_q5" id="cma_q5"
                                            class="form-control has-icon" data-icon="fa-solid fa-link"
                                            value="{{ @$auction->get->cma_q5 }}">
                                    </div>
                                </div> --}}

                                {{-- <div class="wizard-step cma_questions_">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            For the most accurate CMA upload video of your property:
                                        </label>
                                        <input type="text" name="cma_q6" id="cma_q6"
                                            class="form-control has-icon" data-icon="fa-solid fa-link"
                                            value="{{ @$auction->get->cma_q6 }}">
                                    </div>
                                </div> --}}
                            @endif

                            <div class="wizard-step">

                                <div class="row align-items-start">

                                    <div class="form-group mt-4 col-md-6">
                                        <label>
                                            Heated Sqft <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" name="sqft" placeholder=""
                                            class="form-control has-icon" data-icon="fa-solid fa-pen-ruler" required
                                            value="{{ @$auction->get->sqft }}" />
                                    </div>

                                </div>

                                <div class="row align-items-start">
                                    <div class="form-group col-md-6 mt-4">
                                        <label>
                                            Does the seller have a specific price in mind that they would like to obtain for
                                            their property?
                                            <span class="text-danger">*</span>
                                        </label>
                                        <select name="ideal_price" id="ideal_price" class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($yes_or_nos as $item)
                                            @php
                                                if (strtolower($item['name']) == 'yes'){
                                                    $target = '.custom_ideal_price';
                                                }else {
                                                    $target = '';
                                                }
                                            @endphp
                                                <option value="{{ $item['name'] }}" data-target=""
                                                    {{ selected($item['name'], @$auction->get->ideal_price) }}
                                                    class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        {{-- <input type="number" class="form-control has-icon" name="ideal_price"
                                            min="0" required placeholder="0.00" data-icon="fa-solid fa-dollar"
                                            value="{{ @$auction->get->ideal_price }}"> --}}
                                    </div>
                                    <div class="form-group custom_ideal_price @if (@$auction->get->custom_ideal_price == '' || @$auction->get->custom_ideal_price == 'null') d-none @endif ">
                                        <label>Bedrooms</label>
                                        <input type="number" class="form-control has-icon"
                                            placeholder="0.00" name="custom_ideal_price" data-icon="fa-solid fa-dollar"
                                            id="custom_ideal_price" value="{{ @$auction->get->custom_ideal_price }}" required/>
                                    </div>
                                </div>

                            </div>
                            <div class="wizard-step">
                                @php
                                    $financings = [['name' => 'Cash', 'target' => ''], ['name' => 'Conventional', 'target' => ''], ['name' => 'Seller Financing', 'target' => ''], ['name' => 'FHA', 'target' => ''], ['name' => 'Jumbo', 'target' => ''], ['name' => 'VA', 'target' => ''], ['name' => 'USDA', 'target' => ''], ['name' => 'No-Doc', 'target' => ''], ['name' => 'Non-QM', 'target' => ''], ['name' => 'Assumable', 'target' => ''], ['name' => 'Exchange/Trade', 'target' => ''], ['name' => 'Lease Option', 'target' => ''], ['name' => 'Lease Purchase', 'target' => ''], ['name' => 'Private Financing Available', 'target' => ''], ['name' => 'Special Funding', 'target' => ''], ['name' => 'Bitcoin (BTC)', 'target' => ''], ['name' => 'Ethereum (ETH)', 'target' => ''], ['name' => 'Litecoin (LTC)', 'target' => ''], ['name' => 'Bitcoin Cash (BCH)', 'target' => ''], ['name' => 'Dash (DASH)', 'target' => ''], ['name' => 'Ripple (XRP)', 'target' => ''], ['name' => 'Tether (USDT)', 'target' => ''], ['name' => 'USD Coin (USDC)', 'target' => ''], ['name' => 'NFT', 'target' => ''], ['name' => 'Other', 'target' => '']];
                                @endphp
                                <div class="row align-items-end mt-4">
                                    <div class="col-md-12">
                                        <label>Select the types of financing/currency that is acceptable to the
                                            seller:</label>
                                        <div class="select2-parent">
                                            <select name="financings[]" class="grid-picker" multiple>
                                                <option value=""></option>
                                                @foreach ($financings as $financing)
                                                    <option value="{{ $financing['name'] }}" data-target=""
                                                        {{ selected_in($financing['name'], @$auction->get->financings) }}
                                                        class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                        data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                        {{ $financing['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <span class="small">Please note if Seller selects Cryptocurrency the
                                            cryptocurrency
                                            can be converted to cash at closing. This will open up the market to more
                                            international buyers.</span>
                                    </div>
                                </div>

                            </div>
                            <div class="wizard-step">

                                <div class="row">
                                    <div class="form-group mt-4 col-md-12">
                                        <label>
                                            Please provide a property description of the property:
                                            <span class="text-danger">*</span>
                                        </label>
                                        <textarea name="description" placeholder="Include upgrades or renovations with dates" class="form-control"
                                            rows="5" required data-msg-required="Property Description is required">{{ @$auction->get->description }}</textarea>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group mt-4 col-md-12">
                                        <label>
                                            Please provide any important information to know about the property:
                                        </label>
                                        <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5">{{ @$auction->get->important_info }}</textarea>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group mt-4 col-md-12">
                                        <label>
                                            Please provide a description of the ideal agent for the property:
                                        </label>
                                        <textarea name="description_ideal_agent"
                                            placeholder="" class="form-control"
                                            rows="5">{{ @$auction->get->description_ideal_agent }}</textarea>
                                    </div>
                                </div>

                            </div>
                            <div class="wizard-step">
                                <div class="row align-items-end">
                                    <div class="form-group mt-4 col-md-12">
                                        <label>
                                            Pictures of Seller's Property(Optional)
                                        </label>
                                        <input type="file" name="photos[]" size="60" class="form-control"
                                            multiple accept="image/*" />
                                    </div>
                                    <div class="form-group mt-4 col-md-12">
                                        <label>
                                            Video Tour of Seller’s property(Optional)
                                        </label>
                                        <input type="url" name="video_url" class="form-control has-icon"
                                            value="{{ @$auction->get->video_url }}" data-icon="fa-solid fa-link"
                                            placeholder="https://www.youtube.com/watch?v=yaQhG3qyP-A" />
                                        {{-- <input type="file" name="video_file" class="form-control " value="{{old('video_file')}}" accept="video/*" /> --}}
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between form-group mt-4">
                                <div>
                                    <a class="wizard-step-back btn btn-success btn-lg text-600"
                                        style="display: none;">Back</a>
                                </div>
                                <div>
                                    <button type="button" class="wizard-step-next btn btn-success btn-lg text-600"
                                        style="display: none;">Next</button>
                                    <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
                                        style="display: none;">Save</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <template class="questions_temp">
        <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    What is the address of your property? (County and State included so we can notify agents by county) *
                </label>
                <input type="text" name="cma_q1" id="cma_q1" class="form-control search_places has-icon"
                    data-icon="fa-solid fa-location-dot" data-type="address"
                    placeholder="County and State included so we can notify agents by county" required>
            </div>
        </div>

        <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    What price do you want to list your property for? *
                </label>
                <input type="text" name="cma_q2" id="cma_q2" class="form-control" required>
            </div>
        </div>

        <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    Have you done any updates since purchasing the property? *
                </label>
                <input type="text" name="cma_q3" id="cma_q3" class="form-control" required>
            </div>
        </div>

        <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    Are there any known repairs that need to be completed before purchasing? *
                </label>
                <input type="text" name="cma_q4" id="cma_q4" class="form-control" required>
            </div>
        </div>

        <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    For the most accurate CMA upload pictures of your property:
                </label>
                <input type="file" name="cma_q5[]" id="cma_q5" class="form-control has-icon"
                    data-icon="fa-solid fa-link" multiple accept="image/*" />
            </div>
        </div>

        <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    For the most accurate CMA upload video of your property:
                </label>
                <input type="text" name="cma_q6" id="cma_q6" class="form-control has-icon"
                    data-icon="fa-solid fa-link" />
            </div>
        </div>

    </template>
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
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
            // changeAuctionType("Normal (Timer)");
        });

        function showPrompt(s) {
            $('.prompt_response').hide();
            if (s == "3-6 months" || s == "6-9 months" || s == "9-12 months" || s == "More than 12 months") {
                $('.prompt_response').show();
            } else {
                $('.prompt_response').hide();
            }
        }
    </script>
    <script>
        function changePropertyType(p) {
            if (p == "Residential Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').show();
                $('.income-length').hide();
                $('.commercial-length').hide();
            } else if (p == "Income Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').show();
                $('.commercial-length').hide();
            } else if (p == "Commercial Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').show();
            } else {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').hide();
            }
        }
        // document.getElementById('auction_type').change();
        $(function() {
            changePropertyType("");
        });
    </script>
    <script>
        $(function() {
            $(document).ready(function() {
                /* var multipleCancelButton = new Choices('.multiple', {
                    removeItemButton: true,
                    // maxItemCount:5,
                    // searchResultLimit: 5,
                    // renderChoiceLimit: 5
                }); */
                $('.multiple').select2();

                $('.select2').each(function() {
                    $(this).prependTo($(this).parent('.select2-parent'));
                });
            });
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
            console.log(v);
            check_custom();
        }

        function check_custom() {
            $('.option-container').each(function(i, elm) {
                var target = $(elm).data('target') || "";
                var is_active = $(elm).hasClass('active');
                if (target != "") {
                    if (is_active) {
                        $(target).removeClass("d-none");
                    } else {
                        $(target).addClass("d-none");
                        $(target).find('input').val("");
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

                var v = $(".mainform").validate({
                    errorClass: "text-danger w-100",
                    onkeyup: false,
                    onfocusout: false,
                    /* submitHandler: function() {
                        // alert("Submitted, thanks!");
                        $(".mainform").submit();
                    } */
                });

                StepWizard.setStep();
                $('.wizard-step-next').click(function(e) {
                    if (v.form()) {
                        if ($('.wizard-step.active').next().is('.wizard-step')) {
                            $('.wizard-step.active').removeClass('active').next().addClass('active');
                            StepWizard.setStep();
                        }
                    }
                });

                $('.wizard-step-back').click(function(e) {
                    if ($('.wizard-step.active').prev().is('.wizard-step')) {
                        $('.wizard-step.active').removeClass('active').prev().addClass('active');
                        StepWizard.setStep();
                    }
                });

                $('.wizard-step-finish').click(function(e) {
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
                    }
                });
                StepWizard.stepChanged();
            },
            stepChanged: function() {
                var comp = (StepWizard.currentStep / StepWizard.total_steps) * 100;
                $('.steps-progress-percent').animate({
                    width: comp.toFixed(0) + '%',
                });
            },
            currentStep: 1,
            total_steps: 0,
        };
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
    </script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
    </script>
@endpush
