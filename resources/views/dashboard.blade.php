@extends('layouts.main')
@php
    $user = auth()->user();
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

                                {{-- Welcome / Account Summary --}}
                                <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-2">
                                    <div>
                                        <h4 class="fw-bold mb-0">Welcome back, {{ $user->user_name }}!</h4>
                                        <p class="text-muted small mb-0">Here's an overview of your BidYourAgent account.</p>
                                    </div>
                                    <span class="badge text-bg-secondary text-capitalize">{{ str_replace('_', ' ', $user->user_type) }}</span>
                                </div>
                                <hr class="mt-2 mb-3">

                                {{-- Account Info Cards --}}
                                <div class="row g-3 mb-4">
                                    <div class="col-sm-6 col-md-4">
                                        <div class="card border-0 bg-light h-100 p-3">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span class="small text-muted fw-semibold text-uppercase" style="letter-spacing:.05em;font-size:.7rem;">Username</span>
                                            </div>
                                            <div class="fw-bold text-truncate">{{ $user->user_name }}</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <div class="card border-0 bg-light h-100 p-3">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                                </svg>
                                                <span class="small text-muted fw-semibold text-uppercase" style="letter-spacing:.05em;font-size:.7rem;">Email</span>
                                            </div>
                                            <div class="fw-bold text-truncate">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-4">
                                        <div class="card border-0 bg-light h-100 p-3">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                                <span class="small text-muted fw-semibold text-uppercase" style="letter-spacing:.05em;font-size:.7rem;">Location</span>
                                            </div>
                                            <div class="fw-bold">{{ $user->city ?? $user->state ?? '—' }}</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Quick Actions --}}
                                <div class="mb-4">
                                    <div class="small text-uppercase text-muted fw-bold mb-2" style="letter-spacing:.06em;font-size:.7rem;">Quick Actions</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @if(in_array($user->user_type, ['seller']))
                                            <a href="{{ route('sellerAgentHireAuction') }}" class="btn btn-primary btn-sm">+ Hire Agent</a>
                                            <a href="{{ route('hireSellerAgentHireAuctions') }}" class="btn btn-outline-secondary btn-sm">My Listings</a>
                                            <a href="{{ route('myBids') }}" class="btn btn-outline-secondary btn-sm">My Bids</a>
                                        @elseif(in_array($user->user_type, ['buyer']))
                                            <a href="{{ route('buyer.add-auction') }}" class="btn btn-primary btn-sm">+ Hire Agent</a>
                                            <a href="{{ route('buyer.agent.auctions') }}" class="btn btn-outline-secondary btn-sm">My Listings</a>
                                            <a href="{{ route('myBids') }}" class="btn btn-outline-secondary btn-sm">My Bids</a>
                                        @elseif(in_array($user->user_type, ['landlord']))
                                            <a href="{{ route('landlord.hire.agent.auction') }}" class="btn btn-primary btn-sm">+ Hire Agent</a>
                                            <a href="{{ route('landlord.agent.auctions.list') }}" class="btn btn-outline-secondary btn-sm">My Listings</a>
                                            <a href="{{ route('myBids') }}" class="btn btn-outline-secondary btn-sm">My Bids</a>
                                        @elseif(in_array($user->user_type, ['tenant']))
                                            <a href="{{ route('hire.agent.auction', ['user_type'=>'tenant']) }}" class="btn btn-primary btn-sm">+ Hire Agent</a>
                                            <a href="{{ route('tenant.agent.auctions.list') }}" class="btn btn-outline-secondary btn-sm">My Listings</a>
                                            <a href="{{ route('myBids', 'agent-bids') }}" class="btn btn-outline-secondary btn-sm">My Bids</a>
                                        @elseif(in_array($user->user_type, ['agent']))
                                            <a href="{{ route('myAuctions') }}" class="btn btn-primary btn-sm">My Property Listings</a>
                                            <a href="{{ route('myBids') }}" class="btn btn-outline-secondary btn-sm">My Bids</a>
                                            <a href="{{ route('agent.qr.settings') }}" class="btn btn-outline-secondary btn-sm">QR & Hire Me</a>
                                        @endif
                                        <a href="{{ route('messages') }}" class="btn btn-outline-secondary btn-sm">Messages</a>
                                        <a href="{{ route('settings') }}" class="btn btn-outline-secondary btn-sm">Profile Settings</a>
                                    </div>
                                </div>

                                {{-- Active Listings --}}
                                @php
                                    $activeListings = collect();
                                    if ($user->user_type === 'seller') {
                                        $activeListings = $user->seller_agent_auctions ?? collect();
                                    } elseif ($user->user_type === 'buyer') {
                                        $activeListings = $user->buyer_agent_auctions ?? collect();
                                    } elseif ($user->user_type === 'landlord') {
                                        $activeListings = $user->landlord_agent_auctions ?? collect();
                                    } elseif ($user->user_type === 'tenant') {
                                        $activeListings = $user->tenant_agent_auctions ?? collect();
                                    } elseif ($user->user_type === 'agent') {
                                        $activeListings = $user->property_auctions ?? collect();
                                    }
                                    $activeListingsCount = is_countable($activeListings) ? count($activeListings) : 0;
                                @endphp
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">Active Listings</div>
                                        @if($activeListingsCount > 0)
                                        <span class="badge bg-primary">{{ $activeListingsCount }}</span>
                                        @endif
                                    </div>
                                    @if($activeListingsCount > 0)
                                    <div class="card border-0 bg-light rounded-3 p-3">
                                        <div class="row g-2">
                                            @foreach(collect($activeListings)->take(3) as $listing)
                                            <div class="col-12">
                                                <div class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                                                    <div class="text-truncate small fw-semibold">
                                                        {{ $listing->address ?? (@$listing->property_type->name ?? 'Listing #'.$listing->id) }}
                                                    </div>
                                                    <span class="badge bg-success flex-shrink-0">Active</span>
                                                </div>
                                            </div>
                                            @endforeach
                                            @if($activeListingsCount > 3)
                                            <div class="col-12 text-muted small">+ {{ $activeListingsCount - 3 }} more listing{{ $activeListingsCount - 3 != 1 ? 's' : '' }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    @else
                                    <div class="card border-0 bg-light rounded-3 p-3 text-center text-muted small">
                                        No active listings yet. Use Quick Actions above to post your first listing.
                                    </div>
                                    @endif
                                </div>

                                {{-- Active Bids --}}
                                @php
                                    $activeBids = collect();
                                    if ($user->user_type === 'seller') {
                                        $activeBids = $user->seller_agent_auction_bid ?? collect();
                                    } elseif ($user->user_type === 'buyer') {
                                        $activeBids = $user->buyer_agent_auction_bid ?? collect();
                                    } elseif ($user->user_type === 'landlord') {
                                        $activeBids = $user->landlord_agent_auction_bid ?? collect();
                                    } elseif ($user->user_type === 'tenant') {
                                        $activeBids = $user->tenant_agent_auction_bid ?? collect();
                                    } elseif ($user->user_type === 'agent') {
                                        $activeBids = collect()
                                            ->merge($user->seller_agent_auction_bid ?? collect())
                                            ->merge($user->buyer_agent_auction_bid ?? collect())
                                            ->merge($user->landlord_agent_auction_bid ?? collect())
                                            ->merge($user->tenant_agent_auction_bid ?? collect());
                                    }
                                    $activeBidsCount = is_countable($activeBids) ? count($activeBids) : 0;
                                @endphp
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">Active Bids</div>
                                        @if($activeBidsCount > 0)
                                        <span class="badge bg-info">{{ $activeBidsCount }}</span>
                                        @endif
                                    </div>
                                    @if($activeBidsCount > 0)
                                    <div class="card border-0 bg-light rounded-3 p-3">
                                        <p class="text-muted small mb-0">You have <strong>{{ $activeBidsCount }}</strong> active bid{{ $activeBidsCount != 1 ? 's' : '' }} in progress. <a href="{{ route('myBids') }}">Review all bids &rarr;</a></p>
                                    </div>
                                    @else
                                    <div class="card border-0 bg-light rounded-3 p-3 text-center text-muted small">
                                        No active bids yet. Once agents place bids on your listings, they'll appear here.
                                    </div>
                                    @endif
                                </div>

                                {{-- Counter Offers Pending --}}
                                @php
                                    $counterStatuses = ['Countered', 'Counter'];
                                    $counterBids = collect();
                                    if ($user->user_type === 'seller') {
                                        $counterBids = collect($user->seller_agent_auction_bid ?? collect())
                                            ->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $counterStatuses));
                                    } elseif ($user->user_type === 'buyer') {
                                        $counterBids = collect($user->buyer_agent_auction_bid ?? collect())
                                            ->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $counterStatuses));
                                    } elseif ($user->user_type === 'landlord') {
                                        $counterBids = collect($user->landlord_agent_auction_bid ?? collect())
                                            ->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $counterStatuses));
                                    } elseif ($user->user_type === 'tenant') {
                                        $counterBids = collect($user->tenant_agent_auction_bid ?? collect())
                                            ->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $counterStatuses));
                                    } elseif ($user->user_type === 'agent') {
                                        $counterBids = collect()
                                            ->merge(collect($user->seller_agent_auction_bid ?? collect())->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $counterStatuses)))
                                            ->merge(collect($user->buyer_agent_auction_bid ?? collect())->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $counterStatuses)))
                                            ->merge(collect($user->landlord_agent_auction_bid ?? collect())->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $counterStatuses)))
                                            ->merge(collect($user->tenant_agent_auction_bid ?? collect())->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $counterStatuses)));
                                    }
                                    $counterBidsCount = count($counterBids);
                                @endphp
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">Counter Offers Pending</div>
                                        @if($counterBidsCount > 0)
                                        <span class="badge bg-warning text-dark">{{ $counterBidsCount }}</span>
                                        @endif
                                    </div>
                                    @if($counterBidsCount > 0)
                                    <div class="card border-0 rounded-3 p-3" style="border-left:3px solid #ffc107 !important;background:#fffdf0;">
                                        <p class="text-muted small mb-1"><strong>{{ $counterBidsCount }}</strong> counter offer{{ $counterBidsCount != 1 ? 's' : '' }} awaiting your response.</p>
                                        <a href="{{ route('myBids') }}" class="btn btn-sm btn-warning">Review Counter Offers &rarr;</a>
                                    </div>
                                    @else
                                    <div class="card border-0 bg-light rounded-3 p-3 text-center text-muted small">
                                        No counter offers pending. When you or an agent send a counter, it will appear here.
                                    </div>
                                    @endif
                                </div>

                                {{-- Accepted Deals --}}
                                @php
                                    $acceptedStatuses = ['Accepted', 'accepted'];
                                    $acceptedBids = collect();
                                    if ($user->user_type === 'seller') {
                                        $acceptedBids = collect($user->seller_agent_auction_bid ?? collect())
                                            ->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $acceptedStatuses));
                                    } elseif ($user->user_type === 'buyer') {
                                        $acceptedBids = collect($user->buyer_agent_auction_bid ?? collect())
                                            ->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $acceptedStatuses));
                                    } elseif ($user->user_type === 'landlord') {
                                        $acceptedBids = collect($user->landlord_agent_auction_bid ?? collect())
                                            ->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $acceptedStatuses));
                                    } elseif ($user->user_type === 'tenant') {
                                        $acceptedBids = collect($user->tenant_agent_auction_bid ?? collect())
                                            ->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $acceptedStatuses));
                                    } elseif ($user->user_type === 'agent') {
                                        $acceptedBids = collect()
                                            ->merge(collect($user->seller_agent_auction_bid ?? collect())->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $acceptedStatuses)))
                                            ->merge(collect($user->buyer_agent_auction_bid ?? collect())->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $acceptedStatuses)))
                                            ->merge(collect($user->landlord_agent_auction_bid ?? collect())->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $acceptedStatuses)))
                                            ->merge(collect($user->tenant_agent_auction_bid ?? collect())->filter(fn($b) => in_array($b->bid_status ?? $b->status ?? '', $acceptedStatuses)));
                                    }
                                    $acceptedBidsCount = count($acceptedBids);
                                @endphp
                                <div class="mb-4">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small text-uppercase text-muted fw-bold" style="letter-spacing:.06em;font-size:.7rem;">Accepted Deals</div>
                                        @if($acceptedBidsCount > 0)
                                        <span class="badge bg-success">{{ $acceptedBidsCount }}</span>
                                        @endif
                                    </div>
                                    @if($acceptedBidsCount > 0)
                                    <div class="card border-0 rounded-3 p-3" style="border-left:3px solid #198754 !important;background:#f0fff6;">
                                        <p class="text-muted small mb-1"><strong>{{ $acceptedBidsCount }}</strong> accepted deal{{ $acceptedBidsCount != 1 ? 's' : '' }}. Congratulations — next step is to complete your contract.</p>
                                        <a href="{{ route('myBids') }}" class="btn btn-sm btn-success">View Accepted Deals &rarr;</a>
                                    </div>
                                    @else
                                    <div class="card border-0 bg-light rounded-3 p-3 text-center text-muted small">
                                        No accepted deals yet. When a bid is accepted, it will appear here.
                                    </div>
                                    @endif
                                </div>

                                {{-- Needs Attention --}}
                                <div class="mb-4">
                                    <div class="small text-uppercase text-muted fw-bold mb-2" style="letter-spacing:.06em;font-size:.7rem;">Needs Attention</div>
                                    @if($counterBidsCount > 0)
                                    <div class="card border-0 rounded-3 p-3 mb-2" style="border-left:3px solid #ffc107 !important;background:#fffdf0;">
                                        <ul class="list-unstyled mb-0 small">
                                            <li class="d-flex align-items-center gap-2">
                                                <i class="fa fa-exclamation-circle text-warning"></i>
                                                <span><strong>{{ $counterBidsCount }} counter offer{{ $counterBidsCount != 1 ? 's' : '' }}</strong> need{{ $counterBidsCount == 1 ? 's' : '' }} your response. <a href="{{ route('myBids') }}">Review now &rarr;</a></span>
                                            </li>
                                        </ul>
                                    </div>
                                    @else
                                    <div class="card border-0 bg-light rounded-3 p-3 text-center text-muted small">
                                        Nothing needs your attention right now — you're all caught up!
                                    </div>
                                    @endif
                                </div>

                                {{-- Recent Activity --}}
                                <div class="mb-4">
                                    <div class="small text-uppercase text-muted fw-bold mb-2" style="letter-spacing:.06em;font-size:.7rem;">Recent Activity</div>
                                    @if($activeBidsCount > 0 || $acceptedBidsCount > 0 || $activeListingsCount > 0)
                                    <div class="card border-0 bg-light rounded-3 p-3">
                                        <ul class="list-unstyled mb-0 small">
                                            @if($activeListingsCount > 0)
                                            <li class="d-flex align-items-center gap-2 py-1 border-bottom">
                                                <i class="fa fa-list text-secondary"></i>
                                                <span><strong>{{ $activeListingsCount }} active listing{{ $activeListingsCount != 1 ? 's' : '' }}</strong> posted on BidYourAgent.</span>
                                            </li>
                                            @endif
                                            @if($activeBidsCount > 0)
                                            <li class="d-flex align-items-center gap-2 py-1 {{ $acceptedBidsCount > 0 ? 'border-bottom' : '' }}">
                                                <i class="fa fa-gavel text-primary"></i>
                                                <span><strong>{{ $activeBidsCount }} active bid{{ $activeBidsCount != 1 ? 's' : '' }}</strong> in progress. <a href="{{ route('myBids') }}">View bids &rarr;</a></span>
                                            </li>
                                            @endif
                                            @if($acceptedBidsCount > 0)
                                            <li class="d-flex align-items-center gap-2 py-1">
                                                <i class="fa fa-check-circle text-success"></i>
                                                <span><strong>{{ $acceptedBidsCount }} deal{{ $acceptedBidsCount != 1 ? 's' : '' }} accepted.</strong> Complete your contract to move forward. <a href="{{ route('myBids') }}">View &rarr;</a></span>
                                            </li>
                                            @endif
                                        </ul>
                                    </div>
                                    @else
                                    <div class="card border-0 bg-light rounded-3 p-3 text-center text-muted small">
                                        No recent activity yet. Your bids, accepted deals, and listing updates will appear here as they happen.
                                    </div>
                                    @endif
                                </div>

                                {{-- Platform Banner --}}
                                <div class="bg-light rounded-3 p-3 p-md-4 mb-4 d-flex align-items-center gap-3">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold mb-1">BidYourAgent is constantly improving.</div>
                                        <div class="small text-muted">Have a question or feedback? We'd love to hear from you.</div>
                                        <a href="{{ route('faqs') }}" class="btn btn-sm btn-outline-secondary mt-2">View FAQs</a>
                                    </div>
                                    <div class="d-none d-md-block" style="max-width:140px;">
                                        <img src="{{ asset('assets/pictures/dashboard.jpg') }}" class="img-fluid rounded-2" alt="dashboard" />
                                    </div>
                                </div>

                                {{-- Recent Notices --}}
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
                                                $data = $notification->data;
                                                $type = $data['type'] ?? 'general';
                                                $message = $data['message'] ?? 'You have a notification';
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
