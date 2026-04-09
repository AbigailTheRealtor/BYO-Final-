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
                <p class="mt-3"><b>Welcome to BidYourAgent!</b> Our platform gives buyer's agents a transparent, competitive way to win new clients from Buyers and Tenants looking for representation. Bid on open client listings and showcase your expertise.</p>
                <a href="{{ route('buyer.agent.searchListing') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Browse Buyer Listings</a>
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
            <div><strong>Find open Buyer listings.</strong> Browse listings posted by Buyers and Tenants who need a buyer's agent. Filter by property type, bedrooms, bathrooms, and search criteria to find the best matches for your expertise.</div>
        </div>
        <div class="step-item">
            <div class="step-num">2</div>
            <div><strong>Submit a competitive bid.</strong> Each bid you place includes your contact information, offered Buyer's Rebate (if applicable), website, review link, social media profiles, "About Me" section, buyer's agreement timeframe, why you should be hired, what sets you apart, marketing strategy, services you'll provide, a video buyer's presentation, promotional materials, and a business card.</div>
        </div>
        <div class="step-item">
            <div class="step-num">3</div>
            <div><strong>Offer a Buyer's Rebate.</strong> Differentiate your bid by offering up to a 0.5% rebate of your commission back to the Buyer at closing. This credit can be used toward the Buyer's closing costs or to buy down their interest rate — a compelling selling point.</div>
        </div>
        <div class="step-item">
            <div class="step-num">4</div>
            <div><strong>Get notified when invited.</strong> Buyers can enter your contact information when creating their listing to invite you directly. You'll be notified to submit a bid, giving you a head start over other competing agents.</div>
        </div>
        <div class="step-item">
            <div class="step-num">5</div>
            <div><strong>Auction vs. Traditional listing.</strong> Buyers can post traditional listings (you can be hired at any time) or auction listings (you must wait for the auction to end before the Buyer selects the winning agent). Both support Hire Now Terms. Check each listing to know which type you're bidding on.</div>
        </div>
        <div class="step-item">
            <div class="step-num">6</div>
            <div><strong>Earn a referral commission.</strong> BidYourAgent will receive a referral fee from you at closing in exchange for the client match. No upfront cost — you only pay when a deal closes. Check each listing for exact terms.</div>
        </div>
        <div class="step-item">
            <div class="step-num">7</div>
            <div><strong>Provide a free property value analysis.</strong> Buyers may request a free property value analysis as part of your bid. Include this as a value-add service to stand out from competing agents.</div>
        </div>
    </div>
</section>

{{-- Auction Terms --}}
<section class="works-section" style="background:#f8f9fa;padding-top:40px;padding-bottom:50px;">
    <div class="container">
        <h2 class="fw-bold mb-4">Auction Terms to Know</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="why-card">
                    <h6 class="fw-bold text-uppercase text-muted" style="font-size:.75rem;letter-spacing:.05em;">Listing Control</h6>
                    <ul class="list-unstyled mt-2 text-muted small mb-0">
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>The Seller/Buyer sets all terms of the auction or traditional listing.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>Buyers can choose a traditional listing (no timer) or a timed auction.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>Buyers maintain the right to accept, reject, or counter any bid at any time.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>If the reserve is not met, the Seller can extend the auction time limit.</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="why-card">
                    <h6 class="fw-bold text-uppercase text-muted" style="font-size:.75rem;letter-spacing:.05em;">Contracts & Fees</h6>
                    <ul class="list-unstyled mt-2 text-muted small mb-0">
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>Sellers may include a Buyer's Premium or Seller's Premium — check listing terms.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>The Seller determines who pays the 1% Success Fee — check listing notes.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>The Seller's Agent provides the winning bid an "AS IS" contract, disclosures, and addendums.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>Buyer's agents must send their client's proof of funds or pre-approval letter and a valid ID to the Seller/Seller's agent before bidding or the offer may be considered null and void.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>Once the contract is received, the Buyer has 48 hours to sign and submit or the winning offer may be null and void.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Why Use BidYourAgent --}}
<section class="works-section">
    <div class="container">
        <h2 class="fw-bold mb-2">Why Use BidYourAgent?</h2>
        <p class="text-muted mb-4">Trust and transparency are the #1 qualities Sellers and Buyers want in a real estate transaction. BidYourAgent delivers both — and rewards agents who compete on merit.</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-eye text-primary me-2"></i>Full Transparency</h5>
                    <p class="text-muted small mt-2 mb-0">Buyers see all bids openly. Agents put forward their best offer, commission, rebate, and services — no guessing, no hidden negotiations.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-handshake-o text-primary me-2"></i>Qualify More Clients</h5>
                    <p class="text-muted small mt-2 mb-0">Every listing comes with qualified Buyer intent. Buyers post their criteria, timeline, and financing status upfront — so you know who you're bidding for before you submit.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-dollar text-primary me-2"></i>No Upfront Cost</h5>
                    <p class="text-muted small mt-2 mb-0">BidYourAgent receives a referral fee from the hired agent at closing. No cost to you until the deal closes — a risk-free way to grow your pipeline.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Image --}}
