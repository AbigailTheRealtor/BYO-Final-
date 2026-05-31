@extends('layouts.main')
@section('content')

{{-- ================================================================
     WARNING BANNER
     ================================================================ --}}
<div class="alert alert-warning border border-warning d-flex align-items-start gap-2 mb-4" role="alert">
    <strong>&#9888; Agent review only &mdash; internal marketing report. Not public, not seller/landlord-facing, not published.</strong>
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
     SECTION 4: Report Sections (Editable + Read-Only)
     ================================================================ --}}
@php
    $sectionsDecoded = $record ? json_decode($record->sections, true) : [];
    $sectionsDecoded = is_array($sectionsDecoded) ? $sectionsDecoded : [];

    $editableSections = [
        'property_feature_narrative'  => 'Property Feature Narrative',
        'transaction_terms_summary'   => 'Transaction Terms Summary',
        'marketing_asset_statement'   => 'Marketing Asset Statement',
        'listing_preparation_summary' => 'Listing Preparation Summary',
    ];
@endphp

<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-semibold">4. Report Sections</h5>
        <div class="text-muted small">Four editable sections (agent may submit revisions) + one read-only section (<code>missing_information_note</code>)</div>
    </div>
    <div class="card-body">

        {{-- 4a. Four editable sections --}}
        @foreach($editableSections as $sectionKey => $sectionLabel)
            @php
                $sectionData    = $sectionsDecoded[$sectionKey] ?? [];
                $currentText    = is_array($sectionData) ? ($sectionData['draft_text'] ?? '') : (string) $sectionData;
                $updateRoute    = route('agent.property-dna.marketing-reports.sections.update', [
                    'report'  => $record->id,
                    'section' => $sectionKey,
                ]);
            @endphp
            <div class="mb-4 border rounded p-3">
                <h6 class="fw-semibold mb-1">
                    <code>{{ $sectionKey }}</code> &mdash; {{ $sectionLabel }}
                </h6>
                <p class="text-muted small mb-2">Submit a revision to update this section's draft text. A new version row will be recorded.</p>
                <form method="POST" action="{{ $updateRoute }}">
                    @csrf
                    <div class="mb-2">
                        <textarea
                            name="draft_text"
                            class="form-control"
                            rows="8"
                            maxlength="10000"
                            aria-label="{{ $sectionLabel }} draft text"
                        >{{ old('draft_text', $currentText) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save Revision</button>
                </form>
            </div>
        @endforeach

        {{-- 4b. missing_information_note — read-only --}}
        @php
            $missingData = $sectionsDecoded['missing_information_note'] ?? [];
            $missingText = is_array($missingData) ? ($missingData['draft_text'] ?? '') : (string) $missingData;
        @endphp
        <div class="mb-2 border rounded p-3 bg-light">
            <h6 class="fw-semibold mb-1">
                <code>missing_information_note</code> &mdash; Missing Information Note
                <span class="badge bg-secondary ms-1" style="font-size:.7rem;">Read-only</span>
            </h6>
            <p class="text-muted small mb-2">This section is informational only and cannot be revised.</p>
            @if(blank($missingText))
                <p class="text-muted mb-0"><em>No data available.</em></p>
            @else
                <pre class="mb-0" style="font-size:.82rem;white-space:pre-wrap;word-break:break-word;">{{ $missingText }}</pre>
            @endif
        </div>

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
