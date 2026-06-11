@extends('layouts.main')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Offer Detail</h2>
                <button onclick="window.history.back()" class="btn btn-secondary btn-sm">Back</button>
            </div>

            @php
                $fmtDate = function ($v) {
                    if (!$v) return '—';
                    try { return \Carbon\Carbon::parse($v)->format('F j, Y'); }
                    catch (\Throwable $e) { return '—'; }
                };
                $fmtDateTime = function ($v) {
                    if (!$v) return '—';
                    try { return \Carbon\Carbon::parse($v)->format('F j, Y \a\t g:i A'); }
                    catch (\Throwable $e) { return '—'; }
                };
            @endphp

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
                        <dd class="col-sm-9">{{ $fmtDateTime($offer->created_at) }}</dd>

                        <dt class="col-sm-3">Submitted At</dt>
                        <dd class="col-sm-9">{{ $fmtDateTime($offer->submitted_at) }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif
            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            @endif

            {{-- Offer Terms Card --}}
            @php
                $offerStatus = $offer->status;
                $isOwner    = Auth::id() === $offer->user_id;
                $canEdit    = $isOwner && $offerStatus === 'draft';
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
                    @include('offers._offer_terms_form', ['mode' => 'draft_terms', 'formData' => $counterDefaults])
                    @else
                    {{-- Read-only display --}}
                    @include('offers._offer_terms_display', ['metas' => $metas, 'offerType' => $offerType])
                    @endif
                </div>
            </div>

            {{-- Negotiation Timeline --}}
            <div class="card mb-4" id="offer-timeline">
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
                                    <td>{{ $fmtDateTime($item['created_at'] ?? null) }}</td>
                                    <td>{{ $fmtDateTime($item['submitted_at'] ?? null) }}</td>
                                    <td>{{ $item['event_count'] }}</td>
                                    <td>{{ $item['latest_event_type'] ?? '—' }}</td>
                                    <td>{{ $fmtDateTime($item['latest_event_at'] ?? null) }}</td>
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
            <style>
                #submit-offer-action-btn { background:#2563eb; border-color:#2563eb; color:#fff; font-weight:600; }
                #submit-offer-action-btn:hover { background:#1d4ed8; border-color:#1d4ed8; }
                #submit-offer-action-btn:disabled { background:#93c5fd; border-color:#93c5fd; color:#fff; }
                #accept-offer-action-btn { background:#198754; border-color:#198754; color:#fff; font-weight:600; }
                #accept-offer-action-btn:hover { background:#157347; border-color:#146c43; color:#fff; }
                #accept-offer-action-btn:disabled { background:#198754; border-color:#198754; color:#fff; opacity:.55; }
                #reject-offer-action-btn { background:#dc3545; border-color:#dc3545; color:#fff; font-weight:600; }
                #reject-offer-action-btn:hover { background:#bb2d3b; border-color:#b02a37; color:#fff; }
                #reject-offer-action-btn:disabled { background:#dc3545; border-color:#dc3545; color:#fff; opacity:.55; }
                #withdraw-offer-action-btn { background:transparent; border-color:#6c757d; color:#6c757d; font-weight:600; }
                #withdraw-offer-action-btn:hover { background:#6c757d; color:#fff; }
                #withdraw-offer-action-btn:disabled { background:transparent; color:#6c757d; border-color:#6c757d; opacity:.55; }
                .btn:disabled, .btn[disabled], .btn[aria-disabled="true"] { opacity:.55; cursor:not-allowed; }
                .btn-success:disabled  { background-color:#198754; border-color:#198754; color:#fff; }
                .btn-danger:disabled   { background-color:#dc3545; border-color:#dc3545; color:#fff; }
                .btn-warning:disabled  { background-color:#ffc107; border-color:#ffc107; color:#212529; }
                .btn-outline-secondary:disabled { background-color:transparent; color:#6c757d; border-color:#6c757d; }
            </style>
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Available Actions</strong>
                </div>
                <div class="card-body">
                    @php
                        $actionButtons = [
                            'can_submit'        => ['label' => 'Submit Offer',  'btn' => 'btn-primary',           'reason_key' => 'submit',        'route' => 'offers.submit',   'hide_for_submitter' => false],
                            'can_accept'        => ['label' => 'Accept',         'btn' => 'btn-success',           'reason_key' => 'accept',        'route' => 'offers.accept',   'hide_for_submitter' => true],
                            'can_reject'        => ['label' => 'Reject',         'btn' => 'btn-danger',            'reason_key' => 'reject',        'route' => 'offers.reject',   'hide_for_submitter' => true],
                            'can_withdraw'      => ['label' => 'Withdraw',       'btn' => 'btn-outline-secondary', 'reason_key' => 'withdraw',      'route' => 'offers.withdraw', 'hide_for_submitter' => false],
                            'can_view_timeline' => ['label' => 'View Timeline',  'btn' => 'btn-outline-info',      'reason_key' => 'view_timeline', 'route' => null,              'hide_for_submitter' => false],
                        ];
                        $counterReason    = $actions['reasons']['counter'] ?? '';
                        $actorIsSubmitter = auth()->id() !== null && (int) auth()->id() === (int) $offer->user_id;
                    @endphp
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        @foreach($actionButtons as $flag => $cfg)
                            @php
                                $allowed = !empty($actions[$flag]);
                                $reason  = $allowed ? '' : ($actions['reasons'][$cfg['reason_key']] ?? '');
                            @endphp
                            {{-- Accept and Reject are hidden entirely for the submitter — they belong to the other party. --}}
                            @if($actorIsSubmitter && $cfg['hide_for_submitter'])
                                @continue
                            @endif
                            {{-- can_submit=false is silently hidden — no disabled button, no reason text. --}}
                            @if(!$allowed && $flag === 'can_submit')
                                @continue
                            @endif
                            {{-- Disabled / not-permitted: render a disabled button with the reason if one is provided. --}}
                            @if(!$allowed)
                                @if($reason)
                                    <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                        <button type="button" class="btn {{ $cfg['btn'] }} btn-sm" disabled>{{ $cfg['label'] }}</button>
                                        <small class="text-muted mt-1">{{ $reason }}</small>
                                    </div>
                                @endif
                                @continue
                            @endif
                            <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                @if($cfg['route'])
                                    {{-- Enabled action with a route: POST form --}}
                                    <form method="POST" action="{{ route($cfg['route'], $offer) }}">
                                        @csrf
                                        <button type="submit" class="btn {{ $cfg['btn'] }} btn-sm"
                                            @if($flag === 'can_submit')   id="submit-offer-action-btn"
                                            @elseif($flag === 'can_accept') id="accept-offer-action-btn"
                                            @elseif($flag === 'can_reject') id="reject-offer-action-btn"
                                            @elseif($flag === 'can_withdraw') id="withdraw-offer-action-btn"
                                            @endif>{{ $cfg['label'] }}</button>
                                    </form>
                                @else
                                    {{-- Enabled action with no route (e.g. View Timeline): anchor to on-page section --}}
                                    <a href="#offer-timeline" class="btn {{ $cfg['btn'] }} btn-sm">{{ $cfg['label'] }}</a>
                                @endif
                            </div>
                        @endforeach

                        {{-- Counter: hidden entirely for the submitter. --}}
                        @if(!$actorIsSubmitter)
                            @if(!empty($actions['can_counter']))
                                {{-- can_counter=true: full counter form pre-populated from current offer terms --}}
                                <div class="w-100 mt-3">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning bg-opacity-10 fw-semibold">Submit Counter Offer</div>
                                        <div class="card-body">
                                            {{-- Read-only pre-screening context from the root (original) application --}}
                                            @if(in_array($offerType, ['rental', 'lease']))
                                            @php
                                                $_rsm = $rootMetas;
                                                $_hasScreening = $_rsm->get('num_occupants') || $_rsm->get('has_pets') ||
                                                    $_rsm->get('pet_details') || $_rsm->get('smoking_preference') ||
                                                    $_rsm->get('monthly_income') || $_rsm->get('credit_score_range') ||
                                                    $_rsm->get('screening_notes') || $_rsm->get('screening_concerns') ||
                                                    $_rsm->get('screening_concerns_details') || $_rsm->get('message_to_landlord');
                                                $_fmtMoneyRo = fn($v) => $v ? number_format((float) str_replace(',', '', $v)) : '—';
                                            @endphp
                                            @if($_hasScreening)
                                            <div class="card bg-light border mb-4">
                                                <div class="card-header fw-semibold text-secondary" style="font-size:0.85rem;text-transform:uppercase;letter-spacing:.04em;">
                                                    Original Application — Applicant Information
                                                </div>
                                                <div class="card-body py-2">
                                                    <dl class="row mb-0" style="font-size:0.9rem;">
                                                        @if($_rsm->get('num_occupants'))
                                                        <dt class="col-sm-4">Number of Occupants</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('num_occupants') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('has_pets'))
                                                        <dt class="col-sm-4">Pets</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('has_pets') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('pet_details'))
                                                        <dt class="col-sm-4">Pet Details</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('pet_details') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('smoking_preference'))
                                                        <dt class="col-sm-4">Smoking</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('smoking_preference') === 'No' ? 'Non-smoker' : 'Smoker' }}</dd>
                                                        @endif
                                                        @if($_rsm->get('monthly_income'))
                                                        <dt class="col-sm-4">Est. Monthly Income</dt>
                                                        <dd class="col-sm-8">${{ $_fmtMoneyRo($_rsm->get('monthly_income')) }}</dd>
                                                        @endif
                                                        @if($_rsm->get('credit_score_range'))
                                                        <dt class="col-sm-4">Credit Score Range</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('credit_score_range') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('screening_concerns'))
                                                        <dt class="col-sm-4">Screening Concerns</dt>
                                                        <dd class="col-sm-8">{{ $_rsm->get('screening_concerns') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('screening_concerns') === 'Yes' && $_rsm->get('screening_concerns_details'))
                                                        <dt class="col-sm-4">Concern Details</dt>
                                                        <dd class="col-sm-8" style="white-space:pre-wrap;">{{ $_rsm->get('screening_concerns_details') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('screening_notes'))
                                                        <dt class="col-sm-4">About Applicant</dt>
                                                        <dd class="col-sm-8" style="white-space:pre-wrap;">{{ $_rsm->get('screening_notes') }}</dd>
                                                        @endif
                                                        @if($_rsm->get('message_to_landlord'))
                                                        <dt class="col-sm-4">Message to Landlord</dt>
                                                        <dd class="col-sm-8" style="white-space:pre-wrap;">{{ $_rsm->get('message_to_landlord') }}</dd>
                                                        @endif
                                                    </dl>
                                                </div>
                                            </div>
                                            @endif
                                            @endif
                                            @include('offers._offer_terms_form', ['mode' => 'counter_terms', 'formData' => $counterDefaults])
                                        </div>
                                    </div>
                                </div>
                            @elseif($counterReason)
                                {{-- can_counter=false with a reason: disabled button so the user knows why --}}
                                <div class="d-flex flex-column align-items-start" style="min-width: 130px;">
                                    <button type="button" class="btn btn-warning btn-sm" disabled>Counter</button>
                                    <small class="text-muted mt-1">{{ $counterReason }}</small>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
