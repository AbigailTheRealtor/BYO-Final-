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
                    <div class="col-sm-12 col-md-3 col-lg-3">
                        <div class="mb-3">
                            <input type="text" name="title" value="{{ request()->title }}" class="form-control"
                                placeholder="Title / Address.">
                        </div>
                    </div>
                    @php
                        $bedrooms = [
                            ['name' => '1+', 'target' => ''],
                            ['name' => '2+', 'target' => ''],
                            ['name' => '3+', 'target' => ''],
                            ['name' => '4+', 'target' => ''],
                            ['name' => '5+', 'target' => ''],
                            ['name' => '6+', 'target' => ''],
                            ['name' => '7+', 'target' => ''],
                            ['name' => '8+', 'target' => ''],
                            ['name' => '9+', 'target' => ''],
                            ['name' => '10+', 'target' => ''],
                            ['name' => 'Commercial', 'target' => ''],
                            ['name' => 'Other', 'target' => '.other_bedrooms'],
                        ];
                        $bathrooms = [
                            ['name' => '1', 'target' => ''],
                            ['name' => '1.5+', 'target' => ''],
                            ['name' => '2+', 'target' => ''],
                            ['name' => '2.5+', 'target' => ''],
                            ['name' => '3+', 'target' => ''],
                            ['name' => '3.5+', 'target' => ''],
                            ['name' => '4+', 'target' => ''],
                            ['name' => '4.5+', 'target' => ''],
                            ['name' => '5+', 'target' => ''],
                            ['name' => '6+', 'target' => ''],
                            ['name' => '7+', 'target' => ''],
                            ['name' => '8+', 'target' => ''],
                            ['name' => '9+', 'target' => ''],
                            ['name' => '10+', 'target' => ''],
                            ['name' => 'Other', 'target' => '.other_bathrooms'],
                        ];
                    @endphp
                    <div class="col-sm-12 col-md-3 col-lg-3">
                        <div class="mb-3">
                            <select name="bedrooms" onchange="javascript:$('.search-form').submit();"
                                class="form-select form-control">
                                <option value="">Any Bedrooms</option>
                                @foreach ($bedrooms as $item)
                                    <option value="{{ $item['name'] }}" {{ selected($item['name'], request()->bedrooms) }}>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-3 col-lg-3">
                        <div class="mb-3">
                            <select name="bathrooms" onchange="javascript:$('.search-form').submit();" class="form-select">
                                <option value="">Any Bathrooms</option>
                                @foreach ($bathrooms as $item)
                                    <option value="{{ $item['name'] }}" {{ selected($item['name'], request()->bathrooms) }}>
                                        {{ $item['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    <div class="col-sm-12 col-md-3 col-lg-3">
                        @php
                            $property_types = [
                                ['target' => '', 'name' => 'Single Family Home'],
                                ['target' => '', 'name' => 'Townhome'],
                                ['target' => '', 'name' => 'Condo'],
                                ['target' => '', 'name' => 'Condo-Hotel'],
                                ['target' => '', 'name' => 'Villa'],
                                ['target' => '', 'name' => 'Multi-Family'],
                                ['target' => '', 'name' => 'Land/Lots'],
                                ['target' => '', 'name' => 'Manufactured Home'],
                                ['target' => '', 'name' => 'Modular Home'],
                                ['target' => '', 'name' => 'Agricultural'],
                                ['target' => '', 'name' => 'Assembly Building'],
                                ['target' => '', 'name' => 'Business'],
                                ['target' => '', 'name' => 'Five or more'],
                                ['target' => '', 'name' => 'Hotel/Motel'],
                                ['target' => '', 'name' => 'Industrial'],
                                ['target' => '', 'name' => 'Mixed Use'],
                                ['target' => '', 'name' => 'Office'],
                                ['target' => '', 'name' => 'Restaurant'],
                                ['target' => '', 'name' => 'Retail'],
                                ['target' => '', 'name' => 'Warehouse'],
                                ['target' => '.custom_property_type', 'name' => 'Other'],
                            ];
                        @endphp
                        <div class="mb-3">
                            <select name="property_type" onchange="javascript:$('.search-form').submit();"
                                class="form-select">
                                <option value="">Any Property Type</option>
                                @foreach ($property_types as $item)
                                    <option value="{{ $item['name'] }}"
                                        {{ selected($item['name'], request()->property_type) }}>
                                        {{ $item['name'] }}</option>
                                @endforeach
                            </select>
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
                            <option value="1" {{ request()->sort == 1 ? 'selected' : '' }}>Title: (Z-a)</option>
                            <option value="2" {{ request()->sort == 2 ? 'selected' : '' }}>Title: (A-z)</option>
                            <option value="3" {{ request()->sort == 3 ? 'selected' : '' }}>Date: (New)</option>
                            <option value="4" {{ request()->sort == 4 ? 'selected' : '' }}>Date: (Old)</option>
                            {{-- <option value="5" {{request()->sort == 5 ? "selected":""}}>Price: (Highest)</option>
                            <option value="6" {{request()->sort == 6 ? "selected":""}}>Price: (Lowest)</option> --}}
                        </select>
                    </div>
                </div>
            </form>

            <div class="cardsDetails row  justify-content-start">

                @inject('carbon', 'Carbon\Carbon')
                @forelse ($pAuctions as $auction)
                    <div class="col-sm-6 col-md-12 col-lg-4 mb-3">
                        <div class="card" style="overflow: hidden;">
                            <div class="card-body pb-2 pt-2">
                                <div style="min-height: 56px;">
                                    <h5 class="card-title w-75"><a
                                            href="{{ route('seller.agent.auction.detail', @$auction->id) }}">{{ @$auction->title }}</a>
                                    </h5>
                                </div>
                                {{-- <div class="'qr-code" style="width: 50px; height:50px; position: absolute; top:0; right:0;">
                                    {{ qr_code(route('tenant.agent.auction.view', @$auction->id), 150) }}
                                </div> --}}

                                <div class="houseDetails mb-1">
                                    <span>
                                        <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                                src="{{ asset('assets/fontawesome/svgs/thin/bed-front.svg') }}"
                                                alt="bed icon" width="15"><b>
                                                {{ @$auction->get->bedrooms }}</b></span>
                                        <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                                src="{{ asset('assets/fontawesome/svgs/thin/bath.svg') }}" alt="bed icon"
                                                width="15"><b>
                                                {{ @$auction->get->bathrooms }}</b></span>
                                        <span class="d-inline-flex justify-content-center align-items-center gap-1"><img
                                                src="{{ asset('assets/fontawesome/svgs/thin/ruler-triangle.svg') }}"
                                                alt="bed icon" width="15"><b>
                                                {{ isset($auction->get->minimum_heated_square) ? $auction->get->minimum_heated_square : '' }}
                                            </b>Sq Ft</span>
                                        <span class="d-inline-flex justify-content-center align-items-center gap-1">
                                            <img src="{{ asset('assets/fontawesome/svgs/thin/house.svg') }}"
                                                alt="condition icon" width="15">

                                            <b>
                                                @if (!empty($auction->get->condition_prop_buyer))
                                                    @foreach ($auction->get->condition_prop_buyer as $condition)
                                                        {{ $condition }}@if (!$loop->last)
                                                            ,
                                                        @endif
                                                    @endforeach
                                                @endif
                                            </b>
                                        </span>
                                        <span class="d-inline-flex justify-content-center align-items-center gap-1">
                                            <img src="{{ asset('assets/fontawesome/svgs/thin/user.svg') }}" alt="user icon"
                                                width="15">
                                            <b>{{ $auction->get->user_type ?? '' }}</b>
                                        </span>

                                    </span><br>

                                    Seller’s Agent required
                                </div>

                                {{-- @if ($auction->get->auction_time != null || $auction->get->auction_time === '')


                                    <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            @php
                                                $start = $carbon::now();
                                                $end = $carbon::parse(@$auction->created_at)->addDays(30);
                                                $diff = $end->diffInDays($start);
                                            @endphp
                                        </svg>
                                        @php
                                            $days = intval($auction->get->auction_time);

                                        @endphp

                                        <b
                                            class="timer-{{ @$auction->id }} badge bg-info">{{ round($days) <= 0 ? 'No Time Limit' : $diff . 'd ' . $start->diff($end)->format('%H:%I:%S') }}</b>
                                    </p>
                                @endif --}}


                               {{-- <p class="m-0"><svg xmlns="http://www.w3.org/2000/svg" class="clock" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>

                                        </svg>

                                    </p> --}}

                                @php


                                    $rawDays = trim((string) ($auction->get->auction_time ?? ''));

                                    $lengthDays = 0;
                                    $remainingSeconds = 0;
                                    $pretty = null;

                                    if ($rawDays !== '') {


                                    preg_match('/\d+/', $rawDays, $m);
                                        $lengthDays = isset($m[0]) ? (int) $m[0] : 0;

                                        $now = \Carbon\Carbon::now();
                                        $end = \Carbon\Carbon::parse($auction->created_at)->addDays($lengthDays);

                                        $remainingSeconds = $now->diffInSeconds($end, false);

                                        $pretty = function (int $sec) {
                                            if ($sec <= 0) {
                                                return 'Expired';
                                            }
                                            $d = intdiv($sec, 86400);
                                            $sec %= 86400;
                                            $h = intdiv($sec, 3600);
                                            $sec %= 3600;
                                            $i = intdiv($sec, 60);
                                            $s = $sec % 60;
                                            return sprintf('%dd %02d:%02d:%02d', $d, $h, $i, $s);
                                        };
                                    }
                                @endphp





                                @if ($rawDays !== '')
                                    <p class="m-0">
                                        <b class="badge bg-info timer-{{ $auction->id }}" {{-- only add data-seconds if there IS a limit and it’s in the future --}}
                                            @if ($lengthDays > 0 && $remainingSeconds > 0) data-seconds="{{ $remainingSeconds }}" @endif>
                                            {{-- initial server-side text --}}
                                            @if ($lengthDays <= 0)
                                                No Time Limit
                                            @else
                                                {{ $pretty($remainingSeconds) }}
                                            @endif
                                        </b>
                                    </p>
                                @endif

                                @if ($auction->get->agent_bid_visibility != null || $auction->get->agent_bid_visibility === '')
                                    <h5 style="color:rgb(52 162 185)">{{ $auction->get->agent_bid_visibility }}</h5>
                                @endif
                            </div>

                            <div class="card-footer bg-light">
                                <div class="row">
                                    <div class="col-6 left">
                                        <!-- Barcode  -->
                                        {{-- <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                            data-bs-trigger="hover focus" data-bs-placement="top"
                                            data-bs-content="Scan Qr Code" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                                            </path>
                                        </svg> --}}
                                        <!-- Message  -->
                                        <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                            data-bs-trigger="hover focus" data-bs-placement="top"
                                            data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                            </path>
                                        </svg>
                                        <!-- Favourite  -->
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
                                        <b>{{ @$auction->get->budget }}</b>
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
        {{-- jQuery (required by timer.jquery). Skip if your app already loads it. --}}
        <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/timer.jquery/0.9.0/timer.jquery.min.js" crossorigin="anonymous"
            referrerpolicy="no-referrer"></script>
        <script>
            (function() {
                function secondsToTimerString(total) {
                    if (!total || total <= 0) return "0s";
                    var d = Math.floor(total / 86400);
                    total %= 86400;
                    var h = Math.floor(total / 3600);
                    total %= 3600;
                    var m = Math.floor(total / 60);
                    var s = total % 60;
                    var out = "";
                    if (d) out += d + "d";
                    if (h) out += h + "h";
                    if (m) out += m + "m";
                    if (s || (!d && !h && !m)) out += s + "s";
                    return out;
                }

                // Initialize all countdown badges that carry data-seconds
                document.querySelectorAll('[class*="timer-"][data-seconds]').forEach(function(el) {
                    var secs = parseInt(el.getAttribute('data-seconds'), 10) || 0;
                    if (secs <= 0) {
                        el.textContent = 'Expired';
                        return;
                    }
                    $(el).timer({
                        countdown: true,
                        duration: secondsToTimerString(secs),
                        format: '%dd %H:%M:%S',
                        callback: function() {
                            el.textContent = 'Expired';
                        }
                    });
                });
            })();
        </script>
    @endpush
