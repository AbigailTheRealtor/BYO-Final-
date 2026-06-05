@extends('layouts.main')
@php
    $user = auth()->user();
    $totalListings    = array_sum($listingCounts);
    $totalPendingBids = array_sum($pendingBidCounts);

    $roleConfig = [
        'tenant' => [
            'label'     => "Tenant's Agent",
            'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            'color'     => '#0d6efd',
            'listRoute' => 'tenant.agent.auctions.list',
            'createRoute' => 'hire.agent.auction',
            'createParams' => ['user_type' => 'tenant'],
            'bidsRoute' => 'agent-bids',
        ],
        'landlord' => [
            'label'     => "Landlord's Agent",
            'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>',
            'color'     => '#198754',
            'listRoute' => 'landlord.agent.auctions.list',
            'createRoute' => 'landlord.hire.agent.auction',
            'createParams' => [],
            'bidsRoute' => 'hire-landlord-agent-bids',
        ],
        'buyer' => [
            'label'     => "Buyer's Agent",
            'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
            'color'     => '#6f42c1',
            'listRoute' => 'buyer.agent.auctions.list',
            'createRoute' => 'buyer.add-auction',
            'createParams' => [],
            'bidsRoute' => 'hire-buyer-agent-bids',
        ],
        'seller' => [
            'label'     => "Seller's Agent",
            'icon'      => '<svg xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
            'color'     => '#dc3545',
            'listRoute' => 'hireSellerAgentHireAuctions',
            'createRoute' => 'sellerAgentHireAuction',
            'createParams' => [],
            'bidsRoute' => 'hire-seller-agent-bids',
        ],
    ];
