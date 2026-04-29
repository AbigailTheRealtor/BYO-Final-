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
                <p class="mt-3"><b>Welcome to BidYourAgent!</b> Connect with top-performing real estate agents who specialize in Residential, Income, Commercial, Business Opportunity, and Vacant Land properties. Agents compete to represent you — you pick the best fit.</p>
                @if(auth()->check() && auth()->user()->user_type === 'seller')
                    <a href="{{ route('sellerAgentHireAuction') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Hire Agent</a>
                @elseif(!auth()->check())
                    <a href="{{ route('register') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Get Started</a>
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
            <div><strong>Traditional or auction listing.</strong> Choose a traditional listing (no timer — hire whenever you're ready) or a timed auction listing. With a traditional listing, control bid visibility and accept, counter, or reject any bid at any time. With an auction listing, bids are collected over a set period and you choose the best agent at the end — or end early when an agent meets your Hire Now Terms. Both listing types support Hire Now Terms.</div>
        </div>
        <div class="step-item">
            <div class="step-num">6</div>
            <div><strong>Free for Sellers.</strong> BidYourAgent receives a referral fee from the hired agent at closing — no upfront cost to you. <a href="{{ route('sellerDetails') }}">Learn more <i class="fa fa-arrow-right"></i></a></div>
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
                    <h5 class="fw-bold"><i class="fa fa-handshake text-primary me-2"></i>Trust & Fairness</h5>
                    <p class="text-muted small mt-2 mb-0">BidYourAgent puts every party on the same playing field. Sellers can be confident they are seeing all agent bids transparently and in a timely manner.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-dollar-sign text-primary me-2"></i>No Upfront Cost</h5>
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
        <p class="text-muted mb-4">Seller-specific answers about the agent-hiring process.</p>

        <div class="accordion mt-4" id="sellerFAQ">
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf1" aria-expanded="true"><h4 class="mb-0">Is BidYourAgent free for Sellers?</h4></button>
                </div>
                <div id="sf1" class="accordion-collapse collapse show" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">Yes. Posting a listing as a Seller is completely free. BidYourAgent receives a referral fee from the agent you hire at closing — no upfront cost to you.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf2"><h4 class="mb-0">How do I post a listing?</h4></button>
                </div>
                <div id="sf2" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">Click "Hire Agent" on the homepage. Create an account and accept the Terms of Service. Fill in your property details, timeline, commission preferences, and the services you expect. Your listing goes live and agents begin submitting bids.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf3"><h4 class="mb-0">What is a Buyer's Rebate and how does it help me?</h4></button>
                </div>
                <div id="sf3" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">A Buyer's Rebate is a credit of up to 0.5% of the agent's commission offered back to the Buyer at closing. As a Seller, hiring an agent who offers this rebate can make your property more attractive to buyers — helping it stand out in a competitive market.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf4"><h4 class="mb-0">Can I invite a specific agent to bid?</h4></button>
                </div>
                <div id="sf4" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">Yes. When creating your listing, you can enter a preferred agent's contact information. We'll notify them directly so they can submit a bid. You can also share your listing's QR code or link with any agent you choose.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf5"><h4 class="mb-0">How do I review and select an agent?</h4></button>
                </div>
                <div id="sf5" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">Once agents bid, you'll receive email notifications. Log in to compare bids side by side — commission, marketing strategy, services, and more. Accept, counter, or decline any bid at any time. With a traditional listing you can hire at any time; with an auction listing you can hire at the end of the auction period or whenever an agent matches your Hire Now Terms.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf6"><h4 class="mb-0">What property types are supported?</h4></button>
                </div>
                <div id="sf6" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">BidYourAgent supports Residential, Income, Commercial, Business Opportunity, and Vacant Land listings for sale-side clients. Agents specialize by type, so your listing reaches the most relevant agents.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sf7"><h4 class="mb-0">Do I need to already have an agent to use BidYourAgent?</h4></button>
                </div>
                <div id="sf7" class="accordion-collapse collapse" data-bs-parent="#sellerFAQ">
                    <div class="accordion-body text-muted">No. BidYourAgent is designed for Sellers who do not yet have a listing agent. Post your listing, let agents compete, and hire the best fit. All real estate services are handled by the licensed agent you select.</div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
@push('scripts')
@endpush
