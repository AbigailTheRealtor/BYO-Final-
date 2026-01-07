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
                            <div class="container mt-5">
                                <h1>Welcome Back</h1>
                                <p>Here's an overview of your account.</p>
                                <!-- Section 1  -->
                                <div class="p-3 p-md-4 mt-2 mb-5 card">
                                    <div class="d-md-flex justift-content-between userDetails">
                                        <div class="d-md-flex border-end pr-3 w-100 me-3 mb-3">
                                            <div class="me-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z">
                                                    </path>
                                                </svg>
                                            </div>
                                            <a href="/" class="text-black text-decoration-none">
                                                <span>
                                                    <div class="text-600">My Username</div>
                                                    <div class="small opacity-80 text-sm-start" style="max-width: 150px">
                                                        {{ $user->user_name }}
                                                    </div>
                                                </span>
                                            </a>
                                        </div>

                                        <div class="d-md-flex border-end pr-3 w-100 me-3 mb-3">
                                            <div class="me-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                                    </path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                            </div>
                                            <a href="/" class="text-black text-decoration-none">
                                                <span>
                                                    <div class="text-600">My Location</div>
                                                    <div class="text-sm-start opacity-80" style="max-width: 150px">
                                                        Afghanistan</div>
                                                </span>
                                            </a>
                                        </div>

                                        <div class="d-md-flex w-100">
                                            <div class="me-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                                    </path>
                                                </svg>
                                            </div>
                                            <a href="/" class="text-black text-decoration-none">
                                                <span>
                                                    <div class="text-600">My Email</div>
                                                    <div class="text-sm-start opacity-80 text-truncate"
                                                        style="max-width: 150px">{{ $user->email }}
                                                    </div>
                                                </span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <!-- End  -->
                                <!-- Section 2 -->
                                <div class="bg-light p-3 p-md-5 rounded-2 mb-4 position-relative">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="lh-30">
                                                <div class="fs-5 text-600 mb-2">Welcome back!</div>
                                                We are constantly updating and improving our service. If you have any
                                                questions or feedback get in
                                                touch - we would love to hear them.
                                            </div>
                                            <div class="mt-3 fs-lg"><a href="/" class="badge text-bg-light p-2"
                                                    style="border: 1px solid #e0e0e0;background-color: #fff!important;">Contact
                                                    us</a></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="my-4 my-md-0">
                                                <div class="position-relative">
                                                    <img src="{{ asset('assets/pictures/dashboard.jpg') }}"
                                                        class="img-fluid rounded-2" alt="img" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End  -->
                                
                                @if(isset($pendingAgentBids) && $pendingAgentBids->count() > 0)
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="fw-bold"><i class="fas fa-users me-2"></i>Pending Agent Bids</div>
                                        <a href="{{ route('tenant.agent.auctions.list') }}" class="btn btn-sm btn-outline-primary">View All Listings</a>
                                    </div>
                                    <div class="card">
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Agent</th>
                                                            <th>Listing</th>
                                                            <th>Status</th>
                                                            <th>Submitted</th>
                                                            <th class="text-end">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($pendingAgentBids as $agentBid)
                                                            @php
                                                                $agentName = $agentBid->user->name ?? 'Agent';
                                                                $listingAddress = $agentBid->auction->get->address ?? 'N/A';
                                                                $listingId = $agentBid->auction->listing_id ?? 'TAA-'.$agentBid->auction->id;
                                                                $bidStatus = $agentBid->bid_status ?? 'Active';
                                                                $statusClass = match($bidStatus) {
                                                                    'Countered' => 'bg-warning text-dark',
                                                                    'Active' => 'bg-primary',
                                                                    default => 'bg-secondary',
                                                                };
                                                            @endphp
                                                            <tr>
                                                                <td>
                                                                    <div class="fw-semibold">{{ $agentName }}</div>
                                                                    <small class="text-muted">{{ $agentBid->user->email ?? '' }}</small>
                                                                </td>
                                                                <td>
                                                                    <div>{{ Str::limit($listingAddress, 30) }}</div>
                                                                    <small class="text-muted">{{ $listingId }}</small>
                                                                </td>
                                                                <td>
                                                                    <span class="badge {{ $statusClass }}">{{ $bidStatus }}</span>
                                                                </td>
                                                                <td>
                                                                    <small>{{ $agentBid->created_at->format('M d, Y') }}</small>
                                                                </td>
                                                                <td class="text-end">
                                                                    <div class="btn-group btn-group-sm">
                                                                        <a href="{{ route('tenant.agent.auction.view', $agentBid->auction->id) }}" class="btn btn-outline-primary" title="View Bid">
                                                                            <i class="fas fa-eye"></i>
                                                                        </a>
                                                                        @if($bidStatus === 'Active')
                                                                            <form action="{{ route('tenant.hire.agent.auction.bid.accept') }}" method="POST" class="d-inline">
                                                                                @csrf
                                                                                <input type="hidden" name="bid_id" value="{{ $agentBid->id }}">
                                                                                <input type="hidden" name="auction_id" value="{{ $agentBid->auction->id }}">
                                                                                <button type="submit" class="btn btn-success" title="Accept" onclick="return confirm('Accept this bid?')">
                                                                                    <i class="fas fa-check"></i>
                                                                                </button>
                                                                            </form>
                                                                            <a href="{{ route('tenant.counter-terms', $agentBid->id) }}" class="btn btn-warning" title="Counter">
                                                                                <i class="fas fa-exchange-alt"></i>
                                                                            </a>
                                                                            <form action="{{ route('tenant.hire.agent.auction.bid.reject') }}" method="POST" class="d-inline">
                                                                                @csrf
                                                                                <input type="hidden" name="bid_id" value="{{ $agentBid->id }}">
                                                                                <input type="hidden" name="auction_id" value="{{ $agentBid->auction->id }}">
                                                                                <button type="submit" class="btn btn-danger" title="Reject" onclick="return confirm('Reject this bid?')">
                                                                                    <i class="fas fa-times"></i>
                                                                                </button>
                                                                            </form>
                                                                        @elseif($bidStatus === 'Countered')
                                                                            <span class="btn btn-outline-warning" title="Awaiting agent response">
                                                                                <i class="fas fa-clock"></i> Awaiting
                                                                            </span>
                                                                        @endif
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                                
                                <div class="fw-bold">Recent Notices</div>
                                <div class="notification mt-3">
                                    @if($notifications->isEmpty())
                                        <div class="opacity-50 mt-2 fw-bold">No notifications found.</div>
                                    @else
                                        @foreach($notifications as $notification)
                                            @php
                                                $data = $notification->data;
                                                $type = $data['type'] ?? 'general';
                                                $message = $data['message'] ?? 'You have a notification';
                                                $summaryLink = $data['summary_link'] ?? null;
                                                $listingId = $data['listing_id'] ?? $data['auction_id'] ?? null;
                                            @endphp
                                            <div class="alert alert-info d-flex justify-content-between align-items-center mb-2" role="alert">
                                                <div>
                                                    @if($type === 'bid_accepted')
                                                        <i class="fas fa-check-circle text-success me-2"></i>
                                                    @elseif($type === 'bid_countered' || $type === 'counter_bid_submitted')
                                                        <i class="fas fa-exchange-alt text-warning me-2"></i>
                                                    @elseif($type === 'bid_rejected')
                                                        <i class="fas fa-times-circle text-danger me-2"></i>
                                                    @elseif($type === 'bid_submitted')
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
                                                <div>
                                                    @if($summaryLink)
                                                        <a href="{{ $summaryLink }}" class="btn btn-sm btn-primary">View Summary</a>
                                                    @endif
                                                    <form action="{{ route('notifications.markRead') }}" method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{ $notification->id }}">
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
