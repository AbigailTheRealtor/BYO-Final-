@extends('layouts.main')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        @include('layouts.partials.sidenav')

        <div class="col-sm-12 col-md-9 col-lg-9">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <a href="{{ route('agent.ai-inbox.index') }}" class="btn btn-sm btn-outline-secondary mb-2">
                        <i class="fa-solid fa-arrow-left me-1"></i>Back to Inbox
                    </a>
                    <h4 class="mb-0 fw-bold">Conversation Thread</h4>
                </div>
                @if($session->reviewed_at === null)
                    <button id="markReviewedBtn" class="btn btn-sm btn-success"
                        data-session-id="{{ $session->id }}">
                        <i class="fa-solid fa-check me-1"></i>Mark as Reviewed
                    </button>
                @else
                    <span class="badge bg-success">
                        <i class="fa-solid fa-check me-1"></i>Reviewed {{ $session->reviewed_at->diffForHumans() }}
                    </span>
                @endif
            </div>

            {{-- Lead Summary Card --}}
            @php
                $lead       = $session->lead;
                $score      = $lead?->lead_score ?? 0;
                $badgeClass = match(true) {
                    $score >= 90 => 'bg-danger',
                    $score >= 75 => 'bg-warning text-dark',
                    $score >= 50 => 'bg-info text-dark',
                    default      => 'bg-secondary',
                };
            @endphp
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold border-bottom">Lead Details</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm mb-0">
                                <tr>
                                    <th class="text-muted fw-normal small" width="140">Visitor</th>
                                    <td>{{ $lead?->visitor_name ?? 'Anonymous' }}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal small">Email</th>
                                    <td>{{ $lead?->visitor_email ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal small">Phone</th>
                                    <td>{{ $lead?->visitor_phone ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal small">Preferred Contact</th>
                                    <td>{{ $lead?->preferred_contact ?? '—' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless table-sm mb-0">
                                <tr>
                                    <th class="text-muted fw-normal small" width="140">Lead Score</th>
                                    <td><span class="badge {{ $badgeClass }} fs-6">{{ $score }} / 100</span></td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal small">Lead Type</th>
                                    <td>{{ $lead?->lead_type ? ucfirst(str_replace('_',' ',$lead->lead_type)) : '—' }}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal small">Intent</th>
                                    <td>{{ $lead?->intent_phrase ? \Illuminate\Support\Str::limit($lead->intent_phrase, 80) : '—' }}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal small">Started</th>
                                    <td>{{ $session->started_at?->format('M j, Y g:i A') }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    @if($lead?->conversation_summary)
                        <div class="mt-3 border-top pt-3">
                            <strong class="small text-muted d-block mb-1">Conversation Summary</strong>
                            <p class="mb-0 small">{{ $lead->conversation_summary }}</p>
                        </div>
                    @endif
                    @if($lead?->recommended_follow_up)
                        <div class="mt-3 p-2 bg-light rounded">
                            <i class="fa-solid fa-lightbulb text-warning me-1"></i>
                            <strong class="small">Recommended Follow-up:</strong>
                            <span class="small">{{ $lead->recommended_follow_up }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Message Thread --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold border-bottom">
                    Message Thread ({{ $session->messages->count() }} messages)
                </div>
                <div class="card-body p-0">
                    @forelse($session->messages as $msg)
                        <div class="d-flex p-3 border-bottom {{ $msg->role === 'user' ? 'bg-white' : 'bg-light' }}">
                            <div class="flex-shrink-0 me-3">
                                @if($msg->role === 'user')
                                    <span class="badge bg-primary">Visitor</span>
                                @else
                                    <span class="badge bg-secondary">AI</span>
                                @endif
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-1 small" style="white-space: pre-wrap;">{{ $msg->content }}</p>
                                <div class="text-muted" style="font-size:.75rem;">
                                    {{ $msg->created_at?->format('M j, Y g:i A') }}
                                    @if($msg->detected_intent)
                                        &nbsp;·&nbsp;<span class="badge bg-light text-dark border">{{ $msg->detected_intent }}</span>
                                    @endif
                                    @if($msg->lead_score_snapshot !== null)
                                        &nbsp;·&nbsp;Score: <strong>{{ $msg->lead_score_snapshot }}</strong>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-muted text-center small">No messages in this session.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@if($session->reviewed_at === null)
<script>
document.getElementById('markReviewedBtn')?.addEventListener('click', function () {
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Marking…';

    fetch('{{ route('agent.ai-inbox.mark-reviewed', $session->id) }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'reviewed') {
            btn.outerHTML = '<span class="badge bg-success"><i class="fa-solid fa-check me-1"></i>Reviewed</span>';
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Mark as Reviewed'; });
});
</script>
@endif
@endsection
