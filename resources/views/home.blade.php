@extends('layouts.master')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/home.css') }}" />
    <style>
        .bya-hero {
            background: linear-gradient(135deg, #006e9f 0%, #049399 100%);
            color: #fff;
            padding: 80px 0 60px;
            text-align: center;
        }
        .bya-hero h1 {
            font-size: 2.4rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .bya-hero p {
            font-size: 1.1rem;
            opacity: .9;
            max-width: 620px;
            margin: 16px auto 28px;
        }
        .bya-hero .btn-hero-primary {
            background: #fff;
            color: #006e9f;
            font-weight: 600;
            padding: 12px 30px;
            border-radius: 6px;
            margin: 0 8px 12px;
            border: none;
        }
        .bya-hero .btn-hero-primary:hover { opacity: .9; }
        .bya-hero .btn-hero-secondary {
            background: transparent;
            color: #fff;
            font-weight: 600;
            padding: 11px 30px;
            border-radius: 6px;
            margin: 0 8px 12px;
            border: 2px solid #fff;
        }
        .bya-hero .btn-hero-secondary:hover { background: rgba(255,255,255,.1); }

        /* How It Works */
        .bya-how {
            padding: 60px 0;
            background: #f8f9fa;
        }
        .bya-how .step-circle {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #006e9f;
            color: #fff;
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        /* Role value grid */
        .bya-roles {
            padding: 60px 0;
        }
        .bya-role-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 28px 22px;
            height: 100%;
            transition: box-shadow .2s;
        }
        .bya-role-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.1); }
        .bya-role-card .role-icon {
            font-size: 2rem;
            margin-bottom: 12px;
            color: #006e9f;
        }
        .bya-role-card h5 { font-weight: 700; }

        /* Agent benefits */
        .bya-agent-benefits {
            padding: 60px 0;
            background: #f8f9fa;
        }
        .bya-benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 20px;
        }
        .bya-benefit-item .bi-check {
            color: #049399;
            font-size: 1.4rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        /* Match score callout */
        .bya-match {
            padding: 60px 0;
            background: #006e9f;
            color: #fff;
        }
        .bya-match h2 { font-weight: 700; }

        /* Referral row */
        .bya-referral {
            padding: 60px 0;
        }
        .bya-referral-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 28px 22px;
            height: 100%;
        }
        .bya-referral-card h6 { font-weight: 700; color: #006e9f; text-transform: uppercase; letter-spacing: .04em; font-size: .8rem; }

        /* CTA banner */
        .bya-cta {
            padding: 60px 0;
            background: linear-gradient(135deg, #049399 0%, #006e9f 100%);
            color: #fff;
            text-align: center;
        }
        .bya-cta h2 { font-weight: 700; font-size: 2rem; }
        .bya-cta p { opacity: .9; font-size: 1.05rem; max-width: 540px; margin: 12px auto 28px; }
        .bya-cta .btn-cta {
            background: #fff;
            color: #006e9f;
            font-weight: 600;
            padding: 12px 36px;
            border-radius: 6px;
            border: none;
        }
        .bya-cta .btn-cta:hover { opacity: .9; }

        /* FAQ preview */
        .bya-faq-preview {
            padding: 60px 0;
            background: #f8f9fa;
        }

        /* Section heading utility */
        .bya-section-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .bya-section-sub {
            color: #6c757d;
            margin-bottom: 40px;
            font-size: 1rem;
        }
    </style>
@endpush

@section('content')

{{-- ===== HERO ===== --}}
<section class="bya-hero">
    <div class="container">
        <h1>Hire the Right Agent.<br>On Your Terms.</h1>
        <p>BidYourAgent lets Sellers, Buyers, Landlords, and Tenants post their needs — agents bid transparently to be hired. Compare commissions, services, and presentations before you decide.</p>
        <div>
            @if(auth()->check())
                @php $ut = auth()->user()->user_type; @endphp
                @if($ut === 'seller')
                    <a href="{{ route('sellerAgentHireAuction') }}"><button class="btn-hero-primary btn">Hire Agent</button></a>
                @elseif($ut === 'buyer')
                    <a href="{{ route('buyer.add-auction') }}"><button class="btn-hero-primary btn">Hire Agent</button></a>
                @elseif($ut === 'landlord')
                    <a href="{{ route('landlord.hire.agent.auction') }}"><button class="btn-hero-primary btn">Hire Agent</button></a>
                @elseif($ut === 'tenant')
                    <a href="{{ route('hire.agent.auction', ['user_type'=>'tenant']) }}"><button class="btn-hero-primary btn">Hire Agent</button></a>
                @else
                    <a href="{{ route('searchListing') }}"><button class="btn-hero-primary btn">Browse Listings</button></a>
                @endif
            @else
                <a href="{{ route('register') }}"><button class="btn-hero-primary btn">Get Started</button></a>
            @endif
            <a href="{{ route('sellerWorksAgent') }}"><button class="btn-hero-secondary btn">Join as Agent</button></a>
        </div>
    </div>
</section>

{{-- ===== HOW IT WORKS ===== --}}
<section class="bya-how">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="bya-section-title">How It Works</h2>
            <p class="bya-section-sub">Three simple steps to hire the right agent — or win more business.</p>
        </div>
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="step-circle">1</div>
                <h5 class="fw-bold">Post Your Listing</h5>
                <p class="text-muted">Share your property details or search criteria. It's free and takes minutes. Include your timeline, preferred services, and Hire Now terms.</p>
            </div>
            <div class="col-md-4">
                <div class="step-circle">2</div>
                <h5 class="fw-bold">Agents Compete</h5>
                <p class="text-muted">Licensed real estate agents submit detailed bids — commission, marketing strategy, video presentations, and more. You see every bid transparently.</p>
            </div>
            <div class="col-md-4">
                <div class="step-circle">3</div>
                <h5 class="fw-bold">Hire the Best Fit</h5>
                <p class="text-muted">Compare bids side by side, accept, counter, or decline. Hire the agent who best matches your needs — on your terms.</p>
            </div>
        </div>
    </div>
</section>

{{-- ===== ROLE-BASED VALUE GRID ===== --}}
<section class="bya-roles">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="bya-section-title">Who Is BidYourAgent For?</h2>
            <p class="bya-section-sub">Every role in a real estate transaction benefits from transparent agent hiring.</p>
        </div>
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3">
                <div class="bya-role-card">
                    <div class="role-icon"><i class="fa fa-home"></i></div>
                    <h5>Sellers</h5>
                    <p class="text-muted small">Post your property details and let agents bid to list it. Compare commissions, marketing strategies, and track record before you sign anything.</p>
                    <a href="{{ route('sellerWorks') }}" class="btn btn-sm btn-outline-primary mt-2">Learn More</a>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="bya-role-card">
                    <div class="role-icon"><i class="fa fa-search"></i></div>
                    <h5>Buyers</h5>
                    <p class="text-muted small">Define your buying criteria and receive agent bids. Hire the agent who offers the best rebate, services, and local expertise for your purchase.</p>
                    <a href="{{ route('buyerWorks') }}" class="btn btn-sm btn-outline-primary mt-2">Learn More</a>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="bya-role-card">
                    <div class="role-icon"><i class="fa fa-building"></i></div>
                    <h5>Landlords</h5>
                    <p class="text-muted small">Need to rent or manage a property? Post your rental listing and receive competitive bids from agents who specialize in property management.</p>
                    <a href="{{ route('sellerWorks') }}" class="btn btn-sm btn-outline-primary mt-2">Learn More</a>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="bya-role-card">
                    <div class="role-icon"><i class="fa fa-key"></i></div>
                    <h5>Tenants</h5>
                    <p class="text-muted small">Looking for a rental? Share your criteria and get agents competing to find you the right property — at no cost to you.</p>
                    <a href="{{ route('buyerWorks') }}" class="btn btn-sm btn-outline-primary mt-2">Learn More</a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== AGENT BENEFITS ===== --}}
