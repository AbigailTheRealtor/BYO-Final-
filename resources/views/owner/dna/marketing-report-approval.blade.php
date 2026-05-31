@extends('layouts.main')
@section('content')

{{-- ================================================================
     WARNING BANNER
     ================================================================ --}}
<div class="alert alert-info border border-info d-flex align-items-start gap-2 mb-4" role="alert">
    <strong>&#9432; Seller/Landlord approval only &mdash; review before publication. This report is not yet published.</strong>
</div>

<div class="mb-3">
    <a href="{{ url()->previous() }}">&larr; Back</a>
</div>

{{-- ================================================================
     FLASH MESSAGES
     ================================================================ --}}
@if(session('success'))
    <div class="alert alert-success mb-4" role="alert">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger mb-4" role="alert">
        {{ session('error') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger mb-4" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ================================================================
     SECTION 1: Report Summary
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">Report Summary</h5>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Report ID</dt>
            <dd class="col-sm-9"><code>{{ $record->id }}</code></dd>

            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9">
                @php
                    $statusColors = [
                        'pending_review'           => 'warning text-dark',
                        'seller_approved'          => 'success',
                        'rejected'                 => 'danger',
                        'agent_approved'           => 'info text-dark',
                        'published'                => 'primary',
                        'held_attribution_failure' => 'secondary',
                    ];
                    $badgeClass = $statusColors[$record->status] ?? 'secondary';
                @endphp
                <span class="badge bg-{{ $badgeClass }}">{{ $record->status }}</span>
            </dd>

            <dt class="col-sm-3">Generated At</dt>
            <dd class="col-sm-9">{{ $record->generated_at ?? '—' }}</dd>

            <dt class="col-sm-3">Created At</dt>
            <dd class="col-sm-9">{{ $record->created_at ?? '—' }}</dd>

            <dt class="col-sm-3">Updated At</dt>
            <dd class="col-sm-9">{{ $record->updated_at ?? '—' }}</dd>
        </dl>
    </div>
</div>

{{-- ================================================================
     SECTION 2: Report Sections (Read-Only)
     ================================================================ --}}
@php
    $sectionLabels = [
        'property_feature_narrative'  => 'Property Feature Narrative',
        'transaction_terms_summary'   => 'Transaction Terms Summary',
        'marketing_asset_statement'   => 'Marketing Asset Statement',
        'listing_preparation_summary' => 'Listing Preparation Summary',
        'missing_information_note'    => 'Missing Information Note',
    ];
@endphp

<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">Report Sections</h5>
        <div class="text-muted small">Read-only view of all report sections</div>
    </div>
    <div class="card-body">
        @forelse($sectionLabels as $key => $label)
            @php
                $sectionData = $sections[$key] ?? [];
                $text = is_array($sectionData)
                    ? ($sectionData['draft_text'] ?? '')
                    : (string) $sectionData;
            @endphp
            <div class="mb-4 border rounded p-3 @if($key === 'missing_information_note') bg-light @endif">
                <h6 class="fw-semibold mb-1">
                    {{ $label }}
                    @if($key === 'missing_information_note')
                        <span class="badge bg-secondary ms-1" style="font-size:.7rem;">Informational</span>
                    @endif
                </h6>
                @if(blank($text))
                    <p class="text-muted mb-0"><em>No content available.</em></p>
                @else
                    <pre class="mb-0" style="font-size:.87rem;white-space:pre-wrap;word-break:break-word;">{{ $text }}</pre>
                @endif
            </div>
        @empty
            <p class="text-muted mb-0"><em>No sections available.</em></p>
        @endforelse
    </div>
</div>

{{-- ================================================================
     SECTION 3: Version History (Read-Only)
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">Version History</h5>
        <div class="text-muted small">Append-only, ordered by section then version descending</div>
    </div>
    <div class="card-body p-0">
        @if($versions->isEmpty())
            <p class="text-muted p-3 mb-0"><em>No version history available.</em></p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Section</th>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Draft Text</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($versions as $version)
                            <tr>
                                <td><code>{{ $version->section_key }}</code></td>
                                <td>{{ $version->version_number }}</td>
                                <td><span class="badge bg-secondary">{{ $version->status }}</span></td>
                                <td>{{ $version->created_by }}</td>
                                <td>{{ $version->created_at }}</td>
                                <td>
                                    @if(blank($version->draft_text))
                                        <em class="text-muted">No data available.</em>
                                    @else
                                        <pre class="mb-0" style="font-size:.78rem;white-space:pre-wrap;word-break:break-word;max-width:320px;">{{ $version->draft_text }}</pre>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- ================================================================
     SECTION 4: Audit History (Read-Only)
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">Audit History</h5>
        <div class="text-muted small">Append-only, ordered by event date descending</div>
    </div>
    <div class="card-body p-0">
        @if($audits->isEmpty())
            <p class="text-muted p-3 mb-0"><em>No audit history available.</em></p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Event Type</th>
                            <th>Actor ID</th>
                            <th>Event At</th>
                            <th>Event Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($audits as $audit)
                            @php
                                $eventDataDecoded = json_decode($audit->event_data, true);
                            @endphp
                            <tr>
                                <td><code>{{ $audit->event_type }}</code></td>
                                <td>{{ $audit->actor_id ?? '—' }}</td>
                                <td>{{ $audit->event_at }}</td>
                                <td>
                                    @if(empty($eventDataDecoded))
                                        <em class="text-muted">No data available.</em>
                                    @else
                                        <pre class="mb-0" style="font-size:.78rem;white-space:pre-wrap;word-break:break-word;max-width:380px;">{{ json_encode($eventDataDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- ================================================================
     SECTION 5: Approval / Rejection Actions
     Shown only when status is pending_review.
     All other statuses show a read-only status notice with no submit controls.
     ================================================================ --}}
@if($record->status === 'pending_review')

    <div class="card mb-4 border-success">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0 fw-semibold">&#10003; Approve This Report</h5>
        </div>
        <div class="card-body">
            <p class="mb-3">Approving this report confirms you have reviewed the content and it is accurate. The report will be marked as seller/landlord approved and forwarded for the next step.</p>
            <form method="POST" action="{{ route('owner.property-dna.marketing-reports.approve', ['report' => $record->id]) }}">
                @csrf
                <button type="submit" class="btn btn-success"
                    onclick="return confirm('Approve this marketing report? This action cannot be undone.')">
                    Approve Report
                </button>
            </form>
        </div>
    </div>

    <div class="card mb-4 border-danger">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0 fw-semibold">&#10005; Reject This Report</h5>
        </div>
        <div class="card-body">
            <p class="mb-3">Rejecting this report sends it back for revision. You may optionally provide a reason for rejection below.</p>
            <form method="POST" action="{{ route('owner.property-dna.marketing-reports.reject', ['report' => $record->id]) }}">
                @csrf
                <div class="mb-3">
                    <label for="rejection_reason" class="form-label">Rejection Reason <span class="text-muted">(optional)</span></label>
                    <textarea
                        id="rejection_reason"
                        name="rejection_reason"
                        class="form-control"
                        rows="4"
                        maxlength="2000"
                        placeholder="Describe any corrections or concerns..."
                    >{{ old('rejection_reason') }}</textarea>
                    <div class="form-text">Maximum 2,000 characters.</div>
                </div>
                <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Reject this marketing report? This action cannot be undone.')">
                    Reject Report
                </button>
            </form>
        </div>
    </div>

@else

    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-0 text-muted">
                <strong>This report is no longer awaiting approval.</strong>
                Current status: <span class="badge bg-{{ $badgeClass }}">{{ $record->status }}</span>.
                No further action is available from this page.
            </p>
        </div>
    </div>

@endif

@endsection
