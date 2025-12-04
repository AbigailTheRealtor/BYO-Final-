@extends('layouts.main')
@push('styles')
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

        /* .select-btn {
            padding: 1px 10px;
            border: 1px solid rgba(0, 0, 0, 0.175);
            border-radius: 5px;
            color: #11b7cf;
            background-color: transparent;
            margin-bottom: 5px;
        }

        .select-btn.active {
            border-color: #11b7cf;
        } */
    </style>
@endpush
@php
    $yes_or_nos = [
        ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
    ];
    $yes_or_nos_opt = [
        ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
        ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
    ];
@endphp
@section('content')
    <div class="container p-4">
        <h4 class="title">
            {{ $title }}
        </h4>

        <div class="card m-4">
            <div class="card-title">
                {{--  --}}
            </div>
            <div class="card-body">
                <div class="wizard-steps-progress">
                    <div class="steps-progress-percent"></div>
                </div>

                <form class="p-4 pt-0 mainform" action="{{ route('buyer_agent.auction.add') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    <div class="wizard-step" data-step="1">

                        <h4> Please provide the cities, counties, and state pertaining to the real estate location
                            that the buyer intends to purchase a property. </h4>
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
                                            <input type="text" name="cities[]" data-type="cities" id="cities"
                                                class="form-control  search_places has-icon" data-icon="fa-solid fa-city"
                                                data-msg-required="" placeholder="" required>
                                        </td>
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
                                        <th>Counties:</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="counties[]" data-type="counties" id="counties"
                                                class="form-control  search_places has-icon"
                                                data-icon="fa-solid fa-tree-city" data-msg-required="" placeholder=""
                                                required>
                                        </td>
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
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>State:</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="states[]" data-type="states" id="state"
                                                class="form-control  search_places has-icon"
                                                data-icon="fa-solid fa-flag-usa" data-msg-required="" placeholder=""
                                                required>
                                        </td>
                                    </tr>
                                    <tr class="state_btn_row">
                                        <td><button type="button" class="btn btn-secondary btn-sm w-100"
                                                onclick="add_state_row();"><i class="fa-solid fa-plus"></i> Add New
                                                Row</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="2">
                        <div class="form-group">
                            <label for="address" class="fw-bold">Listing Date:</label>
                            <input type="date" name="listing_date" id="listing_date"
                                class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="address" class="fw-bold">Expiration Date:</label>
                            <input type="date" name="expiration_date" id="expiration_date"
                                class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                required>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="3">
                        <div class="form-group">
                            <label class="fw-bold">
                                Listing Service Type:
                            </label>
                            <div>
                                @php
                                    $auction_types = [
                                        [
                                            'name' => 'Full Service',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                            'target' => '',
                                        ],
                                        [
                                            'name' => ' Limited Service',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                            'target' => '',
                                        ],
                                    ];
                                @endphp
                                <select name="service_type" id="service_type" class="grid-picker"
                                    style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($auction_types as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon="{{ $item['icon'] }}">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Representation:</label>
                            @php
                                $representation = [
                                    [
                                        'name' => 'Buyer Represented',
                                        'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Buyer Not Represented',
                                        'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        'target' => '',
                                    ],
                                ];
                            @endphp
                            <select name="representation" id="representation" class="grid-picker"
                                style="justify-content: flex-start;" required>
                                <option value=""></option>
                                @foreach ($representation as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row " style="width:calc(33.3% - 10px);"
                                        data-icon="{{ $item['icon'] }}">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="4">
                        <div class="form-group">
                            <label class="fw-bold">
                                Title of Listing:
                            </label>
                            <input type="text" name="titleListing" class="form-control has-icon"
                                data-icon="fa-solid fa-hotel" required>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="5">
                        @php
                            $property_types = [
                                ['name' => 'Residential Property'],
                                ['name' => 'Income Property'],
                                ['name' => 'Commercial Property'],
                                ['name' => 'Business Opportunity'],
                                ['name' => 'Vacant Land'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Acceptable Property Styles: </label>
                            <select class="grid-picker" name="property_type" id="property_type"
                                onchange="changePropertyType(this.value)" required>
                                <option value="">Select</option>
                                @foreach ($property_types as $row_pt)
                                    <option value="{{ $row_pt['name'] }}" class="card flex-column"
                                        style="width:calc(24% - 10px);" data-icon='<i class="fa-solid fa-hotel"></i>'>
                                        {{ $row_pt['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group property_style">
                            @php
                                $propertyStyles = [
                                    ['name' => 'Agriculture', 'target' => ''],
                                    ['name' => 'Assembly Building', 'target' => ''],
                                    ['name' => 'Business', 'target' => ''],
                                    ['name' => 'Five or More', 'target' => ''],
                                    ['name' => 'Hotel/Motel', 'target' => ''],
                                    ['name' => 'Industrial', 'target' => ''],
                                    ['name' => 'Mixed Use', 'target' => ''],
                                    ['name' => 'Office', 'target' => ''],
                                    ['name' => 'Restaurant', 'target' => ''],
                                    ['name' => 'Retail', 'target' => ''],
                                    ['name' => 'Warehouse', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Property Style:</label>
                                <select class="grid-picker" name="propertyStyles" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($propertyStyles as $item)
                                        <option value="{{ $item['name'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            @php
                                $property_items = [
                                    [
                                        'name' => 'Single Family Residence',
                                        'class' => 'residential-length',
                                        'target' => '',
                                    ],
                                    ['name' => 'Townhouse', 'class' => 'residential-length', 'target' => ''],
                                    ['name' => 'Villa', 'class' => 'residential-length', 'target' => ''],
                                    ['name' => 'Condominium', 'class' => 'residential-length', 'target' => ''],
                                    ['name' => 'Condo-Hotel', 'class' => 'residential-length', 'target' => ''],
                                    ['name' => '½ Duplex', 'class' => 'residential-length', 'target' => ''],
                                    ['name' => 'Dock-Rackominium', 'class' => 'residential-length', 'target' => ''],
                                    ['name' => 'Farm', 'class' => 'residential-length', 'target' => ''],
                                    ['name' => 'Garage Condo', 'class' => 'residential-length', 'target' => ''],
                                    [
                                        'name' => 'Mobile Home- Pre 1976',
                                        'class' => 'residential-length',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Manufactured Home- Post 1977',
                                        'class' => 'residential-length',
                                        'target' => '',
                                    ],
                                    ['name' => 'Modular Home', 'class' => 'residential-length', 'target' => ''],
                                    ['name' => 'Duplex', 'class' => 'income-length', 'target' => ''],
                                    ['name' => 'Triplex', 'class' => 'income-length', 'target' => ''],
                                    ['name' => 'Quadplex', 'class' => 'income-length', 'target' => ''],
                                    ['name' => 'Five or More', 'class' => 'income-length', 'target' => ''],
                                    ['name' => 'Agriculture', 'class' => 'commercial-length', 'target' => ''],
                                    ['name' => 'Assembly Building', 'class' => 'commercial-length', 'target' => ''],
                                    ['name' => 'Business', 'class' => 'commercial-length', 'target' => ''],
                                    ['name' => 'Five or More', 'class' => 'commercial-length', 'target' => ''],
                                    ['name' => 'Hotel/Motel', 'class' => 'commercial-length', 'target' => ''],
                                    ['name' => 'Industrial', 'class' => 'commercial-length', 'target' => ''],
                                    ['name' => 'Mixed Use', 'class' => 'commercial-length', 'target' => ''],
                                    ['name' => 'Office', 'class' => 'commercial-length', 'target' => ''],
                                    ['name' => 'Restaurant', 'class' => 'commercial-length', 'target' => ''],
                                    ['name' => 'Retail', 'class' => 'commercial-length', 'target' => ''],
                                    ['name' => 'Warehouse', 'class' => 'commercial-length', 'target' => ''],

                                    ['name' => 'Aeronautical', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Agriculture', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Arts and Entertainment', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Assembly Hall', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Assisted Living', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Auto Dealer', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Auto Service', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Bar/Tavern/Lounge', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Barber/Beauty', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Car Wash', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Child Care', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Church', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Commercial', 'class' => 'business-length', 'target' => ''],
                                    [
                                        'name' => 'Concession Trailers/Vehicles ',
                                        'class' => 'business-length',
                                        'target' => '',
                                    ],
                                    ['name' => 'Construction/Contractor', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Convenience Stores ', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Distribution', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Distributor Routine Ven', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Education/School', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Farm', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Fashion/Specialty', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Flex Space', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Florist/Nursery', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Food & Beverage', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Gas Station', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Grocery', 'class' => 'business-length', 'target' => ''],
                                    [
                                        'name' => 'Heavy Weight Sales Service',
                                        'class' => 'business-length',
                                        'target' => '',
                                    ],
                                    ['name' => 'Hotel/Motel', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Industrial', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Light Items Sales Only', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Manufacturing', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Marine/Marina', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Medical', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Mixed', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Mobile/Trailer Park', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Personal Service', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Professional Service', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Professional/Office', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Recreation', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Research & Development', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Residential', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Restaurant', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Retail', 'class' => 'business-length', 'target' => ''],
                                    [
                                        'name' => 'Shopping Center/Strip Center ',
                                        'class' => 'business-length',
                                        'target' => '',
                                    ],
                                    ['name' => 'Storage', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Theater', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Timberland', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Veterinary', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Warehouse', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Wholesale', 'class' => 'business-length', 'target' => ''],
                                    ['name' => 'Other', 'class' => 'business-length', 'target' => '.businessOther'],

                                    ['name' => 'Agricultural', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Billboard Site', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Business', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Cattle', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Commercial', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Farm', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Fishery', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Highway Frontage', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Horses', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Industrial', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Land Fill', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Livestock', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Mixed Use', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Multi family', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Nursery', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Orchard', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Pasture', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Poultry', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Ranch', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Residential', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Retail', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Row Crops', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Sod Farm', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Subdivision', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Timber', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Tracts', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Trans/Cell Tower', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Tree Farm', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Unimproved Land', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Well Field', 'class' => 'vacant_land-length', 'target' => ''],
                                    ['name' => 'Other', 'class' => 'vacant_land-length', 'target' => '.vacantOther'],
                                ];
                            @endphp
                            <label for="" class="fw-bold businessType">Business Type: </label>
                            <label for="" class="fw-bold currentUse">Current Use: </label>
                            <select name="property_items[]" id="property_items" class="property_items grid-picker"
                                style="justify-content: flex-start;" multiple required>
                                <option value=""></option>
                                @foreach ($property_items as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row {{ $item['class'] }}" style="width:calc(33.33% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group businessOther d-none">
                                <label class="fw-bold">Business Type:</label>
                                <input type="text" name="businessOther" id="" class="form-control has-icon"
                                    data-icon="fa-regular fa-check-circle" placeholder="">
                            </div>
                            <div class="form-group vacantOther d-none">
                                <label class="fw-bold">Current Use:</label>
                                <input type="text" name="vacantOther" id="" class="form-control has-icon"
                                    data-icon="fa-regular fa-check-circle" placeholder="">
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="6">
                        @php
                            $special_sale = [
                                ['name' => 'Assignment Contract', 'target' => '.assignmentRes'],
                                ['name' => 'Bank Owned/REO', 'target' => ''],
                                ['name' => 'Probate Listing', 'target' => ''],
                                ['name' => 'Short Sale', 'target' => ''],
                                ['name' => 'Government Owned', 'target' => ''],
                                ['name' => 'Auction', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => '.otherSaleRes'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Acceptable Special Sale Provisions:</label>
                            <select class="grid-picker" name="special_sales[]" id=""
                                style="justify-content: flex-start;" required multiple>
                                <option value="">Select</option>
                                @foreach ($special_sale as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group otherSaleRes d-none">
                                <label class="fw-bold">Acceptable Special Sale Provisions: </label>
                                <input type="text" name="otherSaleRes" id="" class="form-control has-icon"
                                    data-icon="fa-regular fa-circle-check" placeholder="" required>
                            </div>
                            <div class="form-group assignmentRes d-none">
                                @php

                                    $assignmentResOpt = [
                                        [
                                            'icon' => 'fa-regular fa-circle-check',
                                            'name' => 'Yes',
                                            'target' => '.assignmentYesRes2',
                                        ],
                                        [
                                            'icon' => 'fa-regular fa-circle-xmark',
                                            'name' => 'No',
                                            'target' => '.assignmentNoRes2',
                                        ],
                                    ];

                                    $assignmentNoResOpt = [
                                        [
                                            'icon' => 'fa-regular fa-circle-check',
                                            'name' => 'Yes',
                                            'target' => '.assignmentYesResOptYes2',
                                        ],
                                        [
                                            'icon' => 'fa-regular fa-circle-xmark',
                                            'name' => 'No',
                                            'target' => '.assignmentNoResOptNo2',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Is the buyer looking to take over another buyer’s contract?</label>
                                <select class="grid-picker" name="specialSaleOpt" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($assignmentResOpt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group assignmentYesRes2 d-none">
                                    <div class="d-flex justify-content-between aalign-items-center">
                                        <label class="fw-bold">What fee would the buyer pay the agent for selling an
                                            assignment
                                            contract?</label>
                                        <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                            <button type="button" class="select-btn me-1 active"
                                                data-type="amount">$</button>
                                            <button type="button" class="select-btn" data-type="percent">%</button>
                                        </div>
                                    </div>
                                    <input type="text" name="specialOptYes" id=""
                                        class="form-control has-icon input-changable-icon"
                                        data-icon="fa-solid fa-dollar-sign" data-symbol="amount" placeholder="" required>
                                </div>
                                <div class="form-group assignmentNoRes2 d-none">
                                    <label class="fw-bold">Is the buyer looking to sell their contract? </label>
                                    {{-- <input type="text" name="specialOptNo" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                                        placeholder="" required> --}}
                                    <select class="grid-picker" name="specialOptResNo" id=""
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($assignmentNoResOpt as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column" style="width:calc(25% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group assignmentYesResOptYes2 d-none">
                                        <div class="d-flex justify-content-between aalign-items-center">
                                            <label class="fw-bold">What fee would the buyer pay the agent for selling an
                                                assignment contract?</label>
                                            <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                                <button type="button" class="select-btn me-1 active"
                                                    data-type="amount">$</button>
                                                <button type="button" class="select-btn" data-type="percent">%</button>
                                            </div>
                                        </div>
                                        <input type="text" name="specialOptYes" id=""
                                            class="form-control has-icon input-changable-icon"
                                            data-icon="fa-solid fa-dollar-sign" data-symbol="amount" placeholder="" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="7">
                        <span class="resFields">
                            @php
                                $propertyConditionsRes = [
                                    ['name' => 'Pre-Construction', 'target' => ''],
                                    ['name' => 'Currently Being Built', 'target' => ''],
                                    ['name' => 'New Construction', 'target' => ''],
                                    ['name' => 'Completely Updated: No updates needed', 'target' => ''],
                                    ['name' => 'Semi-updated: Needs minor updates', 'target' => ''],
                                    ['name' => 'Not Updated: Requires a complete update', 'target' => ''],
                                    [
                                        'name' => 'Tear Down: Requires complete demolition and reconstruction',
                                        'target' => '',
                                    ],
                                    ['name' => 'Open to any type of property condition', 'target' => ''],
                                    ['name' => 'Other', 'target' => '.conditionsResOther'],
                                ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Acceptable Property Conditions:</label>
                                <select class="grid-picker" name="prop_condition[]" id="prop_condition"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($propertyConditionsRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(50% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group conditionsResOther">
                                    <label class="fw-bold">Acceptable Property Condition:</label>
                                    <input type="text" name="propConditionOther" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        placeholder="">
                                </div>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="8">
                        <span class="resFields">
                            <label class="fw-bold">
                                Desired Price and Terms:
                            </label>
                            <div class="form-group">
                                <label class="fw-bold">
                                    Maximum Budget:
                                </label>
                                <input type="number" name="max_price" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar-sign" required>
                            </div>
                            @php
                                $financings = [
                                    ['name' => 'Cash', 'target' => '.cashRes'],
                                    ['name' => 'Conventional', 'target' => ''],
                                    ['name' => 'Seller Financing', 'target' => '.custom_seller_financing_res'],
                                    ['name' => 'FHA', 'target' => ''],
                                    ['name' => 'Jumbo', 'target' => ''],
                                    ['name' => 'VA', 'target' => ''],
                                    ['name' => 'No-Doc', 'target' => ''],
                                    ['name' => 'Non-QM', 'target' => ''],
                                    ['name' => 'Assumable', 'target' => '.custom_assumable_res'],
                                    ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade_res'],
                                    ['name' => 'Lease Option', 'target' => '.lease_option'],
                                    ['name' => 'Lease Purchase', 'target' => '.lease_purchase'],
                                    ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency_res'],
                                    ['name' => 'Non-Fungible Token (NFT)', 'target' => '.nft'],
                                    ['name' => 'USDA', 'target' => ''],
                                    ['name' => 'Other', 'target' => '.financingOtherRes'],
                                ];
                            @endphp
                            <div class="row align-items-end mt-4 ">
                                <div class="col-md-12">
                                    <label class="fw-bold">Offered Currency/Financing:</label>
                                    <div class="select2-parent">
                                        <select name="financings[]" class="grid-picker" multiple id="financingOptions"
                                            required>
                                            @foreach ($financings as $financing)
                                                <option value="{{ $financing['name'] }}"
                                                    data-target="{{ $financing['target'] }}" class="card flex-column "
                                                    style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                                    {{ $financing['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            {{-- Cash --}}
                            {{-- <div class="form-group cashRes d-none ">
                                    <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                                    <input type="text" name="cash[]" data-type="" id="" class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler-combined" >
                                </div> --}}
                            {{-- other --}}
                            <div class="form-group financingOtherRes d-none ">

                                <label class="fw-bold">What type of financing/funds will the buyer use to purchase the
                                    property?</label>
                                <input type="text" name="financingOther" data-type="" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                            </div>
                            {{-- other --}}
                            {{-- exchange/trade --}}
                            <div class="form-group custom_exchange_trade_res d-none ">
                                @php
                                    $exchangeItems = [
                                        ['name' => 'Another Home', 'target' => ''],
                                        ['name' => 'Vehicle', 'target' => ''],
                                        ['name' => 'Boat', 'target' => ''],
                                        ['name' => 'Motorhome', 'target' => ''],
                                        ['name' => 'Artwork', 'target' => ''],
                                        ['name' => 'Jewelry', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.exchange_items_other_opt'],
                                    ];
                                @endphp
                                <div class="row align-items-end mt-4 ">
                                    <div class="col-md-12">
                                        <label class="fw-bold">Acceptable Exchange Item:</label>
                                        <div class="select2-parent">
                                            <select name="trade[]" class="grid-picker" id="exchangeItem" required>
                                                @foreach ($exchangeItems as $exchangeItem)
                                                    <option value="{{ $exchangeItem['name'] }}"
                                                        data-target="{{ $exchangeItem['target'] }}"
                                                        class="card flex-column " style="width:calc(33.3% - 10px);"
                                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                                        {{ $exchangeItem['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group exchange_items_other_opt d-none">
                                    <label class="fw-bold">Acceptable Exchange Item:</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the estimated value of the acceptable exchange/trade
                                        item?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the condition of the item the buyer is looking to
                                        exchange?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">How much cash will the buyer use on top of the Exchange/Trade
                                        item?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                            </div>
                            {{-- exchange/trade --}}
                            {{-- Conventional --}}
                            <div class="form-group" id="conventionalOptions" style="display: none;">
                                @php
                                    $customOptRes = [
                                        [
                                            'target' => '.customOptResYes',
                                            'name' => 'Yes',
                                            'icon' => 'fa-regular fa-check-circle',
                                        ],
                                        [
                                            'target' => '.customOptResNo',
                                            'name' => 'No',
                                            'icon' => 'fa-regular fa-circle-xmark',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Has the buyer been pre-approved for a loan? </label>
                                <select name="conventionalOptions[]" class="grid-picker" id=""
                                    style="justify-content: flex-start;" required>
                                    @foreach ($customOptRes as $item)
                                        <option value="{{ $item['name'] }}"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'
                                            data-target="{{ $item['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group customOptResYes d-none">
                                    <label class="fw-bold">How much is the buyer pre-approved for?</label>
                                    <input type="number" name="financingOptionsConventional[]"
                                        data-type="customOptionsYes" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar-sign">
                                </div>
                                <div class="form-group customOptResNo d-none">
                                    <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                                    <input type="text" name="buyerBudget" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar-sign">
                                    <div class="form-group">
                                        <label class="fw-bold">If not pre-approved yet, does the buyer need a lender?
                                        </label>
                                        @php
                                            $customOptionsYesNo = [
                                                [
                                                    'target' => '',
                                                    'name' => 'Yes',
                                                    'icon' => 'fa-regular fa-check-circle',
                                                ],
                                                [
                                                    'target' => '',
                                                    'name' => 'No',
                                                    'icon' => 'fa-regular fa-circle-xmark',
                                                ],
                                            ];
                                        @endphp
                                        {{-- <label class="fw-bold">Has the buyer been pre-approved for a loan? </label> --}}
                                        <select name="customOptionsYesNo" class="grid-picker" id=""
                                            style="justify-content: flex-start;" required>
                                            @foreach ($customOptionsYesNo as $item)
                                                <option value="{{ $item['name'] }}"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.3% - 10px);">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            {{-- Lease Options --}}
                            <div class="form-group " id="custom_lease_option_res" style="display: none;">
                                <div class="form-group">
                                    <label class="fw-bold">What is the buyer's desired offering price for a lease
                                        option?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What specific terms does the buyer propose for the lease
                                        option?</label><br>
                                    <input type="text" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                                    <input type="text" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the monthly payment amount the buyer is
                                        offering?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What are the specific conditions or requirements outlined by the
                                        buyer for the lease
                                        option?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                @php
                                    $leaseOptionsResOpt = [
                                        [
                                            'icon' => 'fa-regular fa-circle-check',
                                            'name' => 'Yes',
                                            'target' => '.lease_options_res_opt_yes',
                                        ],
                                        [
                                            'icon' => 'fa-regular fa-circle-xmark',
                                            'name' => 'No',
                                            'target' => '',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold mt-3">Will the buyer offer an option fee?</label>
                                <select class="grid-picker" name="leaseOptions[]" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($leaseOptionsResOpt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group lease_options_res_opt_yes d-none">
                                    <label class="fw-bold">How much is the offered option fee?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                            </div>
                            {{-- Lease Options --}}
                            {{-- Lease Purchase --}}
                            <div class="form-group " id="custom_lease_purchase_res" style="display: none;">
                                <div class="form-group">
                                    <label class="fw-bold">What is the buyer's desired offering price for a lease
                                        purchase?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What specific terms does the buyer propose for the lease
                                        purchase?</label><br>
                                    <input type="text" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                                    <input type="text" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the monthly payment amount the buyer is
                                        offering?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What are the specific conditions or requirements outlined by the
                                        buyer for the lease
                                        purchase?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                @php
                                    $leasePurchaseResOpt = [
                                        [
                                            'icon' => 'fa-regular fa-circle-check',
                                            'name' => 'Yes',
                                            'target' => '.lease_options_res_opt_yes',
                                        ],
                                        [
                                            'icon' => 'fa-regular fa-circle-xmark',
                                            'name' => 'No',
                                            'target' => '',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold mt-3">Will the buyer offer an option fee?</label>
                                <select class="grid-picker" name="leasePurchase[]" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($leasePurchaseResOpt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group lease_options_res_opt_yes d-none">
                                    <label class="fw-bold">How much is the offered option fee?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                            </div>
                            {{-- Lease Purchase --}}
                            {{-- Seller  --}}
                            <div class="form-group custom_seller_financing_res d-none">
                                <label class="fw-bold mt-3">Please enter the proposed price and terms that the buyer is
                                    offering to the seller:</label>
                                {{-- Balloon Payment --}}
                                <div class="row">
                                    <div class="col-12 form-group">
                                        <label class="fw-bold" for="buyerBudget">Desired Purchase Price:</label>
                                        <input type="number" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <div class="d-flex justify-content-between aalign-items-center">
                                            <label class="fw-bold" for="downPayment">Desired Down Payment:</label>
                                            <div
                                                class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                                <button type="button" class="select-btn me-1 active"
                                                    data-type="percent">%</button>
                                                <button type="button" class="select-btn" data-type="amount">$</button>
                                            </div>
                                        </div>
                                        <input type="text" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}"
                                            class="form-control has-icon input-changable-icon"
                                            data-icon="fa-solid fa-percent" data-symbol="percent" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label class="fw-bold" for="buyerBudget">Desired Seller Financing
                                                Amount:</label>
                                            <div
                                                class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                                <button type="button" class="select-btn me-1 active"
                                                    data-type="amount">$</button>
                                                <button type="button" class="select-btn" data-type="percent">%</button>
                                            </div>
                                        </div>
                                        <input type="number" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}"
                                            class="form-control has-icon input-changable-icon"
                                            data-icon="fa-solid fa-dollar-sign" data-symbol="amount" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold" for="interestRate">Desired Interest Rate:</label>
                                        <input type="number" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-percent" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold" for="loanDuration">Desired Loan Duration:</label>
                                        <input type="text" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-regular fa-calendar-days" />
                                    </div>
                                </div>
                                {{-- PrePayement --}}
                                @php
                                    $PrepaymentRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.prepaymentOtherRes',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        ],
                                        [
                                            'name' => 'No',
                                            'target' => '',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        ],
                                    ];
                                @endphp
                                <div class="select2-parent">
                                    <br><label class="fw-bold">Prepayment Penalty: </label>
                                    <select name="prepayment" class="grid-picker" id="">
                                        @foreach ($PrepaymentRes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                                data-icon='{{ $item['icon'] }}'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group prepaymentOtherRes d-none ">
                                        <label class="fw-bold">What is the prepayment penalty amount? </label>
                                        <input type="number" name="prepaymentOther[]" data-type="custom_timeframe"
                                            id="" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" required>
                                    </div>
                                </div>
                                {{-- Prepayment --}}
                                {{-- Balloon Payment --}}
                                @php
                                    $balloonRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.balloonpaymentOtherRes',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        ],
                                        [
                                            'name' => 'No',
                                            'target' => '',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        ],
                                    ];
                                @endphp
                                <div class="select2-parent">
                                    <br><label class="fw-bold">Balloon Payment:</label>
                                    <select name="balloon" class="grid-picker" id="">
                                        @foreach ($balloonRes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                                data-icon='{{ $item['icon'] }}'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group balloonpaymentOtherRes d-none ">
                                        <label class="fw-bold">How much is the balloon payment? </label>
                                        <input type="number" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign"
                                            data-type="fa-solid fa-dollar-sign" id=""
                                            class="form-control has-icon" required>
                                        <label class="fw-bold">When is the balloon payment due? </label>
                                        <input type="text" name="balloonpyment[]"
                                            data-icon="fa-regular fa-calendar-days"
                                            data-type="fa-regular fa-calendar-days" id=""
                                            class="form-control has-icon" required>
                                    </div>
                                </div>
                            </div>
                            {{-- Assumable --}}
                            <div class="form-group custom_assumable_res d-none">
                                <div class="row">
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What assumable terms are being offered?</label>
                                        <input type="text" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-ruler-combined" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What is the maximum interest rate of the assumable loan that
                                            the buyer is seeking?</label>
                                        <input type="text" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-percent" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What is the maximum monthly payment that the buyer is
                                            seeking, including principal
                                            and interest, for the assumable loan?</label>
                                        <input type="text" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What is the down payment that the buyer can afford to pay
                                            the seller to bridge the gap
                                            between the asking price and the assumable loan balance?</label>
                                        <input type="number" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" />
                                    </div>
                                </div>
                            </div>
                            {{-- Assumable --}}
                            {{-- Cryptcurrency --}}
                            <div class="form-group custom_cryptocurrency_res d-none ">
                                <div class="form-group">
                                    <label class="fw-bold">What type of Cryptocurrency does the buyer have?</label><br>
                                    <input type="text" name="cryptocurrency[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What percentage of the sales price will be used with
                                        cryptocurrency?</label><br>
                                    <input type="text" name="cryptocurrency[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-percent">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What percentage of the sales price will be used with
                                        cash?</label><br>
                                    <input type="text" name="cryptocurrency[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-percent">
                                </div>
                                <label class="">Note: Cryptocurrency can be converted to cash at closing.</label>
                            </div>
                            {{-- Cryptcurrency --}}
                            {{-- NFT --}}
                            <div class="form-group nft d-none ">
                                <label class="fw-bold">What type of Non-Fungible Token (NFT) is the buyer
                                    offering?</label><br>
                                <input type="text" name="nft[]" class="form-control has-icon"
                                    data-icon="fa-regular fa-check-circle">
                                <label class="fw-bold">What percentage of the sales price will the buyer offer as an
                                    Non-Fungible Token (NFT)?</label><br>
                                <input type="text" name="nft[]" class="form-control has-icon"
                                    data-icon="fa-solid fa-percent">
                                <label class="fw-bold">What percentage of the sales price will the buyer offer as
                                    cash?</label><br>
                                <input type="text" name="nft[]" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar">
                            </div>
                            {{-- NFT --}}
                            {{-- Desired Price and Terms: --}}
                            <div class="form-group">
                                <div class="d-flex justify-content-between aalign-items-center">
                                    <label class="fw-bold mt-4">Offered Escrow Amount:</label>
                                    <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                        <button type="button" class="select-btn me-1 active"
                                            data-type="amount">$</button>
                                        <button type="button" class="select-btn" data-type="percent">%</button>
                                    </div>
                                </div>
                                <input type="number" name="escrow_amount" id="escrow_amount"
                                    class="form-control has-icon input-changable-icon" data-icon="fa-solid fa-dollar-sign"
                                    data-symbol="amount" required>
                            </div>
                            <div class="form-group">
                                @php
                                    $contingencyRes = [
                                        ['name' => 'Inspection', 'target' => '.inspectionRes'],
                                        ['name' => 'Appraisal', 'target' => '.appraisalRes'],
                                        ['name' => 'Financing', 'target' => '.financingRes'],
                                        ['name' => 'Sale of a prior property', 'target' => '.saleRes'],
                                        ['name' => 'Other', 'target' => '.contOtherRes'],
                                    ];
                                @endphp
                                <label class="fw-bold">Offered Contingencies:</label>
                                <select class="grid-picker" name="contingencies[]" id="contingencies" style=""
                                    multiple required>
                                    <option value="">Select</option>
                                    @foreach ($contingencyRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group inspectionRes d-none">
                                    <label class="fw-bold">Inspection (days):</label>
                                    <input type="number" name="contingenciesOptRes" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group appraisalRes d-none">
                                    <label class="fw-bold">Appraisal (days): </label>
                                    <input type="number" name="contingenciesOptRes" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group financingRes d-none">
                                    <label class="fw-bold">Financing (days):</label>
                                    <input type="number" name="contingenciesOptRes" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group saleRes d-none">
                                    <label class="fw-bold">Sale of a Prior Property (days):</label>
                                    <input type="number" name="contingenciesOffered" id="custom_contingencies"
                                        class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group contOtherRes d-none">
                                    <label class="fw-bold">Offered Contingency:</label>
                                    <input type="number" name="contingenciesOffered" id="custom_contingencies"
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                    <label class="fw-bold">Offered Contingency (days):</label>
                                    <input type="number" name="contingenciesOfferDays" id="custom_contingencies"
                                        class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">Days Needed to Close:</label>
                                <input type="number" name="closeDays" id="closing_days" placeholder=""
                                    class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                            </div>
                            <div class="form-group">
                                <label for="address" class="fw-bold">Ideal Closing Date: </label>
                                <input type="date" name="idealDate" id="ideal_move_in_date"
                                    class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                            </div>
                            <div class="form-group">
                                @php
                                    $creditRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.creditOptYesRes',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        ],
                                        [
                                            'name' => 'No',
                                            'target' => '',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Does the buyer want to ask the seller for a credit at closing?
                                </label>
                                <select class="grid-picker" name="creditRes" id="contingencies" required>
                                    <option value="">Select</option>
                                    @foreach ($creditRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='{{ $item['icon'] }}'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group creditOptYesRes d-none">
                                    <div class="d-flex justify-content-between aalign-items-center">
                                        <label for="address" class="fw-bold">What is the credit amount that the buyer
                                            would
                                            like to request from the seller?</label>
                                        <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                            <button type="button" class="select-btn me-1 active"
                                                data-type="amount">$</button>
                                            <button type="button" class="select-btn" data-type="percent">%</button>
                                        </div>
                                    </div>
                                    <input type="number" name="creditOptYes" id="ideal_move_in_date"
                                        class="form-control has-icon input-changable-icon"
                                        data-icon="fa-solid fa-dollar-sign" data-symbol="amount" required>
                                </div>
                            </div>
                            {{-- Desired Price and Terms: --}}
                        </span>
                    </div>
                    <div class="wizard-step" data-step="9">
                        @php
                            $bedroomsRes = [
                                ['target' => '', 'name' => '1'],
                                ['target' => '', 'name' => '2'],
                                ['target' => '', 'name' => '3'],
                                ['target' => '', 'name' => '4'],
                                ['target' => '', 'name' => '5'],
                                ['target' => '', 'name' => '6'],
                                ['target' => '', 'name' => '7'],
                                ['target' => '', 'name' => '8'],
                                ['target' => '', 'name' => '9'],
                                ['target' => '', 'name' => '10'],
                                ['target' => '.custom_bedrooms', 'name' => 'Other'],
                            ];

                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Minimum Bedrooms Needed:</label>
                            <select class="grid-picker" name="bedrooms" id="bedrooms" style="" required>
                                <option value="">Select</option>
                                @foreach ($bedroomsRes as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(10% - 10px);"
                                        data-icon='<i class="fa-solid fa-bed"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_bedrooms d-none">
                            <labe class="fw-bold">Minimum Bedrooms Needed:</labe>
                            <input type="number" name="custom_bedrooms" id="custom_bedrooms"
                                class="form-control has-icon" data-icon="fa-solid fa-bed" required>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="10">
                        @php
                            $bathroomsRes = [
                                ['target' => '', 'name' => '1'],
                                ['target' => '', 'name' => '1.5'],
                                ['target' => '', 'name' => '2'],
                                ['target' => '', 'name' => '2.5'],
                                ['target' => '', 'name' => '3'],
                                ['target' => '', 'name' => '3.5'],
                                ['target' => '', 'name' => '4'],
                                ['target' => '', 'name' => '4.5'],
                                ['target' => '', 'name' => '5'],
                                ['target' => '', 'name' => '5.5'],
                                ['target' => '', 'name' => '6'],
                                ['target' => '', 'name' => '6.5'],
                                ['target' => '', 'name' => '7'],
                                ['target' => '', 'name' => '7.5'],
                                ['target' => '', 'name' => '8'],
                                ['target' => '', 'name' => '8.5'],
                                ['target' => '', 'name' => '9'],
                                ['target' => '', 'name' => '9.5'],
                                ['target' => '', 'name' => '10'],
                                ['target' => '.bathOtherRes', 'name' => 'Other'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Minimum Bathrooms Needed:</label>
                            <select class="grid-picker" name="bathroomsRes" id="" style="" required>
                                <option value="">Select</option>
                                @foreach ($bathroomsRes as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(10% - 10px);"
                                        data-icon='<i class="fa-solid fa-bath"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group bathOtherRes d-none">
                                <label class="fw-bold">Minimum Bathrooms Needed:</label>
                                <input type="number" name="custom_bathroomsRes" id="custom_bathrooms"
                                    class="form-control has-icon" data-icon="fa-solid fa-bath" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="11">
                        <h4>Income Property Criteria: Please Enter the Buyer's Preferred Income Property Criteria:</h4>
                        <div class="form-group">
                            <label class="fw-bold">Minimum Total Number of Units Needed:</label>
                            <input type="number" name="total_number_of_units" id="total_number_of_units"
                                class="form-control has-icon" data-icon="fa-solid fa-hotel" required>
                        </div>
                        <div class="form-group">
                            @php
                                $unit_types = [
                                    ['name' => '1 Bed/1 Bath', 'target' => ''],
                                    ['name' => '1 Bedroom', 'target' => ''],
                                    ['name' => '2 Bed/1 Bath', 'target' => ''],
                                    ['name' => '2 Bed/2 Bath', 'target' => ''],
                                    ['name' => '2 Bedroom', 'target' => ''],
                                    ['name' => '3 Bed/1 Bath', 'target' => ''],
                                    ['name' => '3 Bed/2 Bath', 'target' => ''],
                                    ['name' => '3 Bedroom', 'target' => ''],
                                    ['name' => '4 Bedroom or More', 'target' => ''],
                                    ['name' => '4+ Bed/1 Bath', 'target' => ''],
                                    ['name' => '4+ Bed/2 Bath', 'target' => ''],
                                    ['name' => 'Apartments', 'target' => ''],
                                    ['name' => 'Efficiency', 'target' => ''],
                                    ['name' => 'Loft', 'target' => ''],
                                    ['name' => "Manager's Unit", 'target' => ''],
                                    ['name' => 'Multi-Level', 'target' => ''],
                                    ['name' => 'Penthouse', 'target' => ''],
                                    ['name' => 'Studio', 'target' => ''],
                                    ['name' => 'Other', 'target' => '.otherBuyerIncome'],
                                ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Please select the unit sizes that the buyer is interested in
                                    purchasing:</label>
                                <select class="grid-picker" name="unit_size[]" id="condition_interested" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($unit_types as $unity_type)
                                        <option value="{{ $unity_type['name'] }}" class="card flex-row"
                                            data-target="{{ $unity_type['target'] }}" style="width:calc(50% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $unity_type['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group otherBuyerIncome d-none">
                                    <label class="fw-bold">Please Enter the Buyer's Preferred Income Property
                                        Criteria:</label>
                                    <input type="text" name="otherBuyerIncome" id="minimum_annual_net_income"
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Minimum Annual Net Income:</label>
                                <input type="number" name="minimum_annual_net_income" id="minimum_annual_net_income"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Minimum Cap Rate: </label>
                                <input type="number" name="minimum_cap_rate" id="minimum_cap_rate"
                                    class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold"> Additional Details:</label>
                                <input type="text" name="additional_details" id="additional_details"
                                    class="form-control has-icon" data-icon="" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="12">
                        <div class="form-group">
                            <label class="fw-bold">Minimum Heated Sqft Needed: </label>
                            <input type="number" name="min_sqftRes" id="min_sqft" class="form-control has-icon"
                                data-icon="fa-solid fa-ruler-combined">
                        </div>
                    </div>
                    <div class="wizard-step" data-step="13">
                        <span class="resFields">
                            @php
                                $minimum_total_acreage_needed = [
                                    ['target' => '', 'name' => '0 to less than 1/4'],
                                    ['target' => '', 'name' => '1/4 to less than 1/2'],
                                    ['target' => '', 'name' => '1/2 to less than 1'],
                                    ['target' => '', 'name' => '1 to less than 2'],
                                    ['target' => '', 'name' => '2 to less than 5'],
                                    ['target' => '', 'name' => '5 to less than 10'],
                                    ['target' => '', 'name' => '10 to less than 20'],
                                    ['target' => '', 'name' => '20 to less than 50'],
                                    ['target' => '', 'name' => '50 to less than 100'],
                                    ['target' => '', 'name' => '100 to less than 200'],
                                    ['target' => '', 'name' => '200 to less than 500'],
                                    ['target' => '', 'name' => '500+ Acres'],
                                    ['target' => '', 'name' => 'Non-Applicable'],
                                ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Minimum Total Acreage Needed:</label>
                                <select class="grid-picker" name="minimum_total_acreage_needed" style="" required>
                                    <option value="">Select</option>
                                    @foreach ($minimum_total_acreage_needed as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="14">
                        @php
                            $carportOpt = [
                                ['name' => 'Yes', 'target' => '.carportOptYes', 'icon' => 'fa-solid fa-warehouse'],
                                ['name' => 'No', 'target' => '', 'icon' => 'fa-solid fa-warehouse'],
                                ['name' => 'Optional', 'target' => '', 'icon' => 'fa-solid fa-warehouse'],
                            ];
                        @endphp
                        <div class="form-grou">
                            <label class="fw-bold">Carport Needed:</label>
                            <select class="grid-picker" name="carport" id="carport"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($carportOpt as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group carportOptYes d-none">
                                <label class="fw-bold">Carport Spaces Needed:</label>
                                <input type="number" name="carportOptYes" class="form-control has-icon"
                                    data-icon="fa-solid fa-warehouse" required>
                            </div>
                        </div>
                        <div class="form-group">
                            @php
                                $garageOptRes = [
                                    ['name' => 'Yes', 'target' => '.garageRes', 'icon' => 'fa-solid fa-warehouse'],
                                    ['name' => 'No', 'target' => '', 'icon' => 'fa-solid fa-warehouse'],
                                    ['name' => 'Optional', 'target' => '', 'icon' => 'fa-solid fa-warehouse'],
                                ];
                            @endphp
                            <label class="fw-bold">Garage Needed: </label>
                            <select class="grid-picker" name="garage_Res" id="pool"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($garageOptRes as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            @php
                                $garage_spaces = [
                                    ['target' => '', 'name' => '1'],
                                    ['target' => '', 'name' => '2'],
                                    ['target' => '', 'name' => '3'],
                                    ['target' => '', 'name' => '4'],
                                    ['target' => '', 'name' => '5'],
                                    ['target' => '', 'name' => '6'],
                                    ['target' => '', 'name' => '7'],
                                    ['target' => '', 'name' => '8'],
                                    ['target' => '', 'name' => '9'],
                                    ['target' => '', 'name' => '10+'],
                                    ['target' => '.custom_garage', 'name' => 'Other'],
                                ];
                            @endphp
                            <div class="form-group garageRes d-none">
                                <label class="fw-bold">Garage Spaces Needed:</label>
                                <select class="grid-picker" name="garage_spaces" id="garage_spaces"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($garage_spaces as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column " style="width:calc(10% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group custom_garage d-none">
                                    <label class="fw-bold">Garage Spaces Needed:</label>
                                    <input type="number" name="custom_garage" class="form-control has-icon"
                                        data-icon="fa-solid fa-warehouse" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="15">
                        <div class="form-group">
                            @php
                                $poolOptRes = [
                                    [
                                        'name' => 'Yes',
                                        'target' => '.poolOptYes',
                                        'icon' => 'fa-regular fa-circle-check',
                                    ],
                                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                    ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
                                ];
                            @endphp
                            <label class="fw-bold">Pool Needed:</label>
                            <select class="grid-picker" name="pool" id="pool"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($poolOptRes as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row " style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group poolOptYes d-none">
                                @php
                                    $poolOption = [
                                        ['name' => 'Private', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                        ['name' => 'Community', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                    ];
                                @endphp
                                <select class="grid-picker" name="poolOption" id="pool"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($poolOption as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row " style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            @php
                                $waterPreferenceRes = [
                                    [
                                        'name' => 'Yes',
                                        'target' => '.waterPreferenceRes',
                                        'icon' => 'fa-regular fa-circle-check',
                                    ],
                                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                    [
                                        'name' => 'Optional',
                                        'target' => '',
                                        'icon' => 'fa-regular fa-circle-question',
                                    ],
                                ];
                            @endphp
                            <label class="fw-bold">View Preference Needed:</label>
                            <select class="grid-picker" name="viewOptionsRes" id="waterPreferenceRes"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($waterPreferenceRes as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group" id="waterPreferenceResOpt" style="display: none">
                                @php
                                    $view = [
                                        ['name' => 'City', 'target' => ''],
                                        ['name' => 'Garden', 'target' => ''],
                                        ['name' => 'Golf Course', 'target' => ''],
                                        ['name' => 'Greenbelt', 'target' => ''],
                                        ['name' => 'Mountain(s)', 'target' => ''],
                                        ['name' => 'Park', 'target' => ''],
                                        ['name' => 'Pool', 'target' => ''],
                                        ['name' => 'Tennis Court', 'target' => ''],
                                        ['name' => 'Trees/Woods', 'target' => ''],
                                        ['name' => 'Water', 'target' => ''],
                                        ['name' => 'Beach', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.viewOtherRes'],
                                    ];
                                @endphp
                                <select class="grid-picker" name="view[]" id="view"
                                    style="justify-content: flex-start;" multiple>
                                    <option value="">Select</option>
                                    @foreach ($view as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group viewOtherRes d-none">
                                    <label class="fw-bold">View Preference: </label>
                                    <input type="text" name="viewOther" id="" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="16">
                        <span class="resFields">
                            <div class="form-group">
                                @php
                                    $waterAccessRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterAccessRes',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water Access Needed:</label>
                                <select class="grid-picker" name="has_water_access" id="waterAccessRes"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterAccessRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="waterAccessResOpt" style="display: none;">
                                    @php
                                        $water_access = [
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
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="water_access[]" id="water_access"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($water_access as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group ">
                                @php
                                    $waterViewRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterViewRes',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water View Needed:</label>
                                <select class="grid-picker" name="has_water_view" id="waterViewRes"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterViewRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="waterViewResOpt" style="display: none">
                                    @php
                                        $water_views = [
                                            ['name' => 'Bay/Harbor - Full', 'target' => ''],
                                            ['name' => 'Bay/Harbor - Partial', 'target' => ''],
                                            ['name' => 'Bayou', 'target' => ''],
                                            ['name' => 'Beach', 'target' => ''],
                                            ['name' => 'Canal', 'target' => ''],
                                            ['name' => 'Creek', 'target' => ''],
                                            ['name' => 'Gulf/Ocean - Full', 'target' => ''],
                                            ['name' => 'Gulf/Ocean - Partial', 'target' => ''],
                                            ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
                                            ['name' => 'Intracoastal Waterway', 'target' => ''],
                                            ['name' => 'Lagoon/Estuary', 'target' => ''],
                                            ['name' => 'Lake', 'target' => ''],
                                            ['name' => 'Lake - Chain of Lakes', 'target' => ''],
                                            ['name' => 'Marina', 'target' => ''],
                                            ['name' => 'Pond', 'target' => ''],
                                            ['name' => 'River', 'target' => ''],
                                            ['name' => 'None', 'target' => ''],
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="water_view[]" id="water_view"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($water_views as $water_view)
                                            <option value="{{ $water_view['name'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                data-target="{{ $water_view['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $water_view['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                @php
                                    $waterExtraRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterExtraRes',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water Extras Needed:</label>
                                <select class="grid-picker" name="has_water_extra" id="waterExtraRes"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterExtraRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="waterExtraResOpt" style="display: none;">
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
                                            ['name' => 'None', 'target' => ''],
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="water_extras[]" id="water_extras"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($water_extras as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                @php
                                    $waterFrontageRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterFrontageRes',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water Frontage Needed:</label>
                                <select class="grid-picker" name="has_water_frontage" id="waterFrontageRes"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterFrontageRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="waterFrontageResOpt" style="display: none">
                                    @php
                                        $water_frontage = [
                                            ['name' => 'Bay/Harbor', 'target' => ''],
                                            ['name' => 'Bayou', 'target' => ''],
                                            ['name' => 'Beach', 'target' => ''],
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
                                            ['name' => 'Marina', 'target' => ''],
                                            ['name' => 'Pond', 'target' => ''],
                                            ['name' => 'River', 'target' => ''],
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="water_frontage[]" id="water_frontage"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($water_frontage as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group ">
                                @php
                                    $dockRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.dockRes',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '.dockRes',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Dock Needed:</label>
                                <select class="grid-picker" name="has_dock" id="hasDock"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($dockRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="dockRes" style="display: none">
                                    @php
                                        $dockRes = [
                                            ['name' => '2 Point Moorage', 'target' => ''],
                                            ['name' => '3 Point Moorage', 'target' => ''],
                                            ['name' => '4 Point Moorage', 'target' => ''],
                                            ['name' => 'CATV', 'target' => ''],
                                            ['name' => 'Clubhouse', 'target' => ''],
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
                                            ['name' => 'Dock w/Water Supply', 'target' => ''],
                                            ['name' => 'Dock w/o Water Supply', 'target' => ''],
                                            ['name' => 'Dry Dock', 'target' => ''],
                                            ['name' => 'Fish Cleaning Station', 'target' => ''],
                                            ['name' => 'Floating Dock', 'target' => ''],
                                            ['name' => 'Harbormaster', 'target' => ''],
                                            ['name' => 'Internet', 'target' => ''],
                                            ['name' => 'Lift', 'target' => ''],
                                            ['name' => 'Restroom/Shower', 'target' => ''],
                                            ['name' => 'Wet Dock', 'target' => ''],
                                            ['name' => 'None', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.dock_res_other_opt'],
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="dock[]" id="dock"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($dockRes as $item)
                                            <option value="{{ $item['name'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group dock_res_other_opt d-none">
                                    <label class="fw-bold">Dock Description Needed:</label>
                                    <input type="text" name="dockDescription" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                            </div>
                            {{-- <div class="form-group">
                                @php
                                    $waterPreferenceRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterPreferenceRes',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">View Preference Needed:</label>
                                <select class="grid-picker" name="viewOptions" id="waterPreferenceRes"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterPreferenceRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="waterPreferenceResOpt" style="display: none">
                                    @php
                                        $view = [
                                            ['name' => 'City', 'target' => ''],
                                            ['name' => 'Garden', 'target' => ''],
                                            ['name' => 'Golf Course', 'target' => ''],
                                            ['name' => 'Greenbelt', 'target' => ''],
                                            ['name' => 'Mountain(s)', 'target' => ''],
                                            ['name' => 'Park', 'target' => ''],
                                            ['name' => 'Pool', 'target' => ''],
                                            ['name' => 'Tennis Court', 'target' => ''],
                                            ['name' => 'Trees/Woods', 'target' => ''],
                                            ['name' => 'Water', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.viewOtherRes'],
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="view[]" id="view"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($view as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group viewOtherRes d-none">
                                        <label class="fw-bold">View Preference: </label>
                                        <input type="text" name="viewOther" id=""
                                            class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                                    </div>
                                </div>
                            </div> --}}
                        </span>
                    </div>
                    <div class="wizard-step" data-step="17">
                        @php
                            $yes_or_nos1 = [
                                ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Does the buyer have pets?</label>
                            <select class="grid-picker" name="petOptions" id="pool"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos1 as $yes_or_no)
                                    @php
                                        if ($yes_or_no['name'] == 'Yes') {
                                            $target = '.have_pet';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                        {{ $yes_or_no['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row have_pet">
                            <div class="form-group">
                                <label class="fw-bold">How Many Pet(s) Does The Buyer Have?</label>
                                <input type="text" name="petsNumber" id="how_many_pets"
                                    class="form-control has-icon" data-icon="fa-solid fa-dog" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">What Type Of Pet(s) Does The Buyer Have?</label>
                                <input type="text" name="petsType" id="type_of_pet" class="form-control has-icon"
                                    data-icon="fa-solid fa-dog" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">What breed(s) are the pet(s)?</label>
                                <input type="text" name="pet_breed" id="pet_breed" class="form-control has-icon"
                                    data-icon="fa-solid fa-dog" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">What Is The Weight Of Pet(s)?</label>
                                <input type="text" name="petsWeight" id="pet_weight"
                                    class="form-control has-icon" data-icon="fa-solid fa-dog" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="18">
                        <span class="resFields">
                            @php
                                $yes_or_nos1 = [
                                    ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Does the buyer have any preferences for Air Conditioning and
                                    Heating/Fuel for the property?</label>
                                <select class="grid-picker" name="have_air_conditioning" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos1 as $yes_or_no)
                                        @php
                                            if ($yes_or_no['name'] == 'Yes') {
                                                $target = '.conditioningRes';
                                            } else {
                                                $target = '';
                                            }
                                        @endphp
                                        <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                            {{ $yes_or_no['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row conditioningRes">
                                @php
                                    $air_conditioning = [
                                        ['name' => 'Central Air', 'target' => ''],
                                        ['name' => 'Humidity Control', 'target' => ''],
                                        ['name' => 'Mini-Split Unit(s)', 'target' => ''],
                                        ['name' => 'None', 'target' => ''],
                                        ['name' => 'Wall/Window Unit(s)', 'target' => ''],
                                        ['name' => 'Zoned', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.otherAirConditionRes'],
                                    ];
                                @endphp
                                <div class="form-group ">
                                    <label class="fw-bold">Air Conditioning:</label>
                                    <select class="grid-picker" name="air_conditioning" id=""
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($air_conditioning as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group otherAirConditionRes d-none">
                                        <label class="fw-bold">Air Conditioning:
                                        </label>
                                        <input type="text" name="otherAirCondition" id="non_negotiable_factors"
                                            class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                                            required>
                                    </div>
                                </div>
                                @php
                                    $heating_and_fuel = [
                                        ['name' => 'Baseboard', 'target' => ''],
                                        ['name' => 'Central', 'target' => ''],
                                        ['name' => 'Electric', 'target' => ''],
                                        ['name' => 'Exhaust Fans', 'target' => ''],
                                        ['name' => 'Gas', 'target' => ''],
                                        ['name' => 'Heat Pump', 'target' => ''],
                                        ['name' => 'Heat Recovery Unit', 'target' => ''],
                                        ['name' => 'Natural Gas', 'target' => ''],
                                        ['name' => 'Oil', 'target' => ''],
                                        ['name' => 'Partial', 'target' => ''],
                                        ['name' => 'Propane', 'target' => ''],
                                        ['name' => 'Radiant Ceiling', 'target' => ''],
                                        ['name' => 'Reverse Cycle', 'target' => ''],
                                        ['name' => 'Solar', 'target' => ''],
                                        ['name' => 'Space Heater', 'target' => ''],
                                        ['name' => 'Wall Furnace', 'target' => ''],
                                        ['name' => 'Wall Units / Window Unit', 'target' => ''],
                                        ['name' => 'Zoned', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.otherFuelRes'],
                                        ['name' => 'None', 'target' => ''],
                                    ];
                                @endphp
                                <div class="form-group ">
                                    <label class="fw-bold">Heating and Fuel:</label>
                                    <select class="grid-picker" name="heating_and_fuel" id=""
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($heating_and_fuel as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group otherFuelRes d-none">
                                        <label class="fw-bold">Heating and Fuel:
                                        </label>
                                        <input type="text" name="otherFuel" id="non_negotiable_factors"
                                            class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                                            required>
                                    </div>
                                </div>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="19">
                        <div class="form-group">
                            <label class="fw-bold">Is the buyer eligible/interested in purchasing a property in 55+ and
                                over communities?</label>
                            <select class="grid-picker" name="communitiesOption" id="hoa_community"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        {{-- <div class="row eligiableRes d-none">
                            <div class="form-group has_intrested">
                                <label class="fw-bold">What is the maximum monthly condo and/or HOA fee that the buyer can
                                    afford?</label>
                                <input type="number" name="communities" id="" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar-sign" required>
                            </div>
                        </div> --}}
                    </div>
                    <div class="wizard-step" data-step="20">
                        <span class="resFields">
                            <div class="form-group">
                                @php
                                    $communityOption = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.communityYesRes',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        ],
                                        [
                                            'name' => 'No',
                                            'target' => '',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Would the buyer be interested in purchasing in a community with an
                                    HOA/Condo/Community
                                    association?</label>
                                <select class="grid-picker" name="communitiesOption" id="hoa_community"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($communityOption as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon="{{ $item['icon'] }}">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group communityYesRes d-none">
                                    <label class="fw-bold">What is the maximum monthly condo and/or HOA fee that the buyer
                                        will accept?</label>
                                    <input type="number" name="communityYes" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                                </div>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="21">
                        <span class="resFields">
                            <div class="form-group">
                                <label class="fw-bold">Please provide a detailed description of the buyer’s specific
                                    requirements and preferences for
                                    buying a property:</label><br>
                                <textarea name="description_buyer_specific" id="buyer_specific_requirements" class="form-control" cols="15"
                                    rows="5" required></textarea>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="22">
                        <div class="form-group">
                            <label class="fw-bold">Are there any non-negotiable amenities or property features that the
                                buyer is seeking?</label>
                            <select class="grid-picker" name="nonNegotiableFactorsRes" id="any_non_negotiable_factors"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.has_non_negotiable_factors';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group d-none has_non_negotiable_factors">
                                @php
                                    $negotiableRes = [
                                        ['name' => 'Garage', 'target' => ''],
                                        ['name' => 'Carport', 'target' => ''],
                                        ['name' => 'Pool', 'target' => ''],
                                        ['name' => 'Waterfront', 'target' => ''],
                                        ['name' => 'In-Unit Laundry', 'target' => ''],
                                        ['name' => 'On-site Laundry', 'target' => ''],
                                        ['name' => 'Washer and Dryer Hookup', 'target' => ''],
                                        ['name' => 'Washer and Dryer', 'target' => ''],
                                        ['name' => 'Covered Carport', 'target' => ''],
                                        ['name' => 'First Floor Unit', 'target' => ''],
                                        ['name' => 'Elevator', 'target' => ''],
                                        ['name' => 'Pet Friendly', 'target' => ''],
                                        ['name' => 'Balcony/Patio', 'target' => ''],
                                        ['name' => 'Fitness Center/Gym', 'target' => ''],
                                        ['name' => 'Central Heating', 'target' => ''],
                                        ['name' => 'Central Air Conditioning', 'target' => ''],
                                        ['name' => 'Fireplace', 'target' => ''],
                                        ['name' => 'Walk-in Closet', 'target' => ''],
                                        ['name' => 'Hardwood Floors', 'target' => ''],
                                        ['name' => 'Tile Floors', 'target' => ''],
                                        ['name' => 'Carpet Floors', 'target' => ''],
                                        ['name' => 'Security System', 'target' => ''],
                                        ['name' => 'Gated Community', 'target' => ''],
                                        ['name' => 'HOA Community', 'target' => ''],
                                        ['name' => '55 and Over Community', 'target' => ''],
                                        ['name' => 'Specific School District', 'target' => ''],
                                        ['name' => 'Accessibility Features', 'target' => ''],
                                        ['name' => 'On-site Maintenance', 'target' => ''],
                                        ['name' => 'On-site Management', 'target' => ''],
                                        ['name' => 'Outdoor Space', 'target' => ''],
                                        ['name' => 'Playground', 'target' => ''],
                                        ['name' => 'Clubhouse', 'target' => ''],
                                        ['name' => 'Storage Space', 'target' => ''],
                                        ['name' => 'Study/Den/Office', 'target' => ''],
                                        ['name' => 'Updated Kitchen', 'target' => ''],
                                        ['name' => 'Updated Bathroom', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.negotiableOtherRes'],
                                    ];

                                @endphp
                                <select class="grid-picker" name="nonNegotiable[]" id=""
                                    style="justify-content: flex-start;" multiple>
                                    <option value="">Select</option>
                                    @foreach ($negotiableRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group negotiableOtherRes d-none">
                                    <label class="fw-bold">What non-negotiable amenities or property features is the buyer
                                        is seeking?
                                    </label>
                                    <input type="text" name="negotiableOther" id="non_negotiable_factors"
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="23">
                        <label class="fw-bold">Buyer’s Agent Representation:</label>
                        <div class="form-group">
                            <label class="fw-bold">Does the buyer have a real estate agent representing them?</label>
                            <select class="grid-picker" name="buyerHaveAgentRepresentationRes" id=""
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.buyers_agent_commission';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group d-none buyers_agent_commission">
                                <label class="fw-bold">Does the buyer request that the seller pay the buyer’s agent
                                    commission?</label>
                                <select class="grid-picker" name="buyersAgentCommissionRequestedRes" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $item)
                                        @php
                                            if ($item['name'] == 'Yes') {
                                                $target = '.buyers_agent_compensation';
                                            } else {
                                                $target = '.buyer_agent_comp_not_offered';
                                            }
                                        @endphp
                                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group d-none buyer_agent_comp_not_offered">
                                <label class="fw-bold">Would the buyer be willing to pay an agent's commission for
                                    bringing a property the
                                    buyer ends up purchasing if the seller does not agree to pay the commission?</label>
                                @php
                                    $buyersAgentCompensationNotOffered = [
                                        ['name' => 'Yes', 'icon' => 'fa-regular fa-circle-check', 'target' => ''],
                                        ['name' => 'No', 'icon' => 'fa-regular fa-circle-xmark', 'target' => ''],
                                    ];
                                @endphp
                                <select class="grid-picker" name="buyersAgentCompensationNotOfferedRes" id=""
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($buyersAgentCompensationNotOffered as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group d-none buyers_agent_compensation">
                                <label class="fw-bold">What compensation is the buyer requesting the seller to pay to the
                                    buyer’s agent?</label>
                                @php
                                    $buyersAgentCompensation = [
                                        ['name' => '2%', 'target' => ''],
                                        ['name' => '2.5%', 'target' => ''],
                                        ['name' => '3%', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.buyers_agent_comp'],
                                    ];
                                @endphp
                                <select class="grid-picker" name="buyersAgentCompensationRequestedRes" id=""
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($buyersAgentCompensation as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group buyers_agent_comp d-none">
                                <div class="d-flex justify-content-between aalign-items-center">
                                    <label class="fw-bold">What compensation is the buyer requesting the seller to pay to
                                        the buyer’s agent?</label>
                                    <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                        <button type="button" class="select-btn me-1 active"
                                            data-type="percent">%</button>
                                        <button type="button" class="select-btn" data-type="amount">$</button>
                                    </div>
                                </div>
                                <input type="text" name="buyersAgentCompensationRequestedAmountRes" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="24">
                        <div class="form-group">
                            <label class="fw-bold">Is the buyer currently selling a property?</label>
                            <select class="grid-picker" name="isBuyerCurrentlySellingPropertyRes" id=""
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.link_to_the_property_listing';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group link_to_the_property_listing d-none">
                                <label class="fw-bold">Link to the Property listing on Bid Your Offer:</label>
                                <input type="text" name="linkToThePropertyListingRes" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-link" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="25">
                        <h4>For a more personalized listing, you can include a picture of yourself and/or include a video of
                            yourself providing additional information about your background and criteria.</h4>
                        <div class="row form-group">
                            <div class="col-6">
                                <label class="fw-bold">First Name:</label>
                                <input type="text" name="agent_first_name" id="first_name" placeholder=""
                                    value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-user" required>
                            </div>
                            <div class="col-6">
                                <label class="fw-bold">Last Name:</label>
                                <input type="text" name="agent_last_name" id="last_name" placeholder=""
                                    value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-user" required>
                            </div>
                        </div>
                        <div class="row form-group">
                            <div class="col-6">
                                <label class="fw-bold">Phone Number:</label>
                                <input type="text" name="agent_phoneRes" id="agent_phone" placeholder=""
                                    value="{{ old('agent_phone', optional(Auth::user())->phone) }}"
                                    class="form-control has-icon" data-icon="fa-solid fa-phone">
                            </div>
                            <div class="col-6">
                                <label class="fw-bold">Email:</label>
                                <input type="text" name="agent_email" id="agent_email" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-envelope"
                                    value="{{ Auth::user()->email }}" required>
                            </div>
                        </div>
                        @if(Auth::user()->user_type == "agent")
                        <div class="row form-group">
                            <div class="col-6">
                                <label class="fw-bold">Brokerage:</label>
                                <input type="text" name="agent_brokerageRes" id="agent_brokerage" placeholder=""
                                    value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-handshake">
                            </div>
                            <div class="col-6">
                                <label class="fw-bold">Real Estate License #: </label>
                                <input type="text" name="license" id="agent_mls_id" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-id-card"
                                    value="{{ optional(Auth::user())->mls_id }}">
                            </div>
                        </div>
                        <div class="row form-group">
                            <div class="col-6">
                                <label class="fw-bold">NAR Member ID (NRDS ID): </label>
                                <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-id-card-clip"
                                    value="{{ optional(Auth::user())->mls_id }}">
                            </div>
                            {{-- <div class="col-6">
                                <label class="fw-bold">Listed By: Real Estate Agent:</label>
                                <input type="text" name="agent_commission_percent" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                            </div> --}}
                        </div>
                        @endif
                        <span class="resFields">
                            <div class="row form-group">
                                <div class="col-6">
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
                                <div class="col-6">
                                    <div class="upload">
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
                        </span>
                    </div>
                    <div class="wizard-step" data-step="26">
                        @php
                            $special_sale = [
                                ['name' => 'Assignment Contract', 'target' => '.assignmentIncome'],
                                ['name' => 'Bank Owned/REO', 'target' => ''],
                                ['name' => 'Probate Listing', 'target' => ''],
                                ['name' => 'Short Sale', 'target' => ''],
                                ['name' => 'Government Owned', 'target' => ''],
                                ['name' => 'Auction', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => '.otherSaleCommercial'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Acceptable Special Sale Provisions:</label>
                            <select class="grid-picker" name="special_sales[]" id=""
                                style="justify-content: flex-start;" required multiple>
                                <option value="">Select</option>
                                @foreach ($special_sale as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group otherSaleCommercial d-none">
                                <label class="fw-bold">Acceptable Special Sale Provisions:</label>
                                <input type="text" name="otherSaleRes" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                                    placeholder="" required>
                            </div>
                            <div class="form-group assignmentIncome d-none">
                                @php
                                    $assignmentResOpt = [
                                        [
                                            'icon' => 'fa-regular fa-circle-check',
                                            'name' => 'Yes',
                                            'target' => '.assignmentYesIncome',
                                        ],
                                        [
                                            'icon' => 'fa-regular fa-circle-xmark',
                                            'name' => 'No',
                                            'target' => '.assignmentNoIncome',
                                        ],
                                    ];

                                    $assignmentResNoOpt = [
                                        [
                                            'icon' => 'fa-regular fa-circle-check',
                                            'name' => 'Yes',
                                            'target' => '.assignmentYesResOptYes',
                                        ],
                                        [
                                            'icon' => 'fa-regular fa-circle-xmark',
                                            'name' => 'No',
                                            'target' => '.assignmentNoResOptNo',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Is the buyer looking to take over another buyer’s contract?</label>
                                <select class="grid-picker" name="specialSaleOpt" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($assignmentResOpt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group assignmentYesIncome d-none">
                                    <div class="d-flex justify-content-between aalign-items-center">
                                        <label class="fw-bold">What fee would the buyer pay the agent for selling an
                                            assignment
                                            contract?</label>
                                        <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                            <button type="button" class="select-btn me-1 active"
                                                data-type="amount">$</button>
                                            <button type="button" class="select-btn" data-type="percent">%</button>
                                        </div>
                                    </div>
                                    <input type="text" name="specialOptYes" id=""
                                        class="form-control has-icon input-changable-icon"
                                        data-icon="fa-solid fa-dollar-sign" data-symbol="amount" placeholder=""
                                        required>
                                </div>
                                <div class="form-group assignmentNoIncome d-none">
                                    {{-- <label class="fw-bold">Is the buyer looking to sell their contract? </label>
                                    <input type="text" name="specialOptNo" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                                        placeholder="" required> --}}
                                    <label class="fw-bold">Is the buyer looking to sell their contract? </label>
                                    {{-- <input type="text" name="specialOptNo" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                                        placeholder="" required> --}}
                                    <select class="grid-picker" name="specialOptNoRes" id=""
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($assignmentResNoOpt as $item)
                                            <option value="{{ $item['name'] }}"
                                                data-target="{{ $item['target'] }}" class="card flex-column"
                                                style="width:calc(25% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <div class="form-group assignmentYesResOptYes d-none">
                                        <div class="d-flex justify-content-between aalign-items-center">
                                            <label class="fw-bold">What fee would the buyer pay the agent for selling
                                                an
                                                assignment contract?</label>
                                            <div
                                                class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                                <button type="button" class="select-btn me-1 active"
                                                    data-type="amount">$</button>
                                                <button type="button" class="select-btn"
                                                    data-type="percent">%</button>
                                            </div>
                                        </div>
                                        <input type="text" name="specialOptYes" id=""
                                            class="form-control has-icon input-changable-icon"
                                            data-icon="fa-solid fa-dollar-sign" data-symbol="amount"
                                            placeholder="" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="27">
                        <span class="commercialFields">
                            <label class="fw-bold">
                                Desired Price and Terms:
                            </label>
                            <div class="form-group">
                                <label class="fw-bold">
                                    Maximum Budget:
                                </label>
                                <input type="number" name="max_price" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar-sign" required>
                            </div>
                            @php
                                $finances = [
                                    ['name' => 'Cash', 'target' => '.cashRes2'],
                                    ['name' => 'Conventional', 'target' => ''],
                                    ['name' => 'Seller Financing', 'target' => '.sellerFinancingCommercial'],
                                    ['name' => 'FHA', 'target' => ''],
                                    ['name' => 'Jumbo', 'target' => ''],
                                    ['name' => 'VA', 'target' => ''],
                                    ['name' => 'No-Doc', 'target' => ''],
                                    ['name' => 'Non-QM', 'target' => ''],
                                    ['name' => 'Assumable', 'target' => '.custom_assumable_res2'],
                                    ['name' => 'Exchange/Trade', 'target' => '.tradeCommercial'],
                                    ['name' => 'Lease Option', 'target' => '.lease_option2'],
                                    ['name' => 'Lease Purchase', 'target' => '.lease_purchase2'],
                                    ['name' => 'Cryptocurrency', 'target' => '.cryptoCommercial'],
                                    ['name' => 'Non-Fungible Token (NFT)', 'target' => '.nftCommercial'],
                                    ['name' => 'USDA', 'target' => ''],
                                    ['name' => 'Other', 'target' => '.financingOtherCommercial'],
                                ];
                            @endphp
                            <div class="row align-items-end mt-4 ">
                                <div class="col-md-12">
                                    <label class="fw-bold">Offered Currency/Financing:</label>
                                    <div class="select2-parent">
                                        <select name="financings[]" class="grid-picker" multiple
                                            id="financingOptionsCommercial" required>
                                            @foreach ($finances as $financing)
                                                <option value="{{ $financing['name'] }}"
                                                    data-target="{{ $financing['target'] }}" class="card flex-column "
                                                    style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                                    {{ $financing['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            {{-- Cash --}}
                            {{-- <div class="form-group cashRes2 d-none ">
                                <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                                <input type="text" name="cash[]" data-type="" id="" class="form-control has-icon"
                                data-icon="fa-solid fa-ruler-combined" >
                            </div> --}}
                            {{-- other --}}
                            <div class="form-group financingOtherCommercial d-none ">

                                <label class="fw-bold">What type of financing/funds will the buyer use to purchase the
                                    property?</label>
                                <input type="text" name="financingOther" data-type="" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                            </div>
                            {{-- other --}}
                            {{-- exchange/trade --}}
                            <div class="form-group tradeCommercial d-none ">
                                @php
                                    $exchangeItems = [
                                        ['name' => 'Another Home', 'target' => ''],
                                        ['name' => 'Vehicle', 'target' => ''],
                                        ['name' => 'Boat', 'target' => ''],
                                        ['name' => 'Motorhome', 'target' => ''],
                                        ['name' => 'Artwork', 'target' => ''],
                                        ['name' => 'Jewelry', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.exchange_items_other_opt2'],
                                    ];
                                @endphp
                                <div class="row align-items-end mt-4 ">
                                    <div class="col-md-12">
                                        <label class="fw-bold">Acceptable Exchange Item:</label>
                                        <div class="select2-parent">
                                            <select name="trade[]" class="grid-picker" id="exchangeItem" required>
                                                @foreach ($exchangeItems as $exchangeItem)
                                                    <option value="{{ $exchangeItem['name'] }}"
                                                        data-target="{{ $exchangeItem['target'] }}"
                                                        class="card flex-column " style="width:calc(33.3% - 10px);"
                                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                                        {{ $exchangeItem['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group exchange_items_other_opt2 d-none">
                                    <label class="fw-bold">Acceptable Exchange Item:</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the estimated value of the acceptable exchange/trade
                                        item?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the condition of the item the buyer is looking to
                                        exchange?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">How much cash will the buyer use on top of the Exchange/Trade
                                        item?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                            </div>
                            {{-- exchange/trade --}}
                            {{-- Conventional --}}
                            <div class="form-group" id="conventionalCommercial" style="display: none;">
                                @php
                                    $customOptRes = [
                                        [
                                            'target' => '.customOptCommercialYes',
                                            'name' => 'Yes',
                                            'icon' => 'fa-regular fa-check-circle',
                                        ],
                                        [
                                            'target' => '.customOptCommercialNo',
                                            'name' => 'No',
                                            'icon' => 'fa-regular fa-circle-xmark',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Has the buyer been pre-approved for a loan? </label>
                                <select name="conventionalOptions[]" class="grid-picker" id=""
                                    style="justify-content: flex-start;" required>
                                    @foreach ($customOptRes as $item)
                                        <option value="{{ $item['name'] }}"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'
                                            data-target="{{ $item['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group customOptCommercialYes d-none">
                                    <label class="fw-bold">How much is the buyer pre-approved for?</label>
                                    <input type="number" name="financingOptionsConventional[]"
                                        data-type="customOptionsYes" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar-sign">
                                </div>
                                <div class="form-group customOptCommercialNo d-none">
                                    <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                                    <input type="number" name="buyerBudget" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar-sign">
                                    <div class="form-group">
                                        <label class="fw-bold">If not pre-approved yet, does the buyer need a lender
                                            recommendation?
                                        </label>
                                        @php
                                            $customOptionsYesNo = [
                                                [
                                                    'target' => '',
                                                    'name' => 'Yes',
                                                    'icon' => 'fa-regular fa-check-circle',
                                                ],
                                                [
                                                    'target' => '',
                                                    'name' => 'No',
                                                    'icon' => 'fa-regular fa-circle-xmark',
                                                ],
                                            ];
                                        @endphp
                                        <label class="fw-bold">Has the buyer been pre-approved for a loan? </label>
                                        <select name="customOptionsYesNo" class="grid-picker" id=""
                                            style="justify-content: flex-start;" required>
                                            @foreach ($customOptionsYesNo as $item)
                                                <option value="{{ $item['name'] }}"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.3% - 10px);">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            {{-- Lease Options --}}
                            <div class="form-group lease_option2" id="leaseOptionCommercial" style="display: none;">
                                <div class="form-group">
                                    <label class="fw-bold">What is the buyer's desired offering price for a lease
                                        option?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What specific terms does the buyer propose for the lease
                                        option?</label><br>
                                    <input type="text" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                                    <input type="text" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the monthly payment amount the buyer is
                                        offering?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What are the specific conditions or requirements outlined by the
                                        buyer for the lease
                                        option?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                @php
                                    $leaseOptionsComOpt = [
                                        [
                                            'icon' => 'fa-regular fa-circle-check',
                                            'name' => 'Yes',
                                            'target' => '.lease_options_com_opt_yes',
                                        ],
                                        [
                                            'icon' => 'fa-regular fa-circle-xmark',
                                            'name' => 'No',
                                            'target' => '',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold mt-3">Will the buyer offer an option fee?</label>
                                <select class="grid-picker" name="leaseOptions[]" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($leaseOptionsComOpt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group lease_options_com_opt_yes d-none">
                                    <label class="fw-bold">How much is the offered option fee?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                            </div>
                            {{-- Lease Options --}}
                             {{-- Lease Purchase --}}
                             <div class="form-group " id="leasePurchaseCommercial" style="display: none;">
                                <div class="form-group">
                                    <label class="fw-bold">What is the buyer's desired offering price for a lease
                                        purchase?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What specific terms does the buyer propose for the lease
                                        purchase?</label><br>
                                    <input type="text" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                                    <input type="text" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the monthly payment amount the buyer is
                                        offering?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What are the specific conditions or requirements outlined by the
                                        buyer for the lease
                                        purchase?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                @php
                                    $leasePurchaseComOpt = [
                                        [
                                            'icon' => 'fa-regular fa-circle-check',
                                            'name' => 'Yes',
                                            'target' => '.lease_options_com_opt_yes',
                                        ],
                                        [
                                            'icon' => 'fa-regular fa-circle-xmark',
                                            'name' => 'No',
                                            'target' => '',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold mt-3">Will the buyer offer an option fee?</label>
                                <select class="grid-picker" name="leasePurchase[]" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($leasePurchaseResOpt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group lease_options_com_opt_yes d-none">
                                    <label class="fw-bold">How much is the offered option fee?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                            </div>
                            {{-- Lease Purchase --}}
                            {{-- Seller  --}}
                            <div class="form-group sellerFinancingCommercial d-none ">
                                <label class="fw-bold mt-3">Please enter the proposed price and terms that the buyer is
                                    offering to the seller:</label>
                                <div class="row">
                                    <div class="col-12 form-group">
                                        <label class="fw-bold" for="buyerBudget">Desired Purchase Price:</label>
                                        <input type="number" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <div class="d-flex justify-content-between aalign-items-center">
                                            <label class="fw-bold" for="buyerBudget">Desired Down Payment:</label>
                                            <div
                                                class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                                <button type="button" class="select-btn me-1 active"
                                                    data-type="percent">%</button>
                                                <button type="button" class="select-btn" data-type="amount">$</button>
                                            </div>
                                        </div>
                                        <input type="text" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-percent" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label class="fw-bold" for="downPayment">Desired Seller Financing
                                                Amount:</label>
                                            <div
                                                class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                                <button type="button" class="select-btn me-1 active"
                                                    data-type="amount">$</button>
                                                <button type="button" class="select-btn" data-type="percent">%</button>
                                            </div>
                                        </div>
                                        <input type="text" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold" for="interestRate">Desired Interest Rate:</label>
                                        <input type="text" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-percent" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold" for="loanDuration">Desired Loan Duration:</label>
                                        <input type="text" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-regular fa-calendar-days" />
                                    </div>
                                </div>
                                {{-- PrePayement --}}
                                @php
                                    $PrepaymentRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.prepaymentOtherCommercial',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        ],
                                        [
                                            'name' => 'No',
                                            'target' => '',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        ],
                                    ];
                                @endphp
                                <div class="select2-parent">
                                    <br><label class="fw-bold">Prepayment Penalty: </label>
                                    <select name="prepayment" class="grid-picker" id="">
                                        @foreach ($PrepaymentRes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                                data-icon='{{ $item['icon'] }}'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group prepaymentOtherCommercial d-none ">
                                        <label class="fw-bold">What is the prepayment penalty amount? </label>
                                        <input type="number" name="prepaymentOther[]" data-type="custom_timeframe"
                                            id="" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" required>
                                    </div>
                                </div>
                                {{-- Prepayment --}}
                                {{-- Balloon Payment --}}
                                @php
                                    $balloonRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.balloonpaymentOtherCommercial',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        ],
                                        [
                                            'name' => 'No',
                                            'target' => '',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        ],
                                    ];
                                @endphp
                                <div class="select2-parent">
                                    <br><label class="fw-bold">Balloon Payment:</label>
                                    <select name="balloon" class="grid-picker" id="">
                                        @foreach ($balloonRes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                                data-icon='{{ $item['icon'] }}'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group balloonpaymentOtherCommercial d-none ">
                                        <label class="fw-bold">How much is the balloon payment? </label>
                                        <input type="number" name="balloonpyment[]"
                                            data-icon="fa-solid fa-dollar-sign"
                                            data-type="fa-solid fa-dollar-sign" id=""
                                            class="form-control has-icon" required>
                                        <label class="fw-bold">When is the balloon payment due? </label>
                                        <input type="text" name="balloonpyment[]"
                                            data-icon="fa-regular fa-calendar-days"
                                            data-type="fa-regular fa-calendar-days" id=""
                                            class="form-control has-icon" required>
                                    </div>
                                </div>
                                {{-- Balloon Payment --}}
                            </div>
                            {{-- Assumable --}}
                            <div class="form-group custom_assumable_res2 d-none">
                                <div class="row">
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What assumable terms are being offered?</label>
                                        <input type="text" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-ruler-combined" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What is the maximum interest rate of the assumable loan that
                                            the buyer is seeking?</label>
                                        <input type="text" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-percent" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What is the maximum monthly payment that the buyer is
                                            seeking, including principal
                                            and interest, for the assumable loan?</label>
                                        <input type="text" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What is the down payment that the buyer can afford to pay
                                            the seller to bridge the gap
                                            between the asking price and the assumable loan balance?</label>
                                        <input type="number" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" />
                                    </div>
                                </div>
                            </div>
                            {{-- Assumable --}}
                            {{-- Cryptcurrency --}}
                            <div class="form-group cryptoCommercial d-none ">
                                <div class="form-group">
                                    <label class="fw-bold">What type of Cryptocurrency does the buyer have?</label><br>
                                    <input type="text" name="cryptocurrency[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What percentage of the sales price will be used with
                                        cryptocurrency?</label><br>
                                    <input type="text" name="cryptocurrency[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-percent">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What percentage of the sales price will be used with
                                        cash?</label><br>
                                    <input type="text" name="cryptocurrency[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-percent">
                                </div>
                                <label class="">Note: Cryptocurrency can be converted to cash at closing.</label>
                            </div>
                            {{-- Cryptcurrency --}}
                            {{-- NFTCommercial --}}
                            <div class="form-group nftCommercial d-none ">
                                <label class="fw-bold">What type of Non-Fungible Token (NFT) is the buyer
                                    offering?</label><br>
                                <input type="text" name="nft[]" class="form-control has-icon"
                                    data-icon="fa-regular fa-check-circle">
                                <label class="fw-bold">What percentage of the sales price will the buyer offer as an
                                    Non-Fungible Token (NFT)?</label><br>
                                <input type="text" name="nft[]" class="form-control has-icon"
                                    data-icon="fa-solid fa-percent">
                                <label class="fw-bold">What percentage of the sales price will the buyer offer as
                                    cash?</label><br>
                                <input type="text" name="nft[]" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar">
                            </div>
                            {{-- NFT --}}
                            {{-- Desired Price and Terms: --}}
                            <div class="form-group">
                                <div class="d-flex justify-content-between aalign-items-center">
                                    <label class="fw-bold mt-4">Offered Escrow Amount:</label>
                                    <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                        <button type="button" class="select-btn me-1 active"
                                            data-type="amount">$</button>
                                        <button type="button" class="select-btn" data-type="percent">%</button>
                                    </div>
                                </div>
                                <input type="number" name="escrow_amount" id="escrow_amount"
                                    class="form-control has-icon input-changable-icon" data-icon="fa-solid fa-dollar-sign"
                                    data-symbol="amount" required>
                            </div>
                            <div class="form-group">
                                @php
                                    $contingencyRes = [
                                        ['name' => 'Inspection', 'target' => '.inspectionCommercial'],
                                        ['name' => 'Appraisal', 'target' => '.appraisalCommercial'],
                                        ['name' => 'Financing', 'target' => '.financingCommercial'],
                                        ['name' => 'Sale of a prior property', 'target' => '.saleCommercial'],
                                        ['name' => 'Other', 'target' => '.contOtherCommercial'],
                                    ];
                                @endphp
                                <label class="fw-bold">Offered Contingencies:</label>
                                <select class="grid-picker" name="contingencies[]" id="contingencies" style=""
                                    multiple required>
                                    <option value="">Select</option>
                                    @foreach ($contingencyRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group inspectionCommercial d-none">
                                    <label class="fw-bold">Inspection (days):</label>
                                    <input type="number" name="contingenciesOpt" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group appraisalCommercial d-none">
                                    <label class="fw-bold">Appraisal (days): </label>
                                    <input type="number" name="contingenciesOpt" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group financingCommercial d-none">
                                    <label class="fw-bold">Financing (days):</label>
                                    <input type="number" name="contingenciesOpt" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group saleCommercial d-none">
                                    <label class="fw-bold">Sale of a Prior Property (days):</label>
                                    <input type="number" name="contingenciesOffered" id="custom_contingencies"
                                        class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group contOtherCommercial d-none">
                                    <label class="fw-bold">Offered Contingency:</label>
                                    <input type="number" name="contingenciesOffered" id="custom_contingencies"
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                    <label class="fw-bold">Offered Contingency (days):</label>
                                    <input type="number" name="contingenciesOfferDays" id="custom_contingencies"
                                        class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">Days Needed to Close:</label>
                                <input type="number" name="closeDays" id="closing_days" placeholder=""
                                    class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                            </div>
                            <div class="form-group">
                                <label for="address" class="fw-bold">Ideal Closing Date: </label>
                                <input type="date" name="idealDate" id="ideal_move_in_date"
                                    class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                            </div>
                            <div class="form-group">
                                @php
                                    $creditRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.creditOptYesCommercial',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        ],
                                        [
                                            'name' => 'No',
                                            'target' => '',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Does the buyer want to ask the seller for a credit at closing?
                                </label>
                                <select class="grid-picker" name="creditRes" id="contingencies" required>
                                    <option value="">Select</option>
                                    @foreach ($creditRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='{{ $item['icon'] }}'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group creditOptYesCommercial d-none">
                                    <div class="d-flex justify-content-between aalign-items-center">
                                        <label for="address" class="fw-bold">What is the credit amount that the buyer
                                            would
                                            like to request from the seller?</label>
                                        <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                            <button type="button" class="select-btn me-1 active"
                                                data-type="amount">$</button>
                                            <button type="button" class="select-btn" data-type="percent">%</button>
                                        </div>
                                    </div>
                                    <input type="number" name="creditOptYes" id="ideal_move_in_date"
                                        class="form-control has-icon input-changable-icon"
                                        data-icon="fa-solid fa-dollar-sign" data-symbol="amount" required>
                                </div>
                            </div>
                            {{-- Desired Price and Terms: --}}
                        </span>
                    </div>
                    {{-- <div class="wizard-step" data-step="26">
                        <label class="fw-bold">Escrow Amount Offered:</label>
                        <input type="text" name="escrow_amount" id="escrow_amount" class="form-control has-icon"
                            data-icon="fa-solid fa-dollar" required>
                        <div class="form-group">
                            @php
                                $contingencyRes = [
                                    ['name' => 'Inspection', 'target' => '.inspectionVacant'],
                                    ['name' => 'Appraisal', 'target' => '.appraisalVacant'],
                                    ['name' => 'Financing', 'target' => '.financingVacant'],
                                    ['name' => 'Sale of a prior property', 'target' => '.saleVacant'],
                                    ['name' => 'Other', 'target' => '.contOtherVacant'],
                                ];
                            @endphp
                            <label class="fw-bold">Contingencies Offered: </label>
                            <select class="grid-picker" name="contingencies[]" id="contingencies" style=""
                                multiple required>
                                <option value="">Select</option>
                                @foreach ($contingencyRes as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group inspectionVacant d-none">
                                <label class="fw-bold">Inspection (days):</label>
                                <input type="text" name="contingenciesOpt" class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                            <div class="form-group appraisalVacant d-none">
                                <label class="fw-bold">Appraisal (days): </label>
                                <input type="text" name="contingenciesOpt" class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                            <div class="form-group financingVacant d-none">
                                <label class="fw-bold">Financing (days):</label>
                                <input type="text" name="contingenciesOpt" class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                            <div class="form-group saleVacant d-none">
                                <label class="fw-bold">Sale of a Prior Property (days):</label>
                                <input type="text" name="contingenciesOffered" id="custom_contingencies"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                            <div class="form-group contOtherVacant d-none">
                                <label class="fw-bold">Contingencies Offered:</label>
                                <input type="text" name="contingenciesOffered" id="custom_contingencies"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                                <label class="fw-bold">Contingency Offered (days) :</label>
                                <input type="text" name="contingenciesOfferDays" id="custom_contingencies"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Days Needed to Close:</label>
                            <input type="text" name="closeDays" id="closing_days" placeholder=""
                                class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                        </div>
                        <div class="form-group">
                            <label for="address" class="fw-bold">Ideal Closing Date: </label>
                            <input type="date" name="idealDate" id="ideal_move_in_date"
                                class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                        </div>
                        <div class="form-group">
                            @php
                                $creditRes = [
                                    [
                                        'name' => 'Yes',
                                        'target' => '.creditOptYesVacant',
                                        'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                    ],
                                    [
                                        'name' => 'No',
                                        'target' => '',
                                        'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                    ],
                                ];
                            @endphp
                            <label class="fw-bold">Does the buyer want to ask the seller for a credit at closing? </label>
                            <select class="grid-picker" name="creditRes" id="contingencies" required>
                                <option value="">Select</option>
                                @foreach ($creditRes as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='{{ $item['icon'] }}'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group creditOptYesVacant d-none">
                                <label for="address" class="fw-bold">What is the credit amount that the buyer would
                                    like to request from the seller?</label>
                                <input type="text" name="creditOptYes" id="ideal_move_in_date"
                                    class="form-control has-icon " data-icon="fa-solid fa-dollar-sign" required>
                            </div>
                        </div>
                    </div> --}}
                    <div class="wizard-step" data-step="28">
                        @php
                            $bathroomsIncome = [
                                ['target' => '', 'name' => '1'],
                                ['target' => '', 'name' => '1.5'],
                                ['target' => '', 'name' => '2'],
                                ['target' => '', 'name' => '2.5'],
                                ['target' => '', 'name' => '3'],
                                ['target' => '', 'name' => '3.5'],
                                ['target' => '', 'name' => '4'],
                                ['target' => '', 'name' => '4.5'],
                                ['target' => '', 'name' => '5'],
                                ['target' => '', 'name' => '5.5'],
                                ['target' => '', 'name' => '6'],
                                ['target' => '', 'name' => '6.5'],
                                ['target' => '', 'name' => '7'],
                                ['target' => '', 'name' => '7.5'],
                                ['target' => '', 'name' => '8'],
                                ['target' => '', 'name' => '8.5'],
                                ['target' => '', 'name' => '9'],
                                ['target' => '', 'name' => '9.5'],
                                ['target' => '', 'name' => '10'],
                                ['target' => '.bathOtherIncome', 'name' => 'Other'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Minimum Bathrooms Needed:</label>
                            <select class="grid-picker" name="bathrooms" id="bathrooms" style="" required>
                                <option value="">Select</option>
                                @foreach ($bathroomsIncome as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column " style="width:calc(10% - 10px);"
                                        data-icon='<i class="fa-solid fa-bath"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group bathOtherIncome d-none">
                                <label class="fw-bold">Minimum Bathrooms Needed:</label>
                                <input type="number" name="custom_bathrooms" id="custom_bathrooms"
                                    class="form-control has-icon" data-icon="fa-solid fa-bath" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="29">
                        <h4> Commercial Property Criteria: Please Enter the Buyer's Preferred Commercial Property Criteria
                        </h4>
                        {{-- <div class="form-group">
                            <label class="fw-bold">Please enter the buyer's preferred commercial property criteria:</label>
                            <input type="text" name="propsCriteria" class="form-control has-icon"
                                data-icon="fa-solid fa-ruler-combined" >
                            </div> --}}
                        <div class="form-group">
                            <label class="fw-bold">Minimum Annual Net Income: </label>
                            <input type="number" name="minimum_annual_net_income" id="minimum_annual_net_income"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Minimum Cap Rate: </label>
                            <input type="number" name="minimum_cap_rate" id="minimum_cap_rate"
                                class="form-control has-icon" data-icon="fa-solid fa-percent">
                        </div>
                        @php
                            $commercialPropertyCriteria = [
                                ['name' => 'Building(s) and Land', 'target' => ''],
                                ['name' => 'Furniture/Fixtures', 'target' => ''],
                                ['name' => 'Leases', 'target' => ''],
                                ['name' => 'Other', 'target' => '.includedInSaleOther'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Does the sale need to include any of the following: Building(s) and Land,
                                Furniture/Fixtures, Leases, or Other?</label>
                            <select class="grid-picker" name="included_in_sale[]" id="" style="" multiple
                                required>
                                <option value="">Select</option>
                                @foreach ($commercialPropertyCriteria as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group includedInSaleOther d-none">
                                <label class="fw-bold">What does the sale need to include?</label>
                                <input type="text" name="licenseOther" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Additional Details: </label>
                            <input type="text" name="additional_details" id="additional_details"
                                class="form-control has-icon" data-icon="">
                        </div>
                    </div>
                    <div class="wizard-step" data-step="30">
                        <div class="form-group">
                            <label class="fw-bold">Real Estate Included:</label>
                            <select class="grid-picker" name="real_estate_included" id="real_estate_included"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                     @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.liscence_needed';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $lincenseCommercial = [
                                ['name' => 'Beer/Wine', 'target' => ''],
                                ['name' => 'Liquor', 'target' => ''],
                                ['name' => 'Off Site', 'target' => ''],
                                ['name' => 'On Site', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => '.lincenseOtherCommercial'],
                            ];
                        @endphp
                        <div class="form-group liscence_needed d-none">
                            <label class="fw-bold">Licenses Needed:</label>
                            <select class="grid-picker" name="lincenses[]" id="" style="" multiple
                                required>
                                <option value="">Select</option>
                                @foreach ($lincenseCommercial as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group lincenseOtherCommercial d-none">
                                <label class="fw-bold">Licenses Needed: </label>
                                <input type="text" name="licenseOther" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="31"> 
                    </div>
                    <div class="wizard-step" data-step="32">
                        <div class="form-group">
                            <label class="fw-bold">Minimum Heated Sqft Needed: </label>
                            <input type="number" name="min_sqft" id="min_sqft" class="form-control has-icon"
                                data-icon="fa-solid fa-ruler-combined" required>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="33">
                        <span class="commercialFields">
                            @php
                                $minimum_total_acreage_needed = [
                                    ['target' => '', 'name' => '0 to less than 1/4'],
                                    ['target' => '', 'name' => '1/4 to less than 1/2'],
                                    ['target' => '', 'name' => '1/2 to less than 1'],
                                    ['target' => '', 'name' => '1 to less than 2'],
                                    ['target' => '', 'name' => '2 to less than 5'],
                                    ['target' => '', 'name' => '5 to less than 10'],
                                    ['target' => '', 'name' => '10 to less than 20'],
                                    ['target' => '', 'name' => '20 to less than 50'],
                                    ['target' => '', 'name' => '50 to less than 100'],
                                    ['target' => '', 'name' => '100 to less than 200'],
                                    ['target' => '', 'name' => '200 to less than 500'],
                                    ['target' => '', 'name' => '500+ Acres'],
                                    ['target' => '', 'name' => 'Non-Applicable'],
                                ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Minimum Total Acreage Needed:</label>
                                <select class="grid-picker" name="minimum_total_acreage_needed" style=""
                                    required>
                                    <option value="">Select</option>
                                    @foreach ($minimum_total_acreage_needed as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="34">
                        <div class="form-group">
                            @php

                                $garageCommercialOpt = [
                                    [
                                        'name' => 'Yes',
                                        'target' => '.garageCommercialOptYes',
                                        'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                    ],
                                    [
                                        'name' => 'No',
                                        'target' => '',
                                        'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                    ],
                                    [
                                        'name' => 'Optional',
                                        'target' => '',
                                        'icon' => '<i class="fa-regular fa-circle-question"></i>',
                                    ],
                                ];
                            @endphp

                            <label class="fw-bold">Garage/Parking Features Needed:</label>
                            <select class="grid-picker" name="garage" id="pool"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($garageCommercialOpt as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='{{ $item['icon'] }}'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group garageCommercialOptYes d-none">
                                @php

                                    $garageCommercial = [
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
                                        ['name' => 'Other', 'target' => '.garageNeedOtherCommercial'],
                                    ];
                                @endphp

                                <select class="grid-picker" name="garage_spaces_Com" id="pool"
                                    style="justify-content: flex-start;" required multiple>
                                    <option value="">Select</option>
                                    @foreach ($garageCommercial as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-solid fa-warehouse"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group garageNeedOtherCommercial d-none">
                                    <label class="fw-bold">Garage/Parking Features Needed: </label>
                                    <input type="text" name="garageNeedOther" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-warehouse" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step " data-step="35">
                        <span class="commercialFields">
                            @php
                                $yes_or_nos1 = [
                                    ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Does the buyer have any preferences for Air Conditioning and
                                    Heating/Fuel for the property?</label>
                                <select class="grid-picker" name="have_air_conditioning" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos1 as $yes_or_no)
                                        @php
                                            if ($yes_or_no['name'] == 'Yes') {
                                                $target = '.conditioningCommercial';
                                            } else {
                                                $target = '';
                                            }
                                        @endphp
                                        <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                            {{ $yes_or_no['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row conditioningCommercial">
                                @php
                                    $air_conditioning = [
                                        ['name' => 'A/C - Office Only', 'target' => ''],
                                        ['name' => 'Central Air', 'target' => ''],
                                        ['name' => 'Humidity Control', 'target' => ''],
                                        ['name' => 'Mini-Split Unit(s)', 'target' => ''],
                                        ['name' => 'Wall/Window Unit(s)', 'target' => ''],
                                        ['name' => 'Zoned', 'target' => ''],
                                        ['name' => 'None', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.otherAirConditionCommercial'],
                                    ];
                                @endphp
                                <div class="form-group ">
                                    <label class="fw-bold">Air Conditioning:</label>
                                    <select class="grid-picker" name="air_conditioning" id=""
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($air_conditioning as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group otherAirConditionCommercial d-none">
                                        <label class="fw-bold">Air Conditioning:
                                        </label>
                                        <input type="text" name="otherAirCondition" id="non_negotiable_factors"
                                            class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                                            required>
                                    </div>
                                </div>
                                @php
                                    $heating_and_fuel = [
                                        ['name' => 'Baseboard', 'target' => ''],
                                        ['name' => 'Central', 'target' => ''],
                                        ['name' => 'Central Building', 'target' => ''],
                                        ['name' => 'Central Individual', 'target' => ''],
                                        ['name' => 'Electric', 'target' => ''],
                                        ['name' => 'Exhaust Fans', 'target' => ''],
                                        ['name' => 'Gas', 'target' => ''],
                                        ['name' => 'Heat Pump', 'target' => ''],
                                        ['name' => 'Heat Recovery Unit', 'target' => ''],
                                        ['name' => 'Natural Gas', 'target' => ''],
                                        ['name' => 'Oil', 'target' => ''],
                                        ['name' => 'Partial', 'target' => ''],
                                        ['name' => 'Propane', 'target' => ''],
                                        ['name' => 'Radiant Ceiling', 'target' => ''],
                                        ['name' => 'Reverse Cycle', 'target' => ''],
                                        ['name' => 'Solar', 'target' => ''],
                                        ['name' => 'Space Heater', 'target' => ''],
                                        ['name' => 'Wall Furnace', 'target' => ''],
                                        ['name' => 'Wall Units / Window Unit', 'target' => ''],
                                        ['name' => 'Zoned', 'target' => ''],
                                        ['name' => 'None', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.otherFuelCommercial'],
                                    ];
                                @endphp
                                <div class="form-group ">
                                    <label class="fw-bold">Heating and Fuel:</label>
                                    <select class="grid-picker" name="heating_and_fuel" id=""
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($heating_and_fuel as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                class="card flex-row" style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group otherFuelCommercial d-none">
                                        <label class="fw-bold">Heating and Fuel:
                                        </label>
                                        <input type="text" name="otherFuel" id="non_negotiable_factors"
                                            class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                                            required>
                                    </div>
                                </div>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="36">
                        <div class="form-group">
                            @php
                                $waterPreferenceCommercial = [
                                    [
                                        'name' => 'Yes',
                                        'target' => '.waterPreferenceCommercial',
                                        'icon' => 'fa-regular fa-circle-check',
                                    ],
                                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                    [
                                        'name' => 'Optional',
                                        'target' => '',
                                        'icon' => 'fa-regular fa-circle-question',
                                    ],
                                ];
                            @endphp
                            <label class="fw-bold">View Preference Needed:</label>
                            <select class="grid-picker" name="viewOptionsCom" id="waterPreferenceCommercial"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($waterPreferenceCommercial as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $view = [
                                ['name' => 'City', 'target' => ''],
                                ['name' => 'Garden', 'target' => ''],
                                ['name' => 'Golf Course', 'target' => ''],
                                ['name' => 'Greenbelt', 'target' => ''],
                                ['name' => 'Mountain(s)', 'target' => ''],
                                ['name' => 'Park', 'target' => ''],
                                ['name' => 'Pool', 'target' => ''],
                                ['name' => 'Tennis Court', 'target' => ''],
                                ['name' => 'Trees/Woods', 'target' => ''],
                                ['name' => 'Water', 'target' => ''],
                                ['name' => 'Beach', 'target' => ''],
                                ['name' => 'Other', 'target' => '.viewOtherCommercial'],
                            ];
                        @endphp
                        <div class="form-group" id="waterPreferenceCommercialOpt" style="display: none">
                            <select class="grid-picker" name="view[]" id="view"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($view as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group viewOtherCommercial d-none">
                                <label class="fw-bold">View Preference: </label>
                                <input type="text" name="viewOther" id=""
                                    class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="37">
                        <span class="commercialFields">
                            <div class="form-group">
                                @php
                                    $waterAccessCommercial = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterAccessCommercial',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water Access Needed:</label>
                                <select class="grid-picker" name="has_water_access" id="waterAccessCommercial"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterAccessCommercial as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="waterAccessCommercialOpt" style="display: none">
                                    @php
                                        $water_access = [
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
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="water_access[]" id="water_access"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($water_access as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group ">
                                @php
                                    $waterViewCommercial = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterViewCommercial',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water View Needed:</label>
                                <select class="grid-picker" name="has_water_view" id="waterViewCommercial"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterViewCommercial as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            @php
                                $water_views = [
                                    ['name' => 'Bay/Harbor - Full', 'target' => ''],
                                    ['name' => 'Bay/Harbor - Partial', 'target' => ''],
                                    ['name' => 'Bayou', 'target' => ''],
                                    ['name' => 'Beach', 'target' => ''],
                                    ['name' => 'Canal', 'target' => ''],
                                    ['name' => 'Creek', 'target' => ''],
                                    ['name' => 'Gulf/Ocean - Full', 'target' => ''],
                                    ['name' => 'Gulf/Ocean - Partial', 'target' => ''],
                                    ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
                                    ['name' => 'Intracoastal Waterway', 'target' => ''],
                                    ['name' => 'Lagoon/Estuary', 'target' => ''],
                                    ['name' => 'Lake', 'target' => ''],
                                    ['name' => 'Lake - Chain of Lakes', 'target' => ''],
                                    ['name' => 'Marina', 'target' => ''],
                                    ['name' => 'Pond', 'target' => ''],
                                    ['name' => 'River', 'target' => ''],
                                    ['name' => 'None', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group" id="waterViewCommercialOpt" style="display: none">
                                <select class="grid-picker" name="water_view[]" id="water_view"
                                    style="justify-content: flex-start;" multiple>
                                    <option value="">Select</option>
                                    @foreach ($water_views as $water_view)
                                        <option value="{{ $water_view['name'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>"
                                            data-target="{{ $water_view['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $water_view['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            </div>
                            <div class="form-group">
                                @php
                                    $waterExtraCommercial = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterExtraCommercial',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water Extras Needed:</label>
                                <select class="grid-picker" name="has_water_extra" id="waterExtraCommercial"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterExtraCommercial as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="waterExtraCommercialOpt" style="display: none">
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
                                            ['name' => 'None', 'target' => ''],
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="water_extras[]" id="water_extras"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($water_extras as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                @php
                                    $waterFrontageCommercial = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterFrontageCommercial',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water Frontage Needed:</label>
                                <select class="grid-picker" name="has_water_frontage" id="waterFrontageCommercial"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterFrontageCommercial as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $water_frontage = [
                                    ['name' => 'Bay/Harbor', 'target' => ''],
                                    ['name' => 'Bayou', 'target' => ''],
                                    ['name' => 'Beach', 'target' => ''],
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
                                    ['name' => 'Marina', 'target' => ''],
                                    ['name' => 'Pond', 'target' => ''],
                                    ['name' => 'River', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group" id="waterFrontageCommercialOpt" style="display: none">
                                <select class="grid-picker" name="water_frontage[]" id="water_frontage"
                                    style="justify-content: flex-start;" multiple>
                                    <option value="">Select</option>
                                    @foreach ($water_frontage as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group ">
                                @php
                                    $dockRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.dockRes',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '.dockRes',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Dock Needed:</label>
                                <select class="grid-picker" name="has_dock" id="hasDock2"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($dockRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="dockRes2" style="display: none">
                                    @php
                                        $dockRes2 = [
                                            ['name' => '2 Point Moorage', 'target' => ''],
                                            ['name' => '3 Point Moorage', 'target' => ''],
                                            ['name' => '4 Point Moorage', 'target' => ''],
                                            ['name' => 'CATV', 'target' => ''],
                                            ['name' => 'Clubhouse', 'target' => ''],
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
                                            ['name' => 'Dock w/Water Supply', 'target' => ''],
                                            ['name' => 'Dock w/o Water Supply', 'target' => ''],
                                            ['name' => 'Dry Dock', 'target' => ''],
                                            ['name' => 'Fish Cleaning Station', 'target' => ''],
                                            ['name' => 'Floating Dock', 'target' => ''],
                                            ['name' => 'Harbormaster', 'target' => ''],
                                            ['name' => 'Internet', 'target' => ''],
                                            ['name' => 'Lift', 'target' => ''],
                                            ['name' => 'Restroom/Shower', 'target' => ''],
                                            ['name' => 'Wet Dock', 'target' => ''],
                                            ['name' => 'None', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.dock_res_other_opt2'],
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="dock[]" id="dock2"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($dockRes2 as $item)
                                            <option value="{{ $item['name'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group dock_res_other_opt2 d-none">
                                    <label class="fw-bold">Dock Description Needed:</label>
                                    <input type="text" name="dockDescription" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="38">
                        <label class="fw-bold">Buyer’s Agent Representation:</label>
                        <div class="form-group">
                            <label class="fw-bold">Does the buyer have a real estate agent representing them?</label>
                            <select class="grid-picker" name="buyerHaveAgentRepresentationCom" id=""
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.buyers_agent_commission2';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group d-none buyers_agent_commission2">
                                <label class="fw-bold">Does the buyer request that the seller pay the buyer’s agent
                                    commission?</label>
                                <select class="grid-picker" name="buyersAgentCommissionRequestedCom" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $item)
                                        @php
                                            if ($item['name'] == 'Yes') {
                                                $target = '.buyers_agent_compensation2';
                                            } else {
                                                $target = '.buyer_agent_comp_not_offered2';
                                            }
                                        @endphp
                                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group d-none buyer_agent_comp_not_offered2">
                                <label class="fw-bold">Would the buyer be willing to pay an agent's commission for
                                    bringing a property the
                                    buyer ends up purchasing if the seller does not agree to pay the commission?</label>
                                @php
                                    $buyersAgentCompensationNotOffered = [
                                        ['name' => 'Yes', 'icon' => 'fa-regular fa-circle-check', 'target' => ''],
                                        ['name' => 'No', 'icon' => 'fa-regular fa-circle-xmark', 'target' => ''],
                                    ];
                                @endphp
                                <select class="grid-picker" name="buyersAgentCompensationNotOfferedCom" id=""
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($buyersAgentCompensationNotOffered as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group d-none buyers_agent_compensation2">
                                <label class="fw-bold">What compensation is the buyer requesting the seller to pay to the
                                    buyer’s agent?</label>
                                @php
                                    $buyersAgentCompensation = [
                                        ['name' => '2%', 'target' => ''],
                                        ['name' => '2.5%', 'target' => ''],
                                        ['name' => '3%', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.buyers_agent_comp2'],
                                    ];
                                @endphp
                                <select class="grid-picker" name="buyersAgentCompensationRequestedCom" id=""
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($buyersAgentCompensation as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group buyers_agent_comp2 d-none">
                                <div class="d-flex justify-content-between aalign-items-center">
                                    <label class="fw-bold">What compensation is the buyer requesting the seller to pay to
                                        the buyer’s agent?</label>
                                    <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                        <button type="button" class="select-btn me-1 active"
                                            data-type="percent">%</button>
                                        <button type="button" class="select-btn" data-type="amount">$</button>
                                    </div>
                                </div>
                                <input type="text" name="buyersAgentCompensationRequestedAmountCom" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="39">
                        <div class="form-group">
                            <label class="fw-bold">Is the buyer currently selling a property?</label>
                            <select class="grid-picker" name="isBuyerCurrentlySellingPropertyCom" id=""
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.link_to_the_property_listing2';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group link_to_the_property_listing2 d-none">
                                <label class="fw-bold">Link to the Property listing on Bid Your Offer:</label>
                                <input type="text" name="linkToThePropertyListingCom" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-link" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="40">
                        <span class="commercialFields">
                            <div class="form-group">
                                <label class="fw-bold">Please provide a detailed description of the buyer’s specific
                                    requirements and preferences for buying a property:</label>
                                <textarea name="description_buyer_specific" id="buyer_specific_requirements" class="form-control" cols="15"
                                    rows="5" required></textarea>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="41">
                        <div class="form-group">
                            @php
                                $negotiableCommercial = [
                                    ['name' => 'Parking Spaces', 'target' => ''],
                                    ['name' => 'Loading Dock', 'target' => ''],
                                    ['name' => 'Warehouse Space', 'target' => ''],
                                    ['name' => 'Office Space', 'target' => ''],
                                    ['name' => 'Conference Room', 'target' => ''],
                                    ['name' => 'Kitchenette/Break Room', 'target' => ''],
                                    ['name' => 'Restrooms', 'target' => ''],
                                    ['name' => 'Elevator', 'target' => ''],
                                    ['name' => 'Handicap Accessibility', 'target' => ''],
                                    ['name' => 'Security System', 'target' => ''],
                                    ['name' => 'On-site Maintenance', 'target' => ''],
                                    ['name' => 'On-site Management', 'target' => ''],
                                    ['name' => 'Outdoor Space/Garden', 'target' => ''],
                                    ['name' => 'Signage Opportunities', 'target' => ''],
                                    ['name' => 'High-Speed Internet', 'target' => ''],
                                    ['name' => 'Utilities Included', 'target' => ''],
                                    ['name' => 'HVAC System', 'target' => ''],
                                    ['name' => 'Natural Lighting', 'target' => ''],
                                    ['name' => 'Storage Space', 'target' => ''],
                                    ['name' => 'Open Floor Plan', 'target' => ''],
                                    ['name' => 'Retail Frontage', 'target' => ''],
                                    ['name' => 'Restaurant Space', 'target' => ''],
                                    ['name' => 'Industrial Features', 'target' => ''],
                                    ['name' => 'Flexibility for Renovations', 'target' => ''],
                                    ['name' => 'Common Areas', 'target' => ''],
                                    ['name' => 'Business Center', 'target' => ''],
                                    ['name' => 'Gym/Fitness Facilities', 'target' => ''],
                                    ['name' => 'Lounge Area', 'target' => ''],
                                    ['name' => 'Reception Area', 'target' => ''],
                                    ['name' => 'Security Guard', 'target' => ''],
                                    ['name' => 'Fire Safety Systems', 'target' => ''],
                                    ['name' => 'Energy-Efficient Features', 'target' => ''],
                                    ['name' => 'Green Building Certification', 'target' => ''],
                                    ['name' => 'Access to Public Transportation', 'target' => ''],
                                    ['name' => 'Proximity to Highways', 'target' => ''],
                                    ['name' => 'Visibility from Main Road', 'target' => ''],
                                    ['name' => 'Other', 'target' => '.negotiableOtherCommercial'],
                                ];

                            @endphp
                            <label class="fw-bold">Are there any non-negotiable amenities or property features that the
                                buyer is
                                seeking? </label>
                            <select class="grid-picker" name="nonNegotiableFactors" id="any_non_negotiable_factors"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.negotiableYesCommercial';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group negotiableYesCommercial d-none">
                            <select class="grid-picker" name="nonNegotiable[]" id="any_non_negotiable_factors"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($negotiableCommercial as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group negotiableOtherCommercial d-none">
                                <label class="fw-bold">What non-negotiable amenities or property features is the buyer is
                                    seeking?</label>
                                <input type="text" name="non_negotiable_factors" id="non_negotiable_factors"
                                    class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="42">
                        <h4>For a more personalized listing, you can include a picture of yourself and/or include a video of
                            yourself providing additional information about your background and criteria.</h4>
                        <div class="form-group row">
                            <div class="form-group col-md-6">
                                <label class="fw-bold">First Name:</label>
                                <input type="text" name="agent_first_name" id="first_name" placeholder=""
                                    value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-user" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="fw-bold">Last Name:</label>
                                <input type="text" name="agent_last_name" id="last_name" placeholder=""
                                    value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-user" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="form-group col-md-6">
                                <label class="fw-bold">Phone Number:</label>
                                <input type="text" name="agent_phoneCom" id="agent_phone" placeholder=""
                                    value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-phone">
                            </div>
                            <div class="form-group col-md-6">
                                <label class="fw-bold">Email:</label>
                                <input type="text" name="agent_email" id="agent_email" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-envelope"
                                    value="{{ Auth::user()->email }}" required>
                            </div>
                        </div>
                        @if(Auth::user()->user_type == "agent")
                        <div class="form-group row">
                            <div class="form-group col-md-6">
                                <label class="fw-bold">Brokerage:</label>
                                <input type="text" name="agent_brokerageCom" id="agent_brokerage" placeholder=""
                                    value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-handshake">
                            </div>
                            <div class="form-group col-md-6">
                                <label class="fw-bold">Real Estate License #:</label>
                                <input type="text" name="license" id="agent_mls_id" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                                    value="{{ optional(Auth::user())->mls_id }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="form-group col-md-6">
                                <label class="fw-bold">NAR Member ID (NRDS ID):</label>
                                <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-id-card-clip"
                                    value="{{ optional(Auth::user())->mls_id }}">
                            </div>
                            {{-- <div class="form-group col-md-6">
                                <label class="fw-bold">Listed By: Real Estate Agent:</label>
                                <input type="text" name="agent_commission_percent" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                            </div> --}}
                        </div>
                        @endif
                        <span class="commercialFields">
                            <div class="row">
                                <div class="col-6">
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
                        </span>
                    </div>
                    <div class="wizard-step" data-step="43">
                        <span class="vacantFields">
                            @php
                                $propertyConditionsRes = [
                                    ['name' => 'Pre-Construction', 'target' => ''],
                                    ['name' => 'Currently Being Built', 'target' => ''],
                                    ['name' => 'New Construction', 'target' => ''],
                                    ['name' => 'Completely Updated: No updates needed', 'target' => ''],
                                    ['name' => 'Semi-updated: Needs minor updates', 'target' => ''],
                                    ['name' => 'Not Updated: Requires a complete update', 'target' => ''],
                                    [
                                        'name' => 'Tear Down: Requires complete demolition and reconstruction',
                                        'target' => '',
                                    ],
                                    ['name' => 'Open to any type of property condition', 'target' => ''],
                                    ['name' => 'Other', 'target' => '.conditionsVacantOther'],
                                ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Acceptable Property Conditions:</label>
                                <select class="grid-picker" name="prop_condition[]" id="prop_condition"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($propertyConditionsRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(50% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group conditionsVacantOther">
                                    <label class="fw-bold">Acceptable Property Condition:</label>
                                    <input type="text" name="propConditionOther" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                                        placeholder="">
                                </div>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="44">
                        <span class="vacantFields">
                            <label class="fw-bold">
                                Desired Price and Terms:
                            </label>
                            <div class="form-group">
                                <label class="fw-bold">
                                    Maximum Budget:
                                </label>
                                <input type="number" name="max_price" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar-sign" required>
                            </div>
                            @php
                                $financings = [
                                    ['name' => 'Cash', 'target' => '.cashRes'],
                                    ['name' => 'Conventional', 'target' => ''],
                                    ['name' => 'Seller Financing', 'target' => '.sellerFinancingVacant'],
                                    ['name' => 'FHA', 'target' => ''],
                                    ['name' => 'Jumbo', 'target' => ''],
                                    ['name' => 'VA', 'target' => ''],
                                    ['name' => 'No-Doc', 'target' => ''],
                                    ['name' => 'Non-QM', 'target' => ''],
                                    ['name' => 'Assumable', 'target' => '.assumableVacant'],
                                    ['name' => 'Exchange/Trade', 'target' => '.tradeVacant'],
                                    ['name' => 'Lease Option', 'target' => '.lease_option'],
                                    ['name' => 'Lease Purchase', 'target' => '.lease_purchase'],
                                    ['name' => 'Cryptocurrency', 'target' => '.cryptoVacant'],
                                    ['name' => 'Non-Fungible Token (NFT)', 'target' => '.nftVacant'],
                                    ['name' => 'USDA', 'target' => ''],
                                    ['name' => 'Other', 'target' => '.financingOtherVacant'],
                                ];
                            @endphp
                            <div class="row align-items-end mt-4 ">
                                <div class="col-md-12">
                                    <label class="fw-bold">Offered Currency/Financing:</label>
                                    <div class="select2-parent">
                                        <select name="financings[]" class="grid-picker" multiple
                                            id="financingOptionsVacant" required>
                                            @foreach ($financings as $financing)
                                                <option value="{{ $financing['name'] }}"
                                                    data-target="{{ $financing['target'] }}" class="card flex-column "
                                                    style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                                    {{ $financing['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            {{-- Cash --}}
                            {{-- <div class="form-group cashRes d-none ">
                                    <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                                    <input type="text" name="cash[]" data-type="" id="" class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler-combined" >
                                </div> --}}

                            <div class="form-group financingOtherVacant d-none ">

                                <label class="fw-bold">What type of financing/funds will the buyer use to purchase the
                                    property?</label>
                                <input type="text" name="financingOther" data-type="" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                            </div>
                            {{-- exchange/trade --}}
                            <div class="form-group tradeVacant d-none ">
                                @php
                                    $exchangeItems = [
                                        ['name' => 'Another Home', 'target' => ''],
                                        ['name' => 'Vehicle', 'target' => ''],
                                        ['name' => 'Boat', 'target' => ''],
                                        ['name' => 'Motorhome', 'target' => ''],
                                        ['name' => 'Artwork', 'target' => ''],
                                        ['name' => 'Jewelry', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.exchange_items_other_opt3'],
                                    ];
                                @endphp
                                <div class="row align-items-end mt-4 ">
                                    <div class="col-md-12">
                                        <label class="fw-bold">Acceptable Exchange Item:</label>
                                        <div class="select2-parent">
                                            <select name="trade[]" class="grid-picker" id="exchangeItem" required>
                                                @foreach ($exchangeItems as $exchangeItem)
                                                    <option value="{{ $exchangeItem['name'] }}"
                                                        data-target="{{ $exchangeItem['target'] }}"
                                                        class="card flex-column " style="width:calc(33.3% - 10px);"
                                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                                        {{ $exchangeItem['name'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group exchange_items_other_opt3 d-none">
                                    <label class="fw-bold">Acceptable Exchange Item:</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the estimated value of the acceptable exchange/trade
                                        item?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the condition of the item the buyer is looking to
                                        exchange?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">How much cash will the buyer use on top of the Exchange/Trade
                                        item?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                                    <input type="text" name="trade[]" data-type="" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                            </div>
                            {{-- exchange/trade --}}
                            {{-- Conventional --}}
                            <div class="form-group" id="conventionalVacant" style="display: none;">
                                @php
                                    $customOptRes = [
                                        [
                                            'target' => '.customOptVacantYes',
                                            'name' => 'Yes',
                                            'icon' => 'fa-regular fa-check-circle',
                                        ],
                                        [
                                            'target' => '.customOptVacantNo',
                                            'name' => 'No',
                                            'icon' => 'fa-regular fa-circle-xmark',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Has the buyer been pre-approved for a loan? </label>
                                <select name="conventionalOptions[]" class="grid-picker" id=""
                                    style="justify-content: flex-start;" required>
                                    @foreach ($customOptRes as $item)
                                        <option value="{{ $item['name'] }}"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'
                                            data-target="{{ $item['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group customOptVacantYes d-none">
                                    <label class="fw-bold">How much is the buyer pre-approved for?</label>
                                    <input type="number" name="financingOptionsConventional[]"
                                        data-type="customOptionsYes" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar-sign">
                                </div>
                                <div class="form-group customOptVacantNo d-none">
                                    <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                                    <input type="text" name="buyerBudget" class="form-control has-icon"
                                        data-icon="fa-solid fa-ruler-combined">
                                    <div class="form-group">
                                        <label class="fw-bold">If not pre-approved yet, does the buyer need a lender
                                            recommendation?
                                        </label>
                                        @php
                                            $customOptionsYesNo = [
                                                [
                                                    'target' => '',
                                                    'name' => 'Yes',
                                                    'icon' => 'fa-regular fa-check-circle',
                                                ],
                                                [
                                                    'target' => '',
                                                    'name' => 'No',
                                                    'icon' => 'fa-regular fa-circle-xmark',
                                                ],
                                            ];
                                        @endphp
                                        <label class="fw-bold">Has the buyer been pre-approved for a loan? </label>
                                        <select name="customOptionsYesNo" class="grid-picker" id=""
                                            style="justify-content: flex-start;" required>
                                            @foreach ($customOptionsYesNo as $item)
                                                <option value="{{ $item['name'] }}"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.3% - 10px);">
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            {{-- Lease Options --}}
                            <div class="form-group " id="leaseOptionVacant" style="display: none;">
                                <div class="form-group">
                                    <label class="fw-bold">What is the buyer's desired offering price for a lease
                                        option?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What specific terms does the buyer propose for the lease
                                        option?</label><br>
                                    <input type="text" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                                    <input type="text" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the monthly payment amount the buyer is
                                        offering?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What are the specific conditions or requirements outlined by the
                                        buyer for the lease
                                        option?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                @php
                                    $leaseOptionsVacOpt = [
                                        [
                                            'icon' => 'fa-regular fa-circle-check',
                                            'name' => 'Yes',
                                            'target' => '.lease_options_vac_opt_yes',
                                        ],
                                        [
                                            'icon' => 'fa-regular fa-circle-xmark',
                                            'name' => 'No',
                                            'target' => '',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold mt-3">Will the buyer offer an option fee?</label>
                                <select class="grid-picker" name="leaseOptions[]" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($leaseOptionsVacOpt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group lease_options_vac_opt_yes d-none">
                                    <label class="fw-bold">How much is the offered option fee?</label><br>
                                    <input type="number" name="leaseOptions[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                            </div>
                            {{-- Lease Options --}}
                            {{-- Lease Purchase --}}
                            <div class="form-group " id="leasePurchaseVacant" style="display: none;">
                                <div class="form-group">
                                    <label class="fw-bold">What is the buyer's desired offering price for a lease
                                        purchase?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What specific terms does the buyer propose for the lease
                                        purchase?</label><br>
                                    <input type="text" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                                    <input type="text" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the monthly payment amount the buyer is
                                        offering?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What are the specific conditions or requirements outlined by the
                                        buyer for the lease
                                        purchase?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                @php
                                    $leasePurchaseVacOpt = [
                                        [
                                            'icon' => 'fa-regular fa-circle-check',
                                            'name' => 'Yes',
                                            'target' => '.lease_options_vac_opt_yes',
                                        ],
                                        [
                                            'icon' => 'fa-regular fa-circle-xmark',
                                            'name' => 'No',
                                            'target' => '',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold mt-3">Will the buyer offer an option fee?</label>
                                <select class="grid-picker" name="leasePurchase[]" id=""
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($leasePurchaseVacOpt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group lease_options_vac_opt_yes d-none">
                                    <label class="fw-bold">How much is the offered option fee?</label><br>
                                    <input type="number" name="leasePurchase[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar">
                                </div>
                            </div>
                            {{-- Lease Purchase --}}
                            {{-- Seller  --}}
                            <div class="form-group sellerFinancingVacant d-none ">
                                <label class="fw-bold mt-3">Please enter the proposed price and terms that the buyer is
                                    offering to the seller:</label>
                                <div class="row">
                                    <div class="col-12 form-group">
                                        <label class="fw-bold" for="buyerBudget">Desired Purchase Price:</label>
                                        <input type="number" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <div class="d-flex justify-content-between aalign-items-center">
                                            <label class="fw-bold" for="buyerBudget">Desired Down Payment:</label>
                                            <div
                                                class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                                <button type="button" class="select-btn me-1 active"
                                                    data-type="percent">%</button>
                                                <button type="button" class="select-btn" data-type="amount">$</button>
                                            </div>
                                        </div>
                                        <input type="text" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-percent" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label class="fw-bold" for="downPayment">Desired Seller Financing
                                                Amount:</label>
                                            <div
                                                class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                                <button type="button" class="select-btn me-1 active"
                                                    data-type="amount">$</button>
                                                <button type="button" class="select-btn" data-type="percent">%</button>
                                            </div>
                                        </div>
                                        <input type="text" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold" for="interestRate">Desired Interest Rate:</label>
                                        <input type="text" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-percent" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold" for="loanDuration">Desired Loan Duration:</label>
                                        <input type="text" name="sellerFinancing[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-regular fa-calendar-days" />
                                    </div>
                                </div>
                                {{-- PrePayement --}}
                                @php
                                    $PrepaymentRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.prepaymentOtherVacant',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        ],
                                        [
                                            'name' => 'No',
                                            'target' => '',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        ],
                                    ];
                                @endphp
                                <div class="select2-parent">
                                    <br><label class="fw-bold">Prepayment Penalty: </label>
                                    <select name="prepayment" class="grid-picker" id="">
                                        @foreach ($PrepaymentRes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                                data-icon='{{ $item['icon'] }}'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group prepaymentOtherVacant d-none ">
                                        <label class="fw-bold">What is the prepayment penalty amount? </label>
                                        <input type="number" name="prepaymentOther[]" data-type="custom_timeframe"
                                            id="" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" required>
                                    </div>
                                </div>
                                {{-- Prepayment --}}
                                {{-- Balloon Payment --}}
                                @php
                                    $balloonRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.balloonpaymentOtherVacant',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        ],
                                        [
                                            'name' => 'No',
                                            'target' => '',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        ],
                                    ];
                                @endphp
                                <div class="select2-parent">
                                    <br><label class="fw-bold">Balloon Payment:</label>
                                    <select name="balloon" class="grid-picker" id="">
                                        @foreach ($balloonRes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                                data-icon='{{ $item['icon'] }}'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group balloonpaymentOtherVacant d-none ">
                                        <label class="fw-bold">How much is the balloon payment? </label>
                                        <input type="number" name="balloonpyment[]"
                                            data-icon="fa-solid fa-dollar-sign"
                                            data-type="fa-solid fa-dollar-sign" id=""
                                            class="form-control has-icon" required>
                                        <label class="fw-bold">When is the balloon payment due? </label>
                                        <input type="text" name="balloonpyment[]"
                                            data-icon="fa-regular fa-calendar-days"
                                            data-type="fa-regular fa-calendar-days" id=""
                                            class="form-control has-icon" required>
                                    </div>
                                </div>
                                {{-- Balloon Payment --}}
                            </div>
                            {{-- Assumable --}}
                            <div class="form-group assumableVacant d-none ">
                                <div class="row">
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What assumable terms are being offered?</label>
                                        <input type="text" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-ruler-combined" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What is the maximum interest rate of the assumable loan that
                                            the buyer is seeking?</label>
                                        <input type="text" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-percent" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What is the maximum monthly payment that the buyer is
                                            seeking, including principal
                                            and interest, for the assumable loan?</label>
                                        <input type="text" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" />
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="fw-bold">What is the down payment that the buyer can afford to pay
                                            the seller to bridge the gap
                                            between the asking price and the assumable loan balance?</label>
                                        <input type="number" name="assumable[]" value=""
                                            data-target="{{ $item['target'] }}" class="form-control has-icon"
                                            data-icon="fa-solid fa-dollar-sign" />
                                    </div>
                                </div>
                            </div>
                            {{-- Assumable --}}
                            {{-- Cryptcurrency --}}
                            <div class="form-group cryptoVacant d-none ">
                                <div class="form-group">
                                    <label class="fw-bold">What type of Cryptocurrency does the buyer have?</label><br>
                                    <input type="text" name="cryptocurrency[]" class="form-control has-icon"
                                        data-icon="fa-regular fa-check-circle">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What percentage of the sales price will be used with
                                        cryptocurrency?</label><br>
                                    <input type="text" name="cryptocurrency[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-percent">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What percentage of the sales price will be used with
                                        cash?</label><br>
                                    <input type="text" name="cryptocurrency[]" class="form-control has-icon"
                                        data-icon="fa-solid fa-percent">
                                </div>
                                <label class="">Note: Cryptocurrency can be converted to cash at closing.</label>
                            </div>
                            {{-- Cryptcurrency --}}
                            {{-- NFT --}}
                            <div class="form-group nftVacant d-none ">
                                <label class="fw-bold">What type of Non-Fungible Token (NFT) is the buyer
                                    offering?</label><br>
                                <input type="text" name="nft[]" class="form-control has-icon"
                                    data-icon="fa-regular fa-check-circle">
                                <label class="fw-bold">What percentage of the sales price will the buyer offer as an
                                    Non-Fungible Token (NFT)?</label><br>
                                <input type="text" name="nft[]" class="form-control has-icon"
                                    data-icon="fa-solid fa-percent">
                                <label class="fw-bold">What percentage of the sales price will the buyer offer as
                                    cash?</label><br>
                                <input type="text" name="nft[]" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar">
                            </div>
                            {{-- NFT --}}
                            {{-- Desired Price and Terms: --}}
                            <div class="form-group">
                                <div class="d-flex justify-content-between aalign-items-center">
                                    <label class="fw-bold mt-4">Offered Escrow Amount:</label>
                                    <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                        <button type="button" class="select-btn me-1 active"
                                            data-type="amount">$</button>
                                        <button type="button" class="select-btn" data-type="percent">%</button>
                                    </div>
                                </div>
                                <input type="number" name="escrow_amount" id="escrow_amount"
                                    class="form-control has-icon input-changable-icon" data-icon="fa-solid fa-dollar-sign"
                                    data-symbol="amount" required>
                            </div>
                            <div class="form-group">
                                @php
                                    $contingencyRes = [
                                        ['name' => 'Inspection', 'target' => '.inspectionVacant'],
                                        ['name' => 'Appraisal', 'target' => '.appraisalVacant'],
                                        ['name' => 'Financing', 'target' => '.financingVacant'],
                                        ['name' => 'Sale of a prior property', 'target' => '.saleVacant'],
                                        ['name' => 'Other', 'target' => '.contOtherVacant'],
                                    ];
                                @endphp
                                <label class="fw-bold">Offered Contingencies:</label>
                                <select class="grid-picker" name="contingencies[]" id="contingencies" style=""
                                    multiple required>
                                    <option value="">Select</option>
                                    @foreach ($contingencyRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group inspectionVacant d-none">
                                    <label class="fw-bold">Inspection (days):</label>
                                    <input type="number" name="contingenciesOpt" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group appraisalVacant d-none">
                                    <label class="fw-bold">Appraisal (days): </label>
                                    <input type="number" name="contingenciesOpt" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group financingVacant d-none">
                                    <label class="fw-bold">Financing (days):</label>
                                    <input type="number" name="contingenciesOpt" class="form-control has-icon"
                                        data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group saleVacant d-none">
                                    <label class="fw-bold">Sale of a Prior Property (days):</label>
                                    <input type="number" name="contingenciesOffered" id="custom_contingencies"
                                        class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                                </div>
                                <div class="form-group contOtherVacant d-none">
                                    <label class="fw-bold">Offered Contingency:</label>
                                    <input type="number" name="contingenciesOffered" id="custom_contingencies"
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                    <label class="fw-bold">Offered Contingency (days):</label>
                                    <input type="number" name="contingenciesOfferDays" id="custom_contingencies"
                                        class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Days Needed to Close:</label>
                                <input type="number" name="closeDays" id="closing_days" placeholder=""
                                    class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                            </div>
                            <div class="form-group">
                                <label for="address" class="fw-bold">Ideal Closing Date: </label>
                                <input type="date" name="idealDate" id="ideal_move_in_date"
                                    class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                            </div>
                            <div class="form-group">
                                @php
                                    $creditRes = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.creditOptYesVacant',
                                            'icon' => '<i class="fa-regular fa-circle-check"></i>',
                                        ],
                                        [
                                            'name' => 'No',
                                            'target' => '',
                                            'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Does the buyer want to ask the seller for a credit at closing?
                                </label>
                                <select class="grid-picker" name="creditRes" id="contingencies" required>
                                    <option value="">Select</option>
                                    @foreach ($creditRes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='{{ $item['icon'] }}'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group creditOptYesVacant d-none">
                                    <div class="d-flex justify-content-between aalign-items-center">
                                        <label for="address" class="fw-bold">What is the credit amount that the buyer
                                            would
                                            like to request from the seller?</label>
                                        <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                            <button type="button" class="select-btn me-1 active"
                                                data-type="amount">$</button>
                                            <button type="button" class="select-btn" data-type="percent">%</button>
                                        </div>
                                    </div>
                                    <input type="number" name="creditOptYes" id="ideal_move_in_date"
                                        class="form-control has-icon input-changable-icon"
                                        data-icon="fa-solid fa-dollar-sign" data-symbol="amount" required>
                                </div>
                            </div>
                            {{-- Desired Price and Terms: --}}
                        </span>
                    </div>
                    <div class="wizard-step" data-step="45">
                        @php
                            $lot_features = [
                                ['name' => 'Brownfield', 'target' => ''],
                                ['name' => 'Buildable', 'target' => ''],
                                ['name' => 'Central Business District', 'target' => ''],
                                ['name' => 'Cleared', 'target' => ''],
                                ['name' => 'Coastal Construction Control Line', 'target' => ''],
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
                                ['name' => 'In City Limits', 'target' => ''],
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
                                ['name' => 'Hilly', 'target' => ''],
                                ['name' => 'Zoned for Horses', 'target' => ''],
                                ['name' => 'Other', 'target' => '.otherFeatureVacant'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Lot Features:</label>
                            <select class="grid-picker" name="lot_features[]" id="lot_features" multiple
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($lot_features as $lot_feature)
                                    <option value="{{ $lot_feature['name'] }}"
                                        data-target="{{ $lot_feature['target'] }}" class="card flex-column"
                                        style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $lot_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group otherFeatureVacant d-none">
                                <label class="fw-bold">Lot Features:</label>
                                <input type="text" name="otherFeature" class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler-combined">
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="46">
                        <span class="vacantFields">
                            @php
                                $minimum_total_acreage_needed = [
                                    ['target' => '', 'name' => '0 to less than 1/4'],
                                    ['target' => '', 'name' => '1/4 to less than 1/2'],
                                    ['target' => '', 'name' => '1/2 to less than 1'],
                                    ['target' => '', 'name' => '1 to less than 2'],
                                    ['target' => '', 'name' => '2 to less than 5'],
                                    ['target' => '', 'name' => '5 to less than 10'],
                                    ['target' => '', 'name' => '10 to less than 20'],
                                    ['target' => '', 'name' => '20 to less than 50'],
                                    ['target' => '', 'name' => '50 to less than 100'],
                                    ['target' => '', 'name' => '100 to less than 200'],
                                    ['target' => '', 'name' => '200 to less than 500'],
                                    ['target' => '', 'name' => '500+ Acres'],
                                    ['target' => '', 'name' => 'Non-Applicable'],
                                ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Minimum Total Acreage Needed:</label>
                                <select class="grid-picker" name="minimum_total_acreage_needed" style=""
                                    required>
                                    <option value="">Select</option>
                                    @foreach ($minimum_total_acreage_needed as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Lot Dimensions Needed:</label>
                                <input type="text" name="lot_dimensions" id="lot_dimensions"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Lot Size Square Footage Needed:</label>
                                <input type="text" name="lot_size_square_footage" id="lot_size_square_footage"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Front Footage Needed:</label>
                                <input type="text" name="front_footage" id="front_footage"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="47">
                        @php
                            $road_frontages = [
                                ['name' => 'Access Road', 'target' => ''],
                                ['name' => 'Alley', 'target' => ''],
                                ['name' => 'Business District', 'target' => ''],
                                ['name' => 'City Street', 'target' => ''],
                                ['name' => 'County Road ', 'target' => ''],
                                ['name' => 'Divided Highway', 'target' => ''],
                                ['name' => 'Easement', 'target' => ''],
                                ['name' => 'Highway', 'target' => ''],
                                ['name' => 'Interchange', 'target' => ''],
                                ['name' => 'Interstate', 'target' => ''],
                                ['name' => 'Main Thoroughfare', 'target' => ''],
                                ['name' => 'Private Road', 'target' => ''],
                                ['name' => 'Rail', 'target' => ''],
                                ['name' => 'State Road', 'target' => ''],
                                ['name' => 'Turn Lanes', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => '.otherFrontageVacant'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Road Frontage Needed:</label>
                            <select class="grid-picker" name="road_frontage[]" id="road_frontage"
                                style="justify-content: flex-start;" onclick="" multiple>
                                <option value="">Select</option>
                                @foreach ($road_frontages as $road_frontage)
                                    <option value="{{ $road_frontage['name'] }}"
                                        data-target="{{ $road_frontage['target'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $road_frontage['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group otherFrontageVacant d-none">
                                <label class="fw-bold">Road Frontage Needed:</label>
                                <input type="text" name="otherFrontage" id="front_footage"
                                    class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="48">
                        @php
                            $road_surface_types = [
                                ['name' => 'Asphalt', 'target' => ''],
                                ['name' => 'Brick', 'target' => ''],
                                ['name' => 'Chip And Seal', 'target' => ''],
                                ['name' => 'Concrete', 'target' => ''],
                                ['name' => 'Dirt', 'target' => ''],
                                ['name' => 'Gravel', 'target' => ''],
                                ['name' => 'Limerock', 'target' => ''],
                                ['name' => 'Paved', 'target' => ''],
                                ['name' => 'Unimproved', 'target' => ''],
                                ['name' => 'Other', 'target' => '.othersurfaceVacant'],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Road Surface Needed:</label>
                            <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($road_surface_types as $road_surface_type)
                                    <option value="{{ $road_surface_type['name'] }}"
                                        data-target="{{ $road_surface_type['target'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $road_surface_type['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group othersurfaceVacant d-none">
                                <label class="fw-bold">Road Surface Type Needed:</label>
                                <input type="text" name="othersurface" id="front_footage"
                                    class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="49">
                        @php
                            $utilities = [
                                ['name' => 'BB/HS Internet Available', 'target' => ''],
                                ['name' => 'BB/HS Internet Capable', 'target' => ''],
                                ['name' => 'Cable Available', 'target' => ''],
                                ['name' => 'Cable Connected', 'target' => ''],
                                ['name' => 'Electrical Nearby', 'target' => ''],
                                ['name' => 'Electricity Available', 'target' => ''],
                                ['name' => 'Fiber Optics', 'target' => ''],
                                ['name' => 'Fire Hydrant', 'target' => ''],
                                ['name' => 'Mini Sewer', 'target' => ''],
                                ['name' => 'Natural Gas Available', 'target' => ''],
                                ['name' => 'Phone Available', 'target' => ''],
                                ['name' => 'Private', 'target' => ''],
                                ['name' => 'Propane', 'target' => ''],
                                ['name' => 'Public', 'target' => ''],
                                ['name' => 'Sewer Available', 'target' => ''],
                                ['name' => 'Sewer Connected', 'target' => ''],
                                ['name' => 'Sewer Nearby', 'target' => ''],
                                ['name' => 'Sprinkler Meter', 'target' => ''],
                                ['name' => 'Sprinkler Recycled', 'target' => ''],
                                ['name' => 'Sprinkler Well', 'target' => ''],
                                ['name' => 'Street Lights', 'target' => ''],
                                ['name' => 'Telephone Nearby', 'target' => ''],
                                ['name' => 'Underground Utilities', 'target' => ''],
                                ['name' => 'Utility Pole', 'target' => ''],
                                ['name' => 'Water - Multiple Meters', 'target' => ''],
                                ['name' => 'Water Available', 'target' => ''],
                                ['name' => 'Water Connected', 'target' => ''],
                                ['name' => 'Water Nearby', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => '.otherUtilitiesVacant'],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Utilities Needed:</label>
                            <select class="grid-picker" name="utilities[]" id="utilities"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($utilities as $utilitie12)
                                    <option value="{{ $utilitie12['name'] }}"
                                        data-target="{{ $utilitie12['target'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $utilitie12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group otherUtilitiesVacant d-none">
                                <label class="fw-bold">Utilities Needed:</label>
                                <input type="text" name="otherUtilities" id="front_footage"
                                    class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                            </div>
                        </div>
                        @php
                            $waters = [
                                ['name' => 'Canal/Lake For Irrigation', 'target' => ''],
                                ['name' => 'Private', 'target' => ''],
                                ['name' => 'Public', 'target' => ''],
                                ['name' => 'Well', 'target' => ''],
                                ['name' => 'Well Required', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => '.otherWaterVacant'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Water Needed:</label>
                            <select class="grid-picker" name="water[]" id="water"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($waters as $water)
                                    <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                                        class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group otherWaterVacant d-none">
                                <label class="fw-bold">Water Needed:</label>
                                <input type="text" name="otherWater" id="front_footage"
                                    class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                            </div>
                        </div>
                        @php
                            $sewers = [
                                ['name' => 'PEP-Holding Tank', 'target' => ''],
                                ['name' => 'Private Sewer', 'target' => ''],
                                ['name' => 'Public Sewer', 'target' => ''],
                                ['name' => 'Septic Needed', 'target' => ''],
                                ['name' => 'Septic Tank', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => '.otherSewerVacant'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Sewer Needed:</label>
                            <select class="grid-picker" name="sewer[]" id="sewer"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($sewers as $sewer12)
                                    <option value="{{ $sewer12['name'] }}" data-target="{{ $sewer12['target'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $sewer12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group otherSewerVacant d-none">
                                <label class="fw-bold">Sewer Needed:</label>
                                <input type="text" name="otherSewer" id="front_footage"
                                    class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="50">
                        <div class="form-group">
                            @php
                                $waterPreferenceVacant = [
                                    [
                                        'name' => 'Yes',
                                        'target' => '.waterPreferenceVacant',
                                        'icon' => 'fa-regular fa-circle-check',
                                    ],
                                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                    [
                                        'name' => 'Optional',
                                        'target' => '',
                                        'icon' => 'fa-regular fa-circle-question',
                                    ],
                                ];
                            @endphp
                            <label class="fw-bold">View Preference Needed:</label>
                            <select class="grid-picker" name="viewOptions" id="waterPreferenceVacant"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($waterPreferenceVacant as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group" id="waterPreferenceVacantOpt" style="display: none">
                                @php
                                    $view = [
                                        ['name' => 'City', 'target' => ''],
                                        ['name' => 'Garden', 'target' => ''],
                                        ['name' => 'Golf Course', 'target' => ''],
                                        ['name' => 'Greenbelt', 'target' => ''],
                                        ['name' => 'Mountain(s)', 'target' => ''],
                                        ['name' => 'Park', 'target' => ''],
                                        ['name' => 'Pool', 'target' => ''],
                                        ['name' => 'Tennis Court', 'target' => ''],
                                        ['name' => 'Trees/Woods', 'target' => ''],
                                        ['name' => 'Water', 'target' => ''],
                                        ['name' => 'Beach', 'target' => ''],
                                        ['name' => 'Other', 'target' => '.viewOtherVacant']
                                    ];
                                @endphp
                                <select class="grid-picker" name="view[]" id="view"
                                    style="justify-content: flex-start;" multiple>
                                    <option value="">Select</option>
                                    @foreach ($view as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>"
                                            class="card flex-row" style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group viewOtherVacant d-none">
                                    <label class="fw-bold">View Preference: </label>
                                    <input type="text" name="viewOther" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="51">
                        <span class="vacantFields">
                            <div class="form-group ">
                                @php
                                    $waterViewVacant = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterViewVacant',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water View Needed:</label>
                                <select class="grid-picker" name="has_water_view" id="waterViewVacant"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterViewVacant as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="waterViewVacantOpt" style="display: none">
                                    @php
                                        $water_views = [
                                            ['name' => 'Bay/Harbor - Full', 'target' => ''],
                                            ['name' => 'Bay/Harbor - Partial', 'target' => ''],
                                            ['name' => 'Bayou', 'target' => ''],
                                            ['name' => 'Beach', 'target' => ''],
                                            ['name' => 'Canal', 'target' => ''],
                                            ['name' => 'Creek', 'target' => ''],
                                            ['name' => 'Gulf/Ocean - Full', 'target' => ''],
                                            ['name' => 'Gulf/Ocean - Partial', 'target' => ''],
                                            ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
                                            ['name' => 'Intracoastal Waterway', 'target' => ''],
                                            ['name' => 'Lagoon/Estuary', 'target' => ''],
                                            ['name' => 'Lake', 'target' => ''],
                                            ['name' => 'Lake - Chain of Lakes', 'target' => ''],
                                            ['name' => 'Marina', 'target' => ''],
                                            ['name' => 'Pond', 'target' => ''],
                                            ['name' => 'River', 'target' => ''],
                                            ['name' => 'None', 'target' => ''],
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="water_view[]" id="water_view"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($water_views as $water_view)
                                            <option value="{{ $water_view['name'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                data-target="{{ $water_view['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $water_view['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                @php
                                    $waterExtraVacant = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterExtraVacant',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water Extras Needed:</label>
                                <select class="grid-picker" name="has_water_extra" id="waterExtraVacant"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterExtraVacant as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
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
                                            ['name' => 'None', 'target' => ''],
                                        ];
                            @endphp
                            <div class="form-group" id="waterExtraVacantOpt" style="display: none">
                                <select class="grid-picker" name="water_extras[]" id="water_extras"
                                    style="justify-content: flex-start;" multiple>
                                    <option value="">Select</option>
                                    @foreach ($water_extras as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                @php
                                    $waterFrontageVacant = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterFrontageVacant',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water Frontage Needed:</label>
                                <select class="grid-picker" name="has_water_frontage" id="waterFrontageVacant"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterFrontageVacant as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $water_frontage = [
                                    ['name' => 'Bay/Harbor', 'target' => ''],
                                    ['name' => 'Bayou', 'target' => ''],
                                    ['name' => 'Beach', 'target' => ''],
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
                                    ['name' => 'Marina', 'target' => ''],
                                    ['name' => 'Pond', 'target' => ''],
                                    ['name' => 'River', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group" id="waterFrontageVacantOpt" style="display: none">
                                <select class="grid-picker" name="water_frontage[]" id="water_frontage"
                                    style="justify-content: flex-start;" multiple>
                                    <option value="">Select</option>
                                    @foreach ($water_frontage as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                @php
                                    $waterAccessVacant = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.waterAccessVacant',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Water Access Needed:</label>
                                <select class="grid-picker" name="has_water_access" id="waterAccessVacant"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($waterAccessVacant as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $water_access = [
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
                                ];
                            @endphp
                            <div class="form-group" id="waterAccessVacantOpt" style="display: none;">
                                <select class="grid-picker" name="water_access[]" id="water_access"
                                    style="justify-content: flex-start;" multiple>
                                    <option value="">Select</option>
                                    @foreach ($water_access as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group ">
                                @php
                                    $dockVac = [
                                        [
                                            'name' => 'Yes',
                                            'target' => '.dockVac',
                                            'icon' => 'fa-regular fa-circle-check',
                                        ],
                                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                                        [
                                            'name' => 'Optional',
                                            'target' => '.dockVac',
                                            'icon' => 'fa-regular fa-circle-question',
                                        ],
                                    ];
                                @endphp
                                <label class="fw-bold">Dock Needed:</label>
                                <select class="grid-picker" name="has_dock" id="hasDock3"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($dockVac as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group" id="dockVac" style="display: none">
                                    @php
                                        $dockVac2 = [
                                            ['name' => '2 Point Moorage', 'target' => ''],
                                            ['name' => '3 Point Moorage', 'target' => ''],
                                            ['name' => '4 Point Moorage', 'target' => ''],
                                            ['name' => 'CATV', 'target' => ''],
                                            ['name' => 'Clubhouse', 'target' => ''],
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
                                            ['name' => 'Dock w/Water Supply', 'target' => ''],
                                            ['name' => 'Dock w/o Water Supply', 'target' => ''],
                                            ['name' => 'Dry Dock', 'target' => ''],
                                            ['name' => 'Fish Cleaning Station', 'target' => ''],
                                            ['name' => 'Floating Dock', 'target' => ''],
                                            ['name' => 'Harbormaster', 'target' => ''],
                                            ['name' => 'Internet', 'target' => ''],
                                            ['name' => 'Lift', 'target' => ''],
                                            ['name' => 'Restroom/Shower', 'target' => ''],
                                            ['name' => 'Wet Dock', 'target' => ''],
                                            ['name' => 'None', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.dock_vac_other_opt'],
                                        ];
                                    @endphp
                                    <select class="grid-picker" name="dock[]" id="dock2"
                                        style="justify-content: flex-start;" multiple>
                                        <option value="">Select</option>
                                        @foreach ($dockVac2 as $item)
                                            <option value="{{ $item['name'] }}"
                                                data-icon="<i class='fa-regular fa-circle-check'></i>"
                                                data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);">
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group dock_vac_other_opt d-none">
                                    <label class="fw-bold">Dock Description Needed:</label>
                                    <input type="text" name="dockDescription" id=""
                                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                                </div>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="52">
                        <div class="form-group">
                            <label class="fw-bold">Would the buyer be interested in purchasing in a community with an
                                HOA/Condo/Community association?</label>
                            <select class="grid-picker" name="buyer_intrest_in_purchasing"
                                id="buyer_intrest_in_purchasing" style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.buyer_is_intrested';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group buyer_is_intrested">
                            <label class="fw-bold">What is the maximum monthly condo and/or HOA fee that the buyer will accept?</label>
                            <input type="number" name="maximum_monthly_condo" id="maximum_monthly_condo"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                        </div>
                    </div>
                    <div class="wizard-step" data-step="53">
                        <span class="vacantFields">
                            <div class="form-group">
                                <label class="fw-bold">Please provide a detailed description of the buyer’s specific
                                    requirements and preferences for buying a property:</label>
                                <textarea name="description_buyer_specific" id="buyer_specific_requirements" class="form-control" cols="15"
                                    rows="5" required></textarea>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Buyer’s Agent Representation:</label>
                                <div class="form-group">
                                    <label class="fw-bold">Does the buyer have a real estate agent representing them?</label>
                                    <select class="grid-picker" name="buyerHaveAgentRepresentation" id=""
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $item)
                                            @php
                                                if ($item['name'] == 'Yes') {
                                                    $target = '.buyers_agent_commission_vacant';
                                                } else {
                                                    $target = '';
                                                }
                                            @endphp
                                            <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group d-none buyers_agent_commission_vacant">
                                        <label class="fw-bold">Does the buyer request that the seller pay the buyer’s agent
                                            commission?</label>
                                        <select class="grid-picker" name="buyersAgentCommissionRequested" id=""
                                            style="justify-content: flex-start;" required>
                                            <option value="">Select</option>
                                            @foreach ($yes_or_nos as $item)
                                                @php
                                                    if ($item['name'] == 'Yes') {
                                                        $target = '.buyers_agent_compensation_vacant';
                                                    } else {
                                                        $target = '.buyer_agent_comp_not_offered_vacant';
                                                    }
                                                @endphp
                                                <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                                    class="card flex-row" style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group d-none buyer_agent_comp_not_offered_vacant">
                                        <label class="fw-bold">Would the buyer be willing to pay an agent's commission for
                                            bringing a property the
                                            buyer ends up purchasing if the seller does not agree to pay the commission?</label>
                                        @php
                                            $buyersAgentCompensationNotOffered = [
                                                ['name' => 'Yes', 'icon' => 'fa-regular fa-circle-check', 'target' => ''],
                                                ['name' => 'No', 'icon' => 'fa-regular fa-circle-xmark', 'target' => ''],
                                            ];
                                        @endphp
                                        <select class="grid-picker" name="buyersAgentCompensationNotOffered" id=""
                                            style="justify-content: flex-start;">
                                            <option value="">Select</option>
                                            @foreach ($buyersAgentCompensationNotOffered as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row" style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group d-none buyers_agent_compensation_vacant">
                                        <label class="fw-bold">What compensation is the buyer requesting the seller to pay to the
                                            buyer’s agent?</label>
                                        @php
                                            $buyersAgentCompensation = [
                                                ['name' => '2%', 'target' => ''],
                                                ['name' => '2.5%', 'target' => ''],
                                                ['name' => '3%', 'target' => ''],
                                                ['name' => 'Other', 'target' => '.buyers_agent_comp_vacant'],
                                            ];
                                        @endphp
                                        <select class="grid-picker" name="buyersAgentCompensationRequested" id=""
                                            style="justify-content: flex-start;">
                                            <option value="">Select</option>
                                            @foreach ($buyersAgentCompensation as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-row" style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group buyers_agent_comp_vacant d-none">
                                        <div class="d-flex justify-content-between aalign-items-center">
                                            <label class="fw-bold">What compensation is the buyer requesting the seller to pay to
                                                the buyer’s agent?</label>
                                            <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                                                <button type="button" class="select-btn me-1 active"
                                                    data-type="percent">%</button>
                                                <button type="button" class="select-btn" data-type="amount">$</button>
                                            </div>
                                        </div>
                                        <input type="text" name="buyersAgentCompensationRequestedAmount" id=""
                                            class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                                    </div>
                                </div>
                            </div>
                        </span>
                    </div>
                    <div class="wizard-step" data-step="54">
                        <div class="form-group">
                            <label class="fw-bold">Is the buyer currently selling a property?</label>
                            <select class="grid-picker" name="isBuyerCurrentlySellingProperty" id=""
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.link_to_the_property_listing_vacant';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group link_to_the_property_listing_vacant d-none">
                                <label class="fw-bold">Link to the Property listing on Bid Your Offer:</label>
                                <input type="text" name="linkToThePropertyListing" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-link" required>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="55">
                        <h4>For a more personalized listing, you can include a picture of yourself and/or include a video of
                            yourself providing additional information about your background and criteria.</h4>
                        <div class="row form-group">
                            <div class="col-6">
                                <label class="fw-bold">First Name:</label>
                                <input type="text" name="agent_first_name" id="first_name" placeholder=""
                                    value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-user" required>
                            </div>
                            <div class="col-6">
                                <label class="fw-bold">Last Name:</label>
                                <input type="text" name="agent_last_name" id="last_name" placeholder=""
                                    value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-user" required>
                            </div>
                        </div>
                        <div class="row form-group">
                            <div class="col-6">
                                <label class="fw-bold">Phone Number:</label>
                                <input type="text" name="agent_phone" id="agent_phone" placeholder=""
                                    value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-phone">
                            </div>
                            <div class="col-6">
                                <label class="fw-bold">Email:</label>
                                <input type="text" name="agent_email" id="agent_email" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-envelope"
                                    value="{{ Auth::user()->email }}" required>
                            </div>
                        </div>
                        @if(Auth::user()->user_type == "agent")
                        <div class="row form-group">
                            <div class="col-6">
                                <label class="fw-bold">Brokerage:</label>
                                <input type="text" name="agent_brokerage" id="agent_brokerage" placeholder=""
                                    value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-handshake">
                            </div>
                            <div class="col-6">
                                <label class="fw-bold">Real Estate License #: </label>
                                <input type="text" name="license" id="agent_mls_id" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-id-card"
                                    value="{{ optional(Auth::user())->mls_id }}">
                            </div>
                        </div>
                        <div class="row form-group">
                            <div class="col-6">
                                <label class="fw-bold">NAR Member ID (NRDS ID): </label>
                                <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-id-card-clip"
                                    value="{{ optional(Auth::user())->mls_id }}">
                            </div>
                            {{-- <div class="col-6">
                                <label class="fw-bold">Listed By: Real Estate Agent:</label>
                                <input type="text" name="agent_commission_percent" id=""
                                    class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                            </div> --}}
                        </div>
                        @endif
                        <span class="vacantFields">
                            <div class="row form-group">
                                <div class="col-6">
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
                                <div class="col-6">
                                    <div class="upload">
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
                        </span>
                    </div>
                    <div class="d-flex justify-content-between form-group mt-4">
                        <div>
                            <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                        </div>
                        <div>
                            <a class="wizard-step-next btn btn-success btn-lg text-600" style="display: none;">Next</a>
                            <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
                                style="display: none;" id="saveBtn">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <template class="county_temp">
        <tr>
            <td>
                <div class="input-cover input-cover-0">
                    <label for="cities" class="input-icon"><i class="fa-solid fa-tree-city"></i></label>
                    <input type="text" name="counties[]" placeholder="" data-type="counties"
                        class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                        data-msg-required="Please enter county">
                </div>
            </td>
        </tr>
    </template>
    <template class="city_temp">
        <tr>
            <td>
                <div class="input-cover input-cover-0">
                    <label for="cities" class="input-icon"><i class="fa-solid fa-city "></i></label>
                    <input type="text" name="cities[]" placeholder="" data-type="cities"
                        class="form-control has-icon search_places has-icon" data-icon="fa-solid fa-city"
                        data-msg-required="Please enter city">
                </div>
            </td>
        </tr>
    </template>
    <template class="state_temp">
        <tr>
            <td>
                <div class="input-cover input-cover-0">
                    <label for="cities" class="input-icon"><i class="fa-solid fa-flag-usa"></i></label>
                    <input type="text" name="states[]" placeholder="" data-type="states"
                        class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
                        data-msg-required="Please enter state">
                </div>
            </td>
        </tr>
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
                $('#errorDiv').remove();
                if (this.files[0].size > 20000000) {
                    $(this).parent().after(
                        '<span id="errorDiv" style="color: red;">Please upload a file less than 20MB. Thanks!!</span>'
                    );
                    $(this).val('');
                    $('#saveBtn').prop('disabled', true);
                } else {
                    $('#saveBtn').prop('disabled', false);
                    $('#errorDiv').remove();
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

        function add_state_row() {
            var state_row = $('.state_temp').html();
            $('.state_btn_row').before(state_row);
            initialize();
        }
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
    </script>
    <script>
        function changePropertyType(p) {
            if (p == "Residential Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').show();
                $('.income-length').hide();
                $('.commercial-length').hide();
                $('.vacant_land-length').hide();
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
                $('.businessType').hide();
                $('.property_style').hide();
                $('.currentUse').hide();
                $('.resFields').each(function() {
                    $(this).find('select, input ,textarea').prop('disabled', false);
                });
                $('.commercialFields,.incomeFields,.vacantFields').each(function() {
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
                $('.businessType').hide();
                $('.property_style').hide();
                $('.currentUse').hide();
                $('.incomeFields, .resFields').each(function() {
                    $(this).find('select, input ,textarea').prop('disabled', false);
                });
                $('.commercialFields,.vacantFields').each(function() {
                    $(this).find('select, input ,textarea').prop('disabled', true);
                });

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
                $('.businessType').hide();
                $('.property_style').hide();
                $('.currentUse').hide();
                $('.commercialFields').each(function() {
                    $(this).find('select, input ,textarea').prop('disabled', false);
                });
                $('.resFields,.incomeFields,.vacantFields').each(function() {
                    $(this).find('select, input ,textarea').prop('disabled', true);
                });

            } else if (p == "Vacant Land") {
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
                $('.currentUse').show();
                $('.businessType').hide();
                $('.property_style').hide();
                $('.vacantFields').each(function() {
                    $(this).find('select, input ,textarea').prop('disabled', false);
                });
                $('.resFields,.incomeFields,.commercialFields').each(function() {
                    $(this).find('select, input ,textarea').prop('disabled', true);
                });

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
                $('.businessType').show();
                $('.property_style').show();
                $('.currentUse').hide();
                $('.commercialFields').each(function() {
                    $(this).find('select, input ,textarea').prop('disabled', false);
                });
                $('.resFields,.incomeFields,.vacantFields').each(function() {
                    $(this).find('select, input ,textarea').prop('disabled', true);
                });

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
                $('.businessType').hide();
                $('.property_style').hide();
                $('.currentUse').hide();


            }
        }
        $(function() {
            changePropertyType("");
        });
    </script>
    <script>
        function changePropertyStyle(p) {
            if (p == "Vacant Land") {
                $('.property_style_next_hide').addClass('d-none');
                $('.road_frontage_next_hide').addClass('d-none');
                $('.business_opportunity_show').addClass('d-none');
                $('.vacant_land_show').removeClass('d-none');
                $('.hide_vacant').removeClass('d-none');
                $('.show_vacant').removeClass('d-none');
                $('.vacant_land_hide').remove();
                $('.business_opportunity_remove').show();

            } else {
                $('.vacant_land_show').addClass('d-none');
                $('.hide_vacant').addClass('d-none');
                $('.road_frontage_next_hide').removeClass('d-none');
                $('.business_opportunity_show').removeClass('d-none');
                $('#remove_pets_question').remove();
                $('.show_vacant').addClass('d-none');
            }
        }
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
                var htm = `<label for="${id}" class="input-icon"><i class="${iconClass}" ></i></label>`;
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
                });
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

                if ($(elm).data('target') == ".assignmentYesRes") {
                    $('.assignmentNoRes').find('.options-container').children('.option-container').removeClass('active');
                    $(".assignmentYesResOptYes").addClass("d-none");
                }
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
            var v = $(elm).parent().children('select').val();
            $(elm).parent().children('select').trigger('change');
            check_custom();
        }

        function check_custom() {
            $('.option-container').each(function(i, elm) {
                var target = $(elm).data('target') || "";
                var is_active = $(elm).hasClass('active');
                if (target != "") {
                    if (is_active) {
                        console.log("is_active", is_active)
                        console.log("target", target);
                        $(target).removeClass("d-none");
                    } else {
                        $(target).addClass("d-none");
                    }
                }
            });
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
                });

                StepWizard.setStep();
                property_type;
                $('#property_type').on('change', function() {
                    property_type = $(this).val();
                });
                $('.wizard-step-next').click(function(e) {
                    if (v.form()) {
                        if ($('.wizard-step.active').next().is('.wizard-step')) {
                            console.log(StepWizard.currentStep)
                            $('.wizard-step.active').removeClass('active');

                            if (StepWizard.currentStep == 8 && property_type ==
                                'Income Property') {
                                StepWizard.nextStep = 11;
                                StepWizard.backStep = 8;
                            } else if (StepWizard.currentStep == 18 && property_type ==
                                'Income Property') {
                                StepWizard.nextStep = 21;
                                StepWizard.backStep = 18;
                            } else if (StepWizard.currentStep == 10 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 12;
                                StepWizard.backStep = 10;
                            } else if (StepWizard.currentStep == 5 && (property_type ==
                                    'Commercial Property' || property_type ==
                                    'Business Opportunity')) {
                                StepWizard.nextStep = 26;
                                StepWizard.backStep = 5;
                            } else if (StepWizard.currentStep == 30 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 32;
                                StepWizard.backStep = 30;
                            } else if (StepWizard.currentStep == 29 && property_type ==
                                'Business Opportunity') {
                                StepWizard.nextStep = 32;
                                StepWizard.backStep = 29;
                            } else if (StepWizard.currentStep == 6 && property_type ==
                                'Vacant Land'
                            ) {
                                StepWizard.nextStep = 43;
                                StepWizard.backStep = 6;
                            } else {
                                StepWizard.backStep = StepWizard.currentStep;
                            }
                            $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
                            StepWizard.setStep();
                            if (
                                StepWizard.currentStep == 25 &&
                                (property_type == 'Residential Property' || property_type ==
                                    'Income Property')
                            ) {
                                $('.wizard-step-next').hide();
                                $('.wizard-step-finish').show();
                            }
                            if (
                                StepWizard.currentStep == 42 &&
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
                        if (StepWizard.currentStep == 11 && property_type ==
                            'Income Property') {
                            StepWizard.backStep = 8;
                        } else if (StepWizard.currentStep == 12 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 10;
                        } else if (StepWizard.currentStep == 21 && property_type ==
                            'Income Property') {
                            StepWizard.backStep = 18;
                        } else if (StepWizard.currentStep == 32 && property_type ==
                            'Business Opportunity') {
                            StepWizard.backStep = 29;
                        } else if (StepWizard.currentStep == 26 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 5;
                        } else if (StepWizard.currentStep == 32 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 30;
                        }else if (StepWizard.currentStep == 25 && property_type ==
                            'Business Opportunity') {
                            StepWizard.backStep = 5;
                        } else if (StepWizard.currentStep == 43 && property_type ==
                            'Vacant Land'
                        ) {
                            StepWizard.backStep = 6;
                        } else {
                            StepWizard.backStep = StepWizard.currentStep - 1;
                        }
                    }
                });
                // Assuming the code provided is within a function or a document.ready block

                $('.wizard-step-finish').click(function(e) {

                    //Remove All the SLides Except THe Vacant Land
                    // if (property_type === 'Vacant Land (Current Use)') {
                    //   var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                    //     return parseInt($(this).attr('data-step')) >= 5 && parseInt($(this)
                    //       .attr('data-step')) <= 32;
                    //   });
                    //   $stepsToRemove.each(function() {
                    //     $(this).closest('div[data-step]').remove();
                    //   });
                    // }
                    //Remove All the SLides Except THe Residential and Commercial Property
                    // if (property_type == 'Residential Property' || property_type == 'Income Property') {
                    //   var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                    //     return parseInt($(this).attr('data-step')) >= 30 && parseInt($(this)
                    //       .attr('data-step')) <= 43;
                    //   });

                    //   $stepsToRemove.each(function() {
                    //     $(this).closest('div[data-step]').remove();
                    //   });
                    // }
                    //Remove All the SLides Except THe Commercial and Business Opportunity
                    // if (property_type === 'Commercial Property' || property_type ===
                    //   'Business Opportunity') {
                    //   var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                    //     var stepValue = parseInt($(this).attr('data-step'));
                    //     return (stepValue >= 5 && stepValue <= 19) || (stepValue >= 33 &&
                    //       stepValue <= 43);
                    //   });

                    //   $stepsToRemove.each(function() {
                    //     $(this).closest('div[data-step]').remove();
                    //   });
                    // }
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

                if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 25) {
                    // Calculate progress for Residential and Income property steps (5 to 19)
                    comp = 20 + (((StepWizard.currentStep - 5) / (25 - 5)) * 80);
                } else if (StepWizard.currentStep >= 25 && StepWizard.currentStep <= 42) {
                    // Calculate progress for Commercial and Business opportunity steps (20 to 32)
                    comp = 20 + (((StepWizard.currentStep - 25) / (42 - 25)) * 80);
                } else if (StepWizard.currentStep >= 42 && StepWizard.currentStep <= 55) {
                    // Calculate progress for Vacant land steps (33 to 43)
                    comp = 20 + (((StepWizard.currentStep - 42) / (55 - 42)) * 80);
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
        // Residential
        var toggleDiv = $('#custom_lease_option_res');
        let toggleDiv2 = $('#custom_lease_purchase_res');
        var toggleDivConventional = $('#conventionalOptions');
        $('#financingOptions').change(function() {
            if ($(this).val().includes('Lease Option')) {
                toggleDiv.show();
            } else {
                toggleDiv.hide();
            }

            if ($(this).val().includes('Lease Purchase')) {
                toggleDiv2.show();
            } else {
                toggleDiv2.hide();
            }

            if ($(this).val().includes('FHA') || $(this).val().includes('Conventional') || $(this).val().includes(
                    'VA') ||
                $(this).val().includes('USDA') || $(this).val().includes('Non-QM') || $(this).val().includes(
                    'Jumbo') || $(
                    this).val().includes('No-Doc')) {
                toggleDivConventional.show();
            } else {
                toggleDivConventional.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');

            // Hide all custom option fields first
            $('.form-group[data-target^=".custom_"]').addClass('d-none');

            // Show the specific custom option field based on the selected value
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });

        // Vacant
        var vacantDiv = $('#leaseOptionVacant');
        let vacantLeasePurchaseDiv = $('#leasePurchaseVacant');
        vacnatConventional = $('#conventionalVacant');
        $('#financingOptionsVacant').change(function() {
            if ($(this).val().includes('Lease Option')) {
                vacantDiv.show();
            } else {
                vacantDiv.hide();
            }

            if($(this).val().includes('Lease Purchase')){
                vacantLeasePurchaseDiv.show();
            }else{
                vacantLeasePurchaseDiv.hide();
            }

            if ($(this).val().includes('FHA') || $(this).val().includes('Conventional') || $(this).val().includes(
                    'VA') ||
                $(this).val().includes('USDA') || $(this).val().includes('Non-QM') || $(this).val().includes(
                    'Jumbo') || $(
                    this).val().includes('No-Doc')) {
                vacnatConventional.show();
            } else {
                vacnatConventional.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');

            // Hide all custom option fields first
            $('.form-group[data-target^=".custom_"]').addClass('d-none');

            // Show the specific custom option field based on the selected value
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        // Commercial 
        var waterExtraCom = $('#waterExtraCommercialOpt');
        // let toggleLeaseOptionDiv = $('#leasePurchaseCommercial');
        $('#waterExtraCommercial').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterExtraCom.show();
            } else {
                waterExtraCom.hide();
            }

            // if ($(this).val().includes('Lease Purchase')) {
            //     toggleLeaseOptionDiv.show();
            // } else {
            //     toggleLeaseOptionDiv.hide();
            // }

            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');

            // Hide all custom option fields first
            $('.form-group[data-target^=".custom_"]').addClass('d-none');

            // Show the specific custom option field based on the selected value
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterFrontCom = $('#waterFrontageCommercialOpt');
        $('#waterFrontageCommercial').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterFrontCom.show();
            } else {
                waterFrontCom.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');

            // Hide all custom option fields first
            $('.form-group[data-target^=".custom_"]').addClass('d-none');

            // Show the specific custom option field based on the selected value
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterAccessCom = $('#waterAccessCommercialOpt');
        $('#waterAccessCommercial').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterAccessCom.show();
            } else {
                waterAccessCom.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');

            // Hide all custom option fields first
            $('.form-group[data-target^=".custom_"]').addClass('d-none');

            // Show the specific custom option field based on the selected value
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterViewCom = $('#waterViewCommercialOpt');
        $('#waterViewCommercial').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterViewCom.show();
            } else {
                waterViewCom.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');

            // Hide all custom option fields first
            $('.form-group[data-target^=".custom_"]').addClass('d-none');

            // Show the specific custom option field based on the selected value
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterPreferenceCom = $('#waterPreferenceCommercialOpt');
        $('#waterPreferenceCommercial').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterPreferenceCom.show();
            } else {
                waterPreferenceCom.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');

            // Hide all custom option fields first
            $('.form-group[data-target^=".custom_"]').addClass('d-none');

            // Show the specific custom option field based on the selected value
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        // Lease Options 
        var commercialDiv = $('#leaseOptionCommercial');
        let leasePurchaseDiv = $('#leasePurchaseCommercial');
        var toggleDivCommercial = $('#conventionalCommercial');
        $('#financingOptionsCommercial').change(function() {
            if ($(this).val().includes('Lease Option')) {
                commercialDiv.show();
            } else {
                commercialDiv.hide();
            }

            if ($(this).val().includes('Lease Purchase')) {
                leasePurchaseDiv.show();
            } else {
                leasePurchaseDiv.hide();
            }

            if ($(this).val().includes('FHA') || $(this).val().includes('Conventional') || $(this).val().includes(
                    'VA') ||
                $(this).val().includes('USDA') || $(this).val().includes('Non-QM') || $(this).val().includes(
                    'Jumbo') || $(
                    this).val().includes('No-Doc')) {
                toggleDivCommercial.show();
            } else {
                toggleDivCommercial.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');

            // Hide all custom option fields first
            $('.form-group[data-target^=".custom_"]').addClass('d-none');

            // Show the specific custom option field based on the selected value
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });

        // Vacant 
        var waterExtraVacant = $('#waterExtraVacantOpt');
        $('#waterExtraVacant').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterExtraVacant.show();
            } else {
                waterExtraVacant.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterFrontVacant = $('#waterFrontageVacantOpt');
        $('#waterFrontageVacant').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterFrontVacant.show();
            } else {
                waterFrontVacant.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterAccessVacant = $('#waterAccessVacantOpt');
        $('#waterAccessVacant').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterAccessVacant.show();
            } else {
                waterAccessVacant.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterViewVacant = $('#waterViewVacantOpt');
        $('#waterViewVacant').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterViewVacant.show();
            } else {
                waterViewVacant.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterPreferenceVacant = $('#waterPreferenceVacantOpt');
        $('#waterPreferenceVacant').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterPreferenceVacant.show();
            } else {
                waterPreferenceVacant.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');

            // Hide all custom option fields first
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        // Residential
        var waterExtraRes = $('#waterExtraResOpt');
        $('#waterExtraRes').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterExtraRes.show();
            } else {
                waterExtraRes.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterFrontRes = $('#waterFrontageResOpt');
        $('#waterFrontageRes').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterFrontRes.show();
            } else {
                waterFrontRes.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterAccessRes = $('#waterAccessResOpt');
        $('#waterAccessRes').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterAccessRes.show();
            } else {
                waterAccessRes.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var dockRes = $('#dockRes');
        $('#hasDock').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                dockRes.show();
            } else {
                dockRes.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var dockRes2 = $('#dockRes2');
        $('#hasDock2').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                dockRes2.show();
            } else {
                dockRes2.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var dockVac = $('#dockVac');
        $('#hasDock3').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                dockVac.show();
            } else {
                dockVac.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterViewRes = $('#waterViewResOpt');
        $('#waterViewRes').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterViewRes.show();
            } else {
                waterViewRes.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });
        var waterPreferenceRes = $('#waterPreferenceResOpt');
        $('#waterPreferenceRes').change(function() {
            if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
                waterPreferenceRes.show();
            } else {
                waterPreferenceRes.hide();
            }
            var selectedOption = $(this).find(':selected');
            var targetClass = selectedOption.data('target');

            // Hide all custom option fields first
            $('.form-group[data-target^=".custom_"]').addClass('d-none');
            if (targetClass) {
                $(targetClass).removeClass('d-none');
            }
        });

        // $('.select-btn').click(function() {
        //     $(this).closest('.icon-select-btn-div').find('.select-btn').removeClass('active');
        //     $(this).addClass('active');
        //     let type = $(this).data('type');
        //     let elem = $(this).closest('.form-group').find('.input-icon').children();
        //     if (type == 'percent') {
        //         elem.removeClass("fa-solid fa-dollar-sign");
        //         elem.addClass("fa-solid fa-percent");
        //         elem.attr('data-symbol', 'percent');
        //     } else {
        //         elem.removeClass("fa-solid fa-percent");
        //         elem.addClass("fa-solid fa-dollar-sign");
        //         elem.attr('data-symbol', 'amount');
        //     }
        // })

        // $('.input-changable-icon').change(function(){
        //     let symbol = $(this).closest('.form-group').find('.input-icon').find('i').data('symbol');
        //     console.log("symbol", symbol);
        //     let value = $(this).val();
        //     console.log("value", value);
        //     if(value !== ""){
        //         $(this).val(`${value}${symbol == 'amount' ? "$" : "%"}`);
        //     }
        // })
    </script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
    </script>
@endpush
