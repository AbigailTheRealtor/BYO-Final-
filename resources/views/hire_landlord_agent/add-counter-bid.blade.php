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

        .box {
            display: block;
            width: 200px;
            height: 160px;
            background-color: white;
            border-radius: 5px;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            /* overflow: hidden; */
            position: relative;
        }

        .js--image-preview {
            width: 200px;
            height: 160px;
        }

        .upload {
            margin-bottom: 100px;
        }

        .upload-options {
            position: relative;
            /* height: 65px; */
            background-color: $base-color;
            cursor: pointer;
            overflow: hidden;
            text-align: center;
            transition: background-color ease-in-out 150ms;
            color: red;
            width: 100%;
            border: 1px solid #dedddd;
            border-radius: 0px 0px 5px 5px;

            &:hover {
                background-color: lighten($base-color, 10%);
            }

            & input {
                width: 0.1px;
                height: 0.1px;
                opacity: 0;
                overflow: hidden;
                position: absolute;
                z-index: -1;
            }

            & label {
                display: flex;
                align-items: center;
                width: 100%;
                height: 100%;
                font-weight: 400;
                text-overflow: ellipsis;
                white-space: nowrap;
                cursor: pointer;
                overflow: hidden;

                &::after {
                    content: "+";
                    font-family: "Material Icons";
                    z-index: 0;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    width: 198px;
                    height: 50px;
                    font-size: 28px;
                    color: #e6e6e6;
                }

                & span {
                    display: inline-block;
                    width: 50%;
                    height: 100%;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    overflow: hidden;
                    vertical-align: middle;
                    text-align: center;

                    &:hover i.material-icons {
                        color: lightgray;
                    }
                }
            }
        }

        .js--image-preview {
            height: 100%;
            width: 100%;
            /* position: relative; */
            overflow: hidden;
            background-image: url('/images/image.png');
            background-color: white;
            /* background-position: center center; */
            background-repeat: no-repeat;
            background-size: cover;

            &.js--no-default::after {
                display: none;
            }

            &:nth-child(2) {
                background-image: url("http://bastianandre.at/giphy.gif");
            }
        }

        i.material-icons {
            transition: color 100ms ease-in-out;
            font-size: 2.25em;
            line-height: 55px;
            color: white;
            display: block;
        }

        .drop {
            display: block;
            position: absolute;
            background: transparentize($base-color, 0.8);
            border-radius: 100%;
            transform: scale(0);
        }

        .animate {
            animation: ripple 0.4s linear;
        }

        @keyframes ripple {
            100% {
                opacity: 0;
                transform: scale(2.5);
            }
        }

        .video {
            height: 160px;
            width: 200px;
            position: relative;
            /* overflow: hidden; */

            background-color: white;
            /* background-position: center center; */
            background-repeat: no-repeat;
            background-size: cover;
            margin-bottom: 60px;

        }

        .bgImg {
            background-image: url('/images/play.png');
        }

        span.upload-button {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 198px;
            height: 50px;
            font-size: 28px;
            color: #e6e6e6;
        }

        .videoBox {
            width: 200px;
            border-radius: 5px;

        }

        .videoDiv {
            margin-top: -60px;
        }

        label.fileuploader-btn {
            /* position: absolute; */
            top: 100%;
            border-radius: 0px 0px 5px 5px;
            border: 1px solid #e2e2e2;
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
                <form class="p-4 pt-0 mainform" action="{{ route('landlord.agent.save.counter-bid', $bid->id) }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                    @php
                        $yes_or_nos = [
                            ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ];
                    @endphp
                    <div class="wizard-step">
                        @php
                            $listing_terms = [
                                ['name' => '3 Months', 'target' => ''],
                                ['name' => '6 Months', 'target' => ''],
                                ['name' => '9 Months', 'target' => ''],
                                ['name' => '12 Months', 'target' => ''],
                                ['name' => 'Other', 'target' => '.custom_listing_terms'],
                            ];
                        @endphp
                        <div class="form-group">
                            @if (Auth::user()->user_type == 'landlord')
                                <label class="fw-bold">What is the timeframe offered to the agent in the landlord agency agreement</label>
                            @else
                                <label class="fw-bold">What is the timeframe offered by the agent for the landlord agency agreement?</label>
                            @endif
                            <select class="grid-picker" name="listing_terms" id="listing_terms"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($listing_terms as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_listing_terms d-none">
                            <label class="fw-bold">What is the timeframe offered by the agent for the landlord agency agreement?</label>
                            <input type="text" class="form-control has-icon" name="custom_listing_terms"
                                data-icon="fa-solid fa-calendar-days" id="custom_listing_terms" required />
                        </div>
                    </div>
                    <div class="wizard-step">
                        @if (Auth::user()->user_type == 'landlord')
                            <h4>The owner agrees to compensate the broker as follows, including paying any applicable taxes on the broker's services, if the owner enters into a lease of the property with a tenant during the listing period, regardless of whether the tenant fulfills the terms of the lease; or if, during the listing period, the broker procures a tenant who is ready, willing, and able to lease the property under the terms of this agreement, or terms acceptable to the owner. All commission is negotiable. </h4>
                        @else
                            <h4>Agent's Agreement on Commission Rates:</h4>
                        @endif
                        
                        
                        <div class="form-group">
                            @php
                                $broker_compensation = [
                                    [ 'name' => "___% of each rental period", 'target' => ''],
                                    ['name' => "___% of the gross lease value", 'target' => ''],
                                    ['name' => "____% of the first month's rent", 'target' => ''],
                                    ['name' => 'Fixed amount: $____ ', 'target' => ''],
                                ];
                            @endphp
                            @if (Auth::user()->user_type == 'landlord')
                                <label class="fw-bold">What compensation will the owner provide to the listing broker for their services?</label>
                            @else
                                <label class="fw-bold">What compensation will the listing broker accept from the owner for their services?</label>
                            @endif
                            
                            <select class="grid-picker" name="broker_compensation" id="broker_compensation"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($broker_compensation as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group compensation_broker_yes d-none">
                                @if (Auth::user()->user_type == 'landlord')
                                    <label class="fw-bold">What compensation will the owner provide to the listing broker for their services?</label>
                                @else
                                    <label class="fw-bold">What compensation will the listing broker accept from the owner for their services?</label>
                                @endif
                                <input type="text" class="form-control has-icon" name="compensation_percent"
                                    data-icon="fa-solid fa-dollar-sign" required />
                            </div>
                        </div>
                        <div class="form-group">
                            @php
                                $handle_compensation = [
                                    ['name' => "Allow the listing broker to compensate the tenant's broker from the listing broker's commission, if applicable.", 'target' => ''],
                                    ['name' => "Pay the tenant's broker separately, if applicable.", 'target' => ''],
                                    ['name' => "No compensation will be offered to the buyer’s broker.", 'target' => ''],
                                ];
                            @endphp
                            @if (Auth::user()->user_type == 'landlord')
                                <label class="fw-bold">How would the owner prefer to handle compensation for a tenant's broker?</label>
                            @else
                                <label class="fw-bold">How would the listing broker prefer to handle compensation for a tenant's broker?</label>
                            @endif
                            <select class="grid-picker" name="handle_compensation" id="handle_compensation"
                                style="justify-content: flex-start;" required>
                                <option value="">Select</option>
                                @foreach ($handle_compensation as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-row" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group handle_compensation_broker_yes d-none">
                                @php
                                    $compensation_amount = [
                                        ['name' => "___% of the gross lease value", 'target' => ''],
                                        ['name' => "____% of the first month’s rent", 'target' => ''],
                                        ['name' => 'Fixed amount : $____ ', 'target' => ''],
                                    ];
                                @endphp
                                <label class="fw-bold">What compensation is being offered to the tenant's broker?</label>
                                <select class="grid-picker" name="compensation_amount" id="compensation_amount"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($compensation_amount as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group compensation_amount_yes d-none ">
                                    <label class="fw-bold">What compensation is being offered to the tenant's broker?</label>
                                    <input type="number" class="form-control has-icon" placeholder=""
                                        name="compensation_tenant_broker" data-icon="fa-solid fa-dollar-sign" required />
                                </div> 
                            </div>
                        </div>
                        <div class="form-group">
                            @php
                                $payment_timing = [
                                    ['name' => "Deducted from rent collected by the broker; the owner to pay balance (if any) within ___ calendar days of rent due date.", 'target' => ''],
                                    ['name' => "Paid within ___ calendar days after lease agreement execution.", 'target' => ''],
                                    ['name' => "Paid within ___ calendar days of each tenant rent payment.", 'target' => ''],
                                ];
                            @endphp
                            <label class="fw-bold">Payment Timing for Broker Fees:</label>
                            <select class="grid-picker" name="payment_timing" id="payment_timing"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($payment_timing as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group payment_timing_days d-none ">
                                <label class="fw-bold">Payment Timing for Broker Fees:</label>
                                <input type="number" class="form-control has-icon" placeholder=""
                                    name="payment_timing_days" data-icon="fa-regular fa-check-circle" required />
                            </div>                                
                        </div>
                    </div>
                    <div class="wizard-step">
                        <h4>Early Termination and Protection Period:</h4>

                        <div class="form-group">
                            @php
                                $early_termination = [
                                    ['name' => 'The owner may terminate this agreement by signing a withdrawal agreement and paying a cancellation fee of $______ plus applicable sales tax.', 'target' => ''],
                                    ['name' => "If the property is leased to a tenant during the termination or protection period, the broker may void the termination and collect full compensation (minus the cancellation fee).", 'target' => ''],
                                ];
                            @endphp
                            <label class="fw-bold">Early Termination:</label>
                            <select class="grid-picker" name="early_termination" id="early_termination"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($early_termination as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group early_termination_yes d-none ">
                                <label class="fw-bold">Early Termination:</label>
                                <input type="number" class="form-control has-icon" placeholder=""
                                    name="early_termination_amount" data-icon="fa-regular fa-check-circle" required />
                            </div>                                
                        </div>
                        <div class="form-group">
                            @php
                                $protection_period = [
                                    ['name' => "If the owner leases the property within ___ days after the listing period to any tenant introduced by the broker, the owner agrees to pay the broker's fee.", 'target' => '.protection_period_yes'],
                                    ['name' => "The broker will provide a list of prospects upon request; compensation applies only to names on the list.", 'target' => ''],
                                    ['name' => "The protection period is void if the owner signs an exclusive agreement with another broker after the listing period.", 'target' => ''],
                                ];
                            @endphp
                            <label class="fw-bold">Protection Period:</label>
                            <select class="grid-picker" name="protection_period" id="protection_period"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($protection_period as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>    
                            <div class="form-group protection_period_yes d-none ">
                                <label class="fw-bold">Protection Period:</label>
                                <input type="number" class="form-control has-icon" placeholder=""
                                    name="protection_period_days" data-icon="fa-regular fa-check-circle" required />
                            </div>                        
                        </div>
                    </div>
                    <div class="wizard-step">
                        <h4>New Leases and Renewals:</h4>
                        <div class="form-group">
                            @php
                                $compensation_new_lease = [
                                    ['name' => 'Yes', 'target' => '.new_lease_yes'],
                                    ['name' => "No", 'target' => ''],
                                ];
                            @endphp
                            <label class="fw-bold">If the owner enters into a new lease or renewal with a tenant placed by the broker, does the broker require compensation?</label>
                            <select class="grid-picker" name="compensation_new_lease" id="compensation_new_lease"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($compensation_new_lease as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                        style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-group new_lease_yes d-none ">
                                @php
                                $compensation_amount = [
                                    ['name' => '___% of each rental period', 'target' => ''],
                                    ['name' => "___% of the gross lease value", 'target' => ''],
                                    ['name' => "___% of the first month's rent", 'target' => ''],
                                    ['name' => 'Fixed amount: $____', 'target' => ''],
                                ];
                                @endphp
                                <label class="fw-bold">What compensation will the listing broker accept from the owner for a new lease or lease renewal?</label>
                                <select class="grid-picker" name="compensation_new_lease_percent" id="compensation_new_lease_amount"
                                    style="justify-content: flex-start;">
                                    <option value="">Select</option>
                                    @foreach ($compensation_amount as $item)
                                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                            style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                                            {{ $item['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-group compensation_new_lease_amount d-none ">
                                    <label class="fw-bold">What compensation will the owner provide to the listing broker for a new lease or lease renewal?</label>
                                    <input type="number" class="form-control has-icon" placeholder=""
                                        name="compensation_new_lease_amount" data-icon="fa-regular fa-check-circle" required />
                                </div> 
                            </div>                                
                        </div>
                    </div>
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">About Agent:<span class="text-danger"></span></label>
                            <textarea class="form-control" name="bio" rows="5" required>{{ old('bio') }}</textarea>
                        </div>
                    </div>
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Why should you be hired as their agent? <span
                                    class="text-danger"></span></label>
                            <textarea class="form-control" name="why_hire_you" rows="5" required>{{ old('why_hire_you') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">What sets you apart from other agents? <span
                                    class="text-danger"></span></label>
                            <textarea class="form-control" name="what_sets_you_apart" rows="5" required>{{ old('what_sets_you_apart') }}</textarea>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">What is your marketing strategy?<span class="text-danger"></span></label>
                            <textarea class="form-control" name="marketing_plan" rows="5" required>{{ old('marketing_plan') }}</textarea>
                        </div>
                    </div>
                    <div class="wizard-step" data-step="2">
                        <div class="row form-group">
                            <div class="col-md-12 mb-2">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th colspan="2" class="text-center fw-bold">Social Media Platforms</th>
                                        </tr>
                                        <tr>
                                            <th>Website Link:</th>
                                            <th>Reviews Link:</th>
                                        </tr>
                                    </thead>
                                    <tbody class="links">
                                        <tr>
                                            <td>
                                                <input type="text" name="website_link[]" class="form-control">
                                            </td>
                                            <td>
                                                <input type="text" name="reviews_link[]" class="form-control">
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="2" class="text-right">
                                                <a class="btn btn-primary add-links">Add New Row</a>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                        <div class="row form-group">
                            <div class="col-md-12 mb-2">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th colspan="2" class="text-center fw-bold"></th>
                                        </tr>
                                        <tr>
                                            <th>Type:</th>
                                            <th>Link:</th>
                                        </tr>
                                    </thead>
                                    <tbody class="social-links">
                                        <tr>
                                            <td>
                                                <select name="socialType[]" class="form-select">
                                                    <option value="Facebook">Facebook</option>
                                                    <option value="YouTube">YouTube</option>
                                                    <option value="LinkedIn">LinkedIn</option>
                                                    <option value="X">X</option>
                                                    <option value="Instagram">Instagram</option>
                                                    <option value="TikTok">TikTok</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="social_link[]" class="form-control">
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="2" class="text-right">
                                                <a class="btn btn-primary add-row">Add New Row</a>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">What year did the agent get licensed?</label>
                            <input type="text" class="form-control has-icon" name="licensed"
                                value="{{ old('licensed') }}" data-icon="fa-solid fa-calendar-days" required />
                        </div>
                    </div>
                    <div class="wizard-step">
                        @php
                            $services_data = [];
                            if ($auction->get->property_type == 'Residential Property') {
                                $services_data = [
                                    [
                                        'name' => 'Assist in drafting residential lease agreements and required addendums/disclosures.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Assist in negotiating residential lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Assist with lease renewal negotiations and adjustments to rental terms.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Assist with tenant move-in and move-out inspections.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Conduct a thorough rental market analysis (RMA) to determine the property\'s value and pricing strategy.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Conduct property showings and viewings for interested tenants.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Conduct real estate email marketing campaigns that lead to the property listing.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Conduct tenant screening with a thorough application process that includes credit, criminal, background, eviction, and income verification checks.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Coordinate or assist in the move-in or move-out process for tenants.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Coordinate property maintenance and repairs through trusted contractors and vendors.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Handle tenant inquiries, maintenance requests, and resolve any issues that may arise during the tenancy.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'List the property on major real estate websites—such as Zillow, Trulia, Realtor.com, Homes.com, and others—to increase visibility and exposure.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'List the property on the Bid Your Offer platform.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'List the property on the MLS.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Other - Add additional services as offered.',
                                        'target' => '.other_services',
                                    ],
                                    [
                                        'name' => 'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide a 3D tour to showcase the property\'s features.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide a floor plan of the property to highlight its layout and spatial configuration.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide a professional video to showcase the property\'s features.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide professional photos showcasing the property\'s features.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide regular updates on market activity, showings, and feedback from potential tenants.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide virtual staging to enhance the property\'s visual appeal and attract potential tenants.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Send email alerts to tenants searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.',
                                        'target' => '',
                                    ],
                                ];
                            } else {
                                $services_data = [
                                    [
                                        'name' => 'Assist in drafting residential lease agreements and required addendums/disclosures.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Assist in negotiating residential lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Assist with lease renewal negotiations and adjustments to rental terms.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Assist with tenant move-in and move-out inspections.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Conduct a thorough rental market analysis (RMA) to determine the property\'s value and pricing strategy.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Conduct property showings and viewings for interested tenants.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Conduct real estate email marketing campaigns that lead to the property listing.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Conduct tenant screening with a thorough application process that includes credit, criminal, background, eviction, and income verification checks.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Coordinate or assist in the move-in or move-out process for tenants.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Coordinate property maintenance and repairs through trusted contractors and vendors.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Handle tenant inquiries, maintenance requests, and resolve any issues that may arise during the tenancy.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'List the property on Crexi, a major commercial real estate website.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'List the property on Loopnet, a major commercial real estate website.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'List the property on the Bid Your Offer platform.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'List the property on the MLS.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Other - Add additional services as offered.',
                                        'target' => '.other_services',
                                    ],
                                    [
                                        'name' => 'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide a 3D tour to showcase the property\'s features.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide a floor plan of the property to highlight its layout and spatial configuration.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide a professional video to showcase the property\'s features.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide professional photos showcasing the property\'s features.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide regular updates on market activity, showings, and feedback from potential tenants.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Provide virtual staging to enhance the property\'s visual appeal and attract potential tenants.',
                                        'target' => '',
                                    ],
                                    [
                                        'name' => 'Send email alerts to tenants searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.',
                                        'target' => '',
                                    ],
                                ];
                            }
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Select the included services that the agent will provide to the
                                landlord:</label>
                            <select class="grid-picker" name="services[]" id="services" multiple required>
                                <option value="">Select</option>
                                @foreach ($services_data as $service)
                                    <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}"
                                        class="card flex-row" style="width:calc(100% - 0px);"
                                        data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                                        {{ $service['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group other_services @if (@$auction->get->other_services == '' || @$auction->get->other_services == 'null') d-none @endif ">
                            <label class="fw-bold">What additional services will the agent provide the landlord?</label>
                            <input type="text" name="other_services" id="other_services"
                                data-icon="fa-solid fa-hand-point-right" value="{{ @$auction->get->other_services }}"
                                class="form-control has-icon">
                        </div>
                        @if ($auction->get->auction_type !== null && $auction->get->auction_type == 'Auction Listing')
                        <div class="form-group">
                          @php
                            $hireNowterms = [
                                    [ 'name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-check-circle'],
                                    [ 'name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                            ]
                          @endphp
                          <label class="fw-bold">Has the agent bid on the landlord’s 'Hire Now' terms? The 'Hire Now' terms consist of
                            the landlord’s terms for the Listing Agreement Timeframe, the Listing Agent Commission
                            Offered, the Tenant’s Agent Commission Split from the Listing Agent’s Commission, and
                            the services the landlord requests from their agent.</label>
                          <select class="grid-picker" name="bid_on_hirenow_terms" id="" required>
                              <option value="">Select</option>
                              @foreach ($hireNowterms as $service)
                                  <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}"
                                      class="card flex-row" style="width:calc(100% - 0px);"
                                      data-icon='<i class="{{$service['icon']}}"></i>'>
                                      {{ $service['name'] }}
                                  </option>
                              @endforeach
                          </select>
                      </div>
                      <div class="form-group">
                        <p>Please note that the agent is selected after the timer ends. In the event that an agent bids
                          the 'Hire Now Terms', the landlord may choose to end the timer early and select an agent
                          who matches their 'Hire Now Terms'.</p>
                      </div>
                      @endif
                    </div>
                    <div class="wizard-step">
                      <div class="row">
                        <div class="col-6">
                            <label class="fw-bold mt-1">Virtual Listing Presentation:</label>
                            <div class="videoBox ">
                                <div class="video bgImg"></div>
                                <div class="form-group videoDiv">
                                    <input type="file" class="fileuploader" name="video_file"
                                        style="display: none;" accept="video/*">
                                    <label for="fileuploader" class="fileuploader-btn">
                                        <span class="upload-button">+</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="upload form-group">
                                <label class="fw-bold">Business Card:</label>
                                <div class="wrapper">
                                    <div class="box">
                                        <div class="js--image-preview"></div>
                                        <div class="upload-options">
                                            <label>
                                                <input type="file" name="card" class="image-upload"
                                                    accept="image/*" />
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                      </div>
                        <div class="form-group row">
                            <label class="fw-bold">Virtual Listing Presentation (Link):
                                <input type="url" class="form-control has-icon" name="video_file"
                                    data-icon="fa-solid fa-link">
                                <small>Please input the link with http:// or https://</small>
                        </div>
                        <div class="form-group">
                            <label class="fw-bold">Promotional marketing materials such as postcards, flyers, brochures,
                              marketing guides, etc.</label>
                            <input type="file" class="form-control" name="promo[]">
                            <button type="button" class="btn btn-secondary btn-sm w-100 promotional_btn mt-1"
                                onclick="add_promotional();"><i class="fa-solid fa-plus"></i> Add New
                                Row</button>
                        </div>
                    </div>
                    <div class="wizard-step">
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label class="fw-bold" for="first_name">First Name:</label>
                                <input type="text" name="first_name" placeholder="John" id="first_name"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user-tie"
                                    data-msg-required="Please enter First Name" value="{{ Auth::user()->first_name }}"
                                    autofocus>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold" for="last_name">Last Name:</label>
                                <input type="text" name="last_name" placeholder="Smith" id="last_name"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user-tie"
                                    data-msg-required="Please enter Last Name" value="{{ Auth::user()->last_name }}"
                                    autofocus>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label class="fw-bold" for="agent_phone">Phone Number:</label>
                                <input type="text" name="agent_phone" placeholder="" id="agent_phone"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-phone"
                                    data-msg-required="Please enter Phone Number" value="{{ Auth::user()->phone }}">
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold" for="agent_email">Email:</label>
                                <input type="email" name="agent_email" placeholder="john@example.com" id="agent_email"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-envelope"
                                    data-msg-required="Please enter Email" value="{{ Auth::user()->email }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label class="fw-bold" for="agent_brokerage">Brokerage:</label>
                                <input type="text" name="agent_brokerage" placeholder="" id="agent_brokerage"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-handshake"
                                    data-msg-required="Please enter Brokerage" value="{{ Auth::user()->brokerage }}">
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold" for="agent_license_no">Real Estate License #:</label>
                                <input type="text" name="agent_license_no" placeholder="" id="agent_license_no"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-card"
                                    data-msg-required="Please enter License Number"
                                    value="{{ Auth::user()->license_no }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6">
                                <label class="fw-bold" for="mls_id">NAR Member ID (NRDS ID):</label>
                                <input type="text" name="mls_id" placeholder="" id="mls_id"
                                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-badge"
                                    data-msg-required="Please enter MLS ID" value="{{ Auth::user()->mls_id }}">
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between form-group mt-4">
                        <div>
                            <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                        </div>
                        <div>
                            <a class="wizard-step-next btn btn-success btn-lg text-600" id="nextBtn"
                                style="display: none;">Next</a>
                            <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
                                style="display: none;">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <template class="link_temp mt-2">
        <lable class="fw-bold">Website Link:</lable>
        <input type="text" name="website_link[]" placeholder="" data-type="" class="form-control has-icon"
            data-icon="fa-solid fa-link" data-msg-required="">
    </template>
    <template class="promo_temp mt-3">
        <input type="file" name="promo[]" placeholder="" data-type="" class="form-control has-icon"
            data-icon="fa-solid fa-link" data-msg-required="">
    </template>
@endsection
@push('scripts')
    <script>
        // Video Preview
        $(document).ready(function($) {
            // Click button to activate hidden file input
            $('.fileuploader-btn').on('click', function() {
                $('.fileuploader').click();
            });

            // Click above calls the open dialog box
            // Once something is selected the change function will run
            $('.fileuploader').change(function() {
                $('#fileSizeError').remove();
                if (this.files[0].size > 50000000) {
                    $('.videoDiv').after(
                        '<span id="fileSizeError" style="color: red;">Please upload a file less than 50MB. Thanks!</span>'
                        );
                    $(this).val('');
                    $('#nextBtn').addClass('disabled');
                } else {
                    $('#fileSizeError').remove(); // Remove the error message
                    $('#nextBtn').removeClass('disabled'); // Re-enable the click action
                }
                // Check if a file has been selected
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    var file = this.files[0];

                    if (file.type.startsWith('image/')) {
                        reader.onload = function(event) {
                            $('.video').empty();
                            $('.video').append('<img src="' + event.target.result +
                                '" width="200" height="160">');
                        };
                        reader.readAsDataURL(file);
                        $('.video').removeClass('bgImg');
                    } else if (file.type.startsWith('video/')) {
                        reader.onload = function(event) {
                            var fileContent = event.target.result;
                            $('.video').empty();
                            $('.video').append('<video src="' + fileContent +
                                '" width="200" height="160" controls autoplay></video>');
                        };
                        reader.readAsDataURL(file);
                        $('.video').removeClass('bgImg');
                    } else {
                        // File is neither image nor video
                        alert('Please select either an image or a video file.');
                        this.value = ''; // Clear the file input
                        return;
                    }

                    // Hide the preview icon and show the upload button
                    $('.video').removeClass('bgImg');
                    $('.upload-button').show();
                } else {
                    // If no file is selected, and if .video container is empty, show the preview icon and hide the add button
                    if ($('.video').is(':empty')) {
                        $('.video').addClass('bgImg');
                        $('.upload-button').show();
                    } else {
                        // If a file already exists, hide the preview icon and show the add button
                        $('.video').removeClass('bgImg');
                        $('.upload-button').show();
                    }
                }
            });
        });




        // Video Preview
        function initImageUpload(box) {
            let uploadField = box.querySelector('.image-upload');

            uploadField.addEventListener('change', getFile);

            function getFile(e) {
                let file = e.currentTarget.files[0];
                checkType(file);
            }

            function previewImage(file) {
                let thumb = box.querySelector('.js--image-preview'),
                    reader = new FileReader();

                reader.onload = function() {
                    thumb.style.backgroundImage = 'url(' + reader.result + ')';
                }
                reader.readAsDataURL(file);
                thumb.className += ' js--no-default';
            }

            function checkType(file) {
                let imageType = /image.*/;
                if (!file.type.match(imageType)) {
                    throw 'Datei ist kein Bild';
                } else if (!file) {
                    throw 'Kein Bild gewählt';
                } else {
                    previewImage(file);
                }
            }

        }

        // initialize box-scope
        var boxes = document.querySelectorAll('.box');

        for (let i = 0; i < boxes.length; i++) {
            let box = boxes[i];
            initDropEffect(box);
            initImageUpload(box);
        }



        /// drop-effect
        function initDropEffect(box) {
            let area, drop, areaWidth, areaHeight, maxDistance, dropWidth, dropHeight, x, y;

            // get clickable area for drop effect
            area = box.querySelector('.js--image-preview');
            area.addEventListener('click', fireRipple);

            function fireRipple(e) {
                area = e.currentTarget
                // create drop
                if (!drop) {
                    drop = document.createElement('span');
                    drop.className = 'drop';
                    this.appendChild(drop);
                }
                // reset animate class
                drop.className = 'drop';

                // calculate dimensions of area (longest side)
                areaWidth = getComputedStyle(this, null).getPropertyValue("width");
                areaHeight = getComputedStyle(this, null).getPropertyValue("height");
                maxDistance = Math.max(parseInt(areaWidth, 10), parseInt(areaHeight, 10));

                // set drop dimensions to fill area
                drop.style.width = maxDistance + 'px';
                drop.style.height = maxDistance + 'px';

                // calculate dimensions of drop
                dropWidth = getComputedStyle(this, null).getPropertyValue("width");
                dropHeight = getComputedStyle(this, null).getPropertyValue("height");

                // calculate relative coordinates of click
                // logic: click coordinates relative to page - parent's position relative to page - half of self height/width to make it controllable from the center
                x = e.pageX - this.offsetLeft - (parseInt(dropWidth, 10) / 2);
                y = e.pageY - this.offsetTop - (parseInt(dropHeight, 10) / 2) - 30;

                // position drop and animate
                drop.style.top = y + 'px';
                drop.style.left = x + 'px';
                drop.className += ' animate';
                e.stopPropagation();

            }
        }

        function validateFile() {
            const fileInput = document.querySelector('input[name="video"]');
            const fileSizeError = document.getElementById('fileSizeError');
            const maxFileSize = 5 * 1024 * 1024; // 5MB

            if (fileInput.files[0].size > maxFileSize) {
                fileSizeError.style.display = 'block';
                fileInput.value = ''; // Clear the file input to prevent submission
                return false;
            } else {
                fileSizeError.style.display = 'none';
                return true;
            }
        }

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
        function add_web_link() {
            var link_row = $('.link_temp').html();
            $('.link_btn').before(link_row);
            initialize();
        }

        function add_promotional() {
            var promo = $('.promo_temp').html();
            $('.promotional_btn').before(promo);
            initialize();
        }
        $(function() {
            $('.add-row').on('click', function() {
                var socialRow =
                    `<tr>
            <td>
                <select name="socialType[]" class="form-select">
                    <option value="Facebook">Facebook</option>
                    <option value="YouTube">YouTube</option>
                    <option value="LinkedIn">LinkedIn</option>
                    <option value="Twitter">Twitter</option>
                    <option value="Instagram">Instagram</option>
                </select>
            </td>
            <td>
                <input type="text" name="social_link[]" class="form-control">
            </td>
        </tr>`;
                $('.social-links').append(socialRow);
            });
        });
        $(function() {
            $('.add-links').on('click', function() {
                var links =
                    `<tr>
            <td>
                <input type="text" name="website_link[]" class="form-control">
                </td>
                <td>
                    <input type="text" name="reviews_link[]" class="form-control">
            </td>
        </tr>`;
                $('.links').append(links);
            });
        });
    </script>
    <script>
        function changeAuctionType(v) {
            if (v == "Normal (Timer)") {
                $('.auction_length').val("");
                $('.auction_length').parent().children('.option-container').removeClass('active');
                $('.traditional-length').hide();
                $('.normal-length').show();
            } else {
                $('.auction_length').val("");
                $('.auction_length').parent().children('.option-container').removeClass('active');
                $('.traditional-length').show();
                $('.normal-length').hide();
            }
        }
        $(function() {});
    </script>
    <script>
        $(function() {
            $(document).ready(function() {
                $('.multiple').select2();

                $('.select2').each(function() {
                    $(this).prependTo($(this).parent('.select2-parent'));
                });
            });
        });
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

        function validateFile() {
            const fileInput = document.querySelector('input[name="video_file"]');
            const fileSizeError = document.getElementById('fileSizeError');
            const maxFileSize = 5 * 1024 * 1024; // 5MB

            if (fileInput.files[0].size > maxFileSize) {
                fileSizeError.style.display = 'block';
                fileInput.value = ''; // Clear the file input to prevent submission
                return false;
            } else {
                fileSizeError.style.display = 'none';
                return true;
            }
        }
    </script>
    <script>
        $(document).ready(function(){
            $(document).on('change', '#broker_compensation', function(){
                $('.compensation_broker_yes').removeClass('d-none');
            });

            $(document).on('change', '#handle_compensation', function(){
                let val = $(this).val();
                if(val !== 'No compensation will be offered to the tenant’s broker.'){
                    $('.handle_compensation_broker_yes').removeClass('d-none');
                }else{
                    $('.handle_compensation_broker_yes').addClass('d-none');
                }
            })

            $(document).on('change', '#compensation_amount', function(){
                $('.compensation_amount_yes').removeClass('d-none');
            })

            $(document).on('change', '#early_termination', function(){
                $('.early_termination_yes').removeClass('d-none');
            })

            $(document).on('change', '#compensation_new_lease_amount', function(){
                $('.compensation_new_lease_amount').removeClass('d-none');
            })

            $(document).on('change', '#payment_timing', function(){
                $('.payment_timing_days').removeClass('d-none');
            })
        })
    </script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
    </script>
@endpush
