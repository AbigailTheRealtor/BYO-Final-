<div class="wizard-step" data-step='12'>
    <div class="form-group">
        <label for="heated_sqft" class="fw-bold">Heated Sqft:</label>
        <input type="number" name="heated_sqft" id="heated_sqft" class="form-control has-icon"
            data-icon="fa-solid fa-ruler-combined" required>
    </div>
    <div class="form-group commercial_show">
        <label for="heated_sqft" class="fw-bold"> Net Leasable Sqft:</label>
        <input type="number" name="net_leasable_sqft" id="net_leasable_sqft" class="form-control has-icon"
            data-icon="fa-solid fa-ruler-combined" required>
    </div>
    <div class="form-group">
        <label for="sqft_total" class="fw-bold"> Total Sqft:</label>
        <input type="number" name="sqft_total" id="sqft_total" class="form-control has-icon"
            data-icon="fa-solid fa-ruler-combined" required>
    </div>
    @php
        $heated_sources = [
            ['target' => '', 'name' => 'Appraisal'],
            ['target' => '', 'name' => 'Building'],
            ['target' => '', 'name' => 'Measured'],
            ['target' => '', 'name' => 'Owner Provided'],
            ['target' => '', 'name' => 'Public Records'],
            ['target' => '.otherSqftRes', 'name' => 'Other'],
        ];

    @endphp
    <div class="form-group">
        <label class="fw-bold">Sqft Heated Source:</label>
        <select class="grid-picker" name="heated_source" style="justify-content: left;" required>
            <option value="">Select</option>
            @foreach ($heated_sources as $heated_source)
                <option value="{{ $heated_source['name'] }}" data-target="{{ $heated_source['target'] }}"
                    class="card flex-row" style="width:calc(25% - 10px);"
                    data-icon='<i class="fa-regular fa-circle-check "></i>'>
                    {{ $heated_source['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group otherSqftRes d-none">
            <label for="sqft_total" class="fw-bold"> Sqft Heated Source:</label>
            <input type="text" name="otherSqft" class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                required>
        </div>
    </div>
</div>
<div class="wizard-step" data-step='13'>
    <h4>Land Information:</h4>
    @php
        $total_acreages = [
            ['name' => '0 to less than 1/4', 'target' => ''],
            ['name' => '1/4 to less than 1/2', 'target' => ''],
            ['name' => '1/2 to less than 1', 'target' => ''],
            ['name' => '1 to less than 2', 'target' => ''],
            ['name' => '2 to less than 5', 'target' => ''],
            ['name' => '5 to less than 10', 'target' => ''],
            ['name' => '10 to less than 20', 'target' => ''],
            ['name' => '20 to less than 50', 'target' => ''],
            ['name' => '50 to less than 100', 'target' => ''],
            ['name' => '100 to less than 200', 'target' => ''],
            ['name' => '200 to less than 500', 'target' => ''],
            ['name' => '500+ acres', 'target' => ''],
            ['name' => 'Non-Applicable', 'target' => ''],
        ];
    @endphp

    <div class="form-group ">
        <label class="fw-bold">Total Acreage:</label>
        <select class="grid-picker" name="total_acreage" id="total_acreage" style="justify-content: flex-start;"
            required>
            <option value="">Select</option>
            @foreach ($total_acreages as $total_acreage)
                <option value="{{ $total_acreage['name'] }}" data-target="{{ $total_acreage['target'] }}"
                    class="card flex-column" style="width:calc(25% - 10px);"
                    data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                    {{ $total_acreage['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label class="fw-bold">Year Built:</label>
        <input type="number" name="yearBuilt" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
    </div>
    <div class="form-group">
        <label class="fw-bold">Lot Size:</label>
        <input type="text" name="lotSize" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
            required>
    </div>
    <div class="form-group">
        <label class="fw-bold">Legal Subdivision Name:</label>
        <input type="text" name="legarName" class="form-control has-icon" data-icon="fa-solid fa-tag" required>
    </div>
    <div class="form-group">
        <label class="fw-bold">Tax ID (Parcel Number):</label>
        <input type="text" name="taxId" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
            required>
    </div>
    <div class="form-group">
        <label class="fw-bold">Flood Zone Code:</label>
        <input type="text" name="zoneCode" class="form-control has-icon" data-icon="fa-solid fa-tag" required>
    </div>
    <span class="commercialFields">
        <div class="form-group">
            <label class="fw-bold">Zoning:</label>
            <input type="text" name="zoning" class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                required>
        </div>
        <div class="form-group">
            <label class="fw-bold">Tax Year:</label>
            <input type="number" name="tax_year" class="form-control has-icon" data-icon="fa-regular fa-check-circle"
                required>
        </div>
        <div class="form-group">
            <label class="fw-bold">Taxes (Annual Amount):</label>
            <input type="number" name="taxes_annual" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" required>
        </div>
        <div class="form-group">
            <label class="fw-bold">Legal Description:</label>
            <input type="text" name="legal_description" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle" required>
        </div>
        <div class="form-group">
            <label class="fw-bold">Total Number of Parcels:</label>
            <input type="text" name="no_of_parcels" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle" required>
        </div>
        @php
            $additional = [
                ['name' => 'Yes', 'target' => '.additionalTax', 'icon' => 'fa-regular fa-circle-check'],
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
            ];
        @endphp
        <div class="form-group ">
            <label class="fw-bold">Additional Parcels:</label>
            <select class="grid-picker" name="additional_parcels" id="additional_parcels"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($additional as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                        style="width:calc(25% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group additionalTax  d-none">
            <label class="fw-bold">Additional Tax ID’s:</label>
            <input type="text" name="additional_tax_id" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle" required>
        </div>
    </span>
</div>
<div class="wizard-step" data-step='14'>
    @php
        $furnishings = [
            ['name' => 'Furnished', 'target' => '', 'icon' => ''],
            ['name' => 'Optional', 'target' => '', 'icon' => ''],
            ['name' => 'Partial', 'target' => '', 'icon' => ''],
            ['name' => 'Turnkey', 'target' => '', 'icon' => ''],
            ['name' => 'Unfurnished', 'target' => '', 'icon' => ''],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Furnishings:</label>
        <select class="grid-picker" name="furnishings" id="furnishings" style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($furnishings as $furnishing)
                <option value="{{ $furnishing['name'] }}" data-target="{{ $furnishing['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                    {{ $furnishing['name'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="wizard-step" data-step='15'>
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
            ['name' => 'Touchless Faucet', 'target' => ''],
            ['name' => 'Trash Compactor', 'target' => ''],
            ['name' => 'Washer', 'target' => ''],
            ['name' => 'Water Filtration System', 'target' => ''],
            ['name' => 'Water Purifier', 'target' => ''],
            ['name' => 'Water Softener', 'target' => ''],
            ['name' => 'Whole House R.O. System', 'target' => ''],
            ['name' => 'Wine Refrigerator', 'target' => ''],
            ['name' => 'None', 'target' => ''],
            ['name' => 'Other', 'target' => '.appliancesOtherRes'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Appliances:</label>
        <select class="grid-picker" name="appliances[]" id="appliances" style="justify-content: flex-start;"
            multiple required>
            <option value="">Select</option>
            @foreach ($appliances as $appliance)
                <option value="{{ $appliance['name'] }}" data-target="{{ $appliance['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $appliance['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group appliancesOtherRes d-none">
            <label class="fw-bold">Appliances:</label>
            <input type="text" name="appliancesOther" id="total_floors" placeholder=""
                class="form-control has-icon" data-icon="fa-regular fa-circle-check">
        </div>
    </div>
    
    <span class="resFields">
        <div class="form-group">
            <label class="fw-bold">Fireplace:</label>
            <select class="grid-picker" name="firePlace" id="carport" style="justify-content: flex-start;"
                required>
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </span>
</div>
<div class="wizard-step" data-step='16'>
    <span class="resFields">
        <div class="form-group">
            <label class="fw-bold">
                Amenities and Property Features:
            </label>
            @php
                $amenitiesFeatureRes = [
                    ['target' => '', 'name' => '55 and Over Community'],
                    ['target' => '', 'name' => 'Accessibility Features'],
                    ['target' => '', 'name' => 'Balcony/Patio'],
                    ['target' => '', 'name' => 'Carpet Floors '],
                    ['target' => '', 'name' => 'Carport'],
                    ['target' => '', 'name' => 'Central Air Conditioning'],
                    ['target' => '', 'name' => 'Central Heating'],
                    ['target' => '', 'name' => 'Clubhouse'],
                    ['target' => '', 'name' => 'Covered Carport'],
                    ['target' => '', 'name' => 'Elevator'],
                    ['target' => '', 'name' => 'Fireplace'],
                    ['target' => '', 'name' => 'Fitness Center/Gym'],
                    ['target' => '', 'name' => 'First Floor Unit'],
                    ['target' => '', 'name' => 'Garage'],
                    ['target' => '', 'name' => 'Gated Community'],
                    ['target' => '', 'name' => 'Hardwood Floors'],
                    ['target' => '', 'name' => 'HOA Community'],
                    ['target' => '', 'name' => 'In-Unit Laundry'],
                    ['target' => '', 'name' => 'On-site Laundry'],
                    ['target' => '', 'name' => 'On-site Maintenance'],
                    ['target' => '', 'name' => 'On-site Management'],
                    ['target' => '', 'name' => 'Outdoor Space'],
                    ['target' => '', 'name' => 'Pet Friendly'],
                    ['target' => '', 'name' => 'Playground'],
                    ['target' => '', 'name' => 'Pool'],
                    ['target' => '', 'name' => 'Security System'],
                    ['target' => '', 'name' => 'Specific School District'],
                    ['target' => '', 'name' => 'Storage Space'],
                    ['target' => '', 'name' => 'Study/Den/Office'],
                    ['target' => '', 'name' => 'Tile Floors'],
                    ['target' => '', 'name' => 'Updated Bathroom'],
                    ['target' => '', 'name' => 'Updated Kitchen'],
                    ['target' => '', 'name' => 'Walk-in Closet'],
                    ['target' => '', 'name' => 'Waterfront'],
                    ['target' => '', 'name' => 'Washer and Dryer'],
                    ['target' => '', 'name' => 'Washer and Dryer Hookup'],
                    ['target' => '.otherAmenitiesFeatureRes', 'name' => 'Other'],
                ];

            @endphp
            <select name="amenities[]" id="negotiable_terms" class="grid-picker"
                style="justify-content: flex-start;" multiple required>
                <option value=""></option>
                @foreach ($amenitiesFeatureRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group otherAmenitiesFeatureRes d-none">
            <label class="fw-bold" for="custom_negotiable_terms"> Amenities and Property Features:
            </label>
            <input type="text" name="otherAmenities" id="custom_negotiable_terms" placeholder=""
                class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
        </div>
    </span>
    <span class="commercialFields">
        @php
            $amenitiesCommercial = [
                ['name' => 'Access to Public Transportation ', 'target' => ''],
                ['name' => 'Business Center ', 'target' => ''],
                ['name' => 'Common Areas ', 'target' => ''],
                ['name' => 'Conference Room', 'target' => ''],
                ['name' => 'Elevator', 'target' => ''],
                ['name' => 'Energy-Efficient Features ', 'target' => ''],
                ['name' => 'Flexibility for Renovations ', 'target' => ''],
                ['name' => 'Fire Safety Systems ', 'target' => ''],
                ['name' => 'Green Building Certification ', 'target' => ''],
                ['name' => 'Gym/Fitness Facilities ', 'target' => ''],
                ['name' => 'Handicap Accessibility ', 'target' => ''],
                ['name' => 'High-Speed Internet ', 'target' => ''],
                ['name' => 'HVAC System ', 'target' => ''],
                ['name' => 'Industrial Features ', 'target' => ''],
                ['name' => 'Kitchenette/Break Room', 'target' => ''],
                ['name' => 'Loading Dock', 'target' => ''],
                ['name' => 'Lounge Area ', 'target' => ''],
                ['name' => 'Natural Lighting ', 'target' => ''],
                ['name' => 'Office Space', 'target' => ''],
                ['name' => 'On-site Maintenance ', 'target' => ''],
                ['name' => 'On-site Management ', 'target' => ''],
                ['name' => 'Open Floor Plan ', 'target' => ''],
                ['name' => 'Other ', 'target' => '.otherAmenitiesCommercial'],
                ['name' => 'Outdoor Space/Garden ', 'target' => ''],
                ['name' => 'Parking Spaces', 'target' => ''],
                ['name' => 'Proximity to Highways ', 'target' => ''],
                ['name' => 'Reception Area ', 'target' => ''],
                ['name' => 'Restrooms', 'target' => ''],
                ['name' => 'Restaurant Space ', 'target' => ''],
                ['name' => 'Retail Frontage ', 'target' => ''],
                ['name' => 'Security Guard ', 'target' => ''],
                ['name' => 'Security System ', 'target' => ''],
                ['name' => 'Signage Opportunities ', 'target' => ''],
                ['name' => 'Storage Space ', 'target' => ''],
                ['name' => 'Utilities Included ', 'target' => ''],
                ['name' => 'Visibility from Main Road ', 'target' => ''],
                ['name' => 'Warehouse Space', 'target' => ''],
            ];

        @endphp
        <div class="form-group">
            <label class="fw-bold">Amenities and Property Features:</label>
            <select class="grid-picker" name="amenities[]" id="appliances" style="justify-content: flex-start;"
                multiple required>
                <option value="">Select</option>
                @foreach ($amenitiesCommercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group otherAmenitiesCommercial d-none">
                <label class="fw-bold">Amenities and Property Features:</label>
                <input type="text" class="form-control has-icon" name="otherAmenities"
                    data-icon="fa-regular fa-check-circle" required />
            </div>
        </div>
    </span>
</div>
<div class="wizard-step" data-step='17'>
    @php
        $accessibilityFeaturesRes = [
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
            ['name' => 'Stair Lift', 'target' => ''],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Accessibility Features:</label>
        <select class="grid-picker" name="features[]" multiple style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($accessibilityFeaturesRes as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="wizard-step" data-step='18'>
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
            ['name' => 'Open Floorplan', 'target' => ''],
            ['name' => 'Pest Guard System', 'target' => ''],
            ['name' => 'Primary Bedroom Main Floor', 'target' => ''],
            ['name' => 'Primary Bedroom Upstairs', 'target' => ''],
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
            ['name' => 'None', 'target' => ''],
            ['name' => 'Other', 'target' => '.interiorFeatureOtherRes'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Interior Features:</label>
        <select class="grid-picker" name="interiorFeatures[]" multiple id="tenant_pays"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($interior_features as $interior_feature)
                <option value="{{ $interior_feature['name'] }}" data-target="{{ $interior_feature['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                    {{ $interior_feature['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group interiorFeatureOtherRes d-none">
            <label class="fw-bold">Interior Features:</label>
            <input type="text" name="interiorFeatureOther" id="floors_in_unit" placeholder=""
                class="form-control has-icon" data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='19'>
    <h4>Additional Rooms</h4>
    @php
        $additional_rooms = [
            ['name' => 'Attic', 'target' => ''],
            ['name' => 'Bonus Room', 'target' => ''],
            ['name' => 'Breakfast Room Separate', 'target' => ''],
            ['name' => 'Den/Library/Office', 'target' => ''],
            ['name' => 'Family Room', 'target' => ''],
            ['name' => 'Florida Room', 'target' => ''],
            ['name' => 'Formal Dining Room Separate', 'target' => ''],
            ['name' => 'Formal Living Room Separate', 'target' => ''],
            ['name' => 'Garage Apartment', 'target' => ''],
            ['name' => 'Great Room', 'target' => ''],
            ['name' => 'Inside Utility', 'target' => ''],
            ['name' => 'Interior In-Law Suite w/Private Entry', 'target' => ''],
            ['name' => 'Interior In-Law Suite w/No Private Entry', 'target' => ''],
            ['name' => 'Loft', 'target' => ''],
            ['name' => 'Media Room', 'target' => ''],
            ['name' => 'Storage Rooms', 'target' => ''],
            ['name' => 'Other', 'target' => '.roomOtherRes'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Additional Rooms:</label>
        <select class="grid-picker" name="additional_rooms[]" multiple id="additional_rooms"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($additional_rooms as $additional_room)
                <option value="{{ $additional_room['name'] }}" data-target="{{ $additional_room['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                    {{ $additional_room['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group roomOtherRes d-none">
            <label class="fw-bold">Additional Rooms:</label>
            <input type="text" name="roomOther" id="number_of_buildings" placeholder=""
                class="form-control has-icon" data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='20'>
    @php
        $laundryRes = [
            ['name' => 'Common Area', 'target' => ''],
            ['name' => 'Corridor Access', 'target' => ''],
            ['name' => 'Electric Dryer Hookup', 'target' => ''],
            ['name' => 'Gas Dryer Hookup', 'target' => ''],
            ['name' => 'In Garage', 'target' => ''],
            ['name' => 'In Kitchen', 'target' => ''],
            ['name' => 'Inside', 'target' => ''],
            ['name' => 'Laundry Chute', 'target' => ''],
            ['name' => 'Laundry Closet', 'target' => ''],
            ['name' => 'Laundry Room', 'target' => ''],
            ['name' => 'Outside', 'target' => ''],
            ['name' => 'Same Floor As Condo Unit', 'target' => ''],
            ['name' => 'Upper Floor', 'target' => ''],
            ['name' => 'Washer Hookup', 'target' => ''],
            ['name' => 'None', 'target' => ''],
            ['name' => 'Other', 'target' => '.laundryOtherRes'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Laundry Features:</label>
        <select class="grid-picker" name="laundry[]" multiple style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($laundryRes as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group laundryOtherRes d-none">
            <label class="fw-bold">Laundry Features: </label>
            <input type="text" name="laundryOther" id="number_of_buildings" placeholder=""
                class="form-control has-icon" data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
<div class="wizard-step" data-step='21'>
    <div class="form-group">
        <label class="fw-bold">How many floors are in the property? </label>
        <input type="number" name="propFloors" id="number_of_buildings" placeholder=""
            class="form-control has-icon" data-icon="fa-solid fa-hotel">
    </div>
    <div class="form-group">
        <label class="fw-bold">What floor number is the property on?</label>
        <input type="number" name="floorNumber" id="floors_in_unit" placeholder="" class="form-control has-icon"
            data-icon="fa-solid fa-hotel">
    </div>

    <div class="form-group">
        <label class="fw-bold">How many floors are in the entire building? </label>
        <input type="number" name="totalFloors" id="total_floors" placeholder="" class="form-control has-icon"
            data-icon="fa-solid fa-hotel">
    </div>
    <span class="commercialFields">
        <div class="form-group">
            <label class="fw-bold">Total Number of Buildings: </label>
            <input type="text" name="totalBuildings" placeholder="" class="form-control has-icon"
                data-icon="fa-solid fa-hotel">
        </div>
    </span>
    <div class="form-group">
        <label class="fw-bold">Building Elevator:</label>
        <select class="grid-picker" name="building_elevator" id="building_elevator"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($yes_or_nos as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="fa-solid fa-building"></i>'>
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="wizard-step" data-step='22'>
    @php
        $floor_coverings = [
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
            ['name' => 'Luxury Vinyl', 'target' => ''],
            ['name' => 'Marble', 'target' => ''],
            ['name' => 'Parquet', 'target' => ''],
            ['name' => 'Porcelain Tile', 'target' => ''],
            ['name' => 'Quarry Tile', 'target' => ''],
            ['name' => 'Reclaimed Wood', 'target' => ''],
            ['name' => 'Recycled/Composite Flooring', 'target' => ''],
            ['name' => 'Slate', 'target' => ''],
            ['name' => 'Terrazzo', 'target' => ''],
            ['name' => 'Tile', 'target' => ''],
            ['name' => 'Travertine', 'target' => ''],
            ['name' => 'Vinyl', 'target' => ''],
            ['name' => 'Wood', 'target' => ''],
            ['name' => 'Other', 'target' => '.floorCoveringOtherRes'],
        ];
    @endphp
    <div class="form-group ">
        <label class="fw-bold">Floor Covering:</label>
        <select class="grid-picker" name="floor_covering[]" id="floor_covering" style="justify-content: flex-start;"
            multiple required>
            <option value="">Select</option>
            @foreach ($floor_coverings as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group  floorCoveringOtherRes d-none">
            <label class="fw-bold">Floor Covering:</label>
            <input type="text" name="floorConvringOther" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
        </div>
    </div>
</div>
