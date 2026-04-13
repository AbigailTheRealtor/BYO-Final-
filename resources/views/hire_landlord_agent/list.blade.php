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
                        <div class="rightCol col-sm-12 col-md-8 col-lg-8">
                            <div class="container mt-5 myAuctions">
                                <h1>{{ $title }}</h1>
                                <!-- Section 1  -->
                                <select class="form-select mt-4 mb-3 w-25 auction-type">
                                    <option value="2" {{ $type == '2' ? 'selected' : '' }}>Live ({{ $liveCount }})
                                    </option>
                                    <option value="1" {{ $type == '1' ? 'selected' : '' }}>Pending Approval
                                        ({{ $pendingApprovalCount }})</option>
                                    <option value="3" {{ $type == '3' ? 'selected' : '' }}>Awarded
                                        ({{ $soldCount }})
                                    </option>
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
                                        @foreach ($auctions as $auction)
                                            <tr>
                                                <td class="text-center">{{ $loop->iteration }}</td>
                                                <td><a
                                                        href="{{ route('landlord.agent.auction.view', @$auction->id) }}">{{ @$auction->title }}</a>
                                                </td>
                                                <td>{{ $auction->get->counties[0] ?? '' }}</td>

                                                <td> {{ $auction->get->cities[0] ?? '' }}</td>
                                                <td>{{ @$auction->get->state ?? '' }}</td>
                                                <td>{{ Carbon\Carbon::parse(@$auction->created_at)->format('M d, Y') }}
                                                </td>
                                                <td class="text-center">{{ @$auction->bids->count() }}</td>
                                                <td class="text-center">
                                                    <a href="{{ route('landlord.agent.auction.view', @$auction->id) }}"
                                                       class="btn btn-sm d-block mb-1"
                                                       style="background:#049399;color:#fff;font-size:13px;">View Bids</a>
                                                    <div class="dropdown">
                                                        <button class="btn btn-secondary dropdown-toggle btn-sm"
                                                            type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Action
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li>
                                                                <a class="dropdown-item"
                                                                    href="{{ route('landlord.agent.auction.view', @$auction->id) }}">
                                                                    <i class="fa-solid fa-eye" style="font-size:14px;"></i>
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
                                                                    $counter = App\Models\LandlordCounterTerm::where(
                                                                        'landlord_agent_auction_id',
                                                                        @$auction->id,
                                                                    )->first();
                                                                @endphp

                                                                @if (isset($counter))
                                                                    <a class="dropdown-item"
                                                                        href="{{ route('landlord.edit-counter-terms', $auction->id) }}">
                                                                        <i class="fa-solid fa-edit"
                                                                            style="font-size:14px;"></i>
                                                                        <span style="font-size:14px;">Countered Terms <span
                                                                                class="badge p-2"
                                                                                style="background: #049399">1</span></span>
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
                                                                <i class="fa-solid fa-file-signature me-2"></i>Broker
                                                                Compensation & Agency Agreement Terms
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                aria-label="Close">&times;</button>
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

                                                                <!-- Residential Property - Landlord's Broker Lease Fee -->
                                                                @if ($property_type === 'Residential Property' && !empty($c->purchase_fee_type))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Landlord's Broker Lease Fee:</div>
                                                                        <div class="text-muted">{{ $c->purchase_fee_type }}
                                                                        </div>

                                                                        @if ($c->purchase_fee_type === 'Flat Fee' && !empty($c->purchase_fee_flat))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">${{ $c->purchase_fee_flat }}</span>
                                                                            </div>
                                                                        @elseif($c->purchase_fee_type === 'Percentage of the Rent Due Each Rental Period' && !empty($c->purchase_fee_rental_period))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">{{ $c->purchase_fee_rental_period }}%</span>
                                                                            </div>
                                                                        @elseif($c->purchase_fee_type === 'Percentage of the Gross Lease Value' && !empty($c->purchase_fee_percentage_combo))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">{{ $c->purchase_fee_percentage_combo }}%</span>
                                                                            </div>
                                                                        @elseif($c->purchase_fee_type === 'Percentage of the First Month\'s Rent' && !empty($c->purchase_fee_flat_combo))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">{{ $c->purchase_fee_flat_combo }}%</span>
                                                                            </div>
                                                                        @elseif($c->purchase_fee_type === 'other' && !empty($c->purchase_fee_other))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">{{ $c->purchase_fee_other }}</span>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @endif

                                                                <!-- Commercial Property - Landlord's Broker Lease Fee -->
                                                                @if ($property_type === 'Commercial Property' && !empty($c->purchase_fee_type))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Landlord's Broker Lease Fee:</div>
                                                                        <div class="text-muted">
                                                                            {{ $c->purchase_fee_type }}</div>

                                                                        @if ($c->purchase_fee_type === 'Percentage of the Net Aggregate Rent' && !empty($c->purchase_fee_net_aggregate))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">{{ $c->purchase_fee_net_aggregate }}%</span>
                                                                            </div>
                                                                        @elseif ($c->purchase_fee_type === 'Percentage of the Gross Rent' && !empty($c->purchase_fee_gross_rent))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">{{ $c->purchase_fee_gross_rent }}%</span>
                                                                            </div>
                                                                            @if (!empty($c->sales_tax_option_gross))
                                                                                <div class="mt-1">
                                                                                    <span class="text-muted">Sales Tax:
                                                                                        {{ ucfirst($c->sales_tax_option_gross) }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @elseif ($c->purchase_fee_type === 'Percentage of Month\'s Rent' && !empty($c->purchase_fee_monthly_percentage))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">{{ $c->purchase_fee_monthly_percentage }}%
                                                                                    of month's rent</span>
                                                                            </div>
                                                                            @if (!empty($c->purchase_fee_months))
                                                                                <div class="mt-1">
                                                                                    <span class="text-muted">Number of
                                                                                        Months:
                                                                                        {{ $c->purchase_fee_months }}</span>
                                                                                </div>
                                                                            @endif
                                                                            @if (!empty($c->sales_tax_option_monthly))
                                                                                <div class="mt-1">
                                                                                    <span class="text-muted">Sales Tax:
                                                                                        {{ ucfirst($c->sales_tax_option_monthly) }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @elseif ($c->purchase_fee_type === 'Flat Fee' && !empty($c->purchase_fee_flat_commercial))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">${{ $c->purchase_fee_flat_commercial }}</span>
                                                                            </div>
                                                                            @if (!empty($c->sales_tax_option_flat))
                                                                                <div class="mt-1">
                                                                                    <span class="text-muted">Sales Tax:
                                                                                        {{ ucfirst($c->sales_tax_option_flat) }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @elseif ($c->purchase_fee_type === 'other' && !empty($c->purchase_fee_other_commercial))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">{{ $c->purchase_fee_other_commercial }}</span>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @endif

                                                                <!-- Lease-Option Agreement -->
                                                                @if (!empty($c->interested_lease_option_agreement))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Interested in Lease-Option Agreement:</div>
                                                                        <div class="text-muted">
                                                                            {{ $c->interested_lease_option_agreement }}
                                                                        </div>

                                                                        @if ($c->interested_lease_option_agreement === 'Yes')
                                                                            @if (!empty($c->lease_value))
                                                                                <div class="mt-1">
                                                                                    <span class="text-muted">
                                                                                        Option Consideration:
                                                                                        @if ($c->lease_type === 'percent')
                                                                                            {{ $c->lease_value }}%
                                                                                        @else
                                                                                            ${{ $c->lease_value }}
                                                                                        @endif
                                                                                    </span>
                                                                                </div>
                                                                            @endif
                                                                            @if (!empty($c->purchase_value))
                                                                                <div class="mt-1">
                                                                                    <span class="text-muted">
                                                                                        Purchase Compensation:
                                                                                        @if ($c->purchase_type === 'percent')
                                                                                            {{ $c->purchase_value }}%
                                                                                        @else
                                                                                            ${{ $c->purchase_value }}
                                                                                        @endif
                                                                                    </span>
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                @endif

                                                                <!-- Interested in Selling -->
                                                                @if (!empty($c->interested_in_selling))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Interested in Selling:</div>
                                                                        <div class="text-muted">
                                                                            {{ $c->interested_in_selling }}</div>

                                                                        @if ($c->interested_in_selling === 'Yes' && !empty($c->interested_in_selling_type))
                                                                            <div class="mt-1">
                                                                                <span class="text-muted">Purchase Fee Type:
                                                                                    {{ $c->interested_in_selling_type }}</span>
                                                                            </div>

                                                                            @if (
                                                                                $c->interested_in_selling_type === 'Percentage of the Total Purchase Price' &&
                                                                                    !empty($c->landlord_broker_purchase_price))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->landlord_broker_purchase_price }}%</span>
                                                                                </div>
                                                                            @elseif($c->interested_in_selling_type === 'Percentage of the Total Purchase Price + Flat Fee')
                                                                                @if (!empty($c->landlord_broker_percentage_price))
                                                                                    <div class="mt-1">
                                                                                        <span
                                                                                            class="text-muted">{{ $c->landlord_broker_percentage_price }}%
                                                                                            + </span>
                                                                                    </div>
                                                                                @endif
                                                                                @if (!empty($c->landlord_broker_dollar_price))
                                                                                    <div class="mt-1">
                                                                                        <span
                                                                                            class="text-muted">${{ $c->landlord_broker_dollar_price }}</span>
                                                                                    </div>
                                                                                @endif
                                                                            @elseif($c->interested_in_selling_type === 'Flat Fee' && !empty($c->landlord_broker_flate_fee))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">${{ $c->landlord_broker_flate_fee }}</span>
                                                                                </div>
                                                                            @elseif($c->interested_in_selling_type === 'Other' && !empty($c->landlord_broker_other))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->landlord_broker_other }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                @endif

                                                                <!-- Payment Timing for Broker Fees -->
                                                                @if (!empty($c->broker_fee_timing))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Payment Timing for Broker Fees:</div>
                                                                        <div class="text-muted">
                                                                            {{ $c->broker_fee_timing }}</div>

                                                                        @if ($property_type === 'Residential Property')
                                                                            @if ($c->broker_fee_timing === 'Deducted from Rent Collected' && !empty($c->broker_fee_days_from_rent))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->broker_fee_days_from_rent }}
                                                                                        calendar days</span>
                                                                                </div>
                                                                            @elseif (
                                                                                $c->broker_fee_timing === 'Paid Within Calendar Days After Executed Lease' &&
                                                                                    !empty($c->broker_fee_days_after_lease))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->broker_fee_days_after_lease }}
                                                                                        calendar days</span>
                                                                                </div>
                                                                            @elseif (
                                                                                $c->broker_fee_timing === 'Paid Within Calendar Days of Tenant Rent Payment' &&
                                                                                    !empty($c->broker_fee_days_after_rent))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->broker_fee_days_after_rent }}
                                                                                        calendar days</span>
                                                                                </div>
                                                                            @elseif ($c->broker_fee_timing === 'other' && !empty($c->broker_fee_timing_other))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->broker_fee_timing_other }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @elseif ($property_type === 'Commercial Property')
                                                                            @if ($c->broker_fee_timing === 'Other' && !empty($c->broker_fee_timing_other))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->broker_fee_timing_other }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                @endif

                                                                <!-- Lease Renewal/Extension Fee -->
                                                                @if (!empty($c->renewal_fee_type))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Lease Renewal/Extension Fee:</div>
                                                                        <div class="text-muted">{{ $c->renewal_fee_type }}
                                                                        </div>

                                                                        @if ($property_type === 'Residential Property')
                                                                            @if ($c->renewal_fee_type === 'Percentage of the Rent Due Each Rental Period' && !empty($c->renewal_fee_percentage))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->renewal_fee_percentage }}%</span>
                                                                                </div>
                                                                            @elseif ($c->renewal_fee_type === 'Percentage of the Gross Lease Value' && !empty($c->renewal_fee_lease_value))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->renewal_fee_lease_value }}%</span>
                                                                                </div>
                                                                            @elseif ($c->renewal_fee_type === "Percentage of the First Month's Rent" && !empty($c->renewal_fee_first_month))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->renewal_fee_first_month }}%</span>
                                                                                </div>
                                                                            @elseif ($c->renewal_fee_type === 'Flat Fee' && !empty($c->renewal_fee_flat_free))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">${{ $c->renewal_fee_flat_free }}</span>
                                                                                </div>
                                                                            @elseif ($c->renewal_fee_type === 'other' && !empty($c->renewal_fee_custom))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->renewal_fee_custom }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @elseif ($property_type === 'Commercial Property')
                                                                            @if ($c->renewal_fee_type === 'Percentage of the Net Aggregate Rent' && !empty($c->renewal_fee_percentage))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->renewal_fee_percentage }}%</span>
                                                                                </div>
                                                                            @elseif ($c->renewal_fee_type === 'Percentage of the Gross Rent' && !empty($c->renewal_fee_lease_value))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->renewal_fee_lease_value }}%</span>
                                                                                </div>
                                                                                @if (!empty($c->renewal_fee_sales_tax_lease_value))
                                                                                    <div class="mt-1">
                                                                                        <span class="text-muted">Sales Tax:
                                                                                            {{ ucfirst($c->renewal_fee_sales_tax_lease_value) }}</span>
                                                                                    </div>
                                                                                @endif
                                                                            @elseif ($c->renewal_fee_type === 'Percentage of Month\'s Rent' && !empty($c->renewal_fee_first_month))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->renewal_fee_first_month }}%</span>
                                                                                </div>
                                                                                @if (!empty($c->renewal_fee_no_of_months))
                                                                                    <div class="mt-1">
                                                                                        <span class="text-muted">Number of
                                                                                            Months:
                                                                                            {{ $c->renewal_fee_no_of_months }}</span>
                                                                                    </div>
                                                                                @endif
                                                                                @if (!empty($c->renewal_fee_sales_tax_first_month))
                                                                                    <div class="mt-1">
                                                                                        <span class="text-muted">Sales Tax:
                                                                                            {{ ucfirst($c->renewal_fee_sales_tax_first_month) }}</span>
                                                                                    </div>
                                                                                @endif
                                                                            @elseif($c->renewal_fee_type === 'Flat Fee' && !empty($c->renewal_fee_flat_free))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">${{ $c->renewal_fee_flat_free }}</span>
                                                                                </div>
                                                                                @if (!empty($c->renewal_fee_sales_tax_flat_fee))
                                                                                    <div class="mt-1">
                                                                                        <span class="text-muted">Sales Tax:
                                                                                            {{ ucfirst($c->renewal_fee_sales_tax_flat_fee) }}</span>
                                                                                    </div>
                                                                                @endif
                                                                            @elseif ($c->renewal_fee_type === 'other' && !empty($c->renewal_fee_custom))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->renewal_fee_custom }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                @endif

                                                                <!-- Expansion Commission (Commercial only) -->
                                                                @if ($property_type === 'Commercial Property' && !empty($c->expansion_commission_percentage))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Expansion Commission for Lease Amendment:</div>
                                                                        <div class="text-muted">
                                                                            {{ $c->expansion_commission_percentage }}% of
                                                                            original commission</div>
                                                                    </div>
                                                                @endif

                                                                <!-- Tenant's Broker Commission (Residential only) -->
                                                                @if ($property_type === 'Residential Property' && !empty($c->tenant_broker_commission_structure))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Tenant's Broker Commission Fee:</div>
                                                                        <div class="text-muted">
                                                                            {{ $c->tenant_broker_commission_structure }}
                                                                        </div>

                                                                        @if (!empty($c->tenant_broker_fee_structure))
                                                                            <div class="mt-1">
                                                                                <span class="text-muted">Fee Structure:
                                                                                    {{ $c->tenant_broker_fee_structure }}</span>
                                                                            </div>

                                                                            @if (
                                                                                $c->tenant_broker_fee_structure === 'Percentage of the Rent Due Each Rental Period' &&
                                                                                    !empty($c->tenant_broker_percentage))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->tenant_broker_percentage }}%</span>
                                                                                </div>
                                                                            @elseif ($c->tenant_broker_fee_structure === 'Percentage of the Gross Lease Value' && !empty($c->tenant_broker_gross_lease))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->tenant_broker_gross_lease }}%</span>
                                                                                </div>
                                                                            @elseif (
                                                                                $c->tenant_broker_fee_structure === 'Percentage of the First Month\'s Rent' &&
                                                                                    !empty($c->tenant_broker_first_month_rent))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->tenant_broker_first_month_rent }}%</span>
                                                                                </div>
                                                                            @elseif ($c->tenant_broker_fee_structure === 'Flat fee' && !empty($c->tenant_broker_flat_fee))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">${{ $c->tenant_broker_flat_fee }}</span>
                                                                                </div>
                                                                            @elseif ($c->tenant_broker_fee_structure === 'Other' && !empty($c->tenant_broker_other))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->tenant_broker_other }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            <!-- Agency and Additional Terms Section -->
                                                            <div class="mb-4">
                                                                <h6 class="mb-3"
                                                                    style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                    <i class="fa-solid fa-building me-2"></i>Agency &
                                                                    Additional Terms
                                                                </h6>

                                                                <!-- Protection Period -->
                                                                @if (!empty($c->protection_period))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Protection Period Timeframe:</div>
                                                                        <div class="text-muted">
                                                                            {{ $c->protection_period }} days</div>
                                                                    </div>
                                                                @endif

                                                                <!-- Early Termination Fee -->
                                                                @if (!empty($c->early_termination_fee_option))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Early Termination Fee:</div>
                                                                        <div class="text-muted">
                                                                            {{ $c->early_termination_fee_option === 'yes' ? 'Yes' : 'No' }}
                                                                        </div>

                                                                        @if ($c->early_termination_fee_option === 'yes' && !empty($c->early_termination_fee_amount))
                                                                            <div class="mt-1">
                                                                                <span
                                                                                    class="text-muted">${{ $c->early_termination_fee_amount }}</span>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                @endif

                                                                <!-- Agency Agreement Timeframe -->
                                                                @if (!empty($c->agency_agreement_timeframe))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Landlord Agency Agreement Timeframe:</div>
                                                                        <div class="text-muted">
                                                                            @if ($c->agency_agreement_timeframe === 'Other' && !empty($c->agency_agreement_custom))
                                                                                {{ $c->agency_agreement_custom }}
                                                                            @else
                                                                                {{ $c->agency_agreement_timeframe }}
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endif

                                                                <!-- Property Management -->
                                                                @if (!empty($c->interested_in_property_management))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Interested in Property Management:</div>
                                                                        <div class="text-muted">
                                                                            {{ $c->interested_in_property_management === 'yes' ? 'Yes' : 'No' }}
                                                                        </div>

                                                                        @if ($c->interested_in_property_management === 'yes' && !empty($c->interested_in_property_management_fee))
                                                                            <div class="mt-1">
                                                                                <span class="text-muted">Fee Type:
                                                                                    {{ $c->interested_in_property_management_fee }}</span>
                                                                            </div>

                                                                            @if (
                                                                                $c->interested_in_property_management_fee === 'Percentage of the Gross Lease Value' &&
                                                                                    !empty($c->interested_in_property_management_fee_gross_lease))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->interested_in_property_management_fee_gross_lease }}%</span>
                                                                                </div>
                                                                            @elseif (
                                                                                $c->interested_in_property_management_fee === 'Percentage of the Rent Due Each Rental Period' &&
                                                                                    !empty($c->interested_in_property_management_fee_rental_periord))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->interested_in_property_management_fee_rental_periord }}%</span>
                                                                                </div>
                                                                            @elseif (
                                                                                $c->interested_in_property_management_fee === 'Flat Fee' &&
                                                                                    !empty($c->interested_in_property_management_fee_flate_free))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">${{ $c->interested_in_property_management_fee_flate_free }}</span>
                                                                                </div>
                                                                            @elseif ($c->interested_in_property_management_fee === 'Other' && !empty($c->interested_in_property_management_fee_other))
                                                                                <div class="mt-1">
                                                                                    <span
                                                                                        class="text-muted">{{ $c->interested_in_property_management_fee_other }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                    </div>
                                                                @endif

                                                                <!-- Brokerage Relationship -->
                                                                @if (!empty($c->brokerage_relationship))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Acceptable Brokerage Relationship:</div>
                                                                        <div class="text-muted">
                                                                            {{ $c->brokerage_relationship }}</div>
                                                                    </div>
                                                                @endif

                                                                <!-- Additional Terms -->
                                                                @if (!empty($c->additional_details_broker))
                                                                    <div class="mb-3">
                                                                        <div class="fw-semibold" style="color: #049399;">
                                                                            Additional Terms:</div>
                                                                        <div class="text-muted"
                                                                            style="font-style: italic;">
                                                                            {{ $c->additional_details_broker }}</div>
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            <!-- Services Section -->
                                                            @php
                                                                $counter_services = is_string(@$c->services)
                                                                    ? json_decode(@$c->services, true)
                                                                    : @$c->services;
                                                                $counter_other_services = is_string(@$c->other_services)
                                                                    ? json_decode(@$c->other_services, true)
                                                                    : @$c->other_services;
                                                            @endphp

                                                            @if (!empty($counter_services) && is_array($counter_services))
                                                                <div class="mb-4">
                                                                    <h6 class="mb-3"
                                                                        style="color: #049399; font-weight: 600; border-bottom: 2px solid #049399; padding-bottom: 8px;">
                                                                        <i class="fa-solid fa-list-check me-2"></i>Services
                                                                        Offered
                                                                    </h6>

                                                                    <div class="d-flex flex-wrap gap-2">
                                                                        @foreach ($counter_services as $service)
                                                                            @if ($service == 'Other')
                                                                                @continue
                                                                            @endif
                                                                            <span class="badge bg-light text-dark border"
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
                                                                        <i class="fa-solid fa-plus-circle me-2"></i>Other
                                                                        Services
                                                                    </h6>

                                                                    <div class="d-flex flex-wrap gap-2">
                                                                        @foreach ($counter_other_services as $other_service)
                                                                            @if ($other_service == 'Other')
                                                                                @continue
                                                                            @endif
                                                                            <span class="badge bg-light text-dark border"
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
                                                            <button type="button" class="btn btn-outline-secondary"
                                                                data-bs-dismiss="modal">
                                                                <i class="fa-solid fa-xmark me-1"></i>Close
                                                            </button>
                                                            <a href="{{ route('landlord.edit-counter-terms', $auction->id) }}"
                                                                class="btn btn-primary">
                                                                <i class="fa-solid fa-pen-to-square me-1"></i>Edit Terms
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
                window.location.href = '{{ route('landlord.agent.auctions.list') }}?type=' + val;
            });
        });
    </script>
@endpush
