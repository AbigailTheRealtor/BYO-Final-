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
                                <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-2">
                                    <div>
                                        <h4 class="fw-bold mb-0">Welcome back, {{ $user->user_name }}!</h4>
                                        <p class="text-muted small mb-0">Your BidYourOffer command center.</p>
                                    </div>
                                </div>
                                <hr class="mt-2 mb-4">

                                {{-- ═══════════════════════════════════════════════
                                     QUICK ACTIONS
                                ═══════════════════════════════════════════════ --}}
                                <div class="mb-4">
                                    <div class="small text-uppercase text-muted fw-bold mb-2" style="letter-spacing:.06em;font-size:.7rem;">Quick Actions</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @if($user->user_type === 'agent')
                                            <a href="{{ route('myAuctions') }}" class="btn btn-primary btn-sm">My Property Listings</a>
                                            <a href="{{ route('myBids') }}" class="btn btn-outline-secondary btn-sm">My Bids</a>
                                            <a href="{{ route('agent.qr.settings') }}" class="btn btn-outline-secondary btn-sm">QR &amp; Hire Me</a>
                                        @else
                                            @php
                                                $primaryRole = $user->user_type;
                                                $cfg = $roleConfig[$primaryRole] ?? null;
                                            @endphp
                                            @if($cfg)
                                                <a href="{{ route($cfg['createRoute'], $cfg['createParams']) }}" class="btn btn-primary btn-sm">Create New Request</a>
                                                <a href="{{ route($cfg['listRoute']) }}" class="btn btn-outline-secondary btn-sm">View My Agent Request</a>
                                            @endif
                                        @endif
                                        <a href="{{ route('messages') }}" class="btn btn-outline-secondary btn-sm">Messages</a>
                                        <a href="{{ route('settings') }}" class="btn btn-outline-secondary btn-sm">Profile Settings</a>
                                    </div>
                                </div>

                                @if($user->user_type !== 'agent')
                                @php
                                    $listingCount = count($allListings);
                                    $statusColors = [
                                        'Active'      => '#198754',
                                        'Draft'       => '#6c757d',
                                        'Hired Agent' => '#0d6efd',
                                        'Expired'     => '#dc3545',
                                        'Pending'     => '#fd7e14',
                                    ];
                                @endphp

                                {{-- ═══════════════════════════════════════════════
                                     YOUR AGENT REQUEST(S)
                                ═══════════════════════════════════════════════ --}}
                                <div class="mb-4">
                                    <div class="small text-uppercase text-muted fw-bold mb-2" style="letter-spacing:.06em;font-size:.7rem;">
                                        {{ $listingCount > 1 ? 'Your Agent Requests' : 'Your Active Agent Request' }}
                                    </div>

                                    @if($listingCount === 0)
                                        {{-- Empty state --}}
                                        <div class="card border-0 bg-light rounded-3 p-4 text-center">
                                            <div class="text-muted">
                                                <svg xmlns="http://www.w3.org/2000/svg" style="width:32px;height:32px;opacity:.3;" class="mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                </svg>
                                                <p class="mb-1 fw-semibold">You have not created an agent request yet.</p>
                                                <p class="small mb-3">Post your first request to start receiving agent bids.</p>
                                                @php $cfg = $roleConfig[$user->user_type] ?? null; @endphp
                                                @if($cfg)
                                                    <a href="{{ route($cfg['createRoute'], $cfg['createParams']) }}" class="btn btn-primary btn-sm">+ Create New Request</a>
                                                @endif
                                            </div>
                                        </div>

                                    @else
                                        {{-- One or more requests: show every one --}}
                                        <div class="d-flex flex-column gap-3">
                                        @foreach($allListings as $aItem)
                                            @php
                                                $aListing  = $aItem['listing'];
                                                $aRole     = $aItem['role'];
                                                $aRoleCfg  = $roleConfig[$aRole];
                                                $aBidCount = $aItem['bidCount'];
                                                $aPending  = $pendingBidCounts[$aRole] ?? 0;
                                                $aStatus   = $aListing->is_draft ? 'Draft' : $aListing->status;
                                                $aBidsUrl  = match($aRole) {
                                                    'landlord' => route('myBids', ['type' => 'hire-landlord-agent-bids']),
                                                    'buyer'    => route('myBids', ['type' => 'hire-buyer-agent-bids']),
                                                    'seller'   => route('myBids', ['type' => 'hire-seller-agent-bids']),
                                                    'tenant'   => route('myBids', ['type' => 'agent-bids']),
                                                    default    => route($aRoleCfg['listRoute']),
                                                };
                                                $statusColor = $statusColors[$aStatus] ?? '#6c757d';
                                            @endphp
                                            <div class="card border-0 rounded-3 overflow-hidden" style="border-left:4px solid {{ $aRoleCfg['color'] }} !important;box-shadow:0 1px 4px rgba(0,0,0,.07);">
                                                <div class="card-body p-4">
                                                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                                        <div style="min-width:0;">
                                                            <div class="d-flex align-items-center gap-2 mb-1" style="color:{{ $aRoleCfg['color'] }};">
                                                                {!! $aRoleCfg['icon'] !!}
                                                                <span class="small fw-semibold" style="font-size:.78rem;color:#666;">Hiring a {{ $aRoleCfg['label'] }}</span>
                                                            </div>
                                                            <div class="fw-bold text-truncate" style="font-size:1.05rem;color:#1a3a5c;max-width:340px;">{{ $aListing->title }}</div>
                                                        </div>
                                                        <span class="badge flex-shrink-0" style="background:{{ $statusColor }};color:#fff;font-size:.75rem;">{{ $aStatus }}</span>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-3 mb-4 small">
                                                        <span class="text-muted">ID: <span class="text-dark fw-semibold">#{{ $aListing->id }}</span></span>
                                                        @if($aListing->created_at)
                                                            <span class="text-muted">Created: <span class="text-dark fw-semibold">{{ $aListing->created_at->format('M j, Y') }}</span></span>
                                                        @endif
                                                        <span class="text-muted">Bids: <span class="text-dark fw-semibold">{{ $aBidCount }}</span></span>
                                                        @if($aBidCount === 0)
                                                            <span class="text-muted fst-italic">No bids yet.</span>
                                                        @endif
                                                        @if($aPending > 0)
                                                            <span class="fw-semibold" style="color:#dc3545;">
                                                                {{ $aPending }} bid{{ $aPending != 1 ? 's' : '' }} awaiting your review
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <a href="{{ route($aRoleCfg['listRoute']) }}"
                                                           class="btn btn-sm text-white"
                                                           style="background:{{ $aRoleCfg['color'] }};border:none;">Open Request</a>
                                                        <a href="{{ $aBidsUrl }}"
                                                           class="btn btn-sm btn-outline-secondary">View Bids</a>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                        </div>
                                    @endif
                                </div>

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
                                                            <a href="{{ route($roleCfg['listRoute']) }}"
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
                                                        <a href="{{ route($roleConfig[$user->user_type]['listRoute'] ?? 'tenant.agent.auctions.list') }}"
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

                                @else
                                {{-- ── Agent quick-summary (no listing-by-role grid needed) ── --}}
                                <div class="mb-4">
                                    <div class="card border-0 bg-light rounded-3 p-4 text-center text-muted small">
                                        Use <strong>My Property Listings</strong> and <strong>My Bids</strong> above to manage your agent activity.
                                    </div>
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
                                                <i class="fa fa-bell-slash fa-2x mb-2 opacity-25"></i>
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
                                                        <i class="fas fa-check-circle text-success me-2"></i>
                                                    @elseif($type === 'bid_countered' || $type === 'counter_bid_submitted')
                                                        <i class="fas fa-exchange-alt text-warning me-2"></i>
                                                    @elseif($type === 'bid_rejected')
                                                        <i class="fas fa-times-circle text-danger me-2"></i>
                                                    @elseif($type === 'bid_submitted' || $type === 'bid_received')
                                                        <i class="fas fa-gavel text-primary me-2"></i>
                                                    @else
                                                        <i class="fas fa-bell me-2"></i>
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
