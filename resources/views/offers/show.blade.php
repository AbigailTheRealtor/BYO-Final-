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
            <div class="card mb-4">
                <div class="card-header">
                    <strong>Available Actions</strong>
                </div>
                <div class="card-body">
                    @php
                        $actionFlags = [
                            'can_submit'        => 'Submit',
                            'can_counter'       => 'Counter',
                            'can_accept'        => 'Accept',
                            'can_reject'        => 'Reject',
                            'can_withdraw'      => 'Withdraw',
                            'can_expire'        => 'Expire',
                            'can_view_timeline' => 'View Timeline',
                        ];
                        $reasonKeys = [
                            'can_submit'        => 'submit',
                            'can_counter'       => 'counter',
                            'can_accept'        => 'accept',
                            'can_reject'        => 'reject',
                            'can_withdraw'      => 'withdraw',
                            'can_expire'        => 'expire',
                            'can_view_timeline' => 'view_timeline',
                        ];
                    @endphp
                    <dl class="row mb-0">
                        @foreach($actionFlags as $flag => $label)
                        <dt class="col-sm-3">{{ $label }}</dt>
                        <dd class="col-sm-9">
                            @if($actions[$flag])
                                <span class="badge bg-success">Allowed</span>
                            @else
                                <span class="badge bg-danger">Blocked</span>
                                @php $reason = $actions['reasons'][$reasonKeys[$flag]] ?? ''; @endphp
                                @if($reason)
                                    <span class="ms-2 text-muted small">{{ $reason }}</span>
                                @endif
                            @endif
                        </dd>
                        @endforeach
                    </dl>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
