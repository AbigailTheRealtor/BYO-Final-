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

                <form class="p-4 pt-0 mainform" action="{{ route('landlord.agent.auction.bid.save') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                    @php
                        $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <div class="wizard-step">

                        @php
                            $listing_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '12 months', 'target' => ''], ['name' => 'Other', 'target' => '.custom_listing_terms']];
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">What is the agent's contract timeframe for working with the landlord? </label>
                            <select class="grid-picker" name="listing_terms" id="listing_terms"
                                style="justify-content: flex-start;">
                                <option value="">Select</option>
                                @foreach ($listing_terms as $listing_term)
                                    <option value="{{ $listing_term['name'] }}" data-target="{{ $listing_term['target'] }}"
                                        class="card flex-row pt-0 pb-0" style="width:calc(25% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;position:relative;top:-5px;"></i>'>
                                        {{ $listing_term['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group custom_listing_terms d-none">
                            <label class="fw-bold">What is the agent's contract timeframe for working with the landlord?</label>
                            <input type="text" class="form-control has-icon"
                                placeholder="Enter Duration for Agreement" name="custom_listing_terms" data-icon="fa-regular fa-check-circle"
                                id="custom_listing_terms" required />
                        </div>
                        <div class="form-group">
                            <label class="fw-bold" for="offering_price">Offered Commission: % or $</label>
                            <input type="text" name="offering_price" placeholder="e.g 5% or $1000"
                                id="offering_price" class="form-control hide_arrow" data-icon="fa-solid fa-percent"
                                data-msg-required="Please enter Offered Commission" required>
                        </div>
                    </div>
                    <div class="wizard-step">
                        <div class="row form-group">
                            <div class="form-group">
                                <label class="fw-bold">Website Link:</label>
                                <input type="url" name="website_link[]" id="website_link" placeholder="Website Link 1"
                                    class="form-control has-icon" data-icon="fa-solid fa-link">
                                <input type="url" name="website_link[]" id="website_link" placeholder="Website Link 2"
                                    class="form-control has-icon" data-icon="fa-solid fa-link">
                                <input type="url" name="website_link[]" id="website_link" placeholder="Website Link 3"
                                    class="form-control has-icon" data-icon="fa-solid fa-link">
                            </div>

                            <div class="form-group">
                                <label class="fw-bold">Reviews Link:</label>
                                <input type="text" class="form-control" name="reviews_link"
                                    value="{{ old('reviews_link') }}" />
                            </div>

                        </div>

                        <div class="row form-group">
                            <div class="col-md-12 mb-2">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th colspan="2" class="text-center">Social Media Platforms</th>
                                        </tr>
                                        <tr>
                                            <th>Type</th>
                                            <th>Link</th>
                                        </tr>
                                    </thead>
                                    <tbody class="social-links">
                                        <tr>
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

                        {{-- <div class="form-group">
                            <label class="fw-bold">Add Licensed Year:</label>
                            <input type="text" class="form-control" name="license_year"
                               />
                        </div> --}}

                    </div>
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">About Agent:<span class="text-danger"></span></label>
                            <textarea class="form-control" name="bio" rows="5" required>{{ old('bio') }}</textarea>
                        </div>

                    </div>
                    <div class="wizard-step">

                        <div class="form-group">
                            <label class="fw-bold">Why should you be hired as their agent? <span class="text-danger"></span></label>
                            <textarea class="form-control" name="why_hire_you" rows="5" required>{{ old('why_hire_you') }}</textarea>
                        </div>
                    </div>
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">What sets you apart from other agents? <span class="text-danger"></span></label>
                            <textarea class="form-control" name="what_sets_you_apart" rows="5" required>{{ old('what_sets_you_apart') }}</textarea>
                        </div>
                    </div>
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">What is your marketing strategy?<span class="text-danger"></span></label>
                            <textarea class="form-control" name="marketing_plan" rows="5" required>{{ old('marketing_plan') }}</textarea>
                        </div>
                    </div>

                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold">Video Listing Presentation:</label>
                            <input type="text" class="form-control" name="video_url">
                            <div class="d-flex align-items-baseline mt-1">
                                <input type="file" class="form-control" name="video_file">
                            </div>
                        </div>


                        <div class="form-group">
                            <label class="fw-bold">Promotional marketing materials, such as postcards, flyers, brochures, etc:</label>
                            <div class="d-flex align-items-baseline">
                                <input type="file" class="form-control" name="note">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold">Business Card:</label>
                            <div class="d-flex align-items-baseline">
                                <input type="file" class="form-control" name="card">
                            </div>
                        </div>
                    </div>
                    <div class="wizard-step">
                        <div class="form-group">
                            <label class="fw-bold" for="first_name">First Name:</label>
                            <input type="text" name="first_name" placeholder="John" id="first_name"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user-tie"
                                data-msg-required="Please enter First Name" value="{{Auth::user()->first_name}}" autofocus>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="last_name">Last Name:</label>
                            <input type="text" name="last_name" placeholder="Smith" id="last_name"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user-tie"
                                data-msg-required="Please enter Last Name" value="{{Auth::user()->last_name}}" autofocus>
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="agent_phone">Phone Number:</label>
                            <input type="text" name="agent_phone" placeholder="" id="agent_phone"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-phone"
                                data-msg-required="Please enter Phone Number" value="{{Auth::user()->phone}}">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="agent_email">Email:</label>
                            <input type="email" name="agent_email" placeholder="john@example.com" id="agent_email"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-envelope"
                                data-msg-required="Please enter Email" value="{{Auth::user()->email}}">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="agent_brokerage">Brokerage:</label>
                            <input type="text" name="agent_brokerage" placeholder="" id="agent_brokerage"
                                class="form-control has-icon hide_arrow" data-icon="fa-regular fa-circle-check"
                                data-msg-required="Please enter Brokerage" value="{{Auth::user()->brokerage}}">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="agent_license_no">Real Estate License #:</label>
                            <input type="text" name="agent_license_no" placeholder="" id="agent_license_no"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-card"
                                data-msg-required="Please enter License Number"  value="{{Auth::user()->license_no}}">
                        </div>

                        <div class="form-group">
                            <label class="fw-bold" for="mls_id">NAR Member ID (NRDS ID):</label>
                            <input type="text" name="mls_id" placeholder="" id="mls_id"
                                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-badge"
                                data-msg-required="Please enter MLS ID"  value="{{Auth::user()->mls_id}}">
                        </div>
                    </div>

                    <div class="wizard-step">

                        @php
                            $services_data = [];
                            if($auction->get->property_type =='Residential Property'){
                                $services_data[] = ['target' => '', 'name' => 'List the property on the MLS.'];
                                $services_data[] = ['target' => '', 'name' => 'List the property on major real estate websites, including Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, which will generate thousands of views.'];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'List the property on the Bid Your Offer platform.',
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Conduct tenant screening with a thorough application process that includes credit, criminal, background, eviction, and income verification checks.',
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Provide professional photography.',
                                ];
                                $services_data[] = ['target' => '', 'name' => 'Provide aerial photography.'];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => "Provide a professional video to showcase the property's interior and exterior.",
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => "Provide a 3D tour to showcase the property's interior.",
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => "Market the property to various groups, pages, and affiliates.",
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => "Promote the property on social media platforms.",
                                ];

                                $services_data[] = [
                                    'target' => '',
                                    'name' => "Sending email alerts to tenants searching for properties that match the property's criteria the moment the property is listed directly through the MLS.",
                                ];

                                $services_data[] = [
                                    'target' => '',
                                    'name' => "Expert advice on setting an optimal rental price based on market analysis and current trends.",
                                ];

                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Comprehensive property marketing strategy to attract a larger pool of potential tenants.',
                                ];

                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Conducting property showings and viewings for interested tenants.',
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Assisting with lease renewal negotiations and adjustments to rental terms.',
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Handling negotiations and drafting the rental agreement or lease contract.',
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Assisting with tenant move-in and move-out inspections.',
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Coordinating property maintenance and repairs through trusted contractors and vendors.',
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Offering ongoing property management services, including rent collection, property inspections, and tenant communication.',
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Providing guidance on landlord-tenant laws and regulations to ensure legal compliance.',
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => 'Handling tenant inquiries, maintenance requests, and resolving any issues that may arise during the tenancy.',
                                ];
                                $services_data[] = [
                                    'target' => '',
                                    'name' => "Providing regular feedback and updates on the property's performance.",
                                ];
                                $services_data[] = ['target' => '.other_services', 'name' => 'Other - Add additional services as provided.'];
                            }else{
                                $services_data[] = ['target' => '', 'name' => 'List the property on Loopnet.'];
                                $services_data[] = ['target' => '', 'name' => 'List the property on Crexi.'];
                                $services_data[] = ['target' => '','name' => 'List the property on the MLS.'];
                                $services_data[] = ['target' => '','name' => 'List the property on the Bid Your Offer platform.'];
                                $services_data[] = ['target' => '','name' => 'Conduct tenant screening with a thorough application process that includes credit, criminal, background, eviction, and income verification checks.'];
                                $services_data[] = ['target' => '', 'name' => 'Provide professional photography.'];
                                $services_data[] = ['target' => '','name' => "Provide aerial photography."];
                                $services_data[] = ['target' => '','name' => "Provide a professional video to showcase the property's interior and exterior."];
                                $services_data[] = ['target' => '','name' => "Provide a 3D tour to showcase the property's interior."];
                                $services_data[] = ['target' => '','name' => "Market the property to various groups, pages, and affiliates."];

                                $services_data[] = ['target' => '','name' => "Promote the property on social media platforms."];

                                $services_data[] = ['target' => '','name' => "Sending email alerts to tenants searching for properties that match the property's criteria the moment the property is listed directly through the MLS."];

                                $services_data[] = ['target' => '','name' => 'Expert advice on setting an optimal rental price based on market analysis and current trends.'];

                                $services_data[] = ['target' => '','name' => 'Comprehensive property marketing strategy to attract a larger pool of potential tenants.'
                                ];
                                $services_data[] = ['target' => '','name' => 'Conducting property showings and viewings for interested tenants.'];
                                $services_data[] = ['target' => '','name' => 'Assisting with lease renewal negotiations and adjustments to rental terms.'];
                                $services_data[] = ['target' => '','name' => 'Handling negotiations and drafting the rental agreement or lease contract.'];
                                $services_data[] = ['target' => '','name' => 'Assisting with tenant move-in and move-out inspections.'];
                                $services_data[] = ['target' => '','name' => 'Coordinating property maintenance and repairs through trusted contractors and vendors.'];
                                $services_data[] = ['target' => '','name' => 'Offering ongoing property management services, including rent collection, property inspections, and tenant communication.'];
                                $services_data[] = ['target' => '','name' => 'Providing guidance on landlord-tenant laws and regulations to ensure legal compliance.'];
                                $services_data[] = ['target' => '','name' => "Handling tenant inquiries, maintenance requests, and resolving any issues that may arise during the tenancy."];
                                $services_data[] = ['target' => '','name' => "Providing regular feedback and updates on the property's performance."];
                                $services_data[] = ['target' => '.other_services', 'name' => 'Other - Add additional services as provided.'];
                            }
                        @endphp
                        <div class="form-group">
                            <label class="fw-bold">Select the included services that the agent will provide to the landlord:</label>
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
                                value="{{ @$auction->get->other_services }}" class="form-control">
                        </div>
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
@endsection
@push('scripts')
<script>
    $(function() {
        $('.add-row').on('click', function() {
            var socialRow = `<tr>
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
    // document.getElementById('auction_type').change();
    $(function() {
        // changeAuctionType("Normal (Timer)");
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
