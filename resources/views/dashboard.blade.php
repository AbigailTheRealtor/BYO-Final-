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
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <div>
                                        <h4 class="fw-bold mb-0">Welcome back, {{ $user->user_name }}!</h4>
                                        <p class="text-muted small mb-0">Here's an overview of your BidYourAgent account.</p>
                                    </div>
                                    <span class="badge text-bg-secondary text-capitalize">{{ $user->user_type }}</span>
                                </div>
                                <hr class="mt-2 mb-3">
                                <!-- Section 1: Account Info Cards -->
                                <div class="row g-3 mb-4">
                                    <div class="col-sm-6 col-md-4">
                                        <div class="card border-0 bg-light h-100 p-3">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" style="width:18px;height:18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                                <!-- End Section 1 -->
                                <!-- Section 2: Welcome Banner -->
                                <div class="bg-light rounded-2 p-3 p-md-4 mb-4 d-flex align-items-center gap-3">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold mb-1">BidYourAgent is constantly improving.</div>
                                        <div class="small text-muted">Have a question or feedback? We'd love to hear from you.</div>
                                        <a href="/" class="btn btn-sm btn-outline-secondary mt-2">Contact Us</a>
                                    </div>
                                    <div class="d-none d-md-block" style="max-width:160px;">
                                        <img src="{{ asset('assets/pictures/dashboard.jpg') }}" class="img-fluid rounded-2" alt="dashboard" />
                                    </div>
                                </div>
                                <!-- End Section 2 -->
                                <div class="fw-bold mb-1">Recent Notices</div>
                                <div class="notification mt-3">
                                    @if($notifications->isEmpty())
                                        <div class="opacity-50 mt-2 fw-bold">No notifications found.</div>
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
                                                <div class="d-flex gap-2">
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
