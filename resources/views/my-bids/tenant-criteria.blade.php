@extends('my-bids.layout')
@section('bids-content')
    <div class="container mt-5 myBid">
        <!-- Section 1  -->
        {{-- <select class="form-select mt-4 mb-3 w-25">
            <option value="all">All (6)</option>
            <option value="3">Won/Sold (2)</option>
            <option value="2">Lost (2)</option>
            <option value="1">Bidding (2)</option>
            <option value="4">Finished (0)</option>
        </select> --}}
        <!-- End  -->
        <div class="accordion" id="accordionExample">
            <!-- Section 2 -->
            @foreach ($bids as $bid)
                <div class="card p-3 mb-3" data-bs-toggle="collapse" data-bs-target="#collapse{{$loop->iteration}}" aria-expanded="true"
                    aria-controls="collapse{{$loop->iteration}}">
                    <div class="row myBidsDetails">
                        <div class="col-12 col-md-8 col-lg-8">
                            <div class="fw-bold">{{ $bid->auction->get->property_type }}</div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="fw-bold">${{ $bid->get->price }}
                                <span class="text-sm-end opacity-5 badge bg-secondary">Current price</span>
                                {{-- <span class="text-sm-end badge">buying</span> --}}
                            </div>
                        </div>
                        {{-- <div class="col text-center fw-bold">
                            <div class="fw-bold">
                                <span class="d-inline-flex hasCountdown">
                                    <span>25d </span>
                                    <span>06</span>
                                    <span>:</span>
                                    <span>22</span>
                                    <span>:</span>
                                    <span>45</span>
                                </span>
                            </div>
                            <div class="text-sm-center opacity-50">Time left</div>
                        </div> --}}
                    </div>
                </div>
                <!-- SubSection  -->
                <div id="collapse{{$loop->iteration}}" class="accordion-collapse collapse" data-bs-parent="#accordionExample">
                    <div class="accordion-body p-0">
                        <div class="row">
                            @if ($bid->auction->is_sold == 0)
                                <!-- 1 -->
                                <div class="col-md-12">
                                    <div class="bg-white p-3 mb-4 card">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <div class="float-start me-4 ms-2 pb-3 mt-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                                                        </path>
                                                    </svg>
                                                </div>
                                                <div class="fs-5 fw-bold">Waiting for auction to end</div>
                                                <div class="text-muted mt-2 fs-7">Please wait for the
                                                    auction to finish. </div>
                                            </div>
                                            <div class="col-md-4">
                                                <button class="w-75" type="button"><i
                                                        class="fa fa-spinner fa-spin text-light me-2"></i>
                                                    Waiting </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <!-- 2 -->
                            <div class="col-12">
                                <div class="p-3 p-md-4 bg-light mb-4">
                                    <div class="fs-md pb-3 fs-4 fw-bolder">Details</div>
                                    <div class="row">
                                        <div class="col-12 col-md-6">
                                            <ul class="p-0 left">
                                                <li>
                                                    <div class="left">
                                                        Seller:
                                                    </div>
                                                    <div class="right"><span>
                                                            <a href="{{route('author', $bid->auction->user->id)}}"
                                                                class="border-0 bg-transparent text-primary">{{$bid->auction->user->name}}</a>
                                                        </span> </div>
                                                </li>
                                                {{-- <li>
                                                    <div class="left">
                                                        Cash or Financing Type :
                                                    </div>
                                                    <div class="right">
                                                        DASASD </div>
                                                </li>
                                                <li>
                                                    <div class="left">
                                                        Escrow Amount :
                                                    </div>
                                                    <div class="right">
                                                        ADSASDD </div>
                                                </li>
                                                <li>
                                                    <div class="left">
                                                        Closing Date :
                                                    </div>
                                                    <div class="right">
                                                        ASDASDSA </div>
                                                </li>
                                                <li>
                                                    <div class="left">
                                                        Inspection Period :
                                                    </div>
                                                    <div class="right">
                                                        DASDSA </div>
                                                </li>
                                                <li>
                                                    <div class="left">
                                                        Contingencies :
                                                    </div>
                                                    <div class="right">
                                                        DASD </div>
                                                </li>
                                                <li>
                                                    <div class="left">
                                                        <a href="/" data-ppt-btn="" class="btn-sm"> View Profile</a>
                                                    </div>
                                                    <div class="right">
                                                        <a href="/" class="btn-sm">Send
                                                            Message</a>
                                                    </div>
                                                </li>
                                                <li>
                                                    <div class="left">Order Total:</div>
                                                    <div class="right"><span>$902,000</span></div>

                                                </li>
                                                <li>
                                                    <div class="left">Payment Status:</div>
                                                    <div class="right"> Pending</div>
                                                </li> --}}
                                            </ul>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <ul class="p-0">
                                                <li>
                                                    <div class="left">Property Type:</div>
                                                    <div class="right"> <a href="{{route('tenant.criteria.auction.view', $bid->auction->id)}}" target="_blank">{{$bid->auction->get->property_type}}</a> </div>
                                                </li>
                                                <li>
                                                    <div class="left">My Bid:</div>
                                                    <div class="right">
                                                        <span>${{number_format($bid->get->price)}}</span>
                                                    </div>
                                                </li>

                                                {{-- <li>
                                                    <div class="left">Current Price:</div>
                                                    <div class="right">
                                                        <span>${{$bid->auction->bids->max('price')}}</span>
                                                    </div>
                                                </li> --}}
                                                {{-- <li>
                                                    <div class="left">Offer Date:</div>
                                                    <div class="right">
                                                        <span>October 6, 2022 5:42 pm</span>
                                                    </div>
                                                </li>

                                                <li>
                                                    <div class="left">Hightest Bidder:</div>
                                                    <div class="right">
                                                        Aiden Samuel </div>
                                                </li>
                                                <li>
                                                    <div class="left">Auction Ends:</div>
                                                    <div class="right">
                                                        <span>
                                                            <div><span>25d
                                                                </span><span>04</span><span>:</span><span>41</span><span>:</span><span>56</span>
                                                            </div>
                                                        </span>
                                                    </div>
                                                </li> --}}
                                            </ul>
                                        </div>
                                    </div>
                                    <div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End  -->
            @endforeach
            <!-- End  -->
        </div>

    </div>
@endsection
