@extends('layouts.main')
@push('styles')
  <style>
    .choices__list {
      z-index: 999;
    }

    .wizard-steps-progress {
      height: 5px;
      width: 100%;
      background-color: #CCC;
      position: absolute;
      top: 0;
      left: 0;
    }

    .steps-progress-percent {
      height: 100%;
      width: 0%;
      background-color: #11b7cf;
    }

    .wizard-step {
      display: none;
    }

    .wizard-step.active {
      display: block;
    }

    label.warning {
      color: #f00;
    }

    ::placeholder {
      color: #cacaca !important;
      opacity: 1;
      /* Firefox */
    }

    :-ms-input-placeholder {
      /* Internet Explorer 10-11 */
      color: #cacaca !important;
    }

    ::-ms-input-placeholder {
      /* Microsoft Edge */
      color: #cacaca !important;
    }

    .hide_arrow::-webkit-outer-spin-button,
    .hide_arrow::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    /* Firefox */
    .hide_arrow {
      -moz-appearance: textfield;
    }

    .input-cover {
      align-items: center;
      position: relative;
    }

    .input-cover .input-icon {
      position: absolute;
      left: 10px;
      font-size: 30px;
      color: #11b7cf;
    }

    .input-cover .form-control {
      padding-left: 50px;
    }

    .form-control {
      min-height: 50px;
    }

    .form-group {
      margin-top: 15px;
    }

    .options-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: flex-start;
    }

    .option-container {
      align-items: center;
      cursor: pointer;
      display: flex;
      flex-direction: row;
      align-items: center;
      padding: 15px;
      margin: 10px;
      margin-left: 0;
      margin-bottom: 0;
    }

    .option-container.active {
      border-color: #006e9f;
      color: #006e9f;
    }

    .option-container .option-icon {
      font-size: 40px;
      color: #11b7cf;
    }

    .option-container .option-text {
      padding-left: 10px;
    }

    .text-error {
      width: 100%;
    }

    .text-error {
      border-color: rgba(var(--bs-danger-rgb), var(--bs-text-opacity)) !important;
    }

    .grid-picker {
      width: 100%;
      height: 0px;
      visibility: hidden;
    }

    ::-ms-browse {
      height: 50px;
    }

    ::-webkit-file-upload-button {
      height: 50px;
    }

    input[type=file]::file-selector-button {
      height: 50px;
    }
  </style>
