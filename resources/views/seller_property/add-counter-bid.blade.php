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
      {{ $page_data['title'] }}
    </h4>
    <div class="card m-4">
      <div class="card-title">
      </div>
      <div class="card-body">
        <div class="wizard-steps-progress">
          <div class="steps-progress-percent"></div>
        </div>

        <form class="p-4 pt-0 mainform" action="{{ route('counterBiding', ['bid_id' => $bid_id]) }}" method="POST" enctype="multipart/form-data">
          @csrf

          <input type="hidden" name="auction_id" value="{{ @$page_data['auction']->id }}">
          @php
            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
          @endphp

          <div class="wizard-step">
            <h4 style="color: black">Please provide your offered price and terms for this property:</h4>
            <div class="form-group">
              @php
                $startingPrice = $page_data['auction']->get->starting_price;
              @endphp
              <label class="fw-bold" for="price">Offered Price:</label>
              @if ($page_data['auction']->get->auction_type == 'Auction Listing')
              <input type="number" step="0.01" name="price" placeholder="0.00" id="price"
              class="form-control has-icon" data-icon="fa-solid fa-dollar"
              data-msg-required="Please enter Price" min="{{$startingPrice}}" autofocus required>
              <small>(Minimum starting price is {{$startingPrice}})</small>
              @else
              <input type="number" step="0.01" name="price" placeholder="0.00" id="price"
                class="form-control has-icon" data-icon="fa-solid fa-dollar"
                data-msg-required="Please enter Price" autofocus required>
              @endif
            </div>


            <div class="form-group">
              @php
                $term_financings = [
                    ['name' => 'Cash', 'target' => ''],
                    ['name' => 'Conventional', 'target' => ''],
                    ['name' => 'FHA', 'target' => ''],
                    ['name' => 'VA', 'target' => ''],
                    ['name' => 'Non-Fungible Token (NFT)', 'target' => '.nftAuction'],
                    ['name' => 'Cryptocurrency', 'target' => '.cryptoAuction'],
                    ['name' => 'USDA', 'target' => ''],
                    ['name' => 'Assumable', 'target' => '.assumableAuction'],
                    ['name' => 'Exchange/Trade', 'target' => '.tradeAuction'],
                    ['name' => 'Lease Option', 'target' => '.leaseOptionAuction'],
                    ['name' => 'Lease Purchase', 'target' => '.leasePurchaseAuction'],
                    ['name' => 'Seller Financing', 'target' => '.sellerFinancingAuction'],
                    ['name' => 'Jumbo', 'target' => ''],
                    ['name' => 'Non-QM', 'target' => ''],
                    ['name' => 'No-Doc', 'target' => ''],
                    ['name' => 'Other', 'target' => '.otherFinancingAuction'],
                ];
              @endphp
                <label class="fw-bold">Offered Currency/Financing:</label>
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
                      <label class="fw-bold">What type of Non-Fungible Token (NFT) does the buyer have?</label>
                      <input type="text" name="type_of_NFT_accepted" id="type_of_NFT_accepted" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What percentage of the purchase price will the buyer use as a Non-Fungible Token (NFT)?</label>
                      <input type="number" name="percentage_in_NFT" id="percentage_in_NFT" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What percentage of the purchase price will the buyer pay in cash?</label>
                      <input type="number" name="percentage_in_cash" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                  </div>
                </div>
              {{-- NFT  --}}
              {{-- CryptoAuction  --}}
              <div class="form-group cryptoAuction d-none">
                <div class="form-group col-md-12">
                    <label class="fw-bold">What type of Cryptocurrency does the buyer have?</label>
                    <input type="text" name="cryptocurrency_type" id="cryptocurrency_type" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                </div>
                <div class="form-group col-md-12">
                    <label class="fw-bold">What percentage of the purchase price will be used with Cryptocurrency?</label>
                    <input type="number" name="percentage_in_crypto" id="percentage_in_crypto" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                </div>
                <div class="form-group col-md-12">
                    <label class="fw-bold">What percentage of the purchase price will be used with Cash</label>
                    <input type="number" name="percentage_in_cash" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                </div> 
                <small>Note: Cryptocurrency can be converted to cash at closing.</small>             
              </div>
              {{-- Crypo --}}
              {{-- Seller Finance --}}
              <div class="form-group row sellerFinancingAuction d-none">
                <h4>Please enter the seller financing terms that the buyer is offering to the seller</h4>
                <div class="form-group col-md-3">
                  <label class="fw-bold">Purchase Price:</label>
                  <input type="number" name="purchasePrice" id="purchasePrice"
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                </div>
                <div class="form-group col-md-3">
                  <div class="d-flex justify-content-between aalign-items-center">
                    <label class="fw-bold">Down Payment:</label>
                    <div
                        class="d-flex align-items-center justify-content-center icon-select-btn-div">
                        <button type="button" class="select-btn me-1 active"
                            data-type="percent">%</button>
                        <button type="button" class="select-btn" data-type="amount">$</button>
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
                    <input type="number" name="ballonPenaltyYes" id="closing_costs" class="form-control has-icon"
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
                @php
                $balloonPay = [
                  ['name' => 'Yes', 'target' => '.mortgate-approved', 'icon' => 'fa-regular fa-calendar-days'], 
                  ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-calendar-days']];
                @endphp
                <label class="fw-bold">Has the buyer been approved for a mortgage within the last 90 days?</label>
                <select name="mortgage_approved" id="mortgage_approved" class="grid-picker" style="justify-content: flex-start;"
                  required>
                  @foreach ($balloonPay as $item)
                    <option value="{{ $item['name'] }}"
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="{{ $item['icon'] }}"></i>'>{{ $item['name'] }}
                  </option>
                  @endforeach
                </select>
                <div class="form-group mortgate-approved d-none">
                  <div class="form-group">
                    <label class="fw-bold">How much was the buyer approved for?</label>
                    <input type="text" name="mortgage_approved_amount" id="mortgage_approved_amount" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Has the buyer been denied for a mortgage within the last 90 days?</label>
                    <input type="text" name="mortgage_denied" id="mortgage_denied" class="form-control has-icon"
                      data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Has the buyer experienced any recent changes in their financial situation that may affect their
                      ability to make mortgage payments?</label>
                    <input type="text" name="financial_situation" id="financial_situation" class="form-control has-icon"
                      data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">How long has the buyer been employed at their current job?</label>
                    <input type="text" name="employement_duration" id="employement_duration" class="form-control has-icon"
                      data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Can the buyer provide details about their employment history over the past few years?</label>
                    <input type="text" name="employement_detail" id="employement_detail" class="form-control has-icon"
                      data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Does the buyer foresee any changes in their employment status or income in the near future?</label>
                    <input type="text" name="employement_status_change" id="employement_status_change" class="form-control has-icon"
                      data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">What is the buyer’s current gross monthly income?</label>
                    <input type="numbers" name="gross_income" id="gross_income" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">What are the buyer's monthly debt payments, including credit cards, loans, and other obligations?</label>
                    <input type="numbers" name="debt_payment" id="debt_payment" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">What is the buyer's credit score?</label>
                    <input type="numbers" name="credit_score" id="credit_score" class="form-control has-icon"
                      data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                </div>
              </div>
              {{-- Lease Option  --}}
                <div class="form-group leaseOptionAuction d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the buyer’s desired option purchase price?</label>
                      <input type="number" name="desired_offering_price" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What specific terms does the buyer propose for the lease option?</label>
                      <input name="lease_option_terms" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the proposed duration of the lease?</label>
                      <input type="text" name="proposed_lease_duration" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the monthly payment amount the buyer is seeking for the lease option?</label>
                      <input type="number" name="monthly_payment_amount" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What are the specific conditions or requirements outlined by the buyer for the lease purchase?</label>
                      <input name="lease_option_conditions" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group">
                    @php
                      $sellerFeeOption = [['name' => 'Yes', 'target' => '.sellerFeeOptionYesAuction', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Is the buyer offering an option fee?</label>
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
                      <div class="form-group">
                        <label class="fw-bold">How much is the option fee? </label>
                        <input type="number" name="sellerFeeOptionYes"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                      </div>
                  </div>               
                </div>
              </div>
              {{-- Lease Option  --}}
              {{-- Lease Purchase  --}}
                <div class="form-group leasePurchaseAuction d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the buyer’s desired lease purchase price?</label>
                      <input type="number" name="desired_offering_price_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What specific terms does the buyer propose for the lease purchase?</label>
                      <input name="lease_purchase_terms" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the proposed duration of the lease?</label>
                      <input type="text" name="proposed_lease_duration_lease_purchase" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What is the monthly payment amount the buyer is seeking for the lease purchase?</label>
                      <input type="number" name="monthly_payment_amount_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What are the specific conditions or requirements outlined by the buyer for the lease option?</label>
                      <input name="lease_purchase_conditions" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div> 
                  <div class="form-group">
                    @php
                      $sellerFeePurchase = [['name' => 'Yes', 'target' => '.sellerFeePurchaseYesAuction', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                    @endphp
                    <label class="fw-bold">Is the buyer offering an option fee?</label>
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
                      <input type="number" name="sellerFeePurchaseYes"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                  </div>               
                </div>
              </div>
              {{-- Lease Purchase  --}}
              {{-- AssumableAuction  --}}
              <div class="form-group assumableAuction d-none">
                <div class="form-group col-md-12">
                    <label class="fw-bold">What assumable terms are being offered?</label>
                    <input type="text" name="assumable_terms_offered"  class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                </div>
                @php
                $downPay = [
                ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Can the buyer pay the requested down payment to the seller to bridge the gap between the asking price and the assumable loan balance?</label>
                  <select name="down_payment_loan_balance" id="down_payment_loan_balance" class="grid-picker" style="justify-content: flex-start;"
                    required>
                    @foreach ($downPay as $item)
                      <option value="{{ $item['name'] }}"
                        data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>{{ $item['name'] }}
                    </option>
                    @endforeach
                  </select>
                </div>
                @php
                $mortgageApproved = [
                  ['name' => 'Yes', 'target' => '.mortgate-approved-assumable', 'icon' => 'fa-regular fa-calendar-days'], 
                  ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-calendar-days']];
                @endphp
                <label class="fw-bold">Has the buyer been approved for a mortgage within the last 90 days?</label>
                <select name="assumabale_mortgage_approved" id="assumable_mortgage_approved" class="grid-picker" style="justify-content: flex-start;"
                  required>
                  @foreach ($mortgageApproved as $item)
                    <option value="{{ $item['name'] }}"
                      data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="{{ $item['icon'] }}"></i>'>{{ $item['name'] }}
                  </option>
                  @endforeach
                </select>
                <div class="form-group mortgate-approved-assumable d-none">
                    <div class="form-group">
                      <label class="fw-bold">How much was the buyer approved for?</label>
                      <input type="text" name="assumable_mortgage_approved_amount" id="assumable_mortgage_approved_amount" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Has the buyer been denied for a mortgage within the last 90 days?</label>
                      <input type="text" name="assumable_mortgage_denied" id="assumable_mortgage_denied" class="form-control has-icon"
                        data-icon="fa-regular fa-calendar-days" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Has the buyer experienced any recent changes in their financial situation that may affect their
                        ability to make mortgage payments?</label>
                      <input type="text" name="assumable_financial_situation" id="assumable_financial_situation" class="form-control has-icon"
                        data-icon="fa-regular fa-check-circle" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">How long has the buyer been employed at their current job?</label>
                      <input type="text" name="assumable_employement_duration" id="assumable_employement_duration" class="form-control has-icon"
                        data-icon="fa-regular fa-calendar-days" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Can the buyer provide details about their employment history over the past few years?</label>
                      <input type="text" name="assumable_employement_detail" id="assumable_employement_detail" class="form-control has-icon"
                        data-icon="fa-regular fa-check-circle" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Does the buyer foresee any changes in their employment status or income in the near future?</label>
                      <input type="text" name="assumable_employement_status_change" id="assumable_employement_status_change" class="form-control has-icon"
                        data-icon="fa-regular fa-check-circle" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What is the buyer’s current gross monthly income?</label>
                      <input type="numbers" name="assumable_gross_income" id="assumable_gross_income" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What are the buyer's monthly debt payments, including credit cards, loans, and other obligations?</label>
                      <input type="numbers" name="assumable_debt_payment" id="assumable_debt_payment" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">What is the buyer's credit score?</label>
                      <input type="numbers" name="assumable_credit_score" id="assumable_credit_score" class="form-control has-icon"
                        data-icon="fa-solid fa-dollar-sign" required>
                    </div>
                </div>            
              </div>            
              {{-- Assumable  --}}
              {{-- Exchange/Trade --}}
              <div class="form-group row tradeAuction d-none">
                <h4>Offered Exchange Item:</h4>
                @php
                  $exchange_trades = [
                    ['name' => 'Another home', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
                    ['name' => 'Vehicles', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
                    ['name' => 'Boats', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
                    ['name' => 'Motorhomes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
                    ['name' => 'Artwork', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
                    ['name' => 'Jewelry', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
                    ['name' => 'Other', 'target' => '.otherTradeAuction', 'icon' => 'fa-regular fa-circle-check']];
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
                  <div class="form-group col-md-12 otherTradeAuction d-none">
                    <label class="fw-bold">Acceptable Exchange Item:</label>
                    <input type="text" name="otherTrade" class="form-control has-icon" data-icon="fa-regular fa-check-circle" required>
                  </div>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item?</label>
                  <input type="number" name="estimatedTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
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
              {{-- Exchange/Trade --}}
            <div class="form-group">
              <label class="fw-bold">Offered Escrow Deposit:</label>
              <input type="number" name="escrow_amount" id="term_escrow_amount" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-dollar" required>
            </div>
            <div class="form-group">
              <label class="fw-bold" for="closing_date">Offered Closing Date:</label>
              <input type="date" name="closing_days" id="closing_days" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-calendar-days" required>
            </div>
            <div class="form-group">
              <label class="fw-bold" for="closing_date">Offered Closing Days:</label>
              <input type="text" name="desired_days" id="desired_days" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-calendar-days" required>
            </div>
            <div class="form-group">
              @php
                $contigencies = [['name' => 'Inspection contingency', 'target' => '.inspection'], ['name' => 'Appraisal contingency', 'target' => '.appraisal'], ['name' => 'Financing contingency', 'target' => '.financing'], ['name' => 'Sale of a property contingency', 'target' => '.sale'], ['name' => 'None', 'target' => ''],['name' => 'Other', 'target' => '.otherContingency'],];
              @endphp
              <label class="fw-bold">Acceptable Contingencies:</label>
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
                <div class="form-group">
                  <label class="fw-bold">Offered contingency:</label>
                  <input type="text" name="acceptable" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Offered contingency (days):</label>
                  <input type="number" name="acceptable_days" id="closing" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                </div>
              </div>
            </div>
            <div class="form-group row">
              @php
                $yes_or_nos = [['name' => 'Yes', 'target' => '.custom_seller_close', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Is the buyer requesting a credit from the seller at closing?</label>
                <select class="grid-picker" name="creditForm" id="carport" style="justify-content: flex-start;"
                  required>
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
              <div class="form-group custom_seller_close d-none">
                <div class="d-flex justify-content-between aalign-items-center">
                  <label class="fw-bold">Please enter the requested seller's credit:</label>
                  <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                      <button type="button" class="select-btn me-1 active"
                          data-type="amount">$</button>
                      <button type="button" class="select-btn" data-type="percent">%</button>
                  </div>
                </div>
                <input type="text" name="sellerPreminum" class="form-control form-control has-icon"
                  data-icon="fa-solid fa-dollar-sign" required>
              </div>
            </div>
            {{-- buyer represented --}}
            <div class="form-group">
              @php
              $yes_or_nos = [
                ['name' => 'Yes', 'target' => '.buyer-represented', 'icon' => 'fa-regular fa-circle-check'], 
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Is the buyer represented by a real estate agent?</label>
                <select class="grid-picker" name="buyer_represented" id="buyer_represented" style="justify-content: flex-start;"
                  required>
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
              <div class="form-group buyer-represented d-none">
                @php
                $buyerRepresentedOpt = [
                  ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
                  ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                  ['name' => 'Negotiable', 'target' => '', 'icon' => 'fa-regular fa-check-circle'],
                  ['name' => 'No compensation was offered', 'target' => '', 'icon' => 'fa-regular fa-check-circle'],
                ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Will the buyer's agent accept the compensation currently offered?</label>
                  <select class="grid-picker" name="buyer_agent_accept_compensation" id="buyer_agent_accept_compensation" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($buyerRepresentedOpt as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group negotiable-no-compensation d-none">
                  @php
                  $negotiableOpt = [
                    ['name' => 'Yes', 'target' => '.buyer-agent-commission', 'icon' => 'fa-regular fa-circle-check'], 
                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                  ];
                  @endphp
                  <div class="form-group">
                    <label class="fw-bold">Does the buyer request that the seller pay the buyer's agent's commission,
                      which will be taken out of the proceeds at closing as a closing cost paid by
                      the seller?</label>
                    <select class="grid-picker" name="buyer_requests_agent_commission" id="buyer_requests_agent_commission" style="justify-content: flex-start;"
                      required>
                      <option value="">Select</option>
                      @foreach ($negotiableOpt as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                          class="card flex-row" style="width:calc(33.3% - 10px);"
                          data-icon='<i class="{{ $item['icon'] }}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                  <div class="form-group buyer-agent-commission d-none">
                    @php
                    $agentCommissionOpt = [
                      ['name' => '2%', 'target' => '', 'icon' => 'fa-regular fa-check-circle'], 
                      ['name' => '2.5%', 'target' => '', 'icon' => 'fa-regular fa-check-circle'],
                      ['name' => '3%', 'target' => '', 'icon' => 'fa-regular fa-check-circle'],
                      ['name' => 'Other', 'target' => '.agent-commission-other', 'icon' => 'fa-regular fa-check-circle'],
                    ];
                    @endphp
                    <div class="form-group">
                      <label class="fw-bold">How much is the buyer requesting that the seller pay in commission to the buyer's real estate agent?</label>
                      <select class="grid-picker" name="buyer_requests_agent_commission_amount" id="buyer_requests_agent_commission_amount" style="justify-content: flex-start;"
                        required>
                        <option value="">Select</option>
                        @foreach ($agentCommissionOpt as $item)
                          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                            class="card flex-row" style="width:calc(33.3% - 10px);"
                            data-icon='<i class="{{ $item['icon'] }}"></i>'>
                            {{ $item['name'] }}
                          </option>
                        @endforeach
                      </select>
                    </div>
                    <div class="form-group agent-commission-other d-none">
                      <div class="d-flex justify-content-between aalign-items-center">
                        <label class="fw-bold">How much is the buyer requesting that the seller pay in commission to the buyer's real estate agent?</label>
                        <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                            <button type="button" class="select-btn me-1 active"
                                data-type="amount">$</button>
                            <button type="button" class="select-btn" data-type="percent">%</button>
                        </div>
                      </div>
                      <input type="number" name="buyer_requests_agent_commission_amount_other" id="buyer_requests_agent_commission_amount_other" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            {{-- buyer represented --}}
            {{--  --}}
            @if ($page_data['auction']->get->auction_type == 'Traditional Listing')
              <div class="form-group">
                <label class="fw-bold">When will the offer expire?</label>
                <input type="datetime-local" name="offer_expiry" id="offer-expiry" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required />
              </div>
            @else
              @php
              $increaseBidPriceOpt = [
                ['name' => 'Yes', 'target' => '.increase-bid', 'icon' => 'fa-regular fa-circle-check'], 
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Would the buyer like to set an escalation clause to automatically
                  increase their bid price and terms up to a maximum amount specified by the buyer in the
                  event of multiple offers?</label>
                <select class="grid-picker" name="increase_bid_price" id="increase_bid_price" style="justify-content: flex-start;"
                  required>
                  <option value="">Select</option>
                  @foreach ($increaseBidPriceOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      class="card flex-row" style="width:calc(33.3% - 10px);"
                      data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group increase-bid d-none">
                <div class="form-group">
                  <label class="fw-bold">Autobid Price:</label>
                  <input type="number" name="autobid_price" id="autobid_price" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required />
                </div>
                <div class="form-group">
                  <label class="fw-bold">Autobid Escrow Deposit:</label>
                  <input type="number" name="autobid_escrow_deposit" id="autobid_escrow_deposit" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required />
                  <small>(The Autobid escrow deposit will increase by $1000 over the highest offer, up to the Autobid escrow deposit.)</small>
                </div>
                <div class="form-group">
                  @php
                    $contigencies = [
                      ['name' => 'Inspection contingency', 'target' => '.autobid-inspection'], 
                      ['name' => 'Appraisal contingency', 'target' => '.autobid-appraisal'], 
                      ['name' => 'Financing contingency', 'target' => '.autobid-financing'], 
                      ['name' => 'Sale of a property contingency', 'target' => '.autobid-sale'], 
                      ['name' => 'None', 'target' => ''],
                      ['name' => 'Other', 'target' => '.autobid-otherContingency'],];
                  @endphp
                  <label class="fw-bold">Autobid Contingencies:</label>
                  <select class="grid-picker" name="autobid_contingency" id="autobid_contingency"
                    style="justify-content: flex-start;">
                    <option value="">Select</option>
                    @foreach ($contigencies as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <small>(The Autobid contingencies will decrease by 2 days from the lowest contingency days bid by another buyer, up to the set Autobid contingency days.)</small>
                  <div class="form-group autobid-inspection d-none">
                    <label class="fw-bold">Inspection contingency (days):</label>
                    <input type="number" name="autobid_inspection" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group autobid-appraisal d-none">
                    <label class="fw-bold">Appraisal contingency (days):</label>
                    <input type="number" name="autobid_appraisal" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group autobid-financing d-none">
                    <label class="fw-bold">Financing contingency (days):</label>
                    <input type="number" name="autobid_finance" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group autobid-sale d-none">
                    <label class="fw-bold"> Sale of a property contingency (days): </label>
                    <input type="number" name="autobid_saleContingency" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                  </div>
                  <div class="form-group autobid-otherContingency d-none">
                    <div class="form-group">
                      <label class="fw-bold">Offered contingency:</label>
                      <input type="text" name="autobid_offered_contingency" id="autobid_offered_contingency" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                    </div>
                    <div class="form-group">
                      <label class="fw-bold">Offered contingency (days):</label>
                      <input type="number" name="autobid_offered_contingency_days" id="closing" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
                      <small>(This contingency will remain the same for all autobids.)</small>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Offered Closing Days:</label>
                  <input type="number" name="autobid_closing_date" id="autobid_closing_date" class="form-control has-icon" data-icon="" required />
                  <small>The Autobid closing days will decrease by 2 days from the lowest closing days offered by another buyer, up to the Autobid's set offered closing days.</small>
                </div>
              </div>
            @endif
            <div class="form-group">
                <label class="fw-bold">Acceptable Real Estate Agent Commission:</label>
                <input type="number" name="agent_commission" id="agent_commission" class="form-control has-icon" data-icon="" />
            </div>
            <div class="form-group">
                <label class="fw-bold">Offer Expires:</label>
                <input type="datetime-local" name="offer_expiry" id="offer_expiry" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" />
            </div>
            <div class="form-group">
                <label class="fw-bold">Additional Details or Countered Terms:</label>
                <textarea name="additional_details_counter_terms" class="form-control"  cols="30" rows="10" required></textarea>
            </div>
            <div class="form-group row">
              <div class="form-group">
                <label class="fw-bold">Are there any additional information or notes that the buyer would like to provide? </label>
                <textarea name="additionalInfo" class="form-control"  cols="30" rows="10" required></textarea>
              </div>
            </div>
          </div>
          <div class="wizard-step">
            <h4 style="color: black">This information sent privately to the seller’s agent:</h4>
            @if (auth()->user()->user_type == 'agent')
              <h4>Buyer’s Agent Information:</h4>
            @else
              <h4>Buyer’s Information:</h4>
            @endif
            @if (Auth::user()->user_type == 'buyer')
              @php
                $buyer_types = [['name' => 'Buyer', 'target' => '.simple-buyer'], ['name' => 'Wholesaller', 'target' => '.wholesaller'], ['name' => 'Asset Manager', 'target' => '.asset-manager'], ['name' => 'Attorney', 'target' => '.attorney'], ['name' => 'Builder', 'target' => '.builder']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Buyer Type:</label>
                <select class="grid-picker" name="buyer_type" id="buyer_type" style="justify-content: flex-start;"
                  onchange="show_card_field();" required>
                  <option value="">Select</option>
                  @foreach ($buyer_types as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(20% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            @endif
            <div class="form-group card-field @if (Auth::user()->user_type == 'agent') d-none @endif ">
              <div class="row">
                <div class="form-group col-6 simple-buyer d-none">
                  <label class="fw-bold">First Name:</label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-user" name="first_name">
                </div>
                <div class="form-group col-6 simple-buyer d-none">
                  <label class="fw-bold">Last Name:</label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-user" name="last_name">
                </div>
              </div>
              <div class="row">
                <div class="form-group col-6 simple-buyer d-none">
                  <label class="fw-bold">Phone Number:</label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-phone" name="phone_number">
                </div>
                <div class="form-group col-6 simple-buyer d-none">
                  <label class="fw-bold">Email:</label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-envelope" name="email">
                </div>
              </div>
              {{-- <div class="form-group">
                <label class="fw-bold">Business Card:</label>
                <input type="file" class="form-control" accept="image/*" name="card"
                  value="{{ @$auction->get->business_card }}" required>
              </div> --}}
            </div>
            <div class="form-group card-field @if (Auth::user()->user_type == 'buyer') d-none @endif ">
              <div class="row">
                <div class="form-group col-6">
                  <label class="fw-bold">First Name:</label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-user" name="first_name">
                </div>
                <div class="form-group col-6">
                  <label class="fw-bold">Last Name:</label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-user" name="last_name">
                </div>
              </div>
              <div class="row">
                <div class="form-group col-6">
                  <label class="fw-bold">Phone Number:</label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-phone" name="phone_number">
                </div>
                <div class="form-group col-6">
                  <label class="fw-bold">Email:</label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-envelope" name="email">
                </div>
              </div>
              <div class="row">
                <div class="form-group col-6">
                  <label class="fw-bold">Brokerage:</label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-handshake" name="first_name">
                </div>
                <div class="form-group col-6">
                  <label class="fw-bold">Real Estate License #:</label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-id-card" name="last_name">
                </div>
              </div>
              <div class="row">
                <div class="form-group col-6">
                  <label class="fw-bold">NAR Member ID (NRDS ID):</label>
                  <input type="text" class="form-control has-icon" name="nar" data-icon="fa-solid fa-id-badge">
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
    $(function() {
      $(document).ready(function() {
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
    $('#buyer_agent_accept_compensation').change(function(){
      let val = $(this).val();
      if(val == 'No' || val == 'Negotiable' || val == 'No compensation was offered' ){
        $('.negotiable-no-compensation').removeClass('d-none');
      }else{
        $('.negotiable-no-compensation').addClass('d-none');
      }
    })
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
  </script>

  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush
