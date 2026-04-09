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
                <p class="mt-3"><b>Welcome to BidYourAgent!</b> Our platform gives real estate agents a transparent, competitive way to win new listings from Sellers, Buyers, Landlords, and Tenants. List properties, bid to be hired, and earn referral income.</p>
                <a href="{{ route('search.agents') }}" class="btn mt-3" style="background:#fff;color:#006e9f;font-weight:600;padding:10px 24px;border-radius:6px;">Browse Agent Profiles</a>
            </div>
            <div class="col-md-6 text-center">
                <img class="img-fluid rounded-3 w-75" src="https://bidyouragent.com/wp-content/uploads/2022/08/iStock-93100462-scaled.jpg" alt="How BidYourAgent works for agents" />
            </div>
        </div>
    </div>
</div>

{{-- Auction Terms --}}
<section class="works-section">
    <div class="container">
        <h2 class="fw-bold mb-4">Auction Terms Agents Should Know</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="why-card">
                    <h6 class="fw-bold text-uppercase text-muted" style="font-size:.75rem;letter-spacing:.05em;">Listing Control</h6>
                    <ul class="list-unstyled mt-2 text-muted small mb-0">
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>The Seller sets all terms of the auction or traditional listing.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>Sellers can choose a traditional listing (no timer) or an auction with a time limit.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>Sellers can accept, reject, or counter any offer at any time.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>If the reserve amount is not met, the Seller can extend the auction time limit.</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="why-card">
                    <h6 class="fw-bold text-uppercase text-muted" style="font-size:.75rem;letter-spacing:.05em;">Premiums & Contracts</h6>
                    <ul class="list-unstyled mt-2 text-muted small mb-0">
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>Sellers can include a Buyer's Premium (paid by Buyer at closing) or a Seller's Premium (paid by Seller to Buyer).</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>The Seller determines who pays the 1% Success Fee.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>The Seller's Agent provides the winning bid an "AS IS" contract, disclosures, and addendums.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>The Buyer has 48 hours to sign and return the contract or the winning offer may be considered null and void.</li>
                        <li class="mb-2"><i class="fa fa-circle text-primary me-2" style="font-size:.4rem;vertical-align:middle;"></i>Additional queries should be sent to the Seller's Agent — each auction's terms are determined by the Seller.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Why Use BidYourAgent --}}
<section class="works-section" style="background:#f8f9fa;padding-top:40px;padding-bottom:50px;">
    <div class="container">
        <h2 class="fw-bold mb-2">Why Use BidYourAgent?</h2>
        <p class="text-muted mb-4">Trust and transparency are the top qualities Sellers and Buyers want in a real estate transaction. BidYourAgent delivers both.</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-eye text-primary me-2"></i>Full Transparency</h5>
                    <p class="text-muted small mt-2 mb-0">Sellers see all offers fairly and in a timely manner. Buyers know what other buyers are offering — no more guessing or leaving money on the table.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-trophy text-primary me-2"></i>Competitive Advantage</h5>
                    <p class="text-muted small mt-2 mb-0">Buyers put their best offer forward when they can see competing terms. More bids, better outcomes — for Sellers and agents alike.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="why-card">
                    <h5 class="fw-bold"><i class="fa fa-dollar text-primary me-2"></i>Agent Referral Income</h5>
                    <p class="text-muted small mt-2 mb-0">Agents earn 50% of the 1% Success Fee when the Buyer or Seller pays it. BidYourAgent enables agents to earn more on every transaction.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Paid to offer this service --}}
<section class="works-section">
    <div class="container">
        <h2 class="fw-bold mb-2">Get Paid to Offer This Service to Your Sellers</h2>
        <p class="text-muted mb-4">BidYourAgent pays 50% of its 1% Success Fee to the Seller's agent for a successful sale when the Buyer or Seller pays the Success Fee.</p>
        <div class="text-center mb-4">
            <img class="img-fluid w-75" src="{{ asset('assets/pictures/sellerWork/sellingOption.png') }}" alt="Selling option breakdown" />
        </div>

        <h2 class="fw-bold mb-2">Bonus Selling Option</h2>
        <p class="text-muted mb-4">Agents who include BidYourAgent as part of their listing package get a reduced 0.5% Success Fee (taken from total commission at closing). No additional fees for Sellers or Buyers — and you stand out from the competition.</p>
        <div class="text-center mb-4">
            <img class="img-fluid w-50" src="{{ asset('assets/pictures/sellerWork/additionalSelling.png') }}" alt="Additional selling option" />
        </div>
    </div>