@endpush
@section('content')
  <div class="container p-4">
    <h4 class="title">
      {{ $title }}
    </h4>

    <div class="card m-4">
      <div class="card-title">
        {{--  --}}
      </div>
      <div class="card-body">
        <div class="wizard-steps-progress">
          <div class="steps-progress-percent"></div>
        </div>

        <form class="p-4 pt-0 mainform" action="{{ route('criteria.auction.bid', @$auction->id) }}" method="POST"
          enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
          @php
            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
          @endphp
          <div class="wizard-step">
            <h4 class="title">Please enter the city, county, and state of the property you are offering to sell.
            </h4>
            <div class="form-group">
              <label class="fw-bold">City:</label>
              <input type="text" name="city" data-type="cities" placeholder="Florida city, FL, USA" id="city"
                class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                data-msg-required="Please enter city" required>
            </div>

            <div class="form-group">
              <label class="fw-bold">County:</label>
              <input type="text" name="county" data-type="counties" placeholder="Baker County, FL, USA" id="county"
                class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                data-msg-required="Please enter county" required>
            </div>

            <div class="form-group">
              <label class="fw-bold">State:</label>
              <input type="text" name="state" data-type="states" placeholder="Florida, FL, USA" id="states"
                class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
                data-msg-required="Please enter state" required>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Offered Property Type:</label>
              <select class="grid-picker" name="property_type" id="property_type"
                onchange="changePropertyType(this.value);show_property_opt();" required>
                <option value="">Select</option>
                @foreach ($property_types as $row_pt)
                  <option value="{{ $row_pt['name'] }}" class="card flex-column fw-bold" style="width:calc(24% - 10px);"
                    data-icon='<i class="fa-solid fa-hotel"></i>'>
                    {{ $row_pt['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <div>
                @php
                  $property_items = [
                      ['name' => 'Single Family Residence', 'class' => 'residential-length'],
                      ['name' => 'Townhouse', 'class' => 'residential-length'],
                      ['name' => 'Villa', 'class' => 'residential-length'],
                      ['name' => 'Condominium', 'class' => 'residential-length'],
                      ['name' => 'Condo-Hotel', 'class' => 'residential-length'],
                      ['name' => '½ Duplex', 'class' => 'residential-length'],
                      ['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                      ['name' => 'Farm', 'class' => 'residential-length'],
                      ['name' => 'Garage Condo', 'class' => 'residential-length'],
                      ['name' => 'Manufactured Home', 'class' => 'residential-length'],
                      ['name' => 'Mobile Home', 'class' => 'residential-length'],
                      ['name' => 'Modular Home', 'class' => 'residential-length'],
                      ['name' => 'Duplex', 'class' => 'income-length'],
                      ['name' => 'Triplex', 'class' => 'income-length'],
                      ['name' => 'Quadplex', 'class' => 'income-length'],
                      ['name' => 'Five or More (Residential units)', 'class' => 'income-length'],
                      ['name' => 'Agriculture', 'class' => 'commercial-length'],
                      ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                      ['name' => 'Business', 'class' => 'commercial-length'],
                      ['name' => 'Five or More (Residential units)', 'class' => 'commercial-length'],
                      ['name' => 'Five or More (Commercial units)', 'class' => 'commercial-length'],
                      ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                      ['name' => 'Industrial', 'class' => 'commercial-length'],
                      ['name' => 'Mixed Use', 'class' => 'commercial-length'],
                      ['name' => 'Office', 'class' => 'commercial-length'],
                      ['name' => 'Restaurant', 'class' => 'commercial-length'],
                      ['name' => 'Retail', 'class' => 'commercial-length'],
                      ['name' => 'Unimproved Land', 'class' => 'commercial-length'],
                      ['name' => 'Warehouse', 'class' => 'commercial-length'],
                  ];
                @endphp
                <select name="property_items" id="property_items" class="property_items grid-picker"
                  style="justify-content: flex-start;" multiple required>
                  <option value=""></option>
                  @foreach ($property_items as $item)
                    <option value="{{ $item['name'] }}" data-target="" class="card flex-row  {{ $item['class'] }}"
                      style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>

            {{-- @push('scripts')
              <script>
                function show_property_opt() {
                  var pt = $('#property_type').val();
                  alert
                  if (pt == 'Residential Property' || pt == 'Income Property') {
                    $('.property_opt').removeClass('d-none');
                  } else {
                    $('.property_opt').addClass('d-none');
                  }
                }
              </script>
            @endpush --}}

            @php
              $bedrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '', 'name' => 'none'], ['target' => '.custom_bedrooms', 'name' => 'Other']];
              $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.custom_bathrooms', 'name' => 'Other']];
            @endphp
          </div>
          <div class="wizard-step">
            @php
              $special_sale = [['name' => 'Pre-Construction', 'target' => ''], ['name' => 'Currently Being Built', 'target' => ''], ['name' => 'New Construction', 'target' => ''], ['name' => 'Assignment Contract (Wholesale property)', 'target' => '.assignment_contract'], ['name' => 'Bank Owned/REO', 'target' => ''], ['name' => 'Probate Listing', 'target' => ''], ['name' => 'Short Sale', 'target' => ''], ['name' => 'Government Owned', 'target' => ''], ['name' => 'Auction', 'target' => ''], ['name' => 'None', 'target' => '']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Please select the sale provisions in which the seller is offering: </label>
              <select class="grid-picker" name="assignment_contract[]" id="assignment_contract" multiple
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($special_sale as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                    style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group assignment_contract d-none">
              @php
                $yes_no = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <label class="fw-bold">Is the seller currently under contract with a property they would like to
                assign?</label>
              <select class="grid-picker" name="assignment_contract" id="assignment_contract" style="" required>
                <option value="">Select</option>
                @foreach ($yes_no as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                    style="width:calc(25% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="wizard-step">
            <div class="property_opt">
              <div class="form-group">
                <label class="fw-bold">How many bedrooms does the property have?</label>
                <select class="grid-picker" name="bedrooms" id="bedrooms" style="" required>
                  <option value="">Select</option>
                  @foreach ($bedrooms as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                      style="width:calc(9% - 10px);" data-icon='<i class="fa-solid fa-bed"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group custom_bedrooms d-none">
                <label>Custom Bedrooms:</label>
                <input type="text" name="custom_bedrooms" id="custom_bedrooms" class="form-control has-icon"
                  data-icon="fa-solid fa-bed" required>
              </div>


              <div class="form-group">
                <label class="fw-bold">How many bathrooms does the property have?</label>
                <select class="grid-picker" name="bathrooms" id="bathrooms" style="" required>
                  <option value="">Select</option>
                  @foreach ($bathrooms as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                      style="width:calc(10% - 10px);" data-icon='<i class="fa-solid fa-bath"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group custom_bathrooms d-none">
                <label>Custom Bathrooms:</label>
                <input type="text" name="custom_bathrooms" id="custom_bathrooms" class="form-control has-icon"
                  data-icon="fa-solid fa-bath" required>
              </div>

            </div>
          </div>

          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">How many heated sqft does the property have?</label>
              <input type="number" name="min_sqft" id="min_sqft" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle" required>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">What is the total acreage of the property?</label>
              <select class="grid-picker" name="lot_size" id="lot_size" style="justify-content: flex-start;"
                required>
                <option value="">Select</option>
                @foreach ($lot_sizes as $lot_size)
                  <option value="{{ $lot_size['name'] }}" data-target="{{ $lot_size['target'] }}"
                    class="card flex-column " style="width:calc(20% - 10px);">
                    {{ $lot_size['name'] }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="form-group other_floor_in_property d-none">
              <label class="fw-bold" for="other_floor_in_property">Other Terms of Lease:</label>
              <input type="text" name="other_floor_in_property" id="other_floor_in_property"
                placeholder="Floors in property" class="form-control"
                data-msg-required="Please enter Floors in property" required>
            </div>
          </div>
          <div class="wizard-step">
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
                  ['name' => 'Trash Compactor', 'target' => ''],
                  ['name' => 'Washer', 'target' => ''],
                  ['name' => 'Water Filtration System', 'target' => ''],
                  ['name' => 'Water Purifier', 'target' => ''],
                  ['name' => 'Water Softener', 'target' => ''],
                  ['name' => 'Whole House R.O. System', 'target' => ''],
                  ['name' => 'Wine Refrigerator', 'target' => ''],
                  ['name' => 'None', 'target' => ''],
                  ['name' => 'Other', 'target' => ''],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">What appliances are included? </label>
              <select class="grid-picker" name="lot_size" id="lot_size" style="justify-content: flex-start;"
                required>
                <option value="">Select</option>
                @foreach ($appliances as $appliance)
                  <option value="{{ $appliance['name'] }}" data-target="{{ $appliance['target'] }}"
                    class="card flex-column " style="width:calc(20% - 10px);">
                    {{ $appliance['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              $requires_or_no = [['name' => 'Yes', 'target' => '.custom_insurance', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              $requires_or_no1 = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              $requires_or_no2 = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              $requires_or_no3 = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Is flood insurance required?</label>
              <select class="grid-picker" name="flood_insurance" id="flood_insurance"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($requires_or_no as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group custom_insurance d-none">
              <label class="fw-bold">How much is the flood insurance?</label>
              <input type="text" name="custom_insurance" id="custom_insurance" class="form-control has-icon"
                data-icon="fa-solid fa-dollar-sign" required>
            </div>
          </div>

          <div class="wizard-step">
            @php
              $pets = [['name' => 'Yes', 'target' => '.custom_pets', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              $pet_types = [['name' => 'Dog', 'target' => ''], ['name' => 'Cat', 'target' => ''], ['name' => 'Other', 'target' => '.other_pets']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Are pets allowed? </label>
              <select class="grid-picker" name="pets" id="pets" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($pets as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group custom_pets d-none">
              <label class="fw-bold">Type of pets allowed:</label>
              <select class="grid-picker" name="custom_pets" id="prop_conditions" required>
                <option value="">Select</option>
                @foreach ($pet_types as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group d-none other_pets">
              <label class="fw-bold">Number of pets allowed:</label>
              <input type="number" name="number_of_pets" id="number_of_pets" class="form-control has-icon"
                data-icon="" required>
              <label class="fw-bold">Max pet weight:</label>
              <input type="text" name="pet_weight" id="pet_weight" class="form-control has-icon" data-icon=""
                required>
              <label class="fw-bold">Pet restrictions:</label>
              <input type="text" name="pet_restrictions" id="pet_restrictions" class="form-control has-icon"
                data-icon="" required>
            </div>
          </div>

          <div class="wizard-step">
            @php
              $pools = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Does the property have a pool? </label>
              <select class="grid-picker" name="pools" id="pools" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($pools as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $garage = [['name' => 'Yes', 'target' => '.custom_garage', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Does the property have a garage? </label>
              <select class="grid-picker" name="garage" id="garage" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($garage as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group d-none custom_garage">
              <label class="fw-bold">How many garage spaces? </label>
              <input type="number" name="custom_garage" id="custom_garage" class="form-control has-icon"
                data-icon="" required>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $carport = [['name' => 'Yes', 'target' => '.custom_carport', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Does the property have a carport? </label>
              <select class="grid-picker" name="carport" id="carport" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($carport as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group d-none custom_carport">
              <label class="fw-bold">How many carport spaces?</label>
              <input type="number" name="custom_carport" id="custom_carport" class="form-control has-icon"
                data-icon="" required>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $prop_conditions = [['name' => 'Not Updated: Requires a complete update.', 'target' => ''], ['name' => 'Tear Down: Requires complete demolition and reconstruction.', 'target' => ''], ['name' => 'Semi-updated:Needs minor updates.', 'target' => ''], ['name' => 'Completely Updated:No updates needed.', 'target' => ''], ['name' => 'Other', 'target' => '.custom_prop_conditions']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">What is the condition of the property? </label>
              <select class="grid-picker" name="prop_conditions[]" multiple id="prop_conditions" required>
                <option value="">Select</option>
                @foreach ($prop_conditions as $item)
                  <option value="{{ $item['name'] }}" class="card flex-row" data-target="{{ $item['target'] }}"
                    style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group d-none custom_prop_conditions">
              <label class="fw-bold">What is the condition of the property? </label>
              <input type="number" name="custom_prop_conditions" id="custom_prop_conditions"
                class="form-control has-icon" data-icon="" required>
            </div>
          </div>
          {{-- Changes by Waqas  Start --}}

          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold"> Is the property currently offered for lease?</label>
              <select class="grid-picker" name="is_leased" id="is_leased" style="justify-content: flex-start;"
                required>
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  @php
                    if ($item['name'] == 'Yes') {
                        $target = '.commercial_fields';
                    } else {
                        $target = '';
                    }
                  @endphp
                  <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row "
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="card-body commercial_fields d-none">
              <div class="row align-items-end">
                <div class="form-group col-md-12">
                  <label class="fw-bold">Current Rental Price:</label>

                  <input type="number" name="current_rental_price" placeholder="0.00" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar" required>
                </div>
                @php
                  $utilities = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '1 year', 'target' => ''], ['name' => '2 year', 'target' => ''], ['name' => '3-5 years', 'target' => ''], ['name' => 'Month to Month', 'target' => ''], ['name' => 'Other', 'target' => '']];
                @endphp
                <div class="form-group col-md-12">
                  <label class="fw-bold">Current Lease Terms:</label>
                  <select class="grid-picker" name="leased_term" id="leased_term" style="justify-content: flex-start;"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($utilities as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(25% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                {{-- Changes By Waqas --}}
                <div class="form-group col-md-6">
                  <label for="address" class="fw-bold">Lease Start Date :</label>
                  <input type="date" name="move_in_date" id="move_in_date"
                    class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                    data-msg-required="Please enter listing date" required>
                </div>
                <div class="form-group col-md-6">
                  <label for="address" class="fw-bold">Lease Expiration Date:</label>
                  <input type="date" name="lease_expiration_date" id="lease_expiration_date"
                    class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                    data-msg-required="Please enter listing date" required>
                </div>
                <div class="form-group">
                  <table class="table table-bordered">
                    <thead>
                      <tr>
                        <th>Unit #: </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td><input type="text" name="lease_unit[]" placeholder="Unit" data-type="unit"
                            id="unit" class="form-control  search_places" data-icon="fa-solid fa-unit"
                            data-msg-required="Please enter unit" required>
                        </td>
                      </tr>
                      <tr class="unit_btn_row">
                        <td><button type="button" class="btn btn-secondary btn-sm w-100 add-new-btn"
                            onclick="add_city_row()"><i class="fa-solid fa-plus"></i> Add New
                            Row</button></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Security Deposit</label>
                  <input type="number" name="security_deposit" placeholder="0.00" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Did the tenant pay the last month's rent?</label>
                  <select class="grid-picker" name="is_tenant_pay_rent" id="is_tenant_pay_rent"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($requires_or_no1 as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Is the tenant current on rent?</label>
                  <select class="grid-picker" name="is_tenant_on_rent" id="is_tenant_on_rent"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($requires_or_no2 as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row "
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
          </div>

          <div class="wizard-step">

            <div class="form-group">
              <label class="fw-bold">
                Offered Price:
              </label>
              <input type="text" name="max_price" id="max_price" placeholder="" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle">
            </div>

          </div>
          <div class="wizard-step">
            @php
              $financings = [['name' => 'Cash', 'target' => ''], ['name' => 'Conventional', 'target' => ''], ['name' => 'FHA', 'target' => ''], ['name' => 'VA', 'target' => ''], ['name' => 'USDA', 'target' => ''], ['name' => 'Assumable', 'target' => ''], ['name' => 'Exchange/Trade', 'target' => ''], ['name' => 'Lease Option', 'target' => ''], ['name' => 'Lease Purchase', 'target' => ''], ['name' => 'Special Funding', 'target' => ''], ['name' => 'Seller Financing', 'target' => ''], ['name' => 'Other', 'target' => '.custom_financings']];
            @endphp

            <div class="form-group">
              <label class="fw-bold">Acceptable Currency/Financing:</label>
              <select class="grid-picker" name="financings[]" id="financings" style="justify-content: flex-start;"
                multiple required>
                <option value="">Select</option>
                @foreach ($financings as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(20% - 10px);">
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group custom_financings d-none">
              <label class="fw-bold">Currency/Financing Type:</label>
              <input type="text" name="custom_financings" id="custom_financings" class="form-control has-icon"
                required>
            </div>
          </div>

          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">Acceptable Escrow Deposit:</label>
              <input type="text" name="escrow_amount_percent" id="escrow_amount_percent"
                class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
            </div>
          </div>


          <div class="wizard-step">

            @php
              $contingencies = [['target' => '', 'name' => 'Home Inspection'], ['target' => '', 'name' => 'Appraisal'], ['target' => '', 'name' => 'Financing'], ['target' => '', 'name' => 'Sale of a prior property'], ['target' => '', 'name' => 'None'], ['target' => '.custom_contingencies', 'name' => 'Other']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Acceptable Contingencies:</label>
              <select class="grid-picker" name="contingencies" id="contingencies" style="" multiple required>
                <option value="">Select</option>
                @foreach ($contingencies as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group custom_contingencies d-none">
              <label>Inspection Period Offered:</label>
              <input type="text" name="custom_contingencies" id="custom_contingencies"
                class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
            </div>
          </div>
          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">Offered Closing Date: </label>
              <input type="date" name="offer_closing_date" id="offer_closing_date" class="form-control has-icon"
                data-icon="fa-regular fa-calendar" required>
            </div>
            <div class="form-group">
              <label class="fw-bold">What is the desired number of days for the seller to complete the closing process?
              </label>
              <input type="number" name="number_of_days" id="number_of_days" class="form-control has-icon"
                data-icon="fas fa-calendar-day" required>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $selle_offer = [['name' => 'Yes', 'target' => '.custom_offer', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Is the seller offering a credit to the buyer at closing? </label>
              <select class="grid-picker" name="selle_offer" id="selle_offer" style="justify-content: flex-start;"
                required>
                <option value="">Select</option>
                @foreach ($selle_offer as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group d-none custom_offer">
              <label class="fw-bold">Please enter the offered seller’s premium: </label>
              <input type="number" name="custom_offer" id="custom_offer" class="form-control has-icon"
                data-icon="" required>
            </div>
          </div>
          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">What is the commission offered to the buyer’s agent? </label>
              <input type="number" name="agent_commission_percent" id="agent_commission_percent" min="1"
                class="form-control has-icon" data-icon="" required>
            </div>
          </div>

          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">Description and Additional Details: Please provide any additional information about
                the property:</label>
              <textarea name="description" id="description" class="form-control" cols="30" rows="5" required></textarea>
            </div>
          </div>



          {{-- <div class="wizard-step">

            @php
              $inspection_period = [['target' => '', 'name' => '3 days'], ['target' => '', 'name' => '5 days'], ['target' => '', 'name' => '7 days'], ['target' => '', 'name' => '10 days'], ['target' => '', 'name' => '15 days'], ['target' => '', 'name' => '21 days'], ['target' => '', 'name' => '30 days'], ['target' => '', 'name' => '60 days'], ['target' => '', 'name' => '90 days'], ['target' => '', 'name' => 'None'], ['target' => '.custom_period', 'name' => 'Other']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Inspection Period Offered:</label>
              <select class="grid-picker" name="inspection_period" id="inspection_period" style="" required>
                <option value="">Select</option>
                @foreach ($inspection_period as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group custom_period d-none">
              <label>Inspection Period Offered:</label>
              <input type="text" name="custom_period" id="custom_period" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle" required>
            </div>
          </div> --}}




          {{-- <div class="wizard-step"> --}}

          {{-- @php
                            $request_seller_premium = [['target' => '', 'name' => '.5%'], ['target' => '', 'name' => '1%'], ['target' => '', 'name' => '1.5%'], ['target' => '', 'name' => '2%'], ['target' => '', 'name' => '2.5%'], ['target' => '', 'name' => '3%'], ['target' => '', 'name' => '3.5%'], ['target' => '', 'name' => '4%'], ['target' => '', 'name' => '4.5%'], ['target' => '', 'name' => '5%'], ['target' => '', 'name' => '5.5%'], ['target' => '', 'name' => '6%'], ['target' => '', 'name' => 'None']];
                        @endphp --}}
          {{-- <div class="form-group">
              <label class="fw-bold">Offered Seller's Credit at Closing (Seller's Premium)
                (Optional):</label>
              <input type="text" name="request_seller_premium" id="request_seller_premium"
                class="form-control has-icon" data-icon="fa-regular fa-check-circle" placeholder="e.g $5000 or 20%"
                required> --}}
          {{-- <select class="grid-picker" name="request_seller_premium" id="request_seller_premium"
                                style="" required>
                                <option value="">Select</option>
                                @foreach ($request_seller_premium as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select> --}}
          {{-- </div>
          </div> --}}

          {{-- <div class="wizard-step"> --}}

          {{-- @php
                            $request_buyer_premium = [['target' => '', 'name' => '.5%'], ['target' => '', 'name' => '1%'], ['target' => '', 'name' => '1.5%'], ['target' => '', 'name' => '2%'], ['target' => '', 'name' => '2.5%'], ['target' => '', 'name' => '3%'], ['target' => '', 'name' => '3.5%'], ['target' => '', 'name' => '4%'], ['target' => '', 'name' => '4.5%'], ['target' => '', 'name' => '5%'], ['target' => '', 'name' => '5.5%'], ['target' => '', 'name' => '6%'], ['target' => '', 'name' => 'None']];
                        @endphp --}}
          {{-- <div class="form-group">
              <label class="fw-bold">Requested Buyer's Credit at Closing (Buyer's Premium)
                (Optional):</label>
              <input type="text" name="request_buyer_premium" id="request_buyer_premium"
                class="form-control has-icon" data-icon="fa-regular fa-check-circle" placeholder="e.g $5000 or 20%"
                required>
              <select class="grid-picker" name="request_buyer_premium" id="request_buyer_premium"
                                style="" required>
                                <option value="">Select</option>
                                @foreach ($request_buyer_premium as $item)
                                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                        class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
            </div>
          </div>

          <div class="wizard-step">
            @php
              $sell_days = [['target' => '', 'name' => '7'], ['target' => '', 'name' => '14'], ['target' => '', 'name' => '21'], ['target' => '', 'name' => '30'], ['target' => '', 'name' => '35'], ['target' => '', 'name' => '40'], ['target' => '', 'name' => '45'], ['target' => '', 'name' => '60'], ['target' => '', 'name' => '75'], ['target' => '', 'name' => '90'], ['target' => '', 'name' => '120'], ['target' => '', 'name' => 'Flexible'], ['target' => '.custom_days', 'name' => 'Other']];
            @endphp

            <div class="form-group">
              <label class="fw-bold">When does the Seller want to sell?</label>
              <select class="grid-picker" name="sell_days" id="sell_days" style="" required>
                <option value="">Select</option>
                @foreach ($sell_days as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group custom_days d-none">
              <label>When does the Seller want to sell?</label>
              <input type="text" name="custom_days" id="custom_days" class="form-control has-icon"
                data-icon="fa-regular fa-check-circle" required>
            </div>

          </div> --}}
          {{--
          <div class="wizard-step">
            @php
              $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Lot Size:</label>
              <select class="grid-picker" name="lot_size" id="lot_size" style="justify-content: flex-start;"
                required>
                <option value="">Select</option>
                @foreach ($lot_sizes as $lot_size)
                  <option value="{{ $lot_size['name'] }}" data-target="{{ $lot_size['target'] }}"
                    class="card flex-column fw-bold" style="width:calc(20% - 10px);">
                    {{ $lot_size['name'] }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="form-group other_floor_in_property d-none">
                            <label class="fw-bold" for="other_floor_in_property">Other Terms of Lease:</label>
                            <input type="text" name="other_floor_in_property" id="other_floor_in_property"
                                placeholder="Floors in property" class="form-control"
                                data-msg-required="Please enter Floors in property" required>
                        </div>
          </div> --}}









          {{-- Vacant  --}}

          {{-- <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">Are any unit vacant?</label>
              <select class="grid-picker" name="is_vacant" id="is_vaccant" style="justify-content: flex-start;"
                required>
                <option value="">Select</option>
                @foreach ($requires_or_no3 as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row fw-bold" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="card-body commercial_fields " id="no_vaccant">
              <div class="row align-items-end">
                <div class="form-group col-md-6">
                  <label>Annual Operation Expenses:</label>

                  <input type="number" name="annual_opertaion_expenses" placeholder="0.00"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group col-md-6">
                  <label>Net Operation Income:</label>

                  <input type="number" name="net_operation_income" placeholder="0.00" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group col-md-6">
                  <label>Captalization Rate:</label>

                  <input type="number" name="captalization_rate" placeholder="0.00" class="form-control has-icon"
                    data-icon="fa-solid" required>
                </div>
                @php
                  $utilities = [['name' => 'Building(s) and Land', 'target' => ''], ['name' => 'Furniture/Fixtures', 'target' => ''], ['name' => 'Leases', 'target' => ''], ['name' => 'Other', 'target' => '']];
                @endphp
                <div class="form-group col-md-12">
                  <label>Sale Includes:</label>
                  <select class="grid-picker" name="sale_includes" id="tenant_pays"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($utilities as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row fw-bold" style="width:calc(25% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group col-md-6">
                  <label>Tenant Pays:</label>

                  <input type="number" name="tenant_pays" placeholder="0.00" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group col-md-6">
                  <label>Landlord Pays:</label>

                  <input type="number" name="landlord_pays" placeholder="0.00" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar" required>
                </div>


              </div>
            </div>
            <div class="card-body commercial_fields " id="yes_vaccant">
              <div class="row align-items-end">
                <div class="form-group col-md-6">
                  <label>Current Operating Expenses:</label>

                  <input type="number" name="current_operating_expenses" placeholder="0.00"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group col-md-6">
                  <label> Current Net Operating Income:</label>

                  <input type="number" name="current_net_operating_income" placeholder="0.00"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group col-md-6">
                  <label> Projected Net Operating Income:</label>

                  <input type="number" name="projected_net_operating_income" placeholder="0.00"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group col-md-6">
                  <label> Current Captalization Rate:</label>

                  <input type="number" name="current_captalization_rate" placeholder="0.00"
                    class="form-control has-icon" data-icon="fa-solid" required>
                </div>
                <div class="form-group col-md-6">
                  <label> Projected Captalization Rate:</label>

                  <input type="number" name="projected_captalization_rate" placeholder="0.00"
                    class="form-control has-icon" data-icon="fa-solid" required>
                </div>
                @php
                  $utilities = [['name' => 'Building(s) and Land', 'target' => ''], ['name' => 'Furniture/Fixtures', 'target' => ''], ['name' => 'Leases', 'target' => ''], ['name' => 'Other', 'target' => '']];
                @endphp
                <div class="form-group col-md-12">
                  <label>Sale Includes:</label>
                  <select class="grid-picker" name="sale_includes" id="tenant_pays"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($utilities as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row fw-bold" style="width:calc(25% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group col-md-6">
                  <label>Tenant Pays:</label>

                  <input type="number" name="tenant_pays" placeholder="0.00" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group col-md-6">
                  <label>Landlord Pays:</label>

                  <input type="number" name="landlord_pays" placeholder="0.00" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar" required>
                </div>


              </div>
            </div>
          </div> --}}


          {{-- Vacant End --}}



          {{-- Changes by Waqas  End --}}
          <div class="wizard-step">
            <h4>The following section is privately sent to the agent:</h4>
            <div class="form-group">
              <label class="fw-bold">Address of the property:</label>
              <input type="text" name="address" placeholder="199 Florida A1A, Satellite Beach, FL, USA"
                id="form_title" class="form-control search_places has-icon" data-type="address" required
                data-icon="fa-solid fa-location-dot" />
            </div>

            {{-- <div class="form-group card-field">
              @if (Auth::user()->user_type == 'agent')
                <label class="fw-bold">Business Card:</label>
              @elseif (Auth::user()->user_type == 'seller')
                <label class="fw-bold">ID Card:</label>
              @else
              @endif
              <div class="">
                <input type="file" class="form-control" accept="image/*" name="card" required>
              </div>
            </div> --}}

            {{-- <div class="form-group">
              <label class="fw-bold">Link to the property if listed:</label>
              <input type="text" name="property_link" placeholder="" id="property_link"
                class="form-control has-icon" data-type="address" data-icon="fa-solid fa-link" />
            </div> --}}

            <div class="form-group">
              <label class="fw-bold">Pictures of the property: (upload)</label>
              <input type="file" name="property_image" placeholder="" id="property_image"
                class="form-control has-icon" data-type="address" data-icon="fa-solid fa-link" />
            </div>
            <div class="form-group">
              <label class="fw-bold">Pictures of the property: (link)</label>
              <input type="text" name="floor_plan_link" placeholder="" id="floor_plan_link"
                class="form-control has-icon" data-type="address" data-icon="fa-solid fa-link" />
            </div>


            {{-- <div class="form-group">
              <label class="fw-bold">Link to view property photos and/or floor plan:</label>
              <input type="text" name="floor_plan_link" placeholder="" id="floor_plan_link"
                class="form-control has-icon" data-type="address" data-icon="fa-solid fa-link" />
            </div> --}}

            <div class="form-group">
              <label class="fw-bold">Video of the property: (upload)</label>
              <input type="file" class="form-control" name="video_image">
            </div>
            <div class="form-group">
              <label class="fw-bold">Video of the property: (link)</label>
              <input type="text" class="form-control" name="video_url">
            </div>

            {{-- <div class="form-group">
              <label class="fw-bold">Letter Upload: Personal letter to the Buyer/Buyer's agent. This letter
                will be sent privately to the Buyer's agent.</label>
              <div class="d-flex align-items-baseline">
                <input type="file" class="form-control" name="note">
              </div>
            </div>

            <div class="form-group">
              <label class="fw-bold">Audio Upload: Personal audio for the Buyer/Buyer's agent. This audio
                will be sent privately to the Buyer's agent.</label>
              <div class="d-flex align-items-baseline">
                <input type="file" class="form-control" accept="audio/*" name="audio">
              </div>
            </div>

            <div class="form-group">
              <label class="fw-bold">Video Upload: Personal video for the Buyer/Buyer’s agent. This video
                will be sent privately to the Buyer’s agent.</label>
              <div class="d-flex align-items-baseline">
                <input type="file" class="form-control" accept="audio/*" name="audio">
              </div>
            </div> --}}

          </div>
          @if (auth()->user()->user_type == 'agent')
            <div class="wizard-step">
              <div class="form-group col-md-">
                <label class="fw-bold">First Name:</label>
                <input type="text" name="first_name" id="first_name" class="form-control" cols="30"
                  rows="5" placeholder="First Name" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Last Name:</label>
                <input type="text" name="last_name" id="last_name" class="form-control" cols="30"
                  rows="5" placeholder="Last Name" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Phone Number:</label>
                <input type="number" name="phone_number" id="phone_number" class="form-control" cols="30"
                  rows="5" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Email:</label>
                <input type="email" name="email" id="email" class="form-control" cols="30"
                  rows="5" placeholder="Email">
              </div>
              <div class="form-group">
                <label class="fw-bold">Brokerage:</label>
                <input type="text" name="brokerage" id="brokerage" class="form-control" cols="30"
                  rows="5" placeholder="Brokerage">
              </div>
              <div class="form-group">
                <label class="fw-bold">Real Estate License #</label>
                <input type="text" name="license" id="license" class="form-control" cols="30"
                  rows="5" placeholder="Real Estate License">
              </div>
              <div class="form-group">
                <label class="fw-bold">NAR Member ID (NRDS ID): </label>
                <input type="text" name="member_id" id="member_id" class="form-control" cols="30"
                  rows="5" placeholder="NAR Member ID">
              </div>
            </div>
          @elseif (auth()->user()->user_type == 'seller')
            <div class="wizard-step">
              <div class="form-group col-md-">
                <label class="fw-bold">First Name:</label>
                <input type="text" name="first_name" id="first_name" class="form-control" cols="30"
                  rows="5" placeholder="First Name" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Last Name:</label>
                <input type="text" name="last_name" id="last_name" class="form-control" cols="30"
                  rows="5" placeholder="Last Name">
              </div>
              <div class="form-group">
                <label class="fw-bold">Phone Number:</label>
                <input type="number" name="phone_number" id="phone_number" class="form-control" cols="30"
                  rows="5" placeholder="Phone Number">
              </div>
              <div class="form-group">
                <label class="fw-bold">Email:</label>
                <input type="email" name="email" id="email" class="form-control" cols="30"
                  rows="5" placeholder="Email">
              </div>
            </div>
          @endif



          <div class="d-flex justify-content-between form-group mt-4">
            <div>
              <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
            </div>
            <div>
              <a class="wizard-step-next btn btn-success btn-lg text-600" style="display: none;">Next</a>
              <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
                style="display: none;">Save</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <template class="add_new_lease">
    <tr>
      <td><input type="text" name="lease_unit[]" placeholder="Uint" data-type="unit"
          class="form-control has-icon search_places" data-icon="fa-solid fa-tree-unit"></td>
    </tr>
  </template>
@endsection
@push('scripts')
  <script>
    $(function() {
      $('.add-row').on('click', function() {
        var socialRow = `<tr>
                                <td>
                                    <select name="socialType[]" class="form-select">
                                        <option value="Facebook">Facebook</option>
                                        <option value="YouTube">YouTube</option>
                                        <option value="LinkedIn">LinkedIn</option>
                                        <option value="Twitter">Twitter</option>
                                        <option value="Instagram">Instagram</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="social_link[]" class="form-control">
                                </td>
                            </tr>`;
        $('.social-links').append(socialRow);
      });
    });
  </script>

  <script>
    function changeAuctionType(v) {
      if (v == "Normal (Timer)") {
        $('.auction_length').val("");
        $('.auction_length').parent().children('.option-container').removeClass('active');
        $('.traditional-length').hide();
        $('.normal-length').show();
      } else {
        $('.auction_length').val("");
        $('.auction_length').parent().children('.option-container').removeClass('active');
        $('.traditional-length').show();
        $('.normal-length').hide();
      }
    }
    // document.getElementById('auction_type').change();
    $(function() {
      // changeAuctionType("Normal (Timer)");
    });
  </script>
  <script>
    function changePropertyType(p) {
      if (p == "Residential Property") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').show();
        $('.income-length').hide();
        $('.commercial-length').hide();
      } else if (p == "Income Property") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').show();
        $('.commercial-length').hide();
      } else if (p == "Commercial Property") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').show();
      } else {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').hide();
      }
    }
    // document.getElementById('auction_type').change();
    $(function() {
      changePropertyType("");
    });
  </script>
  <script>
    $(function() {
      $(document).ready(function() {
        /* var multipleCancelButton = new Choices('.multiple', {
            removeItemButton: true,
            // maxItemCount:5,
            // searchResultLimit: 5,
            // renderChoiceLimit: 5
        }); */
        $('.multiple').select2();

        $('.select2').each(function() {
          $(this).prependTo($(this).parent('.select2-parent'));
        });
      });
    });
  </script>

  <script>
    $(function() {
      $('.has-icon').each(function(i) {
        var cover = `<div class="input-cover input-cover-${i}"></div>`;
        $(this).before(cover);
        $(this).appendTo(`.input-cover-${i}`);
        var iconClass = $(this).data('icon');
        var id = $(this).attr('id');
        var htm = `<label for="${id}" class="input-icon"><i class="${iconClass} " ></i></label>`;
        $(this).before(htm);
      });

      $('.grid-picker').each(function(index, elm) {
        var st = $(elm).attr('style');
        var html =
          `<div class="options-container options-container-${index}" style="${st}"></div>`;
        $(elm).after(html);
        $(elm).appendTo(`.options-container-${index}`);
        $(elm).children('option').each(function(i) {
          var val = $(this).val();
          if (val != "") {
            var text = $(this).text();
            var classes = $(this).attr('class');
            var styles = $(this).attr('style') || "";
            var icon = $(this).data('icon') || "";
            var selected = $(this).attr('selected') || "";
            var target = $(this).data('target') || "";
            selected = selected && "active";
            icon = icon && icon + " ";
            var htm = `<div onclick="checkselect(this);" style="${styles}" class="${classes} ${selected} option-container" data-index="${i}" data-target="${target}">
                <div class="option-icon">${icon}</div>
                <div class="option-text">${text}</div>
                </div>`;
            $(`.options-container-${index}`).append(htm);
          }
          // console.log(val, text, icon, classes);
        });
        // console.log(html);
        // html += `</div>`;
        // $(elm).after(html);
        /* $('.option-container').on('click', function(index, elm) {
            // alert("ok");
            var ind = $(elm).data(index);
            var op = $(elm).parent().html();
            console.log(op);
        }); */
      });



    });

    function checkselect(elm) {
      var i = $(elm).data('index');
      var mult = $(elm).parent().children('select').attr('multiple') || false;
      // console.log(mult);
      if (mult == false) {
        var option = $(elm).parent().children('select').children(`option:eq(${i})`);
        var ov = option.val();
        $(elm).parent().children('.option-container').removeClass('active');
        $(elm).addClass('active');
        $(elm).parent().children('select').val(ov);
      } else {
        $(elm).toggleClass('active');
        var option = $(elm).parent().children('select').children(`option:eq(${i})`);
        var ov = option.val();
        var vals = $(elm).parent().children('select').val();
        if (vals.includes(ov)) {
          option.removeAttr('selected');
        } else {
          option.attr('selected', 'selected');
        }
      }

      // console.log(op);
      var v = $(elm).parent().children('select').val();
      $(elm).parent().children('select').trigger('change');
      //   console.log(v);
      check_custom();
    }

    function check_custom() {
      $('.option-container').each(function(i, elm) {
        var target = $(elm).data('target') || "";
        var is_active = $(elm).hasClass('active');
        if (target != "") {
          if (is_active) {
            $(target).removeClass("d-none");
          } else {
            $(target).addClass("d-none");
            $(target).find('input').val("");
          }
        }
      });
      // setTimeout(check_custom, 500);
    }


    $('#no_vaccant').show();
    $('#yes_vaccant').show();
    $(document).on('change', '#is_vaccant', function() {

      var clicked_value = $(this).val();


      if (clicked_value == 'Yes') {

        $('#no_vaccant').hide();
        $('#yes_vaccant').show();
      }
      if (clicked_value == 'No') {
        $('#yes_vaccant').hide();
        $('#no_vaccant').show();
      }

      // alert(clicked_value);


    });
  </script>

  <script>
    $(function() {
      StepWizard.init();
    });

    var StepWizard = {
      init: function() {
        StepWizard.total_steps = $('.wizard-step').length;

        var v = $(".mainform").validate({
          errorClass: "text-danger w-100",
          onkeyup: false,
          onfocusout: false,
          /* submitHandler: function() {
              // alert("Submitted, thanks!");
              $(".mainform").submit();
          } */
        });

        StepWizard.setStep();
        $('.wizard-step-next').click(function(e) {
          if (v.form()) {
            if ($('.wizard-step.active').next().is('.wizard-step')) {
              $('.wizard-step.active').removeClass('active').next().addClass('active');
              StepWizard.setStep();
            }
          }
        });

        $('.wizard-step-back').click(function(e) {
          if ($('.wizard-step.active').prev().is('.wizard-step')) {
            $('.wizard-step.active').removeClass('active').prev().addClass('active');
            StepWizard.setStep();
          }
        });

        $('.wizard-step-finish').click(function(e) {
          if (v.form()) {
            $('.mainform').submit();
          }
        });
      },
      setStep: function() {
        if ($('.wizard-step.active').length == 0) {
          $('.wizard-step').first().addClass('active');
        }
        if ($('.wizard-step.active').prev().is('.wizard-step')) {
          $('.wizard-step-back').show();
        } else {
          $('.wizard-step-back').hide();
        }

        if ($('.wizard-step.active').next().is('.wizard-step')) {
          $('.wizard-step-next').show();
          $('.wizard-step-finish').hide();
        } else {
          $('.wizard-step-next').hide();
          $('.wizard-step-finish').show();
        }
        $('.wizard-step').each(function(i, element) {
          var k = i + 1;
          if ($(element).hasClass('active')) {
            StepWizard.currentStep = k;
          }
        });
        StepWizard.stepChanged();
      },
      stepChanged: function() {
        var comp = (StepWizard.currentStep / StepWizard.total_steps) * 100;
        $('.steps-progress-percent').animate({
          width: comp.toFixed(0) + '%',
        });
      },
      currentStep: 1,
      total_steps: 0,
    };
  </script>

  <script>
    // google.maps.event.addDomListener(window, 'load', initialize);
    function initialize() {
      var inputField = document.getElementsByClassName('search_places');

      for (var i = 0; i < inputField.length; i++) {
        var t = inputField[i].dataset.type;
        // console.log(t);
        if (t === "cities") {
          var options = {
            types: ['(cities)'],
            componentRestrictions: {
              country: "us"
            },
          };
        } else if (t === "states") {
          var options = {
            types: ['administrative_area_level_1'],
            componentRestrictions: {
              country: "us"
            },
          };
        } else if (t === "address") {
          var options = {
            types: [],
            componentRestrictions: {
              country: "us"
            },
          };
        } else {
          var options = {
            types: ['administrative_area_level_2'],
            componentRestrictions: {
              country: "us"
            },
          };
        }

        google.maps.event.addDomListener(inputField[i], 'keydown', function(e) {
          if (e.keyCode == 13) {
            if (e.preventDefault) {
              e.preventDefault();
            } else {
              // Since the google event handler framework does not handle early IE versions, we have to do it by our self.: -(
              e.cancelBubble = true;
              e.returnValue = false;
            }
          }
        });



        var autocomplete = new google.maps.places.Autocomplete(inputField[i], options);

        autocomplete.addListener('place_changed', function(e) {
          var place = autocomplete.getPlace();
          if (place) {
            // console.log("place", place);
            // place variable will have all the information you are looking for.
            var lat = place.geometry['location'].lat();
            var lng = place.geometry['location'].lng();
            if (t == "counties") {
              $('#lat').val(lat);
              $('#long').val(lng);
            }
          }
        });
      }
    }

    function add_city_row() {
      var city_row = $('.add_new_lease').html();
      $('.unit_btn_row').before(city_row);
      initialize();
    }
  </script>
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush
