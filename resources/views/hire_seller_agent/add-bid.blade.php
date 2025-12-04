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
          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">What is the total commission the agent will charge the seller?</label>
              <input type="number" class="form-control has-icon" placeholder="" name="total_comission"
                data-icon="fa-solid fa-dollar-sign" id="custom_terms" required />
            </div>
            {{-- <div class="form-group">
              <label class="fw-bold">If a buyer is represented by an agent, how much of the commission will the agent
                share
                with the buyer’s agent? </label>
              <input type="text" class="form-control has-icon" placeholder="" name="buyerCommission"
                data-icon="fa-solid fa-ruler-combined" required />
            </div>
            <div class="form-group">
              @php
                $totalCommission = [['name' => 'Yes', 'target' => '.commYes', 'dataIcon' => '<i class="fa-regular fa-check-circle"></i>'], ['name' => 'No', 'target' => '.commNo', 'dataIcon' => '<i class="fa-regular fa-circle-xmark"></i>']];
              @endphp
              <label class="fw-bold">If the buyer is not represented by another agent, will the agent retain the total
                commission? </label>
              <select class="grid-picker" name="totalCommission" style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($totalCommission as $item)
                  <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                    style="width:calc(25% - 10px);" data-icon='{{ $item['dataIcon'] }}'>
                    {{ $item['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group commNo d-none">
                <label class="fw-bold">How much will the agent charge if the buyer is not represented by another agent?
                </label>
                <input type="text" class="form-control has-icon" name="otherCommission" data-icon="fa-solid fa-ruler-combined"
                  id="custom_terms" required />
              </div>
            </div> --}}
          </div>
          <div class="wizard-step">
            <div class="form-group">
              @php
                $yes_or_nos = [['name' => 'Yes', 'target' => '.charge_fee', 'dataIcon' => '<i class="fa-regular fa-check-circle"></i>'], ['name' => 'No', 'target' => '', 'dataIcon' => '<i class="fa-regular fa-circle-xmark"></i>']];
              @endphp
              <label class="fw-bold">Does the agent charge any fees if the seller terminates the listing agreement before
                the expiration date?</label>
              <select class="grid-picker" name="charges" id="charge_fee" style="justify-content: flex-start;">
                <option value="">Select</option>
                @foreach ($yes_or_nos as $listing_term)
                  <option value="{{ $listing_term['name'] }}" data-target="{{ $listing_term['target'] }}"
                    class="card flex-row" style="width:calc(33% - 10px);"
                    data-icon='<i class="fa-regular fa-check-circle"></i>'>
                    {{ $listing_term['name'] }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="form-group charge_fee d-none">
              <label class="fw-bold">What is the cancellation fee if the seller terminates the agent's listing agreement
                before the
                expiration date?</label>
              <input type="number" class="form-control has-icon" placeholder="" name="charge_fee"
                data-icon="fa-solid fa-dollar-sign" id="charge_fee" required />
            </div>
          </div>
          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">About Agent: <span class="text-danger"></span></label>
              <textarea class="form-control" name="bio" rows="5" required>{{ @$auth->get->bio }}</textarea>
            </div>
          </div>
          <div class="wizard-step">

            <div class="form-group">
              <label class="fw-bold"> Why should you be hired as their agent? <span class="text-danger"></span></label>
              <textarea class="form-control" name="why_hire_you" rows="5" required>{{ @$auth->get->hired_agent }}</textarea>
            </div>
            <div class="form-group">
              <label class="fw-bold">What sets you apart from other agents? <span class="text-danger"></span></label>
              <textarea class="form-control" name="what_sets_you_apart" rows="5" required>{{ @$auth->get->apart_agent }}</textarea>
            </div>
            <div class="form-group">
              <label class="fw-bold">What is your marketing strategy? <span class="text-danger"></span></label>
              <textarea class="form-control" name="marketing_plan" rows="5" required>{{ @$auth->get->marketing_plan }}</textarea>
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
            <div class="form-group other_services @if (@$auction->get->other_services == '' || @$auction->get->other_services == 'null') d-none @endif ">
              <label class="fw-bold"> What additional services will the agent provide to the seller?</label>
              <input type="text" name="other_services" id="other_services"
                value="{{ @$auction->get->other_services }}" data-icon="fa-solid fa-hand-point-right"
                class="form-control has-icon">
            </div>
          </div>

<div class="wizard-step">
    <div class="form-group">
        <label for="" class="fw-bold">Virtual Tenant Presentation (Link): </label>
        <input type="url" class="form-control has-icon" name="virtual_buyer_presentation_link" data-icon="fa-solid fa-link">
      </div>
      <div class="row">
        <div class="col-6 form-group">
            <label class="fw-bold mt-1">Virtual Tenant Presentation (Upload):</label>
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


