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
            <div class="rightCol col-sm-12 col-md-9 col-lg-9">
              <div class="container mt-5 myAuctions">
                <h1>Hire Seller's Agent Auctions by harry</h1>
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

                <table class="table table-hover data-table" style="border-collapse:separate;border-spacing:0;">
                  <thead>
                    <tr style="background:#049399;color:#fff;">
                      <th style="border:none;">Listing</th>
                      <th style="border:none;">Posted</th>
                      <th class="text-center" style="border:none;">Bids</th>
                      <th class="text-center" style="border:none;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($auctions as $auction)
                      
                      @php
                          $counter = App\Models\SellerCounterTerm::where('seller_agent_auction_id', $auction->id)->first();
                      @endphp
                      <tr>
                        <td class="align-middle">
                          <a href="{{ route('seller.agent.auction.detail', @$auction->id) }}" class="fw-semibold text-decoration-none" style="color:#049399;">{{ @$auction->title }}</a>
                          <div class="text-muted mt-1" style="font-size:12px;">
                            {{ $auction->get->cities[0] ?? '' }}{{ ($auction->get->cities[0] ?? '') && (@$auction->get->state) ? ', ' : '' }}{{ @$auction->get->state }}
                          </div>
                        </td>
                        <td class="text-nowrap align-middle" style="font-size:13px;">{{ Carbon\Carbon::parse(@$auction->created_at)->format('M d, Y') }}</td>
                        <td class="text-center align-middle">
                          <span class="badge rounded-pill" style="background:#049399;color:#fff;font-size:13px;min-width:32px;">{{ @$auction->bids->count() }}</span>
                        </td>
                        <td class="align-middle" style="min-width:170px;">
                          <a href="{{ route('seller.agent.auction.detail', @$auction->id) }}" class="btn btn-sm d-block mb-1" style="background:#049399;color:#fff;">Review Bids</a>
                          @if (!@$auction->is_approved)
                            <a href="{{ route('hire.agent.auction.edit', ['auctionId' => $auction->id, 'user_type' => $auction->get->user_type]) }}" class="btn btn-sm btn-outline-secondary d-block mb-1">Edit Listing</a>
                          @endif
                          @if (isset($counter))
                            <a href="{{ route('seller.edit-counter-terms', $auction->id) }}" class="btn btn-sm d-block mb-1" style="border:1px solid #049399;color:#049399;background:transparent;">Edit Counter Terms</a>
                            <a data-toggle="modal" data-target="#modal-{{ $auction->id }}" class="btn btn-sm d-block" style="border:1px solid #6c757d;color:#6c757d;background:transparent;" href="#">View Counter Terms</a>
                          @endif
                        </td>
                      </tr>
                      <div class="modal fade" id="modal-{{ @$auction->id }}" tabindex="-1" role="dialog"
                        aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                          <div class="modal-content">
                            <div class="modal-header">
                              <h5 class="modal-title" id="exampleModalLabel">Sellers's Countered Terms</h5>
                              <button type="button"
                                style="background: #049399; width:70px; border-radius:5px; border:none; color:white;"
                                class="close p-1" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                              </button>
                            </div>
                            <div class="modal-body">
                              <div class="row" style="flex-wrap: wrap;">
                                @if (isset($counter->timeframe))
                                  <div class="col-md-12 col-12 pt-2 removeBold"><i class="fa-regular fa-check-square"></i>
                                    <span class="fw-bold">Offered Timeframe for the Seller Agency Agreement:</span>
                                    {{ $counter->timeframe }}
                                  </div>
                                @endif
                                @if (isset($counter->commission))
                                  <div class="col-md-12 col-12 pt-2 removeBold"><i class="fa-regular fa-check-square"></i>
                                    <span class="fw-bold">Total Commission being offered to the agent:</span>
                                    {{ $counter->commission }}
                                  </div>
                                @endif
                                @if (isset($counter->services))
                                  <div class="col-md-12 col-12 pt-2 removeBold services"><i
                                      class="fa-regular fa-check-square"></i><span class="fw-bold"> Select the services
                                      the seller wants the hired agent to provide: </span>
                                    @php
                                      $services = json_decode($counter->services);
                                    @endphp
                                    <ul class="px-5">
                                      @foreach ($services as $service)
                                        <li style="font-size: 16px; margin-top:5px;">
                                          <span class="removeBold">
                                            {{ $service }}
                                          </span>
                                        </li>
                                      @endforeach
                                      {{ $service }}
                                    </ul>
                                  </div>
                                @endif
                                @if (isset($counter->additionalDetails))
                                  <div class="col-md-12 col-12 pt-2 removeBold"><i
                                      class="fa-regular fa-check-square"></i><span class="fw-bold"> Additional
                                      Details:</span>
                                    {{ $counter->additionalDetails }}
                                  </div>
                                @endif
                              </div>
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
    function submitForm() {
      $("#statusForm").submit();
    }
  </script>
  <script>
    $(function() {
      $('.auction-type').on('change', function() {
        var val = $(this).val();
        window.location.href = '{{ route('hireSellerAgentHireAuctions') }}?type=' + val;
      });
    });
  </script>
@endpush
