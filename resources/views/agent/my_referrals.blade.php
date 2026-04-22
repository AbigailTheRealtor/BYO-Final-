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

                            {{-- ── Page Header ─────────────────────────────────── --}}
                            <div class="mb-1">
                                <h4 class="fw-bold mb-0">My Referrals</h4>
                                <p class="text-muted small mb-0">
                                    Full referral activity across all funnel stages.
                                    @if($link)
                                        Code: <code>{{ $link->code }}</code>
                                    @endif
                                </p>
                            </div>
                            <hr class="mt-2 mb-4">

                            {{-- ── Summary Metric Cards ──────────────────────────── --}}
                            <div class="row g-3 mb-4">
                                <div class="col-6 col-md-3">
                                    <a href="{{ route('agent.my-referrals', ['stage' => 'clicks']) }}"
                                       class="text-decoration-none">
                                        <div class="card border-0 shadow-sm h-100 text-center py-3 px-2
                                            {{ $stage === 'clicks' ? 'border-bottom border-info border-3' : '' }}">
                                            <div class="fs-2 fw-bold text-info">{{ number_format($clickCount) }}</div>
                                            <div class="small text-muted mt-1">
                                                <i class="fa-solid fa-arrow-pointer me-1"></i>Clicks
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3">
                                    <a href="{{ route('agent.my-referrals', ['stage' => 'signups']) }}"
                                       class="text-decoration-none">
                                        <div class="card border-0 shadow-sm h-100 text-center py-3 px-2
                                            {{ $stage === 'signups' ? 'border-bottom border-success border-3' : '' }}">
                                            <div class="fs-2 fw-bold text-success">{{ number_format($signupCount) }}</div>
                                            <div class="small text-muted mt-1">
                                                <i class="fa-solid fa-user-plus me-1"></i>Signups
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3">
                                    <a href="{{ route('agent.my-referrals', ['stage' => 'listings']) }}"
                                       class="text-decoration-none">
                                        <div class="card border-0 shadow-sm h-100 text-center py-3 px-2
                                            {{ $stage === 'listings' ? 'border-bottom border-warning border-3' : '' }}">
                                            <div class="fs-2 fw-bold text-warning">{{ number_format($listingCount) }}</div>
                                            <div class="small text-muted mt-1">
                                                <i class="fa-solid fa-list me-1"></i>Listings
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 col-md-3">
                                    <a href="{{ route('agent.my-referrals', ['stage' => 'hires']) }}"
                                       class="text-decoration-none">
                                        <div class="card border-0 shadow-sm h-100 text-center py-3 px-2
                                            {{ $stage === 'hires' ? 'border-bottom border-primary border-3' : '' }}">
                                            <div class="fs-2 fw-bold text-primary">{{ number_format($hireCount) }}</div>
                                            <div class="small text-muted mt-1">
                                                <i class="fa-solid fa-handshake me-1"></i>Hires
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            </div>

                            {{-- ── Stage Filter Tabs ──────────────────────────────── --}}
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach($filterLabels as $key => $label)
                                    <a href="{{ route('agent.my-referrals', $key === 'all' ? [] : ['stage' => $key]) }}"
                                       class="btn btn-sm {{ $stage === $key ? 'btn-dark' : 'btn-outline-secondary' }}">
                                        {{ $label }}
                                        @if($key === 'all')
                                            <span class="badge bg-secondary ms-1">{{ $rows->count() }}</span>
                                        @elseif($key === 'clicks')
                                            <span class="badge ms-1 {{ $stage === 'clicks' ? 'bg-light text-dark' : 'bg-info text-dark' }}">{{ $clickCount }}</span>
                                        @elseif($key === 'signups')
                                            <span class="badge ms-1 {{ $stage === 'signups' ? 'bg-light text-dark' : 'bg-success' }}">{{ $signupCount }}</span>
                                        @elseif($key === 'listings')
                                            <span class="badge ms-1 {{ $stage === 'listings' ? 'bg-light text-dark' : 'bg-warning text-dark' }}">{{ $listingCount }}</span>
                                        @elseif($key === 'hires')
                                            <span class="badge ms-1 {{ $stage === 'hires' ? 'bg-light text-dark' : 'bg-primary' }}">{{ $hireCount }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>

                            {{-- ── Activity Table ──────────────────────────────────── --}}
                            @if($rows->isEmpty())
                                <div class="alert alert-light border text-center py-4">
                                    <i class="fa-solid fa-inbox fa-2x text-muted mb-2 d-block"></i>
                                    <span class="text-muted">
                                        No referral activity yet
                                        @if($stage !== 'all') in the <strong>{{ $filterLabels[$stage] }}</strong> stage @endif.
                                    </span>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle small mb-0" style="font-size:.85rem;">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-nowrap">Date</th>
                                                <th>Person / User</th>
                                                <th>Email</th>
                                                <th>Stage</th>
                                                <th class="text-center">Listing ID</th>
                                                <th>Listing Type</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($rows as $row)
                                                <tr>
                                                    {{-- Date --}}
                                                    <td class="text-nowrap text-muted">
                                                        {{ \Carbon\Carbon::parse($row->date)->format('M j, Y') }}
                                                        <div class="opacity-50" style="font-size:.75rem;">
                                                            {{ \Carbon\Carbon::parse($row->date)->format('g:i A') }}
                                                        </div>
                                                    </td>

                                                    {{-- Person / User --}}
                                                    <td>
                                                        @if($row->person_name)
                                                            <span class="fw-semibold">{{ $row->person_name }}</span>
                                                        @else
                                                            <span class="text-muted fst-italic">Anonymous</span>
                                                            @if($row->stage === 'click' && $row->note)
                                                                <div class="opacity-50" style="font-size:.75rem;">
                                                                    <i class="fa-solid fa-globe me-1"></i>{{ $row->note }}
                                                                </div>
                                                            @endif
                                                        @endif

                                                        {{-- For hires: show which agent was hired below the client name --}}
                                                        @if($row->stage === 'hire' && $row->note)
                                                            <div class="text-muted" style="font-size:.75rem;">
                                                                <i class="fa-solid fa-user-tie me-1"></i>Hired: {{ $row->note }}
                                                            </div>
                                                        @endif
                                                    </td>

                                                    {{-- Email --}}
                                                    <td>
                                                        @if($row->email)
                                                            <span class="text-muted">{{ $row->email }}</span>
                                                        @else
                                                            <span class="text-muted opacity-50">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- Stage badge --}}
                                                    <td>
                                                        <span class="badge {{ $stageBadgeClass[$row->stage] ?? 'bg-secondary' }}">
                                                            {{ $stageLabels[$row->stage] ?? ucfirst($row->stage) }}
                                                        </span>
                                                    </td>

                                                    {{-- Listing ID --}}
                                                    <td class="text-center">
                                                        @if($row->listing_id)
                                                            <code class="text-muted">#{{ $row->listing_id }}</code>
                                                        @else
                                                            <span class="text-muted opacity-50">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- Listing Type --}}
                                                    <td>
                                                        @if($row->listing_type)
                                                            {{ $row->listing_type }}
                                                        @else
                                                            <span class="text-muted opacity-50">—</span>
                                                        @endif
                                                    </td>

                                                    {{-- Status --}}
                                                    <td>
                                                        @if($row->stage === 'hire' && $row->status)
                                                            <span class="badge {{ $referralStatusClass[$row->status] ?? 'bg-secondary' }}">
                                                                {{ ucfirst($row->status) }}
                                                            </span>
                                                        @elseif($row->status)
                                                            <span class="text-muted small">{{ $row->status }}</span>
                                                        @else
                                                            <span class="text-muted opacity-50">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-muted small mt-2 text-end">
                                    {{ $rows->count() }} record(s)
                                    @if($stage !== 'all') in <strong>{{ $filterLabels[$stage] }}</strong> stage @endif
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