@endphp
@section('content')
    <div class="mainDashboard">
        <div class="container">

            @include('layouts.partials.dashboard_user_section')

            <div class="dashboardContentDetails mt-3">
                <div class="card">
                    <div class="row">

                        @include('layouts.partials.sidenav')

                        <div class="rightCol col-sm-12 col-md-8 col-lg-8">
                            <div class="container mt-4 mb-5">

                                {{-- ═══════════════════════════════════════════════
                                     WELCOME HEADER
                                ═══════════════════════════════════════════════ --}}
                                <div class="mb-1">
                                    <h4 class="fw-bold mb-0">Welcome back, {{ $user->user_name }}!</h4>
                                    <p class="text-muted small mb-0">Your BidYourOffer command center.</p>
                                </div>
                                <hr class="mt-2 mb-4">

                                {{-- ═══════════════════════════════════════════════
                                     QUICK ACTIONS
                                ═══════════════════════════════════════════════ --}}
                                <div class="mb-4">
                                    <div class="small text-uppercase text-muted fw-bold mb-2" style="letter-spacing:.06em;font-size:.7rem;">Quick Actions</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @if($user->user_type === 'agent')
                                            <a href="{{ route('agent.hire-listings') }}" class="btn btn-primary btn-sm">My Hire Agent Listings</a>
                                            <a href="{{ route('myBids') }}" class="btn btn-outline-secondary btn-sm">My Bids</a>
                                            <a href="{{ route('agent.qr.settings') }}" class="btn btn-outline-secondary btn-sm">QR &amp; Hire Me</a>
                                        @else
                                            @php
                                                $primaryRole = $user->user_type;
                                                $cfg = $roleConfig[$primaryRole] ?? null;
                                            @endphp
                                            @if($cfg)
                                                <a href="{{ route($cfg['createRoute'], $cfg['createParams']) }}" class="btn btn-primary btn-sm">+ Create Hire Agent Listing</a>
                                            @endif
                                            <div class="dropdown">
                                                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    + Create Regular Listing
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="{{ route('offer.listing.seller') }}">Create Seller Listing</a></li>
                                                    <li><a class="dropdown-item" href="{{ route('offer.listing.buyer') }}">Create Buyer Listing</a></li>
                                                    <li><a class="dropdown-item" href="{{ route('offer.listing.landlord') }}">Create Landlord Listing</a></li>
                                                    <li><a class="dropdown-item" href="{{ route('offer.listing.tenant', ['user_type' => 'tenant']) }}">Create Tenant Listing</a></li>
                                                </ul>
                                            </div>
                                            <a href="{{ route('my.listings') }}" class="btn btn-outline-secondary btn-sm">My Listings</a>
                                            @if($user->user_type === 'tenant')
                                                <a href="{{ route('myBids', 'agent-bids') }}" class="btn btn-outline-secondary btn-sm">Bids on My Listings</a>
                                            @elseif($user->user_type === 'landlord')
                                                <a href="{{ route('myBids', 'hire-landlord-agent-bids') }}" class="btn btn-outline-secondary btn-sm">Bids on My Listings</a>
                                            @elseif($user->user_type === 'buyer')
                                                <a href="{{ route('myBids', 'hire-buyer-agent-bids') }}" class="btn btn-outline-secondary btn-sm">Bids on My Listings</a>
                                            @elseif($user->user_type === 'seller')
                                                <a href="{{ route('myBids', 'hire-seller-agent-bids') }}" class="btn btn-outline-secondary btn-sm">Bids on My Listings</a>
                                            @endif
                                        @endif
                                        <a href="{{ route('messages') }}" class="btn btn-outline-secondary btn-sm">Messages</a>
                                        <a href="{{ route('settings') }}" class="btn btn-outline-secondary btn-sm">Profile Settings</a>
                                    </div>
                                </div>

                                @if($user->user_type !== 'agent')

                                {{-- ═══════════════════════════════════════════════
                                     LISTINGS BY ROLE
                                ═══════════════════════════════════════════════ --}}
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">My Listings by Role</div>
                                        @if($totalListings > 0)
                                            <span class="badge bg-secondary">{{ $totalListings }} total</span>
                                        @endif
                                    </div>
                                    <div class="row g-3">
                                        @foreach($roleConfig as $roleKey => $roleCfg)
                                        @php $cnt = $listingCounts[$roleKey] ?? 0; @endphp
                                        <div class="col-6 col-md-3">
                                            <div class="card h-100 border-0 rounded-3 p-3" style="background:#f8f9fa;">
                                                <div class="d-flex align-items-center gap-2 mb-2" style="color:{{ $roleCfg['color'] }};">
                                                    {!! $roleCfg['icon'] !!}
                                                    <span class="small fw-semibold" style="font-size:.78rem;color:#444;">{{ $roleCfg['label'] }}</span>
                                                </div>
                                                <div class="fw-bold mb-2" style="font-size:1.6rem;color:{{ $cnt > 0 ? $roleCfg['color'] : '#adb5bd' }};">
                                                    {{ $cnt }}
                                                </div>
                                                <div class="mt-auto d-flex gap-1 flex-wrap">
                                                    @if($cnt > 0)
                                                        <a href="{{ route($roleCfg['listRoute']) }}"
                                                           class="btn btn-sm flex-fill text-white"
                                                           style="background:{{ $roleCfg['color'] }};font-size:.75rem;">
                                                            View All
                                                        </a>
                                                    @else
                                                        <span class="small text-muted" style="font-size:.75rem;">No listings yet</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- ═══════════════════════════════════════════════
                                     YOUR HIRED AGENT
                                ═══════════════════════════════════════════════ --}}
                                @if($acceptedSummaries->isNotEmpty())
                                @php
                                    $roleLabels = [
                                        'tenant'   => "Tenant's Agent",
                                        'landlord' => "Landlord's Agent",
                                        'buyer'    => "Buyer's Agent",
                                        'seller'   => "Seller's Agent",
                                    ];
                                @endphp
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">Your Hired Agent</div>
                                        @if($acceptedSummaries->count() > 1)
                                            <span class="badge bg-secondary" style="font-size:.68rem;">{{ $acceptedSummaries->count() }} accepted</span>
                                        @endif
                                    </div>
                                    <div class="d-flex flex-column gap-3">
                                        @foreach($acceptedSummaries as $summary)
                                        @php
                                            $sigStatus = $summary->getSignatureStatus();
                                            $statusStyle = match(true) {
                                                str_contains($sigStatus, 'Both')    => 'background:#198754;color:#fff;',
                                                str_contains($sigStatus, 'Creator') => 'background:#fd7e14;color:#fff;',
                                                str_contains($sigStatus, 'Agent')   => 'background:#ffc107;color:#333;',
                                                default                             => 'background:#6c757d;color:#fff;',
                                            };
                                            $agent   = $summary->agent;
                                            $listing = $summary->listingSnapshot;
                                            $roleLabel    = $roleLabels[$summary->listing_type] ?? ucfirst($summary->listing_type);
                                            $listingAddr  = $listing ? ($listing->address ?: ($listing->listing_id ?? ('Listing #'.$summary->listing_id))) : ('Listing #'.$summary->listing_id);
                                            $listingCode  = $listing->listing_id ?? null;
                                        @endphp
                                        <div class="card border-0 rounded-3 overflow-hidden" style="border-left:3px solid #049399 !important;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                                            <div class="card-body py-3 px-3">

                                                {{-- Top row: agent info + status badge --}}
                                                <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                                                    <div>
                                                        <div class="fw-bold mb-1" style="font-size:.95rem;color:#1a2333;">
                                                            {{ $agent->user_name ?? 'Agent' }}
                                                        </div>
                                                        @if($agent && $agent->brokerage)
                                                            <div class="text-muted small mb-1" style="font-size:.8rem;">{{ $agent->brokerage }}</div>
                                                        @endif
                                                        <div class="d-flex flex-wrap gap-3" style="font-size:.78rem;color:#666;">
                                                            @if($agent && $agent->email)
                                                                <span><i class="fa-solid fa-envelope me-1 opacity-50"></i>{{ $agent->email }}</span>
                                                            @endif
                                                            @if($agent && $agent->phone)
                                                                <span><i class="fa-solid fa-phone me-1 opacity-50"></i>{{ $agent->phone }}</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <span class="badge rounded-pill px-3 py-2" style="{{ $statusStyle }}font-size:.68rem;max-width:160px;white-space:normal;text-align:center;line-height:1.3;">{{ $sigStatus }}</span>
                                                </div>

                                                {{-- Bottom row: listing info + action buttons --}}
                                                <div class="mt-2 pt-2 border-top d-flex align-items-start justify-content-between gap-2 flex-wrap">
                                                    <div style="font-size:.78rem;color:#888;">
                                                        <span class="fw-semibold" style="color:#049399;">{{ $roleLabel }}</span>
                                                        &middot;
                                                        {{ $listingAddr }}
                                                        @if($listingCode && $listingCode !== $listingAddr)
                                                            <span class="ms-1" style="font-family:monospace;font-size:.7rem;color:#aaa;">({{ $listingCode }})</span>
                                                        @endif
                                                    </div>
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <a href="{{ route('accepted-bid-summary.view', $summary->id) }}"
                                                           class="btn btn-sm"
                                                           style="background:#049399;color:#fff;font-size:.75rem;white-space:nowrap;">
                                                            View Summary
                                                        </a>
                                                        <a href="{{ route('messages') }}"
                                                           class="btn btn-sm btn-outline-secondary"
                                                           style="font-size:.75rem;white-space:nowrap;">
                                                            Message Agent
                                                        </a>
                                                        @if($summary->summary_pdf_path)
                                                            <a href="{{ route('accepted-bid-summary.download-pdf', $summary->id) }}"
                                                               class="btn btn-sm btn-outline-secondary"
                                                               style="font-size:.75rem;white-space:nowrap;"
                                                               target="_blank">
                                                                Download PDF
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                {{-- ═══════════════════════════════════════════════
                                     NEEDS ATTENTION
                                ═══════════════════════════════════════════════ --}}
                                @php
                                    $hasAttentionItems = ($totalPendingBids > 0 || $unsignedSummariesCount > 0);
                                @endphp
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">Needs Attention</div>
                                        @if($hasAttentionItems)
                                            @php $attentionTotal = $totalPendingBids + $unsignedSummariesCount; @endphp
                                            <span class="badge bg-danger">{{ $attentionTotal }} item{{ $attentionTotal != 1 ? 's' : '' }}</span>
                                        @endif
                                    </div>

                                    @if($hasAttentionItems)
                                        <div class="card border-0 rounded-3 overflow-hidden" style="border-left:3px solid #dc3545 !important;">
                                            <ul class="list-group list-group-flush">

                                                {{-- Pending bids per role --}}
                                                @foreach($roleConfig as $roleKey => $roleCfg)
                                                    @php $rolePending = $pendingBidCounts[$roleKey] ?? 0; @endphp
                                                    @if($rolePending > 0)
                                                        <li class="list-group-item d-flex align-items-center justify-content-between gap-2 py-3">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span style="color:{{ $roleCfg['color'] }};">{!! $roleCfg['icon'] !!}</span>
                                                                <span class="small">
                                                                    <strong>{{ $rolePending }} bid{{ $rolePending != 1 ? 's' : '' }}</strong>
                                                                    awaiting your decision
                                                                    <span class="text-muted">({{ $roleCfg['label'] }})</span>
                                                                </span>
                                                            </div>
                                                            <a href="{{ route('myBids', $roleCfg['bidsRoute']) }}"
                                                               class="btn btn-sm flex-shrink-0 text-white"
                                                               style="background:{{ $roleCfg['color'] }};font-size:.75rem;white-space:nowrap;">
                                                                Review &rarr;
                                                            </a>
                                                        </li>
                                                    @endif
                                                @endforeach

                                                {{-- Unsigned accepted bid summaries --}}
                                                @if($unsignedSummariesCount > 0)
                                                    <li class="list-group-item d-flex align-items-center justify-content-between gap-2 py-3" style="background:#fff8f0;">
                                                        <div class="d-flex align-items-center gap-2">
                                                            <svg xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;color:#fd7e14;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                            <span class="small">
                                                                <strong>{{ $unsignedSummariesCount }} accepted deal{{ $unsignedSummariesCount != 1 ? 's' : '' }}</strong>
                                                                awaiting your signature
                                                            </span>
                                                        </div>
                                                        <a href="{{ route('my.listings') }}"
                                                           class="btn btn-sm flex-shrink-0"
                                                           style="background:#fd7e14;color:#fff;font-size:.75rem;white-space:nowrap;">
                                                            Open Listings &rarr;
                                                        </a>
                                                    </li>
                                                @endif

                                            </ul>
                                        </div>
                                    @else
                                        <div class="card border-0 bg-light rounded-3 p-4 text-center">
                                            <div class="text-muted small">
                                                <svg xmlns="http://www.w3.org/2000/svg" style="width:28px;height:28px;opacity:.35;" class="mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <p class="mb-0 fw-semibold">You're all caught up!</p>
                                                <p class="mb-0" style="font-size:.8rem;">No bids or summaries need your attention right now.</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- ═══════════════════════════════════════════════
                                     CONSUMER BETA: COMPATIBILITY REPORT LINKS
                                     Shown only to non-agents when the consumer
                                     beta flag is enabled and approved reports exist.
                                ═══════════════════════════════════════════════ --}}
                                @if($user->user_type !== 'agent' && !config('bya_compatibility.kill_switch', true) && (config('bya_consumer_beta.consumer_beta_enabled') || config('bya_compatibility.ga_enabled')) && isset($consumerBetaScores) && $consumerBetaScores->isNotEmpty())
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">Compatibility Insights</div>
                                        @if($consumerBetaScores->count() > 1)
                                            <span class="badge bg-secondary" style="font-size:.68rem;">{{ $consumerBetaScores->count() }} report{{ $consumerBetaScores->count() != 1 ? 's' : '' }}</span>
                                        @endif
                                    </div>
                                    <div class="d-flex flex-column gap-2">
                                        @foreach($consumerBetaScores as $cbScore)
                                        <div class="card border-0 rounded-3 overflow-hidden" style="border-left:3px solid #049399 !important;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                                            <div class="card-body py-3 px-3 d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                                <div style="font-size:.85rem;color:#555;">
                                                    <span class="fw-semibold" style="color:#1a2333;">Compatibility Report</span>
                                                    <span class="ms-2 text-muted" style="font-size:.78rem;">
                                                        {{ ucfirst($cbScore->demand_listing_type) }} Listing #{{ $cbScore->demand_listing_id }}
                                                    </span>
                                                </div>
                                                <a href="{{ route('bya.consumer.beta.compatibility-report', $cbScore->id) }}"
                                                   class="btn btn-sm"
                                                   style="background:#049399;color:#fff;font-size:.75rem;white-space:nowrap;">
                                                    View Report &rarr;
                                                </a>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                @else
                                {{-- ═══════════════════════════════════════════════
                                     AGENT: HIRE AGENT FRAMEWORK DASHBOARD
                                ═══════════════════════════════════════════════ --}}
                                @php
                                    $agentRoleConfig = [
                                        'tenant' => [
                                            'label'        => "Hire Tenant's Agent",
                                            'icon'         => '<i class="fa-solid fa-key" style="width:20px;height:20px;font-size:18px;"></i>',
                                            'color'        => '#0d6efd',
                                            'bidsRoute'    => 'tenant.biding.auctions.list',
                                            'createRoute'  => 'hire.agent.auction',
                                            'createParams' => ['user_type' => 'tenant'],
                                        ],
                                        'landlord' => [
                                            'label'        => "Hire Landlord's Agent",
                                            'icon'         => '<i class="fa-solid fa-building" style="width:20px;height:20px;font-size:18px;"></i>',
                                            'color'        => '#198754',
                                            'bidsRoute'    => 'landlord.biding.auctions.list',
                                            'createRoute'  => 'hire.agent.auction',
                                            'createParams' => ['user_type' => 'landlord'],
                                        ],
                                        'buyer' => [
                                            'label'        => "Hire Buyer's Agent",
                                            'icon'         => '<i class="fa-solid fa-search" style="width:20px;height:20px;font-size:18px;"></i>',
                                            'color'        => '#6f42c1',
                                            'bidsRoute'    => 'buyer.biding.auctions.list',
                                            'createRoute'  => 'hire.agent.auction',
                                            'createParams' => ['user_type' => 'buyer'],
                                        ],
                                        'seller' => [
                                            'label'        => "Hire Seller's Agent",
                                            'icon'         => '<i class="fa-solid fa-gavel" style="width:20px;height:20px;font-size:18px;"></i>',
                                            'color'        => '#dc3545',
                                            'bidsRoute'    => 'seller.biding.auctions.list',
                                            'createRoute'  => 'hire.agent.auction',
                                            'createParams' => ['user_type' => 'seller'],
                                        ],
                                    ];
                                    $agentBidCounts = [
                                        'tenant'   => \App\Models\TenantAgentAuctionBid::where('user_id', $user->id)->count(),
                                        'landlord' => \App\Models\LandlordAgentAuctionBid::where('user_id', $user->id)->count(),
                                        'buyer'    => \App\Models\BuyerAgentAuctionBid::where('user_id', $user->id)->count(),
                                        'seller'   => \App\Models\SellerAgentAuctionBid::where('user_id', $user->id)->count(),
                                    ];
                                    $totalAgentBids = array_sum($agentBidCounts);
                                @endphp

                                {{-- My Hire Agent Listings (role grid) --}}
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">My Hire Agent Listings</div>
                                        @if($totalListings > 0)
                                            <a href="{{ route('agent.hire-listings') }}" class="small text-muted" style="font-size:.75rem;text-decoration:none;color:#049399;">View All →</a>
                                        @endif
                                    </div>
                                    <div class="row g-3">
                                        @foreach($agentRoleConfig as $roleKey => $roleCfg)
                                        @php $cnt = $listingCounts[$roleKey] ?? 0; @endphp
                                        <div class="col-6 col-md-3">
                                            <div class="card h-100 border-0 rounded-3 p-3" style="background:#f8f9fa;">
                                                <div class="d-flex align-items-center gap-2 mb-2" style="color:{{ $roleCfg['color'] }};">
                                                    {!! $roleCfg['icon'] !!}
                                                    <span class="small fw-semibold" style="font-size:.75rem;color:#444;">{{ $roleCfg['label'] }}</span>
                                                </div>
                                                <div class="fw-bold mb-2" style="font-size:1.6rem;color:{{ $cnt > 0 ? $roleCfg['color'] : '#adb5bd' }};">
                                                    {{ $cnt }}
                                                </div>
                                                <div class="mt-auto d-flex gap-1 flex-wrap">
                                                    @if($cnt > 0)
                                                        <a href="{{ route('agent.hire-listings') }}"
                                                           class="btn btn-sm flex-fill text-white"
                                                           style="background:{{ $roleCfg['color'] }};font-size:.75rem;">
                                                            View
                                                        </a>
                                                    @else
                                                        <a href="{{ route($roleCfg['createRoute'], $roleCfg['createParams']) }}"
                                                           class="btn btn-sm flex-fill"
                                                           style="background:#f0f0f0;color:#888;font-size:.75rem;border:1px dashed #ccc;">
                                                            + Create
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- My Active Bids (role grid) --}}
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">My Active Bids</div>
                                        @if($totalAgentBids > 0)
                                            <span class="badge bg-secondary" style="font-size:.68rem;">{{ $totalAgentBids }} total</span>
                                        @endif
                                    </div>
                                    <div class="row g-3">
                                        @foreach($agentRoleConfig as $roleKey => $roleCfg)
                                        @php $bidCnt = $agentBidCounts[$roleKey] ?? 0; @endphp
                                        <div class="col-6 col-md-3">
                                            <div class="card h-100 border-0 rounded-3 p-3" style="background:#f8f9fa;">
                                                <div class="d-flex align-items-center gap-2 mb-2" style="color:{{ $roleCfg['color'] }};">
                                                    {!! $roleCfg['icon'] !!}
                                                    <span class="small fw-semibold" style="font-size:.75rem;color:#444;">{{ $roleCfg['label'] }}</span>
                                                </div>
                                                <div class="fw-bold mb-2" style="font-size:1.6rem;color:{{ $bidCnt > 0 ? $roleCfg['color'] : '#adb5bd' }};">
                                                    {{ $bidCnt }}
                                                </div>
                                                <div class="mt-auto d-flex gap-1 flex-wrap">
                                                    @if($bidCnt > 0)
                                                        <a href="{{ route($roleCfg['bidsRoute']) }}"
                                                           class="btn btn-sm flex-fill text-white"
                                                           style="background:{{ $roleCfg['color'] }};font-size:.75rem;">
                                                            View Bids
                                                        </a>
                                                    @else
                                                        <span class="small text-muted" style="font-size:.75rem;">No bids yet</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- ═══════════════════════════════════════════════
                                     HIRE AGENT LEADS  (agent only)
                                ═══════════════════════════════════════════════ --}}
                                @if(isset($hireAgentLeadSummary))
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">
                                            Hire Agent Leads
                                        </div>
                                        <a href="{{ route('agent.hire-leads.index') }}"
                                           class="small" style="font-size:.75rem;text-decoration:none;color:#049399;">
                                            View All →
                                        </a>
                                    </div>

                                    {{-- Summary counts --}}
                                    <div class="row g-2 mb-3">
                                        @foreach([
                                            ['label'=>'New',      'key'=>'new',      'color'=>'#2563eb', 'bg'=>'#eff6ff'],
                                            ['label'=>'Pending',  'key'=>'pending',  'color'=>'#d97706', 'bg'=>'#fffbeb'],
                                            ['label'=>'Accepted', 'key'=>'accepted', 'color'=>'#059669', 'bg'=>'#f0fdf4'],
                                            ['label'=>'Declined', 'key'=>'declined', 'color'=>'#94a3b8', 'bg'=>'#f8fafc'],
                                        ] as $_lc)
                                        <div class="col-3">
                                            <a href="{{ route('agent.hire-leads.index', ['status' => $_lc['key']]) }}"
                                               class="text-decoration-none d-block rounded-3 p-2 text-center"
                                               style="background:{{ $_lc['bg'] }};">
                                                <div style="font-size:1.35rem;font-weight:800;color:{{ $_lc['color'] }};">
                                                    {{ $hireAgentLeadSummary[$_lc['key']] ?? 0 }}
                                                </div>
                                                <div style="font-size:.67rem;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">
                                                    {{ $_lc['label'] }}
                                                </div>
                                            </a>
                                        </div>
                                        @endforeach
                                    </div>

                                    {{-- Recent leads --}}
                                    @if($hireAgentLeadSummary['recent']->isNotEmpty())
                                    <div class="d-flex flex-column gap-2">
                                        @foreach($hireAgentLeadSummary['recent'] as $_lead)
                                        <a href="{{ route('agent.hire-leads.show', $_lead->id) }}"
                                           class="text-decoration-none">
                                            <div class="card border-0 rounded-3 overflow-hidden"
                                                 style="border-left:3px solid {{ $_lead->status === 'new' ? '#2563eb' : ($_lead->status === 'accepted' ? '#059669' : '#d97706') }} !important;box-shadow:0 1px 4px rgba(0,0,0,.06);transition:box-shadow .15s;"
                                                 onmouseover="this.style.boxShadow='0 2px 10px rgba(0,0,0,.11)'"
                                                 onmouseout="this.style.boxShadow='0 1px 4px rgba(0,0,0,.06)'">
                                                <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between gap-2">
                                                    <div>
                                                        <div class="fw-semibold" style="font-size:.88rem;color:#1e293b;">
                                                            {{ $_lead->requester_name ?? '' }}
                                                            @if($_lead->status === 'new')
                                                                <span class="badge bg-primary ms-1" style="font-size:.6rem;">New</span>
                                                            @endif
                                                        </div>
                                                        <div style="font-size:.75rem;color:#64748b;">
                                                            {{ $_lead->repTypeLabel() }} · {{ $_lead->propertyTypeLabel() }}
                                                        </div>
                                                    </div>
                                                    <div style="font-size:.7rem;color:#94a3b8;white-space:nowrap;">
                                                        {{ $_lead->created_at->diffForHumans() }}
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                        @endforeach
                                    </div>
                                    @else
                                    <div class="text-center py-3 text-muted" style="font-size:.82rem;">
                                        <i class="fa-solid fa-user-tie opacity-25 me-1"></i>No leads yet.
                                        Leads appear when visitors request an agent from a listing page.
                                    </div>
                                    @endif
                                </div>
                                @endif

                                {{-- ═══════════════════════════════════════════════
                                     REFERRAL PARTNER LINK  (agent only)
                                ═══════════════════════════════════════════════ --}}
                                @if($referralLink)
                                <div class="mb-4">
                                    <div class="small text-uppercase text-muted fw-bold mb-2" style="letter-spacing:.06em;font-size:.7rem;">Referral Partner Link</div>
                                    <div class="card border-0 rounded-3 overflow-hidden" style="border-left:3px solid #049399 !important;box-shadow:0 1px 4px rgba(0,0,0,.06);">
                                        <div class="card-body py-3 px-3">

                                            {{-- URL row --}}
                                            <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                                                <input id="referral-url-input"
                                                       type="text"
                                                       class="form-control form-control-sm"
                                                       value="{{ $referralLink['url'] }}"
                                                       readonly
                                                       style="font-size:.8rem;font-family:monospace;background:#f8f9fa;max-width:380px;cursor:text;">
                                                <button id="btn-copy-referral"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        style="font-size:.78rem;white-space:nowrap;"
                                                        data-url="{{ $referralLink['url'] }}"
                                                        title="Copy referral link to clipboard">
                                                    <i class="fa-solid fa-copy me-1"></i>Copy Link
                                                </button>
                                                <a href="{{ $referralLink['url'] }}"
                                                   target="_blank"
                                                   class="btn btn-sm btn-outline-secondary"
                                                   style="font-size:.78rem;white-space:nowrap;">
                                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Open Link
                                                </a>
                                            </div>

                                            {{-- Stats row --}}
                                            <div class="d-flex flex-wrap gap-3" style="font-size:.82rem;">
                                                <div class="d-flex flex-column align-items-center px-3 py-2 rounded-3" style="background:#f8f9fa;min-width:70px;">
                                                    <span class="fw-bold" style="font-size:1.3rem;color:#049399;">{{ number_format($referralLink['click_count']) }}</span>
                                                    <span class="text-muted" style="font-size:.72rem;">Clicks</span>
                                                </div>
                                                <div class="d-flex flex-column align-items-center px-3 py-2 rounded-3" style="background:#f8f9fa;min-width:70px;">
                                                    <span class="fw-bold" style="font-size:1.3rem;color:#049399;">{{ number_format($referralLink['signup_count']) }}</span>
                                                    <span class="text-muted" style="font-size:.72rem;">Signups</span>
                                                </div>
                                                <div class="d-flex flex-column align-items-center px-3 py-2 rounded-3" style="background:#f8f9fa;min-width:70px;">
                                                    <span class="fw-bold" style="font-size:1.3rem;color:#049399;">{{ number_format($referralLink['listing_count']) }}</span>
                                                    <span class="text-muted" style="font-size:.72rem;">Listings</span>
                                                </div>
                                                <div class="d-flex flex-column align-items-center px-3 py-2 rounded-3" style="background:#f8f9fa;min-width:70px;">
                                                    <span class="fw-bold" style="font-size:1.3rem;color:#049399;">{{ number_format($referralLink['hire_count']) }}</span>
                                                    <span class="text-muted" style="font-size:.72rem;">Hires</span>
                                                </div>
                                            </div>

                                            {{-- Est. Pending Earnings — only shown when admin has entered at least one amount --}}
                                            @php $pendingEarnings = $pendingReferralEarnings ?? null; @endphp
                                            @if($pendingEarnings !== null && $pendingEarnings > 0)
                                            <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-between" style="font-size:.82rem;">
                                                <span class="text-muted">
                                                    <i class="fa-solid fa-coins me-1 opacity-75"></i>
                                                    Est. Pending Earnings
                                                    <span class="text-muted" style="font-size:.72rem;">(admin estimate, not confirmed)</span>
                                                </span>
                                                <span class="fw-bold text-success" style="font-size:1rem;">${{ number_format($pendingEarnings, 2) }}</span>
                                            </div>
                                            @endif

                                        </div>
                                    </div>
                                </div>
                                @endif

                                {{-- ═══════════════════════════════════════════════
                                     RECENT REFERRAL ACTIVITY  (agent only)
                                ═══════════════════════════════════════════════ --}}
                                <div class="mb-4">
                                    <div class="small text-uppercase text-muted fw-bold mb-2" style="letter-spacing:.06em;font-size:.7rem;">Recent Referral Activity</div>
                                    @if($recentReferrals->isEmpty())
                                        <div class="card border-0 rounded-3 p-3 text-center" style="background:#f8f9fa;">
                                            <span class="text-muted small">No referral activity yet.</span>
                                        </div>
                                    @else
                                    <div class="card border-0 rounded-3 overflow-hidden" style="box-shadow:0 1px 4px rgba(0,0,0,.06);">
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0" style="font-size:.8rem;">
                                                <thead style="background:#f8f9fa;">
                                                    <tr>
                                                        <th class="px-3 py-2" style="font-weight:600;color:#666;">Date</th>
                                                        <th class="px-3 py-2" style="font-weight:600;color:#666;">Listing</th>
                                                        <th class="px-3 py-2" style="font-weight:600;color:#666;">Hired Agent</th>
                                                        <th class="px-3 py-2" style="font-weight:600;color:#666;">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @php
                                                        $referralStatusColors = [
                                                            'pending'   => '#856404',
                                                            'qualified' => '#055160',
                                                            'closed'    => '#0a3622',
                                                            'paid'      => '#084298',
                                                            'void'      => '#495057',
                                                        ];
                                                        $referralStatusBg = [
                                                            'pending'   => '#fff3cd',
                                                            'qualified' => '#cff4fc',
                                                            'closed'    => '#d1e7dd',
                                                            'paid'      => '#cfe2ff',
                                                            'void'      => '#e9ecef',
                                                        ];
                                                    @endphp
                                                    @foreach($recentReferrals as $ref)
                                                    @php
                                                        $refStatus = $ref->referral_status ?? null;
                                                        $badgeFg   = $referralStatusColors[$refStatus] ?? '#495057';
                                                        $badgeBg   = $referralStatusBg[$refStatus]     ?? '#e9ecef';
                                                    @endphp
                                                    <tr>
                                                        <td class="px-3 py-2 text-muted" style="white-space:nowrap;">
                                                            {{ $ref->created_at ? \Carbon\Carbon::parse($ref->created_at)->format('M j, Y') : '—' }}
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <span class="text-muted" style="font-size:.75rem;">ID {{ $ref->listing_id }}</span>
                                                            @if($ref->listing_type)
                                                                <span class="ms-1 text-muted" style="font-size:.72rem;">({{ ucfirst($ref->listing_type) }})</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            @if($ref->hired_agent_name)
                                                                <span>{{ $ref->hired_agent_name }}</span>
                                                            @else
                                                                <span class="text-muted">Agent #{{ $ref->agent_user_id }}</span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            @if($refStatus)
                                                                <span class="badge"
                                                                      style="background:{{ $badgeBg }};color:{{ $badgeFg }};font-size:.7rem;font-weight:600;">
                                                                    {{ ucfirst($refStatus) }}
                                                                </span>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                @endif

                                {{-- ═══════════════════════════════════════════════
                                     RECENT NOTICES
                                ═══════════════════════════════════════════════ --}}
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">Recent Notices</div>
                                </div>
                                <div class="notification">
                                    @if($notifications->isEmpty())
                                        <div class="card border-0 bg-light rounded-3 p-4 text-center">
                                            <div class="text-muted">
                                                <i class="fa-solid fa-bell-slash fa-2x mb-2 opacity-25"></i>
                                                <p class="mb-1 fw-semibold">No notices yet</p>
                                                <p class="small mb-0">When agents bid on your listings or activity occurs, you'll see updates here.</p>
                                            </div>
                                        </div>
                                    @else
                                        @foreach($notifications as $notification)
                                            @php
                                                $data      = $notification->data;
                                                $type      = $data['type'] ?? 'general';
                                                $message   = $data['message'] ?? 'You have a notification';
                                                $listingId = $data['listing_id'] ?? $data['auction_id'] ?? null;
                                            @endphp
                                            <div class="alert alert-info d-flex justify-content-between align-items-center mb-2" role="alert" id="notification-{{ $notification->id }}">
                                                <div>
                                                    @if($type === 'bid_accepted' || $type === 'counter_bid_accepted')
                                                        <i class="fa-solid fa-circle-check text-success me-2"></i>
                                                    @elseif($type === 'bid_countered' || $type === 'counter_bid_submitted')
                                                        <i class="fa-solid fa-right-left text-warning me-2"></i>
                                                    @elseif($type === 'bid_rejected')
                                                        <i class="fa-solid fa-circle-xmark text-danger me-2"></i>
                                                    @elseif($type === 'bid_submitted' || $type === 'bid_received')
                                                        <i class="fa-solid fa-gavel text-primary me-2"></i>
                                                    @else
                                                        <i class="fa-solid fa-bell me-2"></i>
                                                    @endif
                                                    <span>{{ $message }}</span>
                                                    @if($listingId)
                                                        <small class="text-muted ms-2">(Listing: {{ $listingId }})</small>
                                                    @endif
                                                    <small class="text-muted ms-2">{{ $notification->created_at->diffForHumans() }}</small>
                                                </div>
                                                <div class="d-flex gap-2 flex-shrink-0 ms-2">
                                                    <a href="{{ route('notifications.go', $notification->id) }}" class="btn btn-sm btn-primary">View</a>
                                                    <form action="{{ route('notifications.dismiss', $notification->id) }}" method="POST" class="d-inline" onsubmit="event.stopPropagation();">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Dismiss</button>
                                                    </form>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('btn-copy-referral');
    if (!btn) return;

    function fallbackCopy(text, onDone) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity  = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        onDone();
    }

    function showCopied() {
        var orig = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Copied!';
        btn.classList.add('text-success');
        setTimeout(function () {
            btn.innerHTML = orig;
            btn.classList.remove('text-success');
        }, 2000);
    }

    btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-url');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(showCopied).catch(function () {
                fallbackCopy(url, showCopied);
            });
        } else {
            fallbackCopy(url, showCopied);
        }
    });
})();
</script>
@endpush
