@extends('layouts.main')
@push('styles')
    <style>
        .modal {
            --bs-modal-width: 70%;
        }

        .modal-content {
            height: 100vh;
        }

        .services ul {
            --icon-size: 1em;
            --gutter: .5em;
            padding: 0 0 0 calc(var(--icon-size) + 2em);
        }

        .services ul li {
            padding-left: var(--gutter);
            color: #34465c;
        }

        .services ul li::marker {
            content: "\f101";
            font-family: FontAwesome;
            font-size: var(--icon-size);
            color: #11b7cf;
        }

        :root {
            --switches-bg-color: #169499;
            --switches-label-color: white;
            --switch-bg-color: white;
            --switch-text-color: #169499;
        }

        .switches-container {
            width: 16rem;
            display: flex;
            background: var(--switches-bg-color);
            border-radius: 3rem;
            margin: auto;
        }

        .switches-container input {
            visibility: hidden;
        }

        .switches-container label {
            width: 50%;
            text-align: center;
            cursor: pointer;
            color: var(--switches-label-color);
        }

        .switch-wrapper {
            position: absolute;
            width: 50%;
            transition: transform .5s;
        }

        .switch {
            background: var(--switch-bg-color);
            border-radius: 3rem;
            height: 100%;
        }

        .switch div {
            opacity: 0;
            position: absolute;
            width: 100%;
            text-align: center;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .myAuctions h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #666;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.active {
            display: block;
        }

        .dataTables_wrapper {
            padding: 0;
        }

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

                            <div class="container mt-5 myAuctions">

                                <h1>Landlord's Agent Auctions Listing</h1>

                                <!-- Filter -->
                                <select class="form-select mt-4 mb-3 w-25" id="auctionTypeFilter">
                                    <option value="2" {{ $status == '2' ? 'selected' : '' }}>Live ({{ $liveCount }})
                                    </option>
                                    <option value="1" {{ $status == '1' ? 'selected' : '' }}>Bidding Lost ({{ $notWonCount }})</option>

                                    <option value="3" {{ $status == '3' ? 'selected' : '' }}>Awarded
                                        ({{ $soldCount }})</option>
                                </select>

                                <!-- Loading -->
                                <div class="loading" id="loadingSpinner">
                                    <div class="spinner-border text-primary"></div>
                                    <p class="mt-2">Loading auctions...</p>
                                </div>

                                <!-- Table -->
                                <div class="table-responsive" id="auctionsTable">
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
                                                    <td>{{ \Carbon\Carbon::parse($auction->created_at)->format('M d, Y') }}
                                                    </td>
                                                    <td>{{ $auction->bids_count ?? $auction->bids->count() }}</td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button class="btn btn-secondary btn-sm dropdown-toggle"
                                                                data-bs-toggle="dropdown">
                                                                Action
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <a class=""
                                                                        href="{{ route('landlord.agent.auction.view', $auction->id) }}">
                                                                        <i class="fa-solid fa-eye me-2"></i> View
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>

                                            @empty
                                                <!-- FIXED: This row will be shown only when NOT using DataTables -->
                                            @endforelse
                                        </tbody>

                                    </table>

                                    <!-- Empty State (Only shown when no records exist and not using Laravel pagination) -->
                                    @if ($auctions->isEmpty())
                                        <div class="text-center text-muted py-4" id="emptyStateMessage">
                                            <i class="fas fa-inbox fa-2x"></i>
                                            <p class="mt-2 mb-0">No auctions found</p>
                                        </div>
                                    @endif
                                </div>

                                <!-- Laravel Pagination -->
                                @if ($auctions instanceof \Illuminate\Pagination\LengthAwarePaginator && $auctions->hasPages())
                                    <div class="mt-4" id="manualPagination">
                                        {{ $auctions->links() }}
                                    </div>
                                @endif

                            </div> <!-- end auctions -->
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

            /** -----------------------------
             * FIXED DATATABLE INITIALIZATION
             * ----------------------------- */

            // Check if we have any auction records
            var hasAuctions = {{ $auctions->count() > 0 ? 'true' : 'false' }};
            var isPaginated =
                {{ $auctions instanceof \Illuminate\Pagination\LengthAwarePaginator ? 'true' : 'false' }};
            var hasPages =
                {{ $auctions instanceof \Illuminate\Pagination\LengthAwarePaginator && $auctions->hasPages() ? 'true' : 'false' }};

            // Only initialize DataTables when we have records AND we're not using Laravel pagination
            if (hasAuctions && !isPaginated) {
                if ($.fn.DataTable.isDataTable('#auctionsDataTable')) {
                    $('#auctionsDataTable').DataTable().destroy();
                }

                $('#auctionsDataTable').DataTable({
                    pageLength: 10,
                    ordering: true,
                    searching: true,
                    responsive: true,
                    language: {
                        emptyTable: '<div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x"></i><p class="mt-2 mb-0">No auctions found</p></div>'
                    },
                    initComplete: function(settings, json) {
                        // Hide the empty state message since DataTables handles it
                        $('#emptyStateMessage').hide();
                    }
                });
            } else if (!hasAuctions && !isPaginated) {
                // If no records and not paginated, just hide the table and show empty state
                $('#auctionsDataTable').hide();
                $('#emptyStateMessage').show();
            } else if (isPaginated && !hasPages) {
                // If using pagination but no pages (empty), hide the pagination
                $('#manualPagination').hide();
            }

        });
    </script>
@endpush
