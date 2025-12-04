@extends('layouts.main')
@push('styles')

@endpush
@section('content')
<div class="container">
    @include('layouts.partials.search_menu')
</div>
<div class="buyerOfferContentDetails">
    <div class="container">
        <p><span><b>Explore</b></span> <span><i>{{$count}} results</i></span></p>
      <div class="selectionRoom row">
        <div class="col-sm-12 col-md-3 col-lg-3">
          <div class="mb-3">
            <select id="disabledSelect" class="form-select">
              <option>City, County, ZIP, Address...</option>
            </select>
          </div>
        </div>
        <div class="col-sm-12 col-md-3 col-lg-3">
          <div class="mb-3">
            <select id="disabledSelect" class="form-select">
              <option>Bedrooms</option>
            </select>
          </div>
        </div>
        <div class="col-sm-12 col-md-3 col-lg-3">
          <div class="mb-3">
            <select id="disabledSelect" class="form-select">
              <option>Bathrooms</option>
            </select>
          </div>
        </div>
        <div class="col-sm-12 col-md-3 col-lg-3">
          <div class="mb-3">
            <select id="disabledSelect" class="form-select">
              <option>Property Types</option>
            </select>
          </div>
        </div>
      </div>
      <div class="buyerOfferSearchMenu d-flex justify-content-between flex-wrap text-center">
        <div class="left d-flex align-items-center mb-3 flex-wrap">
            <div class="form-check form-control-lg form-switch">
              <input class="form-check-input" type="checkbox" id="buyNow" checked>
              <label class="form-check-label" for="buyNow">Buy Now</label>
          </div>
          <div class="form-check form-control-lg form-switch">
            <input class="form-check-input" type="checkbox" id="hasBid" checked>
            <label class="form-check-label" for="hasBid">Has Bids</label>
          </div>
          <div class="form-check form-control-lg form-switch p-0">
            <i class="fa fa-heart"></i>
            <label class="form-check-label" for="flexSwitchCheckChecked">Favorites</label>
          </div>
        </div>
        <div class="right d-flex align-items-center mb-3 flex-wrap">
          <!-- Select Box  -->
          <select name="selection" class="sortby">
            <option value="">Most Relevant</option>
            <option value="title">Title: (Z-a)</option>
            <option value="title">Title: (A-z)</option>
            <option value="id">Date: (New)</option>
            <option value="id">Date: (Old)</option>
            <option value="price">Price: (Highest)</option>
            <option value="price">Price: (Lowest)</option>
            <option value="expiry">Ending: (Last)</option>
            <option value="expiry">Ending: (Soon)</option>
            <option value="distance">Distance: (Furthest)</option>
            <option value="distance">Distance: (Closest)</option>
            <option value="hits">Popular: (Most)</option>
            <option value="hits">Popular: (Least)</option>
            <option value="update">Updated: (New)</option>
            <option value="update">Updated: (Old)</option>
          </select>
          <!-- Grid view  -->
          <!-- <button> -->
            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Grid View" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
          <!-- </button> -->

          <!-- Menu  -->
          <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="View Menu"  xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
          <!-- Location  -->
          <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="View Location"  xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        </div>
      </div>
      <div class="cardsDetails row  justify-content-start">

        @inject('carbon', 'Carbon\Carbon')
        @forelse ($pAuctions as $auction)
        @inject('sellerServiceModel', 'App\Models\SellerService')
        <div class="col-sm-6 col-md-12 col-lg-3 mb-3">
            <div class="card" style="overflow: hidden;">
                <div class="card-body pb-2 pt-2">
                    <div style="min-height: 56px;">
                        <h5 class="card-title"><a
                                href="{{ route('seller.service.auction.view', $auction->id) }}">{{ $sellerServiceModel::whereId(json_decode($auction->services)[0])->first()->name }}</a>
                        </h5>
                    </div>

                    <div class="houseDetails mb-1">
                        <div>

                            <span
                                class="d-inline-flex justify-content-center align-items-center gap-1">{{-- <img
                                    src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}"
                                    alt="bed icon" width="15"> --}}<b>
                                        {{mb_strimwidth($auction->description, 0, 90, '...')}}
                                </b></span>
                            {{-- <span
                                class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                    src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}"
                                    alt="bed icon" width="15"><b>
                                    {{ $auction->bathroom->name }}</b></span> --}}
                        </div>
                        Service Required
                    </div>


                    <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            @php
                                $start =  $carbon::now();//parse(db_time());
                                if (strpos($auction->auction_length, 'M') !== false) {
                                    $mins = explode('M', $auction->auction_length);
                                    $end = $carbon::parse($auction->created_at)->addMinutes($mins[0]);
                                } else {
                                    $end = $carbon::parse($auction->created_at)->addDays($auction->auction_length);
                                }
                                // $end = $carbon::parse($auction->created_at)->addDays($auction->auction_length);
                                $diff = $end->diffInDays($start);
                            @endphp
                        </svg><b
                            class="timer-{{ $auction->id }} badge bg-info">{{ round($auction->auction_length) <= 0 ? 'No Time Limit' : $diff . 'd ' . $start->diff($end)->format('%H:%I:%S') }}</b>
                    </p>

                </div>



                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-6 left">
                            <!-- Barcode  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                data-bs-trigger="hover focus" data-bs-placement="top"
                                data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                </path>
                            </svg>
                            <!-- Message  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                data-bs-trigger="hover focus" data-bs-placement="top"
                                data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                </path>
                            </svg>
                            <!-- FAvourite  -->
                            <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                data-bs-trigger="hover focus" data-bs-placement="top"
                                data-bs-content="Add Favorites" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                                </path>
                            </svg>
                        </div>
                        <div class="col-6 right text-end">
                            <b>${{ number_format($auction->price, 0, '', ',') }}</b>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        @empty
        <div class="card p-4 text-center">
            <h3>No Record Found!</h3>
        </div>
        @endforelse


            {{ $pAuctions->links('pagination.listing') }}
      </div>
    </div>
    <p class="text-center small opacity-50 mt-n4 tiny text-uppercase"> {{$count}} RESULTS FOUND</p>
</div>
@endsection
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@foreach ($pAuctions as $pa)
@php
    $start =  $carbon::now();//parse(db_time());
    if (strpos($pa->auction_length, 'M') !== false) {
        $mins = explode('M', $pa->auction_length);
        $end = $carbon::parse($pa->created_at)->addMinutes($mins[0]);
    } else {
        $end = $carbon::parse($pa->created_at)->addDays($pa->auction_length);
    }
    // $end = $carbon::parse($auction->created_at)->addDays($auction->auction_length);
    $diff = $end->diffInDays($start);
@endphp
{{--
</svg><b class="timer-{{$pa->id}}" data-time="20s">
    {{round($pa->auction_length)<=0?"No Time Limit": $diff.'d '.$start->diff($end)->format('%H:%I:%S')}}</b></p>
--}}
@if(round($pa->auction_length)!="-1")
@php
    $dt = $diff.'d'.$start->diff($end)->format('%Hh%Im:%Ss');
@endphp
<script>
    $(function () {
        $('.timer-{{$pa->id}}').timer({
            countdown: true,
            duration: '{{$dt}}',    	// This will start the countdown from 3 mins 40 seconds
            format: '%dd %H:%M:%S',
            callback: function() {	// This will execute after the duration has elapsed
                console.log('Time up!');
            }
        });
    });
</script>
@endif
@endforeach
@endpush
