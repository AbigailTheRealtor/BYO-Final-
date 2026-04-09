@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/buyerWorkAgent.css') }}" />
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
                <h1>How It Works for Buyer's Agents</h1>
                <p class="mt-3"><b>Welcome to BidYourAgent!</b> Our platform gives buyer's agents a transparent, competitive way to win new clients from Buyers and Tenants looking for representation. Browse open client listings, submit competitive bids, and grow your business with inbound opportunities.</p>
                <a href="{{ route('register') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Join as Agent</a>
            </div>
            <div class="col-md-6 text-center">
                <img class="img-fluid rounded-3 w-75" src="{{ asset('assets/pictures/buyerWorkAgent/buyerWorkAgent.jpg') }}" alt="How BidYourAgent works for Buyer's Agents" />
            </div>
        </div>
    </div>
</div>

{{-- How It Works --}}
<section class="works-section">
    <div class="container">
        <h2 class="fw-bold mb-4">The Process for Buyer's Agents</h2>

        <div class="step-item">
            <div class="step-num">1</div>
            <div><strong>Find open Buyer listings.</strong> Browse listings posted by Buyers and Tenants who need a buyer's agent. Filter by property type, location criteria, and timeline to find the best matches for your expertise and service area.</div>
        </div>
        <div class="step-item">
            <div class="step-num">2</div>
            <div><strong>Submit a competitive bid.</strong> Each bid includes your contact information, offered Buyer's Rebate (if applicable), website, review link, social media profiles, "About Me" section, buyer's agreement timeframe, why you should be hired, what sets you apart, marketing strategy, services you'll provide, a video buyer's presentation, promotional materials, and a business card.</div>
        </div>
        <div class="step-item">
            <div class="step-num">3</div>
            <div><strong>Offer a Buyer's Rebate.</strong> Stand out by offering up to a 0.5% rebate of your commission back to the Buyer at closing. This credit can be applied toward their closing costs or used to buy down their interest rate — a compelling reason to choose you over competing agents.</div>
        </div>
        <div class="step-item">
            <div class="step-num">4</div>
            <div><strong>Get invited directly.</strong> Buyers can enter your contact information when creating their listing to notify you directly. You'll get a head start over other agents and can submit your bid right away.</div>
        </div>
        <div class="step-item">
            <div class="step-num">5</div>
            <div><strong>Match Score ranks you for relevance.</strong> BidYourAgent's Match Score surfaces your profile to Buyers whose criteria align with your specialization. A complete, up-to-date profile improves your score and your visibility.</div>
        </div>
        <div class="step-item">
            <div class="step-num">6</div>
            <div><strong>Traditional and auction listing types.</strong> Buyers can post traditional listings (you can be hired at any time) or auction listings (selection happens at the end of the auction period, or earlier if you match the Buyer's Hire Now Terms). Check each listing to know which format applies.</div>
        </div>
        <div class="step-item">
            <div class="step-num">7</div>
            <div><strong>Earn a referral commission at closing.</strong> BidYourAgent receives a referral fee from you at closing in exchange for the client match. No upfront cost — you only pay when a deal closes. Check each listing for exact referral terms.</div>
        </div>
        <div class="step-item">
            <div class="step-num">8</div>
            <div><strong>Provide a free property value analysis.</strong> Buyers may request a complimentary property value analysis as part of your bid. Including one as a value-add service helps you stand out from competing agents.</div>
        </div>
    </div>
</section>

{{-- Why Use BidYourAgent --}}
<section class="works-section" style="background:#f8f9fa;padding-top:40px;padding-bottom:50px;">
    <div class="container">
        <h2 class="fw-bold mb-2">Why Use BidYourAgent?</h2>
        <p class="text-muted mb-4">Trust and transparency are the #1 qualities Buyers want in a real estate transaction. BidYourAgent delivers both — and rewards agents who compete on merit.</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-bullseye text-primary me-2"></i>Inbound Opportunities</h5>
                    <p class="text-muted small mt-2 mb-0">Buyers come to you. Browse open listings from motivated clients who have posted their criteria and are actively looking for representation — no cold calling needed.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-handshake-o text-primary me-2"></i>Qualified Client Intent</h5>
                    <p class="text-muted small mt-2 mb-0">Every listing comes with pre-qualified Buyer intent. Clients post their criteria, timeline, and financing status upfront — so you know exactly who you're bidding for before you commit.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-dollar text-primary me-2"></i>No Upfront Cost</h5>
                    <p class="text-muted small mt-2 mb-0">BidYourAgent receives a referral fee from you at closing. No cost until the deal closes — a risk-free way to grow your pipeline with motivated clients.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- FAQ Accordion --}}
<section class="works-section">
    <div class="container">
        <h2 class="fw-bold mb-1">Frequently Asked Questions</h2>
        <p class="text-muted mb-4">Buyer's agent-specific answers about bidding, fees, and the hiring process.</p>

        <div class="accordion" id="buyerAgentFAQ">
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba1" aria-expanded="true"><h4 class="mb-0">How do I register to bid on Buyer listings?</h4></button>
                </div>
                <div id="ba1" class="accordion-collapse collapse show" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">Create an account under the "Register" tab, select "Agent" as your user type, and accept the Terms and Conditions. Once registered, you can immediately browse all open Buyer and Tenant listings and submit your bids.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba2"><h4 class="mb-0">Will I earn a commission if I'm hired?</h4></button>
                </div>
                <div id="ba2" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">Yes. The commission structure is noted in each client listing. Review the listing details carefully before placing a bid to understand the commission and referral fee terms.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba3"><h4 class="mb-0">What does it cost to use BidYourAgent as a Buyer's agent?</h4></button>
                </div>
                <div id="ba3" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">There is no upfront cost. BidYourAgent receives a referral fee from you at closing when a deal closes with a client matched through the platform. You only pay when you earn.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba4"><h4 class="mb-0">What is a Buyer's Rebate and should I offer one?</h4></button>
                </div>
                <div id="ba4" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">A Buyer's Rebate is a credit of up to 0.5% of your commission offered back to the Buyer at closing. Offering a rebate is a powerful way to differentiate your bid and signal to clients that you compete on value — not just price.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba5"><h4 class="mb-0">What is Match Score and how do I improve mine?</h4></button>
                </div>
                <div id="ba5" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">Match Score is a ranking that reflects how well your specialization aligns with each client listing. Keep your profile complete — specializations, service areas, credentials, and experience — to improve your Match Score and appear more prominently to the right clients.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba6"><h4 class="mb-0">How does referral income work for Buyer's agents?</h4></button>
                </div>
                <div id="ba6" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">Beyond representing clients directly, agents can refer clients to other agents on the platform and earn a referral fee at closing. You can also register as a referral agent — connecting clients with the right representation and earning income without managing the full transaction.</div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
