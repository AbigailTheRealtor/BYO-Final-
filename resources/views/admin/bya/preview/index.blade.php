@extends('layouts.admin')
@section('content')

<div class="alert alert-danger border-danger mb-4 d-flex align-items-start gap-3" role="alert" style="border-left: 5px solid #dc3545;">
    <i class="fa-solid fa-triangle-exclamation fa-lg mt-1 text-danger"></i>
    <div>
        <strong class="d-block mb-1">INTERNAL REVIEW ONLY</strong>
        This compatibility report is not approved for consumer or agent use. Content is subject to legal, compliance, and Fair Housing review.
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0">BYA Compatibility Preview</h5>
            <span class="badge badge-danger" style="font-size:.72rem;letter-spacing:.04em;">Internal Review Only</span>
        </div>
        <span class="text-muted small">Read-only diagnostic view &bull; <code>listing_compatibility_scores</code> (BYA-enabled records)</span>
    </div>

    <div class="card-body border-bottom pb-3">
        <form method="GET" action="{{ route('admin.bya.preview.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Demand Listing Type</label>
                <input type="text" name="demand_listing_type" class="form-control form-control-sm"
                    value="{{ $filters['demand_listing_type'] ?? '' }}" placeholder="e.g. buyer">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Supply Listing Type</label>
                <input type="text" name="supply_listing_type" class="form-control form-control-sm"
                    value="{{ $filters['supply_listing_type'] ?? '' }}" placeholder="e.g. seller">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Compat. Computed From</label>
                <input type="date" name="compatibility_computed_at_from" class="form-control form-control-sm"
                    value="{{ $filters['compatibility_computed_at_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Compat. Computed To</label>
                <input type="date" name="compatibility_computed_at_to" class="form-control form-control-sm"
                    value="{{ $filters['compatibility_computed_at_to'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="{{ route('admin.bya.preview.index') }}" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
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
                        <th>Framework Version</th>
                        <th>Moderation Status</th>
                        <th>Compat. Computed At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->demand_listing_type }} / {{ $row->demand_listing_id }}</td>
                        <td>{{ $row->supply_listing_type }} / {{ $row->supply_listing_id }}</td>
                        <td>
                            @if($row->compatibility_framework_version)
                                <code style="font-size:.78rem;">{{ $row->compatibility_framework_version }}</code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($row->moderation_status)
                                <span class="badge badge-secondary" style="font-size:.72rem;">{{ $row->moderation_status }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $row->compatibility_computed_at ? $row->compatibility_computed_at->format('Y-m-d H:i') : '—' }}</td>
                        <td>
                            <a href="{{ route('admin.bya.preview.show', $row->id) }}" class="btn btn-xs btn-outline-secondary">View Report</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-3">No BYA-enabled records found.</td>
                    </tr>
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
