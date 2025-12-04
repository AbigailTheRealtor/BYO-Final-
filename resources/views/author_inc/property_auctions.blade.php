@extends('author')

@section('listing')




<div class="cardsDetails row  justify-content-start">

    @inject('carbon', 'Carbon\Carbon')
    @forelse ($auctions as $auction)
        <div class="col-sm-6 col-md-4 col-lg-4 mb-3">
            <div class="card" style="overflow: hidden;">
              <div style="min-height: 202px;">
                @php
                    $photo = url('auction/images/noimage.png');
                    if (gettype(@$auction->get->photos) == 'array') {
                        $photos = @$auction->get->photos;
                        $photo = url(current($photos));
                    }
                @endphp
                <img
                src="{{ $photo }}"
                class="card-img-top" alt="..." style="width: 100%; height:202px; object-fit: cover;">
              </div>
              <div class="card-body pb-2 pt-2" style="position: relative;">
                <div style="min-height: 56px;">
                    <h5 class="card-title"><a href="{{route('view-pl',@$auction->id)}}">{{@$auction->get->address}}</a>
                    </h5>
                </div>
                <div class="qr-code" style="width: 50px; height:50px; position: absolute; top:0; right:0;">
                    {{qr_code(route('view-pl',@$auction->id), 150)}}
                </div>
                <div class="houseDetails mb-1">
                  <span>
                    <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                        src="{{asset('assets/fontawesome/svgs/thin/bed-front.svg')}}" alt="bed icon" width="15"><b> {{@$auction->get->bedrooms}}</b></span>
                    <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                        src="{{asset('assets/fontawesome/svgs/thin/bath.svg')}}" alt="bed icon" width="15"><b> {{@$auction->get->bathrooms}}</b></span>
                    <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                        src="{{asset("assets/fontawesome/svgs/thin/ruler-triangle.svg")}}" alt="bed icon" width="15"><b> {{number_format(@$auction->get->heated_sqft,0,"",",")}}
                      </b>Sq Ft</span>
                  </span>
                  - House for sale
                </div>
                @php
                    $ptype = @$auction->get->property_type;
                @endphp
                <p class="card-text mb-1"><span class="badge bg-secondary">{{$ptype}}</span> <span
                    class="float-end"><span><b>MLS ID</b></span> <span>#{{@$auction->get->mls_id}}</span></span></p>
                <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      @php
                          $start = $carbon::now();
                          $end = $carbon::parse(@$auction->created_at)->addDays(@$auction->get->auction_length_days);
                          $diff = $end->diffInDays($start);
                      @endphp
                  </svg><b class="badge bg-info timer-{{@$auction->id}}" data-time="20s">{{round(@$auction->get->auction_length_days)<=0?"No Time Limit": $diff.'d '.$start->diff($end)->format('%H:%I:%S')}}</b></p>
              </div>
              <div class="card-footer bg-light">
                <div class="row">
                  <div class="col-6 left">

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
                    <b>${{number_format(@$auction->get->starting_price,0,"",",")}}</b>
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


        {{ $auctions->links('pagination.listing') }}
  </div>


@endsection
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@foreach ($auctions as $auction)
@php
    $start = $carbon::now();
    $end = $carbon::parse(@$auction->created_at)->addDays(@$auction->get->auction_length_days);
    $diff = $end->diffInDays($start);
@endphp
{{--
</svg><b class="timer-{{@$auction->id}}" data-time="20s">
    {{round(@$auction->auction_length)<=0?"No Time Limit": $diff.'d '.$start->diff($end)->format('%H:%I:%S')}}</b></p>
--}}
@if(round(@$auction->get->auction_length_days)>0)
@php
    $dt = $diff.'d'.$start->diff($end)->format('%Hh%Im:%Ss');
@endphp
<script>
    $(function () {
        $('.timer-{{@$auction->id}}').timer({
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
