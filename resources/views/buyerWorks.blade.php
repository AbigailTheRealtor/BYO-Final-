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
                <h1>How It Works for Buyers</h1>
                <p class="mt-3"><b>Welcome to BidYourAgent!</b> Connect with licensed agents who specialize in Residential, Income, and Commercial properties. Agents bid to represent you — you pick the best fit. And it's free for you.</p>
                @if(auth()->check() && auth()->user()->user_type === 'buyer')
                    <a href="{{ route('buyer.add-auction') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Hire Agent</a>
                @elseif(!auth()->check())
                    <a href="{{ route('register') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Get Started Free</a>
                @endif
            </div>
            <div class="col-md-6 text-center">
                <img class="img-fluid rounded-3" style="max-height:280px;object-fit:cover;" src="{{ asset('assets/pictures/sellerWork/sellerWork.jpg') }}" alt="How it works for Buyers" />
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
            <div><strong>Post your buying criteria.</strong> Tell agents what you're looking for: cities, counties, states of interest, property type, desired sales provisions, preferred property condition, budget, timeframe, number of bedrooms and bathrooms, desired Buyer's Rebate (up to 0.5% of the agent's commission), buyer's agreement timeframe, offered financing/currency, pre-approval status, and services you expect. You can also invite a preferred agent directly.</div>
        </div>
        <div class="step-item">
            <div class="step-num">2</div>
            <div><strong>Agents bid to represent you.</strong> Each bid includes contact information, offered Buyer's Rebate (if applicable), website, review link, social media, "About Me", buyer's agreement timeframe, why they should be hired, what sets them apart, marketing strategy, services provided, a video buyer's presentation, promotional materials, and a business card.</div>
        </div>
        <div class="step-item">
            <div class="step-num">3</div>
            <div><strong>Earn a Buyer's Rebate.</strong> Agents on BidYourAgent can offer up to a 0.5% rebate of their commission back to you at closing. Use it toward closing costs or to buy down your interest rate.</div>
        </div>
        <div class="step-item">
            <div class="step-num">4</div>
            <div><strong>Invite your preferred agent.</strong> Enter your preferred agent's contact info when creating the listing — we'll notify them to bid. Share your listing link or QR code with any additional agents you'd like to hear from.</div>
        </div>
        <div class="step-item">
            <div class="step-num">5</div>
            <div><strong>For buyers without an existing agent.</strong> This service is exclusively for Buyers who are not currently working with a real estate agent. We recommend being in the market to purchase within three months or less.</div>
        </div>
        <div class="step-item">
            <div class="step-num">6</div>
            <div><strong>Compare and hire.</strong> Once agents bid, you'll receive email notifications. Review each bid, then accept, reject, or counter at any time (traditional listing) or wait for the auction to end (auction listing). Hire the agent who best matches your needs.</div>
        </div>
        <div class="step-item">
            <div class="step-num">7</div>
            <div><strong>Request a free property value analysis.</strong> Ask the agent you hire for a complimentary analysis of any property you're considering purchasing.</div>
        </div>
        <div class="step-item">
            <div class="step-num">8</div>
            <div><strong>Free for Buyers.</strong> The Seller pays the real estate commission — not you. Going directly to the Seller's listing agent doesn't save you money; that agent represents both sides. Use BidYourAgent to find a Buyer's agent who represents your best interests. BidYourAgent receives a referral fee from the hired agent at closing. <a href="{{ route('buyerDetails') }}">Learn more <i class="fa fa-arrow-right"></i></a></div>
        </div>
    </div>
</section>

{{-- Wide property types supported --}}
<section class="works-section" style="background:#f8f9fa;padding-top:40px;padding-bottom:50px;">
    <div class="container">
        <h2 class="fw-bold mb-2">Wide Range of Properties Supported</h2>
        <p class="text-muted mb-4">Agents on our platform cover Residential, Income, and Commercial properties, including:</p>
        <div class="row g-3">
            <div class="col-md-4">
                <ul class="list-unstyled text-muted small">
                    <li><i class="fa fa-check text-success me-2"></i>Single-family residences</li>
                    <li><i class="fa fa-check text-success me-2"></i>Townhouses &amp; villas</li>
                    <li><i class="fa fa-check text-success me-2"></i>Condominiums &amp; condo-hotels</li>
                    <li><i class="fa fa-check text-success me-2"></i>Duplexes, triplexes &amp; quadplexes</li>
                    <li><i class="fa fa-check text-success me-2"></i>Manufactured, mobile &amp; modular homes</li>
                </ul>
            </div>
            <div class="col-md-4">
                <ul class="list-unstyled text-muted small">
                    <li><i class="fa fa-check text-success me-2"></i>5+ unit residential buildings</li>
                    <li><i class="fa fa-check text-success me-2"></i>Agriculture &amp; farms</li>
                    <li><i class="fa fa-check text-success me-2"></i>Office, industrial &amp; retail</li>
                    <li><i class="fa fa-check text-success me-2"></i>Mixed-use &amp; hotel/motel</li>
                    <li><i class="fa fa-check text-success me-2"></i>Warehouse &amp; restaurant</li>
                </ul>
            </div>
            <div class="col-md-4">
                <ul class="list-unstyled text-muted small">
                    <li><i class="fa fa-check text-success me-2"></i>Pre-construction &amp; new construction</li>
                    <li><i class="fa fa-check text-success me-2"></i>REO/bank-owned properties</li>
                    <li><i class="fa fa-check text-success me-2"></i>Assignment (wholesale) contracts</li>
                    <li><i class="fa fa-check text-success me-2"></i>Short sales &amp; probate properties</li>
                    <li><i class="fa fa-check text-success me-2"></i>Dock-rackominiums &amp; garage condos</li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- Images --}}
<section class="works-section">
    <div class="container">
        <div class="text-center mb-4">
            <img class="img-fluid" src="{{ asset('assets/pictures/sellerWork/buyerOption.png') }}" alt="Buyer options" />
        </div>
    </div>
</section>

@endsection
