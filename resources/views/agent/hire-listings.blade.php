@extends('layouts.main')

@push('styles')
<style>
.hire-hub-badge {
    font-size: .72rem;
    padding: 3px 8px;
    border-radius: 50px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.hire-hub-badge.bg-primary   { background:#049399 !important; color:#fff; }
.hire-hub-badge.bg-warning   { background:#f0ad4e !important; color:#fff; }
.hire-hub-badge.bg-success   { background:#198754 !important; color:#fff; }
.hire-hub-badge.bg-danger    { background:#dc3545 !important; color:#fff; }
.hire-hub-badge.bg-secondary { background:#6c757d !important; color:#fff; }

.role-badge {
    font-size: .68rem;
    padding: 2px 7px;
    border-radius: 4px;
    background: #e8f7f7;
    color: #049399;
    border: 1px solid #b2e0e2;
    font-weight: 600;
    white-space: nowrap;
}

.hub-filter-tabs .nav-link {
    color: #555;
    border: none;
    border-bottom: 3px solid transparent;
    border-radius: 0;
    padding: 8px 14px;
    font-size: .88rem;
}
.hub-filter-tabs .nav-link.active {
    color: #049399;
    border-bottom-color: #049399;
    background: transparent;
    font-weight: 600;
}
.hub-filter-tabs .nav-link:hover:not(.active) {
    color: #049399;
    border-bottom-color: #ccc;
}

.hub-table th {
    background: #f8f9fa;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #6c757d;
    font-weight: 700;
    border-bottom: 2px solid #dee2e6;
    white-space: nowrap;
}
.hub-table td {
    vertical-align: middle;
    font-size: .9rem;
}
.hub-table tr:hover td {
    background: #f4fdfd;
}
.hub-empty {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}
.hub-empty i { color: #b2e0e2; }

.hub-create-btn {
    background: #049399 !important;
    border-color: #049399 !important;
    color: #fff !important;
    font-weight: 600;
}
.hub-create-btn:hover {
    background: #037a7f !important;
    border-color: #037a7f !important;
}
</style>
@endpush

@section('content')
<div class="mainDashboard">
    <div class="container">
        @include('layouts.partials.dashboard_user_section')

        <div class="dashboardContentDetails mt-3">
            <div class="card">
                <div class="row">
                    @include('layouts.partials.sidenav')

                    <div class="rightCol col-sm-12 col-md-8 col-lg-8">
                        <div class="container mt-4">

                            {{-- Header --}}
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div>
                                    <h4 class="mb-0 fw-bold">My Hire Agent Listings</h4>
                                    <small class="text-muted">All listings you've created across all four agent roles.</small>
                                </div>
                                <div class="dropdown">
                                    <button class="btn hub-create-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        + Create New
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><h6 class="dropdown-header">Choose a role</h6></li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'tenant']) }}">
                                                <i class="fa-solid fa-key me-2 text-muted"></i> Hire Tenant's Agent
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'landlord']) }}">
                                                <i class="fa-solid fa-building me-2 text-muted"></i> Hire Landlord's Agent
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'buyer']) }}">
                                                <i class="fa-solid fa-search me-2 text-muted"></i> Hire Buyer's Agent
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('hire.agent.auction', ['user_type' => 'seller']) }}">
                                                <i class="fa-solid fa-gavel me-2 text-muted"></i> Hire Seller's Agent
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            {{-- Filter Tabs --}}
                            <ul class="nav hub-filter-tabs border-bottom mb-3">
                                @foreach([
                                    'all'     => 'All',
                                    'active'  => 'Active',
                                    'pending' => 'Pending',
                                    'draft'   => 'Draft',
                                    'hired'   => 'Hired',
                                    'expired' => 'Expired',
                                ] as $key => $label)
                                <li class="nav-item">
                                    <a class="nav-link {{ $filter === $key ? 'active' : '' }}"
                                       href="{{ route('agent.hire-listings', ['filter' => $key]) }}">
                                        {{ $label }}
                                        @if($counts[$key] > 0)
                                            <span class="badge rounded-pill ms-1"
                                                  style="background:{{ $filter === $key ? '#049399' : '#dee2e6' }};color:{{ $filter === $key ? '#fff' : '#555' }};font-size:.7rem;">
                                                {{ $counts[$key] }}
                                            </span>
                                        @endif
                                    </a>
                                </li>
                                @endforeach
                            </ul>

                            {{-- Flash Messages --}}
                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            @endif

                            {{-- Table --}}
                            @if($listings->isEmpty())
                                <div class="hub-empty">
                                    <i class="fa-solid fa-clipboard-list fa-4x mb-3 d-block"></i>
                                    <h5>No listings found</h5>
                                    <p>You don't have any listings in this category yet.</p>
                                    <a href="{{ route('hire.agent.auction', ['user_type' => 'tenant']) }}" class="btn hub-create-btn mt-2">
                                        + Create Your First Listing
                                    </a>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table hub-table align-middle">
                                        <thead>
                                            <tr>
                                                <th>Listing ID</th>
                                                <th>Role</th>
                                                <th>Title / Address</th>
                                                <th>Status</th>
                                                <th class="text-center">Bids</th>
                                                <th>Ref %</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($listings as $listing)
                                            <tr>
                                                <td>
                                                    <code style="font-size:.78rem;color:#049399;">{{ $listing['listing_id'] }}</code>
                                                </td>
                                                <td>
                                                    <span class="role-badge">{{ $listing['display_role'] }}</span>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold" style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                                         title="{{ $listing['title'] }}">
                                                        {{ $listing['title'] }}
                                                    </div>
                                                    @if($listing['address'])
                                                        <small class="text-muted" style="font-size:.78rem;">
                                                            {{ $listing['address'] }}{{ $listing['state'] ? ', ' . $listing['state'] : '' }}
                                                        </small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="hire-hub-badge bg-{{ $listing['status_class'] }}">
                                                        {{ $listing['status_label'] }}
                                                    </span>
                                                    @if($listing['auction_type'])
                                                        <br>
                                                        <small class="text-muted" style="font-size:.72rem;">{{ ucfirst($listing['auction_type']) }}</small>
                                                    @endif
                                                    @if($listing['_expired'] && $listing['expiry'])
                                                        <br>
                                                        <small class="text-danger" style="font-size:.72rem;">
                                                            Expired {{ \Carbon\Carbon::parse($listing['expiry'])->diffForHumans() }}
                                                        </small>
                                                    @elseif(!$listing['_draft'] && !$listing['_sold'] && $listing['expiry'])
                                                        <br>
                                                        <small class="text-muted" style="font-size:.72rem;">
                                                            Expires {{ \Carbon\Carbon::parse($listing['expiry'])->diffForHumans() }}
                                                        </small>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span class="fw-bold" style="color:#049399;">{{ $listing['bid_count'] }}</span>
                                                </td>
                                                <td>
                                                    @if($listing['referral_pct'] !== null && $listing['referral_pct'] !== '')
                                                        <span class="text-muted fw-semibold" style="font-size:.85rem;">
                                                            {{ number_format((float)$listing['referral_pct'], 2) }}%
                                                        </span>
                                                    @else
                                                        <span class="text-muted" style="font-size:.8rem;">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        {{ $listing['created_at'] ? \Carbon\Carbon::parse($listing['created_at'])->format('M d, Y') : '—' }}
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1 flex-wrap">
                                                        @if($listing['_draft'])
                                                            <a href="{{ $listing['draft_route'] }}"
                                                               class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;">
                                                                Resume
                                                            </a>
                                                        @else
                                                            <a href="{{ $listing['view_route'] }}"
                                                               class="btn btn-sm btn-outline-primary" style="font-size:.75rem;border-color:#049399;color:#049399;">
                                                                View
                                                            </a>
                                                            <a href="{{ $listing['edit_route'] }}"
                                                               class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;">
                                                                Edit
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