</section>

{{-- FAQ Accordion --}}
<section class="works-section" style="background:#f8f9fa;padding-top:40px;padding-bottom:50px;">
    <div class="container">
        <h2 class="fw-bold mb-1">Frequently Asked Questions</h2>
        <p class="text-muted mb-4">Agent-specific answers about fees, premiums, and the listing process.</p>

        <div class="accordion" id="agentFAQ">
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af1" aria-expanded="true"><h2 class="mb-0">What is a Success Fee?</h2></button>
                </div>
                <div id="af1" class="accordion-collapse collapse show" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">The success fee is 1% of the purchase price paid to BidYourAgent LLC at closing for the success of the auction. The success fee will be added to the final sales price if paid by the Buyer, or deducted from the proceeds at closing if paid by the Seller. If listed by a Seller's agent and the Buyer or Seller pays the success fee, the Seller's agent gets half the success fee at closing. The Seller's agent can also opt to pay the success fee and receive a 50% discount. If the property contract is canceled, the Seller or Seller's agent must submit a cancellation contract to admin@bidyouragent.com. The Success fee is only charged when the sale closes.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af2"><h4 class="mb-0">What is a Buyer's Premium?</h4></button>
                </div>
                <div id="af2" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">The Buyer's Premium is a charge to the Buyer in addition to the final sales price. The premium is added to the winning offer to arrive at the final contract price paid by the Buyer. This additional fee is credited to the Seller at closing, reducing the Seller's closing expenses and enabling them to work with a full-service agent without paying full commission. The amount varies per auction and is determined by the Seller. If the property contract is canceled, the Seller or Seller's Agent must submit a cancellation contract to admin@bidyouragent.com.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af3"><h4 class="mb-0">What is a Seller's Premium?</h4></button>
                </div>
                <div id="af3" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">The Seller's Premium is a percentage amount off the purchase price that the Seller pays the Buyer at closing. It reduces the Buyer's closing costs, attracts more buyers to the property, and makes the listing stand out — especially effective in a buyer's market or for properties needing work.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af4"><h4 class="mb-0">When Are the Premiums and Success Fee Due?</h4></button>
                </div>
                <div id="af4" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">All fees are collected at the time of closing only. If the real estate contract falls through, the Seller's agent must promptly submit a release and cancellation agreement to admin@bidyouragent.com.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af5"><h4 class="mb-0">Can a Seller's Agent List on Behalf of a Seller?</h4></button>
                </div>
                <div id="af5" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">Yes — we encourage Sellers to have their agent list on their behalf for the best results.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af6"><h4 class="mb-0">How Do I List a Property?</h4></button>
                </div>
                <div id="af6" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">Click "Hire Agent" on the homepage. Create an account and accept the Terms of Service. Before listing, the Seller's agent must have the Seller sign an addendum agreeing to pay the 0.5% Success Fee to BidYourAgent LLC and 0.5% to the Seller's agent at closing. Sellers can add the 1% Success Fee to the auction and have the Buyer credit the Seller this fee at closing if they choose.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af7"><h4 class="mb-0">How Much Does It Cost to List on BidYourAgent?</h4></button>
                </div>
                <div id="af7" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">Listing is completely free. At closing, BidYourAgent LLC receives a 1% success fee. If listed by a Seller's agent, the agent receives half the success fee at closing for a successful auction. The Buyer can credit the 1% Success Fee to the Seller to pay the fee.</div>
                </div>
            </div>
            <div class="accordion-item">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#af8"><h4 class="mb-0">Do I Need a Sales Contract Ready Before the Auction?</h4></button>
                </div>
                <div id="af8" class="accordion-collapse collapse" data-bs-parent="#agentFAQ">
                    <div class="accordion-body text-muted">No. The Seller's Agent provides the winning bid with an "AS IS" contract, all disclosures, and addendums. If the Seller is not represented, the Buyer's agent provides the documents. If no agents are involved, email admin@bidyouragent.com to request the documents. Once the Buyer receives the contract, they have 48 hours to sign and submit to the Seller or Seller's agent, or the winning offer may be considered null and void.</div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
