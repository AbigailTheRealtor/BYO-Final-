<div class="wizard-step" data-step="13">
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
          ['name' => 'Other', 'target' => '.otherAppliancesRes'],
      ];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Appliances:</label>
      <select class="grid-picker" name="appliances[]" id="appliances" style="justify-content: flex-start;"
        multiple required>
        <option value="">Select</option>
        @foreach ($appliances as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->appliances) && in_array($item['name'], json_decode($auction->get->appliances) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherAppliancesRes d-none">
        <label class="fw-bold">Appliances:</label>
        <input type="text" name="otherAppliances" value="{{isset($auction->get->otherAppliances) ? $auction->get->otherAppliances : ''}}" id="flood_zone_code" placeholder=""
          class="form-control has-icon" data-icon="fa-regular fa-check-circle">
      </div>
    </div>
    <div class="form-group fireplace">
      <label class="fw-bold">Fireplace:</label>
      <select name="fireplace" id="fireplace" class="grid-picker" style="justify-content: flex-start;">
        <option value=""></option>
        @foreach ($yes_or_nos as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->fireplace) && $auction->get->fireplace == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="14">
    <div class="form-group ">
      @php
        $furnishingsRes = [['name' => 'Yes', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],['name' => 'Optional', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-question"></i>']];
      @endphp
      <label class="fw-bold">Are there any furnishings included in the purchase?</label>
      <select class="grid-picker" name="has_furnishing" id="has_furnishing"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($furnishingsRes as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}' {{isset($auction->get->has_furnishing) && $auction->get->has_furnishing == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="row " id="has_furnishing_residential_and_income" style="display: none">
      <div class="form-group">
        <div class="form-group">
          <label class="fw-bold">What furnishings are included in the purchase?</label>
          <input type="text" name="furnishings_include" value="{{isset($auction->get->furnishings_include) ? $auction->get->furnishings_include : ''}}" id="flood_zone_code" placeholder=""
            class="form-control has-icon" data-icon="fa-regular fa-check-circle">
        </div>
      </div>
      @php
        $additional_fees = [['name' => 'Additional Fees', 'target' => ''], ['name' => 'Included in Purchase Price', 'target' => '']];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Are there any additional fees for the listed furnishings, or are
          they
          included in the purchase price? </label>

        <select class="grid-picker" name="has_additional_fees" id="has_water_view"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($additional_fees as $item)
            @php
              if ($item['name'] == 'Additional Fees') {
                  $target = '.has_additional_fees1';
              } else {
                  $target = '';
              }
            @endphp
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->has_additional_fees) && $auction->get->has_additional_fees == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group has_additional_fees1">
        <div class="form-group">
          <label class="fw-bold">How much is the listed furniture?</label>
          <input type="text" name="listed_furniture_price" value="{{isset($auction->get->listed_furniture_price) ? $auction->get->listed_furniture_price : ''}}" id="listed_furniture_price" placeholder=""
            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
        </div>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="15">
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
          ['name' => 'Other', 'target' => '.otherInterior'],
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Interior Features:</label>
      <select class="grid-picker" name="interior_features[]" multiple id="tenant_pays"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($interior_features as $interior_feature)
          <option value="{{ $interior_feature['name'] }}" data-target="{{ $interior_feature['target'] }}"
            class="card flex-column" style="width:calc(33.3% - 10px);"
            data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->interior_features) && in_array($interior_feature['name'], json_decode($auction->get->interior_features) ?? []) ? 'selected' : ''}}>
            {{$interior_feature['name']}}
          </option>
        @endforeach
      </select>
      <div class="form-group otherInterior d-none">
        <label class="fw-bold">Interior Features:</label>
        <input type="text" name="otherInterior" value="{{isset($auction->get->otherInterior) ? $auction->get->otherInterior : ''}}" class="form-control has-icon" data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="16">
    @php
      $additionalRoom = [
        ['name'=> 'Attic', 'target'=>'' ],
        ['name'=> 'Bonus Room', 'target'=>'' ],
        ['name'=> 'Breakfast Room Separate', 'target'=>'' ],
        ['name'=> 'Den/Library/Office', 'target'=>'' ],
        ['name'=> 'Family Room', 'target'=>'' ],
        ['name'=> 'Florida Room', 'target'=>'' ],
        ['name'=> 'Formal Dining Room Separate', 'target'=>'' ],
        ['name'=> 'Formal Living Room Separate', 'target'=>'' ],
        ['name'=> 'Garage Apartment', 'target'=>'' ],
        ['name'=> 'Great Room', 'target'=>'' ],
        ['name'=> 'Inside Utility', 'target'=>'' ],
        ['name'=> 'Interior In-Law Suite w/Private Entry', 'target'=>'' ],
        ['name'=> 'Interior In-Law Suite w/No Private Entry', 'target'=>'' ],
        ['name'=> 'Loft', 'target'=>'' ],
        ['name'=> 'Media Room', 'target'=>'' ],
        ['name'=> 'Storage Rooms', 'target'=> '']
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Additional Rooms:</label>
      <select class="grid-picker" name="additionalRooms[]" style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($additionalRoom as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-column" style="width:calc(33.3% - 10px);"
            data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->additionalRooms) && in_array($item['name'], json_decode($auction->get->additionalRooms) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="17">
    @php
      $accessibilityFeatures = [
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
      <select class="grid-picker" name="accessibilityFeatures[]" style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($accessibilityFeatures as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-column" style="width:calc(33.3% - 10px);"
            data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->accessibilityFeatures) && in_array($item['name'], json_decode($auction->get->accessibilityFeatures) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="18">
    @php
      $laundryFeatures = [
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
        ['name' => 'Other', 'target' => ''],
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Laundry Features:</label>
      <select class="grid-picker" name="laundryFeatures[]" style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($laundryFeatures as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-column" style="width:calc(33.3% - 10px);"
            data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->laundryFeatures) && in_array($item['name'], json_decode($auction->get->laundryFeatures) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="19">
    <div class="form-group">
      <label class="fw-bold">How many floors are in the property? </label>
      <input type="number" name="number_of_buildings" value="{{isset($auction->get->number_of_buildings) ? $auction->get->number_of_buildings : ''}}" id="number_of_buildings" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-building" required>
    </div>
    <div class="form-group">
      <label class="fw-bold">What floor number is the property on?</label>
      <input type="number" name="floor_number" value="{{isset($auction->get->otherAppliances) ? $auction->get->otherAppliances : ''}}" id="floors_in_unit" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-hotel">
    </div>
    <div class="form-group">
      <label class="fw-bold">How many floors are in the entire building?</label>
      <input type="number" name="total_floors" value="{{isset($auction->get->total_floors) ? $auction->get->total_floors : ''}}" id="total_floors" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-hotel">
    </div>
    <div class="form-group">
      @php
        $buildingElevator=[
          ['name'=>'Yes','target'=>'','icon'=>'<i class="fa-solid fa-building"></i>'],
          ['name'=>'No','target'=>'','icon'=>'<i class="fa-solid fa-building"></i>']
    ];
      @endphp
      <label class="fw-bold">Building Elevator:</label>
      <select class="grid-picker" name="building_elevator" id="building_elevator"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($buildingElevator as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->building_elevator) && $auction->get->building_elevator == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="20">
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
        ['name' => 'Reclaimed wood', 'target' => ''],
        ['name' => 'Recycled/Composite Flooring', 'target' => ''],
        ['name' => 'Slate', 'target' => ''],
        ['name' => 'Terrazzo', 'target' => ''],
        ['name' => 'Tile', 'target' => ''],
        ['name' => 'Travertine', 'target' => ''],
        ['name' => 'Vinyl', 'target' => ''],
        ['name' => 'Wood', 'target' => ''],
        ['name' => 'Other', 'target' => '.otherFloorCoveringRes']
      ];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Floor Covering:</label>
      <select class="grid-picker" name="floor_covering[]" id="floor_covering"
        style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($floor_coverings as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->floor_covering) && in_array($item['name'], json_decode($auction->get->floor_covering) ??[]) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherFloorCoveringRes d-none">
        <label class="fw-bold">Floor Covering:</label>
        <input type="text" name="otherFloorCovering" value="{{isset($auction->get->otherFloorCovering) ? $auction->get->otherFloorCovering : ''}}" class="form-control has-icon" data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="21">
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
    <div class="form-group">
      <label class="fw-bold">Front Exposure:</label>
      <select class="grid-picker" name="front_exposure" style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($front_exposures as $front_exposure)
          <option value="{{ $front_exposure['name'] }}" data-target="{{ $front_exposure['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->front_exposure) && $auction->get->front_exposure == $item['name'] ? 'selected' : ''}}>
            {{ $front_exposure['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="22">
    @php
      $foundations = [['name' => 'Basement', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick/Mortar', 'target' => ''], ['name' => 'Concrete Perimeter', 'target' => ''], ['name' => 'Crawlspace', 'target' => ''], ['name' => 'Pillar/Post/Pier', 'target' => ''], ['name' => 'Slab', 'target' => ''], ['name' => 'Stem Wall', 'target' => ''], ['name' => 'Stilt/On Piling', 'target' => ''], ['name' => 'Other', 'target' => '.otherFoundationRes']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Foundation:</label>
      <select class="grid-picker" name="foundation[]" id="foundation" style="justify-content: flex-start;"
        multiple required>
        <option value="">Select</option>
        @foreach ($foundations as $foundation)
          <option value="{{ $foundation['name'] }}" data-target="{{ $foundation['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->foundation) && in_array($foundation['name'], json_decode($auction->get->foundation) ?? []) ? 'selected' : ''}}>
            {{ $foundation['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherFoundationRes d-none">
        <label class="fw-bold">Foundation:</label>
        <input type="text" name="otherFoundation" value="{{isset($auction->get->otherFoundation) ? $auction->get->otherFoundation : ''}}" class="form-control has-icon" data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="23">
    @php
      $exterior_constructions = [['name' => 'Asbestos', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Cedar', 'target' => ''], ['name' => 'Cement Siding', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'HardiPlank Type', 'target' => ''], ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''], ['name' => 'Log', 'target' => ''], ['name' => 'Metal Frame', 'target' => ''], ['name' => 'Metal Siding', 'target' => ''], ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''], ['name' => 'Stone', 'target' => ''], ['name' => 'Stucco', 'target' => ''], ['name' => 'Tilt up Walls', 'target' => ''], ['name' => 'Vinyl Siding', 'target' => ''], ['name' => 'Wood Frame', 'target' => ''], ['name' => 'Wood Frame (FSC)', 'target' => ''], ['name' => 'Wood Siding ', 'target' => ''], ['name' => 'Other', 'target' => '.otherConstructionRes']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Exterior Construction:</label>
      <select class="grid-picker" name="exterior_construction[]" id="exterior_construction"
        style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($exterior_constructions as $item)
          <option value="{{ $item['name'] }}"
            data-target="{{ $item['target'] }}" class="card flex-row"
            data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);" {{isset($auction->get->exterior_construction) && in_array($item['name'], json_decode($auction->get->exterior_construction) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherConstructionRes d-none">
        <label class="fw-bold">Exterior Construction:</label>
        <input type="text" name="otherConstruction" value="{{isset($auction->get->otherConstruction) ? $auction->get->otherConstruction : ''}}" class="form-control has-icon" data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="24">
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
          ['name' => 'Other', 'target' => '.otherExteriorRes'],
      ];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Exterior Features:</label>
      <select class="grid-picker" name="exterior_feature[]" id="exterior_feature"
        style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($exterior_features as $exterior_feature)
          <option value="{{ $exterior_feature['name'] }}" data-target="{{ $exterior_feature['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->exterior_feature) && in_array($exterior_feature['name'], json_decode($auction->get->exterior_feature) ?? []) ? 'selected' : ''}}>
            {{ $exterior_feature['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherExteriorRes d-none">
        <label class="fw-bold">Exterior Features:</label>
        <input type="text" name="otherExterior" value="{{isset($auction->get->otherExterior) ? $auction->get->otherExterior : ''}}" class="form-control has-icon" data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="25">
    @php
      $lot_features = [
          ['name' => 'Cleared', 'target' => ''],
          ['name' => 'Coastal Construction Control Line', 'target' => ''],
          ['name' => 'Conservation Area', 'target' => ''],
          ['name' => 'Corner Lot', 'target' => ''],
          ['name' => 'Cul-De-Sac', 'target' => ''],
          ['name' => 'Drainage Canal', 'target' => ''],
          ['name' => 'Farm', 'target' => ''],
          ['name' => 'Flag Lot', 'target' => ''],
          ['name' => 'Flood Insurance Required', 'target' => ''],
          ['name' => 'Flood Zone', 'target' => ''],
          ['name' => 'Greenbelt', 'target' => ''],
          ['name' => 'Highway', 'target' => ''],
          ['name' => 'Hilly', 'target' => ''],
          ['name' => 'Historic District', 'target' => ''],
          ['name' => 'In City Limits', 'target' => ''],
          ['name' => 'In County', 'target' => ''],
          ['name' => 'Irregular Lot', 'target' => ''],
          ['name' => 'Key Lot', 'target' => ''],
          ['name' => 'Landscaped', 'target' => ''],
          ['name' => 'Level/Flat', 'target' => ''],
          ['name' => 'Mountainous', 'target' => ''],
          ['name' => 'Near Golf Course', 'target' => ''],
          ['name' => 'Near Marina', 'target' => ''],
          ['name' => 'Near Public Transit', 'target' => ''],
          ['name' => 'On Golf Course', 'target' => ''],
          ['name' => 'Oversized Lot', 'target' => ''],
          ['name' => 'Pasture/Agriculture', 'target' => ''],
          ['name' => 'Private', 'target' => ''],
          ['name' => 'Rolling Slope', 'target' => ''],
          ['name' => 'Sidewalks', 'target' => ''],
          ['name' => 'Sloped', 'target' => ''],
          ['name' => 'Street Brick', 'target' => ''],
          ['name' => 'Street Dead-End', 'target' => ''],
          ['name' => 'Street One Way', 'target' => ''],
          ['name' => 'Street Paved', 'target' => ''],
          ['name' => 'Street Private', 'target' => ''],
          ['name' => 'Street Unpaved', 'target' => ''],
          ['name' => 'Tip Lot', 'target' => ''],
          ['name' => 'Unincorporated', 'target' => ''],
          ['name' => 'Zoned for Horses', 'target' => ''],
          ['name' => 'Other', 'target' => '.otherLotFeaturesRes'],
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Lot Features:</label>
      <select class="grid-picker" name="lot_features[]" id="lot_features"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($lot_features as $lot_feature)
          <option value="{{ $lot_feature['name'] }}" data-target="{{ $lot_feature['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->lot_features) && in_array($lot_feature['name'], json_decode($auction->get->lot_features) ?? []) ? 'selected' : ''}}>
            {{ $lot_feature['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherLotFeaturesRes d-none">
        <label class="fw-bold">Lot Features:</label>
        <input type="text" name="otherLotFeature" value="{{isset($auction->get->otherLotFeature) ? $auction->get->otherLotFeature : ''}}" class="form-control has-icon" data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="26">
    <div class="form-group ">
      @php
        $otherStructureOptRes = [
          ['name'=>'Yes','target'=>'.otherStructureResYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
          ['name'=>'No','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']
        ];
      @endphp
      <label class="fw-bold">Other Structures:</label>
      <select class="grid-picker" name="otherStructureOpt" style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($otherStructureOptRes as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="{{$item['icon']}}" class="card flex-row" style="width:calc(33.3% - 10px);" {{isset($auction->get->otherStructureOpt) && $auction->get->otherStructureOpt == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group otherStructureResYes d-none">
      @php
        $otherStructureRes = [
            ['name' => 'Additional Single Family Home', 'target' => '.otherUnitRes'],
            ['name' => 'In-Law- Suite', 'target' => '.otherUnitRes'],
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
            ['name' => 'Other', 'target' => '.otherStructureRes']
          ];
      @endphp
      <select class="grid-picker" name="otherStruct[]" id="otherStucture" style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($otherStructureRes as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->otherStruct) && in_array($item['name'], json_decode($auction->get->otherStruct) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherStructureRes d-none">
        <label class="fw-bold">Other Structures: </label>
        <input type="text" name="otherStructure" value="{{isset($auction->get->otherStructure) ? $auction->get->otherStructure : ''}}" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>
    </div>
    <div class="form-group " id="otherSturctureUnit" style="display: none">
      @php
        $unitStructureRes = [
            ['name' => 'Attached', 'target' => ''],
            ['name' => 'Detached (ADU)', 'target' => ''],
            ['name' => 'Kitchen', 'target' => ''],
            ['name' => 'Kitchenette', 'target' => ''],
            ['name' => 'No Private Entrance', 'target' => ''],
            ['name' => 'Private Entrance', 'target' => ''],
            ['name' => '1 Bed/1 Bath', 'target' => ''],
            ['name' => '1 Bedroom', 'target' => ''],
            ['name' => '2 Bed/1 Bath', 'target' => ''],
            ['name' => '2 Bed/2 Bath', 'target' => ''],
            ['name' => '2 Bedroom', 'target' => ''],
            ['name' => '3 Bed/1 Bath', 'target' => ''],
            ['name' => '3 Bed/2 Bath', 'target' => ''],
            ['name' => '3 Bedroom', 'target' => ''],
            ['name' => '4 Bedroom Or More', 'target' => ''],
            ['name' => '4 Bed/1 Bath', 'target' => ''],
            ['name' => '4 Bed/2 Bath', 'target' => ''],
            ['name' => 'Apartments', 'target' => ''],
            ['name' => 'Efficiency', 'target' => ''],
            ['name' => 'Loft', 'target' => ''],
            ['name' => "Manager's unit", 'target' => ''],
            ['name' => 'Multi-Level', 'target' => ''],
            ['name' => 'Penthouse', 'target' => ''],
            ['name' => 'Studio', 'target' => ''],
            ['name' => 'Other', 'target' => '.otherUnitType']
        ];
      @endphp
      <label class="fw-bold">Unit Type: </label>
      <select class="grid-picker" name="unitStructure[]" style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($unitStructureRes as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->unitStructure) && in_array($item['name'], json_decode($auction->get->unitStructure) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherUnitRes d-none">
        <div class="form-group">
          <label class="fw-bold">Heated Sqft of Additional Structure:</label>
          <input type="text" name="sqftStructure" value="{{isset($auction->get->sqftStructure) ? $auction->get->sqftStructure : ''}}" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
        </div>
        <div class="form-group">
          <label class="fw-bold">Total Sqft of Additional Structure: </label>
          <input type="text" name="totalSqft" value="{{isset($auction->get->totalSqft) ? $auction->get->totalSqft : ''}}" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
        </div>
      </div>
      <div class="form-group d-none otherUnitType">
        <label class="fw-bold">Unit Type:</label>
        <input type="text" name="unitStructureOther" value="{{isset($auction->get->unitStructureOther) ? $auction->get->unitStructureOther : ''}}" class="form-control has-icon" data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="27">
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
        ['name' => 'Other', 'target' => '.otherRoofRes']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Roof:</label>
      <select class="grid-picker" name="roof[]" id="roof" style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($roofs as $roof)
          <option value="{{ $roof['name'] }}" data-target="{{ $roof['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->roof) && in_array($roof['name'], json_decode($auction->get->roof) ?? []) ? 'selected' : ''}}>
            {{ $roof['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherRoofRes d-none" >
        <label class="fw-bold">Roof:</label>
        <input type="text" name="otherRoof" value="{{isset($auction->get->otherRoof) ? $auction->get->otherRoof : ''}}" class="form-control has-icon" data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="28">
    @php
      $road_surface_types = [['name' => 'Asphalt', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Chip And Seal', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Dirt', 'target' => ''], ['name' => 'Gravel', 'target' => ''], ['name' => 'Limerock', 'target' => ''], ['name' => 'Paved', 'target' => ''], ['name' => 'Unimproved', 'target' => ''], ['name' => 'Other', 'target' => '.otherSurfaceRes']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Road Surface Type:</label>
      <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($road_surface_types as $road_surface_type)
          <option value="{{ $road_surface_type['name'] }}"
            data-target="{{ $road_surface_type['target'] }}" class="card flex-row"
            data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);" {{isset($auction->get->road_surface_type) && in_array($road_surface_type['name'], json_decode($auction->get->road_surface_type) ?? []) ? 'selected' : ''}}>
            {{ $road_surface_type['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherSurfaceRes d-none" >
        <label class="fw-bold">Road Surface Type:</label>
        <input type="text" name="otherSurface" value="{{isset($auction->get->otherSurface) ? $auction->get->otherSurface : ''}}" class="form-control has-icon" data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="29">
    <div class="form-group">
      <label class="fw-bold">Garage:</label>
      <select class="grid-picker" name="garage" id="garage" style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $yes_or_no)
          @php
            if ($yes_or_no['name'] == 'Yes') {
                $target = '.garage_spaces';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
            class="card flex-row" style="width:calc(33.3% - 10px);"
            data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>' {{isset($auction->get->garage) && $auction->get->garage == $yes_or_no['name'] ? 'selected' : ''}}>
            {{ $yes_or_no['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group garage_spaces">
      <label class="fw-bold" for="garage_spaces">How many garage spaces?</label>
      <input type="number" name="garage_spaces" value="{{isset($auction->get->garage_spaces) ? $auction->get->garage_spaces : ''}}" id="garage_spaces"
        class="form-control has-icon" data-icon="fa-solid fa-warehouse">
    </div>
    <div class="form-group">
      <label class="fw-bold">Carport:</label>
      <select class="grid-picker" name="carport" id="carport" style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $yes_or_no)
          @php
            if ($yes_or_no['name'] == 'Yes') {
                $target = '.carport_spaces';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
            class="card flex-row" style="width:calc(33.3% - 10px);"
            data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>' {{isset($auction->get->carport) && $auction->get->carport == $yes_or_no['name'] ? 'selected' : ''}}>
            {{ $yes_or_no['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group carport_spaces">
      <label class="fw-bold" for="carport_spaces">How many carport spaces?</label>
      <input type="number" name="carport_spaces" value="{{isset($auction->get->carport_spaces) ? $auction->get->carport_spaces : ''}}" id="carport_spaces"
        class="form-control has-icon " data-icon="fa-solid fa-warehouse">
    </div>
  </div>
  <div class="wizard-step" data-step="30">
    <div class="form-group">
      <label class="fw-bold">Pool:</label>
      <select class="grid-picker" name="pool" id="pool" style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $yes_or_no)
          @php
            if ($yes_or_no['name'] == 'Yes') {
                $target = '.has_pool_residential_and_income';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
            class="card flex-row" style="width:calc(33.3% - 10px);"
            data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>' {{isset($auction->get->pool) && $auction->get->pool == $yes_or_no['name'] ? 'selected' : ''}}>
            {{ $yes_or_no['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    @php
      $private_or_community = [['name' => 'Private', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Community', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
    @endphp

    <div class="form-group has_pool_residential_and_income d-none">
      <select class="grid-picker" name="poolOpt" id="pool" style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($private_or_community as $yes_or_no)
          <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
            class="card flex-row" style="width:calc(33.3% - 10px);"
            data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>' {{isset($auction->get->poolOpt) && $auction->get->poolOpt == $yes_or_no['name'] ? 'selected' : ''}}>
            {{ $yes_or_no['name'] }}
          </option>
        @endforeach
      </select>
    </div>

    <div class="form-group">
      @php
        $viewOpt = [
          ['name' => 'Yes', 'target' => '.viewYesRes','icon'=>'<i class="fa-regular fa-circle-check"></i>'], 
          ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']];
      @endphp
        <label class="fw-bold">View:</label>
        <select class="grid-picker" name="viewOpt" id="view" style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($viewOpt as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
              data-icon="{{$item['icon']}}" class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->viewOpt) && $auction->get->viewOpt == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
    </div>
    <div class="form-group viewYesRes d-none">
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
            ['name' => 'None', 'target' => ''], 
            ['name' => 'Other', 'target' => '.otherViewRes']];
        @endphp
      <label class="fw-bold">View:</label>
      <select class="grid-picker" name="view[]" id="view" style="justify-content: flex-start;"
        multiple>
        <option value="">Select</option>
        @foreach ($view as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->view) && in_array($item['name'], json_decode($auction->get->view) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherViewRes d-none">
        <label class="fw-bold">View:</label>
        <input type="text" name="otherView" value="{{isset($auction->get->otherView) ? $auction->get->otherView : ''}}" class="form-control has-icon"
          data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="31">
    <h4>Land and Tax Information: <span style="font-weight: 400; font-size: 14px">(To find this information check out your
      local property appraiser website and enter the address of the property you are selling)</span></h4>
    
    
    <div class="form-group">
      <label class="fw-bold">Tax ID (Parcel Number):</label>
      <input type="text" name="tax_id" value="{{isset($auction->get->tax_id) ? $auction->get->tax_id : ''}}" id="tax_id" class="form-control has-icon"
        data-icon="fa-solid fa-ruler-combined">
    </div>
    <div class="form-group">
      <label class="fw-bold">Tax Year:</label>
      <input type="number" name="tax_year" value="{{isset($auction->get->tax_year) ? $auction->get->tax_year : ''}}" id="tax_year" class="form-control has-icon"
        data-icon="fa-regular fa-calendar-days" required>
    </div>
    <div class="form-group">
      <label class="fw-bold">Taxes (Annual Amount):</label>
      <input type="number" name="taxes_annual_amount" value="{{isset($auction->get->taxes_annual_amount) ? $auction->get->taxes_annual_amount : ''}}" id="taxes_annual_ammount"
        class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
    </div>
    <div class="form-group ">
      @php
      $additialParcelRes = [
        ['name'=>'Yes','target'=>'.additialParcelResYes1','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
        ['name'=>'No','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']
      ];
    @endphp
      <label class="fw-bold">Additional Parcels:</label>
      <select class="grid-picker" name="additionalParcels" id="sewer"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($additialParcelRes as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="{{$item['icon']}}" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->additionalParcels) && $auction->get->additionalParcels == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group additialParcelResYes1 d-none">
        <div class="form-group">
          <label class="fw-bold">Total Number of Parcels:</label>
          <input type="number" name="total_number_of_parcels" value="{{isset($auction->get->total_number_of_parcels) ? $auction->get->total_number_of_parcels : ''}}" id="total_number_of_parcels"
            class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
        </div>
        <div class="form-group">
          <label class="fw-bold">Additional Tax ID's:</label>
          <input type="text" name="additional_tax_id" value="{{isset($auction->get->additional_tax_id) ? $auction->get->additional_tax_id : ''}}" id="additional_tax_id" class="form-control has-icon"
            data-icon="fa-solid fa-ruler-combined">
        </div>
        <div class="form-group">
          <label class="fw-bold" for="year_built">Year Built:</label>
          <input type="number" name="year_built" value="{{isset($auction->get->year_built) ? $auction->get->year_built : ''}}" id="year_built" class="form-control has-icon "
            data-icon="fa-solid fa-calendar-day" required>
        </div>
        <div class="form-group">
          <label class="fw-bold">Zoning:</label>
          <input type="text" name="zoning" value="{{isset($auction->get->zoning) ? $auction->get->zoning : ''}}" id="zoning"
            class="form-control has-icon" data-icon="fa-solid fa-tag">
        </div>
        <div class="form-group">
          <label class="fw-bold">Legal Description:</label>
          <input type="text" name="legal_description" value="{{isset($auction->get->legal_description) ? $auction->get->legal_description : ''}}" id="legal_description" class="form-control has-icon"
            data-icon="fa-solid fa-tag">
        </div>
        <div class="form-group">
          <label class="fw-bold" for="year_built">Legal Subdivison Name:</label>
          <input type="text" name="legal_subdivison_name" value="{{isset($auction->get->legal_subdivison_name) ? $auction->get->legal_subdivison_name : ''}}" id="legal_subdivison_name"
            class="form-control has-icon " data-icon="fa-solid fa-tag"
            >
        </div>
        <div class="form-group">
          <label class="fw-bold">Flood Zone Code:</label>
          <input type="text" name="flood_zone_code" value="{{isset($auction->get->flood_zone_code) ? $auction->get->flood_zone_code : ''}}" id="flood_zone_code" class="form-control has-icon" data-icon="fa-solid fa-tag">
        </div>
        @php
          $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
        @endphp
        <div class="form-group">
          <label class="fw-bold">Total Acreage:</label>
          <select class="grid-picker" name="total_aceage" id="lot_size" style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($lot_sizes as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                data-icon='<i class="fa-solid fa-ruler-combined"></i>' class="card flex-column"
                style="width:calc(33.3% - 10px);" {{isset($auction->get->total_aceage) && $auction->get->total_aceage == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label class="fw-bold" for="lot_size">Lot Size Square Footage:</label>
          <input type="text" name="lot_size" value="{{isset($auction->get->lot_size) ? $auction->get->lot_size : ''}}" id="lot_size" class="form-control has-icon "
            data-icon="fa-solid fa-ruler-combined" data-msg-required="Please enter Lot Size">
        </div>
        <div class="form-group">
          <label class="fw-bold">Lot Size Acres:</label>
          <input type="text" name="lot_size_acres" value="{{isset($auction->get->lot_size_acres) ? $auction->get->lot_size_acres : ''}}" id="lot_size_acres" class="form-control has-icon"
            data-icon="fa-solid fa-ruler-combined">
        </div>
        <div class="form-group ">
          <label class="fw-bold">Homestead:</label>
          <select class="grid-picker" name="has_homestead" id="sewer"
            style="justify-content: flex-start;" required>
            <option value="">Select</option>
            @foreach ($yes_or_nos as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                style="width:calc(33.3% - 10px);" {{isset($auction->get->has_homestead) && $auction->get->has_homestead == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          @php
            $ccdOpt = [
              ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.anual_cdd_fee_residential_and_income'],
              ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
            ];
          @endphp
          <label class="fw-bold">CDD:</label>
          <select class="grid-picker" name="has_cdd" id="has_cdd" style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($ccdOpt as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->has_cdd) && $auction->get->has_cdd == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="form-group anual_cdd_fee_residential_and_income d-none">
          <label class="fw-bold">Annual CDD Fee:</label>
          <input type="number" name="annual_cdd_fee" value="{{isset($auction->get->annual_cdd_fee) ? $auction->get->annual_cdd_fee : ''}}" id="annual_cdd_fee" class="form-control has-icon"
            data-icon="fa-solid fa-dollar ">
        </div>
        <div class="form-group">
          @php
          $landLeaseOpt = [
              ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.land_lease_fee_residential_and_income'],
              ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
            ];
          @endphp
          <label class="fw-bold">Annual Land Lease Fee:</label>
          <select class="grid-picker" name="has_land_lease" id="has_hoa"
            style="justify-content: flex-start;">
            <option value="">Select</option>
            @foreach ($landLeaseOpt as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->has_land_lease) && $auction->get->has_land_lease == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="form-group land_lease_fee_residential_and_income d-none">
          <label class="fw-bold">Annual Land Lease Fee:</label>
          <input type="number" name="land_lease_fee" value="{{isset($auction->get->land_lease_fee) ? $auction->get->land_lease_fee : ''}}" id="land_lease_fee" class="form-control has-icon"
            data-icon="fa-solid fa-dollar ">
        </div>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="32">
    @php
      $utilitiseRes = [
        ['name' => 'BB/HS Internet Available', 'target' => ''],
        ['name' => 'Cable Available', 'target' => ''],
        ['name' => 'Cable Connected', 'target' => ''],
        ['name' => 'Electricity Available', 'target' => ''],
        ['name' => 'Electricity Connected', 'target' => ''],
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
        ['name' => 'Other', 'target' => '.otherUtilitiseRes'],
      ];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Utilities:</label>
      <select class="grid-picker" name="utilities[]" id="utilities" style="justify-content: flex-start;"
        multiple>
        <option value="">Select</option>
        @foreach ($utilitiseRes as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->utilities) && in_array($item['name'], json_decode($auction->get->utilities) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherUtilitiseRes d-none">
        <label class="fw-bold">Utilities:</label>
        <input type="text" name="otherUtilitise" value="{{isset($auction->get->otherUtilitise) ? $auction->get->otherUtilitise : ''}}" id="legal_description" class="form-control has-icon"
          data-icon="fa-regular fa-check-circle">
      </div>
    </div>

    @php
      $sewerRes = [['name' => 'Aerobic Septic', 'target' => ''],['name' => 'PEP-Holding Tank', 'target' => ''], ['name' => 'Private Sewer', 'target' => ''], ['name' => 'Public Sewer', 'target' => ''], ['name' => 'Septic Needed', 'target' => ''], ['name' => 'Septic Tank', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherSewerRes']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Sewer:</label>
      <select class="grid-picker" name="sewer[]" id="sewer" style="justify-content: flex-start;" multiple >
        <option value="">Select</option>
        @foreach ($sewerRes as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->sewer) && in_array($item['name'], json_decode($auction->get->sewer) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherSewerRes d-none">
        <label class="fw-bold">Sewer:</label>
        <input type="text" name="otherSewer" value="{{isset($auction->get->otherSewer) ? $auction->get->otherSewer : ''}}" class="form-control has-icon"
          data-icon="fa-regular fa-check-circle">
      </div>
    </div>

    @php

      $waterRes = [['name' => 'Canal/Lake For Irrigation', 'target' => ''], ['name' => 'Private', 'target' => ''], ['name' => 'Public', 'target' => ''], ['name' => 'Well', 'target' => ''], ['name' => 'Well Required', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherWaterRes']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Water:</label>
      <select class="grid-picker" name="water[]" id="water12" style="justify-content: flex-start;" multiple >
        <option value="">Select</option>
        @foreach ($waterRes as $water)
          <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water) && in_array($water['name'], json_decode($auction->get->water) ?? []) ? 'selected' : ''}}>
            {{ $water['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherWaterRes d-none">
        <label class="fw-bold">Water:</label>
        <input type="text" name="otherWater" value="{{isset($auction->get->otherWater) ? $auction->get->otherWater : ''}}" id="legal_description" class="form-control has-icon"
          data-icon="fa-regular fa-check-circle">
      </div>
    </div>
  </div>