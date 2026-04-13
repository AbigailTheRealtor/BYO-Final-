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
                                <h1>Hire Tenant's Agent Auctions</h1>
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
                                            <th class="text-center">Counter Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <div>
                                            @foreach ($auctions as $auction)
                                                @php
                                                    // $string = mb_strimwidth($string, 0, 100);
                                                    // $description = mb_strimwidth(@$auction->description, 0, 90, '...');
                                                @endphp
                                                <tr>
                                                    <td class="text-center">{{ $loop->iteration }}</td>
                                                    <td><a
                                                            href="{{ route('tenant.agent.view.auction.view', @$auction->id) }}">{{ @$auction->title }}</a>
                                                    </td>
                                                    <td>{{ $auction->get->counties[0] ?? '' }}</td>

                                                    <td>{{ $auction->get->cities[0] ?? '' }}</td>
                                                    <td>{{ @$auction->get->state }}</td>
                                                    <td>{{ Carbon\Carbon::parse(@$auction->created_at)->format('M d, Y') }}
                                                    </td>
                                                    <td class="text-center">{{ @$auction->bids->count() }}</td>
                                                    <td class="text-center">
                                                        <a href="{{ route('tenant.agent.view.auction.view', @$auction->id) }}"
                                                           class="btn btn-sm d-block mb-1"
                                                           style="background:#049399;color:#fff;font-size:13px;">View Bids</a>
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
                                                                        <span style="font-size:14px;">View Listing</span>
                                                                    </a>
                                                                </li>

                                                                @if (!@$auction->is_approved)
                                                                    <li>
                                                                        <a class="dropdown-item"
                                                                            href="{{ route('hire.agent.auction.edit', ['auctionId' => $auction->id, 'user_type' => $auction->get->user_type]) }}">
                                                                            <i class="fa-solid fa-pencil"
                                                                                style="font-size:14px;"></i>
                                                                            <span style="font-size:14px;">Edit Listing</span>
                                                                        </a>
                                                                    </li>
                                                                @endif
                                                                <li>
                                                                    @php
                                                                        $counter = App\Models\TenantCounterTerm::where(
                                                                            'tenant_agent_auction_id',
                                                                            @$auction->id,
                                                                        )->first();
                                                                    @endphp
                                                                    @if (isset($counter))
                                                                        <a class="dropdown-item"
                                                                            href="{{ route('tenant.edit-counter-terms', $auction->id) }}">
                                                                            <i class="fa-solid fa-edit"
                                                                                style="font-size:14px;"></i>
                                                                            <span style="font-size:14px;"> Edit Terms
                                                                            </span>
                                                                        </a>
                                                                        <a class="dropdown-item" data-bs-toggle="modal"
                                                                            data-bs-target="#modal-{{ $auction->id }}"
                                                                            href="#">
                                                                            <i class="fa-solid fa-eye"
                                                                                style="font-size:14px;"></i>
                                                                            <span style="font-size:14px;"> View Terms</span>
                                                                        </a>
                                                                @endif
                                                                </li>
                                                                
                                                            </ul>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if (isset($counter))
                                                            @if (isset($counter) && $counter->status == '1')
                                                                <span class="badge bg-success p-2">Active</span>
                                                            @else
                                                                <span class="badge bg-danger p-2">InActive</span>
                                                            @endif
                                                        @else
                                                            <span class="badge bg-info p-2">No Terms</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                {{-- Modal --}}
                                                <div class="modal fade" id="modal-{{ @$auction->id }}" tabindex="-1"
                                                    aria-labelledby="modalLabel-{{ @$auction->id }}" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title"
                                                                    id="modalLabel-tenant-broker-{{ @$auction->id }}">
                                                                    <i class="fa-solid fa-file-signature me-2"></i>Seller's Countered Terms
                                                                </h5>
                                                                <!-- Changed data-dismiss to data-bs-dismiss and updated close button -->
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal" aria-label="Close">&times;</button>
                                                            </div>

                                                            <div class="modal-body">
                                                                @php
                                                                    $c = @$counter->get ?? (object) [];
                                                                    $property_type = @$counter->property_type;
                                                                @endphp

                                                                <!-- Main Broker Compensation Section -->
                                                                <div class="mb-4">
                                                                    <h6 class="mb-3"
                                                                        style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                        <i
                                                                            class="fa-solid fa-hand-holding-dollar me-2"></i>Broker
                                                                        Compensation Terms
                                                                    </h6>

                                                                    <!-- Commission Structure -->
                                                                    @if (!empty($c->commission_structure))
                                                                        <div class="mb-3">
                                                                            <div class="fw-semibold"
                                                                                style="color: #049399;">Commission
                                                                                Structure:
                                                                            </div>
                                                                            <div class="text-muted">
                                                                                {{ $c->commission_structure }}</div>
                                                                        </div>
                                                                    @endif

                                                                    <!-- Tenant's Broker Lease Fee -->
                                                                    @if (!empty($c->lease_fee_type))
                                                                        <div class="mb-3">
                                                                            <div class="fw-semibold"
                                                                                style="color: #049399;">Tenant's Broker
                                                                                Lease Fee:
                                                                            </div>
                                                                            <div class="text-muted">
                                                                                {{ $c->lease_fee_type }}</div>
                                                                        </div>

                                                                        <!-- Lease Fee Details -->
                                                                        @if ($c->lease_fee_type === 'Flat Fee' && !empty($c->lease_fee_flat))
                                                                            <div class="mb-2">
                                                                                <div class="fw-semibold"
                                                                                    style="color: #049399;">Flat Fee Amount:
                                                                                </div>
                                                                                <div class="text-muted">
                                                                                    @if (!empty($c->lease_fee_flat_type) && $c->lease_fee_flat_type === '%')
                                                                                        {{ $c->lease_fee_flat }}%
                                                                                    @else
                                                                                        ${{ $c->lease_fee_flat }}
                                                                                    @endif
                                                                                </div>
                                                                            </div>
                                                                        @endif

                                                                        @if ($c->lease_fee_type === 'Percentage of the Gross Lease Value' && !empty($c->lease_fee_percentage))
                                                                            <div class="mb-2">
                                                                                <div class="fw-semibold"
                                                                                    style="color: #049399;">Percentage of
                                                                                    Gross Lease Value:
                                                                                </div>
                                                                                <div class="text-muted">
                                                                                    {{ $c->lease_fee_percentage }}%
                                                                                </div>
                                                                            </div>
                                                                        @endif

                                                                        @if ($c->lease_fee_type === 'Percentage of Monthly Rent' && !empty($c->lease_fee_percentage_monthly_rent))
                                                                            <div class="mb-2">
                                                                                <div class="fw-semibold"
                                                                                    style="color: #049399;">Percentage of
                                                                                    Monthly Rent:
                                                                                </div>
                                                                                <div class="text-muted">
                                                                                    {{ $c->lease_fee_percentage_monthly_rent }}%
                                                                                </div>
                                                                            </div>
                                                                        @endif

                                                                        @if ($c->lease_fee_type === 'Flat Fee + Percentage of the Gross Lease Value')
                                                                            @if (!empty($c->lease_fee_flat_combo))
                                                                                <div class="mb-2">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Flat Fee
                                                                                        Amount:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        ${{ $c->lease_fee_flat_combo }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                            @if (!empty($c->lease_fee_percentage_combo))
                                                                                <div class="mb-2">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Percentage
                                                                                        of Gross Lease Value:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        {{ $c->lease_fee_percentage_combo }}%
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                        @endif

                                                                        @if ($c->lease_fee_type === 'Percentage of the Net Aggregate Rent' && !empty($c->lease_fee_percentage_net))
                                                                            <div class="mb-2">
                                                                                <div class="fw-semibold"
                                                                                    style="color: #049399;">Percentage of
                                                                                    Net Aggregate Rent:
                                                                                </div>
                                                                                <div class="text-muted">
                                                                                    {{ $c->lease_fee_percentage_net }}%
                                                                                </div>
                                                                            </div>
                                                                        @endif

                                                                        @if ($c->lease_fee_type === 'Flat Fee + Percentage of the Net Aggregate Rent')
                                                                            @if (!empty($c->lease_fee_flat_combo_net))
                                                                                <div class="mb-2">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Flat Fee
                                                                                        Amount:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        ${{$c->lease_fee_flat_combo_net }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                            @if (!empty($c->lease_fee_percentage_combo_net))
                                                                                <div class="mb-2">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Percentage
                                                                                        of Net Aggregate Rent:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        {{ $c->lease_fee_percentage_combo_net }}%
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                        @endif

                                                                        @if ($c->lease_fee_type === 'other' && !empty($c->lease_fee_other))
                                                                            <div class="mb-2">
                                                                                <div class="fw-semibold"
                                                                                    style="color: #049399;">Other Lease Fee
                                                                                    Structure:
                                                                                </div>
                                                                                <div class="text-muted">
                                                                                    {{ $c->lease_fee_other }}</div>
                                                                            </div>
                                                                        @endif
                                                                        {{-- <hr class="my-4"> --}}
                                                                    @endif


                                                                    <!-- Purchase Fee Section -->
                                                                    @if (!empty($c->interested_purchase_fee_type) && $c->interested_purchase_fee_type === 'Yes')
                                                                        <div class="mb-4">
                                                                            <h6 class="mb-3"
                                                                                style="color: #049399; font-weight: 600;">
                                                                                Purchase Fee Details:
                                                                            </h6>

                                                                            @if (!empty($c->purchase_fee_type))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Purchase
                                                                                        Fee Type:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        {{ $c->purchase_fee_type }}</div>
                                                                                </div>
                                                                            @endif

                                                                            @if ($c->purchase_fee_type === 'Flat Fee' && !empty($c->purchase_fee_flat))
                                                                                <div class="mb-2">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Flat Fee
                                                                                        Amount:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        @if (!empty($c->purchase_fee_flat_type) && $c->purchase_fee_flat_type === '%')
                                                                                            {{ $c->purchase_fee_flat }}%
                                                                                        @else
                                                                                            ${{ $c->purchase_fee_flat }}
                                                                                        @endif
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            @if ($c->purchase_fee_type === 'Percentage of the Total Purchase Price' && !empty($c->purchase_fee_percentage))
                                                                                <div class="mb-2">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Percentage
                                                                                        of Total Purchase Price:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        {{ $c->purchase_fee_percentage }}%
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            @if ($c->purchase_fee_type === 'Percentage of the Total Purchase Price + Flat Fee')
                                                                                @if (!empty($c->purchase_fee_percentage_combo))
                                                                                    <div class="mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Percentage of Total Purchase
                                                                                            Price:
                                                                                        </div>
                                                                                        <div class="text-muted">
                                                                                            {{ $c->purchase_fee_percentage_combo }}%
                                                                                        </div>
                                                                                    </div>
                                                                                @endif
                                                                                @if (!empty($c->purchase_fee_flat_combo))
                                                                                    <div class="mb-2">
                                                                                        <div class="fw-semibold"
                                                                                            style="color: #049399;">
                                                                                            Additional Flat Fee:
                                                                                        </div>
                                                                                        <div class="text-muted">
                                                                                            ${{ $c->purchase_fee_flat_combo }}
                                                                                        </div>
                                                                                    </div>
                                                                                @endif
                                                                            @endif

                                                                            @if ($c->purchase_fee_type === 'other' && !empty($c->purchase_fee_other))
                                                                                <div class="mb-2">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Other
                                                                                        Purchase Fee Structure:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        {{ $c->purchase_fee_other }}</div>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                        {{-- <hr class="my-4"> --}}
                                                                    @endif

                                                                    <!-- Lease-Option Agreement -->
                                                                    @if (!empty($c->interested_lease_option_agreement) && $c->interested_lease_option_agreement === 'Yes')
                                                                        <div class="mb-4">
                                                                            <h6 class="mb-3"
                                                                                style="color: #049399; font-weight: 600;">
                                                                                Lease-Option Agreement Details:
                                                                            </h6>

                                                                            @if (!empty($c->lease_value))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Lease
                                                                                        Option Compensation:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        @if ($c->lease_type === 'percent')
                                                                                            {{ $c->lease_value }}% of
                                                                                            option consideration
                                                                                        @else
                                                                                            ${{ $c->lease_value }}
                                                                                            flat fee
                                                                                        @endif
                                                                                    </div>
                                                                                </div>
                                                                            @endif

                                                                            @if (!empty($c->purchase_value))
                                                                                <div class="mb-3">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Purchase
                                                                                        Option Compensation:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        @if ($c->purchase_type === 'percent')
                                                                                            {{ $c->purchase_value }}% of
                                                                                            total purchase price
                                                                                        @else
                                                                                            ${{ $c->purchase_value }}
                                                                                            flat fee
                                                                                        @endif
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                        <hr class="my-4">
                                                                    @endif

                                                                    <!-- Protection Period -->
                                                                    @if (!empty($c->protection_period))
                                                                        <div class="mb-3">
                                                                            <div class="fw-semibold"
                                                                                style="color: #049399;">Protection Period
                                                                                Timeframe:
                                                                            </div>
                                                                            <div class="text-muted">
                                                                                {{ $c->protection_period }} days
                                                                            </div>
                                                                        </div>
                                                                    @endif

                                                                    <!-- Early Termination Fee -->
                                                                    @if (!empty($c->early_termination_fee_option))
                                                                        <div class="mb-3">
                                                                            <div class="fw-semibold"
                                                                                style="color: #049399;">Early Termination
                                                                                Fee:
                                                                            </div>
                                                                            <div class="text-muted">
                                                                                {{ $c->early_termination_fee_option }}
                                                                            </div>
                                                                        </div>

                                                                        @if ($c->early_termination_fee_option === 'Yes' && !empty($c->early_termination_fee_amount))
                                                                            <div class="mb-2">
                                                                                <div class="fw-semibold"
                                                                                    style="color: #049399;">Termination Fee
                                                                                    Amount:
                                                                                </div>
                                                                                <div class="text-muted">
                                                                                    ${{ $c->early_termination_fee_amount }}
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                    @endif

                                                                    <!-- Retainer Fee -->
                                                                    @if (!empty($c->retainer_fee_option))
                                                                        <div class="mb-3">
                                                                            <div class="fw-semibold"
                                                                                style="color: #049399;">Retainer Fee:
                                                                            </div>
                                                                            <div class="text-muted">
                                                                                {{ $c->retainer_fee_option }}</div>
                                                                        </div>

                                                                        @if ($c->retainer_fee_option === 'Yes')
                                                                            @if (!empty($c->retainer_fee_amount))
                                                                                <div class="mb-2">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Retainer
                                                                                        Fee Amount:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        ${{ $c->retainer_fee_amount }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                            @if (!empty($c->retainer_fee_application))
                                                                                <div class="mb-2">
                                                                                    <div class="fw-semibold"
                                                                                        style="color: #049399;">Retainer
                                                                                        Fee Application:
                                                                                    </div>
                                                                                    <div class="text-muted">
                                                                                        {{ $c->retainer_fee_application === 'applied' ? 'Applied toward final compensation' : 'Charged in addition to final compensation' }}
                                                                                    </div>
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                    @endif

                                                                    <!-- Agency Agreement Timeframe -->
                                                                    @if (!empty($c->agency_agreement_timeframe))
                                                                        <div class="mb-3">
                                                                            <div class="fw-semibold"
                                                                                style="color: #049399;">Tenant Agency
                                                                                Agreement Timeframe:
                                                                            </div>
                                                                            <div class="text-muted">
                                                                                @if ($c->agency_agreement_timeframe === 'Other' && !empty($c->agency_agreement_custom))
                                                                                    {{ $c->agency_agreement_custom }}
                                                                                @else
                                                                                    {{ $c->agency_agreement_timeframe }}
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                                <!-- Brokerage Relationship -->
                                                                @if (!empty($c->brokerage_relationship))
                                                                    <div class="mb-4">
                                                                        <h6 class="mb-3"
                                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                            <i
                                                                                class="fa-solid fa-handshake me-2"></i>Brokerage
                                                                            Relationship
                                                                        </h6>
                                                                        <div class="mb-3">
                                                                            <div class="fw-semibold"
                                                                                style="color: #049399;">Acceptable
                                                                                Brokerage Relationship:
                                                                            </div>
                                                                            <div class="text-muted">
                                                                                {{ $c->brokerage_relationship }}</div>
                                                                        </div>
                                                                    </div>
                                                                @endif

                                                                <!-- Additional Terms -->
                                                                @if (!empty($c->additional_details_broker))
                                                                    <div class="mb-4">
                                                                        <h6 class="mb-3"
                                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                            <i
                                                                                class="fa-regular fa-note-sticky me-2"></i>Additional
                                                                            Terms
                                                                        </h6>
                                                                        <div class="mb-3">
                                                                            <div class="text-muted"
                                                                                style="font-style: italic;">
                                                                                {{ $c->additional_details_broker }}</div>
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                                <!-- Services Section -->
                                                                @php
                                                                    $counter_services = is_string(@$c->services)
                                                                        ? json_decode(@$c->services, true)
                                                                        : @$c->services;
                                                                    $counter_other_services = is_string(
                                                                        @$c->other_services,
                                                                    )
                                                                        ? json_decode(@$c->other_services, true)
                                                                        : @$c->other_services;
                                                                @endphp

                                                                @if (!empty($counter_services) && is_array($counter_services))
                                                                    <div class="mb-4">
                                                                        <h6 class="mb-3"
                                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                            <i
                                                                                class="fa-solid fa-list-check me-2"></i>Services
                                                                            Offered
                                                                        </h6>
                                                                        <div class="text-muted small mb-2">Services the
                                                                            Tenant Requests from Their Agent
                                                                        </div>
                                                                        <div class="d-flex flex-wrap gap-2">
                                                                            @foreach ($counter_services as $service)
                                                                                @if ($service == 'Other')
                                                                                    @continue
                                                                                @endif
                                                                                <span
                                                                                    class="badge bg-light text-dark border"
                                                                                    style="padding: 8px 12px; font-size: 14px; max-width: 200px; word-wrap: break-word; white-space: normal; line-height: 1.4;">
                                                                                    <i
                                                                                        class="fa-regular fa-square-check me-2 text-success"></i>{{ $service }}
                                                                                </span>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @endif

                                                                <!-- Other Services Section -->
                                                                @if (!empty($counter_other_services) && is_array($counter_other_services))
                                                                    <div class="mb-4">
                                                                        <h6 class="mb-3"
                                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                            <i
                                                                                class="fa-solid fa-plus-circle me-2"></i>Other
                                                                            Services
                                                                        </h6>
                                                                        <div class="text-muted small mb-2">Other Services
                                                                            the Tenant Requests
                                                                        </div>
                                                                        <div class="d-flex flex-wrap gap-2">
                                                                            @foreach ($counter_other_services as $other_service)
                                                                                @if ($other_service == 'Other')
                                                                                    @continue
                                                                                @endif
                                                                                <span
                                                                                    class="badge bg-light text-dark border"
                                                                                    style="padding: 8px 12px; font-size: 14px; max-width: 200px; word-wrap: break-word; white-space: normal; line-height: 1.4;">
                                                                                    <i
                                                                                        class="fa-regular fa-square-check me-2 text-primary"></i>{{ $other_service }}
                                                                                </span>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                                <!-- Additional Details -->
                                                                @if (!empty($c->additional_details))
                                                                    <div class="mb-4">
                                                                        <h6 class="mb-3"
                                                                            style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                            <i
                                                                                class="fa-regular fa-file-alt me-2"></i>Additional
                                                                            Details
                                                                        </h6>
                                                                        <div class="mb-3">
                                                                            <div class="text-muted"
                                                                                style="font-style: italic;">
                                                                                {{ $c->additional_details }}</div>
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            <div class="modal-footer">
                                                                <!-- Changed data-dismiss to data-bs-dismiss -->
                                                                <button type="button" class="btn btn-outline-secondary"
                                                                    data-bs-dismiss="modal">
                                                                    <i class="fa-solid fa-xmark me-1"></i>Close
                                                                </button>
                                                                <a href="{{ route('tenant.edit-counter-terms', $auction->id) }}"
                                                                    class="btn btn-primary">
                                                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                                                    Terms
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- End Modal --}}
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
