@extends('layouts.main')
@push('styles')
<style>
    .ml-role-header {
        background: #049399;
        color: #fff;
        padding: 12px 18px;
        border-radius: 8px 8px 0 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .ml-role-header .role-title {
        font-weight: 700;
        font-size: 1rem;
        letter-spacing: .02em;
    }
    .ml-role-card {
        border: 1.5px solid #049399;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 1.75rem;
    }
    .ml-empty {
        padding: 18px 18px;
        color: #888;
        font-size: .9rem;
        background: #fff;
    }
    .ml-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
        font-size: .88rem;
    }
    .ml-table th {
        background: #f4f6f8;
        color: #555;
        font-weight: 600;
        padding: 9px 14px;
        border-bottom: 1px solid #dee2e6;
        text-transform: uppercase;
        font-size: .73rem;
        letter-spacing: .06em;
    }
    .ml-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
    }
    .ml-table tr:last-child td {
        border-bottom: none;
    }
    .ml-table tr:hover td {
        background: #f9fffe;
    }
    .badge-live    { background:#28a745; color:#fff; }
    .badge-pending { background:#f0ad4e; color:#fff; }
    .badge-sold    { background:#e9ecef; color:#333; border:1px solid #ddd; }
    .badge-draft   { background:#adb5bd; color:#fff; }
    .ml-badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        white-space: nowrap;
    }
    .ml-listing-id {
        font-family: monospace;
        font-size: .78rem;
        color: #888;
        white-space: nowrap;
    }
    .ml-address {
        font-weight: 500;
        color: #1a2333;
    }
    .ml-address small {
        display: block;
        color: #888;
        font-weight: 400;
        font-size: .78rem;
    }
    .ml-bids {
        text-align: center;
        font-weight: 600;
        color: #049399;
    }
    .btn-ml-view {
        background: #049399;
        color: #fff;
        border: none;
        border-radius: 5px;
        padding: 4px 14px;
        font-size: .78rem;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
    }
    .btn-ml-view:hover {
        background: #037a80;
        color: #fff;
    }
    .btn-ml-viewall {
        background: rgba(255,255,255,.18);
        color: #fff;
        border: 1.5px solid rgba(255,255,255,.55);
        border-radius: 5px;
        padding: 3px 13px;
        font-size: .76rem;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        transition: background .15s;
    }
    .btn-ml-viewall:hover {
        background: rgba(255,255,255,.32);
        color: #fff;
    }
    .mainDashboard button:not(.choices__item button) {
        background: unset !important;
        color: unset !important;
    }
</style>
@endpush

@php
/**
 * Resolve a mixed-storage boolean-ish value (true, false, 1, 0, 'true', 'false', '1', '0') to PHP bool.
 */
function mlBool($v): bool {
    if (is_bool($v)) return $v;
    return in_array($v, [1, '1', 'true', true], true);
}

/**
 * Return ['label', 'class'] for a listing's status.
 */
function mlStatus($listing): array {
    if (mlBool($listing->is_draft))     return ['Draft',            'badge-draft'];
    if (!mlBool($listing->is_approved)) return ['Pending Approval', 'badge-pending'];
    if (mlBool($listing->is_sold))      return ['Sold',             'badge-sold'];
    return ['Live', 'badge-live'];
}

$roles = [
    [
        'key'       => 'tenant',
        'label'     => "Tenant's Agent",
        'icon'      => 'fa-solid fa-home',
        'listings'  => $tenantListings,
        'listRoute' => 'tenant.agent.auctions.list',
        'viewRoute' => 'tenant.agent.auction.view',
    ],
    [
        'key'       => 'landlord',
        'label'     => "Landlord's Agent",
        'icon'      => 'fa-solid fa-building',
        'listings'  => $landlordListings,
        'listRoute' => 'landlord.agent.auctions.list',
        'viewRoute' => 'landlord.agent.auction.view',
    ],
    [
        'key'       => 'buyer',
        'label'     => "Buyer's Agent",
        'icon'      => 'fa-solid fa-user',
        'listings'  => $buyerListings,
        'listRoute' => 'buyer.agent.auctions.list',
        'viewRoute' => 'buyer.view-auction',
    ],
    [
        'key'       => 'seller',
        'label'     => "Seller's Agent",
        'icon'      => 'fa-solid fa-sign-out',
        'listings'  => $sellerListings,
        'listRoute' => 'hireSellerAgentHireAuctions',
        'viewRoute' => 'seller.agent.auction.detail',
    ],
];

$grandTotal = $tenantListings->count() + $landlordListings->count() + $buyerListings->count() + $sellerListings->count();
@endphp

@section('content')
<div class="mainDashboard">
    <div class="container">
        <div class="dashboardContentDetails mt-3">
            <div class="card">
                <div class="row">
                    @include('layouts.partials.sidenav')

                    <div class="rightCol col-sm-12 col-md-8 col-lg-8">
                        <div class="container mt-4 mb-5">

                            <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-2">
                                <div>
                                    <h4 class="fw-bold mb-0">My Listings</h4>
                                    <p class="text-muted small mb-0">All listings you've created across every role.</p>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    @if($grandTotal > 0)
                                        <span class="badge bg-secondary px-3 py-2" style="font-size:.75rem;">{{ $grandTotal }} total</span>
                                    @endif
                                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
                                </div>
                            </div>
                            <hr class="mt-2 mb-4">

                            @if($grandTotal === 0)
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-folder-open" style="font-size:2.5rem;opacity:.35;"></i>
                                    <p class="mt-3 mb-1 fw-semibold">No listings yet</p>
                                    <p class="small">Use <strong>+ New Listing</strong> on the dashboard to create your first listing.</p>
                                    <a href="{{ route('dashboard') }}" class="btn btn-sm mt-1" style="background:#049399;color:#fff;">Go to Dashboard</a>
                                </div>
                            @else

                                @foreach($roles as $role)
                                @php $listings = $role['listings']; @endphp

                                <div class="ml-role-card">
                                    <div class="ml-role-header">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="{{ $role['icon'] }}" style="font-size:1rem;opacity:.85;"></i>
                                            <span class="role-title">{{ $role['label'] }}</span>
                                            <span class="badge bg-white text-dark ms-1" style="font-size:.72rem;">{{ $listings->count() }}</span>
                                        </div>
                                        <a href="{{ route($role['listRoute']) }}" class="btn-ml-viewall">View All →</a>
                                    </div>

                                    @if($listings->isEmpty())
                                        <div class="ml-empty">No {{ $role['label'] }} listings yet.</div>
                                    @else
                                        <div style="overflow-x:auto;">
                                            <table class="ml-table">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Address / Title</th>
                                                        <th>Status</th>
                                                        <th style="text-align:center;">Bids</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($listings as $listing)
                                                    @php
                                                        [$statusLabel, $statusClass] = mlStatus($listing);
                                                        $displayId = $listing->listing_id ?? ('#' . $listing->id);
                                                        $address   = trim($listing->address ?? '');
                                                        $titleText = trim($listing->title ?? '');
                                                    @endphp
                                                    <tr>
                                                        <td class="ml-listing-id">{{ $displayId }}</td>
                                                        <td class="ml-address">
                                                            {{ $address ?: ($titleText ?: '—') }}
                                                            @if($address && $titleText && $titleText !== $address)
                                                                <small>{{ $titleText }}</small>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <span class="ml-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                                        </td>
                                                        <td class="ml-bids">{{ $listing->bids_count }}</td>
                                                        <td>
                                                            <a href="{{ route($role['viewRoute'], $listing->id) }}" class="btn-ml-view">View</a>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
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
@endsection
