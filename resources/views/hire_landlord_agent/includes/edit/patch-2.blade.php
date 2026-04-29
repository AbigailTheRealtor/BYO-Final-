<div class="wizard-step" data-step="11">
    <div class="form-group">
        <div class="form-group">
            <label class="fw-bold">Heated Sqft: </label>
            <input type="number" name="heated_square_footage" value="{{isset($auction->get->heated_square_footage) ? $auction->get->heated_square_footage : ''}}"
                data-type="heated_square_footage" id="heated_square_footage"
                class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                data-msg-required="" required />
        </div>
        <div class="form-group commercial_hide">
            <div class="form-group">
                <label class="fw-bold">Net Leaseable Sqft: </label>
                <input type="number" name="net_leasable_square_footage"id="net_square_footage" value="{{isset($auction->get->net_leasable_square_footage) ? $auction->get->net_leasable_square_footage : ''}}"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                    data-msg-required="" required />
            </div>
        </div>
        <div class="form-group">
            <label class="fw-bold">Total Sqft: </label>
            <input type="number" name="totalSqft" id="net_square_footage" value="{{isset($auction->get->totalSqft) ? $auction->get->totalSqft : ''}}"
                class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                data-msg-required="" required />
        </div>
    </div>
    <span class="resFields">
        @php
            $sqftRes = [
                ['name' => 'Appraisal', 'target' => ''],
                ['name' => 'Building', 'target' => ''],
                ['name' => 'Measure', 'target' => ''],
                ['name' => 'Owner Provided', 'target' => ''],
                ['name' => 'Public Records', 'target' => ''],
                ['name' => 'Other', 'target' => '.other_heated_res'],
            ];
        @endphp
        <div class="form-group">
            <label class="fw-bold">Sqft Heated Source:</label>
            <select class="grid-picker" name="heated_sqft" id="prop_condition"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($sqftRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                        style="width:calc(50% - 10px);" {{isset($auction->get->heated_sqft) && $auction->get->heated_sqft === $item['name'] ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group other_heated_res d-none">
            <label class="fw-bold"> Sqft Heated Source:</label>
            <input type="text" class="form-control has-icon" name="other_heated_sqft" value="{{isset($auction->get->other_heated_sqft) ? $auction->get->other_heated_sqft : ''}}"
                data-icon="fa-regular fa-circle-check" id="custom_property_condition"
                required />
        </div>
    </span>
    <span class="commercialFields">
        @php
            $leaseCommercial = [
                ['name' => 'Appraisal', 'target' => ''],
                ['name' => 'Building', 'target' => ''],
                ['name' => 'Measured', 'target' => ''],
                ['name' => 'Owner Provided', 'target' => ''],
                ['name' => 'Public Records', 'target' => ''],
                ['name' => 'Other', 'target' => '.other_lease_commercial'],
            ];
        @endphp
        <div class="form-group">
            <label class="fw-bold">Sqft Total Source: </label>
            <select class="grid-picker" name="lease_sqft" id="prop_condition"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($leaseCommercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                        style="width:calc(50% - 10px);" {{isset($auction->get->lease_sqft) && $auction->get->lease_sqft === $item['name'] ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group other_lease_commercial d-none">
            <label class="fw-bold"> Sqft Total Source: </label>
            <input type="text" class="form-control has-icon" name="other_lease_sqft" value="{{isset($auction->get->other_lease_sqft) ? $auction->get->other_lease_sqft : ''}}"
                data-icon="fa-regular fa-circle-check" id="custom_property_condition"
                required />
        </div>
    </span>
    @php
        $acreageRes = [
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
        <select class="grid-picker" name="total_acreage" id="total_acreage"
            style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($acreageRes as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column" style="width:calc(25% - 10px);"
                    data-icon='<i class="fa-solid fa-ruler-combined"></i>' {{isset($auction->get->total_acreage) && $auction->get->total_acreage === $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="wizard-step" data-step="12" data-old="14">
    <span class="resFields">
        @php
            $garageOptions = [
                [
                    'target' => '.garageYes',
                    'name' => 'Yes',
                    'icon' => 'fa-solid fa-warehouse',
                ],
                [
                    'target' => '.garageNo',
                    'name' => 'No',
                    'icon' => 'fa-solid fa-warehouse',
                ],
            ];
        @endphp
        <div class="row align-items-end mt-4">
            <div class="col-md-12">
                <label class="fw-bold" for="heated_sqft">Garage:</label>
                <div class="select2-parent">
                    <select name="garageOptions" class="grid-picker" id="" required>
                        <option value=""></option>
                        @foreach ($garageOptions as $item)
                            <option value="{{ $item['name'] }}"
                                data-target="{{ $item['target'] }}" class="card flex-column "
                                style="width:calc(33.3% - 10px);"
                                data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->garageOptions) && $auction->get->garageOptions === $item['name'] ? 'selected' : '' }} >
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-group garageYes d-none">
                        <label class="fw-bold" for="heated_sqft">How many garage spaces?
                        </label>
                        <input type="number" name="custom_garage" id="total_acreage" value="{{isset($auction->get->custom_garage) ? $auction->get->custom_garage : '' }}"
                            class="form-control has-icon hide_arrow"
                            data-icon="fa-solid fa-warehouse" required>
                    </div>
                </div>

            </div>
        </div>
        @php
            $carportOptions = [
                [
                    'target' => '.carportOptionsYes',
                    'name' => 'Yes',
                    'icon' => 'fa-solid fa-warehouse',
                ],
                [
                    'target' => '.carportOptionsNo',
                    'name' => 'No',
                    'icon' => 'fa-solid fa-warehouse',
                ],
            ];
        @endphp
        <div class="row align-items-end mt-4">
            <div class="col-md-12">
                <labal class="fw-bold" for="heated_sqft">Carport:</labal>
                <div class="select2-parent">
                    <select name="carportOptions" class="grid-picker" id=""
                        required>
                        @foreach ($carportOptions as $item)
                            <option value="{{ $item['name'] }}"
                                data-target="{{ $item['target'] }}" class="card flex-column "
                                style="width:calc(33.3% - 10px);"
                                data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->carportOptions) && $auction->get->carportOptions === $item['name'] ? 'selected' : '' }} >
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>

                    <div class="form-group carportOptionsYes d-none">
                        <label class="fw-bold" for="heated_sqft">How many carport spaces?
                        </label>
                        <input type="number" name="custom_carport" id="total_acreage" value="{{isset($auction->get->custom_carport) ? $auction->get->custom_carport : '' }}"
                            class="form-control has-icon hide_arrow"
                            data-icon="fa-solid fa-warehouse" required>
                    </div>
                </div>

            </div>
        </div>
</div>
<div class="wizard-step" data-step="13" data-old="15">
    <div class="form-group">
        <label class="fw-bold">Pool:</label>
        </label>
        @php
            $poolOptions = [
                [
                    'name' => 'Yes',
                    'target' => '.poolYesRes',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                [
                    'name' => 'No',
                    'target' => '.poolNoRes',
                    'icon' => 'fa-regular fa-circle-xmark',
                ],
            ];
            $poolRes = [
                ['name' => 'Private', 'target' => '.poolPrivate'],
                ['name' => 'Community', 'target' => '.poolCommunity'],
            ];
        @endphp
        <select name="poolOptions" id="contribute_term" class="grid-picker"
            style="justify-content: flex-start;" required>
            <option value=""></option>
            @foreach ($poolOptions as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column " style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->poolOptions) && $auction->get->poolOptions === $item['name'] ? 'selected' : '' }} >
                    {{ $item['name'] }}
                </option>
            @endforeach
        </select>
        <div class="form-group poolYesRes d-none  ">
            <select name="pool" id="" class="grid-picker"
                style="justify-content: flex-start;" required>
                <option value=""></option>
                @foreach ($poolRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->pool) && $auction->get->pool === $item['name'] ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group  d-none ">
            <label class="fw-bold">How many pool spaces?</label>
            <input type="number" name="customPool" data-type="pool" value="{{isset($auction->get->customPool) ? $auction->get->customPool : '' }}"
                placeholder="Enter Heated Square Footage" id="pool"
                class="form-control has-icon"
                data-msg-required="Please Enter Heated Square Footage"
                data-icon="fa-regular fa-circle-check" required>
        </div>
    </div>
    </span>
    <div class="row align-items-end mt-4">
        @php
            $views = [
                ['target' => '', 'name' => 'City', 'icon' => 'fa-regular fa-circle-check'],
                [
                    'target' => '',
                    'name' => 'Garden',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                [
                    'target' => '',
                    'name' => 'Golf Course',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                [
                    'target' => '',
                    'name' => 'Greenbelt',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                [
                    'target' => '',
                    'name' => 'Mountain(s)',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                ['target' => '', 'name' => 'Park', 'icon' => 'fa-regular fa-circle-check'],
                [
                    'target' => '',
                    'name' => 'Tennis Court',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                [
                    'target' => '',
                    'name' => 'Trees/Woods',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                ['target' => '', 'name' => 'Water', 'icon' => 'fa-regular fa-circle-check'],
                [
                    'target' => '.preferenceNo',
                    'name' => 'Beach',
                    'icon' => 'fa-regular fa-circle-check',
                ],
                ['target' => '', 'name' => 'Pool', 'icon' => 'fa-regular fa-circle-check'],
                [
                    'target' => '.viewOther',
                    'name' => ' Other',
                    'icon' => 'fa-regular fa-circle-check',
                ],
            ];
        @endphp
        <div class="select2-parent">
            <label class="fw-bold" for="heated_sqft">View:</label>
            <select name="view[]" class="grid-picker" multiple required>
                @foreach ($views as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->view) && in_array($item['name'], $auction->get->view) ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group viewOther d-none">
                <label class="fw-bold" for="">View:</label>
                <input type="text" name="viewOther" id="total_acreage" value="{{isset($auction->get->viewOther) ? $auction->get->viewOther : ''}}"
                    class="form-control has-icon hide_arrow"
                    data-icon="fa-regular fa-circle-check" required>
            </div>
        </div>
    </div>
</div>
<div class="wizard-step" data-step="14" data-old="17">
    <span class="commercialFields">
        <div class="form-group">
            @php
                $garageOption = [
                    [
                        'target' => '.garageOptionYes',
                        'name' => 'Yes',
                        'icon' => 'fa-regular fa-circle-check',
                    ],
                    [
                        'target' => '.garageOptionNo',
                        'name' => 'No',
                        'icon' => 'fa-regular fa-circle-xmark',
                    ],
                ];
            @endphp
            <label class="fw-bold">Garage/Parking Features:</label>
            <select name="parkingOptions" class="grid-picker" id="garage"
                style="justify-content: flex-start;" required>
                @foreach ($garageOption as $item)
                    <option value="{{ $item['name'] }}"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'
                        data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" {{isset($auction->get->parkingOptions) && $auction->get->parkingOptions === $item['name'] ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            @php
                $garageParking = [
                    ['name' => '1 to 5 Spaces', 'target' => ''],
                    ['name' => '6 to 12 Spaces', 'target' => ''],
                    ['name' => '13 to 18 Spaces', 'target' => ''],
                    ['name' => '19 to 30 Spaces', 'target' => ''],
                    ['name' => 'Airplane Hangar', 'target' => ''],
                    ['name' => 'Common', 'target' => ''],
                    ['name' => 'Curb Parking', 'target' => ''],
                    ['name' => 'Deeded', 'target' => ''],
                    ['name' => 'Electric Vehicle Charging Station(s)', 'target' => ''],
                    ['name' => 'Ground Level', 'target' => ''],
                    ['name' => 'Lighted', 'target' => ''],
                    ['name' => 'Over 30 Spaces', 'target' => ''],
                    ['name' => 'RV Parking', 'target' => ''],
                    ['name' => 'Secured', 'target' => ''],
                    ['name' => 'Under Building', 'target' => ''],
                    ['name' => 'Underground', 'target' => ''],
                    ['name' => 'Valet', 'target' => ''],
                    ['name' => 'None', 'target' => ''],
                    ['name' => 'Other', 'target' => '.other_garage'],
                ];
            @endphp
            <div class="form-group d-none garageOptionYes">
                <select class="grid-picker" name="parking"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($garageParking as $item)
                        <option value="{{ $item['name'] }}"
                            data-target="{{ $item['target'] }}" class="card flex-column"
                            style="width:calc(25% - 10px);"
                            data-icon='<i class="fa-solid fa-warehouse"></i>' {{isset($auction->get->parking) && $auction->get->parking === $item['name'] ? 'selected' : '' }} >
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group other_garage d-none">
                    <label class="fw-bold" for="other_garage">Garage/Parking Features:</label>
                    <input type="text" name="parkingOther" value="{{isset($auction->get->parkingOther) ? $auction->get->parkingOther : '' }}"
                        class="form-control has-icon hide_arrow"
                        data-icon="fa-solid fa-warehouse" required>
                </div>
            </div>
        </div>
    </span>
</div>
<div class="wizard-step" data-step="15" data-old="18">
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
            ['name' => 'Other', 'target' => '.cutom_appliances'],
        ];
    @endphp
    <div class="form-group">
        <label class="fw-bold">Appliances: </label>

        <select class="grid-picker" name="appliances[]" id="appliances"
            style="justify-content: flex-start;" multiple required>
            <option value="">Select</option>
            @foreach ($appliances as $appliance)
                <option value="{{ $appliance['name'] }}"
                    data-target="{{ $appliance['target'] }}" class="card flex-row"
                    style="width:calc(33.33% - 10px);"
                    data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->appliances) && in_array($appliance['name'], $auction->get->appliances) ? 'selected' : '' }} >
                    {{ $appliance['name'] }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group cutom_appliances d-none">
        <label class="fw-bold">Appliances: </label>
        <input type="text" class="form-control has-icon" name="otherAppliances" value="{{isset($auction->get->otherAppliances) ? $auction->get->otherAppliances : ''}}"
            data-icon="fa-regular fa-circle-check" id="cutom_appliances" required />
    </div>
</div>
<div class="wizard-step" data-step="16" data-old="19">
    <span class="resFields">
        @php
            $Furnishings = [
                ['name' => 'Furnished', 'target' => ''],
                ['name' => 'Optional', 'target' => ''],
                ['name' => 'Partial', 'target' => ''],
                ['name' => 'Turnkey', 'target' => ''],
                ['name' => 'Unfurnished', 'target' => ''],
            ];
        @endphp
        <div class="form-group">
            <label class="fw-bold">Furnishings: </label>

            <select class="grid-picker" name="furnishings[]" id="appliances"
                style="justify-content: flex-start;" multiple required>
                <option value="">Select</option>
                @foreach ($Furnishings as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.33% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->furnishings) && in_array($item['name'], $auction->get->furnishings) ? 'selected' : '' }} >
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </span>
</div>