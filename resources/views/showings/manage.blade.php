@extends('layouts.main')

@section('title', 'Showing Requests')

@section('content')
<div class="container-fluid py-4">
    <div class="row">

        {{-- Sidenav --}}
        @include('layouts.partials.sidenav')

        {{-- Main content --}}
        <div class="col-sm-12 col-md-9 col-lg-9">

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="mb-0 fw-bold">Showing Requests</h4>
                <span class="text-muted small">All requests for your listings</span>
            </div>

            @if($showings->isEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="fa-regular fa-calendar-check fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0">No showing requests yet.</p>
                    </div>
                </div>
            @else

            {{-- ─── PENDING / REQUESTED ─────────────────────────────────── --}}
            @php $pending = $grouped->get('requested', collect()); @endphp
            @if($pending->isNotEmpty())
            <div class="mb-4">
                <h6 class="text-uppercase fw-bold text-muted mb-2" style="font-size:.72rem;letter-spacing:.07em;">
                    <span class="badge bg-warning text-dark me-1">{{ $pending->count() }}</span> Pending Requests
                </h6>
                @foreach($pending as $showing)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold">
                                    {{ $showing->requester->first_name ?? '' }} {{ $showing->requester->last_name ?? '' }}
                                    @if($showing->requested_by_agent)
                                        <span class="badge bg-secondary ms-1">Agent</span>
                                    @endif
                                </div>
                                <div class="text-muted small mt-1">
                                    <i class="fa-regular fa-calendar me-1"></i>
                                    {{ \Carbon\Carbon::parse($showing->requested_date)->format('M j, Y') }}
                                    &nbsp;·&nbsp;
                                    <i class="fa-regular fa-clock me-1"></i>
                                    {{ \Carbon\Carbon::parse($showing->requested_start_time)->format('g:i A') }}
                                    – {{ \Carbon\Carbon::parse($showing->requested_end_time)->format('g:i A') }}
                                </div>
                                @if($showing->requester_message)
                                    <div class="text-muted small mt-1 fst-italic">"{{ $showing->requester_message }}"</div>
                                @endif
                                <div class="text-muted small mt-1">
                                    Listing #{{ $showing->offer_auction_id }}
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-sm btn-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#approveModal-{{ $showing->id }}">
                                    <i class="fa-solid fa-check me-1"></i>Approve
                                </button>
                                <button class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#declineModal-{{ $showing->id }}">
                                    <i class="fa-solid fa-xmark me-1"></i>Decline
                                </button>
                                <form method="POST" action="{{ route('showings.cancel', $showing) }}"
                                      onsubmit="return confirm('Cancel this showing request?')">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-ban me-1"></i>Cancel
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                @include('showings._approve-form', ['showing' => $showing])
                @include('showings._decline-form', ['showing' => $showing])
                @endforeach
            </div>
            @endif

            {{-- ─── APPROVED ─────────────────────────────────────────────── --}}
            @php $approved = $grouped->get('approved', collect()); @endphp
            @if($approved->isNotEmpty())
            <div class="mb-4">
                <h6 class="text-uppercase fw-bold text-muted mb-2" style="font-size:.72rem;letter-spacing:.07em;">
                    <span class="badge bg-success me-1">{{ $approved->count() }}</span> Approved
                </h6>
                @foreach($approved as $showing)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold">
                                    {{ $showing->requester->first_name ?? '' }} {{ $showing->requester->last_name ?? '' }}
                                    @if($showing->requested_by_agent)
                                        <span class="badge bg-secondary ms-1">Agent</span>
                                    @endif
                                </div>
                                <div class="text-muted small mt-1">
                                    <i class="fa-regular fa-calendar me-1"></i>
                                    @if($showing->approved_date)
                                        {{ \Carbon\Carbon::parse($showing->approved_date)->format('M j, Y') }}
                                        &nbsp;·&nbsp;
                                        <i class="fa-regular fa-clock me-1"></i>
                                        {{ \Carbon\Carbon::parse($showing->approved_start_time)->format('g:i A') }}
                                        – {{ \Carbon\Carbon::parse($showing->approved_end_time)->format('g:i A') }}
                                    @else
                                        {{ \Carbon\Carbon::parse($showing->requested_date)->format('M j, Y') }}
                                        &nbsp;·&nbsp;
                                        <i class="fa-regular fa-clock me-1"></i>
                                        {{ \Carbon\Carbon::parse($showing->requested_start_time)->format('g:i A') }}
                                        – {{ \Carbon\Carbon::parse($showing->requested_end_time)->format('g:i A') }}
                                    @endif
                                </div>
                                @if($showing->owner_message)
                                    <div class="text-muted small mt-1 fst-italic">"{{ $showing->owner_message }}"</div>
                                @endif
                                <div class="text-muted small mt-1">
                                    Listing #{{ $showing->offer_auction_id }}
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <form method="POST" action="{{ route('showings.complete', $showing) }}"
                                      onsubmit="return confirm('Mark this showing as completed?')">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-primary">
                                        <i class="fa-solid fa-circle-check me-1"></i>Mark Complete
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('showings.cancel', $showing) }}"
                                      onsubmit="return confirm('Cancel this approved showing?')">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-sm btn-outline-secondary">
                                        <i class="fa-solid fa-ban me-1"></i>Cancel
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- ─── COMPLETED ────────────────────────────────────────────── --}}
            @php $completed = $grouped->get('completed', collect()); @endphp
            @if($completed->isNotEmpty())
            <div class="mb-4">
                <h6 class="text-uppercase fw-bold text-muted mb-2" style="font-size:.72rem;letter-spacing:.07em;">
                    <span class="badge bg-primary me-1">{{ $completed->count() }}</span> Completed
                </h6>
                @foreach($completed as $showing)
                <div class="card border-0 shadow-sm mb-2 opacity-75">
                    <div class="card-body py-2">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <span class="fw-semibold">
                                    {{ $showing->requester->first_name ?? '' }} {{ $showing->requester->last_name ?? '' }}
                                </span>
                                <span class="text-muted small ms-2">
                                    {{ \Carbon\Carbon::parse($showing->requested_date)->format('M j, Y') }}
                                </span>
                                <span class="text-muted small ms-2">Listing #{{ $showing->offer_auction_id }}</span>
                            </div>
                            <span class="badge bg-light text-success border border-success">Completed</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- ─── DECLINED ─────────────────────────────────────────────── --}}
            @php $declined = $grouped->get('declined', collect()); @endphp
            @if($declined->isNotEmpty())
            <div class="mb-4">
                <h6 class="text-uppercase fw-bold text-muted mb-2" style="font-size:.72rem;letter-spacing:.07em;">
                    <span class="badge bg-danger me-1">{{ $declined->count() }}</span> Declined
                </h6>
                @foreach($declined as $showing)
                <div class="card border-0 shadow-sm mb-2 opacity-75">
                    <div class="card-body py-2">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <span class="fw-semibold">
                                    {{ $showing->requester->first_name ?? '' }} {{ $showing->requester->last_name ?? '' }}
                                </span>
                                <span class="text-muted small ms-2">
                                    {{ \Carbon\Carbon::parse($showing->requested_date)->format('M j, Y') }}
                                </span>
                                <span class="text-muted small ms-2">Listing #{{ $showing->offer_auction_id }}</span>
                            </div>
                            <span class="badge bg-light text-danger border border-danger">Declined</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- ─── CANCELED ─────────────────────────────────────────────── --}}
            @php $canceled = $grouped->get('canceled', collect()); @endphp
            @if($canceled->isNotEmpty())
            <div class="mb-4">
                <h6 class="text-uppercase fw-bold text-muted mb-2" style="font-size:.72rem;letter-spacing:.07em;">
                    <span class="badge bg-secondary me-1">{{ $canceled->count() }}</span> Canceled
                </h6>
                @foreach($canceled as $showing)
                <div class="card border-0 shadow-sm mb-2 opacity-75">
                    <div class="card-body py-2">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <span class="fw-semibold">
                                    {{ $showing->requester->first_name ?? '' }} {{ $showing->requester->last_name ?? '' }}
                                </span>
                                <span class="text-muted small ms-2">
                                    {{ \Carbon\Carbon::parse($showing->requested_date)->format('M j, Y') }}
                                </span>
                                <span class="text-muted small ms-2">Listing #{{ $showing->offer_auction_id }}</span>
                            </div>
                            <span class="badge bg-light text-secondary border">Canceled</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Pagination --}}
            <div class="d-flex justify-content-center mt-4">
                {{ $showings->links() }}
            </div>

            @endif
        </div>{{-- /col --}}
    </div>{{-- /row --}}
</div>{{-- /container --}}
@endsection
