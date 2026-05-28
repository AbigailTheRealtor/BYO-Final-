@extends('layouts.admin')
@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="mb-0">DNA Inspector — Coverage Score Records</h5>
        <span class="text-muted small">Read-only diagnostic view &bull; <code>listing_compatibility_scores</code></span>
    </div>

    <div class="card-body border-bottom pb-3">
        <form method="GET" action="{{ route('admin.dna.scores.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Demand Listing Type</label>
                <input type="text" name="demand_listing_type" class="form-control form-control-sm" value="{{ $filters['demand_listing_type'] ?? '' }}" placeholder="e.g. buyer">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">Demand ID</label>
                <input type="text" name="demand_listing_id" class="form-control form-control-sm" value="{{ $filters['demand_listing_id'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Supply Listing Type</label>
                <input type="text" name="supply_listing_type" class="form-control form-control-sm" value="{{ $filters['supply_listing_type'] ?? '' }}" placeholder="e.g. seller">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">Supply ID</label>
                <input type="text" name="supply_listing_id" class="form-control form-control-sm" value="{{ $filters['supply_listing_id'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">Version</label>
                <input type="text" name="version" class="form-control form-control-sm" value="{{ $filters['version'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">Show</label>
                <select name="archived_at" class="form-select form-select-sm">
                    <option value="current" {{ ($filters['archived_at'] ?? 'current') === 'current' ? 'selected' : '' }}>Current</option>
                    <option value="archived" {{ ($filters['archived_at'] ?? '') === 'archived' ? 'selected' : '' }}>Archived</option>
                    <option value="all" {{ ($filters['archived_at'] ?? '') === 'all' ? 'selected' : '' }}>All</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">Conflict Signal</label>
                <select name="deal_breaker_triggered" class="form-select form-select-sm">
                    <option value="" {{ ($filters['deal_breaker_triggered'] ?? '') === '' ? 'selected' : '' }}>Any</option>
                    <option value="1" {{ ($filters['deal_breaker_triggered'] ?? '') === '1' ? 'selected' : '' }}>Present</option>
                    <option value="0" {{ ($filters['deal_breaker_triggered'] ?? '') === '0' ? 'selected' : '' }}>None</option>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">Computed From</label>
                <input type="date" name="computed_at_from" class="form-control form-control-sm" value="{{ $filters['computed_at_from'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">Computed To</label>
                <input type="date" name="computed_at_to" class="form-control form-control-sm" value="{{ $filters['computed_at_to'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size:.81rem;">
                <thead class="thead-light">
                    <tr>
                        <th>ID</th>
                        <th>Demand Type / ID</th>
                        <th>Supply Type / ID</th>
                        <th>Ver.</th>
                        <th>Version Count</th>
                        <th>Status</th>
                        <th>
                            Coverage Score
                            <span data-toggle="tooltip" title="Proportion of the 14 compatibility dimensions for which both listings provided resolvable signal. This is a data completeness indicator only." style="cursor:help;">&#9432;</span>
                        </th>
                        <th>Physical Dimension Coverage</th>
                        <th>Financial Dimension Coverage</th>
                        <th>Location Dimension Coverage</th>
                        <th>Terms Dimension Coverage</th>
                        <th>
                            Conflict Signal
                            <span data-toggle="tooltip" title="These are structural metadata flags only. They record whether a deterministic field conflict was detected during compatibility computation. They do not constitute a recommendation, disqualification, or decision of any kind." style="cursor:help;">&#9432;</span>
                        </th>
                        <th>Computed At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    @php
                        $vcKey = $row->demand_listing_type . ':' . $row->demand_listing_id . ':' . $row->supply_listing_type . ':' . $row->supply_listing_id;
                    @endphp
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->demand_listing_type }} / {{ $row->demand_listing_id }}</td>
                        <td>{{ $row->supply_listing_type }} / {{ $row->supply_listing_id }}</td>
                        <td>v{{ $row->version }}</td>
                        <td class="text-center">{{ $versionCounts[$vcKey]->version_count ?? '—' }}</td>
                        <td>
                            @if(is_null($row->archived_at))
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Archived</span>
                            @endif
                        </td>
                        <td>
                            @if(is_null($row->overall_score))
                                <span class="text-muted">No data</span>
                            @elseif($row->overall_score == 0)
                                0.00 — No resolved dimensions
                            @elseif($row->overall_score == 1)
                                1.00 — All dimensions resolved
                            @else
                                {{ number_format($row->overall_score, 2) }}
                            @endif
                        </td>
                        <td>{{ is_null($row->physical_match_score) ? 'No data' : number_format($row->physical_match_score, 2) }}</td>
                        <td>{{ is_null($row->financial_match_score) ? 'No data' : number_format($row->financial_match_score, 2) }}</td>
                        <td>{{ is_null($row->location_match_score) ? 'No data' : number_format($row->location_match_score, 2) }}</td>
                        <td>{{ is_null($row->terms_match_score) ? 'No data' : number_format($row->terms_match_score, 2) }}</td>
                        <td>
                            @if($row->deal_breaker_triggered)
                                <span class="badge badge-warning text-dark">Conflict Signal Present</span>
                            @else
                                <span class="text-muted">No Conflict Signal</span>
                            @endif
                        </td>
                        <td>{{ $row->computed_at ? $row->computed_at->format('Y-m-d H:i') : '—' }}</td>
                        <td><a href="{{ route('admin.dna.scores.show', $row->id) }}" class="btn btn-xs btn-outline-secondary">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="14" class="text-center text-muted py-3">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
        <div class="p-3">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
