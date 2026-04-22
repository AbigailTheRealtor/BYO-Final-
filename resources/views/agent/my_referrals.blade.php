@extends('layouts.main')

@php
    $stageLabels = [
        'click'   => 'Click',
        'signup'  => 'Signup',
        'listing' => 'Listing',
        'hire'    => 'Hire',
    ];

    $stageBadgeClass = [
        'click'   => 'bg-info text-dark',
        'signup'  => 'bg-success',
        'listing' => 'bg-warning text-dark',
        'hire'    => 'bg-primary',
    ];

    $referralStatusClass = [
        'pending'  => 'bg-warning text-dark',
        'paid'     => 'bg-success',
        'rejected' => 'bg-danger',
        'disputed' => 'bg-secondary',
    ];

    $clickCount   = $link->click_count   ?? 0;
    $signupCount  = $link->signup_count  ?? 0;
    $listingCount = $link->listing_count ?? 0;
    $hireCount    = $link->hire_count    ?? 0;

    $filterLabels = [
        'all'      => 'All',
        'clicks'   => 'Clicks',
        'signups'  => 'Signups',
        'listings' => 'Listings',
        'hires'    => 'Hires',
    ];
@endphp

@section('content')
<div class="mainDashboard">
    <div class="container">

        @include('layouts.partials.dashboard_user_section')

        <div class="dashboardContentDetails mt-3">
            <div class="card">
                <div class="row">

                    @include('layouts.partials.sidenav')

                    <div class="rightCol col-sm-12 col-md-9 col-lg-9">
                        <div class="container mt-4 mb-5">

                            {{-- ── Page Header ─────────────────────────────────────── --}}
                            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-1">
                                <div>
                                    <h4 class="fw-bold mb-1">My Referrals</h4>
                                    <p class="text-muted mb-0" style="font-size:.88rem;">
                                        Track activity tied to your referral link across clicks, signups, listings, and hires.
                                    </p>
                                </div>
                                @if($link)
                                <div class="text-end">
                                    <span class="text-muted small d-block" style="font-size:.75rem;">Your referral code</span>
                                    <code style="font-size:.85rem;letter-spacing:.03em;">{{ $link->code }}</code>
                                </div>
                                @endif
                            </div>
                            <hr class="mt-3 mb-4">

                            {{-- ── Summary Metric Cards ─────────────────────────────── --}}
                            <div class="row g-3 mb-4">

                                {{-- Clicks --}}
                                <div class="col-6 col-md-3">
                                    <a href="{{ route('agent.my-referrals', ['stage' => 'clicks']) }}" class="text-decoration-none">
                                        <div class="card border-0 shadow-sm h-100 text-center py-3 px-2"
                                             style="{{ $stage === 'clicks' ? 'border-bottom:3px solid #0dcaf0 !important;' : '' }}">
                                            <div class="fs-2 fw-bold text-info lh-1 mb-1">{{ number_format($clickCount) }}</div>
                                            <div class="text-muted" style="font-size:.78rem;letter-spacing:.03em;">
                                                <i class="fa-solid fa-arrow-pointer me-1 opacity-75"></i>Clicks
                                            </div>
                                        </div>
                                    </a>
                                </div>

                                {{-- Signups --}}
                                <div class="col-6 col-md-3">
                                    <a href="{{ route('agent.my-referrals', ['stage' => 'signups']) }}" class="text-decoration-none">
                                        <div class="card border-0 shadow-sm h-100 text-center py-3 px-2"
                                             style="{{ $stage === 'signups' ? 'border-bottom:3px solid #198754 !important;' : '' }}">
                                            <div class="fs-2 fw-bold text-success lh-1 mb-1">{{ number_format($signupCount) }}</div>
                                            <div class="text-muted" style="font-size:.78rem;letter-spacing:.03em;">
                                                <i class="fa-solid fa-user-plus me-1 opacity-75"></i>Signups
                                            </div>
                                        </div>
                                    </a>
                                </div>

                                {{-- Listings --}}
                                <div class="col-6 col-md-3">
                                    <a href="{{ route('agent.my-referrals', ['stage' => 'listings']) }}" class="text-decoration-none">
                                        <div class="card border-0 shadow-sm h-100 text-center py-3 px-2"
                                             style="{{ $stage === 'listings' ? 'border-bottom:3px solid #ffc107 !important;' : '' }}">
                                            <div class="fs-2 fw-bold text-warning lh-1 mb-1">{{ number_format($listingCount) }}</div>
                                            <div class="text-muted" style="font-size:.78rem;letter-spacing:.03em;">
                                                <i class="fa-solid fa-list me-1 opacity-75"></i>Listings
                                            </div>
                                        </div>
                                    </a>
                                </div>

                                {{-- Hires --}}
                                <div class="col-6 col-md-3">
                                    <a href="{{ route('agent.my-referrals', ['stage' => 'hires']) }}" class="text-decoration-none">
                                        <div class="card border-0 shadow-sm h-100 text-center py-3 px-2"
                                             style="{{ $stage === 'hires' ? 'border-bottom:3px solid #0d6efd !important;' : '' }}">
                                            <div class="fs-2 fw-bold text-primary lh-1 mb-1">{{ number_format($hireCount) }}</div>
                                            <div class="text-muted" style="font-size:.78rem;letter-spacing:.03em;">
                                                <i class="fa-solid fa-handshake me-1 opacity-75"></i>Hires
                                            </div>
                                        </div>
                                    </a>
                                </div>

                            </div>

                            {{-- ── Stage Filter Tabs ────────────────────────────────── --}}
                            <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                                <span class="text-muted small me-1" style="font-size:.75rem;letter-spacing:.04em;text-transform:uppercase;font-weight:600;">Filter:</span>
                                @foreach($filterLabels as $key => $label)
                                    <a href="{{ route('agent.my-referrals', $key === 'all' ? [] : ['stage' => $key]) }}"
                                       class="btn btn-sm {{ $stage === $key ? 'btn-dark' : 'btn-outline-secondary' }}"
                                       style="font-size:.78rem;">
                                        {{ $label }}
                                        @if($key === 'all')
                                            <span class="badge bg-secondary ms-1" style="font-size:.7rem;">{{ $rows->count() }}</span>
                                        @elseif($key === 'clicks')
                                            <span class="badge ms-1 {{ $stage === 'clicks' ? 'bg-light text-dark' : 'bg-info text-dark' }}" style="font-size:.7rem;">{{ $clickCount }}</span>
                                        @elseif($key === 'signups')
                                            <span class="badge ms-1 {{ $stage === 'signups' ? 'bg-light text-dark' : 'bg-success' }}" style="font-size:.7rem;">{{ $signupCount }}</span>
                                        @elseif($key === 'listings')
                                            <span class="badge ms-1 {{ $stage === 'listings' ? 'bg-light text-dark' : 'bg-warning text-dark' }}" style="font-size:.7rem;">{{ $listingCount }}</span>
                                        @elseif($key === 'hires')
                                            <span class="badge ms-1 {{ $stage === 'hires' ? 'bg-light text-dark' : 'bg-primary' }}" style="font-size:.7rem;">{{ $hireCount }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>

                            {{-- ── Activity Table ───────────────────────────────────── --}}
                            @if($rows->isEmpty())
                                <div class="alert alert-light border text-center py-5 mt-2">
                                    <i class="fa-solid fa-inbox fa-2x text-muted mb-3 d-block"></i>
                                    <div class="text-muted">
                                        No referral activity yet
                                        @if($stage !== 'all')
                                            in the <strong>{{ $filterLabels[$stage] }}</strong> stage
                                        @endif.
                                    </div>
                                    @if($stage !== 'all')
                                        <a href="{{ route('agent.my-referrals') }}" class="btn btn-sm btn-outline-secondary mt-3">
                                            View all stages
                                        </a>
                                    @endif
                                </div>
                            @else
                                <div class="table-responsive rounded border" style="font-size:.84rem;">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead style="background:#f8f9fa;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6c757d;">
                                            <tr>
                                                <th class="text-nowrap ps-3 py-2" style="width:110px;">Date</th>
                                                <th class="py-2">Person / User</th>
                                                <th class="py-2">Email</th>
                                                <th class="py-2" style="width:80px;">Stage</th>
                                                <th class="text-center py-2" style="width:90px;">Listing ID</th>
                                                <th class="py-2" style="width:120px;">Type</th>
                                                <th class="py-2" style="width:90px;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($rows as $row)
                                                <tr style="border-left:3px solid {{ $row->stage === 'hire' ? '#0d6efd' : ($row->stage === 'listing' ? '#ffc107' : ($row->stage === 'signup' ? '#198754' : '#0dcaf0')) }};">

                                                    {{-- Date --}}
                                                    <td class="ps-3 text-nowrap">
                                                        <div class="fw-medium text-dark" style="font-size:.82rem;">
                                                            {{ \Carbon\Carbon::parse($row->date)->format('M j, Y') }}
                                                        </div>
                                                        <div class="text-muted" style="font-size:.72rem;">
                                                            {{ \Carbon\Carbon::parse($row->date)->format('g:i A') }}
                                                        </div>
                                                    </td>

                                                    {{-- Person / User --}}
                                                    <td>
                                                        @if($row->person_name)
                                                            {{-- Named user --}}
                                                            <div class="fw-semibold text-dark" style="font-size:.84rem;">
                                                                {{ $row->person_name }}
                                                            </div>
                                                        @else
                                                            {{-- Anonymous click visitor --}}
                                                            <div class="d-flex align-items-center gap-1">
                                                                <i class="fa-solid fa-user-secret text-muted opacity-50" style="font-size:.8rem;"></i>
                                                                <span class="text-muted fst-italic" style="font-size:.82rem;">Anonymous Visitor</span>
                                                            </div>
                                                            @if($row->stage === 'click' && $row->note)
                                                                <div class="text-muted mt-1" style="font-size:.72rem;">
                                                                    <i class="fa-solid fa-globe me-1 opacity-50"></i>{{ $row->note }}
                                                                </div>
                                                            @endif
                                                        @endif

                                                        {{-- Hired agent pill — only on hire rows --}}
                                                        @if($row->stage === 'hire' && $row->note)
                                                            <div class="mt-1">
                                                                <span style="display:inline-flex;align-items:center;gap:4px;background:#e8f0fe;border:1px solid #c2d1f8;border-radius:4px;padding:2px 7px;font-size:.72rem;color:#3c5a99;white-space:nowrap;">
                                                                    <i class="fa-solid fa-user-tie" style="font-size:.68rem;"></i>
                                                                    Hired agent: {{ $row->note }}
                                                                </span>
                                                            </div>
                                                        @endif
                                                    </td>

                                                    {{-- Email --}}
                                                    <td>
                                                        @if($row->email)
                                                            <span class="text-muted" style="font-size:.82rem;">{{ $row->email }}</span>
                                                        @else
                                                            <span class="text-muted opacity-40">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- Stage badge --}}
                                                    <td>
                                                        <span class="badge {{ $stageBadgeClass[$row->stage] ?? 'bg-secondary' }}"
                                                              style="font-size:.72rem;font-weight:600;letter-spacing:.02em;">
                                                            {{ $stageLabels[$row->stage] ?? ucfirst($row->stage) }}
                                                        </span>
                                                    </td>

                                                    {{-- Listing ID --}}
                                                    <td class="text-center">
                                                        @if($row->listing_id)
                                                            <code class="text-muted" style="font-size:.78rem;">#{{ $row->listing_id }}</code>
                                                        @else
                                                            <span class="text-muted opacity-40">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- Listing Type --}}
                                                    <td>
                                                        @if($row->listing_type)
                                                            <span class="text-muted" style="font-size:.82rem;">{{ $row->listing_type }}</span>
                                                        @else
                                                            <span class="text-muted opacity-40">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- Status --}}
                                                    <td>
                                                        @if($row->stage === 'hire' && $row->status)
                                                            <span class="badge {{ $referralStatusClass[$row->status] ?? 'bg-secondary' }}"
                                                                  style="font-size:.72rem;">
                                                                {{ ucfirst($row->status) }}
                                                            </span>
                                                        @elseif($row->status)
                                                            <span class="text-muted" style="font-size:.8rem;">{{ $row->status }}</span>
                                                        @else
                                                            <span class="text-muted opacity-40">—</span>
                                                        @endif
                                                    </td>

                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Row count footer --}}
                                <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                                    <span class="text-muted" style="font-size:.75rem;">
                                        @if($stage !== 'all')
                                            Showing <strong>{{ $filterLabels[$stage] }}</strong> stage only.
                                            <a href="{{ route('agent.my-referrals') }}" class="ms-1">View all</a>
                                        @else
                                            All stages
                                        @endif
                                    </span>
                                    <span class="text-muted" style="font-size:.75rem;">
                                        {{ $rows->count() }} record{{ $rows->count() !== 1 ? 's' : '' }}
                                    </span>
                                </div>
                            @endif

                        </div>
                    </div>{{-- /.rightCol --}}

                </div>
            </div>
        </div>

    </div>
</div>
@endsection
