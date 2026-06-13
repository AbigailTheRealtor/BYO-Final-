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

    /* // variables */
    $base-color: cadetblue;
    $base-font: "Raleway", sans-serif;

    .wrapper {
      display: flex;
      flex-direction: row;
      flex-wrap: wrap;
      align-items: center;
      justify-content: center;
    }

    h1 {
      font-family: inherit;
      margin: 0 0 0.75em 0;
      color: desaturate($base-color, 15%);
      text-align: center;
    }

    .box {
      display: block;
      width: 200px;
      height: 160px;
      background-color: white;
      border-radius: 5px;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      /* overflow: hidden; */
      position: relative;
    }

    .js--image-preview {
      width: 200px;
      height: 160px;
    }

    .upload {
      margin-bottom: 100px;
    }

    .upload-options {
      position: relative;
      /* height: 65px; */
      background-color: $base-color;
      cursor: pointer;
      overflow: hidden;
      text-align: center;
      transition: background-color ease-in-out 150ms;
      color: red;
      width: 100%;
      border: 1px solid #dedddd;
      border-radius: 0px 0px 5px 5px;

      &:hover {
        background-color: lighten($base-color, 10%);
      }

      & input {
        width: 0.1px;
        height: 0.1px;
        opacity: 0;
        overflow: hidden;
        position: absolute;
        z-index: -1;
      }

      & label {
        display: flex;
        align-items: center;
        width: 100%;
        height: 100%;
        font-weight: 400;
        text-overflow: ellipsis;
        white-space: nowrap;
        cursor: pointer;
        overflow: hidden;

        &::after {
          content: "+";
          font-family: "Material Icons";
          z-index: 0;
          display: flex;
          justify-content: center;
          align-items: center;
          width: 198px;
          height: 50px;
          font-size: 28px;
          color: #e6e6e6;
        }

        & span {
          display: inline-block;
          width: 50%;
          height: 100%;
          text-overflow: ellipsis;
          white-space: nowrap;
          overflow: hidden;
          vertical-align: middle;
          text-align: center;

          &:hover i.material-icons {
            color: lightgray;
          }
        }
      }
    }

    .js--image-preview {
      height: 100%;
      width: 100%;
      /* position: relative; */
      overflow: hidden;
      background-image: url('/images/image.png');
      background-color: white;
      /* background-position: center center; */
      background-repeat: no-repeat;
      background-size: cover;

      &.js--no-default::after {
        display: none;
      }

      &:nth-child(2) {
        background-image: url("http://bastianandre.at/giphy.gif");
      }
    }

    i.material-icons {
      transition: color 100ms ease-in-out;
      font-size: 2.25em;
      line-height: 55px;
      color: white;
      display: block;
    }

    .drop {
      display: block;
      position: absolute;
      background: transparentize($base-color, 0.8);
      border-radius: 100%;
      transform: scale(0);
    }

    .animate {
      animation: ripple 0.4s linear;
    }

    @keyframes ripple {
      100% {
        opacity: 0;
        transform: scale(2.5);
      }
    }

    .video {
      height: 160px;
      width: 200px;
      position: relative;
      /* overflow: hidden; */

      background-color: white;
      /* background-position: center center; */
      background-repeat: no-repeat;
      background-size: cover;
      margin-bottom: 60px;

    }

    .bgImg {
      background-image: url('/images/play.png');
    }

    span.upload-button {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 198px;
      height: 50px;
      font-size: 28px;
      color: #e6e6e6;
    }

    .videoBox {
      width: 200px;
      border-radius: 5px;

    }

    .videoDiv {
      margin-top: -60px;
    }

    label.fileuploader-btn {
      /* position: absolute; */
      top: 100%;
      border-radius: 0px 0px 5px 5px;
      border: 1px solid #e2e2e2;
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

      </div>
      <div class="card-body">
        <div class="wizard-steps-progress">
          <div class="steps-progress-percent"></div>
        </div>

        <form class="p-4 pt-0 mainform" action="{{ route('agent.tenant.criteria.auction.add') }}" method="POST"
          enctype="multipart/form-data">
          @csrf
          <div class="wizard-step" data-step="1">
            <h4 class="">Please provide the cities, counties, and state pertaining to the real estate location
              where
              the
              tenant intends
              to lease a property:</h4>
            <div class="form-group">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Cities:</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <input type="text" name="cities[]" placeholder="" data-type="cities" id="cities"
                        class="form-control  search_places has-icon" data-icon="fa-solid fa-city" required>
                    </td>
                  </tr>
                  <tr class="city_btn_row">
                    <td><button type="button" class="btn btn-secondary btn-sm w-100" onclick="add_city_row();"><i
                          class="fa-solid fa-plus"></i> Add New
                        Row</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="form-group">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>Counties:</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><input type="text" name="counties[]" placeholder="" data-type="counties" id="counties"
                        class="form-control  search_places has-icon" data-icon="fa-solid fa-tree-city" required>
                    </td>
                  </tr>
                  <tr class="county_btn_row">
                    <td><button type="button" class="btn btn-secondary btn-sm w-100" onclick="add_county_row();"><i
                          class="fa-solid fa-plus"></i> Add New
                        Row</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="form-group">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th>State:</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><input type="text" name="state[]" placeholder="" data-type="state" id="state"
                        class="form-control  search_places has-icon" data-icon="fa-solid fa-flag-usa"required>
                    </td>
                  </tr>

                </tbody>
              </table>
            </div>
            @include('partials.location-dna.map-input', ['existingLocationDna' => []])
          </div>
          <div class="wizard-step" data-step="2">
            <div class="form-group">
              <label class="fw-bold">Listing Date:</label>
              <input type="date" name="listing_date" id="listing_date" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" required>
            </div>

            <div class="form-group">
              <label class="fw-bold">Expiration Date:</label>
              <input type="date" name="expiration_date" id="expiration_date" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" required>
            </div>
          </div>
          <div class="wizard-step" data-step="3">
            <div class="form-group">
              @php
                $listingType = [['name' => 'Full Service'], ['name' => 'Limited Service']];
              @endphp
              <label class="fw-bold">Listing Service Type: </label>
              <select name="listingType" id="" class="grid-picker"
                style="justify-content: flex-start;" required>
                @foreach ($listingType as $item)
                  <option value="{{ $item['name'] }}" data-target="" class="card flex-row"
                    style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              @php
                $representation = [['name' => 'Tenant Represented'], ['name' => 'Tenant Not Represented']];
              @endphp
              <label class="fw-bold">Representation: </label>
              <select name="representation" class="grid-picker"
                style="justify-content: flex-start;" required>
                @foreach ($representation as $item)
                  <option value="{{ $item['name'] }}" data-target="" class="card flex-row "
                    style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="wizard-step" data-step="4">
            <div class="form-group">
              <label class="fw-bold">Title of Listing:</label>
              <input type="text" name="titleListing" id="" class="form-control has-icon"
                data-icon="fa-solid fa-hotel" required>
            </div>
          </div>
          <div class="wizard-step" data-step="5">
            {{-- 9 June 2023 for Residential and Income Property --}}
            @php
              $property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];
            @endphp

            <div class="form-group">
              <label class="fw-bold">Acceptable Property Styles: </label>
              <select class="grid-picker" name="property_type" id="property_type"
                onchange="changePropertyType(this.value);" required>
                <option value="">Select</option>
                @foreach ($property_types as $row_pt)
                  <option value="{{ $row_pt['name'] }}" class="card flex-column" style="width:calc(24% - 10px);"
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
                      ['name' => 'Apartment', 'class' => 'residential-length'],
                      ['name' => 'Townhouse', 'class' => 'residential-length'],
                      ['name' => 'Villa', 'class' => 'residential-length'],
                      ['name' => 'Condominium', 'class' => 'residential-length'],
                      ['name' => 'Condo-Hotel', 'class' => 'residential-length'],
                      ['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                      ['name' => 'Farm', 'class' => 'residential-length'],
                      ['name' => 'Garage Condo', 'class' => 'residential-length'],
                      ['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
                      ['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
                      ['name' => 'Modular Home', 'class' => 'residential-length'],
                      ['name' => '½ Duplex', 'class' => 'residential-length'],
                      ['name' => '1/3 Triplex', 'class' => 'residential-length'],
                      ['name' => '1/4 Quadplex', 'class' => 'residential-length'],
                      ['name' => 'Unimproved Land', 'class' => 'residential-length'],
                      ['name' => 'Duplex', 'class' => 'income-length'],
                      ['name' => 'Triplex', 'class' => 'income-length'],
                      ['name' => 'Quadplex', 'class' => 'income-length'],
                      ['name' => 'Five or More (Residential units)', 'class' => 'income-length'],
                      ['name' => 'Agriculture', 'class' => 'commercial-length'],
                      ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                      ['name' => 'Business', 'class' => 'commercial-length'],
                      ['name' => 'Five or More', 'class' => 'commercial-length'],
                      ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                      ['name' => 'Industrial', 'class' => 'commercial-length'],
                      ['name' => 'Mixed Use', 'class' => 'commercial-length'],
                      ['name' => 'Office', 'class' => 'commercial-length'],
                      ['name' => 'Restaurant', 'class' => 'commercial-length'],
                      ['name' => 'Retail', 'class' => 'commercial-length'],
                      ['name' => 'Warehouse', 'class' => 'commercial-length'],
                  ];
                @endphp
                <select name="property_items[]" id="property_items" class="property_items grid-picker"
                  style="justify-content: flex-start;" multiple required>
                  <option value=""></option>
                  @foreach ($property_items as $item)
                    <option value="{{ $item['name'] }}" data-target="" class="card flex-row {{ $item['class'] }}"
                      style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>


            @push('scripts')
              <script>
                function check_hoa_values() {
                  var hoa = $('#hoa_community').val();
                  if (hoa == "No") {
                    $('#hoa_fee_requirement').val("");
                    $('.hoa_req_option').removeClass('active');
                    $('#hoa_fee').val("");
                  }
                }

                function check_hoa() {
                  var pt = $('#property_type').val();
                  if (pt == 'Residential Property' || pt == 'Income Property') {
                    $('.hoa_questions').removeClass('d-none');
                  } else {
                    $('.hoa_questions').addClass('d-none');
                  }
                }
              </script>
              <script>
                function show_hoa() {
                  var h = $('#hoa_community').val();
                  if (h == "Yes" || h == "Optional") {
                    $('.hoa_fee_yes').show();
                  } else {
                    $('.hoa_fee_yes').hide();
                  }
                }
                $(function() {
                  show_hoa("");
                });
              </script>
            @endpush


            {{-- 9 June 2023 for Residential and Income Property --}}
          </div>
          <div class="wizard-step" data-step="6">
            <div class="form-group">
              @php
                $propsRes = [
                    ['name' => 'Entire Property', 'target' => ''],
                    ['name' => 'Single Room', 'target' => '.singleRoomRes'],
                    ['name' => 'Open to Leasing Either the Entire Property or Single Room', 'target' => ''],
                ];
              @endphp
              <label class="fw-bold">Is the tenant interested in leasing an entire property, a single room, or open to leasing either?</label>
              <select class="grid-picker" name="leaseProp" id="bedrooms" style="" required>
                <option value="">Select</option>
                @foreach ($propsRes as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card"
                    style="width:calc(33.33% - 10px );" data-icon='<i class="fa-solid fa-hotel"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group main singleRoomRes d-none">
                <label class="fw-bold">What is the desired size of the room for the tenant?</label>
                <input type="text" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                  name="leasePropOther" required>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="7">
            <span class="resFields">
              @php
                $prop_condition = [
                    ['name' => 'Updated/Renovated', 'target' => '', 'icon' => 'fa-regular fa-circle-check', 'display' => 'Updated / Renovated'],
                    ['name' => 'Partially Updated', 'target' => '', 'icon' => 'fa-regular fa-circle-check', 'display' => 'Partially Updated'],
                    ['name' => 'Older but Clean', 'target' => '', 'icon' => 'fa-regular fa-circle-check', 'display' => 'Older but Clean & Well Maintained'],
                    ['name' => 'No Preference', 'target' => '', 'icon' => 'fa-regular fa-circle-check', 'display' => 'No Preference'],
                ];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">
                  Acceptable Property Conditions:
                </label>
                <select class="grid-picker" name="prop_condition[]" id="prop_condition"
                  style="justify-content: flex-start;" multiple required>
                  <option value="">Select</option>
                  @foreach ($prop_condition as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['display'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group main other_prop_conditionRes d-none">
                <label class="fw-bold">Acceptable Property Conditions: </label>
                <input type="text" name="propsOther" id="propsOther" class="form-control has-icon  "
                  data-icon="fa-regular fa-circle-check" data-msg-required="Property Condition" placeholder="">
              </div>
            </span>
          </div>
          <div class="wizard-step" data-step="8">
            <div class="form-group">
              <h4>Tenant’s Leasing Terms: </h4>
              <label class="fw-bold">Maximum Monthly Lease Price:</label>
              <input type="number" step="0.01" placeholder="0.00" name="monthly_price" id="monthly_price"
                class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
            </div>
            <span class="resFields">
              @php
                $leaseLenRes = [
                    ['name' => '3 Months', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => '6 Months', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => '9 Months', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => '1 Year', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => '2 Years', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => '3-5 Years', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => '5+ Years', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => 'Month to Month', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => 'Other', 'target' => '.leaseOtherRes', 'icon' => 'fa-regular fa-circle-check'],
                ];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">
                  Offered Lease Length:
                </label>
                <select class="grid-picker" name="leaseLength[]" id="" style="justify-content: flex-start;"
                  required multiple>
                  <option value="">Select</option>
                  @foreach ($leaseLenRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group main leaseOtherRes d-none">
                <label class="fw-bold">Offered Lease Length: </label>
                <input type="text" name="leaseOther" id="propsOther" class="form-control has-icon  "
                  data-icon="fa-regular fa-calendar-days" data-msg-required="Property Condition" placeholder="" required>
              </div>
            </span>
            <div class="form-group">
              <label class="fw-bold">Offered Lease Start Date:</label>
              <input type="date" name="idealDate" id="propsOther" class="form-control has-icon  "
                data-icon="fa-regular fa-calendar-days" data-msg-required="Property Condition" placeholder="" required>
            </div>
          </div>
          <div class="wizard-step" data-step="9">

            <div class="form-group">
              @php
                $bedrooms = [
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
                    ['target' => '.custom_bedrooms', 'name' => 'Other'],
                ];

              @endphp
              <label class="fw-bold">Minimum Bedrooms Needed:</label>
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
            <div class="form-group main custom_bedrooms d-none">
              <label class="fw-bold">Minimum Bedrooms Needed:</label>
              <input type="number" name="custom_bedrooms" id="custom_bedrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bed" required>
            </div>
            {{-- </span> --}}
          </div>
          <div class="wizard-step" data-step="10">
            @php
              $bathrooms = [
                  ['target' => '', 'name' => '1'],
                  ['target' => '', 'name' => '1.5'],
                  ['target' => '', 'name' => '2'],
                  ['target' => '', 'name' => '2.5'],
                  ['target' => '', 'name' => '3'],
                  ['target' => '', 'name' => '3.5'],
                  ['target' => '', 'name' => '4'],
                  ['target' => '', 'name' => '4.5'],
                  ['target' => '', 'name' => '5'],
                  ['target' => '', 'name' => '5.5'],
                  ['target' => '', 'name' => '6'],
                  ['target' => '', 'name' => '6.5'],
                  ['target' => '', 'name' => '7'],
                  ['target' => '', 'name' => '7.5'],
                  ['target' => '', 'name' => '8'],
                  ['target' => '', 'name' => '8.5'],
                  ['target' => '', 'name' => '9'],
                  ['target' => '', 'name' => '9.5'],
                  ['target' => '', 'name' => '10'],
                  ['target' => '.custom_bathrooms', 'name' => 'Other'],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Minimum Bathrooms Needed:</label>
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
            <div class="form-group main custom_bathrooms d-none">
              <label class="fw-bold">Minimum Bathrooms Needed: </label>
              <input type="number" name="custom_bathrooms" id="custom_bathrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bath" required>
            </div>
          </div>
          <div class="wizard-step" data-step="11">
            <span class="resFieldss">
              <div class="form-group">
                <label class="fw-bold">Minimum Heated Sqft Needed:</label>
                <input type="number" name="minimum_sqft_needed" id="minimum_sqft_needed"
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
            </span>
            <span class="commercialFieldss">
              <div class="form-group ">
                <label class="fw-bold">Minimum Net Leaseable Sqft Needed: </label>
                <input type="number" name="minimum_sqft_needed" id="minimum_sqft_needed"
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
            </span>
          </div>
          <div class="wizard-step" data-step="12">
            <span class="commercialFieldss">
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
                <label class="fw-bold"> Minimum Total Acreage Needed:</label>
                <select class="grid-picker" name="total_acreage" id="total_acreage"
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($acreageRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(25% - 10px);"
                      data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </span>
          </div>
          <div class="wizard-step" data-step="13">
            <div class="row commercial_show">
              @php

                $garageParkingComm = [
                    ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                    ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
                ];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Garage/Parking Featured Needed:</label>
                <select class="grid-picker" name="garageParkingOpt" id="garageParkingComm"
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($garageParkingComm as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group " id="garageParkingOpt" style="display: none;">
                  @php
                    $garage_spaces = [
                        ['target' => '', 'name' => '1 to 5 Spaces', 'icon' => ''],
                        ['target' => '', 'name' => '6 to 12 Spaces', 'icon' => ''],
                        ['target' => '', 'name' => '13 to 18 Spaces', 'icon' => ''],
                        ['target' => '', 'name' => '19 to 30 Spaces', 'icon' => ''],
                        ['target' => '', 'name' => 'Airplane Hangar', 'icon' => ''],
                        ['target' => '', 'name' => 'Common', 'icon' => ''],
                        ['target' => '', 'name' => 'Curb Parking', 'icon' => ''],
                        ['target' => '', 'name' => 'Deeded', 'icon' => ''],
                        [
                            'target' => '',
                            'name' => 'Electric Vehicle Charging Station(s)',
                            'icon' => '',
                        ],
                        ['target' => '', 'name' => 'Ground Level', 'icon' => ''],
                        ['target' => '', 'name' => 'Lighted', 'icon' => ''],
                        ['target' => '', 'name' => 'Over 30 Spaces', 'icon' => ''],
                        ['target' => '', 'name' => 'Secured', 'icon' => ''],
                        ['target' => '', 'name' => 'Under Building', 'icon' => ''],
                        ['target' => '', 'name' => 'Underground', 'icon' => ''],
                        ['target' => '', 'name' => 'Valet', 'icon' => ''],
                        ['target' => '', 'name' => 'None', 'icon' => ''],
                        ['target' => '.garageOther', 'name' => 'Other', 'icon' => ''],
                    ];
                  @endphp
                  <label class="fw-bold">Garage/Parking Features Needed:</label>
                  <select class="grid-picker" name="parking_feature_garage[]" id="parking_feature_garage"
                    style="justify-content: flex-start;" required multiple>
                    <option value="">Select</option>
                    @foreach ($garage_spaces as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-solid fa-warehouse"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group main garageOther d-none">
                    <label class="fw-bold">Garage/Parking Features:</label>
                    <input type="text" name="parkingGarageOther" id="minimum_sqft_needed" class="form-control has-icon"
                      data-icon="fa-solid fa-warehouse" required>
                  </div>
                </div>
              </div>
            </div>
            @php
              $yes_or_nos = [
                  ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                  ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
              ];
              $yes_or_nos_opt = [
                  ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                  ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                  ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
              ];
            @endphp
            <div class="row residential_show">
              <div class="form-group">
                <label class="fw-bold">Carport Needed:</label>
                <select class="grid-picker" name="carport" id="carport" style="justify-content: flex-start;"
                  onchange="show_carport_opt();" required>
                  <option value="">Select</option>
                  @foreach ($yes_or_nos_opt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
                $carport_options = [
                    ['name' => '1', 'target' => ''],
                    ['name' => '2', 'target' => ''],
                    ['name' => '3', 'target' => ''],
                    ['name' => '4', 'target' => ''],
                    ['name' => '5', 'target' => ''],
                    ['name' => '6', 'target' => ''],
                    ['name' => '7', 'target' => ''],
                    ['name' => '8', 'target' => ''],
                    ['name' => '9', 'target' => ''],
                    ['name' => '10', 'target' => ''],
                    ['name' => 'Other', 'target' => '.custom_carport'],
                ];
              @endphp
              <div class="form-group carport_opt">
                <label class="fw-bold">Carport Spaces Needed:</label>
                <select class="grid-picker" name="carport_opt" id="carport_opt" style="justify-content: flex-start;"
                  required>
                  <option value="">Select</option>
                  @foreach ($carport_options as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(15% - 10px);" data-icon="<i class='fa-solid fa-warehouse'></i>">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group main custom_carport d-none">
                <label class="fw-bold">Carport Spaces Needed:</label>
                <input type="number" name="custom_carport" id="custom_carport" class="form-control has-icon"
                  data-icon="fa-solid fa-warehouse" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Garage Needed: </label>
                <select class="grid-picker" name="garage" id="garage" style="justify-content: flex-start;"
                  onchange="show_garage_opt();" required>
                  <option value="">Select</option>
                  @foreach ($yes_or_nos_opt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
                $garage_options = [
                    ['name' => '1', 'target' => ''],
                    ['name' => '2', 'target' => ''],
                    ['name' => '3', 'target' => ''],
                    ['name' => '4', 'target' => ''],
                    ['name' => '5', 'target' => ''],
                    ['name' => '6', 'target' => ''],
                    ['name' => '7', 'target' => ''],
                    ['name' => '8', 'target' => ''],
                    ['name' => '9', 'target' => ''],
                    ['name' => '10', 'target' => ''],
                    ['name' => 'Other', 'target' => '.custom_garage'],
                ];
              @endphp
              <div class="form-group garage_opt">
                <label class="fw-bold">Garage Spaces Needed:</label>
                <select class="grid-picker" name="garage_opt" id="garage_opt" style="justify-content: flex-start;"
                  required>
                  <option value="">Select</option>
                  @foreach ($garage_options as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(15% - 10px);" data-icon="<i class='fa-solid fa-warehouse'></i>">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group main custom_garage d-none">
                <label class="fw-bold">Garage Spaces Needed:</label>
                <input type="number" name="custom_garage" id="custom_garage" class="form-control has-icon"
                  data-icon="fa-solid fa-warehouse" required>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="14">
            <span class="resFieldss">
              <div class="form-group ">
                @php
                  $waterViewOptRes = [
                      ['name' => 'Yes', 'target' => '.waterviewCommercial', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                      ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
                  ];
                @endphp
                <label class="fw-bold">Water View Needed:</label>
                <select class="grid-picker" name="has_water_view" id="waterViewRes"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($waterViewOptRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="form-group" id="waterViewResOpt" style="display: none">
                @php
                  $waterViewRes = [
                      ['name' => 'Bay/Harbor - Full', 'target' => ''],
                      ['name' => 'Bay/Harbor - Partial', 'target' => ''],
                      ['name' => 'Bayou', 'target' => ''],
                      ['name' => 'Beach', 'target' => ''],
                      ['name' => 'Canal', 'target' => ''],
                      ['name' => 'Creek', 'target' => ''],
                      ['name' => 'Gulf/Ocean - Full', 'target' => ''],
                      ['name' => 'Gulf/Ocean - Partial', 'target' => ''],
                      ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
                      ['name' => 'Intracoastal Waterway', 'target' => ''],
                      ['name' => 'Lagoon/Estuary', 'target' => ''],
                      ['name' => 'Lake', 'target' => ''],
                      ['name' => 'Lake - Chain of Lakes', 'target' => ''],
                      ['name' => 'Marina', 'target' => ''],
                      ['name' => 'Pond', 'target' => ''],
                      ['name' => 'River', 'target' => ''],
                      ['name' => 'None', 'target' => ''],
                  ];
                @endphp
                <label class="fw-bold">Water View Needed:</label>
                <select class="grid-picker" name="water_view[]" id="water_view" style="justify-content: flex-start;"
                  multiple required>
                  <option value="">Select</option>
                  @foreach ($waterViewRes as $item)
                    <option value="{{ $item['name'] }}" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="form-group">
                @php
                  $waterExtrasOptRes = [
                      ['name' => 'Yes', 'target' => '.waterviewCommercial', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                      ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
                  ];
                @endphp
                <label class="fw-bold">Water Extras Needed:</label>
                <select class="grid-picker" name="has_water_extra" id="waterExtraRes"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($waterExtrasOptRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="form-group" id="waterExtraResOpt" style="display: none;">
                @php
                  $waterExtraRes = [
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
                <label class="fw-bold">Water Extras Needed:</label>
                <select class="grid-picker" name="water_extras[]" id="water_extras"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($waterExtraRes as $item)
                    <option value="{{ $item['name'] }}" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group ">
                @php
                  $waterfrontageOptRes = [
                      [
                          'name' => 'Yes',
                          'target' => '.waterfrontageOptRes',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                      ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
                  ];
                @endphp
                <label class="fw-bold">Water Frontage Needed:</label>
                <select class="grid-picker" name="waterFrontageOpt" id="waterFrontageRes"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($waterfrontageOptRes as $item)
                    <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group" id="waterFrontageResOpt" style="display: none;">
                @php
                  $waterFrontageOptCommercial = [
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
                <label class="fw-bold">Water Frontage Needed:</label>
                <select class="grid-picker" name="waterFrontage[]" id="has_water_fontage"
                  style="justify-content: flex-start;" multiple required>
                  <option value="">Select</option>
                  @foreach ($waterFrontageOptCommercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                @php
                  $waterAccessOptRes = [
                      [
                          'name' => 'Yes',
                          'target' => '.waterAccessOptRes',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                      ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
                  ];
                @endphp
                <label class="fw-bold">Water Access Needed:</label>
                <select class="grid-picker" name="waterAccessOpt" id="waterAccessRes"
                  style="justify-content: flex-start">
                  <option value="">Select</option>
                  @foreach ($waterAccessOptRes as $item)
                    <option value="{{ $item['name'] }}" data-icon="<i class='{{ $item['icon'] }}'></i>"
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group" id="waterAccessResOpt" style="display: none;">
                @php
                  $waterAccessRes = [
                      ['name' => 'Bay/Harbor', 'target' => ''],
                      ['name' => 'Bayou', 'target' => ''],
                      ['name' => 'Beach', 'target' => ''],
                      ['name' => 'Beach - Access Deeded', 'target' => ''],
                      ['name' => 'Brackish Water', 'target' => ''],
                      ['name' => 'Canal - Brackish', 'target' => ''],
                      ['name' => 'Canal - Freshwater', 'target' => ''],
                      ['name' => 'Canal - Saltwater', 'target' => ''],
                      ['name' => 'Creek', 'target' => ''],
                      ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''],
                      ['name' => 'Gulf/Ocean', 'target' => ''],
                      ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
                      ['name' => 'Intracoastal Waterway', 'target' => ''],
                      ['name' => 'Lagoon/Estuary', 'target' => ''],
                      ['name' => 'Lake', 'target' => ''],
                      ['name' => 'Lake - Chain of Lakes', 'target' => ''],
                      ['name' => 'Limited Access', 'target' => ''],
                      ['name' => 'Marina', 'target' => ''],
                      ['name' => 'Pond', 'target' => ''],
                      ['name' => 'River', 'target' => ''],
                  ];
                @endphp
                <label class="fw-bold">Water Access Needed:</label>
                <select class="grid-picker" name="water_access[]" id="" style="justify-content: flex-start;"
                  multiple required>
                  <option value="">Select</option>
                  @foreach ($waterAccessRes as $item)
                    <option value="{{ $item['name'] }}" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </span>
            <span class="commercialFieldss">
              <div class="form-group ">
                @php
                  $waterViewOptComm = [
                      ['name' => 'Yes', 'target' => '.waterviewCommercial', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                      ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
                  ];
                @endphp
                <label class="fw-bold">Water View Needed:</label>
                <select class="grid-picker" name="has_water_view" id="waterViewComm"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($waterViewOptComm as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="form-group" id="waterViewOptComm" style="display:none;">
                @php
                  $water_views = [
                      ['name' => 'Bay/Harbor - Full', 'target' => ''],
                      ['name' => 'Bay/Harbor - Partial', 'target' => ''],
                      ['name' => 'Bayou', 'target' => ''],
                      ['name' => 'Beach', 'target' => ''],
                      ['name' => 'Canal', 'target' => ''],
                      ['name' => 'Creek', 'target' => ''],
                      ['name' => 'Gulf/Ocean - Full', 'target' => ''],
                      ['name' => 'Gulf/Ocean - Partial', 'target' => ''],
                      ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
                      ['name' => 'Intracoastal Waterway', 'target' => ''],
                      ['name' => 'Lagoon/Estuary', 'target' => ''],
                      ['name' => 'Lake', 'target' => ''],
                      ['name' => 'Lake - Chain of Lakes', 'target' => ''],
                      ['name' => 'Marina', 'target' => ''],
                      ['name' => 'Pond', 'target' => ''],
                      ['name' => 'River', 'target' => ''],
                      ['name' => 'None', 'target' => ''],
                  ];
                @endphp
                <label class="fw-bold">Water View Needed:</label>
                <select class="grid-picker" name="water_view[]" id="water_view" style="justify-content: flex-start;"
                  multiple required>
                  <option value="">Select</option>
                  @foreach ($water_views as $water_view)
                    <option value="{{ $water_view['name'] }}" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      data-target="{{ $water_view['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $water_view['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="form-group ">
                @php
                  $waterExtraOptComm = [
                      ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                      ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
                  ];
                @endphp
                <label class="fw-bold">Water Extras Needed:</label>
                <select class="grid-picker" name="has_water_extra" id="waterExtraComm"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($waterExtraOptComm as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="form-group" id="waterExtraOptComm" style="display: none;">
                @php
                  $water_extrasComm = [
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
                <label class="fw-bold">Water Extras Needed:</label>
                <select class="grid-picker" name="water_extras[]" id="water_extras"
                  style="justify-content: flex-start;" multiple required>
                  <option value="">Select</option>
                  @foreach ($water_extrasComm as $item)
                    <option value="{{ $item['name'] }}" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group ">
                @php
                  $waterFrontageOptComm = [
                      ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                      ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
                  ];
                @endphp
                <label class="fw-bold">Water Frontage Needed:</label>
                <select class="grid-picker" name="waterFrontageOpt" id="waterFrontageComm"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($waterFrontageOptComm as $item)
                    <option value="{{ $item['name'] }}" data-icon="<i class='{{ $item['icon'] }}'></i>"
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group " id="waterfrontageOptComm" style="display: none;">
                @php
                  $waterFrontageComm = [
                      ['name' => 'Bay/Harbor', 'target' => ''],
                      ['name' => 'Bayou', 'target' => ''],
                      ['name' => 'Beach', 'target' => ''],
                      ['name' => 'Brackish Water', 'target' => ''],
                      ['name' => 'Canal - Brackish', 'target' => ''],
                      ['name' => 'Canal - Freshwater', 'target' => ''],
                      ['name' => 'Canal - Saltwater', 'target' => ''],
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
                      ['name' => 'River', 'target' => ''],
                  ];

                @endphp
                <label class="fw-bold">Water Frontage Needed:</label>
                <select class="grid-picker" name="waterFrontage[]" id="has_water_fontage"
                  style="justify-content: flex-start;" multiple required>
                  <option value="">Select</option>
                  @foreach ($waterFrontageComm as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                @php
                  $waterAccessOptComm = [
                      ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                      ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question'],
                  ];
                @endphp
                <label class="fw-bold">Water Access Needed:</label>
                <select class="grid-picker" name="waterAccessOpt" id="waterAccessComm"
                  style="justify-content: flex-start">
                  <option value="">Select</option>
                  @foreach ($waterAccessOptComm as $item)
                    <option value="{{ $item['name'] }}" data-icon="<i class='{{ $item['icon'] }}'></i>"
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group" id="waterAccessOptComm" style="display:none;">
                @php
                  $waterAccessComm = [
                      ['name' => 'Bay/Harbor', 'target' => ''],
                      ['name' => 'Bayou', 'target' => ''],
                      ['name' => 'Beach', 'target' => ''],
                      ['name' => 'Beach - Access Deeded', 'target' => ''],
                      ['name' => 'Brackish Water', 'target' => ''],
                      ['name' => 'Canal - Brackish', 'target' => ''],
                      ['name' => 'Canal - Freshwater', 'target' => ''],
                      ['name' => 'Canal - Saltwater', 'target' => ''],
                      ['name' => 'Creek', 'target' => ''],
                      ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''],
                      ['name' => 'Gulf/Ocean', 'target' => ''],
                      ['name' => 'Gulf/Ocean to Bay', 'target' => ''],
                      ['name' => 'Intracoastal Waterway', 'target' => ''],
                      ['name' => 'Lagoon/Estuary', 'target' => ''],
                      ['name' => 'Lake', 'target' => ''],
                      ['name' => 'Lake - Chain of Lakes', 'target' => ''],
                      ['name' => 'Limited Access', 'target' => ''],
                      ['name' => 'Marina', 'target' => ''],
                      ['name' => 'Pond', 'target' => ''],
                      ['name' => 'River', 'target' => ''],
                  ];
                @endphp
                <label class="fw-bold">Water Access Needed:</label>
                <select class="grid-picker" name="water_access[]" id="water_access"
                  style="justify-content: flex-start;" multiple required>
                  <option value="">Select</option>
                  @foreach ($waterAccessComm as $water_access1)
                    <option value="{{ $water_access1['name'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>"
                      data-target="{{ $water_access1['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $water_access1['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </span>
            <div class="form-group ">
              @php
                  $dockRes = [
                      [
                          'name' => 'Yes',
                          'target' => '.dockRes',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                      [
                          'name' => 'Optional',
                          'target' => '.dockRes',
                          'icon' => 'fa-regular fa-circle-question',
                      ],
                  ];
              @endphp
              <label class="fw-bold">Dock Needed:</label>
              <select class="grid-picker" name="has_dock" id="hasDock"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($dockRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                      </option>
                  @endforeach
              </select>
              <div class="form-group" id="dockRes" style="display: none">
                  @php
                      $dockRes = [
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
                          ['name' => 'Other', 'target' => '.dock_res_other_opt'],
                      ];
                  @endphp
                  <select class="grid-picker" name="dock[]" id="dock"
                      style="justify-content: flex-start;" multiple>
                      <option value="">Select</option>
                      @foreach ($dockRes as $item)
                          <option value="{{ $item['name'] }}"
                              data-icon="<i class='fa-regular fa-circle-check'></i>"
                              data-target="{{ $item['target'] }}" class="card flex-row"
                              style="width:calc(33.3% - 10px);">
                              {{ $item['name'] }}
                          </option>
                      @endforeach
                  </select>
              </div>
              <div class="form-group main dock_res_other_opt d-none">
                  <label class="fw-bold">Dock Description Needed:</label>
                  <input type="text" name="dockDescription" id=""
                      class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="15">
            <div class="commercialFieldss">
              <div class="form-group ">
                @php
                  $viewOptComm = [
                      ['name' => 'Yes', 'target' => '.viewOptYes', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'No', 'target' => '.viewOptNo', 'icon' => 'fa-regular fa-circle-xmark'],
                      ['name' => 'Optional', 'target' => '.viewOptional', 'icon' => 'fa-regular fa-circle-question'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">View Preference Needed:</label>
                  <select class="grid-picker" name="viewOpt" id="viewOptionComm" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($viewOptComm as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group" id="viewOptComm" style="display:none;">
                  @php
                    $viewReferenceComm = [
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
                        ['name' => 'Beach', 'target' => ''],
                        ['name' => 'Other', 'target' => '.viewReferenceCommercialOther']
                    ];
                  @endphp
                  <label class="fw-bold">View Preference Needed:</label>
                  <select class="grid-picker" name="viewReference[]" id=""
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($viewReferenceComm as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group main viewReferenceCommercialOther d-none">
                    <label class="fw-bold">View Preference Needed:</label>
                    <input type="text" name="viewReferenceOther" class="form-control has-icon"
                      data-icon="fa-regular fa-circle-check" required>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="16">
            <span class="resFieldss">
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
                <label class="fw-bold"> Minimum Total Acreage Needed:</label>
                <select class="grid-picker" name="total_acreage" id="total_acreage"
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($acreageRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(25% - 10px);"
                      data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </span>
          </div>
          <div class="wizard-step" data-step="17">
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
              <label class="fw-bold">Furnishings Needed: </label>
              <select class="grid-picker" name="Furnishings[]" id="Furnishings" style="justify-content: flex-start;"
                multiple required>
                <option value="">Select</option>
                @foreach ($Furnishings as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="wizard-step" data-step="18">
            @php
              $poolNeeded = [
                  [
                      'name' => 'Yes',
                      'target' => '.poolNeededYes',
                      'icon' => 'fa-regular fa-circle-check',
                  ],
                  ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
              ];
            @endphp
            <div class="form-group" id="pool">
              <label class="fw-bold">Pool Needed: </label>
              <select class="grid-picker" name="pool" id="pool" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($poolNeeded as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group main poolNeededYes d-none">
              @php
                $poolNeededYes = [
                    [
                        'name' => 'Private',
                        'target' => '',
                        'icon' => 'fa-regular fa-circle-check',
                    ],
                    ['name' => 'Community', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                ];
              @endphp
              <label class="fw-bold">Pool Type Needed:</label>
              <select class="grid-picker" name="poolNeededOpt" id="pool" style="justify-content: flex-start;"
                required>
                <option value="">Select</option>
                @foreach ($poolNeededYes as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="resFieldss">
              <div class="form-group">
                @php
                  $viewOptRes = [
                      ['name' => 'Yes', 'target' => '.viewOptYes', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'No', 'target' => '.viewOptNo', 'icon' => 'fa-regular fa-circle-xmark'],
                      ['name' => 'Optional', 'target' => '.viewOptional', 'icon' => 'fa-regular fa-circle-question'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">View Preference Needed:</label>
                  <select class="grid-picker" name="viewOpt" id="viewRes" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($viewOptRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group" id="viewOptRes" style="display:none;">
                  @php
                    $viewReferenceRes = [
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
                        ['name' => 'Beach', 'target' => ''],
                        ['name' => 'Other', 'target' => '.viewReferenceResOther'],
                    ];
                  @endphp
                  <label class="fw-bold">View Preference Needed:</label>
                  <select class="grid-picker" name="viewReference[]" id=""
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($viewReferenceRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group main viewReferenceResOther d-none">
                    <label class="fw-bold">View Preference Needed:</label>
                    <input type="text" name="viewReferenceOther" class="form-control has-icon"
                      data-icon="fa-regular fa-circle-check" required>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="19">
            <div class="form-group">
              <label class="fw-bold">Is the tenant eligible or interested in leasing a property in 55+ and over
                communities?</label>
              <select class="grid-picker" name="is_tenant_eligible" id="is_tenant_eligible"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="wizard-step" data-step="20">
            <div class="form-group">
              <label class="fw-bold">Please provide a detailed description of the tenant’s specific requirements and
                preferences for leasing a property:</label>
              <textarea name="description_buyer_specific" id="buyer_specific_requirements" class="form-control" cols="15"
                rows="5" required></textarea>
            </div>
          </div>
          <div class="wizard-step" data-step="21">
            <div class="form-group ">
              @php
                $negotiableOpt = [
                    [
                        'name' => 'Yes',
                        'target' => '.negotiableOptYesRes',
                        'icon' => '<i class="fa-regular fa-circle-check"></i>',
                    ],
                    ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                ];
              @endphp
              <label class="fw-bold">Are there any non-negotiable amenities or property features that the tenant is
                seeking?
              </label>
              <select class="grid-picker" name="any_non_negotiable_factors" id="any_non_negotiable_factors"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($negotiableOpt as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group main negotiableOptYesRes d-none">
                <div class="resFieldss">
                  @php
                    $negotiableRes = [
                        ['name' => 'Garage', 'target' => ''],
                        ['name' => 'Carport', 'target' => ''],
                        ['name' => 'Pool', 'target' => ''],
                        ['name' => 'Waterfront', 'target' => ''],
                        ['name' => 'In-Unit Laundry', 'target' => ''],
                        ['name' => 'On-site Laundry', 'target' => ''],
                        ['name' => 'Washer and Dryer Hookup', 'target' => ''],
                        ['name' => 'Washer and Dryer', 'target' => ''],
                        ['name' => 'Covered Carport', 'target' => ''],
                        ['name' => 'First Floor Unit', 'target' => ''],
                        ['name' => 'Elevator', 'target' => ''],
                        ['name' => 'Pet Friendly', 'target' => ''],
                        ['name' => 'Balcony/Patio', 'target' => ''],
                        ['name' => 'Fitness Center/Gym', 'target' => ''],
                        ['name' => 'Central Heating', 'target' => ''],
                        ['name' => 'Central Air Conditioning', 'target' => ''],
                        ['name' => 'Fireplace', 'target' => ''],
                        ['name' => 'Walk-in Closet', 'target' => ''],
                        ['name' => 'Hardwood Floors', 'target' => ''],
                        ['name' => 'Tile Floors', 'target' => ''],
                        ['name' => 'Carpet Floors', 'target' => ''],
                        ['name' => 'Security System', 'target' => ''],
                        ['name' => 'Gated Community', 'target' => ''],
                        ['name' => 'HOA Community', 'target' => ''],
                        ['name' => '55 and Over Community', 'target' => ''],
                        ['name' => 'Specific School District', 'target' => ''],
                        ['name' => 'Accessibility Features', 'target' => ''],
                        ['name' => 'On-site Maintenance', 'target' => ''],
                        ['name' => 'On-site Management', 'target' => ''],
                        ['name' => 'Outdoor Space', 'target' => ''],
                        ['name' => 'Playground', 'target' => ''],
                        ['name' => 'Clubhouse', 'target' => ''],
                        ['name' => 'Storage Space', 'target' => ''],
                        ['name' => 'Study/Den/Office', 'target' => ''],
                        ['name' => 'Updated Kitchen', 'target' => ''],
                        ['name' => 'Updated Bathroom', 'target' => ''],
                        ['name' => 'Other', 'target' => '.negotiableOtherRes'],
                    ];
                  @endphp
                  <label class="fw-bold">What are the non-negotiable amenities or property features that the tenant will
                    not accept for a property?</label>
                  <select class="grid-picker" name="negotiable[]" id="any_non_negotiable_factors"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($negotiableRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group main negotiableOtherRes d-none">
                    <label class="fw-bold">What are the non-negotiable amenities or property features that the tenant will
                      not accept for a property?</label>
                    <input type="text" name="negotiableOther" id="" class="form-control has-icon"
                      data-icon="fa-regular fa-circle-check" required>
                  </div>
                </div>
                <div class="commercialFieldss">
                  @php
                    $negotiableCom = [
                        ['name' => 'Parking Spaces', 'target' => ''],
                        ['name' => 'Loading Dock', 'target' => ''],
                        ['name' => 'Warehouse Space', 'target' => ''],
                        ['name' => 'Office Space', 'target' => ''],
                        ['name' => 'Conference Room', 'target' => ''],
                        ['name' => 'Kitchenette/Break Room', 'target' => ''],
                        ['name' => 'Restrooms', 'target' => ''],
                        ['name' => 'Elevator', 'target' => ''],
                        ['name' => 'Handicap Accessibility', 'target' => ''],
                        ['name' => 'Security System', 'target' => ''],
                        ['name' => 'On-site Maintenance', 'target' => ''],
                        ['name' => 'On-site Management', 'target' => ''],
                        ['name' => 'Outdoor Space/Garden', 'target' => ''],
                        ['name' => 'Signage Opportunities', 'target' => ''],
                        ['name' => 'High-Speed Internet', 'target' => ''],
                        ['name' => 'Utilities Included', 'target' => ''],
                        ['name' => 'HVAC System', 'target' => ''],
                        ['name' => 'Natural Lighting', 'target' => ''],
                        ['name' => 'Storage Space', 'target' => ''],
                        ['name' => 'Open Floor Plan', 'target' => ''],
                        ['name' => 'Retail Frontage', 'target' => ''],
                        ['name' => 'Restaurant Space', 'target' => ''],
                        ['name' => 'Industrial Features', 'target' => ''],
                        ['name' => 'Flexibility for Renovations', 'target' => ''],
                        ['name' => 'Common Areas', 'target' => ''],
                        ['name' => 'Business Center', 'target' => ''],
                        ['name' => 'Gym/Fitness Facilities', 'target' => ''],
                        ['name' => 'Lounge Area', 'target' => ''],
                        ['name' => 'Reception Area', 'target' => ''],
                        ['name' => 'Security Guard', 'target' => ''],
                        ['name' => 'Fire Safety Systems', 'target' => ''],
                        ['name' => 'Energy-Efficient Features', 'target' => ''],
                        ['name' => 'Green Building Certification', 'target' => ''],
                        ['name' => 'Access to Public Transportation', 'target' => ''],
                        ['name' => 'Proximity to Highways', 'target' => ''],
                        ['name' => 'Visibility from Main Road', 'target' => ''],
                        ['name' => 'Other', 'target' => '.negotiableOtherCom'],
                    ];
                  @endphp
                  <label class="fw-bold">What are the non-negotiable amenities or property features that the tenant will
                    not accept for a property?</label>
                  <select class="grid-picker" name="negotiable[]" id="any_non_negotiable_factors"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($negotiableCom as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group main negotiableOtherCom d-none">
                    <label class="fw-bold">What are the non-negotiable amenities or property features that the tenant will
                      not accept for a property?</label>
                    <input type="text" name="negotiableOther" id="" class="form-control has-icon"
                      data-icon="fa-regular fa-circle-check" required>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="22">
            <h4>Tenant’s Prescreening Criteria:</h4>
            <span class="resFieldss">
              <div class="form-group">
                <label class="fw-bold">Does the tenant have a pet?</label>
                <select class="grid-picker" name="has_pets" id="has_pets" style="justify-content: flex-start;"
                  required>
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.pets_info';
                      } else {
                          $target = '';
                      }
                    @endphp
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="pets_info main d-none">
                <div class="form-group">
                  <label class="fw-bold">How many pets does the tenant have?</label>
                  <input type="text" name="totalPets" id="type_of_pet" class="form-control has-icon"
                    data-icon="fa-solid fa-dog" required>
                </div>
                <div class="form-group">
                  <label class="fw-bold">What type of pet(s) does the tenant have?</label>
                  <input type="text" name="petType" id="pets_bread" class="form-control has-icon"
                    data-icon="fa-solid fa-dog" required>
                </div>
                <div class="form-group">
                  <label class="fw-bold">What breed(s) are the pet(s)?</label>
                  <input type="text" name="petBreed" id="total_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-dog" required>
                </div>
                <div class="form-group">
                  <label class="fw-bold">What is the weight of the pet(s)?</label>
                  <input type="text" name="petWeight" id="total_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-dog" required>
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">How many people will be occupying the property:</label>
                <input type="number" name="how_many_occupying" class="form-control has-icon"
                  data-icon="fa-solid fa-users">
              </div>
              <div class="form-group">
                @php
                  $scoreRating = [
                      ['name' => 'Poor', 'target' => ''],
                      ['name' => 'Fair', 'target' => ''],
                      ['name' => 'Good', 'target' => ''],
                      ['name' => 'Very Good', 'target' => ''],
                      ['name' => 'Excellent', 'target' => ''],
                  ];
                @endphp
                <label class="fw-bold">What is the tenant’s credit score rating? </label>
                <select class="grid-picker" name="tenant_credit_score" id="any_non_negotiable_factors"
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($scoreRating as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">What is the household monthly net income of the tenant?</label>
                <input type="number" step="0.01" placeholder="0.00" min="1"
                  name="monthly_household_income" id="monthly_household_income" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar-sign">
              </div>
              <div class="form-group">
                <label class="fw-bold">
                  Has the tenant been convicted of a felony within the last 7 years?
                </label>
                @php
                  $convictedRes = [
                      ['target' => '.custom_convictedRes', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                      ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                  ];
                @endphp
                <select name="convicted" id="convicted" class="grid-picker" style="justify-content: flex-start;">
                  <option value=""></option>
                  @foreach ($convictedRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group main custom_convictedRes d-none">
                  <label class="fw-bold" for="custom_convicted">Explain when and what for:</label>
                  <input type="text" name="custom_convicted" id="custom_convicted"
                    placeholder="" class="form-control has-icon"
                    data-icon="fa-regular fa-circle-check" required>
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">
                  Has the tenant had any prior evictions within the last 7 years?
                </label>
                @php
                  $evictedRes = [
                      ['target' => '.custom_evictedRes', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                      ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                  ];
                @endphp
                <select name="evicted" id="evicted" class="grid-picker" style="justify-content: flex-start;">
                  <option value=""></option>
                  @foreach ($evictedRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group main custom_evictedRes d-none">
                  <label class="fw-bold" for="custom_evicted_why">Explain when and why:</label>
                  <input type="text" name="custom_evicted" id="custom_evicted" placeholder=""
                    class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                </div>
              </div>
              {{-- <div class="form-group">
                <label class="fw-bold">
                  Has the tenant been involved in any prior lease violations
                </label>
                @php
                  $violationsRes = [
                      ['target' => '.custom_prior_leaseRes', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                      ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                  ];
                @endphp
                <select name="lease_violations" id="lease_violations" class="grid-picker"
                  style="justify-content: flex-start;">
                  <option value=""></option>
                  @foreach ($violationsRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="{{ $item['icon'] }}" ></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group custom_prior_leaseRes d-none">
                  <label class="fw-bold" for="custom_evicted">Explain when and why:</label>
                  <input type="text" name="lease_violation_why" id="lease_violation_why"
                    placeholder="Explain when and why" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                    required>
                </div>
              </div> --}}
              {{-- <div class="form-group">
                <label class="fw-bold">
                  Does the tenant have any outstanding balances with previous landlords?
                </label>
                @php
                  $landlordsRes = [
                      ['target' => '.previousLnadRes', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                      ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                  ];
                @endphp
                <select name="previous_land" id="previous_land" class="grid-picker"
                  style="justify-content: flex-start;">
                  <option value=""></option>
                  @foreach ($landlordsRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group previousLnadRes d-none">
                  <label class="fw-bold" for="custom_evicted_why">Explain when and why:</label>
                  <input type="text" name="custom_previous_land" id="custom_evicted"
                    placeholder="Explain when and why" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                    required>
                </div>
              </div> --}}
              
            </span>
            <span class="commercialFieldss">
              <div class="form-group">
                <label class="fw-bold">How many people will be occupying the property:</label>
                <input type="number" name="how_many_occupying" class="form-control has-icon"
                  data-icon="fa-solid fa-users">
              </div>
              <div class="form-group">
                @php
                  $scoreRatingComm = [
                      ['name' => 'Poor', 'target' => ''],
                      ['name' => 'Fair', 'target' => ''],
                      ['name' => 'Good', 'target' => ''],
                      ['name' => 'Very Good', 'target' => ''],
                      ['name' => 'Excellent', 'target' => ''],
                  ];
                @endphp
                <label class="fw-bold">What is the tenant’s credit score rating? </label>
                <select class="grid-picker" name="tenant_credit_score" id="any_non_negotiable_factors"
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($scoreRatingComm as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">What is the monthly household net income?</label>
                <input type="number" step="0.01" placeholder="0.00" min="1"
                  name="monthly_household_income" id="monthly_household_income" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar-sign">
              </div>
              <div class="form-group">
                <label class="fw-bold">
                  Has the tenant been convicted of a felony within the last 7 years?
                </label>
                @php
                  $convictedCommercial = [
                      [
                          'target' => '.custom_convictedCommercial',
                          'name' => 'Yes',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                  ];
                @endphp
                <select name="convicted" id="convicted" class="grid-picker" style="justify-content: flex-start;">
                  <option value=""></option>
                  @foreach ($convictedCommercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group main custom_convictedCommercial d-none">
                  <label class="fw-bold" for="custom_convicted">Explain when and what for:</label>
                  <input type="text" name="custom_convicted" id="custom_convicted"
                    placeholder="" class="form-control has-icon"
                    data-icon="fa-regular fa-circle-check" required>
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">
                  Has the tenant had any prior evictions within the last 7 years?
                </label>
                @php
                  $evictedCommercial = [
                      [
                          'target' => '.custom_evictedCommercial',
                          'name' => 'Yes',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                  ];
                @endphp
                <select name="evicted" id="evicted" class="grid-picker" style="justify-content: flex-start;">
                  <option value=""></option>
                  @foreach ($evictedCommercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="{{ $item['icon'] }}" style="font-size:24px;"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group main custom_evictedCommercial d-none">
                  <label class="fw-bold" for="custom_evicted_why">Explain when and why:</label>
                  <input type="text" name="custom_evicted" id="custom_evicted" placeholder=""
                    class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                </div>
              </div>
            </span>
          </div>
          <div class="wizard-step" data-step="23">
            <h4>Tenant’s Agent Representation:</h4>
            <div class="form-group">
              <label class="fw-bold">
                Would the tenant compensate an agent if they find them a property that meets their requirements?
              </label>
              @php
                $violationsRes = [
                    ['target' => '.compensate', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                    ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                ];
              @endphp
              <select name="compensateOpt" id="lease_violations" class="grid-picker"
                style="justify-content: flex-start;">
                <option value=""></option>
                @foreach ($violationsRes as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $item['icon'] }}" ></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group main compensate d-none">
                <label class="fw-bold" for="custom_evicted">What is the finder's fee that the tenant is offering to
                  pay
                  an agent if they find a suitable property to lease?</label>
                @php
                  $compensateOther = [
                      ['name' => '1 month’s rent', 'target' => ''],
                      ['name' => '50% of one month’s rent', 'target' => ''],
                      ['name' => '10% of the value of the lease', 'target' => ''],
                      ['name' => '6% of the value of the lease', 'target' => ''],
                      ['name' => 'Negotiable', 'target' => ''],
                      ['name' => 'Other', 'target' => '.compensateOther'],
                  ];
                @endphp
                <select name="compensateOtherOpt" id="lease_violations" class="grid-picker"
                  style="justify-content: flex-start;">
                  <option value=""></option>
                  @foreach ($compensateOther as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="fa-regular fa-circle-check" ></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group main compensateOther d-none">
                  <label class="fw-bold" for="custom_evicted">What is the finder's fee that the tenant is offering to
                    pay an agent if they find a suitable property to lease?</label>
                  <input type="text" name="compensateOther" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined" required>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="fw-bold">
                Does the tenant have a real estate agent representing them?
              </label>
              @php
                $representedRes = [
                    ['target' => '.represented', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                    ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                ];
              @endphp
              <select name="represented" id="represented" class="grid-picker"
                style="justify-content: flex-start;">
                <option value=""></option>
                @foreach ($representedRes as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-column" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $item['icon'] }}" ></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group represented d-none">
                <label class="fw-bold" for="custom_evicted">Does the tenant request that the landlord pay the tenant’s agent commission?</label>
                @php
                  // $representedOther = [
                  //     ['name' => '1 month’s rent', 'target' => ''],
                  //     ['name' => '50% of one month’s rent', 'target' => ''],
                  //     ['name' => '10% of the value of the lease', 'target' => ''],
                  //     ['name' => '6% of the value of the lease', 'target' => ''],
                  //     ['name' => 'Negotiable', 'target' => ''],
                  //     ['name' => 'Other', 'target' => '.compensateOther'],
                  // ];
                  $representedOther = [
                    ['target' => '.representedYesOpt', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                    ['target' => '.representedNoOpt', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                  ];
                @endphp
                <select name="agentCommissionRequested" id="representedRes" class="grid-picker"
                  style="justify-content: flex-start;">
                  <option value=""></option>
                  @foreach ($representedOther as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="fa-regular fa-circle-check" ></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group representedYesOpt d-none">
                  <label class="fw-bold" for="custom_evicted">What compensation is the tenant requesting the landlord to pay to the tenant’s agent?</label>
                  @php
                    $representedOtherOpt = [
                        ['name' => '1 month’s rent', 'target' => ''],
                        ['name' => '50% of one month’s rent', 'target' => ''],
                        ['name' => '10% of the value of the lease', 'target' => ''],
                        ['name' => '6% of the value of the lease', 'target' => ''],
                        ['name' => 'Negotiable', 'target' => ''],
                        ['name' => 'Other', 'target' => '.compensateReqOther'],
                    ];
                  @endphp
                  <select name="agentCompensationAmountReq" id="representedResYes" class="grid-picker"
                    style="justify-content: flex-start;">
                    <option value=""></option>
                    @foreach ($representedOtherOpt as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check" ></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group main compensateReqOther d-none">
                    <label class="fw-bold" for="custom_evicted">What compensation is the tenant requesting the landlord to pay to the tenant’s agent?</label>
                    <input type="text" name="agentCompensationAmountReqOther" class="form-control has-icon"
                      data-icon="fa-regular fa-circle-check" required>
                  </div>
                </div>
                <div class="form-group main representedNoOpt d-none">
                  <label class="fw-bold" for="custom_evicted">Would the tenant be willing to pay an agent's commission if the landlord does not agree to
                    cover it, for bringing a property to the tenant that they end up leasing?</label>
                  @php
                    // $representedOtherOpt = [
                    //     ['name' => '1 month’s rent', 'target' => ''],
                    //     ['name' => '50% of one month’s rent', 'target' => ''],
                    //     ['name' => '10% of the value of the lease', 'target' => ''],
                    //     ['name' => '6% of the value of the lease', 'target' => ''],
                    //     ['name' => 'Negotiable', 'target' => ''],
                    //     ['name' => 'Other', 'target' => '.compensateReqOther'],
                    // ];
                    $tenantPaysOther = [
                      ['target' => '.tenantPaysYesOpt', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                      ['target' => '.tenantPaysNoOpt', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                  @endphp
                  <select name="tenantPaysTheAgent" id="representedResYes" class="grid-picker"
                    style="justify-content: flex-start;">
                    <option value=""></option>
                    @foreach ($tenantPaysOther as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check" ></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group main tenantPaysYesOpt d-none">
                    <label class="fw-bold" for="custom_evicted">What compensation is the tenant requesting the landlord to pay to the tenant’s agent?</label>
                    @php
                      $tenantPaysOtherOpt = [
                        ['name' => '1 month’s rent', 'target' => ''],
                        ['name' => '50% of one month’s rent', 'target' => ''],
                        ['name' => '10% of the value of the lease', 'target' => ''],
                        ['name' => '6% of the value of the lease', 'target' => ''],
                        ['name' => 'Negotiable', 'target' => ''],
                        ['name' => 'Other', 'target' => '.tenantPayAmount'],
                      ];
                    @endphp
                    <select name="tenantPaysAmount" id="representedResYes" class="grid-picker"
                      style="justify-content: flex-start;">
                      <option value=""></option>
                      @foreach ($tenantPaysOtherOpt as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-circle-check" ></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group main tenantPayAmount d-none">
                      <label class="fw-bold" for="custom_evicted">What compensation is the tenant willing to offer to an agent?</label>
                      <input type="text" name="tenantPaysAmountOther" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="24">
            <h4>Agent Info: </h4>
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">First Name:</label>
                <input type="text" name="agent_first_name" id="agent_first_name" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-user"
                  value="{{ Auth::user()->first_name }}" required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Last Name:</label>
                <input type="text" name="agent_last_name" id="agent_last_name" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-user" value="{{ Auth::user()->last_name }}"
                  required>
              </div>
            </div>
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">Phone Number:</label>
                <input type="text" name="agent_phone" id="agent_phone" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-phone" value="{{ Auth::user()->phone }}"
                  required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Email:</label>
                <input type="text" name="agent_email" id="agent_email" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-envelope" value="{{ Auth::user()->email }}"
                  required>
              </div>
            </div>
            @if(Auth::user()->user_type == 'agent')
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">Brokerage:</label>
                <input type="text" name="agent_brokerage" id="agent_brokerage" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-handshake"
                  value="{{ Auth::user()->brokerage }}" required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Real Estate License #:</label>
                <input type="text" name="agent_license_no" id="agent_license_no" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-id-card"
                  value="{{ Auth::user()->license_no }}" required>
              </div>
            </div>
            @endif
            <div class="form-group row">
              @if(Auth::user()->user_type == 'agent')
              <div class="form-group col-md-6">
                <label class="fw-bold">NAR Member ID (NRDS ID): </label>
                <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-id-card-clip" value="{{ Auth::user()->mls_id }}"
                  required>
              </div>
              @endif
              <div class="form-group col-md-6">
                @if (Auth::user()->user_type == 'agent')
                  <label class="fw-bold">Listed By: Real Estate Agent</label>
                @else
                  <label class="fw-bold">Listed By: Tenant</label>
                @endif
                {{-- <input type="text" name="realStateAgent" id="" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined" required> --}}
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="25">
            <label class="fw-bold">For a more personalized listing, you can include a picture of yourself and/or include a video of yourself providing additional information about your background and criteria.</label>
            <div class="row">
              <div class="col-6  form-group">
                <div class="videoBox ">
                  <label class="fw-bold">Video:</label>
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
              <div class="col-6  form-group">
                <div class="upload">
                  <label class="fw-bold">Photo:</label>
                  <div class="wrapper">
                    <div class="box">
                      <div class="js--image-preview"></div>
                      <div class="upload-options">
                        <label>
                          <input type="file" name="photo" class="image-upload" accept="image/*" />
                        </label>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          {{-- ── Step 26: AI Questions / Chatbot Knowledge Base ── --}}
          <div class="wizard-step" data-step="26">
            <h4 class="mb-1">AI Questions / Chatbot Knowledge Base</h4>
            <div class="alert alert-info mb-3" role="alert">
              <i class="fa-solid fa-robot me-1"></i>
              <strong>Internal only</strong> — these answers are never shown publicly. They help power an AI chatbot that landlords can use to learn more about your criteria. All fields are optional; answer only what you are comfortable sharing.
            </div>
            @php
              $faqQuestions = config('tenant_ai_faq.questions');
              $faqCategories = collect($faqQuestions)->where('commercial_only', false)->groupBy('category');
              $commercialQuestions = collect($faqQuestions)->where('commercial_only', true)->groupBy('category');
            @endphp
            @foreach ($faqCategories as $category => $questions)
              <h5 class="mt-4 mb-2 border-bottom pb-1 text-primary">{{ $category }}</h5>
              @foreach ($questions as $q)
                <div class="form-group">
                  <label class="fw-bold">{{ $q['label'] }}</label>
                  <textarea name="ai_faq[{{ $q['key'] }}]" class="form-control" rows="2" placeholder="Optional — type your answer here"></textarea>
                </div>
              @endforeach
            @endforeach
            <div class="commercialFieldss mt-3" style="display:none;">
              <div class="alert alert-secondary" role="alert">
                <i class="fa-solid fa-building me-1"></i>
                <strong>Commercial Add-On Questions</strong> — the following questions apply to commercial listings only.
              </div>
              @foreach ($commercialQuestions as $category => $questions)
                <h5 class="mt-4 mb-2 border-bottom pb-1 text-primary">{{ $category }}</h5>
                @foreach ($questions as $q)
                  <div class="form-group">
                    <label class="fw-bold">{{ $q['label'] }}</label>
                    <textarea name="ai_faq[{{ $q['key'] }}]" class="form-control" rows="2" placeholder="Optional — type your answer here"></textarea>
                  </div>
                @endforeach
              @endforeach
            </div>
          </div>
          {{-- ── /Step 26 ── --}}
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
  <template class="city_temp">
    <tr>
      <td>
        <div class="input-cover input-cover-0"><label for="cities" class="input-icon"><i
              class="fa-solid fa-city "></i></label>
          <input type="text" name="cities[]" data-type="cities" placeholder="" class="form-control has-icon search_places"
            data-icon="fa-solid fa-tree-city">
        </div>
      </td>
    </tr>
  </template>
  <template class="county_temp">
    <tr>
      <td>
        <div class="input-cover input-cover-0"><label for="cities" class="input-icon"><i
              class="fa-solid fa-tree-city "></i></label>
          <input type="text" name="counties[]" placeholder="" data-type="counties"
            class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
          >
        </div>
      </td>
    </tr>
  </template>
  <template class="state_temp">
    <tr>
      <td>
        <div class="input-cover input-cover-0"><label for="cities" class="input-icon"><i
              class="fa-solid fa-flag-usa "></i></label>
          <input type="text" name="state[]" data-type="state" placeholder=""
            class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
            data-msg-required="Please enter state">
        </div>
      </td>
    </tr>
  </template>
@endsection
@push('scripts')
  <script>
    function add_county_row() {
      var county_row = $('.county_temp').html();
      $('.county_btn_row').before(county_row);
      initialize();
    }

    function add_city_row() {
      var city_row = $('.city_temp').html();
      $('.city_btn_row').before(city_row);
      initialize();
    }

    function add_state_row() {
      var state_row = $('.state_temp').html();
      $('.state_btn_row').before(state_row);
      initialize();
    }
  </script>
  <script>
    function changeAuctionType(v) {
      if (v == "Auction (Timer)") {
        $('.auction_length').val("");
        $('.auction_length').parent().children('.option-container').removeClass('active');
        $('.traditional-length').hide();
        $('.normal-length').show();
        $('.auction_length_cover').show();
      } else {
        $('.auction_length').val("");
        $('.auction_length').parent().children('.option-container').removeClass('active');
        $('.traditional-length').show();
        $('.normal-length').hide();
        $('.auction_length_cover').hide();
      }
    }
    // document.getElementById('auction_type').change();
    $(function() {
      changeAuctionType("Auction (Timer)");
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
        $('.commercial_show').addClass('d-none');
        $('.residential_show').removeClass('d-none');
        $('.add_commercial').remove();
        // nisar changing
        $('#leasableSqft').remove();
        $('#sfPrice').remove();
        $('.resFieldss').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
          $(this).show()
        });
        $('.commercialFieldss').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
          $(this).hide()

        });



      } else if (p == "Commercial Property") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').show();
        $('.commercial_show').removeClass('d-none');
        $('.residential_show').addClass('d-none');
        $('.remove_comm').hide();
        $('.remove_residential').show();
        $('#remove-bed').remove();
        $('#heatedSqft').remove();
        $('.commercialFieldss').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
          $(this).show()

        });
        $('.resFieldss').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
          $(this).hide()
        });
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
      console.log(v);
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
          }
        }
      });
      // setTimeout(check_custom, 500);
    }
  </script>
  <script>
    $(function() {
      StepWizard.init();
    });

    var StepWizard = {
      init: function() {
        StepWizard.total_steps = $('.wizard-step').length;

        var v = $(".mainform").validate({
          errorClass: "text-error text-danger w-100",
          onkeyup: false,
          onfocusout: false,
          /* submitHandler: function() {
              // alert("Submitted, thanks!");
              $(".mainform").submit();
          } */
        });
        StepWizard.setStep();
        property_type;
        $('#property_type').on('change', function() {
          property_type = $(this).val();
          // Count the remaining steps without removing them



        });
        $('.wizard-step-next').click(function(e) {
          //   console.log(StepWizard.currentStep)

          if (v.form()) {
            if ($('.wizard-step.active').next().is('.wizard-step')) {

              // $('.wizard-step.active').removeClass('active').next().addClass('active');
              $('.wizard-step.active').removeClass('active');
              console.log(StepWizard.currentStep)
              if(StepWizard.currentStep == 11 && property_type == 'Residential Property') {
                StepWizard.nextStep = 13;
                StepWizard.backStep = 11;
              }else if(StepWizard.currentStep == 14 && property_type == 'Residential Property') {
                StepWizard.nextStep = 16;
                StepWizard.backStep = 14;
              }else if (StepWizard.currentStep == 15 && property_type == 'Commercial Property') {
                StepWizard.nextStep = 20;
                StepWizard.backStep = 15;
              } else if (StepWizard.currentStep == 8 && property_type == 'Commercial Property') {
                StepWizard.nextStep = 10;
                StepWizard.backStep = 8;
              } else {
                StepWizard.backStep = StepWizard.currentStep;
              }
              $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
              StepWizard.setStep();
              if (StepWizard.currentStep == 41 &&
                (property_type == 'Residential Property' || property_type ==
                  'Income Property')
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if (StepWizard.currentStep == 75 &&
                (property_type == 'Commercial Property' || property_type ==
                  'Business Opportunity')
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
            }
          }
        });
        $('.wizard-step-back').click(function(e) {
          if ($('.wizard-step.active').prev().is('.wizard-step')) {

            $('.wizard-step.active').removeClass('active');
            $('[ data-step="' + StepWizard.backStep + '"]').addClass("active");
            StepWizard.setStep();
            console.log(StepWizard.currentStep)
            // if (StepWizard.currentStep == 48 && property_type ==
            //   'Commercial Property') {
            //   StepWizard.backStep = 46;
            // }
            if (StepWizard.currentStep == 13 && property_type == 'Residential Property') {
              StepWizard.backStep = 11;
              StepWizard.nextStep = 13;
            }else if (StepWizard.currentStep == 16 && property_type == 'Residential Property') {
              StepWizard.backStep = 14;
              StepWizard.nextStep = 16;
            }else if (StepWizard.currentStep == 19 && property_type == 'Commercial Property') {
              StepWizard.backStep = 14;
              StepWizard.nextStep = 19;
            } else if (StepWizard.currentStep == 10 && property_type == 'Commercial Property') {
              StepWizard.nextStep = 10;
              StepWizard.backStep = 8;
            } else {
              StepWizard.backStep = StepWizard.currentStep - 1;
            }
          }
        });
        // Assuming the code provided is within a function or a document.ready block

        $('.wizard-step-finish').click(function(e) {

          //Remove All the SLides Except THe Vacant Land
          //   if (property_type === 'Vacant Land (Current Use)') {
          //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
          //       return parseInt($(this).attr('data-step')) >= 8 && parseInt($(this)
          //         .attr('data-step')) <= 76;
          //     });
          //     $stepsToRemove.each(function() {
          //       $(this).closest('div[data-step]').remove();
          //     });
          //   }
          //Remove All the SLides Except THe Residential and Commercial Property
          //   if (property_type == 'Residential Property' || property_type == 'Income Property') {
          //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
          //       return parseInt($(this).attr('data-step')) >= 42 && parseInt($(this)
          //         .attr('data-step')) <= 91;
          //     });

          //     $stepsToRemove.each(function() {
          //       $(this).closest('div[data-step]').remove();
          //     });
          //   }
          //Remove All the SLides Except THe Commercial and Business Opportunity
          //   if (property_type === 'Commercial Property' || property_type ===
          //     'Business Opportunity') {
          //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
          //       var stepValue = parseInt($(this).attr('data-step'));
          //       return (stepValue >= 8 && stepValue <= 41) || (stepValue >= 77 &&
          //         stepValue <= 91);
          //     });

          //     $stepsToRemove.each(function() {
          //       $(this).closest('div[data-step]').remove();
          //     });
          //   }
          //Remove All the SLides Except THe Commercial and Business Opportunity
          // Submitting The Form After Removing the Extra slide to get rid of null Data
          $('.mainform').submit();
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
            StepWizard.data_step = k;
            StepWizard.nextStep = k + 1;
          }
        });
        StepWizard.stepChanged();
      },
      stepChanged: function() {
        var comp = 0;
        if (property_type === 'Residential Property') {
          // Calculate progress for income and residential property steps (9 to 41)
          comp = 20 + (((StepWizard.currentStep - 7) / (26 - 7)) * 80);
        } else if (property_type === 'Commercial Property') {
          comp = 20 + (((StepWizard.currentStep - 7) / (26 - 7)) * 80);
        } else if (property_type === 'Vacant Land (Current Use)') {
          // Calculate progress for vacant land steps (77 to 91)
          comp = 20 + (((StepWizard.currentStep - 7) / (26 - 7)) * 80);
        } else {
          comp = ((StepWizard.currentStep - 1) / 8) * 20;
        }

        $('.steps-progress-percent').animate({
          width: comp.toFixed(0) + '%',
        });
      },
      currentStep: 1,
      nextStep: 2,
      bsckStep: 1,
      total_steps: 0,
      data_step: 1,
    };
    $('#property_picture').click(function() {
      $('.wizard-step-next').hide();
      $('.wizard-step-finish').show();
    });
    $('#property_picture1').click(function() {
      $('.wizard-step-next').hide();
      $('.wizard-step-finish').show();
    });
  </script>
  <script>
    // google.maps.event.addDomListener(window, 'load', initialize);
    function initialize() {
      var inputField = document.getElementsByClassName('search_places');

      for (var i = 0; i < inputField.length; i++) {
        var t = inputField[i].dataset.type;
        console.log(t);
        if (t === "cities") {
          var options = {
            types: ['(cities)'],
            componentRestrictions: {
              country: "us"
            },
          };
        } else if (t === "state") {
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
            console.log("place", place);
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
    var viewRefRes = $('#viewOptRes');
    $('#viewRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? viewRefRes.show():
        viewRefRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var viewRefComm = $('#viewOptComm');
    $('#viewOptionComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? viewRefComm.show():
        viewRefComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterViewRes = $('#waterViewResOpt');
    $('#waterViewRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterViewRes.show():
        waterViewRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterExtraRes = $('#waterExtraResOpt');
    $('#waterExtraRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterExtraRes.show():
        waterExtraRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterFrontageRes = $('#waterFrontageResOpt');
    $('#waterFrontageRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterFrontageRes.show():
        waterFrontageRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterAccessRes = $('#waterAccessResOpt');
    $('#waterAccessRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterAccessRes.show():
        waterAccessRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    //dock-show/hide
    var dockRes = $('#dockRes');
    $('#hasDock').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? dockRes.show():
        dockRes.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });

    var garageParkingComm = $('#garageParkingOpt');
    $('#garageParkingComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? garageParkingComm.show():
        garageParkingComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterViewComm = $('#waterViewOptComm');
    $('#waterViewComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterViewComm.show():
        waterViewComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterExtraComm = $('#waterExtraOptComm');
    $('#waterExtraComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterExtraComm.show():
        waterExtraComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterFrontageComm = $('#waterfrontageOptComm');
    $('#waterFrontageComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterFrontageComm.show():
        waterFrontageComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var waterAccessComm = $('#waterAccessOptComm');
    $('#waterAccessComm').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Yes') || $(this).val().includes('Optional')) ? waterAccessComm.show():
        waterAccessComm.hide()
      //   end
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');
      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
  </script>
  <script>
    // Video Preview
    $(document).ready(function($) {
      // Click button to activate hidden file input
      $('.fileuploader-btn').on('click', function() {
        $('.fileuploader').click();
      });
      // Click above calls the open dialog box
      // Once something is selected the change function will run
      $('.fileuploader').change(function() {
        // Check if a file has been selected
        if (this.files && this.files[0]) {
          var reader = new FileReader();
          var file = this.files[0];

          if (file.type.startsWith('image/')) {
            reader.onload = function(event) {
              $('.video').empty();
              $('.video').append('<img src="' + event.target.result + '" width="200" height="160">');
            };
            reader.readAsDataURL(file);
            $('.video').removeClass('bgImg');
          } else if (file.type.startsWith('video/')) {
            reader.onload = function(event) {
              var fileContent = event.target.result;
              $('.video').empty();
              $('.video').append('<video src="' + fileContent +
                '" width="200" height="160" controls autoplay></video>');
            };
            reader.readAsDataURL(file);
            $('.video').removeClass('bgImg');
          } else {
            // File is neither image nor video
            alert('Please select either an image or a video file.');
            this.value = ''; // Clear the file input
            return;
          }
          // Hide the preview icon and show the upload button
          $('.video').removeClass('bgImg');
          $('.upload-button').show();
        } else {
          // If no file is selected, and if .video container is empty, show the preview icon and hide the add button
          if ($('.video').is(':empty')) {
            $('.video').addClass('bgImg');
            $('.upload-button').show();
          } else {
            // If a file already exists, hide the preview icon and show the add button
            $('.video').removeClass('bgImg');
            $('.upload-button').show();
          }
        }
      });
    });
    // Video Preview
    function initImageUpload(box) {
      let uploadField = box.querySelector('.image-upload');
      uploadField.addEventListener('change', getFile);
      function getFile(e) {
        let file = e.currentTarget.files[0];
        checkType(file);
      }
      function previewImage(file) {
        let thumb = box.querySelector('.js--image-preview'),
          reader = new FileReader();
        reader.onload = function() {
          thumb.style.backgroundImage = 'url(' + reader.result + ')';
        }
        reader.readAsDataURL(file);
        thumb.className += ' js--no-default';
      }
      function checkType(file) {
        let imageType = /image.*/;
        if (!file.type.match(imageType)) {
          throw 'Datei ist kein Bild';
        } else if (!file) {
          throw 'Kein Bild gewählt';
        } else {
          previewImage(file);
        }
      }
    }

    // initialize box-scope
    var boxes = document.querySelectorAll('.box');
    for (let i = 0; i < boxes.length; i++) {
      let box = boxes[i];
      initDropEffect(box);
      initImageUpload(box);
    }
    /// drop-effect
    function initDropEffect(box) {
      let area, drop, areaWidth, areaHeight, maxDistance, dropWidth, dropHeight, x, y;
      // get clickable area for drop effect
      area = box.querySelector('.js--image-preview');
      area.addEventListener('click', fireRipple);
      function fireRipple(e) {
        area = e.currentTarget
        // create drop
        if (!drop) {
          drop = document.createElement('span');
          drop.className = 'drop';
          this.appendChild(drop);
        }
        // reset animate class
        drop.className = 'drop';
        // calculate dimensions of area (longest side)
        areaWidth = getComputedStyle(this, null).getPropertyValue("width");
        areaHeight = getComputedStyle(this, null).getPropertyValue("height");
        maxDistance = Math.max(parseInt(areaWidth, 10), parseInt(areaHeight, 10));
        // set drop dimensions to fill area
        drop.style.width = maxDistance + 'px';
        drop.style.height = maxDistance + 'px';
        // calculate dimensions of drop
        dropWidth = getComputedStyle(this, null).getPropertyValue("width");
        dropHeight = getComputedStyle(this, null).getPropertyValue("height");
        // calculate relative coordinates of click
        // logic: click coordinates relative to page - parent's position relative to page - half of self height/width to make it controllable from the center
        x = e.pageX - this.offsetLeft - (parseInt(dropWidth, 10) / 2);
        y = e.pageY - this.offsetTop - (parseInt(dropHeight, 10) / 2) - 30;
        // position drop and animate
        drop.style.top = y + 'px';
        drop.style.left = x + 'px';
        drop.className += ' animate';
        e.stopPropagation();

      }
    }
    function validateFile() {
      const fileInput = document.querySelector('input[name="video"]');
      const fileSizeError = document.getElementById('fileSizeError');
      const maxFileSize = 5 * 1024 * 1024; // 5MB
      if (fileInput.files[0].size > maxFileSize) {
        fileSizeError.style.display = 'block';
        fileInput.value = ''; // Clear the file input to prevent submission
        return false;
      } else {
        fileSizeError.style.display = 'none';
        return true;
      }
    }
    function show_garage_opt() {
      var h = $('#garage').val();
      if (h == "Yes" || h == "Optional") {
        $('.garage_opt').show();
      } else {
        $('.garage_opt').hide();
      }
    }
    $(function() {
      show_garage_opt("");
    });
  </script>
  <script>
    function show_carport_opt() {
      var h = $('#carport').val();
      if (h == "Yes" || h == "Optional") {
        $('.carport_opt').show();
      } else {
        $('.carport_opt').hide();
      }
    }
    $(function() {
      show_carport_opt("");
    });
  </script>
  <script>
    function show_water_views() {
      var h = $('#need_water_view').val();
      if (h == "Yes" || h == "Optional") {
        $('.water_views').show();
      } else {
        $('.water_views').hide();
      }
    }
    $(function() {
      show_water_views("");
    });
  </script> 
  <script>
    function show_extra_water_views() {
      var h = $('#water_extras').val();
      if (h == "Yes" || h == "Optional") {
        $('.Water_extras_opt').show();
      } else {
        $('.Water_extras_opt').hide();
      }
    }
    $(function() {
      show_extra_water_views("");
    });
  </script>
  <script>
    $(document).ready(function(){
      $('div.main.d-none').find('input, select').prop('disabled', true);
      $(document).click(function(){
        $('div.main').each((i, item) => {
          if ($(item).hasClass('d-none')) {
            $(item).find('input, select').prop('disabled', true);
          }else{
            $(item).find('input, select').prop('disabled', false);
          }
        })
      })
    })
  </script>
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places,drawing&callback=initialize">
  </script>
@endpush

