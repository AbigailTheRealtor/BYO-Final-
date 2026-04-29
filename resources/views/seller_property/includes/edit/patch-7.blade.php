{{-- Vacant Start --}}
<div class="wizard-step" data-step="78">
    @php
      $front_exposures1 = [
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
    <div class="form-group  ">
      <label class="fw-bold">Front Exposure:</label>
      <select class="grid-picker" name="front_exposure_vac" onchange="changeFrontExposure(this.value);"
        id="front_exposure" style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($front_exposures1 as $front_exposure12)
          <option value="{{ $front_exposure12['name'] }}"
            data-icon='<i class="fa-regular fa-circle-check"></i>'
            data-target="{{ $front_exposure12['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->front_exposure_vac) && $auction->get->front_exposure_vac == $front_exposure12['name'] ? 'selected' : ''}}>
            {{ $front_exposure12['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="79">
    @php
      $lot_features = [
          ['name' => 'Brownfield', 'target' => ''],
          ['name' => 'Buildable', 'target' => ''],
          ['name' => 'Central Business District', 'target' => ''],
          ['name' => 'Cleared', 'target' => ''],
          ['name' => 'Coastal Construction Control Line', 'target' => ''],
          ['name' => 'Compact Soil', 'target' => ''],
          ['name' => 'Conservation Area', 'target' => ''],
          ['name' => 'Corner Lot', 'target' => ''],
          ['name' => 'Cul-De-Sac', 'target' => ''],
          ['name' => 'Curb and Gutters', 'target' => ''],
          ['name' => 'Demucked', 'target' => ''],
          ['name' => 'Drainage Canal', 'target' => ''],
          ['name' => 'Environmental Restricted Area', 'target' => ''],
          ['name' => 'Farm', 'target' => ''],
          ['name' => 'Filled', 'target' => ''],
          ['name' => 'Fire Hydrant', 'target' => ''],
          ['name' => 'Fish Breeding Ponds', 'target' => ''],
          ['name' => 'Flag Lot', 'target' => ''],
          ['name' => 'Flood Plain', 'target' => ''],
          ['name' => 'Greenbelt', 'target' => ''],
          ['name' => 'Hilly', 'target' => ''],
          ['name' => 'Historic District', 'target' => ''],
          ['name' => 'Hunting Lease', 'target' => ''],
          ['name' => 'In City Limits', 'target' => ''],
          ['name' => 'In County', 'target' => ''],
          ['name' => 'Industrial Park', 'target' => ''],
          ['name' => 'Interior Lot', 'target' => ''],
          ['name' => 'Irregular Lot', 'target' => ''],
          ['name' => 'Key Lot', 'target' => ''],
          ['name' => 'Landscaped', 'target' => ''],
          ['name' => 'Level/Flat', 'target' => ''],
          ['name' => 'May Need To be Filled', 'target' => ''],
          ['name' => 'Mountainous', 'target' => ''],
          ['name' => 'Near Golf Course', 'target' => ''],
          ['name' => 'Near Marina', 'target' => ''],
          ['name' => 'Near Public Transit', 'target' => ''],
          ['name' => 'Near Railroad Siding', 'target' => ''],
          ['name' => 'On Golf Course', 'target' => ''],
          ['name' => 'Out Parcel', 'target' => ''],
          ['name' => 'Oversized Lot', 'target' => ''],
          ['name' => 'Pasture/Agriculture', 'target' => ''],
          ['name' => 'Private', 'target' => ''],
          ['name' => 'Railroad', 'target' => ''],
          ['name' => 'Reclaimed Land', 'target' => ''],
          ['name' => 'Retention Areas', 'target' => ''],
          ['name' => 'Retention Pond', 'target' => ''],
          ['name' => 'Rolling Slope', 'target' => ''],
          ['name' => 'Room For Pool', 'target' => ''],
          ['name' => 'Rural', 'target' => ''],
          ['name' => 'Seaport', 'target' => ''],
          ['name' => 'Sidewalks', 'target' => ''],
          ['name' => 'Sloped', 'target' => ''],
          ['name' => 'Special Taxing District', 'target' => ''],
          ['name' => 'Stocked Fishing Ponds', 'target' => ''],
          ['name' => 'Street Brick', 'target' => ''],
          ['name' => 'Street Dead-End', 'target' => ''],
          ['name' => 'Street Lights', 'target' => ''],
          ['name' => 'Street One Way', 'target' => ''],
          ['name' => 'Street Paved', 'target' => ''],
          ['name' => 'Street Private', 'target' => ''],
          ['name' => 'Street Unpaved', 'target' => ''],
          ['name' => 'Suburb', 'target' => ''],
          ['name' => 'Tip Lot', 'target' => ''],
          ['name' => 'Turn Around', 'target' => ''],
          ['name' => 'Unincorporated', 'target' => ''],
          ['name' => 'Urban', 'target' => ''],
          ['name' => 'Wetlands', 'target' => ''],
          ['name' => 'Wildlife Sanctuary', 'target' => ''],
          ['name' => 'Wooded', 'target' => ''],
          ['name' => 'Zero Lot Line', 'target' => ''],
          ['name' => 'Zoned for Horses', 'target' => ''],
          ['name' => 'Other', 'target' => '.otherLotFeatureVacant'],
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
      <div class="form-group otherLotFeatureVacant d-none">
        <label class="fw-bold">Lot Features:</label>
        <input type="text" name="otherLotFeatureVac" value="{{isset($auction->get->otherLotFeatureVac) ? $auction->get->otherLotFeatureVac : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="80">
    @php
      $otherStructures = [
          ['name' => 'Barn(s)', 'target' => ''],
          ['name' => 'Billboard', 'target' => ''],
          ['name' => 'Corral(s)', 'target' => ''],
          ['name' => 'Finished RV Port', 'target' => ''],
          ['name' => 'Greenhouse', 'target' => ''],
          ['name' => 'Utility', 'target' => ''],
          ['name' => 'Workshop', 'target' => ''],
          ['name' => 'Other', 'target' => '.otherStructuresVac'],
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
      <div class="form-group otherStructuresVac d-none">
        <label class="fw-bold">Other Structures:</label>
        <input type="text" name="custom_other_structures" value="{{isset($auction->get->custom_other_structures) ? $auction->get->custom_other_structures : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="81">
    <div class="form-group">
      <label class="fw-bold">Current Adjacent Use:</label>
      @php
      $current_adjacent_use = [
        ['name' => 'Church', 'target' => ''], 
        ['name' => 'Commercial', 'target' => ''], 
        ['name' => 'Industrial', 'target' => ''], 
        ['name' => 'Mobile Home Park', 'target' => ''], 
        ['name' => 'Multi-Family', 'target' => ''], 
        ['name' => 'Park', 'target' => ''], 
        ['name' => 'Professional Office', 'target' => ''], 
        ['name' => 'Residential', 'target' => ''], 
        ['name' => 'Retail', 'target' => ''], 
        ['name' => 'School', 'target' => ''], 
        ['name' => 'Vacant', 'target' => '']];
      @endphp
      <select class="grid-picker" name="current_adjacent_use[]" id="lot_features"
        style="justify-content: flex-start;" multiple>
        <option value="">Select</option>
        @foreach ($current_adjacent_use as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);" {{isset($auction->get->current_adjacent_use) && in_array($item['name'], json_decode($auction->get->current_adjacent_use, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="82">
    @php
      $road_frontages = [
        ['name' => 'Access Road', 'target' => ''], 
        ['name' => 'Alley', 'target' => ''], 
        ['name' => 'Business District', 'target' => ''], 
        ['name' => 'City Street', 'target' => ''], 
        ['name' => 'County Road', 'target' => ''], 
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
        ['name' => 'Other', 'target' => '.otherFrontageVacant']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Road Frontage:</label>
      <select class="grid-picker" onclick="changeRoadFrontage(this.value);" name="road_frontage"
        id="road_frontage" style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($road_frontages as $item)
          <option value="{{ $item['name'] }}"
            data-icon='<i class="fa-regular fa-circle-check"></i>'
            data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->road_frontage) && $auction->get->road_frontage == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherFrontageVacant d-none">
        <label class="fw-bold">Road Frontage:</label>
        <input type="text" name="otherFrontage" value="{{isset($auction->get->otherFrontage) ? $auction->get->otherFrontage : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="83">
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
        ['name' => 'Other', 'target' => '.otherSurfaceVacant']];
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
      <div class="form-group otherSurfaceVacant d-none">
        <label class="fw-bold">Road Surface Type:</label>
        <input type="text" name="otherSurfaceVac" value="{{isset($auction->get->otherSurfaceVac) ? $auction->get->otherSurfaceVac : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="84">
    @php
      $utilitiseRes = [
        ['name' => 'BB/HS Internet Available', 'target' => ''],
        ['name' => 'BB/HS Internet Capable', 'target' => ''],
        ['name' => 'Cable Available', 'target' => ''],
        ['name' => 'Cable Connected', 'target' => ''],
        ['name' => 'Electrical Nearby', 'target' => ''],
        ['name' => 'Electricity Available', 'target' => ''],
        ['name' => 'Fiber Optics', 'target' => ''],
        ['name' => 'Fire Hydrant', 'target' => ''],
        ['name' => 'Mini Sewer', 'target' => ''],
        ['name' => 'Natural Gas Available', 'target' => ''],
        ['name' => 'Phone Available', 'target' => ''],
        ['name' => 'Private', 'target' => ''],
        ['name' => 'Propane', 'target' => ''],
        ['name' => 'Public', 'target' => ''],
        ['name' => 'Sewer Available', 'target' => ''],
        ['name' => 'Sewer Connected', 'target' => ''],
        ['name' => 'Sewer Nearby', 'target' => ''],
        ['name' => 'Sprinkler Meter', 'target' => ''],
        ['name' => 'Sprinkler Recycled', 'target' => ''],
        ['name' => 'Sprinkler Well', 'target' => ''],
        ['name' => 'Street Lights', 'target' => ''],
        ['name' => 'Telephone Nearby', 'target' => ''],
        ['name' => 'Underground Utilities', 'target' => ''],
        ['name' => 'Utility Pole', 'target' => ''],
        ['name' => 'Water - Multiple Meters', 'target' => ''],
        ['name' => 'Water Available', 'target' => ''],
        ['name' => 'Water Connected', 'target' => ''],
        ['name' => 'Water Nearby', 'target' => ''],
        ['name' => 'Other', 'target' => '.otherUtilitiseVacant'],
      ];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Utilities:</label>
      <select class="grid-picker" name="utilities[]" id="utilities" style="justify-content: flex-start;"
        multiple required>
        <option value="">Select</option>
        @foreach ($utilitiseRes as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->utilities) && in_array($item['name'], json_decode($auction->get->utilities, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherUtilitiseVacant d-none">
        <label class="fw-bold">Utilities:</label>
        <input type="text" name="otherUtilitiseVac" value="{{isset($auction->get->otherUtilitiseVac) ? $auction->get->otherUtilitiseVac : ''}}" id="legal_description" class="form-control has-icon"
          data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>

    @php
      $sewerVacant = [
        ['name' => 'PEP-Holding Tank', 'target' => ''], 
        ['name' => 'Private Sewer', 'target' => ''], 
        ['name' => 'Public Sewer', 'target' => ''], 
        ['name' => 'Septic Needed', 'target' => ''], 
        ['name' => 'Septic Tank', 'target' => ''], 
        ['name' => 'None', 'target' => ''], 
        ['name' => 'Other', 'target' => '.otherSewerVacant']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Sewer:</label>
      <select class="grid-picker" name="sewer[]" id="sewer" style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($sewerVacant as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->sewer) && in_array($item['name'], json_decode($auction->get->sewer, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherSewerVacant d-none">
        <label class="fw-bold">Sewer:</label>
        <input type="text" name="otherSewerVac" value="{{isset($auction->get->otherSewerVac) ? $auction->get->otherSewerVac : ''}}" class="form-control has-icon"
          data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>

    @php
      $waterVacant = [
        ['name' => 'Canal/Lake For Irrigation', 'target' => ''], 
        ['name' => 'Private', 'target' => ''], 
        ['name' => 'Public', 'target' => ''], 
        ['name' => 'Well', 'target' => ''],
        ['name' => 'Well Required ', 'target' => ''],
        ['name' => 'None', 'target' => ''], 
        ['name' => 'Other', 'target' => '.otherWaterVacant']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Water:</label>
      <select class="grid-picker" name="water[]" id="water12" style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($waterVacant as $water)
          <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->water) && in_array($water['name'], json_decode($auction->get->water, true) ?? []) ? 'selected' : ''}}>
            {{ $water['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherWaterVacant d-none">
        <label class="fw-bold">Water:</label>
        <input type="text" name="otherWaterVac" value="{{isset($auction->get->otherWaterVac) ? $auction->get->otherWaterVac : ''}}" id="legal_description" class="form-control has-icon"
          data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>

    
  </div>
  <div class="wizard-step" data-step="85">
    <h4>Land and Tax Information:</h4>
    <div class="form-group">
      <label class="fw-bold">Tax ID (Parcel Number):</label>
      <input type="text" name="tax_id_vac" value="{{isset($auction->get->tax_id_vac) ? $auction->get->tax_id_vac : ''}}" id="tax_id" class="form-control has-icon"
        data-icon="fa-solid fa-ruler-combined">
    </div>
    <div class="form-group">
      <label class="fw-bold">Tax Year:</label>
      <input type="number" name="tax_year_vac" value="{{isset($auction->get->tax_year_vac) ? $auction->get->tax_year_vac : ''}}" id="tax_year" class="form-control has-icon"
        data-icon="fa-regular fa-calendar-days" >
    </div>
    <div class="form-group">
      <label class="fw-bold">Taxes (Annual Amount):</label>
      <input type="number" name="taxes_annual_amount_vac" value="{{isset($auction->get->taxes_annual_amount_vac) ? $auction->get->taxes_annual_amount_vac : ''}}" id="taxes_annual_ammount"
        class="form-control has-icon" data-icon="fa-solid fa-dollar">
    </div>
    <div class="form-group ">
      @php
      $additialParcelVacant = 
      [
        ['name'=>'Yes','target'=>'.additialParcelVacantYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
        ['name'=>'No','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']
      ];
      @endphp
      <label class="fw-bold">Additional Parcels:</label>
      <select class="grid-picker" name="additionalParcelsVac" id="sewer"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($additialParcelVacant as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon="{{$item['icon']}}" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->additionalParcelsVac) && $auction->get->additionalParcelsVac == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group additialParcelVacantYes d-none">
        <div class="form-group">
          <label class="fw-bold">Additional Tax ID's:</label>
          <input type="text" name="additional_tax_id_vac" value="{{isset($auction->get->additional_tax_id_vac) ? $auction->get->additional_tax_id_vac : ''}}" id="additional_tax_id" class="form-control has-icon"
            data-icon="fa-solid fa-ruler-combined">
        </div>
      </div>
    </div>
    <div class="form-group">
      <label class="fw-bold">Total Number of Parcels:</label>
      <input type="number" name="total_number_of_parcels_vac" value="{{isset($auction->get->total_number_of_parcels_vac) ? $auction->get->total_number_of_parcels_vac : ''}}" id="total_number_of_parcels"
        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
    </div>
    <div class="form-group">
      <label class="fw-bold">Zoning:</label>
      <input type="text" name="zoning_vac" value="{{isset($auction->get->zoning_vac) ? $auction->get->zoning_vac : ''}}" id="zoning"
        class="form-control has-icon" data-icon="fa-solid fa-tag">
    </div>
    <div class="form-group">
      <label class="fw-bold">Legal Description:</label>
      <input type="text" name="legal_description_vac" value="{{isset($auction->get->legal_description_vac) ? $auction->get->legal_description_vac : ''}}" id="legal_description" class="form-control has-icon"
        data-icon="fa-solid fa-tag">
    </div>
    <div class="form-group">
      <label class="fw-bold" for="year_built">Legal Subdivison Name:</label>
      <input type="text" name="legal_subdivison_name_vac" value="{{isset($auction->get->legal_subdivison_name_vac) ? $auction->get->legal_subdivison_name_vac : ''}}" id="legal_subdivison_name"
        class="form-control has-icon " data-icon="fa-solid fa-tag"
        >
    </div>
    @php
      $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Total Acreage:</label>
      <select class="grid-picker" name="total_aceage_vac" id="lot_size" style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($lot_sizes as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            data-icon='<i class="fa-solid fa-ruler-combined"></i>' class="card flex-column"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->total_aceage_vac) && $auction->get->total_aceage_vac == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Flood Zone Code:</label>
      <input type="text" name="flood_zone_code_vac" value="{{isset($auction->get->flood_zone_code_vac) ? $auction->get->flood_zone_code_vac : ''}}" id="flood_zone_code" class="form-control has-icon" data-icon="fa-solid fa-tag">
    </div>
    <div class="form-group">
      <label class="fw-bold">Front Footage:</label>
      <input type="text" name="front_footage" value="{{isset($auction->get->front_footage) ? $auction->get->front_footage : ''}}" id="flood_zone_code" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
    </div>
    <div class="form-group">
      <label class="fw-bold">Lot Size Square Footage:</label>
      <textarea name="lot_size_vac" value="{{isset($auction->get->lot_size_vac) ? $auction->get->lot_size_vac : ''}}" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" rows="3"></textarea>
    </div>
    <div class="form-group">
      <label class="fw-bold">Lot Size Acres:</label>
      <input type="text" name="lot_size_acres_vac" value="{{isset($auction->get->lot_size_acres_vac) ? $auction->get->lot_size_acres_vac : ''}}" id="lot_size_acres" class="form-control has-icon"
        data-icon="fa-solid fa-ruler-combined">
    </div>
    <div class="form-group">
      <label class="fw-bold">Lot Dimensions:</label>
      <input type="text" name="lot_dimensions" value="{{isset($auction->get->lot_dimensions) ? $auction->get->lot_dimensions : ''}}" id="lot_dimensions" class="form-control has-icon"
        data-icon="fa-solid fa-ruler-combined">
    </div>
  </div>