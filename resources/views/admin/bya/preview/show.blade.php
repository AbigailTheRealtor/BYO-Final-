@extends('layouts.admin')
@section('content')

<div class="alert alert-danger border-danger mb-4 d-flex align-items-start gap-3" role="alert" style="border-left: 5px solid #dc3545;">
    <i class="fa-solid fa-triangle-exclamation fa-lg mt-1 text-danger"></i>
    <div>
        <strong class="d-block mb-1">INTERNAL REVIEW ONLY</strong>
        This compatibility report is not approved for consumer or agent use. Content is subject to legal, compliance, and Fair Housing review.
    </div>
</div>

<div class="mb-3 d-flex align-items-center gap-2">
    <a href="{{ route('admin.bya.preview.index') }}" class="btn btn-sm btn-outline-secondary">&larr; Back to BYA Preview List</a>
    <span class="badge badge-danger" style="font-size:.72rem;letter-spacing:.04em;">Internal Review Only</span>
</div>

<div class="mb-3">
    <h5 class="mb-1">
        BYA_REPORT_V1 — Record #{{ $record->id }}
    </h5>
    <small class="text-muted">
        Demand: <strong>{{ $record->demand_listing_type }} / {{ $record->demand_listing_id }}</strong>
        &rarr;
        Supply: <strong>{{ $record->supply_listing_type }} / {{ $record->supply_listing_id }}</strong>
        &nbsp;&bull;&nbsp;
        Framework: <code style="font-size:.78rem;">{{ $record->compatibility_framework_version ?? '—' }}</code>
        &nbsp;&bull;&nbsp;
        Compat. Computed: {{ $record->compatibility_computed_at ? $record->compatibility_computed_at->format('Y-m-d H:i:s') : '—' }}
        @if($record->compatibility_archived_at)
            &nbsp;&bull;&nbsp;<span class="badge badge-secondary">Archived {{ $record->compatibility_archived_at->format('Y-m-d H:i:s') }}</span>
        @endif
    </small>
</div>

@php
    $dimensions = $reportV1['dimensions'] ?? [];
    $summary    = $reportV1['summary']    ?? [];
    $audit      = $reportV1['audit']      ?? [];
    $sourceVersions = $audit['source_versions'] ?? [];
    $traceKeys      = $audit['trace_keys']      ?? [];

    // Version fields are top-level keys in BYA_REPORT_V1, not nested under 'versions'.
    $versionFields = [
        'report_version'      => 'Report Version',
        'alignment_version'   => 'Alignment Version',
        'explanation_version' => 'Explanation Version',
        'narrative_version'   => 'Narrative Version',
    ];
    $hasAnyVersion = array_reduce(
        array_keys($versionFields),
        fn($carry, $k) => $carry || isset($reportV1[$k]),
        false
    );
@endphp

