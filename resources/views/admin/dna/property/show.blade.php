@extends('layouts.admin')
@section('content')
<div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
    <a href="{{ route('admin.dna.property.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Back to Property DNA Index</a>
    @if($current)
        @if($current->listing_type === 'seller')
            <a href="{{ route('admin.dna.profiles.seller', $current->listing_id) }}" class="btn btn-sm btn-outline-primary">View Seller DNA Profile</a>
        @elseif($current->listing_type === 'landlord')
            <a href="{{ route('admin.dna.profiles.landlord', $current->listing_id) }}" class="btn btn-sm btn-outline-primary">View Landlord DNA Profile</a>
        @endif
    @endif
</div>

@if($current)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <h5 class="mb-0">Current Version (v{{ $current->version }}) — Active</h5>
        <span class="badge badge-success">Active</span>
        <span class="text-muted small ms-2">{{ $current->listing_type }} / Listing {{ $current->listing_id }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3" style="font-size:.85rem;">
            <div class="col-md-3"><strong>ID:</strong> {{ $current->id }}</div>
            <div class="col-md-3"><strong>Listing Type:</strong> {{ $current->listing_type }}</div>
            <div class="col-md-3"><strong>Listing ID:</strong> {{ $current->listing_id }}</div>
            <div class="col-md-3"><strong>Version:</strong> v{{ $current->version }}</div>
            <div class="col-md-4"><strong>Computed At:</strong> {{ $current->computed_at ? $current->computed_at->format('Y-m-d H:i:s') : '—' }}</div>
            <div class="col-md-4"><strong>Source Listing Updated At:</strong> {{ $current->source_listing_updated_at ? $current->source_listing_updated_at->format('Y-m-d H:i:s') : '—' }}</div>
            <div class="col-md-4"><strong>Archived At:</strong> <span class="text-success">—</span></div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-3"><strong>Overall DNA Completeness:</strong> {{ $current->overall_dna_completeness ?? 'No data' }}</div>
            <div class="col-md-3"><strong>Physical Score:</strong> {{ $current->physical_score ?? 'No data' }}</div>
            <div class="col-md-3"><strong>Financial Score:</strong> {{ $current->financial_score ?? 'No data' }}</div>
            <div class="col-md-3"><strong>Flexibility Score:</strong> {{ $current->flexibility_score ?? 'No data' }}</div>
            <div class="col-md-3"><strong>Occupant Qualification Score:</strong> {{ $current->occupant_qualification_score ?? 'No data' }}</div>
            <div class="col-md-3"><strong>Marketing Score:</strong> {{ $current->marketing_score ?? 'No data' }}</div>
            <div class="col-md-3"><strong>Commercial Score:</strong> {{ $current->commercial_score ?? 'No data' }}</div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small mb-1">The following fields are reserved and not yet populated by any data source.</p></div>

            <div class="col-md-3">
                <strong>Location Score:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Not Yet Populated</span>
            </div>
            <div class="col-md-3">
                <strong>Condition Score:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Not Yet Populated</span>
            </div>
            <div class="col-md-3">
                <strong>Legal Score:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Not Yet Populated</span>
            </div>
            <div class="col-md-3">
                <strong>Reserved Coverage Field:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Not Yet Populated</span>
            </div>
            <div class="col-md-3">
                <strong>Walk Score:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Not Yet Populated</span>
            </div>
            <div class="col-md-3">
                <strong>Transit Score:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Not Yet Populated</span>
            </div>
            <div class="col-md-3">
                <strong>Bike Score:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Not Yet Populated</span>
            </div>
            <div class="col-md-3">
                <strong>School Rating:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Not Yet Populated</span>
            </div>
            <div class="col-md-3">
                <strong>Flood Zone Verified:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Not Yet Populated</span>
            </div>
            <div class="col-md-3">
                <strong>Estimated Monthly Utilities:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Not Yet Populated</span>
            </div>
        </div>
    </div>
</div>
@else
<div class="alert alert-warning">No current (active) version found for this key group.</div>
@endif

@if($archived->count())
<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Archived Versions ({{ $archived->count() }})</h6>
    </div>
    <div class="card-body p-0">
        <div id="archivedAccordion">
            @foreach($archived as $i => $row)
            <div class="border-bottom">
                <div class="p-3 d-flex align-items-center justify-content-between" style="cursor:pointer;" data-toggle="collapse" data-target="#arch-{{ $row->id }}">
                    <span>
                        <span class="badge badge-secondary me-2">Archived</span>
                        <strong>Version {{ $row->version }}</strong> — Archived {{ $row->archived_at ? $row->archived_at->format('Y-m-d H:i:s') : '—' }}
                    </span>
                    <i class="fa-solid fa-chevron-down text-muted"></i>
                </div>
                <div id="arch-{{ $row->id }}" class="collapse px-3 pb-3">
                    <div class="row g-3 mt-1" style="font-size:.83rem;">
                        <div class="col-md-3"><strong>ID:</strong> {{ $row->id }}</div>
                        <div class="col-md-3"><strong>Version:</strong> v{{ $row->version }}</div>
                        <div class="col-md-3"><strong>Computed At:</strong> {{ $row->computed_at ? $row->computed_at->format('Y-m-d H:i:s') : '—' }}</div>
                        <div class="col-md-3"><strong>Source Updated At:</strong> {{ $row->source_listing_updated_at ? $row->source_listing_updated_at->format('Y-m-d H:i:s') : '—' }}</div>
                        <div class="col-md-3"><strong>Overall DNA Completeness:</strong> {{ $row->overall_dna_completeness ?? 'No data' }}</div>
                        <div class="col-md-3"><strong>Physical Score:</strong> {{ $row->physical_score ?? 'No data' }}</div>
                        <div class="col-md-3"><strong>Financial Score:</strong> {{ $row->financial_score ?? 'No data' }}</div>
                        <div class="col-md-3"><strong>Flexibility Score:</strong> {{ $row->flexibility_score ?? 'No data' }}</div>
                        <div class="col-md-3"><strong>Occupant Qualification Score:</strong> {{ $row->occupant_qualification_score ?? 'No data' }}</div>
                        <div class="col-md-3"><strong>Marketing Score:</strong> {{ $row->marketing_score ?? 'No data' }}</div>
                        <div class="col-md-3"><strong>Commercial Score:</strong> {{ $row->commercial_score ?? 'No data' }}</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@else
<div class="alert alert-light text-muted border">No archived versions for this key group.</div>
@endif
@endsection
