<div class="wizard-step" data-step="66">
    @php
      $air_conditioning = [['name' => 'A/C Office Only', 'target' => ''], ['name' => 'Central Air', 'target' => ''], ['name' => 'Humidity Control', 'target' => ''], ['name' => 'Mini-Split Unit(s)', 'target' => ''],  ['name' => 'Wall/Window Unit(s)', 'target' => ''], ['name' => 'Zoned', 'target' => ''],['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherAirConditionCom']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Air Conditioning:</label>
      <select class="grid-picker" name="air_conditioning_com[]" id="air_conditioning"
        style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($air_conditioning as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->air_conditioning_com) && in_array($item['name'], json_decode($auction->get->air_conditioning_com, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherAirConditionCom d-none">
        <label class="fw-bold">Air Conditioning:</label>
        <input type="text" name="otherAirConditionCom" value="{{isset($auction->get->otherAirConditionCom) ? $auction->get->otherAirConditionCom : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
    </div>
    @php
      $heating_and_fuel = [
        ['name' => 'Baseboard', 'target' => ''], 
        ['name' => 'Central', 'target' => ''], 
        ['name' => 'Central Building', 'target' => ''], 
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
        ['name' => 'Other', 'target' => '.otherHeatingFuelCom']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Heating and Fuel:</label>
      <select class="grid-picker" name="heating_and_fuel[]" id="heating_and_fuel"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($heating_and_fuel as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->heating_and_fuel) && in_array($item['name'], json_decode($auction->get->heating_and_fuel, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherHeatingFuelCom d-none">
        <label class="fw-bold">Heating and Fuel:</label>
        <input type="text" name="otherHeatingFuelCom" value="{{isset($auction->get->otherHeatingFuelCom) ? $auction->get->otherHeatingFuelCom : ''}}" class="form-control has-icon"
            data-icon="fa-regular fa-circle-check">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="67">
    <h4>Water and Dock Information:</h4>
    <div class="form-group ">
      <label class="fw-bold">Water View:</label>
      <select class="grid-picker" name="has_water_view_com" id="has_water_view"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_view_commercial_and_business';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_view_com) && $auction->get->has_water_view_com == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $water_views = [['name' => 'Bay/Harbor - Full', 'target' => ''], ['name' => 'Bay/Harbor - Partial', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Canal', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Gulf/Ocean - Full', 'target' => ''], ['name' => 'Gulf/Ocean - Partial', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'None', 'target' => '']];
    @endphp
    <div class="form-group water_view_commercial_and_business d-none">
      <select class="grid-picker" name="water_view[]" id="water_view" style="justify-content: flex-start;"
        multiple>
        <option value="">Select</option>
        @foreach ($water_views as $water_view)
          <option value="{{ $water_view['name'] }}" data-icon="<i class='fa-regular fa-circle-check'></i>"
            data-target="{{ $water_view['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water_view) && in_array($water_view['name'], json_decode($auction->get->water_view, true) ?? []) ? 'selected' : ''}}>
            {{ $water_view['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Water Extras:</label>
      <select class="grid-picker" name="has_water_extra_com" id="has_water_extra"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_extras_commercial_and_business';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_extra_com) && $auction->get->has_water_extra_com == $item['name'] ? 'selected' : ''}}>
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
    <div class="form-group water_extras_commercial_and_business d-none ">
      <select class="grid-picker" name="water_extras[]" id="water_extras"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($water_extras as $water_extra)
          <option value="{{ $water_extra['name'] }}" data-target="{{ $water_extra['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water_extras) && in_array($water_extra['name'], json_decode($auction->get->water_extras, true) ?? []) ? 'selected' : ''}}>
            {{ $water_extra['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Water Frontage:</label>
      <select class="grid-picker" name="has_water_fontage_com" id="has_water_fontage"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_frontage_commercial_and_business';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_fontage_com) && $auction->get->has_water_fontage_com == $item['name'] ? 'selected' : ''}}>
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
    <div class="form-group water_frontage_commercial_and_business d-none">
      <select class="grid-picker" name="water_frontage[]" id="water_frontage"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($water_frontage as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water_frontage) && in_array($item['name'], json_decode($auction->get->water_frontage, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Water Access:</label>
      <select class="grid-picker" name="has_water_access_com" id="has_water_access"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_access_commercial_and_business';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_access_com) && $auction->get->has_water_access_com == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $water_access = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
    @endphp
    <div class="form-group water_access_commercial_and_business">
      <select class="grid-picker" name="water_access[]" id="water_access"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($water_access as $water_access1)
          <option value="{{ $water_access1['name'] }}" data-target="{{ $water_access1['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water_access) && in_array($water_access1['name'], json_decode($auction->get->water_access, true) ?? []) ? 'selected' : ''}}>
            {{ $water_access1['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Dock:</label>
      <select class="grid-picker" name="has_dock_com" id="has_dock"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.dock_commercial_business';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_dock_com) && $auction->get->has_dock_com == $item['name'] ? 'selected' : ''}}>
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
        ['name' => 'Other', 'target' => '.other-dock-com'],
    ];
    @endphp
    <div class="form-group dock_commercial_business d-none ">
      <label class="fw-bold">Dock Description:</label>
      <select class="grid-picker" name="dock[]" id="dock"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($dock as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" required {{isset($auction->get->dock) && in_array($item['name'], json_decode($auction->get->dock, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group other-dock-com d-none">
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
          class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
  <div class="wizard-step" data-step="68">
    <h4>Ownership and Occupant Type:</h4>
    @php
      $ownerships = [
        ['name' => 'Condominium', 'target' => ''], 
        ['name' => 'Corporation', 'target' => ''], 
        ['name' => 'Franchise', 'target' => ''], 
        ['name' => 'Leasehold', 'target' => ''], 
        ['name' => 'Partnership', 'target' => ''], 
        ['name' => 'Sole Proprietor', 'target' => ''], 
        ['name' => 'Other', 'target' => '']
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Ownership:</label>
      <select class="grid-picker" name="ownership_com" id="ownership"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($ownerships as $item)
          @php
            if ($item['name'] == 'Other') {
                $target = '.otherOwnershipCommercial';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->ownership_com) && $auction->get->ownership_com == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group otherOwnershipCommercial">
      <label class="fw-bold">Ownership:</label>
      <input type="text" name="otherOwnership" value="{{isset($auction->get->otherOwnership) ? $auction->get->otherOwnership : ''}}" id="custom_ownership" class="form-control has-icon"
        data-icon="fa-regular fa-circle-check" required>
    </div>
    @php
      $occupantCommercial = [['name' => 'Owner', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tenant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Occupant Type:</label>
      <select class="grid-picker" name="occupant_type_com" id="occupant_type"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($occupantCommercial as $item)
          @php
            if ($item['name'] == 'Tenant') {
                $target = '.occupantCommercial';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->occupant_type_com) && $auction->get->occupant_type_com == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="row occupantCommercial">
      <div class="row for_residential_only">
        <div class="form-group">
          @php
          $existingLease = [['name' => 'Yes, Existing Lease', 'target' => '.existingLeaseyesCommercial', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Yes, Month to Month', 'target' => '.monthToMonthCommercial', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
          @endphp
          <label class="fw-bold">Existing Lease or Tenant:</label>
          <select class="grid-picker" name="exiting_lease_or_tenant_com" id="exiting_lease_or_tenant"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($existingLease as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                class="card flex-row" style="width:calc(33.3% - 10px);"
                data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->exiting_lease_or_tenant_com) && $auction->get->exiting_lease_or_tenant_com == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
          <div class="form-group existingLeaseyesCommercial d-none">
            <label for="address" class="fw-bold">End Date of Lease:</label>
            <input type="date" name="end_of_lease_date_com" value="{{isset($auction->get->end_of_lease_date_com) ? $auction->get->end_of_lease_date_com : ''}}" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
          </div>
          <div class="form-group monthToMonthCommercial d-none">
            <label for="address" class="fw-bold">What is the required notice period for the tenant to vacate the property?</label>
            <input type="text" name="monthToMonth_com" value="{{isset($auction->get->monthToMonth_com) ? $auction->get->monthToMonth_com : ''}}" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
          </div>
        </div>
        <div class="form-group">
          <label class="fw-bold">Monthly Rental Amount:</label>
          <input type="number" name="monthly_rental_ammount_com" value="{{isset($auction->get->monthly_rental_ammount_com) ? $auction->get->monthly_rental_ammount_com : ''}}" id="monthly_rental_ammount" 
            class="form-control has-icon" data-icon="fa-solid fa-dollar">
        </div>
        <div class="form-group">
          <label class="fw-bold">Days Notice to Tenant if not Renewing:</label>
          <input type="text" name="days_notice_to_terminate_com" value="{{isset($auction->get->days_notice_to_terminate_com) ? $auction->get->days_notice_to_terminate_com : ''}}" id="days_notice_to_terminate"
             class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
        </div>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="69"></div>
  <div class="wizard-step" data-step="70">
    <h4>Financial Information:</h4>
    @php
      $acutual_or_projected = [['name' => 'Actual', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Projected', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Operating Expenses:</label>
      <input type="number" name="operating_expenses" value="{{isset($auction->get->operating_expenses) ? $auction->get->operating_expenses : ''}}" id="operating_expenses"
        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
    </div>
    <div class="form-group">
      <label class="fw-bold">Net Operating Income:</label>
      <input type="number" name="net_operating_income" value="{{isset($auction->get->net_operating_income) ? $auction->get->net_operating_income : ''}}" id="net_operating_income"
        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
    </div>
    <div class="form-group">
      <label class="fw-bold">Net Operating Income Type:</label>
      <select class="grid-picker" name="net_operating_income_type" id="net_operating_income_type"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($acutual_or_projected as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->net_operating_income_type) && $auction->get->net_operating_income_type == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Annual Expense:</label>
      <input type="number" name="annual_expenses" value="{{isset($auction->get->annual_expenses) ? $auction->get->annual_expenses : ''}}" id="annual_expenses" class="form-control has-icon"
        data-icon="fa-solid fa-dollar" >
    </div>
    <div class="form-group">
      <label class="fw-bold">Annual TTL Schedule Income:</label>
      <input type="number" name="annual_ttl_schedule_income" value="{{isset($auction->get->annual_ttl_schedule_income) ? $auction->get->annual_ttl_schedule_income : ''}}" id="annual_ttl_schedule_income"
        class="form-control has-icon" data-icon="fa-solid fa-dollar">
    </div>
    <div class="form-group">
      <label class="fw-bold">Annual Income Type:</label>
      <select class="grid-picker" name="annual_income_type" id="annual_income_type"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($acutual_or_projected as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->annual_income_type) && $auction->get->annual_income_type == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $saleIncludeCommercial = [['name' => 'Building(s) and Land', 'target' => ''], ['name' => 'Furniture/Fixtures', 'target' => ''], ['name' => 'Leases', 'target' => ''], ['name' => 'Other', 'target' => '.otherSaleCommercial']];
    @endphp
    <div class="row ">
      <div class="form-group">
        <label class="fw-bold">Sale Includes:</label>
        <select class="grid-picker" name="saleInclude[]" style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($saleIncludeCommercial as $item)
            <option value="{{ $item['name'] }}"
              data-icon='<i class="fa-regular fa-circle-check"></i>'
              data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->saleInclude) && in_array($item['name'], json_decode($auction->get->saleInclude, true) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group otherSaleCommercial d-none">
          <label class="fw-bold">Sale Includes:</label>
          <input type="text" name="otherSale" value="{{isset($auction->get->otherSale) ? $auction->get->otherSale : ''}}" class="form-control has-icon"
            data-icon="fa-regular fa-circle-check">
        </div>
      </div>
    </div>
    <div class="form-group">
      <label class="fw-bold">Number of Tenants:</label>
      <input type="number" name="number_of_tenants" value="{{isset($auction->get->number_of_tenants) ? $auction->get->number_of_tenants : ''}}" class="form-control has-icon"
        data-icon="fa-solid fa-person">
    </div>
  </div>
  <div class="wizard-step" data-step="71">
    <h4>Space:</h4>
    @php
      $space_type = [
        ['name' => 'New', 'target' => ''], 
        ['name' => 'Re Let', 'target' => ''], 
        ['name' => 'Sub Let', 'target' => '']
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Class of Space:</label>
      <input type="text" name="class_of_space" value="{{isset($auction->get->class_of_space) ? $auction->get->class_of_space : ''}}" id="operating_expenses" class="form-control has-icon"
        data-icon="fa-solid fa-hotel">
    </div>
    <div class="row ">
      <div class="form-group">
        <label class="fw-bold">Space Type:</label>
        <select class="grid-picker" name="sale_include" id="sale_include"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($space_type as $space)
            <option value="{{ $space['name'] }}" data-icon='<i class="fa-regular fa-circle-check"></i>'
              data-target="{{ $space['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->sale_include) && $auction->get->sale_include == $space['name'] ? 'selected' : ''}}>
              {{ $space['name'] }}
            </option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="fw-bold"> # of Hotel/Motel Rooms:</label>
      <input type="text" name="number_of_hotel" value="{{isset($auction->get->number_of_hotel) ? $auction->get->number_of_hotel : ''}}" id="number_of_hotel" class="form-control has-icon"
        data-icon="fa-solid fa-hotel">
    </div>
    <div class="form-group">
      <label class="fw-bold"># of Conference/Meeting Rooms:</label>
      <input type="text" name="number_of_conference" value="{{isset($auction->get->number_of_conference) ? $auction->get->number_of_conference : ''}}" id="annual_expenses" class="form-control has-icon"
        data-icon="fa-solid fa-hotel">
    </div>
    <div class="form-group">
      <label class="fw-bold"># of Restrooms:</label>
      <input type="text" name="number_of_restrooms" value="{{isset($auction->get->number_of_restrooms) ? $auction->get->number_of_restrooms : ''}}" id="annual_ttl_schedule_income"
        class="form-control has-icon" data-icon="fa-solid fa-restroom">
    </div>

    <div class="form-group">
      <label class="fw-bold"># of Bays(Dock High) :</label>
      <input type="text" name="number_of_bays_high" value="{{isset($auction->get->number_of_bays_high) ? $auction->get->number_of_bays_high : ''}}" id="number_of_tenants"
        class="form-control has-icon" data-icon="fa-solid fa-hotel">
    </div>
    <div class="form-group">
      <label class="fw-bold"># of Bays(Grade Level):</label>
      <input type="text" name="number_of_bays_level" value="{{isset($auction->get->number_of_bays_level) ? $auction->get->number_of_bays_level : ''}}" id="number_of_tenants"
        class="form-control has-icon" data-icon="fa-solid fa-hotel">
    </div>
    <div class="form-group">
      <label class="fw-bold"># of Offices:</label>
      <input type="text" name="number_of_offices" value="{{isset($auction->get->number_of_offices) ? $auction->get->number_of_offices : ''}}" id="number_of_tenants" class="form-control has-icon"
        data-icon="fa-solid fa-hotel">
    </div>
  </div>
  <div class="wizard-step" data-step="72">
    <h4>Condo Environment:</h4>
    <div class="form-group">
      <label class="fw-bold"> Is the property in a condo environment?</label>
      <select class="grid-picker" name="has_condo_enviornment" id="has_condo_enviornment"
        style="justify-content: flex-start;">
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
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_condo_enviornment) && $auction->get->has_condo_enviornment == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="row has_condo d-none">
      <div class="form-group ">
        <label class="fw-bold">Condo Fee:</label>
        <input type="number" name="condoFeeAmount_com" value="{{isset($auction->get->condoFeeAmount_com) ? $auction->get->condoFeeAmount_com : ''}}" id="condo_fee" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
      </div>
      @php
        $condo_fee_terms = [['target' => '', 'name' => 'Annual', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Monthly', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Quarterly', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Semi Annual ', 'icon' => 'fa-regular fa-circle-check']];
      @endphp
      <div class="form-group ">
        <label class="fw-bold">Condo Fee Term:</label>
        <select class="grid-picker" name="condo_fee_terms" id="parking_feature_garage"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($condo_fee_terms as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
              class="card flex-row" style="width:calc(33.3% - 10px);"
              data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->condo_fee_terms) && $auction->get->condo_fee_terms == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group ">
        <label class="fw-bold">Association/Manager Name:</label>
        <input type="text" name="association_manager_contact_name" value="{{isset($auction->get->association_manager_contact_name) ? $auction->get->association_manager_contact_name : ''}}" id="condo_fee"
          class="form-control has-icon" data-icon="fa-solid fa-user">
      </div>
      <div class="form-group ">
        <label class="fw-bold">Association/Manager Email:</label>
        <input type="text" name="association_manager_contact_email" value="{{isset($auction->get->association_manager_contact_email) ? $auction->get->association_manager_contact_email : ''}}" id="condo_fee"
          class="form-control has-icon" data-icon="fa-solid fa-envelope">
      </div>
      <div class="form-group ">
        <label class="fw-bold">Association/Manager Phone Number:</label>
        <input type="text" name="association_manager_contact_number" value="{{isset($auction->get->association_manager_contact_number) ? $auction->get->association_manager_contact_number : ''}}" id="condo_fee"
          class="form-control has-icon" data-icon="fa-solid fa-phone">
      </div>
      <div class="form-group ">
        <label class="fw-bold">Association/Manager Website: </label>
        <input type="text" name="association_manager_contact_website" value="{{isset($auction->get->association_manager_contact_website) ? $auction->get->association_manager_contact_website : ''}}" id="condo_fee"
          class="form-control has-icon" data-icon="fa-solid fa-link">
      </div>

      @php
        $community_features = [
          ['name' => 'Activity Core/Center', 'target' => ''], 
          ['name' => 'Airport/Runway', 'target' => ''], 
          ['name' => 'Beach Area', 'target' => ''], 
          ['name' => 'Curbs', 'target' => ''], 
          ['name' => 'Expressway', 'target' => ''], 
          ['name' => 'Sidewalk', 'target' => ''], 
          ['name' => 'Stream Seasonal', 'target' => ''],
          ['name' => 'Other', 'target' => '.community_features_other'],
        ];
      @endphp
      <div class="form-group ">
        <label class="fw-bold">Community Features:</label>
        <select class="grid-picker" name="community_features[]" id="parking_feature_garage"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($community_features as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
              class="card flex-row" style="width:calc(33.3% - 10px);"
              data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->community_features) && in_array($item['name'], json_decode($auction->get->community_features, true) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group community_features_other d-none">
          <label class="fw-bold">Community Features:</label>
          <input type="text" name="community_features_other" value="{{isset($auction->get->community_features_other) ? $auction->get->community_features_other : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dog">
        </div>
      </div>
    </div>
    <label class="fw-bold">Pets Allowed:</label>
    <select class="grid-picker" name="ptes_Allowed_vac" id="has_rental_restrictions"
      style="justify-content: flex-start;">
      <option value="">Select</option>
      @foreach ($yes_or_nos as $item)
        @php
          if ($item['name'] == 'Yes') {
              $target = '.pets_allowed_question_com';
          } else {
              $target = '';
          }
        @endphp
        <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
          style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->ptes_Allowed_vac) && $auction->get->ptes_Allowed_vac == $item['name'] ? 'selected' : ''}}>
          {{ $item['name'] }}
        </option>
      @endforeach
    </select>
    <div class="row ">
      @php
        $total_pets_allowed = [
          ['target' => '', 'name' => '1'], 
          ['target' => '', 'name' => '2'], 
          ['target' => '', 'name' => '3'], 
          ['target' => '', 'name' => '4'], 
          ['target' => '', 'name' => '5'], 
          ['target' => '', 'name' => '6'], 
          ['target' => '', 'name' => '7'], 
          ['target' => '', 'name' => '8'], 
          ['target' => '', 'name' => '9'], 
          ['target' => '', 'name' => '10'], 
          ['target' => '.custom_pets_allowed_com', 'name' => 'Other']
        ];
      @endphp
      <div class="form-group pets_allowed_question_com d-none">
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
        <div class="form-group custom_pets_allowed_com d-none">
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
          <textarea name="pet_restrictions" value="{{isset($auction->get->pet_restrictions) ? $auction->get->pet_restrictions : ''}}" id="pet_restrictions" class="form-control" cols="30" rows="5"></textarea>
        </div>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="73">
    <div class="form-group">
      <label class="fw-bold"> Description:</label>
      <textarea name="descriptionCom" id="description" class="form-control" cols="30" rows="10">{{isset($auction->get->descriptionCom) ? $auction->get->descriptionCom : ''}}</textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">Legal Disclaimers:</label>
      <input type="text" name="disclamer_com" value="{{isset($auction->get->disclamer_com) ? $auction->get->disclamer_com : ''}}" id="keywords" class="form-control has-icon"
        data-icon="fa-solid fa-tag">
    </div>
    <div class="form-group">
      <label class="fw-bold">Driving Directions:</label>
      <input type="text" name="driving_directions_com" value="{{isset($auction->get->driving_directions_com) ? $auction->get->driving_directions_com : ''}}" id="keywords" class="form-control has-icon"
        data-icon="fa-solid fa-car">
    </div>
    <div class="form-group">
      @php
        $sellerCompCommercial = [['name' => 'Yes', 'target' => '','target'=>'.sellerComYesCommercial','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],['name' => 'Negotiable', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-check"></i>']];
      @endphp
      <label class="fw-bold">Is the seller offering compensation for a buyer’s agent?</label>
      <select class="grid-picker" name="looking_other_property" id="looking_other_property"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($sellerCompCommercial as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->looking_other_property) && $auction->get->looking_other_property == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group sellerComYesCommercial d-none">
        <div class="d-flex justify-content-between aalign-items-center">
          <label class="fw-bold">Buyer’s Agent Compensation:</label>
          <div
              class="d-flex align-items-center justify-content-center icon-select-btn-div">
              <button type="button" class="select-btn me-1 active"
                  data-type="percent">%</button>
              <button type="button" class="select-btn" data-type="amount">$</button>
          </div>
        </div>
        <input type="text" name="compensation_amount_com" value="{{isset($auction->get->compensation_amount_com) ? $auction->get->compensation_amount_com : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-percent">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="74">
    <div class="form-group">
      <label class="fw-bold">Is the Seller actively seeking to purchase another property?
      </label>
      <select class="grid-picker" name="looking_other_property_com" id="looking_other_property"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.link_commercial';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(50% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>" {{isset($auction->get->looking_other_property_com) && $auction->get->looking_other_property_com == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group link_commercial">
      <label class="fw-bold">Link to the listing on the Bid Your Offer platform:</label>
      <input type="text" name="listing_link" value="{{isset($auction->get->listing_link) ? $auction->get->listing_link : ''}}" id="listing_link" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-link">
    </div>
  </div>
  <div class="wizard-step" data-step="75">
    <h4>Title Company Information:</h4>
    <div class="form-group">
      <label class="fw-bold">Name:</label>
      <input type="text" name="title_company_name_com" value="{{isset($auction->get->title_company_name_com) ? $auction->get->title_company_name_com : ''}}" id="title_company_name" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-user">
    </div>
    <div class="form-group">
      <label class="fw-bold">Address:</label>
      <input type="text" name="title_company_address_com" value="{{isset($auction->get->title_company_address_com) ? $auction->get->title_company_address_com : ''}}" id="title_company_address" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-location-dot">
    </div>
    <div class="form-group">
      <label class="fw-bold">Phone Number:</label>
      <input type="text" name="title_company_phone_com" value="{{isset($auction->get->title_company_phone_com) ? $auction->get->title_company_phone_com : ''}}" id="title_company_phone" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-phone">
    </div>

    <div class="form-group">
      <label class="fw-bold">Email:</label>
      <input type="text" name="title_company_email_com" value="{{isset($auction->get->title_company_email_com) ? $auction->get->title_company_email_com : ''}}" id="titl_company_email" placeholder=""
        value="{{ @$auction->get->title_company_email }}" data-icon="fa-solid fa-envelope"
        class="form-control has-icon">
    </div>
  </div>
  <div class="wizard-step" data-step="76">
    @if (auth()->user()->user_type == 'agent')
      <h4>Listing Agent Information:</h4>
    @else
      <h4>Seller’s Information:</h4>
    @endif
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">First Name:</label>
        <input type="text"  name="agent_first_name_com" id="first_name" placeholder=""
          value="{{isset($auction->get->agent_first_name_com) ? $auction->get->agent_first_name_com : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-user" required>
      </div>
      <div class="form-group col-md-6">
        <label class="fw-bold">Last Name:</label>
        <input type="text" name="agent_last_name_com" id="last_name" placeholder=""
          value="{{isset($auction->get->agent_last_name_com) ? $auction->get->agent_last_name_com : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-user" required>
      </div>
    </div>
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">Phone Number:</label>
        <input type="text" name="agent_phone_com" id="agent_phone" placeholder=""
          value="{{isset($auction->get->agent_phone_com) ? $auction->get->agent_phone_com : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-phone" required>
      </div>
      <div class="form-group col-md-6">
        <label class="fw-bold">Email:</label>
        <input type="text" name="agent_email_com" id="agent_email" placeholder=""
          class="form-control has-icon" data-icon="fa-solid fa-envelope"
          value="{{isset($auction->get->agent_email_com) ? $auction->get->agent_email_com : ''}}" required>
      </div>
    </div>
    @if (auth()->user()->user_type == 'user')
      <div class="form-group col-md-6">
        <label class="fw-bold">Listed By Owner</label>
      </div>
    @endif
    @if (auth()->user()->user_type == 'agent')
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">Brokerage:</label>
        <input type="text" name="agent_brokerage_com" id="agent_brokerage" placeholder=""
          value="{{isset($auction->get->agent_brokerage_com) ? $auction->get->agent_brokerage_com : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-handshake" required>
      </div>
      <div class="form-group col-md-6">
        <label class="fw-bold">Real Estate License #:</label>
        <input type="text" name="agent_license_no_com" id="agent_license_no" placeholder=""
          value="{{isset($auction->get->agent_license_no_com) ? $auction->get->agent_license_no_com : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-id-card" required>
      </div>
    </div>
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">NAR Member ID (NRDS ID):</label>
        <input type="text" name="agent_mls_id_com" id="agent_mls_id" placeholder=""
          class="form-control has-icon" data-icon="fa-solid fa-id-card-clip"
          value="{{isset($auction->get->agent_mls_id_com) ? $auction->get->agent_mls_id_com : ''}}" required>
      </div>
    </div>
    @endif
  </div>
  <div class="wizard-step" data-step="77">
    <div class="form-group">
      <label class="fw-bold">3D Tour (Link):</label>
      <input type="text" name="three_d_tour" value="{{isset($auction->get->three_d_tour) ? $auction->get->three_d_tour : ''}}" id="three_d_tour" placeholder=""
          class="form-control has-icon" data-icon="fa-solid fa-link">
      </div>
      <div class="form-group">
        <label class="fw-bold">Floor Plan:</label>
        <input type="file" name="visible_note" id="visible_note" class="form-control">
      </div>
      <div class="form-group">
        <label class="fw-bold">Addendums/Disclosures:</label>
        <input type="file" name="visible_upload_file[]" id="upload_file" placeholder=""
        class="form-control documents-input" multiple>
      </div>
      <span class="commercialFields">
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
  {{-- Commercial/Business End --}}