  {{-- Slide 1 --}}
  <div class="wizard-step" id="after_this" data-step="1">
    <h4>Please provide the property's complete address, along with the city, county, and state, pertaining to the real estate asset that the seller intends to place on the market.</h4>
    
    <div class="form-group">
      <label class="fw-bold" for="address">Address:</label>
      <input type="text" name="address" value="{{$auction->get->address}}"data-type="address" placeholder="" id="address"
        class="form-control has-icon search_places" data-icon="fa-solid fa-location-dot" required>
    </div>
    <div class="form-group">
      <label class="fw-bold" for="unit_number">Unit Number:</label>
      <input type="text" name="unit_number" value="{{$auction->get->unit_number}}" data-type="unit_number" placeholder="" id="unit_number"
        class="form-control has-icon search_places" data-icon="fa-solid fa-location-dot" required>
    </div>

    <div class="form-group">
      <label class="fw-bold" for="address">County:</label>
      <input type="text" name="county" value="{{$auction->get->county}}" placeholder="" id="county"
        class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city" required>
    </div>
  </div>
  {{-- Slide 1 --}}
  {{-- Slide 2 --}}
  <div class="wizard-step" data-step="2">
    <?php
      $listingDateTime = $auction->listing_date;
      $listingDate = (new DateTime($listingDateTime))->format('Y-m-d');
      
      $expirationDateTime = $auction->expiration_date; // Example date from database
      $expirationDate = (new DateTime($expirationDateTime))->format('Y-m-d');
    ?>
    <div class="form-group">
      <label for="address" class="fw-bold">Listing Date:</label>
      <input type="date" name="listing_date" value="{{isset($listingDate) ? $listingDate : ''}}" id="listing_date" class="form-control has-icon search_places"
        data-icon="fa-regular fa-calendar-days" required>
    </div>

    <div class="form-group">
      <label for="address" class="fw-bold">Expiration Date:</label>
      <input type="date" name="expiration_date" value="{{isset($expirationDate) ? $expirationDate : ''}}" id="expiration_date"
        class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days" required>
    </div>
  </div>
  {{-- Slide 2 --}}
  {{-- Slide 3 --}}
  <div class="wizard-step" data-step="3">
    <div class="form-group">
      <label class="fw-bold">Representation:</label>

      @php
        $representation = [['name' => 'Seller Represented', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => ''], ['name' => 'Seller Not Represented', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => '']];
      @endphp

      <select name="representation" id="representation" class="grid-picker" style="justify-content: flex-start;"
        required>
        <option value=""></option>
        @foreach ($representation as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
            style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}' {{isset($auction->get->representation) && $auction->get->representation == $item['name'] ? 'selected' : ''}} >
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
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
          $auction_types = [['target' => '.auctionTimer', 'name' => 'Auction Listing', 'icon' => '<i class="fa-regular fa-clock"></i>'], ['target' => '.traditionalTime', 'name' => 'Traditional Listing', 'icon' => '<i class="fa-solid fa-clipboard-list"></i>']];
        @endphp
        <select name="auction_type" id="auction_type" class="grid-picker" required>
          <option value=""></option>
          @foreach ($auction_types as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}' {{isset($auction->get->auction_type) && $auction->get->auction_type == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="form-group  d-none auctionTimer">
      <label class="fw-bold">
        Timer Length:
      </label>
      <div>
        @php
          $timer_lengths = [['name' => '1 Day', 'class' => 'normal-length'], ['name' => '3 Days', 'class' => 'normal-length'], ['name' => '5 Days', 'class' => 'normal-length'], ['name' => '7 Days', 'class' => 'normal-length'], ['name' => '10 Days', 'class' => 'normal-length'], ['name' => '14 Days', 'class' => 'normal-length'], ['name' => '21 Days', 'class' => 'normal-length'], ['name' => '30 Days', 'class' => 'normal-length'], ['name' => '45 Days', 'class' => 'normal-length'], ['name' => '60 Days', 'class' => 'normal-length'], ['name' => '75 Days', 'class' => 'normal-length'], ['name' => '90 Days', 'class' => 'normal-length'], ['name' => 'No time limit', 'class' => 'traditional-length']];
        @endphp
        <select name="auction_length" id="auction_length" class="auction_length grid-picker"
          style="justify-content: flex-start;" required>
          <option value=""></option>
          @foreach ($timer_lengths as $item)
            <option value="{{ $item['name'] }}" data-target="" class="card flex-row {{ $item['class'] }}"
              style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->auction_length) && $auction->get->auction_length == $item['name'] ? 'selected' : ''}}>
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
        Special Sale Provision:
      </label>
      <div>
        @php
          $special_sale_provision = [  
            ['target' => '.assignment_contract_res','name' => 'Assignment Contract (Wholesale Property)'],['name' => 'Auction', 'target' => ''], ['name' => 'Bank Owned/Reo', 'target' => ''], ['name' => 'Government Owned', 'target' => ''], ['name' => 'Probate Listing', 'target' => ''], ['name' => 'Short Sale', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.special_sale_provision']];
        @endphp
        <select name="special_sale" id="special_sale" class="grid-picker" style="justify-content: flex-start;"
          required>
          <option value=""></option>
          @foreach ($special_sale_provision as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->special_sale) && $auction->get->special_sale == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="form-group special_sale_provision d-none">
      <label class="fw-bold">Special Sale Provision:</label>
      <input type="text" name="custom_special_sale_provision" value="{{isset($auction->get->custom_special_sale_provision) ? $auction->get->custom_special_sale_provision : ''}}" id="custom_special_sale_provision"
        class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
            data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->contribute_term) && $auction->get->contribute_term == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>

      <div class="form-group commercialseller_contract_yes d-none">
        <div class="d-flex justify-content-between aalign-items-center">
          <label class="fw-bold">What fee would the seller pay the agent to assign the contract?</label>
          <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
              <button type="button" class="select-btn me-1 active"
                  data-type="amount">$</button>
              <button type="button" class="select-btn" data-type="percent">%</button>
          </div>
        </div>
        <input type="number" class="form-control has-icon" placeholder=""
          name="commercialseller_contract_yes" value="{{isset($auction->get->commercialseller_contract_yes) ? $auction->get->commercialseller_contract_yes : ''}}" data-icon="fa-solid fa-dollar-sign"
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
              data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->custom_seller_contract_no) && $auction->get->custom_seller_contract_no == $item['name'] ? 'selected' : ''}}>
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
        <input type="number"  name="buy_now_price" value="{{isset($auction->get->buy_now_price) ? $auction->get->buy_now_price : ''}}"  id="buy_now_price"
          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
          data-msg-required="Please enter Buy Now Price">
      </div>
      <div class="form-group ">
        <label class="fw-bold" for="reserve_price" required>Buy Now Price Per Sqft:</label>
        <input type="number" name="buy_now_price_per_sqfeet" value="{{isset($auction->get->buy_now_price_per_sqfeet) ? $auction->get->buy_now_price_per_sqfeet : ''}}"  id="reserve_price"
          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
      </div>
      <div class="form-group ">
        <label class="fw-bold" for="starting_price" required>Starting Price:</label>
        <input type="number"  name="starting_price" value="{{isset($auction->get->starting_price) ? $auction->get->starting_price : ''}}"  id="starting_price"
          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign"
          data-msg-required="Please enter Starting Price">
      </div>
      <div class="form-group ">
        <label class="fw-bold" for="reserve_price" required>Reserve Price:</label>
        <input type="number"  name="reserve_price" value="{{isset($auction->get->reserve_price) ? $auction->get->reserve_price : ''}}"  id="reserve_price"
          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
      </div>
      <div class="form-group row ">
        <div class="form-group">
          <div class="d-flex justify-content-between aalign-items-center">
            <label class="fw-bold">Acceptable Escrow Deposit:</label>
            <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                <button type="button" class="select-btn me-1 active"
                    data-type="amount">$</button>
                <button type="button" class="select-btn" data-type="percent">%</button>
            </div>
          </div>
          <input type="number" name="escrow_amount" value="{{isset($auction->get->escrow_amount) ? $auction->get->escrow_amount : ''}}" id="term_escrow_amount" placeholder=""
            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
        </div>
      </div>
      <div class="form-group row ">
        <div class="form-group">
          <label class="fw-bold">Number of Days the Seller Will Accept for Closing:</label>
          <input type="number" name="closing_days" value="{{isset($auction->get->closing_days) ? $auction->get->closing_days : ''}}" id="closing_days" placeholder=""
            class="form-control has-icon" data-icon="fa-solid fa-calendar-day" required>
        </div>
      </div>
      <div class="form-group">
        @php
          $contigencies = [['name' => 'Inspection contingency', 'target' => '.inspectionAuction'], ['name' => 'Appraisal contingency', 'target' => '.appraisalAuction'], ['name' => 'Financing contingency', 'target' => '.financingAuction'], ['name' => 'Sale of a property contingency', 'target' => '.saleAuction'], ['name' => 'None', 'target' => ''],['name' => 'Other', 'target' => '.otherContingencyAuction'],];
        @endphp
        <label class="fw-bold">Acceptable Contingencies: </label>
        <select class="grid-picker" name="contigencies_accepted_by_seller[]" id="contigencies_accepted_by_seller"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($contigencies as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->contigencies_accepted_by_seller) && in_array($item['name'] , json_decode($auction->get->contigencies_accepted_by_seller) ?? []) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group inspectionAuction d-none">
          <label class="fw-bold">Inspection contingency (days):</label>
          <input type="number" name="inspection_auction" value="{{isset($auction->get->inspection_auction) ? $auction->get->inspection_auction : ''}}" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
        <div class="form-group appraisalAuction d-none">
          <label class="fw-bold">Appraisal contingency (days):</label>
          <input type="number" name="appraisal_auction" value="{{isset($auction->get->appraisal_auction) ? $auction->get->appraisal_auction : ''}}" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
        <div class="form-group financingAuction d-none">
          <label class="fw-bold">Financing contingency (days):</label>
          <input type="number" name="finance_auction" value="{{isset($auction->get->finance_auction) ? $auction->get->finance_auction : ''}}" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
        <div class="form-group saleAuction d-none">
          <label class="fw-bold"> Sale of a property contingency (days): </label>
          <input type="number" name="saleContingency_auction" value="{{isset($auction->get->saleContingency_auction) ? $auction->get->saleContingency_auction : ''}}" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
        <div class="form-group otherContingencyAuction d-none">
          <label class="fw-bold">Acceptable contingency:</label>
          <input type="text" name="acceptable_auction" value="{{isset($auction->get->acceptable_auction) ? $auction->get->acceptable_auction : ''}}" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>

          <label class="fw-bold">Acceptable contingency (days):</label>
          <input type="number" name="acceptable_days_auction" value="{{isset($auction->get->acceptable_days_auction) ? $auction->get->acceptable_days_auction : ''}}" id="" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
      </div>
      <div class="form-group">
        @php
          $term_financings = [
              ['name' => 'Assumable', 'target' => '.assumableAuction'],
              ['name' => 'Cash', 'target' => ''],
              ['name' => 'Conventional', 'target' => ''],
              ['name' => 'Cryptocurrency', 'target' => '.cryptoAuction'],
              ['name' => 'Exchange/Trade', 'target' => '.tradeAuction'],
              ['name' => 'FHA', 'target' => ''],
              ['name' => 'Jumbo', 'target' => ''],
              ['name' => 'Lease Option', 'target' => '.leaseOptionAuction'],
              ['name' => 'Lease Purchase', 'target' => '.leasePurchaseAuction'],
              ['name' => 'Non-Fungible Token (NFT)', 'target' => '.nftAuction'],
              ['name' => 'No-Doc', 'target' => ''],
              ['name' => 'Non-QM', 'target' => ''],
              ['name' => 'Seller Financing', 'target' => '.sellerFinancingAuction'],
              ['name' => 'USDA', 'target' => ''],
              ['name' => 'VA', 'target' => ''],
              ['name' => 'Other', 'target' => '.otherFinancingAuction'],
          ];
        @endphp
          <label class="fw-bold">Acceptable Currency/ Financing:</label>
          <select class="grid-picker" name="term_financings[]" id="term_financings"
            style="justify-content: flex-start;" multiple required>
            <option value="">Select</option>
            @foreach ($term_financings as $item)
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
                style="width:calc(33.3% - 10px);" {{isset($auction->get->term_financings) && in_array($item['name'], json_decode($auction->get->term_financings) ?? [])  ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
        </div>
          {{-- Other --}}
          <div class="form-group otherFinancingAuction d-none">
              <label class="fw-bold">Acceptable Currency/Financing:</label>
              <input type="text" name="otherFinancing" value="{{isset($auction->get->otherFinancing) ? $auction->get->otherFinancing : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
          </div>
          {{-- Other --}}
          {{-- NFTAuction  --}}
          <div class="form-group nftAuction d-none">
            <div class="form-group col-md-12">
              <label class="fw-bold">What type of Non-Fungible Token (NFT) will the seller accept?</label>
              <input type="text" name="type_of_NFT_accepted" value="{{isset($auction->get->type_of_NFT_accepted) ? $auction->get->type_of_NFT_accepted : ''}}" id="type_of_NFT_accepted" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">What percentage of the sales price will the seller accept in the form of a Non-Fungible Token (NFT)?</label>
                <input type="number" name="percentage_in_NFT" value="{{isset($auction->get->percentage_in_NFT) ? $auction->get->percentage_in_NFT : ''}}" id="percentage_in_NFT" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
                <input type="number" name="percentage_in_cash" value="{{isset($auction->get->percentage_in_cash) ? $auction->get->percentage_in_cash : ''}}" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
            </div>
          </div>
        {{-- NFT  --}}
        {{-- CryptoAuction  --}}
        <div class="form-group cryptoAuction d-none">
          <div class="form-group col-md-12">
            <label class="fw-bold">What type of cryptocurrency will the seller accept?</label>
            <input type="text" name="cryptocurrency_type" value="{{isset($auction->get->cryptocurrency_type) ? $auction->get->cryptocurrency_type : ''}}" id="cryptocurrency_type" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What percentage of the sales price will the seller accept in cryptocurrency?</label>
              <input type="number" name="percentage_in_crypto" value="{{isset($auction->get->percentage_in_crypto) ? $auction->get->percentage_in_crypto : ''}}" id="percentage_in_crypto" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
              <input type="number" name="percentage_in_cash" value="{{isset($auction->get->percentage_in_cash) ? $auction->get->percentage_in_cash : ''}}" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
          </div> 
          <small>Note: Cryptocurrency can be converted to cash at closing.</small>              
        </div>
        {{-- CryptoAuction  --}}
        {{-- seller financing --}}
        <div class="form-group row sellerFinancingAuction d-none">
          <label class="fw-bold">Please enter the seller’s desired seller financing terms:</label>
        <div class="form-group col-md-3">
          <label class="fw-bold">Purchase Price:</label>
          <input type="number" name="purchase_price_seller_financing" value="{{isset($auction->get->purchase_price_seller_financing) ? $auction->get->purchase_price_seller_financing : ''}}" id="purchase_price_seller_financing"
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
          <input type="number" name="down_payment_seller_financing" value="{{isset($auction->get->down_payment_seller_financing) ? $auction->get->down_payment_seller_financing : ''}}" id="down_payment_seller_financing"
            class="form-control has-icon" data-icon="fa-solid fa-percent" required>
        </div>
        <div class="form-group col-md-3">
          <div class="d-flex justify-content-between aalign-items-center">
            <label class="fw-bold">Seller Financing Amount:</label>
            <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                <button type="button" class="select-btn me-1 active"
                    data-type="amount">$</button>
                <button type="button" class="select-btn" data-type="percent">%</button>
            </div>
          </div>
          <input type="number" name="seller_financing_amount" value="{{isset($auction->get->seller_financing_amount) ? $auction->get->seller_financing_amount : ''}}" id="seller_financing_amount"
            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
        </div>
        <div class="form-group col-md-3">
          <label class="fw-bold">Interest Rate:</label>
          <input type="number" name="interest_rate_seller_financing" value="{{isset($auction->get->interest_rate_seller_financing) ? $auction->get->interest_rate_seller_financing : ''}}" id="interest_rate_seller_financing"
            class="form-control has-icon" data-icon="fa-solid fa-percent" required>
        </div>
        <div class="form-group col-md-3">
          <label class="fw-bold">Loan Duration:</label>
          <input type="text" name="term_seller_financing" value="{{isset($auction->get->term_seller_financing) ? $auction->get->term_seller_financing : ''}}" id="term_seller_financing"
            class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
        <div class="form-group col-md-3">
          <label class="fw-bold">Monthly Payment with Principal and Interest:</label>
          <input type="number" name="monthly_payment_seller_financing" value="{{isset($auction->get->monthly_payment_seller_financing) ? $auction->get->monthly_payment_seller_financing : ''}}" id="monthly_payment_seller_financing"
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
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" 
                style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' 
                {{isset($auction->get->ballonPenalty) && $auction->get->ballonPenalty == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
            </option>
            @endforeach
          </select>
          <div class="form-group  ballonPenaltyYesAuction d-none">
            <label class="fw-bold">What is the prepayment penalty amount? </label>
            <input type="number" name="ballonPenaltyYes" value="{{isset($auction->get->ballonPenaltyYes) ? $auction->get->ballonPenaltyYes : ''}}" id="closing_costs" class="form-control has-icon"
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
                data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->balloonPay) && $auction->get->balloonPay == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
            </option>
            @endforeach
          </select>
            <div class="form-group balloonPayYesAuction d-none">
              <div class="form-group">
                <label class="fw-bold">How much is the balloon payment? </label>
                <input type="number" name="balloonPayment" value="{{isset($auction->get->balloonPayment) ? $auction->get->balloonPayment : ''}}" id="closing_costs" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar-sign" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">When is the balloon payment due? </label>
                <input type="text" name="balloonDue" value="{{isset($auction->get->balloonDue) ? $auction->get->balloonDue : ''}}" id="closing_costs" class="form-control has-icon"
                  data-icon="fa-regular fa-calendar-days" required>
              </div>
            </div>
          </div>
        </div>
        {{-- seller financing --}}
        {{-- Lease Option  --}}
          <div class="form-group leaseOptionAuction d-none">
            <div class="form-group col-md-12">
              <label class="fw-bold">What is the seller's desired offering price for a lease option? </label>
              <input type="number" name="desired_offering_price" value="{{isset($auction->get->desired_offering_price) ? $auction->get->desired_offering_price : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">What specific terms does the seller propose for the lease option?</label>
                <input name="lease_option_terms" value="{{isset($auction->get->lease_option_terms) ? $auction->get->lease_option_terms : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">What is the proposed duration of the lease?</label>
                <input type="text" name="proposed_lease_duration" value="{{isset($auction->get->proposed_lease_duration) ? $auction->get->proposed_lease_duration : ''}}" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">What is the monthly payment amount the seller is seeking? </label>
                <input type="number" name="monthly_payment_amount" value="{{isset($auction->get->monthly_payment_amount) ? $auction->get->monthly_payment_amount : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">What are the specific conditions or requirements outlined by the seller for the lease option?</label>
                <input name="lease_option_conditions" value="{{isset($auction->get->lease_option_conditions) ? $auction->get->lease_option_conditions : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
            </div>
            <div class="form-group">
              @php
                $sellerFeeOption = [['name' => 'Yes', 'target' => '.sellerFeeOptionYesAuction', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <label class="fw-bold">Does the seller require an option fee? </label>
              <select class="grid-picker" name="sellerFeeOption" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($sellerFeeOption as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->sellerFeeOption) && $auction->get->sellerFeeOption == $item['name'] ? 'selected' : ''}}>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group col-md-12 sellerFeeOptionYesAuction d-none">
                <label class="fw-bold">How much is the option fee? </label>
                <input type="number" name="sellerFeeOptionYes" value="{{isset($auction->get->sellerFeeOptionYes) ? $auction->get->sellerFeeOptionYes : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
            </div>               
          </div>
        </div>
        {{-- Lease Option  --}}
        {{-- Lease Purchase  --}}
          <div class="form-group leasePurchaseAuction d-none">
            <div class="form-group col-md-12">
              <label class="fw-bold">What is the seller's desired offering price for a lease purchase?</label>
              <input type="number" name="desired_offering_price_lease_purchase" value="{{isset($auction->get->desired_offering_price_lease_purchase) ? $auction->get->desired_offering_price_lease_purchase : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label>
                <input name="lease_purchase_terms" value="{{isset($auction->get->lease_purchase_terms) ? $auction->get->lease_purchase_terms : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">What is the proposed duration of the lease?</label>
                <input type="text" name="proposed_lease_duration_lease_purchase" value="{{isset($auction->get->proposed_lease_duration_lease_purchase) ? $auction->get->proposed_lease_duration_lease_purchase : ''}}" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label>
                <input type="number" name="monthly_payment_amount_lease_purchase" value="{{isset($auction->get->monthly_payment_amount_lease_purchase) ? $auction->get->monthly_payment_amount_lease_purchase : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">What are the specific conditions or requirements outlined by the seller for the lease purchase?</label>
                <input name="lease_purchase_conditions" value="{{isset($auction->get->lease_purchase_conditions) ? $auction->get->lease_purchase_conditions : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
            </div>    
            <div class="form-group">
              @php
                $sellerFeePurchase = [['name' => 'Yes', 'target' => '.sellerFeePurchaseYesAuction', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <label class="fw-bold">Does the seller require an option fee?</label>
              <select class="grid-picker" name="sellerFeePurchase" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($sellerFeePurchase as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->sellerFeePurchase) && $auction->get->sellerFeePurchase == $item['name'] ? 'selected' : ''}}>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group col-md-12 sellerFeePurchaseYesAuction d-none">
                <label class="fw-bold">How much is the option fee?  </label>
                <input type="number" name="sellerFeePurchaseYes"  value="{{isset($auction->get->sellerFeePurchaseYes) ? $auction->get->sellerFeePurchaseYes : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
            </div>               
          </div>
        </div>
        {{-- Lease Purchase  --}}
        {{-- AssumableAuction  --}}
          <div class="form-group assumableAuction d-none">
            <div class="form-group col-md-12">
              <label class="fw-bold">What assumable terms are being offered?</label>
              <input type="text" name="assumable_terms_offered" value="{{isset($auction->get->assumable_terms_offered) ? $auction->get->assumable_terms_offered : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
            </div>
            <div class="form-group col-md-12">
                <label class="fw-bold">Are there any restrictions or qualifications for a buyer assuming the existing financing?</label>
                <input type="text" name="restrictions_or_qualifications" value="{{isset($auction->get->restrictions_or_qualifications) ? $auction->get->restrictions_or_qualifications : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
            </div>
            <div class="form-group col-md-12">
              <label class="fw-bold">What is the interest rate of the assumable loan?</label>
              <input type="number" name="assumable_interest" value="{{isset($auction->get->assumable_interest) ? $auction->get->assumable_interest : ''}}" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
            </div>
            <div class="form-group col-md-12">
              <label class="fw-bold">What is the monthly payment, including principal and interest, for the assumable loan?</label>
              <input type="number" name="assumable_monthly_payment" value="{{isset($auction->get->assumable_monthly_payment) ? $auction->get->assumable_monthly_payment : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
            </div>
            <div class="form-group col-md-12">
                @php
                $outstandingBalance = [['name' => 'Yes', 'target' => '.outstandingBalanceYesAuction', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <div class="form-group">
                <div class="form-group">
                  <label class="fw-bold">What is the outstanding balance on the existing loan?</label>
                  <input type="number" name="assumable_balance_loan" value="{{isset($auction->get->assumable_balance_loan) ? $auction->get->assumable_balance_loan : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                </div>
                <div class="form-group">
                  <div class="d-flex justify-content-between aalign-items-center">
                    <label class="fw-bold">What is the down payment that the buyer would need to pay the seller to bridge the gap between the asking price and the assumable loan balance?</label>
                    <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                        <button type="button" class="select-btn me-1 active"
                            data-type="amount">$</button>
                        <button type="button" class="select-btn" data-type="percent">%</button>
                    </div>
                  </div>
                  <input type="number" name="loan_balance_down_payment" value="{{isset($auction->get->loan_balance_down_payment) ? $auction->get->loan_balance_down_payment : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
              </div>
            </div>
          </div>            
        </div>            
        {{-- Assumable  --}}
        {{-- Exchange/trade --}}
        <div class="form-group row tradeAuction d-none">
          @php
          $exchange_trades = [
            ['name' => 'Another home', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
            ['name' => 'Artwork', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
            ['name' => 'Boat', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
            ['name' => 'Jewelry', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
            ['name' => 'Motorhome', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
            ['name' => 'Vehicle', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], 
            ['name' => 'Other', 'target' => '.otherTradeAuction', 'icon' => 'fa-regular fa-circle-check']
          ];
          @endphp
          <div class="form-group">
            <label class="fw-bold">Acceptable Exchange Item:</label>
            <select class="grid-picker" name="exchange_trade" id="contigencies_accepted_by_seller"
              style="justify-content: flex-start;" required>
              <option value="">Select</option>
              @foreach ($exchange_trades as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                  style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->exchange_trade) && $auction->get->exchange_trade == $item['name'] ? 'selected' : ''}}>
                  {{ $item['name'] }}
                </option>
              @endforeach
            </select>
            <div class="form-group col-md-12 otherTradeAuction d-none">
              <label class="fw-bold">Acceptable Exchange Item:</label>
              <input type="text" name="otherTrade" value="{{isset($auction->get->otherTrade) ? $auction->get->otherTrade : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
            </div>
          </div>
          <div class="form-group col-md-12">
            <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
            <input type="number" name="estimatedTrade" value="{{isset($auction->get->estimatedTrade) ? $auction->get->estimatedTrade : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
          </div>
          <div class="form-group col-md-12">
            <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is willing to exchange/trade?</label>
            <input type="text" name="specificTrade" value="{{isset($auction->get->specificTrade) ? $auction->get->specificTrade : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
          </div>
          <div class="form-group">
            <label class="fw-bold">How much cash does the seller require on top of the exchange/trade item?</label>
            <input type="number" name="cashTrade" value="{{isset($auction->get->cashTrade) ? $auction->get->cashTrade : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
          </div>
          <div class="form-group col-md-12">
            <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
            <input type="text" name="valueTrade" value="{{isset($auction->get->valueTrade) ? $auction->get->valueTrade : ''}}" class="form-control has-icon"
              data-icon="fa-regular fa-circle-check" required>
          </div>
        </div>
        {{-- Exchangetrade --}}
        <div class="form-group row">
          @php
            $sellerOffer = [
              ['name' => 'Yes', 'target' => '.sellerOfferYesAuction','icon'=>'<i class="fa-regular fa-circle-check"></i>'], 
              ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],
              ['name' => 'Only if the buyer meets the Seller’s Sell Terms, which include the List Price, acceptable Escrow Deposit, number of days the Seller will accept for closing, and acceptable Contingencies, will the seller offer a buyer at closing', 'target' => '.sellerOfferYesAuction','icon'=>'<i class="fa-regular fa-circle-check"></i>']
            ];
          @endphp
        <label class="fw-bold">Is the seller offering a credit to the buyer at closing?  </label>
        <select class="grid-picker" name="sellerOffer" style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($sellerOffer as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='{{$item['icon']}}' {{isset($auction->get->sellerOffer) && $auction->get->sellerOffer == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>  
        <div class="form-group sellerOfferYesAuction d-none">
          <div class="d-flex justify-content-between aalign-items-center">
            <label class="fw-bold">What credit amount is the seller offering to the buyer at closing?</label>
            <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                <button type="button" class="select-btn me-1 active"
                    data-type="amount">$</button>
                <button type="button" class="select-btn" data-type="percent">%</button>
            </div>
          </div>
          <input type="number" name="sellerOfferYes" value="{{isset($auction->get->sellerOfferYes) ? $auction->get->sellerOfferYes : ''}}" placeholder=""
            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
        </div>
      </div>
    </span>
    <span class="traditionalTime">
      <h4>Price and Terms:</h4>
        <div class="form-group ">
          <label class="fw-bold" for="buy_now_price" required>Price:</label>
          <input type="number" name="price" value="{{isset($auction->get->price) ? $auction->get->price : ''}}" placeholder="" id="buy_now_price"
            class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar-sign"
            data-msg-required="Please enter Buy Now Price" required>
        </div>
        <div class="form-group ">
          <label class="fw-bold" for="reserve_price" required>List Price Per Sqft:</label>
          <input type="number" name="price_per_sqfeet" value="{{isset($auction->get->price_per_sqfeet) ? $auction->get->price_per_sqfeet : ''}}" id="reserve_price"
            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
        </div>
        <div class="form-group">
          <div class="d-flex justify-content-between aalign-items-center">
            <label class="fw-bold">Acceptable Escrow Deposit:</label>
            <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                <button type="button" class="select-btn me-1 active"
                    data-type="amount">$</button>
                <button type="button" class="select-btn" data-type="percent">%</button>
            </div>
          </div>
          <input type="number" name="escrow_amount2" value="{{isset($auction->get->escrow_amount2) ? $auction->get->escrow_amount2 : ''}}" id="term_escrow_amount" placeholder=""
            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
        </div>
        <div class="form-group">
          <label class="fw-bold">Number of Days the Seller Will Accept for Closing: </label>
          <input type="number" name="closing_days2"  value="{{isset($auction->get->closing_days2) ? $auction->get->closing_days2 : ''}}" id="closing_days" placeholder=""
            class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
      <div class="form-group">
        @php
          $contigencies = [['name' => 'Inspection contingency', 'target' => '.inspection'], ['name' => 'Appraisal contingency', 'target' => '.appraisal'], ['name' => 'Financing contingency', 'target' => '.financing'], ['name' => 'Sale of a property contingency', 'target' => '.sale'], ['name' => 'None', 'target' => ''],['name' => 'Other', 'target' => '.otherContingency'],];
        @endphp
        <label class="fw-bold">Acceptable Contingencies: </label>
        <select class="grid-picker" name="contigencies_accepted_by_seller[]" id="contigencies_accepted_by_seller"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($contigencies as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->contigencies_accepted_by_seller) && in_array($item['name'], json_decode($auction->get->contigencies_accepted_by_seller) ?? [])  ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group inspection d-none">
          <label class="fw-bold">Inspection contingency (days):</label>
          <input type="number" name="inspection" value="{{isset($auction->get->inspection) ? $auction->get->inspection : ''}}" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
        <div class="form-group appraisal d-none">
          <label class="fw-bold">Appraisal contingency (days):</label>
          <input type="number" name="appraisal" value="{{isset($auction->get->appraisal) ? $auction->get->appraisal : ''}}" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
        <div class="form-group financing d-none">
          <label class="fw-bold">Financing contingency (days):</label>
          <input type="number" name="finance" value="{{isset($auction->get->finance) ? $auction->get->finance : ''}}" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
        <div class="form-group sale d-none">
          <label class="fw-bold"> Sale of a property contingency (days): </label>
          <input type="number" name="saleContingency" value="{{isset($auction->get->saleContingency) ? $auction->get->saleContingency : ''}}" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
        <div class="form-group otherContingency d-none">
          <label class="fw-bold">Acceptable contingency: </label>
          <input type="text" name="acceptable" value="{{isset($auction->get->acceptable) ? $auction->get->acceptable : ''}}" id="closing_days" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>

          <label class="fw-bold">Acceptable contingency (days):</label>
          <input type="number" name="acceptable_days" value="{{isset($auction->get->acceptable_days) ? $auction->get->acceptable_days : ''}}" id="" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
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
        <select class="grid-picker" name="term_financings[]" id="term_financings"
          style="justify-content: flex-start;" multiple required>
          <option value="">Select</option>
          @foreach ($term_financings as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
              data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->term_financings) && in_array($item['name'], json_decode($auction->get->term_financings) ?? [])  ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
        {{-- Other --}}
        <div class="form-group otherFinancing d-none">
            <label class="fw-bold">Acceptable Currency/Financing:</label>
            <input type="text" name="otherFinancing" value="{{isset($auction->get->otherFinancing) ? $auction->get->otherFinancing : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
        </div>
        {{-- Other --}}
        {{-- NFT  --}}
        <div class="form-group nft d-none">
          <div class="form-group col-md-12">
              <label class="fw-bold">What type of Non-Fungible Token (NFT) will the seller accept?</label>
              <input type="text" name="type_of_NFT_accepted" value="{{isset($auction->get->type_of_NFT_accepted) ? $auction->get->type_of_NFT_accepted : ''}}" id="type_of_NFT_accepted" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What percentage of the sales price will the seller accept in the form of a Non-Fungible Token (NFT)?</label>
              <input type="number" name="percentage_in_NFT" value="{{isset($auction->get->percentage_in_NFT) ? $auction->get->percentage_in_NFT : ''}}" id="percentage_in_NFT" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
              <input type="number" name="percentage_in_cash" value="{{isset($auction->get->percentage_in_cash) ? $auction->get->percentage_in_cash : ''}}" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
          </div>
        </div>
      {{-- NFT  --}}
      {{-- Crypto  --}}
      <div class="form-group crypto d-none">
        <div class="form-group col-md-12">
            <label class="fw-bold">What type of cryptocurrency will the seller accept?</label>
            <input type="text" name="cryptocurrency_type" value="{{isset($auction->get->cryptocurrency_type) ? $auction->get->cryptocurrency_type : ''}}" id="cryptocurrency_type" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
        </div>
        <div class="form-group col-md-12">
            <label class="fw-bold">What percentage of the sales price will the seller accept in cryptocurrency?</label>
            <input type="number" name="percentage_in_crypto" value="{{isset($auction->get->percentage_in_crypto) ? $auction->get->percentage_in_crypto : ''}}" id="percentage_in_crypto" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
        </div>
        <div class="form-group col-md-12">
            <label class="fw-bold">What percentage of the sales price will the seller accept in cash?</label>
            <input type="number" name="percentage_in_cash" value="{{isset($auction->get->percentage_in_cash) ? $auction->get->percentage_in_cash : ''}}" id="percentage_in_cash" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
        </div> 
        <small>Note: Cryptocurrency can be converted to cash at closing.</small>             
      </div>
      {{-- Crypto  --}}
      {{-- seller financing --}}
      <div class="form-group row custom_seller_financing d-none">
        <label class="fw-bold">Please enter the seller’s desired seller financing terms:</label>
        <div class="form-group col-md-3">
          <label class="fw-bold">Purchase Price:</label>
          <input type="number" name="purchase_price_seller_financing" value="{{isset($auction->get->purchase_price_seller_financing) ? $auction->get->purchase_price_seller_financing : ''}}" id="purchase_price_seller_financing"
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
          <input type="number" name="down_payment_seller_financing" value="{{isset($auction->get->down_payment_seller_financing) ? $auction->get->down_payment_seller_financing : ''}}" id="down_payment_seller_financing"
            class="form-control has-icon" data-icon="fa-solid fa-percent" required>
        </div>
        <div class="form-group col-md-3">
          <div class="d-flex justify-content-between aalign-items-center">
            <label class="fw-bold">Seller Financing Amount:</label>
            <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                <button type="button" class="select-btn me-1 active"
                    data-type="amount">$</button>
                <button type="button" class="select-btn" data-type="percent">%</button>
            </div>
          </div>
          <input type="number" name="seller_financing_amount" value="{{isset($auction->get->seller_financing_amount) ? $auction->get->seller_financing_amount : ''}}" id="seller_financing_amount"
            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
        </div>
        <div class="form-group col-md-3">
          <label class="fw-bold">Interest Rate:</label>
          <input type="number" name="interest_rate_seller_financing" value="{{isset($auction->get->interest_rate_seller_financing) ? $auction->get->interest_rate_seller_financing : ''}}" id="interest_rate_seller_financing"
            class="form-control has-icon" data-icon="fa-solid fa-percent" required>
        </div>
        <div class="form-group col-md-3">
          <label class="fw-bold">Loan Duration:</label>
          <input type="text" name="term_seller_financing" value="{{isset($auction->get->term_seller_financing) ? $auction->get->term_seller_financing : ''}}" id="term_seller_financing"
            class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
        </div>
        <div class="form-group col-md-3">
          <label class="fw-bold">Monthly Payment with Principal and Interest:</label>
          <input type="number" name="monthly_payment_seller_financing" value="{{isset($auction->get->monthly_payment_seller_financing) ? $auction->get->monthly_payment_seller_financing : ''}}" id="monthly_payment_seller_financing"
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
              <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" 
              style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->ballonPenalty) && $auction->get->ballonPenalty == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
            @endforeach
          </select>
          <div class="form-group  ballonPenaltyYes d-none">
            <label class="fw-bold">What is the prepayment penalty amount? </label>
            <input type="number" name="ballonPenaltyYes" value="{{isset($auction->get->ballonPenaltyYes) ? $auction->get->ballonPenaltyYes : ''}}" id="closing_costs" class="form-control has-icon"
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
                data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->balloonPay) && $auction->get->balloonPay == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
            </option>
            @endforeach
          </select>
            <div class="form-group balloonPayYes d-none">
              <div class="form-group">
                <label class="fw-bold">How much is the balloon payment? </label>
                <input type="number" name="balloonPayment" value="{{isset($auction->get->balloonPayment) ? $auction->get->balloonPayment : ''}}" id="closing_costs" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar-sign" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">When is the balloon payment due? </label>
                <input type="text" name="balloonDue" value="{{isset($auction->get->balloonDue) ? $auction->get->balloonDue : ''}}" id="closing_costs" class="form-control has-icon"
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
              <input type="number" name="desired_offering_price" value="{{isset($auction->get->desired_offering_price) ? $auction->get->desired_offering_price : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What specific terms does the seller propose for the lease option?</label>
              <input name="lease_option_terms" value="{{isset($auction->get->lease_option_terms) ? $auction->get->lease_option_terms : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What is the proposed duration of the lease?</label>
              <input type="text" name="proposed_lease_duration" value="{{isset($auction->get->proposed_lease_duration) ? $auction->get->proposed_lease_duration : ''}}" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What is the monthly payment amount the seller is seeking? </label>
              <input type="number" name="monthly_payment_amount" value="{{isset($auction->get->monthly_payment_amount) ? $auction->get->monthly_payment_amount : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What are the specific conditions or requirements outlined by the seller for the lease option?</label>
              <input name="lease_option_conditions" value="{{isset($auction->get->lease_option_conditions) ? $auction->get->lease_option_conditions : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
          </div>
          <div class="form-group">
            @php
              $sellerFeeOption = [['name' => 'Yes', 'target' => '.sellerFeeOptionYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
            @endphp
            <label class="fw-bold">Does the seller require an option fee? </label>
            <select class="grid-picker" name="sellerFeeOption" style="justify-content: flex-start;" required>
              <option value="">Select</option>
              @foreach ($sellerFeeOption as $item)
                <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                  style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->sellerFeeOption) && $auction->get->sellerFeeOption == $item['name'] ? 'selected' : ''}}>
                  {{ $item['name'] }}
                </option>
              @endforeach
            </select>
            <div class="form-group col-md-12 sellerFeeOptionYes d-none">
              <label class="fw-bold">How much is the option fee? </label>
              <input type="number" name="sellerFeeOptionYes" value="{{isset($auction->get->sellerFeeOptionYes) ? $auction->get->sellerFeeOptionYes : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
          </div>               
        </div>
      </div>
      {{-- Lease Option  --}}
      {{-- Lease Purchase  --}}
        <div class="form-group leasePurchase d-none">
          <div class="form-group col-md-12">
              <label class="fw-bold">What is the seller's desired offering price for a lease purchase?</label>
              <input type="number" name="desired_offering_price_lease_purchase" value="{{isset($auction->get->desired_offering_price_lease_purchase) ? $auction->get->desired_offering_price_lease_purchase : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label>
              <input name="lease_purchase_terms" value="{{isset($auction->get->lease_purchase_terms) ? $auction->get->lease_purchase_terms : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What is the proposed duration of the lease?</label>
              <input type="text" name="proposed_lease_duration_lease_purchase" value="{{isset($auction->get->proposed_lease_duration_lease_purchase) ? $auction->get->proposed_lease_duration_lease_purchase : ''}}" class="form-control has-icon" data-icon="fa-regular fa-calendar-days" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What is the monthly payment amount the seller is seeking?</label>
              <input type="number" name="monthly_payment_amount_lease_purchase" value="{{isset($auction->get->monthly_payment_amount_lease_purchase) ? $auction->get->monthly_payment_amount_lease_purchase : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">What are the specific conditions or requirements outlined by the seller for the lease purchase?</label>
              <input name="lease_purchase_conditions" value="{{isset($auction->get->lease_purchase_conditions) ? $auction->get->lease_purchase_conditions : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                  style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->exchange_trade) && $auction->get->exchange_trade == $item['name'] ? 'selected' : ''}}>
                  {{ $item['name'] }}
                </option>
              @endforeach
            </select>
            <div class="form-group col-md-12 sellerFeePurchaseYes d-none">
              <label class="fw-bold">How much is the option fee?  </label>
              <input type="number" name="sellerFeePurchaseYes" value="{{isset($auction->get->sellerFeePurchaseYes) ? $auction->get->sellerFeePurchaseYes : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
          </div>               
        </div>
      </div>
      {{-- Lease Purchase  --}}
      {{-- Assumable  --}}
        <div class="form-group assumable d-none">
          <div class="form-group col-md-12">
              <label class="fw-bold">What assumable terms are being offered?</label>
              <input type="text" name="assumable_terms_offered" value="{{isset($auction->get->assumable_terms_offered) ? $auction->get->assumable_terms_offered : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
          </div>
          <div class="form-group col-md-12">
              <label class="fw-bold">Are there any restrictions or qualifications for a buyer assuming the existing financing?</label>
              <input type="text" name="restrictions_or_qualifications" value="{{isset($auction->get->restrictions_or_qualifications) ? $auction->get->restrictions_or_qualifications : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
          </div>
          <div class="form-group col-md-12">
            <label class="fw-bold">What is the interest rate of the assumable loan?</label>
            <input type="number" name="assumable_interest" value="{{isset($auction->get->assumable_interest) ? $auction->get->assumable_interest : ''}}" class="form-control has-icon" data-icon="fa-solid fa-percent" required>
          </div>
          <div class="form-group col-md-12">
            <label class="fw-bold">What is the monthly payment, including principal and interest, for the assumable loan?</label>
            <input type="number" name="assumable_monthly_payment" value="{{isset($auction->get->assumable_monthly_payment) ? $auction->get->assumable_monthly_payment : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
          </div>
          <div class="form-group col-md-12">
              @php
              $outstandingBalance = [['name' => 'Yes', 'target' => '.outstandingBalanceYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
            @endphp
            <div class="form-group">
              <div class="form-group">
                <label class="fw-bold">What is the outstanding balance on the existing loan?</label>
                <input type="number" name="assumable_balance_loan" value="{{isset($auction->get->assumable_balance_loan) ? $auction->get->assumable_balance_loan : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
              </div>
              <div class="form-group">
                <div class="d-flex justify-content-between aalign-items-center">
                  <label class="fw-bold">What is the down payment that the buyer would need to pay the seller to bridge the gap between the asking price and the assumable loan balance?</label>
                  <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                      <button type="button" class="select-btn me-1 active"
                          data-type="amount">$</button>
                      <button type="button" class="select-btn" data-type="percent">%</button>
                  </div>
                </div>
                <input type="number" name="loan_balance_down_payment" value="{{isset($auction->get->loan_balance_down_payment) ? $auction->get->loan_balance_down_payment : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
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
                style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>' {{isset($auction->get->exchange_trade) && $auction->get->exchange_trade == $item['name'] ? 'selected' : ''}}>
                {{ $item['name'] }}
              </option>
            @endforeach
          </select>
          <div class="form-group col-md-12 otherTrade d-none">
            <label class="fw-bold">Acceptable Exchange Item:</label>
            <input type="text" name="otherTrade" value="{{isset($auction->get->otherTrade) ? $auction->get->otherTrade : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
          </div>
        </div>
        <div class="form-group col-md-12">
          <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
          <input type="number" name="estimatedTrade" value="{{isset($auction->get->estimatedTrade) ? $auction->get->estimatedTrade : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
        </div>
        <div class="form-group col-md-12">
          <label class="fw-bold">Are there specific criteria or conditions for the type of item the seller is willing to exchange/trade?</label>
          <input type="text" name="specificTrade" value="{{isset($auction->get->specificTrade) ? $auction->get->specificTrade : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
        </div>
        <div class="form-group">
          <label class="fw-bold">How much cash does the seller require on top of the exchange/trade item?</label>
          <input type="number" name="cashTrade" value="{{isset($auction->get->cashTrade) ? $auction->get->cashTrade : ''}}" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
        </div>
        <div class="form-group col-md-12">
          <label class="fw-bold">How is the value of the exchange/trade item determined?</label>
          <input type="text" name="valueTrade" value="{{isset($auction->get->valueTrade) ? $auction->get->valueTrade : ''}}" class="form-control has-icon"
            data-icon="fa-regular fa-circle-check" required>
        </div>
      </div>
      {{-- Exchange/trade --}}
      <div class="form-group row">
          @php
            $sellerOffer = [
              ['name' => 'Yes', 'target' => '.sellerOfferYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'], 
              ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'], 
              ['name' => 'Only if the buyer meets the Seller’s Sell Terms, which include the List Price, acceptable Escrow Deposit, number of days the Seller will accept for closing, and acceptable Contingencies, will the seller offer a buyer at closing', 'target' => '.sellerOfferYes','icon'=>'<i class="fa-regular fa-circle-check"></i>']
            ];
          @endphp
        <label class="fw-bold">Is the seller offering a credit to the buyer at closing?  </label>
        <select class="grid-picker" name="sellerOffer" style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($sellerOffer as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
              style="width:calc(33.3% - 10px);" data-icon='{{$item['icon']}}' {{isset($auction->get->sellerOffer) && $auction->get->sellerOffer == $item['name'] ? 'selected' : ''}}>
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
          <input type="number" name="sellerOfferYes" value="{{isset($auction->get->sellerOfferYes) ? $auction->get->sellerOfferYes : ''}}" placeholder=""
            class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
        </div>
      </div>
    </span>
  </div>
  {{-- Slide 6 --}}
  {{-- Slide 7 --}}
  <div class="wizard-step" data-step="7">
    @php
      $property_types = [
        ['name' => 'Residential Property'], 
        ['name' => 'Income Property'], 
        ['name' => 'Commercial Property'], 
        ['name' => 'Business Opportunity'], 
        ['name' => 'Vacant Land']
      ];
    @endphp
    <div class="form-group">
      <label class="fw-bold">Property Style:</label>
      <select class="grid-picker" name="property_type" id="property_type"
        onchange="changePropertyType(this.value);" required>
        <option value="">Select</option>
        @foreach ($property_types as $row_pt)
          <option value="{{ $row_pt['name'] }}" class="card flex-column" style="width:calc(24% - 10px);"
            data-icon='<i class="fa-solid fa-hotel"></i>' {{isset($auction->get->property_type) && $auction->get->property_type == $row_pt['name'] ? 'selected' : ''}}>
            {{ $row_pt['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group property_style">
      @php
          $propertyStyles = [
              ['name' => 'Agriculture', 'target' => ''],
              ['name' => 'Assembly Building', 'target' => ''],
              ['name' => 'Business', 'target' => ''],
              ['name' => 'Five or More', 'target' => ''],
              ['name' => 'Hotel/Motel', 'target' => ''],
              ['name' => 'Industrial', 'target' => ''],
              ['name' => 'Mixed Use', 'target' => ''],
              ['name' => 'Office', 'target' => ''],
              ['name' => 'Restaurant', 'target' => ''],
              ['name' => 'Retail', 'target' => ''],
              ['name' => 'Warehouse', 'target' => ''],
          ];
      @endphp
      <div class="form-group">
          <label class="fw-bold">Property Style:</label>
          <select class="grid-picker" name="propertyStyles" id="" style="justify-content: flex-start;" required>
              <option value="">Select</option>
              @foreach ($propertyStyles as $item)
                  <option value="{{ $item['name'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);"
                      data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->propertyStyles) && $auction->get->propertyStyles == $item['name'] ? 'selected' : ''}}>
                      {{ $item['name'] }}
                  </option>
              @endforeach
          </select>
      </div>
    </div>
    <div class="form-group">
      <div>
        @php
          $property_items = [
              // Residential Items
              ['target' => '', 'name' => '1/3 Triplex', 'class' => 'residential-length'],
              ['target' => '', 'name' => '1/4 Quadplex', 'class' => 'residential-length'],
              ['target' => '', 'name' => '½ Duplex', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Apartment', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Condominium', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Condo-Hotel', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Dock-Rackominium', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Farm', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Garage Condo', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Manufactured Home- Post 1977', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Mobile Home- Pre 1976', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Modular Home', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Single Family Residence', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Townhouse', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Unimproved', 'class' => 'residential-length'],
              ['target' => '', 'name' => 'Villa', 'class' => 'residential-length'],
              // Income Items
              ['target'=>'','name' => 'Duplex', 'class' => 'income-length'],
              ['target'=>'','name' => 'Five or More', 'class' => 'income-length'],
              ['target'=>'','name' => 'Quadplex', 'class' => 'income-length'],
              ['target'=>'','name' => 'Triplex', 'class' => 'income-length'],                      
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
              ['target' => '', 'name' => 'Aeronautical', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Billboard Site', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Business', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Cattle', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Commercial ', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Farm', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Fishery', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Highway Frontage', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Horses', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Industrial', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Land Fill', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Livestock', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Mixed Use', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Multi family', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Nursery', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Orchard', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Pasture', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Poultry ', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Ranch', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Residential', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Retail', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Row Crops ', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Sod Farm', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Subdivision', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Timber', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Tracts', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Trans/Cell Tower', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Tree Farm', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Unimproved Land', 'class' => 'vacant_land-length'],
              ['target' => '', 'name' => 'Well Field ', 'class' => 'vacant_land-length'],
              ['target'=>'.otherVacant','name' => 'Other', 'class' => 'vacant_land-length'],
          ];
        @endphp
        <label class="fw-bold currentUse">Current Use:</label>
        <label class="fw-bold businessType">Business Type: </label>
        <select name="property_items" id="property_items" class="property_items grid-picker"
          style="justify-content: flex-start;">
          <option value=""></option>
          @foreach ($property_items as $item)
            <option value="{{ $item['name'] }}" data-target="{{$item['target']}}" class="card flex-row {{ $item['class'] }}"
              style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>' {{isset($auction->get->property_items) && $auction->get->property_items == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group otherVacant d-none">
          <label class="fw-bold">Current Use: </label>
          <input type="text" name="otherProperty" value="{{isset($auction->get->otherProperty) ? $auction->get->otherProperty : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
        </div>
        <div class="form-group otherBusiness d-none">
          <label class="fw-bold">Business Type: </label>
          <input type="text" name="otherProperty" value="{{isset($auction->get->otherProperty) ? $auction->get->otherProperty : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
            data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
            style="width:calc(50% - 10px);" {{isset($auction->get->prop_condition) && $auction->get->prop_condition == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
      <div class="form-group otherConditionRes d-none">
        <label class="fw-bold">Property Condition: </label>
        <input type="text" name="otherCondition" value="{{isset($auction->get->otherCondition) ? $auction->get->otherCondition : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
      </div>
    </div>
  </div>
  @php
    $bedrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_bedrooms', 'name' => 'Other']];
    $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.custom_bathrooms', 'name' => 'Other']];
    $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];

  @endphp
  {{-- Residential/income Start --}}
  <div class="wizard-step" data-step="9">
    <div class="form-group">
      <label class="fw-bold">Bedrooms:</label>
      <select class="grid-picker" name="bedrooms" id="bedrooms" style="" required>
        <option value="">Select</option>
        @foreach ($bedrooms as $item)
          <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
            style="width:calc(20% - 10px);" data-icon='<i class="fa-solid fa-bed"></i>' {{isset($auction->get->bedrooms) && $auction->get->bedrooms == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group custom_bedrooms d-none">
      <label class="fw-bold">Bedrooms:</label>
      <input type="number" name="custom_bedrooms" value="{{isset($auction->get->custom_bedrooms) ? $auction->get->custom_bedrooms : ''}}" id="custom_bedrooms" class="form-control has-icon"
        data-icon="fa-solid fa-bed">
    </div>
  </div>
  {{-- Slide 9 --}}
  {{-- Slide 10 --}}
  <div class="wizard-step" data-step="10">
    <div class="form-group">
      <label class="fw-bold">Bathrooms:</label>
      <select class="grid-picker" name="bathrooms" id="bathrooms" style="" required>
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
            style="width:calc(33.3% - 10px);" data-icon='<i class="fa-solid fa-bath"></i>' {{isset($auction->get->bathrooms) && $auction->get->bathrooms == $item['name'] ? 'selected' : ''}}>
            {{ $item['name'] }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="form-group custom_bathroom_residential_and_income d-none">
      <label class="fw-bold">Bathrooms:</label>
      <input type="number" name="custom_bathrooms" value="{{isset($auction->get->custom_bathrooms) ? $auction->get->custom_bathrooms : ''}}" id="custom_bathrooms" class="form-control has-icon"
        data-icon="fa-solid fa-bath">
    </div>
  </div>
  
  <div class="wizard-step" data-step="11">
    @php
      $unitStructure = [
        ['target' => '', 'name' => '1 Bed/1 Bath'], 
        ['target' => '', 'name' => '1 Bedroom'], 
        ['target' => '', 'name' => '2 Bed/1 Bath'], 
        ['target' => '', 'name' => '2 Bed/2 Bath'], 
        ['target' => '', 'name' => '2 Bedroom'], 
        ['target' => '', 'name' => '3 Bed/1 Bath'], 
        ['target' => '', 'name' => '3 Bed/2 Bath'], 
        ['target' => '', 'name' => '3 Bedroom'], 
        ['target' => '', 'name' => '4 Bedroom or more'], 
        ['target' => '', 'name' => '4+ Bed/1 Bath'], 
        ['target' => '', 'name' => '4 Bed/2 Bath'], 
        ['target' => '', 'name' => 'Apartments'], 
        ['target' => '', 'name' => 'Efficiency'], 
        ['target' => '', 'name' => 'Loft'], 
        ['target' => '', 'name' => "Manager's Unit"]
      ];
    @endphp
    <div class="row">
      <div class="form-group">
        @php
          $unitType = isset($auction->get->unit_type_data) && !is_array($auction->get->unit_type_data) ? json_decode($auction->get->unit_type_data, true) : [];
          $unitTypeData = isset($unitType) && !is_array($unitType) ? json_decode($unitType, true) : [];
          $unitTypes = [];
          foreach ($unitTypeData as $unitName => $values) {
              $unitTypes[] = $unitName;
          }
        @endphp
        <label class="fw-bold">Unit Type:</label>
        <select class="grid-picker" name="unit_type[]" id="unit_type_1" style="" multiple required>
          <option value="">Select</option>
          @foreach ($unitStructure as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-column"
              style="width:calc(20% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'  {{isset($unitTypes) && in_array($item['name'], $unitTypes) ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
        <input type="hidden" id="unit_type_input" name="unit_type_data[]" />
        <div id="dynamicFieldsContainer"></div>
      </div>

      <div class="form-group col-md-4">
        <label class="fw-bold">Annual Gross Income</label>
        <input type="number" name="annual_gross_income" value="{{isset($auction->get->annual_gross_income) ? $auction->get->annual_gross_income : ''}}" id="garage_attribute"
           class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Total Monthly Rent</label>
        <input type="number" name="total_monthly_rent" value="{{isset($auction->get->total_monthly_rent) ? $auction->get->total_monthly_rent : ''}}" id="garage_attribute" 
          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Total Monthly Expenses</label>
        <input type="number" name="total_monthly_expenses" value="{{isset($auction->get->total_monthly_expenses) ? $auction->get->total_monthly_expenses : ''}}" id="garage_attribute"
          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
      </div>

      <div class="form-group col-md-4">
        <label class="fw-bold">Annual Net Income</label>
        <input type="number" name="annual_net_income" value="{{isset($auction->get->annual_net_income) ? $auction->get->annual_net_income : ''}}" id="garage_attribute" 
          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Est Annual Market Income</label>
        <input type="number" name="est_annual_market_income" value="{{isset($auction->get->est_annual_market_income) ? $auction->get->est_annual_market_income : ''}}" id="garage_attribute"
          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
      </div>
      <div class="form-group col-md-4">
        <label class="fw-bold">Annual Expenses</label>
        <input type="number" name="annual_expenses" value="{{isset($auction->get->annual_expenses) ? $auction->get->annual_expenses : ''}}" id="garage_attribute"
          class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
      </div>
      @php
        $terms_of_leases = [['name' => 'Gross Lease', 'target' => ''], ['name' => 'Net Lease', 'target' => ''], ['name' => 'Pass Throughs', 'target' => ''], ['name' => 'Purchase Options', 'target' => ''], ['name' => 'Renewal Option', 'target' => ''], ['name' => 'Other', 'target' => '.otherTermLease']];

        $leases_terms = [['name' => 'Month to Month', 'target' => ''], ['name' => '12 Months', 'target' => ''], ['name' => '24 Months', 'target' => ''], ['name' => '3-5 Years', 'target' => ''], ['name' => '6+ Years', 'target' => ''], ['name' => 'Other', 'target' => '.custom_leases_terms']];
        
      @endphp

      <div class="form-group ">
        <label class="fw-bold">Length of Lease:</label>
        <select class="grid-picker" name="length_of_lease[]" id="terms_of_lease"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($leases_terms as $terms_of_lease)
            <option value="{{ $terms_of_lease['name'] }}" data-target="{{ $terms_of_lease['target'] }}"
              class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
              style="width:calc(33.3% - 10px);" {{isset($auction->get->length_of_lease) && in_array($terms_of_lease['name'],  json_decode($auction->get->length_of_lease) ?? []) ? 'selected' : ''}}>
              {{ $terms_of_lease['name'] }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="form-group custom_leases_terms d-none">
        <label class="fw-bold">Length of Lease:</label>
        <input type="text" name="custom_leases_length" value="{{isset($auction->get->custom_leases_length) ? $auction->get->custom_leases_length : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
      </div>

      <div class="form-group">
        <label class="fw-bold">Terms of Lease:</label>
        <select class="grid-picker" name="terms_of_lease" id="terms_of_lease"
          style="justify-content: flex-start;">
          <option value="">Select</option>
          @foreach ($terms_of_leases as $terms_of_lease)
            <option value="{{ $terms_of_lease['name'] }}" data-target="{{ $terms_of_lease['target'] }}"
              class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
              style="width:calc(33.3% - 10px);" {{isset($auction->get->terms_of_lease) && $auction->get->terms_of_lease == $terms_of_lease['name'] ? 'selected' : ''}}>
              {{ $terms_of_lease['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group otherTermLease d-none">
          <label class="fw-bold">Terms of Lease:</label>
          <input type="text" name="otherTermLease" value="{{isset($auction->get->otherTermLease) ? $auction->get->otherTermLease : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
        </div>
      </div>
      @php
        $tenant_pays = [['name' => 'Association Fees', 'target' => ''], ['name' => 'Electricity', 'target' => ''], ['name' => 'Parking Fee', 'target' => ''], ['name' => 'Sewer', 'target' => ''], ['name' => 'Trash Collection', 'target' => ''], ['name' => 'Water', 'target' => ''],['name' => 'Gas', 'target' => ''],['name' => 'Other', 'target' => '.otherTenantPayRes']];
      @endphp
      <div class="form-group">
        <label class="fw-bold">Tenant Pays:</label>
        <select class="grid-picker" name="tenant_pays[]" id="terms_of_lease"
          style="justify-content: flex-start;" multiple>
          <option value="">Select</option>
          @foreach ($tenant_pays as $tenant_pay)
            <option value="{{ $tenant_pay['name'] }}" data-target="{{ $tenant_pay['target'] }}"
              class="card flex-row" data-icon='<i class="fa-regular fa-circle-check"></i>'
              style="width:calc(33.3% - 10px);" {{isset($auction->get->tenant_pays) && in_array($tenant_pay['name'], $auction->get->tenant_pays) ? 'selected' : ''}}>
              {{ $tenant_pay['name'] }}
            </option>
          @endforeach
        </select>
        <div class="form-group otherTenantPayRes d-none">
          <label class="fw-bold">Tenant Pays:</label>
          <input type="text" name="otherTenantPay" value="{{isset($auction->get->otherTenantPay) ? $auction->get->otherTenantPay : ''}}" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
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
              style="width:calc(33.3% - 10px);" {{isset($auction->get->financial_sources) && $auction->get->financial_sources == $financial_source['name'] ? 'selected' : ''}}>
              {{ $financial_source['name'] }}
            </option>
          @endforeach
        </select>
      </div>

      <div class="form-group">
        <label class="fw-bold">Total Number of Units:</label>
        <input type="number" name="total_number_of_units" value="{{isset($auction->get->total_number_of_units) ? $auction->get->total_number_of_units : ''}}" id="total_number_of_units" class="form-control has-icon" data-icon="fa-solid fa-hotel">
      </div>

    </div>
  </div>
  <div class="wizard-step" data-step="12">
    <div class="row ">
      <div class="form-group">
        <label class="fw-bold" for="heated_sqft">Heated Sqft:</label>
        <input type="number" name="heated_sqft" value="{{isset($auction->get->heated_sqft) ? $auction->get->heated_sqft : ''}}" id="heated_sqft" class="form-control has-icon hide_arrow"
          data-icon="fa-solid fa-ruler-combined" required>
      </div>
      <div class="form-group">
        <label class="fw-bold" for="sqft"> Total Sqft:</label>
        <input type="number" name="total_sqft" value="{{isset($auction->get->total_sqft) ? $auction->get->total_sqft : ''}}" id="total_sqft" class="form-control has-icon hide_arrow"
          data-icon="fa-solid fa-ruler-combined" required>
      </div>
      @php
        $heated_sources = [['name' => 'Appraisal', 'target' => ''], ['name' => 'Building', 'target' => ''], ['name' => 'Measured', 'target' => ''], ['name' => 'Owner Provided', 'target' => ''], ['name' => 'Public Records', 'target' => '']];
      @endphp
      <div class="form-group">
        <label class="fw-bold"> Sqft Heated Source:</label>
        <select class="grid-picker" name="heated_source" id="heated_sources"
          style="justify-content: flex-start;" required>
          <option value="">Select</option>
          @foreach ($heated_sources as $item)
            <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
              data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-column"
              style="width:calc(33.3% - 10px);" {{isset($auction->get->heated_source) && $auction->get->heated_source == $item['name'] ? 'selected' : ''}}>
              {{ $item['name'] }}
            </option>
          @endforeach
        </select>
      </div>
    </div>
  </div>