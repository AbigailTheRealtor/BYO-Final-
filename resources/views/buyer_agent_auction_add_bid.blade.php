@extends('layouts.main')
@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
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
  {{-- @if ($errors->any())
<h4>{{$errors->first()}}</h4>
@endif --}}
  @php
    $yes_or_nos = [
        ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
        ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
    ];
  @endphp
  <div class="container pt-5 pb-5">
    <div class="card">
      <div class="row">
        <div class="col-12 p-4">
          <form class="p-4 pt-0 mainform" action="{{ route('buyer_agent.saveBABid') }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @method('put')
            <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
            <div class="container">
              <h4 class="title">
                Add Bid on Hire Buyer's Agent auction - {{ @$auction->title }}
              </h4>
              <div class="wizard-steps-progress">
                <div class="steps-progress-percent"></div>
              </div>

              <div class="wizard-step">
                @php
                  $terms_of_contract = [
                      ['name' => '3 Months', 'target' => ''],
                      ['name' => '6 Months', 'target' => ''],
                      ['name' => '9 Months', 'target' => ''],
                      ['name' => '12 Months', 'target' => ''],
                      ['name' => 'Other', 'target' => '.custom_contract_terms'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">What is the proposed timeframe outlined in the Buyer Agency Agreement?</label>
                  <select name="terms_of_contract" id="terms_of_contract" class="grid-picker"
                    style="justify-content: flex-start;" required>
                    <option value=""></option>
                    @foreach ($terms_of_contract as $toc)
                      <option value="{{ $toc['name'] }}" data-target="{{ $toc['target'] }}" class="card flex-column"
                        style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-solid fa-calendar-days p-2" style="font-size:24px;"></i>'>
                        {{ $toc['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group custom_contract_terms input-cover d-none">
                  <label class="fw-bold">What is the proposed timeframe outlined in the Buyer Agency Agreement?</label>
                  <input type="text" name="custom_contract_terms" id="custom_contract_terms"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined" required>
                </div>
              </div>
              @php
                $yes_or_nos = [
                    ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
                ];
              @endphp
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">Will the agent provide a concession to the buyer at closing to help with the
                    buyer's closing costs? </label>
                  <select class="grid-picker" name="has_buyer_credit_at_closing" id="buyer_credit_at_closing"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '.has_buyer_credit_at_closing';
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
                <div class="form-group has_buyer_credit_at_closing d-none">
                  <label class="fw-bold"> What percentage of the agent's commission will the agent contribute to help
                    cover the buyer's closing costs?</label>
                  <input type="text" name="buyer_credit_at_closing" class="form-control has-icon col-3"
                    data-icon="fa-solid fa-ruler-combined" data-msg-required="Offered Buyer’s Concession">
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold fs-5">
                    In cases where a property is For Sale By Owner or a Buyer’s agent commission is not offered, please
                    select the options with which the Buyer’s agent is comfortable offering:
                  </label>
                  @php
                    $buyer_prefered_timeframe = [
                        [
                            'name' =>
                                "The Buyer can ask the agent to include their agent's commission as a part of the offer to the Seller.",
                            'target' => '.custom_buyer',
                        ],
                        [
                            'name' => "The Buyer must be able to pay their agent's commission out of pocket.",
                            'target' => '.custom_buyer',
                        ],
                        ['name' => 'The Agent will only accept compensation that is already offered.', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_buyer_prefered_timeframes'],
                    ];
                    $commission_rates = [
                        ['name' => '2%', 'target' => ''],
                        ['name' => '2.5%', 'target' => ''],
                        ['name' => '3%', 'target' => ''],
                        ['name' => 'Other', 'target' => '.custom_commission_rates'],
                    ];
                  @endphp
                  <select class="grid-picker" name="buyer_timeframe" id="term_financings"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($buyer_prefered_timeframe as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class=" card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="form-group" id="custom_buyer" style="display: none;">
                  <label class="fw-bold">
                    What is the commission rate charged by the agent?
                  </label>
                  <select class="grid-picker" name="custom_buyer" id="custom_buyer" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($commission_rates as $commission_rate)
                      <option value="{{ $commission_rate['name'] }}" data-target="{{ $commission_rate['target'] }}"
                        class="card flex-row" style="width:calc(33.3% - 10px);"
                        data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $commission_rate['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group custom_commission_rates d-none input-cover">
                    <label class="fw-bold"> What is the commission rate charged by the agent?</label>
                    <input type="text" class="form-control has-icon" placeholder="" name="custom_commission_rates"
                      data-icon="fa-solid fa-dollar-sign" id="custom_amount" required />
                  </div>
                </div>
                <div class="form-group custom_buyer_prefered_timeframes input-cover d-none">
                  <label class="fw-bold"> In cases where a property is For Sale By Owner or a Buyer’s agent commission is
                    not offered, what is the Buyer’s agent comfortable with offering?</label>
                  <input type="text" class="form-control has-icon " placeholder="" name="buyer_prefered_timeframe"
                    data-icon="fa-solid fa-ruler-combined" id="custom_amount" required />
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">Does the agent charge any fees to the buyer if the buyer cancels the Buyer's
                    Agency Agreement?</label>
                  <select class="grid-picker" name="hasagentCancellationFee" id="hasagentCancellationFee"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '.hasagentCancellationFee';
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
                <div class="form-group hasagentCancellationFee d-none">
                  <label class="fw-bold">What is the cancellation fee to the buyer?</label>
                  <input type="text" name="agentCancellationFee" id="agentCancellationFee"
                    class="form-control has-icon" data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">Are there any additional, unlisted fees that the agent charges the
                    buyer?</label>
                  <select class="grid-picker" name="additionalFee" id="additionalFee"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '.additionalFee';
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
                <div class="form-group additionalFee d-none">
                  <label class="fw-bold">How much is this additional fee? </label>
                  <input type="text" name="customAdditionalFee" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined">
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">About Agent:</label>
                  <textarea class="form-control" name="bio" rows="5" required>{{ @$auth->get->bio }}</textarea>
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">Why should you be hired as their agent? </label>
                  <textarea class="form-control" name="why_hire_you" rows="5" required>{{ @$auth->get->hired_agent }}</textarea>
                </div>

                <div class="form-group">
                  <label class="fw-bold">What sets you apart from other agents? </label>
                  <textarea class="form-control" name="what_sets_you_apart" rows="5" required>{{ @$auth->get->apart_agent }}</textarea>
                </div>

                <div class="form-group">
                  <label class="fw-bold">What is your marketing strategy?</label>
                  <textarea class="form-control" name="marketing_plan" rows="5" required>{{ @$auth->get->marketing_plan }}</textarea>
                </div>
              </div>
              <div class="wizard-step">
                <div class="row form-group">
                  <div class="col-md-12 mb-2">
                    <table class="table table-bordered">
                      <thead>

                        <tr>
                          <th>Website Link:</th>
                          <th>Reviews Link:</th>
                        </tr>
                      </thead>
                      <tbody class="site-links">
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
                          <th colspan="3" class="text-right">
                            <a class="btn btn-primary add-row_link">Add New Row</a>
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
                          <th colspan="2" class="text-center">Social Media Platforms</th>
                        </tr>
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
                  <label class="fw-bold">What year did the agent get licensed? </label>
                  <input type="text" class="form-control has-icon" data-icon="fa-solid fa-calendar-days"
                    name="agent_license_year" required>
                </div>
              </div>
              <div class="wizard-step">
                @php
                  $services_data = [
                      [
                          'name' =>
                              'Assist the buyer in obtaining pre-approval or exploring financing options to determine their purchasing power.',
                          'target' => '',
                      ],
                      ['name' => 'List the Buyer’s Criteria Listing on the platform BidYourOffer.com.', 'target' => ''],
                      [
                          'name' =>
                              "Market the buyer's listing on numerous groups, pages, and affiliates through a listing link or QR code that links directly to the listing on BidYourOffer.com.",
                          'target' => '',
                      ],
                      [
                          'name' =>
                              "Promote the buyer's listing on social media platforms using a listing link or QR code that directs to the buyer's listing on the BidYourOffer.com platform.",
                          'target' => '',
                      ],
                      [
                          'name' =>
                              "Send prompt email notifications containing properties that meet the buyer's criteria as soon as they are listed, ensuring access to the most up-to-date listings.",
                          'target' => '',
                      ],
                      ['name' => 'Schedule and accompany the buyer on property viewings and showings.', 'target' => ''],
                      ['name' => 'Schedule video tours of the property as needed.', 'target' => ''],
                      [
                          'name' =>
                              'Provide detailed market analysis and comparative market information to help the buyer make informed decisions.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist the buyer in negotiations and in preparing offers to maximize their chances of securing the desired property.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist the buyer with the review and explanation of all contractual documents and disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Coordinate and oversee the home inspection process, including making recommendations for trusted inspectors.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Facilitate communication and coordination with other professionals involved in the transaction, such as lenders, attorneys, and title companies.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide guidance and support throughout the entire purchase transaction, from offer acceptance to closing.',
                          'target' => '',
                      ],
                      ['name' => 'Other-Add additional services as needed.', 'target' => ''],
                      ['name' => 'Other-Add additional services as offered.', 'target' => '.other_services'],
                  ];
                @endphp
                <div class="form-group">
                  <label class="fw-bold">Select the included services that the agent will provide to the buyer:</label>
                  <select class="grid-picker" name="services[]" id="services" multiple required>
                    <option value="">Select</option>
                    @foreach ($services_data as $service)
                      <option value="{{ $service['name'] }}" data-target="{{ $service['target'] }}"
                        class="card flex-row" style="width:calc(100% - 0px);"
                        data-icon='<i class="fa-solid fa-hand-point-right"></i>'>
                        {{ $service['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group other_services d-none">
                  <label class="fw-bold">What additional services will the agent provide to the buyer?</label>
                  <input type="text" name="other_services" id="other_services" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined" required />
                </div>
              </div>

              <div class="wizard-step">
                <div class="row">
                  <div class="col-6 form-group">
                    <label class="fw-bold mt-1">Virtual Buyer Presentation:</label>
                    <div class="videoBox ">
                      <div class="video bgImg"></div>
                      <div class="form-group videoDiv">
                        <input type="file" class="fileuploader" name="virtual_buyer_presentation"
                          style="display: none;" accept="video/*">
                        <label for="fileuploader" class="fileuploader-btn">
                          <span class="upload-button">+</span>
                        </label>
                      </div>
                    </div>
                  </div>
                  <div class="col-6 form-group">
                    <div class="upload ">
                      <label class="fw-bold">Business Card:</label>
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
                  <label class="fw-bold">Virtual Buyer Presentation:(Link)</label>
                  <input type="url" class="form-control has-icon" data-icon="fa-solid fa-link"
                    name="virtual_buyer_presentation_link">
                </div>
                <div class="form-group">
                  <label class="fw-bold">Promotional marketing materials, such as postcards, flyers, brochures,
                    etc:</label>
                  <div class="d-flex align-items-baseline">
                    <input type="file" class="form-control" name="note[]"
                      value="{{ @$auth->get->promotional_material }}" multiple>
                  </div>
                </div>
              </div>
              <div class="wizard-step">
                <div class="row form-group">
                  <div class="col-md-6">
                    <label class="fw-bold">First Name:</label>
                    <input type="text" class="form-control has-icon" name="first_name" id="first_name"
                      value="{{ auth()->user()->first_name }}" data-icon="fa-solid fa-user" />
                  </div>
                  <div class="col-md-6">
                    <label class="fw-bold">Last Name:</label>
                    <input type="text" class="form-control has-icon" name="last_name" id="last_name"
                      value="{{ auth()->user()->last_name }}" data-icon="fa-solid fa-user" />
                  </div>
                </div>
                <div class="row form-group">
                  <div class="col-md-6">
                    <label class="fw-bold">Phone Number:</label>
                    <input type="text" class="form-control has-icon" name="phone" id="phone"
                      value="{{ auth()->user()->phone }}" data-icon="fa-solid fa-phone" />
                  </div>
                  <div class="col-md-6">
                    <label class="fw-bold">Email:</label>
                    <input type="email" class="form-control has-icon" name="email" id="email"
                      value="{{ auth()->user()->email }}" data-icon="fa-solid fa-envelope" />
                  </div>
                  <div class="col-md-6">
                    <label class="fw-bold">Brokerage:</label>
                    <input type="text" class="form-control has-icon" name="brokerage" id="brokerage"
                      value="{{ auth()->user()->brokerage }}" data-icon="fa-solid fa-handshake">
                  </div>
                  <div class="col-md-6">
                    <label class="fw-bold">Real Estate License #:</label>
                    <input type="text" class="form-control has-icon" name="license_no"
                      value="{{ auth()->user()->license_no }}" data-icon="fa-solid fa-id-card " />
                  </div>
                </div>
                <div class="row form-group">
                  <div class="col-md-6">
                    <label class="fw-bold">NAR Member ID (NRDS ID):</label>
                    <input type="text" class="form-control has-icon" name="mls_id"
                      value="{{ auth()->user()->mls_id }}" data-icon="fa-solid fa-id-badge " />
                  </div>
                </div>
              </div>
              <div class="d-flex justify-content-between form-group mt-4">
                <div>
                  <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                </div>
                <div>
                  <button type="button" id="saveBtn" class="wizard-step-next btn btn-success btn-lg text-600"
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
@endsection
@push('scripts')
  <script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
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
        if (this.files[0].size > 10000000) {
          $('.videoDiv').after('<span style="color: red;">Please upload a file less than 10MB. Thanks!!</span>');
          $(this).val('');
          $('#saveBtn').prop('disabled', true);
        } else {
          $('#saveBtn').prop('disabled', false);
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
      $('.add-row_link').on('click', function() {
        var siteLinkRow = `<tr>
                                    <td>
                                        <input type="text" name="wibsite_link[]" class="form-control">

                                    </td>
                                    <td>
                                        <input type="text" name="revi_link[]" class="form-control">
                                    </td>
                                </tr>`;
        $('.site-links').append(siteLinkRow);
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

    $(document).ready(function() {
      $('[data-target=".custom_buyer"]').click(function() {
        // Show the fields or perform any other actions
        $('.custom_buyer').removeClass('d-none'); // Remove the 'd-none' class to show the fields
      });
    });
    // View Preference Needed?
    var toggleDivTerm = $('#custom_buyer');
    $('#term_financings').change(function() {
      //Disply and hide a div
      if ($(this).val().includes("The Buyer must be able to pay their agent's commission out of pocket.") || $(this)
        .val().includes(
          "The Buyer can ask the agent to include their agent's commission as a part of the offer to the Seller.")) {
        toggleDivTerm.show();
      } else {
        toggleDivTerm.hide();
      }

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
