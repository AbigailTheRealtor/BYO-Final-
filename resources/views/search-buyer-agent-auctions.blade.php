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
                    @php
                        $resolveOther = function($selectedValue, $otherText) {
                            if (strtolower(trim($selectedValue ?? '')) === 'other' && !empty($otherText)) {
                                return $otherText;
                            }
                            return $selectedValue;
                        };

                        $propTypeRaw = strtolower(trim(@$auction->get->property_type ?? ''));
                        $isResidential = (strpos($propTypeRaw, 'residential') !== false);
                        $isCommercial = (strpos($propTypeRaw, 'commercial') !== false) || (strpos($propTypeRaw, 'income') !== false) || (strpos($propTypeRaw, 'business') !== false);
                        $propertyTypeLabel = @$auction->get->property_type ?: '';

                        $propertyItems = @$auction->get->property_items;
                        $propertyStyleDisplay = '';
                        $rawItemsList = [];
                        if (is_array($propertyItems) && count($propertyItems) > 0) {
                            $rawItemsList = $propertyItems;
                        } elseif (is_string($propertyItems) && !empty($propertyItems)) {
                            $decoded = json_decode($propertyItems, true);
                            $rawItemsList = is_array($decoded) ? $decoded : (strlen($propertyItems) > 0 ? [$propertyItems] : []);
                        }
                        $allStyleItems = [];
                        foreach ($rawItemsList as $item) {
                            if (strtolower(trim($item)) === 'other' && !empty(@$auction->get->other_property_items)) {
                                $allStyleItems[] = $auction->get->other_property_items;
                            } else {
                                $allStyleItems[] = $item;
                            }
                        }
                        $propertyStyleDisplay = implode(', ', $allStyleItems);

                        $bedsDisplay = $resolveOther(@$auction->get->bedrooms, @$auction->get->other_bedrooms);
                        $bathsDisplay = $resolveOther(@$auction->get->bathrooms, @$auction->get->other_bathrooms);
                        $sqftDisplay = @$auction->get->minimum_heated_square ?? '';

                        $fmtMoney = function($v) { return '$' . number_format((float)$v, 2); };
                        $fmtPct   = function($v) { return $v . '%'; };
                        $buyerCommStructure = str_replace('"', '', @$auction->get->commission_structure ?? '');
                        $buyerFeeType = @$auction->get->purchase_fee_type ?? '';
                        $buyerFeeCombined = '';
                        if ($buyerFeeType === 'Flat Fee' && !empty(@$auction->get->purchase_fee_flat)) {
                            $buyerFeeCombined = $fmtMoney($auction->get->purchase_fee_flat);
                        } elseif ($buyerFeeType === 'Percentage of the Total Purchase Price' && !empty(@$auction->get->purchase_fee_percentage)) {
                            $buyerFeeCombined = $fmtPct($auction->get->purchase_fee_percentage) . ' of Total Purchase Price';
                        } elseif ($buyerFeeType === 'Percentage of the Total Purchase Price + Flat Fee') {
                            $pts = [];
                            if (!empty(@$auction->get->purchase_fee_flat_combo)) $pts[] = $fmtMoney($auction->get->purchase_fee_flat_combo);
                            if (!empty(@$auction->get->purchase_fee_percentage_combo)) $pts[] = $fmtPct($auction->get->purchase_fee_percentage_combo) . ' of Total Purchase Price';
                            $buyerFeeCombined = implode(' + ', $pts);
                        } elseif (strtolower($buyerFeeType) === 'other' && !empty(@$auction->get->purchase_fee_other)) {
                            $buyerFeeCombined = @$auction->get->purchase_fee_other;
                        }

                        $rawCounties = @$auction->get->counties ?? [];
                        $countyDisplay = '';
                        if (is_array($rawCounties) && count($rawCounties) > 0) {
                            $countyDisplay = $rawCounties[0];
                        } elseif (is_string($rawCounties) && !empty($rawCounties)) {
                            $decoded2 = json_decode($rawCounties, true);
                            if (is_array($decoded2) && count($decoded2) > 0) {
                                $countyDisplay = $decoded2[0];
                            } elseif (trim($rawCounties, '[]') !== '') {
                                $countyDisplay = $rawCounties;
                            }
                        }
                        if (!empty($countyDisplay) && stripos($countyDisplay, 'County') === false) {
                            $countyDisplay .= ' County';
                        }



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
                                if ($sec <= 0) { return 'Expired'; }
                                $d = intdiv($sec, 86400); $sec %= 86400;
                                $h = intdiv($sec, 3600); $sec %= 3600;
                                $i = intdiv($sec, 60); $s = $sec % 60;
                                return sprintf('%dd %02d:%02d:%02d', $d, $h, $i, $s);
                            };
                        }
                    @endphp
                    <div class="col-sm-6 col-md-12 col-lg-4 mb-3">
                        <div class="card" style="overflow: hidden;">
                            <div class="card-body pb-2 pt-2">
                                <div style="min-height: 56px;">
                                    <h5 class="card-title w-75"><a
                                            href="{{ route('buyer.view-auction', @$auction->id) }}">{{ @$auction->title }}</a>
                                    </h5>
                                </div>

                                <div class="houseDetails mb-1">
                                    <p class="mb-1 fw-bold text-muted small">Buyer &bull; {{ $propertyTypeLabel }}</p>

                                    @if (!empty($propertyStyleDisplay))
                                        <p class="mb-1">
                                            @if ($isResidential)
                                                &#x1F3E0;
                                            @elseif ($isCommercial)
                                                &#x1F3E2;
                                            @else
                                                &#x1F3E0;
                                            @endif
                                            <b>{{ $propertyStyleDisplay }}</b>
                                        </p>
                                    @endif

                                    @if (!empty($bedsDisplay) || !empty($bathsDisplay))
                                        <p class="mb-1">
                                            <span class="d-inline-flex align-items-center gap-1">
                                                &#x1F6CF; <b>{{ $bedsDisplay ?: '-' }}</b> Beds
                                            </span>
                                            |
                                            <span class="d-inline-flex align-items-center gap-1">
                                                &#x1F6C1; <b>{{ $bathsDisplay ?: '-' }}</b> Baths
                                            </span>
                                            @if (!empty($sqftDisplay))
                                                |
                                                <span class="d-inline-flex align-items-center gap-1">
                                                    &#x1F4D0; <b>{{ $sqftDisplay }}</b> Sq Ft
                                                </span>
                                            @endif
                                        </p>
                                    @elseif (!empty($sqftDisplay))
                                        <p class="mb-1">
                                            <span class="d-inline-flex align-items-center gap-1">
                                                &#x1F4D0; <b>{{ $sqftDisplay }}</b> Sq Ft
                                            </span>
                                        </p>
                                    @endif

                                    @if (!empty($countyDisplay))
                                        <p class="mb-1">&#x1F4CD; {{ $countyDisplay }}</p>
                                    @endif

                                    <p class="mb-1">&#x1F464; Buyer's Agent Required</p>

                                    @if (!empty($buyerCommStructure))
                                        <p class="mb-1">&#x1F4BC; <b>Buyer's Broker Commission Structure:</b> {{ $buyerCommStructure }}</p>
                                    @endif
                                    @if (!empty($buyerFeeCombined))
                                        <p class="mb-1">&#x1F4BC; <b>Buyer's Broker Purchase Fee:</b> {{ $buyerFeeCombined }}</p>
                                    @endif
                                </div>

                                @if ($rawDays !== '')
                                    <p class="m-0">
                                        <b class="badge bg-info timer-{{ $auction->id }}"
                                            @if ($lengthDays > 0 && $remainingSeconds > 0) data-seconds="{{ $remainingSeconds }}" @endif>
                                            @if ($lengthDays <= 0)
                                                No Time Limit
                                            @else
                                                {{ $pretty($remainingSeconds) }}
                                            @endif
                                        </b>
                                    </p>
                                @endif

                            </div>

                            <div class="card-footer bg-light">
                                <div class="row">
                                    <div class="col-6 left">
                                        <svg data-bs-container="body" tabindex="0" data-bs-toggle="popover"
                                            data-bs-trigger="hover focus" data-bs-placement="top"
                                            data-bs-content="Send Message" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                            </path>
                                        </svg>
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
