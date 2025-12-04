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
                        <td>{{ @$auction->county }}</td>
                        <td>{{ @$auction->city }}</td>
                        <td>{{ @$auction->state }}</td>
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
                                  href="{{ route('tenant.agent.view.auction.view', @$auction->id) }}">
                                  <i class="fa-solid fa-eye" style="font-size:14px;"></i>
                                  <span style="font-size:14px;">View</span>
                                </a>
                              </li>
                              @if (!@$auction->is_approved)
                                <li>
                                  <a class="dropdown-item"
                                    href="{{ route('tenant.hire.agent.auction.edit', @$auction->id) }}">
                                    <i class="fa-solid fa-pencil" style="font-size:14px;"></i>
                                    <span style="font-size:14px;">Edit</span>
                                  </a>
                                </li>
                              @endif
                              <li>
                                @php
                                  $counter = App\Models\TenantCounterTerm::where(
                                      'tenant_auction_id',
                                      @$auction->id,
                                  )->first();
                                @endphp
                                @if (isset($counter))
                                  <a class="dropdown-item" href="{{ route('tenant.edit-counter-terms', $auction->id) }}">
                                    <i class="fa-solid fa-edit" style="font-size:14px;"></i>
                                    <span style="font-size:14px;"> Edit Terms </span>
                                  </a>
                                  <a data-toggle="modal" data-target="#modal-{{ $auction->id }}" class="dropdown-item"
                                    href="#">
                                    <i class="fa-solid fa-eye" style="font-size:14px;"></i>
                                    <span style="font-size:14px;"> View Terms </span>
                                  </a>
                                @else
                                  <a class="dropdown-item" href="{{ route('tenant.counter-terms', $auction->id) }}">
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
    $(function() {
      $('.auction-type').on('change', function() {
        var val = $(this).val();
        window.location.href = '{{ route('tenant.agent.auctions.list') }}?type=' + val;
      });
    });
  </script>
@endpush
