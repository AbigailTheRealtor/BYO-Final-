@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sellerWork.css') }}" />
    <style>
        .works-hero { background: linear-gradient(135deg, #006e9f 0%, #049399 100%); color:#fff; padding:50px 0; }
        .works-hero h1 { font-weight:700; }
        .works-hero p { opacity:.9; max-width:560px; }
        .step-item { display:flex; gap:16px; margin-bottom:24px; align-items:flex-start; }
        .step-num { background:#006e9f; color:#fff; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0; font-size:.9rem; }
        .works-section { padding:50px 0; }
        .why-card { border:1px solid #e0e0e0; border-radius:8px; padding:24px; height:100%; }
    </style>
@endpush
@section('content')

{{-- Hero --}}
<div class="works-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-md-6">
                <h1>How It Works for Sellers</h1>
                <p class="mt-3"><b>Welcome to BidYourAgent!</b> Connect with top-performing real estate agents who specialize in Residential, Income, and Commercial properties. Agents compete to represent you — you pick the best fit.</p>
                @if(auth()->check() && auth()->user()->user_type === 'seller')
                    <a href="{{ route('sellerAgentHireAuction') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Hire Agent</a>
                @elseif(!auth()->check())
                    <a href="{{ route('register') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Get Started Free</a>
                @endif
            </div>
            <div class="col-md-6 text-center">
                <img class="img-fluid rounded-3" style="max-height:280px;object-fit:cover;" src="{{ asset('assets/pictures/sellerWork/sellerWork.jpg') }}" alt="How it works for Sellers" />
            </div>
        </div>
    </div>
</div>

{{-- How It Works Steps --}}
<section class="works-section">
    <div class="container">
        <h2 class="fw-bold mb-4">The Process</h2>

        <div class="step-item">
            <div class="step-num">1</div>
            <div><strong>Post your property details.</strong> Provide the property address, type (Residential / Income / Commercial / Business Opportunity / Vacant Land), bedrooms, bathrooms, square footage, acceptable financing, property condition, expected selling price, timeline, listing agreement timeframe, offered commission, requested buyer rebate, services expected from your agent, property description, ideal agent description, and optionally photos, videos, and a property value analysis request.</div>
        </div>
        <div class="step-item">
            <div class="step-num">2</div>
            <div><strong>Agents bid to represent you.</strong> Each agent bid includes contact information, offered commission, offered buyer's rebate, website, reviews link, social media, "About Me", listing agreement timeframe, why they should be hired, marketing strategy, services provided, a video listing presentation, promotional materials, business card, and property value analysis (if requested). Notify a preferred agent directly from the listing form.</div>
        </div>
        <div class="step-item">
            <div class="step-num">3</div>
            <div><strong>Seller-friendly buyer rebate option.</strong> Hire agents who offer a 0.5% rebate to the buyer at closing — this can help market your property more competitively and differentiate it from others.</div>
        </div>
        <div class="step-item">
            <div class="step-num">4</div>
            <div><strong>Wide range of property types.</strong> Regular sales, pre-construction, new construction, REO/bank-owned, assignment contracts (wholesale), short sales, probate properties, and more.</div>
        </div>
        <div class="step-item">
            <div class="step-num">5</div>
            <div><strong>Auction or Traditional listing.</strong> Choose a timed auction or a traditional listing (no timer). With auctions, all bids are visible — accept, reject, or counter at any time, or wait for the auction to end. With traditional listings, control bid visibility and accept, reject, or counter at any time. Include "Hire Now" terms with either option.</div>
        </div>
        <div class="step-item">
            <div class="step-num">6</div>
            <div><strong>Free for Sellers.</strong> BidYourAgent receives a referral fee from the hired agent at closing. By hiring through our platform, sellers can also list their property for free on our sister platform, BidYourAgent.com. <a href="{{ route('sellerDetails') }}">Learn more <i class="fa fa-arrow-right"></i></a></div>
        </div>
    </div>
</section>

{{-- Why Use BidYourAgent --}}
<section class="works-section" style="background:#f8f9fa;padding-top:40px;padding-bottom:50px;">
    <div class="container">
        <h2 class="fw-bold mb-4">Why Use BidYourAgent?</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-eye text-primary me-2"></i>Full Transparency</h5>
                    <p class="text-muted small mt-2 mb-0">Every agent bid is visible — commission, rebate, marketing strategy, and services. Compare side by side and make an informed decision.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-handshake-o text-primary me-2"></i>Trust & Fairness</h5>
                    <p class="text-muted small mt-2 mb-0">BidYourAgent puts every party on the same playing field. Sellers can be confident they are seeing all offers fairly and in a timely manner.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-dollar text-primary me-2"></i>No Upfront Cost</h5>
                    <p class="text-muted small mt-2 mb-0">Listing is completely free for Sellers. BidYourAgent collects a referral fee from the hired agent — only when the deal closes.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Images --}}
<section class="works-section">
    <div class="container">
        <div class="text-center mb-4">
            <img class="img-fluid" src="{{ asset('assets/pictures/sellerWork/sellingOption.png') }}" alt="Selling options" />
        </div>
        <div class="text-center">
            <img class="img-fluid" height="2757" src="{{ asset('assets/pictures/sellerWork/serviceOption.png') }}" alt="Service options" loading="lazy" />
        </div>
        <div class="text-center mt-4">
            <img class="img-fluid" src="{{ asset('assets/pictures/sellerWork/priceCamparsion.jpg') }}" alt="Price comparison" loading="lazy" />
        </div>
    </div>
</section>

{{-- FAQ Accordion --}}
<section class="works-section" style="background:#f8f9fa;padding-top:40px;padding-bottom:50px;">
    <div class="container">
        <h2 class="fw-bold mb-1">Frequently Asked Questions</h2>
        <p class="text-muted mb-4">Seller-specific answers about fees, premiums, and the listing process.</p>
        <p class="text-muted small"><em>Note: The 1% Success fee can be paid by the Buyer if the Seller chooses to have the Buyer credit the Seller at closing. Sellers can also add a "Buyer's Premium" to help cover closing costs — making it easy to get full-service representation without out-of-pocket expense.</em></p>

        <div class="accordion mt-4" id="sellerFAQ">
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf1" aria-expanded="true"><h4 class="mb-0">What is a Success Fee?</h4></button>
                </div>
                <div id="sf1" class="accordion-collapse collapse show" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">The success fee is 1% of the purchase price paid to BidYourAgent LLC at closing. It is added to the final sales price if paid by the Buyer, or deducted from the Seller's proceeds if paid by the Seller. If the property contract is canceled, the Seller or Seller's agent must submit a cancellation contract to admin@bidyouragent.com. The Success fee is only charged when the sale closes.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf2"><h4 class="mb-0">What is a Buyer's Premium?</h4></button>
                </div>
                <div id="sf2" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">The Buyer's Premium is a charge to the Buyer added on top of the final sales price. It is credited to the Seller at closing and helps offset closing expenses — enabling Sellers to work with a full-service agent without paying full commission. The amount is determined by the Seller. Cancellations require a cancellation contract submitted to admin@bidyouragent.com.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf3"><h4 class="mb-0">What is a Seller's Premium?</h4></button>
                </div>
                <div id="sf3" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">The Seller can optionally include a "Seller's Premium" — a percentage of the purchase price paid by the Seller to the Buyer at closing. This reduces the Buyer's closing costs and makes the property more attractive, particularly useful in a buyer's market or for properties that need work.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf4"><h4 class="mb-0">When are premiums and the success fee due?</h4></button>
                </div>
                <div id="sf4" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">All fees are collected at closing only. If the contract falls through, the Seller or Seller's agent must promptly submit a release and cancellation agreement to admin@bidyouragent.com.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf5"><h4 class="mb-0">How do I list a property?</h4></button>
                </div>
                <div id="sf5" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">Click "Hire Agent" on the homepage. Create an account and accept the Terms of Service. Sign an addendum agreeing to pay the 1% Success Fee at closing before your listing goes live. Sellers can add this fee to the auction and have the Buyer credit it at closing if they choose.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf6"><h4 class="mb-0">How much does it cost to list my property?</h4></button>
                </div>
                <div id="sf6" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">Listing is completely free. At closing, BidYourAgent LLC receives a 1% success fee — which the Buyer can credit to the Seller to pay the fee. If listed by a Seller's agent, the agent receives half the success fee premium at closing for a successful auction.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf7"><h4 class="mb-0">Do I need a sales contract ready before the auction?</h4></button>
                </div>
                <div id="sf7" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">No. The Seller's Agent will provide the winning bid with an "AS IS" contract, all disclosures, and addendums. If no agent is involved, email admin@bidyouragent.com to request the documents. The Buyer has 48 hours to sign and submit, or the offer may be considered null and void.</div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
@push('scripts')
@endpush
