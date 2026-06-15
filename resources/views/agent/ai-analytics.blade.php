@extends('layouts.main')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        @include('layouts.partials.sidenav')

        <div class="col-sm-12 col-md-9 col-lg-9">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h4 class="mb-0 fw-bold">AI Analytics</h4>
                    <p class="text-muted small mb-0">Performance metrics for your Agent AI assistant. Metrics refresh every 5 minutes.</p>
                </div>
            </div>

            {{-- ── Summary metric cards ─────────────────────────────────────── --}}
            <div class="row g-3 mb-4">

                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-muted small fw-semibold mb-1 text-uppercase" style="letter-spacing:.05em;font-size:.7rem;">Questions (30d)</div>
                            <div class="display-6 fw-bold text-primary">{{ number_format($totalQuestionsLast30) }}</div>
                            <div class="text-muted small">All-time: {{ number_format($totalQuestionsAllTime) }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-muted small fw-semibold mb-1 text-uppercase" style="letter-spacing:.05em;font-size:.7rem;">Lead Conversion</div>
                            <div class="display-6 fw-bold text-success">{{ $conversionRate }}%</div>
                            <div class="text-muted small">{{ $sessionsWithEmail }} of {{ $totalSessions }} sessions</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-muted small fw-semibold mb-1 text-uppercase" style="letter-spacing:.05em;font-size:.7rem;">Hot Leads (30d)</div>
                            <div class="display-6 fw-bold text-danger">{{ number_format($hotLeadsLast30) }}</div>
                            <div class="text-muted small">All-time: {{ number_format($hotLeadsAllTime) }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="text-muted small fw-semibold mb-1 text-uppercase" style="letter-spacing:.05em;font-size:.7rem;">Total Sessions</div>
                            <div class="display-6 fw-bold" style="color:#049399;">{{ number_format($totalSessions) }}</div>
                            <div class="text-muted small">all-time</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">

                {{-- ── Most-asked topics ─────────────────────────────────────── --}}
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom fw-semibold small">
                            Most-Asked Topics <span class="text-muted fw-normal">(last 30 days)</span>
                        </div>
                        <div class="card-body p-0">
                            @if($topTopicsLast30->isEmpty())
                                <div class="text-center text-muted py-4 small">No data yet.</div>
                            @else
                                <ul class="list-group list-group-flush">
                                    @foreach($topTopicsLast30 as $topic)
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                            <span class="small">{{ ucfirst(str_replace('_', ' ', $topic->detected_intent)) }}</span>
                                            <span class="badge bg-primary rounded-pill">{{ $topic->cnt }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ── Top CTAs clicked ──────────────────────────────────────── --}}
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom fw-semibold small">
                            Top CTAs Clicked <span class="text-muted fw-normal">(last 30 days)</span>
                        </div>
                        <div class="card-body p-0">
                            @if($topCtasLast30->isEmpty())
                                <div class="text-center text-muted py-4 small">No data yet.</div>
                            @else
                                <ul class="list-group list-group-flush">
                                    @foreach($topCtasLast30 as $cta)
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                            <span class="small">{{ ucwords(str_replace('_', ' ', $cta->action_key)) }}</span>
                                            <span class="badge bg-success rounded-pill">{{ $cta->cnt }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ── Most viewed listings ──────────────────────────────────── --}}
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom fw-semibold small">
                            Most Viewed Listings <span class="text-muted fw-normal">(by session count)</span>
                        </div>
                        <div class="card-body p-0">
                            @if($topListings->isEmpty())
                                <div class="text-center text-muted py-4 small">No data yet.</div>
                            @else
                                <ul class="list-group list-group-flush">
                                    @foreach($topListings as $listing)
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                            <span class="small">
                                                <span class="badge bg-secondary me-1">{{ $listing->listing_type ?? 'unknown' }}</span>
                                                ID {{ $listing->listing_id }}
                                            </span>
                                            <span class="badge rounded-pill" style="background:#049399;">{{ $listing->session_count }} sessions</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ── Most requested services ───────────────────────────────── --}}
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom fw-semibold small">
                            Most Requested Services <span class="text-muted fw-normal">(by intent phrase)</span>
                        </div>
                        <div class="card-body p-0">
                            @if($topServices->isEmpty())
                                <div class="text-center text-muted py-4 small">No data yet.</div>
                            @else
                                <ul class="list-group list-group-flush">
                                    @foreach($topServices as $service)
                                        <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                            <span class="small text-truncate" style="max-width:80%;" title="{{ $service->intent_phrase }}">
                                                {{ Str::limit($service->intent_phrase, 60) }}
                                            </span>
                                            <span class="badge bg-warning text-dark rounded-pill">{{ $service->cnt }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>

            </div>{{-- end .row --}}

        </div>{{-- end main col --}}
    </div>{{-- end .row --}}
</div>
@endsection
