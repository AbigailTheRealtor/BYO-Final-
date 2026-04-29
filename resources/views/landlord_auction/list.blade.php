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

        body {
            font-family: 'Lucida Sans', 'Lucida Sans Regular', 'Lucida Grande', 'Lucida Sans Unicode', Geneva, Verdana, sans-serif;
        }

        .switches-container {
            width: 16rem;
            position: relative;
            display: flex;
            padding: 0;
            background: var(--switches-bg-color);
            line-height: 3rem;
            border-radius: 3rem;
            margin-left: auto;
            margin-right: auto;
        }

        .switches-container input {
            visibility: hidden;
            position: absolute;
            top: 0;
        }

        .switches-container label {
            width: 50%;
            padding: 0;
            margin: 0;
            text-align: center;
            cursor: pointer;
            color: var(--switches-label-color);
        }

        .switch-wrapper {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 50%;
            padding: 0.15rem;
            z-index: 3;
            transition: transform .5s cubic-bezier(.77, 0, .175, 1);
        }

        .switch {
            border-radius: 3rem;
            background: var(--switch-bg-color);
            height: 100%;
        }

        .switch div {
            width: 100%;
            text-align: center;
            opacity: 0;
            display: block;
            color: var(--switch-text-color);
            transition: opacity .2s cubic-bezier(.77, 0, .175, 1) .125s;
            will-change: opacity;
            position: absolute;
            top: 0;
            left: 0;
        }

        .switches-container input:nth-of-type(1):checked~.switch-wrapper {
            transform: translateX(0%);
        }

        .switches-container input:nth-of-type(2):checked~.switch-wrapper {
            transform: translateX(100%);
        }

        .switches-container input:nth-of-type(1):checked~.switch-wrapper .switch div:nth-of-type(1) {
            opacity: 1;
        }

        .switches-container input:nth-of-type(2):checked~.switch-wrapper .switch div:nth-of-type(2) {
            opacity: 1;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .myAuctions h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }

        .auction-type {
            max-width: 300px;
        }

        .dropdown-menu {
            min-width: 120px;
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

        .auction-badge {
            font-size: 0.75rem;
            padding: 2px 8px;
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

                                <!-- Filter Section -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <select class="form-select mt-4 mb-3 w-25 auction-type" id="auctionTypeFilter">
                                            <option value="2" {{ request('status', '2') == '2' ? 'selected' : '' }}>Live ({{ $liveCount ?? 0 }})</option>
                                            <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Pending Approval ({{ $pendingApprovalCount ?? 0 }})</option>
                                            <option value="3" {{ request('status') == '3' ? 'selected' : '' }}>Awarded ({{ $soldCount ?? 0 }})</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- End Filter Section -->

                                <!-- Loading Spinner -->
                                <div class="loading" id="loadingSpinner">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading auctions...</p>
                                </div>

                                <!-- Table Section -->
                                <div class="table-responsive" id="auctionsTable">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center">#</th>
                                                <th>Title</th>
                                                <th>County</th>
                                                <th>City</th>
                                                <th>State</th>
                                                <th>Creation Date</th>
                                                <th class="text-center">Bids</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $counter = 1;
                                            @endphp
                                            @if(isset($auctions) && $auctions->count() > 0)
                                                @foreach ($auctions as $auction)
                                                <tr>
                                                    <td class="text-center">{{ $counter++ }}</td>
                                                    <td>
                                                        <a href="{{ route('landlord.agent.auction.view', $auction->id) }}">
                                                            {{ $auction->title ?? 'N/A' }}
                                                        </a>
                                                    </td>
                                                    <td>
                                                        @if(isset($auction->get->counties) && is_array($auction->get->counties) && count($auction->get->counties) > 0)
                                                            {{ $auction->get->counties[0] }}
                                                        @else
                                                            N/A
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if(isset($auction->get->cities) && is_array($auction->get->cities) && count($auction->get->cities) > 0)
                                                            {{ $auction->get->cities[0] }}
                                                        @else
                                                            N/A
                                                        @endif
                                                    </td>
                                                    <td>{{ $auction->get->state ?? 'N/A' }}</td>
                                                    <td>{{ Carbon\Carbon::parse($auction->created_at)->format('M d, Y') }}</td>
                                                    <td class="text-center">
                                                        @php
                                                            $bidCount = isset($auction->bids_count) ? $auction->bids_count : (isset($auction->bids) ? $auction->bids->count() : 0);
                                                        @endphp
                                                        {{ $bidCount }}
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="dropdown">
                                                            <button class="btn btn-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                Action
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <a class="dropdown-item" href="{{ route('landlord.agent.auction.view', $auction->id) }}">
                                                                        <i class="fa-solid fa-eye me-2"></i>
                                                                        <span>View</span>
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <i class="fa-solid fa-inbox fa-3x mb-3 text-muted"></i>
                                                        <p class="mb-0">No auctions found for this category.</p>
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                                <!-- End Table Section -->

                                <!-- Pagination -->
                                @if(isset($auctions) && $auctions instanceof \Illuminate\Pagination\LengthAwarePaginator && $auctions->total() > $auctions->perPage())
                                <div class="mt-4">
                                    {{ $auctions->appends(request()->except('page'))->links() }}
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
            // Store the current URL without query parameters
            var baseUrl = window.location.origin + window.location.pathname;
            var currentParams = new URLSearchParams(window.location.search);
            var typeParam = currentParams.get('type') || 'bidding';
            var statusParam = currentParams.get('status') || '2';

            $('#auctionTypeFilter').on('change', function() {
                var status = $(this).val();
                var loadingSpinner = $('#loadingSpinner');
                var auctionsTable = $('#auctionsTable');

                // Show loading spinner
                loadingSpinner.addClass('active');
                auctionsTable.hide();

                // Redirect to the correct URL with type and status parameters
                window.location.href = baseUrl + '?type=' + typeParam + '&status=' + status;
            });

            // Hide loading spinner once page is loaded
            $('#loadingSpinner').removeClass('active');
            $('#auctionsTable').show();

            // Simple table sorting and search functionality (if DataTables is not available)
            if (typeof $.fn.DataTable === 'undefined') {
                // Add basic search functionality
                $('#auctionsTable table').addClass('table-striped');

                // Add search box if needed
                var searchBox = '<div class="mb-3"><input type="text" class="form-control" id="tableSearch" placeholder="Search auctions..." style="max-width: 300px;"></div>';
                $('#auctionsTable').before(searchBox);

                $('#tableSearch').on('keyup', function() {
                    var value = $(this).val().toLowerCase();
                    $('#auctionsTable tbody tr').filter(function() {
                        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                    });
                });
            }
        });
    </script>
@endpush
