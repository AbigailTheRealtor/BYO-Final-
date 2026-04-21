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
                                    <h4 class="mt-2 mb-0">Hire Seller's Agent — My Bids</h4>
                                    <p class="page-subtitle mb-0">Listings where you've placed bids to represent Sellers.</p>
                                </div>
                                <hr class="mt-2 mb-3">

                                @if($auctions->isEmpty())
                                    <div class="text-center text-muted py-5">
                                        <i class="fa fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                                        <p class="fw-semibold mb-1">No bids placed yet.</p>
                                        <p class="small mb-0">Browse live Seller's Agent listings to place your first bid.</p>
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
                                            <th class="text-center">Bids</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($auctions as $auction)
                                            <tr>
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td><a href="{{ route('seller.agent.auction.detail', @$auction->id) }}">{{ @$auction->title }}</a></td>
                                                <td>{{ $auction->get->counties[0] ?? '' }}</td>
                                                <td>{{ $auction->get->cities[0] ?? '' }}</td>
                                                <td>{{ @$auction->get->state }}</td>
                                                <td>{{ Carbon\Carbon::parse(@$auction->created_at)->format('M d, Y') }}</td>
                                                <td class="text-center">{{ @$auction->bids->count() }}</td>
                                                <td class="text-center">
                                                    <div class="dropdown">
                                                        <button class="btn btn-secondary dropdown-toggle btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Action
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item" href="{{ route('seller.agent.auction.detail', @$auction->id) }}">
                                                                    <i class="fa-solid fa-eye" style="font-size:14px;"></i>
                                                                    <span style="font-size:14px;">View</span>
                                                                </a>
                                                            </li>
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
                window.location.href = '{{ route('seller.biding.auctions.list') }}?type=' + val;
            });
        });
    </script>
@endpush
