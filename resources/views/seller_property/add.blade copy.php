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

            </div>
            <div class="card-body">
                <div class="wizard-steps-progress">
                    <div class="steps-progress-percent"></div>
                </div>
                <form class="p-4 pt-0 mainform" action="{{ route('add-listing') }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    {{-- Slide 1 --}}
                    <div class="wizard-step" id="after_this" data-step="1">
                        <div class="form-group">
                            <label class="fw-bold" for="address">Address:</label>
                            <input type="text" name="address" data-type="address"
                                placeholder="199 Market St, San Francisco, CA" id="address"
                                class="form-control has-icon search_places" data-icon="fa-solid fa-location-dot"
                                data-msg-required="Please enter address" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold" for="address">City:</label>
                            <input type="text" name="city" placeholder="City" data-type="cities" id="city"
                                class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                                data-msg-required="Please enter city" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="address">County:</label>
                            <input type="text" name="county" placeholder="County" id="county"
                                class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                                data-msg-required="Please enter county" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="address">State:</label>
                            <input type="text" name="state" placeholder="State" data-type="states" id="state"
                                class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
                                data-msg-required="Please enter state" required>
                        </div>
                    </div>
                    {{-- Slide 1 --}}
                    {{-- Slide 2 --}}
                    <div class="wizard-step" data-step="2">
                        <div class="form-group">
                            <label for="address">Listing Date:</label>
                            <input type="date" name="listing_date" id="listing_date"
                                class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                data-msg-required="Please enter listing date" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Expiration Date:</label>
                            <input type="date" name="expiration_date" id="expiration_date"
                                class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                data-msg-required="Please enter expiration date" required>
                        </div>
                    </div>
                    {{-- Slide 2 --}}
                    {{-- Slide 3 --}}
                    <div class="wizard-step" data-step="3">
                        <div class="form-group">
                            <label class="fw-bold">
                                Listing Service Type:
                            </label>
                            <div>
                                @php
                                    $auction_types = [['name' => 'Full Service', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => ''], ['name' => ' Limited Service', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>', 'target' => '']];
                                @endphp
                                <select name="service_type" id="service_type" class="grid-picker"
                                    style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($auction_types as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                            data-icon='{{ $item['icon'] }}'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">
                                Representation:
                            </label>

                            @php
                                $representation = [['name' => 'Seller Represented', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => ''], ['name' => 'Not Represented', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>', 'target' => '']];
                            @endphp

                            <select name="representation" id="representation" class="grid-picker"
                                style="justify-content: flex-start;" required>
                                <option value=""></option>
                                @foreach ($representation as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='{{ $item['icon'] }}'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 3 --}}
                    {{-- Slide 4 --}}
                    <div class="wizard-step" data-step="4">
                        <div class="form-group">
                            <label class="fw-bold">
                                Special Sale Provision:
                            </label>
                            <div>
                                @php
                                    $special_sale_provision = [['name' => 'Auction', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => ''], ['name' => 'Bank Owned/Reo', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => ''], ['name' => 'Government Owned', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => ''], ['name' => 'Probate Listing', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => ''], ['name' => 'Short Sale', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => ''], ['name' => 'None', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => ''], ['name' => 'Other', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => '.special_sale_provision']];
                                @endphp

                                <select name="special_sale" id="special_sale" class="grid-picker"
                                    style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($special_sale_provision as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                            data-icon='{{ $item['icon'] }}'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group special_sale_provision d-none">
                            <label class="fw-bold">Custom Input:</label>
                            <input type="text" name="custom_special_sale_provision" id="custom_special_sale_provision"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode" required>
                        </div>
                    </div>
                    {{-- Slide 5 --}}
                    <div class="wizard-step" data-step="5">
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
                                            class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
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
                                            class="card flex-row fw-bold {{ $item['class'] }}"
                                            style="width:calc(33.3% - 10px);"
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
                        <h4>Price and Terms:</h4>
                        <div class="form-group ">
                            <label class="fw-bold" for="starting_price">Starting Price:</label>
                            <input type="number" step="0.01" name="starting_price" placeholder="0.00"
                                id="starting_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar" data-msg-required="Please enter Starting Price" required>
                        </div>

                        <div class="form-group ">
                            <label class="fw-bold" for="buy_now_price">Buy Now Price:</label>
                            <input type="number" step="0.01" name="buy_now_price" placeholder="0.00"
                                id="buy_now_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar" data-msg-required="Please enter Buy Now Price" required>
                        </div>

                        <div class="form-group ">
                            <label class="fw-bold" for="reserve_price">Reserve Price:</label>
                            <input type="number" step="0.01" name="reserve_price" placeholder="0.00"
                                id="reserve_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar">
                        </div>
                        {{-- 12 June 2023  --}}
                        <div class="form-group row ">
                            <div class="col-md-6">
                                <label class="fw-bold">Escrow Amount(Reserve Terms):</label>
                                <input type="number" name="escrow_amount" id="term_escrow_amount" placeholder=""
                                    class="form-control" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Escrow Amount(Buy Now Terms):</label>
                                <input type="number" name="escrow_amount2" id="term_escrow_amount" placeholder=""
                                    class="form-control" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="col-md-6 ">
                                <label class="fw-bold">Inspection Period(Reserve Terms):</label>
                                <input type="number" name="inspection_period" id="term_inspection_period"
                                    placeholder="" class="form-control has-icon" data-icon="fa-solid fa-calendar-days"
                                    required>
                            </div>
                            <div class="col-md-6 ">
                                <label class="fw-bold">Inspection Period(Buy Now Terms):</label>
                                <input type="number" name="inspection_period2" id="term_inspection_period"
                                    placeholder="" class="form-control has-icon" data-icon="fa-solid fa-calendar-days"
                                    required>
                            </div>

                        </div>
                        <div class="form-group row ">
                            <div class="col-md-6">
                                <label class="fw-bold">Number of days seller will accept for closing:(Reserve
                                    Terms)</label>
                                <input type="number" name="closing_days" id="closing_days" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-calendar-day" required>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Number of days seller will accept for closing:(Buy Now
                                    Terms)</label>
                                <input type="number" name="closing_days2" id="closing_days" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-calendar-day" required>
                            </div>
                        </div>

                        @php
                            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">
                                Autobid:
                            </label>

                            <select name="auto_bid" id="auto_bid" class="grid-picker"
                                style="justify-content: flex-start;" required>
                                <option value=""></option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}"
                                        @php
if ($item['name'] == 'Yes') {
                                        $target = '.autobid_price';
                                    } else {
                                        $target = '';
                                    } @endphp
                                        data-target="{{ $target }}" class="card flex-row fw-bold"
                                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'
                                        @if ($item['name'] === 'No')
                                        selected
                                @endif
                                >
                                {{ $item['name'] }}
                                </option>
                                @endforeach
                            </select>
                        </div>


                        {{-- <div class="form-group ">
                            <label class="fw-bold" for="reserve_price">Autobid:</label>
                            <input class="form-check-input" type="radio" name="auto_bid" id="auto_bid1"
                                value="1">
                            <label class="form-check-label" for="auto_bid1">
                                Yes
                            </label>
                            <input class="form-check-input" type="radio" name="auto_bid" id="auto_bid0"
                                value="0" checked>
                            <label class="form-check-label" for="auto_bid0">
                                No
                            </label>
                        </div> --}}
                        <div class="form-group autobid_price ">
                            <label class="fw-bold" for="autobid_price">Autobid Price:</label>
                            <input type="number" step="0.01" name="autobid_price" placeholder="0.00"
                                id="autobid_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar">
                            <label class="fw-bold" for="autobid_price">Autobid Price:</label>
                            <input type="number" step="0.01" name="autobid_price2" placeholder="0.00"
                                id="autobid_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar">
                            <label class="fw-bold" for="autobid_price">Autobid Price:</label>
                            <input type="number" step="0.01" name="autobid_price3" placeholder="0.00"
                                id="autobid_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar">
                        </div>
                        @php
                            $contigencies = [['name' => 'Home Inspection', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Appraisal', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Financing', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Sale of property', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Contingencies Accepted by Seller:</label>
                            <select class="grid-picker" name="contigencies_accepted_by_seller"
                                id="contigencies_accepted_by_seller" style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($contigencies as $item)
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
                        @php
                            $term_financings = [
                                ['name' => 'Cash', 'target' => ''],
                                ['name' => 'Conventional', 'target' => ''],
                                ['name' => 'FHA', 'target' => ''],
                                ['name' => 'VA', 'target' => ''],
                                ['name' => 'Ethereum (ETH)', 'target' => ''],
                                ['name' => 'Litecoin (LTC)', 'target' => ''],
                                ['name' => 'Bitcoin Cash (BCH)', 'target' => ''],
                                ['name' => 'Dash (DASH)', 'target' => ''],
                                ['name' => 'Ripple (XRP)', 'target' => ''],
                                ['name' => 'Tether (USDT)', 'target' => ''],
                                ['name' => 'USD Coin (USDC)', 'target' => ''],
                                ['name' => 'NFT', 'target' => ''],
                                ['name' => 'USDA', 'target' => ''],
                                ['name' => 'Assumable', 'target' => ''],
                                ['name' => 'Exchange/Trade', 'target' => ''],
                                ['name' => 'Lease Option', 'target' => ''],
                                ['name' => 'Lease Purchase', 'target' => ''],
                                ['name' => 'Private Financing Available', 'target' => ''],
                                ['name' => 'Special Funding', 'target' => ''],
                                ['name' => 'Seller Financing', 'target' => ''],
                                ['name' => 'Jumbo', 'target' => ''],
                                ['name' => 'Non-QM', 'target' => ''],
                                ['name' => 'No-Doc', 'target' => ''],
                                ['name' => 'Other', 'target' => '.custom_term_financings'],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Acceptable Currency/ Financing:</label>
                            <select class="grid-picker" name="term_financings" id="term_financings"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($term_financings as $item)
                                    @php
                                        if ($item['name'] == 'Seller Financing') {
                                            $target = '.custom_seller_financing';
                                        } elseif ($item['name'] == 'Exchange/Trade') {
                                            $target = '.custom_exchange_trade';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group row custom_seller_financing">
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Purchase Price:</label>
                                <input type="number" name="purchase_price_seller_financing"
                                    id="purchase_price_seller_financing" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Down Payment:</label>
                                <input type="number" name="down_payment_seller_financing"
                                    id="down_payment_seller_financing" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Seller Financing Amount:</label>
                                <input type="number" name="seller_financing_amount" id="seller_financing_amount"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Interest Rate:</label>
                                <input type="number" name="interest_rate_seller_financing"
                                    id="interest_rate_seller_financing" class="form-control has-icon"
                                    data-icon="fa-solid fa-percent" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Term:</label>
                                <input type="text" name="term_seller_financing" id="term_seller_financing"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Monthly Payments:</label>
                                <input type="number" name="monthly_payment_seller_financing"
                                    id="monthly_payment_seller_financing" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Balloon Payment: </label>
                                <input type="number" name="baloon_payment_seller_financing"
                                    id="baloon_payment_seller_financing" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Acceptable Currency/ Financing:</label>
                                <input type="text" name="custom_term_financings" id="custom_term_financings"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Security:</label>
                                <input type="text" name="security_seller_financing" id="security_seller_financing"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Prepayment Penalty:</label>
                                <input type="text" name="prepayment_penalty" id="prepayment_penalty"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Closing Costs:</label>
                                <input type="text" name="closing_costs" id="closing_costs"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                        </div>
                        <div class="form-group row custom_exchange_trade">
                            @php
                                $exchange_trades = [['name' => 'Another home', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vehicles', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Boats', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Motorhomes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Artwork', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Jewelry', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Offered Exchange Item: </label>
                                <select class="grid-picker" name="exchange_trade" id="contigencies_accepted_by_seller"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($exchange_trades as $item)
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
                            <div class="form-group col-md-12">
                                <label class="fw-bold">Offered Value of the Exchange Item:</label>
                                <input type="number" name="offer_value_of_exchange_item"
                                    id="offer_value_of_exchange_item" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="fw-bold">Price Adjustment:</label>
                                <input type="number" name="price_adjustment_exchange_trade"
                                    id="price_adjustment_exchange_trade" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Purchase Price With Exchange/Trade:</label>
                                <input type="number" name="purchase_price_with_exchange_trade"
                                    id="purchase_price_with_exchange_trade" class="form-control has-icon"
                                    data-icon="fa-solid fa-percent" required>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="fw-bold">Additional Terms: </label>
                                <input type="text" name="additional_term" id="additional_term"
                                    class="form-control has-icon" data-icon="fa-solid fa-circle-check" required>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="fw-bold">Details of Exchange/Trade Item: </label>
                                <input type="text" name="detail_of_exchange_trade_item"
                                    id="detail_of_exchange_trade_item" class="form-control has-icon"
                                    data-icon="fa-solid fa-circle-check" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label class="fw-bold">Buyer's Premium (Credit buyer will pay seller at closing) -
                                    Optional:</label>
                                <input type="text" name="buyer_premium" id="buyer_premium" placeholder=""
                                    class="form-control " data-icon="fa-solid fa-dollar">
                            </div>

                            <div class="col-md-6">
                                <label class="fw-bold">Buyer's Rebate (Credit buyer will receive at closing) -
                                    Optional:</label>
                                <input type="text" name="seller_premium" id="seller_premium" placeholder=""
                                    class="form-control " data-icon="fa-solid fa-dollar">
                            </div>
                        </div>
                    </div>
                    {{-- Slide 6 --}}
                    {{-- Slide 7 --}}
                    <div class="wizard-step" data-step="7">
                        @php
                            $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property'], ['name' => 'Business Opportunity'], ['name' => 'Vacant Land (Current Use)']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Property Types:</label>
                            <select class="grid-picker" name="property_type" id="property_type"
                                onchange="changePropertyType(this.value);" required>
                                <option value="">Select</option>
                                @foreach ($property_types as $row_pt)
                                    <option value="{{ $row_pt['name'] }}" class="card flex-column"
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
                                        // Residential Items
                                        ['name' => '½ Duplex', 'class' => 'residential-length'],
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
                                        // Income Items
                                        ['name' => 'Duplex', 'class' => 'income-length'],
                                        ['name' => 'Triplex', 'class' => 'income-length'],
                                        ['name' => 'Quadplex', 'class' => 'income-length'],
                                        ['name' => 'Five or More (Residential units)', 'class' => 'income-length'],
                                        // Commercial items
                                        ['name' => 'Agriculture', 'class' => 'commercial-length'],
                                        ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                                        ['name' => 'Business', 'class' => 'commercial-length'],
                                        ['name' => 'Five or More (Commercial Units)', 'class' => 'commercial-length'],
                                        ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                                        ['name' => 'Industrial', 'class' => 'commercial-length'],
                                        ['name' => 'Mixed Use', 'class' => 'commercial-length'],
                                        ['name' => 'Office', 'class' => 'commercial-length'],
                                        ['name' => 'Restaurant', 'class' => 'commercial-length'],
                                        ['name' => 'Retail', 'class' => 'commercial-length'],
                                        ['name' => 'Warehouse', 'class' => 'commercial-length'],
                                        // Business Items
                                        ['name' => 'Aeronautical', 'class' => 'business-length'],
                                        ['name' => 'Agriculture', 'class' => 'business-length'],
                                        ['name' => 'Arts and Entertainment', 'class' => 'business-length'],
                                        ['name' => 'Assembly Hall', 'class' => 'business-length'],
                                        ['name' => 'Assisted Living', 'class' => 'business-length'],
                                        ['name' => 'Auto Dealer', 'class' => 'business-length'],
                                        ['name' => 'Auto Service', 'class' => 'business-length'],
                                        ['name' => 'Bar/Tavern/Lounge', 'class' => 'business-length'],
                                        ['name' => 'Barber/Beauty', 'class' => 'business-length'],
                                        ['name' => 'Car Wash', 'class' => 'business-length'],
                                        ['name' => 'Child Care', 'class' => 'business-length'],
                                        ['name' => 'Church', 'class' => 'business-length'],
                                        ['name' => 'Commercial', 'class' => 'business-length'],
                                        ['name' => 'Construction/Contractor', 'class' => 'business-length'],
                                        ['name' => 'Distribution', 'class' => 'business-length'],
                                        ['name' => 'Distributor Routine Ven', 'class' => 'business-length'],
                                        ['name' => 'Education/School', 'class' => 'business-length'],
                                        ['name' => 'Farm', 'class' => 'business-length'],
                                        ['name' => 'Fashion/Specialty', 'class' => 'business-length'],
                                        ['name' => 'Flex Space', 'class' => 'business-length'],
                                        ['name' => 'Florist/Nursery', 'class' => 'business-length'],
                                        ['name' => 'Food & Beverage', 'class' => 'business-length'],
                                        ['name' => 'Gas Station', 'class' => 'business-length'],
                                        ['name' => 'Grocery', 'class' => 'business-length'],
                                        ['name' => 'Heavy Weight Sales Service', 'class' => 'business-length'],
                                        ['name' => 'Hotel/Motel', 'class' => 'business-length'],
                                        ['name' => 'Industrial', 'class' => 'business-length'],
                                        ['name' => 'Light Items Sales Only', 'class' => 'business-length'],
                                        ['name' => 'Manufacturing', 'class' => 'business-length'],
                                        ['name' => 'Marine/Marina', 'class' => 'business-length'],
                                        ['name' => 'Medical', 'class' => 'business-length'],
                                        ['name' => 'Mixed', 'class' => 'business-length'],
                                        ['name' => 'Mobile/Trailer Park', 'class' => 'business-length'],
                                        ['name' => 'Other', 'class' => 'business-length'],
                                        ['name' => 'Professional Service', 'class' => 'business-length'],
                                        ['name' => 'Professional/Office', 'class' => 'business-length'],
                                        ['name' => 'Recreation', 'class' => 'business-length'],
                                        ['name' => 'Research & Development', 'class' => 'business-length'],
                                        ['name' => 'Residential', 'class' => 'business-length'],
                                        ['name' => 'Restaurant', 'class' => 'business-length'],
                                        ['name' => 'Retail', 'class' => 'business-length'],
                                        ['name' => 'Storage', 'class' => 'business-length'],
                                        ['name' => 'Theatre', 'class' => 'business-length'],
                                        ['name' => 'Timberland', 'class' => 'business-length'],
                                        ['name' => 'Veterinary', 'class' => 'business-length'],
                                        ['name' => 'Warehouse', 'class' => 'business-length'],
                                        ['name' => 'Wholesale', 'class' => 'business-length'],
                                        // Vacant Land Items
                                        ['name' => 'Billboard Site', 'class' => 'vacant_land-length'],
                                        ['name' => 'Business', 'class' => 'vacant_land-length'],
                                        ['name' => 'Cattle', 'class' => 'vacant_land-length'],
                                        ['name' => 'Commercial', 'class' => 'vacant_land-length'],
                                        ['name' => 'Farm', 'class' => 'vacant_land-length'],
                                        ['name' => 'Fishery', 'class' => 'vacant_land-length'],
                                        ['name' => 'Highway Frontage', 'class' => 'vacant_land-length'],
                                        ['name' => 'Horses', 'class' => 'vacant_land-length'],
                                        ['name' => 'Industrial', 'class' => 'vacant_land-length'],
                                        ['name' => 'Land Fill', 'class' => 'vacant_land-length'],
                                        ['name' => 'Livestock', 'class' => 'vacant_land-length'],
                                        ['name' => 'Mixed Use', 'class' => 'vacant_land-length'],
                                        ['name' => 'Nursery', 'class' => 'vacant_land-length'],
                                        ['name' => 'Orchard', 'class' => 'vacant_land-length'],
                                        ['name' => 'Other', 'class' => 'vacant_land-length'],
                                        ['name' => 'Pasture', 'class' => 'vacant_land-length'],
                                        ['name' => 'Poultry', 'class' => 'vacant_land-length'],
                                        ['name' => 'Ranch', 'class' => 'vacant_land-length'],
                                        ['name' => 'Residential', 'class' => 'vacant_land-length'],
                                        ['name' => 'Row Crops', 'class' => 'vacant_land-length'],
                                        ['name' => 'Sod Farm', 'class' => 'vacant_land-length'],
                                        ['name' => 'Subdivision', 'class' => 'vacant_land-length'],
                                        ['name' => 'Timber', 'class' => 'vacant_land-length'],
                                        ['name' => 'Tracts', 'class' => 'vacant_land-length'],
                                        ['name' => 'Trans/Cell Tower', 'class' => 'vacant_land-length'],
                                        ['name' => 'Tree Farm', 'class' => 'vacant_land-length'],
                                        ['name' => 'Unimproved Land', 'class' => 'vacant_land-length'],
                                        ['name' => 'Well Field', 'class' => 'vacant_land-length'],
                                    ];
                                @endphp
                                <select name="property_items" id="property_items" class="property_items grid-picker"
                                    style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($property_items as $item)
                                        <option value="{{ $item['name'] }}" data-target=""
                                            class="card flex-row {{ $item['class'] }}" style="width:calc(33.33% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    {{-- Slide 7 --}}
                    {{-- Slide Residential and Income Properties SLides Start --}}
                    {{-- Slide 8 --}}
                    <div class="wizard-step" data-step="8">
                        @php
                            $prop_conditions = [['name' => 'Not Updated: Requires a complete update.', 'target' => ''], ['name' => 'Tear Down: Requires complete demolition and reconstruction.', 'target' => ''], ['name' => 'Semi-updated, needs minor updates: Partially updated, but requires additional minor updates.', 'target' => ''], ['name' => 'Move-in Ready, Completely Updated: Fully updated and ready for occupancy.', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Property condition:</label>
                            <select class="grid-picker" name="prop_condition" id="prop_condition"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($prop_conditions as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                                        style="width:calc(50% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">List of Known Required Repairs:</label>
                            <textarea name="known_repairs" id="known_repairs" class="form-control" cols="30" rows="5"></textarea>
                        </div>


                    </div>
                    {{-- Slide 8 --}}
                    {{-- Slide 9 --}}
                    @php
                        $bedrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_bedrooms', 'name' => 'Other']];
                        $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.custom_bathrooms', 'name' => 'Other']];
                        $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];

                    @endphp
                    <div class="wizard-step" data-step="9">
                        <div class="form-group">
                            <label class="fw-bold">Bedrooms:</label>
                            <select class="grid-picker" name="bedrooms" id="bedrooms" style="" required>
                                <option value="">Select</option>
                                @foreach ($bedrooms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(9% - 10px);"
                                        data-icon='<i class="fa-solid fa-bed"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_bedrooms d-none">
                            <label class="fw-bold">Bedrooms:</label>
                            <input type="number" name="custom_bedrooms" id="custom_bedrooms"
                                class="form-control has-icon" data-icon="fa-solid fa-bed" required>
                        </div>
                    </div>
                    {{-- Slide 9 --}}
                    {{-- Slide 10 --}}
                    <div class="wizard-step" data-step="10">
                        <div class="form-group">
                            <label class="fw-bold">Bathrooms:</label>
                            <select class="grid-picker" name="bathrooms" id="bathrooms" style="" required>
                                <option value="">Select</option>
                                @foreach ($bathrooms as $item)
                                    @php
                                        if ($item['name'] == 'Other') {
                                            $target = '.custom_bathroom_residential_and_income';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-solid fa-bath"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_bathroom_residential_and_income d-none">
                            <label class="fw-bold">Bathrooms:</label>
                            <input type="number" name="custom_bathrooms" id="custom_bathrooms"
                                class="form-control has-icon" data-icon="fa-solid fa-bath" required>
                        </div>
                    </div>
                    {{-- Slide 10 --}}
                    {{-- Slide 11 --}}
                    <div class="wizard-step" data-step="11">
                        <div class="row ">
                            <div class="form-group">
                                <label class="fw-bold" for="heated_sqft">Heated Sqft:</label>
                                <input type="number" name="heated_sqft" id="heated_sqft"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                                    data-msg-required="Please enter Heated sqft" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="sqft"> Total Sqft:</label>
                                <input type="number" name="total_sqft" id="total_sqft"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                                    data-msg-required="Please enter Heated sqft" required>
                            </div>
                            @php
                                $heated_sources = [['name' => 'Appraisal', 'target' => ''], ['name' => 'Building', 'target' => ''], ['name' => 'Measured', 'target' => ''], ['name' => 'Owner Provided', 'target' => ''], ['name' => 'Public Records', 'target' => '']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold"> Sqft Heated Source:</label>
                                <select class="grid-picker" name="heated_source" id="heated_sources"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($heated_sources as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'
                                            class="card flex-column" style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Total Acreage:</label>
                                <select class="grid-picker" name="total_aceage" id="lot_size"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($lot_sizes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'
                                            class="card flex-column" style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="lot_size">Lot size:</label>
                                <input type="number" name="lot_size" id="lot_size"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                                    data-msg-required="Please enter Lot Size">
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="year_built">Year Built:</label>
                                <input type="text" name="year_built" id="year_built"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-calendar-day"
                                    data-msg-required="Please enter Year Built" required>
                            </div>
                        </div>
                    </div>
                    {{-- Slide 11 --}}
                    {{-- Slide 12 --}}
                    <div class="wizard-step" data-step="12">
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
                                ['name' => 'Other', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Appliances Included:</label>
                            <select class="grid-picker" name="appliances[]" id="appliances"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($appliances as $appliance)
                                    <option value="{{ $appliance['name'] }}" data-target="{{ $appliance['target'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $appliance['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">
                                Fireplace:
                            </label>

                            <select name="fireplace" id="fireplace" class="grid-picker"
                                style="justify-content: flex-start;" required>
                                <option value=""></option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 12 --}}
                    {{-- Slide 13 --}}
                    <div class="wizard-step" data-step="13">
                        <div class="form-group ">
                            <label class="fw-bold">Are there any furnishings included in the purchase?</label>
                            <select class="grid-picker" name="has_furnishing" id="has_furnishing"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.has_furnishing_residential_and_income';
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
                        <div class="row has_furnishing_residential_and_income d-none">

                            <div class="form-group">
                                <div class="form-group">
                                    <label class="fw-bold">What furnishings are included in the purchase?</label>
                                    <input type="text" name="furnishings_include" id="flood_zone_code" placeholder=""
                                        class="form-control has-icon" data-icon="fa-solid fa-qrcode" required>
                                </div>
                            </div>
                            @php
                                $additional_fees = [['name' => 'Additional Fees', 'target' => ''], ['name' => 'Included in Purchase Price', 'target' => '']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Are there any additional fees for the listed furnishings, or are
                                    they
                                    included in the purchase price? </label>

                                <select class="grid-picker" name="has_additional_fees" id="has_water_view"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($additional_fees as $item)
                                        @php
                                            if ($item['name'] == 'Additional Fees') {
                                                $target = '.has_additional_fees1';
                                            } else {
                                                $target = '';
                                            }
                                        @endphp
                                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group has_additional_fees1">
                                <div class="form-group">
                                    <label class="fw-bold">How much is the listed furniture?</label>
                                    <input type="number" name="listed_furniture_price" id="listed_furniture_price"
                                        placeholder="" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                                        required>
                                </div>
                            </div>
                        </div>


                    </div>
                    {{-- Slide 13 --}}
                    {{-- Slide 14 --}}
                    <div class="wizard-step" data-step="14">
                        <h4>Interior Features</h4>
                        @php
                            $interior_features = [
                                ['name' => 'Accessibility Features', 'target' => ''],
                                ['name' => 'Attic Fan', 'target' => ''],
                                ['name' => 'Attic Ventilator', 'target' => ''],
                                ['name' => 'Built in Features', 'target' => ''],
                                ['name' => 'Cathedral Ceiling(s)', 'target' => ''],
                                ['name' => 'Ceiling Fans(s)', 'target' => ''],
                                ['name' => 'Central Vacuum', 'target' => ''],
                                ['name' => 'Chair Rail', 'target' => ''],
                                ['name' => 'Coffered Ceiling(s)', 'target' => ''],
                                ['name' => 'Crown Molding', 'target' => ''],
                                ['name' => 'Dry Bar', 'target' => ''],
                                ['name' => 'Dumbwaiter', 'target' => ''],
                                ['name' => 'Eating Space In Kitchen', 'target' => ''],
                                ['name' => 'Elevator', 'target' => ''],
                                ['name' => 'High Ceiling(s)', 'target' => ''],
                                ['name' => 'In Wall Pest System', 'target' => ''],
                                ['name' => 'Kitchen/Family Room Combo', 'target' => ''],
                                ['name' => 'L Dining', 'target' => ''],
                                ['name' => 'Living Room/Dining Room Combo', 'target' => ''],
                                ['name' => 'Master Bedroom Main Floor', 'target' => ''],
                                ['name' => 'Master Bedroom Upstairs', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Open Floorplan', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
                                ['name' => 'Pest Guard System', 'target' => ''],
                                ['name' => 'Sauna', 'target' => ''],
                                ['name' => 'Skylight(s)', 'target' => ''],
                                ['name' => 'Smart Home', 'target' => ''],
                                ['name' => 'Solid Surface Counters', 'target' => ''],
                                ['name' => 'Solid Wood Cabinets', 'target' => ''],
                                ['name' => 'Split Bedroom', 'target' => ''],
                                ['name' => 'Stone Counters', 'target' => ''],
                                ['name' => 'Thermostat', 'target' => ''],
                                ['name' => 'Thermostat Attic Fan', 'target' => ''],
                                ['name' => 'Tray Ceiling(s)', 'target' => ''],
                                ['name' => 'Vaulted Ceiling(s)', 'target' => ''],
                                ['name' => 'Walk-In Closet(s)', 'target' => ''],
                                ['name' => 'Wet Bar', 'target' => ''],
                                ['name' => 'Window Treatments', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Interior Features:</label>
                            <select class="grid-picker" name="interior_features[]" multiple id="tenant_pays"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($interior_features as $interior_feature)
                                    <option value="{{ $interior_feature['name'] }}"
                                        data-target="{{ $interior_feature['target'] }}" class="card flex-column fw-bold"
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $interior_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 14 --}}
                    {{-- Slide 15 --}}
                    <div class="wizard-step" data-step="15">
                        <div class="form-group">
                            <label class="fw-bold">Number of Floors within the Property:</label>
                            <input type="text" name="number_of_buildings" id="number_of_buildings" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-hotel" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Floor Number:</label>
                            <input type="number" name="floors_in_unit" id="floors_in_unit" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-building" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Total Number of Floors in the Entire Building:</label>
                            <input type="number" name="total_floors" id="total_floors" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-building" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Building Elevator:</label>
                            <select class="grid-picker" name="building_elevator" id="building_elevator"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(25% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 15 --}}
                    {{-- Slide 16 --}}
                    <div class="wizard-step" data-step="16">
                        @php
                            $floor_coverings = [
                                ['name' => 'Brick/Stone', 'target' => ''],
                                ['name' => 'Carpet', 'target' => ''],
                                ['name' => 'Ceramic Tile', 'target' => ''],
                                ['name' => 'Concrete', 'target' => ''],
                                ['name' => 'Cork', 'target' => ''],
                                ['name' => 'Engineered Hardwood', 'target' => ''],
                                ['name' => 'Epoxy', 'target' => ''],
                                ['name' => 'Forestry Stewardship Certified', 'target' => ''],
                                ['name' => 'Granite', 'target' => ''],
                                ['name' => 'Laminate', 'target' => ''],
                                ['name' => 'Linoleum', 'target' => ''],
                                ['name' => 'Marble', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
                                ['name' => 'Parquet', 'target' => ''],
                                ['name' => 'Porcelain Tile', 'target' => ''],
                                ['name' => 'Quarry Tile', 'target' => ''],
                                ['name' => 'Reclaimed View', 'target' => ''],
                                ['name' => 'Recycled/Composite Flooring', 'target' => ''],
                                ['name' => 'Slate', 'target' => ''],
                                ['name' => 'Terrazzo', 'target' => ''],
                                ['name' => 'Tile', 'target' => ''],
                                ['name' => 'Travertine', 'target' => ''],
                                ['name' => 'Vinyl', 'target' => ''],
                                ['name' => 'Wood', 'target' => ''],
                                ['name' => 'Washer', 'target' => ''],
                                ['name' => 'Water Filtration System', 'target' => ''],
                                ['name' => 'Water Purifier', 'target' => ''],
                                ['name' => 'Water Softener', 'target' => ''],
                                ['name' => 'Whole House R.O. System', 'target' => ''],
                                ['name' => 'Wine Refrigerator', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Floor Covering:</label>
                            <select class="grid-picker" name="floor_covering[]" id="floor_covering"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($floor_coverings as $floor_covering)
                                    <option value="{{ $floor_covering['name'] }}"
                                        data-target="{{ $floor_covering['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $floor_covering['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 16 --}}
                    {{-- Slide 17 --}}
                    <div class="wizard-step" data-step="17">
                        @php
                            $front_exposures = [['name' => 'North', 'target' => ''], ['name' => 'East', 'target' => ''], ['name' => 'South', 'target' => ''], ['name' => 'West', 'target' => ''], ['name' => 'Southeast', 'target' => ''], ['name' => 'Northeast', 'target' => ''], ['name' => 'Southwest', 'target' => ''], ['name' => 'Northwest', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Front Exposure:</label>
                            <select class="grid-picker" name="front_exposure" id="front_exposure"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($front_exposures as $front_exposure)
                                    <option value="{{ $front_exposure['name'] }}"
                                        data-target="{{ $front_exposure['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $front_exposure['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 17 --}}
                    {{-- Slide 18 --}}
                    <div class="wizard-step" data-step="18">
                        @php
                            $foundations = [['name' => 'Basement', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick/Mortar', 'target' => ''], ['name' => 'Concrete Perimeter', 'target' => ''], ['name' => 'Crawlspace', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pillar/Post/Pier', 'target' => ''], ['name' => 'Slab', 'target' => ''], ['name' => 'Stem Wall', 'target' => ''], ['name' => 'Stilt/On Piling', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Foundation:</label>
                            <select class="grid-picker" name="foundation[]" id="foundation"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($foundations as $foundation)
                                    <option value="{{ $foundation['name'] }}" data-target="{{ $foundation['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $foundation['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 18 --}}
                    {{-- Slide 19 --}}
                    <div class="wizard-step" data-step="19">
                        @php
                            $exterior_constructions = [['name' => 'Asbestos', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Cedar', 'target' => ''], ['name' => 'Cement Siding', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'HardiPlank Type', 'target' => ''], ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''], ['name' => 'Log', 'target' => ''], ['name' => 'Metal Frame', 'target' => ''], ['name' => 'Metal Siding', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''], ['name' => 'Stone', 'target' => ''], ['name' => 'Stucco', 'target' => ''], ['name' => 'Tilt up Walls', 'target' => ''], ['name' => 'Vinyl Siding', 'target' => ''], ['name' => 'Wood Frame', 'target' => ''], ['name' => 'Wood Frame (FSC)', 'target' => ''], ['name' => 'Wood Siding ', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Exterior Construction:</label>
                            <select class="grid-picker" name="exterior_construction[]" id="exterior_construction"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($exterior_constructions as $exterior_construction)
                                    <option value="{{ $exterior_construction['name'] }}"
                                        data-target="{{ $exterior_construction['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $exterior_construction['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 19 --}}
                    {{-- Slide 20 --}}
                    <div class="wizard-step" data-step="20">
                        @php
                            $exterior_features = [
                                ['name' => 'Awning(s)', 'target' => ''],
                                ['name' => 'Balcony', 'target' => ''],
                                ['name' => 'Courtyard', 'target' => ''],
                                ['name' => 'Dog Run', 'target' => ''],
                                ['name' => 'French Doors', 'target' => ''],
                                ['name' => 'Garden', 'target' => ''],
                                ['name' => 'Gray Water System', 'target' => ''],
                                ['name' => 'Hurricane Shutters', 'target' => ''],
                                ['name' => 'Irrigation System', 'target' => ''],
                                ['name' => 'Lighting', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
                                ['name' => 'Outdoor Grill', 'target' => ''],
                                ['name' => 'Outdoor Kitchen', 'target' => ''],
                                ['name' => 'Outdoor Shower', 'target' => ''],
                                ['name' => 'Private Mailbox', 'target' => ''],
                                ['name' => 'Rain Barrel/Cistern(s)', 'target' => ''],
                                ['name' => 'Rain Gutters', 'target' => ''],
                                ['name' => 'Sauna', 'target' => ''],
                                ['name' => 'Shade Shutter(s)', 'target' => ''],
                                ['name' => 'Sidewalk', 'target' => ''],
                                ['name' => 'Sliding Doors', 'target' => ''],
                                ['name' => 'Sprinkler Metered', 'target' => ''],
                                ['name' => 'Storage', 'target' => ''],
                                ['name' => 'Tennis Court(s)', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Exterior Features:</label>
                            <select class="grid-picker" name="exterior_feature[]" id="exterior_feature"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($exterior_features as $exterior_feature)
                                    <option value="{{ $exterior_feature['name'] }}"
                                        data-target="{{ $exterior_feature['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $exterior_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 20 --}}
                    {{-- Slide 21 --}}
                    <div class="wizard-step" data-step="21">
                        @php
                            $lot_features = [
                                ['name' => 'Cleared', 'target' => ''],
                                ['name' => 'Coastal Construction Control Line', 'target' => ''],
                                ['name' => 'Conservation Area', 'target' => ''],
                                ['name' => 'Corner Lot', 'target' => ''],
                                ['name' => 'Cul-De-Sac', 'target' => ''],
                                ['name' => 'Drainage Canal', 'target' => ''],
                                ['name' => 'Farm', 'target' => ''],
                                ['name' => 'Flag Lot', 'target' => ''],
                                ['name' => 'Flood Insurance Required', 'target' => ''],
                                ['name' => 'Flood Zone', 'target' => ''],
                                ['name' => 'Greenbelt', 'target' => ''],
                                ['name' => 'Highway', 'target' => ''],
                                ['name' => 'Hilly', 'target' => ''],
                                ['name' => 'Historic District', 'target' => ''],
                                ['name' => 'In City Limits', 'target' => ''],
                                ['name' => 'In County', 'target' => ''],
                                ['name' => 'Irregular Lot', 'target' => ''],
                                ['name' => 'Key Lot', 'target' => ''],
                                ['name' => 'Landscaped', 'target' => ''],
                                ['name' => 'Level/Flat', 'target' => ''],
                                ['name' => 'Mountainous', 'target' => ''],
                                ['name' => 'Near Golf Course', 'target' => ''],
                                ['name' => 'Near Marina', 'target' => ''],
                                ['name' => 'Near Public Transit', 'target' => ''],
                                ['name' => 'On Golf Course', 'target' => ''],
                                ['name' => 'Oversized Lot', 'target' => ''],
                                ['name' => 'Pasture/Agriculture', 'target' => ''],
                                ['name' => 'Private', 'target' => ''],
                                ['name' => 'Rolling Slope', 'target' => ''],
                                ['name' => 'Sidewalks', 'target' => ''],
                                ['name' => 'Sloped', 'target' => ''],
                                ['name' => 'Street Brick', 'target' => ''],
                                ['name' => 'Street Dead-End', 'target' => ''],
                                ['name' => 'Street One Way', 'target' => ''],
                                ['name' => 'Street Paved', 'target' => ''],
                                ['name' => 'Street Private', 'target' => ''],
                                ['name' => 'Street Unpaved', 'target' => ''],
                                ['name' => 'Tip Lot', 'target' => ''],
                                ['name' => 'Unincorporated', 'target' => ''],
                                ['name' => 'Zoned for Horses', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Lot Features:</label>
                            <select class="grid-picker" name="lot_features[]" id="lot_features"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($lot_features as $lot_feature)
                                    <option value="{{ $lot_feature['name'] }}"
                                        data-target="{{ $lot_feature['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $lot_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 21 --}}
                    {{-- Slide 22 --}}
                    <div class="wizard-step" data-step="22">
                        @php
                            $roofs = [['name' => 'Built-Up', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Membrane', 'target' => ''], ['name' => 'Metal', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Roof Over', 'target' => ''], ['name' => 'Shake', 'target' => ''], ['name' => 'Shingle', 'target' => ''], ['name' => 'Slate', 'target' => ''], ['name' => 'Tile', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Roof:</label>
                            <select class="grid-picker" name="roof" id="roof"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($roofs as $roof)
                                    <option value="{{ $roof['name'] }}" data-target="{{ $roof['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $roof['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 22 --}}
                    {{-- Slide 23 --}}
                    <div class="wizard-step" data-step="23">
                        @php
                            $road_surface_types = [['name' => 'Asphalt', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Chip And Seal', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Dirt', 'target' => ''], ['name' => 'Gravel', 'target' => ''], ['name' => 'Limerock', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Paved', 'target' => ''], ['name' => 'Unimproved', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Road Surface Type:</label>
                            <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($road_surface_types as $road_surface_type)
                                    <option value="{{ $road_surface_type['name'] }}"
                                        data-target="{{ $road_surface_type['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $road_surface_type['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 23 --}}
                    {{-- Slide 24 --}}
                    <div class="wizard-step" data-step="24">
                        <div class="form-group">
                            <label class="fw-bold">Garage:</label>
                            <select class="grid-picker" name="garage" id="garage"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $yes_or_no)
                                    @php
                                        if ($yes_or_no['name'] == 'Yes') {
                                            $target = '.garage_spaces';
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
                        <div class="form-group garage_spaces">
                            <label class="fw-bold" for="garage_spaces">How many garage spaces?</label>
                            <input type="number" step="1" name="garage_spaces" placeholder="0"
                                id="garage_spaces" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-warehouse"
                                data-msg-required="Please enter how many garage spaces are available?" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Carport:</label>
                            <select class="grid-picker" name="carport" id="carport"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $yes_or_no)
                                    @php
                                        if ($yes_or_no['name'] == 'Yes') {
                                            $target = '.carport_spaces';
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
                        <div class="form-group carport_spaces">
                            <label class="fw-bold" for="carport_spaces">How many carport spaces?</label>
                            <input type="number" step="1" name="carport_spaces" placeholder="0"
                                id="carport_spaces" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-warehouse"
                                data-msg-required="Please enter how many carport spaces are available?" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Pool:</label>
                            <select class="grid-picker" name="pool" id="pool"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $yes_or_no)
                                    @php
                                        if ($yes_or_no['name'] == 'Yes') {
                                            $target = '.has_pool_residential_and_income';
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
                        @php
                            $private_or_community = [['name' => 'Private', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Community', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                        @endphp

                        <div class="form-group has_pool_residential_and_income d-none">
                            <select class="grid-picker" name="pool" id="pool"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($private_or_community as $yes_or_no)
                                    <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                        {{ $yes_or_no['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 24 --}}
                    {{-- Slide 25 --}}
                    <div class="wizard-step" data-step="25">
                        <div class="row ">
                            <div class="form-group">
                                <label class="fw-bold">Pets Allowed:</label>
                                <select class="grid-picker" name="has_rental_restrictions" id="has_rental_restrictions"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $item)
                                        @php
                                            if ($item['name'] == 'Yes') {
                                                $target = '.pets_allowed_question12';
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
                                $total_pets_allowed = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_pets_allowed', 'name' => 'Other']];
                            @endphp
                            <div class="form-group pets_allowed_question12 d-none">
                                <div class="form-group">
                                    <label class="fw-bold">Number of pets allowed:</label>
                                    <select class="grid-picker" name="total_pets_allowed" id="total_pets_allowed"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($total_pets_allowed as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-column" style="width:calc(10% - 10px);"
                                                data-icon='<i class="fa-solid fa-dog"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_pets_allowed d-none">
                                    <label class="fw-bold">Number of pets allowed:</label>
                                    <input type="text" name="custom_pets_allowed" id="custom_pets_allowed"
                                        class="form-control has-icon" data-icon="fa-solid fa-dog" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Max pet weight:</label>
                                    <input type="text" name="max_pet_weight" id="max_pet_weight"
                                        class="form-control has-icon" data-icon="fa-solid fa-dog" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Pet Restrictions:</label>
                                    <textarea name="pet_restrictions" id="pet_restrictions" class="form-control" cols="30" rows="5"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Slide 25 --}}
                    {{-- Slide 26 --}}
                    <div class="wizard-step" data-step="26">
                        <h4>Tax Info</h4>
                        <div class="form-group">
                            <label class="fw-bold">Tax ID (Parcel Number):</label>
                            <input type="number" name="tax_id" id="tax_id" class="form-control has-icon"
                                data-icon="fa-regular fa-id-card" placeholder="Tax ID (Parcel Number)" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Tax Year:</label>
                            <input type="text" name="tax_year" id="tax_year" class="form-control has-icon"
                                data-icon="fa-solid fa-qrcode" placeholder="Tax Year" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Taxes (Annual Amount):</label>
                            <input type="number" name="taxes_annual_amount" id="taxes_annual_ammount"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                placeholder="Taxes (Annual Amount)" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Total Number of Parcels:</label>
                            <input type="number" name="total_number_of_parcels" id="total_number_of_parcels"
                                class="form-control has-icon" placeholder="Total Number of Parcels"
                                data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Additional Tax ID's:</label>
                            <input type="number" name="additional_tax_id" id="additional_tax_id"
                                class="form-control has-icon" placeholder="Additional Tax ID's"
                                data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Zoning:</label>
                            <input type="text" name="zoning" id="zoning" placeholder="Zoning"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Legal Description:</label>
                            <input type="text" name="legal_description" id="legal_description"
                                class="form-control has-icon" placeholder="Legal Description"
                                data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group ">
                            <label class="fw-bold">Homestead:</label>
                            <select class="grid-picker" name="has_homestead" id="sewer"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 26 --}}
                    {{-- Slide 27 --}}
                    <div class="wizard-step" data-step="27">
                        <div class="form-group">
                            <label class="fw-bold">Is Property in a flood zone?</label>
                            <select class="grid-picker" name="is_in_flood_zone" id="is_in_flood_zone"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.has_flood_zoon';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(25% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group has_flood_zoon d-none">
                            <label class="fw-bold">Flood Zone Code:</label>
                            <input type="text" name="flood_zone_code" id="flood_zone_code"
                                placeholder="Flood Zone Code" class="form-control has-icon"
                                data-icon="fa-solid fa-qrcode">
                        </div>
                    </div>
                    {{-- Slide 27 --}}
                    {{-- Slide 28 --}}
                    <div class="wizard-step" data-step="28">
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
                                ['name' => 'BB/HS Internet Capable', 'target' => ''],
                                ['name' => 'Water Available', 'target' => ''],
                                ['name' => 'Water Connected', 'target' => ''],
                                ['name' => 'Water Nearby', 'target' => ''],
                                ['name' => 'Sewer Nearby', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Utilities:</label>
                            <select class="grid-picker" name="utilities[]" id="utilities"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($utilities1 as $utilitie12)
                                    <option value="{{ $utilitie12['name'] }}"
                                        data-target="{{ $utilitie12['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
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
                            <select class="grid-picker" name="water" id="water12"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($waters as $water)
                                    <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
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
                            <select class="grid-picker" name="sewer" id="sewer"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($sewers1 as $sewer12)
                                    <option value="{{ $sewer12['name'] }}" data-target="{{ $sewer12['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $sewer12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 28 --}}
                    {{-- Slide 29 --}}
                    <div class="wizard-step" data-step="29">
                        @php
                            $air_conditioning = [['name' => 'Central Air', 'target' => ''], ['name' => 'Humidity Control', 'target' => ''], ['name' => 'Mini-Split Unit(s)', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Wall/Window Unit(s)', 'target' => ''], ['name' => 'Zoned', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Air Conditioning:</label>
                            <select class="grid-picker" name="air_conditioning" id="air_conditioning"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($air_conditioning as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $heating_and_fuel = [['name' => 'Baseboard', 'target' => ''], ['name' => 'Central', 'target' => ''], ['name' => 'Electric', 'target' => ''], ['name' => 'Exhaust Fans', 'target' => ''], ['name' => 'Heat Pump', 'target' => ''], ['name' => 'Heat Recovery Unit', 'target' => ''], ['name' => 'Natural Gas', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Oil', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Partial', 'target' => ''], ['name' => 'Propane', 'target' => ''], ['name' => 'Radiant Ceiling', 'target' => ''], ['name' => 'Reverse Cycle', 'target' => ''], ['name' => 'Solar', 'target' => ''], ['name' => 'Space Heater', 'target' => ''], ['name' => 'Wall Furnace', 'target' => ''], ['name' => 'Wall Units / Window Unit', 'target' => ''], ['name' => 'Zoned', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Heating and Fuel:</label>
                            <select class="grid-picker" name="heating_and_fuel" id="heating_and_fuel"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($heating_and_fuel as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 29 --}}
                    {{-- Slide 30 --}}
                    <div class="wizard-step" data-step="30">
                        <div class="form-group">
                            <label class="fw-bold">Approximate Room Dimensions:</label>
                            <input type="text" name="approximate_room_dimensions" id="approximate_room_dimensions"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                                placeholder="(Width x Length)">
                        </div>
                        @php
                            $room_types = [
                                ['name' => 'Additional Bedroom', 'target' => ''],
                                ['name' => 'Balcony/Porch/Lanai', 'target' => ''],
                                ['name' => 'Basement', 'target' => ''],
                                ['name' => 'Bathroom 1', 'target' => ''],
                                ['name' => 'Bathroom 2', 'target' => ''],
                                ['name' => 'Bathroom 3', 'target' => ''],
                                ['name' => 'Bathroom 4', 'target' => ''],
                                ['name' => 'Bathroom 5', 'target' => ''],
                                ['name' => 'Bedroom 1', 'target' => ''],
                                ['name' => 'Bedroom 2', 'target' => ''],
                                ['name' => 'Bedroom 3', 'target' => ''],
                                ['name' => 'Bedroom 4', 'target' => ''],
                                ['name' => 'Bedroom 5', 'target' => ''],
                                ['name' => 'Bonus Room', 'target' => ''],
                                ['name' => 'Breezeway', 'target' => ''],
                                ['name' => 'Dining Room', 'target' => ''],
                                ['name' => 'Family Room', 'target' => ''],
                                ['name' => 'Florida Room', 'target' => ''],
                                ['name' => 'Foyer', 'target' => ''],
                                ['name' => 'Game Room', 'target' => ''],
                                ['name' => 'Great Room', 'target' => ''],
                                ['name' => 'Gym', 'target' => ''],
                                ['name' => 'Inside Utility', 'target' => ''],
                                ['name' => 'Interior In-Law Suite', 'target' => ''],
                                ['name' => 'Kitchen', 'target' => ''],
                                ['name' => 'Laundry', 'target' => ''],
                                ['name' => 'Library', 'target' => ''],
                                ['name' => 'Living Room', 'target' => ''],
                                ['name' => 'Loft', 'target' => ''],
                                ['name' => 'Master Bathroom', 'target' => ''],
                                ['name' => 'Master Bedroom', 'target' => ''],
                                ['name' => 'Media Room', 'target' => ''],
                                ['name' => 'Office', 'target' => ''],
                                ['name' => 'Sauna', 'target' => ''],
                                ['name' => 'Studio', 'target' => ''],
                                ['name' => 'Study/Den', 'target' => ''],
                                ['name' => 'Workshop', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Room Type:</label>
                            <select class="grid-picker" name="room_type[]" id="room_type"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($room_types as $room_type)
                                    <option value="{{ $room_type['name'] }}"
                                        data-target="{{ $room_type['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $room_type['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $room_levels = [['name' => 'Upper', 'target' => ''], ['name' => 'Basement', 'target' => ''], ['name' => 'First', 'target' => ''], ['name' => 'Second', 'target' => ''], ['name' => 'Third', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Room Level:</label>
                            <select class="grid-picker" name="room_level[]" id="room_level"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($room_levels as $room_level)
                                    <option value="{{ $room_level['name'] }}"
                                        data-target="{{ $room_level['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $room_level['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $bed_room_closest_types = [['name' => 'Built-in Closet', 'target' => ''], ['name' => 'No Closet', 'target' => ''], ['name' => 'Walk-in Closet', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Bedroom Closet Type:</label>
                            <select class="grid-picker" name="bed_room_closest_type[]" id="bed_room_closest_type"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($bed_room_closest_types as $bed_room_closest_type)
                                    <option value="{{ $bed_room_closest_type['name'] }}"
                                        data-target="{{ $bed_room_closest_type['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $bed_room_closest_type['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $room_primary_floor_coverings = [
                                ['name' => 'Bamboo', 'target' => ''],
                                ['name' => 'Brick/Stone', 'target' => ''],
                                ['name' => 'Carpet', 'target' => ''],
                                ['name' => 'Ceramic Tile', 'target' => ''],
                                ['name' => 'Concrete', 'target' => ''],
                                ['name' => 'Cork', 'target' => ''],
                                ['name' => 'Engineered Hardwood', 'target' => ''],
                                ['name' => 'Epoxy', 'target' => ''],
                                ['name' => 'Forestry Stewardship Certified', 'target' => ''],
                                ['name' => 'Granite', 'target' => ''],
                                ['name' => 'Laminate', 'target' => ''],
                                ['name' => 'Linoleum', 'target' => ''],
                                ['name' => 'Marble', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
                                ['name' => 'Parquet', 'target' => ''],
                                ['name' => 'Porcelain Tile', 'target' => ''],
                                ['name' => 'Quarry Tile', 'target' => ''],
                                ['name' => 'Reclaimed Wood', 'target' => ''],
                                ['name' => 'Recycled/Composite Flooring', 'target' => ''],
                                ['name' => 'Slate', 'target' => ''],
                                ['name' => 'Terrazzo', 'target' => ''],
                                ['name' => 'Tile', 'target' => ''],
                                ['name' => 'Travertine', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Room Primary Floor Covering:</label>
                            <select class="grid-picker" name="room_primary_floor_covering[]"
                                id="room_primary_floor_covering" style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($room_primary_floor_coverings as $room_primary_floor_covering)
                                    <option value="{{ $room_primary_floor_covering['name'] }}"
                                        data-target="{{ $room_primary_floor_covering['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $room_primary_floor_covering['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $room_features = [
                                ['name' => 'Bar', 'target' => ''],
                                ['name' => 'Bath with Spa/Hydro Massage Tub', 'target' => ''],
                                ['name' => 'Bath With Whirlpoo', 'target' => ''],
                                ['name' => 'Bidet', 'target' => ''],
                                ['name' => 'Breakfast Bar', 'target' => ''],
                                ['name' => 'Built-In Shelving', 'target' => ''],
                                ['name' => 'Built-In Shower Bench', 'target' => ''],
                                ['name' => 'Ceiling Fan(s)', 'target' => ''],
                                ['name' => 'Claw Foot Tub', 'target' => ''],
                                ['name' => 'Closet Pantry', 'target' => ''],
                                ['name' => 'Cooking Island', 'target' => ''],
                                ['name' => 'Desk Built-In ', 'target' => ''],
                                ['name' => 'Dual Sinks', 'target' => ''],
                                ['name' => 'En Suite Bathroom ', 'target' => ''],
                                ['name' => 'Exhaust Fan', 'target' => ''],
                                ['name' => 'Garden Bath ', 'target' => ''],
                                ['name' => 'Granite Counters', 'target' => ''],
                                ['name' => 'Handicap Accessible', 'target' => ''],
                                ['name' => 'Heated Floors', 'target' => ''],
                                ['name' => 'Island', 'target' => ''],
                                ['name' => 'Jack and Jill Bathroom', 'target' => ''],
                                ['name' => 'Linen Closet Bath', 'target' => ''],
                                ['name' => 'Makeup/Vanity Space', 'target' => ''],
                                ['name' => 'Multiple Shower Heads', 'target' => ''],
                                ['name' => 'Other- Specify in Remarks', 'target' => ''],
                                ['name' => 'Pantry', 'target' => ''],
                                ['name' => 'Rain Shower Head', 'target' => ''],
                                ['name' => 'Sauna', 'target' => ''],
                                ['name' => 'Shower- No Tub', 'target' => ''],
                                ['name' => 'Single Vanity', 'target' => ''],
                                ['name' => 'Sink-Pedestal ', 'target' => ''],
                                ['name' => 'Split Vanities ', 'target' => ''],
                                ['name' => 'Steam Shower', 'target' => ''],
                                ['name' => 'Stone Counters', 'target' => ''],
                                ['name' => 'Sunken Shower', 'target' => ''],
                                ['name' => 'Tall Countertops ', 'target' => ''],
                                ['name' => 'Tile Counters', 'target' => ''],
                                ['name' => 'Tub with Separate Shower Stall ', 'target' => ''],
                                ['name' => 'Tub with Shower', 'target' => ''],
                                ['name' => 'Urinal', 'target' => ''],
                                ['name' => 'Walk-In Pantry', 'target' => ''],
                                ['name' => 'Walk-In Tub', 'target' => ''],
                                ['name' => 'Water Closet/Priv Toliet', 'target' => ''],
                                ['name' => 'Window/Skylight in Bath', 'target' => ''],
                                ['name' => 'Other- Custom Input', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Room Features:</label>
                            <select class="grid-picker" name="room_feature[]" id="room_feature"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($room_features as $room_feature)
                                    @php
                                        if ($room_feature['name'] == 'Other- Custom Input') {
                                            $target = '.custom_room_features';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $room_feature['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $room_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_room_features">
                            <label class="fw-bold">Custom Room Features:</label>
                            <input type="text" name="custom_room_features" id="custom_room_features"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>

                    </div>
                    {{-- Slide 30 --}}
                    {{-- Slide 31 --}}
                    <div class="wizard-step" data-step="31">

                        <div class="form-group">
                            <label class="fw-bold">Water Access:</label>
                            <select class="grid-picker" name="has_water_access" id="has_water_access"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_access_residentail';
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
                            $water_access = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
                        @endphp
                        <div class="form-group water_access_residentail">
                            <select class="grid-picker" name="water_access[]" id="water_access"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($water_access as $water_access1)
                                    <option value="{{ $water_access1['name'] }}"
                                        data-target="{{ $water_access1['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_access1['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group ">
                            <label class="fw-bold">Water View:</label>
                            <select class="grid-picker" name="has_water_view" id="has_water_view"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_view_residential_and_income';
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
                            $water_views = [['name' => 'Bay/Harbor - Full', 'target' => ''], ['name' => 'Bay/Harbor - Partial', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Canal', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Gulf/Ocean - Full', 'target' => ''], ['name' => 'Gulf/Ocean - Partial', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'None', 'target' => '']];
                        @endphp
                        <div class="form-group water_view_residential_and_income d-none">
                            <select class="grid-picker" name="water_view[]" id="water_view"
                                style="justify-content: flex-start;" multiple required>
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
                        <div class="form-group">
                            <label class="fw-bold">Water Extras:</label>
                            <select class="grid-picker" name="has_water_extra" id="has_water_extra"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_extras_residential_and_income';
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
                        <div class="form-group water_extras_residential_and_income d-none ">
                            <select class="grid-picker" name="water_extras[]" id="water_extras"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($water_extras as $water_extra)
                                    <option value="{{ $water_extra['name'] }}"
                                        data-target="{{ $water_extra['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_extra['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Water Frontage:</label>
                            <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_frontage_residential_and_income';
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
                            $water_frontage = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
                        @endphp
                        <div class="form-group water_frontage_residential_and_income d-none">
                            <select class="grid-picker" name="water_frontage[]" id="water_frontage"
                                style="justify-content: flex-start;" multiple required>
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

                        @php
                            $view = [['name' => 'City', 'target' => ''], ['name' => 'Garden', 'target' => ''], ['name' => 'Golf Course', 'target' => ''], ['name' => 'Greenbelt', 'target' => ''], ['name' => 'Mountain(s)', 'target' => ''], ['name' => 'Park', 'target' => ''], ['name' => 'Pool', 'target' => ''], ['name' => 'Tennis Court', 'target' => ''], ['name' => 'Trees/Woods', 'target' => ''], ['name' => 'Water', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">View:</label>
                            <select class="grid-picker" name="view[]" id="view"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($view as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 31 --}}
                    {{-- Slide 32 --}}
                    <div class="wizard-step" data-step="32">
                        @php
                            $ownerships = [['name' => 'Co-Op', 'target' => ''], ['name' => 'Condominium', 'target' => ''], ['name' => 'Fee Simple', 'target' => ''], ['name' => 'Other', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Ownership and Co-Op:</label>
                            <select class="grid-picker" name="ownership" id="ownership"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($ownerships as $item)
                                    @php
                                        if ($item['name'] == 'Other') {
                                            $target = '.custom_ownership_residential_and_income';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_ownership_residential_and_income d-none">
                            <label class="fw-bold">Other:</label>
                            <input type="text" name="custom_ownership" id="custom_ownership"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode" placeholder="Other">
                        </div>
                    </div>
                    {{-- Slide 32 --}}
                    {{-- Slide 33 --}}
                    <div class="wizard-step" data-step="33">
                        @php
                            $occupant_types = [['name' => 'Owner', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tenant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Occupant Type:</label>
                            <select class="grid-picker" name="occupant_type" id="occupant_type"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($occupant_types as $item)
                                    @php
                                        if ($item['name'] == 'Tenant') {
                                            $target = '.tenant_conditions_residential_and_income';
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
                        <div class="row tenant_conditions_residential_and_income">
                            <div class="row for_residential_only">
                                <div class="form-group">
                                    <label class="fw-bold">Existing Lease or Tenant:</label>
                                    <select class="grid-picker" name="exiting_lease_or_tenant"
                                        id="exiting_lease_or_tenant" style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Monthly Rental Amount:</label>
                                    <input type="number" name="monthly_rental_ammount" id="monthly_rental_ammount"
                                        placeholder="" class="form-control has-icon" data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Days Notice to Tenant if not Renewing:</label>
                                    <input type="text" name="days_notice_to_terminate"
                                        id="days_notice_to_terminate" placeholder="" class="form-control has-icon"
                                        data-icon="fa-solid fa-qrcode">
                                </div>
                                <div class="form-group">
                                    <label for="address">End Date of Lease:</label>
                                    <input type="date" name="end_of_lease_date" id="listing_date"
                                        class="form-control has-icon search_places"
                                        data-icon="fa-regular fa-calendar-days"
                                        data-msg-required="Please enter End Date of Lease Date" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Slide 33 --}}
                    {{-- Slide 34 --}}
                    <div class="wizard-step" data-step="34">
                        <div class="form-group">
                            <label class="fw-bold">Can Property Be Leased:</label>
                            <select class="grid-picker" name="has_leasing" id="has_leasing"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.lease_restriction';
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
                        <div class="row lease_restriction">
                            <div class="form-group ">
                                <label class="fw-bold">Lease Restriction:</label>
                                <select class="grid-picker" name="has_lease_restriction" id="has_lease_restriction"
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
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Association Approval Required:</label>
                                <select class="grid-picker" name="association_approval_required"
                                    id="association_approval_required" style="justify-content: flex-start;" required>
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
                            @php
                                $minimum_lease_period = [['name' => '1-7 Days', 'target' => ''], ['name' => '1 Week', 'target' => ''], ['name' => '2 Week', 'target' => ''], ['name' => '1 Month', 'target' => ''], ['name' => '2 Month', 'target' => ''], ['name' => '3 Month', 'target' => ''], ['name' => '4 Month', 'target' => ''], ['name' => '5 Month', 'target' => ''], ['name' => '6 Month', 'target' => ''], ['name' => '7 Month', 'target' => ''], ['name' => '8-12 Month', 'target' => ''], ['name' => '1-2 Years', 'target' => ''], ['name' => '2+ Years', 'target' => ''], ['name' => 'No Minimum', 'target' => ''], ['name' => 'No Rent', 'target' => '']];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Minimum Lease Period:</label>
                                <select class="grid-picker" name="minimum_lease_period" id="minimum_lease_period"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($minimum_lease_period as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Maximum Time Per Year:</label>
                                <input type="text" name="minimum_lease_per_year" id="minimum_lease_period"
                                    class="form-control has-icon" data-icon="fa-solid fa-calendar-days"
                                    placeholder="Maximum Time Per Year" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Year of Ownership Prior to Leasing Required:</label>
                                <select class="grid-picker" name="years_of_ownership" id="years_of_ownership"
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
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Number of Ownership Years Prior to Leasing:</label>
                                <input type="number" name="number_of_ownership_prior_lease"
                                    id="number_of_ownership_prior_lease"
                                    placeholder="Number of Ownership Years Prior to Lease" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode" required>
                            </div>
                        </div>
                    </div>
                    {{-- Slide 34 --}}
                    {{-- Slide 35 --}}
                    <div class="wizard-step" data-step="35">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Unit Type</label>
                                <input type="text" name="unit_type" id="unit_type" class="form-control has-icon"
                                    placeholder="Unit Type" data-icon="fa-solid fa-qrcode">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Sqt Ft Heated</label>
                                <input type="number" name="sqt_ft_heated" id="sqt_ft_heated"
                                    placeholder="Sqt Ft Heated" class="form-control has-icon"
                                    data-icon="fa-solid fa-ruler-combined">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Number of Units</label>
                                <input type="number" name="number_of_units" id="number_of_units"
                                    placeholder="Number of Units" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Expected Rent</label>
                                <input type="number" name="expected_rent" id="expected_rent"
                                    placeholder="Expected Rent" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Garage Spaces</label>
                                <input type="number" name="garage_spaces_unit" id="garage_spaces_unit"
                                    placeholder="Garage Spaces" class="form-control has-icon"
                                    data-icon="fa-solid fa-warehouse">
                            </div>
                            <div class="form-group ">
                                <label class="fw-bold">Garage Attribute:</label>
                                <select class="grid-picker" name="garage_attribute" id="garage_attribute"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="fw-bold">Unit Type of Description:</label>
                                <textarea name="unit_type_of_description" id="unit_type_of_description" class="form-control" cols="30"
                                    rows="10"></textarea>
                            </div>

                            <div class="form-group col-md-4">
                                <label class="fw-bold">Annual Gross Income</label>
                                <input type="number" name="annual_gross_income" id="garage_attribute"
                                    placeholder="Annual Gross Income" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Total Monthly Rent</label>
                                <input type="number" name="total_monthly_rent" id="garage_attribute"
                                    placeholder="Total Monthly Rent" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Total Monthly Expenses</label>
                                <input type="number" name="total_monthly_expenses" id="garage_attribute"
                                    placeholder="Total Monthly Expenses" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Lease Terms</label>
                                <input type="text" name="lease_terms" id="garage_attribute"
                                    placeholder="Lease Terms" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Annual Net Income</label>
                                <input type="number" name="annual_net_income" id="garage_attribute"
                                    placeholder="Annual Net Income" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Est Annual Market Income</label>
                                <input type="text" name="est_annual_market_income" id="garage_attribute"
                                    placeholder="Est Annual Market Income" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Annual Expenses</label>
                                <input type="number" name="annual_expenses" id="garage_attribute"
                                    placeholder="Annual Expenses" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode">
                            </div>
                            @php
                                $terms_of_leases = [['name' => 'Gross Lease', 'target' => ''], ['name' => 'Net Lease', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pass Throughts', 'target' => ''], ['name' => 'Purchase Options', 'target' => ''], ['name' => 'Renewal Option', 'target' => '']];
                            @endphp
                            <div class="form-group road_frontage_next_hide ">
                                <label class="fw-bold">Terms of Lease:</label>
                                <select class="grid-picker" name="terms_of_lease" id="terms_of_lease"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($terms_of_leases as $terms_of_lease)
                                        <option value="{{ $terms_of_lease['name'] }}"
                                            data-target="{{ $terms_of_lease['target'] }}" class="card flex-row"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'
                                            style="width:calc(33.3% - 10px);">
                                            {{ $terms_of_lease['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $tenant_pays = [['name' => 'Association Fees', 'target' => ''], ['name' => 'Electricity', 'target' => ''], ['name' => 'Parking Fee', 'target' => ''], ['name' => 'Sewer', 'target' => ''], ['name' => 'Trash Collection', 'target' => ''], ['name' => 'Water', 'target' => '']];
                            @endphp
                            <div class="form-group road_frontage_next_hide ">
                                <label class="fw-bold">Tenant Pays:</label>
                                <select class="grid-picker" name="tenant_pays" id="terms_of_lease"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($tenant_pays as $tenant_pay)
                                        <option value="{{ $tenant_pay['name'] }}"
                                            data-target="{{ $tenant_pay['target'] }}" class="card flex-row"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'
                                            style="width:calc(33.3% - 10px);">
                                            {{ $tenant_pay['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            @php
                                $financial_sources = [['name' => 'Accountant', 'target' => ''], ['name' => 'Broker', 'target' => ''], ['name' => 'Owner', 'target' => ''], ['name' => 'Tax Return', 'target' => '']];
                            @endphp
                            <div class="form-group road_frontage_next_hide ">
                                <label class="fw-bold">Financial Sources:</label>
                                <select class="grid-picker" name="financial_sources" id="terms_of_lease"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($financial_sources as $financial_source)
                                        <option value="{{ $financial_source['name'] }}"
                                            data-target="{{ $financial_source['target'] }}" class="card flex-row"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'
                                            style="width:calc(33.3% - 10px);">
                                            {{ $financial_source['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Occupied:</label>
                                <select class="grid-picker" name="occupied" id="occupied"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Total Number of Units:</label>
                                <input type="number" name="total_number_of_units" placeholder="Total Number of Units"
                                    id="total_number_of_units" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode">
                            </div>

                        </div>
                    </div>
                    {{-- Slide 35 --}}
                    {{-- Slide 36 --}}
                    <div class="wizard-step" data-step="36">
                        <div class="form-group">
                            <label class="fw-bold">Does the property have an HOA, condo association, master
                                association,
                                and/or community fee?</label>
                            <select class="grid-picker" name="has_hoa" id="has_hoa"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.hoas_residential_and_income';
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
                            $hoa_fee_requirenments = [['name' => 'None', 'target' => ''], ['name' => 'Optional', 'target' => ''], ['name' => ' Required', 'target' => '']];
                        @endphp
                        <div class="row hoas_residential_and_income d-none">
                            @php
                                $community_features = [
                                    ['name' => 'Airport/Runway', 'target' => ''],
                                    ['name' => 'Association Recreation - Lease', 'target' => ''],
                                    ['name' => ' Association Recreation - Owned', 'target' => ''],
                                    ['name' => 'Buyer Approval Required', 'target' => ''],
                                    ['name' => 'Clubhouse', 'target' => ''],
                                    ['name' => 'Community Boat Ramp', 'target' => ''],
                                    ['name' => 'Community Mailbox', 'target' => ''],
                                    ['name' => 'Deed Restrictions', 'target' => ''],
                                    ['name' => 'Fishing', 'target' => ''],
                                    ['name' => 'Fitness Center', 'target' => ''],
                                    ['name' => 'Gated Community', 'target' => ''],
                                    ['name' => 'Golf Carts OK', 'target' => ''],
                                    ['name' => 'Golf Community', 'target' => ''],
                                    ['name' => 'Handicap Modified', 'target' => ''],
                                    ['name' => 'Horse Stable(s)', 'target' => ''],
                                    ['name' => 'Horses Allowed', 'target' => ''],
                                    ['name' => 'Irrigation-Reclaimed Water', 'target' => ''],
                                    ['name' => 'Lake', 'target' => ''],
                                    ['name' => 'No Truck/RV/Motorcycle Parking', 'target' => ''],
                                    ['name' => 'None', 'target' => ''],
                                    ['name' => 'Park', 'target' => ''],
                                    ['name' => 'Playground', 'target' => ''],
                                    ['name' => 'Pool', 'target' => ''],
                                    ['name' => 'Public Boat Ramp', 'target' => ''],
                                    ['name' => 'Racquetball', 'target' => ''],
                                    ['name' => 'Restaurant', 'target' => ''],
                                    ['name' => 'Sidewalk', 'target' => ''],
                                    ['name' => 'Special Community Restrictions', 'target' => ''],
                                    ['name' => 'Stream Seasonal', 'target' => ''],
                                    ['name' => 'Tennis Courts', 'target' => ''],
                                    ['name' => ' Water Access', 'target' => ''],
                                    ['name' => 'Waterfront', 'target' => ''],
                                    ['name' => 'Wheelchair Access', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Community Features:</label>
                                <select class="grid-picker" name="community_feature[]" id="community_feature"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($community_features as $community_feature)
                                        <option value="{{ $community_feature['name'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>"
                                            data-target="{{ $community_feature['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $community_feature['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $association_amenities = [
                                    ['name' => 'Airport/Runway', 'target' => ''],
                                    ['name' => 'Basketball Court', 'target' => ''],
                                    ['name' => 'Boat Slip', 'target' => ''],
                                    ['name' => 'Cable', 'target' => ''],
                                    ['name' => 'Clubhouse', 'target' => ''],
                                    ['name' => 'Dock', 'target' => ''],
                                    ['name' => 'Elevators', 'target' => ''],
                                    ['name' => 'Fence Restrictions', 'target' => ''],
                                    ['name' => 'Fitness Center', 'target' => ''],
                                    ['name' => 'Gated', 'target' => ''],
                                    ['name' => 'Golf Course', 'target' => ''],
                                    ['name' => 'Handicap Modified', 'target' => ''],
                                    ['name' => 'Horse Stables', 'target' => ''],
                                    ['name' => 'Laundry', 'target' => ''],
                                    ['name' => 'Lobby Key Required', 'target' => ''],
                                    ['name' => 'Maintenance', 'target' => ''],
                                    ['name' => 'Marina', 'target' => ''],
                                    ['name' => 'Optional Additional Fees', 'target' => ''],
                                    ['name' => 'Other', 'target' => ''],
                                    ['name' => 'Park', 'target' => ''],
                                    ['name' => 'Pickleball Court(s)', 'target' => ''],
                                    ['name' => 'Playground', 'target' => ''],
                                    ['name' => 'Pool', 'target' => ''],
                                    ['name' => 'Private Boat Ramp', 'target' => ''],
                                    ['name' => 'Racquet Ball', 'target' => ''],
                                    ['name' => 'Recreation Facilities', 'target' => ''],
                                    ['name' => 'Sauna', 'target' => ''],
                                    ['name' => 'Security', 'target' => ''],
                                    ['name' => 'Shuffleboard Court', 'target' => ''],
                                    ['name' => 'Spa/Hot Tubs', 'target' => ''],
                                    ['name' => 'Storage', 'target' => ''],
                                    ['name' => 'Tennis Court(s)', 'target' => ''],
                                    ['name' => 'Trails', 'target' => ''],
                                    ['name' => 'Vehicle Restrictions', 'target' => ''],
                                    ['name' => 'Wheelchair Access', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Association Amenities:</label>
                                <select class="grid-picker" name="association_amenitie[]" id="association_amenitie"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($association_amenities as $association_amenitie)
                                        <option value="{{ $association_amenitie['name'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>"
                                            data-target="{{ $association_amenitie['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $association_amenitie['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $fee_includes = [
                                    ['name' => '24-Hour Guard', 'target' => ''],
                                    ['name' => 'Cable TV', 'target' => ''],
                                    ['name' => 'Common Area Taxes', 'target' => ''],
                                    ['name' => 'Community Pool', 'target' => ''],
                                    ['name' => 'Electricity', 'target' => ''],
                                    ['name' => 'Escrow Reserves Fund', 'target' => ''],
                                    ['name' => 'Fidelity Bond', 'target' => ''],
                                    ['name' => 'Gas', 'target' => ''],
                                    ['name' => 'Insurance', 'target' => ''],
                                    ['name' => 'Internet', 'target' => ''],
                                    ['name' => 'Maintenance Exterior', 'target' => ''],
                                    ['name' => 'Maintenance Grounds', 'target' => ''],
                                    ['name' => 'Maintenance Repairs', 'target' => ''],
                                    ['name' => 'Manager', 'target' => ''],
                                    ['name' => 'None', 'target' => ''],
                                    ['name' => 'Other', 'target' => ''],
                                    ['name' => 'Pest Control', 'target' => ''],
                                    ['name' => 'Pool Maintenance', 'target' => ''],
                                    ['name' => 'Private Road', 'target' => ''],
                                    ['name' => 'Recreational Facilities', 'target' => ''],
                                    ['name' => 'Security', 'target' => ''],
                                    ['name' => 'Sewer', 'target' => ''],
                                    ['name' => 'Trash', 'target' => ''],
                                    ['name' => 'Water', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Fee Includes:</label>
                                <select class="grid-picker" name="fee_include[]" id="fee_include"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($fee_includes as $fee_include)
                                        <option value="{{ $fee_include['name'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>"
                                            data-target="{{ $fee_include['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $fee_include['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Amenities with Additional Fees:</label>
                                <input type="number" name="amenities_with_additional_fees"
                                    id="amenities_with_additional_fees" class="form-control has-icon"
                                    data-icon="fa-solid fa-qrcode" placeholder="Annual Expenses" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">CDD:</label>
                                <select class="grid-picker" name="has_cdd" id="has_cdd"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $item)
                                        @php
                                            if ($item['name'] == 'Yes') {
                                                $target = '.anual_cdd_fee_residential_and_income';
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
                            <div class="form-group anual_cdd_fee_residential_and_income d-none">
                                <label class="fw-bold">Annual CDD Fee</label>
                                <input type="number" name="annual_cdd_fee" id="annual_cdd_fee"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar ">
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Land Lease:</label>
                                <select class="grid-picker" name="has_land_lease" id="has_hoa"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $item)
                                        @php
                                            if ($item['name'] == 'Yes') {
                                                $target = '.land_lease_fee_residential_and_income';
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
                            <div class="form-group land_lease_fee_residential_and_income d-none">
                                <label class="fw-bold">Land Lease Fee</label>
                                <input type="number" name="land_lease_fee" id="land_lease_fee"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar ">
                            </div>
                        </div>
                    </div>
                    {{-- Slide 36 --}}
                    {{-- Slide 37 --}}
                    <div class="wizard-step" data-step="37">
                        <div class="form-group">
                            <label class="fw-bold"> Description:</label>
                            <textarea name="description" id="description" class="form-control" cols="30" rows="10" required></textarea>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Keywords:<small class="small">(Add keywords of the
                                    best property features) </small></label>
                            <input type="text" name="keywords" id="keywords" class="form-control has-icon"
                                data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Legal Disclamers:</label>
                            <input type="text" name="driving_directions" id="keywords"
                                class="form-control has-icon" data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Driving Directions:</label>
                            <input type="text" name="driving_directions" id="keywords"
                                class="form-control has-icon" data-icon="fa-solid fa-tag">
                        </div>
                        @php
                            $compensation = [['name' => 'Single Agent Compensation', 'target' => '.compensation'], ['name' => 'Non Rep Compensation', 'target' => '.compensation'], ['name' => 'Transaction Broker Compensation', 'target' => '.compensation']];

                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Compensation:</label>
                            <select class="grid-picker" name="compensation" id="fee_include"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($compensation as $item)
                                    @php
                                        if ($item['name'] == 'Single Agent Compensation') {
                                            $target = '.single_agent_compensation_amount_residential_and_income';
                                        } elseif ($item['name'] == 'Non Rep Compensation') {
                                            $target = '.non_rep_compensation_amount_residential_and_income';
                                        } elseif ($item['name'] == 'Transaction Broker Compensation') {
                                            $target = '.transaction_amount';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group single_agent_compensation_amount_residential_and_income">
                            <label class="fw-bold">Single Agent Compensation Amount:</label>
                            <input type="number" name="compensation_amount" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar">
                        </div>
                        <div class="form-group non_rep_compensation_amount_residential_and_income ">
                            <label class="fw-bold"> Non Rep Compensation Amount:</label>
                            <input type="number" name="compensation_amount" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar">
                        </div>
                        <div class="form-group transaction_amount">
                            <label class="fw-bold">Transaction Broker Compensation Amount:</label>
                            <input type="number" name="compensation_amount" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar">
                        </div>
                    </div>
                    {{-- Slide 37 --}}
                    {{-- Slide 38 --}}
                    <div class="wizard-step" data-step="38">
                        <div class="form-group">
                            <label class="fw-bold">Is the Seller actively seeking to purchase another property?
                            </label>
                            <select class="grid-picker" name="looking_other_property" id="looking_other_property"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.link_residential_and_income';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(50% - 10px);"
                                        data-icon="<i class='{{ $item['icon'] }}'></i>">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group link_residential_and_income">
                            <label class="fw-bold">Link to the listing on the Bid Your Offer platform:</label>
                            <input type="url" name="listing_link" id="listing_link" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>
                    </div>
                    {{-- Slide 38 --}}
                    {{-- Slide 39 --}}
                    <div class="wizard-step" data-step="39">
                        <h4> Title Company Information:</h4>
                        <div class="form-group">
                            <label class="fw-bold">Name:</label>
                            <input type="text" name="title_company_name" id="title_company_name" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Address:</label>
                            <input type="text" name="title_company_address" id="title_company_address"
                                placeholder="" class="form-control has-icon" data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Phone Number</label>
                            <input type="text" name="title_company_phone" id="title_company_phone" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-phone" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Email:</label>
                            <input type="text" name="title_company_email" id="titl_company_email" placeholder=""
                                data-icon="fa-solid fa-envelope" class="form-control has-icon" required>
                        </div>
                    </div>
                    {{-- Slide 39 --}}
                    {{-- Slide 40 --}}
                    <div class="wizard-step" data-step="40">
                        <h4>Agent info:</h4>
                        <div class="form-group">
                            <label class="fw-bold">First Name:</label>
                            <input type="text" name="agent_first_name" id="first_name" placeholder=""
                                value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                                data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Last Name:</label>
                            <input type="text" name="agent_last_name" id="last_name" placeholder=""
                                value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                                data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Phone Number:</label>
                            <input type="text" name="agent_phone" id="agent_phone" placeholder=""
                                value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                                data-icon="fa-solid fa-phone">
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
                                value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                                data-icon="fa-solid fa-handshake">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Real Estate License #:</label>
                            <input type="text" name="agent_license_no" id="agent_license_no" placeholder=""
                                value="{{ optional(Auth::user())->license_no }}" class="form-control has-icon"
                                data-icon="fa-solid fa-id-card">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">NRDS ID #:</label>
                            <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                                value="{{ optional(Auth::user())->mls_id }}">
                        </div>

                    </div>
                    {{-- Slide 40 --}}
                    {{-- Slide 41 --}}
                    <div class="wizard-step" data-step="41">
                        <div class="form-group ">
                            <label class="fw-bold">Property Photos</label>
                            <input type="file" name="property_photos" id="property_picture" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Property Video:</label>
                            <input type="file" name="property_video" id="property_video" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">3D Tour:</label>
                            <input type="url" name="three_d_tour" id="three_d_tour" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Floor Plan:</label>
                            <input type="file" name="floor_plan" id="floor_plan" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Lable Addendums/Disclosures:</label>
                            <input type="text" name="lable_disclosures" id="lable_visible_upload_file"
                                placeholder="" class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Addendums/Disclosures:</label>
                            <input type="file" name="disclosures[]" id="upload_file" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                        </div>

                    </div>
                    {{-- Slide 41 --}}
                    {{-- Slides Start for Commercial Property and Business Opportunity --}}
                    {{-- Slide 8 --}}
                    <div class="wizard-step" data-step="42">
                        @php
                            $prop_conditions = [['name' => 'Not Updated: Requires a complete update.', 'target' => ''], ['name' => 'Tear Down: Requires complete demolition and reconstruction.', 'target' => ''], ['name' => 'Semi-updated, needs minor updates: Partially updated, but requires additional minor updates.', 'target' => ''], ['name' => 'Move-in Ready, Completely Updated: Fully updated and ready for occupancy.', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Property condition:</label>
                            <select class="grid-picker" name="prop_condition" id="prop_condition"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($prop_conditions as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(50% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">List of Known Required Repairs</label>
                            <textarea name="known_repairs" id="known_repairs" class="form-control" cols="30" rows="5"></textarea>
                        </div>


                    </div>
                    {{-- Slide 8 --}}
                    {{-- Slide 9 --}}
                    <div class="wizard-step" data-step="43">
                        <div class="form-group">
                            <label class="fw-bold">Bathrooms:</label>
                            <select class="grid-picker" name="bathrooms" id="bathrooms" style="" required>
                                <option value="">Select</option>
                                @foreach ($bathrooms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-solid fa-bath"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_bathrooms d-none">
                            <label class="fw-bold">Bathrooms:</label>
                            <input type="text" name="custom_bathrooms" id="custom_bathrooms"
                                class="form-control has-icon" data-icon="fa-solid fa-bath" required>
                        </div>
                    </div>
                    {{-- Slide 9 --}}
                    {{-- Slide 10 --}}
                    <div class="wizard-step" data-step="44">
                        <div class="row ">
                            <div class="form-group">
                                <label class="fw-bold" for="heated_sqft">Heated Sqft:</label>
                                <input type="number" name="heated_sqft" placeholder="Heated sqft" id="heated_sqft"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                                    data-msg-required="Please enter Heated sqft" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="sqft">Total Sqft:</label>
                                <input type="number" name="total_sqft" placeholder="Heated sqft" id="heated_sqft"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                                    data-msg-required="Please enter Heated sqft" required>
                            </div>
                            @php
                                $heated_sources = [['name' => 'Appraisal', 'target' => ''], ['name' => 'Building', 'target' => ''], ['name' => 'Measured', 'target' => ''], ['name' => 'Owner Provided', 'target' => ''], ['name' => 'Public Records', 'target' => '']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Sqft Heated Source:</label>
                                <select class="grid-picker" name="heated_source" id="heated_sources"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($heated_sources as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'
                                            class="card flex-column" style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Total Acreage:</label>
                                <select class="grid-picker" name="total_aceage" id="lot_size"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($lot_sizes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'
                                            class="card flex-column" style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="lot_size">Lot size:</label>
                                <input type="number" name="lot_size" placeholder="Lot Size" id="lot_size"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                                    data-msg-required="Please enter Lot Size">
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="year_built">Year Built:</label>
                                <input type="number" name="year_built" placeholder="Year Built" id="year_built"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-calendar-day"
                                    data-msg-required="Please enter Year Built" required>
                            </div>
                        </div>
                    </div>
                    {{-- Slide 10 --}}
                    {{-- Slide 11 --}}
                    <div class="wizard-step" data-step="45">
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
                                ['name' => 'Other', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Appliances Included:</label>
                            <select class="grid-picker" name="appliances[]" id="appliances"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($appliances as $appliance)
                                    <option value="{{ $appliance['name'] }}"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>'
                                        data-target="{{ $appliance['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $appliance['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 11 --}}
                    {{-- Slide 12 --}}
                    <div class="wizard-step" data-step="46">
                        <div class="form-group ">
                            <label class="fw-bold">Are there any furnishings included in the purchase?</label>
                            <select class="grid-picker" name="has_furnishing" id="has_water_view"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.has_furnishings_commercial_and_business';
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
                        <div class="row has_furnishings_commercial_and_business d-none">

                            <div class="form-group">
                                <div class="form-group">
                                    <label class="fw-bold">What furnishings are included in the purchase?</label>
                                    <input type="text" name="furnishings_include" id="flood_zone_code"
                                        placeholder="" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                                        required>
                                </div>
                            </div>
                            @php
                                $additional_fees = [['name' => 'Additional Fees', 'target' => ''], ['name' => 'Included in Purchase Price', 'target' => '']];
                            @endphp
                            <div class="form-group">
                                <label class="fw-bold">Are there any additional fees for the listed furnishings, or are
                                    they
                                    included in the purchase price? </label>

                                <select class="grid-picker" name="has_additional_fees" id="has_water_view"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($additional_fees as $item)
                                        @php
                                            if ($item['name'] == 'Additional Fees') {
                                                $target = '.has_additional_fees_commercial_and_business';
                                            } else {
                                                $target = '';
                                            }
                                        @endphp
                                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group has_additional_fees_commercial_and_business">
                                <div class="form-group">
                                    <label class="fw-bold">How much is the listed furniture?</label>
                                    <input type="number" name="listed_furniture_price" id="listed_furniture_price"
                                        placeholder="" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                                        required>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Slide 12 --}}
                    {{-- Slide 13 --}}
                    <div class="wizard-step" data-step="47">
                        <h4>Business Listing Only</h4>
                        @php
                            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Real Estate Include:</label>
                            <select class="grid-picker" name="has_real_estate_include" id="bathrooms" style=""
                                required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Business Name:</label>
                            <input type="text" name="business_name" id="custom_bathrooms"
                                class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Year Established:</label>
                            <input type="text" name="year_established" id="year_established"
                                class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                        </div>
                        @php
                            $licenses = [['name' => 'Beer/Wine', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Liquor', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'None', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Off Site', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'On Site', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Licenses:</label>
                            <select class="grid-picker" name="licenses" id="bathrooms" style="" required>
                                <option value="">Select</option>
                                @foreach ($licenses as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 13 --}}
                    {{-- Slide 14 --}}
                    <div class="wizard-step" data-step="48">
                        <div class="form-group">
                            <label class="fw-bold">Number of Floors within the Property:</label>
                            <input type="text" name="number_of_buildings" id="number_of_buildings" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-hotel" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Floor Number:</label>
                            <input type="number" name="floors_in_unit" id="floors_in_unit" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-building" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Total Number of Floors in the Entire Building:</label>
                            <input type="number" name="total_floors" id="total_floors" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-building" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Building Elevator:</label>
                            <select class="grid-picker" name="building_elevator" id="building_elevator"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(25% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 14 --}}
                    {{-- Slide 15 --}}
                    <div class="wizard-step" data-step="49">
                        @php
                            $floor_coverings = [
                                ['name' => 'Brick/Stone', 'target' => ''],
                                ['name' => 'Carpet', 'target' => ''],
                                ['name' => 'Ceramic Tile', 'target' => ''],
                                ['name' => 'Concrete', 'target' => ''],
                                ['name' => 'Cork', 'target' => ''],
                                ['name' => 'Engineered Hardwood', 'target' => ''],
                                ['name' => 'Epoxy', 'target' => ''],
                                ['name' => 'Forestry Stewardship Certified', 'target' => ''],
                                ['name' => 'Granite', 'target' => ''],
                                ['name' => 'Laminate', 'target' => ''],
                                ['name' => 'Linoleum', 'target' => ''],
                                ['name' => 'Marble', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
                                ['name' => 'Parquet', 'target' => ''],
                                ['name' => 'Porcelain Tile', 'target' => ''],
                                ['name' => 'Quarry Tile', 'target' => ''],
                                ['name' => 'Reclaimed View', 'target' => ''],
                                ['name' => 'Recycled/Composite Flooring', 'target' => ''],
                                ['name' => 'Slate', 'target' => ''],
                                ['name' => 'Terrazzo', 'target' => ''],
                                ['name' => 'Tile', 'target' => ''],
                                ['name' => 'Travertine', 'target' => ''],
                                ['name' => 'Vinyl', 'target' => ''],
                                ['name' => 'Wood', 'target' => ''],
                                ['name' => 'Washer', 'target' => ''],
                                ['name' => 'Water Filtration System', 'target' => ''],
                                ['name' => 'Water Purifier', 'target' => ''],
                                ['name' => 'Water Softener', 'target' => ''],
                                ['name' => 'Whole House R.O. System', 'target' => ''],
                                ['name' => 'Wine Refrigerator', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Floor Covering:</label>
                            <select class="grid-picker" name="floor_covering[]" id="floor_covering"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($floor_coverings as $floor_covering)
                                    <option value="{{ $floor_covering['name'] }}"
                                        data-target="{{ $floor_covering['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $floor_covering['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 15 --}}
                    {{-- Slide 16 --}}
                    <div class="wizard-step" data-step="50">
                        @php
                            $front_exposures = [['name' => 'North', 'target' => ''], ['name' => 'East', 'target' => ''], ['name' => 'South', 'target' => ''], ['name' => 'West', 'target' => ''], ['name' => 'Southeast', 'target' => ''], ['name' => 'Northeast', 'target' => ''], ['name' => 'Southwest', 'target' => ''], ['name' => 'Northwest', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Front Exposure:</label>
                            <select class="grid-picker" name="front_exposure" id="front_exposure"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($front_exposures as $front_exposure)
                                    <option value="{{ $front_exposure['name'] }}"
                                        data-target="{{ $front_exposure['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $front_exposure['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 16 --}}
                    {{-- Slide 17 --}}
                    <div class="wizard-step" data-step="51">
                        @php
                            $foundations = [['name' => 'Basement', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick/Mortar', 'target' => ''], ['name' => 'Concrete Perimeter', 'target' => ''], ['name' => 'Crawlspace', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pillar/Post/Pier', 'target' => ''], ['name' => 'Slab', 'target' => ''], ['name' => 'Stem Wall', 'target' => ''], ['name' => 'Stilt/On Piling', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Foundation:</label>
                            <select class="grid-picker" name="foundation[]" id="foundation"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($foundations as $foundation)
                                    <option value="{{ $foundation['name'] }}"
                                        data-target="{{ $foundation['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $foundation['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 17 --}}
                    {{-- Slide 18 --}}
                    <div class="wizard-step" data-step="52">
                        @php
                            $exterior_constructions = [['name' => 'Asbestos', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Cedar', 'target' => ''], ['name' => 'Cement Siding', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'HardiPlank Type', 'target' => ''], ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''], ['name' => 'Log', 'target' => ''], ['name' => 'Metal Frame', 'target' => ''], ['name' => 'Metal Siding', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''], ['name' => 'Stone', 'target' => ''], ['name' => 'Stucco', 'target' => ''], ['name' => 'Tilt up Walls', 'target' => ''], ['name' => 'Vinyl Siding', 'target' => ''], ['name' => 'Wood Frame', 'target' => ''], ['name' => 'Wood Frame (FSC)', 'target' => ''], ['name' => 'Wood Siding ', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Exterior Construction:</label>
                            <select class="grid-picker" name="exterior_construction[]" id="exterior_construction"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($exterior_constructions as $exterior_construction)
                                    <option value="{{ $exterior_construction['name'] }}"
                                        data-target="{{ $exterior_construction['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $exterior_construction['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 18 --}}
                    {{-- Slide 19 --}}
                    <div class="wizard-step" data-step="53">
                        @php
                            $exterior_features = [
                                ['name' => 'Awning(s)', 'target' => ''],
                                ['name' => 'Balcony', 'target' => ''],
                                ['name' => 'Courtyard', 'target' => ''],
                                ['name' => 'Dog Run', 'target' => ''],
                                ['name' => 'French Doors', 'target' => ''],
                                ['name' => 'Garden', 'target' => ''],
                                ['name' => 'Gray Water System', 'target' => ''],
                                ['name' => 'Hurricane Shutters', 'target' => ''],
                                ['name' => 'Irrigation System', 'target' => ''],
                                ['name' => 'Lighting', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
                                ['name' => 'Outdoor Grill', 'target' => ''],
                                ['name' => 'Outdoor Kitchen', 'target' => ''],
                                ['name' => 'Outdoor Shower', 'target' => ''],
                                ['name' => 'Private Mailbox', 'target' => ''],
                                ['name' => 'Rain Barrel/Cistern(s)', 'target' => ''],
                                ['name' => 'Rain Gutters', 'target' => ''],
                                ['name' => 'Sauna', 'target' => ''],
                                ['name' => 'Shade Shutter(s)', 'target' => ''],
                                ['name' => 'Sidewalk', 'target' => ''],
                                ['name' => 'Sliding Doors', 'target' => ''],
                                ['name' => 'Sprinkler Metered', 'target' => ''],
                                ['name' => 'Storage', 'target' => ''],
                                ['name' => 'Tennis Court(s)', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Exterior Features:</label>
                            <select class="grid-picker" name="exterior_feature[]" id="exterior_feature"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($exterior_features as $exterior_feature)
                                    <option value="{{ $exterior_feature['name'] }}"
                                        data-target="{{ $exterior_feature['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $exterior_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 19 --}}
                    {{-- Slide 20 --}}
                    <div class="wizard-step" data-step="54">
                        @php
                            $lot_features = [
                                ['name' => 'Central Business District', 'target' => ''],
                                ['name' => 'Corner Lot', 'target' => ''],
                                ['name' => 'Cul-De-Sac', 'target' => ''],
                                ['name' => 'Curb and Gutters', 'target' => ''],
                                ['name' => 'Drainage Canal', 'target' => ''],
                                ['name' => 'Farm', 'target' => ''],
                                ['name' => 'Fire Hydrant', 'target' => ''],
                                ['name' => 'Flood Insurance Required', 'target' => ''],
                                ['name' => 'Flood Zone', 'target' => ''],
                                ['name' => 'Historic District', 'target' => ''],
                                ['name' => 'In City Limits', 'target' => ''],
                                ['name' => 'Industrial Condo', 'target' => ''],
                                ['name' => 'Industrial Park', 'target' => ''],
                                ['name' => 'Interior Lot', 'target' => ''],
                                ['name' => 'Landscaped', 'target' => ''],
                                ['name' => 'Near Golf Course', 'target' => ''],
                                ['name' => 'Near Public Transit', 'target' => ''],
                                ['name' => 'Near Railroad Siding', 'target' => ''],
                                ['name' => 'Neighborhood', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
                                ['name' => 'Out Parcel', 'target' => ''],
                                ['name' => 'Oversized Lot', 'target' => ''],
                                ['name' => 'Railroad', 'target' => ''],
                                ['name' => 'Retail Condo', 'target' => ''],
                                ['name' => 'Retention Areas', 'target' => ''],
                                ['name' => 'Retention Pond', 'target' => ''],
                                ['name' => 'Riparian Rights', 'target' => ''],
                                ['name' => 'Rolling Slope', 'target' => ''],
                                ['name' => 'Rural', 'target' => ''],
                                ['name' => 'Seaport', 'target' => ''],
                                ['name' => 'Shopping Center', 'target' => ''],
                                ['name' => 'Sidewalks', 'target' => ''],
                                ['name' => 'Sloped', 'target' => ''],
                                ['name' => 'Special Taxing District', 'target' => ''],
                                ['name' => 'Street Lights', 'target' => ''],
                                ['name' => 'Street Paved', 'target' => ''],
                                ['name' => 'Suburb', 'target' => ''],
                                ['name' => 'Turn Around', 'target' => ''],
                                ['name' => 'Undeveloped', 'target' => ''],
                                ['name' => 'Waterfront', 'target' => ''],
                                ['name' => 'Wooded', 'target' => ''],
                                ['name' => 'Zoned for Horse', 'target' => ''],
                            ];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">Lot Features:</label>
                            <select class="grid-picker" name="lot_features[]" id="lot_features"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($lot_features as $lot_feature)
                                    <option value="{{ $lot_feature['name'] }}"
                                        data-target="{{ $lot_feature['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $lot_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 20 --}}
                    {{-- Slide 21 --}}
                    <div class="wizard-step" data-step="55">
                        @php
                            $building_features = [
                                ['name' => 'Clear Span', 'target' => ''],
                                ['name' => 'Columns', 'target' => ''],
                                ['name' => 'Common Lighting', 'target' => ''],
                                ['name' => 'Drive-Through', 'target' => ''],
                                ['name' => 'Dumpsters', 'target' => ''],
                                ['name' => 'Elevator', 'target' => ''],
                                ['name' => 'Elevator – None', 'target' => ''],
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
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Building Features:</label>
                            <select class="grid-picker" name="building_features[]" id="building_feature"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($building_features as $building_feature)
                                    <option value="{{ $building_feature['name'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                                        data-target="{{ $building_feature['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $building_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 21 --}}
                    {{-- Slide 22 --}}
                    <div class="wizard-step" data-step="56">
                        @php
                            $adjoining_properties = [['name' => 'Airport', 'target' => ''], ['name' => 'Church', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Hotel/Motel', 'target' => ''], ['name' => 'Industrial', 'target' => ''], ['name' => 'Multi-Family', 'target' => ''], ['name' => 'Natural State', 'target' => ''], ['name' => 'Professional Office', 'target' => ''], ['name' => 'Railroad', 'target' => ''], ['name' => 'Residential', 'target' => ''], ['name' => 'School', 'target' => ''], ['name' => 'Undeveloped', 'target' => ''], ['name' => 'Vacant', 'target' => ''], ['name' => 'Waterway', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Adjoining Property:</label>
                            <select class="grid-picker" name="adjoining_property[]" id="roof"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($adjoining_properties as $adjoining_propertie)
                                    <option value="{{ $adjoining_propertie['name'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                                        data-target="{{ $adjoining_propertie['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $adjoining_propertie['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 22 --}}
                    {{-- Slide 23 --}}
                    <div class="wizard-step" data-step="57">
                        @php
                            $roofs = [['name' => 'Built-Up', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Membrane', 'target' => ''], ['name' => 'Metal', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Roof Over', 'target' => ''], ['name' => 'Shake', 'target' => ''], ['name' => 'Shingle', 'target' => ''], ['name' => 'Slate', 'target' => ''], ['name' => 'Tile', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Roof:</label>
                            <select class="grid-picker" name="roof" id="roof"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($roofs as $roof)
                                    <option value="{{ $roof['name'] }}" data-target="{{ $roof['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $roof['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 23 --}}
                    {{-- Slide 24 --}}
                    <div class="wizard-step" data-step="58">
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
                                        data-target="{{ $road_surface_type['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $road_surface_type['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 24 --}}
                    {{-- Slide 25 --}}
                    <div class="wizard-step" data-step="59">
                        @php
                            $road_frontage = [['name' => 'Access Road', 'target' => ''], ['name' => 'Alley', 'target' => ''], ['name' => 'Business District', 'target' => ''], ['name' => 'City Street', 'target' => ''], ['name' => 'County Road', 'target' => ''], ['name' => 'Divided Highway', 'target' => ''], ['name' => 'Easement', 'target' => ''], ['name' => 'Highway', 'target' => ''], ['name' => 'Interchange', 'target' => ''], ['name' => 'Interstate', 'target' => ''], ['name' => 'Main Thoroughfare', 'target' => ''], ['name' => 'Private Road', 'target' => ''], ['name' => 'Rail', 'target' => ''], ['name' => 'State Road', 'target' => ''], ['name' => 'Turn Lanes', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'None', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Road Frontage:</label>
                            <select class="grid-picker" name="road_frontage[]" id="road_frontage"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($road_frontage as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 25 --}}
                    {{-- Slide 26 --}}
                    <div class="wizard-step" data-step="60">
                        @php
                            $garage_parking_feature = [['name' => '1 to 5 Spaces', 'target' => ''], ['name' => '6 to 12 Spaces', 'target' => ''], ['name' => '13 to 18 Spaces', 'target' => ''], ['name' => '19 to 30 Spaces', 'target' => ''], ['name' => 'Airplane Hangar', 'target' => ''], ['name' => 'Common', 'target' => ''], ['name' => 'Curb Parking', 'target' => ''], ['name' => 'Deeded', 'target' => ''], ['name' => 'Electric Vehicle Charging Station(s)', 'target' => ''], ['name' => 'Ground Level', 'target' => ''], ['name' => 'Lighted', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Over 30 Spaces', 'target' => ''], ['name' => 'Secured', 'target' => ''], ['name' => 'Under Building', 'target' => ''], ['name' => 'Underground', 'target' => ''], ['name' => 'Valet', 'target' => '']];
                        @endphp

                        <div class="form-group ">
                            <label class="fw-bold">Garage/Parking Features:</label>
                            <select class="grid-picker" name="garage_parking_feature[]" id="garage_parking_feature"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($garage_parking_feature as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 26 --}}
                    {{-- Slide 27 --}}
                    <div class="wizard-step" data-step="61">
                        <div class="row ">
                            <div class="form-group">
                                <label class="fw-bold">Pets Allowed:</label>
                                <select class="grid-picker" name="has_rental_restrictions"
                                    id="has_rental_restrictions" style="justify-content: flex-start;">
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
                                                class="card flex-column" style="width:calc(10% - 10px);"
                                                data-icon='<i class="fa-solid fa-dog"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_pets_allowed d-none">
                                    <label class="fw-bold">Number of pets allowed:</label>
                                    <input type="text" name="custom_pets_allowed" id="custom_pets_allowed"
                                        class="form-control has-icon" data-icon="fa-solid fa-dog" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Max pet weight:</label>
                                    <input type="text" name="max_pet_weight" id="max_pet_weight"
                                        class="form-control has-icon" data-icon="fa-solid fa-dog">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Pet Restrictions:</label>
                                    <textarea name="pet_restrictions" id="pet_restrictions" class="form-control" cols="30" rows="5"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Slide 27 --}}
                    {{-- Slide 28 --}}
                    <div class="wizard-step" data-step="62">
                        <h4>Tax Info</h4>
                        <div class="form-group">
                            <label class="fw-bold">Tax ID (Parcel Number):</label>
                            <input type="number" name="tax_id" id="tax_id" class="form-control has-icon"
                                data-icon="fa-regular fa-id-card" placeholder="Tax ID (Parcel Number)" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Tax Year:</label>
                            <input type="text" name="tax_year" id="tax_year" class="form-control has-icon"
                                data-icon="fa-solid fa-qrcode" placeholder="Tax Year" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Taxes (Annual Amount):</label>
                            <input type="number" name="taxes_annual_amount" id="taxes_annual_ammount"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                placeholder="Taxes (Annual Amount)" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Total Number of Parcels:</label>
                            <input type="number" name="total_number_of_parcels" id="total_number_of_parcels"
                                class="form-control has-icon" placeholder="Total Number of Parcels"
                                data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Additional Tax ID's:</label>
                            <input type="number" name="additional_tax_id" id="additional_tax_id"
                                class="form-control has-icon" placeholder="Additional Tax ID's"
                                data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Zoning:</label>
                            <input type="text" name="zoning" id="zoning" placeholder="Zoning"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Legal Description:</label>
                            <input type="text" name="legal_description" id="legal_description"
                                class="form-control has-icon" placeholder="Legal Description"
                                data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group ">
                            <label class="fw-bold">Homestead:</label>
                            <select class="grid-picker" name="has_homestead" id="sewer"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 28 --}}
                    {{-- Slide 29 --}}
                    <div class="wizard-step" data-step="63">
                        <div class="form-group">
                            <label class="fw-bold">Is Property in a flood zone?</label>
                            <select class="grid-picker" name="is_in_flood_zone" id="is_in_flood_zone"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.has_flood_zoon_residential';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(25% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group has_flood_zoon_residential">
                            <label class="fw-bold">Flood Zone Code:</label>
                            <input type="text" name="flood_zone_code" id="flood_zone_code"
                                placeholder="Flood Zone Code" class="form-control has-icon"
                                data-icon="fa-solid fa-qrcode" required>
                        </div>
                    </div>
                    {{-- Slide 29 --}}
                    {{-- Slide 30 --}}
                    <div class="wizard-step" data-step="64">
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
                                ['name' => 'BB/HS Internet Capable', 'target' => ''],
                                ['name' => 'Water Available', 'target' => ''],
                                ['name' => 'Water Connected', 'target' => ''],
                                ['name' => 'Water Nearby', 'target' => ''],
                                ['name' => 'Sewer Nearby', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Utilities:</label>
                            <select class="grid-picker" name="utilities[]" id="utilities"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($utilities1 as $utilitie12)
                                    <option value="{{ $utilitie12['name'] }}"
                                        data-target="{{ $utilitie12['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
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
                            <select class="grid-picker" name="water" id="water12"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($waters as $water)
                                    <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
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
                            <select class="grid-picker" name="sewer" id="sewer"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($sewers1 as $sewer12)
                                    <option value="{{ $sewer12['name'] }}" data-target="{{ $sewer12['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $sewer12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 30 --}}
                    {{-- Slide 31 --}}
                    <div class="wizard-step" data-step="65">
                        @php
                            $air_conditioning = [['name' => 'Central Air', 'target' => ''], ['name' => 'Humidity Control', 'target' => ''], ['name' => 'Mini-Split Unit(s)', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Wall/Window Unit(s)', 'target' => ''], ['name' => 'Zoned', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Air Conditioning:</label>
                            <select class="grid-picker" name="air_conditioning" id="air_conditioning"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($air_conditioning as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $heating_and_fuel = [['name' => 'Baseboard', 'target' => ''], ['name' => 'Central', 'target' => ''], ['name' => 'Electric', 'target' => ''], ['name' => 'Exhaust Fans', 'target' => ''], ['name' => 'Heat Pump', 'target' => ''], ['name' => 'Heat Recovery Unit', 'target' => ''], ['name' => 'Natural Gas', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Oil', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Partial', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Heating and Fuel:</label>
                            <select class="grid-picker" name="heating_and_fuel" id="heating_and_fuel"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($heating_and_fuel as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 31 --}}
                    {{-- Slide 32 --}}
                    <div class="wizard-step" data-step="66">
                        <div class="form-group ">
                            <label class="fw-bold">Water View:</label>
                            <select class="grid-picker" name="has_water_view" id="has_water_view"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_view_commercial_and_business';
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
                            $water_views = [['name' => 'Bay/Harbor - Full', 'target' => ''], ['name' => 'Bay/Harbor - Partial', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Canal', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Gulf/Ocean - Full', 'target' => ''], ['name' => 'Gulf/Ocean - Partial', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'None', 'target' => '']];
                        @endphp
                        <div class="form-group water_view_commercial_and_business d-none">
                            <select class="grid-picker" name="water_view[]" id="water_view"
                                style="justify-content: flex-start;" multiple required>
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
                        <div class="form-group">
                            <label class="fw-bold">Water Extras:</label>
                            <select class="grid-picker" name="has_water_extra" id="has_water_extra"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_extras_commercial_and_business';
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
                        <div class="form-group water_extras_commercial_and_business d-none ">
                            <select class="grid-picker" name="water_extras[]" id="water_extras"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($water_extras as $water_extra)
                                    <option value="{{ $water_extra['name'] }}"
                                        data-target="{{ $water_extra['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_extra['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Water Frontage:</label>
                            <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_frontage_commercial_and_business';
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
                            // $water_frontage = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];

                            $water_frontage = [['name' => 'City', 'target' => ''], ['name' => 'Garden', 'target' => ''], ['name' => 'Golf Course', 'target' => ''], ['name' => 'Greenbelt', 'target' => ''], ['name' => 'Mountain(s)', 'target' => ''], ['name' => 'Park', 'target' => ''], ['name' => 'Pool', 'target' => ''], ['name' => 'Tennis Court', 'target' => ''], ['name' => 'Trees/Woods', 'target' => ''], ['name' => 'Water', 'target' => '']];

                        @endphp
                        <div class="form-group water_frontage_commercial_and_business d-none">
                            <select class="grid-picker" name="water_frontage[]" id="water_frontage"
                                style="justify-content: flex-start;" multiple required>
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
                            <label class="fw-bold">Water Access:</label>
                            <select class="grid-picker" name="has_water_access" id="has_water_access"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_access_commercial_and_business';
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
                            $water_access = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
                        @endphp
                        <div class="form-group water_access_commercial_and_business">
                            <select class="grid-picker" name="water_access[]" id="water_access"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($water_access as $water_access1)
                                    <option value="{{ $water_access1['name'] }}"
                                        data-target="{{ $water_access1['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_access1['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 32 --}}
                    {{-- Slide 33 --}}
                    <div class="wizard-step" data-step="67">
                        @php
                            $ownerships = [['name' => 'Co-Op', 'target' => ''], ['name' => 'Condominium', 'target' => ''], ['name' => 'Corporation', 'target' => ''], ['name' => 'Franchise', 'target' => ''], ['name' => 'Partnership', 'target' => ''], ['name' => 'Sole', 'target' => ''], ['name' => 'Proprietor', 'target' => ''], ['name' => 'Other', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Ownership and Co-Op:</label>
                            <select class="grid-picker" name="ownership_co_op" id="ownership"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($ownerships as $item)
                                    @php
                                        if ($item['name'] == 'Other') {
                                            $target = '.custom_ownership_commercial_and_business';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_ownership_commercial_and_business">
                            <label class="fw-bold">Other:</label>
                            <input type="text" name="custom_ownership" id="custom_ownership"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode" placeholder="Other">
                        </div>
                    </div>
                    {{-- Slide 33 --}}
                    {{-- Slide 34 --}}
                    <div class="wizard-step" data-step="68">
                        @php
                            $occupant_types = [['name' => 'Owner', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tenant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Occupant Type:</label>
                            <select class="grid-picker" name="occupant_type" id="occupant_type"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($occupant_types as $item)
                                    @php
                                        if ($item['name'] == 'Tenant') {
                                            $target = '.tenant_conditions';
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
                        <div class="row tenant_conditions">
                            <div class="row for_residential_only">
                                <div class="form-group">
                                    <label class="fw-bold">Existing Lease or Tenant:</label>
                                    <select class="grid-picker" name="exiting_lease_or_tenant"
                                        id="exiting_lease_or_tenant" style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Monthly Rental Amount:</label>
                                    <input type="number" name="monthly_rental_ammount" id="monthly_rental_ammount"
                                        placeholder="" class="form-control has-icon" data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Days Notice to Tenant if not Renewing:</label>
                                    <input type="text" name="days_notice_to_terminate"
                                        id="days_notice_to_terminate" placeholder="" class="form-control has-icon"
                                        data-icon="fa-solid fa-qrcode">
                                </div>
                                <div class="form-group">
                                    <label for="address">End Date of Lease:</label>
                                    <input type="date" name="end_of_lease_date" id="listing_date"
                                        class="form-control has-icon search_places"
                                        data-icon="fa-regular fa-calendar-days"
                                        data-msg-required="Please enter End Date of Lease Date" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Slide 34 --}}
                    {{-- Slide 35 --}}
                    <div class="wizard-step" data-step="69">
                        <h4>Financial Information:</h4>
                        @php
                            $acutual_or_projected = [['name' => 'Actual', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Projected', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Operating Expenses:</label>
                            <input type="number" name="operating_expenses" id="operating_expenses"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar "
                                placeholder="Operating Expenses">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Net Operating Income:</label>
                            <input type="number" name="net_operating_income" id="net_operating_income"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                placeholder="Net Operating Income">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Net Operating Income Type:</label>
                            <select class="grid-picker" name="net_operating_income_type"
                                id="net_operating_income_type" style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($acutual_or_projected as $item)
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
                        <div class="form-group">
                            <label class="fw-bold">Annual Expenses:</label>
                            <input type="number" name="annual_expenses" id="annual_expenses"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                placeholder="Annual Expenses">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Annual TTL Schedule Income:</label>
                            <input type="number" name="annual_ttl_schedule_income" id="annual_ttl_schedule_income"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                placeholder="Annual TTL Schedule Income">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Annual Income Type:</label>
                            <select class="grid-picker" name="annual_income_type" id="annual_income_type"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($acutual_or_projected as $item)
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


                        @php
                            $sale_includes = [['name' => 'Building(s) and Land', 'target' => ''], ['name' => 'Furniture/Fixtures', 'target' => ''], ['name' => 'Leases', 'target' => ''], ['name' => 'Other', 'target' => '']];
                        @endphp
                        <div class="row ">
                            <div class="form-group">
                                <label class="fw-bold">Sale Includes:</label>
                                <select class="grid-picker" name="sale_include" id="sale_include"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($sale_includes as $sale_include)
                                        <option value="{{ $sale_include['name'] }}"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'
                                            data-target="{{ $sale_include['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $sale_include['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Number of Tenants:</label>
                            <input type="number" name="number_of_tenants" id="number_of_tenants"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                    </div>
                    {{-- Slide 35 --}}
                    {{-- Slide 36 --}}
                    <div class="wizard-step" data-step="70">
                        <h4>Space:</h4>
                        <div class="form-group">
                            <label class="fw-bold">Class of Space:</label>
                            <input type="text" name="class_of_space" id="operating_expenses"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Space Type:</label>
                            <input type="text" name="space_type" id="space_type" class="form-control has-icon"
                                data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold"># of Hotel/Motel Rms:</label>
                            <input type="number" name="number_of_hotel" id="number_of_hotel"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold"># of Conference/Meeting Rooms:</label>
                            <input type="number" name="number_of_conference" id="annual_expenses"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold"># of Restrooms:</label>
                            <input type="number" name="number_of_restrooms" id="annual_ttl_schedule_income"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold"># of Bays(Dock High) :</label>
                            <input type="number" name="number_of_bays_high" id="number_of_tenants"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold"># of Bays(Grade Level):</label>
                            <input type="number" name="number_of_bays_level" id="number_of_tenants"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold"># of Offices:</label>
                            <input type="number" name="number_of_offices" id="number_of_tenants"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                    </div>
                    {{-- Slide 36 --}}
                    {{-- Slide 37 --}}
                    <div class="wizard-step" data-step="71">
                        <div class="form-group">
                            <label class="fw-bold">Is Property in a Condo Environment? </label>
                            <select class="grid-picker" name="has_condo_enviornment" id="has_condo_enviornment"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.has_condo';
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
                        <div class="row has_condo d-none">
                            <div class="form-group ">
                                <label class="fw-bold">Condo Fee:$</label>
                                <input type="number" name="condo_fee" id="condo_fee" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar " placeholder="HOA Fee">
                            </div>
                            @php
                                $condo_fee_terms = [['target' => '', 'name' => 'Annual', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Monthly', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Quarterly', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Semi Annual ', 'icon' => 'fa-regular fa-circle-check']];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Condo Fee Term:</label>
                                <select class="grid-picker" name="condo_fee_terms" id="parking_feature_garage"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($condo_fee_terms as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group ">
                                <label class="fw-bold">Association/Manager Contact Name and Contact Information:</label>
                                <input type="text" name="association_manager_contact_name" id="condo_fee"
                                    class="form-control has-icon" data-icon="fa-solid fa-user">
                            </div>
                            @php
                                $community_features = [['name' => 'Activity Core/Center', 'target' => ''], ['name' => 'Airport/Runway', 'target' => ''], ['name' => 'Beach Area', 'target' => ''], ['name' => 'Curbs', 'target' => ''], ['name' => 'Expressway', 'target' => ''], ['name' => 'Sidewalk', 'target' => ''], ['name' => 'Stream Seasonal', 'target' => '']];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Community Features:</label>
                                <select class="grid-picker" name="community_features[]" id="parking_feature_garage"
                                    style="justify-content: flex-start;" required multiple>
                                    <option value="">Select</option>
                                    @foreach ($community_features as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    {{-- Slide 37 --}}
                    {{-- Slide 38 --}}
                    <div class="wizard-step" data-step="72">
                        <div class="form-group">
                            <label class="fw-bold"> Description:</label>
                            <textarea name="description" id="description" class="form-control" cols="30" rows="10" required></textarea>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Keywords:<small class="small">(Add keywords of the
                                    best property features) </small></label>
                            <input type="text" name="keywords" id="keywords" class="form-control has-icon"
                                data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Legal Disclamers:</label>
                            <input type="text" name="driving_directions" id="keywords"
                                class="form-control has-icon" data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Driving Directions:</label>
                            <input type="text" name="driving_directions" id="keywords"
                                class="form-control has-icon" data-icon="fa-solid fa-tag">
                        </div>
                        @php
                            $compensation = [['name' => 'Single Agent Compensation', 'target' => '.compensation'], ['name' => 'Non Rep Compensation', 'target' => '.compensation'], ['name' => 'Transaction Broker Compensation', 'target' => '.compensation']];

                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Compensation:</label>
                            <select class="grid-picker" name="compensation" id="fee_include"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($compensation as $item)
                                    @php
                                        if ($item['name'] == 'Single Agent Compensation') {
                                            $target = '.single_agent_compensation_amount_commercial_and_business_opportunity';
                                        } elseif ($item['name'] == 'Non Rep Compensation') {
                                            $target = '.non_rep_compensation_amount_commercial_and_business_opportunity';
                                        } elseif ($item['name'] == 'Transaction Broker Compensation') {
                                            $target = '.transaction_amount_commercial_and_business_opportunity';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group single_agent_compensation_amount_commercial_and_business_opportunity">
                            <label class="fw-bold">Single Agent Compensation Amount:</label>
                            <input type="number" name="compensation_amount" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar">
                        </div>
                        <div class="form-group non_rep_compensation_amount_commercial_and_business_opportunity ">
                            <label class="fw-bold"> Non Rep Compensation Amount:</label>
                            <input type="number" name="compensation_amount" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar">
                        </div>
                        <div class="form-group transaction_amount_commercial_and_business_opportunity">
                            <label class="fw-bold">Transaction Broker Compensation Amount:</label>
                            <input type="number" name="compensation_amount" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar">
                        </div>
                    </div>
                    {{-- Slide 38 --}}
                    {{-- Slide 39 --}}
                    <div class="wizard-step" data-step="73">
                        <div class="form-group">
                            <label class="fw-bold">Is the Seller actively seeking to purchase another property?
                            </label>
                            <select class="grid-picker" name="looking_other_property" id="looking_other_property"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.link_commercial';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(50% - 10px);"
                                        data-icon="<i class='{{ $item['icon'] }}'></i>">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group link_commercial">
                            <label class="fw-bold">Link to the listing on the Bid Your Offer platform:</label>
                            <input type="url" name="listing_link" id="listing_link" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>
                    </div>
                    {{-- Slide 39 --}}
                    {{-- Slide 40 --}}
                    <div class="wizard-step" data-step="74">
                        <h4> Title Company Information:</h4>
                        <div class="form-group">
                            <label class="fw-bold">Name:</label>
                            <input type="text" name="title_company_name" id="title_company_name" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Address:</label>
                            <input type="text" name="title_company_address" id="title_company_address"
                                placeholder="" class="form-control has-icon" data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Phone Number:</label>
                            <input type="text" name="title_company_phone" id="title_company_phone" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-phone" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Email:</label>
                            <input type="text" name="title_company_email" id="titl_company_email" placeholder=""
                                value="{{ @$auction->get->title_company_email }}" data-icon="fa-solid fa-envelope"
                                class="form-control has-icon" required>
                        </div>
                    </div>
                    {{-- Slide 40 --}}
                    {{-- Slide 41 --}}
                    <div class="wizard-step" data-step="75">
                        <h4>Agent info:</h4>
                        <div class="form-group">
                            <label class="fw-bold">First Name:</label>
                            <input type="text" name="first_name" id="first_name" placeholder=""
                                value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                                data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Last Name:</label>
                            <input type="text" name="last_name" id="last_name" placeholder=""
                                value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                                data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Phone Number:</label>
                            <input type="text" name="agent_phone" id="agent_phone" placeholder=""
                                value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                                data-icon="fa-solid fa-phone">
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
                                value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                                data-icon="fa-solid fa-handshake">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Real Estate License #:</label>
                            <input type="text" name="agent_license_no" id="agent_license_no" placeholder=""
                                value="{{ optional(Auth::user())->license_no }}" class="form-control has-icon"
                                data-icon="fa-solid fa-id-card">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">NRDS ID #:</label>
                            <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                                value="{{ optional(Auth::user())->mls_id }}">
                        </div>
                    </div>
                    {{-- Slide 41 --}}
                    {{-- Slide 42 --}}
                    <div class="wizard-step" data-step="76">
                        <div class="form-group ">
                            <label class="fw-bold">Property Photos:</label>
                            <input type="file" name="visible_photos" id="property_picture" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Property Video:</label>
                            <input type="file" name="visible_property_video" id="property_video" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">3D Tour:</label>
                            <input type="url" name="three_d_tour" id="three_d_tour" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Floor Plan:</label>
                            <input type="file" name="visible_note" id="visible_note" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Lable Addendums/Disclosures:</label>
                            <input type="text" name="lable_visible_upload_file" id="lable_visible_upload_file"
                                placeholder="" class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Addendums/Disclosures:</label>
                            <input type="file" name="visible_upload_file[]" id="upload_file" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                        </div>

                    </div>
                    {{-- Slide 42 --}}
                    {{-- Slides End for Commercial Property and Business Opportunity --}}
                    {{-- Slides Start For Vacant Land  --}}
                    {{-- Slide 8 --}}
                    <div class="wizard-step" data-step="77">
                        @php
                            $front_exposures1 = [['name' => 'North', 'target' => ''], ['name' => 'East', 'target' => ''], ['name' => 'South', 'target' => ''], ['name' => 'West', 'target' => ''], ['name' => 'Southeast', 'target' => ''], ['name' => 'Northeast', 'target' => ''], ['name' => 'Southwest', 'target' => ''], ['name' => 'Northwest', 'target' => '']];
                        @endphp
                        <div class="form-group  ">
                            <label class="fw-bold">Front Exposure:</label>
                            <select class="grid-picker" name="front_exposure"
                                onchange="changeFrontExposure(this.value);" id="front_exposure"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($front_exposures1 as $front_exposure12)
                                    <option value="{{ $front_exposure12['name'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                                        data-target="{{ $front_exposure12['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $front_exposure12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 8 --}}
                    {{-- Slide 9 --}}
                    <div class="wizard-step" data-step="78">
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
                                ['name' => 'Filled', 'target' => ''],
                                ['name' => 'Fire Hydrant', 'target' => ''],
                                ['name' => 'Fish Breeding Ponds', 'target' => ''],
                                ['name' => 'Flag Lot', 'target' => ''],
                                ['name' => 'Flood Plain', 'target' => ''],
                                ['name' => 'Greenbelt', 'target' => ''],
                                ['name' => 'Hilly', 'target' => ''],
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
                                ['name' => 'Other', 'target' => ''],
                                ['name' => 'Out Parcel', 'target' => ''],
                                ['name' => 'Oversized Lot', 'target' => ''],
                                ['name' => 'Pasture/Agriculture', 'target' => ''],
                                ['name' => 'Private', 'target' => ''],
                                ['name' => 'Railroad', 'target' => ''],
                                ['name' => 'Reclaimed Land', 'target' => ''],
                                ['name' => 'Retention Areas', 'target' => ''],
                                ['name' => 'Retention Pond', 'target' => ''],
                                ['name' => 'Rolling Slope', 'target' => ''],
                                ['name' => 'Room For Pool', 'target' => ''],
                                ['name' => 'Rural', 'target' => ''],
                                ['name' => 'Seaport', 'target' => ''],
                                ['name' => 'Sidewalks', 'target' => ''],
                                ['name' => 'Sloped', 'target' => ''],
                                ['name' => 'Special Taxing District', 'target' => ''],
                                ['name' => 'Stocked Fishing Ponds', 'target' => ''],
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
                            <select class="grid-picker" name="lot_features[]" id="lot_features"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($lot_features as $lot_feature)
                                    <option value="{{ $lot_feature['name'] }}"
                                        data-target="{{ $lot_feature['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $lot_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $current_adjacent_use = [['name' => 'Church', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Industrial', 'target' => ''], ['name' => 'Mobile Home Park', 'target' => ''], ['name' => 'Multi-Family', 'target' => ''], ['name' => 'Park', 'target' => ''], ['name' => 'Professional Office', 'target' => ''], ['name' => 'Residential', 'target' => ''], ['name' => 'Retail', 'target' => ''], ['name' => 'School', 'target' => ''], ['name' => 'Vacant', 'target' => '']];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">Current Adjacent Use:</label>
                            <select class="grid-picker" name="current_adjacent_use[]" id="lot_features"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($current_adjacent_use as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 9 --}}
                    {{-- Slide 10 --}}
                    <div class="wizard-step" data-step="79">
                        @php
                            $road_frontages = [['name' => 'Access Road', 'target' => ''], ['name' => 'Alley', 'target' => ''], ['name' => 'Business District', 'target' => ''], ['name' => 'City Street', 'target' => ''], ['name' => 'County Road', 'target' => ''], ['name' => 'Divided Highway', 'target' => ''], ['name' => 'Easement', 'target' => ''], ['name' => 'Highway', 'target' => ''], ['name' => 'Interchange', 'target' => ''], ['name' => 'Interstate', 'target' => ''], ['name' => 'Main Thoroughfare', 'target' => ''], ['name' => 'Private Road', 'target' => ''], ['name' => 'Rail', 'target' => ''], ['name' => 'State Road', 'target' => ''], ['name' => 'Turn Lanes', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'None', 'target' => '']];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">Road Frontage:</label>
                            <select class="grid-picker" onclick="changeRoadFrontage(this.value);" name="road_frontage"
                                id="road_frontage" style="justify-content: flex-start;" onclick="" required>
                                <option value="">Select</option>
                                @foreach ($road_frontages as $road_frontage)
                                    <option value="{{ $road_frontage['name'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                                        data-target="{{ $road_frontage['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $road_frontage['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 10 --}}
                    {{-- Slide 11 --}}
                    <div class="wizard-step" data-step="80">
                        @php
                            $road_surface_types = [['name' => 'Asphalt', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Chip And Seal', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Dirt', 'target' => ''], ['name' => 'Gravel', 'target' => ''], ['name' => 'Limerock', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Paved', 'target' => ''], ['name' => 'Unimproved', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Road Surface Type:</label>
                            <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($road_surface_types as $road_surface_type)
                                    <option value="{{ $road_surface_type['name'] }}"
                                        data-target="{{ $road_surface_type['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $road_surface_type['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 11 --}}
                    {{-- Slide 12 --}}
                    <div class="wizard-step" data-step="81">
                        @php
                            $utilities1 = [
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
                                ['name' => 'Other', 'target' => ''],
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
                            ];
                        @endphp


                        <div class="form-group ">
                            <label class="fw-bold">Utilities:</label>
                            <select class="grid-picker" name="utilities[]" id="utilities"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($utilities1 as $utilitie12)
                                    <option value="{{ $utilitie12['name'] }}"
                                        data-target="{{ $utilitie12['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $utilitie12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $waters = [['name' => 'Canal/Lake For Irrigation', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Private', 'target' => ''], ['name' => 'Public', 'target' => ''], ['name' => 'Well', 'target' => ''], ['name' => 'Well Required', 'target' => '']];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">Water:</label>
                            <select class="grid-picker" name="water" id="water12"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($waters as $water)
                                    <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @php
                            $sewers1 = [['name' => 'None', 'target' => ''], ['name' => 'PEP-Holding Tank', 'target' => ''], ['name' => 'Private Sewer', 'target' => ''], ['name' => 'Public Sewer', 'target' => ''], ['name' => 'Septic Needed', 'target' => ''], ['name' => 'Septic Tank', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Sewer:</label>
                            <select class="grid-picker" name="sewer" id="sewer"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($sewers1 as $sewer12)
                                    <option value="{{ $sewer12['name'] }}" data-target="{{ $sewer12['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $sewer12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 12 --}}
                    {{-- Slide 13 --}}
                    <div class="wizard-step" data-step="82">
                        <h4>Tax Info</h4>
                        <div class="form-group">
                            <label class="fw-bold">Tax ID (Parcel Number):</label>
                            <input type="number" name="tax_id" id="tax_id" class="form-control has-icon"
                                data-icon="fa-regular fa-id-card" placeholder="Tax ID (Parcel Number)" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Tax Year:</label>
                            <input type="text" name="tax_year" id="tax_year" class="form-control has-icon"
                                data-icon="fa-solid fa-qrcode" placeholder="Tax Year" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Taxes (Annual Amount):</label>
                            <input type="number" name="taxes_annual_amount" id="taxes_annual_ammount"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                placeholder="Taxes (Annual Amount)" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Total Number of Parcels:</label>
                            <input type="number" name="total_number_of_parcels" id="total_number_of_parcels"
                                class="form-control has-icon" placeholder="Total Number of Parcels"
                                data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Additional Tax ID's:</label>
                            <input type="number" name="additional_tax_id" id="additional_tax_id"
                                class="form-control has-icon" placeholder="Additional Tax ID's"
                                data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Zoning:</label>
                            <input type="text" name="zoning" id="zoning" placeholder="Zoning"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Legal Description:</label>
                            <input type="text" name="legal_description" id="legal_description"
                                class="form-control has-icon" placeholder="Legal Description"
                                data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group ">
                            <label class="fw-bold">Homestead:</label>
                            <select class="grid-picker" name="has_homestead" id="sewer"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- Slide 13 --}}
                    {{-- Slide 14 --}}
                    <div class="wizard-step" data-step="83">
                        <div class="form-group">
                            <label class="fw-bold">Is Property in a flood zone?</label>
                            <select class="grid-picker" name="is_in_flood_zone" id="is_in_flood_zone"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.has_flood_zoon12';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(25% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group has_flood_zoon12">
                            <label class="fw-bold">Flood Zone Code:</label>
                            <input type="text" name="flood_zone_code" id="flood_zone_code"
                                placeholder="Flood Zone Code" class="form-control has-icon"
                                data-icon="fa-solid fa-qrcode">
                        </div>
                    </div>
                    {{-- Slide 14 --}}
                    {{-- Slide 15 --}}
                    <div class="wizard-step" data-step="84">
                        @php
                            $total_acreages = [['name' => '0 to less than 14', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ acres', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Total Acreage :</label>
                            <select class="grid-picker" name="total_acreage" id="total_acreage"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($total_acreages as $total_acreage)
                                    <option value="{{ $total_acreage['name'] }}"
                                        data-target="{{ $total_acreage['target'] }}" class="card flex-column"
                                        style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $total_acreage['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Lot Dimensions:</label>
                            <input type="number" name="lot_dimensions" id="lot_dimensions"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Lot Size Square Footage:</label>
                            <input type="number" name="lot_size_square_footage" id="lot_size_square_footage"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Front Footage:</label>
                            <input type="number" name="front_footage" id="front_footage1"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode" step="0.01">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Lot Size Acres:</label>
                            <input type="number" name="lot_size_acres" id="lot_size_acres"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                    </div>
                    {{-- Slide 15 --}}
                    {{-- Slide 16 --}}
                    <div class="wizard-step" data-step="85">
                        <div class="form-group">
                            <label class="fw-bold">Water Access:</label>
                            <select class="grid-picker" name="has_water_access" id="has_water_access"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_access';
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
                            $water_access = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
                        @endphp
                        <div class="form-group water_access">
                            <label class="fw-bold">Water Access:</label>
                            <select class="grid-picker" name="water_access[]" id="water_access"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($water_access as $water_access1)
                                    <option value="{{ $water_access1['name'] }}"
                                        data-target="{{ $water_access1['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_access1['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
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
                                    <option value="{{ $water_view['name'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        data-target="{{ $water_view['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_view['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
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
                                        data-target="{{ $water_extra['target'] }}" class="card flex-row"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_extra['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Water Frontage:</label>
                            <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.water_frontage';
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
                            $water_frontage = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
                        @endphp
                        <div class="form-group water_frontage d-none">
                            <label class="fw-bold">Water Frontage:</label>
                            <select class="grid-picker" name="water_frontage[]" id="water_frontage"
                                style="justify-content: flex-start;" multiple required>
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
                        @php
                            $view = [['name' => 'City', 'target' => ''], ['name' => 'Garden', 'target' => ''], ['name' => 'Golf Course', 'target' => ''], ['name' => 'Greenbelt', 'target' => ''], ['name' => 'Mountain(s)', 'target' => ''], ['name' => 'Park', 'target' => ''], ['name' => 'Pool', 'target' => ''], ['name' => 'Tennis Court', 'target' => ''], ['name' => 'Trees/Woods', 'target' => ''], ['name' => 'Water', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">View:</label>
                            <select class="grid-picker" name="view[]" id="view"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($view as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    {{-- Slide 16 --}}
                    {{-- Slide 17 --}}
                    <div class="wizard-step" data-step="86">
                        @php
                            $ownerships = [['name' => 'Co-op', 'target' => ''], ['name' => 'Condominium', 'target' => ''], ['name' => 'Fee Simple', 'target' => ''], ['name' => 'Fractional', 'target' => ''], ['name' => 'Other', 'target' => '']];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">Ownership:</label>
                            <select class="grid-picker" name="ownership_co_op" id="ownership"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($ownerships as $item)
                                    @php
                                        if ($item['name'] == 'Other') {
                                            $target = '.other';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group other">
                            <label class="fw-bold">Other:</label>
                            <input type="text" name="custom_ownership" id="custom_ownership"
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode" placeholder="Other">
                        </div>
                    </div>
                    {{-- Slide 17 --}}
                    {{-- Slide 18 --}}
                    <div class="wizard-step" data-step="87">
                        <div class="form-group">
                            <label class="fw-bold">Does the property have an HOA, condo association, master
                                association,
                                and/or community fee? </label>
                            <select class="grid-picker" name="has_hoa" id="has_hoa"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.hoas';
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
                            $hoa_fee_requirenments = [['name' => 'None', 'target' => ''], ['name' => 'Optional', 'target' => ''], ['name' => ' Required', 'target' => '']];
                        @endphp
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">HOA Fee Requirement:</label>
                            <select class="grid-picker" name="hoa_fee_requirenment" id="hoa"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($hoa_fee_requirenments as $hoa_fee_requirenment)
                                    <option value="{{ $hoa_fee_requirenment['name'] }}"
                                        data-target="{{ $hoa_fee_requirenment['target'] }}" class="card flex-row"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                                        style="width:calc(33.3% - 10px);">
                                        {{ $hoa_fee_requirenment['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">HOA Fee:$</label>
                            <input type="number" name="hoa_fee" id="hoa_fee" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar " placeholder="HOA Fee">
                        </div>
                        @php
                            $hoa_payment_schedules = [['name' => 'Annually', 'target' => ''], ['name' => 'Monthly', 'target' => ''], ['name' => 'Quarterly', 'target' => ''], ['name' => 'Semi-Annually ', 'target' => '']];
                        @endphp
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">HOA Payment Schedule:</label>
                            <select class="grid-picker" name="hoa_payment_schedule" id="hoa"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($hoa_payment_schedules as $hoa_payment_schedule)
                                    <option value="{{ $hoa_payment_schedule['name'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                                        data-target="{{ $hoa_payment_schedule['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $hoa_payment_schedule['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Condo Fee: $</label>
                            <input type="number" name="condo_fee" id="condo_fee" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar " placeholder="Condo Fee">
                        </div>
                        @php
                            $condo_fee_schedules = [['name' => 'Annually', 'target' => ''], ['name' => 'Monthly', 'target' => ''], ['name' => 'Quarterly', 'target' => ''], ['name' => 'Semi-Annually ', 'target' => '']];
                        @endphp
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Condo Fee Schedule:</label>
                            <select class="grid-picker" name="condo_fee_schedule" id="hoa"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($condo_fee_schedules as $condo_fee_schedule)
                                    <option value="{{ $condo_fee_schedule['name'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                                        data-target="{{ $condo_fee_schedule['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $condo_fee_schedule['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>


                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Other Fees: $</label>
                            <input type="number" name="custom_condo_fee" id="other_fee"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar " placeholder="Other Fee">
                        </div>


                        @php

                            $condo_fee_schedules = [['name' => 'Annually', 'target' => ''], ['name' => 'Monthly', 'target' => ''], ['name' => 'Quarterly', 'target' => ''], ['name' => 'Semi-Annually ', 'target' => '']];
                        @endphp
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Condo Fee Schedule:</label>
                            <select class="grid-picker" name="condo_fee_schedule" id="other_fee_schedule"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($condo_fee_schedules as $condo_fee_schedule)
                                    <option value="{{ $condo_fee_schedule['name'] }}"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                                        data-target="{{ $condo_fee_schedule['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $condo_fee_schedule['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>


                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Additional Monthly Maintenance Fee: $</label>
                            <input type="number" name="additional_monthly_maintenance_fee"
                                id="additional_monthly_maintenance_fee" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar " placeholder="Maintenance Fee">
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Association Name/Contact Info: </label>
                            <input type="text" name="association_name" id="assicuatuib_name"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar "
                                placeholder="Association Name">
                        </div>

                        <div class="form-group hoas">
                            <label class="fw-bold">Master Association </label>
                            <select class="grid-picker" name="has_master_association" id="has_master_association"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.master_association';
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

                        <div class="form-group master_association d-none">
                            <label class="fw-bold">Master Association Fee: </label>
                            <input type="number" name="master_association_fee" id="master_association_fee"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar "
                                placeholder="Master Association Fee">
                        </div>
                        <div class="form-group master_association d-none">
                            <label class="fw-bold">Master Association Frequency Schedule: </label>
                            <select class="grid-picker" name="master_association_schedule"
                                id="master_association_schedule" style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($condo_fee_schedules as $condo_fee_schedule)
                                    <option value="{{ $condo_fee_schedule['name'] }}"
                                        data-target="{{ $condo_fee_schedule['target'] }}"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $condo_fee_schedule['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group master_association d-none">
                            <label class="fw-bold">Master Association Name/Contact Info:</label>
                            <input type="text" name="master_association_name" id="master_association_name"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar "
                                placeholder="Master Association Name/Contact Info">
                        </div>
                        <div class="form-group hoas">
                            <label class="fw-bold">Housing For Older Persons:</label>
                            <select class="grid-picker" name="housing_for_older_persons"
                                id="housing_for_older_persons" style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row hoas">
                            @php
                                $community_features = [
                                    ['name' => 'Airport/Runway', 'target' => ''],
                                    ['name' => 'Association Recreation - Lease', 'target' => ''],
                                    ['name' => ' Association Recreation - Owned', 'target' => ''],
                                    ['name' => 'Buyer Approval Required', 'target' => ''],
                                    ['name' => 'Clubhouse', 'target' => ''],
                                    ['name' => 'Community Boat Ramp', 'target' => ''],
                                    ['name' => 'Community Mailbox', 'target' => ''],
                                    ['name' => 'Deed Restrictions', 'target' => ''],
                                    ['name' => 'Fishing', 'target' => ''],
                                    ['name' => 'Fitness Center', 'target' => ''],
                                    ['name' => 'Gated Community', 'target' => ''],
                                    ['name' => 'Golf Carts OK', 'target' => ''],
                                    ['name' => 'Golf Community', 'target' => ''],
                                    ['name' => 'Handicap Modified', 'target' => ''],
                                    ['name' => 'Horse Stable(s)', 'target' => ''],
                                    ['name' => 'Horses Allowed', 'target' => ''],
                                    ['name' => 'Irrigation-Reclaimed Water', 'target' => ''],
                                    ['name' => 'Lake', 'target' => ''],
                                    ['name' => 'No Truck/RV/Motorcycle Parking', 'target' => ''],
                                    ['name' => 'None', 'target' => ''],
                                    ['name' => 'Park', 'target' => ''],
                                    ['name' => 'Playground', 'target' => ''],
                                    ['name' => 'Pool', 'target' => ''],
                                    ['name' => 'Public Boat Ramp', 'target' => ''],
                                    ['name' => 'Racquetball', 'target' => ''],
                                    ['name' => 'Restaurant', 'target' => ''],
                                    ['name' => 'Sidewalk', 'target' => ''],
                                    ['name' => 'Special Community Restrictions', 'target' => ''],
                                    ['name' => 'Stream Seasonal', 'target' => ''],
                                    ['name' => 'Tennis Courts', 'target' => ''],
                                    ['name' => ' Water Access', 'target' => ''],
                                    ['name' => 'Waterfront', 'target' => ''],
                                    ['name' => 'Wheelchair Access', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Community Features:</label>
                                <select class="grid-picker" name="community_feature[]" id="community_feature"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($community_features as $community_feature)
                                        <option value="{{ $community_feature['name'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>"
                                            data-target="{{ $community_feature['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $community_feature['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $association_amenities = [
                                    ['name' => 'Airport/Runway', 'target' => ''],
                                    ['name' => 'Basketball Court', 'target' => ''],
                                    ['name' => 'Boat Slip', 'target' => ''],
                                    ['name' => 'Cable', 'target' => ''],
                                    ['name' => 'Clubhouse', 'target' => ''],
                                    ['name' => 'Dock', 'target' => ''],
                                    ['name' => 'Elevators', 'target' => ''],
                                    ['name' => 'Fence Restrictions', 'target' => ''],
                                    ['name' => 'Fitness Center', 'target' => ''],
                                    ['name' => 'Gated', 'target' => ''],
                                    ['name' => 'Golf Course', 'target' => ''],
                                    ['name' => 'Handicap Modified', 'target' => ''],
                                    ['name' => 'Horse Stables', 'target' => ''],
                                    ['name' => 'Laundry', 'target' => ''],
                                    ['name' => 'Lobby Key Required', 'target' => ''],
                                    ['name' => 'Maintenance', 'target' => ''],
                                    ['name' => 'Marina', 'target' => ''],
                                    ['name' => 'Optional Additional Fees', 'target' => ''],
                                    ['name' => 'Other', 'target' => ''],
                                    ['name' => 'Park', 'target' => ''],
                                    ['name' => 'Pickleball Court(s)', 'target' => ''],
                                    ['name' => 'Playground', 'target' => ''],
                                    ['name' => 'Pool', 'target' => ''],
                                    ['name' => 'Private Boat Ramp', 'target' => ''],
                                    ['name' => 'Racquet Ball', 'target' => ''],
                                    ['name' => 'Recreation Facilities', 'target' => ''],
                                    ['name' => 'Sauna', 'target' => ''],
                                    ['name' => 'Security', 'target' => ''],
                                    ['name' => 'Shuffleboard Court', 'target' => ''],
                                    ['name' => 'Spa/Hot Tubs', 'target' => ''],
                                    ['name' => 'Storage', 'target' => ''],
                                    ['name' => 'Tennis Court(s)', 'target' => ''],
                                    ['name' => 'Trails', 'target' => ''],
                                    ['name' => 'Vehicle Restrictions', 'target' => ''],
                                    ['name' => 'Wheelchair Access', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Association Amenities:</label>
                                <select class="grid-picker" name="association_amenitie[]" id="association_amenitie"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($association_amenities as $association_amenitie)
                                        <option value="{{ $association_amenitie['name'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>"
                                            data-target="{{ $association_amenitie['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $association_amenitie['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $fee_includes = [
                                    ['name' => '24-Hour Guard', 'target' => ''],
                                    ['name' => 'Cable TV', 'target' => ''],
                                    ['name' => 'Common Area Taxes', 'target' => ''],
                                    ['name' => 'Community Pool', 'target' => ''],
                                    ['name' => 'Electricity', 'target' => ''],
                                    ['name' => 'Escrow Reserves Fund', 'target' => ''],
                                    ['name' => 'Fidelity Bond', 'target' => ''],
                                    ['name' => 'Gas', 'target' => ''],
                                    ['name' => 'Insurance', 'target' => ''],
                                    ['name' => 'Internet', 'target' => ''],
                                    ['name' => 'Maintenance Exterior', 'target' => ''],
                                    ['name' => 'Maintenance Grounds', 'target' => ''],
                                    ['name' => 'Maintenance Repairs', 'target' => ''],
                                    ['name' => 'Manager', 'target' => ''],
                                    ['name' => 'None', 'target' => ''],
                                    ['name' => 'Other', 'target' => ''],
                                    ['name' => 'Pest Control', 'target' => ''],
                                    ['name' => 'Pool Maintenance', 'target' => ''],
                                    ['name' => 'Private Road', 'target' => ''],
                                    ['name' => 'Recreational Facilities', 'target' => ''],
                                    ['name' => 'Security', 'target' => ''],
                                    ['name' => 'Sewer', 'target' => ''],
                                    ['name' => 'Trash', 'target' => ''],
                                    ['name' => 'Water', 'target' => ''],
                                ];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Fee Includes:</label>
                                <select class="grid-picker" name="fee_include[]" id="fee_include"
                                    style="justify-content: flex-start;" multiple required>
                                    <option value="">Select</option>
                                    @foreach ($fee_includes as $fee_include)
                                        <option value="{{ $fee_include['name'] }}"
                                            data-icon="<i class='fa-regular fa-circle-check'></i>"
                                            data-target="{{ $fee_include['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $fee_include['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Amenities with Additional Fees:</label>
                                <input type="number" name="amenities_with_additional_fees"
                                    placeholder="Amenities with Additional Fees" id="amenities_with_additional_fees"
                                    class="form-control has-icon" data-icon="fa-solid fa-qrcode" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">CDD:</label>
                                <select class="grid-picker" name="has_cdd" id="has_cdd"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $item)
                                        @php
                                            if ($item['name'] == 'Yes') {
                                                $target = '.anual_cdd_fee';
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
                            <div class="form-group anual_cdd_fee d-none">
                                <label class="fw-bold">Annual CDD Fee</label>
                                <input type="number" name="annual_cdd_fee" id="annual_cdd_fee"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar ">
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Land Lease:</label>
                                <select class="grid-picker" name="has_land_lease" id="has_hoa"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($yes_or_nos as $item)
                                        @php
                                            if ($item['name'] == 'Yes') {
                                                $target = '.land_lease_fee';
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
                            <div class="form-group land_lease_fee d-none">
                                <label class="fw-bold">Land Lease Fee</label>
                                <input type="number" name="land_lease_fee" id="land_lease_fee"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar ">
                            </div>
                        </div>

                    </div>
                    {{-- Slide 18 --}}
                    {{-- Slide 19 --}}
                    <div class="wizard-step" data-step="88">
                        <div class="form-group">
                            <label class="fw-bold"> Description:</label>
                            <textarea name="description" id="description" class="form-control" cols="30" rows="10" required></textarea>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Keywords:<small class="small">(Add keywords of the
                                    best property features) </small></label>
                            <input type="text" name="keywords" id="keywords" class="form-control has-icon"
                                data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Legal Disclamers:</label>
                            <input type="text" name="driving_directions" id="keywords"
                                class="form-control has-icon" data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Driving Directions:</label>
                            <input type="text" name="driving_directions" id="keywords"
                                class="form-control has-icon" data-icon="fa-solid fa-tag">
                        </div>
                        @php
                            $compensation = [['name' => 'Single Agent Compensation', 'target' => ''], ['name' => 'Non Rep Compensation', 'target' => ''], ['name' => 'Transaction Broker Compensation', 'target' => '']];

                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Compensation:</label>
                            <select class="grid-picker" name="compensation" id="fee_include"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($compensation as $item)
                                    @php
                                        if ($item['name'] == 'Single Agent Compensation') {
                                            $target = '.single_agent_compensation_amount_vacant_land';
                                        } elseif ($item['name'] == 'Non Rep Compensation') {
                                            $target = '.non_rep_compensation_amount_vacant_land';
                                        } elseif ($item['name'] == 'Transaction Broker Compensation') {
                                            $target = '.transaction_amount_vacant_land';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group single_agent_compensation_amount_vacant_land">
                            <label class="fw-bold">Single Agent Compensation Amount:</label>
                            <input type="number" name="compensation_amount" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar">
                        </div>
                        <div class="form-group non_rep_compensation_amount_vacant_land ">
                            <label class="fw-bold"> Non Rep Compensation Amount:</label>
                            <input type="number" name="compensation_amount" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar">
                        </div>
                        <div class="form-group transaction_amount_vacant_land">
                            <label class="fw-bold">Transaction Broker Compensation Amount:</label>
                            <input type="number" name="compensation_amount" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar">
                        </div>
                    </div>
                    {{-- Slide 19 --}}
                    {{-- Slide 20 --}}
                    <div class="wizard-step" data-step="89">
                        <h4> Title Company Information:</h4>
                        <div class="form-group">
                            <label class="fw-bold">Name:</label>
                            <input type="text" name="title_company_name" id="title_company_name" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Address:</label>
                            <input type="text" name="title_company_address" id="title_company_address"
                                placeholder="" class="form-control has-icon" data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Phone Number</label>
                            <input type="text" name="title_company_phone" id="title_company_phone" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-phone" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Email:</label>
                            <input type="text" name="title_company_email" id="titl_company_email" placeholder=""
                                value="{{ @$auction->get->title_company_email }}" data-icon="fa-solid fa-envelope"
                                class="form-control has-icon" required>
                        </div>
                    </div>
                    {{-- Slide 20 --}}
                    {{-- Slide 21 --}}
                    <div class="wizard-step" data-step="90">
                        <h4>Agent info:</h4>
                        <div class="form-group">
                            <label class="fw-bold">First Name:</label>
                            <input type="text" name="first_name" id="first_name" placeholder=""
                                value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                                data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Last Name:</label>
                            <input type="text" name="last_name" id="last_name" placeholder=""
                                value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                                data-icon="fa-solid fa-user" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Phone Number:</label>
                            <input type="text" name="agent_phone" id="agent_phone" placeholder=""
                                value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                                data-icon="fa-solid fa-phone">
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
                                value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                                data-icon="fa-solid fa-handshake">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Real Estate License #:</label>
                            <input type="text" name="agent_license_no" id="agent_license_no" placeholder=""
                                value="{{ optional(Auth::user())->license_no }}" class="form-control has-icon"
                                data-icon="fa-solid fa-id-card">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">NRDS ID #:</label>
                            <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                                value="{{ optional(Auth::user())->mls_id }}">
                        </div>

                    </div>
                    {{-- Slide 21 --}}
                    {{-- Slide 22 --}}
                    <div class="wizard-step" data-step="91">
                        <div class="form-group ">
                            <label class="fw-bold">Property Photos</label>
                            <input type="file" name="visible_photos" id="property_picture" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Property Video:</label>
                            <input type="file" name="visible_property_video" id="property_video" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">3D Tour:</label>
                            <input type="url" name="three_d_tour" id="three_d_tour" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Floor Plan:</label>
                            <input type="file" name="visible_note" id="visible_note" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Addendums/Disclosures:</label>
                            <input type="file" name="visible_upload_file[]" id="upload_file" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                        </div>
                    </div>
                    {{-- Slide 22 --}}

                    {{-- Slides End For Vacant Land  --}}
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
@endsection
@push('scripts')
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
        function changePropertyStyle(p) {
            if (p == "Vacant Land") {
                $('.property_style_next_hide').addClass('d-none');
                $('.road_frontage_next_hide').addClass('d-none');
                $('.hide_vacant').removeClass('d-none');
                $('.hide_vacant').removeClass('d-none');
                $('.').remove();

            } else if (p == "Busniess Opportunity") {
                $('.business_oportunity_remove').remove();
            }
        }
        // document.getElementById('auction_type').change();
        $(function() {
            changePropertyStyle("");
        });
    </script>

    <script>
        function changeCurrentUse(p) {


            $('.current_use_next_hide').addClass('d-none');
        }
        // document.getElementById('auction_type').change();
        $(function() {
            changePropertyStyle("");
        });
    </script>
    <script>
        function changeFrontExposure(p) {


            $('.front_exposure_next_hide').addClass('d-none');

        }
        // document.getElementById('auction_type').change();
        $(function() {
            changePropertyStyle("");
        });
    </script>
    <script>
        function changeLotFeature(p) {


            $('.lot_feature_next_hide').addClass('d-none');

        }
        // document.getElementById('auction_type').change();
        $(function() {
            changeLotFeature("");
        });
    </script>
    <script>
        function change_adjacent_use(p) {


            $('.adjacent_use_next_hide').addClass('d-none');

        }
        // document.getElementById('auction_type').change();
        $(function() {
            change_adjacent_use("");
        });
    </script>
    <script>
        function changeRoadFrontage(p) {
            $('.road_frontage_next_hide').addClass('d-none');
        }
        // document.getElementById('auction_type').change();
        $(function() {
            changeRoadFrontage("");
        });
    </script>
    <script>
        function changeRoadSurfaceType(p) {
            $('.road_surface_type_next_hide').addClass('d-none');

        }
        // document.getElementById('auction_type').change();
        $(function() {
            changeRoadSurfaceType("");
        });
    </script>
    <script>
        function Utilities_Water_Sewer(p) {


            $('.utilities_water_sewer_next_hide').addClass('d-none');

        }
        // document.getElementById('auction_type').change();
        $(function() {
            Utilities_Water_Sewer("");
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
                            if (StepWizard.currentStep == 46 && property_type ==
                                'Commercial Property') {
                                StepWizard.nextStep = 48;

                            }
                            if (StepWizard.currentStep == 34 && property_type ==
                                'Residential Property') {
                                StepWizard.nextStep = 36;

                            }
                            if (StepWizard.currentStep == 7 &&
                                (property_type == 'Residential Property' || property_type ==
                                    'Income Property')
                            ) {
                                StepWizard.nextStep = 8;
                                StepWizard.backStep = 7;
                            } else if (StepWizard.currentStep == 7 && (property_type ==
                                    'Commercial Property' || property_type == 'Business Opportunity')

                            ) {
                                StepWizard.nextStep = 42;
                                StepWizard.backStep = 7;
                            } else if (StepWizard.currentStep == 7 && property_type ==
                                'Vacant Land (Current Use)'

                            ) {
                                StepWizard.nextStep = 77;
                                StepWizard.backStep = 7;
                            } else {
                                StepWizard.backStep = StepWizard.currentStep;
                            }

                            $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");



                            StepWizard.setStep();
                            if (
                                StepWizard.currentStep == 41 &&
                                (property_type == 'Residential Property' || property_type ==
                                    'Income Property')
                            ) {
                                $('.wizard-step-next').hide();
                                $('.wizard-step-finish').show();
                            }
                            if (
                                StepWizard.currentStep == 76 &&
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

                        // $('.wizard-step.active').removeClass('active').prev().addClass('active');
                        StepWizard.setStep();
                        if (StepWizard.currentStep == 48 && property_type ==
                            'Commercial Property') {
                            StepWizard.backStep = 46;
                        }
                        if (StepWizard.currentStep == 36 && property_type ==
                            'Residential Property') {
                            StepWizard.backStep = 34;
                        }
                        if (StepWizard.currentStep == 8 &&
                            (property_type == 'Residential Property' || property_type == 'Income Property')
                        ) {
                            StepWizard.backStep = 7;
                        } else if (StepWizard.currentStep == 42 && (property_type ==
                                'Commercial Property' || property_type == 'Business Opportunity')) {
                            StepWizard.backStep = 7;
                        } else if (StepWizard.currentStep == 77 && property_type ==
                            'Vacant Land (Current Use)') {
                            StepWizard.backStep = 7;
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
                            return parseInt($(this).attr('data-step')) >= 8 && parseInt($(this)
                                .attr('data-step')) <= 76;
                        });
                        $stepsToRemove.each(function() {
                            $(this).closest('div[data-step]').remove();
                        });
                    }
                    //Remove All the SLides Except THe Residential and Commercial Property
                    if (property_type == 'Residential Property' || property_type == 'Income Property') {
                        var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
                            return parseInt($(this).attr('data-step')) >= 42 && parseInt($(this)
                                .attr('data-step')) <= 91;
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
                            return (stepValue >= 8 && stepValue <= 41) || (stepValue >= 77 &&
                                stepValue <= 91);
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

    if (property_type === 'Residential Property' || property_type === 'Income Property') {
        // Calculate progress for income and residential property steps (9 to 41)
        comp = 20 + (((StepWizard.currentStep - 9) / (41 - 9)) * 80);
    } else if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
        // Calculate progress for commercial and business opportunity steps (42 to 76)
        comp = 20 + (((StepWizard.currentStep - 42) / (76 - 42)) * 80);
    } else if (property_type === 'Vacant Land (Current Use)') {
        // Calculate progress for vacant land steps (77 to 91)
        comp = 20 + (((StepWizard.currentStep - 77) / (91 - 77)) * 80);
    } else {
        // Default progress calculation for other property types (steps 1 to 8)
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

        $('#property_picture').click(function() {
            $('.wizard-step-next').hide();
            $('.wizard-step-finish').show();
        });
        $('#property_picture1').click(function() {
            $('.wizard-step-next').hide();
            $('.wizard-step-finish').show();
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
