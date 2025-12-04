@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sellerWork.css') }}" />
@endpush
@section('content')
    <div class="container">
        <!-- First Section -->
        <div class="sellerWorkContent">
            <div class="row">
                <div class="col-sm-12 col-md-6 col-lg-6 text-center">
                    <img class="w-75" src="assets/pictures/sellerWork/sellerWork.jpg" alt="sellerWork" />
                </div>
                <div class="col-sm-12 col-md-6 col-lg-6">
                    <h1>How It Works for Sellers?</h1>
                    <p>
                        {{-- Sellers can post their property on this real estate platform to provide Sellers, Buyers, and
                        Realtors
                        with a transparent bidding platform. Sellers will be able to list their property and include all
                        their acceptable terms including purchase price, financing/cash terms, escrow amount, inspection
                        period, closing date, and any contingencies they will accept. Buyers will have a fair opportunity to
                        bid their highest and best on this platform by submitting their offering price, financing/cash
                        terms, escrow deposit amount, days of inspection, closing date, and any contingencies. Buyer’s must
                        send their proof of funds or pre approval letter and a valid ID to the Seller before bidding or
                        their offer can be considered null and void. --}}

                        {{-- add new --}}
                        <b> Welcome to Bid Your Agent!</b> Our platform is designed to connect sellers with top-performing
                        real
                        estate agents who specialize in residential, income, and commercial properties. We simplify the
                        process of hiring a real estate agent and streamline the selling process for sellers by providing a
                        unique approach. Agents compete to bid for your listing based on the seller's individual needs.

                    </p>
                </div>
            </div>
        </div>
        <!-- end -->
        <!-- Second Section -->
        <div class="sellerWorkAuction">
            {{-- <h2 class="mb-3">What Are The Auction Terms?</h2> --}}
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller sets the terms of the auction.</div> --}}
                <div>As a seller, you only need to provide some basic information about your property, including the
                    address, type, number of bedrooms and bathrooms, square footage, acceptable financing/currency, property
                    condition, expected selling price, type of special sale, timeframe for selling, listing agreement
                    timeframe, offered commission, requested buyer rebate, services you expect from your agent, property
                    description, important property information, description of your ideal agent, and optionally requested
                    property value analysis with photos and/or videos of your property.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>Sellers can select to do a traditional route with no timer or auction style and impose a time limit.
                </div> --}}
                <div>Once you provide us with this information, agents will bid on your listing. To place their bids, agents
                    will provide their contact information, offered commission, offered buyer's rebate, website link,
                    reviews link, social media links, "about me" section, listing agreement timeframe, why they should be
                    hired, what sets them apart from other agents, marketing strategy, services provided to the seller,
                    video listing presentation, promotional marketing materials, business card, and property value analysis
                    (if requested). If you have a preferred agent, you can enter their contact information when creating the
                    auction, and we will notify them so they can bid to be your hired agent.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The time limit of the auction is selected by the Seller. The time varies with each auction, and the
                    Seller maintains the right to accept, reject, or counter any offer submitted on the real estate bidding
                    platform at any time.</div> --}}
                <div>Our platform also offers sellers the option to hire agents who offer a 0.5% rebate to contribute to the
                    buyer’s closing costs. This rebate can help sellers effectively market their property to potential
                    buyers and make it stand out from other properties that do not offer such rebates.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>If the property has not met the reserve amount at the end of the auction time, then the seller can
                    extend the time limit.</div> --}}
                <div>We accept a wide range of properties, including regular sales, pre-construction properties, properties
                    that are currently being built, new construction, REO/bank-owned properties, assignment contracts
                    (wholesale properties), short sales, probate properties, and more.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in a “Buyer’s Premium”</div> --}}
                <div>Sellers can choose between an auction (with a timer) or a traditional listing (without a timer). With
                    Auction listings, all offers and terms will be shown. Sellers can accept, deny, reject, or counter any
                    offers they receive, but will need to wait until the auction timer has ended to accept an offer, unless
                    an agent bids the Hire Now terms. The Hire Now terms can be defined as the offered commission, requested
                    buyer’s rebate, terms, and services requested by the seller. With Traditional listings, buyers can
                    choose to hide or show other offers. They can choose to accept, deny, reject, or counter any offers they
                    receive at any time with a traditional listing. Sellers can include Hire Now terms with both auction and
                    traditional listings.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div>Our platform is a free service for sellers. We will receive a referral fee from the hired agent upon
                    closing. Moreover, by hiring an agent on our platform, sellers can list their property for free on our
                    sister platform, BidYourOffer.com. To learn more about the benefits that BidYourOffer can offer for your
                    property listing, please visit the buyer's section on BidYourOffer.com using the following link: ( <a href="{{ route('sellerDetails') }}"><i class="fa fa-arrow-right"></i></a> )</div>
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
            </div> --}}
        </div>
        <!-- End -->
        <!-- Third Section  -->
        <div class="sellerWorkBid">
            <h2 class="mb-3">Why Use Bid Your Offer?</h2>
            <p>Bid Your Offer is a means for all parties to create transparency in every Real Estate transaction. The number
                one quality Sellers and Buyers look for in a Real Estate Transaction is Trust. Sellers and Buyers can rely
                on Bid Your Offer platform to provide all the important offering terms of a transaction by putting every
                party on the same playing field.</p>
            <p>Seller’s can have confidence by utilizing this platform it creates Trust and Transparency for all parties and
                ensures Seller’s will be presented with all offers fairly and timely. Buyers no longer have to be
                disappointed that their offer wasn’t selected because of the lack of transparency with the other competing
                offers. Agents can avoid going back and forth with negotiations of the contract terms and will only need to
                complete the contract once the Seller selects the winning bid.</p>
            <p>Typically, numerous Buyers propose similar price/terms without putting up their best offer because they are
                unaware of the other offering prices/terms. Bid Your Offer is a means for all parties to be more transparent
                because Buyers can now be aware of what other Buyers are offering instead of guessing. The results can be
                numerous bids competing and Buyers putting their best offer forward. In many cases had a buyer known they
                missed out on a property by only a few thousand dollars, they would have paid it.</p>
        </div>
        <!-- End -->
        <!-- Fourth Section  -->
        <div class="sellingOption container text-center">
            <img class="w-100" src="assets/pictures/sellerWork/sellingOption.png" alt="sellingOption" />
        </div>
        <!-- End  -->
        <!-- Fifth Section  -->
        <div class="sellerWorkListingServiceOption container text-center">
            <img class="w-100" height="2757" src="assets/pictures/sellerWork/serviceOption.png" alt="serviceOption"
                loading="lazy" />
        </div>
        <!-- End -->
        <!-- Six Section -->
        <div class="sellerWorkPriceComparison container text-center">
            <img class="w-100" src="assets/pictures/sellerWork/priceCamparsion.jpg" alt="priceCamparsion" loading="lazy" />
        </div>
        <!-- End  -->
        <!-- seven Section  -->
        <div class="accordion" id="accordionPanelsStayOpenExample">
            <p>Please Note: The 1% Success fee can be paid by the Buyer if the Seller chooses to have the Buyer Credit the
                Seller at Closing. Seller can also add in a “Buyer’s Premium” to help cover the closing costs. This makes it
                easy for Sellers to get a Full Service Representation without paying for it.. This real estate platform will
                give you the best results when you are priced correctly.</p>
            <br />
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapseOne" aria-expanded="true"
                        aria-controls="panelsStayOpen-collapseOne">
                        <h4>What is a Success Fee?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapseOne" class="accordion-collapse collapse show"
                    aria-labelledby="panelsStayOpen-headingOne">
                    <div class="accordion-body">The success fee is 1% of the purchase price paid to Bid Your Offer LLC at
                        closing for the success of the auction. The success fee will be added to the final sales price if
                        paid by the Buyer. If paid by the Seller the fee will come out of the proceeds at closing. If the
                        property contract is canceled, the Seller or Seller’s agent must submit a cancellation contract to
                        admin@bidyouroffer.com. The Success fee is only charged when the sale is closed.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapseTwo" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapseTwo">
                        <h4>What is a Buyer's Premium?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapseTwo" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-headingTwo">
                    <div class="accordion-body">The Buyer’s Premium is a charge to the Buyer in addition to the final sales
                        price. The premium is added to the winning offer to arrive at the final contract price paid by the
                        Buyer for the property. This additional fee is credited to the Seller at closing. Additionally, by
                        including a “Buyer’s Premium,” Sellers will reduce their closing expenses. This enables Sellers to
                        work with a full-service realtor without paying a full commission. The amount of the Buyer’s Premium
                        varies from auction to auction and is determined by the Seller. If the property contract is
                        canceled, the Seller or Seller’s Agent must upload and submit a cancellation contract to
                        admin@bidyouroffer.com.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapseThree" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapseThree">
                        <h4>What is a Seller's Premium</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapseThree" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-headingThree">
                    <div class="accordion-body">The Seller has the option to include a “Seller’s Premium” to their auction.
                        The Seller’s Premium is a percentage amount off the purchase price that the Seller will pay the
                        Buyer at closing. This premium will cut down the closing costs for a Buyer. The benefit of adding in
                        a Seller’s Premium is that it attracts more Buyers to a Seller’s property. By adding in this premium
                        it will help a Seller’s Property stand out and could ultimately lead to more offers. This Seller’s
                        Premium is especially beneficial in a “Buyer Market” or for a home that needs work.</div>
                </div>
            </div>

            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-heading4th">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapse4th" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapse4th">
                        <h4>When Will The Premiums and Success Fee Be Due?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapse4th" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-heading4th">
                    <div class="accordion-body">We will only collect the fee at the time of closing. If the real estate
                        contract falls through, the Seller or Seller’s agent must promptly submit a release and cancellation
                        agreement to admin@bidyouroffer.com.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-heading5th">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapse5th" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapse5th">
                        <h4>Can I Use the Services of a Real Estate Agent to Assist Me as a Seller?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapse5th" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-heading5th">
                    <div class="accordion-body">Yes, we suggest using a real estate agent to ensure a successful auction on
                        our real estate bidding platform. Your house will be professionally marketed and priced by a
                        seasoned agent. This is critical to the auction’s success. Please contact us to get to get your
                        property professionally listed and marketed.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-heading6th">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapse6th" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapse6th">
                        <h4>How Do I List a Property?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapse6th" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-heading6th">
                    <div class="accordion-body">On the home page you can click “Add a Listing.” The Seller will be required
                        to create an account and accept the Terms of Service. Seller must sign an addendum prior to listing
                        that they will pay the 1% Success Fee to Bid Your Offer LLC at closing before listing. Seller can
                        add this Success Fee to the auction and have the Buyer credit the Seller this fee at closing, if
                        Seller chooses. If seller chooses to have the Buyer pay the 1% Success fee the fee will be added to
                        the winning bid to come up with the final sales price.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-heading7th">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapse7th" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapse7th">
                        <h4>How Much Is It to List My Property on Bid Your Offer?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapse7th" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-heading7th">
                    <div class="accordion-body">Adding your property to our real estate bidding site is completely free! At
                        the time of closing, Bid Your Off LLC will receive a 1% success fee which the Buyer can credit to
                        the Seller to pay the fee. If listed by a Seller’s agent then Seller’s agent will get half of the
                        success fee premium at closing for a successful auction.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header" id="panelsStayOpen-heading8th">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#panelsStayOpen-collapse8th" aria-expanded="false"
                        aria-controls="panelsStayOpen-collapse8th">
                        <h4>Do I Need to Have a Sales Contract Typed Up Before the Auction?</h4>
                    </button>
                </div>
                <div id="panelsStayOpen-collapse8th" class="accordion-collapse collapse"
                    aria-labelledby="panelsStayOpen-heading8th">
                    <div class="accordion-body">No, the Seller’s Agent will provide the winning real estate bid with an “AS
                        IS” contract, all the disclosures, and addendums. If Seller is not represented by an agent then the
                        Buyer’s agent will provide Buyer the documents. If there are no Real Estate Agents involved, Seller
                        can email admin@bidyouroffer.com to request an “AS IS’ contract, all the disclosures, and addendums.
                        Once the Buyer receives the contract they will have 48 hours to sign and submit the contract to the
                        Seller/Seller’s agent or their offer can be considered null and void.</div>
                </div>
            </div>
        </div>
        <!-- End  -->
    </div>
@endsection
@push('scripts')
@endpush
