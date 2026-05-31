@extends('layouts.admin')
@section('content')

{{-- ================================================================
     WARNING BANNER
     ================================================================ --}}
<div class="alert alert-danger border border-danger d-flex align-items-start gap-2 mb-4" role="alert">
    <strong>&#9888; Internal admin review only &mdash; not public, not agent-facing, not seller/landlord-facing.</strong>
</div>

<div class="mb-3">
    <a href="{{ url()->previous() }}">&larr; Back</a>
</div>

{{-- ================================================================
     FLASH MESSAGES
     ================================================================ --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- ================================================================
     PUBLICATION CONTROLS
     ================================================================ --}}
<div class="card mb-4 border-primary">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0 fw-semibold">Publication Controls</h5>
        <div class="small opacity-75">Admin-only workflow action &mdash; archive is deferred (not supported by current schema)</div>
    </div>
    <div class="card-body">
        @if($record && $record->status === 'seller_approved')
            <p class="mb-3">This report has been approved by the property owner and is ready to publish. Publishing transitions the status to <strong>published</strong> and records an audit entry.</p>
            <form method="POST" action="{{ route('admin.property-dna.marketing-reports.publish', $record->id) }}"
                  onsubmit="return confirm('Publish this marketing report? This action cannot be undone.');">
                @csrf
                <button type="submit" class="btn btn-primary">
                    Publish Report
                </button>
            </form>
        @else
            <p class="mb-0 text-muted">
                <strong>Status:</strong>
                <span class="badge bg-secondary">{{ $record->status ?? '—' }}</span>
                &mdash; No publication action is available for reports in this status.
                Only reports with status <code>seller_approved</code> may be published.
            </p>
        @endif
    </div>
</div>

