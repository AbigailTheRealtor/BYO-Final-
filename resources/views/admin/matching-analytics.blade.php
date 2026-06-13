@extends('layouts.admin')

@section('title', 'Matching Analytics')

@section('content')
<div class="container-fluid py-4">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold"><i class="fa-solid fa-chart-bar me-2 text-primary"></i>Matching Analytics</h4>
            <small class="text-muted">P7 — Score snapshots, readiness funnel, and recommendation effectiveness. No consumer PII exposed.</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            @foreach(['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days', 'all' => 'All time'] as $val => $label)
                <a href="{{ route('admin.matching.analytics', ['range' => $val]) }}"
                   class="btn btn-sm {{ $range === $val ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if($dateFrom)
        <p class="text-muted small mb-3">
            Showing data from <strong>{{ $dateFrom->format('M j, Y') }}</strong> to <strong>{{ $dateTo->format('M j, Y') }}</strong>.
        </p>
    @else
        <p class="text-muted small mb-3">Showing all available data (no date filter).</p>
    @endif

    {{-- ── Summary Cards ────────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-primary">{{ number_format($summaryCards['total_bids']) }}</div>
                    <div class="small text-muted">Distinct Bids Tracked</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-info">{{ number_format($summaryCards['total_snapshots']) }}</div>
                    <div class="small text-muted">Score Snapshots</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-success">
                        {{ $summaryCards['avg_score'] !== null ? $summaryCards['avg_score'] : '—' }}
                    </div>
                    <div class="small text-muted">Avg Compatibility Score</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-warning">{{ number_format($summaryCards['full_ready_count']) }}</div>
                    <div class="small text-muted">Full Match Ready Snapshots</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-dark">{{ number_format($summaryCards['hired_count']) }}</div>
                    <div class="small text-muted">Agent Hired Events</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3 col-xl">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="fs-2 fw-bold text-secondary">{{ number_format($summaryCards['rec_interactions']) }}</div>
                    <div class="small text-muted">Rec. Attributed Interactions</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Readiness Funnel by Role + Property Type ─────────────────────────── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom fw-semibold">
            <i class="fa-solid fa-filter me-2 text-primary"></i>Readiness Funnel by Role &amp; Property Type
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Role</th>
                            <th class="text-end">Total Snapshots</th>
                            <th class="text-end text-danger">Not Ready</th>
                            <th class="text-end text-warning">Quick Match Ready</th>
                            <th class="text-end text-success">Full Match Ready</th>
                            <th style="min-width:200px">Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($funnelData as $row)
                        {{-- Role summary row --}}
                        <tr class="table-light fw-semibold"
                            data-bs-toggle="collapse"
                            data-bs-target="#funnel-pt-{{ $row['role'] }}"
                            style="cursor:pointer;"
                            title="Click to expand property type breakdown">
                            <td>
                                <i class="fa-solid fa-chevron-right me-1 small text-muted"></i>
                                {{ ucfirst($row['role']) }}
                            </td>
                            <td class="text-end">{{ number_format($row['total']) }}</td>
                            <td class="text-end text-danger">
                                {{ number_format($row['not_ready']) }}
                                <small class="text-muted">({{ $row['not_ready_pct'] }}%)</small>
                            </td>
                            <td class="text-end text-warning">
                                {{ number_format($row['quick_match_ready']) }}
                                <small class="text-muted">({{ $row['quick_ready_pct'] }}%)</small>
                            </td>
                            <td class="text-end text-success">
                                {{ number_format($row['full_match_ready']) }}
                                <small class="text-muted">({{ $row['full_ready_pct'] }}%)</small>
                            </td>
                            <td>
                                @if($row['total'] > 0)
                                <div class="progress" style="height:18px;">
                                    <div class="progress-bar bg-danger" style="width:{{ $row['not_ready_pct'] }}%"></div>
                                    <div class="progress-bar bg-warning" style="width:{{ $row['quick_ready_pct'] }}%"></div>
                                    <div class="progress-bar bg-success" style="width:{{ $row['full_ready_pct'] }}%"></div>
                                </div>
                                @else
                                <span class="text-muted small">No data</span>
                                @endif
                            </td>
                        </tr>
                        {{-- Property type breakdown (collapsed by default) --}}
                        @if($row['by_property_type']->isNotEmpty())
                        <tr class="collapse" id="funnel-pt-{{ $row['role'] }}">
                            <td colspan="6" class="p-0">
                                <table class="table table-sm mb-0 border-0">
                                    <tbody>
                                        @foreach($row['by_property_type'] as $pt)
                                        <tr class="table-secondary">
                                            <td style="padding-left:2.5rem; width:200px;">
                                                <span class="text-muted small">↳ {{ $pt['property_type'] ?? 'Not specified' }}</span>
                                            </td>
                                            <td class="text-end small">{{ number_format($pt['total']) }}</td>
                                            <td class="text-end small text-danger">
                                                {{ number_format($pt['not_ready']) }}
                                                <span class="text-muted">({{ $pt['not_ready_pct'] }}%)</span>
                                            </td>
                                            <td class="text-end small text-warning">
                                                {{ number_format($pt['quick_match_ready']) }}
                                                <span class="text-muted">({{ $pt['quick_ready_pct'] }}%)</span>
                                            </td>
                                            <td class="text-end small text-success">
                                                {{ number_format($pt['full_match_ready']) }}
                                                <span class="text-muted">({{ $pt['full_ready_pct'] }}%)</span>
                                            </td>
                                            <td style="min-width:200px;">
                                                @if($pt['total'] > 0)
                                                <div class="progress" style="height:10px;">
                                                    <div class="progress-bar bg-danger" style="width:{{ $pt['not_ready_pct'] }}%"></div>
                                                    <div class="progress-bar bg-warning" style="width:{{ $pt['quick_ready_pct'] }}%"></div>
                                                    <div class="progress-bar bg-success" style="width:{{ $pt['full_ready_pct'] }}%"></div>
                                                </div>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small">
            Counts represent score snapshots (one per bid lifecycle event). Click a role row to see the property type breakdown.
        </div>
    </div>

    {{-- ── Score Distribution ───────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom fw-semibold">
                    <i class="fa-solid fa-chart-column me-2 text-info"></i>Score Distribution by Role &amp; Type
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Role</th>
                                    <th>Score Type</th>
                                    <th class="text-end">Count</th>
                                    <th class="text-end">Avg</th>
                                    <th class="text-end">Min</th>
                                    <th class="text-end">Max</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($scoreDistribution['by_role'] as $row)
                                <tr>
                                    <td class="text-capitalize">{{ $row->role }}</td>
                                    <td>
                                        <span class="badge {{ $row->score_type === 'full_match' ? 'bg-success' : 'bg-warning text-dark' }}">
                                            {{ $row->score_type === 'full_match' ? 'Full Match' : 'Quick Match' }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ number_format($row->cnt) }}</td>
                                    <td class="text-end">{{ $row->avg_score }}</td>
                                    <td class="text-end">{{ $row->min_score }}</td>
                                    <td class="text-end">{{ $row->max_score }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">No scored snapshots in this range.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom fw-semibold">
                    <i class="fa-solid fa-sliders me-2 text-info"></i>Score Bucket Distribution
                </div>
                <div class="card-body">
                    @foreach(['quick_match' => 'Quick Match', 'full_match' => 'Full Match'] as $typeKey => $typeLabel)
                        @php
                            $typeBuckets = $scoreDistribution['buckets']->get($typeKey, collect());
                            $typeTotal   = $typeBuckets->sum('cnt');
                        @endphp
                        <h6 class="fw-semibold mb-2">{{ $typeLabel }}</h6>
                        @if($typeBuckets->isEmpty())
                            <p class="text-muted small mb-3">No data.</p>
                        @else
                            <div class="mb-3">
                                @foreach($typeBuckets->sortBy('bucket') as $bucket)
                                    @php
                                        $pct = $typeTotal > 0 ? round($bucket->cnt / $typeTotal * 100) : 0;
                                        $label = $bucket->bucket . '–' . min(100, $bucket->bucket + 9);
                                    @endphp
                                    <div class="d-flex align-items-center mb-1" style="gap:6px;">
                                        <span class="text-muted small" style="width:50px;">{{ $label }}</span>
                                        <div class="progress flex-grow-1" style="height:14px;">
                                            <div class="progress-bar {{ $typeKey === 'full_match' ? 'bg-success' : 'bg-warning' }}"
                                                 style="width:{{ $pct }}%"></div>
                                        </div>
                                        <span class="small text-muted" style="width:55px; text-align:right;">
                                            {{ number_format($bucket->cnt) }} ({{ $pct }}%)
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ── True Stage-to-Stage Conversion Funnel ────────────────────────────── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom fw-semibold">
            <i class="fa-solid fa-arrow-trend-up me-2 text-success"></i>Stage-to-Stage Conversion Funnel
            <small class="text-muted fw-normal ms-2">
                Not Ready → Quick Match → Full Match → Hired &nbsp;·&nbsp;
                Sourced from immutable first-entry funnel timestamps.
            </small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Role</th>
                            <th class="text-end" title="Bids that entered any funnel stage">Total in Funnel</th>
                            <th class="text-end">Not Ready</th>
                            <th class="text-end">
                                Quick Ready
                                <small class="d-block text-muted fw-normal" style="font-size:0.7em;">(of Not Ready)</small>
                            </th>
                            <th class="text-end">
                                Full Ready
                                <small class="d-block text-muted fw-normal" style="font-size:0.7em;">(of Quick Ready)</small>
                            </th>
                            <th class="text-end">Submitted</th>
                            <th class="text-end">
                                Accepted
                                <small class="d-block text-muted fw-normal" style="font-size:0.7em;">(of Submitted)</small>
                            </th>
                            <th class="text-end">
                                Hired
                                <small class="d-block text-muted fw-normal" style="font-size:0.7em;">(of Full Ready)</small>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($conversionRates as $row)
                        <tr>
                            <td class="fw-semibold text-capitalize">{{ $row['role'] }}</td>
                            <td class="text-end">{{ number_format($row['total']) }}</td>
                            <td class="text-end">{{ number_format($row['not_ready']) }}</td>
                            <td class="text-end">
                                {{ number_format($row['quick_match_ready']) }}
                                @if($row['not_ready'] > 0)
                                <span class="badge bg-warning text-dark ms-1">{{ $row['not_to_quick_rate'] }}%</span>
                                @endif
                            </td>
                            <td class="text-end">
                                {{ number_format($row['full_match_ready']) }}
                                @if($row['quick_match_ready'] > 0)
                                <span class="badge bg-success ms-1">{{ $row['quick_to_full_rate'] }}%</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($row['submitted']) }}</td>
                            <td class="text-end">
                                {{ number_format($row['accepted']) }}
                                @if($row['submitted'] > 0)
                                <span class="badge {{ $row['submit_to_accept'] >= 50 ? 'bg-success' : 'bg-secondary' }} ms-1">
                                    {{ $row['submit_to_accept'] }}%
                                </span>
                                @endif
                            </td>
                            <td class="text-end">
                                {{ number_format($row['hired']) }}
                                @if($row['full_match_ready'] > 0)
                                <span class="badge {{ $row['full_to_hired_rate'] >= 30 ? 'bg-success' : 'bg-secondary' }} ms-1">
                                    {{ $row['full_to_hired_rate'] }}%
                                </span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white text-muted small">
            Stage-to-stage rates use first-entry funnel timestamps as denominator.
            Each bid is counted at most once per stage, regardless of how many score snapshots it has.
            "Quick Ready % (of Not Ready)" = bids that progressed past Not Ready into Quick Match Ready.
        </div>
    </div>

    {{-- ── Recommendation Effectiveness ────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom fw-semibold">
            <i class="fa-solid fa-thumbs-up me-2 text-primary"></i>Recommendation Effectiveness
            <small class="text-muted fw-normal ms-2">
                Bid views are attributed when the listing page is loaded with <code>?from_rec=1&amp;surface=&lt;surface&gt;</code>.
            </small>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded bg-light text-center">
                        <div class="fs-4 fw-bold text-primary">{{ $recommendationData['ctr'] }}%</div>
                        <div class="small text-muted">Rec. Click-Through Rate</div>
                        <div class="text-muted" style="font-size:0.75em;">{{ number_format($recommendationData['viewed_rec']) }} / {{ number_format($recommendationData['viewed_total']) }} views</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded bg-light text-center">
                        <div class="fs-4 fw-bold text-success">{{ $recommendationData['rec_accept_rate'] }}%</div>
                        <div class="small text-muted">Rec. Acceptance Rate</div>
                        <div class="text-muted" style="font-size:0.75em;">{{ number_format($recommendationData['accepted_rec']) }} accepted via rec</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded bg-light text-center">
                        <div class="fs-4 fw-bold text-dark">{{ $recommendationData['rec_hire_rate'] }}%</div>
                        <div class="small text-muted">Rec. Hire Rate</div>
                        <div class="text-muted" style="font-size:0.75em;">{{ number_format($recommendationData['hired_rec']) }} hired via rec</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded bg-light text-center">
                        <div class="fs-4 fw-bold text-secondary">{{ number_format($recommendationData['viewed_total']) }}</div>
                        <div class="small text-muted">Total Bid Views</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded bg-light text-center">
                        <div class="fs-4 fw-bold text-secondary">{{ number_format($recommendationData['accepted_total']) }}</div>
                        <div class="small text-muted">Total Accepted</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="p-3 rounded bg-light text-center">
                        <div class="fs-4 fw-bold text-secondary">{{ number_format($recommendationData['hired_total']) }}</div>
                        <div class="small text-muted">Total Hired</div>
                    </div>
                </div>
            </div>

            @if($recommendationData['by_surface']->isNotEmpty())
            <h6 class="fw-semibold mb-2">By Recommendation Surface</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Surface</th>
                            <th class="text-end">Views</th>
                            <th class="text-end">Accepted</th>
                            <th class="text-end">Hired</th>
                            <th class="text-end">Accept Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recommendationData['by_surface'] as $s)
                        <tr>
                            <td><code>{{ $s['surface'] ?? '—' }}</code></td>
                            <td class="text-end">{{ number_format($s['viewed']) }}</td>
                            <td class="text-end">{{ number_format($s['accepted']) }}</td>
                            <td class="text-end">{{ number_format($s['hired']) }}</td>
                            <td class="text-end">
                                {{ $s['viewed'] > 0 ? round($s['accepted'] / $s['viewed'] * 100, 1) : 0 }}%
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <p class="text-muted small mb-0">
                    No recommendation interaction data in this range.
                    Interactions are recorded when users view a bid page with <code>?from_rec=1&amp;surface=&lt;surface_name&gt;</code>
                    (e.g., <code>consumer_fit_card</code>, <code>coaching_panel</code>, <code>preset_completion</code>).
                </p>
            @endif
        </div>
    </div>

    {{-- ── Note on timing metrics ────────────────────────────────────────────── --}}
    <div class="alert alert-light border small mb-0">
        <i class="fa-solid fa-circle-info me-1 text-muted"></i>
        <strong>Timing metrics</strong> (time-to-ready, time-to-submit, time-to-hire) are captured in <code>bid_funnel_timestamps</code> but their reporting dashboard is deferred to a future phase.
        All timestamps are first-entry-only and immutable — they accurately reflect when each bid first entered each funnel stage.
    </div>

</div>
@endsection
