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
                <h1>Hire Buyer's Agent Auctions</h1>
                <!-- Section 1  -->
                <select class="form-select mt-4 mb-3 w-25 auction-type">
                  <option value="2" {{ $type == '2' ? 'selected' : '' }}>Live ({{ $liveCount }})</option>
                  <option value="1" {{ $type == '1' ? 'selected' : '' }}>Pending Approval
                    ({{ $pendingApprovalCount }})
                  </option>
                  <option value="3" {{ $type == '3' ? 'selected' : '' }}>Awarded ({{ $soldCount }})</option>
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
                            href="{{ route('buyer.view-auction', @$auction->id) }}">{{ @$auction->title }}</a>
                        </td>
                              <td>{{ $auction->get->counties[0] }}</td>

                        <td> {{$auction->get->cities[0]}}</td>
                        <td>{{ @$auction->get->state }}</td>
                        <td>{{ Carbon\Carbon::parse(@$auction->created_at)->format('M d, Y') }}
                        </td>
                        <td class="text-center">{{ @$auction->bids->count() }}</td>
                        <td class="text-center">
                          <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle btn-sm" type="button"
                              data-bs-toggle="dropdown" aria-expanded="false">
                              Action
                            </button>
                            <ul class="dropdown-menu">
                              <li>
                                <a class="dropdown-item"
                                  href="{{ route('buyer.view-auction', @$auction->id) }}">
                                  <i class="fa-solid fa-eye" style="font-size:14px;"></i>
                                  <span style="font-size:14px;">View</span>
                                </a>
                              </li>
                              @if (!@$auction->is_approved)
                            <li>
                                   <a class="dropdown-item"
                                 href="{{ route('hire.agent.auction.edit', ['auctionId' => $auction->id, 'user_type' => $auction->get->user_type]) }}">
                                    <i class="fa-solid fa-pencil" style="font-size:14px;"></i>
                                    <span style="font-size:14px;">Edit</span>
                                  </a>
                                </li>
                              @endif
                              <li>
                                @php
                                  $counter = App\Models\BuyerCounterTerm::where(
                                      'buyer_agent_auction_id',
                                      @$auction->id,
                                  )->first();
                                @endphp

                                @if (isset($counter))
                                <a class="dropdown-item" href="{{ route('buyer.edit-counter-terms', $auction->id) }}">
                                    <i class="fa-solid fa-edit" style="font-size:14px;"></i>
                                    <span style="font-size:14px;"> Edit Terms </span>
                                </a>
                                  <a data-toggle="modal" data-target="#modal-{{ $auction->id }}" class="dropdown-item"
                                    href="#">
                                    <i class="fa-solid fa-eye" style="font-size:14px;"></i>
                                    <span style="font-size:14px;"> View Terms </span>
                                  </a>
                                @else
                                  <a class="dropdown-item" href="{{ route('buyer.counter-terms', $auction->id) }}">
                                    <i class="fa-solid fa-plus" style="font-size:14px;"></i>
                                    <span style="font-size:14px;"> Add Terms </span>
                                  </a>
                                @endif
                              </li>
                              <li>
                                <a class="dropdown-item"
                                  href="{{ route('manage.bot.questions', ['tenant-agent', $auction->id]) }}">
                                  <i class="fa-solid fa-robot" style="font-size:14px;"></i>
                                  <span style="font-size:14px;">Manage Chat Bot
                                    Questions</span>
                                </a>
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
                      <!-- Modal -->

                      <!-- Modal -->
                                                <div class="modal fade" id="modal-{{ @$auction->id }}" tabindex="-1"
                                                    aria-labelledby="modalLabel-{{ @$auction->id }}" aria-hidden="true">
                                                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                        <div class="modal-content">

                                                            <div class="modal-header">
                                                                <h5 class="modal-title"
                                                                    id="modalLabel-{{ @$auction->id }}">
                                                                    <i class="fa-solid fa-file-signature me-2"></i>Sellers's
                                                                    Countered Terms
                                                                </h5>
                                                                <button type="button" class="btn-close"
                                                                    data-bs-dismiss="modal" aria-label="Close">&times;</button>
                                                            </div>

                                                            <div class="modal-body">

                                                                {{-- ===== Broker Compensation ===== --}}


                                                                        <!-- Broker Compensation Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Broker Compensation</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- Commission Structure -->
            @if (@$counter->get->commission_structure != null)
                <div class="col-md-6">
                    <div class="text-muted small mb-1">Buyer's Broker Commission Structure</div>
                    <span class="badge bg-secondary-subtle text-dark px-3 py-2">
                        {{ $counter->get->commission_structure ?? '' }}
                    </span>
                </div>
            @endif

            <!-- Purchase Fee -->
            @if (@$counter->get->purchase_fee_type != null)
                <div class="col-12">
                    <div class="text-muted small mb-1">Buyer's Broker Purchase Fee</div>
                    <span class="badge bg-primary-subtle text-dark px-3 py-2 mb-2">
                        {{ $counter->get->purchase_fee_type ?? '' }}
                    </span>

                    <!-- Purchase Fee Details -->
                    <div class="mt-2">
                        @if (@$counter->get->purchase_fee_type === 'Flat Fee' && @$counter->get->purchase_fee_flat != null)
                            <div class="fw-semibold">${{ $counter->get->purchase_fee_flat }}</div>
                        @elseif(@$counter->get->purchase_fee_type === 'Percentage of the Total Purchase Price' && @$counter->get->purchase_fee_percentage != null)
                            <div class="fw-semibold">{{ $counter->get->purchase_fee_percentage }}%</div>
                        @elseif(@$counter->get->purchase_fee_type === 'Percentage of the Total Purchase Price + Flat Fee')
                            @if (@$counter->get->purchase_fee_percentage_combo || @$counter->get->purchase_fee_flat_combo)
                                <div class="fw-semibold">
                                    {{ $counter->get->purchase_fee_percentage_combo }}% + ${{ $counter->get->purchase_fee_flat_combo }}
                                </div>
                            @endif
                        @elseif(@$counter->get->purchase_fee_type === 'other' && @$counter->get->purchase_fee_other != null)
                            <div class="fw-semibold">{{ $counter->get->purchase_fee_other }}</div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Lease Agreement Interest -->
            @if (@$counter->get->interested_lease_option != null)
                <div class="col-md-6">
                    <div class="text-muted small mb-1">Interested in Lease Agreement</div>
                    <span class="badge {{ @$counter->get->interested_lease_option === 'Yes' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-dark' }} px-3 py-2">
                        {{ $counter->get->interested_lease_option ?? '' }}
                    </span>
                </div>
            @endif

            <!-- Lease Option Agreement Interest -->
            @if (@$counter->get->interested_lease_option_agreement != null)
                <div class="col-md-6">
                    <div class="text-muted small mb-1">Interested in Lease-Option Agreement</div>
                    <span class="badge {{ @$counter->get->interested_lease_option_agreement === 'Yes' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-dark' }} px-3 py-2">
                        {{ $counter->get->interested_lease_option_agreement ?? '' }}
                    </span>
                </div>
            @endif
        </div>

        <!-- Lease Fee Details (Only show if interested_lease_option is 'Yes') -->
        @if (@$counter->get->interested_lease_option === 'Yes' && @$counter->get->lease_fee_type != null)
            <div class="mt-4 pt-3 border-top">
                <div class="text-muted small mb-2">Buyer's Broker Lease Fee</div>
                <span class="badge bg-info-subtle text-dark px-3 py-2 mb-3">
                    {{ $counter->get->lease_fee_type ?? '' }}
                </span>

                <!-- Lease Fee details by type -->
                <div class="row g-2">
                    @if (@$counter->get->lease_fee_type === 'flat' && @$counter->get->lease_fee_flat != null)
                        <div class="col-12">
                            <div class="fw-semibold">${{ $counter->get->lease_fee_flat }}</div>
                        </div>
                    @elseif(@$counter->get->lease_fee_type === 'Percentage of the Gross Lease Value' && @$counter->get->lease_fee_percentage != null)
                        <div class="col-12">
                            <div class="fw-semibold">{{ $counter->get->lease_fee_percentage }}% of Gross Lease Value</div>
                        </div>
                    @elseif(@$counter->get->lease_fee_type === 'Percentage of Monthly Rent' && @$counter->get->lease_fee_percentage_monthly_rent != null)
                        <div class="col-12">
                            <div class="fw-semibold">
                                {{ $counter->get->lease_fee_percentage_monthly_rent }}% of Monthly Rent
                                @if (@$counter->get->lease_fee_percentage_monthly_number)
                                    for {{ $counter->get->lease_fee_percentage_monthly_number }} months
                                @endif
                            </div>
                        </div>
                    @elseif(@$counter->get->lease_fee_type === 'Flat Fee + Percentage of the Gross Lease Value')
                        @if (@$counter->get->lease_fee_flat_combo || @$counter->get->lease_fee_percentage_combo)
                            <div class="col-12">
                                <div class="fw-semibold">
                                    ${{ $counter->get->lease_fee_flat_combo }} + {{ $counter->get->lease_fee_percentage_combo }}% of Gross Lease Value
                                </div>
                            </div>
                        @endif
                    @elseif(@$counter->get->lease_fee_type === 'Percentage of the Net Aggregate Rent' && @$counter->get->lease_fee_percentage_net != null)
                        <div class="col-12">
                            <div class="fw-semibold">{{ $counter->get->lease_fee_percentage_net }}% of Net Aggregate Rent</div>
                        </div>
                    @elseif(@$counter->get->lease_fee_type === 'Flat Fee + Percentage of the Net Aggregate Rent')
                        @if (@$counter->get->lease_fee_flat_combo_net || @$counter->get->lease_fee_percentage_combo_net)
                            <div class="col-12">
                                <div class="fw-semibold">
                                    ${{ $counter->get->lease_fee_flat_combo_net }} + {{ $counter->get->lease_fee_percentage_combo_net }}% of Net Aggregate Rent
                                </div>
                            </div>
                        @endif
                    @elseif(@$counter->get->lease_fee_type === 'other' && @$counter->get->lease_fee_other != null)
                        <div class="col-12">
                            <div class="fw-semibold">{{ $counter->get->lease_fee_other }}</div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Lease-Option Agreement Details (Only show if interested_lease_option_agreement is 'Yes') -->
        @if (@$counter->get->interested_lease_option_agreement === 'Yes')
            <div class="mt-4 pt-3 border-top">
                <h6 class="text-muted mb-3">Lease-Option Agreement Compensation</h6>

                <!-- Compensation for Creating Lease-Option Agreement -->
                @if (@$counter->get->lease_value)
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <div class="text-muted small">For Creating Agreement</div>
                            <div class="fw-semibold">
                                {{ $counter->get->lease_value }}{{ @$counter->get->lease_type === 'percent' ? '%' : '$' }}
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Compensation if Purchase Option is Exercised -->
                @if (@$counter->get->purchase_value)
                    <div class="row g-2">
                        <div class="col-md-6">
                            <div class="text-muted small">If Option Exercised</div>
                            <div class="fw-semibold">
                                {{ $counter->get->purchase_value }}{{ @$counter->get->purchase_type === 'percent' ? '%' : '$' }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

<!-- Agreement Settings Section -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fa-solid fa-shield-halved me-2"></i>Agreement Settings</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <!-- Protection Period -->
            @if (@$counter->get->protection_period != null)
                <div class="col-md-6">
                    <div class="text-muted small">Protection Period (Days)</div>
                    <div class="fw-semibold">
                        {{ $counter->get->protection_period ?? '' }}
                    </div>
                </div>
            @endif

            <!-- Early Termination Fee -->
            @if (@$counter->get->early_termination_fee_option != null)
                <div class="col-md-6">
                    <div class="text-muted small">Early Termination Fee</div>
                    <div>
                        <span class="badge {{ @$counter->get->early_termination_fee_option === 'yes' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-dark' }} px-3 py-2">
                            {{ @$counter->get->early_termination_fee_option === 'yes' ? 'Yes' : 'No' }}
                        </span>
                    </div>
                </div>
            @endif

            @if (@$counter->get->early_termination_fee_option === 'yes' && @$counter->get->early_termination_fee_amount != null)
                <div class="col-12">
                    <div class="text-muted small">Early Termination Fee Amount</div>
                    <div class="fw-semibold">
                        ${{ $counter->get->early_termination_fee_amount }}
                    </div>
                </div>
            @endif

            <!-- Retainer Fee -->
            @if (@$counter->get->retainer_fee_option != null)
                <div class="col-md-6">
                    <div class="text-muted small">Retainer Fee</div>
                    <div>
                        <span class="badge {{ @$counter->get->retainer_fee_option === 'yes' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-dark' }} px-3 py-2">
                            {{ @$counter->get->retainer_fee_option === 'yes' ? 'Yes' : 'No' }}
                        </span>
                    </div>
                </div>
            @endif

            @if (@$counter->get->retainer_fee_option === 'yes' && @$counter->get->retainer_fee_amount != null)
                <div class="col-md-6">
                    <div class="text-muted small">Retainer Fee Amount</div>
                    <div class="fw-semibold">
                        ${{ $counter->get->retainer_fee_amount ?? '' }}
                    </div>
                </div>
            @endif

            @if (@$counter->get->retainer_fee_option === 'yes' && @$counter->get->retainer_fee_application != null)
                <div class="col-12">
                    <div class="text-muted small">Retainer Fee Application</div>
                    <div class="fw-semibold">
                        {{ $counter->get->retainer_fee_application ?? '' }}
                    </div>
                </div>
            @endif

            <!-- Agency Agreement Timeframe -->
            @if (@$counter->get->agency_agreement_timeframe != null)
                <div class="col-md-6">
                    <div class="text-muted small">Buyer Agency Agreement Timeframe</div>
                    <div class="fw-semibold">
                        {{ $counter->get->agency_agreement_timeframe ?? '' }}
                    </div>
                </div>
            @endif

            @if (@$counter->get->agency_agreement_timeframe === 'custom' && @$counter->get->agency_agreement_custom != null)
                <div class="col-md-6">
                    <div class="text-muted small">Custom Timeframe</div>
                    <div class="fw-semibold">
                        {{ $counter->get->agency_agreement_custom }}
                    </div>
                </div>
            @endif

            <!-- Brokerage Relationship -->
            @if (@$counter->get->brokerage_relationship != null)
                <div class="col-12">
                    <div class="text-muted small">Acceptable Brokerage Relationship</div>
                    <div class="fw-semibold">
                        {{ $counter->get->brokerage_relationship ?? '' }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Additional Terms Section -->
@if (@$counter->get->additional_details_broker != null)
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fa-regular fa-note-sticky me-2"></i>Additional Terms</h5>
        </div>
        <div class="card-body">
            <div class="fw-semibold">
                {{ $counter->get->additional_details_broker ?? '' }}
            </div>
        </div>
    </div>
@endif

                                                                {{-- ===== Services ===== --}}
                                                                <div class="card border-0 shadow-sm mb-4">
                                                                    <div class="card-header bg-light">
                                                                        <h5 class="mb-0"><i
                                                                                class="fa-solid fa-list-check me-2"></i>Services
                                                                        </h5>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <div class="text-muted small mb-2">Services the
                                                                            Tenant Requests from Their Agent</div>
                                                                        <ul class="list-group rounded">
                                                                            @if (!empty($counter->get->services))
                                                                                @foreach ($counter->get->services as $service)
                                                                                    <li class="list-group-item">
                                                                                        <i
                                                                                            class="fa-regular fa-square-check me-2 text-success"></i>{{ $service }}
                                                                                    </li>
                                                                                @endforeach
                                                                            @else
                                                                                <li class="list-group-item text-muted">No
                                                                                    services selected.</li>
                                                                            @endif
                                                                        </ul>

                                                                        @if (@$counter->get->other_services != null)
                                                                            <hr class="my-3">
                                                                            <div class="text-muted small mb-2">Other
                                                                                Services the Tenant Requests</div>
                                                                            <ul class="list-group rounded">
                                                                                @if (!empty($counter->get->other_services))
                                                                                    @foreach ($counter->get->other_services as $other_service)
                                                                                        <li class="list-group-item">
                                                                                            <i
                                                                                                class="fa-regular fa-square-check me-2 text-primary"></i>{{ $other_service }}
                                                                                        </li>
                                                                                    @endforeach
                                                                                @else
                                                                                    <li class="list-group-item text-muted">
                                                                                        No other services listed.</li>
                                                                                @endif
                                                                            </ul>
                                                                        @endif
                                                                    </div>
                                                                </div>

                                                                {{-- ===== Additional Details ===== --}}
                                                                @if (@$counter->get->additional_details != null)
                                                                    <div class="card border-0 shadow-sm">
                                                                        <div class="card-header bg-light">
                                                                            <h5 class="mb-0"><i
                                                                                    class="fa-regular fa-note-sticky me-2"></i>Additional
                                                                                Details</h5>
                                                                        </div>
                                                                        <div class="card-body">
                                                                            <div class="fw-semibold">
                                                                                {{ $counter->get->additional_details ?? '' }}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                @endif

                                                            </div> {{-- /modal-body --}}

                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-outline-secondary"
                                                                    data-bs-dismiss="modal">
                                                                    <i class="fa-solid fa-xmark me-1"></i>Close
                                                                </button>
                                                                <a href="{{ route('buyer.edit-counter-terms', $auction->id) }}"
                                                                    class="btn btn-primary">
                                                                    <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                                                                    Terms
                                                                </a>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>

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
        window.location.href = '{{ route('buyer.agent.auctions.list') }}?type=' + val;
      });
    });
  </script>
@endpush
