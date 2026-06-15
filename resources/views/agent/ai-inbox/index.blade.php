@extends('layouts.main')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        @include('layouts.partials.sidenav')

        <div class="col-sm-12 col-md-9 col-lg-9">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h4 class="mb-0 fw-bold">AI Inbox</h4>
                    <p class="text-muted small mb-0">Chat sessions and lead signals from your Agent AI assistant.</p>
                </div>
                @if($unreadHotLeadCount > 0)
                    <span class="badge bg-danger fs-6">{{ $unreadHotLeadCount }} unread hot lead{{ $unreadHotLeadCount !== 1 ? 's' : '' }}</span>
                @endif
            </div>

            {{-- Filters --}}
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body pb-2">
                    <form method="GET" action="{{ route('agent.ai-inbox.index') }}" class="row g-2 align-items-end">
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label small fw-semibold">Min Score</label>
                            <input type="number" name="min_score" class="form-control form-control-sm" value="{{ $minScore }}" min="0" max="100" placeholder="0">
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label small fw-semibold">Max Score</label>
                            <input type="number" name="max_score" class="form-control form-control-sm" value="{{ $maxScore }}" min="0" max="100" placeholder="100">
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label small fw-semibold">Lead Type</label>
                            <select name="lead_type" class="form-select form-select-sm">
                                <option value="">All Types</option>
                                @foreach(\App\Models\AgentAiChatLead::LEAD_TYPES as $type)
                                    <option value="{{ $type }}" @selected($leadType === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label small fw-semibold">Date From</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
                        </div>
                        <div class="col-sm-6 col-md-2">
                            <label class="form-label small fw-semibold">Date To</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
                        </div>
                        <div class="col-sm-6 col-md-2 d-flex gap-1">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="{{ route('agent.ai-inbox.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Session list --}}
            @forelse($sessions as $session)
                @php
                    $lead       = $session->lead;
                    $score      = $lead?->lead_score ?? 0;
                    $reviewed   = $session->reviewed_at !== null;
                    $badgeClass = match(true) {
                        $score >= 90 => 'bg-danger',
                        $score >= 75 => 'bg-warning text-dark',
                        $score >= 50 => 'bg-info text-dark',
                        default      => 'bg-secondary',
                    };
                    $messageCount = $session->messages->count();
                    $latestUserMsg = $session->messages->where('role','user')->last();
                @endphp
                <div class="card mb-3 border-0 shadow-sm {{ !$reviewed && $score >= 75 ? 'border-start border-4 border-danger' : '' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                            <div>
                                <div class="fw-semibold mb-1">
                                    {{ $lead?->visitor_name ?? $lead?->visitor_email ?? 'Anonymous Visitor' }}
                                    @if(!$reviewed && $score >= 75)
                                        <span class="badge bg-danger ms-2 small">Unread</span>
                                    @endif
                                </div>
                                <div class="text-muted small mb-1">
                                    <i class="fa-solid fa-clock me-1"></i>{{ $session->last_active_at?->diffForHumans() }}
                                    &nbsp;·&nbsp;
                                    {{ $messageCount }} message{{ $messageCount !== 1 ? 's' : '' }}
                                </div>
                                @if($lead?->lead_type)
                                    <span class="badge bg-light text-dark border me-1">{{ ucfirst(str_replace('_',' ',$lead->lead_type)) }}</span>
                                @endif
                                @if($lead?->conversation_summary)
                                    <p class="text-muted small mt-2 mb-0">{{ \Illuminate\Support\Str::limit($lead->conversation_summary, 140) }}</p>
                                @elseif($latestUserMsg)
                                    <p class="text-muted small mt-2 mb-0 fst-italic">"{{ \Illuminate\Support\Str::limit($latestUserMsg->content, 120) }}"</p>
                                @endif
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="badge {{ $badgeClass }} fs-6 mb-2">{{ $score }}</span>
                                <br>
                                <a href="{{ route('agent.ai-inbox.show', $session->id) }}" class="btn btn-sm btn-outline-primary">View Thread</a>
                            </div>
                        </div>
                        @if($lead?->recommended_follow_up)
                            <div class="mt-2 p-2 bg-light rounded small">
                                <i class="fa-solid fa-lightbulb text-warning me-1"></i>
                                <strong>Follow-up:</strong> {{ $lead->recommended_follow_up }}
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-inbox fa-3x mb-3 opacity-25"></i>
                    <p class="mb-0">No AI chat sessions found.</p>
                    <p class="small">Sessions appear here when visitors interact with your Agent AI assistant.</p>
                </div>
            @endforelse

            {{ $sessions->links('pagination::bootstrap-4') }}
        </div>
    </div>
</div>
@endsection
