<div class="wizard-step" data-step="29" data-old="30">
    <h4>Owner's Agreement on Commission Rates:</h4>
    <h6>The owner agrees to compensate the broker as follows, including paying any applicable taxes on the broker's services, if the owner enters into a lease of the property with a tenant during the listing period, regardless of whether the tenant fulfills the terms of the lease; or if, during the listing period, the broker procures a tenant who is ready, willing, and able to lease the property under the terms of this agreement, or terms acceptable to the owner. All commission is negotiable.</h6>
    
    <div class="form-group">
        @php
            $broker_compensation = [
                [ 'name' => "___% of each rental period", 'target' => ''],
                ['name' => "___% of the gross lease value", 'target' => ''],
                ['name' => "____% of the first month's rent", 'target' => ''],
                ['name' => 'Fixed amount: $____ ', 'target' => ''],
                ['name' => 'Negotiable', 'target' => ''],
            ];
        @endphp
        <label class="fw-bold">What compensation will the owner provide to the listing broker for their services?</label>
        <select class="grid-picker" name="broker_compensation" id="broker_compensation"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($broker_compensation as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->broker_compensation) && $auction->get->broker_compensation == $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group compensation_broker_yes d-none ">
            <label class="fw-bold">What compensation will the owner provide to the listing broker for their services?</label>
            <input type="number" class="form-control has-icon" placeholder="" value="{{isset($auction->get->compensation_percent) ? $auction->get->compensation_percent : '' }}"
                name="compensation_percent" data-icon="fa-solid fa-dollar-sign" required />
        </div>                                
    </div>
    <div class="form-group">
        @php
            $handle_compensation = [
                ['name' => "Allow the listing broker to compensate the tenant's broker from the listing broker's commission, if applicable.", 'target' => ''],
                ['name' => "Pay the tenant's broker separately, if applicable.", 'target' => ''],
                ['name' => "No compensation will be offered to the tenant’s broker.", 'target' => ''],
            ];
        @endphp
        <label class="fw-bold">How would the owner prefer to handle compensation for a tenant's broker?</label>
        <select class="grid-picker" name="handle_compensation" id="handle_compensation"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($handle_compensation as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->handle_compensation) && $auction->get->handle_compensation == $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group handle_compensation_broker_yes d-none ">
            @php
            $compensation_amount = [
                [ 'name' => "___% of the gross lease value", 'target' => ''],
                ['name' => "____% of the first month’s rent", 'target' => ''],
                ['name' => 'Fixed amount: $____', 'target' => ''],
                ['name' => 'Negotiable', 'target' => ''],
            ];
            @endphp
            <label class="fw-bold">What compensation is being offered to the tenant's broker?</label>
            <select class="grid-picker" name="compensation_amount" id="compensation_amount"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($compensation_amount as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->compensation_amount) && $auction->get->compensation_amount == $item['name'] ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group compensation_amount_yes d-none ">
                <label class="fw-bold">What compensation is being offered to the tenant's broker?</label>
                <input type="number" class="form-control has-icon" placeholder=""
                    name="compensation_tenant_broker" data-icon="fa-solid fa-dollar-sign" value="{{isset($auction->get->compensation_tenant_broker) ? $auction->get->compensation_tenant_broker : '' }}" required />
            </div> 
        </div>                                
    </div>
    <div class="form-group">
        @php
            $payment_timing = [
                ['name' => "Deducted from rent collected by the broker; the owner will pay the balance (if any) within ___ calendar days of the rent due date.", 'target' => ''],
                ['name' => "Paid within ___ calendar days after the lease agreement is executed.", 'target' => ''],
                ['name' => "Paid within ___ calendar days of each tenant's rent payment.", 'target' => ''],
            ];
        @endphp
        <label class="fw-bold">Payment Timing for Broker Fees:</label>
        <select class="grid-picker" name="payment_timing" id="payment_timing"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($payment_timing as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->payment_timing) && $auction->get->payment_timing == $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group payment_timing_days d-none ">
            <label class="fw-bold">Payment Timing for Broker Fees:</label>
            <input type="number" class="form-control has-icon" placeholder=""
                name="payment_timing_days" data-icon="fa-regular fa-check-circle" value="{{isset($auction->get->payment_timing_days) ? $auction->get->payment_timing_days : '' }}" required />
        </div>                                
    </div>
