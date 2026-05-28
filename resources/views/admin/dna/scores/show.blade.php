@extends('layouts.admin')
@section('content')
<div class="mb-3">
    <a href="{{ route('admin.dna.scores.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Back to Coverage Score Records</a>
</div>

@if($current)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <h5 class="mb-0">Current Version (v{{ $current->version }}) — Active</h5>
        <span class="badge badge-success">Active</span>
        <span class="text-muted small ms-2">
            Demand: {{ $current->demand_listing_type }} / {{ $current->demand_listing_id }}
            &nbsp;&rarr;&nbsp;
            Supply: {{ $current->supply_listing_type }} / {{ $current->supply_listing_id }}
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3" style="font-size:.85rem;">
            <div class="col-md-2"><strong>ID:</strong> {{ $current->id }}</div>
            <div class="col-md-2"><strong>Version:</strong> v{{ $current->version }}</div>
            <div class="col-md-3"><strong>Scoring Framework:</strong> {{ $current->scoring_framework_version ?? '—' }}</div>
            <div class="col-md-2"><strong>Computed At:</strong> {{ $current->computed_at ? $current->computed_at->format('Y-m-d H:i:s') : '—' }}</div>
            <div class="col-md-3"><strong>Archived At:</strong> <span class="text-success">—</span></div>

            <div class="col-md-4"><strong>Demand Listing Type:</strong> {{ $current->demand_listing_type }}</div>
            <div class="col-md-2"><strong>Demand Listing ID:</strong> {{ $current->demand_listing_id }}</div>
            <div class="col-md-4"><strong>Supply Listing Type:</strong> {{ $current->supply_listing_type }}</div>
            <div class="col-md-2"><strong>Supply Listing ID:</strong> {{ $current->supply_listing_id }}</div>

            <div class="col-md-4"><strong>Demand Listing Snapshot At:</strong> {{ $current->demand_listing_updated_at_snapshot ? $current->demand_listing_updated_at_snapshot->format('Y-m-d H:i:s') : '—' }}</div>
            <div class="col-md-4"><strong>Supply Listing Snapshot At:</strong> {{ $current->supply_listing_updated_at_snapshot ? $current->supply_listing_updated_at_snapshot->format('Y-m-d H:i:s') : '—' }}</div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-4">
                <strong>
                    Dimension Coverage Score
                    <span data-toggle="tooltip" title="Proportion of the 14 compatibility dimensions for which both listings provided resolvable signal. This is a data completeness indicator only." style="cursor:help;">&#9432;</span>
                </strong><br>
                @if(is_null($current->overall_score))
                    <span class="text-muted">No data</span>
                @elseif($current->overall_score == 0)
                    <span>0.00 — No resolved dimensions</span>
                @elseif($current->overall_score == 1)
                    <span>1.00 — All dimensions resolved</span>
                @else
                    <span>{{ number_format($current->overall_score, 2) }}</span>
                @endif
            </div>

            <div class="col-md-2">
                <strong>Physical Dimension Coverage:</strong><br>
                {{ is_null($current->physical_match_score) ? 'No data' : number_format($current->physical_match_score, 2) }}
            </div>
            <div class="col-md-2">
                <strong>Financial Dimension Coverage:</strong><br>
                {{ is_null($current->financial_match_score) ? 'No data' : number_format($current->financial_match_score, 2) }}
            </div>
            <div class="col-md-2">
                <strong>Location Dimension Coverage:</strong><br>
                {{ is_null($current->location_match_score) ? 'No data' : number_format($current->location_match_score, 2) }}
            </div>
            <div class="col-md-2">
                <strong>Terms Dimension Coverage:</strong><br>
                {{ is_null($current->terms_match_score) ? 'No data' : number_format($current->terms_match_score, 2) }}
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-4">
                <strong>
                    Conflict Signal Present / No Conflict Signal
                    <span data-toggle="tooltip" title="These are structural metadata flags only. They record whether a deterministic field conflict was detected during compatibility computation. They do not constitute a recommendation, disqualification, or decision of any kind." style="cursor:help;">&#9432;</span>
                </strong><br>
                @if($current->deal_breaker_triggered)
                    <span class="badge badge-warning text-dark">Conflict Signal Present</span>
                @else
                    <span class="text-muted">No Conflict Signal</span>
                @endif
            </div>

            <div class="col-md-8">
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
                        <div class="col-md-2"><strong>ID:</strong> {{ $row->id }}</div>
                        <div class="col-md-2"><strong>Version:</strong> v{{ $row->version }}</div>
                        <div class="col-md-4"><strong>Computed At:</strong> {{ $row->computed_at ? $row->computed_at->format('Y-m-d H:i:s') : '—' }}</div>
                        <div class="col-md-4"><strong>Archived At:</strong> {{ $row->archived_at ? $row->archived_at->format('Y-m-d H:i:s') : '—' }}</div>
                        <div class="col-md-4">
                            <strong>Dimension Coverage Score:</strong><br>
                            @if(is_null($row->overall_score))
                                <span class="text-muted">No data</span>
                            @elseif($row->overall_score == 0)
                                0.00 — No resolved dimensions
                            @elseif($row->overall_score == 1)
                                1.00 — All dimensions resolved
                            @else
                                {{ number_format($row->overall_score, 2) }}
                            @endif
                        </div>
                        <div class="col-md-2"><strong>Physical Dimension Coverage:</strong><br>{{ is_null($row->physical_match_score) ? 'No data' : number_format($row->physical_match_score, 2) }}</div>
                        <div class="col-md-2"><strong>Financial Dimension Coverage:</strong><br>{{ is_null($row->financial_match_score) ? 'No data' : number_format($row->financial_match_score, 2) }}</div>
                        <div class="col-md-2"><strong>Location Dimension Coverage:</strong><br>{{ is_null($row->location_match_score) ? 'No data' : number_format($row->location_match_score, 2) }}</div>
                        <div class="col-md-2"><strong>Terms Dimension Coverage:</strong><br>{{ is_null($row->terms_match_score) ? 'No data' : number_format($row->terms_match_score, 2) }}</div>
                        <div class="col-md-4">
                            <strong>Conflict Signal:</strong><br>
                            @if($row->deal_breaker_triggered)
                                <span class="badge badge-warning text-dark">Conflict Signal Present</span>
                            @else
                                <span class="text-muted">No Conflict Signal</span>
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
