@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sellerWork.css') }}" />
    <style>
        .details-hero { background: linear-gradient(135deg, #006e9f 0%, #049399 100%); color:#fff; padding:50px 0; }
        .details-hero h1 { font-weight:700; }
        .details-hero p { opacity:.9; max-width:560px; }
        .step-item { display:flex; gap:16px; margin-bottom:22px; align-items:flex-start; }
        .step-num { background:#006e9f; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0; font-size:.9rem; }
        .details-section { padding:50px 0; }
    </style>
@endpush
@section('content')

{{-- Hero --}}
<div class="details-hero">
    <div class="container">
        <h1>How Buyer Listings Work</h1>
        <p class="mt-3">Everything you need to know about creating a listing and hiring a Buyer's agent on BidYourAgent.</p>
        @if(!auth()->check())
            <a href="{{ route('register') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Get Started Free</a>
        @elseif(auth()->user()->user_type === 'buyer')
            <a href="{{ route('buyer.add-auction') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Post a Listing</a>
        @endif
    </div>
</div>

{{-- Details Steps --}}
<section class="details-section">
    <div class="container">
        <div class="step-item">
            <div class="step-num">1</div>
            <div><strong>Information Provided by Buyers:</strong> To hire an agent, Buyers provide information on the cities, counties, and states of interest, property types they prefer, desired sales provisions, preferred property condition, budget, timeframe for purchasing, number of bedrooms (for residential properties) and bathrooms required, desired Buyer's Rebate (up to half a percent of the agent's commission), buyer's agreement timeframe, offered financing/currency, whether they are pre-approved for a loan, a cash buyer, or need lender recommendations, and services they request from their agent. Additionally, Buyers may request that their preferred agents be notified.</div>
        </div>
        <div class="step-item">
            <div class="step-num">2</div>
            <div><strong>Information Included in an Agent's Bid:</strong> Agents who bid on a Buyer's listing include their contact information, offered Buyer's Rebate (if applicable), website link, review link, social media links, "About Me" section, buyer's agreement timeframe, why they should be hired, what sets them apart from other agents, marketing strategy, services provided to the Buyer, video buyer's presentation, promotional marketing materials, and business card.</div>
        </div>
        <div class="step-item">
            <div class="step-num">3</div>
            <div><strong>Buyer Rebate:</strong> By utilizing this platform, Buyers can hire agents who offer a 0.5% rebate of a buyer's closing costs. This rebate will be credited to the Buyer at closing, and they can use it to help with their closing costs or to buy down their interest rate.</div>
        </div>
        <div class="step-item">
            <div class="step-num">4</div>
            <div><strong>Auction Link:</strong> Buyers can enter their preferred agent's contact information when creating the listing, and we will notify that agent so they can bid to be the Buyer's agent. Additionally, the Buyer can share the auction link or QR code through various outlets, providing more opportunities for agents to compete to be their hired agent.</div>
        </div>
        <div class="step-item">
            <div class="step-num">5</div>
            <div><strong>No Existing Agent:</strong> This service is exclusively for Buyers who are not currently working with an agent.</div>
        </div>
        <div class="step-item">
            <div class="step-num">6</div>
            <div><strong>Timeline:</strong> We recommend being in the market to buy a property within three months or less to use this platform.</div>
        </div>
        <div class="step-item">
            <div class="step-num">7</div>
            <div><strong>Types of Agents:</strong> You can hire Residential, Income, and Commercial agents for this platform. Simply select your property type, and we will match you with an agent who specializes in your property type.</div>
        </div>
        <div class="step-item">
            <div class="step-num">8</div>
            <div><strong>Selecting an Agent:</strong> Once an agent places a bid on your listing, you will receive an email notification. You can then log in to your account to review the bid and choose to accept, deny, reject, or counter any offers you receive at any time with a traditional listing. If you choose an auction listing, you will need to wait until the end of the auction to select your agent.</div>
        </div>
        <div class="step-item">
            <div class="step-num">9</div>
            <div><strong>Special Sale Properties:</strong> We offer a wide range of properties, including regular sales, pre-construction properties, properties that are currently being built, new construction, REO/bank-owned properties, assignment contracts (wholesale properties), short sales, probate properties, and more!</div>
        </div>
        <div class="step-item">
            <div class="step-num">10</div>
            <div><strong>Types of Properties:</strong> Agents on our platform offer Residential, Income, and Commercial properties, including single-family residences, townhouses, villas, condominiums, condo-hotels, half-duplexes, dock-rackominiums, farms, garage condos, manufactured homes, mobile homes, modular homes, duplexes, triplexes, quadplexes, and properties with five or more residential units, as well as agriculture, assembly building, business, properties with five or more commercial units, hotel/motel, industrial, mixed-use, office, restaurant, retail, unimproved land, and warehouse properties.</div>
        </div>
        <div class="step-item">
            <div class="step-num">11</div>
            <div><strong>Type of Listing:</strong> The Seller can choose between an auction (with a timer) or a traditional listing (without a timer). If the Seller opts for an auction, the listing will end at the predetermined time chosen by the Seller, and all offers on this platform will be shown excluding sensitive materials. After the auction ends, the Seller can select the best agent that meets their criteria. The only way an auction can end early is if an agent bids on the Hire Now Terms. On the other hand, if the Seller selects a traditional listing (without a timer), they can choose their agent at any time. Sellers also have the option to show or hide bids with traditional listings. The Seller has the right to accept, counter, or reject any bids, regardless of whether it is an auction or traditional listing.</div>
        </div>
        <div class="step-item">
            <div class="step-num">12</div>
            <div><strong>Hire Now Terms:</strong> The Hire Now Terms refer to specific rebates, terms, and services Buyers offer to agents when bidding on a listing. These terms may include up to a 0.5% Buyer's Rebate, specific contractual conditions, and services requested by the Buyer. If an agent offers the "Hire Now Terms," the Buyer can choose to end an auction early and hire that agent immediately.</div>
        </div>
        <div class="step-item">
            <div class="step-num">13</div>
            <div><strong>Free Property Value Analysis:</strong> Buyers can request a free property value analysis from the agent they hire for the property they are interested in purchasing.</div>
        </div>
        <div class="step-item">
            <div class="step-num">14</div>
            <div><strong>Price:</strong> The service is free for Buyers. The Seller pays the real estate commission to their listing agent, who then splits the commission with the Buyer's agent. Going directly to the Seller's listing agent doesn't save Buyers money, as that agent is working with both parties. It's important to be represented by a Buyer's agent to keep the Buyer's best interests in mind. BidYourAgent will receive a referral fee from the hired agent at closing.</div>
        </div>
    </div>
</section>

@endsection
