<div class="wizard-step" data-step='23'>
    <h4>Room Details:</h4>
    <input type="hidden" id="room_type_input" name="room_details_data" />
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
            ['name' => 'Dinette', 'target' => ''],
            ['name' => 'Dining Room', 'target' => ''],
            ['name' => 'Double Primary Bedroom', 'target' => ''],
            ['name' => 'Family Room', 'target' => ''],
            ['name' => 'Florida Room', 'target' => ''],
            ['name' => 'Foyer', 'target' => ''],
            ['name' => 'Game Room', 'target' => ''],
            ['name' => 'Garage Room', 'target' => ''],
            ['name' => 'Garage Apartment', 'target' => ''],
            ['name' => 'Great Room', 'target' => ''],
            ['name' => 'Gym', 'target' => ''],
            ['name' => 'Inside Utility', 'target' => ''],
            ['name' => 'Interior In-Law Suite', 'target' => ''],
            ['name' => 'Kitchen', 'target' => ''],
            ['name' => 'Laundry', 'target' => ''],
            ['name' => 'Library', 'target' => ''],
            ['name' => 'Living Room', 'target' => ''],
            ['name' => 'Loft', 'target' => ''],
            ['name' => 'Media Room', 'target' => ''],
            ['name' => 'Office', 'target' => ''],
            ['name' => 'Primary Bathroom', 'target' => ''],
            ['name' => 'Primary Bedroom', 'target' => ''],
            ['name' => 'Sauna', 'target' => ''],
            ['name' => 'Studio', 'target' => ''],
            ['name' => 'Study/Den', 'target' => ''],
            ['name' => 'Workshop', 'target' => ''],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Room Type:</label>
        <select class="grid-picker" name="room_type" id="room_typeRes" onChange="roomFtn();"
            style="justify-content: flex-start;" multiple required>
            <option value="">Select</option>
            @foreach ($room_types as $room_type)
                <option value="{{ $room_type['name'] }}" data-target="{{ $room_type['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $room_type['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div id="dynamicFieldsContainer"></div>
