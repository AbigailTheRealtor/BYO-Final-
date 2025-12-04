<div class="wizard-step" data-step='34'>
    @php
        $otherStructures = [
            ['name' => 'Additional Single Family Home', 'target' => ''],
            ['name' => 'Airplane Hangar', 'target' => ''],
            ['name' => 'Barn(s)', 'target' => ''],
            ['name' => 'Boathouse', 'target' => ''],
            ['name' => 'Cabana', 'target' => ''],
            ['name' => 'Corral(s)', 'target' => ''],
            ['name' => 'Finished RV Port', 'target' => ''],
            ['name' => 'Gazebo', 'target' => ''],
            ['name' => 'Greenhouse', 'target' => ''],
            ['name' => 'Guest House', 'target' => ''],
            ['name' => 'Kennel/Dog Run', 'target' => ''],
            ['name' => 'Outdoor Kitchen', 'target' => ''],
            ['name' => 'Outhouse', 'target' => ''],
            ['name' => 'Shed(s)', 'target' => ''],
            ['name' => 'Storage', 'target' => ''],
            ['name' => 'Tennis Court(s)', 'target' => ''],
            ['name' => 'Workshop', 'target' => ''],
            ['name' => 'Other', 'target' => '.roadStructures'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Other Structures:</label>
        <select class="grid-picker" name="other_structures[]" id="other_structures" style="justify-content: flex-start;"
            required multiple>
            <option value="">Select</option>
            @foreach ($otherStructures as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group roadStructures d-none">
            <label class="fw-bold">Other Structures:</label>
            <input type="text" name="structuresOther" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='35'>
    @php
        $buildingFeatures = [
            ['name' => 'Bathrooms', 'target' => ''],
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
            ['name' => 'Outside Storage', 'target' => ''],
            ['name' => 'Overhead Doors', 'target' => ''],
            ['name' => 'Pool/Spa', 'target' => ''],
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
            ['name' => 'Other', 'target' => '.buildingFeaturesOther'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Building Features:</label>
        <select class="grid-picker" name="buildingFeatures[]" style="justify-content: flex-start;" multiple>
            <option value="">Select</option>
            @foreach ($buildingFeatures as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group buildingFeaturesOther d-none">
            <label class="fw-bold">Building Features:</label>
            <input type="text" name="buildingFeaturesOther" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='36'>
    @php
        $road_frontages = [
            ['name' => 'Access Road', 'target' => ''],
            ['name' => 'Alley', 'target' => ''],
            ['name' => 'Business District', 'target' => ''],
            ['name' => 'City Street', 'target' => ''],
            ['name' => 'County Road ', 'target' => ''],
            ['name' => 'Divided Highway', 'target' => ''],
            ['name' => 'Easement', 'target' => ''],
            ['name' => 'Highway', 'target' => ''],
            ['name' => 'Interchange', 'target' => ''],
            ['name' => 'Interstate', 'target' => ''],
            ['name' => 'Main Thoroughfare', 'target' => ''],
            ['name' => 'Private Road', 'target' => ''],
            ['name' => 'Rail', 'target' => ''],
            ['name' => 'State Road', 'target' => ''],
            ['name' => 'Turn Lanes', 'target' => ''],
            ['name' => 'None', 'target' => ''],
            ['name' => 'Other', 'target' => '.roadFrontageOther'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Road Frontage:</label>
        <select class="grid-picker" name="road_frontage[]" id="road_frontage" style="justify-content: flex-start;"
            multiple>
            <option value="">Select</option>
            @foreach ($road_frontages as $road_frontage)
                <option value="{{ $road_frontage['name'] }}" data-target="{{ $road_frontage['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $road_frontage['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group roadFrontageOther d-none">
            <label class="fw-bold">Road Frontage: </label>
            <input type="text" name="roadFrontageOther" class="form-control has-icon"
                data-icon="fa-regular fa-circle-check">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='37'>
    @php
        $road_surface_types = [
            ['name' => 'Asphalt', 'target' => ''],
            ['name' => 'Brick', 'target' => ''],
            ['name' => 'Chip And Seal', 'target' => ''],
            ['name' => 'Concrete', 'target' => ''],
            ['name' => 'Dirt', 'target' => ''],
            ['name' => 'Gravel', 'target' => ''],
            ['name' => 'Limerock', 'target' => ''],
            ['name' => 'Paved', 'target' => ''],
            ['name' => 'Unimproved', 'target' => ''],
            ['name' => 'Other', 'target' => '.roadSurfaceOther'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Road Surface Type:</label>
        <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
            style="justify-content: flex-start;" required multiple>
            <option value="">Select</option>
            @foreach ($road_surface_types as $road_surface_type)
                <option value="{{ $road_surface_type['name'] }}" data-target="{{ $road_surface_type['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $road_surface_type['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group roadSurfaceOther d-none">
            <label class="fw-bold">Road Surface Type:</label>
            <input type="text" name="roadSurfaceOther" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='38'>
    @php
        $roofs = [
            ['name' => 'Built-Up', 'target' => ''],
            ['name' => 'Cement', 'target' => ''],
            ['name' => 'Concrete', 'target' => ''],
            ['name' => 'Membrane', 'target' => ''],
            ['name' => 'Metal', 'target' => ''],
            ['name' => 'Roof Over', 'target' => ''],
            ['name' => 'Shake', 'target' => ''],
            ['name' => 'Shingle', 'target' => ''],
            ['name' => 'Slate', 'target' => ''],
            ['name' => 'Tile', 'target' => ''],
            ['name' => 'Other', 'target' => '.roofCementOther'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Roof:</label>
        <select class="grid-picker" name="roof[]" id="roof" style="justify-content: flex-start;" multiple>
            <option value="">Select</option>
            @foreach ($roofs as $roof)
                <option value="{{ $roof['name'] }}" data-target="{{ $roof['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $roof['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group roofCementOther d-none">
            <label class="fw-bold">Roof:</label>
            <input type="text" name="roofCementOther" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='39'>
    @php
        $adjoining_properties = [
            ['name' => 'Airport', 'target' => ''],
            ['name' => 'Church', 'target' => ''],
            ['name' => 'Commercial', 'target' => ''],
            ['name' => 'Hotel/Motel', 'target' => ''],
            ['name' => 'Industrial', 'target' => ''],
            ['name' => 'Multi-Family', 'target' => ''],
            ['name' => 'Natural State', 'target' => ''],
            ['name' => 'Professional Office', 'target' => ''],
            ['name' => 'Railroad', 'target' => ''],
            ['name' => 'Residential', 'target' => ''],
            ['name' => 'School', 'target' => ''],
            ['name' => 'Undeveloped', 'target' => ''],
            ['name' => 'Vacant', 'target' => ''],
            ['name' => 'Waterway', 'target' => ''],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Adjoining Property:</label>
        <select class="grid-picker" name="adjoining_property[]" id="roof" style="justify-content: flex-start;"
            multiple>
            <option value="">Select</option>
            @foreach ($adjoining_properties as $adjoining_propertie)
                <option value="{{ $adjoining_propertie['name'] }}"
                    data-target="{{ $adjoining_propertie['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $adjoining_propertie['name'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="wizard-step" data-step='40'>
    @php
        $lot_features1 = [
            ['name' => 'Central Business District', 'target' => ''],
            ['name' => 'Corner Lot', 'target' => ''],
            ['name' => 'Cul-De-Sac', 'target' => ''],
            ['name' => 'Curb and Gutters', 'target' => ''],
            ['name' => 'Drainage Canal', 'target' => ''],
            ['name' => 'Fire Hydrant', 'target' => ''],
            ['name' => 'Flood Insurance Required', 'target' => ''],
            ['name' => 'Flood Zone', 'target' => ''],
            ['name' => 'Fuel Pump', 'target' => ''],
            ['name' => 'Historic District', 'target' => ''],
            ['name' => 'In City Limits', 'target' => ''],
            ['name' => 'Industrial Condo', 'target' => ''],
            ['name' => 'Industrial Park', 'target' => ''],
            ['name' => 'Infrastructure In', 'target' => ''],
            ['name' => 'Interior Lot', 'target' => ''],
            ['name' => 'Landscaped', 'target' => ''],
            ['name' => 'Near Golf Course', 'target' => ''],
            ['name' => 'Near Public Transit', 'target' => ''],
            ['name' => 'Near Railroad Siding', 'target' => ''],
            ['name' => 'Neighborhood', 'target' => ''],
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
            ['name' => 'Zoned for Horses', 'target' => ''],
            ['name' => 'Other', 'target' => '.otherFeaturesCommercial'],
        ];
    @endphp
    <div class="form-group  ">
        <label class="fw-bold">Lot Features:</label>
        <select class="grid-picker" name="lot_features[]" id="lot_features" style="justify-content: flex-start;"
            multiple>
            <option value="">Select</option>
            @foreach ($lot_features1 as $lot_feature12)
                <option value="{{ $lot_feature12['name'] }}" data-target="{{ $lot_feature12['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $lot_feature12['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherFeaturesCommercial d-none ">
            <label class="fw-bold">Lot Features:</label>
            <input type="text" name="otherFeatures" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='41'>
    <div class="form-group">
        <label class="fw-bold">Is the property located in a condo environment? </label>
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
                <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="row has_condo d-none">
        @php
            $condo_fee_terms = [
                ['target' => '', 'name' => 'Annual'],
                ['target' => '', 'name' => 'Monthly'],
                ['target' => '', 'name' => ' Quarterly'],
                ['target' => '', 'name' => 'Semi Annual '],
            ];
        @endphp
        <div class="form-group ">
            <label class="fw-bold">Condo Fee Term:</label>
            <select class="grid-picker" name="condo_fee_terms[]" id="parking_feature_garage"
                style="justify-content: flex-start;" required multiple>
                <option value="">Select</option>
                @foreach ($condo_fee_terms as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group  ">
            <label class="fw-bold">Condo Fee:</label>
            <input type="number" name="condo_fee" id="condo_fee" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign">
        </div>
    </div>
    <div class="form-group">
        <label class="fw-bold">Association/Manager Name:</label>
        <input type="text" name="association_name" class="form-control has-icon" data-icon="fa-solid fa-user">
    </div>
    <div class="form-group">
        <label class="fw-bold">Association/Manager Phone:</label>
        <input type="text" name="association_phone" class="form-control has-icon" data-icon="fa-solid fa-phone">
    </div>
    <div class="form-group">
        <label class="fw-bold">Association/Manager Email:</label>
        <input type="email" name="association_email" class="form-control has-icon"
            data-icon="fa-solid fa-envelope">
    </div>
    <div class="form-group">
        <label class="fw-bold">Association/Manager Website:</label>
        <input type="text" name="association_website" class="form-control has-icon"
            data-icon="fa-solid fa-globe">
    </div>
    <div class="form-group ">
        @php
            $community_features = [
                ['name' => 'Activity Core/Center', 'target' => ''],
                ['name' => 'Airport/Runway', 'target' => ''],
                ['name' => 'Beach Area', 'target' => ''],
                ['name' => 'Curbs', 'target' => ''],
                ['name' => 'Expressway', 'target' => ''],
                ['name' => 'Sidewalk', 'target' => ''],
                ['name' => 'Stream Seasonal', 'target' => ''],
                ['name' => 'Other', 'target' => '.communityFeatureOther'],
            ];
        @endphp
        <label class="fw-bold">Community Features:</label>
        <select class="grid-picker" name="community_feature[]" id="community_feature"
            style="justify-content: flex-start;" multiple>
            <option value="">Select</option>
            @foreach ($community_features as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group communityFeatureOther d-none">
            <label class="fw-bold">Community Features:</label>
            <input type="text" name="communityFeatureOther" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='42'>
    <div class="form-group">
        <label class="fw-bold">Does the property have an HOA, condo association, master association, and/or community
            fee? </label>
        <select class="grid-picker" name="has_hoa" id="has_hoa" style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($yes_or_nos as $item)
                @php
                    if ($item['name'] == 'Yes') {
                        $target = '.hoas';
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

    <div class="form-group hoas d-none">
        <label class="fw-bold">Association Approval Required: </label>
        <select class="grid-picker" name="assocRequired" style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($yes_or_nos as $item)
                @php
                    if ($item['name'] == 'Yes') {
                        $target = '.master_association';
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
    <div class="form-group hoas d-none">
        <label class="fw-bold">Housing For Older Persons: </label>
        <select class="grid-picker" name="oldHouse" style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($yes_or_nos as $item)
                @php
                    if ($item['name'] == 'Yes') {
                        $target = '.master_association';
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

    <div class="form-group hoas d-none">
        @php
            $hoa_fee_requirenments = [
                ['name' => 'None', 'target' => ''],
                ['name' => 'Optional', 'target' => ''],
                ['name' => ' Required', 'target' => ''],
            ];
        @endphp
        <label class="fw-bold">HOA Fee Requirement:</label>
        <select class="grid-picker" name="hoa_fee_requirenment" id="feeReqOption"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($hoa_fee_requirenments as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group" id="feeReq" style="display: none;">
            <label class="fw-bold">How much is the HOA Fee?</label>
            <input type="number" name="feeReq" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
            @php
                $paySchedule = [
                    ['name' => 'Annually', 'target' => ''],
                    ['name' => 'Monthly', 'target' => ''],
                    ['name' => 'Quarterly', 'target' => ''],
                    ['name' => 'Semi-Annually ', 'target' => ''],
                ];
            @endphp
            <div class="form-group">
                <label class="fw-bold">HOA Payment Schedule: </label>
                <select class="grid-picker" name="paySchedule" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($paySchedule as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="form-group hoas d-none">
        <label class="fw-bold">Association Approval Fee for Tenants:</label>
        <input type="number" name="association_approval_fee" id="association_approval_fee"
            class="form-control has-icon " data-icon="fa-solid fa-dollar-sign">
    </div>
    <div class="form-group hoas d-none">
        <label class="fw-bold">Parking Fee For Tenants:</label>
        <input type="number" name="parking_fee_for_tenants" id="parking_fee_for_tenants"
            class="form-control has-icon " data-icon="fa-solid fa-dollar-sign">
    </div>
    <div class="form-group hoas d-none">
        <label class="fw-bold">Association Security Deposit Fee for Tenant:</label>
        <input type="number" name="association_security_deposit" id="association_security_deposit"
            class="form-control has-icon " data-icon="fa-solid fa-dollar-sign">
    </div>
    <div class="form-group hoas d-none">
        <label class="fw-bold">Other Association Fees for Tenants:</label>
        <input type="number" name="other_association_fee" id="association_security_deposit"
            class="form-control has-icon " data-icon="fa-solid fa-dollar-sign">
    </div>
    <div class="form-group hoas d-none">
        <label class="fw-bold">Association/Manager Name:</label>
        <input type="text" name="association_name" class="form-control has-icon" data-icon="fa-solid fa-user">
    </div>
    <div class="form-group hoas d-none">
        <label class="fw-bold">Association/Manager Phone:</label>
        <input type="text" name="association_phone" class="form-control has-icon" data-icon="fa-solid fa-phone">
    </div>
    <div class="form-group hoas d-none">
        <label class="fw-bold">Association/Manager Email:</label>
        <input type="email" name="association_email" class="form-control has-icon"
            data-icon="fa-solid fa-envelope">
    </div>

    <div class="form-group HOA_show d-none">
        @php
            $community_features = [
                ['name' => 'Airport/Runway', 'target' => ''],
                ['name' => 'Association Recreation - Lease', 'target' => ''],
                ['name' => 'Association Recreation - Owned', 'target' => ''],
                ['name' => 'Buyer Approval Required', 'target' => ''],
                ['name' => 'Clubhouse', 'target' => ''],
                ['name' => 'Dog Park', 'target' => ''],
                ['name' => 'Fitness Center', 'target' => ''],
                ['name' => 'Gated Community - Guard', 'target' => ''],
                ['name' => 'Gated Community- Not Guard ', 'target' => ''],
                ['name' => 'Golf Carts OK', 'target' => ''],
                ['name' => 'Golf Community', 'target' => ''],
                ['name' => 'Handicap Modified', 'target' => ''],
                ['name' => 'Horse Stable(s)', 'target' => ''],
                ['name' => 'Horses Allowed', 'target' => ''],
                ['name' => 'Irrigation-Reclaimed Water', 'target' => ''],
                ['name' => 'No Truck/RV/Motorcycle Parking', 'target' => ''],
                ['name' => 'No Truck/RV/Motorcycle Parking', 'target' => ''],
                ['name' => 'Park', 'target' => ''],
                ['name' => 'Playground', 'target' => ''],
                ['name' => 'Pool', 'target' => ''],
                ['name' => 'Racquetball', 'target' => ''],
                ['name' => 'Restaurant', 'target' => ''],
                ['name' => 'Sidewalk', 'target' => ''],
                ['name' => 'Special Community Restrictions', 'target' => ''],
                ['name' => 'Stream Seasonal', 'target' => ''],
                ['name' => 'Tennis Courts', 'target' => ''],
                ['name' => 'Wheelchair Access', 'target' => ''],
                ['name' => 'None', 'target' => ''],
                ['name' => 'Other', 'target' => '.otherCommunity'],
            ];
        @endphp
        <label class="fw-bold">Community Features:</label>
        <select class="grid-picker" name="community_feature[]" id="community_feature"
            style="justify-content: flex-start;" multiple>
            <option value="">Select</option>
            @foreach ($community_features as $community_feature)
                <option value="{{ $community_feature['name'] }}" data-target="{{ $community_feature['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $community_feature['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherCommunity d-none">
            <label class="fw-bold">Community Features:</label>
            <input type="text" name="communityOther" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>

    <div class="form-group  residential_show">
        @php
            $association_amenities = [
                ['name' => 'Airport/Runway', 'target' => ''],
                ['name' => 'Basketball Court', 'target' => ''],
                ['name' => 'Cable', 'target' => ''],
                ['name' => 'Clubhouse', 'target' => ''],
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
                ['name' => 'Optional Additional Fees', 'target' => ''],
                ['name' => 'Park', 'target' => ''],
                ['name' => 'Pickleball Court(s)', 'target' => ''],
                ['name' => 'Playground', 'target' => ''],
                ['name' => 'Pool', 'target' => ''],
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
                ['name' => 'None', 'target' => ''],
                ['name' => 'Other', 'target' => '.otherAmenitiesRes'],
            ];
        @endphp
        <label class="fw-bold">Association Amenities:</label>
        <select class="grid-picker" name="association_amenitie[]" id="association_amenitie"
            style="justify-content: flex-start;" multiple>
            <option value="">Select</option>
            @foreach ($association_amenities as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherAmenitiesRes d-none">
            <label class="fw-bold"> Association Amenities:</label>
            <input name="otherAmenities" class="form-control has-icon" data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='43'>
    <div class="form-group">
        <label class="fw-bold"> Description:</label>
        <textarea name="description" id="description" class="form-control" cols="30" rows="10" required></textarea>
    </div>
    <div class="form-group">
        <label class="fw-bold">Legal Disclaimers:</label>
        <textarea name="disclaimer" id="description" class="form-control has-icon" data-icon="fa-solid fa-tag"
            cols="30" rows="6" required></textarea>
    </div>
    <div class="form-group">
        <label class="fw-bold">Driving Directions:</label>
        <input type="text" name="driving_directions" class="form-control has-icon" data-icon="fa-solid fa-car">
    </div>
    {{-- <div class="form-group">
        @php
            $compensationYesRes = [
                ['name' => 'Yes', 'target' => '.agentCompensationYesRes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
                ['name' => ' Negotiable', 'target' => '','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
            ];
        @endphp
        <label class="fw-bold">Is the landlord offering compensation for a tenant’s agent?</label>
        <select class="grid-picker" name="tenant_agent_compensation" id="feeReqOption"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($compensationYesRes as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon="{{$item['icon']}}">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group agentCompensationYesRes d-none">
            <label class="fw-bold">Tenant’s Agent Compensation: $ </label>
            <input type="text" name="compensationYes" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
        </div>
    </div> --}}
</div>
<div class="wizard-step" data-step="44">
    <h4>Tenant’s Agent Compensation:</h4>
    <div class="form-group">
        @php
            $agent_compensation = [
                [
                    'name' =>
                        "The listing broker will compensate the tenant's broker from the listing broker's commission, if applicable.",
                    'target' => '',
                ],
                ['name' => "The owner will pay the tenant's broker separately, if applicable.", 'target' => ''],
                ['name' => "There is no compensation offered to the tenant's broker.", 'target' => ''],
            ];
        @endphp
        <label class="fw-bold">What is the compensation structure for the tenant's broker?</label>
        <select class="grid-picker" name="compensation_structure" id="compensation_structure"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($agent_compensation as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group compensationYes d-none">
            @php
                $agent_compensation_yes = [
                    ['name' => '___% of the gross lease value', 'target' => ''],
                    ['name' => '____% of the first month’s rent', 'target' => ''],
                    ['name' => 'Fixed amount : $____', 'target' => ''],
                    ['name' => 'Negotiable', 'target' => ''],
                ];
            @endphp
            <label class="fw-bold">What compensation is being offered to the tenant's broker?</label>
            <select class="grid-picker" name="compensation_structure_yes" id="compensation_structure_yes"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($agent_compensation_yes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="wizard-step" data-step='45'>
    @if (auth()->user()->user_type == 'landlord')
        <h4>Landlord’s Info:</h4>
    @else
        <h4>Listing Agent’s Info:</h4>
    @endif


    <div class="form-group row">
        <div class="form-group col-md-6">
            <label class="fw-bold" for="first_name">First Name:</label>
            <input type="text" name="first_name" placeholder="" id="first_name"
                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user"
                value="{{ Auth::user()->first_name }}">
        </div>
        <div class="form-group col-md-6">
            <label class="fw-bold" for="last_name">Last Name:</label>
            <input type="text" name="last_name" placeholder="" id="last_name"
                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user"
                value="{{ Auth::user()->last_name }}">
        </div>
    </div>
    <div class="form-group row">
        <div class="form-group col-md-6">
            <label class="fw-bold" for="agent_phone">Phone Number:</label>
            <input type="text" name="agent_phone" placeholder="" id="agent_phone"
                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-phone"
                value="{{ Auth::user()->phone }}">
        </div>
        <div class="form-group col-md-6">
            <label class="fw-bold" for="agent_email">Email:</label>
            <input type="text" name="agent_email" class="form-control has-icon hide_arrow"
                data-icon="fa-solid fa-envelope" value="{{ Auth::user()->email }}">
        </div>
    </div>
    @if (auth()->user()->user_type !== 'landlord')
        <div class="form-group row">
            <div class="form-group col-md-6">
                <label class="fw-bold" for="agent_brokerage">Brokerage:</label>
                <input type="text" name="agent_brokerage" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-handshake" value="{{ Auth::user()->brokerage }}">
            </div>
            <div class="form-group col-md-6">
                <label class="fw-bold" for="agent_license_no">Real Estate License #:</label>
                <input type="text" name="agent_license_no" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-id-card" value="{{ Auth::user()->license_no }}">
            </div>
        </div>

        <div class="form-group row">
            <div class="form-group col-md-6">
                <label class="fw-bold" for="agent_mls_id">NAR Member ID (NRDS ID): </label>
                <input type="number" name="agent_mls_id" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-id-card-clip" value="{{ Auth::user()->mls_id }}">
            </div>
            {{-- <div class="form-group col-md-6">
                <label class="fw-bold" for="agent_mls_id">Listed By: Real Estate Agent: </label>
                <input type="text" name="realEstate" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-id-card-clip" >
            </div> --}}
        </div>
    @endif
</div>
<div class="wizard-step" data-step='46'>
    <div class="row">
        <div class="col-6">
            <div class="upload form-group">
                <label class="fw-bold">Property Photos:</label>
                <div class="wrapper">
                    <div class="box">
                        <div class="js--image-preview"></div>
                        <div class="upload-options">
                            <label class="image-input-label">
                                <input type="file" name="photo[]" class="image-input" accept="image/*" multiple />
                            </label>
                        </div>
                        <div class="thumbnails-container"></div>
                        <div class="hidden-inputs-container"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 video_div">
            <input type="hidden" name="video_type" class="video_type" value="video_upload">
            <div class="video_type_select d-flex align-items-center justify-content-left">
                <div class="form-check me-2">
                    <label class="form-check-label">
                        <input type="checkbox" class="video_upload form-check-input video_type_check"
                            name="video_upload">
                        Video Upload
                    </label>
                </div>
                <div class="form-check me-2">
                    <label class="form-check-label">
                        <input type="checkbox" class="youtube_video form-check-input video_type_check"
                            name="youtube_video">
                        Youtube Video
                    </label>
                </div>
                <div class="form-check">
                    <label class="form-check-label">
                        <input type="checkbox" class="vimeo_video form-check-input video_type_check"
                            name="vimeo_video">
                        Vimeo Video
                    </label>
                </div>
            </div>
            <div class="video-upload video-type-element">
                <label class="fw-bold mt-1"> Property Video:</label>
                <div class="videoBox ">
                    <div class="video bgImg"></div>
                    <div class="form-group videoDiv">
                        <input type="file" class="fileuploader" name="video" style="display: none;"
                            accept="video/*">
                        <label for="fileuploader" class="fileuploader-btn">
                            <span class="upload-button">+</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="youtube-video video-type-element form-group d-none">
                <label class="fw-bold mt-1"> Youtube Video:</label>
                <input type="text" name="youtube_video_link" class="form-control"
                    placeholder="Youtube video link i.e. https://youtube.com/embed/videoId">
            </div>
            <div class="vimeo-video video-type-element form-group d-none">
                <label class="fw-bold mt-1">Vimeo Video:</label>
                <input type="text" name="vimeo_video_link" class="form-control"
                    placeholder="Vimeo video link i.e. https://player.vimeo.com/video/videoId">
            </div>
        </div>
    </div>
    <div class="row align-items-end">
        <div class="form-group">
            <label class="fw-bold">3d Tour (Link):</label>
            <input type="text" name="three_d_tour" id="three_d_tour" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-link">
        </div>
        <div class="form-group">
            <label class="fw-bold">Floor Plan:</label>
            <input type="file" name="visible_note" id="visible_note" class="form-control p-3">
        </div>
        <div class="form-group">
            <label class="fw-bold">Addendums/Disclosures: </label>
            <input type="file" name="disclosures[]" class="form-control p-3 documents-input" multiple>
        </div>
        {{-- <div class="form-group">
            <label class="fw-bold">upload documents: </label>
            <input type="file" name="documents[]" class="form-control p-3" multiple>
        </div> --}}
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
<template class="roomDimensionTemp">
    <input type="text" name="roomDimensions[]" data-type="" class="form-control mt-2 dynamic-room-input"
        data-msg-required="">
</template>
