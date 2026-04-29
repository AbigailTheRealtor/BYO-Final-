@extends('layouts.main')
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/sellerWorkAgent.css') }}" />
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
                <h1>How It Works for Agents</h1>
                <p class="mt-3"><b>Welcome to BidYourAgent!</b> Our platform gives real estate agents a transparent, competitive way to win new listings from Sellers, Buyers, Landlords, and Tenants. Bid to be hired, showcase your expertise, and earn referral income — all in one place.</p>
                <a href="{{ route('register') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Join as Agent</a>
            </div>
            <div class="col-md-6 text-center">
                <img class="img-fluid rounded-3 w-75" src="https://bidyouragent.com/wp-content/uploads/2022/08/iStock-93100462-scaled.jpg" alt="How BidYourAgent works for agents" />
            </div>
        </div>
    </div>
</div>

{{-- How It Works --}}
<section class="works-section">
    <div class="container">
        <h2 class="fw-bold mb-4">The Process for Agents</h2>

        <div class="step-item">
            <div class="step-num">1</div>
            <div><strong>Browse open client listings.</strong> Find Sellers, Buyers, Landlords, and Tenants who have posted listings and are actively looking for an agent to represent them. Filter by property type, client role, and search criteria to find the best matches for your expertise.</div>
        </div>
        <div class="step-item">
            <div class="step-num">2</div>
            <div><strong>Submit a competitive bid.</strong> Your bid includes your contact information, offered commission, offered Buyer's Rebate (if applicable), website, review link, social media profiles, "About Me" section, listing agreement timeframe, why you should be hired, your marketing strategy, services you'll provide, a video listing presentation, promotional materials, and business card. For Seller listings, you may also include a property value analysis.</div>
        </div>
        <div class="step-item">
            <div class="step-num">3</div>
            <div><strong>Get invited directly.</strong> Clients can enter your contact information when creating their listing to notify you directly. You'll get a head start on competing agents and can submit your bid right away.</div>
        </div>
        <div class="step-item">
            <div class="step-num">4</div>
            <div><strong>Match Score ranks you for relevance.</strong> BidYourAgent's Match Score surfaces your profile to clients whose listing type aligns with your specialization — Residential, Income, Commercial, Business Opportunity, or Vacant Land. A strong match means more visibility.</div>
        </div>
        <div class="step-item">
            <div class="step-num">5</div>
            <div><strong>Traditional and auction listing types.</strong> Clients can post traditional listings (you can be hired at any time) or auction listings (selection happens at the end of the auction period, or earlier if an agent matches Hire Now Terms). Check each listing to know which format applies.</div>
        </div>
        <div class="step-item">
            <div class="step-num">6</div>
            <div><strong>Earn a referral fee at closing.</strong> BidYourAgent receives a referral fee from the hired agent at closing — no upfront cost to you. You only pay when a deal closes. Check each listing for exact terms.</div>
        </div>
    </div>
</section>

{{-- Why Use BidYourAgent --}}
<section class="works-section" style="background:#f8f9fa;padding-top:40px;padding-bottom:50px;">
    <div class="container">
        <h2 class="fw-bold mb-2">Why Use BidYourAgent?</h2>
        <p class="text-muted mb-4">Trust and transparency are what Sellers and Buyers want most in a real estate transaction. BidYourAgent delivers both — and rewards agents who compete on merit.</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-bullseye text-primary me-2"></i>Inbound Opportunities</h5>
                    <p class="text-muted small mt-2 mb-0">Clients come to you. Browse open listings from motivated Sellers, Buyers, Landlords, and Tenants — and bid to represent them. No cold calling required.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-eye text-primary me-2"></i>Transparent Negotiation</h5>
                    <p class="text-muted small mt-2 mb-0">Every agent bid is visible to the client. Compete honestly on commission, services, and expertise. Clients can accept, counter, or decline any bid — giving you a clear path to closing.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-dollar-sign text-primary me-2"></i>Referral Income</h5>
                    <p class="text-muted small mt-2 mb-0">Refer clients to other agents and earn a referral fee upon closing. Agents can also be listed specifically as referral agents on the platform — a new income stream with no extra legwork.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Agent Tools --}}
<section class="works-section">
    <div class="container">
        <h2 class="fw-bold mb-2">Tools Built for Agents</h2>
        <p class="text-muted mb-4">BidYourAgent gives you everything you need to win business and stay visible.</p>
        <div class="text-center mb-4">
            <img class="img-fluid w-75" src="{{ asset('assets/pictures/sellerWork/sellingOption.png') }}" alt="Selling option breakdown" />
        </div>

        <div class="row g-4 mt-2">
            <div class="col-md-4">
                <div class="why-card">
                    <h6 class="fw-bold"><i class="fa fa-qrcode text-primary me-2"></i>QR Code Widget</h6>
                    <p class="text-muted small mt-2 mb-0">Generate a personalized QR code linked to your Hire Me profile. Share it on business cards, flyers, yard signs, or anywhere clients might find you.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h6 class="fw-bold"><i class="fa fa-link text-primary me-2"></i>Hire Me Landing Page</h6>
                    <p class="text-muted small mt-2 mb-0">Your personalized Hire Me page showcases your services, reviews, and contact details. Clients can post a listing directly from your page, making it easy to convert referrals.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h6 class="fw-bold"><i class="fa fa-star text-primary me-2"></i>Match Score Visibility</h6>
                    <p class="text-muted small mt-2 mb-0">Keep your agent profile complete and up to date. A higher Match Score means your bids appear more prominently to clients whose listings align with your specialization.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- FAQ Accordion --}}
<section class="works-section" style="background:#f8f9fa;padding-top:40px;padding-bottom:50px;">
    <div class="container">
        <h2 class="fw-bold mb-1">Frequently Asked Questions</h2>
        <p class="text-muted mb-4">Agent-specific answers about bidding, fees, and the hiring process.</p>

        <div class="accordion" id="agentFAQ">
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af1" aria-expanded="true"><h2 class="mb-0">How do I register to bid on client listings?</h2></button>
                </div>
                <div id="af1" class="accordion-collapse collapse show" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">Create an account under the "Register" tab, select "Agent" as your user type, and accept the Terms and Conditions. Once registered, you can browse all open client listings and submit bids immediately.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af2"><h4 class="mb-0">What does it cost to use BidYourAgent as an agent?</h4></button>
                </div>
                <div id="af2" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">There is no upfront cost. BidYourAgent receives a referral fee from you at closing when a deal closes with a client you hired through the platform. You only pay when you earn.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af3"><h4 class="mb-0">What is a Buyer's Rebate and should I offer one?</h4></button>
                </div>
                <div id="af3" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">A Buyer's Rebate is a credit of up to 0.5% of your commission offered back to the Buyer at closing. Offering a rebate can make your bid more competitive and attractive to clients — it's a powerful differentiator when other agents do not offer one.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af4"><h4 class="mb-0">What is Match Score and how do I improve mine?</h4></button>
                </div>
                <div id="af4" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">Match Score is a ranking that reflects how well your specialization aligns with each client listing. Keep your agent profile complete — specializations, service areas, and credentials — to improve your match and appear more prominently to relevant clients.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af5"><h4 class="mb-0">Can I list a property on behalf of a Seller client?</h4></button>
                </div>
                <div id="af5" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">Yes. Agents can post a listing on behalf of their Seller, Buyer, Landlord, or Tenant clients. We encourage clients to have their agent manage the listing for the best results.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af6"><h4 class="mb-0">How does referral income work for agents?</h4></button>
                </div>
                <div id="af6" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">Agents can refer clients to other agents on the platform and earn a referral fee upon closing. You can also register specifically as a referral agent — connecting clients to the right agent and earning income without managing the transaction yourself.</div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