<section class="bya-agent-benefits">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-md-6">
                <h2 class="bya-section-title">Agents: Win More Clients</h2>
                <p class="bya-section-sub mb-4">Showcase your expertise and let clients come to you.</p>
                <div class="bya-benefit-item">
                    <i class="fa fa-circle-check bi-check"></i>
                    <div><strong>Competitive Bidding.</strong> Submit your best commission, buyer's rebate, and services to win listings transparently.</div>
                </div>
                <div class="bya-benefit-item">
                    <i class="fa fa-circle-check bi-check"></i>
                    <div><strong>Match Score.</strong> Our platform matches your expertise — Residential, Income, Commercial, Business Opportunity, or Vacant Land — to the right listing.</div>
                </div>
                <div class="bya-benefit-item">
                    <i class="fa fa-circle-check bi-check"></i>
                    <div><strong>Hire Me Link & QR Code.</strong> Share a personal Hire Me landing page and QR code with clients anywhere.</div>
                </div>
                <div class="bya-benefit-item">
                    <i class="fa fa-circle-check bi-check"></i>
                    <div><strong>Referral Income.</strong> Earn referral fees by connecting other agents to opportunities on the platform.</div>
                </div>
                <div class="bya-benefit-item">
                    <i class="fa fa-circle-check bi-check"></i>
                    <div><strong>Video Presentations.</strong> Upload listing presentations, marketing materials, and business cards with every bid.</div>
                </div>
                <div class="mt-4">
                    <a href="{{ route('sellerWorksAgent') }}" class="btn btn-primary px-4">Join as Agent</a>
                </div>
            </div>
            <div class="col-md-6 text-center">
                <video class="w-100 rounded-3" src="{{ asset('assets/pictures/home/offerVideo.mp4') }}" controls
                    controlslist="nodownload" preload="none" poster="{{ asset('assets/pictures/home/offerPoster.jpg') }}" style="max-height:360px;object-fit:cover;"></video>
            </div>
        </div>
    </div>
</section>

