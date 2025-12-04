@extends('layouts.main')

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
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
  </style>
@endpush
@section('content')
  {{-- @if ($errors->any())
<h4>{{$errors->first()}}</h4>
@endif --}}
  @php
    $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
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
                    if (val == "Yes") {
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

              {{-- Slide 1 --}}
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
              {{-- Slide 1 --}}
              {{-- Slide 2 --}}
              <div class="wizard-step" data-step="2">
                <h4 class="title">Please provide the property's complete address, along with the city,
                  county, and
                  state, pertaining to the real estate asset that the seller intends to place on the
                  market.</h4>
                <div class="form-group">
                  <label class="fw-bold">Address</label>
                  <input type="text" name="address" id="form_title" class="form-control has-icon" data-type="address"
                    required data-icon="fa-solid fa-location-dot" />
                  @if ($errors->has('address'))
                    <div class="small error">{{ $errors->first('address') }}</div>
                  @endif
                </div>

                <div class="form-group">
                  <label class="fw-bold">City</label>
                  <input type="text" name="city" data-type="cities" id="city" class="form-control has-icon"
                    data-icon="fa-solid fa-city" data-msg-required="Please enter city" required>
                </div>

                <div class="form-group">
                  <label class="fw-bold">County</label>
                  <input type="text" name="county" data-type="counties" id="county" class="form-control has-icon"
                    data-icon="fa-solid fa-tree-city" data-msg-required="Please enter county" required>
                </div>

                {{-- nisar changing --}}
                <div class="form-group">
                  <label class="fw-bold">State</label>
                  <input type="text" name="state" data-type="states" id="state" class="form-control has-icon"
                    data-icon="fa-solid fa-flag-usa" data-msg-required="Please enter state" required>
                </div>

              </div>
              {{-- Slide 2 --}}
              {{-- Slide 3 --}}
              <div class="wizard-step" data-step="3">
                <div class="form-group">
                  <label for="address" class="fw-bold">Listing Date:</label>
                  <input type="date" name="listing_date" id="listing_date" class="form-control has-icon search_places"
                    data-icon="fa-regular fa-calendar-days" data-msg-required="Please enter listing date" required>
                </div>
                <div class="form-group" class="fw-bold">
                  <label for="address" class="fw-bold">Expiration Date:</label>
                  <input type="date" name="expiration_date" id="expiration_date"
                    class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days"
                    data-msg-required="Please enter expiration date" required>
                </div>
              </div>
              {{-- Slide 3 --}}
              {{-- Slide 4 --}}
              <div class="wizard-step" data-step="4">
                <div class="form-group">
                  <label class="fw-bold">
                    Listing Type:
                  </label>
                  <div>
                    @php
                      $auction_types = [['name' => 'Auction (Timer)', 'icon' => '<i class="fa-regular fa-clock"></i>', 'target' => ''], ['name' => 'Traditional (No Timer)', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>', 'target' => '']];
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

                <div class="form-group auction_length_cover">
                  <label class="fw-bold">
                    Auction Length:
                  </label>
                  <div>
                    @php
                      $auction_lengths = [['name' => '1 Day', 'class' => 'normal-length'], ['name' => '3 Days', 'class' => 'normal-length'], ['name' => '5 Days', 'class' => 'normal-length'], ['name' => '7 Days', 'class' => 'normal-length'], ['name' => '10 Days', 'class' => 'normal-length'], ['name' => '14 Days', 'class' => 'normal-length'], ['name' => '21 Days', 'class' => 'normal-length'], ['name' => '30 Days', 'class' => 'normal-length'], ['name' => '45 Days', 'class' => 'normal-length'], ['name' => '60 Days', 'class' => 'normal-length'], ['name' => '75 Days', 'class' => 'normal-length'], ['name' => '90 Days', 'class' => 'normal-length'], ['name' => 'No time limit', 'class' => 'traditional-length']];
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
              {{-- Slide 4 --}}
              {{-- Slide 5 --}}
              <div class="wizard-step" data-step="5">
                @php
                  $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property'], ['name' => 'Business Opportunity'], ['name' => 'Vacant Land']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Property Type:</label>
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
                          ['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
                          ['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
                          ['name' => 'Modular Home', 'class' => 'residential-length'],
                          ['name' => 'Duplex', 'class' => 'income-length'],
                          ['name' => 'Triplex', 'class' => 'income-length'],
                          ['name' => 'Quadplex', 'class' => 'income-length'],
                          ['name' => 'Five or More (Residential units)', 'class' => 'income-length'],
                          ['name' => 'Agriculture', 'class' => 'commercial-length'],
                          ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                          ['name' => 'Business', 'class' => 'commercial-length'],
                          ['name' => 'Five or More (Commercial units)', 'class' => 'commercial-length'],
                          ['name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                          ['name' => 'Industrial', 'class' => 'commercial-length'],
                          ['name' => 'Mixed Use', 'class' => 'commercial-length'],
                          ['name' => 'Office', 'class' => 'commercial-length'],
                          ['name' => 'Restaurant', 'class' => 'commercial-length'],
                          ['name' => 'Retail', 'class' => 'commercial-length'],
                          ['name' => 'Unimproved Land', 'class' => 'commercial-length'],
                          ['name' => 'Warehouse', 'class' => 'commercial-length'],
                          // Business Items
                          ['name' => 'Aeronautical', 'class' => 'business-length'],
                          ['name' => 'Agriculture', 'class' => 'business-length'],
                          ['name' => 'Arts and Entertainment', 'class' => 'business-length'],
                          ['name' => 'Assembly Hall', 'class' => 'business-length'],
                          ['name' => 'Assisted Living', 'class' => 'business-length'],
                          ['name' => 'Auto Dealer', 'class' => 'business-length'],
                          ['name' => 'Auto Service', 'class' => 'business-length'],
                          ['name' => 'Bar/Tavern/Lounge', 'class' => 'business-length'],
                          ['name' => 'Barber/Beauty', 'class' => 'business-length'],
                          ['name' => 'Car Wash', 'class' => 'business-length'],
                          ['name' => 'Child Care', 'class' => 'business-length'],
                          ['name' => 'Church', 'class' => 'business-length'],
                          ['name' => 'Commercial', 'class' => 'business-length'],
                          ['name' => 'Construction/Contractor', 'class' => 'business-length'],
                          ['name' => 'Distribution', 'class' => 'business-length'],
                          ['name' => 'Distributor Routine Ven', 'class' => 'business-length'],
                          ['name' => 'Education/School', 'class' => 'business-length'],
                          ['name' => 'Farm', 'class' => 'business-length'],
                          ['name' => 'Fashion/Specialty', 'class' => 'business-length'],
                          ['name' => 'Flex Space', 'class' => 'business-length'],
                          ['name' => 'Florist/Nursery', 'class' => 'business-length'],
                          ['name' => 'Food & Beverage', 'class' => 'business-length'],
                          ['name' => 'Gas Station', 'class' => 'business-length'],
                          ['name' => 'Grocery', 'class' => 'business-length'],
                          ['name' => 'Heavy Weight Sales Service', 'class' => 'business-length'],
                          ['name' => 'Hotel/Motel', 'class' => 'business-length'],
                          ['name' => 'Industrial', 'class' => 'business-length'],
                          ['name' => 'Light Items Sales Only', 'class' => 'business-length'],
                          ['name' => 'Manufacturing', 'class' => 'business-length'],
                          ['name' => 'Marine/Marina', 'class' => 'business-length'],
                          ['name' => 'Medical', 'class' => 'business-length'],
                          ['name' => 'Mixed', 'class' => 'business-length'],
                          ['name' => 'Mobile/Trailer Park', 'class' => 'business-length'],
                          ['name' => 'Other', 'class' => 'business-length'],
                          ['name' => 'Professional Service', 'class' => 'business-length'],
                          ['name' => 'Professional/Office', 'class' => 'business-length'],
                          ['name' => 'Recreation', 'class' => 'business-length'],
                          ['name' => 'Research & Development', 'class' => 'business-length'],
                          ['name' => 'Residential', 'class' => 'business-length'],
                          ['name' => 'Restaurant', 'class' => 'business-length'],
                          ['name' => 'Retail', 'class' => 'business-length'],
                          ['name' => 'Storage', 'class' => 'business-length'],
                          ['name' => 'Theater', 'class' => 'business-length'],
                          ['name' => 'Timberland', 'class' => 'business-length'],
                          ['name' => 'Veterinary', 'class' => 'business-length'],
                          ['name' => 'Warehouse', 'class' => 'business-length'],
                          ['name' => 'Wholesale', 'class' => 'business-length'],
                          // Vacant Land Items
                          ['name' => 'Billboard Site', 'class' => 'vacant_land-length'],
                          ['name' => 'Business', 'class' => 'vacant_land-length'],
                          ['name' => 'Cattle', 'class' => 'vacant_land-length'],
                          ['name' => 'Commercial', 'class' => 'vacant_land-length'],
                          ['name' => 'Farm', 'class' => 'vacant_land-length'],
                          ['name' => 'Fishery', 'class' => 'vacant_land-length'],
                          ['name' => 'Highway Frontage', 'class' => 'vacant_land-length'],
                          ['name' => 'Horses', 'class' => 'vacant_land-length'],
                          ['name' => 'Industrial', 'class' => 'vacant_land-length'],
                          ['name' => 'Land Fill', 'class' => 'vacant_land-length'],
                          ['name' => 'Livestock', 'class' => 'vacant_land-length'],
                          ['name' => 'Mixed Use', 'class' => 'vacant_land-length'],
                          ['name' => 'Nursery', 'class' => 'vacant_land-length'],
                          ['name' => 'Orchard', 'class' => 'vacant_land-length'],
                          ['name' => 'Other', 'class' => 'vacant_land-length'],
                          ['name' => 'Pasture', 'class' => 'vacant_land-length'],
                          ['name' => 'Poultry', 'class' => 'vacant_land-length'],
                          ['name' => 'Ranch', 'class' => 'vacant_land-length'],
                          ['name' => 'Residential', 'class' => 'vacant_land-length'],
                          ['name' => 'Row Crops', 'class' => 'vacant_land-length'],
                          ['name' => 'Sod Farm', 'class' => 'vacant_land-length'],
                          ['name' => 'Subdivision', 'class' => 'vacant_land-length'],
                          ['name' => 'Timber', 'class' => 'vacant_land-length'],
                          ['name' => 'Tracts', 'class' => 'vacant_land-length'],
                          ['name' => 'Trans/Cell Tower', 'class' => 'vacant_land-length'],
                          ['name' => 'Tree Farm', 'class' => 'vacant_land-length'],
                          ['name' => 'Unimproved Land', 'class' => 'vacant_land-length'],
                          ['name' => 'Well Field', 'class' => 'vacant_land-length'],
                      ];
                    @endphp
                    <select name="property_items" id="property_items" class="property_items grid-picker"
                      style="justify-content: flex-start;" multiple required>
                      <option value=""></option>
                      @foreach ($property_items as $item)
                        <option value="{{ $item['name'] }}" data-target="" class="card flex-row {{ $item['class'] }}"
                          style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
              {{-- Slide 5 --}}
              {{-- Residential Slide Start --}}
              {{-- Slide 6 --}}
              <div class="wizard-step" data-step="6">
                <div class="form-group">
                  <label class="fw-bold">
                    What type of sale is the seller’s property?
                  </label>
                  @php
                    $seller_property = [['target' => '', 'name' => 'Regular Sale'], ['target' => '', 'name' => 'Pre-Construction'], ['target' => '', 'name' => 'Currently Being Built'], ['target' => '', 'name' => 'New Construction'], ['target' => '', 'name' => 'Bank Owned/REO'], ['target' => '.assignment_contract', 'name' => 'Assignment Contract (Wholesale properties)'], ['target' => '', 'name' => 'Short Sale'], ['target' => '', 'name' => 'Probate'], ['target' => '', 'name' => 'Government Owned'], ['target' => '.custom_special_sale_residential', 'name' => 'Other']];
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
                <div class="form-group custom_special_sale_residential d-none">
                  <label class="fw-bold" for="custom_special_sale_residential">What type of sale is the seller’s
                    property?</label>
                  <input type="text" name="custom_special_sale" id="custom_special_sale"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode" required>
                </div>

                <div class="form-group d-none assignment_contract">
                  <label class="fw-bold">
                    Is the seller currently under contract with a property they would like to assign?
                  </label>
                  @php
                    $seller_contract_options = [['name' => 'Yes', 'target' => '.custom_seller_contract_yes'], ['name' => 'No', 'target' => '.custom_seller_contract_no']];
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

                  <div class="form-group custom_seller_contract_yes d-none">
                    <label class="fw-bold">What fee would the seller pay the agent to assign the contract? </label>
                    <input type="number" class="form-control has-icon" min="0.5" step=".01"
                      placeholder="Seller’s closing costs up to .5%" name="custom_seller_contract_yes"
                      data-icon="fa-solid fa-qrcode" id="custom_seller_contract_yes" required />
                  </div>
                  <div class="form-group custom_seller_contract_no d-none">
                    <label class="fw-bold">Is the seller looking to take over a buyer’s contract?? </label>
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

              </div>
              {{-- Slide 6 --}}
              {{-- Slide 7 --}}
              <div class="wizard-step" data-step="7">
                @php
                  $prop_condition = [['name' => 'Completely Updated: No updates needed.', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Semi-updated: Needs minor updates.', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Not Updated: Requires a complete update.', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tear Down: Requires complete demolition and reconstruction.', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Short Sale', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">
                    What is the condition of the seller's property?
                  </label>
                  <select class="grid-picker" name="prop_condition" onchange="change_sale_provision(this.value)"
                    id="prop_condition" style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($prop_condition as $item)
                      @php
                        if ($item['name'] == 'Other') {
                            $target = '.other_prop_condition_residential';
                        } elseif ($item['name'] == 'Assignment Contract (Wholesale properties)') {
                            $target = '.prop_condition_residential';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                        style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="row prop_condition_residential d-none">
                  <div class="form-group">
                    <label class="fw-bold">Is the seller currently under contract with a property they would like to
                      assign?</label>
                    <select class="grid-picker" name="financing_info" onchange="seller_under_contract(this.value)"
                      id="financing_info" required>
                      <option value="">Select</option>
                      @foreach ($yes_or_nos as $item)
                        <option value="{{ $item['name'] }}" class="card flex-column"
                          style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group has_assignment d-none">
                    <label class="fw-bold">What fee would the seller pay the agent to assign the contract? $ or %</label>
                    <input type="number" name="finder_fee" placeholder="0.00" id="concession" min="0.5"
                      step=".01" class="form-control has-icon search_places col-3" data-icon="fa-solid fa-percent"
                      data-msg-required="Finder Fee" placeholder="% or $">
                  </div>
                  <div class="form-group has_no_assignment d-none">
                    <label class="fw-bold">Is the seller looking to take over a buyer’s contract?</label>
                    <select class="grid-picker" name="another_contract" id="financing_info" required>
                      <option value="">Select</option>
                      @foreach ($yes_or_nos as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                          class="card flex-column" style="width:calc(33.33% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="form-group  d-none" id="other_sale_provision_residential">
                  <label class="fw-bold">What is the condition of the seller's property? </label>
                  <input type="text" name="custom_prop_condition" id="custom_prop_condition"
                    class="form-control has-icon  " data-icon="fa-solid fa-qrcode"
                    data-msg-required="Property Condition" placeholder="">
                </div>
              </div>
              {{-- Slide 7 --}}
              {{-- Slide 8 --}}

              <div class="wizard-step" data-step="8">
                @php
                  $bedrooms = [['name' => '1', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Other', 'target' => '.other_bedrooms_residential']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">How many bedrooms does the property have?</label>
                  <select class="grid-picker" name="bedrooms" id="bedrooms" style="justify-content: center;"
                    required>
                    <option value="">Select</option>
                    @foreach ($bedrooms as $bedroom)
                      <option value="{{ $bedroom['name'] }}"
                        @php
if ($bedroom['name'] == 'Other') {
                                $target = '.other_bedrooms_residential';
                                } else {
                                    $target = '';
                        } @endphp
                        data-target="{{ $bedroom['target'] }}" class="card flex-column"
                        style="width:calc(20% - 10px);" data-icon='<i class="fa-solid fa-bed"></i>'>
                        {{ $bedroom['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group other_bedrooms_residential d-none">
                  <label class="fw-bold" for="other_bedrooms">How many bedrooms does the property have?</label>
                  <input type="text" name="other_bedrooms" id="other_bedrooms" placeholder="Custom Bedrooms"
                    class="form-control has-icon" data-icon="fa-solid fa-bed"
                    data-msg-required="Please enter Custom Bedrooms" required>
                </div>
              </div>
              {{-- Slide 8 --}}
              {{-- Slide 9 --}}
              <div class="wizard-step" data-step="9">
                @php

                  $bathrooms = [['name' => '1', 'target' => ''], ['name' => '1.5', 'target' => ''], ['name' => '2', 'target' => ''], ['name' => '2.5', 'target' => ''], ['name' => '3', 'target' => ''], ['name' => '3.5', 'target' => ''], ['name' => '4', 'target' => ''], ['name' => '4.5', 'target' => ''], ['name' => '5', 'target' => ''], ['name' => '6', 'target' => ''], ['name' => '7', 'target' => ''], ['name' => '8', 'target' => ''], ['name' => '9', 'target' => ''], ['name' => '10', 'target' => ''], ['name' => 'Other', 'target' => '.other_bathrooms_residential']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">How many bathrooms does the property have?</label>
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
                <div class="form-group other_bathrooms_residential d-none">
                  <label class="fw-bold" for="other_bathrooms">How many bathrooms does the property have?</label>
                  <input type="text" name="other_bathrooms" id="other_bathrooms" placeholder="Custom Bathrooms"
                    class="form-control has-icon" data-icon="fa-solid fa-bath"
                    data-msg-required="Please enter Custom Bathrooms" required>
                </div>
              </div>
              {{-- Slide 9 --}}
              {{-- Slide 10 --}}
              <div class="wizard-step" data-step="10">
                <div class="form-group">
                  <label class="fw-bold">What is the heated square footage of the property?</label>
                  <input type="number" name="heated_square_footage" data-type="heated_square_footage" placeholder=""
                    id="heated_square_footage" class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                    data-msg-required="Please Enter Heated Square Footage" required>
                </div>
              </div>
              {{-- Slide 10 --}}
              {{-- Slide 11 --}}
              <div class="wizard-step" data-step="11">
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
                  <label class="fw-bold">What appliances are included in the property? </label>
                  <select class="grid-picker" name="appliances[]" id="appliances" style="justify-content: flex-start;"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($appliances as $appliance)
                      <option value="{{ $appliance['name'] }}" data-target="{{ $appliance['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $appliance['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group custom_appliances d-none">
                    <label class="fw-bold">What appliances are included in the property?</label>
                    <input type="text" class="form-control has-icon" name="custom_appliances"
                      data-icon="fa-regular fa-circle-check" id="custom_appliances" required />
                  </div>
                </div>
              </div>
              {{-- Slide 11 --}}
              {{-- Slide 12 --}}
              <div class="wizard-step" data-step="12">
                @php
                  $occupant_types = [['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => ' Owner-Occupied', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tenant-Occupied ', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">What is the current occupancy status of the property?</label>
                  <select class="grid-picker" name="occupant_type" id="occupant_type"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($occupant_types as $item)
                      @php
                        if ($item['name'] == 'Tenant') {
                            $target = '.tenant_conditions';
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
              </div>
              {{-- Slide 12 --}}
              {{-- Slide 13 --}}
              <div class="wizard-step" data-step="13">
                <div class="form-group">
                  <label class="fw-bold">Does the seller have a specific price in mind that they would
                    like to obtain for their property?</label>
                  <select class="grid-picker" name="seller_specific_price" id="seller_specific_price"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $yes_or_no)
                      @php
                        if ($yes_or_no['name'] == 'Yes') {
                            $target = '.seller_specific_price_residential';
                        } elseif ($yes_or_no['name'] == 'No') {
                            $target = '.select_your_residential';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                        {{ $yes_or_no['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group seller_specific_price_residential d-none">
                  <label class="fw-bold">What price would the seller like to obtain for their property?</label>
                  <input type="text" class="form-control has-icon" name="custom_seller_specific_price"
                    data-icon="fa-regular fa-circle-check" id="custom_listing_terms" required />
                </div>

                <div class="form-group select_your_residential d-none">
                  <label class="fw-bold">
                    How much do you think your property will sell for?
                  </label>
                  @php
                    $expectations = [['name' => '100k or less'], ['name' => '100k-200k'], ['name' => '200k-300k'], ['name' => '300k-400k'], ['name' => '400k-500k'], ['name' => '500k-600k'], ['name' => '600k-700k'], ['name' => '700k-800k'], ['name' => '800-900k'], ['name' => '900k-1 million'], ['name' => '1 million +'], ['name' => '2 million +'], ['name' => '3 million +'], ['name' => '4 million +'], ['name' => '5 million +'], ['name' => '10 million +']];
                  @endphp
                  <select name="expectation" id="expectation" class="grid-picker" style="justify-content: flex-start;"
                    required>
                    <option value=""></option>
                    @foreach ($expectations as $item)
                      <option value="{{ $item['name'] }}" data-target="" class="card flex-column"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              {{-- Slide 13 --}}
              {{-- Slide 14 --}}
              <div class="wizard-step" data-step="14">
                @php
                  $financings = [['name' => 'Cash', 'target' => ''], ['name' => 'Conventional', 'target' => ''], ['name' => 'Seller Financing', 'target' => '.custom_seller_financing'], ['name' => 'FHA', 'target' => ''], ['name' => 'Jumbo', 'target' => ''], ['name' => 'VA', 'target' => ''], ['name' => 'No-Doc', 'target' => ''], ['name' => 'Non-QM', 'target' => ''], ['name' => 'Assumable', 'target' => '.custom_assumable'], ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade'], ['name' => 'Lease Option', 'target' => ''], ['name' => 'Lease Purchase', 'target' => ''], ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency'], ['name' => 'NFT', 'target' => ''], ['name' => 'USDA', 'target' => ''], ['name' => 'Other', 'target' => '.custom_financing_residential']];
                @endphp
                <div class="row align-items-end mt-4">
                  <div class="col-md-12">
                    <label class="fw-bold">Select the types of financing/currency that is acceptable to the
                      seller:</label>
                    <div class="select2-parent">
                      <select name="financings[]" class="grid-picker" multiple id="financingOptions">
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
                <div class="form-group custom_financing_residential d-none">
                  <label class="fw-bold">What type of financing/currency is acceptable to the seller? </label>
                  <input type="number" name="type_of_financing" data-type="type_of_financing" id="type_of_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_exchange_trade d-none">
                  <label class="fw-bold">What item does the seller want to Exchange/Trade, and what is the value of the
                    seller's acceptable Exchange/Trade item? How is the value determined, and how much additional cash
                    does the seller require on top of the Exchange/Trade item? Additionally, are there specific criteria
                    or conditions for the type of item the seller is willing to Exchange/Trade? </label>
                  <input type="text" name="custom_exchange_trade" data-type="custom_exchange_trade"
                    id="custom_exchange_trade" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group" id="custom_lease_option" style="display: none;">
                  <label class="fw-bold">What are the seller's terms for a lease option or lease purchase, including
                    the
                    desired offering price, specific terms, proposed lease duration, monthly payment amount, any
                    conditions or requirements, presence of an option fee, and potential changes in offering price
                    during
                    the lease period? </label>
                  <input type="text" name="custom_lease" data-type="custom_lease" id="custom_lease"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_seller_financing d-none">
                  <label class="fw-bold">What are the seller's terms for seller financing, including the desired price,
                    offered terms, acceptable down payment, interest rate, expected security or collateral, and any
                    prepayment penalties or conditions for early repayment?</label>
                  <input type="text" name="custom_seller_financing" data-type="custom_seller_financing"
                    id="custom_seller_financing" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_assumable d-none">
                  <label class="fw-bold">What are the assumable terms offered by the seller, and are there any
                    restrictions or qualifications for a buyer assuming the existing financing? Additionally, is there any
                    outstanding balance on the existing financing that the buyer would need to cover?</label>
                  <input type="text" name="custom_assumable" data-type="custom_assumable" id="custom_assumable"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_cryptocurrency d-none">
                  <label class="fw-bold">What type of Cryptocurrency would the seller accept?</label><br>
                  <label class="fw-bold">Note:Cryptocurrency can be converted to cash at closing. </label>
                  <input type="text" name="custom_cryptocurrency" data-type="custom_cryptocurrency"
                    id="custom_cryptocurrency" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>


              </div>
              {{-- Slide 14 --}}
              {{-- Slide 15 --}}
              <div class="wizard-step" data-step="15">
                <div class="form-group">
                  <label class="fw-bold">
                    When will the property be ready to sell?
                  </label>
                  @php
                    $timeframes = [['name' => 'Now', 'target' => ''], ['name' => '1 month', 'target' => ''], ['name' => '2 months', 'target' => ''], ['name' => '3 months', 'target' => ''], ['name' => '4 months', 'target' => ''], ['name' => '5 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => 'Over 6 months', 'target' => ''], ['name' => 'Not looking to sell', 'target' => ''], ['name' => 'Other', 'target' => '']];
                  @endphp
                  <select name="selling_timeframe" id="selling_timeframe" class="grid-picker"
                    style="justify-content: flex-start;" onchange="showPrompt(this.value);" required>
                    <option value=""></option>
                    @foreach ($timeframes as $item)
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
                <div class="form-group  d-none" id="custom_timeframe_residential">
                  <label class="fw-bold">When will the property be ready to sell? </label>
                  <input type="texts" name="custom_timeframe" data-type="custom_timeframe" id="custom_timeframe"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Your Custom Timeframe" required>
                </div>
                <div class="form-group prompt_response d-none">
                  <p class="text-danger">This is a service designed for sellers who are seeking to sell
                    their property within three months or less.</p>
                </div>
              </div>
              {{-- Slide 15 --}}
              {{-- Slide 16 --}}
              <div class="wizard-step" data-step="16">
                <div class="form-group">
                  <label class="fw-bold">
                    What is the timeframe offered to the agent in the Seller Agency agreement?
                  </label>
                  @php
                    $listing_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '12 months', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_listing_terms_residential']];
                  @endphp
                  <select name="listing_term" id="listing_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($listing_terms as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_listing_terms_residential d-none">
                  <label class="fw-bold">What is the timeframe offered to the agent in the Seller Agency
                    agreement?</label>
                  <input type="text" class="form-control has-icon" placeholder="Write Custom Terms"
                    name="custom_listing_terms" data-icon="fa-solid fa-qrcode" id="custom_listing_terms" required />
                </div>
              </div>
              {{-- Slide 16 --}}
              {{-- Slide 17 --}}
              <div class="wizard-step" data-step="17">
                <div class="form-group">
                  <label class="fw-bold">
                    What is the total commission being offered to the agent?
                  </label>
                  @php
                    $offered_commissions = [['name' => '5%', 'target' => ''], ['name' => '5.5%', 'target' => ''], ['name' => '6%', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_offered_commission_residential']];
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
                <div class="form-group custom_offered_commission_residential d-none">
                  <label>What is the total commission being offered to the agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="Write Custom Commission"
                    name="custom_offered_commission" data-icon="fa-solid fa-qrcode" id="custom_offered_commission"
                    required />
                </div>
                <div class="form-group">
                  <label class="fw-bold">
                    Would the seller like to request that the agent contribute a portion of their commission towards the
                    buyer's closing costs to further promote their property?
                  </label>
                  @php
                    $contribute_terms = [['name' => 'Yes', 'target' => ''], ['name' => 'No', 'target' => '']];
                  @endphp
                  <select name="contribute_term" id="contribute_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($contribute_terms as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>

                </div>
                {{-- <div class="form-group custom_contribute_terms_residential d-none">
                  <label>How much would the seller like the agent to contribute towards the buyer's closing costs? (Up to
                    0.5%): %</label>
                  <input type="number" class="form-control has-icon" min="0.5" step=".01"
                    placeholder="Seller’s closing costs up to .5%" name="custom_contribute_terms"
                    data-icon="fa-solid fa-qrcode" id="custom_contribute_terms" required />
                </div> --}}
              </div>
              {{-- Slide 17 --}}
              {{-- Slide 18 --}}
              <div class="wizard-step" data-step="18">
                <div class="form-group ">
                  <label class="fw-bold">
                    Please select the services that the seller requests from the agent:
                  </label>
                  @php
                    $services_data = [
                        ['name' => "Conduct a thorough comparative market analysis (CMA) to determine the property's value and pricing strategy.", 'target' => ''],
                        ['name' => 'Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.', 'target' => ''],
                        ['name' => 'Offer expert negotiation skills to secure the best possible terms and price during the selling process.', 'target' => ''],
                        ['name' => 'Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.', 'target' => ''],
                        ['name' => "Provide professional photos to showcase the property's best features.", 'target' => ''],
                        ['name' => "Offer aerial photography to capture the property's surroundings and neighborhood.", 'target' => ''],
                        ['name' => "Provide a professional video to showcase the property's interior and exterior.", 'target' => ''],
                        ['name' => "Provide a 3D tour to showcase the property's interior.", 'target' => ''],
                        ['name' => 'Provide a floor plan of the property to showcase its layout and spatial configuration.', 'target' => ''],
                        ['name' => 'Provide recommendations for home staging professionals.', 'target' => ''],
                        ['name' => 'List the property on the Bid Your Offer platform.', 'target' => ''],
                        ['name' => 'List the property on the MLS.', 'target' => ''],
                        ['name' => "List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, to increase the property's visibility and exposure.", 'target' => ''],
                        ['name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads.', 'target' => ''],
                        ['name' => 'Assist with the development of a strategic marketing plan tailored to the property and target buyers.', 'target' => ''],
                        ['name' => 'Promote the property on social media platforms.', 'target' => ''],
                        ['name' => "Implement neighborhood marketing with a QR code or listing link that leads to the property's listing on the BidYourOffer.com platform.", 'target' => ''],
                        ['name' => "Provide virtual staging to enhance the property's visual appeal and attract potential buyers.", 'target' => ''],
                        ['name' => 'Host an Open House(s).', 'target' => ''],
                        ['name' => "Send email alerts to buyers searching for properties that match the property's criteria the moment the property is listed directly through the MLS.", 'target' => ''],
                        ['name' => 'Provide guidance and support throughout the entire transaction, from listing to closing, to ensure a seamless and successful sale.', 'target' => ''],
                        ['name' => 'Assist with the completion and submission of all necessary paperwork and documentation related to the sale.', 'target' => ''],
                        ['name' => 'Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.', 'target' => ''],
                        ['name' => 'Provide regular updates on market activity, showings, and feedback from potential buyers.', 'target' => ''],
                        ['name' => 'Offer post-sale assistance, such as recommending reputable service providers for moving, repairs, or other post-sale needs.', 'target' => ''],
                        ['name' => 'Other - Add additional services as needed.', 'target' => '.commercial_service_residential'],
                    ];
                  @endphp

                  <select name="services[]" id="commercial-services" multiple class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($services_data as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(100%);"
                        data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group commercial_service_residential d-none">
                  <label class="fw-bold">What additional services would the seller like to request from an agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="Write Commercial Services"
                    name="commercial_servic" data-icon="fa-regular fa-circle-check" id="commercial_servic" required />
                </div>
              </div>
              {{-- Slide 18 --}}
              {{-- Slide 19 --}}
              <div class="wizard-step" data-step="19">

                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What are the most important aspects you will consider when hiring a real estate
                      agent?

                    </label>
                    <textarea name="important_aspect" class="form-control" rows="5" required
                      data-msg-required="This Field is Required">{{ old('description') }}</textarea>
                  </div>
                </div>

                <div class="row removeIncome">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What additional details would the seller like to share with the agent?
                    </label>
                    <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5"></textarea>
                  </div>
                </div>
              </div>
              {{-- Slide 19 --}}
              {{-- Residential Slide End --}}
              {{-- Income Slide Start --}}
              {{-- Slide 6 --}}
              <div class="wizard-step" data-step="20">
                <div class="form-group">
                  <label class="fw-bold">
                    What type of sale is the seller’s property?
                  </label>
                  @php
                    $special_sales = [['target' => '', 'name' => 'Regular Sale'], ['target' => '', 'name' => 'Pre-Construction'], ['target' => '', 'name' => 'Currently Being Built'], ['target' => '', 'name' => 'New Construction'], ['target' => '', 'name' => 'REO/Bank Owned'], ['target' => '.assignment_contract_income', 'name' => 'Assignment Contract (Wholesale properties)'], ['target' => '', 'target' => '', 'name' => 'Government Owned'], ['target' => '', 'name' => 'Short Sale'], ['target' => '', 'name' => 'Probate'], ['target' => '.custom_special_sale_income', 'name' => 'Other']];
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
                <div class="form-group custom_special_sale_income d-none">
                  <label class="fw-bold" for="custom_special_sale_residential">What type of sale is the seller’s
                    property?</label>
                  <input type="text" name="custom_special_sale" id="custom_special_sale"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode" required>
                </div>
                <div class="form-group d-none assignment_contract_income">
                  <label class="fw-bold">
                    Is the seller currently under contract with a property they would like to assign?
                  </label>
                  @php
                    $seller_contract_options_income = [['name' => 'Yes', 'target' => '.custom_seller_income_yes'], ['name' => 'No', 'target' => '.custom_seller_income_no']];
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

                  <div class="form-group custom_seller_income_yes d-none">
                    <label class="fw-bold">What fee would the seller pay the agent to assign the contract? </label>
                    <input type="number" class="form-control has-icon" min="0.5" step=".01"
                      placeholder="Seller’s closing costs up to .5%" name="custom_seller_income_yes"
                      data-icon="fa-solid fa-qrcode" id="custom_seller_income_yes" required />
                  </div>
                  <div class="form-group custom_seller_income_no d-none">
                    <label class="fw-bold">Is the seller looking to take over a buyer’s contract?? </label>
                    @php
                      $seller_contract_yes_no = [['name' => 'Yes', 'target' => ''], ['name' => 'No', 'target' => '']];
                    @endphp
                    <select name="custom_seller_income_no" id="custom_seller_income_no" class="grid-picker"
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
              </div>
              {{-- Slide 6 --}}
              {{-- Slide 7 --}}
              <div class="wizard-step" data-step="21">
                @php
                  $prop_condition = [['name' => 'Completely Updated: No updates needed.', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Semi-updated: Needs minor updates.', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Not Updated: Requires a complete update.', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tear Down: Requires complete demolition and reconstruction.', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">
                    What is the condition of the seller's property?
                  </label>
                  <select class="grid-picker" name="prop_condition" onchange="change_sale_provision(this.value)"
                    id="prop_condition" style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($prop_condition as $item)
                      @php
                        if ($item['name'] == 'Other') {
                            $target = '.other_prop_condition_income';
                        } elseif ($item['name'] == 'Assignment Contract (Wholesale properties)') {
                            $target = '.prop_condition_income';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                        style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="row other_prop_condition_income d-none">
                  <div class="form-group">
                    <label class="fw-bold">What is the condition of the seller's property? </label>
                    <select class="grid-picker" name="financing_info" onchange="seller_under_contract(this.value)"
                      id="financing_info" required>
                      <option value="">Select</option>
                      @foreach ($yes_or_nos as $item)
                        <option value="{{ $item['name'] }}" class="card flex-column"
                          style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
              {{-- Slide 7 --}}
              {{-- Slide 8 --}}
              <div class="wizard-step" data-step="22">
                <h4>Units</h4>
                {{-- Add Unit --}}
                <label class="fw-bold">Please provide the information on each unit type that the seller intends to
                  sell.</label>
                @php
                  $unit_types = [['name' => '1 Bed/1 Bath'], ['name' => '1 Bedroom'], ['name' => '2 Bed/1 Bath'], ['name' => '2 Bed/2 Bath'], ['name' => '2 Bedroom'], ['name' => '3 Bed/1 Bath'], ['name' => '3 Bed/2 Bath'], ['name' => '3 Bedroom'], ['name' => '4 Bedroom or More'], ['name' => '4+ Bed/1 Bath'], ['name' => '4+ Bed/2 Bath'], ['name' => 'Apartments'], ['name' => 'Efficiency'], ['name' => 'Loft'], ['name' => "Manager's Unit"]];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Unit Type</label>
                  <select class="grid-picker" name="unit_types" id="condition_interested" required>
                    <option value="">Select</option>
                    @foreach ($unit_types as $unity_type)
                      <option value="{{ $unity_type['name'] }}" class="card flex-row" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $unity_type['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Beds/Unit</label>
                  <input type="text" class="form-control has-icon" name="unit_beds" data-icon="fa-solid fa-qrcode"
                    id="unit_beds" required />
                </div>
                <div class="form-group">
                  <label class="fw-bold">Baths/Unit</label>
                  <input type="text" class="form-control has-icon" name="unit_baths" data-icon="fa-solid fa-qrcode"
                    id="unit_baths" required />
                </div>
                <div class="form-group">
                  <label class="fw-bold">Heated Sqft</label>
                  <input type="text" class="form-control has-icon" name="sqft_heated_unit"
                    data-icon="fa-solid fa-qrcode" id="sqft_heated_unit" required />
                </div>
                <div class="form-group">
                  <label class="fw-bold"># of Units</label>
                  <input type="text" class="form-control has-icon" name="number_of_units"
                    data-icon="fa-solid fa-qrcode" id="number_of_units" required />
                </div>
                <div class="form-group">
                  <label class="fw-bold"># Occupied</label>
                  <input type="text" class="form-control has-icon" name="number_occupied"
                    data-icon="fa-solid fa-qrcode" id="number_occupied" required />
                </div>
                <div class="form-group">
                  <label class="fw-bold">Expected Rent</label>
                  <input type="text" class="form-control has-icon" name="expected_rent"
                    data-icon="fa-solid fa-qrcode" id="expected_rent" required />
                </div>
              </div>
              {{-- Slide 8 --}}
              {{-- Slide 9 --}}
              <div class="wizard-step" data-step="23">
                <div class="form-group">
                  @php
                    $rent_include = [['target' => '', 'name' => 'Cable TV'], ['target' => '', 'name' => 'Electricity'], ['target' => '', 'name' => 'Gas'], ['target' => '', 'name' => 'Grounds Care'], ['target' => '', 'name' => 'Insurance'], ['target' => '', 'name' => 'Internet'], ['target' => '', 'name' => 'Laundry'], ['target' => '', 'name' => 'Management'], ['target' => '', 'name' => 'None'], ['target' => '', 'name' => 'Pest Control'], ['target' => '', 'name' => 'Pool Maintenance'], ['target' => '', 'name' => 'Recreational'], ['target' => '', 'name' => 'Repairs'], ['target' => '', 'name' => 'Security'], ['target' => '', 'name' => 'Sewer'], ['target' => '', 'name' => 'Taxes'], ['target' => '', 'name' => 'Telephone'], ['target' => '', 'name' => 'Trash Collection'], ['target' => '', 'name' => 'Water'], ['target' => '.custom_rent', 'name' => 'Other']];
                  @endphp
                  <label class="fw-bold">What is included in the rent?</label>
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
                  <label class="fw-bold">What is included in the rent? </label>
                  <input type="text" class="form-control has-icon" name="custom_rent"
                    data-icon="fa-solid fa-qrcode" id="custom_rent" required />
                </div>
              </div>
              {{-- Slide 9 --}}
              {{-- Slide 10 --}}

              <div class="wizard-step" data-step="24">
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
                  <label class="fw-bold">What appliances are included in the property? </label>
                  <select class="grid-picker" name="appliances[]" id="appliances" style="justify-content: flex-start;"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($appliances as $appliance)
                      <option value="{{ $appliance['name'] }}" data-icon='<i class="fa-regular fa-check-circle"></i>'
                        data-target="{{ $appliance['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-target="{{ $item['target'] }}">
                        {{ $appliance['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group custom_appliances d-none">
                    <label class="fw-bold">What appliances are included in the property?</label>
                    <input type="text" class="form-control has-icon" name="custom_rent"
                      data-icon="fa-solid fa-qrcode" id="custom_rent" required />
                  </div>
                </div>
              </div>
              {{-- Slide 10 --}}
              {{-- Slide 11 --}}
              <div class="wizard-step" data-step="25">
                <div class="form-group">
                  <label class="fw-bold">Does the seller have a specific price in mind that they would
                    like to obtain for their property?</label>
                  <select class="grid-picker" name="seller_specific_price" id="seller_specific_price"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $yes_or_no)
                      @php
                        if ($yes_or_no['name'] == 'Yes') {
                            $target = '.seller_specific_price_income';
                        } elseif ($yes_or_no['name'] == 'No') {
                            $target = '.select_your_income';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                        {{ $yes_or_no['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group seller_specific_price_income d-none">
                  <label class="fw-bold">What price would the seller like to obtain for their property?</label>
                  <input type="text" class="form-control has-icon" name="custom_seller_specific_price"
                    data-icon="fa-solid fa-qrcode" id="custom_listing_terms" required />
                </div>

                <div class="form-group select_your_income d-none">
                  <label class="fw-bold">
                    How much do you think your property will sell for?
                  </label>
                  @php
                    $expectations = [['name' => '100k or less'], ['name' => '100k-200k'], ['name' => '200k-300k'], ['name' => '300k-400k'], ['name' => '400k-500k'], ['name' => '500k-600k'], ['name' => '600k-700k'], ['name' => '700k-800k'], ['name' => '800-900k'], ['name' => '900k-1 million'], ['name' => '1 million +'], ['name' => '2 million +'], ['name' => '3 million +'], ['name' => '4 million +'], ['name' => '5 million +'], ['name' => '10 million +']];
                  @endphp
                  <select name="expectation" id="expectation" class="grid-picker" style="justify-content: flex-start;"
                    required>
                    <option value=""></option>
                    @foreach ($expectations as $item)
                      <option value="{{ $item['name'] }}" data-target="" class="card flex-column"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              {{-- Slide 11 --}}
              {{-- Slide 12 --}}
              {{-- <div class="wizard-step" data-step="26">
                @php
                  $financings = [['name' => 'Cash', 'target' => ''], ['name' => 'Conventional', 'target' => ''], ['name' => 'Seller Financing', 'target' => ''], ['name' => 'FHA', 'target' => ''], ['name' => 'Jumbo', 'target' => ''], ['name' => 'VA', 'target' => ''], ['name' => 'No-Doc', 'target' => ''], ['name' => 'Non-QM', 'target' => ''], ['name' => 'Assumable', 'target' => ''], ['name' => 'Exchange/Trade', 'target' => ''], ['name' => 'Lease Option', 'target' => ''], ['name' => 'Lease Purchase', 'target' => ''], ['name' => 'Private Financing Available', 'target' => ''], ['name' => 'Special Funding', 'target' => ''], ['name' => 'Other', 'target' => '']];
                @endphp
                <div class="row align-items-end mt-4">
                  <div class="col-md-12">
                    <label class="fw-bold">Select the types of financing/currency that is acceptable to the
                      seller:</label>
                    <div class="select2-parent">
                      <select name="financings[]" class="grid-picker" multiple>
                        <option value=""></option>
                        @foreach ($financings as $financing)
                          @php
                            if ($financing['name'] == 'Other') {
                                $target = '.custom_financing_income';
                            } else {
                                $target = '';
                            }
                          @endphp
                          <option value="{{ $financing['name'] }}" data-target="{{ $target }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $financing['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>

                  </div>
                </div>
                <div class="form-group custom_financing_income d-none">
                  <label class="fw-bold">What type of financing/currency is acceptable to the seller? </label>
                  <input type="number" name="type_of_financing" data-type="type_of_financing" id="type_of_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing" required>
                </div>
              </div> --}}
              {{-- <div class="wizard-step" data-step="26">
                @php
                  $financings_income = [['name' => 'Cash', 'target' => ''], ['name' => 'Conventional', 'target' => ''], ['name' => 'Seller Financing', 'target' => '.custom_seller_financing'], ['name' => 'FHA', 'target' => ''], ['name' => 'Jumbo', 'target' => ''], ['name' => 'VA', 'target' => ''], ['name' => 'No-Doc', 'target' => ''], ['name' => 'Non-QM', 'target' => ''], ['name' => 'Assumable', 'target' => '.custom_assumable_income'], ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade_income'], ['name' => 'Lease Option', 'target' => ''], ['name' => 'Lease Purchase', 'target' => ''], ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency_income'], ['name' => 'NFT', 'target' => ''], ['name' => 'USDA', 'target' => ''], ['name' => 'Other', 'target' => '.custom_financing_income']];
                @endphp
                <div class="row align-items-end mt-4">
                  <div class="col-md-12">
                    <label class="fw-bold">Select the types of financing/currency that is acceptable to the
                      seller:</label>
                    <div class="select2-parent">
                      <select name="financings_income[]" class="grid-picker" multiple id="financingOptionsIncome">
                        @foreach ($financings_income as $financing)
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
                <div class="form-group custom_financing_income d-none">
                  <label class="fw-bold">What type of financing/currency is acceptable to the seller? </label>
                  <input type="number" name="type_of_financing" data-type="type_of_financing" id="type_of_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_exchange_trade_income d-none">
                  <label class="fw-bold">What item does the seller want to Exchange/Trade, and what is the value of the
                    seller's acceptable Exchange/Trade item? How is the value determined, and how much additional cash
                    does the seller require on top of the Exchange/Trade item? Additionally, are there specific criteria
                    or conditions for the type of item the seller is willing to Exchange/Trade? </label>
                  <input type="text" name="custom_exchange_trade_income" data-type="custom_exchange_trade_income"
                    id="custom_exchange_trade_income" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group" id="custom_lease_optionIncome" style="display: none;">
                  <label class="fw-bold">What are the seller's terms for a lease option or lease purchase, including
                    the
                    desired offering price, specific terms, proposed lease duration, monthly payment amount, any
                    conditions or requirements, presence of an option fee, and potential changes in offering price
                    during
                    the lease period? </label>
                  <input type="text" name="lease_optionIncome" data-type="lease_optionIncome"
                    id="lease_optionIncome" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_seller_financing d-none">
                  <label class="fw-bold">What are the seller's terms for seller financing, including the desired price,
                    offered terms, acceptable down payment, interest rate, expected security or collateral, and any
                    prepayment penalties or conditions for early repayment?</label>
                  <input type="text" name="custom_seller_financing" data-type="custom_seller_financing"
                    id="custom_seller_financing" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_assumable_income d-none">
                  <label class="fw-bold">What are the assumable terms offered by the seller, and are there any
                    restrictions or qualifications for a buyer assuming the existing financing? Additionally, is there any
                    outstanding balance on the existing financing that the buyer would need to cover?</label>
                  <input type="text" name="custom_assumable_income" data-type="custom_assumable_income"
                    id="custom_assumable_income" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_cryptocurrency_income d-none">
                  <label class="fw-bold">What type of Cryptocurrency would the seller accept?</label><br>
                  <label class="fw-bold">Note:Cryptocurrency can be converted to cash at closing. </label>
                  <input type="text" name="custom_cryptocurrency_income" data-type="custom_cryptocurrency_income"
                    id="custom_cryptocurrency_income" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>


              </div> --}}
              {{-- Slide 14 --}}
              {{-- Slide 12 --}}
              {{-- Slide 13 --}}
              <div class="wizard-step" data-step="27">
                <div class="form-group">
                  <label class="fw-bold">
                    When will the property be ready to sell?
                  </label>
                  @php
                    $timeframes = [['name' => 'Now', 'target' => ''], ['name' => '1 month', 'target' => ''], ['name' => '2 months', 'target' => ''], ['name' => '3 months', 'target' => ''], ['name' => '4 months', 'target' => ''], ['name' => '5 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => 'Over 6 months', 'target' => ''], ['name' => 'Not looking to sell', 'target' => ''], ['name' => 'Other', 'target' => '']];
                  @endphp
                  <select name="selling_timeframe" id="selling_timeframe" class="grid-picker"
                    style="justify-content: flex-start;" onchange="showPrompt(this.value);" required>
                    <option value=""></option>
                    @foreach ($timeframes as $item)
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
                <div class="form-group  d-none" id="custom_timeframe_income">
                  <label class="fw-bold">When will the property be ready to sell? </label>
                  <input type="texts" name="custom_timeframe" data-type="custom_timeframe" id="custom_timeframe"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Your Custom Timeframe" required>
                </div>
                <div class="form-group prompt_response d-none">
                  <p class="text-danger">This is a service designed for sellers who are seeking to sell
                    their property within three months or less.</p>
                </div>
              </div>
              {{-- Slide 13 --}}
              {{-- Slide 14 --}}
              <div class="wizard-step" data-step="28">
                <div class="form-group">
                  <label class="fw-bold">
                    What are the terms of the listing agreement you are offering to the agent?
                  </label>
                  @php
                    $listing_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '12 months', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_listing_terms_income']];
                  @endphp
                  <select name="listing_term" id="listing_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($listing_terms as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_listing_terms_income d-none">
                  <label>What are the terms of the listing agreement you are offering to the agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="Write Custom Terms"
                    name="custom_listing_terms" data-icon="fa-solid fa-qrcode" id="custom_listing_terms" required />
                </div>
              </div>
              {{-- Slide 14 --}}
              {{-- Slide 15 --}}
              <div class="wizard-step" data-step="29">
                <div class="form-group">
                  <label class="fw-bold">
                    What is the total commission being offered to the agent?
                  </label>
                  @php
                    $offered_commissions = [['name' => '5%', 'target' => ''], ['name' => '5.5%', 'target' => ''], ['name' => '6%', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_offered_commission_income']];
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
                <div class="form-group custom_offered_commission_income d-none">
                  <label>What is the total commission being offered to the agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="Write Custom Commission"
                    name="custom_offered_commission" data-icon="fa-solid fa-qrcode" id="custom_offered_commission"
                    required />
                </div>
                <div class="form-group">
                  <label class="fw-bold">
                    Would the seller like to request the agent to contribute up to half a percent of their commission
                    towards the buyer's closing costs in order to further promote their property?
                  </label>
                  @php
                    $contribute_terms = [['name' => 'Yes', 'target' => '.custom_contribute_terms_income'], ['name' => 'No', 'target' => ''], ['name' => 'Optional', 'target' => '']];
                  @endphp
                  <select name="contribute_term" id="contribute_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($contribute_terms as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column fw-bold" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>

                </div>
                <div class="form-group custom_contribute_terms_income d-none">
                  <label>How much would the seller like the agent to contribute towards the buyer's closing costs? (Up
                    to
                    0.5%): %</label>
                  <input type="number" class="form-control has-icon" min="0.5" step=".01"
                    placeholder="Seller’s closing costs up to .5%" name="custom_contribute_terms"
                    data-icon="fa-solid fa-qrcode" id="custom_contribute_terms" required />
                </div>
              </div>
              {{-- Slide 15 --}}
              {{-- Slide 16 --}}
              <div class="wizard-step commercial_business" data-step="30">
                <div class="form-group ">
                  <label class="fw-bold">
                    Please select the services that the seller requests from the agent3:
                  </label>
                  @php
                    $services_data = [
                        ['name' => "Conduct a thorough comparative market analysis (CMA) to determine the property's value and pricing strategy.", 'target' => ''],
                        ['name' => 'Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.', 'target' => ''],
                        ['name' => 'Offer expert negotiation skills to secure the best possible terms and price during the selling process.', 'target' => ''],
                        ['name' => 'Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.', 'target' => ''],
                        ['name' => "Provide professional photos to showcase the property's best features.", 'target' => ''],
                        ['name' => "Offer aerial photography to capture the property's surroundings and neighborhood.", 'target' => ''],
                        ['name' => "Provide a professional video to showcase the property's interior and exterior.", 'target' => ''],
                        ['name' => "Provide a 3D tour to showcase the property's interior.", 'target' => ''],
                        ['name' => 'Provide a floor plan of the property to showcase its layout and spatial configuration.', 'target' => ''],
                        ['name' => 'Provide recommendations for home staging professionals.', 'target' => ''],
                        ['name' => 'List the property on the MLS.', 'target' => ''],
                        ['name' => 'List the property on Loopnet, a major commercial real estate website.', 'target' => ''],
                        ['name' => 'List the property on Crexi, a major commercial real estate website.', 'target' => ''],
                        ['name' => 'List the property on the Bid Your Offer platform.', 'target' => ''],
                        ['name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads.', 'target' => ''],
                        ['name' => 'Assist with the development of a strategic marketing plan tailored to the property and target buyers.', 'target' => ''],
                        ['name' => 'Promote the property on social media platforms.', 'target' => ''],
                        ['name' => "Implement neighborhood marketing with a QR code or listing link that leads to the property's listing on the BidYourOffer.com platform.", 'target' => ''],
                        ['name' => "Provide virtual staging to enhance the property's visual appeal and attract potential buyers.", 'target' => ''],
                        ['name' => "Send email alerts to buyers searching for properties that match the property's criteria the moment the property is listed directly through the MLS.", 'target' => ''],
                        ['name' => 'Assist with the completion and submission of all necessary paperwork and documentation related to the sale.', 'target' => ''],
                        ['name' => 'Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.', 'target' => ''],
                        ['name' => 'Provide regular updates on market activity, showings, and feedback from potential buyers.', 'target' => ''],
                        ['name' => 'Assist with the smooth transition and transfer of ownership, including coordinating with attorneys, title companies, and other professionals involved in the closing process.', 'target' => ''],
                        ['name' => 'Offer post-sale assistance, such as recommending reputable service providers for moving, repairs, or other post-sale needs.', 'target' => ''],
                        ['name' => 'Other - Add additional services as needed.', 'target' => '.commercial_service_income'],
                    ];
                  @endphp


                  <select name="services[]" id="commercial-services" multiple class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($services_data as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row " style="width:calc(100%);"
                        data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group commercial_service_income d-none">
                  <label>Add additional services as needed.</label>
                  <input type="text" class="form-control has-icon" placeholder="Write Commercial Services"
                    name="commercial_servic" data-icon="fa-regular fa-circle-check" id="commercial_servic" required />
                </div>
              </div>
              {{-- Slide 16 --}}
              {{-- Slide 17 --}}
              <div class="wizard-step" data-step="31">

                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What are the most important aspects you will consider when hiring a real estate
                      agent?

                    </label>
                    <textarea name="important_aspect" class="form-control" rows="5" required
                      data-msg-required="This Field is Required">{{ old('description') }}</textarea>
                  </div>
                </div>

                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What additional details would the seller like to share with the agent?
                    </label>
                    <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5"></textarea>
                  </div>
                </div>
              </div>
              {{-- Slide 17 --}}
              {{-- Slide 18 --}}
              <div class="wizard-step" data-step="32">
                <div class="form-group">
                  <label class="fw-bold">Does the seller have a preferred agent(s) whom they would like to
                    notify to participate in the auction?</label>
                  <select class="grid-picker" name="preferred_agent" onchange="change_buyer_prefered_agent(this.value)"
                    id="preferred_agent" style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                        style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group  d-none" id="explains_income">
                  <h4>Please enter the first name, last name, brokerage, phone number, and email address
                    of the preferred agent(s):</h4>
                  <div class="form-group">
                    <label class="fw-bold">First name:</label>
                    <input type="text" name="first_name" placeholder="Enter first name" id="first_name"
                      class="form-control has-icon" data-icon="fa-solid fa-user"
                      data-msg-required="Please enter first name" required>
                  </div>

                  <div class="form-group">
                    <label class="fw-bold">Last name:</label>
                    <input type="text" name="last_name" placeholder="Enter last name" id="last_name"
                      class="form-control has-icon" data-icon="fa-solid fa-user"
                      data-msg-required="Please enter last name" required>
                  </div>

                  <div class="form-group">
                    <label class="fw-bold">Brokerage:</label>
                    <input type="text" name="brokerage" data-type="brokerages" placeholder="Enter brokerage"
                      id="brokerage" class="form-control has-icon" data-icon="fa-solid fa-house"
                      data-msg-required="Please enter brokerage" required>
                  </div>

                  <div class="form-group">
                    <label class="fw-bold">Cell phone #:</label>
                    <input type="text" name="phone" data-type="phones" placeholder="Enter phone number"
                      id="phone" class="form-control has-icon" data-icon="fa-solid fa-phone"
                      data-msg-required="Please enter phone number" required>
                  </div>

                  <div class="form-group">
                    <label class="fw-bold">Email:</label>
                    <input type="text" name="email" data-type="emails" placeholder="Enter your email address"
                      id="email" class="form-control has-icon" data-icon="fa-solid fa-envelope"
                      data-msg-required="Please enter email address">
                  </div>
                </div>
              </div>
              {{-- Slide 18 --}}
              {{-- Income Slide End --}}
              {{-- Commercial and Bussiness Slide Start --}}
              {{-- Slide 6 --}}
              <div class="wizard-step" data-step="33">
                <div class="form-group">
                  <label class="fw-bold">
                    What type of sale is the seller’s property?
                  </label>
                  @php
                    $special_sales = [['name' => 'Regular Sale'], ['name' => 'Pre-Construction'], ['name' => 'Currently Being Built'], ['name' => 'New Construction'], ['name' => 'REO/Bank Owned'], ['name' => 'Assignment Contract (Wholesale properties)'], ['name' => 'Short Sale'], ['name' => 'Probate'], ['name' => 'Other']];
                  @endphp
                  <div class="select2-parent">
                    <select name="special_sale" id="special_sale" class="grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($special_sales as $item)
                        @php
                          if ($item['name'] == 'Other') {
                              $target = '.custom_special_sale_commercial_and_business';
                          } else {
                              $target = '';
                          }
                        @endphp
                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                          class="card flex-row " style="width:calc(33.33% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="form-group custom_special_sale_commercial_and_business d-none">
                  <label class="fw-bold" for="custom_special_sale_residential">What type of sale is the seller’s
                    property?</label>
                  <input type="text" name="custom_special_sale" id="custom_special_sale"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode" required>
                </div>
              </div>
              {{-- Slide 6 --}}
              {{-- Slide 7 --}}
              <div class="wizard-step" data-step="34">
                @php
                  $prop_condition = [['name' => 'Regular Sale', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Pre-Construction', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'New Construction', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Assignment Contract (Wholesale properties)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Short Sale', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">
                    What is the condition of the seller's property?
                  </label>
                  <select class="grid-picker" name="prop_condition" onchange="change_sale_provision(this.value)"
                    id="prop_condition" style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($prop_condition as $item)
                      @php
                        if ($item['name'] == 'Other') {
                            $target = '.other_prop_condition_residential';
                        } elseif ($item['name'] == 'Assignment Contract (Wholesale properties)') {
                            $target = '.prop_condition_commercial_and_business';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                        class="card flex-row" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="row prop_condition_commercial_and_business d-none">
                  <div class="form-group">
                    <label class="fw-bold">Is the seller currently under contract with a property they would like to
                      assign?</label>
                    <select class="grid-picker" name="financing_info" onchange="seller_under_contract(this.value)"
                      id="financing_info" required>
                      <option value="">Select</option>
                      @foreach ($yes_or_nos as $item)
                        <option value="{{ $item['name'] }}" class="card flex-column"
                          style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group has_assignment d-none">
                    <label class="fw-bold">What fee would the seller pay the agent to assign the contract? $ or
                      %</label>
                    <input type="number" name="finder_fee" placeholder="0.00" id="concession" min="0.5"
                      step=".01" class="form-control has-icon search_places col-3"
                      data-icon="fa-solid fa-percent" data-msg-required="Finder Fee" placeholder="% or $">
                  </div>
                  <div class="form-group has_no_assignment d-none">
                    <label class="fw-bold">Is the seller looking to take over a buyer’s contract?</label>
                    <select class="grid-picker" name="another_contract" id="financing_info" required>
                      <option value="">Select</option>
                      @foreach ($yes_or_nos as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                          class="card flex-column" style="width:calc(33.33% - 10px);"
                          data-icon='<i class="fa-regular fa-check-circle"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
                <div class="form-group  d-none" id="other_sale_provision_commercial_and_business">
                  <label class="fw-bold">What is the condition of the seller's property?</label>
                  <input type="text" name="custom_prop_condition" id="custom_prop_condition"
                    class="form-control has-icon  " data-icon="fa-solid fa-qrcode"
                    data-msg-required="Property Condition" placeholder="Property Condition">
                </div>
              </div>
              {{-- Slide 7 --}}
              {{-- Slide 8 --}}
              <div class="wizard-step" data-step="35">
                @php
                  $bathrooms = [['name' => '1', 'target' => ''], ['name' => '1.5+', 'target' => ''], ['name' => '2+', 'target' => ''], ['name' => '2.5+', 'target' => ''], ['name' => '3+', 'target' => ''], ['name' => '3.5+', 'target' => ''], ['name' => '4+', 'target' => ''], ['name' => '4.5+', 'target' => ''], ['name' => '5+', 'target' => ''], ['name' => '6+', 'target' => ''], ['name' => '7+', 'target' => ''], ['name' => '8+', 'target' => ''], ['name' => '9+', 'target' => ''], ['name' => '10+', 'target' => ''], ['name' => 'Other', 'target' => '.other_bathrooms_commercial_and_business']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">How many bathrooms does the property have?</label>
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
                <div class="form-group other_bathrooms_commercial_and_business d-none">
                  <label class="fw-bold" for="other_bathrooms">How many bathrooms does the property have?</label>
                  <input type="text" name="other_bathrooms" id="other_bathrooms" placeholder="Custom Bathrooms"
                    class="form-control has-icon" data-icon="fa-solid fa-bath"
                    data-msg-required="Please enter Custom Bathrooms" required>
                </div>
              </div>
              {{-- Slide 8 --}}
              {{-- Slide 9 --}}
              <div class="wizard-step" data-step="36">
                <div class="form-group">
                  <label class="fw-bold">What is the heated square footage of the property?</label>
                  <input type="number" name="heated_square_footage" data-type="heated_square_footage"
                    placeholder="Enter Heated Square Footage" id="heated_square_footage"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Heated Square Footage" required>
                </div>
              </div>
              {{-- Slide 9 --}}
              {{-- Slide 10 --}}
              <div class="wizard-step" data-step="37">
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
                  <label class="fw-bold">What appliances are included in the property? </label>
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
                </div>
              </div>
              {{-- Slide 10 --}}
              {{-- Slide 11 --}}
              <div class="wizard-step business_show d-none" data-step="38">
                @php
                  $purchase_of_business = [['name' => 'Real Estate Building and Business', 'target' => ''], ['name' => 'Business Only ', 'target' => '']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Does the purchase of the business include the Real Estate building itself, or
                    is it soley for the business?</label>
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
              {{-- Slide 11 --}}
              {{-- Slide 12 --}}
              <div class="wizard-step" data-step="39">
                @php
                  $occupant_types = [['name' => 'Owner-Occupied', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tenant-Occupied', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">What is the current occupancy status of the property?</label>
                  <select class="grid-picker" name="occupant_type" id="occupant_type"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($occupant_types as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="form-group">
                  <label class="fw-bold">Does the seller have a specific price in mind that they would
                    like to obtain for their property?</label>
                  <select class="grid-picker" name="seller_specific_price" id="seller_specific_price"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $yes_or_no)
                      @php
                        if ($yes_or_no['name'] == 'Yes') {
                            $target = '.seller_specific_price_commercial_and_business';
                        } elseif ($yes_or_no['name'] == 'No') {
                            $target = '.select_your_commercial_and_business';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                        {{ $yes_or_no['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group seller_specific_price_commercial_and_business d-none">
                  <label>Price:</label>
                  <input type="text" class="form-control has-icon" name="custom_seller_specific_price"
                    data-icon="fa-solid fa-qrcode" id="custom_listing_terms" required />
                </div>

                <div class="form-group select_your_commercial_and_business d-none">
                  <label class="fw-bold">
                    How much do you think your property will sell for?
                  </label>
                  @php
                    $expectations = [['name' => '100k or less'], ['name' => '100k-200k'], ['name' => '200k-300k'], ['name' => '300k-400k'], ['name' => '400k-500k'], ['name' => '500k-600k'], ['name' => '600k-700k'], ['name' => '700k-800k'], ['name' => '800-900k'], ['name' => '900k-1 million'], ['name' => '1 million +'], ['name' => '2 million +'], ['name' => '3 million +'], ['name' => '4 million +'], ['name' => '5 million +'], ['name' => '10 million +']];
                  @endphp
                  <select name="expectation" id="expectation" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($expectations as $item)
                      <option value="{{ $item['name'] }}" data-target="" class="card flex-column"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              {{-- Slide 12 --}}
              {{-- Slide 13 --}}
              <div class="wizard-step" data-step="40">
                @php
                  $financings = [['name' => 'Cash', 'target' => ''], ['name' => 'Conventional', 'target' => ''], ['name' => 'Seller Financing', 'target' => ''], ['name' => 'FHA', 'target' => ''], ['name' => 'Jumbo', 'target' => ''], ['name' => 'VA', 'target' => ''], ['name' => 'No-Doc', 'target' => ''], ['name' => 'Non-QM', 'target' => ''], ['name' => 'Assumable', 'target' => ''], ['name' => 'Exchange/Trade', 'target' => ''], ['name' => 'Lease Option', 'target' => ''], ['name' => 'Lease Purchase', 'target' => ''], ['name' => 'Private Financing Available', 'target' => ''], ['name' => 'Special Funding', 'target' => ''], ['name' => 'Other', 'target' => '']];
                @endphp
                <div class="row align-items-end mt-4">
                  <div class="col-md-12">
                    <label class="fw-bold">Select the types of financing/currency that is acceptable to the
                      seller:</label>
                    <div class="select2-parent">
                      <select name="financings[]" class="grid-picker" multiple>
                        <option value=""></option>
                        @foreach ($financings as $financing)
                          @php
                            if ($financing['name'] == 'Other') {
                                $target = '.custom_financing_commercial_and_business';
                            } else {
                                $target = '';
                            }
                          @endphp
                          <option value="{{ $financing['name'] }}" data-target="{{ $target }}"
                            class="card flex-column " style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $financing['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>

                  </div>
                </div>
                <div class="form-group custom_financing_commercial_and_business d-none">
                  <label class="fw-bold">What type of financing/currency is acceptable to the seller? </label>
                  <input type="number" name="type_of_financing" data-type="type_of_financing"
                    id="type_of_financing" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing" required>
                </div>
              </div>
              {{-- Slide 13 --}}
              {{-- Slide 14 --}}
              <div class="wizard-step" data-step="41">
                <div class="form-group">
                  <label class="fw-bold">
                    When will the property be ready to sell?
                  </label>
                  @php
                    $timeframes = [['name' => 'Now', 'target' => ''], ['name' => '1 month', 'target' => ''], ['name' => '2 months', 'target' => ''], ['name' => '3 months', 'target' => ''], ['name' => '4 months', 'target' => ''], ['name' => '5 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => 'Over 6 months', 'target' => ''], ['name' => 'Not looking to sell', 'target' => ''], ['name' => 'Other', 'target' => '']];
                  @endphp
                  <select name="selling_timeframe" id="selling_timeframe" class="grid-picker"
                    style="justify-content: flex-start;" onchange="showPrompt(this.value);" required>
                    <option value=""></option>
                    @foreach ($timeframes as $item)
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
                <div class="form-group  d-none" id="custom_timeframe_commercial_and_business">
                  <label class="fw-bold">When will the property be ready to sell? </label>
                  <input type="texts" name="custom_timeframe" data-type="custom_timeframe" id="custom_timeframe"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Your Custom Timeframe" required>
                </div>
                <div class="form-group prompt_response d-none">
                  <p class="text-danger">This is a service designed for sellers who are seeking to sell
                    their property within three months or less.</p>
                </div>
              </div>
              {{-- Slide 14 --}}
              {{-- Slide 15 --}}
              <div class="wizard-step" data-step="42">
                <div class="form-group">
                  <label class="fw-bold">
                    What are the terms of the listing agreement you are offering to the agent?
                  </label>
                  @php
                    $listing_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '12 months', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_listing_terms_commercial_and_business']];
                  @endphp
                  <select name="listing_term" id="listing_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($listing_terms as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_listing_terms_commercial_and_business d-none">
                  <label>What are the terms of the listing agreement you are offering to the agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="Write Custom Terms"
                    name="custom_listing_terms" data-icon="fa-solid fa-qrcode" id="custom_listing_terms" required />
                </div>
              </div>
              {{-- Slide 15 --}}
              {{-- Slide 16 --}}
              <div class="wizard-step" data-step="43">
                <div class="form-group">
                  <label class="fw-bold">
                    What is the total commission being offered to the agent?
                  </label>
                  @php
                    $offered_commissions = [['name' => '5%', 'target' => ''], ['name' => '5.5%', 'target' => ''], ['name' => '6%', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_offered_commission_commercial_and_business']];
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
                <div class="form-group custom_offered_commission_commercial_and_business d-none">
                  <label>What is the total commission being offered to the agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="Write Custom Commission"
                    name="custom_offered_commission" data-icon="fa-solid fa-qrcode" id="custom_offered_commission"
                    required />
                </div>
                <div class="form-group">
                  <label class="fw-bold">
                    Would the seller like to request the agent to contribute up to half a percent of their commission
                    towards the buyer's closing costs in order to further promote their property?
                  </label>
                  @php
                    $contribute_terms = [['name' => 'Yes', 'target' => '.custom_contribute_terms_commercial_and_business'], ['name' => 'No', 'target' => ''], ['name' => 'Optional', 'target' => '']];
                  @endphp
                  <select name="contribute_term" id="contribute_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($contribute_terms as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_contribute_terms_commercial_and_business d-none">
                  <label>How much would the seller like the agent to contribute towards the buyer's closing costs? (Up
                    to
                    0.5%): %</label>
                  <input type="number" class="form-control has-icon" min="0.5" step=".01"
                    placeholder="Seller’s closing costs up to .5%" name="custom_contribute_terms"
                    data-icon="fa-solid fa-qrcode" id="custom_contribute_terms" required />
                </div>
              </div>
              {{-- Slide 16 --}}
              {{-- Slide 17 --}}
              <div class="wizard-step" data-step="44">
                <div class="form-group ">
                  <label class="fw-bold">
                    Please select the services that the seller requests from the agent4:
                  </label>

                  @php
                    $services_data = [
                        ['name' => 'Conducting a thorough comparative market analysis (CMA) to determine the property\'s value and pricing strategy.', 'target' => ''],
                        ['name' => 'Providing guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.', 'target' => ''],
                        ['name' => 'Offering expert negotiation skills to secure the best possible terms and price during the selling process.', 'target' => ''],
                        ['name' => 'Coordinating and scheduling showings for potential buyers, ensuring a smooth and efficient viewing experience.', 'target' => ''],
                        ['name' => 'Providing professional photos to showcase the property\'s best features.', 'target' => ''],
                        ['name' => 'Offering aerial photography to capture the property\'s surroundings and neighborhood.', 'target' => ''],
                        ['name' => 'Providing a professional video to showcase the property\'s interior and exterior.', 'target' => ''],
                        ['name' => 'Providing a 3D tour to showcase the property\'s interior.', 'target' => ''],
                        ['name' => 'Providing a floor plan of the property to showcase its layout and spatial configuration.', 'target' => ''],
                        ['name' => 'Providing recommendations for home staging professionals.', 'target' => ''],
                        ['name' => 'Listing the property on the MLS.', 'target' => ''],
                        ['name' => 'Listing the property on Loopnet, a major commercial real estate website.', 'target' => ''],
                        ['name' => 'Listing the property on Crexi, a major commercial real estate website.', 'target' => ''],
                        ['name' => 'Listing the property on the Bid Your Offer platform.', 'target' => ''],
                        ['name' => 'Marketing the property to various groups, pages, and affiliates to generate interest and leads.', 'target' => ''],
                        ['name' => 'Assisting with the development of a strategic marketing plan tailored to the property and target buyers.', 'target' => ''],
                        ['name' => 'Promoting the property on social media platforms.', 'target' => ''],
                        ['name' => 'Implementing neighborhood marketing with a QR code or listing link that leads to the property\'s listing on the BidYourOffer.com platform.', 'target' => ''],
                        ['name' => 'Providing virtual staging to enhance the property\'s visual appeal and attract potential buyers.', 'target' => ''],
                        ['name' => 'Sending email alerts to buyers searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.', 'target' => ''],
                        ['name' => 'Assisting with the completion and submission of all necessary paperwork and documentation related to the sale.', 'target' => ''],
                        ['name' => 'Collaborating with other real estate professionals and agents to expand the network of potential buyers for the property.', 'target' => ''],
                        ['name' => 'Providing regular updates on market activity, showings, and feedback from potential buyers.', 'target' => ''],
                        ['name' => 'Assisting with the smooth transition and transfer of ownership, including coordinating with attorneys, title companies, and other professionals involved in the closing process.', 'target' => ''],
                        ['name' => 'Offering post-sale assistance, such as recommending reputable service providers for moving, repairs, or other post-sale needs.', 'target' => ''],
                        ['name' => 'Other - Add additional services as needed', 'target' => '.commercial_service_commercial_and_business'],
                    ];
                  @endphp
                  <select name="services[]" id="commercial-services" multiple class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($services_data as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(100%);"
                        data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group commercial_service_commercial_and_business d-none">
                  <label>Add additional services as needed.</label>
                  <input type="text" class="form-control has-icon" placeholder="Write Commercial Services"
                    name="commercial_servic" data-icon="fa-regular fa-circle-check" id="commercial_servic"
                    required />
                </div>
              </div>
              {{-- Slide 17 --}}
              {{-- Slide 18 --}}
              <div class="wizard-step" data-step="45">
                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What are the most important aspects you will consider when hiring a real estate
                      agent?

                    </label>
                    <textarea name="important_aspect" class="form-control" rows="5" required
                      data-msg-required="This Field is Required">{{ old('description') }}</textarea>
                  </div>
                </div>

                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What additional details would the seller like to share with the agent?
                    </label>
                    <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5"></textarea>
                  </div>
                </div>
              </div>
              {{-- Slide 18 --}}
              {{-- Slide 19 --}}
              <div class="wizard-step" data-step="46">
                <div class="form-group">
                  <label class="fw-bold">Does the seller have a preferred agent whom they would like to
                    notify to participate in the auction?</label>
                  <select class="grid-picker" name="preferred_agent"
                    onchange="change_buyer_prefered_agent(this.value)" id="preferred_agent"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                        class="card flex-row" style="width:calc(50% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group  d-none" id="explains_commercial_and_business">
                  <h4>Please enter the first name, last name, brokerage, phone number, and email address
                    of the preferred agent(s):</h4>
                  <div class="form-group">
                    <label class="fw-bold">First name:</label>
                    <input type="text" name="first_name" placeholder="Enter first name" id="first_name"
                      class="form-control has-icon" data-icon="fa-solid fa-user"
                      data-msg-required="Please enter first name" required>
                  </div>

                  <div class="form-group">
                    <label class="fw-bold">Last name:</label>
                    <input type="text" name="last_name" placeholder="Enter last name" id="last_name"
                      class="form-control has-icon" data-icon="fa-solid fa-user"
                      data-msg-required="Please enter last name" required>
                  </div>

                  <div class="form-group">
                    <label class="fw-bold">Brokerage:</label>
                    <input type="text" name="brokerage" data-type="brokerages" placeholder="Enter brokerage"
                      id="brokerage" class="form-control has-icon" data-icon="fa-solid fa-house"
                      data-msg-required="Please enter brokerage" required>
                  </div>

                  <div class="form-group">
                    <label class="fw-bold">Cell phone #:</label>
                    <input type="text" name="phone" data-type="phones" placeholder="Enter phone number"
                      id="phone" class="form-control has-icon" data-icon="fa-solid fa-phone"
                      data-msg-required="Please enter phone number" required>
                  </div>

                  <div class="form-group">
                    <label class="fw-bold">Email:</label>
                    <input type="text" name="email" data-type="emails" placeholder="Enter your email address"
                      id="email" class="form-control has-icon" data-icon="fa-solid fa-envelope"
                      data-msg-required="Please enter email address">
                  </div>
                </div>
              </div>
              {{-- Slide 19 --}}
              {{-- Commercial and Bussines  Slide End --}}
              {{-- Vacant Land  Slide Start --}}
              {{-- Slide 6 --}}
              <div class="wizard-step" data-step="47">
                <div class="form-group">
                  <label class="fw-bold">
                    What type of sale is the seller’s property?
                  </label>
                  @php
                    $seller_property = [['target' => '', 'name' => 'Regular Sale'], ['target' => '', 'name' => 'Pre-Construction'], ['target' => '', 'name' => 'Currently Being Built'], ['target' => '', 'name' => 'New Construction'], ['target' => '', 'name' => 'Bank Owned/REO'], ['target' => '.assignment_contract', 'name' => 'Assignment Contract (Wholesale properties)'], ['target' => '', 'name' => 'Short Sale'], ['target' => '', 'name' => 'Probate'], ['target' => '', 'name' => 'Government Owned'], ['target' => '.custom_special_sale_residential', 'name' => 'Other']];
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
                <div class="form-group custom_special_sale_residential d-none">
                  <label class="fw-bold" for="custom_special_sale_residential">What type of sale is the seller’s
                    property?</label>
                  <input type="text" name="custom_special_sale" id="custom_special_sale"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode" required>
                </div>

                <div class="form-group d-none assignment_contract">
                  <label class="fw-bold">
                    Is the seller currently under contract with a property they would like to assign?
                  </label>
                  @php
                    $seller_contract_options = [['name' => 'Yes', 'target' => '.custom_seller_contract_yes'], ['name' => 'No', 'target' => '.custom_seller_contract_no']];
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

                  <div class="form-group custom_seller_contract_yes d-none">
                    <label class="fw-bold">What fee would the seller pay the agent to assign the contract? </label>
                    <input type="number" class="form-control has-icon" min="0.5" step=".01"
                      placeholder="Seller’s closing costs up to .5%" name="custom_seller_contract_yes"
                      data-icon="fa-solid fa-qrcode" id="custom_seller_contract_yes" required />
                  </div>
                  <div class="form-group custom_seller_contract_no d-none">
                    <label class="fw-bold">Is the seller looking to take over a buyer’s contract?? </label>
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

              </div>
              {{-- Slide 6 --}}
              {{-- Slide 7 --}}
              <div class="wizard-step" data-step="48">
                @php
                  $total_acreages = [['name' => '0 to less than 14', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ acres', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
                @endphp

                <div class="form-group vacant_land_hide">
                  <label class="fw-bold">What is the total acreage of the property?</label>
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

              </div>
              {{-- Slide 7 --}}
              <div class="wizard-step" data-step="49">

                <div class="form-group">
                  <label class="fw-bold">Does the seller have a specific price in mind that they would
                    like to obtain for their property?</label>
                  <select class="grid-picker" name="seller_specific_price" id="seller_specific_price"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $yes_or_no)
                      @php
                        if ($yes_or_no['name'] == 'Yes') {
                            $target = '.seller_specific_price_vacant_land';
                        } elseif ($yes_or_no['name'] == 'No') {
                            $target = '.select_your_vacant_land';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $yes_or_no['name'] }}" data-target="{{ $target }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                        {{ $yes_or_no['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group seller_specific_price_vacant_land d-none">
                  <label class="fw-bold">Price:</label>
                  <input type="text" class="form-control has-icon" name="custom_seller_specific_price"
                    data-icon="fa-solid fa-qrcode" id="custom_listing_terms" required />
                </div>

                <div class="form-group select_your_vacant_land d-none">
                  <label class="fw-bold">
                    How much do you think your property will sell for?
                  </label>
                  @php
                    $expectations = [['name' => '100k or less'], ['name' => '100k-200k'], ['name' => '200k-300k'], ['name' => '300k-400k'], ['name' => '400k-500k'], ['name' => '500k-600k'], ['name' => '600k-700k'], ['name' => '700k-800k'], ['name' => '800-900k'], ['name' => '900k-1 million'], ['name' => '1 million +'], ['name' => '2 million +'], ['name' => '3 million +'], ['name' => '4 million +'], ['name' => '5 million +'], ['name' => '10 million +']];
                  @endphp
                  <select name="expectation" id="expectation" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($expectations as $item)
                      <option value="{{ $item['name'] }}" data-target="" class="card flex-column"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              {{-- Slide 8 --}}
              <div class="wizard-step" data-step="50">
                @php
                  $financings = [['name' => 'Cash', 'target' => ''], ['name' => 'Conventional', 'target' => ''], ['name' => 'Seller Financing', 'target' => '.custom_seller_financing'], ['name' => 'FHA', 'target' => ''], ['name' => 'Jumbo', 'target' => ''], ['name' => 'VA', 'target' => ''], ['name' => 'No-Doc', 'target' => ''], ['name' => 'Non-QM', 'target' => ''], ['name' => 'Assumable', 'target' => '.custom_assumable'], ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade'], ['name' => 'Lease Option', 'target' => ''], ['name' => 'Lease Purchase', 'target' => ''], ['name' => 'Cryptocurrency', 'target' => '.custom_cryptocurrency'], ['name' => 'NFT', 'target' => ''], ['name' => 'USDA', 'target' => ''], ['name' => 'Other', 'target' => '.custom_financing_residential']];
                @endphp
                <div class="row align-items-end mt-4">
                  <div class="col-md-12">
                    <label class="fw-bold">Select the types of financing/currency that is acceptable to the
                      seller:</label>
                    <div class="select2-parent">
                      <select name="financings[]" class="grid-picker" multiple id="landFinancingOptions">
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
                <div class="form-group custom_financing_residential d-none">
                  <label class="fw-bold">What type of financing/currency is acceptable to the seller? </label>
                  <input type="number" name="type_of_financing" data-type="type_of_financing"
                    id="type_of_financing" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_exchange_trade d-none">
                  <label class="fw-bold">What item does the seller want to Exchange/Trade, and what is the value of the
                    seller's acceptable Exchange/Trade item? How is the value determined, and how much additional cash
                    does the seller require on top of the Exchange/Trade item? Additionally, are there specific criteria
                    or conditions for the type of item the seller is willing to Exchange/Trade? </label>
                  <input type="text" name="custom_exchange_trade" data-type="custom_exchange_trade"
                    id="custom_exchange_trade" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group" id="land_lease_option" style="display: none;">
                  <label class="fw-bold">What are the seller's terms for a lease option or lease purchase, including
                    the
                    desired offering price, specific terms, proposed lease duration, monthly payment amount, any
                    conditions or requirements, presence of an option fee, and potential changes in offering price
                    during
                    the lease period? </label>
                  <input type="text" name="custom_lease" data-type="custom_lease" id="custom_lease"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_seller_financing d-none">
                  <label class="fw-bold">What are the seller's terms for seller financing, including the desired price,
                    offered terms, acceptable down payment, interest rate, expected security or collateral, and any
                    prepayment penalties or conditions for early repayment?</label>
                  <input type="text" name="custom_seller_financing" data-type="custom_seller_financing"
                    id="custom_seller_financing" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_assumable d-none">
                  <label class="fw-bold">What are the assumable terms offered by the seller, and are there any
                    restrictions or qualifications for a buyer assuming the existing financing? Additionally, is there any
                    outstanding balance on the existing financing that the buyer would need to cover?</label>
                  <input type="text" name="custom_assumable" data-type="custom_assumable" id="custom_assumable"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>
                <div class="form-group custom_cryptocurrency d-none">
                  <label class="fw-bold">What type of Cryptocurrency would the seller accept?</label><br>
                  <label class="fw-bold">Note:Cryptocurrency can be converted to cash at closing. </label>
                  <input type="text" name="custom_cryptocurrency" data-type="custom_cryptocurrency"
                    id="custom_cryptocurrency" class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Type of Financing">
                </div>


              </div>
              {{-- Slide 8 --}}
              {{-- Slide 9 --}}
              <div class="wizard-step" data-step="51">
                <div class="form-group">
                  <label class="fw-bold">
                    When will the property be ready to sell?
                  </label>
                  @php
                    $timeframes = [['name' => 'Now', 'target' => ''], ['name' => '1 month', 'target' => ''], ['name' => '2 months', 'target' => ''], ['name' => '3 months', 'target' => ''], ['name' => '4 months', 'target' => ''], ['name' => '5 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => 'Over 6 months', 'target' => ''], ['name' => 'Not looking to sell', 'target' => ''], ['name' => 'Other', 'target' => '']];
                  @endphp
                  <select name="selling_timeframe" id="selling_timeframe" class="grid-picker"
                    style="justify-content: flex-start;" onchange="showPrompt(this.value);" required>
                    <option value=""></option>
                    @foreach ($timeframes as $item)
                      @php
                        if ($item['name'] == 'Other') {
                            $target = '.custom_timeframe_vacant_land';
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
                <div class="form-group  d-none" id="custom_timeframe_vacant_land">
                  <label class="fw-bold">When will the property be ready to sell? </label>
                  <input type="texts" name="custom_timeframe" data-type="custom_timeframe" id="custom_timeframe"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode"
                    data-msg-required="Please Enter Your Custom Timeframe" required>
                </div>
                <div class="form-group prompt_response d-none">
                  <p class="text-danger">This is a service designed for sellers who are seeking to sell
                    their property within three months or less.</p>
                </div>
              </div>
              {{-- Slide 9 --}}
              {{-- Slide 10 --}}
              <div class="wizard-step" data-step="52">
                <div class="form-group">
                  <label class="fw-bold">
                    What is the timeframe offered to the agent in the Seller Agency agreement?
                  </label>
                  @php
                    $listing_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '12 months', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_listing_terms_vacant_land']];
                  @endphp
                  <select name="listing_term" id="listing_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($listing_terms as $item)
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
                    agreement?</label>
                  <input type="text" class="form-control has-icon" placeholder="" name="custom_listing_terms"
                    data-icon="fa-solid fa-qrcode" id="custom_listing_terms" required />
                </div>
              </div>
              {{-- Slide 10 --}}
              {{-- Slide 11 --}}
              <div class="wizard-step" data-step="53">
                <div class="form-group">
                  <label class="fw-bold">
                    What is the total commission being offered to the agent?
                  </label>
                  @php
                    $offered_commissions = [['name' => '5%', 'target' => ''], ['name' => '5.5%', 'target' => ''], ['name' => '6%', 'target' => ''], ['name' => 'Negotiable', 'target' => ''], ['name' => 'Other', 'target' => '.custom_offered_vacant_land']];
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
                  <label class="fw-bold">What is the total commission being offered to the agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="Write Custom Commission"
                    name="custom_offered_commission" data-icon="fa-solid fa-qrcode" id="custom_offered_commission"
                    required />
                </div>
                <div class="form-group">
                  <label class="fw-bold">
                    Would the seller like to request that the agent contribute a portion of their commission towards the
                    buyer's closing costs to further promote their property?
                  </label>
                  @php
                    $contribute_terms = [['name' => 'Yes', 'target' => '.custom_contribute_vacant_land'], ['name' => 'No', 'target' => '']];
                  @endphp
                  <select name="contribute_term" id="contribute_term" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($contribute_terms as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-column" style="width:calc(20% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>

                </div>
                {{-- <div class="form-group custom_contribute_vacant_land d-none">
                  <label>How much would the seller like the agent to contribute towards the buyer's closing costs? (Up
                    to
                    0.5%): %</label>
                  <input type="number" class="form-control has-icon" min="0.5" step=".01"
                    placeholder="Seller’s closing costs up to .5%" name="custom_contribute_terms"
                    data-icon="fa-solid fa-qrcode" id="custom_contribute_terms" required />
                </div> --}}
              </div>
              {{-- Slide 11 --}}
              {{-- Slide 12 --}}
              <div class="wizard-step" data-step="54">
                <div class="form-group ">
                  <label class="fw-bold">
                    Please select the services that the seller requests from the agent:
                  </label>
                  @php
                    $services_data = [
                        ['name' => "Conduct a thorough comparative market analysis (CMA) to determine the property's value and pricing strategy.", 'target' => ''],
                        ['name' => 'Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.', 'target' => ''],
                        ['name' => 'Offer expert negotiation skills to secure the best possible terms and price during the selling process.', 'target' => ''],
                        ['name' => 'Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.', 'target' => ''],
                        ['name' => "Provide professional photos to showcase the property's best features.", 'target' => ''],
                        ['name' => "Offer aerial photography to capture the property's surroundings and neighborhood.", 'target' => ''],
                        ['name' => "Provide a professional video to showcase the property's interior and exterior", 'target' => ''],
                        ['name' => "Provide a 3D tour to showcase the property's interior.", 'target' => ''],
                        ['name' => 'Provide a floor plan of the property to showcase its layout and spatial configuration.', 'target' => ''],
                        ['name' => 'Provide recommendations for home staging professionals.', 'target' => ''],
                        ['name' => 'List the property on the Bid Your Offer platform.', 'target' => ''],
                        ['name' => 'List the property on the MLS.', 'target' => ''],
                        ['name' => "List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and many more, to increase the property's visibility and exposure.", 'target' => ''],
                        ['name' => 'Market the property to various groups, pages, and affiliates to generate interest and leads.', 'target' => ''],
                        ['name' => 'Assist with the development of a strategic marketing plan tailored to the property and target buyers.', 'target' => ''],
                        ['name' => 'Promote the property on social media platforms.', 'target' => ''],
                        ['name' => "Implement neighborhood marketing with a QR code or listing link that leads to the property's listing on the BidYourOffer.com platform.", 'target' => ''],
                        ['name' => "Provide virtual staging to enhance the property's visual appeal and attract potential buyers.", 'target' => ''],
                        ['name' => 'Host an Open House(s).', 'target' => ''],
                        ['name' => "Send email alerts to buyers searching for properties that match the property's criteria the moment the property is listed directly through the MLS.", 'target' => ''],
                        ['name' => 'Provide guidance and support throughout the entire transaction, from listing to closing, to ensure a seamless and successful sale.', 'target' => ''],
                        ['name' => 'Assist with the completion and submission of all necessary paperwork and documentation related to the sale.', 'target' => ''],
                        ['name' => 'Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.', 'target' => ''],
                        ['name' => 'Provide regular updates on market activity, showings, and feedback from potential buyers.', 'target' => ''],
                        ['name' => 'Offer post-sale assistance, such as recommending reputable service providers for moving, repairs, or other post-sale needs.', 'target' => ''],
                        ['name' => 'Other - Add additional services as needed.', 'target' => '.commercial_service_vacant_land'],
                    ];
                  @endphp

                  <select name="services[]" id="commercial-services" multiple class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($services_data as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(100%);"
                        data-icon='<i class="fa-regular fa-circle-check" style="font-size:28px;"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group commercial_service_vacant_land d-none">
                  <label class="fw-bold">What additional services would the seller like to request from an
                    agent?</label>
                  <input type="text" class="form-control has-icon" placeholder="" name="commercial_servic"
                    data-icon="fa-regular fa-circle-check" id="commercial_servic" required />
                </div>
              </div>
              {{-- Slide 12 --}}
              {{-- Slide 13 --}}
              <div class="wizard-step" data-step="55">

                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What are the most important aspects you will consider when hiring a real estate
                      agent?

                    </label>
                    <textarea name="important_aspect" class="form-control" rows="5" required
                      data-msg-required="This Field is Required">{{ old('description') }}</textarea>
                  </div>
                </div>

                <div class="row">
                  <div class="form-group mt-4 col-md-12">
                    <label class="fw-bold">
                      What additional details would the seller like to share with the agent?
                    </label>
                    <textarea type="text" name="important_info" placeholder="" class="form-control" rows="5"></textarea>
                  </div>
                </div>
              </div>
              {{-- Slide 13 --}}
              {{-- Vacant Land End --}}
              {{-- 13 Jul 2023 for Business Opportunity --}}
              {{-- 13 Jul 2023 for Business Opportunity --}}
              <div class="d-flex justify-content-between form-group mt-4">
                <div>
                  <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                </div>
                <div>
                  <button type="button" class="wizard-step-next btn btn-success btn-lg text-600"
                    style="display: none;">Next</button>
                  <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
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
    {{-- <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    What is the address of your property? (County and State included so we can notify agents by county)
                </label>
                <input type="text" name="cma_q1" id="cma_q1" class="form-control search_places has-icon"
                    data-icon="fa-solid fa-location-dot" data-type="address"
                    placeholder="County and State included so we can notify agents by county" required>
            </div>
        </div> --}}

    {{-- <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    What price do you want to list your property for?
                </label>
                <input type="text" name="cma_q2" id="cma_q2" class="form-control" required>
            </div>
        </div> --}}

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

    {{-- <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    For the most accurate CMA upload pictures of your property:
                </label>
                <input type="file" name="cma_q5" id="cma_q5" class="form-control has-icon"
                    data-icon="fa-solid fa-link" multiple accept="image/*" />
            </div>
        </div> --}}

    {{-- <div class="wizard-step cma_questions_">
            <div class="form-group">
                <label class="fw-bold">
                    For the most accurate CMA upload video of your property:
                </label>
                <input type="text" name="cma_q6" id="cma_q6" class="form-control has-icon"
                    data-icon="fa-solid fa-link">
            </div>
        </div> --}}

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
        $('.for_residential_only').removeClass('d-none');
        $('.residential_hide').removeClass('d-none');
        $('.business-length').hide();

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
      } else {
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
      if (w == "4 months" || w == "5 months" || w == "6 months" || w == "Over 6 months" || w == "" || w ==
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
          if (v.form()) {
            if ($('.wizard-step.active').next().is('.wizard-step')) {

              // $('.wizard-step.active').removeClass('active').next().addClass('active');
              $('.wizard-step.active').removeClass('active');
              if (StepWizard.currentStep == 5 && property_type ==
                'Income Property') {
                StepWizard.nextStep = 20;
                StepWizard.backStep = 5;
              }
              if (StepWizard.currentStep == 24 && property_type ==
                'Income Property') {
                StepWizard.nextStep = 13;
                StepWizard.backStep = 24;
              } else if (StepWizard.currentStep == 5 && property_type ==
                'Residential Property') {
                StepWizard.nextStep = 6;
                StepWizard.backStep = 5;
              } else if (StepWizard.currentStep == 37 && property_type ==
                'Commercial Property') {
                StepWizard.nextStep = 39;
                StepWizard.backStep = 37;
              } else if (StepWizard.currentStep == 17 && (property_type ==
                  'Commercial Property' || property_type ==
                  'Business Opportunity')

              ) {
                StepWizard.nextStep = 30;
                StepWizard.backStep = 17;

              } else if (StepWizard.currentStep == 11 && property_type ==
                'Business Opportunity') {
                StepWizard.nextStep = 38;
                StepWizard.backStep = 11;
              } else if (StepWizard.currentStep == 37 && property_type ==
                'Business Opportunity') {
                StepWizard.nextStep = 12;
                StepWizard.backStep = 37;

              } else if (StepWizard.currentStep == 29 && (property_type ==
                  'Commercial Property' || property_type ==
                  'Business Opportunity')) {
                StepWizard.nextStep = 19;
                StepWizard.backStep = 30;
              } else if (StepWizard.currentStep == 5 && (property_type == 'Vacant Land')) {
                StepWizard.nextStep = 47;
                StepWizard.backStep = 5;
              } else if (StepWizard.currentStep >= 46 && property_type ==
                'Vacant Land') {
                // StepWizard.backStep = StepWizard.currentStep;
                //   StepWizard.nextStep = StepWizard.nextStep + 1;
                // alert(StepWizard.nextStep)
                StepWizard.nextStep = StepWizard.nextStep + 1;
                StepWizard.backStep = StepWizard.currentStep;



              } else {
                StepWizard.backStep = StepWizard.currentStep;

                // StepWizard.backStep = StepWizard.nextStep + 1;

              }

              $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
              StepWizard.setStep();
              if (StepWizard.currentStep == 20 &&
                property_type == 'Residential Property'
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if (StepWizard.currentStep == 19 &&
                property_type == 'Income Property'
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if (StepWizard.currentStep == 19 &&
                property_type == 'Commercial Property') {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if ((StepWizard.currentStep == 19) &&
                property_type == 'Business Opportunity') {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
              if (StepWizard.currentStep == 54 &&
                property_type == 'Vacant Land'
              ) {
                $('.wizard-step-next').hide();
                $('.wizard-step-finish').show();
              }
            }
          }
        });

        $('.wizard-step-back').click(function(e) {
          console.log('first' + StepWizard.currentStep)
          console.log('back' + StepWizard.backStep)

          if ($('.wizard-step.active').prev().is('.wizard-step')) {

            $('.wizard-step.active').removeClass('active');

            $('[ data-step="' + StepWizard.backStep + '"]').addClass("active");
            StepWizard.setStep();
            if (StepWizard.currentStep == 20 && property_type ==
              'Income Property') {

              StepWizard.backStep = 5;
            } else if (StepWizard.currentStep == 13 && property_type ==
              'Income Property') {
              StepWizard.backStep = 24;
            } else if (StepWizard.currentStep == 39 && property_type ==
              'Commercial Property') {
              StepWizard.backStep = 37;
            } else if (StepWizard.currentStep == 6 && property_type ==
              'Residential Property') {

              StepWizard.backStep = 5;
            } else if (StepWizard.currentStep == 29 && (property_type ==
                'Commercial Property' || property_type ==
                'Business Opportunity')

            ) {
              StepWizard.backStep = 17;

            } else if (StepWizard.currentStep == 36 && property_type ==
              'Business Opportunity') {
              StepWizard.backStep = 11;

            } else if (StepWizard.currentStep == 46 && (property_type == 'Vacant Land')) {
              console.log("current" + StepWizard.currentStep)
              alert('grater or equal 47')
              StepWizard.backStep = 5;
            } else if (StepWizard.currentStep == 54 && (property_type == 'Vacant Land')) {
              alert('current54' + StepWizard.currentStep)
              alert('back54' + StepWizard.backStep)
              alert('next54' + StepWizard.nextStep)
              StepWizard.currentStep = StepWizard.currentStep;
              StepWizard.backStep = 53;
            } else if (StepWizard.currentStep == 53 && (property_type == 'Vacant Land')) {
              alert('current53' + StepWizard.currentStep)
              alert('back53' + StepWizard.backStep)
              alert('next53' + StepWizard.nextStep)
              StepWizard.currentStep = StepWizard.currentStep;
              StepWizard.backStep = 52;
            } else if (StepWizard.currentStep == 52 && (property_type == 'Vacant Land')) {
              alert('current52' + StepWizard.currentStep)
              alert('back52' + StepWizard.backStep)
              alert('next52' + StepWizard.nextStep)
              StepWizard.currentStep = StepWizard.currentStep;
              StepWizard.backStep = 51;
              StepWizard.currentStep = 51;
            } else if (StepWizard.currentStep == 51 && (property_type == 'Vacant Land')) {
              alert('current51' + StepWizard.currentStep)
              alert('back51' + StepWizard.backStep)
              alert('next51' + StepWizard.nextStep)
              StepWizard.currentStep = StepWizard.currentStep;
              StepWizard.backStep = 50;
            } else {
              alert('else' + StepWizard.currentStep)
              //   console.log(StepWizard.currentStep)
              //   alert(StepWizard.currentStep)
              //   alert(StepWizard.nextStep)

              //   alert(StepWizard.backStep)
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
          if (property_type === 'Residential Property') {
            var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
              return parseInt($(this).attr('data-step')) >= 20 && parseInt($(this)
                .attr('data-step')) <= 54;
            });
            $stepsToRemove.each(function() {
              $(this).closest('div[data-step]').remove();
            });
          }
          if (property_type === 'Income Property') {
            var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
              var stepValue = parseInt($(this).attr('data-step'));
              return (stepValue >= 6 && stepValue <= 19) || (stepValue >= 33 &&
                stepValue <= 54);
            });
            $stepsToRemove.each(function() {
              $(this).closest('div[data-step]').remove();
            });
          }
          //Remove All the SLides Except THe Commercial and Business Opportunity
          if (property_type === 'Commercial Property' || property_type ===
            'Business Opportunity') {
            var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
              var stepValue = parseInt($(this).attr('data-step'));
              return (stepValue >= 6 && stepValue <= 32) || (stepValue >= 47 &&
                stepValue <= 54);
            });

            $stepsToRemove.each(function() {
              $(this).closest('div[data-step]').remove();
            });
          }
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

        if (StepWizard.currentStep >= 6 && StepWizard.currentStep <= 19) {
          // Calculate progress for Residential steps (6 to 19)
          comp = 20 + (((StepWizard.currentStep - 6) / (19 - 6)) * 80);
        } else if (StepWizard.currentStep >= 20 && StepWizard.currentStep <= 32) {
          // Calculate progress for Income property steps (20 to 32)
          comp = 20 + (((StepWizard.currentStep - 20) / (32 - 20)) * 80);
        } else if (StepWizard.currentStep >= 33 && StepWizard.currentStep < 46) {
          // Calculate progress for Commercial and Business opportunity steps (33 to 46)
          comp = 20 + (((StepWizard.currentStep - 33) / (46 - 33)) * 80);
        } else if (StepWizard.currentStep >= 46 && StepWizard.currentStep <= 54) {
          // Calculate progress for Vacant land steps (46 to 54)
          comp = 20 + (((StepWizard.currentStep - 46) / (54 - 46)) * 80);
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
    var toggleDiv = $('#custom_lease_option');
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
    var toggleDiv = $('#land_lease_option');
    $('#landFinancingOptions').change(function() {
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
    // var toggleDiv = $('#custom_lease_optionIncome');
    // $('#financingOptionsIncome').change(function() {
    //   //Disply and hide a div
    //   ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) ? toggleDiv
    //     .show(): toggleDiv.hide()
    //   //   end
    //   var selectedOption = $(this).find(':selected');
    //   var targetClass = selectedOption.data('target');

    //   // Hide all custom option fields first
    //   $('.form-group[data-target^=".custom_"]').addClass('d-none');

    //   // Show the specific custom option field based on the selected value
    //   if (targetClass) {
    //     $(targetClass).removeClass('d-none');
    //   }
    // });
  </script>
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush
