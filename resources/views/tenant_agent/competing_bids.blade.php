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
                <strong>Bidding Period:</strong> During the active bidding period, bids are advisory. You can view other Agents' Offered Services and Terms Match summaries below. Agent identities and compensation details remain confidential.
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
                            @php
                                $svcTotal   = $bid['match_score']['services_total'] ?? 0;
                                $svcMatched = $bid['match_score']['services_matched'] ?? 0;
                                $svcExtra   = $bid['match_score']['services_extra_count'] ?? 0;
                                $svcMissing = $bid['match_score']['services_missing_count'] ?? 0;
                                $trmTotal   = $bid['match_score']['broker_comp_total'] ?? 0;
                                $trmMatched = $bid['match_score']['broker_comp_matched'] ?? 0;
                                $trmChanged = $bid['match_score']['terms_changed_count'] ?? 0;
                                $trmAdded   = $bid['match_score']['terms_added_count'] ?? 0;
                                $trmMissing = max(0, $trmTotal - $trmMatched - $trmChanged);
                            @endphp

                            {{-- Offered Services --}}
                            <p class="mb-0" style="font-size: 1.05rem; color: #1a3a5c;">
                                <span style="font-weight: 600;">Offered Services:</span>
                                @if($svcTotal > 0)
                                    <span style="color: #28a745; font-weight: 600;">{{ $svcMatched }}/{{ $svcTotal }} matched</span>
                                    @if($svcExtra > 0) <span class="text-muted ms-2">&bull; {{ $svcExtra }} extra</span>@endif
                                    @if($svcMissing > 0) <span class="ms-2" style="color: #dc3545;">&bull; {{ $svcMissing }} missing</span>@endif
                                @else
                                    <span class="text-muted">No services requested</span>
                                @endif
                            </p>
                            @if($svcExtra > 0)
                            <div class="mt-1 mb-2" style="font-size: 0.78rem; color: #6c757d; font-style: italic;">&#11088; Extra Value Added &mdash; does not affect match score</div>
                            @endif

                            {{-- Terms Match --}}
                            <p class="mb-0 mt-2" style="font-size: 1.05rem; color: #1a3a5c;">
                                <span style="font-weight: 600;">Terms Match:</span>
                                @if($trmTotal > 0)
                                    <span style="color: #28a745; font-weight: 600;">{{ $trmMatched }}/{{ $trmTotal }} matched</span>
                                    @if($trmChanged > 0) <span class="ms-2" style="color: #dc3545;">&bull; {{ $trmChanged }} changed</span>@endif
                                    @if($trmAdded > 0) <span class="text-muted ms-2">&bull; {{ $trmAdded }} added</span>@endif
                                    @if($trmMissing > 0) <span class="ms-2" style="color: #dc3545;">&bull; {{ $trmMissing }} missing</span>@endif
                                @else
                                    <span class="text-muted">No terms provided</span>
                                @endif
                            </p>
                            <div class="mt-1" style="font-size: 0.78rem; color: #6c757d; font-style: italic;">&mdash; affects match score</div>

                            {{-- Match Summary (de-emphasized) --}}
                            <hr style="margin: 12px 0; border-color: #e0e0e0;">
                            <div class="p-2 rounded" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #dee2e6; font-size: 0.88rem;">
                                <span style="font-weight: 600; color: #6c757d; font-size: 0.85rem;"><i class="fas fa-chart-pie me-1"></i>Match Summary</span>
                                <div class="row g-2 mt-1">
                                    @php
                                        $servicesScore = $bid['match_score']['services_percent'];
                                        $servicesColor = $servicesScore >= 80 ? '#28a745' : ($servicesScore >= 50 ? '#ffc107' : '#dc3545');
                                    @endphp
                                    <div class="col-6 text-center">
                                        <div class="fw-bold" style="font-size: 1.2rem; color: {{ $servicesColor }};">{{ $servicesScore }}%</div>
                                        <small class="text-muted">Services Match</small>
                                    </div>
                                    <div class="col-6 text-center">
                                        <div class="fw-bold" style="font-size: 1.2rem; color: {{ $scoreColor }};">{{ $score }}%</div>
                                        <small class="text-muted">Overall Match</small>
                                    </div>
                                </div>
                                <div class="small text-muted mt-1" style="font-style: italic;">{{ $bid['match_score']['compared_to_label'] }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection
