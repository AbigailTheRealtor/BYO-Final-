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
    </style>
@endpush
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

                <form class="p-4 pt-0 mainform" action="{{ route('agent.tenant.criteria.auction.edit', @$auction->id) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Listing Date:</label>
                            <input type="date" name="listing_date" value="{{ @$auction->get->listing_date }}"
                                id="listing_date" class="form-control has-icon" data-icon="fa-solid fa-calendar" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Expiration Date:</label>
                            <input type="date" name="expiration_date" value="{{ @$auction->get->expiration_date }}"
                                id="expiration_date" class="form-control has-icon" data-icon="fa-solid fa-calendar"
                                required>
                        </div>
                    </div>



                    <div class="wizard-step">

                        <div class="form-group">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Cities</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if (gettype(@$auction->get->cities) == 'array')
                                        @foreach (@$auction->get->cities as $item)
                                            <tr>
                                                <td><input type="text" name="cities[]" value="{{ $item }}"
                                                        placeholder="City" data-type="cities" id="cities"
                                                        class="form-control  search_places" data-icon="fa-solid fa-city"
                                                        data-msg-required="Please enter city"
                                                        @if ($loop->iteration == 1) required @endif></td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td><input type="text" name="cities[]" placeholder="City" data-type="cities"
                                                    id="cities" class="form-control  search_places"
                                                    data-icon="fa-solid fa-city" data-msg-required="Please enter city"
                                                    required></td>
                                        </tr>
                                    @endif
                                    <tr class="city_btn_row">
                                        <td><button type="button" class="btn btn-secondary btn-sm w-100"
                                                onclick="add_city_row();"><i class="fa-solid fa-plus"></i> Add New
                                                Row</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            {{-- <label>City:</label>
                            <input type="text" name="city" value="{{@$auction->get->city}}" placeholder="City" data-type="cities" id="city"
                                class="form-control search_places has-icon" data-icon="fa-solid fa-city"
                                data-msg-required="Please enter city" required> --}}
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">County:</label>
                            <input type="text" name="county" value="{{ @$auction->get->county }}" placeholder="County"
                                data-type="counties" id="county" class="form-control search_places has-icon"
                                data-icon="fa-solid fa-building" data-msg-required="Please enter county" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">State:</label>
                            <input type="text" name="state" value="{{ @$auction->get->state }}" placeholder="State"
                                data-type="states" id="state" class="form-control search_places has-icon"
                                data-icon="fa-solid fa-flag-usa" data-msg-required="Please enter state" required>
                        </div>

                    </div>
                    <div class="wizard-step">
                        {{-- 9 June 2023 for Residential and Income Property --}}
                        @php
                            $property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">Interested Property Types:</label>
                            <select class="grid-picker" name="property_type" id="property_type"
                                onchange="changePropertyType(this.value);check_hoa();property_questions();" multiple
                                required>
                                <option value="">Select</option>
                                @foreach ($property_types as $row_pt)
                                    <option value="{{ $row_pt['name'] }}"
                                        {{ selected($row_pt['name'], @$auction->get->property_type) }}
                                        class="card flex-column" style="width:calc(24% - 10px);"
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
                                <select name="property_items" id="property_items" class="property_items grid-picker"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value=""></option>
                                    @foreach ($property_items as $item)
                                        <option value="{{ $item['name'] }}" data-target=""
                                            {{ selected($item['name'], @$auction->get->property_items) }}
                                            class="card flex-row fw-bold {{ $item['class'] }}"
                                            style="width:calc(33.33% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>


                        @push('scripts')
                            <script>
                                function check_hoa_values() {
                                    var hoa = $('#hoa_community').val();
                                    if (hoa == "No") {
                                        $('#hoa_fee_requirement').val("");
                                        $('.hoa_req_option').removeClass('active');
                                        $('#hoa_fee').val("");
                                    }
                                }

                                function check_hoa() {
                                    var pt = $('#property_type').val();
                                    if (pt == 'Residential Property' || pt == 'Income Property') {
                                        $('.hoa_questions').removeClass('d-none');
                                    } else {
                                        $('.hoa_questions').addClass('d-none');
                                    }
                                }
                            </script>
                            <script>
                                function show_hoa() {
                                    var h = $('#hoa_community').val();
                                    if (h == "Yes" || h == "Optional") {
                                        $('.hoa_fee_yes').show();
                                    } else {
                                        $('.hoa_fee_yes').hide();
                                    }
                                }
                                $(function() {
                                    show_hoa("");
                                });
                            </script>
                        @endpush


                        {{-- 9 June 2023 for Residential and Income Property --}}
                    </div>

                    {{-- 16 June 2023 for residentail --}}
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Maximum Monthly Lease Price:</label>
                            <input type="number" step="0.01" placeholder="0.00" name="monthly_price"
                                id="monthly_price" value="{{ @$auction->get->monthly_price }}"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar">
                        </div>

                        <div class="form-group" id="sfPrice">
                            <label class="fw-bold">Price SF/YR:</label>
                            <input type="number" value="{{ @$auction->get->sf_price }}" step="0.01"
                                placeholder="0.00" name="sf_price" id="sf_price" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar">
                        </div>
                    </div>

                    {{-- 16 June 2023 for Residential --}}
                    {{-- 16  June 2023 for Residential --}}
                    @php
                        $bedrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_bedrooms', 'name' => 'Other']];
                        $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.custom_bathrooms', 'name' => 'Other']];
                    @endphp
                    <div class="wizard-step" id="remove-bed">
                        <div class="form-group">
                            <label class="fw-bold">Minimum Bedrooms Needed:</label>
                            <select class="grid-picker" name="bedrooms" id="bedrooms" style="" required>
                                <option value="">Select</option>


                                @foreach ($bedrooms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->bedrooms) }} class="card flex-column"
                                        style="width:calc(9% - 10px);" data-icon='<i class="fa-solid fa-bed"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_bedrooms d-none">
                            <label class="fw-bold">Bedrooms:</label>
                            <input type="text" value="{{ $auction->get->custom_bedrooms }}" name="custom_bedrooms"
                                id="custom_bedrooms" class="form-control has-icon" data-icon="fa-solid fa-bed" required>
                        </div>
                    </div>
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Minimum Bathrooms Needed:</label>
                            <select class="grid-picker" name="bathrooms" id="bathrooms" style="" required>
                                <option value="">Select</option>
                                @foreach ($bathrooms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->bathrooms) }} class="card flex-column"
                                        style="width:calc(10% - 10px);" data-icon='<i class="fa-solid fa-bath"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_bathrooms d-none">
                            <label class="fw-bold">Bathrooms:</label>
                            <input type="text" value="{{ $auction->get->custom_bathrooms }}" name="custom_bathrooms"
                                id="custom_bathrooms" class="form-control has-icon" data-icon="fa-solid fa-bath"
                                required>
                        </div>
                    </div>
                    {{-- 16  June 2023 for Residential --}}


                    {{-- 16 June 2023 for Residential --}}
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Minimum Sqft Needed:</label>
                            <input type="number" value="{{ $auction->get->minimum_sqft_needed ?? '' }}" step="0.01"
                                placeholder="0.00" name="minimum_sqft_needed" id="minimum_sqft_needed"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar">

                        </div>
                    </div>
                    {{-- 16 June 2023 for Residential --}}
                    {{-- 16 June 2023 for Residential --}}

                    <div class="wizard-step" id="furnishings">
                        @php
                            $Furnishings = [['name' => 'Furnished', 'target' => ''], ['name' => 'Optional', 'target' => ''], ['name' => 'Partial', 'target' => ''], ['name' => 'Turnkey', 'target' => ''], ['name' => 'Unfurnished', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Furnishings:</label>
                            <select class="grid-picker" name="Furnishings" id="Furnishings"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($Furnishings as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->Furnishings) }}
                                        class="card flex-row pt-0 pb-0" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 16 June 2023 for Residential --}}
                    {{-- 16 June 2023 for Residential --}}

                    <div class="wizard-step">


                        {{-- 19 June for Commercial Property --}}
                        <div class="row commercial_show">
                            @php
                                $garage_spaces = [
                                    ['target' => '', 'name' => '1 to 5 Spaces', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => '6 to 12 Spaces', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => '13 to 18 Spaces', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => '19 to 30 Spaces', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Airplane Hangar', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Common', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Curb Parking', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Deeded', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Electric Vehicle Charging Station(s)', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Ground Level', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Lighted', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'None', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Other', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Over 30 Spaces', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Secured', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Under Building', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Underground', 'icon' => 'fa-regular fa-circle-check'],
                                    ['target' => '', 'name' => 'Valet', 'icon' => 'fa-regular fa-circle-check'],
                                ];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Garage/Parking Features:</label>
                                <select class="grid-picker" name="parking_feature_garage[]" id="parking_feature_garage"
                                    style="justify-content: flex-start;" required multiple>
                                    <option value="">Select</option>
                                    @foreach ($garage_spaces as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            {{ selected($item['name'], @$auction->get->parking_feature_garage) }}
                                            class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        {{-- 19 June for Commercial Property --}}




                        @php
                            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                            $yes_or_nos_opt = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'], ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question']];
                        @endphp
                        <div class="row residential_show">

                            <div class="form-group" id="pool">
                                <label class="fw-bold">Pool:</label>
                                <select class="grid-picker" name="pool" id="pool"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos_opt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            {{ selected($item['name'], @$auction->get->pool) }} class="card flex-row"
                                            style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">Carport:</label>
                                <select class="grid-picker" name="carport" id="carport"
                                    style="justify-content: flex-start;" onchange="show_carport_opt();">
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos_opt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            {{ selected($item['name'], @$auction->get->carport) }}
                                            class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $carport_options = [['name' => '1', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Other', 'target' => '.custom_carport']];
                            @endphp
                            <div class="form-group carport_opt">
                                <label class="fw-bold">How many carport spaces tenant need:</label>
                                <select class="grid-picker" name="carport_opt" id="carport_opt"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($carport_options as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            {{ selected($item['name'], @$auction->get->carport_opt) }}
                                            class="card flex-row" style="width:calc(9% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_carport d-none">
                                <label class="fw-bold">How many carport spaces tenant need:</label>
                                <input type="text" name="custom_carport" value="{{ $auction->get->custom_carport }}"
                                    id="custom_carport" class="form-control has-icon"
                                    data-icon="fa-regular fa-circle-check" required>
                            </div>


                            <div class="form-group">
                                <label class="fw-bold">Garage:</label>
                                <select class="grid-picker" name="garage" id="garage"
                                    style="justify-content: flex-start;" onchange="show_garage_opt();">
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos_opt as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            {{ selected($item['name'], @$auction->get->garage) }} class="card flex-row"
                                            style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $garage_options = [['name' => '1', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Other', 'target' => '.custom_garage']];
                            @endphp
                            <div class="form-group garage_opt">
                                <label class="fw-bold">How many garage spaces tenant need:</label>
                                <select class="grid-picker" name="garage_opt" id="garage_opt"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($garage_options as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            {{ selected($item['name'], @$auction->get->garage_opt) }}
                                            class="card flex-row" style="width:calc(9% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_garage d-none">
                                <label class="fw-bold">How many garage spaces tenant need:</label>
                                <input type="text" name="custom_garage" value="{{ $auction->get->custom_garage }}"
                                    id="custom_garage" class="form-control has-icon"
                                    data-icon="fa-regular fa-circle-check" required>
                            </div>
                            {{-- new --}}
                        </div>
                    </div>

                    {{-- 19 June 2023 for Commercial --}}
                    <div class="wizard-step add_commercial">
                        <h4>Rent Now Terms</h4>

                        <div class="form-group">
                            <label class="fw-bold">How many people will be occupying the property:</label>
                            <input type="number" name="how_many_occupying" id="how_many_occupying"
                                value="{{ $auction->get->how_many_occupying }}" class="form-control has-icon"
                                data-icon="fa-solid fa-ruler-combined">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Does the tenant smoke?</label>
                            <select class="grid-picker" name="is_tenant_smoke" id="any_non_negotiable_factors"
                                style="justify-content: flex-start;" required>
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
                                        {{ selected($item['name'], @$auction->get->garage_opt) }} class="card flex-row"
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">What is the tenant’s credit score?</label>
                            <input type="number" value="{{ $auction->get->tenant_credit_score }}"
                                name="tenant_credit_score" id="how_many_occupying" class="form-control has-icon"
                                data-icon="fa-solid fa-ruler-combined">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">What is the monthly household net income?</label>
                            <input type="number" value="{{ $auction->get->monthly_household_income }}" step="0.01"
                                placeholder="0.00" min="1" name="monthly_household_income"
                                id="monthly_household_income" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar" required>
                        </div>


                        <div class="form-group">
                            <label class="fw-bold">
                                Has tenant ever been convicted of a felony?
                            </label>
                            @php
                                $convicted = [['target' => '.custom_convicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                            @endphp
                            <select name="convicted" id="convicted" class="grid-picker"
                                style="justify-content: flex-start;">
                                <option value=""></option>
                                @foreach ($convicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->garage_opt) }} class="card flex-column"
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group custom_convicted d-none">
                            <label class="fw-bold" for="custom_convicted">Explain when and what for:</label>
                            <input type="text" name="custom_convicted" value="{{ @$auction->get->custom_convicted }}"
                                id="custom_convicted" placeholder="Explain when and what for" class="form-control"
                                data-icon="fa-solid fa-bath" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">
                                Has tenant ever been evicted?
                            </label>
                            @php
                                $evicted = [['target' => '.custom_evicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                            @endphp
                            <select name="evicted" id="evicted" class="grid-picker"
                                style="justify-content: flex-start;">
                                <option value=""></option>
                                @foreach ($evicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->evicted) }} class="card flex-column"
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group custom_evicted d-none">
                            <label class="fw-bold" for="custom_evicted_why">Explain when and why:</label>
                            <input type="text" name="custom_evicted" value="{{ $auction->get->custom_evicted }}"
                                id="custom_evicted" placeholder="Explain when and why" class="form-control"
                                data-icon="fa-solid fa-bath" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">
                                Has the tenant been involved in any prior lease violations
                            </label>
                            @php
                                $evicted = [['target' => '.custom_prior_lease', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                            @endphp
                            <select name="lease_violations" id="lease_violations" class="grid-picker"
                                style="justify-content: flex-start;">
                                <option value=""></option>
                                @foreach ($evicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->lease_violations) }}
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_prior_lease d-none">
                            <label class="fw-bold" for="custom_evicted">Explain when and why:</label>
                            <input type="text" name="lease_violation_why"
                                value="{{ $auction->get->lease_violation_why }}" id="lease_violation_why"
                                placeholder="Explain when and why" class="form-control" data-icon="fa-solid fa-bath"
                                required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">
                                Does the tenant have any outstanding balances with previous landlords?
                            </label>
                            @php
                                $evicted = [['target' => '.custom_evicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                            @endphp
                            <select name="previous_land" id="previous_land" class="grid-picker"
                                style="justify-content: flex-start;">
                                <option value=""></option>
                                @foreach ($evicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->previous_land) }}
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 19 June 2023 for Commercial --}}

                    {{-- 19 June 2023 for residential and Commercial --}}
                    <div class="wizard-step remove_commercial">
                        {{-- new --}}
                        <div class="form-group ">
                            <label class="fw-bold">Water View:</label>
                            <select class="grid-picker" name="has_water_view" id="has_water_view"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_view';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        {{ selected($item['name'], @$auction->get->has_water_view) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @php
                            $water_views = [['name' => 'Bay/Harbor - Full', 'target' => ''], ['name' => 'Bay/Harbor - Partial', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Canal', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Gulf/Ocean - Full', 'target' => ''], ['name' => 'Gulf/Ocean - Partial', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'None', 'target' => '']];
                        @endphp
                        <div class="form-group water_view d-none">
                            <label class="fw-bold">Water View:</label>
                            <select class="grid-picker" name="water_view[]" id="water_view"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($water_views as $water_view)
                                    <option value="{{ $water_view['name'] }}" data-target="{{ $water_view['target'] }}"
                                        {{ selected($item['name'], @$auction->get->has_water_view) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);">
                                        {{ $water_view['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group ">
                            <label class="fw-bold">Water Extras:</label>
                            <select class="grid-picker" name="has_water_extra" id="has_water_extra"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_extras';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        {{ selected($item['name'], @$auction->get->has_water_view) }}
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
                                ['name' => 'None', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group water_extras d-none ">
                            <label class="fw-bold">Water Extras:</label>
                            <select class="grid-picker" name="water_extras[]" id="water_extras"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($water_extras as $water_extra)
                                    <option value="{{ $water_extra['name'] }}"
                                        {{ selected($item['name'], @$auction->get->water_extras) }}
                                        data-target="{{ $water_extra['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_extra['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>





                        {{-- Chnages 31 May 2023 --}}

                        <div class="form-group ">
                            <label class="fw-bold">Water Frontage:</label>
                            <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
                                style="justify-content: flex-start;" required>
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
                                        {{ selected($item['name'], @$auction->get->has_water_fontage) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @php
                            $water_access = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Water Access:</label>
                            <select class="grid-picker" name="water_access[]" id="water_access"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($water_access as $water_access1)
                                    <option value="{{ $water_access1['name'] }}"
                                        {{ selected($item['name'], @$auction->get->water_access) }}
                                        data-target="{{ $water_access1['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_access1['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 19 June 2023 for residential and Commercial --}}

                    <div class="wizard-step remove_commercial">
                        <div class="form-group">
                            <label class="fw-bold">Does the tenant have a pet?</label>
                            <select class="grid-picker" name="has_pets" id="has_pets"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.pets_info';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        {{ selected($item['name'], @$auction->get->has_pets) }} class="card flex-row"
                                        style="width:calc(25% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="pets_info d-none">
                            <div class="form-group">
                                <label class="fw-bold">What type of pet does the tenant have?</label>
                                <input type="text" name="type_of_pet" value="{{ $auction->get->type_of_pet }}"
                                    id="type_of_pet" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">What is the breed of the pet?</label>
                                <input type="text" name="pets_bread" value="{{ $auction->get->pets_bread }}"
                                    id="pets_bread" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">How many pets does the tenant have?</label>
                                <input type="text" name="total_pets" value="{{ $auction->get->total_pets }}"
                                    id="total_pets" class="form-control" required>
                            </div>

                        </div>
                    </div>

                    {{-- 16 June 2023 for Residential --}}

                    {{-- 16 June 2023 for Residential --}}
                    <div class="wizard-step remove_commercial">
                        <div class="form-group">
                            <label class="fw-bold">Is the tenant eligible for 55+ and over communities?</label>
                            <select class="grid-picker" name="is_tenant_eligible" id="is_tenant_eligible"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->is_tenant_eligible) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    {{-- 16 June 2023 for Residential --}}



                    {{-- 16 June 2023 for Residential --}}

                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Tenant's specific requirements</label>
                            <textarea name="description_buyer_specific" value="{{ $auction->get->description_buyer_specific }}"
                                id="buyer_specific_requirements" class="form-control" cols="15" rows="5" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Prefrence for Buying a Property</label>
                            <textarea name="description_preference" value="{{ $auction->get->description_preference }}"
                                id="prefrence_of_buying_a_property" class="form-control" cols="15" rows="5" required></textarea>
                        </div>



                        <div class="form-group ">
                            <label class="fw-bold">Are there any non-negotiable factors that the tenant will not accept for
                                a property?</label>
                            <select class="grid-picker" name="any_non_negotiable_factors" id="any_non_negotiable_factors"
                                style="justify-content: flex-start;" required>
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
                                        {{ selected($item['name'], @$auction->get->any_non_negotiable_factors) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 19 June 2023  for commercial --}}


                    <div class="wizard-step remove_commercial">
                        <h4>Rent Now Terms</h4>

                        <div class="form-group">
                            <label class="fw-bold">How many people will be occupying the property:</label>
                            <input type="number" value="{{ $auction->get->how_many_occupying }}"
                                name="how_many_occupying" id="how_many_occupying" class="form-control has-icon"
                                data-icon="fa-solid fa-ruler-combined">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Does the tenant smoke?</label>
                            <select class="grid-picker" name="is_tenant_smoke" id="any_non_negotiable_factors"
                                style="justify-content: flex-start;" required>
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
                                        {{ selected($item['name'], @$auction->get->is_tenant_smoke) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">What is the tenant’s credit score?</label>
                            <input type="number" value="{{ $auction->get->tenant_credit_score }}"
                                name="tenant_credit_score" id="how_many_occupying" class="form-control has-icon"
                                data-icon="fa-solid fa-ruler-combined">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">What is the monthly household net income?</label>
                            <input type="number" step="0.01" value="{{ $auction->get->monthly_household_income }}"
                                placeholder="0.00" min="1" name="monthly_household_income"
                                id="monthly_household_income" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar" required>
                        </div>


                        <div class="form-group">
                            <label class="fw-bold">
                                Has tenant ever been convicted of a felony?
                            </label>
                            @php
                                $convicted = [['target' => '.custom_convicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                            @endphp
                            <select name="convicted" id="convicted" class="grid-picker"
                                style="justify-content: flex-start;">
                                <option value=""></option>
                                @foreach ($convicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->convicted) }} class="card flex-column"
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group custom_convicted d-none">
                            <label class="fw-bold" for="custom_convicted">Explain when and what for:</label>
                            <input type="text" value="{{ $auction->get->custom_convicted }}" name="custom_convicted"
                                id="custom_convicted" placeholder="Explain when and what for" class="form-control"
                                data-icon="fa-solid fa-bath" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">
                                Has tenant ever been evicted?
                            </label>
                            @php
                                $evicted = [['target' => '.custom_evicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                            @endphp
                            <select name="evicted" id="evicted" class="grid-picker"
                                style="justify-content: flex-start;">
                                <option value=""></option>
                                @foreach ($evicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->evicted) }} class="card flex-column"
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group custom_evicted d-none">
                            <label class="fw-bold" for="custom_evicted_why">Explain when and why:</label>
                            <input type="text" name="custom_evicted" value="{{ $auction->get->custom_evicted }}"
                                id="custom_evicted" placeholder="Explain when and why" class="form-control"
                                data-icon="fa-solid fa-bath" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">
                                Has the tenant been involved in any prior lease violations
                            </label>
                            @php
                                $evicted = [['target' => '.custom_prior_lease', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                            @endphp
                            <select name="lease_violations" id="lease_violations" class="grid-picker"
                                style="justify-content: flex-start;">
                                <option value=""></option>
                                @foreach ($evicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->lease_violations) }}
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_prior_lease d-none">
                            <label class="fw-bold" for="custom_evicted">Explain when and why:</label>
                            <input type="text" value="{{ $auction->get->lease_violation_why }}"
                                name="lease_violation_why" id="lease_violation_why" placeholder="Explain when and why"
                                class="form-control" data-icon="fa-solid fa-bath" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">
                                Does the tenant have any outstanding balances with previous landlords?
                            </label>
                            @php
                                $evicted = [['target' => '.custom_evicted', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark']];
                            @endphp
                            <select name="previous_land" id="previous_land" class="grid-picker"
                                style="justify-content: flex-start;">
                                <option value=""></option>
                                @foreach ($evicted as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->previous_land) }}
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>


                    </div>
                    {{-- 19 June 2023  for commercial --}}
                    {{-- 16 June 2023 for Residential --}}
                    <div class="wizard-step">
                        {{-- 12 June 2023 for Residential and Income --}}
                        <h4>Agent Contact: </h4>
                        <div class="form-group">
                            <label class="fw-bold">First Name:</label>
                            <input type="text" name="agent_first_name" id="agent_first_name" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-user"
                                value="{{ Auth::user()->first_name }}" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Last Name:</label>
                            <input type="text" name="agent_last_name" id="agent_last_name" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-user"
                                value="{{ Auth::user()->last_name }}" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Phone Number:</label>
                            <input type="text" name="agent_phone" id="agent_phone" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-phone"
                                value="{{ Auth::user()->phone }}" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Email:</label>
                            <input type="text" name="agent_email" id="agent_email" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-envelope"
                                value="{{ Auth::user()->email }}" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Brokerage:</label>
                            <input type="text" name="agent_brokerage" id="agent_brokerage" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-icon"
                                value="{{ Auth::user()->brokerage }}" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Real Estate License #:</label>
                            <input type="text" name="agent_license_no" id="agent_license_no" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-id-card"
                                value="{{ Auth::user()->license_no }}" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">MLS ID #:</label>
                            <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                                value="{{ Auth::user()->mls_id }}" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Agent Commission (Paid by Seller): $ or %</label>
                            <input type="text" name="agent_commission_percent" id=""
                                class="form-control has-icon"
                                value="{{ $auction->get->agent_commission_percent ?? '' }}"
                                data-icon="fa-solid fa-percent" required>
                        </div>
                        {{-- 12 June 2023 for Residential and Income --}}

                    </div>
                    {{-- 16 June 2023 for Residential --}}
                    {{-- 16 June 2023 for Residential --}}

                    <div class="wizard-step">
                        @php
                            $lease_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '1 year', 'target' => ''], ['name' => '2 years', 'target' => ''], ['name' => '3-5 years', 'target' => ''], ['name' => '5+ years', 'target' => ''], ['name' => 'Month to Month', 'target' => ''], ['name' => 'Other', 'target' => '.custom_terms']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Lease Terms:</label>
                            <select class="grid-picker" name="lease_terms1" id="lease_terms"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($lease_terms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->lease_terms1) }}
                                        class="card flex-row fw-bold pt-0 pb-0" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold" for="offere_rental_price">Offered Rental Price:</label>
                            <input type="text" name="offer_rental_price"
                                value="{{ $auction->get->offer_rental_price ?? '' }}" placeholder="Enter Lease Terms"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                data-msg-required="Please enter Custom Lease terms" required>
                        </div>
                        @php
                            $lease_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '1 year', 'target' => ''], ['name' => '2 years', 'target' => ''], ['name' => '3-5 years', 'target' => ''], ['name' => '5+ years', 'target' => ''], ['name' => 'Month to Month', 'target' => ''], ['name' => 'Other', 'target' => '.custom_terms']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Offered Lease Terms:</label>
                            <select class="grid-picker" name="offered_leased_terms" id="lease_terms"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($lease_terms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->offered_leased_terms) }}
                                        class="card flex-row fw-bold pt-0 pb-0" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_terms d-none">
                            <label class="fw-bold" for="custom_terms">Offered Lease Terms:</label>
                            <input type="text" value="{{ $auction->get->offered_custom_terms ?? '' }}"
                                name="offered_custom_terms" id="custom_terms" placeholder="Enter Lease Terms"
                                class="form-control has-icon" data-msg-required="Please enter Custom Lease terms"
                                required>
                        </div>
                        <div class="wizard-step">
                            <div class="form-group">
                                <label class="fw-bold">Ideal Move in Date:</label>
                                <input type="date" value="{{ $auction->get->ideal_move_in_date ?? '' }}"
                                    name="ideal_move_in_date" id="move_in_date" class="form-control has-icon"
                                    data-icon="fa-solid fa-calendar">
                            </div>
                        </div>
                        <div class="wizard-step">
                            <div class="form-group">
                                <label class="fw-bold">What is required at move-in?</label>
                                <input type="text" name="required_at_move_in"
                                    value="{{ $auction->get->required_at_move_in ?? '' }}" id="required_at_move_in"
                                    class="form-control has-icon" data-icon="fa-solid fa-calendar">
                            </div>
                        </div>
                        <div class="wizard-step">
                            <div class="form-group">
                                <label class="fw-bold">Is there an application?</label>
                                <select class="grid-picker" name="is_application" id="is_application"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($lease_terms as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            {{ selected($item['name'], @$auction->get->is_application) }}
                                            class="card flex-row fw-bold pt-0 pb-0" style="width:calc(25% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="wizard-step">
                            <div class="form-group">
                                <label class="fw-bold">Will the landlord accept a pet If the tenant has one?</label>
                                <select class="grid-picker" name="accept_pet" id="accept_pet"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($lease_terms as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            {{ selected($item['name'], @$auction->get->accept_pet) }}
                                            class="card flex-row fw-bold pt-0 pb-0" style="width:calc(25% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    {{-- 16 June 2023 for Residential --}}
                    {{-- 16 June 2023 for Residential --}}
                    <div class="wizard-step ">
                        <div class="form-group ">
                            <label class="fw-bold">Description and Additional Details: </label>
                            <textarea name="description_and_additional_terms"
                                value="{{ $auction->get->description_and_additional_terms ?? '' }}" id="buyer_specific_requirements"
                                class="form-control" cols="15" rows="5" required></textarea>
                        </div>
                    </div>
                    {{-- 16 June 2023 for Residential --}}
                    @push('scripts')
                        <script>
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
                            function show_carport_opt() {
                                var h = $('#carport').val();
                                if (h == "Yes" || h == "Optional") {
                                    $('.carport_opt').show();
                                } else {
                                    $('.carport_opt').hide();
                                }
                            }
                            $(function() {
                                show_carport_opt("");
                            });
                        </script>
                        <script>
                            function show_water_views() {
                                var h = $('#need_water_view').val();
                                if (h == "Yes" || h == "Optional") {
                                    $('.water_views').show();
                                } else {
                                    $('.water_views').hide();
                                }
                            }
                            $(function() {
                                show_water_views("");
                            });
                        </script>
                        <script>
                            function show_extra_water_views() {
                                var h = $('#water_extras').val();
                                if (h == "Yes" || h == "Optional") {
                                    $('.Water_extras_opt').show();
                                } else {
                                    $('.Water_extras_opt').hide();
                                }
                            }
                            $(function() {
                                show_extra_water_views("");
                            });
                        </script>
                    @endpush
                    {{-- 19 June 2023 for Residential and Income --}}
                    <div class="wizard-step">
                        {{-- 12 June 2023 for Residential and Income --}}
                        <h4>Agent or Landlord’s Info: </h4>
                        <div class="form-group">
                            <label class="fw-bold">Address of the Property:</label>
                            <input type="text" name="adress_of_the_property"
                                value="{{ $auction->get->adress_of_the_property ?? '' }}" id="adress_of_the_property"
                                placeholder="199 Florida A1A,Satellite Beach,FL,USA" class="form-control has-icon"
                                data-icon="fa-solid fa-user" value="{{ Auth::user()->first_name }}" required>
                        </div>

                        <div class="form-group ">
                            <label class="fw-bold">Business Card</label>
                            <input type="file" name="business_card" id="business_card" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>
                        <div class="form-group ">
                            <label class="fw-bold">Pictures</label>
                            <input type="file" name="visible_property_picture" id="property_picture" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold"> Video:</label>
                            <input type="file" name="visible_property_video" id="property_video" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Floor Plan:</label>
                            <input type="file" name="visible_note" id="visible_note" class="form-control">
                        </div>
                        {{-- 12 June 2023 for Residential and Income --}}
                    </div>
                    {{-- 19 June 2023 for Residential and Income --}}
                    <div class="d-flex justify-content-between form-group mt-4">
                        <div>
                            <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                        </div>
                        <div>
                            <a class="wizard-step-next btn btn-success btn-lg text-600" style="display: none;">Next</a>
                            <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
                                style="display: none;">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <template class="county_temp">
        <tr>
            <td><input type="text" name="counties[]" placeholder="County" class="form-control has-icon search_places"
                    data-icon="fa-solid fa-tree-city" data-msg-required="Please enter county"></td>
        </tr>
    </template>

    <template class="city_temp">
        <tr>
            <td><input type="text" name="cities[]" placeholder="City" data-type="cities"
                    class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                    data-msg-required="Please enter city"></td>
        </tr>
    </template>
    <template class="state_temp">
        <tr>
            <td><input type="text" name="states[]" placeholder="State" data-type="states"
                    class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                    data-msg-required="Please enter state"></td>
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
        function changePropertyType(p) {
            if (p == "Residential Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').show();
                $('.income-length').hide();
                $('.commercial-length').hide();
                $('.commercial_show').addClass('d-none');
                $('.residential_show').removeClass('d-none');
                $('.add_commercial').remove();


                // nisar changing
                $('#leasableSqft').remove();
                $('#sfPrice').remove();


            } else if (p == "Commercial Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').show();
                $('.commercial_show').removeClass('d-none');
                $('.residential_show').addClass('d-none');
                $('.remove_commercial').remove();

                // nisar changing
                $('#remove-bed').remove();
                $('#furnishings').remove();
                $('#pool').remove();
                $('#heatedSqft').remove();
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
                    if (v.form()) {
                        $('.mainform').submit();
                    }
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