</div>
<div class="wizard-step" data-step='24'>
    <h4>Water and Dock Information:</h4>
    <div class="form-group ">
        @php
            $waterAccessOption = [
                [
                    'name' => 'Yes',
                    'target' => '.waterAccessYes',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
            ];
        @endphp
        <label class="fw-bold">Water Access:</label>
        <select class="grid-picker" name="waterAccessOpt" id="water_access" style="justify-content: flex-start;"
            multiple>
            <option value="">Select</option>
            @foreach ($waterAccessOption as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group waterAccessYes d-none ">
        @php
            $water_access = [
                ['name' => 'Bay/Harbor', 'target' => ''],
                ['name' => 'Bayou', 'target' => ''],
                ['name' => 'Beach', 'target' => ''],
                ['name' => 'Beach - Access Deeded', 'target' => ''],
                ['name' => 'Brackish Water', 'target' => ''],
                ['name' => 'Canal - Brackish', 'target' => ''],
                ['name' => 'Canal - Freshwater', 'target' => ''],
                ['name' => 'Canal - Saltwater', 'target' => ''],
                ['name' => 'Creek', 'target' => ''],
                ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''],
                ['name' => 'Gulf/Ocean', 'target' => ''],
                ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
                ['name' => 'Intracoastal Waterway', 'target' => ''],
                ['name' => 'Lagoon/Estuary', 'target' => ''],
                ['name' => 'Lake', 'target' => ''],
                ['name' => 'Lake - Chain of Lakes', 'target' => ''],
                ['name' => 'Limited Access', 'target' => ''],
                ['name' => 'Marina', 'target' => ''],
                ['name' => 'Pond', 'target' => ''],
                ['name' => 'River', 'target' => ''],
            ];
        @endphp
        <label class="fw-bold">Water Access:</label>
        <select class="grid-picker" name="water_access[]" id="water_access" style="justify-content: flex-start;"
            required multiple>
            <option value="">Select</option>
            @foreach ($water_access as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group ">
        <label class="fw-bold">Water View:</label>
        <select class="grid-picker" name="has_water_view" id="has_water_view" style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($yes_or_nos as $item)
                @php
                    if ($item['name'] == 'Yes') {
                        $target = '.water_view';
                    } else {
                        $target = '';
                    }
                @endphp
                <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    @php
        $water_views = [
            ['name' => 'Bay/Harbor - Full', 'target' => ''],
            ['name' => 'Bay/Harbor - Partial', 'target' => ''],
            ['name' => 'Bayou', 'target' => ''],
            ['name' => 'Beach', 'target' => ''],
            ['name' => 'Canal', 'target' => ''],
            ['name' => 'Creek', 'target' => ''],
            ['name' => 'Gulf/Ocean - Full', 'target' => ''],
            ['name' => 'Gulf/Ocean - Partial', 'target' => ''],
            ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
            ['name' => 'Intracoastal Waterway', 'target' => ''],
            ['name' => 'Lagoon/Estuary', 'target' => ''],
            ['name' => 'Lake', 'target' => ''],
            ['name' => 'Lake - Chain of Lakes', 'target' => ''],
            ['name' => 'Marina', 'target' => ''],
            ['name' => 'Pond', 'target' => ''],
            ['name' => 'River', 'target' => ''],
            ['name' => 'None', 'target' => ''],
        ];
    @endphp
    <div class="form-group water_view d-none">
        <label class="fw-bold">Water View:</label>
        <select class="grid-picker" name="water_view[]" id="water_view" style="justify-content: flex-start;" multiple
            required>
            <option value="">Select</option>
            @foreach ($water_views as $water_view)
                <option value="{{ $water_view['name'] }}" data-target="{{ $water_view['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $water_view['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group ">
        <label class="fw-bold">Water Extras:</label>
        <select class="grid-picker" name="has_water_extra" id="has_water_extra" style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($yes_or_nos as $item)
                @php
                    if ($item['name'] == 'Yes') {
                        $target = '.water_extras';
                    } else {
                        $target = '';
                    }
                @endphp
                <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
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
        <select class="grid-picker" name="water_extras[]" id="water_extras" style="justify-content: flex-start;"
            multiple>
            <option value="">Select</option>
            @foreach ($water_extras as $water_extra)
                <option value="{{ $water_extra['name'] }}" data-target="{{ $water_extra['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $water_extra['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group ">
        <label class="fw-bold">Water Frontage:</label>
        <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($yes_or_nos as $item)
                @php
                    if ($item['name'] == 'Yes') {
                        $target = '.waterFrontageYes';
                    } else {
                        $target = '';
                    }
                @endphp
                <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group waterFrontageYes d-none">
            @php
                $waterFrontageView = [
                    ['name' => 'Bay/Harbor', 'target' => ''],
                    ['name' => 'Bayou', 'target' => ''],
                    ['name' => 'Beach', 'target' => ''],
                    ['name' => 'Brackish Water', 'target' => ''],
                    ['name' => 'Canal - Brackish', 'target' => ''],
                    ['name' => 'Canal - Freshwater', 'target' => ''],
                    ['name' => 'Canal - Saltwater', 'target' => ''],
                    ['name' => 'Canal Front', 'target' => ''],
                    ['name' => 'Creek', 'target' => ''],
                    ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''],
                    ['name' => 'Gulf/Ocean', 'target' => ''],
                    ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
                    ['name' => 'Intracoastal Waterway', 'target' => ''],
                    ['name' => 'Lagoon/Estuary', 'target' => ''],
                    ['name' => 'Lake', 'target' => ''],
                    ['name' => 'Lake - Chain of Lakes', 'target' => ''],
                    ['name' => 'Marina', 'target' => ''],
                    ['name' => 'Pond', 'target' => ''],
                    ['name' => 'Riparian Rights', 'target' => ''],
                    ['name' => 'River', 'target' => ''],
                ];
            @endphp
            <label class="fw-bold">Water Frontage: </label>
            <select class="grid-picker" name="waterFrontageView[]" style="justify-content: flex-start;" multiple
                required>
                <option value="">Select</option>
                @foreach ($waterFrontageView as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="form-group ">
        <label class="fw-bold">Dock:</label>
        <select class="grid-picker" name="has_dock" id="has_dock" style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($yes_or_nos as $item)
                @php
                    if ($item['name'] == 'Yes') {
                        $target = '.dockYes';
                    } else {
                        $target = '';
                    }
                @endphp
                <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group dockYes d-none">
            @php
                $dock = [
                    ['name' => '2 Point Moorage', 'target' => ''],
                    ['name' => '3 Point Moorage', 'target' => ''],
                    ['name' => '4 Point Moorage', 'target' => ''],
                    ['name' => 'CATV', 'target' => ''],
                    ['name' => 'Clubhouse', 'target' => ''],
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
                    ['name' => 'Dry Dock', 'target' => ''],
                    ['name' => 'Fish Cleaning Station', 'target' => ''],
                    ['name' => 'Floating Dock', 'target' => ''],
                    ['name' => 'Harbormaster', 'target' => ''],
                    ['name' => 'Internet', 'target' => ''],
                    ['name' => 'Lift', 'target' => ''],
                    ['name' => 'Restroom/Shower', 'target' => ''],
                    ['name' => 'Wet Dock', 'target' => ''],
                    ['name' => 'None', 'target' => ''],
                    ['name' => 'Other', 'target' => '.otherDock'],
                ];
            @endphp
            <label class="fw-bold">Dock: </label>
            <select class="grid-picker" name="dock[]" style="justify-content: flex-start;" multiple required>
                <option value="">Select</option>
                @foreach ($dock as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group otherDock d-none">
                <label class="fw-bold">Dock Description:</label>
                <input type="text" name="dockDescription" class="form-control has-icon"
                    data-icon="fa-regular fa-check-circle">
            </div>
            <div class="form-group">
                <label class="fw-bold">Dock Lift Capacity:</label>
                <input type="text" name="dockLiftCapacity" class="form-control has-icon"
                    data-icon="fa-regular fa-check-circle">
            </div>
            <div class="form-group">
                <label class="fw-bold">Dock Year Built:</label>
                <input type="number" name="dockYearBuilt" class="form-control has-icon"
                    data-icon="fa-regular fa-calendar-days">
            </div>
            <div class="form-group">
                <label class="fw-bold">Dock Dimension:</label>
                <input type="text" name="dockDimension" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
                <label class="fw-bold">Dock Maintenance Fee:</label>
                <input type="number" name="dockMaintenanceFee" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign">
            </div>
            @php
                $dock = [
                    ['name' => 'Annual', 'target' => ''],
                    ['name' => 'Monthly', 'target' => ''],
                    ['name' => 'Quarterly', 'target' => ''],
                    ['name' => 'N/A', 'target' => ''],
                ];
            @endphp
            <label class="fw-bold">Dock Maintenance Fee Frequency:</label>
            <select class="grid-picker" name="dockMaintenanceFeeFrequency" style="justify-content: flex-start;"
                multiple required>
                <option value="">Select</option>
                @foreach ($dock as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="wizard-step" data-step='25'>
    @php
        $utilities = [
            ['name' => 'BB/HS Internet Available', 'target' => ''],
            ['name' => 'Cable Available', 'target' => ''],
            ['name' => 'Cable Connected', 'target' => ''],
            ['name' => 'Electric - Multiple Meters', 'target' => ''],
            ['name' => 'Electricity Available', 'target' => ''],
            ['name' => 'Electricity Connected', 'target' => ''],
            ['name' => 'Emergency Power', 'target' => ''],
            ['name' => 'Fiber Optics', 'target' => ''],
            ['name' => 'Fire Hydrant', 'target' => ''],
            ['name' => 'Mini Sewer', 'target' => ''],
            ['name' => 'Natural Gas Available', 'target' => ''],
            ['name' => 'Natural Gas Connected', 'target' => ''],
            ['name' => 'Phone Available', 'target' => ''],
            ['name' => 'Private', 'target' => ''],
            ['name' => 'Propane', 'target' => ''],
            ['name' => 'Public', 'target' => ''],
            ['name' => 'Sewer Available', 'target' => ''],
            ['name' => 'Sewer Connected', 'target' => ''],
            ['name' => 'Solar', 'target' => ''],
            ['name' => 'Sprinkler Meter', 'target' => ''],
            ['name' => 'Sprinkler Recycled', 'target' => ''],
            ['name' => 'Sprinkler Well', 'target' => ''],
            ['name' => 'Street Lights', 'target' => ''],
            ['name' => 'Underground Utilities', 'target' => ''],
            ['name' => 'Water - Multiple Meters', 'target' => ''],
            ['name' => 'Water Available', 'target' => ''],
            ['name' => 'Water Connected', 'target' => ''],
            ['name' => 'None', 'target' => ''],
            ['name' => 'Other', 'target' => '.otherUtilitiesRes'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Utilities:</label>
        <select class="grid-picker" name="utilities[]" id="utilities" style="justify-content: flex-start;" multiple
            required>
            <option value="">Select</option>
            @foreach ($utilities as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherUtilitiesRes d-none">
            <label for="" class="fw-bold">Utilities: </label>
            <input type="text" class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                name="otherUtilities">
        </div>
    </div>

    @php
        $sewers1 = [
            ['name' => 'Aerobic Septic', 'target' => ''],
            ['name' => 'PEP-Holding Tank', 'target' => ''],
            ['name' => 'Private Sewer', 'target' => ''],
            ['name' => 'Public Sewer', 'target' => ''],
            ['name' => ' Septic Tank', 'target' => ''],
            ['name' => ' None', 'target' => ''],
            ['name' => 'Other', 'target' => '.otherSewerRes'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Sewer:</label>
        <select class="grid-picker" name="sewer[]" id="sewer" style="justify-content: flex-start;" multiple
            required>
            <option value="">Select</option>
            @foreach ($sewers1 as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherSewerRes d-none">
            <label for="" class="fw-bold">Sewer: </label>
            <input type="text" class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                name="otherSewer">
        </div>
    </div>

    @php
        $waters = [
            ['name' => 'Canal/Lake For Irrigation', 'target' => ''],
            ['name' => 'Private', 'target' => ''],
            ['name' => 'Public', 'target' => ''],
            ['name' => 'Well', 'target' => ''],
            ['name' => 'Well Required', 'target' => ''],
            ['name' => 'None', 'target' => ''],
            ['name' => 'Other', 'target' => '.otherWaterRes'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Water:</label>
        <select class="grid-picker" name="water[]" id="water12" style="justify-content: flex-start;" multiple
            required>
            <option value="">Select</option>
            @foreach ($waters as $water)
                <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $water['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherWaterRes d-none">
            <label for="" class="fw-bold">Water: </label>
            <input type="text" class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                name="otherWater">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='26'>
    <div class="form-group ">
        @php
            $airConditioning = [
                ['name' => 'A/C - Office Only', 'target' => ''],
                ['name' => 'Central Air', 'target' => ''],
                ['name' => 'Humidity Control', 'target' => ''],
                ['name' => 'Mini-Split Unit(s)', 'target' => ''],
                ['name' => 'Wall/Window Unit(s)', 'target' => ''],
                ['name' => 'Zoned', 'target' => ''],
                ['name' => 'None', 'target' => ''],
                ['name' => 'Other', 'target' => '.otherAirConditionRes'],
            ];
        @endphp
        <label class="fw-bold">Air Conditioning: </label>
        <select class="grid-picker" name="airConditioning[]" id="utilities" style="justify-content: flex-start;"
            multiple required>
            <option value="">Select</option>
            @foreach ($airConditioning as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherAirConditionRes d-none">
            <label for="" class="fw-bold"> Air Conditioning: </label>
            <input type="text" class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                name="otherAirCondition">
        </div>
    </div>
    <div class="form-group ">
        @php
            $heatingFuel = [
                ['name' => 'Baseboard', 'target' => ''],
                ['name' => 'Central', 'target' => ''],
                ['name' => 'Central Building ', 'target' => ''],
                ['name' => 'Central Individual', 'target' => ''],
                ['name' => 'Electric', 'target' => ''],
                ['name' => 'Exhaust Fans', 'target' => ''],
                ['name' => 'Gas', 'target' => ''],
                ['name' => 'Heat Pump', 'target' => ''],
                ['name' => 'Heat Recovery Unit', 'target' => ''],
                ['name' => 'Natural Gas', 'target' => ''],
                ['name' => 'Oil', 'target' => ''],
                ['name' => 'Partial', 'target' => ''],
                ['name' => 'Propane', 'target' => ''],
                ['name' => 'Radiant Ceiling', 'target' => ''],
                ['name' => 'Reverse Cycle', 'target' => ''],
                ['name' => 'Solar', 'target' => ''],
                ['name' => 'Space Heater', 'target' => ''],
                ['name' => 'Wall Furnace', 'target' => ''],
                ['name' => 'Wall Units / Window Unit', 'target' => ''],
                ['name' => 'Zoned', 'target' => ''],
                ['name' => 'None', 'target' => ''],
                ['name' => 'Other', 'target' => '.otherFuelRes'],
            ];
        @endphp
        <label class="fw-bold">Heating and Fuel: </label>
        <select class="grid-picker" name="heatingFuel[]" id="utilities" style="justify-content: flex-start;"
            multiple required>
            <option value="">Select</option>
            @foreach ($heatingFuel as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherFuelRes d-none">
            <label for="" class="fw-bold"> Heating and Fuel: </label>
            <input type="text" class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                name="otherFuel">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='27'>
    <div class="form-group ">
        @php
            $carportOption = [
                [
                    'name' => 'Yes',
                    'target' => '.carprotYes',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
            ];
        @endphp
        <label class="fw-bold">Carport:</label>
        <select class="grid-picker" name="carport" id="carport" style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($carportOption as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group carprotYes d-none">
            <label class="fw-bold">How many carport spaces?</label>
            <input type="number" name="carportOther" id="condo_fee" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse">
        </div>
    </div>
    <div class="form-group ">
        @php
            $garageOption = [
                ['name' => 'Yes', 'target' => '.garageYes', 'icon' => 'fa-regular fa-circle-check'],
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
            ];
        @endphp
        <label class="fw-bold">Garage:</label>
        <select class="grid-picker" name="garage" id="garage" style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($garageOption as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group garageYes d-none">
            <label class="fw-bold">How many garage spaces?</label>
            <input type="number" name="garageOther" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='28'>

    <div class="form-group ">
        <div class="form-group">
            @php
                $poolOpt = [
                    ['name' => 'Yes', 'target' => '.poolYesRes', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                ];
            @endphp
            <label class="fw-bold">Pool:</label>
            <select class="grid-picker" name="poolOpt" id="pool" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($poolOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group poolYesRes d-none">
                @php
                    $pools = [
                        ['name' => 'Private', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                        ['name' => 'Community', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ];
                @endphp
                <label class="fw-bold">Pool Type:</label>
                <select class="grid-picker" name="pool" id="pool" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($pools as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group ">
            @php
                $viewOption = [
                    [
                        'name' => 'Yes',
                        'target' => '.viewYes',
                        'icon' => 'fa-regular fa-circle-check',
                    ],
                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                ];
            @endphp
            <label class="fw-bold">View:</label>
            <select class="grid-picker" name="viewOption[]" style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($viewOption as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>">
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group viewYes d-none">
                @php
                    $view = [
                        ['name' => 'Beach', 'target' => ''],
                        ['name' => 'City', 'target' => ''],
                        ['name' => 'Garden', 'target' => ''],
                        ['name' => 'Golf Course', 'target' => ''],
                        ['name' => 'Greenbelt', 'target' => ''],
                        ['name' => 'Mountain(s)', 'target' => ''],
                        ['name' => 'Park', 'target' => ''],
                        ['name' => 'Pool', 'target' => ''],
                        ['name' => 'Tennis Court', 'target' => ''],
                        ['name' => 'Trees/Woods', 'target' => ''],
                        ['name' => 'Water', 'target' => ''],
                        ['name' => 'Other', 'target' => '.viewOther'],
                    ];
                @endphp
                <label class="fw-bold">View: </label>
                <select class="grid-picker" name="view[]" id="water_access" style="justify-content: flex-start;"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($view as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group viewOther d-none">
                    <label for="" class="fw-bold">View: </label>
                    <input type="text" class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                        name="viewOther">
                </div>
            </div>
        </div>
    </div>

</div>
<div class="wizard-step" data-step='29'>
    @php
        $garage_spaces = [
            ['target' => '', 'name' => '1 to 5 Spaces', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => '6 to 12 Spaces', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => '13 to 18 Spaces', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => '19 to 30 Spaces', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Airplane Hangar', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Common', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Curb Parking', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Deeded', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Electric Vehicle Charging Station(s)', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Ground Level', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Lighted', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Over 30 Spaces', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'RV Parking', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Secured', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Under Building', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Underground', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'Valet', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '', 'name' => 'None', 'icon' => 'fa-solid fa-warehouse'],
            ['target' => '.otherParkingCommercial', 'name' => 'Other', 'icon' => 'fa-solid fa-warehouse'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Garage/Parking Features:</label>
        <select class="grid-picker" name="parking_feature_garage[]" id="parking_feature_garage"
            style="justify-content: flex-start;" required multiple>
            <option value="">Select</option>
            @foreach ($garage_spaces as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherParkingCommercial d-none">
            <label class="fw-bold">Garage/Parking Features: </label>
            <input type="text" name="otherParking" class="form-control has-icon"
                data-icon="fa-solid fa-warehouse">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='30'>
    @php
        $front_exposures = [
            ['name' => 'East', 'target' => ''],
            ['name' => 'North', 'target' => ''],
            ['name' => 'Northeast', 'target' => ''],
            ['name' => 'Northwest', 'target' => ''],
            ['name' => 'South', 'target' => ''],
            ['name' => 'Southeast', 'target' => ''],
            ['name' => 'Southwest', 'target' => ''],
            ['name' => 'West', 'target' => ''],
        ];
    @endphp
    <div class="form-group residential_and_income_hide">
        <label class="fw-bold">Front Exposure:</label>
        <select class="grid-picker" name="front_exposure" id="front_exposure" style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($front_exposures as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>"
                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="wizard-step" data-step='31'>
    @php
        $foundations = [
            ['name' => 'Basement', 'target' => ''],
            ['name' => 'Block', 'target' => ''],
            ['name' => 'Brick/Mortar', 'target' => ''],
            ['name' => 'Concrete Perimeter', 'target' => ''],
            ['name' => 'Crawlspace', 'target' => ''],
            ['name' => 'Pillar/Post/Pier', 'target' => ''],
            ['name' => 'Slab', 'target' => ''],
            ['name' => 'Stem Wall', 'target' => ''],
            ['name' => 'Stilt/On Piling', 'target' => ''],
            ['name' => 'Other', 'target' => '.foundationOther'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Foundation:</label>
        <select class="grid-picker" name="foundation[]" id="foundation" style="justify-content: flex-start;"
            multiple required>
            <option value="">Select</option>
            @foreach ($foundations as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group foundationOther d-none">
            <label class="fw-bold">Foundation: </label>
            <input type="text" name="foundationOther" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='32'>
    @php
        $exterior_constructions = [
            ['name' => 'Asbestos', 'target' => ''],
            ['name' => 'Block', 'target' => ''],
            ['name' => 'Brick', 'target' => ''],
            ['name' => 'Cedar', 'target' => ''],
            ['name' => 'Cement Siding', 'target' => ''],
            ['name' => 'Concrete', 'target' => ''],
            ['name' => 'HardiPlank Type', 'target' => ''],
            ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''],
            ['name' => 'Log', 'target' => ''],
            ['name' => 'Metal Frame', 'target' => ''],
            ['name' => 'Metal Siding', 'target' => ''],
            ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''],
            ['name' => 'Stone', 'target' => ''],
            ['name' => 'Stucco', 'target' => ''],
            ['name' => 'Tilt up Walls', 'target' => ''],
            ['name' => 'Vinyl Siding', 'target' => ''],
            ['name' => 'Wood Frame', 'target' => ''],
            ['name' => 'Wood Frame (FSC)', 'target' => ''],
            ['name' => 'Wood Siding ', 'target' => ''],
            ['name' => 'Other', 'target' => '.exteriorOther'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Exterior Construction:</label>
        <select class="grid-picker" name="exterior_construction[]" id="exterior_construction"
            style="justify-content: flex-start;" multiple>
            <option value="">Select</option>
            @foreach ($exterior_constructions as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group exteriorOther d-none">
            <label class="fw-bold">Exterior Construction: </label>
            <input type="text" name="exteriorOther" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='33'>
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
            ['name' => 'Other', 'target' => '.exteriorFeatureOther'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Exterior Features:</label>
        <select class="grid-picker" name="exterior_feature[]" id="exterior_feature"
            style="justify-content: flex-start;" multiple>
            <option value="">Select</option>
            @foreach ($exterior_features as $exterior_feature)
                <option value="{{ $exterior_feature['name'] }}" data-target="{{ $exterior_feature['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $exterior_feature['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group exteriorFeatureOther d-none">
            <label class="fw-bold">Exterior Features: </label>
            <input type="text" name="exteriorFeatureOther" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
