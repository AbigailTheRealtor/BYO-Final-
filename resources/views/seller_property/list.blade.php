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
                        <div class="rightCol col-sm-12 col-md-9 col-lg-9">
                            <div class="container mt-5 myAuctions">
                                <h1>My Auctions</h1>
                                <!-- Section 1  -->
                                <select class="form-select mt-4 mb-3 w-25 auction-type">
                                    <option value="2" {{ $type == '2' ? 'selected' : '' }}>Live ({{ $liveCount }})
                                    </option>
                                    <option value="0" {{ $type == '0' ? 'selected' : '' }}>Pending ({{ $pendingCount }})
                                    </option>
                                    <option value="1" {{ $type == '1' ? 'selected' : '' }}>Pending Approval
                                        ({{ $pendingApprovalCount }})</option>
                                    <option value="4" {{ $type == '4' ? 'selected' : '' }}>Waiting Payment
                                        ({{ $pendingPaymentCount }})</option>
                                    <option value="3" {{ $type == '3' ? 'selected' : '' }}>Ended/ Sold
                                        ({{ $soldCount }})</option>
                                </select>
                                <!-- End  -->

                                <table class="table table-bordered data-table">
                                    <thead>
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th>Photo</th>
                                            <th>Address</th>
                                            <th>Description</th>
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
                                                <td>
                                                    @php
                                                        $photo = url('auction/images/noimage.png');
                                                        if (gettype(@$auction->get->photos) == 'array') {
                                                            $photos = @$auction->get->photos;
                                                            $photo = url(current($photos));
                                                        }
                                                    @endphp
                                                    <div class="text-center image" style="width:100px; height:100px;">
                                                        <img src="{{ $photo }}" class="img-fluid rounded-start"
                                                            style="width:100%; height:100%; object-fit:cover;"
                                                            alt="...">
                                                    </div>
                                                </td>
                                                <td><a
                                                        href="{{ route('view-pl', @$auction->id) }}">{{ @$auction->address }}</a>
                                                </td>
                                                <td>{{ @$auction->description }}</td>
                                                <td>{{ Carbon\Carbon::parse(@$auction->created_at)->format('M d, Y') }}
                                                </td>
                                                <td class="text-center">{{ @$auction->bids->count() }}</td>
                                                <td class="text-center">
                                                    @if (Carbon\Carbon::parse($auction->expiration_date) < Carbon\Carbon::today())
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
                                                                    <a class="dropdown-item" id="href_remove" href="{{ url('/renew_property_sale/'.$auction->id) }}">
                                                                        <i class="fa-solid fa-eye" style="font-size:14px;"></i>
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
                                                                        href="{{ route('view-pl', @$auction->id) }}">
                                                                        <i class="fa-solid fa-eye"
                                                                            style="font-size:14px;"></i>
                                                                        <span style="font-size:14px;">View</span>
                                                                    </a>
                                                                </li>
                                                                @if (!@$auction->is_approved)
                                                                    <li>
                                                                        <a class="dropdown-item"
                                                                            href="{{ route('edit-seller-property-listing', @$auction->id) }}">
                                                                            <i class="fa-solid fa-pencil"
                                                                                style="font-size:14px;"></i>
                                                                            <span style="font-size:14px;">Edit</span>
                                                                        </a>
                                                                    </li>
                                                                @endif
                                                                <li>
                                                                    <a class="dropdown-item"
                                                                        href="{{ route('manage.bot.questions', ['seller-property', $auction->id]) }}">
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
                window.location.href = '{{ route('myAuctions') }}?type=' + val;
            });

        });

    </script>
@endpush
