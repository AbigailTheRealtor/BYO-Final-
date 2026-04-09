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
            <a href="{{ route('register') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Get Started</a>
        @elseif(auth()->user()->user_type === 'buyer')
            <a href="{{ route('buyer.add-auction') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Create Listing</a>
        @endif
    </div>
</div>

{{-- Details Steps --}}
<section class="details-section">
    <div class="container">
        <div class="step-item">
            <div class="step-num">1</div>
            <div><strong>Information Provided by Buyers:</strong> To hire an agent, Buyers provide details on the cities, counties, and states of interest; property type (Residential, Income, Commercial, Business Opportunity); desired sales provisions; preferred property condition; budget; timeframe for purchasing; number of bedrooms and bathrooms required; desired Buyer's Rebate (up to 0.5% of the agent's commission); buyer's agreement timeframe; offered financing or pre-approval status; and the services they request from their agent. Buyers may also invite a preferred agent directly from the listing form.</div>
        </div>
        <div class="step-item">
            <div class="step-num">2</div>
            <div><strong>Information Included in an Agent's Bid:</strong> Agents who bid on a Buyer's listing include their contact information, offered Buyer's Rebate (if applicable), website link, review link, social media links, "About Me" section, buyer's agreement timeframe, why they should be hired, what sets them apart from other agents, marketing strategy, services provided to the Buyer, video buyer's presentation, promotional marketing materials, and business card.</div>
        </div>
        <div class="step-item">
            <div class="step-num">3</div>
            <div><strong>Buyer's Rebate:</strong> By using this platform, Buyers can hire agents who offer up to a 0.5% rebate of the agent's commission. This rebate is credited to the Buyer at closing and can be used toward closing costs or to buy down their interest rate.</div>
        </div>
        <div class="step-item">
            <div class="step-num">4</div>
            <div><strong>Invite a Preferred Agent:</strong> Buyers can enter their preferred agent's contact information when creating the listing, and we will notify that agent so they can bid to be hired. Additionally, the Buyer can share the listing link or QR code with any other agents they want to hear from.</div>
        </div>
        <div class="step-item">
            <div class="step-num">5</div>
            <div><strong>No Existing Agent:</strong> This service is exclusively for Buyers who are not currently working with a real estate agent.</div>
        </div>
        <div class="step-item">
            <div class="step-num">6</div>
            <div><strong>Timeline:</strong> We recommend being in the market to buy a property within three months or less to use this platform.</div>
        </div>
        <div class="step-item">
            <div class="step-num">7</div>
            <div><strong>Match Score:</strong> BidYourAgent's Match Score ranks agents by how well their specialization aligns with your property type. Simply post your listing with your property type and budget, and the platform surfaces the most relevant agents for your needs.</div>
        </div>
        <div class="step-item">
            <div class="step-num">8</div>
            <div><strong>Selecting an Agent:</strong> Once an agent bids on your listing, you will receive an email notification. Log in to review the bid and choose to accept, counter, or decline at any time with a traditional listing. If you choose an auction listing, you will wait until the end of the auction period to select your agent — unless an agent meets your Hire Now Terms, which lets you end the process early.</div>
        </div>
        <div class="step-item">
            <div class="step-num">9</div>
            <div><strong>Wide Range of Properties:</strong> Agents on our platform cover a wide range of property types, including regular sales, pre-construction, new construction, REO/bank-owned, assignment contracts (wholesale), short sales, probate properties, and more.</div>
        </div>
        <div class="step-item">
            <div class="step-num">10</div>
            <div><strong>Types of Properties:</strong> Agents on our platform specialize in Residential, Income, Commercial, and Business Opportunity properties, including single-family residences, townhouses, villas, condominiums, condo-hotels, duplexes, triplexes, quadplexes, manufactured homes, mobile homes, modular homes, dock-rackominiums, garage condos, farms, properties with five or more residential units, agriculture, office, industrial, retail, mixed-use, hotel/motel, restaurant, warehouse, and more.</div>
        </div>
        <div class="step-item">
            <div class="step-num">11</div>
            <div><strong>Listing Type:</strong> Buyers can choose between a traditional listing (no timer — hire at any time) or an auction listing (with a set time limit). With a traditional listing, you can select and hire an agent whenever you're ready. With an auction listing, bids are collected over the listing period and you choose the best agent at the end. Either way, you can accept, counter, or decline any bid.</div>
        </div>
        <div class="step-item">
            <div class="step-num">12</div>
            <div><strong>Hire Now Terms:</strong> When creating a listing, Buyers can set Hire Now Terms — specific rebates, services, or conditions that would prompt them to hire immediately. If an agent meets those terms, the Buyer can end the listing early and hire that agent right away.</div>
        </div>
        <div class="step-item">
            <div class="step-num">13</div>
            <div><strong>Free Property Value Analysis:</strong> Buyers can request a complimentary property value analysis from the agent they hire for any property they are considering purchasing.</div>
        </div>
        <div class="step-item">
            <div class="step-num">14</div>
            <div><strong>Free for Buyers:</strong> This service is free for Buyers. The Seller pays the real estate commission to their listing agent, who then splits it with the Buyer's agent. Going directly to the Seller's listing agent does not save Buyers money — that agent represents both parties. A dedicated Buyer's agent keeps your best interests front and center. BidYourAgent receives a referral fee from the hired agent at closing.</div>
        </div>
    </div>
</section>

@endsection