<section class="works-section" style="background:#f8f9fa;padding-top:30px;padding-bottom:40px;">
    <div class="container text-center">
        <img class="img-fluid w-75" src="{{ asset('assets/pictures/sellerWork/sellingOption.png') }}" alt="Selling option breakdown" />
    </div>
</section>

{{-- FAQ Accordion --}}
<section class="works-section">
    <div class="container">
        <h2 class="fw-bold mb-1">Frequently Asked Questions</h2>
        <p class="text-muted mb-4">Buyer's agent-specific answers about bidding, fees, and the process.</p>

        <div class="accordion" id="buyerAgentFAQ">
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba1" aria-expanded="true"><h4 class="mb-0">Will a Buyer's Agent Get Paid a Commission?</h4></button>
                </div>
                <div id="ba1" class="accordion-collapse collapse show" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">Yes. The Buyer's agent commission is noted in the listing. Review the listing details to understand the commission structure before placing a bid.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba2"><h4 class="mb-0">How Do I Register to Bid?</h4></button>
                </div>
                <div id="ba2" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">Create an account under the "Register" tab and accept the Terms and Conditions. Before bidding, Buyer's agents must send their client's proof of funds or pre-approval letter and a valid ID to the Seller or Seller's agent, or the bid may be considered null and void.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba3"><h4 class="mb-0">What Do I Need to Provide to Bid?</h4></button>
                </div>
                <div id="ba3" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">Before bidding, Buyer's agents must send their client's proof of funds or pre-approval letter and a valid ID to the Seller or Seller's agent, or the bid may be considered null and void. Contact the Selling agent or Seller if you have any additional questions.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba4"><h4 class="mb-0">Do I Need a Sales Contract Before the Auction?</h4></button>
                </div>
                <div id="ba4" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">No. The Seller's Agent will provide the winning bid with an "AS IS" contract, all disclosures, and addendums. If no Seller's agent is involved, the Buyer's agent provides the documents. If no agents are involved, email admin@bidyouragent.com. The Buyer has 48 hours to sign and submit or the winning offer may be null and void.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba5"><h4 class="mb-0">What is a Success Fee?</h4></button>
                </div>
                <div id="ba5" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">The success fee is 1% of the purchase price paid to BidYourAgent LLC at closing. The Seller determines who pays it — check the listing notes before bidding. The fee is only charged when the sale closes.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba6"><h4 class="mb-0">What is a Buyer's Premium?</h4></button>
                </div>
                <div id="ba6" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">The Buyer's Premium is a charge to the Buyer in addition to the final sales price. The premium is added to the winning offer to arrive at the final contract price, credited to the Seller at closing. The amount varies per auction — check the Terms and Conditions of each listing.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba7"><h4 class="mb-0">What is a Seller's Premium?</h4></button>
                </div>
                <div id="ba7" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">The Seller's Premium is a percentage of the purchase price the Seller pays to the Buyer at closing, reducing the Buyer's closing costs. This premium is set by the Seller and is shown in the listing details.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#ba8"><h4 class="mb-0">When Are the Buyer's Premium and Success Fee Due?</h4></button>
                </div>
                <div id="ba8" class="accordion-collapse collapse" data-bs-parent="#buyerAgentFAQ">
                    <div class="accordion-body text-muted">All fees are collected at the time of closing only. If the real estate contract falls through, the Buyer must promptly submit a release and cancellation agreement to the Seller or Seller's agent.</div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
