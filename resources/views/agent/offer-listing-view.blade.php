@extends('layouts.main')
@section('content')
<div class="container-fluid py-4" style="max-width:900px;">

    {{-- Breadcrumb + Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <a href="{{ $data['hub_route'] }}" class="text-muted small text-decoration-none">
                <i class="fa-solid fa-arrow-left me-1"></i>My Offer Listings
            </a>
            <h4 class="fw-bold mb-0 mt-1">
                <i class="fa-solid fa-file-lines me-2" style="color:#049399;"></i>
                {{ $data['title'] ?: ('Offer Listing #' . $data['id']) }}
            </h4>
            <div class="d-flex align-items-center gap-2 mt-1">
                <code class="small" style="color:#049399;">{{ $data['listing_id'] }}</code>
                <span class="badge bg-{{ $data['status_class'] }}">{{ $data['status_label'] }}</span>
                @php
                    $otLabels = ['sale' => 'Sale', 'rental' => 'Rental', 'lease' => 'Lease'];
                    $otColors = ['sale' => 'bg-info', 'rental' => 'bg-primary', 'lease' => ''];
                    $ot = $data['offer_type'];
                @endphp
                @if($ot)
                <span class="badge {{ $otColors[$ot] ?? 'bg-secondary' }}" style="{{ $ot === 'lease' ? 'background:#6f42c1;' : '' }}">
                    {{ $otLabels[$ot] ?? ucfirst($ot) }}
                </span>
                @endif
            </div>
        </div>
        <a href="{{ $data['edit_route'] }}" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing
        </a>
    </div>

    {{-- Property Details --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold py-3" style="border-bottom:1px solid #f0f0f0;">
            <i class="fa-solid fa-home me-2" style="color:#049399;"></i>Property Details
        </div>
        <div class="card-body">
            <div class="row g-3">
                @if($data['property_address'])
                <div class="col-12">
                    <div class="text-muted small mb-1">Address</div>
                    <div class="fw-semibold">
                        {{ $data['property_address'] }}
                        @if($data['city'] || $data['state'] || $data['zip_code'])
                            <span class="text-muted fw-normal">,
                                {{ implode(', ', array_filter([$data['city'], $data['state'], $data['zip_code']])) }}
                            </span>
                        @endif
                    </div>
                </div>
                @endif
                @if($data['property_type'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Property Type</div>
                    <div>{{ ucwords(str_replace('_', ' ', $data['property_type'])) }}</div>
                </div>
                @endif
                @if($data['bedrooms'])
                <div class="col-md-2">
                    <div class="text-muted small mb-1">Beds</div>
                    <div>{{ $data['bedrooms'] }}</div>
                </div>
                @endif
                @if($data['bathrooms'])
                <div class="col-md-2">
                    <div class="text-muted small mb-1">Baths</div>
                    <div>{{ $data['bathrooms'] }}</div>
                </div>
                @endif
                @if($data['sqft'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Sq Ft</div>
                    <div>{{ number_format($data['sqft']) }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Financial Terms --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold py-3" style="border-bottom:1px solid #f0f0f0;">
            <i class="fa-solid fa-dollar-sign me-2" style="color:#049399;"></i>Financial Terms
        </div>
        <div class="card-body">
            <div class="row g-3">
                @if($data['offer_price'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Offer Price</div>
                    <div class="fw-semibold">${{ number_format($data['offer_price']) }}</div>
                </div>
                @endif
                @if($data['monthly_rent'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Monthly Rent</div>
                    <div class="fw-semibold">${{ number_format($data['monthly_rent']) }}/mo</div>
                </div>
                @endif
                @if($data['earnest_deposit'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Earnest Deposit</div>
                    <div>${{ number_format($data['earnest_deposit']) }}</div>
                </div>
                @endif
                @if($data['financing_type'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Financing Type</div>
                    <div>{{ ucwords(str_replace('_', ' ', $data['financing_type'])) }}</div>
                </div>
                @endif
                @if($data['down_payment_percent'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Down Payment</div>
                    <div>{{ $data['down_payment_percent'] }}%</div>
                </div>
                @endif
                @if($data['security_deposit'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Security Deposit</div>
                    <div>${{ number_format($data['security_deposit']) }}</div>
                </div>
                @endif
                @if($data['lease_term_months'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Lease Term</div>
                    <div>{{ $data['lease_term_months'] }} months</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Contingencies --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold py-3" style="border-bottom:1px solid #f0f0f0;">
            <i class="fa-solid fa-square-check me-2" style="color:#049399;"></i>Contingencies
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Financing Contingency</div>
                    @if($data['financing_contingency'])
                        <span class="badge bg-success">Yes</span>
                        @if($data['financing_contingency_days'])
                            <span class="text-muted small ms-1">{{ $data['financing_contingency_days'] }} days</span>
                        @endif
                    @else
                        <span class="badge bg-secondary">No</span>
                    @endif
                </div>
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Inspection Contingency</div>
                    @if($data['inspection_contingency'])
                        <span class="badge bg-success">Yes</span>
                        @if($data['inspection_contingency_days'])
                            <span class="text-muted small ms-1">{{ $data['inspection_contingency_days'] }} days</span>
                        @endif
                    @else
                        <span class="badge bg-secondary">No</span>
                    @endif
                </div>
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Appraisal Contingency</div>
                    @if($data['appraisal_contingency'])
                        <span class="badge bg-success">Yes</span>
                    @else
                        <span class="badge bg-secondary">No</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Key Dates --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold py-3" style="border-bottom:1px solid #f0f0f0;">
            <i class="fa-solid fa-calendar me-2" style="color:#049399;"></i>Key Dates
        </div>
        <div class="card-body">
            <div class="row g-3">
                @if($data['closing_date'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Closing Date</div>
                    <div>{{ \Carbon\Carbon::parse($data['closing_date'])->format('F j, Y') }}</div>
                </div>
                @endif
                @if($data['possession_date'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Possession Date</div>
                    <div>{{ \Carbon\Carbon::parse($data['possession_date'])->format('F j, Y') }}</div>
                </div>
                @endif
                @if($data['listing_expiration'])
                <div class="col-md-4">
                    <div class="text-muted small mb-1">Listing Expiration</div>
                    <div>{{ \Carbon\Carbon::parse($data['listing_expiration'])->format('F j, Y') }}</div>
                </div>
                @endif
                @if(!$data['closing_date'] && !$data['possession_date'] && !$data['listing_expiration'])
                <div class="col-12 text-muted small">No dates specified.</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Additional Terms --}}
    @if($data['custom_terms'] || $data['notes'])
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold py-3" style="border-bottom:1px solid #f0f0f0;">
            <i class="fa-solid fa-file-lines me-2" style="color:#049399;"></i>Additional Terms &amp; Notes
        </div>
        <div class="card-body">
            @if($data['custom_terms'])
            <div class="mb-3">
                <div class="text-muted small mb-1">Custom Terms</div>
                <div class="border rounded p-3 bg-light small" style="white-space:pre-wrap;">{{ $data['custom_terms'] }}</div>
            </div>
            @endif
            @if($data['notes'])
            <div>
                <div class="text-muted small mb-1">Notes</div>
                <div class="border rounded p-3 bg-light small" style="white-space:pre-wrap;">{{ $data['notes'] }}</div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Footer actions --}}
    <div class="d-flex gap-2 justify-content-end">
        <a href="{{ $data['hub_route'] }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to My Offer Listings
        </a>
        <a href="{{ $data['edit_route'] }}" class="btn btn-sm text-white" style="background:#049399;">
            <i class="fa-solid fa-pen-to-square me-1"></i>Edit Listing
        </a>
    </div>

</div>
@endsection
