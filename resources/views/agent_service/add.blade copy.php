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

        input[type="radio"] {
            -webkit-appearance: radio !importent;
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
                    <form class="p-4 pt-0 validate mainform" action="{{ route('agent.service.auction.save') }}"
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
                                                "This is a service for Landlord’s that are not currently working with a licensed agent."
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
                                {{ $title }}
                            </h4>
                            <div class="wizard-steps-progress">
                                <div class="steps-progress-percent"></div>
                            </div>
                            {{-- 14 Jul 2023 --}}
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
                                            @php
                                                if ($item['name'] == 'Referral agent Service') {
                                                    $target = '.referral_fee';
                                                } else {
                                                    $target = '';
                                                }
                                            @endphp
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
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
                                <div class="form-group referral_fee d-none">
                                    <label class="fw-bold">How much referral fee would agent like?</label>
                                    <select class="grid-picker" name="referral_fee" id="referral_fee"
                                        style="justify-content: flex-start;" required>
                                        <option value="">Select</option>
                                        @foreach ($referral_fee as $item)
                                            @php
                                                if ($item['name'] == 'Other') {
                                                    $target = '. custom_referral_fee';
                                                } else {
                                                    $target = '';
                                                }
                                            @endphp
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group custom_referral_fee d-none">
                                    <label class="fw-bold">Referral Fee:</label>
                                    <input type="text" name="custom_referral_fee" placeholder="eg 60%"
                                        id="custom_referral_fee" class="form-control has-icon"
                                        data-icon="fa-solid fa-percent" required>
                                </div>
                            </div>

                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
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
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">What city, county, and state do the services need to be provided
                                        in?</label>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">City</label>
                                    <input type="text" name="city" data-type="cities"
                                        placeholder="St. Petersburg, FL, USA" id="city"
                                        class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                                        data-msg-required="Please enter city" required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">County</label>
                                    <input type="text" name="county" data-type="counties" placeholder="Pinellas County"
                                        id="county" class="form-control has-icon search_places"
                                        data-icon="fa-solid fa-tree-city" data-msg-required="Please enter county" required>
                                </div>

                                <div class="form-group">
                                    <label class="fw-bold">State</label>
                                    <input type="text" name="state" data-type="states" placeholder="Florida, USA"
                                        id="state" class="form-control has-icon search_places"
                                        data-icon="fa-solid fa-flag-usa" data-msg-required="Please enter state" required>
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
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
                                                <option value="{{ $item['name'] }}" data-target=""
                                                    class="card flex-row {{ $item['class'] }}"
                                                    style="width:calc(33.33% - 10px);"
                                                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                                    {{ $item['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <h5 style="color: black">Public Notes: Write Please do not share any private information
                                    here. Once an agent has been selected, you may include such details in the private notes
                                    section.</h5>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
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
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">When does the service need to be provided?</label>
                                    <input type="date" name="service_date" id="service_date"
                                        class="form-control has-icon" required data-icon="fa-solid fa-calendar">
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            {{-- 14 Jul 2023 --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Which type of service or services do you need?
                                    </label>
                                    @php
                                        $services_data = [
                                            ['target' => '', 'name' => 'Conduct a Comparative Market Analysis to evaluate the fair market value of a property.'],
                                            ['target' => '', 'name' => 'Conduct a Rental Value Analysis to ascertain the optimal rental price of a property.'],
                                            ['target' => '', 'name' => 'Show a property to a client.'],
                                            ['target' => '', 'name' => 'Show multiple properties for a client.'],
                                            ['target' => '', 'name' => 'Oversee transactions by composing, arranging, and disseminating essential legal paperwork, including contracts, disclosures, and addendums.'],
                                            ['target' => '', 'name' => 'Schedule and manage a certified home inspector to examine the condition of a property.'],
                                            ['target' => '', 'name' => 'Arrange and oversee a professional photographer to capture high-quality images of a property.'],
                                            ['target' => '', 'name' => 'Plan and direct a professional videographer to create a virtual tour of a property.'],
                                            ['target' => '', 'name' => 'Organize and supervise a home staging professional.'],
                                            ['target' => '', 'name' => 'Schedule and manage a handyman to perform necessary repairs or improvements on a property.'],
                                            ['target' => '', 'name' => 'Meet an appraiser.'],
                                            ['target' => '', 'name' => 'Meet an inspector.'],
                                            ['target' => '', 'name' => 'Conduct an open house for a property.'],
                                            ['target' => '', 'name' => 'Produce a video tour of a property.'],
                                            ['target' => '', 'name' => 'Take aerial photos of a property.'],
                                            ['target' => '', 'name' => 'Provide 3D Tour for a property.'],
                                            ['target' => '', 'name' => 'Provide virtual staging for a property.'],
                                            ['target' => '', 'name' => 'Provide a floor plan of a property.'],
                                            ['target' => '', 'name' => 'Provide mentorship opportunities for novice agents to learn from experienced colleagues.'],
                                            ['target' => '', 'name' => 'Install a lockbox at a property.'],
                                            ['target' => '', 'name' => 'Manage the installation and removal of home sale-related yard signs.'],
                                            ['target' => '', 'name' => 'Advertise a property on the MLS.'],
                                            ['target' => '', 'name' => 'Post a property listing on Loopnet.'],
                                            ['target' => '', 'name' => 'Post a property listing on Crexi.'],
                                            ['target' => '', 'name' => 'Post a property listing on Bid Your Offer.'],
                                            ['target' => '', 'name' => 'Market a property to various groups, pages, and affiliates.'],
                                            ['target' => '', 'name' => 'Promote the property on various social media platforms.'],
                                            ['target' => '', 'name' => 'Respond to phone calls and arrange showings.'],
                                            ['target' => '', 'name' => 'Dispatch property details via email to clients seeking homes that meet their criteria as soon as the property is listed.'],
                                            ['target' => '', 'name' => 'Conduct comprehensive tenant screening that includes an application process with credit, criminal, background, eviction, and income verification checks.'],
                                            ['target' => '', 'name' => 'Distribute postcards to the neighborhood or an agent-selected area.'],
                                            ['target' => '', 'name' => 'Conduct a tenant move-in/move-out inspection.'],
                                            ['target' => '', 'name' => 'Prepare, review, and negotiate lease agreements or contracts.'],
                                            ['target' => '', 'name' => 'Provide consultation on property management and investment.'],
                                            ['target' => '', 'name' => 'Facilitate connections with local contractors, suppliers, and service providers.'],
                                            ['target' => '', 'name' => 'Conduct walk-throughs before closing a sale to ensure agreed-upon repairs or improvements have been done.'],
                                            ['target' => '', 'name' => 'Negotiate terms and conditions with a prospective client.'],
                                            ['target' => '', 'name' => 'Assist in drafting and negotiating commercial lease agreements.'],
                                            ['target' => '', 'name' => 'Assist in drafting and negotiating commercial sale agreements.'],
                                            ['target' => '', 'name' => 'Assist in drafting and negotiating residential lease agreements.'],
                                            ['target' => '', 'name' => 'Assist in drafting and negotiating residential sale agreements.'],
                                            ['target' => '', 'name' => 'Managing communication between buyers, sellers, and their respective agents.'],
                                            ['target' => '', 'name' => 'Creating and distributing digital brochures or pamphlets about the property.'],
                                            ['target' => '', 'name' => 'Coordinate or assist in the move-in or move-out process for tenants.'],
                                            ['target' => '', 'name' => 'Assist in the creation of attractive and engaging listing descriptions.'],
                                            ['target' => '', 'name' => 'Coordinate interactions with title companies, escrow officers, and other key parties in a real estate transaction.'],
                                            ['target' => '', 'name' => 'Provide advice on the process of buying or selling distressed properties.'],
                                            ['target' => '', 'name' => 'Provide social media management and marketing services.'],
                                            ['target' => '', 'name' => 'Assist with property inspections and coordinating necessary repairs.'],
                                            ['target' => '', 'name' => 'Offer guidance on property investment strategies.'],
                                            ['target' => '', 'name' => 'Provide support in developing and implementing lead nurturing campaigns.'],
                                            ['target' => '', 'name' => 'Conduct property market trend analysis and reporting.'],
                                            ['target' => '', 'name' => 'Assist with real estate email marketing campaigns.'],
                                            ['target' => '', 'name' => 'Provide support in developing and implementing client referral programs.'],
                                            ['target' => '', 'name' => 'Offer guidance on real estate investment risk assessment and mitigation.'],
                                            ['target' => '', 'name' => 'Assist with real estate lead conversion and follow-up strategies.'],
                                            ['target' => '', 'name' => 'Provide support in developing and implementing real estate advertising campaigns.'],
                                            ['target' => '', 'name' => 'Assist with real estate market research for specific demographics or target markets.'],
                                            ['target' => '', 'name' => 'Assist with property management tasks, such as tenant communication and lease renewals.'],
                                            ['target' => '', 'name' => 'Assist with marketing efforts, including creating and distributing promotional materials, marketing campaigns, advertising strategies, online presence, social media management, and content creation for blogs or websites.'],
                                            ['target' => '', 'name' => 'Conduct administrative tasks such as data entry, paperwork processing, appointment scheduling, and customer relationship management (CRM) implementation and management.'],
                                            ['target' => '', 'name' => 'Mentor and/or shadow an agent (ideal for new agents).'],
                                            ['target' => '', 'name' => 'Other- Explain the service needed:'],
                                        ];
                                    @endphp
                                    <select name="services[]" id="services" class="grid-picker"
                                        style="justify-content: flex-start;" multiple required>
                                        <option value=""></option>
                                        @foreach ($services_data as $item)
                                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                                class="card flex-row" style="width:calc(100%);"
                                                data-icon='<i class="fa-regular fa-hand-point-right" style="font-size:28px;"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group custom_services d-none">
                                    <label class="fw-bold">Explain the service needed:</label>
                                    <input type="text" class="form-control has-icon"
                                        placeholder="Write Custom Services" name="custom_services"
                                        data-icon="fa-regular fa-hand-point-right" id="custom_services" required />
                                </div>
                            </div>
                            {{-- 14 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Does the hired agent need to meet in person at the property?
                                    </label>
                                    <div class="options-container options-container-7"
                                        style="justify-content: flex-start;">
                                        <div onclick="checkselect(this);" style="width:calc(33.3% - 10px);"
                                            class="card flex-row option-container active" data-index="1"
                                            data-target=".p_agent">
                                            <div class="option-icon"><i class="fa-regular fa-circle-check"></i> </div>
                                            <div class="option-text">
                                                Yes
                                            </div>
                                        </div>
                                        <div onclick="checkselect(this);" style="width:calc(33.3% - 10px);"
                                            class="card flex-row option-container" data-index="2" data-target="">
                                            <div class="option-icon"><i class="fa-regular fa-circle-xmark"></i> </div>
                                            <div class="option-text">
                                                No
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p_agent">
                                    <div class="form-group">
                                        <label class="fw-bold">Information on the person the client is meeting</label>
                                    </div>
                                    <div class="form-group">
                                        <label class="fw-bold"> Full Name: </label>
                                        <input type="text" name="contact_name" id="contact_name"
                                            class="form-control has-icon" placeholder="" data-icon="fa-solid fa-user"
                                            value="{{ Auth::user()->name }}" required />
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold"> Phone Number: </label>
                                        <input type="text" name="contact_phone" id="contact_phone"
                                            class="form-control has-icon" placeholder="" data-icon="fa-solid fa-phone"
                                            value="{{ Auth::user()->phone }}" required />
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold"> Email Address: </label>
                                        <input type="text" name="contact_email" id="contact_email"
                                            class="form-control has-icon" placeholder="" data-icon="fa-solid fa-envelope"
                                            value="{{ Auth::user()->email }}" required />
                                    </div>
                                    <div class="form-group">
                                        <label class="fw-bold">
                                            Has agent scheduled the showing with the agent?
                                        </label>
                                        <div>
                                            <select name="showing_with_agent" id="showing_with_agent" class="grid-picker"
                                                style="justify-content: flex-start;"
                                                onchange="changeAuctionType(this.value);" required>
                                                <option value=""></option>
                                                @foreach ($yes_or_nos as $item)
                                                    <option value="{{ $item['name'] }}"
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
                                            <div class="pe-4"><input type="date" name="meeting_date"
                                                    id="meeting_date" class="form-control has-icon" placeholder=""
                                                    data-icon="fa-solid fa-calendar" value="" required /></div>
                                            <div><input type="time" name="meeting_time" id="meeting_time"
                                                    class="form-control has-icon" placeholder=""
                                                    data-icon="fa-solid fa-clock" value="" required /></div>
                                        </div>
                                    </div>


                                    <div class="form-group">
                                        <label class="fw-bold"> What are the showing instructions: (lockbox code etc.)
                                        </label>
                                        <textarea name="instructions" id="instructions" class="form-control" rows="5" placeholder=""></textarea>
                                    </div>
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">What is the compensation you are offering to the agent who will
                                        provide the service?</label>
                                    <input type="number" step="0.01" placeholder="0.00" name="offerer_commission"
                                        id="offerer_commission" class="form-control has-icon"
                                        data-icon="fa-solid fa-dollar" required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">What is the Exact Amount?</label>
                                    <input type="number" step="0.01" placeholder="0.00" name="exact_amount"
                                        id="exact_amount" class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                        required>
                                </div>
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Is it Negotiable Or Not
                                    </label>
                                    <select name="has_negotiable" id="has_negotiable" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($yes_or_nos as $item)
                                            <option value="{{ $item['name'] }}" class="card flex-row"
                                                style="width:calc(33.3% - 10px);"
                                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                                {{ $item['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step">
                                <div class="form-group">
                                    <label class="fw-bold">
                                        Does the agent have any preferred agent(s) whom they would like to notify to
                                        participate
                                        in the auction?
                                    </label>
                                    <select name="have_preferred_agent" id="have_preferred_agent" class="grid-picker"
                                        style="justify-content: flex-start;" required>
                                        <option value=""></option>
                                        @foreach ($yes_or_nos as $item)
                                            @php
                                                if ($item['name'] == 'Yes') {
                                                    $target = '.p_agent_info';
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
                                <div class="form-group p_agent_info d-none">
                                    <h5 style="color: black">Please enter the first name, last name, brokerage, phone
                                        number, and email address of the preferred agent(s):</h5>

                                    <div class="form-group">
                                        <label class="fw-bold">First Name:</label>
                                        <input type="text" name="first_name" id="first_name"
                                            class="form-control has-icon" data-icon="fa-solid fa-user"
                                            placeholder="Enter first name" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Last Name:</label>
                                        <input type="text" name="last_name" id="last_name"
                                            class="form-control has-icon" data-icon="fa-solid fa-user"
                                            placeholder="Enter last name" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Brokerage:</label>
                                        <input type="text" name="brokerage" id="brokerage"
                                            class="form-control has-icon" data-icon="fa-solid fa-dollar"
                                            placeholder="Enter brokerage" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Cell phone #:</label>
                                        <input type="text" name="phone" id="phone"
                                            class="form-control has-icon" data-icon="fa-solid fa-phone"
                                            placeholder="Enter phone number" required>
                                    </div>

                                    <div class="form-group">
                                        <label class="fw-bold">Email:</label>
                                        <input type="text" name="email" id="email"
                                            class="form-control has-icon" data-icon="fa-solid fa-envelope"
                                            placeholder="Enter email address">
                                    </div>
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step">
                                <h4>Add Public Note</h4>
                                <div class="form-group">
                                    <label class="fw-bold"> Public Note:(Displayed publicly on the listing) </label>
                                    <textarea name="public_note" id="public_note" class="form-control" cols="30" rows="10" required></textarea>
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
                            <div class="wizard-step">
                                <h4>Add Private Note</h4>
                                <div class="form-group">
                                    <label class="fw-bold"> Private Note:(Shared exclusively with the hired agent)</label>
                                    <textarea name="private_note" id="private_note" class="form-control" cols="30" rows="10" required></textarea>
                                </div>
                            </div>
                            {{-- 17 Jul 2023 --}}
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
        <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    What price does the landlord want to ideally list the property for? *
                </label>
                <input type="text" name="cma_q1" id="cma_q1" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar" placeholder="0.00" required>
            </div>
        </div>

        <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    Picture link for the most accurate analysis
                </label>
                <input type="url" name="cma_q2" id="cma_q2" class="form-control">
            </div>
        </div>

        <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    Video link for the most accurate analysis
                </label>
                <input type="url" name="cma_q3" id="cma_q3" class="form-control">
            </div>
        </div>

    </template>
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
