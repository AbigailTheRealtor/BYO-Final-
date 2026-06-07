@extends('layouts.admin')
@section('content')
<div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
    <a href="{{ route('admin.dna.location.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Location DNA Index</a>
    @if($listingType === 'seller')
        <a href="{{ route('admin.dna.profiles.seller', $listingId) }}" class="btn btn-sm btn-outline-primary">View Seller DNA Profile</a>
    @elseif($listingType === 'landlord')
        <a href="{{ route('admin.dna.profiles.landlord', $listingId) }}" class="btn btn-sm btn-outline-primary">View Landlord DNA Profile</a>
    @endif
    <span class="text-muted small">/ Location DNA — {{ $listingType }} / Listing {{ $listingId }}</span>
</div>

@include('admin.dna.partials.location-dna-card', ['dna' => $dna, 'pois' => $pois])
@endsection
