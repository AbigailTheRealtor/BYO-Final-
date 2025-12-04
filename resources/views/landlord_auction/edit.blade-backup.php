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
                <form class="p-4 pt-0 mainform" action="{{ route('agent.landlord.auction.update', @$auction->id) }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="wizard-step">
                        <div class="form-group">
                            <label for="address">Address:</label>
                            <input type="text" name="address" value="{{ @$auction->address }} " data-type="address"
                                placeholder="199 Market St, San Francisco, CA" id="address"
                                class="form-control has-icon search_places" data-icon="fa-solid fa-location-dot"
                                data-msg-required="Please enter address" required>
                        </div>
                        <div class="form-group">
                            <label for="address">County:</label>
                            <input type="text" name="county" value="{{ @$auction->county }}" placeholder="County"
                                id="county" class="form-control has-icon search_places" data-icon="fa-regular fa-flag"
                                data-msg-required="Please enter county" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Listing Date:</label>
                            <input type="date" name="listing_date"
                                value="{{ Carbon\Carbon::parse(@$auction->listing_date)->format('Y-m-d') }}"
                                id="listing_date" class="form-control has-icon search_places"
                                data-icon="fa-regular fa-calendar-days" data-msg-required="Please enter listing date"
                                required>
                        </div>

                        <div class="form-group">
                            <label for="address">Expiration Date:</label>
                            <input type="date" name="expiration_date"
                                value="{{ Carbon\Carbon::parse(@$auction->expiration_date)->format('Y-m-d') }}"
                                id="expiration_date" class="form-control has-icon search_places"
                                data-icon="fa-regular fa-calendar-days" data-msg-required="Please enter expiration date"
                                required>
                        </div>
                        {{-- <div class="form-group">
                            <select class="grid-picker" name="test" required>
                                <option value="">Select</option>
                                <option value="a" class="card flex-column fw-bold" style="width:calc(33% - 10px);"
                                    data-icon='<i class="fa-regular fa-flag"></i>'>A
                                </option>
                            </select>
                        </div> --}}
                    </div>
                    <div class="wizard-step">
                        {{-- 19 June 2023 for Residential --}}
                        @php
                            $yes_or_nos12 = [['name' => 'Full Service', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Limited Service', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                        @endphp
                        <div class="form-group" id="pool">
                            <label class="fw-bold">Listing Service Type:</label>
                            <select class="grid-picker" name="listing_service_type" id="listing_service_type"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos12 as $yes_or_no)
                                    <option value="{{ $yes_or_no['name'] }}" data-target="{{ $yes_or_no['target'] }}"
                                        {{ selected($yes_or_no['name'], @$auction->get->listing_service_type) }}
                                        class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                        {{ $yes_or_no['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 19 June 2023 for Residential --}}
                    {{-- 19 June 2023 for Residential --}}
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

                    {{-- 19 June 2023 for Residential --}}

                    {{-- 19 June 2023 for Residential --}}
                    <div class="wizard-step">
                        @php
                            $lease_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '1 year', 'target' => ''], ['name' => '2 years', 'target' => ''], ['name' => '3-5 years', 'target' => ''], ['name' => '5+ years', 'target' => ''], ['name' => 'Month to Month', 'target' => ''], ['name' => 'Other', 'target' => '.custom_terms']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Lease Terms:</label>
                            <select class="grid-picker" name="lease_terms" id="lease_terms"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($lease_terms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        {{ selected($item['name'], @$auction->get->lease_terms) }}
                                        class="card flex-row fw-bold pt-0 pb-0" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $lease_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '1 year', 'target' => ''], ['name' => '2 years', 'target' => ''], ['name' => '3-5 years', 'target' => ''], ['name' => '5+ years', 'target' => ''], ['name' => 'Month to Month', 'target' => ''], ['name' => 'Other', 'target' => '.custom_terms']];
                        @endphp
                        <div class="form-group custom_terms d-none">
                            <label class="fw-bold" for="custom_terms">Offered Lease Terms:</label>
                            <input type="text" name="offered_custom_lease_terms"
                                value="{{ $auction->get->offered_custom_lease_terms ?? '' }}" id="custom_terms"
                                placeholder="Enter Lease Terms" class="form-control has-icon"
                                data-msg-required="Please enter Custom Lease terms" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Start date:</label>
                            <input type="date" name="start_date" id="start_date"
                                value="{{ $auction->get->start_date ?? '' }}" class="form-control has-icon"
                                data-icon="fa-solid fa-calendar">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">End Date:</label>
                            <input type="date" name="end_date" value="{{ $auction->get->end_date ?? '' }}"
                                id="end_date" class="form-control has-icon" data-icon="fa-solid fa-calendar">
                        </div>
                        @php
                            $yes_or_nos1 = [
                                ['name' => 'First, Last, and Security', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'First, Last, Security Deposit, Exit Cleaning Fee, & Application Fee', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'First, Last, Security Deposit, Pet Deposit (if applicable), Exit Cleaning Fee, & Application Fee', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'First, Last, Security Deposit, Exit Cleaning Fee, Application Fee, Vacation Tax', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'First, Security Deposit, Exit Cleaning Fee, Application Fee, & Vacation Tax', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'First, Security, Exit Cleaning Fee & Application', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'First, Security, Application', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Other- Custom Input', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                            ];

                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">What is required at move-in? (Please enter the amount for each required
                                fee at move-in)</label>
                            <select class="grid-picker" name="required_at_move_in" id="required_at_move_in"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos1 as $item)
                                    @php
                                        if ($item['name'] == 'Other- Custom Input') {
                                            $target = '.custom_input';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        {{ selected($item['name'], @$auction->get->required_at_move_in) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_input">
                            <label class="fw-bold">Other- Custom Input</label>
                            <input type="number" name="required_at_move_in_custom"
                                value="{{ $auction->get->required_at_move_in_custom ?? '' }}" id="other_custom_input"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar">
                        </div>

                    </div>

                    {{-- 19 June 2023 for Residential --}}
                    {{-- 19 June 2023 for Residential --}}
                    <div class="wizard-step">
                        <div class="form-group ">
                            <label class="fw-bold">Application Link:</label>
                            <input type="text" name="application_link" value="{{ $auction->get->application_link }}"
                                id="application_link" class="form-control has-icon" data-icon="fa-solid fa-dollar">
                        </div>
                    </div>
                    {{-- 19 June 2023 for Residential --}}
                    {{-- 19 June 2023 for Residential --}}
                    <div class="wizard-step">
                        <h4>Landlord Prescreening Terms:</h4>
                        @php
                            $lease_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '1 year', 'target' => ''], ['name' => '2 years', 'target' => ''], ['name' => '3-5 years', 'target' => ''], ['name' => '5+ years', 'target' => ''], ['name' => 'Other', 'target' => '.custom_terms']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Lease Terms:</label>
                            <select class="grid-picker" name="offer_lease_term" id="offer_lease_term"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>

                                @foreach ($lease_terms as $lease_term)
                                    @php
                                        if ($item['name'] == 'Other- Custom Input') {
                                            $target = '.custom_lease_term';
                                        } else {
                                            $target = '';
                                        }
                                    @endphp
                                    <option value="{{ $lease_term['name'] }}" data-target="{{ $lease_term['target'] }}"
                                        {{ selected($item['name'], @$auction->get->offer_lease_term) }}
                                        class="card flex-row fw-bold pt-0 pb-0" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                        {{ $lease_term['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_lease_term d-none">
                            <label class="fw-bold" for="custom_terms">Lease Terms:</label>
                            <input type="text" name="custom_lease_terms"
                                value="{{ $auction->get->custom_lease_terms ?? '' }}" id="custom_lease_terms"
                                placeholder="Enter Lease Terms" class="form-control has-icon"
                                data-msg-required="Please enter Custom Lease terms" required>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="offer_move_date">Offered Move in Date:</label>
                            <input type="date" name="offer_move_date" value="{{ $auction->get->offer_move_date }}"
                                placeholder="" id="offer_move_date" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-calendar-days">
                        </div>

                        @php
                            $offer_occupants_accept = [['name' => '1', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8+', 'target' => ''], ['name' => 'Other', 'target' => '.custom_occupants']];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">How many occupants will landlord accept?</label>
                        <select class="grid-picker" name="offer_allowed_occupants" id="offer_allowed_occupants"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($offer_occupants_accept as $oca)
                                    <option value="{{ $oca['name'] }}" data-target="{{ $oca['target'] }}"
                                        {{ selected($oca['name'], @$auction->get->offer_allowed_occupants) }}
                                        class="card flex-row fw-bold pt-0 pb-0" style="width:calc(10% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                        {{ $oca['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_occupants d-none">
                            <label class="fw-bold" for="custom_occupants">How many occupants will landlord accept?</label>
                            <input type="text" name="custom_occupants" value="{{ $auction->get->custom_occupants }}"
                                placeholder="" id="custom_occupants" class="form-control has-icon hide_arrow"
                                data-icon="fa-regular fa-check-circle">
                        </div>

                        @php
                            $offer_allow_pets = [['name' => 'Yes Dog', 'target' => ''], ['name' => 'Yes Cat', 'target' => ''], ['name' => 'No', 'target' => ''], ['name' => 'Other', 'target' => '.custom_allow_pets']];
                        @endphp

                        {{-- Nidsar Changing --}}
                        <div class="form-group" id="acceptPet">
                            <label class="fw-bold">Will landlord accept a pet?</label>
                            <select class="grid-picker" name="offer_allow_pets[]" id="offer_allow_pets"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($offer_allow_pets as $oap)
                                    <option value="{{ $oap['name'] }}" data-target="{{ $oap['target'] }}"
                                        {{ selected($oap['name'], @$auction->get->offer_allow_pets) }}
                                        class="card flex-row fw-bold pt-0 pb-0" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                        {{ $oap['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_allow_pets d-none">
                            <label class="fw-bold" for="custom_allow_pets">Will landlord accept a pet?</label>
                            <input type="text" name="custom_allow_pets"
                                value="{{ $auction->get->custom_allow_pets }}" id="custom_allow_pets"
                                class="form-control has-icon hide_arrow" data-icon="fa-regular fa-check-circle">
                        </div>

                        @php
                            $offer_number_of_pets_allowed = ['1', '2', '3', '4', '5', '6', '7', '8+', 'None'];
                        @endphp
                               <div class="form-group" id="manyPets">
                                <label class="fw-bold">How many pets will landlord accept?</label>
                                <select class="grid-picker" name="offer_number_of_pets_allowed"
                                    id="offer_number_of_pets_allowed" style="justify-content: flex-start;">
                                    <option value="">Select</option>
                        @foreach ($offer_number_of_pets_allowed as $onopa)
                            <option value="{{ $onopa }}" data-target=""
                                {{ selected($onopa, @$auction->get->offer_number_of_pets_allowed) }}
                                class="card flex-row fw-bold pt-0 pb-0" style="width:calc(10% - 10px);"
                                data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                {{ $onopa }}
                            </option>
                        @endforeach
                    </select>
                </div>


                        @php
                            $offer_min_credit_ratings = ['Poor', 'Fair', 'Good', 'Excellent'];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">Minimum credit rating landlord will accept:</label>
                            <select class="grid-picker" name="offer_min_credit_ratings" id="offer_min_credit_ratings"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($offer_min_credit_ratings as $omcr)
                                <option value="{{ $omcr }}" data-target=""
                                    {{ selected($omcr, @$auction->get->offer_min_credit_ratings) }}
                                    class="card flex-row fw-bold pt-0 pb-0" style="width:calc(33.3% - 10px);"
                                    data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                    {{ $omcr }}
                                </option>
                            @endforeach

                            </select>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="offer_min_net_income">To qualify for the rental, what is the
                                minimum net income that a household must earn?</label>
                            <input type="number" name="offer_min_net_income"
                                value="{{ $auction->get->offer_min_net_income }}" placeholder="0.00"
                                id="offer_min_net_income" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-dollar">
                        </div>

                        @php
                            $yes_or_no_depends = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'], ['name' => 'Depends on the circumstance', 'target' => '', 'icon' => 'fa-solid fa-shield-halved']];
                        @endphp

                        <div class="form-group">
                            <label class="fw-bold">Will landlord accept a tenant with a prior eviction?</label>
                            <select class="grid-picker" name="offer_prior_eviction" id="offer_prior_eviction"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($yes_or_no_depends as $yes_or_no)
                                    <option value="{{ $yes_or_no['name'] }}" data-target=""
                                        {{ selected($yes_or_no['name'], @$auction->get->offer_prior_eviction) }}
                                        class="card flex-row fw-bold pt-0 pb-0" style="width:calc(30% - 10px);"
                                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                        {{ $yes_or_no['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Will landlord accept a tenant with a prior felony?</label>
                            <select class="grid-picker" name="offer_prior_felony" id="offer_prior_felony"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($yes_or_no_depends as $yes_or_no)
                                    <option value="{{ $yes_or_no['name'] }}" data-target=""
                                        {{ selected($yes_or_no['name'], @$auction->get->offer_prior_felony) }}
                                        class="card flex-row fw-bold pt-0 pb-0" style="width:calc(30% - 10px);"
                                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                        {{ $yes_or_no['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    {{-- 19 June 2023 for Residential --}}
                    <div class="wizard-step">
                        @php
                            $property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Property Types:</label>
                            <select class="grid-picker" name="property_type" id="property_type"
                                onchange="changePropertyType(this.value);" required>
                                <option value="">Select</option>
                                @foreach ($property_types as $item)
                                    <option value="{{ $item['name'] }}" class="card flex-column"
                                        {{ selected($item['name'], @$auction->get->property_type) }}
                                        style="width:calc(24% - 10px);" data-icon='<i class="fa-solid fa-hotel"></i>'>
                                        {{ $item['name'] }}
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
                                        // Changing nisar
                                        // ['name' => 'Five or More (Residential units)', 'class' => 'commercial-length'],
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
                                            class="card flex-row {{ $item['class'] }}" style="width:calc(33.33% - 10px);"
                                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Nisar Changing --}}
                    @php
                        $bedrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '', 'name' => 'Commercial'], ['target' => '.other_bedrooms', 'name' => 'Other']];
                        $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.other_bathrooms', 'name' => 'Other']];
                    @endphp
                    <div class="wizard-step" id="bedrooms">
                        <div class="form-group">
                            <label class="fw-bold">Bedrooms:</label>
                            <select class="grid-picker" name="bedrooms" id="bedrooms" style="justify-content: center;"
                                required>
                                <option value="">Select</option>
                                @foreach ($bedrooms as $bedroom)
                                    <option value="{{ $bedroom['name'] }}" data-target="{{ $bedroom['target'] }}"
                                        {{ selected($bedroom['name'], @$auction->get->bedrooms) }}
                                        class="card flex-column fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-solid fa-bed"></i>'>
                                        {{ $bedroom['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group other_bedrooms d-none">
                            <label class="fw-bold" for="other_bedrooms">Bedrooms:</label>
                            <input type="text" value="{{ $auction->get->other_bedrooms }}" name="other_bedrooms"
                                id="other_bedrooms" placeholder="Enter Number of Bedrooms" class="form-control has-icon"
                                data-icon="fa-solid fa-bed" data-msg-required="Please enter Custom Bedrooms" required>
                        </div>
                    </div>
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Bathrooms:</label>
                            <select class="grid-picker" name="bathrooms" id="bathrooms" style="justify-content: center;"
                                required>
                                <option value="">Select</option>
                                @foreach ($bathrooms as $bathroom)
                                    <option value="{{ $bathroom['name'] }}" data-target="{{ $bathroom['target'] }}"
                                        {{ selected($bathroom['name'], @$auction->get->bathrooms) }}
                                        class="card flex-column fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-solid fa-bath"></i>'>
                                        {{ $bathroom['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group other_bathrooms d-none">
                            <label class="fw-bold" for="other_bathrooms">Bathrooms:</label>
                            <input type="text" name="other_bathrooms" value="{{ $auction->get->other_bathrooms }}"
                                id="other_bathrooms" placeholder="Enter number of Bathrooms"
                                class="form-control has-icon" data-icon="fa-solid fa-bath"
                                data-msg-required="Please enter Custom Bathrooms" required>
                        </div>
                    </div>

                    {{-- Nisar Changing End --}}

                    {{-- 19 June 2023 for Residential --}}
                    <div class="wizard-step">


                        <div class="form-group">
                            <label for="heated_sqft">Heated Sqft:</label>
                            <input type="number" name="heated_sqft" value="{{ $auction->get->heated_sqft }}"
                                placeholder="Heated Sqft" id="heated_sqft" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-ruler-combined" data-msg-required="Please enter Heated Sqft"
                                required>
                        </div>
                        {{-- 21 June 2023 for Commercial --}}
                        <div class="form-group commercial_show">
                            <label for="heated_sqft"> Net Leasable Sqft:</label>
                            <input type="number" name="net_leasable_sqft"
                                value="{{ $auction->get->net_leasable_sqft ?? ''}}" id="net_leasable_sqft"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                                data-msg-required="Please enter Heated Sqft" required>
                        </div>
                        {{-- 21 June 2023 for Commercial --}}
                        <div class="form-group">
                            <label for="sqft_total">Sqft:</label>
                            <input type="number" name="sqft" value="{{ $auction->get->sqft ?? '' }}" placeholder="Sqft"
                                id="sqft" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-ruler-horizontal" data-msg-required="Please enter Sqft Total"
                                required>
                        </div>
                        <div class="form-group">
                            <label for="sqft_total">Sqft Total:</label>
                            <input type="number" name="sqft_total" value="{{ $auction->get->sqft_total }}"
                                placeholder="Sqft Total" id="sqft_total" class="form-control has-icon hide_arrow"
                                data-icon="fa-solid fa-ruler-horizontal" data-msg-required="Please enter Sqft Total"
                                required>
                        </div>
                        @php
                            $heated_sources = [['target' => '', 'name' => 'Appraisal'], ['target' => '', 'name' => 'Building'], ['target' => '', 'name' => 'Measured'], ['target' => '', 'name' => 'Owner Provided'], ['target' => '', 'name' => 'Public Records']];

                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Heated Source:</label>
                            <select class="grid-picker" name="heated_source" id="bedrooms"
                                style="justify-content: center;" required>
                                <option value="">Select</option>
                                @foreach ($heated_sources as $heated_source)
                                    <option value="{{ $heated_source['name'] }}"
                                        {{ selected($heated_source['name'], @$auction->get->heated_source) }}
                                        data-target="{{ $heated_source['target'] }}" class="card flex-column fw-bold"
                                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-solid fa-bed"></i>'>
                                        {{ $heated_source['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

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
                                        {{ selected($total_acreage['name'], @$auction->get->total_acreage) }}
                                        data-target="{{ $total_acreage['target'] }}" class="card flex-column"
                                        style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-solid fa-check-circle"></i>'>
                                        {{ $total_acreage['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Lot Size:</label>
                            <input type="number" name="lot_size" value="{{ $auction->get->lot_size }}" id="lot_size"
                                class="form-control has-icon" data-icon="fa-solid " placeholder="Tax ID" required>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Legal Subdivision Name:</label>
                            <input type="text" name="legal_subdivision_name"
                                value="{{ $auction->get->legal_subdivision_name ?? '' }}" id="legal_subdivision_name"
                                class="form-control has-icon" data-icon="fa-solid " placeholder="Tax ID">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Tax ID:</label>
                            <input type="number" name="tax_id" value="{{ $auction->get->tax_id ?? '' }}" id="tax_id"
                                class="form-control has-icon" data-icon="fa-solid " placeholder="Tax ID">
                        </div>
                    </div>
                    {{-- 19 June 2023 for Residential --}}
                    {{-- 19 June 2023 for Residential --}}
                    <div class="wizard-step residential_remove">
                        @php
                            $furnishings = [['name' => 'Furnished', 'target' => '', 'icon' => ''], ['name' => 'Optional', 'target' => '', 'icon' => ''], ['name' => 'Partial', 'target' => '', 'icon' => ''], ['name' => 'Turnkey', 'target' => '', 'icon' => ''], ['name' => 'Unfurnished', 'target' => '', 'icon' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Furnishings:</label>
                            <select class="grid-picker" name="furnishings" id="furnishings"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($furnishings as $furnishing)
                                    <option value="{{ $furnishing['name'] }}" data-target="{{ $furnishing['target'] }}"
                                        {{ selected($furnishing['name'], @$auction->get->furnishings) }}
                                        class="card flex-column fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $furnishing['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 20 June for Residential --}}
                    <div class="wizard-step">
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
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => ''],
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
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Appliances Included:</label>
                            <select class="grid-picker" name="appliances[]" id="appliances"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($appliances as $appliance)
                                    <option value="{{ $appliance['name'] }}" data-target="{{ $appliance['target'] }}"
                                        {{ selected($appliance['name'], @$auction->get->appliances) }}
                                        class="card flex-row fw-bold" style="width:calc(33.3% - 10px);">
                                        {{ $appliance['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @php
                            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                            $yes_or_nos_opt = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'], ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Fireplace:</label>
                            <select class="grid-picker" name="carport" id="carport"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($yes_or_nos as $yes_or_no)
                                    <option value="{{ $yes_or_no['name'] }}" data-target="{{ $yes_or_no['target'] }}"
                                        {{ selected($yes_or_no['name'], @$auction->get->carport) }}
                                        class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                                        {{ $yes_or_no['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 20 June for Residential --}}
                    {{-- 20 June for Residential --}}
                    <div class="wizard-step" id="TenantPays">
                        @php
                            $tenant_pays = [['name' => 'Cable TV', 'target' => ''], ['name' => 'Electricity', 'target' => ''], ['name' => 'Gas', 'target' => ''], ['name' => 'Grounds Care', 'target' => ''], ['name' => 'Insurance', 'target' => ''], ['name' => 'Internet', 'target' => ''], ['name' => 'Laundry', 'target' => ''], ['name' => 'Management', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pest Control', 'target' => ''], ['name' => 'Pool Maintenance', 'target' => ''], ['name' => 'Recreational', 'target' => ''], ['name' => 'Repairs', 'target' => ''], ['name' => 'Security', 'target' => ''], ['name' => 'Sewer', 'target' => ''], ['name' => 'Taxes', 'target' => ''], ['name' => 'Telephone', 'target' => ''], ['name' => 'Trash Collection', 'target' => ''], ['name' => 'Water', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Tenant Pays:</label>
                            <select class="grid-picker" name="tenant_pays[]" multiple id="tenant_pays"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($tenant_pays as $tenant_pay)
                                    <option value="{{ $tenant_pay['name'] }}"
                                        data-target="{{ $tenant_pay['target'] }}"
                                        {{ selected($tenant_pay['name'], @$auction->get->tenant_pays) }}
                                        class="card flex-column fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $tenant_pay['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 21 June for Commercial --}}
                    <div class="wizard-step commercial_remove">
                        <div class="form-group">
                            <label class="fw-bold">Owner Pays</label>
                            <input type="text" name="owner_pays" value="{{ $auction->get->owner_pays ?? '' }}"
                                id="owner_pays" placeholder="" class="form-control has-icon"
                                data-icon="fa-solid fa-hotel">
                        </div>
                    </div>
                    {{-- 21 June 2023 for Commercial --}}
                    {{-- 21 June for Commercial --}}
                    <div class="wizard-step commercial_remove">
                        @php
                            $acessiblity_features = [
                                ['name' => 'Accessible Approach', 'target' => ''],
                                ['name' => 'Accessible Bedroom', 'target' => ''],
                                ['name' => 'Accessible Closets', 'target' => ''],
                                ['name' => 'Accessible Common Room', 'target' => ''],
                                ['name' => 'Accessible Doors', 'target' => ''],
                                ['name' => 'Accessible Electrical and Environmental Controls', 'target' => ''],
                                ['name' => 'Accessible Elevator Installed', 'target' => ''],
                                ['name' => 'Accessible Entrance', 'target' => ''],
                                ['name' => 'Accessible for Hearing-Impairment', 'target' => ''],
                                ['name' => 'Accessible Full Bath', 'target' => ''],
                                ['name' => 'Accessible Guest Bathroom', 'target' => ''],
                                ['name' => 'Accessible Hallway(s)', 'target' => ''],
                                ['name' => 'Accessible Kitchen', 'target' => ''],
                                ['name' => 'Accessible Kitchen Appliances', 'target' => ''],
                                ['name' => 'Accessible Living Area', 'target' => ''],
                                ['name' => 'Accessible Stairway', 'target' => ''],
                                ['name' => 'Accessible Washer/Dryer', 'target' => ''],
                                ['name' => 'Ceiling Track for Chair Lift', 'target' => ''],
                                ['name' => 'Central Living Area', 'target' => ''],
                                ['name' => 'Customized Wheelchair Accessible', 'target' => ''],
                                ['name' => 'Enhanced Accessible', 'target' => ''],
                                ['name' => 'Exterior Wheelchair Lift', 'target' => ''],
                                ['name' => 'Grip-Accessible Features', 'target' => ''],
                            ];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Accessibility Features:</label>
                            <select class="grid-picker" name="acessiblity_features[]" multiple id="acessiblity_features"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($acessiblity_features as $acessiblity_feature)
                                    <option value="{{ $acessiblity_feature['name'] }}"
                                        {{ selected($acessiblity_feature['name'], @$auction->get->acessiblity_features) }}
                                        data-target="{{ $acessiblity_feature['target'] }}"
                                        class="card flex-column fw-bold" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $acessiblity_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 21 June 2023 for Commercial --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step residential_remove">
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
                                        {{ selected($interior_feature['name'], @$auction->get->interior_features) }}
                                        data-target="{{ $interior_feature['target'] }}" class="card flex-column fw-bold"
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $interior_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step residential_remove">
                        <h4>Additional Rooms</h4>
                        @php
                            $additional_rooms = [['name' => 'Attic', 'target' => ''], ['name' => 'Bonus Room', 'target' => ''], ['name' => 'Breakfast Room Separate', 'target' => ''], ['name' => 'Den/Library/Office', 'target' => ''], ['name' => 'Family Room', 'target' => ''], ['name' => 'Florida Room', 'target' => ''], ['name' => 'Formal Dining Room Separate', 'target' => ''], ['name' => 'Formal Living Room Separate', 'target' => ''], ['name' => 'Great Room', 'target' => ''], ['name' => 'Inside Utility', 'target' => ''], ['name' => 'Interior In-Law Suite', 'target' => ''], ['name' => 'Loft', 'target' => ''], ['name' => 'Media Room', 'target' => ''], ['name' => 'Storage Rooms', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Additional Rooms:</label>
                            <select class="grid-picker" name="additional_rooms[]" multiple id="additional_rooms"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($additional_rooms as $additional_room)
                                    <option value="{{ $additional_room['name'] }}"
                                        {{ selected($additional_room['name'], @$auction->get->additional_rooms) }}
                                        data-target="{{ $additional_room['target'] }}" class="card flex-column fw-bold"
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $additional_room['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">

                        <div class="form-group">
                            <label class="fw-bold">Number Of Floors within the Property</label>
                            <input type="text" name="number_of_buildings"
                                value="{{ $auction->get->number_of_buildings ?? '' }}" id="number_of_buildings"
                                placeholder="" class="form-control has-icon" data-icon="fa-solid fa-hotel">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Floor Number</label>
                            <input type="number" name="floors_in_unit" value="{{ $auction->get->floors_in_unit ?? '' }}"
                                id="floors_in_unit" placeholder="" class="form-control has-icon"
                                data-icon="fa-solid fa-building">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Total Number of Floors in the Entire Building</label>
                            <input type="number" name="total_floors" value="{{ $auction->get->total_floors ?? '' }}"
                                id="total_floors" placeholder="" class="form-control has-icon"
                                data-icon="fa-solid fa-building">
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
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">
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
                                        {{ selected($floor_covering['name'], @$auction->get->floor_covering) }}
                                        data-target="{{ $floor_covering['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $floor_covering['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step residential_remove">
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
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($room_types as $room_type)
                                    <option value="{{ $room_type['name'] }}" data-target="{{ $room_type['target'] }}"
                                        {{ selected($room_type['name'], @$auction->get->floor_covering) }}
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
                            <select class="grid-picker" name="room_level[]" id="room_level"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($room_levels as $room_level)
                                    <option value="{{ $room_level['name'] }}"
                                        data-target="{{ $room_level['target'] }}"
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
                            <select class="grid-picker" name="bed_room_closest_type[]" id="bed_room_closest_type"
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
                            <select class="grid-picker" name="room_primary_floor_covering[]"
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
                                        {{ selected($room_feature['name'], @$auction->get->room_feature) }}
                                        data-target="{{ $room_feature['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $room_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
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
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">
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
                            <select class="grid-picker" name="water[]" id="water12"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($waters as $water)
                                    <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                                        {{ selected($water['name'], @$auction->get->water) }} class="card flex-row"
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
                            <select class="grid-picker" name="sewer[]" id="sewer"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($sewers1 as $sewer12)
                                    <option value="{{ $sewer12['name'] }}" data-target="{{ $sewer12['target'] }}"
                                        {{ selected($sewer12['name'], @$auction->get->sewer) }} class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $sewer12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>


                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step residential_remove">
                        <div class="form-group ">
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
                        <div class="form-group ">
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
                    </div>
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}

                    @php
                        $pools = [['name' => 'Private', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Community', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'], ['name' => 'None', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Pool:</label>
                            <select class="grid-picker" name="pool" id="pool"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($pools as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                        {{ selected($item['name'], @$auction->get->pool) }} class="card flex-row"
                                        style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">
                        @php
                            $front_exposures = [['name' => 'North', 'target' => ''], ['name' => 'East', 'target' => ''], ['name' => 'South', 'target' => ''], ['name' => 'West', 'target' => ''], ['name' => 'Southeast', 'target' => ''], ['name' => 'Northeast', 'target' => ''], ['name' => 'Southwest', 'target' => ''], ['name' => 'Northwest', 'target' => '']];
                        @endphp
                        <div class="form-group residential_and_income_hide">
                            <label class="fw-bold">Front Exposure:</label>
                            <select class="grid-picker" name="front_exposure" id="front_exposure"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($front_exposures as $front_exposure)
                                    <option value="{{ $front_exposure['name'] }}"
                                        {{ selected($front_exposure['name'], @$auction->get->front_exposure) }}
                                        data-target="{{ $front_exposure['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $front_exposure['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">

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
                                        {{ selected($foundation['name'], @$auction->get->foundation) }}
                                        data-target="{{ $foundation['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $foundation['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">

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
                                        {{ selected($exterior_construction['name'], @$auction->get->exterior_construction) }}
                                        data-target="{{ $exterior_construction['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $exterior_construction['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">
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
                                        {{ selected($exterior_feature['name'], @$auction->get->exterior_feature) }}
                                        data-target="{{ $exterior_feature['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $exterior_feature['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 20 June 2023 for Residential --}}
                    {{-- 21 June 2023 for Commercial --}}
                    <div class="wizard-step commercial_remove">
                        @php
                            $road_frontages = [['name' => 'Access Road', 'target' => ''], ['name' => 'Alley', 'target' => ''], ['name' => 'Business District', 'target' => ''], ['name' => 'City Street', 'target' => ''], ['name' => 'County Road ', 'target' => ''], ['name' => 'Divided Highway', 'target' => ''], ['name' => 'Easement', 'target' => ''], ['name' => 'Highway', 'target' => ''], ['name' => 'Interchange', 'target' => ''], ['name' => 'Interstate', 'target' => ''], ['name' => 'Main Thoroughfare', 'target' => ''], ['name' => 'Private Road', 'target' => ''], ['name' => 'Rail', 'target' => ''], ['name' => 'State Road', 'target' => ''], ['name' => 'Turn Lanes', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'None', 'target' => '']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Road Frontage:</label>
                            <select class="grid-picker" name="road_frontage[]" id="road_frontage"
                                style="justify-content: flex-start;" multiple>
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
                    {{-- 21 June 2023 for Commercial --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">
                        @php
                            $road_surface_types = [['name' => 'Asphalt', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Chip And Seal', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Dirt', 'target' => ''], ['name' => 'Gravel', 'target' => ''], ['name' => 'Limerock', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Paved', 'target' => ''], ['name' => 'Unimproved', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Road Surface Type:</label>
                            <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
                                style="justify-content: flex-start;">
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
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">
                        @php
                            $roofs = [['name' => 'Built-Up', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Membrane', 'target' => ''], ['name' => 'Metal', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Roof Over', 'target' => ''], ['name' => 'Shake', 'target' => ''], ['name' => 'Shingle', 'target' => ''], ['name' => 'Slate', 'target' => ''], ['name' => 'Tile', 'target' => '']];
                        @endphp
                        <div class="form-group ">
                            <label class="fw-bold">Roof:</label>
                            <select class="grid-picker" name="roof[]" id="roof"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($roofs as $roof)
                                    <option value="{{ $roof['name'] }}" data-target="{{ $roof['target'] }}"
                                        {{ selected($roof['name'], @$auction->get->roof) }} class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $roof['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 20 June 2023 for Residential --}}
                    {{-- 21 June 2023 for Commercial --}}
                    <div class="wizard-step commercial_remove">
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
                                        {{ selected($adjoining_propertie['name'], @$auction->get->adjoining_property) }}
                                        data-target="{{ $adjoining_propertie['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $adjoining_propertie['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 21 June 2023 for Commercial --}}
                    {{-- 21 June 2023 for Commercial --}}
                    <div class="wizard-step commercial_remove">
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
                        <div class="form-group  ">
                            <label class="fw-bold">Lot Features:</label>
                            <select class="grid-picker" name="lot_features[]" id="lot_features"
                                style="justify-content: flex-start;" multiple>
                                <option value="">Select</option>
                                @foreach ($lot_features1 as $lot_feature12)
                                    <option value="{{ $lot_feature12['name'] }}"
                                        {{ selected($lot_feature12['name'], @$auction->get->lot_features) }}
                                        data-target="{{ $lot_feature12['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $lot_feature12['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- 21 June 2023 for Commercial --}}
                    {{-- 21 June 2023 for Commercial --}}
                    <div class="wizard-step commercial_remove">
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

                    {{-- 21 June 2023 for Commercial --}}
                    {{-- 21 June 2023 for Commercial --}}
                    <div class="wizard-step commercial_remove">
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

                    {{-- 21 June 2023 for Commercial --}}
                    {{-- 21 June 2023 for Commercial --}}
                    <div class="wizard-step commercial_remove">
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
                                        {{ selected($item['name'], @$auction->get->has_condo_enviornment) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row has_condo d-none">
                            <div class="form-group  ">
                                <label class="fw-bold">Condo Fee:$</label>
                                <input type="number" name="condo_fee" value="{{ $auction->get->condo_fee ?? '' }}"
                                    id="condo_fee" class="form-control has-icon" data-icon="fa-solid fa-dollar "
                                    placeholder="HOA Fee">
                            </div>
                            @php
                                $condo_fee_terms = [['target' => '', 'name' => 'Annual, Monthly', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => ' Quarterly', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Semi Annual ', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Association/Manager Contact Name', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => ' Contact Information', 'icon' => 'fa-regular fa-circle-check']];
                            @endphp
                            <div class="form-group ">
                                <label class="fw-bold">Condo Fee Term:</label>
                                <select class="grid-picker" name="condo_fee_terms[]" id="parking_feature_garage"
                                    style="justify-content: flex-start;" required multiple>
                                    <option value="">Select</option>
                                    @foreach ($condo_fee_terms as $condo_fee_term)
                                        <option value="{{ $item['name'] }}"
                                            {{ selected($condo_fee_term['name'], @$auction->get->condo_fee_terms) }}
                                            data-target="{{ $condo_fee_term['target'] }}" class="card flex-row fw-bold"
                                            style="width:calc(33.3% - 10px);"
                                            data-icon='<i class="{{ $condo_fee_term['icon'] }}"></i>'>
                                            {{ $condo_fee_term['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- 21 June 2023 for Commercial --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step residential_remove">
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
                                        {{ selected($item['name'], @$auction->get->has_hoa) }} class="card flex-row"
                                        style="width:calc(33.3% - 10px);"
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
                            <label class="fw-bold">Association Approval Fee for Tenants</label>
                            <input type="number" name="association_approval_fee"
                                value="{{ $auction->get->association_approval_fee ?? '' }}" id="association_approval_fee"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar " placeholder="HOA Fee">
                        </div>

                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Parking Fee For Tenants</label>
                            <input type="number" name="parking_fee_for_tenants"
                                value="{{ $auction->get->parking_fee_for_tenants ?? '' }}" id="parking_fee_for_tenants"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar " placeholder="HOA Fee">
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Assoc Appr Req </label>
                            <select class="grid-picker" name="assoc_appr_req" id="assoc_appr_req"
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
                                        {{ selected($item['name'], @$auction->get->assoc_appr_req) }}
                                        class="card flex-row" style="width:calc(33.3% - 10px);"
                                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Association Security Deposit Fee for Tenant</label>
                            <input type="number" name="association_security_deposit"
                                value="{{ $auction->get->association_security_deposit ?? '' }}"
                                id="association_security_deposit" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar " placeholder="HOA Fee">
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Other Association Fees for Tenants</label>
                            <input type="number" name="other_association_fee"
                                value="{{ $auction->get->other_association_fee ?? '' }}" id="association_security_deposit"
                                class="form-control has-icon" data-icon="fa-solid fa-dollar " placeholder="HOA Fee">
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Other Association Fees for Tenants Frequently</label>
                            <input type="number" name="other_association_fee_frequently"
                                value="{{ $auction->get->other_association_fee_frequently ?? ''}}"
                                id="other_association_fee_frequently" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar " placeholder="HOA Fee">
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Association/Manager Contact Phone</label>
                            <input type="text" name="association_phone" id="association_phone"
                                value="{{ $auction->get->association_phone ?? '' }}" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar " placeholder="HOA Fee">
                        </div>
                        <div class="form-group hoas d-none">
                            <label class="fw-bold">Association/Manager Contact Name</label>
                            <input type="text" name="association_name" id="association_name"
                                value="{{ $auction->get->association_name ?? '' }}" class="form-control has-icon"
                                data-icon="fa-solid fa-dollar " placeholder="HOA Fee">
                        </div>



                    </div>

                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">
                        <h4>Community</h4>
                        <div class="form-group hoas residential_show">
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
                                        {{ selected($community_feature['name'], @$auction->get->community_feature) }}
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
                        <div class="form-group  residential_show">
                            <label class="fw-bold">Association Amenities:</label>
                            <select class="grid-picker" name="association_amenitie[]" id="association_amenitie"
                                style="justify-content: flex-start;" multiple required>
                                <option value="">Select</option>
                                @foreach ($association_amenities as $association_amenitie)
                                    <option value="{{ $association_amenitie['name'] }}"
                                        {{ selected($association_amenitie['name'], @$auction->get->association_amenitie) }}
                                        data-target="{{ $association_amenitie['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);">
                                        {{ $association_amenitie['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 21 June 2023 for Commercial --}}
                    <div class="wizard-step commercial_remove">

                        <div class="form-group">
                            <label class="fw-bold">Tax Id(Parcel Number):</label>
                            <input type="number" name="tax_id" value="{{ $auction->get->tax_id ?? ''}}"
                                id="tax_id" class="form-control has-icon" data-icon="fa-solid "
                                placeholder="Tax ID" required>
                        </div>


                        <div class="col-md-4">

                            <div class="form-group">
                                <label class="fw-bold">Tax Year:</label>
                                <input type="number" name="tax_year" value="{{ $auction->get->tax_year ?? '' }}"
                                    id="tax_year" class="form-control has-icon" data-icon="fa-solid "
                                    placeholder="Annual Year" required>
                            </div>

                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="fw-bold">Taxes (Annual Ammount):</label>
                                <input type="number" name="taxes_annual_ammount" id="taxes_annual_ammount"
                                    value="{{ $auction->get->taxes_annual_ammount ?? '' }}" class="form-control has-icon"
                                    data-icon="fa-solid fa-dollar" placeholder="0.00" step="0.01" required>
                            </div>
                        </div>


                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="fw-bold">Total Number of Parcels:</label>
                                <input type="number" name="total_number_of_parcels" id="total_number_of_parcels"
                                    value="{{ $auction->get->total_number_of_parcels ?? '' }}" class="form-control has-icon"
                                    data-icon="fa-solid">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="fw-bold">Additional Tax ID's:</label>
                                <input type="number" name="additional_tax_id" id="additional_tax_id"
                                    value="{{ $auction->get->additional_tax_id ?? '' }}" class="form-control has-icon"
                                    data-icon="fa-solid">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="fw-bold">Zoning:</label>
                                <input type="text" name="zoning" id="zoning"
                                    value="{{ $auction->get->zoning ?? '' }}" class="form-control has-icon"
                                    data-icon="fa-solid ">
                            </div>
                        </div>
                    </div>

                    {{-- 21 June 2023 for Commercial --}}
                    {{-- 20 June 2023 for Residential --}}
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold"> Description:</label>
                            <textarea name="description" id="description" class="form-control" cols="30" rows="10" required>{{ $auction->get->description }}</textarea>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Description/Keywords:<small class="small">(Add keywords of the
                                    best
                                    property features) Users will be able to search keywords on the platform,
                                    comma-separated. </small></label>
                            <input type="text" name="keywords" id="keywords"
                                value="{{ $auction->get->keywords ?? '' }}"
                                placeholder="eg: House in Florida, Single family house for sale"
                                class="form-control has-icon" data-icon="fa-solid fa-tag">
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Driving Directions:</label>
                            <input type="text" name="driving_directions" id="keywords"
                                value="{{ $auction->get->driving_directions ?? '' }}"
                                placeholder="eg: House in Florida, Single family house for sale"
                                class="form-control has-icon" data-icon="fa-solid fa-tag">
                        </div>
                    </div>
                    {{-- 20 June 2023 for Residential --}}
                    {{-- 20 June 2023 For Residential --}}
                    <div class="wizard-step">
                        <h4>Landlord’s Agent Info:</h4>

                        <div class="form-group">
                            <label class="fw-bold" for="first_name">First Name:</label>
                            <input type="text" name="first_name" placeholder="" id="first_name"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user-tie"
                                value="{{ Auth::user()->first_name }}">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="last_name">Last Name:</label>
                            <input type="text" name="last_name" placeholder="" id="last_name"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user-tie"
                                value="{{ Auth::user()->last_name }}">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="agent_phone">Phone Number:</label>
                            <input type="text" name="agent_phone" placeholder="" id="agent_phone"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-phone"
                                value="{{ Auth::user()->phone }}">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="agent_email">Email:</label>
                            <input type="text" name="agent_email" placeholder="" id="agent_email"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-envelope"
                                value="{{ Auth::user()->email }}">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="agent_brokerage">Brokerage:</label>
                            <input type="text" name="agent_brokerage" placeholder="" id="agent_brokerage"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-circle-dollar-to-slot"
                                value="{{ Auth::user()->brokerage }}">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="agent_license_no">Real Estate License #:</label>
                            <input type="text" name="agent_license_no" placeholder="" id="agent_license_no"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-card"
                                value="{{ Auth::user()->license_no }}">
                        </div>

                        {{-- <div class="form-group">
                            <label class="fw-bold" for="agent_mls_id">MLS ID #: (if listed on the MLS)</label>
                            <input type="text" name="agent_mls_id" placeholder="" id="agent_mls_id"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-card-clip"
                                value="{{ Auth::user()->mls_id }}">
                        </div> --}}

                    </div>
                    {{-- 20 June 2023 For Residential --}}
                    {{-- 20 June 2023 For Residential --}}
                    <div class="wizard-step">
                        <div class="row align-items-end">
                            <div class="form-group ">
                                <label class="fw-bold">Property Picture</label>
                                <input type="file" name="visible_property_picture" id="property_picture"
                                    placeholder="" class="form-control has-icon" data-icon="fa-solid fa-link">
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">Property Video:</label>
                                <input type="file" name="visible_property_video" id="property_video"
                                    placeholder="" class="form-control has-icon" data-icon="fa-solid fa-link">
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">3D Tour:</label>
                                <input type="url" name="three_d_tour" value="{{ $auction->get->three_d_tour ?? '' }}"
                                    id="three_d_tour" placeholder="" class="form-control has-icon"
                                    data-icon="fa-solid fa-link">
                            </div>
                            <div class="form-group">
                                <label class="fw-bold">Upload File</label>
                                <input type="file" name="visible_upload_file[]" id="upload_file" placeholder=""
                                    class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                            </div>



                            <div class="form-group">
                                <label class="fw-bold">Floor Plan:</label>
                                <input type="file" name="visible_note" id="visible_note" class="form-control">
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">Photos:</label>
                                <input type="file" name="visible_photos[]" accept="image/*" id="visible_photos"
                                    multiple class="form-control">
                            </div>
                        </div>
                    </div>
                    {{-- 20 June 2023 For Residential --}}
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

        // Nisar Changing
        Filevalidation = () => {

            var txt = "";
            const fi = document.getElementById('file');
            // Check if any file is selected.
            if (fi.files.length > 0) {
                for (const i = 0; i <= fi.files.length - 1; i++) {

                    const fsize = fi.files.item(i).size;
                    const file = Math.round((fsize / 1024));
                    // The size of the file.
                    if (file >= 102400) {
                        // alert(
                        //     "File too Big, please select a file less than 2mb");
                        txt = "File too Big, please select a file less than 100mb";
                        document.getElementById("demo").innerHTML = txt;
                        // var myText = "File too Big, please select a file less than 2mb";
                    } else if (file < 102400) {
                        txt = "";
                        document.getElementById("demo").innerHTML = txt;
                    } else {
                        document.getElementById('size').innerHTML = '<b>' +
                            file + '</b> KB';
                    }
                }
            }
        }
        // Nisar Changing End
    </script>
    <script>
        function changePropertyType(p) {
            if (p == "Residential Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').show();
                $('.income-length').hide();
                $('.commercial-length').hide();
                $('.residential_show').removeClass('d-none');
                $('.commercial_show').addClass('d-none');
                $('.commercial_remove').remove();

                // nisar changing
                $('#leasable-sqft').remove();
                $('#price').remove();
                $('#terms').remove();
                $('#TenantPays').remove();
                $('#OwnerPays').remove();


            } else if (p == "Commercial Property") {
                $('.property_items').val("");
                $('.property_items').parent().children('.option-container').removeClass('active');
                $('.residential-length').hide();
                $('.income-length').hide();
                $('.commercial-length').show();
                $('.residential_show').addClass('d-none');
                $('.commercial_show').removeClass('d-none');
                $('.residential_remove').remove();


                // nisar changing
                $('#price').show();
                $('#bedrooms').remove();
                $('#commercial').remove();
                $('#furnishings').remove();
                $('#pool').remove();
                $('#priceSqft').remove();
                $('#RentIncludes').remove();
                $('#acceptPet').remove();
                $('#manyPets').remove();


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
        //water view changing by waqas


        $(document).on('change', '#has_water_view', function() {

            var selectedOptionWater = $(this).val();
            // alert(selectedOptionWater);


            if (selectedOptionWater == 'No') {
                $('#water_show').hide();
                $('#water_extras_show').hide();
            }
            if (selectedOptionWater == 'Yes') {
                $('#water_show').show();
                $('#water_extras_show').show();
            }

        });

        //water view changing by waqas


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
