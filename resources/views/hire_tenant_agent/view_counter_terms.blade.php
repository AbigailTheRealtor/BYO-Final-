@extends('layouts.main')

@section('content')
<div class="container py-5">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('myBids', 'tenant-agent') }}">My Bids</a></li>
                    <li class="breadcrumb-item active">Counter Terms</li>
                </ol>
            </nav>
            
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Counter Terms for Your Bid</h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">Listing Information</h6>
                            <p><strong>Title:</strong> {{ $auction->title ?? 'N/A' }}</p>
                            <p><strong>Address:</strong> {{ $auction->get->address ?? 'N/A' }}</p>
                            <p><strong>Listing Owner:</strong> {{ $auction->user->name ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Your Bid Status</h6>
                            @php
                                $bidStatus = $bid->bid_status ?? 'Active';
                            @endphp
                            <p><strong>Status:</strong> 
                                <span class="badge {{ $bidStatus === 'Countered' ? 'bg-warning text-dark' : ($bidStatus === 'Accepted' ? 'bg-success' : ($bidStatus === 'Rejected' ? 'bg-danger' : 'bg-primary')) }}">
                                    {{ $bidStatus }}
                                </span>
                            </p>
                            <p><strong>Submitted:</strong> {{ $bid->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                    </div>
                    
                    @if($tenantCounter)
                    <div class="border rounded p-4 bg-light mb-4">
                        <h5 class="text-primary mb-3"><i class="fas fa-file-contract me-2"></i>Tenant's Counter Terms</h5>
                        <p class="text-muted small">Last updated: {{ $tenantCounter->updated_at->format('M d, Y h:i A') }}</p>
                        
                        @php
                            $counterData = $tenantCounter->meta ? $tenantCounter->meta->pluck('meta_value', 'meta_key')->toArray() : [];
                        @endphp
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Broker Compensation</h6>
                                <ul class="list-unstyled">
                                    @if(!empty($counterData['commission_structure']))
                                        <li><strong>Commission Structure:</strong> {{ $counterData['commission_structure'] }}</li>
                                    @endif
                                    @if(!empty($counterData['lease_fee_type']))
                                        <li><strong>Lease Fee Type:</strong> {{ $counterData['lease_fee_type'] }}</li>
                                    @endif
                                    @if(!empty($counterData['lease_fee_flat']))
                                        <li><strong>Lease Fee Flat:</strong> ${{ number_format($counterData['lease_fee_flat'], 2) }}</li>
                                    @endif
                                    @if(!empty($counterData['lease_fee_percentage']))
                                        <li><strong>Lease Fee Percentage:</strong> {{ $counterData['lease_fee_percentage'] }}%</li>
                                    @endif
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Agreement Terms</h6>
                                <ul class="list-unstyled">
                                    @if(!empty($counterData['protection_period']))
                                        <li><strong>Protection Period:</strong> {{ $counterData['protection_period'] }} days</li>
                                    @endif
                                    @if(!empty($counterData['agency_agreement_timeframe']))
                                        <li><strong>Agency Agreement:</strong> {{ $counterData['agency_agreement_timeframe'] }}</li>
                                    @endif
                                    @if(!empty($counterData['brokerage_relationship']))
                                        <li><strong>Brokerage Relationship:</strong> {{ $counterData['brokerage_relationship'] }}</li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                        
                        @if(!empty($counterData['services']))
                            @php
                                $services = is_string($counterData['services']) ? json_decode($counterData['services'], true) : $counterData['services'];
                            @endphp
                            @if(is_array($services) && count($services) > 0)
                            <div class="mt-3">
                                <h6>Requested Services</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($services as $service)
                                        <span class="badge bg-secondary">{{ $service }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        @endif
                        
                        @if(!empty($counterData['additional_details']))
                        <div class="mt-3">
                            <h6>Additional Details</h6>
                            <p>{{ $counterData['additional_details'] }}</p>
                        </div>
                        @endif
                    </div>
                    
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('tenant.agent.auction.view', $auction->id) }}" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i>View Listing
                        </a>
                        <a href="{{ route('agent.tenant.counter-bid', ['pab' => $auction->id, 'bidId' => $bid->id]) }}" class="btn btn-warning">
                            <i class="fas fa-reply me-2"></i>Counter Back
                        </a>
                        <form action="{{ route('tenant.hire.agent.auction.counter.bid.accept') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="counter_bid_id" value="{{ $tenantCounter->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to accept these counter terms?')">
                                <i class="fas fa-check me-2"></i>Accept Counter
                            </button>
                        </form>
                        <form action="{{ route('tenant.hire.agent.auction.counter.bid.reject') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="counter_bid_id" value="{{ $tenantCounter->id }}">
                            <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject these counter terms?')">
                                <i class="fas fa-times me-2"></i>Reject Counter
                            </button>
                        </form>
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No counter terms have been submitted by the tenant yet. Your bid is still active and awaiting a response.
                    </div>
                    <a href="{{ route('tenant.agent.auction.view', $auction->id) }}" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Listing
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
