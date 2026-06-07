@extends('layouts.admin')
@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="mb-0">DNA Inspector — Location DNA Records</h5>
        <span class="text-muted small">Read-only diagnostic view &bull; <code>property_location_dna</code></span>
    </div>

    <div class="card-body border-bottom pb-3">
        <form method="GET" action="{{ route('admin.dna.location.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Listing Type</label>
                <input type="text" name="listing_type" class="form-control form-control-sm" value="{{ $filters['listing_type'] ?? '' }}" placeholder="e.g. seller">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Listing ID</label>
                <input type="text" name="listing_id" class="form-control form-control-sm" value="{{ $filters['listing_id'] ?? '' }}" placeholder="numeric ID">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Generated From</label>
                <input type="date" name="generated_at_from" class="form-control form-control-sm" value="{{ $filters['generated_at_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Generated To</label>
                <input type="date" name="generated_at_to" class="form-control form-control-sm" value="{{ $filters['generated_at_to'] ?? '' }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="{{ route('admin.dna.location.index') }}" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
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
                        <th>Geocode Status</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Generated At</th>
                        <th>Created At</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->listing_type }}</td>
                        <td>{{ $row->listing_id }}</td>
                        <td>
                            @if($row->geocode_status === 'geocoded')
                                <span class="badge badge-success">geocoded</span>
                            @elseif($row->geocode_status)
                                <span class="badge badge-secondary">{{ $row->geocode_status }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="font-monospace">{{ $row->geocoded_lat ?? '—' }}</td>
                        <td class="font-monospace">{{ $row->geocoded_lng ?? '—' }}</td>
                        <td>{{ $row->generated_at ? $row->generated_at->format('Y-m-d H:i') : '—' }}</td>
                        <td>{{ $row->created_at ? $row->created_at->format('Y-m-d H:i') : '—' }}</td>
                        <td>
                            <a href="{{ route('admin.dna.location.show', [$row->listing_type, $row->listing_id]) }}"
                               class="btn btn-xs btn-outline-secondary">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-3">No Location DNA records found.</td></tr>
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
