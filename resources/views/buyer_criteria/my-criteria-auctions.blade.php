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

                                @forelse ($auctions as $pa)
                                    @php
                                        // $string = mb_strimwidth($string, 0, 100);
                                        $description = mb_strimwidth($pa->description, 0, 90, '...');
                                    @endphp
                                    <!-- Section 2  -->
                                    <div class="card mb-3 p-3">
                                        <div class="row g-0">
                                            <div class="col-md-12">
                                                <div class="card-body">
                                                    <a href="{{ route('buyer.criteria.view', $pa->id) }}"
                                                        class="text-dark fs-4 fw-bold ">{{ $pa->title }}</a>
                                                    <p class="card-text">{{$description}}</p>
                                                    <table class="table table-bordered">
                                                        <tr>
                                                            <th class="text-white bg-secondary fw-normal">Auction Type:</th>
                                                            <td>{{ $pa->auction_type }}</td>
                                                            <th class="text-white bg-secondary fw-normal">Auction Length:
                                                            </th>
                                                            <td>
                                                                @if ($pa->auction_length > 0)
                                                                    {{ $pa->auction_length }}
                                                                    {{ $pa->auction_length > 1 ? 'days' : 'day' }}
                                                                @else
                                                                    "No Time Limit"
                                                                @endif
                                                            </td>
                                                            <th class="text-white bg-secondary fw-normal">Maximum Price:
                                                            </th>
                                                            <td>${{ number_format($pa->max_price, 0, '.', '') }}</td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between buttons flex-wrap">
                                        @if ($type == '2')
                                            <button data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                                data-bs-trigger="hover focus" data-bs-placement="top"
                                                data-bs-content="26 views"><svg xmlns="http://www.w3.org/2000/svg"
                                                    class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                                    </path>
                                                </svg> Live</button>
                                        @endif
                                        @if ($type != '4' && $type != '3')
                                            <button>
                                                <a href="{{ route('buyer_agent.auction.edit', $pa->id) }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                        </path>
                                                    </svg>
                                                    Edit Page
                                                </a>
                                            </button>
                                        @endif

                                        <button data-bs-toggle="modal" data-bs-target="#exampleModal_{{ $pa->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z">
                                                </path>
                                            </svg>
                                            Statistics
                                        </button>
                                        <button data-bs-toggle="modal" data-bs-target="#updateModal_{{ $pa->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z">
                                                </path>
                                            </svg>
                                            Upgrades
                                        </button>
                                    </div>
                                    <hr>
                                    <!-- End  -->



                                    <!-- Statistics  Chart modal  -->
                                    <div class="modal fade" id="exampleModal_{{ $pa->id }}" tabindex="-1"
                                        aria-labelledby="exampleModal_{{ $pa->id }}Label" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <div>
                                                        <h1 class="modal-title fs-5"
                                                            id="exampleModal_{{ $pa->id }}Label">{{ $pa->address }}
                                                        </h1>
                                                        <p class="opacity-50">Page views for the last 14 days. </p>
                                                    </div>
                                                </div>
                                                <div class="modal-body">
                                                    <div>
                                                        <canvas id="myChart"></canvas>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Update modal  -->
                                    <div class="modal fade" id="updateModal_{{ $pa->id }}" tabindex="-1"
                                        aria-labelledby="updateModal_{{ $pa->id }}Label" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content upgradeModal position-relative">

                                                <div class="image">
                                                    <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
                                                        class="rounded-circle position-absolute" alt="">
                                                    <!-- Inner Curved Div -->
                                                    <div class="curved"></div>
                                                    <div class="card-body bg-white">
                                                        <h5 class="text-700 mb-4"><a
                                                                href="https://bidyouroffer.com/listing/no-timer-6/"
                                                                target="_blank" class="text-dark">{{ $pa->address }}</a>
                                                        </h5>
                                                        <div class="alert alert-success small"><i class="fa fa-check"></i>
                                                            Your auction is fully
                                                            updated.</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                @endforelse





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
