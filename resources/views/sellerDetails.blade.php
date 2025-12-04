@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sellerWork.css') }}" />
@endpush
@section('content')
    <div class="container" style="background:#f7f7f8">


        <div class="sellerWorkAuction">
            {{-- <h2 class="mb-3">What Are The Auction Terms?</h2> --}}
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller sets the terms of the auction.</div> --}}
                <div><b>Information Provided by Sellers:</b> To hire an agent, sellers need to provide the property address,
                    type,
                    number of bedrooms and bathrooms, square footage, acceptable financing/currency, property condition,
                    expected selling price, type of special sale, timeframe for selling, listing agreement timeframe,
                    offered commission, requested buyer rebate, services they expect from their agent, property description,
                    important property information, description of their ideal agent, and optionally requested property
                    value analysis with photos and/or videos of their property.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>Sellers can select to do a traditional route with no timer or auction style and impose a time limit.
                </div> --}}
                <div><b>Information Included in an Agent's Bid:</b> Agents who bid on a seller's auction need to provide
                    their
                    contact information, offered commission, offered buyer's rebate, website link, review link, social media
                    links, "About Me" section, listing agreement timeframe, why they should be hired, what sets them apart
                    from other agents, marketing strategy, services provided to the seller, video listing presentation,
                    promotional marketing materials, business card, and property value analysis (if requested).</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The time limit of the auction is selected by the Seller. The time varies with each auction, and the
                    Seller maintains the right to accept, reject, or counter any offer submitted on the real estate bidding
                    platform at any time.</div> --}}
                <div><b>Buyer Rebate:</b> By utilizing this platform, sellers can hire agents who offer a 0.5% rebate of the
                    buyer's closing costs. This rebate will be given to the buyer at closing to help them pay their closing
                    costs or buy down their interest rate. This rebate can help sellers effectively market their property to
                    potential buyers and make it stand out from other properties that do not offer such rebates.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>If the property has not met the reserve amount at the end of the auction time, then the seller can
                    extend the time limit.</div> --}}
                <div><b>Auction Link:</b> Sellers can enter their preferred agent's contact information when creating the
                    auction,
                    and we will notify that agent so they can bid to be the seller's agent. Additionally, the seller can
                    share the auction link or QR code through various outlets, providing more opportunities for agents to
                    compete to be their hired agent.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in a “Buyer’s Premium”</div> --}}
                <div><b>No Existing Agent:</b> This service is exclusively for sellers who are not currently working with an
                    agent.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>Timeline:</b> We recommend being in the market to sell your property within 3 months or less to use
                    this platform.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>Types of Agents:</b> You can hire residential, income, and commercial agents for this platform.
                    Simply select your property type, and we will match you with an agent who specializes in your property
                    type.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>Selecting an Agent:</b> Once an agent places a bid on your listing, you will receive an email
                    notification. You can then log in to your account to review the bid and choose to accept, deny, reject,
                    or counter any offers you receive at any time with a traditional listing. If you choose an auction
                    listing, you will need to wait until the end of the auction to select your agent.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>Special Sale Properties:</b> Agents on our platform accept a wide range of properties, including
                    regular sales, pre-construction properties, properties that are currently being built, new construction,
                    REO/bank-owned properties, assignment contracts (wholesale properties), short sales, probate properties,
                    and more!</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>Types of Properties:</b> Agents on our platform accept residential, income, and commercial
                    properties, including single-family residences, townhouses, villas, condominiums, condo-hotels,
                    half-duplexes, dock-rackominiums, farms, garage condos, manufactured homes, mobile homes, modular homes,
                    duplexes, triplexes, quadplexes, properties with five or more residential units, as well as agriculture,
                    assembly building, business, properties with five or more commercial units, hotel/motel, industrial,
                    mixed-use, office, restaurant, retail, unimproved land, and warehouse.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>Type of Listing:</b> The seller can choose between an Auction (with a timer) or a Traditional
                    Listing (without a timer). If the seller opts for an Auction, the listing will end at the predetermined
                    time chosen by the seller. After the auction ends, the seller can select the best agent that meets their
                    criteria. The only way an auction can end early is if an agent bids on the Hire Now Terms. On the other
                    hand, if the seller selects a Traditional Listing (without a timer), they can choose their agent at any
                    time. The seller has the right to accept, counter, or reject any bids.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>Hire Now Terms:</b> The Hire Now Terms refer to specific commission rates, buyer's rebates, terms,
                    and
                    services that sellers offer to agents. These terms may include an acceptable commission rate, up to a
                    0.5% buyer's rebate, specific contractual conditions, and services requested by the seller. If an agent
                    offers the "Hire Now Terms," the seller can choose to end an auction early and hire that agent
                    immediately.</div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>Free Property Value Analysis:</b> Sellers can request a free property value analysis from agents. To
                    ensure
                    a reliable analysis, sellers should include pictures and videos of their property. Upon receiving the
                    necessary materials, agents can provide a complimentary property value analysis report to the seller.
                </div>
            </div>
            <div class="d-flex">
                <div><i class="fa fa-circle"></i></div>
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>Price:</b> This service is free for sellers. We will receive a referral fee from the hired agent
                    upon closing.</div>
            </div>

        </div>

    </div>
@endsection
