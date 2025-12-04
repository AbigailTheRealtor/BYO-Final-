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
                                <h1>My Auctions</h1>
                                <!-- Section 1  -->
                                <select class="form-select mt-4 mb-3 w-25 auction-type">
                                    <option value="2" {{ $type == '2' ? 'selected' : '' }}>Live ({{ $liveCount }})
                                    </option>
                                    {{-- <option value="0" {{ ($type=="0")?"selected":"" }}>Pending ({{$pendingCount}})</option> --}}
                                    <option value="1" {{ $type == '1' ? 'selected' : '' }}>Pending Approval
                                        ({{ $pendingApprovalCount }})</option>
                                    {{-- <option value="4" {{ ($type=="4")?"selected":"" }}>Waiting Payment ({{$pendingPaymentCount}})</option> --}}
                                    <option value="3" {{ $type == '3' ? 'selected' : '' }}>Ended/ Sold
                                        ({{ $soldCount }})</option>
                                </select>
                                <!-- End  -->


                                <table class="table table-bordered data-table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>Property Type</th>
                                            <th>Counties</th>
                                            <th>Cities</th>
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
                                                        href="{{ route('buyer.criteria.view', @$auction->id) }}">{{ @$auction->get->property_type }}</a>
                                                </td>
                                                <td>
                                                    @if (gettype(@$auction->get->counties) == 'array')
                                                        @foreach (@$auction->get->counties as $item)
                                                            <span class="badge bg-secondary">{{ $item }}</span>
                                                        @endforeach
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (gettype(@$auction->get->cities) == 'array')
                                                        @foreach (@$auction->get->cities as $item)
                                                            <span class="badge bg-secondary">{{ $item }}</span>
                                                        @endforeach
                                                    @endif
                                                </td>
                                                <td>{{ @$auction->get->state }}</td>
                                                <td>{{ Carbon\Carbon::parse(@$auction->created_at)->format('M d, Y') }}
                                                </td>
                                                <td class="text-center">{{ @$auction->bids->count() }}</td>
                                                <td class="text-center">
                                                    @if (Carbon\Carbon::parse($auction->get->expiration_date) < Carbon\Carbon::today())
                                                        <div class="dropdown">
                                                            <button class="btn btn-primary dropdown-toggle btn-sm"
                                                            type="button" data-bs-toggle="dropdown"
                                                            aria-expanded="false" style=" color: #fff;
                                                            background-color: #007bff;
                                                            border-color: #007bff;">
                                                            Renew
                                                        </button>
                                                            <ul class="dropdown-menu" id="modal_dropdown">
                                                                <li>
                                                                    <a class="dropdown-item" id="href_remove"
                                                                        href="{{ url('/renew_buyer_criteria/' . $auction->id) }}">
                                                                        <i class="fa-solid fa-eye"
                                                                            style="font-size:14px;"></i>
                                                                        <span style="font-size:14px;">Renew</span>
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    @else
                                                        <div class="dropdown">
                                                            <button class="btn btn-secondary dropdown-toggle btn-sm"
                                                                type="button" data-bs-toggle="dropdown"
                                                                aria-expanded="false">
                                                                Action
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <a class="dropdown-item"
                                                                        href="{{ route('buyer.criteria.view', @$auction->id) }}">
                                                                        <i class="fa-solid fa-eye"
                                                                            style="font-size:14px;"></i>
                                                                        <span style="font-size:14px;">View</span>
                                                                    </a>
                                                                </li>
                                                                @if (!@$auction->is_approved)
                                                                    <li>
                                                                        <a class="dropdown-item"
                                                                            href="{{ route('buyer_agent.auction.edit', @$auction->id) }}">
                                                                            <i class="fa-solid fa-pencil"
                                                                                style="font-size:14px;"></i>
                                                                            <span style="font-size:14px;">Edit</span>
                                                                        </a>
                                                                    </li>
                                                                @endif
                                                                @if($auction->user_id == auth()->user()->id && $auction->bids->count() > 0)
                                                                    @if ($auction->display_bids == 0)
                                                                        <form action="{{ route('criteria.auction.bids.visibility', ['id' => $auction->id, 'vis' => 'show']) }}"  method="post">
                                                                        @csrf
                                                                        <li>
                                                                            <button class="dropdown-item" style="background-color: transparent; color: #000"><span style="font-size:14px;">Show Bids</span></button>
                                                                        </li>
                                                                        </form>
                                                                    @else
                                                                        <form action="{{ route('criteria.auction.bids.visibility', ['id' => $auction->id, 'vis' => 'hide']) }}" method="post">
                                                                        @csrf
                                                                        <li>
                                                                            <button class="dropdown-item" style="background-color: transparent; color: #000"><span style="font-size:14px;">Hide Bids</span></button>
                                                                        </li>
                                                                        </form>
                                                                    @endif
                                                                @endif
                                                                <li>
                                                                    <a class="dropdown-item"
                                                                        href="{{ route('manage.bot.questions', ['buyer-criteria', $auction->id]) }}">
                                                                        <i class="fa-solid fa-robot"
                                                                            style="font-size:14px;"></i>
                                                                        <span style="font-size:14px;">Manage Chat Bot
                                                                            Questions</span>
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    @endif
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
                window.location.href = '{{ route('buyer.criteria.auctions') }}?type=' + val;
            });
        });
    </script>
@endpush
