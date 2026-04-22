@extends('layouts.admin')
@section('content')

{{-- ── Flash messages ──────────────────────────────────────────────────────── --}}
@if(session('success'))
    <div class="alert alert-success mx-3 mt-3">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger mx-3 mt-3">{{ session('error') }}</div>
@endif

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="mb-0">Referral Tracking</h5>
        <span class="text-muted small">{{ $rows->total() }} referred hire{{ $rows->total() !== 1 ? 's' : '' }}</span>
    </div>

    {{-- ── Per-Agent Link Summary ──────────────────────────────────────── --}}
    @if($linkStats->isNotEmpty())
    <div class="card-body pb-0">
        <h6 class="fw-bold text-muted mb-2" style="font-size:.78rem;letter-spacing:.06em;text-transform:uppercase;">Agent Referral Link Summary</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" style="font-size:.82rem;">
                <thead class="thead-light">
                    <tr>
                        <th>Agent</th>
                        <th>Email</th>
                        <th>Code</th>
                        <th class="text-center">Clicks</th>
                        <th class="text-center">Signups</th>
                        <th class="text-center">Listings</th>
                        <th class="text-center">Hires</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($linkStats as $ls)
                    <tr>
                        <td>{{ $ls->agent_name ?? '—' }}</td>
                        <td class="text-muted">{{ $ls->agent_email ?? '—' }}</td>
                        <td><code>{{ $ls->code }}</code></td>
                        <td class="text-center">{{ number_format($ls->click_count) }}</td>
                        <td class="text-center">{{ number_format($ls->signup_count) }}</td>
                        <td class="text-center">{{ number_format($ls->listing_count) }}</td>
                        <td class="text-center">
                            @if($ls->hire_count > 0)
                                <strong class="text-success">{{ number_format($ls->hire_count) }}</strong>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <hr class="my-3">
    @endif

    {{-- ── Filter Bar ──────────────────────────────────────────────────── --}}
    <div class="card-body py-2">
        <form method="GET" action="{{ route('admin.referrals.index') }}" class="d-flex align-items-center gap-2 flex-wrap">
            <label class="mb-0 small fw-semibold">Filter by status:</label>
            <select name="status" class="form-control form-control-sm" style="width:auto;">
                <option value="">All statuses</option>
                @foreach($validStatuses as $s)
                    <option value="{{ $s }}" {{ $filterStatus === $s ? 'selected' : '' }}>
                        {{ ucfirst($s) }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
            @if($filterStatus)
                <a href="{{ route('admin.referrals.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>

    {{-- ── Referred Hires Table ────────────────────────────────────────── --}}
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:.82rem;">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Referring Agent</th>
                        <th>Code</th>
                        <th>Referred User</th>
                        <th>Listing</th>
                        <th>Hired Agent</th>
                        <th>Status</th>
                        <th class="text-right" title="Admin-entered platform fee">Platform Fee</th>
                        <th class="text-right" title="Admin-entered partner earnings">Partner Earnings</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    @php
                        $statusColors = [
                            'pending'   => 'warning',
                            'qualified' => 'info',
                            'closed'    => 'success',
                            'paid'      => 'primary',
                            'void'      => 'secondary',
                        ];
                        $badgeColor = $statusColors[$row->referral_status ?? ''] ?? 'light';
                        // Action buttons: all statuses except the one currently set
                        $actionStatuses = array_filter(
                            array_keys($statusColors),
                            fn($s) => $s !== ($row->referral_status ?? '')
                        );
                    @endphp
                    <tr>
                        <td class="text-muted">{{ $rows->firstItem() + $loop->index }}</td>
                        <td>
                            @if($row->referring_agent_name)
                                <div class="fw-semibold">{{ $row->referring_agent_name }}</div>
                                <div class="text-muted" style="font-size:.75rem;">{{ $row->referring_agent_email }}</div>
                                <div style="font-size:.72rem;color:#aaa;">ID: {{ $row->referring_agent_id }}</div>
                            @else
                                <span class="text-muted">Agent #{{ $row->referring_agent_id }}</span>
                            @endif
                        </td>
                        <td><code style="font-size:.75rem;">{{ $row->referral_source_code ?? '—' }}</code></td>
                        <td>
                            @if($row->referred_user_name)
                                <div>{{ $row->referred_user_name }}</div>
                                <div style="font-size:.72rem;color:#aaa;">ID: {{ $row->tenant_user_id }}</div>
                            @else
                                <span class="text-muted">User #{{ $row->tenant_user_id }}</span>
                            @endif
                        </td>
                        <td>
                            <div>ID: {{ $row->listing_id }}</div>
                            <div class="badge badge-light border" style="font-size:.7rem;">{{ ucfirst($row->listing_type ?? '—') }}</div>
                        </td>
                        <td>
                            @if($row->hired_agent_name)
                                <div>{{ $row->hired_agent_name }}</div>
                                <div style="font-size:.72rem;color:#aaa;">ID: {{ $row->agent_user_id }}</div>
                            @else
                                <span class="text-muted">Agent #{{ $row->agent_user_id }}</span>
                            @endif
                        </td>
                        <td>
                            @if($row->referral_status)
                                <span class="badge badge-{{ $badgeColor }}">{{ ucfirst($row->referral_status) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        {{-- ── Platform Fee (read-only display) ─── --}}
                        <td class="text-right" style="white-space:nowrap;">
                            @if($row->platform_referral_amount !== null)
                                <span class="text-dark">${{ number_format($row->platform_referral_amount, 2) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        {{-- ── Partner Earnings (read-only display) ─ --}}
                        <td class="text-right" style="white-space:nowrap;">
                            @if($row->partner_referral_amount !== null)
                                <span class="text-success fw-semibold">${{ number_format($row->partner_referral_amount, 2) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        <td class="text-muted" style="white-space:nowrap;">
                            {{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('M j, Y') : '—' }}
                        </td>

                        {{-- ── Single form: status buttons + optional amount inputs ── --}}
                        <td style="min-width:200px;vertical-align:middle;">
                            <form method="POST"
                                  action="{{ route('admin.referrals.status', $row->id) }}"
                                  onsubmit="return confirm('Set referral #{{ $row->id }} to \'' + (this.dataset.newStatus || '?') + '\'?');">
                                @csrf

                                {{-- Status action buttons --}}
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    @foreach($actionStatuses as $s)
                                    <button type="submit"
                                            name="status"
                                            value="{{ $s }}"
                                            class="btn btn-outline-{{ $statusColors[$s] ?? 'secondary' }} btn-xs"
                                            style="font-size:.7rem;padding:2px 7px;line-height:1.4;"
                                            onclick="this.form.dataset.newStatus='{{ ucfirst($s) }}';">
                                        {{ ucfirst($s) }}
                                    </button>
                                    @endforeach
                                </div>

                                {{-- Optional earnings inputs (saved only when a status button is clicked) --}}
                                <div class="d-flex gap-1 align-items-center" title="Enter amounts and click a status button to save together">
                                    <input type="number"
                                           name="platform_referral_amount"
                                           step="0.01"
                                           min="0"
                                           placeholder="Platform $"
                                           value="{{ $row->platform_referral_amount !== null ? number_format((float)$row->platform_referral_amount, 2, '.', '') : '' }}"
                                           class="form-control form-control-sm"
                                           style="width:78px;font-size:.7rem;"
                                           title="Platform fee (optional — leave blank to keep current value)">
                                    <input type="number"
                                           name="partner_referral_amount"
                                           step="0.01"
                                           min="0"
                                           placeholder="Partner $"
                                           value="{{ $row->partner_referral_amount !== null ? number_format((float)$row->partner_referral_amount, 2, '.', '') : '' }}"
                                           class="form-control form-control-sm"
                                           style="width:78px;font-size:.7rem;"
                                           title="Partner earnings (optional — leave blank to keep current value)">
                                </div>
                                <div class="text-muted mt-1" style="font-size:.68rem;line-height:1.2;">
                                    Amounts saved when a status button is clicked. Leave blank to keep existing values.
                                </div>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">
                            @if($filterStatus)
                                No referred hires with status "{{ ucfirst($filterStatus) }}".
                                <a href="{{ route('admin.referrals.index') }}">Clear filter</a>
                            @else
                                No referred hires found.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Pagination ──────────────────────────────────────────────────── --}}
    @if($rows->hasPages())
    <div class="card-footer d-flex justify-content-end">
        {{ $rows->links() }}
    </div>
    @endif
</div>

@endsection
