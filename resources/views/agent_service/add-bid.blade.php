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

        <form class="p-4 pt-0 mainform" action="{{ route('agent.service.auction.bid.save', @$auction->id) }}" method="POST"
          enctype="multipart/form-data">
          @csrf
          @php
            $yes_or_nos = [
                ['name' => 'Yes', 'target' => '', 'icon' => 'fa-regular fa-circle-check'],
                ['name' => 'No', 'target' => '', 'icon' => 'fa-regular fa-circle-xmark'],
            ];
          @endphp


          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold" for="agent">What is the total fee that the agent will charge for the
                service?</label>
              <input type="text" name="agent_fee" placeholder="" id="agent_fee"
                class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar-sign" data-msg-required=""
                value="{{ old('agent_fee') }}" required>
            </div>
          </div>
          <div class="wizard-step">
            @php
              $cancel_fee = [
                  [
                      'name' => 'Yes',
                      'target' => '.custom_fee_cancel',
                      'icon' => '<i class="fa-regular fa-circle-check"></i>',
                  ],
                  ['name' => 'No', 'target' => '', 'icon' => '<i class="fa-regular fa-circle-xmark"></i>'],
              ];
            @endphp
            <div class="form-group">
              <label class="fw-bold" for="agent">Does the agent charge any fees for cancellations after being hired?
              </label>
              <select name="fee_cancel " class="grid-picker" style="justify-content: flex-start;" required>
                @foreach ($cancel_fee as $item)
                  <option value="{{ $item['name'] }}" style="width:calc(33.3% - 10px);" class="card flex-column"
                    data-target="{{ $item['target'] }}" data-icon="{{ $item['icon'] }}">
                    {{ $item['name'] }}</option>
                @endforeach
              </select>
              <div class="form-group custom_fee_cancel d-none">
                <label class="fw-bold" for="">What is the agent's cancellation fee?</label>
                <input type="text" name="custom_fee_cancel" placeholder="" id="custom_fee_cancel"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-dollar-sign"
                  data-msg-required="Enter first name" value="{{ old('custom_fee_cancel') }}" autofocus>
              </div>
            </div>
          </div>

          <div class="wizard-step">
            @php
              $type_of_client = [
                  ['name' => 'Buyer', 'target' => '.cutom_buyer_list_option', 'icon' => 'fa-regular fa-circle-check'],
                  ['name' => 'Seller', 'target' => '.cutom_seller_list_option', 'icon' => 'fa-regular fa-circle-check'],
                  [
                      'name' => 'Landlord',
                      'target' => '.cutom_landlord_list_option',
                      'icon' => 'fa-regular fa-circle-check',
                  ],
                  ['name' => 'Tenant', 'target' => '.cutom_tenant_list_option', 'icon' => 'fa-regular fa-circle-check'],
                  [
                      'name' => 'Real Estate Agent',
                      'target' => '.custom_real_estate_agent_option',
                      'icon' => 'fa-regular fa-circle-check',
                  ],
              ];
              $buyer_listings = [
                  [
                      'target' => '',
                      'name' =>
                          'Conduct a thorough comparative market analysis (CMA) to determine the property\'s value and pricing strategy.',
                  ],
                  ['target' => '', 'name' => 'List the buyer\'s preferences on the BidYourOffer.com platform.'],
                  ['target' => '', 'name' => 'Provide property investment guidance.'],
                  ['target' => '', 'name' => 'Offer risk assessment and investment advice.'],
                  ['target' => '', 'name' => 'Perform real estate market research.'],
                  ['target' => '', 'name' => 'Analyze property market trends.'],
                  ['target' => '', 'name' => 'Advise on distressed property transactions.'],
                  [
                      'target' => '',
                      'name' =>
                          'Send prompt email notifications for newly listed properties meeting buyers\' criteria.',
                  ],
                  ['target' => '', 'name' => 'Assist in obtaining pre-approval and financing options.'],
                  ['target' => '', 'name' => 'Arrange a showing for a single property.'],
                  ['target' => '', 'name' => 'Arrange showings for multiple properties.'],
                  ['target' => '', 'name' => 'Conduct a video tour for a single property.'],
                  ['target' => '', 'name' => 'Conduct video tours for multiple properties.'],
                  [
                      'target' => '',
                      'name' => 'Assist in drafting residential sale agreements and required addendums/disclosures.',
                  ],
                  ['target' => '', 'name' => 'Assist in negotiating residential sale agreements.'],
                  [
                      'target' => '',
                      'name' => 'Assist in drafting commercial sale agreements and required addendums/disclosures.',
                  ],
                  ['target' => '', 'name' => 'Assist in negotiating commercial sale agreements.'],
                  [
                      'target' => '',
                      'name' => 'Coordinate interactions with title companies, escrow officers, and other parties.',
                  ],
                  ['target' => '', 'name' => 'Oversee the home inspection process.'],
                  ['target' => '', 'name' => 'Verify completion of agreed-upon repairs.'],
                  ['target' => '', 'name' => 'Review and explain contractual documents.'],
                  [
                      'target' => '',
                      'name' =>
                          'Market listing on various groups, pages, and affiliates through QR codes or links to the listing on the BidYourOffer.com platform.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Promote buyers\' criteria listings on social media through QR codes or links to the listing on the BidYourOffer.com platform.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Distribute postcards to the desired neighborhood with a QR code or listing link leading to the property\'s listing on the BidYourOffer.com platform.',
                  ],
                  ['target' => '', 'name' => 'Assist with real estate email marketing campaigns.'],
                  ['target' => '', 'name' => 'Facilitate communication with professionals involved.'],
                  ['target' => '', 'name' => 'Provide guidance throughout the purchase transaction.'],
                  ['target' => '', 'name' => 'Offer post-purchase assistance and recommend service providers.'],
                  ['target' => '.custom_buyers_flat_fee_list', 'name' => 'Other - Add additional services as needed.'],
              ];

              $real_estate_agent_listings = [
                  [
                      'target' => '',
                      'name' =>
                          'Conduct a Comparative Market Analysis to evaluate the fair market value of a property.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Conduct a Rental Value Analysis to ascertain the optimal rental price of a property.',
                  ],
                  ['target' => '', 'name' => 'Show a property to a client.'],
                  ['target' => '', 'name' => 'Show multiple properties to a client.'],
                  [
                      'target' => '',
                      'name' =>
                          'Oversee transactions by composing, arranging, and disseminating essential legal paperwork, including contracts, disclosures, and addendums for a residential property sale.',
                  ],
                  ['target' => '', 'name' => 'Assist in negotiating residential sale agreements.'],
                  [
                      'target' => '',
                      'name' =>
                          'Oversee transactions by composing, arranging, and disseminating essential legal paperwork, including contracts, disclosures, and addendums for a commercial property sale.',
                  ],
                  ['target' => '', 'name' => 'Assist in negotiating commercial sale agreements.'],
                  [
                      'target' => '',
                      'name' =>
                          'Oversee transactions by composing, arranging, and disseminating essential legal paperwork, including leases, disclosures, and addendums for a residential lease.',
                  ],
                  ['target' => '', 'name' => 'Assist in negotiating residential lease agreements.'],
                  [
                      'target' => '',
                      'name' =>
                          'Oversee transactions by composing, arranging, and disseminating essential legal paperwork, including leases, disclosures, and addendums for a commercial lease.',
                  ],
                  ['target' => '', 'name' => 'Assist in negotiating commercial lease agreements.'],
                  [
                      'target' => '',
                      'name' =>
                          'Schedule and manage a certified home inspector to examine the condition of a property.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Arrange and oversee a professional photographer to capture high-quality images of a property.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Plan and direct a professional videographer to create a virtual tour of a property.',
                  ],
                  ['target' => '', 'name' => 'Organize and supervise a home staging professional.'],
                  [
                      'target' => '',
                      'name' =>
                          'Schedule and manage a handyman to perform necessary repairs or improvements on a property.',
                  ],
                  ['target' => '', 'name' => 'Meet an appraiser.'],
                  ['target' => '', 'name' => 'Meet an inspector.'],
                  ['target' => '', 'name' => 'Conduct an open house for a property.'],
                  ['target' => '', 'name' => 'Produce a video tour of a property.'],
                  ['target' => '', 'name' => 'Produce interior photos of a property.'],
                  ['target' => '', 'name' => 'Take aerial photos of a property.'],
                  ['target' => '', 'name' => 'Provide 3D Tour for a property.'],
                  ['target' => '', 'name' => 'Provide virtual staging for a property.'],
                  ['target' => '', 'name' => 'Provide a floor plan of a property.'],
                  [
                      'target' => '',
                      'name' =>
                          'Provide mentorship opportunities for novice agents to learn from experienced colleagues.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Hire an agent to provide support during your listing presentation to a seller.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Hire an agent to provide support during your listing presentation to a buyer.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Hire an agent to provide support during your listing presentation to a landlord.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Hire an agent to provide support during your listing presentation to a tenant.',
                  ],
                  ['target' => '', 'name' => 'Install a lockbox at a property.'],
                  ['target' => '', 'name' => 'Manage the installation and removal of home sale-related yard signs.'],
                  [
                      'target' => '.custom_real_state_property',
                      'name' => 'List the property on the Bid Your Offer platform.',
                  ],
                  ['target' => '.custom_real_state_property', 'name' => 'List the property on the MLS.'],
                  [
                      'target' => '.custom_real_state_property',
                      'name' =>
                          'List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and others, to increase visibility and exposure.',
                  ],
                  [
                      'target' => '.custom_real_state_property',
                      'name' => 'List the property on Loopnet, a significant commercial real estate website.',
                  ],
                  [
                      'target' => '.custom_real_state_property',
                      'name' => 'List the property on Crexi, another major commercial real estate website.',
                  ],
                  ['target' => '', 'name' => 'Market a property to various groups, pages, and affiliates.'],
                  ['target' => '', 'name' => 'Promote the property on various social media platforms.'],
                  ['target' => '', 'name' => 'Respond to phone calls and arrange showings.'],
                  [
                      'target' => '',
                      'name' =>
                          'Dispatch property details via email to clients seeking homes that meet their criteria as soon as the property is listed.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Conduct comprehensive tenant screening that includes an application process with credit, criminal, background, eviction, and income verification checks.',
                  ],
                  ['target' => '', 'name' => 'Distribute postcards to the neighborhood or an agent-selected area.'],
                  ['target' => '', 'name' => 'Conduct a tenant move-in/move-out inspection.'],
                  ['target' => '', 'name' => 'Provide consultation on property management and investment.'],
                  [
                      'target' => '',
                      'name' => 'Facilitate connections with local contractors, suppliers, and service providers.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Conduct walk-throughs before closing a sale to ensure agreed-upon repairs or improvements have been done.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Managing communication between buyers, sellers, and their respective agents.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Managing communication between landlords, tenants, and their respective agents.',
                  ],
                  ['target' => '', 'name' => 'Creating and distributing digital brochures or pamphlets.'],
                  ['target' => '', 'name' => 'Coordinate or assist in the move-in or move-out process for tenants.'],
                  ['target' => '', 'name' => 'Assist in the creation of attractive and engaging listing descriptions.'],
                  [
                      'target' => '',
                      'name' =>
                          'Coordinate interactions with title companies, escrow officers, and other key parties in a real estate transaction.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Provide advice on the process of buying or selling distressed properties.',
                  ],
                  ['target' => '', 'name' => 'Provide social media management and marketing services.'],
                  ['target' => '', 'name' => 'Assist with property inspections and coordinating necessary repairs.'],
                  ['target' => '', 'name' => 'Offer guidance on property investment strategies.'],
                  [
                      'target' => '',
                      'name' => 'Provide support in developing and implementing lead nurturing campaigns.',
                  ],
                  ['target' => '', 'name' => 'Conduct property market trend analysis and reporting.'],
                  ['target' => '', 'name' => 'Assist with real estate email marketing campaigns.'],
                  [
                      'target' => '',
                      'name' => 'Provide support in developing and implementing client referral programs.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Offer guidance on real estate investment risk assessment and mitigation.',
                  ],
                  ['target' => '', 'name' => 'Assist with real estate lead conversion and follow-up strategies.'],
                  [
                      'target' => '',
                      'name' => 'Provide support in developing and implementing real estate advertising campaigns.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Assist with real estate market research for specific demographics or target markets.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Assist with property management tasks, such as tenant communication and lease renewals.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Assist with marketing efforts, including creating and distributing promotional materials, marketing campaigns, advertising strategies, online presence, social media management, and content creation for blogs or websites.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Conduct administrative tasks such as data entry, paperwork processing, appointment scheduling, and customer relationship management (CRM) implementation and management.',
                  ],
                  ['target' => '', 'name' => 'Mentor and/or shadow an agent.'],
                  [
                      'target' => '',
                      'name' =>
                          'Co-host open house events, partnering to showcase multiple properties in the same neighborhood, offering a convenient experience for potential buyers.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Organize networking events or workshops, creating opportunities for agents to exchange expertise, discuss market trends, and foster a collaborative and learning-oriented real estate community.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Develop and execute joint marketing campaigns, pooling resources for broader audience reach and potential benefits for both agents and their clients.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Offer assistance with international transactions, navigating legal, financial, and logistical complexities when dealing with foreign buyers or sellers.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Deliver training sessions on advanced digital tools and technologies, enhancing real estate services and client engagement.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Collaborate on co-authoring industry-specific resources such as ebooks, guides, or articles, providing insights and valuable information to clients and other agents.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Coordinate joint social media campaigns to maximize visibility and engagement, especially during special events or promotions.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Organize and co-host webinars or seminars on relevant real estate topics, delivering valuable information to agents and clients.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Create a shared repository of templates, checklists, and tools for agents to streamline processes and ensure consistency in services.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Collaboratively participate in community events, sponsorships, or charity initiatives to reinforce a positive image of real estate professionals within the local community.',
                  ],
                  ['target' => '.custom_real_estatet_fee_list', 'name' => 'Other - Add additional services as needed.'],
              ];

              $seller_listings = [
                  [
                      'target' => '',
                      'name' =>
                          'Conduct a thorough comparative market analysis (CMA) to determine the property\'s value and pricing strategy.',
                  ],
                  [
                      'target' => '.custom_seller_property',
                      'name' => 'List the property on the Bid Your Offer platform.',
                  ],
                  ['target' => '.custom_seller_property', 'name' => 'List the property on the MLS.'],
                  [
                      'target' => '.custom_seller_property',
                      'name' =>
                          'List the property on major real estate websites, such as Zillow, Trulia, Realtor.com, Homes.com, Homesnap, Hotpads, and others, to increase visibility and exposure.',
                  ],
                  [
                      'target' => '.custom_seller_property',
                      'name' => 'List the property on Loopnet, a significant commercial real estate website.',
                  ],
                  [
                      'target' => '.custom_seller_property',
                      'name' => 'List the property on Crexi, another major commercial real estate website.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing on the BidYourOffer.com platform.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Distribute postcards featuring the seller\'s listing within their neighborhood with a QR code or listing link leading to the property\'s listing.',
                  ],
                  ['target' => '', 'name' => 'Host an Open House.'],
                  [
                      'target' => '',
                      'name' =>
                          'Send email alerts to buyers seeking properties matching the criteria as soon as the property is listed through the MLS.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Provide regular updates on market activity, showings, and feedback from potential buyers.',
                  ],
                  ['target' => '', 'name' => 'Show the seller\'s property to a potential buyer.'],
                  ['target' => '', 'name' => 'Show the seller\'s property to multiple potential buyers.'],
                  ['target' => '', 'name' => 'Respond to phone calls and arrange showings.'],
                  [
                      'target' => '',
                      'name' => 'Schedule and manage a handyman for necessary repairs or improvements on a property.',
                  ],
                  ['target' => '', 'name' => 'Meet with an appraiser.'],
                  ['target' => '', 'name' => 'Meet with an inspector.'],
                  ['target' => '', 'name' => 'Install a lockbox on the property.'],
                  ['target' => '', 'name' => 'Manage the installation and removal of home sale-related yard signs.'],
                  ['target' => '', 'name' => 'Negotiate terms and conditions with buyers and/or their agents.'],
                  ['target' => '', 'name' => 'Assist in the creation of attractive and engaging listing descriptions.'],
                  ['target' => '', 'name' => 'Offer guidance on property investment strategies.'],
                  [
                      'target' => '',
                      'name' => 'Assist in drafting residential sale agreements and required addendums/disclosures.',
                  ],
                  ['target' => '', 'name' => 'Assist in negotiating residential sale agreements.'],
                  [
                      'target' => '',
                      'name' => 'Assist in drafting commercial sale agreements and required addendums/disclosures.',
                  ],
                  ['target' => '', 'name' => 'Assist in negotiating commercial sale agreements.'],
                  ['target' => '', 'name' => 'Review and explain contractual documents.'],
                  [
                      'target' => '',
                      'name' =>
                          'Coordinate interactions with title companies, escrow officers, and other key parties in real estate transactions.',
                  ],
                  ['target' => '', 'name' => 'Conduct property market trend analysis and reporting.'],
                  ['target' => '', 'name' => 'Assist with real estate email marketing campaigns.'],
                  [
                      'target' => '',
                      'name' => 'Offer guidance on real estate investment risk assessment and mitigation.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Assist with real estate market research for specific demographics or target markets.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Provide advice on the process of buying or selling distressed properties.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Provide guidance and assistance in preparing the property for sale, including recommendations for repairs or improvements.',
                  ],
                  ['target' => '', 'name' => 'Provide professional photos showcasing the property\'s best features.'],
                  [
                      'target' => '',
                      'name' => 'Provide a professional video to showcase the property\'s interior and exterior.',
                  ],
                  ['target' => '', 'name' => 'Provide a 3D tour to showcase the property\'s interior.'],
                  [
                      'target' => '',
                      'name' =>
                          'Provide a floor plan of the property to highlight its layout and spatial configuration.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Provide virtual staging to enhance the property\'s visual appeal and attract potential buyers.',
                  ],
                  ['target' => '', 'name' => 'Offer recommendations for home staging professionals.'],
                  [
                      'target' => '',
                      'name' =>
                          'Conduct walk-throughs before closing a sale to ensure agreed-upon repairs or improvements have been done.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Offer post-sale assistance, such as recommending reputable service providers for moving, repairs, or other post-sale needs.',
                  ],
                  ['target' => '.custom_seller_fee_list', 'name' => 'Other - Add additional services as needed.'],
              ];

              $tenant_listings = [
                  [
                      'target' => '',
                      'name' =>
                          'Conduct a thorough rental market analysis (RMA) to determine the property\'s value and pricing strategy.',
                  ],
                  ['target' => '', 'name' => 'List the tenant\'s preferences on the BidYourOffer.com platform.'],
                  ['target' => '', 'name' => 'Arrange showings for a single property.'],
                  ['target' => '', 'name' => 'Arrange showings for multiple properties.'],
                  ['target' => '', 'name' => 'Conduct a video tour for a single property.'],
                  ['target' => '', 'name' => 'Conduct video tours for multiple properties.'],
                  [
                      'target' => '',
                      'name' => 'Negotiate terms and conditions with a prospective landlord and/or their agent.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Assist in drafting residential lease agreements and any required addendums/disclosures.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Assist in negotiating residential lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Assist in drafting commercial lease agreements and any required addendums/disclosures.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Assist in negotiating commercial lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Send prompt email notifications containing properties that match the tenant\'s criteria as soon as they are listed, ensuring access to the most up-to-date listings.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Market the tenant\'s listing on various groups, pages, and affiliates, along with a QR code or listing link leading to the listing on BidYourOffer.com.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Promote the tenant\'s listing on social media platforms using a QR code or listing link leading to the listing on BidYourOffer.com.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Distribute postcards to the desired neighborhood with a QR code or listing link leading to the property\'s listing on the BidYourOffer.com platform.',
                  ],
                  ['target' => '', 'name' => 'Assist with real estate email marketing campaigns.'],
                  [
                      'target' => '',
                      'name' => 'Assist with the tenant\'s rental application process, providing guidance and support.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Coordinate and oversee the move-in process, including inspections and key handovers.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Provide resources and recommendations for local amenities, such as schools, parks, and healthcare facilities.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Facilitate communication between the tenant and the landlord, their agent, or the property management company.',
                  ],
                  ['target' => '', 'name' => 'Offer resources and information on tenant rights and responsibilities.'],
                  [
                      'target' => '',
                      'name' =>
                          'Provide guidance on lease renewal options and negotiate rent adjustments if necessary.',
                  ],
                  ['target' => '.custom_tenant_fee_list', 'name' => 'Other - Add additional services as needed.'],
              ];

              $landlord_listings = [
                  [
                      'target' => '',
                      'name' =>
                          'Conduct a thorough rental market analysis (RMA) to determine the property\'s value and pricing strategy.',
                  ],
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
                      'name' => 'List the property on Loopnet, a significant commercial real estate website.',
                  ],
                  [
                      'target' => '.custom_landlord_property',
                      'name' => 'List the property on Crexi, another major commercial real estate website.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Market the property to various groups, pages, and affiliates to generate interest and leads with a QR code or listing link leading to the property\'s listing.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Promote the property on social media platforms with a QR code or listing link leading to the property\'s listing.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Distribute postcards featuring the landlord\'s listing within their neighborhood with a QR code or listing link leading to the property\'s listing.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Conduct tenant screening with a comprehensive application process, including credit, criminal, background, eviction, and income verification checks.',
                  ],
                  ['target' => '', 'name' => 'Provide professional photos showcasing the property\'s best features.'],
                  [
                      'target' => '',
                      'name' => 'Provide a professional video to showcase the property\'s interior and exterior.',
                  ],
                  ['target' => '', 'name' => 'Provide a 3D tour to showcase the property\'s interior.'],
                  [
                      'target' => '',
                      'name' =>
                          'Provide a floor plan of the property to highlight its layout and spatial configuration.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Provide virtual staging to enhance the property\'s visual appeal and attract potential tenants.',
                  ],
                  ['target' => '', 'name' => 'Host an Open House.'],
                  [
                      'target' => '',
                      'name' =>
                          'Send email alerts to tenants seeking properties matching the criteria as soon as the property is listed through the MLS.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Provide regular updates on market activity, showings, and feedback from potential tenants.',
                  ],
                  ['target' => '', 'name' => 'Show the landlord\'s property to a potential tenant.'],
                  ['target' => '', 'name' => 'Show the landlord\'s property to multiple potential tenants.'],
                  ['target' => '', 'name' => 'Respond to phone calls and arrange showings.'],
                  [
                      'target' => '',
                      'name' => 'Schedule and manage a handyman for necessary repairs or improvements on a property.',
                  ],
                  ['target' => '', 'name' => 'Install a lockbox on the property.'],
                  ['target' => '', 'name' => 'Manage the installation and removal of home sale-related yard signs.'],
                  ['target' => '', 'name' => 'Negotiate terms and conditions with tenants and/or their agents.'],
                  ['target' => '', 'name' => 'Assist in the creation of attractive and engaging listing descriptions.'],
                  [
                      'target' => '',
                      'name' => 'Assist in drafting residential lease agreements and required addendums/disclosures.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Assist in negotiating residential lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                  ],
                  [
                      'target' => '',
                      'name' => 'Assist in drafting commercial lease agreements and required addendums/disclosures.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Assist in negotiating commercial lease terms, including rental price, lease duration, and any additional clauses or provisions.',
                  ],
                  ['target' => '', 'name' => 'Review and explain contractual documents.'],
                  ['target' => '', 'name' => 'Assist with real estate email marketing campaigns.'],
                  [
                      'target' => '',
                      'name' => 'Coordinate property maintenance and repairs through trusted contractors and vendors.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Offer ongoing property management services, including rent collection, property inspections, and tenant communication.',
                  ],
                  [
                      'target' => '',
                      'name' =>
                          'Handle tenant inquiries, maintenance requests, and resolve any issues that may arise during the tenancy.',
                  ],
                  ['target' => '', 'name' => 'Coordinate or assist in the move-in or move-out process for tenants.'],
                  ['target' => '.custom_landlord_fee_list', 'name' => 'Other - Add additional services as needed.'],
              ];

            @endphp
            <div class="form-group">
              <label class="fw-bold">Is the agent providing the service for a Buyer, Seller, Landlord, Tenant, or Real
                Estate Agent? </label>
              <select class="grid-picker" name="type_of_client" id="type_of_client" style="justify-content: flex-start;"
                required>
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
              <label class="fw-bold buyerAgent">Select the service that the agent will provide:</label>
              <select name="buyers_flat_fee_list[]" id="buyers_flat_fee_list" class="grid-picker"
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
                <label class="fw-bold">What additional service(s) will the agent provide? </label>
                <input type="text" class="form-control has-icon" placeholder=" " name="custom_buyers_flat_fee_list"
                  data-icon="fa-solid fa-hand-point-right" id="custom_buyers_flat_fee_list" required />
              </div>
            </div>
            {{-- real estate --}}

            <div class="form-group d-none custom_real_estate_agent_option">
              <label class="fw-bold">
                Select the service that the agent will provide:
              </label>

              <select name="real_estate_flat_fee_list[]" id="real_estate_flat_fee_list" class="grid-picker"
                style="justify-content: flex-start;" multiple required>
                <option value=""></option>
                @foreach ($real_estate_agent_listings as $real_estate_agent_listing)
                  <option value="{{ $real_estate_agent_listing['name'] }}"
                    data-target="{{ $real_estate_agent_listing['target'] }}" class="card flex-row"
                    style="width:calc(100%);"
                    data-icon='<i class="fa-solid fa-hand-point-right" style="font-size:28px;"></i>'>
                    {{ $real_estate_agent_listing['name'] }}
                  </option>
                @endforeach
              </select>
              <div class="form-group custom_real_estatet_fee_list d-none">
                <label class="fw-bold">What additional service(s) will the agent provide? </label>
                <input type="text" class="form-control has-icon" placeholder=" " name="custom_real_estatet_fee_list"
                  data-icon="fa-solid fa-hand-point-right" id="custom_real_estatet_fee_list" required />
              </div>
            </div>
            <div class="form-group d-none custom_real_state_property">
              <label class="fw-bold"> If the agent is representing a client will the agent’s client pay a commission
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
            </div>
            <div class="form-group custom_real_state_cooperating_agent d-none">
              <label class="fw-bold">What compensation will the agent’s client pay a cooperating agent?</label>
              <input type="text" class="form-control has-icon" placeholder=" "
                name="custom_real_state_cooperating_agent" data-icon="fa-solid fa-ruler-combined"
                id="custom_real_state_cooperating_agent" required />
            </div>

            {{-- seller --}}

            <div class="form-group d-none cutom_seller_list_option">
              <label class="fw-bold sellerAgent">Select the service that the agent will provide: </label>

              <select name="seller_flat_fee_list[]" id="seller_flat_fee_list" class="grid-picker"
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
              <div class="form-group custom_seller_fee_list d-none">
                <label class="fw-bold">What additional service(s) will the agent provide? </label>
                <input type="text" class="form-control has-icon" placeholder=" "
                  name="custom_real_estatet_fee_list" data-icon="fa-solid fa-hand-point-right"
                  id="custom_real_estatet_fee_list" required />
              </div>
            </div>
            <div class="form-group d-none custom_seller_property">
              <label class="fw-bold"> If the buyer is represented by an agent, will the seller pay a commission to
                the buyer's agent?
              </label>
              <select class="grid-picker" name="has_non_negotiable_amenities" id="has_non_negotiable_amenities"
                style="justify-content: flex-start;" required>
                <option value="">Select</option>
                @foreach ($yes_or_nos as $item)
                  @php
                    if ($item['name'] == 'Yes') {
                        $target = '.custom_seller_cooperating_agent';
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
            </div>
            <div class="form-group custom_seller_cooperating_agent d-none">
              <label class="fw-bold">What compensation will the seller pay a cooperating agent if the buyer has an
                agent? </label>
              <input type="text" class="form-control has-icon" placeholder=" "
                name="custom_seller_cooperating_agent" data-icon="fa-solid fa-ruler-combined"
                id="custom_seller_cooperating_agent" required />
            </div>

            {{-- Tenant --}}

            <div class="form-group d-none cutom_tenant_list_option">
              <label class="fw-bold">
                <label class="fw-bold tenantAgent">Select the service that the agent will provide: </label>
              </label>

              <select name="tenant_flat_fee_list[]" id="seller_flat_fee_list" class="grid-picker"
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
                <label class="fw-bold">What additional service(s) will the agent provide? </label>
                <input type="text" class="form-control has-icon" placeholder=" "
                  name="custom_real_estatet_fee_list" data-icon="fa-solid fa-hand-point-right"
                  id="custom_real_estatet_fee_list" required />
              </div>
            </div>

            {{-- landlord --}}
            <div class="form-group d-none cutom_landlord_list_option">
              <label class="fw-bold landlordAgent">Select the service that the agent will provide: </label>

              <select name="tenant_flat_fee_list[]" id="seller_flat_fee_list" class="grid-picker"
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
              <div class="form-group custom_landlord_fee_list d-none">
                <label class="fw-bold">What additional service(s) will the agent provide? </label>
                <input type="text" class="form-control has-icon" placeholder=" "
                  name="custom_real_estatet_fee_list" data-icon="fa-solid fa-hand-point-right"
                  id="custom_real_estatet_fee_list" required />
              </div>
            </div>
            <div class="form-group d-none custom_landlord_property">
              <label class="fw-bold"> If the landlord is represented by an agent, will the landlord pay a
                commission to the tenant’s agent?
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
            </div>
            <div class="form-group custom_landlord_cooperating_agent d-none">
              <label class="fw-bold">What compensation will the landlord pay a cooperating agent if the tenant has an
                agent? </label>
              <input type="text" class="form-control has-icon" placeholder=" "
                name="custom_landlord_cooperating_agent" data-icon="fa-solid fa-ruler-combined"
                id="custom_landlord_cooperating_agent" required />
            </div>



          </div>




          <div class="wizard-step">
            <div class="form-group">
              <label class="fw-bold">About Agent:</label>
              <textarea class="form-control" name="bio" rows="5" required>{{ old('bio') }}</textarea>
            </div>

          </div>


          <div class="wizard-step">

            <div class="form-group">
              <label class="fw-bold">Why should you be hired as their @if (@$auction->get->service_type == 'Referral agent Service')
                  referred
                @endif agent? </label>
              <textarea class="form-control" name="why_hire_you" rows="5" required>{{ old('why_hire_you') }}</textarea>
            </div>
            <div class="form-group">
              <label class="fw-bold">What sets you apart from other agents? </label>
              <textarea class="form-control" name="what_sets_you_apart" rows="5" required>{{ old('what_sets_you_apart') }}</textarea>
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
              <input type="text" name="agent_licensed" id="agent_licensed"
                value="{{ @$auction->get->agent_licensed }}" class="form-control has-icon"
                data-icon="fa-solid fa-calendar-days" required>
            </div>
          </div>

          @if (@$auction->get->service_type == 'Referral agent Service')
            <div class="wizard-step">
              @php
                $services_data = [];
                $services_data[] = ['target' => '', 'name' => 'Comparative Market Analysis on a property for a Buyer'];
                $services_data[] = ['target' => '', 'name' => 'Comparative Market Analysis on a property for a Seller'];
                $services_data[] = ['target' => '', 'name' => 'Schedule Showings for a Buyer'];
                $services_data[] = [
                    'target' => '',
                    'name' => 'Transaction management (Writing and sending out contracts, disclosures, and addendums)',
                ];
                $services_data[] = ['target' => '', 'name' => 'Schedule Photographer'];
                $services_data[] = ['target' => '', 'name' => 'Schedule a Home Inspector'];
                $services_data[] = ['target' => '', 'name' => 'Schedule Videographer'];
                $services_data[] = ['target' => '', 'name' => 'Schedule a Home stager'];
                $services_data[] = ['target' => '', 'name' => 'Schedule a Handyman'];
                $services_data[] = ['target' => '', 'name' => 'Schedule vendor (fill in blank)'];
                $services_data[] = ['target' => '', 'name' => 'Provide a Home stager service.'];
                $services_data[] = ['target' => '', 'name' => 'Meet a Home Inspector'];
                $services_data[] = ['target' => '', 'name' => 'Meet an Appraiser'];
                $services_data[] = ['target' => '', 'name' => 'Meet a vendor (fill in blank of which vendor)'];
                $services_data[] = ['target' => '', 'name' => 'Meeting a Buyer for one showing'];
                $services_data[] = ['target' => '', 'name' => 'Meet a Buyer for 2 showings'];
                $services_data[] = ['target' => '', 'name' => 'Meet a Buyer for 3 showings'];
                $services_data[] = ['target' => '', 'name' => 'Meet a Buyer for (blank) amount of showings'];
                $services_data[] = ['target' => '', 'name' => 'Hosting an Open House'];
                $services_data[] = ['target' => '', 'name' => 'Do a video tour of a property'];
                $services_data[] = ['target' => '', 'name' => 'Take pictures of a property'];
                $services_data[] = [
                    'target' => '',
                    'name' => 'Shadow an agent (new agents learn directly from another agent)',
                ];
                $services_data[] = ['target' => '', 'name' => 'Putting a lockbox on a property'];
                $services_data[] = ['target' => '', 'name' => 'Putting a sign up on a property'];
                $services_data[] = ['target' => '', 'name' => 'Listing a property on the MLS'];
                $services_data[] = ['target' => '', 'name' => 'Listing a property on Bid Your Offer'];
                $services_data[] = ['target' => '', 'name' => 'Arranging showings on a listing'];
                $services_data[] = ['target' => '', 'name' => 'Taking phone calls on listings.'];
                $services_data[] = ['target' => '.other_services', 'name' => 'Other - Add additional services as needed.'];
              @endphp
              <div class="form-group">
                <label class="fw-bold">Services Offered:</label>
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
              <div class="form-group other_services @if (@$auction->get->other_services == '' || @$auction->get->other_services == 'null') d-none @endif ">
                <label class="fw-bold">What additional service(s) will the agent provide?</label>
                <input type="text" name="other_services" id="other_services"
                  value="{{ @$auction->get->other_services }}" class="form-control has-icon" data-icon="fa-solid fa-hand-point-right">
              </div>
            </div>
          @endif

          <div class="wizard-step">
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
            <div class="row">
              <div class="col-6 form-group">
                <label class="fw-bold mt-1">Agent Video Presentation:</label>
                <div class="videoBox ">
                  <div class="video bgImg"></div>
                  <div class="form-group videoDiv">
                    <input type="file" class="fileuploader" name="agent_video" style="display: none;"
                      accept="video/*">
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

          </div>
          <div class="wizard-step">
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold" for="first_name">First Name:</label>
                <input type="text" name="agent_first_name" placeholder="John" id="agent_first_name"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user-tie"
                  data-msg-required="Enter first name" value="{{ Auth::user()->first_name }}" autofocus>
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold" for="last_name">Last Name:</label>
                <input type="text" name="agent_last_name" placeholder="Smith" id="agent_last_name"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-user-tie"
                  data-msg-required="Enter last name" value="{{ Auth::user()->last_name }}" autofocus>
              </div>
            </div>
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold" for="agent_phone">Phone Number:</label>
                <input type="text" name="agent_phone" placeholder="" id="agent_phone"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-phone"
                  data-msg-required="Enter Phone Number" value="{{ Auth::user()->phone }}">
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold" for="agent_email">Email:</label>
                <input type="email" name="agent_email" placeholder="john@example.com" id="agent_email"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-envelope"
                  data-msg-required="Enter Email" value="{{ Auth::user()->email }}">
              </div>
            </div>
            <div class="form-group row">
              <div class="form-group col-md-6">
                <label class="fw-bold" for="agent_brokerage">Brokerage:</label>
                <input type="text" name="agent_brokerage" placeholder="" id="agent_brokerage"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-circle-dollar-to-slot"
                  data-msg-required="Enter Brokerage" value="{{ Auth::user()->brokerage }}">
              </div>
              <div class="form-group col-md-6">
                <label class="fw-bold" for="agent_license_no">Real Estate License #:</label>
                <input type="text" name="agent_license_no" placeholder="" id="agent_license_no"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-card"
                  data-msg-required="Enter License Number" value="{{ Auth::user()->license_no }}">
              </div>
            </div>
            <div class="form-group row">
              <div class="form-group col-md-6">

                <label class="fw-bold" for="agent_mls_id">NAR Member ID (NRDS ID): </label>
                <input type="text" name="agent_mls_id" placeholder="" id="agent_mls_id"
                  class="form-control has-icon hide_arrow" data-icon="fa-solid fa-id-badge"
                  data-msg-required="Enter MLS ID" value="{{ Auth::user()->mls_id }}">
              </div>
            </div>
          </div>
          <div class="d-flex justify-content-between form-group mt-4">
            <div>
              <a class="wizard-step-back btn btn-success btn-lg text-600" style="display: none;">Back</a>
            </div>
            <div>
              <a class="wizard-step-next btn btn-success btn-lg text-600" id="nextBtn"
                style="display: none;">Next</a>
              <button type="button" class="wizard-step-finish btn btn-success btn-lg text-600"
                style="display: none;">Save</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
  <template class="add_input">
    <tr>
      <td><input type="file" class="form-control" name="note[]"></td>
    </tr>
  </template>
@endsection
@push('scripts')
  <script>
    function validateVideoSize(input) {
      var fileSize = input.files[0].size; // Size in bytes
      var maxSize = 5 * 1024 * 1024; // 5MB in bytes
      var errorContainer = $('#errorContainer');

      if (fileSize > maxSize) {
        errorContainer.text("Video file size exceeds 5MB limit.").show();
        // Clear the file input
        $(input).val('');
      } else {
        errorContainer.hide().text("");
      }
    }

    function addInput() {
      var city_row = $('.add_input').html();
      $('.newInput').before(city_row);
      initialize();
    }
    $(function() {
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
  </script>
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
        $('#fileSizeError').remove();
        if (this.files[0].size > 10000000 ) {
            $('.videoDiv').after('<span id="fileSizeError" style="color: red;">Please upload a file less than 10MB. Thanks!</span>');
            $(this).val('');
            $('#nextBtn').addClass('disabled');
        } else {
            $('#fileSizeError').remove(); // Remove the error message
            $('#nextBtn').removeClass('disabled'); // Re-enable the click action
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
  <script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_PLACES_API_KEY') }}&libraries=places&callback=initialize">
  </script>
@endpush
