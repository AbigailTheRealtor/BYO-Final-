@extends('layouts.main')
@push('styles')
@endpush
@section('content')
    <div class="mainDashboard">
        <div class="container">
            @include('layouts.partials.dashboard_user_section')
            <div class="dashboardContentDetails mt-3">
                <div class="card">
                    <div class="row">
                        @include('layouts.partials.sidenav')
                        <div class="rightCol col-sm-12 col-md-8 col-lg-8">
                            <div class="container mt-5 myAuctions">
                                <h1>Hire Seller's Agent Auctions</h1>
                                <!-- Section 1  -->
                                <select class="form-select mt-4 mb-3 w-25 auction-type">
                                    <option value="2" {{ $type == '2' ? 'selected' : '' }}>Live ({{ $liveCount }})
                                    </option>
                                    <option value="1" {{ $type == '1' ? 'selected' : '' }}>Pending Approval
                                        ({{ $pendingApprovalCount }})</option>
                                    <option value="3" {{ $type == '3' ? 'selected' : '' }}>Awarded ({{ $soldCount }})
                                    </option>
                                </select>
                                <!-- End  -->

                                <table class="table table-bordered data-table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>Address</th>
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
                                            @php
                                                // $string = mb_strimwidth($string, 0, 100);
                                                // $description = mb_strimwidth(@$auction->description, 0, 90, '...');
                                            @endphp
                                            <tr>
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td><a
                                                        href="{{ route('seller.agent.auction.detail', @$auction->id) }}">{{ @$auction->address }}</a>
                                                </td>
                                                <td>{{ @$auction->get->county }}</td>
                                                <td>{{ @$auction->get->city }}</td>
                                                <td>{{ @$auction->get->state }}</td>
                                                <td>{{ Carbon\Carbon::parse(@$auction->created_at)->format('M d, Y') }}
                                                </td>
                                                <td class="text-center">{{ @$auction->bids->count() }}</td>
                                                <td class="text-center">
                                                    <div class="dropdown">
                                                        <button class="btn btn-secondary dropdown-toggle btn-sm"
                                                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Action
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item"
                                                                    href="{{ route('seller.agent.auction.detail', @$auction->id) }}">
                                                                    <i class="fa-solid fa-eye" style="font-size:14px;"></i>
                                                                    <span style="font-size:14px;">View</span>
                                                                </a>
                                                            </li>
                                                            @if (!@$auction->is_approved)
                                                                <li>
                                                                    <a class="dropdown-item"
                                                                        href="{{ route('editSellerAgentHireAuction', @$auction->id) }}">
                                                                        <i class="fa-solid fa-pencil"
                                                                            style="font-size:14px;"></i>
                                                                        <span style="font-size:14px;">Edit</span>
                                                                    </a>
                                                                </li>
                                                            @endif
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

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
                window.location.href = '{{ route('hireSellerAgentHireAuctions') }}?type=' + val;
            });
        });
    </script>
@endpush
