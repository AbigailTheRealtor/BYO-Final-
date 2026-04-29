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
        <form class="p-4 pt-0 mainform" action="{{ route('landlord.save.counter-bid', $bid->id) }}" method="POST"
          enctype="multipart/form-data">
          @csrf
          @php
            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
          @endphp
          <div class="wizard-step" data-step="8">
            <div class="form-group">
              <label class="fw-bold" for="offered_price">Offered Price:</label>
              <input type="text" id="offered_price" name="offered_price" class="form-control has-icon" data-icon="fa-regular fa-circle-check"
                required>
            </div>
            @php
              $lease_terms = [['name' => '3 months', 'target' => ''], ['name' => '6 months', 'target' => ''], ['name' => '9 months', 'target' => ''], ['name' => '1 year', 'target' => ''], ['name' => '2 years', 'target' => ''], ['name' => '3-5 years', 'target' => ''], ['name' => '5+ years', 'target' => ''], ['name' => 'Month to Month', 'target' => ''], ['name' => 'Other', 'target' => '.otherLease']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Offered Lease Length: </label>
              <select class="grid-picker" name="lease_terms[]" id="mySelect" style="justify-content: flex-start;"
                required multiple>
                <option value="">Select</option>
                @foreach ($lease_terms as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(25% - 10px);" data-icon='<i class="fa-regular fa-calendar-days"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group otherLease d-none">
                <label class="fw-bold" for="custom_terms">Offered Lease Length:</label>
                <input type="text" name="price" class="form-control has-icon" data-icon="fa-regular fa-calendar-days"
                  required>
              </div>
            </div>
            <div class="form-group" >
              <div class="form-group">
                <label class="fw-bold">Offered Lease Start Date:</label>
                <input type="date" name="start_date" id="start_date" class="form-control has-icon"
                  data-icon="fa-regular fa-calendar-days">
              </div>
            </div>
            <div class="form-group" >
              <div class="form-group">
                <label class="fw-bold">Days Until Lease Start Date:</label>
                <input type="number" name="days_until_start_date" id="days_until_start_date" class="form-control has-icon"
                  data-icon="fa-regular fa-calendar-days">
              </div>
            </div>
            <div class="form-group">
              <label class="fw-bold" for="custom_terms">How many people will be occupying the property? </label>
              <input type="number" name="occupants" class="form-control has-icon" data-icon="fa-solid fa-users"
                required>
            </div>
            <div class="form-group">
              @php
                $tenantPet = [['name' => 'Yes', 'target' => '.petYes', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <div class="form-group">
                <label class="fw-bold" for="custom_terms">Does the tenant have a pet? </label>
                <select class="grid-picker" name="petOpt" id="mySelect" style="justify-content: flex-start;"
                  required>
                  <option value="">Select</option>
                  @foreach ($tenantPet as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group petYes d-none">
                  <div class="form-group">
                    <label class="fw-bold"> How many pets does the tenant have?</label>
                    <input type="text" name="pets" class="form-control has-icon" data-icon="fa-solid fa-dog"
                      required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">What type of pet(s) does the tenant have?</label>
                    <input type="text" name="petTypes" class="form-control has-icon" data-icon="fa-solid fa-dog"
                      required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">What breed(s) are the pet(s)?</label>
                    <input type="text" name="petBreed" class="form-control has-icon" data-icon="fa-solid fa-dog"
                      required>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">What is the weight of the pet(s)? </label>
                    <input type="text" name="petWeight" class="form-control has-icon"
                      data-icon="fa-solid fa-dog" required>
                  </div>
                </div>
              </div>
            </div>
            <div class="form-group">
              @php 
                $creditScores = [
                  ["name" => "Poor", "target" => ""],
                  ["name" => "Fair", "target" => ""],
                  ["name" => "Good", "target" => ""],
                  ["name" => "Very Good", "target" => ""],
                  ["name" => "Excellent", "target" => ""]
                ];  
              @endphp
              <label class="fw-bold" for="">What is the tenant’s credit score rating? </label>
              <select class="grid-picker" name="scoreRating" id="mySelect" style="justify-content: flex-start;"
              required>
                <option value="">Select</option>
                @foreach ($creditScores as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label class="fw-bold" for="">What is the household monthly net income of the tenant? </label>
              <input type="text" name="monthlyIncome" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
            </div>
            <div class="form-group">
              @php 
                $evictions = [
                  ["name" => "Yes", "target" => ".evictionsYes",'icon'=>'fa-regular fa-circle-check'],
                  ["name" => "No", "target" => "",'icon'=>'fa-regular fa-circle-xmark'],
                ];  
              @endphp
              <label class="fw-bold" for="">Has the tenant had any prior evictions within the last 7 years?
              </label>
              <select class="grid-picker" name="evictions" id="mySelect" style="justify-content: flex-start;"
              required>
                <option value="">Select</option>
                @foreach ($evictions as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{$item['icon']}}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group evictionsYes d-none">
                <label class="fw-bold" for="">Explain when and what for:</label>
                <input type="text" name="evictionsYes" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
            <div class="form-group">
              @php 
                $convicted = [
                  ["name" => "Yes", "target" => ".convictedYes",'icon'=>'fa-regular fa-circle-check'],
                  ["name" => "No", "target" => "",'icon'=>'fa-regular fa-circle-xmark'],
                ];  
              @endphp
              <label class="fw-bold" for="">Has the tenant been convicted of a felony within the last 7 years?
              </label>
              <select class="grid-picker" name="convicted" id="mySelect" style="justify-content: flex-start;"
              required>
                <option value="">Select</option>
                @foreach ($convicted as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{$item['icon']}}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group convictedYes d-none">
                <label class="fw-bold" for="">Explain when and what for:</label>
                <input type="text" name="convictedYes" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
            <div class="form-group">
              @php 
                $violations = [
                  ["name" => "Yes", "target" => ".violationsYes",'icon'=>'fa-regular fa-circle-check'],
                  ["name" => "No", "target" => "",'icon'=>'fa-regular fa-circle-xmark'],
                ];  
              @endphp
              <label class="fw-bold" for="">Has the tenant been involved in any prior lease violations?</label>
              <select class="grid-picker" name="violations" id="mySelect" style="justify-content: flex-start;"
              required>
                <option value="">Select</option>
                @foreach ($violations as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{$item['icon']}}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group violationsYes d-none">
                <label class="fw-bold" for="">Explain when and what for:</label>
                <input type="text" name="violationsYes" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
            <div class="form-group">
              @php 
                $outstanding = [
                  ["name" => "Yes", "target" => ".outstandingYes",'icon'=>'fa-regular fa-circle-check'],
                  ["name" => "No", "target" => "",'icon'=>'fa-regular fa-circle-xmark'],
                ];  
              @endphp
              <label class="fw-bold" for="">Does the tenant have any outstanding balances with previous landlords?</label>
              <select class="grid-picker" name="outstanding" id="mySelect" style="justify-content: flex-start;"
              required>
                <option value="">Select</option>
                @foreach ($outstanding as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{$item['icon']}}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group outstandingYes d-none">
                <label class="fw-bold" for="">Explain when, why, and how much the tenant owes any previous landlords:</label>
                <input type="text" name="outstandingYes" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>

            <div class="form-group">
              @php 
                $tenantRepresented = [
                  ["name" => "Yes", "target" => ".representedYes",'icon'=>'fa-regular fa-circle-check'],
                  ["name" => "No", "target" => "",'icon'=>'fa-regular fa-circle-xmark'],
                ];  
              @endphp
              <label class="fw-bold" for="">Is the tenant represented by a real estate agent?</label>
              <select class="grid-picker" name="tenant_represented" id="represented" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($tenantRepresented as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{$item['icon']}}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group representedYes d-none">
                @php 
                $agent_accept_compensation = [
                  ["name" => "Yes", "target" => "",'icon'=>'fa-regular fa-circle-check'],
                  ["name" => "No", "target" => "",'icon'=>'fa-regular fa-circle-xmark'],
                  ["name" => "Negotiable", "target" => "",'icon'=>'fa-regular fa-circle-check'],
                  ["name" => "No compensation was offered", "target" => "",'icon'=>'fa-regular fa-circle-xmark'],
                ];  
                @endphp
                <label class="fw-bold" for="">Will the tenant's agent accept the compensation currently offered?</label>
                <select class="grid-picker" name="agent_accept_compensation" id="agent_accept_compensation" style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($agent_accept_compensation as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon='<i class="{{$item['icon']}}"></i>'>
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>

                <div class="form-group agentCompensation d-none">
                  @php 
                  $tenant_requests_commission = [
                    ["name" => "Yes", "target" => ".tenantRequests",'icon'=>'fa-regular fa-circle-check'],
                    ["name" => "No", "target" => "",'icon'=>'fa-regular fa-circle-xmark'],
                  ];  
                  @endphp
                  <label class="fw-bold" for="">Does the tenant request that the landlord pay the tenant's agent's commission?</label>
                  <select class="grid-picker" name="tenant_requests_commission" id="tenant_requests_commission" style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($tenant_requests_commission as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{$item['icon']}}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>

                  <div class="form-group tenantRequests d-none">
                    @php 
                    $tenant_requests_commission_amount = [
                      ["name" => "1 month's rent", "target" => "",'icon'=>'fa-regular fa-circle-check'],
                      ["name" => "50% of one month's rent", "target" => "",'icon'=>'fa-regular fa-circle-check'],
                      ["name" => "10% of the gross value of the lease", "target" => "",'icon'=>'fa-regular fa-circle-check'],
                      ["name" => "6% of the gross value of the lease", "target" => "",'icon'=>'fa-regular fa-circle-check'],
                      ["name" => "Negotiable", "target" => "",'icon'=>'fa-regular fa-circle-check'],
                      ["name" => "Other", "target" => ".tenantRequestsAmountOther",'icon'=>'fa-regular fa-circle-check'],
                    ];  
                    @endphp
                    <label class="fw-bold" for="">How much is the tenant requesting that the landlord pay in commission to the tenant's real estate agent?</label>
                    <select class="grid-picker" name="tenant_requests_commission_amount" id="tenant_requests_commission_amount" style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($tenant_requests_commission_amount as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='<i class="{{$item['icon']}}"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group tenantRequestsAmountOther d-none">
                      <label class="fw-bold" for="">How much is the tenant requesting that the landlord pay in commission to the tenant's real estate agent?</label>
                      <input type="text" name="tenant_requests_commission_amount_other" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                    </div>
                  </div>
                </div>
              </div>
            </div>

            @if ($auction->get->auction_type == 'Traditional Listing')
              <div class="form-group">
                <label class="fw-bold" for="">When will the offer expire?</label>
                 <input type="datetime-local" name="offer_expiry" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
              </div>
            @endif

            @if ($auction->get->auction_type == 'Auction Listing')
              @php 
              $escalation_clause = [
                ["name" => "Yes", "target" => ".escalationClauseYes", 'icon'=>'fa-regular fa-circle-check'],
                ["name" => "No", "target" => "", 'icon'=>'fa-regular fa-circle-xmark'],
              ];  
              @endphp
              <label class="fw-bold mt-4" for="">Would the tenant like to set an escalation clause to automatically increase their
                bid price and terms up to a maximum amount specified by the tenant in the event of
                multiple offers?</label>
              <select class="grid-picker" name="escalation_clause" id="escalation_clause" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($escalation_clause as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{$item['icon']}}"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>

              <div class="form-group escalationClauseYes d-none">
                <div class="form-group">
                  <label class="fw-bold" for="">Autobid Price:</label>
                  <input type="number" name="autobid_price" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                  <span>(The Autobid price will increase by $50 over the highest offer, up to the Autobid price.)</span>
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="">Autobid Days Until the Lease Start Date:</label>
                  <input type="number" name="autobid_days_start_date" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                  <span>(The Autobid days until the lease start date will decrease by 5 days per bid over the lowest bid until it reaches the limit set by the tenant.)</span>
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="">Autobid Lease Length in Months:</label>
                  <input type="number" name="autobid_lease_length" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                  <span>(The Autobid lease length in months will increase by 1 month per bid over the highest bid until it reaches the limit set by the tenant.)</span>
                </div>
              </div>
            @endif

            <div class="form-group">
              <label class="fw-bold" for="">Please provide any additional information that the tenant would like to include.</label>
              <textarea type="text" name="additionalInfo" class="form-control has-icon" data-icon="fa-regular fa-circle-check" rows="8">{{ old('additionalInfo') }}</textarea>
            </div>
          </div>
          <div class="wizard-step">
            <div class="row">
              <div class="form-group col-6">
                <label class="fw-bold" for="first_name">First Name:</label>
                <input type="text" name="first_name" placeholder="John" id="first_name"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user"
                  value="{{ Auth::user()->first_name }}">
              </div>
              <div class="form-group col-6">
                <label class="fw-bold" for="last_name">Last Name:</label>
                <input type="text" name="last_name" placeholder="Smith" id="last_name"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user"
                  value="{{ Auth::user()->last_name }}">
              </div>
            </div>
            <div class="row">
              <div class="form-group col-6">
                <label class="fw-bold" for="agent_phone">Phone Number:</label>
                <input type="text" name="agent_phone" placeholder="" id="agent_phone"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-phone"
                  value="{{ Auth::user()->phone }}">
              </div>
              <div class="form-group col-6">
                <label class="fw-bold" for="agent_email">Email:</label>
                <input type="email" name="agent_email" placeholder="john@example.com" id="agent_email"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-envelope"
                  value="{{ Auth::user()->email }}">
              </div>
            </div>
            @if (auth()->user()->user_type == 'agent')
              <div class="row">
                <div class="form-group col-6">
                  <label class="fw-bold" for="agent_brokerage">Brokerage:</label>
                  <input type="text" name="agent_brokerage" placeholder="" id="agent_brokerage"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-handshake"
                    value="{{ Auth::user()->brokerage }}">
                </div>
                <div class="form-group col-6">
                  <label class="fw-bold" for="agent_license_no">Real Estate License #:</label>
                  <input type="text" name="agent_license_no" placeholder="" id="agent_license_no"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-card"
                    value="{{ Auth::user()->license_no }}">
                </div>
              </div>
              <div class="row">
                <div class="form-group col-6">
                  <label class="fw-bold" for="agent_mls_id">NAR Member ID (NRDS ID): </label>
                  <input type="text" name="agent_mls_id" placeholder="" id="agent_mls_id"
                    class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-card-clip"
                    value="{{ Auth::user()->mls_id }}">
                </div>
              </div>
            @endif
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
    // Nisar Changing
    Filevalidation = () => {

      var txt = "";
      const fi = document.getElementById('file');
      // Check if any file is selected.
      if (fi.files.length > 0) {
        for (const i = 0; i <= fi.files.length - 1; i++) {

          const fsize = fi.files.item(i).size;
          const file = Math.round((fsize / 1024));
          // The size of the file.
          if (file >= 102400) {
            // alert(
            //     "File too Big, please select a file less than 2mb");
            txt = "File too Big, please select a file less than 100mb";
            document.getElementById("demo").innerHTML = txt;
            // var myText = "File too Big, please select a file less than 2mb";
          } else if (file < 102400) {
            txt = "";
            document.getElementById("demo").innerHTML = txt;
          } else {
            document.getElementById('size').innerHTML = '<b>' +
              file + '</b> KB';
          }
        }
      }
    }
    // Nisar Changing End
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
    $(document).on('change', '#has_water_view', function() {

      var selectedOptionWater = $(this).val();
      if (selectedOptionWater == 'No') {
        $('#water_show').hide();
        $('#water_extras_show').hide();
      }
      if (selectedOptionWater == 'Yes') {
        $('#water_show').show();
        $('#water_extras_show').show();
      }

    });
    //water view changing by waqas
    function checkselect(elm) {
      var i = $(elm).data('index');
      var mult = $(elm).parent().children('select').attr('multiple') || false;
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
          errorClass: "text-error text-danger",
          onkeyup: false,
          onfocusout: false,
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
    var leaseRes = $('#leaseTermOptions');
    $('#mySelect').change(function() {
      //Disply and hide a div
      ($(this).val().includes('3 months') || $(this).val().includes('6 months') || $(this).val().includes(
          '9 months') || $(this).val().includes('1 year') || $(this).val().includes('2 years') || $(this).val()
        .includes('3-5 years') || $(this).val().includes('5+ years') || $(this).val().includes('Month to Month') || $(
          this).val().includes('Other')) ? leaseRes.show():
        leaseRes.hide()
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

    var feeReqOptDiv = $('#feeReq');
    $('#feeReqOption').change(function() {
      //Disply and hide a div
      ($(this).val().includes('Required') || $(this).val().includes('Optional')) ? feeReqOptDiv.show():
        feeReqOptDiv.hide()
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

    function add_room_dimension() {
      var roomTemp = $('.roomDimensionTemp').html();
      $('.roomBtn').before(roomTemp);
    }

    function roomFtn() {
      if ($('#room_typeRes').val() !== '') {
        $('.roomDet').each(function() {
          $(this).show();
        });
      } else {
        $('.roomDet').each(function() {
          $(this).hide();
        });
      }

    }
    roomFtn();

    $('#agent_accept_compensation').change(function(){
      if($(this).val() == 'Negotiable' || $(this).val() == 'No compensation was offered' || $(this).val() == 'No'){
        $('.agentCompensation').removeClass('d-none');
      }else{
        $('.agentCompensation').addClass('d-none');
      }
    })
  </script>
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush
