@extends('layouts.main')
@push('styles')
<link rel="stylesheet" href="assets/css/faq.css" />
@endpush
@section('content')
<div class="container">
    <!-- First Section -->
    <div class="faqContent">
      <div class="row">
        <div class="col-sm-12 col-md-6 col-lg-6 text-center">
          <img class="w-100" src="assets/pictures/faq/faq.jpg" alt="faq" />
        </div>
        <div class="col-sm-12 col-md-6 col-lg-6">
          <h1>We're here to help.</h1>
          <p>Bid Your Offer provides a brand-new approach to real estate bidding. This platform allows consumers to enjoy full transparency when it comes to purchasing real estate properties.</p>
          <a href="addListing.html"><button class="btn">Add Auction</button></a>
        </div>
      </div>
    </div>
    <!-- end -->
    <!-- Second Section -->

    <div class="accordion" id="accordionPanelsStayOpenExample">
      <h2>Common FAQ</h2>
      <p>For any additional questions email admin@bidyouroffer.com</p>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-headingOne">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseOne" aria-expanded="true" aria-controls="panelsStayOpen-collapseOne"><h4>Can I Use the Services of a Real Estate Broker to Assist Me as A Buyer?</h4></button>
        </div>
        <div id="panelsStayOpen-collapseOne" class="accordion-collapse collapse show" aria-labelledby="panelsStayOpen-headingOne">
          <div class="accordion-body">Yes, Buyer’s can use the agent they choose. If Buyer is not being represented by a Buyer’s agent and the Buyer would like to be represented we can refer a Buyer’s agent to represent them at no cost to the Buyer.</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-headingTwo">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseTwo" aria-expanded="false" aria-controls="panelsStayOpen-collapseTwo"><h4>How Do I Register to Bid on A Property (Buyers and Buyer’s Agents)?</h4></button>
        </div>
        <div id="panelsStayOpen-collapseTwo" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingTwo">
          <div class="accordion-body">Buyers/ Buyer’s agents will be required to create an account under the tab “Register” to bid on a property. Buyers/Buyer’s agents must accept the Terms and Conditions. Buyers/Buyer’s agent must send proof of funds or pre-approval letter and a valid ID to the Seller or Seller’s agent before bidding on a property or their bid can be considered null and void.</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-headingThree">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseThree" aria-expanded="false" aria-controls="panelsStayOpen-collapseThree"><h4>Can I Use the Services of a Real Estate Broker to Assist Me as A Seller?</h4></button>
        </div>
        <div id="panelsStayOpen-collapseThree" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingThree">
          <div class="accordion-body">Yes, we suggest using a real estate agent to ensure a successful auction on our real estate bidding platform. Your house will be professionally marketed and priced by a seasoned agent. This is critical to the auction’s success. Please contact us to get to get your property professionally listed and marketed.</div>
        </div>
      </div>

      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading4th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse4th" aria-expanded="false" aria-controls="panelsStayOpen-collapse4th"><h4>What Do I Need to Provide So I Can Bid?</h4></button>
        </div>
        <div id="panelsStayOpen-collapse4th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading4th">
          <div class="accordion-body">Before bidding on a property the Buyer’s agents must send their Buyers proof of funds or pre approval letter and a valid ID to the Seller/Seller’s agent or their offer can be considered null and void.</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading5th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse5th" aria-expanded="false" aria-controls="panelsStayOpen-collapse5th"><h4>What Is the Buyer's Premium?</h4></button>
        </div>
        <div id="panelsStayOpen-collapse5th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading5th">
          <div class="accordion-body">The Buyer’s Premium is a charge to the Buyer in addition to the final sales price. The premium is added to the winning offer to arrive at the final contract price paid by the Buyer for the property. This additional fee goes to the Seller at closing. The amount of the Buyer’s Premium varies from auction to auction. As a result, you’ll need to look at the Terms and Conditions of each auction to figure out how much you’ll be charged.</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading6th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse6th" aria-expanded="false" aria-controls="panelsStayOpen-collapse6th"><h4>Do I Need to Have a Sale Contract Typed Up Before the Auction?</h4></button>
        </div>
        <div id="panelsStayOpen-collapse6th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading6th">
          <div class="accordion-body">No, the Seller’s Agent will provide the winning real estate bid with an “AS IS” contract, all the disclosures, and addendums. If Seller is not represented by an agent then the Buyer’s agent will provide Buyer the documents. If there are no Real Estate Agents involved, Seller can email admin@bidyouroffer.com to request an “AS IS’ contract, all the disclosures, and addendums. Once the Buyer receives the contract they will have 48 hours to sign and submit the contract to the Seller/Seller’s agent or their offer can be considered null and void.</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading7th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse7th" aria-expanded="false" aria-controls="panelsStayOpen-collapse7th"><h4>When Will Buyer’s Premium Be Due?</h4></button>
        </div>
        <div id="panelsStayOpen-collapse7th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading7th">
          <div class="accordion-body">We will only collect the fee at the time of closing. If the real estate contract falls through, the Buyer must promptly sign the cancellation agreement and send to the Seller/Seller’s agent.</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading8th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse8th" aria-expanded="false" aria-controls="panelsStayOpen-collapse8th"><h4>How Much Is It to List My Property on Bid Your Offer? (Seller’s And Listing Agents)</h4></button>
        </div>
        <div id="panelsStayOpen-collapse8th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading8th">
          <div class="accordion-body">Adding your property to our real estate bidding site is completely free! At the time of closing, Bid Your Offer LLC a will collect a 1% success fee. The Seller determines who pays the success fee. If Seller is represented by an agent and the Seller or Buyer pay the 1% success fee we will split 50% of the success fee with the Seller’s agent. Additionally, the Seller’s agent can opt to pay the success fee with their commission for half a percentage (.50%.) There is no charge to Bid Your Offer if the property does not close!</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading9th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse9th" aria-expanded="false" aria-controls="panelsStayOpen-collapse9th"><h4>How Do I List a Property on Bid Your Offer?</h4></button>
        </div>
        <div id="panelsStayOpen-collapse9th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading9th">
          <div class="accordion-body">On the home page you can click “Add a Listing.” The Seller will be required to create an account and accept the Terms of Service. Seller must sign an addendum prior to listing that they will pay the 1% Success Fee to Bid Your Offer LLC at closing before listing. If Seller is represented by an agent, the agent will get 50% of the success fee. Seller can add this Success Fee to the auction and have the Buyer credit the Seller this fee at closing, if Seller chooses. If seller chooses to have the Buyer pay the 1% Success fee the fee will be added to the winning bid to come up with the final sales price.</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading10th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse10th" aria-expanded="false" aria-controls="panelsStayOpen-collapse10th"><h4>Who Can Use This Platform?</h4></button>
        </div>
        <div id="panelsStayOpen-collapse10th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading10th">
          <div class="accordion-body">Any Licensed Realtor, Seller, or Buyer in the Florida.</div>
        </div>
      </div>
    </div>
    <!-- End  -->
  </div>
@endsection
