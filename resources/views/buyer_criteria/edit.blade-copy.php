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
@php
    $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
    $yes_or_nos_opt = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'], ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question']];
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

                <form class="p-4 pt-0 mainform" action="{{ route('buyer_agent.auction.update') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="id" value="{{ $id }}">
                    <div class="wizard-step">
                        {{-- 9 June 2023 for Residential and Income --}}
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
                        {{-- 9 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step">
                        {{-- 9 June 2023 --}}
                        <div class="form-group">
                            <label class="fw-bold" for="address"> Interested City:</label>
                            <input type="text" name="city" value="{{ @$auction->get->city }}" placeholder="City"
                                data-type="cities" id="city" class="form-control has-icon search_places"
                                data-icon="fa-solid fa-city" data-msg-required="Please enter city" required>
                        </div>


                        <div class="form-group">
                            <label class="fw-bold">Interested County:</label>
                            <input type="text" name="county" value="{{ @$auction->get->county }}" placeholder="County"
                                data-type="counties" id="county" class="form-control search_places has-icon"
                                data-icon="fa-solid fa-building" data-msg-required="Please enter county" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Interested State:</label>
                            <input type="text" name="state" value="{{ @$auction->get->state }}" placeholder="State"
                                data-type="states" id="state" class="form-control search_places has-icon"
                                data-icon="fa-solid fa-flag-usa" data-msg-required="Please enter state" required>
                        </div>
                        {{-- 9 June 2023 --}}
                    </div>


                    @push('scripts')
                        <script>
                            function property_questions() {
                                var want_analysis = $('#property_type').val();
                                // alert(want_analysis);
                                if (want_analysis == 'Residential Property' || want_analysis == 'Income Property') {
                                    var temp = $('.property_temp').html();
                                    // alert(temp);
                                    $('.property_questions').after(temp);
                                    initialize();
                                } else {
                                    $('.property_questions_').remove();
                                }
                            }
                        </script>
                    @endpush

                    <div class="wizard-step">
                        {{-- 9 June 2023 for Residential and Income Property --}}
                        @php
                            $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property']];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">Interested Property Types:</label>
                            <select class="grid-picker" name="property_type" id="property_type"
                                onchange="changePropertyType(this.value);check_hoa();property_questions();" multiple
                                required>
                                <option value="">Select</option>
                                @foreach ($property_types as $row_pt)
                                    <option value="{{ $row_pt['name'] }}" class="card flex-column fw-bold"
                                        {{ selected($row_pt['name'], @$auction->get->property_type) }}
                                        style="width:calc(24% - 10px);" data-icon='<i class="fa-solid fa-hotel"></i>'>
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
                    <div class="wizard-step">
                        {{-- 13 June 2023 for Business Opportunity --}}
                        @php
                            $property_styles = [['name' => 'Vacant Land'], ['name' => 'Busniess Opportunity']];
                        @endphp

                        <div class="form-group business_type_next ">
                            <label class="fw-bold">Property Styles:</label>
                            <select class="grid-picker" name="property_style" id="property_style"
                                onchange="changePropertyStyle(this.value);" required>
                                <option value="">Select</option>
                                @foreach ($property_styles as $property_style)
                                    <option value="{{ $property_style['name'] }}" class="card flex-column"
                                        {{ selected($property_style['name'], @$auction->get->property_items) }}
                                        style="width:calc(24% - 10px);" data-icon='<i class="fa-solid fa-hotel"></i>'>
                                        {{ $property_style['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        {{-- 13 June 2023 for Business Opportunity --}}
                        {{-- 14 June 2023 for Vacant Land --}}

                        @php
                            $current_uses = [
                                ['name' => 'Billboard Site', 'target' => ''],
                                ['name' => 'Business', 'target' => ''],
                                ['name' => 'Cattle', 'target' => ''],
                                ['name' => 'Commercial', 'target' => ''],
                                ['name' => 'Farm', 'target' => ''],
                                ['name' => 'Fishery', 'target' => ''],
                                ['name' => 'Highway Frontage', 'target' => ''],
                                ['name' => 'Horses', 'target' => ''],
                                ['name' => 'Industrial', 'target' => ''],
                                ['name' => 'Land Fill', 'target' => ''],
                                ['name' => 'Livestock', 'target' => ''],
                                ['name' => 'Mixed Use', 'target' => ''],
                                ['name' => 'Nursery', 'target' => ''],
                                ['name' => 'Orchard', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
                                ['name' => 'Pasture', 'target' => ''],
                                ['name' => 'Poultry', 'target' => ''],
                                ['name' => 'Ranch', 'target' => ''],
                                ['name' => 'Residential', 'target' => ''],
                                ['name' => 'Row Crops', 'target' => ''],
                                ['name' => 'Sod Farm', 'target' => ''],
                                ['name' => 'Subdivision', 'target' => ''],
                                ['name' => 'Timber', 'target' => ''],
                                ['name' => 'Tracts', 'target' => ''],
                                ['name' => 'Trans/Cell Tower', 'target' => ''],
                                ['name' => 'Tree Farm', 'target' => ''],
                                ['name' => 'Unimproved Land', 'target' => ''],
                                ['name' => 'Well Field ', 'target' => ''],
                            ];
                        @endphp
                        {{-- Changes 30 May 2023 --}}
                        <div class="form-group  hide_vacant">
                            <label class="fw-bold">Current Use:</label>
                            <select class="grid-picker" name="current_use" onchange="changeCurrentUse(this.value);"
                                id="current_use" style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($current_uses as $current_use)
                                    <option value="{{ $current_use['name'] }}"
                                        data-target="{{ $current_use['target'] }}"
                                        {{ selected($current_use['name'], @$auction->get->current_use) }}
                                        class="card flex-column" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $current_use['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 14 June 2023 for Vacant Land --}}

                        {{-- 13 June 2023 for Business Opportunity --}}
                        <div class="form-group business_opportunity_show ">
                            <label class="fw-bold">Real Estate Included:</label>
                            <select class="grid-picker" name="real_estate_included" id="real_estate_included" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" class="card flex-row fw-bold"
                                        {{ selected($item['name'], @$auction->get->real_estate_included) }}
                                        style="width:calc(50% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        {{-- 13 June 2023 for Business Opportunity --}}

                        {{-- 9 June 2023 for Residential and Income Property --}}
                        @php
                            $prop_conditions = [['name' => 'Not Updated: Requires a complete update'], ['name' => 'Tear Down: Requires complete demolition and reconstruction'], ['name' => 'Semi-updated, needs minor updates: Partially updated, but requires additional minor updates'], ['name' => 'Move-in Ready, Completely Updated: Fully updated and ready for occupancy']];
                        @endphp
                        <div class="form-group  ">
                            <label class="fw-bold">Acceptable Property Conditions:</label>
                            <select class="grid-picker" name="prop_conditions[]" id="prop_conditions" required>
                                <option value="">Select</option>
                                @foreach ($prop_conditions as $item)
                                    <option value="{{ $item['name'] }}" class="card flex-row fw-bold"
                                        {{ selected($current_use['name'], @$auction->get->prop_conditions) }}
                                        style="width:calc(50% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        {{-- 9 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step">
                        @php
                            $special_sale = [['name' => 'Pre-Construction', 'target' => ''], ['name' => 'Currently Being Built', 'target' => ''], ['name' => 'New Construction', 'target' => ''], ['name' => 'Assignment Contract', 'target' => ''], ['name' => 'Bank Owned/REO', 'target' => ''], ['name' => 'Probate', 'target' => ''], ['name' => 'Short Sale', 'target' => ''], ['name' => 'Government Owned', 'target' => ''], ['name' => 'Auction', 'target' => ''], ['name' => 'None', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Acceptable Sales Provisions:</label>
                            <select class="grid-picker" name="special_sales[]" id="special_sales" multiple
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($special_sale as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->special_sales) }}
                                        class="card flex-column fw-bold" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>


                    {{-- 14 June 2023 for Vacant Land --}}
                    <div class="wizard-step vacant_land_remove">
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
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Lot Features:</label>
                            <select class="grid-picker" name="lot_features[]" id="lot_features" multiple
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($lot_features as $lot_feature)
                                    <option value="{{ $lot_feature['name'] }}"
                                        {{ selected($lot_feature['name'], @$auction->get->lot_features) }}
                                        data-target="{{ $lot_feature['target'] }}" class="card flex-column fw-bold"
                                        style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $lot_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 14 June 2023 for Vacant Land --}}


                    {{-- 14 June 2023 for Vacant Land --}}
                    <div class="wizard-step vacant_land_remove">
                        @php
                            $road_frontages = [['name' => 'Access Road', 'target' => ''], ['name' => 'Alley', 'target' => ''], ['name' => 'Business District', 'target' => ''], ['name' => 'City Street', 'target' => ''], ['name' => 'County Road ', 'target' => ''], ['name' => 'Divided Highway', 'target' => ''], ['name' => 'Easement', 'target' => ''], ['name' => 'Highway', 'target' => ''], ['name' => 'Interchange', 'target' => ''], ['name' => 'Interstate', 'target' => ''], ['name' => 'Main Thoroughfare', 'target' => ''], ['name' => 'Private Road', 'target' => ''], ['name' => 'Rail', 'target' => ''], ['name' => 'State Road', 'target' => ''], ['name' => 'Turn Lanes', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'None', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Road Frontage:</label>
                            <select class="grid-picker" name="road_frontage[]" id="road_frontage"
                                style="justify-content: flex-start;" onclick="" multiple>
                                <option value="">Select</option>
                                @foreach ($road_frontages as $road_frontage)
                                    <option value="{{ $road_frontage['name'] }}"
                                        {{ selected($road_frontage['name'], @$auction->get->road_frontage) }}
                                        data-target="{{ $road_frontage['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $road_frontage['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 14 June 2023 for Vacant Land --}}

                    {{-- 14 June 2023 for Vacant Land --}}
                    <div class="wizard-step vacant_land_remove">
                        @php
                            $road_surface_types = [['name' => 'Asphalt', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Chip And Seal', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Dirt', 'target' => ''], ['name' => 'Gravel', 'target' => ''], ['name' => 'Limerock', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Paved', 'target' => ''], ['name' => 'Unimproved', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Road Surface Type:</label>
                            <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($road_surface_types as $road_surface_type)
                                    <option value="{{ $road_surface_type['name'] }}"
                                        {{ selected($road_surface_type['name'], @$auction->get->road_surface_type) }}
                                        data-target="{{ $road_surface_type['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $road_surface_type['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 14 June 2023 for Vacant Land --}}

                    @php
                        $bedrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_bedrooms', 'name' => 'Other']];
                        $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.custom_bathrooms', 'name' => 'Other']];
                    @endphp
                    <div class="wizard-step" id="bedrooms">
                        <div class="form-group">
                            <label class="fw-bold">Minimum Bedrooms Needed:</label>
                            <select class="grid-picker" name="bedrooms" id="bedrooms" style="" required>
                                <option value="">Select</option>
                                @foreach ($bedrooms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->bedrooms) }}
                                        class="card flex-column fw-bold" style="width:calc(9% - 10px);"
                                        data-icon='<i class="fa-solid fa-bed"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_bedrooms d-none">
                            <label>Minimum Bedrooms Needed:</label>
                            <input type="text" name="custom_bedrooms" value="{{ $auction->get->custom_bedrooms }}"
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
                                        {{ selected($item['name'], @$auction->get->bathrooms) }}
                                        class="card flex-column fw-bold" style="width:calc(10% - 10px);"
                                        data-icon='<i class="fa-solid fa-bath"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_bathrooms d-none">
                            <label>Minimum Bathrooms Needed:</label>
                            <input type="text" name="custom_bathrooms" value="{{ $auction->get->custom_bathrooms }}"
                                id="custom_bathrooms" class="form-control has-icon" data-icon="fa-solid fa-bath"
                                required>
                        </div>
                    </div>

                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Minimum Sqft Needed: </label>
                            <input type="number" name="min_sqft" id="min_sqft" value="{{ $auction->get->min_sqft }}"
                                class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                        </div>
                    </div>
                    {{-- 9 June 2023 for Residential and Income Property --}}

                    <div class="wizard-step remove_business_opportunity">

                        {{-- 9 June 2023 for Residential and Income Property --}}
                        <div class="form-group">
                            <label class="fw-bold">Pool Needed?</label>
                            <select class="grid-picker" name="pool" id="pool"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos_opt as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->pool) }}
                                        class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        {{-- 9 June 2023 for Residential and Income Property --}}

                    </div>
                    <div class="wizard-step">

                        {{-- 14 June 2023 for Vacant Land --}}

                        <div class="row vacant_land_show">
                            @php
                                $utilities1 = [
                                    ['name' => 'BB/HS Internet Available', 'target' => ''],
                                    ['name' => 'Cable Available', 'target' => ''],
                                    ['name' => 'Cable Connected', 'target' => ''],
                                    ['name' => 'Electrical Nearby', 'target' => ''],
                                    ['name' => 'Electricity Available', 'target' => ''],
                                    ['name' => 'Fiber Optics', 'target' => ''],
                                    ['name' => 'Fire Hydrant', 'target' => ''],
                                    ['name' => 'Mini Sewer', 'target' => ''],
                                    ['name' => 'Natural Gas Available', 'target' => ''],
                                    ['name' => 'Other', 'target' => ''],
                                    ['name' => 'Phone Available', 'target' => ''],
                                    ['name' => 'Private', 'target' => ''],
                                    ['name' => 'Propane', 'target' => ''],
                                    ['name' => 'Public', 'target' => ''],
                                    ['name' => 'Sewer Available', 'target' => ''],
                                    ['name' => 'Sewer Connected', 'target' => ''],
                                    ['name' => 'Sprinkler Meter', 'target' => ''],
                                    ['name' => 'Sprinkler Recycled', 'target' => ''],
                                    ['name' => 'Sprinkler Well', 'target' => ''],
                                    ['name' => 'Street Lights', 'target' => ''],
                                    ['name' => 'Telephone Nearby', 'target' => ''],
                                    ['name' => 'Underground Utilities', 'target' => ''],
                                    ['name' => 'Utility Pole', 'target' => ''],
                                    ['name' => 'Water Multiple Meters', 'target' => ''],

                                    ['name' => 'Water Available', 'target' => ''],
                                    ['name' => 'Water Connected', 'target' => ''],
                                    ['name' => 'Water Nearby', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Utilities:</label>
                                <select class="grid-picker" name="utilities[]" id="utilities"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($utilities1 as $utilitie12)
                                        <option value="{{ $utilitie12['name'] }}"
                                            {{ selected($utilitie12['name'], @$auction->get->utilities) }}
                                            data-target="{{ $utilitie12['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $utilitie12['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $waters = [['name' => 'Canal Lake for Irrigation', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Private', 'target' => ''], ['name' => 'Public', 'target' => ''], ['name' => 'Well', 'target' => ''], ['name' => 'Well Required', 'target' => '']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Water:</label>
                                <select class="grid-picker" name="water12[]" id="water12"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($waters as $water)
                                        <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                                            {{ selected($water['name'], @$auction->get->water12) }}
                                            class="card flex-row" style="width:calc(33.3% - 10px);">
                                            {{ $water['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            @php
                                $sewers1 = [['name' => 'PEP-Holding Tank', 'target' => ''], ['name' => 'Private Sewer', 'target' => ''], ['name' => 'Public Sewer', 'target' => ''], ['name' => 'Septic Needed', 'target' => ''], ['name' => 'Septic Tank', 'target' => '']];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Sewer:</label>
                                <select class="grid-picker" name="sewer[]" id="sewer"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($sewers1 as $sewer12)
                                        <option value="{{ $sewer12['name'] }}" data-target="{{ $sewer12['target'] }}"
                                            {{ selected($sewer12['name'], @$auction->get->sewer) }}
                                            class="card flex-row" style="width:calc(33.3% - 10px);">
                                            {{ $sewer12['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- 14 June 2023 for Vacant Land --}}




                        {{-- 9 June 2023 for Residential and Income Property --}}
                        <div class="form-group commercial_hide">
                            <label class="fw-bold">Carport Needed?</label>
                            <select class="grid-picker" name="carport" id="carport"
                                style="justify-content: flex-start;" required>
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
                            $yes_or_nos1 = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                        @endphp
                        <div class="form-group commercial_hide">
                            <label class="fw-bold">Garage Needed?</label>
                            <select class="grid-picker" name="garage" id="garage"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos1 as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.grage_space_needed';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        {{ selected($item['name'], @$auction->get->garage) }} class="card flex-row"
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @php
                            $garage_spaces = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10+'], ['target' => '', 'name' => 'Optional'], ['target' => '.custom_garage', 'name' => 'Other']];
                        @endphp

                        <div class="form-group grage_space_needed  commercial_hide">
                            <label class="fw-bold">Garage Spaces Needed:</label>
                            <select class="grid-picker" name="garage_spaces" id="garage_spaces"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($garage_spaces as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->garage_spaces) }}
                                        class="card flex-column fw-bold" style="width:calc(10% - 10px);"
                                        data-icon='<i class="fa-solid fa-warehouse"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_garage d-none">
                            <label>Garage/Parking Features:</label>
                            <input type="text" name="custom_garage" value="{{ $auction->get->custom_garage }}"
                                id="custom_garage" class="form-control has-icon" data-icon="fa-solid fa-warehouse"
                                required>
                        </div>
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
                        <div class="form-group business_opportunity_show">
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
                    <div class="wizard-step">
                        {{-- 9 June 2023 for Residential and Income --}}
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
                                        {{ selected($water_view['name'], @$auction->get->water_view) }}
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
                                    {{ selected($item['name'], @$auction->get->has_water_extra) }}
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
                                    {{ selected($water_extra['name'], @$auction->get->water_extras) }}
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
                                    {{ selected($water_access1['name'], @$auction->get->water_access) }}
                                        data-target="{{ $water_access1['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_access1['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 14 June 2024 for Vacant Land --}}
                    <div class="wizard-step remove_business_opportunity">
                        <div class="row">
                            <div class="col-md-12">
                                @php
                                    $total_acreages = [['name' => '0 to less than 14', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ acres', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
                                @endphp
                                <div class="form-group ">
                                    <label class="fw-bold">Total Acreage :</label>
                                    <select class="grid-picker" name="total_acreage" id="total_acreage"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($total_acreages as $total_acreage)
                                            <option value="{{ $item['name'] }}"
                                                 {{ selected($total_acreage['name'], @$auction->get->total_acreage) }}
                                                data-target="{{ $total_acreage['target'] }}" class="card flex-column"
                                                style="width:calc(25% - 10px);"
                                                data-icon='<i class="fa-solid fa-check-circle"></i>'>
                                                {{ $total_acreage['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 ">

                                <div class="form-group">
                                    <label class="fw-bold">Lot Dimensions(Parcel Number):</label>
                                    <input type="number" name="lot_dimensions" value="{{ $auction->get->lot_dimensions ?? ''}}" id="lot_dimensions"
                                        class="form-control has-icon" data-icon="fa-solid " placeholder="Tax ID"
                                        required>
                                </div>

                            </div>
                            <div class="col-md-4">

                                <div class="form-group">
                                    <label class="fw-bold">Lot Size Square Footage:</label>
                                    <input type="number" name="lot_size_square_footage" value="{{ $auction->get->lot_size_square_footage ?? '' }}" id="lot_size_square_footage"
                                        class="form-control has-icon" data-icon="fa-solid "
                                        placeholder="Lot Size Square Footage">
                                </div>

                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="fw-bold">Front Footage:</label>
                                    <input type="number" name="front_footage1" value="{{ $auction->get->front_footage1 ?? '' }}" id="front_footage1"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                                        placeholder="0.00" step="0.01">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="fw-bold">Lot Size Acres:</label>
                                    <input type="number" name="lot_size_acres" value="{{ $auction->get->lot_size_acres ?? '' }}" id="lot_size_acres"
                                        class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- 14 June 2024 for Vacant Land --}}
                    <div class="wizard-step remove_business_opportunity">
                        <div class="form-group">
                            <label class="fw-bold">Would the buyer be interested in purchasing in a community with an
                                HOA/Condo/Community association?</label>
                            <select class="grid-picker" name="buyer_intrest_in_purchasing"
                                id="buyer_intrest_in_purchasing" style="justify-content: flex-start;">
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
                                    {{ selected($item['name'], @$auction->get->buyer_intrest_in_purchasing) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="wizard-step" id="remove_pets_question">
                        {{-- 9 June 2023 for Residential and Income --}}
                        <div class="form-group">
                            <label class="fw-bold">Pets Allowed:</label>
                            <select class="grid-picker" name="has_rental_restrictions" id="has_rental_restrictions"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.pets_allowed_question1';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                    {{ selected($item['name'], @$auction->get->has_rental_restrictions) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $total_pets_allowed = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_pets_allowed', 'name' => 'Other']];
                        @endphp

                        <div class="form-group pets_allowed_question1 d-none">
                            <div class="form-group">
                                <label class="fw-bold">Number of pets allowed:</label>
                                <select class="grid-picker" name="total_pets_allowed" id="total_pets_allowed"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($total_pets_allowed as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            {{ selected($item['name'], @$auction->get->total_pets_allowed) }}
                                            class="card flex-column" style="width:calc(10% - 10px);"
                                            data-icon='<i class="fa-solid fa-dog"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_pets_allowed d-none">
                                <label class="fw-bold">Number of pets allowed:</label>
                                <input type="text" name="custom_pets_allowed" value="{{ $auction->get->custom_pets_allowed }}" id="custom_pets_allowed"
                                    class="form-control has-icon" data-icon="fa-solid fa-dog" required>
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">Max pet weight:</label>
                                <input type="text" name="max_pet_weight" value="{{ $auction->get->max_pet_weight }}" id="max_pet_weight"
                                    class="form-control has-icon" data-icon="fa-solid fa-dog">
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">Pet Restrictions:</label>
                                <textarea name="pet_restrictions" id="pet_restrictions" value="{{ $auction->get->pet_restrictions }}" class="form-control" cols="30" rows="5"></textarea>
                            </div>
                        </div>

                        {{-- 9 June 2023 for Residential and Income --}}
                        <div class="form-group other_days d-none">
                            <label>Inspection Period Offered:</label>
                            <input type="text" name="other_days" value="{{ $auction->get->other_days }}" id="other_days" class="form-control has-icon"
                                data-icon="fa-regular fa-check-circle" required>
                        </div>
                    </div>

                    {{-- 9 June 2023 for Residential and Income --}}
                    <div class="wizard-step" id="hoaCommunity">
                        <div class="form-group">
                            <label class="fw-bold"> Is the buyer eligible for 55+ and over communities?</label>
                            <select class="grid-picker" name="hoa_community" id="hoa_community"
                                style="justify-content: flex-start;" onchange="check_hoa_values();show_hoa();">
                                <option value="">Select</option>
                                @foreach ($yes_or_nos_opt as $item)
                                    <option value="{{ $item['name'] }}" data-target="" class="card flex-row fw-bold"
                                    {{ selected($item['name'], @$auction->get->hoa_community) }}
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="hoa_fee_yes">

                            @php
                                $hoa_fee_requirements = [['target' => '', 'name' => 'Required'], ['target' => '', 'name' => 'Optional'], ['target' => '', 'name' => 'No'], ['target' => '', 'name' => 'Yes']];
                            @endphp

                            <div class="form-group">
                                <label class="fw-bold">HOA Fee Requirement:</label>
                                <select class="grid-picker" name="hoa_fee_requirement" id="hoa_fee_requirement"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($hoa_fee_requirements as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ selected($item['name'], @$auction->get->hoa_fee_requirement) }}
                                            class="card flex-row fw-bold hoa_req_option" style="width:calc(25% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">What is the maximum monthly condo and/or HOA fee that the buyer
                                    can afford?</label>
                                <input type="number" name="hoa_fee" value="{{ $auction->get->hoa_fee }}" id="hoa_fee" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" placeholder="0.00" step="0.01">
                            </div>
                        </div>
                    </div>
                    {{-- 9 June 2023 for Residential and Income --}}
                    <div class="wizard-step">
                        {{-- 9 June 2023 for Residential and Income --}}
                        <div class="form-group">
                            <label class="fw-bold">buyer’s specific requirements</label>
                            <textarea name="description_buyer_specific" value="{{ $auction->get->description_buyer_specific }}" id="buyer_specific_requirements" class="form-control" cols="15"
                                rows="5" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Prefrence for Buying a Property</label>
                            <textarea name="description_preference" value="{{ $auction->get->description_preference }}" id="prefrence_of_buying_a_property" class="form-control" cols="15"
                                rows="5" required></textarea>
                        </div>
                        <div class="form-group ">
                            <label class="fw-bold">Are there any non-negotiable factors that the buyer will not accept for
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
                        {{-- 9 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step">
                        {{-- 9 June 2023 for Residential and Income --}}
                        <div class="form-group">
                            <label class="fw-bold">
                                Maximum Price:
                            </label>
                            <input type="number" name="max_price" value="{{ $auction->get->max_price }}" id="max_price" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar-sign" required>
                        </div>
                        {{-- 9 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step">
                        {{-- 9 June 2023 for Residential and Income --}}
                        @php
                            $financings = [
                                ['name' => 'Cash', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Conventional', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'FHA', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'VA', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Ethereum (ETH)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Litecoin (LTC)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Bitcoin Cash (BCH)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Dash (DASH)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Ripple (XRP)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Tether (USDT)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'USD Coin (USDC)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'NFT', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'USDA', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Assumable', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Exchange/Trade', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Lease Option', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Lease Purchase', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Private Financing Available', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Private Financing Available', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Special Funding', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Seller Financing', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Other', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Offered Currency/Financing:</label>
                            <select class="grid-picker" name="financings[]" id="financings"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($financings as $item)
                                    @php
                                        if ($item['name'] == 'Other') {
                                            $target = '.custom_financings';
                                        } elseif ($item['name'] == 'Seller Financing') {
                                            $target = '.seller_financing';
                                        } elseif ($item['name'] == 'Exchange/Trade') {
                                            $target = '.trade';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                    {{ selected($item['name'], @$auction->get->financings) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_financings d-none">
                            <label>Offered Currency/Financing: *</label>
                            <input type="text" name="custom_financings" value="{{ $auction->get->custom_financings }}" id="custom_financings"
                                class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                        </div>
                        <div class="row seller_financing">

                            <div class="form-group col-md-4 ">
                                <label class="fw-bold">Purchase Price:$</label>
                                <input type="number" name="purchase_price" value="{{ $auction->get->purchase_price }}" id="purchase_price"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar "
                                    placeholder="Purchase Price">
                            </div>

                            <div class="form-group col-md-4 ">
                                <label class="fw-bold">Down Payment:$</label>
                                <input type="number" name="down_payment" value="{{ $auction->get->down_payment }}" id="down_payment"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar "
                                    placeholder="Purchase Price">
                            </div>
                            <div class="form-group col-md-4 ">
                                <label class="fw-bold">Seller Financing Amount:$</label>
                                <input type="number" name="seller_financing_amount" value="{{ $auction->get->seller_financing_amount }}" id="seller_financing_amount"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar "
                                    placeholder="Purchase Price">
                            </div>
                            <div class="form-group col-md-4 ">
                                <label class="fw-bold">Interest Rate:$</label>
                                <input type="number" name="interest_rate" value="{{ $auction->get->interest_rate }}" id="interest_rate"
                                    class="form-control has-icon" data-icon="fa-solid fa-percent "
                                    placeholder="Purchase Price">
                            </div>
                            <div class="form-group col-md-4 ">
                                <label class="fw-bold">Term:</label>
                                <input type="number" name="term" id="interest_rate" value="{{ $auction->get->term }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-days " placeholder="Enter Days ">
                            </div>
                            <div class="form-group col-md-4 ">
                                <label class="fw-bold">Monthly Payments:</label>
                                <input type="number" name="monthly_payment" value="{{ $auction->get->monthly_payment }}" id="monthly_payment"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar "
                                    placeholder="Enter Days ">
                            </div>
                            <div class="form-group col-md-4 ">
                                <label class="fw-bold">Balloon Payment:</label>
                                <input type="number" name="ballon_payment" value="{{ $auction->get->ballon_payment }}" id="ballon_payment"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                    placeholder="Enter Days ">
                            </div>
                            <div class="form-group col-md-4 ">
                                <label class="fw-bold">Prepayment Penalty:</label>
                                <input type="number" name="prepayment_penalty" value="{{ $auction->get->prepayment_penalty }}" id="prepayment_penalty"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                    placeholder="Enter Days ">
                            </div>

                            <div class="form-group col-md-4 ">
                                <label class="fw-bold">Closing Costs:</label>
                                <input type="number" name="closing_costs" value="{{ $auction->get->closing_costs }}" id="closing_costs"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                    placeholder="Enter Days ">
                            </div>
                        </div>
                        <div class="row trade">
                            @php
                                $exchange_trade_for = [['name' => 'Another home', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vehicles', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Boats', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Motorhomes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Artwork', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Jewelry', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Exchange/Trade/Cash for:</label>
                                <select class="grid-picker" name="exchange_trade_for" id="exchange_trade_for"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($exchange_trade_for as $item)
                                        @php
                                            if ($item['name'] == 'Other') {
                                                $target = '.custom_trade';
                                            } else {
                                                $target = '';
                                            }
                                        @endphp
                                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                    {{ selected($item['name'], @$auction->get->exchange_trade_for) }}
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_trade ">
                                <label>Exchange For</label>
                                <input type="text" name="exchange_for_custom" value="{{ $auction->get->exchange_for_custom }}" id="exchange_for_custom"
                                    class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Cash:($)</label>
                                <input type="number" name="exchange_cash" value="{{ $auction->get->exchange_cash }}" id="exchange_cash"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                    placeholder="Enter Cash Amount">
                            </div>
                        </div>
                        {{-- 9 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step">
                        {{-- 12 June 2023 for Residential and Income --}}
                        <div class="row residential_and_income">
                            <div class="form-group">
                                <label class="fw-bold">Escrow Amount Offered:</label>
                                <input type="number" step="0.01" name="escrow_amount" value="{{ $auction->get->escrow_amount }}" id="escrow_amount"
                                    placeholder="0.00" class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                    required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Inspection Period Offered:</label>
                                <input type="text" name="inspection_period" value="{{ $auction->get->inspection_period }}" id="inspection_period" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-calendar-day" required>
                            </div>
                            @php
                                $contingencies = [['target' => '', 'name' => 'Home Inspection'], ['target' => '', 'name' => 'Appraisal'], ['target' => '', 'name' => 'Financing'], ['target' => '', 'name' => 'Sale of a prior property'], ['target' => '', 'name' => 'None'], ['target' => '.custom_contingencies', 'name' => 'Other']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Contingencies Offered:</label>
                                <select class="grid-picker" name="contingencies[]"  id="contingencies" style=""
                                    multiple required>
                                    <option value="">Select</option>
                                    @foreach ($contingencies as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ selected($item['name'], @$auction->get->contingencies) }}
                                            class="card flex-column fw-bold" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group custom_contingencies d-none">
                                <label>Contingencies Offered:</label>
                                <input type="text" name="custom_contingencies" value="{{ $auction->get->custom_contingencies }}" id="custom_contingencies"
                                    class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Days needed to Close:</label>
                                <input type="text" name="closing_days" value="{{ $auction->get->closing_days }}" id="closing_days" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-calendar-day" required>
                            </div>
                            <div class="form-group">
                                <label for="address">Ideal Move in Date:</label>
                                <input type="date" name="ideal_move_in_date" value="{{ $auction->get->ideal_move_in_date }}" id="ideal_move_in_date"
                                    class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                    data-msg-required="Please Enter your Ideal Move in  Date" required>
                            </div>
                        </div>
                        {{-- 12 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step">
                        {{-- 12 June 2023 for Residential and Income --}}
                        <div class="form-group">
                            <label class="fw-bold">Requested Credit at Closing (Buyer's Rebate) (optional):
                            </label>
                            <input type="text" name="request_buyer_credit" value="{{ $auction->get->request_buyer_credit }}" id="request_seller_premium"
                                class="form-control has-icon" placeholder="Requested Credit">
                        </div>
                        {{-- 12 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step">
                        {{-- 12 June 2023 for Residential and Income --}}
                        <div class="form-group">
                            <label class="fw-bold">Preferred Title Company (Optional):
                            </label>
                            <input type="text" name="preferred_title_company" value="{{ $auction->get->preferred_title_company }}" id="preferred_title_company"
                                class="form-control has-icon">
                        </div>
                        {{-- 12 June 2023 for Residential and Income --}}
                    </div>
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
                            <input type="text" name="agent_commission_percent" value="{{ $auction->get->agent_commission_percent }}" id=""
                                class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                        </div>
                        {{-- 12 June 2023 for Residential and Income --}}

                    </div>
                    <div class="wizard-step">
                        {{-- 12 June 2023 for Residential and Income --}}
                        @php
                            $special_sale = [['name' => 'Pre-Construction', 'target' => ''], ['name' => 'Currently Being Built', 'target' => ''], ['name' => 'New Construction', 'target' => ''], ['name' => 'Assignment Contract', 'target' => ''], ['name' => 'Bank Owned/REO', 'target' => ''], ['name' => 'Probate', 'target' => ''], ['name' => 'Short Sale', 'target' => ''], ['name' => 'Government Owned', 'target' => ''], ['name' => 'Auction', 'target' => ''], ['name' => 'None', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Special Save Sales Provisions:</label>
                            <select class="grid-picker" name="special_sales1[]" id="special_sales" multiple
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($special_sale as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ selected($item['name'], @$auction->get->special_sales1) }}
                                        class="card flex-column fw-bold" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        {{-- 12 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step">
                        {{-- 12 June 2023 for Residential and Income --}}
                        <div class="form-group">
                            <label class="fw-bold">How many heated sqft does the property have?</label>
                            <input type="number" name="heated_sqft" value="{{ $auction->get->heated_sqft }}" id="heated_sqft" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-icon" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">How many total sqft does the property have?</label>
                            <input type="number" name="total_sqft" value="{{ $auction->get->total_sqft }}" id="heated_sqft" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-icon" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Total Acreage</label>
                            <input type="number" name="total_acreage" value="{{ $auction->get->total_acreage }}" id="total_acreage" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-icon" required>
                        </div>
                        {{-- 12 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step vacant_land_hide">
                        {{-- 12 June 2023 for Residential and Income --}}
                        <div class="form-group  ">
                            <label class="fw-bold">Is the currently offered property leased? </label>
                            <select class="grid-picker" name="has_property_leased" id="has_property_leased"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.enter_amount';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                    {{ selected($item['name'], @$auction->get->has_property_leased) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group d-none enter_amount">
                            <label class="fw-bold">Amount</label>
                            <input type="number" name="ammount" value="{{ $auction->get->ammount }}" id="ammount" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-dollar">
                        </div>
                        {{-- 12 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step remove_business_opportunity">
                        {{-- 14 June 2023 for Business Opportunity --}}
                        <div class="form-group ">
                            <label class="fw-bold">Description and Additional Details: </label>
                            <textarea name="description_and_additional_terms" value="{{ $auction->get->description_and_additional_terms }}" id="buyer_specific_requirements" class="form-control"
                                cols="15" rows="5" required></textarea>
                        </div>
                        {{-- 14 June 2023 for Business Opportunity --}}
                    </div>
                    <div class="wizard-step remove_business_opportunity">
                        {{-- 14 June 2023 for Residential and Income --}}
                        <h4>Buy Now Terms:</h4>
                        <div class="form-group">
                            <label class="fw-bold">Price</label>
                            <input type="number" value="{{ $auction->get->price }}" step="0.01" name="price" id="price" placeholder="0.00"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Escrow Amount Offered:</label>
                            <input type="number" step="0.01" name="escrow_amount" value="{{ $auction->get->escrow_amount }}" id="escrow_amount"
                                placeholder="0.00" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">What is the desired timeline or timeframe for the Seller to complete the
                                sale?</label>
                            <input type="number" step="0.01" name="desired_timline" value="{{ $auction->get->desired_timline }}" id="desired_timline"
                                placeholder="0.00" class="form-control has-icon" data-icon="fa-solid fa-check" required>
                        </div>
                        {{-- 14 June 2023 for Residential and Income --}}
                    </div>
                    <div class="wizard-step">
                        {{-- 12 June 2023 for Residential and Income --}}
                        <h4>The Following section is privately sent to the agent: </h4>
                        <div class="form-group">
                            <label class="fw-bold">Address of the Property:</label>
                            <input type="text" name="adress_of_the_property" value="{{ $auction->get->adress_of_the_property }}" id="adress_of_the_property"
                                placeholder="199 Florida A1A,Satellite Beach,FL,USA" class="form-control has-icon"
                                data-icon="fa-solid fa-user"required>
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
    <template class="property_temp">
        <div class="wizard-step property_questions_">
            <h5>If Buyer is an investor looking to purchase an investment property/commercial, please fill
                out
                the following</h5>

            {{-- <div class="form-group">
            <label>Rental Requirements: (List any requirements you need): Example: home located in a
                short-term rental zone that allows for Airbnbs</label>
            <textarea name="rental_requirements" id="rental_requirements" cols="30" rows="5" class="form-control">{{ @$auction->get->rental_requirements }}</textarea>
        </div> --}}

            <div class="form-group">
                <label>The total number of units required:</label>
                <input type="text" name="total_units_needed" id="total_units_needed"
                    value="{{ @$auction->get->total_units_needed }}" class="form-control">
            </div>

            <div class="form-group">
                <label>Required minimum annual net income:</label>
                <input type="text" name="annual_income" id="annual_income"
                    value="{{ @$auction->get->annual_income }}" class="form-control">
            </div>

            <div class="form-group">
                <label>Required minimum cap rate:</label>
                <input type="text" name="min_cap_rate" id="min_cap_rate"
                    value="{{ @$auction->get->min_cap_rate }}" class="form-control">
            </div>

            <div class="form-group">
                <label>Additional details, if any:</label>
                <textarea name="additional_details" id="additional_details" cols="30" rows="5"
                    class="form-control">{{ @$auction->get->additional_details }}</textarea>
            </div>

            {{-- <div class="form-group">
            <label>ARV:</label>
            <input type="text" name="arv" id="arv" value="{{ @$auction->get->arv }}"
                class="form-control">
        </div> --}}

        </div>
    </template>
    <template class="county_temp">
        <tr>
            <td><input type="text" name="counties[]" placeholder="County"
                    class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                    data-msg-required="Please enter county"></td>
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
                $('.business_opportunity_show').hide();
                $('.vacant_land_show').hide();
                $('.business_type_next').hide();
                $('.commercial_hide').show();
                $('.remove_business_opportunity').remove();
                $('.vacant_land_remove').remove();
                $('.business_opportunity_remove').remove();
                $('.show_vacant').addClass('d-none');





                // Nisar Changing
                $('#investment').remove();


            } else if (p == "Income Property") {

                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').show();
                $('.commercial-length').hide();
                $('.business_opportunity_show').hide();
                $('.vacant_land_show').hide();
                $('.business_type_next').hide();
                $('.remove_business_opportunity').remove();
                $('.business_opportunity_remove').remove();
                $('.show_vacant').addClass('d-none');
                $('.vacant_land_remove').remove();




                $('.commercial_hide').show();

            } else if (p == "Commercial Property") {


                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').show();
                $('.business_type_next').show();

                $('.commercial_hide').hide();
                // $('.business_opportunity_show').addClass('d-none');



                // Nisar Changing
                $('#bedrooms').remove();
                $('#hoaCommunity').remove();
                $('#oldPersonsCommunity').remove();


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
                $('.water_view').show();
            } else {
                $('.water_view').hide();
            }
        }
        $(function() {
            changeWaterView("");
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
