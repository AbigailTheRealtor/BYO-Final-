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

        /* .fa-dollar,
            .fa-percent {
                padding: 0 20px;
                background: #facd34;
                color: #fff;
                border: 0;
                font-weight: 700 !important;
                line-height: 39px !important;
                margin-right: -5px;
                z-index: 1;
                border-radius: 3px 0 0 3px;
            }

            .form-control,
            .form-select {
                border-radius: 0.25rem;
                box-shadow: inset 0 1px 2px 0 rgb(66 71 112 / 12%);
                border-radius: 0.25rem;
                background-color: #fafafb;
                margin-bottom: 15px;
            } */
    </style>
@endpush

@section('content')
    @inject('carbon', 'Carbon\Carbon')
    @inject('serviceModel', 'App\Models\SellerService')
    @php
        $auth_id = auth()->user() ? auth()->user()->id : 0;
    @endphp
    <!-- Gallery Start Here  -->
    <div class="container listingDescription">
        <div class="row">
            <div class="col-sm-12 col-md-8 col-lg-8 leftCol">
                <!-- Description Box  -->
                <div class="card description">
                    @if ($auction->video)
                        <div class="row">
                            <div class="col-md-12">
                                <video class="video-fluid" controls playsinline muted style="width: 100%;">
                                    <source src="{{ asset('auction/videos/' . $auction->video) }}" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                    @endif

                    @if ($auction->audio)
                        <div class="row">
                            <div class="col-md-12">

                                <audio class="audio-fluid" style="width: 100%;" controls>
                                    <source src="{{ asset('auction/audios/' . $auction->audio) }}" type="audio/mp3">
                                    Your browser does not support the audio tag.
                                </audio>
                            </div>
                        </div>
                    @endif

                    @if ($auction->note)
                        <div class="row">
                            <div class="col-md-12 p-4">
                                <a href="{{ asset('auction/audios/' . $auction->audio) }}" class="btn btn-primary">
                                    Download Note
                                </a>
                            </div>
                        </div>
                    @endif

                    <div class="card-header">
                        <h5>Description</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">{{ $auction->description }}</p>
                        <hr />
                        <h4>Features</h4>
                        <div class="row" style="flex-wrap: wrap;">
                            <div class="col-md-6 col-6 pt-2 fw-bold"><i class="fa fa-dot-circle-o"></i> City:
                                {{ @$auction->city->name }} </div>
                            <div class="col-md-6 col-6 pt-2 fw-bold"><i class="fa fa-dot-circle-o"></i> County:
                                {{ @$auction->county->name }} </div>
                            {{-- <div class="col-md-6 col-6 pt-2 fw-bold"><i class="fa fa-dot-circle-o"></i> State: {{ @$auction->state->name }} </div> --}}


                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa fa-money"></i> Price Seller is
                                willing to accept:
                                ${{ number_format($auction->price, 0, '.', '') }}
                            </div>
                            <div class="col-md-12 col-12 pt-2 fw-bold"><i class="fa fa-dot-circle-o"></i> When does the
                                service need to take place?
                                {{ $carbon::parse($auction->required_at)->format('M d, Y h:i a') }} </div>
                        </div>
                        <hr>
                        <h4>Services:</h4>
                        <ol>
                            @if ($auction->services)
                                @foreach (json_decode($auction->services) as $service_id)
                                @if($serviceModel::find($service_id))
                                    <li>{{ $serviceModel::find($service_id)->name }}</li>
                                @endif
                                @endforeach
                            @endif
                        </ol>
                        {{-- <hr />
                        <h4>Public Note</h4>
                        <div class="row" style="flex-wrap: wrap;">
                            <p>{{ $auction->public_notes }}</p>
                        </div> --}}
                        <hr />
                        <h4>Seller Info</h4>
                        <div class="row" style="flex-wrap: wrap;">
                            <div class="col-md-6 col-6 pt-2 fw-bold">Name: {{ $auction->seller_name }}</div>
                            {{-- <div class="col-md-6 col-6 pt-2 fw-bold">Brokerage: {{ $auction->agent_brokerage }}</div> --}}
                            {{-- <div class="col-md-6 col-6 pt-2 fw-bold">License No.: {{ $auction->agent_license_no }}</div> --}}
                            <div class="col-md-6 col-6 pt-2 fw-bold">Phone: {{ $auction->seller_phone }}</div>
                            <div class="col-md-6 col-6 pt-2 fw-bold">Email: {{ $auction->seller_email }}</div>
                            {{-- <div class="col-md-6 col-6 pt-2 fw-bold">MLD ID: {{ $auction->agent_mls_id }}</div> --}}
                        </div>

                        @if ($auction->bids && $auction->bids->where('accepted', true)->count())
                            <hr />
                            <h4>Private Notes</h4>
                            <p>{{ $auction->private_notes }}</p>
                            <div class="col-md-6 col-6 pt-2 fw-bold">Information on the person the client is meeting:</div>
                            <p>{{ $auction->meeting_info }}</p>
                            <div class="col-md-6 col-6 pt-2 fw-bold">Address of the property: {{ $auction->address }}</div>
                        @endif



                    </div>
                </div>
                <!-- End  -->
                <!-- Location Box  -->
                {{-- <div class="card location">
                    <div class="card-header">
                        <h5>Location</h5>
                    </div>
                    <div class="card-body">
                        <div class="locationMap position-relative">
                            <img class="w-100"
                                src="https://bidyouroffer.com/wp-content/uploads/2022/09/map-placeholder.jpg"
                                alt="">
                            <div class="right position-absolute">
                                <button class="btn btn-sm"><i class="fa fa-map-marker"></i> View Map</button>
                                <button class="btn btn-sm">Get Direction</button>

                            </div>
                        </div>
                        <br>
                        <p><b>{{ $auction->address }}</b></p>
                    </div>
                </div> --}}
                <!-- End  -->

                @inject('auctionUser', 'App\Models\User')
                @php
                    $auser = $auctionUser::find($auction->user_id);
                @endphp
                <!-- Review  -->
                <div class="card review">
                    <div class="card-body d-flex align-items-center">
                        <div class="left d-flex align-items-center">
                            <img class="w-25"
                                src="{{ $auser->avatar ? asset('images/avatar/' . $auser->avatar) : 'https://ppt1080.b-cdn.net/images/avatar/none.png' }}"
                                alt="">
                            <div>
                                <p class="mb-0"><a href="{{ route('author', [$auction->user_id]) }}"><b>User
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
                {{-- <h1>{{ $auction->service->name }}</h1> --}}
                {{-- <hr> --}}

                @php
                    if ($auction->auction_length != "-1") {
                        $start = $carbon::now();//parse(db_time());
                        if (strpos($auction->auction_length, 'M') !== false) {
                            $mins = explode('M', $auction->auction_length);
                            $end = $carbon::parse($auction->created_at)->addMinutes($mins[0]);
                        } else {
                            $end = $carbon::parse($auction->created_at)->addDays($auction->auction_length);
                        }
                        $diff = $end->diffInDays($start);
                    }
                @endphp
                @if ($auction->auction_length != "-1")
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
                    $lowest_bid_price = $auction->bids->min('price') ?? $auction->price;
                    $lowest_bid_price = $lowest_bid_price < $auction->price ? $lowest_bid_price : $auction->price;
                    $lowest_bidder = $auction->bids->where('price', $lowest_bid_price)->first();
                    $my_bid = $auction->bids->where('user_id', $auth_id)->first();
                @endphp
                @if ($auth_id)
                    @if (in_array(auth()->user()->user_type, ['agent']))
                        <button class="btn w-100" data-bs-toggle="modal" data-bs-target="#exampleModal"
                            {{ $my_bid || $auction->user_id == $auth_id ? 'disabled' : '' }}>
                            <span class="bid">Bid Now </span>
                            <span
                                class="badge bg-light float-end text-dark">${{ number_format($auction->price, 0, '.', '') }}</span>
                            @if ($auction->sold)
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
                                class="badge bg-light float-end text-dark">${{ number_format($lowest_bid_price, 0, '.', '') }}</span>
                        </button>
                    </a>
                @endif
                <!-- Highest Bider   -->
                <div class="card higestBider">
                    <div class="card-body">
                        @if ($lowest_bidder)
                            {{-- <p><b>{{ $lowest_bidder->user->name ?? '' }}</b> is the Lowest bidder.</p> --}}
                        @else
                            <p>No one has bid on this auction.</p>
                        @endif
                        <div class="accordion" id="accordionExample">
                            <div class="accordion-item border-0">
                                @foreach ($auction->bids as $bid)
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
                                                ${{ number_format($bid->price, 0, '.', '') }} </div>
                                            <div class="col-2">
                                                Terms↓
                                            </div>
                                        </div>
                                    </div>
                                    <div id="item{{ $bid->id }}" class="accordion-collapse collapse"
                                        aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                        <div class="accordion-body">
                                            <div id="bidding_history_data">
                                                <div>
                                                    <p class="d-flex justify-content-between small">Brokerage:
                                                        <span>${{ $bid->brokerage }}</span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small">License No.:
                                                        <span>{{ $bid->license_no }}</span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small">Phone:
                                                        <span>{{ $bid->phone }}</span>
                                                    </p>
                                                    <p class="d-flex justify-content-between small">Email:
                                                        <span>{{ $bid->email }}</span>
                                                    </p>
                                                    {{-- <p class="d-flex justify-content-between small">MLS ID:
                                                        <span>{{ $bid->mls_id }}</span>
                                                    </p> --}}
                                                    <p class="d-flex justify-content-between small">Offering Price:
                                                        <span>{{$bid->price_in=="$"?"$":""}}{{ number_format($bid->price, 0, '.', '') }}{{$bid->price_in=="%"?"%":""}}</span>
                                                    </p>

                                                    <p class="d-flex justify-content-between small">Additional Details:
                                                        <span>{{ $bid->additional_details }}</span>
                                                    </p>


                                                    @if($bid->other)
                                                        @php
                                                            $other = json_decode($bid->other);
                                                        @endphp
                                                        @if($other->website_link)

                                                            <p class="d-flex justify-content-between small">Website Link:
                                                                <a href="{{$other->website_link}}" target="_blank">{{ $other->website_link }}</a>
                                                            </p>
                                                        @endif

                                                        @if($other->reviews_link)
                                                            <p class="d-flex justify-content-between small">Reviews Link:
                                                                <a href="{{$other->reviews_link}}" target="_blank">{{ $other->reviews_link }}</a>
                                                            </p>
                                                        @endif

                                                        @if($other->social_link)
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th colspan="2" class="text-center">Social Media Platforms</th>
                                                                </tr>
                                                                <tr>
                                                                    <th>Type</th>
                                                                    <th>Link</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($other->social_link as $social)
                                                                    <tr>
                                                                        <td>{{$other->socialType[$loop->iteration-1]}}</td>
                                                                        <td><a href="{{$social}}" target="_blank">{{$social}}</a></td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                        @endif
                                                    @endif


                                                    @if ($auction->user_id == $auth_id)
                                                        <p class="d-flex justify-content-between small">
                                                            <img src="{{ asset('auction/bid/cards/' . $bid->card) }}"
                                                                controls style="width: 200px;" />
                                                        </p>
                                                    @endif

                                                    @if ($auction->user_id == $auth_id)
                                                        @if($bid->video_url)
                                                        <p>
                                                            <a href="{{$bid->video_url}}" class="btn btn-sm btn-primary" target="_blank">View Video</a>
                                                        </p>
                                                        @endif
                                                        @if($bid->video)
                                                        <p class="d-flex justify-content-between small">
                                                            <video src="{{ asset('auction/videos/' . $bid->video) }}"
                                                                controls width="100%"></video>
                                                        </p>
                                                        @endif
                                                    @endif
                                                    @if ($auction->user_id == $auth_id)
                                                        <p class="d-flex justify-content-between small">
                                                            <audio class="audio-fluid" controls style="width: 100%;">
                                                                <source src="{{ asset('auction/audios/' . $bid->audio) }}"
                                                                    type="audio/mp3">
                                                                Your browser does not support the audio tag.
                                                            </audio>
                                                        </p>
                                                    @endif

                                                    @if ($auction->user_id == $auth_id)
                                                        <p class="d-flex justify-content-between small">
                                                            <a href="{{ asset('auction/files/' . $bid->note) }}"
                                                                class="btn btn-secondary btn-sm" download
                                                                style="width: 100%;">Download Note
                                                            </a>
                                                        </p>
                                                    @endif

                                                    @if ($auction->user_id == $auth_id)
                                                        @if (!$auction->is_sold)
                                                            <form
                                                                action="{{ route('seller.service.auction.bid.accept') }}"
                                                                method="post">
                                                                @csrf
                                                                <input type="hidden" name="auction_id"
                                                                    value="{{ $auction->id }}">
                                                                <input type="hidden" name="bid_id"
                                                                    value="{{ $bid->id }}">
                                                                <div style="text-align: right;">
                                                                    <button type="submit"
                                                                        class="btn btn-success btn-sm">Accept</button>
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
                                        src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}" alt="bed icon"
                                        width="15"><b>
                                        4</b></span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                        src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}" alt="bed icon"
                                        width="15"><b>
                                        2</b></span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                        src="{{ asset('assets/fontawesome/svgs/thin/ruler-triangle.svg') }}"
                                        alt="bed icon" width="15"><b> 1,643 </b>Sq Ft</span>
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
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Scan Qr Code"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
                                <!-- <div hidden class="w-50">
                                            <div id="qr-content" class="text-center w-75 m-auto">
                                              <img class="w-50" src="https://www.freepnglogos.com/uploads/qr-code-png/qr-code-file-bangla-mobile-code-0.png" alt="">
                                              <p>Scan QR code to view on mobile device.</p>
                                            </div>
                                          </div> -->
                                <!-- Message  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Send Message"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                    </path>
                                </svg>
                                <!-- FAvourite  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Add Favorites"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
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
                        <p class="card-text"><span class="badge bg-secondary">land/lots</span> <span
                                class="float-end"><span><b>MLS ID</b></span> <span>#12345</span></span></p>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="row">
                            <div class="col-6 left">
                                <!-- Barcode  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Scan Qr Code"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                    </path>
                                </svg>
                                <!-- Message  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Send Message"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                    </path>
                                </svg>
                                <!-- FAvourite  -->
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                    data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Add Favorites"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
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


    <!-- Modal -->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('agent.saveSSBid') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="auction_id" value="{{ $auction->id }}">
                    <div class="modal-body">
                        <div class="d-flex justify-content-between mb-2">
                            <div style="font-size:18px; font-weight:bold;">
                                ${{ number_format($auction->price, 0, '.', '') }}</div>
                            <div style="color: rgba(0,0,0,0.5);">Price Offered</div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row form-group">
                                    <div class="col-md-6 mb-2">
                                        <label>Name: <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" required
                                            value="{{ old('name') }}" />
                                        @if ($errors->has('name'))
                                            <div class="small error">{{ $errors->first('name') }}</div>
                                        @endif
                                    </div>



                                    <div class="col-md-6 mb-2">
                                        <label>Phone Number:</label>
                                        <input type="text" class="form-control" name="phone"
                                            value="{{ old('phone') }}" />
                                    </div>



                                    <div class="col-md-6 mb-2">
                                        <label>Email:</label>
                                        <input type="email" class="form-control" name="email"
                                            value="{{ old('email') }}" />
                                    </div>


                                    <div class="col-md-6 mb-2">
                                        <label>Brokerage: <span class="text-danger">*</span></label>
                                        {{-- <div class="input-group">
                                            <div class="input-group-text">
                                                <i class="fa fa-dollar"></i>
                                            </div> --}}
                                            <input type="text" class="form-control" name="brokerage"
                                                id="brokerage" required
                                                value="{{ old('brokerage') }}">
                                        {{-- </div> --}}
                                        @if ($errors->has('brokerage'))
                                            <div class="small error">{{ $errors->first('brokerage') }}</div>
                                        @endif
                                    </div>



                                    <div class="col-md-6 mb-2">
                                        <label>Real Estate License #: <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="license_no" required
                                            value="{{ old('license_no') }}" />
                                        @if ($errors->has('license_no'))
                                            <div class="small error">{{ $errors->first('license_no') }}</div>
                                        @endif
                                    </div>



                                    <div class="col-md-6 mb-2">
                                        <label>Offering Price: $ or % <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-text p-0">
                                                <select name="price_in" class="form-control"
                                                    style="background-color: #e9ecef; border:0;">
                                                    <option value="$">$</option>
                                                    <option value="%">%</option>
                                                </select>
                                            </div>
                                            <input type="number" class="form-control border-start-0" name="price"
                                                id="price" min="0" required placeholder="0.00" required
                                                value="{{ old('price') }}">
                                        </div>
                                        @if ($errors->has('price'))
                                            <div class="small error">{{ $errors->first('price') }}</div>
                                        @endif
                                    </div>



                                </div>


                                {{-- <div class="row form-group d-none">

                                    <div class="col-md-6">
                                        <label>MLS ID: <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="mls_id"
                                            value="{{ old('mls_id') }}" />
                                        @if ($errors->has('mls_id'))
                                            <div class="small error">{{ $errors->first('mls_id') }}</div>
                                        @endif
                                    </div>
                                </div> --}}


                                {{-- <div class="row form-group d-none">
                                    <div class="col-md-6 d-none">
                                        <label>County the agent is in:</label>
                                        <select class="form-control" name="county_id">
                                            @foreach ($counties as $county)
                                                <option value="{{ $county->id }}"
                                                    {{ old('county_id') == $county->id ? 'selected' : '' }}>
                                                    {{ $county->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                </div> --}}

                                {{-- <div class="row form-group">
                                    <div class="col-md-6">
                                        <label>Offering Price %: <span class="text-danger">*</span></label>
                                        <div class="d-flex align-items-baseline">
                                            <i class="fa fa-percent"></i>
                                            <input type="number" class="form-control border-start-0"
                                                name="price_percent" id="price_percent" min="0" max="100"
                                                required placeholder="0.00" value="{{ old('price_percent') }}">
                                        </div>
                                        @if ($errors->has('price_percent'))
                                            <div class="small error">{{ $errors->first('price_percent') }}</div>
                                        @endif
                                    </div>
                                </div> --}}

                            </div>

                            <div class="form-group mb-2">
                                <label>Any additional details agent wants to include:</label>
                                <textarea class="form-control" name="additional_details">{{ old('additional_details') }}</textarea>
                            </div>



                            <div class="col-md-12">

                                <div class="row">
                                    <div class="form-group col-md-6 mb-2">
                                        <label>Website link:</label>
                                        <div class="d-flex align-items-baseline">
                                            <input type="text" class="form-control" name="website_link">
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 mb-2">
                                        <label>Reviews link:</label>
                                        <div class="d-flex align-items-baseline">
                                            <input type="text" class="form-control" name="reviews_link">
                                        </div>
                                    </div>

                                    <div class="form-group col-md-12 mb-2">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th colspan="2" class="text-center">Social Media Platforms</th>
                                                </tr>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Link</th>
                                                </tr>
                                            </thead>
                                            <tbody class="social-links">
                                                <tr>
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
                                                </tr>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="2" class="text-right">
                                                        <a class="btn btn-primary add-row">Add New Row</a>
                                                    </th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">

                                <div class="row">
                                    <div class="form-group col-md-6 mb-2">
                                        <div>
                                            <label>Business Card:</label>
                                            <div class="d-flex align-items-baseline">
                                                <input type="file" class="form-control" name="card">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 mb-2 d-none">
                                        <div>
                                            <label>Video:</label>
                                            <div class="d-flex align-items-baseline">
                                                <input type="file" class="form-control" name="video">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 mb-2">
                                        <label>Video URL:</label>
                                        <div class="d-flex align-items-baseline">
                                            <input type="text" class="form-control" name="video_url">
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 mb-2">
                                        <label>Audio:</label>
                                        <div class="d-flex align-items-baseline">
                                            <input type="file" class="form-control" name="audio">
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 mb-2">
                                        <label>Note:</label>
                                        <div class="d-flex align-items-baseline">
                                            <input type="file" class="form-control" name="note">
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </div>
                        <div style="text-align: right;">
                            <button type="submit" class="btn btn-secondary">Bid Now</button>
                        </div>
                    </div>
                </form>
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
        $(function () {
            $('.add-row').on('click', function(){
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
