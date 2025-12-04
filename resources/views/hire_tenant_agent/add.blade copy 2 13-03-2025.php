@extends('layouts.main')
@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
<style>
    @import url(https://fonts.googleapis.com/icon?family=Material+Icons);
    @import url("https://fonts.googleapis.com/css?family=Raleway");

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


    /* // variables */
    $base-color: cadetblue;
    $base-font: "Raleway", sans-serif;

    .wrapper {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
    }

    h1 {
        font-family: inherit;
        margin: 0 0 0.75em 0;
        color: desaturate($base-color, 15%);
        text-align: center;
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
                content: "add";
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
</style>
@endpush
@section('content')
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
                <form class="p-4 pt-0 mainform" action="{{ route('tenant.hire.agent.auction') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    @push('scripts')
                    <script>
                        $(function() {
                            $('#working_with_agent').change(function() {
                                var val = $(this).val();
                                if (val == "No") {
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
                        <div class="wizard-step" data-step="1">
                            <div class="form-group">
                                <label class="fw-bold">Is the tenant currently represented by another agent?</label>
                                <select class="grid-picker" name="working_with_agent" id="working_with_agent"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $yes_or_no)
                                    <option value="{{ $yes_or_no['name'] }}" data-target="{{ $yes_or_no['target'] }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                        {{ $yes_or_no['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="yes_message text-danger" style="font-size:18px; font-weight:bold;"></label>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="2">
                            <h4>Please provide the cities, counties, and state pertaining to the real estate location where the tenant intends to lease a property:</h4>
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
                                                    class="form-control has-icon  search_places" data-icon="fa-solid fa-city" placeholder=""
                                                    required>
                                            </td>
                                        </tr>
                                        <tr class="city_btn_row">
                                            <td><button type="button" class="btn btn-secondary btn-sm w-100" onclick="add_city_row();"><i
                                                        class="fa-solid fa-plus"></i> Add New
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
                                                </label><input type="text" name="counties[]" data-type="counties" id="counties"
                                                    class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city" placeholder=""
                                                    required>
                                            </td>
                                        </tr>
                                        <tr class="county_btn_row">
                                            <td><button type="button" class="btn btn-secondary btn-sm w-100" onclick="add_county_row();"><i
                                                        class="fa-solid fa-plus"></i> Add New
                                                    Row</button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">State:</label>
                                <input type="text" name="state" data-type="states" id="state"
                                    class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa" placeholder="" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="3">
                            <div class="form-group">
                                <label for="address" class="fw-bold">Listing Date:</label>
                                <input type="date" name="listing_date" id="listing_date" class="form-control has-icon search_places"
                                    data-icon="fa-regular fa-calendar-days" min="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="form-group">
                                <label for="address" class="fw-bold">Expiration Date:</label>
                                <input type="date" name="expiration_date" id="expiration_date"
                                    class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                    min="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="4">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Listing Type:
                                </label>
                                <div>
                                    @php
                                    $auction_types = [
                                    [
                                    'name' => 'Auction (Timer)',
                                    'icon' => '<i class="fa-solid fa-layer-group"></i>',
                                    'target' => '.auctionTimer',
                                    ],
                                    [
                                    'name' => 'Traditional (No Timer)',
                                    'icon' => '<i class="fa-solid fa-layer-group"></i>',
                                    'target' => '',
                                    ],
                                    ];
                                    @endphp
                                    <select name="auction_type" id="auction_type" class="grid-picker" style="justify-content: flex-start;"
                                        required>
                                        <option value=""></option>
                                        @foreach ($auction_types as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row "
                                            style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group auctionTimer d-none">
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
                                    <select name="auction_length" id="auction_length" class="auction_length grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($auction_lengths as $item)
                                        <option value="{{ $item['name'] }}" data-target=""
                                            class="card flex-row  {{ $item['class'] }}" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="5">
                            <div class="form-group">
                                <label for="listing_title" class="fw-bold">Title of Listing:</label>
                                <input type="text" name="listing_title" id="listing_title" class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="6">
                            @php
                            $property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];

                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Acceptable Property Styles:
                                </label>
                                <select class="grid-picker" name="property_type" id="property_type"
                                    onchange="changePropertyType(this.value);check_hoa();property_questions();">
                                    <option value="">Select</option>
                                    @foreach ($property_types as $row_pt)
                                    <option value="{{ $row_pt['name'] }}" class="card flex-column" style="width:calc(24% - 10px);"
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
                                    ['name' => 'Apartments', 'class' => 'residential-length'],
                                    ['name' => 'Townhouse', 'class' => 'residential-length'],
                                    ['name' => 'Villa', 'class' => 'residential-length'],
                                    ['name' => 'Condominium', 'class' => 'residential-length'],
                                    ['name' => 'Condo-Hotel', 'class' => 'residential-length'],
                                    ['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                                    ['name' => 'Farm', 'class' => 'residential-length'],
                                    ['name' => 'Garage Condo', 'class' => 'residential-length'],
                                    ['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
                                    ['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
                                    ['name' => 'Modular Home', 'class' => 'residential-length'],
                                    ['name' => '1/2 Duplex', 'class' => 'residential-length'],
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
                                    <select name="property_items[]" id="property_items" class="property_items grid-picker"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value=""></option>
                                        @foreach ($property_items as $item)
                                        <option value="{{ $item['name'] }}" data-target="" class="card flex-row {{ $item['class'] }}"
                                            style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="7">
                            @php
                            $lease = [
                            ['name' => 'Entire Property', 'target' => ''],
                            ['name' => 'Single Room', 'target' => ''],
                            ['name' => 'Open to Leasing Either the Entire Property or Single Room', 'target' => ''],
                            ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Is the tenant interested in leasing an entire property, a single room, or open
                                    to leasing either?</label>
                                <select class="grid-picker" name="tenant_lease" id="bedrooms" style="" required>
                                    <option value="">Select</option>
                                    </option>
                                    @foreach ($lease as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                        style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="8">
                            @php
                            $property_condition = [
                            ['name' => 'New Construction', 'target' => ''],
                            ['name' => 'Completely Updated: No updates needed.', 'target' => ''],
                            ['name' => 'Semi-updated: Needs minor updates.', 'target' => ''],
                            ['name' => 'Not Updated: Requires a complete update.', 'target' => ''],
                            ['name' => 'Open to any type of property condition.', 'target' => ''],
                            ['name' => 'Other ', 'target' => '.other_property_condition'],
                            ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Acceptable Property Conditions:
                                </label>
                                <select class="grid-picker" name="condition_prop[]" id="condition_prop" style="" multiple>
                                    <option value="">Select</option>
                                    @foreach ($property_condition as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                        style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                                <div class="form-group other_property_condition d-none">
                                    <label class="fw-bold" for="other_property_condition">Acceptable Property Conditions:</label>
                                    <input type="text" name="other_property_condition" class="form-control has-icon"
                                        data-icon="fa-solid fa-ruler-combined" required>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="9">
                            @php
                            $bedroomsRes = [
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
                            ['name' => 'Other', 'target' => '.other_bedrooms_res'],
                            ];

                            @endphp
                            <div class="form-group">
                                <label class="fw-bold"> Minimum Bedrooms Needed: </label>
                                <select class="grid-picker" name="bathrooms" id="bedrooms" style="" required>
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
                            <div class="form-group other_bedrooms_res d-none">
                                <label class="fw-bold" for="other_bedrooms">Minimum Bedrooms Needed: </label>
                                <input type="text" name="other_bedrooms" id="other_bedrooms_res" class="form-control has-icon"
                                    data-icon="fa-solid fa-bed" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="10">
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
                            ['name' => 'Other', 'target' => '.other_bathrooms'],
                            ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Minimum Bathrooms Needed: </label>
                                <select class="grid-picker" name="bathrooms" id="bathrooms" style="justify-content: center;"
                                    required>
                                    <option value="">Select</option>
                                    @foreach ($bathrooms as $bathroom)
                                    <option value="{{ $bathroom['name'] }}" data-target="{{ $bathroom['target'] }}"
                                        class="card flex-column" style="width:calc(20% - 10px);"
                                        data-icon='<i class="fa-solid fa-bath"></i>'>
                                        {{ $bathroom['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                                <div class="form-group other_bathrooms d-none">
                                    <label class="fw-bold" for="other_bathrooms">Minimum Bathrooms Needed:
                                    </label>
                                    <input type="text" name="other_bathrooms" id="other_bathrooms" class="form-control has-icon"
                                        data-icon="fa-solid fa-bath" required>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="11">
                            <div class="form-group  removeCommercial">
                                <label class="fw-bold">Minimum Heated Sqft Needed:</label>
                                <input type="text" name="minimum_heated_square" id="other_bathrooms"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                            <div class="form-group removeResidential">
                                <label class="fw-bold">Minimum Net Leaseable Sqft Needed:</label>
                                <input type="text" name="minimum_net_leasable_square" id="other_bathrooms"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="12">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Furnishings Needed:
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
                                <select name="tenant_require[]" id="tenant_require" class="grid-picker"
                                    style="justify-content: flex-start;" multiple>
                                    <option value=""></option>
                                    @foreach ($tenant_require as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(20% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="13">
                            <span class="resFields">
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
                                    <label class="fw-bold">Minimum Total Acreage Needed:</label>
                                    <select class="grid-picker" name="total_acreage" style="justify-content: flex-start;">
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
                            </span>
                        </div>
                        <div class="wizard-step" data-step="14">
                            <span class="resFields">

                                @php
                                $garageOptions = [
                                ['target' => '.garageYes', 'name' => 'Yes', 'icon' => 'fa-solid fa-warehouse'],
                                ['target' => '.garageNo', 'name' => 'No', 'icon' => 'fa-solid fa-warehouse'],
                                ['target' => '.garageOptional', 'name' => 'Optional', 'icon' => 'fa-solid fa-warehouse'],
                                ];
                                @endphp
                                <div class="row align-items-end mt-4 removeCommercial">
                                    <div class="col-md-12">
                                        <label class="fw-bold" for="heated_sqft">Garage Needed: </label>
                                        <div class="select2-parent">
                                            <select name="garageOptions" class="grid-picker" id="garageNeedOpt">
                                                <option value=""></option>
                                                @foreach ($garageOptions as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-column " style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                                @endforeach
                                            </select>
                                            <div class="form-group" id="garageNeedOptions" style="display: none;">
                                                <label class="fw-bold" for="heated_sqft">Garage Spaces Needed: </label>
                                                <input type="text" name="custom_garage" id="total_acreage"
                                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-warehouse" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @php
                                $carportOptions = [
                                ['target' => '.carportOptionsYes', 'name' => 'Yes', 'icon' => 'fa-solid fa-warehouse'],
                                ['target' => '.carportOptionsNo', 'name' => 'No', 'icon' => 'fa-solid fa-warehouse'],
                                [
                                'target' => '.carportOptionsOptional',
                                'name' => 'Optional',
                                'icon' => 'fa-solid fa-warehouse',
                                ],
                                ];
                                @endphp
                                <div class="row align-items-end mt-4 removeCommercial">
                                    <div class="col-md-12">
                                        <labal class="fw-bold" for="heated_sqft">Carport Needed: </labal>
                                        <div class="select2-parent">
                                            <select name="carportOptions" class="grid-picker" id="carportNeedOpt">
                                                <option value=""></option>
                                                @foreach ($carportOptions as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-column " style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                                @endforeach
                                            </select>
                                            <div class="form-group" id="carportNeedOptions" style="display: none;">
                                                <label class="fw-bold" for="heated_sqft">Carport Spaces Needed:</label>
                                                <input type="text" name="custom_carport" class="form-control has-icon hide_arrow"
                                                    data-icon="fa-solid fa-warehouse" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </span>
                            <div class="form-group removeResidential">
                                @php
                                $garageOption = [
                                ['target' => '.garageOptionYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '.garageOptionNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                                [
                                'target' => '.garageOptionOptional',
                                'name' => 'Optional',
                                'icon' => 'fa-regular fa-check-circle',
                                ],
                                ];
                                @endphp
                                <label class="fw-bold">Garage/Parking Features Needed?</label>
                                <select name="garageOption" class="grid-picker" id="garage" style="justify-content: flex-start;"
                                    required>
                                    <option value=""></option>
                                    @foreach ($garageOption as $item)
                                    <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                                        data-target="{{ $item['target'] }}" class="card flex-column"
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
                                    <select class="grid-picker" name="garage_parking[]" style="justify-content: flex-start;" multiple
                                        required>
                                        <option value="">Select</option>
                                        @foreach ($garageParking as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group other_garage d-none">
                                        <label class="fw-bold" for="other_garage">Garage/Parking Features :</label>
                                        <input type="text" name="other_garage" class="form-control has-icon hide_arrow"
                                            data-icon="fa-solid fa-warehouse" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="15">
                            @php
                            $poolOptions = [
                            ['target' => '.poolYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                            ['target' => '.poolNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                            ['target' => '.poolOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle'],
                            ];
                            @endphp
                            <div class="row align-items-end mt-4 removeCommercial">
                                <div class="col-md-12">
                                    <label class="fw-bold" for="heated_sqft">Pool Needed: </label>
                                    <div class="select2-parent">
                                        <select name="poolOptions" class="grid-picker" id="poolNeedOpt">
                                            @foreach ($poolOptions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                            @endforeach
                                        </select>
                                        <div class="select2-parent" id="poolNeedOptions" style="display: none">
                                            @php
                                            $poolOpt = [['target' => '', 'name' => 'Private'], ['target' => '', 'name' => 'Community']];
                                            @endphp
                                            <label class="fw-bold mt-1">Pool Type Needed:</label>
                                            <select name="poolOpt[]" class="grid-picker" id="" required multiple>
                                                @foreach ($poolOpt as $item)
                                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                    class="card flex-column " style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @php
                            $prefrenceOptions = [
                            ['target' => '.preferenceYesIncome', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                            ['target' => '.preferenceNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                            ['target' => '.preferenceOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle'],
                            ];
                            @endphp
                            <div class="row align-items-end mt-4">
                                <div class="col-md-12">
                                    <label class="fw-bold" for="heated_sqft">View Preference Needed: </label>
                                    <div class="select2-parent">
                                        <select name="prefrenceOptions" class="grid-picker" id="preferenceOpt">
                                            <option value=""></option>
                                            @foreach ($prefrenceOptions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                @php
                                $ifYesPreference = [
                                ['target' => '', 'name' => 'City', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'Garden', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'Golf Course', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'Greenbelt', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'Mountain(s)', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'Park', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'Tennis Court', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'Trees/Woods', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'Water', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '.preferenceNo', 'name' => 'Beach', 'icon' => 'fa-regular fa-circle-xmark'],
                                [
                                'target' => '.preferenceOtherIncome',
                                'name' => ' Other',
                                'icon' => 'fa-regular fa-check-circle',
                                ],
                                ];
                                @endphp
                                <div class="select2-parent" id="preferenceOptions" style="display: none">
                                    <select name="prefrence[]" class="grid-picker" id="" multiple required>
                                        @foreach ($ifYesPreference as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column " style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group preferenceOtherIncome d-none">
                                        <label class="fw-bold" for="heated_sqft">View Preference: </label>
                                        <input type="text" name="preferenceOther" id="total_acreage"
                                            class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="16">
                            @php
                            $petOptions = [
                            ['target' => '.petYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                            ['target' => '.petNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                            ];
                            @endphp
                            <div class="row align-items-end mt-4">
                                <div class="col-md-12">
                                    <labal class="fw-bold" for="heated_sqft">Does the tenant have a pet?</labal>
                                    <div class="select2-parent">
                                        <select name="petOptions" class="grid-picker" id="" required>
                                            <option value=""></option>
                                            @foreach ($petOptions as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group petYes d-none">
                                        <div class="form-group">
                                            <label class="fw-bold" for="heated_sqft">How many pets does the tenant have?</label>
                                            <input type="text" name="petsNumber" id="total_acreage"
                                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="fw-bold" for="heated_sqft">What type of pet(s) does the tenant have?</label>
                                            <input type="text" name="petsType" id="total_acreage"
                                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="fw-bold" for="heated_sqft">What breed(s) are the pet(s)?</label>
                                            <input type="text" name="petsBreed" id="total_acreage"
                                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="fw-bold" for="heated_sqft">What is the weight of the pet(s)?</label>
                                            <input type="text" name="petsWeight" id="total_acreage"
                                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="17">

                            <div class="form-group">
                                @php
                                $purchasing_props = [
                                ['target' => '.purchasingPropsYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '.purchasingPropsNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                                ];
                                @endphp
                                <label class="fw-bold" for="heated_sqft"> Is the tenant eligible or interested in leasing a
                                    property in leasing in 55-and-over communities? </label>
                                <div class="select2-parent">
                                    <select name="purchasing_props" class="grid-picker" id="">
                                        @foreach ($purchasing_props as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column " style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="18">
                            <span class="commercialFields">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Are there any non-negotiable amenities or property features that the tenant is seeking?
                                    </label>
                                    @php
                                    $non_negotialble = [
                                    [
                                    'target' => '.negotiable_terms_yes',
                                    'icon' => 'fa-regular fa-circle-check',
                                    'name' => 'Yes',
                                    ],
                                    ['target' => '', 'icon' => 'fa-regular fa-circle-xmark', 'name' => 'No'],
                                    ];
                                    @endphp
                                    <select name="has_non_negotiable_terms" id="has_non_negotiable_terms" class="grid-picker"
                                        style="justify-content: flex-start;">
                                        <option value=""></option>
                                        @foreach ($non_negotialble as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group  negotiable_terms_yes">
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
                                    <label class="fw-bold">What are the non-negotiable amenities or property features that the tenant will not accept for a property?</label>
                                    <select name="non_negotiable_terms[]" id="negotiable_terms" class="grid-picker"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble_terms_commercial as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(20% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check" style="font-size:24px;"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group negotiable_terms_commercial d-none">
                                        <label class="fw-bold" for="custom_non_negotiable_terms">What are the non-negotiable amenities or property features that the tenant will not accept for a property?
                                        </label>
                                        <input type="text" name="custom_non_negotiable_terms" class="form-control has-icon"
                                            data-icon="fa-solid fa-ruler-combined" required>
                                    </div>
                                </div>

                            </span>
                        </div>
                        <div class="wizard-step" data-step="19">
                            <span class="resFields">
                                <div class="form-group residential_and_income">
                                    <label class="fw-bold">
                                        Are there any non-negotiable amenities or property features that the tenant is seeking?

                                    </label>
                                    @php
                                    $non_negotialble = [
                                    ['target' => '.negotiable_terms', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                                    ];
                                    @endphp
                                    <select name="has_non_negotiable_terms" id="has_non_negotiable_terms" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group residential_and_income negotiable_terms">
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
                                    <label class="fw-bold">What are the non-negotiable amenities or property features that the tenant will not accept for a property?</label>
                                    <select name="non_negotiable_terms[]" id="negotiable_terms" class="grid-picker"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value=""></option>
                                        @foreach ($non_negotialble_terms_residential as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(20% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check" style="font-size:24px;"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group negotiable_terms_res d-none">
                                        <label class="fw-bold" for="custom_negotiable_terms"> What are the non-negotiable amenities or property features that the tenant will not accept for a property?
                                        </label>
                                        <input type="text" name="custom_negotiable_terms" class="form-control has-icon"
                                            data-icon="fa-solid fa-ruler-combined" required>
                                    </div>
                                </div>
                            </span>
                        </div>
                        <div class="wizard-step" data-step="20">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Maximum Monthly Lease Price:
                                </label>
                                <input type="number" name="budget" id="budget" placeholder="" class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="21">
                            <div class="form-group">
                                <label for="address" class="fw-bold">Offered Lease Date: </label>
                                <input type="date" name="lease_by" id="lease_by" class="form-control has-icon search_places"
                                    data-icon="fa-regular fa-calendar-days" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="22">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Offered Lease Length:
                                </label>
                                @php
                                $lease_for = [
                                ['target' => '', 'name' => '3 Months'],
                                ['target' => '', 'name' => '6 Months'],
                                ['target' => '', 'name' => '9 Months'],
                                ['target' => '', 'name' => '1 Year'],
                                ['target' => '', 'name' => '2 Years'],
                                ['target' => '', 'name' => '3-5 Years'],
                                ['target' => '', 'name' => '5+ Years'],
                                ['target' => '.custom_lease_for', 'name' => 'Other'],
                                ];
                                @endphp
                                <select name="lease_for" id="lease_for" class="grid-picker" style="justify-content: flex-start;"
                                    required>
                                    <option value=""></option>
                                    @foreach ($lease_for as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(20% - 10px);"
                                        data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_lease_for d-none">
                                <label class="fw-bold" for="custom_lease_for">Offered Lease Length:</label>
                                <input type="text" name="custom_lease_for" id="custom_lease_for" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-calendar-days" required>
                            </div>
                        </div>

                        <div class="wizard-step" data-step="23">
                            <div class="form-group">
                                <label class="fw-bold">
                                    How many people will be occupying the property?
                                </label>
                                <input type="text" name="people" id="custom_evicted"
                                    class="form-control has-icon" data-icon="fa-solid fa-users" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="24">
                            <div class="form-group">
                                <label class="fw-bold">
                                    What is the tenant's monthly net household income?
                                </label>
                                <input type="text" name="monthly_income" id="monthly_income" placeholder=""
                                    class="form-control has-icon" required data-icon="fa-solid fa-ruler-combined">
                            </div>
                        </div>
                        <div class="wizard-step" data-step="25">
                            <div class="form-group">
                                <label class="fw-bold">
                                    What is the tenant's credit score rating?
                                </label>
                                @php
                                $credit_score = [
                                ['target' => '', 'name' => 'Poor'],
                                ['target' => '', 'name' => 'Fair'],
                                ['target' => '', 'name' => 'Good'],
                                ['target' => '', 'name' => 'Very Good'],
                                ['target' => '', 'name' => 'Excellent'],
                                ];
                                @endphp
                                <select name="credit_score" id="credit_score" class="grid-picker"
                                    style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($credit_score as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(20% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="26">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Has the tenant had any prior evictions within the last 7 years?
                                </label>
                                @php
                                $evicted = [
                                ['target' => '.custom_evicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                                ];
                                @endphp
                                <select name="evicted" id="evicted" class="grid-picker" style="justify-content: flex-start;"
                                    required>
                                    <option value=""></option>
                                    @foreach ($evicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(25% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}" ></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_evicted d-none">
                                <label class="fw-bold" for="custom_evicted">Explain when and why:</label>
                                <input type="text" name="custom_evicted" id="custom_evicted"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="27">
                            <div class="form-group">
                                <label class="fw-bold">
                                    Has the tenant been convicted of a felony within the last 7 years?
                                </label>
                                @php
                                $convicted = [
                                ['target' => '.custom_convicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                                ];
                                @endphp
                                <select name="convicted" id="convicted" class="grid-picker" style="justify-content: flex-start;"
                                    required>
                                    <option value=""></option>
                                    @foreach ($convicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(25% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_convicted d-none">
                                <label class="fw-bold" for="custom_convicted">Explain when and what for:</label>
                                <input type="text" name="custom_convicted" id="custom_convicted"
                                    class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="28">
                            <div class="form-group">
                                <label class="fw-bold">
                                    What is the timeframe offered to the agent in the Tenant Agency Agreement?
                                </label>
                                @php
                                $tenant_terms = [
                                ['target' => '', 'name' => '1 Month'],
                                ['target' => '', 'name' => '2 Months'],
                                ['target' => '', 'name' => '3 Months'],
                                ['target' => '', 'name' => '4 Months'],
                                ['target' => '', 'name' => '5 Months'],
                                ['target' => '', 'name' => '6 Months'],
                                ['target' => '', 'name' => '9 Months'],
                                ['target' => '', 'name' => '12 Months'],
                                ['target' => '.custom_tenant_terms', 'name' => 'Other'],
                                ];
                                @endphp
                                <select name="tenant_terms" id="tenant_terms" class="grid-picker"
                                    style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($tenant_terms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_tenant_terms d-none">
                                <label class="fw-bold" for="custom_tenant_terms">What is the timeframe offered to the agent in the
                                    Tenant Agency Agreement?</label>
                                <input type="text" name="custom_tenant_terms" id="custom_tenant_terms"
                                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="29">
                            <div class="form-group">
                                <label class="fw-bold">
                                    What is the offered fee the tenant is willing to pay the agent for their assistance in finding them a
                                    property?
                                </label>
                                @php
                                $find_property = [
                                ['target' => '.findProps', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                                ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                                ];
                                @endphp
                                <select name="find_property" class="grid-picker" style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($find_property as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(25% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                                <div class="findProps d-none">
                                    @php
                                    $property_fee = [
                                    ['target' => '.costom_property_fee', 'name' => "1 month's rent"],
                                    ['target' => '', 'name' => "50% of one month's rent"],
                                    ['target' => '', 'name' => '10% of the value of the lease'],
                                    ['target' => '', 'name' => '6% of the value of the lease'],
                                    ['target' => '', 'name' => 'Negotiable'],
                                    ['target' => '.Other_property_fee', 'name' => 'Other'],
                                    ];
                                    @endphp
                                    <select name="property_fee" class="grid-picker" style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($property_fee as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-column" style="width:calc(20% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <div class="form-group Other_property_fee d-none">
                                        <label class="fw-bold" for="custom_convicted">What is the offered fee the tenant is willing to
                                            pay
                                            the agent for their assistance in finding them a property? </label>
                                        <br>
                                        <div class="form-group">
                                            <input type="text" name="Other_property_fee" class="form-control has-icon"
                                                data-icon="fa-solid fa-ruler-combined" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="30">
                            <div class="form-group">
                                <label class="fw-bold" for="important_aspects">What are the most important aspects the tenant will
                                    consider when hiring a real estate agent?</label>
                                <textarea class="form-control" type="text" name="important_aspects" rows="5" id="important_aspects"
                                    required></textarea>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="additional_details">What additional details would the tenant like to
                                    share
                                    with the agent?</label>
                                <textarea class="form-control" type="text" name="additional_details" rows="5" id="additional_details"></textarea>
                            </div>
                        </div>
                        <div class="wizard-step" data-step="31">
                            @php
                            $services_data = [
                            ['name' => 'List the tenant’s property on BidYourOffer.com.', 'target' => ''],
                            [
                            'name' =>
                            'Market the tenant’s property on various groups, pages, and affiliates, along with a QR code or listing link that leads to the listing on BidYourOffer.com.',
                            'target' => '',
                            ],
                            [
                            'name' =>
                            'Promote the tenant’s property on social media platforms with a QR code or listing link that leads to the listing on BidYourOffer.com.',
                            'target' => '',
                            ],
                            [
                            'name' =>
                            "Send prompt email notifications containing properties that meet the tenant's criteria as soon as they are listed, ensuring access to the most up-to-date listings.",
                            'target' => '',
                            ],
                            ['name' => 'Schedule and accompany the tenant on property viewings and showings', 'target' => ''],
                            ['name' => 'Schedule video tours of the tenant’s preferred properties', 'target' => ''],
                            [
                            'name' =>
                            'Assist with the tenant\'s rental application process, including providing guidance and support',
                            'target' => '',
                            ],
                            [
                            'name' =>
                            'Assist with the negotiation of lease terms, including rental price, lease duration, and any additional clauses or provisions',
                            'target' => '',
                            ],
                            [
                            'name' =>
                            'Coordinate and oversee the move-in process, including inspections and key handovers',
                            'target' => '',
                            ],
                            [
                            'name' =>
                            'Provide resources and recommendations for local amenities, such as schools, parks, and healthcare facilities',
                            'target' => '',
                            ],
                            [
                            'name' =>
                            'Facilitate communication between the tenant and the landlord or property management company before move-in',
                            'target' => '',
                            ],
                            [
                            'name' => 'Offer resources and information on tenant rights and responsibilities',
                            'target' => '',
                            ],
                            [
                            'name' =>
                            'Provide guidance on lease renewal options and negotiate rent adjustments if necessary',
                            'target' => '',
                            ],
                            ['target' => '.other_services', 'name' => 'Other - Add additional services as needed.'],
                            ];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Select the services that the tenant requests from an agent:</label>
                                <select class="grid-picker" name="services[]" id="services" multiple>
                                    <option value="">Select</option>
                                    @foreach ($services_data as $service)
                                    <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}"
                                        class="card flex-row" style="width:calc(100% - 0px);"
                                        data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                                        {{ $service['name'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group other_services d-none">
                                <label class="fw-bold ">What additional services would the tenant like to request from an agent?
                                </label>
                                <input type="text" name="other_services" id="" class="form-control has-icon"
                                    data-icon="fa-solid fa-hand-point-right">
                            </div>
                        </div>
                        <div class="wizard-step" data-step="32">
                            <h4>For a more personalized listing, you can include a picture of yourself and/or
                                include a video of yourself providing additional information about your background and criteria.</h4>
                            <div class="row form-group">
                                <label class="fw-bold">Tenant Info:</label>
                                <div class="col-md-6">
                                    <label class="fw-bold">First Name: <span class="text-danger"></span></label>
                                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-user-tie "
                                        name="first_name" id="first_name" required value="{{ Auth::user()->first_name }}" />
                                </div>
                                <div class="col-md-6">
                                    <label class="fw-bold">Last Name: <span class="text-danger"></span></label>
                                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-user-tie "
                                        name="last_name" id="last_name" required value="{{ Auth::user()->last_name }}" />
                                </div>
                                <div class="col-md-6">
                                    <label class="fw-bold">Phone Number: <span class="text-danger"></span></label>
                                    <input type="text" class="form-control has-icon" data-icon="fa-solid fa-phone" name="phone"
                                        id="phone" value="{{ Auth::user()->phone }}" required />
                                </div>
                                <div class="col-md-6">
                                    <label class="fw-bold">Email: <span class="text-danger"></span></label>
                                    <input type="email" class="form-control has-icon" data-icon="fa-solid fa-envelope"
                                        name="email" id="email" value="{{ Auth::user()->email }}" required />
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <div class="videoBox form-group">
                                        <label class="fw-bold mt-1">Video:</label>
                                        <div class="video bgImg"></div>
                                        <div class="videoDiv">
                                            <input type="file" class="fileuploader" name="video" style="display: none;"
                                                accept="video/*">
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
                                                        <input type="file" name="photo" class="image-upload" accept="image/*" />
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
                                <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                            </div>
                            <div>
                                <button type="button" class="wizard-step-next btn btn-success btn-lg text-600"
                                    style="display: none;">Next</button>
                                <button type="button" id="saveBtn" class="wizard-step-finish btn btn-success btn-lg text-600"
                                    style="display: none;">Save</button>
                            </div>
                        </div>
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
                <div class="input-cover input-cover-0"><label for="cities" class="input-icon"><i
                            class="fa-solid fa-city "></i></label><input type="text" name="cities[]" data-type="cities"
                        class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city" placeholder=""></div>
            </div>
        </td>
    </tr>
</template>
<template class="county_temp">
    <tr>
        <td>
            <div class="input-cover input-cover-0"><label for="county" class="input-icon"><i
                        class="fa-solid fa-tree-city "></i></label><input type="text" id="county" name="counties[]"
                    placeholder="County" data-type="counties" class="form-control has-icon search_places"
                    data-icon="fa-solid fa-tree-city" placeholder=""></div>
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
            if (this.files[0].size > 10000000) {
                $('.videoDiv').after('<span id="fileSizeError"  style="color: red;">Please upload a file less than 10MB. Thanks!!</span>');
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
                        $('.video').append('<img src="' + event.target.result + '" width="200" height="160">');
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
            $('.resFields').each(function() {
                $(this).find('select, input ,textarea').prop('disabled', true);
            });
            $('.commercialFields').each(function() {
                $(this).find('select, input ,textarea').prop('disabled', true);
            });

        }
    }
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
            });
        });
    });

    function checkselect(elm) {
        var i = $(elm).data('index');
        var mult = $(elm).parent().children('select').attr('multiple') || false;
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
                    poolNeedOpt: {
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
                    services: {
                        required: true,
                    },
                },
                messages: {
                    working_with_agent: {}
                },
                errorPlacement: function(error, element) {
                    error.insertAfter(element.parent());
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
            });
            $('.wizard-step-next').click(function(e) {
                console.log(StepWizard.currentStep)

                if (v.form()) {
                    if ($('.wizard-step.active').next().is('.wizard-step')) {
                        $('.wizard-step.active').removeClass('active');
                        if (StepWizard.currentStep == 7 && property_type ==
                            'Income Property') {
                            StepWizard.nextStep = 10;
                            StepWizard.backStep = 7;
                        } else if (StepWizard.currentStep == 17 && property_type ==
                            'Residential Property') {
                            StepWizard.nextStep = 19;
                            StepWizard.backStep = 17;
                        } else if (StepWizard.currentStep == 23 && property_type ==
                            'Business Opportunity') {
                            StepWizard.nextStep = 25;
                            StepWizard.backStep = 23;
                        } else if (StepWizard.currentStep == 15 && (property_type == 'Commercial Property')) {
                            // Exclude Step 11 from the condition
                            StepWizard.nextStep = 17;
                            StepWizard.backStep = 15;
                        } else if (StepWizard.currentStep == 18 && (property_type == 'Commercial Property')) {
                            // Exclude Step 11 from the condition
                            StepWizard.nextStep = 20;
                            StepWizard.backStep = 18;
                        } else if (StepWizard.currentStep == 4 && (property_type == 'Business Opportunity')) {
                            StepWizard.nextStep = 20;
                            StepWizard.backStep = 4;
                        } else if (StepWizard.currentStep == 4 && property_type ==
                            'Vacant Land (Current Use)'
                        ) {
                            StepWizard.nextStep = 32;
                            StepWizard.backStep = 4;
                        } else {
                            StepWizard.backStep = StepWizard.currentStep;
                        }
                        $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
                        StepWizard.setStep();
                        if (
                            StepWizard.currentStep == 32 &&
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
                    } else if (StepWizard.currentStep == 19 && property_type ==
                        'Residential Property') {

                        StepWizard.backStep = 17;
                    } else if (StepWizard.currentStep == 25 && property_type ==
                        'Business Opportunity') {

                        StepWizard.backStep = 23;
                    } else if (StepWizard.currentStep == 20 && property_type ==
                        'Commercial Property') {
                        StepWizard.backStep = 18;
                    } else if (StepWizard.currentStep == 17 && property_type ==
                        'Commercial Property') {
                        StepWizard.backStep = 15;
                    } else if (StepWizard.currentStep == 20 && property_type ==
                        'Business Opportunity') {
                        StepWizard.backStep = 4;
                    } else if (StepWizard.currentStep == 32 && property_type ==
                        'Vacant Land (Current Use)'
                    ) {

                        StepWizard.backStep = 4;
                    } else {
                        StepWizard.backStep = StepWizard.currentStep - 1;
                    }
                }
            });
            $('.wizard-step-finish').click(function(e) {
                if (property_type === 'Vacant Land (Current Use)') {
                    var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                        return parseInt($(this).attr('data-step')) >= 5 && parseInt($(this)
                            .attr('data-step')) <= 32;
                    });
                    $stepsToRemove.each(function() {
                        $(this).closest('div[data-step]').remove();
                    });
                }
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
            if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 32 && property_type ==
                'Residential Property') {
                comp = 20 + (((StepWizard.currentStep - 5) / (32 - 5)) * 80);
            } else if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 32 && property_type ==
                'Commercial Property') {
                comp = 20 + (((StepWizard.currentStep - 5) / (32 - 5)) * 80);
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
    });
</script>
<script>
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
    // GarageRes
    var toggleGarageRes = $('#garageNeedOptions');
    $('#garageNeedOpt').change(function() {
        //Disply and hide a div
        if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
            toggleGarageRes.show();
        } else {
            toggleGarageRes.hide();
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
    // CarportRes
    var toggleCarport = $('#carportNeedOptions');
    $('#carportNeedOpt').change(function() {
        //Disply and hide a div
        if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
            toggleCarport.show();
        } else {
            toggleCarport.hide();
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
    // Pool Needed
    var togglePoolNeed = $('#poolNeedOptions');
    $('#poolNeedOpt').change(function() {
        //Disply and hide a div
        if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
            togglePoolNeed.show();
        } else {
            togglePoolNeed.hide();
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
    // Pool Needed
    var togglePreference = $('#preferenceOptions');
    $('#preferenceOpt').change(function() {
        //Disply and hide a div
        if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
            togglePreference.show();
        } else {
            togglePreference.hide();
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
</script>
<script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
</script>
@endpush