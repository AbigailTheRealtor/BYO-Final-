@extends('layouts.main')

{{-- @php
    $getState=collect($countries)->where('name','algeria')->first();
    dd($getState->states->first()->name);
    dd($getState);
@endphp
@foreach ($countries as $country)


{{ dd($country->name); }}
@endforeach --}}

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
  <style>
    /* .dropdown-menu {
                                                                                                                                                                                                                                                                                                            width: 100%;
                                                                                                                                                                                                                                                                                                          } */

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


    .fa-city:before {
      color: #11b7cf;
    }

    .fa-tree-city:before {
      color: #11b7cf;
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
  @php
    $yes_or_nos = [
        ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
    ];
  @endphp
  <div class="container pt-5 pb-5">
    <div class="card">
      <div class="row">
        <div class="col-12 p-4">
          <form class="p-4 pt-0 mainform" action="{{ route('buyer.add-auction') }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @push('scripts')
              <script>
                $(function() {
                  $('#working_with_agent').change(function() {
                    var val = $(this).val();
                    if (val == "No") {
                      $('.wizard-step-next').attr("disabled", "disabled");
                      $('.yes_message').text(
                        "This is a service for buyers that are not currently working with a licensed agent."
                      );
                    } else {
                      $('.wizard-step-next').removeAttr("disabled");
                      $('.yes_message').text("");
                    }
                  });
                });
              </script>
            @endpush
            <div class="container">
              <h4 class="title">
                Hire Buyer's Agent auction
              </h4>
              <div class="wizard-steps-progress">
                <div class="steps-progress-percent"></div>
              </div>


              {{-- Slide 1  --}}
              <div class="wizard-step" data-step="1">
                <div class="form-group">
                  <label class="fw-bold">Is the buyer currently represented by another agent?</label>
                  <select class="grid-picker" name="working_with_agent" id="working_with_agent"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $yes_or_no)
                      <option value="{{ $yes_or_no['name'] }}" data-target="{{ $yes_or_no['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                        {{ $yes_or_no['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  <label class="yes_message text-danger" style="font-size:18px; font-weight:bold;"></label>
                </div>
              </div>
              {{-- Slide 1 --}}
              {{-- Slide 2  --}}
              <div class="wizard-step" data-step="2">

                <h4> Please provide the cities, counties, and state pertaining to the real estate location
                  that the buyer intends to purchase a property. </h4>
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
                          <input type="text" name="cities[]" placeholder="City" data-type="cities" id="cities"
                            class="form-control has-icon  search_places" data-icon="fa-solid fa-city" required>
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
                        <td><input type="text" name="counties[]" placeholder="County" data-type="counties"
                            id="counties" class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                            required>
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
                        <td><input type="text" name="state" placeholder="State" data-type="state" id="state"
                            class="form-control has-icon  search_places" data-icon="fa-solid fa-flag-usa" required>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="wizard-step" data-step="3">
                <div class="form-group">
                  <label for="address" class="fw-bold">Listing Date:</label>
                  <input type="date" name="listing_date" id="listing_date" class="form-control has-icon search_places"
                    data-icon="fa-regular fa-calendar-days" required min="{{ date('Y-m-d') }}">
                </div>
                <div class="form-group">
                  <label for="address" class="fw-bold">Expiration Date:</label>
                  <input type="date" name="expiration_date" id="expiration_date"
                    class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days" required
                    min="{{ date('Y-m-d') }}">
                </div>
              </div>
              <div class="wizard-step" data-step="4">
                <div class="form-group">
                  <label class="fw-bold">
                    Listing Type:
                  </label>
                  <div>
                    @php
                      $auction_types = [
                          [
                              'name' => 'Auction (Timer)',
                              'icon' => '<i class="fa-regular fa-clock"></i>',
                              'target' => '',
                          ],
                          [
                              'name' => 'Traditional (No Timer)',
                              'icon' => '<i class="fa-regular fa-circle-xmark"></i>',
                              'target' => '',
                          ],
                      ];
                    @endphp
                    <select name="auction_type" id="auction_type" class="grid-picker" style="justify-content: flex-start;"
                      onchange="changeAuctionType(this.value);" required>
                      <option value=""></option>
                      @foreach ($auction_types as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="form-group auction_length_cover d-none">
                  <label class="fw-bold">
                    Auction Length:
                  </label>
                  <div>
                    @php
                      $auction_lengths = [
                          ['name' => '1 Day', 'class' => 'normal-length'],
                          ['name' => '3 Days', 'class' => 'normal-length'],
                          ['name' => '5 Days', 'class' => 'normal-length'],
                          ['name' => '7 Days', 'class' => 'normal-length'],
                          ['name' => '10 Days', 'class' => 'normal-length'],
                          ['name' => '14 Days', 'class' => 'normal-length'],
                          ['name' => '21 Days', 'class' => 'normal-length'],
                          ['name' => '30 Days', 'class' => 'normal-length'],
                          ['name' => '45 Days', 'class' => 'normal-length'],
                          ['name' => '60 Days', 'class' => 'normal-length'],
                          ['name' => '75 Days', 'class' => 'normal-length'],
                          ['name' => '90 Days', 'class' => 'normal-length'],
                          ['name' => 'No time limit', 'class' => 'traditional-length'],
                      ];
                    @endphp
                    <select name="auction_length" id="auction_length" class="auction_length grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($auction_lengths as $item)
                        <option value="{{ $item['name'] }}" data-target=""
                          class="card flex-row  {{ $item['class'] }}" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
              <div class="wizard-step" data-step="5">
                <div class="form-group">
                  <label class="fw-bold" for="heated_sqft">Title of Listing:</label>
                  <input type="text" name="title_of_listing" placeholder="Enter Title of Listing"
                    id="title_of_listing" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-ruler-combined" required>
                </div>
              </div>
              <div class="wizard-step" data-step="6">
                @php
                  $property_types = [
                      ['name' => 'Residential Property'],
                      ['name' => 'Income Property'],
                      ['name' => 'Commercial Property'],
                      ['name' => 'Business Opportunity'],
                      ['name' => 'Vacant Land'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold"> Acceptable Property Styles:
                  </label>
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
                          ['target' => '', 'name' => 'Single Family Residence', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Townhouse', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Villa', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Condominium', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Condo-Hotel', 'class' => 'residential-length'],
                          ['target' => '', 'name' => '½ Duplex', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Farm', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Garage Condo', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Modular Home', 'class' => 'residential-length'],
                          ['target' => '', 'name' => 'Duplex', 'class' => 'income-length'],
                          ['target' => '', 'name' => 'Triplex', 'class' => 'income-length'],
                          ['target' => '', 'name' => 'Quadplex', 'class' => 'income-length'],
                          ['target' => '', 'name' => 'Five or More', 'class' => 'income-length'],
                          ['target' => '', 'name' => 'Agriculture', 'class' => 'commercial-length'],
                          ['target' => '', 'name' => 'Assembly Building', 'class' => 'commercial-length'],
                          ['target' => '', 'name' => 'Business', 'class' => 'commercial-length'],
                          ['target' => '', 'name' => 'Five or More', 'class' => 'commercial-length'],
                          ['target' => '', 'name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                          ['target' => '', 'name' => 'Industrial', 'class' => 'commercial-length'],
                          ['target' => '', 'name' => 'Mixed Use', 'class' => 'commercial-length'],
                          ['target' => '', 'name' => 'Office', 'class' => 'commercial-length'],
                          ['target' => '', 'name' => 'Restaurant', 'class' => 'commercial-length'],
                          ['target' => '', 'name' => 'Retail', 'class' => 'commercial-length'],
                          ['target' => '', 'name' => 'Warehouse', 'class' => 'commercial-length'],
                          // Vacant Land Items
                          ['target' => '', 'name' => 'Agricultural', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Billboard Site', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Business', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Cattle', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Commercial', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Farm', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Fishery', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Highway Frontage', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Horses', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Industrial', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Land Fill', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Livestock', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Mixed Use', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Multi Family', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Nursery', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Orchard', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Pasture', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Poultry', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Ranch', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Residential', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Row Crops', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Sod Farm', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Subdivision', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Timber', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Tracts', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Trans/Cell Tower', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Tree Farm', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Unimproved Land', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Well Field', 'class' => 'vacant_land-length'],
                          ['target' => '.vacantOther', 'name' => 'Other', 'class' => 'vacant_land-length'],
                          ['target' => '', 'name' => 'Aeronautical', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Agriculture', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Arts and Entertainment', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Assembly Hall', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Assisted Living', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Auto Dealer', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Auto Service', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Bar/Tavern/Lounge', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Barber/Beauty', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Car Wash', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Child Care', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Church', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Commercial', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Concession Trailers/Vehicles', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Construction/Contractor', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Convenience Store', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Distribution', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Distributor Routine Ven', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Education/School', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Farm', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Fashion/Specialty', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Flex Space', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Florist/Nursery', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Food & Beverage', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Gas Station', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Grocery', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Heavy Weight Sales Service', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Hotel/Motel', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Industrial', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Light Items Sales Only', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Manufacturing', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Marine/Marina', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Medical', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Mixed', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Mobile/Trailer Park', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Personal Service', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Professional Service', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Professional/Office', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Recreation', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Research & Development', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Residential', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Restaurant', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Retail', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Shopping Center/Strip Center', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Storage', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Theater', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Timberland', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Veterinary', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Warehouse', 'class' => 'business-length'],
                          ['target' => '', 'name' => 'Wholesale', 'class' => 'business-length'],
                          ['target' => '.buisnessOther', 'name' => 'Other', 'class' => 'business-length'],
                      ];
                    @endphp
                    <label class="fw-bold businessTitle">Business Type: </label>
                    <label class="fw-bold vacantTitle">Current Use: </label>
                    <select name="property_items[]" id="property_items" class="property_items grid-picker"
                      style="justify-content: flex-start;" multiple required>
                      @foreach ($property_items as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row {{ $item['class'] }}" style="width:calc(33.33% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group buisnessOther d-none">
                      <label class="fw-bold" for="buisnessOther">
                        Business Type:
                      </label>
                      <input type="text" name="otherProps" id="" class="form-control has-icon"
                        data-icon="fa-solid fa-hotel" required>
                    </div>
                    <div class="form-group vacantOther d-none">
                      <label class="fw-bold" for="buisnessOther">
                        Current Use:
                      </label>
                      <input type="text" name="otherProps" id="" class="form-control has-icon"
                        data-icon="fa-solid fa-hotel" required>
                    </div>
                  </div>
                </div>
              </div>
              <div class="wizard-step" data-step="7">
                <div class="form-group">
                  <label class="fw-bold">
                    Acceptable Special Sale Provisions:
                  </label>
                  @php
                    $sellerPropRes = [
                        ['name' => 'Auction', 'target' => ''],
                        ['target' => '', 'name' => 'Bank Owned/REO'],
                        ['target' => '.assignmentRes', 'name' => 'To Assignment Contract (Wholesale Properties)'],
                        ['target' => '', 'name' => 'Short Sale'],
                        ['target' => '', 'name' => 'Probate'],
                        ['target' => '', 'name' => 'Government Owned'],
                        ['target' => '', 'name' => 'None'],
                        ['target' => '.custom_special_sale_residential', 'name' => 'Other'],
                    ];
                  @endphp
                  <div class="select2-parent">
                    <select name="special_sale[]" id="special_sale" class="grid-picker"
                      style="justify-content: flex-start;" required multiple>
                      <option value=""></option>
                      @foreach ($sellerPropRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row " style="width:calc(33.33% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="form-group custom_special_sale_residential d-none">
                  <label class="fw-bold" for="custom_special_sale_residential">
                    Acceptable Special Sale Provisions:
                  </label>
                  <input type="text" name="custom_special_sale" id="custom_special_sale"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group d-none assignmentRes">
                  <label class="fw-bold">
                    Is the buyer currently under contract with a property they would like to assign?
                  </label>
                  @php
                    $assignmentRes = [
                        ['name' => 'Yes', 'target' => '.assignmentResYes'],
                        ['name' => 'No', 'target' => '.assignmentResNo'],
                    ];
                  @endphp
                  <select name="contribute_term" id="contribute_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($assignmentRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group assignmentResYes d-none">
                    <label class="fw-bold">What fee would the buyer pay the agent to assign the contract? </label>
                    <input type="text" class="form-control has-icon" placeholder=""
                      name="residentialSeller_contract_yes" data-icon="fa-solid fa-dollar-sign"
                      id="residentialSeller_contract_yes" required />
                  </div>
                  <div class="form-group assignmentResNo d-none">
                    <label class="fw-bold">Is the buyer looking to sell their contract? </label>
                    @php
                      $seller_contract_yes_no = [
                          ['name' => 'Yes', 'target' => '.contract_res_yes'],
                          ['name' => 'No', 'target' => ''],
                      ];
                    @endphp
                    <select name="residential_seller_contract_no" id="" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($seller_contract_yes_no as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group contract_res_yes d-none">
                      <label class="fw-bold">What fee would the buyer pay the agent to assign the contract? </label>
                      <input type="text" class="form-control has-icon" placeholder=""
                        name="residentialSeller_contract_yes" data-icon="fa-solid fa-ruler-combined"
                        id="residentialSeller_contract_yes" required />
                    </div>
                  </div>
                </div>
              </div>
              <div class="wizard-step" data-step="8">
                @php
                  $conditionsRes = [
                      ['name' => 'Pre-Construction', 'target' => ''],
                      ['name' => 'Currently Being Built', 'target' => ''],
                      ['name' => 'New Construction', 'target' => ''],
                      ['name' => 'Completely Updated: No updates needed', 'target' => ''],
                      ['name' => 'Semi-updated: Needs minor updates', 'target' => ''],
                      ['name' => 'Not Updated: Requires a complete update', 'target' => ''],
                      ['name' => 'Tear Down: Requires complete demolition and reconstruction', 'target' => ''],
                      ['name' => 'Open to any type of property condition', 'target' => ''],
                      ['name' => 'Other', 'target' => '.custom_condition_interested_res'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Acceptable Property Conditions:</label>
                  <select class="grid-picker" name="propsCondition[]" id="condition_interested" multiple required>
                    <option value="">Select</option>
                    @foreach ($conditionsRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="form-group custom_condition_interested_res d-none">
                  <label class="fw-bold">Acceptable Property Conditions: </label>
                  <input type="text" name="custom_props_condition" id="custom_condition_interested_res"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined" required>
                </div>
              </div>
              <div class="wizard-step " data-step="9">
                @php
                  $beds = [
                      ['name' => '1+', 'target' => ''],
                      ['name' => '2+', 'target' => ''],
                      ['name' => '3+', 'target' => ''],
                      ['name' => '4+', 'target' => ''],
                      ['name' => '5+', 'target' => ''],
                      ['name' => '6+', 'target' => ''],
                      ['name' => '7+', 'target' => ''],
                      ['name' => '8+', 'target' => ''],
                      ['name' => '9+', 'target' => ''],
                      ['name' => '10+', 'target' => ''],
                      ['name' => 'Other', 'target' => '.other_bedrooms'],
                  ];

                @endphp
                <div class="form-group">
                  <label class="fw-bold">Minimum Bedrooms Needed: </label>
                  <select class="grid-picker" name="bedrooms" id="bedrooms" style="justify-content: center;"
                    required>
                    <option value="">Select</option>
                    @foreach ($beds as $bedroom)
                      <option value="{{ $bedroom['name'] }}" data-target="{{ $bedroom['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-solid fa-bed"></i>'>
                        {{ $bedroom['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group other_bedrooms d-none">
                  <label class="fw-bold" for="other_bedrooms">Minimum Bedrooms Needed: </label>
                  <input type="text" name="other_bedrooms" id="other_bedrooms" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-bed" data-msg-required="" required>
                </div>
              </div>
              <div class="wizard-step " data-step="10">
                <span class="resFields">
                  @php
                    $bathrooms = [
                        ['name' => '1+', 'target' => ''],
                        ['name' => '1.5+', 'target' => ''],
                        ['name' => '2+', 'target' => ''],
                        ['name' => '2.5+', 'target' => ''],
                        ['name' => '3+', 'target' => ''],
                        ['name' => '3.5+', 'target' => ''],
                        ['name' => '4+', 'target' => ''],
                        ['name' => '4.5+', 'target' => ''],
                        ['name' => '5+', 'target' => ''],
                        ['name' => '6+', 'target' => ''],
                        ['name' => '7+', 'target' => ''],
                        ['name' => '8+', 'target' => ''],
                        ['name' => '9+', 'target' => ''],
                        ['name' => '10+', 'target' => ''],
                        ['name' => 'Other', 'target' => '.other_bathrooms'],
                    ];
                  @endphp
                  <div class="form-group">
                    <label class="fw-bold">Bathrooms: </label>
                    <select class="grid-picker" name="bathrooms" id="bathrooms" style="justify-content: center;"
                      required>
                      <option value="">Select</option>
                      @foreach ($bathrooms as $bathroom)
                        <option value="{{ $bathroom['name'] }}" data-target="{{ $bathroom['target'] }}"
                          class="card flex-column" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-solid fa-bath"></i>'>
                          {{ $bathroom['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group other_bathrooms d-none">
                      <label class="fw-bold" for="other_bathrooms">Bathrooms: </label>
                      <input type="text" name="other_bathrooms" id="other_bathrooms" placeholder=""
                        class="form-control has-icon" data-icon="fa-solid fa-bath"
                        data-msg-required="Please enter Custom Bathrooms" required>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step minRes" data-step="11">
                <span class="resFields">
                  <div class="form-group">
                    <label class="fw-bold" for="heated_sqft">Minimum Heated Sqft Needed:</label>
                    <input type="number" name="minimum_heated_sqft" id=""
                      class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined">
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="12">
                <span class="resFields">
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
                    <label class="fw-bold">Minimum Total Acreage Needed: </label>
                    <select class="grid-picker" name="total_acreage" style="justify-content: flex-start;">
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
                <span class="resFields">
                  @php
                    $carportOptionsRes = [
                        ['target' => '.carportOptionsYes', 'name' => 'Yes', 'icon' => 'fa-solid fa-warehouse'],
                        ['target' => '.carportOptionsNo', 'name' => 'No', 'icon' => 'fa-solid fa-warehouse'],
                        [
                            'target' => '.carportOptionsOptional',
                            'name' => 'Optional',
                            'icon' => 'fa-solid fa-warehouse',
                        ],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <labal class="fw-bold" for="heated_sqft">Carport Needed:</labal>
                      <div class="select2-parent">
                        <select name="carportOptions" class="grid-picker" id="carportOptRes">
                          <option value="">Select</option>
                          @foreach ($carportOptionsRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group" id="carportResCustom" style="display: none;">
                          <div class="form-group">
                            <label class="fw-bold">Carport Spaces Needed:</label>
                            <input type="text" name="carportCustom" data-type="" id=""
                              class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  @php
                    $garageOptions = [
                        ['target' => '.garageYes', 'name' => 'Yes', 'icon' => 'fa-solid fa-warehouse'],
                        ['target' => '.garageNo', 'name' => 'No', 'icon' => 'fa-solid fa-warehouse'],
                        ['target' => '.garageOptional', 'name' => 'Optional', 'icon' => 'fa-solid fa-warehouse'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <label class="fw-bold" for="heated_sqft">Garage Needed:</label>
                      <div class="select2-parent">
                        <select name="garageOptions" class="grid-picker" id="garageOpt">
                          <option value="">Select</option>
                          @foreach ($garageOptions as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group" id="garageResCustom" style="display: none;">
                          <div class="form-group">
                            <label class="fw-bold">Garage Spaces Needed:</label>
                            <input type="text" name="garageCustom" data-type="" id=""
                              class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="14">
                <span class="resFields">
                  @php
                    $poolOptions = [
                        ['target' => '.poolYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                        ['target' => '.poolNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['target' => '.poolOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <label class="fw-bold" for="heated_sqft">Pool Needed: </label>
                      <div class="select2-parent">
                        <select name="poolOptions" class="grid-picker" id="poolOpt" required>
                          <option value="">Select</option>
                          @foreach ($poolOptions as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group" id="poolCustom" style="display: none;">
                          <div class="form-group">
                            @php
                              $poolCustom = [
                                  ['target' => '', 'name' => 'Private', 'icon' => 'fa-regular fa-check-circle'],
                                  ['target' => '', 'name' => 'Community', 'icon' => 'fa-regular fa-check-circle'],
                              ];
                            @endphp
                            <label class="fw-bold">Private or Community:</label>
                            <select name="poolCustom[]" class="grid-picker" id="poolOpt" required multiple>
                              @foreach ($poolCustom as $item)
                                <option value="">Select</option>
                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                  class="card flex-column " style="width:calc(33.3% - 10px);"
                                  data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                  {{ $item['name'] }}
                                </option>
                              @endforeach
                            </select>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  @php
                    $prefrenceOptions = [
                        ['target' => '.preferenceYesRes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                        ['target' => '.preferenceNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                        [
                            'target' => '.preferenceOptional',
                            'name' => 'Optional',
                            'icon' => 'fa-regular fa-check-circle',
                        ],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <label class="fw-bold" for="heated_sqft">View Preference Needed: </label>
                      <div class="select2-parent">
                        <select name="prefrenceOptions" class="grid-picker" id="preferenceResOpt" required>
                          <option value="">Select</option>
                          @foreach ($prefrenceOptions as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                    @php
                      $ifYesPreference = [
                          ['target' => '', 'name' => 'City', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Garden', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Golf Course', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Greenbelt', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Mountain(s)', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Park', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Tennis Court', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Trees/Woods', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Water', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '.preferenceNo', 'name' => 'Beach', 'icon' => 'fa-regular fa-circle-xmark'],
                          [
                              'target' => '.preferenceOtherRes',
                              'name' => ' Other',
                              'icon' => 'fa-regular fa-check-circle',
                          ],
                      ];
                    @endphp
                    <div class="select2-parent" style="display:none" id="preferenceResOptional">
                      <select name="prefrence[]" class="grid-picker" id="" multiple required>
                        <option value="">Select</option>
                        @foreach ($ifYesPreference as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group preferenceOtherRes d-none">
                        <label class="fw-bold" for="heated_sqft">View Preference Needed:</label>
                        <input type="text" name="preferenceOther" id="total_acreage"
                          class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="15">
                <span class="resFields">
                  @php
                    $petOptions = [
                        ['target' => '.petYesRes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                        ['target' => '.petNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <labal class="fw-bold" for="heated_sqft">Does the buyer have a pet?</labal>
                      <div class="select2-parent">
                        <select name="petOptions" class="grid-picker" id="" required>
                          <option value="">Select</option>
                          @foreach ($petOptions as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                      <div class="form-group petYesRes d-none">
                        <div class="form-group">
                          <label class="fw-bold" for="heated_sqft">How many pets does the buyer have?</label>
                          <input type="text" name="petsNumber" id="" class="form-control has-icon"
                            data-icon="fa-solid fa-dog" required>
                        </div>
                        <div class="form-group">
                          <label class="fw-bold" for="heated_sqft">What type of pet(s) does the buyer have?</label>
                          <input type="text" name="petsType" id="" class="form-control has-icon"
                            data-icon="fa-solid fa-dog" required>
                        </div>
                        <div class="form-group">
                          <label class="fw-bold" for="heated_sqft">What breed(s) are the pet(s)?</label>
                          <input type="text" name="petsBreed" id="" class="form-control has-icon"
                            data-icon="fa-solid fa-dog" required>
                        </div>
                        <div class="form-group">
                          <label class="fw-bold" for="heated_sqft">What is the weight of the pet(s)?</label>
                          <input type="text" name="petsWeight" id="" class="form-control has-icon"
                            data-icon="fa-solid fa-dog" required>
                        </div>
                      </div>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="16">
                <div class="form-group">
                  @php
                    $purchasing_props = [
                        ['target' => '.purchasingPropsYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                        ['target' => '.purchasingPropsNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                  @endphp
                  <label class="fw-bold" for="heated_sqft">Is the buyer eligible/interested in purchasing a property
                    in 55-and-over communities?</label>
                  <div class="select2-parent">
                    <select name="purchasing_props" class="grid-picker" id="">
                      <option value="">Select</option>
                      @foreach ($purchasing_props as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column " style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
              <div class="wizard-step" data-step="17">
                <span class="resFields">
                  <div class="form-group">
                    <label class="fw-bold">
                      Are there any non-negotiable amenities or property features that the buyer is seeking?
                    </label>
                    @php
                      $non_negotialble = [
                          [
                              'icon' => 'fa-regular fa-circle-check',
                              'target' => '.negotiable_terms_commercial',
                              'name' => 'Yes',
                          ],
                          ['icon' => 'fa-regular fa-circle-xmark', 'target' => '', 'name' => 'No'],
                      ];
                    @endphp
                    <select name="has_non_negotiable_terms" id="" class="grid-picker"
                      style="justify-content: flex-start;"required>
                      <option value=""></option>
                      @foreach ($non_negotialble as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group negotiable_terms_commercial d-none">
                    <label class="fw-bold">
                      What non-negotiable amenities or property features is the buyer is seeking?
                    </label>
                    @php
                      $non_negotiable_terms = [
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
                          ['name' => 'Other', 'target' => '.custom_non_negotiable_terms_resdent'],
                      ];
                    @endphp
                    <select name="non_negotiable_terms[]" id="negotiable_terms" class="grid-picker"
                      style="justify-content: flex-start;" multiple required>
                      <option value=""></option>
                      @foreach ($non_negotiable_terms as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle" style="font-size:24px;"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group custom_non_negotiable_terms_resdent d-none">
                    <label class="fw-bold" for="custom_non_negotiable_terms_resdent"> What non-negotiable amenities or
                      property features is the buyer is seeking?
                    </label>
                    <input type="text" name="custom_non_negotiable_terms" id="custom_non_negotiable_terms_resdent"
                      placeholder="" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="18">
                <span class="resFields">
                  @php
                    $financings = [
                        ['name' => 'Cash', 'target' => '.customCashRes'],
                        ['name' => 'Conventional', 'target' => '.customConventional'],
                        ['name' => 'Seller Financing', 'target' => '.custom_seller_financing_commercial'],
                        ['name' => 'FHA', 'target' => '.yes_no'],
                        ['name' => 'Jumbo', 'target' => ''],
                        ['name' => 'VA', 'target' => ''],
                        ['name' => 'No-Doc', 'target' => ''],
                        ['name' => 'Non-QM', 'target' => ''],
                        ['name' => 'Assumable', 'target' => '.custom_assumable_commercial'],
                        ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade_commercial'],
                        ['name' => 'Lease Option', 'target' => '.lease_option_res'],
                        ['name' => 'Lease Purchase', 'target' => '.lease_purchase_res'],
                        ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency_commercial'],
                        ['name' => 'NFT', 'target' => '.nft'],
                        ['name' => 'USDA', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_other_commercial'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="form-group">
                      <label class="fw-bold">Maximum Budget:</label>
                      <input type="text" class="form-control has-icon" name="maxBudget"
                        data-icon="fa-solid fa-dollar-sign" required>
                    </div>
                    <div class="col-md-12">
                      <label class="fw-bold">Please indicate the financing/currency that the buyer will use to purchase
                        the
                        property:</label>
                      <div class="select2-parent">
                        <select name="financings[]" class="grid-picker" multiple id="financingRes" required>
                          @foreach ($financings as $financing)
                            <option value="{{ $financing['name'] }}" data-target="{{ $financing['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="fa-regular fa-circle-check"></i>'>
                              {{ $financing['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>

                    </div>
                  </div>
                  <div class="form-group" id="conventionalRes" style="display: none;">
                    @php
                      $customOptionsRes = [
                          ['target' => '.customOptionsYesRes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '.customOptionsNoRes', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                      ];
                    @endphp
                    <label class="fw-bold">Has the buyer been pre-approved for a loan? </label>
                    <select name="conventionalOptions[]" class="grid-picker" id=""
                      style="justify-content: flex-start;" required>
                      @foreach ($customOptionsRes as $item)
                        <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                          data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group customOptionsYesRes d-none">
                      <label class="fw-bold">How much is the buyer pre-approved for?</label>
                      <input type="text" name="financingOptionsConventional[]" data-type="customOptionsYes"
                        id="customOptionsYes" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group customOptionsNoRes d-none">
                      <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                      <input type="text" name="financingOptionsConventional[]" data-type="customOptionsNoInput"
                        id="customOptionsNoInput" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                      <div class="form-group">
                        <label class="fw-bold">If not pre-approved yet, does the buyer need a lender recommendation?
                        </label>
                        @php
                          $customOptionsYesNo = [
                              ['target' => '', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                              ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                          ];
                        @endphp
                        <select name="conventionalOptionsYesNo[]" class="grid-picker" id=""
                          style="justify-content: flex-start;" required>
                          @foreach ($customOptionsYesNo as $item)
                            <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                              data-target="{{ $item['target'] }}" class="card flex-row"
                              style="width:calc(33.3% - 10px);">
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="form-group customCashRes d-none" id="">
                    <label class="fw-bold" for="customCashRes">What is the buyer's budget for purchasing a
                      property?</label>
                    <input type="text" name="financingOptionsCash[]" id="customCashRes"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group" id="financingOptionRes" style="display: none;">
                    <div class="form-group">
                      <label class="fw-bold">What specific terms is the buyer considering for the lease purchase?
                      </label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option" id="lease_option"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What is the proposed duration or term of the lease?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option" id="lease_option"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much is the buyer comfortable paying in monthly lease payments?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option" id="lease_option"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Is the buyer offering an option fee, and if so, what is the amount?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option" id="lease_option"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Lease Option --}}

                  {{-- NFT --}}

                  <div class="form-group nft d-none" id="">
                    <div class="form-group">
                      <label class="fw-bold">What type of NFT is the buyer offering?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will the buyer offer as an
                        NFT?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will the buyer offer in cash?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- NFT --}}

                  {{-- seller financing --}}
                  <div class="form-group custom_seller_financing_commercial d-none" id="custom_seller_financing">
                    <div class="form-group">
                      <label class="fw-bold">What terms is the buyer offering to the seller?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What down payment is the buyer offering the seller?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What interest rate is the buyer proposing?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- seller financing --}}
                  {{-- Exchange Trade --}}
                  <div class="form-group custom_exchange_trade_commercial d-none">
                    <div class="form-group">
                      <label class="fw-bold">What item would the buyer like to Exchange/Trade?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_ress"
                        id="custom_exchange_trade_ress" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much is the buyer’s Exchange/Trade item valued at?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_ress"
                        id="custom_exchange_trade_ress" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much cash will the buyer use on top of the Exchange/Trade item?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_ress"
                        id="custom_exchange_trade_ress" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_ress"
                        id="custom_exchange_trade_ress" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Exchange Trade --}}
                  {{-- Assemble --}}
                  {{-- <div class="form-group custom_assumable_commercial d-none">
                    <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                    <input type="text" name="financingOptionsAssumble[]" data-type="custom_assumable_res"
                      id="custom_assumable_res" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      data-msg-required="Please Enter Type of Financing">
                  </div> --}}
                  {{-- Assemble --}}
                  {{-- Cryptocurrency --}}
                  <div class="form-group custom_cryptocurrency_commercial d-none">
                    <div class="form-group">
                      <label class="fw-bold">What type of Cryptocurrency does the buyer have?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrencyRes"
                        id="custom_cryptocurrencyRes" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will be used with
                        Cryptocurrency?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrencyRes"
                        id="custom_cryptocurrencyRes" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will be used with Cash?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrencyRes"
                        id="custom_cryptocurrencyRes" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <label class="">Note: Cryptocurrency can be converted to cash at closing.</label>
                  </div>
                  {{-- Cryptocurrency --}}
                  {{-- Other --}}
                  <div class="form-group custom_other_commercial d-none">
                    <div class="form-group">
                      <label class="fw-bold">What type of financing/funds will the buyer use to purchase the
                        property?</label>
                      <input type="text" name="financingOther[]" data-type="custom_other_res" id="custom_other_res"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Other --}}
                </span>
              </div>
              <div class="wizard-step" data-step="19">
                <span class="resFields">
                  <div class="form-group">
                    <label class="fw-bold">When will the buyer be ready to purchase a property? </label>



                    @php
                      $timeframes = [
                          ['name' => 'ASAP', 'target' => ''],
                          ['name' => '1 Month', 'target' => ''],
                          ['name' => '2 Months', 'target' => ''],
                          ['name' => '3 Months', 'target' => ''],
                          ['name' => '4 Months', 'target' => ''],
                          ['name' => '5 Months', 'target' => ''],
                          ['name' => '6 Months', 'target' => ''],
                          ['name' => 'Over 6 Months', 'target' => ''],
                          // ['name' => 'Other', 'target' => '.custom_amount1']
                      ];
                    @endphp
                    <select class="grid-picker" name="buying_timeframe" id="buying_timeframe"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($timeframes as $item)
                        @php
                          if ($item['name'] == 'Other') {
                              $target = '.custom_amount12';
                          } else {
                              $target = '';
                          }
                        @endphp
                        <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>

                  </div>
                  <div class="form-group custom_amount12 d-none">
                    <label class="fw-bold">When will the buyer be ready to purchase a property? </label>
                    <input type="text" class="form-control has-icon" name="custom_buyer_looking_to_purchase"
                      data-icon="fa-regular fa-check-circle" id="custom_amount" required />
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="20">
                <span class="resFields">
                  <div class="form-group">
                    <label class="fw-bold">
                      What is the timeframe offered to the agent in the Buyer Agency Agreement?
                    </label>
                    @php
                      $timeframe_res = [
                          ['name' => '1 Month', 'target' => ''],
                          ['name' => '2 Months', 'target' => ''],
                          ['name' => '3 Months', 'target' => ''],
                          ['name' => '4 Months', 'target' => ''],
                          ['name' => '5 Months', 'target' => ''],
                          ['name' => '6 Months', 'target' => ''],
                          ['name' => 'Over 6 Months', 'target' => ''],
                          ['name' => 'Negotiable ', 'target' => ''],
                          ['name' => 'Other', 'target' => '.other_timeframe_res'],
                      ];
                    @endphp
                    <select class="grid-picker" name="timeframe" id="term_financings"
                      style="justify-content: flex-start;" required onchange="showPrompt(this.value);">
                      <option value="">Select</option>
                      @foreach ($timeframe_res as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group other_timeframe_res d-none">
                    <label class="fw-bold">What is the timeframe offered to the agent in the Buyer Agency
                      Agreement?</label>
                    <input type="text" class="form-control has-icon" placeholder="" name="other_timeframe"
                      data-icon="fa-solid fa-ruler-combined" id="custom_amount" required />
                  </div>
                  <div class="form-group prompt_response d-none">
                    {{-- <p class="text-danger">This service is designed for buyers who intend to purchase a property within
                      the
                      next three months.</p> --}}
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="21">
                <span class="resFields">
                  <label class="fw-bold">
                    In cases where a property is For Sale By Owner or a Buyer’s agent commission is not offered, how will
                    the Buyer be able to pay their agent's commission?
                  </label>
                  <?php
                  $commissionRes = [['target' => '', 'name' => "The Buyer will ask the agent to include their agent's commission as a part of the offer to the Seller."], ['target' => '', 'name' => "The Buyer will be able to pay their agent's commission out of pocket."], ['target' => '', 'name' => 'The Buyer will not allow any compensation that is not already offered.'], ['target' => '.OtherCommissionRes', 'name' => 'Other']];
                  ?>
                  <select class="grid-picker" name="commission" id="commissionResOpt"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($commissionRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group OtherCommissionRes d-none">
                    <label class="fw-bold">How will the Buyer be able to pay their agent's commission?</label>
                    <input type="text" name="OtherCommission" class="form-control has-icon col-3"
                      data-icon="fa-solid fa-ruler-combined" data-msg-required="" required>
                  </div>
                  <div class="form-group" id="commissionResOptional" style="display:none;">
                    <label class="fw-bold">How much will the Buyer compensate their agent?</label>
                    <?php
                    $commissionOpt = [['target' => '', 'name' => '2%'], ['target' => '', 'name' => '2.5%'], ['target' => '', 'name' => '3%'], ['target' => '', 'name' => 'Negotiable'], ['target' => '.otherComResOpt', 'name' => 'Other']];
                    ?>
                    <select class="grid-picker" name="commissionOpt" style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($commissionOpt as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group otherComResOpt d-none">
                      <label class="fw-bold">How much will the Buyer compensate their agent?</label>
                      <input type="text" name="OtherComOptions" class="form-control has-icon col-3"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="" required>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="22">
                <span class="resFields">
                  <label class="fw-bold">Would the buyer like to request that their hired agent offer a concession to
                    assist with the buyer's closing costs from the agent's commission?
                  </label>
                  <?php
                  $concession = [['target' => '', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'], ['target' => '.concessionOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle']];
                  ?>
                  <select class="grid-picker" name="concession" id="" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($concession as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group">
                    <small>(Available in all the states except: Alabama, Alaska, Kansas, Mississippi,
                      Missouri,
                      Oklahoma, Oregon, and Tennessee. This list is subject to change.) </small>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="23">
                <span class="resFields">
                  <div class="form-group">
                    <label class="fw-bold">
                      What are the most important aspects the buyer will consider when hiring a real estate agent?
                    </label>
                    <textarea name="aspect_hiring_agent" class="form-control" rows="5">{{ old('aspect_hiring_agent') }}</textarea>
                  </div>

                  <div class=" form-group">
                    <label class="fw-bold">What additional details would the buyer like to share with the agent? </label>
                    <textarea name="additional_details" class="form-control" rows="5">{{ old('additional_details') }}</textarea>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="24">
                @php
                  $services_data = [
                      [
                          'name' =>
                              'Assist the buyer in obtaining pre-approval or exploring financing options to determine their purchasing power.',
                          'target' => '',
                      ],
                      ['name' => 'List the Buyer’s Criteria Listing on the platform BidYourOffer.com.', 'target' => ''],
                      [
                          'name' =>
                              "Market the buyer's listing on numerous groups, pages, and affiliates through a listing link or QR code that links directly to the listing on BidYourOffer.com.",
                          'target' => '',
                      ],
                      [
                          'name' =>
                              "Promote the buyer's listing on social media platforms using a listing link or QR code that directs to the buyer's listing on the BidYourOffer.com platform.",
                          'target' => '',
                      ],
                      [
                          'name' =>
                              "Send prompt email notifications containing properties that meet the buyer's criteria as soon as they are listed, ensuring access to the most up-to-date listings.",
                          'target' => '',
                      ],
                      ['name' => 'Schedule and accompany the buyer on property viewings and showings.', 'target' => ''],
                      ['name' => 'Schedule video tours of the property as needed.', 'target' => ''],
                      [
                          'name' =>
                              'Provide detailed market analysis and comparative market information to help the buyer make informed decisions.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist the buyer in negotiations and in preparing offers to maximize their chances of securing the desired property.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist the buyer with the review and explanation of all contractual documents and disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Coordinate and oversee the home inspection process, including making recommendations for trusted inspectors.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Facilitate communication and coordination with other professionals involved in the transaction, such as lenders, attorneys, and title companies.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide guidance and support throughout the entire purchase transaction, from offer acceptance to closing.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide guidance and support throughout the entire purchase transaction, from offer acceptance to closing.',
                          'target' => '',
                      ],
                      [
                          'target' => '.custom_service_buyer_want_resdent',
                          'name' => 'Other-Add additional services as needed.',
                      ],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Select the services that the buyer requests from an agent:</label>
                  <select class="grid-picker" name="services[]" onchange="service_data(this.value)" id="services"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($services_data as $service)
                      <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}"
                        class="card flex-row" style="width:calc(100% - 0px);"
                        data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                        {{ $service['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_service_buyer_want_resdent d-none">
                  <label class="fw-bold">What additional services would the buyer like to request from an agent?</label>
                  <input type="text" class="form-control has-icon" name="custom_service_buyer_want_res"
                    data-icon="fa-solid fa-ruler-combined" id="" required />
                </div>
              </div>
              <div class="wizard-step" data-step="25">
                <span class="resFields">
                  <div class="row form-group">
                    <div class="col-md-6">
                      <label class="fw-bold">First Name:</label>
                      <input type="text" class="form-control has-icon" name="first_name" id="first_name"
                        value="{{ auth()->user()->first_name }}" data-icon="fa-solid fa-user" />
                    </div>
                    <div class="col-md-6">
                      <label class="fw-bold">Last Name:</label>
                      <input type="text" class="form-control has-icon" name="last_name" id="last_name"
                        value="{{ auth()->user()->last_name }}" data-icon="fa-solid fa-user" />
                    </div>
                  </div>
                  <div class="row form-group">
                    <div class="col-md-6">
                      <label class="fw-bold">Phone Number:</label>
                      <input type="text" class="form-control has-icon" name="phone" id="phone"
                        value="{{ auth()->user()->phone }}" data-icon="fa-solid fa-phone" />
                    </div>
                    <div class="col-md-6">
                      <label class="fw-bold">Email:</label>
                      <input type="email" class="form-control has-icon" name="email" id="email"
                        value="{{ auth()->user()->email }}" data-icon="fa-solid fa-envelope" />
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-6">
                      <label class="fw-bold mt-1">Video:</label>
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
                    <div class="col-6">
                      <div class="upload form-group">
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
                </span>
              </div>
              <div class="wizard-step" data-step="26">
                <div class="form-group">
                  <label class="fw-bold">
                    Acceptable Special Sale Provisions:
                  </label>
                  @php
                    $seller_property = [
                        ['target' => '', 'name' => 'Auction'],
                        ['target' => '', 'name' => 'Bank Owned/REO'],
                        [
                            'target' => '.assignment_contract_income',
                            'name' => 'To To Assignment Contract (Wholesale Properties) ',
                        ],
                        ['target' => '', 'name' => 'Short Sale'],
                        ['target' => '', 'name' => 'Probate'],
                        ['target' => '', 'name' => 'Government Owned'],
                        ['target' => '', 'name' => 'None'],
                        ['target' => '.custom_special_sale_income', 'name' => 'Other'],
                    ];
                  @endphp
                  <div class="select2-parent">
                    <select name="special_sale[]" id="special_sale" class="grid-picker"
                      style="justify-content: flex-start;" required multiple>
                      <option value=""></option>
                      @foreach ($seller_property as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row " style="width:calc(33.33% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="form-group custom_special_sale_income d-none">
                  <label class="fw-bold" for="custom_special_sale_income">
                    Acceptable Special Sale Provisions:
                  </label>
                  <input type="text" name="custom_special_sale" id="custom_special_sale"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>

                <div class="form-group d-none assignment_contract_income">
                  <label class="fw-bold">
                    Is the buyer currently under contract with a property they would like to assign?
                  </label>
                  @php
                    $seller_contract_options = [
                        ['name' => 'Yes', 'target' => '.incomeSeller_contract_yes'],
                        ['name' => 'No', 'target' => '.incomeSeller_contract_no'],
                    ];
                  @endphp
                  <select name="contribute_term" id="contribute_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($seller_contract_options as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>

                  <div class="form-group incomeSeller_contract_yes d-none">
                    <label class="fw-bold">What fee would the buyer pay the agent to assign the contract? </label>
                    <input type="text" class="form-control has-icon" placeholder=""
                      name="incomeSeller_contract_yes" data-icon="fa-solid fa-dollar-sign"
                      id="incomeSeller_contract_yes" required />
                  </div>
                  <div class="form-group incomeSeller_contract_no d-none">
                    <label class="fw-bold"></label>
                    @php
                      $seller_contract_yes_no = [
                          ['name' => 'Yes', 'target' => '.contract_income_yes'],
                          ['name' => 'No', 'target' => ''],
                      ];
                    @endphp
                    <select name="incomeSeller_contract_no" id="incomeSeller_contract_no" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($seller_contract_yes_no as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group contract_income_yes d-none">
                      <label class="fw-bold">What fee would the buyer pay the agent to assign the contract? </label>
                      <input type="text" class="form-control has-icon" placeholder=""
                        name="incomeSeller_contract_yes" data-icon="fa-solid fa-ruler-combined"
                        id="incomeSeller_contract_yes" required />
                    </div>
                  </div>
                </div>

              </div>
              <div class="wizard-step" data-step="27">
                @php
                  $conditions_interested_items = [
                      ['name' => 'Pre-Construction', 'target' => ''],
                      ['name' => 'Currently Being Built', 'target' => ''],
                      ['name' => 'New Construction', 'target' => ''],
                      ['name' => 'Completely Updated: No updates needed', 'target' => ''],
                      ['name' => 'Semi-updated: Needs minor updates', 'target' => ''],
                      ['name' => 'Not Updated: Requires a complete update', 'target' => ''],
                      ['name' => 'Tear Down: Requires complete demolition and reconstruction', 'target' => ''],
                      ['name' => 'Open to any type of property condition', 'target' => ''],
                      ['name' => 'Other', 'target' => '.custom_condition_income'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Acceptable Property Conditions: </label>
                  <select class="grid-picker" name="propsCondition[]" id="custom_income" required multiple>
                    <option value="">Select</option>
                    @foreach ($conditions_interested_items as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_condition_income d-none">
                  <label class="fw-bold" for=""> Acceptable Property Conditions: </label>
                  <input type="text" name="custom_props_condition" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-ruler-combined" required>
                </div>

              </div>
              <div class="wizard-step" data-step="28">
                <span class="incomeFields">
                  @php
                    $total_acreages = [
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
                  <div class="form-group commercial_hide">
                    <label class="fw-bold">Minimum Total Acreage Needed: </label>
                    <select class="grid-picker" name="total_acreage" id="total_acreage"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($total_acreages as $total_acreage)
                        <option value="{{ $item['name'] }}" data-target="{{ $total_acreage['target'] }}"
                          class="card flex-column" style="width:calc(25% - 10px);"
                          data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                          {{ $total_acreage['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="29">
                <span class="incomeFields">
                  @php
                    $carportOptionsIncome = [
                        ['target' => '.carportOptionsYes', 'name' => 'Yes', 'icon' => 'fa-solid fa-warehouse'],
                        ['target' => '.carportOptionsNo', 'name' => 'No', 'icon' => 'fa-solid fa-warehouse'],
                        [
                            'target' => '.carportOptionsOptional',
                            'name' => 'Optional',
                            'icon' => 'fa-solid fa-warehouse',
                        ],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <labal class="fw-bold" for="heated_sqft">Carport Needed:</labal>
                      <div class="select2-parent">
                        <select name="carportOptions" class="grid-picker" id="carportOptIncome" required>
                          @foreach ($carportOptionsIncome as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group" id="carportIncomeCustom" style="display: none;">
                          <div class="form-group">
                            <label class="fw-bold">Carport Spaces Needed:</label>
                            <input type="text" name="carportCustom" data-type="" id=""
                              class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                              data-msg-required="" required>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  @php
                    $garageOptions = [
                        ['target' => '.garageYes', 'name' => 'Yes', 'icon' => 'fa-solid fa-warehouse'],
                        ['target' => '.garageNo', 'name' => 'No', 'icon' => 'fa-solid fa-warehouse'],
                        ['target' => '.garageOptional', 'name' => 'Optional', 'icon' => 'fa-solid fa-warehouse'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <label class="fw-bold" for="heated_sqft">Garage Needed:</label>
                      <div class="select2-parent">
                        <select name="garageOptions" class="grid-picker" id="garageOptIncome" required>
                          @foreach ($garageOptions as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group" id="garageIncomeCustom" style="display: none;">
                          <div class="form-group">
                            <label class="fw-bold">Garage Spaces Needed:</label>
                            <input type="text" name="garageCustom" data-type="" id=""
                              class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                              data-msg-required="" required>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="30">
                <span class="incomeFields">
                  @php
                    $poolOptions = [
                        ['target' => '.poolYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                        ['target' => '.poolNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['target' => '.poolOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <label class="fw-bold" for="heated_sqft">Pool Needed: </label>
                      <div class="select2-parent">
                        <select name="poolOptions" class="grid-picker" id="poolOptIncome" required>
                          @foreach ($poolOptions as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                      <div class="form-group" id="poolCustomIncome" style="display: none;">
                        @php
                          $poolOptIncome = [
                              ['target' => '', 'name' => 'Private', 'icon' => 'fa-regular fa-check-circle'],
                              ['target' => '', 'name' => 'Community', 'icon' => 'fa-regular fa-check-circle'],
                          ];
                        @endphp
                        <div class="form-group">
                          <label class="fw-bold">Private or Community:</label>
                          <select name="poolCustom[]" class="grid-picker" id="poolOptIncome" required multiple>
                            @foreach ($poolOptIncome as $item)
                              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-column " style="width:calc(33.3% - 10px);"
                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                {{ $item['name'] }}
                              </option>
                            @endforeach
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                  @php
                    $prefrenceOptions = [
                        ['target' => '.preferenceYesIncome', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                        ['target' => '.preferenceNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                        [
                            'target' => '.preferenceOptional',
                            'name' => 'Optional',
                            'icon' => 'fa-regular fa-check-circle',
                        ],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <label class="fw-bold" for="heated_sqft">View Preference Needed: </label>
                      <div class="select2-parent">
                        <select name="prefrenceOptions" class="grid-picker" id="preferenceNeedIncome" required>
                          <option value="">Select</option>
                          @foreach ($prefrenceOptions as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                    @php
                      $ifYesPreference = [
                          ['target' => '', 'name' => 'City', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Garden', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Golf Course', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Greenbelt', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Mountain(s)', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Park', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Tennis Court', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Trees/Woods', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '', 'name' => 'Water', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '.preferenceNo', 'name' => 'Beach', 'icon' => 'fa-regular fa-check-circle'],
                          [
                              'target' => '.preferenceOtherIncome',
                              'name' => ' Other',
                              'icon' => 'fa-regular fa-check-circle',
                          ],
                      ];
                    @endphp
                    <div class="select2-parent" id="preferenceOptionIncome" style="display: none;">
                      <select name="prefrence[]" class="grid-picker" id="" multiple required>
                        @foreach ($ifYesPreference as $item)
                          <option value="">Select</option>
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group preferenceOtherIncome d-none">
                        <label class="fw-bold" for="heated_sqft">View Preference:</label>
                        <input type="text" name="preferenceOther" id="total_acreage"
                          class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="31">
                <span class="incomeFields">
                  @php
                    $petOptions = [
                        ['target' => '.petYesIncome', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                        ['target' => '.petNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <labal class="fw-bold" for="heated_sqft">Does the buyer have a pet?</labal>
                      <div class="select2-parent">
                        <select name="petOptions" class="grid-picker" id="" required>
                          <option value="">Select</option>
                          @foreach ($petOptions as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                      <div class="form-group petYesIncome d-none">
                        <div class="form-group">
                          <label class="fw-bold" for="heated_sqft">How many pets does the buyer have?</label>
                          <input type="text" name="petsNumber" id="total_acreage" class="form-control has-icon"
                            data-icon="fa-solid fa-dog" required>
                        </div>
                        <div class="form-group">
                          <label class="fw-bold" for="heated_sqft">What type of pet(s) does the buyer have?</label>
                          <input type="text" name="petsType" id="total_acreage" class="form-control has-icon"
                            data-icon="fa-solid fa-dog" required>
                        </div>
                        <div class="form-group">
                          <label class="fw-bold" for="heated_sqft">What breed(s) are the pet(s)?</label>
                          <input type="text" name="petsBreed" id="total_acreage" class="form-control has-icon"
                            data-icon="fa-solid fa-dog" required>
                        </div>
                        <div class="form-group">
                          <label class="fw-bold" for="heated_sqft">What is the weight of the pet(s)?</label>
                          <input type="text" name="petsWeight" id="total_acreage" class="form-control has-icon"
                            data-icon="fa-solid fa-dog" required>
                        </div>
                      </div>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="32">
                <div class="form-group">
                  <label class="fw-bold">Unit Size:</label>
                  <input type="text" name="unitSize" class="form-control has-icon"
                    data-icon="fa-solid fa-hotel">
                </div>
                @php
                  $unit_types = [
                      ['name' => '1 Bed/1 Bath', 'target' => ''],
                      ['name' => '1 Bedroom', 'target' => ''],
                      ['name' => '2 Bed/1 Bath', 'target' => ''],
                      ['name' => '2 Bed/2 Bath', 'target' => ''],
                      ['name' => '2 Bedroom', 'target' => ''],
                      ['name' => '3 Bed/1 Bath', 'target' => ''],
                      ['name' => '3 Bed/2 Bath', 'target' => ''],
                      ['name' => '3 Bedroom', 'target' => ''],
                      ['name' => '4 Bedroom or More', 'target' => ''],
                      ['name' => '4+ Bed/1 Bath', 'target' => ''],
                      ['name' => '4+ Bed/2 Bath', 'target' => ''],
                      ['name' => 'Apartments', 'target' => ''],
                      ['name' => 'Efficiency', 'target' => ''],
                      ['name' => 'Loft', 'target' => ''],
                      ['name' => "Manager's Unit", 'target' => ''],
                      ['name' => 'Other', 'target' => '.custom_unit_type'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Income Property Criteria: Please Enter the Buyer's Preferred Income Property
                    Criteria:</label>
                  <select class="grid-picker" name="unit_types" id="condition_interested" multiple required>
                    <option value="">Select</option>
                    @foreach ($unit_types as $unity_type)
                      <option value="{{ $unity_type['name'] }}" class="card flex-row"
                        data-target="{{ $unity_type['target'] }}" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $unity_type['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group custom_unit_type d-none">
                    <label class="fw-bold">Please select the unit types the buyer would be
                      interested in purchasing?</label>
                    <input type="text" name="custom_unit_type" id="custom_unit_type"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                </div>
                <div class="form-group row">
                  <div class="form-group col-6">
                    <label class="fw-bold">Total Number of Units Needed:</label>
                    <input type="text" name="units" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-hotel" required>
                  </div>
                  <div class="form-group col-6">
                    <label class="fw-bold">Minimum Annual Net Income:</label>
                    <input type="text" name="annualIncome" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                  <div class="form-group col-6">
                    <label class="fw-bold">Minimum Cap Rate:</label>
                    <input type="text" name="capRate" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-percent" required>
                  </div>
                  <div class="form-group col-6">
                    <label class="fw-bold">Additional Details:</label>
                    <input type="text" name="additiaonalDetails" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                </div>
              </div>
              <div class="wizard-step" data-step="33">
                <span class="incomeFields">
                  @php
                    $non_negotiable_value = [
                        [
                            'name' => 'Yes',
                            'target' => '.custom_non_negotiable_income',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];

                    $non_negotiable_terms = [
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
                        ['name' => 'Other', 'target' => '.custom_non_negotiable_terms_other_income'],
                    ];
                  @endphp
                  <div class="form-group">
                    <label class="fw-bold">Are there any non-negotiable amenities or property features that
                      the buyer is seeking?</label>
                    <select class="grid-picker" name="has_non_negotiable_terms" id="custom_non_negotiable_income"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($non_negotiable_value as $value)
                        <option value="{{ $value['name'] }}" data-target="{{ $value['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $value['icon'] }}"></i>'>
                          {{ $value['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group custom_non_negotiable_income d-none">
                    <select class="grid-picker" name="non_negotiable_terms[]" id="utilities"
                      style="justify-content: flex-start;" required multiple>
                      <option value="">Select</option>
                      @foreach ($non_negotiable_terms as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                          style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group custom_non_negotiable_terms_other_income d-none">
                    <label class="fw-bold" for="custom_non_negotiable_terms">What non-negotiable amenities or property
                      features is the buyer is seeking? </label>
                    <input type="text" name="custom_non_negotiable_terms_other_income"
                      id="custom_non_negotiable_terms_other_income" placeholder="" class="form-control has-icon"
                      data-icon="fa-solid fa-ruler-combined"
                      data-msg-required="Please enter Custom Non-Negotiable Terms" required>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="34">
                <span class="incomeFields">
                  @php
                    $financings = [
                        ['name' => 'Cash', 'target' => '.customCashIncome'],
                        ['name' => 'Conventional', 'target' => '.customConventional'],
                        ['name' => 'Seller Financing', 'target' => '.custom_seller_financing_income'],
                        ['name' => 'FHA', 'target' => '.yes_no'],
                        ['name' => 'Jumbo', 'target' => ''],
                        ['name' => 'VA', 'target' => ''],
                        ['name' => 'No-Doc', 'target' => ''],
                        ['name' => 'Non-QM', 'target' => ''],
                        ['name' => 'Assumable', 'target' => '.custom_assumable_income'],
                        ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade_income'],
                        ['name' => 'Lease Option', 'target' => '.lease_optionIncome'],
                        ['name' => 'Lease Purchase', 'target' => '.lease_purchaseIncome'],
                        ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrencyIncome'],
                        ['name' => 'NFT', 'target' => '.nftIncome'],
                        ['name' => 'USDA', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_other_income'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="form-group">
                      <label class="fw-bold">Maximum Budget:</label>
                      <input type="text" class="form-control has-icon" name="maxBudget"
                        data-icon="fa-solid fa-dollar-sign" required>
                    </div>
                    <div class="col-md-12">
                      <label class="fw-bold">Please indicate the financing/currency that the buyer will use to purchase
                        the
                        property:</label>
                      <div class="select2-parent">
                        <select name="financings[]" class="grid-picker" multiple id="financingIncome" required>
                          @foreach ($financings as $financing)
                            <option value="{{ $financing['name'] }}" data-target="{{ $financing['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="fa-regular fa-circle-check"></i>'>
                              {{ $financing['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>

                    </div>
                  </div>

                  <div class="form-group" id="conventionalIncome" style="display: none;">
                    @php
                      $customOptions = [
                          [
                              'target' => '.customOptionsYesIncome',
                              'name' => 'Yes',
                              'icon' => 'fa-regular fa-check-circle',
                          ],
                          [
                              'target' => '.customOptionsNoIncome',
                              'name' => 'No',
                              'icon' => 'fa-regular fa-circle-xmark',
                          ],
                      ];
                    @endphp
                    <label class="fw-bold">Has the buyer been pre-approved for a loan? </label>
                    <select name="conventionalOptions[]" class="grid-picker" id=""
                      style="justify-content: flex-start;" required>
                      @foreach ($customOptions as $item)
                        <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                          data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group customOptionsYesIncome d-none">
                      <label class="fw-bold">How much is the buyer pre-approved for?</label>
                      <input type="text" name="financingOptionsConventional[]" data-type="customOptionsYesIncome"
                        id="customOptionsYes" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group customOptionsNoIncome d-none">
                      <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                      <input type="text" name="financingOptionsConventional[]" data-type="customOptionsNoIncome"
                        id="customOptionsNoInput" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                      <div class="form-group">
                        <label class="fw-bold">If not pre-approved yet, does the buyer need a lender recommendation?
                        </label>
                        @php
                          $customOptionsYesNo = [
                              ['target' => '', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                              ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                          ];
                        @endphp
                        <select name="conventionalOptionsYesNo[]" class="grid-picker" id=""
                          style="justify-content: flex-start;" required>
                          @foreach ($customOptionsYesNo as $item)
                            <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                              data-target="{{ $item['target'] }}" class="card flex-row"
                              style="width:calc(33.3% - 10px);">
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                  </div>
                  {{-- Cash --}}
                  <div class="form-group customCashIncome d-none">
                    <label class="fw-bold" for="customCashIncome">What is the buyer's budget for purchasing a
                      property?</label>
                    <input type="text" name="financingOptionsCash[]" id="customCash"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  {{-- Cash --}}
                  {{-- Lease Option --}}
                  <div class="form-group lease_optionIncome" id="leaseOptionIncome" style="display: none;">
                    <div class="form-group">
                      <label class="fw-bold">What specific terms is the buyer considering for the lease
                        purchase?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What is the proposed duration or term of the lease?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much is the buyer comfortable paying in monthly lease payments?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Is the buyer offering an option fee, and if so, what is the amount?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="custom_lease_option" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Lease Option --}}

                  {{-- seller financing --}}
                  <div class="form-group custom_seller_financing_income d-none">
                    <div class="form-group">
                      <label class="fw-bold">What terms is the buyer offering to the seller?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What down payment is the buyer offering the seller?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What interest rate is the buyer proposing?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- seller financing --}}
                  {{-- Exchange Trade --}}
                  <div class="form-group custom_exchange_trade_income d-none">
                    <div class="form-group">
                      <label class="fw-bold">What item would the buyer like to Exchange/Trade?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much is the buyer’s Exchange/Trade item valued at?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much cash will the buyer use on top of the Exchange/Trade item?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Exchange Trade --}}
                  {{-- Assemble --}}
                  {{-- <div class="form-group custom_assumable_income d-none">
                    <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                    <input type="text" name="financingOptionsAssumble[]" data-type="custom_assumable"
                      id="custom_assumable" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      data-msg-required="Please Enter Type of Financing">
                  </div> --}}
                  {{-- Assemble --}}
                  {{-- Cryptocurrency --}}
                  <div class="form-group custom_cryptocurrencyIncome d-none">
                    <div class="form-group">
                      <label class="fw-bold">What type of Cryptocurrency does the buyer have?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrency"
                        id="custom_cryptocurrency" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will be used with
                        Cryptocurrency?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrency"
                        id="custom_cryptocurrency" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will be used with Cash?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrency"
                        id="custom_cryptocurrency" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <label class="">Note: Cryptocurrency can be converted to cash at closing.</label>
                  </div>
                  {{-- Cryptocurrency --}}
                  {{-- NFT --}}
                  <div class="form-group nftIncome d-none">
                    <div class="form-group">
                      <label class="fw-bold">What type of NFT is the buyer offering?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will the buyer offer as an
                        NFT?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will the buyer offer in cash?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- NFT --}}
                  {{-- Other --}}
                  <div class="form-group custom_other_income d-none">
                    <div class="form-group">
                      <label class="fw-bold">What type of financing/funds will the buyer use to purchase the
                        property?</label>
                      <input type="text" name="financingOther[]" data-type="custom_other" id="custom_other"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                </span>

              </div>
              <div class="wizard-step" data-step="35">
                <span class="incomeFields">
                  <div class="form-group">
                    <label class="fw-bold">When will the buyer be ready to purchase a property?</label>
                    @php
                      $timeframes = [
                          ['name' => 'ASAP', 'target' => ''],
                          ['name' => '1 Month', 'target' => ''],
                          ['name' => '2 Months', 'target' => ''],
                          ['name' => '3 Months', 'target' => ''],
                          ['name' => '4 Months', 'target' => ''],
                          ['name' => '5 Months', 'target' => ''],
                          ['name' => '6 Months', 'target' => ''],
                          ['name' => 'Over 6 Months', 'target' => ''],
                          // ['name' => 'Other', 'target' => '.custom_amount1']
                      ];
                    @endphp
                    <select class="grid-picker" name="buying_timeframe" id="buying_timeframe"
                      style="justify-content: flex-start;" onchange="buyerPrompt(this.value);" required>
                      <option value="">Select</option>
                      @foreach ($timeframes as $item)
                        @php
                          if ($item['name'] == 'Other') {
                              $target = '.custom_amount12';
                          } else {
                              $target = '';
                          }
                        @endphp
                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>

                  </div>
                  <div class="form-group custom_amount12 d-none">
                    <label class="fw-bold">When will the buyer be ready to purchase a property? </label>
                    <input type="text" class="form-control has-icon" name="custom_buyer_looking_to_purchase"
                      data-icon="fa-regular fa-check-circle" id="custom_amount" required />
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="36">
                <span class="incomeFields">
                  <div class="form-group">
                    <label class="fw-bold">
                      What is the timeframe offered to the agent in the Buyer Agency Agreement?
                    </label>
                    @php
                      $buyer_prefered_vacant = [
                          ['name' => '1 Month', 'target' => ''],
                          ['name' => '2 Months', 'target' => ''],
                          ['name' => '3 Months', 'target' => ''],
                          ['name' => '4 Months', 'target' => ''],
                          ['name' => '5 Months', 'target' => ''],
                          ['name' => '6 Months', 'target' => ''],
                          ['name' => 'Over 6 Months', 'target' => ''],
                          ['name' => 'Negotiable', 'target' => ''],
                          ['name' => 'Other', 'target' => '.custom_icome_pro'],
                      ];
                    @endphp
                    <select class="grid-picker" name="timeframe" id="term_financings"
                      style="justify-content: flex-start;" required onchange="showPrompt(this.value);">
                      <option value="">Select</option>
                      @foreach ($buyer_prefered_vacant as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group custom_icome_pro d-none">
                    <label class="fw-bold"> What is the timeframe offered to the agent in the Buyer Agency
                      Agreement?</label>
                    <input type="text" class="form-control has-icon" placeholder="" name="other_timeframe"
                      data-icon="fa-solid fa-ruler-combined" id="custom_amount" required />
                  </div>
                  <div class="form-group prompt_response d-none">
                    {{-- <p class="text-danger">This service is designed for buyers who intend to purchase a property within
                      the
                      next three months.</p> --}}
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="37">
                <span class="incomeFields">
                  <label class="fw-bold">
                    In cases where a property is For Sale By Owner or a Buyer’s agent commission is not offered, how will
                    the Buyer be able to pay their agent's commission?
                  </label>
                  <?php
                  $commissionIncome = [['target' => '', 'name' => "The Buyer will ask the agent to include their agent's commission as a part of the offer to the Seller."], ['target' => '', 'name' => "The Buyer will be able to pay their agent's commission out of pocket."], ['target' => '', 'name' => 'The Buyer will not allow any compensation that is not already offered.'], ['target' => '.OtherCommissionIncome', 'name' => 'Other']];
                  ?>
                  <select class="grid-picker" name="commission" id="commissionIncomeOpt"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($commissionIncome as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group OtherCommissionIncome d-none">
                    <label class="fw-bold">How will the Buyer be able to pay their agent's commission?</label>
                    <input type="text" name="OtherCommission" class="form-control has-icon col-3"
                      data-icon="fa-solid fa-percent" data-msg-required="" required>
                  </div>
                  <div class="form-group" id="commissionIncomeOptional" style="display:none;">
                    <label class="fw-bold">How much will the Buyer compensate their agent?</label>
                    <?php
                    $commissionOpt = [['target' => '', 'name' => '2%'], ['target' => '', 'name' => '2.5%'], ['target' => '', 'name' => '3%'], ['target' => '', 'name' => 'Negotiable'], ['target' => '.otherComIncomeOpt', 'name' => 'Other']];
                    ?>
                    <select class="grid-picker" name="commissionOpt" style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($commissionOpt as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group otherComIncomeOpt d-none">
                      <label class="fw-bold">How much will the Buyer compensate their agent?</label>
                      <input type="text" name="OtherComOptions" class="form-control has-icon col-3"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="" required>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="38">
                <span class="incomeFields">
                  <label class="fw-bold">Would the buyer like to request that their hired agent offer a concession to
                    assist with the buyer's closing costs from the agent's commission?
                  </label>
                  <?php
                  $concession = [['target' => '', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'], ['target' => '.concessionOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle']];
                  ?>
                  <select class="grid-picker" name="concession" id=""
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($concession as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group">
                    <small>(Available in all the states except: Alabama, Alaska, Kansas, Mississippi,
                      Missouri,
                      Oklahoma, Oregon, and Tennessee. This list is subject to change.) </small>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="39">
                <span class="incomeFields">
                  <div class="form-group">
                    <label class="fw-bold">
                      What are the most important aspects the buyer will consider when hiring a real estate agent?
                    </label>
                    <textarea name="aspect_hiring_agent" class="form-control" rows="5">{{ old('aspect_hiring_agent') }}</textarea>
                  </div>

                  <div class=" form-group">
                    <label class="fw-bold">What additional details would the buyer like to share with the agent?
                    </label>
                    <textarea name="additional_details" class="form-control" rows="5">{{ old('additional_details') }}</textarea>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="40">
                @php
                  $services_data = [
                      [
                          'name' =>
                              'Assist the buyer in obtaining pre-approval or exploring financing options to determine their purchasing power.',
                          'target' => '',
                      ],
                      ['name' => 'List the Buyer’s Criteria Listing on the platform BidYourOffer.com.', 'target' => ''],
                      [
                          'name' =>
                              "Market the buyer's listing on numerous groups, pages, and affiliates through a listing link or QR code that links directly to the listing on BidYourOffer.com.",
                          'target' => '',
                      ],
                      [
                          'name' =>
                              "Promote the buyer's listing on social media platforms using a listing link or QR code that directs to the buyer's listing on the BidYourOffer.com platform.",
                          'target' => '',
                      ],
                      [
                          'name' =>
                              "Send prompt email notifications containing properties that meet the buyer's criteria as soon as they are listed, ensuring access to the most up-to-date listings.",
                          'target' => '',
                      ],
                      ['name' => 'Schedule and accompany the buyer on property viewings and showings.', 'target' => ''],
                      ['name' => 'Schedule video tours of the property as needed.', 'target' => ''],
                      [
                          'name' =>
                              'Provide detailed market analysis and comparative market information to help the buyer make informed decisions.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist the buyer in negotiations and in preparing offers to maximize their chances of securing the desired property.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist the buyer with the review and explanation of all contractual documents and disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Coordinate and oversee the home inspection process, including making recommendations for trusted inspectors.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Facilitate communication and coordination with other professionals involved in the transaction, such as lenders, attorneys, and title companies.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide guidance and support throughout the entire purchase transaction, from offer acceptance to closing.',
                          'target' => '',
                      ],
                      [
                          'target' => '.custom_service_buyer_want_res',
                          'name' => 'Other-Add additional services as needed.',
                      ],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Select the services that the buyer requests from an agent:</label>
                  <select class="grid-picker" name="services[]" onchange="service_data(this.value)" id="services"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($services_data as $service)
                      <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}"
                        class="card flex-row" style="width:calc(100% - 0px);"
                        data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                        {{ $service['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_service_buyer_want_res d-none">
                  <label class="fw-bold">What additional services would the buyer like to request from an agent?</label>
                  <input type="text" class="form-control has-icon" name="custom_service_buyer_want_res"
                    data-icon="fa-solid fa-ruler-combined" id="custom_service_buyer_want_res" required />
                </div>
              </div>
              <div class="wizard-step" data-step="41">
                <span class="incomeFields">
                  <div class="row form-group">
                    <div class="col-md-6">
                      <label class="fw-bold">First Name:</label>
                      <input type="text" class="form-control has-icon" name="first_name" id="first_name"
                        value="{{ auth()->user()->first_name }}" data-icon="fa-solid fa-user" />
                    </div>
                    <div class="col-md-6">
                      <label class="fw-bold">Last Name:</label>
                      <input type="text" class="form-control has-icon" name="last_name" id="last_name"
                        value="{{ auth()->user()->last_name }}" data-icon="fa-solid fa-user" />
                    </div>
                  </div>
                  <div class="row form-group">
                    <div class="col-md-6">
                      <label class="fw-bold">Phone Number:</label>
                      <input type="text" class="form-control has-icon" name="phone" id="phone"
                        value="{{ auth()->user()->phone }}" data-icon="fa-solid fa-phone" />
                    </div>
                    <div class="col-md-6">
                      <label class="fw-bold">Email:</label>
                      <input type="email" class="form-control has-icon" name="email" id="email"
                        value="{{ auth()->user()->email }}" data-icon="fa-solid fa-envelope" />
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-6">
                      <label class="fw-bold mt-1">Video:</label>
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
                    <div class="col-6">
                      <div class="upload form-group">
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
                </span>
              </div>
              <div class="wizard-step" data-step="42">
                <div class="form-group">
                  <label class="fw-bold">
                    Acceptable Special Sale Provisions:
                  </label>
                  @php
                    $seller_property = [
                        ['target' => '', 'name' => 'Auction'],
                        ['target' => '', 'name' => 'Bank Owned/REO'],
                        [
                            'target' => '.assignment_contract_vacant',
                            'name' => 'To Assignment Contract (Wholesale Properties)',
                        ],
                        ['target' => '', 'name' => 'Short Sale'],
                        ['target' => '', 'name' => 'Probate'],
                        ['target' => '', 'name' => 'Government Owned'],
                        ['target' => '', 'name' => 'None'],
                        ['target' => '.custom_vacant_land', 'name' => 'Other'],
                    ];
                  @endphp
                  <div class="select2-parent">
                    <select name="special_sale[]" id="special_sale" class="grid-picker"
                      style="justify-content: flex-start;" required multiple>
                      <option value=""></option>
                      @foreach ($seller_property as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row " style="width:calc(33.33% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="form-group custom_vacant_land d-none">
                  <label class="fw-bold" for="custom_vacant_land">
                    Acceptable Special Sale Provisions:
                  </label>
                  <input type="text" name="custom_special_sale" id="custom_vacant_land"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>

                <div class="form-group d-none assignment_contract_vacant">
                  <label class="fw-bold">
                    Is the buyer currently under contract with a property they would like to assign?
                  </label>
                  @php
                    $seller_contract_options = [
                        ['name' => 'Yes', 'target' => '.vacant_seller_contract_yes'],
                        ['name' => 'No', 'target' => '.vacant_seller_contract_no'],
                    ];
                  @endphp
                  <select name="contribute_term" id="contribute_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($seller_contract_options as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>

                  <div class="form-group vacant_seller_contract_yes d-none">
                    <label class="fw-bold">What fee would the buyer pay the agent to assign the contract? </label>
                    <input type="text" class="form-control has-icon" placeholder=""
                      name="commercialseller_contract_yes" data-icon="fa-solid fa-dollar-sign"
                      id="commercialseller_contract_yes" required />
                  </div>
                  <div class="form-group vacant_seller_contract_no d-none">
                    <label class="fw-bold">Is the buyer looking to sell their contract? </label>
                    @php
                      $seller_contract_yes_no = [
                          ['name' => 'Yes', 'target' => '.contract_vacant_yes'],
                          ['name' => 'No', 'target' => ''],
                      ];
                    @endphp
                    <select name="vacant_seller_contract_no" id="commercial_seller_contract_no" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($seller_contract_yes_no as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group contract_vacant_yes d-none">
                      <label class="fw-bold">What fee would the buyer pay the agent to assign the contract? </label>
                      <input type="text" class="form-control has-icon" placeholder=""
                        name="commercialseller_contract_yes" data-icon="fa-solid fa-ruler-combined"
                        id="commercialseller_contract_yes" required />
                    </div>
                  </div>
                </div>

              </div>
              <div class="wizard-step" data-step="43">
                <span class="vacantFields">
                  @php
                    $total_acreages = [
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
                  <div class="form-group commercial_hide">
                    <label class="fw-bold">Minimum Total Acreage Needed: </label>
                    <select class="grid-picker" name="total_acreage" id="total_acreage"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($total_acreages as $total_acreage)
                        <option value="{{ $item['name'] }}" data-target="{{ $total_acreage['target'] }}"
                          class="card flex-column" style="width:calc(25% - 10px);"
                          data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                          {{ $total_acreage['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="44">
                <span class="vacantFields">
                  <div class="form-group">
                    @php
                      $preferenceNeedVacant = [
                          ['target' => '.preferenceYesVacant', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '.preferenceNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                          [
                              'target' => '.preferenceOptional',
                              'name' => 'Optional',
                              'icon' => 'fa-regular fa-check-circle',
                          ],
                      ];
                    @endphp
                    <label class="fw-bold">View Preference Needed: </label>
                    <div class="form-group ">
                      <select name="prefrenceOptions" class="grid-picker" id="preferenceOptVacant"
                        style="justify-content: flex-start;" required>
                        @foreach ($preferenceNeedVacant as $item)
                          <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                            data-target="{{ $item['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);">
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>
                    @php
                      $preferenceOptionsVacant = [
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
                          ['name' => 'Other', 'target' => '.other_preference_vacant'],
                      ];
                    @endphp
                    <div class="form-group" id="preferenceOptionsVacant" style="display: none;">
                      <select class="grid-picker" name="prefrence[]" style="justify-content: flex-start;" required
                        multiple>
                        @foreach ($preferenceOptionsVacant as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column" style="width:calc(25% - 10px);"
                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>
                    <div class="form-group other_preference_vacant d-none">
                      <label class="fw-bold" for="other_preference"> View Preference: </label>
                      <input type="text" name="preferenceOther" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="45">
                <div class="form-group">
                  <label class="fw-bold">Maximum Budget:</label>
                  <input type="text" class="form-control has-icon" name="maxBudget"
                    data-icon="fa-solid fa-dollar-sign" required>
                </div>
                <span class="vacantFields">
                  @php
                    $financings = [
                        ['name' => 'Cash', 'target' => '.customCashVacant'],
                        ['name' => 'Conventional', 'target' => '.customConventional'],
                        ['name' => 'Seller Financing', 'target' => '.custom_seller_financing_vacant'],
                        ['name' => 'FHA', 'target' => '.yes_no'],
                        ['name' => 'Jumbo', 'target' => ''],
                        ['name' => 'VA', 'target' => ''],
                        ['name' => 'No-Doc', 'target' => ''],
                        ['name' => 'Non-QM', 'target' => ''],
                        ['name' => 'Assumable', 'target' => '.custom_assumable_vacant'],
                        ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade_vacant'],
                        ['name' => 'Lease Option', 'target' => '.lease_optionVacant'],
                        ['name' => 'Lease Purchase', 'target' => '.lease_purchaseVacant'],
                        ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency_vacant'],
                        ['name' => 'NFT', 'target' => '.nftVacant'],
                        ['name' => 'USDA', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_other_vacant'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="col-md-12">
                      <label class="fw-bold">Please indicate the financing/currency that the buyer will use to purchase
                        the
                        property:</label>
                      <div class="select2-parent">
                        <select name="financings[]" class="grid-picker" multiple id="financingVacant" required>
                          @foreach ($financings as $financing)
                            <option value="{{ $financing['name'] }}" data-target="{{ $financing['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="fa-regular fa-circle-check"></i>'>
                              {{ $financing['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>

                    </div>
                  </div>

                  <div class="form-group" id="conventionalVacant" style="display: none;">
                    @php
                      $customOptions = [
                          [
                              'target' => '.customOptionsYesVacant',
                              'name' => 'Yes',
                              'icon' => 'fa-regular fa-check-circle',
                          ],
                          [
                              'target' => '.customOptionsNoVacant',
                              'name' => 'No',
                              'icon' => 'fa-regular fa-circle-xmark',
                          ],
                      ];
                    @endphp
                    <label class="fw-bold">Has the buyer been pre-approved for a loan? </label>
                    <select name="conventionalOptions[]" class="grid-picker" id=""
                      style="justify-content: flex-start;" required>
                      @foreach ($customOptions as $item)
                        <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                          data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group customOptionsYesVacant d-none">
                      <label class="fw-bold">How much is the buyer pre-approved for?</label>
                      <input type="text" name="financingOptionsConventional[]" data-type="customOptionsYes"
                        id="customOptionsYes" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group customOptionsNoVacant d-none">
                      <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                      <input type="text" name="financingOptionsConventional[]" data-type="customOptionsNoInput"
                        id="customOptionsNoInput" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                      <div class="form-group">
                        <label class="fw-bold">If not pre-approved yet, does the buyer need a lender recommendation?
                        </label>
                        @php
                          $customOptionsYesNo = [
                              ['target' => '', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                              ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                          ];
                        @endphp
                        <select name="customOptionsYesNo[]" class="grid-picker" id=""
                          style="justify-content: flex-start;" required>
                          @foreach ($customOptionsYesNo as $item)
                            <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                              data-target="{{ $item['target'] }}" class="card flex-row"
                              style="width:calc(33.3% - 10px);">
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                  </div>
                  {{-- Cash --}}
                  <div class="form-group customCashVacant d-none">
                    <label class="fw-bold" for="customCash">What is the buyer's budget for purchasing a
                      property?</label>
                    <input type="text" name="financingOptionsCash[]" id="customCash"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  {{-- Cash --}}
                  {{-- Lease Option --}}
                  <div class="form-group" id="financingOptionVacant" style="display: none;">
                    <div class="form-group">
                      <label class="fw-bold">What specific terms is the buyer considering for the lease purchase?
                      </label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What is the proposed duration or term of the lease?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much is the buyer comfortable paying in monthly lease payments?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Is the buyer offering an option fee, and if so, what is the amount?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Lease Option --}}

                  {{-- NFT --}}

                  {{-- seller financing --}}
                  <div class="form-group custom_seller_financing_vacant d-none" id="custom_seller_financing">
                    <div class="form-group">
                      <label class="fw-bold">What terms is the buyer offering to the seller?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What down payment is the buyer offering the seller?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What interest rate is the buyer proposing?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- seller financing --}}
                  {{-- Exchange Trade --}}
                  <div class="form-group custom_exchange_trade_vacant d-none">
                    <div class="form-group">
                      <label class="fw-bold">What item would the buyer like to Exchange/Trade?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much is the buyer’s Exchange/Trade item valued at?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much cash will the buyer use on top of the Exchange/Trade item?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Exchange Trade --}}
                  {{-- NFT --}}

                  <div class="form-group nftVacant d-none" id="">
                    <div class="form-group">
                      <label class="fw-bold">What type of NFT is the buyer offering?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will the buyer offer as an
                        NFT?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will the buyer offer in cash?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- NFT --}}
                  {{-- Assemble --}}
                  {{-- <div class="form-group custom_assumable_vacant d-none">
                    <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                    <input type="text" name="financingOptionsAssumble[]" data-type="custom_assumable"
                      id="custom_assumable" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      data-msg-required="Please Enter Type of Financing">
                  </div> --}}
                  {{-- Assemble --}}
                  {{-- Cryptocurrency --}}
                  <div class="form-group custom_cryptocurrency_vacant d-none">
                    <div class="form-group">
                      <label class="fw-bold">What type of Cryptocurrency does the buyer have?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrency"
                        id="custom_cryptocurrency" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will be used with
                        Cryptocurrency?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrency"
                        id="custom_cryptocurrency" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will be used with Cash?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrency"
                        id="custom_cryptocurrency" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <label class="">Note: Cryptocurrency can be converted to cash at closing.</label>
                  </div>
                  {{-- Cryptocurrency --}}
                  {{-- Other --}}
                  <div class="form-group custom_other_vacant d-none">
                    <div class="form-group">
                      <label class="fw-bold">What type of financing/funds will the buyer use to purchase the
                        property?</label>
                      <input type="text" name="financingOther[]" data-type="custom_other" id="custom_other"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Other --}}
                </span>
              </div>
              <div class="wizard-step" data-step="46">
                <span class="vacantFields">
                  <div class="form-group">
                    <label class="fw-bold">When will the buyer be ready to purchase a property? </label>
                    @php
                      $timeframes = [
                          ['name' => 'ASAP', 'target' => ''],
                          ['name' => '1 Month', 'target' => ''],
                          ['name' => '2 Months', 'target' => ''],
                          ['name' => '3 Months', 'target' => ''],
                          ['name' => '4 Months', 'target' => ''],
                          ['name' => '5 Months', 'target' => ''],
                          ['name' => '6 Months', 'target' => ''],
                          ['name' => 'Over 6 Months', 'target' => ''],
                          // ['name' => 'Other', 'target' => '.custom_amount1']
                      ];
                    @endphp
                    <select class="grid-picker" name="buying_timeframe" id="buying_timeframe"
                      style="justify-content: flex-start;" onchange="buyerPrompt(this.value);" required>
                      <option value="">Select</option>
                      @foreach ($timeframes as $item)
                        @php
                          if ($item['name'] == 'Other') {
                              $target = '.custom_amount12';
                          } else {
                              $target = '';
                          }
                        @endphp
                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>

                  </div>
                  <div class="form-group custom_amount12 d-none">
                    <label class="fw-bold">When will the buyer be ready to purchase a property? </label>
                    <input type="text" class="form-control has-icon" name="custom_buyer_looking_to_purchase"
                      data-icon="fa-regular fa-check-circle" id="custom_amount" required />
                  </div>
              </div>
              </span>
              <div class="wizard-step" data-step="47">
                <span class="vacantFields">
                  <div class="form-group">
                    <label class="fw-bold">
                      What is the timeframe offered to the agent in the Buyer Agency Agreement?
                    </label>
                    @php
                      $buyer_prefered_vacant = [
                          ['name' => '1 Month', 'target' => ''],
                          ['name' => '2 Months', 'target' => ''],
                          ['name' => '3 Months', 'target' => ''],
                          ['name' => '4 Months', 'target' => ''],
                          ['name' => '5 Months', 'target' => ''],
                          ['name' => '6 Months', 'target' => ''],
                          ['name' => 'Over 6 Months', 'target' => ''],
                          ['name' => 'Negotiable', 'target' => ''],
                          ['name' => 'Other', 'target' => '.custom_buyer_vacant'],
                      ];
                    @endphp
                    <select class="grid-picker" name="timeframe" id="term_financings"
                      style="justify-content: flex-start;" required onchange="showPrompt(this.value);">
                      <option value="">Select</option>
                      @foreach ($buyer_prefered_vacant as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group custom_buyer_vacant d-none">
                    <label class="fw-bold"> What is the timeframe offered to the agent in the Buyer Agency
                      Agreement?</label>
                    <input type="text" class="form-control has-icon" placeholder="" name="other_timeframe"
                      data-icon="fa-solid fa-ruler-combined" id="custom_amount" required />
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="48">
                <span class="vacantFields">
                  <label class="fw-bold">
                    In cases where a property is For Sale By Owner or a Buyer’s agent commission is not offered, how will
                    the Buyer be able to pay their agent's commission?
                  </label>
                  <?php
                  $commissionVacant = [['target' => '', 'name' => "The Buyer will ask the agent to include their agent's commission as a part of the offer to the Seller."], ['target' => '', 'name' => "The Buyer will be able to pay their agent's commission out of pocket."], ['target' => '', 'name' => 'The Buyer will not allow any compensation that is not already offered.'], ['target' => '.OtherCommissionVacant', 'name' => 'Other']];
                  ?>
                  <select class="grid-picker" name="commission" id="commissionVacantOpt"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($commissionVacant as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group OtherCommissionVacant d-none">
                    <label class="fw-bold">How will the Buyer be able to pay their agent's commission? </label>
                    <input type="text" name="OtherCommission" class="form-control has-icon col-3"
                      data-icon="fa-solid fa-ruler-combined" data-msg-required="" required>
                  </div>
                  <div class="form-group" id="commissionVacantOptional" style="display:none;">
                    <label class="fw-bold">How much will the Buyer compensate their agent?</label>
                    <?php
                    $commissionOpt = [['target' => '', 'name' => '2%'], ['target' => '', 'name' => '2.5%'], ['target' => '', 'name' => '3%'], ['target' => '', 'name' => 'Negotiable'], ['target' => '.otherComResOptVacant', 'name' => 'Other']];
                    ?>
                    <select class="grid-picker" name="commissionOpt" style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($commissionOpt as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group otherComResOptVacant d-none">
                      <label class="fw-bold">How much will the Buyer compensate their agent?</label>
                      <input type="text" name="OtherComOptions" class="form-control has-icon col-3"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="" required>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="49">
                <span class="vacantFields">
                  <label class="fw-bold">Would the buyer like to request that their hired agent offer a concession to
                    assist with the buyer's closing costs from the agent's commission?
                  </label>
                  <?php
                  $concession = [['target' => '', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'], ['target' => '.concessionOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle']];
                  ?>
                  <select class="grid-picker" name="concession" id=""
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($concession as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group">
                    <small>(Available in all the states except: Alabama, Alaska, Kansas, Mississippi,
                      Missouri,
                      Oklahoma, Oregon, and Tennessee.) </small>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="50">
                <span class="vacantFields">
                  <div class="form-group">
                    <label class="fw-bold">
                      What are the most important aspects the buyer will consider when hiring a real estate agent?
                    </label>
                    <textarea name="aspect_hiring_agent" class="form-control" rows="5">{{ old('aspect_hiring_agent') }}</textarea>
                  </div>

                  <div class=" form-group">
                    <label class="fw-bold">What additional details would the buyer like to share with the
                      agent? </label>
                    <textarea name="additional_details" class="form-control" rows="5">{{ old('additional_details') }}</textarea>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="51">
                @php
                  $services_data = [
                      [
                          'name' =>
                              'Assist the buyer in obtaining pre-approval or exploring financing options to determine their purchasing power.',
                          'target' => '',
                      ],
                      ['name' => 'List the Buyer’s Criteria Listing on the platform BidYourOffer.com.', 'target' => ''],
                      [
                          'name' =>
                              "Market the buyer's listing on numerous groups, pages, and affiliates through a listing link or QR code that links directly to the listing on BidYourOffer.com.",
                          'target' => '',
                      ],
                      [
                          'name' =>
                              "Promote the buyer's listing on social media platforms using a listing link or QR code that directs to the buyer's listing on the BidYourOffer.com platform.",
                          'target' => '',
                      ],
                      [
                          'name' =>
                              "Send prompt email notifications containing properties that meet the buyer's criteria as soon as they are listed, ensuring access to the most up-to-date listings.",
                          'target' => '',
                      ],
                      ['name' => 'Schedule and accompany the buyer on property viewings and showings.', 'target' => ''],
                      ['name' => 'Schedule video tours of the property as needed.', 'target' => ''],
                      [
                          'name' =>
                              'Provide detailed market analysis and comparative market information to help the buyer make informed decisions.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist the buyer in negotiations and in preparing offers to maximize their chances of securing the desired property.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist the buyer with the review and explanation of all contractual documents and disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Coordinate and oversee the home inspection process, including making recommendations for trusted inspectors.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Facilitate communication and coordination with other professionals involved in the transaction, such as lenders, attorneys, and title companies.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide guidance and support throughout the entire purchase transaction, from offer acceptance to closing.',
                          'target' => '',
                      ],
                      [
                          'target' => '.custom_service_buyer_want_vancat',
                          'name' => 'Other-Add additional services as needed.',
                      ],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Select the services that the buyer requests from an agent:</label>
                  <select class="grid-picker" name="services[]" onchange="service_data(this.value)" id="services"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($services_data as $service)
                      <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}"
                        class="card flex-row" style="width:calc(100% - 0px);"
                        data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                        {{ $service['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_service_buyer_want_vancat d-none">
                  <label class="fw-bold">What additional services would the buyer like to request from an agent?</label>
                  <input type="text" class="form-control has-icon" name="custom_service_buyer_want_vancat"
                    data-icon="fa-solid fa-ruler-combined" id="custom_service_buyer_want_vancat" required />
                </div>
              </div>
              <div class="wizard-step" data-step="52">
                <span class="vacantFields">
                  <div class="row form-group">
                    <div class="col-md-6">
                      <label class="fw-bold">First Name:</label>
                      <input type="text" class="form-control has-icon" name="first_name" id="first_name"
                        value="{{ auth()->user()->first_name }}" data-icon="fa-solid fa-user" />
                    </div>
                    <div class="col-md-6">
                      <label class="fw-bold">Last Name:</label>
                      <input type="text" class="form-control has-icon" name="last_name" id="last_name"
                        value="{{ auth()->user()->last_name }}" data-icon="fa-solid fa-user" />
                    </div>
                  </div>
                  <div class="row form-group">
                    <div class="col-md-6">
                      <label class="fw-bold">Phone Number:</label>
                      <input type="text" class="form-control has-icon" name="phone" id="phone"
                        value="{{ auth()->user()->phone }}" data-icon="fa-solid fa-phone" />
                    </div>
                    <div class="col-md-6">
                      <label class="fw-bold">Email:</label>
                      <input type="email" class="form-control has-icon" name="email" id="email"
                        value="{{ auth()->user()->email }}" data-icon="fa-solid fa-envelope" />
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-6">
                      <label class="fw-bold mt-1">Video:</label>
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
                    <div class="col-6">
                      <div class="upload form-group">
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
                </span>
              </div>
              <div class="wizard-step" data-step="53">
                <div class="form-group">
                  <label class="fw-bold">
                    Acceptable Special Sale Provisions:
                  </label>
                  @php
                    $seller_property = [
                        ['target' => '', 'name' => 'Auction'],
                        ['target' => '', 'name' => 'Bank Owned/REO'],
                        [
                            'target' => '.assignment_contract_commercial',
                            'name' => 'To Assignment Contract (Wholesale Properties)',
                        ],
                        ['target' => '', 'name' => 'Short Sale'],
                        ['target' => '', 'name' => 'Probate'],
                        ['target' => '', 'name' => 'Government Owned'],
                        ['target' => '', 'name' => 'None'],
                        ['target' => '.custom_special_sale_commercial', 'name' => 'Other'],
                    ];
                  @endphp
                  <div class="select2-parent">
                    <select name="special_sale[]" id="special_sale" class="grid-picker"
                      style="justify-content: flex-start;" required multiple>
                      <option value=""></option>
                      @foreach ($seller_property as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row " style="width:calc(33.33% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="form-group custom_special_sale_commercial d-none">
                  <label class="fw-bold" for="custom_special_sale_commercial">
                    Acceptable Special Sale Provisions:
                  </label>
                  <input type="text" name="custom_special_sale" id="custom_special_sale"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>

                <div class="form-group d-none assignment_contract_commercial">
                  <label class="fw-bold">
                    Is the buyer currently under contract with a property they would like to assign?
                  </label>
                  @php
                    $seller_contract_options = [
                        ['name' => 'Yes', 'target' => '.commercialseller_contract_yes'],
                        ['name' => 'No', 'target' => '.commercial_seller_contract_no'],
                    ];
                  @endphp
                  <select name="contribute_term" id="contribute_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($seller_contract_options as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>

                  <div class="form-group commercialseller_contract_yes d-none">
                    <label class="fw-bold">What fee would the buyer pay the agent to assign the contract? </label>
                    <input type="text" class="form-control has-icon" placeholder=""
                      name="commercialseller_contract_yes" data-icon="fa-solid fa-dollar-sign"
                      id="commercialseller_contract_yes" required />
                  </div>
                  <div class="form-group commercial_seller_contract_no d-none">
                    <label class="fw-bold">Is the buyer looking to sell their contract? </label>
                    @php
                      $seller_contract_yes_no = [
                          ['name' => 'Yes', 'target' => '.contract_commercial_yes'],
                          ['name' => 'No', 'target' => ''],
                      ];
                    @endphp
                    <select name="commercial_seller_contract_no" id="commercial_seller_contract_no"
                      class="grid-picker" style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($seller_contract_yes_no as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group contract_commercial_yes d-none">
                      <label class="fw-bold">Is the buyer looking to sell their contract? </label>
                      <input type="text" class="form-control has-icon" placeholder=""
                        name="commercialseller_contract_yes" data-icon="fa-solid fa-ruler-combined"
                        id="commercialseller_contract_yes" required />
                    </div>
                  </div>
                </div>
              </div>
              <div class="wizard-step" data-step="54">
                @php
                  $conditions_interested_items = [
                      ['name' => 'Pre-Construction', 'target' => ''],
                      ['name' => 'Currently Being Built', 'target' => ''],
                      ['name' => 'New Construction', 'target' => ''],
                      ['name' => 'Completely Updated: No updates needed', 'target' => ''],
                      ['name' => 'Semi-updated: Needs minor updates', 'target' => ''],
                      ['name' => 'Not Updated: Requires a complete update', 'target' => ''],
                      ['name' => 'Tear Down: Requires complete demolition and reconstruction', 'target' => ''],
                      ['name' => 'Open to any type of property condition', 'target' => ''],
                      ['name' => 'Other', 'target' => '.custom_condition_interested_income'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Acceptable Property Conditions: </label>
                  <select class="grid-picker" name="propsCondition[]" id="custom_condition_interested_income"
                    required multiple>
                    <option value="">Select</option>

                    @foreach ($conditions_interested_items as $condition_interested)
                      <option value="{{ $condition_interested['name'] }}"
                        data-target="{{ $condition_interested['target'] }}" class="card flex-row"
                        style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $condition_interested['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_condition_interested_income d-none">
                  <label class="fw-bold" for="">Acceptable Property Conditions: </label>
                  <input type="text" name="custom_props_condition" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined" required>
                </div>
              </div>
              <div class="wizard-step" data-step="55">
                <span class="commercialFields">
                  @php
                    $bathrooms = [
                        ['name' => '1+', 'target' => ''],
                        ['name' => '1.5+', 'target' => ''],
                        ['name' => '2+', 'target' => ''],
                        ['name' => '2.5+', 'target' => ''],
                        ['name' => '3+', 'target' => ''],
                        ['name' => '3.5+', 'target' => ''],
                        ['name' => '4+', 'target' => ''],
                        ['name' => '4.5+', 'target' => ''],
                        ['name' => '5+', 'target' => ''],
                        ['name' => '6+', 'target' => ''],
                        ['name' => '7+', 'target' => ''],
                        ['name' => '8+', 'target' => ''],
                        ['name' => '9+', 'target' => ''],
                        ['name' => '10+', 'target' => ''],
                        ['name' => 'Other', 'target' => '.other_bathrooms_commercial'],
                    ];
                  @endphp
                  <div class="form-group">
                    <label class="fw-bold">Bathrooms: </label>
                    <select class="grid-picker" name="bathrooms" id="bedrooms" style="justify-content: center;"
                      required>
                      <option value="">Select</option>
                      @foreach ($bathrooms as $bedroom)
                        <option value="{{ $bedroom['name'] }}"data-target="{{ $bedroom['target'] }}"
                          class="card flex-column" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-solid fa-bath"></i>'>
                          {{ $bedroom['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group other_bathrooms_commercial d-none">
                    <label class="fw-bold" for="other_bathrooms_commercial">Bathrooms:
                    </label>
                    <input type="text" name="other_bedrooms" id="other_bathrooms_commercial" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-bath"
                      data-msg-required="Please enter Custom Bedrooms" required>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="56">
                <span class="commercialFields">
                  <div class="form-group">
                    <label class="fw-bold" for="heated_sqft">Minimum Heated Sqft Needed: </label>
                    <input type="number" name="minimum_heated_sqft" id=""
                      class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined">
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="57">
                <span class="commercialFields">
                  @php
                    $total_acreages = [
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
                  <div class="form-group commercial_hide">
                    <label class="fw-bold">Minimum Total Acreage Needed: </label>
                    <select class="grid-picker" name="total_acreage" id="total_acreage"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($total_acreages as $total_acreage)
                        <option value="{{ $item['name'] }}" data-target="{{ $total_acreage['target'] }}"
                          class="card flex-column" style="width:calc(25% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $total_acreage['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="58">
                <span class="commercialFields">
                  <div class="form-group commercial_hide">
                    @php
                      $garageOption = [
                          ['target' => '.garageOptionYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '.garageOptionNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                          [
                              'target' => '.garageOptionOptional',
                              'name' => 'Optional',
                              'icon' => 'fa-regular fa-check-circle',
                          ],
                      ];
                    @endphp
                    <label class="fw-bold">Garage/Parking Spaces Needed:</label>
                    <select name="garageOption" class="grid-picker" id="garageFeatureOpt"
                      style="justify-content: flex-start;" required>
                      @foreach ($garageOption as $item)
                        <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                          data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);">
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
                    <div class="form-group" id="garageFeatures" style="display: none;">
                      <select class="grid-picker" name="garage_parking[]" style="justify-content: flex-start;"
                        multiple required>
                        <option value="">Select</option>
                        @foreach ($garageParking as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column" style="width:calc(25% - 10px);"
                            data-icon='<i class="fa-solid fa-warehouse"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group other_garage d-none">
                        <label class="fw-bold" for="other_garage">Garage/Parking Features :</label>
                        <input type="text" name="other_garage" class="form-control has-icon hide_arrow"
                          data-icon="fa-solid fa-warehouse" required>
                      </div>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="59">
                <span class="commercialFields">
                  <div class="form-group">
                    @php
                      $preferenceNeed = [
                          ['target' => '.preferenceYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '.preferenceNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                          [
                              'target' => '.preferenceOptional',
                              'name' => 'Optional',
                              'icon' => 'fa-regular fa-check-circle',
                          ],
                      ];
                    @endphp
                    <div class="form-group">
                      <label class="fw-bold">View Preference Needed: </label>
                      <select name="prefrenceOptions" class="grid-picker" id="preferenceOptCommercial"
                        style="justify-content: flex-start;" required>
                        @foreach ($preferenceNeed as $item)
                          <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                            data-target="{{ $item['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);">
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      @php
                        $preferenceOptions = [
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
                            ['name' => 'Other', 'target' => '.other_preference'],
                        ];
                      @endphp
                      <div class="form-group" id="preferenceOptionsCommercial" style="display: none;">
                        <select class="grid-picker" name="prefrence[]" style="justify-content: flex-start;" required
                          multiple>
                          @foreach ($preferenceOptions as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column" style="width:calc(25% - 10px);"
                              data-icon='<i class="fa-regular fa-check-circle"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group other_preference d-none">
                          <label class="fw-bold" for="other_preference">View Preference:</label>
                          <input type="text" name="preferenceOther" class="form-control has-icon hide_arrow"
                            data-icon="fa-solid fa-ruler-combined" data-icon="fa-solid fa-ruler-combined" required>
                        </div>
                      </div>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step showOnlyBusiness" data-step="60">
                @php
                  $purchase_of_business = [
                      ['name' => 'Real Estate Building and Business', 'target' => ''],
                      ['name' => 'Business Only ', 'target' => ''],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Does the purchase of the business need to include both the real estate building
                    and the business, or solely the business? </label>
                  <select class="grid-picker" name="purchase_of_business" id="purchase_of_business"
                    style="justify-content: center;" required>
                    <option value="">Select</option>
                    @foreach ($purchase_of_business as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="wizard-step" data-step="61">
                <span class="commercialFields">
                  @php
                    $yes_or_nos = [
                        ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                  @endphp
                  <div class="form-group">
                    <label class="fw-bold">Are there any non-negotiable amenities or property features that the buyer
                      is
                      seeking?</label>
                    <select class="grid-picker" name="has_non_negotiable_terms" id=""
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($yes_or_nos as $item)
                        @php
                          if ($item['name'] == 'Yes') {
                              $target = '.custom_bathroom_residential_and_income';
                          } else {
                              $target = '';
                          }
                        @endphp
                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  @php
                    $non_negotiable_terms = [
                        ['name' => 'Parking Spaces', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                        ['name' => 'Loading Dock', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Warehouse Space', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Office Space', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Conference Room', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Kitchenette/Break Room', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Restrooms', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Elevator', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Handicap Accessibility', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Security System', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'On-site Maintenance', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'On-site Management', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Outdoor Space/Garden', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Signage Opportunities', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'High-Speed Internet', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Utilities Included', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'HVAC System', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Natural Lighting', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Storage Space', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Open Floor Plan', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Retail Frontage', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Restaurant Space', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Industrial Features', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        [
                            'name' => 'Flexibility for Renovations',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-xmark',
                        ],
                        ['name' => 'Common Areas', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Business Center', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Gym/Fitness Facilities', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Lounge Area', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Reception Areas', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Reception Area', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Security Guard', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Fire Safety Systems', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Energy-Efficient Features', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        [
                            'name' => 'Green Building Certification',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-xmark',
                        ],
                        [
                            'name' => 'Access to Public Transportation',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-xmark',
                        ],
                        ['name' => 'Proximity to Highways', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Visibility from Main Road', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ['name' => 'Other', 'target' => '.custom_non_negotiable_terms'],
                    ];
                  @endphp

                  <div class="form-group custom_bathroom_residential_and_income d-none">
                    <select class="grid-picker" name="non_negotiable_terms[]" id="utilities"
                      style="justify-content: flex-start;" required multiple>
                      <option value="">Select</option>
                      @foreach ($non_negotiable_terms as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                          style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group custom_non_negotiable_terms d-none">
                    <label class="fw-bold" for="custom_non_negotiable_terms">What non-negotiable amenities or
                      property
                      features is the buyer is seeking?</label>
                    <input type="text" name="custom_non_negotiable_terms" id="custom_non_negotiable_terms"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="62">
                <span class="commercialFields">
                  @php
                    $financings = [
                        ['name' => 'Cash', 'target' => '.customCashCommercial'],
                        ['name' => 'Conventional', 'target' => '.customConventional'],
                        ['name' => 'Seller Financing', 'target' => '.custom_seller_financing'],
                        ['name' => 'FHA', 'target' => '.yes_no'],
                        ['name' => 'Jumbo', 'target' => ''],
                        ['name' => 'VA', 'target' => ''],
                        ['name' => 'No-Doc', 'target' => ''],
                        ['name' => 'Non-QM', 'target' => ''],
                        ['name' => 'Assumable', 'target' => '.custom_assumable'],
                        ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade_res'],
                        ['name' => 'Lease Option', 'target' => '.lease_optionCommercial'],
                        ['name' => 'Lease Purchase', 'target' => '.lease_purchaseCommercial'],
                        ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency'],
                        ['name' => 'NFT', 'target' => '.nftCommercial'],
                        ['name' => 'USDA', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_other'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4">
                    <div class="form-group">
                      <label class="fw-bold">Maximum Budget:</label>
                      <input type="text" class="form-control has-icon" name="maxBudget"
                        data-icon="fa-solid fa-dollar-sign" required>
                    </div>
                    <div class="col-md-12">
                      <label class="fw-bold">Please indicate the financing/currency that the buyer will use to
                        purchase
                        the
                        property:</label>
                      <div class="select2-parent">
                        <select name="financings[]" class="grid-picker" multiple id="financingCommercial" required>
                          @foreach ($financings as $financing)
                            <option value="{{ $financing['name'] }}" data-target="{{ $financing['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='<i class="fa-regular fa-circle-check"></i>'>
                              {{ $financing['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>

                    </div>
                  </div>

                  <div class="form-group" id="ConventionalCommercialOpt" style="display: none;">
                    @php
                      $customOptions = [
                          ['target' => '.customOptionsYes', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                          ['target' => '.customOptionsNo', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                      ];
                    @endphp
                    <label class="fw-bold">Has the buyer been pre-approved for a loan? </label>
                    <select name="conventionalOptions[]" class="grid-picker" id=""
                      style="justify-content: flex-start;" required>
                      @foreach ($customOptions as $item)
                        <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                          data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group customOptionsYes d-none">
                      <label class="fw-bold">How much is the buyer pre-approved for?</label>
                      <input type="text" name="financingOptionsConventional[]" data-type="customOptionsYes"
                        id="customOptionsYes" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group customOptionsNo d-none">
                      <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                      <input type="text" name="financingOptionsConventional[]" data-type="customOptionsNoInput"
                        id="customOptionsNoInput" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                      <div class="form-group">
                        <label class="fw-bold">If not pre-approved yet, does the buyer need a lender recommendation?
                        </label>
                        @php
                          $customOptionsYesNo = [
                              ['target' => '', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'],
                              ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                          ];
                        @endphp
                        <select name="customOptionsYesNo" class="grid-picker" id=""
                          style="justify-content: flex-start;" required>
                          @foreach ($customOptionsYesNo as $item)
                            <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                              data-target="{{ $item['target'] }}" class="card flex-row"
                              style="width:calc(33.3% - 10px);">
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                      </div>
                    </div>
                  </div>
                  {{-- Cash --}}
                  <div class="form-group customCashCommercial d-none">
                    <label class="fw-bold" for="customCash">What is the buyer's budget for purchasing a
                      property?</label>
                    <input type="text" name="financingOptionsCash[]" id="customCash"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  {{-- Cash --}}
                  {{-- Lease Option --}}
                  <div class="form-group" id="financingOptionCommercial" style="display: none;">
                    <div class="form-group">
                      <label class="fw-bold">What specific terms is the buyer considering for the lease purchase?
                      </label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What is the proposed duration or term of the lease?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much is the buyer comfortable paying in monthly lease
                        payments?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Is the buyer offering an option fee, and if so, what is the
                        amount?</label>
                      <input type="text" name="financingOptionsLease[]" data-type="lease_option"
                        id="lease_option" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Lease Option --}}
                  {{-- NFT --}}

                  <div class="form-group nftCommercial d-none" id="">
                    <div class="form-group">
                      <label class="fw-bold">What type of NFT is the buyer offering?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will the buyer offer as an
                        NFT?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will the buyer offer in
                        cash?</label>
                      <input type="text" name="financingOptionsNft[]" data-type="nft" id="nft"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- NFT --}}

                  {{-- seller financing --}}
                  <div class="form-group custom_seller_financing d-none" id="custom_seller_financing">
                    <div class="form-group">
                      <label class="fw-bold">What terms is the buyer offering to the seller?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What down payment is the buyer offering the seller?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What interest rate is the buyer proposing?</label>
                      <input type="text" name="financingOptionsSeller[]" data-type="custom_seller_financing"
                        id="custom_seller_financing" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- seller financing --}}
                  {{-- Exchange Trade --}}
                  <div class="form-group custom_exchange_trade_res d-none">
                    <div class="form-group">
                      <label class="fw-bold">What item would the buyer like to Exchange/Trade?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much is the buyer’s Exchange/Trade item valued at?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much cash will the buyer use on top of the Exchange/Trade
                        item?</label>
                      <input type="text" name="financingOptionsTrade[]" data-type="custom_exchange_trade_res"
                        id="custom_exchange_trade_res" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Exchange Trade --}}
                  {{-- Assemble --}}
                  {{-- <div class="form-group custom_assumable d-none">
                    <label class="fw-bold">What is the buyer's budget for purchasing a property?</label>
                    <input type="text" name="financingOptionsAssumble[]" data-type="custom_assumable"
                      id="custom_assumable" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      data-msg-required="Please Enter Type of Financing">
                  </div> --}}
                  {{-- Assemble --}}
                  {{-- Cryptocurrency --}}
                  <div class="form-group custom_cryptocurrency d-none">
                    <div class="form-group">
                      <label class="fw-bold">What type of Cryptocurrency does the buyer have?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrency"
                        id="custom_cryptocurrency" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will be used with
                        Cryptocurrency?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrency"
                        id="custom_cryptocurrency" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What percentage of the purchase price will be used with Cash?</label>
                      <input type="text" name="financingOptionsCrypto[]" data-type="custom_cryptocurrency"
                        id="custom_cryptocurrency" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="Please Enter Type of Financing">
                    </div>
                    <label class="">Note: Cryptocurrency can be converted to cash at closing.</label>
                  </div>
                  {{-- Cryptocurrency --}}
                  {{-- Other --}}
                  <div class="form-group custom_other d-none">
                    <div class="form-group">
                      <label class="fw-bold">What type of financing/funds will the buyer use to purchase the
                        property?</label>
                      <input type="text" name="financingOther[]" data-type="custom_other" id="custom_other"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        data-msg-required="Please Enter Type of Financing">
                    </div>
                  </div>
                  {{-- Other --}}
                </span>
              </div>
              <div class="wizard-step" data-step="63">
                <span class="commercialFields">
                  <div class="form-group">
                    <label class="fw-bold">When will the buyer be ready to purchase a property?</label>
                    @php
                      $timeframes = [
                          ['name' => 'ASAP', 'target' => ''],
                          ['name' => '1 Month', 'target' => ''],
                          ['name' => '2 Months', 'target' => ''],
                          ['name' => '3 Months', 'target' => ''],
                          ['name' => '4 Months', 'target' => ''],
                          ['name' => '5 Months', 'target' => ''],
                          ['name' => '6 Months', 'target' => ''],
                          ['name' => 'Over 6 Months', 'target' => ''],
                          // ['name' => 'Other', 'target' => '.custom_amount1']
                      ];
                    @endphp
                    <select class="grid-picker" name="buying_timeframe" id="buying_timeframe"
                      style="justify-content: flex-start;" onchange="buyerPrompt(this.value);" required>
                      <option value="">Select</option>
                      @foreach ($timeframes as $item)
                        @php
                          if ($item['name'] == 'Other') {
                              $target = '.custom_amount12';
                          } else {
                              $target = '';
                          }
                        @endphp
                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>

                  </div>
                  <div class="form-group custom_amount12 d-none">
                    <label class="fw-bold">When will the buyer be ready to purchase a property? </label>
                    <input type="text" class="form-control has-icon" name="custom_buyer_looking_to_purchase"
                      data-icon="fa-regular fa-check-circle" id="custom_amount" required />
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="64">
                <span class="commercialFields">
                  <div class="form-group">
                    <label class="fw-bold">
                      What is the timeframe offered to the agent in the Buyer Agency Agreement?
                    </label>
                    @php
                      $buyer_prefered_timeframe = [
                          ['name' => '1 Month', 'target' => ''],
                          ['name' => '2 Months', 'target' => ''],
                          ['name' => '3 Months', 'target' => ''],
                          ['name' => '4 Months', 'target' => ''],
                          ['name' => '5 Months', 'target' => ''],
                          ['name' => '6 Months', 'target' => ''],
                          ['name' => 'Over 6 Months', 'target' => ''],
                          ['name' => 'Negotiable', 'target' => ''],
                          ['name' => 'Other', 'target' => ''],
                      ];
                    @endphp
                    <select class="grid-picker" name="timeframe" id="term_financings"
                      style="justify-content: flex-start;" required onchange="showPrompt(this.value);">
                      <option value="">Select</option>
                      @foreach ($buyer_prefered_timeframe as $item)
                        @php
                          if ($item['name'] == 'Other') {
                              $target = '.custom_buyer_prefered_timeframes';
                          } else {
                              $target = '';
                          }
                        @endphp
                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group custom_buyer_prefered_timeframes d-none">
                    <label class="fw-bold"> What is the timeframe offered to the agent in the Buyer Agency
                      Agreement?</label>
                    <input type="text" class="form-control has-icon" placeholder="" name="other_timeframe"
                      data-icon="fa-solid fa-ruler-combined" id="custom_amount" required />
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="65">
                <span class="commercialFields">
                  <label class="fw-bold">
                    In cases where a property is For Sale By Owner or a Buyer’s agent commission is not offered, how will
                    the Buyer be able to pay their agent's commission?
                  </label>
                  <?php
                  $commissionCommercial = [['target' => '', 'name' => "The Buyer will ask the agent to include their agent's commission as a part of the offer to the Seller."], ['target' => '', 'name' => "The Buyer will be able to pay their agent's commission out of pocket."], ['target' => '', 'name' => 'The Buyer will not allow any compensation that is not already offered.'], ['target' => '.OtherCommission', 'name' => 'Other']];
                  ?>
                  <select class="grid-picker" name="commission" id="commissionOptCommercial"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($commissionCommercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group" id="commissionCommercialOptional" style="display:none;">
                    <label class="fw-bold">How much will the Buyer compensate their agent?</label>
                    <?php
                    $commissionOpt = [['target' => '', 'name' => '2%'], ['target' => '', 'name' => '2.5%'], ['target' => '', 'name' => '3%'], ['target' => '', 'name' => 'Negotiable'], ['target' => '.otherComComercialOpt', 'name' => 'Other']];
                    ?>
                    <select class="grid-picker" name="commissionOpt" style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($commissionOpt as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group otherComComercialOpt d-none">
                      <label class="fw-bold">How much will the Buyer compensate their agent?</label>
                      <input type="text" name="OtherComOptions" class="form-control has-icon col-3"
                        data-icon="fa-solid fa-ruler-combined" data-msg-required="" required>
                    </div>
                  </div>
                  <div class="form-group OtherCommission d-none">
                    <label class="fw-bold">How will the Buyer be able to pay their agent's commission?</label>
                    <input type="text" name="OtherCommission" class="form-control has-icon col-3"
                      data-icon="fa-solid fa-ruler-combined" data-msg-required="" required>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="66">
                <span class="commercialFields">
                  <label class="fw-bold">Would the buyer like to request that their hired agent offer a concession to
                    assist with the buyer's closing costs from the agent's commission?
                  </label>
                  <?php
                  $concession = [['target' => '', 'name' => 'Yes', 'icon' => 'fa-regular fa-check-circle'], ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'], ['target' => '.concessionOptional', 'name' => 'Optional', 'icon' => 'fa-regular fa-check-circle']];
                  ?>
                  <select class="grid-picker" name="concession" id=""
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($concession as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group">
                    <small>(Available in all the states except: Alabama, Alaska, Kansas, Mississippi,
                      Missouri,
                      Oklahoma, Oregon, and Tennessee. This list is subject to change.) </small>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="67">
                <span class="commercialFields">
                  <div class="form-group">
                    <label class="fw-bold">
                      What are the most important aspects the buyer will consider when hiring a real estate agent?
                    </label>
                    <textarea name="aspect_hiring_agent" class="form-control" rows="5">{{ old('aspect_hiring_agent') }}</textarea>
                  </div>
                  <div class=" form-group">
                    <label class="fw-bold">What additional details would the buyer like to share with the
                      agent? </label>
                    <textarea name="additional_details" class="form-control" rows="5">{{ old('additional_details') }}</textarea>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="68">
                @php
                  $services_data = [
                      [
                          'name' =>
                              'Assist the buyer in obtaining pre-approval or exploring financing options to determine their purchasing power.',
                          'target' => '',
                      ],
                      ['name' => 'List the Buyer’s Criteria Listing on the platform BidYourOffer.com.', 'target' => ''],
                      [
                          'name' =>
                              "Market the buyer's listing on numerous groups, pages, and affiliates through a listing link or QR code that links directly to the listing on BidYourOffer.com.",
                          'target' => '',
                      ],
                      [
                          'name' =>
                              "Promote the buyer's listing on social media platforms using a listing link or QR code that directs to the buyer's listing on the BidYourOffer.com platform.",
                          'target' => '',
                      ],
                      [
                          'name' =>
                              "Send prompt email notifications containing properties that meet the buyer's criteria as soon as they are listed, ensuring access to the most up-to-date listings.",
                          'target' => '',
                      ],
                      ['name' => 'Schedule and accompany the buyer on property viewings and showings.', 'target' => ''],
                      ['name' => 'Schedule video tours of the property as needed.', 'target' => ''],
                      [
                          'name' =>
                              'Provide detailed market analysis and comparative market information to help the buyer make informed decisions.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist the buyer in negotiations and in preparing offers to maximize their chances of securing the desired property.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist the buyer with the review and explanation of all contractual documents and disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Coordinate and oversee the home inspection process, including making recommendations for trusted inspectors.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Facilitate communication and coordination with other professionals involved in the transaction, such as lenders, attorneys, and title companies.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide guidance and support throughout the entire purchase transaction, from offer acceptance to closing.',
                          'target' => '',
                      ],
                      ['target' => '.custom_service_vancat', 'name' => 'Other-Add additional services as needed.'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Select the services that the buyer requests from an agent:</label>
                  <select class="grid-picker" name="services[]" onchange="service_data(this.value)" id="services"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($services_data as $service)
                      <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}"
                        class="card flex-row" style="width:calc(100% - 0px);"
                        data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                        {{ $service['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_service_vancat d-none">
                  <label class="fw-bold">What additional services would the buyer like to request from an agent?</label>
                  <input type="text" class="form-control has-icon" name="custom_service_buyer_want_vancat"
                    data-icon="fa-solid fa-ruler-combined" id="custom_service_vancat" required />
                </div>
              </div>
              <div class="wizard-step" data-step="69">
                <span class="commercialFields">
                  <div class="row form-group">
                    <div class="col-md-6">
                      <label class="fw-bold">First Name:</label>
                      <input type="text" class="form-control has-icon" name="first_name" id="first_name"
                        value="{{ auth()->user()->first_name }}" data-icon="fa-solid fa-user" />
                    </div>
                    <div class="col-md-6">
                      <label class="fw-bold">Last Name:</label>
                      <input type="text" class="form-control has-icon" name="last_name" id="last_name"
                        value="{{ auth()->user()->last_name }}" data-icon="fa-solid fa-user" />
                    </div>
                  </div>
                  <div class="row form-group">
                    <div class="col-md-6">
                      <label class="fw-bold">Phone Number:</label>
                      <input type="text" class="form-control has-icon" name="phone" id="phone"
                        value="{{ auth()->user()->phone }}" data-icon="fa-solid fa-phone" />
                    </div>
                    <div class="col-md-6">
                      <label class="fw-bold">Email:</label>
                      <input type="email" class="form-control has-icon" name="email" id="email"
                        value="{{ auth()->user()->email }}" data-icon="fa-solid fa-envelope" />
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-6">
                      <label class="fw-bold mt-1">Video:</label>
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
                    <div class="col-6">
                      <div class="upload form-group">
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
                </span>
              </div>
              <div class="d-flex justify-content-between form-group" style="margin-top:78px;">
                <div>
                  <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                </div>
                <div>
                  <button type="button" class="wizard-step-next btn btn-success btn-lg text-600"
                    style="display: none;">Next</button>
                  <button type="button" id="saveBtn" class="wizard-step-finish btn btn-success btn-lg text-600"
                    style="display: none;">Save</button>
                </div>
              </div>
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>
  <template class="city_temp">
    <tr>
      <td>
        <div class="input-cover input-cover-0">
          <i class="fa-solid fa-city " style="margin: 9px; position:absolute; font-size:30px;"></i>
          <input type="text" name="cities[]" placeholder="City" data-type="cities"
            class="form-control has-icon search_places" data-icon="fa-solid fa-city"
            data-msg-required="Please enter city">
        </div>
      </td>
    </tr>
  </template>
  <template class="county_temp">
    <tr>
      <td>
        <div class="input-cover input-cover-0">
          <i class="fa-solid fa-tree-city" style="margin: 9px; position:absolute; font-size:30px;"></i>
          <input type="text" name="counties[]" placeholder="County" data-type="counties"
            class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
            data-msg-required="Please enter city">
        </div>
      </td>
    </tr>
  </template>

  <template class="state_temp">
    <tr>
      <td><input type="text" name="state[]" placeholder="State" data-type="state"
          class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
          data-msg-required="Please enter state"></td>
    </tr>
  </template>
@endsection
@push('scripts')
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
        $('#errorDiv').remove();
        if (this.files[0].size > 10000000) {
          $(this).parent().after('<span id="errorDiv" style="color: red;">Please upload a file less than 10MB. Thanks!!</span>');
          $(this).val('');
          $('#saveBtn').prop('disabled', true);
        } else {
          $('#saveBtn').prop('disabled', false);
          $('#errorDiv').remove();

        }
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
  </script>
  <script>
    function changeAuctionType(v) {
      if (v == "Auction (Timer)") {
        $('.auction_length').val("");
        $('.auction_length').parent().children('.option-container').removeClass('active');
        $('.traditional-length').hide();
        $('.normal-length').show();
        $('.auction_length_cover').removeClass('d-none');

      } else {
        $('.auction_length').val("");
        $('.auction_length').parent().children('.option-container').removeClass('active');
        $('.traditional-length').show();
        $('.normal-length').hide();
        $('.auction_length_cover').addClass('d-none');
      }
    }
    // document.getElementById('auction_type').change();

    changeAuctionType();
  </script>
  <script>
    $('.exchange_trade_for').on('change', function() {
      var items = $(this).val();
      if (items == "Another home") {
        $('.custom_trade').addClass('d-none');

        $('.item_values').removeClass('d-none');
      } else if (items == "Vehicles") {
        $('.custom_trade').addClass('d-none');
        $('.item_values').removeClass('d-none');
      } else if (items == "Boats") {
        $('.custom_trade').addClass('d-none');
        $('.item_values').removeClass('d-none');
      } else if (items == "Motorhomes") {
        $('.custom_trade').addClass('d-none');
        $('.item_values').removeClass('d-none');
      } else if (items == "Artwork") {
        $('.custom_trade').addClass('d-none');
        $('.item_values').removeClass('d-none');
      } else if (items == "Jewelry") {
        $('.custom_trade').addClass('d-none');
        $('.item_values').removeClass('d-none');
      } else if (items == "Other") {
        $('.item_values').addClass('d-none');
        $('.custom_trade').removeClass('d-none');
      }
    });
    // function exchange_trade_for(p) {
    //     var selectedValue = selectElement.value;
    //     var selectedOption = selectElement.options[selectElement.selectedIndex];
    //     var target = selectedOption.getAttribute('data-target');

    //     alert('Selected Value: ' + selectedValue + '\nTarget: ' + target);
    // }
    // $(function() {
    //     exchange_trade_for("");
    //     });
  </script>
  <script>
    function changePropertyType(p) {
      if (p == "Residential Property") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').show();
        $('.income-length').hide();
        $('.commercial-length').hide();
        $('.business_type_next').addClass('d-none');
        $('.business_type_next_hide').removeClass('d-none');
        $('.hide_vacant').addClass('d-none');
        $('.road_frontage_next_hide').removeClass('d-none');
        $('.residential_and_income_hide').addClass('d-none');
        $('.residential_and_income').removeClass('d-none');
        $('.for_income_only').addClass('d-none');
        $('.petYes').remove();
        $('.for_residential_only').removeClass('d-none');
        $('.residential_hide').removeClass('d-none');
        $('.business-length').hide();
        $('.businessTitle').addClass('d-none');
        $('.vacantTitle').addClass('d-none');
        $('.resFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
        });
        $('.commercialFields, .incomeFields, .vacantFields').each(function() {
          $(this).find('select, input, textarea').each(function() {
            $(this).prop('disabled', true);
          });
        });

      } else if (p == "Income Property") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').show();
        $('.commercial-length').hide();
        $('.residential_hide').removeClass('d-none');
        $('.residential_remove').remove();
        $('.vacant_land-length').hide();
        $('.business-length').hide();
        $('.petYesRes').remove();
        $('.businessTitle').addClass('d-none');
        $('.vacantTitle').addClass('d-none');
        $('.incomeFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
        });
        $('.commercialFields, .resFields, .vacantFields').each(function() {
          $(this).find('select, input, textarea').each(function() {
            $(this).prop('disabled', true);
          });
        });

      } else if (p == "Commercial Property") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').show();
        $('.business_type_next').removeClass('d-none');
        $('.business_type_next_hide').addClass('d-none');
        $('.residential_and_income').addClass('d-none');
        $('.residential_hide').addClass('d-none');
        $('.vacant_land-length').hide();
        $('.business-length').hide();
        $('.businessTitle').addClass('d-none');
        $('.vacantTitle').addClass('d-none');
        $('.commercialFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
        });
        $('.incomeFields, .resFields, .vacantFields').each(function() {
          $(this).find('select, input, textarea').each(function() {
            $(this).prop('disabled', true);
          });
        });

      } else if (p == "Vacant Land") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').hide();
        $('.business_type_next').removeClass('d-none');
        $('.business_type_next_hide').addClass('d-none');
        $('.residential_and_income').addClass('d-none');
        $('.residential_hide').addClass('d-none');
        $('.vacant_land-length').show();
        $('.business-length').hide();
        $('.businessTitle').addClass('d-none');
        $('.vacantTitle').removeClass('d-none');
        $('.vacantFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
        });
        $('.incomeFields, .resFields, .commercialFields').each(function() {
          $(this).find('select, input, textarea').each(function() {
            $(this).prop('disabled', true);
          });
        });

      } else if (p == "Business Opportunity") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').hide();
        $('.business_type_next').removeClass('d-none');
        $('.business_type_next_hide').addClass('d-none');
        $('.residential_and_income').addClass('d-none');
        $('.residential_hide').addClass('d-none');
        $('.vacant_land-length').hide();
        $('.business-length').show();
        $('.businessTitle').removeClass('d-none');
        $('.vacantTitle').addClass('d-none');
        $('.commercialFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
        });
        $('.incomeFields, .resFields, .vacantFields').each(function() {
          $(this).find('select, input, textarea').each(function() {
            $(this).prop('disabled', true);
          });
        });
      } else {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').hide();
        $('.business_type_next').addClass('d-none');
        $('.business_type_next_hide').removeClass('d-none');
        $('.hide_vacant').addClass('d-none');
        $('.road_frontage_next_hide').removeClass('d-none');
        $('.vacant_land-length').hide();
        $('.business-length').hide();
        $('.businessTitle').addClass('d-none');
        $('.vacantTitle').addClass('d-none');

      }
    }
    // document.getElementById('auction_type').change();
    $(function() {
      changePropertyType("");
    });
  </script>
  <script>
    function changePropertyStyle(p) {
      if (p == "Vacant Land") {
        // alert('ok');
        $('.property_style_next_hide').addClass('d-none');
        $('.road_frontage_next_hide').addClass('d-none');
        $('.business_opportunity_show').addClass('d-none');
        $('.vacant_land_show').removeClass('d-none');
        $('.hide_vacant').removeClass('d-none');
        $('.show_vacant').removeClass('d-none');
        $('.vacant_land_hide').remove();
        // $('.remove_business_opportunity').remove();
        $('.business_opportunity_remove').show();

      } else {
        $('.vacant_land_show').addClass('d-none');
        $('.hide_vacant').addClass('d-none');
        $('.road_frontage_next_hide').removeClass('d-none');
        $('.business_opportunity_show').removeClass('d-none');
        $('#remove_pets_question').remove();
        // $('.vacant_land_remove').remove();
        $('.show_vacant').addClass('d-none');
      }
    }
    // document.getElementById('auction_type').change();
    $(function() {
      changePropertyStyle("");
    });
  </script>
  <script>
    function buyer_under_contract(w) {
      if (w == "Yes") {
        $('.has_assignment').removeClass('d-none');
        $('.has_no_assignment').addClass('d-none');
      } else if (w == "No") {
        $('.has_no_assignment').removeClass('d-none');
        $('.has_assignment').addClass('d-none');
      } else {
        $('.has_no_assignment').addClass('d-none');
        $('.has_assignment').addClass('d-none');
      }
    }

    function service_data(w) {
      if (w == "Other- Add additional services as needed") {
        $('#custom_service_buyer_want_income').removeClass('d-none');
        $('#custom_service_buyer_want').removeClass('d-none');
        $('#custom_service_buyer_want_commercial_and_business').removeClass('d-none');
        $('#custom_service_buyer_want_vacant').removeClass('d-none');
      } else {
        $('#custom_service_buyer_want_income').addClass('d-none');
        $('#custom_service_buyer_want').addClass('d-none');
        $('#custom_service_buyer_want_commercial_and_business').addClass('d-none');
        $('#custom_service_buyer_want_vacant').addClass('d-none');

      }
    }

    function change_sale_provision(w) {
      if (w == "Other") {
        $('#other_sale_provision').removeClass('d-none');
        $('#other_sale_provision1').removeClass('d-none');
        $('#other_sale_provision_commercial_and_business').removeClass('d-none');
        $('#other_sale_provision_vacant_land').removeClass('d-none');
      } else {
        $('#other_sale_provision').addClass('d-none');
        $('#other_sale_provision1').addClass('d-none');
        $('#other_sale_provision_commercial_and_business').addClass('d-none');
        $('#other_sale_provision_vacant_land').addClass('d-none');

      }
    }

    function buyerPrompt(w) {
      if (w == "4 Months" || w == "5 Months" || w == "6 Months" || w == "Over 6 Months" || w == "" || w ==
        'Not looking to sell') {
        $('.buying_timeframe_prompt').removeClass('d-none');
        $('#custom_timeframe_residential').addClass('d-none');
        $('#custom_timeframe_income').addClass('d-none');
        $('#custom_timeframe_commercial_and_business').addClass('d-none');
        $('#custom_timeframe_vacant_land').addClass('d-none');

      } else if (w == "Other") {
        $('#custom_timeframe_residential').removeClass('d-none');
        $('#custom_timeframe_income').removeClass('d-none');
        $('#custom_timeframe_commercial_and_business').removeClass('d-none');
        $('#custom_timeframe_vacant_land').removeClass('d-none');
        $('.buying_timeframe_prompt').addClass('d-none');
      } else {
        $('#custom_timeframe_residential').addClass('d-none');
        $('#custom_timeframe_income').addClass('d-none');
        $('#custom_timeframe_vacant_land').addClass('d-none');
        $('#custom_timeframe_commercial_and_business').addClass('d-none');
        $('.buying_timeframe_prompt').addClass('d-none');
      }
    }

    function showPrompt(w) {
      if (w == "4 Months" || w == "5 Months" || w == "6 Months" || w == "Over 6 Months" || w == "" || w ==
        'Not looking to sell') {
        $('.prompt_response').removeClass('d-none');
        $('#custom_timeframe_residential').addClass('d-none');
        $('#custom_timeframe_income').addClass('d-none');
        $('#custom_timeframe_commercial_and_business').addClass('d-none');
        $('#custom_timeframe_vacant_land').addClass('d-none');

      } else if (w == "Other") {
        $('#custom_timeframe_residential').removeClass('d-none');
        $('#custom_timeframe_income').removeClass('d-none');
        $('#custom_timeframe_commercial_and_business').removeClass('d-none');
        $('#custom_timeframe_vacant_land').removeClass('d-none');
        $('.prompt_response').addClass('d-none');
      } else {
        $('#custom_timeframe_residential').addClass('d-none');
        $('#custom_timeframe_income').addClass('d-none');
        $('#custom_timeframe_vacant_land').addClass('d-none');
        $('#custom_timeframe_commercial_and_business').addClass('d-none');
        $('.prompt_response').addClass('d-none');
      }
    }

    function changeWaterExtras(w) {
      if (w == "Yes" || w == "Optional") {
        $('.water_extras_residential_and_income').show();
      } else {
        $('.water_extras_residential_and_income').hide();
      }
    }

    function changeWaterFrontage(w) {
      if (w == "Yes" || w == "Optional") {
        $('.water_frontage_residential_and_income').show();
      } else {
        $('.water_frontage_residential_and_income').hide();
      }
    }

    function changeViewPrefrence(w) {
      if (w == "Yes" || w == "Optional") {
        $('.water_view_preference_residential_and_income').show();
      } else {
        $('.water_view_preference_residential_and_income').hide();
      }
    }
    $(function() {
      changeWaterView("");
      changeWaterAccess("");
      changeWaterExtras("");
      changeWaterFrontage("");
      changeViewPrefrence("");
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

      //   if (v.includes('Assembly Building') || v.includes('Agriculture') || v.includes('Retail') || v.includes(
      //       'Warehouse') || v.includes('Restaurant') || v.includes('Office') || v.includes('Industrial') || v.includes(
      //       'Mixed Use') || v.includes('Hotel/Motel') || v.includes('Five or More (Commercial units)')) {
      //     $('.businesstypes').show();
      //     $('#businesstypes').parent().show();
      //     $('.business-length').hide();
      //     // alert('show')
      //   } else {
      //     $('.businesstypes').hide()
      //     $('#businesstypes').hide();

      //     // alert('hide')

      //   }
      check_custom();
    }

    function check_custom() {
      $('.option-container').each(function(i, elm) {
        var target = $(elm).data('target') || "";
        var is_active = $(elm).hasClass('active');
        if (target != "") {
          //   console.log("is_active", is_active, target);
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
          // alert($('.wizard-step.active').next().is('.wizard-step'));
          if (v.form()) {
            if ($('.wizard-step.active').next().is('.wizard-step')) {

              // $('.wizard-step.active').removeClass('active').next().addClass('active');
              $('.wizard-step.active').removeClass('active');

              if (StepWizard.currentStep == 6 && property_type ==
                'Income Property') {
                StepWizard.nextStep = 26;
                StepWizard.backStep = 6;
              } else if (StepWizard.currentStep == 6 && property_type ==
                'Residential Property') {
                StepWizard.nextStep = 7;
                StepWizard.backStep = 6;
              } else if (StepWizard.currentStep == 6 && (property_type ==
                  'Commercial Property' || property_type ==
                  'Business Opportunity')) {
                StepWizard.nextStep = 53;
                StepWizard.backStep = 6;
              } else if (
                StepWizard.currentStep == 59 && (property_type == 'Commercial Property')
              ) {
                StepWizard.nextStep = 61;
                StepWizard.backStep = 59;
              } else if (StepWizard.currentStep == 6 && property_type ==
                'Vacant Land'
              ) {
                StepWizard.nextStep = 42;
                StepWizard.backStep = 6;
              } else {
                StepWizard.backStep = StepWizard.currentStep;
              }
              console.log(StepWizard.currentStep)
              $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
              StepWizard.setStep();
              if (
                StepWizard.currentStep == 25 &&
                property_type == 'Residential Property'
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if (
                StepWizard.currentStep == 41 &&
                property_type == 'Income Property'
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if (
                StepWizard.currentStep == 69 &&
                (property_type == 'Commercial Property' || property_type ==
                  'Business Opportunity')
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if (
                StepWizard.currentStep == 52 &&
                property_type == 'Vacant Land'
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
            console.log(StepWizard.currentStep)
            StepWizard.setStep();
            if (StepWizard.currentStep == 26 && property_type ==
              'Income Property') {
              StepWizard.backStep = 6;
            } else if (StepWizard.currentStep == 53 && property_type ==
              'Business Opportunity') {
              StepWizard.backStep = 6;
            } else if (StepWizard.currentStep == 53 && property_type ==
              'Commercial Property') {
              StepWizard.backStep = 6;
            } else if (StepWizard.currentStep == 7 && property_type ==
              'Residential Property') {
              StepWizard.backStep = 6;
            } else if (StepWizard.currentStep == 61 && (property_type == 'Commercial Property')) {
              StepWizard.backStep = 59;
            } else if (StepWizard.currentStep == 42 && property_type == 'Vacant Land') {
              StepWizard.backStep = 6;
            } else {
              StepWizard.backStep = StepWizard.currentStep - 1;
            }
          }
        });
        // Assuming the code provided is within a function or a document.ready block

        $('.wizard-step-finish').click(function(e) {
          // if (property_type === 'Residential Property') {
          //   var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
          //     return parseInt($(this).attr('data-step')) >= 20 && parseInt($(this)
          //       .attr('data-step')) <= 51;
          //   });
          //   $stepsToRemove.each(function() {
          //     $(this).closest('div[data-step]').remove();
          //   });
          // }
          //   if (property_type === 'Income Property') {
          //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
          //       var stepValue = parseInt($(this).attr('data-step'));
          //       return (stepValue >= 7 && stepValue <= 19) || (stepValue >= 29 &&
          //         stepValue <= 51);
          //     });
          //     $stepsToRemove.each(function() {
          //       $(this).closest('div[data-step]').remove();
          //     });
          //   }
          //   if (property_type === 'Commercial Property' || property_type ===
          //     'Business Opportunity') {
          //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
          //       var stepValue = parseInt($(this).attr('data-step'));
          //       return (stepValue >= 7 && stepValue <= 28) || (stepValue >= 42 &&
          //         stepValue <= 56);
          //     });

          //     $stepsToRemove.each(function() {
          //       $(this).closest('div[data-step]').remove();
          //     });
          //   }
          //   if (property_type === 'Vacant Land') {
          //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
          //       return parseInt($(this).attr('data-step')) >= 7 && parseInt($(this)
          //         .attr('data-step')) <= 41;
          //     });
          //     $stepsToRemove.each(function() {
          //       $(this).closest('div[data-step]').remove();
          //     });
          //   }
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

        if (StepWizard.currentStep >= 6 && StepWizard.currentStep <= 25) {
          // Calculate progress for Residential steps (7 to 19)
          comp = 20 + ((StepWizard.currentStep - 6) / (25 - 6)) * 80;
        } else if (StepWizard.currentStep >= 6 && StepWizard.currentStep <= 41) {
          comp = (20 + ((StepWizard.currentStep - 19) / (41 - 19)) * 80)
          // Calculate progress for Income steps (20 to 28)
        } else if (StepWizard.currentStep >= 53 && StepWizard.currentStep <= 69) {
          // if(StepWizard.currentStep == 47){}
          // Calculate progress for Commercial and Business opportunity steps (29 to 41)
          comp = 20 + ((StepWizard.currentStep - 53) / (69 - 53)) * 80;
        } else if (StepWizard.currentStep >= 42 && StepWizard.currentStep <= 53) {
          // Calculate progress for Vacant land steps (42 to 51)
          comp = 20 + ((StepWizard.currentStep - 42) / (53 - 42)) * 80;
        } else {
          // Default progress calculation for other steps
          comp = ((StepWizard.currentStep - 1) / 25) * 100;
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
    $(document).on('change', '#need_water_view', function() {
      var selectedValue = $(this).val();
      if (selectedValue == 'No') {
        $('#prefer_water_view').hide();
        $('#extra_water').hide();
      }
      if (selectedValue == 'No') {
        $('#prefer_water_view').hide();
        $('#extra_water').hide();

      }
      if (selectedValue == 'Yes') {
        $('#prefer_water_view').show();
        $('#extra_water').show();

      }
    });
  </script>

  <script>
    // For  Residential Property
    var toggleDiv = $('#custom_lease_option');
    var toggleMulti = $('#customConventional');
    $('#financingOptions').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) {
        toggleDiv.show();
      } else {
        toggleDiv.hide();
      }
      if ($(this).val().includes('FHA') || $(this).val().includes('Conventional') || $(this).val().includes('VA') ||
        $(this).val().includes('USDA') || $(this).val().includes('Non-QM') || $(this).val().includes('Jumbo') || $(
          this).val().includes('No-Doc')) {
        toggleMulti.show();
      } else {
        toggleMulti.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // For  Residential Property


    // For Residential property
    var toggleMultiRes = $('#financingOptionRes');
    var toggleMultiConventional = $('#conventionalRes');
    $('#financingRes').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) {
        toggleMultiRes.show();
      } else {
        toggleMultiRes.hide();
      }
      if ($(this).val().includes('FHA') || $(this).val().includes('Conventional') || $(this).val().includes('VA') ||
        $(this).val().includes('USDA') || $(this).val().includes('Non-QM') || $(this).val().includes('Jumbo') || $(
          this).val().includes('No-Doc')) {
        toggleMultiConventional.show();
      } else {
        toggleMultiConventional.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // For Residential property
    // For Income property
    var toggleMultiIncome = $('#leaseOptionIncome');
    var toggleConventionalIncome = $('#conventionalIncome');

    $('#financingIncome').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) {
        toggleMultiIncome.show();
      } else {
        toggleMultiIncome.hide();
      }
      if ($(this).val().includes('FHA') || $(this).val().includes('Conventional') || $(this).val().includes('VA') ||
        $(this).val().includes('USDA') || $(this).val().includes('Non-QM') || $(this).val().includes('Jumbo') || $(
          this).val().includes('No-Doc')) {
        toggleConventionalIncome.show();
      } else {
        toggleConventionalIncome.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // For Income property
    // For Vacant property
    var toggleMultiVacant = $('#financingOptionVacant');
    var toggleConventionalVacant = $('#conventionalVacant');
    $('#financingVacant').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) {
        toggleMultiVacant.show();
      } else {
        toggleMultiVacant.hide();
      }
      if ($(this).val().includes('FHA') || $(this).val().includes('Conventional') || $(this).val().includes('VA') ||
        $(this).val().includes('USDA') || $(this).val().includes('Non-QM') || $(this).val().includes('Jumbo') || $(
          this).val().includes('No-Doc')) {
        toggleConventionalVacant.show();
      } else {
        toggleConventionalVacant.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // For Vacant property
    // For Commercial property
    var toggleDivCommercial = $('#custom_lease_option_commercial');
    var toggleMultiCommercial = $('#customConventional');
    $('#customConventionalCommercial').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) {
        toggleDivCommercial.show();
      } else {
        toggleDivCommercial.hide();
      }
      if ($(this).val().includes('FHA') || $(this).val().includes('Conventional') || $(this).val().includes('VA') ||
        $(this).val().includes('USDA') || $(this).val().includes('Non-QM') || $(this).val().includes('Jumbo') || $(
          this).val().includes('No-Doc')) {
        toggleMultiCommercial.show();
      } else {
        toggleMultiCommercial.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // For Commercial property
    // For Commercial property
    var toggleDivCommercial = $('#financingOptionCommercial');
    var toggleCommercial = $('#ConventionalCommercialOpt');
    $('#financingCommercial').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) {
        toggleDivCommercial.show();
      } else {
        toggleDivCommercial.hide();
      }
      if ($(this).val().includes('FHA') || $(this).val().includes('Conventional') || $(this).val().includes('VA') ||
        $(this).val().includes('USDA') || $(this).val().includes('Non-QM') || $(this).val().includes('Jumbo') || $(
          this).val().includes('No-Doc')) {
        toggleCommercial.show();
      } else {
        toggleCommercial.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // For Commercial property

    // For Commercial property
    var toggleGarage = $('#garageParking');
    $('#garage').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes')) {
        toggleGarage.show();
      } else {
        toggleGarage.hide();
      }
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // For Commercial property


    // CarportRes
    var toggleCarportRes = $('#carportResCustom');
    $('#carportOptRes').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes')) {
        toggleCarportRes.show();
      } else {
        toggleCarportRes.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // GarageRes
    var toggleGarageRes = $('#garageResCustom');
    $('#garageOpt').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes')) {
        toggleGarageRes.show();
      } else {
        toggleGarageRes.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // CarportIncome
    var toggleCarportIncome = $('#carportIncomeCustom');
    $('#carportOptIncome').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes')) {
        toggleCarportIncome.show();
      } else {
        toggleCarportIncome.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // GarageIncome
    var toggleGarageIncome = $('#garageIncomeCustom');
    $('#garageOptIncome').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes')) {
        toggleGarageIncome.show();
      } else {
        toggleGarageIncome.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // Pool
    var togglePool = $('#poolCustom');
    $('#poolOpt').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes')) {
        togglePool.show();
      } else {
        togglePool.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // Pool Income
    var togglePoolIncome = $('#poolCustomIncome');
    $('#poolOptIncome').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes')) {
        togglePoolIncome.show();
      } else {
        togglePoolIncome.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });

    // Garage/Parking
    var toggleGarageFeature = $('#garageFeatures');
    $('#garageFeatureOpt').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes')) {
        toggleGarageFeature.show();
      } else {
        toggleGarageFeature.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // View Preference Needed?
    var togglepreferenceNeed = $('#preferenceNeed');
    $('#preferenceOpt').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
        togglepreferenceNeed.show();
      } else {
        togglepreferenceNeed.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // View Preference Needed: Income
    var togglepreferenceIncome = $('#preferenceOptionIncome');
    $('#preferenceNeedIncome').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
        togglepreferenceIncome.show();
      } else {
        togglepreferenceIncome.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    var togglepreferenceRes = $('#preferenceResOptional');
    $('#preferenceResOpt').change(function() {
      //Disply and hide a div
      if ($(this).val().includes('Yes') || $(this).val().includes('Optional')) {
        togglepreferenceRes.show();
      } else {
        togglepreferenceRes.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // Commission Res
    var toggleCommissionRes = $('#commissionResOptional');
    $('#commissionResOpt').change(function() {
      //Disply and hide a div
      if ($(this).val().includes("The Buyer will be able to pay their agent's commission out of pocket.") || $(this)
        .val().includes(
          "The Buyer will ask the agent to include their agent's commission as a part of the offer to the Seller.")) {
        toggleCommissionRes.show();
      } else {
        toggleCommissionRes.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // Commission Income
    var toggleCommissionIncome = $('#commissionIncomeOptional');
    $('#commissionIncomeOpt').change(function() {
      //Disply and hide a div
      if ($(this).val().includes("The Buyer will be able to pay their agent's commission out of pocket.") || $(this)
        .val().includes(
          "The Buyer will ask the agent to include their agent's commission as a part of the offer to the Seller.")) {
        toggleCommissionIncome.show();
      } else {
        toggleCommissionIncome.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // Commission Vacant
    var toggleCommissionVacant = $('#commissionVacantOptional');
    $('#commissionVacantOpt').change(function() {
      //Disply and hide a div
      if ($(this).val().includes("The Buyer will be able to pay their agent's commission out of pocket.") || $(this)
        .val().includes(
          "The Buyer will ask the agent to include their agent's commission as a part of the offer to the Seller.")) {
        toggleCommissionVacant.show();
      } else {
        toggleCommissionVacant.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });

    // Commission Commercial
    var toggleCommissionCommercail = $('#commissionCommercialOptional');
    $('#commissionOptCommercial').change(function() {
      //Disply and hide a div
      if ($(this).val().includes("The Buyer will be able to pay their agent's commission out of pocket.") || $(this)
        .val().includes(
          "The Buyer will ask the agent to include their agent's commission as a part of the offer to the Seller.")) {
        toggleCommissionCommercail.show();
      } else {
        toggleCommissionCommercail.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });

    // Preference View Vacant
    var preferenceVacantOptions = $('#preferenceOptionsVacant');
    $('#preferenceOptVacant').change(function() {
      //Disply and hide a div
      if ($(this).val().includes("Yes") || $(this).val().includes("Optional")) {
        preferenceVacantOptions.show();
      } else {
        preferenceVacantOptions.hide();
      }

      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');

      // Hide all custom option fields first
      $('.form-group[data-target^=".custom_"]').addClass('d-none');

      // Show the specific custom option field based on the selected value
      if (targetClass) {
        $(targetClass).removeClass('d-none');
      }
    });
    // Commercial View Vacant
    var preferenceCommercial = $('#preferenceOptionsCommercial');
    $('#preferenceOptCommercial').change(function() {
      //Disply and hide a div
      if ($(this).val().includes("Yes") || $(this).val().includes("Optional")) {
        preferenceCommercial.show();
      } else {
        preferenceCommercial.hide();
      }

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
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush
