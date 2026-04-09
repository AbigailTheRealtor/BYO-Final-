@extends('layouts.main')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/faq.css') }}" />
<style>
    .faq-hero {
        background: linear-gradient(135deg, #006e9f 0%, #049399 100%);
        color: #fff;
        padding: 50px 0;
        text-align: center;
    }
    .faq-hero h1 { font-weight: 700; font-size: 2rem; }
    .faq-hero p { opacity: .9; max-width: 540px; margin: 12px auto 24px; }
    .faq-section-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 40px 0 12px;
        color: #006e9f;
        text-transform: uppercase;
        letter-spacing: .04em;
        font-size: .85rem;
    }
    .accordion-button:not(.collapsed) {
        background-color: #f0f8ff;
        color: #006e9f;
    }
    .accordion-button:focus { box-shadow: none; }
    .accordion-item { border-radius: 8px !important; overflow: hidden; margin-bottom: 8px; }
</style>
@endpush
@section('content')

{{-- Hero --}}
<div class="faq-hero">
    <div class="container">
        <h1>Frequently Asked Questions</h1>
        <p>Find answers about how BidYourAgent works, who it's for, and how to get started. Still have questions? Reach us at <a href="mailto:admin@bidyouragent.com" class="text-white fw-semibold">admin@bidyouragent.com</a>.</p>
        <a href="{{ route('register') }}"><button class="btn" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 28px;border-radius:6px;">Get Started</button></a>
    </div>
</div>

<div class="container py-5">

    {{-- How It Works --}}
    <div class="faq-section-title">How It Works</div>
    <div class="accordion" id="faqHowItWorks">
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fh1" aria-expanded="true">
                    What is BidYourAgent?
                </button>
            </div>
            <div id="fh1" class="accordion-collapse collapse show" data-bs-parent="#faqHowItWorks">
                <div class="accordion-body text-muted">BidYourAgent is a competitive agent-hiring marketplace. Sellers, Buyers, Landlords, and Tenants post a listing for free, and licensed real estate agents submit bids to be hired. Clients compare commissions, services, and presentations — then hire the agent who best fits their needs. All real estate services are handled by the licensed professional you select.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fh2">
                    How does the hiring process work?
                </button>
            </div>
            <div id="fh2" class="accordion-collapse collapse" data-bs-parent="#faqHowItWorks">
                <div class="accordion-body text-muted">Post your listing for free — describe your property or buying criteria, your timeline, and the services you expect. Licensed agents then submit transparent bids that include their commission, marketing strategy, Buyer's Rebate offer, video presentation, and more. You compare bids side by side, accept, counter, or decline, and hire the best fit. With a traditional listing you can hire at any time; with an auction listing you select at the end of the auction period or whenever an agent meets your Hire Now Terms.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fh3">
                    Is BidYourAgent free for clients?
                </button>
            </div>
            <div id="fh3" class="accordion-collapse collapse" data-bs-parent="#faqHowItWorks">
                <div class="accordion-body text-muted">Yes. Posting a listing as a Seller, Buyer, Landlord, or Tenant is completely free. BidYourAgent receives a referral fee from the agent you hire at closing — no upfront cost to you.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fh4">
                    Can I invite a specific agent to bid?
                </button>
            </div>
            <div id="fh4" class="accordion-collapse collapse" data-bs-parent="#faqHowItWorks">
                <div class="accordion-body text-muted">Yes. When creating your listing, you can enter a preferred agent's contact information. We'll notify them directly so they can submit a bid. You can also share your listing's QR code or link with any agent you choose.</div>
            </div>
        </div>
    </div>

    {{-- Who It's For --}}
    <div class="faq-section-title">Who It's For</div>
    <div class="accordion" id="faqWhoItsFor">
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fw1">
                    Who can use BidYourAgent as a client?
                </button>
            </div>
            <div id="fw1" class="accordion-collapse collapse" data-bs-parent="#faqWhoItsFor">
                <div class="accordion-body text-muted">BidYourAgent is built for Sellers, Buyers, Landlords, and Tenants in the real estate market. If you need a real estate agent to represent you in a sale, purchase, or rental transaction and you don't yet have one, you can post a listing for free and let agents compete to earn your business.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fw2">
                    What property types are supported?
                </button>
            </div>
            <div id="fw2" class="accordion-collapse collapse" data-bs-parent="#faqWhoItsFor">
                <div class="accordion-body text-muted">For sale-side listings: Residential, Income, Commercial, Business Opportunity, and Vacant Land. For rental-side listings: Residential and Commercial. Agents specialize by property type, so your listing reaches the most relevant agents.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fw3">
                    Who can use BidYourAgent as an agent?
                </button>
            </div>
            <div id="fw3" class="accordion-collapse collapse" data-bs-parent="#faqWhoItsFor">
                <div class="accordion-body text-muted">Any licensed real estate agent can register and bid on open client listings. Agents compete by submitting detailed bids that include their commission, marketing strategy, services, and video presentation. There is no upfront cost — agents pay a referral fee at closing only when they win and close a deal.</div>
            </div>
        </div>
    </div>

    {{-- How Agents Bid --}}
    <div class="faq-section-title">How Agents Bid</div>
    <div class="accordion" id="faqAgentsBid">
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fab1">
                    What does an agent's bid include?
                </button>
            </div>
            <div id="fab1" class="accordion-collapse collapse" data-bs-parent="#faqAgentsBid">
                <div class="accordion-body text-muted">Each agent bid includes contact information, offered commission, offered Buyer's Rebate (if applicable), website, review link, social media profiles, "About Me" section, listing or buyer's agreement timeframe, why they should be hired, marketing strategy, services provided, a video listing or buyer's presentation, promotional materials, and a business card. For Seller listings, agents may also include a property value analysis.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fab2">
                    What is a Buyer's Rebate?
                </button>
            </div>
            <div id="fab2" class="accordion-collapse collapse" data-bs-parent="#faqAgentsBid">
                <div class="accordion-body text-muted">Agents can offer up to a 0.5% rebate of their commission back to the Buyer at closing. This credit can be applied toward the Buyer's closing costs or used to buy down their interest rate. For Sellers, hiring an agent who offers this rebate can make your property more attractive to potential buyers.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fab3">
                    Can I accept, counter, or decline any bid?
                </button>
            </div>
            <div id="fab3" class="accordion-collapse collapse" data-bs-parent="#faqAgentsBid">
                <div class="accordion-body text-muted">Yes. You are in full control. Accept, counter, or decline any agent bid at any time. You can also negotiate directly with agents through the platform before making a final hiring decision.</div>
            </div>
        </div>
    </div>

    {{-- Match Score --}}
    <div class="faq-section-title">Match Score</div>
    <div class="accordion" id="faqMatchScore">
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fms1">
                    What is Match Score?
                </button>
            </div>
            <div id="fms1" class="accordion-collapse collapse" data-bs-parent="#faqMatchScore">
                <div class="accordion-body text-muted">Match Score is BidYourAgent's ranking system that surfaces agents whose specialization best aligns with your listing. When you post a Residential sale listing, agents who specialize in Residential properties are ranked higher. The same applies to Income, Commercial, Business Opportunity, and rental listings. Match Score helps you find the most relevant, experienced agents for your specific needs.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fms2">
                    Does a higher Match Score mean a better agent?
                </button>
            </div>
            <div id="fms2" class="accordion-collapse collapse" data-bs-parent="#faqMatchScore">
                <div class="accordion-body text-muted">Match Score reflects specialization alignment, not overall quality. It's one signal among many. We encourage you to review each agent's full bid — their commission, experience, marketing strategy, and presentation — before making a hiring decision.</div>
            </div>
        </div>
    </div>

    {{-- Referrals --}}
    <div class="faq-section-title">Referrals</div>
    <div class="accordion" id="faqReferrals">
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fr1">
                    How does the referral system work for agents?
                </button>
            </div>
            <div id="fr1" class="accordion-collapse collapse" data-bs-parent="#faqReferrals">
                <div class="accordion-body text-muted">Agents can refer clients to other agents on the platform and earn a referral fee when the transaction closes. Agents can also register specifically as referral agents — connecting clients with the right representation and earning income without managing the full transaction themselves.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fr2">
                    Does BidYourAgent receive a referral fee?
                </button>
            </div>
            <div id="fr2" class="accordion-collapse collapse" data-bs-parent="#faqReferrals">
                <div class="accordion-body text-muted">Yes. BidYourAgent receives a referral fee from the hired agent at closing. This fee is paid by the agent — not the client. There is no upfront cost and no fee if the transaction does not close.</div>
            </div>
        </div>
    </div>

    {{-- Licensed Professionals --}}
    <div class="faq-section-title">Licensed Professionals</div>
    <div class="accordion" id="faqLicensed">
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fl1">
                    Do I still need a licensed agent for my transaction?
                </button>
            </div>
            <div id="fl1" class="accordion-collapse collapse" data-bs-parent="#faqLicensed">
                <div class="accordion-body text-muted">Yes. BidYourAgent is a hiring marketplace — all actual real estate services, including listing, showing, negotiating, and closing, are performed by the licensed real estate professional you hire through the platform. BidYourAgent does not provide real estate services and is not a licensed brokerage.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fl2">
                    Are all agents on the platform licensed?
                </button>
            </div>
            <div id="fl2" class="accordion-collapse collapse" data-bs-parent="#faqLicensed">
                <div class="accordion-body text-muted">Agents are required to be licensed real estate professionals to bid on client listings. We recommend reviewing each agent's credentials, profile, and reviews before making a hiring decision. If you have concerns about a specific agent, contact us at <a href="mailto:admin@bidyouragent.com">admin@bidyouragent.com</a>.</div>
            </div>
        </div>
    </div>

    <div class="text-center mt-5">
        <p class="text-muted">Have more questions? Email us at <a href="mailto:admin@bidyouragent.com">admin@bidyouragent.com</a></p>
        <a href="{{ route('register') }}" class="btn btn-primary px-4">Hire Agent</a>
    </div>
</div>
@endsection
