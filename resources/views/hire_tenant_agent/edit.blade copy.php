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
        <h4 class="title">{{ $title }}</h4>
        <div class="card">
            <div class="row">
                <div class="col-12 p-4">
                    <form class="p-4 pt-0 mainform" action="{{ route('tenant.hire.agent.auction.edit', @$auction->id) }}"
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
                            <div class="wizard-steps-progress">
                                <div class="steps-progress-percent"></div>
                            </div>
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">Does the Tenant have an active listing agreement with an
                                        agent?</label>
                                    <select class="grid-picker" name="working_with_agent" id="working_with_agent"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->working_with_agent) }}
                                                data-target="{{ $item['target'] }}" class="card flex-row fw-bold"
                                                style="width:calc(33.3% - 10px);"
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
                                        <select name="property_items" id="property_items" class="property_items grid-picker"
                                            style="justify-content: flex-start;" multiple required>
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
                                {{-- <div class="form-group">
                                    <label>
                                        Property Types Tenant is interested in:
                                        <span class="text-danger">*</span>
                                    </label>
                                    @php
                                        $property_types = [
                                            ['target'=>'','name' => 'Single Family Home'],
                                            ['target'=>'','name' => 'Townhome'],
                                            ['target'=>'','name' => 'Condo'],
                                            ['target'=>'','name' => 'Condo-Hotel'],
                                            ['target'=>'','name' => 'Villa'],
                                            ['target'=>'','name' => 'Multi-Family'],
                                            ['target'=>'','name' => 'Land/Lots'],
                                            ['target'=>'','name' => 'Manufactured Home'],
                                            ['target'=>'','name' => 'Modular Home'],
                                            ['target'=>'','name' => 'Agricultural'],
                                            ['target'=>'','name' => 'Assembly Building'],
                                            ['target'=>'','name' => 'Business'],
                                            ['target'=>'','name' => 'Five or more'],
                                            ['target'=>'','name' => 'Hotel/Motel'],
                                            ['target'=>'','name' => 'Industrial'],
                                            ['target'=>'','name' => 'Mixed Use'],
                                            ['target'=>'','name' => 'Office'],
                                            ['target'=>'','name' => 'Restaurant'],
                                            ['target'=>'','name' => 'Retail'],
                                            ['target'=>'','name' => 'Warehouse'],
                                            ['target'=>'.custom_property_type','name' => 'Other'],
                                            ];
                                    @endphp
                                    <select name="property_type" id="property_type" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($property_types as $item)
                                            <option value="{{ $item['name'] }}" {{selected($item['name'], @$auction->get->property_type)}}  data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group custom_property_type {{is_hidden(@$auction->get->custom_property_type)}}">
                                    <label class="fw-bold" for="custom_property_type">Custom Property Type: *</label>
                                    <input type="text" name="custom_property_type" id="custom_property_type" value="{{@$auction->get->custom_property_type}}"
                                        placeholder="Custom Property Type" class="form-control"
                                        data-icon="fa-solid fa-bath" required>
                                </div> --}}

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
                                                    style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                            </div>

                            <div class="wizard-step">
                                <label class="fw-bold">Please enter the cities, county, and state that the tenant is
                                    interested in renting in.</label>

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
                                                        <td><input type="text" name="cities[]"
                                                                value="{{ $item }}" placeholder="City"
                                                                data-type="cities" id="cities"
                                                                class="form-control  search_places"
                                                                data-icon="fa-solid fa-city"
                                                                data-msg-required="Please enter city"
                                                                @if ($loop->iteration == 1) required @endif></td>
                                                    </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td><input type="text" name="cities[]" placeholder="City"
                                                            data-type="cities" id="cities"
                                                            class="form-control  search_places" data-icon="fa-solid fa-city"
                                                            data-msg-required="Please enter city" required></td>
                                                </tr>
                                            @endif
                                            <tr class="city_btn_row">
                                                <td><button type="button" class="btn btn-secondary btn-sm w-100"
                                                        onclick="add_city_row();"><i class="fa-solid fa-plus"></i> Add New
                                                        Row</button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    {{-- <label class="fw-bold">City</label>
                                    <input type="text" name="city" data-type="cities"
                                        placeholder="St. Petersburg, FL, USA" id="city" value="{{@$auction->get->city}}"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                                        data-msg-required="Please enter city" required> --}}
                                </div>

                                <div class="form-group">
                                    <input type="hidden" name="lat" id="lat">
                                    <input type="hidden" name="lng" id="lng">
                                    <label class="fw-bold">County</label>
                                    <input type="text" name="county" data-type="counties"
                                        placeholder="Pinellas County, FL, USA" id="county"
                                        value="{{ @$auction->get->county }}" class="form-control has-icon search_places"
                                        data-icon="fa-solid fa-tree-city" data-msg-required="Please enter county"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">State</label>
                                    <input type="text" name="state" data-type="states" placeholder="Florida, USA"
                                        id="state" class="form-control has-icon search_places"
                                        value="{{ @$auction->get->state }}" data-icon="fa-solid fa-flag-usa"
                                        data-msg-required="Please enter state" required>
                                </div>

                            </div>

                            {{-- <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">Add Title</label>
                                    <input type="text" name="title"
                                        placeholder="eg. Need a tenant's agent for a house" id="title" value="{{@$auction->get->title}}"
                                        class="form-control has-icon" data-icon="fa-solid fa-heading"
                                        data-msg-required="Please enter title" required>
                                </div>
                            </div> --}}


                            @php
                                $bedrooms = [['name' => '1+', 'target' => ''], ['name' => '2+', 'target' => ''], ['name' => '3+', 'target' => ''], ['name' => '4+', 'target' => ''], ['name' => '5+', 'target' => ''], ['name' => '6+', 'target' => ''], ['name' => '7+', 'target' => ''], ['name' => '8+', 'target' => ''], ['name' => '9+', 'target' => ''], ['name' => '10+', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Other', 'target' => '.other_bedrooms']];
                                $bathrooms = [['name' => '1', 'target' => ''], ['name' => '1.5+', 'target' => ''], ['name' => '2+', 'target' => ''], ['name' => '2.5+', 'target' => ''], ['name' => '3+', 'target' => ''], ['name' => '3.5+', 'target' => ''], ['name' => '4+', 'target' => ''], ['name' => '4.5+', 'target' => ''], ['name' => '5+', 'target' => ''], ['name' => '6+', 'target' => ''], ['name' => '7+', 'target' => ''], ['name' => '8+', 'target' => ''], ['name' => '9+', 'target' => ''], ['name' => '10+', 'target' => ''], ['name' => 'Other', 'target' => '.other_bathrooms']];
                            @endphp
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">How many bedrooms does the tenant need?</label>
                                    <select class="grid-picker" name="bedrooms" id="bedrooms"
                                        style="justify-content: center;" required>
                                        <option value="">Select</option>
                                        @foreach ($bedrooms as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->bedrooms) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-solid fa-bed"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group other_bedrooms {{ is_hidden(@$auction->get->other_bedrooms) }}">
                                    <label class="fw-bold" for="other_bedrooms">Bedrooms:</label>
                                    <input type="text" name="other_bedrooms" id="other_bedrooms"
                                        value="{{ @$auction->get->other_bedrooms }}" placeholder="Custom Bedrooms"
                                        class="form-control has-icon" data-icon="fa-solid fa-bed"
                                        data-msg-required="Please enter Custom Bedrooms" required>
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">How many bathrooms does the tenant need?</label>
                                    <select class="grid-picker" name="bathrooms" id="bathrooms"
                                        style="justify-content: center;" required>
                                        <option value="">Select</option>
                                        @foreach ($bathrooms as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->bathrooms) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-solid fa-bath"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group other_bathrooms {{ is_hidden(@$auction->get->other_bathrooms) }}">
                                    <label class="fw-bold" for="other_bathrooms">Bathrooms:</label>
                                    <input type="text" name="other_bathrooms" id="other_bathrooms"
                                        value="{{ @$auction->get->other_bathrooms }}" placeholder="Custom Bathrooms"
                                        class="form-control has-icon" data-icon="fa-solid fa-bath"
                                        data-msg-required="Please enter Custom Bathrooms" required>
                                </div>
                            </div>



                            <div class="wizard-step">
                                {{-- @php
                                        $budgets = [
                                            ['target'=>'','name' => '$1000 or less'],
                                            ['target'=>'','name' => '$1000-$1500'],
                                            ['target'=>'','name' => '$1500-$2000'],
                                            ['target'=>'','name' => '$2000-$2500'],
                                            ['target'=>'','name' => '$2500-$3000'],
                                            ['target'=>'','name' => '$3000-$3500'],
                                            ['target'=>'','name' => '$3500-$4000'],
                                            ['target'=>'','name' => '$4000-$4500'],
                                            ['target'=>'','name' => '$5000-$6000'],
                                            ['target'=>'','name' => '$6000-$7000'],
                                            ['target'=>'','name' => '$7000+'],
                                            ['target'=>'.custom_budget','name' => 'Other'],
                                        ];
                                    @endphp --}}
                                <div class="form-group">
                                    <label>
                                        How much is the tenant’s budget?
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" name="budget" id="budget"
                                        value="{{ @$auction->get->budget }}" placeholder="8000+" class="form-control"
                                        data-icon="fa-solid fa-dollar-sign" required>
                                    {{-- <select name="budget" id="budget" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($budgets as $item)
                                            <option value="{{ $item['name'] }}" {{selected($item['name'], @$auction->get->budget)}} data-target="{{ $item['target'] }}"
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select> --}}
                                </div>


                                {{-- <div class="form-group custom_budget {{is_hidden(@$auction->get->custom_budget)}}">
                                    <label class="fw-bold" for="custom_budget">Custom Budget: *</label>
                                    <input type="text" name="custom_budget" id="custom_budget" value="{{@$auction->get->custom_budget}}"
                                        placeholder="$8000+" class="form-control"
                                        data-icon="fa-solid fa-bath" required>
                                </div> --}}
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label>
                                        When is the tenant looking to move in by?
                                        <span class="text-danger">*</span>
                                    </label>
                                    @php
                                        $lease_by = [['target' => '', 'name' => 'ASAP'], ['target' => '', 'name' => '15 days'], ['target' => '', 'name' => '30 days'], ['target' => '', 'name' => '60 days'], ['target' => '', 'name' => '90 days'], ['target' => '.custom_lease_by', 'name' => 'Other']];
                                    @endphp
                                    <select name="lease_by" id="lease_by" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($lease_by as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->lease_by) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group custom_lease_by {{ is_hidden(@$auction->get->custom_lease_by) }}">
                                    <label class="fw-bold" for="custom_lease_by">When is the tenant looking to move in
                                        by?</label>
                                    <input type="text" name="custom_lease_by" id="custom_lease_by"
                                        value="{{ @$auction->get->custom_lease_by }}" placeholder="Custom lease by"
                                        class="form-control" data-icon="fa-solid fa-bath" required>
                                </div>

                            </div>




                            <div class="wizard-step">
                                <div class="form-group">
                                    <label>
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
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->lease_for) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div
                                    class="form-group custom_lease_for {{ is_hidden(@$auction->get->custom_lease_for) }}">
                                    <label class="fw-bold" for="custom_lease_for">How long would the tenant like to lease
                                        for?</label>
                                    <input type="text" name="custom_lease_for" id="custom_lease_for"
                                        value="{{ @$auction->get->custom_lease_for }}" placeholder="Custom lease for"
                                        class="form-control" data-icon="fa-solid fa-bath" required>
                                </div>

                            </div>



                            <div class="wizard-step">
                                <div class="form-group">
                                    <label>
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
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->tenant_terms) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div
                                    class="form-group custom_tenant_terms {{ is_hidden(@$auction->get->custom_tenant_terms) }}">
                                    <label class="fw-bold" for="custom_tenant_terms">What are the tenant's terms for
                                        working with an agent to secure a property?</label>
                                    <input type="text" name="custom_tenant_terms"
                                        value="{{ @$auction->get->custom_tenant_terms }}" id="custom_tenant_terms"
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
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->agents_fee) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div
                                    class="form-group custom_agents_fee {{ is_hidden(@$auction->get->custom_agents_fee) }}">
                                    <label for="custom_agents_fee">Total finder's fee offered to agents: (To be paid by the
                                        tenant to the agent upon lease signing)</label>
                                    <input type="text" name="custom_agents_fee"
                                        value="{{ @$auction->get->custom_agents_fee }}" id="custom_agents_fee"
                                        placeholder="Custom Agents Fee" class="form-control" data-icon="fa-solid fa-bath"
                                        required>
                                </div>

                            </div>



                            <div class="wizard-step">
                                <div class="form-group">
                                    <label>
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
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->credit_score) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>



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
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->has_pets) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group custom_has_pets {{ is_hidden(@$auction->get->custom_has_pets) }}">
                                    <label for="custom_has_pets">Does the tenant have a pet, and if so,
                                        how many and what type of pet?</label>
                                    <input type="text" name="custom_has_pets"
                                        value="{{ @$auction->get->custom_has_pets }}" id="custom_has_pets"
                                        placeholder="Custom Agents Fee" class="form-control" data-icon="fa-solid fa-bath"
                                        required>
                                </div>

                            </div>




                            <div class="wizard-step">
                                <div class="form-group">
                                    <label>
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
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->evicted) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group custom_evicted {{ is_hidden(@$auction->get->custom_evicted) }}">
                                    <label class="fw-bold" for="custom_evicted">Explain when and why:</label>
                                    <input type="text" name="custom_evicted"
                                        value="{{ @$auction->get->custom_evicted }}" id="custom_evicted"
                                        placeholder="Explain when and why" class="form-control"
                                        data-icon="fa-solid fa-bath" required>
                                </div>

                            </div>



                            <div class="wizard-step">
                                <div class="form-group">
                                    <label>
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
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->convicted) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div
                                    class="form-group custom_convicted {{ is_hidden(@$auction->get->custom_convicted) }}">
                                    <label class="fw-bold" for="custom_convicted">Explain when and what for:</label>
                                    <input type="text" name="custom_convicted"
                                        value="{{ @$auction->get->custom_convicted }}" id="custom_convicted"
                                        placeholder="Explain when and what for" class="form-control"
                                        data-icon="fa-solid fa-bath" required>
                                </div>

                            </div>




                            <div class="wizard-step">
                                {{-- @php
                                    $monthly_income = [['target' => '', 'name' => '$2000 or less'], ['target' => '', 'name' => '$2000-$3000'], ['target' => '', 'name' => '$4000-$5000'], ['target' => '', 'name' => '$5000-$6000'], ['target' => '', 'name' => '$6000-$7000'], ['target' => '', 'name' => '$7000-$8000'], ['target' => '', 'name' => '$8000-$9000'], ['target' => '', 'name' => '$9000-$10000'], ['target' => '', 'name' => '$11000-$12000'], ['target' => '', 'name' => '$12000-$13000'], ['target' => '', 'name' => '$14000-$15000'], ['target' => '', 'name' => '$15,000+ a month'], ['target' => '.custom_monthly_income', 'name' => 'Other']];
                                @endphp --}}
                                <div class="form-group">
                                    <label>
                                        What is the tenant's monthly net household income?
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" name="monthly_income"
                                        value="{{ @$auction->get->monthly_income }}" id="monthly_income"
                                        placeholder="8000" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar-sign" required>
                                    {{-- <select name="monthly_income" id="monthly_income" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($monthly_income as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->monthly_income) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select> --}}
                                </div>

                                {{-- <div
                                    class="form-group custom_monthly_income {{ is_hidden(@$auction->get->custom_monthly_income) }}">
                                    <label class="fw-bold" for="custom_monthly_income">Custom monthly net household
                                        income: *</label>
                                    <input type="text" name="custom_monthly_income"
                                        value="{{ @$auction->get->custom_monthly_income }}" id="custom_monthly_income"
                                        placeholder="Custom monthly net household income" class="form-control"
                                        data-icon="fa-solid fa-bath" required>
                                </div> --}}

                            </div>



                            <div class="wizard-step">

                                @php
                                    $services_data = [];
                                    $services_data[] = ['target' => '', 'name' => 'Tenant’s listing on the platform BidYourOffer.com.'];
                                    $services_data[] = ['target' => '', 'name' => 'Email notifications for properties that fit the tenant’s criteria as soon as the properties are listed on the MLS, providing the fastest and most up-to-date listings.'];
                                    $services_data[] = ['target' => '', 'name' => 'Marketing of the tenant’s listing on various groups, pages, and affiliates, along with a QR code or listing link that leads to the listing on BidYourOffer.com.'];
                                    $services_data[] = ['target' => '', 'name' => 'Promotion of the tenant’s listing on social media platforms with a QR code or listing link that leads to the listing on BidYourOffer.com.'];
                                    $services_data[] = ['target' => '', 'name' => 'Scheduled video tours of the tenant’s preferred properties (ideal for out-of-town tenants).'];
                                    $services_data[] = ['target' => '.other_services', 'name' => 'Other-–the Tenant may enter any additional services they request from the Agent.'];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Select the included services that the Agent will provide to the
                                        Tenant:</label>
                                    <select class="grid-picker" name="services[]" id="services" multiple required>
                                        <option value="">Select</option>
                                        @foreach ($services_data as $service)
                                            <option value="{{ $service['name'] }}"
                                                data-target="{{ $service['target'] }}"
                                                {{ selected_in($item['name'], @$auction->get->services) }}
                                                class="card flex-row fw-bold" style="width:calc(100% - 0px);"
                                                data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                                                {{ $service['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group other_services {{ is_hidden(@$auction->get->other_services) }}">
                                    <label>Write other services</label>
                                    <input type="text" name="other_services"
                                        value="{{ @$auction->get->other_services }}" id="other_services"
                                        class="form-control">
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label>
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
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->has_preferred_agent) }}
                                                data-target="{{ $item['target'] }}" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group preferred_agent {{ is_hidden(@$auction->get->preferred_agent) }}">
                                    <h5>Please enter the first name, last name,
                                        brokerage, phone number, and email address of the preferred agent(s):</h5>
                                    <div class="form-group">
                                        <label>First Name:</label>
                                        <input type="text" name="first_name" value="{{ $auction->get->first_name }}"
                                            id="first_name" placeholder="First Name" class="form-control has-icon"
                                            data-icon="fa-solid fa-user" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Last Name:</label>
                                        <input type="text" name="last_name" value="{{ $auction->get->last_name }}"
                                            id="last_name" placeholder="First Name" class="form-control has-icon"
                                            data-icon="fa-solid fa-user" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Brokerage:</label>
                                        <input type="text" name="brokerage" value="{{ $auction->get->brokerage }}"
                                            id="brokerage" placeholder="Brokerage" class="form-control has-icon"
                                            data-icon="fa-solid fa-handshake" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Cell phone #:</label>
                                        <input type="text" name="phone" value="{{ $auction->get->phone }}"
                                            id="phone" placeholder="Phone" class="form-control has-icon"
                                            data-icon="fa-solid fa-phone" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Email:</label>
                                        <input type="text" name="email" value="{{ $auction->get->email }}"
                                            id="email" placeholder="Email" class="form-control has-icon"
                                            data-icon="fa-solid fa-envelope" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Business Card:</label>
                                        <input type="text" name="business_card"
                                            value="{{ $auction->get->business_card }}" id="business_card"
                                            placeholder="Business Card" class="form-control has-icon"
                                            data-icon="fa-solid fa-id-badge" required>
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
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
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
