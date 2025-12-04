<div class="wizard-step" data-step="23" data-old="24">
    @php
        $termLease = [
            ['name' => 'Absolute (Triple) Net', 'target' => ''],
            ['name' => 'Gross Lease', 'target' => ''],
            ['name' => 'Gross Percentages', 'target' => ''],
            ['name' => 'Ground Lease', 'target' => ''],
            ['name' => 'Lease Option', 'target' => ''],
            ['name' => 'Modified Gross', 'target' => ''],
            ['name' => 'Net Lease', 'target' => ''],
            ['name' => 'Net Net', 'target' => ''],
            ['name' => 'Other', 'target' => ''],
            ['name' => 'Pass Throughs', 'target' => ''],
            ['name' => 'Purchase Option', 'target' => ''],
            ['name' => 'Renewal Option', 'target' => ''],
            ['name' => 'Sale-Leaseback', 'target' => ''],
            ['name' => 'Seasonal', 'target' => ''],
            ['name' => 'Special Available (CLO)', 'target' => ''],
            ['name' => 'Varied Terms', 'target' => ''],
            ['name' => 'Other', 'target' => '.other_terms_lease'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold"> Terms of Lease:</label>

        <select class="grid-picker" name="termLease[]" id="appliances"
            style="justify-content: flex-start;" multiple required>
            <option value="">Select</option>
            @foreach ($termLease as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" style="width:calc(33.33% - 10px);"
                    data-icon='<i class="fa-solid fa-calendar-days"></i>' {{isset($auction->get->termLease) && in_array($item['name'], $auction->get->termLease) ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>

        <div class="form-group other_terms_lease d-none">
            <label class="fw-bold">Terms of Lease:<span class="text-danger"></span></label>
            <input type="text" class="form-control has-icon" placeholder="" value="{{isset($auction->get->termLeaseOther) ? $auction->get->termLeaseOther : '' }}"
                name="termLeaseOther" data-icon="fa-regular fa-calendar-days"
                id="occupant_type_input" required />
        </div>
    </div>
</div>
<div class="wizard-step" data-step="24" data-old="25">
    @php
        $occupant_types = [
            ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
            ['name' => 'Occupied', 'target' => '.custom_occupant_type', 'icon' => 'fa-regular fa-circle-check'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">What is the current occupancy status of the property?</label>
        <select class="grid-picker" name="occupant_type" id="occupant_type_select"
            style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($occupant_types as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->occupant_type) && $auction->get->occupant_type == $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group custom_occupant_type d-none">
        <label class="fw-bold">When is the property occupied until?<span
                class="text-danger"></span></label>
        <input type="date" class="form-control has-icon" placeholder="" value="{{isset($auction->get->occupied_until) ? $auction->get->occupied_until : '' }}"
            name="occupied_until" data-icon="fa-solid fa-calendar-days"
            id="occupant_type_input" required />
    </div>
</div>
<div class="wizard-step" data-step="25" data-old="26">
    <div class="form-group">
        <label class="fw-bold">
            Desired Rental Amount:
        </label>
        <input type="number" class="form-control has-icon" name="expectation" value="{{isset($auction->get->expectation) ? $auction->get->expectation : '' }}"
            data-icon="fa-solid fa-dollar-sign" id="expectation" required />
    </div>
</div>
<div class="wizard-step" data-step="26" data-old="27">
    <div class="form-group">

        <label class="fw-bold">Listing Availability Date:</label>
        <input type="date" name="custom_ready_timeframe" id="custom_ready_timeframe" value="{{isset($auction->get->custom_ready_timeframe) ? $auction->get->custom_ready_timeframe : '' }}"
            class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
    </div>
</div>
<div class="wizard-step" data-step="27" data-old="28">
    <div class="form-group">
        <label class="fw-bold">
            Desired Lease Length:
        </label>
        @php
            $lease_period = [
                ['name' => '3 Months', 'target' => ''],
                ['name' => '6 Months', 'target' => ''],
                ['name' => '9 Months', 'target' => ''],
                ['name' => '1 Year', 'target' => ''],
                ['name' => '2 Years', 'target' => ''],
                ['name' => '3-5 Years', 'target' => ''],
                ['name' => '5+ Years', 'target' => ''],
                ['name' => 'Month to Month', 'target' => ''],
                ['name' => 'Other', 'target' => '.custom_lease_period'],
            ];
        @endphp
        <select name="lease_period" id="lease_period" class="grid-picker"
            style="justify-content: flex-start;" required>
            <option value=""></option>
            @foreach ($lease_period as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column" style="width:calc(20% - 10px);"
                    data-icon='<i class="fa-solid fa-calendar-days"></i>' {{isset($auction->get->lease_period) && $auction->get->lease_period == $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group custom_lease_period d-none">
        <label class="fw-bold">Desired Lease Length: </label>
        <input type="text" class="form-control has-icon" name="custom_lease_period" value="{{isset($auction->get->custom_lease_period) ? $auction->get->custom_lease_period : '' }}"
            data-icon="fa-solid fa-calendar-days" id="custom_lease_period" required />
    </div>
</div>
<div class="wizard-step" data-step="28" data-old="29">
    <div class="form-group ">
        <label class="fw-bold">
            What is the timeframe offered to the agent in the landlord agency agreement? 
        </label>
        @php
            $listing_terms_res = [
                ['name' => '3 Months', 'target' => ''],
                ['name' => '6 Months', 'target' => ''],
                ['name' => '9 Months', 'target' => ''],
                ['name' => '12 Months', 'target' => ''],
                ['name' => 'Negotiable', 'target' => ''],
                ['name' => 'Other', 'target' => '.custom_listing_terms_residential'],
            ];
        @endphp
        <select name="listing_term" id="listing_term" class="grid-picker"
            style="justify-content: flex-start;" required>
            <option value=""></option>
            @foreach ($listing_terms_res as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column" style="width:calc(20% - 10px);"
                    data-icon='<i class="fa-solid fa-calendar-days"></i>' {{isset($auction->get->listing_term) && $auction->get->listing_term == $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group custom_listing_terms_residential d-none ">
        <label class="fw-bold">What is the timeframe offered to the agent in the Landlord
            Agency
            Agreement?</label>
        <input type="text" class="form-control has-icon" placeholder=""
            name="custom_listing_terms" data-icon="fa-solid fa-calendar-days" value="{{isset($auction->get->custom_listing_terms) ? $auction->get->custom_listing_terms : '' }}"
            id="custom_listing_terms" required />
    </div>
    {{-- </span> --}}
</div>