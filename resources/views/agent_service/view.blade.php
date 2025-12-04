@extends('layouts.main')
@push('styles')
  <!-- //Listing Description css  -->
  <link rel="stylesheet" href="{{ asset('assets/css/listingDescription.css') }}" />
  <style>
    /* Chrome, Safari, Edge, Opera */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    /* Firefox */
    input[type=number] {
      -moz-appearance: textfield;
    }

    ul {
      --icon-size: 1em;
      --gutter: .5em;
      padding: 0 0 0 calc(var(--icon-size) + 2em);
    }

    ul li {
      padding-left: var(--gutter);
      color: #34465c;
    }

    ul li::marker {
      content: "\f101";
      /* FontAwesome Unicode */
      font-family: FontAwesome;
      font-size: var(--icon-size);
      /* color: #006e9f; */
      color: #11b7cf;
    }

    .removeBold {
      font-weight: normal;
    }
  </style>
@endpush

@section('content')
  @inject('carbon', 'Carbon\Carbon')
  @php
    $auth_id = auth()->user() ? auth()->user()->id : 0;
  @endphp
  <!-- Gallery Start Here  -->
  <div class="container listingDescription">
    <div class="row">
      <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
        <!-- Description Box  -->
        <div class="card description">
          <div class="card-header">
            <h5>Features</h5>
          </div>
          <div class="card-body">
            {{-- @dd(@$auction->get) --}}
            <div class="row" style="flex-wrap: wrap;">
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                City:<span class="removeBold">{{ @$auction->get->city }}</span> </div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i>
                County:<span class="removeBold">{{ @$auction->get->county }}</span> </div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> State:
                <span class="removeBold">{{ @$auction->get->state }}</span> 
              </div>
              @if(@$auction->get->listing_date != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Listing Date :
                  <span class="removeBold">{{ Carbon\Carbon::parse(@$auction->get->listing_date)->format('m-d-Y') }}</span>
                </div>
              @endif
              @if(@$auction->get->expiration_date != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Expiration Date :
                  <span class="removeBold">{{ Carbon\Carbon::parse(@$auction->get->expiration_date)->format('m-d-Y') }}</span> 
                </div>
              @endif
              @if (@$auction->get->service_type == 'Referral agent Service')
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> How
                  much referral fee would agent like?
                  <span
                    class="removeBold">{{ @$auction->get->custom_referral_fee == '' ? @$auction->get->referral_fee : @$auction->get->custom_referral_fee }}</span>
                </div>
              @endif
              @if(@$auction->get->titleListing != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Title of Listing :
                  <span class="removeBold">{{ @$auction->get->titleListing}}</span> 
                </div>
              @endif
              @if(@$auction->get->property_type != null)
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Acceptable Property Styles:
                  <span class="removeBold">{{ @$auction->get->property_type}}</span> 
                  @if (gettype(@$auction->get->property_items) == 'array')
                    @foreach (@$auction->get->property_items as $item)
                      <li class="small"><span class="removeBold">{{ $item }}</span></li>
                    @endforeach
                  @endif
                </div>
              @endif
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Type of Client: <span
                  class="removeBold">{{ @$auction->get->type_of_client }}</span> </div>

              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> What is
                offered compensation to the agent providing the service?
                <span class="removeBold">${{ @$auction->get->offerer_commission }} @if (@$auction->get->offerer_commission_negotiable == 'Yes')
                    negotiable
                  @endif
                </span>
              </div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> When does
                the service need to be provided?<span class="removeBold">
                  {{ Carbon\Carbon::parse(@$auction->get->service_date)->format('d, F, Y') }}</span>
              </div>
              @if (@$auction->get->services != null && @$auction->get->services != 'null' && @$auction->get->services != '')
                <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Service(s) Needed:
                  <ul>
                    @if (gettype(@$auction->get->services) == 'array')
                      @foreach (@$auction->get->services as $item)
                        @if ($item == 'Other')
                          @continue
                        @endif
                        <li class="small"><span class="removeBold">{{ $item }}</span></li>
                      @endforeach
                      @if (@$auction->get->custom_services != '')
                        <li class="small"><span class="removeBold">{{ @$auction->get->custom_services }}</span></li>
                      @endif
                    @endif
                  </ul>
                </div>
              @endif
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Does the hired agent
                need to meet in person at the property?
                <span class="removeBold">
                  ({{ @$auction->get->has_non_negotiable_amenities }})</span><br>
                @if (@$auction->get->has_non_negotiable_amenities == 'No')
                  <ul>
                    <li class="small ">
                      Date the service(s) need to be provided:<span
                        class="removeBold">{{ @$auction->get->custom_non_negotiable_terms }}</span>
                    </li>

                  </ul>
                @else
                  <ul>
                    <li class="small ">
                      Date and time the agent needs to meet at the property:
                      <span class="removeBold">{{ @$auction->get->meeting_date }} at
                        {{ date('h:i A', strtotime($auction->get->meeting_time)) }}</span><br>
                      <small>Note: Information on the person is provided in the showing instructions and is only disclosed
                        to
                        the
                        hired agent for privacy reasons.</small>
                    </li>

                  </ul>
                @endif
              </div>
              <hr />
              <h4>Additional Details:</h4>
              <p>{{ @$auction->get->additional_details }}</p>


            </div>

            @if (@$auction->bids && @$auction->bids->where('accepted', true)->count())
              <hr />
              <h4>Private Notes:</h4>
              <p>{{ @$auction->get->private_notes }}</p>
              <div class="col-md-12 col-12 pt-2 fw-bold"> <i class="fa-regular fa-check-square"></i>
                Additional Information on the service needed:</div>
              <p>{{ @$auction->get->additional_details }}</p>

              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Address of
                the property that agent needs to meet: <br>{{ @$auction->get->address }}</div>

              <div class="fw-bold mt-4">Information on the person the client is meeting</div>

              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Full Name:
                {{ @$auction->get->contact_name }}</div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Phone
                Number: {{ @$auction->get->contact_phone }}</div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Email
                Address: {{ @$auction->get->contact_email }}</div>

              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Has agent
                scheduled the showing with the agent? {{ @$auction->get->showing_with_agent }}</div>
              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> Date and
                time agent needs to meet at the property:
                {{ Carbon\Carbon::parse(@$auction->get->meeting_date . ' ' . @$auction->get->meeting_time)->format('d F, Y h:i a') }}
              </div>

              <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa-regular fa-check-square"></i> What are
                the showing instructions: <br>{{ @$auction->get->instructions }}</div>
            @endif
          </div>
        </div>
        @inject('auctionUser', 'App\Models\User')
        @php
          $auser = $auctionUser::find(@$auction->user_id);
        @endphp
        <!-- Review  -->
        <div class="card review">
          <div class="card-body d-flex align-items-center">
            <div class="left d-flex align-items-center">
              <img class="w-25"
                src="{{ $auser->avatar ? asset('images/avatar/' . $auser->avatar) : 'https://ppt1080.b-cdn.net/images/avatar/none.png' }}"
                alt="">
              <div>
                <p class="mb-0"><a href="{{ route('author', [$auser->id]) }}"><b>User
                      Details</b></a><span></span>
                  <span class="start opacity-50">
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                  </span>
                </p>
                <p class="mb-0">...</p>
                <p class="mb-0 opacity-50">{{ $auser->name }} • last online 5 days ago.</p>
              </div>
            </div>
            <div class="right text-center">
              <a href="{{ route('author', [$auser->id]) }}"><button class="btn">Message</button></a>
              <a href="{{ route('author', [$auser->id]) }}"><button class="btn">View Profile</button></a>
            </div>
          </div>
        </div>
        <!-- End  -->
      </div>
      <div class="col-sm-12 col-md-4 col-lg-4 rightCol">
        <h1>{{ @$auction->get->service_type }}</h1>
        <hr>
        @php
          if (@$auction->auction_length != '-1') {
              $start = $carbon::now(); //parse(db_time());
              if (strpos(@$auction->auction_length, 'M') !== false) {
                  $mins = explode('M', @$auction->auction_length);
                  $end = $carbon::parse(@$auction->created_at)->addMinutes($mins[0]);
              } else {
                  $end = $carbon::parse(@$auction->created_at)->addDays(@$auction->auction_length);
              }
              $diff = $end->diffInDays($start);
          }
        @endphp
        @if (@$auction->auction_length != '-1')
          @php
            $diff_d = $diff;
            $diff_H = $start->diff($end)->format('%H');
            $diff_I = $start->diff($end)->format('%I');
            $diff_S = $start->diff($end)->format('%S');
          @endphp
          <div class="time d-flex justify-content-between text-center flex-wrap pb-2">
            <div>
              <h5><b class="timer-d"> {{ $diff_d }} </b></h5>
              <h6 class="opacity-50">Days</h6>
            </div>
            <div>
              <h5><b class="timer-h"> {{ $diff_H }} </b></h5>
              <h6 class="opacity-50">Hrs</h6>
            </div>
            <div>
              <h5><b class="timer-m"> {{ $diff_I }} </b></h5>
              <h6 class="opacity-50">Mins</h6>
            </div>
            <div>
              <h5><b class="timer-s"> {{ $diff_S }} </b></h5>
              <h6 class="opacity-50">Secs</h6>
            </div>
          </div>
        @endif
        @php
          $lowest_bid_price = @$auction->bids->min('price') ?? @$auction->min_price;
          $lowest_bid_price = $lowest_bid_price < @$auction->min_price ? $lowest_bid_price : @$auction->min_price;
          $lowest_bidder = @$auction->bids->where('price', $lowest_bid_price)->first();
          $my_bid = @$auction->bids->where('user_id', $auth_id)->first();
        @endphp
        @if (@$auction->user_id != $auth_id)
          <a href="{{ route('auction-chat', ['agent-service', $auction->id]) }}" class="btn btn-success w-100 mb-2"> <i
              class="fa-solid fa-paper-plane"></i> Send Message</a>
        @endif
        @if ($auth_id)
          @if (in_array(auth()->user()->user_type, ['agent']))
            <button class="btn w-100"
              onclick="javascript:window.location='{{ route('agent.service.auction.bid.add', @$auction->id) }}';"
              {{ $my_bid || @$auction->user_id == $auth_id ? 'disabled' : '' }}>
              <span class="bid">Bid Now </span>
              <span class="badge bg-light float-end text-dark">
                @if (@$auction->get->service_type != 'Referral agent Service')
                  {{-- ${{ number_format(@$auction->get->offerer_commission) }} @if (@$auction->get->offerer_commission_negotiable == 'Yes') --}}
                  ${{ @$auction->get->offerer_commission }} @if (@$auction->get->offerer_commission_negotiable == 'Yes')
                    negotiable
                  @endif
                @endif
                @if (@$auction->get->service_type == 'Referral agent Service')
                  {{ @$auction->get->custom_referral_fee == '' ? @$auction->get->referral_fee : @$auction->get->custom_referral_fee }}
                @endif
              </span>
              @if (@$auction->sold)
                <span class="badge bg-danger">Sold</span>
              @endif
              {{-- {{$res}} --}}
            </button>
          @endif
        @else
          <a href="{{ route('login') }}">
            <button class="btn w-100">
              <span class="bid">Login for Bid </span>
              <span
                class="badge bg-light float-end text-dark">${{ number_format($lowest_bid_price, 2, '.', ',') }}</span>
            </button>
          </a>
        @endif
        <!-- Highest Bider   -->
        <div class="card higestBider">
          <div class="card-body">
            @if ($lowest_bidder)
            @else
              <p>No one has bid on this auction.</p>
            @endif
            <div class="accordion" id="accordionExample">
              <div class="accordion-item border-0">
                @foreach (@$auction->bids as $bid)
                  <!-- Item loop -->
                  <div class="accordion" type="button" data-bs-toggle="collapse"
                    data-bs-target="#item{{ $bid->id }}" aria-expanded="true"
                    aria-controls="item{{ $bid->id }}">
                    <div class="d-flex small accordion mr-0 text-center">
                      <div class="col-1">
                        <span class="badge">{{ $loop->iteration }}</span>
                      </div>
                      <div class="col-4">
                        {{ $bid->user->name }} </div>
                      <div class="col-4 text-right">
                        @if (@$auction->get->service_type != 'Referral agent Service')
                          $
                        @endif
                        {{-- @dd($bid->price) --}}
                        {{ number_format($bid->price, 0, '.', '') }}
                        @if (@$auction->get->service_type == 'Referral agent Service')
                          %
                        @endif
                      </div>
                      <div class="col-2">
                        Terms↓
                      </div>
                    </div>
                  </div>
                  <div id="item{{ $bid->id }}" class="accordion-collapse collapse" aria-labelledby="headingOne"
                    data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                      <div id="bidding_history_data">
                        <div>
                          <p class="d-flex justify-content-between small">First Name:
                            <span>{{ $bid->get->agent_first_name }}</span>
                          </p>
                          @if (@$auction->get->service_type == 'Referral agent Service')
                            <p class="d-flex justify-content-between small">Offering Referral Price:
                              <span>{{ $bid->get->agent_fee }}%</span>
                            </p>
                          @else
                            <p class="d-flex justify-content-between small">Acceptable Compensation:
                              <span>${{ $bid->get->agent_fee }}</span>
                            </p>
                          @endif
                          @if (@$auction->user_id == $auth_id)
                            @if (!@$auction->is_sold)
                              <form action="{{ route('agent.acceptASBid') }}" method="post">
                                @csrf
                                <input type="hidden" name="auction_id" value="{{ @$auction->id }}">
                                <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                <div style="text-align: right;">
                                  <button type="submit" class="btn btn-success btn-sm">Accept</button>
                                </div>
                              </form>
                            @endif
                          @endif
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- End  -->
                @endforeach
              </div>
            </div>
          </div>
        </div>
        <button class="btn w-100 mt-0">
          <span class="bid m-0"><i class="fa fa-user"></i> </span>
        </button>
        <!-- End  -->
        <!-- Social Details  -->
        <div class="p-4 card">
          <p class="text-600">Share this link via</p>
          <div class="qr-code" style="width: 100%; height:200px;">
            {{ qr_code(route('agent.service.auction.view', @$auction->id), 200) }}
          </div>
          <div class="card-social">
            <ul class="icons">
              <a href="">
                <i class="fab fa-facebook-f"></i>
              </a>

              <a href="">
                <i class="fab fa-twitter"></i>
              </a>
              <a href="">
                <i class="fab fa-instagram"></i>
              </a>
              <a href="">
                <i class="fab fa-pinterest"></i>
              </a>
              <a href="">
                <i class="fab fa-linkedin"></i>
              </a>
            </ul>
            <p class="small opacity-8">Or copy link</p>
            <div class="field">
              <i class="fa fa-link"></i>
              <input type="text" readonly="" id="copylink"
                value="https://bidyouroffer.com/listing/534-pinellas-bayway-s-204-tierra-verde-fl-33715-4/">
              <button class="btn-primary btn-sm text-600 js-copy-link text-center border-0"
                style="min-width:60px;">Copy</button>
            </div>

          </div>
        </div>
        <!-- End  -->
      </div>
    </div>
  </div>
  <hr>
  <!-- Recommmended Section  -->
  <div class="container buyerOfferContentDetails">
    <h3 class="text-600 mb-4">Recommended For You</h3>
    <div class="cardsDetails row  justify-content-start">
      <!-- Card 1 -->
      <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
        <div class="card ">
          <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
            class="card-img-top" alt="...">
          <div class="card-body pb-2 pt-2">
            <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
            <div class="houseDetails mb-1">
              <span>
                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                    src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}" alt="bed icon" width="15"><b>
                    4</b></span>
                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                    src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}" alt="bed icon" width="15"><b>
                    2</b></span>
                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                    src="{{ asset('assets/fontawesome/svgs/thin/ruler-triangle.svg') }}" alt="bed icon"
                    width="15"><b> 1,643 </b>Sq Ft</span>
              </span>
              - House for sale
            </div>
            <p class="card-text mb-1"><span class="badge bg-secondary">land/lots</span> <span
                class="float-end"><span><b>MLS ID</b></span> <span>#12345</span></span></p>
            <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg><b>28d 03:15:29</b></p>
          </div>
          <div class="card-footer bg-light">
            <div class="row">
              <div class="col-6 left">
                <!-- Barcode  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                  </path>
                </svg>
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                  </path>
                </svg>
                <!-- FAvourite  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                  </path>
                </svg>
              </div>
              <div class="col-6 right text-end">
                <b>$1,000</b>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Card 2 -->
      <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
        <div class="card ">
          <img src="https://bidyouroffer.com/wp-content/uploads/2022/10/165522238955562a8b07535346697508007-300x200.jpg"
            class="card-img-top" alt="...">
          <div class="card-body">
            <h5 class="card-title"><a href="">1199 Randall Way, Brownsburg, IN 46112 </a></h5>
            <div class="houseDetails">
              <span>
                <span><b>4</b> bds</span>
                <span><b>2</b> ba</span>
                <span><b>1,643</b> sqft</span>
              </span>
              - House for sale
            </div>
            <p class="card-text"><span class="badge bg-secondary">land/lots</span> <span class="float-end"><span><b>MLS
                    ID</b></span> <span>#12345</span></span></p>
          </div>
          <div class="card-footer bg-light">
            <div class="row">
              <div class="col-6 left">
                <!-- Barcode  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                  </path>
                </svg>
                <!-- Message  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                  </path>
                </svg>
                <!-- FAvourite  -->
                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus"
                  data-bs-placement="top" data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg"
                  fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                  </path>
                </svg>
              </div>
              <div class="col-6 right text-end">
                <b>$1,000</b>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

@endsection

@push('scripts')
  {{-- <script src="{{asset('assets/bootstrap-5.2.2/js/twitter-bootstrap.min.js')}}"></script> --}}
  <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous"
    referrerpolicy="no-referrer"></script>
  <script>
    var durations = '{{ $diff_d }}d{{ $diff_H }}h{{ $diff_I }}m{{ $diff_S }}s';
    $('.timer-d').timer({
      countdown: true,
      duration: durations,
      format: '%d'
    });
    $('.timer-h').timer({
      countdown: true,
      duration: durations,
      format: '%h'
    });
    $('.timer-m').timer({
      countdown: true,
      duration: durations,
      format: '%m'
    });
    $('.timer-s').timer({
      countdown: true,
      duration: durations,
      format: '%s'
    });
  </script>
  <script>
    $(function() {
      $('.add-row').on('click', function() {
        var socialRow = `<tr>
                                    <td>
                                        <select name="socialType[]" class="form-select">
                                            <option value="Facebook">Facebook</option>
                                            <option value="YouTube">YouTube</option>
                                            <option value="LinkedIn">LinkedIn</option>
                                            <option value="Twitter">Twitter</option>
                                            <option value="Instagram">Instagram</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="social_link[]" class="form-control">
                                    </td>
                                </tr>`;
        $('.social-links').append(socialRow);
      });
    });
  </script>
@endpush