{{-- ============================================================
     SECTION 1: Versions
     ============================================================ --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fa-solid fa-tag me-2 text-secondary"></i>Versions</h6>
    </div>
    <div class="card-body" style="font-size:.85rem;">
        @if(!$hasAnyVersion)
            <span class="text-muted">No version data available — compatibility_trait_results may be empty for this record.</span>
        @else
        <div class="row g-3">
            @foreach($versionFields as $key => $label)
            <div class="col-md-3">
                <strong>{{ $label }}</strong><br>
                @if(isset($reportV1[$key]))
                    <code style="font-size:.8rem;">{{ $reportV1[$key] }}</code>
                @else
                    <span class="text-muted">—</span>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- ============================================================
     SECTION 2: Per-Dimension Table
     ============================================================ --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fa-solid fa-table me-2 text-secondary"></i>Per-Dimension Results</h6>
    </div>
    <div class="card-body p-0">
        @if(empty($dimensions))
            <div class="p-3 text-muted">No dimension data available — compatibility_trait_results may be empty for this record.</div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover mb-0" style="font-size:.8rem;">
                <thead class="thead-light">
                    <tr>
                        <th>Dimension</th>
                        <th>Relationship</th>
                        <th>Alignment Category</th>
                        <th>Explanation Type</th>
                        <th>Explanation Key</th>
                        <th>Template ID</th>
                        <th>Sentence</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dimensions as $dim => $data)
                    @php
                        $relationship      = $data['relationship']       ?? '—';
                        $alignCat          = $data['alignment_category'] ?? '—';
                        $explainType       = $data['explanation_type']   ?? '—';
                        $explainKey        = $data['explanation_key']    ?? '—';
                        $templateId        = $data['template_id']        ?? '—';
                        $sentence          = $data['sentence']           ?? null;

                        $alignBadge = match($alignCat) {
                            'full_alignment'         => 'badge-success',
                            'partial_alignment'      => 'badge-info',
                            'adjacent_compatibility' => 'badge-warning',
                            'neutral_compatibility'  => 'badge-light text-dark border',
                            'incompatible_alignment' => 'badge-danger',
                            'insufficient_data'      => 'badge-secondary',
                            default                  => 'badge-light text-dark border',
                        };
                    @endphp
                    <tr>
                        <td><code style="font-size:.77rem;">{{ $dim }}</code></td>
                        <td>{{ $relationship }}</td>
                        <td>
                            <span class="badge {{ $alignBadge }}" style="font-size:.72rem;">{{ $alignCat }}</span>
                        </td>
                        <td>{{ $explainType }}</td>
                        <td><code style="font-size:.77rem;">{{ $explainKey }}</code></td>
                        <td><code style="font-size:.77rem;">{{ $templateId }}</code></td>
                        <td style="max-width:300px;white-space:normal;">
                            @if($sentence)
                                {{ $sentence }}
                            @else
                                <span class="text-muted">—</span>
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

{{-- ============================================================
     SECTION 3: Summary
     ============================================================ --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fa-solid fa-chart-bar me-2 text-secondary"></i>Summary</h6>
    </div>
    <div class="card-body" style="font-size:.85rem;">
        @if(empty($summary))
            <span class="text-muted">No summary data available.</span>
        @else

        @php
            $alignCounts   = $summary['alignment_category_counts']  ?? [];
            $explainCounts = $summary['explanation_type_counts']     ?? [];
            $narrativeCounts = $summary['narrative_type_counts']     ?? [];
            $summarySentence = $summary['summary_sentence']          ?? null;
        @endphp

        <div class="row g-4 mb-3">
            <div class="col-md-4">
                <strong class="d-block mb-2">Alignment Category Counts</strong>
                @if(empty($alignCounts))
                    <span class="text-muted">No data</span>
                @else
                <ul class="list-unstyled mb-0" style="font-size:.82rem;">
                    @foreach($alignCounts as $cat => $count)
                    <li class="d-flex justify-content-between border-bottom py-1">
                        <span>{{ $cat }}</span>
                        <strong>{{ $count }}</strong>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
            <div class="col-md-4">
                <strong class="d-block mb-2">Explanation Type Counts</strong>
                @if(empty($explainCounts))
                    <span class="text-muted">No data</span>
                @else
                <ul class="list-unstyled mb-0" style="font-size:.82rem;">
                    @foreach($explainCounts as $type => $count)
                    <li class="d-flex justify-content-between border-bottom py-1">
                        <span>{{ $type }}</span>
                        <strong>{{ $count }}</strong>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
            <div class="col-md-4">
                <strong class="d-block mb-2">Narrative Type Counts</strong>
                @if(empty($narrativeCounts))
                    <span class="text-muted">No data</span>
                @else
                <ul class="list-unstyled mb-0" style="font-size:.82rem;">
                    @foreach($narrativeCounts as $type => $count)
                    <li class="d-flex justify-content-between border-bottom py-1">
                        <span>{{ $type }}</span>
                        <strong>{{ $count }}</strong>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>

        @if($summarySentence)
        <div class="alert alert-light border mt-2 mb-0" style="font-size:.85rem;">
            <strong>Summary Sentence:</strong> {{ $summarySentence }}
        </div>
        @endif

        @endif
    </div>
</div>

{{-- ============================================================
     SECTION 4: Audit / Trace
     ============================================================ --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fa-solid fa-magnifying-glass me-2 text-secondary"></i>Audit / Trace</h6>
    </div>
    <div class="card-body" style="font-size:.85rem;">
        @if(empty($audit))
            <span class="text-muted">No audit data available.</span>
        @else

        <div class="row g-4">
            <div class="col-md-6">
                <strong class="d-block mb-2">Source Versions</strong>
                @if(empty($sourceVersions))
                    <span class="text-muted">No source version data</span>
                @else
                <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;">
                    <thead class="thead-light">
                        <tr><th>Key</th><th>Value</th></tr>
                    </thead>
                    <tbody>
                        @foreach($sourceVersions as $key => $value)
                        <tr>
                            <td><code style="font-size:.77rem;">{{ $key }}</code></td>
                            <td><code style="font-size:.77rem;">{{ is_array($value) ? json_encode($value) : $value }}</code></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
            <div class="col-md-6">
                <strong class="d-block mb-2">Trace Keys (per Dimension)</strong>
                @if(empty($traceKeys))
                    <span class="text-muted">No trace key data</span>
                @else
                <table class="table table-sm table-bordered mb-0" style="font-size:.8rem;">
                    <thead class="thead-light">
                        <tr><th>Dimension</th><th>Trace Key</th></tr>
                    </thead>
                    <tbody>
                        @foreach($traceKeys as $dim => $traceKey)
                        <tr>
                            <td><code style="font-size:.77rem;">{{ $dim }}</code></td>
                            <td>
                                @if(is_array($traceKey))
                                    <code style="font-size:.77rem;white-space:pre-wrap;">{{ json_encode($traceKey, JSON_PRETTY_PRINT) }}</code>
                                @else
                                    <code style="font-size:.77rem;">{{ $traceKey }}</code>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        @endif
    </div>
</div>

{{-- ============================================================
     Fair Housing Review Checklist (display only — no form)
     ============================================================ --}}
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning text-dark d-flex align-items-center gap-2">
        <i class="fa-solid fa-scale-balanced"></i>
        <h6 class="mb-0">Fair Housing Review Checklist</h6>
        <span class="badge badge-dark ms-auto" style="font-size:.7rem;">Display Only — No Workflow</span>
    </div>
    <div class="card-body" style="font-size:.85rem;">
        <p class="text-muted mb-3" style="font-size:.82rem;">
            The following checklist is for internal reference only. It does not constitute legal review, compliance approval,
            or a workflow gate. No checkbox state is saved or transmitted.
        </p>
        <ul class="list-unstyled mb-0">
            <li class="d-flex align-items-start gap-2 mb-3">
                <input type="checkbox" disabled class="mt-1" style="width:1rem;height:1rem;flex-shrink:0;">
                <div>
                    <strong>Protected-class references</strong><br>
                    <span class="text-muted" style="font-size:.8rem;">
                        Confirm no dimension name, explanation key, template ID, or sentence text references a
                        protected class (race, color, national origin, religion, sex, familial status, disability,
                        or any state-level addition).
                    </span>
                </div>
            </li>
            <li class="d-flex align-items-start gap-2 mb-3">
                <input type="checkbox" disabled class="mt-1" style="width:1rem;height:1rem;flex-shrink:0;">
                <div>
                    <strong>Proxy characteristics</strong><br>
                    <span class="text-muted" style="font-size:.8rem;">
                        Confirm no dimension or output field serves as a proxy for a protected characteristic
                        (e.g., neighborhood name, school district, zip code used as a demographic signal).
                    </span>
                </div>
            </li>
            <li class="d-flex align-items-start gap-2 mb-3">
                <input type="checkbox" disabled class="mt-1" style="width:1rem;height:1rem;flex-shrink:0;">
                <div>
                    <strong>Steering language</strong><br>
                    <span class="text-muted" style="font-size:.8rem;">
                        Confirm no sentence or summary text steers a consumer toward or away from a property, area,
                        or listing based on characteristics of the neighborhood or its residents.
                    </span>
                </div>
            </li>
            <li class="d-flex align-items-start gap-2 mb-3">
                <input type="checkbox" disabled class="mt-1" style="width:1rem;height:1rem;flex-shrink:0;">
                <div>
                    <strong>Recommendation language</strong><br>
                    <span class="text-muted" style="font-size:.8rem;">
                        Confirm the report contains no language that constitutes a recommendation, suggestion,
                        or endorsement of a listing to a consumer or agent. Output must remain neutral and descriptive.
                    </span>
                </div>
            </li>
            <li class="d-flex align-items-start gap-2 mb-3">
                <input type="checkbox" disabled class="mt-1" style="width:1rem;height:1rem;flex-shrink:0;">
                <div>
                    <strong>Ranking language</strong><br>
                    <span class="text-muted" style="font-size:.8rem;">
                        Confirm the report does not rank, order, or score listings in a way that could be construed
                        as preference or qualification assessment for a consumer or protected class.
                    </span>
                </div>
            </li>
            <li class="d-flex align-items-start gap-2 mb-3">
                <input type="checkbox" disabled class="mt-1" style="width:1rem;height:1rem;flex-shrink:0;">
                <div>
                    <strong>Suitability language</strong><br>
                    <span class="text-muted" style="font-size:.8rem;">
                        Confirm no sentence or field implies that a listing is or is not "suitable," "appropriate,"
                        or "right" for a specific type of buyer, tenant, or household composition.
                    </span>
                </div>
            </li>
        </ul>
    </div>
</div>

@endsection
