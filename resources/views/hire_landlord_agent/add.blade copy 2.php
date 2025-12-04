@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.css">

    <style>
        .choices__list {
            z-index: 999;
        }

        .bedroom .option-text {
            padding: 0 !important;
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

        .address_confidential {
            color: #6666668c;
            font-family: emoji;

        }

        .box {
            display: block;
            width: 200px;
            height: 160px;
            background-color: white;
            border-radius: 5px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            /* overflow: hidden; */
            position: relative;
        }

        .js--image-preview {
            width: 200px;
            height: 160px;
        }

        .upload {
            margin-bottom: 100px;
        }

        .upload-options {
            position: relative;
            /* height: 65px; */
            background-color: $base-color;
            cursor: pointer;
            overflow: hidden;
            text-align: center;
            transition: background-color ease-in-out 150ms;
            color: red;
            width: 100%;
            border: 1px solid #dedddd;
            border-radius: 0px 0px 5px 5px;

            &:hover {
                background-color: lighten($base-color, 10%);
            }

            & input {
                width: 0.1px;
                height: 0.1px;
                opacity: 0;
                overflow: hidden;
                position: absolute;
                z-index: -1;
            }

            & label {
                display: flex;
                align-items: center;
                width: 100%;
                height: 100%;
                font-weight: 400;
                text-overflow: ellipsis;
                white-space: nowrap;
                cursor: pointer;
                overflow: hidden;

                &::after {
                    content: "+";
                    font-family: "Material Icons";
                    z-index: 0;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    width: 198px;
                    height: 50px;
                    font-size: 28px;
                    color: #e6e6e6;
                }

                & span {
                    display: inline-block;
                    width: 50%;
                    height: 100%;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    overflow: hidden;
                    vertical-align: middle;
                    text-align: center;

                    &:hover i.material-icons {
                        color: lightgray;
                    }
                }
            }
        }

        .js--image-preview {
            height: 100%;
            width: 100%;
            /* position: relative; */
            overflow: hidden;
            background-image: url('/images/image.png');
            background-color: white;
            /* background-position: center center; */
            background-repeat: no-repeat;
            background-size: cover;

            &.js--no-default::after {
                display: none;
            }

            &:nth-child(2) {
                background-image: url("http://bastianandre.at/giphy.gif");
            }
        }

        i.material-icons {
            transition: color 100ms ease-in-out;
            font-size: 2.25em;
            line-height: 55px;
            color: white;
            display: block;
        }

        .drop {
            display: block;
            position: absolute;
            background: transparentize($base-color, 0.8);
            border-radius: 100%;
            transform: scale(0);
        }

        .animate {
            animation: ripple 0.4s linear;
        }

        @keyframes ripple {
            100% {
                opacity: 0;
                transform: scale(2.5);
            }
        }

        .video {
            height: 160px;
            width: 200px;
            position: relative;
            /* overflow: hidden; */

            background-color: white;
            /* background-position: center center; */
            background-repeat: no-repeat;
            background-size: cover;
            margin-bottom: 60px;

        }

        .bgImg {
            background-image: url('/images/play.png');
        }

        span.upload-button {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 198px;
            height: 50px;
            font-size: 28px;
            color: #e6e6e6;
        }

        .videoBox {
            width: 200px;
            border-radius: 5px;

        }

        .videoDiv {
            margin-top: -60px;
        }

        label.fileuploader-btn {
            /* position: absolute; */
            top: 100%;
            border-radius: 0px 0px 5px 5px;
            border: 1px solid #e2e2e2;
        }

        /* Style to hide the custom_occupant_type by default */
    </style>
@endpush
@section('content')
    {{-- @if ($errors->any())
<h4>{{$errors->first()}}</h4>
@endif --}}
    @php
        $yes_or_nos = [
            ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
        ];
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
                                Hire a Listing Agent
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
                                    address, along with the city, county, and state, pertaining to the real estate asset
                                    that the
                                    landlord intends to place on the market:</h4>
                                <div class="form-group">
                                    <label class="fw-bold">Address:</label>
                                    <input type="text" name="address" class="form-control search_places has-icon"
                                        data-type="address" required value="{{ old('address') }}"
                                        data-icon="fa-solid fa-location-dot" placeholder="" />
                                    @if ($errors->has('address'))
                                        <div class="small error">{{ $errors->first('address') }}</div>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">Unit Number:</label>
                                    <input type="text" name="unit_num" data-type="cities" id="city"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                                        placeholder="">
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">City: </label>
                                    <input type="text" name="city" data-type="cities" id="city"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                                        placeholder="" required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">County: </label>
                                    <input type="text" name="county" data-type="counties" id="county"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                                        placeholder="" required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">State:</label>
                                    <input type="text" name="state" data-type="states" id="state"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
                                        placeholder="" required>
                                </div>
                                <small class="mt-1 fs-5">Note: The listing's address remains confidential until a seller
                                    employs an agent.
                                </small>
                            </div>
                            {{-- Slide 2 --}}
                            {{-- Slide 3 --}}
                            <div class="wizard-step" data-step="3">
                                <div class="form-group">
                                    <label for="address" class="fw-bold">Listing Date:</label>
                                    <input type="date" name="listing_date" id="listing_date"
                                        class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                        min="{{ date('Y-m-d') }}" required>
                                </div>

                                <div class="form-group">
                                    <label for="address" class="fw-bold">Expiration Date:</label>
                                    <input type="date" name="expiration_date" id="expiration_date"
                                        class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                        min="{{ date('Y-m-d') }}" required>
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
                                            $auction_types = [
                                                [
                                                    'name' => 'Auction Listing',
                                                    'icon' => '<i class="fa-regular fa-clock"></i>',
                                                    'target' => '.auction_length_cover',
                                                ],
                                                [
                                                    'name' => 'Traditional Listing',
                                                    'icon' => '<i class="fa-solid fa-clipboard-list"></i>',
                                                    'target' => '',
                                                ],
                                            ];
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
                                            $auction_lengths = [
                                                ['name' => '1 Day', 'class' => 'normal-length'],
                                                ['name' => '3 Days', 'class' => 'normal-length'],
                                                ['name' => '5 Days', 'class' => 'normal-length'],
                                                ['name' => '7 Days', 'class' => 'normal-length'],
                                                ['name' => '10 Days', 'class' => 'normal-length'],
                                                ['name' => '14 Days', 'class' => 'normal-length'],
                                                ['name' => '21 Days', 'class' => 'normal-length'],
                                                ['name' => '30 Days', 'class' => 'normal-length'],
                                                ['name' => '45 Days', 'class' => 'normal-length'],
                                                ['name' => '60 Days', 'class' => 'normal-length'],
                                                ['name' => '75 Days', 'class' => 'normal-length'],
                                                ['name' => '90 Days', 'class' => 'normal-length'],
                                                ['name' => 'No time limit', 'class' => 'traditional-length'],
                                            ];
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
                            <div class="wizard-step" data-step="5">
                                <div class="form-group">
                                    <label class="fw-bold">Title of Listing:</label>
                                    <input type="text" class="form-control has-icon" name="titleListing"
                                        id="" required data-icon="fa-solid fa-hotel" />
                                </div>
                            </div>
                            <div class="wizard-step" data-step="6">
                                @php
                                    $property_types = [
                                        ['name' => 'Residential Property'],
                                        ['name' => 'Commercial Property'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Property Style:</label>
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
                                                ['name' => '1/2 Duplex', 'class' => 'residential-length'],
                                                ['name' => '1/3 Triplex', 'class' => 'residential-length'],
                                                ['name' => '1/4 Quadplex', 'class' => 'residential-length'],
                                                ['name' => 'Apartment', 'class' => 'residential-length'],
                                                ['name' => 'Condo-Hotel', 'class' => 'residential-length'],
                                                ['name' => 'Condominium', 'class' => 'residential-length'],
                                                ['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                                                ['name' => 'Farm', 'class' => 'residential-length'],
                                                ['name' => 'Garage Condo', 'class' => 'residential-length'],
                                                ['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
                                                ['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
                                                ['name' => 'Modular Home', 'class' => 'residential-length'],
                                                ['name' => 'Single Family Residence', 'class' => 'residential-length'],
                                                ['name' => 'Townhouse', 'class' => 'residential-length'],
                                                ['name' => 'Villa', 'class' => 'residential-length'],
                                                ['name' => 'Unimproved Land', 'class' => 'residential-length'],

                                                ['name' => 'Agriculture', 'class' => 'commercial-length'],
                                                ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                                                ['name' => 'Business', 'class' => 'commercial-length'],
                                                ['name' => 'Five or More', 'class' => 'commercial-length'],
                                                ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                                                ['name' => 'Industrial', 'class' => 'commercial-length'],
                                                ['name' => 'Mixed Use', 'class' => 'commercial-length'],
                                                ['name' => 'Office', 'class' => 'commercial-length'],
                                                ['name' => 'Restaurant', 'class' => 'commercial-length'],
                                                ['name' => 'Retail', 'class' => 'commercial-length'],
                                                ['name' => 'Warehouse', 'class' => 'commercial-length'],


                                                // [
                                                //     'name' => 'Five or More (Residential units)',
                                                //     'class' => 'income-length',
                                                // ],
                                                // ['name' => 'Duplex', 'class' => 'income-length'],
                                                // ['name' => 'Triplex', 'class' => 'income-length'],
                                                // ['name' => 'Quadplex', 'class' => 'income-length'],
                                            ];
                                        @endphp
                                        <select name="property_items[]" id="property_items"
                                            class="property_items grid-picker" style="justify-content: flex-start;"
                                            multiple required>
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
                            <div class="wizard-step" data-step="7">
                                <span class="resFields">
                                    @php
                                        $lease_prop_res = [
                                            ['name' => 'Entire Property', 'target' => ''],
                                            ['name' => 'Single Room', 'target' => '.singleRoomRes'],
                                        ];
                                    @endphp
                                    <div class="form-group">
                                        <label class="fw-bold">Is the landlord looking to lease their entire property or a single room? 
                                        </label>
                                        <select class="grid-picker" name="leaseRoom" id="prop_condition"
                                            style="justify-content: flex-start;" required>
                                            <option value="">Select</option>
                                            @foreach ($lease_prop_res as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'
                                                    style="width:calc(50% - 10px);">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-group singleRoomRes d-none">
                                            <div class="form-group input-cover">
                                                <span class="commercialFields">
                                                    <div class="form-group">
                                                        <label class="fw-bold">Are there any shared amenities, such as
                                                            conference rooms or parking
                                                            facilities?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="sharedAmenities"
                                                            id="sharedAmenities" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">Are there specific hours of operation for
                                                            the
                                                            building, and is 24/7
                                                            access available?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="hoursOfOperation"
                                                            id="hoursOfOperation" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">Are there specific zoning restrictions or
                                                            permitted uses for the
                                                            space?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle"
                                                            name="zoningRestrictions" id="zoningRestrictions" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">How are maintenance issues and repairs
                                                            handled
                                                            for the commercial
                                                            space?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle"
                                                            name="maintenanceHandling" id="maintenanceHandling"
                                                            required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">How are the utilities split?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="utilitiesSplit"
                                                            id="utilitiesSplit" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">How is cleaning and maintenance of common
                                                            areas
                                                            managed?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="areasManaged"
                                                            required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">How is the layout of the commercial space
                                                            configured?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle"
                                                            name="layoutConfiguration" id="layoutConfiguration"
                                                            required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">How much storage space is available?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="storageSpace"
                                                            id="storageSpaceAvailable" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">What is the size of the room the landlord
                                                            intends to lease?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="sizeOfRoom"
                                                            id="roomSize" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">What types of businesses are neighboring
                                                            tenants
                                                            in the building or
                                                            surrounding area?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle"
                                                            name="neighboringTenants" id="neighboringTenants" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">Is there a designated reception
                                                            area?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle"
                                                            name="designatedReceptionArea" id="designatedReceptionArea"
                                                            required />
                                                    </div>
                                                </span>
                                                <span class="resFields">
                                                    <div class="form-group">
                                                        <label class="fw-bold">Are tenants allowed to have guests, and if
                                                            so,
                                                            are there any
                                                            restrictions?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="tenantsGuests"
                                                            id="custom_property_condition" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">Can tenants use common areas like the
                                                            kitchen,
                                                            living room, or
                                                            backyard?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="commonAreas"
                                                            id="custom_property_condition" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">How are maintenance issues handled?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle"
                                                            name="maintenanceIssues" id="custom_property_condition"
                                                            required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">How are the utilities split?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="utilitiesSplit"
                                                            id="custom_property_condition" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold">How is cleaning and maintenance of common
                                                            areas
                                                            managed?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="areasManaged"
                                                            id="custom_property_condition" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold"> How much storage space is
                                                            available?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="storageSpace"
                                                            id="custom_property_condition" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold"> Is there a private bathroom, or is it
                                                            shared?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="privateBathroom"
                                                            id="custom_property_condition" required />
                                                    </div>
                                                    <div class="form-group">
                                                        <label class="fw-bold"> What is the size of the room the landlord
                                                            intends to lease?</label>
                                                        <input type="text" class="form-control has-icon"
                                                            data-icon="fa-regular fa-check-circle" name="sizeOfRoom"
                                                            id="custom_property_condition" required />
                                                    </div>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </span>
                                <span class="commercialFields">
                                    @php
                                        $lease_prop_commercial = [
                                            ['name' => 'Entire Property', 'target' => ''],
                                            ['name' => 'Single Room', 'target' => '.singleRoomCommercial'],
                                        ];
                                    @endphp
                                    <div class="form-group">
                                        <label class="fw-bold">Is the landlord looking to lease their entire property or a
                                            single room?
                                        </label>
                                        <select class="grid-picker" name="leaseRoom" id="prop_condition"
                                            style="justify-content: flex-start;" required>
                                            <option value="">Select</option>
                                            @foreach ($lease_prop_commercial as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'
                                                    style="width:calc(50% - 10px);">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-group singleRoomCommercial d-none">
                                            <span class="commercialFields">
                                                <div class="form-group">
                                                    <label class="fw-bold">Are there any shared amenities, such as
                                                        conference rooms or parking
                                                        facilities?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle" name="sharedAmenities"
                                                        id="sharedAmenities" required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="fw-bold">Are there specific hours of operation for
                                                        the
                                                        building, and is 24/7
                                                        access available?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle" name="hoursOfOperation"
                                                        id="hoursOfOperation" required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="fw-bold">Are there specific zoning restrictions or
                                                        permitted uses for the
                                                        space?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle" name="zoningRestrictions"
                                                        id="zoningRestrictions" required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="fw-bold">How are maintenance issues and repairs
                                                        handled
                                                        for the commercial
                                                        space?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle" name="maintenanceHandling"
                                                        id="maintenanceHandling" required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="fw-bold">How are the utilities split?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle" name="utilitiesSplit"
                                                        id="utilitiesSplit" required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="fw-bold">How is cleaning and maintenance of common
                                                        areas
                                                        managed?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle" name="areasManaged"
                                                        required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="fw-bold">How is the layout of the commercial space
                                                        configured?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle" name="layoutConfiguration"
                                                        id="layoutConfiguration" required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="fw-bold">How much storage space is available?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle" name="storageSpace"
                                                        id="storageSpaceAvailable" required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="fw-bold">What is the size of the room the landlord
                                                        intends to lease?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle" name="sizeOfRoom"
                                                        id="roomSize" required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="fw-bold">What types of businesses are neighboring
                                                        tenants
                                                        in the building or
                                                        surrounding area?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle" name="neighboringTenants"
                                                        id="neighboringTenants" required />
                                                </div>
                                                <div class="form-group">
                                                    <label class="fw-bold">Is there a designated reception
                                                        area?</label>
                                                    <input type="text" class="form-control has-icon"
                                                        data-icon="fa-regular fa-check-circle"
                                                        name="designatedReceptionArea" id="designatedReceptionArea"
                                                        required />
                                                </div>
                                            </span>
                                        </div>
                                    </div>
                                </span>
                            </div>
                            <div class="wizard-step" data-step="8">
                                @php
                                    $prop_conditions = [
                                        ['name' => 'New Construction', 'target' => ''],
                                        ['name' => 'Completely Updated: No updates needed.', 'target' => ''],
                                        ['name' => 'Semi-updated: Needs minor updates.', 'target' => ''],
                                        ['name' => 'Not Updated: Requires a complete update.', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.custom_property_condition'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Property Condition: </label>
                                    <select class="grid-picker" name="prop_condition" id="prop_condition"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($prop_conditions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row"
                                                data-icon='<i class="fa-regular fa-circle-check"></i>'
                                                style="width:calc(50% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_property_condition d-none">
                                    <label class="fw-bold">Property Condition: </label>
                                    <input type="text" class="form-control has-icon" name="custom_property_condition"
                                        data-icon="fa-regular fa-circle-check" id="custom_property_condition" required />
                                </div>
                            </div>
                            <div class="wizard-step bedroom" data-step="9">
                                <span class="resFields">
                                    @php
                                        $bedrooms = [
                                            ['name' => '1', 'target' => ''],
                                            ['name' => '2', 'target' => ''],
                                            ['name' => '3', 'target' => ''],
                                            ['name' => '4', 'target' => ''],
                                            ['name' => '5', 'target' => ''],
                                            ['name' => '6', 'target' => ''],
                                            ['name' => '7', 'target' => ''],
                                            ['name' => '8', 'target' => ''],
                                            ['name' => '9', 'target' => ''],
                                            ['name' => '10', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.custom_bedrooms_commerical'],
                                        ];
                                    @endphp
                                    <div class="form-group">
                                        <label class="fw-bold">Bedrooms:</label>
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
                                        <div class="form-group custom_bedrooms_commerical d-none">
                                            <label class="fw-bold">Bedrooms:</label>
                                            <input type="number" class="form-control has-icon" name="custom_bedrooms"
                                                data-icon="fa-solid fa-bed" id="custom_bedrooms" required />
                                        </div>
                                    </div>
                                </span>
                            </div>
                            <div class="wizard-step bedroom" data-step="10">
                                <div class="form-group">
                                    @php
                                        $bathrooms = [
                                            ['name' => '1', 'target' => ''],
                                            ['name' => '1.5', 'target' => ''],
                                            ['name' => '2', 'target' => ''],
                                            ['name' => '2.5', 'target' => ''],
                                            ['name' => '3', 'target' => ''],
                                            ['name' => '3.5', 'target' => ''],
                                            ['name' => '4', 'target' => ''],
                                            ['name' => '4.5', 'target' => ''],
                                            ['name' => '5', 'target' => ''],
                                            ['name' => '6', 'target' => ''],
                                            ['name' => '7', 'target' => ''],
                                            ['name' => '8', 'target' => ''],
                                            ['name' => '9', 'target' => ''],
                                            ['name' => '10', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.custom_bathrooms'],
                                        ];
                                    @endphp
                                    <label class="fw-bold">Bathrooms: </label>
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
                                    <div class="form-group custom_bathrooms d-none">
                                        <label class="fw-bold">Bathrooms:</label>
                                        <input type="number" class="form-control has-icon" name="custom_bathrooms"
                                            data-icon="fa-solid fa-bath" id="custom_bathrooms" required />
                                    </div>
                                </div>
                            </div>
                            <div class="wizard-step" data-step="11">
                                <div class="form-group">
                                    <div class="form-group">
                                        <label class="fw-bold">Heated Sqft: </label>
                                        <input type="number" name="heated_square_footage"
                                            data-type="heated_square_footage" id="heated_square_footage"
                                            class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                                            data-msg-required="" required />
                                    </div>
                                    <div class="form-group commercial_hide">
                                        <div class="form-group">
                                            <label class="fw-bold">Net Leaseable Sqft: </label>
                                            <input type="number" name="net_leasable_square_footage"id="net_square_footage"
                                                class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                                                data-msg-required="" required />
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="fw-bold">Total Sqft: </label>
                                        <input type="number" name="totalSqft" id="net_square_footage"
                                            class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                                            data-msg-required="" required />
                                    </div>
                                </div>
                                <span class="resFields">
                                    @php
                                        $sqftRes = [
                                            ['name' => 'Appraisal', 'target' => ''],
                                            ['name' => 'Building', 'target' => ''],
                                            ['name' => 'Measure', 'target' => ''],
                                            ['name' => 'Owner Provided', 'target' => ''],
                                            ['name' => 'Public Records', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.other_heated_res'],
                                        ];
                                    @endphp
                                    <div class="form-group">
                                        <label class="fw-bold">Sqft Heated Source:</label>
                                        <select class="grid-picker" name="heated_sqft" id="prop_condition"
                                            style="justify-content: flex-start;" required>
                                            <option value="">Select</option>
                                            @foreach ($sqftRes as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'
                                                    style="width:calc(50% - 10px);">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group other_heated_res d-none">
                                        <label class="fw-bold"> Sqft Heated Source:</label>
                                        <input type="text" class="form-control has-icon" name="other_heated_sqft"
                                            data-icon="fa-regular fa-check-circle" id="custom_property_condition"
                                            required />
                                    </div>
                                </span>
                                <span class="commercialFields">
                                    @php
                                        $leaseCommercial = [
                                            ['name' => 'Appraisal', 'target' => ''],
                                            ['name' => 'Building', 'target' => ''],
                                            ['name' => 'Measured', 'target' => ''],
                                            ['name' => 'Owner Provided', 'target' => ''],
                                            ['name' => 'Public Records', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.other_lease_commercial'],
                                        ];
                                    @endphp
                                    <div class="form-group">
                                        <label class="fw-bold">Sqft Total Source: </label>
                                        <select class="grid-picker" name="lease_sqft" id="prop_condition"
                                            style="justify-content: flex-start;" required>
                                            <option value="">Select</option>
                                            @foreach ($leaseCommercial as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'
                                                    style="width:calc(50% - 10px);">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group other_lease_commercial d-none">
                                        <label class="fw-bold"> Sqft Total Source: </label>
                                        <input type="text" class="form-control has-icon" name="other_lease_sqft"
                                            data-icon="fa-regular fa-check-circle" id="custom_property_condition"
                                            required />
                                    </div>
                                </span>
                                @php
                                    $acreageRes = [
                                        ['name' => '0 to less than 1/4', 'target' => ''],
                                        ['name' => '1/4 to less than 1/2', 'target' => ''],
                                        ['name' => '1/2 to less than 1', 'target' => ''],
                                        ['name' => '1 to less than 2', 'target' => ''],
                                        ['name' => '2 to less than 5', 'target' => ''],
                                        ['name' => '5 to less than 10', 'target' => ''],
                                        ['name' => '10 to less than 20', 'target' => ''],
                                        ['name' => '20 to less than 50', 'target' => ''],
                                        ['name' => '50 to less than 100', 'target' => ''],
                                        ['name' => '100 to less than 200', 'target' => ''],
                                        ['name' => '200 to less than 500', 'target' => ''],
                                        ['name' => '500+ acres', 'target' => ''],
                                        ['name' => 'Non-Applicable', 'target' => ''],
                                    ];
                                @endphp
                                <div class="form-group ">
                                    <label class="fw-bold">Total Acreage:</label>
                                    <select class="grid-picker" name="total_acreage" id="total_acreage"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($acreageRes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column" style="width:calc(25% - 10px);"
                                                data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            {{-- <div class="wizard-step" data-step="12">
                                <span class="resFields">
                                    @php
                                        $sqftRes = [
                                            ['name' => 'Appraisal', 'target' => ''],
                                            ['name' => 'Building', 'target' => ''],
                                            ['name' => 'Measure', 'target' => ''],
                                            ['name' => 'Owner Provided', 'target' => ''],
                                            ['name' => 'Public Records', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.other_heated_res'],
                                        ];
                                    @endphp
                                    <div class="form-group">
                                        <label class="fw-bold">Sqft Heated Source:</label>
                                        <select class="grid-picker" name="heated_sqft" id="prop_condition"
                                            style="justify-content: flex-start;" required>
                                            <option value="">Select</option>
                                            @foreach ($sqftRes as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'
                                                    style="width:calc(50% - 10px);">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group other_heated_res d-none">
                                        <label class="fw-bold"> Sqft Heated Source:</label>
                                        <input type="text" class="form-control has-icon" name="other_heated_sqft"
                                            data-icon="fa-solid fa-ruler-combined" id="custom_property_condition"
                                            required />
                                    </div>
                                </span>
                                <span class="commercialFields">
                                    @php
                                        $leaseCommercial = [
                                            ['name' => 'Appraisal', 'target' => ''],
                                            ['name' => 'Building', 'target' => ''],
                                            ['name' => 'Measure', 'target' => ''],
                                            ['name' => 'Owner Provided', 'target' => ''],
                                            ['name' => 'Public Records', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.other_lease_commercial'],
                                        ];
                                    @endphp
                                    <div class="form-group">
                                        <label class="fw-bold">Net Leaseable Sqft Source: </label>
                                        <select class="grid-picker" name="lease_sqft" id="prop_condition"
                                            style="justify-content: flex-start;" required>
                                            <option value="">Select</option>
                                            @foreach ($leaseCommercial as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'
                                                    style="width:calc(50% - 10px);">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group other_lease_commercial d-none">
                                        <label class="fw-bold"> Net Leaseable Sqft Source: </label>
                                        <input type="text" class="form-control has-icon" name="other_lease_sqft"
                                            data-icon="fa-solid fa-ruler-combined" id="custom_property_condition"
                                            required />
                                    </div>
                                </span>
                            </div> --}}
                            {{-- <div class="wizard-step" data-step="13">
                                @php
                                    $acreageRes = [
                                        ['name' => '0 to less than 1/4', 'target' => ''],
                                        ['name' => '1/4 to less than 1/2', 'target' => ''],
                                        ['name' => '1/2 to less than 1', 'target' => ''],
                                        ['name' => '1 to less than 2', 'target' => ''],
                                        ['name' => '2 to less than 5', 'target' => ''],
                                        ['name' => '5 to less than 10', 'target' => ''],
                                        ['name' => '10 to less than 20', 'target' => ''],
                                        ['name' => '20 to less than 50', 'target' => ''],
                                        ['name' => '50 to less than 100', 'target' => ''],
                                        ['name' => '100 to less than 200', 'target' => ''],
                                        ['name' => '200 to less than 500', 'target' => ''],
                                        ['name' => '500+ acres', 'target' => ''],
                                        ['name' => 'Non-Applicable', 'target' => ''],
                                    ];
                                @endphp

                                <div class="form-group ">
                                    <label class="fw-bold">Total Acreage:</label>
                                    <select class="grid-picker" name="total_acreage" id="total_acreage"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($acreageRes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column" style="width:calc(25% - 10px);"
                                                data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div> --}}
                            <div class="wizard-step" data-step="12" data-old="14">
                                <span class="resFields">
                                    @php
                                        $garageOptions = [
                                            [
                                                'target' => '.garageYes',
                                                'name' => 'Yes',
                                                'icon' => 'fa-solid fa-warehouse',
                                            ],
                                            [
                                                'target' => '.garageNo',
                                                'name' => 'No',
                                                'icon' => 'fa-solid fa-warehouse',
                                            ],
                                        ];
                                    @endphp
                                    <div class="row align-items-end mt-4">
                                        <div class="col-md-12">
                                            <label class="fw-bold" for="heated_sqft">Garage:</label>
                                            <div class="select2-parent">
                                                <select name="garageOptions" class="grid-picker" id="" required>
                                                    <option value=""></option>
                                                    @foreach ($garageOptions as $item)
                                                        <option value="{{ $item['name'] }}"
                                                            data-target="{{ $item['target'] }}" class="card flex-column "
                                                            style="width:calc(33.3% - 10px);"
                                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                            {{ $item['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <div class="form-group garageYes d-none">
                                                    <label class="fw-bold" for="heated_sqft">How many garage spaces?
                                                    </label>
                                                    <input type="number" name="custom_garage" id="total_acreage"
                                                        class="form-control has-icon hide_arrow"
                                                        data-icon="fa-solid fa-warehouse" required>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                    @php
                                        $carportOptions = [
                                            [
                                                'target' => '.carportOptionsYes',
                                                'name' => 'Yes',
                                                'icon' => 'fa-solid fa-warehouse',
                                            ],
                                            [
                                                'target' => '.carportOptionsNo',
                                                'name' => 'No',
                                                'icon' => 'fa-solid fa-warehouse',
                                            ],
                                        ];
                                    @endphp
                                    <div class="row align-items-end mt-4">
                                        <div class="col-md-12">
                                            <labal class="fw-bold" for="heated_sqft">Carport:</labal>
                                            <div class="select2-parent">
                                                <select name="carportOptions" class="grid-picker" id=""
                                                    required>
                                                    @foreach ($carportOptions as $item)
                                                        <option value="{{ $item['name'] }}"
                                                            data-target="{{ $item['target'] }}" class="card flex-column "
                                                            style="width:calc(33.3% - 10px);"
                                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                            {{ $item['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <div class="form-group carportOptionsYes d-none">
                                                    <label class="fw-bold" for="heated_sqft">How many carport spaces?
                                                    </label>
                                                    <input type="number" name="custom_carport" id="total_acreage"
                                                        class="form-control has-icon hide_arrow"
                                                        data-icon="fa-solid fa-warehouse" required>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                            </div>
                            <div class="wizard-step" data-step="13" data-old="15">
                                <div class="form-group">
                                    <label class="fw-bold">Pool:</label>
                                    </label>
                                    @php
                                        $poolOptions = [
                                            [
                                                'name' => 'Yes',
                                                'target' => '.poolYesRes',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            [
                                                'name' => 'No',
                                                'target' => '.poolNoRes',
                                                'icon' => 'fa-regular fa-circle-xmark',
                                            ],
                                        ];
                                        $poolRes = [
                                            ['name' => 'Private', 'target' => '.poolPrivate'],
                                            ['name' => 'Community', 'target' => '.poolCommunity'],
                                        ];
                                    @endphp
                                    <select name="poolOptions" id="contribute_term" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($poolOptions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group poolYesRes d-none  ">
                                        <select name="pool" id="" class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($poolRes as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-column " style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group  d-none ">
                                        <label class="fw-bold">How many pool spaces?</label>
                                        <input type="number" name="customPool" data-type="pool"
                                            placeholder="Enter Heated Square Footage" id="pool"
                                            class="form-control has-icon"
                                            data-msg-required="Please Enter Heated Square Footage"
                                            data-icon="fa-regular fa-check-circle" required>
                                    </div>
                                </div>
                                </span>
                                <div class="row align-items-end mt-4">
                                    @php
                                        $views = [
                                            ['target' => '', 'name' => 'City', 'icon' => 'fa-regular fa-check-circle'],
                                            [
                                                'target' => '',
                                                'name' => 'Garden',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            [
                                                'target' => '',
                                                'name' => 'Golf Course',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            [
                                                'target' => '',
                                                'name' => 'Greenbelt',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            [
                                                'target' => '',
                                                'name' => 'Mountain(s)',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            ['target' => '', 'name' => 'Park', 'icon' => 'fa-regular fa-check-circle'],
                                            [
                                                'target' => '',
                                                'name' => 'Tennis Court',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            [
                                                'target' => '',
                                                'name' => 'Trees/Woods',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            ['target' => '', 'name' => 'Water', 'icon' => 'fa-regular fa-check-circle'],
                                            [
                                                'target' => '.preferenceNo',
                                                'name' => 'Beach',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            ['target' => '', 'name' => 'Pool', 'icon' => 'fa-regular fa-check-circle'],
                                            [
                                                'target' => '.viewOther',
                                                'name' => ' Other',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                        ];
                                    @endphp
                                    <div class="select2-parent">
                                        <label class="fw-bold" for="heated_sqft">View:</label>
                                        <select name="view[]" class="grid-picker" multiple required>
                                            @foreach ($views as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-column " style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-group viewOther d-none">
                                            <label class="fw-bold" for="">View:</label>
                                            <input type="text" name="viewOther" id="total_acreage"
                                                class="form-control has-icon hide_arrow"
                                                data-icon="fa-regular fa-check-circle" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            {{-- <div class="wizard-step" data-step="16">
                                <div class="row align-items-end mt-4">
                                    @php
                                        $views = [
                                            ['target' => '', 'name' => 'City', 'icon' => 'fa-regular fa-check-circle'],
                                            [
                                                'target' => '',
                                                'name' => 'Garden',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            [
                                                'target' => '',
                                                'name' => 'Golf Course',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            [
                                                'target' => '',
                                                'name' => 'Greenbelt',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            [
                                                'target' => '',
                                                'name' => 'Mountain(s)',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            ['target' => '', 'name' => 'Park', 'icon' => 'fa-regular fa-check-circle'],
                                            [
                                                'target' => '',
                                                'name' => 'Tennis Court',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            [
                                                'target' => '',
                                                'name' => 'Trees/Woods',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                            ['target' => '', 'name' => 'Water', 'icon' => 'fa-regular fa-check-circle'],
                                            [
                                                'target' => '.preferenceNo',
                                                'name' => 'Beach',
                                                'icon' => 'fa-regular fa-circle-xmark',
                                            ],
                                            [
                                                'target' => '.viewOther',
                                                'name' => ' Other',
                                                'icon' => 'fa-regular fa-check-circle',
                                            ],
                                        ];
                                    @endphp
                                    <div class="select2-parent">
                                        <label class="fw-bold" for="heated_sqft">View:</label>
                                        <select name="view[]" class="grid-picker" multiple required>
                                            @foreach ($views as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-column " style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-group viewOther d-none">
                                            <label class="fw-bold" for="">View:</label>
                                            <input type="text" name="viewOther" id="total_acreage"
                                                class="form-control has-icon hide_arrow"
                                                data-icon="fa-solid fa-ruler-combined" required>
                                        </div>
                                    </div>
                                </div>
                            </div> --}}
                            <div class="wizard-step" data-step="14" data-old="17">
                                <span class="commercialFields">
                                    <div class="form-group">
                                        @php
                                            $garageOption = [
                                                [
                                                    'target' => '.garageOptionYes',
                                                    'name' => 'Yes',
                                                    'icon' => 'fa-regular fa-check-circle',
                                                ],
                                                [
                                                    'target' => '.garageOptionNo',
                                                    'name' => 'No',
                                                    'icon' => 'fa-regular fa-circle-xmark',
                                                ],
                                            ];
                                        @endphp
                                        <label class="fw-bold">Garage/Parking Features:</label>
                                        <select name="parkingOptions" class="grid-picker" id="garage"
                                            style="justify-content: flex-start;" required>
                                            @foreach ($garageOption as $item)
                                                <option value="{{ $item['name'] }}"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.3% - 10px);">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @php
                                            $garageParking = [
                                                ['name' => '1 to 5 Spaces', 'target' => ''],
                                                ['name' => '6 to 12 Spaces', 'target' => ''],
                                                ['name' => '13 to 18 Spaces', 'target' => ''],
                                                ['name' => '19 to 30 Spaces', 'target' => ''],
                                                ['name' => 'Airplane Hangar', 'target' => ''],
                                                ['name' => 'Common', 'target' => ''],
                                                ['name' => 'Curb Parking', 'target' => ''],
                                                ['name' => 'Deeded', 'target' => ''],
                                                ['name' => 'Electric Vehicle Charging Station(s)', 'target' => ''],
                                                ['name' => 'Ground Level', 'target' => ''],
                                                ['name' => 'Lighted', 'target' => ''],
                                                ['name' => 'Over 30 Spaces', 'target' => ''],
                                                ['name' => 'RV Parking', 'target' => ''],
                                                ['name' => 'Secured', 'target' => ''],
                                                ['name' => 'Under Building', 'target' => ''],
                                                ['name' => 'Underground', 'target' => ''],
                                                ['name' => 'Valet', 'target' => ''],
                                                ['name' => 'None', 'target' => ''],
                                                ['name' => 'Other', 'target' => '.other_garage'],
                                            ];
                                        @endphp
                                        <div class="form-group d-none garageOptionYes">
                                            <select class="grid-picker" name="parking"
                                                style="justify-content: flex-start;" multiple required>
                                                <option value="">Select</option>
                                                @foreach ($garageParking as $item)
                                                    <option value="{{ $item['name'] }}"
                                                        data-target="{{ $item['target'] }}" class="card flex-column"
                                                        style="width:calc(25% - 10px);"
                                                        data-icon='<i class="fa-solid fa-warehouse"></i>'>
                                                        {{ $item['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="form-group other_garage d-none">
                                                <label class="fw-bold" for="other_garage">Garage/Parking Features:</label>
                                                <input type="text" name="parkingOther"
                                                    class="form-control has-icon hide_arrow"
                                                    data-icon="fa-solid fa-warehouse" required>
                                            </div>
                                        </div>
                                    </div>
                                </span>
                            </div>
                            <div class="wizard-step" data-step="15" data-old="18">
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
                                        ['name' => 'Touchless Faucet', 'target' => ''],
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
                                    <label class="fw-bold">Appliances: </label>

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
                                    <label class="fw-bold">Appliances: </label>
                                    <input type="text" class="form-control has-icon" name="otherAppliances"
                                        data-icon="fa-regular fa-check-circle" id="cutom_appliances" required />
                                </div>
                            </div>
                            <div class="wizard-step" data-step="16" data-old="19">
                                <span class="resFields">
                                    @php
                                        $Furnishings = [
                                            ['name' => 'Furnished', 'target' => ''],
                                            ['name' => 'Optional', 'target' => ''],
                                            ['name' => 'Partial', 'target' => ''],
                                            ['name' => 'Turnkey', 'target' => ''],
                                            ['name' => 'Unfurnished', 'target' => ''],
                                        ];
                                    @endphp
                                    <div class="form-group">
                                        <label class="fw-bold">Furnishings: </label>

                                        <select class="grid-picker" name="furnishings[]" id="appliances"
                                            style="justify-content: flex-start;" multiple required>
                                            <option value="">Select</option>
                                            @foreach ($Furnishings as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row" style="width:calc(33.33% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </span>
                            </div>
                            <div class="wizard-step" data-step="17" data-old="20">
                                <span class="commercialFields">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            Amenities and Property Features:
                                        </label>
                                        @php
                                            $amenitiesFeatureCommercial = [
                                                ['name' => 'Access to Public Transportation', 'target' => ''],
                                                ['name' => 'Business Center', 'target' => ''],
                                                ['name' => 'Common Areas', 'target' => ''],
                                                ['name' => 'Conference Room', 'target' => ''],
                                                ['name' => 'Elevator', 'target' => ''],
                                                ['name' => 'Energy-Efficient Features', 'target' => ''],
                                                ['name' => 'Fire Safety Systems', 'target' => ''],
                                                ['name' => 'Flexibility for Renovations', 'target' => ''],
                                                ['name' => 'Green Building Certification', 'target' => ''],
                                                ['name' => 'Gym/Fitness Facilities', 'target' => ''],
                                                ['name' => 'Handicap Accessibility', 'target' => ''],
                                                ['name' => 'High-Speed Internet', 'target' => ''],
                                                ['name' => 'HVAC System', 'target' => ''],
                                                ['name' => 'Industrial Features', 'target' => ''],
                                                ['name' => 'Kitchenette/Break Room', 'target' => ''],
                                                ['name' => 'Loading Dock', 'target' => ''],
                                                ['name' => 'Lounge Area', 'target' => ''],
                                                ['name' => 'Natural Lighting', 'target' => ''],
                                                ['name' => 'Office Space', 'target' => ''],
                                                ['name' => 'On-site Maintenance', 'target' => ''],
                                                ['name' => 'On-site Management', 'target' => ''],
                                                ['name' => 'Open Floor Plan', 'target' => ''],
                                                ['name' => 'Parking Spaces', 'target' => ''],
                                                ['name' => 'Proximity to Highways', 'target' => ''],
                                                ['name' => 'Reception Area', 'target' => ''],
                                                ['name' => 'Restrooms', 'target' => ''],
                                                ['name' => 'Retail Frontage', 'target' => ''],
                                                ['name' => 'Restaurant Space', 'target' => ''],
                                                ['name' => 'Security Guard', 'target' => ''],
                                                ['name' => 'Security System', 'target' => ''],
                                                ['name' => 'Signage Opportunities', 'target' => ''],
                                                ['name' => 'Storage Space', 'target' => ''],
                                                ['name' => 'Utilities Included', 'target' => ''],
                                                ['name' => 'Visibility from Main Road', 'target' => ''],
                                                ['name' => 'Warehouse Space', 'target' => ''],
                                                ['target' => '.otherAmenitiesFeatureCommercial', 'name' => 'Other'],
                                            ];
                                        @endphp
                                        <select name="amenities[]" id="negotiable_terms" class="grid-picker"
                                            style="justify-content: flex-start;" multiple required>
                                            <option value=""></option>
                                            @foreach ($amenitiesFeatureCommercial as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" 
                                                style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group otherAmenitiesFeatureCommercial d-none">
                                        <label class="fw-bold" for="custom_non_negotiable_terms"> Amenities and Property
                                            Features:
                                        </label>
                                        <input type="text" name="otherAmenities" id="custom_non_negotiable_terms"
                                            placeholder="" class="form-control" data-icon="fa-regular fa-check-circle"
                                            required>
                                    </div>
                                </span>
                                <span class="resFields">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            Amenities and Property Features:
                                        </label>
                                        @php
                                            $amenitiesFeatureRes = [
                                                ['target' => '', 'name' => '55 and Over Community'],
                                                ['target' => '', 'name' => 'Accessibility Features'],
                                                ['target' => '', 'name' => 'Balcony/Patio'],
                                                ['target' => '', 'name' => 'Carpet Floors'],
                                                ['target' => '', 'name' => 'Carport'],
                                                ['target' => '', 'name' => 'Central Air Conditioning'],
                                                ['target' => '', 'name' => 'Central Heating'],
                                                ['target' => '', 'name' => 'Clubhouse'],
                                                ['target' => '', 'name' => 'Covered Carport'],
                                                ['target' => '', 'name' => 'Elevator'],
                                                ['target' => '', 'name' => 'Fireplace'],
                                                ['target' => '', 'name' => 'Fitness Center/Gym'],
                                                ['target' => '', 'name' => 'First Floor Unit'],
                                                ['target' => '', 'name' => 'Gated Community'],
                                                ['target' => '', 'name' => 'Garage'],
                                                ['target' => '', 'name' => 'Hardwood Floors'],
                                                ['target' => '', 'name' => 'HOA Community'],
                                                ['target' => '', 'name' => 'In-Unit Laundry'],
                                                ['target' => '', 'name' => 'On-site Laundry'],
                                                ['target' => '', 'name' => 'On-site Maintenance'],
                                                ['target' => '', 'name' => 'On-site Management'],
                                                ['target' => '', 'name' => 'Outdoor Space'],
                                                ['target' => '', 'name' => 'Pet Friendly'],
                                                ['target' => '', 'name' => 'Playground'],
                                                ['target' => '', 'name' => 'Pool'],
                                                ['target' => '', 'name' => 'Security System'],
                                                ['target' => '', 'name' => 'Specific School District'],
                                                ['target' => '', 'name' => 'Storage Space'],
                                                ['target' => '', 'name' => 'Study/Den/Office'],
                                                ['target' => '', 'name' => 'Tile Floors'],
                                                ['target' => '', 'name' => 'Updated Bathroom'],
                                                ['target' => '', 'name' => 'Updated Kitchen'],
                                                ['target' => '', 'name' => 'Walk-in Closet'],
                                                ['target' => '', 'name' => 'Washer and Dryer'],
                                                ['target' => '', 'name' => 'Washer and Dryer Hookup'],
                                                ['target' => '', 'name' => 'Waterfront'],
                                                ['target' => '.otherAmenitiesFeatureRes', 'name' => 'Other'],
                                            ];
                                        @endphp
                                        <select name="amenities[]" id="negotiable_terms" class="grid-picker"
                                            style="justify-content: flex-start;" multiple required>
                                            <option value=""></option>
                                            @foreach ($amenitiesFeatureRes as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row" style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group otherAmenitiesFeatureRes d-none">
                                        <label class="fw-bold" for="custom_negotiable_terms"> Amenities and Property Features:
                                        </label>
                                        <input type="text" name="otherAmenities" id="custom_negotiable_terms"
                                            placeholder="" class="form-control has-icon"
                                            data-icon="fa-regular fa-check-circle" required>
                                    </div>
                                </span>
                            </div>
                            <div class="wizard-step" data-step="18" data-old="21">
                                @php
                                    $rent_includes = [
                                        ['name' => 'Cable TV', 'target' => ''],
                                        ['name' => 'Electricity', 'target' => ''],
                                        ['name' => 'Gas', 'target' => ''],
                                        ['name' => 'Grounds Care', 'target' => ''],
                                        ['name' => 'Insurance', 'target' => ''],
                                        ['name' => 'Internet', 'target' => ''],
                                        ['name' => 'Laundry', 'target' => ''],
                                        ['name' => 'Management', 'target' => ''],
                                        ['name' => 'Pest Control', 'target' => ''],
                                        ['name' => 'Pool Maintenance', 'target' => ''],
                                        ['name' => 'Recreational', 'target' => ''],
                                        ['name' => 'Repairs', 'target' => ''],
                                        ['name' => 'Security', 'target' => ''],
                                        ['name' => 'Sewer', 'target' => ''],
                                        ['name' => 'Taxes', 'target' => ''],
                                        ['name' => 'Telephone', 'target' => ''],
                                        ['name' => 'Trash Collection', 'target' => ''],
                                        ['name' => 'Water', 'target' => ''],
                                        ['name' => 'None', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.rent_include'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Rent Includes: </label>
                                    <select class="grid-picker" name="rent_include[]" id="rent_include"
                                        style="justify-content: flex-start;" multiple required>
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
                                    <label class="fw-bold">Rent Includes: </label>
                                    <input type="text" class="form-control has-icon" name="other_rent_include"
                                        data-icon="fa-regular fa-check-circle" id="rent_include" required />
                                </div>
                            </div>
                            <div class="wizard-step" data-step="19" data-old="22">
                                @php
                                    $tenantPays = [
                                        ['name' => 'Association Fees', 'target' => ''],
                                        ['name' => 'Capital Expenses', 'target' => ''],
                                        ['name' => 'Common Area Maintenance', 'target' => ''],
                                        ['name' => 'Condominium Fees', 'target' => ''],
                                        ['name' => 'Electricity', 'target' => ''],
                                        ['name' => 'Gas', 'target' => ''],
                                        ['name' => 'Liability Insurance', 'target' => ''],
                                        ['name' => 'Parking Fee', 'target' => ''],
                                        ['name' => 'Pro-Rated', 'target' => ''],
                                        ['name' => 'Property Insurance', 'target' => ''],
                                        ['name' => 'Property Taxes', 'target' => ''],
                                        ['name' => 'Reserves', 'target' => ''],
                                        ['name' => 'Sewer', 'target' => ''],
                                        ['name' => 'Trash Collection', 'target' => ''],
                                        ['name' => 'Water', 'target' => ''],
                                        ['name' => 'None ', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.otherTenantPays'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Tenant Pays:</label>
                                    <select class="grid-picker" name="tenantPays[]" id="rent_include"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value="">Select</option>
                                        @foreach ($tenantPays as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.33% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group otherTenantPays d-none">
                                    <label class="fw-bold">Tenant Pays: </label>
                                    <input type="text" class="form-control has-icon" placeholder=""
                                        name="otherTenantPays" data-icon="fa-regular fa-check-circle" id="rent_include"
                                        required />
                                </div>
                                @php
                                    $ownerPays = [
                                        ['name' => 'Cable TV', 'target' => ''],
                                        ['name' => 'Electricity', 'target' => ''],
                                        ['name' => 'Gas', 'target' => ''],
                                        ['name' => 'Grounds Care', 'target' => ''],
                                        ['name' => 'Insurance', 'target' => ''],
                                        ['name' => 'Internet', 'target' => ''],
                                        ['name' => 'Laundry', 'target' => ''],
                                        ['name' => 'Management', 'target' => ''],
                                        ['name' => 'Pest Control', 'target' => ''],
                                        ['name' => 'Pool Maintenance', 'target' => ''],
                                        ['name' => 'Recreational', 'target' => ''],
                                        ['name' => 'Repairs', 'target' => ''],
                                        ['name' => 'Security', 'target' => ''],
                                        ['name' => 'Sewer', 'target' => ''],
                                        ['name' => 'Taxes', 'target' => ''],
                                        ['name' => 'Telephone', 'target' => ''],
                                        ['name' => 'Trash Collection', 'target' => ''],
                                        ['name' => 'Water', 'target' => ''],
                                        ['name' => 'None', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.otherOwnerPays'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Owner Pays:</label>
                                    <select class="grid-picker" name="ownerPays[]" id=""
                                        style="justify-content: flex-start;" multiple required>
                                        <option value="">Select</option>
                                        @foreach ($ownerPays as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.33% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group otherOwnerPays d-none">
                                    <label class="fw-bold">Owner Pays:</label>
                                    <input type="text" class="form-control has-icon" placeholder=""
                                        name="otherOwnerPays" data-icon="fa-regular fa-check-circle" id="rent_include"
                                        required />
                                </div>

                            </div>
                            <div class="wizard-step" data-step="20" data-old="23">
                                @php
                                    $petOptions = [
                                        [
                                            'target' => '.petYes',
                                            'name' => 'Yes',
                                            'icon' => 'fa-solid fa-dog',
                                        ],
                                        ['target' => '.petNo', 'name' => 'No', 'icon' => 'fa-solid fa-dog'],
                                    ];
                                @endphp
                                <div class="row align-items-end mt-4">
                                    <div class="col-md-12">
                                        <labal class="fw-bold" for="heated_sqft">Will the landlord accept pets?</labal>
                                        <div class="select2-parent">
                                            <select name="petOptions" class="grid-picker" id="" required>
                                                <option value=""></option>
                                                @foreach ($petOptions as $item)
                                                    <option value="{{ $item['name'] }}"
                                                        data-target="{{ $item['target'] }}" class="card flex-column "
                                                        style="width:calc(33.3% - 10px);"
                                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                        {{ $item['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group petYes d-none">
                                            <div class="form-group">
                                                <label class="fw-bold" for="heated_sqft">Number of Pets Allowed:</label>
                                                <input type="text" name="petsNumber" id="total_acreage"
                                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" >
                                            </div>
                                            <div class="form-group">
                                                <label class="fw-bold" for="heated_sqft">Acceptable Pet Types:</label>
                                                <input type="text" name="petsType" id="total_acreage"
                                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" >
                                            </div>
                                            <div class="form-group">
                                                <label class="fw-bold" for="heated_sqft">Maximum Pet Weight:</label>
                                                <input type="text" name="petsWeight" id="total_acreage"
                                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" >
                                            </div>
                                            <div class="form-group">
                                                <label class="fw-bold" for="heated_sqft">One-Time Pet Deposit or Monthly
                                                    Pet Fee:</label>
                                                <input type="text" name="petsFee" id="total_acreage"
                                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" >
                                            </div>
                                            <div class="form-group">
                                                <label class="fw-bold" for="heated_sqft">Pet Fee Amount:</label>
                                                <input type="number" name="petsFeeAmount" id="total_acreage"
                                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar-sign" >
                                            </div>
                                            <div class="form-group">
                                                <label class="fw-bold" for="heated_sqft">Is the Pet Fee Refundable or
                                                    Non-Refundable?</label>
                                                <input type="text" name="petsFund" id="total_acreage"
                                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" >
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="wizard-step" data-step="21" data-old="24">
                                <div class="form-group">
                                    <label class="fw-bold">Is the property located in a 55-and-over community?</label>
                                    </label>
                                    @php
                                        $propertyLoc = [
                                            ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-check-circle'],
                                            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        ];
                                    @endphp
                                    <select name="propertyLoc" id="propertyLoc" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($propertyLoc as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="wizard-step" data-step="22" data-old="25">
                                <span class="resFields">
                                    @php
                                        $leaseAmount = [
                                            ['name' => 'Annually', 'target' => ''],
                                            ['name' => 'Daily', 'target' => ''],
                                            ['name' => 'Monthly', 'target' => ''],
                                            ['name' => 'Seasonally', 'target' => '.season_runs'],
                                            ['name' => 'Weekly', 'target' => ''],
                                        ];
                                    @endphp
                                    <div class="form-group">
                                        <label class="fw-bold">Select the frequency at which the lease amount is paid:
                                        </label>

                                        <select class="grid-picker" name="leaseAmount" id="appliances"
                                            style="justify-content: flex-start;" required>
                                            <option value="">Select</option>
                                            @foreach ($leaseAmount as $item)
                                                <option value="{{ $item['name'] }}"
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.33% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="season_runs d-none">
                                        <div class="form-group">
                                            <label class="fw-bold">Season runs from:</label>
                                            <input type="date" name="season_runs_from" id="season_runs_from" class="form-control has-icon"
                                                data-icon="fa-regular fa-calendar-days" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="fw-bold">Season runs to:</label>
                                            <input type="date" name="season_runs_to" id="season_runs_to" class="form-control has-icon"
                                                data-icon="fa-regular fa-calendar-days" required>
                                        </div>
                                    </div>
                                </span>
                                <span class="commercialFields">
                                    @php
                                        $leaseAmount = [
                                            ['name' => 'Annually', 'target' => ''],
                                            ['name' => 'Monthly', 'target' => ''],
                                        ];
                                    @endphp
                                    <div class="form-group">
                                        <label class="fw-bold">Select the frequency in which the Lease Amount is paid:
                                        </label>

                                        <select class="grid-picker" name="leaseAmount" id="appliances"
                                            style="justify-content: flex-start;" multiple required>
                                            <option value="">Select</option>
                                            @foreach ($leaseAmount as $item)
                                                <option value="{{ $item['name'] }}"
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.33% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </span>
                            </div>
                            {{-- <div class="wizard-step" data-step="23" data-old="26">
                                @php
                                    $leaseDuration = [
                                        ['name' => '3 Months', 'target' => ''],
                                        ['name' => '6 Months', 'target' => ''],
                                        ['name' => '9 Months', 'target' => ''],
                                        ['name' => '1 Year', 'target' => ''],
                                        ['name' => '2 Years', 'target' => ''],
                                        ['name' => '3-5 Years', 'target' => ''],
                                        ['name' => '5+ Years', 'target' => ''],
                                        ['name' => 'Month to Month', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.otherLeaseDuration'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Acceptable Lease Duration: </label>

                                    <select class="grid-picker" name="leaseDuration" id="appliances"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value="">Select</option>
                                        @foreach ($leaseDuration as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.33% - 10px);"
                                                data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group otherLeaseDuration d-none">
                                        <label class="fw-bold">Acceptable Lease Duration: <span
                                                class="text-danger"></span></label>
                                        <input type="text" class="form-control has-icon" name="otherLeaseDuration"
                                            data-icon="fa-solid fa-calendar-days" required />
                                    </div>
                                </div>
                            </div> --}}
                            <div class="wizard-step" data-step="23" data-old="24">
                                @php
                                    $termLease = [
                                        ['name' => 'Absolute (Triple) Net', 'target' => ''],
                                        ['name' => 'Gross Lease', 'target' => ''],
                                        ['name' => 'Gross Percentages', 'target' => ''],
                                        ['name' => 'Ground Lease', 'target' => ''],
                                        ['name' => 'Lease Option', 'target' => ''],
                                        ['name' => 'Modified Gross', 'target' => ''],
                                        ['name' => 'Net Lease', 'target' => ''],
                                        ['name' => 'Net Net', 'target' => ''],
                                        ['name' => 'Other', 'target' => ''],
                                        ['name' => 'Pass Throughs', 'target' => ''],
                                        ['name' => 'Purchase Option', 'target' => ''],
                                        ['name' => 'Renewal Option', 'target' => ''],
                                        ['name' => 'Sale-Leaseback', 'target' => ''],
                                        ['name' => 'Seasonal', 'target' => ''],
                                        ['name' => 'Special Available (CLO)', 'target' => ''],
                                        ['name' => 'Varied Terms', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.other_terms_lease'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold"> Terms of Lease:</label>

                                    <select class="grid-picker" name="termLease[]" id="appliances"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value="">Select</option>
                                        @foreach ($termLease as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.33% - 10px);"
                                                data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <div class="form-group other_terms_lease d-none">
                                        <label class="fw-bold">Terms of Lease:<span class="text-danger"></span></label>
                                        <input type="text" class="form-control has-icon" placeholder=" "
                                            name="termLeaseOther" data-icon="fa-regular fa-calendar-days"
                                            id="occupant_type_input" required />
                                    </div>
                                </div>
                            </div>
                            <div class="wizard-step" data-step="24" data-old="25">
                                @php
                                    $occupant_types = [
                                        ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                        ['name' => 'Occupied', 'target' => '.custom_occupant_type', 'icon' => 'fa-regular fa-circle-check'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">What is the current occupancy status of the property?</label>
                                    <select class="grid-picker" name="occupant_type" id="occupant_type_select"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($occupant_types as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_occupant_type d-none">
                                    <label class="fw-bold">When is the property occupied until?<span
                                            class="text-danger"></span></label>
                                    <input type="date" class="form-control has-icon" placeholder=" "
                                        name="occupied_until" data-icon="fa-solid fa-calendar-days"
                                        id="occupant_type_input" required />
                                </div>
                            </div>
                            <div class="wizard-step" data-step="25" data-old="26">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Desired Rental Amount:
                                    </label>
                                    <input type="number" class="form-control has-icon" name="expectation"
                                        data-icon="fa-solid fa-dollar-sign" id="expectation" required />
                                </div>
                            </div>
                            <div class="wizard-step" data-step="26" data-old="27">
                                <div class="form-group">

                                    <label class="fw-bold">Listing Availability Date:</label>
                                    <input type="date" name="custom_ready_timeframe" id="custom_ready_timeframe"
                                        class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                                </div>
                            </div>
                            <div class="wizard-step" data-step="27" data-old="28">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Desired Lease Length:
                                    </label>
                                    @php
                                        $lease_period = [
                                            ['name' => '3 Months', 'target' => ''],
                                            ['name' => '6 Months', 'target' => ''],
                                            ['name' => '9 Months', 'target' => ''],
                                            ['name' => '1 Year', 'target' => ''],
                                            ['name' => '2 Years', 'target' => ''],
                                            ['name' => '3-5 Years', 'target' => ''],
                                            ['name' => '5+ Years', 'target' => ''],
                                            ['name' => 'Month to Month', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.custom_lease_period'],
                                        ];
                                    @endphp
                                    <select name="lease_period" id="lease_period" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($lease_period as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_lease_period d-none">
                                    <label class="fw-bold">Desired Lease Length: </label>
                                    <input type="text" class="form-control has-icon" name="custom_lease_period"
                                        data-icon="fa-solid fa-calendar-days" id="custom_lease_period" required />
                                </div>
                            </div>
                            <div class="wizard-step" data-step="28" data-old="29">
                                <div class="form-group ">
                                    <label class="fw-bold">
                                        What is the timeframe offered to the agent in the landlord agency agreement? 
                                    </label>
                                    @php
                                        $listing_terms_res = [
                                            ['name' => '3 Months', 'target' => ''],
                                            ['name' => '6 Months', 'target' => ''],
                                            ['name' => '9 Months', 'target' => ''],
                                            ['name' => '12 Months', 'target' => ''],
                                            ['name' => 'Negotiable', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.custom_listing_terms_residential'],
                                        ];
                                    @endphp
                                    <select name="listing_term" id="listing_term" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($listing_terms_res as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_listing_terms_residential d-none ">
                                    <label class="fw-bold">What is the timeframe offered to the agent in the Landlord
                                        Agency
                                        Agreement?</label>
                                    <input type="text" class="form-control has-icon" placeholder=""
                                        name="custom_listing_terms" data-icon="fa-solid fa-calendar-days"
                                        id="custom_listing_terms" required />
                                </div>
                                {{-- </span> --}}
                            </div>
                            <div class="wizard-step" data-step="29" data-old="30">
                                <h4>Owner's Agreement on Commission Rates:</h4>
                                <h6>The owner agrees to compensate the broker as follows, including paying any applicable taxes on the broker's services, if the owner enters into a lease of the property with a tenant during the listing period, regardless of whether the tenant fulfills the terms of the lease; or if, during the listing period, the broker procures a tenant who is ready, willing, and able to lease the property under the terms of this agreement, or terms acceptable to the owner. All commission is negotiable.</h6>
                                
                                <div class="form-group">
                                    @php
                                        $broker_compensation = [
                                            [ 'name' => "___% of each rental period", 'target' => ''],
                                            ['name' => "___% of the gross lease value", 'target' => ''],
                                            ['name' => "____% of the first month's rent", 'target' => ''],
                                            ['name' => 'Fixed amount: $____ ', 'target' => ''],
                                            ['name' => 'Negotiable', 'target' => ''],
                                        ];
                                    @endphp
                                    <label class="fw-bold">What compensation will the owner provide to the listing broker for their services?</label>
                                    <select class="grid-picker" name="broker_compensation" id="broker_compensation"
                                        style="justify-content: flex-start;">
                                        <option value="">Select</option>
                                        @foreach ($broker_compensation as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group compensation_broker_yes d-none ">
                                        <label class="fw-bold">What compensation will the owner provide to the listing broker for their services?</label>
                                        <input type="number" class="form-control has-icon" placeholder=""
                                            name="compensation_percent" data-icon="fa-solid fa-dollar-sign" required />
                                    </div>                                
                                </div>
                                <div class="form-group">
                                    @php
                                        $handle_compensation = [
                                            ['name' => "Allow the listing broker to compensate the tenant's broker from the listing broker's commission, if applicable.", 'target' => ''],
                                            ['name' => "Pay the tenant's broker separately, if applicable.", 'target' => ''],
                                            ['name' => "No compensation will be offered to the tenant’s broker.", 'target' => ''],
                                        ];
                                    @endphp
                                    <label class="fw-bold">How would the owner prefer to handle compensation for a tenant's broker?</label>
                                    <select class="grid-picker" name="handle_compensation" id="handle_compensation"
                                        style="justify-content: flex-start;">
                                        <option value="">Select</option>
                                        @foreach ($handle_compensation as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group handle_compensation_broker_yes d-none ">
                                        @php
                                        $compensation_amount = [
                                            [ 'name' => "___% of the gross lease value", 'target' => ''],
                                            ['name' => "____% of the first month’s rent", 'target' => ''],
                                            ['name' => 'Fixed amount: $____', 'target' => ''],
                                            ['name' => 'Negotiable', 'target' => ''],
                                        ];
                                        @endphp
                                        <label class="fw-bold">What compensation is being offered to the tenant's broker?</label>
                                        <select class="grid-picker" name="compensation_amount" id="compensation_amount"
                                            style="justify-content: flex-start;">
                                            <option value="">Select</option>
                                            @foreach ($compensation_amount as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-group compensation_amount_yes d-none ">
                                            <label class="fw-bold">What compensation is being offered to the tenant's broker?</label>
                                            <input type="number" class="form-control has-icon" placeholder=""
                                                name="compensation_tenant_broker" data-icon="fa-solid fa-dollar-sign" required />
                                        </div> 
                                    </div>                                
                                </div>
                                <div class="form-group">
                                    @php
                                        $payment_timing = [
                                            ['name' => "Deducted from rent collected by the broker; the owner will pay the balance (if any) within ___ calendar days of the rent due date.", 'target' => ''],
                                            ['name' => "Paid within ___ calendar days after the lease agreement is executed.", 'target' => ''],
                                            ['name' => "Paid within ___ calendar days of each tenant's rent payment.", 'target' => ''],
                                        ];
                                    @endphp
                                    <label class="fw-bold">Payment Timing for Broker Fees:</label>
                                    <select class="grid-picker" name="payment_timing" id="payment_timing"
                                        style="justify-content: flex-start;">
                                        <option value="">Select</option>
                                        @foreach ($payment_timing as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group payment_timing_days d-none ">
                                        <label class="fw-bold">Payment Timing for Broker Fees:</label>
                                        <input type="number" class="form-control has-icon" placeholder=""
                                            name="payment_timing_days" data-icon="fa-regular fa-check-circle" required />
                                    </div>                                
                                </div>
                                {{-- <div class="form-group ">
                                    <label class="fw-bold">
                                        What is the total commission being offered to the listing agent?
                                    </label>
                                    @php
                                        $offered_commissions = [
                                            ['name' => 'One Month’s Rent', 'target' => ''],
                                            ['name' => '10% of the Gross Value of the Lease', 'target' => ''],
                                            ['name' => 'Negotiable', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.commissionOther'],
                                        ];
                                    @endphp
                                    <select name="offered_commission" id="offered_commission" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($offered_commissions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(25% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group commissionOther d-none ">
                                        <label class="fw-bold">What is the total commission being offered to the listing
                                            agent?</label>
                                        <input type="text" class="form-control has-icon"
                                            name="offeredCommissionOther" data-icon="fa-solid fa-dollar-sign"
                                            id="custom_offered_commission" required />
                                    </div>
                                </div> --}}
                            </div>
                            {{-- <div class="wizard-step" data-step="30" data-old="31">
                                <div class="form-group ">
                                    <label class="fw-bold">
                                        If a tenant is represented by an agent, what portion of the commission should be
                                        shared with the tenant’s agent?
                                    </label>
                                    @php
                                        $offered_commissions = [
                                            ['name' => 'Half a Month’s Rent,', 'target' => ''],
                                            ['name' => '5% of the Gross Value of the Lease,', 'target' => ''],
                                            ['name' => 'Negotiable', 'target' => ''],
                                            ['name' => 'None', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.commissionTenantOther'],
                                        ];
                                    @endphp
                                    <select name="tenantCommission" id="offered_tenant_commission" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($offered_commissions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(25% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group commissionTenantOther d-none ">
                                        <label class="fw-bold">If a tenant is represented by an agent, what portion of the
                                            commission should be
                                            shared with the tenant’s agent?</label>
                                        <input type="text" class="form-control has-icon"
                                            name="tenantCommissionOther" data-icon="fa-solid fa-dollar-sign"
                                            id="custom_offered_commission" required />
                                    </div>
                                </div>
                            </div> --}}
                            <div class="wizard-step" data-step="30">
                                <h4>Early Termination and Protection Period:</h4>
                                <div class="form-group">
                                    @php
                                        $early_termination = [
                                            ['name' => 'The owner may terminate this agreement by signing a withdrawal agreement and paying a cancellation fee of $______ plus tax.', 'target' => ''],
                                            ['name' => "If the property is leased to a tenant during the termination or protection period, the broker may void the termination and collect full compensation (minus the cancellation fee).", 'target' => ''],
                                        ];
                                    @endphp
                                    <label class="fw-bold">Early Termination:</label>
                                    <select class="grid-picker" name="early_termination" id="early_termination"
                                        style="justify-content: flex-start;">
                                        <option value="">Select</option>
                                        @foreach ($early_termination as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group early_termination_yes d-none ">
                                        <label class="fw-bold">Early Termination:</label>
                                        <input type="number" class="form-control has-icon" placeholder=""
                                            name="early_termination_amount" data-icon="fa-regular fa-check-circle" required />
                                    </div>                                
                                </div>

                                <div class="form-group">
                                    @php
                                        $protection_period = [
                                            ['name' => "If the owner leases the property within ___ days after the listing period to any tenant introduced by the broker, the owner agrees to pay the broker's fee.", 'target' => '.protection_period_yes'],
                                            ['name' => "The broker will provide a list of prospects upon request; compensation applies only to names on the list.", 'target' => ''],
                                            ['name' => "The protection period is void if the owner signs an exclusive agreement with another broker after the listing period.", 'target' => ''],
                                        ];
                                    @endphp
                                    <label class="fw-bold">Protection Period:</label>
                                    <select class="grid-picker" name="protection_period" id="protection_period"
                                        style="justify-content: flex-start;">
                                        <option value="">Select</option>
                                        @foreach ($protection_period as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>    
                                    <div class="form-group protection_period_yes d-none ">
                                        <label class="fw-bold">Protection Period:</label>
                                        <input type="number" class="form-control has-icon" placeholder=""
                                            name="protection_period_days" data-icon="fa-regular fa-check-circle" required />
                                    </div>                        
                                </div>
                            </div>
                            <div class="wizard-step" data-step="31">
                                <h4>New Leases and Renewals:</h4>
                                <div class="form-group">
                                    @php
                                        $compensation_new_lease = [
                                            ['name' => 'Yes', 'target' => '.new_lease_yes'],
                                            ['name' => "No", 'target' => ''],
                                            ['name' => "Negotiable", 'target' => ''],
                                        ];
                                    @endphp
                                    <label class="fw-bold">If the owner enters into a new lease or renewal with a tenant placed in the property by or through the broker, does the owner agree to pay the broker compensation for the new lease or renewal?</label>
                                    <select class="grid-picker" name="compensation_new_lease" id="compensation_new_lease"
                                        style="justify-content: flex-start;">
                                        <option value="">Select</option>
                                        @foreach ($compensation_new_lease as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group new_lease_yes d-none ">
                                        @php
                                        $compensation_amount = [
                                            ['name' => '___% of each rental period', 'target' => ''],
                                            ['name' => "___% of the gross lease value", 'target' => ''],
                                            ['name' => "___% of the first month's rent", 'target' => ''],
                                            ['name' => 'Fixed amount: $____', 'target' => ''],
                                            ['name' => "Negotiable", 'target' => ''],
                                        ];
                                        @endphp
                                        <label class="fw-bold">What compensation will the owner provide to the listing broker for a new lease or lease renewal?</label>
                                        <select class="grid-picker" name="compensation_new_lease_percent" id="compensation_new_lease_amount"
                                            style="justify-content: flex-start;">
                                            <option value="">Select</option>
                                            @foreach ($compensation_amount as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-group compensation_new_lease_amount d-none ">
                                            <label class="fw-bold">What compensation will the owner provide to the listing broker for a new lease or lease renewal?</label>
                                            <input type="number" class="form-control has-icon" placeholder=""
                                                name="compensation_new_lease_amount" data-icon="fa-regular fa-check-circle" required />
                                        </div> 
                                    </div>                                
                                </div>
                            </div>
                            <div class="wizard-step" data-step="32" data-old="32">
                                <div class="row">
                                    <div class="form-group mt-4 col-md-12">
                                        <label class='fw-bold'>
                                            What are the most important aspects the landlord will consider when hiring a
                                            real estate agent?
                                        </label>
                                        <textarea name="description" placeholder="" class="form-control" rows="5">{{ old('description') }}</textarea>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="form-group mt-4 col-md-12">
                                        <label class='fw-bold'>
                                            What additional details would the landlord like to share with the agent?
                                        </label>
                                        <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="wizard-step residential_remove" data-step="33" data-old="36">
                                <span class="resFields">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            Select the services that the landlord requests from an agent:
                                        </label>
                                        @php
                                            $serviceRes = [
                                                [
                                                    'name' => 'Assist in drafting residential lease agreements and required addendums/disclosures.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Assist in negotiating residential lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Assist with lease renewal negotiations and adjustments to rental terms.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Assist with tenant move-in and move-out inspections.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Conduct a thorough rental market analysis (RMA) to determine the property\'s value and pricing strategy.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Conduct property showings and viewings for interested tenants.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Conduct real estate email marketing campaigns that lead to the property listing.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Conduct tenant screening with a thorough application process that includes credit, criminal, background, eviction, and income verification checks.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Coordinate or assist in the move-in or move-out process for tenants.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Coordinate property maintenance and repairs through trusted contractors and vendors.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Handle tenant inquiries, maintenance requests, and resolve any issues that may arise during the tenancy.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Host an Open House(s).',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'List the property on major real estate websites—such as Zillow, Trulia, Realtor.com, Homes.com, and others—to increase visibility and exposure.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'List the property on the Bid Your Offer platform.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Other - Add additional services as needed.',
                                                    'target' => '.otherRes',
                                                ],
                                                [
                                                    'name' => 'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide a 3D tour to showcase the property\'s features.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide a floor plan of the property to highlight its layout and spatial configuration.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide a professional video to showcase the property\'s features.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide professional photos showcasing the property\'s features.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide regular updates on market activity, showings, and feedback from potential tenants.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide virtual staging to enhance the property\'s visual appeal and attract potential tenants.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Send email alerts to tenants searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.',
                                                    'target' => '',
                                                ],
                                            ];

                                        @endphp
                                        <select name="services[]" multiple class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($serviceRes as $item)
                                                <option value="{{ $item['name'] }}"
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(100%);"
                                                    data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group otherRes d-none">
                                        <label class="fw-bold">What additional services would the landlord like to request
                                            from an agent?
                                        </label>
                                        <input type="text" class="form-control has-icon" name="servicesOther"
                                            data-icon="fa-solid fa-hand-point-right" id="custom_services_data"
                                            required />
                                    </div>
                                </span>
                                <span class="commercialFields">
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            Select the services that the landlord requests from an agent:
                                        </label>
                                        @php
                                            $serviceCommercial = [
                                                [
                                                    'name' => 'Assist in drafting residential lease agreements and required addendums/disclosures.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Assist in negotiating residential lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Assist with lease renewal negotiations and adjustments to rental terms.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Assist with tenant move-in and move-out inspections.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Conduct a thorough rental market analysis (RMA) to determine the property\'s value and pricing strategy.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Conduct property showings and viewings for interested tenants.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Conduct real estate email marketing campaigns that lead to the property listing.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Conduct tenant screening with a thorough application process that includes credit, criminal, background, eviction, and income verification checks.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Coordinate or assist in the move-in or move-out process for tenants.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Coordinate property maintenance and repairs through trusted contractors and vendors.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Handle tenant inquiries, maintenance requests, and resolve any issues that may arise during the tenancy.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'List the property on Crexi, a major commercial real estate website.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'List the property on Loopnet, a major commercial real estate website.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'List the property on the Bid Your Offer platform.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'List the property on the MLS.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Other - Add additional services as needed.',
                                                    'target' => '.otherCommercial',
                                                ],
                                                [
                                                    'name' => 'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide a 3D tour to showcase the property\'s features.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide a floor plan of the property to highlight its layout and spatial configuration.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide a professional video to showcase the property\'s features.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide professional photos showcasing the property\'s features.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide regular updates on market activity, showings, and feedback from potential tenants.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Provide virtual staging to enhance the property\'s visual appeal and attract potential tenants.',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Send email alerts to tenants searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.',
                                                    'target' => '',
                                                ],
                                            ];
                                        @endphp
                                        <select name="services[]" multiple class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($serviceCommercial as $item)
                                                <option value="{{ $item['name'] }}"
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(100%);"
                                                    data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group otherCommercial d-none">
                                        <label class="fw-bold">What additional services would the landlord like to request
                                            from an agent?
                                        </label>
                                        <input type="text" class="form-control has-icon" name="servicesOther"
                                            data-icon="fa-solid fa-hand-point-right" id="custom_services_data"
                                            required />
                                    </div>
                                </span>
                            </div>
                            <div class="wizard-step" data-step="34" data-old="37">
                                <h4>For a more personalized listing, you can include a picture of
                                    yourself and/or include a video of yourself providing additional information about your
                                    background and criteria.</h4>
                                <div class="row form-group">
                                    <div class="col-md-6">
                                        <label class="fw-bold">First Name:</label>
                                        <input type="text" class="form-control has-icon" name="first_name"
                                            id="first_name" value="{{ auth()->user()->first_name }}"
                                            data-icon="fa-solid fa-user" required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="fw-bold">Last Name:</label>
                                        <input type="text" class="form-control has-icon" name="last_name"
                                            id="last_name" value="{{ auth()->user()->last_name }}"
                                            data-icon="fa-solid fa-user" required />
                                    </div>
                                </div>
                                <div class="row form-group">
                                    <div class="col-md-6">
                                        <label class="fw-bold">Phone Number:</label>
                                        <input type="text" class="form-control has-icon" name="phone"
                                            id="phone" value="{{ auth()->user()->phone }}"
                                            data-icon="fa-solid fa-phone" required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="fw-bold">Email:</label>
                                        <input type="email" class="form-control has-icon" name="email"
                                            id="email" value="{{ auth()->user()->email }}"
                                            data-icon="fa-solid fa-envelope" required />
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6 video_div">
                                        <input type="hidden" name="video_type" class="video_type" value="video_upload">
                                        <div class="video_type_select d-flex align-items-center justify-content-left">
                                            <div class="form-check me-2">
                                                <label class="form-check-label">
                                                    <input type="checkbox" class="video_upload form-check-input video_type_check"
                                                        name="video_upload">
                                                    Video Upload
                                                </label>
                                            </div>
                                            <div class="form-check me-2">
                                                <label class="form-check-label">
                                                    <input type="checkbox" class="youtube_video form-check-input video_type_check"
                                                        name="youtube_video">
                                                    Youtube Video
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <label class="form-check-label">
                                                    <input type="checkbox" class="vimeo_video form-check-input video_type_check"
                                                        name="vimeo_video">
                                                    Vimeo Video
                                                </label>
                                            </div>
                                        </div>
                                        <div class="video-upload video-type-element">
                                            <label class="fw-bold mt-1">Video:</label>
                                            <div class="videoBox ">
                                                <div class="video bgImg"></div>
                                                <div class="form-group videoDiv">
                                                    <input type="file" class="fileuploader" name="video"
                                                        style="display: none;" accept="video/*">
                                                    <label for="fileuploader" class="fileuploader-btn">
                                                        <span class="upload-button">+</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="youtube-video video-type-element form-group d-none">
                                            <label class="fw-bold mt-1"> Youtube Video:</label>
                                            <input type="text" name="youtube_video_link" class="form-control"
                                                placeholder="Youtube video link i.e. https://youtube.com/embed/videoId">
                                        </div>
                                        <div class="vimeo-video video-type-element form-group d-none">
                                            <label class="fw-bold mt-1">Vimeo Video:</label>
                                            <input type="text" name="vimeo_video_link" class="form-control"
                                                placeholder="Vimeo video link i.e. https://player.vimeo.com/video/videoId">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="upload form-group">
                                            <label class="fw-bold">Photo:</label>
                                            <div class="wrapper">
                                                <div class="box">
                                                    <div class="js--image-preview"></div>
                                                    <div class="upload-options">
                                                        <label>
                                                            <input type="file" name="photo" class="image-upload"
                                                                accept="image/*" />
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
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
                                        style="display: none;" id="saveBtn">Save</button>
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
        // Video Preview
        $(document).ready(function($) {
            // Click button to activate hidden file input
            $('.fileuploader-btn').on('click', function() {
                $('.fileuploader').click();
            });

            // Click above calls the open dialog box
            // Once something is selected the change function will run
            $('.fileuploader').change(function() {
                $('#fileSizeError').remove();
                if (this.files[0].size > 50000000) {
                    $('.videoDiv').after(
                        '<span id="fileSizeError"  style="color: red;">Please upload a file less than 50MB. Thanks!</span>'
                    );
                    $(this).val('');
                    $('#saveBtn').prop('disabled', true);
                } else {
                    $('#saveBtn').prop('disabled', false);
                    $('#fileSizeError').remove();
                }
                // Check if a file has been selected
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    var file = this.files[0];

                    if (file.type.startsWith('image/')) {
                        reader.onload = function(event) {
                            $('.video').empty();
                            $('.video').append('<img src="' + event.target.result +
                                '" width="200" height="160">');
                        };
                        reader.readAsDataURL(file);
                        $('.video').removeClass('bgImg');
                    } else if (file.type.startsWith('video/')) {
                        reader.onload = function(event) {
                            var fileContent = event.target.result;
                            $('.video').empty();
                            $('.video').append('<video src="' + fileContent +
                                '" width="200" height="160" controls autoplay></video>');
                        };
                        reader.readAsDataURL(file);
                        $('.video').removeClass('bgImg');
                    } else {
                        // File is neither image nor video
                        alert('Please select either an image or a video file.');
                        this.value = ''; // Clear the file input
                        return;
                    }

                    // Hide the preview icon and show the upload button
                    $('.video').removeClass('bgImg');
                    $('.upload-button').show();
                } else {
                    // If no file is selected, and if .video container is empty, show the preview icon and hide the add button
                    if ($('.video').is(':empty')) {
                        $('.video').addClass('bgImg');
                        $('.upload-button').show();
                    } else {
                        // If a file already exists, hide the preview icon and show the add button
                        $('.video').removeClass('bgImg');
                        $('.upload-button').show();
                    }
                }
            });
        });




        // Video Preview
        function initImageUpload(box) {
            let uploadField = box.querySelector('.image-upload');

            uploadField.addEventListener('change', getFile);

            function getFile(e) {
                let file = e.currentTarget.files[0];
                checkType(file);
            }

            function previewImage(file) {
                let thumb = box.querySelector('.js--image-preview'),
                    reader = new FileReader();

                reader.onload = function() {
                    thumb.style.backgroundImage = 'url(' + reader.result + ')';
                }
                reader.readAsDataURL(file);
                thumb.className += ' js--no-default';
            }

            function checkType(file) {
                let imageType = /image.*/;
                if (!file.type.match(imageType)) {
                    throw 'Datei ist kein Bild';
                } else if (!file) {
                    throw 'Kein Bild gewählt';
                } else {
                    previewImage(file);
                }
            }

        }

        // initialize box-scope
        var boxes = document.querySelectorAll('.box');

        for (let i = 0; i < boxes.length; i++) {
            let box = boxes[i];
            initDropEffect(box);
            initImageUpload(box);
        }



        /// drop-effect
        function initDropEffect(box) {
            let area, drop, areaWidth, areaHeight, maxDistance, dropWidth, dropHeight, x, y;

            // get clickable area for drop effect
            area = box.querySelector('.js--image-preview');
            area.addEventListener('click', fireRipple);

            function fireRipple(e) {
                area = e.currentTarget
                // create drop
                if (!drop) {
                    drop = document.createElement('span');
                    drop.className = 'drop';
                    this.appendChild(drop);
                }
                // reset animate class
                drop.className = 'drop';

                // calculate dimensions of area (longest side)
                areaWidth = getComputedStyle(this, null).getPropertyValue("width");
                areaHeight = getComputedStyle(this, null).getPropertyValue("height");
                maxDistance = Math.max(parseInt(areaWidth, 10), parseInt(areaHeight, 10));

                // set drop dimensions to fill area
                drop.style.width = maxDistance + 'px';
                drop.style.height = maxDistance + 'px';

                // calculate dimensions of drop
                dropWidth = getComputedStyle(this, null).getPropertyValue("width");
                dropHeight = getComputedStyle(this, null).getPropertyValue("height");

                // calculate relative coordinates of click
                // logic: click coordinates relative to page - parent's position relative to page - half of self height/width to make it controllable from the center
                x = e.pageX - this.offsetLeft - (parseInt(dropWidth, 10) / 2);
                y = e.pageY - this.offsetTop - (parseInt(dropHeight, 10) / 2) - 30;

                // position drop and animate
                drop.style.top = y + 'px';
                drop.style.left = x + 'px';
                drop.className += ' animate';
                e.stopPropagation();

            }
        }

        function validateFile() {
            const fileInput = document.querySelector('input[name="video"]');
            const fileSizeError = document.getElementById('fileSizeError');
            const maxFileSize = 5 * 1024 * 1024; // 5MB

            if (fileInput.files[0].size > maxFileSize) {
                fileSizeError.style.display = 'block';
                fileInput.value = ''; // Clear the file input to prevent submission
                return false;
            } else {
                fileSizeError.style.display = 'none';
                return true;
            }
        }

        function show_garage_opt() {
            var h = $('#garage').val();
            if (h == "Yes" || h == "Optional") {
                $('.garage_opt').show();
            } else {
                $('.garage_opt').hide();
            }
        }
        $(function() {
            show_garage_opt("");
        });
    </script>
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
            if (v == "Auction Listing") {
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
            changeAuctionType("Auction Listing");
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
                $('.bedroomRes').removeClass('d-none');
                $('.traditional_hide').addClass('d-none');
                $('.commercial_hide').addClass('d-none');
                $('.business-length').hide();
                $('.removeResidential').addClass('d-none');
                $('.resFields').each(function() {
                    $(this).find('select, input ,textarea,option').prop('disabled', false);
                    $(this).show();
                });
                $('.commercialFields').each(function() {
                    $(this).find('select, input ,textarea,option').prop('disabled', true);
                    $(this).hide();
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
                $('.bedroomRes').addClass('d-none');
                $('.traditional_hide').removeClass('d-none');
                $('.commercial_hide').removeClass('d-none');
                $('.vacant_land-length').hide();
                $('.business-length').hide();
                $('.removeCommercial').addClass('d-none');
                $('.commercialFields').each(function() {
                    $(this).find('select, input ,textarea,option').prop('disabled', false);
                    $(this).show();
                });
                $('.resFields').each(function() {
                    $(this).find('select, input ,textarea,option').prop('disabled', true);
                    $(this).hide();
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
            // console.log(v);
            check_custom();
        }

        function check_custom() {
            $('.option-container').each(function(i, elm) {
                var target = $(elm).data('target') || "";
                var is_active = $(elm).hasClass('active');
                if (target != "") {
                    // console.log("is_active", is_active, target);
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
                    console.log(StepWizard.currentStep);
                    if (v.form()) {
                        if ($('.wizard-step.active').next().is('.wizard-step')) {
                            $('.wizard-step.active').removeClass('active');
                            if (StepWizard.currentStep == 7 && property_type ==
                                'Income Property') {
                                StepWizard.nextStep = 10;
                                StepWizard.backStep = 7;
                            } else if (StepWizard.currentStep == 13 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 15;
                                StepWizard.backStep = 13;
                            } else if (StepWizard.currentStep == 18 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 20;
                                StepWizard.backStep = 18;
                            } else if (StepWizard.currentStep == 23 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 25;
                                StepWizard.backStep = 23;
                            } else if (StepWizard.currentStep == 8 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 10;
                                StepWizard.backStep = 8;
                            } else if (StepWizard.currentStep == 11 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 14;
                                StepWizard.backStep = 11;
                            } else if (StepWizard.currentStep == 15 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 17;
                                StepWizard.backStep = 15;
                            } else if (StepWizard.currentStep == 17 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 19;
                                StepWizard.backStep = 17;
                            } else if (StepWizard.currentStep == 19 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 22;
                                StepWizard.backStep = 19;
                            } else {
                                StepWizard.backStep = StepWizard.currentStep;
                            }
                            $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
                            StepWizard.setStep();
                            if (
                                StepWizard.currentStep == 19 &&
                                (property_type ==
                                    'Income Property')
                            ) {
                                $('.wizard-step-next').hide();
                                $('.wizard-step-finish').show();
                            }
                            if (
                                StepWizard.currentStep == 34 &&
                                (property_type == 'Commercial Property' || property_type ==
                                    'Residential Property')
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
                        }
                        // else if (StepWizard.currentStep == 12 && property_type ==
                        //   'Residential Property') {
                        //   StepWizard.backStep = 10;
                        // }
                        else if (StepWizard.currentStep == 15 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 13;
                        } else if (StepWizard.currentStep == 20 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 18;
                        } else if (StepWizard.currentStep == 25 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 23;
                        } else if (StepWizard.currentStep == 10 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 8;
                        } else if (StepWizard.currentStep == 14 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 11;
                        } else if (StepWizard.currentStep == 17 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 15;
                        } else if (StepWizard.currentStep == 19 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 17;
                        } else if (StepWizard.currentStep == 22 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 19;
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
                    //   Remove All the SLides Except THe Residential and Commercial Property
                    //   if (property_type == 'Residential Property' || property_type == 'Income Property') {
                    //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                    //       return parseInt($(this).attr('data-step')) >= 20 && parseInt($(this)
                    //         .attr('data-step')) <= 43;
                    //     });

                    //     $stepsToRemove.each(function() {
                    //       $(this).closest('div[data-step]').remove();
                    //     });
                    //   }
                    //   Remove All the SLides Except THe Commercial and Business Opportunity
                    //   if (property_type === 'Commercial Property' || property_type ===
                    //     'Business Opportunity') {
                    //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                    //       var stepValue = parseInt($(this).attr('data-step'));
                    //       return (stepValue >= 5 && stepValue <= 15) || (stepValue >= 20 &&
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
                var comp = 0;

                if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 34) {
                    // Calculate progress for Residential and Income property steps (5 to 19)
                    comp = 20 + (((StepWizard.currentStep - 5) / (34 - 5)) * 80);
                } else if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 34) {
                    // Calculate progress for Commercial and Business opportunity steps (20 to 32)
                    comp = 20 + (((StepWizard.currentStep - 5) / (34 - 5)) * 80);
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
        $(document).ready(function(){
            $(document).on('change', '#broker_compensation', function(){
                let val = $(this).val();
                if(val !== 'Negotiable'){
                    $('.compensation_broker_yes').removeClass('d-none');
                }else{
                    $('.compensation_broker_yes').addClass('d-none');
                }
            });

            $(document).on('change', '#handle_compensation', function(){
                let val = $(this).val();
                if(val !== 'No compensation will be offered to the tenant’s broker.'){
                    $('.handle_compensation_broker_yes').removeClass('d-none');
                }else{
                    $('.handle_compensation_broker_yes').addClass('d-none');
                }
            })

            $(document).on('change', '#compensation_amount', function(){
                let val = $(this).val();
                if(val !== 'Negotiable'){
                    $('.compensation_amount_yes').removeClass('d-none');
                }else{
                    $('.compensation_amount_yes').addClass('d-none');
                }
            })

            $(document).on('change', '#early_termination', function(){
                $('.early_termination_yes').removeClass('d-none');
            })

            $(document).on('change', '#compensation_new_lease_amount', function(){
                let val = $(this).val();

                if(val !== 'Negotiable'){
                    $('.compensation_new_lease_amount').removeClass('d-none');
                }else{
                    $('.compensation_new_lease_amount').addClass('d-none');
                }
            })

            $(document).on('change', '#payment_timing', function(){
                $('.payment_timing_days').removeClass('d-none');
            })
        })
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
