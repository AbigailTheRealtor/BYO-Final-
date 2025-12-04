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
                            href="{{ route('seller.agent.auction.detail', @$auction->id) }}">{{ @$auction->title }}</a>
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
                                  href="{{ route('seller.agent.auction.detail', @$auction->id) }}">



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
                              @php
                                $counter = App\Models\SellerCounterTerm::where('seller_auction_id', $auction->id)->first();
                              @endphp
                              @if (isset($counter))
                                <a class="dropdown-item" href="{{ route('seller.edit-counter-terms', $auction->id) }}">
                                  <i class="fa-solid fa-edit" style="font-size:14px;"></i>
                                  <span style="font-size:14px;">Edit Terms </span>
                                </a>
                                <a data-toggle="modal" data-target="#modal-{{ $auction->id }}" class="dropdown-item"
                                  href="#">
                                  <i class="fa-solid fa-eye" style="font-size:14px;"></i>
                                  <span style="font-size:14px;">View Terms </span>
                                </a>
                              @else
                                <a class="dropdown-item" href="{{ route('seller.counter-terms', $auction->id) }}">
                                  <i class="fa-solid fa-plus" style="font-size:14px;"></i>
                                  <span style="font-size:14px;">Add Terms </span>
                                </a>
                              @endif
                              <li>
                                <a class="dropdown-item"
                                  href="{{ route('manage.bot.questions', ['seller-agent', $auction->id]) }}">
                                  <i class="fa-solid fa-robot" style="font-size:14px;"></i>
                                  <span style="font-size:14px;">Manage Chat Bot
                                    Questions</span>
                                </a>
                              </li>
                            </ul>
                          </div>
                        </td>
                        <!-- Your other form fields -->
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
