@extends('layouts.main')
@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.css">
  <link rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.css">

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

    input[type="radio"] {
      -webkit-appearance: radio !importent;
    }
  </style>
@endpush
@section('content')
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
          <form class="p-4 pt-0 validate mainform" action="{{ route('agent.service.auction.save') }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @push('scripts')
              <script>
                $(function() {
                  $('#working_with_agent').change(function() {
                    var val = $(this).val();
                    if (val == "Yes") {
                      $('.wizard-step-next').attr("disabled", "disabled");
                      $('.yes_message').text(
                        "This is a service for Landlord’s that are not currently working with a licensed agent."
                      );
                    } else {
                      $('.wizard-step-next').removeAttr("disabled");
                      $('.yes_message').text("");
                    }
                  });
                });
              </script>
            @endpush
            <div class="container">
              <h4 class="title">
                {{ $title }}
              </h4>
              <div class="wizard-steps-progress">
                <div class="steps-progress-percent"></div>
              </div>


              {{-- 14 Jul 2023 --}}
              @php
                $service_types = [
                    ['name' => 'Flat Fee Service', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                    [
                        'name' => 'Referral agent Service',
                        'target' => '.referral_fee',
                        'icon' => 'fa-regular fa-circle-check',
                    ],
                ];
              @endphp
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">Service Type:</label>
                  <select class="grid-picker" name="service_type" id="service_type" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($service_types as $item)
                      @php
                        if ($item['name'] == 'Referral agent Service') {
                            $target = '.referral_fee';
                        } else {
                            $target = '';
                        }
                      @endphp
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                @php
                  $referral_fee = [
                      ['target' => '', 'name' => '20%'],
                      ['target' => '', 'name' => '25%'],
                      ['target' => '', 'name' => '30%'],
                      ['target' => '', 'name' => '35%'],
                      ['target' => '', 'name' => '40%'],
                      ['target' => '', 'name' => '45%'],
                      ['target' => '', 'name' => '50%'],
                      ['target' => '.custom_referral_fee', 'name' => 'Other'],
                  ];
                @endphp
                <div class="form-group referral_fee d-none">
                  <label class="fw-bold">How much referral fee would agent like?</label>
                  <select class="grid-picker" name="referral_fee" id="referral_fee" style="justify-content: flex-start;"
                    required>
                    <option value="">Select</option>
                    @foreach ($referral_fee as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="fa-regular fa-check-circle"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="form-group custom_referral_fee d-none">
                  <label class="fw-bold">How much referral fee would agent like?</label>
                  <input type="text" name="custom_referral_fee"  id="custom_referral_fee"
                    class="form-control has-icon" data-icon="fa-solid fa-percent" required>
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label for="address" class="fw-bold">Listing Date:</label>
                  <input type="date" name="listing_date" id="listing_date" class="form-control has-icon search_places"
                    data-icon="fa-regular fa-calendar-days" required>
                </div>

                <div class="form-group">
                  <label for="address" class="fw-bold">Expiration Date:</label>
                  <input type="date" name="expiration_date" id="expiration_date"
                    class="form-control has-icon search_places" data-icon="fa-regular fa-calendar-days" required>
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">What city, county, and state do the services need to be provided
                    in?</label>
                </div>

                <div class="form-group">
                  <label class="fw-bold">City:</label>
                  <input type="text" name="city" data-type="cities" id="city"
                    class="form-control has-icon search_places" data-icon="fa-solid fa-city" required placeholder="">
                </div>

                <div class="form-group">
                  <label class="fw-bold">County:</label>
                  <input type="text" name="county" data-type="counties" id="county"
                    class="form-control has-icon search_places" data-icon="fa-solid fa-tree-city" placeholder="" required>
                </div>

                <div class="form-group">
                  <label class="fw-bold">State:</label>
                  <input type="text" name="state" data-type="states" id="state"
                    class="form-control has-icon search_places" data-icon="fa-solid fa-flag-usa" placeholder=" " required>
                </div>
              </div>
              {{-- 14 Jul 2023 --}}
              {{-- 14 Jul 2023 --}}
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">
                    What is the amount of time that you will allow for bidding?
                  </label>
                  <div>
                    @php
                      $auction_lengths = [
                          ['name' => '1 hour', 'class' => 'normal-length'],
                          ['name' => '2 hours', 'class' => 'normal-length'],
                          ['name' => '3 hours', 'class' => 'normal-length'],
                          ['name' => '4 hours', 'class' => 'normal-length'],
                          ['name' => '5 hours', 'class' => 'normal-length'],
                          ['name' => '6 hours', 'class' => 'normal-length'],
                          ['name' => '7 hours', 'class' => 'normal-length'],
                          ['name' => '8 hours', 'class' => 'normal-length'],
                          ['name' => '9 hours', 'class' => 'normal-length'],
                          ['name' => '10 hours', 'class' => 'normal-length'],
                          ['name' => '11 hours', 'class' => 'normal-length'],
                          ['name' => '12 hours', 'class' => 'normal-length'],
                          ['name' => '1 day', 'class' => 'normal-length'],
                          ['name' => '2 days', 'class' => 'normal-length'],
                          ['name' => '3 days', 'class' => 'normal-length'],
                          ['name' => '5 days', 'class' => 'normal-length'],
                          ['name' => '7 days', 'class' => 'normal-length'],
                          ['name' => '10 days', 'class' => 'normal-length'],
                          ['name' => '14 days', 'class' => 'normal-length'],
                          ['name' => '21 days', 'class' => 'normal-length'],
                          ['name' => '30 days', 'class' => 'normal-length'],
                      ];
                    @endphp
                    <select name="auction_length" id="auction_length" class="auction_length grid-picker"
                      style="justify-content: flex-start;" required>
                      <option value=""></option>
                      @foreach ($auction_lengths as $item)
                        <option value="{{ $item['name'] }}" data-target="" class="card flex-row {{ $item['class'] }}"
                          style="width:calc(33.33% - 10px);" data-icon='<i class="fa-solid fa-calendar-days"></i>'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group ">
                  <label class="fw-bold">Title of Listing</label>
                  <input type="text" class="form-control has-icon" placeholder="Enter Title of Listing "
                    name="titleListing" data-icon="fa-solid fa-ruler-combined" required />
                </div>
              </div>
              <div class="wizard-step">
                @php
                  $type_of_client = [
                      [
                          'name' => 'Buyer',
                          'target' => '.cutom_buyer_list_option',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Seller',
                          'target' => '.cutom_seller_list_option',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Landlord',
                          'target' => '.cutom_landlord_list_option',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Tenant',
                          'target' => '.cutom_tenant_list_option',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                      [
                          'name' => 'Real Estate Agent',
                          'target' => '.custom_real_estate_agent_option',
                          'icon' => 'fa-regular fa-circle-check',
                      ],
                  ];
                  $buyer_listings = [
                      [
                          'name' =>
                              'Assist the buyer in obtaining pre-approval or exploring financing options to determine their purchasing power.',
                          'target' => '',
                      ],
                      ['name' => 'List the buyer’s criteria listing on the platform BidYourOffer.com.', 'target' => ''],
                      [
                          'name' =>
                              'Market the buyer\'s listing on social media platforms using a listing link or QR code that directs to the buyer\'s listing on the BidYourOffer.com platform.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Market the buyer\'s criteria listing on numerous groups, pages, and affiliates through a listing link or QR code that links directly to the listing on BidYourOffer.com.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Distribute postcards to the buyer’s desired neighborhood with a QR code or listing link leading to the buyer’s criteria listing on the BidYourOffer.com platform.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Conduct real estate email marketing campaigns that lead to the buyer’s criteria listing on the BidYourOffer.com platform.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Send prompt email notifications containing properties that meet the buyer\'s criteria as soon as they are listed, ensuring access to the most up-to-date listings.',
                          'target' => '',
                      ],
                      ['name' => 'Arrange a showing for a single property.', 'target' => ''],
                      ['name' => 'Arrange showings for multiple properties.', 'target' => ''],
                      ['name' => 'Conduct a video tour for a single property.', 'target' => ''],
                      ['name' => 'Conduct video tours for multiple properties.', 'target' => ''],
                      [
                          'name' =>
                              'Assist in drafting residential sale agreements and required addendums/disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Assist in drafting commercial sale agreements and required addendums/disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Conduct a thorough comparative market analysis (CMA) to determine a property\'s value and pricing strategy.',
                          'target' => '',
                      ],
                      ['name' => 'Perform real estate market research.', 'target' => ''],
                      ['name' => 'Analyze property market trends.', 'target' => ''],
                      ['name' => 'Offer risk assessment and investment advice.', 'target' => ''],
                      ['name' => 'Provide property investment guidance.', 'target' => ''],
                      ['name' => 'Advise on distressed property transactions.', 'target' => ''],
                      [
                          'name' =>
                              'Coordinate and oversee the home inspection process, including making recommendations for trusted inspectors.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Arrange for a property survey to verify boundaries, easements, and other important property details.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist in negotiating repair credits or concessions with the seller based on inspection findings to ensure the buyer\'s interests are protected.',
                          'target' => '',
                      ],
                      ['name' => 'Verify completion of agreed-upon repairs.', 'target' => ''],
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
                      [
                          'target' => '.custom_buyers_flat_fee_list',
                          'name' => 'Other - Add additional services as needed.',
                      ],
                  ];
                  $real_estate_agent_listings = [
                      [
                          'name' =>
                              'Conduct a thorough comparative market analysis (CMA) to determine the property\'s value and pricing strategy.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Conduct a thorough rental market analysis (RMA) to determine the property\'s value and pricing strategy.',
                          'target' => '',
                      ],
                      ['name' => 'Show a property to a buyer.', 'target' => ''],
                      ['name' => 'Show a property to a tenant.', 'target' => ''],
                      ['name' => 'Show multiple properties to a buyer.', 'target' => ''],
                      ['name' => 'Show multiple properties to a tenant.', 'target' => ''],
                      ['name' => 'Show the seller\'s property to a potential buyer.', 'target' => ''],
                      ['name' => 'Show the seller\'s property to multiple potential buyers.', 'target' => ''],
                      ['name' => 'Show the landlord\'s property to a potential buyer.', 'target' => ''],
                      ['name' => 'Show the landlord\'s property to multiple potential buyers.', 'target' => ''],
                      [
                          'name' =>
                              'Oversee transactions by composing, arranging, and disseminating essential legal paperwork, including contracts, disclosures, and addendums for a residential property sale.',
                          'target' => '',
                      ],
                      ['name' => 'Assist in negotiating residential sale agreements.', 'target' => ''],
                      [
                          'name' =>
                              'Oversee transactions by composing, arranging, and disseminating essential legal paperwork, including contracts, disclosures, and addendums for a commercial property sale.',
                          'target' => '',
                      ],
                      ['name' => 'Assist in negotiating commercial sale agreements.', 'target' => ''],
                      [
                          'name' =>
                              'Oversee transactions by composing, arranging, and disseminating essential legal paperwork, including leases, disclosures, and addendums for a residential lease.',
                          'target' => '',
                      ],
                      ['name' => 'Assist in negotiating residential lease agreements.', 'target' => ''],
                      [
                          'name' =>
                              'Oversee transactions by composing, arranging, and disseminating essential legal paperwork, including leases, disclosures, and addendums for a commercial lease.',
                          'target' => '',
                      ],
                      ['name' => 'Assist in negotiating commercial lease agreements.', 'target' => ''],
                      [
                          'name' =>
                              'Schedule and manage a certified home inspector to examine the condition of a property.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Schedule and manage a handyman to perform necessary repairs or improvements on a property.',
                          'target' => '',
                      ],
                      ['name' => 'Meet an appraiser.', 'target' => ''],
                      ['name' => 'Meet an inspector.', 'target' => ''],
                      ['name' => 'Host an Open House.', 'target' => ''],
                      ['name' => 'Provide professional photos showcasing the property\'s features.', 'target' => ''],
                      ['name' => 'Provide a professional video to showcase the property\'s features.', 'target' => ''],
                      [
                          'name' =>
                              'Provide aerial photography to capture the property\'s surroundings and neighborhood.',
                          'target' => '',
                      ],
                      ['name' => 'Provide a 3D tour to showcase the property\'s features.', 'target' => ''],
                      [
                          'name' =>
                              'Provide a floor plan of the property to highlight its layout and spatial configuration.',
                          'target' => '',
                      ],
                      ['name' => 'Provide a plot plan to showcase the land.', 'target' => ''],
                      ['name' => 'Provide virtual staging to enhance the property\'s visual appeal.', 'target' => ''],
                      ['name' => 'Provide recommendations for staging professionals.', 'target' => ''],
                      ['name' => 'Provide staging for a property.', 'target' => ''],
                      [
                          'name' =>
                              'Provide mentorship opportunities for novice agents to learn from experienced colleagues.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Hire an agent to provide support during your listing presentation to a seller.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Hire an agent to provide support during your listing presentation to a buyer.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Hire an agent to provide support during your listing presentation to a landlord.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Hire an agent to provide support during your listing presentation to a tenant.',
                          'target' => '',
                      ],
                      ['name' => 'Install a lockbox at a property.', 'target' => ''],
                      [
                          'name' => 'Manage the installation and removal of home sale-related yard signs.',
                          'target' => '',
                      ],
                      [
                          'name' => 'List the property on the Bid Your Offer platform.',
                          'target' => '.custom_real_state_property',
                      ],
                      ['name' => 'List the property on the MLS.', 'target' => '.custom_real_state_property'],
                      [
                          'name' =>
                              'List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and others, to increase visibility and exposure.',
                          'target' => '.custom_real_state_property',
                      ],
                      [
                          'name' => 'List the property on Loopnet, a major commercial real estate website.',
                          'target' => '.custom_real_state_property',
                      ],
                      [
                          'name' => 'List the property on Crexi, a major commercial real estate website.',
                          'target' => '.custom_real_state_property',
                      ],
                      [
                          'name' =>
                              'Implement an online marketing campaign with a QR code or listing link that leads to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Distribute postcards featuring the listing to a specified neighborhood with a QR code or listing link leading to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Distribute postcards featuring the seller\'s listing to the most opportune buyers with a QR code or listing link leading to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Conduct real estate email marketing campaigns that lead to the property’s listing.',
                          'target' => '',
                      ],
                      ['name' => 'Respond to phone calls and arrange showings.', 'target' => ''],
                      [
                          'name' =>
                              'Send prompt email notifications containing properties that meet the buyer\'s criteria as soon as they are listed, ensuring access to the most up-to-date listings.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Conduct comprehensive tenant screening that includes an application process with credit, criminal, background, eviction, and income verification checks.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Handle tenant inquiries, maintenance requests, and resolve any issues that may arise during the tenancy.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Assist with lease renewal negotiations and adjustments to rental terms.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Coordinate or assist in the move-in or move-out process for tenants.',
                          'target' => '',
                      ],
                      ['name' => 'Provide a consultation on property management and investment.', 'target' => ''],
                      [
                          'name' =>
                              'Coordinate property maintenance and repairs through trusted contractors and vendors.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Managing communication between buyers, sellers, and their respective agents.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Managing communication between landlords, tenants, and their respective agents.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Conduct walk-throughs before closing a sale to ensure agreed-upon repairs or improvements have been done.',
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
                      [
                          'name' => 'Provide advice on the process of buying or selling distressed properties.',
                          'target' => '',
                      ],
                      ['name' => 'Provide social media management and marketing services.', 'target' => ''],
                      ['name' => 'Creating and distributing digital brochures or pamphlets.', 'target' => ''],
                      [
                          'name' => 'Assist in the creation of attractive and engaging listing descriptions.',
                          'target' => '',
                      ],
                      ['name' => 'Offer guidance on property investment strategies.', 'target' => ''],
                      ['name' => 'Conduct property market trend analysis and reporting.', 'target' => ''],
                      [
                          'name' => 'Provide support in developing and implementing client referral programs.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Offer guidance on real estate investment risk assessment and mitigation.',
                          'target' => '',
                      ],
                      ['name' => 'Assist with real estate lead conversion and follow-up strategies.', 'target' => ''],
                      [
                          'name' =>
                              'Assist with real estate market research for specific demographics or target markets.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Conduct administrative tasks such as data entry, paperwork processing, appointment scheduling, and customer relationship management (CRM) implementation and management.',
                          'target' => '',
                      ],
                      ['name' => 'Mentor and/or shadow an agent.', 'target' => ''],
                      [
                          'name' =>
                              'Co-host open house events, partnering to showcase multiple properties in the same neighborhood, offering a convenient experience for potential buyers.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Organize networking events or workshops, creating opportunities for agents to exchange expertise, discuss market trends, and foster a collaborative and learning-oriented real estate community.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Develop and execute joint marketing campaigns, pooling resources for broader audience reach and potential benefits for both agents and their clients.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide assistance with international transactions, navigating legal, financial, and logistical complexities when dealing with foreign buyers or sellers.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide guidance on short-term rental regulations and property management for vacation rentals.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Conduct market research and analysis for niche markets or specific property types.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Provide assistance with real estate investment analysis and financial modeling.',
                          'target' => '',
                      ],
                      ['name' => 'Offer guidance on property tax assessment appeals or challenges.', 'target' => ''],
                      ['name' => 'Provide relocation services for clients moving to or from the area.', 'target' => ''],
                      [
                          'name' => 'Provide support with specialized marketing strategies for luxury properties.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide expertise in green building certifications or energy-efficient property features.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide guidance on short-term rental regulations and property management for vacation rentals.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Providing support with real estate software and technology implementation for improved efficiency.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide assistance with property valuation for estate planning or probate purposes.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Provide support with real estate crowdfunding or syndication opportunities.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Provide assistance with property insurance selection and risk management.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide support with real estate investment syndication or partnership agreements.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Collaborate on co-authoring industry-specific resources such as ebooks, guides, or articles, providing insights and valuable information to clients and other agents.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Coordinately participate in community events, sponsorships, or charity initiatives to reinforce a positive image of real estate professionals within the local community.',
                          'target' => '',
                      ],
                      [
                          'target' => '.custom_real_estatet_fee_list',
                          'name' => 'Other - Add additional services as needed.',
                      ],
                  ];
                  $seller_listings = [
                      [
                          'name' =>
                              'Conduct a thorough comparative market analysis (CMA) to determine the property\'s value and pricing strategy.',
                          'target' => '',
                      ],
                      [
                          'name' => 'List the property on the Bid Your Offer platform.',
                          'target' => '.custom_seller_property',
                      ],
                      ['name' => 'List the property on the MLS.', 'target' => '.custom_seller_property'],
                      [
                          'name' =>
                              'List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and others, to increase visibility and exposure.',
                          'target' => '.custom_seller_property',
                      ],
                      [
                          'name' => 'List the property on Loopnet, a significant commercial real estate website.',
                          'target' => '.custom_seller_property',
                      ],
                      [
                          'name' => 'List the property on Crexi, another major commercial real estate website.',
                          'target' => '.custom_seller_property',
                      ],
                      [
                          'name' =>
                              'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Distribute postcards featuring the seller\'s listing within their neighborhood with a QR code or listing link leading to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Distribute postcards featuring the seller\'s listing to the most opportune buyers with a QR code or listing link leading to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Conduct real estate email marketing campaigns that lead to the seller’s listing.',
                          'target' => '',
                      ],
                      ['name' => 'Provide professional photos showcasing the property\'s features.', 'target' => ''],
                      [
                          'name' =>
                              'Provide aerial photography to capture the property\'s surroundings and neighborhood.',
                          'target' => '',
                      ],
                      ['name' => 'Provide a professional video to showcase the property\'s features.', 'target' => ''],
                      ['name' => 'Provide a 3D tour to showcase the property\'s features.', 'target' => ''],
                      [
                          'name' =>
                              'Provide a floor plan of the property to highlight its layout and spatial configuration.',
                          'target' => '',
                      ],
                      ['name' => 'Provide a plot plan.', 'target' => ''],
                      [
                          'name' =>
                              'Provide virtual staging to enhance the property\'s visual appeal and attract potential buyers.',
                          'target' => '',
                      ],
                      ['name' => 'Provide recommendations for staging professionals.', 'target' => ''],
                      ['name' => 'Host an Open House.', 'target' => ''],
                      [
                          'name' =>
                              'Send email alerts to buyers searching for properties that match the property\'s criteria the moment the property is listed directly through the MLS.',
                          'target' => '',
                      ],
                      ['name' => 'Show the seller\'s property to a potential buyer.', 'target' => ''],
                      ['name' => 'Show the seller\'s property to multiple potential buyers.', 'target' => ''],
                      ['name' => 'Respond to phone calls and arrange showings.', 'target' => ''],
                      [
                          'name' =>
                              'Provide regular updates on market activity, showings, and feedback from potential buyers.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Schedule and manage a handyman for necessary repairs or improvements on a property.',
                          'target' => '',
                      ],
                      ['name' => 'Meet with an appraiser.', 'target' => ''],
                      ['name' => 'Meet with an inspector.', 'target' => ''],
                      ['name' => 'Install a lockbox on the property.', 'target' => ''],
                      [
                          'name' => 'Manage the installation and removal of home sale-related yard signs.',
                          'target' => '',
                      ],
                      ['name' => 'Offer guidance on property investment strategies.', 'target' => ''],
                      [
                          'name' =>
                              'Assist in drafting residential sale agreements and required addendums/disclosures.',
                          'target' => '',
                      ],
                      ['name' => 'Assist in negotiating residential sale agreements.', 'target' => ''],
                      [
                          'name' => 'Assist in drafting commercial sale agreements and required addendums/disclosures.',
                          'target' => '',
                      ],
                      ['name' => 'Assist in negotiating commercial sale agreements.', 'target' => ''],
                      [
                          'name' =>
                              'Provide the necessary property disclosure statements to ensure legal compliance and transparency with potential buyers.',
                          'target' => '',
                      ],
                      ['name' => 'Review and explain contractual documents.', 'target' => ''],
                      [
                          'name' =>
                              'Coordinate interactions with title companies, escrow officers, and other key parties in real estate transactions.',
                          'target' => '',
                      ],
                      ['name' => 'Conduct property market trend analysis and reporting.', 'target' => ''],
                      [
                          'name' => 'Offer guidance on real estate investment risk assessment and mitigation.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist with real estate market research for specific demographics or target markets.',
                          'target' => '',
                      ],
                      ['name' => 'Provide advice on the process of selling distressed properties.', 'target' => ''],
                      [
                          'name' =>
                              'Conduct walk-throughs before closing a sale to ensure agreed-upon repairs or improvements have been done.',
                          'target' => '',
                      ],
                      ['target' => '.custom_seller_fee_list', 'name' => 'Other - Add additional services as needed.'],
                  ];
                  $tenant_listings = [
                      [
                          'name' => 'List the tenant\'s criteria listing on the BidYourOffer.com platform.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Market the tenant’s criteria listing on social media platforms using a listing link or QR code that directs to the buyer\'s listing on the BidYourOffer.com platform.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Market the tenant’s criteria listing on numerous groups, pages, and affiliates through a listing link or QR code that links directly to the listing on BidYourOffer.com.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Conduct real estate email marketing campaigns that lead to the tenant’s criteria listing on the BidYourOffer.com platform.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Distribute postcards to the tenant’s desired neighborhood with a QR code or listing link leading to the buyer’s criteria listing on the BidYourOffer.com platform.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Send prompt email notifications containing properties that match the tenant\'s criteria as soon as they are listed, ensuring access to the most up-to-date listings.',
                          'target' => '',
                      ],
                      ['name' => 'Arrange showings for a single property.', 'target' => ''],
                      ['name' => 'Arrange showings for multiple properties.', 'target' => ''],
                      ['name' => 'Conduct a video tour for a single property.', 'target' => ''],
                      ['name' => 'Conduct video tours for multiple properties.', 'target' => ''],
                      [
                          'name' =>
                              'Assist in drafting residential lease agreements and any required addendums/disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist in negotiating residential lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist in drafting commercial lease agreements and any required addendums/disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist in negotiating commercial lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist with the tenant\'s rental application process, providing guidance and support.',
                          'target' => '',
                      ],
                      ['name' => 'Review and explain lease documents.', 'target' => ''],
                      [
                          'name' =>
                              'Conduct a thorough rental market analysis (RMA) to determine the property\'s value and pricing strategy.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Coordinate and oversee the move-in process, including inspections and key handovers.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide resources and recommendations for local amenities, such as schools, parks, and healthcare facilities.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Facilitate communication between the tenant and the landlord or property management company before move-in.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Provide resources and information on tenant rights and responsibilities.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide guidance on lease renewal options and negotiate rent adjustments if necessary.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist tenants with understanding their rights regarding security deposits and refunds.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Provide support with resolving landlord-tenant disputes or conflicts.',
                          'target' => '',
                      ],
                      ['target' => '.custom_tenant_fee_list', 'name' => 'Other - Add additional services as needed.'],
                  ];

                  $landlord_listings = [
                      [
                          'target' => '.custom_landlord_property',
                          'name' => 'List the property on the Bid Your Offer platform.',
                      ],
                      ['target' => '.custom_landlord_property', 'name' => 'List the property on the MLS.'],
                      [
                          'target' => '.custom_landlord_property',
                          'name' =>
                              'List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and others, to increase visibility and exposure.',
                      ],
                      [
                          'target' => '.custom_landlord_property',
                          'name' => 'List the property on Loopnet, a major commercial real estate website.',
                      ],
                      [
                          'target' => '.custom_landlord_property',
                          'name' => 'List the property on Crexi, a major commercial real estate website.',
                      ],
                      [
                          'name' =>
                              'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Conduct real estate email marketing campaigns that lead to the property listing.',
                          'target' => '',
                      ],
                      ['name' => 'Provide professional photos showcasing the property\'s features.', 'target' => ''],
                      ['name' => 'Provide a professional video to showcase the property\'s features.', 'target' => ''],
                      ['name' => 'Provide a 3D tour to showcase the property\'s features.', 'target' => ''],
                      [
                          'name' =>
                              'Provide a floor plan of the property to highlight its layout and spatial configuration.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Provide virtual staging to enhance the property\'s visual appeal and attract potential tenants.',
                          'target' => '',
                      ],
                      ['name' => 'Host an Open House.', 'target' => ''],
                      [
                          'name' =>
                              'Send email alerts to tenants seeking properties matching the criteria as soon as the property is listed through the MLS.',
                          'target' => '',
                      ],
                      ['name' => 'Show the landlord\'s property to a potential tenant.', 'target' => ''],
                      ['name' => 'Show the landlord\'s property to multiple potential tenants.', 'target' => ''],
                      ['name' => 'Respond to phone calls and arrange showings.', 'target' => ''],
                      [
                          'name' =>
                              'Provide regular updates on market activity, showings, and feedback from potential tenants.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Conduct tenant screening with a thorough application process that includes credit, criminal, background, eviction, and income verification checks.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Schedule and manage a handyman for necessary repairs or improvements on a property.',
                          'target' => '',
                      ],
                      ['name' => 'Install a lockbox on the property.', 'target' => ''],
                      [
                          'name' => 'Manage the installation and removal of home sale-related yard signs.',
                          'target' => '',
                      ],
                      ['name' => 'Negotiate terms and conditions with tenants and/or their agents.', 'target' => ''],
                      [
                          'name' =>
                              'Assist in drafting residential lease agreements and required addendums/disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist in negotiating residential lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist in drafting commercial lease agreements and required addendums/disclosures.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Assist in negotiating commercial lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Assist with lease renewal negotiations and adjustments to rental terms.',
                          'target' => '',
                      ],
                      ['name' => 'Review and explain contractual documents.', 'target' => ''],
                      [
                          'name' =>
                              'Coordinate property maintenance and repairs through trusted contractors and vendors.',
                          'target' => '',
                      ],
                      [
                          'name' =>
                              'Handle tenant inquiries, maintenance requests, and resolve any issues that may arise during the tenancy.',
                          'target' => '',
                      ],
                      [
                          'name' => 'Coordinate or assist in the move-in or move-out process for tenants.',
                          'target' => '',
                      ],
                      ['target' => '.custom_landlord_fee_list', 'name' => 'Other - Add additional services as needed.'],
                  ];

                @endphp
                <div class="form-group">
                  <label class="fw-bold">What type of client is the service being provided for:</label>
                  <select class="grid-picker" name="type_of_client" id="type_of_client"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($type_of_client as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(33.3% - 10px);" data-icon='<i class="{{ $item['icon'] }}"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                </div>
                {{-- Buyer --}}
                <div class="form-group d-none cutom_buyer_list_option">
                  <label class="fw-bold buyerAgent">Select the services that the buyer needs: </label>
                  <select name="services[]" id="buyers_flat_fee_list" class="grid-picker"
                    style="justify-content: flex-start;" multiple required>
                    <option value=""></option>
                    @foreach ($buyer_listings as $buyer_listing)
                      <option value="{{ $buyer_listing['name'] }}" data-target="{{ $buyer_listing['target'] }}"
                        class="card flex-row" style="width:calc(100%);"
                        data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                        {{ $buyer_listing['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group custom_buyers_flat_fee_list d-none">
                    <label class="fw-bold">What service(s) would you like the agent to provide? </label>
                    <input type="text" class="form-control has-icon" placeholder=" "
                      name="custom_buyers_flat_fee_list" data-icon="fa-solid fa-ruler-combined"
                      id="custom_buyers_flat_fee_list" required />
                  </div>
                </div>
                {{-- real estate --}}

                <div class="form-group d-none custom_real_estate_agent_option">
                  <label class="fw-bold">
                    Select the services that the Real Estate Agent needs:
                  </label>

                  <select name="services[]" id="real_estate_flat_fee_list" class="grid-picker"
                    style="justify-content: flex-start;" multiple required>
                    <option value=""></option>
                    @foreach ($real_estate_agent_listings as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(100%);"
                        data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group custom_real_estatet_fee_list d-none">
                    <label class="fw-bold">What service(s) would you like the agent to provide? </label>
                    <input type="text" class="form-control has-icon" placeholder=" "
                      name="custom_real_estatet_fee_list" data-icon="fa-solid fa-ruler-combined"
                      id="custom_real_estatet_fee_list" required />
                  </div>
                  <div class="form-group d-none realEstateOpt">
                    <label class="fw-bold"> If the agent is representing a client will the agent’s client pay a
                      commission
                      to the agent?
                    </label>
                    <select class="grid-picker" name="has_non_negotiable_amenities" id="has_non_negotiable_amenities"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($yes_or_nos as $item)
                        @php
                          if ($item['name'] == 'Yes') {
                              $target = '.custom_real_state_cooperating_agent';
                          } elseif ($item['name'] == 'No') {
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
                    <div class="form-group custom_real_state_cooperating_agent d-none">
                      <label class="fw-bold">What compensation will the agent’s client pay a cooperating agent?</label>
                      <input type="text" class="form-control has-icon" placeholder=" "
                        name="custom_real_state_cooperating_agent" data-icon="fa-solid fa-ruler-combined"
                        id="custom_real_state_cooperating_agent" required />
                    </div>
                  </div>
                </div>


                {{-- seller --}}

                <div class="form-group d-none cutom_seller_list_option">
                  <label class="fw-bold sellerAgent">Select the services that the seller needs: </label>

                  <select name="services[]" id="seller_flat_fee_list" class="grid-picker"
                    style="justify-content: flex-start;" multiple required>
                    <option value=""></option>
                    @foreach ($seller_listings as $item)
                      <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                        style="width:calc(100%);"
                        data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                        {{ $item['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group d-none sellerOpt">
                    @php
                      $sellerOptions = [
                          [
                              'name' => 'Yes',
                              'icon' => "<i class='fa-regular fa-circle-check'></i>",
                              'target' => '.custom_seller_cooperating_agent',
                          ],
                          ['name' => 'No', 'icon' => "<i class='fa-regular fa-circle-xmark'></i>", 'target' => ''],
                          [
                              'name' => 'Negotiable',
                              'icon' => "<i class='fa-regular fa-circle-check'></i>",
                              'target' => '',
                          ],
                      ];
                    @endphp
                    <label class="fw-bold"> If the buyer is represented by an agent, will the seller pay a commission to
                      the buyer's agent?
                    </label>
                    <select class="grid-picker" name="has_non_negotiable_amenities" id="has_non_negotiable_amenities"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($sellerOptions as $item)
                        <option value="{{ $item['name'] }}" data-target="{{ $item['target'] }}" class="card flex-row"
                          style="width:calc(33.3% - 10px);" data-icon='{{ $item['icon'] }}'>
                          {{ $item['name'] }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-group custom_seller_cooperating_agent d-none">
                      <label class="fw-bold">What compensation will the seller pay a cooperating agent if the buyer has
                        an
                        agent? </label>
                      <input type="text" class="form-control has-icon" placeholder=" "
                        name="custom_seller_cooperating_agent" data-icon="fa-solid fa-ruler-combined"
                        id="custom_seller_cooperating_agent" required />
                    </div>
                  </div>
                  <div class="form-group custom_seller_fee_list d-none">
                    <label class="fw-bold">What service(s) would you like the agent to provide? </label>
                    <input type="text" class="form-control has-icon" placeholder=" "
                      name="custom_real_estatet_fee_list" data-icon="fa-solid fa-ruler-combined"
                      id="custom_real_estatet_fee_list" required />
                  </div>
                </div>
                {{-- Tenant --}}

                <div class="form-group d-none cutom_tenant_list_option">
                  <label class="fw-bold">
                    <label class="fw-bold tenantAgent">Select the services that the Tenanat needs: </label>
                  </label>

                  <select name="services[]" id="seller_flat_fee_list" class="grid-picker"
                    style="justify-content: flex-start;" multiple required>
                    <option value=""></option>
                    @foreach ($tenant_listings as $tenant_listing)
                      <option value="{{ $tenant_listing['name'] }}" data-target="{{ $tenant_listing['target'] }}"
                        class="card flex-row" style="width:calc(100%);"
                        data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                        {{ $tenant_listing['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group custom_tenant_fee_list d-none">
                    <label class="fw-bold">What service(s) would you like the agent to provide? </label>
                    <input type="text" class="form-control has-icon" placeholder=" "
                      name="custom_real_estatet_fee_list" data-icon="fa-solid fa-ruler-combined"
                      id="custom_real_estatet_fee_list" required />
                  </div>
                </div>

                {{-- landlord --}}
                <div class="form-group d-none cutom_landlord_list_option">
                  <label class="fw-bold landlordAgent">Select the services that the Landlord needs: </label>

                  <select name="services[]" id="landLordOptions" class="grid-picker"
                    style="justify-content: flex-start;" multiple required>
                    <option value=""></option>
                    @foreach ($landlord_listings as $landlord_listing)
                      <option value="{{ $landlord_listing['name'] }}" data-target="{{ $landlord_listing['target'] }}"
                        class="card flex-row" style="width:calc(100%);"
                        data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                        {{ $landlord_listing['name'] }}
                      </option>
                    @endforeach
                  </select>
                  <div class="form-group d-none landlordOpt">
                    <label class="fw-bold"> If the tenant represented by an agent, will the landlord pay a commission to
                      the tenant’s agent?
                    </label>
                    <select class="grid-picker" name="has_non_negotiable_amenities" id="has_non_negotiable_amenities"
                      style="justify-content: flex-start;" required>
                      <option value="">Select</option>
                      @foreach ($yes_or_nos as $item)
                        @php
                          if ($item['name'] == 'Yes') {
                              $target = '.custom_landlord_cooperating_agent';
                          } elseif ($item['name'] == 'No') {
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
                    <div class="form-group custom_landlord_cooperating_agent d-none">
                      <label class="fw-bold">What compensation will the landlord pay a cooperating agent if the tenant
                        has
                        an
                        agent? </label>
                      <input type="text" class="form-control has-icon" placeholder=" "
                        name="custom_landlord_cooperating_agent" data-icon="fa-solid fa-ruler-combined"
                        id="custom_landlord_cooperating_agent" required />
                    </div>
                  </div>
                  <div class="form-group custom_landlord_fee_list d-none">
                    <label class="fw-bold">What service(s) would you like the agent to provide? </label>
                    <input type="text" class="form-control has-icon" placeholder=" "
                      name="custom_real_estatet_fee_list" data-icon="fa-solid fa-ruler-combined"
                      id="custom_real_estatet_fee_list" required />
                  </div>
                </div>


              </div>


              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold"> Does the hired agent need to meet in person at the property?
                  </label>
                  <select class="grid-picker" name="has_non_negotiable_amenities" id="has_non_negotiable_amenities"
                    style="justify-content: flex-start;" required>
                    <option value="">Select</option>
                    @foreach ($yes_or_nos as $item)
                      @php
                        if ($item['name'] == 'Yes') {
                            $target = '.custom_bathroom_residential_and_income';
                        } elseif ($item['name'] == 'No') {
                            $target = '.custom_non_negotiable_terms';
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

                <div class="form-group custom_non_negotiable_terms d-none">
                  <label class="fw-bold" for="custom_non_negotiable_terms">When does the service need to be
                    provided?</label>
                  <input type="date" name="custom_non_negotiable_terms" id="custom_non_negotiable_terms"
                    class="form-control has-icon" data-icon="fa-solid fa-calendar-days" required>
                </div>

                <div class="custom_bathroom_residential_and_income d-none">
                  <div class="form-group">
                    <label class="fw-bold">Please provide details about the individual the agent is meeting:</label>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold"> Full Name: </label>
                    <input type="text" name="contact_name" id="contact_name" class="form-control has-icon"
                      placeholder="" data-icon="fa-solid fa-user" value="{{ Auth::user()->name }}" required />
                  </div>

                  <div class="form-group">
                    <label class="fw-bold"> Phone Number: </label>
                    <input type="text" name="contact_phone" id="contact_phone" class="form-control has-icon"
                      placeholder="" data-icon="fa-solid fa-phone" value="{{ Auth::user()->phone }}" required />
                  </div>

                  <div class="form-group">
                    <label class="fw-bold"> Email Address: </label>
                    <input type="text" name="contact_email" id="contact_email" class="form-control has-icon"
                      placeholder="" data-icon="fa-solid fa-envelope" value="{{ Auth::user()->email }}" required />
                  </div>

                  <div class="form-group">
                    <label class="fw-bold"> Address of the property where the agent needs to meet: </label>
                    <input type="text" name="agent_need" id="agent_need"
                      class="form-control has-icon search_places" placeholder="" data-type="address"
                      data-icon="fa-solid fa-location" required />
                  </div>
                  <div class="form-group">
                    <label class="fw-bold">Date and time the agent needs to meet at the property: </label>
                    <div class="d-flex">
                      <div class="pe-4"><input type="date" name="meeting_date" id="meeting_date"
                          class="form-control has-icon" placeholder="" data-icon="fa-solid fa-calendar" value=""
                          required /></div>
                      <div><input type="time" name="meeting_time" id="meeting_time" class="form-control has-icon"
                          placeholder="" data-icon="fa-solid fa-clock" value="" required /></div>
                    </div>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold"> What are the showing instructions?
                    </label>
                    <textarea name="instructions" id="instructions" class="form-control" rows="5" placeholder=""></textarea>
                  </div>
                  <div class="form-group">
                    <label class="fw-bold"> What additional private details would you like to share with the agent?
                    </label>
                    <input type="text" name="private_details" id="private_details" class="form-control has-icon"
                      placeholder="" data-icon="fa-solid fa-ruler-combined" required />
                  </div>
                  <p>Note: Information on the person is provided in the showing instructions and is only disclosed to the
                    hired agent for privacy reasons.</p>
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold">What compensation are you offering to the agent providing the service(s)? Please
                    enter an amount or type 'Negotiable'.</label>
                  <input type="text" name="offerer_commission" class="form-control has-icon"
                    data-icon="fa-solid fa-ruler-combined" required>
                </div>
              </div>
              <div class="wizard-step">
                <div class="form-group">
                  <label class="fw-bold"> What additional details would you like to share with the agent? </label>
                  <textarea name="public_note" id="public_note" class="form-control" cols="30" rows="10" required></textarea>
                </div>
              </div>
              <div class="d-flex justify-content-between form-group mt-4">
                <div>
                  <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
                </div>
                <div>
                  <button type="button" class="wizard-step-next btn btn-success btn-lg text-600"
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
  <template class="questions_temp">
    <div class="wizard-step cma_questions_">
      <div class="form-group">
        <label class="fw-bold">
          What price does the landlord want to ideally list the property for? *
        </label>
        <input type="text" name="cma_q1" id="cma_q1" class="form-control has-icon"
          data-icon="fa-solid fa-dollar" placeholder="0.00" required>
      </div>
    </div>

    <div class="wizard-step cma_questions_">
      <div class="form-group">
        <label class="fw-bold">
          Picture link for the most accurate analysis
        </label>
        <input type="url" name="cma_q2" id="cma_q2" class="form-control">
      </div>
    </div>

    <div class="wizard-step cma_questions_">
      <div class="form-group">
        <label class="fw-bold">
          Video link for the most accurate analysis
        </label>
        <input type="url" name="cma_q3" id="cma_q3" class="form-control">
      </div>
    </div>

  </template>
@endsection
@push('scripts')
  <script src="https://cdn.jsdelivr.net/gh/bbbootstrap/libraries@main/choices.min.js"></script>
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
      });
    });


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
      check_custom();
      $(elm).parent().children('select').trigger('change');
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
      console.log(inputField)
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

    // Landlord
    $('#landLordOptions').change(function() {
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      if (targetClass === '.custom_landlord_property') {
        $('.landlordOpt').removeClass('d-none').prop('disabled', false);
      } else {
        $('.landlordOpt').addClass('d-none').prop('disabled', true);
      }
    });
    // Seller
    $('#seller_flat_fee_list').change(function() {
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      if (targetClass === '.custom_seller_property') {
        $('.sellerOpt').removeClass('d-none').prop('disabled', false);
      } else {
        $('.sellerOpt').addClass('d-none').prop('disabled', true);
      }
    });
    // Real Estate
    $('#real_estate_flat_fee_list').change(function() {
      var selectedOption = $(this).find(':selected');
      var targetClass = selectedOption.data('target');
      if (targetClass === '.custom_real_state_property') {
        $('.realEstateOpt').removeClass('d-none').prop('disabled', false);
      } else {
        $('.realEstateOpt').addClass('d-none').prop('disabled', true);
      }
    });
  </script>
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush
