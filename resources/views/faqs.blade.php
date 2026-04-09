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
        <h1>We're here to help.</h1>
        <p>BidYourAgent provides a transparent approach to hiring real estate agents. Find answers below, or reach out at <a href="mailto:admin@bidyouragent.com" class="text-white fw-semibold">admin@bidyouragent.com</a>.</p>
        <a href="{{ route('register') }}"><button class="btn" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 28px;border-radius:6px;">Hire Agent — It's Free</button></a>
    </div>
</div>

<div class="container py-5">
    {{-- General --}}
    <div class="faq-section-title">General</div>
    <div class="accordion" id="faqGeneral">
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fq1" aria-expanded="true">
                    Who can use BidYourAgent?
                </button>
            </div>
            <div id="fq1" class="accordion-collapse collapse show" data-bs-parent="#faqGeneral">
                <div class="accordion-body text-muted">Any licensed Realtor, Seller, Buyer, Landlord, or Tenant in Florida. Sellers, Buyers, Landlords, and Tenants post listings for free. Real estate agents bid on those listings to be hired.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fq2">
                    Is BidYourAgent free for clients?
                </button>
            </div>
            <div id="fq2" class="accordion-collapse collapse" data-bs-parent="#faqGeneral">
                <div class="accordion-body text-muted">Yes. Posting a listing as a Seller, Buyer, Landlord, or Tenant is completely free. BidYourAgent receives a referral fee from the hired agent upon closing — no upfront cost to you.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fq3">
                    Can I use the services of a real estate agent to assist me as a Buyer?
                </button>
            </div>
            <div id="fq3" class="accordion-collapse collapse" data-bs-parent="#faqGeneral">
                <div class="accordion-body text-muted">Yes. Buyers can use the agent they choose. If a Buyer is not being represented and would like to be, we can refer a Buyer's agent to represent them at no cost to the Buyer.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fq4">
                    Can I use the services of a real estate agent to assist me as a Seller?
                </button>
            </div>
            <div id="fq4" class="accordion-collapse collapse" data-bs-parent="#faqGeneral">
                <div class="accordion-body text-muted">Yes. We suggest using a real estate agent to ensure a successful auction on our platform. Your property will be professionally marketed and priced by a seasoned agent — critical to the auction's success.</div>
            </div>
        </div>
    </div>

    {{-- Bidding --}}
    <div class="faq-section-title">Bidding & Registration</div>
    <div class="accordion" id="faqBidding">
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fb1">
                    How do I register to bid?
                </button>
            </div>
            <div id="fb1" class="accordion-collapse collapse" data-bs-parent="#faqBidding">
                <div class="accordion-body text-muted">Create an account under the "Register" tab and accept the Terms and Conditions. Buyers and Buyer's agents must also send proof of funds or a pre-approval letter and a valid ID to the Seller or Seller's agent before bidding, or their bid may be considered null and void.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fb2">
                    What do I need to provide to bid?
                </button>
            </div>
            <div id="fb2" class="accordion-collapse collapse" data-bs-parent="#faqBidding">
                <div class="accordion-body text-muted">Before bidding on a property, Buyers and Buyer's agents must send their proof of funds or pre-approval letter and a valid ID to the Seller or Seller's agent, or the bid may be considered null and void.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#fb3">
                    Do I need a sales contract before the auction?
                </button>
            </div>
            <div id="fb3" class="accordion-collapse collapse" data-bs-parent="#faqBidding">
                <div class="accordion-body text-muted">No. The Seller's Agent will provide the winning bid with an "AS IS" contract, all disclosures, and addendums. If the Seller is not represented by an agent, the Buyer's agent provides the documents. If no agents are involved, email admin@bidyouragent.com to request the documents. The Buyer must sign and return the contract within 48 hours or the winning offer may be considered null and void.</div>
            </div>
        </div>
    </div>

    {{-- Fees --}}
    <div class="faq-section-title">Fees & Premiums</div>
    <div class="accordion" id="faqFees">
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ff1">
                    What is a Buyer's Premium?
                </button>
            </div>
            <div id="ff1" class="accordion-collapse collapse" data-bs-parent="#faqFees">
                <div class="accordion-body text-muted">The Buyer's Premium is a charge to the Buyer in addition to the final sale price. It is added to the winning offer to arrive at the final contract price and goes to the Seller at closing. The amount varies per auction — check the Terms and Conditions of each listing.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ff2">
                    When is the Buyer's Premium due?
                </button>
            </div>
            <div id="ff2" class="accordion-collapse collapse" data-bs-parent="#faqFees">
                <div class="accordion-body text-muted">The fee is only collected at closing. If the real estate contract falls through, the Buyer must promptly sign the cancellation agreement and send it to the Seller or Seller's agent.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ff3">
                    How much does it cost to list on BidYourAgent?
                </button>
            </div>
            <div id="ff3" class="accordion-collapse collapse" data-bs-parent="#faqFees">
                <div class="accordion-body text-muted">Listing is completely free. At closing, BidYourAgent LLC collects a 1% success fee. The Seller determines who pays it. If the Seller is represented by an agent and the Seller or Buyer pay the 1% success fee, BidYourAgent splits 50% of the fee with the Seller's agent. There is no charge if the property does not close.</div>
            </div>
        </div>
        <div class="accordion-item">
            <div class="accordion-header">
                <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ff4">
                    How do I list a property on BidYourAgent?
                </button>
            </div>
            <div id="ff4" class="accordion-collapse collapse" data-bs-parent="#faqFees">
                <div class="accordion-body text-muted">Click "Hire Agent" on the homepage. You'll be asked to create an account and accept the Terms of Service. Sellers must sign an addendum agreeing to pay the 1% Success Fee at closing before listing. Sellers can add this fee to the auction and have the Buyer credit it at closing if they choose.</div>
            </div>
        </div>
    </div>

    <div class="text-center mt-5">
        <p class="text-muted">Have more questions? Email us at <a href="mailto:admin@bidyouragent.com">admin@bidyouragent.com</a></p>
        <a href="{{ route('register') }}" class="btn btn-primary px-4">Get Started — Hire Agent</a>
    </div>
</div>
@endsection
