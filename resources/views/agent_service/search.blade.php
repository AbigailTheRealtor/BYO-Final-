@extends('layouts.main')
@push('styles')
@endpush
@section('content')
  <div class="container">
    @include('layouts.partials.search_menu')
  </div>
  <div class="buyerOfferContentDetails">
    <div class="container">
      <p><span><b>Explore</b></span> <span><i>{{ $count }} results</i></span></p>
      <form action="" method="GET" class="search-form">
        <div class="selectionRoom row">
          <div class="col-sm-12 col-md-12 col-lg-12">
            <div class="mb-3">
              <input type="text" name="title" value="{{ request()->title }}" class="form-control"
                placeholder="Search service...">
            </div>
          </div>

        </div>

        <div class="buyerOfferSearchMenu d-flex justify-content-between flex-wrap text-center">
          <div class="left d-flex align-items-center mb-3 flex-wrap">
            {{-- <div class="form-check form-control-lg form-switch">
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
                </div> --}}
          </div>
          <div class="right d-flex align-items-center mb-3 flex-wrap">
            <!-- Select Box  -->
            <select name="sort" class="sortby" onchange="javascript:$('.search-form').submit();">
              <option value="">Most Relevant</option>
              {{-- <option value="1" {{request()->sort == 1 ? "selected":""}}>Title: (Z-a)</option>
                        <option value="2" {{request()->sort == 2 ? "selected":""}}>Title: (A-z)</option> --}}
              <option value="3" {{ request()->sort == 1 ? 'selected' : '' }}>Date: (New)</option>
              <option value="4" {{ request()->sort == 2 ? 'selected' : '' }}>Date: (Old)</option>
              {{-- <option value="5" {{request()->sort == 5 ? "selected":""}}>Price: (Highest)</option>
                        <option value="6" {{request()->sort == 6 ? "selected":""}}>Price: (Lowest)</option> --}}
            </select>
          </div>
        </div>
      </form>









      <div class="cardsDetails row  justify-content-start">

        @inject('carbon', 'Carbon\Carbon')
        @forelse ($pAuctions as $auction)
          <div class="col-sm-6 col-md-12 col-lg-3 mb-3">
            <div class="card" style="overflow: hidden;">
              <div class="card-body pb-2 pt-2">
                <div style="min-height: 56px;">
                  <h5 class="card-title w-75"><a
                      href="{{ route('agent.service.auction.view', @$auction->id) }}">{{ @$auction->get->service_type }}</a>
                  </h5>
                </div>
                <div class="qr-code" style="width: 50px; height:50px; position: absolute; top:0; right:0;">
                  {{ qr_code(route('agent.service.auction.view', @$auction->id), 150) }}
                </div>

                <div class="houseDetails mb-1">
                  <div>
                    <span class="d-inline-flex justify-content-center align-items-center gap-1">{{-- <img
                                    src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}"
                                    alt="bed icon" width="15"> --}}<b>
                        {{ mb_strimwidth(@$auction->description, 0, 90, '...') }}</b></span>
                    {{-- <span
                                class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                    src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}"
                                    alt="bed icon" width="15"><b>
                                    {{ @$auction->bathroom->name }}</b></span> --}}
                  </div>
                  Service Required
                </div>


                <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    @php
                      $start = $carbon::parse(db_time());
                      if (strpos(@$auction->auction_length, 'M') !== false) {
                          $mins = explode('M', @$auction->auction_length);
                          $end = $carbon::parse(@$auction->created_at)->addMinutes($mins[0]);
                      } else {
                          $end = $carbon::parse(@$auction->created_at)->addDays(@$auction->auction_length);
                      }
                      // $end = $carbon::parse(@$auction->created_at)->addDays(30);
                      $diff = $end->diffInDays($start);
                    @endphp
                  </svg><b
                    class="timer-{{ @$auction->id }} badge bg-info">{{ $diff . 'd ' . $start->diff($end)->format('%H:%I:%S') }}</b>
                </p>

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
                    {{-- <b>${{ number_format(@$auction->get->offerer_commission) }} @if (@$auction->get->offerer_commission_negotiable == 'Yes') --}}
                    <b>${{ @$auction->get->offerer_commission }} @if (@$auction->get->offerer_commission_negotiable == 'Yes')
                        Negotiatable
                      @endif </b>
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
    <p class="text-center small opacity-50 mt-n4 tiny text-uppercase"> {{ $count }} RESULTS FOUND</p>
  </div>
@endsection
@push('scripts')
  <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous"
    referrerpolicy="no-referrer"></script>
  @foreach ($pAuctions as $pa)
    @php
      $start = $carbon::parse(db_time());
      if (strpos(@$auction->auction_length, 'M') !== false) {
          $mins = explode('M', @$auction->auction_length);
          $end = $carbon::parse(@$auction->created_at)->addMinutes($mins[0]);
      } else {
          $end = $carbon::parse(@$auction->created_at)->addDays(@$auction->auction_length);
      }
      // $end = $carbon::parse($pa->created_at)->addDays($pa->auction_length);
      $diff = $end->diffInDays($start);
    @endphp
    {{--
</svg><b class="timer-{{$pa->id}}" data-time="20s">
    {{round($pa->auction_length)<=0?"No Time Limit": $diff.'d '.$start->diff($end)->format('%H:%I:%S')}}</b></p>
--}}

    @php
      $dt = $diff . 'd' . $start->diff($end)->format('%Hh%Im:%Ss');
    @endphp
    <script>
      $(function() {
        $('.timer-{{ $pa->id }}').timer({
          countdown: true,
          duration: '{{ $dt }}', // This will start the countdown from 3 mins 40 seconds
          format: '%dd %H:%M:%S',
          callback: function() { // This will execute after the duration has elapsed
            console.log('Time up!');
          }
        });
      });
    </script>
  @endforeach
@endpush
