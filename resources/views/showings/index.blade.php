@extends('layouts.main')

@push('styles')
<style>
.showings-page .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 0.25rem 0.65rem;
    border-radius: 20px;
    border: 1px solid;
    white-space: nowrap;
    text-transform: capitalize;
}
.showings-page .status-requested  { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.showings-page .status-approved   { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.showings-page .status-declined   { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
.showings-page .status-canceled   { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; }
.showings-page .status-completed  { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }

.showings-page .showing-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
    transition: box-shadow .15s;
}
.showings-page .showing-card:hover {
    box-shadow: 0 4px 14px rgba(0,0,0,.09);
}
.showings-page .showing-card .showing-address {
    font-weight: 700;
    color: #1e293b;
    font-size: 0.975rem;
}
.showings-page .showing-card .showing-meta {
    font-size: 0.82rem;
    color: #64748b;
    margin-top: 0.25rem;
}
.showings-page .showing-card .showing-meta i {
    width: 16px;
    text-align: center;
    color: #94a3b8;
}
.showings-page .empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #64748b;
}
.showings-page .empty-state i {
    font-size: 3rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
    display: block;
}
</style>
@endpush

@section('content')
<div class="container py-4 showings-page">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1 fw-bold" style="color:#1e293b;">My Showings</h2>
            <p class="text-muted mb-0" style="font-size:.9rem;">Showing requests you have submitted</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('offer.listing.seller.searchListing') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-house me-1"></i>Browse Listings
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($showings->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-calendar-xmark"></i>
            <h5 class="fw-semibold mb-2" style="color:#334155;">No showing requests yet</h5>
            <p style="font-size:.9rem;max-width:360px;margin:0 auto 1.5rem;">
                When you request a showing on a property listing, it will appear here.
            </p>
            <a href="{{ route('offer.listing.seller.searchListing') }}" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-magnifying-glass me-1"></i>Find Properties
            </a>
        </div>
    @else
        @foreach($showings as $showing)
            @php
                $listingMetas = [];
                if ($showing->offerAuction) {
                    foreach ($showing->offerAuction->metas as $m) {
                        $listingMetas[$m->meta_key] = $m->meta_value;
                    }
                }
                $address     = $listingMetas['address'] ?? null;
                $city        = $listingMetas['property_city'] ?? null;
                $state       = $listingMetas['property_state'] ?? null;
                $zip         = $listingMetas['property_zip'] ?? $listingMetas['zip_code'] ?? null;
                $addrParts   = array_filter([$address, $city]);
                $stateZip    = trim($state . ($state && $zip ? ' ' : '') . $zip);
                if ($stateZip) $addrParts[] = $stateZip;
                $fullAddress = implode(', ', array_filter($addrParts));
                $listingTitle = $listingMetas['listing_title'] ?? ($showing->offerAuction->title ?? null);
                $displayLabel = $fullAddress ?: $listingTitle ?: ('Listing #' . ($showing->offer_auction_id));
                $role        = $listingMetas['user_type'] ?? null;

                $statusColors = [
                    'requested' => 'requested',
                    'approved'  => 'approved',
                    'declined'  => 'declined',
                    'canceled'  => 'canceled',
                    'completed' => 'completed',
                ];
                $statusClass = $statusColors[$showing->status] ?? 'requested';

                $statusIcons = [
                    'requested' => 'fa-clock',
                    'approved'  => 'fa-circle-check',
                    'declined'  => 'fa-circle-xmark',
                    'canceled'  => 'fa-ban',
                    'completed' => 'fa-flag-checkered',
                ];
                $statusIcon = $statusIcons[$showing->status] ?? 'fa-clock';

                $requestedDate = $showing->requested_date
                    ? \Carbon\Carbon::parse($showing->requested_date)->format('M j, Y')
                    : null;

                $fmtTime = function($t) {
                    if (!$t) return null;
                    try { return \Carbon\Carbon::createFromTimeString($t)->format('g:i A'); }
                    catch(\Exception $e) { return $t; }
                };
                $startTime = $fmtTime($showing->requested_start_time);
                $endTime   = $fmtTime($showing->requested_end_time);
            @endphp

            <div class="showing-card">
                <a href="{{ route('showings.show', $showing) }}" class="stretched-link" style="text-decoration:none;"></a>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2" style="position:relative;z-index:1;">
                    <div>
                        <div class="showing-address">
                            <i class="fa-solid fa-location-dot me-1" style="color:#2563eb;font-size:.85rem;"></i>
                            {{ $displayLabel }}
                        </div>

                        @if($role)
                            <div class="showing-meta" style="margin-top:.2rem;">
                                <i class="fa-solid fa-tag"></i>
                                {{ ucfirst($role) }} listing
                            </div>
                        @endif

                        <div class="showing-meta mt-2">
                            @if($requestedDate)
                                <span>
                                    <i class="fa-regular fa-calendar"></i>
                                    {{ $requestedDate }}
                                </span>
                                @if($startTime && $endTime)
                                    &nbsp;&middot;&nbsp;
                                    <span>
                                        <i class="fa-regular fa-clock"></i>
                                        {{ $startTime }} – {{ $endTime }}
                                    </span>
                                @endif
                            @endif
                        </div>

                        @if($showing->requester_message)
                            <div class="showing-meta mt-1" style="font-style:italic;color:#94a3b8;">
                                <i class="fa-regular fa-comment"></i>
                                "{{ Str::limit($showing->requester_message, 120) }}"
                            </div>
                        @endif
                    </div>

                    <div class="d-flex flex-column align-items-end gap-2">
                        <span class="status-badge status-{{ $statusClass }}">
                            <i class="fa-solid {{ $statusIcon }}"></i>
                            {{ ucfirst($showing->status) }}
                        </span>
                        <div style="font-size:.72rem;color:#94a3b8;">
                            {{ $showing->created_at->format('M j, Y') }}
                        </div>
                    </div>
                </div>

                @if($showing->owner_message)
                    <div class="mt-3 p-3" style="background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;">
                        <div style="font-size:.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;">
                            <i class="fa-solid fa-reply me-1"></i>Owner's Response
                        </div>
                        <div style="font-size:.875rem;color:#334155;">{{ $showing->owner_message }}</div>
                        @if($showing->approved_date)
                            @php
                                $approvedDate  = \Carbon\Carbon::parse($showing->approved_date)->format('M j, Y');
                                $approvedStart = $fmtTime($showing->approved_start_time);
                                $approvedEnd   = $fmtTime($showing->approved_end_time);
                            @endphp
                            <div class="mt-1" style="font-size:.78rem;color:#15803d;font-weight:600;">
                                <i class="fa-solid fa-calendar-check me-1"></i>
                                Confirmed: {{ $approvedDate }}
                                @if($approvedStart && $approvedEnd)
                                    {{ $approvedStart }} – {{ $approvedEnd }}
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach

        <div class="d-flex justify-content-center mt-4">
            {{ $showings->links() }}
        </div>
    @endif

</div>
@endsection
