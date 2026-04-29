<div class="wizard-step" data-step="17" data-old="20">
    <span class="commercialFields">
        <div class="form-group">
            <label class="fw-bold">
                Amenities and Property Features:
            </label>
            @php
                $amenitiesFeatureCommercial = [
                    ['name' => 'Access to Public Transportation', 'target' => ''],
                    ['name' => 'Business Center', 'target' => ''],
                    ['name' => 'Common Areas', 'target' => ''],
                    ['name' => 'Conference Room', 'target' => ''],
                    ['name' => 'Elevator', 'target' => ''],
                    ['name' => 'Energy-Efficient Features', 'target' => ''],
                    ['name' => 'Fire Safety Systems', 'target' => ''],
                    ['name' => 'Flexibility for Renovations', 'target' => ''],
                    ['name' => 'Green Building Certification', 'target' => ''],
                    ['name' => 'Gym/Fitness Facilities', 'target' => ''],
                    ['name' => 'Handicap Accessibility', 'target' => ''],
                    ['name' => 'High-Speed Internet', 'target' => ''],
                    ['name' => 'HVAC System', 'target' => ''],
                    ['name' => 'Industrial Features', 'target' => ''],
                    ['name' => 'Kitchenette/Break Room', 'target' => ''],
                    ['name' => 'Loading Dock', 'target' => ''],
                    ['name' => 'Lounge Area', 'target' => ''],
                    ['name' => 'Natural Lighting', 'target' => ''],
                    ['name' => 'Office Space', 'target' => ''],
                    ['name' => 'On-site Maintenance', 'target' => ''],
                    ['name' => 'On-site Management', 'target' => ''],
                    ['name' => 'Open Floor Plan', 'target' => ''],
                    ['name' => 'Parking Spaces', 'target' => ''],
                    ['name' => 'Proximity to Highways', 'target' => ''],
                    ['name' => 'Reception Area', 'target' => ''],
                    ['name' => 'Restrooms', 'target' => ''],
                    ['name' => 'Retail Frontage', 'target' => ''],
                    ['name' => 'Restaurant Space', 'target' => ''],
                    ['name' => 'Security Guard', 'target' => ''],
                    ['name' => 'Security System', 'target' => ''],
                    ['name' => 'Signage Opportunities', 'target' => ''],
                    ['name' => 'Storage Space', 'target' => ''],
                    ['name' => 'Utilities Included', 'target' => ''],
                    ['name' => 'Visibility from Main Road', 'target' => ''],
                    ['name' => 'Warehouse Space', 'target' => ''],
                    ['target' => '.otherAmenitiesFeatureCommercial', 'name' => 'Other'],
                ];
            @endphp
            <select name="amenities[]" id="negotiable_terms" class="grid-picker"
                style="justify-content: flex-start;" multiple required>
                <option value=""></option>
                @foreach ($amenitiesFeatureCommercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" 
                    style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->amenities) && in_array($item['name'], $auction->get->amenities) ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group otherAmenitiesFeatureCommercial d-none">
            <label class="fw-bold" for="custom_non_negotiable_terms"> Amenities and Property
                Features:
            </label>
            <input type="text" name="otherAmenities" id="custom_non_negotiable_terms" value="{{isset($auction->get->otherAmenities) ? $auction->get->otherAmenities : ''}}"
                placeholder="" class="form-control" data-icon="fa-regular fa-circle-check"
                required>
        </div>
    </span>
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
                    ['target' => '', 'name' => 'Carpet Floors'],
                    ['target' => '', 'name' => 'Carport'],
                    ['target' => '', 'name' => 'Central Air Conditioning'],
                    ['target' => '', 'name' => 'Central Heating'],
                    ['target' => '', 'name' => 'Clubhouse'],
                    ['target' => '', 'name' => 'Covered Carport'],
                    ['target' => '', 'name' => 'Elevator'],
                    ['target' => '', 'name' => 'Fireplace'],
                    ['target' => '', 'name' => 'Fitness Center/Gym'],
                    ['target' => '', 'name' => 'First Floor Unit'],
                    ['target' => '', 'name' => 'Gated Community'],
                    ['target' => '', 'name' => 'Garage'],
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
                    ['target' => '', 'name' => 'Washer and Dryer'],
                    ['target' => '', 'name' => 'Washer and Dryer Hookup'],
                    ['target' => '', 'name' => 'Waterfront'],
                    ['target' => '.otherAmenitiesFeatureRes', 'name' => 'Other'],
                ];
            @endphp
            <select name="amenities[]" id="negotiable_terms" class="grid-picker"
                style="justify-content: flex-start;" multiple required>
                <option value=""></option>
                @foreach ($amenitiesFeatureRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->amenities) && in_array($item['name'], $auction->get->amenities) ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group otherAmenitiesFeatureRes d-none">
            <label class="fw-bold" for="custom_negotiable_terms"> Amenities and Property Features:
            </label>
            <input type="text" name="otherAmenities" id="custom_negotiable_terms" value="{{isset($auction->get->otherAmenities) ? $auction->get->otherAmenities : ''}}"
                placeholder="" class="form-control has-icon"
                data-icon="fa-regular fa-circle-check" required>
        </div>
    </span>
</div>
<div class="wizard-step" data-step="18" data-old="21">
    @php
        $rent_includes = [
            ['name' => 'Cable TV', 'target' => ''],
            ['name' => 'Electricity', 'target' => ''],
            ['name' => 'Gas', 'target' => ''],
            ['name' => 'Grounds Care', 'target' => ''],
            ['name' => 'Insurance', 'target' => ''],
            ['name' => 'Internet', 'target' => ''],
            ['name' => 'Laundry', 'target' => ''],
            ['name' => 'Management', 'target' => ''],
            ['name' => 'Pest Control', 'target' => ''],
            ['name' => 'Pool Maintenance', 'target' => ''],
            ['name' => 'Recreational', 'target' => ''],
            ['name' => 'Repairs', 'target' => ''],
            ['name' => 'Security', 'target' => ''],
            ['name' => 'Sewer', 'target' => ''],
            ['name' => 'Taxes', 'target' => ''],
            ['name' => 'Telephone', 'target' => ''],
            ['name' => 'Trash Collection', 'target' => ''],
            ['name' => 'Water', 'target' => ''],
            ['name' => 'None', 'target' => ''],
            ['name' => 'Other', 'target' => '.rent_include'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Rent Includes: </label>
        <select class="grid-picker" name="rent_include[]" id="rent_include"
            style="justify-content: flex-start;" multiple required>
            <option value="">Select</option>
            @foreach ($rent_includes as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" style="width:calc(33.33% - 10px);"
                    data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->rent_include) && in_array($item['name'], $auction->get->rent_include) ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group rent_include d-none">
        <label class="fw-bold">Rent Includes: </label>
        <input type="text" class="form-control has-icon" name="other_rent_include" value="{{isset($auction->get->other_rent_include) ? $auction->get->other_rent_include : ''}}"
            data-icon="fa-regular fa-circle-check" id="rent_include" required />
    </div>
</div>
<div class="wizard-step" data-step="19" data-old="22">
    @php
        $tenantPays = [
            ['name' => 'Association Fees', 'target' => ''],
            ['name' => 'Capital Expenses', 'target' => ''],
            ['name' => 'Common Area Maintenance', 'target' => ''],
            ['name' => 'Condominium Fees', 'target' => ''],
            ['name' => 'Electricity', 'target' => ''],
            ['name' => 'Gas', 'target' => ''],
            ['name' => 'Liability Insurance', 'target' => ''],
            ['name' => 'Parking Fee', 'target' => ''],
            ['name' => 'Pro-Rated', 'target' => ''],
            ['name' => 'Property Insurance', 'target' => ''],
            ['name' => 'Property Taxes', 'target' => ''],
            ['name' => 'Reserves', 'target' => ''],
            ['name' => 'Sewer', 'target' => ''],
            ['name' => 'Trash Collection', 'target' => ''],
            ['name' => 'Water', 'target' => ''],
            ['name' => 'None ', 'target' => ''],
            ['name' => 'Other', 'target' => '.otherTenantPays'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Tenant Pays:</label>
        <select class="grid-picker" name="tenantPays[]" id="rent_include"
            style="justify-content: flex-start;" multiple required>
            <option value="">Select</option>
            @foreach ($tenantPays as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" style="width:calc(33.33% - 10px);"
                    data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->tenantPays) && in_array($item['name'], json_decode($auction->get->tenantPays, true) ?? []) ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group otherTenantPays d-none">
        <label class="fw-bold">Tenant Pays: </label>
        <input type="text" class="form-control has-icon" placeholder="" value="{{isset($auction->get->otherTenantPays) ? $auction->get->otherTenantPays : ''}}"
            name="otherTenantPays" data-icon="fa-regular fa-circle-check" id="rent_include"
            required />
    </div>
    @php
        $ownerPays = [
            ['name' => 'Cable TV', 'target' => ''],
            ['name' => 'Electricity', 'target' => ''],
            ['name' => 'Gas', 'target' => ''],
            ['name' => 'Grounds Care', 'target' => ''],
            ['name' => 'Insurance', 'target' => ''],
            ['name' => 'Internet', 'target' => ''],
            ['name' => 'Laundry', 'target' => ''],
            ['name' => 'Management', 'target' => ''],
            ['name' => 'Pest Control', 'target' => ''],
            ['name' => 'Pool Maintenance', 'target' => ''],
            ['name' => 'Recreational', 'target' => ''],
            ['name' => 'Repairs', 'target' => ''],
            ['name' => 'Security', 'target' => ''],
            ['name' => 'Sewer', 'target' => ''],
            ['name' => 'Taxes', 'target' => ''],
            ['name' => 'Telephone', 'target' => ''],
            ['name' => 'Trash Collection', 'target' => ''],
            ['name' => 'Water', 'target' => ''],
            ['name' => 'None', 'target' => ''],
            ['name' => 'Other', 'target' => '.otherOwnerPays'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Owner Pays:</label>
        <select class="grid-picker" name="ownerPays[]" id=""
            style="justify-content: flex-start;" multiple required>
            <option value="">Select</option>
            @foreach ($ownerPays as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" style="width:calc(33.33% - 10px);"
                    data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->ownerPays) && in_array($item['name'], json_decode($auction->get->ownerPays, true) ?? []) ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group otherOwnerPays d-none">
        <label class="fw-bold">Owner Pays:</label>
        <input type="text" class="form-control has-icon" placeholder="" value="{{isset($auction->get->otherOwnerPays) ? $auction->get->otherOwnerPays : ''}}"
            name="otherOwnerPays" data-icon="fa-regular fa-circle-check" id="rent_include"
            required />
    </div>

</div>
<div class="wizard-step" data-step="20" data-old="23">
    @php
        $petOptions = [
            [
                'target' => '.petYes',
                'name' => 'Yes',
                'icon' => 'fa-solid fa-dog',
            ],
            ['target' => '.petNo', 'name' => 'No', 'icon' => 'fa-solid fa-dog'],
        ];
    @endphp
    <div class="row align-items-end mt-4">
        <div class="col-md-12">
            <labal class="fw-bold" for="heated_sqft">Will the landlord accept pets?</labal>
            <div class="select2-parent">
                <select name="petOptions" class="grid-picker" id="" required>
                    <option value=""></option>
                    @foreach ($petOptions as $item)
                        <option value="{{ $item['name'] }}"
                            data-target="{{ $item['target'] }}" class="card flex-column "
                            style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->petOptions) && $auction->get->petOptions == $item['name'] ? 'selected' : '' }} >
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group petYes d-none">
                <div class="form-group">
                    <label class="fw-bold" for="heated_sqft">Number of Pets Allowed:</label>
                    <input type="text" name="petsNumber" id="total_acreage" value="{{isset($auction->get->petsNumber) ? $auction->get->petsNumber : ''}}"
                        class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" >
                </div>
                <div class="form-group">
                    <label class="fw-bold" for="heated_sqft">Acceptable Pet Types:</label>
                    <input type="text" name="petsType" id="total_acreage" value="{{isset($auction->get->petsType) ? $auction->get->petsType : ''}}"
                        class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" >
                </div>
                <div class="form-group">
                    <label class="fw-bold" for="heated_sqft">Maximum Pet Weight:</label>
                    <input type="text" name="petsWeight" id="total_acreage" value="{{isset($auction->get->petsWeight) ? $auction->get->petsWeight : ''}}"
                        class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" >
                </div>
                <div class="form-group">
                    <label class="fw-bold" for="heated_sqft">One-Time Pet Deposit or Monthly
                        Pet Fee:</label>
                    <input type="text" name="petsFee" id="total_acreage" value="{{isset($auction->get->petsFee) ? $auction->get->petsFee : ''}}"
                        class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" >
                </div>
                <div class="form-group">
                    <label class="fw-bold" for="heated_sqft">Pet Fee Amount:</label>
                    <input type="number" name="petsFeeAmount" id="total_acreage" value="{{isset($auction->get->petsFeeAmount) ? $auction->get->petsFeeAmount : ''}}"
                        class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar-sign" >
                </div>
                <div class="form-group">
                    <label class="fw-bold" for="heated_sqft">Is the Pet Fee Refundable or
                        Non-Refundable?</label>
                    <input type="text" name="petsFund" id="total_acreage" value="{{isset($auction->get->petsFund) ? $auction->get->petsFund : ''}}"
                        class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dog" >
                </div>
            </div>
        </div>
    </div>
</div>
<div class="wizard-step" data-step="21" data-old="24">
    <div class="form-group">
        <label class="fw-bold">Is the property located in a 55-and-over community?</label>
        </label>
        @php
            $propertyLoc = [
                ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
            ];
        @endphp
        <select name="propertyLoc" id="propertyLoc" class="grid-picker"
            style="justify-content: flex-start;" required>
            <option value=""></option>
            @foreach ($propertyLoc as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->propertyLoc) && $auction->get->propertyLoc == $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="wizard-step" data-step="22" data-old="25">
    <span class="resFields">
        @php
            $leaseAmount = [
                ['name' => 'Annually', 'target' => ''],
                ['name' => 'Daily', 'target' => ''],
                ['name' => 'Monthly', 'target' => ''],
                ['name' => 'Seasonally', 'target' => '.season_runs'],
                ['name' => 'Weekly', 'target' => ''],
            ];
        @endphp
        <div class="form-group">
            <label class="fw-bold">Select the frequency at which the lease amount is paid:
            </label>

            <select class="grid-picker" name="leaseAmount" id="appliances"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($leaseAmount as $item)
                    <option value="{{ $item['name'] }}"
                        data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.33% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->leaseAmount) && $auction->get->leaseAmount == $item['name'] ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="season_runs d-none">
            <div class="form-group">
                <label class="fw-bold">Season runs from:</label>
                <input type="date" name="season_runs_from" id="season_runs_from" class="form-control has-icon" value="{{isset($auction->get->season_runs_from) ? $auction->get->season_runs_from : ''}}"
                    data-icon="fa-regular fa-calendar-days" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Season runs to:</label>
                <input type="date" name="season_runs_to" id="season_runs_to" class="form-control has-icon" value="{{isset($auction->get->season_runs_to) ? $auction->get->season_runs_to : ''}}"
                    data-icon="fa-regular fa-calendar-days" required>
            </div>
        </div>
    </span>
    <span class="commercialFields">
        @php
            $leaseAmount = [
                ['name' => 'Annually', 'target' => ''],
                ['name' => 'Monthly', 'target' => ''],
            ];
        @endphp
        <div class="form-group">
            <label class="fw-bold">Select the frequency in which the Lease Amount is paid:
            </label>

            <select class="grid-picker" name="leaseAmount" id="appliances"
                style="justify-content: flex-start;" multiple required>
                <option value="">Select</option>
                @foreach ($leaseAmount as $item)
                    <option value="{{ $item['name'] }}"
                        data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.33% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->leaseAmount) && $auction->get->leaseAmount == $item['name'] ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </span>
</div>