{{-- ================================================================
     SECTION 1: Report Summary
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">1. Report Summary</h5>
        <div class="text-muted small">Core identity fields from <code>marketing_reports</code></div>
    </div>
    <div class="card-body">
        @if(! $record)
            <p class="text-muted mb-0"><em>No data available.</em></p>
        @else
            <dl class="row mb-0">
                <dt class="col-sm-3">Report ID</dt>
                <dd class="col-sm-9"><code>{{ $record->id }}</code></dd>

                <dt class="col-sm-3">Listing ID</dt>
                <dd class="col-sm-9">{{ $record->listing_id }}</dd>

                <dt class="col-sm-3">Profile ID</dt>
                <dd class="col-sm-9">{{ $record->profile_id }}</dd>

                <dt class="col-sm-3">Status</dt>
                <dd class="col-sm-9">
                    <span class="badge bg-secondary">{{ $record->status }}</span>
                </dd>

                <dt class="col-sm-3">Attribution Verified</dt>
                <dd class="col-sm-9">
                    @if($record->attribution_verified)
                        <span class="badge bg-success">Yes</span>
                    @else
                        <span class="badge bg-warning text-dark">No</span>
                    @endif
                </dd>

                <dt class="col-sm-3">Generated At</dt>
                <dd class="col-sm-9">{{ $record->generated_at ?? '—' }}</dd>

                <dt class="col-sm-3">Created At</dt>
                <dd class="col-sm-9">{{ $record->created_at ?? '—' }}</dd>

                <dt class="col-sm-3">Updated At</dt>
                <dd class="col-sm-9">{{ $record->updated_at ?? '—' }}</dd>
            </dl>
        @endif
    </div>
</div>

{{-- ================================================================
     SECTION 2: Report Metadata
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">2. Report Metadata</h5>
        <div class="text-muted small">AI model, prompt template, and contract versioning</div>
    </div>
    <div class="card-body">
        @if(! $record)
            <p class="text-muted mb-0"><em>No data available.</em></p>
        @else
            <dl class="row mb-0">
                <dt class="col-sm-3">AI Model</dt>
                <dd class="col-sm-9"><code>{{ $record->ai_model ?? '—' }}</code></dd>

                <dt class="col-sm-3">Prompt Template Version</dt>
                <dd class="col-sm-9"><code>{{ $record->prompt_template_version ?? '—' }}</code></dd>

                <dt class="col-sm-3">Report Contract Version</dt>
                <dd class="col-sm-9"><code>{{ $record->report_contract_version ?? '—' }}</code></dd>

                <dt class="col-sm-3">Phase R Brief Version</dt>
                <dd class="col-sm-9"><code>{{ $record->phase_r_brief_version ?? '—' }}</code></dd>

                <dt class="col-sm-3">Phase U Readiness Version</dt>
                <dd class="col-sm-9"><code>{{ $record->phase_u_readiness_version ?? '—' }}</code></dd>
            </dl>
        @endif
    </div>
</div>

{{-- ================================================================
     SECTION 3: Readiness Snapshot
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">3. Readiness Snapshot</h5>
        <div class="text-muted small"><code>readiness_snapshot</code> &mdash; JSONB field</div>
    </div>
    <div class="card-body p-0">
        @php
            $readinessDecoded = $record ? json_decode($record->readiness_snapshot, true) : null;
        @endphp
        @if(empty($readinessDecoded))
            <p class="text-muted p-3 mb-0"><em>No data available.</em></p>
        @else
            <pre class="p-3 mb-0" style="font-size:.82rem;white-space:pre-wrap;word-break:break-word;">{{ json_encode($readinessDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div>
</div>

{{-- ================================================================
     SECTION 4: Report Sections
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">4. Report Sections</h5>
        <div class="text-muted small"><code>sections</code> &mdash; JSONB field containing all five AI-generated content sections</div>
    </div>
    <div class="card-body p-0">
        @php
            $sectionsDecoded = $record ? json_decode($record->sections, true) : null;
        @endphp
        @if(empty($sectionsDecoded))
            <p class="text-muted p-3 mb-0"><em>No data available.</em></p>
        @else
            <pre class="p-3 mb-0" style="font-size:.82rem;white-space:pre-wrap;word-break:break-word;">{{ json_encode($sectionsDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div>
</div>

{{-- ================================================================
     SECTION 5: Version History
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">5. Version History</h5>
        <div class="text-muted small">Rows from <code>marketing_report_versions</code> &mdash; append-only, ordered by section then version descending</div>
    </div>
    <div class="card-body p-0">
        @if($versions->isEmpty())
            <p class="text-muted p-3 mb-0"><em>No data available.</em></p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Section Key</th>
                            <th>Version #</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Draft Text</th>
                            <th>Source Attribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($versions as $version)
                            @php
                                $attrDecoded = json_decode($version->source_attribution, true);
                            @endphp
                            <tr>
                                <td>{{ $version->id }}</td>
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
                                <td>
                                    @if(empty($attrDecoded))
                                        <em class="text-muted">No data available.</em>
                                    @else
                                        <pre class="mb-0" style="font-size:.78rem;white-space:pre-wrap;word-break:break-word;max-width:280px;">{{ json_encode($attrDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
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
     SECTION 6: Audit History
     ================================================================ --}}
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">6. Audit History</h5>
        <div class="text-muted small">Rows from <code>marketing_report_audits</code> &mdash; append-only, ordered by <code>event_at</code> descending</div>
    </div>
    <div class="card-body p-0">
        @if($audits->isEmpty())
            <p class="text-muted p-3 mb-0"><em>No data available.</em></p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Event Type</th>
                            <th>Actor ID</th>
                            <th>Event At</th>
                            <th>Created At</th>
                            <th>Event Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($audits as $audit)
                            @php
                                $eventDataDecoded = json_decode($audit->event_data, true);
                            @endphp
                            <tr>
                                <td>{{ $audit->id }}</td>
                                <td><code>{{ $audit->event_type }}</code></td>
                                <td>{{ $audit->actor_id ?? '—' }}</td>
                                <td>{{ $audit->event_at }}</td>
                                <td>{{ $audit->created_at }}</td>
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

@endsection
