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
     SECTION 5: Current Review Status Banner
     ============================================================ --}}
@php
    $statusBannerMap = [
        'pending_review'      => ['class' => 'alert-secondary',  'icon' => 'fa-clock',          'label' => 'Pending Review'],
        'in_review'           => ['class' => 'alert-info',       'icon' => 'fa-magnifying-glass','label' => 'In Review'],
        'approved'            => ['class' => 'alert-success',    'icon' => 'fa-circle-check',   'label' => 'Approved'],
        'approved_with_notes' => ['class' => 'alert-primary',    'icon' => 'fa-circle-check',   'label' => 'Approved with Notes'],
        'flagged'             => ['class' => 'alert-warning',    'icon' => 'fa-flag',            'label' => 'Flagged'],
        'rejected'            => ['class' => 'alert-danger',     'icon' => 'fa-ban',             'label' => 'Rejected'],
    ];
    $statusBanner = $latestReviewStatus ? ($statusBannerMap[$latestReviewStatus] ?? null) : null;
@endphp

<div class="mb-4">
    @if($statusBanner)
    <div class="alert {{ $statusBanner['class'] }} d-flex align-items-center gap-2 mb-0" role="alert">
        <i class="fa-solid {{ $statusBanner['icon'] }}"></i>
        <span><strong>Current Review Status:</strong> {{ $statusBanner['label'] }}</span>
        <span class="ml-auto text-muted small">Based on most recent review entry</span>
    </div>
    @else
    <div class="alert alert-light border d-flex align-items-center gap-2 mb-0" role="alert">
        <i class="fa-solid fa-circle-question text-muted"></i>
        <span class="text-muted"><strong>Current Review Status:</strong> No review entries yet</span>
    </div>
    @endif
</div>

{{-- ============================================================
     SECTION 6: Review History
     ============================================================ --}}
@include('admin.bya.review._history', ['reviewLogs' => $reviewLogs])

{{-- ============================================================
     SECTION 7: Submit New Review Entry
     ============================================================ --}}
@include('admin.bya.review._form', ['record' => $record])

@endsection
