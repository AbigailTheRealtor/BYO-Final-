@extends('layouts.main')
@push('styles')
@endpush
@section('content')
<div class="container">
    @include('layouts.partials.search_menu')
</div>
<div class="buyerOfferContentDetails">
    <div class="container">
        <div class="mb-4">
            <h4 class="fw-bold">Buyer Criteria Listings</h4>
            <p class="text-muted small">Buyers and Tenants post their search criteria — agents bid to be hired as their representative. Browse open listings and submit your bid.</p>
            <p class="text-muted small mb-0"><strong>{{ $count }}</strong> listing{{ $count != 1 ? 's' : '' }} found</p>
        </div>

        {{-- Filters (display only) --}}
        <div class="selectionRoom row mb-3">
            <div class="col-sm-12 col-md-3 col-lg-3">
                <div class="mb-3">
                    <select id="disabledSelect" class="form-select" disabled>
                        <option>City, County, ZIP, Address...</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 col-md-3 col-lg-3">
                <div class="mb-3">
                    <select id="disabledSelect" class="form-select" disabled>
                        <option>Bedrooms</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 col-md-3 col-lg-3">
                <div class="mb-3">
                    <select id="disabledSelect" class="form-select" disabled>
                        <option>Bathrooms</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 col-md-3 col-lg-3">
                <div class="mb-3">
                    <select id="disabledSelect" class="form-select" disabled>
                        <option value="">Property Type (Sale)</option>
                        <option>Residential</option>
                        <option>Income</option>
                        <option>Commercial</option>
                        <option>Business Opportunity</option>
                        <option>Vacant Land</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="buyerOfferSearchMenu d-flex justify-content-end flex-wrap text-center mb-3">
            <div class="right d-flex align-items-center flex-wrap">
                <select name="selection" class="sortby">
                    <option value="">Most Relevant</option>
                    <option value="id">Date: (New)</option>
                    <option value="id">Date: (Old)</option>
                    <option value="price">Price: (Highest)</option>
                    <option value="price">Price: (Lowest)</option>
                    <option value="expiry">Ending: (Last)</option>
                    <option value="expiry">Ending: (Soon)</option>
                </select>
            </div>
        </div>

        <div class="cardsDetails row justify-content-start">
            @inject('carbon', 'Carbon\Carbon')
            @forelse ($pAuctions as $auction)
            <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                <div class="card rounded-3" style="overflow: hidden;">
                    <div class="card-body pb-2 pt-2">
                        <div style="min-height: 56px;">
                            <p class="mb-1 text-muted small fw-semibold text-uppercase" style="letter-spacing:.04em;font-size:.7rem;">Buyer / Tenant Criteria</p>
                            <h5 class="card-title"><a href="{{ route('buyer.criteria.view', @$auction->id) }}">{{ @$auction->property_type->name }}</a></h5>
                        </div>

                        <div class="houseDetails mb-2">
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="d-inline-flex justify-content-center align-items-center gap-1">
                                    <img src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}" alt="bed icon" width="15">
                                    <b>{{ @$auction->bedrooms ?: '—' }}</b> Beds
                                </span>
                                <span class="d-inline-flex justify-content-center align-items-center gap-1">
                                    <img src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}" alt="bath icon" width="15">
                                    <b>{{ @$auction->bathrooms ?: '—' }}</b> Baths
                                </span>
                            </span>
                        </div>

                        <p class="m-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                @php
                                    $start = $carbon::now();
                                    $end = $carbon::parse(@$auction->created_at)->addDays(30);
                                    $diff = $end->diffInDays($start);
                                @endphp
                            </svg>
                            <b class="timer-{{ @$auction->id }} badge bg-info">
                                {{ round(@$auction->auction_length) <= 0 ? 'No Time Limit' : $diff . 'd ' . $start->diff($end)->format('%H:%I:%S') }}
                            </b>
                        </p>
                    </div>

                    <div class="card-footer bg-light">
                        <div class="row">
                            <div class="col-6 left">
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Scan QR Code" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                </svg>
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path>
                                </svg>
                                <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="Add to Favorites" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                            </div>
                            <div class="col-6 right text-end">
                                <b>${{ number_format(@$auction->max_price, 0, '', ',') }}</b>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="card border-0 bg-light rounded-3 p-5 text-center">
                    <div class="text-muted">
                        <i class="fa fa-search fa-3x mb-3 opacity-25"></i>
                        <h5 class="fw-semibold">No listings found</h5>
                        <p class="small mb-0">No Buyers or Tenants have posted criteria listings in this category yet. Check back soon or broaden your search.</p>
                    </div>
                </div>
            </div>
            @endforelse

            {{ $pAuctions->links('pagination.listing') }}
        </div>
    </div>
    @if($count > 0)
    <p class="text-center small text-muted mt-2 text-uppercase" style="letter-spacing:.05em;">{{ $count }} result{{ $count != 1 ? 's' : '' }} found</p>
    @endif
</div>
@endsection
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@foreach ($pAuctions as $pa)
@php
    $start = $carbon::now();
    $end = $carbon::parse($pa->created_at)->addDays($pa->auction_length);
    $diff = $end->diffInDays($start);
@endphp
@if(round($pa->auction_length) > 0)
@php $dt = $diff . 'd' . $start->diff($end)->format('%Hh%Im:%Ss'); @endphp
<script>
    $(function () {
        $('.timer-{{ $pa->id }}').timer({
            countdown: true,
            duration: '{{ $dt }}',
            format: '%dd %H:%M:%S',
            callback: function() { console.log('Time up!'); }
        });
    });
</script>
@endif
@endforeach
@endpush
