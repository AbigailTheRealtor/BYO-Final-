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
        $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
    @endphp
    <div class="container pt-5 pb-5">
        <h4 class="title">
            {{ $title }}
        </h4>
        <div class="card">
            <div class="row">
                <div class="col-12 p-4">
                    <form class="p-4 pt-0 validate mainform" action="{{ route('agent.service.auction.update') }}"
                        method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="id" value="{{ $auction->id }}">
                        <div class="container">

                            <div class="wizard-steps-progress">
                                <div class="steps-progress-percent"></div>
                            </div>
                            @php
                                $service_types = [['name' => 'Flat Fee Service', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Referral agent Service', 'target' => '.referral_fee', 'icon' => 'fa-regular fa-circle-check']];
                            @endphp
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">Service Type:</label>
                                    <select class="grid-picker" name="service_type" id="service_type"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($service_types as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                {{ selected($item['name'], @$auction->get->service_type) }}
                                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                @php
                                    $referral_fee = [['target' => '', 'name' => '20%'], ['target' => '', 'name' => '25%'], ['target' => '', 'name' => '30%'], ['target' => '', 'name' => '35%'], ['target' => '', 'name' => '40%'], ['target' => '', 'name' => '45%'], ['target' => '', 'name' => '50%'], ['target' => '.custom_referral_fee', 'name' => 'Other']];
                                @endphp
                                <div class="referral_fee @if (@$auction->get->service_type != 'Referral agent Service') d-none @endif ">
                                    <div class="form-group ">
                                        <label class="fw-bold">How much referral fee would agent like?</label>
                                        <select class="grid-picker" name="referral_fee" id="referral_fee"
                                            style="justify-content: flex-start;" required>
                                            <option value="">Select</option>
                                            @foreach ($referral_fee as $item)
                                                <option value="{{ $item['name'] }}"
                                                    {{ selected($item['name'], @$auction->get->referral_fee) }}
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div
                                        class="form-group custom_referral_fee {{ is_hidden(@$auction->get->custom_referral_fee) }}">
                                        <label class="fw-bold">Referral Fee:</label>
                                        <input type="text" name="custom_referral_fee" placeholder="eg 60%"
                                            id="custom_referral_fee" class="form-control has-icon"
                                            data-icon="fa-solid fa-percent"
                                            value="{{ @$auction->get->custom_referral_fee }}" required>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-step">
                                @php
                                    $type_of_client = [['name' => 'Buyer', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Seller', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Landlord', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tenant', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                                @endphp
                                <div class="form-group">
                                    <label class="fw-bold">What type of client is the service being provided for:</label>
                                    <select class="grid-picker" name="type_of_client" id="type_of_client"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($type_of_client as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->type_of_client) }}
                                                data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">What city, county, and state do the services need to be provided
                                        in?</label>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">City</label>
                                    <input type="text" name="city" data-type="cities"
                                        placeholder="St. Petersburg, FL, USA" id="city"
                                        value="{{ @$auction->get->city }}" class="form-control has-icon search_places"
                                        data-icon="fa-solid fa-city" data-msg-required="Please enter city" required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">County</label>
                                    <input type="text" name="county" data-type="counties" placeholder="Pinellas County"
                                        id="county" value="{{ @$auction->get->county }}"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                                        data-msg-required="Please enter county" required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">State</label>
                                    <input type="text" name="state" data-type="states" placeholder="Florida, USA"
                                        id="state" class="form-control has-icon search_places"
                                        value="{{ @$auction->get->state }}" data-icon="fa-solid fa-flag-usa"
                                        data-msg-required="Please enter state" required>
                                </div>

                            </div>


                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Which type of service or services do you need?
                                    </label>
                                    @php
                                        $services_data = [];
                                        $services_data[] = ['target' => '', 'name' => 'Conduct a Comparative Market Analysis to determine the fair market value of a property for a potential buyer.'];
                                        $services_data[] = ['target' => '', 'name' => 'Conduct a Comparative Market Analysis to determine the fair market value of a property for a potential seller.'];
                                        $services_data[] = ['target' => '', 'name' => 'Conduct a Rental Value Analysis to determine the optimal rental price for a property for a landlord.'];
                                        $services_data[] = ['target' => '', 'name' => 'Conduct a Rental Value Analysis to determine the fair rental price for a property for a tenant.'];
                                        $services_data[] = ['target' => '', 'name' => 'Schedule and coordinate property showings for a buyer.'];
                                        $services_data[] = ['target' => '', 'name' => 'Schedule and coordinate property showings for a seller.'];
                                        $services_data[] = ['target' => '', 'name' => 'Schedule and coordinate property showings for a tenant.'];
                                        $services_data[] = ['target' => '', 'name' => 'Schedule and coordinate property showings for a landlord.'];
                                        $services_data[] = ['target' => '', 'name' => 'Manage transactions by drafting, preparing, and sending necessary legal documents, including contracts, disclosures, and addendums.'];
                                        $services_data[] = ['target' => '', 'name' => 'Schedule and coordinate a certified home inspector to evaluate the condition of a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'Schedule and coordinate a professional photographer to take high-quality photos of a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'Schedule and coordinate a professional videographer to create a video tour of a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'Schedule and coordinate a home stager.'];
                                        $services_data[] = ['target' => '', 'name' => 'Schedule and coordinate a handyman to make necessary repairs or improvements to a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'Schedule and coordinate an appraiser.'];
                                        $services_data[] = ['target' => '', 'name' => 'Meet with a potential buyer for one showing.'];
                                        $services_data[] = ['target' => '', 'name' => 'Meet with a buyer for multiple showings.'];
                                        $services_data[] = ['target' => '', 'name' => 'Host an open house for a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'Create a video tour of a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'Take high-quality photos of a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'Take aerial photographs of a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'Provide 3D tours of a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'Provide shadowing opportunities for new agents to learn from experienced agents.'];
                                        $services_data[] = ['target' => '', 'name' => 'Install a lockbox on a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'Install a sign on a property.'];
                                        $services_data[] = ['target' => '', 'name' => 'List a property on the MLS.'];
                                        $services_data[] = ['target' => '', 'name' => 'List a property on Loopnet.'];
                                        $services_data[] = ['target' => '', 'name' => 'List a property on Crexi.'];
                                        $services_data[] = ['target' => '', 'name' => 'List a property on Bid Your Offer.'];
                                        $services_data[] = ['target' => '', 'name' => 'Market a property to various groups, pages, and affiliates.'];
                                        $services_data[] = ['target' => '', 'name' => 'Promote the property on social media platforms.'];
                                        $services_data[] = ['target' => '', 'name' => 'Answer phone calls for sellers and arrange showings.'];
                                        $services_data[] = ['target' => '', 'name' => 'Send property details via email to buyers searching for homes that fit their criteria as soon as the property is listed.'];
                                        $services_data[] = ['target' => '', 'name' => 'Send property details via email to tenants searching for homes that fit their criteria as soon as the property is listed.'];
                                        $services_data[] = ['target' => '', 'name' => 'Conduct tenant screening with a thorough application process that includes credit, criminal, background, eviction, and income verification checks.'];
                                        $services_data[] = ['target' => '', 'name' => 'Send out postcards to the neighborhood or selected area of an agent’s choice.'];
                                        $services_data[] = ['target' => '.custom_services', 'name' => 'Other'];
                                    @endphp
                                    <select name="services[]" id="services" class="grid-picker"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value=""></option>
                                        @foreach ($services_data as $item)
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->services) }}
                                                data-target="{{ $item['target'] }}" class="card flex-row"
                                                style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-hand-point-right" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_services {{ is_hidden(@$auction->get->custom_services) }}">
                                    <label class="fw-bold">Explain the service needed: *</label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Services" name="custom_services"
                                        data-icon="fa-regular fa-hand-point-right" id="custom_services"
                                        value="{{ @$auction->get->custom_services }}" required />
                                </div>
                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">What is the compensation you are offering to the agent who will
                                        provide the service?</label>
                                    <input type="number" step="0.01" placeholder="0.00" name="offerer_commission"
                                        id="offerer_commission" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar" value="{{ @$auction->get->offerer_commission }}"
                                        required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">
                                        Is the offered commission negotiable?
                                    </label>
                                    <div>
                                        <select name="offerer_commission_negotiable" id="offerer_commission_negotiable"
                                            class="grid-picker" style="justify-content: flex-start;"
                                            onchange="changeAuctionType(this.value);" required>
                                            <option value=""></option>
                                            @foreach ($yes_or_nos as $item)
                                                <option value="{{ $item['name'] }}"
                                                    {{ selected($item['name'], @$auction->get->offerer_commission_negotiable) }}
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
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
                                        What is the amount of time that you will allow for bidding?
                                    </label>
                                    <div>
                                        @php
                                            $auction_lengths = [
                                                ['name' => '1 hour', 'class' => 'normal-length'],
                                                ['name' => '2 hours', 'class' => 'normal-length'],
                                                ['name' => '3 hours', 'class' => 'normal-length'],
                                                ['name' => '4 hours', 'class' => 'normal-length'],
                                                ['name' => '5 hours', 'class' => 'normal-length'],
                                                ['name' => '6 hours', 'class' => 'normal-length'],
                                                ['name' => '7 hours', 'class' => 'normal-length'],
                                                ['name' => '8 hours', 'class' => 'normal-length'],
                                                ['name' => '9 hours', 'class' => 'normal-length'],
                                                ['name' => '10 hours', 'class' => 'normal-length'],
                                                ['name' => '11 hours', 'class' => 'normal-length'],
                                                ['name' => '12 hours', 'class' => 'normal-length'],
                                                ['name' => '1 day', 'class' => 'normal-length'],
                                                ['name' => '2 day', 'class' => 'normal-length'],
                                                ['name' => '3 days', 'class' => 'normal-length'],
                                                ['name' => '5 days', 'class' => 'normal-length'],
                                                ['name' => '7 days', 'class' => 'normal-length'],
                                                ['name' => '10 days', 'class' => 'normal-length'],
                                                ['name' => '14 days', 'class' => 'normal-length'],
                                                ['name' => '21 days', 'class' => 'normal-length'],
                                                ['name' => '30 days', 'class' => 'normal-length'],
                                            ];
                                        @endphp
                                        <select name="auction_length" id="auction_length"
                                            class="auction_length grid-picker" style="justify-content: flex-start;"
                                            required>
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


                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">Does the agent have any preferred agent(s) whom they would like
                                        to notify to participate
                                        in the auction?</label>
                                    <select class="grid-picker" name="have_preferred_agent" id="have_preferred_agent"
                                        style="justify-content: flex-start;">
                                        <option value="">Select</option>
                                        @foreach ($yes_or_nos as $item)
                                            @php
                                                if ($item['name'] == 'Yes') {
                                                    $target = '.p_agent_info';
                                                } else {
                                                    $target = '';
                                                }
                                            @endphp
                                            <option value="{{ $item['name'] }}"
                                                {{ selected($item['name'], @$auction->get->hoa_community) }}
                                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                                data-target="{{ $target }}"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group p_agent_info d-none">
                                    <h5 style="color: black">Please enter the first name, last name, brokerage, phone
                                        number, and email address of the preferred agent(s):</h5>

                                    <div class="form-group">
                                        <label class="fw-bold">First Name:</label>
                                        <input type="text" name="first_name" id="first_name"
                                            class="form-control has-icon" data-icon="fa-solid fa-user"
                                            placeholder="Enter first name" value="{{ @$auction->get->first_name }}">
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Last Name:</label>
                                        <input type="text" name="last_name" id="last_name"
                                            class="form-control has-icon" data-icon="fa-solid fa-user"
                                            placeholder="Enter last name" value="{{ @$auction->get->last_name }}">
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Brokerage:</label>
                                        <input type="text" name="brokerage" id="brokerage"
                                            class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                            placeholder="Enter brokerage" value="{{ @$auction->get->brokerage }}">
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Cell phone #:</label>
                                        <input type="text" name="phone" id="phone"
                                            class="form-control has-icon" data-icon="fa-solid fa-phone"
                                            placeholder="Enter phone number" value="{{ @$auction->get->phone }}">
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Email:</label>
                                        <input type="text" name="email" id="email"
                                            class="form-control has-icon" data-icon="fa-solid fa-envelope"
                                            placeholder="Enter email address" value="{{ @$auction->get->email }}">
                                    </div>
                                </div>
                            </div>


                            <div class="wizard-step">
                                <h5 class="text-black">The following information is visible after a winning bid has been
                                    selected:</h5>

                                <div class="form-group">
                                    <label class="fw-bold"> Private Notes: </label>
                                    <textarea name="private_notes" id="private_notes" class="form-control" rows="5" placeholder="">{{ @$auction->get->private_notes }}</textarea>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold"> Additional Information on the service needed: Include all the
                                        private details about what the agent needs to know: </label>
                                    <textarea name="additional_details" id="additional_details" class="form-control" rows="5" placeholder="">{{ @$auction->get->additional_details }}</textarea>
                                </div>
                                <br>
                                <div class="fw-bold">If hired agent needs to meet at a property, please provide the
                                    following information:</div>

                                <div class="form-group">
                                    <label class="fw-bold"> Address of the property that agent needs to meet: </label>
                                    <input type="text" name="address" id="address"
                                        value="{{ @$auction->get->address }}" class="form-control search_places has-icon"
                                        data-type="address" placeholder="199 Florida A1A, Satellite Beach, FL, USA"
                                        data-icon="fa-solid fa-location-dot" required />
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">Information on the person the client is meeting</label>
                                </div>


                                <div class="form-group">
                                    <label class="fw-bold"> Full Name: </label>
                                    <input type="text" name="contact_name" id="contact_name"
                                        class="form-control has-icon" placeholder="" data-icon="fa-solid fa-user"
                                        value="{{ @$auction->get->contact_name }}" required />
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold"> Phone Number: </label>
                                    <input type="text" name="contact_phone" id="contact_phone"
                                        class="form-control has-icon" placeholder="" data-icon="fa-solid fa-phone"
                                        value="{{ @$auction->get->contact_phone }}" required />
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold"> Email Address: </label>
                                    <input type="text" name="contact_email" id="contact_email"
                                        class="form-control has-icon" placeholder="" data-icon="fa-solid fa-envelope"
                                        value="{{ @$auction->get->contact_email }}" required />
                                </div>



                                <div class="form-group">
                                    <label class="fw-bold">
                                        Has agent scheduled the showing with the agent?
                                    </label>
                                    <div>
                                        <select name="showing_with_agent" id="showing_with_agent" class="grid-picker"
                                            style="justify-content: flex-start;" required>
                                            <option value=""></option>
                                            @foreach ($yes_or_nos as $item)
                                                <option value="{{ $item['name'] }}"
                                                    {{ selected($item['name'], @$auction->get->showing_with_agent) }}
                                                    data-target="{{ $item['target'] }}" class="card flex-row"
                                                    style="width:calc(33.3% - 10px);"
                                                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>


                                <div class="form-group">
                                    <label class="fw-bold">Date and time agent needs to meet at the property: </label>
                                    <div class="d-flex">
                                        <div class="pe-4"><input type="date" name="meeting_date" id="meeting_date"
                                                class="form-control has-icon" placeholder=""
                                                data-icon="fa-solid fa-calendar"
                                                value="{{ @$auction->get->meeting_date }}" required />
                                        </div>
                                        <div><input type="time" name="meeting_time" id="meeting_time"
                                                class="form-control has-icon" placeholder=""
                                                data-icon="fa-solid fa-clock" value="{{ @$auction->get->meeting_time }}"
                                                required />
                                        </div>
                                    </div>
                                </div>


                                <div class="form-group">
                                    <label class="fw-bold"> What are the showing instructions: (lockbox code etc.) </label>
                                    <textarea name="instructions" id="instructions" class="form-control" rows="5" placeholder="">{{ @$auction->get->instructions }}</textarea>
                                </div>

                            </div>

                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">When does the service need to be provided?</label>
                                    <input type="date" name="service_date" id="service_date"
                                        class="form-control has-icon" required data-icon="fa-solid fa-calendar"
                                        value="{{ @$auction->get->service_date }}">
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
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
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
