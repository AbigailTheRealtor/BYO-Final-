@extends('layouts.admin')
@section('content')

<div class="card">
    <div class="card-header">
        <h5>Offer Listings</h5>
    </div>

    @if(session('success'))
        <div class="alert alert-success mx-3 mt-3">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mx-3 mt-3">{{ session('error') }}</div>
    @endif

    <ul class="nav nav-tabs border-tab px-3 pt-2" role="tablist">
        <li class="nav-item">
            <a href="{{ route('admin.offerListings') }}?type=0"
               class="nav-link {{ $type == '0' ? 'active' : '' }}">
                <i class="icofont icofont-close"></i> Pending Review
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.offerListings') }}?type=1"
               class="nav-link {{ $type == '1' ? 'active' : '' }}">
                <i class="icofont icofont-check"></i> Approved
            </a>
        </li>
    </ul>

    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>#</th>
                    <th>Agent</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Address</th>
                    <th>Submitted</th>
                    @if($type == '0')
                    <th>Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($listings as $listing)
                    @php $meta = $listing->get; @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $listing->user->name ?? '—' }}</td>
                        <td>{{ $listing->title ?? '—' }}</td>
                        <td>{{ ucfirst($meta->offer_type ?? '—') }}</td>
                        <td>{{ $meta->property_address ?? '—' }}</td>
                        <td>
                            @if($listing->created_at)
                                {{ \Carbon\Carbon::parse($listing->created_at)->format('M j, Y') }}
                            @else
                                N/A
                            @endif
                        </td>
                        @if($type == '0')
                        <td>
                            <form action="{{ route('admin.offerListing.approve', $listing->id) }}"
                                  method="POST" class="d-inline"
                                  onsubmit="return confirm('Approve this listing?')">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-success">Approve</button>
                            </form>
                            <form action="{{ route('admin.offerListing.reject', $listing->id) }}"
                                  method="POST" class="d-inline ms-1"
                                  onsubmit="return confirm('Reject and return this listing to draft?')">
                                @csrf
                                <button type="submit" class="btn btn-xs btn-danger">Reject</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $type == '0' ? 7 : 6 }}" class="text-center text-muted py-4">
                            No listings found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
