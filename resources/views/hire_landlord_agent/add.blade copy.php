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
        .address_confidential{
            color: #6666668c;
            font-family: emoji;

        }

        /* Style to hide the custom_occupant_type by default */

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
                    <form class="p-4 pt-0 validate mainform" action="{{ route('landlord.hire.agent.auction') }}"
                        method="POST" enctype="multipart/form-data">
                        @csrf
                        @push('scripts')
                            <script>
                                $(function() {
                                    $('#working_with_agent').change(function() {
                                        var val = $(this).val();
                                        if (val == "Yes") {
                                            $('.wizard-step-next').attr("disabled", "disabled");
                                            $('.yes_message').text(
                                                "This is a service for landlords that are not currently working with a licensed agent."
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
                                Hire Landlord's Agent auction
                            </h4>
                            <div class="wizard-steps-progress">
                                <div class="steps-progress-percent"></div>
                            </div>
                          {{-- Slide 1 --}}
                            <div class="wizard-step" data-step="1">
                                <div class="form-group">
                                    <label class="fw-bold">Is the landlord currently represented by another agent?
                                    </label>
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
                            {{-- Slide 1 --}}
                            {{-- Slide 2 --}}
                            <div class="wizard-step" data-step="2">
                                <h4> Please provide the property&#39;s complete
                                    address, along with the city, county, and state, pertaining to the real estate asset that the
                                    landlord intends to place on the market:</h4>
                                <div class="form-group">
                                    <label class="fw-bold">Address</label>
                                    <input type="text" name="address"
                                        class="form-control search_places has-icon" data-type="address" required
                                        value="{{ old('address') }}" data-icon="fa-solid fa-location-dot"  placeholder="Address"/>
                                    @if ($errors->has('address'))
                                        <div class="small error">{{ $errors->first('address') }}</div>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">City </label>
                                    <input type="text" name="city" data-type="cities"
                                        id="city" class="form-control has-icon search_places"
                                        data-icon="fa-solid fa-city"  placeholder="City"  required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">County </label>
                                    <input type="text" name="county" data-type="counties"
                                        id="county" class="form-control has-icon search_places"
                                        data-icon="fa-solid fa-tree-city"  placeholder="County"  required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">State</label>
                                    <input type="text" name="state" data-type="states"
                                        id="state" class="form-control has-icon search_places"
                                        data-icon="fa-solid fa-flag-usa"  placeholder="State" required>
                                </div>
                                <p class="mt-3 address_confidential"> <span class="fw-bold">Note :</span> listing&#39;s address remains confidential until a seller employs an agent.</p>
                            </div>
                            {{-- Slide 2 --}}
                            {{-- Slide 3 --}}
                            <div class="wizard-step" data-step="3">
                                <div class="form-group">
                                    <label for="address" class="fw-bold">Listing Date:</label>
                                    <input type="date" name="listing_date" id="listing_date"
                                        class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                        data-msg-required="" required>
                                </div>

                                <div class="form-group">
                                    <label for="address" class="fw-bold">Expiration Date:</label>
                                    <input type="date" name="expiration_date" id="expiration_date"
                                        class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                        data-msg-required="" required>
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
                                            $auction_types = [['name' => 'Auction (Timer)', 'icon' => '<i class="fa-regular fa-clock"></i>', 'target' => '.auction_length_cover'], ['name' => 'Traditional (No Timer)', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>', 'target' => '']];
                                        @endphp

                                        <select name="auction_type" id="auction_type" class="grid-picker"
                                            style="justify-content: flex-start;" onchange="changeAuctionType(this.value);"
                                            required>
                                            <option value=""></option>
                                            @foreach ($auction_types as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row" style="width:calc(33.3% - 10px);"
                                                    data-icon='{{ $item['icon'] }}'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group auction_length_cover d-none">
                                    <label class="fw-bold">
                                        Auction Length:
                                    </label>
                                    <div>
                                        @php
                                            $auction_lengths = [['name' => '1 Day', 'class' => 'normal-length'], ['name' => '3 Days', 'class' => 'normal-length'], ['name' => '5 Days', 'class' => 'normal-length'], ['name' => '7 Days', 'class' => 'normal-length'], ['name' => '10 Days', 'class' => 'normal-length'], ['name' => '14 Days', 'class' => 'normal-length'], ['name' => '21 Days', 'class' => 'normal-length'], ['name' => '30 Days', 'class' => 'normal-length'], ['name' => '45 Days', 'class' => 'normal-length'], ['name' => '60 Days', 'class' => 'normal-length'], ['name' => '75 Days', 'class' => 'normal-length'], ['name' => '90 Days', 'class' => 'normal-length'], ['name' => 'No time limit', 'class' => 'traditional-length']];
                                        @endphp
                                        <select name="auction_length" id="auction_length"
                                            class="auction_length grid-picker" style="justify-content: flex-start;"
                                            required>
                                            <option value=""></option>
                                            @foreach ($auction_lengths as $item)
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

                            </div>
                            {{-- Slide 4 --}}
                            {{-- Slide 5 --}}
                            <div class="wizard-step" data-step="5">
                                @php
                                    $property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Property Type:</label>
                                    <select class="grid-picker" name="property_type" id="property_type"
                                        onchange="changePropertyType(this.value);" required>
                                        <option value="">Select</option>
                                        @foreach ($property_types as $row_pt)
                                            <option value="{{ $row_pt['name'] }}" class="card flex-column"
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
                                                ['name' => 'Five or More (Commercial units)', 'class' => 'commercial-length'],
                                                ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                                                ['name' => 'Industrial', 'class' => 'commercial-length'],
                                                ['name' => 'Mixed Use', 'class' => 'commercial-length'],
                                                ['name' => 'Office', 'class' => 'commercial-length'],
                                                ['name' => 'Restaurant', 'class' => 'commercial-length'],
                                                ['name' => 'Retail', 'class' => 'commercial-length'],
                                                ['name' => 'Unimproved Land', 'class' => 'residential-length'],
                                                ['name' => '1/3 Triplex', 'class' => 'residential-length'],
                                                ['name' => '1/4 Quadplex', 'class' => 'residential-length'],
                                                ['name' => 'Warehouse', 'class' => 'commercial-length'],
                                            ];
                                        @endphp
                                        <select name="property_items" id="property_items"
                                            class="property_items grid-picker" style="justify-content: flex-start;"
                                            multiple required>
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
                            </div>
                            {{-- Slide 5 --}}
                            {{-- Slide 6 --}}
                            <div class="wizard-step" data-step="6">
                                @php
                                    $prop_conditions = [
                                        ['name' => 'Completely Updated', 'target' => ''],
                                         ['name' => 'Partially Updated', 'target' => ''],
                                          ['name' => 'Not Updated', 'target' => ''],
                                           ['name' => 'Other', 'target' => '.custom_property_condition']
                                        ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Select the property condition: </label>
                                    <select class="grid-picker" name="prop_condition" id="prop_condition"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($prop_conditions as $item)
                                        @php
                                        if ($item['name'] == 'Other') {
                                            $target = '.custom_property_condition';
                                        }else{

                                        }
                                    @endphp
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row"  data-icon='<i class="fa-regular fa-circle-check"></i>' style="width:calc(50% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_property_condition d-none">
                                    <label class="fw-bold"> What is the property condition?</label>
                                    <input type="text" class="form-control has-icon"
                                        name="custom_property_condition" data-icon="fa-solid fa-qrcode"
                                        id="custom_property_condition" required />
                                </div>
                            </div>
                            {{-- Slide 6 --}}
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}

                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}

                            {{-- 17 Jul 2023 --}}

                            {{-- 17 Jul 2023 --}}

                            {{-- 17 Jul 2023 --}}
                            @php
                                $bedrooms = [['name' => '1', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Other', 'target' => '.custom_bedrooms']];
                                $bathrooms = [['name' => '1', 'target' => ''], ['name' => '1.5', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '2.5', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '3.5', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '4.5', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Other', 'target' => '.custom_bathrooms']];
                            @endphp
                            <div class="wizard-step"  data-step="7">
                                <div class="form-group traditional_hide">
                                    <label class="fw-bold">How many bedrooms does the property have?</label>
                                    <select class="grid-picker" name="bedrooms" id="bedrooms"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($bedrooms as $bedroom)
                                            <option value="{{ $bedroom['name'] }}"
                                                data-target="{{ $bedroom['target'] }}" class="card flex-column"
                                                style="width:calc(10% - 10px);"
                                                data-icon='<i class="fa-solid fa-bed"></i>'>
                                                {{ $bedroom['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_bedrooms d-none">
                                    <label class="fw-bold">Bedrooms:</label>
                                    <input type="number" class="form-control has-icon"
                                        placeholder="Write Custom Bedrooms" name="custom_bedrooms" data-icon="fa fa-bed"
                                        id="custom_bedrooms" required />
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">How many bathrooms does the property have? </label>
                                    <select class="grid-picker" name="bathrooms" id="bathrooms"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($bathrooms as $bathroom)
                                            <option value="{{ $bathroom['name'] }}"
                                                data-target="{{ $bathroom['target'] }}" class="card flex-column"
                                                style="width:calc(10% - 10px);"
                                                data-icon='<i class="fa-solid fa-bath"></i>'>
                                                {{ $bathroom['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_bathrooms d-none">
                                    <label class="fw-bold">How many bathrooms does the property have?</label>
                                    <input type="number" class="form-control has-icon"
                                        placeholder="Write Custom Bathrooms" name="custom_bathrooms"
                                        data-icon="fa-solid fa-bath" id="custom_bathrooms" required />
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step"  data-step="8">
                                <div class="form-group residential_hide">
                                    <label class="fw-bold">What is the heated square footage of the property?</label>
                                    <input type="number" name="heated_square_footage" data-type="heated_square_footage"
                                        placeholder="Enter Heated Square Footage" id="heated_square_footage"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="" required />
                                </div>

                                <div class="form-group commercial_hide">
                                    <label class="fw-bold">What is the net leasable square footage of the property?</label>
                                    <input type="number" name="net_leasable_square_footage"
                                        data-type="heated_square_footage" placeholder="Enter Net Leasable Square Footage"
                                        id="net_square_footage" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check"
                                        data-msg-required="" required />
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step"  data-step="9">
                                @php
                                    $appliances = [
                                        ['name' => 'Bar Fridge', 'target' => ''],
                                        ['name' => 'Built-In Oven', 'target' => ''],
                                        ['name' => 'Convection Oven', 'target' => ''],
                                        ['name' => 'Cooktop', 'target' => ''],
                                        ['name' => 'Dishwasher', 'target' => ''],
                                        ['name' => 'Disposal', 'target' => ''],
                                        ['name' => 'Dryer', 'target' => ''],
                                        ['name' => 'Electric Water Heater', 'target' => ''],
                                        ['name' => 'Exhaust Fan', 'target' => ''],
                                        ['name' => 'Freezer', 'target' => ''],
                                        ['name' => 'Gas Water Heater', 'target' => ''],
                                        ['name' => 'Ice Maker', 'target' => ''],
                                        ['name' => 'Indoor Grill', 'target' => ''],
                                        ['name' => 'Kitchen Reverse Osmosis System', 'target' => ''],
                                        ['name' => 'Microwave', 'target' => ''],
                                        ['name' => 'Range Electric', 'target' => ''],
                                        ['name' => 'Range Gas', 'target' => ''],
                                        ['name' => 'Range Hood', 'target' => ''],
                                        ['name' => 'Refrigerator', 'target' => ''],
                                        ['name' => 'Solar Hot Water', 'target' => ''],
                                        ['name' => 'Solar Hot Water Owned', 'target' => ''],
                                        ['name' => 'Solar Hot Water Rented', 'target' => ''],
                                        ['name' => 'Tankless Water Heater', 'target' => ''],
                                        ['name' => 'Trash Compactor', 'target' => ''],
                                        ['name' => 'Washer', 'target' => ''],
                                        ['name' => 'Water Filtration System', 'target' => ''],
                                        ['name' => 'Water Purifier', 'target' => ''],
                                        ['name' => 'Water Softener', 'target' => ''],
                                        ['name' => 'Whole House R.O. System', 'target' => ''],
                                        ['name' => 'Wine Refrigerator', 'target' => ''],
                                        ['name' => 'None', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.cutom_appliances'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">What appliances are included in the property? </label>
                                    <select class="grid-picker" name="appliances[]" id="appliances"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value="">Select</option>
                                        @foreach ($appliances as $appliance)
                                            <option value="{{ $appliance['name'] }}"
                                                data-target="{{ $appliance['target'] }}" class="card flex-row"
                                                style="width:calc(33.33% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $appliance['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group cutom_appliances d-none">
                                    <label class="fw-bold"></label>What appliances are included in the property?</label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Appliances" name="appliances[]"
                                        data-icon="fa-solid fa-qrcode" id="cutom_appliances" required />
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step"  data-step="10">
                                @php
                                    $rent_includes = [['name' => 'Electricity', 'target' => ''], ['name' => 'Cable TV', 'target' => ''], ['name' => 'Gas', 'target' => ''], ['name' => 'Grounds Care', 'target' => ''], ['name' => 'Insurance', 'target' => ''], ['name' => 'Internet', 'target' => ''], ['name' => 'Laundry', 'target' => ''], ['name' => 'Management', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pest Control', 'target' => ''], ['name' => 'Pool Maintenance', 'target' => ''], ['name' => 'Recreational', 'target' => ''], ['name' => 'Repairs', 'target' => ''], ['name' => 'Security', 'target' => ''], ['name' => 'Sewer', 'target' => ''], ['name' => 'Taxes', 'target' => ''], ['name' => 'Telephone', 'target' => ''], ['name' => 'Trash Collection', 'target' => ''], ['name' => 'Water', 'target' => ''],['name' => 'Other', 'target' => '.rent_include']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">What amenities or utilities are included in the rent? </label>
                                    <select class="grid-picker" name="rent_include[]" id="rent_include"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($rent_includes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.33% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group rent_include d-none">
                                    <label class="fw-bold"></label>What amenities or utilities are included in the rent? </label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Amenities or Utilities" name="rent_include[]"
                                        data-icon="fa-solid fa-qrcode" id="rent_include" required />
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}



                            <div class="wizard-step" data-step="11">
                                @php
                                $occupant_types = [['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                                   ['name' => 'Occupied', 'target' => '.custom_occupant_type', 'icon' => 'fa-regular fa-circle-check']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">What is the current occupancy status of the property?</label>
                                    <select class="grid-picker" name="occupant_type" id="occupant_type_select" style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($occupant_types as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_occupant_type d-none">
                                    <label class="fw-bold">When is the property occupied until?<span class="text-danger"></span></label>
                                    <input type="number" class="form-control has-icon" placeholder=" " name="occupied_until" data-icon="fa-solid fa-qrcode" id="occupant_type_input" required />
                                </div>
                            </div>





                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step"  data-step="12">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        What is the desired rental amount for the landlord's property?
                                    </label>
                                    <input type="number" class="form-control has-icon" placeholder="e.g 5000"
                                        name="expectation" data-icon="fa-solid fa-dollar" id="expectation" required />
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            {{-- <div class="wizard-step"  data-step="13">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        When will the property be ready for rent?
                                    </label>
                                    @php
                                        $timeframes = [['name' => 'Now', 'target' => ''], ['name' => '15 days', 'target' => ''], ['name' => '30 days', 'target' => ''], ['name' => '60 days', 'target' => ''], ['name' => '90 days', 'target' => ''], ['name' => 'Other', 'target' => '.custom_ready_timeframe']];
                                    @endphp
                                    <select name="ready_timeframe" id="ready_timeframe" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($timeframes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_ready_timeframe d-none">
                                    <label class="fw-bold">When will the property be ready for rent?</label>
                                    <input type="date" name="custom_ready_timeframe" id="custom_ready_timeframe"
                                        class="form-control" data-icon="fa-regular fa-calendar-days" required>
                                </div>
                            </div> --}}
                            <div class="wizard-step"  data-step="13">
                                <div class="form-group">

                                    <label class="fw-bold">When will the property be ready for rent?</label>
                                    <input type="date" name="custom_ready_timeframe" id="custom_ready_timeframe"
                                        class="form-control" data-icon="fa-regular fa-calendar-days">
                                </div>
                            </div>

                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step"  data-step="14">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        How long would the landlord like to lease their property for?
                                    </label>
                                    @php
                                        $lease_period = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '1 year', 'target' => ''], ['name' => '2 years', 'target' => ''], ['name' => '3-5 years', 'target' => ''], ['name' => '5+ years', 'target' => ''], ['name' => 'Other', 'target' => '.custom_lease_period']];
                                    @endphp
                                    <select name="lease_period" id="lease_period" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($lease_period as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_lease_period d-none">
                                    <label class="fw-bold">How long would the landlord like to lease their property
                                        for?</label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Lease Period" name="custom_lease_period"
                                        data-icon="fa-regular fa-square-check" id="custom_lease_period" required />
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step commercial_remove"  data-step="15">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        What are the terms of the lease?
                                    </label>
                                    @php
                                        $lease_term = [['name' => 'Absolute (Triple) Net', 'target' => ''], ['name' => 'Gross Lease', 'target' => ''], ['name' => 'Gross Percentages', 'target' => ''], ['name' => 'Ground Lease', 'target' => ''], ['name' => 'Lease Option', 'target' => ''], ['name' => 'Modified Gross', 'target' => ''], ['name' => 'Net Lease', 'target' => ''], ['name' => 'Net Net', 'target' => ''], ['name' => 'Pass Throughs', 'target' => ''], ['name' => 'Purchase Option', 'target' => ''], ['name' => 'Renewal Option', 'target' => ''], ['name' => 'Sale-Leaseback', 'target' => ''], ['name' => 'Seasonal', 'target' => ''], ['name' => 'Special Available (CLO)', 'target' => ''], ['name' => 'Varied Terms', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.custom_terms']];
                                    @endphp

                                    <select name="lease_term" id="lease_term" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($lease_term as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column" style="width:calc(33.33% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_terms d-none">
                                    <label class="fw-bold"> What are the terms of the lease?</label>
                                    <input type="text" class="form-control has-icon" placeholder="Write Custom Terms"
                                        name="custom_terms" data-icon="fa-regular fa-square-check"
                                        id="custom_terms" required />
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step"  data-step="16">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        What are the terms of the listing contract that the landlord is offering to the agent?
                                    </label>
                                    @php
                                        $listing_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '12 months', 'target' => ''], ['name' => 'Other', 'target' => '.custom_listing_terms']];
                                    @endphp
                                    <select name="listing_term" id="listing_term" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($listing_terms as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column"style="width:calc(33.33% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_listing_terms d-none">
                                    <label class="fw-bold"> What are the terms of the listing contract that the landlord is offering to the agent?</label>
                                    <input type="text" class="form-control has-icon" placeholder="Write Custom Terms"
                                        name="custom_listing_terms" data-icon="fa-regular fa-square-check"
                                        id="custom_listing_terms" required />
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step"  data-step="17">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        What is the total commission being offered to the agent?
                                    </label>
                                    @php
                                        $offered_commissions = [['name' => 'One month’s rent', 'target' => ''], ['name' => '10% of the value of the lease', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_offered_commission']];
                                    @endphp
                                    <select name="offered_commission" id="offered_commission" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($offered_commissions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(50% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" ></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_offered_commission d-none">
                                    <label class="fw-bold">What is the total commission being offered to the agent? $ or % of the value of the lease.
                                    </label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Commission" name="custom_offered_commission"
                                        data-icon="fa-regular fa-square-check" id="custom_offered_commission" required />
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}

                            <div class="wizard-step residential_remove" data-step="18">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Please select the services that the landlord requests from the agent:
                                    </label>
                                    @php
                                        $real_estate_service = [
                                            ['name' => 'List the property on the MLS.', 'target' => ''],
                                            ['name' => 'List the property on major real estate websites, including Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, which will generate thousands of views.', 'target' => ''],
                                            ['name' => 'List the property on the Bid Your Offer platform.', 'target' => ''],
                                            ['name' => 'Conduct tenant screening with a thorough application process that includes credit, criminal, background, eviction, and income verification checks.', 'target' => ''],
                                            ['name' => 'Provide professional photography.', 'target' => ''],
                                            ['name' => 'Provide aerial photography.', 'target' => ''],
                                            ['name' => 'Provide a professional video to showcase the property\'s interior and exterior.', 'target' => ''],
                                            ['name' => 'Provide a 3D tour to showcase the property\'s interior.', 'target' => ''],
                                            ['name' => 'Market the property to various groups, pages, and affiliates.', 'target' => ''],
                                            ['name' => 'Promote the property on social media platforms.', 'target' => ''],
                                            ['name' => 'Sending email alerts to tenants searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.', 'target' => ''],
                                            ['name' => 'Expert advice on setting an optimal rental price based on market analysis and current trends.', 'target' => ''],
                                            ['name' => 'Comprehensive property marketing strategy to attract a larger pool of potential tenants.', 'target' => ''],
                                            ['name' => 'Conducting property showings and viewings for interested tenants.', 'target' => ''],
                                            ['name' => 'Assisting with lease renewal negotiations and adjustments to rental terms.', 'target' => ''],
                                            ['name' => 'Handling negotiations and drafting the rental agreement or lease contract.', 'target' => ''],
                                            ['name' => 'Assisting with tenant move-in and move-out inspections.', 'target' => ''],
                                            ['name' => 'Coordinating property maintenance and repairs through trusted contractors and vendors.', 'target' => ''],
                                            ['name' => 'Offering ongoing property management services, including rent collection, property inspections, and tenant communication.', 'target' => ''],
                                            ['name' => 'Providing guidance on landlord-tenant laws and regulations to ensure legal compliance.', 'target' => ''],
                                            ['name' => 'Handling tenant inquiries, maintenance requests, and resolving any issues that may arise during the tenancy.', 'target' => ''],
                                            ['name' => 'Providing regular feedback and updates on the property\'s performance.', 'target' => ''],
                                            ['name' => 'Other - Add additional services as needed.Write Custom Services *', 'target' => '.custom_services_data'],
                                        ];
                                    @endphp
                                    <select name="real_estate_service" id="real_estate_service" class="grid-picker"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value=""></option>
                                        @foreach ($real_estate_service as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.33% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_services_data d-none">
                                    <label class="fw-bold">What is the total commission being offered to the agent? $ or % of the value of the lease.
                                    </label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Commission" name="custom_services_data"
                                        data-icon="fa-regular fa-square-check" id="custom_services_data" required />
                                </div>
                            </div>



                            {{-- 17 Jul 2023 --}}

                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step"  data-step="19">

                                <div class="row">
                                    <div class="form-group mt-4 col-md-12">
                                        <label>
                                            What are the most important aspects you will consider when hiring a real estate
                                            agent?
                                            <span class="">*</span>
                                        </label>
                                        <textarea name="description" placeholder="" class="form-control"
                                            rows="5" >{{ old('description') }}</textarea>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group mt-4 col-md-12">
                                        <label>
                                            What additional details would the landlord like to share with the agent?
                                        </label>
                                        <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5"></textarea>
                                    </div>
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            {{-- <div class="wizard-step"  data-step="20">
                                <div class="form-group">
                                    <label class="fw-bold"> Does the landlord have any preferred agent(s) whom they would
                                        like to notify to
                                        participate in the auction?</label>
                                    <select class="grid-picker" name="need_cma" id="seller_specific_price"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $yes_or_no)
                                            @php
                                                if ($yes_or_no['name'] == 'Yes') {
                                                    $target = '.yes_notify';
                                                } else {
                                                    $target = '';
                                                }
                                            @endphp
                                            <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
                                                class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                                {{ $yes_or_no['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="row yes_notify d-none">

                                    <div class="form-group">
                                        <label class="fw-bold">First Name:</label>
                                        <input type="text" name="first_name" id="first_name" placeholder=""
                                            class="form-control has-icon" data-icon="fa-solid fa-user"
                                            value="{{ Auth::user()->first_name }}" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Last Name:</label>
                                        <input type="text" name="last_name" id="last_name" placeholder=""
                                            class="form-control has-icon" data-icon="fa-solid fa-user"
                                            value="{{ Auth::user()->last_name }}" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Brokerage:</label>
                                        <input type="text" name="landlord_brokerage" id="agent_brokerage"
                                            placeholder="" class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                            value="{{ Auth::user()->brokerage }}" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Phone Number:</label>
                                        <input type="text" name="landlord_phone" id="agent_phone" placeholder=""
                                            class="form-control has-icon" data-icon="fa-solid fa-phone"
                                            value="{{ Auth::user()->phone }}" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Email:</label>
                                        <input type="text" name="landlord_email" id="agent_email" placeholder=""
                                            class="form-control has-icon" data-icon="fa-solid fa-envelope"
                                            value="{{ Auth::user()->email }}">
                                    </div>
                                </div>
                            </div> --}}
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            {{-- @push('scripts')
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
                            <div class="wizard-step cma_questions"  data-step="22">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Does the landlord wish to receive a free rental market analysis (RMA) from the
                                        agent?
                                    </label>
                                    <select name="free_rental_market_analysis" id="need_cma" class="grid-picker"
                                        style="justify-content: flex-start;" onchange="cma_questions();" required>
                                        <option value=""></option>
                                        @foreach ($yes_or_nos as $item)
                                            <option value="{{ $item['name'] }}" data-target="" class="card flex-column"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div> --}}


                            {{-- <div class="wizard-step residential_remove"  data-step="23">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Kitchen Upgrades:
                                    </label>
                                    @php
                                        $kitchen_upgrades = [['name' => 'Countertops', 'target' => ''], ['name' => 'Cabinets', 'target' => ''], ['name' => 'Appliances', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_kitchen_upgrades']];
                                    @endphp
                                    <select name="kitchen_upgrades" id="commercial-services" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($kitchen_upgrades as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_kitchen_upgrades">
                                    <label class="fw-bold">Add Custom Kitchen Upgrades</label>
                                    <input type="text" name="custom_kitchen_upgrade" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Kitchen Upgrades"
                                        required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="24">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Bathroom Upgrades:
                                    </label>
                                    @php
                                        $bathroom_upgrades = [['name' => 'Fixtures', 'target' => ''], ['name' => 'Vanities', 'target' => ''], ['name' => '', 'target' => ''], ['name' => 'Showers', 'target' => ''], ['name' => '', 'target' => ''], ['name' => 'Tubs', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_bathroom_upgrades']];
                                    @endphp
                                    <select name="bathroom_upgrades" id="commercial-services" multiple
                                        class="grid-picker" style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($bathroom_upgrades as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_bathroom_upgrades">
                                    <label class="fw-bold">Add Custom Bathroom Upgrade</label>
                                    <input type="text" name="custom_bathroom_upgrade" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Kitchen Upgrades"
                                        required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="25">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Flooring Upgrades:
                                    </label>
                                    @php
                                        $flooring_upgrades = [['name' => 'Hardwood', 'target' => ''], ['name' => 'Tile', 'target' => ''], ['name' => 'Carpet', 'target' => ''], ['name' => 'Laminate', 'target' => ''], ['name' => 'Vinyl', 'target' => ''], ['name' => 'Natural Stone', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_flooring_upgrades']];
                                    @endphp
                                    <select name="flooring_upgrades" id="commercial-services" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($flooring_upgrades as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_flooring_upgrades">
                                    <label class="fw-bold">Add Custom Flooring Upgrade</label>
                                    <input type="text" name="custom_bathroom_upgrade" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Kitchen Upgrades"
                                        required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="26">
                                <h4>Upgrades</h4>

                                <div class="form-group">
                                    <label class="fw-bold">
                                        Energy-Efficient Features:
                                    </label>
                                    @php
                                        $energy_efficient_features = [['name' => 'Solar panels', 'target' => ''], ['name' => 'Insulation', 'target' => ''], ['name' => 'Energy-efficient appliances', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_energy_efficient']];
                                    @endphp
                                    <select name="energy_efficient_features" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($energy_efficient_features as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_energy_efficient">
                                    <label class="fw-bold">Custom Energy Efficient</label>
                                    <input type="text" name="custom_energy_efficient" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Kitchen Upgrades"
                                        required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="27">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Smart Home Technology:
                                    </label>
                                    @php
                                        $smart_home_technology_upgrades = [['name' => 'Smart thermostats', 'target' => ''], ['name' => 'Automated lighting', 'target' => ''], ['name' => 'Security systems', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_smart_home_technology']];
                                    @endphp
                                    <select name="smart_home_technology_upgrades" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($smart_home_technology_upgrades as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_smart_home_technology">
                                    <label class="fw-bold">Custom Smart Home Technology </label>
                                    <input type="text" name="custom_smart_home_technology" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Upgrades" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="28">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        HVAC Upgrades:
                                    </label>
                                    @php
                                        $hvac_upgrades = [['name' => 'Newer HVAC system', 'target' => ''], ['name' => 'Automated lighting', 'target' => ''], ['name' => 'Security systems', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_hvac_upgrade']];
                                    @endphp
                                    <select name="hvac_upgrades" class="grid-picker" style="justify-content: flex-start;"
                                        required>
                                        <option value=""></option>
                                        @foreach ($hvac_upgrades as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_hvac_upgrade">
                                    <label class="fw-bold">Custom HVAC Upgrades</label>
                                    <input type="text" name="custom_hvac_upgrade" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Upgrades" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="29">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Roofing Upgrades:
                                    </label>
                                    @php
                                        $roofing_upgrades = [['name' => 'New roof', 'target' => ''], ['name' => 'Solar tiles', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_roofing_upgrades']];
                                    @endphp
                                    <select name="roofing_upgrades" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($roofing_upgrades as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_roofing_upgrades">
                                    <label class="fw-bold">Custom Roofing Upgrade </label>
                                    <input type="text" name="custom_roofing_upgrades" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Upgrades" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="30">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Window Upgrades
                                    </label>
                                    @php
                                        $window_upgrades = [['name' => 'Double-pane windows', 'target' => ''], ['name' => 'Energy-efficient windows', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_window_upgrades']];
                                    @endphp
                                    <select name="window_upgrades" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($window_upgrades as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_window_upgrades">
                                    <label class="fw-bold">Custom Window Upgrade </label>
                                    <input type="text" name="custom_window_upgrades" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Upgrades" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="31">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Electrical or Plumbing Upgrades:
                                    </label>
                                    @php
                                        $electrical_upgrades = [['name' => 'Updated wiring', 'target' => ''], ['name' => 'Plumbing fixtures', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_electrical_upgrades']];
                                    @endphp
                                    <select name="electrical_upgrades" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($electrical_upgrades as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_electrical_upgrades">
                                    <label class="fw-bold">Custom Electrical or Plubming Upgrade </label>
                                    <input type="text" name="custom_electrical_upgrades" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Upgrades" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="32">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Renovations or Additions:
                                    </label>
                                    @php
                                        $renovations_upgrades = [['name' => 'Additional rooms', 'target' => ''], ['name' => 'Expanded living spaces', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_renovation_upgrade']];
                                    @endphp
                                    <select name="renovations_upgrades" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($renovations_upgrades as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_renovation_upgrade">
                                    <label class="fw-bold">Custom Renovation or Additions </label>
                                    <input type="text" name="custom_renovation_upgrade" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Upgrades" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="33">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Solar water heater:
                                    </label>
                                    @php
                                        $solar_water_heater = [['name' => 'High-end appliances', 'target' => ''], ['name' => 'Upgraded lighting fixtures', 'target' => ''], ['name' => 'Hardwood or engineered wood flooring', 'target' => ''], ['name' => 'Custom cabinetry or built-in storage', 'target' => ''], ['name' => 'Soundproofing or acoustic enhancements', 'target' => ''], ['name' => 'Upgraded electrical panel', 'target' => ''], ['name' => 'New plumbing system or fixtures', 'target' => '']];
                                    @endphp
                                    <select name="solar_water_heater" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($solar_water_heater as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="34">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Exterior Upgrades:
                                    </label>
                                    @php
                                        $exterior_upgrades = [['name' => 'New siding', 'target' => ''], ['name' => 'Fresh paint', 'target' => ''], ['name' => 'Landscaping', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_exterior_upgrades']];
                                    @endphp
                                    <select name="exterior_upgrades" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($exterior_upgrades as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_exterior_upgrades">
                                    <label class="fw-bold">Custom Exterior Upgrades</label>
                                    <input type="text" name="custom_exterior_upgrade" data-type="phones"
                                        placeholder="Enter phone number" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Upgrades" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="35">
                                <h4>Upgrades</h4>
                                <div class="form-group">
                                    <label class="fw-bold">Custom Upgrade</label>
                                    <input type="text" name="custom_upgrade" data-type="custom_upgrade"
                                        placeholder="Custom Upgrade" id="custom_upgrade" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Upgrades" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="36">
                                <h4>Amenities</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Pool:
                                    </label>
                                    @php
                                        $pool = [['name' => 'Private', 'target' => ''], ['name' => 'Community', 'target' => '']];
                                    @endphp
                                    <select name="pool" class="grid-picker" style="justify-content: flex-start;"
                                        required>
                                        <option value=""></option>
                                        @foreach ($pool as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Garage (number of spaces)</label>
                                    <input type="number" name="garage_number_of_spaces"
                                        data-type="garage_number_of_spaces" placeholder="Enter Garage Spaces"
                                        id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Amenities" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Patio:
                                    </label>
                                    @php
                                        $patio = [['name' => 'Deck', 'target' => ''], ['name' => 'Balcony', 'target' => ''], ['name' => 'Spa', 'target' => ''], ['name' => 'Garden', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_patio']];
                                    @endphp
                                    <select name="patio" class="grid-picker" style="justify-content: flex-start;"
                                        required>
                                        <option value=""></option>
                                        @foreach ($patio as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_patio">
                                    <label class="fw-bold">Custom Patio</label>
                                    <input type="text" name="custom_patio" data-type="phones"
                                        placeholder="Enter custom Patio" id="phone" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Amenities" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="37">
                                <h4>Amenities</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Security Features:
                                    </label>
                                    @php
                                        $security_features = [['name' => 'Security system', 'target' => ''], ['name' => 'Gated community', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_security_features']];
                                    @endphp
                                    <select name="security_features" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($security_features as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_security_features">
                                    <label class="fw-bold">Custom Security Features</label>
                                    <input type="text" name="custom_security_features"
                                        data-type="custom_security_features" placeholder="Enter custom Security Features"
                                        id="custom_security_features" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Amenities" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Views:
                                    </label>
                                    @php
                                        $views = [['name' => 'Ocean', 'target' => ''], ['name' => 'City', 'target' => ''], ['name' => 'Mountain', 'target' => ''], ['name' => 'Golf Course', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Pool', 'target' => ''], ['name' => 'Tennis Court', 'target' => ''], ['name' => 'Park', 'target' => ''], ['name' => 'Greenbelt', 'target' => ''], ['name' => 'Garden', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_view']];
                                    @endphp
                                    <select name="views" class="grid-picker" style="justify-content: flex-start;"
                                        required>
                                        <option value=""></option>
                                        @foreach ($views as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_view">
                                    <label class="fw-bold">Custom View</label>
                                    <input type="text" name="custom_view" data-type="custom_view"
                                        placeholder="Enter Custom View" id="custom_view" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Amenities" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="38">
                                <h4>Amenities</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Water features:
                                    </label>
                                    @php
                                        $water_features = [['name' => 'Fountains', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'Waterfall', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_water_features']];
                                    @endphp
                                    <select name="water_features" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($water_features as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_water_features">
                                    <label class="fw-bold">Custom Water Features</label>
                                    <input type="text" name="custom_water_features"
                                        data-type="custom_security_features" placeholder="Enter custom Security Features"
                                        id="custom_security_features" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Amenities" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Home Security Features:
                                    </label>
                                    @php
                                        $home_security_features = [['name' => 'Surveillance cameras', 'target' => ''], ['name' => 'Alarm system', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_home_security_features'], ['name' => '', 'target' => '']];
                                    @endphp
                                    <select name="home_security_features" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($home_security_features as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_home_security_features">
                                    <label class="fw-bold">Custom Home Security Features</label>
                                    <input type="text" name="custom_home_security_features"
                                        data-type="custom_home_security_features"
                                        placeholder="Enter custom Home Security Features" id="custom_security_features"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Amenities" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="39">
                                <h4>Amenities</h4>
                                <div class="form-group ">
                                    <label class="fw-bold">Other Custom Input</label>
                                    <input type="text" name="custom_input_amenities"
                                        data-type="custom_input_amenities" placeholder="Enter Custom Amenities"
                                        id="custom_security_features" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Amenities" required>
                                </div>
                            </div>

                            <div class="wizard-step residential_remove"  data-step="40">
                                <h4>Unique Characteristics:</h4>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Architectural Style:
                                    </label>
                                    @php
                                        $architectural_styles = [
                                            ['name' => 'Bungalow', 'target' => ''],
                                            ['name' => 'Cabin', 'target' => ''],
                                            ['name' => 'Cape Cod', 'target' => ''],
                                            ['name' => 'Coastal', 'target' => ''],
                                            ['name' => 'Colonial', 'target' => ''],
                                            ['name' => 'Contemporary', 'target' => ''],
                                            ['name' => 'Cottage', 'target' => ''],
                                            ['name' => 'Courtyard', 'target' => ''],
                                            ['name' => 'Craftsman', 'target' => ''],
                                            ['name' => 'Custom', 'target' => ''],
                                            ['name' => 'Dutch Provincial', 'target' => ''],
                                            ['name' => 'Elevated', 'target' => ''],
                                            ['name' => 'Florida', 'target' => ''],
                                            ['name' => 'French Provincial', 'target' => ''],
                                            ['name' => 'Historical', 'target' => ''],
                                            ['name' => 'Key West', 'target' => ''],
                                            ['name' => 'Mid-Century Modern', 'target' => ''],
                                            ['name' => 'Other', 'target' => ''],
                                            ['name' => 'Ranch', 'target' => ''],
                                            ['name' => 'Spanish/Mediterranean', 'target' => ''],
                                            ['name' => 'Traditional', 'target' => ''],
                                            ['name' => 'Tudor', 'target' => ''],
                                            ['name' => 'Victorian', 'target' => ''],
                                            ['name' => 'Other- Custom input', 'target' => '.custom_architectural_styles'],
                                        ];
                                    @endphp
                                    <select name="architectural_styles" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($architectural_styles as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_architectural_styles">
                                    <label class="fw-bold">Custom Architectural Style:</label>
                                    <input type="text" name="custom_architectural_styles"
                                        data-type="custom_architectural_styles" id="custom_security_features"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Unique Characteristics:" required>
                                </div>
                            </div>
                            @php
                                $water_frontage = [
                                    ['name' => 'Bay/Harbor', 'target' => ''],
                                    ['name' => 'Bayou', 'target' => ''],
                                    ['name' => 'Beach', 'target' => ''],
                                    ['name' => 'Beach - Access Deeded', 'target' => ''],
                                    ['name' => 'Brackish Water', 'target' => ''],
                                    ['name' => 'Canal - Brackish', 'target' => ''],
                                    ['name' => 'Canal - Freshwater', 'target' => ''],
                                    ['name' => 'Canal - Saltwater', 'target' => ''],
                                    ['name' => 'Creek', 'target' => ''],
                                    ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''],
                                    ['name' => 'Gulf/Ocean', 'target' => ''],
                                    ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
                                    ['name' => 'Intracoastal Waterway', 'target' => ''],
                                    ['name' => 'Lagoon/Estuary', 'target' => ''],
                                    ['name' => 'Lake', 'target' => ''],
                                    ['name' => 'Lake - Chain of Lakes', 'target' => ''],
                                    ['name' => 'Limited Access', 'target' => ''],
                                    ['name' => 'Marina', 'target' => ''],
                                    ['name' => 'Pond', 'target' => ''],
                                    ['name' => 'River', 'target' => ''],
                                    ['name' => 'Other- Custom input', 'target' => '.custom_water_frontage'],
                                ];
                            @endphp
                            <div class="wizard-step residential_remove"  data-step="41">
                                <h4>Unique Characteristics:</h4>
                                <div class="form-group">
                                    <label class="fw-bold">Water Frontage:</label>
                                    <select class="grid-picker" name="water_frontage" id="water_frontage"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value="">Select</option>
                                        @foreach ($water_frontage as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_water_frontage">
                                    <label class="fw-bold">Custom Water Frontage:</label>
                                    <input type="text" name="custom_water_frontage" data-type="custom_water_frontage"
                                        id="custom_water_frontage" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Unique Characteristics:"
                                        required>
                                </div>
                            </div>
                            @php
                                $water_extras = [
                                    ['name' => 'Assigned Boat Slip', 'target' => ''],
                                    ['name' => 'Boat Port', 'target' => ''],
                                    ['name' => 'Boat Ramp - Private', 'target' => ''],
                                    ['name' => 'Boathouse', 'target' => ''],
                                    ['name' => 'Boats - None Allowed', 'target' => ''],
                                    ['name' => 'Bridges - Fixed', 'target' => ''],
                                    ['name' => 'Bridges - No Fixed Bridges', 'target' => ''],
                                    ['name' => 'Davits', 'target' => ''],
                                    ['name' => 'Dock - Composite', 'target' => ''],
                                    ['name' => 'Dock - Concrete', 'target' => ''],
                                    ['name' => 'Dock - Covered', 'target' => ''],
                                    ['name' => 'Dock - Open', 'target' => ''],
                                    ['name' => 'Dock - Slip 1st Come', 'target' => ''],
                                    ['name' => 'Dock - Slip Deeded Off-Site', 'target' => ''],
                                    ['name' => 'Dock - Slip Deeded On-Site', 'target' => ''],
                                    ['name' => 'Dock - Wood', 'target' => ''],
                                    ['name' => 'Dock w/Electric', 'target' => ''],
                                    ['name' => 'Dock w/o Electric', 'target' => ''],
                                    ['name' => 'Dock w/o Water Supply', 'target' => ''],
                                    ['name' => 'Dock w/Water Supply', 'target' => ''],
                                    ['name' => 'Fishing Pier', 'target' => ''],
                                    ['name' => 'Lift', 'target' => ''],
                                    ['name' => 'Lift - Covered', 'target' => ''],
                                    ['name' => 'Lock', 'target' => ''],
                                    ['name' => 'Minimum Wake Zone', 'target' => ''],
                                    ['name' => 'No Wake Zone', 'target' => ''],
                                    ['name' => 'Powerboats – None Allowed', 'target' => ''],
                                    ['name' => 'Private Lake Dues Required', 'target' => ''],
                                    ['name' => 'Riprap', 'target' => ''],
                                    ['name' => 'Sailboat Water', 'target' => ''],
                                    ['name' => 'Seawall - Concrete', 'target' => ''],
                                    ['name' => 'Seawall - Other', 'target' => ''],
                                    ['name' => 'Skiing Allowed', 'target' => ''],
                                    ['name' => 'Other- Custom input', 'target' => '.custom_water_extra'],
                                ];
                            @endphp
                            <div class="wizard-step residential_remove"  data-step="42">
                                <h4>Unique Characteristics:</h4>
                                <div class="form-group">
                                    <label class="fw-bold">Water Extras:</label>
                                    <select class="grid-picker" name="water_extras" id="water_extras"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($water_extras as $water_extra)
                                            <option value="{{ $water_extra['name'] }}"
                                                data-target="{{ $water_extra['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $water_extra['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_water_extra">
                                    <label class="fw-bold">Custom Water Extras:</label>
                                    <input type="text" name="custom_water_extra" data-type="custom_water_extra"
                                        id="custom_water_extra" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Unique Characteristics:"
                                        required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="43">
                                <h4>Unique Characteristics:</h4>
                                @php
                                    $privacy_features = [['name' => 'Fenced yard', 'target' => ''], ['name' => 'Secluded location', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_privacy_features']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Privacy Features</label>
                                    <select class="grid-picker" name="privacy_features" id="water_extras"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($privacy_features as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_privacy_features">
                                    <label class="fw-bold">Custom Privacy Features:</label>
                                    <input type="text" name="custom_privacy_features"
                                        data-type="custom_privacy_features" id="custom_privacy_features"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Unique Characteristics:" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="44">
                                <h4>Unique Characteristics:</h4>
                                @php
                                    $acessiblity_features = [['name' => 'Ramps', 'target' => ''], ['name' => 'Elevators', 'target' => ''], ['name' => 'Accessible Doors', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_acessiblity_features']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Accessibility Features:</label>
                                    <select class="grid-picker" name="acessiblity_features" id="water_extras"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($acessiblity_features as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_acessiblity_features">
                                    <label class="fw-bold">Custom Accessibility Features:</label>
                                    <input type="text" name="custom_acessiblity_features"
                                        data-type="custom_acessiblity_features" id="custom_acessiblity_features"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Unique Characteristics:" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="45">
                                <h4>Unique Characteristics:</h4>
                                @php
                                    $unique_design_elements = [['name' => 'Vaulted ceiling', 'target' => ''], ['name' => 'Skylights', 'target' => ''], ['name' => 'Custom finishes', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_unique_design']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Unique design elements:</label>
                                    <select class="grid-picker" name="unique_design_elements" id="water_extras"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($unique_design_elements as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_unique_design">
                                    <label class="fw-bold">Custom Unique Design:</label>
                                    <input type="text" name="custom_unique_design" data-type="custom_unique_design"
                                        id="custom_unique_design" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Unique Characteristics:" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="46">
                                <h4>Unique Characteristics:</h4>
                                @php
                                    $flloor_plan = [['name' => 'Open', 'target' => ''], ['name' => 'Ranch', 'target' => ''], ['name' => 'Traditional', 'target' => ''], ['name' => 'Split-Level', 'target' => ''], ['name' => 'Bi-Level', 'target' => ''], ['name' => 'Duplex/Triplex ', 'target' => ''], ['name' => 'Loft ', 'target' => ''], ['name' => 'Tiny House ', 'target' => '']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Floor Plan:</label>
                                    <select class="grid-picker" name="flloor_plan" id="water_extras"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($flloor_plan as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group ">
                                    <label class="fw-bold">Historical architectural details</label>
                                    <input type="text" name="historical_architectural_details"
                                        data-type="historical_architectural_details"
                                        id="historical_architectural_details" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Unique Characteristics:" required>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove"  data-step="47">
                                @php
                                    $features = [
                                        ['name' => 'Spa', 'target' => ''],
                                        ['name' => 'Covered Carport', 'target' => ''],
                                        ['name' => 'Fireplace', 'target' => ''],
                                        ['name' => 'Fitness center or gym', 'target' => ''],
                                        ['name' => 'Clubhouse', 'target' => ''],
                                        ['name' => 'Community facilities', 'target' => ''],
                                        ['name' => 'Tennis court', 'target' => ''],
                                        ['name' => 'Playground', 'target' => ''],
                                        ['name' => 'Park access', 'target' => ''],
                                        ['name' => 'Pool house', 'target' => ''],
                                        ['name' => 'Sauna', 'target' => ''],
                                        ['name' => 'Steam room', 'target' => ''],
                                        ['name' => 'Home theater', 'target' => ''],
                                        ['name' => 'Home automation system', 'target' => ''],
                                        ['name' => 'Central vacuum system', 'target' => ''],
                                        ['name' => 'Outdoor kitchen', 'target' => ''],
                                        ['name' => 'Historical significance or heritage status', 'target' => ''],
                                        ['name' => 'Double Lot', 'target' => ''],
                                        ['name' => 'Home office', 'target' => ''],
                                        ['name' => 'Wine cellar', 'target' => ''],
                                        ['name' => 'Basement', 'target' => ''],
                                        ['name' => 'In-law suite or guest house', 'target' => ''],
                                        ['name' => 'Historical architectural details', 'target' => ''],
                                        ['name' => 'Wine-producing vineyard or winery', 'target' => ''],
                                        ['name' => 'Equestrian facilities (barn, riding arena, pasture)', 'target' => ''],
                                        ['name' => 'Backyard oasis or resort-style landscaping', 'target' => ''],
                                        ['name' => 'Guest quarters or separate living space', 'target' => ''],
                                        ['name' => 'Art studio or creative space', 'target' => ''],
                                        ['name' => 'Dual primary suites', 'target' => ''],
                                        ['name' => 'Built-in sound system', 'target' => ''],
                                        ['name' => 'Home warranty or transferable warranties', 'target' => ''],
                                        ['name' => 'Other Custom-Input', 'target' => '.custom_features'],
                                    ];

                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Features:</label>
                                    <select class="grid-picker" name="features[]" id="features"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($features as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_features d-none">
                                    <label class="fw-bold">Other- Custom Input </label>
                                    <input type="text" name="custom_unique_characteristics"
                                        data-type="custom_unique_characteristics" id="custom_unique_characteristics"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Unique Characteristics:" required>
                                </div>
                            </div>

                            <div class="wizard-step commercial_remove"  data-step="48">
                                @php
                                    $interior_amenities = [['name' => 'Modern and well-maintained office spaces or retail areas', 'target' => ''], ['name' => 'Upgraded kitchen or break room facilities', 'target' => ''], ['name' => 'Renovated restrooms with high-quality fixtures', 'target' => ''], ['name' => 'Well-designed and functional floor plan for efficient use of space', 'target' => ''], ['name' => 'Adequate storage or inventory areas', 'target' => ''], ['name' => 'Custom built-ins or shelving for optimal organization', 'target' => ''], ['name' => 'Dedicated conference rooms or meeting spaces', 'target' => ''], ['name' => 'High-speed internet connectivity or fiber optic infrastructure', 'target' => ''], ['name' => 'Smart technology integration for enhanced security or automation', 'target' => ''], ['name' => 'Other- Custom Input', 'target' => '.custom_interior_amenities']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Interior Amenities: </label>
                                    <select class="grid-picker" name="interior_amenities" id="water_extras"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($interior_amenities as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_interior_amenities">
                                    <label class="fw-bold">Custom Interior Amenities</label>
                                    <input type="text" name="custom_interior_amenities"
                                        data-type="custom_interior_amenities" id="custom_interior_amenities"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                                </div>
                            </div>
                            <div class="wizard-step commercial_remove"  data-step="49">
                                @php
                                    $exterior_amenities = [['name' => 'Ample parking spaces for employees and visitors', 'target' => ''], ['name' => 'Covered parking or designated loading/unloading areas', 'target' => ''], ['name' => 'Renovated restrooms with high-quality fixtures', 'target' => ''], ['name' => 'Eye-catching signage opportunities for increased visibility', 'target' => ''], ['name' => 'Landscaped outdoor areas or courtyards for relaxation or gatherings', 'target' => ''], ['name' => 'Outdoor seating or patio spaces for dining or events', 'target' => ''], ['name' => 'Accessibility features such as ramps or elevators', 'target' => ''], ['name' => 'Security measures like surveillance cameras or gated entry', 'target' => ''], ['name' => 'Other- Custom Input', 'target' => '.custom_exterior_amenities']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Exterior Amenities: </label>
                                    <select class="grid-picker" name="exterior_amenities" id="exterior_amenities"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($exterior_amenities as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_exterior_amenities" >
                                    <label class="fw-bold">Custom Exterior Amenities:</label>
                                    <input type="text" name="custom_exterior_amenities"
                                        data-type="custom_exterior_amenities" id="custom_exterior_amenities"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Unique Characteristics:" required>
                                </div>
                            </div>


                            <div class="wizard-step commercial_remove"  data-step="50">
                                @php
                                    $structural_and_mechanical_upgrades = [['name' => 'Updated roofing and well-maintained building exteriors', 'target' => ''], ['name' => 'Energy-efficient windows and doors for improved insulation', 'target' => ''], ['name' => 'Upgraded HVAC systems for optimal climate control', 'target' => ''], ['name' => 'Advanced lighting systems for energy efficiency and cost savings', 'target' => ''], ['name' => 'Updated electrical systems to meet current standards', 'target' => ''], ['name' => 'Plumbing improvements or water-saving fixtures', 'target' => ''], ['name' => 'Other- Custom Input', 'target' => '']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold"> Structural and Mechanical Upgrades:</label>
                                    <select class="grid-picker" name="structural_and_mechanical_upgrades"
                                        id="structural_and_mechanical_upgrades" style="justify-content: flex-start;"
                                        required>
                                        <option value="">Select</option>
                                        @foreach ($structural_and_mechanical_upgrades as $yes_or_no)
                                            @php
                                                if ($yes_or_no['name'] == 'Other- Custom Input') {
                                                    $target = '.custom_structural_and_mechanical_upgrades';
                                                } else {
                                                    $target = '';
                                                }
                                            @endphp
                                            <option value="{{ $yes_or_no['name'] }}"
                                                data-target="{{ $target }}" class="card flex-row fw-bold"
                                                style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $yes_or_no['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_structural_and_mechanical_upgrades ">
                                    <label class="fw-bold">Custom Structural and Mechanical Upgrades:</label>
                                    <input type="text" name="custom_structural_and_mechanical_upgrades"
                                        data-type="custom_structural_and_mechanical_upgrades"
                                        id="custom_structural_and_mechanical_upgrades" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" required>
                                </div>
                            </div>
                            <div class="wizard-step commercial_remove"  data-step="51">
                                @php
                                    $additional_features = [['name' => 'Dedicated server or IT rooms for data center facilities', 'target' => ''], ['name' => 'Soundproofing enhancements for noise reduction', 'target' => ''], ['name' => 'LEED certification or sustainable building features', 'target' => ''], ['name' => 'Specialized facilities such as laboratories, studios, or showrooms', 'target' => ''], ['name' => 'On-site amenities like fitness centers, cafeterias, or lounges', 'target' => ''], ['name' => 'Secure storage or warehouse spaces', 'target' => ''], ['name' => 'Advanced security systems for protection against theft or vandalism', 'target' => ''], ['name' => 'Flexible or customizable spaces to accommodate various business needs. ', 'target' => ''], ['name' => 'Other- Custom Input', 'target' => '']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold"> Additional Features:</label>
                                    <select class="grid-picker" name="additional_features" id="additional_features"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($additional_features as $yes_or_no)
                                            @php
                                                if ($yes_or_no['name'] == 'Other- Custom Input') {
                                                    $target = '.custom_additional_features';
                                                } else {
                                                    $target = '';
                                                }
                                            @endphp
                                            <option value="{{ $yes_or_no['name'] }}"
                                                data-target="{{ $target }}" class="card flex-row fw-bold"
                                                style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $yes_or_no['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_additional_features">
                                    <label class="fw-bold">Custom Additional Features:</label>
                                    <input type="text" name="custom_additional_features"
                                        data-type="custom_additional_features" id="custom_additional_features"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                                </div>
                            </div>
                            <div class="wizard-step commercial_remove"  data-step="52">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Views:
                                    </label>
                                    @php
                                        $views = [['name' => 'Ocean', 'target' => ''], ['name' => 'City', 'target' => ''], ['name' => 'Mountain', 'target' => ''], ['name' => 'Golf Course', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Pool', 'target' => ''], ['name' => 'Tennis Court', 'target' => ''], ['name' => 'Park', 'target' => ''], ['name' => 'Greenbelt', 'target' => ''], ['name' => 'Garden', 'target' => ''], ['name' => 'Other- Custom input', 'target' => '.custom_view']];
                                    @endphp
                                    <select name="views" class="grid-picker" style="justify-content: flex-start;"
                                        required>
                                        <option value=""></option>
                                        @foreach ($views as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row fw-bold" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_view">
                                    <label class="fw-bold">Custom View</label>
                                    <input type="text" name="custom_view" data-type="custom_view"
                                        placeholder="Enter Custom View" id="custom_view" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Amenities" required>
                                </div>
                            </div>
                            <div class="wizard-step commercial_remove"  data-step="53">
                                @php
                                    $road_frontages = [['name' => 'Access Road', 'target' => ''], ['name' => 'Alley', 'target' => ''], ['name' => 'Business District', 'target' => ''], ['name' => 'City Street', 'target' => ''], ['name' => 'County Road ', 'target' => ''], ['name' => 'Divided Highway', 'target' => ''], ['name' => 'Easement', 'target' => ''], ['name' => 'Highway', 'target' => ''], ['name' => 'Interchange', 'target' => ''], ['name' => 'Interstate', 'target' => ''], ['name' => 'Main Thoroughfare', 'target' => ''], ['name' => 'Private Road', 'target' => ''], ['name' => 'Rail', 'target' => ''], ['name' => 'State Road', 'target' => ''], ['name' => 'Turn Lanes', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Other- Custom Input', 'target' => '.custom_road_frontage']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Road Frontage:</label>
                                    <select class="grid-picker" name="road_frontage" id="road_frontage"
                                        style="justify-content: flex-start;" onclick="" required>
                                        <option value="">Select</option>
                                        @foreach ($road_frontages as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_road_frontage">
                                    <label class="fw-bold"> Custom Road Frontage</label>
                                    <input type="text" name="custom_road_frontage" data-type="custom_road_frontage"
                                        placeholder="Enter Custom Road Frontage" id="custom_road_frontage"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Amenities" required>
                                </div>
                            </div>
                            <div class="wizard-step commercial_remove"  data-step="54">
                                @php
                                    $adjoining_property = [['name' => 'Airport', 'target' => ''], ['name' => 'Church', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Hotel/Motel', 'target' => ''], ['name' => 'Industrial', 'target' => ''], ['name' => 'Multi-Family', 'target' => ''], ['name' => 'Natural State', 'target' => ''], ['name' => 'Professional Office', 'target' => ''], ['name' => 'Railroad', 'target' => ''], ['name' => 'Residential', 'target' => ''], ['name' => 'School', 'target' => ''], ['name' => 'Undeveloped', 'target' => ''], ['name' => 'Vacant', 'target' => ''], ['name' => 'Waterway', 'target' => ''], ['name' => 'Other- Custom Input', 'target' => '.custom_adjoining_property']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Adjoining Property:</label>
                                    <select class="grid-picker" name="adjoining_property" id="adjoining_property"
                                        style="justify-content: flex-start;" onclick="" required>
                                        <option value="">Select</option>
                                        @foreach ($adjoining_property as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_adjoining_property">
                                    <label class="fw-bold">Custom Adjoining Property:</label>
                                    <input type="text" name="custom_adjoining_property"
                                        data-type="custom_adjoining_property" id="custom_adjoining_property"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Amenities" required>
                                </div>
                            </div>
                            <div class="wizard-step commercial_remove"  data-step="55">
                                @php
                                    $lot_features = [
                                        ['name' => 'Brownfield', 'target' => ''],
                                        ['name' => 'Buildable', 'target' => ''],
                                        ['name' => 'Central Business District', 'target' => ''],
                                        ['name' => 'Cleared', 'target' => ''],
                                        ['name' => 'CoastalConstruction Control Line', 'target' => ''],
                                        ['name' => 'Compact Soil', 'target' => ''],
                                        ['name' => 'Conservation Area', 'target' => ''],
                                        ['name' => 'Corner Lot', 'target' => ''],
                                        ['name' => 'Cul-De-Sac', 'target' => ''],
                                        ['name' => 'Curb and Gutters', 'target' => ''],
                                        ['name' => 'Demucked', 'target' => ''],
                                        ['name' => 'Drainage Canal', 'target' => ''],
                                        ['name' => 'Environmental Restricted Area', 'target' => ''],
                                        ['name' => 'Farm', 'target' => ''],
                                        ['name' => 'Fire Hydrant', 'target' => ''],
                                        ['name' => 'Fish Breeding Ponds', 'target' => ''],
                                        ['name' => 'Flag Lot', 'target' => ''],
                                        ['name' => 'Flood Plain', 'target' => ''],
                                        ['name' => 'Greenbelt', 'target' => ''],
                                        ['name' => 'Historic District', 'target' => ''],
                                        ['name' => 'Hunting Lease', 'target' => ''],
                                        ['name' => ' In City Limits', 'target' => ''],
                                        ['name' => 'In County', 'target' => ''],
                                        ['name' => 'Industrial Park', 'target' => ''],
                                        ['name' => 'Interior Lot', 'target' => ''],
                                        ['name' => 'Irregular Lot', 'target' => ''],
                                        ['name' => 'Key Lot', 'target' => ''],
                                        ['name' => 'Landscaped', 'target' => ''],
                                        ['name' => 'Level/Flat', 'target' => ''],
                                        ['name' => 'May Need To be Filled', 'target' => ''],
                                        ['name' => 'Mountainous', 'target' => ''],
                                        ['name' => 'Near Golf Course', 'target' => ''],
                                        ['name' => 'Near Marina', 'target' => ''],
                                        ['name' => 'Near Public Transit', 'target' => ''],
                                        ['name' => 'Near Railroad Siding', 'target' => ''],
                                        ['name' => 'On Golf Course', 'target' => ''],
                                        ['name' => 'Other', 'target' => ''],
                                        ['name' => 'Out Parcel', 'target' => ''],
                                        ['name' => 'Oversized Lot', 'target' => ''],
                                        ['name' => 'Pasture/Agriculture', 'target' => ''],
                                        ['name' => 'Private', 'target' => ''],
                                        ['name' => 'Railroad', 'target' => ''],
                                        ['name' => 'Reclaimed Land', 'target' => ''],
                                        ['name' => 'Retention Pond', 'target' => ''],
                                        ['name' => 'Retention Areas', 'target' => ''],
                                        ['name' => 'Rolling Slope', 'target' => ''],
                                        ['name' => 'Room For Pool', 'target' => ''],
                                        ['name' => 'Rural', 'target' => ''],
                                        ['name' => 'Seaport', 'target' => ''],
                                        ['name' => 'Sidewalks', 'target' => ''],
                                        ['name' => 'Sloped', 'target' => ''],
                                        ['name' => 'Special Taxing District ', 'target' => ''],
                                        ['name' => 'Stocked Fishing Ponds   ', 'target' => ''],
                                        ['name' => 'Street Brick', 'target' => ''],
                                        ['name' => 'Street Dead-End', 'target' => ''],
                                        ['name' => 'Street Lights', 'target' => ''],
                                        ['name' => 'Street One Way', 'target' => ''],
                                        ['name' => 'Street Paved', 'target' => ''],
                                        ['name' => 'Street Private', 'target' => ''],
                                        ['name' => 'Street Unpaved', 'target' => ''],
                                        ['name' => 'Suburb', 'target' => ''],
                                        ['name' => 'Tip Lot', 'target' => ''],
                                        ['name' => 'Turn Around', 'target' => ''],
                                        ['name' => 'Unincorporated', 'target' => ''],
                                        ['name' => 'Urban', 'target' => ''],
                                        ['name' => 'Wetlands', 'target' => ''],
                                        ['name' => 'Wildlife Sanctuary', 'target' => ''],
                                        ['name' => 'Wooded', 'target' => ''],
                                        ['name' => 'Zero Lot Line', 'target' => ''],
                                        ['name' => 'Zoned for Horses', 'target' => ''],
                                        ['name' => 'Other- Custom Input', 'target' => '.custom_lot_features'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Lot Features:</label>
                                    <select class="grid-picker" name="lot_features" id="lot_features"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($lot_features as $lot_feature)
                                            <option value="{{ $lot_feature['name'] }}"
                                                data-target="{{ $lot_feature['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(25% - 10px);"
                                                data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                                {{ $lot_feature['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_lot_features">
                                    <label class="fw-bold">Custom Lot Features:</label>
                                    <input type="text" name="custom_lot_features" data-type="custom_lot_features"
                                        id="custom_lot_features" class="form-control has-icon"
                                        data-icon="fa-regular fa-circle-check" data-msg-required="Amenities" required>
                                </div>
                            </div>
                            <div class="wizard-step commercial_remove"  data-step="56">
                                @php
                                    $building_features = [
                                        ['name' => 'Clear Span', 'target' => ''],
                                        ['name' => 'Columns', 'target' => ''],
                                        ['name' => 'Common Lighting', 'target' => ''],
                                        ['name' => 'Drive-Through', 'target' => ''],
                                        ['name' => 'Dumpsters', 'target' => ''],
                                        ['name' => 'Elevator-None', 'target' => ''],
                                        ['name' => 'Elevator', 'target' => ''],
                                        ['name' => 'Extra Storage', 'target' => ''],
                                        ['name' => 'Fencing', 'target' => ''],
                                        ['name' => 'Fiber Optic', 'target' => ''],
                                        ['name' => 'Freight Elevator', 'target' => ''],
                                        ['name' => 'Furnished', 'target' => ''],
                                        ['name' => 'High Bays', 'target' => ''],
                                        ['name' => 'Janitorial Services', 'target' => ''],
                                        ['name' => 'Kitchen Facility', 'target' => ''],
                                        ['name' => 'Lit Sign on Site', 'target' => ''],
                                        ['name' => 'Loading Dock', 'target' => ''],
                                        ['name' => 'Loft', 'target' => ''],
                                        ['name' => 'Medical Disposal', 'target' => ''],
                                        ['name' => 'On Site Shower', 'target' => ''],
                                        ['name' => 'Other', 'target' => ''],
                                        ['name' => 'Outside Storage', 'target' => ''],
                                        ['name' => 'Overhead Doors', 'target' => ''],
                                        ['name' => 'Ramp', 'target' => ''],
                                        ['name' => 'Reception', 'target' => ''],
                                        ['name' => 'Seating', 'target' => ''],
                                        ['name' => 'Service Stations', 'target' => ''],
                                        ['name' => 'Solid Surface Counter', 'target' => ''],
                                        ['name' => 'Stone Counter', 'target' => ''],
                                        ['name' => 'Trash Removal', 'target' => ''],
                                        ['name' => 'Truck Doors', 'target' => ''],
                                        ['name' => 'Truck Well', 'target' => ''],
                                        ['name' => 'Waiting Room', 'target' => ''],
                                        ['name' => 'Other- Custom Input', 'target' => '.custom_building_features'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Building Features:</label>
                                    <select class="grid-picker" name="building_features" id="building_feature"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($building_features as $building_feature)
                                            <option value="{{ $building_feature['name'] }}"
                                                data-target="{{ $building_feature['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $building_feature['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_building_features">
                                    <label class="fw-bold">Custom Building Features</label>
                                    <input type="text" name="custom_building_features"
                                        data-type="custom_building_features" id="custom_building_features"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Amenities" required>
                                </div>
                            </div>


                            <div class="wizard-step commercial_remove"  data-step="57">
                                @php
                                    $parking_features = [['name' => '1 to 5 Spaces', 'target' => ''], ['name' => '6 to 12 Spaces', 'target' => ''], ['name' => '13 to 18 Spaces', 'target' => ''], ['name' => '19 to 30 Spaces', 'target' => ''], ['name' => 'Airplane Hangar', 'target' => ''], ['name' => 'Common', 'target' => ''], ['name' => 'Curb Parking', 'target' => ''], ['name' => 'Deeded', 'target' => ''], ['name' => 'Electric Vehicle Charging Station(s)', 'target' => ''], ['name' => 'Ground Level', 'target' => ''], ['name' => 'Lighted', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Over 30 Spaces', 'target' => ''], ['name' => 'Secured', 'target' => ''], ['name' => 'Under Building', 'target' => ''], ['name' => 'Underground', 'target' => ''], ['name' => 'Valet', 'target' => ''], ['name' => 'Other- Custom Input', 'target' => '.custom_parking_features']];
                                @endphp

                                <div class="form-group">
                                    <label class="fw-bold">Garage/Parking Features:</label>
                                    <select class="grid-picker" name="parking_feature" id="parking_feature"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($parking_features as $parking_feature)
                                            <option value="{{ $parking_feature['name'] }}"
                                                data-target="{{ $parking_feature['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $parking_feature['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_parking_features">
                                    <label class="fw-bold">Custom Parking Features</label>
                                    <input type="text" name="custom_parking_features"
                                        data-type="custom_parking_features" id="custom_parking_features"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        data-msg-required="Amenities" required>
                                </div>
                            </div>

                            <div class="wizard-step"  data-step="58">
                                <h4>If you have requested a Comparative Market Analysis, please provide pictures and/or a
                                    video of the property.</h4>
                                <div class="form-group ">
                                    <label class="fw-bold">Upload Pictures of the Landlord's Property:</label>
                                    <input type="file" name="property_picture" id="property_picture"
                                        placeholder="" class="form-control has-icon" data-icon="fa-solid fa-link">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Link to Pictures of the Landlord’s Property:</label>
                                    <input type="url" name="link_picture" id="three_d_tour" placeholder=""
                                        class="form-control has-icon" data-icon="fa-solid fa-link">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Upload a Video of the Landlord's Property:</label>
                                    <input type="file" name="property_video" id="property_video" placeholder=""
                                        class="form-control has-icon" data-icon="fa-solid fa-link">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Link to Video of the Landlord’s Property:</label>
                                    <input type="url" name="link_video" id="three_d_tour" placeholder=""
                                        class="form-control has-icon" data-icon="fa-solid fa-link">
                                </div>
                            </div> --}}
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
