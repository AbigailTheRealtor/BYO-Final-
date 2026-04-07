@extends('layouts.main')
@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Competing Bids</h4>
                    <p class="text-muted mb-0">Listing: {{ $listingId }}</p>
                </div>
                <a href="{{ route('tenant.agent.auction.view', $auction->id) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Listing
                </a>
            </div>

            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Bidding Period Transparency:</strong> During the active bidding period, you can view anonymized competing bids showing only Broker Compensation & Agency Agreement Terms, Offered Services, and Match Scores. Agent identities remain confidential.
            </div>

            @if(count($competingBids) === 0)
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5>No Competing Bids Yet</h5>
                        <p class="text-muted">Be the first to submit a bid, or check back later to see competing offers.</p>
                    </div>
                </div>
            @else
                @foreach($competingBids as $index => $bid)
                    <div class="card mb-4" style="border-radius: 12px; border: 1px solid #e0e0e0;">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background: #f8f9fa; border-bottom: 1px solid #e0e0e0; border-radius: 12px 12px 0 0;">
                            <div class="d-flex align-items-center gap-3">
                                <span class="fw-bold" style="font-size: 1.1rem;">{{ $bid['anonymous_label'] }}</span>
                                @if($bid['is_updated'])
                                    <span class="badge" style="background: #e3f2fd; color: #1976d2; font-size: 0.75rem;">
                                        <i class="fas fa-sync-alt me-1"></i>Updated
                                    </span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                @php
                                    $score = $bid['match_score']['overall_percent'];
                                    $scoreColor = $score >= 80 ? '#28a745' : ($score >= 50 ? '#ffc107' : '#dc3545');
                                @endphp
                                <div class="text-end">
                                    <span class="badge" style="background: {{ $scoreColor }}; color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem;">
                                        <i class="fas fa-chart-pie me-1"></i>{{ $score }}% Match
                                    </span>
                                    <div class="small text-muted mt-1">Compared to Your Bid</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <h6 class="fw-bold mb-3" style="color: #049399;">
                                        <i class="fas fa-file-contract me-2"></i>Broker Compensation & Agency Agreement Terms
                                    </h6>
                                    <div class="bg-light p-3 rounded">
                                        @if(count($bid['broker_compensation']) > 0)
                                            <div class="row">
                                                @foreach($bid['broker_compensation'] as $field => $value)
                                                    <div class="col-6 mb-2">
                                                        <small class="text-muted d-block">{{ ucwords(str_replace('_', ' ', $field)) }}</small>
                                                        <span class="fw-medium">
                                                            @if(is_array($value))
                                                                {{ implode(', ', $value) }}
                                                            @else
                                                                {{ $value }}
                                                            @endif
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-muted mb-0">No broker compensation terms specified.</p>
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-4">
                                    <h6 class="fw-bold mb-3" style="color: #049399;">
                                        <i class="fas fa-concierge-bell me-2"></i>Offered Services
                                    </h6>
                                    <div class="bg-light p-3 rounded">
                                        @if(count($bid['offered_services']['standard']) > 0 || count($bid['offered_services']['other']) > 0)
                                            <div class="d-flex flex-wrap gap-2">
                                                @foreach($bid['offered_services']['standard'] as $service)
                                                    <span class="badge" style="background: #e8f5e9; color: #2e7d32; padding: 6px 12px; border-radius: 20px;">
                                                        <i class="fas fa-check me-1"></i>{{ $service }}
                                                    </span>
                                                @endforeach
                                                @foreach($bid['offered_services']['other'] as $service)
                                                    <span class="badge" style="background: #fff3e0; color: #e65100; padding: 6px 12px; border-radius: 20px;">
                                                        <i class="fas fa-plus me-1"></i>{{ $service }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="text-muted mb-0">No services specified.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border-top pt-3">
                                <h6 class="fw-bold mb-3" style="color: #049399;">
                                    <i class="fas fa-chart-bar me-2"></i>Match Score Breakdown
                                </h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="text-center p-3 rounded" style="background: #f8f9fa;">
                                            @php
                                                $brokerScore = $bid['match_score']['broker_comp_percent'];
                                                $brokerColor = $brokerScore >= 80 ? '#28a745' : ($brokerScore >= 50 ? '#ffc107' : '#dc3545');
                                            @endphp
                                            <div class="fw-bold" style="font-size: 1.5rem; color: {{ $brokerColor }};">{{ $brokerScore }}%</div>
                                            <small class="text-muted">Broker Compensation</small>
                                            <div class="small text-muted">{{ $bid['match_score']['broker_comp_total'] > 0 ? $bid['match_score']['broker_comp_matched'].'/'.$bid['match_score']['broker_comp_total'].' fields matched' : 'No terms provided' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center p-3 rounded" style="background: #f8f9fa;">
                                            @php
                                                $servicesScore = $bid['match_score']['services_percent'];
                                                $servicesColor = $servicesScore >= 80 ? '#28a745' : ($servicesScore >= 50 ? '#ffc107' : '#dc3545');
                                            @endphp
                                            <div class="fw-bold" style="font-size: 1.5rem; color: {{ $servicesColor }};">{{ $servicesScore }}%</div>
                                            <small class="text-muted">Services</small>
                                            <div class="small text-muted">{{ $bid['match_score']['services_total'] > 0 ? $bid['match_score']['services_matched'].'/'.$bid['match_score']['services_total'].' services matched' : 'No services requested' }}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center p-3 rounded" style="background: #e3f2fd;">
                                            <div class="fw-bold" style="font-size: 1.5rem; color: {{ $scoreColor }};">{{ $score }}%</div>
                                            <small class="text-muted">Overall Match</small>
                                            <div class="small" style="color: #666;">{{ $bid['match_score']['compared_to_label'] }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection
