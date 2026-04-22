@extends('layouts.main')
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/choices.min.css') }}">
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.css">
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
  {{-- @if ($errors->any())
<h4>{{$errors->first()}}</h4>
@endif --}}
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
          <form class="p-4 pt-0 validate mainform" action="{{ route('sellerAgentHireAuction') }}" method="POST"
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
                        "This service is for sellers who are not currently working with a licensed agent."
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
                Hire Seller's Agent auction
              </h4>
              <div class="wizard-steps-progress">
                <div class="steps-progress-percent"></div>
              </div>
              <div class="wizard-step" data-step="1">
                <div class="form-group">
                  <label class="fw-bold">Is the seller currently represented by another agent?
                  </label>
                  <select class="grid-picker" name="working_with_agent" id="working_with_agent"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $yes_or_no)
                      <option value="{{ $yes_or_no['name'] }}" data-target="{{ $yes_or_no['target'] }}"
                        class="card flex-row " style="width:calc(33.3% - 10px);"
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
              <div class="wizard-step" data-step="2">
                <h4 class="title">Please provide the property's complete address, along with the city,
                  county, and
                  state, pertaining to the real estate asset that the seller intends to place on the
                  market.</h4>
                <div class="form-group">
                  <label class="fw-bold">Address:</label>
                  <input type="text" name="address" id="form_title" class="form-control has-icon search_places" data-type="address"
                    required data-icon="fa-solid fa-location-dot" placeholder="" />
                  @if ($errors->has('address'))
                    <div class="small error">{{ $errors->first('address') }}</div>
                  @endif
                </div>

                <div class="form-group">
                  <label class="fw-bold">City:</label>
                  <input type="text" name="city" data-type="cities" id="city" class="form-control has-icon search_places"
                    data-icon="fa-solid fa-city" placeholder="" required>
                </div>

                <div class="form-group">
                  <label class="fw-bold">County:</label>
                  <input type="text" name="county" data-type="counties" id="county" class="form-control has-icon search_places"
                    data-icon="fa-solid fa-tree-city" placeholder="" required>
                </div>

                {{-- nisar changing --}}
                <div class="form-group">
                  <label class="fw-bold">State:</label>
                  <input type="text" name="state" data-type="states" id="state" class="form-control has-icon search_places"
                    data-icon="fa-solid fa-flag-usa" placeholder="" required>
                </div>
                <lable>Note: The listing's address remains confidential until a seller employs an agent. </lable>
              </div>
              <div class="wizard-step" data-step="3">
                <div class="form-group">
                  <label for="address" class="fw-bold">Listing Date:</label>
                  <input type="date" name="listing_date" id="listing_date" class="form-control has-icon search_places"
                    data-icon="fa-regular fa-calendar-days"
                    min="{{ date('Y-m-d') }}" placeholder="" required>
                </div>
                <div class="form-group" class="fw-bold">
                  <label for="address" class="fw-bold">Expiration Date:</label>
                  <input type="date" name="expiration_date" id="expiration_date"
                    class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days" min="{{ date('Y-m-d') }}" placeholder="" required>
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
                    <select name="auction_type" id="auction_type" class="grid-picker"
                      style="justify-content: flex-start;" onchange="changeAuctionType(this.value);" required>
                      <option value=""></option>
                      @foreach ($auction_types as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row "
                          style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>

                <div class="form-group d-none auction_length_cover">
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
                          class="card flex-row  {{ $item['class'] }}" style="width:calc(33.33% - 10px);"
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
                  <label for="address" class="fw-bold">Title of Listing:</label>
                  <input type="text" name="title_listing" id="" class="form-control has-icon "
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
                  <label class="fw-bold">Property Style:</label>
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
                          // Business Items
                          ['target' => '','name' => 'Aeronautical', 'class' => 'business-length'],
                        ['target' => '','name' => 'Agriculture', 'class' => 'business-length'],
                        ['target' => '','name' => 'Arts and Entertainment', 'class' => 'business-length'],
                        ['target' => '','name' => 'Assembly Hall', 'class' => 'business-length'],
                        ['target' => '','name' => 'Assisted Living', 'class' => 'business-length'],
                        ['target' => '','name' => 'Auto Dealer', 'class' => 'business-length'],
                        ['target' => '','name' => 'Auto Service', 'class' => 'business-length'],
                        ['target' => '','name' => 'Bar/Tavern/Lounge', 'class' => 'business-length'],
                        ['target' => '','name' => 'Barber/Beauty', 'class' => 'business-length'],
                        ['target' => '','name' => 'Car Wash', 'class' => 'business-length'],
                        ['target' => '','name' => 'Child Care', 'class' => 'business-length'],
                        ['target' => '','name' => 'Church', 'class' => 'business-length'],
                        ['target' => '','name' => 'Commercial', 'class' => 'business-length'],
                        ['target' => '','name' => 'Concession Trailers/Vehicles', 'class' => 'business-length'],
                        ['target' => '','name' => 'Construction/Contractor', 'class' => 'business-length'],
                        ['target' => '','name' => 'Convenience Store', 'class' => 'business-length'],
                        ['target' => '','name' => 'Distribution', 'class' => 'business-length'],
                        ['target' => '','name' => 'Distributor Routine Ven', 'class' => 'business-length'],
                        ['target' => '','name' => 'Education/School', 'class' => 'business-length'],
                        ['target' => '','name' => 'Farm', 'class' => 'business-length'],
                        ['target' => '','name' => 'Fashion/Specialty', 'class' => 'business-length'],
                        ['target' => '','name' => 'Flex Space', 'class' => 'business-length'],
                        ['target' => '','name' => 'Florist/Nursery', 'class' => 'business-length'],
                        ['target' => '','name' => 'Food & Beverage', 'class' => 'business-length'],
                        ['target' => '','name' => 'Gas Station', 'class' => 'business-length'],
                        ['target' => '','name' => 'Grocery', 'class' => 'business-length'],
                        ['target' => '','name' => 'Heavy Weight Sales Service', 'class' => 'business-length'],
                        ['target' => '','name' => 'Hotel/Motel', 'class' => 'business-length'],
                        ['target' => '','name' => 'Industrial', 'class' => 'business-length'],
                        ['target' => '','name' => 'Light Items Sales Only', 'class' => 'business-length'],
                        ['target' => '','name' => 'Manufacturing', 'class' => 'business-length'],
                        ['target' => '','name' => 'Marine/Marina', 'class' => 'business-length'],
                        ['target' => '','name' => 'Medical', 'class' => 'business-length'],
                        ['target' => '','name' => 'Mixed', 'class' => 'business-length'],
                        ['target' => '','name' => 'Mobile/Trailer Park', 'class' => 'business-length'],
                        ['target' => '','name' => 'Personal Service', 'class' => 'business-length'],
                        ['target' => '','name' => 'Professional Service', 'class' => 'business-length'],
                        ['target' => '','name' => 'Professional/Office', 'class' => 'business-length'],
                        ['target' => '','name' => 'Recreation', 'class' => 'business-length'],
                        ['target' => '','name' => 'Research & Development', 'class' => 'business-length'],
                        ['target' => '','name' => 'Residential', 'class' => 'business-length'],
                        ['target' => '','name' => 'Restaurant', 'class' => 'business-length'],
                        ['target' => '','name' => 'Retail', 'class' => 'business-length'],
                        ['target' => '','name' => 'Shopping Center/Strip Center', 'class' => 'business-length'],
                        ['target' => '','name' => 'Storage', 'class' => 'business-length'],
                        ['target' => '','name' => 'Theatre', 'class' => 'business-length'],
                        ['target' => '','name' => 'Timberland', 'class' => 'business-length'],
                        ['target' => '','name' => 'Veterinary', 'class' => 'business-length'],
                        ['target' => '','name' => 'Warehouse', 'class' => 'business-length'],
                        ['target' => '','name' => 'Wholesale', 'class' => 'business-length'],
                          ['target' => '.businessOther', 'name' => 'Other', 'class' => 'business-length'],
                          // Vacant Land Items
                          ['target' => '', 'name' => 'Agricultural ', 'class' => 'vacant_land-length'],
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
                      ];
                    @endphp

                    <label class="fw-bold d-none businessTitle">Business Type:</label>
                    <label class="fw-bold d-none vacantTitle">Current Use:</label>
                    <select name="property_items[]" id="property_items" class="property_items grid-picker"
                      style="justify-content: flex-start;" multiple required>
                      <option value=""></option>
                      @foreach ($property_items as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row {{ $item['class'] }}" style="width:calc(33.33% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group vacantOther d-none">
                      <label class="fw-bold">Current Use: </label>
                      <input type="text" name="otherVacant" class="form-control has-icon  "
                        data-icon="fa-solid fa-ruler-combined"  placeholder="">
                    </div>
                    <div class="form-group businessOther d-none">
                      <label class="fw-bold">Business Type: </label>
                      <input type="text" name="businessOther" class="form-control has-icon  "
                        data-icon="fa-solid fa-ruler-combined"  placeholder="">
                    </div>
                  </div>
                </div>
              </div>
              <div class="wizard-step" data-step="7">
                <span class="resFields">
                  <div class="form-group">
                    <label class="fw-bold">
                        Special Sale Provision:
                    </label>
                    @php
                      $seller_property = [
                          [
                              'target' => '.assignment_contract_res',
                              'name' => 'Assignment Contract',
                            ],
                        ['target' => '', 'name' => 'Auction'],
                        ['target' => '', 'name' => 'Bank Owned/REO'],
                        ['target' => '', 'name' => 'Government Owned'],
                        ['target' => '', 'name' => 'Probate Listing'],
                        ['target' => '', 'name' => 'Short Sale'],
                        ['target' => '', 'name' => 'None'],
                          ['target' => '.custom_special_sale_res', 'name' => 'Other'],
                      ];
                    @endphp
                    <div class="select2-parent ">
                      <select name="special_sale" id="special_sale" class="grid-picker"
                        style="justify-content: flex-start;" required>
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
                  <div class="form-group custom_special_sale_res d-none">
                    <label class="fw-bold" for="custom_special_sale_res">Special Sale Provision: </label>
                    <input type="text" name="custom_special_sale" id="custom_special_sale"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>

                  <div class="form-group d-none assignment_contract_res">
                    <label class="fw-bold">
                      Is the seller currently under contract with a property they would like to assign?
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
                      <label class="fw-bold">What fee would the seller pay the agent to assign the contract? </label>
                      <input type="text" class="form-control has-icon" placeholder=""
                        name="commercialseller_contract_yes" data-icon="fa-solid fa-dollar-sign"
                        id="commercialseller_contract_yes" required />
                    </div>
                    <div class="form-group commercial_seller_contract_no d-none">
                      <label class="fw-bold">Is the seller looking to take over a buyer’s contract? </label>
                      @php
                        $seller_contract_yes_no = [['name' => 'Yes', 'target' => ''], ['name' => 'No', 'target' => '']];
                      @endphp
                      <select name="custom_seller_contract_no" id="commercial_seller_contract_no" class="grid-picker"
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
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="8">
                <span class="resFields">
                  @php
                    $prop_condition = [
                        ['name' => 'Pre-Construction', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                        ['name' => 'Currently Being Built', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                        ['name' => 'New Construction', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                        [
                            'name' => 'Completely Updated: No updates needed',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                        [
                            'name' => 'Semi-updated: Needs minor updates',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                        [
                            'name' => 'Not Updated: Requires a complete update',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                        [
                            'name' => 'Tear Down: Requires complete demolition and reconstruction',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                        [
                            'name' => 'Other',
                            'target' => '.other_prop_condition',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                    ];
                  @endphp
                  <div class="form-group ">
                    <label class="fw-bold">
                        Property Condition:
                    </label>
                    <select class="grid-picker" name="prop_condition" id="prop_condition"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($prop_condition as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group other_prop_condition d-none">
                    <label class="fw-bold">Property Condition:  </label>
                    <input type="text" name="custom_prop_condition" id="custom_prop_condition"
                      class="form-control has-icon  " data-icon="fa-solid fa-ruler-combined" placeholder="">
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="9">
                <span class="resFields">
                  @php
                    $bedrooms = [
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
                        ['name' => 'Other', 'target' => '.other_bedrooms_residential'],
                    ];
                  @endphp
                  <div class="form-group ">
                    <label class="fw-bold"> Bedrooms:</label>
                    <select class="grid-picker" name="bedrooms" id="bedrooms" style="justify-content: center;"
                      required>
                      <option value="">Select</option>
                      @foreach ($bedrooms as $bedroom)
                        <option value="{{ $bedroom['name'] }}" data-target="{{ $bedroom['target'] }}"
                          class="card flex-column" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-solid fa-bed"></i>'>
                          {{ $bedroom['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group other_bedrooms_residential d-none ">
                    <label class="fw-bold" for="other_bedrooms">Bedrooms:</label>
                    <input type="text" name="other_bedrooms" id="other_bedrooms" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-bed"
                      required>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="10">
                <span class="resFields">
                  @php

                    $bathrooms = [
                        ['name' => '1', 'target' => ''],
                        ['name' => '1.5', 'target' => ''],
                        ['name' => '2', 'target' => ''],
                        ['name' => '2.5', 'target' => ''],
                        ['name' => '3', 'target' => ''],
                        ['name' => '3.5', 'target' => ''],
                        ['name' => '4', 'target' => ''],
                        ['name' => '4.5', 'target' => ''],
                        ['name' => '5', 'target' => ''],
                        ['name' => '6', 'target' => ''],
                        ['name' => '7', 'target' => ''],
                        ['name' => '8', 'target' => ''],
                        ['name' => '9', 'target' => ''],
                        ['name' => '10', 'target' => ''],
                        ['name' => 'Other', 'target' => '.other_bathrooms_residential'],
                    ];
                  @endphp
                  <div class="form-group ">
                    <label class="fw-bold">Bathrooms:</label>
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
                  </div>
                  <div class="form-group other_bathrooms_residential d-none ">
                    <label class="fw-bold" for="other_bathrooms">Bathrooms:</label>
                    <input type="text" name="other_bathrooms" id="other_bathrooms" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-bath"
                      required>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="11">
                <span class="resFields">
                  <div class="form-group ">
                    <label class="fw-bold">What is the heated square footage of the property?</label>
                    <input type="text" name="heated_square" data-type="heated_square_footage" placeholder=""
                      id="heated_square_footage" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group ">
                    @php
                      $sqfts = [
                          ['name' => 'Appraisal', 'target' => ''],
                          ['name' => 'Building', 'target' => ''],
                          ['name' => 'Measure', 'target' => ''],
                          ['name' => 'Owner Provided', 'target' => ''],
                          ['name' => 'Public Records', 'target' => ''],
                          ['name' => 'Other', 'target' => '.othersqft'],
                      ];
                    @endphp
                    <label class="fw-bold">Sqft Heated Source: </label>
                    <select class="grid-picker" name="sqft" id="appliances" style="justify-content: flex-start;"
                      required>
                      <option value="">Select</option>
                      @foreach ($sqfts as $item)
                        <option value="{{ $item['name'] }}" data-icon='<i class="fa-regular fa-check-circle"></i>'
                          data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group othersqft d-none ">
                      <label class="fw-bold">Sqft Heated Source:</label>
                      <input type="text" name="other_heated" data-type="heated_square_footage"
                        data-icon="fa-solid fa-ruler-combined" class="form-control has-icon" required>
                    </div>
                  </div>
                  <div class="form-group  ">
                    <label class="fw-bold">Total Sqft:  :</label>
                    <input type="text" name="totalSqft" data-type="heated_square_footage"
                      data-icon="fa-solid fa-ruler-combined" class="form-control has-icon" required>
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
                    <label class="fw-bold">Total Acreage: </label>
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
                <span class="resFields">
                  <div class="form-group ">
                    <label class="fw-bold">Garage:</label>
                    </label>
                    @php
                      $garageRes = [
                          ['name' => 'Yes', 'target' => '.garageYesRes', 'icon' => 'fa-solid fa-warehouse'],
                          ['name' => 'No', 'target' => '.garageNoRes', 'icon' => 'fa-solid fa-warehouse'],
                      ];
                    @endphp
                    <select name="garageOptions" id="contribute_term" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($garageRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group garageYesRes d-none ">
                      <label class="fw-bold">How many garage spaces?</label>
                      <input type="text" name="garage" data-type="garage" data-icon="fa-solid fa-warehouse"
                        class="form-control has-icon" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Carport:</label>
                      </label>
                      @php
                        $carportRes = [
                            ['name' => 'Yes', 'target' => '.carportYesRes', 'icon' => 'fa-solid fa-warehouse'],
                            ['name' => 'No', 'target' => '.carportNoRes', 'icon' => 'fa-solid fa-warehouse'],
                        ];
                      @endphp
                      <select name="carportOptions" id="contribute_term" class="grid-picker"
                        style="justify-content: flex-start;" required>
                        <option value=""></option>
                        @foreach ($carportRes as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group carportYesRes d-none ">
                        <label class="fw-bold">How many carport spaces?</label>
                        <input type="text" name="carport" data-type="carport" data-icon="fa-solid fa-warehouse"
                          class="form-control has-icon"  required>
                      </div>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="14">
                <span class="resFields">
                  <div class="form-group ">
                    <label class="fw-bold">Pool:</label>
                    </label>
                    @php
                      $poolOptions = [
                          ['name' => 'Yes', 'target' => '.poolYesRes', 'icon' => 'fa-regular fa-check-circle'],
                          ['name' => 'No', 'target' => '.poolNoRes', 'icon' => 'fa-regular fa-circle-xmark'],
                      ];
                      $poolRes = [
                          ['name' => 'Private', 'target' => '.poolPrivate'],
                          ['name' => 'Community', 'target' => '.poolCommunity'],
                      ];
                    @endphp
                    <select name="poolOptions" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($poolOptions as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group poolYesRes d-none ">
                      <select name="pool" id="" class="grid-picker" style="justify-content: flex-start;"
                        required>
                        <option value=""></option>
                        @foreach ($poolRes as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>
                    <div class="form-group d-none ">
                      <label class="fw-bold">How many pool spaces?</label>
                      <input type="number" name="poolOther" data-type="pool" id="pool" class="form-control has-icon" required>
                    </div>
                  </div>
                </span>
                    @php
                    $viewRes = [
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
                        ['name' => 'Other', 'target' => '.otherView'],
                    ];
                @endphp
                <div class="form-group ">
                    <label class="fw-bold">View:</label>
                    <select class="grid-picker" name="view[]"  style="justify-content: flex-start;"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($viewRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                        </option>
                    @endforeach
                    </select>
                    <div class="form-group otherView d-none ">
                    <label class="fw-bold">View:</label>
                    <input type="text" class="form-control has-icon" name="otherView" id="otherView" required
                        data-icon="fa-solid fa-ruler-combined" />
                    </div>
                </div>
              </div>
              <div class="wizard-step" data-step="15">
                <span class="resFields">
                  <div class="form-group commercial_hide">
                    @php
                      $garageRes = [
                          [
                              'target' => '.garageYesResCommercial',
                              'name' => 'Yes',
                              'icon' => 'fa-regular fa-check-circle',
                          ],
                          ['target' => '.garageNoCommercial', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                      ];
                    @endphp
                    <label class="fw-bold">Garage/Parking Features:</label>
                    <select name="garageCommercial" class="grid-picker" id="garage"
                      style="justify-content: flex-start;" required>
                      @foreach ($garageRes as $item)
                        <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                          data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    @php
                      $garageParkingRes = [
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
                          ['name' => 'Other', 'target' => '.other_garage_res'],
                      ];
                    @endphp
                    <div class="form-group d-none garageYesResCommercial">
                      <select class="grid-picker" name="garage_parking" style="justify-content: flex-start;" required>
                        <option value="">Select</option>
                        @foreach ($garageParkingRes as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column" style="width:calc(25% - 10px);"
                            data-icon='<i class="fa-solid fa-warehouse"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group other_garage_res d-none">
                        <label class="fw-bold" for="other_garage">Garage/Parking Features :</label>
                        <input type="text" name="other_garage" class="form-control has-icon hide_arrow"
                          data-icon="fa-solid fa-warehouse" required>
                      </div>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="16">
                <span class="resFields">
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
                        ['name' => 'Other', 'target' => '.custom_appliances_commercial'],
                    ];
                  @endphp
                  <div class="form-group ">
                    <label class="fw-bold">Appliances: </label>
                    <select class="grid-picker" name="appliances[]" id="appliances"
                      style="justify-content: flex-start;" multiple required>
                      <option value="">Select</option>
                      @foreach ($appliances as $appliance)
                        <option value="{{ $appliance['name'] }}" data-target="{{ $appliance['target'] }}"
                          data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                          style="width:calc(33.3% - 10px);">
                          {{ $appliance['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group custom_appliances_commercial d-none ">
                      <label class="fw-bold">Appliances:</label>
                      <input type="text" class="form-control has-icon" name="custom_appliances"
                        data-icon="fa-solid fa-ruler-combined" id="custom_appliances_commercial" required />
                    </div>
                  </div>
                  <div class="form-group ">
                    <label class="fw-bold">Touchless Faucet:</label>
                    <input type="text" class="form-control has-icon" name="faucet"
                      data-icon="fa-solid fa-ruler-combined" id="custom_appliances_commercial" required />
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="17">
                <span class="resFields">
                  @php
                    $appliances = [
                        ['name' => 'Parking Spaces', 'target' => ''],
                        ['name' => 'Loading Dock', 'target' => ''],
                        ['name' => 'Warehouse Space', 'target' => ''],
                        ['name' => 'Office Space', 'target' => ''],
                        ['name' => 'Conference Room', 'target' => ''],
                        ['name' => 'Kitchenette/Break Room', 'target' => ''],
                        ['name' => 'Restrooms', 'target' => ''],
                        ['name' => 'Elevator', 'target' => ''],
                        ['name' => 'Handicap Accessibility ', 'target' => ''],
                        ['name' => 'Security System ', 'target' => ''],
                        ['name' => 'On-site Maintenance ', 'target' => ''],
                        ['name' => 'On-site Management ', 'target' => ''],
                        ['name' => 'Outdoor Space/Garden ', 'target' => ''],
                        ['name' => 'Signage Opportunities ', 'target' => ''],
                        ['name' => 'High-Speed Internet ', 'target' => ''],
                        ['name' => 'Utilities Included ', 'target' => ''],
                        ['name' => 'HVAC System ', 'target' => ''],
                        ['name' => 'Natural Lighting ', 'target' => ''],
                        ['name' => 'Storage Space ', 'target' => ''],
                        ['name' => 'Open Floor Plan ', 'target' => ''],
                        ['name' => 'Retail Frontage ', 'target' => ''],
                        ['name' => 'Restaurant Space ', 'target' => ''],
                        ['name' => 'Industrial Features ', 'target' => ''],
                        ['name' => 'Flexibility for Renovations ', 'target' => ''],
                        ['name' => 'Common Areas ', 'target' => ''],
                        ['name' => 'Business Center ', 'target' => ''],
                        ['name' => 'Gym/Fitness Facilities ', 'target' => ''],
                        ['name' => 'Lounge Area ', 'target' => ''],
                        ['name' => 'Reception Area ', 'target' => ''],
                        ['name' => 'Security Guard ', 'target' => ''],
                        ['name' => 'Fire Safety Systems ', 'target' => ''],
                        ['name' => 'Energy-Efficient Features ', 'target' => ''],
                        ['name' => 'Green Building Certification ', 'target' => ''],
                        ['name' => 'Access to Public Transportation ', 'target' => ''],
                        ['name' => 'Proximity to Highways ', 'target' => ''],
                        ['name' => 'Visibility from Main Road ', 'target' => ''],
                        ['name' => 'Other ', 'target' => '.otherAmenitiesRes'],
                    ];
                  @endphp
                  <div class="form-group">
                    <label class="fw-bold">Amenities and Property Features:</label>
                    <select class="grid-picker" name="amenities[]" id="appliances"
                      style="justify-content: flex-start;" multiple required>
                      <option value="">Select</option>
                      @foreach ($appliances as $appliance)
                        <option value="{{ $appliance['name'] }}" data-target="{{ $appliance['target'] }}"
                          data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                          style="width:calc(33.3% - 10px);">
                          {{ $appliance['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group otherAmenitiesRes d-none">
                      <label class="fw-bold">Amenities and Property Features:</label>
                      <input type="text" class="form-control has-icon" name="otherAmenities"
                        data-icon="fa-regular fa-circle-check" id="otherAmenities" required />
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="18">
                <span class="resFields">
                  <div class="form-group ">
                    <label class="fw-bold">Is the property located in a 55-and-over community?</label>
                    </label>
                    @php
                      $propertyLoc = [
                          ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-check-circle'],
                          ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                      ];
                    @endphp
                    <select name="propertyLoc" id="propertyLoc" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($propertyLoc as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="19">
                <span class="resFields">
                  @php
                    $occupant_types_res = [
                        ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                        ['name' => ' Owner-Occupied', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                        ['name' => 'Tenant-Occupied ', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ];
                  @endphp

                  <div class="form-group ">
                    <label class="fw-bold">What is the current occupancy status of the property?</label>
                    <select class="grid-picker" name="occupant_type" id="occupant_type"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($occupant_types_res as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="20">
                <span class="resFields">
                  <div class="form-group ">
                    @php
                      $specific_price_res = [
                          [
                              'icon' => 'fa-regular fa-check-circle',
                              'name' => 'Yes',
                              'target' => '.specific_price_res_yes',
                          ],
                          [
                              'icon' => 'fa-regular fa-circle-xmark',
                              'name' => 'No',
                              'target' => '.specific_price_res_no',
                          ],
                      ];
                    @endphp
                    <label class="fw-bold">Does the seller have a specific price in mind that they would
                      like to obtain for their property?</label>
                    <select class="grid-picker" name="seller_specific_price" id="seller_specific_price"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($specific_price_res as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group specific_price_res_yes d-none ">
                      <label class="fw-bold">What price would the seller like to obtain for their property?</label>
                      <input type="text" class="form-control has-icon" name="custom_seller_specific_price"
                        data-icon="fa-solid fa-dollar-sign" id="custom_listing_terms" required />
                    </div>
                  </div>

                  <div class="form-group specific_price_res_no d-none ">
                    <label class="fw-bold">
                      How much do you think your property will sell for?
                    </label>
                    @php
                      $expectationsRes = [
                          ['target' => '', 'name' => '$100k $or less'],
                          ['target' => '', 'name' => '$100k-$200k'],
                          ['target' => '', 'name' => '$200k-$300k'],
                          ['target' => '', 'name' => '$300k-$400k'],
                          ['target' => '', 'name' => '$400k-$500k'],
                          ['target' => '', 'name' => '$500k-$600k'],
                          ['target' => '', 'name' => '$600k-$700k'],
                          ['target' => '', 'name' => '$700k-$800k'],
                          ['target' => '', 'name' => '$800-$900k'],
                          ['target' => '', 'name' => '$900k-$1 million'],
                          ['target' => '', 'name' => '$1 million +'],
                          ['target' => '', 'name' => '$2 million +'],
                          ['target' => '', 'name' => '$3 million +'],
                          ['target' => '', 'name' => '$4 million +'],
                          ['target' => '', 'name' => '$5 million +'],
                          ['target' => '', 'name' => '$10 million +'],
                          ['icon' => 'fa-regular fa-check-circle', 'name' => 'Other', 'target' => '.otherRes'],
                      ];
                    @endphp
                    <select name="expectation" id="expectation" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($expectationsRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group otherRes  d-none">
                      <label class="fw-bold">How much do you think your property will sell for?</label>
                      <input type="number" class="form-control has-icon" name="otherSellerPrice"
                        data-icon="fa-solid fa-dollar-sign" id="custom_listing_terms" required />
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="21">
                <span class="resFields">
                  @php
                    $financings = [
                        ['name' => 'Cash', 'target' => ''],
                        ['name' => 'Conventional', 'target' => ''],
                        ['name' => 'Seller Financing', 'target' => '.custom_seller_financing_res'],
                        ['name' => 'FHA', 'target' => ''],
                        ['name' => 'Jumbo', 'target' => ''],
                        ['name' => 'VA', 'target' => ''],
                        ['name' => 'No-Doc', 'target' => ''],
                        ['name' => 'Non-QM', 'target' => ''],
                        ['name' => 'Assumable', 'target' => '.custom_assumable_res'],
                        ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade_res'],
                        ['name' => 'Lease Option', 'target' => '.lease_option_res'],
                        ['name' => 'Lease Purchase', 'target' => '.lease_purchase_res'],
                        ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency_res'],
                        ['name' => 'NFT', 'target' => '.nft'],
                        ['name' => 'USDA', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_financing_residential'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4 ">
                    <div class="col-md-12">
                      <label class="fw-bold">Select the types of financing/currency that is acceptable to the
                        seller:</label>
                      <div class="select2-parent">
                        <select name="financings[]" class="grid-picker" multiple id="financingOptions" required>
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
                  <div class="form-group custom_financing_residential d-none ">
                    <label class="fw-bold">What type of financing/currency is acceptable to the seller? </label>
                    <input type="text" name="type_of_financing" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                  </div>
                  <div class="form-group custom_exchange_trade_res d-none ">
                    @php
                      $tradeRes = [
                          ['name' => 'Another Home', 'target' => ''],
                          ['name' => 'Vehicle', 'target' => ''],
                          ['name' => 'Boat', 'target' => ''],
                          ['name' => 'Motorhome', 'target' => ''],
                          ['name' => 'Artwork', 'target' => ''],
                          ['name' => 'Jewelry ', 'target' => ''],
                          ['name' => 'Other ', 'target' => '.tradeOtherRes'],
                      ];
                    @endphp
                    <label class="fw-bold">Acceptable Exchange Item:</label>
                    <select name="trade[]" class="grid-picker" id="">
                      @foreach ($tradeRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column " style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group tradeOtherRes d-none ">
                      <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is
                        willing to exchange/trade?</label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      <label class="fw-bold">How much cash does the seller require on top of the Exchange/Trade
                        item?</label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                    </div>
                  </div>
                  {{-- Lease Options --}}
                  <div class="form-group lease_option_res d-none" >
                    <label class="fw-bold">What is the seller's desired offering price for a lease option?</label><br>
                    <input type="text" name="leaseOptions[]"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign "
                      >
                    <label class="fw-bold">What specific terms does the seller propose for the lease option?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label><br>
                    <input type="text" name="leaseOptions[]" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for
                      the lease option?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">Is there a possibility of changes in the offering price during the lease
                      period?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                      <div class="form-group">
                        @php
                        $leaseOptRes = [
                            ['name' => 'Yes', 'target' => '.leaseOptionsRes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                            ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <label class="fw-bold">Does the seller require an option fee? </label>
                      <select name="trade[]" class="grid-picker" id="">
                        @foreach ($leaseOptRes as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon="{{$item['icon']}}">
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group leaseOptionsRes d-none">
                        <label class="fw-bold">How much is the option fee? </label>
                          <input type="text" name="leaseOptionsRes" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      </div>
                    </div>
                  </div>
                  {{-- Lease Options --}}
                   {{-- Lease Purchase --}}
                   <div class="form-group lease_purchase_res d-none" >
                    <label class="fw-bold">What is the seller's desired offering price for a lease purchase?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign "
                      >
                    <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for
                      the lease purchase?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold"></label><br>
                    <label class="fw-bold">Is there a possibility of changes in the offering price during the lease
                        period?</label><br>
                        <input type="text" name="leasePurchase[]"
                         class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                        >
                    <div class="form-group">
                        @php
                        $leasePurchaseOpt = [
                            ['name' => 'Yes', 'target' => '.leasePurchaseYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                            ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <label class="fw-bold">Does the seller require an option fee? </label>
                      <select name="trade[]" class="grid-picker" id="">
                        @foreach ($leasePurchaseOpt as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon="{{$item['icon']}}">
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group leasePurchaseYes d-none">
                        <label class="fw-bold">How much is the option fee? </label>
                          <input type="text" name="leasePurchaseYes" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      </div>
                    </div>

                  </div>
                  {{-- Lease Purchase --}}
                  {{-- Seller  --}}
                  <div class="form-group custom_seller_financing_res d-none ">
                    <div class="row">
                      <div class="col-4 form-group">
                        <label class="fw-bold">Purchase Price:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Down Payment:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Seller Financing Amount:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Interest Rate:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-ruler-combined" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Term:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-ruler-combined" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Monthly Payments:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Closing Costs:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      {{-- PrePayement --}}
                      @php
                        $PrepaymentRes = [
                            [
                                'name' => 'Yes',
                                'target' => '.prepaymentOtherRes',
                                'icon' => '<i class="fa-regular fa-circle-check"></i>',
                            ],
                            ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <div class="select2-parent">
                        <br><label class="fw-bold">Prepayment Penalty: </label>
                        <select name="prepayment" class="grid-picker" id="">
                          @foreach ($PrepaymentRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='{{ $item['icon'] }}'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group prepaymentOtherRes d-none ">
                          <label class="fw-bold">What is the prepayment penalty amount? </label>
                          <input type="texts" name="prepaymentOther[]" data-type="custom_timeframe" id=""
                            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                        </div>
                      </div>
                      {{-- Prepayment --}}
                      {{-- Balloon Payment --}}
                      @php
                        $balloonRes = [
                            [
                                'name' => 'Yes',
                                'target' => '.balloonpaymentOtherRes',
                                'icon' => '<i class="fa-regular fa-circle-check"></i>',
                            ],
                            ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <div class="select2-parent">
                        <br><label class="fw-bold">Balloon Payment:</label>
                        <select name="balloon" class="grid-picker" id="">
                          @foreach ($balloonRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='{{ $item['icon'] }}'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group balloonpaymentOtherRes d-none ">
                          <label class="fw-bold">How much is the balloon payment? </label>
                          <input type="text" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign" id=""
                            class="form-control has-icon" required>
                          <label class="fw-bold">When is the balloon payment due? </label>
                          <input type="text" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign" id=""
                            class="form-control has-icon" required>
                        </div>
                      </div>
                      {{-- Balloon Payment --}}
                    </div>
                  </div>
                  {{-- Assumable --}}
                  <div class="form-group custom_assumable_res d-none ">
                    <label class="fw-bold">What assumable terms are being offered?</label>
                    <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" />
                    <label class="fw-bold">Are there any restrictions or qualifications for a buyer assuming the existing
                      financing?</label>
                    <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" />
                    <label class="fw-bold">Is there any outstanding balance on the existing financing that the buyer
                      would need to cover?</label>
                    <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" />

                     <div class="form-group">
                        @php
                        $assumableOpt=[
                          ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.assumableYes'],
                          ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                            ]
                        @endphp
                        <select name="assumable[]" class="grid-picker" id="">
                        @foreach ($assumableOpt as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='{{ $item['icon'] }}'>
                            {{ $item['name'] }}
                            </option>
                        @endforeach
                        </select>
                        <div class="form-group assumableYes d-none ">
                        <label class="fw-bold">What is the outstanding balance on the existing financing that the buyer would need to cover? </label>
                        <input type="text" name="assumableYes" data-icon="fa-solid fa-dollar-sign" id=""
                            class="form-control has-icon" required>
                        </div>
                     </div>
                  </div>
                  {{-- Assumable --}}
                  {{-- Cryptcurrency --}}
                  <div class="form-group custom_cryptocurrency_res d-none ">
                    <label class="fw-bold">What type of cryptocurrency will the seller accept?</label><br>
                        <input type="text" name="cryptocurrency[]"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        >
                    <label class="fw-bold">What percentage of the purchase price will the seller accept in
                        cryptocurrency?</label><br>
                        <input type="text" name="cryptocurrency[]"
                         class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        >
                    <label class="fw-bold">What percentage of the purchase price will the seller accept in
                      cash?</label><br>
                    <input type="text" name="cryptocurrency[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="">Note: Cryptocurrency can be converted to cash at closing. </label>

                  </div>
                  {{-- NFT --}}
                  <div class="form-group nft d-none ">
                    <label class="fw-bold">What type of NFT will the seller accept?</label><br>
                    <input type="text" name="nft[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined "
                      >
                    <label class="fw-bold">What percentage of the sales price will the seller accept in the form of a Non-Fungible Token (NFT)? </label><br>
                    <input type="text" name="nft[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">What percentage of the purchase price will the seller accept in
                      cash?</label><br>
                    <input type="text" name="nft[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >

                  </div>
                  {{-- NFT --}}
                </span>
              </div>
              <div class="wizard-step" data-step="22">
                <span class="resFields">
                  <div class="form-group ">
                    <label class="fw-bold">
                      When will the property be ready to list?
                    </label>
                    @php
                      $timeframesRes = [
                          ['name' => 'Now', 'target' => ''],
                          ['name' => '1 Month', 'target' => ''],
                          ['name' => '2 Months', 'target' => ''],
                          ['name' => '3 Months', 'target' => ''],
                          ['name' => '4 Months', 'target' => ''],
                          ['name' => '5 Months', 'target' => ''],
                          ['name' => '6 Months', 'target' => ''],
                          ['name' => 'Over 6 Months', 'target' => ''],
                          ['name' => 'Other', 'target' => '.otherPropList'],
                      ];
                    @endphp
                    <select name="selling_timeframe" id="selling_timeframe" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($timeframesRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column " style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group otherPropList d-none ">
                    <label class="fw-bold">When will the property be ready to list? </label>
                    <input type="texts" name="custom_timeframe" data-type="custom_timeframe" id="custom_timeframe"
                      class="form-control has-icon" data-icon="fa-solid fa-calendar-days" required>
                  </div>
                  <div class="form-group prompt_response d-none">
                    <p class="text-danger">This is a service designed for sellers who are seeking to sell
                      their property within three Months or less.</p>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="23">
                <span class="resFields">
                  <div class="form-group ">
                    <label class="fw-bold">
                      What is the timeframe offered to the agent in the Seller Agency Agreement?
                    </label>
                    @php
                      $listing_terms_res = [
                          ['name' => '3 Months', 'target' => ''],
                          ['name' => '6 Months', 'target' => ''],
                          ['name' => '9 Months', 'target' => ''],
                          ['name' => '12 Months', 'target' => ''],
                          ['name' => 'Negotiable', 'target' => ''],
                          ['name' => 'Other', 'target' => '.custom_listing_terms_residential'],
                      ];
                    @endphp
                    <select name="listing_term" id="listing_term" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($listing_terms_res as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group custom_listing_terms_residential d-none ">
                    <label class="fw-bold">What is the timeframe offered to the agent in the Seller Agency
                      Agreement?</label>
                    <input type="text" class="form-control has-icon" placeholder="" name="custom_listing_terms"
                      data-icon="fa-solid fa-ruler-combined" id="custom_listing_terms" required />
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="24">
                <span class="resFields">
                  <div class="form-group ">
                    <label class="fw-bold">
                      What is the total commission being offered to the listing agent?
                    </label>
                    @php
                      $offered_commissions = [
                          ['name' => '5%', 'target' => ''],
                          ['name' => '5.5%', 'target' => ''],
                          ['name' => '6%', 'target' => ''],
                          ['name' => 'Negotiable', 'target' => ''],
                          ['name' => 'Other', 'target' => '.custom_offered_commission_residential'],
                      ];
                    @endphp
                    <select name="offered_commission" id="offered_commission" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($offered_commissions as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column " style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group custom_offered_commission_residential d-none ">
                    <label class="fw-bold">What is the total commission being offered to the listing agent?</label>
                    <input type="text" class="form-control has-icon" name="custom_offered_commission"
                      data-icon="fa-solid fa-dollar-sign" required />
                  </div>
                  <div class="form-group commissionSplitRes  d-none">
                    <label class="fw-bold">If a buyer is represented by an agent, what portion of the commission should
                      be shared with the buyer’s agent?</label>
                    <input type="text" class="form-control has-icon" placeholder="" name="commissionSplitOther"
                      data-icon="fa-solid fa-ruler-combined" id="custom_offered_commission" required />
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="25">
                <span class="resFields">
                  <div class="row ">
                    <div class="form-group mt-4 col-md-12">
                      <label class="fw-bold">
                        What are the most important aspects the seller will consider when hiring a real estate agent?
                      </label>
                      <textarea name="important_aspect" class="form-control" value="res" rows="5" required
                        data-msg-required="This Field is Required">{{ old('important_aspect') }}</textarea>
                    </div>
                  </div>

                  <div class="row removeIncome ">
                    <div class="form-group mt-4 col-md-12">
                      <label class="fw-bold">
                        What additional details would the seller like to share with the agent?
                      </label>
                      <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5">{{ old('important_info') }}</textarea>
                    </div>
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="26">
                <span class="resFields">
                  <div class="form-group ">
                    <label class="fw-bold">
                      Select the services that the seller requests from an agent:
                    </label>
                    @php
                      $services_data = [
                          [
                              'name' =>
                                  "Conduct a thorough comparative market analysis (CMA) to determine the property's value and pricing strategy.",
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  "Assist with the development of a strategic marketing plan tailored to the property and 'target' buyers.",
                              'target' => '',
                          ],
                          ['name' => 'List the property on the MLS.', 'target' => ''],
                          [
                              'name' =>
                                  "List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, to increase the property's visibility and exposure.",
                              'target' => '',
                          ],
                          ['name' => 'List the property on the Bid Your Offer platform.', 'target' => ''],
                          [
                              'name' =>
                                  "Implement neighborhood marketing with a QR code or listing link that leads to the property's listing on the BidYourOffer.com platform.",
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  'Market the property to various groups, pages, and affiliates to generate interest and leads.',
                              'target' => '',
                          ],
                          ['name' => 'Promote the property on social media platforms.', 'target' => ''],
                          [
                              'name' => "Provide professional photos to showcase the property's best features.",
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  "Offer aerial photography to capture the property's surroundings and neighborhood.",
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  "Provide a professional video to showcase the property's interior and exterior.",
                              'target' => '',
                          ],
                          ['name' => "Provide a 3D tour to showcase the property's interior.", 'target' => ''],
                          [
                              'name' =>
                                  'Provide a floor plan of the property to showcase its layout and spatial configuration.',
                              'target' => '',
                          ],
                          ['name' => 'Provide recommendations for home staging professionals.', 'target' => ''],
                          [
                              'name' =>
                                  "Provide virtual staging to enhance the property's visual appeal and attract potential buyers.",
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  'Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.',
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  'Offer expert negotiation skills to secure the best possible terms and price during the selling process.',
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  'Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.',
                              'target' => '',
                          ],
                          ['name' => 'Host an Open House(s).', 'target' => ''],
                          [
                              'name' =>
                                  "Send email alerts to buyers searching for properties that match the property's criteria the moment the property is listed directly through the MLS.",
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  'Provide guidance and support throughout the entire transaction, from listing to closing, to ensure a seamless and successful sale.',
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  'Assist with the completion and submission of all necessary paperwork and documentation related to the sale.',
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  'Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.',
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  'Provide regular updates on market activity, showings, and feedback from potential buyers.',
                              'target' => '',
                          ],
                          [
                              'name' =>
                                  'Offer post-sale assistance, such as recommending reputable service providers for moving, repairs, or other post-sale needs.',
                              'target' => '',
                          ],
                          [
                              'name' => 'Other - Add additional services as needed.',
                              'target' => '.commercial_service_residential',
                          ],
                      ];
                    @endphp

                    <select name="services[]" id="commercial-services" multiple class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($services_data as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(100%);"
                          data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group commercial_service_residential d-none ">
                    <label class="fw-bold">What additional services would the seller like to request from an
                      agent?</label>
                    <input type="text" class="form-control has-icon" placeholder="" name="commercial_servic"
                      data-icon="fa-solid fa-hand-point-right" id="commercial_servic" required />
                  </div>
                </span>
              </div>
              <div class="wizard-step" data-step="27">
                <h4>For a more personalized listing, you can include a picture of yourself and/or include a video of yourself providing additional information about your background and criteria.</h4>
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
                  
                </span>
              </div>
             
              <div class="wizard-step" data-step="28">
                <span class="incomeFields">
                  <div class="form-group ">
                    <label class="fw-bold">
                        Special Sale Provision:
                    </label>
                    @php
                      $special_sales = [
                          [
                              'target' => '.assignment_contract_income',
                              'name' => 'Assignment Contract',
                            ],
                        ['target' => '', 'name' => 'Auction'],
                        ['target' => '', 'name' => 'Bank Owned/REO'],
                        ['target' => '', 'name' => 'Government Owned'],
                        ['target' => '', 'name' => 'Probate Listing'],
                        ['target' => '', 'name' => 'Short Sale'],
                            ['target' => '', 'name' => 'None'],
                          ['target' => '.custom_special_sale_income', 'name' => 'Other'],
                      ];
                    @endphp
                    <div class="select2-parent">
                      <select name="special_sale" id="special_sale" class="grid-picker"
                        style="justify-content: flex-start;" required>
                        <option value=""></option>
                        @foreach ($special_sales as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row " style="width:calc(33.33% - 10px);"
                            data-icon='<i class="fa-regular fa-check-circle"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>
                  </div>
                  <div class="form-group custom_special_sale_income d-none ">
                    <label class="fw-bold" for="custom_special_sale_residential">Special Sale Provision: </label>
                    <input type="text" name="custom_special_sale" id="custom_special_sale"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group d-none assignment_contract_income">
                    <label class="fw-bold">
                      Is the seller currently under contract with a property they would like to assign?
                    </label>
                    @php
                      $seller_contract_options_income = [
                          ['name' => 'Yes', 'target' => '.custom_seller_income_yes'],
                          ['name' => 'No', 'target' => '.custom_seller_income_no'],
                      ];
                    @endphp
                    <select name="contribute_term" id="contribute_term" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($seller_contract_options_income as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group custom_seller_income_yes d-none ">
                      <label class="fw-bold">What fee would the seller pay the agent to assign the contract? </label>
                      <input type="number" class="form-control has-icon" min="0.5" step=".01"
                        placeholder="" name="custom_seller_income_yes" data-icon="fa-solid fa-dollar-sign"
                        id="custom_seller_income_yes" required />
                    </div>
                    <div class="form-group custom_seller_income_no d-none">
                      <label class="fw-bold">Is the seller looking to take over a buyer’s contract? </label>
                      @php
                        $seller_contract_yes_no = [['name' => 'Yes', 'target' => ''], ['name' => 'No', 'target' => '']];
                      @endphp
                      <select name="custom_seller_contract_no" id="custom_seller_income_no" class="grid-picker"
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
                    </div>
                </span>
              </div>
            </div>
            <div class="wizard-step" data-step="29">
              <span class="incomeFields">
                @php
                  $prop_condition = [
                      ['name' => 'Pre-Construction', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'Currently Being Built', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'New Construction', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                      [
                          'name' => 'Completely Updated: No updates needed',
                          'target' => '',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Semi-updated: Needs minor updates',
                          'target' => '',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Not Updated: Requires a complete update',
                          'target' => '',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Tear Down: Requires complete demolition and reconstruction',
                          'target' => '',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Other',
                          'target' => '.other_prop_condition_income',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                  ];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">
                    Property Condition:
                  </label>
                  <select class="grid-picker" name="prop_condition" style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($prop_condition as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="row other_prop_condition_income  d-none">
                  <div class="form-group">
                    <label class="fw-bold">Property Condition: </label>
                    <input type="text" class="form-control has-icon" name="custom_prop_condition"
                      data-icon="fa-solid fa-ruler-combined" id="unit_beds" required />
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="30">
              <span class="incomeFields">
                <h4>Units</h4>
                <label class="fw-bold">Please provide the information on each unit type that the seller intends to
                  sell.</label>
                @php
                  $unit_types = [
                      ['target' => '', 'name' => '1 Bed/1 Bath'],
                      ['target' => '', 'name' => '1 Bedroom'],
                      ['target' => '', 'name' => '2 Bed/1 Bath'],
                      ['target' => '', 'name' => '2 Bed/2 Bath'],
                      ['target' => '', 'name' => '2 Bedroom'],
                      ['target' => '', 'name' => '3 Bed/1 Bath'],
                      ['target' => '', 'name' => '3 Bed/2 Bath'],
                      ['target' => '', 'name' => '3 Bedroom'],
                      ['target' => '', 'name' => '4 Bedroom or More'],
                      ['target' => '', 'name' => '4+ Bed/1 Bath'],
                      ['target' => '', 'name' => '4+ Bed/2 Bath'],
                      ['target' => '', 'name' => 'Apartments'],
                      ['target' => '', 'name' => 'Efficiency'],
                      ['target' => '', 'name' => 'Loft'],
                      ['target' => '', 'name' => "Manager's Unit"],
                      ['target' => '.unitOther', 'name' => 'Other'],
                  ];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">Unit Type:</label>
                  <select class="grid-picker" name="unit_types" id="condition_interested" required>
                    <option value="">Select</option>
                    @foreach ($unit_types as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group unitOther d-none">
                    <label class="fw-bold">Unit Type:</label>
                    <input type="text" class="form-control has-icon" name="unitOther"
                      data-icon="fa-solid fa-ruler-combined" id="" required />
                  </div>
                </div>
                <div class="form-group ">
                  <label class="fw-bold"> Heated Sqft:</label>
                  <input type="text" class="form-control has-icon" name="sqft_heated_unit"
                    data-icon="fa-solid fa-ruler-combined" id="sqft_heated_unit" required />
                </div>
                <div class="form-group ">
                  @php
                    $heatedSqft = [
                        ['target' => '', 'name' => 'Appraisal'],
                        ['target' => '', 'name' => 'Building'],
                        ['target' => '', 'name' => 'Measure'],
                        ['target' => '', 'name' => 'Owner Provided'],
                        ['target' => '', 'name' => 'Public Records'],
                        ['target' => '.heatedOther', 'name' => 'Other'],
                    ];
                  @endphp
                  <label class="fw-bold">Sqft Heated Source:</label>
                  <select class="grid-picker" name="heated_source" id="condition_interested" required>
                    <option value="">Select</option>
                    @foreach ($heatedSqft as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group heatedOther d-none">
                    <label class="fw-bold">Sqft Heated Source: </label>
                    <input type="text" class="form-control has-icon" name="otherSqft"
                      data-icon="fa-solid fa-ruler-combined" required />
                  </div>
                </div>
                <div class="form-group ">
                  <label class="fw-bold"># of Units: </label>
                  <input type="text" class="form-control has-icon" name="number_of_units"
                    data-icon="fa-solid fa-ruler-combined" id="number_of_units" required />
                </div>
                <div class="form-group ">
                  <label class="fw-bold"># Occupied:</label>
                  <input type="text" class="form-control has-icon" name="number_occupied"
                    data-icon="fa-solid fa-ruler-combined" id="number_occupied" required />
                </div>
                <div class="form-group ">
                  <label class="fw-bold">Current Rent:</label>
                  <input type="text" class="form-control has-icon" name="number_occupied"
                    data-icon="fa-solid fa-ruler-combined" id="number_occupied" required />
                </div>
                <div class="form-group ">
                  <label class="fw-bold"># Vacant:</label>
                  <input type="text" class="form-control has-icon" name="vacant"
                    data-icon="fa-solid fa-ruler-combined" id="unit_baths" required />
                </div>
                <div class="form-group ">
                  <label class="fw-bold">Expected Rent:</label>
                  <input type="text" class="form-control has-icon" name="expected_rent"
                    data-icon="fa-solid fa-ruler-combined" id="expected_rent" required />
                </div>

                <span class="incomeFields">
                  <div class="form-group ">
                    <label class="fw-bold">Garage:</label>
                    </label>
                    @php
                      $garageIncome = [
                          ['name' => 'Yes', 'target' => '.garageYesIncome', 'icon' => 'fa-regular fa-check-circle'],
                          ['name' => 'No', 'target' => '.garageNoRes', 'icon' => 'fa-regular fa-circle-xmark'],
                      ];
                    @endphp
                    <select name="garageOptions" id="contribute_term" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($garageIncome as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group garageYesIncome d-none ">
                      <label class="fw-bold">How many garage spaces?</label>
                      <input type="text" name="garage" data-type="garage" placeholder="" id="garage"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Carport:</label>
                      </label>
                      @php
                        $carportIncome = [
                            ['name' => 'Yes', 'target' => '.carportYesIncome', 'icon' => 'fa-regular fa-check-circle'],
                            ['name' => 'No', 'target' => '.carportNoRes', 'icon' => 'fa-regular fa-circle-xmark'],
                        ];
                      @endphp
                      <select name="carportOptions" id="contribute_term" class="grid-picker"
                        style="justify-content: flex-start;" required>
                        <option value=""></option>
                        @foreach ($carportIncome as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group carportYesIncome d-none ">
                        <label class="fw-bold">How many carport spaces?</label>
                        <input type="text" name="carport" data-type="carport" placeholder="" id="carport"
                          class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                    </div>
                  </div>
                </span>
                <div class="form-group ">
                  <button type="button" class="btn btn-secondary btn-sm w-100 unit_btn"
                    onclick="add_city_row();"><i class="fa-solid fa-plus"></i> Add New
                    Row</button>
                  <label>Note: Add more if there are more than one type of unit:</label>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="31">
              <span class="incomeFields">
                @php
                  $acreageIncome = [
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

                <div class="form-group">
                  <label class="fw-bold">Total Acreage:</label>
                  <select class="grid-picker" name="total_acreage" id="total_acreage"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($acreageIncome as $item)
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
            <div class="wizard-step" data-step="32">
              <span class="incomeFields">
                <div class="form-group">
                  <label class="fw-bold">Pool:</label>
                  </label>
                  @php
                    $poolOptionsIncome = [
                        ['name' => 'Yes', 'target' => '.poolYesIncome', 'icon' => 'fa-regular fa-check-circle'],
                        ['name' => 'No', 'target' => '.poolNoIncome', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                    $poolIncome = [
                        ['name' => 'Private', 'target' => '.poolPrivate'],
                        ['name' => 'Community', 'target' => '.poolCommunity'],
                    ];
                  @endphp
                  <select name="poolOptions" id="contribute_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($poolOptionsIncome as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group poolYesIncome d-none">
                    <select name="pool" id="" class="grid-picker" style="justify-content: flex-start;"
                      required>
                      <option value=""></option>
                      @foreach ($poolIncome as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group d-none">
                    <label class="fw-bold">How many pool spaces?</label>
                    <input type="number" name="poolOther" data-type="pool"
                      placeholder="Enter Heated Square Footage" id="pool" class="form-control has-icon"
                      data-msg-required="Please Enter Heated Square Footage" required>
                  </div>
                </div>
              </span>
              <span class="incomeFields">
                @php
                  $viewIncome = [
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
                      ['name' => 'Other', 'target' => '.otherViewIncome'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">View:</label>
                  <select class="grid-picker" name="view[]" id="appliances" style="justify-content: flex-start;"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($viewIncome as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherViewIncome d-none">
                    <label class="fw-bold">View:</label>
                    <input type="text" class="form-control has-icon" name="otherView" id="otherView" required
                      data-icon="fa-solid fa-ruler-combined" />
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="33">
              <span class="incomeFields">
                <div class="form-group">
                  @php
                    $rent_include = [
                        ['target' => '', 'name' => 'Cable TV'],
                        ['target' => '', 'name' => 'Electricity'],
                        ['target' => '', 'name' => 'Gas'],
                        ['target' => '', 'name' => 'Grounds Care'],
                        ['target' => '', 'name' => 'Insurance'],
                        ['target' => '', 'name' => 'Internet'],
                        ['target' => '', 'name' => 'Laundry'],
                        ['target' => '', 'name' => 'Management'],
                        ['target' => '', 'name' => 'None'],
                        ['target' => '', 'name' => 'Pest Control'],
                        ['target' => '', 'name' => 'Pool Maintenance'],
                        ['target' => '', 'name' => 'Recreational'],
                        ['target' => '', 'name' => 'Repairs'],
                        ['target' => '', 'name' => 'Security'],
                        ['target' => '', 'name' => 'Sewer'],
                        ['target' => '', 'name' => 'Taxes'],
                        ['target' => '', 'name' => 'Telephone'],
                        ['target' => '', 'name' => 'Trash Collection'],
                        ['target' => '', 'name' => 'Water'],
                        ['target' => '', 'name' => 'None'],
                        ['target' => '.custom_rent', 'name' => 'Other'],
                    ];
                  @endphp
                  <label class="fw-bold">Rent includes: </label>
                  <select class="grid-picker" name="rent_include" id="condition_interested" required multiple>
                    <option value="">Select</option>
                    @foreach ($rent_include as $item)
                      <option value="{{ $item['name'] }}" class="card flex-row" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' data-target="{{ $item['target'] }}">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_rent d-none">
                  <label class="fw-bold">Rent includes: </label>
                  <input type="text" class="form-control has-icon" name="custom_rent"
                    data-icon="fa-solid fa-ruler-combined" id="custom_rent" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="34">
              <span class="incomeFields">
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
                      ['name' => 'Other', 'target' => '.custom_appliances'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Appliances: </label>
                  <select class="grid-picker" name="appliances[]" id="appliances"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($appliances as $appliance)
                      <option value="{{ $appliance['name'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'
                        data-target="{{ $appliance['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-target="{{ $item['target'] }}">
                        {{ $appliance['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group custom_appliances d-none">
                    <label class="fw-bold">Appliances:</label>
                    <input type="text" class="form-control has-icon" name="custom_appliances"
                      data-icon="fa-solid fa-ruler-combined" id="custom_rent" required />
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="35">
              <span class="incomeFields">
                @php
                  $amenitiesIncome = [
                      ['name' => 'Garage ', 'target' => ''],
                      ['name' => 'Carport', 'target' => ''],
                      ['name' => 'Pool', 'target' => ''],
                      ['name' => 'Waterfront', 'target' => ''],
                      ['name' => 'In-Unit Laundry ', 'target' => ''],
                      ['name' => 'On-site Laundry ', 'target' => ''],
                      ['name' => 'Washer and Dryer Hookup ', 'target' => ''],
                      ['name' => 'Washer and Dryer ', 'target' => ''],
                      ['name' => 'Covered Carport ', 'target' => ''],
                      ['name' => 'First Floor Unit ', 'target' => ''],
                      ['name' => 'Elevator', 'target' => ''],
                      ['name' => 'Pet Friendly ', 'target' => ''],
                      ['name' => 'Balcony/Patio', 'target' => ''],
                      ['name' => 'Fitness Center/Gym', 'target' => ''],
                      ['name' => 'Central Heating ', 'target' => ''],
                      ['name' => 'Central Air Conditioning ', 'target' => ''],
                      ['name' => 'Fireplace', 'target' => ''],
                      ['name' => 'Walk-in Closet', 'target' => ''],
                      ['name' => 'Hardwood Floors', 'target' => ''],
                      ['name' => 'Tile Floors', 'target' => ''],
                      ['name' => 'Carpet Floors', 'target' => ''],
                      ['name' => 'Security System', 'target' => ''],
                      ['name' => 'Gated Community', 'target' => ''],
                      ['name' => 'HOA Community', 'target' => ''],
                      ['name' => '55 and Over Community', 'target' => ''],
                      ['name' => 'Specific School District ', 'target' => ''],
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
                      ['name' => 'Other', 'target' => '.otherAmenitiesIncome'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Amenities and Property Features:</label>
                  <select class="grid-picker" name="amenities[]" id="amenitiesIncome"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($amenitiesIncome as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherAmenitiesIncome d-none">
                    <label class="fw-bold">Amenities and Property Features:</label>
                    <input type="text" class="form-control has-icon" name="otherAmenities"
                      data-icon="fa-solid fa-ruler-combined" id="otherAmenities" required />
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="36">
              <span class="incomeFields">
                <div class="form-group">
                  <label class="fw-bold">Is the property located in a 55-and-over community?</label>
                  </label>
                  @php
                    $propertyLoc = [
                        ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-check-circle'],
                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                  @endphp
                  <select name="propertyLoc" id="propertyLoc" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($propertyLoc as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="37">
              <span class="incomeFields">
                <div class="form-group">
                  @php
                    $specific_price_income = [
                        [
                            'icon' => 'fa-regular fa-check-circle',
                            'name' => 'Yes',
                            'target' => '.specific_price_income_yes',
                        ],
                        [
                            'icon' => 'fa-regular fa-circle-xmark',
                            'name' => 'No',
                            'target' => '.specific_price_income_no',
                        ],
                    ];
                  @endphp
                  <label class="fw-bold">Does the seller have a specific price in mind that they would
                    like to obtain for their property?</label>
                  <select class="grid-picker" name="seller_specific_price" id="seller_specific_price"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($specific_price_income as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group specific_price_income_yes d-none">
                  <label class="fw-bold">What price would the seller like to obtain for their property?</label>
                  <input type="text" class="form-control has-icon" name="custom_seller_specific_price"
                    data-icon="fa-solid fa-ruler-combined" id="custom_listing_terms" required />
                </div>

                <div class="form-group specific_price_income_no d-none">
                  <label class="fw-bold">
                    How much do you think your property will sell for?
                  </label>
                  @php
                    $expectationsIncome = [
                        ['target' => '', 'name' => '$100k or less'],
                        ['target' => '', 'name' => '$100k-$200k'],
                        ['target' => '', 'name' => '$200k-$300k'],
                        ['target' => '', 'name' => '$300k-$400k'],
                        ['target' => '', 'name' => '$400k-$500k'],
                        ['target' => '', 'name' => '$500k-$600k'],
                        ['target' => '', 'name' => '$600k-$700k'],
                        ['target' => '', 'name' => '$700k-$800k'],
                        ['target' => '', 'name' => '$800-$900k'],
                        ['target' => '', 'name' => '$900k-$1 million'],
                        ['target' => '', 'name' => '$1 million +'],
                        ['target' => '', 'name' => '$2 million +'],
                        ['target' => '', 'name' => '$3 million +'],
                        ['target' => '', 'name' => '$4 million +'],
                        ['target' => '', 'name' => '$5 million +'],
                        ['target' => '', 'name' => '$10 million +'],
                        ['name' => 'Other', 'target' => '.otherSellerPrice'],
                    ];
                  @endphp
                  <select name="expectation" id="expectation" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($expectationsIncome as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherSellerPrice d-none">
                    <label class="fw-bold">How much do you think your property will sell for?</label>
                    <input type="number" class="form-control has-icon" name="otherSellerPrice"
                      data-icon="fa-solid fa-dollar-sign" id="custom_listing_terms" required />
                  </div>
                </div>
                <div class="form-group sellIncomeOther d-none">
                  <label class="fw-bold">How much do you think your property will sell for? </label>
                  <input type="number" name="otherSellProp" data-type="type_of_financing" id="type_of_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                    >
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="38">
              <span class="incomeFields">
                @php
                  $financingsIncome = [
                      ['name' => 'Cash', 'target' => ''],
                      ['name' => 'Conventional', 'target' => ''],
                      ['name' => 'Seller Financing', 'target' => '.custom_seller_financing_income'],
                      ['name' => 'FHA', 'target' => ''],
                      ['name' => 'Jumbo', 'target' => ''],
                      ['name' => 'VA', 'target' => ''],
                      ['name' => 'No-Doc', 'target' => ''],
                      ['name' => 'Non-QM', 'target' => ''],
                      ['name' => 'Assumable', 'target' => '.custom_assumable_income'],
                      ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade_income'],
                      ['name' => 'Lease Option', 'target' => '.lease_option_income'],
                      ['name' => 'Lease Purchase', 'target' => '.lease_purchase_income'],
                      ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency_income'],
                      ['name' => 'NFT', 'target' => '.nftIncome'],
                      ['name' => 'USDA', 'target' => ''],
                      ['name' => 'Other', 'target' => '.custom_financing_income'],
                  ];
                @endphp
                <div class="row align-items-end mt-4 ">
                  <div class="col-md-12">
                    <label class="fw-bold">Select the types of financing/currency that is acceptable to the
                      seller:</label>
                    <div class="select2-parent">
                      <select name="financings[]" class="grid-picker" multiple id="financingIncome" required>
                        @foreach ($financingsIncome as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>

                  </div>
                </div>

                <div class="form-group custom_financing_income d-none ">
                  <label class="fw-bold">What type of financing/currency is acceptable to the seller? </label>
                  <input type="text" name="type_of_financing" data-type="type_of_financing"
                    id="type_of_financing" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                    >
                </div>
                <div class="form-group custom_exchange_trade_income d-none ">
                  @php
                    $tradeIncome = [
                        ['name' => 'Another Home', 'target' => ''],
                        ['name' => 'Vehicle', 'target' => ''],
                        ['name' => 'Boat', 'target' => ''],
                        ['name' => 'Motorhome', 'target' => ''],
                        ['name' => 'Artwork', 'target' => ''],
                        ['name' => 'Jewelry ', 'target' => ''],
                        ['name' => 'Other ', 'target' => '.tradeOtherIncome'],
                    ];
                  @endphp
                  <label class="fw-bold">Acceptable Exchange Item:</label>
                  <select name="trade[]" class="grid-picker" id="">
                    @foreach ($tradeIncome as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group tradeOtherIncome d-none ">
                    <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                    <input type="text" name="tradeOther[]" data-type="" id=""
                      class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                    <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is
                      willing to exchange/trade?</label>
                    <input type="text" name="tradeOther[]" data-type="" id=""
                      class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                    <label class="fw-bold">How much cash does the seller require on top of the Exchange/Trade
                      item?</label>
                    <input type="text" name="tradeOther[]" data-type="" id=""
                      class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                    <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                    <input type="text" name="tradeOther[]" data-type="" id=""
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                </div>
              {{-- Lease Options --}}
              <div class="form-group lease_option_income d-none" >
                <label class="fw-bold">What is the seller's desired offering price for a lease option?</label><br>
                <input type="text" name="leaseOptions[]"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign "
                  >
                <label class="fw-bold">What specific terms does the seller propose for the lease option?</label><br>
                <input type="text" name="leaseOptions[]"
                   class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                  >
                <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                <input type="text" name="leaseOptions[]"
                   class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                  >
                <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label><br>
                <input type="text" name="leaseOptions[]" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                  >
                <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for
                  the lease option?</label><br>
                <input type="text" name="leaseOptions[]"
                   class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                  >
                <label class="fw-bold">Is there a possibility of changes in the offering price during the lease
                  period?</label><br>
                <input type="text" name="leaseOptions[]"
                   class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                  >
                  <div class="form-group">
                    @php
                    $leaseOptRes = [
                        ['name' => 'Yes', 'target' => '.leaseOptionsIncome','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                        ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
                    ];
                  @endphp
                  <label class="fw-bold">Does the seller require an option fee? </label>
                  <select name="trade[]" class="grid-picker" id="">
                    @foreach ($leaseOptRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(33.3% - 10px);"
                        data-icon="{{$item['icon']}}">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group leaseOptionsIncome d-none">
                    <label class="fw-bold">How much is the option fee? </label>
                      <input type="text" name="leaseOptionsRes" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                  </div>
                </div>
              </div>
              {{-- Lease Options --}}
               {{-- Lease Purchase --}}
               <div class="form-group lease_purchase_income d-none" >
                <label class="fw-bold">What is the seller's desired offering price for a lease purchase?</label><br>
                <input type="text" name="leasePurchase[]"
                   class="form-control has-icon" data-icon="fa-solid fa-dollar-sign "
                  >
                <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label><br>
                <input type="text" name="leasePurchase[]"
                   class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                  >
                <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                <input type="text" name="leasePurchase[]"
                   class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                  >
                <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label><br>
                <input type="text" name="leasePurchase[]"
                   class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                  >
                <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for
                  the lease purchase?</label><br>
                <input type="text" name="leasePurchase[]"
                   class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                  >
                <label class="fw-bold"></label><br>
                <label class="fw-bold">Is there a possibility of changes in the offering price during the lease
                    period?</label><br>
                    <input type="text" name="leasePurchase[]"
                     class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                    >
                <div class="form-group">
                    @php
                    $leasePurchaseOpt = [
                        ['name' => 'Yes', 'target' => '.leasePurchaseYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                        ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
                    ];
                  @endphp
                  <label class="fw-bold">Does the seller require an option fee? </label>
                  <select name="trade[]" class="grid-picker" id="">
                    @foreach ($leasePurchaseOpt as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(33.3% - 10px);"
                        data-icon="{{$item['icon']}}">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group leasePurchaseYes d-none">
                    <label class="fw-bold">How much is the option fee? </label>
                      <input type="text" name="leasePurchaseYes" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                  </div>
                </div>

              </div>
              {{-- Lease Purchase --}}
                {{-- Seller  --}}
                <div class="form-group custom_seller_financing_income d-none ">
                  <div class="row">
                    <div class="col-4 form-group">
                      <label class="fw-bold">Purchase Price:</label>
                      <input type="text" name="sellerFinancing[]" value=""
                        data-target="{{ $item['target'] }}" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" />
                    </div>
                    <div class="col-4 form-group">
                      <label class="fw-bold">Down Payment:</label>
                      <input type="text" name="sellerFinancing[]" value=""
                        data-target="{{ $item['target'] }}" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" />
                    </div>
                    <div class="col-4 form-group">
                      <label class="fw-bold">Seller Financing Amount:</label>
                      <input type="text" name="sellerFinancing[]" value=""
                        data-target="{{ $item['target'] }}" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" />
                    </div>
                    <div class="col-4 form-group">
                      <label class="fw-bold">Interest Rate:</label>
                      <input type="text" name="sellerFinancing[]" value=""
                        data-target="{{ $item['target'] }}" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" />
                    </div>
                    <div class="col-4 form-group">
                      <label class="fw-bold">Term:</label>
                      <input type="text" name="sellerFinancing[]" value=""
                        data-target="{{ $item['target'] }}" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" />
                    </div>
                    <div class="col-4 form-group">
                      <label class="fw-bold">Monthly Payments:</label>
                      <input type="text" name="sellerFinancing[]" value=""
                        data-target="{{ $item['target'] }}" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" />
                    </div>
                    <div class="col-4 form-group">
                      <label class="fw-bold">Closing Costs:</label>
                      <input type="text" name="sellerFinancing[]" value=""
                        data-target="{{ $item['target'] }}" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" />
                    </div>
                    {{-- PrePayement --}}
                    @php
                      $PrepaymentIncome = [
                          [
                              'name' => 'Yes',
                              'target' => '.prepaymentOtherIncome',
                              'icon' => '<i class="fa-regular fa-circle-check"></i>',
                          ],
                          ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                      ];
                    @endphp
                    <div class="select2-parent prepaymentIncome d-none">
                      <br><label class="fw-bold">Prepayment Penalty: </label>
                      <select name="prepayment" class="grid-picker" id="">
                        @foreach ($PrepaymentIncome as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='{{ $item['icon'] }}'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group prepaymentOtherIncome d-none ">
                        <label class="fw-bold">What is the prepayment penalty amount? </label>
                        <input type="texts" name="prepaymentOther[]" data-type="custom_timeframe" id=""
                          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                      </div>
                    </div>
                    {{-- Prepayment --}}
                    {{-- Balloon Payment --}}
                    @php
                      $balloonIncome = [
                          [
                              'name' => 'Yes',
                              'target' => '.balloonpaymentOtherIncome',
                              'icon' => '<i class="fa-regular fa-circle-check"></i>',
                          ],
                          ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                      ];
                    @endphp
                    <div class="select2-parent balloonpaymentIncome d-none">
                      <br><label class="fw-bold">Balloon Payment:</label>
                      <select name="balloon" class="grid-picker" id="">
                        @foreach ($balloonIncome as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='{{ $item['icon'] }}'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group balloonpaymentOtherIncome d-none ">
                        <label class="fw-bold">How much is the balloon payment? </label>
                        <input type="text" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign" id=""
                          class="form-control has-icon" required>
                        <label class="fw-bold">When is the balloon payment due? </label>
                        <input type="text" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign" id=""
                          class="form-control has-icon" required>
                      </div>
                    </div>
                    {{-- Balloon Payment --}} {{-- PrePayement --}}
                    @php
                      $PrepaymentIncome = [
                          [
                              'name' => 'Yes',
                              'target' => '.prepaymentOtherIncome',
                              'icon' => '<i class="fa-regular fa-circle-check"></i>',
                          ],
                          ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                      ];
                    @endphp
                    <div class="select2-parent ">
                      <br><label class="fw-bold">Prepayment Penalty: </label>
                      <select name="prepayment" class="grid-picker" id="">
                        @foreach ($PrepaymentIncome as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='{{ $item['icon'] }}'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group prepaymentOtherIncome d-none ">
                        <label class="fw-bold">What is the prepayment penalty amount? </label>
                        <input type="texts" name="prepaymentOther[]" data-type="custom_timeframe" id=""
                          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                      </div>
                    </div>
                    {{-- Prepayment --}}
                    {{-- Balloon Payment --}}
                    @php
                      $balloonIncome = [
                          [
                              'name' => 'Yes',
                              'target' => '.balloonpaymentOtherIncome',
                              'icon' => '<i class="fa-regular fa-circle-check"></i>',
                          ],
                          ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                      ];
                    @endphp
                    <div class="select2-parent">
                      <br><label class="fw-bold">Balloon Payment:</label>
                      <select name="balloon" class="grid-picker" id="">
                        @foreach ($balloonIncome as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='{{ $item['icon'] }}'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group balloonpaymentOtherIncome d-none ">
                        <label class="fw-bold">How much is the balloon payment? </label>
                        <input type="text" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign" id=""
                          class="form-control has-icon" required>
                        <label class="fw-bold">When is the balloon payment due? </label>
                        <input type="text" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign" id=""
                          class="form-control has-icon" required>
                      </div>
                    </div>
                    {{-- Balloon Payment --}}
                  </div>
                </div>
                {{-- Assumable --}}
                <div class="form-group custom_assumable_income d-none ">
                  <label class="fw-bold">What assumable terms are being offered?</label>
                  <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                    class=" form-control has-icon" data-icon="fa-solid fa-ruler-combined" />
                  <label class="fw-bold">Are there any restrictions or qualifications for a buyer assuming the existing
                    financing?</label>
                  <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                    class=" form-control has-icon" data-icon="fa-solid fa-ruler-combined" />
                  <label class="fw-bold">Is there any outstanding balance on the existing financing that the buyer
                    would need to cover?</label>
                  <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                    class=" form-control has-icon" data-icon="fa-solid fa-ruler-combined" />
                </div>
                {{-- Assumable --}}
                {{-- Cryptcurrency --}}
                <div class="form-group custom_cryptocurrency_income d-none ">
                    <label class="fw-bold">What type of cryptocurrency will the seller accept? </label><br>
                    <input type="text" name="cryptocurrency[]" data-type="" id=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                    >
                  <label class="fw-bold">What percentage of the purchase price will the seller accept in
                    cryptocurrency?</label><br>
                  <input type="text" name="cryptocurrency[]" data-type="" id=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                    >
                  <label class="fw-bold">What percentage of the purchase price will the seller accept in
                    cash?</label><br>
                  <input type="text" name="cryptocurrency[]" data-type="" id=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                    >
                  <label class="">Note: Cryptocurrency can be converted to cash at closing. </label>

                </div>
                {{-- NFT --}}
                <div class="form-group nftIncome d-none ">
                  <label class="fw-bold">What type of NFT will the seller accept?</label><br>
                  <input type="text" name="nft[]" data-type="" id=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined "
                    >
                  <label class="fw-bold">What percentage of the sales price will the seller accept in the form of a Non-Fungible Token (NFT)? </label><br>
                  <input type="text" name="nft[]" data-type="" id=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                    >
                  <label class="fw-bold">What percentage of the purchase price will the seller accept in
                    cash?</label><br>
                  <input type="text" name="nft[]" data-type="" id=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                    >

                </div>
                {{-- NFT --}}
              </span>
            </div>
            <div class="wizard-step" data-step="39">
              <span class="incomeFields">
                <div class="form-group">
                  <label class="fw-bold">
                    When will the property be ready to list?
                  </label>
                  @php
                    $timeframesIncome = [
                        ['name' => 'Now', 'target' => ''],
                        ['name' => '1 Month', 'target' => ''],
                        ['name' => '2 Months', 'target' => ''],
                        ['name' => '3 Months', 'target' => ''],
                        ['name' => '4 Months', 'target' => ''],
                        ['name' => '5 Months', 'target' => ''],
                        ['name' => '6 Months', 'target' => ''],
                        ['name' => 'Over 6 Months', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_timeframe_income'],
                    ];
                  @endphp
                  <select name="selling_timeframe" id="selling_timeframe" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($timeframesIncome as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_timeframe_income  d-none">
                  <label class="fw-bold">When will the property be ready to list? </label>
                  <input type="texts" name="custom_timeframe" data-type="custom_timeframe" id="custom_timeframe"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group prompt_response d-none">
                  <p class="text-danger">This is a service designed for sellers who are seeking to sell
                    their property within three Months or less.</p>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="40">
              <span class="incomeFields">
                <div class="form-group">
                  <label class="fw-bold">
                    What is the timeframe offered to the agent in the Seller Agency Agreement?
                  </label>
                  @php
                    $listing_terms_income = [
                        ['name' => '3 Months', 'target' => ''],
                        ['name' => '6 Months', 'target' => ''],
                        ['name' => '9 Months', 'target' => ''],
                        ['name' => '12 Months', 'target' => ''],
                        ['name' => 'Negotiable', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_listing_terms_income'],
                    ];
                  @endphp
                  <select name="listing_term" id="listing_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($listing_terms_income as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_listing_terms_income d-none">
                  <label class="fw-bold">What is the timeframe offered to the agent in the Seller Agency
                    Agreement?</label>
                  <input type="text" class="form-control has-icon" placeholder="" name="custom_listing_terms"
                    data-icon="fa-solid fa-ruler-combined" id="custom_listing_terms" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="41">
              <span class="incomeFields">
                <div class="form-group ">
                  <label class="fw-bold">
                    What is the total commission being offered to the listing agent?
                  </label>
                  @php
                    $offered_commissionsIncome = [
                        ['name' => '5%', 'target' => ''],
                        ['name' => '5.5%', 'target' => ''],
                        ['name' => '6%', 'target' => ''],
                        ['name' => 'Negotiable', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_offered_commission_income'],
                    ];
                  @endphp
                  <select name="offered_commission" id="offered_commission" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($offered_commissionsIncome as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_offered_commission_income d-none ">
                  <label class="fw-bold">What is the total commission being offered to the listing agent?</label>
                  <input type="text" class="form-control has-icon" name="custom_offered_commission"
                    data-icon="fa-solid fa-ruler-combined" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="42">
              <span class="incomeFields">
                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What are the most important aspects the seller will consider when hiring a real estate agent?

                    </label>
                    <textarea name="important_aspect" value="income" class="form-control" rows="5" required
                      data-msg-required="This Field is Required">{{ old('important_aspect') }}</textarea>
                  </div>
                </div>

                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What additional details would the seller like to share with the agent?
                    </label>
                    <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5">{{ old('important_info') }}</textarea>
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step commercial_business" data-step="43">
              <span class="incomeFields">
                <div class="form-group ">
                  <label class="fw-bold">
                    Select the services that the seller requests from an agent:
                  </label>
                  @php
                    $services_data = [
                    ['name' => 'Conduct a thorough comparative market analysis (CMA) to determine the property\'s value and pricing strategy.', 'target' => ''],
                    ['name' => 'List the property on the MLS.', 'target' => ''],
                    ['name' => 'List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, to increase the property\'s visibility and exposure.', 'target' => ''],
                    ['name' => 'List the property on Loopnet, a major commercial real estate website.', 'target' => ''],
                    ['name' => 'List the property on Crexi, a major commercial real estate website.', 'target' => ''],
                    ['name' => 'List the property on the Bid Your Offer platform.', 'target' => ''],
                    ['name' => 'Implement an online marketing campaign with a QR code or listing link that leads to the property\'s listing on the BidYourOffer.com platform.', 'target' => ''],
                    ['name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing on the BidYourOffer.com platform.', 'target' => ''],
                    ['name' => 'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.', 'target' => ''],
                    ['name' => 'Distribute postcards featuring the seller\'s listing within their neighborhood with a QR code or listing link leading to the property\'s listing.', 'target' => ''],
                    ['name' => 'Distribute postcards featuring the seller\'s listing to the most opportune buyers with a QR code or listing link leading to the property\'s listing.', 'target' => ''],
                    ['name' => 'Conduct real estate email marketing campaigns that lead to the seller’s listing on the BidYourOffer.com platform.', 'target' => ''],
                    ['name' => 'Provide professional photos to showcase the property\'s best features.', 'target' => ''],
                    ['name' => 'Provide aerial photography to capture the property\'s surroundings and neighborhood.', 'target' => ''],
                    ['name' => 'Provide a professional video to showcase the property\'s interior and exterior.', 'target' => ''],
                    ['name' => 'Provide a 3D tour to showcase the property\'s interior.', 'target' => ''],
                    ['name' => 'Provide a floor plan of the property to showcase its layout and spatial configuration.', 'target' => ''],
                    ['name' => 'Provide virtual staging to enhance the property\'s visual appeal and attract potential buyers.', 'target' => ''],
                    ['name' => 'Provide recommendations for staging professionals.', 'target' => ''],
                    ['name' => 'Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.', 'target' => ''],
                    ['name' => 'Offer expert negotiation skills to secure the best possible terms and price during the selling process.', 'target' => ''],
                    ['name' => 'Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.', 'target' => ''],
                    ['name' => 'Host an Open House(s).', 'target' => ''],
                    ['name' => 'Send email alerts to buyers searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.', 'target' => ''],
                    ['name' => 'Provide guidance and support throughout the entire transaction, from listing to closing, to ensure a seamless and successful sale.', 'target' => ''],
                    ['name' => 'Assist with the completion and submission of all necessary paperwork and documentation related to the sale.', 'target' => ''],
                    ['name' => 'Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.', 'target' => ''],
                    ['name' => 'Provide regular updates on market activity, showings, and feedback from potential buyers.', 'target' => ''],
                    [
                        'name' => 'Other - Add additional services as needed.',
                        'target' => '.commercial_service_income',
                    ],
                ];

                  @endphp
                  <select name="services[]" id="commercial-services" multiple class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($services_data as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row " style="width:calc(100%);"
                        data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group commercial_service_income d-none">
                  <label class="fw-bold">What additional services would the seller like to request from an
                    agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="" name="commercial_servic"
                    data-icon="fa-solid fa-hand-point-right" id="commercial_servic" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="44">
                <h4>For a more personalized listing, you can include a picture of yourself and/or include a video of yourself providing additional information about your background and criteria.</h4>
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
            <div class="wizard-step" data-step="45">
              <span class="commercialFields">
                <div class="form-group">
                  <label class="fw-bold">
                    Special Sale Provision:
                  </label>
                  @php
                    $seller_property_vacant = [
                        [
                            'target' => '.assignment_contract_commercial',
                            'name' => 'Assignment Contract',
                        ],
                        ['target' => '', 'name' => 'Auction'],
                        ['target' => '', 'name' => 'Bank Owned/REO'],
                        ['target' => '', 'name' => 'Government Owned'],
                        ['target' => '', 'name' => 'Probate Listing'],
                        ['target' => '', 'name' => 'Short Sale'],
                        ['target' => '', 'name' => 'None'],
                        ['target' => '.other_sale_vacant', 'name' => 'Other'],
                    ];
                  @endphp
                  <div class="select2-parent">
                    <select name="special_sale" id="special_sale" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($seller_property_vacant as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row " style="width:calc(33.33% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="form-group other_sale_vacant d-none ">
                  <label class="fw-bold" for="other_sale_vacant">Special Sale Provision: </label>
                  <input type="text" name="custom_special_sale" id="custom_special_sale"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>

                <div class="form-group d-none assignment_contract_commercial ">
                  <label class="fw-bold">
                    Is the seller currently under contract with a property they would like to assign?
                  </label>
                  @php
                    $seller_contract_options_vacant = [
                        ['name' => 'Yes', 'target' => '.seller_yes_commercial'],
                        ['name' => 'No', 'target' => '.seller_no_commercial'],
                    ];
                  @endphp
                  <select name="contribute_term" id="contribute_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($seller_contract_options_vacant as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>

                  <div class="form-group seller_yes_commercial d-none ">
                    <label class="fw-bold">What fee would the seller pay the agent to assign the contract? </label>
                    <input type="number" class="form-control has-icon" min="0.5" step=".01"
                      placeholder="" name="seller_yes_vacant" data-icon="fa-solid fa-dollar-sign"
                      id="seller_yes_vacant" required />
                  </div>
                  <div class="form-group seller_no_commercial d-none">
                    <label class="fw-bold">Is the seller looking to take over a buyer’s contract? </label>
                    @php
                      $seller_yes_no_vacant = [['name' => 'Yes', 'target' => ''], ['name' => 'No', 'target' => '']];
                    @endphp
                    <select name="custom_seller_contract_no" id="seller_no_vacant" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($seller_yes_no_vacant as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="46">
              <span class="commercialFields">
                @php
                  $prop_condition_commercial = [
                      ['target' => '', 'name' => 'Pre-Construction'],
                      ['target' => '', 'name' => 'Currently Being Built'],
                      ['target' => '', 'name' => 'New Construction'],
                      [
                          'name' => 'Completely Updated: No updates needed',
                          'target' => '',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Semi-updated: Needs minor updates',
                          'target' => '',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Not Updated: Requires a complete update',
                          'target' => '',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Tear Down: Requires complete demolition and reconstruction',
                          'target' => '',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      ['name' => 'Other', 'target' => '.other_prop_commercial', 'icon' => 'fa-regular fa-circle-check'],
                  ];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">
                    Property Condition:
                  </label>
                  <select class="grid-picker" name="prop_condition" id="prop_condition"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($prop_condition_commercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group  other_prop_commercial   d-none" id="">
                  <label class="fw-bold">Property Condition:  </label>
                  <input type="text" name="custom_prop_condition" id="custom_prop_condition"
                    class="form-control has-icon  " data-icon="fa-solid fa-ruler-combined"
                    data-msg-required="Property Condition" placeholder="">
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="47">
              <span class="commercialFields">

                @php
                  $bathroomsCommercial = [
                      ['name' => '1', 'target' => ''],
                      ['name' => '1.5', 'target' => ''],
                      ['name' => '2', 'target' => ''],
                      ['name' => '2.5', 'target' => ''],
                      ['name' => '3', 'target' => ''],
                      ['name' => '3.5', 'target' => ''],
                      ['name' => '4', 'target' => ''],
                      ['name' => '4.5', 'target' => ''],
                      ['name' => '5', 'target' => ''],
                      ['name' => '6', 'target' => ''],
                      ['name' => '7', 'target' => ''],
                      ['name' => '8', 'target' => ''],
                      ['name' => '9', 'target' => ''],
                      ['name' => '10', 'target' => ''],
                      ['name' => 'Other', 'target' => '.other_bathrooms_commercial'],
                  ];
                @endphp
                <div class="form-group  ">
                  <label class="fw-bold">Bathrooms:</label>
                  <select class="grid-picker" name="bathrooms" id="bathrooms" style="justify-content: center;"
                    required>
                    <option value="">Select</option>
                    @foreach ($bathroomsCommercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-solid fa-bath"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group other_bathrooms_commercial d-none ">
                  <label class="fw-bold" for="other_bathrooms">Bathrooms:</label>
                  <input type="text" name="other_bathrooms" id="other_bathrooms" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-bath" data-msg-required="Please enter "
                    required>
                </div>
              </span>

            </div>
            <div class="wizard-step" data-step="48">
              <span class="commercialFields">
                <div class="form-group ">
                  <label class="fw-bold">What is the heated square footage of the property?</label>
                  <input type="text" name="heated_square" data-type="heated_square_footage"
                    data-icon="fa-solid fa-ruler-combined" class="form-control has-icon" required>
                </div>
                <div class="form-group">
                  @php
                    $sqftsCommercial = [
                        ['name' => 'Appraisal', 'target' => ''],
                        ['name' => 'Building', 'target' => ''],
                        ['name' => 'Measure', 'target' => ''],
                        ['name' => 'Owner Provided', 'target' => ''],
                        ['name' => 'Public Records', 'target' => ''],
                        ['name' => 'Other', 'target' => '.otherSqftsCommercial'],
                    ];
                  @endphp
                  <label class="fw-bold">Sqft Heated Source: </label>
                  <select class="grid-picker" name="sqft" id="appliances" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($sqftsCommercial as $item)
                      <option value="{{ $item['name'] }}" data-icon='<i class="fa-regular fa-check-circle"></i>'
                        data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherSqftsCommercial  d-none">
                    <label class="fw-bold">Sqft Heated Source:</label>
                    <input type="text" name="other_heated" data-type="heated_square_footage" placeholder=""
                      id="heated_square_footage" class="form-control has-icon"
                      data-msg-required="Please Enter Heated Square Footage" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group ">
                    <label class="fw-bold">Total Sqft:  </label>
                    <input type="text" name="totalSqft" data-type="heated_square_footage"
                      data-icon="fa-solid fa-ruler-combined" class="form-control has-icon" required>
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="49">
              <span class="commercialFields">
                @php
                  $acreagesCommercial = [
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

                <div class="form-group vacant_land_hide ">
                  <label class="fw-bold">Total Acreage:  </label>
                  <select class="grid-picker" name="total_acreage" id="total_acreage"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($acreagesCommercial as $item)
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
            <div class="wizard-step" data-step="50">
              <span class="commercialFields">
                <div class="form-group commercial_hide ">
                  @php
                    $garageCommercial = [
                        ['target' => '.garageYesCommercial', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                        ['target' => '.garageNoCommercial', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                  @endphp
                  <label class="fw-bold">Garage/Parking Features:</label>
                  <select name="garageCommercial" class="grid-picker" id="garage"
                    style="justify-content: flex-start;" required>
                    @foreach ($garageCommercial as $item)
                      <option value="{{ $item['name'] }}" data-icon='<i class="{{ $item['icon'] }}"></i>'
                        data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  @php
                    $garageParkingCommercial = [
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
                        ['name' => 'Other', 'target' => '.other_garage_commercial'],
                    ];
                  @endphp
                  <div class="form-group d-none garageYesCommercial ">
                    <select class="grid-picker" name="garage_parking" style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($garageParkingCommercial as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(25% - 10px);"
                          data-icon='<i class="fa-solid fa-warehouse"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group other_garage_commercial d-none ">
                      <label class="fw-bold" for="other_garage">Garage/Parking Features :</label>
                      <input type="text" name="other_garage" class="form-control has-icon hide_arrow"
                        data-icon="fa-solid fa-warehouse" required>
                    </div>
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="51">
              <span class="commercialFields">

                @php
                  $viewCommercial = [
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
                      ['name' => 'Other', 'target' => '.otherViewCommercial'],
                  ];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">View:</label>
                  <select class="grid-picker" name="view[]" id="appliances" style="justify-content: flex-start;"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($viewCommercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherViewCommercial  d-none">
                    <label class="fw-bold">View:</label>
                    <input type="text" class="form-control has-icon" name="otherView" id="otherView"
                      data-icon="fa-solid fa-ruler-combined" required />
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="52">
              <span class="commercialFields">
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
                      ['name' => 'Other', 'target' => '.other_appliances_income'],
                  ];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">Appliances: </label>
                  <select class="grid-picker" name="appliances[]" id="appliances"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($appliances as $appliance)
                      <option value="{{ $appliance['name'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'
                        data-target="{{ $appliance['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $appliance['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group other_appliances_income d-none ">
                    <label class="fw-bold">Appliances:</label>
                    <input type="text" class="form-control has-icon" name="custom_appliances" id="otherView"
                      required data-icon="fa-solid fa-ruler-combined" />
                  </div>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Touchless Faucet: </label>
                  <input type="text" class="form-control has-icon" name="faucet"
                    required data-icon="fa-solid fa-ruler-combined" />
                </div>
              </span>
            </div>
            <div class="wizard-step business_show" data-step="53">
              @php
                $purchase_of_business = [
                    ['name' => 'Real Estate Building and Business', 'target' => ''],
                    ['name' => 'Business Only ', 'target' => ''],
                ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Does the purchase of the business include the real estate building and business,
                  or is it solely the business? </label>
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
            <div class="wizard-step" data-step="54">
              <span class="commercialFields">

                @php
                  $amenitiesCommercial = [
                      ['name' => 'Parking Spaces', 'target' => ''],
                      ['name' => 'Loading Dock', 'target' => ''],
                      ['name' => 'Warehouse Space', 'target' => ''],
                      ['name' => 'Office Space', 'target' => ''],
                      ['name' => 'Conference Room', 'target' => ''],
                      ['name' => 'Kitchenette/Break Room', 'target' => ''],
                      ['name' => 'Restrooms', 'target' => ''],
                      ['name' => 'Elevator', 'target' => ''],
                      ['name' => 'Handicap Accessibility ', 'target' => ''],
                      ['name' => 'Security System ', 'target' => ''],
                      ['name' => 'On-site Maintenance ', 'target' => ''],
                      ['name' => 'On-site Management ', 'target' => ''],
                      ['name' => 'Outdoor Space/Garden ', 'target' => ''],
                      ['name' => 'Signage Opportunities ', 'target' => ''],
                      ['name' => 'High-Speed Internet ', 'target' => ''],
                      ['name' => 'Utilities Included ', 'target' => ''],
                      ['name' => 'HVAC System ', 'target' => ''],
                      ['name' => 'Natural Lighting ', 'target' => ''],
                      ['name' => 'Storage Space ', 'target' => ''],
                      ['name' => 'Open Floor Plan ', 'target' => ''],
                      ['name' => 'Retail Frontage ', 'target' => ''],
                      ['name' => 'Restaurant Space ', 'target' => ''],
                      ['name' => 'Industrial Features ', 'target' => ''],
                      ['name' => 'Flexibility for Renovations ', 'target' => ''],
                      ['name' => 'Common Areas ', 'target' => ''],
                      ['name' => 'Business Center ', 'target' => ''],
                      ['name' => 'Gym/Fitness Facilities ', 'target' => ''],
                      ['name' => 'Lounge Area ', 'target' => ''],
                      ['name' => 'Reception Area ', 'target' => ''],
                      ['name' => 'Security Guard ', 'target' => ''],
                      ['name' => 'Fire Safety Systems ', 'target' => ''],
                      ['name' => 'Energy-Efficient Features ', 'target' => ''],
                      ['name' => 'Green Building Certification ', 'target' => ''],
                      ['name' => 'Access to Public Transportation ', 'target' => ''],
                      ['name' => 'Proximity to Highways ', 'target' => ''],
                      ['name' => 'Visibility from Main Road ', 'target' => ''],
                      ['name' => 'Other ', 'target' => '.otherAmenitiesCommercial'],
                  ];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">Amenities and Property Features:</label>
                  <select class="grid-picker" name="amenities[]" style="justify-content: flex-start;" multiple
                    required>
                    <option value="">Select</option>
                    @foreach ($amenitiesCommercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherAmenitiesCommercial d-none ">
                    <label class="fw-bold">Amenities and Property Features:</label>
                    <input type="text" class="form-control has-icon" name="otherAmenities"
                      data-icon="fa-solid fa-ruler-combined" id="otherAmenities" required />
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="55">
              <span class="commercialFields">

                @php
                  $occupant_types_commercial = [
                      ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'Owner-Occupied', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                      ['name' => 'Tenant-Occupied', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                  ];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">What is the current occupancy status of the property?</label>
                  <select class="grid-picker" name="occupant_type" id="occupant_type"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($occupant_types_commercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="form-group select_your_commercial_and_business  d-none">
                  <label class="fw-bold">
                    How much do you think your property will sell for?
                  </label>
                  @php
                    $propSellCommercial = [
                        ['name' => '$100k or less'],
                        ['name' => '$100k-$200k'],
                        ['name' => '$200k-$300k'],
                        ['name' => '$300k-$400k'],
                        ['name' => '$400k-$500k'],
                        ['name' => '$500k-$600k'],
                        ['name' => '$600k-$700k'],
                        ['name' => '$700k-$800k'],
                        ['name' => '$800-$900k'],
                        ['name' => '$900k-$1 million'],
                        ['name' => '$1 million +'],
                        ['name' => '$2 million +'],
                        ['name' => '$3 million +'],
                        ['name' => '$4 million +'],
                        ['name' => '$5 million +'],
                        ['name' => '$10 million +'],
                        ['target' => '.otherSellerPriceCom', 'name' => 'Other'],
                    ];
                  @endphp
                  <select name="expectation" id="expectation" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($propSellCommercial as $item)
                      <option value="{{ $item['name'] }}" data-target="" class="card flex-column"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherSellerPriceCom d-none">
                    <label class="fw-bold">How much do you think your property will sell for?</label>
                    <input type="number" class="form-control has-icon" name="otherSellerPrice"
                      data-icon="fa-solid fa-dollar-sign" id="custom_listing_terms" required />
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="56">
              <span class="commercialFields">
                <div class="form-group ">
                  @php
                    $specific_price_commercial = [
                        [
                            'icon' => 'fa-regular fa-check-circle',
                            'name' => 'Yes',
                            'target' => '.specific_price_commercial_yes',
                        ],
                        [
                            'icon' => 'fa-regular fa-circle-xmark',
                            'name' => 'No',
                            'target' => '.specific_price_commercial_no',
                        ],
                    ];
                  @endphp
                  <label class="fw-bold">Does the seller have a specific price in mind that they would
                    like to obtain for their property?</label>
                  <select class="grid-picker" name="seller_specific_price" id="seller_specific_price"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($specific_price_commercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group specific_price_commercial_other   d-none">
                  <label class="fw-bold">How much do you think your property will sell for?</label>
                  <input type="text" class="form-control has-icon" name="custom_seller_specific_other"
                    data-icon="fa-solid fa-ruler-combined" id="custom_listing_terms" required />
                </div>
                <div class="form-group specific_price_commercial_yes   d-none">
                  <label class="fw-bold">What price would the seller like to obtain for their property?</label>
                  <input type="text" class="form-control has-icon" name="custom_seller_specific_price"
                    data-icon="fa-solid fa-ruler-combined" id="custom_listing_terms" required />
                </div>
                <div class="form-group specific_price_commercial_no  d-none">
                  <label class="fw-bold">
                    How much do you think your property will sell for?
                  </label>
                  @php
                    $expectationsCommercial = [
                        ['target' => '', 'name' => '$100k or less'],
                        ['target' => '', 'name' => '$100k-$200k'],
                        ['target' => '', 'name' => '$200k-$300k'],
                        ['target' => '', 'name' => '$300k-$400k'],
                        ['target' => '', 'name' => '$400k-$500k'],
                        ['target' => '', 'name' => '$500k-$600k'],
                        ['target' => '', 'name' => '$600k-$700k'],
                        ['target' => '', 'name' => '$700k-$800k'],
                        ['target' => '', 'name' => '$800-$900k'],
                        ['target' => '', 'name' => '$900k-$1 million'],
                        ['target' => '', 'name' => '$1 million +'],
                        ['target' => '', 'name' => '$2 million +'],
                        ['target' => '', 'name' => '$3 million +'],
                        ['target' => '', 'name' => '$4 million +'],
                        ['target' => '', 'name' => '$5 million +'],
                        ['target' => '', 'name' => '$10 million +'],
                        ['target' => '', 'name' => '$5 million +'],
                        ['target' => '.otherSellerPriceComm', 'name' => 'Other'],
                    ];
                  @endphp
                  <select name="expectation" id="expectation" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($expectationsCommercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherSellerPriceComm d-none">
                    <label class="fw-bold">How much do you think your63 property will sell for?</label>
                    <input type="number" class="form-control has-icon" name="otherSellerPrice"
                      data-icon="fa-solid fa-dollar-sign" id="custom_listing_terms" required />
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="57">
                <span class="commercialFields">
                  @php
                    $financings = [
                        ['name' => 'Cash', 'target' => ''],
                        ['name' => 'Conventional', 'target' => ''],
                        ['name' => 'Seller Financing', 'target' => '.custom_seller_financing_commercial'],
                        ['name' => 'FHA', 'target' => ''],
                        ['name' => 'Jumbo', 'target' => ''],
                        ['name' => 'VA', 'target' => ''],
                        ['name' => 'No-Doc', 'target' => ''],
                        ['name' => 'Non-QM', 'target' => ''],
                        ['name' => 'Assumable', 'target' => '.custom_assumable_commercial'],
                        ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade_commercial'],
                        ['name' => 'Lease Option', 'target' => '.lease_option_commercial'],
                        ['name' => 'Lease Purchase', 'target' => '.lease_purchase_commercial'],
                        ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency_commercial'],
                        ['name' => 'NFT', 'target' => '.nftCommercial'],
                        ['name' => 'USDA', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_financing_commercial'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4 ">
                    <div class="col-md-12">
                      <label class="fw-bold">Select the types of financing/currency that is acceptable to the
                        seller:</label>
                      <div class="select2-parent">
                        <select name="financings[]" class="grid-picker" multiple id="financingOptions" required>
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
                  <div class="form-group custom_financing_commercial d-none ">
                    <label class="fw-bold">What type of financing/currency is acceptable to the seller? </label>
                    <input type="text" name="type_of_financing" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                  </div>
                  <div class="form-group custom_exchange_trade_commercial d-none ">
                    @php
                      $tradeRes = [
                          ['name' => 'Another Home', 'target' => ''],
                          ['name' => 'Vehicle', 'target' => ''],
                          ['name' => 'Boat', 'target' => ''],
                          ['name' => 'Motorhome', 'target' => ''],
                          ['name' => 'Artwork', 'target' => ''],
                          ['name' => 'Jewelry ', 'target' => ''],
                          ['name' => 'Other ', 'target' => '.tradeOtherRes'],
                      ];
                    @endphp
                    <label class="fw-bold">Acceptable Exchange Item:</label>
                    <select name="trade[]" class="grid-picker" id="">
                      @foreach ($tradeRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column " style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group tradeOtherRes d-none ">
                      <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is
                        willing to exchange/trade?</label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      <label class="fw-bold">How much cash does the seller require on top of the Exchange/Trade
                        item?</label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                    </div>
                  </div>
                  {{-- Lease Options --}}
                  <div class="form-group lease_option_commercial d-none" >
                    <label class="fw-bold">What is the seller's desired offering price for a lease option?</label><br>
                    <input type="text" name="leaseOptions[]"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign "
                      >
                    <label class="fw-bold">What specific terms does the seller propose for the lease option?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label><br>
                    <input type="text" name="leaseOptions[]" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for
                      the lease option?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">Is there a possibility of changes in the offering price during the lease
                      period?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                      <div class="form-group">
                        @php
                        $leaseOptRes = [
                            ['name' => 'Yes', 'target' => '.leaseOptionsRes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                            ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <label class="fw-bold">Does the seller require an option fee? </label>
                      <select name="trade[]" class="grid-picker" id="">
                        @foreach ($leaseOptRes as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon="{{$item['icon']}}">
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group leaseOptionsRes d-none">
                        <label class="fw-bold">How much is the option fee? </label>
                          <input type="text" name="leaseOptionsRes" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      </div>
                    </div>
                  </div>
                  {{-- Lease Options --}}
                   {{-- Lease Purchase --}}
                   <div class="form-group lease_purchase_commercial d-none" >
                    <label class="fw-bold">What is the seller's desired offering price for a lease purchase?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign "
                      >
                    <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for
                      the lease purchase?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold"></label><br>
                    <label class="fw-bold">Is there a possibility of changes in the offering price during the lease
                        period?</label><br>
                        <input type="text" name="leasePurchase[]"
                         class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                        >
                    <div class="form-group">
                        @php
                        $leasePurchaseOpt = [
                            ['name' => 'Yes', 'target' => '.leasePurchaseYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                            ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <label class="fw-bold">Does the seller require an option fee? </label>
                      <select name="trade[]" class="grid-picker" id="">
                        @foreach ($leasePurchaseOpt as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon="{{$item['icon']}}">
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group leasePurchaseYes d-none">
                        <label class="fw-bold">How much is the option fee? </label>
                          <input type="text" name="leasePurchaseYes" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      </div>
                    </div>

                  </div>
                  {{-- Lease Purchase --}}
                  {{-- Seller  --}}
                  <div class="form-group custom_seller_financing_commercial d-none ">
                    <div class="row">
                      <div class="col-4 form-group">
                        <label class="fw-bold">Purchase Price:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Down Payment:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Seller Financing Amount:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Interest Rate:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-ruler-combined" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Term:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-ruler-combined" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Monthly Payments:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Closing Costs:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      {{-- PrePayement --}}
                      @php
                        $PrepaymentRes = [
                            [
                                'name' => 'Yes',
                                'target' => '.prepaymentOtherRes',
                                'icon' => '<i class="fa-regular fa-circle-check"></i>',
                            ],
                            ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <div class="select2-parent">
                        <br><label class="fw-bold">Prepayment Penalty: </label>
                        <select name="prepayment" class="grid-picker" id="">
                          @foreach ($PrepaymentRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='{{ $item['icon'] }}'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group prepaymentOtherRes d-none ">
                          <label class="fw-bold">What is the prepayment penalty amount? </label>
                          <input type="texts" name="prepaymentOther[]" data-type="custom_timeframe" id=""
                            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                        </div>
                      </div>
                      {{-- Prepayment --}}
                      {{-- Balloon Payment --}}
                      @php
                        $balloonRes = [
                            [
                                'name' => 'Yes',
                                'target' => '.balloonpaymentOtherRes',
                                'icon' => '<i class="fa-regular fa-circle-check"></i>',
                            ],
                            ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <div class="select2-parent">
                        <br><label class="fw-bold">Balloon Payment:</label>
                        <select name="balloon" class="grid-picker" id="">
                          @foreach ($balloonRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='{{ $item['icon'] }}'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group balloonpaymentOtherRes d-none ">
                          <label class="fw-bold">How much is the balloon payment? </label>
                          <input type="text" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign" id=""
                            class="form-control has-icon" required>
                          <label class="fw-bold">When is the balloon payment due? </label>
                          <input type="text" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign" id=""
                            class="form-control has-icon" required>
                        </div>
                      </div>
                      {{-- Balloon Payment --}}
                    </div>
                  </div>
                  {{-- Assumable --}}
                  <div class="form-group custom_assumable_commercial d-none ">
                    <label class="fw-bold">What assumable terms are being offered?</label>
                    <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" />
                    <label class="fw-bold">Are there any restrictions or qualifications for a buyer assuming the existing
                      financing?</label>
                    <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" />
                    <label class="fw-bold">Is there any outstanding balance on the existing financing that the buyer
                      would need to cover?</label>
                    <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" />

                     <div class="form-group">
                        @php
                        $assumableOpt=[
                          ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.assumableYes'],
                          ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                            ]
                        @endphp
                        <select name="assumable[]" class="grid-picker" id="">
                        @foreach ($assumableOpt as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='{{ $item['icon'] }}'>
                            {{ $item['name'] }}
                            </option>
                        @endforeach
                        </select>
                        <div class="form-group assumableYes d-none ">
                        <label class="fw-bold">What is the outstanding balance on the existing financing that the buyer would need to cover? </label>
                        <input type="text" name="assumableYes" data-icon="fa-solid fa-dollar-sign" id=""
                            class="form-control has-icon" required>
                        </div>
                     </div>
                  </div>
                  {{-- Assumable --}}
                  {{-- Cryptcurrency --}}
                  <div class="form-group custom_cryptocurrency_commercial d-none ">
                    <label class="fw-bold">What type of cryptocurrency will the seller accept?</label><br>
                        <input type="text" name="cryptocurrency[]"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        >
                    <label class="fw-bold">What percentage of the purchase price will the seller accept in
                        cryptocurrency?</label><br>
                        <input type="text" name="cryptocurrency[]"
                         class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        >
                    <label class="fw-bold">What percentage of the purchase price will the seller accept in
                      cash?</label><br>
                    <input type="text" name="cryptocurrency[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="">Note: Cryptocurrency can be converted to cash at closing. </label>

                  </div>
                  {{-- NFT --}}
                  <div class="form-group nftCommercial d-none ">
                    <label class="fw-bold">What type of NFT will the seller accept?</label><br>
                    <input type="text" name="nft[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined "
                      >
                    <label class="fw-bold">What percentage of the sales price will the seller accept in the form of a Non-Fungible Token (NFT)? </label><br>
                    <input type="text" name="nft[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">What percentage of the purchase price will the seller accept in
                      cash?</label><br>
                    <input type="text" name="nft[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >

                  </div>
                  {{-- NFT --}}
                </span>
              </div>
            <div class="wizard-step" data-step="58">
              <span class="commercialFields">
                <div class="form-group ">
                  <label class="fw-bold">
                    When will the property be ready to list?
                  </label>
                  @php
                    $timeframesCommercial = [
                        ['name' => 'Now', 'target' => ''],
                        ['name' => '1 Month', 'target' => ''],
                        ['name' => '2 Months', 'target' => ''],
                        ['name' => '3 Months', 'target' => ''],
                        ['name' => '4 Months', 'target' => ''],
                        ['name' => '5 Months', 'target' => ''],
                        ['name' => '6 Months', 'target' => ''],
                        ['name' => 'Over 6 Months', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_timeframe_commercial'],
                    ];
                  @endphp
                  <select name="selling_timeframe" id="selling_timeframe" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($timeframesCommercial as $item)
                      @php
                        if ($item['name'] == 'Other') {
                            $target = '.custom_timeframe_residential';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_timeframe_commercial  d-none">
                  <label class="fw-bold">When will the property be ready to list? </label>
                  <input type="texts" name="custom_timeframe" data-type="custom_timeframe" id="custom_timeframe"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group prompt_response d-none">
                  <p class="text-danger">This is a service designed for sellers who are seeking to sell
                    their property within three Months or less.</p>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="59">
              <span class="commercialFields">
                <div class="form-group ">
                  <label class="fw-bold">
                    What is the timeframe offered to the agent in the Seller Agency Agreement?
                  </label>
                  @php
                    $listing_terms_commercial = [
                        ['name' => '3 Months', 'target' => ''],
                        ['name' => '6 Months', 'target' => ''],
                        ['name' => '9 Months', 'target' => ''],
                        ['name' => '12 Months', 'target' => ''],
                        ['name' => 'Negotiable', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_listing_terms_commercial_and_business'],
                    ];
                  @endphp
                  <select name="listing_term" id="listing_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($listing_terms_commercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_listing_terms_commercial_and_business  d-none">
                  <label class="fw-bold">What is the timeframe offered to the agent in the Seller Agency
                    Agreement?</label>
                  <input type="text" class="form-control has-icon" placeholder="" name="custom_listing_terms"
                    data-icon="fa-solid fa-ruler-combined" id="custom_listing_terms" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="60">
              <span class="commercialFields">
                <div class="form-group ">
                  <label class="fw-bold">
                    What is the total commission being offered to the listing agent?
                  </label>
                  @php
                    $offered_commissions = [
                        ['name' => '5%', 'target' => ''],
                        ['name' => '5.5%', 'target' => ''],
                        ['name' => '6%', 'target' => ''],
                        ['name' => 'Negotiable', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_offered_commission_commercial_and_business'],
                    ];
                  @endphp
                  <select name="offered_commission" id="offered_commission" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($offered_commissions as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_offered_commission_commercial_and_business  d-none">
                  <label class="fw-bold">What is the total commission being offered to the listing agent?</label>
                  <input type="text" class="form-control has-icon" name="custom_offered_commission"
                    data-icon="fa-solid fa-ruler-combined" required />
                </div>
                <div class="form-group ">
                  <label class="fw-bold">
                    If a buyer is represented by an agent, what portion of the commission should be shared with the
                    buyer’s agent?
                  </label>
                  @php
                    $commissionSplitCommercial = [
                        ['name' => '2.5%', 'target' => ''],
                        ['name' => '2.75%', 'target' => ''],
                        ['name' => '3%', 'target' => ''],
                        ['name' => 'Negotiable', 'target' => ''],
                        ['name' => 'None', 'target' => ''],
                        ['name' => 'Other', 'target' => '.commissionSplit'],
                    ];
                  @endphp
                  <select name="commissionSplit" id="offered_commission" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($commissionSplitCommercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group commissionSplit  d-none">
                  <label class="fw-bold">If a buyer is represented by an agent, what portion of the commission should be
                    shared with the buyer’s agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="" name="commissionSplitOther"
                    data-icon="fa-solid fa-ruler-combined" id="custom_offered_commission" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="61">
              <span class="commercialFields">
                <div class="row">
                  <div class="form-group mt-4 col-md-12 ">
                    <label class="fw-bold">
                      What are the most important aspects the seller will consider when hiring a real estate agent?
                    </label>
                    <textarea name="important_aspect" value="com" class="form-control" rows="5" required
                      data-msg-required="This Field is Required">{{ old('important_aspect') }}</textarea>
                  </div>
                </div>

                <div class="row">
                  <div class="form-group mt-4 col-md-12 ">
                    <label class="fw-bold">
                      What additional details would the seller like to share with the agent?
                    </label>
                    <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5">{{ old('important_info') }}</textarea>
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="62">
              <span class="commercialFields">
                <div class="form-group  ">
                  <label class="fw-bold">
                    Select the services that the seller requests from an agent:
                  </label>
                  @php
                    $services_data = [
                        ['name' => 'Conduct a thorough comparative market analysis (CMA) to determine the property\'s value and pricing strategy.', 'target' => ''],
                        ['name' => 'List the property on the MLS.', 'target' => ''],
                        ['name' => 'List the property on Loopnet, a major commercial real estate website.', 'target' => ''],
                        ['name' => 'List the property on Crexi, a major commercial real estate website.', 'target' => ''],
                        ['name' => 'List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, to increase the property\'s visibility and exposure.', 'target' => ''],
                        ['name' => 'List the property on the Bid Your Offer platform.', 'target' => ''],
                        ['name' => 'Implement an online marketing campaign with a QR code or listing link that leads to the property\'s listing on the BidYourOffer.com platform.', 'target' => ''],
                        ['name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing on the BidYourOffer.com platform.', 'target' => ''],
                        ['name' => 'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.', 'target' => ''],
                        ['name' => 'Distribute postcards featuring the seller\'s listing within their neighborhood with a QR code or listing link leading to the property\'s listing.', 'target' => ''],
                        ['name' => 'Distribute postcards featuring the seller\'s listing to the most opportune buyers with a QR code or listing link leading to the property\'s listing.', 'target' => ''],
                        ['name' => 'Conduct real estate email marketing campaigns that lead to the seller’s listing on the BidYourOffer.com platform.', 'target' => ''],
                        ['name' => 'Provide professional photos to showcase the property\'s best features.', 'target' => ''],
                        ['name' => 'Provide aerial photography to capture the property\'s surroundings and neighborhood.', 'target' => ''],
                        ['name' => 'Provide a professional video to showcase the property\'s interior and exterior.', 'target' => ''],
                        ['name' => 'Provide a 3D tour to showcase the property\'s interior.', 'target' => ''],
                        ['name' => 'Provide a floor plan of the property to showcase its layout and spatial configuration.', 'target' => ''],
                        ['name' => 'Provide virtual staging to enhance the property\'s visual appeal and attract potential buyers.', 'target' => ''],
                        ['name' => 'Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.', 'target' => ''],
                        ['name' => 'Offer expert negotiation skills to secure the best possible terms and price during the selling process.', 'target' => ''],
                        ['name' => 'Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.', 'target' => ''],
                        ['name' => 'Send email alerts to buyers searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.', 'target' => ''],
                        ['name' => 'Provide guidance and support throughout the entire transaction, from listing to closing, to ensure a seamless and successful sale.', 'target' => ''],
                        ['name' => 'Assist with the completion and submission of all necessary paperwork and documentation related to the sale.', 'target' => ''],
                        ['name' => 'Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.', 'target' => ''],
                        ['name' => 'Provide regular updates on market activity, showings, and feedback from potential buyers.', 'target' => ''],
                        [
                            'name' => 'Other - Add additional services as needed.',
                            'target' => '.commercial_service_commercial_and_business',
                        ],
                    ];

                  @endphp
                  <select name="services[]" id="commercial-services" multiple class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($services_data as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(100%);"
                        data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group commercial_service_commercial_and_business  d-none">
                  <label class="fw-bold">What additional services would the seller like to request from an
                    agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="" name="commercial_servic"
                    data-icon="fa-solid fa-hand-point-right" id="commercial_servic" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="63">
                <h4>For a more personalized listing, you can include a picture of yourself and/or include a video of yourself providing additional information about your background and criteria.</h4>
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
            <div class="wizard-step" data-step="64">
              <span class="vacantFields">
                <div class="form-group">
                  <label class="fw-bold">
                    Special Sale Provision:
                  </label>
                  @php
                    $seller_property = [
                        [
                            'target' => '.assignment_contract_vacant',
                            'name' => 'Assignment Contract',
                        ],
                        ['target' => '', 'name' => 'Auction'],
                        ['target' => '', 'name' => 'Bank Owned/REO'],
                        ['target' => '', 'name' => 'Government Owned'],
                        ['target' => '', 'name' => 'Probate Listing'],
                        ['target' => '', 'name' => 'Short Sale'],
                        ['target' => '', 'name' => 'None'],
                        ['target' => '.custom_special_sale_vacant', 'name' => 'Other'],
                    ];
                  @endphp
                  <div class="select2-parent">
                    <select name="special_sale" id="special_sale" class="grid-picker"
                      style="justify-content: flex-start;" required>
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
                <div class="form-group custom_special_sale_vacant d-none ">
                  <label class="fw-bold" for="custom_special_sale_residential">Special Sale Provision: </label>
                  <input type="text" name="custom_special_sale" id="custom_special_sale"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>

                <div class="form-group d-none assignment_contract_vacant ">
                  <label class="fw-bold">
                    Is the seller currently under contract with a property they would like to assign?
                  </label>
                  @php
                    $seller_contract_options = [
                        ['name' => 'Yes', 'target' => '.custom_seller_contract_yes'],
                        ['name' => 'No', 'target' => '.custom_seller_contract_no'],
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

                  <div class="form-group custom_seller_contract_yes d-none ">
                    <label class="fw-bold">What fee would the seller pay the agent to assign the contract? </label>
                    <input type="number" class="form-control has-icon" min="0.5" step=".01"
                      placeholder="" name="custom_seller_contract_yes" data-icon="fa-solid fa-dollar-sign"
                      id="custom_seller_contract_yes" required />
                  </div>
                  <div class="form-group custom_seller_contract_no d-none ">
                    <label class="fw-bold">Is the seller looking to take over a buyer’s contract? </label>
                    @php
                      $seller_contract_yes_no = [['name' => 'Yes', 'target' => ''], ['name' => 'No', 'target' => '']];
                    @endphp
                    <select name="custom_seller_contract_no" id="custom_seller_contract_no" class="grid-picker"
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
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="65">
              <span class="vacantFields">
                @php
                  $acreagesVacant = [
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

                <div class="form-group vacant_land_hide">
                  <label class="fw-bold">Total Acreage: </label>
                  <select class="grid-picker" name="total_acreage" id="total_acreage"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($acreagesVacant as $item)
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
            <div class="wizard-step" data-step="66">
              <span class="vacantFields">
                @php
                  $viewVacant = [
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
                      ['name' => 'Other', 'target' => '.otherViewVacant'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">View:</label>
                  <select class="grid-picker" name="view[]" id="appliances" style="justify-content: flex-start;"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($viewVacant as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherViewVacant d-none">
                    <label class="fw-bold">View:</label>
                    <input type="text" class="form-control has-icon" name="otherView" id="otherView" required
                      data-icon="fa-solid fa-ruler-combined" />
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="67">
              <span class="vacantFields">
                <div class="form-group">
                  @php
                    $specific_price_vacant = [
                        [
                            'icon' => 'fa-regular fa-check-circle',
                            'name' => 'Yes',
                            'target' => '.specific_price_vacant_yes',
                        ],
                        [
                            'icon' => 'fa-regular fa-circle-xmark',
                            'name' => 'No',
                            'target' => '.specific_price_vacant_no',
                        ],
                    ];
                  @endphp
                  <label class="fw-bold">Does the seller have a specific price in mind that they would
                    like to obtain for their property?</label>
                  <select class="grid-picker" name="seller_specific_price" id=""
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($specific_price_vacant as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group specific_price_vacant_yes d-none">
                  <label class="fw-bold">What price would the seller like to obtain for their property?</label>
                  <input type="text" class="form-control has-icon" name="custom_seller_specific_price"
                    data-icon="fa-solid fa-dollar-sign" id="custom_listing_terms" required />
                </div>

                <div class="form-group specific_price_vacant_no d-none">
                  <label class="fw-bold">
                    How much do you think your property will sell for?
                  </label>
                  @php
                    $expectationsVacant = [
                        ['target' => '', 'name' => '$100k or less'],
                        ['target' => '', 'name' => '$100k-$200k'],
                        ['target' => '', 'name' => '$200k-$300k'],
                        ['target' => '', 'name' => '$300k-$400k'],
                        ['target' => '', 'name' => '$400k-$500k'],
                        ['target' => '', 'name' => '$500k-$600k'],
                        ['target' => '', 'name' => '$600k-$700k'],
                        ['target' => '', 'name' => '$700k-$800k'],
                        ['target' => '', 'name' => '$800-$900k'],
                        ['target' => '', 'name' => '$900k-$1 million'],
                        ['target' => '', 'name' => '$1 million +'],
                        ['target' => '', 'name' => '$2 million +'],
                        ['target' => '', 'name' => '$3 million +'],
                        ['target' => '', 'name' => '$4 million +'],
                        ['target' => '', 'name' => '$5 million +'],
                        ['target' => '', 'name' => '$10 million +'],
                        ['target' => '.otherPriceVacant', 'name' => 'Other'],
                    ];
                  @endphp
                  <select name="expectation" id="expectation" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($expectationsVacant as $item)
                      <option value="{{ $item['name'] }}" data-target="" class="card flex-column"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group otherPriceVacant  d-none">
                  <label class="fw-bold">How much do you think your property will sell for?</label>
                  <input type="number" class="form-control has-icon" name="otherSellerPrice"
                    data-icon="fa-solid fa-dollar-sign" id="custom_listing_terms" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="68">
                <span class="vacantFields">
                  @php
                    $financings = [
                        ['name' => 'Cash', 'target' => ''],
                        ['name' => 'Conventional', 'target' => ''],
                        ['name' => 'Seller Financing', 'target' => '.custom_seller_financing_vacant'],
                        ['name' => 'FHA', 'target' => ''],
                        ['name' => 'Jumbo', 'target' => ''],
                        ['name' => 'VA', 'target' => ''],
                        ['name' => 'No-Doc', 'target' => ''],
                        ['name' => 'Non-QM', 'target' => ''],
                        ['name' => 'Assumable', 'target' => '.custom_assumable_vacant'],
                        ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade_vacant'],
                        ['name' => 'Lease Option', 'target' => '.lease_option_vacant'],
                        ['name' => 'Lease Purchase', 'target' => '.lease_purchase_vacant'],
                        ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency_vacant'],
                        ['name' => 'NFT', 'target' => '.nftVacant'],
                        ['name' => 'USDA', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_financing_vacant'],
                    ];
                  @endphp
                  <div class="row align-items-end mt-4 ">
                    <div class="col-md-12">
                      <label class="fw-bold">Select the types of financing/currency that is acceptable to the
                        seller:</label>
                      <div class="select2-parent">
                        <select name="financings[]" class="grid-picker" multiple id="financingOptions" required>
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
                  <div class="form-group custom_financing_vacant d-none ">
                    <label class="fw-bold">What type of financing/currency is acceptable to the seller? </label>
                    <input type="text" name="type_of_financing" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                  </div>
                  <div class="form-group custom_exchange_trade_vacant d-none ">
                    @php
                      $tradeRes = [
                          ['name' => 'Another Home', 'target' => ''],
                          ['name' => 'Vehicle', 'target' => ''],
                          ['name' => 'Boat', 'target' => ''],
                          ['name' => 'Motorhome', 'target' => ''],
                          ['name' => 'Artwork', 'target' => ''],
                          ['name' => 'Jewelry ', 'target' => ''],
                          ['name' => 'Other ', 'target' => '.tradeOtherRes'],
                      ];
                    @endphp
                    <label class="fw-bold">Acceptable Exchange Item:</label>
                    <select name="trade[]" class="grid-picker" id="">
                      @foreach ($tradeRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column " style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group tradeOtherRes d-none ">
                      <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is
                        willing to exchange/trade?</label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      <label class="fw-bold">How much cash does the seller require on top of the Exchange/Trade
                        item?</label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                      <input type="text" name="tradeOther[]" data-type="" id=""
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                    </div>
                  </div>
                  {{-- Lease Options --}}
                  <div class="form-group lease_option_vacant d-none" >
                    <label class="fw-bold">What is the seller's desired offering price for a lease option?</label><br>
                    <input type="text" name="leaseOptions[]"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign "
                      >
                    <label class="fw-bold">What specific terms does the seller propose for the lease option?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label><br>
                    <input type="text" name="leaseOptions[]" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for
                      the lease option?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">Is there a possibility of changes in the offering price during the lease
                      period?</label><br>
                    <input type="text" name="leaseOptions[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                      <div class="form-group">
                        @php
                        $leaseOptRes = [
                            ['name' => 'Yes', 'target' => '.leaseOptionsRes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                            ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <label class="fw-bold">Does the seller require an option fee? </label>
                      <select name="trade[]" class="grid-picker" id="">
                        @foreach ($leaseOptRes as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon="{{$item['icon']}}">
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group leaseOptionsRes d-none">
                        <label class="fw-bold">How much is the option fee? </label>
                          <input type="text" name="leaseOptionsRes" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      </div>
                    </div>
                  </div>
                  {{-- Lease Options --}}
                   {{-- Lease Purchase --}}
                   <div class="form-group lease_purchase_vacant d-none" >
                    <label class="fw-bold">What is the seller's desired offering price for a lease purchase?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign "
                      >
                    <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">What is the proposed duration of the lease?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for
                      the lease purchase?</label><br>
                    <input type="text" name="leasePurchase[]"
                       class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                      >
                    <label class="fw-bold"></label><br>
                    <label class="fw-bold">Is there a possibility of changes in the offering price during the lease
                        period?</label><br>
                        <input type="text" name="leasePurchase[]"
                         class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
                        >
                    <div class="form-group">
                        @php
                        $leasePurchaseOpt = [
                            ['name' => 'Yes', 'target' => '.leasePurchaseYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                            ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <label class="fw-bold">Does the seller require an option fee? </label>
                      <select name="trade[]" class="grid-picker" id="">
                        @foreach ($leasePurchaseOpt as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon="{{$item['icon']}}">
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group leasePurchaseYes d-none">
                        <label class="fw-bold">How much is the option fee? </label>
                          <input type="text" name="leasePurchaseYes" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                      </div>
                    </div>

                  </div>
                  {{-- Lease Purchase --}}
                  {{-- Seller  --}}
                  <div class="form-group custom_seller_financing_vacant d-none ">
                    <div class="row">
                      <div class="col-4 form-group">
                        <label class="fw-bold">Purchase Price:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Down Payment:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Seller Financing Amount:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Interest Rate:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-ruler-combined" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Term:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-ruler-combined" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Monthly Payments:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      <div class="col-4 form-group">
                        <label class="fw-bold">Closing Costs:</label>
                        <input type="text" name="sellerFinancing[]" value=""
                          data-target="{{ $item['target'] }}" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" />
                      </div>
                      {{-- PrePayement --}}
                      @php
                        $PrepaymentRes = [
                            [
                                'name' => 'Yes',
                                'target' => '.prepaymentOtherRes',
                                'icon' => '<i class="fa-regular fa-circle-check"></i>',
                            ],
                            ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <div class="select2-parent">
                        <br><label class="fw-bold">Prepayment Penalty: </label>
                        <select name="prepayment" class="grid-picker" id="">
                          @foreach ($PrepaymentRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='{{ $item['icon'] }}'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group prepaymentOtherRes d-none ">
                          <label class="fw-bold">What is the prepayment penalty amount? </label>
                          <input type="texts" name="prepaymentOther[]" data-type="custom_timeframe" id=""
                            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                        </div>
                      </div>
                      {{-- Prepayment --}}
                      {{-- Balloon Payment --}}
                      @php
                        $balloonRes = [
                            [
                                'name' => 'Yes',
                                'target' => '.balloonpaymentOtherRes',
                                'icon' => '<i class="fa-regular fa-circle-check"></i>',
                            ],
                            ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
                        ];
                      @endphp
                      <div class="select2-parent">
                        <br><label class="fw-bold">Balloon Payment:</label>
                        <select name="balloon" class="grid-picker" id="">
                          @foreach ($balloonRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-column " style="width:calc(33.3% - 10px);"
                              data-icon='{{ $item['icon'] }}'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group balloonpaymentOtherRes d-none ">
                          <label class="fw-bold">How much is the balloon payment? </label>
                          <input type="text" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign" id=""
                            class="form-control has-icon" required>
                          <label class="fw-bold">When is the balloon payment due? </label>
                          <input type="text" name="balloonpyment[]" data-icon="fa-solid fa-dollar-sign" id=""
                            class="form-control has-icon" required>
                        </div>
                      </div>
                      {{-- Balloon Payment --}}
                    </div>
                  </div>
                  {{-- Assumable --}}
                  <div class="form-group custom_assumable_vacant d-none ">
                    <label class="fw-bold">What assumable terms are being offered?</label>
                    <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" />
                    <label class="fw-bold">Are there any restrictions or qualifications for a buyer assuming the existing
                      financing?</label>
                    <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" />
                    <label class="fw-bold">Is there any outstanding balance on the existing financing that the buyer
                      would need to cover?</label>
                    <input type="text" name="assumable[]" value="" data-target="{{ $item['target'] }}"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" />

                     <div class="form-group">
                        @php
                        $assumableOpt=[
                          ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.assumableYes'],
                          ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                            ]
                        @endphp
                        <select name="assumable[]" class="grid-picker" id="">
                        @foreach ($assumableOpt as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='{{ $item['icon'] }}'>
                            {{ $item['name'] }}
                            </option>
                        @endforeach
                        </select>
                        <div class="form-group assumableYes d-none ">
                        <label class="fw-bold">What is the outstanding balance on the existing financing that the buyer would need to cover? </label>
                        <input type="text" name="assumableYes" data-icon="fa-solid fa-dollar-sign" id=""
                            class="form-control has-icon" required>
                        </div>
                     </div>
                  </div>
                  {{-- Assumable --}}
                  {{-- Cryptcurrency --}}
                  <div class="form-group custom_cryptocurrency_vacant d-none ">
                    <label class="fw-bold">What type of cryptocurrency will the seller accept?</label><br>
                        <input type="text" name="cryptocurrency[]"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        >
                    <label class="fw-bold">What percentage of the purchase price will the seller accept in
                        cryptocurrency?</label><br>
                        <input type="text" name="cryptocurrency[]"
                         class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                        >
                    <label class="fw-bold">What percentage of the purchase price will the seller accept in
                      cash?</label><br>
                    <input type="text" name="cryptocurrency[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="">Note: Cryptocurrency can be converted to cash at closing. </label>

                  </div>
                  {{-- NFTVacant --}}
                  <div class="form-group nftVacant d-none ">
                    <label class="fw-bold">What type of NFT will the seller accept?</label><br>
                    <input type="text" name="nft[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined "
                      >
                    <label class="fw-bold">What percentage of the sales price will the seller accept in the form of a Non-Fungible Token (NFT)? </label><br>
                    <input type="text" name="nft[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >
                    <label class="fw-bold">What percentage of the purchase price will the seller accept in
                      cash?</label><br>
                    <input type="text" name="nft[]"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined"
                      >

                  </div>
                  {{-- NFT --}}
                </span>
              </div>
            <div class="wizard-step" data-step="69">
              <span class="vacantFields">
                <div class="form-group">
                  <label class="fw-bold">
                    When will the property be ready to list?
                  </label>
                  @php
                    $timeframes = [
                        ['name' => 'Now', 'target' => ''],
                        ['name' => '1 Month', 'target' => ''],
                        ['name' => '2 Months', 'target' => ''],
                        ['name' => '3 Months', 'target' => ''],
                        ['name' => '4 Months', 'target' => ''],
                        ['name' => '5 Months', 'target' => ''],
                        ['name' => '6 Months', 'target' => ''],
                        ['name' => 'Over 6 Months', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_timeframe_vacant_land'],
                    ];
                  @endphp
                  <select name="selling_timeframe" id="selling_timeframe" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($timeframes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_timeframe_vacant_land d-none">
                  <label class="fw-bold">When will the property be ready to list? </label>
                  <input type="texts" name="custom_timeframe" data-type="custom_timeframe" id="custom_timeframe"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group prompt_response d-none">
                  <p class="text-danger">This is a service designed for sellers who are seeking to sell
                    their property within three Months or less.</p>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="70">
              <span class="vacantFields">
                <div class="form-group">
                  <label class="fw-bold">
                    What is the timeframe offered to the agent in the Seller Agency Agreement?
                  </label>
                  @php
                    $listing_terms_vacant = [
                        ['name' => '3 Months', 'target' => ''],
                        ['name' => '6 Months', 'target' => ''],
                        ['name' => '9 Months', 'target' => ''],
                        ['name' => '12 Months', 'target' => ''],
                        ['name' => 'Negotiable', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_listing_terms_vacant_land'],
                    ];
                  @endphp
                  <select name="listing_term" id="listing_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($listing_terms_vacant as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_listing_terms_vacant_land d-none">
                  <label class="fw-bold">What is the timeframe offered to the agent in the Seller Agency
                    Agreement?</label>
                  <input type="text" class="form-control has-icon" placeholder="" name="custom_listing_terms"
                    data-icon="fa-solid fa-ruler-combined" id="custom_listing_terms" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="71">
              <span class="vacantFields">
                <div class="form-group">
                  <label class="fw-bold">
                    What is the total commission being offered to the listing agent?
                  </label>
                  @php
                    $offered_commissions = [
                        ['name' => '5%', 'target' => ''],
                        ['name' => '5.5%', 'target' => ''],
                        ['name' => '6%', 'target' => ''],
                        ['name' => 'Negotiable', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_offered_vacant_land'],
                    ];
                  @endphp
                  <select name="offered_commission" id="offered_commission" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($offered_commissions as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column " style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_offered_vacant_land d-none">
                  <label class="fw-bold">What is the total commission being offered to the listing agent?</label>
                  <input type="text" class="form-control has-icon" name="custom_offered_commission"
                    data-icon="fa-solid fa-ruler-combined" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="72">
              <span class="vacantFields">
                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What are the most important aspects the seller will consider when hiring a real estate agent?
                    </label>
                    <textarea name="important_aspect" value="vacant" class="form-control" rows="5" required
                      data-msg-required="This Field is Required">{{ old('important_aspect') }}</textarea>
                  </div>
                </div>

                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What additional details would the seller like to share with the agent?
                    </label>
                    <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5">{{ old('important_info') }}</textarea>
                  </div>
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="73">
              <span class="vacantFields">
                <div class="form-group ">
                  <label class="fw-bold">
                    Select the services that the seller requests from an agent:
                  </label>
                  @php
                    $services_data = [
                    ['name' => 'Conduct a thorough comparative market analysis (CMA) to determine the property\'s value and pricing strategy.', 'target' => ''],
                    ['name' => 'List the property on the MLS.', 'target' => ''],
                    ['name' => 'List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, to increase the property\'s visibility and exposure.', 'target' => ''],
                    ['name' => 'List the property on the Bid Your Offer platform.', 'target' => ''],
                    ['name' => 'Implement an online marketing campaign with a QR code or listing link that leads to the property\'s listing.', 'target' => ''],
                    ['name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing.', 'target' => ''],
                    ['name' => 'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.', 'target' => ''],
                    ['name' => 'Distribute postcards featuring the seller\'s listing within their neighborhood with a QR code or listing link leading to the property\'s listing.', 'target' => ''],
                    ['name' => 'Distribute postcards featuring the seller\'s listing to the most opportune buyers with a QR code or listing link leading to the property\'s listing.', 'target' => ''],
                    ['name' => 'Conduct real estate email marketing campaigns that lead to the seller’s listing.', 'target' => ''],
                    ['name' => 'Provide professional photos to showcase the property\'s features.', 'target' => ''],
                    ['name' => 'Provide aerial photography to capture the property\'s surroundings and neighborhood.', 'target' => ''],
                    ['name' => 'Provide a professional video to showcase the land.', 'target' => ''],
                    ['name' => 'Provide a plot plan to showcase the land.', 'target' => ''],
                    ['name' => 'Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.', 'target' => ''],
                    ['name' => 'Offer expert negotiation skills to secure the best possible terms and price during the selling process.', 'target' => ''],
                    ['name' => 'Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.', 'target' => ''],
                    ['name' => 'Send email alerts to buyers searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.', 'target' => ''],
                    ['name' => 'Provide guidance and support throughout the entire transaction, from listing to closing, to ensure a seamless and successful sale.', 'target' => ''],
                    ['name' => 'Assist with the completion and submission of all necessary paperwork and documentation related to the sale.', 'target' => ''],
                    ['name' => 'Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.', 'target' => ''],
                    ['name' => 'Provide regular updates on market activity, showings, and feedback from potential buyers.', 'target' => ''],

                                        [
                                            'name' => 'Other - Add additional services as needed.',
                                            'target' => '.commercial_service_vacant_land',
                                        ],
                ];
                  @endphp

                  <select name="services[]" id="commercial-services" multiple class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($services_data as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(100%);"
                        data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group commercial_service_vacant_land d-none">
                  <label class="fw-bold">What additional services would the seller like to request from an
                    agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="" name="commercial_servic"
                    data-icon="fa-solid fa-hand-point-right" id="commercial_servic" required />
                </div>
              </span>
            </div>
            <div class="wizard-step" data-step="74">
                <h4>For a more personalized listing, you can include a picture of yourself and/or include a video of yourself providing additional information about your background and criteria.</h4>
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
            <div class="d-flex justify-content-between form-group mt-4">
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

  <template class="questions_temp">

    <div class="wizard-step cma_questions_">
      <div class="form-group">
        <label class="fw-bold">
          Have you done any updates since purchasing the property?
        </label>
        <input type="text" name="cma_q3" id="cma_q3" class="form-control" required>
      </div>
    </div>

    <div class="wizard-step cma_questions_">
      <div class="form-group">
        <label class="fw-bold">
          Are there any known repairs that need to be completed before purchasing?
        </label>
        <input type="text" name="cma_q4" id="cma_q4" class="form-control" required>
      </div>
    </div>

  </template>
  <template class="unit_temp">
    <lable class="fw-bold">Unit:</lable>
    <input type="text" name="unitAdded[]" placeholder="" data-type="" class="form-control has-icon"
      data-icon="fa-solid fa-ruler-combined"data-icon="fa-solid fa-ruler-combined" data-msg-required=""></br>
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
      var unitTemp = $('.unit_temp').html();
      $('.unit_btn').before(unitTemp);
      var city_row = $('.city_temp').html();
      $('.city_btn_row').before(city_row);
      initialize();
    }

    /* function add_state_row() {
        var state_row = $('.state_temp').html();
        $('.state_btn_row').before(state_row);
        initialize();
    } */
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
        $('.for_residential_only').removeClass('d-none');
        $('.residential_hide').removeClass('d-none');
        $('.business-length').hide();
        $('.businessTitle').addClass('d-none');
        $('.vacantTitle').addClass('d-none');
        $('.resFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
        });
        $('.incomeFields,.vacantFields,.commercialFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
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
        $('.removeIncome').hide();
        $('.businessTitle').addClass('d-none');
        $('.vacantTitle').addClass('d-none');
        $('.incomeFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
        });
        $('.resFields,.commercialFields,.vacantFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
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
        $('.bedroomRes').remove();
        $('.special_sale_vacant').remove();
        $('.special_sale_res').remove();
        $('.special_sale_income').remove();
        $('.businessTitle').addClass('d-none');
        $('.vacantTitle').addClass('d-none');
        $('.commercialFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
        });
        $('.resFields,.incomeFields,.vacantFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
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
        $('.special_sale_income').remove();
        $('.special_sale_res').remove();
        $('.special_sale_commercial').remove();
        $('.businessTitle').addClass('d-none');
        $('.vacantTitle').removeClass('d-none');
        $('.vacantFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
        });
        $('.resFields,.incomeFields,.commercialFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
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
        $('.business_show').removeClass('d-none');
        $('.special_sale_vacant').remove();
        $('.special_sale_res').remove();
        $('.special_sale_income').remove();
        $('.businessTitle').removeClass('d-none');
        $('.vacantTitle').addClass('d-none');
        $('.commercialFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
        });
        $('.resFields,.incomeFields,.vacantFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
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
    function change_buyer_prefered_agent(w) {
      if (w == "Yes") {
        $('#explains_income').removeClass('d-none');
        $('#explains_commercial_and_business').removeClass('d-none');
      } else {

        $('#explains_income').addClass('d-none');
        $('#explains_commercial_and_business').addClass('d-none');

      }
    }

    function change_sale_provision(w) {
      if (w == "Other") {
        $('#other_sale_provision_residential').removeClass('d-none');
        $('#other_sale_provision_income').removeClass('d-none');
        $('#other_sale_provision_commercial_and_business').removeClass('d-none');
        $('#other_sale_provision_vacant_land').removeClass('d-none');
        $('#other_prop_commercial').removeClass('d-none');

      } else {
        $('#other_prop_commercial').addClass('d-none');
        $('#other_sale_provision_residential').addClass('d-none');
        $('#other_sale_provision_income').addClass('d-none');
        $('#other_sale_provision_commercial_and_business').addClass('d-none');
        $('#other_sale_provision_vacant_land').addClass('d-none');

      }
    }

    function seller_under_contract(w) {
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

    function changeWaterAccess(w) {
      if (w == "Yes" || w == "Optional") {
        $('.water_access_residentail').show();
      } else {
        $('.water_access_residentail').hide();
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
          console.log(StepWizard.currentStep)
          if (v.form()) {
            if ($('.wizard-step.active').next().is('.wizard-step')) {


              $('.wizard-step.active').removeClass('active');
              if (StepWizard.currentStep == 6 && property_type ==
                'Income Property') {
                StepWizard.nextStep = 28;
                StepWizard.backStep = 6;
              } else if (StepWizard.currentStep == 5 && property_type ==
                'Residential Property') {
                StepWizard.nextStep = 6;
                StepWizard.backStep = 5;
              }
            //   else if (StepWizard.currentStep == 15 && property_type ==
            //     'Residential Property') {
            //     StepWizard.nextStep = 17;
            //     StepWizard.backStep = 15;
            //   }
            //    else if (StepWizard.currentStep == 17 && property_type ==
            //     'Residential Property') {
            //     StepWizard.nextStep = 19;
            //     StepWizard.backStep = 17;
            //   }
              else if (StepWizard.currentStep == 6 && (property_type ==
                  'Commercial Property' || property_type ==
                  'Business Opportunity')) {
                StepWizard.nextStep = 45;
                StepWizard.backStep = 6;
              } else if (StepWizard.currentStep == 53 && property_type ==
                'Commercial Property') {
                StepWizard.nextStep = 55;
                StepWizard.backStep = 53;
              } else if (StepWizard.currentStep == 6 && (property_type == 'Vacant Land')) {
                StepWizard.nextStep = 64;
                StepWizard.backStep = 6;
              } else {
                StepWizard.backStep = StepWizard.currentStep;

                // StepWizard.backStep = StepWizard.nextStep + 1;

              }

              $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
              StepWizard.setStep();
              if (StepWizard.currentStep == 27 &&
                property_type == 'Residential Property'
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if (StepWizard.currentStep == 44 &&
                property_type == 'Income Property'
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if (StepWizard.currentStep == 63 &&
                property_type == 'Commercial Property') {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if ((StepWizard.currentStep == 63) &&
                property_type == 'Business Opportunity') {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if (StepWizard.currentStep == 74 &&
                property_type == 'Vacant Land'
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
            }
          }
        });

        $('.wizard-step-back').click(function(e) {
          console.log('current' + StepWizard.currentStep)
          console.log('back' + StepWizard.backStep)
          console.log('next' + StepWizard.nextStep)
          if ($('.wizard-step.active').prev().is('.wizard-step')) {

            $('.wizard-step.active').removeClass('active');
            $('[ data-step="' + StepWizard.backStep + '"]').addClass("active");
            StepWizard.setStep();
            console.log('current' + StepWizard.currentStep)
            console.log('back' + StepWizard.backStep)
            console.log('next' + StepWizard.nextStep)
            if (StepWizard.currentStep == 28 && property_type ==
              'Income Property') {
              StepWizard.backStep = 6;
            } else if (StepWizard.currentStep == 45 && property_type ==
              'Commercial Property') {
              StepWizard.backStep = 6;
            } else if (StepWizard.currentStep == 46 && property_type ==
              'Business Opportunity') {
              StepWizard.backStep = 6;
            } else if (StepWizard.currentStep == 55 && property_type ==
              'Commercial Property') {
              StepWizard.backStep = 53;
            } else if (StepWizard.currentStep == 7 && property_type ==
              'Residential Property') {
              StepWizard.backStep = 6;
            }
            // else if (StepWizard.currentStep == 17 && property_type ==
            //   'Residential Property') {
            //   StepWizard.backStep = 15;
            // } else if (StepWizard.currentStep == 19 && property_type ==
            //   'Residential Property') {
            //   StepWizard.backStep = 17;
            // }
             else if (StepWizard.currentStep == 64 && (property_type == 'Vacant Land')) {
              StepWizard.backStep = 6;
            } else {
              StepWizard.backStep = StepWizard.currentStep - 1;

            }
          }
        });
        // Assuming the code provided is within a function or a document.ready block

        $('.wizard-step-finish').click(function(e) {

          //Remove All the SLides Except THe Vacant Land
          //   if (property_type === 'Vacant Land') {
          //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
          //       return parseInt($(this).attr('data-step')) >= 6 && parseInt($(this)
          //         .attr('data-step')) <= 45;
          //     });
          //     $stepsToRemove.each(function() {
          //       $(this).closest('div[data-step]').remove();
          //     });
          //   }
          //Remove All the SLides Except THe Residential and Commercial Property
          //   if (property_type === 'Residential Property') {
          //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
          //       return parseInt($(this).attr('data-step')) >= 19 && parseInt($(this)
          //         .attr('data-step')) <= 54;
          //     });
          //     $stepsToRemove.each(function() {
          //       $(this).closest('div[data-step]').remove();
          //     });
          //   }
          //   if (property_type === 'Income Property') {
          //     var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
          //       var stepValue = parseInt($(this).attr('data-step'));
          //       return (stepValue >= 6 && stepValue <= 19) || (stepValue >= 33 &&
          //         stepValue <= 54);
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
          //       return (stepValue >= 6 && stepValue <= 32) || (stepValue >= 47 &&
          //         stepValue <= 54);
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

        if (StepWizard.currentStep >= 6 && StepWizard.currentStep <= 27) {
          // Calculate progress for Residential steps (6 to 19)
          comp = 20 + (((StepWizard.currentStep - 6) / (27 - 6)) * 80);
        } else if (StepWizard.currentStep >= 27 && StepWizard.currentStep <= 44) {
          // Calculate progress for Income property steps (20 to 32)
          comp = 20 + (((StepWizard.currentStep - 27) / (44 - 27)) * 80);
        } else if (StepWizard.currentStep >= 45 && StepWizard.currentStep <= 63) {
          // Calculate progress for Commercial and Business opportunity steps (33 to 45)
          comp = 20 + (((StepWizard.currentStep - 45) / (63 - 45)) * 80);
        } else if (StepWizard.currentStep >= 64 && StepWizard.currentStep <= 74) {
          // Calculate progress for Vacant land steps (46 to 54)
          comp = 20 + (((StepWizard.currentStep - 64) / (74 - 64)) * 80);
        } else {
          // Default progress calculation for other steps (1 to 5)
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
      // alert('ok');

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
      //    alert(selectedValue);
    });
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
  </script>
  <script>
    // Residential
    var toggleDiv = $('#custom_lease_option_res');
    $('#financingOptions').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) ? toggleDiv
        .show(): toggleDiv.hide()
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
    var toggleLand = $('#land_lease_option');
    $('#landFinancingOptions').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) ? toggleLand.show():
        toggleLand.hide()
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
    // Commercial
    var toggleCommercial = $('#lease_option_commercial');
    $('#commercialFinancingOptions').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) ? toggleCommercial.show():
        toggleCommercial.hide()
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
    // Income
    var toggleIncome = $('#lease_option_income');
    $('#financingIncome').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) ? toggleIncome.show():
        toggleIncome.hide()
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
    var toggleDivVacant = $('#land_lease_option_vacant');
    $('#financingVacant').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) ? toggleDivVacant.show():
        toggleDivVacant.hide()
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
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush
