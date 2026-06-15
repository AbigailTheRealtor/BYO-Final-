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
        {{--  --}}
      </div>
      <div class="card-body">
        <div class="wizard-steps-progress">
          <div class="steps-progress-percent"></div>
        </div>

        <form class="p-4 pt-0 mainform" action="{{ route('tenant.criteria.auction.bid', @$auction->id) }}" method="POST"
          enctype="multipart/form-data">
          @csrf
          
          <div class="wizard-step" data-step="1">
            <div class="form-group">
              @php
                $yes_or_nos = [['name' => 'Yes', 'target' => '.property_link', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <label class="fw-bold" for="address">Is the property currently listed for rent?</label>
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
              <div class="form-group main property_link d-none">
                <label class="fw-bold" for="address">Please provide the link to the property listing:</label>
                <input type="text" name="property_link" data-type="property_link" placeholder="" id="property_link"
                  class="form-control has-icon search_places" data-icon="fa-solid fa-link" required>
              </div>
            </div>           
          </div>
          <div class="wizard-step" data-step='2'>
            @php
              $yes_or_nos = [
                  ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                  ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
              ];
            @endphp
            <h4>Please provide the property's complete address, including the city, county, and state, for the real
              estate asset that the landlord would like to offer to the tenant</h4>
            <div class="form-group">
              <label class="fw-bold" for="address">Address:</label>
              <input type="text" name="address" placeholder="" data-type="address" id="address"
                class="form-control has-icon search_places" data-icon="fa-solid fa-location-dot"
                  required>
            </div>

            <div class="form-group">
              <label class="fw-bold" for="address">City:</label>
              <input type="text" name="city" placeholder="" data-type="cities" id="city"
                class="form-control has-icon search_places" data-icon="fa-solid fa-city"
                 required>
            </div>

            <div class="form-group">
              <label class="fw-bold" for="address">County:</label>
              <input type="text" name="county" placeholder="" id="county"
                class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city"
                 required>
            </div>

            <div class="form-group">
              <label class="fw-bold" for="address">State:</label>
              <input type="text" name="state" placeholder="" data-type="states" id="state"
                class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa"
                 required>
            </div>
          </div>
          <div class="wizard-step" data-step="3">
            <span class="traditional">
              <div class="form-group">
                  <label class="fw-bold" for="custom_terms">Price:</label>
                  <input type="number" name="price" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold" for="custom_terms">List Price Per Sqft:</label>
                  <input type="number" name="list_price_per_sq" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" required>
              </div>
            </span>
            <div class="form-group">
              <label class="fw-bold" for="custom_terms">Lease Availability Date:</label>
              <input type="date" name="leaseDate" class="form-control has-icon"
                  data-icon="fa-regular fa-calendar-days" required>
            </div>
            <div class="form-group">
              @php
                  $leaseTime = [
                      ['name' => '3 Months', 'target' => ''],
                      ['name' => '6 Months', 'target' => ''],
                      ['name' => '9 Months', 'target' => ''],
                      ['name' => '1 Year', 'target' => ''],
                      ['name' => '2 Years', 'target' => ''],
                      ['name' => '3-5 Years', 'target' => ''],
                      ['name' => '5+ Years', 'target' => ''],
                      ['name' => 'Month to Month', 'target' => ''],
                      ['name' => 'Other', 'target' => '.otherLeaseDurationNo'],
                  ];
              @endphp
              <label class="fw-bold">Acceptable Lease Duration: </label>
              <select class="grid-picker" name="leaseTime[]" id="leaseTermRes"
                  style="justify-content: flex-start;" required multiple>
                  <option value="">Select</option>
                  @foreach ($leaseTime as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(25% - 10px);"
                          data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                          {{ $item['name'] }}
                      </option>
                  @endforeach
              </select>
              <div class="form-group main otherLeaseDurationNo d-none">
                  <div class="form-group">
                      <label class="fw-bold">Acceptable Lease Duration:</label>
                      <input type="text" name="other_lease_duration" id="other_lease_duration" class="form-control has-icon"
                          data-icon="fa-regular fa-calendar-days">
                  </div>
              </div>
            </div>
            @if ($auction->get->property_type == 'Commercial Property')
              <span class="">
                <div class="form-group">
                    @php
                        $leaseTerms = [
                            ["name" => 'Absolute (Triple) Net', "target" => '' ],
                            ["name" => 'Gross Lease', "target" => '' ],
                            ["name" => 'Gross Percentages', "target" => '' ],
                            ["name" => 'Ground Lease', "target" => '' ],
                            ["name" => 'Lease Option', "target" => '' ],
                            ["name" => 'Modified Gross', "target" => '' ],
                            ["name" => 'Net Lease', "target" => '' ],
                            ["name" => 'Net Net', "target" => '' ],
                            ["name" => 'Pass Throughs', "target" => '' ],
                            ["name" => 'Purchase Option', "target" => '' ],
                            ["name" => 'Renewal Option', "target" => '' ],
                            ["name" => 'Sale-Leaseback', "target" => '' ],
                            ["name" => 'Seasonal', "target" => '' ],
                            ["name" => 'Special Available (CLO)', "target" => '' ],
                            ["name" => 'Varied Terms', "target" => '' ],
                            ["name" => 'Other', "target" => '.otherTermsLeaseNo' ]
                    ];
                    @endphp
                    <label class="fw-bold"> Terms of Lease: </label>
                    <select class="grid-picker" name="leaseTerms[]" id="leaseTermRes"
                        style="justify-content: flex-start;" required multiple>
                        <option value="">Select</option>
                        @foreach ($leaseTerms as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(25% - 10px);"
                                data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-group main otherTermsLeaseNo d-none">
                        <div class="form-group">
                            <label class="fw-bold">Terms of Lease:</label>
                            <input type="text" name="other_lease_terms" id="other_lease_terms" class="form-control has-icon"
                                data-icon="fa-regular fa-circle-check">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                        @php
                            $frequencyRes = [
                                ['name' => 'Annually', 'target' => ''],
                                ['name' => 'Monthly', 'target' => ''],
                            ];
                        @endphp
                        <label class="fw-bold">Select the frequency in which the Lease Amount is paid: </label>
                        <select class="grid-picker" name="frequency[]" style="justify-content: flex-start;" required
                            multiple>
                            <option value="">Select</option>
                            @foreach ($frequencyRes as $item)
                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    class="card flex-row" style="width:calc(25% - 10px);"
                                    data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                                    {{ $item['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        @php
                            $tenant_pays = [
                                ['name' => 'Association Fees', 'target' => ''],
                                ['name' => 'Capital Expenses', 'target' => ''],
                                ['name' => 'Common Area Maintenance', 'target' => ''],
                                ['name' => 'Condominium Fees', 'target' => ''],
                                ['name' => 'Electricity', 'target' => ''],
                                ['name' => 'Gas', 'target' => ''],
                                ['name' => 'Liability Insurance', 'target' => ''],
                                ['name' => 'Parking Fee', 'target' => ''],
                                ['name' => 'Pro-rated', 'target' => ''],
                                ['name' => 'Property Insurance', 'target' => ''],
                                ['name' => 'Property Taxes', 'target' => ''],
                                ['name' => 'Reserves', 'target' => ''],
                                ['name' => 'Sewer', 'target' => ''],
                                ['name' => 'Trash Collection', 'target' => ''],
                                ['name' => 'Water', 'target' => ''],
                                ['name' => 'Other', 'target' => '.tenantPaysOtherNo'],
                            ];
                        @endphp
                        <label class="fw-bold">Tenant Pays:</label>
                        <select class="grid-picker" name="tenant_pays[]" multiple id="tenant_pays"
                            style="justify-content: flex-start;">
                            <option value="">Select</option>
                            @foreach ($tenant_pays as $tenant_pay)
                                <option value="{{ $tenant_pay['name'] }}"
                                    data-target="{{ $tenant_pay['target'] }}" class="card flex-row"
                                    style="width:calc(33.3% - 10px);"
                                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                    {{ $tenant_pay['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-group main tenantPaysOtherNo d-none">
                            <label class="fw-bold">Tenant Pays:</label>
                            <input type="text" name="tenantPaysOther" class="form-control has-icon"
                                data-icon="fa-solid fa-ruler-combined">
                        </div>
                    </div>
                    <div class="form-group">
                        @php
                            $landlordPays = [
                                ['name' => 'Cable TV', 'target' => ''],
                                ['name' => 'Electricity', 'target' => ''],
                                ['name' => 'Gas', 'target' => ''],
                                ['name' => 'Grounds Care', 'target' => ''],
                                ['name' => 'Insurance', 'target' => ''],
                                ['name' => 'Internet', 'target' => ''],
                                ['name' => 'Laundry', 'target' => ''],
                                ['name' => 'Management', 'target' => ''],
                                ['name' => 'Pest Control', 'target' => ''],
                                ['name' => 'Pool Maintenance', 'target' => ''],
                                ['name' => 'Recreational', 'target' => ''],
                                ['name' => 'Repairs', 'target' => ''],
                                ['name' => 'Security', 'target' => ''],
                                ['name' => 'Sewer', 'target' => ''],
                                ['name' => 'Taxes', 'target' => ''],
                                ['name' => 'Telephone', 'target' => ''],
                                ['name' => 'Trash Collection', 'target' => ''],
                                ['name' => 'Water', 'target' => ''],
                                ['name' => 'None', 'target' => ''],
                                ['name' => 'Other', 'target' => '.landlordPaysOtherNo'],
                            ];
                        @endphp
                        <label class="fw-bold">Landlord Pays:</label>
                        <select class="grid-picker" name="wnerPays[]" multiple
                            style="justify-content: flex-start;">
                            <option value="">Select</option>
                            @foreach ($landlordPays as $item)
                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    class="card flex-row" style="width:calc(33.3% - 10px);"
                                    data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                    {{ $item['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-group main landlordPaysOtherNo d-none">
                            <label class="fw-bold">Landlord Pays:</label>
                            <input type="text" name="landlordPaysOther" id="owner_pays"
                                class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                        </div>
                    </div>
              </span>
            @endif
            @if ($auction->get->property_type == 'Residential Property')
              <span class="">
                <div class="form-group">
                    @php
                        $frequencyCommercial = [
                            ['name' => 'Annually', 'target' => ''],
                            ['name' => 'Daily', 'target' => ''],
                            ['name' => 'Monthly', 'target' => ''],
                            ['name' => 'Seasonally', 'target' => ''],
                        ];
                    @endphp
                    <label class="fw-bold">Select the frequency in which the Lease Amount is paid: </label>
                    <select class="grid-picker" name="frequency[]" style="justify-content: flex-start;" required
                        multiple>
                        <option value="">Select</option>
                        @foreach ($frequencyCommercial as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(25% - 10px);"
                                data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
              </span>
              <span class="">
                <div class="form-group">
                    @php
                        $rentRes = [
                            ['name' => 'Cable TV', 'target' => ''],
                            ['name' => 'Electricity', 'target' => ''],
                            ['name' => 'Gas', 'target' => ''],
                            ['name' => 'Grounds Care', 'target' => ''],
                            ['name' => 'Insurance', 'target' => ''],
                            ['name' => 'Internet', 'target' => ''],
                            ['name' => 'Laundry', 'target' => ''],
                            ['name' => 'Management', 'target' => ''],
                            ['name' => 'Pest Control', 'target' => ''],
                            ['name' => 'Pool Maintenance', 'target' => ''],
                            ['name' => 'Recreational', 'target' => ''],
                            ['name' => 'Repairs', 'target' => ''],
                            ['name' => 'Security', 'target' => ''],
                            ['name' => 'Sewer', 'target' => ''],
                            ['name' => 'Taxes', 'target' => ''],
                            ['name' => 'Telephone', 'target' => ''],
                            ['name' => 'Trash Collection', 'target' => ''],
                            ['name' => 'Water', 'target' => ''],
                            ['name' => 'None', 'target' => ''],
                            ['name' => 'Other', 'target' => '.rentOtherResNo'],
                        ];
                    @endphp
                    <label class="fw-bold">Rent Includes:</label>
                    <select class="grid-picker" name="rent[]" multiple id="tenant_pays"
                        style="justify-content: flex-start;">
                        <option value="">Select</option>
                        @foreach ($rentRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-group main rentOtherResNo d-none">
                        <label class="fw-bold">Rent Includes:</label>
                        <input type="text" name="rentOther" class="form-control has-icon"
                            data-icon="fa-regular fa-circle-check">
                    </div>
                </div>
              </span>
            @endif
            <div class="form-group">
              @php
                $leaseTermRes = [
                  ['name' => 'First, Last, and Security', 'target' => '.depositOne'],
                  [
                      'name' => 'First, Last, Security Deposit, Exit Cleaning Fee, & Application Fee',
                      'target' => '.depositSecondNo'
                  ],
                  [
                      'name' =>
                          'First, Last, Security Deposit, Pet Deposit, Exit Cleaning Fee, & Application Fee',
                      'target' => '.depositThirdNo',
                  ],
                  [
                      'name' =>
                          'First, Last, Security Deposit, Exit Cleaning Fee, Application Fee, Vacation Tax',
                      'target' => '.depositFourNo',
                  ],
                  [
                      'name' =>
                          'First, Security Deposit, Exit Cleaning Fee, Application Fee, & Vacation Tax',
                      'target' => '.depositFiveNo',
                  ],
                  [
                      'name' => 'First, Security, Exit Cleaning Fee & Application Fee',
                      'target' => '.depositSixNo',
                  ],
                  ['name' => 'First, Security, & Application Fee', 'target' => '.depositSeven'],
                  ['name' => 'Other', 'target' => '.custom_inputNo'],
                ];
              @endphp
            </div>
            <div class="form-group ">
              <label class="fw-bold">What is required at move-in?</label>
              <select class="grid-picker" name="required_at_move_in" id=""
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($leaseTermRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="fa-regular fa-circle-check"></i>'>
                          {{ $item['name'] }}
                      </option>
                  @endforeach
              </select>
            </div>
            <div class="custom_inputNo main d-none">
              <div class="form-group">
                  <label class="fw-bold">What is required at move-in?</label>
                  <input type="text" name="moveInOther" class="form-control has-icon"
                      data-icon="fa-regular fa-circle-check">
              </div>
              <div class="form-group">
                  <label class="fw-bold">Please enter the required move-in amounts:</label>
                  <input type="number" name="moveInOtherAmount" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign">
              </div>
            </div>
            <div class="form-group main depositOneNo d-none">
              <label class="fw-bold">Please enter the required move-in amounts:</label>
              <div class="form-group">
                  <label class="fw-bold">First Month:</label>
                  <input type="number" name="firstMonthDeposit" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Last Month:</label>
                  <input type="number" name="lastMonthDeposit" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Security Deposit:</label>
                  <input type="number" name="securityDeposit" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
            </div>
            <div class="form-group main depositSecondNo d-none">
              <label class="fw-bold">Please enter the required move-in amounts:</label>
              <div class="form-group">
                  <label class="fw-bold">First Month:</label>
                  <input type="number" name="firstMonthSecond" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Last Month:</label>
                  <input type="number" name="lastMonthSecond" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Security Deposit:</label>
                  <input type="number" name="securityDepositSecond" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Exit Cleaning Fee:</label>
                  <input type="number" name="exitCleaningFeeSecond" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Fee:</label>
                  <input type="number" name="applicationFeeSecond" data-type="cities" id="cities"
                      class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder=""
                      required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Link: </label>
                  <input type="number" name="applicationLinkSecond" data-type="cities" id="cities"
                      class="form-control has-icon search_places" data-icon="fa-solid fa-link" placeholder=""
                      required>
              </div>
            </div>
            <div class="form-group main depositThirdNo d-none">
              <label class="fw-bold">Please enter the required move-in amounts:</label>
              <div class="form-group">
                  <label class="fw-bold">First Month:</label>
                  <input type="number" name="firstMonthThird" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Last Month:</label>
                  <input type="number" name="lastMonthThird" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Security Deposit:</label>
                  <input type="number" name="securityDepositThird" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Pet Deposit:</label>
                  <input type="number" name="petDepositThird" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Exit Cleaning Fee:</label>
                  <input type="number" name="exitCleaningFeeThird" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Fee:</label>
                  <input type="number" name="applicationFeeThird" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Link:</label>
                  <input type="text" name="applicationLinkThird" class="form-control has-icon "
                      data-icon="fa-solid fa-link" placeholder="" required>
              </div>
            </div>
            <div class="form-group main depositFourNo d-none">
              <label class="fw-bold">Please enter the required move-in amounts:</label>
              <div class="form-group">
                  <label class="fw-bold">First Month:</label>
                  <input type="number" name="firstMonthFour" class="form-control has-icon "
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Last Month:</label>
                  <input type="number" name="lastMonthFour" class="form-control has-icon "
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Security Deposit:</label>
                  <input type="number" name="securityDepositFour" class="form-control has-icon "
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Exit Cleaning Fee:</label>
                  <input type="number" name="exitCleaningFeeFour" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Fee:</label>
                  <input type="number" name="applicationFeeFour" class="form-control has-icon "
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Link:</label>
                  <input type="text" name="applicationLinkFour" class="form-control has-icon "
                      data-icon="fa-solid fa-link" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Vacation Tax:</label>
                  <input type="number" name="vacationTaxFour"class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign">
              </div>
            </div>
            <div class="form-group main depositFiveNo d-none">
              <label class="fw-bold">Please enter the required move-in amounts:</label>
              <div class="form-group">
                  <label class="fw-bold">First Month:</label>
                  <input type="number" name="firstMonthFive" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Security Deposit:</label>
                  <input type="number" name="securityDepositFive" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Exit Cleaning Fee:</label>
                  <input type="number" name="exitCleaningFeeFive" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Fee:</label>
                  <input type="number" name="applicationFeeFive" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Link:</label>
                  <input type="text" name="applicationLinkFive" data-icon="fa-solid fa-link"
                      class="form-control has-icon" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Vacation Tax:</label>
                  <input type="number" name="vacationTaxFive" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
            </div>
            <div class="form-group main depositSixNo d-none">
              <label class="fw-bold">Please enter the required move-in amounts:</label>
              <div class="form-group">
                  <label class="fw-bold">First Month:</label>
                  <input type="number" name="firstMonthSix" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Security Deposit:</label>
                  <input type="number" name="securityDepositSix" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Exit Cleaning Fee:</label>
                  <input type="number" name="exitCleaningFeeSix" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Fee:</label>
                  <input type="number" name="applicationFeeSix" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Link:</label>
                  <input type="text" name="applicationLinkSix" data-icon="fa-solid fa-link"
                      class="form-control has-icon" required>
              </div>
            </div>
            <div class="form-group main depositSevenNo d-none">
              <label class="fw-bold">Please enter the required move-in amounts:</label>
              <div class="form-group">
                  <label class="fw-bold">First Month:</label>
                  <input type="number" name="firstMonthSeven" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Security Deposit:</label>
                  <input type="number" name="securityDepositSeven" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Fee:</label>
                  <input type="number" name="applicationFeeSeven" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" placeholder="" required>
              </div>
              <div class="form-group">
                  <label class="fw-bold">Application Link:</label>
                  <input type="text" name="applicationLinkSeven" data-icon="fa-solid fa-link"
                      class="form-control has-icon" required>
              </div>
            </div>
            <div class="form-group">
              <div class="form-group ">
                @php
                    $specialMoveRes = [
                        [
                            'name' => 'Yes',
                            'target' => '.specialMoveResNo',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                @endphp
                <label class="fw-bold">Would the landlord like to offer any move in specials for a tenant?
                </label>
                <select class="grid-picker" name="specialMoveOption" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($specialMoveRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main specialMoveResNo d-none">
                    <label class="fw-bold">What is the move in special?</label>
                    <input type="text" name="specialMove" id="" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="4">
            <h4>Landlord Prescreening Terms:</h4>
            @if ($auction->get->property_type == 'Residential Property')
              <span class="">
                <div class="form-group">
                    @php
                        $petsRes = [
                            [
                                'name' => 'Yes',
                                'target' => '.petsYesResNo',
                                'icon' => 'fa-regular fa-circle-check',
                            ],
                            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ];
                    @endphp
                    <label class="fw-bold">Will the landlord accept pets? </label>
                    <select class="grid-picker" name="petsOpt" style="justify-content: flex-start;">
                        <option value="">Select</option>
                        @foreach ($petsRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="from-group main petsYesResNo d-none">
                        <label class="fw-bold">Number of Pets Allowed:</label>
                        <input type="number" class="form-control has-icon" name="petsNumber"
                            data-icon="fa-solid fa-dog">
                        <label class="fw-bold">Acceptable Pet Types:</label>
                        <input type="text" class="form-control has-icon" name="petsType"
                            data-icon="fa-solid fa-dog">
                        <label class="fw-bold">Maximum Pet Weight:</label>
                        <input type="text" class="form-control has-icon" name="petsWeight"
                            data-icon="fa-solid fa-dog">
                        <label class="fw-bold">One-Time Pet Deposit or Monthly Pet Fee:</label>
                        <input type="text" class="form-control has-icon" name="petsFee"
                            data-icon="fa-solid fa-dog">
                        <label class="fw-bold">Pet Fee Amount:</label>
                        <input type="number" class="form-control has-icon" name="petsAmount"
                            data-icon="fa-solid fa-dog">
                        <label class="fw-bold">Is the Pet Fee Refundable or Non-Refundable?</label>
                        <input type="text" class="form-control has-icon" name="petsFund"
                            data-icon="fa-solid fa-dog">
                    </div>
                </div>
              </span>
            @endif
            @php
                $offer_occupants_accept = [
                    ['name' => '1', 'target' => ''],
                    ['name' => '2', 'target' => ''],
                    ['name' => '3', 'target' => ''],
                    ['name' => '4', 'target' => ''],
                    ['name' => '5', 'target' => ''],
                    ['name' => '6', 'target' => ''],
                    ['name' => '7', 'target' => ''],
                    ['name' => '8+', 'target' => ''],
                    ['name' => 'Other', 'target' => '.custom_occupantsNo'],
                ];
            @endphp
            <div class="form-group">
                <label class="fw-bold">How many occupants will the landlord accept?</label>
                <select class="grid-picker" name="offer_allowed_occupants" id="offer_allowed_occupants"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($offer_occupants_accept as $oca)
                        <option value="{{ $oca['name'] }}" data-target="{{ $oca['target'] }}"
                            class="card flex-row pt-0 pb-0" style="width:calc(10% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check" style="font-size:24px;position:relative;top:-5px;"></i>'>
                            {{ $oca['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main custom_occupantsNo d-none">
                    <label class="fw-bold" for="custom_occupants">How many occupants will the landlord
                        accept?</label>
                    <input type="text" name="custom_occupants" placeholder="" id="custom_occupants"
                        class="form-control has-icon hide_arrow" data-icon="fa-regular fa-circle-check">
                </div>
            </div>
            <div class="form-group">
                @php
                    $creditScoreRes = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                @endphp
                <label class="fw-bold">What is the minimum credit score rating the landlord will
                    accept?</label>
                <select class="grid-picker" name="creditScore" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($creditScoreRes as $item)
                        <option value="{{ $item }}" data-target="" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="fw-bold" for="offer_min_net_income">
                    What is the minimum net income a household must earn to qualify for the rental?
                </label>
                <input type="number" name="offer_min_net_income" id="offer_min_net_income" class="form-control has-icon hide_arrow" 
                    data-icon="fa-solid fa-dollar-sign">
            </div>
            <div class="form-group">
                @php
                    $evictionRes = [
                        ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        [
                            'name' => 'Depends on the circumstance',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                    ];
                @endphp
                <label class="fw-bold">Will the landlord accept a tenant with a prior eviction within the last 7 Years?</label>
                <select class="grid-picker" name="eviction" id=""
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($evictionRes as $item)
                        <option value="{{ $item['name'] }}" data-target="" class="card flex-row"
                            style="width:calc(30% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="fw-bold">Will the landlord accept a tenant with a prior felony within the last 7
                    Years?
                </label>
                <select class="grid-picker" name="offer_prior_felony" id="offer_prior_felony"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($evictionRes as $item)
                        <option value="{{ $item['name'] }}" data-target="" class="card flex-row"
                            style="width:calc(30% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
              <label class="fw-bold">
                If the tenant is represented by an agent, will the landlord offer the agent a commission?
              </label>
              @php
                $representedRes = [
                    ['target' => '.commission', 'name' => 'Yes', 'icon' => 'fa-regular fa-circle-check'],
                    ['target' => '', 'name' => 'No', 'icon' => 'fa-regular fa-circle-xmark'],
                    ['target' => '', 'name' => 'Not Applicable', 'icon' => 'fa-regular fa-circle-xmark'],
                ];
              @endphp
              <select name="landlordOfferCommission" id="represented" class="grid-picker"
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
            </div>
            <div class="form-group main commission d-none">
              <label class="fw-bold" for="custom_evicted">What is the commission offered to the tenant’s agent?</label>
              @php
                $representedOtherOpt = [
                    ['name' => '1 month’s rent', 'target' => ''],
                    ['name' => '50% of one month’s rent', 'target' => ''],
                    ['name' => '10% of the value of the lease', 'target' => ''],
                    ['name' => '6% of the value of the lease', 'target' => ''],
                    ['name' => 'Other', 'target' => '.compensateReqOtherNo'],
                ];
              @endphp
              <select name="commissionAmmountOffered" id="representedResYes" class="grid-picker"
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
              <div class="form-group main compensateReqOtherNo d-none">
                <label class="fw-bold" for="custom_evicted">What compensation is the landlord willing to pay the tenant’s agent?</label>
                <input type="text" name="landlordPaysAmount" class="form-control has-icon"
                  data-icon="fa-regular fa-circle-check" required>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="5">
            <div class="form-group">
              <label class="fw-bold">Offer Expires:</label>
              <input type="datetime-local" name="offerExpires" class="form-control has-icon"
                  data-icon="fa-regular fa-calendar-days" required>
            </div>
          </div>
          <div class="wizard-step" data-step="6">
            <div class="form-group">
                @php
                    $representationRes = [
                        [
                            'name' => 'Landlord Represented',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                        [
                            'name' => 'Landlord Not Represented',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                    ];
                @endphp
                <label class="fw-bold">Representation: </label>
                <select class="grid-picker" name="representation" id="listing_service_type"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($representationRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
          </div>
          <div class="wizard-step" data-step="7">
            @php
                $property_types = [['name' => 'Residential Property'], ['name' => 'Commercial Property']];
            @endphp
            <div class="form-group">
                <label class="fw-bold">Property Style: </label>
                <select class="grid-picker" name="property_type" id="property_type"
                    onchange="changePropertyType(this.value);" required>
                    <option value="">Select</option>
                    @foreach ($property_types as $item)
                        <option value="{{ $item['name'] }}" class="card flex-column"
                            style="width:calc(24% - 10px);" data-icon='<i class="fa-solid fa-hotel"></i>'>
                            {{ $item['name'] }}
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
                            ['name' => '½ Duplex', 'class' => 'residential-length'],
                            ['name' => '1/3 Triplex', 'class' => 'residential-length'],
                            ['name' => '1/4 Quadplex', 'class' => 'residential-length'],
                            ['name' => 'Dock-Rackominium', 'class' => 'residential-length'],
                            ['name' => 'Farm', 'class' => 'residential-length'],
                            ['name' => 'Garage Condo', 'class' => 'residential-length'],
                            ['name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
                            ['name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
                            ['name' => 'Unimproved Land', 'class' => 'residential-length'],
                            ['name' => 'Modular Home', 'class' => 'residential-length'],
                            ['name' => 'Duplex', 'class' => 'income-length'],
                            ['name' => 'Triplex', 'class' => 'income-length'],
                            ['name' => 'Quadplex', 'class' => 'income-length'],
                            ['name' => 'Five or More', 'class' => 'income-length'],
                            ['name' => 'Agriculture', 'class' => 'commercial-length'],
                            ['name' => 'Assembly Building', 'class' => 'commercial-length'],
                            ['name' => 'Business', 'class' => 'commercial-length'],
                            // Changing nisar
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
                            <option value="{{ $item['name'] }}" data-target=""
                                class="card flex-row {{ $item['class'] }}" style="width:calc(33.33% - 10px);"
                                data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step="8">
            <div class="form-group ">
                <label class="fw-bold">Leasing Space:</label>
                @php
                    $leasePropOption = [
                        ['name' => 'Entire Property', 'target' => ''],
                        ['name' => 'Single Room', 'target' => '.singleRoomRes'],
                    ];
                @endphp
                <select name="leasePropOption" id="auction_length" class="auction_length grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($leasePropOption as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <span class="resFields">
                    <div class="form-group main singleRoomRes d-none">
                        <label class="fw-bold">What is the size of the room the landlord intends to lease?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]"
                            data-icon="fa-solid fa-question">
                        <label class="fw-bold">Is there a private bathroom, or is it shared?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]"
                            data-icon="fa-solid fa-question">
                        <label class="fw-bold">How much storage space is available?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]"
                            data-icon="fa-solid fa-question">
                        <label class="fw-bold">Can tenants use common areas like the kitchen, living room, or
                            backyard?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]"
                            data-icon="fa-solid fa-question">
                        <label class="fw-bold">How is cleaning and maintenance of common areas managed?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]"
                            data-icon="fa-solid fa-question">
                        <label class="fw-bold">Are tenants allowed to have guests, and if so, are there any
                            restrictions?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]"
                            data-icon="fa-solid fa-question">
                        <label class="fw-bold">How are maintenance issues handled?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]"
                            data-icon="fa-solid fa-question">
                        <label class="fw-bold">How are the utilities split?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]"
                            data-icon="fa-solid fa-question">
                    </div>
                </span>
                <span class="commercialFields">
                    <div class="form-group main singleRoomRes d-none">
                        <label class="fw-bold">What is the size of the room the landlord intends to lease?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                        <label class="fw-bold">Is there a designated reception area?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                        <label class="fw-bold">How is the layout of the commercial space configured?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                        <label class="fw-bold">Are there specific zoning restrictions or permitted uses for the space?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                        <label class="fw-bold">How much storage space is available?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                        <label class="fw-bold">Are there any shared amenities, such as conference rooms or parking facilities?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                        <label class="fw-bold">How is cleaning and maintenance of common areas managed?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                        <label class="fw-bold">Are there specific hours of operation for the building, and is 24/7 access available?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                        <label class="fw-bold">How are maintenance issues and repairs handled for the commercial space?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                        <label class="fw-bold">How are the utilities split?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                        <label class="fw-bold">What types of businesses are neighboring tenants in the building or surrounding area?</label>
                        <input class="form-control has-icon" type="text" name="singleRoom[]" data-icon="fa-solid fa-question">
                    </div>
                </span>
            </div>
        </div>
        <div class="wizard-step" data-step="9">
            <div class="form-group">
                @php
                    $propConditions = [
                        ['name' => 'New Construction', 'target' => ''],
                        ['name' => 'Completely Updated: No updates needed', 'target' => ''],
                        ['name' => 'Semi-Updated: Needs minor updates', 'target' => ''],
                        ['name' => 'Not Updated: Requires a complete update', 'target' => ''],
                        ['name' => 'Other', 'target' => '.propOtherRes'],
                    ];
                @endphp
                <label class="fw-bold">Property Condition: </label>
                <select class="grid-picker" name="propConditions" id="housing_for_older_persons"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($propConditions as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main propOtherRes d-none">
                    <label class="fw-bold" for="">Property Condition: </label>
                    <input type="text" name="propOther" id="" placeholder=""
                        class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step="10">
          <span class="timerAuction">
            <div class="form-group">
              <label class="fw-bold" for="custom_terms">Rent Now Price:</label>
              <input type="number" name="rentNow" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" required>
            </div>
            <div class="form-group">
                <label class="fw-bold" for="custom_terms">Rent Now Price Per Sqft:</label>
                <input type="number" name="rentNowSqft" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" required>
              </div>
            <div class="form-group">
                <label class="fw-bold" for="custom_terms">Starting Price:</label>
                <input type="number" name="startingPrice" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" required>
            </div>
            <div class="form-group">
                <label class="fw-bold" for="custom_terms">Reserve Price:</label>
                <input type="number" name="reservePrice" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" required>
            </div>
          </span>
          <span class="traditional">
            <div class="form-group">
                <label class="fw-bold" for="custom_terms">Price:</label>
                <input type="number" name="price" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" required>
            </div>
            <div class="form-group">
                <label class="fw-bold" for="custom_terms">List Price Per Sqft:</label>
                <input type="number" name="list_price_per_sq" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" required>
            </div>
          </span>
          <div class="form-group">
            <label class="fw-bold" for="custom_terms">Lease Availability Date:</label>
            <input type="date" name="leaseDate" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" required>
          </div>
          <div class="form-group">
            @php
                $leaseTime = [
                    ['name' => '3 Months', 'target' => ''],
                    ['name' => '6 Months', 'target' => ''],
                    ['name' => '9 Months', 'target' => ''],
                    ['name' => '1 Year', 'target' => ''],
                    ['name' => '2 Years', 'target' => ''],
                    ['name' => '3-5 Years', 'target' => ''],
                    ['name' => '5+ Years', 'target' => ''],
                    ['name' => 'Month to Month', 'target' => ''],
                    ['name' => 'Other', 'target' => '.otherLeaseDuration'],
                ];
            @endphp
            <label class="fw-bold">Acceptable Lease Duration: </label>
            <select class="grid-picker" name="leaseTime[]" id="leaseTermRes"
                style="justify-content: flex-start;" required multiple>
                <option value="">Select</option>
                @foreach ($leaseTime as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(25% - 10px);"
                        data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
            <div class="form-group main otherLeaseDuration d-none">
                <div class="form-group">
                    <label class="fw-bold">Acceptable Lease Duration:</label>
                    <input type="text" name="other_lease_duration" id="other_lease_duration" class="form-control has-icon"
                        data-icon="fa-regular fa-calendar-days">
                </div>
            </div>
          </div>
          <span class="commercialFields">
            <div class="form-group">
                @php
                    $leaseTerms = [
                        ["name" => 'Absolute (Triple) Net', "target" => '' ],
                        ["name" => 'Gross Lease', "target" => '' ],
                        ["name" => 'Gross Percentages', "target" => '' ],
                        ["name" => 'Ground Lease', "target" => '' ],
                        ["name" => 'Lease Option', "target" => '' ],
                        ["name" => 'Modified Gross', "target" => '' ],
                        ["name" => 'Net Lease', "target" => '' ],
                        ["name" => 'Net Net', "target" => '' ],
                        ["name" => 'Pass Throughs', "target" => '' ],
                        ["name" => 'Purchase Option', "target" => '' ],
                        ["name" => 'Renewal Option', "target" => '' ],
                        ["name" => 'Sale-Leaseback', "target" => '' ],
                        ["name" => 'Seasonal', "target" => '' ],
                        ["name" => 'Special Available (CLO)', "target" => '' ],
                        ["name" => 'Varied Terms', "target" => '' ],
                        ["name" => 'Other', "target" => '.otherTermsLease' ]
                ];
                @endphp
                <label class="fw-bold"> Terms of Lease: </label>
                <select class="grid-picker" name="leaseTerms[]" id="leaseTermRes"
                    style="justify-content: flex-start;" required multiple>
                    <option value="">Select</option>
                    @foreach ($leaseTerms as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(25% - 10px);"
                            data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherTermsLease d-none">
                    <div class="form-group">
                        <label class="fw-bold">Terms of Lease:</label>
                        <input type="text" name="other_lease_terms" id="other_lease_terms" class="form-control has-icon"
                            data-icon="fa-regular fa-circle-check">
                    </div>
                </div>
            </div>
            <div class="form-group">
                    @php
                        $frequencyRes = [
                            ['name' => 'Annually', 'target' => ''],
                            ['name' => 'Monthly', 'target' => ''],
                        ];
                    @endphp
                    <label class="fw-bold">Select the frequency in which the Lease Amount is paid: </label>
                    <select class="grid-picker" name="frequency[]" style="justify-content: flex-start;" required
                        multiple>
                        <option value="">Select</option>
                        @foreach ($frequencyRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(25% - 10px);"
                                data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    @php
                        $tenant_pays = [
                            ['name' => 'Association Fees', 'target' => ''],
                            ['name' => 'Capital Expenses', 'target' => ''],
                            ['name' => 'Common Area Maintenance', 'target' => ''],
                            ['name' => 'Condominium Fees', 'target' => ''],
                            ['name' => 'Electricity', 'target' => ''],
                            ['name' => 'Gas', 'target' => ''],
                            ['name' => 'Liability Insurance', 'target' => ''],
                            ['name' => 'Parking Fee', 'target' => ''],
                            ['name' => 'Pro-rated', 'target' => ''],
                            ['name' => 'Property Insurance', 'target' => ''],
                            ['name' => 'Property Taxes', 'target' => ''],
                            ['name' => 'Reserves', 'target' => ''],
                            ['name' => 'Sewer', 'target' => ''],
                            ['name' => 'Trash Collection', 'target' => ''],
                            ['name' => 'Water', 'target' => ''],
                            // ['name' => 'Telephone', 'target' => ''],
                            // ['name' => 'Trash Collection', 'target' => ''],
                            // ['name' => 'Water', 'target' => ''],
                            // ['name' => 'None', 'target' => ''],
                            ['name' => 'Other', 'target' => '.tenantPaysOther'],
                        ];
                    @endphp
                    <label class="fw-bold">Tenant Pays:</label>
                    <select class="grid-picker" name="tenant_pays[]" multiple id="tenant_pays"
                        style="justify-content: flex-start;">
                        <option value="">Select</option>
                        @foreach ($tenant_pays as $tenant_pay)
                            <option value="{{ $tenant_pay['name'] }}"
                                data-target="{{ $tenant_pay['target'] }}" class="card flex-row"
                                style="width:calc(33.3% - 10px);"
                                data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                {{ $tenant_pay['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-group main tenantPaysOther d-none">
                        <label class="fw-bold">Tenant Pays:</label>
                        <input type="text" name="tenantPaysOther" class="form-control has-icon"
                            data-icon="fa-solid fa-ruler-combined">
                    </div>
                </div>
                <div class="form-group">
                    @php
                        $landlordPays = [
                            ['name' => 'Cable TV', 'target' => ''],
                            ['name' => 'Electricity', 'target' => ''],
                            ['name' => 'Gas', 'target' => ''],
                            ['name' => 'Grounds Care', 'target' => ''],
                            ['name' => 'Insurance', 'target' => ''],
                            ['name' => 'Internet', 'target' => ''],
                            ['name' => 'Laundry', 'target' => ''],
                            ['name' => 'Management', 'target' => ''],
                            ['name' => 'Pest Control', 'target' => ''],
                            ['name' => 'Pool Maintenance', 'target' => ''],
                            ['name' => 'Recreational', 'target' => ''],
                            ['name' => 'Repairs', 'target' => ''],
                            ['name' => 'Security', 'target' => ''],
                            ['name' => 'Sewer', 'target' => ''],
                            ['name' => 'Taxes', 'target' => ''],
                            ['name' => 'Telephone', 'target' => ''],
                            ['name' => 'Trash Collection', 'target' => ''],
                            ['name' => 'Water', 'target' => ''],
                            ['name' => 'None', 'target' => ''],
                            ['name' => 'Other', 'target' => '.landlordPaysOther'],
                        ];
                    @endphp
                    <label class="fw-bold">Landlord Pays:</label>
                    <select class="grid-picker" name="wnerPays[]" multiple
                        style="justify-content: flex-start;">
                        <option value="">Select</option>
                        @foreach ($landlordPays as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-group main landlordPaysOther d-none">
                        <label class="fw-bold">Landlord Pays:</label>
                        <input type="text" name="landlordPaysOther" id="owner_pays"
                            class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                    </div>
                </div>
          </span>
          <span class="resFields">
            <div class="form-group">
                @php
                    $frequencyCommercial = [
                        ['name' => 'Annually', 'target' => ''],
                        ['name' => 'Daily', 'target' => ''],
                        ['name' => 'Monthly', 'target' => ''],
                        ['name' => 'Seasonally', 'target' => ''],
                    ];
                @endphp
                <label class="fw-bold">Select the frequency in which the Lease Amount is paid: </label>
                <select class="grid-picker" name="frequency[]" style="justify-content: flex-start;" required
                    multiple>
                    <option value="">Select</option>
                    @foreach ($frequencyCommercial as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(25% - 10px);"
                            data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
          </span>
          <span class="resFields">
            <div class="form-group">
                @php
                    $rentRes = [
                        ['name' => 'Cable TV', 'target' => ''],
                        ['name' => 'Electricity', 'target' => ''],
                        ['name' => 'Gas', 'target' => ''],
                        ['name' => 'Grounds Care', 'target' => ''],
                        ['name' => 'Insurance', 'target' => ''],
                        ['name' => 'Internet', 'target' => ''],
                        ['name' => 'Laundry', 'target' => ''],
                        ['name' => 'Management', 'target' => ''],
                        ['name' => 'Pest Control', 'target' => ''],
                        ['name' => 'Pool Maintenance', 'target' => ''],
                        ['name' => 'Recreational', 'target' => ''],
                        ['name' => 'Repairs', 'target' => ''],
                        ['name' => 'Security', 'target' => ''],
                        ['name' => 'Sewer', 'target' => ''],
                        ['name' => 'Taxes', 'target' => ''],
                        ['name' => 'Telephone', 'target' => ''],
                        ['name' => 'Trash Collection', 'target' => ''],
                        ['name' => 'Water', 'target' => ''],
                        ['name' => 'None', 'target' => ''],
                        ['name' => 'Other', 'target' => '.rentOtherRes'],
                    ];
                @endphp
                <label class="fw-bold">Rent Includes:</label>
                <select class="grid-picker" name="rent[]" multiple id="tenant_pays"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($rentRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main rentOtherRes d-none">
                    <label class="fw-bold">Rent Includes:</label>
                    <input type="text" name="rentOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
          </span>
          <div class="form-group">
            @php
              $leaseTermRes = [
                ['name' => 'First, Last, and Security', 'target' => '.depositOne'],
                [
                    'name' => 'First, Last, Security Deposit, Exit Cleaning Fee, & Application Fee',
                    'target' => '.depositSecond'
                ],
                [
                    'name' =>
                        'First, Last, Security Deposit, Pet Deposit, Exit Cleaning Fee, & Application Fee',
                    'target' => '.depositThird',
                ],
                [
                    'name' =>
                        'First, Last, Security Deposit, Exit Cleaning Fee, Application Fee, Vacation Tax',
                    'target' => '.depositFour',
                ],
                [
                    'name' =>
                        'First, Security Deposit, Exit Cleaning Fee, Application Fee, & Vacation Tax',
                    'target' => '.depositFive',
                ],
                [
                    'name' => 'First, Security, Exit Cleaning Fee & Application Fee',
                    'target' => '.depositSix',
                ],
                ['name' => 'First, Security, & Application Fee', 'target' => '.depositSeven'],
                ['name' => 'Other', 'target' => '.custom_input'],
              ];
            @endphp
          </div>
          <div class="form-group ">
            <label class="fw-bold">What is required at move-in?</label>
            <select class="grid-picker" name="required_at_move_in" id=""
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($leaseTermRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                    </option>
                @endforeach
            </select>
          </div>
          <div class="custom_input main d-none">
            <div class="form-group">
                <label class="fw-bold">What is required at move-in?</label>
                <input type="text" name="leaseTermOther" class="form-control has-icon"
                    data-icon="fa-regular fa-circle-check">
            </div>
            <div class="form-group">
                <label class="fw-bold">Please enter the required move-in amounts:</label>
                <input type="number" name="leaseTermOther" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign">
            </div>
          </div>
          <div class="form-group main depositOne d-none">
            <label class="fw-bold">Please enter the required move-in amounts:</label>
            <div class="form-group">
                <label class="fw-bold">First Month:</label>
                <input type="number" name="firstMonthDeposit" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Last Month:</label>
                <input type="number" name="lastMonthDeposit" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Security Deposit:</label>
                <input type="number" name="securityDeposit" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
          </div>
          <div class="form-group main depositSecond d-none">
            <label class="fw-bold">Please enter the required move-in amounts:</label>
            <div class="form-group">
                <label class="fw-bold">First Month:</label>
                <input type="number" name="firstMonthSecond" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Last Month:</label>
                <input type="number" name="lastMonthSecond" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Security Deposit:</label>
                <input type="number" name="securityDepositSecond" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Exit Cleaning Fee:</label>
                <input type="number" name="exitCleaningFeeSecond" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Fee:</label>
                <input type="number" name="applicationFeeSecond" data-type="cities" id="cities"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder=""
                    required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Link: </label>
                <input type="number" name="applicationLinkSecond" data-type="cities" id="cities"
                    class="form-control has-icon search_places" data-icon="fa-solid fa-link" placeholder=""
                    required>
            </div>
          </div>
          <div class="form-group main depositThird d-none">
            <label class="fw-bold">Please enter the required move-in amounts:</label>
            <div class="form-group">
                <label class="fw-bold">First Month:</label>
                <input type="number" name="firstMonthThird" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Last Month:</label>
                <input type="number" name="lastMonthThird" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Security Deposit:</label>
                <input type="number" name="securityDepositThird" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Pet Deposit:</label>
                <input type="number" name="petDepositThird" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Exit Cleaning Fee:</label>
                <input type="number" name="exitCleaningFeeThird" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Fee:</label>
                <input type="number" name="applicationFeeThird" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Link:</label>
                <input type="text" name="applicationLinkThird" class="form-control has-icon "
                    data-icon="fa-solid fa-link" placeholder="" required>
            </div>
          </div>
          <div class="form-group main depositFour d-none">
            <label class="fw-bold">Please enter the required move-in amounts:</label>
            <div class="form-group">
                <label class="fw-bold">First Month:</label>
                <input type="number" name="firstMonthFour" class="form-control has-icon "
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Last Month:</label>
                <input type="number" name="lastMonthFour" class="form-control has-icon "
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Security Deposit:</label>
                <input type="number" name="securityDepositFour" class="form-control has-icon "
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Exit Cleaning Fee:</label>
                <input type="number" name="exitCleaningFeeFour" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Fee:</label>
                <input type="number" name="applicationFeeFour" class="form-control has-icon "
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Link:</label>
                <input type="text" name="applicationLinkFour" class="form-control has-icon "
                    data-icon="fa-solid fa-link" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Vacation Tax:</label>
                <input type="number" name="vacationTaxFour"class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign">
            </div>
          </div>
          <div class="form-group main depositFive d-none">
            <label class="fw-bold">Please enter the required move-in amounts:</label>
            <div class="form-group">
                <label class="fw-bold">First Month:</label>
                <input type="number" name="firstMonthFive" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Security Deposit:</label>
                <input type="number" name="securityDepositFive" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Exit Cleaning Fee:</label>
                <input type="number" name="exitCleaningFeeFive" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Fee:</label>
                <input type="number" name="applicationFeeFive" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Link:</label>
                <input type="text" name="applicationLinkFive" data-icon="fa-solid fa-link"
                    class="form-control has-icon" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Vacation Tax:</label>
                <input type="number" name="vacationTaxFive" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
          </div>
          <div class="form-group main depositSix d-none">
            <label class="fw-bold">Please enter the required move-in amounts:</label>
            <div class="form-group">
                <label class="fw-bold">First Month:</label>
                <input type="number" name="firstMonthSix" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Security Deposit:</label>
                <input type="number" name="securityDepositSix" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Exit Cleaning Fee:</label>
                <input type="number" name="exitCleaningFeeSix" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Fee:</label>
                <input type="number" name="applicationFeeSix" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Link:</label>
                <input type="text" name="applicationLinkSix" data-icon="fa-solid fa-link"
                    class="form-control has-icon" required>
            </div>
          </div>
          <div class="form-group main depositSeven d-none">
            <label class="fw-bold">Please enter the required move-in amounts:</label>
            <div class="form-group">
                <label class="fw-bold">First Month:</label>
                <input type="number" name="firstMonthSeven" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Security Deposit:</label>
                <input type="number" name="securityDepositSeven" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Fee:</label>
                <input type="number" name="applicationFeeSeven" class="form-control has-icon"
                    data-icon="fa-solid fa-dollar-sign" placeholder="" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Application Link:</label>
                <input type="text" name="applicationLinkSeven" data-icon="fa-solid fa-link"
                    class="form-control has-icon" required>
            </div>
          </div>
          <div class="form-group">
            {{-- <div class="form-group">
              @php
                  $timeFrame = [
                      ['name' => '12 hours', 'target' => ''],
                      ['name' => '24 hours (1 day)', 'target' => ''],
                      ['name' => '36 hours', 'target' => ''],
                      ['name' => '48 hours (2 days)', 'target' => ''],
                      ['name' => '60 hours', 'target' => ''],
                      ['name' => '72 hours (3 days)', 'target' => ''],
                      ['name' => '96 hours (4 days)', 'target' => ''],
                      ['name' => '120 hours (5 days)', 'target' => ''],
                      ['name' => '144 hours (6 days)', 'target' => ''],
                      ['name' => '168 hours (7 days)', 'target' => ''],
                  ];
              @endphp
              <label class="fw-bold">Time Frame Allocated to Respond to Multiple Offers:</label>
              <select class="grid-picker" name="timeFrameMultiple" style="justify-content: flex-start;"
                  required multiple>
                  <option value="">Select</option>
                  @foreach ($timeFrame as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(25% - 10px);"
                          data-icon='<i class="fa-regular fa-clock"></i>'>
                          {{ $item['name'] }}
                      </option>
                  @endforeach
              </select>
            </div> --}}
            <div class="form-group ">
              @php
                  $specialMoveRes = [
                      [
                          'name' => 'Yes',
                          'target' => '.specialMoveRes',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                  ];
              @endphp
              <label class="fw-bold">Would the landlord like to offer any move in specials for a tenant?
              </label>
              <select class="grid-picker" name="specialMoveOption" style="justify-content: flex-start;"
                  required>
                  <option value="">Select</option>
                  @foreach ($specialMoveRes as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                      </option>
                  @endforeach
              </select>
              <div class="form-group main specialMoveRes d-none">
                  <label class="fw-bold">What is the move in special?</label>
                  <input type="text" name="specialMove" id="" class="form-control has-icon"
                      data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
        </div>
        <div class="wizard-step" data-step="11">
            <h4>Landlord Prescreening Terms:</h4>
            <span class="resFields">
                <div class="form-group">
                    @php
                        $petsRes = [
                            [
                                'name' => 'Yes',
                                'target' => '.petsYesRes',
                                'icon' => 'fa-regular fa-circle-check',
                            ],
                            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ];
                    @endphp
                    <label class="fw-bold">Will the landlord accept pets? </label>
                    <select class="grid-picker" name="petsOpt" style="justify-content: flex-start;">
                        <option value="">Select</option>
                        @foreach ($petsRes as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="from-group main petsYesRes d-none">
                        <label class="fw-bold">Number of Pets Allowed:</label>
                        <input type="number" class="form-control has-icon" name="petsNumber"
                            data-icon="fa-solid fa-dog">
                        <label class="fw-bold">Acceptable Pet Types:</label>
                        <input type="text" class="form-control has-icon" name="petsType"
                            data-icon="fa-solid fa-dog">
                        <label class="fw-bold">Maximum Pet Weight:</label>
                        <input type="text" class="form-control has-icon" name="petsWeight"
                            data-icon="fa-solid fa-dog">
                        <label class="fw-bold">One-Time Pet Deposit or Monthly Pet Fee:</label>
                        <input type="text" class="form-control has-icon" name="petsFee"
                            data-icon="fa-solid fa-dog">
                        <label class="fw-bold">Pet Fee Amount:</label>
                        <input type="number" class="form-control has-icon" name="petsAmount"
                            data-icon="fa-solid fa-dog">
                        <label class="fw-bold">Is the Pet Fee Refundable or Non-Refundable?</label>
                        <input type="text" class="form-control has-icon" name="petsFund"
                            data-icon="fa-solid fa-dog">
                    </div>
                </div>
            </span>
            @php
                $offer_occupants_accept = [
                    ['name' => '1', 'target' => ''],
                    ['name' => '2', 'target' => ''],
                    ['name' => '3', 'target' => ''],
                    ['name' => '4', 'target' => ''],
                    ['name' => '5', 'target' => ''],
                    ['name' => '6', 'target' => ''],
                    ['name' => '7', 'target' => ''],
                    ['name' => '8+', 'target' => ''],
                    ['name' => 'Other', 'target' => '.custom_occupants'],
                ];
            @endphp
            <div class="form-group">
                <label class="fw-bold">How many occupants will the landlord accept?</label>
                <select class="grid-picker" name="offer_allowed_occupants" id="offer_allowed_occupants"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($offer_occupants_accept as $oca)
                        <option value="{{ $oca['name'] }}" data-target="{{ $oca['target'] }}"
                            class="card flex-row pt-0 pb-0" style="width:calc(10% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check" style="font-size:24px;position:relative;top:-5px;"></i>'>
                            {{ $oca['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main custom_occupants d-none">
                    <label class="fw-bold" for="custom_occupants">How many occupants will the landlord
                        accept?</label>
                    <input type="text" name="custom_occupants" placeholder="" id="custom_occupants"
                        class="form-control has-icon hide_arrow" data-icon="fa-regular fa-circle-check">
                </div>
            </div>
            <div class="form-group">
                @php
                    $creditScoreRes = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                @endphp
                <label class="fw-bold">What is the minimum credit score rating the landlord will
                    accept?</label>
                <select class="grid-picker" name="creditScore" style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($creditScoreRes as $item)
                        <option value="{{ $item }}" data-target="" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="fw-bold" for="offer_min_net_income">
                    What is the minimum net income a household must earn to qualify for the rental?
                </label>
                <input type="number" name="offer_min_net_income" id="offer_min_net_income" class="form-control has-icon hide_arrow" 
                    data-icon="fa-solid fa-dollar-sign">
            </div>
            <div class="form-group">
                @php
                    $evictionRes = [
                        ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        [
                            'name' => 'Depends on the circumstance',
                            'target' => '',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                    ];
                @endphp
                <label class="fw-bold">Will the landlord accept a tenant with a prior eviction within the last 7 Years?</label>
                <select class="grid-picker" name="eviction" id=""
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($evictionRes as $item)
                        <option value="{{ $item['name'] }}" data-target="" class="card flex-row"
                            style="width:calc(30% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="fw-bold">Will the landlord accept a tenant with a prior felony within the last 7
                    Years?
                </label>
                <select class="grid-picker" name="offer_prior_felony" id="offer_prior_felony"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($evictionRes as $item)
                        <option value="{{ $item['name'] }}" data-target="" class="card flex-row"
                            style="width:calc(30% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="wizard-step" data-step='12'>
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
                    ['target' => '.other_bedrooms', 'name' => 'Other'],
                ];

            @endphp
            <div class="form-group">
                <label class="fw-bold">Bedrooms:</label>
                <select class="grid-picker" name="bedroom" style="justify-content: center;" required>
                    <option value="">Select</option>
                    @foreach ($bedrooms as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column" style="width:calc(15% - 10px);"
                            data-icon='<i class="fa-solid fa-bed"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main other_bedrooms d-none">
                    <label class="fw-bold" for="other_bedrooms">Bedrooms:</label>
                    <input type="number" name="other_bedrooms" id="other_bedrooms"
                        class="form-control has-icon" data-icon="fa-solid fa-bed" required>
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='13'>
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
                    ['target' => '.other_bathrooms', 'name' => 'Other'],
                ];
            @endphp
            <div class="form-group">
                <label class="fw-bold">Bathrooms:</label>
                <select class="grid-picker" name="bathrooms" id="bathrooms" style="justify-content: center;"
                    required>
                    <option value="">Select</option>
                    @foreach ($bathrooms as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-column" style="width:calc(15% - 10px);"
                            data-icon='<i class="fa-solid fa-bath"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main other_bathrooms d-none">
                    <label class="fw-bold" for="other_bathrooms">Bathrooms:</label>
                    <input type="number" name="other_bathrooms" class="form-control has-icon" data-icon="fa-solid fa-bath" required>
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='14'>
            <div class="form-group">
                <label for="heated_sqft" class="fw-bold">Heated Sqft:</label>
                <input type="text" name="heated_sqft" id="heated_sqft"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
            </div>
            <div class="form-group commercial_show">
                <label for="heated_sqft" class="fw-bold"> Net Leasable Sqft:</label>
                <input type="text" name="net_leasable_sqft" id="net_leasable_sqft"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
            </div>
            <div class="form-group">
                <label for="sqft_total" class="fw-bold"> Total Sqft:</label>
                <input type="text" name="sqft_total" id="sqft_total"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
            </div>
            @php
                $heated_sources = [
                    ['target' => '', 'name' => 'Appraisal'],
                    ['target' => '', 'name' => 'Building'],
                    ['target' => '', 'name' => 'Measured'],
                    ['target' => '', 'name' => 'Owner Provided'],
                    ['target' => '', 'name' => 'Public Records'],
                    ['target' => '.otherSqftRes', 'name' => 'Other'],
                ];

            @endphp
            <div class="form-group">
                <label class="fw-bold">Sqft Heated Source:</label>
                <select class="grid-picker" name="heated_source" style="justify-content: left;" required>
                    <option value="">Select</option>
                    @foreach ($heated_sources as $heated_source)
                        <option value="{{ $heated_source['name'] }}"
                            data-target="{{ $heated_source['target'] }}" class="card flex-row"
                            style="width:calc(25% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check "></i>'>
                            {{ $heated_source['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherSqftRes d-none">
                    <label for="sqft_total" class="fw-bold"> Sqft Heated Source:</label>
                    <input type="text" name="otherSqft" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='15'>
            <h4>Land Information:</h4>
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

            <div class="form-group ">
                <label class="fw-bold">Total Acreage:</label>
                <select class="grid-picker" name="total_acreage" id="total_acreage"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($total_acreages as $total_acreage)
                        <option value="{{ $total_acreage['name'] }}"
                            data-target="{{ $total_acreage['target'] }}" class="card flex-column"
                            style="width:calc(25% - 10px);"
                            data-icon='<i class="fa-solid fa-ruler-combined"></i>'>
                            {{ $total_acreage['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="fw-bold">Year Built:</label>
                <input type="text" name="yearBuilt" class="form-control has-icon"
                    data-icon="fa-regular fa-calendar-days">
            </div>
            <div class="form-group">
                <label class="fw-bold">Lot Size:</label>
                <input type="text" name="lotSize" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Legal Subdivision Name:</label>
                <input type="text" name="legarName" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined" required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Tax ID (Parcel Number) :</label>
                <input type="text" name="taxId" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined"  required>
            </div>
            <div class="form-group">
                <label class="fw-bold">Flood Zone Code:</label>
                <input type="text" name="zoneCode" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined"  required>
            </div>
            <span class="commercialFields">
                <div class="form-group">
                    <label class="fw-bold">Zoning:</label>
                    <input type="text" name="zoning" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check"  required>
                </div>
                <div class="form-group">
                    <label class="fw-bold">Tax Year:</label>
                    <input type="text" name="tax_year" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check"  required>
                </div>
                <div class="form-group">
                    <label class="fw-bold">Taxes (Annual Amount):</label>
                    <input type="text" name="taxes_annual" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign"  required>
                </div>
                <div class="form-group">
                    <label class="fw-bold">Legal Description:</label>
                    <input type="text" name="legal_description" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check"  required>
                </div>
                <div class="form-group">
                    <label class="fw-bold">Total Number of Parcels:</label>
                    <input type="text" name="no_of_parcels" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check"  required>
                </div>
                @php
                $additional = [
                    ['name' => 'Yes', 'target' => '.additionalTax', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                ];
                @endphp
                <div class="form-group ">
                    <label class="fw-bold">Additional Parcels</label>
                    <select class="grid-picker" name="additional_parcels" id="additional_parcels"
                        style="justify-content: flex-start;" required>
                        <option value="">Select</option>
                        @foreach ($additional as $item)
                            <option value="{{ $item['name'] }}"
                                data-target="{{ $item['target'] }}" class="card flex-column"
                                style="width:calc(25% - 10px);"
                                data-icon='<i class="{{$item['icon']}}"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group main additionalTax  d-none">
                    <label class="fw-bold">Additional Tax ID’s:</label>
                    <input type="text" name="additional_tax_id" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check"  required>
                </div>
            </span>
        </div>
        <div class="wizard-step" data-step='16'>
            @php
                $furnishings = [
                    ['name' => 'Furnished', 'target' => '', 'icon' => ''],
                    ['name' => 'Optional', 'target' => '', 'icon' => ''],
                    ['name' => 'Partial', 'target' => '', 'icon' => ''],
                    ['name' => 'Turnkey', 'target' => '', 'icon' => ''],
                    ['name' => 'Unfurnished', 'target' => '', 'icon' => ''],
                ];
            @endphp
            <div class="form-group">
                <label class="fw-bold">Furnishings:</label>
                <select class="grid-picker" name="furnishings" id="furnishings"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($furnishings as $furnishing)
                        <option value="{{ $furnishing['name'] }}" data-target="{{ $furnishing['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $furnishing['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="wizard-step" data-step='17'>
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
                    ['name' => 'Other', 'target' => '.appliancesOtherRes'],
                ];
                @endphp
            <div class="form-group">
                <label class="fw-bold">Appliances:</label>
                <select class="grid-picker" name="appliances[]" id="appliances"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($appliances as $appliance)
                        <option value="{{ $appliance['name'] }}" data-target="{{ $appliance['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $appliance['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main appliancesOtherRes d-none">
                    <label class="fw-bold">Appliances:</label>
                    <input type="text" name="appliancesOther" id="total_floors" placeholder=""
                        class="form-control has-icon" data-icon="fa-regular fa-circle-check">
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
            <span class="resFields">
                <div class="form-group">
                    <label class="fw-bold">Fireplace:</label>
                    <select class="grid-picker" name="firePlace" id="carport"
                        style="justify-content: flex-start;" required>
                        <option value="">Select</option>
                        @foreach ($yes_or_nos as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </span>
        </div>
        <div class="wizard-step" data-step='18'>
            <span class="resFields">
            <div class="form-group">
                <label class="fw-bold">
                  Amenities and Property Features:
                </label>
                @php
                  $amenitiesFeatureRes = [
                      ['target' => '', 'name' => 'Garage'],
                      ['target' => '', 'name' => 'Carport'],
                      ['target' => '', 'name' => 'Pool'],
                      ['target' => '', 'name' => 'Waterfront'],
                      ['target' => '', 'name' => 'In-Unit Laundry'],
                      ['target' => '', 'name' => 'On-site Laundry'],
                      ['target' => '', 'name' => 'Washer and Dryer Hookup'],
                      ['target' => '', 'name' => 'Washer and Dryer'],
                      ['target' => '', 'name' => 'Covered Carport'],
                      ['target' => '', 'name' => 'First Floor Unit'],
                      ['target' => '', 'name' => 'Elevator'],
                      ['target' => '', 'name' => 'Pet Friendly'],
                      ['target' => '', 'name' => 'Balcony/Patio'],
                      ['target' => '', 'name' => 'Fitness Center/Gym'],
                      ['target' => '', 'name' => 'Central Heating'],
                      ['target' => '', 'name' => 'Central Air Conditioning'],
                      ['target' => '', 'name' => 'Fireplace'],
                      ['target' => '', 'name' => 'Walk-in Closet'],
                      ['target' => '', 'name' => 'Hardwood Floors'],
                      ['target' => '', 'name' => 'Tile Floors'],
                      ['target' => '', 'name' => 'Carpet Floors '],
                      ['target' => '', 'name' => 'Security System'],
                      ['target' => '', 'name' => 'Gated Community'],
                      ['target' => '', 'name' => 'HOA Community'],
                      ['target' => '', 'name' => '55 and Over Community'],
                      ['target' => '', 'name' => 'Specific School District'],
                      ['target' => '', 'name' => 'Accessibility Features'],
                      ['target' => '', 'name' => 'On-site Maintenance'],
                      ['target' => '', 'name' => 'On-site Management'],
                      ['target' => '', 'name' => 'Outdoor Space'],
                      ['target' => '', 'name' => 'Playground'],
                      ['target' => '', 'name' => 'Clubhouse'],
                      ['target' => '', 'name' => 'Storage Space'],
                      ['target' => '', 'name' => 'Study/Den/Office'],
                      ['target' => '', 'name' => 'Updated Kitchen'],
                      ['target' => '', 'name' => 'Updated Bathroom'],
                      ['target' => '.otherAmenitiesFeatureRes', 'name' => 'Other'],
                  ];
                @endphp
                <select name="amenities[]" id="negotiable_terms" class="grid-picker"
                  style="justify-content: flex-start;" multiple required>
                  <option value=""></option>
                  @foreach ($amenitiesFeatureRes as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-column" style="width:calc(20% - 10px);"
                      data-icon='<i class="fa-regular fa-circle-check" style="font-size:24px;"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group main otherAmenitiesFeatureRes d-none">
                <label class="fw-bold" for="custom_negotiable_terms"> Amenities and Property Features:
                </label>
                <input type="text" name="otherAmenities" id="custom_negotiable_terms" placeholder=""
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              </span>
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
                <div class="form-group">
                  <label class="fw-bold">Amenities and Property Features:</label>
                  <select class="grid-picker" name="amenities[]" id="appliances"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($amenitiesCommercial as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
                        style="width:calc(33.3% - 10px);">
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group main otherAmenitiesCommercial d-none">
                    <label class="fw-bold">Amenities and Property Features:</label>
                    <input type="text" class="form-control has-icon" name="otherAmenities"
                      data-icon="fa-regular fa-circle-check" required />
                  </div>
                </div>
              </span>
        </div>
        <div class="wizard-step" data-step='19'>
            @php
                $accessibilityFeaturesRes = [
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
                <select class="grid-picker" name="features[]" multiple
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($accessibilityFeaturesRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="wizard-step" data-step='20'>
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
                    ['name' => 'Other', 'target' => '.interiorFeatureOtherRes'],
                ];
                @endphp
            <div class="form-group">
                <label class="fw-bold">Interior Features:</label>
                <select class="grid-picker" name="interiorFeatures[]" multiple id="tenant_pays"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($interior_features as $interior_feature)
                        <option value="{{ $interior_feature['name'] }}"
                            data-target="{{ $interior_feature['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $interior_feature['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main interiorFeatureOtherRes d-none">
                    <label class="fw-bold">Interior Features:</label>
                    <input type="text" name="interiorFeatureOther" id="floors_in_unit" placeholder=""
                        class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='21'>
            <h4>Additional Rooms</h4>
            @php
                $additional_rooms = [
                    ['name' => 'Attic', 'target' => ''],
                    ['name' => 'Bonus Room', 'target' => ''],
                    ['name' => 'Breakfast Room Separate', 'target' => ''],
                    ['name' => 'Den/Library/Office', 'target' => ''],
                    ['name' => 'Family Room', 'target' => ''],
                    ['name' => 'Florida Room', 'target' => ''],
                    ['name' => 'Formal Dining Room Separate', 'target' => ''],
                    ['name' => 'Formal Living Room Separate', 'target' => ''],
                    ['name' => 'Garage Apartment', 'target' => ''],
                    ['name' => 'Great Room', 'target' => ''],
                    ['name' => 'Inside Utility', 'target' => ''],
                    ['name' => 'Interior In-Law Suite w/Private Entry', 'target' => ''],
                    ['name' => 'Interior In-Law Suite w/No Private Entry', 'target' => ''],
                    ['name' => 'Loft', 'target' => ''],
                    ['name' => 'Media Room', 'target' => ''],
                    ['name' => 'Storage Rooms', 'target' => ''],
                    ['name' => 'Other', 'target' => '.roomOtherRes'],
                ];
            @endphp
            <div class="form-group">
                <label class="fw-bold">Additional Rooms:</label>
                <select class="grid-picker" name="additional_rooms[]" multiple id="additional_rooms"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($additional_rooms as $additional_room)
                        <option value="{{ $additional_room['name'] }}"
                            data-target="{{ $additional_room['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $additional_room['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main roomOtherRes d-none">
                    <label class="fw-bold">Additional Rooms:</label>
                    <input type="text" name="roomOther" id="number_of_buildings" placeholder=""
                        class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='22'>
            @php
                $laundryRes = [
                    ['name' => 'Common Area', 'target' => ''],
                    ['name' => 'Corridor Access', 'target' => ''],
                    ['name' => 'Electric Dryer Hookup', 'target' => ''],
                    ['name' => 'Gas Dryer Hookup', 'target' => ''],
                    ['name' => 'Outside', 'target' => ''],
                    ['name' => 'Same Floor As Condo Unit', 'target' => ''],
                    ['name' => 'Upper Floor', 'target' => ''],
                    ['name' => 'Washer Hookup', 'target' => ''],
                    ['name' => 'Inside', 'target' => ''],
                    ['name' => 'In Garage', 'target' => ''],
                    ['name' => 'In Kitchen', 'target' => ''],
                    ['name' => 'Laundry Chute', 'target' => ''],
                    ['name' => 'Laundry Closet', 'target' => ''],
                    ['name' => 'Laundry Room', 'target' => ''],
                    ['name' => 'None', 'target' => ''],
                    ['name' => 'Other', 'target' => '.laundryOtherRes'],
                ];
            @endphp
            <div class="form-group">
                <label class="fw-bold">Laundry Features:</label>
                <select class="grid-picker" name="laundry[]" multiple
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($laundryRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-regular fa-circle-check"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main laundryOtherRes d-none">
                    <label class="fw-bold">Laundry Features: </label>
                    <input type="text" name="laundryOther" id="number_of_buildings" placeholder=""
                        class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='23'>
            <div class="form-group">
                <label class="fw-bold">How many floors are in the property? </label>
                <input type="text" name="propFloors" id="number_of_buildings" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-hotel">
            </div>
            <div class="form-group">
                <label class="fw-bold">What floor number is the property on?</label>
                <input type="text" name="floorNumber" id="floors_in_unit" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-hotel">
            </div>

            <div class="form-group">
                <label class="fw-bold">How many floors are in the entire building? </label>
                <input type="text" name="totalFloors" id="total_floors" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-hotel">
            </div>
            <span class="commercialFields">
                <div class="form-group">
                    <label class="fw-bold">Total Number of Buildings: </label>
                    <input type="text" name="totalBuildings" placeholder=""
                        class="form-control has-icon" data-icon="fa-solid fa-hotel">
                </div>
            </span>
            <div class="form-group">
                <label class="fw-bold">Building Elevator:</label>
                <select class="grid-picker" name="building_elevator" id="building_elevator"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="fa-solid fa-hotel"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="wizard-step" data-step='24'>
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
                    ['name' => 'Reclaimed Wood', 'target' => ''],
                    ['name' => 'Recycled/Composite Flooring', 'target' => ''],
                    ['name' => 'Slate', 'target' => ''],
                    ['name' => 'Terrazzo', 'target' => ''],
                    ['name' => 'Tile', 'target' => ''],
                    ['name' => 'Travertine', 'target' => ''],
                    ['name' => 'Vinyl', 'target' => ''],
                    ['name' => 'Wood', 'target' => ''],
                    ['name' => 'Other', 'target' => '.floorCoveringOtherRes'],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Floor Covering:</label>
                <select class="grid-picker" name="floor_covering[]" id="floor_covering"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($floor_coverings as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main  floorCoveringOtherRes d-none">
                    <label class="fw-bold">Floor Covering:</label>
                    <input type="text" name="floorConvringOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='25'>
            <h4>Room Details:</h4>
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
                    ['name' => 'Dinette', 'target' => ''],
                    ['name' => 'Garage Room', 'target' => ''],
                    ['name' => 'Garage Apartment,', 'target' => ''],
                    ['name' => 'Double Primary Bedroom', 'target' => ''],
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
                    ['name' => 'Primary Bathroom', 'target' => ''],
                    ['name' => 'Primary Bedroom', 'target' => ''],
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
                <select class="grid-picker" name="room_type[]" id="room_typeRes" onChange="roomFtn();"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($room_types as $room_type)
                        <option value="{{ $room_type['name'] }}"
                            data-target="{{ $room_type['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $room_type['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group roomDet">
                <label class="fw-bold">Approximate Room Dimensions (Width x Length) </label>
                <input type="text" name="roomDimensions[]" class="form-control" required>
                <button type="button" class="btn btn-secondary btn-sm w-100 roomBtn mt-2"
                    onclick="add_room_dimension();"><i class="fa-solid fa-plus"></i> Add New
                    Row</button>
            </div>
            @php
                $room_levels = [
                    ['name' => 'Upper', 'target' => ''],
                    ['name' => 'Basement', 'target' => ''],
                    ['name' => 'First', 'target' => ''],
                    ['name' => 'Second', 'target' => ''],
                    ['name' => 'Third', 'target' => ''],
                ];
            @endphp
            <div class="form-group roomDet">
                <label class="fw-bold">Room Level:</label>
                <select class="grid-picker" name="room_level[]" id="room_level"
                    style="justify-content: flex-start;" required multiple>
                    <option value="">Select</option>
                    @foreach ($room_levels as $room_level)
                        <option value="{{ $room_level['name'] }}"
                            data-target="{{ $room_level['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $room_level['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            @php
                $bedroomCloset = [
                    ['name' => 'Built-in Closet', 'target' => ''],
                    ['name' => 'Coat Closet', 'target' => ''],
                    ['name' => 'Dual Closets', 'target' => ''],
                    ['name' => 'Linen Closet', 'target' => ''],
                    ['name' => 'No Closet', 'target' => ''],
                    ['name' => 'Storage Closet', 'target' => ''],
                    ['name' => 'Walk-in Closet', 'target' => ''],
                ];
            @endphp
            <div class="form-group roomDet ">
                <label class="fw-bold">Closet Type:</label>
                <select class="grid-picker" name="bedroomCloset[]" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($bedroomCloset as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            @php
                $roomPrimary = [
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
                    ['name' => 'Vinyl', 'target' => ''],
                    ['name' => 'Wood', 'target' => ''],
                    ['name' => 'Other', 'target' => ''],
                ];
            @endphp
            <div class="form-group roomDet ">
                <label class="fw-bold">Room Primary Floor Covering:</label>
                <select class="grid-picker" name="roomPrimary[]" style="justify-content: flex-start;"
                    multiple required>
                    <option value="">Select</option>
                    @foreach ($roomPrimary as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
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
                    ['name' => 'Wet Bar', 'target' => ''],
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
                    ['name' => 'Other', 'target' => '.roomFeatureOther'],
                ];
            @endphp
            <div class="form-group roomDet ">
                <label class="fw-bold">Room Features:</label>
                <select class="grid-picker" name="room_feature[]" id="room_feature"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($room_features as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main roomFeatureOther d-none">
                    <label class="fw-bold">Room Features:</label>
                    <input type="text" name="roomFeatueOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='26'>
            <h4>Water and Dock Information:</h4>
            <div class="form-group ">
                @php
                    $waterAccessOption = [
                        [
                            'name' => 'Yes',
                            'target' => '.waterAccessYes',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                @endphp
                <label class="fw-bold">Water Access:</label>
                <select class="grid-picker" name="waterAccessOpt" id="water_access"
                    style="justify-content: flex-start;" >
                    <option value="">Select</option>
                    @foreach ($waterAccessOption as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='{{ $item['icon'] }}'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group main waterAccessYes d-none ">
                @php
                    $water_access = [
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
                <label class="fw-bold">Water Access:</label>
                <select class="grid-picker" name="water_access[]" id="water_access"
                    style="justify-content: flex-start;" required multiple>
                    <option value="">Select</option>
                    @foreach ($water_access as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group ">
                <label class="fw-bold">Water View:</label>
                <select class="grid-picker" name="has_water_view" id="has_water_view"
                    style="justify-content: flex-start;" >
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                        @php
                            if ($item['name'] == 'Yes') {
                                $target = '.water_view';
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
            <div class="form-group main water_view d-none">
                <label class="fw-bold">Water View:</label>
                <select class="grid-picker" name="water_view[]" id="water_view"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($water_views as $water_view)
                        <option value="{{ $water_view['name'] }}"
                            data-target="{{ $water_view['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $water_view['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group ">
                <label class="fw-bold">Water Extras:</label>
                <select class="grid-picker" name="has_water_extra" id="has_water_extra"
                    style="justify-content: flex-start;" >
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                        @php
                            if ($item['name'] == 'Yes') {
                                $target = '.water_extras';
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
                $water_extras = [
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
            <div class="form-group main water_extras d-none ">
                <label class="fw-bold">Water Extras:</label>
                <select class="grid-picker" name="water_extras[]" id="water_extras"
                    style="justify-content: flex-start;" multiple >
                    <option value="">Select</option>
                    @foreach ($water_extras as $water_extra)
                        <option value="{{ $water_extra['name'] }}"
                            data-target="{{ $water_extra['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $water_extra['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group ">
                <label class="fw-bold">Water Frontage:</label>
                <select class="grid-picker" name="has_water_fontage" id="has_water_fontage"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                        @php
                            if ($item['name'] == 'Yes') {
                                $target = '.waterFrontageYes';
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
                <div class="form-group main waterFrontageYes d-none">
                    @php
                        $waterFrontageView = [
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
                    <label class="fw-bold">Water Frontage: </label>
                    <select class="grid-picker" name="waterFrontageView[]"
                        style="justify-content: flex-start;" multiple required>
                        <option value="">Select</option>
                        @foreach ($waterFrontageView as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                data-icon="<i class='fa-regular fa-circle-check'></i>">
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group ">
                <label class="fw-bold">Dock:</label>
                <select class="grid-picker" name="has_dock" id="has_dock"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                        @php
                            if ($item['name'] == 'Yes') {
                                $target = '.dockYes';
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
                <div class="form-group main dockYes d-none">
                    @php
                        $dock = [
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
                            ['name' => 'Dock w/o Water Supply', 'target' => ''],
                            ['name' => 'Dock w/Water Supply', 'target' => ''],
                            ['name' => 'Fish Cleaning Station', 'target' => ''],
                            ['name' => 'Floating Dock', 'target' => ''],
                            ['name' => 'Harbormaster', 'target' => ''],
                            ['name' => 'Internet', 'target' => ''],
                            ['name' => 'Lift', 'target' => ''],
                            ['name' => 'Restroom/Shower', 'target' => ''],
                            ['name' => 'Wet Dock', 'target' => ''],
                            ['name' => 'None', 'target' => ''],
                            ['name' => 'Other', 'target' => '.therDock']
                        ];
                    @endphp
                    <label class="fw-bold">Dock: </label>
                    <select class="grid-picker" name="dock[]"
                        style="justify-content: flex-start;" multiple required>
                        <option value="">Select</option>
                        @foreach ($dock as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                data-icon="<i class='fa-regular fa-circle-check'></i>">
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-group main otherDock d-none">
                        <label class="fw-bold">Dock Description:</label>
                        <input type="text" name="dockDescription" class="form-control has-icon"
                            data-icon="fa-regular fa-circle-check">
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">Dock Lift Capacity:</label>
                        <input type="text" name="dockLiftCapacity" class="form-control has-icon"
                            data-icon="fa-regular fa-circle-check">
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">Dock Year Built:</label>
                        <input type="text" name="dockYearBuilt" class="form-control has-icon"
                            data-icon="fa-regular fa-circle-check">
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">Dock Dimension:</label>
                        <input type="text" name="dockDimension" class="form-control has-icon"
                            data-icon="fa-regular fa-circle-check">
                    </div>
                    <div class="form-group">
                        <label class="fw-bold">Dock Maintenance Fee:</label>
                        <input type="text" name="dockMaintenanceFee" class="form-control has-icon"
                            data-icon="fa-regular fa-circle-check">
                    </div>
                    @php
                        $dock = [
                            ['name' => 'Annual', 'target' => ''],
                            ['name' => 'Monthly', 'target' => ''],
                            ['name' => 'Quarterly', 'target' => ''],
                            ['name' => 'N/A', 'target' => ''],
                        ];
                    @endphp
                    <label class="fw-bold">Dock Maintenance Fee Frequency:</label>
                    <select class="grid-picker" name="dockMaintenanceFeeFrequency"
                        style="justify-content: flex-start;" multiple required>
                        <option value="">Select</option>
                        @foreach ($dock as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                data-icon="<i class='fa-regular fa-circle-check'></i>">
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='27'>
            @php
                $utilities = [
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
                    ['name' => 'Other', 'target' => '.otherUtilitiesRes'],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Utilities:</label>
                <select class="grid-picker" name="utilities[]" id="utilities"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($utilities as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherUtilitiesRes d-none">
                    <label for="" class="fw-bold">Utilities: </label>
                    <input type="text" class="form-control has-icon" data-icon="fa-regular fa-circle-check" name="otherUtilities">
                </div>
            </div>
            @php
                $waters = [
                    ['name' => 'Canal/Lake For Irrigation', 'target' => ''],
                    ['name' => 'Private', 'target' => ''],
                    ['name' => 'Public', 'target' => ''],
                    ['name' => 'Well', 'target' => ''],
                    ['name' => 'None', 'target' => ''],
                    ['name' => 'Other', 'target' => '.otherWaterRes'],
                ];
            @endphp
            <div class="form-group">
                <label class="fw-bold">Water:</label>
                <select class="grid-picker" name="water[]" id="water12"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($waters as $water)
                        <option value="{{ $water['name'] }}" data-target="{{ $water['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $water['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherWaterRes d-none">
                    <label for="" class="fw-bold">Water: </label>
                    <input type="text" class="form-control has-icon" data-icon="fa-regular fa-circle-check" name="otherWater">
                </div>
            </div>

            @php
                $sewers1 = [
                    ['name' => 'Aerobic Septic', 'target' => ''],
                    ['name' => 'PEP-Holding Tank', 'target' => ''],
                    ['name' => 'Private Sewer', 'target' => ''],
                    ['name' => 'Public Sewer', 'target' => ''],
                    ['name' => ' Septic Tank', 'target' => ''],
                    ['name' => ' None', 'target' => ''],
                    ['name' => 'Other', 'target' => '.otherSewerRes'],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Sewer:</label>
                <select class="grid-picker" name="sewer[]" id="sewer"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($sewers1 as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherSewerRes d-none">
                    <label for="" class="fw-bold">Sewer: </label>
                    <input type="text" class="form-control has-icon" data-icon="fa-regular fa-circle-check" name="otherSewer">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='28'>
            <div class="form-group ">
                @php
                    $airConditioning = [
                        ['name' => 'Central Air', 'target' => ''],
                        ['name' => 'Humidity Control', 'target' => ''],
                        ['name' => 'Mini-Split Unit(s)', 'target' => ''],
                        ['name' => 'Wall/Window Unit(s)', 'target' => ''],
                        ['name' => 'Zoned', 'target' => ''],
                        ['name' => 'None', 'target' => ''],
                        ['name' => 'Other', 'target' => '.otherAirConditionRes'],
                    ];
                @endphp
                <label class="fw-bold">Air Conditioning: </label>
                <select class="grid-picker" name="airConditioning[]" id="utilities"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($airConditioning as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherAirConditionRes d-none">
                    <label for="" class="fw-bold"> Air Conditioning: </label>
                    <input type="text" class="form-control has-icon" data-icon="fa-regular fa-circle-check" name="otherAirCondition">
                </div>
            </div>
            <div class="form-group ">
                @php
                    $heatingFuel = [
                        ['name' => 'Baseboard', 'target' => ''],
                        ['name' => 'Central', 'target' => ''],
                        ['name' => 'Electric', 'target' => ''],
                        ['name' => 'Exhaust Fans', 'target' => ''],
                        ['name' => 'Heat Pump', 'target' => ''],
                        ['name' => 'Heat Recovery Unit', 'target' => ''],
                        ['name' => 'Natural Gas', 'target' => ''],
                        ['name' => 'Oil', 'target' => ''],
                        ['name' => 'Partial', 'target' => ''],
                        ['name' => 'Propane', 'target' => ''],
                        ['name' => 'Radiant Ceiling', 'target' => ''],
                        ['name' => 'Reverse Cycle', 'target' => ''],
                        ['name' => 'Solar', 'target' => ''],
                        ['name' => 'Space Heater', 'target' => ''],
                        ['name' => 'Wall Furnace', 'target' => ''],
                        ['name' => 'Wall Units / Window Unit', 'target' => ''],
                        ['name' => 'Zoned', 'target' => ''],
                        ['name' => 'None', 'target' => ''],
                        ['name' => 'Other', 'target' => '.otherFuelRes'],
                    ];
                @endphp
                <label class="fw-bold">Heating and Fuel: </label>
                <select class="grid-picker" name="heatingFuel[]" id="utilities"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($heatingFuel as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherFuelRes d-none">
                    <label for="" class="fw-bold"> Heating and Fuel: </label>
                    <input type="text" class="form-control has-icon" data-icon="fa-regular fa-circle-check" name="otherFuel">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='29'>
            <div class="form-group ">
                @php
                    $carportOption = [
                        [
                            'name' => 'Yes',
                            'target' => '.carprotYes',
                            'icon' => 'fa-regular fa-circle-check',
                        ],
                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                @endphp
                <label class="fw-bold">Carport:</label>
                <select class="grid-picker" name="carport" id="carport"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($carportOption as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main carprotYes d-none">
                    <label class="fw-bold">How many carport spaces?</label>
                    <input type="number" name="carportOther" id="condo_fee"
                        class="form-control has-icon" data-icon="fa-solid fa-warehouse">
                </div>
            </div>
            <div class="form-group ">
                @php
                    $garageOption = [
                        ['name' => 'Yes', 'target' => '.garageYes', 'icon' => 'fa-regular fa-circle-check'],
                        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                    ];
                @endphp
                <label class="fw-bold">Garage:</label>
                <select class="grid-picker" name="garage" id="garage"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($garageOption as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main garageYes d-none">
                    <label class="fw-bold">How many garage spaces?</label>
                    <input type="number" name="garageOther" class="form-control has-icon"
                        data-icon="fa-solid fa-warehouse">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='30'>
            
            <div class="form-group ">
                @if (optional($auction->get)->property_type === 'Residential Property')
                <div class="form-group">
                    @php
                        $poolOpt = [
                            ['name' => 'Yes', 'target' => '.poolYesRes', 'icon' => 'fa-regular fa-circle-check'],
                            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ];
                    @endphp
                    <label class="fw-bold">Pool:</label>
                    <select class="grid-picker" name="poolOpt" id="pool"
                        style="justify-content: flex-start;" required>
                        <option value="">Select</option>
                        @foreach ($poolOpt as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-group main poolYesRes d-none">
                        @php
                            $pools = [
                                ['name' => 'Private', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                                ['name' => 'Community', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                            ];
                        @endphp
                        <label class="fw-bold">Pool Type:</label>
                        <select class="grid-picker" name="pool" id="pool"
                            style="justify-content: flex-start;" required>
                            <option value="">Select</option>
                            @foreach ($pools as $item)
                                <option value="{{ $item['name'] }}" data-target="{{ $target }}"
                                    class="card flex-row" style="width:calc(33.3% - 10px);"
                                    data-icon='<i class="{{ $item['icon'] }}"></i>'>
                                    {{ $item['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @endif
                <div class="form-group ">
                    @php
                        $viewOption = [
                            [
                                'name' => 'Yes',
                                'target' => '.viewYes',
                                'icon' => 'fa-regular fa-circle-check',
                            ],
                            ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                        ];
                    @endphp
                    <label class="fw-bold">View:</label>
                    <select class="grid-picker" name="viewOption[]" style="justify-content: flex-start;">
                        <option value="">Select</option>
                        @foreach ($viewOption as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(33.3% - 10px);"
                                data-icon="<i class='{{ $item['icon'] }}'></i>">
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-group main viewYes d-none">
                        @php
                            $view = [
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
                                ['name' => 'Other', 'target' => '.viewOther'],
                            ];
                        @endphp
                        <label class="fw-bold">View: </label>
                        <select class="grid-picker" name="view[]" id="water_access"
                            style="justify-content: flex-start;" multiple required>
                            <option value="">Select</option>
                            @foreach ($view as $item)
                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    class="card flex-row" style="width:calc(33.3% - 10px);"
                                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                                    {{ $item['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-group main viewOther d-none">
                            <label for="" class="fw-bold">View: </label>
                            <input type="text" class="form-control has-icon"
                                data-icon="fa-regular fa-circle-check" name="viewOther">
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        <div class="wizard-step" data-step='31'>
            @php
                $garage_spaces = [
                    ['target' => '', 'name' => '1 to 5 Spaces', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => '6 to 12 Spaces', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => '13 to 18 Spaces', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => '19 to 30 Spaces', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'Airplane Hangar', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'Common', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'Curb Parking', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'Deeded', 'icon' => 'fa-solid fa-warehouse'],
                    [
                        'target' => '',
                        'name' => 'Electric Vehicle Charging Station(s)',
                        'icon' => 'fa-solid fa-warehouse',
                    ],
                    ['target' => '', 'name' => 'Ground Level', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'Lighted', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'None', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'Over 30 Spaces', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'Secured', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'Under Building', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'Underground', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'Valet', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '', 'name' => 'None', 'icon' => 'fa-solid fa-warehouse'],
                    ['target' => '.otherParkingCommercial', 'name' => 'Other', 'icon' => 'fa-solid fa-warehouse'],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Garage/Parking Features:</label>
                <select class="grid-picker" name="parking_feature_garage[]" id="parking_feature_garage"
                    style="justify-content: flex-start;" required multiple>
                    <option value="">Select</option>
                    @foreach ($garage_spaces as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherParkingCommercial d-none">
                    <label class="fw-bold">Garage/Parking Features: </label>
                    <input type="text" name="otherParking" class="form-control has-icon"
                        data-icon="fa-solid fa-warehouse">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='32'>
            @php
                $front_exposures = [
                    ['name' => 'North', 'target' => ''],
                    ['name' => 'East', 'target' => ''],
                    ['name' => 'South', 'target' => ''],
                    ['name' => 'West', 'target' => ''],
                    ['name' => 'Southeast', 'target' => ''],
                    ['name' => 'Northeast', 'target' => ''],
                    ['name' => 'Southwest', 'target' => ''],
                    ['name' => 'Northwest', 'target' => ''],
                    ['name' => 'Undetermined', 'target' => ''],
                ];
            @endphp
            <div class="form-group residential_and_income_hide">
                <label class="fw-bold">Front Exposure:</label>
                <select class="grid-picker" name="front_exposure" id="front_exposure"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($front_exposures as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="wizard-step" data-step='33'>
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
                    ['name' => 'Other', 'target' => '.foundationOther'],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Foundation:</label>
                <select class="grid-picker" name="foundation[]" id="foundation"
                    style="justify-content: flex-start;" multiple required>
                    <option value="">Select</option>
                    @foreach ($foundations as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main foundationOther d-none">
                    <label class="fw-bold">Foundation: </label>
                    <input type="text" name="foundationOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='34'>
            @php
                $exterior_constructions = [
                    ['name' => 'Asbestos', 'target' => ''],
                    ['name' => 'Block', 'target' => ''],
                    ['name' => 'Brick', 'target' => ''],
                    ['name' => 'Cedar', 'target' => ''],
                    ['name' => 'Cement Siding', 'target' => ''],
                    ['name' => 'Concrete', 'target' => ''],
                    ['name' => 'HardiPlank Type', 'target' => ''],
                    ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''],
                    ['name' => 'Log', 'target' => ''],
                    ['name' => 'Metal Frame', 'target' => ''],
                    ['name' => 'Metal Siding', 'target' => ''],
                    ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''],
                    ['name' => 'Stone', 'target' => ''],
                    ['name' => 'Stucco', 'target' => ''],
                    ['name' => 'Tilt up Walls', 'target' => ''],
                    ['name' => 'Vinyl Siding', 'target' => ''],
                    ['name' => 'Wood Frame', 'target' => ''],
                    ['name' => 'Wood Frame (FSC)', 'target' => ''],
                    ['name' => 'Wood Siding ', 'target' => ''],
                    ['name' => 'Other', 'target' => '.exteriorOther'],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Exterior Construction:</label>
                <select class="grid-picker" name="exterior_construction[]" id="exterior_construction"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($exterior_constructions as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main exteriorOther d-none">
                    <label class="fw-bold">Exterior Construction: </label>
                    <input type="text" name="exteriorOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='35'>
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
                    ['name' => 'Other', 'target' => '.exteriorFeatureOther'],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Exterior Features:</label>
                <select class="grid-picker" name="exterior_feature[]" id="exterior_feature"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($exterior_features as $exterior_feature)
                        <option value="{{ $exterior_feature['name'] }}"
                            data-target="{{ $exterior_feature['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $exterior_feature['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main exteriorFeatureOther d-none">
                    <label class="fw-bold">Exterior Features: </label>
                    <input type="text" name="exteriorFeatureOther" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='36'>
            @php
            $otherStructures = [
                ['name' => 'Additional Single Family Home', 'target' => ''],
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
                ['name' => 'Other', 'target' => '.roadStructures'],
            ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Other Structures:</label>
                <select class="grid-picker" name="other_structures[]" id="other_structures"
                    style="justify-content: flex-start;" required multiple>
                    <option value="">Select</option>
                    @foreach ($otherStructures as $item)
                        <option value="{{ $item['name'] }}"
                            data-target="{{ $item['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main roadStructures d-none">
                    <label class="fw-bold">Other Structures:</label>
                    <input type="text" name="structuresOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='37'>
            @php
                $buildingFeatures = [
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
                    ['name' => 'Other', 'target' => '.buildingFeaturesOther'],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Building Features :</label>
                <select class="grid-picker" name="buildingFeatures[]" style="justify-content: flex-start;"
                    multiple>
                    <option value="">Select</option>
                    @foreach ($buildingFeatures as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main buildingFeaturesOther d-none">
                    <label class="fw-bold">Building Features:</label>
                    <input type="text" name="buildingFeaturesOther" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='38'>
            @php
                $road_frontages = [
                    ['name' => 'Access Road', 'target' => ''],
                    ['name' => 'Alley', 'target' => ''],
                    ['name' => 'Business District', 'target' => ''],
                    ['name' => 'City Street', 'target' => ''],
                    ['name' => 'County Road ', 'target' => ''],
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
                    ['name' => 'Other', 'target' => '.roadFrontageOther'],
                ];
            @endphp
            <div class="form-group">
                <label class="fw-bold">Road Frontage:</label>
                <select class="grid-picker" name="road_frontage[]" id="road_frontage"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($road_frontages as $road_frontage)
                        <option value="{{ $road_frontage['name'] }}"
                            data-target="{{ $road_frontage['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $road_frontage['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main roadFrontageOther d-none">
                    <label class="fw-bold">Road Frontage: </label>
                    <input type="text" name="roadFrontageOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='39'>
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
                    ['name' => 'Other', 'target' => '.roadSurfaceOther'],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Road Surface Type:</label>
                <select class="grid-picker" name="road_surface_type[]" id="road_surface_type"
                    style="justify-content: flex-start;" required multiple>
                    <option value="">Select</option>
                    @foreach ($road_surface_types as $road_surface_type)
                        <option value="{{ $road_surface_type['name'] }}"
                            data-target="{{ $road_surface_type['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $road_surface_type['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main roadSurfaceOther d-none">
                    <label class="fw-bold">Road Surface Type:</label>
                    <input type="text" name="roadSurfaceOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='40'>
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
                    ['name' => 'Other', 'target' => '.roofCementOther'],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Roof:</label>
                <select class="grid-picker" name="roof[]" id="roof"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($roofs as $roof)
                        <option value="{{ $roof['name'] }}" data-target="{{ $roof['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $roof['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main roofCementOther d-none">
                    <label class="fw-bold">Roof:</label>
                    <input type="text" name="roofCementOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='41'>
            @php
                $adjoining_properties = [
                    ['name' => 'Airport', 'target' => ''],
                    ['name' => 'Church', 'target' => ''],
                    ['name' => 'Commercial', 'target' => ''],
                    ['name' => 'Hotel/Motel', 'target' => ''],
                    ['name' => 'Industrial', 'target' => ''],
                    ['name' => 'Multi-Family', 'target' => ''],
                    ['name' => 'Natural State', 'target' => ''],
                    ['name' => 'Professional Office', 'target' => ''],
                    ['name' => 'Railroad', 'target' => ''],
                    ['name' => 'Residential', 'target' => ''],
                    ['name' => 'School', 'target' => ''],
                    ['name' => 'Undeveloped', 'target' => ''],
                    ['name' => 'Vacant', 'target' => ''],
                    ['name' => 'Waterway', 'target' => ''],
                ];
            @endphp
            <div class="form-group ">
                <label class="fw-bold">Adjoining Property:</label>
                <select class="grid-picker" name="adjoining_property[]" id="roof"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($adjoining_properties as $adjoining_propertie)
                        <option value="{{ $adjoining_propertie['name'] }}"
                            data-target="{{ $adjoining_propertie['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $adjoining_propertie['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="wizard-step" data-step='42'>
            @php
                $lot_features1 = [
                    ['name' => 'Central Business District', 'target' => ''],
                    ['name' => 'Corner Lot', 'target' => ''],
                    ['name' => 'Cul-De-Sac', 'target' => ''],
                    ['name' => 'Curb and Gutters', 'target' => ''],
                    ['name' => 'Drainage Canal', 'target' => ''],
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
                    ['name' => 'Riprarian Rights', 'target' => ''],
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
                    ['name' => 'Zoned for Horses', 'target' => ''],
                    ['name' => 'Other', 'target' => '.otherFeaturesCommercial'],
                ];
            @endphp
            <div class="form-group  ">
                <label class="fw-bold">Lot Features:</label>
                <select class="grid-picker" name="lot_features[]" id="lot_features"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($lot_features1 as $lot_feature12)
                        <option value="{{ $lot_feature12['name'] }}"
                            data-target="{{ $lot_feature12['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $lot_feature12['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherFeaturesCommercial d-none ">
                    <label class="fw-bold">Lot Features:</label>
                    <input type="text" name="otherFeatures"  class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='43'>
            <div class="form-group">
                <label class="fw-bold">Is the property located in a condo environment? </label>
                <select class="grid-picker" name="has_condo_enviornment" id="has_condo_enviornment"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                        @php
                            if ($item['name'] == 'Yes') {
                                $target = '.has_condo';
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
            <div class="row has_condo main d-none">
                @php
                    $condo_fee_terms = [
                        ['target' => '', 'name' => 'Annual'],
                        ['target' => '', 'name' => 'Monthly'],
                        ['target' => '', 'name' => ' Quarterly'],
                        ['target' => '', 'name' => 'Semi Annual '],
                    ];
                @endphp
                <div class="form-group ">
                    <label class="fw-bold">Condo Fee Term:</label>
                    <select class="grid-picker" name="condo_fee_terms[]" id="parking_feature_garage"
                        style="justify-content: flex-start;" required multiple>
                        <option value="">Select</option>
                        @foreach ($condo_fee_terms as $item)
                            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                class="card flex-row" style="width:calc(50% - 10px);"
                                data-icon='<i class="fa-regular fa-circle-check"></i>'>
                                {{ $item['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group  ">
                    <label class="fw-bold">Condo Fee:</label>
                    <input type="text" name="condo_fee" id="condo_fee" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign">
                </div>
            </div>
            <div class="form-group">
                <label class="fw-bold">Association/Manager Name:</label>
                <input type="text" name="association_name" class="form-control has-icon"
                    data-icon="fa-solid fa-user">
            </div>
            <div class="form-group">
                <label class="fw-bold">Association/Manager Phone:</label>
                <input type="text" name="association_phone" class="form-control has-icon"
                data-icon="fa-solid fa-phone">
            </div>
            <div class="form-group">
                <label class="fw-bold">Association/Manager Email:</label>
                <input type="email" name="association_email" class="form-control has-icon"
                    data-icon="fa-solid fa-envelope">
            </div>
            <div class="form-group">
                <label class="fw-bold">Association/Manager Website:</label>
                <input type="text" name="association_website" class="form-control has-icon"
                    data-icon="fa-solid fa-globe">
            </div>
            <div class="form-group ">
                @php
                    $community_features = [
                        ['name' => 'Activity Core/Center', 'target' => ''],
                        ['name' => 'Airport/Runway', 'target' => ''],
                        ['name' => 'Beach Area', 'target' => ''],
                        ['name' => 'Curbs', 'target' => ''],
                        ['name' => 'Expressway', 'target' => ''],
                        ['name' => 'Sidewalk', 'target' => ''],
                        ['name' => 'Stream Seasonal', 'target' => ''],
                        ['name' => 'Other', 'target' => '.communityFeatureOther'],
                    ];
                @endphp
                <label class="fw-bold">Community Features:</label>
                <select class="grid-picker" name="community_feature[]" id="community_feature"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($community_features as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main communityFeatureOther d-none">
                    <label class="fw-bold">Community Features:</label>
                    <input type="text" name="communityFeatureOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='44'>
            <div class="form-group">
                <label class="fw-bold">Does the property have an HOA, condo association, master association, and/or community fee? </label>
                <select class="grid-picker" name="has_hoa" id="has_hoa"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                        @php
                            if ($item['name'] == 'Yes') {
                                $target = '.hoas';
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

            <div class="form-group main hoas d-none">
                <label class="fw-bold">Association Approval Required: </label>
                <select class="grid-picker" name="assocRequired" 
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                        @php
                            if ($item['name'] == 'Yes') {
                                $target = '.master_association';
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
            <div class="form-group main hoas d-none">
                <label class="fw-bold">Housing For Older Persons: </label>
                <select class="grid-picker" name="oldHouse"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                        @php
                            if ($item['name'] == 'Yes') {
                                $target = '.master_association';
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

            <div class="form-group main hoas d-none">
                @php
                    $hoa_fee_requirenments = [
                        ['name' => 'None', 'target' => ''],
                        ['name' => 'Optional', 'target' => ''],
                        ['name' => ' Required', 'target' => ''],
                    ];
                @endphp
                <label class="fw-bold">HOA Fee Requirement:</label>
                <select class="grid-picker" name="hoa_fee_requirenment" id="feeReqOption"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($hoa_fee_requirenments as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group" id="feeReq" style="display: none;">
                    <label class="fw-bold">How much is the HOA Fee?</label>
                    <input type="number" name="feeReq" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign">
                    @php
                        $paySchedule = [
                            ['name' => 'Annually', 'target' => ''],
                            ['name' => 'Monthly', 'target' => ''],
                            ['name' => 'Quarterly', 'target' => ''],
                            ['name' => 'Semi-Annually ', 'target' => ''],
                        ];
                    @endphp
                    <div class="form-group">
                        <label class="fw-bold">HOA Payment Schedule: </label>
                        <select class="grid-picker" name="paySchedule"
                            style="justify-content: flex-start;">
                            <option value="">Select</option>
                            @foreach ($paySchedule as $item)
                                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                                    class="card flex-row" style="width:calc(33.3% - 10px);"
                                    data-icon="<i class='fa-regular fa-circle-check'></i>">
                                    {{ $item['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-group main hoas d-none">
                <label class="fw-bold">Association Approval Fee for Tenants:</label>
                <input type="number" name="association_approval_fee" id="association_approval_fee"
                    class="form-control has-icon " data-icon="fa-solid fa-dollar-sign">
            </div>
            <div class="form-group main hoas d-none">
                <label class="fw-bold">Parking Fee For Tenants:</label>
                <input type="number" name="parking_fee_for_tenants" id="parking_fee_for_tenants"
                    class="form-control has-icon " data-icon="fa-solid fa-dollar-sign">
            </div>
            <div class="form-group main hoas d-none">
                <label class="fw-bold">Association Security Deposit Fee for Tenant:</label>
                <input type="number" name="association_security_deposit"
                    id="association_security_deposit" class="form-control has-icon "
                    data-icon="fa-solid fa-dollar-sign">
            </div>
            <div class="form-group main hoas d-none">
                <label class="fw-bold">Other Association Fees for Tenants:</label>
                <input type="number" name="other_association_fee" id="association_security_deposit"
                    class="form-control has-icon " data-icon="fa-solid fa-dollar-sign">
            </div>
            <div class="form-group main hoas d-none">
                <label class="fw-bold">Association/Manager Name:</label>
                <input type="text" name="association_name" class="form-control has-icon"
                    data-icon="fa-solid fa-user">
            </div>
            <div class="form-group main hoas d-none">
                <label class="fw-bold">Association/Manager Phone:</label>
                <input type="text" name="association_phone" class="form-control has-icon"
                data-icon="fa-solid fa-phone">
            </div>
            <div class="form-group main hoas d-none">
                <label class="fw-bold">Association/Manager Email:</label>
                <input type="email" name="association_email" class="form-control has-icon"
                    data-icon="fa-solid fa-envelope">
            </div>

            <div class="form-group main HOA_show d-none">
                @php
                    $community_features = [
                        ['name' => 'Airport/Runway', 'target' => ''],
                        ['name' => 'Association Recreation - Lease', 'target' => ''],
                        ['name' => 'Association Recreation - Owned', 'target' => ''],
                        ['name' => 'Buyer Approval Required', 'target' => ''],
                        ['name' => 'Clubhouse', 'target' => ''],
                        ['name' => 'Dog Park', 'target' => ''],
                        ['name' => 'Community Boat Ramp', 'target' => ''],
                        ['name' => 'Community Mailbox', 'target' => ''],
                        ['name' => 'Deed Restrictions', 'target' => ''],
                        ['name' => 'Fishing', 'target' => ''],
                        ['name' => 'Fitness Center', 'target' => ''],
                        ['name' => 'Gated Community - Guard', 'target' => ''],
                        ['name' => 'Gated Community- Not Guard ', 'target' => ''],
                        ['name' => 'Golf Carts OK', 'target' => ''],
                        ['name' => 'Golf Community', 'target' => ''],
                        ['name' => 'Handicap Modified', 'target' => ''],
                        ['name' => 'Horse Stable(s)', 'target' => ''],
                        ['name' => 'Horses Allowed', 'target' => ''],
                        ['name' => 'Irrigation-Reclaimed Water', 'target' => ''],
                        ['name' => 'No Truck/RV/Motorcycle Parking', 'target' => ''],
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
                        ['name'=> 'Other', 'target' => '.otherCommunity']
                    ];
                @endphp
                <label class="fw-bold">Community Features:</label>
                <select class="grid-picker" name="community_feature[]" id="community_feature"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($community_features as $community_feature)
                        <option value="{{ $community_feature['name'] }}"
                            data-target="{{ $community_feature['target'] }}" class="card flex-row"
                            style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $community_feature['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherCommunity d-none">
                    <label class="fw-bold">Community Features:</label>
                    <input type="email" name="communityOther" class="form-control has-icon"
                        data-icon="fa-regular fa-circle-check">
                </div>
            </div>

            <div class="form-group  residential_show">
                @php
                    $association_amenities = [
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
                        ['name' => 'Spa/Hot Tubs', 'target' => ''],
                        ['name' => 'Storage', 'target' => ''],
                        ['name' => 'Tennis Court(s)', 'target' => ''],
                        ['name' => 'Trails', 'target' => ''],
                        ['name' => 'Vehicle Restrictions', 'target' => ''],
                        ['name' => 'Wheelchair Access', 'target' => ''],
                        ['name' => 'None', 'target' => ''],
                        ['name' => 'Other', 'target' => '.otherAmenitiesRes'],
                    ];
                @endphp
                <label class="fw-bold">Association Amenities:</label>
                <select class="grid-picker" name="association_amenitie[]" id="association_amenitie"
                    style="justify-content: flex-start;" multiple>
                    <option value="">Select</option>
                    @foreach ($association_amenities as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="<i class='fa-regular fa-circle-check'></i>">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main otherAmenitiesRes d-none">
                    <label class="fw-bold"> Association Amenities: :</label>
                    <input name="otherAmenities" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='45'>
            <div class="form-group">
                <label class="fw-bold"> Description:</label>
                <textarea name="description" id="description" class="form-control" cols="30" rows="10" required></textarea>
            </div>
            <div class="form-group">
                <label class="fw-bold">Legal Disclaimers:</label>
                <textarea name="disclaimer" id="description" class="form-control" cols="30" rows="6" required></textarea>
            </div>
            <div class="form-group">
                <label class="fw-bold">Driving Directions:</label>
                <input type="text" name="driving_directions" class="form-control has-icon" data-icon="fa-solid fa-car" >
            </div>
            <div class="form-group">
                @php
                    $compensationYesRes = [
                        ['name' => 'Yes', 'target' => '.agentCompensationYesRes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                        ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
                        ['name' => ' Negotiable', 'target' => '','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                    ];
                @endphp
                <label class="fw-bold">Is the landlord offering compensation for a tenant’s agent?</label>
                <select class="grid-picker" name="tenant_agent_compensation" id="feeReqOption"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($compensationYesRes as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon="{{$item['icon']}}">
                            {{ $item['name'] }}
                        </option>
                    @endforeach
                </select>
                <div class="form-group main agentCompensationYesRes d-none">
                    <label class="fw-bold">Tenant’s Agent Compensation: $ </label>
                    <input type="text" name="compensationYes" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                </div>
            </div>
        </div>
        <div class="wizard-step" data-step='46'>
            @if (auth()->user()->user_type == 'landlord')
                <h4>Landlord’s Info:</h4>
            @else
                <h4>Landlord’s Agent Info:</h4>
            @endif
            

            <div class="form-group row">
                <div class="form-group col-md-6">
                    <label class="fw-bold" for="first_name">First Name:</label>
                    <input type="text" name="first_name" placeholder="" id="first_name"
                        class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user"
                        value="{{ Auth::user()->first_name }}">
                </div>
                <div class="form-group col-md-6">
                    <label class="fw-bold" for="last_name">Last Name:</label>
                    <input type="text" name="last_name" placeholder="" id="last_name"
                        class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user"
                        value="{{ Auth::user()->last_name }}">
                </div>
            </div>
            <div class="form-group row">
                <div class="form-group col-md-6">
                    <label class="fw-bold" for="agent_phone">Phone Number:</label>
                    <input type="text" name="agent_phone" placeholder="" id="agent_phone"
                        class="form-control has-icon hide_arrow" data-icon="fa-solid fa-phone"
                        value="{{ Auth::user()->phone }}">
                </div>
                <div class="form-group col-md-6">
                    <label class="fw-bold" for="agent_email">Email:</label>
                    <input type="text" name="agent_email" class="form-control has-icon hide_arrow" data-icon="fa-solid fa-envelope" value="{{ Auth::user()->email }}">
                </div>
            </div>
            @if (auth()->user()->user_type !== 'landlord')
                <div class="form-group row">
                    <div class="form-group col-md-6">
                        <label class="fw-bold" for="agent_brokerage">Brokerage:</label>
                        <input type="text" name="agent_brokerage" class="form-control has-icon hide_arrow" data-icon="fa-solid fa-handshake" value="{{ Auth::user()->brokerage }}">
                    </div>
                    <div class="form-group col-md-6">
                        <label class="fw-bold" for="agent_license_no">Real Estate License #:</label>
                        <input type="text" name="agent_license_no" class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-card"
                            value="{{ Auth::user()->license_no }}">
                    </div>
                </div>

                <div class="form-group row">
                    <div class="form-group col-md-6">
                        <label class="fw-bold" for="agent_mls_id">NAR Member ID (NRDS ID): </label>
                        <input type="number" name="agent_mls_id" class="form-control has-icon hide_arrow"
                            data-icon="fa-solid fa-id-card-clip" value="{{ Auth::user()->mls_id }}">
                    </div>
                    {{-- <div class="form-group col-md-6">
                        <label class="fw-bold" for="agent_mls_id">Listed By: Real Estate Agent: </label>
                        <input type="text" name="realEstate" class="form-control has-icon hide_arrow"
                            data-icon="fa-solid fa-id-card-clip" >
                    </div> --}}
                </div>
            @endif
        </div>
          {{--
              AUDIT NOTE (purchase terms):
              Exhaustive grep of this file confirmed zero instances of offer_price,
              earnest_deposit, down_payment, financing_type, contingency, closing_date,
              or possession_date.  This form already uses rental language throughout.

              AUDIT NOTE (shared partial):
              offers/_offer_terms_form.blade.php rental block requires $offer (Eloquent),
              $mode, $formData (Collection), and $offerType — data structures that are
              not available in this wizard's flat-POST flow (TenantCriteriaAuctionBidController).
              Direct inclusion is not possible without a significant adapter layer.
              The rental fields (price, leaseDate, leaseTime, leaseTerms, frequency,
              tenant_pays, required_at_move_in, etc.) are already implemented as
              isolated fields in steps 3–11 of this wizard, which is the correct
              approach for this architecture.
          --}}

          {{-- ═══════════════════════════════════════════════════════════════════════
               Step 47 — Criteria Summary / Tenant Requirements (read-only)
               Displays the parent auction's criteria so the submitter can verify
               their offer aligns.  Sourced from $auction->get (Fluent).
          ═══════════════════════════════════════════════════════════════════════ --}}
          {{-- ╔══════════════════════════════════════════════════════════════════════════╗
               WHY TenantBidMatchScoreHelper IS NOT USED HERE
               ═══════════════════════════════════════════════════════════════════════════
               TenantBidMatchScoreHelper (app/Helpers/TenantBidMatchScoreHelper.php) is
               purpose-built for scoring TWO agent bids against each other on the
               BROKER COMPENSATION & AGENCY AGREEMENT TERMS axis:
                 commission_structure, lease_fee_type, payment_timing, purchase_fee_type,
                 lease_type, purchase_type, protection_period, early_termination_fee_*,
                 retainer_fee_*, agency_agreement_timeframe, brokerage_relationship,
                 referral_fee_percent.
               These are agent-to-agent comparison fields (see LOGICAL_FIELD_GROUPS).

               This step instead compares PROPERTY TERMS offered by a landlord/agent
               (rent price, bedrooms, bathrooms, pet policy, furnished preference) against
               the TENANT'S PROPERTY CRITERIA. No such comparison logic exists in
               TenantBidMatchScoreHelper — it has no rent/bedroom/bathroom/pet fields.

               Per the task spec: "Drive matched/unmatched indicators from
               TenantBidMatchScoreHelper WHERE LOGIC EXISTS; otherwise display raw
               criteria values." Since no relevant logic exists in the helper for property
               criteria matching, direct field comparison is used and raw criteria are
               displayed. The JS match indicators (tc-ind-*) below are the equivalent
               implementation for the property criteria domain.
          ╚══════════════════════════════════════════════════════════════════════════╝ --}}
          <div class="wizard-step" data-step="47">
            <h4><i class="fa-solid fa-list-check me-2"></i>Criteria Summary / Tenant Requirements</h4>
            <p class="text-muted small mb-3">
              The following are the tenant's stated requirements for this listing.
              Review them before finalising your offer.
            </p>
            {{-- data-* attrs embed PHP auction criteria for JS match-indicator comparison --}}
            <div class="card border-0 bg-light p-3 mb-3" id="tc-criteria-card"
                 data-max-rent="{{ (float)(@$auction->get->max_rent_budget ?: @$auction->get->monthly_price) }}"
                 data-min-beds="{{ (int)@$auction->get->bedrooms }}"
                 data-min-baths="{{ (float)@$auction->get->bathrooms }}"
                 data-crit-pets="{{ @$auction->get->has_pets ?? '' }}"
                 data-crit-furn="{{ is_array(@$auction->get->furnished) ? implode('|', @$auction->get->furnished) : (@$auction->get->furnished ?? '') }}">
              <div class="row g-2">
                @php $ag = @$auction->get; @endphp

                @if($ag->monthly_price)
                  <div class="col-sm-6">
                    <div class="d-flex align-items-start gap-2">
                      <i class="fa-solid fa-dollar-sign mt-1 text-success"></i>
                      <div>
                        <div class="fw-semibold small text-muted">Monthly Rent Budget <span id="tc-ind-rent"></span></div>
                        <div>${{ number_format((float)$ag->monthly_price) }}
                          @if($ag->max_rent_budget)
                            – ${{ number_format((float)$ag->max_rent_budget) }}
                          @endif
                        </div>
                      </div>
                    </div>
                  </div>
                @endif

                @if($ag->bedrooms)
                  <div class="col-sm-6">
                    <div class="d-flex align-items-start gap-2">
                      <i class="fa-solid fa-bed mt-1 text-success"></i>
                      <div>
                        <div class="fw-semibold small text-muted">Bedrooms <span id="tc-ind-beds"></span></div>
                        <div>{{ $ag->bedrooms }}+</div>
                      </div>
                    </div>
                  </div>
                @endif

                @if($ag->bathrooms)
                  <div class="col-sm-6">
                    <div class="d-flex align-items-start gap-2">
                      <i class="fa-solid fa-bath mt-1 text-success"></i>
                      <div>
                        <div class="fw-semibold small text-muted">Bathrooms <span id="tc-ind-baths"></span></div>
                        <div>{{ $ag->bathrooms }}+</div>
                      </div>
                    </div>
                  </div>
                @endif

                @if($ag->move_in_date)
                  <div class="col-sm-6">
                    <div class="d-flex align-items-start gap-2">
                      <i class="fa-solid fa-calendar-day mt-1 text-success"></i>
                      <div>
                        <div class="fw-semibold small text-muted">Desired Move-in Date</div>
                        <div>{{ $ag->move_in_date }}</div>
                      </div>
                    </div>
                  </div>
                @endif

                @if($ag->lease_duration)
                  <div class="col-sm-6">
                    <div class="d-flex align-items-start gap-2">
                      <i class="fa-solid fa-file-signature mt-1 text-success"></i>
                      <div>
                        <div class="fw-semibold small text-muted">Desired Lease Length</div>
                        <div>{{ is_array($ag->lease_duration) ? implode(', ', $ag->lease_duration) : $ag->lease_duration }}</div>
                      </div>
                    </div>
                  </div>
                @endif

                @if($ag->furnished)
                  <div class="col-sm-6">
                    <div class="d-flex align-items-start gap-2">
                      <i class="fa-solid fa-couch mt-1 text-success"></i>
                      <div>
                        <div class="fw-semibold small text-muted">Furnished Preference <span id="tc-ind-furn"></span></div>
                        <div>{{ is_array($ag->furnished) ? implode(', ', $ag->furnished) : $ag->furnished }}</div>
                      </div>
                    </div>
                  </div>
                @endif

                @if($ag->has_pets)
                  <div class="col-sm-6">
                    <div class="d-flex align-items-start gap-2">
                      <i class="fa-solid fa-paw mt-1 text-success"></i>
                      <div>
                        <div class="fw-semibold small text-muted">Pets <span id="tc-ind-pets"></span></div>
                        <div>{{ $ag->has_pets }}
                          @if($ag->petType) — {{ $ag->petType }} @endif
                          @if($ag->petBreed) ({{ $ag->petBreed }}) @endif
                        </div>
                      </div>
                    </div>
                  </div>
                @endif

                @if($ag->parking_feature_garage)
                  <div class="col-sm-6">
                    <div class="d-flex align-items-start gap-2">
                      <i class="fa-solid fa-car mt-1 text-success"></i>
                      <div>
                        <div class="fw-semibold small text-muted">Parking</div>
                        <div>{{ is_array($ag->parking_feature_garage) ? implode(', ', $ag->parking_feature_garage) : $ag->parking_feature_garage }}</div>
                      </div>
                    </div>
                  </div>
                @endif

                @if($ag->non_negotiable_amenities)
                  <div class="col-12">
                    <div class="d-flex align-items-start gap-2">
                      <i class="fa-solid fa-star mt-1 text-warning"></i>
                      <div>
                        <div class="fw-semibold small text-muted">Required Amenities / Features</div>
                        <div>{{ is_array($ag->non_negotiable_amenities) ? implode(', ', $ag->non_negotiable_amenities) : $ag->non_negotiable_amenities }}</div>
                      </div>
                    </div>
                  </div>
                @endif

              </div>
            </div>
            <p class="text-muted small mt-2">
              <i class="fa-solid fa-circle-info me-1"></i>
              This panel is read-only. Criteria are sourced from the tenant's original listing.
              Match indicators (<span class="badge bg-success fw-normal" style="font-size:0.7em;">✓ Match</span>
              / <span class="badge bg-danger fw-normal" style="font-size:0.7em;">✗ Mismatch</span>)
              compare your offered terms (entered in prior steps) against these requirements.
              A <span class="badge bg-secondary fw-normal" style="font-size:0.7em;">?</span> means the field
              was not yet filled in.
            </p>
          </div>

          {{-- ═══════════════════════════════════════════════════════════════════════
               Step 48 — Property Description
               Saved as meta key: property_description
               Positioned after Media & Availability and before Rental Highlights.
          ═══════════════════════════════════════════════════════════════════════ --}}
          <div class="wizard-step" data-step="48">
            <h4><i class="fa-solid fa-align-left me-2"></i>Property Description</h4>
            <p class="text-muted small mb-3">
              Provide a compelling description of the rental property you are offering.
              This will be displayed to the tenant in the bid summary.
            </p>
            <div class="form-group">
              <label class="fw-bold" for="property_description">Property Description:</label>
              <textarea name="property_description" id="property_description"
                class="form-control" rows="8"
                placeholder="Describe the property, its standout features, neighbourhood, and why it is a great match for this tenant's criteria..."></textarea>
            </div>
          </div>

          {{-- ═══════════════════════════════════════════════════════════════════════
               Step 49 — Rental Highlights
               Saved as JSON via meta key: rental_highlights
          ═══════════════════════════════════════════════════════════════════════ --}}
          <div class="wizard-step" data-step="49">
            <h4><i class="fa-solid fa-star me-2"></i>Rental Highlights</h4>
            <p class="text-muted small mb-3">
              Select all features that highlight this rental property. Tenants will see
              these as a quick-glance summary when reviewing your bid.
            </p>
            @php
              $rentalHighlights = [
                ['name' => 'Move-in Ready',           'icon' => 'fa-solid fa-house-circle-check'],
                ['name' => 'Recently Renovated',       'icon' => 'fa-solid fa-paint-roller'],
                ['name' => 'New Appliances',           'icon' => 'fa-solid fa-blender'],
                ['name' => 'In-unit Laundry',          'icon' => 'fa-solid fa-jug-detergent'],
                ['name' => 'Covered Parking',          'icon' => 'fa-solid fa-square-parking'],
                ['name' => 'Gated / Secured Building', 'icon' => 'fa-solid fa-lock'],
                ['name' => 'Pet Friendly',             'icon' => 'fa-solid fa-paw'],
                ['name' => 'Pool Access',              'icon' => 'fa-solid fa-person-swimming'],
                ['name' => 'Fitness Center',           'icon' => 'fa-solid fa-dumbbell'],
                ['name' => 'Near Public Transit',      'icon' => 'fa-solid fa-bus'],
                ['name' => 'Near Top-Rated Schools',   'icon' => 'fa-solid fa-school'],
                ['name' => 'Furnished Option',         'icon' => 'fa-solid fa-couch'],
                ['name' => 'Utilities Included',       'icon' => 'fa-solid fa-bolt'],
                ['name' => 'Short-term Lease Available','icon' => 'fa-solid fa-calendar-check'],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Select all that apply:</label>
              <select class="grid-picker" name="rental_highlights[]"
                style="justify-content: flex-start;" multiple>
                <option value="">Select</option>
                @foreach ($rentalHighlights as $rh)
                  <option value="{{ $rh['name'] }}" data-target=""
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $rh['icon'] }}"></i>'>
                    {{ $rh['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>

          {{-- ═══════════════════════════════════════════════════════════════════════
               Step 50 — Leasing Incentives
               Saved as JSON via meta key: leasing_incentives
               "Other" free-text via meta key: leasing_incentives_other
          ═══════════════════════════════════════════════════════════════════════ --}}
          <div class="wizard-step" data-step="50">
            <h4><i class="fa-solid fa-gift me-2"></i>Leasing Incentives</h4>
            <p class="text-muted small mb-3">
              Indicate any incentives you are offering to attract the right tenant.
              These will be displayed prominently in your bid.
            </p>
            @php
              $leasingIncentives = [
                ['name' => 'First Month Free',            'target' => '', 'icon' => 'fa-solid fa-percent'],
                ['name' => 'Reduced Security Deposit',    'target' => '', 'icon' => 'fa-solid fa-shield-halved'],
                ['name' => 'No Application Fee',          'target' => '', 'icon' => 'fa-solid fa-file-circle-xmark'],
                ['name' => 'Free Parking Included',       'target' => '', 'icon' => 'fa-solid fa-square-parking'],
                ['name' => 'Free Storage Unit',           'target' => '', 'icon' => 'fa-solid fa-box-archive'],
                ['name' => 'Complimentary Move-in Package','target'=> '', 'icon' => 'fa-solid fa-gift'],
                ['name' => 'Flexible Move-in Date',       'target' => '', 'icon' => 'fa-solid fa-calendar-days'],
                ['name' => 'Other Concession',            'target' => '.leasingIncentivesOtherWrap', 'icon' => 'fa-regular fa-circle-check'],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Select all that apply:</label>
              <select class="grid-picker" name="leasing_incentives[]"
                style="justify-content: flex-start;" multiple>
                <option value="">Select</option>
                @foreach ($leasingIncentives as $li)
                  <option value="{{ $li['name'] }}" data-target="{{ $li['target'] }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);"
                    data-icon='<i class="{{ $li['icon'] }}"></i>'>
                    {{ $li['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group main leasingIncentivesOtherWrap d-none">
              <label class="fw-bold">Describe the other concession or incentive:</label>
              <input type="text" name="leasing_incentives_other"
                class="form-control has-icon"
                data-icon="fa-regular fa-circle-check"
                placeholder="e.g. Two months of free HOA dues">
            </div>
          </div>

          {{-- ═══════════════════════════════════════════════════════════════════════
               Step 51 — Location DNA Compatibility (read-only)
               Reads location_dna_preferences meta from the parent TenantCriteriaAuction.
               Agents cannot edit this data from this form.
          ═══════════════════════════════════════════════════════════════════════ --}}
          <div class="wizard-step" data-step="51">
            <h4><i class="fa-solid fa-map-location-dot me-2"></i>Location DNA Compatibility</h4>
            <p class="text-muted small mb-3">
              The tenant's stated location preferences are shown below.
              Verify that the property you are offering falls within their preferred area.
              This panel is <strong>read-only</strong> — location preferences are set by the tenant.
            </p>
            @php
              $dnaPrefRaw  = @$auction->info('location_dna_preferences');
              $dnaPrefs    = $dnaPrefRaw ? json_decode($dnaPrefRaw, true) : null;
              $dnaCities   = $dnaPrefs['cities']          ?? [];
              $dnaZips     = $dnaPrefs['zip_codes']       ?? [];
              $dnaNeigh    = $dnaPrefs['neighborhoods']   ?? [];
              $dnaRadii    = $dnaPrefs['radius_searches'] ?? [];
              // polygons is a separate key from radius_searches — user-drawn custom areas
              $dnaPolys    = $dnaPrefs['polygons']        ?? [];
              $dnaFlex     = $dnaPrefs['flexible_location'] ?? false;
              $dnaNotes    = $dnaPrefs['location_notes']  ?? '';
              $hasDnaData  = !empty($dnaCities) || !empty($dnaZips) || !empty($dnaNeigh)
                          || !empty($dnaRadii) || !empty($dnaPolys) || $dnaFlex || $dnaNotes;
            @endphp

            @if($hasDnaData)
              <div class="card border-0 bg-light p-3">

                @if($dnaFlex)
                  <div class="alert alert-info py-2 mb-3 small">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    The tenant has indicated they are <strong>flexible on location</strong>.
                  </div>
                @endif

                @if(!empty($dnaCities))
                  <div class="mb-3">
                    <div class="fw-semibold small text-muted mb-1">
                      <i class="fa-solid fa-city me-1"></i>Preferred Cities
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                      @foreach($dnaCities as $city)
                        <span class="badge rounded-pill"
                          style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;">
                          {{ is_array($city) ? ($city['name'] ?? $city['city'] ?? '') : $city }}
                        </span>
                      @endforeach
                    </div>
                  </div>
                @endif

                @if(!empty($dnaZips))
                  <div class="mb-3">
                    <div class="fw-semibold small text-muted mb-1">
                      <i class="fa-solid fa-map-pin me-1"></i>Preferred ZIP Codes
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                      @foreach($dnaZips as $zip)
                        <span class="badge rounded-pill"
                          style="background:#dcfce7;color:#15803d;border:1px solid #86efac;">
                          {{ $zip }}
                        </span>
                      @endforeach
                    </div>
                  </div>
                @endif

                @if(!empty($dnaNeigh))
                  <div class="mb-3">
                    <div class="fw-semibold small text-muted mb-1">
                      <i class="fa-solid fa-house-chimney me-1"></i>Preferred Neighbourhoods
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                      @foreach($dnaNeigh as $nb)
                        <span class="badge rounded-pill"
                          style="background:#fef9c3;color:#854d0e;border:1px solid #fde047;">
                          {{ is_array($nb) ? ($nb['name'] ?? '') : $nb }}
                        </span>
                      @endforeach
                    </div>
                  </div>
                @endif

                @if(!empty($dnaRadii))
                  <div class="mb-3">
                    <div class="fw-semibold small text-muted mb-1">
                      <i class="fa-solid fa-circle-dot me-1"></i>Radius Searches
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                      @foreach($dnaRadii as $r)
                        <span class="badge rounded-pill"
                          style="background:#fdf4ff;color:#7e22ce;border:1px solid #d8b4fe;">
                          @if(is_array($r) && isset($r['radius_miles']))
                            {{ $r['radius_miles'] }} mi radius
                            @if(isset($r['center_city'])) around {{ $r['center_city'] }}@endif
                          @else
                            {{ is_array($r) ? (implode(', ', array_filter([$r['label'] ?? '', $r['name'] ?? ''])) ?: json_encode($r)) : $r }}
                          @endif
                        </span>
                      @endforeach
                    </div>
                  </div>
                @endif

                @if(!empty($dnaPolys))
                  <div class="mb-3">
                    <div class="fw-semibold small text-muted mb-1">
                      <i class="fa-solid fa-draw-polygon me-1"></i>Custom Drawn Areas
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                      @foreach($dnaPolys as $poly)
                        <span class="badge rounded-pill"
                          style="background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;">
                          <i class="fa-solid fa-vector-square me-1" style="font-size:0.7em;"></i>
                          @if(is_array($poly) && isset($poly['label']))
                            {{ $poly['label'] }}
                          @elseif(is_array($poly) && isset($poly['name']))
                            {{ $poly['name'] }}
                          @else
                            Custom Area {{ $loop->iteration }}
                          @endif
                        </span>
                      @endforeach
                    </div>
                  </div>
                @endif

                @if($dnaNotes)
                  <div class="mb-2">
                    <div class="fw-semibold small text-muted mb-1">
                      <i class="fa-solid fa-note-sticky me-1"></i>Location Notes
                    </div>
                    <div class="small">{{ $dnaNotes }}</div>
                  </div>
                @endif

              </div>
            @else
              <div class="alert alert-secondary py-3">
                <i class="fa-solid fa-location-crosshairs me-2"></i>
                No Location DNA preferences were provided.
              </div>
            @endif
          </div>

          {{-- ═══════════════════════════════════════════════════════════════════════
               Step 52 — Preview Tenant View + Review & Submit
               Provides a lightweight read-only summary of the bid before final
               submission.  Preview is generated client-side from current form values.
               Only the bid's author sees this; the form is not yet saved at this point.
          ═══════════════════════════════════════════════════════════════════════ --}}
          <div class="wizard-step" data-step="52">
            <h4><i class="fa-solid fa-eye me-2"></i>Preview &amp; Submit</h4>
            <p class="text-muted small mb-3">
              Review the key details of your rental offer before submitting.
              Click <strong>Preview Tenant View</strong> to see how your bid will appear to the tenant,
              then click <strong>Save</strong> to submit.
            </p>

            {{-- Preview button triggers Bootstrap modal --}}
            <button type="button" class="btn btn-outline-primary mb-4"
              id="tenantPreviewBtn" data-bs-toggle="modal" data-bs-target="#tenantPreviewModal">
              <i class="fa-solid fa-eye me-1"></i> Preview Tenant View
            </button>

            {{-- Read-only summary card --}}
            <div class="card border-0 bg-light p-3 mb-3">
              <div class="fw-semibold mb-2">
                <i class="fa-solid fa-house me-1"></i> Offer Summary
              </div>
              <div class="row g-2 small">
                <div class="col-sm-6">
                  <span class="text-muted">Address:</span>
                  <span id="prev-address" class="ms-1">—</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Offered Rent:</span>
                  <span id="prev-price" class="ms-1">—</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Lease Start:</span>
                  <span id="prev-leaseDate" class="ms-1">—</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Bedrooms:</span>
                  <span id="prev-bedroom" class="ms-1">—</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Bathrooms:</span>
                  <span id="prev-bathrooms" class="ms-1">—</span>
                </div>
                <div class="col-sm-6">
                  <span class="text-muted">Property Type:</span>
                  <span id="prev-proptype" class="ms-1">—</span>
                </div>
                <div class="col-12 mt-1">
                  <span class="text-muted">Highlights:</span>
                  <span id="prev-highlights" class="ms-1">—</span>
                </div>
                <div class="col-12">
                  <span class="text-muted">Incentives:</span>
                  <span id="prev-incentives" class="ms-1">—</span>
                </div>
              </div>
            </div>

            <div class="alert alert-success py-2 small">
              <i class="fa-solid fa-circle-check me-1"></i>
              When you are ready, click <strong>Save</strong> below to submit your rental offer.
            </div>
          </div>

          {{-- ── Preview Modal (Bootstrap 5) ──────────────────────────────────── --}}
          <div class="modal fade" id="tenantPreviewModal" tabindex="-1"
            aria-labelledby="tenantPreviewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
              <div class="modal-content">
                <div class="modal-header bg-success text-white">
                  <h5 class="modal-title" id="tenantPreviewModalLabel">
                    <i class="fa-solid fa-eye me-2"></i>Tenant View Preview
                  </h5>
                  <button type="button" class="btn-close btn-close-white"
                    data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <p class="text-muted small mb-3">
                    This is an approximation of how your offer will appear to the tenant
                    after submission.  Some fields may differ slightly in the final view.
                  </p>
                  <div id="modalPreviewContent"></div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
              </div>
            </div>
          </div>

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
    function show_card_field() {
      $('.card-field').removeClass('d-none');
    }
  </script>
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
        $('.resFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
          $(this).show();
        });
        $('.commercialFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
          $(this).hide();
        });

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

        $('.commercialFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', false);
          $(this).show();
        });
        $('.resFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
          $(this).hide();
        });


      } else {
        $('.property_items').val("");
        $('.property_items').parent().children('.option-container').removeClass('active');
        $('.residential-length').hide();
        $('.income-length').hide();
        $('.commercial-length').hide();
        $('.commercialFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
          $(this).hide();
        });
        $('.resFields').each(function() {
          $(this).find('select, input ,textarea').prop('disabled', true);
          $(this).hide();
        });

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
            $(target).find('input').val("");
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
        });
        StepWizard.setStep();
        property_listed;
        $('#property_listed').on('change', function() {
          property_listed = $(this).val();
        });
        property_type;
                $('#property_type').on('change', function() {
                    property_type = $(this).val();
                });
        $('.wizard-step-next').click(function(e) {
          console.log(StepWizard.currentStep)
          if (v.form()) {
            if ($('.wizard-step.active').next().is('.wizard-step')) {

              $('.wizard-step.active').removeClass('active');
              if (StepWizard.currentStep == 5 && property_listed ==
                'Yes') {
                StepWizard.nextStep = 46;
                StepWizard.backStep = 5;
              } 
              else if (StepWizard.currentStep == 2 && property_listed ==
                'No') {
                StepWizard.nextStep = 6;
                StepWizard.backStep = 2;
              } else if (StepWizard.currentStep == 10 && (property_type == 'Commercial Property' || property_type == 'Residential Property')) {
                    StepWizard.nextStep = 5;
                    StepWizard.backStep = 10;

                }else if (StepWizard.currentStep == 5 && (property_type == 'Commercial Property' || property_type == 'Residential Property')) {
                    StepWizard.nextStep = 4;
                    StepWizard.backStep = 5;

                }else if (StepWizard.currentStep == 4 && (property_type == 'Commercial Property' || property_type == 'Residential Property')) {
                    StepWizard.nextStep = 11;
                    StepWizard.backStep = 4;

                }
              else if (StepWizard.currentStep == 15 && property_type ==
                    'Commercial Property') {
                    StepWizard.nextStep = 17;
                    StepWizard.backStep = 15;

                }else if (StepWizard.currentStep == 12 && property_type ==
                    'Commercial Property') {
                    StepWizard.nextStep = 14;
                    StepWizard.backStep = 12;

                } else if (StepWizard.currentStep == 19 && property_type ==
                    'Commercial Property') {
                    StepWizard.nextStep = 23;
                    StepWizard.backStep = 19;

                } else if (StepWizard.currentStep == 24 && property_type ==
                    'Commercial Property') {
                    StepWizard.nextStep = 26;
                    StepWizard.backStep = 24;

                } else if (StepWizard.currentStep == 28 && property_type ==
                    'Commercial Property') {
                    StepWizard.nextStep = 31;
                    StepWizard.backStep = 28;

                } else if (StepWizard.currentStep == 34 && property_type ==
                    'Commercial Property') {
                    StepWizard.nextStep = 37;
                    StepWizard.backStep = 34;

                } else if (StepWizard.currentStep == 43 && property_type ==
                    'Commercial Property') {
                    StepWizard.nextStep = 45;
                    StepWizard.backStep = 43;

                } else if (StepWizard.currentStep == 30 && property_type ==
                    'Residential Property') {
                    StepWizard.nextStep = 32;
                    StepWizard.backStep = 30;
                    
                } else if (StepWizard.currentStep == 36 && property_type ==
                    'Residential Property') {
                    StepWizard.nextStep = 39;
                    StepWizard.backStep = 36;
                } 
                else if (StepWizard.currentStep == 40 && property_type ==
                    'Residential Property') {
                    StepWizard.nextStep = 44;
                    StepWizard.backStep = 40;
                } else {
                StepWizard.backStep = StepWizard.currentStep;

              }
              $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
              StepWizard.setStep();
              //   if (StepWizard.currentStep == 50 &&
              //     property_type == 'Residential Property'
              //   ) {
              //     $('.wizard-step-next').hide();
              //     $('.wizard-step-finish').show();
              //   }
            }
          }
        });

        $('.wizard-step-back').click(function(e) {
          if ($('.wizard-step.active').prev().is('.wizard-step')) {

            $('.wizard-step.active').removeClass('active');
            $('[ data-step="' + StepWizard.backStep + '"]').addClass("active");
            StepWizard.setStep();
            console.log(StepWizard.currentStep)
            if (StepWizard.currentStep == 46 && property_listed ==
              'Yes') {
              StepWizard.backStep = 5;
            } else if (StepWizard.currentStep == 6 && property_listed ==
              'No') {
              StepWizard.backStep = 2;
            } else if (StepWizard.currentStep == 5 && (property_type == 'Commercial Property' || property_type == 'Residential Property')) {
                StepWizard.backStep = 10;
            }else if (StepWizard.currentStep == 4 && (property_type == 'Commercial Property' || property_type == 'Residential Property')) {
                StepWizard.backStep = 5;
            }else if (StepWizard.currentStep == 17 && property_type ==
                'Commercial Property') {
                StepWizard.backStep = 15;
            } else if (StepWizard.currentStep == 14 && property_type ==
                'Commercial Property') {
                StepWizard.backStep = 12;
            } else if (StepWizard.currentStep == 23 && property_type ==
                'Commercial Property') {
                StepWizard.backStep = 19;
            } else if (StepWizard.currentStep == 26 && property_type ==
                'Commercial Property') {
                StepWizard.backStep = 24;
            } else if (StepWizard.currentStep == 31 && property_type ==
                'Commercial Property') {
                StepWizard.backStep = 28;
            }else if (StepWizard.currentStep == 37 && property_type ==
                'Commercial Property') {
                StepWizard.backStep = 34;
            } else if (StepWizard.currentStep == 45 && property_type ==
                'Commercial Property') {
                StepWizard.backStep = 43;
            }  else if (StepWizard.currentStep == 32 && property_type ==
                'Residential Property') {
                StepWizard.backStep = 30;
            } else if (StepWizard.currentStep == 39 && property_type ==
                'Residential Property') {
                StepWizard.backStep = 35;
            } else if (StepWizard.currentStep == 44 && property_type ==
                'Residential Property') {
                StepWizard.backStep = 40;
            } else {
              StepWizard.backStep = StepWizard.currentStep - 1;
            }
          }
        });

        $('.wizard-step-finish').click(function(e) {
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
        console.log(t);
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
            var lat = place.geometry['locations'].lat();
            var lng = place.geometry['locations'].lng();
            if (t == "counties") {
              $('#lat').val(lat);
              $('#long').val(lng);
            }
          }
        });
      }
    }
    var leaseTerms = $('#leaseTermsOptions');
    $('#leaseTermsOpt').change(function() {
      //Disply and hide a div
      ($(this).val().includes('3 months') || $(this).val().includes('6 months') || $(this).val().includes(
          '9 months') || $(this).val().includes('1 year') || $(this).val().includes('2 years') || $(this).val()
        .includes('3-5 years') || $(this).val().includes('5+ years') || $(this).val().includes('Month to Month') || $(
          this).val().includes('Other')) ? leaseTerms.show():
        leaseTerms.hide()
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
  <x-google-maps-script :callback="'initialize'" />

  {{-- ── Preview Tenant View — populate summary card and modal on step 52 ── --}}
  <script>
    (function () {
      /* Escape HTML special chars before injecting into innerHTML */
      function escHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
      }
      function getFieldVal(name) {
        var el = document.querySelector('[name="' + name + '"]');
        return el ? (el.value || '') : '';
      }
      function getMultiSelected(name) {
        var sel = document.querySelector('[name="' + name + '[]"]');
        if (!sel) return [];
        return Array.from(sel.options)
          .filter(function(o){ return o.selected && o.value; })
          .map(function(o){ return o.value; });
      }
      function populatePreview() {
        var address  = [getFieldVal('address'), getFieldVal('city'), getFieldVal('state')].filter(Boolean).join(', ');
        var price    = getFieldVal('price');
        var leaseDate= getFieldVal('leaseDate');
        var bedroom  = getFieldVal('bedroom') || getFieldVal('other_bedrooms');
        var baths    = getFieldVal('bathrooms') || getFieldVal('other_bathrooms');
        var propType = getFieldVal('property_type');
        var desc     = getFieldVal('property_description');
        var highlights = getMultiSelected('rental_highlights');
        var incentives = getMultiSelected('leasing_incentives');

        /* Summary card (step 52) */
        var fmt = function(v){ return v || '—'; };
        document.getElementById('prev-address').textContent     = fmt(address);
        document.getElementById('prev-price').textContent       = price ? '$' + Number(price).toLocaleString() : '—';
        document.getElementById('prev-leaseDate').textContent   = fmt(leaseDate);
        document.getElementById('prev-bedroom').textContent     = fmt(bedroom);
        document.getElementById('prev-bathrooms').textContent   = fmt(baths);
        document.getElementById('prev-proptype').textContent    = fmt(propType);
        document.getElementById('prev-highlights').textContent  = highlights.length ? highlights.join(', ') : '—';
        document.getElementById('prev-incentives').textContent  = incentives.length ? incentives.join(', ') : '—';

        /* Modal content — all user-input values wrapped in escHtml() */
        var html = '<div class="table-responsive">'
          + '<table class="table table-bordered table-sm">'
          + '<tbody>'
          + '<tr><th class="small">Property Address</th><td class="small">' + escHtml(address || '—') + '</td></tr>'
          + '<tr><th class="small">Offered Monthly Rent</th><td class="small">' + (price ? '$' + Number(price).toLocaleString() : '—') + '</td></tr>'
          + '<tr><th class="small">Lease Start Date</th><td class="small">' + escHtml(leaseDate || '—') + '</td></tr>'
          + '<tr><th class="small">Bedrooms</th><td class="small">' + escHtml(bedroom || '—') + '</td></tr>'
          + '<tr><th class="small">Bathrooms</th><td class="small">' + escHtml(baths || '—') + '</td></tr>'
          + '<tr><th class="small">Property Type</th><td class="small">' + escHtml(propType || '—') + '</td></tr>';

        if (desc) {
          html += '<tr><th class="small">Property Description</th><td class="small" style="white-space:pre-wrap;">' + escHtml(desc) + '</td></tr>';
        }
        if (highlights.length) {
          html += '<tr><th class="small">Rental Highlights</th><td class="small">'
            + highlights.map(function(h){ return '<span class="badge bg-success me-1">' + escHtml(h) + '</span>'; }).join('')
            + '</td></tr>';
        }
        if (incentives.length) {
          html += '<tr><th class="small">Leasing Incentives</th><td class="small">'
            + incentives.map(function(i){ return '<span class="badge bg-primary me-1">' + escHtml(i) + '</span>'; }).join('')
            + '</td></tr>';
        }
        html += '</tbody></table></div>';
        document.getElementById('modalPreviewContent').innerHTML = html;
      }

      /* Populate when user arrives at step 52 via the wizard's setStep hook */
      var origSetStep = null;
      $(function(){
        /* Re-populate every time the preview step becomes active */
        $(document).on('click', '.wizard-step-next, .wizard-step-back', function(){
          setTimeout(function(){
            if ($('.wizard-step.active').data('step') == 52) {
              populatePreview();
            }
          }, 50);
        });
        /* Also populate when the modal is about to open */
        document.getElementById('tenantPreviewModal') && document.getElementById('tenantPreviewModal').addEventListener('show.bs.modal', function(){
          populatePreview();
        });
      });
    })();
  </script>

  {{-- ═══════════════════════════════════════════════════════════════════════
       Step 47 — Tenant Criteria Match Indicators
       Compares bid form values (price, bedroom, bathrooms, petsOpt, furnishings)
       entered in earlier steps against auction criteria embedded as data-* attrs
       on #tc-criteria-card. Uses TenantBidMatchScoreHelper field groupings as
       the conceptual baseline; direct field comparison replaces the helper here
       since it targets TenantAgentAuction (not TenantCriteriaAuction) bids.
  ═══════════════════════════════════════════════════════════════════════ --}}
  <script>
    (function () {
      /**
       * runTCMatchIndicators — fires when the user reaches step 47.
       * Reads offered terms from prior form steps and compares against
       * the tenant's auction criteria embedded on #tc-criteria-card.
       * Badge semantics:
       *   ✓ Match   (green)  — offered value satisfies the criterion
       *   ✗ Mismatch(red)    — offered value does not satisfy the criterion
       *   ?         (grey)   — criterion or bid field is blank/not yet filled
       */
      function runTCMatchIndicators() {
        var card = document.getElementById('tc-criteria-card');
        if (!card) return;

        var maxRent  = parseFloat(card.dataset.maxRent)  || 0;
        var minBeds  = parseInt(card.dataset.minBeds)    || 0;
        var minBaths = parseFloat(card.dataset.minBaths) || 0;
        var critPets = (card.dataset.critPets || '').toLowerCase();
        var critFurn = (card.dataset.critFurn || '').split('|').map(function(s){ return s.trim().toLowerCase(); }).filter(Boolean);

        function badge(matched) {
          if (matched === null) return '<span class="badge bg-secondary ms-1 fw-normal" style="font-size:0.68em;">?</span>';
          if (matched)          return '<span class="badge bg-success ms-1 fw-normal" style="font-size:0.68em;">✓ Match</span>';
          return                       '<span class="badge bg-danger ms-1 fw-normal" style="font-size:0.68em;">✗ Mismatch</span>';
        }

        function setInd(id, matched) {
          var el = document.getElementById(id);
          if (el) el.innerHTML = badge(matched);
        }

        /* Rent — bid price must be ≤ tenant's max budget */
        var rentEl = document.querySelector('input[name="price"]');
        var rent   = rentEl ? parseFloat(rentEl.value) : 0;
        setInd('tc-ind-rent', (maxRent > 0 && rent > 0) ? (rent <= maxRent) : null);

        /* Bedrooms — offered must be ≥ tenant's minimum */
        var bedEl = document.querySelector('select[name="bedroom"]');
        var beds  = bedEl ? parseInt(bedEl.value) : 0;
        setInd('tc-ind-beds', (minBeds > 0 && beds > 0) ? (beds >= minBeds) : null);

        /* Bathrooms — offered must be ≥ tenant's minimum */
        var bathEl = document.querySelector('select[name="bathrooms"]');
        var baths  = bathEl ? parseFloat(bathEl.value) : 0;
        setInd('tc-ind-baths', (minBaths > 0 && baths > 0) ? (baths >= minBaths) : null);

        /* Pets — if tenant requires no-pets property, bid must not allow pets */
        var petsEl  = document.querySelector('select[name="petsOpt"] option:checked');
        var bidPets = petsEl ? petsEl.value.toLowerCase() : '';
        if (critPets && bidPets) {
          var petsFail = (critPets.indexOf('no') !== -1) && (bidPets.indexOf('no') === -1);
          setInd('tc-ind-pets', !petsFail);
        } else {
          setInd('tc-ind-pets', null);
        }

        /* Furnished — offered preference must overlap tenant's requirement */
        var furnEl  = document.querySelector('select[name="furnishings"] option:checked');
        var bidFurn = furnEl ? furnEl.value.trim().toLowerCase() : '';
        if (critFurn.length && bidFurn) {
          var furnMatch = critFurn.indexOf(bidFurn) !== -1
            || critFurn.indexOf('either') !== -1
            || bidFurn === 'either';
          setInd('tc-ind-furn', furnMatch);
        } else {
          setInd('tc-ind-furn', null);
        }
      }

      window.runTCMatchIndicators = runTCMatchIndicators;

      /* Hook into the wizard navigation buttons */
      $(function () {
        $(document).on('click', '.wizard-step-next, .wizard-step-back', function () {
          setTimeout(function () {
            if ($('.wizard-step.active').data('step') == 47) {
              runTCMatchIndicators();
            }
          }, 60);
        });
      });
    })();
  </script>
@endpush
