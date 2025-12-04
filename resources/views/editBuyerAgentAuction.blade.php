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
                    <form class="p-4 pt-0 mainform" action="{{ route('buyer.update-auction') }}" method="POST"
                        enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="id" value="{{ $id }}">
                        @push('scripts')
                            <script>
                                $(function() {
                                    $('#working_with_agent').change(function() {
                                        var val = $(this).val();
                                        if (val == "Yes") {
                                            $('.wizard-step-next').attr("disabled", "disabled");
                                            $('.yes_message').text(
                                                "This is a service for buyers that are not currently working with a licensed agent."
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
                                Edit Hire Buyer's Agent auction - {{ @$auction->get->title }}
                            </h4>
                            <div class="wizard-steps-progress">
                                <div class="steps-progress-percent"></div>
                            </div>
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">Are you currently working with an agent?</label>
                                    <select class="grid-picker" name="working_with_agent" id="working_with_agent"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $yes_or_no)
                                            <option value="{{ $yes_or_no['name'] }}"
                                                data-target="{{ $yes_or_no['target'] }}"
                                                {{ @$auction->get->working_with_agent == $yes_or_no['name'] ? 'selected' : '' }}
                                                class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
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

                            {{-- <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Is Buyer interested in Residential or Commercial?
                                    </label>
                                    <div>
                                        @php
                                            $interested_in = [['name' => 'Residential', 'icon' => '<i class="fa-solid fa-house-chimney-window"></i>', 'target' => ''], ['name' => 'Commercial', 'icon' => '<i class="fa-solid fa-hotel"></i>', 'target' => '']];
                                        @endphp
                                        <select name="interested_in" id="interested_in" class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($interested_in as $item)
                                                <option value="{{ $item['name'] }}"
                                                    {{ @$auction->get->interested_in == $item['name'] ? 'selected' : '' }}
                                                    data-target="{{ $item['target'] }}" class="card flex-row fw-bold"
                                                    style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div> --}}

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
                                                    {{ @$auction->get->auction_type == $item['name'] ? 'selected' : '' }}
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
                                                    {{ @$auction->get->auction_length == $item['name'] ? 'selected' : '' }}
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
                                </div>

                                <div class="form-group">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Counties</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @if (gettype(@$auction->get->counties))
                                                @foreach (@$auction->get->counties as $item)
                                                    <tr>
                                                        <td>
                                                            <input type="text" name="counties[]" placeholder="County"
                                                                id="counties" class="form-control search_places"
                                                                data-icon="fa-solid fa-tree-city"
                                                                data-msg-required="Please enter county"
                                                                value="{{ $item }}"
                                                                @if ($loop->iteration == 1) required @endif>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td>
                                                        <input type="text" name="counties[]" placeholder="County"
                                                            id="counties" class="form-control search_places"
                                                            data-icon="fa-solid fa-tree-city"
                                                            data-msg-required="Please enter county" required>
                                                    </td>
                                                </tr>
                                            @endif
                                            <tr class="county_btn_row">
                                                <td><button type="button" class="btn btn-secondary btn-sm w-100"
                                                        onclick="add_county_row();"><i class="fa-solid fa-plus"></i> Add
                                                        New
                                                        Row</button></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">State</label>
                                    <input type="text" name="state" data-type="states" placeholder="Florida, USA"
                                        id="state" value="{{ @$auction->get->state }}"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
                                        data-msg-required="Please enter state" required>
                                </div>

                            </div>

                            {{-- <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">Add Title</label>
                                    <input type="text" name="title" value="{{ @$auction->get->title }}"
                                        placeholder="eg. Need a buyer's agent for a house" id="title"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-heading"
                                        data-msg-required="Please enter title" required>
                                </div>
                            </div> --}}

                            <div class="wizard-step">
                                @php
                                    $terms_of_contract = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '12 months', 'target' => ''], ['name' => 'Other', 'target' => '.custom_contract_terms']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Terms of the contract with the real estate agent:</label>
                                    <select name="terms_of_contract" id="terms_of_contract" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($terms_of_contract as $toc)
                                            <option value="{{ $pt['name'] }}"
                                                {{ @$auction->get->terms_of_contract == $toc['name'] ? 'selected' : '' }}
                                                data-target="" class="card flex-column fw-bold"
                                                style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $toc['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div
                                        class="form-group custom_contract_terms @if (@$auction->get->custom_contract_terms == '') d-none @endif">
                                        <label>Garage Spaces Needed:</label>
                                        <input type="text" name="custom_contract_terms" id="custom_contract_terms"
                                            class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                                            value="{{ @$auction->get->custom_contract_terms }}" required>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step">
                                @php
                                    $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Please select the property types in which the buyer is
                                        interested in purchasing:</label>
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

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label>
                                        Please select the sale provisions in which the buyer is interested in purchasing:
                                    </label>
                                    @php
                                        $types_of_prop = [['name' => 'Regular Sale'], ['name' => 'Pre-Construction'], ['name' => 'Currently Being Built'], ['name' => 'New Construction'], ['name' => 'REO/Bank Owned'], ['name' => 'Assignment Contract (Wholesale properties)'], ['name' => 'Short Sale'], ['name' => 'Other']];
                                    @endphp
                                    <div class="select2-parent">
                                        <select name="type_of_prop" id="type_of_prop" class="grid-picker"
                                            style="justify-content: flex-start;" multiple required>
                                            <option value=""></option>
                                            @foreach ($types_of_prop as $pt)
                                                <option value="{{ $pt['name'] }}"
                                                    {{ @$auction->get->type_of_prop == $pt['name'] ? 'selected' : '' }}
                                                    data-target="" class="card flex-row fw-bold"
                                                    style="width:calc(50% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-7px;"></i>'>
                                                    {{ $pt['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        At what price point is the buyer interested in making a purchase?
                                        <span class="text-danger">*</span>
                                    </label>
                                    @php
                                        $buyer_budgets = [['name' => '100k or less'], ['name' => '100k-200k'], ['name' => '200k-300k'], ['name' => '300k-400k'], ['name' => '400k-500k'], ['name' => '500k-600k'], ['name' => '600k-700k'], ['name' => '700k-800k'], ['name' => '800-900k'], ['name' => '900k-1 million'], ['name' => '1 million +'], ['name' => '2 million +'], ['name' => '3 million +'], ['name' => '4 million +'], ['name' => '5 million +'], ['name' => '10 million +']];
                                    @endphp
                                    <select name="buyer_budget" id="buyer_budget" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($buyer_budgets as $pt)
                                            <option value="{{ $pt['name'] }}"
                                                {{ @$auction->get->buyer_budget == $pt['name'] ? 'selected' : '' }}
                                                data-target="" class="card flex-column" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $pt['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="wizard-step">
                                @php
                                    $financings_info = [['name' => 'Yes, pre-approved'], ['name' => 'Yes, cash buyer'], ['name' => 'Yes, cash and cryptocurrency'], ['name' => 'No, I need a lender recommendation'], ['name' => 'No, I have my own lender I will apply with']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Has the buyer been pre-approved for a loan for the selected
                                        amount, or are they a cash buyer, or will they be using a combination of cash and
                                        cryptocurrency for the purchase?</label>
                                    <select class="grid-picker" name="financing_info" id="financing_info" required>
                                        <option value="">Select</option>
                                        @foreach ($financings_info as $financing_info)
                                            <option value="{{ $financing_info['name'] }}"
                                                {{ @$auction->get->financing_info == $financing_info['name'] ? 'selected' : '' }}
                                                class="card flex-column fw-bold" style="width:calc(33.33% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $financing_info['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @php
                                $bedrooms = [['name' => '1+', 'target' => ''], ['name' => '2+', 'target' => ''], ['name' => '3+', 'target' => ''], ['name' => '4+', 'target' => ''], ['name' => '5+', 'target' => ''], ['name' => '6+', 'target' => ''], ['name' => '7+', 'target' => ''], ['name' => '8+', 'target' => ''], ['name' => '9+', 'target' => ''], ['name' => '10+', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Other', 'target' => '.other_bedrooms']];
                                $bathrooms = [['name' => '1', 'target' => ''], ['name' => '1.5+', 'target' => ''], ['name' => '2+', 'target' => ''], ['name' => '2.5+', 'target' => ''], ['name' => '3+', 'target' => ''], ['name' => '3.5+', 'target' => ''], ['name' => '4+', 'target' => ''], ['name' => '4.5+', 'target' => ''], ['name' => '5+', 'target' => ''], ['name' => '6+', 'target' => ''], ['name' => '7+', 'target' => ''], ['name' => '8+', 'target' => ''], ['name' => '9+', 'target' => ''], ['name' => '10+', 'target' => ''], ['name' => 'Other', 'target' => '.other_bathrooms']];
                            @endphp
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">How many bedrooms does the buyer require?</label>
                                    <select class="grid-picker" name="bedrooms" id="bedrooms"
                                        style="justify-content: center;" required>
                                        <option value="">Select</option>
                                        @foreach ($bedrooms as $bedroom)
                                            <option value="{{ $bedroom['name'] }}"
                                                data-target="{{ $bedroom['target'] }}"
                                                {{ @$auction->get->bedrooms == $bedroom ? 'selected' : '' }}
                                                class="card flex-column" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-solid fa-bed"></i>'>
                                                {{ $bedroom['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group other_bedrooms @if (@$auction->get->other_bedrooms == '') d-none @endif">
                                    <label class="fw-bold" for="other_bedrooms">Bedrooms:</label>
                                    <input type="text" name="other_bedrooms" id="other_bedrooms"
                                        placeholder="Custom Bedrooms" class="form-control has-icon"
                                        data-icon="fa-solid fa-bed" data-msg-required="Please enter Custom Bedrooms"
                                        required value="{{ @$auction->get->other_bedrooms }}">
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">How many bathrooms does the buyer require?</label>
                                    <select class="grid-picker" name="bathrooms" id="bathrooms"
                                        style="justify-content: center;" required>
                                        <option value="">Select</option>
                                        @foreach ($bathrooms as $bathroom)
                                            <option value="{{ $bathroom['name'] }}"
                                                data-target="{{ $bathroom['target'] }}"
                                                {{ @$auction->get->bathrooms == $bathroom ? 'selected' : '' }}
                                                class="card flex-column" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-solid fa-bath"></i>'>
                                                {{ $bathroom['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group other_bathrooms @if (@$auction->get->other_bathrooms == '') d-none @endif">
                                    <label class="fw-bold" for="other_bathrooms">Bathrooms:</label>
                                    <input type="text" name="other_bathrooms" id="other_bathrooms"
                                        placeholder="Custom Bathrooms" class="form-control has-icon"
                                        data-icon="fa-solid fa-bath" data-msg-required="Please enter Custom Bathrooms"
                                        required value="{{ @$auction->get->other_bathrooms }}">
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group col-md-6 mt-4">
                                    <label class="fw-bold">
                                        Please indicate the concession that the buyer wishes to receive, if any (up to 0.5%
                                        of the agent's commission):
                                    </label>
                                    <input type="number" name="concession" value="{{ @$auction->get->concession }}"
                                        placeholder="0.00" id="concession" min="0" max=".05" step=".01"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-dollar"
                                        data-msg-required="">
                                </div>
                                <small>(Available in all the states except: Alaska, Oregon, Kansas, Missouri, Oklahoma,
                                    Louisiana, & Mississippi. Iowa restricts rebates when one or more brokers assist a
                                    client to buy or sell a property.)</small>

                                @php
                                    $financings = [['name' => 'Cash'], ['name' => 'Conventional'], ['name' => 'FHA'], ['name' => 'Jumbo'], ['name' => 'VA'], ['name' => 'USDA'], ['name' => 'No-Doc'], ['name' => 'Non-QM'], ['name' => 'Assumable'], ['name' => 'Exchange/Trade'], ['name' => 'Lease Option'], ['name' => 'Lease Purchase'], ['name' => 'Private Financing Available'], ['name' => 'Special Funding'], ['name' => 'Seller Financing'], ['name' => 'Bitcoin (BTC)'], ['name' => 'Ethereum (ETH)'], ['name' => 'Litecoin (LTC)'], ['name' => 'Bitcoin Cash (BCH)'], ['name' => 'Dash (Dash)'], ['name' => 'Ripple (XRP)'], ['name' => 'Tether (USDT)'], ['name' => 'USD Coin (USDC)'], ['name' => 'NFT'], ['name' => 'Other']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Please indicate the financing/currency that the buyer will use
                                        to purchase the property:</label>
                                    <select class="grid-picker" name="financing_currency[]" id="financing_currency"
                                        multiple required data-msg-required="Please select financing/currency">
                                        <option value="">Select</option>
                                        @foreach ($financings as $financing)
                                            <option value="{{ $financing['name'] }}" data-target=""
                                                {{ @$auction->get->$financing == $financing['name'] ? 'selected' : '' }}
                                                class="card flex-column fw-bold" style="width:calc(25% - 10px);"
                                                data-icon=''>
                                                {{ $financing['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>

                            <div class="wizard-step">

                                @php
                                    $conditions_interested = [['name' => 'Not Updated: Requires a complete update.'], ['name' => 'Tear Down: Requires complete demolition and reconstruction.'], ['name' => 'Semi-updated, needs minor updates: Partially updated, but requires additional minor updates.'], ['name' => 'Move-in Ready, Completely Updated: Fully updated and ready for occupancy.'], ['name' => 'Open to any type of property condition.']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Select the property conditions Buyer is interested in
                                        purchasing:</label>
                                    <select class="grid-picker" name="condition_interested" id="condition_interested"
                                        required>
                                        <option value="">Select</option>
                                        @foreach ($conditions_interested as $condition_interested)
                                            <option value="{{ $condition_interested['name'] }}"
                                                {{ @$auction->get->condition_interested == $condition_interested['name'] ? 'selected' : '' }}
                                                class="card flex-row fw-bold" style="width:calc(50% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $condition_interested['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>

                            <div class="wizard-step">

                                @php
                                    $services_data = [];
                                    $services_data[] = ['target' => '', 'name' => 'Buyer’s listing on the platform BidYourOffer.com'];
                                    $services_data[] = ['target' => '', 'name' => 'Comparative Market Analysis of the property the Buyer is looking to purchase'];
                                    $services_data[] = ['target' => '', 'name' => 'Emailed properties that fit the criteria of what the Buyer is looking for the moment the property gets listed. This is set up directly from the MLS to get the fastest most up to date listings.'];
                                    $services_data[] = ['target' => '', 'name' => 'Marketing Buyer’s listing on many groups, pages, and affiliates with QR code that leads to listing on BidYourOffer.com'];
                                    $services_data[] = ['target' => '', 'name' => 'Marketing Buyer’s listing on social media platforms with QR code or listing link that leads to listing on BidYourOffer.com'];
                                    $services_data[] = ['target' => '', 'name' => 'Schedule video tours'];
                                    $services_data[] = ['target' => '', 'name' => 'Neighborhood marketing to the Buyers chosen neighborhood with a QR code or listing link that leads directly to the Buyer’s listing on BidYourOffer.com'];
                                    $services_data[] = ['target' => '', 'name' => 'Marketing on Loopnet (Commercial Only)'];
                                    $services_data[] = ['target' => '', 'name' => 'Marketing on Crexi (Commercial Only)'];
                                    $services_data[] = ['target' => '', 'name' => 'Buyer Rebate Credit of .5% from agents’ commissions. Credit is paid at closing to help buyer pay closing costs or to help buy down Buyer’s interest rate. (Available in all the states except: Alaska, Oregon, Kansas, Missouri, Oklahoma, Louisiana, & Mississippi. Iowa restricts rebates when one or more brokers assist a client to buy or sell a property.)'];
                                    $services_data[] = ['target' => '.other_services', 'name' => 'Other'];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">Select the services Buyer wants hired agent to provide:</label>
                                    <select class="grid-picker" name="services[]" id="services" multiple required>
                                        <option value="">Select</option>
                                        @foreach ($services_data as $service)
                                            <option value="{{ $service['name'] }}"
                                                {{ in_array($service['name'], @$auction->get->services) ? 'selected' : '' }}
                                                data-target="{{ $service['target'] }}" class="card flex-row fw-bold"
                                                style="width:calc(100% - 0px);"
                                                data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                                                {{ $service['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group other_services @if (@$auction->get->other_services == '' || @$auction->get->other_services == 'null') d-none @endif ">
                                    <label>Write other services</label>
                                    <input type="text" name="other_services" id="other_services"
                                        value="{{ @$auction->get->other_services }}" class="form-control">
                                </div>
                            </div>

                            <div class="wizard-step">

                                <div class="row">
                                    <div class="form-group mt-4 col-md-12">
                                        <label class="fw-bold">
                                            If the buyer has been pre-approved, what is the pre-approval amount?
                                        </label>
                                        <input type="number" name="preapproval_amount"
                                            value="{{ @$auction->get->preapproval_amount }}" placeholder="0.00"
                                            id="preapproval_amount" class="form-control has-icon search_places"
                                            data-icon="fa-solid fa-dollar">
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-6 form-group">
                                        <label class="fw-bold">If the buyer is a cash buyer, what is their budget?</label>
                                        <input type="number" name="cash_budget"
                                            value="{{ @$auction->get->cash_budget }}" placeholder="0.00"
                                            id="cash_budget" class="form-control mt-4 has-icon search_places"
                                            data-icon="fa-solid fa-dollar">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="fw-bold">What percentage of the purchase price does the buyer plan to
                                            put down as
                                            their down payment?</label>
                                        <input type="number" name="crypto_budget"
                                            value="{{ @$auction->get->crypto_budget }}" placeholder="0.00"
                                            id="crypto_budget" class="form-control has-icon search_places"
                                            data-icon="fa-solid fa-percent">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="fw-bold">If the buyer intends to purchase using cryptocurrency, what
                                            is the current
                                            cash value?</label>
                                        <input type="number" name="cryptocurrency_value"
                                            value="{{ @$auction->get->cryptocurrency_value }}" placeholder="5000"
                                            id="cryptocurrency_value" class="form-control has-icon search_places"
                                            data-icon="fa-solid fa-dollar">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="fw-bold">If the buyer intends to make a purchase using
                                            cryptocurrency, what is the
                                            cash amount they plan to put down?</label>
                                        <input type="number" name="cryptocurrency_putdown_plan"
                                            value="{{ @$auction->get->cryptocurrency_putdown_plan }}" placeholder="5000"
                                            id="cryptocurrency_putdown_plan" class="form-control has-icon search_places"
                                            data-icon="fa-solid fa-dollar">
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-12 form-group">
                                        <label class="fw-bold">Additional details the buyer wishes to share with the
                                            agent:</label>
                                        <textarea name="additional_details" class="form-control" rows="5">{{ @$auction->get->additional_details }}</textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        When is Buyer looking to purchase?
                                    </label>
                                    <div class="form-group prompt_response" style="display: none;">
                                        <p class="text-danger" value="{{ @$auction->get->prompt_response }}">This is a
                                            service designed for buyers who are seeking to purchase a property within the next 3
                                            months.</p>
                                    </div>
                                    @php
                                        $timeframes = [['name' => 'ASAP', 'target' => ''], ['name' => '1 month', 'target' => ''], ['name' => '2 months', 'target' => ''], ['name' => '3 months', 'target' => ''], ['name' => '4 months', 'target' => ''], ['name' => '5 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => 'Over 6 months', 'target' => ''], ['name' => 'Not looking to purchase', 'target' => ''], ['name' => 'Other', 'target' => '.custom_amount']];
                                    @endphp
                                    <select name="buying_timeframe" id="buying_timeframe" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($timeframes as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                {{ selected($item['name'], @$auction->get->buying_timeframe) }}
                                                class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_amount @if (@$auction->get->custom_amount == '' || @$auction->get->custom_amount == 'null') d-none @endif ">
                                    <label>When is Buyer looking to purchase?</label>
                                    <input type="text" class="form-control has-icon" placeholder="Enter Amount"
                                        name="custom_amount" data-icon="fa-solid fa-dollar" id="custom_amount" required
                                        value="{{ @$auction->get->custom_amount }}" />
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

        function showPrompt(s) {
            $('.prompt_response').hide();
            if (s == "4 months" || s == "5 months" || s == "6 months" || s == "Over 6 months" || s ==
                "Not looking to purchase") {
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
