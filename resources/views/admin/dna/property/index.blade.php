@extends('layouts.admin')
@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="mb-0">DNA Inspector — Property DNA Profiles</h5>
        <span class="text-muted small">Read-only diagnostic view &bull; <code>property_dna_profiles</code></span>
    </div>

    <div class="card-body border-bottom pb-3">
        <form method="GET" action="{{ route('admin.dna.property.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Listing Type</label>
                <input type="text" name="listing_type" class="form-control form-control-sm" value="{{ $filters['listing_type'] ?? '' }}" placeholder="e.g. seller">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Listing ID</label>
                <input type="text" name="listing_id" class="form-control form-control-sm" value="{{ $filters['listing_id'] ?? '' }}" placeholder="numeric ID">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">Version</label>
                <input type="text" name="version" class="form-control form-control-sm" value="{{ $filters['version'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Show</label>
                <select name="archived_at" class="form-select form-select-sm">
                    <option value="current" {{ ($filters['archived_at'] ?? 'current') === 'current' ? 'selected' : '' }}>Current only</option>
                    <option value="archived" {{ ($filters['archived_at'] ?? '') === 'archived' ? 'selected' : '' }}>Archived only</option>
                    <option value="all" {{ ($filters['archived_at'] ?? '') === 'all' ? 'selected' : '' }}>All versions</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Computed From</label>
                <input type="date" name="computed_at_from" class="form-control form-control-sm" value="{{ $filters['computed_at_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
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
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size:.82rem;">
                <thead class="thead-light">
                    <tr>
                        <th>ID</th>
                        <th>Listing Type</th>
                        <th>Listing ID</th>
                        <th>Version</th>
                        <th>Version Count</th>
                        <th>Status</th>
                        <th>Overall DNA Completeness</th>
                        <th>Physical Score</th>
                        <th>Financial Score</th>
                        <th>Flexibility Score</th>
                        <th>Computed At</th>
                        <th>Source Updated At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    @php $vcKey = $row->listing_type . ':' . $row->listing_id; @endphp
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->listing_type }}</td>
                        <td>{{ $row->listing_id }}</td>
                        <td>v{{ $row->version }}</td>
                        <td class="text-center">{{ $versionCounts[$vcKey]->version_count ?? '—' }}</td>
                        <td>
                            @if(is_null($row->archived_at))
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Archived</span>
                            @endif
                        </td>
                        <td>{{ $row->overall_dna_completeness ?? 'No data' }}</td>
                        <td>{{ $row->physical_score ?? 'No data' }}</td>
                        <td>{{ $row->financial_score ?? 'No data' }}</td>
                        <td>{{ $row->flexibility_score ?? 'No data' }}</td>
                        <td>{{ $row->computed_at ? $row->computed_at->format('Y-m-d H:i') : '—' }}</td>
                        <td>{{ $row->source_listing_updated_at ? $row->source_listing_updated_at->format('Y-m-d H:i') : '—' }}</td>
                        <td><a href="{{ route('admin.dna.property.show', $row->id) }}" class="btn btn-xs btn-outline-secondary">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="13" class="text-center text-muted py-3">No records found.</td></tr>
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
