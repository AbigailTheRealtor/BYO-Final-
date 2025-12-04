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
          <div id="wizard-step-container">
            <div class="wizard-step" data-step="1">
              <div class="form-group">
                <label class="fw-bold" for="address">Is the property currently listed for sale?</label>
                <select name="property_listed" id="property_listed" class="grid-picker" style="justify-content: flex-start;"
                    required>
                    <option value=""></option>
                    @foreach ($yes_or_nos as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="<i class='{{$item['icon']}}'></i>">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                </select>
              </div>           
            </div>
            <div class="wizard-step" data-step="2">
              <h4>Please provide the property's complete address, including the city, county, and state, for the real
                estate asset that the seller would like to offer to the buyer.</h4>
              <div class="form-group">
                <label class="fw-bold" for="address">Address:</label>
                <input type="text" name="address" data-type="address" placeholder="" id="address"
                  class="form-control has-icon search_places" data-icon="fa-solid fa-location-dot" required>
              </div>
              <div class="form-group">
                <label class="fw-bold" for="address">City:</label>
                <input type="text" name="city" placeholder="" data-type="cities" id="city"
                  class="form-control has-icon search_places" data-icon="fa-solid fa-city" required>
              </div>

              <div class="form-group">
                <label class="fw-bold" for="address">County:</label>
                <input type="text" name="county" placeholder="" id="county"
                  class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city" required>
              </div>

              <div class="form-group">
                <label class="fw-bold" for="address">State:</label>
                <input type="text" name="state" placeholder="" data-type="states" id="state"
                  class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa" required>
              </div>
           
            </div>
            {{-- <div class="wizard-step" data-step="3">
              @php
                $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property'], ['name' => 'Business Opportunity'], ['name' => 'Vacant Land']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Offered Property Style:</label>
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
                        ['name' => 'Aeronautical', 'class' => 'business-opportunity'],
                        ['name' => 'Agriculture', 'class' => 'business-opportunity'],
                        ['name' => 'Arts and Entertainment', 'class' => 'business-opportunity'],
                        ['name' => 'Assembly Hall', 'class' => 'business-opportunity'],
                        ['name' => 'Assisted Living', 'class' => 'business-opportunity'],
                        ['name' => 'Auto Dealer', 'class' => 'business-opportunity'],
                        ['name' => 'Auto Service', 'class' => 'business-opportunity'],
                        ['name' => 'Bar/Tavern/Lounge', 'class' => 'business-opportunity'],
                        ['name' => 'Barber/Beauty', 'class' => 'business-opportunity'],
                        ['name' => 'Car Wash', 'class' => 'business-opportunity'],
                        ['name' => 'Child Care', 'class' => 'business-opportunity'],
                        ['name' => 'Church', 'class' => 'business-opportunity'],
                        ['name' => 'Commercial', 'class' => 'business-opportunity'],
                        ['name' => 'Concession Trailers/Vehicles', 'class' => 'business-opportunity'],
                        ['name' => 'Construction/Contractor', 'class' => 'business-opportunity'],
                        ['name' => 'Convenience Store', 'class' => 'business-opportunity'],
                        ['name' => 'Distribution', 'class' => 'business-opportunity'],
                        ['name' => 'Distributor Routine Ven', 'class' => 'business-opportunity'],
                        ['name' => 'Education/School', 'class' => 'business-opportunity'],
                        ['name' => 'Farm', 'class' => 'business-opportunity'],
                        ['name' => 'Fashion/Specialty', 'class' => 'business-opportunity'],
                        ['name' => 'Flex Space', 'class' => 'business-opportunity'],
                        ['name' => 'Florist/Nursery', 'class' => 'business-opportunity'],
                        ['name' => 'Food & Beverage', 'class' => 'business-opportunity'],
                        ['name' => 'Gas Station', 'class' => 'business-opportunity'],
                        ['name' => 'Grocery', 'class' => 'business-opportunity'],
                        ['name' => 'Heavy Weight Sales Service', 'class' => 'business-opportunity'],
                        ['name' => 'Hotel/Motel', 'class' => 'business-opportunity'],
                        ['name' => 'Industrial', 'class' => 'business-opportunity'],
                        ['name' => 'Light Items Sales Only', 'class' => 'business-opportunity'],
                        ['name' => 'Manufacturing', 'class' => 'business-opportunity'],
                        ['name' => 'Marine/Marina', 'class' => 'business-opportunity'],
                        ['name' => 'Medical', 'class' => 'business-opportunity'],
                        ['name' => 'Mixed', 'class' => 'business-opportunity'],
                        ['name' => 'Mobile/Trailer Park', 'class' => 'business-opportunity'],
                        ['name' => 'Other', 'class' => 'business-opportunity'],
                        ['name' => 'Personal Service', 'class' => 'business-opportunity'],
                        ['name' => 'Professional Service', 'class' => 'business-opportunity'],
                        ['name' => 'Professional/Office', 'class' => 'business-opportunity'],
                        ['name' => 'Recreation', 'class' => 'business-opportunity'],
                        ['name' => 'Research & Development', 'class' => 'business-opportunity'],
                        ['name' => 'Residential', 'class' => 'business-opportunity'],
                        ['name' => 'Restaurant', 'class' => 'business-opportunity'],
                        ['name' => 'Retail', 'class' => 'business-opportunity'],
                        ['name' => 'Shopping Center/Strip Center', 'class' => 'business-opportunity'],
                        ['name' => 'Storage', 'class' => 'business-opportunity'],
                        ['name' => 'Theater', 'class' => 'business-opportunity'],
                        ['name' => 'Timberland', 'class' => 'business-opportunity'],
                        ['name' => 'Veterinary', 'class' => 'business-opportunity'],
                        ['name' => 'Warehouse', 'class' => 'business-opportunity'],
                        ['name' => 'Wholesale', 'class' => 'business-opportunity'],
                        ['name' => 'Billboard Site', 'class' => 'vacant-land'],
                        ['name' => 'Business', 'class' => 'vacant-land'],
                        ['name' => 'Cattle', 'class' => 'vacant-land'],
                        ['name' => 'Commercial', 'class' => 'vacant-land'],
                        ['name' => 'Farm', 'class' => 'vacant-land'],
                        ['name' => 'Fishery', 'class' => 'vacant-land'],
                        ['name' => 'Highway Frontage', 'class' => 'vacant-land'],
                        ['name' => 'Horses', 'class' => 'vacant-land'],
                        ['name' => 'Industrial', 'class' => 'vacant-land'],
                        ['name' => 'Land Fill', 'class' => 'vacant-land'],
                        ['name' => 'Livestock', 'class' => 'vacant-land'],
                        ['name' => 'Mixed Use', 'class' => 'vacant-land'],
                        ['name' => 'Nursery', 'class' => 'vacant-land'],
                        ['name' => 'Orchard', 'class' => 'vacant-land'],
                        ['name' => 'Other', 'class' => 'vacant-land'],
                        ['name' => 'Pasture', 'class' => 'vacant-land'],
                        ['name' => 'Poultry', 'class' => 'vacant-land'],
                        ['name' => 'Ranch', 'class' => 'vacant-land'],
                        ['name' => 'Residential', 'class' => 'vacant-land'],
                        ['name' => 'Row Crops', 'class' => 'vacant-land'],
                        ['name' => 'Sod Farm', 'class' => 'vacant-land'],
                        ['name' => 'Subdivision', 'class' => 'vacant-land'],
                        ['name' => 'Timber', 'class' => 'vacant-land'],
                        ['name' => 'Tracts', 'class' => 'vacant-land'],
                        ['name' => 'Trans/Cell Tower', 'class' => 'vacant-land'],
                        ['name' => 'Tree Farm', 'class' => 'vacant-land'],
                        ['name' => 'Unimproved Land', 'class' => 'vacant-land'],
                        ['name' => 'Well Field', 'class' => 'vacant-land'],
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
                  <label for="" class="business-opportunity fw-bold">Business Type: </label>
                  <label for="" class="vacant-land fw-bold">Current Use: </label>
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
            </div> --}}
            {{-- <div class="wizard-step" data-step="4">
              @php
                $realEstateRes = [['icon' => '<i class="fa-regular fa-circle-check"></i>', 'name' => 'Yes', 'target' => '.realEstateRes'], ['icon' => '<i class="fa-regular fa-circle-xmark"></i>', 'name' => 'No', 'target' => '']];
              @endphp
              <div class="row ">
                <div class="form-group">
                  <label class="fw-bold">Real Estate Included: </label>
                  <select class="grid-picker" name="sale_include" id="sale_include" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($realEstateRes as $item)
                      <option value="{{ $item['name'] }}" data-icon='{{ $item['icon'] }}'
                        data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group realEstateRes d-none">
                    <div class="form-group">
                      <label class="fw-bold">Business Name:</label>
                      <input type="text" name="businessName" id="" class="form-control has-icon"
                        data-icon="fa-solid fa-qrcode" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Year Established:</label>
                      <input type="text" name="yearEstablish" id="" class="form-control has-icon"
                        data-icon="fa-solid fa-qrcode" required>
                    </div>
                  </div>
                </div>
              </div>
              @php
                $licenseRes = [['name' => 'Beer/Wine', 'target' => ''], ['name' => 'Liquor', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Off Site', 'target' => ''], ['name' => 'On Site', 'target' => '']];
              @endphp
              <div class="row ">
                <div class="form-group">
                  <label class="fw-bold">Licenses: </label>
                  <select class="grid-picker" name="lincenses[]" id="sale_include" style="justify-content: flex-start;"
                    required multiple>
                    <option value="">Select</option>
                    @foreach ($licenseRes as $item)
                      <option value="{{ $item['name'] }}" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div> --}}
            <div class="wizard-step" data-step="3">
              <h4>Price and Terms:</h4>
                <div class="form-group ">
                  <label class="fw-bold" for="buy_now_price" required>Price:</label>
                  <input type="number"  name="price" placeholder="" id="buy_now_price"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar"
                    data-msg-required="Please enter Buy Now Price">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Acceptable Escrow Deposit: :</label>
                  <input type="number" name="escrow_amount2" id="term_escrow_amount" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Number of Days the Seller Will Accept for Closing: </label>
                  <input type="number" name="closing_days2" id="closing_days" placeholder=""
                    class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
              <div class="form-group">
                @php
                  $contigencies = [['name' => 'Inspection contingency', 'target' => '.inspection'], ['name' => 'Appraisal contingency', 'target' => '.appraisal'], ['name' => 'Financing contingency', 'target' => '.financing'], ['name' => 'Sale of a property contingency', 'target' => '.sale'], ['name' => 'None', 'target' => ''],['name' => 'Other', 'target' => '.otherContingency'],];
                @endphp
                <label class="fw-bold">Acceptable Contingencies: </label>
                <select class="grid-picker" name="contigencies_accepted_by_seller" id="contigencies_accepted_by_seller"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($contigencies as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group inspection d-none">
                  <label class="fw-bold">Inspection contingency (days):</label>
                  <input type="number" name="inspection" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
                <div class="form-group appraisal d-none">
                  <label class="fw-bold">Appraisal contingency (days):</label>
                  <input type="number" name="appraisal" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
                <div class="form-group financing d-none">
                  <label class="fw-bold">Finance contingency (days):</label>
                  <input type="number" name="finance" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
                <div class="form-group sale d-none">
                  <label class="fw-bold"> Sale of a property contingency (days): </label>
                  <input type="number" name="saleContingency" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
                <div class="form-group otherContingency d-none">
                  <label class="fw-bold">Acceptable contingency: </label>
                  <input type="number" name="acceptable" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
              </div>
              @php
                $timeFrame = [
                  ["name"=> "12 hours", "target"=> ''],
                  ["name"=> "24 hours (1 day)", "target"=> ''],
                  ["name"=> "36 hours", "target"=> ''],
                  ["name"=> "48 hours (2 days)", "target"=> ''],
                  ["name"=> "60 hours", "target"=> ''],
                  ["name"=> "72 hours (3 days)", "target"=> ''],
                  ["name"=> "96 hours (4 days)", "target"=> ''],
                  ["name"=> "120 hours (5 days)", "target"=> ''],
                  ["name"=> "144 hours (6 days)", "target"=> ''],
                  ["name"=> "168 hours (7 days)", "target"=> '']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Time Frame Allocated to Respond Offers: </label>
                <select class="grid-picker" name="timeFrame" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($timeFrame as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
                $multiTimeFrame = [
                  ["name"=> "12 hours", "target"=> ''],
                  ["name"=> "24 hours (1 day)", "target"=> ''],
                  ["name"=> "36 hours", "target"=> ''],
                  ["name"=> "48 hours (2 days)", "target"=> ''],
                  ["name"=> "60 hours", "target"=> ''],
                  ["name"=> "72 hours (3 days)", "target"=> ''],
                  ["name"=> "96 hours (4 days)", "target"=> ''],
                  ["name"=> "120 hours (5 days)", "target"=> ''],
                  ["name"=> "144 hours (6 days)", "target"=> ''],
                  ["name"=> "168 hours (7 days)", "target"=> '']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Time Frame Allocated to Respond to Multiple Offers:  </label>
                <select class="grid-picker" name="multiTimeFrame" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($multiTimeFrame as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                  @php
                    $term_financings = [
                        ['name' => 'Cash', 'target' => ''],
                        ['name' => 'Conventional', 'target' => ''],
                        ['name' => 'FHA', 'target' => ''],
                        ['name' => 'VA', 'target' => ''],
                        ['name' => 'Litecoin (LTC)', 'target' => ''],
                        ['name' => 'Bitcoin Cash (BCH)', 'target' => ''],
                        ['name' => 'Dash (DASH)', 'target' => ''],
                        ['name' => 'Ripple (XRP)', 'target' => ''],
                        ['name' => 'Tether (USDT)', 'target' => ''],
                        ['name' => 'USD Coin (USDC)', 'target' => ''],
                        ['name' => 'Non-Fungible Token (NFT)', 'target' => '.nft'],
                        ['name' => 'Cryptocurrency', 'target' => '.crypto'],
                        ['name' => 'USDA', 'target' => ''],
                        ['name' => 'Assumable', 'target' => '.assumable'],
                        ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade'],
                        ['name' => 'Lease Option', 'target' => '.leaseOption'],
                        ['name' => 'Lease Purchase', 'target' => '.leasePurchase'],
                        ['name' => 'Private Financing Available', 'target' => ''],
                        ['name' => 'Special Funding', 'target' => ''],
                        ['name' => 'Seller Financing', 'target' => '.custom_seller_financing'],
                        ['name' => 'Jumbo', 'target' => ''],
                        ['name' => 'Non-QM', 'target' => ''],
                        ['name' => 'No-Doc', 'target' => ''],
                        ['name' => 'Other', 'target' => '.otherFinancing'],
                    ];
                  @endphp
                <label class="fw-bold">Acceptable Currency/ Financing:</label>
                <select class="grid-picker" name="term_financings" id="term_financings"
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($term_financings as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
                {{-- Other --}}
                <div class="form-group otherFinancing d-none">
                    <label class="fw-bold">Acceptable Currency/Financing: </label>
                    <input type="text" name="otherFinancing"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                {{-- Other --}}
                {{-- NFT  --}}
                <div class="form-group nft d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What type of Non-Fungible Token (NFT) will the seller accept?</label>
                      <input type="text" name="type_of_NFT_accepted" id="type_of_NFT_accepted" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What percentage of the sales price will the seller accept in the form of a Non-Fungible Token (NFT)?</label>
                      <input type="number" name="percentage_in_NFT" id="percentage_in_NFT" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
                      <input type="number" name="percentage_in_cash" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                  </div>
                </div>
              {{-- NFT  --}}
              {{-- Crypto  --}}
              <div class="form-group crypto d-none">
                <div class="form-group col-md-12">
                    <label class="fw-bold">What type of cryptocurrency will the seller accept?</label>
                    <input type="text" name="cryptocurrency_type" id="cryptocurrency_type" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group col-md-12">
                    <label class="fw-bold">What percentage of the sales price will the seller accept in cryptocurrency?</label>
                    <input type="number" name="percentage_in_crypto" id="percentage_in_crypto" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group col-md-12">
                    <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
                    <input type="number" name="percentage_in_cash" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div> 
                <small>Note: Cryptocurrency can be converted to cash at closing.</small>             
              </div>
              <div class="form-group row custom_seller_financing">
                <div class="form-group col-md-3">
                  <label class="fw-bold">Down Payment:</label>
                  <input type="number" name="down_payment_seller_financing" id="down_payment_seller_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Seller Financing Amount:</label>
                  <input type="number" name="seller_financing_amount" id="seller_financing_amount"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Interest Rate:</label>
                  <input type="number" name="interest_rate_seller_financing" id="interest_rate_seller_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Term:</label>
                  <input type="text" name="term_seller_financing" id="term_seller_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Monthly Payments:</label>
                  <input type="number" name="monthly_payment_seller_financing" id="monthly_payment_seller_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Closing Costs:</label>
                  <input type="text" name="closing_costs" id="closing_costs" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined" required>
                </div>
                <div class="form-group">
                  @php
                    $ballonPenalty = [['name' => 'Yes', 'target' => '.ballonPenaltyYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                  @endphp
                  <label class="fw-bold">Prepayment Penalty:</label>
                  <select name="ballonPenalty" id="auto_bid" class="grid-picker" style="justify-content: flex-start;"
                    required>
                    @foreach ($ballonPenalty as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>{{ $item['name'] }}
                    </option>
                    @endforeach
                  </select>
                  <div class="form-group  ballonPenaltyYes d-none">
                    <label class="fw-bold">What is the prepayment penalty amount? </label>
                    <input type="text" name="ballonPenaltyYes" id="closing_costs" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                </div>
                <div class="form-group">
                  @php
                    $balloonPay = [['name' => 'Yes', 'target' => '.balloonPayYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                  @endphp
                  <label class="fw-bold">Balloon Payment:</label>
                  <select name="balloonPay" id="auto_bid" class="grid-picker" style="justify-content: flex-start;"
                    required>
                    @foreach ($balloonPay as $item)
                      <option value="{{ $item['name'] }}"
                        data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>{{ $item['name'] }}
                    </option>
                    @endforeach
                  </select>
                    <div class="form-group balloonPayYes d-none">
                      <div class="form-group">
                        <label class="fw-bold">How much is the balloon payment? </label>
                        <input type="text" name="balloonPayment" id="closing_costs" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" required>
                      </div>
                      <div class="form-group">
                        <label class="fw-bold">When is the balloon payment due? </label>
                        <input type="text" name="balloonDue" id="closing_costs" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" required>
                      </div>
                    </div>
                </div>
              </div>
              {{-- Lease Option  --}}
                <div class="form-group leaseOption d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the seller's desired offering price for a lease option? </label>
                      <input type="number" name="desired_offering_price" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What specific terms does the seller propose for the lease option?</label>
                      <input name="lease_option_terms" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the proposed duration of the lease?</label>
                      <input type="text" name="proposed_lease_duration" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the monthly payment amount the seller is seeking? </label>
                      <input type="number" name="monthly_payment_amount" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for the lease option?</label>
                      <input name="lease_option_conditions" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                        <label class="fw-bold">Is there a possibility of changes in the offering price during the lease period? </label>
                        <input type="text" name="price_changes_possibility" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div> 
                  <div class="form-group">
                    @php
                      $sellerFeeOption = [['name' => 'Yes', 'target' => '.sellerFeeOptionYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Does the seller require an option fee? </label>
                    <select class="grid-picker" name="exchange_trade" style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($sellerFeeOption as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group col-md-12 sellerFeeOptionYes d-none">
                      <label class="fw-bold">How much is the option fee? </label>
                      <input type="text" name="sellerFeeOptionYes"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>               
                </div>
              </div>
              {{-- Lease Option  --}}
              {{-- Lease Purchase  --}}
                <div class="form-group leasePurchase d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the seller's desired offering price for a lease purchase?</label>
                      <input type="number" name="desired_offering_price_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label>
                      <input name="lease_purchase_terms" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the proposed duration of the lease?</label>
                      <input type="text" name="proposed_lease_duration_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label>
                      <input type="number" name="monthly_payment_amount_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for the lease purchase?</label>
                      <input name="lease_purchase_conditions" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">Is there a possibility of changes in the offering price during the lease period?</label>
                      <input type="text" name="price_changes_possibility_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>    
                  <div class="form-group">
                    @php
                      $sellerFeePurchase = [['name' => 'Yes', 'target' => '.sellerFeePurchaseYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Does the seller require an option fee?  </label>
                    <select class="grid-picker" name="exchange_trade" style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($sellerFeePurchase as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group col-md-12 sellerFeePurchaseYes d-none">
                      <label class="fw-bold">How much is the option fee?  </label>
                      <input type="text" name="sellerFeePurchaseYes"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>               
                </div>
              </div>
              {{-- Lease Purchase  --}}
              {{-- Assumable  --}}
                <div class="form-group assumable d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What assumable terms are being offered?</label>
                      <input type="text" name="assumable_terms_offered"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">Are there any restrictions or qualifications for a buyer assuming the existing financing?</label>
                      <input type="text" name="restrictions_or_qualifications"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                      @php
                      $outstandingBalance = [['name' => 'Yes', 'target' => '.outstandingBalanceYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <div class="form-group">
                      <label class="fw-bold">Is there any outstanding balance on the existing financing that the buyer would need to cover?</label>
                      <select class="grid-picker" name="exchange_trade" id="contigencies_accepted_by_seller"
                        style="justify-content: flex-start;" required>
                        <option value="">Select</option>
                        @foreach ($outstandingBalance as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group col-md-12 outstandingBalanceYes d-none">
                        <label class="fw-bold">What is the outstanding balance on the existing financing that the buyer would need to cover? </label>
                        <input type="text" name="outstandingBalanceYes"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                  </div>
                </div>            
              </div>            
              {{-- Assumable  --}}
              <div class="form-group row custom_exchange_trade">
                @php
                  $exchange_trades = [['name' => 'Another home', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vehicles', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Boats', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Motorhomes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Artwork', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Jewelry', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '.otherTrade', 'icon' => 'fa-regular fa-circle-check']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Offered Exchange Item: </label>
                  <select class="grid-picker" name="exchange_trade" id="contigencies_accepted_by_seller"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($exchange_trades as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group col-md-12 otherTrade d-none">
                    <label class="fw-bold">Acceptable Exchange Item:</label>
                    <input type="text" name="otherTrade" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                  <input type="number" name="estimatedTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is willing to exchange/trade?</label>
                  <input type="number" name="specificTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group">
                  <label class="fw-bold">How much cash does the seller require on top of the exchange/trade item? </label>
                  <input type="number" name="cashTrade" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">How is the value of the exchange/trade item determined?  </label>
                  <input type="text" name="valueTrade" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined" required>
                </div>
              </div>
              <div class="form-group row">
                  @php
                    $sellerOffer = [['name' => 'Yes', 'target' => '.sellerOfferYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']];
                  @endphp
                <label class="fw-bold">Is the seller offering a credit to the buyer at closing?  </label>
                <select class="grid-picker" name="sellerOffer" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($sellerOffer as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='{{$item['icon']}}'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>  
                <div class="form-group sellerOfferYes d-none">
                  <label class="fw-bold">What credit amount is the seller offering to the buyer at closing?</label>
                  <input type="number" name="sellerOfferYes" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="4">
              <div class="form-group">
                @php
                  $representation = [['name' => 'Yes', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => '.buyers_agent_compensation'], ['name' => 'No', 'icon' => '<i class="fa-regular fa-xmark-circle"></i>', 'target' => ''], ['name' => 'Not Applicable', 'icon' => '<i class="fa-regular fa-xmark-circle"></i>', 'target' => '']];
                @endphp
                <label class="fw-bold">
                  If the buyer is represented by an agent, will the seller offer the agent a commission?
                </label>
                <select name="agent-commission-offered" id="commission-offered" class="grid-picker" style="justify-content: flex-start;"
                  required>
                  <option value=""></option>
                  @foreach ($representation as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group d-none buyers_agent_compensation">
                  <label class="fw-bold">What is the commission offered to the buyer’s agent?</label>
                  @php
                      $buyersAgentCompensation = [
                          ['name' => '2%', 'target' => ''],
                          ['name' => '2.5%', 'target' => ''],
                          ['name' => '3%', 'target' => ''],
                          ['name' => 'Other', 'target' => '.buyers_agent_comp'],
                      ];
                  @endphp
                  <select class="grid-picker" name="agent-commission" id=""
                      style="justify-content: flex-start;">
                      <option value="">Select</option>
                      @foreach ($buyersAgentCompensation as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                              class="card flex-row" style="width:calc(33.3% - 10px);"
                              data-icon='<i class="fa-regular fa-circle-check"></i>'>
                              {{ $item['name'] }}
                          </option>
                      @endforeach
                  </select>
                </div>
                <div class="form-group buyers_agent_comp d-none">
                    <label class="fw-bold">What compensation is the seller willing to pay the buyer’s agent?</label>
                    <input type="text" name="agent-compensation" id=""
                        class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                </div>
              </div>
            </div>
            {{-- <div class="wizard-step" data-step="4">
              @php
                $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property'], ['name' => 'Business Opportunity'], ['name' => 'Vacant Land']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Offered Property Style:</label>
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
                        ['name' => 'Aeronautical', 'class' => 'business-opportunity'],
                        ['name' => 'Agriculture', 'class' => 'business-opportunity'],
                        ['name' => 'Arts and Entertainment', 'class' => 'business-opportunity'],
                        ['name' => 'Assembly Hall', 'class' => 'business-opportunity'],
                        ['name' => 'Assisted Living', 'class' => 'business-opportunity'],
                        ['name' => 'Auto Dealer', 'class' => 'business-opportunity'],
                        ['name' => 'Auto Service', 'class' => 'business-opportunity'],
                        ['name' => 'Bar/Tavern/Lounge', 'class' => 'business-opportunity'],
                        ['name' => 'Barber/Beauty', 'class' => 'business-opportunity'],
                        ['name' => 'Car Wash', 'class' => 'business-opportunity'],
                        ['name' => 'Child Care', 'class' => 'business-opportunity'],
                        ['name' => 'Church', 'class' => 'business-opportunity'],
                        ['name' => 'Commercial', 'class' => 'business-opportunity'],
                        ['name' => 'Concession Trailers/Vehicles', 'class' => 'business-opportunity'],
                        ['name' => 'Construction/Contractor', 'class' => 'business-opportunity'],
                        ['name' => 'Convenience Store', 'class' => 'business-opportunity'],
                        ['name' => 'Distribution', 'class' => 'business-opportunity'],
                        ['name' => 'Distributor Routine Ven', 'class' => 'business-opportunity'],
                        ['name' => 'Education/School', 'class' => 'business-opportunity'],
                        ['name' => 'Farm', 'class' => 'business-opportunity'],
                        ['name' => 'Fashion/Specialty', 'class' => 'business-opportunity'],
                        ['name' => 'Flex Space', 'class' => 'business-opportunity'],
                        ['name' => 'Florist/Nursery', 'class' => 'business-opportunity'],
                        ['name' => 'Food & Beverage', 'class' => 'business-opportunity'],
                        ['name' => 'Gas Station', 'class' => 'business-opportunity'],
                        ['name' => 'Grocery', 'class' => 'business-opportunity'],
                        ['name' => 'Heavy Weight Sales Service', 'class' => 'business-opportunity'],
                        ['name' => 'Hotel/Motel', 'class' => 'business-opportunity'],
                        ['name' => 'Industrial', 'class' => 'business-opportunity'],
                        ['name' => 'Light Items Sales Only', 'class' => 'business-opportunity'],
                        ['name' => 'Manufacturing', 'class' => 'business-opportunity'],
                        ['name' => 'Marine/Marina', 'class' => 'business-opportunity'],
                        ['name' => 'Medical', 'class' => 'business-opportunity'],
                        ['name' => 'Mixed', 'class' => 'business-opportunity'],
                        ['name' => 'Mobile/Trailer Park', 'class' => 'business-opportunity'],
                        ['name' => 'Other', 'class' => 'business-opportunity'],
                        ['name' => 'Personal Service', 'class' => 'business-opportunity'],
                        ['name' => 'Professional Service', 'class' => 'business-opportunity'],
                        ['name' => 'Professional/Office', 'class' => 'business-opportunity'],
                        ['name' => 'Recreation', 'class' => 'business-opportunity'],
                        ['name' => 'Research & Development', 'class' => 'business-opportunity'],
                        ['name' => 'Residential', 'class' => 'business-opportunity'],
                        ['name' => 'Restaurant', 'class' => 'business-opportunity'],
                        ['name' => 'Retail', 'class' => 'business-opportunity'],
                        ['name' => 'Shopping Center/Strip Center', 'class' => 'business-opportunity'],
                        ['name' => 'Storage', 'class' => 'business-opportunity'],
                        ['name' => 'Theater', 'class' => 'business-opportunity'],
                        ['name' => 'Timberland', 'class' => 'business-opportunity'],
                        ['name' => 'Veterinary', 'class' => 'business-opportunity'],
                        ['name' => 'Warehouse', 'class' => 'business-opportunity'],
                        ['name' => 'Wholesale', 'class' => 'business-opportunity'],
                        ['name' => 'Billboard Site', 'class' => 'vacant-land'],
                        ['name' => 'Business', 'class' => 'vacant-land'],
                        ['name' => 'Cattle', 'class' => 'vacant-land'],
                        ['name' => 'Commercial', 'class' => 'vacant-land'],
                        ['name' => 'Farm', 'class' => 'vacant-land'],
                        ['name' => 'Fishery', 'class' => 'vacant-land'],
                        ['name' => 'Highway Frontage', 'class' => 'vacant-land'],
                        ['name' => 'Horses', 'class' => 'vacant-land'],
                        ['name' => 'Industrial', 'class' => 'vacant-land'],
                        ['name' => 'Land Fill', 'class' => 'vacant-land'],
                        ['name' => 'Livestock', 'class' => 'vacant-land'],
                        ['name' => 'Mixed Use', 'class' => 'vacant-land'],
                        ['name' => 'Nursery', 'class' => 'vacant-land'],
                        ['name' => 'Orchard', 'class' => 'vacant-land'],
                        ['name' => 'Other', 'class' => 'vacant-land'],
                        ['name' => 'Pasture', 'class' => 'vacant-land'],
                        ['name' => 'Poultry', 'class' => 'vacant-land'],
                        ['name' => 'Ranch', 'class' => 'vacant-land'],
                        ['name' => 'Residential', 'class' => 'vacant-land'],
                        ['name' => 'Row Crops', 'class' => 'vacant-land'],
                        ['name' => 'Sod Farm', 'class' => 'vacant-land'],
                        ['name' => 'Subdivision', 'class' => 'vacant-land'],
                        ['name' => 'Timber', 'class' => 'vacant-land'],
                        ['name' => 'Tracts', 'class' => 'vacant-land'],
                        ['name' => 'Trans/Cell Tower', 'class' => 'vacant-land'],
                        ['name' => 'Tree Farm', 'class' => 'vacant-land'],
                        ['name' => 'Unimproved Land', 'class' => 'vacant-land'],
                        ['name' => 'Well Field', 'class' => 'vacant-land'],
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
                  <label for="" class="business-opportunity fw-bold">Business Type: </label>
                  <label for="" class="vacant-land fw-bold">Current Use: </label>
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
            </div> --}}
            <div class="wizard-step" data-step="5">
              <div class="form-group">
                <label class="fw-bold">Offer Expires:</label>
                <input type="date" name="offer-expiry" id=""
                        class="form-control has-icon" data-icon="fa-regular fa-clock" required>
              </div>
            </div>
            {{-- property_listed="Yes" --}}
            <div class="wizard-step" data-step="4">
              <div class="form-group">
                <label class="fw-bold">
                  Special Sale Provision:
                </label>
                <div>
                  @php
                    $special_sale_provision = [  
                      ['target' => '.assignment_contract_res','name' => 'Assignment Contract'],['name' => 'Auction', 'target' => ''], ['name' => 'Bank Owned/Reo', 'target' => ''], ['name' => 'Government Owned', 'target' => ''], ['name' => 'Probate Listing', 'target' => ''], ['name' => 'Short Sale', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.special_sale_provision']];
                  @endphp
                  <select name="special_sale" id="special_sale" class="grid-picker" style="justify-content: flex-start;"
                    required>
                    <option value=""></option>
                    @foreach ($special_sale_provision as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="form-group special_sale_provision d-none">
                <label class="fw-bold">Special Sale Provision :</label>
                <input type="text" name="custom_special_sale_provision" id="custom_special_sale_provision"
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
            </div>
            {{-- Slide 5 --}}
            <div class="wizard-step" data-step="5">
              <div class="form-group">
                <label class="fw-bold">
                  Listing Type:
                </label>
                <div>
                  @php
                    $auction_types = [['target' => '.auctionTimer', 'name' => 'Auction (Timer)', 'icon' => '<i class="fa-regular fa-clock"></i>'], ['target' => '.traditionalTime', 'name' => 'Traditional (No Timer)', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>']];
                  @endphp
                  <select name="auction_type" id="auction_type" class="grid-picker" required>
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
              <div class="form-group  d-none auctionTimer">
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
                      <option value="{{ $item['name'] }}" data-target="" class="card flex-row {{ $item['class'] }}"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="6">
              <span class="timeAuction">
                <h4>Price and Terms:</h4>
                <div class="form-group ">
                  <label class="fw-bold" for="buy_now_price" required>Buy Now Price:</label>
                  <input type="number"  name="buy_now_price"  id="buy_now_price"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar"
                    data-msg-required="Please enter Buy Now Price">
                </div>
                <div class="form-group ">
                  <label class="fw-bold" for="starting_price" required>Starting Price:</label>
                  <input type="number"  name="starting_price"  id="starting_price"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar"
                    data-msg-required="Please enter Starting Price">
                </div>
                <div class="form-group ">
                  <label class="fw-bold" for="reserve_price" required>Reserve Price:</label>
                  <input type="number"  name="reserve_price"  id="reserve_price"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group row ">
                  <div class="form-group">
                    <label class="fw-bold">Acceptable Escrow Deposit:</label>
                    <input type="number" name="escrow_amount" id="term_escrow_amount" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                </div>
                <div class="form-group row ">
                  <div class="form-group">
                    <label class="fw-bold">Number of Days the Seller Will Accept for Closing:</label>
                    <input type="number" name="closing_days" id="closing_days" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-calendar-day" required>
                  </div>
                </div>
                <div class="form-group">
                  @php
                    $contigencies = [['name' => 'Inspection contingency', 'target' => '.inspectionAuction'], ['name' => 'Appraisal contingency', 'target' => '.appraisalAuction'], ['name' => 'Financing contingency', 'target' => '.financingAuction'], ['name' => 'Sale of a property contingency', 'target' => '.saleAuction'], ['name' => 'None', 'target' => ''],['name' => 'Other', 'target' => '.otherContingencyAuction'],];
                  @endphp
                  <label class="fw-bold">Acceptable Contingencies: </label>
                  <select class="grid-picker" name="contigencies_accepted_by_seller" id="contigencies_accepted_by_seller"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($contigencies as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group inspectionAuction d-none">
                    <label class="fw-bold">Inspection contingency (days):</label>
                    <input type="number" name="inspection" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group appraisalAuction d-none">
                    <label class="fw-bold">Appraisal contingency (days):</label>
                    <input type="number" name="appraisal" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group financingAuction d-none">
                    <label class="fw-bold">Finance contingency (days):</label>
                    <input type="number" name="finance" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group saleAuction d-none">
                    <label class="fw-bold"> Sale of a property contingency (days): </label>
                    <input type="number" name="saleContingency" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group otherContingencyAuction d-none">
                    <label class="fw-bold">Acceptable contingency: </label>
                    <input type="number" name="acceptable" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                </div>
                <div class="form-group">
                  @php
                    $term_financings = [
                        ['name' => 'Cash', 'target' => ''],
                        ['name' => 'Conventional', 'target' => ''],
                        ['name' => 'FHA', 'target' => ''],
                        ['name' => 'VA', 'target' => ''],
                        ['name' => 'Litecoin (LTC)', 'target' => ''],
                        ['name' => 'Bitcoin Cash (BCH)', 'target' => ''],
                        ['name' => 'Dash (DASH)', 'target' => ''],
                        ['name' => 'Ripple (XRP)', 'target' => ''],
                        ['name' => 'Tether (USDT)', 'target' => ''],
                        ['name' => 'USD Coin (USDC)', 'target' => ''],
                        ['name' => 'Non-Fungible Token (NFT)', 'target' => '.nftAuction'],
                        ['name' => 'Cryptocurrency', 'target' => '.cryptoAuction'],
                        ['name' => 'USDA', 'target' => ''],
                        ['name' => 'Assumable', 'target' => '.assumableAuction'],
                        ['name' => 'Exchange/Trade', 'target' => '.tradeAuction'],
                        ['name' => 'Lease Option', 'target' => '.leaseOptionAuction'],
                        ['name' => 'Lease Purchase', 'target' => '.leasePurchaseAuction'],
                        ['name' => 'Private Financing Available', 'target' => ''],
                        ['name' => 'Special Funding', 'target' => ''],
                        ['name' => 'Seller Financing', 'target' => '.sellerFinancingAuction d-none'],
                        ['name' => 'Jumbo', 'target' => ''],
                        ['name' => 'Non-QM', 'target' => ''],
                        ['name' => 'No-Doc', 'target' => ''],
                        ['name' => 'Other', 'target' => '.otherFinancingAuction'],
                    ];
                  @endphp
                    <label class="fw-bold">Acceptable Currency/ Financing:</label>
                    <select class="grid-picker" name="term_financings" id="term_financings"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($term_financings as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                          style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                    {{-- Other --}}
                    <div class="form-group otherFinancingAuction d-none">
                        <label class="fw-bold">Acceptable Currency/Financing: </label>
                        <input type="text" name="otherFinancing"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    {{-- Other --}}
                    {{-- NFTAuction  --}}
                    <div class="form-group nftAuction d-none">
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What type of Non-Fungible Token (NFT) will the seller accept?</label>
                          <input type="text" name="type_of_NFT_accepted" id="type_of_NFT_accepted" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What percentage of the sales price will the seller accept in the form of a Non-Fungible Token (NFT)?</label>
                          <input type="number" name="percentage_in_NFT" id="percentage_in_NFT" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
                          <input type="number" name="percentage_in_cash" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                      </div>
                    </div>
                  {{-- NFT  --}}
                  {{-- CryptoAuction  --}}
                  <div class="form-group cryptoAuction d-none">
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What type of cryptocurrency will the seller accept?</label>
                        <input type="text" name="cryptocurrency_type" id="cryptocurrency_type" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What percentage of the sales price will the seller accept in cryptocurrency?</label>
                        <input type="number" name="percentage_in_crypto" id="percentage_in_crypto" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
                        <input type="number" name="percentage_in_cash" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div> 
                    <small>Note: Cryptocurrency can be converted to cash at closing.</small>             
                  </div>
                  <div class="form-group row sellerFinancingAuction d-none">
                    <div class="form-group col-md-3">
                      <label class="fw-bold">Down Payment:</label>
                      <input type="number" name="down_payment_seller_financing" id="down_payment_seller_financing"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-3">
                      <label class="fw-bold">Seller Financing Amount:</label>
                      <input type="number" name="seller_financing_amount" id="seller_financing_amount"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-3">
                      <label class="fw-bold">Interest Rate:</label>
                      <input type="number" name="interest_rate_seller_financing" id="interest_rate_seller_financing"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-3">
                      <label class="fw-bold">Term:</label>
                      <input type="text" name="term_seller_financing" id="term_seller_financing"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-3">
                      <label class="fw-bold">Monthly Payments:</label>
                      <input type="number" name="monthly_payment_seller_financing" id="monthly_payment_seller_financing"
                        class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-3">
                      <label class="fw-bold">Closing Costs:</label>
                      <input type="text" name="closing_costs" id="closing_costs" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group">
                      @php
                        $ballonPenalty = [['name' => 'Yes', 'target' => '.ballonPenaltyYesAuction', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                      @endphp
                      <label class="fw-bold">Prepayment Penalty:</label>
                      <select name="ballonPenalty" id="auto_bid" class="grid-picker" style="justify-content: flex-start;"
                        required>
                        @foreach ($ballonPenalty as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>{{ $item['name'] }}
                        </option>
                        @endforeach
                      </select>
                      <div class="form-group  ballonPenaltyYesAuction d-none">
                        <label class="fw-bold">What is the prepayment penalty amount? </label>
                        <input type="text" name="ballonPenaltyYes" id="closing_costs" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" required>
                      </div>
                    </div>
                    <div class="form-group">
                      @php
                        $balloonPay = [['name' => 'Yes', 'target' => '.balloonPayYesAuction', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                      @endphp
                      <label class="fw-bold">Balloon Payment:</label>
                      <select name="balloonPay" id="auto_bid" class="grid-picker" style="justify-content: flex-start;"
                        required>
                        @foreach ($balloonPay as $item)
                          <option value="{{ $item['name'] }}"
                            data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>{{ $item['name'] }}
                        </option>
                        @endforeach
                      </select>
                        <div class="form-group balloonPayYesAuction d-none">
                          <div class="form-group">
                            <label class="fw-bold">How much is the balloon payment? </label>
                            <input type="text" name="balloonPayment" id="closing_costs" class="form-control has-icon"
                              data-icon="fa-solid fa-dollar-sign" required>
                          </div>
                          <div class="form-group">
                            <label class="fw-bold">When is the balloon payment due? </label>
                            <input type="text" name="balloonDue" id="closing_costs" class="form-control has-icon"
                              data-icon="fa-solid fa-dollar-sign" required>
                          </div>
                        </div>
                    </div>
                  </div>
                  {{-- Lease Option  --}}
                    <div class="form-group leaseOptionAuction d-none">
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What is the seller's desired offering price for a lease option? </label>
                          <input type="number" name="desired_offering_price" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What specific terms does the seller propose for the lease option?</label>
                          <input name="lease_option_terms" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What is the proposed duration of the lease?</label>
                          <input type="text" name="proposed_lease_duration" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What is the monthly payment amount the seller is seeking? </label>
                          <input type="number" name="monthly_payment_amount" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for the lease option?</label>
                          <input name="lease_option_conditions" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                      <div class="form-group col-md-12">
                            <label class="fw-bold">Is there a possibility of changes in the offering price during the lease period? </label>
                            <input type="text" name="price_changes_possibility" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                        </div> 
                      <div class="form-group">
                        @php
                          $sellerFeeOption = [['name' => 'Yes', 'target' => '.sellerFeeOptionYesAuction', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                        @endphp
                        <label class="fw-bold">Does the seller require an option fee? </label>
                        <select class="grid-picker" name="exchange_trade" style="justify-content: flex-start;" required>
                          <option value="">Select</option>
                          @foreach ($sellerFeeOption as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                              style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group col-md-12 sellerFeeOptionYesAuction d-none">
                          <label class="fw-bold">How much is the option fee? </label>
                          <input type="text" name="sellerFeeOptionYes"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>               
                    </div>
                  </div>
                  {{-- Lease Option  --}}
                  {{-- Lease Purchase  --}}
                    <div class="form-group leasePurchaseAuction d-none">
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What is the seller's desired offering price for a lease purchase?</label>
                          <input type="number" name="desired_offering_price_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label>
                          <input name="lease_purchase_terms" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What is the proposed duration of the lease?</label>
                          <input type="text" name="proposed_lease_duration_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label>
                          <input type="number" name="monthly_payment_amount_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for the lease purchase?</label>
                          <input name="lease_purchase_conditions" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">Is there a possibility of changes in the offering price during the lease period?</label>
                          <input type="text" name="price_changes_possibility_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>    
                      <div class="form-group">
                        @php
                          $sellerFeePurchase = [['name' => 'Yes', 'target' => '.sellerFeePurchaseYesAuction', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                        @endphp
                        <label class="fw-bold">Does the seller require an option fee?  </label>
                        <select class="grid-picker" name="exchange_trade" style="justify-content: flex-start;" required>
                          <option value="">Select</option>
                          @foreach ($sellerFeePurchase as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                              style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group col-md-12 sellerFeePurchaseYesAuction d-none">
                          <label class="fw-bold">How much is the option fee?  </label>
                          <input type="text" name="sellerFeePurchaseYes"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>               
                    </div>
                  </div>
                  {{-- Lease Purchase  --}}
                  {{-- AssumableAuction  --}}
                    <div class="form-group assumableAuction d-none">
                      <div class="form-group col-md-12">
                          <label class="fw-bold">What assumable terms are being offered?</label>
                          <input type="text" name="assumable_terms_offered"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                      <div class="form-group col-md-12">
                          <label class="fw-bold">Are there any restrictions or qualifications for a buyer assuming the existing financing?</label>
                          <input type="text" name="restrictions_or_qualifications"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                      <div class="form-group col-md-12">
                          @php
                          $outstandingBalance = [['name' => 'Yes', 'target' => '.outstandingBalanceYesAuction', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                        @endphp
                        <div class="form-group">
                          <label class="fw-bold">Is there any outstanding balance on the existing financing that the buyer would need to cover?</label>
                          <select class="grid-picker" name="exchange_trade" id="contigencies_accepted_by_seller"
                            style="justify-content: flex-start;" required>
                            <option value="">Select</option>
                            @foreach ($outstandingBalance as $item)
                              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                                style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                {{ $item['name'] }}
                              </option>
                            @endforeach
                          </select>
                          <div class="form-group col-md-12 outstandingBalanceYesAuction d-none">
                            <label class="fw-bold">What is the outstanding balance on the existing financing that the buyer would need to cover? </label>
                            <input type="text" name="outstandingBalanceYes"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                        </div>
                      </div>
                    </div>            
                  </div>            
                  {{-- Assumable  --}}
                  <div class="form-group row tradeAuction d-none">
                    @php
                      $exchange_trades = [['name' => 'Another home', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vehicles', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Boats', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Motorhomes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Artwork', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Jewelry', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '.otherTradeAuction', 'icon' => 'fa-regular fa-circle-check']];
                    @endphp
                    <div class="form-group">
                      <label class="fw-bold">Offered Exchange Item: </label>
                      <select class="grid-picker" name="exchange_trade" id="contigencies_accepted_by_seller"
                        style="justify-content: flex-start;" required>
                        <option value="">Select</option>
                        @foreach ($exchange_trades as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group col-md-12 otherTradeAuction d-none">
                        <label class="fw-bold">Acceptable Exchange Item:</label>
                        <input type="text" name="otherTrade" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                    </div>
                    <div class="form-group col-md-12">
                      <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                      <input type="number" name="estimatedTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                    </div>
                    <div class="form-group col-md-12">
                      <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is willing to exchange/trade?</label>
                      <input type="number" name="specificTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much cash does the seller require on top of the exchange/trade item? </label>
                      <input type="number" name="cashTrade" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                    </div>
                    <div class="form-group col-md-12">
                      <label class="fw-bold">How is the value of the exchange/trade item determined?  </label>
                      <input type="text" name="valueTrade" class="form-control has-icon"
                        data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                  </div>
                  <div class="form-group row">
                    @php
                      $sellerOffer = [['name' => 'Yes', 'target' => '.sellerOfferYesAuction','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']];
                    @endphp
                  <label class="fw-bold">Is the seller offering a credit to the buyer at closing?  </label>
                  <select class="grid-picker" name="sellerOffer" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($sellerOffer as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='{{$item['icon']}}'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>  
                  <div class="form-group sellerOfferYesAuction d-none">
                    <label class="fw-bold">What credit amount is the seller offering to the buyer at closing?</label>
                    <input type="number" name="sellerOfferYes" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                </div>
              </span>
              <span class="traditionalTime">
                <h4>Price and Terms:</h4>
                  <div class="form-group ">
                    <label class="fw-bold" for="buy_now_price" required>Price:</label>
                    <input type="number"  name="price" placeholder="" id="buy_now_price"
                      class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar"
                      data-msg-required="Please enter Buy Now Price">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Acceptable Escrow Deposit: :</label>
                    <input type="number" name="escrow_amount2" id="term_escrow_amount" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Number of Days the Seller Will Accept for Closing: </label>
                    <input type="number" name="closing_days2" id="closing_days" placeholder=""
                      class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                <div class="form-group">
                  @php
                    $contigencies = [['name' => 'Inspection contingency', 'target' => '.inspection'], ['name' => 'Appraisal contingency', 'target' => '.appraisal'], ['name' => 'Financing contingency', 'target' => '.financing'], ['name' => 'Sale of a property contingency', 'target' => '.sale'], ['name' => 'None', 'target' => ''],['name' => 'Other', 'target' => '.otherContingency'],];
                  @endphp
                  <label class="fw-bold">Acceptable Contingencies: </label>
                  <select class="grid-picker" name="contigencies_accepted_by_seller" id="contigencies_accepted_by_seller"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($contigencies as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group inspection d-none">
                    <label class="fw-bold">Inspection contingency (days):</label>
                    <input type="number" name="inspection" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group appraisal d-none">
                    <label class="fw-bold">Appraisal contingency (days):</label>
                    <input type="number" name="appraisal" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group financing d-none">
                    <label class="fw-bold">Finance contingency (days):</label>
                    <input type="number" name="finance" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group sale d-none">
                    <label class="fw-bold"> Sale of a property contingency (days): </label>
                    <input type="number" name="saleContingency" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group otherContingency d-none">
                    <label class="fw-bold">Acceptable contingency: </label>
                    <input type="number" name="acceptable" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                </div>
                @php
                  $timeFrame = [
                    ["name"=> "12 hours", "target"=> ''],
                    ["name"=> "24 hours (1 day)", "target"=> ''],
                    ["name"=> "36 hours", "target"=> ''],
                    ["name"=> "48 hours (2 days)", "target"=> ''],
                    ["name"=> "60 hours", "target"=> ''],
                    ["name"=> "72 hours (3 days)", "target"=> ''],
                    ["name"=> "96 hours (4 days)", "target"=> ''],
                    ["name"=> "120 hours (5 days)", "target"=> ''],
                    ["name"=> "144 hours (6 days)", "target"=> ''],
                    ["name"=> "168 hours (7 days)", "target"=> '']
                ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Time Frame Allocated to Respond Offers: </label>
                  <select class="grid-picker" name="timeFrame" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($timeFrame as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                @php
                  $multiTimeFrame = [
                    ["name"=> "12 hours", "target"=> ''],
                    ["name"=> "24 hours (1 day)", "target"=> ''],
                    ["name"=> "36 hours", "target"=> ''],
                    ["name"=> "48 hours (2 days)", "target"=> ''],
                    ["name"=> "60 hours", "target"=> ''],
                    ["name"=> "72 hours (3 days)", "target"=> ''],
                    ["name"=> "96 hours (4 days)", "target"=> ''],
                    ["name"=> "120 hours (5 days)", "target"=> ''],
                    ["name"=> "144 hours (6 days)", "target"=> ''],
                    ["name"=> "168 hours (7 days)", "target"=> '']
                ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Time Frame Allocated to Respond to Multiple Offers:  </label>
                  <select class="grid-picker" name="multiTimeFrame" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($multiTimeFrame as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                    @php
                      $term_financings = [
                          ['name' => 'Cash', 'target' => ''],
                          ['name' => 'Conventional', 'target' => ''],
                          ['name' => 'FHA', 'target' => ''],
                          ['name' => 'VA', 'target' => ''],
                          ['name' => 'Litecoin (LTC)', 'target' => ''],
                          ['name' => 'Bitcoin Cash (BCH)', 'target' => ''],
                          ['name' => 'Dash (DASH)', 'target' => ''],
                          ['name' => 'Ripple (XRP)', 'target' => ''],
                          ['name' => 'Tether (USDT)', 'target' => ''],
                          ['name' => 'USD Coin (USDC)', 'target' => ''],
                          ['name' => 'Non-Fungible Token (NFT)', 'target' => '.nft'],
                          ['name' => 'Cryptocurrency', 'target' => '.crypto'],
                          ['name' => 'USDA', 'target' => ''],
                          ['name' => 'Assumable', 'target' => '.assumable'],
                          ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade'],
                          ['name' => 'Lease Option', 'target' => '.leaseOption'],
                          ['name' => 'Lease Purchase', 'target' => '.leasePurchase'],
                          ['name' => 'Private Financing Available', 'target' => ''],
                          ['name' => 'Special Funding', 'target' => ''],
                          ['name' => 'Seller Financing', 'target' => '.custom_seller_financing'],
                          ['name' => 'Jumbo', 'target' => ''],
                          ['name' => 'Non-QM', 'target' => ''],
                          ['name' => 'No-Doc', 'target' => ''],
                          ['name' => 'Other', 'target' => '.otherFinancing'],
                      ];
                    @endphp
                  <label class="fw-bold">Acceptable Currency/ Financing:</label>
                  <select class="grid-picker" name="term_financings" id="term_financings"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($term_financings as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                  {{-- Other --}}
                  <div class="form-group otherFinancing d-none">
                      <label class="fw-bold">Acceptable Currency/Financing: </label>
                      <input type="text" name="otherFinancing"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  {{-- Other --}}
                  {{-- NFT  --}}
                  <div class="form-group nft d-none">
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What type of Non-Fungible Token (NFT) will the seller accept?</label>
                        <input type="text" name="type_of_NFT_accepted" id="type_of_NFT_accepted" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What percentage of the sales price will the seller accept in the form of a Non-Fungible Token (NFT)?</label>
                        <input type="number" name="percentage_in_NFT" id="percentage_in_NFT" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
                        <input type="number" name="percentage_in_cash" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                    </div>
                  </div>
                {{-- NFT  --}}
                {{-- Crypto  --}}
                <div class="form-group crypto d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What type of cryptocurrency will the seller accept?</label>
                      <input type="text" name="cryptocurrency_type" id="cryptocurrency_type" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What percentage of the sales price will the seller accept in cryptocurrency?</label>
                      <input type="number" name="percentage_in_crypto" id="percentage_in_crypto" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
                      <input type="number" name="percentage_in_cash" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div> 
                  <small>Note: Cryptocurrency can be converted to cash at closing.</small>             
                </div>
                <div class="form-group row custom_seller_financing">
                  <div class="form-group col-md-3">
                    <label class="fw-bold">Down Payment:</label>
                    <input type="number" name="down_payment_seller_financing" id="down_payment_seller_financing"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-3">
                    <label class="fw-bold">Seller Financing Amount:</label>
                    <input type="number" name="seller_financing_amount" id="seller_financing_amount"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-3">
                    <label class="fw-bold">Interest Rate:</label>
                    <input type="number" name="interest_rate_seller_financing" id="interest_rate_seller_financing"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-3">
                    <label class="fw-bold">Term:</label>
                    <input type="text" name="term_seller_financing" id="term_seller_financing"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-3">
                    <label class="fw-bold">Monthly Payments:</label>
                    <input type="number" name="monthly_payment_seller_financing" id="monthly_payment_seller_financing"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group col-md-3">
                    <label class="fw-bold">Closing Costs:</label>
                    <input type="text" name="closing_costs" id="closing_costs" class="form-control has-icon"
                      data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group">
                    @php
                      $ballonPenalty = [['name' => 'Yes', 'target' => '.ballonPenaltyYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Prepayment Penalty:</label>
                    <select name="ballonPenalty" id="auto_bid" class="grid-picker" style="justify-content: flex-start;"
                      required>
                      @foreach ($ballonPenalty as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>{{ $item['name'] }}
                      </option>
                      @endforeach
                    </select>
                    <div class="form-group  ballonPenaltyYes d-none">
                      <label class="fw-bold">What is the prepayment penalty amount? </label>
                      <input type="text" name="ballonPenaltyYes" id="closing_costs" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" required>
                    </div>
                  </div>
                  <div class="form-group">
                    @php
                      $balloonPay = [['name' => 'Yes', 'target' => '.balloonPayYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Balloon Payment:</label>
                    <select name="balloonPay" id="auto_bid" class="grid-picker" style="justify-content: flex-start;"
                      required>
                      @foreach ($balloonPay as $item)
                        <option value="{{ $item['name'] }}"
                          data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>{{ $item['name'] }}
                      </option>
                      @endforeach
                    </select>
                      <div class="form-group balloonPayYes d-none">
                        <div class="form-group">
                          <label class="fw-bold">How much is the balloon payment? </label>
                          <input type="text" name="balloonPayment" id="closing_costs" class="form-control has-icon"
                            data-icon="fa-solid fa-dollar-sign" required>
                        </div>
                        <div class="form-group">
                          <label class="fw-bold">When is the balloon payment due? </label>
                          <input type="text" name="balloonDue" id="closing_costs" class="form-control has-icon"
                            data-icon="fa-solid fa-dollar-sign" required>
                        </div>
                      </div>
                  </div>
                </div>
                {{-- Lease Option  --}}
                  <div class="form-group leaseOption d-none">
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What is the seller's desired offering price for a lease option? </label>
                        <input type="number" name="desired_offering_price" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What specific terms does the seller propose for the lease option?</label>
                        <input name="lease_option_terms" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What is the proposed duration of the lease?</label>
                        <input type="text" name="proposed_lease_duration" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What is the monthly payment amount the seller is seeking? </label>
                        <input type="number" name="monthly_payment_amount" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for the lease option?</label>
                        <input name="lease_option_conditions" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                          <label class="fw-bold">Is there a possibility of changes in the offering price during the lease period? </label>
                          <input type="text" name="price_changes_possibility" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div> 
                    <div class="form-group">
                      @php
                        $sellerFeeOption = [['name' => 'Yes', 'target' => '.sellerFeeOptionYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                      @endphp
                      <label class="fw-bold">Does the seller require an option fee? </label>
                      <select class="grid-picker" name="exchange_trade" style="justify-content: flex-start;" required>
                        <option value="">Select</option>
                        @foreach ($sellerFeeOption as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group col-md-12 sellerFeeOptionYes d-none">
                        <label class="fw-bold">How much is the option fee? </label>
                        <input type="text" name="sellerFeeOptionYes"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>               
                  </div>
                </div>
                {{-- Lease Option  --}}
                {{-- Lease Purchase  --}}
                  <div class="form-group leasePurchase d-none">
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What is the seller's desired offering price for a lease purchase?</label>
                        <input type="number" name="desired_offering_price_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label>
                        <input name="lease_purchase_terms" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What is the proposed duration of the lease?</label>
                        <input type="text" name="proposed_lease_duration_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label>
                        <input type="number" name="monthly_payment_amount_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">Are there any specific conditions or requirements outlined by the seller for the lease purchase?</label>
                        <input name="lease_purchase_conditions" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">Is there a possibility of changes in the offering price during the lease period?</label>
                        <input type="text" name="price_changes_possibility_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>    
                    <div class="form-group">
                      @php
                        $sellerFeePurchase = [['name' => 'Yes', 'target' => '.sellerFeePurchaseYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                      @endphp
                      <label class="fw-bold">Does the seller require an option fee?  </label>
                      <select class="grid-picker" name="exchange_trade" style="justify-content: flex-start;" required>
                        <option value="">Select</option>
                        @foreach ($sellerFeePurchase as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                      <div class="form-group col-md-12 sellerFeePurchaseYes d-none">
                        <label class="fw-bold">How much is the option fee?  </label>
                        <input type="text" name="sellerFeePurchaseYes"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>               
                  </div>
                </div>
                {{-- Lease Purchase  --}}
                {{-- Assumable  --}}
                  <div class="form-group assumable d-none">
                    <div class="form-group col-md-12">
                        <label class="fw-bold">What assumable terms are being offered?</label>
                        <input type="text" name="assumable_terms_offered"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                        <label class="fw-bold">Are there any restrictions or qualifications for a buyer assuming the existing financing?</label>
                        <input type="text" name="restrictions_or_qualifications"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                    <div class="form-group col-md-12">
                        @php
                        $outstandingBalance = [['name' => 'Yes', 'target' => '.outstandingBalanceYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                      @endphp
                      <div class="form-group">
                        <label class="fw-bold">Is there any outstanding balance on the existing financing that the buyer would need to cover?</label>
                        <select class="grid-picker" name="exchange_trade" id="contigencies_accepted_by_seller"
                          style="justify-content: flex-start;" required>
                          <option value="">Select</option>
                          @foreach ($outstandingBalance as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                              style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                              {{ $item['name'] }}
                            </option>
                          @endforeach
                        </select>
                        <div class="form-group col-md-12 outstandingBalanceYes d-none">
                          <label class="fw-bold">What is the outstanding balance on the existing financing that the buyer would need to cover? </label>
                          <input type="text" name="outstandingBalanceYes"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                      </div>
                    </div>
                  </div>            
                </div>            
                {{-- Assumable  --}}
                <div class="form-group row custom_exchange_trade">
                  @php
                    $exchange_trades = [['name' => 'Another home', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vehicles', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Boats', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Motorhomes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Artwork', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Jewelry', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '.otherTrade', 'icon' => 'fa-regular fa-circle-check']];
                  @endphp
                  <div class="form-group">
                    <label class="fw-bold">Offered Exchange Item: </label>
                    <select class="grid-picker" name="exchange_trade" id="contigencies_accepted_by_seller"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($exchange_trades as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group col-md-12 otherTrade d-none">
                      <label class="fw-bold">Acceptable Exchange Item:</label>
                      <input type="text" name="otherTrade" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                    </div>
                  </div>
                  <div class="form-group col-md-12">
                    <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                    <input type="number" name="estimatedTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group col-md-12">
                    <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is willing to exchange/trade?</label>
                    <input type="number" name="specificTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">How much cash does the seller require on top of the exchange/trade item? </label>
                    <input type="number" name="cashTrade" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                  </div>
                  <div class="form-group col-md-12">
                    <label class="fw-bold">How is the value of the exchange/trade item determined?  </label>
                    <input type="text" name="valueTrade" class="form-control has-icon"
                      data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                </div>
                <div class="form-group row">
                    @php
                      $sellerOffer = [['name' => 'Yes', 'target' => '.sellerOfferYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']];
                    @endphp
                  <label class="fw-bold">Is the seller offering a credit to the buyer at closing?  </label>
                  <select class="grid-picker" name="sellerOffer" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($sellerOffer as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='{{$item['icon']}}'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>  
                  <div class="form-group sellerOfferYes d-none">
                    <label class="fw-bold">What credit amount is the seller offering to the buyer at closing?</label>
                    <input type="number" name="sellerOfferYes" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                </div>
              </span>
            </div>
            {{-- Slide 6 --}}
            {{-- Slide 7 --}}
            <div class="wizard-step" data-step="7">
              @php
                $property_types = [['name' => 'Residential Property'], ['name' => 'Income Property'], ['name' => 'Commercial Property'], ['name' => 'Business Opportunity'], ['name' => 'Vacant Land']];
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
                        // Residential Items
                        ['target'=>'','name' => 'Single Family Residence', 'class' => 'residential-length'],
                        ['target'=>'','name' => 'Townhouse', 'class' => 'residential-length'],
                        ['target'=>'','name' => 'Villa', 'class' => 'residential-length'],
                        ['target'=>'','name' => 'Condominium', 'class' => 'residential-length'],
                        ['target'=>'','name' => 'Condo-Hotel', 'class' => 'residential-length'],
                        ['target'=>'','name' => '½ Duplex', 'class' => 'residential-length'],
                        ['target'=>'','name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                        ['target'=>'','name' => 'Farm', 'class' => 'residential-length'],
                        ['target'=>'','name' => 'Garage Condo', 'class' => 'residential-length'],
                        ['target'=>'','name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
                        ['target'=>'','name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
                        ['target'=>'','name' => 'Modular Home', 'class' => 'residential-length'],
                        // Income Items
                        ['target'=>'','name' => 'Duplex', 'class' => 'income-length'],
                        ['target'=>'','name' => 'Triplex', 'class' => 'income-length'],
                        ['target'=>'','name' => 'Quadplex', 'class' => 'income-length'],
                        ['target'=>'','name' => 'Five or More', 'class' => 'income-length'],
                        // Commercial items
                        ['target'=>'','name' => 'Agriculture', 'class' => 'commercial-length'],
                        ['target'=>'','name' => 'Assembly Building', 'class' => 'commercial-length'],
                        ['target'=>'','name' => 'Business', 'class' => 'commercial-length'],
                        ['target'=>'','name' => 'Five or More', 'class' => 'commercial-length'],
                        ['target'=>'','name' => 'Hotel/Motel', 'class' => 'commercial-length'],
                        ['target'=>'','name' => 'Industrial', 'class' => 'commercial-length'],
                        ['target'=>'','name' => 'Mixed Use', 'class' => 'commercial-length'],
                        ['target'=>'','name' => 'Office', 'class' => 'commercial-length'],
                        ['target'=>'','name' => 'Restaurant', 'class' => 'commercial-length'],
                        ['target'=>'','name' => 'Retail', 'class' => 'commercial-length'],
                        ['target'=>'','name' => 'Warehouse', 'class' => 'commercial-length'],
                        // Business Items
                        ['target'=>'','name' => 'Aeronautical', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Agriculture', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Arts and Entertainment', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Assembly Hall', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Assisted Living', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Auto Dealer', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Auto Service', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Bar/Tavern/Lounge', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Barber/Beauty', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Car Wash', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Child Care', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Church', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Commercial', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Concession Trailers/Vehicles', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Construction/Contractor', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Convenience Store', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Distribution', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Distributor Routine Ven', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Education/School', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Farm', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Fashion/Specialty', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Flex Space', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Florist/Nursery', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Food & Beverage', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Gas Station', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Grocery', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Heavy Weight Sales Service', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Hotel/Motel', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Industrial', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Light Items Sales Only', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Manufacturing', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Marine/Marina', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Medical', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Mixed', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Mobile/Trailer Park', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Personal Service', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Professional Service', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Professional/Office', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Recreation', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Research & Development', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Residential', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Restaurant', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Retail', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Shopping Center/Strip Center', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Storage', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Theater', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Timberland', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Veterinary', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Warehouse', 'class' => 'business-length'],
                        ['target'=>'','name' => 'Wholesale', 'class' => 'business-length'],
                        ['target'=>'.otherBusiness','name' => 'Other', 'class' => 'business-length'],
                        // Vacant Land Items
                        ['target'=>'','name' => 'Aeronautical', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Agriculture', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Arts and Entertainment', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Assembly Hall', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Assisted Living', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Auto Dealer', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Auto Service', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Bar/Tavern/Lounge', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Barber/Beauty', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Car Wash', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Child Care', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Church', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Commercial', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Concession Trailers/Vehicles', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Construction/Contractor', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Convenience Store', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Distribution', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Distributor Routine Ven', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Education/School', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Farm', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Fashion/Specialty', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Flex Space', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Florist/Nursery', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Food & Beverage', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Gas Station', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Grocery', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Heavy Weight Sales Service', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Hotel/Motel', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Industrial', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Light Items Sales Only', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Manufacturing', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Marine/Marina', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Medical', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Mixed', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Mobile/Trailer Park', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Personal Service', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Professional Service', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Professional/Office', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Recreation', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Research & Development', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Residential', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Restaurant', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Retail', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Shopping Center/Strip Center', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Storage', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Theater', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Timberland', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Veterinary', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Warehouse', 'class' => 'vacant_land-length'],
                        ['target'=>'','name' => 'Wholesale', 'class' => 'vacant_land-length'],
                        ['target'=>'.otherVacant','name' => 'Other', 'class' => 'vacant_land-length']
                    ];
                  @endphp
                  <label class="fw-bold currentUse">Current Use:</label>
                  <label class="fw-bold businessType">Business Type: </label>
                  <select name="property_items" id="property_items" class="property_items grid-picker"
                    style="justify-content: flex-start;">
                    <option value=""></option>
                    @foreach ($property_items as $item)
                      <option value="{{ $item['name'] }}" data-target="{{$item['target']}}" class="card flex-row {{ $item['class'] }}"
                        style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherVacant d-none">
                    <label class="fw-bold">Current Use: </label>
                    <input type="text" name="otherProperty" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                  <div class="form-group otherBusiness d-none">
                    <label class="fw-bold">Business Type: </label>
                    <input type="text" name="otherProperty" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                  </div>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="8">
              @php
                $prop_conditions = [
                  ['name' => 'Pre-Construction', 'target' => ''],
                  ['name' => 'Currently Being Built', 'target' => ''],
                  ['name' => 'New Construction', 'target' => ''],
                  ['name' => 'Completely Updated: No updates needed.', 'target' => ''],
                  ['name' => 'Semi-updated: Needs minor updates.', 'target' => ''],
                  ['name' => 'Not Updated: Requires a complete update.', 'target' => ''],
                  ['name' => 'Tear Down: Requires complete demolition and reconstruction.', 'target' => ''],
                  ['name' => 'Other', 'target' => '.otherConditionRes'],
                ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Property Condition:</label>
                <select class="grid-picker" name="prop_condition" id="prop_condition"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($prop_conditions as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-row"
                      style="width:calc(50% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherConditionRes d-none">
                  <label class="fw-bold">Property Condition: </label>
                  <input type="number" name="otherCondition" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
              </div>
            </div>
            @php
              $bedrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_bedrooms', 'name' => 'Other']];
              $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.custom_bathrooms', 'name' => 'Other']];
              $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
  
            @endphp
            <div class="wizard-step" data-step="9">
              <div class="form-group">
                <label class="fw-bold">Bedrooms:</label>
                <select class="grid-picker" name="bedrooms" id="bedrooms" style="">
                  <option value="">Select</option>
                  @foreach ($bedrooms as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                      style="width:calc(20% - 10px);" data-icon='<i class="fa-solid fa-bed"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group custom_bedrooms d-none">
                <label class="fw-bold">Bedrooms:</label>
                <input type="number" name="custom_bedrooms" id="custom_bedrooms" class="form-control has-icon"
                  data-icon="fa-solid fa-bed">
              </div>
            </div>
            {{-- Slide 9 --}}
            {{-- Slide 10 --}}
            <div class="wizard-step" data-step="10">
              <div class="form-group">
                <label class="fw-bold">Bathrooms:</label>
                <select class="grid-picker" name="bathrooms" id="bathrooms" style="">
                  <option value="">Select</option>
                  @foreach ($bathrooms as $item)
                    @php
                      if ($item['name'] == 'Other') {
                          $target = '.custom_bathroom_residential_and_income';
                      } else {
                          $target = '';
                      }
                    @endphp
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-column"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-solid fa-bath"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group custom_bathroom_residential_and_income d-none">
                <label class="fw-bold">Bathrooms:</label>
                <input type="number" name="custom_bathrooms" id="custom_bathrooms" class="form-control has-icon"
                  data-icon="fa-solid fa-bath">
              </div>
            </div>
            @php
              $unitStructure = [['target' => '', 'name' => '1 Bed/1 Bath'], ['target' => '', 'name' => '1 Bedroom'], ['target' => '', 'name' => '2 Bed/1 Bath'], ['target' => '', 'name' => '2 Bed/2 Bath'], ['target' => '', 'name' => '2 Bedroom'], ['target' => '', 'name' => '3 Bed/1 Bath'], ['target' => '', 'name' => '3 Bed/2 Bath'], ['target' => '', 'name' => '3 Bedroom'], ['target' => '', 'name' => '4 Bedroom or more'], ['target' => '', 'name' => '4+ Bed/1 Bath'], ['target' => '', 'name' => '4 Bed/2 Bath'], ['target' => '', 'name' => 'Apartments'], ['target' => '', 'name' => 'Efficiency'], ['target' => '', 'name' => 'Loft'], ['target' => '', 'name' => "Manager's Unit"]];
  
            @endphp
            <div class="wizard-step" data-step="11">
              <div class="row">
                <div class="form-group">
                  <label class="fw-bold">Unit Type:</label>
                  <select class="grid-picker" name="unit_type" id="unit_type" style="">
                    <option value="">Select</option>
                    @foreach ($unitStructure as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                        style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Beds/Unit:</label>
                  <input type="number" name="beds_unit" id="beds_unit"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Baths/Unit:</label>
                  <input type="number" name="baths_unit" id="baths_unit"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Sqft Heated:</label>
                  <input type="number" name="sqt_ft_heated" id="sqt_ft_heated"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Number of Units:</label>
                  <input type="number" name="number_of_units" id="number_of_units" 
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
  
                @php
                  $requires_or_no3 = [['name' => 'Yes', 'target' => '.custom_occupied', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '.custom_occupied_rent', 'icon' => 'fa-regular fa-circle-xmark']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Are any units occupied? </label>
                  <select class="grid-picker" name="occupied" id="occupied" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($requires_or_no3 as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group col-md-4 d-none custom_occupied">
                  <label class="fw-bold">Number of Occupied Units:  </label>
                  <input type="number" name="custom_occupied" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
  
                <div class="form-group col-md-4 d-none custom_occupied">
                  <label class="fw-bold">Current Rent</label>
                  <input type="number" name="current_rent" id="current_rent"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4 custom_occupied_rent  d-none">
                  <label class="fw-bold">Expected Rent</label>
                  <input type="number" name="expected_rent" id="expected_rent"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Garage Spaces</label>
                  <input type="text" name="garage_spaces_unit" id="garage_spaces_unit"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">Unit Type Description:</label>
                  <textarea name="unit_type_of_description" id="unit_type_of_description" class="form-control" cols="30"
                    rows="10"></textarea>
                </div>
  
                <div class="form-group col-md-4">
                  <label class="fw-bold">Annual Gross Income</label>
                  <input type="number" name="annual_gross_income" id="garage_attribute"
                     class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Total Monthly Rent</label>
                  <input type="number" name="total_monthly_rent" id="garage_attribute" 
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Total Monthly Expenses</label>
                  <input type="text" name="total_monthly_expenses" id="garage_attribute"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
  
                <div class="form-group col-md-4">
                  <label class="fw-bold">Annual Net Income</label>
                  <input type="text" name="annual_net_income" id="garage_attribute" 
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Est Annual Market Income</label>
                  <input type="text" name="est_annual_market_income" id="garage_attribute"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Annual Expenses</label>
                  <input type="text" name="annual_expenses" id="garage_attribute"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                @php
                  $terms_of_leases = [['name' => 'Gross Lease', 'target' => ''], ['name' => 'Net Lease', 'target' => ''], ['name' => 'Pass Throughs', 'target' => ''], ['name' => 'Purchase Options', 'target' => ''], ['name' => 'Renewal Option', 'target' => ''], ['name' => 'Other', 'target' => '.otherTermLease']];
  
                  $leases_terms = [['name' => 'Month to Month', 'target' => ''], ['name' => '12 Months', 'target' => ''], ['name' => '24 Months', 'target' => ''], ['name' => '3-5 Years', 'target' => ''], ['name' => '6+ Years', 'target' => ''], ['name' => 'Other', 'target' => '.custom_leases_terms']];
                @endphp
  
                <div class="form-group ">
                  <label class="fw-bold">Length of Lease:</label>
                  <select class="grid-picker" name="terms_of_lease[]" id="terms_of_lease"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($leases_terms as $terms_of_lease)
                      <option value="{{ $terms_of_lease['name'] }}" data-target="{{ $terms_of_lease['target'] }}"
                        class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        style="width:calc(33.3% - 10px);">
                        {{ $terms_of_lease['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_leases_terms d-none">
                  <label class="fw-bold">Length of Lease:</label>
                  <input type="text" name="custom_leases_terms" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
  
                <div class="form-group">
                  <label class="fw-bold">Terms of Lease:</label>
                  <select class="grid-picker" name="terms_of_lease" id="terms_of_lease"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($terms_of_leases as $terms_of_lease)
                      <option value="{{ $terms_of_lease['name'] }}" data-target="{{ $terms_of_lease['target'] }}"
                        class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        style="width:calc(33.3% - 10px);">
                        {{ $terms_of_lease['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherTermLease d-none">
                    <label class="fw-bold">Terms of Lease:</label>
                    <input type="text" name="otherTermLease" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                </div>
                @php
                  $tenant_pays = [['name' => 'Association Fees', 'target' => ''], ['name' => 'Electricity', 'target' => ''], ['name' => 'Parking Fee', 'target' => ''], ['name' => 'Sewer', 'target' => ''], ['name' => 'Trash Collection', 'target' => ''], ['name' => 'Water', 'target' => ''],['name' => 'Gas', 'target' => ''],['name' => 'Other', 'target' => '.otherTenantPayRes']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Tenant Pays:</label>
                  <select class="grid-picker" name="tenant_pays" id="terms_of_lease"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($tenant_pays as $tenant_pay)
                      <option value="{{ $tenant_pay['name'] }}" data-target="{{ $tenant_pay['target'] }}"
                        class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        style="width:calc(33.3% - 10px);">
                        {{ $tenant_pay['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherTenantPayRes d-none">
                    <label class="fw-bold">Tenant Pays:</label>
                    <input type="text" name="otherTenantPay" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                      <option value="{{ $financial_source['name'] }}" data-target="{{ $financial_source['target'] }}"
                        class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        style="width:calc(33.3% - 10px);">
                        {{ $financial_source['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
  
                <div class="form-group">
                  <label class="fw-bold">Total Number of Units:</label>
                  <input type="number" name="total_number_of_units" id="total_number_of_units" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
  
              </div>
            </div>
            <div class="wizard-step" data-step="12">
              <div class="row ">
                <div class="form-group">
                  <label class="fw-bold" for="heated_sqft">Heated Sqft:</label>
                  <input type="number" name="heated_sqft" id="heated_sqft" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="sqft"> Total Sqft:</label>
                  <input type="number" name="total_sqft" id="total_sqft" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
                @php
                  $heated_sources = [['name' => 'Appraisal', 'target' => ''], ['name' => 'Building', 'target' => ''], ['name' => 'Measured', 'target' => ''], ['name' => 'Owner Provided', 'target' => ''], ['name' => 'Public Records', 'target' => '']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold"> Sqft Heated Source:</label>
                  <select class="grid-picker" name="heated_source" id="heated_sources"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($heated_sources as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-column"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
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
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="lot_size">Lot Size Square Footage:</label>
                  <input type="number" name="lot_size" id="lot_size" class="form-control has-icon "
                    data-icon="fa-solid fa-ruler-combined" data-msg-required="Please enter Lot Size">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="year_built">Year Built:</label>
                  <input type="text" name="year_built" id="year_built" class="form-control has-icon "
                    data-icon="fa-solid fa-calendar-day">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="year_built">Legal Description:</label>
                  <input type="text" name="legal_description" id="legal_description" class="form-control has-icon "
                    data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="year_built">Legal Subdivison Name:</label>
                  <input type="text" name="legal_subdivison_name" id="legal_subdivison_name"
                    class="form-control has-icon " data-icon="fa-solid fa-ruler-combined"
                   >
                </div>
              </div>
            </div>
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
                    ['name' => 'Trash Compactor', 'target' => ''],
                    ['name' => 'Washer', 'target' => ''],
                    ['name' => 'Water Filtration System', 'target' => ''],
                    ['name' => 'Water Purifier', 'target' => ''],
                    ['name' => 'Water Softener', 'target' => ''],
                    ['name' => 'Whole House R.O. System', 'target' => ''],
                    ['name' => 'Wine Refrigerator', 'target' => ''],
                    ['name' => 'Touchless Faucet', 'target' => ''],
                    ['name' => 'None', 'target' => ''],
                    ['name' => 'Other', 'target' => '.otherAppliancesRes'],
                ];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Appliances:</label>
                <select class="grid-picker" name="appliances[]" id="appliances" style="justify-content: flex-start;"
                  multiple>
                  <option value="">Select</option>
                  @foreach ($appliances as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherAppliancesRes d-none">
                  <label class="fw-bold">Appliances:</label>
                  <input type="text" name="otherAppliances" id="flood_zone_code" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">
                  Fireplace:
                </label>
  
                <select name="fireplace" id="fireplace" class="grid-picker" style="justify-content: flex-start;">
                  <option value=""></option>
                  @foreach ($yes_or_nos as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
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
                      style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="row " id="has_furnishing_residential_and_income" style="display: none">
                <div class="form-group">
                  <div class="form-group">
                    <label class="fw-bold">What furnishings are included in the purchase?</label>
                    <input type="text" name="furnishings_include" id="flood_zone_code" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group has_additional_fees1">
                  <div class="form-group">
                    <label class="fw-bold">How much is the listed furniture?</label>
                    <input type="number" name="listed_furniture_price" id="listed_furniture_price" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                    ['name' => 'Primary Bedroom Main Floor', 'target' => ''],
                    ['name' => 'Primary Bedroom Upstairs', 'target' => ''],
                    ['name' => 'Open Floorplan', 'target' => ''],
                    ['name' => 'Pest Guard System', 'target' => ''],
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
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($interior_features as $interior_feature)
                    <option value="{{ $interior_feature['name'] }}" data-target="{{ $interior_feature['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{$interior_feature['name']}}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherInterior d-none">
                  <label class="fw-bold">Interior Features:</label>
                  <input type="text" name="otherInterior" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                <select class="grid-picker" name="additionalRooms" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($additionalRoom as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="17">
              <div class="form-group">
                <label class="fw-bold">How many floors are in the property? </label>
                <input type="text" name="number_of_buildings" id="number_of_buildings" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-building">
              </div>
              <div class="form-group">
                <label class="fw-bold">What floor number is the property on?</label>
                <input type="text" name=" " id="floors_in_unit" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">How many floors are in the entire building?</label>
                <input type="text" name="total_floors" id="total_floors" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                      style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="18">
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
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($floor_coverings as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherFloorCoveringRes d-none">
                  <label class="fw-bold">Floor Covering:</label>
                  <input type="text" name="otherFloorCovering" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="19">
              @php
                $front_exposures = [['name' => 'North', 'target' => ''], ['name' => 'East', 'target' => ''], ['name' => 'South', 'target' => ''], ['name' => 'West', 'target' => ''], ['name' => 'Southeast', 'target' => ''], ['name' => 'Northeast', 'target' => ''], ['name' => 'Southwest', 'target' => ''], ['name' => 'Northwest', 'target' => ''], ['name' => 'Undetermined', 'target' => '']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Front Exposure:</label>
                <select class="grid-picker" name="front_exposure" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($front_exposures as $front_exposure)
                    <option value="{{ $front_exposure['name'] }}" data-target="{{ $front_exposure['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $front_exposure['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="20">
              @php
                $foundations = [['name' => 'Basement', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick/Mortar', 'target' => ''], ['name' => 'Concrete Perimeter', 'target' => ''], ['name' => 'Crawlspace', 'target' => ''], ['name' => 'Pillar/Post/Pier', 'target' => ''], ['name' => 'Slab', 'target' => ''], ['name' => 'Stem Wall', 'target' => ''], ['name' => 'Stilt/On Piling', 'target' => ''], ['name' => 'Other', 'target' => '.otherFoundationRes']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Foundation:</label>
                <select class="grid-picker" name="foundation[]" id="foundation" style="justify-content: flex-start;"
                  multiple>
                  <option value="">Select</option>
                  @foreach ($foundations as $foundation)
                    <option value="{{ $foundation['name'] }}" data-target="{{ $foundation['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $foundation['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherFoundationRes d-none">
                  <label class="fw-bold">Foundation:</label>
                  <input type="text" name="otherFoundation" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="21">
              @php
                $exterior_constructions = [['name' => 'Asbestos', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Cedar', 'target' => ''], ['name' => 'Cement Siding', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'HardiPlank Type', 'target' => ''], ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''], ['name' => 'Log', 'target' => ''], ['name' => 'Metal Frame', 'target' => ''], ['name' => 'Metal Siding', 'target' => ''], ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''], ['name' => 'Stone', 'target' => ''], ['name' => 'Stucco', 'target' => ''], ['name' => 'Tilt up Walls', 'target' => ''], ['name' => 'Vinyl Siding', 'target' => ''], ['name' => 'Wood Frame', 'target' => ''], ['name' => 'Wood Frame (FSC)', 'target' => ''], ['name' => 'Wood Siding ', 'target' => ''], ['name' => 'Other', 'target' => '.otherConstructionRes']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Exterior Construction:</label>
                <select class="grid-picker" name="exterior_construction[]" id="exterior_construction"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($exterior_constructions as $item)
                    <option value="{{ $item['name'] }}"
                      data-target="{{ $item['target'] }}" class="card flex-row"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherConstructionRes d-none">
                  <label class="fw-bold">Exterior Construction:</label>
                  <input type="text" name="otherConstruction" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="22">
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
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($exterior_features as $exterior_feature)
                    <option value="{{ $exterior_feature['name'] }}" data-target="{{ $exterior_feature['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $exterior_feature['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherExteriorRes d-none">
                  <label class="fw-bold">Exterior Features:</label>
                  <input type="text" name="otherExterior" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="23">
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
                      style="width:calc(33.3% - 10px);">
                      {{ $lot_feature['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherLotFeaturesRes d-none">
                  <label class="fw-bold">Lot Features:</label>
                  <input type="text" name="otherLotFeature" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="24">
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
                      data-icon="{{$item['icon']}}" class="card flex-row" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group otherStructureResYes d-none">
                @php
                  $otherStructureRes = [
                      ['name' => 'Additional Single Family Home', 'target' => ''],
                      ['name' => 'In-Law- Suite', 'target' => ''],
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
                <select class="grid-picker" name="otherStruct" id="otherStucture" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($otherStructureRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherStructureRes d-none">
                  <label class="fw-bold">Other Structures: </label>
                  <input type="number" name="otherStructure" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                      ['name' => 'Other', 'target' => '.otherUnitRes']
                  ];
                @endphp
                <label class="fw-bold">Unit Type: </label>
                <select class="grid-picker" name="unitStructure" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($unitStructureRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherUnitRes d-none">
                  <div class="form-group">
                    <label class="fw-bold">Heated Sqft of Additional Structure:</label>
                    <input type="number" name="sqftStructure" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Total Sqft of Additional Structure: </label>
                    <input type="number" name="totalSqft" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="25">
              @php
                $roofs = [['name' => 'Built-Up', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Membrane', 'target' => ''], ['name' => 'Metal', 'target' => ''], ['name' => 'Roof Over', 'target' => ''], ['name' => 'Shake', 'target' => ''], ['name' => 'Shingle', 'target' => ''], ['name' => 'Slate', 'target' => ''], ['name' => 'Tile', 'target' => ''], ['name' => 'Cement', 'target' => ''], ['name' => 'Other', 'target' => '.otherRoofRes']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Roof:</label>
                <select class="grid-picker" name="roof[]" id="roof" style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($roofs as $roof)
                    <option value="{{ $roof['name'] }}" data-target="{{ $roof['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $roof['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherRoofRes d-none" >
                  <label class="fw-bold">Roof:</label>
                  <input type="text" name="otherRoof" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="26">
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
                      data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                      {{ $road_surface_type['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherSurfaceRes d-none" >
                  <label class="fw-bold">Road Surface Type:</label>
                  <input type="number" name="otherSurface" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="27">
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
                      data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                      {{ $yes_or_no['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group garage_spaces">
                <label class="fw-bold" for="garage_spaces">How many garage spaces?</label>
                <input type="text"  name="garage_spaces" id="garage_spaces"
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
                      data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                      {{ $yes_or_no['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group carport_spaces">
                <label class="fw-bold" for="carport_spaces">How many carport spaces?</label>
                <input type="text"  name="carport_spaces" id="carport_spaces"
                  class="form-control has-icon " data-icon="fa-solid fa-warehouse">
              </div>
            </div>
            <div class="wizard-step" data-step="28">
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
                      data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
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
                      data-icon='<i class="{{ $yes_or_no['icon'] }}"></i>'>
                      {{ $yes_or_no['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="29">
              <div class="row ">
                <div class="form-group">
                  <label class="fw-bold">Acceptable Pet Types:</label>
                  <input type="text" name="acceptablePet" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Pets Allowed:</label>
                  <select class="grid-picker" name="ptesAllowed" id="has_rental_restrictions"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '.pets_allowed_question12';
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
                @php
                  $total_pets_allowed = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_pets_allowed', 'name' => 'Other']];
                @endphp
                <div class="form-group pets_allowed_question12 d-none">
                  <div class="form-group">
                    <label class="fw-bold">Number of Pets Allowed:</label>
                    <select class="grid-picker" name="total_pets_allowed" id="total_pets_allowed"
                      style="justify-content: flex-start;">
                      <option value="">Select</option>
                      @foreach ($total_pets_allowed as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-column" style="width:calc(10% - 10px);"
                          data-icon='<i class="fa-solid fa-dog"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group custom_pets_allowed d-none">
                    <label class="fw-bold">Number of Pets Allowed:</label>
                    <input type="text" name="custom_pets_allowed" id="custom_pets_allowed"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Max Pet Weight:</label>
                    <input type="text" name="max_pet_weight" id="max_pet_weight" class="form-control has-icon"
                      data-icon="fa-solid fa-ruler-combined">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Pet Restrictions:</label>
                    <textarea name="pet_restrictions" id="pet_restrictions" class="form-control" cols="30" rows="5"></textarea>
                  </div>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="30">
              <h4>Tax Info</h4>
              <div class="form-group">
                <label class="fw-bold">Tax ID (Parcel Number):</label>
                <input type="text" name="tax_id" id="tax_id" class="form-control has-icon"
                  data-icon="fa-regular fa-id-card">
              </div>
              <div class="form-group">
                <label class="fw-bold">Tax Year:</label>
                <input type="text" name="tax_year" id="tax_year" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined" >
              </div>
              <div class="form-group">
                <label class="fw-bold">Taxes (Annual Amount):</label>
                <input type="text" name="taxes_annual_amount" id="taxes_annual_ammount"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar">
              </div>
              <div class="form-group ">
                @php
                $additialParcelRes = [
                  ['name'=>'Yes','target'=>'.additialParcelResYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                  ['name'=>'No','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']
                ];
              @endphp
                <label class="fw-bold">Additional Parcels:</label>
                <select class="grid-picker" name="has_homestead" id="sewer"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($additialParcelRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="{{$item['icon']}}" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group additialParcelResYes d-none">
                  <div class="form-group">
                    <label class="fw-bold">Total Number of Parcels:</label>
                    <input type="number" name="total_number_of_parcels" id="total_number_of_parcels"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Additional Tax ID's:</label>
                    <input type="number" name="additional_tax_id" id="additional_tax_id" class="form-control has-icon"
                      data-icon="fa-solid fa-ruler-combined">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Zoning:</label>
                    <input type="text" name="zoning" id="zoning"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Legal Description:</label>
                    <input type="text" name="legal_description" id="legal_description" class="form-control has-icon"
                      data-icon="fa-solid fa-tag">
                  </div>
                  <div class="form-group ">
                    <label class="fw-bold">Homestead:</label>
                    <select class="grid-picker" name="has_homestead" id="sewer"
                      style="justify-content: flex-start;">
                      <option value="">Select</option>
                      @foreach ($yes_or_nos as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                          style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="31">
              <div class="form-group">
                <label class="fw-bold">Is the property in a flood zone?</label>
                <select class="grid-picker" name="is_in_flood_zone" id="is_in_flood_zone"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.has_flood_zoon';
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
              <div class="form-group has_flood_zoon d-none">
                <label class="fw-bold">Flood Zone Code:</label>
                <input type="text" name="flood_zone_code" id="flood_zone_code" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
            </div>
            <div class="wizard-step" data-step="32">
              @php
                $utilitiseRes = [
                  ['name' => 'BB/HS Internet Available', 'target' => ''],
                  ['name' => 'Cable Available', 'target' => ''],
                  ['name' => 'Cable Connected', 'target' => ''],
                  ['name' => 'Electric - Multiple Meters', 'target' => ''],
                  ['name' => 'Electricity Available', 'target' => ''],
                  ['name' => 'Electricity Connected', 'target' => ''],
                  ['name' => 'Emergency Power', 'target' => ''],
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
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherUtilitiseRes d-none">
                  <label class="fw-bold">Utilities:</label>
                  <input type="text" name="otherUtilitise" id="legal_description" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
              @php
  
                $waterRes = [['name' => 'Canal/Lake For Irrigation', 'target' => ''], ['name' => 'Private', 'target' => ''], ['name' => 'Public', 'target' => ''], ['name' => 'Well', 'target' => ''],['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherWaterRes']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Water:</label>
                <select class="grid-picker" name="water" id="water12" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($waterRes as $water)
                    <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $water['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherWaterRes d-none">
                  <label class="fw-bold">Water:</label>
                  <input type="text" name="otherWater" id="legal_description" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
  
              @php
                $sewerRes = [['name' => 'Aerobic Septic', 'target' => ''],['name' => 'PEP-Holding Tank', 'target' => ''], ['name' => 'Private Sewer', 'target' => ''], ['name' => 'Public Sewer', 'target' => ''], ['name' => 'Septic Needed', 'target' => ''], ['name' => 'Septic Tank', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherSewerRes']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Sewer:</label>
                <select class="grid-picker" name="sewer" id="sewer" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($sewerRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherSewerRes d-none">
                  <label class="fw-bold">Sewer:</label>
                  <input type="text" name="otherSewer"  class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="33">
              @php
                $air_conditioning = [['name' => 'Central Air', 'target' => ''], ['name' => 'Humidity Control', 'target' => ''], ['name' => 'Mini-Split Unit(s)', 'target' => ''], ['name' => 'Wall/Window Unit(s)', 'target' => ''], ['name' => 'Zoned', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.airConditionRes']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Air Conditioning:</label>
                <select class="grid-picker" name="air_conditioning" id="air_conditioning"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($air_conditioning as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group airConditionRes d-none">
                  <label class="fw-bold">Air Conditioning:</label>
                  <input type="text" name="otherAirCondition"  class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
              @php
                $heating_and_fuel = [['name' => 'Baseboard', 'target' => ''], ['name' => 'Central', 'target' => ''], ['name' => 'Electric', 'target' => ''], ['name' => 'Exhaust Fans', 'target' => ''], ['name' => 'Heat Pump', 'target' => ''], ['name' => 'Heat Recovery Unit', 'target' => ''], ['name' => 'Natural Gas', 'target' => ''], ['name' => 'Oil', 'target' => ''], ['name' => 'Partial', 'target' => ''], ['name' => 'Propane', 'target' => ''], ['name' => 'Radiant Ceiling', 'target' => ''], ['name' => 'Reverse Cycle', 'target' => ''], ['name' => 'Solar', 'target' => ''], ['name' => 'Space Heater', 'target' => ''], ['name' => 'Wall Furnace', 'target' => ''], ['name' => 'Wall Units / Window Unit', 'target' => ''], ['name' => 'Zoned', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherHeatingFuelRes']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Heating and Fuel:</label>
                <select class="grid-picker" name="heating_and_fuel" id="heating_and_fuel"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($heating_and_fuel as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherHeatingFuelRes d-none">
                  <label class="fw-bold">Heating and Fuel:</label>
                  <input type="text" name="otherHeatingFuel"  class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="34">
              <div class="form-group">
                <label class="fw-bold">Approximate Room Dimensions:</label>
                <input type="text" name="approximate_room_dimensions" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" >
              </div>
              @php
                $room_types = [
                    ['name' => 'Additional Bedroom', 'target' => ''],
                    ['name' => 'Balcony/Porch/Lanai', 'target' => ''],
                    ['name' => 'Basement', 'target' => ''],
                    ['name' => 'Bathroom 1', 'target' => ''],
                    ['name' => 'Bathroom 2', 'target' => ''],
                    ['name' => 'Bathroom 3', 'target' => ''],
                    ['name' => 'Bathroom 4', 'target' => ''],
                    ['name' => 'Bathroom 5', 'target' => ''],
                    ['name' => 'Bedroom 1', 'target' => ''],
                    ['name' => 'Bedroom 2', 'target' => ''],
                    ['name' => 'Bedroom 3', 'target' => ''],
                    ['name' => 'Bedroom 4', 'target' => ''],
                    ['name' => 'Bedroom 5', 'target' => ''],
                    ['name' => 'Bonus Room', 'target' => ''],
                    ['name' => 'Breezeway', 'target' => ''],
                    ['name' => 'Dining Room', 'target' => ''],
                    ['name' => 'Family Room', 'target' => ''],
                    ['name' => 'Florida Room', 'target' => ''],
                    ['name' => 'Foyer', 'target' => ''],
                    ['name' => 'Game Room', 'target' => ''],
                    ['name' => 'Great Room', 'target' => ''],
                    ['name' => 'Gym', 'target' => ''],
                    ['name' => 'Inside Utility', 'target' => ''],
                    ['name' => 'Interior In-Law Suite', 'target' => ''],
                    ['name' => 'Kitchen', 'target' => ''],
                    ['name' => 'Laundry', 'target' => ''],
                    ['name' => 'Library', 'target' => ''],
                    ['name' => 'Living Room', 'target' => ''],
                    ['name' => 'Loft', 'target' => ''],
                    ['name' => 'Master Bathroom', 'target' => ''],
                    ['name' => 'Master Bedroom', 'target' => ''],
                    ['name' => 'Media Room', 'target' => ''],
                    ['name' => 'Office', 'target' => ''],
                    ['name' => 'Sauna', 'target' => ''],
                    ['name' => 'Studio', 'target' => ''],
                    ['name' => 'Study/Den', 'target' => ''],
                    ['name' => 'Workshop', 'target' => ''],
                ];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Room Type:</label>
                <select class="grid-picker" name="room_type[]" id="room_type" style="justify-content: flex-start;"
                  multiple>
                  <option value="">Select</option>
                  @foreach ($room_types as $room_type)
                    <option value="{{ $room_type['name'] }}" data-target="{{ $room_type['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $room_type['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
                $room_levels = [['name' => 'Upper', 'target' => ''], ['name' => 'Basement', 'target' => ''], ['name' => 'First', 'target' => ''], ['name' => 'Second', 'target' => ''], ['name' => 'Third', 'target' => '']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Room Level:</label>
                <select class="grid-picker" name="room_level[]" id="room_level" style="justify-content: flex-start;"
                  multiple>
                  <option value="">Select</option>
                  @foreach ($room_levels as $room_level)
                    <option value="{{ $room_level['name'] }}" data-target="{{ $room_level['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $room_level['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
                $bed_room_closest_types = [['name' => 'Built-in Closet', 'target' => ''], ['name' => 'No Closet', 'target' => ''], ['name' => 'Walk-in Closet', 'target' => '']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Bedroom Closet Type:</label>
                <select class="grid-picker" name="bed_room_closest_type[]" id="bed_room_closest_type"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($bed_room_closest_types as $bed_room_closest_type)
                    <option value="{{ $bed_room_closest_type['name'] }}"
                      data-target="{{ $bed_room_closest_type['target'] }}" class="card flex-row"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                      {{ $bed_room_closest_type['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
                $room_primary_floor_coverings = [
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
                    ['name' => 'Marble', 'target' => ''],
                    ['name' => 'Parquet', 'target' => ''],
                    ['name' => 'Porcelain Tile', 'target' => ''],
                    ['name' => 'Quarry Tile', 'target' => ''],
                    ['name' => 'Reclaimed Wood', 'target' => ''],
                    ['name' => 'Recycled/Composite Flooring', 'target' => ''],
                    ['name' => 'Slate', 'target' => ''],
                    ['name' => 'Terrazzo', 'target' => ''],
                    ['name' => 'Tile', 'target' => ''],
                    ['name' => 'Travertine', 'target' => ''],
                    ['name' => 'Other', 'target' => ''],
                ];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Room Primary Floor Covering:</label>
                <select class="grid-picker" name="room_primary_floor_covering[]" id="room_primary_floor_covering"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($room_primary_floor_coverings as $room_primary_floor_covering)
                    <option value="{{ $room_primary_floor_covering['name'] }}"
                      data-target="{{ $room_primary_floor_covering['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $room_primary_floor_covering['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
                $room_features = [
                    ['name' => 'Bar', 'target' => ''],
                    ['name' => 'Bath with Spa/Hydro Massage Tub', 'target' => ''],
                    ['name' => 'Bath With Whirlpoo', 'target' => ''],
                    ['name' => 'Bidet', 'target' => ''],
                    ['name' => 'Breakfast Bar', 'target' => ''],
                    ['name' => 'Built-In Shelving', 'target' => ''],
                    ['name' => 'Built-In Shower Bench', 'target' => ''],
                    ['name' => 'Ceiling Fan(s)', 'target' => ''],
                    ['name' => 'Claw Foot Tub', 'target' => ''],
                    ['name' => 'Closet Pantry', 'target' => ''],
                    ['name' => 'Cooking Island', 'target' => ''],
                    ['name' => 'Desk Built-In ', 'target' => ''],
                    ['name' => 'Dual Sinks', 'target' => ''],
                    ['name' => 'En Suite Bathroom ', 'target' => ''],
                    ['name' => 'Exhaust Fan', 'target' => ''],
                    ['name' => 'Garden Bath ', 'target' => ''],
                    ['name' => 'Granite Counters', 'target' => ''],
                    ['name' => 'Handicap Accessible', 'target' => ''],
                    ['name' => 'Heated Floors', 'target' => ''],
                    ['name' => 'Island', 'target' => ''],
                    ['name' => 'Jack and Jill Bathroom', 'target' => ''],
                    ['name' => 'Linen Closet Bath', 'target' => ''],
                    ['name' => 'Makeup/Vanity Space', 'target' => ''],
                    ['name' => 'Multiple Shower Heads', 'target' => ''],
                    ['name' => 'Other- Specify in Remarks', 'target' => ''],
                    ['name' => 'Pantry', 'target' => ''],
                    ['name' => 'Rain Shower Head', 'target' => ''],
                    ['name' => 'Sauna', 'target' => ''],
                    ['name' => 'Shower- No Tub', 'target' => ''],
                    ['name' => 'Single Vanity', 'target' => ''],
                    ['name' => 'Sink-Pedestal ', 'target' => ''],
                    ['name' => 'Split Vanities ', 'target' => ''],
                    ['name' => 'Steam Shower', 'target' => ''],
                    ['name' => 'Stone Counters', 'target' => ''],
                    ['name' => 'Sunken Shower', 'target' => ''],
                    ['name' => 'Tall Countertops ', 'target' => ''],
                    ['name' => 'Tile Counters', 'target' => ''],
                    ['name' => 'Tub with Separate Shower Stall ', 'target' => ''],
                    ['name' => 'Tub with Shower', 'target' => ''],
                    ['name' => 'Urinal', 'target' => ''],
                    ['name' => 'Walk-In Pantry', 'target' => ''],
                    ['name' => 'Walk-In Tub', 'target' => ''],
                    ['name' => 'Water Closet/Priv Toliet', 'target' => ''],
                    ['name' => 'Window/Skylight in Bath', 'target' => ''],
                    ['name' => 'Other', 'target' => ''],
                ];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Room Features:</label>
                <select class="grid-picker" name="room_feature[]" id="room_feature"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($room_features as $room_feature)
                    @php
                      if ($room_feature['name'] == 'Other') {
                          $target = '.custom_room_features';
                      } else {
                          $target = '';
                      }
                    @endphp
                    <option value="{{ $room_feature['name'] }}" data-target="{{ $target }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $room_feature['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group custom_room_features">
                <label class="fw-bold">Room Features:</label>
                <input type="text" name="custom_room_features" id="custom_room_features"
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
            </div>
            <div class="wizard-step" data-step="35">
  
              <div class="form-group">
                <label class="fw-bold">Water Access:</label>
                <select class="grid-picker" name="has_water_access" id="has_water_access"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.water_access_residentail';
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
              @php
                $water_access = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
              @endphp
              <div class="form-group water_access_residentail">
                <select class="grid-picker" name="water_access[]" id="water_access"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($water_access as $water_access1)
                    <option value="{{ $water_access1['name'] }}" data-target="{{ $water_access1['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $water_access1['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group ">
                <label class="fw-bold">Water View:</label>
                <select class="grid-picker" name="has_water_view" id="has_water_view"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.water_view_residential_and_income';
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
              @php
                $water_views = [['name' => 'Bay/Harbor - Full', 'target' => ''], ['name' => 'Bay/Harbor - Partial', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Canal', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Gulf/Ocean - Full', 'target' => ''], ['name' => 'Gulf/Ocean - Partial', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'None', 'target' => '']];
              @endphp
              <div class="form-group water_view_residential_and_income d-none">
                <select class="grid-picker" name="water_view[]" id="water_view" style="justify-content: flex-start;"
                  multiple>
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
              <div class="form-group">
                <label class="fw-bold">Water Extras:</label>
                <select class="grid-picker" name="has_water_extra" id="has_water_extra"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.water_extras_residential_and_income';
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
                    ['name' => 'Dock w/o Water Supply', 'target' => ''],
                    ['name' => 'Dock w/Water Supply', 'target' => ''],
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
              <div class="form-group water_extras_residential_and_income d-none ">
                <select class="grid-picker" name="water_extras[]" id="water_extras"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($water_extras as $water_extra)
                    <option value="{{ $water_extra['name'] }}" data-target="{{ $water_extra['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $water_extra['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">Water Frontage:</label>
                <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.water_frontage_residential_and_income';
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
              @php
                $water_frontage = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
              @endphp
              <div class="form-group water_frontage_residential_and_income d-none">
                <select class="grid-picker" name="water_frontage[]" id="water_frontage"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($water_frontage as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
              @php
                $viewOpt = [['name' => 'Yes', 'target' => '.viewYesRes','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']];
              @endphp
                <label class="fw-bold">View:</label>
                <select class="grid-picker" name="viewOpt" id="view" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($viewOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="{{$item['icon']}}" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group viewYesRes d-none">
                  @php
                    $view = [['name' => 'City', 'target' => ''], ['name' => 'Garden', 'target' => ''], ['name' => 'Golf Course', 'target' => ''], ['name' => 'Greenbelt', 'target' => ''], ['name' => 'Mountain(s)', 'target' => ''], ['name' => 'Park', 'target' => ''], ['name' => 'Pool', 'target' => ''], ['name' => 'Tennis Court', 'target' => ''], ['name' => 'Trees/Woods', 'target' => ''], ['name' => 'Water', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherViewRes']];
                  @endphp
                <label class="fw-bold">View:</label>
                <select class="grid-picker" name="view[]" id="view" style="justify-content: flex-start;"
                  multiple>
                  <option value="">Select</option>
                  @foreach ($view as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherViewRes d-none">
                  <label class="fw-bold">View:</label>
                  <input type="text" name="otherView" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="36">
              @php
                $ownerships = [['name' => 'Co-Op', 'target' => ''], ['name' => 'Condominium', 'target' => ''], ['name' => 'Fee Simple', 'target' => ''],['name' => 'Fractional', 'target' => ''], ['name' => 'Other', 'target' => '']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Ownership:</label>
                <select class="grid-picker" name="ownership" id="ownership" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($ownerships as $item)
                    @php
                      if ($item['name'] == 'Other') {
                          $target = '.otherOwnershipRes';
                      } else {
                          $target = '';
                      }
                    @endphp
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group otherOwnershipRes d-none">
                <label class="fw-bold">Ownership:</label>
                <input type="text" name="otherOwnership" id="custom_ownership" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
            </div>
            <div class="wizard-step" data-step="37">
              @php
                $occupant_types = [['name' => 'Owner', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tenant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Occupant Type:</label>
                <select class="grid-picker" name="occupant_type" id="occupant_type"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($occupant_types as $item)
                    @php
                      if ($item['name'] == 'Tenant') {
                          $target = '.tenant_conditions_residential_and_income';
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
              <div class="row tenant_conditions_residential_and_income">
                <div class="row for_residential_only">
                  <div class="form-group">
                    @php
                    $existingLease = [['name' => 'Yes, Existing Lease', 'target' => '.existingLeaseyes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Yes, Month to Month', 'target' => '.monthToMonth', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Existing Lease or Tenant:</label>
                    <select class="grid-picker" name="exiting_lease_or_tenant" id="exiting_lease_or_tenant"
                      style="justify-content: flex-start;">
                      <option value="">Select</option>
                      @foreach ($existingLease as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group existingLeaseyes d-none">
                      <label for="address" class="fw-bold">End Date of Lease:</label>
                      <input type="date" name="end_of_lease_date" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                    </div>
                    <div class="form-group monthToMonth d-none">
                      <label for="address" class="fw-bold">What is the required notice period for the tenant to vacate the property?</label>
                      <input type="text" name="monthToMonth" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Monthly Rental Amount:</label>
                    <input type="number" name="monthly_rental_ammount" id="monthly_rental_ammount" 
                      class="form-control has-icon" data-icon="fa-solid fa-dollar">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Days Notice to Tenant if not Renewing:</label>
                    <input type="text" name="days_notice_to_terminate" id="days_notice_to_terminate"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                  
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="38">
              <div class="form-group">
                <label class="fw-bold">Can the property be leased?  </label>
                <select class="grid-picker" name="has_leasing" id="has_leasing"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.lease_restriction';
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
              <div class="row lease_restriction">
                <div class="form-group ">
                  <label class="fw-bold">Lease Restrictions:</label>
                  <select class="grid-picker" name="has_lease_restriction" id="has_lease_restriction"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '';
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
                <div class="form-group">
                  <label class="fw-bold">Association Approval Required:</label>
                  <select class="grid-picker" name="association_approval_required" id="association_approval_required"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '';
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
                @php
                  $minimum_lease_period = [['name' => '1-7 Days', 'target' => ''], ['name' => '1 Week', 'target' => ''], ['name' => '2 Week', 'target' => ''], ['name' => '1 Month', 'target' => ''], ['name' => '2 Month', 'target' => ''], ['name' => '3 Month', 'target' => ''], ['name' => '4 Month', 'target' => ''], ['name' => '5 Month', 'target' => ''], ['name' => '6 Month', 'target' => ''], ['name' => '7 Month', 'target' => ''], ['name' => '8-12 Month', 'target' => ''], ['name' => '1-2 Years', 'target' => ''], ['name' => '2+ Years', 'target' => ''], ['name' => 'No Minimum', 'target' => ''], ['name' => 'No Rent', 'target' => '']];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">Minimum Lease Period:</label>
                  <select class="grid-picker" name="minimum_lease_period" id="minimum_lease_period"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($minimum_lease_period as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Maximum Times Per Year:</label>
                  <input type="text" name="minimum_lease_per_year"
                    class="form-control has-icon" data-icon="fa-solid fa-calendar-days">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Years of Ownership Prior to Leasing Required:</label>
                  <select class="grid-picker" name="years_of_ownership" id="years_of_ownership"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '';
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
                <div class="form-group">
                  <label class="fw-bold">Number of Ownership Years Prior to Leasing:</label>
                  <input type="number" name="number_of_ownership_prior_lease" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="39">
              <div class="form-group">
                @php
                  $propsOpt = [
                    ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.hoas_residential_and_income'],
                    ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
                  ];
                @endphp
                <label class="fw-bold">Does the property have an HOA, condo association, master
                  association, and/or community fee?</label>
                <select class="grid-picker" name="has_hoa" id="has_hoa" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($propsOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
                $hoa_fee_requirenments = [['name' => 'None', 'target' => ''], ['name' => 'Optional', 'target' => ''], ['name' => ' Required', 'target' => '']];
              @endphp
              <div class="row hoas_residential_and_income d-none">
                @php
                  $community_features = [
                      ['name' => 'Airport/Runway', 'target' => ''],
                      ['name' => 'Association Recreation - Lease', 'target' => ''],
                      ['name' => ' Association Recreation - Owned', 'target' => ''],
                      ['name' => 'Buyer Approval Required', 'target' => ''],
                      ['name' => 'Clubhouse', 'target' => ''],
                      ['name' => 'Community Boat Ramp', 'target' => ''],
                      ['name' => 'Community Mailbox', 'target' => ''],
                      ['name' => 'Deed Restrictions', 'target' => ''],
                      ['name' => 'Fishing', 'target' => ''],
                      ['name' => 'Fitness Center', 'target' => ''],
                      ['name' => 'Gated Community - Guard', 'target' => ''],
                      ['name' => 'Gated Community - No Guard', 'target' => ''],
                      ['name' => 'Golf Carts OK', 'target' => ''],
                      ['name' => 'Golf Community', 'target' => ''],
                      ['name' => 'Handicap Modified', 'target' => ''],
                      ['name' => 'Horse Stable(s)', 'target' => ''],
                      ['name' => 'Horses Allowed', 'target' => ''],
                      ['name' => 'Irrigation-Reclaimed Water', 'target' => ''],
                      ['name' => 'Lake', 'target' => ''],
                      ['name' => 'No Truck/RV/Motorcycle Parking', 'target' => ''],
                      ['name' => 'Park', 'target' => ''],
                      ['name' => 'Playground', 'target' => ''],
                      ['name' => 'Pool', 'target' => ''],
                      ['name' => 'Public Boat Ramp', 'target' => ''],
                      ['name' => 'Racquetball', 'target' => ''],
                      ['name' => 'Restaurant', 'target' => ''],
                      ['name' => 'Sidewalk', 'target' => ''],
                      ['name' => 'Special Community Restrictions', 'target' => ''],
                      ['name' => 'Stream Seasonal', 'target' => ''],
                      ['name' => 'Tennis Courts', 'target' => ''],
                      ['name' => ' Water Access', 'target' => ''],
                      ['name' => 'Waterfront', 'target' => ''],
                      ['name' => 'Wheelchair Access', 'target' => ''],
                      ['name' => 'None', 'target' => ''],
                  ];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">Community Features:</label>
                  <select class="grid-picker" name="community_feature[]" id="community_feature"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($community_features as $community_feature)
                      <option value="{{ $community_feature['name'] }}"
                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                        data-target="{{ $community_feature['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $community_feature['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                @php
                  $association_amenities = [
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
                      ['name' => 'Marina', 'target' => ''],
                      ['name' => 'Optional Additional Fees', 'target' => ''],
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
                      ['name' => 'Other', 'target' => '.otherAssocAmenitiesRes'],
                  ];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">Association Amenities:</label>
                  <select class="grid-picker" name="association_amenitie[]" id="association_amenitie"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($association_amenities as $association_amenitie)
                      <option value="{{ $association_amenitie['name'] }}"
                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                        data-target="{{ $association_amenitie['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $association_amenitie['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherAssocAmenitiesRes d-none">
                    <label class="fw-bold">Association Amenities: </label>
                    <input type="text" name="otherAssocAmenities" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                </div>
                @php
                  $fee_includes = [
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
                      ['name' => 'None', 'target' => ''],
                      ['name' => 'Pest Control', 'target' => ''],
                      ['name' => 'Pool Maintenance', 'target' => ''],
                      ['name' => 'Private Road', 'target' => ''],
                      ['name' => 'Recreational Facilities', 'target' => ''],
                      ['name' => 'Security', 'target' => ''],
                      ['name' => 'Sewer', 'target' => ''],
                      ['name' => 'Trash', 'target' => ''],
                      ['name' => 'Water', 'target' => ''],
                      ['name' => 'Other', 'target' => '.otherFeeIncludeRes'],
                  ];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">Fee Includes:</label>
                  <select class="grid-picker" name="fee_include[]" id="fee_include"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($fee_includes as $fee_include)
                      <option value="{{ $fee_include['name'] }}"
                        data-icon="<i class='fa-regular fa-circle-check'></i>"
                        data-target="{{ $fee_include['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $fee_include['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherFeeIncludeRes d-none">
                    <label class="fw-bold">Fee Includes:</label>
                    <input type="text" name="otherFeeInclude" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Amenities with Additional Fees:</label>
                  <input type="number" name="amenities_with_additional_fees"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                        style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group anual_cdd_fee_residential_and_income d-none">
                  <label class="fw-bold">Annual CDD Fee:</label>
                  <input type="number" name="annual_cdd_fee" id="annual_cdd_fee" class="form-control has-icon"
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
                        style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group land_lease_fee_residential_and_income d-none">
                  <label class="fw-bold">Annual Land Lease Fee:</label>
                  <input type="text" name="land_lease_fee" id="land_lease_fee" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar ">
                </div>
              </div>
                  <div class="form-group">
                      @php
                        $hoaFeeRequirements = [
                          ['name'=>'None','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>''],
                          ['name'=>'Optional','icon'=>'<i class="fa-regular fa-circle-question"></i>','target'=>''],
                          ['name'=>'Required','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'']
                        ];
                      @endphp
                    <label class="fw-bold">Hoa Fee Requirement:</label>
                      <select name="hoaFeeRequirements" class="grid-picker" style="justify-content: flex-start;">
                        <option value="">Select</option>
                        @foreach ($hoaFeeRequirements as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                          {{ $item['name'] }}
                        </option>
                        @endforeach
                      </select>
                  </div>
  
                  <div class="form-group">
                    <label class="fw-bold">HOA Fee:</label>
                    <input type="text" name="hoaFeeAmount"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" >
                  </div>
  
                  <div class="form-group">
                    @php
                      $paymentSchedules = [
                              ['name'=>'Annually','target'=>''],
                              ['name'=>'Monthly','target'=>''],
                              ['name'=>'Quarterly','target'=>''],
                              ['name'=>'Semi-Annually','target'=>'']
                            ];
                    @endphp
                    <label class="fw-bold">HOA Payment Schedule:</label>
                    <select name="paymentSchedules" id="hoaPaymentSchedule" class="grid-picker">
                      <option value="">Select</option>
                      @foreach ($paymentSchedules as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
  
                  <div class="form-group">
                    <label class="fw-bold">Condo Fee:</label>
                    <input type="text" name="condoFeeAmount" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" >
                  </div>
  
                  <div class="form-group">
                    @php
                      $condoPayOpt = [
                              ['name'=>'Annually','target'=>''],
                              ['name'=>'Monthly','target'=>''],
                              ['name'=>'Quarterly','target'=>''],
                              ['name'=>'Semi-Annually','target'=>'']
                            ];
                    @endphp
                    <label class="fw-bold">Condo Payment Schedule:</label>
                    <select name="condoPay" id="condoPaymentSchedule" class="grid-picker">
                      <option value="">Select</option>
                      @foreach ($condoPayOpt as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
  
                  <div class="form-group">
                      @php
                        $masterAssocOpt = [
                          ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.masterAssocYesRes'],
                          ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                              ];
                      @endphp
                    <label class="fw-bold">Master Association:</label>
                    <select name="masterAssoc" id="masterAssociation" class="grid-picker">
                      <option value="">Select</option>
                      @foreach ($masterAssocOpt as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
  
                  <div class="form-group masterAssocYesRes d-none">
                    <div class="form-group">
                      <label class="fw-bold">Master Association Fee:</label>
                      <input type="text" name="masterAssociationFeeAmount" id="masterAssociationFeeAmount" class="form-control has-icon " data-icon="fa-solid fa-dollar-sign" >
                    </div>
  
                    <div class="form-group">
                      @php
                          $assocScheduleOpt = [
                                  ['name'=>'Annually','target'=>''],
                                  ['name'=>'Monthly','target'=>''],
                                  ['name'=>'Quarterly','target'=>''],
                                  ['name'=>'Semi-Annually','target'=>'']
                                ];
                        @endphp
                      <label class="fw-bold">Master Association Fee Schedule:</label>
                      <select name="assocSchedule" id="masterAssociationFeeSchedule" class="grid-picker">
                        <option value="">Select</option>
                        @foreach ($assocScheduleOpt as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>
  
                    <div class="form-group">
                      <label class="fw-bold">Master Association Name:</label>
                      <input type="text" name="masterAssociationName" id="masterAssociationName" class="form-control has-icon" data-icon="fa-solid fa-user">
                    </div>
  
                    <div class="form-group">
                      <label class="fw-bold">Master Association Contact Phone:</label>
                      <input type="text" name="masterAssociationContactPhone" class="form-control has-icon" data-icon="fa-solid fa-phone">
                    </div>
                  </div>
  
                  <div class="form-group">
                    @php
                      $additioalFeeOpt = [
                        ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.additionalFeeYesRes'],
                        ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                            ];
                    @endphp
                    <label class="fw-bold">Are there any additional fees?</label>
                    <select name="additionalFees" id="additionalFees" class="grid-picker">
                      <option value="">Select</option>
                      @foreach ($additioalFeeOpt as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group additionalFeeYesRes d-none">
                    <div class="form-group">
                      <label class="fw-bold">What is the fee for?</label>
                      <input type="text" name="additionalFeeReason" id="additionalFeeReason" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Other Fee:</label>
                      <input type="text" name="otherFeeAmount" id="otherFeeAmount" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                    </div>
                  </div>
                    <div class="form-group">
                      @php
                        $otherFeeOpt = [
                                ['name'=>'Annually','target'=>''],
                                ['name'=>'Monthly','target'=>''],
                                ['name'=>'Quarterly','target'=>''],
                                ['name'=>'Semi-Annually','target'=>'']
                              ];
                      @endphp
                      <label class="fw-bold">Other Fee Schedule:</label>
                      <select name="otherFee" id="otherFeeSchedule" class="grid-picker">
                        <option value="">Select</option>
                        @foreach ($otherFeeOpt as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>
  
                  <div class="form-group">
                    <label class="fw-bold">Association/Manager Contact Name:</label>
                    <input type="text" name="associationManagerContactName"  class="form-control has-icon" data-icon="fa-solid fa-user">
                  </div>
  
                  <div class="form-group">
                    <label class="fw-bold">Association/Manager Contact Email:</label>
                    <input type="email" name="associationManagerContactEmail"  class="form-control has-icon" data-icon="fa-solid fa-envelope">
                  </div>
  
                  <div class="form-group">
                    <label class="fw-bold">Association/Manager Contact Phone:</label>
                    <input type="text" name="associationManagerContactPhone" class="form-control has-icon" data-icon="fa-solid fa-phone">
                  </div>
  
                  <div class="form-group">
                    <label class="fw-bold">Association/Manager Contact Website Address:</label>
                    <input type="text" name="associationManagerContactWebsite"  class="form-control has-icon" data-icon="fa-regular fa-window-restore">
                  </div>
  
                  <div class="form-group">
                    @php
                      $olderPersonOpt = [
                        ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>''],
                        ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                            ];
                    @endphp
                    <label class="fw-bold">Housing for Older Persons:</label>
                    <select name="olderPersons" id="housingForOlderPersons" class="grid-picker">
                      <option value="">Select</option>
                      @foreach ($olderPersonOpt as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
            </div>
            <div class="wizard-step" data-step="40">
              <div class="form-group">
                <label class="fw-bold"> Description:</label>
                <textarea name="description" id="description" class="form-control" cols="30" rows="10" required></textarea>
              </div>
  
              <div class="form-group">
                <label class="fw-bold">Keywords:<small class="small">(Add keywords of the
                    best property features) </small></label>
                <input type="text" name="keywords" id="keywords" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">Legal Disclamers:</label>
                <input type="text" name="disclamer" id="keywords" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">Driving Directions:</label>
                <input type="text" name="driving_directions" id="keywords" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                    @php
                      $sellerCompRes = [['name' => 'Yes', 'target' => '','target'=>'.sellerComYesRes','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],['name' => 'Negotiable', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-check"></i>']];
                    @endphp
                  <label class="fw-bold">Is the seller offering compensation for a buyer’s agent?
                  </label>
                  <select class="grid-picker" name="looking_other_property" id="looking_other_property"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($sellerCompRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33% - 10px);" data-icon="{{ $item['icon'] }}">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group sellerComYesRes d-none">
                    <label class="fw-bold">Buyer’s Agent Compensation:</label>
                    <input type="text" name="compensation_amount" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar">
                  </div>
                </div>
            </div>
            <div class="wizard-step" data-step="41">
              <div class="form-group">
                <label class="fw-bold">Is the Seller actively seeking to purchase another property?
                </label>
                <select class="grid-picker" name="looking_other_property" id="looking_other_property"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.link_residential_and_income';
                      } else {
                          $target = '';
                      }
                    @endphp
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(50% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group link_residential_and_income">
                <label class="fw-bold">Link to the listing on the Bid Your Offer platform:</label>
                <input type="url" name="listing_link" id="listing_link" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-link">
              </div>
            </div>
            <div class="wizard-step" data-step="42">
              <h4> Title Company Information:</h4>
              <div class="form-group">
                <label class="fw-bold">Name:</label>
                <input type="text" name="title_company_name" id="title_company_name" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-user">
              </div>
              <div class="form-group">
                <label class="fw-bold">Address:</label>
                <input type="text" name="title_company_address" id="title_company_address" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-location-dot">
              </div>
              <div class="form-group">
                <label class="fw-bold">Phone Number:</label>
                <input type="text" name="title_company_phone" id="title_company_phone" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-phone">
              </div>
  
              <div class="form-group">
                <label class="fw-bold">Email:</label>
                <input type="text" name="title_company_email" id="titl_company_email" placeholder=""
                data-icon="fa-solid fa-envelope" class="form-control has-icon">
              </div>
          
            </div>
            <div class="wizard-step" data-step="43">
              <h4>Agent info:</h4>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">First Name:</label>
                  <input type="text" name="agent_first_name" id="first_name" placeholder=""
                    value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                    data-icon="fa-solid fa-user">
                </div>
                <div class="form-group col-md-6">
                  <label class="fw-bold">Last Name:</label>
                  <input type="text" name="agent_last_name" id="last_name" placeholder=""
                    value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                    data-icon="fa-solid fa-user">
                </div>
              </div>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">Phone Number:</label>
                  <input type="text" name="agent_phone" id="agent_phone" placeholder=""
                    value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                    data-icon="fa-solid fa-phone">
                </div>
                <div class="form-group col-md-6 ">
                  <label class="fw-bold">Email:</label>
                  <input type="text" name="agent_email" id="agent_email" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-envelope"
                    value="{{ Auth::user()->email }}">
                </div>
              </div>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">Brokerage:</label>
                  <input type="text" name="agent_brokerage" id="agent_brokerage" placeholder=""
                    value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                    data-icon="fa-solid fa-handshake">
                </div>
                <div class="form-group col-md-6">
                  <label class="fw-bold">Real Estate License #:</label>
                  <input type="text" name="agent_license_no" id="agent_license_no" placeholder=""
                    value="{{ optional(Auth::user())->license_no }}" class="form-control has-icon"
                    data-icon="fa-solid fa-id-card">
                </div>
              </div>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">NAR Member ID (NRDS ID):</label>
                  <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                    value="{{ optional(Auth::user())->mls_id }}">
                </div>
                <div class="form-group  col-md-6">
                  <label class="fw-bold">Listed By: Real Estate Agent:</label>
                  <input type="text" name="realEstateAgent" id="titl_company_email"
                    data-icon="fa-solid fa-user" class="form-control has-icon">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="44">
              <div class="form-group">
                <label class="fw-bold">3D Tour:</label>
                <input type="url" name="three_d_tour" id="three_d_tour" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">Floor Plan:</label>
                <input type="file" name="floor_plan" id="floor_plan" class="form-control">
              </div>
              <div class="form-group">
                <label class="fw-bold">Addendums/Disclosures:</label>
                <input type="file" name="disclosures[]" id="upload_file" placeholder="" class="form-control"
                multiple>
              </div>
              <span class="resFields">
                <div class="row">
                  <div class="col-6">
                    <div class="videoBox form-group">
                        <label class="fw-bold mt-1">Property Video:</label>
                        <div class="video bgImg"></div>
                        <div class="videoDiv">
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
                          <label class="fw-bold">Property Photos:</label>
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
              <div class="form-group">
                <label class="fw-bold">Bathrooms:</label>
                <select class="grid-picker" name="bathrooms" id="bathrooms" style="">
                  <option value="">Select</option>
                  @foreach ($bathrooms as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(20% - 10px);"
                      data-icon='<i class="fa-solid fa-bath"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group custom_bathrooms d-none">
                <label class="fw-bold">Bathrooms:</label>
                <input type="text" name="custom_bathrooms" id="custom_bathrooms" class="form-control has-icon"
                  data-icon="fa-solid fa-bath">
              </div>
            </div>
            <div class="wizard-step" data-step="46">
              <div class="row">
                <div class="form-group col-md-4">
                  <label class="fw-bold">Unit Type</label>
                  <input type="text" name="unit_type" id="unit_type" class="form-control has-icon"
                    placeholder="Unit Type" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Sqt Ft Heated</label>
                  <input type="number" name="sqt_ft_heated" id="sqt_ft_heated" placeholder="Sqt Ft Heated"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Number of Units</label>
                  <input type="number" name="number_of_units" id="number_of_units" placeholder="Number of Units"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Expected Rent</label>
                  <input type="number" name="expected_rent" id="expected_rent" placeholder="Expected Rent"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Garage Spaces</label>
                  <input type="number" name="garage_spaces_unit" id="garage_spaces_unit"
                    placeholder="Garage Spaces" class="form-control has-icon" data-icon="fa-solid fa-warehouse">
                </div>
                <div class="form-group ">
                  <label class="fw-bold">Garage Attribute:</label>
                  <select class="grid-picker" name="garage_attribute" id="garage_attribute"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">Unit Type of Description:</label>
                  <textarea name="unit_type_of_description" id="unit_type_of_description" class="form-control" cols="30"
                    rows="10"></textarea>
                </div>
  
                <div class="form-group col-md-4">
                  <label class="fw-bold">Annual Gross Income</label>
                  <input type="number" name="annual_gross_income" id="garage_attribute"
                    placeholder="Annual Gross Income" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Total Monthly Rent</label>
                  <input type="number" name="total_monthly_rent" id="garage_attribute"
                    placeholder="Total Monthly Rent" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Total Monthly Expenses</label>
                  <input type="number" name="total_monthly_expenses" id="garage_attribute"
                    placeholder="Total Monthly Expenses" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Length of Lease</label>
                  <input type="text" name="lease_terms" id="garage_attribute" placeholder="Length of Lease"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Annual Net Income</label>
                  <input type="number" name="annual_net_income" id="garage_attribute"
                    placeholder="Annual Net Income" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Est Annual Market Income</label>
                  <input type="text" name="est_annual_market_income" id="garage_attribute"
                    placeholder="Est Annual Market Income" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Annual Expenses</label>
                  <input type="text" name="annual_expenses" id="garage_attribute"
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
                        style="width:calc(33.3% - 10px);">
                        {{ $terms_of_lease['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherTermLeaseVacant d-none">
                    <label class="fw-bold">Terms of Lease:</label>
                    <input type="text" name="otherTermLease" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                        style="width:calc(33.3% - 10px);">
                        {{ $tenant_pay['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherTenantPayVacant d-none">
                    <label class="fw-bold">Tenant Pays:</label>
                    <input type="text" name="otherTenantPay" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                        data-icon='<i class="fa-regular fa-circle-check"></i>' style="width:calc(33.3% - 10px);">
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
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Total Number of Units:</label>
                  <input type="number" name="total_number_of_units" placeholder="Total Number of Units"
                    id="total_number_of_units" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
  
              </div>
            </div>
            <div class="wizard-step" data-step="47">
              <div class="row ">
                <div class="form-group">
                  <label class="fw-bold" for="heated_sqft">Heated Sqft:</label>
                  <input type="number" name="heated_sqft" placeholder="Heated sqft" id="heated_sqft"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="sqft">Total Sqft:</label>
                  <input type="number" name="total_sqft" placeholder="Heated sqft" id="heated_sqft"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined">
                </div>
                @php
                  $heated_sources = [['name' => 'Appraisal', 'target' => ''], ['name' => 'Building', 'target' => ''], ['name' => 'Measured', 'target' => ''], ['name' => 'Owner Provided', 'target' => ''], ['name' => 'Public Records', 'target' => '']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Sqft Heated Source:</label>
                  <select class="grid-picker" name="heated_source" id="heated_sources"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($heated_sources as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-check-circle"></i>' class="card flex-column"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                @php
                  $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Total Acreage:</label>
                  <select class="grid-picker" name="total_aceage" id="lot_size"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($lot_sizes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-solid fa-ruler-combined"></i>' class="card flex-column"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="lot_size">Lot size:</label>
                  <input type="number" name="lot_size" placeholder="Lot Size" id="lot_size"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined"
                   >
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="year_built">Year Built:</label>
                  <input type="text" name="year_built" id="year_built"
                    class="form-control has-icon " data-icon="fa-solid fa-calendar-day">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="48">
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
                    ['name' => 'Touchless Faucet', 'target' => ''],
                    ['name' => 'None', 'target' => ''],
                    ['name' => 'Other', 'target' => '.otherAppliancesIncome'],
                ];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Appliances:</label>
                <select class="grid-picker" name="appliances[]" id="appliances" style="justify-content: flex-start;"
                  multiple>
                  <option value="">Select</option>
                  @foreach ($appliances as $item)
                    <option value="{{ $item['name'] }}" data-icon='<i class="fa-regular fa-check-circle"></i>'
                      data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherAppliancesIncome d-none">
                  <label class="fw-bold">Appliances:</label>
                  <input type="text" name="otherAppliances" id="flood_zone_code" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="49">
              <div class="form-group ">
                @php
                  $furnishingsIncome = [['name' => 'Yes', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],['name' => 'Optional', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-question"></i>']];
                @endphp
                <label class="fw-bold">Are there any furnishings included in the purchase?</label>
                <select class="grid-picker" name="has_furnishing" id="has_water_view"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($furnishingsIncome as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="row has_furnishings_commercial_and_business d-none">
  
                <div class="form-group">
                  <div class="form-group">
                    <label class="fw-bold">What furnishings are included in the purchase?</label>
                    <input type="text" name="furnishings_include" id="flood_zone_code" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                            $target = '.has_additional_fees_commercial_and_business';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group has_additional_fees_commercial_and_business">
                  <div class="form-group">
                    <label class="fw-bold">How much is the listed furniture?</label>
                    <input type="text" name="listed_furniture_price" id="listed_furniture_price" placeholder=""
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="50">
              <h4>Business Listing Only</h4>
              @php
                $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Real Estate Include:</label>
                <select class="grid-picker" name="has_real_estate_include" id="bathrooms" style="">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">Business Name:</label>
                <input type="text" name="business_name" id="custom_bathrooms" class="form-control has-icon"
                  data-icon="fa-regular fa-circle-check">
              </div>
              <div class="form-group">
                <label class="fw-bold">Year Established:</label>
                <input type="text" name="year_established" id="year_established" class="form-control has-icon"
                  data-icon="fa-regular fa-circle-check">
              </div>
              @php
                $licenses = [['name' => 'Beer/Wine', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Liquor', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'None', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Off Site', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'On Site', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Licenses:</label>
                <select class="grid-picker" name="licenses" id="bathrooms" style="">
                  <option value="">Select</option>
                  @foreach ($licenses as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="51">
              <div class="form-group">
                <label class="fw-bold">Number of Floors within the Property:</label>
                <input type="text" name="number_of_buildings" id="number_of_buildings" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-hotel">
              </div>
              <div class="form-group">
                <label class="fw-bold">Floor Number:</label>
                <input type="number" name="floors_in_unit" id="floors_in_unit" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-building">
              </div>
              <div class="form-group">
                <label class="fw-bold">Total Number of Floors in the Entire Building:</label>
                <input type="number" name="total_floors" id="total_floors" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-building">
              </div>
              <div class="form-group">
                <label class="fw-bold">Building Elevator:</label>
                <select class="grid-picker" name="building_elevator" id="building_elevator"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(25% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="52">
              @php
                $floor_coverings = [
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
              <div class="form-group ">
                <label class="fw-bold">Floor Covering:</label>
                <select class="grid-picker" name="floor_covering[]" id="floor_covering"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($floor_coverings as $floor_covering)
                    <option value="{{ $floor_covering['name'] }}" data-target="{{ $floor_covering['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $floor_covering['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="53">
              @php
                $front_exposures = [['name' => 'North', 'target' => ''], ['name' => 'East', 'target' => ''], ['name' => 'South', 'target' => ''], ['name' => 'West', 'target' => ''], ['name' => 'Southeast', 'target' => ''], ['name' => 'Northeast', 'target' => ''], ['name' => 'Southwest', 'target' => ''], ['name' => 'Northwest', 'target' => '']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Front Exposure:</label>
                <select class="grid-picker" name="front_exposure" id="front_exposure"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($front_exposures as $front_exposure)
                    <option value="{{ $front_exposure['name'] }}" data-target="{{ $front_exposure['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $front_exposure['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="54">
              @php
                $foundations = [['name' => 'Basement', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick/Mortar', 'target' => ''], ['name' => 'Concrete Perimeter', 'target' => ''], ['name' => 'Crawlspace', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pillar/Post/Pier', 'target' => ''], ['name' => 'Slab', 'target' => ''], ['name' => 'Stem Wall', 'target' => ''], ['name' => 'Stilt/On Piling', 'target' => '']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Foundation:</label>
                <select class="grid-picker" name="foundation[]" id="foundation" style="justify-content: flex-start;"
                  multiple>
                  <option value="">Select</option>
                  @foreach ($foundations as $foundation)
                    <option value="{{ $foundation['name'] }}" data-target="{{ $foundation['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $foundation['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="55">
              @php
                $exterior_constructions = [['name' => 'Asbestos', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Cedar', 'target' => ''], ['name' => 'Cement Siding', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'HardiPlank Type', 'target' => ''], ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''], ['name' => 'Log', 'target' => ''], ['name' => 'Metal Frame', 'target' => ''], ['name' => 'Metal Siding', 'target' => ''], ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''], ['name' => 'Stone', 'target' => ''], ['name' => 'Stucco', 'target' => ''], ['name' => 'Tilt up Walls', 'target' => ''], ['name' => 'Vinyl Siding', 'target' => ''], ['name' => 'Wood Frame', 'target' => ''], ['name' => 'Wood Frame (FSC)', 'target' => ''], ['name' => 'Wood Siding ', 'target' => ''], ['name' => 'Other ', 'target' => '.otherExteriorCon']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Exterior Construction:</label>
                <select class="grid-picker" name="exterior_construction[]" id="exterior_construction"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($exterior_constructions as $exterior_construction)
                    <option value="{{ $exterior_construction['name'] }}"
                      data-target="{{ $exterior_construction['target'] }}" class="card flex-row"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                      {{ $exterior_construction['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherExteriorCon d-none">
                  <label class="fw-bold">Exterior Construction:</label>
                  <input type="text" name="otherExterior" id="max_pet_weight" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="56">
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
                    ['name' => 'Other', 'target' => '.otherFeatureCommercial'],
                ];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Exterior Features:</label>
                <select class="grid-picker" name="exterior_feature[]" id="exterior_feature"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($exterior_features as $exterior_feature)
                    <option value="{{ $exterior_feature['name'] }}" data-target="{{ $exterior_feature['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $exterior_feature['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherFeatureCommercial d-none">
                  <label class="fw-bold">Exterior Features:</label>
                  <input type="text" name="otherExterior" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="57">
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
                    ['name' => 'Historic District', 'target' => ''],
                    ['name' => 'In City Limits', 'target' => ''],
                    ['name' => 'Industrial Condo', 'target' => ''],
                    ['name' => 'Industrial Park', 'target' => ''],
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
                      style="width:calc(33.3% - 10px);">
                      {{ $lot_feature['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherLotFeatureCommercial d-none">
                  <label class="fw-bold">Lot Features:</label>
                  <input type="text" name="otherLotFeature" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="58">
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
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherBuildingCommercial d-none">
                  <label class="fw-bold">Building Features:</label>
                  <input type="text" name="otherBuilding" placeholder="" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="59">
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
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherAdjoiningCommercial d-none">
                  <label class="fw-bold">Adjoining Property:</label>
                  <input type="text" name="otherAdjoining" placeholder="" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="60">
              @php
                $roofs = [['name' => 'Built-Up', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Membrane', 'target' => ''], ['name' => 'Metal', 'target' => ''], ['name' => 'Roof Over', 'target' => ''], ['name' => 'Shake', 'target' => ''], ['name' => 'Shingle', 'target' => ''], ['name' => 'Slate', 'target' => ''], ['name' => 'Tile', 'target' => ''], ['name' => 'Cement', 'target' => ''], ['name' => 'Other', 'target' => '.otherRoofCommercial']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Roof:</label>
                <select class="grid-picker" name="roof[]" id="roof" style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($roofs as $roof)
                    <option value="{{ $roof['name'] }}" data-target="{{ $roof['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $roof['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherRoofCommercial d-none" >
                  <label class="fw-bold">Roof:</label>
                  <input type="text" name="otherRoof" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="61">
              @php
                $road_surface_types = [['name' => 'Asphalt', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Chip And Seal', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Dirt', 'target' => ''], ['name' => 'Gravel', 'target' => ''], ['name' => 'Limerock', 'target' => ''], ['name' => 'Paved', 'target' => ''], ['name' => 'Unimproved', 'target' => ''], ['name' => 'Other', 'target' => '.otherSurfaceCommercial']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Road Surface Type:</label>
                <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($road_surface_types as $road_surface_type)
                    <option value="{{ $road_surface_type['name'] }}"
                      data-target="{{ $road_surface_type['target'] }}" class="card flex-row"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                      {{ $road_surface_type['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherSurfaceCommercial d-none" >
                  <label class="fw-bold">Road Surface Type:</label>
                  <input type="text" name="otherRoof" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="62">
              @php
                $road_frontage = [['name' => 'Access Road', 'target' => ''], ['name' => 'Alley', 'target' => ''], ['name' => 'Business District', 'target' => ''], ['name' => 'City Street', 'target' => ''], ['name' => 'County Road', 'target' => ''], ['name' => 'Divided Highway', 'target' => ''], ['name' => 'Easement', 'target' => ''], ['name' => 'Highway', 'target' => ''], ['name' => 'Interchange', 'target' => ''], ['name' => 'Interstate', 'target' => ''], ['name' => 'Main Thoroughfare', 'target' => ''], ['name' => 'Private Road', 'target' => ''], ['name' => 'Rail', 'target' => ''], ['name' => 'State Road', 'target' => ''], ['name' => 'Turn Lanes', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherFrontageCommercial']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Road Frontage:</label>
                <select class="grid-picker" name="road_frontage[]" id="road_frontage"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($road_frontage as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherFrontageCommercial d-none" >
                  <label class="fw-bold">Road Frontage:</label>
                  <input type="text" name="otherFrontage" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="63">
              @php
                $garage_parking_feature = [['name' => '1 to 5 Spaces', 'target' => ''], ['name' => '6 to 12 Spaces', 'target' => ''], ['name' => '13 to 18 Spaces', 'target' => ''], ['name' => '19 to 30 Spaces', 'target' => ''], ['name' => 'Airplane Hangar', 'target' => ''], ['name' => 'Common', 'target' => ''], ['name' => 'Curb Parking', 'target' => ''], ['name' => 'Deeded', 'target' => ''], ['name' => 'Electric Vehicle Charging Station(s)', 'target' => ''], ['name' => 'Ground Level', 'target' => ''], ['name' => 'Lighted', 'target' => ''], ['name' => 'Over 30 Spaces', 'target' => ''], ['name' => 'Secured', 'target' => ''], ['name' => 'Under Building', 'target' => ''], ['name' => 'Underground', 'target' => ''], ['name' => 'Valet', 'target' => ''],['name' => 'RV Parking', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherGarageFeatureCommercial']];
              @endphp
  
              <div class="form-group ">
                <label class="fw-bold">Garage/Parking Features:</label>
                <select class="grid-picker" name="garage_parking_feature[]" id="garage_parking_feature"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($garage_parking_feature as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherGarageFeatureCommercial d-none" >
                  <label class="fw-bold">Garage/Parking Features:</label>
                  <input type="text" name="otherGarageFeature" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>  
            <div class="wizard-step" data-step="64">
              <h4>Tax Info</h4>
              <div class="form-group">
                <label class="fw-bold">Tax ID (Parcel Number):</label>
                <input type="text" name="tax_id" id="tax_id" class="form-control has-icon"
                  data-icon="fa-regular fa-id-card">
              </div>
              <div class="form-group">
                <label class="fw-bold">Tax Year:</label>
                <input type="text" name="tax_year" id="tax_year" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined" >
              </div>
              <div class="form-group">
                <label class="fw-bold">Taxes (Annual Amount):</label>
                <input type="text" name="taxes_annual_amount" id="taxes_annual_ammount"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar" "
                  >
              </div>
              <div class="form-group">
                <label class="fw-bold">Total Number of Parcels:</label>
                <input type="text" name="total_number_of_parcels" id="total_number_of_parcels"
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">Additional Tax ID's:</label>
                <input type="text" name="additional_tax_id" id="additional_tax_id" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">Zoning:</label>
                <input type="text" name="zoning" id="zoning" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">Legal Description:</label>
                <input type="text" name="legal_description" id="legal_description" class="form-control has-icon" data-icon="fa-solid fa-tag">
              </div>
              <div class="form-group ">
                @php
                  $additionalParcelsCommercial = [['name' => 'Yes', 'target' => '.parcelYesCommercial', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                @endphp
                <label class="fw-bold">Additional Parcels:</label>
                <select class="grid-picker" name="additionalParcels" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($additionalParcelsCommercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group parcelYesCommercial d-none">
                  <label class="fw-bold">Additional Parcels: </label>
                  <input type="text" name="parcelYes" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
              <div class="form-group ">
                <label class="fw-bold">Homestead:</label>
                <select class="grid-picker" name="has_homestead" id="sewer"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="65">
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
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group floodZoonYesCommercial">
                <label class="fw-bold">Flood Zone Code:</label>
                <input type="text" name="flood_zone_code"
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
            </div>
            <div class="wizard-step" data-step="66">
              @php
                $utilitiseCommercial = [
                  ['name' => 'BB/HS Internet Available', 'target' => ''],
                  ['name' => 'Cable Available', 'target' => ''],
                  ['name' => 'Cable Connected', 'target' => ''],
                  ['name' => 'Electric - Multiple Meters', 'target' => ''],
                  ['name' => 'Electricity Available', 'target' => ''],
                  ['name' => 'Electricity Connected', 'target' => ''],
                  ['name' => 'Emergency Power', 'target' => ''],
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
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherUtilitiseCommercial d-none">
                  <label class="fw-bold">Utilities:</label>
                  <input type="text" name="otherUtilitise" id="legal_description" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
              @php
  
                $waterCommercial = [['name' => 'Canal/Lake For Irrigation', 'target' => ''], ['name' => 'Private', 'target' => ''], ['name' => 'Public', 'target' => ''], ['name' => 'Well', 'target' => ''],['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherWaterCommercial']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Water:</label>
                <select class="grid-picker" name="water" id="water12" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($waterCommercial as $water)
                    <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $water['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherWaterCommercial d-none">
                  <label class="fw-bold">Water:</label>
                  <input type="text" name="otherWater" id="legal_description" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
  
              @php
                $sewerCommercial = [['name' => 'Aerobic Septic', 'target' => ''],['name' => 'PEP-Holding Tank', 'target' => ''], ['name' => 'Private Sewer', 'target' => ''], ['name' => 'Public Sewer', 'target' => ''], ['name' => 'Septic Needed', 'target' => ''], ['name' => 'Septic Tank', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherSewerCommercial']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Sewer:</label>
                <select class="grid-picker" name="sewer" id="sewer" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($sewerCommercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherSewerCommercial d-none">
                  <label class="fw-bold">Sewer:</label>
                  <input type="text" name="otherSewer"  class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="67">
              @php
                $air_conditioning = [['name' => 'Central Air', 'target' => ''], ['name' => 'Humidity Control', 'target' => ''], ['name' => 'Mini-Split Unit(s)', 'target' => ''],  ['name' => 'Wall/Window Unit(s)', 'target' => ''], ['name' => 'Zoned', 'target' => ''],['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherAirConditionCommercial']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Air Conditioning:</label>
                <select class="grid-picker" name="air_conditioning" id="air_conditioning"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($air_conditioning as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherAirConditionCommercial d-none">
                  <label class="fw-bold">Air Conditioning:</label>
                  <input type="text" name="otherAirCondition"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
              @php
                $heating_and_fuel = [['name' => 'Baseboard', 'target' => ''], ['name' => 'Central', 'target' => ''], ['name' => 'Electric', 'target' => ''], ['name' => 'Exhaust Fans', 'target' => ''], ['name' => 'Heat Pump', 'target' => ''], ['name' => 'Heat Recovery Unit', 'target' => ''], ['name' => 'Natural Gas', 'target' => ''], ['name' => 'Oil', 'target' => ''], ['name' => 'Partial', 'target' => ''], ['name' => 'Propane', 'target' => ''], ['name' => 'Radiant Ceiling', 'target' => ''], ['name' => 'Reverse Cycle', 'target' => ''], ['name' => 'Solar', 'target' => ''], ['name' => 'Space Heater', 'target' => ''], ['name' => 'Wall Furnace', 'target' => ''], ['name' => 'Wall Units / Window Unit', 'target' => ''], ['name' => 'Zoned', 'target' => ''],['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherHeatingFuelCommercial']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Heating and Fuel:</label>
                <select class="grid-picker" name="heating_and_fuel" id="heating_and_fuel"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($heating_and_fuel as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherHeatingFuelCommercial d-none">
                  <label class="fw-bold">Heating and Fuel:</label>
                  <input type="text" name="otherHeatingFuel"  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="68">
              <div class="form-group ">
                <label class="fw-bold">Water View:</label>
                <select class="grid-picker" name="has_water_view" id="has_water_view"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.water_view_commercial_and_business';
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
              @php
                $water_views = [['name' => 'Bay/Harbor - Full', 'target' => ''], ['name' => 'Bay/Harbor - Partial', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Canal', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Gulf/Ocean - Full', 'target' => ''], ['name' => 'Gulf/Ocean - Partial', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'None', 'target' => '']];
              @endphp
              <div class="form-group water_view_commercial_and_business d-none">
                <select class="grid-picker" name="water_view[]" id="water_view" style="justify-content: flex-start;"
                  multiple>
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
              <div class="form-group">
                <label class="fw-bold">Water Extras:</label>
                <select class="grid-picker" name="has_water_extra" id="has_water_extra"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.water_extras_commercial_and_business';
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
                    ['name' => 'Dock w/o Water Supply', 'target' => ''],
                    ['name' => 'Dock w/Water Supply', 'target' => ''],
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
              <div class="form-group water_extras_commercial_and_business d-none ">
                <select class="grid-picker" name="water_extras[]" id="water_extras"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($water_extras as $water_extra)
                    <option value="{{ $water_extra['name'] }}" data-target="{{ $water_extra['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $water_extra['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">Water Frontage:</label>
                <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.water_frontage_commercial_and_business';
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
              @php
                $water_frontage = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'Canal Front and Riparian Rights', 'target' => '']];
  
              @endphp
              <div class="form-group water_frontage_commercial_and_business d-none">
                <select class="grid-picker" name="water_frontage[]" id="water_frontage"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($water_frontage as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">Water Access:</label>
                <select class="grid-picker" name="has_water_access" id="has_water_access"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.water_access_commercial_and_business';
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
              @php
                $water_access = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
              @endphp
              <div class="form-group water_access_commercial_and_business">
                <select class="grid-picker" name="water_access[]" id="water_access"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($water_access as $water_access1)
                    <option value="{{ $water_access1['name'] }}" data-target="{{ $water_access1['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $water_access1['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="69">
              @php
                $ownerships = [['name' => 'Condominium', 'target' => ''], ['name' => 'Corporation', 'target' => ''], ['name' => 'Franchise', 'target' => ''], ['name' => 'Partnership', 'target' => ''], ['name' => 'Sole', 'target' => ''], ['name' => 'Proprietor', 'target' => ''], ['name' => 'Fractional', 'target' => ''], ['name' => 'Other', 'target' => '']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Ownership:</label>
                <select class="grid-picker" name="ownership_co_op" id="ownership"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($ownerships as $item)
                    @php
                      if ($item['name'] == 'Other') {
                          $target = '.otherOwnershipCommercial';
                      } else {
                          $target = '';
                      }
                    @endphp
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group otherOwnershipCommercial">
                <label class="fw-bold">Ownership:</label>
                <input type="text" name="otherOwnership" id="custom_ownership" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
            </div>
            <div class="wizard-step" data-step="70">
              @php
                $occupantCommercial = [['name' => 'Owner', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tenant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Occupant Type:</label>
                <select class="grid-picker" name="occupant_type" id="occupant_type"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($occupantCommercial as $item)
                    @php
                      if ($item['name'] == 'Tenant') {
                          $target = '.occupantCommercial';
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
              <div class="row occupantCommercial">
                <div class="row for_residential_only">
                  <div class="form-group">
                    @php
                    $existingLease = [['name' => 'Yes, Existing Lease', 'target' => '.existingLeaseyesCommercial', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Yes, Month to Month', 'target' => '.monthToMonthCommercial', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Existing Lease or Tenant:</label>
                    <select class="grid-picker" name="exiting_lease_or_tenant" id="exiting_lease_or_tenant"
                      style="justify-content: flex-start;">
                      <option value="">Select</option>
                      @foreach ($existingLease as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group existingLeaseyesCommercial d-none">
                      <label for="address" class="fw-bold">End Date of Lease:</label>
                      <input type="date" name="end_of_lease_date" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                    </div>
                    <div class="form-group monthToMonthCommercial d-none">
                      <label for="address" class="fw-bold">What is the required notice period for the tenant to vacate the property?</label>
                      <input type="text" name="monthToMonth" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Monthly Rental Amount:</label>
                    <input type="number" name="monthly_rental_ammount" id="monthly_rental_ammount" 
                      class="form-control has-icon" data-icon="fa-solid fa-dollar">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Days Notice to Tenant if not Renewing:</label>
                    <input type="text" name="days_notice_to_terminate" id="days_notice_to_terminate"
                       class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                  
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="71">
              <h4>Financial Information:</h4>
              @php
                $acutual_or_projected = [['name' => 'Actual', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Projected', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Operating Expenses:</label>
                <input type="text" name="operating_expenses" id="operating_expenses"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar ">
              </div>
              <div class="form-group">
                <label class="fw-bold">Net Operating Income:</label>
                <input type="text" name="net_operating_income" id="net_operating_income"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar">
              </div>
              <div class="form-group">
                <label class="fw-bold">Net Operating Income Type:</label>
                <select class="grid-picker" name="net_operating_income_type" id="net_operating_income_type"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($acutual_or_projected as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '';
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
              <div class="form-group">
                <label class="fw-bold">Annual Expense:</label>
                <input type="text" name="annual_expenses" id="annual_expenses" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar" >
              </div>
              <div class="form-group">
                <label class="fw-bold">Annual TTL Schedule Income:</label>
                <input type="text" name="annual_ttl_schedule_income" id="annual_ttl_schedule_income"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar">
              </div>
              <div class="form-group">
                <label class="fw-bold">Annual Income Type:</label>
                <select class="grid-picker" name="annual_income_type" id="annual_income_type"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($acutual_or_projected as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '';
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
              @php
                $saleIncludeCommercial = [['name' => 'Building(s) and Land', 'target' => ''], ['name' => 'Furniture/Fixtures', 'target' => ''], ['name' => 'Leases', 'target' => ''], ['name' => 'Other', 'target' => '.otherSaleCommercial']];
              @endphp
              <div class="row ">
                <div class="form-group">
                  <label class="fw-bold">Sale Includes:</label>
                  <select class="grid-picker" name="saleInclude[]" style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($saleIncludeCommercial as $item)
                      <option value="{{ $item['name'] }}"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'
                        data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherSaleCommercial d-none">
                    <label class="fw-bold">Sale Includes:</label>
                    <input type="text" name="otherSale" class="form-control has-icon"
                      data-icon="fa-solid fa-ruler-combined">
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">Number of Tenants:</label>
                <input type="text" name="number_of_tenants" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
            </div>
            <div class="wizard-step" data-step="72">
  
              @php
                $space_type = [['name' => 'New', 'target' => ''], ['name' => 'Re Let', 'target' => ''], ['name' => 'Sub Let', 'target' => '']];
              @endphp
              <h4>Space:</h4>
              <div class="form-group">
                <label class="fw-bold">Class of Space:</label>
                <input type="text" name="class_of_space" id="operating_expenses" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="row ">
                <div class="form-group">
                  <label class="fw-bold">Space Type:</label>
                  <select class="grid-picker" name="sale_include" id="sale_include"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($space_type as $space)
                      <option value="{{ $space['name'] }}" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        data-target="{{ $space['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $space['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold"> # of Hotel/Motel Rooms:</label>
                <input type="text" name="number_of_hotel" id="number_of_hotel" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold"># of Conference/Meeting Rooms:</label>
                <input type="text" name="number_of_conference" id="annual_expenses" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold"># of Restrooms:</label>
                <input type="text" name="number_of_restrooms" id="annual_ttl_schedule_income"
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
  
              <div class="form-group">
                <label class="fw-bold"># of Bays(Dock High) :</label>
                <input type="text" name="number_of_bays_high" id="number_of_tenants"
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold"># of Bays(Grade Level):</label>
                <input type="text" name="number_of_bays_level" id="number_of_tenants"
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold"># of Offices:</label>
                <input type="text" name="number_of_offices" id="number_of_tenants" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
            </div>
            <div class="wizard-step" data-step="73">
              <div class="form-group">
                <label class="fw-bold"> Is the property in a condo environment?  </label>
                <select class="grid-picker" name="has_condo_enviornment" id="has_condo_enviornment"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.has_condo';
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
              <div class="row has_condo d-none">
                <div class="form-group ">
                  <label class="fw-bold">Condo Fee:$</label>
                  <input type="text" name="condo_fee" id="condo_fee" class="form-control ">
                </div>
                @php
                  $condo_fee_terms = [['target' => '', 'name' => 'Annual', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Monthly', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Quarterly', 'icon' => 'fa-regular fa-circle-check'], ['target' => '', 'name' => 'Semi Annual ', 'icon' => 'fa-regular fa-circle-check']];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">Condo Fee Term:</label>
                  <select class="grid-picker" name="condo_fee_terms" id="parking_feature_garage"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($condo_fee_terms as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group ">
                  <label class="fw-bold">Association/Manager Name:</label>
                  <input type="text" name="association_manager_contact_name" id="condo_fee"
                    class="form-control has-icon" data-icon="fa-solid fa-user">
                </div>
                <div class="form-group ">
                  <label class="fw-bold">Association/Manager Email:</label>
                  <input type="text" name="association_manager_contact_name" id="condo_fee"
                    class="form-control has-icon" data-icon="fa-solid fa-envelope">
                </div>
                <div class="form-group ">
                  <label class="fw-bold">Association/Manager Phone Number:</label>
                  <input type="text" name="association_manager_contact_name" id="condo_fee"
                    class="form-control has-icon" data-icon="fa-solid fa-phone">
                </div>
                <div class="form-group ">
                  <label class="fw-bold">Association/Manager Website: </label>
                  <input type="text" name="association_manager_contact_name" id="condo_fee"
                    class="form-control has-icon" data-icon="fa-solid fa-link">
                </div>
  
                @php
                  $community_features = [['name' => 'Activity Core/Center', 'target' => ''], ['name' => 'Airport/Runway', 'target' => ''], ['name' => 'Beach Area', 'target' => ''], ['name' => 'Curbs', 'target' => ''], ['name' => 'Expressway', 'target' => ''], ['name' => 'Sidewalk', 'target' => ''], ['name' => 'Stream Seasonal', 'target' => '']];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">Community Features:</label>
                  <select class="grid-picker" name="community_features[]" id="parking_feature_garage"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($community_features as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="74">
              <div class="form-group">
                <label class="fw-bold"> Description:</label>
                <textarea name="description" id="description" class="form-control" cols="30" rows="10"></textarea>
              </div>
              <div class="form-group">
                  @php
                    $sellerCompCommercial = [['name' => 'Yes', 'target' => '','target'=>'.sellerComYesCommercial','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],['name' => 'Negotiable', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-check"></i>']];
                  @endphp
                <label class="fw-bold">Is the seller offering compensation for a buyer’s agent?
                </label>
                <select class="grid-picker" name="looking_other_property" id="looking_other_property"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($sellerCompCommercial as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33% - 10px);" data-icon="{{ $item['icon'] }}">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group sellerComYesCommercial d-none">
                  <label class="fw-bold">Buyer’s Agent Compensation:</label>
                  <input type="text" name="compensation_amount" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">Keywords:<small class="small">(Add keywords of the
                    best property features) </small></label>
                <input type="text" name="keywords" id="keywords" class="form-control has-icon"
                  data-icon="fa-solid fa-tag">
              </div>
              <div class="form-group">
                <label class="fw-bold">Legal Disclamers:</label>
                <input type="text" name="driving_directions" id="keywords" class="form-control has-icon"
                  data-icon="fa-solid fa-tag">
              </div>
              <div class="form-group">
                <label class="fw-bold">Driving Directions:</label>
                <input type="text" name="driving_directions" id="keywords" class="form-control has-icon"
                  data-icon="fa-solid fa-tag">
              </div>
              <div class="form-group single_agent_compensation_amount_commercial_and_business_opportunity">
                <label class="fw-bold">Single Agent Compensation Amount:</label>
                <input type="number" name="compensation_amount" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar">
              </div>
              <div class="form-group non_rep_compensation_amount_commercial_and_business_opportunity ">
                <label class="fw-bold"> Non Rep Compensation Amount:</label>
                <input type="number" name="compensation_amount" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar">
              </div>
              <div class="form-group transaction_amount_commercial_and_business_opportunity">
                <label class="fw-bold">Transaction Broker Compensation Amount:</label>
                <input type="number" name="compensation_amount" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar">
              </div>
            </div>
            <div class="wizard-step" data-step="75">
              <div class="form-group">
                <label class="fw-bold">Is the Seller actively seeking to purchase another property?
                </label>
                <select class="grid-picker" name="looking_other_property" id="looking_other_property"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.link_commercial';
                      } else {
                          $target = '';
                      }
                    @endphp
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(50% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group link_commercial">
                <label class="fw-bold">Link to the listing on the Bid Your Offer platform:</label>
                <input type="url" name="listing_link" id="listing_link" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-link">
              </div>
            </div>
            <div class="wizard-step" data-step="76">
              <h4> Title Company Information:</h4>
              <div class="form-group">
                <label class="fw-bold">Name:</label>
                <input type="text" name="title_company_name" id="title_company_name" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-user">
              </div>
              <div class="form-group">
                <label class="fw-bold">Address:</label>
                <input type="text" name="title_company_address" id="title_company_address" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-location-dot">
              </div>
              <div class="form-group">
                <label class="fw-bold">Phone Number:</label>
                <input type="text" name="title_company_phone" id="title_company_phone" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-phone">
              </div>
  
              <div class="form-group">
                <label class="fw-bold">Email:</label>
                <input type="text" name="title_company_email" id="titl_company_email" placeholder=""
                  value="{{ @$auction->get->title_company_email }}" data-icon="fa-solid fa-envelope"
                  class="form-control has-icon">
              </div>
            </div>
            <div class="wizard-step" data-step="77">
              <h4>Agent info:</h4>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">First Name:</label>
                  <input type="text" name="first_name" id="first_name" placeholder=""
                    value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                    data-icon="fa-solid fa-user">
                </div>
                <div class="form-group col-md-6">
                  <label class="fw-bold">Last Name:</label>
                  <input type="text" name="last_name" id="last_name" placeholder=""
                    value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                    data-icon="fa-solid fa-user">
                </div>
              </div>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">Phone Number:</label>
                  <input type="text" name="agent_phone" id="agent_phone" placeholder=""
                    value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                    data-icon="fa-solid fa-phone">
                </div>
                <div class="form-group col-md-6">
                  <label class="fw-bold">Email:</label>
                  <input type="text" name="agent_email" id="agent_email" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-envelope"
                    value="{{ Auth::user()->email }}">
                </div>
              </div>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">Brokerage:</label>
                  <input type="text" name="agent_brokerage" id="agent_brokerage" placeholder=""
                    value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                    data-icon="fa-solid fa-handshake">
                </div>
                <div class="form-group col-md-6">
                  <label class="fw-bold">Real Estate License #:</label>
                  <input type="text" name="agent_license_no" id="agent_license_no" placeholder=""
                    value="{{ optional(Auth::user())->license_no }}" class="form-control has-icon"
                    data-icon="fa-solid fa-id-card">
                </div>
              </div>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">NAR Member ID (NRDS ID):</label>
                  <input type="number" name="agent_mls_id" id="agent_mls_id" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                    value="{{ optional(Auth::user())->mls_id }}">
                </div>
                <div class="form-group col-md-6">
                  <label class="fw-bold">Listed By: Real Estate Agent:</label>
                  <input type="number" name="agent_mls_id" id="agent_mls_id" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                    value="{{ optional(Auth::user())->mls_id }}">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="78">
              <div class="form-group">
                <label class="fw-bold">3D Tour:</label>
                <input type="url" name="three_d_tour" id="three_d_tour" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-link">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Floor Plan:</label>
                  <input type="file" name="visible_note" id="visible_note" class="form-control">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Lable Addendums/Disclosures:</label>
                  <input type="text" name="lable_visible_upload_file" id="lable_visible_upload_file" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-link" multiple>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Addendums/Disclosures:</label>
                  <input type="file" name="visible_upload_file[]" id="upload_file" placeholder=""
                  class="form-control" multiple>
                </div>
                <span class="commercialFields">
                <div class="row">
                  <div class="col-6">
                    <div class="videoBox form-group">
                        <label class="fw-bold mt-1">Property Video:</label>
                        <div class="video bgImg"></div>
                        <div class="videoDiv">
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
                          <label class="fw-bold">Property Photos:</label>
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
            <div class="wizard-step" data-step="79">
              @php
                $front_exposures1 = [['name' => 'North', 'target' => ''], ['name' => 'East', 'target' => ''], ['name' => 'South', 'target' => ''], ['name' => 'West', 'target' => ''], ['name' => 'Southeast', 'target' => ''], ['name' => 'Northeast', 'target' => ''], ['name' => 'Southwest', 'target' => ''], ['name' => 'Northwest', 'target' => ''], ['name' => 'Undetermined', 'target' => '']];
              @endphp
              <div class="form-group  ">
                <label class="fw-bold">Front Exposure:</label>
                <select class="grid-picker" name="front_exposure" onchange="changeFrontExposure(this.value);"
                  id="front_exposure" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($front_exposures1 as $front_exposure12)
                    <option value="{{ $front_exposure12['name'] }}"
                      data-icon='<i class="fa-regular fa-circle-check"></i>'
                      data-target="{{ $front_exposure12['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $front_exposure12['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="80">
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
                  style="width:calc(33.3% - 10px);">
                  {{ $lot_feature['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherLotFeatureVacant d-none">
                  <label class="fw-bold">Lot Features:</label>
                  <input type="text" name="otherLotFeature" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
              @php
                $current_adjacent_use = [['name' => 'Church', 'target' => ''], ['name' => 'Commercial', 'target' => ''], ['name' => 'Industrial', 'target' => ''], ['name' => 'Mobile Home Park', 'target' => ''], ['name' => 'Multi-Family', 'target' => ''], ['name' => 'Park', 'target' => ''], ['name' => 'Professional Office', 'target' => ''], ['name' => 'Residential', 'target' => ''], ['name' => 'Retail', 'target' => ''], ['name' => 'School', 'target' => ''], ['name' => 'Vacant', 'target' => '']];
              @endphp
             
            </div>
            <div class="wizard-step" data-step="81">
              <div class="form-group">
                <label class="fw-bold">Current Adjacent Use:</label>
                <select class="grid-picker" name="current_adjacent_use[]" id="lot_features"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($current_adjacent_use as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="wizard-step" data-step="82">
              @php
                $road_frontages = [['name' => 'Access Road', 'target' => ''], ['name' => 'Alley', 'target' => ''], ['name' => 'Business District', 'target' => ''], ['name' => 'City Street', 'target' => ''], ['name' => 'County Road', 'target' => ''], ['name' => 'Divided Highway', 'target' => ''], ['name' => 'Easement', 'target' => ''], ['name' => 'Highway', 'target' => ''], ['name' => 'Interchange', 'target' => ''], ['name' => 'Interstate', 'target' => ''], ['name' => 'Main Thoroughfare', 'target' => ''], ['name' => 'Private Road', 'target' => ''], ['name' => 'Rail', 'target' => ''], ['name' => 'State Road', 'target' => ''], ['name' => 'Turn Lanes', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherFrontageVacant']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Road Frontage:</label>
                <select class="grid-picker" onclick="changeRoadFrontage(this.value);" name="road_frontage"
                  id="road_frontage" style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($road_frontages as $item)
                    <option value="{{ $item['name'] }}"
                      data-icon='<i class="fa-regular fa-circle-check"></i>'
                      data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherFrontageVacant d-none">
                  <label class="fw-bold">Road Frontage:</label>
                  <input type="text" name="otherFrontage" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="83">
              @php
                $road_surface_types = [['name' => 'Asphalt', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Chip And Seal', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Dirt', 'target' => ''], ['name' => 'Gravel', 'target' => ''], ['name' => 'Limerock', 'target' => ''], ['name' => 'Paved', 'target' => ''], ['name' => 'Unimproved', 'target' => ''], ['name' => 'Other', 'target' => '.otherSurfaceVacant']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Road Surface Type:</label>
                <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($road_surface_types as $road_surface_type)
                    <option value="{{ $road_surface_type['name'] }}"
                      data-target="{{ $road_surface_type['target'] }}" class="card flex-row"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                      {{ $road_surface_type['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherSurfaceVacant d-none">
                  <label class="fw-bold">Road Surface Type:</label>
                  <input type="text" name="otherSurface" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="84">
              @php
                $utilitiseRes = [
                  ['name' => 'BB/HS Internet Available', 'target' => ''],
                  ['name' => 'Cable Available', 'target' => ''],
                  ['name' => 'Cable Connected', 'target' => ''],
                  ['name' => 'Electric - Multiple Meters', 'target' => ''],
                  ['name' => 'Electricity Available', 'target' => ''],
                  ['name' => 'Electricity Connected', 'target' => ''],
                  ['name' => 'Emergency Power', 'target' => ''],
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
                  ['name' => 'Other', 'target' => '.otherUtilitiseVacant'],
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
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherUtilitiseVacant d-none">
                  <label class="fw-bold">Utilities:</label>
                  <input type="text" name="otherUtilitise" id="legal_description" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
              @php
  
                $waterVacant = [['name' => 'Canal/Lake For Irrigation', 'target' => ''], ['name' => 'Private', 'target' => ''], ['name' => 'Public', 'target' => ''], ['name' => 'Well', 'target' => ''],['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherWaterVacant']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Water:</label>
                <select class="grid-picker" name="water" id="water12" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($waterVacant as $water)
                    <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $water['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherWaterVacant d-none">
                  <label class="fw-bold">Water:</label>
                  <input type="text" name="otherWater" id="legal_description" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
  
              @php
                $sewerVacant = [['name' => 'Aerobic Septic', 'target' => ''],['name' => 'PEP-Holding Tank', 'target' => ''], ['name' => 'Private Sewer', 'target' => ''], ['name' => 'Public Sewer', 'target' => ''], ['name' => 'Septic Needed', 'target' => ''], ['name' => 'Septic Tank', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherSewerVacant']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Sewer:</label>
                <select class="grid-picker" name="sewer" id="sewer" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($sewerVacant as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherSewerVacant d-none">
                  <label class="fw-bold">Sewer:</label>
                  <input type="text" name="otherSewer"  class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="85">
              <h4>Tax Info</h4>
              <div class="form-group">
                <label class="fw-bold">Tax ID (Parcel Number):</label>
                <input type="text" name="tax_id" id="tax_id" class="form-control has-icon"
                  data-icon="fa-regular fa-id-card">
              </div>
              <div class="form-group">
                <label class="fw-bold">Tax Year:</label>
                <input type="text" name="tax_year" id="tax_year" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined" >
              </div>
              <div class="form-group">
                <label class="fw-bold">Taxes (Annual Amount):</label>
                <input type="text" name="taxes_annual_amount" id="taxes_annual_ammount"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar">
              </div>
              <div class="form-group ">
                @php
                $additialParcelVacant = [
                  ['name'=>'Yes','target'=>'.additialParcelVacantYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                  ['name'=>'No','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']
                ];
              @endphp
                <label class="fw-bold">Additional Parcels:</label>
                <select class="grid-picker" name="has_homestead" id="sewer"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($additialParcelVacant as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="{{$item['icon']}}" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group additialParcelVacantYes d-none">
                  <div class="form-group">
                    <label class="fw-bold">Total Number of Parcels:</label>
                    <input type="number" name="total_number_of_parcels" id="total_number_of_parcels"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Additional Tax ID's:</label>
                    <input type="number" name="additional_tax_id" id="additional_tax_id" class="form-control has-icon"
                      data-icon="fa-solid fa-ruler-combined">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Zoning:</label>
                    <input type="text" name="zoning" id="zoning"
                      class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Legal Description:</label>
                    <input type="text" name="legal_description" id="legal_description" class="form-control has-icon"
                      data-icon="fa-solid fa-tag">
                  </div>
                  <div class="form-group ">
                    <label class="fw-bold">Homestead:</label>
                    <select class="grid-picker" name="has_homestead" id="sewer"
                      style="justify-content: flex-start;">
                      <option value="">Select</option>
                      @foreach ($yes_or_nos as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                          style="width:calc(33.3% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="86">
              <div class="form-group">
                <label class="fw-bold">Is the property in a flood zone?</label>
                <select class="grid-picker" name="is_in_flood_zone" id="is_in_flood_zone"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.floodZoneVacant';
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
              <div class="form-group floodZoneVacant d-none">
                <label class="fw-bold">Flood Zone Code:</label>
                <input type="text" name="flood_zone_code" id="flood_zone_code" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
            </div>
            <div class="wizard-step" data-step="87">
              @php
                $total_acreages = [['name' => '0 to less than 14', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ acres', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Total Acreage :</label>
                <select class="grid-picker" name="total_acreage" id="total_acreage"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($total_acreages as $total_acreage)
                    <option value="{{ $total_acreage['name'] }}" data-target="{{ $total_acreage['target'] }}"
                      class="card flex-column" style="width:calc(25% - 10px);"
                      data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                      {{ $total_acreage['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">Lot Dimensions:</label>
                <input type="text" name="lot_dimensions" id="lot_dimensions" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">S</label>
                <input type="text" name="lot_size_square_footage" id="lot_size_square_footage"
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">Front Footage:</label>
                <input type="text" name="front_footage" id="front_footage1" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined" >
              </div>
              <div class="form-group">
                <label class="fw-bold">Lot Size Acres:</label>
                <input type="text" name="lot_size_acres" id="lot_size_acres" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
            </div>
            <div class="wizard-step" data-step="88">
  
              <div class="form-group">
                <label class="fw-bold">Water Access:</label>
                <select class="grid-picker" name="has_water_access" id="has_water_access"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.waterAccessVacant';
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
              @php
                $waterAccessVacant = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
              @endphp
              <div class="form-group waterAccessVacant">
                <select class="grid-picker" name="water_access[]" id="water_access"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($waterAccessVacant as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group ">
                <label class="fw-bold">Water View:</label>
                <select class="grid-picker" name="has_water_view" id="has_water_view"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.waterViewVacant';
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
              @php
                $waterViewVacant = [['name' => 'Bay/Harbor - Full', 'target' => ''], ['name' => 'Bay/Harbor - Partial', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Canal', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Gulf/Ocean - Full', 'target' => ''], ['name' => 'Gulf/Ocean - Partial', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'None', 'target' => '']];
              @endphp
              <div class="form-group waterViewVacant d-none">
                <select class="grid-picker" name="water_view[]" id="water_view" style="justify-content: flex-start;"
                  multiple>
                  <option value="">Select</option>
                  @foreach ($waterViewVacant as $item)
                    <option value="{{ $item['name'] }}" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">Water Extras:</label>
                <select class="grid-picker" name="has_water_extra" id="has_water_extra"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.waterEXtraVacant';
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
              @php
                $waterExtraVacant = [
                    ['name' => 'Assigned Boat Slip', 'target' => ''],
                    ['name' => 'Boat Port', 'target' => ''],
                    ['name' => 'Boat Ramp - Private', 'target' => ''],
                    ['name' => 'Boathouse', 'target' => ''],
                    ['name' => 'Boats - None Allowed', 'target' => ''],
                    ['name' => 'Bridges - Fixed', 'target' => ''],
                    ['name' => 'Bridges - No Fixed Bridges', 'target' => ''],
                    ['name' => 'Davits', 'target' => ''],
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
                    ['name' => 'Dock w/o Water Supply', 'target' => ''],
                    ['name' => 'Dock w/Water Supply', 'target' => ''],
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
              <div class="form-group waterEXtraVacant d-none ">
                <select class="grid-picker" name="water_extras[]" id="water_extras"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($waterExtraVacant as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">Water Frontage:</label>
                <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($yes_or_nos as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '.waterFrontageVacant';
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
              @php
                $waterFrontageVacant = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
              @endphp
              <div class="form-group waterFrontageVacant d-none">
                <select class="grid-picker" name="water_frontage[]" id="water_frontage"
                  style="justify-content: flex-start;" multiple>
                  <option value="">Select</option>
                  @foreach ($waterFrontageVacant as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
              @php
                $viewOptVacant = [['name' => 'Yes', 'target' => '.viewYesVacant','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']];
              @endphp
                <label class="fw-bold">View:</label>
                <select class="grid-picker" name="viewOpt" id="view" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($viewOptVacant as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="{{$item['icon']}}" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group viewYesVacant d-none">
                  @php
                    $viewVacant = [['name' => 'City', 'target' => ''], ['name' => 'Garden', 'target' => ''], ['name' => 'Golf Course', 'target' => ''], ['name' => 'Greenbelt', 'target' => ''], ['name' => 'Mountain(s)', 'target' => ''], ['name' => 'Park', 'target' => ''], ['name' => 'Pool', 'target' => ''], ['name' => 'Tennis Court', 'target' => ''], ['name' => 'Trees/Woods', 'target' => ''], ['name' => 'Water', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherViewVacant']];
                  @endphp
                <label class="fw-bold">View:</label>
                <select class="grid-picker" name="view[]" id="view" style="justify-content: flex-start;"
                  multiple>
                  <option value="">Select</option>
                  @foreach ($viewVacant as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherViewVacant d-none">
                  <label class="fw-bold">View:</label>
                  <input type="text" name="otherView" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="89">
              @php
                $ownerships = [['name' => 'Co-op', 'target' => ''], ['name' => 'Condominium', 'target' => ''], ['name' => 'Fee Simple', 'target' => ''], ['name' => 'Fractional', 'target' => ''], ['name' => 'Other', 'target' => '']];
              @endphp
  
              <div class="form-group">
                <label class="fw-bold">Ownership:</label>
                <select class="grid-picker" name="ownership_co_op" id="ownership"
                  style="justify-content: flex-start;">
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
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group otherOwnershipVacant">
                <label class="fw-bold">Ownership:</label>
                <input type="text" name="otherOwnership" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined" >
              </div>
            </div>
            <div class="wizard-step" data-step="90">
              <div class="form-group">
                @php
                  $propsOptVacant = [
                    ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.hoasVacant'],
                    ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
                  ];
                @endphp
                <label class="fw-bold">Does the property have an HOA, condo association, master
                  association, and/or community fee?</label>
                <select class="grid-picker" name="has_hoa" id="has_hoa" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($propsOptVacant as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
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
                      ['name' => 'Airport/Runway', 'target' => ''],
                      ['name' => 'Association Recreation - Lease', 'target' => ''],
                      ['name' => ' Association Recreation - Owned', 'target' => ''],
                      ['name' => 'Buyer Approval Required', 'target' => ''],
                      ['name' => 'Clubhouse', 'target' => ''],
                      ['name' => 'Community Boat Ramp', 'target' => ''],
                      ['name' => 'Community Mailbox', 'target' => ''],
                      ['name' => 'Deed Restrictions', 'target' => ''],
                      ['name' => 'Fishing', 'target' => ''],
                      ['name' => 'Fitness Center', 'target' => ''],
                      ['name' => 'Gated Community - Guard', 'target' => ''],
                      ['name' => 'Gated Community - No Guard', 'target' => ''],
                      ['name' => 'Golf Carts OK', 'target' => ''],
                      ['name' => 'Golf Community', 'target' => ''],
                      ['name' => 'Handicap Modified', 'target' => ''],
                      ['name' => 'Horse Stable(s)', 'target' => ''],
                      ['name' => 'Horses Allowed', 'target' => ''],
                      ['name' => 'Irrigation-Reclaimed Water', 'target' => ''],
                      ['name' => 'Lake', 'target' => ''],
                      ['name' => 'No Truck/RV/Motorcycle Parking', 'target' => ''],
                      ['name' => 'Park', 'target' => ''],
                      ['name' => 'Playground', 'target' => ''],
                      ['name' => 'Pool', 'target' => ''],
                      ['name' => 'Public Boat Ramp', 'target' => ''],
                      ['name' => 'Racquetball', 'target' => ''],
                      ['name' => 'Restaurant', 'target' => ''],
                      ['name' => 'Sidewalk', 'target' => ''],
                      ['name' => 'Special Community Restrictions', 'target' => ''],
                      ['name' => 'Stream Seasonal', 'target' => ''],
                      ['name' => 'Tennis Courts', 'target' => ''],
                      ['name' => ' Water Access', 'target' => ''],
                      ['name' => 'Waterfront', 'target' => ''],
                      ['name' => 'Wheelchair Access', 'target' => ''],
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
                        style="width:calc(33.3% - 10px);">
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
                      ['name' => 'Marina', 'target' => ''],
                      ['name' => 'Optional Additional Fees', 'target' => ''],
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
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherAssocAmenitiesVacant d-none">
                    <label class="fw-bold">Association Amenities: </label>
                    <input type="text" name="otherAssocAmenities" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                      ['name' => 'None', 'target' => ''],
                      ['name' => 'Pest Control', 'target' => ''],
                      ['name' => 'Pool Maintenance', 'target' => ''],
                      ['name' => 'Private Road', 'target' => ''],
                      ['name' => 'Recreational Facilities', 'target' => ''],
                      ['name' => 'Security', 'target' => ''],
                      ['name' => 'Sewer', 'target' => ''],
                      ['name' => 'Trash', 'target' => ''],
                      ['name' => 'Water', 'target' => ''],
                      ['name' => 'Other', 'target' => '.otherFeeIncludeVacant'],
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
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherFeeIncludeVacant d-none">
                    <label class="fw-bold">Fee Includes:</label>
                    <input type="text" name="otherFeeInclude" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Amenities with Additional Fees:</label>
                  <input type="number" name="amenities_with_additional_fees"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group">
                  @php
                    $ccdOptVacant = [
                      ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.ccdVacant'],
                      ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
                    ];
                  @endphp
                  <label class="fw-bold">CDD:</label>
                  <select class="grid-picker" name="has_cdd" id="has_cdd" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($ccdOptVacant as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group ccdVacant d-none">
                  <label class="fw-bold">Annual CDD Fee:</label>
                  <input type="number" name="annual_cdd_fee" id="annual_cdd_fee" class="form-control has-icon"
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
                  <select class="grid-picker" name="has_land_lease" id="has_hoa"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($landLeaseOptVacant as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group landLeaseVacant d-none">
                  <label class="fw-bold">Annual Land Lease Fee:</label>
                  <input type="text" name="land_lease_fee" id="land_lease_fee" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar ">
                </div>
              </div>
                  <div class="form-group">
                      @php
                        $hoaFeeRequirementsVacant = [
                          ['name'=>'None','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>''],
                          ['name'=>'Optional','icon'=>'<i class="fa-regular fa-circle-question"></i>','target'=>''],
                          ['name'=>'Required','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'']
                        ];
                      @endphp
                    <label class="fw-bold">Hoa Fee Requirement:</label>
                      <select name="hoaFeeRequirements" class="grid-picker" style="justify-content: flex-start;">
                        <option value="">Select</option>
                        @foreach ($hoaFeeRequirementsVacant as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                          {{ $item['name'] }}
                        </option>
                        @endforeach
                      </select>
                  </div>
  
                  <div class="form-group">
                    <label class="fw-bold">HOA Fee:</label>
                    <input type="text" name="hoaFeeAmount"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" >
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
                    <select name="paymentSchedules" id="hoaPaymentSchedule" class="grid-picker">
                      <option value="">Select</option>
                      @foreach ($paymentSchedulesVacant as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
  
                  <div class="form-group">
                    <label class="fw-bold">Condo Fee:</label>
                    <input type="text" name="condoFeeAmount" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" >
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
                    <select name="condoPay" id="condoPaymentSchedule" class="grid-picker">
                      <option value="">Select</option>
                      @foreach ($condoPayOptVacant as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
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
                          style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
  
                  <div class="form-group masterAssocYesVacant d-none">
                    <div class="form-group">
                      <label class="fw-bold">Master Association Fee:</label>
                      <input type="text" name="masterAssociationFeeAmount" id="masterAssociationFeeAmount" class="form-control has-icon " data-icon="fa-solid fa-dollar-sign" >
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
                      <select name="assocSchedule" id="masterAssociationFeeSchedule" class="grid-picker">
                        <option value="">Select</option>
                        @foreach ($assocScheduleOptVacant as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>
  
                    <div class="form-group">
                      <label class="fw-bold">Master Association Name:</label>
                      <input type="text" name="masterAssociationName" id="masterAssociationName" class="form-control has-icon" data-icon="fa-solid fa-user">
                    </div>
  
                    <div class="form-group">
                      <label class="fw-bold">Master Association Contact Phone:</label>
                      <input type="text" name="masterAssociationContactPhone" class="form-control has-icon" data-icon="fa-solid fa-phone">
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
                    <select name="additionalFees" id="additionalFees" class="grid-picker">
                      <option value="">Select</option>
                      @foreach ($additioalFeeOptVacant as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group additionalFeeYesVacant d-none">
                    <div class="form-group">
                      <label class="fw-bold">What is the fee for?</label>
                      <input type="text" name="additionalFeeReason" id="additionalFeeReason" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Other Fee:</label>
                      <input type="text" name="otherFeeAmount" id="otherFeeAmount" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
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
                            style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>
  
                  <div class="form-group">
                    <label class="fw-bold">Association/Manager Contact Name:</label>
                    <input type="text" name="associationManagerContactName"  class="form-control has-icon" data-icon="fa-solid fa-user">
                  </div>
  
                  <div class="form-group">
                    <label class="fw-bold">Association/Manager Contact Email:</label>
                    <input type="email" name="associationManagerContactEmail"  class="form-control has-icon" data-icon="fa-solid fa-envelope">
                  </div>
  
                  <div class="form-group">
                    <label class="fw-bold">Association/Manager Contact Phone:</label>
                    <input type="text" name="associationManagerContactPhone" class="form-control has-icon" data-icon="fa-solid fa-phone">
                  </div>
  
                  <div class="form-group">
                    <label class="fw-bold">Association/Manager Contact Website Address:</label>
                    <input type="text" name="associationManagerContactWebsite"  class="form-control has-icon" data-icon="fa-regular fa-window-restore">
                  </div>
  
                  <div class="form-group">
                    @php
                      $olderPersonOptVacant = [
                        ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>''],
                        ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                            ];
                    @endphp
                    <label class="fw-bold">Housing for Older Persons:</label>
                    <select name="olderPersons" id="housingForOlderPersons" class="grid-picker">
                      <option value="">Select</option>
                      @foreach ($olderPersonOptVacant as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon="{{ $item['icon'] }}">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
            </div>
            <div class="wizard-step" data-step="91">
              <div class="form-group">
                <label class="fw-bold"> Description:</label>
                <textarea name="description" id="description" class="form-control" cols="30" rows="10" required></textarea>
              </div>
  
              <div class="form-group">
                <label class="fw-bold">Keywords:<small class="small">(Add keywords of the
                    best property features) </small></label>
                <input type="text" name="keywords" id="keywords" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">Legal Disclamers:</label>
                <input type="text" name="driving_directions" id="keywords" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
              </div>
              <div class="form-group">
                <label class="fw-bold">Driving Directions:</label>
                <input type="text" name="driving_directions" id="keywords" class="form-control has-icon"
                  data-icon="fa-solid fa-ruler-combined">
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
                        style="width:calc(33% - 10px);" data-icon="{{ $item['icon'] }}">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group sellerComYesVacant d-none">
                    <label class="fw-bold">Buyer’s Agent Compensation:</label>
                    <input type="text" name="compensation_amount" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar">
                  </div>
                </div>
            </div>
            <div class="wizard-step" data-step="92">
              <div class="form-group">
                <label class="fw-bold">Is the Seller actively seeking to purchase another property?
                </label>
                <select class="grid-picker" name="looking_other_property" id="looking_other_property"
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
                      style="width:calc(50% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group anotherPropVacant">
                <label class="fw-bold">Link to the listing on the Bid Your Offer platform:</label>
                <input type="url" name="listing_link" id="listing_link" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-link">
              </div>
            </div>
            <div class="wizard-step" data-step="93">
              <h4> Title Company Information:</h4>
              <div class="form-group">
                <label class="fw-bold">Name:</label>
                <input type="text" name="title_company_name" id="title_company_name" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-user">
              </div>
              <div class="form-group">
                <label class="fw-bold">Address:</label>
                <input type="text" name="title_company_address" id="title_company_address" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-location-dot">
              </div>
              <div class="form-group">
                <label class="fw-bold">Phone Number:</label>
                <input type="text" name="title_company_phone" id="title_company_phone" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-phone">
              </div>
  
              <div class="form-group">
                <label class="fw-bold">Email:</label>
                <input type="text" name="title_company_email" id="titl_company_email" placeholder=""
                data-icon="fa-solid fa-envelope" class="form-control has-icon">
              </div>
          
            </div>
            <div class="wizard-step" data-step="94">
              <h4>Agent info:</h4>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">First Name:</label>
                  <input type="text" name="agent_first_name" id="first_name" placeholder=""
                    value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                    data-icon="fa-solid fa-user">
                </div>
                <div class="form-group col-md-6">
                  <label class="fw-bold">Last Name:</label>
                  <input type="text" name="agent_last_name" id="last_name" placeholder=""
                    value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                    data-icon="fa-solid fa-user">
                </div>
              </div>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">Phone Number:</label>
                  <input type="text" name="agent_phone" id="agent_phone" placeholder=""
                    value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                    data-icon="fa-solid fa-phone">
                </div>
                <div class="form-group col-md-6 ">
                  <label class="fw-bold">Email:</label>
                  <input type="text" name="agent_email" id="agent_email" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-envelope"
                    value="{{ Auth::user()->email }}">
                </div>
              </div>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">Brokerage:</label>
                  <input type="text" name="agent_brokerage" id="agent_brokerage" placeholder=""
                    value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                    data-icon="fa-solid fa-handshake">
                </div>
                <div class="form-group col-md-6">
                  <label class="fw-bold">Real Estate License #:</label>
                  <input type="text" name="agent_license_no" id="agent_license_no" placeholder=""
                    value="{{ optional(Auth::user())->license_no }}" class="form-control has-icon"
                    data-icon="fa-solid fa-id-card">
                </div>
              </div>
              <div class="form-group row">
                <div class="form-group col-md-6">
                  <label class="fw-bold">NAR Member ID (NRDS ID):</label>
                  <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                    value="{{ optional(Auth::user())->mls_id }}">
                </div>
                <div class="form-group  col-md-6">
                  <label class="fw-bold">Listed By: Real Estate Agent:</label>
                  <input type="text" name="realEstateAgent" id="titl_company_email"
                    data-icon="fa-solid fa-user" class="form-control has-icon">
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="95">
              <div class="form-group">
                <label class="fw-bold">3D Tour:</label>
                <input type="url" name="three_d_tour" id="three_d_tour" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                  </div>
                <div class="form-group">
                  <label class="fw-bold">Floor Plan:</label>
                  <input type="file" name="floor_plan" id="floor_plan" class="form-control">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Addendums/Disclosures:</label>
                  <input type="file" name="disclosures[]" id="upload_file" placeholder="" class="form-control"
                  multiple>
                </div>
                <span class="vacantFields">
                <div class="row">
                  <div class="col-6">
                    <div class="videoBox form-group">
                        <label class="fw-bold mt-1">Property Video:</label>
                        <div class="video bgImg"></div>
                        <div class="videoDiv">
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
                          <label class="fw-bold">Property Photos:</label>
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

            {{-- <div class="wizard-step" data-step="7">
              <h4>Financial Information:</h4>
              @php
                $acutual_or_projected = [['name' => 'Actual', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Projected', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Operating Expenses:</label>
                <input type="number" name="operating_expenses" id="operating_expenses" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar " placeholder="Operating Expenses">
              </div>
              <div class="form-group">
                <label class="fw-bold">Net Operating Income:</label>
                <input type="number" name="net_operating_income" id="net_operating_income"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar" placeholder="Net Operating Income">
              </div>
              <div class="form-group">
                <label class="fw-bold">Net Operating Income Type:</label>
                <select class="grid-picker" name="net_operating_income_type" id="net_operating_income_type"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($acutual_or_projected as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '';
                      } else {
                          $target = '';
                      }
                    @endphp
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">Annual Expense:</label>
                <input type="number" name="annual_expenses" id="annual_expenses" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar" placeholder="Annual Expenses">
              </div>
              <div class="form-group">
                <label class="fw-bold">Annual TTL Schedule Income:</label>
                <input type="number" name="annual_ttl_schedule_income" id="annual_ttl_schedule_income"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar" placeholder="Annual TTL Schedule Income">
              </div>
              <div class="form-group">
                <label class="fw-bold">Annual Income Type:</label>
                <select class="grid-picker" name="annual_income_type" id="annual_income_type"
                  style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($acutual_or_projected as $item)
                    @php
                      if ($item['name'] == 'Yes') {
                          $target = '';
                      } else {
                          $target = '';
                      }
                    @endphp
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>


              @php
                $sale_includes = [['name' => 'Building(s) and Land', 'target' => ''], ['name' => 'Furniture/Fixtures', 'target' => ''], ['name' => 'Leases', 'target' => ''], ['name' => 'Other', 'target' => '']];
              @endphp
              <div class="row ">
                <div class="form-group">
                  <label class="fw-bold">Sale Includes:</label>
                  <select class="grid-picker" name="sale_include" id="sale_include"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($sale_includes as $sale_include)
                      <option value="{{ $sale_include['name'] }}" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        data-target="{{ $sale_include['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $sale_include['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">Number of Tenants:</label>
                <input type="number" name="number_of_tenants" id="number_of_tenants" class="form-control has-icon"
                  data-icon="fa-solid fa-qrcode">
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="8">
              <div class="form-group">
                @php
                  $unit_types = [['target' => '', 'name' => '1 Bed/1 Bath'], ['target' => '', 'name' => '1 Bedroom'], ['target' => '', 'name' => '2 Bed/1 Bath'], ['target' => '', 'name' => '2 Bed/2 Bath'], ['target' => '', 'name' => '2 Bedroom'], ['target' => '', 'name' => '3 Bed/1 Bath'], ['target' => '', 'name' => '3 Bed/2 Bath'], ['target' => '', 'name' => '3 Bedroom'], ['target' => '', 'name' => '4 Bedroom or More'], ['target' => '', 'name' => '4+ Bed/1 Bath'], ['target' => '', 'name' => '4+ Bed/2 Bath'], ['target' => '', 'name' => 'Apartments'], ['target' => '', 'name' => 'Efficiency'], ['target' => '', 'name' => 'Loft'], ['target' => '', 'name' => "Manager's Unit"], ['target' => '', 'name' => 'Multi-Level'], ['target' => '', 'name' => 'Penthouse'], ['target' => '', 'name' => 'Studio']];
                @endphp
                <label for="" class="fw-bold">Unit Types:</label>
                <select class="grid-picker" name="unitTypes[]" id="" style="justify-content: flex-start;"
                  multiple required>
                  <option value="">Select</option>
                  @foreach ($unit_types as $item)
                    <option value="{{ $item['name'] }}" data-target="" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <button type="button" class="btn btn-secondary btn-sm w-100 unitTypeBtn mt-1"
                  onclick="add_unit_types();"><i class="fa-solid fa-plus"></i> Add More</button>
              </div>
              <div class="row">
                <div class="form-group col-md-4">
                  <label class="fw-bold">Heated Sqft</label>
                  <input type="number" name="heatedSqft[]" id="sqt_ft_heated" placeholder="Heated Sqft "
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                  <button type="button" class="btn btn-secondary btn-sm w-100 sqft_btn_row mt-1"
                    onclick="add_sqft();"><i class="fa-solid fa-plus"></i> Add More</button>
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Number of Units</label>
                  <input type="number" name="unitNumber[]" id="number_of_units" placeholder="Number of Units"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                  <button type="button" class="btn btn-secondary btn-sm w-100 unitNumberBtn mt-1"
                    onclick="add_nmbr();"><i class="fa-solid fa-plus"></i> Add More</button>
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Expected Rent</label>
                  <input type="number" name="expected_rent" id="expected_rent" placeholder="Expected Rent"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                  <button type="button" class="btn btn-secondary btn-sm w-100 rentBtn mt-1" onclick="add_rent();"><i
                      class="fa-solid fa-plus"></i> Add More</button>
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Garage Spaces</label>
                  <input type="number" name="garageSpaces[]" id="" placeholder="Garage Spaces"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                  <button type="button" class="btn btn-secondary btn-sm w-100 garageBtn mt-1"
                    onclick="add_garage_spaces();"><i class="fa-solid fa-plus"></i> Add More</button>
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Beds/Unit </label>
                  <input type="number" name="bedsUnit[]" id="" placeholder="Beds/Unit"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                  <button type="button" class="btn btn-secondary btn-sm w-100 bedBtn mt-1" onclick="add_bed_unit();"><i
                      class="fa-solid fa-plus"></i> Add More</button>
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Baths/Unit</label>
                  <input type="number" name="bathUnit[]" id="" placeholder="Baths/Unit"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                  <button type="button" class="btn btn-secondary btn-sm w-100 bathBtn mt-1"
                    onclick="add_baths_unit();"><i class="fa-solid fa-plus"></i> Add More</button>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">Unit Type of Description:</label>
                  <textarea name="unit_type_of_description" id="unit_type_of_description" class="form-control" cols="30"
                    rows="10"></textarea>
                </div>

                <div class="form-group col-md-4">
                  <label class="fw-bold">Total Monthly Rent</label>
                  <input type="number" name="total_monthly_rent" id="garage_attribute" placeholder="Total Monthly Rent"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Total Number of Units:</label>
                  <input type="number" name="total_number_of_units" placeholder="Total Number of Units"
                    id="total_number_of_units" class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Total Monthly Expenses</label>
                  <input type="number" name="total_monthly_expenses" id="garage_attribute"
                    placeholder="Total Monthly Expenses" class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Lease Terms</label>
                  <input type="text" name="lease_terms" id="garage_attribute" placeholder="Lease Terms"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Annual Net Income</label>
                  <input type="number" name="annual_net_income" id="garage_attribute" placeholder="Annual Net Income"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Annual Gross Income</label>
                  <input type="number" name="annual_gross_income" id="garage_attribute"
                    placeholder="Annual Gross Income" class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Est Annual Market Income</label>
                  <input type="text" name="est_annual_market_income" id="garage_attribute"
                    placeholder="Est Annual Market Income" class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Annual Expenses</label>
                  <input type="number" name="annual_expenses" id="garage_attribute" placeholder="Annual Expenses"
                    class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                </div>
                @php
                  $terms_of_leases = [['name' => 'Gross Lease', 'target' => ''], ['name' => 'Net Lease', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pass Throughts', 'target' => ''], ['name' => 'Purchase Options', 'target' => ''], ['name' => 'Renewal Option', 'target' => '']];
                @endphp
                <div class="form-group  ">
                  <label class="fw-bold">Terms of Lease:</label>
                  <select class="grid-picker" name="terms_of_lease" id="terms_of_lease"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($terms_of_leases as $terms_of_lease)
                      <option value="{{ $terms_of_lease['name'] }}" data-target="{{ $terms_of_lease['target'] }}"
                        class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        style="width:calc(33.3% - 10px);">
                        {{ $terms_of_lease['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                @php
                  $tenant_pays = [['name' => 'Association Fees', 'target' => ''], ['name' => 'Electricity', 'target' => ''], ['name' => 'Parking Fee', 'target' => ''], ['name' => 'Sewer', 'target' => ''], ['name' => 'Trash Collection', 'target' => ''], ['name' => 'Water', 'target' => ''], ['name' => 'Other', 'target' => '.tenantPayOthers']];
                @endphp
                <div class="form-group ">
                  <label class="fw-bold">Tenant Pays:</label>
                  <select class="grid-picker" name="tenant_pays" id="terms_of_lease"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($tenant_pays as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        data-icon='<i class="fa-regular fa-circle-check"></i>' style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group tenantPayOthers d-none">
                    <label class="fw-bold">Tenant Pays:</label>
                    <input type="number" name="tenantPays" placeholder="" id="total_number_of_units"
                      class="form-control has-icon" data-icon="fa-solid fa-qrcode">
                  </div>

                </div>

                @php
                  $financial_sources = [['name' => 'Accountant', 'target' => ''], ['name' => 'Broker', 'target' => ''], ['name' => 'Owner', 'target' => ''], ['name' => 'Tax Return', 'target' => '']];
                @endphp
                <div class="form-group road_frontage_next_hide ">
                  <label class="fw-bold">Financial Sources:</label>
                  <select class="grid-picker" name="financial_sources" id="terms_of_lease"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($financial_sources as $financial_source)
                      <option value="{{ $financial_source['name'] }}" data-target="{{ $financial_source['target'] }}"
                        class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
                        style="width:calc(33.3% - 10px);">
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
                      <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="9">
              @php
                $special_sale = [['name' => 'Pre-Construction', 'target' => ''], ['name' => 'Currently Being Built', 'target' => ''], ['name' => 'New Construction', 'target' => ''], ['name' => 'Assignment Contract (Wholesale property)', 'target' => '.assignment_contract'], ['name' => 'Bank Owned/REO', 'target' => ''], ['name' => 'Probate Listing', 'target' => ''], ['name' => 'Short Sale', 'target' => ''], ['name' => 'Government Owned', 'target' => ''], ['name' => 'Auction', 'target' => ''], ['name' => 'None', 'target' => '']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Please select the sale provisions in which the seller is offering: </label>
                <select class="grid-picker" name="saleProvisions[]" id="assignment_contract" multiple
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($special_sale as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group assignment_contract d-none">
                @php
                  $yes_no = [['name' => 'Yes', 'target' => '.assignment_contractYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                @endphp
                <label class="fw-bold">Is the seller currently under contract with a property they would like to
                  assign?</label>
                <select class="grid-picker" name="assignment_contract[]" id="assignment_contract" style=""
                  required>
                  <option value="">Select</option>
                  @foreach ($yes_no as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                      style="width:calc(25% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group assignment_contractYes d-none">
                  <label class="fw-bold">Please provide more information on the contract that the seller is seeking to
                    assign:</label>
                  <input type="text" name="custom_assignment" id="custom_bedrooms" class="form-control"
                    data-icon="" required>
                </div>
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="10">
              @php
                $bedrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '', 'name' => 'None'], ['target' => '.custom_bedrooms', 'name' => 'Other']];
              @endphp
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
                  <label class="fw-bold">Bedrooms:</label>
                  <input type="text" name="custom_bedrooms" id="custom_bedrooms" class="form-control has-icon"
                    data-icon="fa-solid fa-bed" required>
                </div>
                <div class="form-group">
                  @php
                    $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '', 'name' => 'None'], ['target' => '.custom_bathrooms', 'name' => 'Other']];
                  @endphp
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
                  <label class="fw-bold">Bathrooms:</label>
                  <input type="text" name="custom_bathrooms" id="custom_bathrooms" class="form-control has-icon"
                    data-icon="fa-solid fa-bath" required>
                </div>
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="11">
              <div class="form-group">
                <label class="fw-bold">How many heated sqft does the property have?</label>
                <input type="number" name="min_sqft" id="min_sqft" class="form-control has-icon"
                  data-icon="fa-solid fa-qrcode" required>
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="10">
              @php
                $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">What is the total acreage of the property?</label>
                <select class="grid-picker" name="lot_size" id="lot_size" style="justify-content: flex-start;"
                  required>
                  <option value="">Select</option>
                  @foreach ($lot_sizes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
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
            {{-- <div class="wizard-"11">
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
            </div> --}}
            {{-- <div class="wizard-step" data-step="11">
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
            </div> --}}

            {{-- <div class="wizard-step" data-step="12">
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
                <div class="form-group d-none other_pets">
                  <label class="fw-bold">Number of pets allowed:</label>
                  <input type="number" name="number_of_pets" id="number_of_pets" class="form-control has-icon"
                    data-icon="fa-solid fa-qrcode" required>
                  <label class="fw-bold">Max pet weight:</label>
                  <input type="text" name="pet_weight" id="pet_weight" class="form-control has-icon"
                    data-icon="fa-solid fa-qrcode" required>
                  <label class="fw-bold">Pet restrictions:</label>
                  <input type="text" name="pet_restrictions" id="pet_restrictions" class="form-control has-icon"
                    data-icon="fa-solid fa-qrcode" required>
                </div>
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="13">
              <div class="form-group">
                @php
                  $association = [['name' => 'Yes', 'target' => '.associationYesRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                @endphp
                <label class="fw-bold">Does the property have an HOA, condo association, master association, and/or
                  community fee? </label>
                <select class="grid-picker" name="associationOpt" id="pools" style="justify-content: flex-start;"
                  required>
                  <option value="">Select</option>
                  @foreach ($association as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group d-none associationYesRes">
                  <label class="fw-bold">Does the property have an HOA, condo association, master association, and/or
                    community fee? </label>
                  <label class="fw-bold">HOA Fee:</label>
                  <input type="number" name="custom_garage" id="custom_garage" class="form-control has-icon"
                    data-icon="fa-solid fa-qrcode" required>
                  <div class="form-group">
                    @php
                      $paymentSchedule = [['name' => 'Annually', 'target' => '', 'icon' => 'f'], ['name' => 'Monthly', 'target' => '', 'icon' => ''], ['name' => 'Quarterly', 'target' => '', 'icon' => ''], ['name' => 'Semi-Annually', 'target' => '', 'icon' => '']];
                    @endphp
                    <label class="fw-bold">HOA Payment Schedule:</label>
                    <select class="grid-picker" name="paymentSchedule" id=""
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($paymentSchedule as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Condo Fee:</label>
                    <input type="text" name="condoFee" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" required>
                  </div>
                  <div class="form-group">
                    @php
                      $condoSchedule = [['name' => 'Annually', 'target' => '', 'icon' => 'f'], ['name' => 'Monthly', 'target' => '', 'icon' => ''], ['name' => 'Quarterly', 'target' => '', 'icon' => ''], ['name' => 'Semi-Annually', 'target' => '', 'icon' => '']];
                    @endphp
                    <label class="fw-bold">Condo Payment Schedule: </label>
                    <select class="grid-picker" name="condoSchedule" id="" style="justify-content: flex-start;"
                      required>
                      <option value="">Select</option>
                      @foreach ($condoSchedule as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group">
                    @php
                      $masterAssocYesRes = [['name' => 'Yes', 'target' => '.masterAssocYesRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Master Association: </label>
                    <select class="grid-picker" name="masterAssoc" id="pools" style="justify-content: flex-start;"
                      required>
                      <option value="">Select</option>
                      @foreach ($masterAssocYesRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group d-none masterAssocYesRes">
                      <label class="fw-bold">Master Association Fee:</label>
                      <input type="text" name="csutomMasterAssoc" id="custom_garage" class="form-control has-icon"
                        data-icon="fa-solid fa-qrcode" required>
                    </div>
                  </div>
                  <div class="form-group">
                    @php
                      $masterAssocFee = [['name' => 'Annually', 'target' => '', 'icon' => 'f'], ['name' => 'Monthly', 'target' => '', 'icon' => ''], ['name' => 'Quarterly', 'target' => '', 'icon' => ''], ['name' => 'Semi-Annually', 'target' => '', 'icon' => '']];
                    @endphp
                    <label class="fw-bold">Master Association Fee Schedule: </label>
                    <select class="grid-picker" name="masterAssocFee" id=""
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($masterAssocFee as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Master Association Name:</label>
                    <input type="text" name="masterAssocName" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Master Association Contact Phone: </label>
                    <input type="text" name="masterAssocPhone" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" required>
                  </div>
                  <div class="form-group">
                    @php
                      $additionalFeeRes = [['name' => 'Yes', 'target' => '.additionalFeeRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Are there any additional fees? </label>
                    <select class="grid-picker" name="additionalFee" id="pools" style="justify-content: flex-start;"
                      required>
                      <option value="">Select</option>
                      @foreach ($additionalFeeRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group d-none additionalFeeRes">
                      <label class="fw-bold">What is the fee for? </label>
                      <input type="text" name="customFee" id="custom_garage" class="form-control has-icon"
                        data-icon="fa-solid fa-qrcode" required>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Other Fee: </label>
                    <input type="text" name="otherFee" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" required>
                  </div>
                  <div class="form-group">
                    @php
                      $otherScheduleFee = [['name' => 'Annually', 'target' => '', 'icon' => 'f'], ['name' => 'Monthly', 'target' => '', 'icon' => ''], ['name' => 'Quarterly', 'target' => '', 'icon' => ''], ['name' => 'Semi-Annually', 'target' => '', 'icon' => '']];
                    @endphp
                    <label class="fw-bold">Other Fee Schedule: </label>
                    <select class="grid-picker" name="otherScheduleFee" id=""
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($otherScheduleFee as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Association/Manger Contact Name: </label>
                    <input type="text" name="mangerName" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Association/Manger Contact Email: </label>
                    <input type="text" name="managerEmail" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Association/Manger Contact Phone:</label>
                    <input type="text" name="managerPhone" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Association/Manger Contact Website Address:</label>
                    <input type="text" name="managerWeb" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" required>
                  </div>
                  <div class="form-group">
                    @php
                      $housesRes = [['name' => 'Yes', 'target' => '.housesRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Housing for Older Persons: </label>
                    <select class="grid-picker" name="houses" id="pools" style="justify-content: flex-start;"
                      required>
                      <option value="">Select</option>
                      @foreach ($housesRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="14">
              <div class="form-group">
                @php
                  $communityFeature = [
                      ['name' => 'Airport/Runway', 'target' => ''],
                      ['name' => 'Association Recreation - Lease', 'target' => ''],
                      ['name' => 'Association Recreation - Owned', 'target' => ''],
                      ['name' => 'Buyer Approval Required', 'target' => ''],
                      ['name' => 'Clubhouse', 'target' => ''],
                      ['name' => 'Community Boat Ramp', 'target' => ''],
                      ['name' => 'Community Mailbox', 'target' => ''],
                      ['name' => 'Deed Restrictions', 'target' => ''],
                      ['name' => 'Dog Park', 'target' => ''],
                      ['name' => 'Fishing', 'target' => ''],
                      ['name' => 'Fitness Center', 'target' => ''],
                      ['name' => 'Gated Community - Guard', 'target' => ''],
                      ['name' => 'Gated Community - No Guard', 'target' => ''],
                      ['name' => 'Golf Carts OK', 'target' => ''],
                      ['name' => 'Golf Community', 'target' => ''],
                      ['name' => 'Handicap Modified', 'target' => ''],
                      ['name' => 'Horse Stable(s)', 'target' => ''],
                      ['name' => 'Horses Allowed', 'target' => ''],
                      ['name' => 'Irrigation-Reclaimed Water', 'target' => ''],
                      ['name' => 'Lake', 'target' => ''],
                      ['name' => 'No Truck/RV/Motorcycle Parking', 'target' => ''],
                      ['name' => 'None', 'target' => ''],
                      ['name' => 'Park', 'target' => ''],
                      ['name' => 'Playground', 'target' => ''],
                      ['name' => 'Pool', 'target' => ''],
                      ['name' => 'Public Boat Ramp', 'target' => ''],
                      ['name' => 'Racquetball', 'target' => ''],
                      ['name' => 'Restaurant', 'target' => ''],
                      ['name' => 'Sidewalk', 'target' => ''],
                      ['name' => 'Special Community Restrictions', 'target' => ''],
                      ['name' => 'Stream Seasonal', 'target' => ''],
                      ['name' => 'Tennis Courts', 'target' => ''],
                      ['name' => 'Water Access', 'target' => ''],
                      ['name' => 'Waterfront', 'target' => ''],
                      ['name' => 'Wheelchair Access', 'target' => ''],
                  ];

                @endphp
                <label class="fw-bold">Community Features: </label>
                <select class="grid-picker" name="communityFeature[]" id="pools"
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($communityFeature as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="15">
              <div class="form-group">
                @php
                  $assocAmenities = [
                      ['name' => 'Airport/Runway', 'target' => ''],
                      ['name' => 'Basketball Court', 'target' => ''],
                      ['name' => 'Boat Slip', 'target' => ''],
                      ['name' => 'Cable', 'target' => ''],
                      ['name' => 'Clubhouse', 'target' => ''],
                      ['name' => 'Dock', 'target' => ''],
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
                      ['name' => 'Marina', 'target' => ''],
                      ['name' => 'Optional Additional Fees', 'target' => ''],
                      ['name' => 'Other', 'target' => ''],
                      ['name' => 'Park', 'target' => ''],
                      ['name' => 'Pickleball Court(s)', 'target' => ''],
                      ['name' => 'Playground', 'target' => ''],
                      ['name' => 'Pool', 'target' => ''],
                      ['name' => 'Private Boat Ramp', 'target' => ''],
                      ['name' => 'Racquet Ball', 'target' => ''],
                      ['name' => 'Recreation Facilities', 'target' => ''],
                      ['name' => 'Sauna', 'target' => ''],
                      ['name' => 'Security', 'target' => ''],
                      ['name' => 'Shuffleboard Court', 'target' => ''],
                      ['name' => 'Spa/Hot Tub', 'target' => ''],
                      ['name' => 'Storage', 'target' => ''],
                      ['name' => 'Tennis Court(s)', 'target' => ''],
                      ['name' => 'Trails', 'target' => ''],
                      ['name' => 'Vehicle Restrictions', 'target' => ''],
                      ['name' => 'Wheelchair Access', 'target' => ''],
                  ];

                @endphp
                <label class="fw-bold">Association Amenities: </label>
                <select class="grid-picker" name="assocAmenities[]" id="pools" style="justify-content: flex-start;"
                  required>
                  <option value="">Select</option>
                  @foreach ($assocAmenities as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="16">
              <div class="form-group">
                @php
                  $feeIncludes = [
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
                      ['name' => 'None', 'target' => ''],
                      ['name' => 'Other', 'target' => ''],
                      ['name' => 'Pest Control', 'target' => ''],
                      ['name' => 'Pool Maintenance', 'target' => ''],
                      ['name' => 'Private Road', 'target' => ''],
                      ['name' => 'Recreational Facilities', 'target' => ''],
                      ['name' => 'Security', 'target' => ''],
                      ['name' => 'Sewer', 'target' => ''],
                      ['name' => 'Trash', 'target' => ''],
                      ['name' => 'Water', 'target' => ''],
                  ];
                @endphp
                <label class="fw-bold">Fee Includes: </label>
                <select class="grid-picker" name="feeIncludes[]" id="" style="justify-content: flex-start;"
                  required>
                  <option value="">Select</option>
                  @foreach ($feeIncludes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="17">
              <span class="resFields">
                <div class="form-group">
                  @php
                    $waterAccessRes = [['name' => 'Yes', 'target' => '.waterAccessRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'], ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question']];
                  @endphp
                  <label class="fw-bold">Water Access:</label>
                  <select class="grid-picker" name="has_water_access" id="has_water_access"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($waterAccessRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                @php
                  $water_access = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Beach - Access Deeded', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Limited Access', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
                @endphp
                <div class="form-group waterAccessRes d-none">
                  <select class="grid-picker" name="water_access[]" id="water_access"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($water_access as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group ">
                  @php
                    $waterViewRes = [['name' => 'Yes', 'target' => '.waterViewRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'], ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question']];
                  @endphp
                  <label class="fw-bold">Water View:</label>
                  <select class="grid-picker" name="has_water_view" id="has_water_view"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($waterViewRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                @php
                  $water_views = [['name' => 'Bay/Harbor - Full', 'target' => ''], ['name' => 'Bay/Harbor - Partial', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Canal', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Gulf/Ocean - Full', 'target' => ''], ['name' => 'Gulf/Ocean - Partial', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'None', 'target' => '']];
                @endphp
                <div class="form-group waterViewRes d-none ">
                  <select class="grid-picker" name="water_view[]" id="water_view" style="justify-content: flex-start;"
                    multiple>
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
                <div class="form-group">
                  @php
                    $waterExtraRes = [['name' => 'Yes', 'target' => '.waterExtraRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'], ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question']];
                  @endphp
                  <label class="fw-bold">Water Extras:</label>
                  <select class="grid-picker" name="has_water_extra" id="has_water_extra"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($waterExtraRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
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
                      ['name' => 'Dock w/o Water Supply', 'target' => ''],
                      ['name' => 'Dock w/Water Supply', 'target' => ''],
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
                <div class="form-group waterExtraRes d-none ">
                  <select class="grid-picker" name="water_extras[]" id="water_extras"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($water_extras as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        data-icon="<i class='fa-regular fa-circle-check'></i>" style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  @php
                    $waterFrontageRes = [['name' => 'Yes', 'target' => '.waterFrontageRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'], ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question']];
                  @endphp
                  <label class="fw-bold">Water Frontage:</label>
                  <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($waterFrontageRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                @php
                  $water_frontage = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => '']];
                @endphp
                <div class="form-group waterFrontageRes d-none">
                  <select class="grid-picker" name="water_frontage[]" id="water_frontage"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($water_frontage as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group">
                  @php
                    $waterPreferenceRes = [['name' => 'Yes', 'target' => '.waterPreferenceRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'], ['name' => 'Optional', 'target' => '', 'icon' => 'fa-regular fa-circle-question']];
                  @endphp
                  <label class="fw-bold">What is the condition of the property? </label>
                  <select class="grid-picker" name="viewOptions" id="has_view_prefrence"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($waterPreferenceRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                @php
                  $view = [['name' => 'City', 'target' => ''], ['name' => 'Garden', 'target' => ''], ['name' => 'Golf Course', 'target' => ''], ['name' => 'Greenbelt', 'target' => ''], ['name' => 'Mountain(s)', 'target' => ''], ['name' => 'Park', 'target' => ''], ['name' => 'Pool', 'target' => ''], ['name' => 'Tennis Court', 'target' => ''], ['name' => 'Trees/Woods', 'target' => ''], ['name' => 'Water', 'target' => ''], ['name' => 'Other', 'target' => '.viewOtherRes']];
                @endphp
                <div class="form-group waterPreferenceRes d-none">
                  <select class="grid-picker" name="view[]" id="view" style="justify-content: flex-start;"
                    multiple>
                    <option value="">Select</option>
                    @foreach ($view as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group viewOtherRes d-none">
                    <label class="fw-bold">What is the condition of the property? </label>
                    <input type="text" name="viewOther" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-qrcode" required>
                  </div>
                </div>
              </span>
            </div> --}}
            {{-- <div class="wizard-step" data-step="18">
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
            </div> --}}
            {{-- <div class="wizard-step" data-step="19">
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
                  data-icon="fa-solid fa-qrcode" required>
              </div>
            </div> --}}
            {{-- <div class="wizard-step" data-step="20">
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
            </div> --}}
            {{-- <div class="wizard-step" data-step="21">
              @php
                $propConditionsRes = [['name' => 'Pre-Construction', 'target' => ''], ['name' => 'Currently Being Built', 'target' => ''], ['name' => 'New Construction', 'target' => ''], ['name' => 'Completely Updated: No updates needed', 'target' => ''], ['name' => 'Semi-updated: Needs minor updates', 'target' => ''], ['name' => 'Not Updated: Requires a complete update', 'target' => ''], ['name' => 'Tear Down: Requires complete demolition and reconstruction', 'target' => ''], ['name' => 'Other', 'target' => '.propConditionOtherRes']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">What is the condition of the property? </label>
                <select class="grid-picker" name="prop_conditions[]" multiple id="prop_conditions" required>
                  <option value="">Select</option>
                  @foreach ($propConditionsRes as $item)
                    <option value="{{ $item['name'] }}" class="card flex-row" data-target="{{ $item['target'] }}"
                      style="width:calc(50% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group d-none propConditionOtherRes">
                <label class="fw-bold">What is the condition of the property? </label>
                <input type="number" name="custom_prop_conditions" id="custom_prop_conditions"
                  class="form-control has-icon" data-icon="fa-solid fa-qrcode" required>
              </div>
            </div> --}}
            {{-- Changes by Waqas  Start --}}

            {{-- <div class="wizard-step" data-step="22">
              <div class="form-group">
                <label class="fw-bold"> Is the property currently offered leased? </label>
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
                    <input type="number" name="current_rental_price" placeholder="0.00"
                      class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  @php
                    $utilities = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '1 year', 'target' => ''], ['name' => '2 year', 'target' => ''], ['name' => '3-5 years', 'target' => ''], ['name' => 'Month to Month', 'target' => ''], ['name' => 'Other', 'target' => '']];
                  @endphp
                  <div class="form-group col-md-12">
                    <label class="fw-bold">Current Lease Terms:</label>
                    <select class="grid-picker" name="leased_term" id="leased_term"
                      style="justify-content: flex-start;" multiple required>
                      <option value="">Select</option>
                      @foreach ($utilities as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(25% - 10px);">
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
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
                  <div class="form-group only_income_property">
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
                    <label class="fw-bold">Security Deposit:</label>
                    <input type="number" name="security_deposit" placeholder="0.00" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Did the tenant pay the last month's rent?</label>
                    <select class="grid-picker" name="is_tenant_pay_rent" id="is_tenant_pay_rent"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($requires_or_no1 as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
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
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row " style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>

                </div>
              </div>
            </div> --}}
            {{-- changes --}}
            {{-- <div class="wizard-step" data-step="23">
              <div class="form-group">
                @php
                  $contingencies = [['name' => 'Contingency', 'target' => '.contingency', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Inspection', 'target' => '.inspection', 'icon' => 'fa-regular fa-circle-check']];

                  $inspection = [['name' => 'Inspection contingency (days)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Appraisal contingency (days)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Financing contingency (days)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Sale of a property contingency (days)', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '.custom_inspection', 'icon' => 'fa-regular fa-circle-check']];
                @endphp

                <div class="form-group">
                  <label class="fw-bold">Acceptable Contingencies :</label>
                  <select class="grid-picker" name="contingencies" id="carport"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($contingencies as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="form-group contingency d-none">
                  <label class="fw-bold">Contingencies :</label>
                  <input type="text" name="contingency" id="contingency" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group inspection d-none">
                  <label class="fw-bold">Inspection :</label>
                  <select class="grid-picker" name="inspection" id="carport" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($inspection as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group custom_inspection d-none">
                    <label class="fw-bold">Please enter Contingency (days) :</label>
                    <input type="text" name="custom_inspection" id="custom_inspection"
                      class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                </div>
              </div>
            </div> --}}

            {{-- <div class="wizard-step" data-step="11">
              <div class="form-group">
                <label class="fw-bold">Offered Closing Date: </label>
                <input type="date" name="offer_closing_date" id="offer_closing_date"
                  class="form-control has-icon" data-icon="fa-regular fa-calendar" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">What is the desired number of days for the seller to complete the closing
                  process?
                </label>
                <input type="number" name="number_of_days" id="number_of_days" class="form-control has-icon"
                  data-icon="fas fa-calendar-day" required>
              </div>
            </div>
            <div class="wizard-step" data-step="11">
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
                <label class="fw-bold"> Please enter the offered buyer’s credit:</label>
                <input type="number" name="custom_offer" id="custom_offer" class="form-control has-icon"
                  data-icon="" required>
              </div>
            </div> --}}

            {{-- <div class="wizard-step" data-step="24">
              <div class="form-group">
                <label class="fw-bold" for="price">Offered Price:</label>
                <input type="number" step="0.01" name="max_price" placeholder="0.00" id="price"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar"
                  data-msg-required="Please enter Price" autofocus required>
              </div>
              @php
                $financingsRes = [
                    ['name' => 'Cash', 'target' => ''],
                    ['name' => 'Conventional', 'target' => ''],
                    ['name' => 'FHA', 'target' => ''],
                    ['name' => 'VA', 'target' => ''],
                    ['name' => 'Litecoin (LTC)', 'target' => ''],
                    ['name' => 'Bitcoin Cash (BCH)', 'target' => ''],
                    ['name' => 'Dash (DASH)', 'target' => ''],
                    ['name' => 'Ripple (XRP)', 'target' => ''],
                    ['name' => 'Tether (USDT)', 'target' => ''],
                    ['name' => 'USD Coin (USDC)', 'target' => ''],
                    ['name' => 'NFT', 'target' => '.nftRes'],
                    ['name' => 'Private Financing Available', 'target' => ''],
                    ['name' => 'USDA', 'target' => ''],
                    ['name' => 'Assumable', 'target' => '.assumableRes'],
                    ['name' => 'Acceptable Exchange Item', 'target' => '.custom_exchange'],
                    ['name' => 'Lease Option', 'target' => ''],
                    ['name' => 'Lease Purchase', 'target' => ''],
                    ['name' => 'Special Funding', 'target' => ''],
                    ['name' => 'Seller Financing', 'target' => '.custom_seller_financings'],
                    ['name' => 'Cryptocurrency', 'target' => '.cryptoRes'],
                    ['name' => 'Prepayment Penalty', 'target' => '.prepaymentRes'],
                    ['name' => 'Balloon Payment', 'target' => '.balloonPaymentRes'],
                    ['name' => 'Other', 'target' => '.otherRes'],
                ];
              @endphp

              <div class="form-group">
                <label class="fw-bold">Acceptable Currency/Financing Type:</label>
                <select class="grid-picker" name="financing" id="financingRes" style="justify-content: flex-start;"
                  required>
                  @foreach ($financingsRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>

              </div>
              <div class="form-group otherRes d-none">
                <div class="form-group">
                  <label class="fw-bold">Acceptable Currency/Financing: </label>
                  <input type="text" name="financingOther[]" id=""
                    class="form-control form-control has-icon" data-icon="fa-solid fa-dollar" required>
                </div>
              </div>
              <div class="form-group row" id="leaseOptionRes" style="display: none;">
                <div class="form-group">
                  <label class="fw-bold" for="desired-offering-price">What is the seller's desired offering price for a
                    lease
                    purchase?</label>
                  <input type="text" id="desired-offering-price" name="lease[]"
                    class="form-control form-control has-icon">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="specific-terms">What specific terms does the seller propose for the lease
                    purchase?</label>
                  <input type="text" id="specific-terms" name="lease[]"
                    class="form-control form-control has-icon">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="proposed-duration">What is the proposed duration of the lease?</label>
                  <input type="text" id="proposed-duration" name="lease[]"
                    class="form-control form-control has-icon">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="monthly-payment">What is the monthly payment amount the seller is
                    seeking?</label>
                  <input type="text" id="monthly-payment" name="lease[]"
                    class="form-control form-control has-icon">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="conditions-requirements">Are there any specific conditions or requirements
                    outlined by the
                    seller for the lease purchase?</label>
                  <input type="text" id="conditions-requirements" name="lease[]"
                    class="form-control form-control has-icon">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="option-fee">Does the seller require an option fee, and if so, what is the
                    amount?</label>
                  <input type="text" id="option-fee" name="lease[]" class="form-control form-control has-icon">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="changes-in-offering-price">Is there a possibility of changes in the
                    offering price during
                    the lease period?</label>
                  <input type="text" id="changes-in-offering-price" name="lease[]"
                    class="form-control form-control has-icon">
                </div>
              </div>
              <div class="form-group row custom_seller_financings d-none">
                <div class="form-group col-md-4">
                  <label class="fw-bold">Puchase Price:</label>
                  <input type="text" name="sellerFinancing[]" id=""
                    class="form-control form-control has-icon" data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Down Payment:</label>
                  <input type="text" name="sellerFinancing[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Seller Financing Amount:</label>
                  <input type="text" name="sellerFinancing[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Interest rate:</label>
                  <input type="text" name="sellerFinancing[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Term:</label>
                  <input type="text" name="sellerFinancing[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Monthly Payments:</label>
                  <input type="text" name="sellerFinancing[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Closing Costs:</label>
                  <input type="text" name="sellerFinancing[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
              </div>
              <div class="form-group assumableRes d-none">
                <div class="form-group">
                  <label class="fw-bold">What assumable terms are being offered?</label>
                  <input type="text" name="assumable[]" id="" class="form-control form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group">
                  <label class="fw-bold"> Are there any restrictions or qualifications for a buyer assuming the existing
                    financing?</label>
                  <input type="text" name="assumable[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Is there any outstanding balance on the existing financing that the buyer would
                    need to cover?</label>
                  <input type="text" name="assumable[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
              </div>
              <div class="form-group cryptoRes d-none">
                <div class="form-group">
                  <label class="fw-bold">What type of cryptocurrency will the seller accept?</label>
                  <input type="text" name="crypto[]" id="" class="form-control form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group">
                  <label class="fw-bold"> What percentage of the purchase price will the seller accept in
                    cryptocurrency?</label>
                  <input type="text" name="crypto[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group">
                  <label class="fw-bold">What percentage of the purchase price will the seller accept in cash?</label>
                  <input type="text" name="crypto[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
              </div>
              <div class="form-group nftRes d-none">
                <div class="form-group">
                  <label class="fw-bold">What type of NFT will the seller accept?</label>
                  <input type="text" name="nft[]" id="" class="form-control form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group">
                  <label class="fw-bold">What percentage of the purchase price will the seller accept as an NFT?</label>
                  <input type="text" name="nft[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
                <div class="form-group">
                  <label class="fw-bold">What percentage of the purchase price will the seller accept in cash?</label>
                  <input type="text" name="nft[]" id="" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar">
                </div>
              </div>
              <div class="form-group custom_exchange d-none">
                <div class="form-group">
                  @php
                    $exchangeItemRes = [['name' => 'Another Home', 'target' => ''], ['name' => 'Vehicle', 'target' => ''], ['name' => 'Boat', 'target' => ''], ['name' => 'Motorhome', 'target' => ''], ['name' => 'Artwork', 'target' => ''], ['name' => 'Jewelry', 'target' => ''], ['name' => 'Other', 'target' => '.exchangeOtherRes']];
                  @endphp
                  <label class="fw-bold">Offered Exchange Item :</label>
                  <select class="grid-picker" name="carport" id="carport" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($exchangeItemRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group exchangeOtherRes">
                    <div class="form-group">
                      <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                      <input type="text" name="exchangeOther[]" id="" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is
                        willing to exchange/trade? </label>
                      <input type="text" name="exchangeOther[]" id="" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How much cash does the seller require on top of the Exchange/Trade item?
                      </label>
                      <input type="text" name="exchangeOther[]" id="" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar">
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How is the value of the exchange/trade item determined? </label>
                      <input type="text" name="exchangeOther[]" id="" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar">
                    </div>
                  </div>
                </div>
              </div>
              <div class="form-group prepaymentRes d-none">
                @php
                  $prepaymentOpt = [['name' => 'Yes', 'target' => '.prepaymentYesRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Is the seller offering a credit to the buyer at closing? </label>
                  <select class="grid-picker" name="prepaymentOption" id="selle_offer"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($prepaymentOpt as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach

                  </select>
                  <div class="form-group prepaymentYesRes d-none">
                    <label class="fw-bold">What is the prepayment penalty amount?</label>
                    <input type="text" name="customPrepayment" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar">
                  </div>
                </div>
              </div>
              <div class="form-group balloonPaymentRes d-none">
                @php
                  $balloonOpt = [['name' => 'Yes', 'target' => '.balloonYesRes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Is the seller offering a credit to the buyer at closing? </label>
                  <select class="grid-picker" name="balloonOption" id="selle_offer"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($balloonOpt as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach

                  </select>
                  <div class="form-group balloonYesRes d-none">
                    <label class="fw-bold">How much is the balloon payment?</label>
                    <input type="text" name="customPrepayment" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar">
                    <label class="fw-bold">When is the balloon payment due?</label>
                    <input type="text" name="customPrepayment" id="" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar">
                  </div>
                </div>
              </div>
            </div> --}}

            {{-- <div class="wizard-step" data-step="25">
              <div class="form-group">
                <label class="fw-bold">What is the commission offered to the buyer’s agent? </label>
                <input type="number" name="agent_commission_percent" id="agent_commission_percent" min="1"
                  class="form-control has-icon" data-icon="" required>
              </div>
            </div> --}}

            {{-- <div class="wizard-step" data-step="26">
              <div class="form-group">
                <label class="fw-bold">Description and Additional Details: Please provide any additional information
                  about
                  the property:</label>
                <textarea name="description" id="description" class="form-control" cols="30" rows="5" required></textarea>
              </div>
            </div> --}}

            {{-- Changes by Waqas  End --}}
            {{-- <div class="wizard-step" data-step="27">
              <small class="">Note: The following section is privately sent to the agent.</small>
              <div class="form-group">
                <label class="fw-bold">Address of the property:</label>
                <input type="text" name="address" placeholder="199 Florida A1A, Satellite Beach, FL, USA"
                  id="form_title" class="form-control search_places has-icon" data-type="address" required
                  data-icon="fa-solid fa-location-dot" />
              </div>
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

              <div class="form-group">
                <label class="fw-bold">Video of the property: (upload)</label>
                <input type="file" class="form-control" name="video_image">
              </div>
              <div class="form-group">
                <label class="fw-bold">Video of the property: (link)</label>
                <input type="text" class="form-control" name="video_url">
              </div>
              <div class="form-group">
                <label class="fw-bold">Floor plan of the property: (upload)</label>
                <input type="file" class="form-control" name="floorImage">
              </div>
              <div class="form-group">
                <label class="fw-bold">Floor plan of the property: (link)</label>
                <input type="text" class="form-control" name="flooLink">
              </div>
            </div> --}}
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
  <template class="sqft mt-1"><input type="text" name="heatedSqft[]" placeholder="Heated Sqft"
      data-type="unit" class="form-control has-icon mt-1" data-icon="fa-solid fa-qrcode">
  </template>
  <template class="unitTypeTemp mt-1"><input type="text" name="unitTypes[]" placeholder="Unit Types"
      data-type="unit" class="form-control has-icon mt-1" data-icon="fa-solid fa-qrcode">
  </template>
  <template class="unitNumber mt-1"><input type="text" name="unitNumber[]" placeholder="Number of Units"
      data-type="unit" class="form-control has-icon mt-1" data-icon="fa-solid fa-qrcode">
  </template>
  <template class="rentBtnTemp mt-1"><input type="text" name="rent[]" placeholder="Expected Rent"
      data-type="unit" class="form-control has-icon mt-1" data-icon="fa-solid fa-qrcode">
  </template>
  <template class="bedUnitTemp mt-1"><input type="text" name="bedUnit[]" placeholder="Beds/Unit"
      data-type="unit" class="form-control has-icon mt-1" data-icon="fa-solid fa-qrcode">
  </template>
  <template class="bathUnitTemp mt-1"><input type="text" name="bathUnit[]" placeholder="Baths/Unit"
      data-type="unit" class="form-control has-icon mt-1" data-icon="fa-solid fa-qrcode">
  </template>
  <template class="garageTemp mt-1">
    <input type="text" name="garageSpaces[]" placeholder="Garage Spaces" data-type="unit"
      class="form-control has-icon mt-1" data-icon="fa-solid fa-qrcode">
  </template>
@endsection
@push('scripts')
  <script>
    $(function() {
      $('.add-row').on('click', function() {
        var socialRow =
          `<tr>
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
        $('.business-opportunity').hide();
        $('.vacant-land').hide();
        $('.income-length').hide();
        $('.commercial-length').hide();
        $('.only_income_property').hide();
        $('.businessOpportunity').hide();


      } else if (p == "Income Property") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').show();
        $('.commercial-length').hide();
        $('.only_income_property').show();
        $('.business-opportunity').hide();
        $('.vacant-land').hide();
        $('.businessOpportunity').hide();


      } else if (p == "Commercial Property") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').show();
        $('.only_income_property').hide();
        $('.business-opportunity').hide();
        $('.vacant-land').hide();
        $('.businessOpportunity').hide();


      } else if (p == "Business Opportunity") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').show();
        $('.only_income_property').hide();
        $('.business-opportunity').show();
        $('.vacant-land').hide();
        $('.businessOpportunity').show();


      } else if (p == "Vacant Land") {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').show();
        $('.only_income_property').hide();
        $('.business-opportunity').hide();
        $('.vacant-land').show();
        $('.businessOpportunity').hide();


      } else {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').hide();
        $('.only_income_property').hide();
        $('.business-opportunity').hide();
        $('.vacant-land').hide();
        $('.businessOpportunity').hide();

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
          errorClass: "text-error text-danger w-100",
          onkeyup: false,
          onfocusout: false,
          /* submitHandler: function() {
              // alert("Submitted, thanks!");
              $(".mainform").submit();
          } */
        });

        StepWizard.setStep();
        // property_type;
        property_listed;
        $('#property_listed').on('change', function() {
          // property_type = $(this).val();
          property_listed = $(this).val();
          // Count the remaining steps without removing them
        });
        $('.wizard-step-next').click(function(e) {
          if (v.form()) {
            if ($('.wizard-step.active').next().is('.wizard-step')) {


              // $('.wizard-step.active').removeClass('active');
              // console.log(StepWizard.currentStep)
              // if (StepWizard.currentStep == 2 && (property_type ==
              //     'Residential Property' || property_type == 'Vacant Land')) {
              //   StepWizard.nextStep = 6;
              //   StepWizard.backStep = 2;
              // } else if (StepWizard.currentStep == 2 && property_type ==
              //   'Commercial Property') {
              //   StepWizard.nextStep = 4;
              //   StepWizard.backStep = 2;
              // } else if (StepWizard.currentStep == 4 && property_type ==
              //   'Commercial Property') {
              //   StepWizard.nextStep = 6;
              //   StepWizard.backStep = 4;
              // } else if (StepWizard.currentStep == 2 && property_type ==
              //   'Income Property') {
              //   StepWizard.nextStep = 5;
              //   StepWizard.backStep = 2;
              // } else if (StepWizard.currentStep == 4 && property_type ==
              //   'Business Opportunity') {
              //   StepWizard.nextStep = 6;
              //   StepWizard.backStep = 4;
              // } else {
              //   StepWizard.backStep = StepWizard.currentStep;
              //   // StepWizard.backStep = StepWizard.nextStep + 1;
              // }

              $('.wizard-step.active').removeClass('active');
              console.log(StepWizard.currentStep)
              if (StepWizard.currentStep == 5 && (property_listed ==
                  'Yes')) {
                StepWizard.nextStep = 28;
                StepWizard.backStep = 5;
              
              } else {
                StepWizard.backStep = StepWizard.currentStep;
                // StepWizard.backStep = StepWizard.nextStep + 1;
              }


              $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
              StepWizard.setStep();

              // if (StepWizard.currentStep == 27 &&
              //   property_type == 'Residential Property'
              // ) {
              //   $('.wizard-step-next').hide();
              //   $('.wizard-step-finish').show();
              // }
              // if (StepWizard.currentStep == 49 &&
              //   property_type == 'Income Property'
              // ) {
              //   $('.wizard-step-next').hide();
              //   $('.wizard-step-finish').show();
              // }
              // if (StepWizard.currentStep == 69 &&
              //   property_type == 'Commercial Property') {
              //   $('.wizard-step-next').hide();
              //   $('.wizard-step-finish').show();
              // }
              // if ((StepWizard.currentStep == 69) &&
              //   property_type == 'Business Opportunity') {
              //   $('.wizard-step-next').hide();
              //   $('.wizard-step-finish').show();
              // }
              // if (StepWizard.currentStep == 81 &&
              //   property_type == 'Vacant Land'
              // ) {
              //   $('.wizard-step-next').hide();
              //   $('.wizard-step-finish').show();
              // }

              if (StepWizard.currentStep == 28 &&
                property_listed == 'Yes'
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

            // if (StepWizard.currentStep == 6 && (property_type ==
            //     'Residential Property' || property_type == 'Vacant Land')) {
            //   StepWizard.backStep = 2;

            // } else if (StepWizard.currentStep == 4 && (property_type ==
            //     'Commercial Property' || property_type == 'Vacant Land')) {
            //   StepWizard.backStep = 2;

            // } else if (StepWizard.currentStep == 6 && (property_type ==
            //     'Commercial Property' || property_type == 'Vacant Land')) {
            //   StepWizard.backStep = 4;

            // } else if (StepWizard.currentStep == 5 && property_type ==
            //   'Income Property') {
            //   StepWizard.backStep = 2;
            // } else if (StepWizard.currentStep == 6 && property_type ==
            //   'Business Opportunity') {
            //   StepWizard.backStep = 4;
            // } else {
            //   StepWizard.backStep = StepWizard.currentStep - 1;
            // }


            if(StepWizard.currentStep == 28 && property_listed ==
              'Yes'){
                StepWizard.backStep = 5;
            } else {
              StepWizard.backStep = StepWizard.currentStep - 1;
            }
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
            StepWizard.data_step = k;
            StepWizard.nextStep = k + 1;
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

    function add_unit_types() {
      var unitType = $('.unitTypeTemp').html();
      $('.unitTypeBtn').before(unitType);
      initialize();
    }

    function add_sqft() {
      var sqft = $('.sqft').html();
      $('.sqft_btn_row').before(sqft);
      initialize();
    }

    function add_nmbr() {
      var unitNumber = $('.unitNumber').html();
      $('.unitNumberBtn').before(unitNumber);
      initialize();
    }

    function add_rent() {
      var rentBtn = $('.rentBtnTemp').html();
      $('.rentBtn').before(rentBtn);
      initialize();
    }

    function add_bed_unit() {
      var UnitTemp = $('.bedUnitTemp').html();
      $('.bedBtn').before(UnitTemp);
      initialize();
    }

    function add_baths_unit() {
      var bathTemp = $('.bathUnitTemp').html();
      $('.bathBtn').before(bathTemp);
      initialize();
    }

    function add_garage_spaces() {
      var garage = $('.garageTemp').html();
      $('.garageBtn').before(garage);
      initialize();
    }
    // Residential
    var leaseResDiv = $('#leaseOptionRes');
    $('#financingRes').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Lease Purchase') || $(this).val().includes('Lease Option')) ? leaseResDiv
        .show(): leaseResDiv.hide()
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
