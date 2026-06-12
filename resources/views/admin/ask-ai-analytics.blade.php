@extends('layouts.admin')
@section('content')

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Ask AI — Usage &amp; Cost Analytics</h5>
            <small class="text-muted">Admin-only read-only dashboard. All sections reflect the active date filter. Not linked from any navigation.</small>
        </div>
    </div>
    <div class="card-body">

        {{-- ── Date filter form ────────────────────────────────────────────── --}}
        <form method="GET" action="{{ route('admin.ask-ai.analytics') }}" class="mb-2">
            <div class="d-flex flex-wrap align-items-center" style="gap:0.5rem;">
                <label class="font-weight-bold mb-0 mr-1">Quick Filter:</label>
                @foreach(['today' => 'Today', 'last_7' => 'Last 7 Days', 'last_30' => 'Last 30 Days'] as $key => $label)
                <a href="{{ route('admin.ask-ai.analytics', ['preset' => $key]) }}"
                   class="btn btn-sm {{ $preset === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $label }}
                </a>
                @endforeach
                <span class="text-muted mx-1">|</span>
                <label class="mb-0">Custom:</label>
                <input type="hidden" name="preset" value="custom">
                <input type="date" name="from" class="form-control form-control-sm" style="width:auto;"
                       value="{{ $preset === 'custom' ? $rawFrom : '' }}">
                <span class="mb-0">to</span>
                <input type="date" name="to" class="form-control form-control-sm" style="width:auto;"
                       value="{{ $preset === 'custom' ? $rawTo : '' }}">
                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
            </div>
        </form>
        <p class="text-muted small mb-0">
            Active range: <strong>{{ $dateFrom->toDateString() }}</strong>
            &ndash; <strong>{{ $dateTo->toDateString() }}</strong>
            &mdash; all sections below are scoped to this range.
        </p>

    </div>
</div>

{{-- ── Summary Cards ───────────────────────────────────────────────────────── --}}

