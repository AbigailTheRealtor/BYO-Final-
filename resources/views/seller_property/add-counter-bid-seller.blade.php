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

      </div>
      <div class="card-body">
        <div class="wizard-steps-progress">
          <div class="steps-progress-percent"></div>
        </div>
        <form class="p-4 pt-0 mainform" action="{{ route('add-listing') }}" method="POST" enctype="multipart/form-data">
          @csrf
          {{-- Slide 1 --}}
          <div class="wizard-step">
            <span class="traditionalTime">
              <h4>Price and Terms:</h4>
                <div class="form-group ">
                  <label class="fw-bold" for="buy_now_price" required>Price:</label>
                  <input type="number"  name="price" placeholder="" id="buy_now_price"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar"
                    data-msg-required="Please enter Buy Now Price">
                </div>
                <div class="form-group ">
                  <label class="fw-bold" for="reserve_price" required>List Price Per Sqft:</label>
                  <input type="number" name="price_per_sqfeet"  id="reserve_price"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Acceptable Escrow Deposit:</label>
                  <input type="number" name="escrow_amount2" id="term_escrow_amount" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
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
                  <label class="fw-bold">Financing contingency (days):</label>
                  <input type="number" name="finance" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
                <div class="form-group sale d-none">
                  <label class="fw-bold"> Sale of a property contingency (days): </label>
                  <input type="number" name="saleContingency" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
                <div class="form-group otherContingency d-none">
                  <label class="fw-bold">Acceptable contingency: </label>
                  <input type="text" name="acceptable" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>

                  <label class="fw-bold">Acceptable contingency (days):</label>
                  <input type="text" name="acceptable_days" id="" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
              </div>
              <div class="form-group">
                  @php
                    $term_financings = [
                        ['name' => 'Cash', 'target' => ''],
                        ['name' => 'Conventional', 'target' => ''],
                        ['name' => 'FHA', 'target' => ''],
                        ['name' => 'VA', 'target' => ''],
                        ['name' => 'Non-Fungible Token (NFT)', 'target' => '.nft'],
                        ['name' => 'Cryptocurrency', 'target' => '.crypto'],
                        ['name' => 'USDA', 'target' => ''],
                        ['name' => 'Assumable', 'target' => '.assumable'],
                        ['name' => 'Exchange/Trade', 'target' => '.custom_exchange_trade'],
                        ['name' => 'Lease Option', 'target' => '.leaseOption'],
                        ['name' => 'Lease Purchase', 'target' => '.leasePurchase'],
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
                    <label class="fw-bold">Acceptable Currency/Financing:</label>
                    <input type="text" name="otherFinancing"  class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                </div>
                {{-- Other --}}
                {{-- NFT  --}}
                <div class="form-group nft d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What type of Non-Fungible Token (NFT) will the seller accept?</label>
                      <input type="text" name="type_of_NFT_accepted" id="type_of_NFT_accepted" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
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
                    <input type="text" name="cryptocurrency_type" id="cryptocurrency_type" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                </div>
                <div class="form-group col-md-12">
                    <label class="fw-bold">What percentage of the sales price will the seller accept in cryptocurrency?</label>
                    <input type="number" name="percentage_in_crypto" id="percentage_in_crypto" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                </div>
                <div class="form-group col-md-12">
                    <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
                    <input type="number" name="percentage_in_cash" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                </div> 
                <small>Note: Cryptocurrency can be converted to cash at closing.</small>             
              </div>
              {{-- Crypto  --}}
              {{-- seller financing --}}
              <div class="form-group row custom_seller_financing d-none">
                <label class="fw-bold">Please enter the seller’s desired seller financing terms:</label>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Purchase Price:</label>
                  <input type="number" name="purchase_price_seller_financing" id="purchase_price_seller_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                </div>
                <div class="form-group col-md-3">
                  <div class="d-flex justify-content-between aalign-items-center">
                    <label class="fw-bold">Down Payment:</label>
                    <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                        <button type="button" class="select-btn me-1 active"
                            data-type="amount">$</button>
                        <button type="button" class="select-btn" data-type="percent">%</button>
                    </div>
                  </div>
                  <input type="number" name="down_payment_seller_financing" id="down_payment_seller_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Seller Financing Amount:</label>
                  <input type="number" name="seller_financing_amount" id="seller_financing_amount"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Interest Rate:</label>
                  <input type="number" name="interest_rate_seller_financing" id="interest_rate_seller_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Loan Duration:</label>
                  <input type="text" name="term_seller_financing" id="term_seller_financing"
                    class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Monthly Payment with Principal and Interest:</label>
                  <input type="number" name="monthly_payment_seller_financing" id="monthly_payment_seller_financing"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
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
                    <input type="number" name="ballonPenaltyYes" id="closing_costs" class="form-control has-icon"
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
                        <input type="number" name="balloonPayment" id="closing_costs" class="form-control has-icon"
                          data-icon="fa-solid fa-dollar-sign" required>
                      </div>
                      <div class="form-group">
                        <label class="fw-bold">When is the balloon payment due? </label>
                        <input type="text" name="balloonDue" id="closing_costs" class="form-control has-icon"
                          data-icon="fa-regular fa-calendar-days" required>
                      </div>
                    </div>
                </div>
              </div>
              {{-- seller financing --}}
              {{-- Lease Option  --}}
                <div class="form-group leaseOption d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the seller's desired offering price for a lease option? </label>
                      <input type="number" name="desired_offering_price" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What specific terms does the seller propose for the lease option?</label>
                      <input name="lease_option_terms" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the proposed duration of the lease?</label>
                      <input type="text" name="proposed_lease_duration" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the monthly payment amount the seller is seeking? </label>
                      <input type="number" name="monthly_payment_amount" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What are the specific conditions or requirements outlined by the seller for the lease option?</label>
                      <input name="lease_option_conditions" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
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
                      <input type="number" name="sellerFeeOptionYes"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
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
                      <input name="lease_purchase_terms" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the proposed duration of the lease?</label>
                      <input type="text" name="proposed_lease_duration_lease_purchase" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label>
                      <input type="number" name="monthly_payment_amount_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What are the specific conditions or requirements outlined by the seller for the lease purchase?</label>
                      <input name="lease_purchase_conditions" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>    
                  <div class="form-group">
                    @php
                      $sellerFeePurchase = [['name' => 'Yes', 'target' => '.sellerFeePurchaseYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Does the seller require an option fee?</label>
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
                      <input type="number" name="sellerFeePurchaseYes"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                  </div>               
                </div>
              </div>
              {{-- Lease Purchase  --}}
              {{-- Assumable  --}}
                <div class="form-group assumable d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What assumable terms are being offered?</label>
                      <input type="text" name="assumable_terms_offered"  class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">Are there any restrictions or qualifications for a buyer assuming the existing financing?</label>
                      <input type="text" name="restrictions_or_qualifications"  class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group col-md-12">
                    <label class="fw-bold">What is the interest rate of the assumable loan?</label>
                    <input type="text" name="assumable_interest"  class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                  </div>
                  <div class="form-group col-md-12">
                    <label class="fw-bold">What is the monthly payment, including principal and interest, for the assumable loan?</label>
                    <input type="number" name="assumable_monthly_payment"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                  <div class="form-group col-md-12">
                      @php
                      $outstandingBalance = [['name' => 'Yes', 'target' => '.outstandingBalanceYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <div class="form-group">
                      <label class="fw-bold">What is the outstanding balance on the existing loan?</label>
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
                        <label class="fw-bold">What is the outstanding balance on the existing loan?</label>
                        <input type="number" name="outstandingBalanceYes"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>

                        <label class="fw-bold">What is the down payment that the buyer would need to pay the seller to bridge the gap
                          between the asking price and the assumable loan balance?</label>
                        <input type="number" name="loan_balance_down_payment"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                    </div>
                  </div>
                </div>            
              </div> 
              {{-- Assumable --}}
              {{-- Exchange/trade  --}}
              <div class="form-group row custom_exchange_trade">
                @php
                  $exchange_trades = [['name' => 'Another home', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vehicle', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Boat', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Motorhome', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Artwork', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Jewelry', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Other', 'target' => '.otherTrade', 'icon' => 'fa-regular fa-circle-check']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Acceptable Exchange Item:</label>
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
                    <input type="text" name="otherTrade" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                  <input type="text" name="estimatedTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is willing to exchange/trade?</label>
                  <input type="text" name="specificTrade" class="form-control has-icon" data-icon="" required>
                </div>
                <div class="form-group">
                  <label class="fw-bold">How much cash does the seller require on top of the exchange/trade item?</label>
                  <input type="number" name="cashTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
                  <input type="text" name="valueTrade" class="form-control has-icon"
                    data-icon="fa-regular fa-check-circle" required>
                </div>
              </div>
              {{-- Exchange/trade --}}
              <div class="form-group row">
                  @php
                    $sellerOffer = [['name' => 'Yes', 'target' => '.sellerOfferYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'], ['name' => 'Only if the buyer meets the Seller’s Sell Terms, which include the List Price, acceptable Escrow
                                                                                                                                                                                                                                    Deposit, number of days the Seller will accept for closing, and acceptable Contingencies, will the
                                                                                                                                                                                                                                    seller offer a buyer at closing', 'target' => '.sellerOfferYes','icon'=>'<i class="fa-regular fa-circle-check"></i>']];
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
                  <div class="d-flex justify-content-between aalign-items-center">
                    <label class="fw-bold">What credit amount is the seller offering to the buyer at closing?</label>
                    <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                        <button type="button" class="select-btn me-1 active"
                            data-type="amount">$</button>
                        <button type="button" class="select-btn" data-type="percent">%</button>
                    </div>
                  </div>
                  <input type="number" name="sellerOfferYes" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                </div>
              </div>
            </span>
          </div>
          <div class="wizard-step">
            @if (auth()->user()->user_type == 'agent')
              <h4>Listing Agent Information:</h4>
            @else
              <h4>Seller’s Information:</h4>
            @endif
            
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">First Name:</label>
                <input type="text" name="agent_first_name" id="first_name" placeholder=""
                  value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                  data-icon="fa-solid fa-user" required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Last Name:</label>
                <input type="text" name="agent_last_name" id="last_name" placeholder=""
                  value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                  data-icon="fa-solid fa-user" required>
              </div>
            </div>
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">Phone Number:</label>
                <input type="text" name="agent_phone" id="agent_phone" placeholder=""
                  value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                  data-icon="fa-solid fa-phone" required>
              </div>
              <div class="form-group col-md-6 ">
                <label class="fw-bold">Email:</label>
                <input type="text" name="agent_email" id="agent_email" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-envelope"
                  value="{{ Auth::user()->email }}" required>
              </div>
            </div>
            @if (auth()->user()->user_type == 'agent')
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">Brokerage:</label>
                <input type="text" name="agent_brokerage" id="agent_brokerage" placeholder=""
                  value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                  data-icon="fa-solid fa-handshake" required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Real Estate License #:</label>
                <input type="text" name="agent_license_no" id="agent_license_no" placeholder=""
                  value="{{ optional(Auth::user())->license_no }}" class="form-control has-icon"
                  data-icon="fa-solid fa-id-card" required>
              </div>
            </div>
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">NAR Member ID (NRDS ID):</label>
                <input type="text" name="agent_mls_id" id="agent_mls_id" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                  value="{{ optional(Auth::user())->mls_id }}" required>
              </div>
            </div>
            @endif
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
      $('#errorDiv').remove();
      if (this.files[0].size > 30000000) {
        $(this).parent().after('<span id="errorDiv" style="color: red;">Please upload a file less than 30MB. Thanks!!</span>');
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
  $('#auction_type').change(function(){
      let v=$(this).val();
    if (v == "Auction Listing") {
      $('.auction_length').val("");
      $('.auction_length').parent().children('.option-container').removeClass('active');
      $('.traditional-length').hide();
      $('.normal-length').show();
      $('.auction_length_cover').show();
      $('.timeAuction').show();
      $('.traditionalTime').hide();
    } else {
      $('.auction_length').val("");
      $('.auction_length').parent().children('.option-container').removeClass('active');
      $('.traditional-length').show();
      $('.normal-length').hide();
      $('.auction_length_cover').hide();
      $('.timeAuction').hide();
      $('.traditionalTime').show();
    }
  })
</script>
<script>
  function changePropertyStyle(p) {
    if (p == "Vacant Land") {
      $('.property_style_next_hide').addClass('d-none');
      $('.road_frontage_next_hide').addClass('d-none');
      $('.hide_vacant').removeClass('d-none');
      $('.hide_vacant').removeClass('d-none');
      $('.').remove();

    } else if (p == "Busniess Opportunity") {
      $('.business_oportunity_remove').remove();
    }
  }
  // document.getElementById('auction_type').change();
  $(function() {
    changePropertyStyle("");
  });
</script>
<script>
  function changeCurrentUse(p) {


    $('.current_use_next_hide').addClass('d-none');
  }
  // document.getElementById('auction_type').change();
  $(function() {
    changePropertyStyle("");
  });
</script>
<script>
  function changeFrontExposure(p) {


    $('.front_exposure_next_hide').addClass('d-none');

  }
  // document.getElementById('auction_type').change();
  $(function() {
    changePropertyStyle("");
  });
</script>
<script>
  function changeLotFeature(p) {


    $('.lot_feature_next_hide').addClass('d-none');

  }
  // document.getElementById('auction_type').change();
  $(function() {
    changeLotFeature("");
  });
</script>
<script>
  function change_adjacent_use(p) {


    $('.adjacent_use_next_hide').addClass('d-none');

  }
  // document.getElementById('auction_type').change();
  $(function() {
    change_adjacent_use("");
  });
</script>
<script>
  function changeRoadFrontage(p) {
    $('.road_frontage_next_hide').addClass('d-none');
  }
  // document.getElementById('auction_type').change();
  $(function() {
    changeRoadFrontage("");
  });
</script>
<script>
  function changeRoadSurfaceType(p) {
    $('.road_surface_type_next_hide').addClass('d-none');

  }
  // document.getElementById('auction_type').change();
  $(function() {
    changeRoadSurfaceType("");
  });
</script>
<script>
  function Utilities_Water_Sewer(p) {


    $('.utilities_water_sewer_next_hide').addClass('d-none');

  }
  // document.getElementById('auction_type').change();
  $(function() {
    Utilities_Water_Sewer("");
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
      $('.vacant_land-length').hide();
      $('.business-length').hide();
      $('.property_style').hide();
      $('.currentUse').hide();
      $('.fireplace').show();
      $('.businessType').hide();
      $('.resFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', false);
      });
      $('.commercialFields,.incomeFields,.vacantFields').each(function() {
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
      $('.currentUse').hide();
      $('.property_style').hide();
      $('.businessType').hide();
      $('.fireplace').show();
      $('.resFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', false);
      });
      $('.commercialFields,.incomeFields,.vacantFields').each(function() {
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
      $('.currentUse').hide();
      $('.property_style').hide();
      $('.businessType').hide();
      $('.fireplace').hide();
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
      $('.property_style').hide();
      $('.currentUse').show();
      $('.businessType').hide();
      $('.fireplace').show();
      $('.vacantFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', false);
      });
      $('.commercialFields,.incomeFields,.resFields').each(function() {
        $(this).find('select,img, input ,textarea').prop('disabled', true);
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
      $('.currentUse').hide();
      $('.businessType').show();
      $('.property_style').show();
      $('.fireplace').hide();
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
      $('.currentUse').hide();
      $('.businessType').hide();
      $('.property_style').hide();
      $('.resFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', true);
      });
      $('.commercialFields').each(function() {
        $(this).find('select, input ,textarea').prop('disabled', true);
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

  $('#room_type').change(function(){
    $('.room-type-fields').removeClass('d-none');
  })
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
            if (StepWizard.currentStep == 7 && property_type == 'Vacant Land') {
              StepWizard.nextStep = 76;
              StepWizard.backStep = 7;
            } 
            // else if (StepWizard.currentStep == 34 && property_type ==
            //   'Residential Property') {
            //   StepWizard.nextStep = 36;
            // } 
            else if (StepWizard.currentStep == 7 && (property_type == 'Residential Property' ||
                property_type ==
                'Income Property')) {
              StepWizard.nextStep = 8;
              StepWizard.backStep = 7;
            }
            else if (StepWizard.currentStep == 8 && property_type == 'Income Property') {
              StepWizard.nextStep = 11;
              StepWizard.backStep = 8
            }
            else if (StepWizard.currentStep == 10 && property_type == 'Residential Property') {
              StepWizard.nextStep = 12;
              StepWizard.backStep = 10
            } else if (StepWizard.currentStep == 8 && (property_type ==
                'Commercial Property' || property_type == 'Business Opportunity')

            ) {
              StepWizard.nextStep = 42;
              StepWizard.backStep = 8;
            } 
            else if (StepWizard.currentStep == 42 && (property_type ==
                'Commercial Property' || property_type == 'Business Opportunity')

            ) {
              StepWizard.nextStep = 44;
              StepWizard.backStep = 42;
            } 
            else if (StepWizard.currentStep == 61 && (property_type ==
                'Commercial Property' || property_type == 'Business Opportunity')

            ) {
              StepWizard.nextStep = 63;
              StepWizard.backStep = 61;
            }
            else if (StepWizard.currentStep == 66 && (property_type ==
                'Commercial Property' || property_type == 'Business Opportunity')

            ) {
              StepWizard.nextStep = 68;
              StepWizard.backStep = 66;
            }
            // else if (StepWizard.currentStep == 14 && (property_type ==
            //     'Commercial Property' || property_type == 'Business Opportunity')

            // ) {
            //   StepWizard.nextStep = 47;
            //   StepWizard.backStep = 14;
            // } 
            // else if (StepWizard.currentStep == 47 && (property_type ==
            //     'Commercial Property' || property_type == 'Business Opportunity')

            // ) {
            //   StepWizard.nextStep = 17;
            //   StepWizard.backStep = 47;
            // } 
            
            // else if (StepWizard.currentStep == 21 && (property_type ==
            //     'Commercial Property' || property_type == 'Business Opportunity')

            // ) {
            //   StepWizard.nextStep = 52;
            //   StepWizard.backStep = 21;
            // }  else if (StepWizard.currentStep == 10 && (property_type ==
            //     'Commercial Property' || property_type == 'Business Opportunity')

            // ) {
            //   StepWizard.nextStep = 12;
            //   StepWizard.backStep = 10;
            // } else if (StepWizard.currentStep == 20 && (property_type ==
            //     'Commercial Property' || property_type == 'Business Opportunity')

            // ) {
            //   StepWizard.nextStep = 21;
            //   StepWizard.backStep = 20;
            // } 
            
            else {
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
          
          if (StepWizard.currentStep == 11 && property_type == 'Income Property') {
              StepWizard.backStep = 8
              StepWizard.nextStep = 11;
            }else if (StepWizard.currentStep == 12 && property_type == 'Residential Property') {
              StepWizard.backStep = 10
              StepWizard.nextStep = 12;
            }else if (StepWizard.currentStep == 42 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 8;
              StepWizard.nextStep = 42;
            }else if (StepWizard.currentStep == 44 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 42;
              StepWizard.nextStep = 44;
            }else if (StepWizard.currentStep == 63 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 61;
              StepWizard.nextStep = 63;
            }else if (StepWizard.currentStep == 68 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 66;
              StepWizard.nextStep = 68;
            } 
            
          //   else if (StepWizard.currentStep == 13 && (property_type == 'Commercial Property' || property_type == 'Business Opportunity')) {
          //   StepWizard.backStep = 11;
          //   StepWizard.nextStep = 13;
          // } else if (StepWizard.currentStep == 47 && (property_type == 'Commercial Property' ||
          //     property_type ==
          //     'Business Opportunity')) {
          //   StepWizard.backStep = 14;
          //   StepWizard.nextStep = 47;
          // } else if (StepWizard.currentStep == 17 && (property_type == 'Commercial Property' ||
          //     property_type ==
          //     'Business Opportunity')) {
          //   StepWizard.backStep = 47;
          //   StepWizard.nextStep = 17;
          // } else if (StepWizard.currentStep == 10 && (property_type ==
          //     'Commercial Property' || property_type == 'Business Opportunity')

          // ) {
          //   StepWizard.backStep = 8;
          //   StepWizard.nextStep = 10;
          // } else if (StepWizard.currentStep == 21 && (property_type ==
          //     'Commercial Property' || property_type == 'Business Opportunity')

          // ) {
          //   StepWizard.nextStep = 21;
          //   StepWizard.backStep = 20;
          // } 
          // else if (StepWizard.currentStep == 36 && property_type ==
          //   'Residential Property') {
          //   StepWizard.backStep = 34;
          // } 
          else if (StepWizard.currentStep == 8 && (property_type == 'Residential Property' || property_type == 'Income Property')) {
            StepWizard.backStep = 7;
          } else if (StepWizard.currentStep == 76 && property_type == 'Vacant Land') {
            StepWizard.backStep = 7;
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
        //       return parseInt($(this).attr('data-step')) >= 8 && parseInt($(this)
        //         .attr('data-step')) <= 76;
        //     });
        //     $stepsToRemove.each(function() {
        //       $(this).closest('div[data-step]').remove();
        //     });
        //   }
        //Remove All the SLides Except THe Residential and Commercial Property
      
        //Remove All the SLides Except THe Commercial and Business Opportunity
        // if (property_type === 'Commercial Property' || property_type ===
        //   'Business Opportunity') {
        //   var $stepsToRemove = $('.wizard-step[data-step]').filter(function() {
        //     var stepValue = parseInt($(this).attr('data-step'));
        //     return (stepValue >= 8 && stepValue <= 41) || (stepValue >= 77 &&
        //       stepValue <= 91);
        //   });

        //   $stepsToRemove.each(function() {
        //     $(this).closest('div[data-step]').remove();
        //   });
        // }
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

      if (property_type === 'Residential Property' || property_type === 'Income Property') {
        if (StepWizard.currentStep >= 7 && StepWizard.currentStep <= 41) {
          comp = 20 + (((StepWizard.currentStep - 7) / (41 - 7)) * 80);
        }
      }
      else if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
        // Calculate progress for commercial and business opportunity steps (42 to 76)
        comp = 20 + (((StepWizard.currentStep - 7) / (75 - 7)) * 80);
        if (StepWizard.currentStep == 21) {
          comp = 20 + (((StepWizard.currentStep - 21) / (55 - 21)) * 80);
        }
      }
      // else if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
      //   //   console.log(StepWizard.currentStep)
      //   if (StepWizard.currentStep >= 7 && StepWizard.currentStep <= 20) {
      //     comp = 20 + (((StepWizard.currentStep - 7) / (75 - 7)) * 80);
      //   }
      //   if (StepWizard.currentStep >= 20 && StepWizard.currentStep <= 23) {
      //     comp = 20 + (((StepWizard.currentStep - 7) / (75 - 7)) * 80);

      //   }
      //   if (StepWizard.currentStep >= 44 && StepWizard.currentStep <= 75) {
      //     comp = 20 + (((StepWizard.currentStep - 44) / (75 - 44)) * 80);
      //   }
      // }
      else if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
        if (StepWizard.currentStep >= 1 && StepWizard.currentStep <= 8) {
            // Steps 1 to 8
            comp = 20 + ((StepWizard.currentStep - 1) / 7) * 20; // 20% for this range
        } else if (StepWizard.currentStep >= 9 && StepWizard.currentStep <= 41) {
            // Jump from step 8 to 42, so 8 is 20%, and 42 is 100% (80% for steps 9 to 41)
            comp = 20 + (80 * (StepWizard.currentStep - 9) / 33); // Steps 9 to 41 contribute to the middle range
        } else if (StepWizard.currentStep >= 42 && StepWizard.currentStep <= 61) {
            // Steps 42 to 61
            comp = 100 * ((StepWizard.currentStep - 42) / 19) + 20; // 80% for this range (20% already added)
        } else if (StepWizard.currentStep >= 62 && StepWizard.currentStep <= 66) {
            // Steps 62 to 66
            comp = 100 * ((StepWizard.currentStep - 62) / 4) + 100; // 100% (20% + 80% + 20% of the last range)
        } else if (StepWizard.currentStep >= 67 && StepWizard.currentStep <= 75) {
            // Steps 67 to 75
            comp = 100 * ((StepWizard.currentStep - 67) / 8) + 120; // 20% for last range
        }else if (StepWizard.currentStep === 75) {
            // Step 75 is the final step
            comp = 100; // Final step, complete
        }
      }
      
      else if (property_type === 'Vacant Land') {
        // Calculate progress for vacant land steps (77 to 91)
        comp = 20 + (((StepWizard.currentStep - 76) / (91 - 76)) * 80);
      } else {

        // Default progress calculation for other property types (steps 1 to 8)
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

  $('#unit_type').change(function(){
    $('.unit-info').removeClass('d-none');
  })
</script>
<script>
  // google.maps.event.addDomListener(window, 'load', initialize);
  function initialize() {
    var inputField = document.getElementsByClassName('search_places');

    for (var i = 0; i < inputField.length; i++) {
      var t = inputField[i].dataset.type;
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
  $('#has_furnishing').change(function(){
    let w=$(this).val();
    if (w == "Yes" || w == "Optional") {
      $('#has_furnishing_residential_and_income').show();
    } else {
      $('#has_furnishing_residential_and_income').hide();
    }
  })
  $('#otherStucture').change(function(){
    let w=$(this).val();
    if (w == "Additional Single Family Home" || w == "In-Law- Suite") {
      $('#otherSturctureUnit').show();
    } else {
      $('#otherSturctureUnit').hide();
    }
  })
</script>
<script
  src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
</script>
@endpush
