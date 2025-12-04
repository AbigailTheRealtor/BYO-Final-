@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
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
                    <form class="p-4 pt-0 mainform" action="{{ route('tenant.hire.agent.auction') }}" method="POST"
                        enctype="multipart/form-data">
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
                                    <select class="grid-picker" name="working_with_agent" id="working_with_agent"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $yes_or_no)
                                            <option value="{{ $yes_or_no['name'] }}"
                                                data-target="{{ $yes_or_no['target'] }}" class="card flex-row"
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
                            {{-- slide 1  --}}

                            {{-- Slide 2 --}}
                            <div class="wizard-step" data-step="2">
                                <h4>Please provide the cities, counties, and state pertaining to the real estate location that the tenant intends to lease a property:</h4>
                                <div class="form-group">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Cities</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><input type="text" name="cities[]" placeholder="City"
                                                        data-type="cities" id="cities"
                                                        class="form-control  search_places" data-icon="fa-solid fa-city"
                                                        data-msg-required="Please enter city" required></td>
                                            </tr>
                                            <tr class="city_btn_row">
                                                <td><button type="button" class="btn btn-secondary btn-sm w-100"
                                                        onclick="add_city_row();"><i class="fa-solid fa-plus"></i> Add New
                                                        Row</button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="form-group">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Counties</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><input type="text" name="counties[]" placeholder="County"
                                                        data-type="counties" id="counties"
                                                        class="form-control  search_places" data-icon="fa-solid fa-city"
                                                        data-msg-required="Please enter County" required></td>
                                            </tr>
                                            <tr class="county_btn_row">
                                                <td><button type="button" class="btn btn-secondary btn-sm w-100"
                                                        onclick="add_county_row();"><i class="fa-solid fa-plus"></i> Add New
                                                        Row</button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">State</label>
                                    <input type="text" name="state" data-type="states" placeholder="Florida, USA"
                                        id="state" class="form-control has-icon search_places"
                                        data-icon="fa-solid fa-flag-usa" data-msg-required="Please enter state" required>
                                </div>

                            </div>
                            {{-- Slide 2 --}}
                            {{-- Slide 3 --}}
                            <div class="wizard-step" data-step="3">
                                <div class="form-group">
                                    <label for="address" class="fw-bold">Listing Date:</label>
                                    <input type="date" name="listing_date" id="listing_date"
                                        class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                        data-msg-required="Please enter listing date" required>
                                </div>

                                <div class="form-group">
                                    <label for="address" class="fw-bold">Expiration Date:</label>
                                    <input type="date" name="expiration_date" id="expiration_date"
                                        class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                        data-msg-required="Please enter expiration date" required>
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
                                        <select name="auction_type" id="auction_type" class="grid-picker"
                                            style="justify-content: flex-start;" onchange="changeAuctionType(this.value);"
                                            required>
                                            <option value=""></option>
                                            @foreach ($auction_types as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row " style="width:calc(33.3% - 10px);"
                                                    data-icon='{{ $item['icon'] }}'>
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
                                                <option value="{{ $item['name'] }}" data-target=""
                                                    class="card flex-row  {{ $item['class'] }}"
                                                    style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
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
                                    <label for="address">Title of Listing:</label>
                                    <input type="text" name="listing_title" id="listing_title"
                                    placeholder="Enter title of Listing"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-qrcode"
                                        data-msg-required="Please enter Listing  Title" required>
                                </div>
                            </div>
                            {{-- Slide 5 --}}
                            {{-- Slide 6 --}}
                            <div class="wizard-step" data-step="6">
                                @php
                                    $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Interested Property Types:</label>
                                    <select class="grid-picker" name="property_type" id="property_type"
                                        onchange="changePropertyType(this.value);check_hoa();property_questions();"
                                        required>
                                        <option value="">Select</option>
                                        @foreach ($property_types as $row_pt)
                                            <option value="{{ $row_pt['name'] }}" class="card flex-column fw-bold"
                                                style="width:calc(24% - 10px);"
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
                                                ['name' => '1/3 Triplex', 'class' => 'residential-length'],
                                                ['name' => ' 1/4 Quadplex', 'class' => 'residential-length'],
                                                ['name' => 'Duplex', 'class' => 'income-length'],
                                                ['name' => 'Triplex', 'class' => 'income-length'],
                                                ['name' => 'Quadplex', 'class' => 'income-length'],
                                                ['name' => 'Five or More (Residential units)', 'class' => 'income-length'],
                                                ['name' => 'Agriculture', 'class' => 'commercial-length'],
                                                ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                                                ['name' => 'Business', 'class' => 'commercial-length'],
                                                // Nisar Changing
                                                // ['name' => 'Five or More (Residential units)', 'class' => 'commercial-length'],
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
                                        <select name="property_items" id="property_items" class="property_items grid-picker"
                                            style="justify-content: flex-start;" multiple required>
                                            <option value=""></option>
                                            @foreach ($property_items as $item)
                                                <option value="{{ $item['name'] }}" data-target=""
                                                    class="card flex-row {{ $item['class'] }}"
                                                    style="width:calc(33.33% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                {{-- <div class="form-group">
                                    <label>
                                        Property Types Tenant is interested in:
                                        <span class="text-danger">*</span>
                                    </label>
                                    @php
                                        $property_types = [['target' => '', 'name' => 'Single Family Home'], ['target' => '', 'name' => 'Townhome'], ['target' => '', 'name' => 'Condo'], ['target' => '', 'name' => 'Condo-Hotel'], ['target' => '', 'name' => 'Villa'], ['target' => '', 'name' => 'Multi-Family'], ['target' => '', 'name' => 'Land/Lots'], ['target' => '', 'name' => 'Manufactured Home'], ['target' => '', 'name' => 'Modular Home'], ['target' => '', 'name' => 'Agricultural'], ['target' => '', 'name' => 'Assembly Building'], ['target' => '', 'name' => 'Business'], ['target' => '', 'name' => 'Five or more'], ['target' => '', 'name' => 'Hotel/Motel'], ['target' => '', 'name' => 'Industrial'], ['target' => '', 'name' => 'Mixed Use'], ['target' => '', 'name' => 'Office'], ['target' => '', 'name' => 'Restaurant'], ['target' => '', 'name' => 'Retail'], ['target' => '', 'name' => 'Warehouse'], ['target' => '.custom_property_type', 'name' => 'Other']];
                                    @endphp
                                    <select name="property_type" id="property_type" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($property_types as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group custom_property_type d-none">
                                    <label class="fw-bold" for="custom_property_type">Custom Property Type: *</label>
                                    <input type="text" name="custom_property_type" id="custom_property_type"
                                        placeholder="Custom Property Type" class="form-control"
                                        data-icon="fa-solid fa-bath" required>
                                </div> --}}

                            </div>
                            {{-- Slide 6 --}}
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}

                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}

                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}

                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                @php
                                    $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Interested Property Types:</label>
                                    <select class="grid-picker" name="property_type" id="property_type"
                                        onchange="changePropertyType(this.value);check_hoa();property_questions();"
                                        required>
                                        <option value="">Select</option>
                                        @foreach ($property_types as $row_pt)
                                            <option value="{{ $row_pt['name'] }}" class="card flex-column fw-bold"
                                                style="width:calc(24% - 10px);"
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
                                                // Nisar Changing
                                                // ['name' => 'Five or More (Residential units)', 'class' => 'commercial-length'],
                                                ['name' => 'Five or More ', 'class' => 'commercial-length'],
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
                                        <select name="property_items" id="property_items" class="property_items grid-picker"
                                            style="justify-content: flex-start;" multiple required>
                                            <option value=""></option>
                                            @foreach ($property_items as $item)
                                                <option value="{{ $item['name'] }}" data-target=""
                                                    class="card flex-row fw-bold {{ $item['class'] }}"
                                                    style="width:calc(33.33% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                {{-- <div class="form-group">
                                    <label>
                                        Property Types Tenant is interested in:
                                        <span class="text-danger">*</span>
                                    </label>
                                    @php
                                        $property_types = [['target' => '', 'name' => 'Single Family Home'], ['target' => '', 'name' => 'Townhome'], ['target' => '', 'name' => 'Condo'], ['target' => '', 'name' => 'Condo-Hotel'], ['target' => '', 'name' => 'Villa'], ['target' => '', 'name' => 'Multi-Family'], ['target' => '', 'name' => 'Land/Lots'], ['target' => '', 'name' => 'Manufactured Home'], ['target' => '', 'name' => 'Modular Home'], ['target' => '', 'name' => 'Agricultural'], ['target' => '', 'name' => 'Assembly Building'], ['target' => '', 'name' => 'Business'], ['target' => '', 'name' => 'Five or more'], ['target' => '', 'name' => 'Hotel/Motel'], ['target' => '', 'name' => 'Industrial'], ['target' => '', 'name' => 'Mixed Use'], ['target' => '', 'name' => 'Office'], ['target' => '', 'name' => 'Restaurant'], ['target' => '', 'name' => 'Retail'], ['target' => '', 'name' => 'Warehouse'], ['target' => '.custom_property_type', 'name' => 'Other']];
                                    @endphp
                                    <select name="property_type" id="property_type" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($property_types as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group custom_property_type d-none">
                                    <label class="fw-bold" for="custom_property_type">Custom Property Type: *</label>
                                    <input type="text" name="custom_property_type" id="custom_property_type"
                                        placeholder="Custom Property Type" class="form-control"
                                        data-icon="fa-solid fa-bath" required>
                                </div> --}}

                            </div>
                            @php
                                $bedrooms = [['name' => '1+', 'target' => ''], ['name' => '2+', 'target' => ''], ['name' => '3+', 'target' => ''], ['name' => '4+', 'target' => ''], ['name' => '5+', 'target' => ''], ['name' => '6+', 'target' => ''], ['name' => '7+', 'target' => ''], ['name' => '8+', 'target' => ''], ['name' => '9+', 'target' => ''], ['name' => '10+', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Other', 'target' => '.other_bedrooms']];
                                $bathrooms = [['name' => '1', 'target' => ''], ['name' => '1.5+', 'target' => ''], ['name' => '2+', 'target' => ''], ['name' => '2.5+', 'target' => ''], ['name' => '3+', 'target' => ''], ['name' => '3.5+', 'target' => ''], ['name' => '4+', 'target' => ''], ['name' => '4.5+', 'target' => ''], ['name' => '5+', 'target' => ''], ['name' => '6+', 'target' => ''], ['name' => '7+', 'target' => ''], ['name' => '8+', 'target' => ''], ['name' => '9+', 'target' => ''], ['name' => '10+', 'target' => ''], ['name' => 'Other', 'target' => '.other_bathrooms']];
                            @endphp
                            <div class="wizard-step">
                                <h4>Property Characteristics and Amenities:</h4>
                                <div class="form-group commercial_hide">
                                    <label class="fw-bold">How many bedrooms does the tenant need?</label>
                                    <select class="grid-picker" name="bedrooms" id="bedrooms"
                                        style="justify-content: center;" required>
                                        <option value="">Select</option>
                                        @foreach ($bedrooms as $bedroom)
                                            <option value="{{ $bedroom['name'] }}"
                                                data-target="{{ $bedroom['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-solid fa-bed"></i>'>
                                                {{ $bedroom['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group other_bedrooms d-none">
                                    <label class="fw-bold" for="other_bedrooms">Bedrooms:</label>
                                    <input type="number" name="other_bedrooms" id="other_bedrooms"
                                        placeholder="Custom Bedrooms" class="form-control has-icon"
                                        data-icon="fa-solid fa-bed" data-msg-required="Please enter Custom Bedrooms"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">How many bathrooms does the tenant need?</label>
                                    <select class="grid-picker" name="bathrooms" id="bathrooms"
                                        style="justify-content: center;" required>
                                        <option value="">Select</option>
                                        @foreach ($bathrooms as $bathroom)
                                            <option value="{{ $bathroom['name'] }}"
                                                data-target="{{ $bathroom['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-solid fa-bath"></i>'>
                                                {{ $bathroom['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group other_bathrooms d-none">
                                    <label class="fw-bold" for="other_bathrooms">Bathrooms:</label>
                                    <input type="number" name="other_bathrooms" id="other_bathrooms"
                                        placeholder="Custom Bathrooms" class="form-control has-icon"
                                        data-icon="fa-solid fa-bath" data-msg-required="Please enter Custom Bathrooms"
                                        required>
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                <h4>Property Characteristics and Amenities:</h4>
                                <div class="form-group residential_and_income_hide">
                                    <label class="fw-bold" >What is the minimum heated square footage required?</label>
                                    <input type="number" name="minimum_heated_square" id="other_bathrooms"
                                         class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Please enter Minimum Heated Square Footage"
                                        required>
                                </div>
                                <div class="form-group commercial_hide">
                                    <label class="fw-bold" >What is the minimum net leasable square footage required?:</label>
                                    <input type="number" name="minimum_net_leasable_square" id="other_bathrooms"
                                         class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Please enter Custom Bathrooms"
                                        required>
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                <h4>Property Characteristics and Amenities:</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Does the tenant require furnished, optional, partial, turnkey, or unfurnished accommodations?
                                        <span class="text-danger">*</span>
                                    </label>
                                    @php
                                        $tenant_require = [
                                            ['target' => '', 'name' => 'Furnished'],
                                            ['target' => '', 'name' => 'Optional'],
                                            ['target' => '', 'name' => 'Partial'],
                                            ['target' => '', 'name' => 'Turnkey'],
                                            ['target' => '', 'name' => 'Unfurnished'],
                                            ];
                                    @endphp
                                    <select name="tenant_require" id="tenant_require" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($tenant_require as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                <h4>Property Characteristics and Amenities:</h4>
                                <div class="form-group commercial_remove">
                                    <label class="fw-bold">
                                        Are there any non-negotiable amenities or property features that the tenant is seeking?

                                    </label>
                                    @php
                                        $non_negotialble = [
                                            ['target' => '.negotiable_terms', 'name' => 'Yes'],
                                            ['target' => '', 'name' => 'No'],
                                            ];
                                    @endphp
                                    <select name="has_non_negotiable_terms" id="has_non_negotiable_terms" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group commercial_remove negotiable_terms">
                                    <label class="fw-bold">
                                      Non Negotiable Terms
                                    </label>
                                    @php
                                        $non_negotialble_terms = [
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
                                            ['target' => '.custom_negotiable_terms', 'name' => 'Other (Custom input)'],
                                            ];
                                    @endphp
                                    <select name="non_negotiable_terms" id="negotiable_terms" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble_terms as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group residential_and_income_remove">
                                    <label class="fw-bold">
                                        Are there any non-negotiable amenities or property features that the tenant is seeking?
                                    </label>
                                    @php
                                        $non_negotialble = [
                                            ['target' => '.negotiable_terms_commercial', 'name' => 'Yes'],
                                            ['target' => '', 'name' => 'No'],
                                            ];
                                    @endphp
                                    <select name="has_non_negotiable_terms" id="has_non_negotiable_terms" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group residential_and_income_remove negotiable_terms_commercial">
                                    <label class="fw-bold">
                                       Negotiable Terms
                                    </label>
                                    @php
                                        $non_negotialble_terms = [
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
                                            ['target' => '.custom_negotiable_terms', 'name' => 'Other (Custom input)'],
                                            ];
                                    @endphp
                                    <select name="non_negotiable_terms" id="negotiable_terms" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble_terms as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group custom_negotiable_terms d-none">
                                    <label class="fw-bold" for="custom_lease_for">Non Negotiable Terms</label>
                                    <input type="text" name="custom_non_negotiable_terms" id="custom_lease_for"
                                        placeholder="Non Negotiable Terms" class="form-control" data-icon="fa-solid fa-bath"
                                        required>
                                </div>

                            </div>
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                <h4>Pre-Qualification and Preferences:</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        How much is the tenant’s budget?
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" name="budget" id="budget" placeholder="8000+"
                                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                                </div>
                            </div>
                            {{-- 14 Jul 2023  --}}
                            <div class="wizard-step">
                                <h4>Pre-Qualification and Preferences:</h4>
                                <div class="form-group">
                                    <label for="address"> When is the tenant looking to lease by?:</label>
                                    <input type="date" name="lease_by" id="lease_by"
                                    class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                    data-msg-required="When is the tenant looking to lease by?" required>
                                </div>
                            </div>
                            {{-- 14 Jul 2023  --}}
                            {{-- 14 Jul 2023  --}}
                            <div class="wizard-step">
                                <h4>Pre-Qualification and Preferences:</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        How long would the tenant like to lease for?
                                        <span class="text-danger">*</span>
                                    </label>
                                    @php
                                        $lease_for = [['target' => '', 'name' => '3 months'], ['target' => '', 'name' => '6 months'], ['target' => '', 'name' => '9 months'], ['target' => '', 'name' => '1 year'], ['target' => '', 'name' => '2 years'], ['target' => '', 'name' => '3-5 years'], ['target' => '', 'name' => '5+ years'], ['target' => '.custom_lease_for', 'name' => 'Other']];
                                    @endphp
                                    <select name="lease_for" id="lease_for" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($lease_for as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_lease_for d-none">
                                    <label class="fw-bold" for="custom_lease_for">How long would the tenant like to lease
                                        for?</label>
                                    <input type="text" name="custom_lease_for" id="custom_lease_for"
                                        placeholder="Custom lease for" class="form-control" data-icon="fa-solid fa-bath"
                                        required>
                                </div>
                            </div>
                                {{-- 14 Jul 2023  --}}
                                {{-- 14 Jul 2023  --}}
                                <div class="wizard-step">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            Does the tenant have a pet, and if so, how many and what type of pet?
                                            <span class="text-danger">*</span>
                                        </label>
                                        @php
                                            $has_pets = [['target' => '', 'name' => 'Yes, 1 Dog'], ['target' => '', 'name' => 'Yes, 1 Cat'], ['target' => '', 'name' => 'Yes, 2+ Dogs'], ['target' => '', 'name' => 'Yes, 2+ Cats'], ['target' => '', 'name' => 'No'], ['target' => '.custom_has_pets', 'name' => 'Other']];
                                        @endphp
                                        <select name="has_pets" id="has_pets" class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($has_pets as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group custom_has_pets d-none">
                                        <label for="custom_has_pets" class="fw-bold">Cutom Pet</label>
                                        <input type="text" name="custom_has_pets" id="custom_has_pets"
                                            placeholder="Custom Pet" class="form-control" data-icon="fa-solid fa-bath"
                                            required>
                                    </div>
                                </div>
                                {{-- 14 Jul 2023  --}}
                                {{-- 14 Jul 2023  --}}
                                <div class="wizard-step">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            What is the tenants credit score?
                                            <span class="text-danger">*</span>
                                        </label>
                                        @php
                                            $credit_score = [['target' => '', 'name' => 'Poor'], ['target' => '', 'name' => 'Fair'], ['target' => '', 'name' => 'Good'], ['target' => '', 'name' => 'Excellent']];
                                        @endphp
                                        <select name="credit_score" id="credit_score" class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($credit_score as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                {{-- 14 Jul 2023  --}}
                                {{-- 14 Jul 2023  --}}
                                <div class="wizard-step">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            Has the tenant ever been evicted?
                                            <span class="text-danger">*</span>
                                        </label>
                                        @php
                                            $evicted = [['target' => '.custom_evicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                                        @endphp
                                        <select name="evicted" id="evicted" class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($evicted as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                    data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group custom_evicted d-none">
                                        <label class="fw-bold" for="custom_evicted">Explain when and why:</label>
                                        <input type="text" name="custom_evicted" id="custom_evicted"
                                            placeholder="Explain when and why" class="form-control"
                                            data-icon="fa-solid fa-bath" required>
                                    </div>
                                </div>
                                {{-- 14 Jul 2023  --}}
                                {{-- 14 Jul 2023  --}}
                                <div class="wizard-step">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            Has the tenant been convicted of a felony?
                                            <span class="text-danger">*</span>
                                        </label>
                                        @php
                                            $convicted = [['target' => '.custom_convicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                                        @endphp
                                        <select name="convicted" id="convicted" class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($convicted as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                    data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group custom_convicted d-none">
                                        <label class="fw-bold" for="custom_convicted">Explain when and what for:</label>
                                        <input type="text" name="custom_convicted" id="custom_convicted"
                                            placeholder="Explain when and what for" class="form-control"
                                            data-icon="fa-solid fa-bath" required>
                                    </div>
                                </div>
                                {{-- 14 Jul 2023  --}}
                                {{-- 14 Jul 2023  --}}
                                <div class="wizard-step">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            What is the tenant's monthly net household income?
                                            <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" name="monthly_income" id="monthly_income" placeholder="8000"
                                            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                                    </div>
                                </div>
                                {{-- 14 Jul 2023  --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        What are the tenant's terms for working with an agent to secure a property?
                                        <span class="text-danger">*</span>
                                    </label>
                                    @php
                                        $tenant_terms = [['target' => '', 'name' => '1 month'], ['target' => '', 'name' => '2 months'], ['target' => '', 'name' => '3 months'], ['target' => '', 'name' => '4 months'], ['target' => '', 'name' => '5 months'], ['target' => '', 'name' => '6 months'], ['target' => '', 'name' => '9 months'], ['target' => '', 'name' => '12 months'], ['target' => '.custom_tenant_terms', 'name' => 'Other']];
                                    @endphp
                                    <select name="tenant_terms" id="tenant_terms" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($tenant_terms as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_tenant_terms d-none">
                                    <label class="fw-bold" for="custom_tenant_terms">What are the tenant's terms for
                                        working with an agent to secure a property?</label>
                                    <input type="text" name="custom_tenant_terms" id="custom_tenant_terms"
                                        placeholder="Custom tenant terms" class="form-control"
                                        data-icon="fa-solid fa-bath" required>
                                </div>
                            </div>
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Total finder's fee offered to agents: (To be paid by the tenant to the agent upon
                                        lease signing)
                                        <span class="text-danger">*</span>
                                    </label>
                                    @php
                                        $agents_fee = [['target' => '', 'name' => '1 month\'s rent'], ['target' => '', 'name' => '50% of one month\'s rent'], ['target' => '', 'name' => '10% of the value of the lease'], ['target' => '', 'name' => '6% of the value of the lease'], ['target' => '', 'name' => 'Negotiable'], ['target' => '.custom_agents_fee', 'name' => 'Other']];
                                    @endphp
                                    <select name="agents_fee" id="agents_fee" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($agents_fee as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_agents_fee d-none">
                                    <label for="custom_agents_fee">Total finder's fee offered to agents:
                                        (To be paid by the tenant to the agent upon lease signing)</label>
                                    <input type="text" name="custom_agents_fee" id="custom_agents_fee"
                                        placeholder="Custom Agents Fee" class="form-control" data-icon="fa-solid fa-bath"
                                        required>
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Would the tenant be willing to pay a fee to the agent for their assistance in finding a property?
                                    </label>
                                    @php
                                        $has_pay_fee = [
                                            ['target' => '.has_pay_fee', 'name' => 'Yes'],
                                            ['target' => '', 'name' => 'No'],

                                        ];
                                    @endphp
                                    <select name="has_pay_fee"  class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($has_pay_fee as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group has_pay_fee">
                                    <label for="">Fee</label>
                                    <input type="number" name="fee" placeholder="$" id="fee"
                                        min="25" max="50"
                                        class="form-control has-icon search_places col-3" data-icon="fa-solid fa-dollar"
                                        data-msg-required="">
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                @php
                                    $services_data = [
                                            ['target' => '', 'name' => 'Tenant’s listing on the platform BidYourOffer.com.'],
                                            ['target' => '', 'name' => "Sending prompt email notifications containing properties that meet the tenant's criteria as soon as they are listed, ensuring access to the most up-to-date listings."],
                                            ['target' => '', 'name' => "Marketing the tenant’s listing on various groups, pages, and affiliates, along with a QR code or listing link that leads to the listing on BidYourOffer.com."],
                                            ['target' => '', 'name' => "Promoting the tenant’s listing on social media platforms with a QR code or listing link that leads to the listing on BidYourOffer.com."],
                                            ['target' => '', 'name' => "Scheduling and accompanying the tenant on property viewings and showings."],
                                            ['target' => '', 'name' => "Scheduling video tours of the tenant’s preferred properties (ideal for out-of-town tenants)."],
                                            ['target' => '', 'name' => "Assisting with the tenant's rental application process, including providing guidance and support."],
                                            ['target' => '', 'name' => "Assisting with the negotiation of lease terms, including rental price, lease duration, and any additional clauses or provisions."],
                                            ['target' => '', 'name' => "Coordinating and overseeing the move-in process, including inspections and key handovers"],
                                            ['target' => '', 'name' => "Providing resources and recommendations for local amenities, such as schools, parks, and healthcare facilities."],
                                            ['target' => '', 'name' => "Facilitating communication between the tenant and the landlord or property management company."],
                                            ['target' => '', 'name' => "Offering resources and information on tenant rights and responsibilities."],
                                            ['target' => '', 'name' => "Providing guidance on lease renewal options and negotiating rent adjustments if necessary."],
                                            ['target' => '', 'name' => "Other - Add additional services as needed."],
                                        ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Select the included services that the Agent will provide to the
                                        Tenant:</label>
                                    <select class="grid-picker" name="services[]" id="services" multiple required>
                                        <option value="">Select</option>
                                        @foreach ($services_data as $service)
                                            <option value="{{ $service['name'] }}"
                                                data-target="{{ $service['target'] }}" class="card flex-row fw-bold"
                                                style="width:calc(100% - 0px);"
                                                data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                                                {{ $service['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group other_services d-none">
                                    <label>Write other services</label>
                                    <input type="text" name="other_services" id="other_services"
                                        class="form-control">
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Does the tenant have any preferred agent(s) whom they would like to notify to
                                        participate in the auction?
                                        <span class="text-danger">*</span>
                                    </label>
                                    @php
                                        $has_preferred_agent = [['target' => '.preferred_agent', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                                    @endphp
                                    <select name="has_preferred_agent" id="has_preferred_agent" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($has_preferred_agent as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group preferred_agent d-none">
                                    <h5>Please enter the first name, last name,
                                        brokerage, phone number, and email address of the preferred agent(s):</h5>
                                    <div class="form-group">
                                        <label>First Name:</label>
                                        <input type="text" name="first_name" id="first_name" placeholder="First Name"
                                            class="form-control has-icon" data-icon="fa-solid fa-user" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Last Name:</label>
                                        <input type="text" name="last_name" id="last_name" placeholder="Last Name"
                                            class="form-control has-icon" data-icon="fa-solid fa-user" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Brokerage:</label>
                                        <input type="text" name="brokerage" id="brokerage" placeholder="Brokerage"
                                            class="form-control has-icon" data-icon="fa-solid fa-handshake" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Cell phone #:</label>
                                        <input type="text" name="phone" id="phone" placeholder="Phone"
                                            class="form-control has-icon" data-icon="fa-solid fa-phone" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Email:</label>
                                        <input type="text" name="email" id="email" placeholder="Email"
                                            class="form-control has-icon" data-icon="fa-solid fa-envelope">
                                    </div>
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label>What are the most important aspects you will consider when hiring a real estate agent?</label>
                                    <input type="text" name="important_aspect" id="other_services"
                                        class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>What additional details would the tenant like to share with the agent?</label>
                                    <input type="text" name="additional_details" id="other_services"
                                        class="form-control">
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
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
                            {{-- <div class="form-group mt-4 text-right">
                                <button class="btn btn-lg text-600 btn-success" type="submit">Save</button>
                            </div> --}}
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <template class="city_temp">
        <tr>
            <td><input type="text" name="cities[]" placeholder="City" data-type="cities"
                    class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                    data-msg-required="Please enter city"></td>
        </tr>
    </template>
    <template class="county_temp">
        <tr>
            <td><input type="text" name="counties[]" placeholder="County" data-type="counties"
                    class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                    data-msg-required="Please enter County"></td>
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
                $('.residential_and_income_hide').addClass('d-none');
                $('.residential_and_income').removeClass('d-none');
                $('.for_income_only').addClass('d-none');
                $('.for_residential_only').removeClass('d-none');
                $('.residential_hide').removeClass('d-none');
                $('.business-length').hide();

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
            console.log(v);
            check_custom();
        }

        function check_custom() {
            $('.option-container').each(function(i, elm) {
                var target = $(elm).data('target') || "";
                var is_active = $(elm).hasClass('active');
                if (target != "") {
                    console.log("is_active", is_active, target);
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

                var v = $(".mainform").validate({
                    errorClass: "text-error text-danger w-100",
                    onkeyup: false,
                    onfocusout: false,
                    /* submitHandler: function() {
                        // alert("Submitted, thanks!");
                        $(".mainform").submit();
                    } */
                });

                StepWizard.setStep();
                property_type;
                $('#property_type').on('change', function() {
                    property_type = $(this).val();
                    // Count the remaining steps without removing them
                });
                $('.wizard-step-next').click(function(e) {
                    // alert($('.wizard-step.active').next().is('.wizard-step'));
                    if (v.form()) {
                        if ($('.wizard-step.active').next().is('.wizard-step')) {

                            // $('.wizard-step.active').removeClass('active').next().addClass('active');
                            $('.wizard-step.active').removeClass('active');
                            if (StepWizard.currentStep == 7 && property_type ==
                                'Income Property') {
                                StepWizard.nextStep = 10;
                                StepWizard.backStep = 7;
                            } else if (StepWizard.currentStep == 9 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 11;
                                StepWizard.backStep = 9;
                            } else if (StepWizard.currentStep == 24 && property_type ==
                                'Business Opportunity') {
                                StepWizard.nextStep = 26;
                                StepWizard.backStep = 24;
                            } else if (StepWizard.currentStep == 4 && (property_type ==
                                    'Commercial Property' || property_type == 'Business Opportunity')

                            ) {
                                if (property_type == 'Commercial Property') {
                                    StepWizard.nextStep = 21;
                                    StepWizard.backStep = 4;
                                } else {
                                    StepWizard.nextStep = 20;
                                    StepWizard.backStep = 4;
                                }

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
                                StepWizard.currentStep == 19 &&
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
                        } else if (StepWizard.currentStep == 11 && property_type ==
                            'Residential Property') {

                            StepWizard.backStep = 9;
                        } else if (StepWizard.currentStep == 26 && property_type ==
                            'Business Opportunity') {

                            StepWizard.backStep = 24;
                        } else if (StepWizard.currentStep == 21 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 4;
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
                    if (property_type == 'Residential Property' || property_type == 'Income Property') {
                        var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                            return parseInt($(this).attr('data-step')) >= 20 && parseInt($(this)
                                .attr('data-step')) <= 43;
                        });

                        $stepsToRemove.each(function() {
                            $(this).closest('div[data-step]').remove();
                        });
                    }
                    //Remove All the SLides Except THe Commercial and Business Opportunity
                    if (property_type === 'Commercial Property' || property_type ===
                        'Business Opportunity') {
                        var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                            var stepValue = parseInt($(this).attr('data-step'));
                            return (stepValue >= 5 && stepValue <= 19) || (stepValue >= 33 &&
                                stepValue <= 43);
                        });

                        $stepsToRemove.each(function() {
                            $(this).closest('div[data-step]').remove();
                        });
                    }
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
                var comp = 0;

                if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 19) {
                    // Calculate progress for Residential and Income property steps (5 to 19)
                    comp = 20 + (((StepWizard.currentStep - 5) / (19 - 5)) * 80);
                } else if (StepWizard.currentStep >= 20 && StepWizard.currentStep <= 32) {
                    // Calculate progress for Commercial and Business opportunity steps (20 to 32)
                    comp = 20 + (((StepWizard.currentStep - 20) / (32 - 20)) * 80);
                } else if (StepWizard.currentStep >= 33 && StepWizard.currentStep <= 43) {
                    // Calculate progress for Vacant land steps (33 to 43)
                    comp = 20 + (((StepWizard.currentStep - 33) / (43 - 33)) * 80);
                } else {
                    // Default progress calculation for other steps
                    comp = ((StepWizard.currentStep - 1) / 8) * 20;
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
    </script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
    </script>
@endpush
