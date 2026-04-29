<div class="wizard-step" data-step="86">
    <h4>Water and Dock Information:</h4>
    <div class="form-group ">
      <label class="fw-bold">Water View:</label>
      <select class="grid-picker" name="has_water_view_vac" id="has_water_view"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_view_vacant';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_view_vac) && $item['name'] == $auction->get->has_water_view_vac ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $water_views = [['name' => 'Bay/Harbor - Full', 'target' => ''], ['name' => 'Bay/Harbor - Partial', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Canal', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Gulf/Ocean - Full', 'target' => ''], ['name' => 'Gulf/Ocean - Partial', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'None', 'target' => '']];
    @endphp
    <div class="form-group water_view_vacant d-none">
      <select class="grid-picker" name="water_view[]" id="water_view" style="justify-content: flex-start;"
        multiple required>
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
      <select class="grid-picker" name="has_water_extra_vac" id="has_water_extra"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_extras_vacant';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_extra_vac) &&  $item['name'] == $auction->get->has_water_extra_vac  ? 'selected' : ''}}>
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
    <div class="form-group water_extras_vacant d-none ">
      <select class="grid-picker" name="water_extras[]" id="water_extras"
        style="justify-content: flex-start;" multiple required>
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
      <select class="grid-picker" name="has_water_fontage_vac" id="has_water_fontage"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_frontage_vacant';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_fontage_vac) && $item['name'] == $auction->get->has_water_fontage_vac  ? 'selected' : ''}}>
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
    <div class="form-group water_frontage_vacant d-none">
      <select class="grid-picker" name="water_frontage[]" id="water_frontage"
        style="justify-content: flex-start;" multiple required>
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
      <select class="grid-picker" name="has_water_access_vac" id="has_water_access"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.water_access_vacant';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_water_access_vac) && $item['name'] == $auction->get->has_water_access_vac ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $water_access = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
    @endphp
    <div class="form-group water_access_vacant">
      <select class="grid-picker" name="water_access[]" id="water_access"
        style="justify-content: flex-start;" multiple required>
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
      <select class="grid-picker" name="has_dock_vac" id="has_dock"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.dock_vacant';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_dock_vac) && $item['name'] == $auction->get->has_dock_vac ? 'selected' : ''}}>
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
        ['name' => 'Other', 'target' => '.other-dock-vacant'],
    ];
    @endphp
    <div class="form-group dock_vacant d-none ">
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
      <div class="form-group other-dock-vacant d-none">
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
        $feeFrequency = [
          ['name' => 'Annual', 'target' => ''], 
          ['name' => 'Monthly', 'target' => ''], 
          ['name' => 'Quarterly', 'target' => ''], 
          ['name' => 'N/A', 'target' => '']]
      @endphp
      <label class="fw-bold">Dock Maintenance Fee Frequency:</label>
      <select class="grid-picker" name="dock_maintenance_fee_frequency" id="dock_maintenance_fee_frequency"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($feeFrequency as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->dock_maintenance_fee_frequency) && $item['name'] == $auction->get->dock_maintenance_fee_frequency ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="87">
    <h4>Ownership Information:</h4>
    @php
      $ownerships = [
        ['name' => 'Co-op', 'target' => ''], 
        ['name' => 'Condominium', 'target' => ''], 
        ['name' => 'Fee Simple', 'target' => ''], 
        ['name' => 'Fractional', 'target' => ''], 
        ['name' => 'Other', 'target' => '']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Ownership:</label>
      <select class="grid-picker" name="ownership_vac" id="ownership"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($ownerships as $item)
          @php
            if ($item['name'] == 'Other') {
                $target = '.otherOwnershipVacant';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->ownership_vac) && $item['name'] == $auction->get->ownership_vac ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group otherOwnershipVacant">
      <label class="fw-bold">Ownership:</label>
      <input type="text" name="otherOwnership" value="{{isset($auction->get->otherOwnership) ? $auction->get->otherOwnership : ''}}" class="form-control has-icon"
        data-icon="fa-regular fa-circle-check" required>
    </div>
  </div>
  <div class="wizard-step" data-step="88">
    <h4>HOA, Condo Association and/or Master Association Information:</h4>
    <div class="form-group">
      @php
        $propsOptVacant = [
          ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.hoasVacant'],
          ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
        ];
      @endphp
      <label class="fw-bold">Does the property have an HOA, condo association, master association, and/or community fee?</label>
      <select class="grid-picker" name="has_hoa_vac" id="has_hoa" style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($propsOptVacant as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}' {{isset($auction->get->has_hoa_vac) && $item['name'] == $auction->get->has_hoa_vac ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $hoa_fee_requirenments = [['name' => 'None', 'target' => ''], ['name' => 'Optional', 'target' => ''], ['name' => ' Required', 'target' => '']];
    @endphp
    <div class="row hoasVacant d-none">
      @php
        $communityFeaturesVacant = [
            ['name' => 'Activity Core/Center', 'target' => ''],
            ['name' => 'Airport/Runway', 'target' => ''],
            ['name' => 'Association Recreation - Lease', 'target' => ''],
            ['name' => 'Association Recreation - Owned', 'target' => ''],
            ['name' => 'Beach Area', 'target' => ''],
            ['name' => 'Curbs', 'target' => ''],
            ['name' => 'Deed Restrictions', 'target' => ''],
            ['name' => 'Dog Park', 'target' => ''],
            ['name' => 'Expressway', 'target' => ''],
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
            ['name' => 'Shopping Center', 'target' => ''],
            ['name' => 'Sidewalk', 'target' => ''],
            ['name' => 'Special Community Restrictions', 'target' => ''],
            ['name' => 'Stream Seasonal', 'target' => ''],
            ['name' => 'Tennis Courts', 'target' => ''],
            ['name' => 'None', 'target' => ''],
        ];
      @endphp
      <div class="form-group ">
        <label class="fw-bold">Community Features:</label>
        <select class="grid-picker" name="community_feature[]" id="community_feature"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($communityFeaturesVacant as $item)
            <option value="{{ $item['name'] }}"
              data-icon="<i class='fa-regular fa-circle-check'></i>"
              data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->community_feature) && in_array($item['name'], json_decode($auction->get->community_feature, true) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      @php
        $associationAmenitiesVacant = [
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
            ['name' => 'Other', 'target' => '.otherAssocAmenitiesVacant'],
        ];
      @endphp
      <div class="form-group ">
        <label class="fw-bold">Association Amenities:</label>
        <select class="grid-picker" name="association_amenitie[]" id="association_amenitie"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($associationAmenitiesVacant as $item)
            <option value="{{ $item['name'] }}"
              data-icon="<i class='fa-regular fa-circle-check'></i>"
              data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->association_amenitie) && in_array($item['name'], json_decode($auction->get->association_amenitie, true) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group otherAssocAmenitiesVacant d-none">
          <label class="fw-bold">Association Amenities: </label>
          <input type="text" name="otherAssocAmenities_vac" value="{{isset($auction->get->otherAssocAmenities_vac) ? $auction->get->otherAssocAmenities_vac : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
        </div>
      </div>
      @php
        $feeIncludesVacant = [
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
            ['name' => 'Other', 'target' => '.otherFeeIncludeVacant'],
            ['name' => 'None', 'target' => ''],
        ];
      @endphp
      <div class="form-group ">
        <label class="fw-bold">Fee Includes:</label>
        <select class="grid-picker" name="fee_include[]" id="fee_include"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($feeIncludesVacant as $item)
            <option value="{{ $item['name'] }}"
              data-icon="<i class='fa-regular fa-circle-check'></i>"
              data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->fee_include) && in_array($item['name'], json_decode($auction->get->fee_include, true) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group otherFeeIncludeVacant d-none">
          <label class="fw-bold">Fee Includes:</label>
          <input type="text" name="otherFeeInclude_vac" value="{{isset($auction->get->otherFeeInclude_vac) ? $auction->get->otherFeeInclude_vac : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
        </div>
      </div>
      <div class="form-group">
        <label class="fw-bold">Amenities with Additional Fees:</label>
        <input type="text" name="amenities_with_additional_fees_vac" value="{{isset($auction->get->amenities_with_additional_fees_vac) ? $auction->get->amenities_with_additional_fees_vac : ''}}"
          class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
      <div class="form-group">
        @php
          $communityAssociationWaterFeatures = [
            ['name' => 'Bay/Harbor Front', 'target' => ''],
            ['name' => 'Boat Slip', 'target' => ''],
            ['name' => 'Canal Front', 'target' => ''],
            ['name' => 'Community Boat Ramp', 'target' => ''],
            ['name' => 'Dock', 'target' => ''],
            ['name' => 'Fishing', 'target' => ''],
            ['name' => 'Gulf/Ocean Front', 'target' => ''],
            ['name' => 'Intracoastal Waterway', 'target' => ''],
            ['name' => 'Lake', 'target' => ''],
            ['name' => 'Marina', 'target' => ''],
            ['name' => 'Private Boat Ramp', 'target' => ''],
            ['name' => 'Public Boat Ramp', 'target' => ''],
            ['name' => 'River', 'target' => ''],
            ['name' => 'Water Access', 'target' => ''],
            ['name' => 'Waterfront', 'target' => ''],
          ];
        @endphp
        <label class="fw-bold">Community/Association Water Features:</label>
        <select class="grid-picker" name="comm_assoc_water_features" id="has_cdd" style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($communityAssociationWaterFeatures as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" {{isset($auction->get->comm_assoc_water_features) && $item['name'] == $auction->get->comm_assoc_water_features ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        @php
          $ccdOptVacant = [
            ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.ccdVacant'],
            ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
          ];
        @endphp
        <label class="fw-bold">CDD:</label>
        <select class="grid-picker" name="has_cdd_vac" id="has_cdd" style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($ccdOptVacant as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->has_cdd_vac) && $item['name'] == $auction->get->has_cdd_vac ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group ccdVacant d-none">
        <label class="fw-bold">Annual CDD Fee:</label>
        <input type="number" name="annual_cdd_fee_vac" value="{{isset($auction->get->annual_cdd_fee_vac) ? $auction->get->annual_cdd_fee_vac : ''}}" id="annual_cdd_fee" class="form-control has-icon"
          data-icon="fa-solid fa-dollar ">
      </div>
      <div class="form-group">
          @php
          $landLeaseOptVacant = [
              ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.landLeaseVacant'],
              ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
            ];
          @endphp
        <label class="fw-bold">Annual Land Lease Fee:</label>
        <select class="grid-picker" name="has_land_lease_vac" id="has_hoa"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($landLeaseOptVacant as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->has_land_lease_vac) && $item['name'] == $auction->get->has_land_lease_vac ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group landLeaseVacant d-none">
        <label class="fw-bold">Annual Land Lease Fee:</label>
        <input type="number" name="land_lease_fee_vac" value="{{isset($auction->get->land_lease_fee_vac) ? $auction->get->land_lease_fee_vac : ''}}" id="land_lease_fee" class="form-control has-icon"
          data-icon="fa-solid fa-dollar ">
      </div>
    </div>
        <div class="form-group">
            @php
              $hoaFeeRequirementsVacant = [
                ['name'=>'None','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>''],
                ['name'=>'Optional','icon'=>'<i class="fa-regular fa-circle-question"></i>','target'=>'.hoa_yes_vac'],
                ['name'=>'Required','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.hoa_yes_vac']
              ];
            @endphp
          <label class="fw-bold">Hoa Fee Requirement:</label>
            <select name="hoaFeeRequirements_vac" class="grid-picker" style="justify-content: flex-start;">
              <option value="">Select</option>
              @foreach ($hoaFeeRequirementsVacant as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->hoaFeeRequirements_vac) && $item['name'] == $auction->get->hoaFeeRequirements_vac ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
              @endforeach
            </select>
        </div>

        <div class="form-group hoa_yes_vac d-none">
          <div class="form-group">
            <label class="fw-bold">HOA Fee:</label>
            <input type="number" name="hoaFeeAmount_vac" value="{{isset($auction->get->hoaFeeAmount_vac) ? $auction->get->hoaFeeAmount_vac : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
          </div>
          <div class="form-group">
            @php
              $paymentSchedulesVacant = [
                      ['name'=>'Annually','target'=>''],
                      ['name'=>'Monthly','target'=>''],
                      ['name'=>'Quarterly','target'=>''],
                      ['name'=>'Semi-Annually','target'=>'']
                    ];
            @endphp
            <label class="fw-bold">HOA Payment Schedule:</label>
            <select name="paymentSchedules_vac" id="hoaPaymentSchedule" class="grid-picker">
              <option value="">Select</option>
              @foreach ($paymentSchedulesVacant as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                  style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->paymentSchedules_vac) && $item['name'] == $auction->get->paymentSchedules_vac ? 'selected' : ''}}>
                  {{ $item['name'] }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="form-group">
          @php
            $condoFee = [
                    ['name'=>'Yes','target'=>'.condo_yes'],
                    ['name'=>'No','target'=>''],
                  ];
          @endphp
          <label class="fw-bold">Condo Fee:</label>
          <select name="condoFee_vac" class="grid-picker">
            <option value="">Select</option>
            @foreach ($condoFee as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->condoFee_vac) && $item['name'] == $auction->get->condoFee_vac ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>

          <div class="form-group condo_yes d-none">
            <label class="fw-bold">Condo Fee:</label>
            <input type="number" name="condoFeeAmount_vac" value="{{isset($auction->get->condoFeeAmount_vac) ? $auction->get->condoFeeAmount_vac : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" >
          </div>
        </div>
        

        <div class="form-group">
          @php
            $condoPayOptVacant = [
                    ['name'=>'Annually','target'=>''],
                    ['name'=>'Monthly','target'=>''],
                    ['name'=>'Quarterly','target'=>''],
                    ['name'=>'Semi-Annually','target'=>'']
                  ];
          @endphp
          <label class="fw-bold">Condo Payment Schedule:</label>
          <select name="condoPay_vac" id="condoPaymentSchedule" class="grid-picker">
            <option value="">Select</option>
            @foreach ($condoPayOptVacant as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->condoPay_vac) && $item['name'] == $auction->get->condoPay_vac ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group">
            @php
              $masterAssocOptVacant = [
                ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.masterAssocYesVacant'],
                ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                    ];
            @endphp
          <label class="fw-bold">Master Association:</label>
          <select name="masterAssoc" id="masterAssociation" class="grid-picker">
            <option value="">Select</option>
            @foreach ($masterAssocOptVacant as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->masterAssoc) && $item['name'] == $auction->get->masterAssoc ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="form-group masterAssocYesVacant d-none">
          <div class="form-group">
            <label class="fw-bold">Master Association Fee:</label>
            <input type="text" name="masterAssociationFeeAmount_vac" value="{{isset($auction->get->masterAssociationFeeAmount_vac) ? $auction->get->masterAssociationFeeAmount_vac : ''}}" id="masterAssociationFeeAmount" class="form-control has-icon " data-icon="fa-solid fa-dollar-sign" >
          </div>

          <div class="form-group">
            @php
                $assocScheduleOptVacant = [
                        ['name'=>'Annually','target'=>''],
                        ['name'=>'Monthly','target'=>''],
                        ['name'=>'Quarterly','target'=>''],
                        ['name'=>'Semi-Annually','target'=>'']
                      ];
              @endphp
            <label class="fw-bold">Master Association Fee Schedule:</label>
            <select name="assocSchedule_vac" id="masterAssociationFeeSchedule_vac" class="grid-picker">
              <option value="">Select</option>
              @foreach ($assocScheduleOptVacant as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                  style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->assocSchedule_vac) && $item['name'] == $auction->get->assocSchedule_vac ? 'selected' : ''}}>
                  {{ $item['name'] }}
                </option>
              @endforeach
            </select>
          </div>

          <div class="form-group">
            <label class="fw-bold">Master Association Name:</label>
            <input type="text" name="masterAssociationName_vac" value="{{isset($auction->get->masterAssociationName_vac) ? $auction->get->masterAssociationName_vac : ''}}" id="masterAssociationName" class="form-control has-icon" data-icon="fa-solid fa-user">
          </div>

          <div class="form-group">
            <label class="fw-bold">Master Association Contact Phone:</label>
            <input type="text" name="masterAssociationContactPhone_vac" value="{{isset($auction->get->masterAssociationContactPhone_vac) ? $auction->get->masterAssociationContactPhone_vac : ''}}" class="form-control has-icon" data-icon="fa-solid fa-phone">
          </div>
        </div>

        <div class="form-group">
          @php
            $additioalFeeOptVacant = [
              ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.additionalFeeYesVacant'],
              ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                  ];
          @endphp
          <label class="fw-bold">Are there any additional fees?</label>
          <select name="additionalFees_vac" id="additionalFees" class="grid-picker">
            <option value="">Select</option>
            @foreach ($additioalFeeOptVacant as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}' {{isset($auction->get->additionalFees_vac) && $item['name'] == $auction->get->additionalFees_vac ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="form-group additionalFeeYesVacant d-none">
          <div class="form-group">
            <label class="fw-bold">What is the fee for?</label>
            <input type="text" name="additionalFeeReason" value="{{isset($auction->get->additionalFeeReason) ? $auction->get->additionalFeeReason : ''}}" id="additionalFeeReason" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
          </div>
          <div class="form-group">
            <label class="fw-bold">Other Fee:</label>
            <input type="number" name="otherFeeAmount_vac" value="{{isset($auction->get->otherFeeAmount_vac) ? $auction->get->otherFeeAmount_vac : ''}}" id="otherFeeAmount" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
          </div>
        </div>
          <div class="form-group">
            @php
              $otherFeeOptVacant = [
                      ['name'=>'Annually','target'=>''],
                      ['name'=>'Monthly','target'=>''],
                      ['name'=>'Quarterly','target'=>''],
                      ['name'=>'Semi-Annually','target'=>'']
                    ];
            @endphp
            <label class="fw-bold">Other Fee Schedule:</label>
            <select name="otherFee" id="otherFeeSchedule" class="grid-picker">
              <option value="">Select</option>
              @foreach ($otherFeeOptVacant as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                  style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->otherFee) && $item['name'] == $auction->get->otherFee ? 'selected' : ''}}>
                  {{ $item['name'] }}
                </option>
              @endforeach
            </select>
          </div>

        <div class="form-group">
          <label class="fw-bold">Association/Manager Contact Name:</label>
          <input type="text" name="associationManagerContactName_vac" value="{{isset($auction->get->associationManagerContactName_vac) ? $auction->get->associationManagerContactName_vac : ''}}" class="form-control has-icon" data-icon="fa-solid fa-user">
        </div>

        <div class="form-group">
          <label class="fw-bold">Association/Manager Contact Email:</label>
          <input type="email" name="associationManagerContactEmail_vac" value="{{isset($auction->get->associationManagerContactEmail_vac) ? $auction->get->associationManagerContactEmail_vac : ''}}" class="form-control has-icon" data-icon="fa-solid fa-envelope">
        </div>

        <div class="form-group">
          <label class="fw-bold">Association/Manager Contact Phone:</label>
          <input type="text" name="associationManagerContactPhone_vac" value="{{isset($auction->get->associationManagerContactPhone_vac) ? $auction->get->associationManagerContactPhone_vac : ''}}" class="form-control has-icon" data-icon="fa-solid fa-phone">
        </div>

        <div class="form-group">
          <label class="fw-bold">Association/Manager Contact Website Address:</label>
          <input type="text" name="associationManagerContactWebsite_vac" value="{{isset($auction->get->associationManagerContactWebsite_vac) ? $auction->get->associationManagerContactWebsite_vac : ''}}" class="form-control has-icon" data-icon="fa-regular fa-window-restore">
        </div>

        <div class="form-group">
          @php
            $olderPersonOptVacant = [
              ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>''],
              ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                  ];
          @endphp
          <label class="fw-bold">Housing for Older Persons:</label>
          <select name="olderPersons_vac" id="housingForOlderPersons" class="grid-picker">
            <option value="">Select</option>
            @foreach ($olderPersonOptVacant as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->olderPersons_vac) && $item['name'] == $auction->get->olderPersons_vac ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>
  </div>
  <div class="wizard-step" data-step="89">
    <div class="form-group">
      <label class="fw-bold"> Description:</label>
      <textarea name="descriptionVac" value="{{isset($auction->get->descriptionVac) ? $auction->get->descriptionVac : ''}}" id="description" class="form-control" cols="30" rows="10" required></textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">Legal Disclaimers:</label>
      <textarea name="disclamer_vac" value="{{isset($auction->get->disclamer_vac) ? $auction->get->disclamer_vac : ''}}" id="keywords" class="form-control has-icon" data-icon="fa-solid fa-tag" cols="30" rows="1"></textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">Driving Directions:</label>
      <input type="text" name="driving_directions_vac" value="{{isset($auction->get->driving_directions_vac) ? $auction->get->driving_directions_vac : ''}}" id="keywords" class="form-control has-icon"
        data-icon="fa-solid fa-car">
    </div>
    <div class="form-group">
          @php
            $sellerCompVacant = [['name' => 'Yes', 'target' => '','target'=>'.sellerComYesVacant','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],['name' => 'Negotiable', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-check"></i>']];
          @endphp
        <label class="fw-bold">Is the seller offering compensation for a buyer’s agent?
        </label>
        <select class="grid-picker" name="looking_other_property" id="looking_other_property"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($sellerCompVacant as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->looking_other_property) && $item['name'] == $auction->get->looking_other_property ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group sellerComYesVacant d-none">
          <div class="d-flex justify-content-between aalign-items-center">
            <label class="fw-bold">Buyer’s Agent Compensation:</label>
            <div
                class="d-flex align-items-center justify-content-center icon-select-btn-div">
                <button type="button" class="select-btn me-1 active"
                    data-type="percent">%</button>
                <button type="button" class="select-btn" data-type="amount">$</button>
            </div>
          </div>
          <input type="text" name="compensation_amount_vac" value="{{isset($auction->get->compensation_amount_vac) ? $auction->get->compensation_amount_vac : ''}}" class="form-control has-icon"
            data-icon="fa-solid fa-percent">
        </div>
      </div>
  </div>
  <div class="wizard-step" data-step="90">
    <div class="form-group">
      <label class="fw-bold">Is the Seller actively seeking to purchase another property?
      </label>
      <select class="grid-picker" name="looking_other_property_vac" id="looking_other_property"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.anotherPropVacant';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(50% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>" {{isset($auction->get->looking_other_property_vac) && $item['name'] == $auction->get->looking_other_property_vac ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group anotherPropVacant">
      <label class="fw-bold">Link to the listing on the Bid Your Offer platform:</label>
      <input type="text" name="listing_link" value="{{isset($auction->get->listing_link) ? $auction->get->listing_link : ''}}" id="listing_link" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-link">
    </div>
  </div>
  <div class="wizard-step" data-step="91">
    <h4>Title Company Information:</h4>
    <div class="form-group">
      <label class="fw-bold">Name:</label>
      <input type="text" name="title_company_name_vac" value="{{isset($auction->get->title_company_name_vac) ? $auction->get->title_company_name_vac : ''}}" id="title_company_name" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-user">
    </div>
    <div class="form-group">
      <label class="fw-bold">Address:</label>
      <input type="text" name="title_company_address_vac" value="{{isset($auction->get->title_company_address_vac) ? $auction->get->title_company_address_vac : ''}}" id="title_company_address" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-location-dot">
    </div>
    <div class="form-group">
      <label class="fw-bold">Phone Number:</label>
      <input type="text" name="title_company_phone_vac" value="{{isset($auction->get->title_company_phone_vac) ? $auction->get->title_company_phone_vac : ''}}" id="title_company_phone" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-phone">
    </div>

    <div class="form-group">
      <label class="fw-bold">Email:</label>
      <input type="text" name="title_company_email_vac" value="{{isset($auction->get->title_company_email_vac) ? $auction->get->title_company_email_vac : ''}}" id="titl_company_email" placeholder=""
      data-icon="fa-solid fa-envelope" class="form-control has-icon">
    </div>

  </div>
  <div class="wizard-step" data-step="92">
    @if (auth()->user()->user_type == 'agent')
      <h4>Listing Agent Information:</h4>
    @else
      <h4>Seller’s Information:</h4>
    @endif
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">First Name:</label>
        <input type="text" name="agent_first_name_vac" id="first_name" placeholder=""
          value="{{isset($auction->get->agent_first_name_vac) ? $auction->get->agent_first_name_vac : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-user" required>
      </div>
      <div class="form-group col-md-6">
        <label class="fw-bold">Last Name:</label>
        <input type="text" name="agent_last_name_vac" id="last_name" placeholder=""
          value="{{isset($auction->get->agent_last_name_vac) ? $auction->get->agent_last_name_vac : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-user" required>
      </div>
    </div>
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">Phone Number:</label>
        <input type="text" name="agent_phone_vac" id="agent_phone" placeholder=""
          value="{{isset($auction->get->agent_phone_vac) ? $auction->get->agent_phone_vac : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-phone" required>
      </div>
      <div class="form-group col-md-6 ">
        <label class="fw-bold">Email:</label>
        <input type="text" name="agent_email_vac" id="agent_email" placeholder=""
          class="form-control has-icon" data-icon="fa-solid fa-envelope"
          value="{{isset($auction->get->agent_email_vac) ? $auction->get->agent_email_vac : ''}}" required>
      </div>
    </div>
    @if (auth()->user()->user_type == 'user')
    <div class="form-group  col-md-6">
      <label class="fw-bold">Listed By: Listed By Owner:</label>
    </div>
    @endif
    @if (auth()->user()->user_type == 'agent')
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">Brokerage:</label>
        <input type="text" name="agent_brokerage_vac" id="agent_brokerage" placeholder=""
          value="{{isset($auction->get->agent_brokerage_vac) ? $auction->get->agent_brokerage_vac : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-handshake" required>
      </div>
      <div class="form-group col-md-6">
        <label class="fw-bold">Real Estate License #:</label>
        <input type="text" name="agent_license_no_vac" id="agent_license_no" placeholder=""
          value="{{isset($auction->get->agent_license_no_vac) ? $auction->get->agent_license_no_vac : ''}}" class="form-control has-icon"
          data-icon="fa-solid fa-id-card" required>
      </div>
    </div>
    <div class="form-group row">
      <div class="form-group col-md-6">
        <label class="fw-bold">NAR Member ID (NRDS ID):</label>
        <input type="text" name="agent_mls_id_vac" id="agent_mls_id" placeholder=""
          class="form-control has-icon" data-icon="fa-solid fa-id-card-clip"
          value="{{isset($auction->get->agent_mls_id_vac) ? $auction->get->agent_mls_id_vac : ''}}" required>
      </div>
    </div>
    @endif
  </div>
  <div class="wizard-step" data-step="93">
    <div class="form-group">
      <label class="fw-bold">3D Tour (Link):</label>
      <input type="text" name="three_d_tour" value="{{isset($auction->get->three_d_tour) ? $auction->get->three_d_tour : ''}}" id="three_d_tour" placeholder=""
          class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
        </div>
      <div class="form-group">
        <label class="fw-bold">Plot Plan:</label>
        <input type="file" name="floor_plan[]" id="floor_plan_1" class="form-control" accept="image/*" >
      </div>
      <div class="form-group">
        <label class="fw-bold">Addendums/Disclosures:</label>
        <input type="file" name="disclosures[]" id="upload_file" placeholder="" class="form-control documents-input"
        multiple>
      </div>
      <span class="vacantFields">
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
  {{-- Vacant end --}}
  <div class="d-flex justify-content-between form-group mt-4">
    <div>
      <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
    </div>
    <div>
      <a class="wizard-step-next btn btn-success btn-lg text-600" style="display: none;">Next</a>
      <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
        style="display: none;" id="saveBtn">Save</button>
    </div>
  </div>