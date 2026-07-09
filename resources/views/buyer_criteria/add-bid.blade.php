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

    /* ── Finish / Save button — explicit states ──────────────────────────────── */
    .wizard-step-finish {
      background-color: #0d6efd !important;
      border-color: #0d6efd !important;
      color: #fff !important;
      font-weight: 600;
      min-width: 160px;
      transition: background-color .15s ease-in-out, box-shadow .15s ease-in-out;
    }
    .wizard-step-finish:hover,
    .wizard-step-finish:focus {
      background-color: #0b5ed7 !important;
      border-color: #0a58ca !important;
      box-shadow: 0 0 0 .2rem rgba(13,110,253,.3) !important;
    }
    .wizard-step-finish:disabled,
    .wizard-step-finish.disabled {
      background-color: #6ea8fe !important;
      border-color: #6ea8fe !important;
      opacity: .65;
      cursor: not-allowed;
    }
    @media (max-width: 576px) {
      .wizard-step-finish {
        width: 100%;
        font-size: 1.05rem;
        padding: .65rem 1rem;
      }
    }

    /* ── Criteria match badges ───────────────────────────────────────────────── */
    .criteria-match-badge {
      display: inline-block;
      font-size: .7rem;
      font-weight: 700;
      padding: .1rem .45rem;
      border-radius: 999px;
      margin-left: .35rem;
      vertical-align: middle;
      white-space: nowrap;
    }
    .criteria-match-badge.match  { background:#d1fae5; color:#065f46; }
    .criteria-match-badge.no-match { background:#fee2e2; color:#991b1b; }
    .criteria-match-badge.pending  { background:#f3f4f6; color:#6b7280; }

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

    {{-- Buyer Criteria Checklist (read-only reference panel) --}}
    <div class="row mb-4 g-3">
      <div class="col-lg-8">
        <div class="card border-primary shadow-sm h-100">
          <div class="card-header bg-primary text-white d-flex align-items-center py-2">
            <i class="fa-solid fa-list-check me-2"></i>
            <strong>Buyer Criteria Checklist</strong>
            <span class="ms-2 badge bg-white text-primary small">Reference — what the buyer is looking for</span>
          </div>
          <div class="card-body py-3">
            <p class="small text-muted mb-2"><i class="fa-solid fa-circle-info me-1"></i>Green badge = your bid matches this criterion. Red = mismatch. Grey = field not yet entered.</p>
            <div class="row row-cols-2 row-cols-md-3 g-2 small" id="criteria-checklist-grid">
              @if($auction->address)
              <div class="col"><span class="text-muted">Listed Address:</span> <strong>{{ $auction->address }}</strong></div>
              @endif

              @if($auction->get->property_type ?? null)
              <div class="col criteria-row"
                   data-field="property_type"
                   data-form-field="property_type"
                   data-match-value="{{ $auction->get->property_type }}">
                <span class="text-muted">Property Type:</span>
                <strong>{{ $auction->get->property_type }}</strong>
                <span class="criteria-match-badge pending js-criteria-badge">–</span>
              </div>
              @endif

              @if($auction->get->maximum_budget ?? null)
              <div class="col criteria-row"
                   data-field="maximum_budget"
                   data-form-field="price"
                   data-match-value="{{ $auction->get->maximum_budget }}"
                   data-match-type="lte">
                <span class="text-muted">Max Budget:</span>
                <strong>${{ number_format($auction->get->maximum_budget) }}</strong>
                <span class="criteria-match-badge pending js-criteria-badge">–</span>
              </div>
              @endif

              @if($auction->get->bedrooms ?? null)
              <div class="col criteria-row"
                   data-field="bedrooms"
                   data-form-field="bedrooms"
                   data-match-value="{{ $auction->get->bedrooms }}"
                   data-match-type="gte">
                <span class="text-muted">Bedrooms:</span>
                <strong>{{ $auction->get->bedrooms }}</strong>
                <span class="criteria-match-badge pending js-criteria-badge">–</span>
              </div>
              @endif

              @if($auction->get->bathrooms ?? null)
              <div class="col criteria-row"
                   data-field="bathrooms"
                   data-form-field="bathrooms"
                   data-match-value="{{ $auction->get->bathrooms }}"
                   data-match-type="gte">
                <span class="text-muted">Bathrooms:</span>
                <strong>{{ $auction->get->bathrooms }}</strong>
                <span class="criteria-match-badge pending js-criteria-badge">–</span>
              </div>
              @endif

              @if($auction->get->heated_sqft ?? null)
              <div class="col criteria-row"
                   data-field="heated_sqft"
                   data-form-field="heated_sqft"
                   data-match-value="{{ $auction->get->heated_sqft }}"
                   data-match-type="gte">
                <span class="text-muted">Sq Ft:</span>
                <strong>{{ $auction->get->heated_sqft }}</strong>
                <span class="criteria-match-badge pending js-criteria-badge">–</span>
              </div>
              @endif

              @if($auction->get->preferred_location ?? null)
              <div class="col"><span class="text-muted">Location Pref:</span> <strong>{{ $auction->get->preferred_location }}</strong></div>
              @endif

              @if($auction->get->city ?? null)
              <div class="col criteria-row"
                   data-field="city"
                   data-form-field="city"
                   data-match-value="{{ $auction->get->city }}">
                <span class="text-muted">City:</span>
                <strong>{{ $auction->get->city }}</strong>
                <span class="criteria-match-badge pending js-criteria-badge">–</span>
              </div>
              @endif

              @if($auction->get->county ?? null)
              <div class="col criteria-row"
                   data-field="county"
                   data-form-field="county"
                   data-match-value="{{ $auction->get->county }}">
                <span class="text-muted">County:</span>
                <strong>{{ $auction->get->county }}</strong>
                <span class="criteria-match-badge pending js-criteria-badge">–</span>
              </div>
              @endif

              @if($auction->get->state ?? null)
              <div class="col criteria-row"
                   data-field="state"
                   data-form-field="state"
                   data-match-value="{{ $auction->get->state }}">
                <span class="text-muted">State:</span>
                <strong>{{ $auction->get->state }}</strong>
                <span class="criteria-match-badge pending js-criteria-badge">–</span>
              </div>
              @endif

              @if($auction->get->desired_move_in_date ?? null)
              <div class="col"><span class="text-muted">Move-in:</span> <strong>{{ $auction->get->desired_move_in_date }}</strong></div>
              @endif

              @if($auction->get->term_financings ?? null)
              <div class="col criteria-row"
                   data-field="term_financings"
                   data-form-field="term_financings"
                   data-match-value="{{ is_array($auction->get->term_financings) ? implode(',', $auction->get->term_financings) : $auction->get->term_financings }}">
                <span class="text-muted">Financing:</span>
                <strong>{{ is_array($auction->get->term_financings) ? implode(', ', $auction->get->term_financings) : $auction->get->term_financings }}</strong>
                <span class="criteria-match-badge pending js-criteria-badge">–</span>
              </div>
              @endif

              @if($auction->get->contingencies ?? null)
              <div class="col"><span class="text-muted">Contingencies:</span> <strong>{{ $auction->get->contingencies }}</strong></div>
              @endif

              @if($auction->get->additional_details ?? null)
              <div class="col-12 mt-1"><span class="text-muted">Buyer Notes:</span> {{ $auction->get->additional_details }}</div>
              @endif
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card border-info shadow-sm h-100">
          <div class="card-header bg-info text-white d-flex align-items-center py-2">
            <i class="fa-solid fa-location-dot me-2"></i>
            <strong>Location DNA Compatibility</strong>
          </div>
          <div class="card-body py-3">
            @php
              $dnaPrefs    = ($locationDna && is_array($locationDna->summary_json)) ? $locationDna->summary_json : [];
              $dnaCities   = $dnaPrefs['cities']          ?? [];
              $dnaZips     = $dnaPrefs['zip_codes']       ?? [];
              $dnaNeigh    = $dnaPrefs['neighborhoods']   ?? [];
              $dnaRadii    = $dnaPrefs['radius_searches'] ?? [];
              $dnaPolygons = $dnaPrefs['polygons']        ?? [];
              $dnaFlex     = $dnaPrefs['flexible_location'] ?? false;
              $dnaNotes    = $dnaPrefs['location_notes']  ?? '';
              $hasPrefs    = $locationDna && (count($dnaCities) || count($dnaZips) || count($dnaNeigh) || count($dnaRadii) || count($dnaPolygons) || $dnaNotes);
            @endphp
            @if(!$locationDna || (!$hasPrefs && $locationDna->geocode_status !== 'geocoded'))
              <p class="small text-muted mb-0"><i class="fa-solid fa-circle-info me-1 text-muted"></i>No Location DNA preferences were provided.</p>
            @else
              {{-- Source / geocoded reference location --}}
              @if($locationDna->source_city || $locationDna->source_state)
                <p class="small text-muted mb-1">Buyer's reference location:</p>
                <p class="mb-2 small fw-semibold"><i class="fa-solid fa-map-pin me-1 text-info"></i>
                  {{ implode(', ', array_filter([$locationDna->source_city, $locationDna->source_county, $locationDna->source_state, $locationDna->source_zip])) }}
                  @if($locationDna->geocode_status !== 'geocoded')
                    <span class="badge bg-warning text-dark ms-1 small" style="font-size:.65rem;">Pending geocode</span>
                  @endif
                </p>
              @endif

              {{-- Flexible location flag --}}
              <p class="small mb-2">
                <span class="fw-bold text-muted">Flexible on location:</span>
                @if($dnaFlex)
                  <span class="badge bg-success ms-1 small">Yes</span>
                @else
                  <span class="badge bg-secondary ms-1 small">No</span>
                @endif
              </p>

              {{-- Cities --}}
              @if(count($dnaCities))
                <p class="small fw-bold text-muted mb-1">Preferred Cities:</p>
                <div class="d-flex flex-wrap gap-1 mb-2">
                  @foreach($dnaCities as $c)
                    <span class="badge bg-primary bg-opacity-75 small">{{ $c }}</span>
                  @endforeach
                </div>
              @endif

              {{-- ZIP Codes --}}
              @if(count($dnaZips))
                <p class="small fw-bold text-muted mb-1">ZIP Codes:</p>
                <div class="d-flex flex-wrap gap-1 mb-2">
                  @foreach($dnaZips as $z)
                    <span class="badge bg-secondary bg-opacity-75 small">{{ $z }}</span>
                  @endforeach
                </div>
              @endif

              {{-- Neighborhoods --}}
              @if(count($dnaNeigh))
                <p class="small fw-bold text-muted mb-1">Neighborhoods:</p>
                <div class="d-flex flex-wrap gap-1 mb-2">
                  @foreach($dnaNeigh as $n)
                    <span class="badge bg-info text-dark small">{{ $n }}</span>
                  @endforeach
                </div>
              @endif

              {{-- Radius searches --}}
              @if(count($dnaRadii))
                <p class="small fw-bold text-muted mb-1">Radius Zones:</p>
                <ul class="list-unstyled mb-2 small">
                  @foreach($dnaRadii as $r)
                    <li><i class="fa-solid fa-circle-dot me-1 text-info" style="font-size:.7rem;"></i>
                      {{ $r['label'] ?? 'Zone' }} — {{ $r['radius_miles'] ?? '?' }} mi radius
                    </li>
                  @endforeach
                </ul>
              @endif

              {{-- Polygon searches --}}
              @if(count($dnaPolygons))
                <p class="small fw-bold text-muted mb-1">Custom Area Polygons:</p>
                <ul class="list-unstyled mb-2 small">
                  @foreach($dnaPolygons as $p)
                    <li><i class="fa-solid fa-draw-polygon me-1 text-secondary" style="font-size:.7rem;"></i>{{ $p['label'] ?? 'Custom area' }}</li>
                  @endforeach
                </ul>
              @endif

              {{-- Location notes --}}
              @if($dnaNotes)
                <hr class="my-2">
                <p class="small fw-bold text-muted mb-1">Location Notes:</p>
                <p class="small fst-italic">{{ $dnaNotes }}</p>
              @endif

              {{-- Lifestyle signals --}}
              @if($locationDna->lifestyle_json && count($locationDna->lifestyle_json))
                <hr class="my-2">
                <p class="small text-muted mb-1">Lifestyle signals:</p>
                <div class="d-flex flex-wrap gap-1">
                  @foreach(array_slice($locationDna->lifestyle_json, 0, 6) as $key => $val)
                    <span class="badge bg-light text-dark border small">{{ ucwords(str_replace('_', ' ', $key)) }}: {{ $val }}</span>
                  @endforeach
                </div>
              @endif
            @endif
          </div>
        </div>
      </div>
    </div>

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
            $yes_or_nos = [['name' => 'Yes', 'target' => '.property_link', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
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
                <div class="form-group property_link d-none">
                  <label class="fw-bold" for="address">Please provide the link to the property listing:</label>
                  <input type="text" name="property_link" data-type="property_link" placeholder="" id="property_link"
                    class="form-control has-icon search_places" data-icon="fa-solid fa-link" required>
                </div>
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
            {{-- property_listed="No" --}}
            <div class="wizard-step" data-step="3">
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
                      data-icon='<i class="fa-regular fa-circle-check"></i>'>
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
                        data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="4">
              <h4>Price and Terms:</h4>
              <div class="form-group ">
                <label class="fw-bold" for="buy_now_price" required>Price:</label>
                <input type="number"  name="price" placeholder="" id="buy_now_price"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar-sign"
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
                      data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
                {{-- Other --}}
                <div class="form-group otherFinancing d-none">
                    <label class="fw-bold">Acceptable Currency/Financing:</label>
                    <input type="text" name="otherFinancing"  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                </div>
                {{-- Other --}}
                {{-- NFT  --}}
                <div class="form-group nft d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What type of Non-Fungible Token (NFT) will the seller accept?</label>
                      <input type="text" name="type_of_NFT_accepted" id="type_of_NFT_accepted" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                    <input type="text" name="cryptocurrency_type" id="cryptocurrency_type" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                      <input name="lease_option_terms" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                      <input name="lease_option_conditions" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                      <input type="number" name="desired_offering_price_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label>
                      <input name="lease_purchase_terms" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                      <input name="lease_purchase_conditions" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                <div class="form-group assumable d-none" x-data="{ showDetails: false }">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">Interested in Assumable Financing?</label>
                      <select name="assumable_interest" class="form-control has-icon" data-icon="fa-solid fa-circle-question" required @change="showDetails = ($event.target.value === 'Yes')">
                          <option value="">Select</option>
                          <option value="Yes">Yes</option>
                          <option value="No">No</option>
                      </select>
                  </div>
                  <div class="form-group col-md-12" x-show="showDetails">
                      <label class="fw-bold">Maximum Interest Rate You Would Accept: <small class="text-muted">(Optional)</small></label>
                      <div class="input-group">
                          <input type="text" name="assumable_max_interest_rate" class="form-control has-icon" data-icon="fa-solid fa-percent" placeholder="e.g., 5">
                          <span class="input-group-text">%</span>
                      </div>
                  </div>
                  <div class="form-group col-md-12" x-show="showDetails">
                      <label class="fw-bold">Maximum Monthly Payment (P&amp;I) You Would Accept: <small class="text-muted">(Optional)</small></label>
                      <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input type="text" name="assumable_max_monthly_payment" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="e.g., 2000">
                      </div>
                  </div>
                  <div class="form-group col-md-12" x-show="showDetails">
                      <label class="fw-bold">Cash Available to Bridge the Gap: <small class="text-muted">(Optional)</small></label>
                      <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input type="text" name="assumable_bridge_gap_cash" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="e.g., 50000">
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
                    <input type="text" name="otherTrade" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                  </div>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                  <input type="text" name="estimatedTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
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
                    data-icon="fa-regular fa-circle-check" required>
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
            </div>
            {{-- Additional Purchase Offer Terms --}}
            <div class="mt-4 pt-2 border-top">
              <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-file-contract me-2"></i>Additional Offer Terms</h5>
              <div class="row g-3">
                <div class="col-md-6 form-group">
                  <label class="fw-bold">Possession Date:</label>
                  <input type="date" name="possession_date" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                  <small class="text-muted">When would you like possession of the property?</small>
                </div>
                <div class="col-md-6 form-group">
                  <label class="fw-bold">Response Requested By:</label>
                  <input type="date" name="response_requested_by" class="form-control has-icon" data-icon="fa-regular fa-calendar-check">
                  <small class="text-muted">Deadline for seller to respond to this offer.</small>
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">Home Warranty Requested:</label>
                @php $yes_no_warranty = [['name'=>'Yes','target'=>'.homeWarrantyYes','icon'=>'fa-regular fa-circle-check'],['name'=>'No','target'=>'','icon'=>'fa-regular fa-circle-xmark']]; @endphp
                <select name="home_warranty_requested" class="grid-picker" style="justify-content:flex-start;">
                  <option value="">Select</option>
                  @foreach($yes_no_warranty as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>">{{ $item['name'] }}</option>
                  @endforeach
                </select>
                <div class="form-group homeWarrantyYes d-none mt-2">
                  <label class="fw-bold">Home Warranty Amount <small class="text-muted">(Optional)</small>:</label>
                  <input type="number" name="home_warranty_amount" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="e.g., 500">
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">Seller Contribution Requested:</label>
                @php $yes_no_contrib = [['name'=>'Yes','target'=>'.sellerContribYes','icon'=>'fa-regular fa-circle-check'],['name'=>'No','target'=>'','icon'=>'fa-regular fa-circle-xmark']]; @endphp
                <select name="seller_contribution_requested" class="grid-picker" style="justify-content:flex-start;">
                  <option value="">Select</option>
                  @foreach($yes_no_contrib as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row" style="width:calc(33.3% - 10px);" data-icon="<i class='{{ $item['icon'] }}'></i>">{{ $item['name'] }}</option>
                  @endforeach
                </select>
                <div class="form-group sellerContribYes d-none mt-2">
                  <label class="fw-bold">Contribution Amount or %:</label>
                  <input type="text" name="seller_contribution_amount" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="e.g., $5,000 or 3%">
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">Included Personal Property <small class="text-muted">(Optional)</small>:</label>
                <textarea name="included_personal_property" class="form-control" rows="2" placeholder="e.g., Refrigerator, washer/dryer, lawn mower..."></textarea>
                <small class="text-muted">List any personal property you expect to be included in the sale.</small>
              </div>
              <div class="form-group">
                <label class="fw-bold">Excluded Items <small class="text-muted">(Optional)</small>:</label>
                <textarea name="excluded_items" class="form-control" rows="2" placeholder="e.g., Seller's grandfather clock, dining room chandelier..."></textarea>
                <small class="text-muted">List any items you expect to be excluded from the sale.</small>
              </div>
              <div class="form-group">
                <label class="fw-bold">Custom Terms / Special Conditions <small class="text-muted">(Optional)</small>:</label>
                <textarea name="custom_terms" class="form-control" rows="3" placeholder="Any additional terms, conditions, or contingencies not covered above..."></textarea>
              </div>
            </div>
            {{-- /Additional Purchase Offer Terms --}}
            {{-- Slide 6 --}}
            {{-- Slide 7 --}}
            <div class="wizard-step" data-step="5">
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
                    <select class="grid-picker" name="propertyStyles" id=""
                        style="justify-content: flex-start;" required>
                        <option value="">Select</option>
                        @foreach ($propertyStyles as $item)
                            <option value="{{ $item['name'] }}" class="card flex-row"
                                style="width:calc(33.3% - 10px);"
                                data-icon='<i class="fa-regular fa-circle-check"></i>'>
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
                        style="width:calc(33.33% - 10px);" data-icon='<i class="fa-regular fa-circle-check"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group otherVacant d-none">
                    <label class="fw-bold">Current Use: </label>
                    <input type="text" name="otherProperty" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                  </div>
                  <div class="form-group otherBusiness d-none">
                    <label class="fw-bold">Business Type: </label>
                    <input type="text" name="otherProperty" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                  </div>
                </div>
              </div>
            </div>
            <div class="wizard-step" data-step="6">
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
                      style="width:calc(50% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
                <div class="form-group otherConditionRes d-none">
                  <label class="fw-bold">Property Condition: </label>
                  <input type="text" name="otherCondition" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                </div>
              </div>
            </div>
            @php
            $bedrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '10'], ['target' => '.custom_bedrooms', 'name' => 'Other']];
            $bathrooms = [['target' => '', 'name' => '1'], ['target' => '', 'name' => '1.5'], ['target' => '', 'name' => '2'], ['target' => '', 'name' => '2.5'], ['target' => '', 'name' => '3'], ['target' => '', 'name' => '3.5'], ['target' => '', 'name' => '4'], ['target' => '', 'name' => '4.5'], ['target' => '', 'name' => '5'], ['target' => '', 'name' => '5.5'], ['target' => '', 'name' => '6'], ['target' => '', 'name' => '6.5'], ['target' => '', 'name' => '7'], ['target' => '', 'name' => '7.5'], ['target' => '', 'name' => '8'], ['target' => '', 'name' => '8.5'], ['target' => '', 'name' => '9'], ['target' => '', 'name' => '9.5'], ['target' => '', 'name' => '10'], ['target' => '.custom_bathrooms', 'name' => 'Other']];
            $yes_or_nos = [['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];

          @endphp
          {{-- Residential/income Start --}}
          <div class="wizard-step" data-step="7">
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
          <div class="wizard-step" data-step="7">
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
          <div class="wizard-step" data-step="8">
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
              <div class="row form-group d-none unit-info">
                <div class="form-group col-md-4">
                  <label class="fw-bold">Beds/Unit:</label>
                  <input type="number" name="beds_unit" id="beds_unit"
                    class="form-control has-icon" data-icon="fa-solid fa-hotel">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Baths/Unit:</label>
                  <input type="number" name="baths_unit" id="baths_unit"
                    class="form-control has-icon" data-icon="fa-solid fa-hotel">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Sqft Heated:</label>
                  <input type="number" name="sqt_ft_heated" id="sqt_ft_heated"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group col-md-4">
                  <label class="fw-bold">Number of Units:</label>
                  <input type="number" name="number_of_units" id="number_of_units" 
                    class="form-control has-icon" data-icon="fa-solid fa-hotel">
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
                  <input type="number" name="custom_occupied" class="form-control has-icon" data-icon="fa-solid fa-hotel">
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
                  <input type="number" name="garage_spaces_unit" id="garage_spaces_unit"
                    class="form-control has-icon" data-icon="fa-solid fa-warehouse">
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">Unit Type Description:</label>
                  <textarea name="unit_type_of_description" id="unit_type_of_description" class="form-control" cols="30"
                    rows="10"></textarea>
                </div>
              </div>

              <div class="form-group col-md-4">
                <label class="fw-bold">Annual Gross Income</label>
                <input type="number" name="annual_gross_income" id="garage_attribute"
                   class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
              </div>
              <div class="form-group col-md-4">
                <label class="fw-bold">Total Monthly Rent</label>
                <input type="number" name="total_monthly_rent" id="garage_attribute" 
                  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
              </div>
              <div class="form-group col-md-4">
                <label class="fw-bold">Total Monthly Expenses</label>
                <input type="number" name="total_monthly_expenses" id="garage_attribute"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
              </div>

              <div class="form-group col-md-4">
                <label class="fw-bold">Annual Net Income</label>
                <input type="number" name="annual_net_income" id="garage_attribute" 
                  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
              </div>
              <div class="form-group col-md-4">
                <label class="fw-bold">Est Annual Market Income</label>
                <input type="number" name="est_annual_market_income" id="garage_attribute"
                  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
              </div>
              <div class="form-group col-md-4">
                <label class="fw-bold">Annual Expenses</label>
                <input type="number" name="annual_expenses" id="garage_attribute"
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
                      style="width:calc(33.3% - 10px);">
                      {{ $terms_of_lease['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group custom_leases_terms d-none">
                <label class="fw-bold">Length of Lease:</label>
                <input type="text" name="custom_leases_length" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
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
                  <input type="text" name="otherTermLease" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
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
                <input type="number" name="total_number_of_units" id="total_number_of_units" class="form-control has-icon" data-icon="fa-solid fa-hotel">
              </div>

            </div>
          </div>
          <div class="wizard-step" data-step="9">
            <div class="row ">
              <div class="form-group">
                <label class="fw-bold" for="heated_sqft">Heated Sqft:</label>
                <input type="number" name="heated_sqft" id="heated_sqft" class="form-control has-icon hide_arrow"
                  data-icon="fa-solid fa-ruler-combined" required>
              </div>
              <div class="form-group">
                <label class="fw-bold" for="sqft"> Total Sqft:</label>
                <input type="number" name="total_sqft" id="total_sqft" class="form-control has-icon hide_arrow"
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
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="10">
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
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
            <div class="form-group fireplace">
              <label class="fw-bold">Fireplace:</label>
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
          <div class="wizard-step" data-step="11">
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
                    class="form-control has-icon" data-icon="fa-regular fa-circle-check">
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
                  <input type="text" name="listed_furniture_price" id="listed_furniture_price" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="12">
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
                <input type="text" name="otherInterior" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="13">
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
              <select class="grid-picker" name="additionalRooms[]" style="justify-content: flex-start;" multiple>
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
          <div class="wizard-step" data-step="14">
            <div class="form-group">
              <label class="fw-bold">How many floors are in the property? </label>
              <input type="text" name="number_of_buildings" id="number_of_buildings" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-building">
            </div>
            <div class="form-group">
              <label class="fw-bold">What floor number is the property on?</label>
              <input type="text" name=" " id="floors_in_unit" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-hotel">
            </div>
            <div class="form-group">
              <label class="fw-bold">How many floors are in the entire building?</label>
              <input type="text" name="total_floors" id="total_floors" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-hotel">
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
          <div class="wizard-step" data-step="15">
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
                <input type="text" name="otherFloorCovering" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="16">
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
          <div class="wizard-step" data-step="17">
            @php
              $foundations = [['name' => 'Basement', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick/Mortar', 'target' => ''], ['name' => 'Concrete Perimeter', 'target' => ''], ['name' => 'Crawlspace', 'target' => ''], ['name' => 'Pillar/Post/Pier', 'target' => ''], ['name' => 'Slab', 'target' => ''], ['name' => 'Stem Wall', 'target' => ''], ['name' => 'Stilt/On Piling', 'target' => ''], ['name' => 'Other', 'target' => '.otherFoundationRes']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Foundation:</label>
              <select class="grid-picker" name="foundation[]" id="foundation" style="justify-content: flex-start;"
                multiple required>
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
                <input type="text" name="otherFoundation" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="18">
            @php
              $exterior_constructions = [['name' => 'Asbestos', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Cedar', 'target' => ''], ['name' => 'Cement Siding', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'HardiPlank Type', 'target' => ''], ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''], ['name' => 'Log', 'target' => ''], ['name' => 'Metal Frame', 'target' => ''], ['name' => 'Metal Siding', 'target' => ''], ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''], ['name' => 'Stone', 'target' => ''], ['name' => 'Stucco', 'target' => ''], ['name' => 'Tilt up Walls', 'target' => ''], ['name' => 'Vinyl Siding', 'target' => ''], ['name' => 'Wood Frame', 'target' => ''], ['name' => 'Wood Frame (FSC)', 'target' => ''], ['name' => 'Wood Siding ', 'target' => ''], ['name' => 'Other', 'target' => '.otherConstructionRes']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Exterior Construction:</label>
              <select class="grid-picker" name="exterior_construction[]" id="exterior_construction"
                style="justify-content: flex-start;" multiple required>
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
                <input type="text" name="otherConstruction" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="19">
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
                style="justify-content: flex-start;" multiple required>
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
                <input type="text" name="otherExterior" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="20">
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
                <input type="text" name="otherLotFeature" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="21">
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
                    ['name' => '1 Bed/1 Bath', 'target' => ''],
                    ['name' => '1 Bedroom', 'target' => ''],
                    ['name' => '2 Bed/1 Bath', 'target' => ''],
                    ['name' => '2 Bed/2 Bath', 'target' => ''],
                    ['name' => '2 Bedroom', 'target' => ''],
                    ['name' => '3 Bed/1 Bath', 'target' => ''],
                    ['name' => '3 Bed/2 Bath', 'target' => ''],
                    ['name' => '3 Bedroom', 'target' => ''],
                    ['name' => '4 Bedroom Or More', 'target' => ''],
                    ['name' => '4 Bed/1 Bath', 'target' => ''],
                    ['name' => '4 Bed/2 Bath', 'target' => ''],
                    ['name' => 'Appartments', 'target' => ''],
                    ['name' => 'Efficiency', 'target' => ''],
                    ['name' => 'Loft', 'target' => ''],
                    ['name' => "Manager's unit", 'target' => ''],
                    ['name' => 'Multi-Level', 'target' => ''],
                    ['name' => 'Penthouse', 'target' => ''],
                    ['name' => 'Studio', 'target' => ''],
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
                  <input type="text" name="sqftStructure" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Total Sqft of Additional Structure: </label>
                  <input type="text" name="totalSqft" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="22">
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
                ['name' => 'Other', 'target' => '.otherRoofRes']];
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
                <input type="text" name="otherRoof" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="23">
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
                <input type="text" name="otherSurface" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="24">
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
          <div class="wizard-step" data-step="25">
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
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="26">
            <label class="fw-bold">Land and Tax Information: (To find this information check out your
              local property appraiser website and enter the address of the property you are selling)</label>
            
            
            <div class="form-group">
              <label class="fw-bold">Tax ID (Parcel Number):</label>
              <input type="text" name="tax_id" id="tax_id" class="form-control has-icon"
                data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold">Tax Year:</label>
              <input type="text" name="tax_year" id="tax_year" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" >
            </div>
            <div class="form-group">
              <label class="fw-bold">Taxes (Annual Amount):</label>
              <input type="text" name="taxes_annual_amount" id="taxes_annual_ammount"
                class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
            </div>
            <div class="form-group ">
              @php
              $additialParcelRes = [
                ['name'=>'Yes','target'=>'.additialParcelResYes1','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                ['name'=>'No','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']
              ];
            @endphp
              <label class="fw-bold">Additional Parcels:</label>
              <select class="grid-picker" name="additionalParcels" id="sewer"
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
              <div class="form-group additialParcelResYes1 d-none">
                <div class="form-group">
                  <label class="fw-bold">Total Number of Parcels:</label>
                  <input type="number" name="total_number_of_parcels" id="total_number_of_parcels"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Additional Tax ID's:</label>
                  <input type="text" name="additional_tax_id" id="additional_tax_id" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group">
                  <label class="fw-bold" for="year_built">Year Built:</label>
                  <input type="text" name="year_built" id="year_built" class="form-control has-icon "
                    data-icon="fa-solid fa-calendar-day" required>
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
                <div class="form-group">
                  <label class="fw-bold" for="year_built">Legal Subdivison Name:</label>
                  <input type="text" name="legal_subdivison_name" id="legal_subdivison_name"
                    class="form-control has-icon " data-icon="fa-solid fa-ruler-combined"
                    >
                </div>
                <div class="form-group has_flood_zoon d-none">
                  <label class="fw-bold">Flood Zone Code:</label>
                  <input type="text" name="flood_zone_code" id="flood_zone_code" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
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
                  <input type="text" name="lot_size" id="lot_size" class="form-control has-icon "
                    data-icon="fa-solid fa-ruler-combined" data-msg-required="Please enter Lot Size">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Lot Size Acres:</label>
                  <input type="text" name="lot_size_acres" id="lot_size_acres" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
                <div class="form-group ">
                  <label class="fw-bold">Homestead:</label>
                  <select class="grid-picker" name="has_homestead" id="sewer"
                    style="justify-content: flex-start;" required>
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
                    data-icon="fa-solid fa-dollar-sign ">
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
                    data-icon="fa-solid fa-dollar-sign ">
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="27">
            @php
              $utilitiseRes = [
                ['name' => 'BB/HS Internet Available', 'target' => ''],
                ['name' => 'Cable Available', 'target' => ''],
                ['name' => 'Cable Connected', 'target' => ''],
                ['name' => 'Electricity Available', 'target' => ''],
                ['name' => 'Electricity Connected', 'target' => ''],
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
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>
            @php

              $waterRes = [['name' => 'Canal/Lake For Irrigation', 'target' => ''], ['name' => 'Private', 'target' => ''], ['name' => 'Public', 'target' => ''], ['name' => 'Well', 'target' => ''], ['name' => 'Well Required', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherWaterRes']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Water:</label>
              <select class="grid-picker" name="water[]" id="water12" style="justify-content: flex-start;" multiple >
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
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>

            @php
              $sewerRes = [['name' => 'Aerobic Septic', 'target' => ''],['name' => 'PEP-Holding Tank', 'target' => ''], ['name' => 'Private Sewer', 'target' => ''], ['name' => 'Public Sewer', 'target' => ''], ['name' => 'Septic Needed', 'target' => ''], ['name' => 'Septic Tank', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherSewerRes']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Sewer:</label>
              <select class="grid-picker" name="sewer[]" id="sewer" style="justify-content: flex-start;" multiple >
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
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="28">
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
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>
            @php
              $heating_and_fuel = [['name' => 'Baseboard', 'target' => ''], ['name' => 'Central', 'target' => ''], ['name' => 'Electric', 'target' => ''], ['name' => 'Exhaust Fans', 'target' => ''], ['name' => 'Gas', 'target' => ''], ['name' => 'Heat Pump', 'target' => ''], ['name' => 'Heat Recovery Unit', 'target' => ''], ['name' => 'Natural Gas', 'target' => ''], ['name' => 'Oil', 'target' => ''], ['name' => 'Partial', 'target' => ''], ['name' => 'Propane', 'target' => ''], ['name' => 'Radiant Ceiling', 'target' => ''], ['name' => 'Reverse Cycle', 'target' => ''], ['name' => 'Solar', 'target' => ''], ['name' => 'Space Heater', 'target' => ''], ['name' => 'Wall Furnace', 'target' => ''], ['name' => 'Wall Units / Window Unit', 'target' => ''], ['name' => 'Zoned', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherHeatingFuelRes']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Heating and Fuel:</label>
              <select class="grid-picker" name="heating_and_fuel[]" id="heating_and_fuel"
                style="justify-content: flex-start;" multiple >
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
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="29">
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
                  ['name' => 'Primary Bathroom', 'target' => ''],
                  ['name' => 'Primary Bedroom', 'target' => ''],
                  ['name' => 'Media Room', 'target' => ''],
                  ['name' => 'Office', 'target' => ''],
                  ['name' => 'Sauna', 'target' => ''],
                  ['name' => 'Studio', 'target' => ''],
                  ['name' => 'Study/Den', 'target' => ''],
                  ['name' => 'Workshop', 'target' => ''],
                  ['name' => 'Dinette', 'target' => ''],
                  ['name' => 'Garage Room', 'target' => ''],
                  ['name' => 'Garage Apartment', 'target' => ''],
                  ['name' => 'Double Primary Bedroom', 'target' => ''],
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
            <div class="form-group d-none room-type-fields">
              <div class="form-group">
                <label class="fw-bold">Approximate Room Dimensions:</label>
                <input type="text" name="approximate_room_dimensions" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" >
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
                $bed_room_closest_types = [['name' => 'Built-in Closet', 'target' => ''], ['name' => 'Coat Closet', 'target' => ''], ['name' => 'Dual Closets', 'target' => ''], ['name' => 'Linen Closet', 'target' => ''], ['name' => 'No Closet', 'target' => ''], ['name' => 'Storage Closet', 'target' => ''], ['name' => 'Walk-in Closet', 'target' => '']];
              @endphp
              <div class="form-group ">
                <label class="fw-bold">Closet Type:</label>
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
                    ['name' => 'Vinyl', 'target' => ''],
                    ['name' => 'Wood', 'target' => ''],
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
                    ['name' => 'Wet Bar', 'target' => ''],
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
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="30">
            <label class="fw-bold">Water and Dock Information:</label>
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
              $water_frontage = [['name' => 'Bay/Harbor', 'target' => ''], ['name' => 'Bayou', 'target' => ''], ['name' => 'Beach', 'target' => ''], ['name' => 'Brackish Water', 'target' => ''], ['name' => 'Canal - Brackish', 'target' => ''], ['name' => 'Canal - Freshwater', 'target' => ''], ['name' => 'Canal - Saltwater', 'target' => ''], ['name' => 'Creek', 'target' => ''], ['name' => 'Freshwater Canal w/Lift to Saltwater Canal', 'target' => ''], ['name' => 'Gulf/Ocean', 'target' => ''], ['name' => 'Gulf/Ocean to Bay', 'target' => ''], ['name' => 'Intracoastal Waterway', 'target' => ''], ['name' => 'Lagoon/Estuary', 'target' => ''], ['name' => 'Lake', 'target' => ''], ['name' => 'Lake - Chain of Lakes', 'target' => ''], ['name' => 'Marina', 'target' => ''], ['name' => 'Pond', 'target' => ''], ['name' => 'River', 'target' => ''], ['name' => 'Canal Front', 'target' => ''], ['name' => 'Riparian Rights', 'target' => '']];
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
              <label class="fw-bold">Dock:</label>
              <select class="grid-picker" name="has_dock" id="has_dock"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  @php
                    if ($item['name'] == 'Yes') {
                        $target = '.dock_residential_and_income';
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
                  ['name' => 'Dock w/Water Supply', 'target' => ''],
                  ['name' => 'Dock w/o Water Supply', 'target' => ''],
                  ['name' => 'Dry Dock', 'target' => ''],
                  ['name' => 'Fish Cleaning Station', 'target' => ''],
                  ['name' => 'Floating Dock', 'target' => ''],
                  ['name' => 'Harbormaster', 'target' => ''],
                  ['name' => 'Internet', 'target' => ''],
                  ['name' => 'Lift', 'target' => ''],
                  ['name' => 'Restroom/Shower', 'target' => ''],
                  ['name' => 'Wet Dock', 'target' => ''],
                  ['name' => 'None', 'target' => ''],
                  ['name' => 'Other', 'target' => '.other-dock'],
              ];
            @endphp
            <div class="form-group dock_residential_and_income d-none ">
              <label class="fw-bold">Dock Description:</label>
              <select class="grid-picker" name="dock[]" id="dock"
                style="justify-content: flex-start;" multiple>
                <option value="">Select</option>
                @foreach ($dock as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                    style="width:calc(33.3% - 10px);" required>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group other-dock d-none">
                <label class="fw-bold">Dock Description:</label>
                <input type="text" name="custom_dock" id="custom_dock"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Lift Capacity:</label>
                <input type="text" name="dock_lift_capacity" id="dock_lift_capacity"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Year Built:</label>
                <input type="text" name="dock_year_built" id="dock_year_built"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Dimension:</label>
                <input type="text" name="dock_dimension" id="dock_dimension"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Maintenance Fee:</label>
                <input type="text" name="dock_maintenance_fee" id="dock_maintenance_fee"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              @php
                $feeFrequency = [['name' => 'Annual', 'target' => ''], ['name' => 'Monthly', 'target' => ''], ['name' => 'Quarterly', 'target' => ''], ['name' => 'N/A', 'target' => '']]
              @endphp
              <label class="fw-bold">Dock Maintenance Fee Frequency:</label>
              <select class="grid-picker" name="dock_maintenance_fee_frequency" id="dock_maintenance_fee_frequency"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($feeFrequency as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                    style="width:calc(33.3% - 10px);">
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="wizard-step" data-step="31">
            <label class="fw-bold">HOA, Condo Association and/or Master Association Information:</label>
            <div class="form-group">
              <?php
                  $propsOpt = [
                      ['name' => 'Yes', 'icon' => 'fa-regular fa-circle-check', 'target' => '.hoas_residential_and_income'],
                      ['name' => 'No', 'icon' => 'fa-regular fa-circle-xmark', 'target' => '']
                  ];
              ?>

              <label class="fw-bold">Does the property have an HOA, condo association, master
                association, and/or community fee?</label>
              <select class="grid-picker" name="has_hoa" id="has_hoa" style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($propsOpt as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
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
                    ['name' => 'Dog Park', 'target' => ''],
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
                    ['name' => 'None', 'target' => ''],
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
                  <input type="text" name="otherAssocAmenities" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
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
                  <input type="text" name="otherFeeInclude" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">Amenities with Additional Fees:</label>
                <input type="text" name="amenities_with_additional_fees"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check">
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
                    <input type="text" name="additionalFeeReason" id="additionalFeeReason" class="form-control has-icon" data-icon="">
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
          <div class="wizard-step" data-step="32">
            <label class="fw-bold">Ownership, Leasing Restrictions and Pets Information:</label>
            @php
              $ownerships = [
                ['name' => 'Co-Op', 'target' => ''], 
                ['name' => 'Condominium', 'target' => ''], 
                ['name' => 'Fee Simple', 'target' => ''],
                ['name' => 'Fractional', 'target' => ''],
                ['name' => 'Leasehold', 'target' => ''] ,
                ['name' => 'Other', 'target' => '']];
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
                data-icon="fa-regular fa-circle-check">
            </div>
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
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Days Notice to Tenant if not Renewing:</label>
                  <input type="text" name="days_notice_to_terminate" id="days_notice_to_terminate"
                    class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                </div>
              </div>
            </div>
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
                <label class="fw-bold">Maximum Lease Times Per Year:</label>
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
                <input type="text" name="number_of_ownership_prior_lease" class="form-control has-icon"
                  data-icon="fa-regular fa-calendar-days">
              </div>
            </div>
            <div class="form-group">
              <label class="fw-bold">Pets Allowed:</label>
              <select class="grid-picker" name="ptes_Allowed" id="has_rental_restrictions"
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
              <div class="row ">
                <div class="form-group">
                  <label class="fw-bold">Acceptable Pet Types:</label>
                  <input type="text" name="acceptablePet" class="form-control has-icon" data-icon="fa-solid fa-dog">
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
                      class="form-control has-icon" data-icon="fa-solid fa-dog">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Max Pet Weight:</label>
                    <input type="text" name="max_pet_weight" id="max_pet_weight" class="form-control has-icon"
                      data-icon="fa-solid fa-dog">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Pet Restrictions:</label>
                    <textarea name="pet_restrictions" id="pet_restrictions" class="form-control" cols="30" rows="5"></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="33">
            <label class="fw-bold">Green Features:</label>
            @php
              $greenOpt = [
                ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.green-field-opts'],
                ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Does the property have any Green Features?</label>
              <select class="grid-picker" name="green_features" id="green_features" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($greenOpt as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);" data-icon="{{$item['icon']}}" >
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group d-none green-field-opts" >
              @php
              $buildingVerificationOpt = [
                ['name'=>'Certified Passive House','target'=>''],
                ['name'=>'EarthCraft House','target'=>''],
                ['name'=>'ENERGY STAR Certified Homes','target'=>''],
                ['name'=>'EnerPHit','target'=>''],
                ['name'=>'EPA Indoor Air Quality Plus','target'=>''],
                ['name'=>'FGBC Green Certified Building','target'=>''],
                ['name'=>'FGBC Green Certified Home','target'=>''],
                ['name'=>'FGBC Remodel','target'=>''],
                ['name'=>'Florida Certified Yard','target'=>''],
                ['name'=>'Florida Friendly Landscape','target'=>''],
                ['name'=>'Florida Friendly Yard Recognition','target'=>''],
                ['name'=>'Florida Green Lodging','target'=>''],
                ['name'=>'Florida Water Star','target'=>''],
                ['name'=>'FORTIFIED for Safer Living','target'=>''],
                ['name'=>'Geothermal HVAC','target'=>''],
                ['name'=>'HERS Index Score','target'=>''],
                ['name'=>'Home Energy Score','target'=>''],
                ['name'=>'Home Energy Upgrade Certificate of Energy Efficiency Improvements','target'=>''],
                ['name'=>'Home Energy Upgrade Certificate of Energy Efficiency Performance','target'=>''],
                ['name'=>'Home Performance with ENERGY STAR','target'=>''],
                ['name'=>'LEED Certified Building','target'=>''],
                ['name'=>'LEED for Homes','target'=>''],
                ['name'=>'LEED Neighborhood development','target'=>''],
                ['name'=>'Living Building Challenge','target'=>''],
                ['name'=>'NAHB Certification','target'=>''],
                ['name'=>'NGBS New Construction','target'=>''],
                ['name'=>'NGBS Small Projects Remodel','target'=>''],
                ['name'=>'NGBS Whole-Home Remodel','target'=>''],
                ['name'=>'Other - Specify in Remarks','target'=>''],
                ['name'=>'Pearl Certification','target'=>''],
                ['name'=>'PHIUS+','target'=>''],
                ['name'=>'WaterSense','target'=>''],
                ['name'=>'Zero Energy Ready Home','target'=>'']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Building Verification:</label>
                <select class="grid-picker" name="building_verification" id="building_verification" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($buildingVerificationOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
              $statusOpt = [
                ['name'=>'Complete','target'=>''],
                ['name'=>'In Progress','target'=>'']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Status:</label>
                <select class="grid-picker" name="green_status" id="green_status" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($statusOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">Year:</label>
                <input type="text" name="green_year" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
              <div class="form-group">
                <label class="fw-bold">Version:</label>
                <input type="text" name="green_version" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
              <div class="form-group">
                <label class="fw-bold">Body:</label>
                <input type="text" name="green_body" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
              <div class="form-group">
                <label class="fw-bold">Metric:</label>
                <input type="text" name="green_metric" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
              <div class="form-group">
                <label class="fw-bold">Rating:</label>
                <input type="text" name="green_rating" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
              @php
              $sourceOpt = [
                ['name'=>'Administrator','target'=>''],
                ['name'=>'Assessor','target'=>''],
                ['name'=>'Builder','target'=>''],
                ['name'=>'Contractor or Installer','target'=>''],
                ['name'=>'Other - see Remarks','target'=>''],
                ['name'=>'Owner','target'=>''],
                ['name'=>'Program Sponser','target'=>''],
                ['name'=>'Program Verifier','target'=>''],
                ['name'=>'Public Records','target'=>'']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Source:</label>
                <select class="grid-picker" name="green_source" id="green_source" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($sourceOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="form-group">
                <label class="fw-bold">Green Verification URL:</label>
                <input type="text" name="green_url" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
              @php
              $sustainabilityOpt = [
                ['name'=>'Conserving Methods','target'=>''],
                ['name'=>'Onsite Recycling Center','target'=>''],
                ['name'=>'Recyclable Materials','target'=>''],
                ['name'=>'Recycled Materials','target'=>''],
                ['name'=>'Regionally-Sourced Materials','target'=>''],
                ['name'=>'Renewable Materials','target'=>''],
                ['name'=>'Salvaged Materials','target'=>'']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Green Sustainability:</label>
                <select class="grid-picker" name="green_sustainability" id="green_sustainability" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($sustainabilityOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
              $generationOpt = [
                ['name'=>'Hydro Power','target'=>''],
                ['name'=>'Solar','target'=>''],
                ['name'=>'Wind','target'=>'']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Green Energy Generation:</label>
                <select class="grid-picker" name="green_generation" id="green_generation" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($generationOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
              $waterOpt = [
                ['name'=>'Drip Irrigation','target'=>''],
                ['name'=>'Efficient Hot Water Distribution','target'=>''],
                ['name'=>'Gray Water System','target'=>''],
                ['name'=>'Green Infrastructure','target'=>''],
                ['name'=>'Irrig. System-Drip/Microheads','target'=>''],
                ['name'=>'Irrig. System-Rainwater from Ponds','target'=>''],
                ['name'=>'Irrigation-Reclaimed Water','target'=>''],
                ['name'=>'Low-Flow Fixtures','target'=>''],
                ['name'=>'Water Recycling','target'=>''],
                ['name'=>'Water Smart Landscaping','target'=>''],
                ['name'=>'Whole House Water Purification','target'=>'']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Green Water Features:</label>
                <select class="grid-picker" name="green_water_features" id="green_water" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($waterOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
              $energyOpt = [
                ['name'=>'Appliances','target'=>''],
                ['name'=>'Construction','target'=>''],
                ['name'=>'Doors','target'=>''],
                ['name'=>'Energy Monitoring System','target'=>''],
                ['name'=>'Exposure/Shade','target'=>''],
                ['name'=>'HVAC','target'=>''],
                ['name'=>'Incentives','target'=>''],
                ['name'=>'Insulation','target'=>''],
                ['name'=>'Lighting','target'=>''],
                ['name'=>'Pool','target'=>''],
                ['name'=>'Roof','target'=>''],
                ['name'=>'Thermostat','target'=>''],
                ['name'=>'Water Heater','target'=>''],
                ['name'=>'Windows','target'=>'']
            ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Green Energy Features:</label>
                <select class="grid-picker" name="green_energy_features" id="green_energy" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($energyOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
              $landscapingOpt = [
                ['name'=>'Fl. Friendly/Native Landscape','target'=>''],
                ['name'=>'Non-Toxic Fertilizer/Pesticides','target'=>''],
                ['name'=>'Rain Water Harvesting','target'=>''],
                ['name'=>'Veg. (Productive) Garden','target'=>''],
                ['name'=>'Xeriscape','target'=>'']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Green Landscaping:</label>
                <select class="grid-picker" name="green_landscaping" id="green_landscaping" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($landscapingOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
              $solarOpt = [
                ['name'=>'Owned','target'=>''],
                ['name'=>'Leased/Assumable','target'=>''],
                ['name'=>'Leased/Non-Assumable','target'=>'']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Solar Panel Ownership:</label>
                <select class="grid-picker" name="green_solar" id="green_solar" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($solarOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
              $disasterOpt = [
                ['name'=>'Above Flood Plain','target'=>''],
                ['name'=>'Fire Resistant Exterior','target'=>''],
                ['name'=>'Fire/Smoke Detection Integration','target'=>''],
                ['name'=>'Hurricane Insur. Deduction Qual.','target'=>''],
                ['name'=>'Hurricane Shutters/Windows','target'=>''],
                ['name'=>'Lightning Protection System','target'=>''],
                ['name'=>'Safe Room','target'=>'']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Disaster Mitigation:</label>
                <select class="grid-picker" name="green_disaster" id="green_disaster" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($disasterOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
              @php
              $airOpt = [
                ['name'=>'Air Filters MERV 10+','target'=>''],
                ['name'=>'Containment Control','target'=>''],
                ['name'=>'HVAC Cartridge/Media Filter','target'=>''],
                ['name'=>'HVAC Filter MERV 8+','target'=>''],
                ['name'=>'HVAC UV/Elec. Filtration','target'=>''],
                ['name'=>'Integrated Pest Management','target'=>''],
                ['name'=>'Janitor Closet Neg Pressurized','target'=>''],
                ['name'=>'Moisture Control','target'=>''],
                ['name'=>'No Smoking-Interior Buildg','target'=>''],
                ['name'=>'No/Low VOC Cabinets/Counters','target'=>''],
                ['name'=>'No/Low VOC Flooring','target'=>''],
                ['name'=>'No/Low VOC Paint/Finish','target'=>''],
                ['name'=>'Non-Toxic Pest Control','target'=>''],
                ['name'=>'Sealed Combustion','target'=>''],
                ['name'=>'Ventilation','target'=>''],
                ['name'=>'Whole House Vacuum System','target'=>'']
              ];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Indoor Air Quality:</label>
                <select class="grid-picker" name="green_air" id="green_air" style="justify-content: flex-start;">
                  <option value="">Select</option>
                  @foreach ($airOpt as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $target }}" class="card flex-row"
                      style="width:calc(33.3% - 10px);" data-icon="<i class='fa-regular fa-circle-check'></i>" >
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="34">
            <div class="form-group">
              <label class="fw-bold"> Description:</label>
              <textarea name="description" id="description" class="form-control" cols="30" rows="10" required></textarea>
            </div>
            <div class="form-group">
              <label class="fw-bold">Legal Disclamers:</label>
              <textarea name="disclamer" id="keywords" class="form-control" cols="30" rows="10" ></textarea>  
            </div>
            <div class="form-group">
              <label class="fw-bold">Driving Directions:</label>
              <textarea name="driving_directions" id="keywords" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" cols="30" rows="10" ></textarea>    
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
                  <div class="d-flex justify-content-between aalign-items-center">
                    <label class="fw-bold">Buyer’s Agent Compensation:</label>
                    <div class="d-flex align-items-center justify-content-center icon-select-btn-div">
                        <button type="button" class="select-btn me-1 active"
                            data-type="amount">$</button>
                        <button type="button" class="select-btn" data-type="percent">%</button>
                    </div>
                  </div>
                  <input type="text" name="compensation_amount" class="form-control has-icon"
                    data-icon="fa-solid fa-percent">
                </div>
              </div>
          </div>
          <div class="wizard-step" data-step="35">
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
              <input type="text" name="listing_link" id="listing_link" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-link">
            </div>
          </div>
          <div class="wizard-step" data-step="36">
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
          <div class="wizard-step" data-step="37">
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
          <div class="wizard-step" data-step="38">
            <div class="form-group">
              <label class="fw-bold">3D Tour (Link):</label>
              <input type="text" name="three_d_tour" id="three_d_tour" placeholder=""
              class="form-control has-icon" data-icon="fa-solid fa-link">
            </div>
            <div class="form-group">
              <label class="fw-bold">Floor Plan:</label>
              <input type="file" name="floor_plan[]" id="floor_plan_1" class="form-control" accept="image/*" >
            </div>
            <div class="form-group">
              <label class="fw-bold">Addendums/Disclosures:</label>
              <input type="file" name="disclosures[]" id="upload_file" placeholder="" class="form-control documents-input"
              multiple>
            </div>
            <span class="resFields">
              <div class="row">
                <div class="col-6 video_div">
                  <input type="hidden" name="video_type" class="video_type" value="video_upload">
                  <div class="video_type_select d-flex align-items-center justify-content-left">
                      <div class="form-check me-2">
                          <label class="form-check-label">
                              <input type="checkbox" class="video_upload form-check-input video_type_check" name="video_upload" >
                              Video Upload
                          </label>
                      </div>
                      <div class="form-check me-2">
                          <label class="form-check-label">
                              <input type="checkbox" class="youtube_video form-check-input video_type_check" name="youtube_video">
                              Youtube Video
                          </label>
                      </div>
                      <div class="form-check">
                          <label class="form-check-label">
                              <input type="checkbox" class="vimeo_video form-check-input video_type_check" name="vimeo_video">
                              Vimeo Video
                          </label>
                      </div>
                  </div>
                  <div class="video-upload video-type-element">
                      <label class="fw-bold mt-1">Property Video:</label>
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
                  <div class="youtube-video video-type-element form-group d-none">
                      <label class="fw-bold mt-1">Youtube Video:</label>
                      <input type="text" name="youtube_video_link" class="form-control" placeholder="Youtube video link i.e. https://youtube.com/embed/videoId">
                  </div>
                  <div class="vimeo-video video-type-element form-group d-none">
                      <label class="fw-bold mt-1">Vimeo Video:</label>
                      <input type="text" name="vimeo_video_link" class="form-control" placeholder="Vimeo video link i.e. https://player.vimeo.com/video/videoId">
                  </div>
                </div>
                <div class="col-6">
                    <div class="upload form-group">
                        <label class="fw-bold">Property Photos:</label>
                        <div class="wrapper">
                          <div class="box">
                            <div class="js--image-preview"></div>
                            <div class="upload-options">
                              <label class="image-input-label">
                                <input type="file" name="photo[]" class="image-input" accept="image/*" multiple />
                              </label>
                            </div>
                            <div class="thumbnails-container"></div>
                            <div class="hidden-inputs-container"></div>
                          </div>
                        </div>
                    </div>
                </div>
              </div>
            </span>
          </div>
          {{-- residential/income end --}}
          {{-- commercial/business Start --}}
          <div class="wizard-step" data-step="39">
            <div class="form-group">
              <label class="fw-bold">Bathrooms:</label>
              <select class="grid-picker" name="bathroomsCom" id="bathrooms" style="">
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
              <input type="text" name="custom_bathrooms_com" id="custom_bathrooms" class="form-control has-icon"
                data-icon="fa-solid fa-bath">
            </div>
          </div>
          <div class="wizard-step" data-step="40">
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
          <div class="wizard-step" data-step="41">
            <div class="row ">
              <div class="form-group">
                <label class="fw-bold" for="heated_sqft">Heated Sqft:</label>
                <input type="number" name="heated_sqft_com" placeholder="Heated sqft" id="heated_sqft"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined" required>
              </div>
              <div class="form-group">
                <label class="fw-bold" for="sqft">Total Sqft:</label>
                <input type="number" name="total_sqft_com" placeholder="Heated sqft" id="heated_sqft"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-ruler-combined" required>
              </div>
              @php
                $heated_sources = [['name' => 'Appraisal', 'target' => ''], ['name' => 'Building', 'target' => ''], ['name' => 'Measured', 'target' => ''], ['name' => 'Owner Provided', 'target' => ''], ['name' => 'Public Records', 'target' => '']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Sqft Heated Source:</label>
                <select class="grid-picker" name="heated_source_com" id="heated_sources"
                  style="justify-content: flex-start;" required>
                  <option value="">Select</option>
                  @foreach ($heated_sources as $item)
                    <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                      data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-column"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="42">
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
                  <option value="{{ $item['name'] }}" data-icon='<i class="fa-regular fa-circle-check"></i>'
                    data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(33.3% - 10px);">
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group otherAppliancesIncome d-none">
                <label class="fw-bold">Appliances:</label>
                <input type="text" name="otherAppliancesCom" id="flood_zone_code" placeholder=""
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="43">
            <div class="form-group ">
              @php
                $furnishingsIncome = [['name' => 'Yes', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],['name' => 'Optional', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-question"></i>']];
              @endphp
              <label class="fw-bold">Are there any furnishings included in the purchase?</label>
              <select class="grid-picker" name="has_furnishing_com" id="has_water_view"
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
                  <input type="text" name="furnishings_include_com" id="flood_zone_code" placeholder=""
                    class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                </div>
              </div>
              @php
                $additional_fees = [['name' => 'Additional Fees', 'target' => ''], ['name' => 'Included in Purchase Price', 'target' => '']];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Are there any additional fees for the listed furnishings, or are they included in the purchase price?</label>
                <select class="grid-picker" name="has_additional_fees_com" id="has_water_view"
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
                  <input type="text" name="listed_furniture_price_com" id="listed_furniture_price" placeholder=""
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="44">
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
            <div class="form-group">
              <div class="form-group">
                <label class="fw-bold">Licenses:</label>
                <input type="text" name="custom_licenses" id="custom_licenses" placeholder=""
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="45">
            <div class="form-group">
              <label class="fw-bold">How many floors are in the property?</label>
              <input type="text" name="number_of_buildings_com" id="number_of_buildings" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-hotel">
            </div>
            <div class="form-group">
              <label class="fw-bold">What floor number is the property on?</label>
              <input type="number" name="floors_in_unit_com" id="floors_in_unit" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-building">
            </div>
            <div class="form-group">
              <label class="fw-bold">How many floors are in the entire building?</label>
              <input type="number" name="total_floors_com" id="total_floors" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-building">
            </div>
            <div class="form-group">
              <label class="fw-bold">Building Elevator</label>
              <select class="grid-picker" name="building_elevator_com" id="building_elevator"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(25% - 10px);" data-icon='<i class="fa-solid fa-building"></i>'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="wizard-step" data-step="46">
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
                  ['name' => 'Other', 'target' => '.otherFloorCoveringCom'],
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
            <div class="form-group otherFloorCoveringCom d-none">
              <label class="fw-bold">Floor Covering:</label>
              <input type="text" name="otherFloorCoveringCom" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
            </div>
          </div>
          <div class="wizard-step" data-step="47">
            @php
              $front_exposures = [['name' => 'North', 'target' => ''], ['name' => 'East', 'target' => ''], ['name' => 'South', 'target' => ''], ['name' => 'West', 'target' => ''], ['name' => 'Southeast', 'target' => ''], ['name' => 'Northeast', 'target' => ''], ['name' => 'Southwest', 'target' => ''], ['name' => 'Northwest', 'target' => '']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Front Exposure:</label>
              <select class="grid-picker" name="front_exposure_com" id="front_exposure"
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
          <div class="wizard-step" data-step="48">
            @php
              $foundations = [['name' => 'Basement', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick/Mortar', 'target' => ''], ['name' => 'Concrete Perimeter', 'target' => ''], ['name' => 'Crawlspace', 'target' => ''], ['name' => 'Other', 'target' => ''], ['name' => 'Pillar/Post/Pier', 'target' => ''], ['name' => 'Slab', 'target' => ''], ['name' => 'Stem Wall', 'target' => ''], ['name' => 'Stilt/On Piling', 'target' => ''], ['name' => 'Other', 'target' => '.otherFoundationCom']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Foundation:</label>
              <select class="grid-picker" name="foundation[]" id="foundation" style="justify-content: flex-start;"
                multiple required>
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
            <div class="form-group otherFoundationCom d-none">
              <label class="fw-bold">Foundation:</label>
              <input type="text" name="otherFoundationCom" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
            </div>
          </div>
          <div class="wizard-step" data-step="49">
            @php
              $exterior_constructions = [['name' => 'Asbestos', 'target' => ''], ['name' => 'Block', 'target' => ''], ['name' => 'Brick', 'target' => ''], ['name' => 'Cedar', 'target' => ''], ['name' => 'Cement Siding', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'HardiPlank Type', 'target' => ''], ['name' => 'ICFs (Insulated Concrete Forms)', 'target' => ''], ['name' => 'Log', 'target' => ''], ['name' => 'Metal Frame', 'target' => ''], ['name' => 'Metal Siding', 'target' => ''], ['name' => 'SIP (Structurally Insulated Panel)', 'target' => ''], ['name' => 'Stone', 'target' => ''], ['name' => 'Stucco', 'target' => ''], ['name' => 'Tilt up Walls', 'target' => ''], ['name' => 'Vinyl Siding', 'target' => ''], ['name' => 'Wood Frame', 'target' => ''], ['name' => 'Wood Frame (FSC)', 'target' => ''], ['name' => 'Wood Siding ', 'target' => ''], ['name' => 'Other ', 'target' => '.otherExteriorCon']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Exterior Construction:</label>
              <select class="grid-picker" name="exterior_construction[]" id="exterior_construction"
                style="justify-content: flex-start;" multiple required>
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
                <input type="text" name="otherConstructionCom" id="max_pet_weight" class="form-control has-icon"
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="50">
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
                  ['name' => 'Infrastructure', 'target' => ''],
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
                <input type="text" name="otherLotFeatureCom" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="51">
            @php
              $otherStructures = [
                  ['name' => 'Airplane Hangar', 'target' => ''],
                  ['name' => 'Annex', 'target' => ''],
                  ['name' => 'Containers', 'target' => ''],
                  ['name' => 'Garage(s)', 'target' => ''],
                  ['name' => 'Maintenance', 'target' => ''],
                  ['name' => 'Outbuilding', 'target' => ''],
                  ['name' => 'Security Trailer', 'target' => ''],
                  ['name' => 'Storage', 'target' => ''],
                  ['name' => 'Workshop', 'target' => ''],
                  ['name' => 'Other', 'target' => '.otherStructures'],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Other Structures:</label>
              <select class="grid-picker" name="other_structures[]" id="lot_features"
                style="justify-content: flex-start;" multiple>
                <option value="">Select</option>
                @foreach ($otherStructures as $structure)
                  <option value="{{ $structure['name'] }}" data-target="{{ $structure['target'] }}"
                    class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                    style="width:calc(33.3% - 10px);">
                    {{ $structure['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group otherStructures d-none">
                <label class="fw-bold">Other Structures::</label>
                <input type="text" name="custom_other_structures" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="52">
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
                <input type="text" name="otherBuilding" placeholder="" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="53">
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
                <input type="text" name="otherAdjoining" placeholder="" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="54">
            @php
              $roofs = [['name' => 'Built-Up', 'target' => ''], ['name' => 'Cement', 'target' => ''], ['name' => 'Concrete', 'target' => ''], ['name' => 'Membrane', 'target' => ''], ['name' => 'Metal', 'target' => ''], ['name' => 'Roof Over', 'target' => ''], ['name' => 'Shake', 'target' => ''], ['name' => 'Shingle', 'target' => ''], ['name' => 'Slate', 'target' => ''], ['name' => 'Tile', 'target' => ''], ['name' => 'Other', 'target' => '.otherRoofCommercial']];
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
                <input type="text" name="otherRoofCom" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="55">
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
                <input type="text" name="otherSurfaceCom" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="56">
            @php
              $road_frontage = [['name' => 'Access Road', 'target' => ''], ['name' => 'Alley', 'target' => ''], ['name' => 'Business District', 'target' => ''], ['name' => 'City Street', 'target' => ''], ['name' => 'County Road', 'target' => ''], ['name' => 'Divided Highway', 'target' => ''], ['name' => 'Easement', 'target' => ''], ['name' => 'Highway', 'target' => ''], ['name' => 'Interchange', 'target' => ''], ['name' => 'Interstate', 'target' => ''], ['name' => 'Main Thoroughfare', 'target' => ''], ['name' => 'Private Road', 'target' => ''], ['name' => 'Rail', 'target' => ''], ['name' => 'State Road', 'target' => ''], ['name' => 'Turn Lanes', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherFrontageCommercial']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Road Frontage:</label>
              <select class="grid-picker" name="road_frontage[]" id="road_frontage"
                style="justify-content: flex-start;" multiple required>
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
                <input type="text" name="otherFrontage" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="57">
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
                    data-icon="<i class='fa-solid fa-warehouse'></i>" style="width:calc(33.3% - 10px);">
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group otherGarageFeatureCommercial d-none" >
                <label class="fw-bold">Garage/Parking Features:</label>
                <input type="text" name="otherGarageFeature" class="form-control has-icon" data-icon="fa-solid fa-warehouse">
              </div>
            </div>
          </div>  
          <div class="wizard-step" data-step="58">
            <h4>Land and Tax Information:</h4>
            <div class="form-group">
              <label class="fw-bold">Tax ID (Parcel Number):</label>
              <input type="text" name="tax_id_com" id="tax_id" class="form-control has-icon"
                data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold">Tax Year:</label>
              <input type="text" name="tax_year_com" id="tax_year" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" >
            </div>
            <div class="form-group">
              <label class="fw-bold">Taxes (Annual Amount):</label>
              <input type="text" name="taxes_annual_amount_com" id="taxes_annual_ammount"
                class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
            </div>
            <div class="form-group ">
              @php
                $additionalParcelsCommercial = [['name' => 'Yes', 'target' => '.parcelYesCommercial', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
              @endphp
              <label class="fw-bold">Additional Parcels:</label>
              <select class="grid-picker" name="additionalParcelsCom" style="justify-content: flex-start;">
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
                <input type="text" name="parcelYes" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
            <div class="form-group">
              <label class="fw-bold">Total Number of Parcels:</label>
              <input type="text" name="total_number_of_parcels_com" id="total_number_of_parcels"
                class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold">Additional Tax ID's:</label>
              <input type="text" name="additional_tax_id_com" id="additional_tax_id" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold" for="year_built">Year Built:</label>
              <input type="text" name="year_built_com" id="year_built"
                class="form-control has-icon " data-icon="fa-solid fa-calendar-day">
            </div>
            <div class="form-group">
              <label class="fw-bold">Zoning:</label>
              <input type="text" name="zoning_com" id="zoning" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold">Legal Description:</label>
              <input type="text" name="legal_description_com" id="legal_description" class="form-control has-icon" data-icon="fa-solid fa-tag">
            </div>
            <div class="form-group">
              <label class="fw-bold" for="year_built">Legal Subdivison:</label>
              <input type="text" name="legal_subdivison_name_com" id="legal_subdivison_name"
                class="form-control has-icon " data-icon="fa-solid fa-ruler-combined" >
            </div>
            @php
            $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Total Acreage:</label>
              <select class="grid-picker" name="total_aceage_com" id="lot_size"
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
              <label class="fw-bold">Flood Zone Code:</label>
              <input type="text" name="flood_zone_code_com"
                class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold" for="lot_size">Lot Size Square Footage:</label>
              <input type="text" name="lot_size_com" id="lot_size" class="form-control has-icon "
                data-icon="fa-solid fa-ruler-combined" data-msg-required="Please enter Lot Size">
            </div>
            <div class="form-group">
              <label class="fw-bold">Lot Size Acres:</label>
              <input type="text" name="lot_size_acres_com" id="lot_size_acres" class="form-control has-icon"
                data-icon="fa-solid fa-ruler-combined">
            </div>     
          </div>
          <div class="wizard-step" data-step="59">
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
            
          </div>
          <div class="wizard-step" data-step="60">
            @php
              $utilitiseCommercial = [
                ['name' => 'Electrical Nearby', 'target' => ''],
                ['name' => 'Electricity Available', 'target' => ''],
                ['name' => 'Emergency Power', 'target' => ''],
                ['name' => 'Natural Gas Available', 'target' => ''],
                ['name' => 'Phone Available', 'target' => ''],
                ['name' => 'Private', 'target' => ''],
                ['name' => 'Public', 'target' => ''],
                ['name' => 'Sewer Nearby', 'target' => ''],
                ['name' => 'Solar', 'target' => ''],
                ['name' => 'Telephone Nearby', 'target' => ''],
                ['name' => 'Underground Utilities', 'target' => ''],
                ['name' => 'Water Connected', 'target' => ''],
                ['name' => 'Water Nearby', 'target' => ''],
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
                <input type="text" name="otherUtilitiseCom" id="legal_description" class="form-control has-icon"
                  data-icon="fa-regular fa-circle-check">
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
                <input type="text" name="otherWaterCom" id="legal_description" class="form-control has-icon"
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>

            @php
              $sewerCommercial = [['name' => 'Aerobic Septic', 'target' => ''],['name' => 'PEP-Holding Tank', 'target' => ''], ['name' => 'Private Sewer', 'target' => ''], ['name' => 'Public Sewer', 'target' => ''], ['name' => 'Septic Needed', 'target' => ''], ['name' => 'Septic Tank', 'target' => ''], ['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherSewerCommercial']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Sewer:</label>
              <select class="grid-picker" name="sewer[]" id="sewer" style="justify-content: flex-start;">
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
                <input type="text" name="otherSewerCom"  class="form-control has-icon"
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="61">
            @php
              $air_conditioning = [['name' => 'A/C Office Only', 'target' => ''], ['name' => 'Central Air', 'target' => ''], ['name' => 'Humidity Control', 'target' => ''], ['name' => 'Mini-Split Unit(s)', 'target' => ''],  ['name' => 'Wall/Window Unit(s)', 'target' => ''], ['name' => 'Zoned', 'target' => ''],['name' => 'None', 'target' => ''], ['name' => 'Other', 'target' => '.otherAirConditionCom']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Air Conditioning:</label>
              <select class="grid-picker" name="air_conditioning_com" id="air_conditioning"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($air_conditioning as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    data-icon="<i class='fa-regular fa-circle-check'></i>" class="card flex-row"
                    style="width:calc(33.3% - 10px);">
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group otherAirConditionCom d-none">
                <label class="fw-bold">Air Conditioning:</label>
                <input type="text" name="otherAirConditionCom"  class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
            @php
              $heating_and_fuel = [
                ['name' => 'Baseboard', 'target' => ''], 
                ['name' => 'Central', 'target' => ''], 
                ['name' => 'Central Building', 'target' => ''], 
                ['name' => 'Central Individual', 'target' => ''], 
                ['name' => 'Electric', 'target' => ''], 
                ['name' => 'Exhaust Fans', 'target' => ''], 
                ['name' => 'Gas', 'target' => ''], 
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
                ['name' => 'Other', 'target' => '.otherHeatingFuelCom']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Heating and Fuel:</label>
              <select class="grid-picker" name="heating_and_fuel[]" id="heating_and_fuel"
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
              <div class="form-group otherHeatingFuelCom d-none">
                <input type="text" name="otherHeatingFuelCom"  class="form-control has-icon"
                    data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="62">
            <h4>Water and Dock Information:</h4>
            <div class="form-group ">
              <label class="fw-bold">Water View:</label>
              <select class="grid-picker" name="has_water_view_com" id="has_water_view"
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
              <select class="grid-picker" name="has_water_extra_com" id="has_water_extra"
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
              <select class="grid-picker" name="has_water_fontage_com" id="has_water_fontage"
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
              $water_frontage = [
                ['name' => 'Bay/Harbor', 'target' => ''], 
                ['name' => 'Bayou', 'target' => ''], 
                ['name' => 'Beach', 'target' => ''], 
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
                ['name' => 'Marina', 'target' => ''], 
                ['name' => 'Pond', 'target' => ''], 
                ['name' => 'River', 'target' => ''], 
                ['name' => 'Canal Front', 'target' => ''],
                ['name' => 'Riparian Rights', 'target' => '']];

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
              <select class="grid-picker" name="has_water_access_com" id="has_water_access"
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
            <div class="form-group">
              <label class="fw-bold">Dock:</label>
              <select class="grid-picker" name="has_dock_com" id="has_dock"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  @php
                    if ($item['name'] == 'Yes') {
                        $target = '.dock_commercial_business';
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
                ['name' => 'Dock w/Water Supply', 'target' => ''],
                ['name' => 'Dock w/o Water Supply', 'target' => ''],
                ['name' => 'Dry Dock', 'target' => ''],
                ['name' => 'Fish Cleaning Station', 'target' => ''],
                ['name' => 'Floating Dock', 'target' => ''],
                ['name' => 'Harbormaster', 'target' => ''],
                ['name' => 'Internet', 'target' => ''],
                ['name' => 'Lift', 'target' => ''],
                ['name' => 'Restroom/Shower', 'target' => ''],
                ['name' => 'Wet Dock', 'target' => ''],
                ['name' => 'None', 'target' => ''],
                ['name' => 'Other', 'target' => '.other-dock-com'],
            ];
            @endphp
            <div class="form-group dock_commercial_business d-none ">
              <label class="fw-bold">Dock Description:</label>
              <select class="grid-picker" name="dock[]" id="dock"
                style="justify-content: flex-start;" multiple>
                <option value="">Select</option>
                @foreach ($dock as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                    style="width:calc(33.3% - 10px);" required>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group other-dock-com d-none">
                <label class="fw-bold">Dock Description:</label>
                <input type="text" name="custom_dock" id="custom_dock"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Lift Capacity:</label>
                <input type="text" name="dock_lift_capacity" id="dock_lift_capacity"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Year Built:</label>
                <input type="text" name="dock_year_built" id="dock_year_built"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Dimension:</label>
                <input type="text" name="dock_dimension" id="dock_dimension"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Maintenance Fee:</label>
                <input type="text" name="dock_maintenance_fee" id="dock_maintenance_fee"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              @php
                $feeFrequency = [['name' => 'Annual', 'target' => ''], ['name' => 'Monthly', 'target' => ''], ['name' => 'Quarterly', 'target' => ''], ['name' => 'N/A', 'target' => '']]
              @endphp
              <label class="fw-bold">Dock Maintenance Fee Frequency:</label>
              <select class="grid-picker" name="dock_maintenance_fee_frequency" id="dock_maintenance_fee_frequency"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($feeFrequency as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                    style="width:calc(33.3% - 10px);">
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="wizard-step" data-step="63">
            <h4>Ownership and Occupant Type:</h4>
            @php
              $ownerships = [
                ['name' => 'Condominium', 'target' => ''], 
                ['name' => 'Corporation', 'target' => ''], 
                ['name' => 'Franchise', 'target' => ''], 
                ['name' => 'Leasehold', 'target' => ''], 
                ['name' => 'Partnership', 'target' => ''], 
                ['name' => 'Sole Proprietor', 'target' => ''], 
                ['name' => 'Other', 'target' => '']
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Ownership:</label>
              <select class="grid-picker" name="ownership_com" id="ownership"
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
                data-icon="fa-regular fa-circle-check">
            </div>
            @php
              $occupantCommercial = [['name' => 'Owner', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Tenant', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Vacant', 'target' => '', 'icon' => 'fa-regular fa-circle-check']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Occupant Type:</label>
              <select class="grid-picker" name="occupant_type_com" id="occupant_type"
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
                  <select class="grid-picker" name="exiting_lease_or_tenant_com" id="exiting_lease_or_tenant"
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
                    <input type="date" name="end_of_lease_date_com" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                  </div>
                  <div class="form-group monthToMonthCommercial d-none">
                    <label for="address" class="fw-bold">What is the required notice period for the tenant to vacate the property?</label>
                    <input type="text" name="monthToMonth_com" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                  </div>
                </div>
                <div class="form-group">
                  <label class="fw-bold">Monthly Rental Amount:</label>
                  <input type="number" name="monthly_rental_ammount_com" id="monthly_rental_ammount" 
                    class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Days Notice to Tenant if not Renewing:</label>
                  <input type="text" name="days_notice_to_terminate_com" id="days_notice_to_terminate"
                     class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
                </div>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="64"></div>
          <div class="wizard-step" data-step="65">
            <h4>Financial Information:</h4>
            @php
              $acutual_or_projected = [['name' => 'Actual', 'target' => '', 'icon' => 'fa-regular fa-circle-check'], ['name' => 'Projected', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Operating Expenses:</label>
              <input type="text" name="operating_expenses" id="operating_expenses"
                class="form-control has-icon" data-icon="fa-solid fa-dollar-sign ">
            </div>
            <div class="form-group">
              <label class="fw-bold">Net Operating Income:</label>
              <input type="text" name="net_operating_income" id="net_operating_income"
                class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
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
                data-icon="fa-solid fa-dollar-sign" >
            </div>
            <div class="form-group">
              <label class="fw-bold">Annual TTL Schedule Income:</label>
              <input type="text" name="annual_ttl_schedule_income" id="annual_ttl_schedule_income"
                class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
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
                    data-icon="fa-regular fa-circle-check">
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="fw-bold">Number of Tenants:</label>
              <input type="text" name="number_of_tenants" class="form-control has-icon"
                data-icon="fa-solid fa-person">
            </div>
          </div>
          <div class="wizard-step" data-step="66">
            <h4>Space:</h4>
            @php
              $space_type = [['name' => 'New', 'target' => ''], ['name' => 'Re Let', 'target' => ''], ['name' => 'Sub Let', 'target' => '']];
            @endphp
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
                class="form-control has-icon" data-icon="fa-solid fa-restroom">
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
          <div class="wizard-step" data-step="67">
            <h4>Condo Environment:</h4>
            <div class="form-group">
              <label class="fw-bold"> Is the property in a condo environment?</label>
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
                <label class="fw-bold">Condo Fee:</label>
                <input type="text" name="condoFeeAmount_com" id="condo_fee" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
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
                $community_features = [
                  ['name' => 'Activity Core/Center', 'target' => ''], 
                  ['name' => 'Airport/Runway', 'target' => ''], 
                  ['name' => 'Beach Area', 'target' => ''], 
                  ['name' => 'Curbs', 'target' => ''], 
                  ['name' => 'Expressway', 'target' => ''], 
                  ['name' => 'Sidewalk', 'target' => ''], 
                  ['name' => 'Stream Seasonal', 'target' => '']];
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
            <label class="fw-bold">Pets Allowed:</label>
            <select class="grid-picker" name="ptes_Allowed_vac" id="has_rental_restrictions"
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
          <div class="wizard-step" data-step="68">
            <div class="form-group">
              <label class="fw-bold"> Description:</label>
              <textarea name="descriptionCom" id="description" class="form-control" cols="30" rows="10"></textarea>
            </div>
            <div class="form-group">
              <label class="fw-bold">Legal Disclamers:</label>
              <input type="text" name="disclamer_com" id="keywords" class="form-control has-icon"
                data-icon="fa-solid fa-tag">
            </div>
            <div class="form-group">
              <label class="fw-bold">Driving Directions:</label>
              <input type="text" name="driving_directions_com" id="keywords" class="form-control has-icon"
                data-icon="fa-solid fa-tag">
            </div>
            <div class="form-group">
              @php
                $sellerCompCommercial = [['name' => 'Yes', 'target' => '','target'=>'.sellerComYesCommercial','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'],['name' => 'Negotiable', 'target' => '','target'=>'','icon'=>'<i class="fa-regular fa-circle-check"></i>']];
              @endphp
              <label class="fw-bold">Is the seller offering compensation for a buyer’s agent?</label>
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
                <div class="d-flex justify-content-between aalign-items-center">
                  <label class="fw-bold">Buyer’s Agent Compensation:</label>
                  <div
                      class="d-flex align-items-center justify-content-center icon-select-btn-div">
                      <button type="button" class="select-btn me-1 active"
                          data-type="percent">%</button>
                      <button type="button" class="select-btn" data-type="amount">$</button>
                  </div>
                </div>
                <input type="text" name="compensation_amount_com" class="form-control has-icon"
                  data-icon="fa-solid fa-percent">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="69">
            <div class="form-group">
              <label class="fw-bold">Is the Seller actively seeking to purchase another property?
              </label>
              <select class="grid-picker" name="looking_other_property_com" id="looking_other_property"
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
              <input type="text" name="listing_link" id="listing_link" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-link">
            </div>
          </div>
          <div class="wizard-step" data-step="70">
            <h4>Title Company Information:</h4>
            <div class="form-group">
              <label class="fw-bold">Name:</label>
              <input type="text" name="title_company_name_com" id="title_company_name" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-user">
            </div>
            <div class="form-group">
              <label class="fw-bold">Address:</label>
              <input type="text" name="title_company_address_com" id="title_company_address" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-location-dot">
            </div>
            <div class="form-group">
              <label class="fw-bold">Phone Number:</label>
              <input type="text" name="title_company_phone_com" id="title_company_phone" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-phone">
            </div>

            <div class="form-group">
              <label class="fw-bold">Email:</label>
              <input type="text" name="title_company_email_com" id="titl_company_email" placeholder=""
                value="{{ @$auction->get->title_company_email }}" data-icon="fa-solid fa-envelope"
                class="form-control has-icon">
            </div>
          </div>
          <div class="wizard-step" data-step="71">
            @if (auth()->user()->user_type == 'agent')
              <h4>Listing Agent Information:</h4>
            @else
              <h4>Seller’s Information:</h4>
            @endif
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">First Name:</label>
                <input type="text"  name="agent_first_name_com" id="first_name" placeholder=""
                  value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                  data-icon="fa-solid fa-user" required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Last Name:</label>
                <input type="text" name="agent_last_name_com" id="last_name" placeholder=""
                  value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                  data-icon="fa-solid fa-user" required>
              </div>
            </div>
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">Phone Number:</label>
                <input type="text" name="agent_phone_com" id="agent_phone" placeholder=""
                  value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                  data-icon="fa-solid fa-phone" required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Email:</label>
                <input type="text" name="agent_email_com" id="agent_email" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-envelope"
                  value="{{ Auth::user()->email }}" required>
              </div>
            </div>
            @if (auth()->user()->user_type == 'user')
              <div class="form-group col-md-6">
                <label class="fw-bold">Listed By Owner</label>
              </div>
            @endif
            @if (auth()->user()->user_type == 'agent')
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">Brokerage:</label>
                <input type="text" name="agent_brokerage_com" id="agent_brokerage" placeholder=""
                  value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                  data-icon="fa-solid fa-handshake" required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Real Estate License #:</label>
                <input type="text" name="agent_license_no_com" id="agent_license_no" placeholder=""
                  value="{{ optional(Auth::user())->license_no }}" class="form-control has-icon"
                  data-icon="fa-solid fa-id-card" required>
              </div>
            </div>
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">NAR Member ID (NRDS ID):</label>
                <input type="number" name="agent_mls_id_com" id="agent_mls_id" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-id-card-clip"
                  value="{{ optional(Auth::user())->mls_id }}" required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Listed By: Real Estate Agent:</label>
                {{-- <input type="number" name="agent_mls_id" id="agent_mls_id" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                  value="{{ optional(Auth::user())->mls_id }}"> --}}
              </div>
            </div>
            @endif
          </div>
          <div class="wizard-step" data-step="72">
            <div class="form-group">
              <label class="fw-bold">3D Tour (Link):</label>
              <input type="text" name="three_d_tour" id="three_d_tour" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-link">
              </div>
              <div class="form-group">
                <label class="fw-bold">Floor Plan:</label>
                <input type="file" name="visible_note" id="visible_note" class="form-control">
              </div>
              <div class="form-group">
                <label class="fw-bold">Addendums/Disclosures:</label>
                <input type="file" name="visible_upload_file[]" id="upload_file" placeholder=""
                class="form-control documents-input" multiple>
              </div>
              <span class="commercialFields">
              <div class="row">
                <div class="col-6 video_div">
                  <input type="hidden" name="video_type" class="video_type" value="video_upload">
                  <div class="video_type_select d-flex align-items-center justify-content-left">
                      <div class="form-check me-2">
                          <label class="form-check-label">
                              <input type="checkbox" class="video_upload form-check-input video_type_check" name="video_upload" >
                              Video Upload
                          </label>
                      </div>
                      <div class="form-check me-2">
                          <label class="form-check-label">
                              <input type="checkbox" class="youtube_video form-check-input video_type_check" name="youtube_video">
                              Youtube Video
                          </label>
                      </div>
                      <div class="form-check">
                          <label class="form-check-label">
                              <input type="checkbox" class="vimeo_video form-check-input video_type_check" name="vimeo_video">
                              Vimeo Video
                          </label>
                      </div>
                  </div>
                  <div class="video-upload video-type-element">
                      <label class="fw-bold mt-1"> Property Video:</label>
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
                  <div class="youtube-video video-type-element form-group d-none">
                      <label class="fw-bold mt-1"> Youtube Video:</label>
                      <input type="text" name="youtube_video_link" class="form-control" placeholder="Youtube video link i.e. https://youtube.com/embed/videoId">
                  </div>
                  <div class="vimeo-video video-type-element form-group d-none">
                      <label class="fw-bold mt-1">Vimeo Video:</label>
                      <input type="text" name="vimeo_video_link" class="form-control" placeholder="Vimeo video link i.e. https://player.vimeo.com/video/videoId">
                  </div>
              </div>
                <div class="col-6">
                    <div class="upload form-group">
                        <label class="fw-bold">Property Photos:</label>
                        <div class="wrapper">
                          <div class="box">
                            <div class="js--image-preview"></div>
                            <div class="upload-options">
                              <label class="image-input-label">
                                <input type="file" name="photo[]" class="image-input" accept="image/*" multiple />
                              </label>
                            </div>
                            <div class="thumbnails-container"></div>
                            <div class="hidden-inputs-container"></div>
                          </div>
                        </div>
                    </div>
                </div>
              </div>
            </span>
          </div>
          {{-- Commercial/Business End --}}
          {{-- Vacant Start --}}
          <div class="wizard-step" data-step="73">
            @php
              $front_exposures1 = [['name' => 'North', 'target' => ''], ['name' => 'East', 'target' => ''], ['name' => 'South', 'target' => ''], ['name' => 'West', 'target' => ''], ['name' => 'Southeast', 'target' => ''], ['name' => 'Northeast', 'target' => ''], ['name' => 'Southwest', 'target' => ''], ['name' => 'Northwest', 'target' => ''], ['name' => 'Undetermined', 'target' => '']];
            @endphp
            <div class="form-group  ">
              <label class="fw-bold">Front Exposure:</label>
              <select class="grid-picker" name="front_exposure_vac" onchange="changeFrontExposure(this.value);"
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
          <div class="wizard-step" data-step="74">
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
                <input type="text" name="otherLotFeatureVac" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="75">
            @php
              $otherStructures = [
                  ['name' => 'Barn(s)', 'target' => ''],
                  ['name' => 'Billboard', 'target' => ''],
                  ['name' => 'Corral(s)', 'target' => ''],
                  ['name' => 'Finished RV Port', 'target' => ''],
                  ['name' => 'Greenhouse', 'target' => ''],
                  ['name' => 'Utility', 'target' => ''],
                  ['name' => 'Workshop', 'target' => ''],
                  ['name' => 'Other', 'target' => '.otherStructuresVac'],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Other Structures:</label>
              <select class="grid-picker" name="other_structures[]" id="lot_features"
                style="justify-content: flex-start;" multiple>
                <option value="">Select</option>
                @foreach ($otherStructures as $structure)
                  <option value="{{ $structure['name'] }}" data-target="{{ $structure['target'] }}"
                    class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                    style="width:calc(33.3% - 10px);">
                    {{ $structure['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group otherStructuresVac d-none">
                <label class="fw-bold">Other Structures::</label>
                <input type="text" name="custom_other_structures" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="76">
            <div class="form-group">
              <label class="fw-bold">Current Adjacent Use:</label>
              @php
              $current_adjacent_use = [
                ['name' => 'Church', 'target' => ''], 
                ['name' => 'Commercial', 'target' => ''], 
                ['name' => 'Industrial', 'target' => ''], 
                ['name' => 'Mobile Home Park', 'target' => ''], 
                ['name' => 'Multi-Family', 'target' => ''], 
                ['name' => 'Park', 'target' => ''], 
                ['name' => 'Professional Office', 'target' => ''], 
                ['name' => 'Residential', 'target' => ''], 
                ['name' => 'Retail', 'target' => ''], 
                ['name' => 'School', 'target' => ''], 
                ['name' => 'Vacant', 'target' => '']];
              @endphp
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
          <div class="wizard-step" data-step="77">
            @php
              $road_frontages = [
                ['name' => 'Access Road', 'target' => ''], 
                ['name' => 'Alley', 'target' => ''], 
                ['name' => 'Business District', 'target' => ''], 
                ['name' => 'City Street', 'target' => ''], 
                ['name' => 'County Road', 'target' => ''], 
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
                ['name' => 'Other', 'target' => '.otherFrontageVacant']];
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
                <input type="text" name="otherFrontage" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="78">
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
                ['name' => 'Other', 'target' => '.otherSurfaceVacant']];
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
                <input type="text" name="otherSurfaceVac" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="79">
            @php
              $utilitiseRes = [
                ['name' => 'BB/HS Internet Available', 'target' => ''],
                ['name' => 'BB/HS Internet Connected', 'target' => ''],
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
                ['name' => 'Water Nearby', 'target' => ''],
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
                <input type="text" name="otherUtilitiseVac" id="legal_description" class="form-control has-icon"
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>
            @php
              $waterVacant = [
                ['name' => 'Canal/Lake For Irrigation', 'target' => ''], 
                ['name' => 'Private', 'target' => ''], 
                ['name' => 'Public', 'target' => ''], 
                ['name' => 'Well', 'target' => ''],
                ['name' => 'None', 'target' => ''], 
                ['name' => 'Other', 'target' => '.otherWaterVacant']];
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
                <input type="text" name="otherWaterVac" id="legal_description" class="form-control has-icon"
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>
            @php
              $sewerVacant = [
                ['name' => 'PEP-Holding Tank', 'target' => ''], 
                ['name' => 'Private Sewer', 'target' => ''], 
                ['name' => 'Public Sewer', 'target' => ''], 
                ['name' => 'Septic Needed', 'target' => ''], 
                ['name' => 'Septic Tank', 'target' => ''], 
                ['name' => 'None', 'target' => ''], 
                ['name' => 'Other', 'target' => '.otherSewerVacant']];
            @endphp
            <div class="form-group ">
              <label class="fw-bold">Sewer:</label>
              <select class="grid-picker" name="sewer[]" id="sewer" style="justify-content: flex-start;">
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
                <input type="text" name="otherSewerVac"  class="form-control has-icon"
                  data-icon="fa-regular fa-circle-check">
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="80">
            <h4>Land and Tax Information:</h4>
            <div class="form-group">
              <label class="fw-bold">Tax ID (Parcel Number):</label>
              <input type="text" name="tax_id_vac" id="tax_id" class="form-control has-icon"
                data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold">Tax Year:</label>
              <input type="text" name="tax_year_vac" id="tax_year" class="form-control has-icon"
                data-icon="fa-regular fa-calendar-days" >
            </div>
            <div class="form-group">
              <label class="fw-bold">Taxes (Annual Amount):</label>
              <input type="text" name="taxes_annual_amount_vac" id="taxes_annual_ammount"
                class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
            </div>
            <div class="form-group ">
              @php
              $additialParcelVacant = 
              [
                ['name'=>'Yes','target'=>'.additialParcelVacantYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'],
                ['name'=>'No','target'=>'','icon'=>'<i class="fa-regular fa-circle-xmark"></i>']
              ];
              @endphp
              <label class="fw-bold">Additional Parcels:</label>
              <select class="grid-picker" name="additionalParcelsVac" id="sewer"
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
                  <label class="fw-bold">Additional Tax ID's:</label>
                  <input type="text" name="additional_tax_id_vac" id="additional_tax_id" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="fw-bold">Total Number of Parcels:</label>
              <input type="number" name="total_number_of_parcels_vac" id="total_number_of_parcels"
                class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold">Zoning:</label>
              <input type="text" name="zoning_vac" id="zoning"
                class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold">Legal Description:</label>
              <input type="text" name="legal_description_vac" id="legal_description" class="form-control has-icon"
                data-icon="fa-solid fa-tag">
            </div>
            <div class="form-group">
              <label class="fw-bold" for="year_built">Legal Subdivison Name:</label>
              <input type="text" name="legal_subdivison_name_vac" id="legal_subdivison_name"
                class="form-control has-icon " data-icon="fa-solid fa-ruler-combined"
                >
            </div>
            @php
              $lot_sizes = [['name' => '0 to less than 1/4', 'target' => ''], ['name' => '1/4 to less than 1/2', 'target' => ''], ['name' => '1/2 to less than 1', 'target' => ''], ['name' => '1 to less than 2', 'target' => ''], ['name' => '2 to less than 5', 'target' => ''], ['name' => '5 to less than 10', 'target' => ''], ['name' => '10 to less than 20', 'target' => ''], ['name' => '20 to less than 50', 'target' => ''], ['name' => '50 to less than 100', 'target' => ''], ['name' => '100 to less than 200', 'target' => ''], ['name' => '200 to less than 500', 'target' => ''], ['name' => '500+ Acers', 'target' => ''], ['name' => 'Non-Applicable', 'target' => '']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Total Acreage:</label>
              <select class="grid-picker" name="total_aceage_vac" id="lot_size" style="justify-content: flex-start;">
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
            <div class="form-group has_flood_zoon d-none">
              <label class="fw-bold">Flood Zone Code:</label>
              <input type="text" name="flood_zone_code_vac" id="flood_zone_code" class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold" for="lot_size">Lot Size Square Footage:</label>
              <input type="text" name="lot_size_vac" id="lot_size" class="form-control has-icon "
                data-icon="fa-solid fa-ruler-combined" data-msg-required="Please enter Lot Size">
            </div>
            <div class="form-group">
              <label class="fw-bold">Lot Size Acres:</label>
              <input type="text" name="lot_size_acres_vac" id="lot_size_acres" class="form-control has-icon"
                data-icon="fa-solid fa-ruler-combined">
            </div>
            <div class="form-group">
              <label class="fw-bold">Lot Dimensions:</label>
              <input type="text" name="lot_dimensions" id="lot_dimensions" class="form-control has-icon"
                data-icon="fa-solid fa-ruler-combined">
            </div>
          </div>
          <div class="wizard-step" data-step="81">
            <h4>Water and Dock Information:</h4>
            <div class="form-group ">
              <label class="fw-bold">Water View:</label>
              <select class="grid-picker" name="has_water_view_vac" id="has_water_view"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  @php
                    if ($item['name'] == 'Yes') {
                        $target = '.water_view_vacant';
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
            <div class="form-group water_view_vacant d-none">
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
              <select class="grid-picker" name="has_water_extra_vac" id="has_water_extra"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  @php
                    if ($item['name'] == 'Yes') {
                        $target = '.water_extras_vacant';
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
            <div class="form-group water_extras_vacant d-none ">
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
              <select class="grid-picker" name="has_water_fontage_vac" id="has_water_fontage"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  @php
                    if ($item['name'] == 'Yes') {
                        $target = '.water_frontage_vacant';
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
              $water_frontage = [
                ['name' => 'Bay/Harbor', 'target' => ''], 
                ['name' => 'Bayou', 'target' => ''], 
                ['name' => 'Beach', 'target' => ''], 
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
                ['name' => 'Marina', 'target' => ''], 
                ['name' => 'Pond', 'target' => ''], 
                ['name' => 'River', 'target' => ''], 
                ['name' => 'Canal Front', 'target' => ''],
                ['name' => 'Riparian Rights', 'target' => '']];

            @endphp
            <div class="form-group water_frontage_vacant d-none">
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
              <select class="grid-picker" name="has_water_access_vac" id="has_water_access"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  @php
                    if ($item['name'] == 'Yes') {
                        $target = '.water_access_vacant';
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
            <div class="form-group water_access_vacant">
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
            <div class="form-group">
              <label class="fw-bold">Dock:</label>
              <select class="grid-picker" name="has_dock_vac" id="has_dock"
                style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  @php
                    if ($item['name'] == 'Yes') {
                        $target = '.dock_vacant';
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
                ['name' => 'Dock w/Water Supply', 'target' => ''],
                ['name' => 'Dock w/o Water Supply', 'target' => ''],
                ['name' => 'Dry Dock', 'target' => ''],
                ['name' => 'Fish Cleaning Station', 'target' => ''],
                ['name' => 'Floating Dock', 'target' => ''],
                ['name' => 'Harbormaster', 'target' => ''],
                ['name' => 'Internet', 'target' => ''],
                ['name' => 'Lift', 'target' => ''],
                ['name' => 'Restroom/Shower', 'target' => ''],
                ['name' => 'Wet Dock', 'target' => ''],
                ['name' => 'None', 'target' => ''],
                ['name' => 'Other', 'target' => '.other-dock-vacant'],
            ];
            @endphp
            <div class="form-group dock_vacant d-none ">
              <label class="fw-bold">Dock Description:</label>
              <select class="grid-picker" name="dock[]" id="dock"
                style="justify-content: flex-start;" multiple>
                <option value="">Select</option>
                @foreach ($dock as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                    style="width:calc(33.3% - 10px);" required>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group other-dock-vacant d-none">
                <label class="fw-bold">Dock Description:</label>
                <input type="text" name="custom_dock" id="custom_dock"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Lift Capacity:</label>
                <input type="text" name="dock_lift_capacity" id="dock_lift_capacity"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Year Built:</label>
                <input type="text" name="dock_year_built" id="dock_year_built"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Dimension:</label>
                <input type="text" name="dock_dimension" id="dock_dimension"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              <div class="form-group">
                <label class="fw-bold">Dock Maintenance Fee:</label>
                <input type="text" name="dock_maintenance_fee" id="dock_maintenance_fee"
                  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
              @php
                $feeFrequency = [
                  ['name' => 'Annual', 'target' => ''], 
                  ['name' => 'Monthly', 'target' => ''], 
                  ['name' => 'Quarterly', 'target' => ''], 
                  ['name' => 'N/A', 'target' => '']]
              @endphp
              <label class="fw-bold">Dock Maintenance Fee Frequency:</label>
              <select class="grid-picker" name="dock_maintenance_fee_frequency" id="dock_maintenance_fee_frequency"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($feeFrequency as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}"
                    class="card flex-row" data-icon="<i class='fa-regular fa-circle-check'></i>"
                    style="width:calc(33.3% - 10px);">
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="wizard-step" data-step="82">
            <h4>Ownership Information:</h4>
            @php
              $ownerships = [
                ['name' => 'Co-op', 'target' => ''], 
                ['name' => 'Condominium', 'target' => ''], 
                ['name' => 'Fee Simple', 'target' => ''], 
                ['name' => 'Fractional', 'target' => ''], 
                ['name' => 'Other', 'target' => '']];
            @endphp
            <div class="form-group">
              <label class="fw-bold">Ownership:</label>
              <select class="grid-picker" name="ownership_vac" id="ownership"
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
                data-icon="fa-regular fa-circle-check" >
            </div>
          </div>
          <div class="wizard-step" data-step="83">
            <h4>HOA, Condo Association and/or Master Association Information:</h4>
            <div class="form-group">
              @php
                $propsOptVacant = [
                  ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.hoasVacant'],
                  ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
                ];
              @endphp
              <label class="fw-bold">Does the property have an HOA, condo association, master association, and/or community fee?</label>
              <select class="grid-picker" name="has_hoa_vac" id="has_hoa" style="justify-content: flex-start;">
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
                    ['name' => 'Activity Core/Center', 'target' => ''],
                    ['name' => 'Airport/Runway', 'target' => ''],
                    ['name' => 'Association Recreation - Lease', 'target' => ''],
                    ['name' => 'Association Recreation - Owned', 'target' => ''],
                    ['name' => 'Beach Area', 'target' => ''],
                    ['name' => 'Community Boat Ramp', 'target' => ''],
                    ['name' => 'Curbs', 'target' => ''],
                    ['name' => 'Deed Restrictions', 'target' => ''],
                    ['name' => 'Dog Park', 'target' => ''],
                    ['name' => 'Expressway', 'target' => ''],
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
                    ['name' => 'No Truck/RV/Motorcycle Parking', 'target' => ''],
                    ['name' => 'None', 'target' => ''],
                    ['name' => 'Park', 'target' => ''],
                    ['name' => 'Playground', 'target' => ''],
                    ['name' => 'Pool', 'target' => ''],
                    ['name' => 'Public Boat Ramp', 'target' => ''],
                    ['name' => 'Racquetball', 'target' => ''],
                    ['name' => 'Shopping Center', 'target' => ''],
                    ['name' => 'Sidewalk', 'target' => ''],
                    ['name' => 'Special Community Restrictions', 'target' => ''],
                    ['name' => 'Stream Seasonal', 'target' => ''],
                    ['name' => 'Tennis Courts', 'target' => ''],
                    ['name' => 'Water Access', 'target' => ''],
                    ['name' => 'Waterfront', 'target' => ''],
                    ['name' => 'Waterfront Complex', 'target' => '']
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
                  <input type="text" name="otherAssocAmenities_vac" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
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
                  <input type="text" name="otherFeeInclude_vac" class="form-control has-icon" data-icon="fa-regular fa-circle-check">
                </div>
              </div>
              <div class="form-group">
                <label class="fw-bold">Amenities with Additional Fees:</label>
                <input type="text" name="amenities_with_additional_fees_vac"
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
                <select class="grid-picker" name="has_cdd_vac" id="has_cdd" style="justify-content: flex-start;">
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
                <input type="text" name="annual_cdd_fee_vac" id="annual_cdd_fee" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar-sign ">
              </div>
              <div class="form-group">
                  @php
                  $landLeaseOptVacant = [
                      ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>'.landLeaseVacant'],
                      ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>'']
                    ];
                  @endphp
                <label class="fw-bold">Annual Land Lease Fee:</label>
                <select class="grid-picker" name="has_land_lease_vac" id="has_hoa"
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
                <input type="text" name="land_lease_fee_vac" id="land_lease_fee" class="form-control has-icon"
                  data-icon="fa-solid fa-dollar-sign ">
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
                    <select name="hoaFeeRequirements_vac" class="grid-picker" style="justify-content: flex-start;">
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
                  <input type="text" name="hoaFeeAmount_vac"  class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" >
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
                  <select name="paymentSchedules_vac" id="hoaPaymentSchedule" class="grid-picker">
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
                  <input type="text" name="condoFeeAmount_vac" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" >
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
                  <select name="condoPay_vac" id="condoPaymentSchedule" class="grid-picker">
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
                    <input type="text" name="masterAssociationFeeAmount_vac" id="masterAssociationFeeAmount" class="form-control has-icon " data-icon="fa-solid fa-dollar-sign" >
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
                    <select name="assocSchedule_vac" id="masterAssociationFeeSchedule_vac" class="grid-picker">
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
                    <input type="text" name="masterAssociationName_vac" id="masterAssociationName" class="form-control has-icon" data-icon="fa-solid fa-user">
                  </div>

                  <div class="form-group">
                    <label class="fw-bold">Master Association Contact Phone:</label>
                    <input type="text" name="masterAssociationContactPhone_vac" class="form-control has-icon" data-icon="fa-solid fa-phone">
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
                  <select name="additionalFees_vac" id="additionalFees" class="grid-picker">
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
                    <input type="text" name="additionalFeeReason" id="additionalFeeReason" class="form-control has-icon" data-icon="">
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Other Fee:</label>
                    <input type="text" name="otherFeeAmount_vac" id="otherFeeAmount" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign">
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
                  <input type="text" name="associationManagerContactName_vac"  class="form-control has-icon" data-icon="fa-solid fa-user">
                </div>

                <div class="form-group">
                  <label class="fw-bold">Association/Manager Contact Email:</label>
                  <input type="email" name="associationManagerContactEmail_vac"  class="form-control has-icon" data-icon="fa-solid fa-envelope">
                </div>

                <div class="form-group">
                  <label class="fw-bold">Association/Manager Contact Phone:</label>
                  <input type="text" name="associationManagerContactPhone_vac" class="form-control has-icon" data-icon="fa-solid fa-phone">
                </div>

                <div class="form-group">
                  <label class="fw-bold">Association/Manager Contact Website Address:</label>
                  <input type="text" name="associationManagerContactWebsite_vac"  class="form-control has-icon" data-icon="fa-regular fa-window-restore">
                </div>

                <div class="form-group">
                  @php
                    $olderPersonOptVacant = [
                      ['name'=>'Yes','icon'=>'<i class="fa-regular fa-circle-check"></i>','target'=>''],
                      ['name'=>'No','icon'=>'<i class="fa-regular fa-circle-xmark"></i>','target'=>''],
                          ];
                  @endphp
                  <label class="fw-bold">Housing for Older Persons:</label>
                  <select name="olderPersons_vac" id="housingForOlderPersons" class="grid-picker">
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
          <div class="wizard-step" data-step="84">
            <div class="form-group">
              <label class="fw-bold"> Description:</label>
              <textarea name="descriptionVac" id="description" class="form-control" cols="30" rows="10" required></textarea>
            </div>
            <div class="form-group">
              <label class="fw-bold">Legal Disclamers:</label>
              <textarea name="disclamer_vac" id="keywords" class="form-control" cols="30" rows="10" required></textarea>
            </div>
            <div class="form-group">
              <label class="fw-bold">Driving Directions:</label>
              <input type="text" name="driving_directions_vac" id="keywords" class="form-control has-icon"
                data-icon="fa-solid fa-car">
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
                  <div class="d-flex justify-content-between aalign-items-center">
                    <label class="fw-bold">Buyer’s Agent Compensation:</label>
                    <div
                        class="d-flex align-items-center justify-content-center icon-select-btn-div">
                        <button type="button" class="select-btn me-1 active"
                            data-type="percent">%</button>
                        <button type="button" class="select-btn" data-type="amount">$</button>
                    </div>
                  </div>
                  <input type="text" name="compensation_amount_vac" class="form-control has-icon"
                    data-icon="fa-solid fa-percent">
                </div>
              </div>
          </div>
          <div class="wizard-step" data-step="85">
            <div class="form-group">
              <label class="fw-bold">Is the Seller actively seeking to purchase another property?
              </label>
              <select class="grid-picker" name="looking_other_property_vac" id="looking_other_property"
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
              <input type="text" name="listing_link" id="listing_link" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-link">
            </div>
          </div>
          <div class="wizard-step" data-step="86">
            <h4>Title Company Information:</h4>
            <div class="form-group">
              <label class="fw-bold">Name:</label>
              <input type="text" name="title_company_name_vac" id="title_company_name" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-user">
            </div>
            <div class="form-group">
              <label class="fw-bold">Address:</label>
              <input type="text" name="title_company_address_vac" id="title_company_address" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-location-dot">
            </div>
            <div class="form-group">
              <label class="fw-bold">Phone Number:</label>
              <input type="text" name="title_company_phone_vac" id="title_company_phone" placeholder=""
                class="form-control has-icon" data-icon="fa-solid fa-phone">
            </div>

            <div class="form-group">
              <label class="fw-bold">Email:</label>
              <input type="text" name="title_company_email_vac" id="titl_company_email" placeholder=""
              data-icon="fa-solid fa-envelope" class="form-control has-icon">
            </div>
        
          </div>
          <div class="wizard-step" data-step="87">
            @if (auth()->user()->user_type == 'agent')
              <h4>Listing Agent Information:</h4>
            @else
              <h4>Seller’s Information:</h4>
            @endif
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">First Name:</label>
                <input type="text" name="agent_first_name_vac" id="first_name" placeholder=""
                  value="{{ Auth::user()->first_name }}" class="form-control has-icon"
                  data-icon="fa-solid fa-user" required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Last Name:</label>
                <input type="text" name="agent_last_name_vac" id="last_name" placeholder=""
                  value="{{ Auth::user()->last_name }}" class="form-control has-icon"
                  data-icon="fa-solid fa-user" required>
              </div>
            </div>
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">Phone Number:</label>
                <input type="text" name="agent_phone_vac" id="agent_phone" placeholder=""
                  value="{{ optional(Auth::user())->phone }}" class="form-control has-icon"
                  data-icon="fa-solid fa-phone" required>
              </div>
              <div class="form-group col-md-6 ">
                <label class="fw-bold">Email:</label>
                <input type="text" name="agent_email_vac" id="agent_email" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-envelope"
                  value="{{ Auth::user()->email }}" required>
              </div>
            </div>
            @if (auth()->user()->user_type == 'user')
            <div class="form-group  col-md-6">
              <label class="fw-bold">Listed By: Listed By Owner:</label>
            </div>
            @endif
            @if (auth()->user()->user_type == 'agent')
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">Brokerage:</label>
                <input type="text" name="agent_brokerage_vac" id="agent_brokerage" placeholder=""
                  value="{{ optional(Auth::user())->brokerage }}" class="form-control has-icon"
                  data-icon="fa-solid fa-handshake" required>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold">Real Estate License #:</label>
                <input type="text" name="agent_license_no_vac" id="agent_license_no" placeholder=""
                  value="{{ optional(Auth::user())->license_no }}" class="form-control has-icon"
                  data-icon="fa-solid fa-id-card" required>
              </div>
            </div>
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold">NAR Member ID (NRDS ID):</label>
                <input type="text" name="agent_mls_id_vac" id="agent_mls_id" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-id-badge"
                  value="{{ optional(Auth::user())->mls_id }}" required>
              </div>
              <div class="form-group  col-md-6">
                <label class="fw-bold">Listed By: Real Estate Agent:</label>
              </div>
            </div>
            @endif
          </div>
          <div class="wizard-step" data-step="88">
            <div class="form-group">
              <label class="fw-bold">3D Tour (Link):</label>
              <input type="text" name="three_d_tour" id="three_d_tour" placeholder=""
                  class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              <div class="form-group">
                <label class="fw-bold">Plot Plan:</label>
                <input type="file" name="floor_plan[]" id="floor_plan_1" class="form-control">
              </div>
              <div class="form-group">
                <label class="fw-bold">Addendums/Disclosures:</label>
                <input type="file" name="disclosures[]" id="upload_file" placeholder="" class="form-control documents-input"
                multiple>
              </div>
              <span class="vacantFields">
              <div class="row">
                <div class="col-6 video_div">
                  <input type="hidden" name="video_type" class="video_type" value="video_upload">
                  <div class="video_type_select d-flex align-items-center justify-content-left">
                      <div class="form-check me-2">
                          <label class="form-check-label">
                              <input type="checkbox" class="video_upload form-check-input video_type_check" name="video_upload" >
                              Video Upload
                          </label>
                      </div>
                      <div class="form-check me-2">
                          <label class="form-check-label">
                              <input type="checkbox" class="youtube_video form-check-input video_type_check" name="youtube_video">
                              Youtube Video
                          </label>
                      </div>
                      <div class="form-check">
                          <label class="form-check-label">
                              <input type="checkbox" class="vimeo_video form-check-input video_type_check" name="vimeo_video">
                              Vimeo Video
                          </label>
                      </div>
                  </div>
                  <div class="video-upload video-type-element">
                      <label class="fw-bold mt-1"> Property Video:</label>
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
                  <div class="youtube-video video-type-element form-group d-none">
                      <label class="fw-bold mt-1"> Youtube Video:</label>
                      <input type="text" name="youtube_video_link" class="form-control" placeholder="Youtube video link i.e. https://youtube.com/embed/videoId">
                  </div>
                  <div class="vimeo-video video-type-element form-group d-none">
                      <label class="fw-bold mt-1">Vimeo Video:</label>
                      <input type="text" name="vimeo_video_link" class="form-control" placeholder="Vimeo video link i.e. https://player.vimeo.com/video/videoId">
                  </div>
              </div>
                <div class="col-6">
                    <div class="upload form-group">
                        <label class="fw-bold">Property Photos:</label>
                        <div class="wrapper">
                          <div class="box">
                            <div class="js--image-preview"></div>
                            <div class="upload-options">
                              <label class="image-input-label">
                                <input type="file" name="photo[]" class="image-input" accept="image/*" multiple />
                              </label>
                            </div>
                            <div class="thumbnails-container"></div>
                            <div class="hidden-inputs-container"></div>
                          </div>
                        </div>
                    </div>
                </div>
              </div>
            </span>
          </div>
          {{-- property_listed="Yes" --}}
          <div class="wizard-step" data-step="89">
            <span>
              <h4>Price and Terms:</h4>
              <div class="form-group ">
                <label class="fw-bold" for="buy_now_price" required>Price:</label>
                <input type="number"  name="price" placeholder="" id="buy_now_price"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar-sign"
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
                      data-icon='<i class="fa-regular fa-circle-check"></i>' class="card flex-row"
                      style="width:calc(33.3% - 10px);">
                      {{ $item['name'] }}
                    </option>
                  @endforeach
                </select>
              </div>
                {{-- Other --}}
                <div class="form-group otherFinancing d-none">
                    <label class="fw-bold">Acceptable Currency/Financing:</label>
                    <input type="text" name="otherFinancing"  class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                </div>
                {{-- Other --}}
                {{-- NFT  --}}
                <div class="form-group nft d-none">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What type of Non-Fungible Token (NFT) will the seller accept?</label>
                      <input type="text" name="type_of_NFT_accepted" id="type_of_NFT_accepted" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                    <input type="text" name="cryptocurrency_type" id="cryptocurrency_type" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                      <input name="lease_option_terms" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                      <input name="lease_option_conditions" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                      <input type="number" name="desired_offering_price_lease_purchase" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
                  </div>
                  <div class="form-group col-md-12">
                      <label class="fw-bold">What specific terms does the seller propose for the lease purchase?</label>
                      <input name="lease_purchase_terms" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                      <input name="lease_purchase_conditions" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
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
                <div class="form-group assumable d-none" x-data="{ showDetails: false }">
                  <div class="form-group col-md-12">
                      <label class="fw-bold">Interested in Assumable Financing?</label>
                      <select name="assumable_interest" class="form-control has-icon" data-icon="fa-solid fa-circle-question" required @change="showDetails = ($event.target.value === 'Yes')">
                          <option value="">Select</option>
                          <option value="Yes">Yes</option>
                          <option value="No">No</option>
                      </select>
                  </div>
                  <div class="form-group col-md-12" x-show="showDetails">
                      <label class="fw-bold">Maximum Interest Rate You Would Accept: <small class="text-muted">(Optional)</small></label>
                      <div class="input-group">
                          <input type="text" name="assumable_max_interest_rate" class="form-control has-icon" data-icon="fa-solid fa-percent" placeholder="e.g., 5">
                          <span class="input-group-text">%</span>
                      </div>
                  </div>
                  <div class="form-group col-md-12" x-show="showDetails">
                      <label class="fw-bold">Maximum Monthly Payment (P&amp;I) You Would Accept: <small class="text-muted">(Optional)</small></label>
                      <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input type="text" name="assumable_max_monthly_payment" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="e.g., 2000">
                      </div>
                  </div>
                  <div class="form-group col-md-12" x-show="showDetails">
                      <label class="fw-bold">Cash Available to Bridge the Gap: <small class="text-muted">(Optional)</small></label>
                      <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input type="text" name="assumable_bridge_gap_cash" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" placeholder="e.g., 50000">
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
                    <input type="text" name="otherTrade" class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
                  </div>
                </div>
                <div class="form-group col-md-12">
                  <label class="fw-bold">What is the estimated value of the acceptable exchange/trade item? </label>
                  <input type="text" name="estimatedTrade" class="form-control has-icon" data-icon="fa-solid fa-dollar-sign" required>
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
                    data-icon="fa-regular fa-circle-check" required>
                </div>
              </div>
              {{-- Exchange/trade --}}
              <div class="form-group row">
                  @php
                    $sellerOffer = [['name' => 'Yes', 'target' => '.sellerOfferYes','icon'=>'<i class="fa-regular fa-circle-check"></i>'], ['name' => 'No', 'target' => '','icon'=>'<i class="fa-regular fa-circle-xmark"></i>'], ['name' => 'Only if the buyer meets the Seller’s Sell Terms, which include the List Price, acceptable Escrow seller offer a buyer at closing', 'target' => '.sellerOfferYes','icon'=>'<i class="fa-regular fa-circle-check"></i>']];
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
          <div class="wizard-step" data-step="90">
            <div class="form-group">
              @php
                $representation = [['name' => 'Yes', 'icon' => '<i class="fa-regular fa-circle-check"></i>', 'target' => '.buyers_agent_compensation'], ['name' => 'No', 'icon' => '<i class="fa-regular fa-xmark-circle"></i>', 'target' => ''], ['name' => 'Not Applicable', 'icon' => '<i class="fa-regular fa-xmark-circle"></i>', 'target' => '']];
              @endphp
              <label class="fw-bold">
                If the buyer is represented by an agent, will the seller offer the agent a commission?
              </label>
              <select name="agent_commission_offered" id="commission-offered" class="grid-picker" style="justify-content: flex-start;"
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
                <select class="grid-picker" name="agent_commission" id=""
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
                  <input type="text" name="agent_compensation" id=""
                      class="form-control has-icon" data-icon="fa-regular fa-circle-check" required>
              </div>
            </div>
          </div>
          <div class="wizard-step" data-step="91">
            <div class="form-group">
              <label class="fw-bold">Offer Expires:</label>
              <input type="date" name="offer_expiry" id=""
                      class="form-control has-icon" data-icon="fa-regular fa-clock" required>
            </div>
          </div>
          <div class="wizard-step" data-step="92">
            @if (auth()->user()->user_type == 'agent')
            <div class="form-group col-md-">
              <label class="fw-bold">First Name:</label>
              <input type="text" name="first_name" id="first_name" class="form-control" cols="30"
                rows="5" placeholder="First Name" required>
            </div>
            <div class="form-group">
              <label class="fw-bold">Last Name:</label>
              <input type="text" name="last_name" id="last_name" class="form-control" cols="30"
                rows="5" placeholder="Last Name" required>
            </div>
            <div class="form-group">
              <label class="fw-bold">Phone Number:</label>
              <input type="number" name="phone_number" id="phone_number" class="form-control" cols="30"
                rows="5" required>
            </div>
            <div class="form-group">
              <label class="fw-bold">Email:</label>
              <input type="email" name="email" id="email" class="form-control" cols="30"
                rows="5" placeholder="Email">
            </div>
            <div class="form-group">
              <label class="fw-bold">Brokerage:</label>
              <input type="text" name="brokerage" id="brokerage" class="form-control" cols="30"
                rows="5" placeholder="Brokerage">
            </div>
            <div class="form-group">
              <label class="fw-bold">Real Estate License #</label>
              <input type="text" name="license" id="license" class="form-control" cols="30"
                rows="5" placeholder="Real Estate License">
            </div>
            <div class="form-group">
              <label class="fw-bold">NAR Member ID (NRDS ID): </label>
              <input type="text" name="member_id" id="member_id" class="form-control" cols="30"
                rows="5" placeholder="NAR Member ID">
            </div>
            @elseif (auth()->user()->user_type == 'seller')
            <div class="form-group col-md-">
              <label class="fw-bold">First Name:</label>
              <input type="text" name="first_name" id="first_name" class="form-control" cols="30"
                rows="5" placeholder="First Name" required>
            </div>
            <div class="form-group">
              <label class="fw-bold">Last Name:</label>
              <input type="text" name="last_name" id="last_name" class="form-control" cols="30"
                rows="5" placeholder="Last Name">
            </div>
            <div class="form-group">
              <label class="fw-bold">Phone Number:</label>
              <input type="number" name="phone_number" id="phone_number" class="form-control" cols="30"
                rows="5" placeholder="Phone Number">
            </div>
            <div class="form-group">
              <label class="fw-bold">Email:</label>
              <input type="email" name="email" id="email" class="form-control" cols="30"
                rows="5" placeholder="Email">
            </div>
            @endif
          </div>
          {{-- property_listed="Yes" --}}

          {{-- New Steps: Property Description, Highlights, Why, Compromises, Timeline --}}
          <div class="wizard-step" data-step="94">
            <h5 class="mb-3 fw-bold"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Property Description</h5>
            <p class="text-muted mb-4">Describe the property you are offering to the buyer. Help them visualize the space and understand its key selling points.</p>
            <div class="form-group">
              <label class="fw-bold" for="property_description">Property Description: <small class="text-danger">*</small></label>
              <textarea name="property_description" id="property_description" class="form-control" rows="8" required
                placeholder="Describe the property — its layout, standout features, condition, and what makes it a great match for this buyer's criteria..."></textarea>
              <small class="text-muted">Required. Describe the property's layout, standout features, condition, and why it's a great match for this buyer.</small>
            </div>
          </div>

          <div class="wizard-step" data-step="95">
            <h5 class="mb-3 fw-bold"><i class="fa-solid fa-star me-2 text-warning"></i>Property Highlights</h5>
            <p class="text-muted mb-4">Select or describe the top features of your property that align with the buyer's criteria.</p>
            <div class="form-group">
              <label class="fw-bold">Select Highlights: <small class="text-muted">(Choose all that apply)</small></label>
              @php
                $propertyHighlights = [
                  'Move-in ready', 'Recently renovated', 'Updated kitchen', 'Updated bathrooms',
                  'Open floor plan', 'Natural light', 'Private backyard', 'Waterfront / water view',
                  'Corner lot', 'Cul-de-sac', 'New roof', 'New HVAC', 'Energy efficient',
                  'Solar panels', 'Smart home features', 'Gated community', 'Low HOA fees',
                  'No HOA', 'Pool', 'Garage / parking', 'Other',
                ];
              @endphp
              <select class="grid-picker" name="property_highlights[]" style="justify-content: flex-start;" multiple>
                <option value="">Select</option>
                @foreach ($propertyHighlights as $highlight)
                  <option value="{{ $highlight }}"
                    data-icon='<i class="fa-regular fa-star"></i>'
                    data-target="{{ $highlight === 'Other' ? '.highlightOther' : '' }}"
                    class="card flex-row" style="width:calc(33.3% - 10px);">
                    {{ $highlight }}
                  </option>
                @endforeach
              </select>
              <div class="form-group highlightOther d-none mt-2">
                <label class="fw-bold">Other Highlights:</label>
                <input type="text" name="property_highlights_other" class="form-control has-icon"
                  data-icon="fa-regular fa-star" placeholder="Describe additional highlights...">
              </div>
            </div>
          </div>

          <div class="wizard-step" data-step="96">
            <h5 class="mb-3 fw-bold"><i class="fa-solid fa-circle-check me-2 text-success"></i>Why This Property Matches</h5>
            <p class="text-muted mb-4">Explain how your property meets the buyer's stated criteria. Reference specific requirements they listed.</p>
            <div class="form-group">
              <label class="fw-bold">Why This Property Matches the Buyer's Criteria: <small class="text-muted">(Optional)</small></label>
              <textarea name="why_property_matches" id="why_property_matches" class="form-control" rows="8"
                placeholder="e.g., This property meets your need for 3+ bedrooms, is within your target zip code, and falls within your stated price range..."></textarea>
            </div>
          </div>

          <div class="wizard-step" data-step="97">
            <h5 class="mb-3 fw-bold"><i class="fa-solid fa-handshake me-2 text-info"></i>Compromises &amp; Concessions</h5>
            <p class="text-muted mb-4">Be transparent about any areas where the property may not perfectly align with the buyer's criteria, and note any concessions you are willing to make.</p>
            <div class="form-group">
              <label class="fw-bold">Potential Compromises or Gaps: <small class="text-muted">(Optional)</small></label>
              <textarea name="compromises_concessions" id="compromises_concessions" class="form-control" rows="6"
                placeholder="e.g., The property is 5 miles outside your preferred zip code, but it offers a larger lot size than requested. Seller is open to covering closing costs as a concession..."></textarea>
            </div>
          </div>

          <div class="wizard-step" data-step="98">
            <h5 class="mb-3 fw-bold"><i class="fa-solid fa-calendar-check me-2 text-primary"></i>Negotiation Timeline</h5>
            <p class="text-muted mb-4">Outline your preferred timeline for negotiation and closing.</p>
            <div class="form-group">
              <label class="fw-bold">Preferred Response Deadline: <small class="text-muted">(Optional)</small></label>
              <input type="date" name="negotiation_response_deadline" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
            </div>
            <div class="form-group">
              <label class="fw-bold">Preferred Closing Date: <small class="text-muted">(Optional)</small></label>
              <input type="date" name="negotiation_closing_date" class="form-control has-icon" data-icon="fa-regular fa-calendar-days">
            </div>
            <div class="form-group">
              <label class="fw-bold">Negotiation Notes: <small class="text-muted">(Optional)</small></label>
              <textarea name="negotiation_timeline" id="negotiation_timeline" class="form-control" rows="4"
                placeholder="Any additional notes about your preferred negotiation timeline, urgency, or flexibility..."></textarea>
            </div>
            <div class="mt-4 p-3 border border-primary rounded" style="background:#f0f4ff;">
              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                  <h6 class="mb-1 fw-bold text-primary"><i class="fa-solid fa-eye me-2"></i>Preview Your Offer</h6>
                  <p class="mb-0 text-muted small">Review how your offer will appear to the buyer before submitting.</p>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#offerPreviewModal">
                  <i class="fa-solid fa-eye me-1"></i> Preview
                </button>
              </div>
            </div>
          </div>
          {{-- /New Steps --}}

            <div class="d-flex justify-content-between form-group mt-4">
              <div>
                <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
              </div>
              <div>
                <a class="wizard-step-next btn btn-success btn-lg text-600" style="display: none;">Next</a>
                <button type="button" class="wizard-step-finish btn btn-primary btn-lg text-600"
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
          class="form-control has-icon search_places" data-icon="fa-solid fa-tree"></td>
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
  {{-- Offer Preview Modal --}}
  <div class="modal fade" id="offerPreviewModal" tabindex="-1" aria-labelledby="offerPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="offerPreviewModalLabel">
            <i class="fa-solid fa-eye me-2"></i>Offer Preview — Buyer View
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="offerPreviewBody">
          <p class="text-muted small mb-3"><i class="fa-solid fa-circle-info me-1"></i>This is a preview of how key details will appear to the buyer after submission. Some fields may be hidden or summarised on the final bid card.</p>
          <div id="previewContent"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="wizard-step-finish btn btn-primary">
            <i class="fa-solid fa-paper-plane me-1"></i>Submit Offer
          </button>
        </div>
      </div>
    </div>
  </div>

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
    // Video Preview
    $(document).ready(function($) {
      // Click button to activate hidden file input
      $('.fileuploader-btn').on('click', function() {
        $('.fileuploader').click();
      });

      // $('#property_listed').change(function(){
      //   if($(this).val() === "Yes"){
      //     $('#property-not-listed').hide();
      //     $('#property-listed').show();
      //   }else{
      //     $('#property-not-listed').show();
      //     $('#property-listed').hide();
      //   }
      // })
  
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
    $('#auction_type').change(function(){
        let v=$(this).val();
      if (v == "Auction (Timer)") {
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
              if($('#property_listed').val() === "No"){
                if (StepWizard.currentStep == 5 && property_type ==
                  'Vacant Land'
                ) {
                  StepWizard.nextStep = 73;
                  StepWizard.backStep = 5;
                } 
                // else if (StepWizard.currentStep == 34 && property_type ==
                //   'Residential Property') {
                //   StepWizard.nextStep = 36;
                // }  
                else if (StepWizard.currentStep == 5 && (property_type == 'Residential Property' ||
                    property_type ==
                    'Income Property')) {
                  StepWizard.nextStep = 6;
                  StepWizard.backStep = 5;
                }
                else if (StepWizard.currentStep == 6 && property_type == 'Income Property') {
                  StepWizard.nextStep = 9;
                  StepWizard.backStep = 6
                }
                else if (StepWizard.currentStep == 8 && property_type == 'Residential Property') {
                  StepWizard.nextStep = 10;
                  StepWizard.backStep = 8
                } else if (StepWizard.currentStep == 5 && (property_type == 'Commercial Property' || property_type == 'Business Opportunity')
                ) {
                  StepWizard.nextStep = 39;
                  StepWizard.backStep = 5;
                }  else if (StepWizard.currentStep == 39 && (property_type ==
                'Commercial Property' || property_type == 'Business Opportunity')
                ) {
                    StepWizard.nextStep = 41;
                    StepWizard.backStep = 39;
                  } 
                  else if (StepWizard.currentStep == 58 && (property_type ==
                      'Commercial Property' || property_type == 'Business Opportunity')

                  ) {
                    StepWizard.nextStep = 60;
                    StepWizard.backStep = 58;
                  }
                  else if (StepWizard.currentStep == 63 && (property_type ==
                      'Commercial Property' || property_type == 'Business Opportunity')

                  ){
                    StepWizard.nextStep = 65;
                    StepWizard.backStep = 63;
                  }
            }else{
              if (StepWizard.currentStep == 2) {
                StepWizard.nextStep = 89;
                StepWizard.backStep = 2;
              } 
            }


              $('[ data-step="' + StepWizard.nextStep + '"]').addClass("active");
              StepWizard.setStep();

              if($('#property_listed').val() === "Yes"){
                if (StepWizard.currentStep == 92) {
                  $('.wizard-step.active').removeClass('active');
                  $('[data-step="94"]').addClass('active');
                  StepWizard.setStep();
                }
              }

              if (StepWizard.currentStep == 38 &&
              (property_type == 'Residential Property' || property_type ==
                'Income Property')
            ) {
              $('.wizard-step.active').removeClass('active');
              $('[data-step="94"]').addClass('active');
              StepWizard.setStep();
            }
            if (StepWizard.currentStep == 72 &&
              (property_type == 'Commercial Property' || property_type ==
                'Business Opportunity')
            ) {
              $('.wizard-step.active').removeClass('active');
              $('[data-step="94"]').addClass('active');
              StepWizard.setStep();
            }
              if (StepWizard.currentStep == 92 &&
                (property_type == 'other')
              ) {
                $('.wizard-step.active').removeClass('active');
                $('[data-step="94"]').addClass('active');
                StepWizard.setStep();
              }

              // After every forward-navigation step, fix backStep for new steps 94-98
              // using data-step attribute directly (immune to DOM-index offset from duplicate step 7)
              var _fwdDs = parseInt($('.wizard-step.active').attr('data-step'));
              if (_fwdDs === 94) {
                if (property_type === 'Residential Property' || property_type === 'Income Property') {
                  StepWizard.backStep = 37;
                } else if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
                  StepWizard.backStep = 71;
                } else if (property_type === 'Vacant Land') {
                  StepWizard.backStep = 92;
                } else {
                  StepWizard.backStep = 91;
                }
              } else if (_fwdDs === 95) {
                StepWizard.backStep = 94;
              } else if (_fwdDs === 96) {
                StepWizard.backStep = 95;
              } else if (_fwdDs === 97) {
                StepWizard.backStep = 96;
              } else if (_fwdDs === 98) {
                StepWizard.backStep = 97;
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

            if($('#property_listed').val() === "Yes"){
                if (StepWizard.currentStep == 89) {
                  StepWizard.backStep = 2;
                }
              }

              // Use data-step directly to set backStep for new steps 94-98
              var _bkDs = parseInt($('.wizard-step.active').attr('data-step'));
              if (_bkDs === 94) {
                if (property_type === 'Residential Property' || property_type === 'Income Property') {
                  StepWizard.backStep = 37;
                } else if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
                  StepWizard.backStep = 71;
                } else if (property_type === 'Vacant Land') {
                  StepWizard.backStep = 92;
                } else {
                  StepWizard.backStep = 91;
                }
              } else if (_bkDs === 95) {
                StepWizard.backStep = 94;
              } else if (_bkDs === 96) {
                StepWizard.backStep = 95;
              } else if (_bkDs === 97) {
                StepWizard.backStep = 96;
              } else if (_bkDs === 98) {
                StepWizard.backStep = 97;
              }

              if (StepWizard.currentStep == 8 && property_type == 'Income Property') {
              StepWizard.backStep = 5
              StepWizard.nextStep = 8;
            }else if (StepWizard.currentStep == 9 && property_type == 'Residential Property') {
              StepWizard.backStep = 7
              StepWizard.nextStep = 9;
            }else if (StepWizard.currentStep == 39 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 5;
              StepWizard.nextStep = 39;
            }else if (StepWizard.currentStep == 41 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 39;
              StepWizard.nextStep = 41;
            }else if (StepWizard.currentStep == 60 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 58;
              StepWizard.nextStep = 60;
            }else if (StepWizard.currentStep == 65 && (property_type == 'Business Opportunity' || property_type == 'Commercial Property')){
              StepWizard.backStep = 63;
              StepWizard.nextStep = 65;
            } 
          
            else if (StepWizard.currentStep == 6 &&
              (property_type == 'Residential Property' || property_type == 'Income Property')
            ) {
              StepWizard.backStep = 5;
            } else if (StepWizard.currentStep == 77 && property_type ==
              'Vacant Land') {
              StepWizard.backStep = 5;
            }else if (StepWizard.currentStep == 92 && property_type ==
              'Other') {
              StepWizard.backStep = 2;
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
        console.log("++++")
        if($('#property_listed').val() === "Yes"){
          console.log(".....")
          if (StepWizard.currentStep >= 2 && StepWizard.currentStep <= 97) {
            comp = 20 + (((StepWizard.currentStep - 2) / (97 - 2)) * 80);
          }
        }else{
  
        if (property_type === 'Residential Property' || property_type === 'Income Property') {
          if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 44) {
            comp = 20 + (((StepWizard.currentStep - 5) / (42 - 5)) * 80);
          }
        }
        // else if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
        //   // Calculate progress for commercial and business opportunity steps (42 to 76)
        //   comp = 20 + (((StepWizard.currentStep - 7) / (75 - 7)) * 80);
        //   if (StepWizard.currentStep == 21) {
        //     comp = 20 + (((StepWizard.currentStep - 21) / (55 - 21)) * 80);
        //   }
        // }
        else if (property_type === 'Commercial Property' || property_type === 'Business Opportunity') {
          //   console.log(StepWizard.currentStep)
          if (StepWizard.currentStep >= 5 && StepWizard.currentStep <= 18) {
            comp = 20 + (((StepWizard.currentStep - 5) / (76 - 5)) * 80);
          }
          if (StepWizard.currentStep >= 18 && StepWizard.currentStep <= 21) {
            comp = 20 + (((StepWizard.currentStep - 5) / (76 - 5)) * 80);
  
          }
          if (StepWizard.currentStep >= 53 && StepWizard.currentStep <= 76) {
            comp = 20 + (((StepWizard.currentStep - 43) / (76 - 43)) * 80);
          }
        } else if (property_type === 'Vacant Land') {
          // Calculate progress for vacant land steps (77 to 91)
          comp = 20 + (((StepWizard.currentStep - 77) / (93 - 77)) * 80);
        } else {
  
          // Default progress calculation for other property types (steps 1 to 8)
          comp = ((StepWizard.currentStep - 1) / 8) * 20;
        }
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
  </script>
  <script>
    // google.maps.event.addDomListener(window, 'load', initialize);
    function initialize() {
        // Phase 0 item 1 (INV-12): the Maps SDK may never have loaded — no credential,
        // a rejected key, or a blocked referrer. Degrade silently instead of throwing
        // ReferenceError: google is not defined, which would break the rest of this handler.
        if (typeof google === 'undefined' || !google.maps) { return; }

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

    /* ── Buyer Criteria match badge logic ───────────────────────────────────── */
    /*
     * WHY a lightweight JS engine rather than reusing BuyerBidMatchScoreHelper (PHP):
     *
     * BuyerBidMatchScoreHelper is a server-side, post-submission scoring system that
     * runs against stored bid records and computes weighted scores across 16+ field groups
     * (commission structure, lease options, retainer fees, etc.). It requires Eloquent
     * models and DB access and is appropriate for the view-page score shown to buyers.
     *
     * updateCriteriaBadges() is a pre-submission, in-browser real-time feedback widget.
     * Its scope is limited to the 8 Checklist fields visible to the agent in the sidebar
     * panel (property type, price, beds, baths, sqft, city, county, state, financing).
     * It uses simple exact/gte/lte comparisons that are correct for these fields and
     * runs instantly on every input change without a server round-trip.
     *
     * Porting the full PHP scoring logic to JS or adding an AJAX endpoint for a live
     * badge widget would introduce latency, maintenance drift, and unnecessary complexity.
     * The two systems are complementary: JS badges guide the agent before submitting;
     * the PHP helper scores the bid after submission for the listing owner.
     * See: docs/audits/BUYER_CRITERIA_BID_FORM_FIELD_AUDIT.md §3
     */
    function updateCriteriaBadges() {
      $('#criteria-checklist-grid .criteria-row').each(function() {
        var $row       = $(this);
        var formField  = $row.data('form-field');
        var matchValue = String($row.data('match-value') || '').toLowerCase().trim();
        var matchType  = $row.data('match-type') || 'exact';
        var $badge     = $row.find('.js-criteria-badge');
        var $input     = $('[name="' + formField + '"]');
        if (!$input.length) return;
        var enteredVal = ($input.is('select') ? $input.find('option:selected').val() : $input.val()) || '';
        enteredVal = String(enteredVal).toLowerCase().trim();
        if (!enteredVal) {
          $badge.attr('class', 'criteria-match-badge pending js-criteria-badge').text('–');
          return;
        }
        var isMatch = false;
        if (matchType === 'gte') {
          isMatch = parseFloat(enteredVal) >= parseFloat(matchValue);
        } else if (matchType === 'lte') {
          isMatch = parseFloat(enteredVal) <= parseFloat(matchValue);
        } else {
          isMatch = enteredVal === matchValue || enteredVal.indexOf(matchValue) !== -1 || matchValue.indexOf(enteredVal) !== -1;
        }
        if (isMatch) {
          $badge.attr('class', 'criteria-match-badge match js-criteria-badge').html('✓ Match');
        } else {
          $badge.attr('class', 'criteria-match-badge no-match js-criteria-badge').html('✗ No match');
        }
      });
    }
    $(document).ready(function() {
      updateCriteriaBadges();
      $('form.mainform').on('change input', 'input, select, textarea', function() {
        updateCriteriaBadges();
      });
    });

    $('#offerPreviewModal').on('show.bs.modal', function () {
      var rows = '';
      function previewRow(label, val) {
        if (val && val.trim && val.trim() !== '') {
          rows += '<p class="d-flex justify-content-between small border-bottom pb-1 mb-1"><span class="text-muted">' + label + ':</span><span class="ms-2 fw-semibold text-end" style="max-width:60%">' + val + '</span></p>';
        }
      }
      function previewBlock(label, val) {
        if (val && val.trim && val.trim() !== '') {
          rows += '<div class="mb-3"><p class="fw-bold mb-1 small text-muted">' + label + '</p><div class="p-2 bg-light rounded small" style="white-space:pre-wrap">' + $('<span>').text(val).html() + '</div></div>';
        }
      }

      previewRow('Address', $('[name="address"]').val());
      previewRow('Property Type', $('[name="property_type"]').val() || $('[name="property_type"] option:selected').text());
      previewRow('Offer Price', $('[name="price"]').val() ? '$' + $('[name="price"]').val() : '');
      previewRow('Financing Type', $('[name="term_financings"]').val());
      previewRow('Closing Days', $('[name="closing_days2"]').val());
      previewRow('Escrow Amount', $('[name="escrow_amount2"]').val());
      previewRow('Bedrooms', $('[name="bedrooms"]').val());
      previewRow('Bathrooms', $('[name="bathrooms"]').val());
      previewRow('County', $('[name="county"]').val());
      previewRow('State', $('[name="state"]').val());
      previewRow('Possession Date', $('[name="possession_date"]').val());
      previewRow('Response Requested By', $('[name="response_requested_by"]').val());
      previewRow('Home Warranty Requested', $('[name="home_warranty_requested"] option:selected').val());
      previewRow('Seller Contribution Requested', $('[name="seller_contribution_requested"] option:selected').val());
      previewRow('Preferred Response Deadline', $('[name="negotiation_response_deadline"]').val());
      previewRow('Preferred Closing Date', $('[name="negotiation_closing_date"]').val());
      previewBlock('Property Description', $('[name="property_description"]').val());
      previewBlock('Included Personal Property', $('[name="included_personal_property"]').val());
      previewBlock('Excluded Items', $('[name="excluded_items"]').val());
      previewBlock('Custom Terms', $('[name="custom_terms"]').val());
      previewBlock('Why This Property Matches', $('[name="why_property_matches"]').val());
      previewBlock('Compromises & Concessions', $('[name="compromises_concessions"]').val());
      previewBlock('Negotiation Notes', $('[name="negotiation_timeline"]').val());

      var highlights = [];
      $('[name="property_highlights[]"] option:selected').each(function() {
        if ($(this).val()) highlights.push($(this).val());
      });
      if (highlights.length) {
        rows += '<p class="small border-bottom pb-1 mb-1"><span class="text-muted">Highlights:</span> ' + highlights.map(function(h){ return '<span class="badge bg-secondary ms-1">' + h + '</span>'; }).join('') + '</p>';
      }

      if (!rows) {
        rows = '<p class="text-muted">No details entered yet. Complete the form steps to see a preview here.</p>';
      }
      $('#previewContent').html(rows);
    });
  </script>
  <x-google-maps-script :callback="'initialize'" />
@endpush
