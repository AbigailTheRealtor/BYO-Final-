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
        <h1>How Seller Listings Work</h1>
        <p class="mt-3">Everything you need to know about creating a listing and hiring a Seller's agent on BidYourAgent.</p>
        @if(!auth()->check())
            <a href="{{ route('register') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Get Started Free</a>
        @elseif(auth()->user()->user_type === 'seller')
            <a href="{{ route('sellerAgentHireAuction') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Post a Listing</a>
        @endif
    </div>
</div>

{{-- Details Steps --}}
<section class="details-section">
    <div class="container">
        <div class="step-item">
            <div class="step-num">1</div>
            <div><strong>Information Provided by Sellers:</strong> To hire an agent, Sellers need to provide the property address, type, number of bedrooms and bathrooms, square footage, acceptable financing/currency, property condition, expected selling price, type of special sale, timeframe for selling, listing agreement timeframe, offered commission, requested buyer rebate, services they expect from their agent, property description, important property information, description of their ideal agent, and optionally requested property value analysis with photos and/or videos of their property.</div>
        </div>
        <div class="step-item">
            <div class="step-num">2</div>
            <div><strong>Information Included in an Agent's Bid:</strong> Agents who bid on a Seller's listing need to provide their contact information, offered commission, offered buyer's rebate, website link, review link, social media links, "About Me" section, listing agreement timeframe, why they should be hired, what sets them apart from other agents, marketing strategy, services provided to the Seller, video listing presentation, promotional marketing materials, business card, and property value analysis (if requested).</div>
        </div>
        <div class="step-item">
            <div class="step-num">3</div>
            <div><strong>Buyer Rebate:</strong> By utilizing this platform, Sellers can hire agents who offer a 0.5% rebate of the buyer's closing costs. This rebate will be given to the buyer at closing to help them pay their closing costs or buy down their interest rate. This rebate can help Sellers effectively market their property to potential buyers and make it stand out from other properties that do not offer such rebates.</div>
        </div>
        <div class="step-item">
            <div class="step-num">4</div>
            <div><strong>Auction Link:</strong> Sellers can enter their preferred agent's contact information when creating the listing, and we will notify that agent so they can bid to be the Seller's agent. Additionally, the Seller can share the auction link or QR code through various outlets, providing more opportunities for agents to compete to be their hired agent.</div>
        </div>
        <div class="step-item">
            <div class="step-num">5</div>
            <div><strong>No Existing Agent:</strong> This service is exclusively for Sellers who are not currently working with an agent.</div>
        </div>
        <div class="step-item">
            <div class="step-num">6</div>
            <div><strong>Timeline:</strong> We recommend being in the market to sell your property within 3 months or less to use this platform.</div>
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
            <div><strong>Special Sale Properties:</strong> Agents on our platform accept a wide range of properties, including regular sales, pre-construction properties, properties that are currently being built, new construction, REO/bank-owned properties, assignment contracts (wholesale properties), short sales, probate properties, and more!</div>
        </div>
        <div class="step-item">
            <div class="step-num">10</div>
            <div><strong>Types of Properties:</strong> Agents on our platform accept Residential, Income, and Commercial properties, including single-family residences, townhouses, villas, condominiums, condo-hotels, half-duplexes, dock-rackominiums, farms, garage condos, manufactured homes, mobile homes, modular homes, duplexes, triplexes, quadplexes, properties with five or more residential units, as well as agriculture, assembly building, business, properties with five or more commercial units, hotel/motel, industrial, mixed-use, office, restaurant, retail, unimproved land, and warehouse.</div>
        </div>
        <div class="step-item">
            <div class="step-num">11</div>
            <div><strong>Type of Listing:</strong> The Seller can choose between an Auction (with a timer) or a Traditional Listing (without a timer). If the Seller opts for an Auction, the listing will end at the predetermined time chosen by the Seller. After the auction ends, the Seller can select the best agent that meets their criteria. The only way an auction can end early is if an agent bids on the Hire Now Terms. On the other hand, if the Seller selects a Traditional Listing (without a timer), they can choose their agent at any time. The Seller has the right to accept, counter, or reject any bids.</div>
        </div>
        <div class="step-item">
            <div class="step-num">12</div>
            <div><strong>Hire Now Terms:</strong> The Hire Now Terms refer to specific commission rates, buyer's rebates, terms, and services that Sellers offer to agents. These terms may include an acceptable commission rate, up to a 0.5% buyer's rebate, specific contractual conditions, and services requested by the Seller. If an agent offers the "Hire Now Terms," the Seller can choose to end an auction early and hire that agent immediately.</div>
        </div>
        <div class="step-item">
            <div class="step-num">13</div>
            <div><strong>Free Property Value Analysis:</strong> Sellers can request a free property value analysis from agents. To ensure a reliable analysis, Sellers should include pictures and videos of their property. Upon receiving the necessary materials, agents can provide a complimentary property value analysis report to the Seller.</div>
        </div>
        <div class="step-item">
            <div class="step-num">14</div>
            <div><strong>Price:</strong> This service is free for Sellers. BidYourAgent will receive a referral fee from the hired agent upon closing.</div>
        </div>
    </div>
</section>

@endsection
