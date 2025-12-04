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
            /* FontAwesome Unicode */
            font-family: FontAwesome;
            font-size: var(--icon-size);
            /* color: #006e9f; */
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

        /* container for all of the switch elements
                                              - adjust "width" to fit the content accordingly
                                          */
        .switches-container {
            width: 16rem;
            position: relative;
            display: flex;
            padding: 0;
            position: relative;
            background: var(--switches-bg-color);
            line-height: 3rem;
            border-radius: 3rem;
            margin-left: auto;
            margin-right: auto;
        }

        /* input (radio) for toggling. hidden - use labels for clicking on */
        .switches-container input {
            visibility: hidden;
            position: absolute;
            top: 0;
        }

        /* labels for the input (radio) boxes - something to click on */
        .switches-container label {
            width: 50%;
            padding: 0;
            margin: 0;
            text-align: center;
            cursor: pointer;
            color: var(--switches-label-color);
        }

        /* switch highlighters wrapper (sliding left / right)
                                              - need wrapper to enable the even margins around the highlight box
                                          */
        .switch-wrapper {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 50%;
            padding: 0.15rem;
            z-index: 3;
            transition: transform .5s cubic-bezier(.77, 0, .175, 1);
            /* transition: transform 1s; */
        }

        /* switch box highlighter */
        .switch {
            border-radius: 3rem;
            background: var(--switch-bg-color);
            height: 100%;
        }

        /* switch box labels
                                              - default setup
                                              - toggle afterwards based on radio:checked status
                                          */
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

        /* slide the switch box from right to left */
        .switches-container input:nth-of-type(1):checked~.switch-wrapper {
            transform: translateX(0%);
        }

        /* slide the switch box from left to right */
        .switches-container input:nth-of-type(2):checked~.switch-wrapper {
            transform: translateX(100%);
        }

        /* toggle the switch box labels - first checkbox:checked - show first switch div */
        .switches-container input:nth-of-type(1):checked~.switch-wrapper .switch div:nth-of-type(1) {
            opacity: 1;
        }

        /* toggle the switch box labels - second checkbox:checked - show second switch div */
        .switches-container input:nth-of-type(2):checked~.switch-wrapper .switch div:nth-of-type(2) {
            opacity: 1;
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
                                <h1>Tenant's Agent Auctions Listing</h1>
                                <!-- Section 1  -->
                                <select class="form-select mt-4 mb-3 w-25 auction-type">
                                    <option value="2" {{ $type == '2' ? 'selected' : '' }}>Live ({{ $liveCount }})
                                    </option>
                                    <option value="1" {{ $type == '1' ? 'selected' : '' }}>Pending Approval
                                        ({{ $pendingApprovalCount }})
                                    </option>
                                    <option value="3" {{ $type == '3' ? 'selected' : '' }}>Awarded
                                        ({{ $soldCount }})</option>
                                </select>
                                <!-- End  -->

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
                                        <div>
                                            @foreach ($auctions as $auction)

                                                <tr>
                                                    <td class="text-center">{{ $loop->iteration }}</td>
                                                    <td><a
                                                            href="{{ route('tenant.agent.view.auction.view', @$auction->id) }}">{{ @$auction->title }}</a>
                                                    </td>
                                                    <td>{{ $auction->get->counties[0] }}</td>

                                                    <td> {{ $auction->get->cities[0] }}</td>
                                                    <td>{{ @$auction->get->state }}</td>
                                                    <td>{{ Carbon\Carbon::parse(@$auction->created_at)->format('M d, Y') }}
                                                    </td>
                                                    <td class="text-center">{{ @$auction->bids->count() }}</td>
                                                    <td class="text-center">
                                                        <div class="dropdown">
                                                            <button class="btn btn-secondary dropdown-toggle btn-sm"
                                                                type="button" data-bs-toggle="dropdown"
                                                                aria-expanded="false">
                                                                Action
                                                            </button>

                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <a class="dropdown-item"
                                                                        href="{{ route('tenant.agent.view.auction.view', @$auction->id) }}">
                                                                        <i class="fa-solid fa-eye"
                                                                            style="font-size:14px;"></i>
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
                window.location.href = '{{ route('tenant.agent.auctions.list') }}?type=' + val;
            });
        });
    </script>
@endpush
