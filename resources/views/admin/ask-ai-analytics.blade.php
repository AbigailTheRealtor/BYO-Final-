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

{{-- Active-filter summary row (scoped to the chosen date range) --}}
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
