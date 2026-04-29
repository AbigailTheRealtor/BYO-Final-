@extends('layouts.main')
@section('content')
<div class="container-fluid py-4">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fa-solid fa-file-lines me-2" style="color:#049399;"></i>My Offer Listings</h4>
            <p class="text-muted small mb-0">All your offer, rental, and lease listings in one place.</p>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm text-white dropdown-toggle" style="background:#049399;" type="button" data-bs-toggle="dropdown">
                <i class="fa-solid fa-plus me-1"></i>New Offer Listing
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('offer.listing.create', ['offer_type' => 'sale']) }}"><i class="fa-solid fa-tag me-2 text-muted"></i>Sale (Purchase Offer)</a></li>
                <li><a class="dropdown-item" href="{{ route('offer.listing.create', ['offer_type' => 'rental']) }}"><i class="fa-solid fa-home me-2 text-muted"></i>Rental Offer</a></li>
                <li><a class="dropdown-item" href="{{ route('offer.listing.create', ['offer_type' => 'lease']) }}"><i class="fa-solid fa-key me-2 text-muted"></i>Lease Offer</a></li>
            </ul>
        </div>
    </div>

    {{-- Filter tabs --}}
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        @foreach(['all' => 'All', 'active' => 'Active', 'pending' => 'Pending', 'draft' => 'Draft', 'accepted' => 'Accepted', 'expired' => 'Expired'] as $key => $label)
        <a href="{{ route('agent.offer-listings', ['filter' => $key]) }}"
            class="btn btn-sm {{ $filter === $key ? 'text-white' : 'btn-outline-secondary' }}"
            style="{{ $filter === $key ? 'background:#049399;border-color:#049399;' : '' }}">
            {{ $label }}
            @if(isset($counts[$key]) && $counts[$key] > 0)
                <span class="badge {{ $filter === $key ? 'bg-white text-dark' : 'bg-secondary' }} ms-1">{{ $counts[$key] }}</span>
            @endif
        </a>
        @endforeach
    </div>

    {{-- Table --}}
    @if($listings->isEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <div style="font-size:2.5rem;color:#ccc;" class="mb-3"><i class="fa-solid fa-file-lines"></i></div>
            <h6 class="text-muted">No offer listings found</h6>
            <p class="text-muted small mb-3">
                @if($filter === 'all')
                    You haven't created any offer listings yet.
                @else
                    No listings match the "{{ ucfirst($filter) }}" filter.
                @endif
            </p>
            <a href="{{ route('offer.listing.create', ['offer_type' => 'sale']) }}" class="btn btn-sm text-white" style="background:#049399;">
                <i class="fa-solid fa-plus me-1"></i>Create Your First Offer Listing
            </a>
        </div>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Listing ID</th>
                        <th>Title / Address</th>
                        <th>Offer Type</th>
                        <th>Price / Rent</th>
                        <th>Closing / Expiry</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($listings as $listing)
                    <tr>
                        <td class="ps-3">
                            <code class="small" style="color:#049399;">{{ $listing['listing_id'] }}</code>
                        </td>
                        <td>
                            <div class="fw-semibold small">{{ \Illuminate\Support\Str::limit($listing['title'], 45) }}</div>
                            @if($listing['address'])
                            <div class="text-muted" style="font-size:.78rem;">{{ $listing['address'] }}{{ $listing['state'] ? ', '.$listing['state'] : '' }}</div>
                            @endif
                        </td>
                        <td>
                            @php $ot = $listing['offer_type'] ?? ''; @endphp
                            @if($ot === 'sale')    <span class="badge bg-info">Sale</span>
                            @elseif($ot === 'rental') <span class="badge bg-primary">Rental</span>
                            @elseif($ot === 'lease')  <span class="badge" style="background:#6f42c1;">Lease</span>
                            @else <span class="badge bg-secondary">—</span>
                            @endif
                        </td>
                        <td class="small">
                            @if($listing['offer_price'])${{ number_format($listing['offer_price']) }}
                            @elseif($listing['monthly_rent'])${{ number_format($listing['monthly_rent']) }}/mo
                            @else <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="small">
                            @if($listing['closing_date'])
                                <div>Close: {{ \Carbon\Carbon::parse($listing['closing_date'])->format('M j, Y') }}</div>
                            @endif
                            @if($listing['expiry'])
                                <div class="text-muted">Exp: {{ \Carbon\Carbon::parse($listing['expiry'])->format('M j, Y') }}</div>
                            @endif
                            @if(!$listing['closing_date'] && !$listing['expiry'])
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $listing['status_class'] }}">{{ $listing['status_label'] }}</span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                @if($listing['_draft'])
                                <a href="{{ $listing['draft_route'] }}" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Continue Draft
                                </a>
                                @else
                                <a href="{{ $listing['view_route'] }}" class="btn btn-outline-primary">
                                    <i class="fa-solid fa-eye me-1"></i>View
                                </a>
                                <a href="{{ $listing['edit_route'] }}" class="btn btn-outline-secondary">
                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
