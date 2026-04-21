@extends('layouts.main')
@push('styles')
<style>
.agent-bid-page h4 { font-weight: 700; }
.agent-bid-page .page-subtitle { font-size: .85rem; color: #6c757d; }
.agent-bid-page .back-link { font-size: .8rem; color: #049399; text-decoration: none; }
.agent-bid-page .back-link:hover { text-decoration: underline; }
.no-data { text-align: center; padding: 30px; color: #666; }
.loading { display: none; text-align: center; padding: 20px; }
.loading.active { display: block; }
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
                                    <h4 class="mt-2 mb-0">Hire Landlord's Agent — My Bids</h4>
                                    <p class="page-subtitle mb-0">Listings where you've placed bids to represent Landlords.</p>
                                </div>
                                <hr class="mt-2 mb-3">

                                <!-- Status Filter -->
                                <select class="form-select mb-3 w-25" id="auctionTypeFilter">
                                    <option value="2" {{ $status == '2' ? 'selected' : '' }}>Live ({{ $liveCount }})</option>
                                    <option value="1" {{ $status == '1' ? 'selected' : '' }}>Bidding Lost ({{ $notWonCount }})</option>
                                    <option value="3" {{ $status == '3' ? 'selected' : '' }}>Awarded ({{ $soldCount }})</option>
                                </select>

                                <!-- Loading -->
                                <div class="loading" id="loadingSpinner">
                                    <div class="spinner-border text-primary"></div>
                                    <p class="mt-2">Loading listings...</p>
                                </div>

                                <!-- Table -->
                                <div class="table-responsive" id="auctionsTable">
                                    @if ($auctions->isEmpty())
                                        <div class="text-center text-muted py-5" id="emptyStateMessage">
                                            <i class="fa fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                                            <p class="fw-semibold mb-1">No listings found for this status.</p>
                                            <p class="small mb-0">Switch the filter above or browse live Landlord's Agent listings to place a bid.</p>
                                        </div>
                                    @else
                                    <table class="table table-bordered data-table" id="auctionsDataTable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Title</th>
                                                <th>County</th>
                                                <th>City</th>
                                                <th>State</th>
                                                <th>Created</th>
                                                <th>Bids</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $counter = 1; @endphp
                                            @forelse ($auctions as $auction)
                                                <tr>
                                                    <td>{{ $counter++ }}</td>
                                                    <td>
                                                        <a href="{{ route('landlord.agent.auction.view', $auction->id) }}">
                                                            {{ $auction->title ?? 'N/A' }}
                                                        </a>
                                                    </td>
                                                    <td>{{ $auction->get->counties[0] ?? 'N/A' }}</td>
                                                    <td>{{ $auction->get->cities[0] ?? 'N/A' }}</td>
                                                    <td>{{ $auction->get->state ?? 'N/A' }}</td>
                                                    <td>{{ \Carbon\Carbon::parse($auction->created_at)->format('M d, Y') }}</td>
                                                    <td>{{ $auction->bids_count ?? $auction->bids->count() }}</td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                                                Action
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <a class="dropdown-item" href="{{ route('landlord.agent.auction.view', $auction->id) }}">
                                                                        <i class="fa-solid fa-eye me-2"></i> View
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                            @endforelse
                                        </tbody>
                                    </table>
                                    @endif
                                </div>

                                @if ($auctions instanceof \Illuminate\Pagination\LengthAwarePaginator && $auctions->hasPages())
                                    <div class="mt-4" id="manualPagination">
                                        {{ $auctions->links() }}
                                    </div>
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
        $(document).ready(function() {
            var baseUrl = window.location.origin + window.location.pathname;
            var currentParams = new URLSearchParams(window.location.search);
            var typeParam = currentParams.get('type') || 'bidding';

            $('#auctionTypeFilter').on('change', function() {
                $('#loadingSpinner').addClass('active');
                $('#auctionsTable').hide();
                window.location.href = baseUrl + '?type=' + typeParam + '&status=' + $(this).val();
            });

            var hasAuctions = {{ $auctions->count() > 0 ? 'true' : 'false' }};
            var isPaginated = {{ $auctions instanceof \Illuminate\Pagination\LengthAwarePaginator ? 'true' : 'false' }};
            var hasPages = {{ $auctions instanceof \Illuminate\Pagination\LengthAwarePaginator && $auctions->hasPages() ? 'true' : 'false' }};

            if (hasAuctions && !isPaginated) {
                if ($.fn.DataTable.isDataTable('#auctionsDataTable')) {
                    $('#auctionsDataTable').DataTable().destroy();
                }
                $('#auctionsDataTable').DataTable({
                    pageLength: 10,
                    ordering: true,
                    searching: true,
                    responsive: true,
                });
            }
        });
    </script>
@endpush
