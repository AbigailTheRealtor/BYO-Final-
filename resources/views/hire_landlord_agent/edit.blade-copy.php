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
        $yes_or_nos = [
            ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
        ];
    @endphp
    <div class="container pt-5 pb-5">
        <div class="card">
            <div class="row">
                <div class="col-12 p-4">
                    <form class="p-4 pt-0 validate mainform"
                        action="{{ route('landlord.hire.agent.auction.edit', @$auction->id) }}" method="POST"
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
                                Edit Hire Landlord's Agent auction - {{ @$auction->address }}
                            </h4>
                            <div class="wizard-steps-progress">
                                <div class="steps-progress-percent"></div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">Does the landlord have an active listing agreement with an agent?
                                    </label>
                                    <select class="grid-picker" name="working_with_agent" id="working_with_agent"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                {{ selected($item['name'], @$auction->get->working_with_agent) }}
                                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
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
                                <div class="form-group">
                                    <label class="fw-bold">Address </label>
                                    <input type="text" name="address"
                                        placeholder="199 Florida A1A, Satellite Beach, FL, USA" id="form_title"
                                        class="form-control search_places has-icon" data-type="address" required
                                        value="{{ @$auction->get->address }}" data-icon="fa-solid fa-location-dot" />
                                    @if ($errors->has('address'))
                                        <div class="small error">{{ $errors->first('address') }}</div>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">State </label>
                                    <input type="text" name="state" data-type="states" placeholder="Florida, USA"
                                        id="state" class="form-control has-icon search_places"
                                        value="{{ @$auction->get->state }}" data-icon="fa-solid fa-flag-usa"
                                        data-msg-required="Please enter state" required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">City </label>
                                    <input type="text" name="city" data-type="cities" placeholder="St. Petersburg, FL"
                                        id="city" value="{{ @$auction->get->city }}"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                                        data-msg-required="Please enter city" required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">County </label>
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
                                            $auction_types = [
                                                [
                                                    'name' => 'Auction Listing',
                                                    'icon' => '<i class="fa-regular fa-clock"></i>',
                                                    'target' => '',
                                                ],
                                                [
                                                    'name' => 'Traditional Listing',
                                                    'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                                                    'target' => '',
                                                ],
                                            ];
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
                                    ['name' => 'Commercial', 'target' => ''],
                                    ['name' => 'Other', 'target' => '.custom_bedrooms'],
                                ];
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
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">How many bedrooms does the property have?</label>
                                    <select class="grid-picker" name="bedrooms" id="bedrooms"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($bedrooms as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->bedrooms) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column"
                                                style="width:calc(10% - 10px);"
                                                data-icon='<i class="fa-solid fa-bed"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_bedrooms {{ is_hidden(@$auction->get->custom_bedrooms) }}">
                                    <label class="fw-bold">Bedrooms:</label>
                                    <input type="text" class="form-control has-icon"
                                        value="{{ @$auction->get->custom_bedrooms }}" placeholder="Write Custom Bedrooms"
                                        name="custom_bedrooms" data-icon="fa fa-bed" id="custom_bedrooms" required />
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">How many bathrooms does the property have?</label>
                                    <select class="grid-picker" name="bathrooms" id="bathrooms"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($bathrooms as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->bathrooms) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column"
                                                style="width:calc(10% - 10px);"
                                                data-icon='<i class="fa-solid fa-bath"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div
                                    class="form-group custom_bathrooms {{ is_hidden(@$auction->get->custom_bathrooms) }}">
                                    <label class="fw-bold">Bathrooms:</label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Bathrooms" name="custom_bathrooms"
                                        value="{{ @$auction->get->custom_bathrooms }}" data-icon="fa-solid fa-bath"
                                        id="custom_bathrooms" required />
                                </div>
                            </div>

                            {{-- @php
                                $expectations = [['name' => '$1000 and under', 'target' => ''], ['name' => '$1000-$1500', 'target' => ''], ['name' => '$1500-$2000', 'target' => ''], ['name' => '$2000-$2500', 'target' => ''], ['name' => '$2500-$3000', 'target' => ''], ['name' => '$3000-$3500', 'target' => ''], ['name' => '$3500-$4000', 'target' => ''], ['name' => '$4000-$4500', 'target' => ''], ['name' => '$5000-$6000', 'target' => ''], ['name' => '$6000-$7000', 'target' => ''], ['name' => '$7000+', 'target' => ''], ['name' => 'Other', 'target' => '.custom_expectation']];
                            @endphp --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        What is the desired rental amount for the landlord's property?
                                    </label>
                                    <input type="number" class="form-control has-icon"
                                        value="{{ @$auction->get->custom_expectation }}" placeholder="e.g 5000"
                                        name="expectation" data-icon="fa-solid fa-dollar" id="expectation" required />
                                    {{-- <select name="expectation" id="expectation" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($expectations as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                {{ selected($item['name'], @$auction->get->expectation) }}
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select> --}}
                                </div>

                                {{-- <div
                                    class="form-group custom_expectation {{ is_hidden(@$auction->get->custom_expectation) }}">
                                    <label>Custom Amount *</label>
                                    <input type="text" class="form-control has-icon"
                                        value="{{ @$auction->get->custom_expectation }}" placeholder="Custom Amount"
                                        name="custom_expectation" data-icon="fa-solid fa-dollar" id="custom_expectation"
                                        required />
                                </div> --}}
                            </div>


                            <div class="wizard-step">
                                @php
                                    $property_types = [
                                        ['name' => 'Residential Property'],
                                        ['name' => 'Income Property'],
                                        ['name' => 'Commercial Property'],
                                    ];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Interested Property Types:</label>
                                    <select class="grid-picker" name="property_type" id="property_type"
                                        onchange="changePropertyType(this.value);" required>
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
                                                [
                                                    'name' => 'Five or More (Residential units)',
                                                    'class' => 'income-length',
                                                ],
                                                ['name' => 'Agriculture', 'class' => 'commercial-length'],
                                                ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                                                ['name' => 'Business', 'class' => 'commercial-length'],
                                                [
                                                    'name' => 'Five or More (Residential units)',
                                                    'class' => 'commercial-length',
                                                ],
                                                [
                                                    'name' => 'Five or More (Commercial units)',
                                                    'class' => 'commercial-length',
                                                ],
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
                                                    data-target="" class="card flex-row {{ $item['class'] }}"
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
                                <div class="form-group">
                                    <label class="fw-bold">
                                        When will the property be ready for rent?
                                    </label>
                                    @php
                                        $timeframes = [
                                            ['name' => 'Now', 'target' => ''],
                                            ['name' => '15 days', 'target' => ''],
                                            ['name' => '30 days', 'target' => ''],
                                            ['name' => '60 days', 'target' => ''],
                                            ['name' => '90 days', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.custom_ready_timeframe'],
                                        ];
                                    @endphp
                                    <select name="ready_timeframe" id="ready_timeframe" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($timeframes as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->ready_timeframe) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div
                                    class="form-group custom_ready_timeframe {{ is_hidden(@$auction->get->custom_ready_timeframe) }}">
                                    <label class="fw-bold">When will the property be ready for rent?</label>
                                    <input type="text" name="custom_ready_timeframe"
                                        value="{{ @$auction->get->custom_ready_timeframe }}" id="custom_ready_timeframe"
                                        class="form-control" required>
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        How long would the landlord like to lease their property for?
                                    </label>
                                    @php
                                        $lease_period = [
                                            ['name' => '3 months', 'target' => ''],
                                            ['name' => '6 months', 'target' => ''],
                                            ['name' => '9 months', 'target' => ''],
                                            ['name' => '1 year', 'target' => ''],
                                            ['name' => '2 years', 'target' => ''],
                                            ['name' => '3-5 years', 'target' => ''],
                                            ['name' => '5+ years', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.custom_lease_period'],
                                        ];
                                    @endphp
                                    <select name="lease_period" id="lease_period" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($lease_period as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->lease_period) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div
                                    class="form-group custom_lease_period {{ is_hidden(@$auction->get->custom_lease_period) }}">
                                    <label class="fw-bold">How long would the landlord like to lease their property
                                        for?</label>
                                    <input type="text" class="form-control has-icon"
                                        value="{{ @$auction->get->custom_lease_period }}"
                                        placeholder="Write Custom Lease Period" name="custom_lease_period"
                                        data-icon="fa-regular fa-square-check" id="custom_lease_period" required />
                                </div>
                            </div>


                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Offered Listing Agreement Terms with an Agent:
                                    </label>
                                    @php
                                        $listing_terms = [
                                            ['name' => '3 months', 'target' => ''],
                                            ['name' => '6 months', 'target' => ''],
                                            ['name' => '9 months', 'target' => ''],
                                            ['name' => '12 months', 'target' => ''],
                                            ['name' => 'Other', 'target' => '.custom_listing_terms'],
                                        ];
                                    @endphp
                                    <select name="listing_term" id="listing_term" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($listing_terms as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->listing_term) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div
                                    class="form-group custom_listing_terms {{ is_hidden(@$auction->get->custom_listing_terms) }}">
                                    <label class="fw-bold">Offered Listing Agreement Terms with an Agent:</label>
                                    <input type="text" class="form-control has-icon"
                                        value="{{ @$auction->get->custom_listing_terms }}"
                                        placeholder="Write Custom Terms" name="custom_listing_terms"
                                        data-icon="fa-regular fa-square-check" id="custom_listing_terms" required />
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Total offered commission to agent:
                                    </label>
                                    @php
                                        $offered_commissions = [
                                            ['name' => 'One month’s rent (for a one-year lease)', 'target' => ''],
                                            [
                                                'name' =>
                                                    '10% of the value of the lease (for a lease of one year or less)',
                                                'target' => '',
                                            ],
                                            [
                                                'name' =>
                                                    '6% of the value of the lease (for a lease of more than one year)',
                                                'target' => '',
                                            ],
                                            ['name' => 'Other', 'target' => '.custom_offered_commission'],
                                        ];
                                    @endphp
                                    <select name="offered_commission" id="offered_commission" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($offered_commissions as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->offered_commission) }}
                                                data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(50% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" ></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div
                                    class="form-group custom_offered_commission {{ is_hidden(@$auction->get->custom_offered_commission) }}">
                                    <label class="fw-bold">Total offered commission to agent: % or $</label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Commission" name="custom_offered_commission"
                                        value="{{ @$auction->get->custom_offered_commission }}"
                                        data-icon="fa-regular fa-square-check" id="custom_offered_commission" required />
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Select the included services that Landlord requests from agent: *
                                    </label>
                                    @php
                                        $services_data = [];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' => 'List property on platform Bid Your Offer platform',
                                        ];
                                        $services_data[] = ['target' => '', 'name' => 'List Property on the MLS'];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' =>
                                                'List property on the major Real Estate websites including Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more which will get thousands of views.',
                                        ];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' => 'Listing on Loopnet (Commercial)',
                                        ];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' =>
                                                'Tenant screening with a through application process that includes a credit, criminal, background, eviction, and income verification check.',
                                        ];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' => 'Provide a rental lease for esign for all parties',
                                        ];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' => 'Rental Comparative Market Analysis',
                                        ];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' => 'Marketing to many groups, pages, and affiliates.',
                                        ];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' => 'Marketing on social media platforms',
                                        ];
                                        $services_data[] = ['target' => '', 'name' => 'Professional Photography'];
                                        $services_data[] = ['target' => '', 'name' => 'Aerial Photography'];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' => 'Professional Videography or 3D virtual walk-through tour',
                                        ];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' =>
                                                'Property gets emailed to Tenant searching for homes that fit their criteria the moment the property gets listed directly through the MLS.',
                                        ];
                                        $services_data[] = [
                                            'target' => '',
                                            'name' => 'Property management after the property is leased.',
                                        ];
                                        $services_data[] = ['target' => '.custom_services', 'name' => 'Other'];
                                    @endphp
                                    <select name="services[]" id="services" multiple class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($services_data as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected_in($item['name'], @$auction->get->services) }}
                                                data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-hand-point-right" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_services {{ is_hidden(@$auction->get->custom_services) }}">
                                    <label class="fw-bold">Write Custom Services *</label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Services" name="custom_services"
                                        value="{{ @$auction->get->custom_services }}"
                                        data-icon="fa-regular fa-hand-point-right" id="custom_services" required />
                                </div>
                            </div>

                            <div class="wizard-step">
                                <h5>Does the landlord have any preferred agent(s) whom they would like to notify to
                                    participate in the auction?</h5>
                                <div class="form-group">
                                    <label class="fw-bold"> Agent Email Address: </label>
                                    <input type="email" name="preffered_agent_email" id="preffered_agent_email"
                                        class="form-control has-icon" data-icon="fa-solid fa-envelope"
                                        placeholder="eg. mail@example.com"
                                        value="{{ @$auction->get->preffered_agent_email }}">
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold"> Agent Phone: </label>
                                    <input type="text" name="preffered_agent_phone" id="preffered_agent_phone"
                                        class="form-control has-icon" data-icon="fa-solid fa-phone" placeholder=""
                                        value="{{ @$auction->get->preffered_agent_phone }}">
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
                                    $(function() {
                                        cma_questions();
                                    });
                                </script>
                            @endpush

                            <div class="wizard-step cma_questions">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Does the landlord want a free rental analysis?
                                    </label>
                                    <select name="need_cma" id="need_cma" class="grid-picker"
                                        style="justify-content: flex-start;" onchange="cma_questions();" required>
                                        <option value=""></option>
                                        @foreach ($yes_or_nos as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->need_cma) }} data-target=""
                                                class="card flex-column" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>



                            <div class="wizard-step">
                                <div class="row align-items-end">
                                    <div class="form-group mt-4 col-md-12">
                                        <label class="fw-bold">
                                            Pictures of Landlord's Property
                                        </label>
                                        <input type="file" name="photos[]" size="60" class="form-control"
                                            multiple accept="image/*" />
                                    </div>
                                    <div class="form-group mt-4 col-md-12">
                                        <label class="fw-bold">
                                            Video Tour of Landlord's property
                                        </label>
                                        <input type="text" name="video_url" class="form-control has-icon"
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
        {{-- <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    What price does the landlord want to ideally list the property for? *
                </label>
                <input type="text" name="cma_q1" id="cma_q1" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar" value="{{ @$auction->get->cma_q1 }}" placeholder="0.00" required>
            </div>
        </div> --}}

        {{-- <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    Picture link for the most accurate analysis
                </label>
                <input type="url" name="cma_q2" id="cma_q2" class="form-control"
                    value="{{ @$auction->get->cma_q2 }}">
            </div>
        </div>

        <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    Video link for the most accurate analysis
                </label>
                <input type="url" name="cma_q3" id="cma_q3" class="form-control"
                    value="{{ @$auction->get->cma_q3 }}">
            </div>
        </div> --}}

    </template>
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
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
            // changeAuctionType("Auction (Timer)");
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
            check_custom();
            $(elm).parent().children('select').trigger('change');
            console.log(v);
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
