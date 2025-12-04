@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sellerWork.css') }}" />
@endpush
@section('content')
    <div class="container">


        <div class="sellerWorkAuction">
            {{-- <h2 class="mb-3">What Are The Auction Terms?</h2> --}}
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller sets the terms of the auction.</div> --}}
                <div><b>1.</b>&emsp;<b>Information Provided by Buyers:</b> To hire an agent, buyers provide information on the
                    cities,
                    counties, and states of interest, property types they prefer, desired sales provisions, preferred
                    property condition, budget, timeframe for purchasing, number of bedrooms (for residential properties)
                    and bathrooms required, desired Buyer's Rebate (up to half a percent of the agent's commission), buyer’s
                    agreement timeframe, offered financing/currency, whether they are pre-approved for a loan, a cash buyer,
                    or need lender recommendations, and services they request from their agent. Additionally, buyers may
                    request that their preferred agents be notified.</div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>Sellers can select to do a traditional route with no timer or auction style and impose a time limit.
                </div> --}}
                <div><b>2.</b>&emsp;<b>Information Included in an Agent's Bid:</b> Agents who bid on a buyer’s auction include their
                    contact
                    information, offered Buyer's Rebate (if applicable), website link, review link, social media links,
                    "About Me" section, buyer's agreement timeframe, why they should be hired, what sets them apart from
                    other agents, marketing strategy, services provided to the buyer, video buyer’s presentation,
                    promotional marketing materials, and business card.</div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The time limit of the auction is selected by the Seller. The time varies with each auction, and the
                    Seller maintains the right to accept, reject, or counter any offer submitted on the real estate bidding
                    platform at any time.</div> --}}
                <div><b>3.</b>&emsp;<b>Buyer Rebate:</b> By utilizing this platform, buyers can hire agents who offer a 0.5% rebate of a
                    buyer's
                    closing costs. This rebate will be credited to the buyer at closing, and they can use it to help with
                    their closing costs or to buy down their interest rate.</div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>If the property has not met the reserve amount at the end of the auction time, then the seller can
                    extend the time limit.</div> --}}
                <div>
                    <b>4.</b>&emsp;<b>Auction Link:</b> Buyers can enter their preferred agent's contact information when creating the
                    auction,
                    and we will notify that agent so they can bid to be the buyer's agent. Additionally, the buyer can share
                    the auction link or QR code through various outlets, providing more opportunities for agents to compete
                    to be their hired agent.
                </div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller has the option to add in a “Buyer’s Premium”</div> --}}
                <div><b>5.</b>&emsp;<b>No Existing Agent:</b> This service is exclusively for buyers who are not currently working with an
                    agent.</div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>6.</b>&emsp;<b>Timeline:</b> We recommend being in the market to buy a property within three months or less to use
                    this platform.</div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>7.</b>&emsp;<b>Types of Agents:</b> You can hire Residential, Income, and Commercial agents for this platform.
                    Simply
                    select your property type, and we will match you with an agent who specializes in your property type.
                </div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>8.</b>&emsp;<b>Selecting an Agent:</b> Once an agent places a bid on your listing, you will receive an email
                    notification.
                    You can then log in to your account to review the bid and choose to accept, deny, reject, or counter any
                    offers you receive at any time with a traditional listing. If you choose an auction listing, you will
                    need to wait until the end of the auction to select your agent.</div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>9.</b>&emsp;<b>Special Sale Properties:</b> We offer a wide range of properties, including regular sales,
                    pre-construction
                    properties, properties that are currently being built, new construction, REO/bank-owned properties,
                    assignment contracts (wholesale properties), short sales, probate properties, and more!</div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>10.</b>&emsp;<b>Types of Properties:</b> Agents on our platform offer residential, income, and commercial
                    properties,
                    including single-family residences, townhouses, villas, condominiums, condo-hotels, half-duplexes,
                    dock-rackominiums, farms, garage condos, manufactured homes, mobile homes, modular homes, duplexes,
                    triplexes, quadplexes, and properties with five or more residential units, as well as agriculture,
                    assembly building, business, properties with five or more commercial units, hotel/motel, industrial,
                    mixed-use, office, restaurant, retail, unimproved land, and warehouse properties.</div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>11.</b>&emsp;<b>Type of Listing:</b> The seller can choose between an auction (with a timer) or a traditional
                    listing
                    (without a timer). If the seller opts for an auction, the listing will end at the predetermined time
                    chosen by the seller, and all offers on this platform will be shown excluding sensitive materials. After
                    the auction ends, the seller can select the best agent that meets their criteria. The only way an
                    auction can end early is if an agent bids on the Hire Now Terms. On the other hand, if the seller
                    selects a traditional listing (without a timer), they can choose their agent at any time. Sellers also
                    have the option to show or hide bids with traditional listings. The seller has the right to accept,
                    counter, or reject any bids, regardless of whether it is an auction or traditional listing.</div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>12.</b>&emsp;<b>Hire Now Terms:</b> The Hire Now Terms refer to specific rebates, terms, and services buyers offer to
                    agents when bidding on a listing. These terms may include up to a 0.5% buyer's rebate, specific
                    contractual conditions, and services requested by the buyer. If an agent offers the "Hire Now Terms,"
                    the buyer can choose to end an auction early and hire that agent immediately.</div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>13.</b>&emsp;<b>Free Property Value Analysis:</b> Buyers can request a free property value analysis from the agent they hire for the property they are interested in purchasing.
                </div>
            </div>
            <div class="d-flex">
                {{-- <div><i class="fa fa-circle"></i></div> --}}
                {{-- <div>The Seller has the option to add in the “Seller’s Premium”</div> --}}
                <div><b>14.</b>&emsp;<b>Price:</b> The service is free for buyers. The seller pays the real estate commission to their listing agent, who then splits the commission with the buyer's agent. Going directly to the seller's listing agent doesn't save buyers money, as that agent is working with both parties. It's important to be represented by a buyer's agent to keep the buyer's best interests in mind. We will receive a referral fee from the hired agent at closing.
                </div>
            </div>

        </div>

    </div>
@endsection
