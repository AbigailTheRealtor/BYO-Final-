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
                                        <div class="fw-bold"><i class="fas fa-users me-2"></i>Agent Bids</div>
                                        <a href="{{ route('tenant.agent.auctions.list') }}" class="btn btn-sm btn-outline-primary" style="border-color: #049399; color: #049399;">View All Listings</a>
                                    </div>
                                    
                                    @foreach($pendingAgentBids as $agentBid)
                                    @php
                                        $agentName = $agentBid->user->name ?? 'Agent';
                                        $agentEmail = $agentBid->user->email ?? '';
                                        $listingAddress = $agentBid->auction->get->address ?? 'N/A';
                                        $listingId = $agentBid->auction->listing_id ?? 'TAA-'.$agentBid->auction->id;
                                        $bidStatus = $agentBid->bid_status ?? 'Active';
                                        $statusStyles = [
                                            'Countered' => 'background-color: #ffc107; color: #000;',
                                            'Active' => 'background-color: #007bff; color: #fff;',
                                            'Accepted' => 'background-color: #28a745; color: #fff;',
                                            'Rejected' => 'background-color: #dc3545; color: #fff;',
                                        ];
                                        
                                        $bidData = (array) $agentBid->get;
                                        $auctionData = (array) $agentBid->auction->get;
                                        
                                        $normalizeForMatch = function($v) {
                                            if (is_null($v) || $v === '') return '';
                                            if (is_array($v) || is_object($v)) return json_encode($v);
                                            $v = trim((string) $v);
                                            return preg_replace('/[\s$,%]/', '', strtolower($v));
                                        };
                                        
                                        $brokerFields = [
                                            'commission_structure', 'lease_fee_type', 'broker_fee_timing', 'broker_fee_days_from_rent',
                                            'interested_purchase_fee_type', 'purchase_fee_type', 'interested_lease_option_agreement',
                                            'lease_type', 'lease_value', 'purchase_type', 'purchase_value', 'protection_period',
                                            'early_termination_fee_option', 'early_termination_fee_amount', 'retainer_fee_option',
                                            'retainer_fee_amount', 'retainer_fee_application', 'agency_agreement_timeframe', 'brokerage_relationship',
                                        ];
                                        
                                        $brokerMatched = 0;
                                        $brokerTotal = 0;
                                        $brokerMismatches = [];
                                        
                                        foreach ($brokerFields as $field) {
                                            $bidVal = $bidData[$field] ?? null;
                                            $auctionVal = $auctionData[$field] ?? null;
                                            if (!empty($bidVal) || !empty($auctionVal)) {
                                                $brokerTotal++;
                                                if ($normalizeForMatch($bidVal) === $normalizeForMatch($auctionVal)) {
                                                    $brokerMatched++;
                                                } else {
                                                    $brokerMismatches[$field] = ['bid' => $bidVal, 'listing' => $auctionVal];
                                                }
                                            }
                                        }
                                        
                                        $brokerScore = $brokerTotal > 0 ? round(($brokerMatched / $brokerTotal) * 100) : 100;
                                        
                                        $normalizeService = function($s) {
                                            $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
                                            $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
                                            return strtolower(trim($s));
                                        };
                                        
                                        $bidServices = $bidData['services'] ?? [];
                                        if (is_string($bidServices)) $bidServices = json_decode($bidServices, true) ?? [];
                                        $bidServices = is_array($bidServices) ? array_values(array_filter($bidServices)) : [];
                                        
                                        $bidOtherServices = $bidData['other_services'] ?? [];
                                        if (is_string($bidOtherServices)) $bidOtherServices = json_decode($bidOtherServices, true) ?? [];
                                        $bidOtherServices = is_array($bidOtherServices) ? array_values(array_filter($bidOtherServices, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                                        $allBidServices = array_merge($bidServices, $bidOtherServices);
                                        
                                        $auctionServices = $auctionData['services'] ?? [];
                                        if (is_string($auctionServices)) $auctionServices = json_decode($auctionServices, true) ?? [];
                                        $auctionServices = is_array($auctionServices) ? array_values(array_filter($auctionServices)) : [];
                                        
                                        $auctionOtherServices = $auctionData['other_services'] ?? [];
                                        if (is_string($auctionOtherServices)) $auctionOtherServices = json_decode($auctionOtherServices, true) ?? [];
                                        $auctionOtherServices = is_array($auctionOtherServices) ? array_values(array_filter($auctionOtherServices, fn($s) => is_string($s) && !empty(trim($s)))) : [];
                                        $allAuctionServices = array_merge($auctionServices, $auctionOtherServices);
                                        
                                        $bidNorm = array_map($normalizeService, $allBidServices);
                                        $auctionNorm = array_map($normalizeService, $allAuctionServices);
                                        
                                        $allServicesUnion = array_unique(array_merge($bidNorm, $auctionNorm));
                                        $servicesTotal = count($allServicesUnion);
                                        $servicesMatched = 0;
                                        $servicesMissing = [];
                                        $servicesAdded = [];
                                        
                                        foreach ($allServicesUnion as $svc) {
                                            $inBid = in_array($svc, $bidNorm);
                                            $inAuction = in_array($svc, $auctionNorm);
                                            if ($inBid && $inAuction) {
                                                $servicesMatched++;
                                            } elseif ($inBid && !$inAuction) {
                                                $servicesAdded[] = $svc;
                                            } elseif (!$inBid && $inAuction) {
                                                $servicesMissing[] = $svc;
                                            }
                                        }
                                        
                                        $servicesScore = $servicesTotal > 0 ? round(($servicesMatched / $servicesTotal) * 100) : 100;
                                        
                                        $hasBroker = $brokerTotal > 0;
                                        $hasServices = $servicesTotal > 0;
                                        
                                        if ($hasBroker && $hasServices) {
                                            $totalScore = round(($brokerScore + $servicesScore) / 2);
                                        } elseif ($hasBroker) {
                                            $totalScore = $brokerScore;
                                        } elseif ($hasServices) {
                                            $totalScore = $servicesScore;
                                        } else {
                                            $totalScore = 100;
                                        }
                                        
                                        $getScoreColor = function($score) {
                                            if ($score >= 80) return '#28a745';
                                            if ($score >= 50) return '#ffc107';
                                            return '#dc3545';
                                        };
                                        
                                        $totalScoreColor = $getScoreColor($totalScore);
                                        $brokerScoreColor = $getScoreColor($brokerScore);
                                        $servicesScoreColor = $getScoreColor($servicesScore);
                                    @endphp
                                    
                                    <div class="card mb-3" style="border: 1px solid #dee2e6; border-radius: 8px;">
                                        <div class="card-header d-flex justify-content-between align-items-center" style="background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                                            <div>
                                                <span class="fw-bold">{{ $agentName }}</span>
                                                <small class="text-muted d-block">{{ $agentEmail }}</small>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge" style="{{ $statusStyles[$bidStatus] ?? $statusStyles['Active'] }} padding: 6px 12px; border-radius: 4px;">{{ $bidStatus }}</span>
                                                <span class="badge" style="background: {{ $totalScoreColor }}; color: white; padding: 6px 12px; border-radius: 4px;">
                                                    <i class="fas fa-chart-pie me-1"></i>{{ $totalScore }}% Match
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-4 mb-2">
                                                    <small class="text-muted">Listing</small>
                                                    <div>{{ Str::limit($listingAddress, 40) }}</div>
                                                    <small class="text-muted">{{ $listingId }}</small>
                                                </div>
                                                <div class="col-md-4 mb-2">
                                                    <small class="text-muted">Submitted</small>
                                                    <div>{{ $agentBid->created_at->format('M d, Y') }}</div>
                                                </div>
                                                <div class="col-md-4 mb-2">
                                                    <div class="d-flex gap-3">
                                                        <div>
                                                            <small class="text-muted d-block">Broker Terms</small>
                                                            <span style="color: {{ $brokerScoreColor }}; font-weight: 600;">{{ $brokerScore }}%</span>
                                                            <small class="text-muted">({{ $brokerMatched }}/{{ $brokerTotal }})</small>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted d-block">Services</small>
                                                            <span style="color: {{ $servicesScoreColor }}; font-weight: 600;">{{ $servicesScore }}%</span>
                                                            <small class="text-muted">({{ $servicesMatched }}/{{ $servicesTotal }})</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            @if(count($brokerMismatches) > 0 || count($servicesAdded) > 0 || count($servicesMissing) > 0)
                                            <div class="mt-3 pt-3" style="border-top: 1px solid #eee;">
                                                <div class="row">
                                                    @if(count($brokerMismatches) > 0)
                                                    <div class="col-md-4 mb-2">
                                                        <small class="fw-semibold text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Term Differences ({{ count($brokerMismatches) }})</small>
                                                        <div class="mt-1" style="max-height: 60px; overflow-y: auto;">
                                                            @foreach(array_slice($brokerMismatches, 0, 3) as $field => $vals)
                                                                <span class="badge me-1 mb-1" style="background: #ffe6e6; color: #dc3545; font-size: 0.7rem;">{{ ucwords(str_replace('_', ' ', $field)) }}</span>
                                                            @endforeach
                                                            @if(count($brokerMismatches) > 3)
                                                                <span class="badge" style="background: #f8d7da; color: #721c24; font-size: 0.7rem;">+{{ count($brokerMismatches) - 3 }} more</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @endif
                                                    @if(count($servicesAdded) > 0)
                                                    <div class="col-md-4 mb-2">
                                                        <small class="fw-semibold" style="color: #28a745;"><i class="fas fa-plus-circle me-1"></i>Extra Services ({{ count($servicesAdded) }})</small>
                                                        <div class="mt-1" style="max-height: 60px; overflow-y: auto;">
                                                            @foreach(array_slice($servicesAdded, 0, 2) as $svc)
                                                                <span class="badge me-1 mb-1" style="background: #e6ffe6; color: #28a745; font-size: 0.7rem;">{{ Str::limit($svc, 25) }}</span>
                                                            @endforeach
                                                            @if(count($servicesAdded) > 2)
                                                                <span class="badge" style="background: #d4edda; color: #155724; font-size: 0.7rem;">+{{ count($servicesAdded) - 2 }} more</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @endif
                                                    @if(count($servicesMissing) > 0)
                                                    <div class="col-md-4 mb-2">
                                                        <small class="fw-semibold" style="color: #ffc107;"><i class="fas fa-minus-circle me-1"></i>Not Offered ({{ count($servicesMissing) }})</small>
                                                        <div class="mt-1" style="max-height: 60px; overflow-y: auto;">
                                                            @foreach(array_slice($servicesMissing, 0, 2) as $svc)
                                                                <span class="badge me-1 mb-1" style="background: #fff3cd; color: #856404; font-size: 0.7rem;">{{ Str::limit($svc, 25) }}</span>
                                                            @endforeach
                                                            @if(count($servicesMissing) > 2)
                                                                <span class="badge" style="background: #ffeeba; color: #856404; font-size: 0.7rem;">+{{ count($servicesMissing) - 2 }} more</span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                        <div class="card-footer d-flex justify-content-end gap-2" style="background: #f8f9fa;">
                                            <a href="{{ route('tenant.agent.auction.view', $agentBid->auction->id) }}" class="btn btn-sm" style="background: #fff; border: 1px solid #049399; color: #049399;">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </a>
                                            @if($bidStatus === 'Active')
                                                <form action="{{ route('tenant.hire.agent.auction.bid.accept') }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="bid_id" value="{{ $agentBid->id }}">
                                                    <input type="hidden" name="auction_id" value="{{ $agentBid->auction->id }}">
                                                    <button type="submit" class="btn btn-sm" style="background: #28a745; color: #fff; border: none;" onclick="return confirm('Accept this bid?')">
                                                        <i class="fas fa-check me-1"></i>Accept
                                                    </button>
                                                </form>
                                                <a href="{{ route('tenant.counter-terms', $agentBid->id) }}" class="btn btn-sm" style="background: #ffc107; color: #000; border: none;">
                                                    <i class="fas fa-exchange-alt me-1"></i>Counter
                                                </a>
                                                <form action="{{ route('tenant.hire.agent.auction.bid.reject') }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="bid_id" value="{{ $agentBid->id }}">
                                                    <input type="hidden" name="auction_id" value="{{ $agentBid->auction->id }}">
                                                    <button type="submit" class="btn btn-sm" style="background: #dc3545; color: #fff; border: none;" onclick="return confirm('Reject this bid?')">
                                                        <i class="fas fa-times me-1"></i>Reject
                                                    </button>
                                                </form>
                                            @elseif($bidStatus === 'Countered')
                                                <span class="btn btn-sm" style="background: #fff3cd; color: #856404; border: 1px solid #ffc107;">
                                                    <i class="fas fa-clock me-1"></i>Awaiting Response
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach
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
