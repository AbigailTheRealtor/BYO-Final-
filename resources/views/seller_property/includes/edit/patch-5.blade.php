<div class="wizard-step" data-step="55">
    @php
      $lot_features = [
          ['name' => 'Central Business District', 'target' => ''],
          ['name' => 'Corner Lot', 'target' => ''],
          ['name' => 'Cul-De-Sac', 'target' => ''],
          ['name' => 'Curb and Gutters', 'target' => ''],
          ['name' => 'Drainage Canal', 'target' => ''],
          ['name' => 'Farm', 'target' => ''],
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
          ['name' => 'Riparian Rights', 'target' => ''],
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
          ['name' => 'Zoned for Horse', 'target' => ''],
          ['name' => 'Other', 'target' => '.otherLotFeatureCommercial'],
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
            style="width:calc(33.3% - 10px);" {{isset($auction->get->lot_features) && in_array($lot_feature['name'], json_decode($auction->get->lot_features, true) ?? []) ? 'selected' : ''}}>
            {{ $lot_feature['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherLotFeatureCommercial d-none">
        <label class="fw-bold">Lot Features:</label>
        <input type="text" name="otherLotFeatureCom" value="{{isset($auction->get->otherLotFeatureCom) ? $auction->get->otherLotFeatureCom : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="56">
    @php
      $otherStructures = [
          ['name' => 'Airplane Hangar', 'target' => ''],
          ['name' => 'Annex', 'target' => ''],
          ['name' => 'Containers', 'target' => ''],
          ['name' => 'Garage(s)', 'target' => ''],
          ['name' => 'Maintenance', 'target' => ''],
          ['name' => 'Outbuilding', 'target' => ''],
          ['name' => 'Security Trailer', 'target' => ''],
          ['name' => 'Storage', 'target' => ''],
          ['name' => 'Workshop', 'target' => ''],
          ['name' => 'Other', 'target' => '.otherStructures'],
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Other Structures:</label>
      <select class="grid-picker" name="other_structures[]" id="lot_features"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($otherStructures as $structure)
          <option value="{{ $structure['name'] }}" data-target="{{ $structure['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->other_structures) && in_array($structure['name'], json_decode($auction->get->other_structures, true) ?? []) ? 'selected' : ''}}>
            {{ $structure['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherStructures d-none">
        <label class="fw-bold">Other Structures:</label>
        <input type="text" name="custom_other_structures" value="{{isset($auction->get->custom_other_structures) ? $auction->get->custom_other_structures : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="57">
    @php
      $building_features = [
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
          ['name' => 'Other', 'target' => '.otherBuildingCommercial'],
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Building Features:</label>
      <select class="grid-picker" name="building_features[]" id="building_feature"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($building_features as $item)
          <option value="{{ $item['name'] }}"
            data-icon='<i class="fa-regular fa-circle-check"></i>'
            data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->building_features) && in_array($item['name'], json_decode($auction->get->building_features, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherBuildingCommercial d-none">
        <label class="fw-bold">Building Features:</label>
        <input type="text" name="otherBuilding" value="{{isset($auction->get->otherBuilding) ? $auction->get->otherBuilding : ''}}" placeholder="" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="58">
    @php
      $adjoining_properties = [['name' => 'Airport', 'target' => ''], ['name' => 'Church', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Hotel/Motel', 'target' => ''], ['name' => 'Industrial', 'target' => ''], ['name' => 'Multi-Family', 'target' => ''], ['name' => 'Natural State', 'target' => ''], ['name' => 'Professional Office', 'target' => ''], ['name' => 'Railroad', 'target' => ''], ['name' => 'Residential', 'target' => ''], ['name' => 'School', 'target' => ''], ['name' => 'Undeveloped', 'target' => ''], ['name' => 'Vacant', 'target' => ''], ['name' => 'Waterway', 'target' => ''], ['name' => 'Other', 'target' => '.otherAdjoiningCommercial']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Adjoining Property:</label>
      <select class="grid-picker" name="adjoining_property[]"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($adjoining_properties as $item)
          <option value="{{ $item['name'] }}"
            data-icon='<i class="fa-regular fa-circle-check"></i>'
            data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->adjoining_property) && in_array($item['name'], json_decode($auction->get->adjoining_property, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherAdjoiningCommercial d-none">
        <label class="fw-bold">Adjoining Property:</label>
        <input type="text" name="otherAdjoining" value="{{isset($auction->get->otherAdjoining) ? $auction->get->otherAdjoining : ''}}" placeholder="" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="59">
    @php
      $roofs = [['name' => 'Built-Up', 'target' => ''], ['name' => 'Cement', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Membrane', 'target' => ''], ['name' => 'Metal', 'target' => ''], ['name' => 'Roof Over', 'target' => ''], ['name' => 'Shake', 'target' => ''], ['name' => 'Shingle', 'target' => ''], ['name' => 'Slate', 'target' => ''], ['name' => 'Tile', 'target' => ''], ['name' => 'Other', 'target' => '.otherRoofCommercial']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Roof:</label>
      <select class="grid-picker" name="roof[]" id="roof" style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($roofs as $roof)
          <option value="{{ $roof['name'] }}" data-target="{{ $roof['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->roof) && in_array($roof['name'], json_decode($auction->get->roof, true) ?? []) ? 'selected' : ''}}>
            {{ $roof['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherRoofCommercial d-none" >
        <label class="fw-bold">Roof:</label>
        <input type="text" name="otherRoofCom" value="{{isset($auction->get->otherRoofCom) ? $auction->get->otherRoofCom : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="60">
    @php
      $road_surface_types = [['name' => 'Asphalt', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Chip And Seal', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Dirt', 'target' => ''], ['name' => 'Gravel', 'target' => ''], ['name' => 'Limerock', 'target' => ''], ['name' => 'Paved', 'target' => ''], ['name' => 'Unimproved', 'target' => ''], ['name' => 'Other', 'target' => '.otherSurfaceCommercial']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Road Surface Type:</label>
      <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
        style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($road_surface_types as $road_surface_type)
          <option value="{{ $road_surface_type['name'] }}"
            data-target="{{ $road_surface_type['target'] }}" class="card flex-row"
            data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);" {{isset($auction->get->road_surface_type) && in_array($road_surface_type['name'], json_decode($auction->get->road_surface_type, true) ?? []) ? 'selected' : ''}}>
            {{ $road_surface_type['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherSurfaceCommercial d-none" >
        <label class="fw-bold">Road Surface Type:</label>
        <input type="text" name="otherSurfaceCom" value="{{isset($auction->get->otherSurfaceCom) ? $auction->get->otherSurfaceCom : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="61">
    @php
      $road_frontage = [['name' => 'Access Road', 'target' => ''], ['name' => 'Alley', 'target' => ''], ['name' => 'Business District', 'target' => ''], ['name' => 'City Street', 'target' => ''], ['name' => 'County Road', 'target' => ''], ['name' => 'Divided Highway', 'target' => ''], ['name' => 'Easement', 'target' => ''], ['name' => 'Highway', 'target' => ''], ['name' => 'Interchange', 'target' => ''], ['name' => 'Interstate', 'target' => ''], ['name' => 'Main Thoroughfare', 'target' => ''], ['name' => 'Private Road', 'target' => ''], ['name' => 'Rail', 'target' => ''], ['name' => 'State Road', 'target' => ''], ['name' => 'Turn Lanes', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherFrontageCommercial']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Road Frontage:</label>
      <select class="grid-picker" name="road_frontage[]" id="road_frontage"
        style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($road_frontage as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);" {{isset($auction->get->road_frontage) && in_array($item['name'], json_decode($auction->get->road_frontage, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherFrontageCommercial d-none" >
        <label class="fw-bold">Road Frontage:</label>
        <input type="text" name="otherFrontage" value="{{isset($auction->get->otherFrontage) ? $auction->get->otherFrontage : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="62">
    @php
      $garage_parking_feature = [['name' => '1 to 5 Spaces', 'target' => ''], ['name' => '6 to 12 Spaces', 'target' => ''], ['name' => '13 to 18 Spaces', 'target' => ''], ['name' => '19 to 30 Spaces', 'target' => ''], ['name' => 'Airplane Hangar', 'target' => ''], ['name' => 'Common', 'target' => ''], ['name' => 'Curb Parking', 'target' => ''], ['name' => 'Deeded', 'target' => ''], ['name' => 'Electric Vehicle Charging Station(s)', 'target' => ''], ['name' => 'Ground Level', 'target' => ''], ['name' => 'Lighted', 'target' => ''], ['name' => 'Over 30 Spaces', 'target' => ''], ['name' => 'Secured', 'target' => ''], ['name' => 'Under Building', 'target' => ''], ['name' => 'Underground', 'target' => ''], ['name' => 'Valet', 'target' => ''],['name' => 'RV Parking', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherGarageFeatureCommercial']];
    @endphp

    <div class="form-group ">
      <label class="fw-bold">Garage/Parking Features:</label>
      <select class="grid-picker" name="garage_parking_feature[]" id="garage_parking_feature"
        style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($garage_parking_feature as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            data-icon="<i class='fa-solid fa-warehouse'></i>" style="width:calc(33.3% - 10px);" {{isset($auction->get->garage_parking_feature) && in_array($item['name'], json_decode($auction->get->garage_parking_feature, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherGarageFeatureCommercial d-none" >
        <label class="fw-bold">Garage/Parking Features:</label>
        <input type="text" name="otherGarageFeature" value="{{isset($auction->get->otherGarageFeature) ? $auction->get->otherGarageFeature : ''}}" class="form-control has-icon" data-icon="fa-solid fa-warehouse" required>
      </div>
    </div>
  </div>  
  <div class="wizard-step" data-step="63">
    <h4>Land and Tax Information:</h4>
    <div class="form-group">
      <label class="fw-bold">Tax ID (Parcel Number):</label>
      <input type="text" name="tax_id_com" value="{{isset($auction->get->tax_id_com) ? $auction->get->tax_id_com : ''}}" id="tax_id" class="form-control has-icon"
        data-icon="fa-solid fa-ruler-combined">
    </div>
    <div class="form-group">
      <label class="fw-bold">Tax Year:</label>
      <input type="text" name="tax_year_com" value="{{isset($auction->get->tax_year_com) ? $auction->get->tax_year_com : ''}}" id="tax_year" class="form-control has-icon"
        data-icon="fa-regular fa-calendar-days" >
    </div>
    <div class="form-group">
      <label class="fw-bold">Taxes (Annual Amount):</label>
      <input type="text" name="taxes_annual_amount_com" value="{{isset($auction->get->taxes_annual_amount_com) ? $auction->get->taxes_annual_amount_com : ''}}" id="taxes_annual_ammount"
        class="form-control has-icon" data-icon="fa-solid fa-dollar">
    </div>
    <div class="form-group ">
      @php
        $additionalParcelsCommercial = [
          ['name' => 'Yes', 'target' => '.additionalYes', 'icon' => 'fa-regular fa-circle-check'], 
          ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']
        ];
      @endphp
      <label class="fw-bold">Additional Parcels:</label>
      <select class="grid-picker" name="additionalParcelsCom" style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($additionalParcelsCommercial as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->additionalParcelsCom) && $auction->get->additionalParcelsCom == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Total Number of Parcels:</label>
      <input type="text" name="total_number_of_parcels_com" value="{{isset($auction->get->total_number_of_parcels_com) ? $auction->get->total_number_of_parcels_com : ''}}" id="total_number_of_parcels"
        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
    </div>
    <div class="form-group additionalYes d-none">
      <label class="fw-bold">Additional Tax ID's:</label>
      <input type="text" name="additional_tax_id_com" value="{{isset($auction->get->additional_tax_id_com) ? $auction->get->additional_tax_id_com : ''}}" id="additional_tax_id" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
    </div>
    <div class="form-group">
      <label class="fw-bold" for="year_built">Year Built:</label>
      <input type="number" name="year_built_com" value="{{isset($auction->get->year_built_com) ? $auction->get->year_built_com : ''}}" id="year_built"
        class="form-control has-icon " data-icon="fa-solid fa-calendar-day">
    </div>
    <div class="form-group">
      <label class="fw-bold">Zoning:</label>
      <input type="text" name="zoning_com" value="{{isset($auction->get->zoning_com) ? $auction->get->zoning_com : ''}}" id="zoning" class="form-control has-icon" data-icon="fa-solid fa-tag">
    </div>
    <div class="form-group">
      <label class="fw-bold">Legal Description:</label>
      <input type="text" name="legal_description_com" value="{{isset($auction->get->legal_description_com) ? $auction->get->legal_description_com : ''}}" id="legal_description" class="form-control has-icon" data-icon="fa-solid fa-tag">
    </div>
    <div class="form-group">
      <label class="fw-bold" for="year_built">Legal Subdivison:</label>
      <input type="text" name="legal_subdivison_name_com" value="{{isset($auction->get->legal_subdivison_name_com) ? $auction->get->legal_subdivison_name_com : ''}}" id="legal_subdivison_name"
        class="form-control has-icon " data-icon="fa-solid fa-tag" >
    </div>
    @php
    $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Total Acreage:</label>
      <select class="grid-picker" name="total_aceage_com" id="lot_size"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($lot_sizes as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon='<i class="fa-solid fa-ruler-combined"></i>' class="card flex-column"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->total_aceage_com) && $auction->get->total_aceage_com == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Flood Zone Code:</label>
      <input type="text" name="flood_zone_code_com" value="{{isset($auction->get->flood_zone_code_com) ? $auction->get->flood_zone_code_com : ''}}"
        class="form-control has-icon" data-icon="fa-solid fa-tag">
    </div>
    <div class="form-group">
      <label class="fw-bold" for="lot_size">Lot Size Square Footage:</label>
      <input type="text" name="lot_size_com" value="{{isset($auction->get->lot_size_com) ? $auction->get->lot_size_com : ''}}" id="lot_size" class="form-control has-icon "
        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please enter Lot Size">
    </div>
    <div class="form-group">
      <label class="fw-bold">Lot Size Acres:</label>
      <input type="text" name="lot_size_acres_com" value="{{isset($auction->get->lot_size_acres_com) ? $auction->get->lot_size_acres_com : ''}}" id="lot_size_acres" class="form-control has-icon"
        data-icon="fa-solid fa-ruler-combined">
    </div>     
  </div>
  <div class="wizard-step" data-step="64">
    <div class="form-group">
      <label class="fw-bold">Is the property in a flood zone?</label>
      <select class="grid-picker" name="is_in_flood_zone" id="is_in_flood_zone"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          @php
            if ($item['name'] == 'Yes') {
                $target = '.floodZoonYesCommercial';
            } else {
                $target = '';
            }
          @endphp
          <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->is_in_flood_zone) && $auction->get->is_in_flood_zone == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    
  </div>
  <div class="wizard-step" data-step="65">
    @php
      $utilitiseCommercial = [
        ['name' => 'BB/HS Internet Capable', 'target' => ''],
        ['name' => 'Electrical Nearby', 'target' => ''],
        ['name' => 'Electricity Available', 'target' => ''],
        ['name' => 'Emergency Power', 'target' => ''],
        ['name' => 'Natural Gas Available', 'target' => ''],
        ['name' => 'Phone Available', 'target' => ''],
        ['name' => 'Private', 'target' => ''],
        ['name' => 'Public', 'target' => ''],
        ['name' => 'Sewer Nearby', 'target' => ''],
        ['name' => 'Solar', 'target' => ''],
        ['name' => 'Telephone Nearby', 'target' => ''],
        ['name' => 'Underground Utilities', 'target' => ''],
        ['name' => 'Water Connected', 'target' => ''],
        ['name' => 'Water Nearby', 'target' => ''],
        ['name' => 'None', 'target' => ''],
        ['name' => 'Other', 'target' => '.otherUtilitiseCommercial'],
      ];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Utilities:</label>
      <select class="grid-picker" name="utilities[]" id="utilities" style="justify-content: flex-start;"
        multiple>
        <option value="">Select</option>
        @foreach ($utilitiseCommercial as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->utilities) && in_array($item['name'], json_decode($auction->get->utilities, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherUtilitiseCommercial d-none">
        <label class="fw-bold">Utilities:</label>
        <input type="text" name="otherUtilitiseCom" value="{{isset($auction->get->otherUtilitiseCom) ? $auction->get->otherUtilitiseCom : ''}}" id="legal_description" class="form-control has-icon"
          data-icon="fa-regular fa-circle-check">
      </div>
    </div>
    @php
    $sewerCommercial = [['name' => 'Aerobic Septic', 'target' => ''],['name' => 'PEP-Holding Tank', 'target' => ''], ['name' => 'Private Sewer', 'target' => ''], ['name' => 'Public Sewer', 'target' => ''], ['name' => 'Septic Needed', 'target' => ''], ['name' => 'Septic Tank', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherSewerCommercial']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Sewer:</label>
      <select class="grid-picker" name="sewer[]" id="sewer" style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($sewerCommercial as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->sewer) && in_array($item['name'], json_decode($auction->get->sewer, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherSewerCommercial d-none">
        <label class="fw-bold">Sewer:</label>
        <input type="text" name="otherSewerCom" value="{{isset($auction->get->otherSewerCom) ? $auction->get->otherSewerCom : ''}}" class="form-control has-icon"
          data-icon="fa-regular fa-circle-check">
      </div>
    </div>
    @php
      $waterCommercial = [
        ['name' => 'Canal/Lake For Irrigation', 'target' => ''], 
        ['name' => 'Private', 'target' => ''], 
        ['name' => 'Public', 'target' => ''], 
        ['name' => 'Well', 'target' => ''],
        ['name' => 'Well Required', 'target' => ''],
        ['name' => 'None', 'target' => ''], 
        ['name' => 'Other', 'target' => '.otherWaterCommercial']
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Water:</label>
      <select class="grid-picker" name="water[]" id="water12" style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($waterCommercial as $water)
          <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water) && in_array($water['name'], json_decode($auction->get->water, true) ?? []) ? 'selected' : ''}}>
            {{ $water['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherWaterCommercial d-none">
        <label class="fw-bold">Water:</label>
        <input type="text" name="otherWaterCom" value="{{isset($auction->get->otherWaterCom) ? $auction->get->otherWaterCom : ''}}" id="legal_description" class="form-control has-icon"
          data-icon="fa-regular fa-circle-check">
      </div>
    </div>
  </div>