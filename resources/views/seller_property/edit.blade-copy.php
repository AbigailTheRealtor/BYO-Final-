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
                <form class="p-4 pt-0 mainform" action="{{ route('edit-seller-property-listing', @$auction->id) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="wizard-step " id="after_this">
                        <div class="form-group">
                            <label class="fw-bold" for="address">Address:</label>
                            <input type="text" name="address" value="{{ $auction->get->address }}" data-type="address"
                                placeholder="199 Market St, San Francisco, CA" id="address"
                                class="form-control has-icon search_places" data-icon="fa-solid fa-location-dot"
                                data-msg-required="Please enter address" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold" for="address">City:</label>
                            <input type="text" name="city" value="{{ $auction->get->city }}" placeholder="City" data-type="cities" id="city"
                                class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                                data-msg-required="Please enter city" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="address">County:</label>
                            <input type="text" name="county" value="{{ $auction->get->county }}" placeholder="County" id="county"
                                class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                                data-msg-required="Please enter county" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="address">State:</label>
                            <input type="text" name="state" value="{{ $auction->get->state }}" placeholder="State" data-type="states" id="state"
                                class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
                                data-msg-required="Please enter state" required>
                        </div>
                    </div>
                    {{--  3 Jul 2023 for Business Opportunity --}}
                    {{--  3 Jul 2023 for Business Opportunity --}}
                    <div class="wizard-step">
                        <div class="form-group">
                            <label for="address">Listing Date:</label>
                            <input type="date" name="listing_date" value="{{ $auction->listing_date ?? '' }}" id="listing_date"
                                class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                data-msg-required="Please enter listing date" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Expiration Date:</label>
                            <input type="date" name="expiration_date" value="{{ $auction->expiration_date ?? '' }}" id="expiration_date"
                                class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                                data-msg-required="Please enter expiration date" required>
                        </div>
                    </div>
                    {{--  3 Jul 2023 for Business Opportunity --}}
                    {{--  3 Jul 2023 for Business Opportunity --}}
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">
                                Listing Type:
                            </label>
                            <div>
                                @php
                                    $auction_types = [['name' => 'Auction (Timer)', 'icon' => '<i class="fa-regular fa-clock"></i>', 'target' => ''], ['name' => 'Traditional (No Timer)', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>', 'target' => '']];
                                @endphp
                                <select name="auction_type" id="auction_type" class="grid-picker"
                                    style="justify-content: flex-start;" onchange="changeAuctionType(this.value);" required>
                                    <option value=""></option>
                                    @foreach ($auction_types as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->auction_type) }}
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
                                        {{ selected($item['name'], @$auction->get->auction_length) }}
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
                    {{-- 3 Jul 2023 for Business Opportunity --}}
                    <div class="wizard-step">
                        <h4>Autobid (Reserve Price and Terms)</h4>


                        <div class="form-group ">
                            <label class="fw-bold" for="starting_price">Starting Price:</label>
                            <input type="number" step="0.01" name="starting_price" value="{{ $auction->get->starting_price ?? '' }}" placeholder="0.00"
                                id="starting_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar" data-msg-required="Please enter Starting Price" required>
                        </div>

                        <div class="form-group ">
                            <label class="fw-bold" for="buy_now_price">Buy Now Price:</label>
                            <input type="number" step="0.01" name="buy_now_price" value="{{ $auction->get->buy_now_price ?? '' }}" placeholder="0.00"
                                id="buy_now_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar" data-msg-required="Please enter Buy Now Price" required>
                        </div>

                        <div class="form-group ">
                            <label class="fw-bold" for="reserve_price">Reserve Price:</label>
                            <input type="number" step="0.01" name="reserve_price" value="{{ $auction->get->reserve_price ?? '' }}" placeholder="0.00"
                                id="reserve_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar">
                        </div>
                        {{-- 12 June 2023  --}}
                        <div class="form-group row ">
                            <div class="col-md-6">
                                <label class="fw-bold">Escrow Amount(Reserve Terms):</label>
                                <input type="number" name="escrow_amount" value="{{ $auction->escrow_amount ?? '' }}" id="term_escrow_amount" placeholder=""
                                    class="form-control" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Escrow Amount(Buy Now Terms):</label>
                                <input type="number" name="escrow_amount2" value="{{ $auction->escrow_amount2 ?? '' }}" id="term_escrow_amount" placeholder=""
                                    class="form-control" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="col-md-6 ">
                                <label class="fw-bold">Inspection Period(Reserve Terms):</label>
                                <input type="number" name="inspection_period" value="{{ $auction->inspection_period ?? '' }}" id="term_inspection_period"
                                    placeholder="" class="form-control has-icon"  data-icon="fa-solid fa-calendar-days"
                                    required>
                            </div>
                            <div class="col-md-6 ">
                                <label class="fw-bold">Inspection Period(Buy Now Terms):</label>
                                <input type="number" name="inspection_period2" value="{{ $auction->inspection_period2 ?? ''}}" id="term_inspection_period"
                                    placeholder="" class="form-control has-icon" data-icon="fa-solid fa-calendar-days"
                                    required>
                            </div>

                        </div>
                        <div class="form-group row ">
                            <div class="col-md-6">
                                <label class="fw-bold">Number of days seller will accept for closing:(Reserve
                                    Terms)</label>
                                <input type="number" name="closing_days" value="{{ $auction->get->closing_days ?? '' }}" id="closing_days" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-calendar-day" required>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Number of days seller will accept for closing:(Buy Now
                                    Terms)</label>
                                <input type="number" name="closing_days2" value="{{ $auction->closing_days2 ?? '' }}" id="closing_days" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-calendar-day" required>
                            </div>
                        </div>
                        <div class="form-group ">
                            <label class="fw-bold" for="reserve_price">Autobid:</label>
                            <input class="form-check-input" type="radio" name="auto_bid" value="{{ $auction->auto_bid ?? '' }}" id="auto_bid1"
                                value="1">
                            <label class="form-check-label" for="auto_bid1">
                                Yes
                            </label>
                            <input class="form-check-input" type="radio" name="auto_bid" id="auto_bid0"
                                value="0" checked>
                            <label class="form-check-label" for="auto_bid0">
                                No
                            </label>
                        </div>
                        <div class="form-group autobid_price ">
                            <label class="fw-bold" for="autobid_price">Autobid Price:</label>
                            <input type="number" step="0.01"  name="autobid_price" value="{{ $auction->autobid_price ?? '' }}" placeholder="0.00"
                                id="autobid_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar">
                            <label class="fw-bold" for="autobid_price">Autobid Price:</label>
                            <input type="number" step="0.01" name="autobid_price2" value="{{ $auction->autobid_price2 ?? '' }}" placeholder="0.00"
                                id="autobid_price" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar">
                            <label class="fw-bold" for="autobid_price">Autobid Price:</label>
                            <input type="number" step="0.01" name="autobid_price3"  value="{{ $auction->autobid_price3 ?? '' }}"   placeholder="0.00"
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
                                    {{ selected($item['name'], @$auction->get->contigencies_accepted_by_seller) }}
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
                                    {{ selected($item['name'], @$auction->get->term_financings) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group row custom_seller_financing">
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Purchase Price:</label>
                                <input type="number" name="purchase_price_seller_financing"
                                    id="purchase_price_seller_financing" value="{{ $auction->get->purchase_price_seller_financing ?? '' }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Down Payment:</label>
                                <input type="number" name="down_payment_seller_financing" value="{{ $auction->get->down_payment_seller_financing ?? '' }}"
                                    id="down_payment_seller_financing" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Seller Financing Amount:</label>
                                <input type="number" name="seller_financing_amount" id="seller_financing_amount" value="{{ $auction->get->seller_financing_amount ?? '' }}"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Interest Rate:</label>
                                <input type="number" name="interest_rate_seller_financing" value="{{ $auction->get->interest_rate_seller_financing ?? '' }}"
                                    id="interest_rate_seller_financing" class="form-control has-icon"
                                    data-icon="fa-solid fa-percent" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Term:</label>
                                <input type="text" name="term_seller_financing" id="term_seller_financing" value="{{ $auction->get->term_seller_financing ?? '' }}"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Monthly Payments:</label>
                                <input type="number" name="monthly_payment_seller_financing" value="{{ $auction->get->monthly_payment_seller_financing ?? '' }}"
                                    id="monthly_payment_seller_financing" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Balloon Payment: </label>
                                <input type="number" name="baloon_payment_seller_financing" value="{{ $auction->get->baloon_payment_seller_financing ?? '' }}"
                                    id="baloon_payment_seller_financing" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Acceptable Currency/ Financing:</label>
                                <input type="text" name="custom_term_financings" id="custom_term_financings" value="{{ $auction->get->custom_term_financings ?? '' }}"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Security:</label>
                                <input type="text" name="security_seller_financing" id="security_seller_financing" value="{{ $auction->get->security_seller_financing ?? '' }}"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Prepayment Penalty:</label>
                                <input type="text" name="prepayment_penalty" id="prepayment_penalty" value="{{ $auction->get->prepayment_penalty ?? '' }}"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Closing Costs:</label>
                                <input type="text" name="closing_costs" id="closing_costs" value="{{ $auction->get->closing_costs ?? '' }}"
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
                                    {{ selected($item['name'], @$auction->get->exchange_trade) }}
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="fw-bold">Offered Value of the Exchange Item:</label>
                                <input type="number" name="offer_value_of_exchange_item" value="{{ $auction->get->offer_value_of_exchange_item ?? '' }}"
                                    id="offer_value_of_exchange_item" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="fw-bold">Price Adjustment:</label>
                                <input type="number" name="price_adjustment_exchange_trade" value="{{ $auction->get->price_adjustment_exchange_trade ?? '' }}"
                                    id="price_adjustment_exchange_trade" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="fw-bold">Purchase Price With Exchange/Trade:</label>
                                <input type="number" name="purchase_price_with_exchange_trade" value="{{ $auction->get->purchase_price_with_exchange_trade ?? '' }}"
                                    id="purchase_price_with_exchange_trade" class="form-control has-icon"
                                    data-icon="fa-solid fa-percent" required>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="fw-bold">Additional Terms: </label>
                                <input type="text" name="additional_term" id="additional_term" value="{{ $auction->get->additional_term ?? '' }}"
                                    class="form-control has-icon" data-icon="fa-solid fa-circle-check" required>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="fw-bold">Details of Exchange/Trade Item: </label>
                                <input type="text" name="detail_of_exchange_trade_item" value="{{ $auction->get->detail_of_exchange_trade_item ?? '' }}"
                                    id="detail_of_exchange_trade_item" class="form-control has-icon"
                                    data-icon="fa-solid fa-circle-check" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label class="fw-bold">Buyer's Premium (Credit buyer will pay seller at closing) -
                                    Optional:</label>
                                <input type="text" name="buyer_premium" id="buyer_premium" value="{{ $auction->get->buyer_premium ?? '' }}" placeholder=""
                                    class="form-control " data-icon="fa-solid fa-dollar">
                            </div>

                            <div class="col-md-6">
                                <label class="fw-bold">Buyer's Rebate (Credit buyer will receive at closing) -
                                    Optional:</label>
                                <input type="text" name="seller_premium" id="seller_premium" value="{{ $auction->get->seller_premium ?? '' }}" placeholder=""
                                    class="form-control " data-icon="fa-solid fa-dollar">
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step">

                        {{--  30 May 2023 --}}
                        @php
                            $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property']];
                        @endphp

                        {{--  30 May 2023 --}}
                        <div class="form-group">
                            <label class="fw-bold">Property Types:</label>
                            <select class="grid-picker" name="property_type" id="property_type"
                                onchange="changePropertyType(this.value);" required>
                                <option value="">Select</option>
                                @foreach ($property_types as $row_pt)
                                    <option value="{{ $row_pt['name'] }}" class="card flex-column"
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
                                        ['name' => 'Arts And Entertainment', 'class' => 'commercial-length'],
                                        ['name' => 'Assembly Hall', 'class' => 'commercial-length'],
                                        ['name' => 'Assisted Living', 'class' => 'commercial-length'],
                                        ['name' => 'Auto Dealer', 'class' => 'commercial-length'],
                                        ['name' => 'Auto Service', 'class' => 'commercial-length'],
                                        ['name' => 'Bar/Tavern/Lounge', 'class' => 'commercial-length'],
                                        ['name' => 'Barber Beauty', 'class' => 'commercial-length'],
                                        ['name' => 'Car Wash', 'class' => 'commercial-length'],
                                        ['name' => 'Child Care', 'class' => 'commercial-length'],
                                        ['name' => 'Church', 'class' => 'commercial-length'],
                                        ['name' => 'Commercial', 'class' => 'commercial-length'],
                                        ['name' => 'Construction/Contractor', 'class' => 'commercial-length'],
                                        ['name' => 'Distribution', 'class' => 'commercial-length'],
                                        ['name' => 'Distributor Routine Ven', 'class' => 'commercial-length'],
                                        ['name' => 'Education/School', 'class' => 'commercial-length'],
                                        ['name' => 'Farm', 'class' => 'commercial-length'],
                                        ['name' => 'Fashion Specialty', 'class' => 'commercial-length'],
                                        ['name' => 'Flex Spaces', 'class' => 'commercial-length'],
                                        ['name' => 'Florist/Nursery', 'class' => 'commercial-length'],
                                        ['name' => 'Food & Beverage', 'class' => 'commercial-length'],
                                        ['name' => 'Gas Station', 'class' => 'commercial-length'],
                                        ['name' => 'Grocery', 'class' => 'commercial-length'],
                                        ['name' => 'Heave Weight Sales Service', 'class' => 'commercial-length'],
                                        ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                                        ['name' => 'Industrial', 'class' => 'commercial-length'],
                                        ['name' => 'Light Items Sales Only', 'class' => 'commercial-length'],
                                        ['name' => 'Marine/Marina', 'class' => 'commercial-length'],
                                        ['name' => 'Medical', 'class' => 'commercial-length'],
                                        ['name' => 'Mixed', 'class' => 'commercial-length'],
                                        ['name' => 'Mobile/Trailer Park', 'class' => 'commercial-length'],
                                        ['name' => 'Professional Service', 'class' => 'commercial-length'],
                                        ['name' => 'Professional/Office', 'class' => 'commercial-length'],
                                        ['name' => 'Five or More (Residential Units)', 'class' => 'income-length'],
                                        ['name' => 'Recreation', 'class' => 'commercial-length'],
                                        ['name' => 'Research & Development', 'class' => 'commercial-length'],
                                        ['name' => 'Residential', 'class' => 'commercial-length'],
                                        ['name' => 'Restaurant', 'class' => 'commercial-length'],
                                        ['name' => 'Retail', 'class' => 'commercial-length'],
                                        ['name' => 'Storage', 'class' => 'commercial-length'],
                                        ['name' => 'Theatre', 'class' => 'commercial-length'],
                                        ['name' => 'Timberland', 'class' => 'commercial-length'],
                                        ['name' => 'Veterinary', 'class' => 'commercial-length'],
                                        ['name' => 'Warehouse', 'class' => 'commercial-length'],
                                        ['name' => 'Wholesale', 'class' => 'commercial-length'],
                                    ];
                                @endphp
                                <select name="property_items" id="property_items" class="property_items grid-picker"
                                    style="justify-content: flex-start;" required>
                                    <option value=""></option>
                                    @foreach ($property_items as $item)
                                        <option value="{{ $item['name'] }}" data-target=""
                                    {{ selected($item['name'], @$auction->get->property_items) }}
                                            class="card flex-row {{ $item['class'] }}" style="width:calc(33.33% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    @php
                        $bedrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_bedrooms', 'name' => 'Other']];
                        $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.custom_bathrooms', 'name' => 'Other']];
                    @endphp
                    <div class="wizard-step">
                        {{-- Changes 2 June 2023 --}}
                        @php
                            $property_styles = [['name' => 'Vacant Land'], ['name' => 'Busniess Opportunity']];
                        @endphp
                        {{-- Changes 30 May 2023 --}}
                        <div class="form-group business_type_next">
                            <label class="fw-bold">Property Styles:</label>
                            <select class="grid-picker" name="property_style" id="property_style"
                                onchange="changePropertyStyle(this.value);" required>
                                <option value="">Select</option>
                                @foreach ($property_styles as $property_style)
                                    <option value="{{ $property_style['name'] }}" class="card flex-column"
                                    {{ selected($property_style['name'], @$auction->get->property_style) }}
                                        style="width:calc(24% - 10px);" data-icon='<i class="fa-solid fa-hotel"></i>'>
                                        {{ $property_style['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group residential_hide">
                            <label class="fw-bold">Bedrooms:</label>
                            <select class="grid-picker" name="bedrooms" id="bedrooms" style="" required>
                                <option value="">Select</option>
                                @foreach ($bedrooms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ selected($item['name'], @$auction->get->bedrooms) }}
                                        class="card flex-column" style="width:calc(9% - 10px);"
                                        data-icon='<i class="fa-solid fa-bed"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_bedrooms d-none">
                            <label class="fw-bold">Bedrooms:</label>
                            <input type="text" name="custom_bedrooms" value="{{ $auction->get->custom_bedrooms ?? '' }}" id="custom_bedrooms"
                                class="form-control has-icon" data-icon="fa-solid fa-bed" required>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        <div class="form-group">
                            <label class="fw-bold">Bathrooms:</label>
                            <select class="grid-picker" name="bathrooms" id="bathrooms" style="" required>
                                <option value="">Select</option>
                                @foreach ($bathrooms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ selected($item['name'], @$auction->get->bathrooms) }}
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-solid fa-bath"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_bathrooms d-none">
                            <label class="fw-bold">Bathrooms:</label>
                            <input type="text" name="custom_bathrooms" value="{{ $auction->get->custom_bathrooms ?? '' }}" id="custom_bathrooms"
                                class="form-control has-icon" data-icon="fa-solid fa-bath" required>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Vacant Land --}}
                    <div class="wizard-step business_oportunity_remove residential_and_income_remove">
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
                        <div class="form-group  ">
                            <label class="fw-bold">Current Use:</label>
                            <select class="grid-picker" name="current_use" onchange="changeCurrentUse(this.value);"
                                id="current_use" style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($current_uses as $current_use)
                                    <option value="{{ $current_use['name'] }}"
                                    {{ selected($current_use['name'], @$auction->get->current_use) }}
                                        data-target="{{ $current_use['target'] }}" class="card flex-column"
                                        style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $current_use['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Vacant Land --}}
                    {{-- 3 Jul 2023 For Vacant Land --}}
                    <div class="wizard-step vacant_land_remove residential_and_income_remove">
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
                                    {{ selected($item['name'], @$auction->get->has_real_estate_include) }}
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Business Name:</label>
                            <input type="text" name="business_name" value="{{ $auction->get->business_name ?? '' }}" id="custom_bathrooms"
                                class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Year Established:</label>
                            <input type="number" name="year_established" value="{{ $auction->get->year_established ?? '' }}" id="year_established"
                                class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                        </div>
                        @php
                            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];

                            $yes_or_nos1 = [['name' => 'Beer/Wine', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Liquor', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'None', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Off Site', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'On Site', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Real Estate Include:</label>
                            <select class="grid-picker" name="real_estate_include" id="bathrooms" style=""
                                required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos1 as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ selected($item['name'], @$auction->get->real_estate_include) }}
                                        class="card flex-column" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
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
                                    {{ selected($item['name'], @$auction->get->prop_condition) }}
                                        class="card flex-row" style="width:calc(50% - 10px);">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">List of Required Rquired Repairs</label>
                            <textarea name="known_repairs" id="known_repairs" value="{{ $auction->get->known_repairs ?? '' }}" class="form-control" cols="30" rows="5"></textarea>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        <div class="row ">

                            <div class="form-group">
                                <label class="fw-bold" for="heated_sqft">Heated Sqft:</label>
                                <input type="number" name="heated_sqft" value="{{ $auction->get->heated_sqft ?? '' }}" placeholder="Heated sqft" id="heated_sqft"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                                    data-msg-required="Please enter Heated sqft" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="total_heated_sqft">Total heated Sqft:</label>
                                <input type="number" name="total_heated_sqft" value="{{ $auction->get->total_heated_sqft ?? '' }}" placeholder="Heated sqft"
                                    id="heated_sqft" class="form-control has-icon hide_arrow"
                                    data-icon="fa-solid fa-ruler-combined" data-msg-required="Please enter Heated sqft"
                                    required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="sqft">Sqft:</label>
                                <input type="number" name="sqft" value="{{ $auction->get->sqft ?? '' }}" placeholder="Heated sqft" id="heated_sqft"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                                    data-msg-required="Please enter Heated sqft" required>
                            </div>
                            @php
                                $heated_sources = [['name' => 'Appraisal', 'target' => ''], ['name' => 'Building', 'target' => ''], ['name' => 'Measured', 'target' => ''], ['name' => 'Owner Provided', 'target' => ''], ['name' => 'Public Records', 'target' => '']];
                            @endphp

                            <div class="form-group">
                                <label class="fw-bold">Heated Source</label>
                                <select class="grid-picker" name="heated_source" id="heated_sources" style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($heated_sources as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                            {{ selected($item['name'], @$auction->get->heated_source) ? 'selected' : '' }}
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
                                <label class="fw-bold">Total Aceage</label>
                                <select class="grid-picker" name="total_aceage[]" id="lot_size"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($lot_sizes as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->total_aceage) ? 'selected' : '' }}
                                            class="card flex-column" style="width:calc(33.3% - 10px);">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="lot_size">Lot size</label>
                                <input type="number" name="lot_size" value="{{ $auction->get->lot_size ?? '' }}" placeholder="Lot Size" id="lot_size"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                                    data-msg-required="Please enter Lot Size">
                            </div>
                            <div class="form-group">
                                <label class="fw-bold" for="year_built">Year Built:</label>
                                <input type="number" name="year_built" value="{{ $auction->get->year_built ?? '' }}" placeholder="Year Built" id="year_built"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-calendar-day"
                                    data-msg-required="Please enter Year Built" required>
                            </div>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        <div class="form-group">
                            <label class="fw-bold">Number Of Floors within the Property</label>
                            <input type="text" name="number_of_buildings" value="{{ $auction->get->number_of_buildings ?? '' }}" id="number_of_buildings" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-hotel">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Floor Number</label>
                            <input type="number" name="floors_in_unit" value="{{ $auction->get->floors_in_unit ?? '' }}" id="floors_in_unit" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-building">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Total Number of Floors in the Entire Building</label>
                            <input type="number" name="total_floors" value="{{ $auction->get->total_floors ?? '' }}" id="total_floors" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-building">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Building Elevator:</label>
                            <select class="grid-picker" name="building_elevator" id="building_elevator"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ selected($item['name'], @$auction->get->building_elevator) }}
                                        class="card flex-row" style="width:calc(25% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
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
                        <div class="form-group">
                            <label class="fw-bold">Appliances Included:</label>
                            <select class="grid-picker" name="appliances[]" id="appliances" style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($appliances as $appliance)
                                    <option value="{{ $appliance['name'] }}" data-target="{{ $appliance['target'] }}"
                                        {{ in_array($appliance['name'], @$auction->get->appliances) ? 'selected' : '' }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);">
                                        {{ $appliance['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        @php
                            $floor_coverings = [['name' => 'Bamboo', 'target' => ''], ['name' => 'Brick/Stone', 'target' => ''], ['name' => 'Carpet', 'target' => ''], ['name' => 'Ceramic Tile', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Cork', 'target' => ''], ['name' => 'Engineered Hardwood', 'target' => ''], ['name' => 'Epoxy', 'target' => ''], ['name' => 'Forestry Stewardship Certified', 'target' => ''], ['name' => 'Granite', 'target' => ''], ['name' => 'Laminate', 'target' => ''], ['name' => 'Linoleum', 'target' => ''], ['name' => 'Marble', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Parquet', 'target' => ''], ['name' => 'Porcelain Tile', 'target' => ''], ['name' => 'Quarry Tile', 'target' => ''], ['name' => 'Reclaimed Wood', 'target' => ''], ['name' => 'Recycled/Composite Flooring', 'target' => ''], ['name' => 'Slate', 'target' => ''], ['name' => 'Terrazzo', 'target' => ''], ['name' => 'Tile', 'target' => ''], ['name' => 'Travertine', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Floor Covering</label>
                            <select class="grid-picker" name="floor_covering[]" id="room_primary_floor_covering"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($floor_coverings as $floor_covering)
                                    <option value="{{ $floor_covering['name'] }}"
                                    {{ in_array($floor_covering['name'], @$auction->get->floor_covering) ? 'selected' : ''}}
                                        data-target="{{ $floor_covering['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $floor_covering['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 5 Jul For Residential and Income --}}
                    <div class="wizard-step vacant_land_remove business_oportunity_remove">
                        @php
                            $interior_features = [
                                ['name' => ' Accessibility Features', 'target' => ''],
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
                        <div class="row">
                            <div class="form-group">
                                <label class="fw-bold">Interior Features:</label>
                                <select class="grid-picker" name="interior_feature[]" id="interior_feature"
                                    style="justify-content: flex-start;" required multiple>
                                    <option value="">Select</option>
                                    @foreach ($interior_features as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ in_array($item['name'], @$auction->get->interior_feature) ? 'selected' : '' }}
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    {{-- 5 Jul For Residential and Income --}}
                    <div class="wizard-step vacant_land_remove">
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
                                    <option value="{{ $utilitie12['name'] }}" data-target="{{ $utilitie12['target'] }}"
                                    {{ in_array($utilitie12['name'], @$auction->get->utilities) ? 'selected' : '' }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);">
                                        {{ $utilitie12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $waters = [['name' => 'Canal Lake for Irrigation', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Private', 'target' => ''], ['name' => 'Public', 'target' => ''], ['name' => 'Well', 'target' => ''], ['name' => 'Well Required', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Water:</label>
                            <select class="grid-picker" name="water12[]" id="water12"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($waters as $water)
                                    <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                                    {{ in_array($water['name'], @$auction->get->water) ? 'selected' : '' }}
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
                                    {{ in_array($sewer12['name'], @$auction->get->sewer) ? 'selected' : '' }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);">
                                        {{ $sewer12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 5 Jul 2023 For Residential and Income --}}
                    <div class="wizard-step vacant_land_remove residential_and_income_remove">
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
                            <select class="grid-picker" name="room_type" id="room_type"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($room_types as $room_type)
                                    <option value="{{ $room_type['name'] }}" data-target="{{ $room_type['target'] }}"
                                    {{ selected($room_type['name'], @$auction->get->room_type) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);">
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
                            <select class="grid-picker" name="room_level" id="room_level"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($room_levels as $room_level)
                                    <option value="{{ $room_level['name'] }}" data-target="{{ $room_level['target'] }}"
                                    {{ selected($room_level['name'], @$auction->get->room_level) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);">
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
                            <select class="grid-picker" name="bed_room_closest_type" id="bed_room_closest_type"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($bed_room_closest_types as $bed_room_closest_type)
                                    <option value="{{ $bed_room_closest_type['name'] }}"
                                    {{ selected($bed_room_closest_type['name'], @$auction->get->bed_room_closest_type) }}
                                        data-target="{{ $bed_room_closest_type['target'] }}" class="card flex-row"
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
                            <select class="grid-picker" name="room_primary_floor_covering"
                                id="room_primary_floor_covering" style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($room_primary_floor_coverings as $room_primary_floor_covering)
                                    <option value="{{ $room_primary_floor_covering['name'] }}"
                                    {{ selected($room_primary_floor_covering['name'], @$auction->get->room_primary_floor_covering) }}
                                        data-target="{{ $room_primary_floor_covering['target'] }}" class="card flex-row"
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
                                ['name' => 'Other- Custom Input', 'target' => ''],
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
                                    <option value="{{ $room_feature['name'] }}"
                                    {{ (is_array($auction->get->room_feature) && in_array($room_feature['name'], $auction->get->room_feature)) ? 'selected' : '' }}
                                        data-target="{{ $room_feature['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $room_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 5 Jul 2023 For Residential and Income --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove residential_and_income_remove">
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
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Building Features:</label>
                            <select class="grid-picker" name="building_features[]" id="building_feature"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($building_features as $building_feature)
                                    <option value="{{ $building_feature['name'] }}"
                                    {{ (is_array($auction->get->building_features) && in_array($building_feature['name'], $auction->get->building_features)) ? 'selected' : '' }}
                                        data-target="{{ $building_feature['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $building_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step residential_and_income_remove">
                        @php
                            $lot_features1 = [
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
                                ['name' => 'Riprarian Rights', 'target' => ''],
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
                                style="justify-content: flex-start;" onclick="changeLotFeature(this.value)" multiple
                                required>
                                <option value="">Select</option>
                                @foreach ($lot_features1 as $lot_feature12)
                                    <option value="{{ $lot_feature12['name'] }}"
                                    {{ (is_array($auction->get->lot_features) && in_array($lot_feature12['name'], $auction->get->lot_features)) ? 'selected' : '' }}
                                        data-target="{{ $lot_feature12['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $lot_feature12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove residential_and_income_remove">
                        @php
                            $adjoining_properties = [['name' => 'Airport', 'target' => ''], ['name' => 'Church', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Hotel/Motel', 'target' => ''], ['name' => 'Industrial', 'target' => ''], ['name' => 'Multi-Family', 'target' => ''], ['name' => 'Natural State', 'target' => ''], ['name' => 'Professional Office', 'target' => ''], ['name' => 'Railroad', 'target' => ''], ['name' => 'Residential', 'target' => ''], ['name' => 'School', 'target' => ''], ['name' => 'Undeveloped', 'target' => ''], ['name' => 'Vacant', 'target' => ''], ['name' => 'Waterway', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Adjoining Property (Optional):</label>
                            <select class="grid-picker" name="adjoining_property" id="adjoining_property"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($adjoining_properties as $adjoining_property)
                                    <option value="{{ $adjoining_property['name'] }}"
                                    {{ selected($adjoining_property['name'], @$auction->get->adjoining_property) }}
                                        data-target="{{ $adjoining_property['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $adjoining_property['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        @php
                            $foundations = [['name' => 'Basement', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick/Mortar', 'target' => ''], ['name' => 'Concrete Perimeter', 'target' => ''], ['name' => 'Crawlspace', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pillar/Post/Pier', 'target' => ''], ['name' => 'Slab', 'target' => ''], ['name' => 'Stem Wall', 'target' => ''], ['name' => 'Stilt/On Piling', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Foundation:</label>
                            <select class="grid-picker" name="foundation" id="foundation"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($foundations as $foundation)
                                    <option value="{{ $foundation['name'] }}" data-target="{{ $foundation['target'] }}"
                                    {{ selected($foundation['name'], @$auction->get->foundation) }}

                                        class="card flex-row" style="width:calc(33.3% - 10px);">
                                        {{ $foundation['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        @php
                            $exterior_constructions = [['name' => 'Asbestos', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Cedar', 'target' => ''], ['name' => 'Cement Siding', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'HardiPlank Type', 'target' => ''], ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''], ['name' => 'Log', 'target' => ''], ['name' => 'Metal Frame', 'target' => ''], ['name' => 'Metal Siding', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''], ['name' => 'Stone', 'target' => ''], ['name' => 'Stucco', 'target' => ''], ['name' => 'Tilt up Walls', 'target' => ''], ['name' => 'Vinyl Siding', 'target' => ''], ['name' => 'Wood Frame', 'target' => ''], ['name' => 'Wood Frame (FSC)', 'target' => ''], ['name' => 'Wood Siding ', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Exterior Construction:</label>
                            <select class="grid-picker" name="exterior_construction" id="exterior_construction"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($exterior_constructions as $exterior_construction)
                                    <option value="{{ $exterior_construction['name'] }}"
                                    {{ selected($exterior_construction['name'], @$auction->get->exterior_construction) }}
                                        data-target="{{ $exterior_construction['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $exterior_construction['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Residential and Income --}}
                    <div class="wizard-step vacant_land_remove business_oportunity_remove">
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
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($exterior_features as $exterior_feature)
                                    <option value="{{ $exterior_feature['name'] }}"
                                    {{ in_array($exterior_feature['name'], @$auction->get->exterior_feature) ? 'selected' : '' }}
                                        data-target="{{ $exterior_feature['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $exterior_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 26 Jul 2023 Editing --}}
                    {{-- 3 Jul 2023 For Residential and Income --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        @php
                            $roofs = [['name' => 'Built-Up', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Membrane', 'target' => ''], ['name' => 'Metal', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Roof Over', 'target' => ''], ['name' => 'Shake', 'target' => ''], ['name' => 'Shingle', 'target' => ''], ['name' => 'Slate', 'target' => ''], ['name' => 'Tile', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Roof:</label>
                            <select class="grid-picker" name="roof" id="roof"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($roofs as $roof)
                                    <option value="{{ $roof['name'] }}" data-target="{{ $roof['target'] }}"
                                    {{ selected($roof['name'], @$auction->get->roof) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);">
                                        {{ $roof['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step">
                        @php
                            $road_surface_types = [['name' => 'Asphalt', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Chip And Seal', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Dirt', 'target' => ''], ['name' => 'Gravel', 'target' => ''], ['name' => 'Limerock', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Paved', 'target' => ''], ['name' => 'Unimproved', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Road Surface Type:</label>
                            <select class="grid-picker" name="road_surface_type" onclick="changeRoadSurfaceType()"
                                id="road_surface_type" style="justify-content: flex-start;" required>
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
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step  residential_and_income_remove">
                        @php
                            $road_frontages = [['name' => 'Access Road', 'target' => ''], ['name' => 'Alley', 'target' => ''], ['name' => 'Business District', 'target' => ''], ['name' => 'City Street', 'target' => ''], ['name' => 'County Road ', 'target' => ''], ['name' => 'Divided Highway', 'target' => ''], ['name' => 'Easement', 'target' => ''], ['name' => 'Highway', 'target' => ''], ['name' => 'Interchange', 'target' => ''], ['name' => 'Interstate', 'target' => ''], ['name' => 'Main Thoroughfare', 'target' => ''], ['name' => 'Private Road', 'target' => ''], ['name' => 'Rail', 'target' => ''], ['name' => 'State Road', 'target' => ''], ['name' => 'Turn Lanes', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'None', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Road Frontage:</label>
                            <select class="grid-picker" onclick="changeRoadFrontage(this.value);" name="road_frontage"
                                id="road_frontage" style="justify-content: flex-start;" onclick="" required>
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
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step">
                        @php
                            $front_exposures1 = [['name' => 'North', 'target' => ''], ['name' => 'East', 'target' => ''], ['name' => 'South', 'target' => ''], ['name' => 'West', 'target' => ''], ['name' => 'Southeast', 'target' => ''], ['name' => 'Northeast', 'target' => ''], ['name' => 'Southwest', 'target' => ''], ['name' => 'Northwest', 'target' => '']];
                        @endphp
                        <div class="form-group  ">
                            <label class="fw-bold">Front Exposure:</label>
                            <select class="grid-picker" name="front_exposure" onchange="changeFrontExposure(this.value);"
                                id="front_exposure" style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($front_exposures1 as $front_exposure12)
                                    <option value="{{ $front_exposure12['name'] }}"
                                    {{ selected($front_exposure12['name'], @$auction->get->front_exposure) }}
                                        data-target="{{ $front_exposure12['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $front_exposure12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        @php
                            $parking_features = [['name' => '1 to 5 Spaces', 'target' => ''], ['name' => '6 to 12 Spaces', 'target' => ''], ['name' => '13 to 18 Spaces', 'target' => ''], ['name' => '19 to 30 Spaces', 'target' => ''], ['name' => 'Airplane Hangar', 'target' => ''], ['name' => 'Common', 'target' => ''], ['name' => 'Curb Parking', 'target' => ''], ['name' => 'Deeded', 'target' => ''], ['name' => 'Electric Vehicle Charging Station(s)', 'target' => ''], ['name' => 'Ground Level', 'target' => ''], ['name' => 'Lighted', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Over 30 Spaces', 'target' => ''], ['name' => 'Secured', 'target' => ''], ['name' => 'Under Building', 'target' => ''], ['name' => 'Underground', 'target' => ''], ['name' => 'Valet', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Garage/Parking Features:</label>
                            <select class="grid-picker" name="parking_feature[]" id="parking_feature"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($parking_features as $parking_feature)
                                    <option value="{{ $parking_feature['name'] }}"
                                    {{ in_array($parking_feature['name'], @$auction->get->parking_feature) ? 'selected' : '' }}
                                        data-target="{{ $parking_feature['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $parking_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Vacant Land  --}}
                    <div class="wizard-step business_oportunity_remove">
                        @php
                            $total_acreages = [['name' => '0 to less than 14', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ acres', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
                        @endphp

                        <div class="form-group ">
                            <label class="fw-bold">Total Acreage :</label>
                            <select class="grid-picker" name="total_acreage" id="total_acreage"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($total_acreages as $total_acreage)
                                    <option value="{{ $total_acreage['name'] }}" data-target="{{ $total_acreage['target'] }}"
                                    {{ selected($total_acreage['name'], @$auction->get->total_acreage) }}
                                        class="card flex-column" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                        {{ $total_acreage['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Lot Dimensions(Parcel Number):</label>
                            <input type="number" name="lot_dimensions" value="{{ $auction->get->lot_dimensions }}" id="lot_dimensions"
                                class="form-control has-icon" data-icon="fa-solid " placeholder="Tax ID" required>
                        </div>



                        <div class="form-group">
                            <label class="fw-bold">Lot Size Square Footage:</label>
                            <input type="number" name="lot_size_square_footage" value="{{ $auction->get->lot_size_square_footage }}" id="lot_size_square_footage"
                                class="form-control has-icon" data-icon="fa-solid " placeholder="Annual Year">
                        </div>


                        <div class="form-group">
                            <label class="fw-bold">Front Footage:</label>
                            <input type="number" name="front_footage" value="{{ $auction->get->front_footage }}" id="front_footage1" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar" placeholder="0.00" step="0.01">
                        </div>


                        <div class="form-group">
                            <label class="fw-bold">Lot Size Acres:</label>
                            <input type="number" name="lot_size_acres" value="{{ $auction->get->lot_size_acres }}" id="lot_size_acres"
                                class="form-control has-icon" data-icon="fa-solid">
                        </div>

                    </div>
                    {{-- 3 Jul 2023 For Vacant Land  --}}
                    {{-- 3 Jul 2023 For Vacant Land  --}}
                    <div class="wizard-step">
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
                                    <option value="{{ $water_view['name'] }}"
                                    {{ selected($water_view['name'], @$auction->get->water_view) }}
                                        data-target="{{ $water_view['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
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
                        <div class="form-group">
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
                        <div class="form-group">
                            <label class="fw-bold">Water Access:</label>
                            <select class="grid-picker" name="water_access[]" id="water_access"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($water_access as $water_access1)
                                    <option value="{{ $water_access1['name'] }}"
                                    {{ in_array($water_access1['name'], @$auction->get->water_access) ? 'selected ' : ''}}
                                        data-target="{{ $water_access1['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $water_access1['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 5 Jul2023 For Residential and Income --}}
                    {{-- 5 Jul2023 For Residential and Income --}}
                    <div class="wizard-step vacant_land business_oportunity_remove">
                        @php
                            $ownership_co_ops = [['name' => 'Condominium', 'target' => ''], ['name' => 'Fee Simple', 'target' => ''], ['name' => 'Fractional', 'target' => ''], ['name' => 'Other', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Ownership- Co-op:</label>
                            <select class="grid-picker" name="ownership_co_op" id="water_access"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($ownership_co_ops as $ownership_co_op)
                                    <option value="{{ $ownership_co_op['name'] }}"
                                    {{ selected($ownership_co_op['name'], @$auction->get->ownership_co_op) }}
                                        data-target="{{ $ownership_co_op['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $ownership_co_op['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        <div class="form-group">
                            <label class="fw-bold">Is the property located in a flood zone?</label>
                            <select class="grid-picker" name="is_in_flood_zone" id="is_in_flood_zone"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @php
                                    $yes_or_nos1 = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                                @endphp
                                @foreach ($yes_or_nos1 as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ selected($item['name'], @$auction->get->is_in_flood_zone) }}
                                        class="card flex-row" style="width:calc(25% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Flood Zone Code:</label>
                            <input type="text" name="flood_zone_code" value="{{ $auction->get->flood_zone_code }}" id="flood_zone_code" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step">
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
                                    <textarea name="pet_restrictions" id="pet_restrictions" value="{{ $auction->get->pet_restrictions }}" class="form-control" cols="30" rows="5">{{ $auction->get->pet_restrictions }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        <div class="form-group">
                            <label class="fw-bold">Tax Id(Parcel Number):</label>
                            <input type="number" name="tax_id" value="{{ $auction->get->tax_id }}" id="tax_id" class="form-control has-icon"
                                data-icon="fa-regular fa-circle-check" placeholder="Tax ID" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Tax Year:</label>
                            <input type="number" name="tax_year" id="tax_year" value="{{ $auction->get->tax_id }}" class="form-control has-icon"
                                data-icon="fa-regular fa-circle-check" placeholder="Annual Year" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Taxes (Annual Ammount):</label>
                            <input type="number" name="taxes_annual_ammount" value="{{ $auction->get->taxes_annual_ammount }}" id="taxes_annual_ammount"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar" placeholder="0.00"
                                step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Total Number of Parcels:</label>
                            <input type="number" name="total_number_of_parcels" value="{{ $auction->get->total_number_of_parcels }}" id="total_number_of_parcels"
                                class="form-control has-icon" data-icon="fa-solid">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Additional Tax ID's:</label>
                            <input type="number" name="additional_tax_id" value="{{ $auction->get->additional_tax_id }}" id="additional_tax_id"
                                class="form-control has-icon" data-icon="fa-solid">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Zoning:</label>
                            <input type="text" name="zoning" id="zoning" value="{{ $auction->get->zoning }}" class="form-control has-icon"
                                data-icon="fa-solid ">
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
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
                                    {{ selected($item['name'], @$auction->get->occupant_type) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row tenant_conditions ">
                            <div class="row for_residential_only">
                                <div class="form-group">
                                    <label class="fw-bold">Exiting Lease or Tenant:</label>
                                    <select class="grid-picker" name="exiting_lease_or_tenant"
                                        id="exiting_lease_or_tenant" style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                    {{ selected($item['name'], @$auction->get->exiting_lease_or_tenant) }}
                                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Monthly Rental Amount:</label>
                                    <input type="number" name="monthly_rental_ammount" value="{{ $auction->get->monthly_rental_ammount }}" id="monthly_rental_ammount"
                                        placeholder="" class="form-control has-icon" data-icon="fa-solid fa-dollar">
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">Days Notice to terminate if not Renewing:</label>
                                    <input type="text" name="days_notice_to_terminate" value="{{ $auction->get->days_notice_to_terminate }}"
                                        id="days_notice_to_terminate" placeholder="" class="form-control has-icon"
                                        data-icon="fa-solid ">
                                </div>
                                <div class="form-group">
                                    <label for="address">End Date of Lease Date:</label>
                                    <input type="date" name="end_of_lease_date" id="listing_date" value="{{ $auction->get->end_of_lease_date }}"
                                        class="form-control has-icon search_places"
                                        data-icon="fa-regular fa-calendar-days"
                                        data-msg-required="Please enter End Date of Lease Date" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 5 Jul 2023 For Income --}}
                    <div class="wizard-step vacant_land_remove business_oportunity_remove residential_remove">
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Unit Type</label>
                                <input type="text" name="unit_type" value="{{ $auction->get->unit_type }}" id="unit_type" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid">
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Sqt Ft Heated</label>
                                <input type="number" name="sqt_ft_heated" value="{{ $auction->get->sqt_ft_heated }}" id="sqt_ft_heated" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Number of Units</label>
                                <input type="number" name="number_of_units" value="{{ $auction->get->number_of_units }}" id="number_of_units" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Occupied</label>
                                <input type="number" name="occupied" value="{{ $auction->get->occupied }}" id="occupied" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Expected Rent</label>
                                <input type="number" name="expected_rent" value="{{ $auction->get->expected_rent }}" id="expected_rent" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Garage Spaces</label>
                                <input type="number" name="garage_spaces" value="{{ $auction->get->garage_spaces }}" id="garage_spaces" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Garage Attribute</label>
                                <input type="number" name="garage_attribute" value="{{ $auction->get->garage_attribute }}" id="garage_attribute" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-12">
                                <label class="fw-bold">Unit Type of Description:</label>
                                <textarea name="unit_type_of_description" value="{{ $auction->get->unit_type_of_description }}" id="unit_type_of_description" class="form-control" cols="30"
                                    rows="10"></textarea>
                            </div>

                            <div class="form-group col-md-4">
                                <label class="fw-bold">Annual Gross Income</label>
                                <input type="number" name="annual_gross_income" value="{{ $auction->get->annual_gross_income }}" id="garage_attribute"
                                    placeholder="" class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Total Monthly Rent</label>
                                <input type="number" name="total_monthly_rent" value="{{ $auction->get->total_monthly_rent }}" id="garage_attribute" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Total Monthly Expenses</label>
                                <input type="number" name="total_monthly_expenses" value="{{ $auction->get->total_monthly_expenses }}" id="garage_attribute"
                                    placeholder="" class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Lease Terms</label>
                                <input type="number" name="lease_terms" value="{{ $auction->get->lease_terms }}" id="garage_attribute" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Annual Net Income</label>
                                <input type="number" name="annual_net_income" value="{{ $auction->get->annual_net_income }}" id="garage_attribute" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Est Annual Market Income</label>
                                <input type="number" name="est_annual_market_income" value="{{ $auction->get->est_annual_market_income }}" id="garage_attribute"
                                    placeholder="" class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="fw-bold">Annual Expenses</label>
                                <input type="number" name="annual_expenses" value="{{ $auction->get->annual_expenses }}" id="garage_attribute" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid" required>
                            </div>
                            @php
                                $terms_of_leases = [['name' => 'Gross Lease', 'target' => ''], ['name' => 'Net Lease', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pass Throughts', 'target' => ''], ['name' => 'Purchase Options', 'target' => ''], ['name' => 'Renewal Option', 'target' => '']];
                            @endphp
                            <div class="form-group road_frontage_next_hide ">
                                <label class="fw-bold">Terms of Lease:</label>
                                <select class="grid-picker" name="terms_of_lease" id="terms_of_lease"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($terms_of_leases as $terms_of_lease)
                                        <option value="{{ $terms_of_lease['name'] }}"
                                    {{ selected($terms_of_lease['name'], @$auction->get->terms_of_lease) }}
                                            data-target="{{ $terms_of_lease['target'] }}" class="card flex-row"
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
                                    {{ selected($tenant_pay['name'], @$auction->get->tenant_pays) }}
                                            data-target="{{ $tenant_pay['target'] }}" class="card flex-row"
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
                                    {{ selected($financial_source['name'], @$auction->get->financial_sources) }}
                                            data-target="{{ $financial_source['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $financial_source['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Total Number of Units:</label>
                                <input type="number" name="total_number_of_units" value="{{ $auction->get->total_number_of_units }}" id="total_number_of_units"
                                    placeholder="" class="form-control has-icon" data-icon="fa-solid ">
                            </div>

                        </div>
                    </div>

                    {{-- 5 Jul 2023 For Income --}}
                    {{-- 5 Jul 2023 For Income --}}
                    <div class="wizard-step vacant_land_remove business_oportunity_remove">
                        <div class="form-group">
                            <label class="fw-bold">Can Property Be Leased:</label>
                            <select class="grid-picker" name="has_leasing" id="has_leasing"
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
                                    {{ selected($item['name'], @$auction->get->has_leasing) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
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
                                    {{ selected($item['name'], @$auction->get->has_lease_restriction) }}
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
                                    {{ selected($item['name'], @$auction->get->association_approval_required) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Minimum Lease Period:</label>
                            <input type="number" name="minimum_lease_period" value="{{ $auction->get->minimum_lease_period }}" id="minimum_lease_period"
                                class="form-control has-icon" data-icon="fa-solid " placeholder="Lease Period" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Maximum Time Per Year:</label>
                            <input type="number" name="minimum_lease_per_year" value="{{ $auction->get->minimum_lease_per_year }}" id="minimum_lease_period"
                                class="form-control has-icon" data-icon="fa-solid " placeholder="Time Per Year" required>
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
                                    {{ selected($item['name'], @$auction->get->years_of_ownership) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Number of Ownership Prior to Leasing Required:</label>
                            <select class="grid-picker" name="number_of_ownership" id="number_of_ownership"
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
                                    {{ selected($item['name'], @$auction->get->number_of_ownership) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Number of Ownership Years Prior to Lease:</label>
                            <select class="grid-picker" name="number_of_ownership_prior_lease"
                                id="number_of_ownership_prior_lease" style="justify-content: flex-start;" required>
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
                                    {{ selected($item['name'], @$auction->get->number_of_ownership_prior_lease) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 5 Jul 2023 For Income --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove">
                        @php
                            $acutual_or_projected = [['name' => 'Actual', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Projected', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Operating Expenses</label>
                            <input type="number" name="operating_expenses" value="{{ $auction->get->operating_expenses}}" id="operating_expenses"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar "
                                placeholder="Operating Expenses">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Net Operating Income:</label>
                            <input type="number" name="net_operating_income" value="{{ $auction->get->net_operating_income}}" id="net_operating_income"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                placeholder="Net Operating Income">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Annual Expenses:</label>
                            <input type="number" name="annual_expenses" value="{{ $auction->get->annual_expenses}}" id="annual_expenses"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                placeholder="Annual Expenses">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Annual TTL Schedule Income:</label>
                            <input type="number" name="annual_ttl_schedule_income" value="{{ $auction->get->annual_ttl_schedule_income}}" id="annual_ttl_schedule_income"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                placeholder="Annual TTL Schedule Income">
                        </div>
                        @php
                            $sale_includes = [['name' => 'Building(s) and Land', 'target' => ''], ['name' => 'Furniture/Fixtures', 'target' => ''], ['name' => 'Leases', 'target' => ''], ['name' => 'Other', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Number of Tenants:</label>
                            <input type="number" name="number_of_tenants" value="{{ $auction->get->number_of_tenants}}" id="number_of_tenants"
                                class="form-control has-icon" data-icon="fa-solid">
                        </div>
                        <div class="row ">
                            <div class="form-group">
                                <label class="fw-bold">Sale Includes</label>
                                <select class="grid-picker" name="sale_include" id="sale_include"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($sale_includes as $sale_include)
                                        <option value="{{ $sale_include['name'] }}"
                                    {{ selected($sale_include['name'], @$auction->get->sale_include) }}
                                            data-target="{{ $sale_include['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);">
                                            {{ $sale_include['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row ">
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
                                    {{ selected($item['name'], @$auction->get->net_operating_income_type) }}
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
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
                                    {{ selected($item['name'], @$auction->get->net_operating_income_type) }}
                                            class="card flex-row" style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step">
                        @php
                            $ownerships = [['name' => 'Condominium', 'target' => ''], ['name' => 'Corporation', 'target' => ''], ['name' => 'Franchise', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Partnership', 'target' => ''], ['name' => 'Sole Proprietor', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Ownership:</label>
                            <select class="grid-picker" name="ownership" id="ownership"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($ownerships as $ownership)
                                    <option value="{{ $ownership['name'] }}"
                                    {{ selected($ownership['name'], @$auction->get->ownership) }}
                                        data-target="{{ $ownership['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $ownership['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step business_oportunity_remove">
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
                                    {{ selected($item['name'], @$auction->get->has_hoa) }}
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
                                    {{ selected($hoa_fee_requirenment['name'], @$auction->get->hoa_fee_requirenment) }}
                                        data-target="{{ $hoa_fee_requirenment['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $hoa_fee_requirenment['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">HOA Fee:$</label>
                            <input type="number" name="hoa_fee" value="{{ $auction->get->hoa_fee }}" id="hoa_fee" class="form-control has-icon"
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
                                    {{ selected($hoa_payment_schedule['name'], @$auction->get->hoa_payment_schedule) }}
                                        data-target="{{ $hoa_payment_schedule['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $hoa_payment_schedule['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Condo Fee: $</label>
                            <input type="number" name="condo_fee" value="{{ $auction->get->condo_fee }}" id="condo_fee" class="form-control has-icon"
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
                                    {{ selected($condo_fee_schedule['name'], @$auction->get->condo_fee_schedule) }}
                                        data-target="{{ $condo_fee_schedule['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $condo_fee_schedule['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Other Fees: $</label>
                            <input type="number" name="other_fee" value="{{ $auction->get->other_fee }}" id="other_fee" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar " placeholder="Other Fee">
                        </div>


                        @php

                            $condo_fee_schedules = [['name' => 'Annually', 'target' => ''], ['name' => 'Monthly', 'target' => ''], ['name' => 'Quarterly', 'target' => ''], ['name' => 'Semi-Annually ', 'target' => '']];
                        @endphp
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Other Fee Schedule:</label>
                            <select class="grid-picker" name="other_fee_schedule" id="other_fee_schedule"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($condo_fee_schedules as $condo_fee_schedule)
                                    <option value="{{ $condo_fee_schedule['name'] }}"
                                    {{ selected($condo_fee_schedule['name'], @$auction->get->other_fee_schedule) }}
                                        data-target="{{ $condo_fee_schedule['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $condo_fee_schedule['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>


                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Additional Monthly Maintenance Fee: $</label>
                            <input type="number" value="{{ $auction->get->additional_monthly_maintenance_fee }}" name="additional_monthly_maintenance_fee"
                                id="additional_monthly_maintenance_fee" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar " placeholder="Maintenance Fee">
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Association Name/Contact Info: </label>
                            <input type="text" name="association_name" value="{{ $auction->get->association_name }}" id="assicuatuib_name"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar " placeholder="Association Name">
                        </div>

                        <div class="form-group hoas">
                            <label class="fw-bold">Master Association </label>
                            <select class="grid-picker" name="has_master_association"  id="has_master_association"
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
                                    {{ selected($item['name'], @$auction->get->has_master_association) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group master_association d-none">
                            <label class="fw-bold">Master Association Fee: </label>
                            <input type="number" name="master_association_fee" value="{{ $auction->get->master_association_fee }}" id="master_association_fee"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar " placeholder="Master Fee">
                        </div>
                        <div class="form-group master_association d-none">
                            <label class="fw-bold">Master Association Frequency Schedule: </label>
                            <select class="grid-picker" name="master_association_schedule"
                                id="master_association_schedule" style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($condo_fee_schedules as $condo_fee_schedule)
                                    <option value="{{ $condo_fee_schedule['name'] }}"
                                    {{ selected($condo_fee_schedule['name'], @$auction->get->master_association_schedule) }}
                                        data-target="{{ $condo_fee_schedule['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $condo_fee_schedule['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group master_association d-none">
                            <label class="fw-bold">Master Association Name: </label>
                            <input type="text" name="master_association_name" value="{{ $auction->get->master_association_name }}" id="master_association_name"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar " placeholder="HOA Fee">
                        </div>



                        <div class="form-group hoas">
                            <label class="fw-bold">Housing For Older Persons:</label>
                            <select class="grid-picker" name="housing_for_older_persons"
                                id="housing_for_older_persons" style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                    {{ selected($item['name'], @$auction->get->housing_for_older_persons) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step vacant_land_remove residential_and_income_remove">
                        @php
                            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Is Property in a Condo Environment?</label>
                            <select class="grid-picker" name="is_condo_enviornment" id="is_condo_enviornment"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    @php
                                        if ($item['name'] == 'Yes') {
                                            $target = '.condo_terms';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                    {{ selected($item['name'], @$auction->get->is_condo_enviornment) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group condo_terms row d-none">
                            <div class="form-group">
                                <label class="fw-bold">Condo Fee</label>
                                <input type="number" name="condo_fee" value="{{ $auction->get->condo_fee }}" id="condo_fee" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" required>
                            </div>
                            @php
                                $condo_fee_terms = [['name' => 'Annual', 'target' => ''], ['name' => 'Monthly', 'target' => ''], ['name' => 'Quarterly', 'target' => ''], ['name' => 'Semi Annual ', 'target' => '']];
                            @endphp

                            <div class="form-group">
                                <label class="fw-bold">Condo Fee Term:</label>
                                <select class="grid-picker" name="condo_fee_term" id="condo_fee_term"
                                    style="justify-content: flex-start;" required>
                                    <option value="">Select</option>
                                    @foreach ($condo_fee_terms as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ selected($item['name'], @$auction->get->condo_fee_term) }}
                                            class="card flex-column" style="width:calc(25% - 10px);"
                                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Association/Manager Contact Name</label>
                                <input type="text" name="association_contact_name" value="{{ $auction->get->association_contact_name }}" id="association_contact_name"
                                    class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                            </div>
                            <div class="form-group">
                                <label class="fw-bold"> Contact Information </label>
                                <input type="text" name="association_contact_information" value="{{ $auction->get->association_contact_information }}" id="contact_information"
                                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                            </div>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step">
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
                                    {{ in_array($community_feature['name'], @$auction->get->community_feature) ? ' selected' : '' }}
                                        data-target="{{ $community_feature['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $community_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Vacant Land --}}
                    <div class="wizard-step business_oportunity_remove">
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
                                    {{ in_array($association_amenitie['name'], @$auction->get->association_amenitie) ? 'selected' : ''}}
                                        data-target="{{ $association_amenitie['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $association_amenitie['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Vacant Land --}}
                    {{-- 3 Jul 2023 For Vacant Land --}}
                    <div class="wizard-step business_oportunity_remove">
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
                                    {{ in_array($fee_include['name'], @$auction->get->fee_include) ? 'selected' : '' }}
                                        data-target="{{ $fee_include['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $fee_include['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Amenities with Additional Fees:</label>
                            <input type="number" name="amenities_with_additional_fees" value="{{ $auction->get->amenities_with_additional_fees }}"
                                id="amenities_with_additional_fees" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar" placeholder="Annual Expenses" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Cost of Additional Amenities:</label>
                            <input type="number" name="cost_additional_amenities" value="{{ $auction->get->cost_additional_amenities }}" id="cost_additional_amenities"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                placeholder="Annual Expenses" required>
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Vacant Land --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold"> Description:</label>
                            <textarea name="description" id="description" class="form-control" cols="30" rows="10" required>{{ $auction->get->description ?? '' }}</textarea>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Description/Keywords:<small class="small">(Add keywords of the
                                    best
                                    property features) Users will be able to search keywords on the platform,
                                    comma-separated. </small></label>
                            <input type="text" name="keywords" id="keywords" value="{{ $auction->get->keywords }}"
                                placeholder="eg: House in Florida, Single family house for sale"
                                class="form-control has-icon" data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Driving Directions:</label>
                            <input type="text" name="driving_directions" id="keywords" value="{{ $auction->get->driving_directions }}"
                                placeholder="eg: House in Florida, Single family house for sale"
                                class="form-control has-icon" data-icon="fa-solid fa-tag">
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    {{-- 3 Jul 2023 For Business Opportunity --}}
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Is the Seller actively seeking to purchase another property?
                            </label>
                            <select class="grid-picker" name="looking_other_property" id="looking_other_property"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    {{ selected($item['name'], @$auction->get->looking_other_property) }}
                                        class="card flex-row" style="width:calc(50% - 10px);"
                                        data-icon="<i class='{{ $item['icon'] }}'></i>">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Link to the listing on the Bid Your Offer platform:</label>
                            <input type="url" name="listing_link" value="{{ $auction->get->listing_link }}" id="listing_link" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Title Company Info</label>
                            <input type="text" name="title_company_info" value="{{ $auction->get->title_company_info }}" id="title_company_info" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Agent Info</label>
                            <input type="text" name="agent_info" value="{{ $auction->get->agent_info }}" id="agent_info" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Dual/Variable Compensation</label>
                            <select class="grid-picker" name="dual_variable_compensation"
                                id="dual_variable_compensation" style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                    {{ selected($item['name'], @$auction->get->dual_variable_compensation) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group ">
                            <label class="fw-bold">Property Picture</label>
                            <input type="file" name="property_picture" id="property_picture" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Property Video:</label>
                            <input type="file" name="visible_property_video" id="property_video" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">3D Tour:</label>
                            <input type="url" name="three_d_tour" value="{{ $auction->get->three_d_tour ?? '' }}" id="three_d_tour" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Upload File</label>
                            <input type="file" name="visible_upload_file[]" id="upload_file" placeholder=""
                                class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Lot Plan:</label>
                            <input type="file" name="visible_note" id="visible_note" class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Photos:</label>
                            <input type="file" name="visible_photos[]" accept="image/*" id="visible_photos"
                                multiple class="form-control">
                        </div>
                    </div>
                    {{-- 3 Jul 2023 For Business Opportunity --}}
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

            // alert(p);
            if (p == "Vacant Land") {
                // alert('ok');
                $('.property_style_next_hide').addClass('d-none');
                $('.road_frontage_next_hide').addClass('d-none');
                $('.hide_vacant').removeClass('d-none');
                $('.hide_vacant').removeClass('d-none');
                $('.vacant_land_remove').remove();

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

            } else if (p == "Income Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').show();
                $('.commercial-length').hide();
                $('.residential_hide').removeClass('d-none');
                $('.residential_remove').remove();
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
        $('#property_picture').click(function() {
            $('.wizard-step-next').hide();
            $('.wizard-step-finish').show();
        });
        $('#property_picture1').click(function() {
            $('.wizard-step-next').hide();
            $('.wizard-step-finish').show();
        });

        $(".autobid_price").hide();
        // show auto biding price field
        $("#auto_bid0").click(function() {
            $(".autobid_price").hide();
        })
        $("#auto_bid1").click(function() {
            $(".autobid_price").show();
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
