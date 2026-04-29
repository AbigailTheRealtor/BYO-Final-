<div class="wizard-step" data-step="33">
    @php
      $air_conditioning = [['name' => 'Central Air', 'target' => ''], ['name' => 'Humidity Control', 'target' => ''], ['name' => 'Mini-Split Unit(s)', 'target' => ''], ['name' => 'Wall/Window Unit(s)', 'target' => ''], ['name' => 'Zoned', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.airConditionRes']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Air Conditioning:</label>
      <select class="grid-picker" name="air_conditioning[]" id="air_conditioning"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($air_conditioning as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->air_conditioning) && in_array($item['name'], json_decode($auction->get->air_conditioning) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group airConditionRes d-none">
        <label class="fw-bold">Air Conditioning:</label>
        <input type="text" name="otherAirCondition" value="{{isset($auction->get->otherAirCondition) ? $auction->get->otherAirCondition : ''}}" class="form-control has-icon"
          data-icon="fa-regular fa-circle-check">
      </div>
    </div>
    @php
      $heating_and_fuel = [
        ['name' => 'Baseboard', 'target' => ''], 
        ['name' => 'Central', 'target' => ''], 
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
        ['name' => 'Other', 'target' => '.otherHeatingFuelRes']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Heating and Fuel:</label>
      <select class="grid-picker" name="heating_and_fuel[]" id="heating_and_fuel"
        style="justify-content: flex-start;" multiple >
        <option value="">Select</option>
        @foreach ($heating_and_fuel as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->heating_and_fuel) && in_array($item['name'], json_decode($auction->get->heating_and_fuel) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherHeatingFuelRes d-none">
        <label class="fw-bold">Heating and Fuel:</label>
        <input type="text" name="otherHeatingFuel" value="{{isset($auction->get->otherHeatingFuel) ? $auction->get->otherHeatingFuel : ''}}" class="form-control has-icon"
          data-icon="fa-regular fa-circle-check">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="34">
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
          ['name' => 'Dining Room', 'target' => ''],
          ['name' => 'Family Room', 'target' => ''],
          ['name' => 'Florida Room', 'target' => ''],
          ['name' => 'Foyer', 'target' => ''],
          ['name' => 'Game Room', 'target' => ''],
          ['name' => 'Great Room', 'target' => ''],
          ['name' => 'Gym', 'target' => ''],
          ['name' => 'Inside Utility', 'target' => ''],
          ['name' => 'Interior In-Law Suite', 'target' => ''],
          ['name' => 'Kitchen', 'target' => ''],
          ['name' => 'Laundry', 'target' => ''],
          ['name' => 'Library', 'target' => ''],
          ['name' => 'Living Room', 'target' => ''],
          ['name' => 'Loft', 'target' => ''],
          ['name' => 'Primary Bathroom', 'target' => ''],
          ['name' => 'Primary Bedroom', 'target' => ''],
          ['name' => 'Media Room', 'target' => ''],
          ['name' => 'Office', 'target' => ''],
          ['name' => 'Sauna', 'target' => ''],
          ['name' => 'Studio', 'target' => ''],
          ['name' => 'Study/Den', 'target' => ''],
          ['name' => 'Workshop', 'target' => ''],
          ['name' => 'Dinette', 'target' => ''],
          ['name' => 'Garage Room', 'target' => ''],
          ['name' => 'Garage Apartment', 'target' => ''],
          ['name' => 'Double Primary Bedroom', 'target' => ''],
      ];
    @endphp
    <div class="form-group ">
      @php
        $roomData = isset($auction->get->room_details_data) && !is_array($auction->get->room_details_data) ? json_decode($auction->get->room_details_data, true) : [];
        $roomDetailsData = isset($roomData) && $auction->get->property_type == 'Residential Property' ? json_decode($roomData, true) : [];
        $roomTypes = [];
        foreach ($roomDetailsData as $roomName => $values) {
            $roomTypes[] = $roomName;
        }
      @endphp
      <label class="fw-bold">Room Type:</label>
      <select class="grid-picker" name="room_type[]" id="room_type" style="justify-content: flex-start;"
        multiple>
        <option value="">Select</option>
        @foreach ($room_types as $room_type)
          <option value="{{ $room_type['name'] }}" data-target="{{ $room_type['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);"  {{isset($roomTypes) && in_array($room_type['name'], $roomTypes) ? 'selected' : ''}}>
            {{ $room_type['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <input type="hidden" id="room_type_input" name="room_details_data[]" value="{{isset($roomTypeData) ? $roomTypeData : ''}}" />
    <div id="dynamicFieldsContainerRoomType"></div>
  </div>
  <div class="wizard-step" data-step="35">
    <h4>Water and Dock Information:</h4>
    <div class="form-group ">
      <label class="fw-bold">Water View:</label>
      <select class="grid-picker" name="has_water_view" id="has_water_view"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_view_residential_and_income';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_view) && $auction->get->has_water_view == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $water_views = [['name' => 'Bay/Harbor - Full', 'target' => ''], ['name' => 'Bay/Harbor - Partial', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Canal', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Gulf/Ocean - Full', 'target' => ''], ['name' => 'Gulf/Ocean - Partial', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'None', 'target' => '']];
    @endphp
    <div class="form-group water_view_residential_and_income d-none">
      <select class="grid-picker" name="water_view[]" id="water_view" style="justify-content: flex-start;"
        multiple>
        <option value="">Select</option>
        @foreach ($water_views as $water_view)
          <option value="{{ $water_view['name'] }}" data-icon="<i class='fa-regular fa-circle-check'></i>"
            data-target="{{ $water_view['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water_view) && in_array($water_view['name'], json_decode($auction->get->water_view) ?? []) ? 'selected' : ''}}>
            {{ $water_view['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    
    <div class="form-group">
      <label class="fw-bold">Water Extras:</label>
      <select class="grid-picker" name="has_water_extra" id="has_water_extra"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_extras_residential_and_income';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_extra) && $auction->get->has_water_extra == $item['name'] ? 'selected' : ''}}>
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
    <div class="form-group water_extras_residential_and_income d-none ">
      <select class="grid-picker" name="water_extras[]" id="water_extras"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($water_extras as $water_extra)
          <option value="{{ $water_extra['name'] }}" data-target="{{ $water_extra['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water_extras) && in_array($water_extra['name'], json_decode($auction->get->water_extras) ?? []) ? 'selected' : ''}}>
            {{ $water_extra['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Water Frontage:</label>
      <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_frontage_residential_and_income';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_fontage) && $auction->get->has_water_fontage == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $water_frontage = [
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
    <div class="form-group water_frontage_residential_and_income d-none">
      <select class="grid-picker" name="water_frontage[]" id="water_frontage"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($water_frontage as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water_frontage) && in_array($item['name'], json_decode($auction->get->water_frontage) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Water Access:</label>
      <select class="grid-picker" name="has_water_access" id="has_water_access"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_access_residentail';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_access) && $auction->get->has_water_access == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $water_access = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
    @endphp
    <div class="form-group water_access_residentail">
      <select class="grid-picker" name="water_access[]" id="water_access"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($water_access as $water_access1)
          <option value="{{ $water_access1['name'] }}" data-target="{{ $water_access1['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water_access) && in_array($water_access1['name'], json_decode($auction->get->water_access) ?? []) ? 'selected' : ''}}>
            {{ $water_access1['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Dock:</label>
      <select class="grid-picker" name="has_dock" id="has_dock"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.dock_residential_and_income';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_dock) && $auction->get->has_dock == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
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
          ['name' => 'Dock w/Water Supply', 'target' => ''],
          ['name' => 'Dock w/o Water Supply', 'target' => ''],
          ['name' => 'Dry Dock', 'target' => ''],
          ['name' => 'Fish Cleaning Station', 'target' => ''],
          ['name' => 'Floating Dock', 'target' => ''],
          ['name' => 'Harbormaster', 'target' => ''],
          ['name' => 'Internet', 'target' => ''],
          ['name' => 'Lift', 'target' => ''],
          ['name' => 'Restroom/Shower', 'target' => ''],
          ['name' => 'Wet Dock', 'target' => ''],
          ['name' => 'None', 'target' => ''],
          ['name' => 'Other', 'target' => '.other-dock'],
      ];
    @endphp
    <div class="form-group dock_residential_and_income d-none ">
      <label class="fw-bold">Dock Description:</label>
      <select class="grid-picker" name="dock[]" id="dock"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($dock as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" required {{isset($auction->get->dock) && in_array($item['name'], json_decode($auction->get->dock) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group other-dock d-none">
        <label class="fw-bold">Dock Description:</label>
        <input type="text" name="custom_dock" value="{{isset($auction->get->custom_dock) ? $auction->get->custom_dock : ''}}" id="custom_dock"
          class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
      </div>
      <div class="form-group">
        <label class="fw-bold">Dock Lift Capacity:</label>
        <input type="text" name="dock_lift_capacity" value="{{isset($auction->get->dock_lift_capacity) ? $auction->get->dock_lift_capacity : ''}}" id="dock_lift_capacity"
          class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
      </div>
      <div class="form-group">
        <label class="fw-bold">Dock Year Built:</label>
        <input type="number" name="dock_year_built" value="{{isset($auction->get->dock_year_built) ? $auction->get->dock_year_built : ''}}" id="dock_year_built"
          class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
      </div>
      <div class="form-group">
        <label class="fw-bold">Dock Dimension:</label>
        <input type="text" name="dock_dimension" value="{{isset($auction->get->dock_dimension) ? $auction->get->dock_dimension : ''}}" id="dock_dimension"
          class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
      </div>
      <div class="form-group">
        <label class="fw-bold">Dock Maintenance Fee:</label>
        <input type="number" name="dock_maintenance_fee" value="{{isset($auction->get->dock_maintenance_fee) ? $auction->get->dock_maintenance_fee : ''}}" id="dock_maintenance_fee"
          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
      </div>
      @php
        $feeFrequency = [['name' => 'Annual', 'target' => ''], ['name' => 'Monthly', 'target' => ''], ['name' => 'Quarterly', 'target' => ''], ['name' => 'N/A', 'target' => '']]
      @endphp
      <label class="fw-bold">Dock Maintenance Fee Frequency:</label>
      <select class="grid-picker" name="dock_maintenance_fee_frequency" id="dock_maintenance_fee_frequency"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($feeFrequency as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->dock_maintenance_fee_frequency) && $auction->get->dock_maintenance_fee_frequency == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="36">
    <h4>HOA, Condo Association and/or Master Association Information:</h4>
    <div class="form-group">
      <?php
          $propsOpt = [
              ['name' => 'Yes', 'icon' => 'fa-regular fa-circle-check', 'target' => '.hoas_residential_and_income'],
              ['name' => 'No', 'icon' => 'fa-regular fa-circle-xmark', 'target' => '']
          ];
      ?>

      <label class="fw-bold">Does the property have an HOA, condo association, master
        association, and/or community fee?</label>
      <select class="grid-picker" name="has_hoa" id="has_hoa" style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($propsOpt as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_hoa) && $auction->get->has_hoa == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $hoa_fee_requirenments = [['name' => 'None', 'target' => ''], ['name' => 'Optional', 'target' => ''], ['name' => ' Required', 'target' => '']];
    @endphp
    <div class="row hoas_residential_and_income d-none">
      @php
        $community_features = [
            ['name' => 'Airport/Runway', 'target' => ''],
            ['name' => 'Association Recreation - Lease', 'target' => ''],
            ['name' => 'Association Recreation - Owned', 'target' => ''],
            ['name' => 'Buyer Approval Required', 'target' => ''],
            ['name' => 'Clubhouse', 'target' => ''],
            ['name' => 'Community Mailbox', 'target' => ''],
            ['name' => 'Deed Restrictions', 'target' => ''],
            ['name' => 'Dog Park', 'target' => ''],
            ['name' => 'Fitness Center', 'target' => ''],
            ['name' => 'Gated Community - Guard', 'target' => ''],
            ['name' => 'Gated Community - No Guard', 'target' => ''],
            ['name' => 'Golf Carts OK', 'target' => ''],
            ['name' => 'Golf Community', 'target' => ''],
            ['name' => 'Handicap Modified', 'target' => ''],
            ['name' => 'Horse Stable(s)', 'target' => ''],
            ['name' => 'Horses Allowed', 'target' => ''],
            ['name' => 'Irrigation-Reclaimed Water', 'target' => ''],
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
        ];
      @endphp
      <div class="form-group ">
        <label class="fw-bold">Community Features:</label>
        <select class="grid-picker" name="community_feature[]" id="community_feature"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($community_features as $community_feature)
            <option value="{{ $community_feature['name'] }}"
              data-icon="<i class='fa-regular fa-circle-check'></i>"
              data-target="{{ $community_feature['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->community_feature) && in_array($community_feature['name'], json_decode($auction->get->community_feature) ?? []) ? 'selected' : ''}}>
              {{ $community_feature['name'] }}
            </option>
          @endforeach
        </select>
      </div>
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
            ['name' => 'Marina', 'target' => ''],
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
            ['name' => 'Other', 'target' => '.otherAssocAmenitiesRes'],
        ];
      @endphp
      <div class="form-group ">
        <label class="fw-bold">Association Amenities:</label>
        <select class="grid-picker" name="association_amenitie[]" id="association_amenitie"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($association_amenities as $association_amenitie)
            <option value="{{ $association_amenitie['name'] }}"
              data-icon="<i class='fa-regular fa-circle-check'></i>"
              data-target="{{ $association_amenitie['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->association_amenitie) && in_array($association_amenitie['name'], json_decode($auction->get->association_amenitie) ?? []) ? 'selected' : ''}}>
              {{ $association_amenitie['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group otherAssocAmenitiesRes d-none">
          <label class="fw-bold">Association Amenities: </label>
          <input type="text" name="otherAssocAmenities" {{isset($auction->get->otherAssocAmenities) && $auction->get->otherAssocAmenities == $item['name'] ? 'selected' : ''}} class="form-control has-icon" data-icon="fa-regular fa-circle-check">
        </div>
      </div>
      @php
        $fee_includes = [
            ['name' => '24-Hour Guard', 'target' => ''],
            ['name' => 'Cable TV', 'target' => ''],
            ['name' => 'Common Area Taxes', 'target' => ''],
            ['name' => 'Community Pool', 'target' => ''],
            ['name' => 'Electricity', 'target' => ''],
            ['name' => 'Escrow Reserves Fund', 'target' => ''],
            ['name' => 'Fidelity Bond', 'target' => ''],
            ['name' => 'Gas', 'target' => ''],
            ['name' => 'Insurance', 'target' => ''],
            ['name' => 'Internet', 'target' => ''],
            ['name' => 'Maintenance Exterior', 'target' => ''],
            ['name' => 'Maintenance Grounds', 'target' => ''],
            ['name' => 'Maintenance Repairs', 'target' => ''],
            ['name' => 'Manager', 'target' => ''],
            ['name' => 'Pest Control', 'target' => ''],
            ['name' => 'Pool Maintenance', 'target' => ''],
            ['name' => 'Private Road', 'target' => ''],
            ['name' => 'Recreational Facilities', 'target' => ''],
            ['name' => 'Security', 'target' => ''],
            ['name' => 'Sewer', 'target' => ''],
            ['name' => 'Trash', 'target' => ''],
            ['name' => 'Water', 'target' => ''],
            ['name' => 'None', 'target' => ''],
            ['name' => 'Other', 'target' => '.otherFeeIncludeRes'],
        ];
      @endphp
      <div class="form-group ">
        <label class="fw-bold">Fee Includes:</label>
        <select class="grid-picker" name="fee_include[]" id="fee_include"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($fee_includes as $fee_include)
            <option value="{{ $fee_include['name'] }}"
              data-icon="<i class='fa-regular fa-circle-check'></i>"
              data-target="{{ $fee_include['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->fee_include) && in_array($fee_include['name'], json_decode($auction->get->fee_include) ?? []) ? 'selected' : ''}}>
              {{ $fee_include['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group otherFeeIncludeRes d-none">
          <label class="fw-bold">Fee Includes:</label>
          <input type="text" name="otherFeeInclude" value="{{isset($auction->get->otherFeeInclude) ? $auction->get->otherFeeInclude : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
        </div>
      </div>
      <div class="form-group">
        <label class="fw-bold">Amenities with Additional Fees:</label>
        <input type="text" name="amenities_with_additional_fees" value="{{isset($auction->get->amenities_with_additional_fees) ? $auction->get->amenities_with_additional_fees : ''}}"
          class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
    </div>
        <div class="form-group">
            @php
              $hoaFeeRequirements = [
                ['name'=>'None','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>''],
                ['name'=>'Optional','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>''],
                ['name'=>'Required','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'']
              ];
            @endphp
          <label class="fw-bold">Hoa Fee Requirement:</label>
            <select name="hoaFeeRequirements" class="grid-picker" style="justify-content: flex-start;">
              <option value="">Select</option>
              @foreach ($hoaFeeRequirements as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->hoaFeeRequirements) && $auction->get->hoaFeeRequirements == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
              @endforeach
            </select>
        </div>

        <div class="form-group">
          <label class="fw-bold">HOA Fee:</label>
          <input type="number" name="hoaFeeAmount" value="{{isset($auction->get->hoaFeeAmount) ? $auction->get->hoaFeeAmount : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" >
        </div>

        <div class="form-group">
          @php
            $paymentSchedules = [
                    ['name'=>'Annually','target'=>''],
                    ['name'=>'Monthly','target'=>''],
                    ['name'=>'Quarterly','target'=>''],
                    ['name'=>'Semi-Annually','target'=>'']
                  ];
          @endphp
          <label class="fw-bold">HOA Payment Schedule:</label>
          <select name="paymentSchedules" id="hoaPaymentSchedule" class="grid-picker">
            <option value="">Select</option>
            @foreach ($paymentSchedules as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->paymentSchedules) && $auction->get->paymentSchedules == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group">
          <label class="fw-bold">Condo Fee:</label>
          <input type="number" name="condoFeeAmount" value="{{isset($auction->get->condoFeeAmount) ? $auction->get->condoFeeAmount : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" >
        </div>

        <div class="form-group">
          @php
            $condoPayOpt = [
                    ['name'=>'Annually','target'=>''],
                    ['name'=>'Monthly','target'=>''],
                    ['name'=>'Quarterly','target'=>''],
                    ['name'=>'Semi-Annually','target'=>'']
                  ];
          @endphp
          <label class="fw-bold">Condo Payment Schedule:</label>
          <select name="condoPay" id="condoPaymentSchedule" class="grid-picker">
            <option value="">Select</option>
            @foreach ($condoPayOpt as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->condoPay) && $auction->get->condoPay == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group">
            @php
              $masterAssocOpt = [
                ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.masterAssocYesRes'],
                ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                    ];
            @endphp
          <label class="fw-bold">Master Association:</label>
          <select name="masterAssoc" id="masterAssociation" class="grid-picker">
            <option value="">Select</option>
            @foreach ($masterAssocOpt as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->masterAssoc) && $auction->get->masterAssoc == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group masterAssocYesRes d-none">
          <div class="form-group">
            <label class="fw-bold">Master Association Fee:</label>
            <input type="number" name="masterAssociationFeeAmount" value="{{isset($auction->get->masterAssociationFeeAmount) ? $auction->get->masterAssociationFeeAmount : ''}}" id="masterAssociationFeeAmount" class="form-control has-icon " data-icon="fa-solid fa-dollar-sign" >
          </div>

          <div class="form-group">
            @php
                $assocScheduleOpt = [
                        ['name'=>'Annually','target'=>''],
                        ['name'=>'Monthly','target'=>''],
                        ['name'=>'Quarterly','target'=>''],
                        ['name'=>'Semi-Annually','target'=>'']
                      ];
              @endphp
            <label class="fw-bold">Master Association Fee Schedule:</label>
            <select name="assocSchedule" id="masterAssociationFeeSchedule" class="grid-picker">
              <option value="">Select</option>
              @foreach ($assocScheduleOpt as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                  style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->assocSchedule) && $auction->get->assocSchedule == $item['name'] ? 'selected' : ''}}>
                  {{ $item['name'] }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label class="fw-bold">Master Association Name:</label>
            <input type="text" name="masterAssociationName" value="{{isset($auction->get->masterAssociationName) ? $auction->get->masterAssociationName : ''}}" id="masterAssociationName" class="form-control has-icon" data-icon="fa-solid fa-user">
          </div>

          <div class="form-group">
            <label class="fw-bold">Master Association Contact Phone:</label>
            <input type="text" name="masterAssociationContactPhone" value="{{isset($auction->get->masterAssociationContactPhone) ? $auction->get->masterAssociationContactPhone : ''}}" class="form-control has-icon" data-icon="fa-solid fa-phone">
          </div>
        </div>

        <div class="form-group">
          @php
            $additioalFeeOpt = [
              ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.additionalFeeYesRes'],
              ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                  ];
          @endphp
          <label class="fw-bold">Are there any additional fees?</label>
          <select name="additionalFees" id="additionalFees" class="grid-picker">
            <option value="">Select</option>
            @foreach ($additioalFeeOpt as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}' {{isset($auction->get->additionalFees) && $auction->get->additionalFees == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="form-group additionalFeeYesRes d-none">
          <div class="form-group">
            <label class="fw-bold">What is the fee for?</label>
            <input type="text" name="additionalFeeReason" value="{{isset($auction->get->additionalFeeReason) ? $auction->get->additionalFeeReason : ''}}" id="additionalFeeReason" class="form-control has-icon" data-icon="">
          </div>
          <div class="form-group">
            <label class="fw-bold">Other Fee:</label>
            <input type="text" name="otherFeeAmount" value="{{isset($auction->get->otherFeeAmount) ? $auction->get->otherFeeAmount : ''}}" id="otherFeeAmount" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
          </div>
        </div>
          <div class="form-group">
            @php
              $otherFeeOpt = [
                      ['name'=>'Annually','target'=>''],
                      ['name'=>'Monthly','target'=>''],
                      ['name'=>'Quarterly','target'=>''],
                      ['name'=>'Semi-Annually','target'=>'']
                    ];
            @endphp
            <label class="fw-bold">Other Fee Schedule:</label>
            <select name="otherFee" id="otherFeeSchedule" class="grid-picker">
              <option value="">Select</option>
              @foreach ($otherFeeOpt as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                  style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->otherFee) && $auction->get->otherFee == $item['name'] ? 'selected' : ''}}>
                  {{ $item['name'] }}
                </option>
              @endforeach
            </select>
          </div>

        <div class="form-group">
          <label class="fw-bold">Association/Manager Contact Name:</label>
          <input type="text" name="associationManagerContactName" value="{{isset($auction->get->associationManagerContactName) ? $auction->get->associationManagerContactName : ''}}" class="form-control has-icon" data-icon="fa-solid fa-user">
        </div>

        <div class="form-group">
          <label class="fw-bold">Association/Manager Contact Email:</label>
          <input type="email" name="associationManagerContactEmail" value="{{isset($auction->get->associationManagerContactEmail) ? $auction->get->associationManagerContactEmail : ''}}" class="form-control has-icon" data-icon="fa-solid fa-envelope">
        </div>

        <div class="form-group">
          <label class="fw-bold">Association/Manager Contact Phone:</label>
          <input type="text" name="associationManagerContactPhone" value="{{isset($auction->get->associationManagerContactPhone) ? $auction->get->associationManagerContactPhone : ''}}" class="form-control has-icon" data-icon="fa-solid fa-phone">
        </div>

        <div class="form-group">
          <label class="fw-bold">Association/Manager Contact Website Address:</label>
          <input type="text" name="associationManagerContactWebsite" value="{{isset($auction->get->associationManagerContactWebsite) ? $auction->get->associationManagerContactWebsite : ''}}" class="form-control has-icon" data-icon="fa-regular fa-window-restore">
        </div>

        <div class="form-group">
          @php
            $olderPersonOpt = [
              ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>''],
              ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                  ];
          @endphp
          <label class="fw-bold">Housing for Older Persons:</label>
          <select name="olderPersons" id="housingForOlderPersons" class="grid-picker">
            <option value="">Select</option>
            @foreach ($olderPersonOpt as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->olderPersons) && $auction->get->olderPersons == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>
  </div>
  <div class="wizard-step" data-step="37">
    <h4>Ownership, Leasing Restrictions and Pets Information:</h4>
    @php
      $ownerships = [
        ['name' => 'Co-Op', 'target' => ''], 
        ['name' => 'Condominium', 'target' => ''], 
        ['name' => 'Fee Simple', 'target' => ''],
        ['name' => 'Fractional', 'target' => ''],
        ['name' => 'Leasehold', 'target' => ''] ,
        ['name' => 'Other', 'target' => '']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Ownership:</label>
      <select class="grid-picker" name="ownership" id="ownership" style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($ownerships as $item)
          @php
            if ($item['name'] == 'Other') {
                $target = '.otherOwnershipRes';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->ownership) && $auction->get->ownership == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group otherOwnershipRes d-none">
      <label class="fw-bold">Ownership:</label>
      <input type="text" name="otherOwnership" value="{{isset($auction->get->otherOwnership) ? $auction->get->otherOwnership : ''}}" id="custom_ownership" class="form-control has-icon"
        data-icon="fa-regular fa-circle-check">
    </div>
    @php
    $occupant_types = [['name' => 'Owner', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tenant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Occupant Type:</label>
      <select class="grid-picker" name="occupant_type" id="occupant_type"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($occupant_types as $item)
          @php
            if ($item['name'] == 'Tenant') {
                $target = '.tenant_conditions_residential_and_income';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->occupant_type) && $auction->get->occupant_type == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="row tenant_conditions_residential_and_income">
      <div class="row for_residential_only">
        <div class="form-group">
          @php
          $existingLease = [['name' => 'Yes, Existing Lease', 'target' => '.existingLeaseyes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Yes, Month to Month', 'target' => '.monthToMonth', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
          @endphp
          <label class="fw-bold">Existing Lease or Tenant:</label>
          <select class="grid-picker" name="exiting_lease_or_tenant" id="exiting_lease_or_tenant"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($existingLease as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                class="card flex-row" style="width:calc(33.3% - 10px);"
                data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->exiting_lease_or_tenant) && $auction->get->exiting_lease_or_tenant == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
          <div class="form-group existingLeaseyes d-none">
            <label for="address" class="fw-bold">End Date of Lease:</label>
            <input type="date" name="end_of_lease_date" value="{{isset($auction->get->end_of_lease_date) ? $auction->get->end_of_lease_date : ''}}" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
          </div>
          <div class="form-group monthToMonth d-none">
            <label for="address" class="fw-bold">What is the required notice period for the tenant to vacate the property?</label>
            <input type="text" name="monthToMonth" value="{{isset($auction->get->monthToMonth) ? $auction->get->monthToMonth : ''}}" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
          </div>
        </div>
        <div class="form-group">
          <label class="fw-bold">Monthly Rental Amount:</label>
          <input type="number" name="monthly_rental_ammount" value="{{isset($auction->get->monthly_rental_ammount) ? $auction->get->monthly_rental_ammount : ''}}" id="monthly_rental_ammount" 
            class="form-control has-icon" data-icon="fa-solid fa-dollar">
        </div>
        <div class="form-group">
          <label class="fw-bold">Days Notice to Tenant if not Renewing:</label>
          <input type="text" name="days_notice_to_terminate" value="{{isset($auction->get->days_notice_to_terminate) ? $auction->get->days_notice_to_terminate : ''}}" id="days_notice_to_terminate"
            class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
        </div>
      </div>
    </div>
    <div class="form-group">
      <label class="fw-bold">Can the property be leased?  </label>
      <select class="grid-picker" name="has_leasing" id="has_leasing"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.lease_restriction';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_leasing) && $auction->get->has_leasing == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="row lease_restriction">
      <div class="form-group ">
        <label class="fw-bold">Lease Restrictions:</label>
        <select class="grid-picker" name="has_lease_restriction" id="has_lease_restriction"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($yes_or_nos as $item)
            @php
              if ($item['name'] == 'Yes') {
                  $target = '';
              } else {
                  $target = '';
              }
            @endphp
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_lease_restriction) && $auction->get->has_lease_restriction == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="fw-bold">Association Approval Required:</label>
        <select class="grid-picker" name="association_approval_required" id="association_approval_required"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($yes_or_nos as $item)
            @php
              if ($item['name'] == 'Yes') {
                  $target = '';
              } else {
                  $target = '';
              }
            @endphp
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->association_approval_required) && $auction->get->association_approval_required == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      @php
        $minimum_lease_period = [['name' => '1-7 Days', 'target' => ''], ['name' => '1 Week', 'target' => ''], ['name' => '2 Week', 'target' => ''], ['name' => '1 Month', 'target' => ''], ['name' => '2 Month', 'target' => ''], ['name' => '3 Month', 'target' => ''], ['name' => '4 Month', 'target' => ''], ['name' => '5 Month', 'target' => ''], ['name' => '6 Month', 'target' => ''], ['name' => '7 Month', 'target' => ''], ['name' => '8-12 Month', 'target' => ''], ['name' => '1-2 Years', 'target' => ''], ['name' => '2+ Years', 'target' => ''], ['name' => 'No Minimum', 'target' => ''], ['name' => 'No Rent', 'target' => '']];
      @endphp
      <div class="form-group ">
        <label class="fw-bold">Minimum Lease Period:</label>
        <select class="grid-picker" name="minimum_lease_period" id="minimum_lease_period"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($minimum_lease_period as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
              data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->minimum_lease_period) && $auction->get->minimum_lease_period == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="fw-bold">Maximum Lease Times Per Year:</label>
        <input type="text" name="minimum_lease_per_year" value="{{isset($auction->get->minimum_lease_per_year) ? $auction->get->minimum_lease_per_year : ''}}"
          class="form-control has-icon" data-icon="fa-solid fa-calendar-days">
      </div>
      <div class="form-group">
        <label class="fw-bold">Years of Ownership Prior to Leasing Required:</label>
        <select class="grid-picker" name="years_of_ownership" id="years_of_ownership"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($yes_or_nos as $item)
            @php
              if ($item['name'] == 'Yes') {
                  $target = '';
              } else {
                  $target = '';
              }
            @endphp
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->years_of_ownership) && $auction->get->years_of_ownership == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="fw-bold">Number of Ownership Years Prior to Leasing:</label>
        <input type="text" name="number_of_ownership_prior_lease" value="{{isset($auction->get->number_of_ownership_prior_lease) ? $auction->get->number_of_ownership_prior_lease : ''}}" class="form-control has-icon"
          data-icon="fa-regular fa-calendar-days">
      </div>
    </div>
    <div class="form-group">
      <label class="fw-bold">Pets Allowed:</label>
      <select class="grid-picker" name="ptes_Allowed" id="has_rental_restrictions"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.pets_allowed_question12';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->ptes_Allowed) && $auction->get->ptes_Allowed == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="row ">
        @php
          $total_pets_allowed = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_pets_allowed', 'name' => 'Other']];
        @endphp
        <div class="form-group pets_allowed_question12 d-none">
          <div class="form-group">
            <label class="fw-bold">Acceptable Pet Types:</label>
            <input type="text" name="acceptablePet" value="{{isset($auction->get->acceptablePet) ? $auction->get->acceptablePet : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dog">
          </div>
          <div class="form-group">
            <label class="fw-bold">Number of Pets Allowed:</label>
            <select class="grid-picker" name="total_pets_allowed" id="total_pets_allowed"
              style="justify-content: flex-start;">
              <option value="">Select</option>
              @foreach ($total_pets_allowed as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                  class="card flex-column" style="width:calc(10% - 10px);"
                  data-icon='<i class="fa-solid fa-dog"></i>' {{isset($auction->get->total_pets_allowed) && $auction->get->total_pets_allowed == $item['name'] ? 'selected' : ''}}>
                  {{ $item['name'] }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="form-group custom_pets_allowed d-none">
            <label class="fw-bold">Number of Pets Allowed:</label>
            <input type="text" name="custom_pets_allowed" value="{{isset($auction->get->custom_pets_allowed) ? $auction->get->custom_pets_allowed : ''}}" id="custom_pets_allowed"
              class="form-control has-icon" data-icon="fa-solid fa-dog">
          </div>
          <div class="form-group">
            <label class="fw-bold">Max Pet Weight:</label>
            <input type="text" name="max_pet_weight" value="{{isset($auction->get->max_pet_weight) ? $auction->get->max_pet_weight : ''}}" id="max_pet_weight" class="form-control has-icon"
              data-icon="fa-solid fa-dog">
          </div>
          <div class="form-group">
            <label class="fw-bold">Pet Restrictions:</label>
            <textarea name="pet_restrictions" id="pet_restrictions" class="form-control" cols="30" rows="5">{{isset($auction->get->pet_restrictions) ? $auction->get->pet_restrictions : ''}}</textarea>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="38">
    <h4>Green Features:</h4>
    @php
      $greenOpt = [
        ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.green-field-opts'],
        ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Does the property have any Green Features?</label>
      <select class="grid-picker" name="green_features" id="green_features" style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($greenOpt as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon="{{$item['icon']}}" {{isset($auction->get->green_features) && $auction->get->green_features == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group d-none green-field-opts" >
      @php
      $buildingVerificationOpt = [
        ['name'=>'Certified Passive House','target'=>''],
        ['name'=>'EarthCraft House','target'=>''],
        ['name'=>'ENERGY STAR Certified Homes','target'=>''],
        ['name'=>'EnerPHit','target'=>''],
        ['name'=>'EPA Indoor Air Quality Plus','target'=>''],
        ['name'=>'FGBC Green Certified Building','target'=>''],
        ['name'=>'FGBC Green Certified Home','target'=>''],
        ['name'=>'FGBC Remodel','target'=>''],
        ['name'=>'Florida Certified Yard','target'=>''],
        ['name'=>'Florida Friendly Landscape','target'=>''],
        ['name'=>'Florida Friendly Yard Recognition','target'=>''],
        ['name'=>'Florida Green Lodging','target'=>''],
        ['name'=>'Florida Water Star','target'=>''],
        ['name'=>'FORTIFIED for Safer Living','target'=>''],
        ['name'=>'Geothermal HVAC','target'=>''],
        ['name'=>'HERS Index Score','target'=>''],
        ['name'=>'Home Energy Score','target'=>''],
        ['name'=>'Home Energy Upgrade Certificate of Energy Efficiency Improvements','target'=>''],
        ['name'=>'Home Energy Upgrade Certificate of Energy Efficiency Performance','target'=>''],
        ['name'=>'Home Performance with ENERGY STAR','target'=>''],
        ['name'=>'LEED Certified Building','target'=>''],
        ['name'=>'LEED for Homes','target'=>''],
        ['name'=>'LEED Neighborhood development','target'=>''],
        ['name'=>'Living Building Challenge','target'=>''],
        ['name'=>'NAHB Certification','target'=>''],
        ['name'=>'NGBS New Construction','target'=>''],
        ['name'=>'NGBS Small Projects Remodel','target'=>''],
        ['name'=>'NGBS Whole-Home Remodel','target'=>''],
        ['name'=>'Pearl Certification','target'=>''],
        ['name'=>'PHIUS+','target'=>''],
        ['name'=>'WaterSense','target'=>''],
        ['name'=>'Zero Energy Ready Home','target'=>''],
        ['name'=>'Other','target'=>'.buildingVerOther']
      ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Building Verification:</label>
        <select class="grid-picker" name="building_verification" id="building_verification" style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($buildingVerificationOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->building_verification) && $auction->get->building_verification == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group buildingVerOther d-none">
        <label class="fw-bold">Building Verification:</label>
        <input type="text" name="building_verification_other" value="{{isset($auction->get->building_verification_other) ? $auction->get->building_verification_other : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check"/>
      </div>
      @php
      $statusOpt = [
        ['name'=>'Complete','target'=>''],
        ['name'=>'In Progress','target'=>'']
      ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Status:</label>
        <select class="grid-picker" name="green_status" id="green_status" style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($statusOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->green_status) && $auction->get->green_status == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="fw-bold">Year:</label>
        <input type="text" name="green_year" value="{{isset($auction->get->green_year) ? $auction->get->green_year : ''}}" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
      </div>
      <div class="form-group">
        <label class="fw-bold">Version:</label>
        <input type="text" name="green_version" value="{{isset($auction->get->green_version) ? $auction->get->green_version : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
      <div class="form-group">
        <label class="fw-bold">Body:</label>
        <input type="text" name="green_body" value="{{isset($auction->get->green_body) ? $auction->get->green_body : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
      <div class="form-group">
        <label class="fw-bold">Metric:</label>
        <input type="text" name="green_metric" value="{{isset($auction->get->green_metric) ? $auction->get->green_metric : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
      <div class="form-group">
        <label class="fw-bold">Rating:</label>
        <input type="text" name="green_rating" value="{{isset($auction->get->green_rating) ? $auction->get->green_rating : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
      @php
      $sourceOpt = [
        ['name'=>'Administrator','target'=>''],
        ['name'=>'Assessor','target'=>''],
        ['name'=>'Builder','target'=>''],
        ['name'=>'Contractor or Installer','target'=>''],
        ['name'=>'Owner','target'=>''],
        ['name'=>'Program Sponser','target'=>''],
        ['name'=>'Program Verifier','target'=>''],
        ['name'=>'Public Records','target'=>''],
        ['name'=>'Other','target'=>'.sourceOther'],
      ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Source:</label>
        <select class="grid-picker" name="green_source" id="green_source" style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($sourceOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->green_source) && $auction->get->green_source == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group sourceOther d-none">
          <label class="fw-bold">Source:</label>
          <input type="text" name="green_source_other" value="{{isset($auction->get->green_source_other) ? $auction->get->green_source_other : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
        </div>
      </div>
      <div class="form-group">
        <label class="fw-bold">Green Verification URL:</label>
        <input type="text" name="green_url" value="{{isset($auction->get->green_url) ? $auction->get->green_url : ''}}" class="form-control has-icon" data-icon="fa-solid fa-link">
      </div>
      @php
      $sustainabilityOpt = [
        ['name'=>'Conserving Methods','target'=>''],
        ['name'=>'Onsite Recycling Center','target'=>''],
        ['name'=>'Recyclable Materials','target'=>''],
        ['name'=>'Recycled Materials','target'=>''],
        ['name'=>'Regionally-Sourced Materials','target'=>''],
        ['name'=>'Renewable Materials','target'=>''],
        ['name'=>'Salvaged Materials','target'=>'']
      ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Green Sustainability:</label>
        <select class="grid-picker" name="green_sustainability[]" id="green_sustainability" style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($sustainabilityOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->green_sustainability) && in_array($item['name'], json_decode($auction->get->green_sustainability) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      @php
      $generationOpt = [
        ['name'=>'Hydro Power','target'=>''],
        ['name'=>'Solar','target'=>''],
        ['name'=>'Wind','target'=>'']
      ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Green Energy Generation:</label>
        <select class="grid-picker" name="green_generation[]" id="green_generation" style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($generationOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->green_generation) && in_array($item['name'], json_decode($auction->get->green_generation) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      @php
      $waterOpt = [
        ['name'=>'Drip Irrigation','target'=>''],
        ['name'=>'Efficient Hot Water Distribution','target'=>''],
        ['name'=>'Gray Water System','target'=>''],
        ['name'=>'Green Infrastructure','target'=>''],
        ['name'=>'Irrig. System-Drip/Microheads','target'=>''],
        ['name'=>'Irrig. System-Rainwater from Ponds','target'=>''],
        ['name'=>'Irrigation-Reclaimed Water','target'=>''],
        ['name'=>'Low-Flow Fixtures','target'=>''],
        ['name'=>'Water Recycling','target'=>''],
        ['name'=>'Water Smart Landscaping','target'=>''],
        ['name'=>'Whole House Water Purification','target'=>'']
      ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Green Water Features:</label>
        <select class="grid-picker" name="green_water_features[]" id="green_water" style="justify-content: flex-start;" multiple multiple>
          <option value="">Select</option>
          @foreach ($waterOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->green_water_features) && in_array($item['name'], json_decode($auction->get->green_water_features) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      @php
      $energyOpt = [
        ['name'=>'Appliances','target'=>''],
        ['name'=>'Construction','target'=>''],
        ['name'=>'Doors','target'=>''],
        ['name'=>'Energy Monitoring System','target'=>''],
        ['name'=>'Exposure/Shade','target'=>''],
        ['name'=>'HVAC','target'=>''],
        ['name'=>'Incentives','target'=>''],
        ['name'=>'Insulation','target'=>''],
        ['name'=>'Lighting','target'=>''],
        ['name'=>'Pool','target'=>''],
        ['name'=>'Roof','target'=>''],
        ['name'=>'Thermostat','target'=>''],
        ['name'=>'Water Heater','target'=>''],
        ['name'=>'Windows','target'=>'']
    ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Green Energy Features:</label>
        <select class="grid-picker" name="green_energy_features[]" id="green_energy" style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($energyOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->green_energy_features) && in_array($item['name'], json_decode($auction->get->green_energy_features) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      @php
      $landscapingOpt = [
        ['name'=>'Fl. Friendly/Native Landscape','target'=>''],
        ['name'=>'Non-Toxic Fertilizer/Pesticides','target'=>''],
        ['name'=>'Rain Water Harvesting','target'=>''],
        ['name'=>'Veg. (Productive) Garden','target'=>''],
        ['name'=>'Xeriscape','target'=>'']
      ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Green Landscaping:</label>
        <select class="grid-picker" name="green_landscaping[]" id="green_landscaping" style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($landscapingOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->green_landscaping) && in_array($item['name'], json_decode($auction->get->green_landscaping) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      @php
      $solarOpt = [
        ['name'=>'Owned','target'=>''],
        ['name'=>'Leased/Assumable','target'=>''],
        ['name'=>'Leased/Non-Assumable','target'=>'']
      ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Solar Panel Ownership:</label>
        <select class="grid-picker" name="green_solar[]" id="green_solar" style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($solarOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->green_solar) && in_array($item['name'], json_decode($auction->get->green_solar) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      @php
      $disasterOpt = [
        ['name'=>'Above Flood Plain','target'=>''],
        ['name'=>'Fire Resistant Exterior','target'=>''],
        ['name'=>'Fire/Smoke Detection Integration','target'=>''],
        ['name'=>'Hurricane Insur. Deduction Qual.','target'=>''],
        ['name'=>'Hurricane Shutters/Windows','target'=>''],
        ['name'=>'Lightning Protection System','target'=>''],
        ['name'=>'Safe Room','target'=>'']
      ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Disaster Mitigation:</label>
        <select class="grid-picker" name="green_disaster[]" id="green_disaster" style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($disasterOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->green_disaster) && in_array($item['name'], json_decode($auction->get->green_disaster) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      @php
      $airOpt = [
        ['name'=>'Air Filters MERV 10+','target'=>''],
        ['name'=>'Containment Control','target'=>''],
        ['name'=>'HVAC Cartridge/Media Filter','target'=>''],
        ['name'=>'HVAC Filter MERV 8+','target'=>''],
        ['name'=>'HVAC UV/Elec. Filtration','target'=>''],
        ['name'=>'Integrated Pest Management','target'=>''],
        ['name'=>'Janitor Closet Neg Pressurized','target'=>''],
        ['name'=>'Moisture Control','target'=>''],
        ['name'=>'No Smoking-Interior Buildg','target'=>''],
        ['name'=>'No/Low VOC Cabinets/Counters','target'=>''],
        ['name'=>'No/Low VOC Flooring','target'=>''],
        ['name'=>'No/Low VOC Paint/Finish','target'=>''],
        ['name'=>'Non-Toxic Pest Control','target'=>''],
        ['name'=>'Sealed Combustion','target'=>''],
        ['name'=>'Ventilation','target'=>''],
        ['name'=>'Whole House Vacuum System','target'=>'']
      ];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Indoor Air Quality:</label>
        <select class="grid-picker" name="green_air[]" id="green_air" style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($airOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->green_air) && in_array($item['name'], json_decode($auction->get->green_air) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="39">
    <div class="form-group">
      <label class="fw-bold"> Description:</label>
      <textarea name="description" value="{{isset($auction->get->description) ? $auction->get->description : ''}}" id="description" class="form-control" cols="30" rows="10" required></textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">Legal Disclaimers:</label>
      <textarea name="disclamer" value="{{isset($auction->get->disclamer) ? $auction->get->disclamer : ''}}" id="keywords" class="form-control" cols="30" rows="10" ></textarea>  
    </div>
    <div class="form-group">
      <label class="fw-bold">Driving Directions:</label>
      <textarea name="driving_directions" value="{{isset($auction->get->driving_directions) ? $auction->get->driving_directions : ''}}" id="keywords" class="form-control" cols="30" rows="10" ></textarea>    
    </div>
    <div class="form-group">
          @php
            $sellerCompRes = [['name' => 'Yes', 'target' => '','target'=>'.sellerComYesRes','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],['name' => 'Negotiable', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-check"></i>']];
          @endphp
        <label class="fw-bold">Is the seller offering compensation for a buyer’s agent?
        </label>
        <select class="grid-picker" name="looking_other_property" id="looking_other_property"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($sellerCompRes as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->looking_other_property) && $auction->get->looking_other_property == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group sellerComYesRes d-none">
          <div class="d-flex justify-content-between aalign-items-center">
            <label class="fw-bold">Buyer’s Agent Compensation:</label>
            <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                <button type="button" class="select-btn me-1 active"
                    data-type="amount">$</button>
                <button type="button" class="select-btn" data-type="percent">%</button>
            </div>
          </div>
          <input type="text" name="compensation_amount" value="{{isset($auction->get->compensation_amount) ? $auction->get->compensation_amount : ''}}" class="form-control has-icon"
            data-icon="fa-solid fa-percent">
        </div>
      </div>
  </div>
  <div class="wizard-step" data-step="40">
    <div class="form-group">
      <label class="fw-bold">Is the Seller actively seeking to purchase another property?
      </label>
      <select class="grid-picker" name="looking_other_property" id="looking_other_property"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.link_residential_and_income';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(50% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>" {{isset($auction->get->looking_other_property) && $auction->get->looking_other_property == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group link_residential_and_income">
      <label class="fw-bold">Link to the listing on the Bid Your Offer platform:</label>
      <input type="text" name="listing_link"  value="{{isset($auction->get->listing_link) ? $auction->get->listing_link : ''}}" id="listing_link" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-link">
    </div>
  </div>
  <div class="wizard-step" data-step="41">
    <h4> Title Company Information:</h4>
    <div class="form-group">
      <label class="fw-bold">Name:</label>
      <input type="text" name="title_company_name" value="{{isset($auction->get->title_company_name) ? $auction->get->title_company_name : ''}}" id="title_company_name" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-user">
    </div>
    <div class="form-group">
      <label class="fw-bold">Address:</label>
      <input type="text" name="title_company_address" value="{{isset($auction->get->title_company_address) ? $auction->get->title_company_address : ''}}" id="title_company_address" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-location-dot">
    </div>
    <div class="form-group">
      <label class="fw-bold">Phone Number:</label>
      <input type="text" name="title_company_phone" value="{{isset($auction->get->title_company_phone) ? $auction->get->title_company_phone : ''}}" id="title_company_phone" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-phone">
    </div>

    <div class="form-group">
      <label class="fw-bold">Email:</label>
      <input type="text" name="title_company_email" value="{{isset($auction->get->title_company_email) ? $auction->get->title_company_email : ''}}" id="titl_company_email" placeholder=""
      data-icon="fa-solid fa-envelope" class="form-control has-icon">
    </div>

  </div>
  <div class="wizard-step" data-step="42">
    @if (auth()->user()->user_type == 'agent')
      <h4>Listing Agent Information:</h4>
    @else
      <h4>Seller’s Information:</h4>
    @endif
    
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">First Name:</label>
        <input type="text" name="agent_first_name" id="first_name" placeholder=""
        value="{{isset($auction->get->agent_first_name) ? $auction->get->agent_first_name : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-user" required>
      </div>
      <div class="form-group col-md-6">
        <label class="fw-bold">Last Name:</label>
        <input type="text" name="agent_last_name" id="last_name" placeholder=""
          value="{{isset($auction->get->agent_last_name) ? $auction->get->agent_last_name : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-user" required>
      </div>
    </div>
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">Phone Number:</label>
        <input type="text" name="agent_phone" id="agent_phone" placeholder=""
          value="{{isset($auction->get->agent_phone) ? $auction->get->agent_phone : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-phone" required>
      </div>
      <div class="form-group col-md-6 ">
        <label class="fw-bold">Email:</label>
        <input type="text" name="agent_email" id="agent_email" placeholder=""
          class="form-control has-icon" data-icon="fa-solid fa-envelope"
          value="{{isset($auction->get->agent_email) ? $auction->get->agent_email : ''}}" required>
      </div>
    </div>
    @if (auth()->user()->user_type == 'agent')
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">Brokerage:</label>
        <input type="text" name="agent_brokerage" id="agent_brokerage" placeholder=""
          value="{{isset($auction->get->agent_brokerage) ? $auction->get->agent_brokerage : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-handshake" required>
      </div>
      <div class="form-group col-md-6">
        <label class="fw-bold">Real Estate License #:</label>
        <input type="text" name="agent_license_no" id="agent_license_no" placeholder=""
          value="{{isset($auction->get->agent_license_no) ? $auction->get->agent_license_no : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-id-card" required>
      </div>
    </div>
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">NAR Member ID (NRDS ID):</label>
        <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
          class="form-control has-icon" data-icon="fa-solid fa-id-card-clip"
          value="{{isset($auction->get->agent_mls_id) ? $auction->get->agent_mls_id : ''}}" required>
      </div>
    </div>
    @endif
  </div>
  <div class="wizard-step" data-step="43">
    <div class="form-group">
      <label class="fw-bold">3D Tour (Link):</label>
      <input type="text" name="three_d_tour" value="{{isset($auction->get->three_d_tour) ? $auction->get->three_d_tour : ''}}" id="three_d_tour" placeholder=""
      class="form-control has-icon" data-icon="fa-solid fa-link">
    </div>
    <div class="form-group">
      <label class="fw-bold">Floor Plan:</label>
      <input type="file" name="floor_plan[]" id="floor_plan_1" class="form-control" accept="image/*" >
    </div>
    <div class="form-group">
      <label class="fw-bold">Addendums/Disclosures:</label>
      <input type="file" name="disclosures[]" id="upload_file" placeholder="" class="form-control documents-input" multiple>
    </div>
    <span class="resFields">
      <div class="row">
        <div class="col-6 video_div">
          <input type="hidden" name="video_type" class="video_type" value="video_upload">
          <div class="video_type_select d-flex align-items-center justify-content-left">
              <div class="form-check me-2">
                  <label class="form-check-label">
                      <input type="checkbox" class="video_upload form-check-input video_type_check" name="video_upload" >
                      Video Upload
                  </label>
              </div>
              <div class="form-check me-2">
                  <label class="form-check-label">
                      <input type="checkbox" class="youtube_video form-check-input video_type_check" name="youtube_video">
                      Youtube Video
                  </label>
              </div>
              <div class="form-check">
                  <label class="form-check-label">
                      <input type="checkbox" class="vimeo_video form-check-input video_type_check" name="vimeo_video">
                      Vimeo Video
                  </label>
              </div>
          </div>
          <div class="video-upload video-type-element">
              <label class="fw-bold mt-1">Property Video:</label>
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
              <label class="fw-bold mt-1">Youtube Video:</label>
              <input type="text" name="youtube_video_link" class="form-control" placeholder="Youtube video link i.e. https://youtube.com/embed/videoId">
          </div>
          <div class="vimeo-video video-type-element form-group d-none">
              <label class="fw-bold mt-1">Vimeo Video:</label>
              <input type="text" name="vimeo_video_link" class="form-control" placeholder="Vimeo video link i.e. https://player.vimeo.com/video/videoId">
          </div>
        </div>
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
      </div>
    </span>
  </div>
  {{-- residential/income end --}}