{{-- ===== MATCH SCORE ===== --}}
<section class="bya-match">
    <div class="container text-center">
        <h2>Match Score: Find Your Best-Fit Agent</h2>
        <p class="mt-3 mb-4" style="max-width:640px;margin-left:auto;margin-right:auto;opacity:.9;">
            BidYourAgent's Match Score ranks agents by how well their specialization — Residential, Income, Commercial, Business Opportunity, or Vacant Land — aligns with your specific listing. You see every bid side by side, so you compare commissions, rebates, marketing strategies, and more. No guesswork, full information.
        </p>
        <div class="row g-4 mt-2">
            <div class="col-sm-4">
                <div class="bg-white bg-opacity-10 rounded-3 p-4">
                    <div style="font-size:2rem;font-weight:700;">100%</div>
                    <div style="opacity:.85;">Transparent Bids</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="bg-white bg-opacity-10 rounded-3 p-4">
                    <div style="font-size:2rem;font-weight:700;">Free</div>
                    <div style="opacity:.85;">for Clients to Post</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="bg-white bg-opacity-10 rounded-3 p-4">
                    <div style="font-size:2rem;font-weight:700;">Up to 0.5%</div>
                    <div style="opacity:.85;">Buyer Rebate Available</div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== AGENT TOOLS ===== --}}
<section class="bya-referral">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="bya-section-title">Agent Tools</h2>
            <p class="bya-section-sub">Tools that help agents grow their pipeline and win more clients.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="bya-referral-card">
                    <h6><i class="fa fa-qrcode me-2"></i>QR Code Widget</h6>
                    <p class="text-muted small mt-2">Generate a personalized QR code linked to your Hire Me profile. Share it on business cards, flyers, or anywhere clients might find you.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bya-referral-card">
                    <h6><i class="fa fa-link me-2"></i>Hire Me Landing Page</h6>
                    <p class="text-muted small mt-2">Your own Hire Me link showcases your services, reviews, and contact info. Clients can post a listing directly from your page.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bya-referral-card">
                    <h6><i class="fa fa-handshake me-2"></i>Referral Income</h6>
                    <p class="text-muted small mt-2">Refer clients to other agents and earn a referral fee upon closing. Agents can also be hired specifically as referral agents on the platform.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===== FINAL CTA ===== --}}
<section class="bya-cta">
    <div class="container">
        <h2>Ready to Hire the Right Agent?</h2>
        <p>Post your listing for free. Agents compete. You choose the best fit.</p>
        @if(!auth()->check())
            <a href="{{ route('register') }}"><button class="btn-cta btn">Get Started</button></a>
        @else
            <a href="{{ route('searchListing') }}"><button class="btn-cta btn">Browse Listings</button></a>
        @endif
    </div>
</section>

{{-- ===== FAQ PREVIEW ===== --}}
<section class="bya-faq-preview">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="bya-section-title">Common Questions</h2>
            <p class="bya-section-sub">Quick answers to help you get started.</p>
        </div>
        <div class="accordion" id="homeFaqAccordion">
            <div class="accordion-item border rounded-3 mb-2">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#hfaq1">
                        Is BidYourAgent free for clients?
                    </button>
                </div>
                <div id="hfaq1" class="accordion-collapse collapse" data-bs-parent="#homeFaqAccordion">
                    <div class="accordion-body text-muted">Yes. Posting a listing as a Seller, Buyer, Landlord, or Tenant is completely free. BidYourAgent receives a referral fee from the hired agent upon closing — no upfront cost to you.</div>
                </div>
            </div>
            <div class="accordion-item border rounded-3 mb-2">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#hfaq2">
                        What property types are supported?
                    </button>
                </div>
                <div id="hfaq2" class="accordion-collapse collapse" data-bs-parent="#homeFaqAccordion">
                    <div class="accordion-body text-muted">BidYourAgent supports Residential, Income, Commercial, Business Opportunity, and Vacant Land for sale-side listings, and Residential and Commercial for rental-side listings. Agents specialize by type, so your listing reaches the right agents.</div>
                </div>
            </div>
            <div class="accordion-item border rounded-3 mb-2">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#hfaq3">
                        What is a buyer's rebate?
                    </button>
                </div>
                <div id="hfaq3" class="accordion-collapse collapse" data-bs-parent="#homeFaqAccordion">
                    <div class="accordion-body text-muted">Agents can offer up to 0.5% of their commission back to the buyer at closing. This credit can be used toward closing costs or to buy down your interest rate. Sellers also benefit — offering a rebate makes your listing more attractive to buyers.</div>
                </div>
            </div>
            <div class="accordion-item border rounded-3 mb-2">
                <div class="accordion-header">
                    <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#hfaq4">
                        Can I invite a specific agent to bid?
                    </button>
                </div>
                <div id="hfaq4" class="accordion-collapse collapse" data-bs-parent="#homeFaqAccordion">
                    <div class="accordion-body text-muted">Absolutely. When creating your listing, you can enter a preferred agent's contact information. We'll notify them directly so they can submit a bid. You can also share your listing's QR code or link with any agent you choose.</div>
                </div>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="{{ route('faqs') }}" class="btn btn-outline-primary px-4">View All FAQs</a>
        </div>
    </div>
</section>

@endsection
