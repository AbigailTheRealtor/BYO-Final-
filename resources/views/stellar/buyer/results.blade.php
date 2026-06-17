@extends('layouts.main')

@section('title', 'Matched Listings')

@section('content')
<div class="container-fluid py-4">

    {{-- ===================================================================
         Page header
    =================================================================== --}}
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h2 class="mb-0 fw-semibold" style="font-size:1.35rem;">
                <i class="fas fa-search me-2 text-primary"></i>Your Matched Listings
            </h2>
            @if(!$emptyState && $total > 0)
                <small class="text-muted">
                    {{ $total }} listing{{ $total !== 1 ? 's' : '' }} match your criteria &mdash;
                    sorted by <strong>Best Match</strong>
                </small>
            @endif
        </div>
        {{-- Map View placeholder (Phase C hook) --}}
        <button class="btn btn-outline-secondary btn-sm" disabled title="Map view coming soon">
            <i class="fas fa-map me-1"></i>Map View
        </button>
    </div>

    {{-- ===================================================================
         Empty states
    =================================================================== --}}
    @if($emptyState === 'import_unavailable')
        <div class="alert alert-info d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-circle-info fa-lg mt-1 text-info flex-shrink-0"></i>
            <div>
                <strong>Listing data is being set up.</strong><br>
                Please check back shortly.
            </div>
        </div>

    @elseif($emptyState === 'no_inventory')
        <div class="alert alert-info d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-circle-info fa-lg mt-1 text-info flex-shrink-0"></i>
            <div>
                <strong>Stellar MLS listing data is not yet available.</strong><br>
                Check back soon &mdash; our data import runs regularly and new listings will appear here shortly.
            </div>
        </div>

    @elseif($emptyState === 'no_criteria')
        <div class="alert alert-warning d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-triangle-exclamation fa-lg mt-1 text-warning flex-shrink-0"></i>
            <div>
                <strong>Your buyer profile isn't complete yet.</strong><br>
                Set up your home criteria to see matched listings.
                <div class="mt-2">
                    <a href="{{ url('/buyer') }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-sliders me-1"></i>Set Up My Criteria
                    </a>
                </div>
            </div>
        </div>

    @elseif($emptyState === 'no_location')
        <div class="alert alert-warning d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-location-dot fa-lg mt-1 text-warning flex-shrink-0"></i>
            <div>
                <strong>Your search doesn't include a location yet.</strong><br>
                Add your preferred cities, ZIP codes, or draw a custom search area to see matched listings near you.
                <div class="mt-2">
                    <a href="{{ url('/buyer') }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-map-pin me-1"></i>Add My Preferred Locations
                    </a>
                </div>
            </div>
        </div>

    @elseif($emptyState === 'no_matches')
        <div class="alert alert-secondary d-flex align-items-start gap-3 py-3" role="alert">
            <i class="fas fa-house-circle-xmark fa-lg mt-1 flex-shrink-0"></i>
            <div>
                <strong>No active listings match your current search criteria.</strong><br>
                Try widening your price range, expanding your location preferences, or relaxing your minimum bedroom or bathroom requirements.
                <div class="mt-2">
                    <a href="{{ url('/buyer') }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-sliders me-1"></i>Edit My Criteria
                    </a>
                </div>
            </div>
        </div>

    @else
        {{-- ===============================================================
             Results grid
        =============================================================== --}}
        <div class="row row-cols-1 row-cols-lg-2 g-3 mb-4">
            @foreach($results as $index => $card)
                <div class="col">
                    <x-stellar.buyer-result-card
                        :card="$card"
                        :is-top="$index === 0 && $paginator->currentPage() === 1"
                    />
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($paginator->hasPages())
            <div class="d-flex justify-content-center mt-3">
                {{ $paginator->links() }}
            </div>
        @endif
    @endif

</div>
@endsection
