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
            <a href="{{ route('register') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Get Started</a>
        @elseif(auth()->user()->user_type === 'seller')
            <a href="{{ route('sellerAgentHireAuction') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Create Listing</a>
        @endif
    </div>
</div>

{{-- Details Steps --}}
<section class="details-section">
    <div class="container">
        <div class="step-item">
            <div class="step-num">1</div>
            <div><strong>Information Provided by Sellers:</strong> To hire an agent, Sellers provide the property address; property type (Residential, Income, Commercial, Business Opportunity, Vacant Land); number of bedrooms and bathrooms; square footage; acceptable financing or currency; property condition; expected selling price; type of special sale; timeframe for selling; listing agreement timeframe; offered commission; requested Buyer's Rebate; services expected from their agent; property description; important property details; and a description of their ideal agent. Sellers may also optionally request a property value analysis and upload photos and videos.</div>
        </div>
        <div class="step-item">
            <div class="step-num">2</div>
            <div><strong>Information Included in an Agent's Bid:</strong> Agents who bid on a Seller's listing provide their contact information, offered commission, offered Buyer's Rebate, website link, review link, social media links, "About Me" section, listing agreement timeframe, why they should be hired, what sets them apart from other agents, marketing strategy, services provided to the Seller, video listing presentation, promotional marketing materials, business card, and property value analysis (if requested).</div>
        </div>
        <div class="step-item">
            <div class="step-num">3</div>
            <div><strong>Buyer's Rebate:</strong> By using this platform, Sellers can hire agents who offer a 0.5% rebate of the agent's commission to the Buyer at closing. This rebate helps Buyers cover closing costs or buy down their interest rate — and makes your property more attractive and competitive in the marketplace.</div>
        </div>
        <div class="step-item">
            <div class="step-num">4</div>
            <div><strong>Invite a Preferred Agent:</strong> Sellers can enter their preferred agent's contact information when creating the listing, and we will notify that agent so they can bid to be hired. Additionally, the Seller can share the listing link or QR code with any other agents they want to hear from.</div>
        </div>
        <div class="step-item">
            <div class="step-num">5</div>
            <div><strong>No Existing Agent:</strong> This service is designed for Sellers who are not currently working with a listing agent and are looking to hire one through a competitive process.</div>
        </div>
        <div class="step-item">
            <div class="step-num">6</div>
            <div><strong>Timeline:</strong> We recommend being in the market to sell your property within three months or less to get the most from this platform.</div>
        </div>
        <div class="step-item">
            <div class="step-num">7</div>
            <div><strong>Match Score:</strong> BidYourAgent's Match Score ranks agents by how well their specialization aligns with your property type. Post your listing and the platform surfaces the most relevant, experienced agents for your specific needs — Residential, Income, Commercial, Business Opportunity, or Vacant Land.</div>
        </div>
        <div class="step-item">
            <div class="step-num">8</div>
            <div><strong>Selecting an Agent:</strong> Once an agent bids on your listing, you will receive an email notification. Log in to review the bid and choose to accept, counter, or decline at any time with a traditional listing. If you choose an auction listing, you will wait until the end of the auction period to select your agent — unless an agent meets your Hire Now Terms, which lets you end the process early and hire immediately.</div>
        </div>
        <div class="step-item">
            <div class="step-num">9</div>
            <div><strong>Wide Range of Properties:</strong> Agents on our platform accept a wide range of properties, including regular sales, pre-construction, new construction, REO/bank-owned, assignment contracts (wholesale), short sales, probate properties, and more.</div>
        </div>
        <div class="step-item">
            <div class="step-num">10</div>
            <div><strong>Types of Properties:</strong> Agents on our platform specialize in Residential, Income, Commercial, Business Opportunity, and Vacant Land properties, including single-family residences, townhouses, villas, condominiums, condo-hotels, duplexes, triplexes, quadplexes, manufactured homes, mobile homes, modular homes, dock-rackominiums, garage condos, farms, properties with five or more residential units, agriculture, office, industrial, retail, mixed-use, hotel/motel, restaurant, warehouse, and more.</div>
        </div>
        <div class="step-item">
            <div class="step-num">11</div>
            <div><strong>Listing Type:</strong> Sellers can choose between a traditional listing (no timer — hire at any time) or an auction listing (with a set time limit). With a traditional listing, you can show or hide bids and select your agent whenever you're ready. With an auction listing, the process runs for a set period and you choose the best agent at the end. Either way, you retain the right to accept, counter, or decline any bid.</div>
        </div>
        <div class="step-item">
            <div class="step-num">12</div>
            <div><strong>Hire Now Terms:</strong> When creating a listing, Sellers can set Hire Now Terms — a specific commission rate, Buyer's Rebate, services, or conditions that would prompt them to hire immediately. If an agent meets those terms, the Seller can end the listing early and hire that agent right away.</div>
        </div>
        <div class="step-item">
            <div class="step-num">13</div>
            <div><strong>Free Property Value Analysis:</strong> Sellers can request a complimentary property value analysis from agents as part of the bid. To ensure a reliable analysis, include photos and videos of your property when creating the listing.</div>
        </div>
        <div class="step-item">
            <div class="step-num">14</div>
            <div><strong>Free for Sellers:</strong> This service is free for Sellers. BidYourAgent receives a referral fee from the hired agent at closing. All actual real estate services — listing, marketing, negotiating, and closing — are handled by the licensed agent you hire.</div>
        </div>
    </div>
</section>

@endsection
