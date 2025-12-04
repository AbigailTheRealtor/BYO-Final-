@extends('layouts.main')
@push('name')
<link rel="stylesheet" href="assets/css/buyerWorkAgent.css" />
@endpush
@section('content')
<div class="container">
    <!-- First Section -->
    <div class="buyerWorkAgentContent">
      <div class="row">
        <div class="col-sm-12 col-md-6 col-lg-6 text-center">
          <img class="w-75" src="assets/pictures/buyerWorkAgent/buyerWorkAgent.jpg" alt="" />
        </div>
        <div class="col-sm-12 col-md-6 col-lg-6">
          <h1>How It Works for Buyer Agents?</h1>
          <p>Buyer’s agents will be allowed to conduct transparent real estate bidding on the property that their client wants to buy. This new method of making offers allows Buyers to see what other Buyers are proposing and to avoid missing out on a deal by providing transparency for everybody. The property will be published on our real estate bidding platform according to the Selling terms and conditions. Buyer’s agents can place a bid on the property by entering their offering price, financing terms/cash, escrow deposit amount, days of inspection, closing date, and any contingencies. Before bidding on a property the Buyer’s agents must send their Buyers proof of funds or pre approval letter and a valid ID to the Seller/Seller’s agent or their offer can be considered null and void.</p>
          <a href="listingDescription.html"><button class="btn">Start Search</button></a>
        </div>
      </div>
    </div>
    <!-- end -->
    <!-- Second Section -->
    <div class="buyerWorkAgentAuction">
      <h2 class="mb-3">What Are The Auction Terms?</h2>
      <div class="d-flex">
        <div><i class="fa fa-circle"></i></div>
        <div>The Seller sets the terms of the auction.</div>
      </div>
      <div class="d-flex">
        <div><i class="fa fa-circle"></i></div>
        <div>Sellers can select to do a traditional route with no timer or auction style and impose a time limit.</div>
      </div>
      <div class="d-flex">
        <div><i class="fa fa-circle"></i></div>
        <div>The time limit of the auction is selected by the Seller. The time varies with each auction, and the Seller maintains the right to accept, reject, or counter any offer submitted on the real estate bidding platform at any time.</div>
      </div>
      <div class="d-flex">
        <div><i class="fa fa-circle"></i></div>
        <div>If the property has not met the reserve amount at the end of the auction time, then the seller can extend the time limit.</div>
      </div>
      <div class="d-flex">
        <div><i class="fa fa-circle"></i></div>
        <div>The Seller has the option to add in a “Buyer’s Premium”</div>
      </div>
      <div class="d-flex">
        <div><i class="fa fa-circle"></i></div>
        <div>The Seller has the option to add in the “Seller’s Premium”</div>
      </div>
      <div class="d-flex">
        <div><i class="fa fa-circle"></i></div>
        <div>The Seller determines who pays the 1% “Success fee”</div>
      </div>
      <div class="d-flex">
        <div><i class="fa fa-circle"></i></div>
        <div>The Seller’s Agent will provide the winning real estate bid with an “AS IS” contract, all the disclosures, and addendums. If Seller is not represented by an agent then the Buyer’s agent will provide Buyer the documents. If there are no Real Estate Agents involved, Seller can email <a href="">admin@bidyouroffer.com</a> to request an “AS IS’ contract, all the disclosures, and addendums.</div>
      </div>
      <div class="d-flex">
        <div><i class="fa fa-circle"></i></div>
        <div>Once the Contract is received by the Buyer. The Buyer must submit the contract, disclosures, and addendums to the Seller or Seller’s agent within 24 hours. Otherwise, the winning offer can be considered null and void.</div>
      </div>
      <div class="d-flex">
        <div><i class="fa fa-circle"></i></div>
        <div>Any additional queries should be sent to the property’s Seller’s Agent/Seller, as each auction’s terms and conditions are determined by the Seller of the property.</div>
      </div>
    </div>
    <!-- End -->
    <!-- Third Section  -->
    <div class="buyerWorkAgentBid">
      <h2 class="mb-3">Why Use Bid Your Offer?</h2>
      <p>Bid Your Offer is a means for all parties to create transparency in every Real Estate transaction. The number one quality Sellers and Buyers look for in a Real Estate Transaction is Trust. Sellers, Buyers, and Realtors can rely on Bid Your Offer platform to provide all the important offering terms of a transaction by putting every party on the same playing field.</p>
      <p>Seller’s can have confidence by utilizing this platform it creates Trust and Transparency for all parties and ensures Seller’s will be presented with all offers fairly and timely. Buyers no longer have to be disappointed that their offer wasn’t selected because of the lack of transparency with the other competing offers. Sellers, Buyers, and Realtors can avoid going back and forth with negotiations of the contract terms and will only need to complete the contract once the Seller selects the winning bid.
      </p>
      <p>Typically, numerous Buyers propose similar price/terms because they are unaware of the other offering prices/terms. Bid Your Offer is a means for all parties to be more transparent because Buyers can now be aware of what other Buyers are offering instead of guessing. In many cases had a buyer known they missed out on a property by only a few thousand dollars, they would have paid it.</p>
    </div>
    <!-- End -->
    <!-- Fourth Section  -->
    <div class="sellingOption container text-center">
      <img class="w-75" src="assets/pictures/sellerWork/sellingOption.png" alt="sellingOption" />
    </div>
    <!-- End  -->
    <!-- fifth Section  -->
    <div class="accordion" id="accordionPanelsStayOpenExample">
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-headingOne">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseOne" aria-expanded="true" aria-controls="panelsStayOpen-collapseOne"><h4>Will a Buyer's Agent Get Paid a Commission?</h4></button>
        </div>
        <div id="panelsStayOpen-collapseOne" class="accordion-collapse collapse show" aria-labelledby="panelsStayOpen-headingOne">
          <div class="accordion-body">Yes, Buyer’s agents commission is noted on the listing. </div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-headingTwo">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseTwo" aria-expanded="false" aria-controls="panelsStayOpen-collapseTwo"><h4>How Do I Register to Bid on a Property?</h4></button>
        </div>
        <div id="panelsStayOpen-collapseTwo" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingTwo">
          <div class="accordion-body">Buyer’s agents will be required to create an account under the tab “Register” to bid on a property. Buyers agents must accept the Terms and Conditions. Buyer’s agent must send proof of funds or pre-approval letter and a valid ID to the Seller or Seller’s agent before bidding on a property or their bid can be considered null and void.</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-headingThree">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseThree" aria-expanded="false" aria-controls="panelsStayOpen-collapseThree"><h4>What Do I Need to Provide So I Can Bid?</h4></button>
        </div>
        <div id="panelsStayOpen-collapseThree" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingThree">
          <div class="accordion-body">Buyer’s agent must send proof of funds or pre-approval letter and a valid ID to the Seller or Seller’s agent before bidding on a property or their bid can be considered null and void. Don’t hesitate to get in touch with the Selling agent or the property Seller if you have any additional questions or require assistance.</div>
        </div>
      </div>

      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading4th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse4th" aria-expanded="false" aria-controls="panelsStayOpen-collapse4th"><h4>Do I Need to Have a Sales Contract Typed Up Before the Auction?</h4></button>
        </div>
        <div id="panelsStayOpen-collapse4th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading4th">
          <div class="accordion-body">No, the Seller’s Agent will provide the winning real estate bid with an “AS IS” contract, all the disclosures, and addendums. If Seller is not represented by an agent then the Buyer’s agent will provide Buyer the documents. If there are no Real Estate Agents involved, Seller can email admin@bidyouroffer.com to request an “AS IS’ contract, all the disclosures, and addendums. Once the Buyer receives the contract they will have 48 hours to sign and submit the contract to the Seller/Seller’s agent or their offer can be considered null and void. </div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading5th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse5th" aria-expanded="false" aria-controls="panelsStayOpen-collapse5th"><h4>What is a Success Fee?</h4></button>
        </div>
        <div id="panelsStayOpen-collapse5th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading5th">
          <div class="accordion-body">The success fee is 1% of the purchase price paid to Bid Your Offer LLC at closing for the success of the auction. The success fee will be added to the final sales price if paid by the Buyer. The Seller determines who pays the success fee.  The Buyer’s agent must check the listing notes on the property to determine if the Buyer is responsible for paying the success fee.  The success fee is only charged when the sale is closed. </div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading6th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse6th" aria-expanded="false" aria-controls="panelsStayOpen-collapse6th"><h4>What is a Buyer's Premium?</h4></button>
        </div>
        <div id="panelsStayOpen-collapse6th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading6th">
          <div class="accordion-body">The Buyer’s Premium is a charge to the Buyer in addition to the final sales price. The premium is added to the winning offer to arrive at the final contract price paid by the Buyer for the property. This additional fee goes to the Seller at closing. The amount of the Buyer’s Premium varies from auction to auction.  As a result, you’ll need to look at the Terms and Conditions of each auction to figure out how much you’ll be charged.</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading7th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse7th" aria-expanded="false" aria-controls="panelsStayOpen-collapse7th"><h4>What is a Seller's Premium</h4></button>
        </div>
        <div id="panelsStayOpen-collapse7th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading7th">
          <div class="accordion-body">The Seller has the option to include a “Seller’s Premium” to their auction. The Seller’s Premium is a percentage amount off the purchase price that the Seller will credit the Buyer at closing. This premium will cut down the closing costs for a Buyer.</div>
        </div>
      </div>
      <div class="accordion-item">
        <div class="accordion-header" id="panelsStayOpen-heading8th">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapse8th" aria-expanded="false" aria-controls="panelsStayOpen-collapse8th"><h4>When Will Buyer’s Premium and Success Fee Be Due?</h4></button>
        </div>
        <div id="panelsStayOpen-collapse8th" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-heading8th">
          <div class="accordion-body">We will only collect the fee at the time of closing. If the real estate contract falls through, the Buyer must promptly submit a release and cancellation agreement to the Seller or Seller’s agent.</div>
        </div>
      </div>
    </div>
    <!-- End  -->
  </div>
@endsection
