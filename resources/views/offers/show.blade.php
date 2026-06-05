@extends('layouts.main')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Offer Detail</h2>
                <button onclick="window.history.back()" class="btn btn-secondary btn-sm">Back</button>
            </div>

            {{-- Offer Summary Card --}}
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Offer Information</strong>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Offer ID</dt>
                        <dd class="col-sm-9">{{ $offer->id }}</dd>

                        <dt class="col-sm-3">Status</dt>
                        <dd class="col-sm-9">
                            @php
                                $statusColors = [
                                    'draft'     => 'secondary',
                                    'submitted' => 'primary',
                                    'countered' => 'warning',
                                    'accepted'  => 'success',
                                    'rejected'  => 'danger',
                                    'withdrawn' => 'dark',
                                    'expired'   => 'secondary',
                                    'cancelled' => 'danger',
                                ];
                                $color = $statusColors[$offer->status] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $color }} text-capitalize">{{ $offer->status }}</span>
                        </dd>

                        @if($offer->parent_offer_id)
                        <dt class="col-sm-3">Parent Offer ID</dt>
                        <dd class="col-sm-9">{{ $offer->parent_offer_id }}</dd>
                        @endif

                        <dt class="col-sm-3">Created At</dt>
                        <dd class="col-sm-9">{{ $offer->created_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>

                        <dt class="col-sm-3">Submitted At</dt>
                        <dd class="col-sm-9">{{ $offer->submitted_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Success Flash --}}
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            {{-- Offer Terms Card --}}
            @php
                $isOwner    = Auth::id() === $offer->user_id;
                $canEdit    = $isOwner && $offer->status === 'draft';
                $safeDate   = function ($v) {
                    if (!$v) return '—';
                    try { return \Carbon\Carbon::parse($v)->format('Y-m-d'); }
                    catch (\Throwable $e) { return '—'; }
                };
            @endphp
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Offer Terms</strong>
                    @if($canEdit)
                        <span class="badge bg-success">Editable</span>
                    @else
                        <span class="badge bg-secondary">Locked</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($canEdit)
                    <form method="POST" action="{{ route('offers.terms', $offer) }}">
                        @csrf

                        @if($errors->any())
                        <div class="alert alert-danger mb-3">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        </div>
                        @endif

                        {{-- Common fields --}}
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Offer Expires At</label>
                                <input type="date" name="expires_at" class="form-control"
                                    value="{{ old('expires_at', ($v = $metas->get('expires_at')) ? $safeDate($v) : '') }}">
                            </div>
                        </div>

                        {{-- Sale-specific fields --}}
                        @if($offerType === 'sale')
                        <h6 class="fw-semibold text-muted mt-3 mb-2">Sale Terms</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Offer Price ($)</label>
                                <input type="number" name="offer_price" class="form-control" min="0" step="1000"
                                    value="{{ old('offer_price', $metas->get('offer_price')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Earnest Deposit ($)</label>
                                <input type="number" name="earnest_deposit" class="form-control" min="0" step="100"
                                    value="{{ old('earnest_deposit', $metas->get('earnest_deposit')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Down Payment (%)</label>
                                <input type="number" name="down_payment_percent" class="form-control" min="0" max="100" step="0.5"
                                    value="{{ old('down_payment_percent', $metas->get('down_payment_percent')) }}">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Financing Type</label>
                                <select name="financing_type" class="form-select">
                                    <option value="">— Select —</option>
                                    @foreach(['cash' => 'All Cash', 'conventional' => 'Conventional', 'fha' => 'FHA', 'va' => 'VA', 'other' => 'Other'] as $val => $lbl)
                                    <option value="{{ $val }}" {{ old('financing_type', $metas->get('financing_type')) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Closing Date</label>
                                <input type="date" name="closing_date" class="form-control"
                                    value="{{ old('closing_date', ($v = $metas->get('closing_date')) ? $safeDate($v) : '') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Possession Date</label>
                                <input type="date" name="possession_date" class="form-control"
                                    value="{{ old('possession_date', ($v = $metas->get('possession_date')) ? $safeDate($v) : '') }}">
                            </div>
                        </div>
                        <div class="row g-3 mb-3" x-data="{
                            finCont: {{ old('financing_contingency', $metas->get('financing_contingency')) ? 'true' : 'false' }},
                            inspCont: {{ old('inspection_contingency', $metas->get('inspection_contingency')) ? 'true' : 'false' }}
                        }">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="fin_cont_terms" name="financing_contingency"
                                        value="1" x-model="finCont"
                                        {{ old('financing_contingency', $metas->get('financing_contingency')) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="fin_cont_terms">Financing Contingency</label>
                                </div>
                                <div x-show="finCont" class="mt-1">
                                    <label class="form-label small">Contingency Period (days)</label>
                                    <input type="number" name="financing_contingency_days" class="form-control form-control-sm w-auto" min="1" max="365"
                                        value="{{ old('financing_contingency_days', $metas->get('financing_contingency_days')) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="insp_cont_terms" name="inspection_contingency"
                                        value="1" x-model="inspCont"
                                        {{ old('inspection_contingency', $metas->get('inspection_contingency')) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="insp_cont_terms">Inspection Contingency</label>
                                </div>
                                <div x-show="inspCont" class="mt-1">
                                    <label class="form-label small">Inspection Period (days)</label>
                                    <input type="number" name="inspection_contingency_days" class="form-control form-control-sm w-auto" min="1" max="365"
                                        value="{{ old('inspection_contingency_days', $metas->get('inspection_contingency_days')) }}">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="appr_cont_terms" name="appraisal_contingency"
                                    value="1"
                                    {{ old('appraisal_contingency', $metas->get('appraisal_contingency')) ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="appr_cont_terms">Appraisal Contingency</label>
                            </div>
                        </div>
                        @endif

                        {{-- Rental/Lease-specific fields --}}
                        @if(in_array($offerType, ['rental', 'lease']))
                        <h6 class="fw-semibold text-muted mt-3 mb-2">{{ ucfirst($offerType) }} Terms</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Monthly Rent ($)</label>
                                <input type="number" name="monthly_rent" class="form-control" min="0" step="50"
                                    value="{{ old('monthly_rent', $metas->get('monthly_rent')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Security Deposit ($)</label>
                                <input type="number" name="security_deposit" class="form-control" min="0" step="50"
                                    value="{{ old('security_deposit', $metas->get('security_deposit')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Move-in Date</label>
                                <input type="date" name="move_in_date" class="form-control"
                                    value="{{ old('move_in_date', ($v = $metas->get('move_in_date')) ? $safeDate($v) : '') }}">
                            </div>
                        </div>
                        @if($offerType === 'lease')
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Lease Term (Months)</label>
                            <input type="number" name="lease_term_months" class="form-control w-auto" min="1" max="360"
                                value="{{ old('lease_term_months', $metas->get('lease_term_months')) }}">
                        </div>
                        @endif
                        @endif

                        {{-- Common: Custom Terms & Notes --}}
                        <hr class="my-3">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Custom Terms / Special Conditions</label>
                            <textarea name="custom_terms" class="form-control" rows="4"
                                placeholder="Any special conditions, addendums, or custom terms…">{{ old('custom_terms', $metas->get('custom_terms')) }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Private Notes <span class="text-muted small">(not shown to the other party)</span></label>
                            <textarea name="notes" class="form-control" rows="3"
                                placeholder="Private notes for your reference…">{{ old('notes', $metas->get('notes')) }}</textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Save Offer Terms</button>
                        </div>
                    </form>
                    @else
                    {{-- Read-only display --}}
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Offer Expires At</dt>
                        <dd class="col-sm-9">{{ $safeDate($metas->get('expires_at')) }}</dd>

                        @if($offerType === 'sale')
                        <dt class="col-sm-3">Offer Price</dt>
                        <dd class="col-sm-9">{{ $metas->get('offer_price') ? '$' . number_format($metas->get('offer_price')) : '—' }}</dd>

                        <dt class="col-sm-3">Earnest Deposit</dt>
                        <dd class="col-sm-9">{{ $metas->get('earnest_deposit') ? '$' . number_format($metas->get('earnest_deposit')) : '—' }}</dd>

                        <dt class="col-sm-3">Financing Type</dt>
                        <dd class="col-sm-9">{{ $metas->get('financing_type') ? ucfirst($metas->get('financing_type')) : '—' }}</dd>

                        <dt class="col-sm-3">Down Payment %</dt>
                        <dd class="col-sm-9">{{ $metas->get('down_payment_percent') !== null ? $metas->get('down_payment_percent') . '%' : '—' }}</dd>

                        <dt class="col-sm-3">Financing Contingency</dt>
                        <dd class="col-sm-9">
                            {{ $metas->get('financing_contingency') ? 'Yes' : 'No' }}
                            @if($metas->get('financing_contingency') && $metas->get('financing_contingency_days'))
                                ({{ $metas->get('financing_contingency_days') }} days)
                            @endif
                        </dd>

                        <dt class="col-sm-3">Inspection Contingency</dt>
                        <dd class="col-sm-9">
                            {{ $metas->get('inspection_contingency') ? 'Yes' : 'No' }}
                            @if($metas->get('inspection_contingency') && $metas->get('inspection_contingency_days'))
                                ({{ $metas->get('inspection_contingency_days') }} days)
                            @endif
                        </dd>

                        <dt class="col-sm-3">Appraisal Contingency</dt>
                        <dd class="col-sm-9">{{ $metas->get('appraisal_contingency') ? 'Yes' : 'No' }}</dd>

                        <dt class="col-sm-3">Closing Date</dt>
                        <dd class="col-sm-9">{{ $safeDate($metas->get('closing_date')) }}</dd>

                        <dt class="col-sm-3">Possession Date</dt>
                        <dd class="col-sm-9">{{ $safeDate($metas->get('possession_date')) }}</dd>
                        @endif

                        @if(in_array($offerType, ['rental', 'lease']))
                        <dt class="col-sm-3">Monthly Rent</dt>
                        <dd class="col-sm-9">{{ $metas->get('monthly_rent') ? '$' . number_format($metas->get('monthly_rent')) : '—' }}</dd>

                        <dt class="col-sm-3">Security Deposit</dt>
                        <dd class="col-sm-9">{{ $metas->get('security_deposit') ? '$' . number_format($metas->get('security_deposit')) : '—' }}</dd>

                        <dt class="col-sm-3">Move-in Date</dt>
                        <dd class="col-sm-9">{{ $safeDate($metas->get('move_in_date')) }}</dd>

                        @if($offerType === 'lease')
                        <dt class="col-sm-3">Lease Term</dt>
                        <dd class="col-sm-9">{{ $metas->get('lease_term_months') ? $metas->get('lease_term_months') . ' months' : '—' }}</dd>
                        @endif
                        @endif

                        <dt class="col-sm-3">Custom Terms</dt>
                        <dd class="col-sm-9" style="white-space: pre-wrap;">{{ $metas->get('custom_terms') ?: '—' }}</dd>

                        @if($isOwner)
                        <dt class="col-sm-3">Private Notes</dt>
                        <dd class="col-sm-9" style="white-space: pre-wrap;">{{ $metas->get('notes') ?: '—' }}</dd>
                        @endif
                    </dl>
                    @endif
                </div>
            </div>

            {{-- Negotiation Timeline --}}
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Negotiation Timeline</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Offer ID</th>
                                    <th>Parent Offer ID</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Submitted At</th>
                                    <th>Event Count</th>
                                    <th>Latest Event Type</th>
                                    <th>Latest Event At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($timeline as $item)
                                <tr @if($item['offer_id'] === $offer->id) class="table-active" @endif>
                                    <td>{{ $item['offer_id'] }}</td>
                                    <td>{{ $item['parent_offer_id'] ?? '—' }}</td>
                                    <td>
                                        @php $tColor = $statusColors[$item['status']] ?? 'secondary'; @endphp
                                        <span class="badge bg-{{ $tColor }} text-capitalize">{{ $item['status'] }}</span>
                                    </td>
                                    <td>{{ $item['created_at'] ?? '—' }}</td>
                                    <td>{{ $item['submitted_at'] ?? '—' }}</td>
                                    <td>{{ $item['event_count'] }}</td>
                                    <td>{{ $item['latest_event_type'] ?? '—' }}</td>
                                    <td>{{ $item['latest_event_at'] ?? '—' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted">No timeline data available.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Available Actions --}}
            {{-- can_expire is intentionally not shown. Submit/Accept/Reject/Withdraw POST via named routes when enabled. --}}
            {{-- Counter has dedicated three-branch logic below the shared loop. Disabled actions render as bare disabled buttons. --}}
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Available Actions</strong>
                </div>
                <div class="card-body">
                    @php
                        $actionButtons = [
                            'can_submit'        => ['label' => 'Submit Offer',  'btn' => 'btn-primary',           'reason_key' => 'submit',        'route' => 'offers.submit'],
                            'can_accept'        => ['label' => 'Accept',         'btn' => 'btn-success',           'reason_key' => 'accept',        'route' => 'offers.accept'],
                            'can_reject'        => ['label' => 'Reject',         'btn' => 'btn-danger',            'reason_key' => 'reject',        'route' => 'offers.reject'],
                            'can_withdraw'      => ['label' => 'Withdraw',       'btn' => 'btn-outline-secondary', 'reason_key' => 'withdraw',      'route' => 'offers.withdraw'],
                            'can_view_timeline' => ['label' => 'View Timeline',  'btn' => 'btn-outline-info',      'reason_key' => 'view_timeline', 'route' => null],
                        ];
                        $counterReason = $actions['reasons']['counter'] ?? '';
                    @endphp
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        @foreach($actionButtons as $flag => $cfg)
                            @php
                                $allowed = !empty($actions[$flag]);
                                $reason  = $allowed ? '' : ($actions['reasons'][$cfg['reason_key']] ?? '');
                            @endphp
                            <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                @if($allowed && $cfg['route'])
                                    {{-- Enabled action with a route: POST form --}}
                                    <form method="POST" action="{{ route($cfg['route'], $offer) }}">
                                        @csrf
                                        <button type="submit" class="btn {{ $cfg['btn'] }} btn-sm">{{ $cfg['label'] }}</button>
                                    </form>
                                @elseif($allowed)
                                    {{-- Enabled action with no route (e.g. View Timeline): plain enabled button --}}
                                    <button type="button" class="btn {{ $cfg['btn'] }} btn-sm">{{ $cfg['label'] }}</button>
                                @else
                                    {{-- Disabled action: bare button, no form --}}
                                    <button type="button" class="btn {{ $cfg['btn'] }} btn-sm" disabled title="{{ $reason }}" aria-disabled="true" tabindex="-1">{{ $cfg['label'] }}</button>
                                    @if($reason)
                                        <small class="text-muted mt-1 px-1" style="font-size: 0.75rem; line-height: 1.3;">{{ $reason }}</small>
                                    @endif
                                @endif
                            </div>
                        @endforeach

                        {{-- Counter: three-branch logic --}}
                        @if(!empty($actions['can_counter']))
                            {{-- can_counter=true: real POST form with expires_at date input --}}
                            <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                <form method="POST" action="{{ route('offers.counter', $offer) }}">
                                    @csrf
                                    <div class="mb-2">
                                        <input type="date" name="expires_at" class="form-control form-control-sm">
                                    </div>
                                    <button type="submit" class="btn btn-warning btn-sm">Counter</button>
                                </form>
                            </div>
                        @elseif($counterReason !== '')
                            {{-- can_counter=false with reason: disabled button with tooltip and reason text --}}
                            <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                <button type="button" class="btn btn-warning btn-sm" disabled title="{{ $counterReason }}" aria-disabled="true" tabindex="-1">Counter</button>
                                <small class="text-muted mt-1 px-1" style="font-size: 0.75rem; line-height: 1.3;">{{ $counterReason }}</small>
                            </div>
                        @endif
                        {{-- can_counter=false with empty reason: nothing rendered --}}
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
