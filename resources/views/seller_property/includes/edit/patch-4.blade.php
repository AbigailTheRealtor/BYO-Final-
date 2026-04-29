{{-- commercial/business Start --}}
<div class="wizard-step" data-step="44">
  @php
    $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.custom_bathrooms', 'name' => 'Other']];
  @endphp
    <div class="form-group">
      <label class="fw-bold">Bathrooms:</label>
      <select class="grid-picker" name="bathroomsCom" id="bathrooms" style="">
        <option value="">Select</option>
        @foreach ($bathrooms as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-column" style="width:calc(20% - 10px);"
            data-icon='<i class="fa-solid fa-bath"></i>' {{isset($auction->get->bathroomsCom) && $auction->get->bathroomsCom == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group main custom_bathrooms d-none">
      <label class="fw-bold">Bathrooms:</label>
      <input type="number" name="custom_bathrooms_com" value="{{isset($auction->get->custom_bathrooms_com) ? $auction->get->custom_bathrooms_com : ''}}" id="custom_bathrooms" class="form-control has-icon"
        data-icon="fa-solid fa-bath">
    </div>
  </div>
  <div class="wizard-step" data-step="45">
    <div class="row">
      <div class="form-group col-md-4">
        <label class="fw-bold">Unit Type</label>
        <input type="text" name="unit_type" value="{{isset($auction->get->unit_type) ? $auction->get->unit_type : ''}}" id="unit_type" class="form-control has-icon"
          placeholder="Unit Type" data-icon="fa-solid fa-ruler-combined">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Sqt Ft Heated</label>
        <input type="number" name="sqt_ft_heated" value="{{isset($auction->get->sqt_ft_heated) ? $auction->get->sqt_ft_heated : ''}}" id="sqt_ft_heated" placeholder="Sqt Ft Heated"
          class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Number of Units</label>
        <input type="number" name="number_of_units" value="{{isset($auction->get->number_of_units) ? $auction->get->number_of_units : ''}}" id="number_of_units" placeholder="Number of Units"
          class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Expected Rent</label>
        <input type="number" name="expected_rent" value="{{isset($auction->get->expected_rent) ? $auction->get->expected_rent : ''}}" id="expected_rent" placeholder="Expected Rent"
          class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Garage Spaces</label>
        <input type="number" name="garage_spaces_unit" value="{{isset($auction->get->garage_spaces_unit) ? $auction->get->garage_spaces_unit : ''}}" id="garage_spaces_unit"
          placeholder="Garage Spaces" class="form-control has-icon" data-icon="fa-solid fa-warehouse">
      </div>
      <div class="form-group ">
        <label class="fw-bold">Garage Attribute:</label>
        <select class="grid-picker" name="garage_attribute" id="garage_attribute"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($yes_or_nos as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->garage_attribute) && $auction->get->garage_attribute == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group col-md-12">
        <label class="fw-bold">Unit Type of Description:</label>
        <textarea name="unit_type_of_description" id="unit_type_of_description" class="form-control" cols="30"
          rows="10">{{isset($auction->get->unit_type_of_description) ? $auction->get->unit_type_of_description : ''}}</textarea>
      </div>

      <div class="form-group col-md-4">
        <label class="fw-bold">Annual Gross Income</label>
        <input type="number" name="annual_gross_income" value="{{isset($auction->get->annual_gross_income) ? $auction->get->annual_gross_income : ''}}" id="garage_attribute"
          placeholder="Annual Gross Income" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Total Monthly Rent</label>
        <input type="number" name="total_monthly_rent" value="{{isset($auction->get->total_monthly_rent) ? $auction->get->total_monthly_rent : ''}}" id="garage_attribute"
          placeholder="Total Monthly Rent" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Total Monthly Expenses</label>
        <input type="number" name="total_monthly_expenses" value="{{isset($auction->get->total_monthly_expenses) ? $auction->get->total_monthly_expenses : ''}}" id="garage_attribute"
          placeholder="Total Monthly Expenses" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Length of Lease</label>
        <input type="text" name="lease_terms" value="{{isset($auction->get->lease_terms) ? $auction->get->lease_terms : ''}}" id="garage_attribute" placeholder="Length of Lease"
          class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Annual Net Income</label>
        <input type="number" name="annual_net_income" value="{{isset($auction->get->annual_net_income) ? $auction->get->annual_net_income : ''}}" id="garage_attribute"
          placeholder="Annual Net Income" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Est Annual Market Income</label>
        <input type="text" name="est_annual_market_income" value="{{isset($auction->get->est_annual_market_income) ? $auction->get->est_annual_market_income : ''}}" id="garage_attribute"
          placeholder="Est Annual Market Income" class="form-control has-icon"
          data-icon="fa-solid fa-ruler-combined">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Annual Expenses</label>
        <input type="text" name="annual_expenses" value="{{isset($auction->get->annual_expenses) ? $auction->get->annual_expenses : ''}}" id="garage_attribute"
          class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>
      @php
        $terms_of_leases = [['name' => 'Gross Lease', 'target' => ''], ['name' => 'Net Lease', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pass Throughs', 'target' => ''], ['name' => 'Purchase Options', 'target' => ''], ['name' => 'Renewal Option', 'target' => 'otherTermLeaseVacant']];
      @endphp
      <div class="form-group road_frontage_next_hide ">
        <label class="fw-bold">Terms of Lease:</label>
        <select class="grid-picker" name="terms_of_lease" id="terms_of_lease"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($terms_of_leases as $terms_of_lease)
            <option value="{{ $terms_of_lease['name'] }}" data-target="{{ $terms_of_lease['target'] }}"
              class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
              style="width:calc(33.3% - 10px);" {{isset($auction->get->terms_of_lease) && $auction->get->terms_of_lease == $terms_of_lease['name'] ? 'selected' : ''}}>
              {{ $terms_of_lease['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group otherTermLeaseVacant d-none">
          <label class="fw-bold">Terms of Lease:</label>
          <input type="text" name="otherTermLease" value="{{isset($auction->get->otherTermLease) ? $auction->get->otherTermLease : ''}}" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
        </div>
      </div>
      @php
        $tenant_pays = [['name' => 'Association Fees', 'target' => ''], ['name' => 'Electricity', 'target' => ''], ['name' => 'Parking Fee', 'target' => ''], ['name' => 'Sewer', 'target' => ''], ['name' => 'Trash Collection', 'target' => ''], ['name' => 'Water', 'target' => ''],['name' => 'Gas', 'target' => ''],['name' => 'Other', 'target' => '.otherTenantPayVacant']];
      @endphp
      <div class="form-group road_frontage_next_hide ">
        <label class="fw-bold">Tenant Pays:</label>
        <select class="grid-picker" name="tenant_pays" id="terms_of_lease"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($tenant_pays as $tenant_pay)
            <option value="{{ $tenant_pay['name'] }}" data-target="{{ $tenant_pay['target'] }}"
              class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
              style="width:calc(33.3% - 10px);" {{isset($auction->get->tenant_pays) && $auction->get->tenant_pays == $tenant_pay['name'] ? 'selected' : ''}}>
              {{ $tenant_pay['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group otherTenantPayVacant d-none">
          <label class="fw-bold">Tenant Pays:</label>
          <input type="text" name="otherTenantPay" value="{{isset($auction->get->otherTenantPay) ? $auction->get->otherTenantPay : ''}}" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
        </div>
      </div>

      @php
        $financial_sources = [['name' => 'Accountant', 'target' => ''], ['name' => 'Broker', 'target' => ''], ['name' => 'Owner', 'target' => ''], ['name' => 'Tax Return', 'target' => '']];
      @endphp
      <div class="form-group road_frontage_next_hide ">
        <label class="fw-bold">Financial Source:</label>
        <select class="grid-picker" name="financial_sources" id="terms_of_lease"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($financial_sources as $financial_source)
            <option value="{{ $financial_source['name'] }}"
              data-target="{{ $financial_source['target'] }}" class="card flex-row"
              data-icon='<i class="fa-regular fa-circle-check"></i>' style="width:calc(33.3% - 10px);"{{isset($auction->get->financial_sources) && $auction->get->financial_sources == $financial_source['name'] ? 'selected' : ''}}>
              {{ $financial_source['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="fw-bold">Occupied:</label>
        <select class="grid-picker" name="occupied" id="occupied" style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($yes_or_nos as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->occupied) && $auction->get->occupied == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label class="fw-bold">Total Number of Units:</label>
        <input type="number" name="total_number_of_units" value="{{isset($auction->get->total_number_of_units) ? $auction->get->total_number_of_units : ''}}" placeholder="Total Number of Units"
          id="total_number_of_units" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
      </div>

    </div>
  </div>
  <div class="wizard-step" data-step="46">
    <div class="row ">
      <div class="form-group main">
        <label class="fw-bold" for="heated_sqft">Heated Sqft:</label>
        <input type="number" name="heated_sqft_com" value="{{isset($auction->get->heated_sqft_com) ? $auction->get->heated_sqft_com : ''}}" id="heated_sqft"
          class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined" required>
      </div>
      <div class="form-group">
        <label class="fw-bold" for="sqft">Total Sqft:</label>
        <input type="number" name="total_sqft_com" value="{{isset($auction->get->total_sqft_com) ? $auction->get->total_sqft_com : ''}}" id="heated_sqft"
          class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined" required>
      </div>
      @php
        $heated_sources = [['name' => 'Appraisal', 'target' => ''], ['name' => 'Building', 'target' => ''], ['name' => 'Measured', 'target' => ''], ['name' => 'Owner Provided', 'target' => ''], ['name' => 'Public Records', 'target' => '']];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Sqft Heated Source:</label>
        <select class="grid-picker" name="heated_source_com" id="heated_sources"
          style="justify-content: flex-start;" required>
          <option value="">Select</option>
          @foreach ($heated_sources as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
              data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-column"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->heated_source_com) && $auction->get->heated_source_com == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="47">
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
          ['name' => 'Touchless Faucet', 'target' => ''],
          ['name' => 'None', 'target' => ''],
          ['name' => 'Other', 'target' => '.otherAppliancesIncome'],
      ];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Appliances:</label>
      <select class="grid-picker" name="appliances[]" id="appliances" style="justify-content: flex-start;"
        multiple required>
        <option value="">Select</option>
        @foreach ($appliances as $item)
          <option value="{{ $item['name'] }}" data-icon='<i class="fa-regular fa-circle-check"></i>'
            data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->appliances) && in_array($item['name'], json_decode($auction->get->appliances, true) ?? []) ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherAppliancesIncome d-none">
        <label class="fw-bold">Appliances:</label>
        <input type="text" name="otherAppliancesCom" value="{{isset($auction->get->otherAppliancesCom) ? $auction->get->otherAppliancesCom : ''}}" id="flood_zone_code" placeholder=""
          class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="48">
    <div class="form-group ">
      @php
        $furnishingsIncome = [['name' => 'Yes', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],['name' => 'Optional', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-question"></i>']];
      @endphp
      <label class="fw-bold">Are there any furnishings included in the purchase?</label>
      <select class="grid-picker" name="has_furnishing_com" id="has_water_view"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($furnishingsIncome as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}" {{isset($auction->get->has_furnishing_com) && $auction->get->has_furnishing_com == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="row has_furnishings_commercial_and_business d-none">
      <div class="form-group">
        <div class="form-group">
          <label class="fw-bold">What furnishings are included in the purchase?</label>
          <input type="text" name="furnishings_include_com" value="{{isset($auction->get->furnishings_include_com) ? $auction->get->furnishings_include_com : ''}}" id="flood_zone_code" placeholder=""
            class="form-control has-icon" data-icon="fa-regular fa-circle-check">
        </div>
      </div>
      @php
        $additional_fees = [['name' => 'Additional Fees', 'target' => ''], ['name' => 'Included in Purchase Price', 'target' => '']];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Are there any additional fees for the listed furnishings, or are they included in the purchase price?</label>
        <select class="grid-picker" name="has_additional_fees_com" id="has_water_view"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($additional_fees as $item)
            @php
              if ($item['name'] == 'Additional Fees') {
                  $target = '.has_additional_fees_commercial_and_business';
              } else {
                  $target = '';
              }
            @endphp
            <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->has_additional_fees_com) && $auction->get->has_additional_fees_com == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group has_additional_fees_commercial_and_business">
        <div class="form-group">
          <label class="fw-bold">How much is the listed furniture?</label>
          <input type="text" name="listed_furniture_price_com" value="{{isset($auction->get->listed_furniture_price_com) ? $auction->get->listed_furniture_price_com : ''}}" id="listed_furniture_price" placeholder=""
            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
        </div>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="49">
    @php
      $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Real Estate Include:</label>
      <select class="grid-picker" name="has_real_estate_include" id="bathrooms" style="" required>
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-column" style="width:calc(33.3% - 10px);"
            data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->has_real_estate_include) && $auction->get->has_real_estate_include == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group">
      <label class="fw-bold">Business Name:</label>
      <input type="text" name="business_name" value="{{isset($auction->get->business_name) ? $auction->get->business_name : ''}}" id="custom_bathrooms" class="form-control has-icon"
        data-icon="fa-regular fa-circle-check" required>
    </div>
    <div class="form-group">
      <label class="fw-bold">Year Established:</label>
      <input type="text" name="year_established" value="{{isset($auction->get->year_established) ? $auction->get->year_established : ''}}" id="year_established" class="form-control has-icon"
        data-icon="fa-regular fa-calendar-days" required>
    </div>
    @php
      $licenses = [
        ['name' => 'Beer/Wine', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
        ['name' => 'Liquor', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
        ['name' => 'None', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
        ['name' => 'Off Site', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
        ['name' => 'On Site', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
        ['name' => 'Other', 'target' => '.licensesOther', 'icon' => 'fa-regular fa-circle-check']
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Licenses:</label>
      <select class="grid-picker" name="licenses" id="bathrooms" style="" required>
        <option value="">Select</option>
        @foreach ($licenses as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
            class="card flex-column" style="width:calc(33.3% - 10px);"
            data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->licenses) && $auction->get->licenses == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group licensesOther d-none">
      <div class="form-group">
        <label class="fw-bold">Licenses:</label>
        <input type="text" name="custom_licenses" value="{{isset($auction->get->custom_licenses) ? $auction->get->custom_licenses : ''}}" id="custom_licenses" placeholder=""
          class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>
  </div>
  <div class="wizard-step" data-step="50">
    <div class="form-group">
      <label class="fw-bold">How many floors are in the property?</label>
      <input type="number" name="number_of_buildings_com" value="{{isset($auction->get->number_of_buildings_com) ? $auction->get->number_of_buildings_com : ''}}" id="number_of_buildings" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-hotel" required>
    </div>
    <div class="form-group">
      <label class="fw-bold">What floor number is the property on?</label>
      <input type="number" name="floors_in_unit_com" value="{{isset($auction->get->floors_in_unit_com) ? $auction->get->floors_in_unit_com : ''}}" id="floors_in_unit" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-building">
    </div>
    <div class="form-group">
      <label class="fw-bold">How many floors are in the entire building?</label>
      <input type="number" name="total_floors_com" value="{{isset($auction->get->total_floors_com) ? $auction->get->total_floors_com : ''}}" id="total_floors" placeholder=""
        class="form-control has-icon" data-icon="fa-solid fa-building">
    </div>
    <div class="form-group">
      <label class="fw-bold">Building Elevator</label>
      <select class="grid-picker" name="building_elevator_com" id="building_elevator"
        style="justify-content: flex-start;">
        <option value="">Select</option>
        @foreach ($yes_or_nos as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(25% - 10px);" data-icon='<i class="fa-solid fa-building"></i>' {{isset($auction->get->building_elevator_com) && $auction->get->building_elevator_com == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="51">
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
          ['name' => 'Other', 'target' => ''],
          ['name' => 'Parquet', 'target' => ''],
          ['name' => 'Porcelain Tile', 'target' => ''],
          ['name' => 'Quarry Tile', 'target' => ''],
          ['name' => 'Reclaimed View', 'target' => ''],
          ['name' => 'Recycled/Composite Flooring', 'target' => ''],
          ['name' => 'Slate', 'target' => ''],
          ['name' => 'Terrazzo', 'target' => ''],
          ['name' => 'Tile', 'target' => ''],
          ['name' => 'Travertine', 'target' => ''],
          ['name' => 'Vinyl', 'target' => ''],
          ['name' => 'Wood', 'target' => ''],
          ['name' => 'None', 'target' => ''],
          ['name' => 'Other', 'target' => '.otherFloorCoveringCom'],
      ];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Floor Covering:</label>
      <select class="grid-picker" name="floor_covering[]" id="floor_covering"
        style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($floor_coverings as $floor_covering)
          <option value="{{ $floor_covering['name'] }}" data-target="{{ $floor_covering['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->floor_covering) && in_array($floor_covering['name'], json_decode($auction->get->floor_covering, true) ?? []) ? 'selected' : ''}}>
            {{ $floor_covering['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group otherFloorCoveringCom d-none">
      <label class="fw-bold">Floor Covering:</label>
      <input type="text" name="otherFloorCoveringCom" value="{{isset($auction->get->otherFloorCoveringCom) ? $auction->get->otherFloorCoveringCom : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
    </div>
  </div>
  <div class="wizard-step" data-step="52">
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
      <select class="grid-picker" name="front_exposure_com" id="front_exposure"
        style="justify-content: flex-start;" required>
        <option value="">Select</option>
        @foreach ($front_exposures as $front_exposure)
          <option value="{{ $front_exposure['name'] }}" data-target="{{ $front_exposure['target'] }}"
            class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->front_exposure_com) && $auction->get->front_exposure_com == $front_exposure['name'] ? 'selected' : ''}}>
            {{ $front_exposure['name'] }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="wizard-step" data-step="53">
    @php
      $foundations = [
        ['name' => 'Basement', 'target' => ''], 
        ['name' => 'Block', 'target' => ''], 
        ['name' => 'Brick/Mortar', 'target' => ''], 
        ['name' => 'Concrete Perimeter', 'target' => ''], 
        ['name' => 'Crawlspace', 'target' => ''],
        ['name' => 'Pillar/Post/Pier', 'target' => ''], 
        ['name' => 'Slab', 'target' => ''], 
        ['name' => 'Stem Wall', 'target' => ''], 
        ['name' => 'Stilt/On Piling', 'target' => ''], 
        ['name' => 'Other', 'target' => '.otherFoundationCom']
      ];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Foundation:</label>
      <select class="grid-picker" name="foundation[]" id="foundation" style="justify-content: flex-start;"
        multiple required>
        <option value="">Select</option>
        @foreach ($foundations as $foundation)
          <option value="{{ $foundation['name'] }}" data-target="{{ $foundation['target'] }}"
            data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
            style="width:calc(33.3% - 10px);" {{isset($auction->get->foundation) && in_array($foundation['name'], json_decode($auction->get->foundation, true) ?? []) ? 'selected' : ''}}>
            {{ $foundation['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group otherFoundationCom d-none">
      <label class="fw-bold">Foundation:</label>
      <input type="text" name="otherFoundationCom" value="{{isset($auction->get->otherFoundationCom) ? $auction->get->otherFoundationCom : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
    </div>
  </div>
  <div class="wizard-step" data-step="54">
    @php
      $exterior_constructions = [['name' => 'Asbestos', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Cedar', 'target' => ''], ['name' => 'Cement Siding', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'HardiPlank Type', 'target' => ''], ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''], ['name' => 'Log', 'target' => ''], ['name' => 'Metal Frame', 'target' => ''], ['name' => 'Metal Siding', 'target' => ''], ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''], ['name' => 'Stone', 'target' => ''], ['name' => 'Stucco', 'target' => ''], ['name' => 'Tilt up Walls', 'target' => ''], ['name' => 'Vinyl Siding', 'target' => ''], ['name' => 'Wood Frame', 'target' => ''], ['name' => 'Wood Frame (FSC)', 'target' => ''], ['name' => 'Wood Siding ', 'target' => ''], ['name' => 'Other ', 'target' => '.otherExteriorCon']];
    @endphp
    <div class="form-group ">
      <label class="fw-bold">Exterior Construction:</label>
      <select class="grid-picker" name="exterior_construction[]" id="exterior_construction"
        style="justify-content: flex-start;" multiple required>
        <option value="">Select</option>
        @foreach ($exterior_constructions as $exterior_construction)
          <option value="{{ $exterior_construction['name'] }}"
            data-target="{{ $exterior_construction['target'] }}" class="card flex-row"
            data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);" {{isset($auction->get->exterior_construction) && in_array($exterior_construction['name'], json_decode($auction->get->exterior_construction, true) ?? []) ? 'selected' : ''}}>
            {{ $exterior_construction['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherExteriorCon d-none">
        <label class="fw-bold">Exterior Construction:</label>
        <input type="text" name="otherConstructionCom" value="{{isset($auction->get->otherConstructionCom) ? $auction->get->otherConstructionCom : ''}}" id="max_pet_weight" class="form-control has-icon"
          data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>
  </div>