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
        <form class="p-4 pt-0 mainform" action="{{ route('saveSABid') }}" method="POST" enctype="multipart/form-data">
          @csrf
          <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
          {{-- @php
            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
          @endphp --}}

          <div class="wizard-step">
            @php
              $listing_terms = [['name' => '3 Months', 'target' => ''], ['name' => '6 Months', 'target' => ''], ['name' => '9 Months', 'target' => ''], ['name' => '12 Months', 'target' => ''], ['name' => 'Other', 'target' => '.custom_terms']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">What is the proposed timeframe outlined in the Seller Agency Agreement?</label>
              <select class="grid-picker" name="listing_terms" id="listing_terms" style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($listing_terms as $listing_term)
                  <option value="{{ $listing_term['name'] }}" data-target="{{ $listing_term['target'] }}"
                    class="card flex-row " style="width:calc(33.3% - 10px);"
                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                    {{ $listing_term['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group custom_terms d-none">
              <label class="fw-bold">What is the proposed timeframe outlined in the Seller Agency Agreement? </label>
              <input type="text" class="form-control has-icon" name="custom_terms" data-icon="fa-solid fa-calendar-days"
                id="custom_terms" required />
            </div>
          </div>
          @php
            $rawPropertyType = $auction->get->property_type ?? 'Residential Property';
            $isIncomeCommercial = in_array($rawPropertyType, ['Income Property', 'Commercial Property', 'Business Opportunity']);
            $isCommercialBusiness = in_array($rawPropertyType, ['Commercial Property', 'Business Opportunity']);
            $isResidentialIncomeVacant = in_array($rawPropertyType, ['Residential Property', 'Vacant Land', 'Income Property']);
            $get = $auction->get;
            $alpineCompData = json_encode([
              'purchase_fee_type'              => old('purchase_fee_type', $get->purchase_fee_type ?? ''),
              'commission_structure'           => old('commission_structure', $get->commission_structure ?? ''),
              'commission_structure_type'      => old('commission_structure_type', $get->commission_structure_type ?? ''),
              'interested_purchase_fee_type'   => old('interested_purchase_fee_type', $get->interested_purchase_fee_type ?? ''),
              'seller_leasing_fee_type'        => old('seller_leasing_fee_type', $get->seller_leasing_fee_type ?? ''),
              'interested_lease_option_agreement' => old('interested_lease_option_agreement', $get->interested_lease_option_agreement ?? ''),
              'early_termination_fee_option'   => old('early_termination_fee_option', $get->early_termination_fee_option ?? ''),
              'retainer_fee_option'            => old('retainer_fee_option', $get->retainer_fee_option ?? ''),
              'agency_agreement_timeframe'     => old('agency_agreement_timeframe', $get->agency_agreement_timeframe ?? ''),
              'brokerage_relationship'         => old('brokerage_relationship', $get->brokerage_relationship ?? ''),
            ]);
          @endphp
          <div class="wizard-step" x-data="{{ $alpineCompData }}">

            <h5 class="fw-bold mb-3">Broker Compensation &amp; Agency Agreement Terms</h5>
            <div class="alert alert-info bg-light-info border-info mb-4">
              <strong>Complete the compensation terms that apply. Listing values are pre-filled as a starting point — adjust any field to reflect your proposed terms.</strong>
            </div>

            {{-- Seller's Broker Purchase Fee --}}
            <div class="form-group mb-4">
              <label class="fw-bold d-flex align-items-center">
                Seller's Broker Purchase Fee: <span class="text-danger ms-1">*</span>
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                  title="Choose how the Seller's Broker will be compensated.">
                  <i class="fa-solid fa-circle-info"></i>
                </span>
              </label>
              <div class="input-cover mt-2">
                <select x-model="purchase_fee_type" name="purchase_fee_type" class="form-control has-icon"
                  data-icon="fa-solid fa-file-invoice-dollar">
                  <option value="">Select</option>
                  <option value="percentage">Percentage of the Total Purchase Price</option>
                  <option value="flat">Flat Fee</option>
                  <option value="combo">Percentage of the Total Purchase Price + Flat Fee</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="mt-3">
                <div x-show="purchase_fee_type === 'flat'">
                  <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="text" name="purchase_fee_flat" class="form-control"
                      placeholder="Enter flat fee amount (e.g., 5000)"
                      value="{{ old('purchase_fee_flat', $get->purchase_fee_flat ?? '') }}">
                  </div>
                </div>
                <div x-show="purchase_fee_type === 'percentage'">
                  <div class="input-group">
                    <input type="number" name="purchase_fee_percentage" class="form-control"
                      placeholder="Enter percentage of total purchase price (e.g., 6)"
                      value="{{ old('purchase_fee_percentage', $get->purchase_fee_percentage ?? '') }}">
                    <span class="input-group-text">%</span>
                  </div>
                </div>
                <div x-show="purchase_fee_type === 'combo'">
                  <div class="row g-2">
                    <div class="col-md-6">
                      <div class="input-group">
                        <input type="number" name="purchase_fee_percentage_combo" class="form-control"
                          placeholder="Enter percentage (e.g., 2)"
                          value="{{ old('purchase_fee_percentage_combo', $get->purchase_fee_percentage_combo ?? '') }}">
                        <span class="input-group-text">%</span>
                      </div>
                    </div>
                    <div class="col-md-1 text-center pt-2">+</div>
                    <div class="col-md-5">
                      <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" name="purchase_fee_flat_combo" class="form-control"
                          placeholder="Enter flat fee (e.g., 2000)"
                          value="{{ old('purchase_fee_flat_combo', $get->purchase_fee_flat_combo ?? '') }}">
                      </div>
                    </div>
                  </div>
                </div>
                <div x-show="purchase_fee_type === 'other'">
                  <input type="text" name="purchase_fee_other" class="form-control mt-2"
                    placeholder="Enter commission structure (e.g., Tiered fee: 5% on the first $500,000)"
                    value="{{ old('purchase_fee_other', $get->purchase_fee_other ?? '') }}">
                </div>
              </div>
              {{-- Hidden total_comission used for bid->price --}}
              <input type="hidden" name="total_comission" value="{{ old('total_comission', $get->purchase_fee_percentage ?? $get->purchase_fee_flat ?? $get->purchase_fee_other ?? 0) }}">
            </div>

            @if($isIncomeCommercial)
            <div class="form-group mb-4">
              <label class="fw-bold">Nominal Consideration Fee:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                  title="If the property is transferred for nominal value, enter the flat fee the Seller's Broker will be paid.">
                  <i class="fa-solid fa-circle-info"></i>
                </span>
              </label>
              <div class="input-group mt-2">
                <span class="input-group-text">$</span>
                <input type="text" name="nominal" class="form-control"
                  placeholder="Enter nominal consideration fee amount (e.g., 1000)"
                  value="{{ old('nominal', $get->nominal ?? '') }}">
              </div>
            </div>
            @endif

            {{-- Buyer's Broker Commission Structure --}}
            <div class="form-group mb-4">
              <label class="fw-bold d-flex align-items-center">
                Buyer's Broker Commission Structure:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                  title="Select how the Buyer's Broker will be compensated.">
                  <i class="fa-solid fa-circle-info"></i>
                </span>
              </label>
              <div class="input-cover mt-2">
                <select x-model="commission_structure" name="commission_structure" class="form-control has-icon"
                  data-icon="fa-solid fa-file-invoice-dollar">
                  <option value="">Select</option>
                  <option value="Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission">Seller's Broker to Compensate Buyer's Broker from Seller's Broker Commission</option>
                  <option value="Seller to Pay Buyer's Broker Separately">Seller to Pay Buyer's Broker Separately</option>
                  <option value="No Compensation Offered to the Buyer's Broker">No Compensation Offered to the Buyer's Broker</option>
                </select>
              </div>
            </div>

            {{-- Buyer's Broker Commission Fee (conditional) --}}
            <template x-if="commission_structure === 'Seller\'s Broker to Compensate Buyer\'s Broker from Seller\'s Broker Commission' || commission_structure === 'Seller to Pay Buyer\'s Broker Separately'">
              <div class="form-group mb-4">
                <label class="fw-bold">Buyer's Broker Commission Fee:</label>
                <div class="input-cover mt-2">
                  <select x-model="commission_structure_type" name="commission_structure_type" class="form-control has-icon"
                    data-icon="fa-solid fa-file-invoice-dollar">
                    <option value="">Select</option>
                    <option value="Percentage of the Total Purchase Price">Percentage of the Total Purchase Price</option>
                    <option value="Flat Fee">Flat Fee</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div class="mt-3">
                  <div x-show="commission_structure_type === 'Flat Fee'">
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="text" name="commission_structure_type_fee_flat" class="form-control"
                        placeholder="Enter flat fee amount (e.g., 4000)"
                        value="{{ old('commission_structure_type_fee_flat', $get->commission_structure_type_fee_flat ?? '') }}">
                    </div>
                  </div>
                  <div x-show="commission_structure_type === 'Percentage of the Total Purchase Price'">
                    <div class="input-group">
                      <input type="number" name="commission_structure_type_fee_percentage" class="form-control"
                        placeholder="Enter percentage (e.g., 6)"
                        value="{{ old('commission_structure_type_fee_percentage', $get->commission_structure_type_fee_percentage ?? '') }}">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                  <div x-show="commission_structure_type === 'other'">
                    <input type="text" name="commission_structure_type_fee_other" class="form-control mt-2"
                      placeholder="Enter compensation for the Buyer's Broker"
                      value="{{ old('commission_structure_type_fee_other', $get->commission_structure_type_fee_other ?? '') }}">
                  </div>
                </div>
              </div>
            </template>

            {{-- Interested in Offering a Lease Agreement --}}
            <div class="form-group mb-4">
              <label class="fw-bold">Interested in Offering a Lease Agreement:</label>
              <div class="input-cover mt-2">
                <select x-model="interested_purchase_fee_type" name="interested_purchase_fee_type" class="form-control has-icon"
                  data-icon="fa-solid fa-file-invoice-dollar">
                  <option value="">Select</option>
                  <option value="Yes">Yes</option>
                  <option value="No">No</option>
                </select>
              </div>
            </div>
            <div x-show="interested_purchase_fee_type === 'Yes'">
              <div class="form-group mb-4">
                <label class="fw-bold">Seller's Broker Leasing Fee:</label>
                <div class="input-cover mt-2">
                  <select x-model="seller_leasing_fee_type" name="seller_leasing_fee_type" class="form-control has-icon"
                    data-icon="fa-solid fa-file-invoice-dollar">
                    <option value="">Select</option>
                    @if($isResidentialIncomeVacant)
                      <option value="Percentage of the Rent Due Each Rental Period">Percentage of the Rent Due Each Rental Period</option>
                      <option value="Percentage of the Gross Lease Value">Percentage of the Gross Lease Value</option>
                      <option value="Percentage of the First Month's Rent">Percentage of the First Month's Rent</option>
                      <option value="Flat Fee">Flat Fee</option>
                      <option value="other">Other</option>
                    @endif
                    @if($isCommercialBusiness)
                      <option value="Percentage of Net Aggregate Rent">Percentage of Net Aggregate Rent</option>
                      <option value="Percentage of Gross Rent">Percentage of Gross Rent</option>
                      <option value="Percentage of Month's Rent">Percentage of Month's Rent</option>
                      <option value="Flat Fee">Flat Fee</option>
                    @endif
                  </select>
                </div>
                <div class="mt-3">
                  <div x-show="seller_leasing_fee_type === 'Percentage of the Gross Lease Value' || seller_leasing_fee_type === 'Percentage of Gross Rent'">
                    <div class="input-group">
                      <input type="number" name="seller_leasing_gross" class="form-control"
                        placeholder="Enter percentage (e.g., 10)"
                        value="{{ old('seller_leasing_gross', $get->seller_leasing_gross ?? '') }}">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                  <div x-show="seller_leasing_fee_type === 'Percentage of the Rent Due Each Rental Period'">
                    <div class="input-group">
                      <input type="number" name="seller_leasing_gross_rental" class="form-control"
                        placeholder="Enter percentage (e.g., 10)"
                        value="{{ old('seller_leasing_gross_rental', $get->seller_leasing_gross_rental ?? '') }}">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                  <div x-show="seller_leasing_fee_type === 'Percentage of the First Month\'s Rent' || seller_leasing_fee_type === 'Percentage of Month\'s Rent'">
                    <div class="input-group">
                      <input type="number" name="seller_leasing_gross_month_rent" class="form-control"
                        placeholder="Enter percentage (e.g., 100)"
                        value="{{ old('seller_leasing_gross_month_rent', $get->seller_leasing_gross_month_rent ?? '') }}">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                  <div x-show="seller_leasing_fee_type === 'Percentage of Net Aggregate Rent'">
                    <div class="input-group">
                      <input type="text" name="seller_leasing_gross_other" class="form-control"
                        placeholder="Enter percentage (e.g., 6)"
                        value="{{ old('seller_leasing_gross_other', $get->seller_leasing_gross_other ?? '') }}">
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                  <div x-show="seller_leasing_fee_type === 'Flat Fee'">
                    <div class="input-group">
                      <span class="input-group-text">$</span>
                      <input type="text" name="seller_leasing_gross_purchase_fee_flat_amount" class="form-control"
                        placeholder="Enter flat fee amount (e.g., 5000)"
                        value="{{ old('seller_leasing_gross_purchase_fee_flat_amount', $get->seller_leasing_gross_purchase_fee_flat_amount ?? '') }}">
                    </div>
                  </div>
                  <div x-show="seller_leasing_fee_type === 'other'">
                    <input type="text" name="seller_leasing_gross_purchase_fee_other" class="form-control mt-2"
                      placeholder="Enter lease fee structure"
                      value="{{ old('seller_leasing_gross_purchase_fee_other', $get->seller_leasing_gross_purchase_fee_other ?? '') }}">
                  </div>
                </div>
              </div>
            </div>

            {{-- Lease-Option Agreement --}}
            <div class="form-group mb-2">
              <label class="fw-bold">Interested in Offering a Lease-Option Agreement:</label>
              <div class="input-cover mt-2">
                <select x-model="interested_lease_option_agreement" name="interested_lease_option_agreement" class="form-control has-icon"
                  data-icon="fa-solid fa-file-invoice-dollar">
                  <option value="">Select</option>
                  <option value="Yes">Yes</option>
                  <option value="No">No</option>
                </select>
              </div>
            </div>
            <div x-show="interested_lease_option_agreement === 'Yes'" class="mt-3">
              <div class="form-group mb-3">
                <label class="fw-bold">Compensation for Creating the Lease-Option Agreement:</label>
                <div class="row g-2 mt-1">
                  <div class="col-md-3">
                    <select name="lease_type" class="form-select">
                      <option value="percent">%</option>
                      <option value="flat">$</option>
                    </select>
                  </div>
                  <div class="col-md-9">
                    <input type="text" name="lease_value" class="form-control"
                      placeholder="Enter amount"
                      value="{{ old('lease_value', $get->lease_value ?? '') }}">
                  </div>
                </div>
              </div>
              <div class="form-group mb-3">
                <label class="fw-bold">Compensation if Purchase Option is Exercised:</label>
                <div class="row g-2 mt-1">
                  <div class="col-md-3">
                    <select name="purchase_type" class="form-select">
                      <option value="percent">%</option>
                      <option value="flat">$</option>
                    </select>
                  </div>
                  <div class="col-md-9">
                    <input type="text" name="purchase_value" class="form-control"
                      placeholder="Enter amount"
                      value="{{ old('purchase_value', $get->purchase_value ?? '') }}">
                  </div>
                </div>
              </div>
            </div>

            {{-- Protection Period --}}
            <div class="form-group mb-4 mt-3">
              <label class="fw-bold d-flex align-items-center">
                Protection Period Timeframe (Days):
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                  title="Number of days after the Termination Date during which the Broker may still earn compensation.">
                  <i class="fa-solid fa-circle-info"></i>
                </span>
              </label>
              <div class="input-cover mt-2">
                <input type="number" name="protection_period" class="form-control has-icon"
                  data-icon="fa-solid fa-shield-alt"
                  placeholder="Enter protection period in days (e.g., 90)"
                  value="{{ old('protection_period', $get->protection_period ?? '') }}">
              </div>
            </div>

            {{-- Early Termination Fee --}}
            <div class="form-group mb-4">
              <label class="fw-bold d-flex align-items-center">
                Early Termination Fee:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                  title="Select whether a cancellation fee applies if the agreement is terminated early.">
                  <i class="fa-solid fa-circle-info"></i>
                </span>
              </label>
              <div class="input-cover mt-2">
                <select x-model="early_termination_fee_option" name="early_termination_fee_option" class="form-control has-icon"
                  data-icon="fa-solid fa-exclamation-triangle">
                  <option value="">Select</option>
                  <option value="yes">Yes</option>
                  <option value="no">No</option>
                </select>
              </div>
              <div x-show="early_termination_fee_option === 'yes'" class="mt-3">
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="text" name="early_termination_fee_amount" class="form-control"
                    placeholder="Enter early termination fee amount (e.g., 1000)"
                    value="{{ old('early_termination_fee_amount', $get->early_termination_fee_amount ?? '') }}">
                </div>
              </div>
            </div>

            {{-- Retainer Fee --}}
            <div class="form-group mb-4">
              <label class="fw-bold d-flex align-items-center">
                Retainer Fee:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                  title="Select whether a non-refundable retainer fee applies to initiate Broker services.">
                  <i class="fa-solid fa-circle-info"></i>
                </span>
              </label>
              <div class="input-cover mt-2">
                <select x-model="retainer_fee_option" name="retainer_fee_option" class="form-control has-icon"
                  data-icon="fa-solid fa-file-invoice-dollar">
                  <option value="">Select</option>
                  <option value="yes">Yes</option>
                  <option value="no">No</option>
                </select>
              </div>
              <div x-show="retainer_fee_option === 'yes'" class="mt-3">
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input type="text" name="retainer_fee_amount" class="form-control"
                    placeholder="Enter retainer fee amount (e.g., 500)"
                    value="{{ old('retainer_fee_amount', $get->retainer_fee_amount ?? '') }}">
                </div>
                <div class="mt-3">
                  <label class="fw-bold">Retainer Fee Application:</label>
                  <select name="retainer_fee_application" class="form-control mt-2">
                    <option value="">Select application method</option>
                    <option value="Applied Toward Final Compensation"
                      {{ old('retainer_fee_application', $get->retainer_fee_application ?? '') === 'Applied Toward Final Compensation' ? 'selected' : '' }}>Applied Toward Final Compensation</option>
                    <option value="Charged in Addition to Final Compensation"
                      {{ old('retainer_fee_application', $get->retainer_fee_application ?? '') === 'Charged in Addition to Final Compensation' ? 'selected' : '' }}>Charged in Addition to Final Compensation</option>
                  </select>
                </div>
              </div>
            </div>

            {{-- Seller's Broker's Share of Retained Deposits --}}
            <div class="form-group mb-4">
              <label class="fw-bold d-flex align-items-center">
                Seller's Broker's Share of Retained Deposits:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                  title="Enter the percentage of any retained deposit the Seller's Broker is entitled to if the Buyer defaults.">
                  <i class="fa-solid fa-circle-info"></i>
                </span>
              </label>
              <div class="input-group">
                <input type="number" name="retained_deposits" class="form-control"
                  placeholder="Enter percentage (e.g., 50)"
                  value="{{ old('retained_deposits', $get->retained_deposits ?? '') }}">
                <span class="input-group-text">%</span>
              </div>
            </div>

            {{-- Agency Agreement Timeframe --}}
            <div class="form-group mb-4">
              <label class="fw-bold d-flex align-items-center">
                Seller Agency Agreement Timeframe:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                  title="Select how long the Seller's agreement with the Broker will last.">
                  <i class="fa-solid fa-circle-info"></i>
                </span>
              </label>
              <div class="input-cover mt-2">
                <select x-model="agency_agreement_timeframe" name="agency_agreement_timeframe" class="form-control has-icon"
                  data-icon="fa-solid fa-calendar-alt">
                  <option value="">Select</option>
                  <option value="3 Months">3 Months</option>
                  <option value="6 Months">6 Months</option>
                  <option value="9 Months">9 Months</option>
                  <option value="12 Months">12 Months</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div x-show="agency_agreement_timeframe === 'Other'" class="mt-3">
                <input type="text" name="agency_agreement_custom" class="form-control"
                  placeholder="Enter Seller agency agreement timeframe (e.g., 8 Months)"
                  value="{{ old('agency_agreement_custom', $get->agency_agreement_custom ?? '') }}">
              </div>
            </div>

            {{-- Brokerage Relationship --}}
            <div class="form-group mb-4">
              <label class="fw-bold d-flex align-items-center">
                Acceptable Brokerage Relationship:
                <span class="ms-2" data-bs-toggle="tooltip" data-bs-html="true"
                  title="Select the type of legal relationship the Seller wishes to establish with the Broker.">
                  <i class="fa-solid fa-circle-info"></i>
                </span>
              </label>
              <div class="input-cover mt-2">
                <select x-model="brokerage_relationship" name="brokerage_relationship" class="form-control has-icon"
                  data-icon="fa-solid fa-handshake">
                  <option value="">Select</option>
                  <option value="Transaction Broker Representation">Transaction Broker Representation</option>
                  <option value="Single Agent Representation">Single Agent Representation</option>
                  <option value="Dual Agency Representation">Dual Agency Representation</option>
                  <option value="No Brokerage Relationship">No Brokerage Relationship</option>
                </select>
              </div>
              <div x-show="brokerage_relationship" class="mt-3 p-3 bg-light rounded">
                <div x-show="brokerage_relationship === 'Transaction Broker Representation'">
                  <h6 class="fw-bold">• Transaction Broker Representation:</h6>
                  <ul class="mb-2 ps-3" style="list-style-type: disc;">
                    <li>Default brokerage relationship in Florida unless otherwise specified by law.</li>
                    <li>The Broker provides limited representation to both parties without full fiduciary duties.</li>
                    <li>This brokerage relationship may not be permitted in certain states, including Texas, Alaska, Vermont, Kansas, or Colorado.</li>
                  </ul>
                </div>
                <div x-show="brokerage_relationship === 'Single Agent Representation'">
                  <h6 class="fw-bold">• Single Agent Representation:</h6>
                  <ul class="mb-2 ps-3" style="list-style-type: disc;">
                    <li>The Broker acts as a fiduciary, providing the highest level of loyalty, confidentiality, and full disclosure.</li>
                    <li>The Broker must always act in the Seller's best interest.</li>
                  </ul>
                </div>
                <div x-show="brokerage_relationship === 'Dual Agency Representation'">
                  <h6 class="fw-bold">• Dual Agency Representation:</h6>
                  <ul class="mb-2 ps-3" style="list-style-type: disc;">
                    <li>The Broker represents both the Seller and the Buyer in the same transaction.</li>
                    <li>Requires written consent from both parties.</li>
                  </ul>
                </div>
                <div x-show="brokerage_relationship === 'No Brokerage Relationship'">
                  <h6 class="fw-bold">• No Brokerage Relationship:</h6>
                  <ul class="mb-2 ps-3" style="list-style-type: disc;">
                    <li>The Broker does not represent the Seller and has no fiduciary duties.</li>
                    <li>The Broker must still act honestly and disclose all known material facts.</li>
                  </ul>
                </div>
                <div class="alert alert-warning mt-3 p-2 small">
                  <strong>Warning:</strong> Certain brokerage relationships are not permitted in all states. Both the Broker and Seller are responsible for complying with all current local, state, and federal laws.
                </div>
              </div>
            </div>

            {{-- Additional Terms --}}
            <div class="form-group mb-4">
              <label class="fw-bold">Additional Terms:</label>
              <textarea name="additional_details_broker" class="form-control mt-2" rows="3"
                placeholder="Enter any additional terms">{{ old('additional_details_broker', $get->additional_details_broker ?? '') }}</textarea>
            </div>
          </div>
          <div class="wizard-step">
            {{-- Default Profile Banner --}}
            @if(!empty($defaultProfileData))
                <div class="alert alert-success d-flex align-items-center mb-3">
                    <i class="fa-solid fa-circle-check me-2"></i>
                    <strong>Your saved default profile has been pre-filled.</strong>&nbsp;You can edit any field before submitting.
                </div>
            @endif
            <div class="form-group">
              <label class="fw-bold">About Agent: <span class="text-danger">*</span></label>
              <textarea class="form-control" name="bio" rows="5" required>{{ old('bio', $defaultProfileData['bio'] ?? '') }}</textarea>
              @if($errors->has('bio'))<span class="text-danger small">{{ $errors->first('bio') }}</span>@endif
            </div>
          </div>
          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">Why should you be hired as their agent? <span class="text-danger">*</span></label>
              <textarea class="form-control" name="why_hire_you" rows="5" required>{{ old('why_hire_you', $defaultProfileData['why_hire_you'] ?? '') }}</textarea>
              @if($errors->has('why_hire_you'))<span class="text-danger small">{{ $errors->first('why_hire_you') }}</span>@endif
            </div>
            <div class="form-group">
              <label class="fw-bold">What sets you apart from other agents? <span class="text-danger">*</span></label>
              <textarea class="form-control" name="what_sets_you_apart" rows="5" required>{{ old('what_sets_you_apart', $defaultProfileData['what_sets_you_apart'] ?? '') }}</textarea>
              @if($errors->has('what_sets_you_apart'))<span class="text-danger small">{{ $errors->first('what_sets_you_apart') }}</span>@endif
            </div>
            <div class="form-group">
              <label class="fw-bold">What is your marketing strategy? <span class="text-danger">*</span></label>
              <textarea class="form-control" name="marketing_plan" rows="5" required>{{ old('marketing_plan', $defaultProfileData['marketing_plan'] ?? '') }}</textarea>
              @if($errors->has('marketing_plan'))<span class="text-danger small">{{ $errors->first('marketing_plan') }}</span>@endif
            </div>

          </div>

          <div class="wizard-step" data-step="2">
            <div class="row form-group">
              <div class="col-md-12 mb-2">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>Website Link:</th>
                      <th>Reviews Link:</th>
                    </tr>
                  </thead>
                  <tbody class="links">
                    <tr>
                      <td>
                        <input type="text" name="website_link[]" class="form-control">
                      </td>
                      <td>
                        <input type="text" name="reviews_link[]" class="form-control">
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <th colspan="2" class="text-right">
                        <a class="btn btn-primary add-links">Add New Row</a>
                      </th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
            <div class="row form-group">
              <div class="col-md-12 mb-2">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>Type:</th>
                      <th>Link:</th>
                    </tr>
                  </thead>
                  <tbody class="social-links">
                    <tr>
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
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <th colspan="2" class="text-right">
                        <a class="btn btn-primary add-row">Add New Row</a>
                      </th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>
          </div>
          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">What year did the agent get licensed?</label>
              <input type="text" class="form-control has-icon" data-icon="fa-solid fa-calendar-days" name="license_year"
                required />
            </div>
          </div>
          <div class="wizard-step">

            @php
              if ($auction->get->property_type == 'Vacant Land' || $auction->get->property_type == 'Residential Property') {
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
                    ['target' => '.other_services', 'name' => 'Other – Add additional services as offered. '],
                ];

              } elseif ($auction->get->property_type == 'Income Property') {
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
                    ['target' => '.other_services', 'name' => 'Other – Add additional services as offered. '],
                ];

              } elseif ($auction->get->property_type == 'Commercial Property' || $auction->get->property_type == 'Business Opportunity') {
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
                    ['target' => '.other_services', 'name' => 'Other – Add additional services as offered. '],
                ];

              } else {
                  // Default for Residential and any other property type
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
                    ['name' => 'Conduct real estate email marketing campaigns that lead to the seller\'s listing.', 'target' => ''],
                    ['name' => 'Provide professional photos to showcase the property\'s features.', 'target' => ''],
                    ['name' => 'Provide aerial photography to capture the property\'s surroundings and neighborhood.', 'target' => ''],
                    ['name' => 'Provide a professional video to showcase the property.', 'target' => ''],
                    ['name' => 'Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.', 'target' => ''],
                    ['name' => 'Offer expert negotiation skills to secure the best possible terms and price during the selling process.', 'target' => ''],
                    ['name' => 'Coordinate and schedule showings for potential buyers, ensuring a smooth and efficient viewing experience.', 'target' => ''],
                    ['name' => 'Send email alerts to buyers searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.', 'target' => ''],
                    ['name' => 'Provide guidance and support throughout the entire transaction, from listing to closing, to ensure a seamless and successful sale.', 'target' => ''],
                    ['name' => 'Assist with the completion and submission of all necessary paperwork and documentation related to the sale.', 'target' => ''],
                    ['name' => 'Collaborate with other real estate professionals and agents to expand the network of potential buyers for the property.', 'target' => ''],
                    ['name' => 'Provide regular updates on market activity, showings, and feedback from potential buyers.', 'target' => ''],
                    ['target' => '.other_services', 'name' => 'Other – Add additional services as offered. '],
                  ];
              }

            @endphp
            <div class="form-group">
              <label class="fw-bold">Select the included services that the agent will provide to the seller:</label>
              <select class="grid-picker" name="services[]" id="services" multiple required>
                <option value="">Select</option>
                @foreach ($services_data as $service)
                  <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}" class="card flex-row"
                    style="width:calc(100% - 0px);" data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                    {{ $service['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            @php
              $otherServicesVal = @$auction->get->other_services;
              if (is_array($otherServicesVal)) { $otherServicesVal = implode(', ', $otherServicesVal); }
              $otherServicesVal = (string)($otherServicesVal ?? '');
            @endphp
            <div class="form-group other_services @if ($otherServicesVal == '' || $otherServicesVal == 'null') d-none @endif ">
              <label class="fw-bold"> What additional services will the agent provide to the seller?</label>
              <input type="text" name="other_services" id="other_services"
                value="{{ $otherServicesVal }}" data-icon="fa-solid fa-hand-point-right"
                class="form-control has-icon">
            </div>
          </div>

<div class="wizard-step">
    <div class="form-group">
        <label for="" class="fw-bold">Virtual Agent Presentation (Link): </label>
        <input type="url" class="form-control has-icon" name="virtual_buyer_presentation_link" data-icon="fa-solid fa-link">
      </div>
      <div class="row">
        <div class="col-6 form-group">
            <label class="fw-bold mt-1">Virtual Agent Presentation (Upload):</label>
            <div class="videoBox ">
                <div class="video bgImg"></div>
                <div class="videoDiv">
                  <input type="file" class="fileuploader" name="virtual_buyer_presentation" style="display: none;"
                    accept="video/*">
                  <label for="fileuploader" class="fileuploader-btn">
                    <span class="upload-button">+</span>
                  </label>
                </div>
              </div>
        </div>
        <div class="col-6 form-group">
            <label class="fw-bold">Business Card:</label>
            <div class="upload ">
                <div class="wrapper">
                  <div class="box">
                    <div class="js--image-preview"></div>
                    <div class="upload-options">
                      <label>
                        <input type="file" name="card" class="image-upload" accept="image/*" />
                      </label>
                    </div>
                  </div>
                </div>
              </div>
        </div>
    </div>
    <div class="form-group">
      <label class="fw-bold">Promotional marketing materials, such as postcards, flyers, brochures, etc.:
      </label>
      <div class="">
        <input type="file" class="form-control" name="note[]">
        <button type="button" class="btn btn-secondary btn-sm w-100 newInput mt-2"
        onclick="addInput();"><i class="fa-solid fa-plus"></i> Add New
          Row</button>
      </div>
    </div>
  </div>

          {{-- <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">Virtual Listing Presentation (Upload): </label>
              <input type="file" class="form-control" id="presentation" name="virtual_buyer_presentation"
                accept=".mp4,.avi">
              <small class="text-muted">Maximum file size: 5 MB</small>
              <div class="error-message" style="display: none; color: red;">File size exceeds the maximum limit (5 MB).
                Please choose a smaller file.</div>
            </div>



            <div class="form-group">
              <label class="fw-bold">Virtual Listing Presentation (Link): </label>
              <input type="url" class="form-control" data-icon="" name="virtual_buyer_presentation_link">
            </div>
            <div class="form-group">
              <label class="fw-bold">Business Card:</label>
              <div class="d-flex align-items-baseline">
                <input type="file" class="form-control" name="card"">
              </div>
            </div>
            <div class="form-group">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th> <label class="fw-bold">Promotional marketing materials, such as postcards, flyers, brochures,
                        etc.:</label>
                      <div class="d-flex align-items-baseline">
                        <input type="file" class="form-control" name="note[]">
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="newInput">
                    <td><button type="button" class="btn btn-secondary btn-sm w-100" onclick="addInput();"><i
                          class="fa-solid fa-plus"></i> Add New
                        Row</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div> --}}
          <div class="wizard-step">

            <div class="form-group">
              <div class="row">
                <div class="form-group col-6">
                  <label class="fw-bold" for="first_name">First Name:</label>
                  <input type="text" name="first_name" id="first_name" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-user-tie" value="{{ Auth::user()->first_name }}">
                </div>
                <div class="form-group col-6">
                  <label class="fw-bold" for="last_name">Last Name:</label>
                  <input type="text" name="last_name" id="last_name" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-user-tie" value="{{ Auth::user()->last_name }}">
                </div>
              </div>
              <div class="row">
                <div class="form-group col-6">
                  <label class="fw-bold" for="agent_phone">Phone Number:</label>
                  <input type="text" name="agent_phone" id="agent_phone" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-phone" value="{{ Auth::user()->phone }}">
                </div>
                <div class="form-group col-6">
                  <label class="fw-bold" for="agent_email">Email:</label>
                  <input type="email" name="agent_email" id="agent_email" class="form-control has-icon hide_arrow"
                    data-icon="fa-solid fa-envelope" value="{{ Auth::user()->email }}">
                </div>
              </div>
              <div class="row">
                <div class="form-group col-6">
                  <label class="fw-bold" for="agent_brokerage">Brokerage:</label>
                  <input type="text" name="agent_brokerage" id="agent_brokerage"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-handshake"
                    value="{{ Auth::user()->brokerage }}">
                </div>

                <div class="form-group col-6">
                  <label class="fw-bold" for="agent_license_no">Real Estate License #:</label>
                  <input type="text" name="agent_license_no" id="agent_license_no"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-card"
                    value="{{ Auth::user()->license_no }}">
                </div>
              </div>

              <div class="form-group col-6">
                <label class="fw-bold" for="mls_id">NAR Member ID (NRDS ID): </label>
                <input type="text" name="mls_id" id="mls_id" class="form-control has-icon hide_arrow"
                  data-icon="fa-solid fa-id-badge" value="{{ Auth::user()->mls_id }}">
              </div>
            </div>

            {{-- Save as Default Profile option --}}
            <div class="mt-4 p-3 border rounded bg-light">
                <div class="form-check d-flex align-items-start gap-2">
                    <input class="form-check-input mt-1" type="checkbox" name="save_as_default" id="save_as_default" value="1">
                    <label class="form-check-label" for="save_as_default">
                        <strong><i class="fa-solid fa-bookmark me-1 text-primary"></i> Save as my default profile</strong>
                        <p class="mb-0 small text-muted">Save your overview and contact answers to pre-fill future bids (Seller — {{ ucfirst($auction->get->property_type ?? 'residential') }}).</p>
                    </label>
                </div>
            </div>
          </div>
          <div class="d-flex justify-content-between form-group mt-4">
            <div>
              <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
            </div>
            <div>
              <a class="wizard-step-next btn btn-success btn-lg text-600" id="nextBtn" style="display: none;">Next</a>
              <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
                style="display: none;">Save</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <template class="add_input mt-3">
    <input type="file" name="note[]" placeholder="" data-type="" class="form-control mt-3">
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
          $('.videoDiv').after('<span id="errorDiv" style="color: red;">Please upload a file less than 10MB. Thanks!!</span>');
          $(this).val('');
          $('#nextBtn').prop('disabled', true);
        } else {
          $('#nextBtn').prop('disabled', false);
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
    $(function() {
      $('.add-links').on('click', function() {
        var links =
          `<tr>
            <td>
                <input type="text" name="website_link[]" class="form-control">
                </td>
                <td>
                    <input type="text" name="reviews_link[]" class="form-control">
            </td>
        </tr>`;
        $('.links').append(links);
      });
    });
  </script>
  <script>
    $(function() {
      $('.addReview').on('click', function() {
        var addReview =
          `<input type="text" name="reviews_link[]" class="form-control mt-2">`;
        $('.addLinks').append(addReview);
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

    function addInput() {
      var city_row = $('.add_input').html();
      $('.newInput').before(city_row);
      initialize();
    }
  </script>
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush


