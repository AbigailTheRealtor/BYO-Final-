@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="assets/css/buyerWork.css" />
@endpush
@section('content')
    <div class="container">
        <!-- First Section -->
        <div class="buyerWorkContent">
            <div class="row">
                <div class="col-sm-12 col-md-6 col-lg-6 text-center">
                    <img class="w-75" src="assets/pictures/buyerWork/buyerWork.jpg" alt="buyerWork" />
                </div>
                <div class="col-sm-12 col-md-6 col-lg-6">
                    <h1>How It Works for Buyers?</h1>
                    {{-- <p>Bid Your Offer is a one-of-a-kind approach to buying real estate. Buyers can participate in
                        transparent bidding on the property they want to buy. This new method of making offers allows buyers
                        to see what other buyers are offering. This platform provides transparency for everybody to avoid
                        missing out on a Buyer’s perfect home. The property will be published on our real estate bidding
                        platform according to the Seller’s conditions. Buyers can place a bid on the property by entering
                        the price, financing/cash terms, escrow deposit amount, days of inspection, closing date, and any
                        contingencies. Before bidding on a property the Buyer’s must send their proof of funds or pre
                        approval letter and a valid ID to the Seller/Seller’s agent or their offer can be considered null
                        and void.</p> --}}

                    {{-- change data --}}
                    <p>
                        <b>Welcome to Bid Your Agent!</b> Our platform is designed to connect buyers with top-performing
                        real
                        estate agents who specialize in their desired property type and location. We offer buyers the
                        opportunity to hire agents who provide a 0.5% rebate on the buyer's closing costs, making the buying
                        process more affordable and efficient.
                    </p>

                    <a href="listingDescription.html">
                        <button class="btn">Start Search</button>
                    </a>
                </div>
            </div>
        </div>
        <!-- end -->
        <!-- Second Section -->
        <div class="buyerWorkAuction">
            {{-- <h2 class="mb-3">What Are The Auction Terms?</h2> --}}
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller sets the terms of the auction.</div> --}}
                <div>
                    To use our platform, buyers need to provide information about the cities, counties, and states they are
                    interested in, the type of property they prefer, the desired sales provisions, budget, timeframe for
                    purchasing, and the number of bedrooms and bathrooms required. Buyers can also request specific services
                    from their agent. If a buyer has a preferred agent, they can enter their contact information when
                    creating the auction, and we will notify them so they can bid to be the buyer's hired agent.
                </div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div> Sellers can select to do a traditional route with no timer or auction style and impose a time limit.
                </div> --}}
                <div>
                    Agents who bid on a buyer's auction include their contact information, website link, review link, social
                    media links, "about me" section, marketing strategy, services provided, video buyer's presentation,
                    promotional marketing materials, offered buyer’s rebate (if requested), and business card. Buyers can
                    choose the best agent that meets their criteria and select an agent who will offer up to 0.5% rebate on
                    their closing costs, credited to the buyer at closing.
                </div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The time limit of the auction is selected by the Seller. The time varies with each auction, and the
                    Seller maintains the right to accept, reject, or counter any offer submitted on the real estate bidding
                    platform at any time.</div> --}}
                <div>
                    Our platform offers a wide range of properties, including regular sales, pre-construction properties,
                    properties that are currently being built, new construction, REO/bank-owned properties, assignment
                    contracts (wholesale properties), short sales, probate properties, and more. Buyers can hire
                    residential, income, and commercial agents who specialize in their desired property type.
                </div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>If the property has not met the reserve amount at the end of the auction time, then the seller can
                    extend the time limit.</div> --}}
                <div>
                    Buyers can choose between an auction (with a timer) or a traditional listing (without a timer). With
                    Auction listings, all offers and terms will be shown. Buyers can accept, deny, reject, or counter any
                    offers they receive, but will need to wait until the auction timer has ended to accept an offer, unless
                    an agent bids the Hire Now terms. The Hire Now terms can be defined as the requested buyer’s rebate,
                    terms, and services requested by the buyer. With Traditional listings, buyers can choose to hide or show
                    other offers. They can choose to accept, deny, reject, or counter any offers they receive at any time
                    with a traditional listing. Buyers can include Hire Now terms with both auction and traditional
                    listings.
                </div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in a “Buyer’s Premium”</div> --}}
                <div>
                    Our platform is entirely free for buyers, and the seller pays the real estate commission to their
                    listing agent, who then splits the commission with the buyer's agent. We will receive a referral fee
                    from the hired agent upon closing.
                </div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div>
                    Moreover, by hiring an agent on our platform, buyers can list their property criteria for free on our
                    sister platform, BidYourOffer.com. To learn more about the benefits that BidYourOffer can offer for your
                    property criteria listing, please visit BidYourOffer.com
                </div>
            </div>
            {{-- <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                <div>The Seller determines who pays the 1% “Success fee”</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                <div>The Seller’s Agent will provide the winning real estate bid with an “AS IS” contract, all the
                    disclosures, and addendums. If Seller is not represented by an agent then the Buyer’s agent will provide
                    Buyer the documents. If there are no Real Estate Agents involved, Seller can email
                    admin@bidyouroffer.com to request an “AS IS’ contract, all the disclosures, and addendums.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                <div>Once the Contract is received by the Buyer. The Buyer must submit the contract, disclosures, and
                    addendums to the Seller or Seller’s agent within 48 hours. Otherwise, the winning offer can be
                    considered null and void.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                <div>Any additional queries should be sent to the property’s Seller’s Agent/Seller, as each auction’s terms
                    and conditions are determined by the Seller of the property.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                <div>Seller can add in a premium that the seller will pay a percentage to the Buyer to help a Buyer cover
                    costs with purchasing a home. This percentage will be added to the contract and credited to the Buyer at
                    Closing. This fee is optional and will be determined by the Seller.</div>
            </div> --}}
            <div>
                Here are some ways in which our platform assists Buyers. Click on the arrow button next to each term to view more information.
            </div>
            <a href="{{ route('buyerDetails') }}"><i class="fa fa-arrow-right"></i></a>

        </div>
        <!-- End -->
        <!-- Third Section  -->
        <div class="buyerWorkBid">
            <h2 class="mb-3">Why Use Bid Your Offer?</h2>
            <p>Bid Your Offer is a means for all parties to create transparency in every Real Estate transaction. The number
                one quality Sellers and Buyers look for in a Real Estate Transaction is Trust. Sellers, Buyers, and Realtors
                can rely on Bid Your Offer platform to provide all the important offering terms of a transaction by putting
                every party on the same playing field.</p>
            <p>Seller’s can have confidence by utilizing this platform it creates Trust and Transparency for all parties and
                ensures Seller’s will be presented with all offers fairly and timely. Buyers no longer have to be
                disappointed that their offer wasn’t selected because of the lack of transparency with the other competing
                offers. Sellers, Buyers, and Realtors can avoid going back and forth with negotiations of the contract terms
                and will only need to complete the contract once the Seller selects the winning bid.</p>
            <p>Typically, numerous Buyers propose similar price/terms because they are unaware of the other offering
                prices/terms. Bid Your Offer is a means for all parties to be more transparent because Buyers can now be
                aware of what other Buyers are offering instead of guessing. In many cases had a buyer known they missed out
                on a property by only a few thousand dollars, they would have paid it.</p>
        </div>
        <!-- End -->
        <!-- Fourth Section  -->
        <div class="sellingOption container text-center">
            <img class="w-100" src="assets/pictures/sellerWork/sellingOption.png" alt="selling option" />
        </div>
        <!-- End  -->
        <!-- Fifth Section  -->
        <div class="accordion" id="accordionPanelsStayOpenExample">
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapseOne" aria-expanded="true"
                        aria-controls="panelsStayOpen-collapseOne">
                        <h4>Can I Use the Services of a Real Estate Agent to Assist Me as A Buyer</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapseOne" class="accordion-collapse collapse show"
                    aria-labelledby="panelsStayOpen-headingOne">
                    <div class="accordion-body">Yes, Buyer’s can use the agent they choose. If Buyer is not being
                        represented by a Buyer’s agent and the Buyer would like to be represented we can refer a Buyer’s
                        agent to represent them at no cost to the Buyer.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapseTwo" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapseTwo">
                        <h4>How Do I Register to Bid on a Property?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapseTwo" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-headingTwo">
                    <div class="accordion-body">Buyer’s will be required to create an account under the tab “Register” to
                        bid on a property. Buyers must accept the Terms and Conditions. Buyer’s must send proof of funds or
                        pre-approval letter and a valid ID to the Seller or Seller’s agent before bidding on a property or
                        their bid can be considered null and void.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapseThree" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapseThree">
                        <h4>What Do I Need to Provide So I Can Bid?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapseThree" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-headingThree">
                    <div class="accordion-body">Buyer’s must send proof of funds or pre-approval letter and a valid ID to
                        the Seller or Seller’s agent before bidding on a property or their bid can be considered null and
                        void. Don’t hesitate to get in touch with the Selling agent or the property Seller if you have any
                        additional questions or require assistance.</div>
                </div>
            </div>

            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-heading4th">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapse4th" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapse4th">
                        <h4>Do I Need to Have a Sales Contract Typed Up Before the Auction?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapse4th" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-heading4th">
                    <div class="accordion-body">No, the Seller’s Agent will provide the winning real estate bid with an “AS
                        IS” contract, all the disclosures, and addendums. If Seller is not represented by an agent then the
                        Buyer’s agent will provide Buyer the documents. If there are no Real Estate Agents involved, Seller
                        can email admin@bidyouroffer.com to request an “AS IS’ contract, all the disclosures, and addendums.
                        Once the Buyer receives the contract they will have 48 hours to sign and submit the contract to the
                        Seller/Seller’s agent or their offer can be considered null and void.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-heading5th">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapse5th" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapse5th">
                        <h4>What is a Success Fee?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapse5th" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-heading5th">
                    <div class="accordion-body">The success fee is 1% of the purchase price paid to Bid Your Offer LLC at
                        closing for the success of the auction. The success fee will be added to the final sales price if
                        paid by the Buyer. The Seller determines who pays the success fee. The Buyer must check the listing
                        notes on the property to determine if the Buyer is responsible for paying the success fee. The
                        success fee is only charged when the sale is closed.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-heading6th">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapse6th" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapse6th">
                        <h4>What is a Buyer's Premium?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapse6th" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-heading6th">
                    <div class="accordion-body">The Buyer’s Premium is a charge to the Buyer in addition to the final sales
                        price. The premium is added to the winning offer to arrive at the final contract price paid by the
                        Buyer for the property. This additional fee goes to the Seller at closing. The amount of the Buyer’s
                        Premium varies from auction to auction. As a result, you’ll need to look at the Terms and Conditions
                        of each auction to figure out how much you’ll be charged.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-heading7th">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapse7th" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapse7th">
                        <h4>What is a Seller's Premium</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapse7th" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-heading7th">
                    <div class="accordion-body">The Seller has the option to include a “Seller’s Premium” to their auction.
                        The Seller’s Premium is a percentage amount off the purchase price that the Seller will pay the
                        Buyer at closing. This premium will cut down the closing costs for a Buyer.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-heading8th">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapse8th" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapse8th">
                        <h4>What is a Seller's Premium</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapse8th" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-heading8th">
                    <div class="accordion-body">The Seller has the option to include a “Seller’s Premium” to their auction.
                        The Seller’s Premium is a percentage amount off the purchase price that the Seller will pay the
                        Buyer at closing. This premium will cut down the closing costs for a Buyer.</div>
                </div>
            </div>
        </div>
        <!-- End  -->
    </div>
@endsection
@push('scripts')
@endpush