</div>
<div class="wizard-step" data-step="30">
    <h4>Early Termination and Protection Period:</h4>
    <div class="form-group">
        @php
            $early_termination = [
                ['name' => 'The owner may terminate this agreement by signing a withdrawal agreement and paying a cancellation fee of $______ plus tax.', 'target' => ''],
                ['name' => "If the property is leased to a tenant during the termination or protection period, the broker may void the termination and collect full compensation (minus the cancellation fee).", 'target' => ''],
            ];
        @endphp
        <label class="fw-bold">Early Termination:</label>
        <select class="grid-picker" name="early_termination" id="early_termination"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($early_termination as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->early_termination) && $auction->get->early_termination == $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group early_termination_yes d-none ">
            <label class="fw-bold">Early Termination:</label>
            <input type="number" class="form-control has-icon" placeholder=""
                name="early_termination_amount" data-icon="fa-regular fa-check-circle" value="{{isset($auction->get->early_termination_amount) ? $auction->get->early_termination_amount : '' }}" required />
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
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->protection_period) && $auction->get->protection_period == $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>    
        <div class="form-group protection_period_yes d-none ">
            <label class="fw-bold">Protection Period:</label>
            <input type="number" class="form-control has-icon" placeholder=""
                name="protection_period_days" data-icon="fa-regular fa-check-circle" value="{{isset($auction->get->protection_period_days) ? $auction->get->protection_period_days : '' }}" required />
        </div>                        
    </div>
</div>
<div class="wizard-step" data-step="31">
    <h4>New Leases and Renewals:</h4>
    <div class="form-group">
        @php
            $compensation_new_lease = [
                ['name' => 'Yes', 'target' => '.new_lease_yes'],
                ['name' => "No", 'target' => ''],
                ['name' => "Negotiable", 'target' => ''],
            ];
        @endphp
        <label class="fw-bold">If the owner enters into a new lease or renewal with a tenant placed in the property by or through the broker, does the owner agree to pay the broker compensation for the new lease or renewal?</label>
        <select class="grid-picker" name="compensation_new_lease" id="compensation_new_lease"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($compensation_new_lease as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->compensation_new_lease) && $auction->get->compensation_new_lease == $item['name'] ? 'selected' : '' }} >
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
                ['name' => "Negotiable", 'target' => ''],
            ];
            @endphp
            <label class="fw-bold">What compensation will the owner provide to the listing broker for a new lease or lease renewal?</label>
            <select class="grid-picker" name="compensation_new_lease_percent" id="compensation_new_lease_amount"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($compensation_amount as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->compensation_new_lease_percent) && $auction->get->compensation_new_lease_percent == $item['name'] ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group compensation_new_lease_amount d-none ">
                <label class="fw-bold">What compensation will the owner provide to the listing broker for a new lease or lease renewal?</label>
                <input type="number" class="form-control has-icon" placeholder=""
                    name="compensation_new_lease_amount" data-icon="fa-regular fa-check-circle" value="{{isset($auction->get->compensation_new_lease_amount) ? $auction->get->compensation_new_lease_amount : '' }}" required />
            </div> 
        </div>                                
    </div>
</div>
<div class="wizard-step" data-step="32" data-old="32">
    <div class="row">
        <div class="form-group mt-4 col-md-12">
            <label class='fw-bold'>
                What are the most important aspects the landlord will consider when hiring a
                real estate agent?
            </label>
            <textarea name="description" placeholder="" class="form-control" rows="5">{{ old('description', isset($auction->get->description) ? $auction->get->description : '') }}</textarea>
        </div>
    </div>

    <div class="row">
        <div class="form-group mt-4 col-md-12">
            <label class='fw-bold'>
                What additional details would the landlord like to share with the agent?
            </label>
            <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5">{{isset($auction->get->important_info) ? $auction->get->important_info : '' }}</textarea>
        </div>
    </div>