{{-- Fixed-window reference row (always Today / Last 7 / Last 30) --}}
<div class="row mb-2">
    <div class="col-12 mb-1"><small class="text-muted font-weight-bold text-uppercase">Questions</small></div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Questions Today</div>
                <div class="h3 mb-0">{{ number_format($questionsToday) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Questions Last 7 Days</div>
                <div class="h3 mb-0">{{ number_format($questionsLast7) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Questions Last 30 Days</div>
                <div class="h3 mb-0">{{ number_format($questionsLast30) }}</div>
            </div>
        </div>
    </div>

    <div class="col-12 mb-1"><small class="text-muted font-weight-bold text-uppercase">Estimated Cost</small></div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Estimated Cost Today</div>
                <div class="h3 mb-0">${{ number_format($costToday, 4) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Estimated Cost Last 7 Days</div>
                <div class="h3 mb-0">${{ number_format($costLast7, 4) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Estimated Cost Last 30 Days</div>
                <div class="h3 mb-0">${{ number_format($costLast30, 4) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Active-filter summary row --}}
<div class="row mb-4">
    <div class="col-12 mb-1"><small class="text-muted font-weight-bold text-uppercase">Active Filter: {{ $dateFrom->toDateString() }} &ndash; {{ $dateTo->toDateString() }}</small></div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Total Questions</div>
                <div class="h3 mb-0">{{ number_format($totalQuestions) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Estimated Total Cost (USD)</div>
                <div class="h3 mb-0">${{ number_format($totalCost, 4) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Average Cost Per Question</div>
                <div class="h3 mb-0">${{ number_format($avgCostPerQuestion, 6) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Unique Listings Using Ask AI</div>
                <div class="h3 mb-0">{{ number_format($uniqueListings) }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-3">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="text-muted small mb-1">Rate Limited Requests</div>
                <div class="h3 mb-0">{{ number_format($rateLimitedCount) }}</div>
            </div>
        </div>
    </div>

</div>

{{-- ── Phase 5: Outcome-Category Breakdown ─────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Outcome Category Breakdown</h6>
        <small class="text-muted">DB Hit % = questions served from snapshot without OpenAI call.</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Outcome Category</th>
                        <th class="text-right">Count</th>
                        <th class="text-right">% of Outcomes</th>
                        <th>Meaning</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $outcomeDescriptions = [
                            'database_hit'                   => 'Answer served from knowledge snapshot — no OpenAI call made.',
                            'openai_fallback'                => 'No snapshot match; OpenAI was called to generate a response.',
                            'blank_information_not_provided' => 'Field found in snapshot but had no value; "not provided" returned without OpenAI.',
                            'restricted'                     => 'Field is compliance-sensitive; surfacing blocked per governance rules.',
                            'blocked_restricted'             => 'Question blocked by classifier as restricted/prohibited topic.',
                            'unsupported'                    => 'Question not mappable to any listing field or FAQ topic.',
                            'error'                          => 'Pipeline error; response could not be generated.',
                        ];
                        $outcomeColors = [
                            'database_hit'                   => 'success',
                            'openai_fallback'                => 'warning',
                            'blank_information_not_provided' => 'info',
                            'restricted'                     => 'secondary',
                            'blocked_restricted'             => 'secondary',
                            'unsupported'                    => 'light',
                            'error'                          => 'danger',
                        ];
                    @endphp
                    @forelse($outcomeData as $row)
                    <tr>
                        <td>
                            <span class="badge badge-{{ $outcomeColors[$row['key']] ?? 'light' }}">{{ $row['label'] }}</span>
                        </td>
                        <td class="text-right">{{ number_format($row['count']) }}</td>
                        <td class="text-right">
                            @if($row['count'] > 0)
                                <div class="d-flex align-items-center justify-content-end">
                                    <span class="mr-2">{{ $row['percentage'] }}%</span>
                                    <div class="progress" style="width:80px;height:8px;">
                                        <div class="progress-bar bg-{{ $outcomeColors[$row['key']] ?? 'secondary' }}"
                                             style="width:{{ $row['percentage'] }}%"></div>
                                    </div>
                                </div>
                            @else
                                0%
                            @endif
                        </td>
                        <td><small class="text-muted">{{ $outcomeDescriptions[$row['key']] ?? '' }}</small></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted">No outcome data for this period. (outcome_category may not be populated yet.)</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Phase 5: Cost Savings Card ───────────────────────────────────────────── --}}
<div class="card mb-4 border-success">
    <div class="card-header bg-success text-white">
        <h6 class="mb-0">Estimated Cost Savings (DB Hits vs. OpenAI Calls)</h6>
    </div>
    <div class="card-body">
        <div class="row text-center mb-3">
            <div class="col-md-3 mb-2">
                <div class="text-muted small">DB Hits in Period</div>
                <div class="h4 text-success mb-0">{{ number_format($savingsData['db_hits']) }}</div>
                <small class="text-muted">{{ $savingsData['db_hit_pct'] }}% of all questions</small>
            </div>
            <div class="col-md-3 mb-2">
                <div class="text-muted small">Avg Tokens Saved / Hit</div>
                <div class="h4 mb-0">{{ number_format($savingsData['avg_tokens_per_db_hit']) }}</div>
                <small class="text-muted">configurable constant</small>
            </div>
            <div class="col-md-3 mb-2">
                <div class="text-muted small">Total Tokens Avoided</div>
                <div class="h4 mb-0">{{ number_format($savingsData['tokens_avoided']) }}</div>
                <small class="text-muted">in active date range</small>
            </div>
            <div class="col-md-3 mb-2">
                <div class="text-muted small">Estimated USD Saved</div>
                <div class="h4 text-success mb-0">${{ number_format($savingsData['estimated_saved_usd'], 4) }}</div>
                <small class="text-muted">≈ ${{ number_format($savingsData['monthly_estimate_usd'], 4) }}/mo extrapolated</small>
            </div>
        </div>
        <p class="text-muted small mb-0">
            <strong>Methodology:</strong> {{ $savingsData['methodology_note'] }}
        </p>
    </div>
</div>

{{-- ── Phase 5: Daily Outcome Trend ─────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Daily Outcome Breakdown</h6>
        <small class="text-muted">Per-day counts for each outcome category within the active date range (newest first, up to 30 days).</small>
    </div>
    <div class="card-body p-0">
        @php
            // Pivot $dailyOutcomeTrend (date, outcome_category, cnt) → keyed by date
            $trendByDate = [];
            foreach ($dailyOutcomeTrend as $row) {
                $trendByDate[$row->date][$row->outcome_category] = (int) $row->cnt;
            }
            krsort($trendByDate);
            $trendDates = array_slice(array_keys($trendByDate), 0, 30);
            $trendCategories = [
                'database_hit'                   => 'DB Hit',
                'openai_fallback'                => 'OpenAI',
                'blank_information_not_provided' => 'Blank',
                'restricted'                     => 'Restricted',
                'blocked_restricted'             => 'Blocked',
                'unsupported'                    => 'Unsupported',
                'error'                          => 'Error',
            ];
        @endphp
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:0.82rem;">
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
                        <th class="text-right text-success">DB Hit</th>
                        <th class="text-right text-warning">OpenAI</th>
                        <th class="text-right">Blank</th>
                        <th class="text-right">Restricted</th>
                        <th class="text-right">Blocked</th>
                        <th class="text-right">Unsupported</th>
                        <th class="text-right text-danger">Error</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trendDates as $date)
                    @php
                        $dayRow = $trendByDate[$date] ?? [];
                        $dayTotal = array_sum($dayRow);
                        $dbHitPct = $dayTotal > 0
                            ? round(($dayRow['database_hit'] ?? 0) / $dayTotal * 100)
                            : 0;
                    @endphp
                    <tr>
                        <td class="font-weight-bold">{{ $date }}</td>
                        <td class="text-right text-success">
                            {{ number_format($dayRow['database_hit'] ?? 0) }}
                            @if($dayTotal > 0)
                            <small class="text-muted">({{ $dbHitPct }}%)</small>
                            @endif
                        </td>
                        <td class="text-right text-warning">{{ number_format($dayRow['openai_fallback'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($dayRow['blank_information_not_provided'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($dayRow['restricted'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($dayRow['blocked_restricted'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($dayRow['unsupported'] ?? 0) }}</td>
                        <td class="text-right text-danger">{{ number_format($dayRow['error'] ?? 0) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($dayTotal) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted">No outcome data for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Phase 5: Weekly Outcome Breakdown ────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Weekly Outcome Breakdown</h6>
        <small class="text-muted">Per-week counts (week starting Monday) and percentages for each outcome category.</small>
    </div>
    <div class="card-body p-0">
        @php
            $weekTrend = [];
            foreach ($weeklyOutcomeTrend as $row) {
                $weekTrend[$row->week_start][$row->outcome_category] = (int) $row->cnt;
            }
            krsort($weekTrend);
            $weekDates = array_keys($weekTrend);
        @endphp
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:0.82rem;">
                <thead class="thead-light">
                    <tr>
                        <th>Week Of</th>
                        <th class="text-right text-success">DB Hit</th>
                        <th class="text-right text-success">DB Hit %</th>
                        <th class="text-right text-warning">OpenAI</th>
                        <th class="text-right text-warning">OpenAI %</th>
                        <th class="text-right">Blank</th>
                        <th class="text-right">Restricted</th>
                        <th class="text-right">Blocked</th>
                        <th class="text-right">Unsupported</th>
                        <th class="text-right text-danger">Error</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($weekDates as $weekStart)
                    @php
                        $wRow   = $weekTrend[$weekStart] ?? [];
                        $wTotal = array_sum($wRow);
                        $wDbHit = $wRow['database_hit'] ?? 0;
                        $wOai   = $wRow['openai_fallback'] ?? 0;
                        $wDbPct = $wTotal > 0 ? round($wDbHit / $wTotal * 100, 1) : 0;
                        $wOaiPct = $wTotal > 0 ? round($wOai / $wTotal * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td class="font-weight-bold">{{ $weekStart }}</td>
                        <td class="text-right text-success">{{ number_format($wDbHit) }}</td>
                        <td class="text-right text-success">{{ $wDbPct }}%</td>
                        <td class="text-right text-warning">{{ number_format($wOai) }}</td>
                        <td class="text-right text-warning">{{ $wOaiPct }}%</td>
                        <td class="text-right">{{ number_format($wRow['blank_information_not_provided'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($wRow['restricted'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($wRow['blocked_restricted'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($wRow['unsupported'] ?? 0) }}</td>
                        <td class="text-right text-danger">{{ number_format($wRow['error'] ?? 0) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($wTotal) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center text-muted">No outcome data for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Phase 5: Monthly Outcome Breakdown ───────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Monthly Outcome Breakdown</h6>
        <small class="text-muted">Per-month counts and percentages for each outcome category.</small>
    </div>
    <div class="card-body p-0">
        @php
            $monthTrend = [];
            foreach ($monthlyOutcomeTrend as $row) {
                $monthTrend[$row->month][$row->outcome_category] = (int) $row->cnt;
            }
            krsort($monthTrend);
            $monthKeys = array_keys($monthTrend);
        @endphp
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:0.82rem;">
                <thead class="thead-light">
                    <tr>
                        <th>Month</th>
                        <th class="text-right text-success">DB Hit</th>
                        <th class="text-right text-success">DB Hit %</th>
                        <th class="text-right text-warning">OpenAI</th>
                        <th class="text-right text-warning">OpenAI %</th>
                        <th class="text-right">Blank</th>
                        <th class="text-right">Restricted</th>
                        <th class="text-right">Blocked</th>
                        <th class="text-right">Unsupported</th>
                        <th class="text-right text-danger">Error</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monthKeys as $month)
                    @php
                        $mRow    = $monthTrend[$month] ?? [];
                        $mTotal  = array_sum($mRow);
                        $mDbHit  = $mRow['database_hit'] ?? 0;
                        $mOai    = $mRow['openai_fallback'] ?? 0;
                        $mDbPct  = $mTotal > 0 ? round($mDbHit / $mTotal * 100, 1) : 0;
                        $mOaiPct = $mTotal > 0 ? round($mOai / $mTotal * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td class="font-weight-bold">{{ $month }}</td>
                        <td class="text-right text-success">{{ number_format($mDbHit) }}</td>
                        <td class="text-right text-success">{{ $mDbPct }}%</td>
                        <td class="text-right text-warning">{{ number_format($mOai) }}</td>
                        <td class="text-right text-warning">{{ $mOaiPct }}%</td>
                        <td class="text-right">{{ number_format($mRow['blank_information_not_provided'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($mRow['restricted'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($mRow['blocked_restricted'] ?? 0) }}</td>
                        <td class="text-right">{{ number_format($mRow['unsupported'] ?? 0) }}</td>
                        <td class="text-right text-danger">{{ number_format($mRow['error'] ?? 0) }}</td>
                        <td class="text-right font-weight-bold">{{ number_format($mTotal) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center text-muted">No outcome data for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Phase 5: Top Unanswered Questions ───────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:0.5rem;">
        <div>
            <h6 class="mb-0">Top Unanswered Questions <small class="text-muted">(OpenAI Fallback — Top 50 by Frequency)</small></h6>
            <small class="text-muted">Questions that fell through to OpenAI — candidates for canonical mapping expansion.</small>
            <div class="mt-1 p-2 rounded" style="background:#fff3cd;border:1px solid #ffc107;font-size:0.78rem;">
                <strong>Note:</strong> Only the SHA-256 <code>question_hash</code> is stored in usage logs — the original question text is not persisted. To identify what question a hash represents, correlate it against the matching hash in a test environment or add question-text logging (see audit doc §9.1 for the recommended schema change).
            </div>
        </div>
        <form method="GET" action="{{ route('admin.ask-ai.analytics') }}" class="d-flex align-items-center" style="gap:0.4rem;">
            <input type="hidden" name="preset" value="{{ $preset }}">
            @if($preset === 'custom')
            <input type="hidden" name="from" value="{{ $rawFrom }}">
            <input type="hidden" name="to" value="{{ $rawTo }}">
            @endif
            <select name="fallback_role" class="form-control form-control-sm" style="width:auto;">
                <option value="">All Roles</option>
                @foreach(['seller','buyer','landlord','tenant'] as $r)
                <option value="{{ $r }}" {{ $fallbackRoleFilter === $r ? 'selected' : '' }}>{{ ucfirst($r) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Question Hash</th>
                        <th>Role</th>
                        <th class="text-right">Occurrences</th>
                        <th>First Seen</th>
                        <th>Last Seen</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topFallbackQuestions as $i => $row)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td><code style="font-size:0.75rem;">{{ $row->question_hash }}</code></td>
                        <td><span class="badge badge-secondary">{{ $row->listing_type }}</span></td>
                        <td class="text-right font-weight-bold">{{ number_format($row->occurrences) }}</td>
                        <td><small>{{ \Carbon\Carbon::parse($row->first_seen)->toDateString() }}</small></td>
                        <td><small>{{ \Carbon\Carbon::parse($row->last_seen)->toDateString() }}</small></td>
                        <td><small class="text-muted">Add to FAQ_KEY_KEYWORD_MAP</small></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted">No OpenAI fallback questions in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Phase 5: Role-Scoped Coverage ───────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">Coverage by Role</h6>
        <small class="text-muted">Registry-mapped: total fields in AskAiFieldQuestionRegistryService for the role. Snapshot-covered: distinct canonical keys stored across all ready snapshots. Answerable: keys with a non-empty stored answer. Restricted: compliance-sensitive keys excluded from public responses.</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Role</th>
                        <th class="text-right">Registry-Mapped</th>
                        <th class="text-right">Snapshot-Covered</th>
                        <th class="text-right">Coverage %</th>
                        <th class="text-right">Uncovered Gap</th>
                        <th class="text-right">Answerable</th>
                        <th class="text-right">Restricted</th>
                        <th class="text-right">DB-Hit Rate (Period)</th>
                        <th class="text-right">Total Questions (Period)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roleCoverageData as $row)
                    @php $uncovered = max(0, $row['registry_mapped'] - $row['snapshot_covered']); @endphp
                    <tr>
                        <td><strong>{{ ucfirst($row['role']) }}</strong></td>
                        <td class="text-right">{{ number_format($row['registry_mapped']) }}</td>
                        <td class="text-right">{{ number_format($row['snapshot_covered']) }}</td>
                        <td class="text-right">
                            @php $pct = $row['coverage_pct']; @endphp
                            <span class="{{ $pct >= 80 ? 'text-success' : ($pct >= 50 ? 'text-warning' : 'text-danger') }}">
                                {{ $pct }}%
                            </span>
                        </td>
                        <td class="text-right">
                            <span class="{{ $uncovered > 20 ? 'text-danger' : ($uncovered > 5 ? 'text-warning' : 'text-success') }}">
                                {{ number_format($uncovered) }}
                            </span>
                            <small class="text-muted d-block" style="font-size:0.7rem;">registry – covered</small>
                        </td>
                        <td class="text-right">{{ number_format($row['answerable']) }}</td>
                        <td class="text-right">{{ number_format($row['restricted']) }}</td>
                        <td class="text-right">
                            <span class="{{ $row['db_hit_rate'] >= 50 ? 'text-success' : ($row['db_hit_rate'] >= 20 ? 'text-warning' : 'text-muted') }}">
                                {{ $row['db_hit_rate'] }}%
                            </span>
                            <small class="text-muted">({{ number_format($row['db_hits']) }} hits)</small>
                        </td>
                        <td class="text-right">{{ number_format($row['total_questions']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted">No role coverage data available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Model Usage Table ───────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Model Usage</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Model</th>
                        <th class="text-right">Questions</th>
                        <th class="text-right">Prompt Tokens</th>
                        <th class="text-right">Completion Tokens</th>
                        <th class="text-right">Total Tokens</th>
                        <th class="text-right">Estimated Cost (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($modelUsage as $row)
                    <tr>
                        <td>{{ $row->model }}</td>
                        <td class="text-right">{{ number_format($row->questions) }}</td>
                        <td class="text-right">{{ number_format($row->prompt_tokens) }}</td>
                        <td class="text-right">{{ number_format($row->completion_tokens) }}</td>
                        <td class="text-right">{{ number_format($row->total_tokens) }}</td>
                        <td class="text-right">${{ number_format((float)$row->estimated_cost, 4) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">No model data for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Question Type Table ─────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Question Types</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Question Type</th>
                        <th class="text-right">Count</th>
                        <th class="text-right">% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($questionTypeData as $row)
                    <tr>
                        <td>{{ $row['type'] }}</td>
                        <td class="text-right">{{ number_format($row['questions']) }}</td>
                        <td class="text-right">{{ $row['percentage'] }}%</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Top 25 Listing Analytics ────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Top 25 Listings by Question Count</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Listing ID</th>
                        <th>Listing Type</th>
                        <th class="text-right">Questions</th>
                        <th class="text-right">Estimated Cost (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topListings as $row)
                    <tr>
                        <td>{{ $row->listing_id }}</td>
                        <td>{{ $row->listing_type }}</td>
                        <td class="text-right">{{ number_format($row->questions) }}</td>
                        <td class="text-right">${{ number_format((float)$row->estimated_cost, 4) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted">No listing data for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Rate Limiter Analytics ──────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Rate Limiter Hits</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Limit Type</th>
                        <th class="text-right">Hits</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rateLimiterData as $row)
                    <tr>
                        <td>{{ $row['error_code'] }}</td>
                        <td class="text-right">{{ number_format($row['hits']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Daily Cost Table ────────────────────────────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Daily Cost (up to 30 rows, within active range)</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Date</th>
                        <th class="text-right">Questions</th>
                        <th class="text-right">Prompt Tokens</th>
                        <th class="text-right">Completion Tokens</th>
                        <th class="text-right">Total Tokens</th>
                        <th class="text-right">Estimated Cost (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dailyCost as $row)
                    <tr>
                        <td>{{ $row->date }}</td>
                        <td class="text-right">{{ number_format($row->questions) }}</td>
                        <td class="text-right">{{ number_format($row->prompt_tokens) }}</td>
                        <td class="text-right">{{ number_format($row->completion_tokens) }}</td>
                        <td class="text-right">{{ number_format($row->total_tokens) }}</td>
                        <td class="text-right">${{ number_format((float)$row->estimated_cost, 4) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">No data for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
