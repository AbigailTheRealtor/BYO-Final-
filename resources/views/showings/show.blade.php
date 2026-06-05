@extends('layouts.main')

@section('title', 'Showing Detail')

@push('styles')
<style>
.showing-detail .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 0.35rem 0.85rem;
    border-radius: 20px;
    border: 1px solid;
    text-transform: capitalize;
}
.showing-detail .status-requested { background:#fffbeb; color:#b45309; border-color:#fde68a; }
.showing-detail .status-approved  { background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
.showing-detail .status-declined  { background:#fff1f2; color:#be123c; border-color:#fecdd3; }
.showing-detail .status-canceled  { background:#f1f5f9; color:#64748b; border-color:#e2e8f0; }
.showing-detail .status-completed { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }

.showing-detail .detail-card {
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:0.75rem;
    padding:1.5rem;
    margin-bottom:1.25rem;
    box-shadow:0 1px 4px rgba(0,0,0,.05);
}
.showing-detail .detail-label {
    font-size:0.72rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:#94a3b8;
    margin-bottom:0.2rem;
}
.showing-detail .detail-value {
    font-size:0.9rem;
    color:#1e293b;
}
.showing-detail .timeline-item {
    display:flex;
    gap:0.75rem;
    align-items:flex-start;
    padding:0.5rem 0;
    border-bottom:1px solid #f1f5f9;
}
.showing-detail .timeline-item:last-child { border-bottom:none; }
.showing-detail .timeline-dot {
    width:10px;
    height:10px;
    border-radius:50%;
    margin-top:4px;
    flex-shrink:0;
}
.showing-detail .dot-requested  { background:#b45309; }
.showing-detail .dot-approved   { background:#15803d; }
.showing-detail .dot-declined   { background:#be123c; }
.showing-detail .dot-canceled   { background:#64748b; }
.showing-detail .dot-completed  { background:#1d4ed8; }
.showing-detail .dot-pending    { background:#cbd5e1; border:1px solid #e2e8f0; }
</style>
@endpush

@section('content')
@php
    $listingMetas = [];
    if ($showing->offerAuction) {
        foreach ($showing->offerAuction->metas as $m) {
            $listingMetas[$m->meta_key] = $m->meta_value;
        }
    }
    $address     = $listingMetas['address']       ?? null;
    $city        = $listingMetas['property_city'] ?? null;
    $state       = $listingMetas['property_state'] ?? null;
    $zip         = $listingMetas['property_zip']  ?? $listingMetas['zip_code'] ?? null;
    $addrParts   = array_filter([$address, $city]);
    $stateZip    = trim($state . ($state && $zip ? ' ' : '') . $zip);
    if ($stateZip) $addrParts[] = $stateZip;
    $fullAddress  = implode(', ', array_filter($addrParts));
    $listingTitle = $listingMetas['listing_title'] ?? ($showing->offerAuction->title ?? null);
    $displayLabel = $fullAddress ?: $listingTitle ?: ('Listing #' . $showing->offer_auction_id);
    $role         = $listingMetas['user_type'] ?? null;

    $statusIcons = [
        'requested' => 'fa-clock',
        'approved'  => 'fa-circle-check',
        'declined'  => 'fa-circle-xmark',
        'canceled'  => 'fa-ban',
        'completed' => 'fa-flag-checkered',
    ];
    $statusIcon = $statusIcons[$showing->status] ?? 'fa-clock';

    $fmtTime = function($t) {
        if (!$t) return null;
        try { return \Carbon\Carbon::createFromTimeString($t)->format('g:i A'); }
        catch(\Exception $e) { return $t; }
    };

    $authUser      = Auth::user();
    $isOwnerOrAgent = $showing->offerAuction && (
        (int) $showing->offerAuction->user_id === (int) $authUser->id
        || \App\Models\UserAgent::where('user_id', $showing->offerAuction->user_id)
                ->where('agent_id', $authUser->id)->exists()
    );
    $isRequester    = (int) $showing->requester_id === (int) $authUser->id;

    // Listing view route — best effort based on role
    $listingViewRoute = null;
    if ($showing->offerAuction) {
        try {
            if ($role === 'seller') {
                $listingViewRoute = route('offer.listing.seller.searchListing');
            } elseif ($role === 'landlord') {
                $listingViewRoute = route('offer.listing.landlord.searchListing');
            }
        } catch (\Exception $e) {}
    }
@endphp

<div class="container py-4 showing-detail" style="max-width:860px;">

    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0" style="font-size:.8rem;">
            <li class="breadcrumb-item">
                @if($isRequester)
                    <a href="{{ route('showings.index') }}">My Showings</a>
                @else
                    <a href="{{ route('showings.manage') }}">Showing Requests</a>
                @endif
            </li>
            <li class="breadcrumb-item active">Showing Detail</li>
        </ol>
    </nav>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-1" style="color:#1e293b;">Showing Detail</h4>
            @if($listingViewRoute)
                <a href="{{ $listingViewRoute }}" class="text-muted" style="font-size:.85rem;">
                    <i class="fa-solid fa-location-dot me-1" style="color:#2563eb;"></i>{{ $displayLabel }}
                </a>
            @else
                <span class="text-muted" style="font-size:.85rem;">
                    <i class="fa-solid fa-location-dot me-1" style="color:#2563eb;"></i>{{ $displayLabel }}
                </span>
            @endif
        </div>
        <span class="status-badge status-{{ $showing->status }}">
            <i class="fa-solid {{ $statusIcon }}"></i>
            {{ ucfirst($showing->status) }}
        </span>
    </div>

    <div class="row g-3">

        {{-- Left column: main info + messages --}}
        <div class="col-md-7">

            {{-- Requester info --}}
            <div class="detail-card">
                <div class="mb-3">
                    <div class="detail-label">Requester</div>
                    <div class="detail-value fw-semibold">
                        {{ $showing->requester->first_name ?? '' }} {{ $showing->requester->last_name ?? '' }}
                        @if($showing->requested_by_agent)
                            <span class="badge bg-secondary ms-1" style="font-size:.65rem;">Agent</span>
                        @endif
                    </div>
                </div>

                {{-- Requested date/time --}}
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="detail-label">Requested Date</div>
                        <div class="detail-value">
                            @if($showing->requested_date)
                                <i class="fa-regular fa-calendar me-1 text-muted"></i>
                                {{ \Carbon\Carbon::parse($showing->requested_date)->format('M j, Y') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="detail-label">Requested Time</div>
                        <div class="detail-value">
                            @php $rs = $fmtTime($showing->requested_start_time); $re = $fmtTime($showing->requested_end_time); @endphp
                            @if($rs && $re)
                                <i class="fa-regular fa-clock me-1 text-muted"></i>
                                {{ $rs }} – {{ $re }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Confirmed date/time (if approved) --}}
                @if($showing->isApproved() && $showing->approved_date)
                    <hr class="my-3">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="detail-label" style="color:#15803d;">Confirmed Date</div>
                            <div class="detail-value fw-semibold" style="color:#15803d;">
                                <i class="fa-solid fa-calendar-check me-1"></i>
                                {{ \Carbon\Carbon::parse($showing->approved_date)->format('M j, Y') }}
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="detail-label" style="color:#15803d;">Confirmed Time</div>
                            <div class="detail-value fw-semibold" style="color:#15803d;">
                                @php $as = $fmtTime($showing->approved_start_time); $ae = $fmtTime($showing->approved_end_time); @endphp
                                @if($as && $ae)
                                    <i class="fa-regular fa-clock me-1"></i>
                                    {{ $as }} – {{ $ae }}
                                @else
                                    <span class="text-muted fw-normal">—</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Messages --}}
            @if($showing->requester_message || $showing->owner_message)
            <div class="detail-card">
                @if($showing->requester_message)
                    <div class="{{ $showing->owner_message ? 'mb-3' : '' }}">
                        <div class="detail-label">Requester's Message</div>
                        <div class="detail-value" style="white-space:pre-wrap;">{{ $showing->requester_message }}</div>
                    </div>
                @endif
                @if($showing->owner_message)
                    <div>
                        <div class="detail-label">Owner's Message</div>
                        <div class="detail-value" style="white-space:pre-wrap;">{{ $showing->owner_message }}</div>
                    </div>
                @endif
            </div>
            @endif

            {{-- Action buttons --}}
            @if($showing->isRequested() || $showing->isApproved())
            <div class="detail-card">
                <div class="detail-label mb-2">Actions</div>
                <div class="d-flex flex-wrap gap-2">

                    @if($isOwnerOrAgent && $showing->isRequested())
                        <button class="btn btn-sm btn-success"
                                data-bs-toggle="modal"
                                data-bs-target="#approveModal-{{ $showing->id }}">
                            <i class="fa-solid fa-check me-1"></i>Approve
                        </button>
                        <button class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal"
                                data-bs-target="#declineModal-{{ $showing->id }}">
                            <i class="fa-solid fa-xmark me-1"></i>Decline
                        </button>
                    @endif

                    @if($isOwnerOrAgent && $showing->isApproved())
                        <form method="POST" action="{{ route('showings.complete', $showing) }}"
                              onsubmit="return confirm('Mark this showing as completed?')">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-primary">
                                <i class="fa-solid fa-circle-check me-1"></i>Mark Complete
                            </button>
                        </form>
                    @endif

                    @if($isRequester || $isOwnerOrAgent)
                        <form method="POST" action="{{ route('showings.cancel', $showing) }}"
                              onsubmit="return confirm('Cancel this showing?')">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-ban me-1"></i>Cancel
                            </button>
                        </form>
                    @endif

                </div>
            </div>

            {{-- Approve / Decline modals --}}
            @if($isOwnerOrAgent && $showing->isRequested())
                @include('showings._approve-form', ['showing' => $showing])
                @include('showings._decline-form', ['showing' => $showing])
            @endif
            @endif

        </div>{{-- /left col --}}

        {{-- Right column: status timeline --}}
        <div class="col-md-5">
            <div class="detail-card">
                <div class="detail-label mb-3">Status History</div>

                {{-- Requested --}}
                <div class="timeline-item">
                    <div class="timeline-dot dot-requested"></div>
                    <div>
                        <div style="font-size:.82rem;font-weight:600;color:#b45309;">Requested</div>
                        <div style="font-size:.75rem;color:#64748b;">
                            {{ $showing->created_at->format('M j, Y g:i A') }}
                        </div>
                    </div>
                </div>

                {{-- Approved --}}
                @if($showing->approved_date)
                <div class="timeline-item">
                    <div class="timeline-dot dot-approved"></div>
                    <div>
                        <div style="font-size:.82rem;font-weight:600;color:#15803d;">Approved</div>
                        <div style="font-size:.75rem;color:#64748b;">
                            Confirmed for {{ \Carbon\Carbon::parse($showing->approved_date)->format('M j, Y') }}
                        </div>
                    </div>
                </div>
                @elseif($showing->isApproved())
                <div class="timeline-item">
                    <div class="timeline-dot dot-approved"></div>
                    <div>
                        <div style="font-size:.82rem;font-weight:600;color:#15803d;">Approved</div>
                        <div style="font-size:.75rem;color:#64748b;">Date from request</div>
                    </div>
                </div>
                @elseif(!$showing->isDeclined() && !$showing->isCanceled())
                <div class="timeline-item">
                    <div class="timeline-dot dot-pending"></div>
                    <div>
                        <div style="font-size:.82rem;color:#94a3b8;">Awaiting approval…</div>
                    </div>
                </div>
                @endif

                {{-- Declined --}}
                @if($showing->isDeclined())
                <div class="timeline-item">
                    <div class="timeline-dot dot-declined"></div>
                    <div>
                        <div style="font-size:.82rem;font-weight:600;color:#be123c;">Declined</div>
                        @if($showing->owner_message)
                            <div style="font-size:.75rem;color:#64748b;">{{ Str::limit($showing->owner_message, 80) }}</div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Canceled --}}
                @if($showing->canceled_at)
                <div class="timeline-item">
                    <div class="timeline-dot dot-canceled"></div>
                    <div>
                        <div style="font-size:.82rem;font-weight:600;color:#64748b;">Canceled</div>
                        <div style="font-size:.75rem;color:#64748b;">
                            {{ $showing->canceled_at->format('M j, Y g:i A') }}
                        </div>
                    </div>
                </div>
                @endif

                {{-- Completed --}}
                @if($showing->completed_at)
                <div class="timeline-item">
                    <div class="timeline-dot dot-completed"></div>
                    <div>
                        <div style="font-size:.82rem;font-weight:600;color:#1d4ed8;">Completed</div>
                        <div style="font-size:.75rem;color:#64748b;">
                            {{ $showing->completed_at->format('M j, Y g:i A') }}
                        </div>
                    </div>
                </div>
                @endif

            </div>{{-- /timeline card --}}

            {{-- Listing info --}}
            <div class="detail-card">
                <div class="detail-label mb-2">Listing</div>
                <div class="detail-value fw-semibold mb-1">{{ $displayLabel }}</div>
                @if($role)
                    <div style="font-size:.78rem;color:#64748b;">
                        <i class="fa-solid fa-tag me-1"></i>{{ ucfirst($role) }} listing
                    </div>
                @endif
                @if($listingViewRoute)
                    <a href="{{ $listingViewRoute }}" class="btn btn-outline-secondary btn-sm mt-2" style="font-size:.75rem;">
                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>View Listing
                    </a>
                @endif
            </div>

        </div>{{-- /right col --}}

    </div>{{-- /row --}}

</div>
@endsection
