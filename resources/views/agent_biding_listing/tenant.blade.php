@extends('layouts.main')
@push('styles')
<style>
.agent-bid-page h4 { font-weight: 700; }
.agent-bid-page .page-subtitle { font-size: .85rem; color: #6c757d; }
.agent-bid-page .back-link { font-size: .8rem; color: #049399; text-decoration: none; }
.agent-bid-page .back-link:hover { text-decoration: underline; }
</style>
@endpush
@section('content')
    <div class="mainDashboard">
        <div class="container">
            @include('layouts.partials.dashboard_user_section')
            <div class="dashboardContentDetails mt-3">
                <div class="card">
                    <div class="row">
                        @include('layouts.partials.sidenav')
                        <div class="rightCol col-sm-12 col-md-9 col-lg-9">
                            <div class="container mt-4 myAuctions agent-bid-page">

                                {{-- Page Header --}}
                                <div class="mb-3">
                                    <a href="{{ route('agent.hire-listings') }}" class="back-link">
                                        <i class="fa fa-arrow-left me-1"></i> My Hire Agent Listings
                                    </a>
                                    <h4 class="mt-2 mb-0">Hire Tenant's Agent — My Bids</h4>
                                    <p class="page-subtitle mb-0">Listings where you've placed bids to represent Tenants.</p>
                                </div>
                                <hr class="mt-2 mb-3">

                                <!-- Status Filter -->
                                <select class="form-select mb-3 w-25 auction-type">
                                    <option value="2" {{ $type == '2' ? 'selected' : '' }}>Live ({{ $liveCount }})</option>
                                    <option value="1" {{ $type == '1' ? 'selected' : '' }}>Pending Approval ({{ $pendingApprovalCount }})</option>
                                    <option value="3" {{ $type == '3' ? 'selected' : '' }}>Awarded ({{ $soldCount }})</option>
                                </select>

                                @if($auctions->isEmpty())
                                    <div class="text-center text-muted py-5">
                                        <i class="fa fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                                        <p class="fw-semibold mb-1">No listings found for this status.</p>
                                        <p class="small mb-0">Switch the filter above or browse live Tenant's Agent listings to place a bid.</p>
                                    </div>
                                @else
                                <table class="table table-bordered data-table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>Title</th>
                                            <th>County</th>
                                            <th>City</th>
                                            <th>State</th>
                                            <th>Creation Date</th>
                                            <th class="text-center">Bid Status</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($auctions as $auction)
                                            @php
                                                $userBid = $auction->bids->where('user_id', auth()->id())->first();
                                                $hasCounterBids = $userBid ? \App\Models\TenantCounterBidding::where('tenant_agent_auction_bid_id', $userBid->id)->exists() : false;
                                                $bidState = $userBid ? $userBid->accepted : null;
                                                $bidStatusLabel = match($bidState) {
                                                    'accepted' => 'Accepted',
                                                    'rejected' => 'Rejected',
                                                    'countered' => 'Countered',
                                                    default => $hasCounterBids ? 'Countered' : 'Active',
                                                };
                                                $bidStatusClass = match($bidState) {
                                                    'accepted' => 'bg-success',
                                                    'rejected' => 'bg-danger',
                                                    'countered' => 'bg-warning text-dark',
                                                    default => $hasCounterBids ? 'bg-warning text-dark' : 'bg-info',
                                                };
                                                $endDate = strtotime($auction->end_date . ' ' . ($auction->end_time ?? '23:59:59'));
                                                $isExpired = time() > $endDate;
                                                $canEditWithdraw = $userBid && !$isExpired && $bidState !== 'accepted' && $bidState !== 'rejected';
                                            @endphp
                                            <tr>
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td><a href="{{ route('tenant.agent.view.auction.view', @$auction->id) }}">{{ @$auction->title }}</a></td>
                                                <td>{{ $auction->get->counties[0] ?? '' }}</td>
                                                <td>{{ $auction->get->cities[0] ?? '' }}</td>
                                                <td>{{ @$auction->get->state }}</td>
                                                <td>{{ Carbon\Carbon::parse(@$auction->created_at)->format('M d, Y') }}</td>
                                                <td class="text-center">
                                                    @if($userBid)
                                                        <span class="badge {{ $bidStatusClass }}">{{ $bidStatusLabel }}</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <div class="dropdown">
                                                        <button class="btn btn-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Action
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item" href="{{ route('tenant.agent.view.auction.view', @$auction->id) }}">
                                                                    <i class="fa-solid fa-eye" style="font-size:14px;"></i>
                                                                    <span style="font-size:14px;">View Listing</span>
                                                                </a>
                                                            </li>
                                                            @if($canEditWithdraw && $userBid)
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <a class="dropdown-item" href="{{ route('agent.tenant.agent.auction.bid', $auction->id) }}?edit={{ $userBid->id }}">
                                                                    <i class="fa-solid fa-edit" style="font-size:14px;"></i>
                                                                    <span style="font-size:14px;">Edit Bid</span>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <form action="{{ route('tenant.hire.agent.auction.bid.withdraw') }}" method="POST"
                                                                      onsubmit="return confirm('Are you sure you want to withdraw your bid? This action cannot be undone.');">
                                                                    @csrf
                                                                    <input type="hidden" name="bid_id" value="{{ $userBid->id }}">
                                                                    <button type="submit" class="dropdown-item text-danger">
                                                                        <i class="fa-solid fa-times-circle" style="font-size:14px;"></i>
                                                                        <span style="font-size:14px;">Withdraw Bid</span>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            @elseif($userBid && $isExpired)
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <span class="dropdown-item text-muted">
                                                                    <i class="fa-solid fa-clock" style="font-size:14px;"></i>
                                                                    <span style="font-size:14px;">Auction Ended</span>
                                                                </span>
                                                            </li>
                                                            @elseif($userBid && ($bidState === 'accepted' || $bidState === 'rejected'))
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <span class="dropdown-item text-muted">
                                                                    <i class="fa-solid fa-lock" style="font-size:14px;"></i>
                                                                    <span style="font-size:14px;">Bid {{ ucfirst($bidState) }}</span>
                                                                </span>
                                                            </li>
                                                            @endif
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(function() {
            $('.auction-type').on('change', function() {
                var val = $(this).val();
                window.location.href = '{{ route('tenant.biding.auctions.list') }}?type=' + val;
            });
        });
    </script>
@endpush