</div>
<div class="wizard-step residential_remove" data-step="33" data-old="36">
    <span class="resFields">
        <div class="form-group">
            <label class="fw-bold">
                Select the services that the landlord requests from an agent:
            </label>
            @php
                $serviceRes = [
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
                        'name' => 'Host an Open House(s).',
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
                        'name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing.',
                        'target' => '',
                    ],
                    [
                        'name' => 'Other - Add additional services as needed.',
                        'target' => '.otherRes',
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

            @endphp
            <select name="services[]" multiple class="grid-picker"
                style="justify-content: flex-start;" required>
                <option value=""></option>
                @foreach ($serviceRes as $item)
                    <option value="{{ $item['name'] }}"
                        data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(100%);"
                        data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>' {{isset($auction->get->services) && in_array($item['name'], $auction->get->services) ? 'selected' : '' }}>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group otherRes d-none">
            <label class="fw-bold">What additional services would the landlord like to request
                from an agent?
            </label>
            <input type="text" class="form-control has-icon" name="servicesOther" value="{{isset($auction->get->servicesOther) ? $auction->get->servicesOther : ''}}"
                data-icon="fa-solid fa-hand-point-right" id="custom_services_data"
                required />
        </div>
    </span>
    <span class="commercialFields">
        <div class="form-group">
            <label class="fw-bold">
                Select the services that the landlord requests from an agent:
            </label>
            @php
                $serviceCommercial = [
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
                        'name' => 'Other - Add additional services as needed.',
                        'target' => '.otherCommercial',
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
            @endphp
            <select name="services[]" multiple class="grid-picker"
                style="justify-content: flex-start;" required>
                <option value=""></option>
                @foreach ($serviceCommercial as $item)
                    <option value="{{ $item['name'] }}"
                        data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(100%);"
                        data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>' {{isset($auction->get->services) && in_array($item['name'], $auction->get->services) ? 'selected' : '' }}>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group otherCommercial d-none">
            <label class="fw-bold">What additional services would the landlord like to request
                from an agent?
            </label>
            <input type="text" class="form-control has-icon" name="servicesOther"
                data-icon="fa-solid fa-hand-point-right" id="custom_services_data" value="{{isset($auction->get->servicesOther) ? $auction->get->servicesOther : ''}}"
                required />
        </div>
    </span>
</div>
<div class="wizard-step" data-step="34" data-old="37">
    <h4>For a more personalized listing, you can include a picture of
        yourself and/or include a video of yourself providing additional information about your
        background and criteria.</h4>
    <div class="row form-group">
        <div class="col-md-6">
            <label class="fw-bold">First Name:</label>
            <input type="text" class="form-control has-icon" name="first_name"
                id="first_name" value="{{ isset($auction->get->first_name) ? $auction->get->first_name : '' }}"
                data-icon="fa-solid fa-user" required />
        </div>
        <div class="col-md-6">
            <label class="fw-bold">Last Name:</label>
            <input type="text" class="form-control has-icon" name="last_name"
                id="last_name" value="{{ isset($auction->get->last_name) ? $auction->get->last_name : '' }}"
                data-icon="fa-solid fa-user" required />
        </div>
    </div>
    <div class="row form-group">
        <div class="col-md-6">
            <label class="fw-bold">Phone Number:</label>
            <input type="text" class="form-control has-icon" name="phone"
                id="phone" value="{{ isset($auction->get->phone) ? $auction->get->phone : '' }}"
                data-icon="fa-solid fa-phone" required />
        </div>
        <div class="col-md-6">
            <label class="fw-bold">Email:</label>
            <input type="email" class="form-control has-icon" name="email"
                id="email" value="{{ isset($auction->get->email) ? $auction->get->email : '' }}"
                data-icon="fa-solid fa-envelope" required />
        </div>
    </div>
    <div class="row">
        <div class="col-6 video_div">
            <input type="hidden" name="video_type" class="video_type" value="video_upload">
            <div class="video_type_select d-flex align-items-center justify-content-left">
                <div class="form-check me-2">
                    <label class="form-check-label">
                        <input type="checkbox" class="video_upload form-check-input video_type_check"
                            name="video_upload" value="{{isset($auction->get->video_upload) ? 'check' : ''}}">
                        Video Upload
                    </label>
                </div>
                <div class="form-check me-2">
                    <label class="form-check-label">
                        <input type="checkbox" class="youtube_video form-check-input video_type_check"
                            name="youtube_video" value="{{isset($auction->get->youtube_video) ? 'check' : ''}}">
                        Youtube Video
                    </label>
                </div>
                <div class="form-check">
                    <label class="form-check-label">
                        <input type="checkbox" class="vimeo_video form-check-input video_type_check"
                            name="vimeo_video" value="{{isset($auction->get->vimeo_video) ? 'check' : ''}}">
                        Vimeo Video
                    </label>
                </div>
            </div>
            <div class="video-upload video-type-element">
                <label class="fw-bold mt-1">Video:</label>
                <div class="videoBox ">
                    <div class="video bgImg"></div>
                    <div class="form-group videoDiv">
                        <input type="file" class="fileuploader" name="video"
                            style="display: none;" accept="video/*">
                        <label for="fileuploader" class="fileuploader-btn">
                            <span class="upload-button">+</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="youtube-video video-type-element form-group d-none">
                <label class="fw-bold mt-1"> Youtube Video:</label>
                <input type="text" name="youtube_video_link" class="form-control" value="{{isset($auction->get->youtube_video_link) ? $auction->get->youtube_video_link : ''}}" placeholder="Enter Youtube Video Link"
                    placeholder="Youtube video link i.e. https://youtube.com/embed/videoId">
            </div>
            <div class="vimeo-video video-type-element form-group d-none">
                <label class="fw-bold mt-1">Vimeo Video:</label>
                <input type="text" name="vimeo_video_link" class="form-control" value="{{isset($auction->get->vimeo_video_link) ? $auction->get->vimeo_video_link : ''}}" placeholder="Enter Vimeo Video Link"
                    placeholder="Vimeo video link i.e. https://player.vimeo.com/video/videoId">
            </div>
        </div>
        <div class="col-6">
            <div class="upload form-group">
                <label class="fw-bold">Photo:</label>
                <div class="wrapper">
                    <div class="box">
                        <div class="js--image-preview"></div>
                        <div class="upload-options">
                            <label>
                                <input type="file" name="photo" class="image-upload"
                                    accept="image/*" />
                            </label>
                        </div>
                    </div>
                </div>
            </div>
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
            style="display: none;" id="saveBtn">Save</button>
    </div>
</div>