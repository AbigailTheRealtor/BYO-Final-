@extends('layouts.admin')
@section('content')
<div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
    <a href="{{ route('admin.dna.demand.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Back to Demand DNA Index</a>
    @if($current)
        @if($current->listing_type === 'buyer')
            <a href="{{ route('admin.dna.profiles.buyer', $current->listing_id) }}" class="btn btn-sm btn-outline-primary">View Buyer DNA Profile</a>
        @elseif($current->listing_type === 'tenant')
            <a href="{{ route('admin.dna.profiles.tenant', $current->listing_id) }}" class="btn btn-sm btn-outline-primary">View Tenant DNA Profile</a>
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

            <div class="col-md-3">
                <strong>Preference Completeness:</strong><br>
                {{ $current->preference_completeness ?? 'No data' }}
            </div>

            <div class="col-md-9">
                <strong>Lifestyle Tags (raw JSON):</strong><br>
                @if(!is_null($current->lifestyle_tags))
                    <code class="d-block p-2 bg-light border rounded" style="font-size:.78rem;white-space:pre-wrap;">{{ json_encode($current->lifestyle_tags, JSON_PRETTY_PRINT) }}</code>
                @else
                    <span class="text-muted">No data</span>
                @endif
            </div>

            <div class="col-md-6">
                <strong>
                    Conflict Dimensions (metadata)
                    <span data-toggle="tooltip" title="These are structural metadata flags only. They record whether a deterministic field conflict was detected during compatibility computation. They do not constitute a recommendation, disqualification, or decision of any kind." style="cursor:help;">&#9432;</span>
                </strong><br>
                @if(!is_null($current->deal_breaker_flags))
                    <code class="d-block p-2 bg-light border rounded" style="font-size:.78rem;white-space:pre-wrap;">{{ json_encode($current->deal_breaker_flags, JSON_PRETTY_PRINT) }}</code>
                @else
                    <span class="text-muted">Conflict Dimensions — Not yet populated</span>
                @endif
            </div>

            <div class="col-md-6">
                <strong>Commute Polygon Cache:</strong><br>
                <span class="badge badge-light border text-muted" style="font-size:.78rem;">Reserved — Geospatial / Future Phase</span>
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
                        <div class="col-md-3"><strong>Preference Completeness:</strong> {{ $row->preference_completeness ?? 'No data' }}</div>
                        <div class="col-md-9">
                            <strong>Lifestyle Tags (raw JSON):</strong><br>
                            @if(!is_null($row->lifestyle_tags))
                                <code style="font-size:.78rem;white-space:pre-wrap;">{{ json_encode($row->lifestyle_tags, JSON_PRETTY_PRINT) }}</code>
                            @else
                                <span class="text-muted">No data</span>
                            @endif
                        </div>
                        <div class="col-md-12">
                            <strong>Conflict Dimensions (metadata):</strong><br>
                            @if(!is_null($row->deal_breaker_flags))
                                <code style="font-size:.78rem;white-space:pre-wrap;">{{ json_encode($row->deal_breaker_flags, JSON_PRETTY_PRINT) }}</code>
                            @else
                                <span class="text-muted">Conflict Dimensions — Not yet populated</span>
                            @endif
                        </div>
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
