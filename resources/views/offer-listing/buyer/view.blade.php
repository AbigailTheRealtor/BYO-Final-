@extends('layouts.main')

@php
    $fmtMoney = function($v) {
        if ($v === null || $v === '') return null;
        $raw = preg_replace('/[^0-9.]/', '', (string)$v);
        if ($raw === '' || !is_numeric($raw)) return null;
        return '$' . number_format((float)$raw, 0);
    };
    $fmtPercent = function($v) {
        if ($v === null || $v === '') return null;
        $raw = preg_replace('/[^0-9.]/', '', (string)$v);
        if ($raw === '' || !is_numeric($raw)) return null;
        $num = (float)$raw;
        return (floor($num) == $num ? (string)(int)$num : (string)$num) . '%';
    };
    $val = fn($key) => $meta[$key] ?? null;
    $str = function($key) use ($meta) { $v = $meta[$key] ?? ''; return is_array($v) ? implode(', ', $v) : (string)$v; };
    $arr = function($key) use ($meta) {
        $v = $meta[$key] ?? [];
        if (is_string($v)) {
            $d = json_decode($v, true);
            if (is_string($d)) { $d = json_decode($d, true); }
            return is_array($d) ? $d : [];
        }
        return is_array($v) ? $v : [];
    };
    $yesNo = fn($v) => match((string)$v) { '1','true','yes','Yes' => 'Yes', '0','false','no','No' => 'No', default => $v };
    $fmtDate = function($v) {
        if ($v === null || $v === '') return null;
        try {
            $d = \Carbon\Carbon::parse((string)$v);
            return $d->format('F j, Y');
        } catch (\Exception $e) {
            return null;
        }
    };
    $subOther = function(array $items, string $otherVal): array {
        if (!$otherVal) return $items;
        return array_map(fn($v) => $v === 'Other' ? $otherVal : $v, $items);
    };
    $orOther = function(string $primary, string $otherVal): string {
        return ($primary === 'Other' && $otherVal !== '') ? $otherVal : $primary;
    };
    $row = function($label, $value) {
        if ($value === null || $value === '' || $value === false) return '';
        return '<div class="row mb-2"><div class="col-md-5 text-muted fw-semibold">' . e($label) . '</div><div class="col-md-7" style="overflow-wrap:break-word;word-break:break-word;">' . e($value) . '</div></div>';
    };
@endphp

@push('styles')
<style>
/* ============================================================
   bol-view-page — Buyer Offer Listing view page styles
   ============================================================ */
.bol-view-page .section-card {
    margin-bottom: 1.75rem;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 6px rgba(0,0,0,.06);
    overflow: hidden;
}
.bol-view-page .section-card .card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    font-weight: 700;
    font-size: 1.05rem;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    letter-spacing: -0.01em;
    color: #1e293b;
}
.bol-view-page .section-card .card-body { padding: 1.25rem 1.5rem; }
.bol-view-page hr { border-color: #e9ecef; opacity: 0.6; margin: 1rem 0; }
.bol-view-page .field-label { color: #64748b; font-weight: 600; font-size: 0.85rem; }
.bol-view-page .field-value { font-size: 0.925rem; overflow-wrap: break-word; color: #1e293b; }
.bol-view-page h6.fw-semibold, .bol-view-page h6.fw-bold {
    color: #1e293b; font-size: 0.97rem; font-weight: 600; letter-spacing: 0;
}
.bol-view-page .section-card .card-body .row.mb-2 { margin-bottom: 0.65rem !important; }

/* Hero */
.bol-view-page .bol-hero {
    border-radius: 1rem; overflow: hidden; margin-bottom: 1.5rem;
    box-shadow: 0 4px 24px rgba(0,0,0,.10); background: #1e293b;
}
.bol-view-page .bol-hero-placeholder {
    min-height: 280px;
    background: linear-gradient(135deg, #1e3a5f, #0f172a);
    display: flex; align-items: center; justify-content: center;
    color: #94a3b8; font-size: 3rem;
}
.bol-view-page .bol-hero-summary {
    background: #fff; padding: 1.6rem 1.4rem;
    display: flex; flex-direction: column; justify-content: space-between;
    height: 100%; min-height: 280px; gap: 0.1rem;
}
.bol-view-page .bol-hero-price { font-size: 1.85rem; font-weight: 800; color: #1e293b; letter-spacing: -0.03em; line-height: 1.15; }
.bol-view-page .bol-hero-title { color: #1e293b; font-size: 1rem; font-weight: 700; margin-top: 0.3rem; word-break: break-word; }
.bol-view-page .bol-hero-sub { color: #475569; font-size: 0.88rem; margin-top: 0.2rem; }
.bol-view-page .bol-hero-meta { display: flex; flex-wrap: wrap; gap: 0.4rem 0.9rem; margin-top: 0.65rem; font-size: 0.84rem; color: #334155; }
.bol-view-page .bol-hero-meta-item i { color: #2563eb; margin-right: 4px; }
.bol-view-page .bol-hero-badges { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.75rem; }
.bol-view-page .bol-badge {
    display: inline-flex; align-items: center; gap: 4px; font-size: 0.73rem; font-weight: 600;
    padding: 0.25rem 0.55rem; border-radius: 20px; border: 1px solid;
    white-space: nowrap; max-width: 100%; overflow: hidden; text-overflow: ellipsis;
}
.bol-view-page .bol-badge-blue   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.bol-view-page .bol-badge-green  { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.bol-view-page .bol-badge-purple { background: #faf5ff; color: #7c3aed; border-color: #ddd6fe; }
.bol-view-page .bol-badge-amber  { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.bol-view-page .bol-badge-teal   { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
.bol-view-page .bol-badge-rose   { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
.bol-view-page .bol-hero-status {
    display: inline-flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 700;
    padding: 0.28rem 0.7rem; border-radius: 20px; background: #dcfce7; color: #166534;
    border: 1px solid #86efac; margin-top: 0.6rem; white-space: nowrap;
}
.bol-view-page .bol-hero-dates { font-size: 0.76rem; color: #94a3b8; margin-top: 0.4rem; }

/* Nav tabs */
.bol-view-page .bol-nav-tabs-wrap {
    background: #fff; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.75rem;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.bol-view-page .bol-nav-tabs {
    display: flex; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none;
    gap: 0; list-style: none; padding: 0; margin: 0;
}
.bol-view-page .bol-nav-tabs::-webkit-scrollbar { display: none; }
.bol-view-page .bol-nav-tabs li a {
    display: block; padding: 0.75rem 1.1rem; font-size: 0.82rem; font-weight: 600;
    color: #64748b; text-decoration: none; white-space: nowrap;
    border-bottom: 2px solid transparent; margin-bottom: -2px;
    transition: color .15s, border-color .15s; letter-spacing: 0.01em;
}
.bol-view-page .bol-nav-tabs li a:hover { color: #2563eb; border-bottom-color: #2563eb; }

/* Sticky sidebar */
.bol-view-page .bol-sticky-card {
    position: sticky; top: 72px; background: #fff;
    border-radius: 0.75rem; border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0,0,0,.08); padding: 1.25rem 1rem;
}
.bol-view-page .bol-sticky-card .bol-sticky-title {
    font-size: 0.78rem; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.75rem;
    padding-bottom: 0.5rem; border-bottom: 1px solid #f1f5f9;
}
.bol-view-page .bol-sticky-card .bol-action-btn {
    display: flex; align-items: center; gap: 0.6rem; width: 100%;
    padding: 0.6rem 0.75rem; font-size: 0.83rem; font-weight: 600;
    border-radius: 8px; margin-bottom: 0.4rem; text-align: left;
    border: 1px solid transparent; cursor: pointer;
    transition: background .15s, border-color .15s; text-decoration: none;
}
.bol-view-page .bol-sticky-card .bol-action-btn i { width: 18px; text-align: center; flex-shrink: 0; }
.bol-view-page .bol-action-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
.bol-view-page .bol-action-primary:hover { background: #1d4ed8; color: #fff; }
.bol-view-page .bol-action-outline { background: #fff; color: #334155; border-color: #e2e8f0; }
.bol-view-page .bol-action-outline:hover { background: #f8fafc; border-color: #cbd5e1; }

/* Mobile bar */
.bol-mobile-bar {
    display: none; position: fixed; bottom: 0; left: 0; right: 0; z-index: 1030;
    background: #fff; border-top: 1px solid #e2e8f0;
    box-shadow: 0 -4px 16px rgba(0,0,0,.10);
    padding: 0.5rem 1rem; padding-bottom: calc(0.5rem + env(safe-area-inset-bottom)); gap: 0.5rem;
}
.bol-mobile-bar-btn {
    display: flex; flex-direction: column; align-items: center; gap: 3px; flex: 1;
    font-size: 0.65rem; font-weight: 700; color: #334155; text-decoration: none;
    padding: 0.4rem 0.25rem; border-radius: 8px; border: none; background: transparent;
    cursor: pointer; transition: background .15s; min-height: 52px; justify-content: center;
}
.bol-mobile-bar-btn i { font-size: 1.15rem; color: #2563eb; }
.bol-mobile-bar-btn:hover, .bol-mobile-bar-btn:active { background: #f1f5f9; color: #1e293b; }
@media (max-width: 991.98px) {
    .bol-mobile-bar { display: flex; }
    .bol-main-content-wrap { padding-bottom: calc(80px + env(safe-area-inset-bottom)); }
}
</style>
@endpush

@section('content')
<div class="container py-4 bol-view-page">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Page Header --}}
    @php
        $listingTitle = $meta['listing_title'] ?? ($auction->title ?? 'Buyer Criteria Listing');
        $propType     = $str('property_type');
    @endphp
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h2 class="mb-1 fw-bold" style="color:#1e293b;">{{ $listingTitle }}</h2>
            <p class="text-muted mb-0">
                <i class="fa-solid fa-magnifying-glass-location me-1"></i>Buyer Criteria Listing
                @if($propType) &bull; {{ $propType }} @endif
            </p>
        </div>
        @if(auth()->id() == $auction->user_id)
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('offer.listing.buyer.edit', ['auctionId' => $auction->id]) }}"
               class="btn btn-outline-primary">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
            </a>
        </div>
        @endif
    </div>

    @php
        /* Hero: max budget / purchase price */
        $heroPrice = null;
        foreach (['maximum_budget','purchase_price','max_purchase_price','buyer_budget'] as $pk) {
            $pv = $meta[$pk] ?? '';
            if ($pv !== '' && $pv !== null) { $heroPrice = $fmtMoney($pv); break; }
        }

        /* Hero: beds / baths / sqft */
        $heroBeds     = $str('bedrooms')  ?: null;
        $heroBaths    = $str('bathrooms') ?: null;
        $heroSqft     = $str('minimum_heated_square') ?: $str('minimum_heated_sqft') ?: null;
        $heroPropType = $str('property_type') ?: null;
        $heroStatus   = $str('listing_status') ?: null;
        $heroListDate = $fmtDate($str('listing_date'));
        $heroUpdDate  = $auction->updated_at ? \Carbon\Carbon::parse($auction->updated_at)->format('F j, Y') : null;

        /* Hero badges */
        $heroOfFin       = $arr('offered_financing');
        $badgeFinancing  = count($heroOfFin) > 0;
        $badgeCash       = in_array('Cash', $heroOfFin);
        $badgeLeaseOpt   = in_array('Lease Option', $heroOfFin);
        $badgeLeasePur   = in_array('Lease Purchase', $heroOfFin);
        $badgeCrypto     = in_array('Cryptocurrency', $heroOfFin);
        $badgeAssumable  = in_array('Assumable Mortgage', $heroOfFin);
        $badgeSellerFin  = in_array('Seller Financing', $heroOfFin);
        $badgeExchange   = in_array('Exchange', $heroOfFin) || in_array('Trade', $heroOfFin);
        $badgePreApproved = (strtolower((string)($str('pre_approved'))) === 'yes' || strtolower((string)($str('pre_approved'))) === '1' || strtolower((string)($str('pre_approved'))) === 'true');

        $heroBadges = [
            ['show' => $badgePreApproved,   'label' => 'Pre-Approved',      'icon' => 'fa-solid fa-circle-check',        'color' => 'green'],
            ['show' => $badgeCash,          'label' => 'Cash Buyer',        'icon' => 'fa-solid fa-money-bill-wave',      'color' => 'green'],
            ['show' => $badgeAssumable,     'label' => 'Assumable Mortgage','icon' => 'fa-solid fa-hand-holding-dollar',  'color' => 'teal'],
            ['show' => $badgeSellerFin,     'label' => 'Seller Financing',  'icon' => 'fa-solid fa-hand-holding-dollar',  'color' => 'teal'],
            ['show' => $badgeLeaseOpt,      'label' => 'Lease Option',      'icon' => 'fa-solid fa-key',                 'color' => 'purple'],
            ['show' => $badgeLeasePur,      'label' => 'Lease Purchase',    'icon' => 'fa-solid fa-key',                 'color' => 'purple'],
            ['show' => $badgeCrypto,        'label' => 'Crypto Accepted',   'icon' => 'fa-brands fa-bitcoin',            'color' => 'amber'],
            ['show' => $badgeExchange,      'label' => 'Exchange / Trade',  'icon' => 'fa-solid fa-arrows-rotate',       'color' => 'amber'],
            ['show' => $badgeFinancing && !$badgeCash && !$badgeAssumable && !$badgeSellerFin && !$badgeLeaseOpt && !$badgeLeasePur && !$badgeCrypto && !$badgeExchange,
                       'label' => 'Flexible Financing', 'icon' => 'fa-solid fa-hand-holding-dollar', 'color' => 'blue'],
            ['show' => (bool)$heroPropType, 'label' => (string)$heroPropType, 'icon' => 'fa-solid fa-tag', 'color' => 'blue'],
        ];
        $heroBadgesDisplay = array_slice(array_values(array_filter($heroBadges, fn($b) => $b['show'])), 0, 5);
    @endphp

    {{-- HERO --}}
    <div class="bol-hero mb-4">
        <div class="row g-0" style="min-height:280px;">
            <div class="col-lg-8">
                <div class="bol-hero-placeholder">
                    <i class="fa-solid fa-magnifying-glass-dollar"></i>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="bol-hero-summary">
                    @if($heroPrice)
                        <div class="bol-hero-price">{{ $heroPrice }}</div>
                        <div class="bol-hero-sub">Max Purchase Budget</div>
                    @endif

                    <div class="bol-hero-title">{{ $listingTitle }}</div>

                    @php
                        $locationParts = array_filter([
                            $str('property_city') ?: null,
                            $str('property_county') ? $str('property_county') . ' County' : null,
                            $str('property_state') ?: null,
                        ]);
                        // Also try cities/counties arrays
                        if (empty($locationParts)) {
                            $citiesArr = $arr('cities');
                            if (count($citiesArr)) $locationParts[] = implode(', ', array_slice($citiesArr, 0, 2));
                            $countiesArr = $arr('counties');
                            if (count($countiesArr)) $locationParts[] = $countiesArr[0] . ' County';
                        }
                        $locationDisplay = implode(', ', $locationParts);
                    @endphp
                    @if($locationDisplay)
                        <div class="bol-hero-sub"><i class="fa-solid fa-location-dot me-1" style="color:#2563eb;"></i>{{ $locationDisplay }}</div>
                    @endif

                    <div class="bol-hero-meta">
                        @if($heroBeds)
                            <span class="bol-hero-meta-item"><i class="fa-solid fa-bed"></i>{{ $heroBeds }} Bed{{ $heroBeds != '1' ? 's' : '' }}</span>
                        @endif
                        @if($heroBaths)
                            <span class="bol-hero-meta-item"><i class="fa-solid fa-bath"></i>{{ $heroBaths }} Bath{{ $heroBaths != '1' ? 's' : '' }}</span>
                        @endif
                        @if($heroSqft)
                            <span class="bol-hero-meta-item"><i class="fa-solid fa-ruler-combined"></i>{{ number_format((int)preg_replace('/[^0-9]/','',$heroSqft)) }}+ Sq Ft</span>
                        @endif
                        @if($heroPropType)
                            <span class="bol-hero-meta-item"><i class="fa-solid fa-tag"></i>{{ $heroPropType }}</span>
                        @endif
                    </div>

                    @if($heroStatus)
                        <div><span class="bol-hero-status"><i class="fa-solid fa-circle-check"></i>{{ $heroStatus }}</span></div>
                    @endif

                    @if($heroListDate || $heroUpdDate)
                        <div class="bol-hero-dates">
                            @if($heroListDate)<span>Listed: {{ $heroListDate }}</span>@endif
                            @if($heroListDate && $heroUpdDate)<span class="mx-1">·</span>@endif
                            @if($heroUpdDate)<span>Updated: {{ $heroUpdDate }}</span>@endif
                        </div>
                    @endif

                    <div class="bol-hero-badges">
                        @foreach ($heroBadgesDisplay as $b)
                            <span class="bol-badge bol-badge-{{ $b['color'] }}"><i class="{{ $b['icon'] }}"></i> {{ $b['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TWO-COLUMN LAYOUT --}}
    <div class="row g-4 align-items-start">

        {{-- Main content column --}}
        <div class="col-lg-9 bol-main-content-wrap">

    {{-- SMOOTH-SCROLL NAV TABS --}}
    <div class="bol-nav-tabs-wrap">
        <ul class="bol-nav-tabs">
            <li><a href="#section-overview">Overview</a></li>
            <li><a href="#section-criteria">Purchase Criteria</a></li>
            <li><a href="#section-financing">Financing</a></li>
            <li><a href="#section-features">Property Features</a></li>
            <li><a href="#section-compensation">Compensation</a></li>
            <li><a href="#section-contact">Contact</a></li>
        </ul>
    </div>

    {{-- Listing Overview --}}
    <div class="card section-card" id="section-overview">
        <div class="card-header"><i class="fa-solid fa-list-check me-2"></i>Listing Overview</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Listing Title', $str('listing_title') ?: $auction->title) !!}
                    {!! $row('Auction Type', $str('auction_type')) !!}
                    {!! $row('Listing Status', $str('listing_status')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Listing Date', $fmtDate($str('listing_date'))) !!}
                    {!! $row('Expiration Date', $fmtDate($str('expiration_date'))) !!}
                    {!! $row('Bidding Period', $str('auction_time')) !!}
                </div>
            </div>
            @if($val('additional_details') || $val('preferance_details'))
            <hr>
            @if($val('additional_details'))
            <div class="mb-2">
                <div class="field-label mb-1">Additional Details</div>
                <p class="field-value mb-0">{!! nl2br(e($val('additional_details'))) !!}</p>
            </div>
            @endif
            @if($val('preferance_details'))
            <div class="mb-0">
                <div class="field-label mb-1">Preference Details</div>
                <p class="field-value mb-0">{!! nl2br(e($val('preferance_details'))) !!}</p>
            </div>
            @endif
            @endif
        </div>
    </div>

    {{-- Purchase Criteria --}}
    <div class="card section-card" id="section-criteria">
        <div class="card-header"><i class="fa-solid fa-magnifying-glass-dollar me-2"></i>Purchase Criteria</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Property Type', $str('property_type')) !!}
                    {!! $row('Bedrooms', $orOther($str('bedrooms'), $str('other_bedrooms'))) !!}
                    {!! $row('Bathrooms', $orOther($str('bathrooms'), $str('other_bathrooms'))) !!}
                    {!! $row('Min. Heated Sq Ft', $str('minimum_heated_square') ?: $str('minimum_heated_sqft')) !!}
                    {!! $row('Min. Leaseable Sq Ft', $str('minimum_leaseable')) !!}
                    {!! $row('Min. Acreage', $str('min_acreage') ?: $str('total_acreage')) !!}
                    {!! $row('Max Purchase Budget', $fmtMoney($str('maximum_budget') ?: $str('buyer_budget'))) !!}
                    {!! $row('Max Purchase Price', $fmtMoney($str('max_purchase_price') ?: $str('purchase_price'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Target Closing Date', $str('target_closing_date') ?: $fmtDate($str('target_closing_date'))) !!}
                    {!! $row('Desired Hire Date', $fmtDate($str('desired_agent_hire_date'))) !!}
                    {!! $row('Working with an Agent', $str('working_with_agent')) !!}
                    {!! $row('Sale Provision', $orOther($str('sale_provision'), $str('sale_provision_other'))) !!}
                    {!! $row('Buyer Sell Contract Required', $str('buyer_sell_contract')) !!}
                    {!! $row('Number of Occupants', $str('number_occupant') ?: $str('occupant_types')) !!}
                    {!! $row('Purchase Purpose', $str('purchase_purpose')) !!}
                </div>
            </div>

            @php
                $prefLocations = [];
                $citiesArr   = $arr('cities');
                $countiesArr = $arr('counties');
                $stateVal    = $str('property_state') ?: $str('state');
                if (count($citiesArr))   $prefLocations[] = 'Cities: ' . implode(', ', $citiesArr);
                if (count($countiesArr)) $prefLocations[] = 'Counties: ' . implode(', ', $countiesArr);
                if ($stateVal)           $prefLocations[] = 'State: ' . $stateVal;
            @endphp
            @if(count($prefLocations))
            <hr>
            <h6 class="fw-semibold mb-2">Preferred Locations</h6>
            <div class="row">
                @foreach($prefLocations as $loc)
                <div class="col-md-12 mb-1"><span class="field-value">{{ $loc }}</span></div>
                @endforeach
            </div>
            @endif

            @php $propCondBuyer = $subOther($arr('condition_prop_buyer'), $str('other_property_condition')); @endphp
            @if(count($propCondBuyer))
            <hr>
            <div class="row">
                <div class="col-md-6">{!! $row('Acceptable Property Conditions', implode(', ', $propCondBuyer)) !!}</div>
            </div>
            @endif

            @php $propItems = $subOther($arr('property_items'), $str('other_property_items')); @endphp
            @if(count($propItems))
            <hr>
            <div class="mb-1"><span class="field-label">Desired Property Items / Style</span></div>
            <p class="field-value">{{ implode(', ', $propItems) }}</p>
            @endif

            {{-- Commute / HOA / Flood Zone preferences --}}
            @php
                $dnaFields = array_filter([
                    ['Commute Destination ZIP',   $str('commute_destination_zip')],
                    ['Max Commute (minutes)',      $str('max_commute_minutes')],
                    ['Commute Mode',              $str('commute_mode')],
                    ['HOA Acceptance',            $str('hoa_acceptance')],
                    ['Max Monthly HOA Fee',       $fmtMoney($str('hoa_max_monthly_fee'))],
                    ['Flood Zone Tolerance',      $str('flood_zone_tolerance')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($dnaFields))
            <hr>
            <h6 class="fw-semibold mb-2">Lifestyle &amp; Location Preferences</h6>
            <div class="row">
                @foreach($dnaFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Financing Details --}}
    <div class="card section-card" id="section-financing">
        <div class="card-header"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Financing Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    @php $ofFin = $subOther($arr('offered_financing'), $str('other_financing')); @endphp
                    @if(count($ofFin)) {!! $row('Financing Types', implode(', ', $ofFin)) !!} @endif
                    {!! $row('Buyer Pre-Approved', $str('pre_approved')) !!}
                    {!! $row('Pre-Approval Amount', $fmtMoney($str('pre_approval_amount'))) !!}
                    {!! $row('Cash Budget', $fmtMoney($str('cash_budget'))) !!}
                </div>
                <div class="col-md-6">
                    @php
                        $_dpType = $str('down_payment_type');
                        $_dpAmt  = $_dpType === '%' ? $fmtPercent($str('down_payment_amount')) : $fmtMoney($str('down_payment_amount'));
                    @endphp
                    {!! $row('Down Payment Amount', $_dpAmt) !!}
                    {!! $row('Sale Provision (Assignment)', $str('sale_provision_assignment')) !!}
                    {!! $row('Assignment Fee', $str('assignment_fee_type') === '$' ? $fmtMoney($str('assignment_fee_amount')) : ($str('assignment_fee_type') === '%' ? $fmtPercent($str('assignment_fee_amount')) : $str('assignment_fee_amount'))) !!}
                </div>
            </div>

            @php
                $ofFinArr     = $arr('offered_financing');
                $hasCash      = in_array('Cash', $ofFinArr);
                $hasSellerFin = in_array('Seller Financing', $ofFinArr);
                $hasAssumable = in_array('Assumable Mortgage', $ofFinArr);
                $hasCrypto    = in_array('Cryptocurrency', $ofFinArr);
                $hasExchange  = in_array('Exchange/Trade', $ofFinArr);
                $hasLeaseOpt  = in_array('Lease Option', $ofFinArr);
                $hasLeasePur  = in_array('Lease Purchase', $ofFinArr);
                $hasNFT       = in_array('Non-Fungible Token (NFT)', $ofFinArr);
            @endphp

            {{-- Seller Financing --}}
            @if($hasSellerFin || $str('interest_rate'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Seller Financing Terms Sought</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Interest Rate', $str('interest_rate') ? $fmtPercent($str('interest_rate')) : null) !!}
                    {!! $row('Loan Duration (Years)', $str('loan_duration')) !!}
                    {!! $row('Prepayment Penalty Amount', $fmtMoney($str('prepayment_penalty_amount'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Balloon Payment', $yesNo($str('balloon_payment'))) !!}
                    {!! $row('Balloon Payment Amount', $fmtMoney($str('balloon_payment_amount'))) !!}
                    {!! $row('Balloon Payment Date', $str('balloon_payment_date')) !!}
                </div>
            </div>
            @endif

            {{-- Assumable --}}
            @if($hasAssumable || $str('assumable_terms'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Assumable Mortgage</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Assumable Terms', $str('assumable_terms')) !!}
                    {!! $row('Assumable Loan Type', $str('assumable_loan_type')) !!}
                    {!! $row('Max Assumable Rate', $str('max_assumable_rate') ? $fmtPercent($str('max_assumable_rate')) : null) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Max Monthly Payment (P&I)', $fmtMoney($str('max_monthly_payment'))) !!}
                    {!! $row('Gap Payment Amount', $str('gap_payment_type') === '$' ? $fmtMoney($str('gap_payment_amount')) : $fmtPercent($str('gap_payment_amount'))) !!}
                </div>
            </div>
            @endif

            {{-- Lease Option --}}
            @if($hasLeaseOpt || $str('lease_option_price'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Lease Option</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Option Purchase Price', $fmtMoney($str('lease_option_price'))) !!}
                    {!! $row('Monthly Payment', $fmtMoney($str('lease_option_payment'))) !!}
                    {!! $row('Duration (Months)', $str('lease_option_duration')) !!}
                    {!! $row('Option Fee', $yesNo($str('has_option_fee'))) !!}
                    {!! $row('Option Fee Amount', $fmtMoney($str('option_fee_amount'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Option Fee Credit', $str('lease_option_fee_credit')) !!}
                    {!! $row('Option Fee Credit %', $str('lease_option_fee_credit_percentage') ? $fmtPercent($str('lease_option_fee_credit_percentage')) : null) !!}
                    {!! $row('Conditions', $str('lease_option_conditions')) !!}
                    {!! $row('Terms', $str('lease_option_terms')) !!}
                </div>
            </div>
            @endif

            {{-- Lease Purchase --}}
            @if($hasLeasePur || $str('lease_purchase_price'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Lease Purchase</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Purchase Price', $fmtMoney($str('lease_purchase_price'))) !!}
                    {!! $row('Monthly Payment', $fmtMoney($str('lease_purchase_payment'))) !!}
                    {!! $row('Duration (Months)', $str('lease_purchase_duration')) !!}
                    {!! $row('Rent Credit Toward Purchase', $str('lease_purchase_rent_credit')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Non-Refundable Deposit', $fmtMoney($str('lease_purchase_deposit'))) !!}
                    {!! $row('Conditions', $str('lease_purchase_conditions')) !!}
                    {!! $row('Terms', $str('lease_purchase_terms')) !!}
                </div>
            </div>
            @endif

            {{-- Cryptocurrency --}}
            @if($hasCrypto || $str('cryptocurrency_type'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Cryptocurrency</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Cryptocurrency Type', $str('cryptocurrency_type')) !!}
                    {!! $row('Crypto % of Purchase Price', $str('crypto_percentage') ? $fmtPercent($str('crypto_percentage')) : null) !!}
                    {!! $row('Cash % of Purchase Price', $str('cash_percentage_crypto') ? $fmtPercent($str('cash_percentage_crypto')) : null) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Exchange Method', $str('crypto_exchange_method')) !!}
                    {!! $row('Custodian / Wallet', $str('crypto_custodian_wallet')) !!}
                    {!! $row('Transaction Fees Responsibility', $str('crypto_transaction_fees')) !!}
                </div>
            </div>
            @endif

            {{-- Exchange / Trade --}}
            @if($hasExchange || $str('exchange_item_value'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Exchange / Trade</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Exchange Item', $str('other_exchange_item')) !!}
                    {!! $row('Estimated Value', $fmtMoney($str('exchange_item_value'))) !!}
                    {!! $row('Condition of Exchange Item', $str('exchange_item_condition')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Additional Cash Required', $fmtMoney($str('additional_cash'))) !!}
                    {!! $row('Transfer Method', $str('exchange_transfer_method')) !!}
                    {!! $row('Liens / Encumbrances', $str('exchange_liens') . ($str('exchange_liens_details') ? ' – ' . $str('exchange_liens_details') : '')) !!}
                </div>
            </div>
            @endif

            {{-- NFT --}}
            @if($hasNFT || $str('nft_description'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Non-Fungible Token (NFT)</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('NFT Description', $str('nft_description')) !!}
                    {!! $row('NFT % of Purchase Price', $str('nft_percentage') ? $fmtPercent($str('nft_percentage')) : null) !!}
                    {!! $row('Cash % of Purchase Price', $str('cash_percentage_nft') ? $fmtPercent($str('cash_percentage_nft')) : null) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('NFT Valuation Method', $str('nft_valuation_method')) !!}
                    {!! $row('NFT Transfer Method', $str('nft_transfer_method')) !!}
                    {!! $row('Gas Fees Responsibility', $str('nft_gas_fees')) !!}
                </div>
            </div>
            @endif

        </div>
    </div>

    {{-- Desired Property Features --}}
    <div class="card section-card" id="section-features">
        <div class="card-header"><i class="fa-solid fa-house me-2"></i>Desired Property Features</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Garage', $str('garage_needed')) !!}
                    {!! $row('Garage Spaces', $orOther($str('garage_parking_spaces'), $str('other_garage_needed'))) !!}
                    {!! $row('Carport', $str('carport_needed')) !!}
                    {!! $row('Carport Spaces', $str('other_carport_needed')) !!}
                    {!! $row('Pool', $str('pool_needed')) !!}
                    @php
                        $poolTypeRaw  = $arr('pool_type');
                        $poolTypeList = [];
                        if (!empty($poolTypeRaw['community'])) $poolTypeList[] = 'Community';
                        if (!empty($poolTypeRaw['private']))   $poolTypeList[] = 'Private';
                    @endphp
                    {!! $row('Pool Type', count($poolTypeList) ? implode(', ', $poolTypeList) : null) !!}
                </div>
                <div class="col-md-6">
                    @php $viewPref = $subOther($arr('view_preference'), $str('other_preferences')); @endphp
                    @if(count($viewPref)) {!! $row('View Preference', implode(', ', $viewPref)) !!} @endif
                    {!! $row('Leasing Space', $str('leasing_space')) !!}
                    {!! $row('Age-Restricted (55+)', $str('leasing_55_plus')) !!}
                    {!! $row('Real Estate Purchase Included', $str('real_estate_purchase')) !!}
                    {!! $row('Min. Annual Net Income', $fmtMoney($str('minimum_annual_net_income'))) !!}
                    {!! $row('Min. Cap Rate', $str('minimum_cap_rate') ? $fmtPercent($str('minimum_cap_rate')) : null) !!}
                </div>
            </div>

            @php $nonNegAmenities = $subOther($arr('non_negotiable_amenities'), $str('other_non_negotiable_amenities')); @endphp
            @if(count($nonNegAmenities))
            <hr>
            <div class="row">
                <div class="col-md-12">{!! $row('Non-Negotiable Amenities', implode(', ', $nonNegAmenities)) !!}</div>
            </div>
            @endif

            @php
                $unitFields = array_filter([
                    ['Number of Units',        $orOther($str('number_of_unit'), $str('number_of_unit_other'))],
                    ['Unit Type',              $str('number_of_unit_type_other') ?: (count($arr('number_of_unit_type')) ? implode(', ', $arr('number_of_unit_type')) : null)],
                    ['Unit Size',              $orOther($str('unit_size'), $str('unit_size_other'))],
                    ['Property Criteria',      $str('property_criteria')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($unitFields))
            <hr>
            <div class="row">
                @foreach($unitFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Broker Compensation --}}
    <div class="card section-card" id="section-compensation">
        <div class="card-header"><i class="fa-solid fa-file-contract me-2"></i>Broker Compensation &amp; Agency Agreement</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Commission Structure', $str('commission_structure')) !!}
                    {!! $row('Brokerage Relationship', $str('brokerage_relationship')) !!}
                    {!! $row('Agency Agreement Timeframe', $orOther($str('agency_agreement_timeframe'), $str('agency_agreement_custom'))) !!}
                    {!! $row('Protection Period (Days)', $str('protection_period')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Retainer Fee', $yesNo($str('retainer_fee_option'))) !!}
                    {!! $row('Retainer Fee Amount', $fmtMoney($str('retainer_fee_amount'))) !!}
                    {!! $row('Retainer Fee Application', $str('retainer_fee_application')) !!}
                    {!! $row('Early Termination Fee', $yesNo($str('early_termination_fee_option'))) !!}
                    {!! $row('Early Termination Fee Amount', $fmtMoney($str('early_termination_fee_amount'))) !!}
                    {!! $row('Additional Broker Details', $str('additional_details_broker')) !!}
                </div>
            </div>

            @php
                $purchaseFeeType = $str('purchase_fee_type');
                $purchaseFeeDisplay = null;
                if ($purchaseFeeType === 'percentage') $purchaseFeeDisplay = $fmtPercent($str('purchase_fee_percentage'));
                elseif ($purchaseFeeType === 'flat') $purchaseFeeDisplay = $fmtMoney($str('purchase_fee_flat'));
                elseif ($purchaseFeeType === 'combo') {
                    $_pct = $fmtPercent($str('purchase_fee_percentage_combo'));
                    $_flt = $fmtMoney($str('purchase_fee_flat_combo'));
                    $purchaseFeeDisplay = ($_pct && $_flt) ? $_pct . ' + ' . $_flt : ($_pct ?: $_flt);
                } elseif ($purchaseFeeType === 'other') $purchaseFeeDisplay = $str('purchase_fee_other');
            @endphp
            @if($purchaseFeeType || $purchaseFeeDisplay)
            <hr>
            <div class="row">
                <div class="col-md-6">{!! $row("Buyer's Broker Purchase Fee Type", $purchaseFeeType) !!}</div>
                <div class="col-md-6">{!! $row("Buyer's Broker Purchase Fee", $purchaseFeeDisplay) !!}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Contact --}}
    <div class="card section-card" id="section-contact">
        <div class="card-header"><i class="fa-solid fa-address-card me-2"></i>Contact Information</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    @php
                        $contactName = trim(($str('first_name') . ' ' . $str('last_name')));
                    @endphp
                    {!! $row('Name', $contactName ?: null) !!}
                    {!! $row('Email', $str('email')) !!}
                    {!! $row('Phone', $str('phone_number')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Brokerage', $str('agent_brokerage')) !!}
                    {!! $row('License Number', $str('agent_license_number')) !!}
                    {!! $row('NAR Member ID', $str('agent_nar_member_id')) !!}
                </div>
            </div>
        </div>
    </div>

        </div>{{-- /col-lg-9 --}}

        {{-- Sticky sidebar --}}
        <div class="col-lg-3 d-none d-lg-block">
            <div class="bol-sticky-card">
                <div class="bol-sticky-title"><i class="fa-solid fa-bolt me-1"></i>Quick Info</div>

                @if($heroPrice)
                <div class="mb-3 pb-3" style="border-bottom:1px solid #f1f5f9;">
                    <div style="font-size:0.74rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Max Budget</div>
                    <div style="font-size:1.4rem;font-weight:800;color:#1e293b;letter-spacing:-0.02em;">{{ $heroPrice }}</div>
                </div>
                @endif

                @if($heroBeds || $heroBaths || $heroSqft)
                <div class="mb-3 pb-3" style="border-bottom:1px solid #f1f5f9;">
                    @if($heroBeds)<div class="d-flex justify-content-between mb-1"><span style="font-size:.82rem;color:#64748b;">Bedrooms</span><span style="font-size:.82rem;font-weight:700;">{{ $heroBeds }}</span></div>@endif
                    @if($heroBaths)<div class="d-flex justify-content-between mb-1"><span style="font-size:.82rem;color:#64748b;">Bathrooms</span><span style="font-size:.82rem;font-weight:700;">{{ $heroBaths }}</span></div>@endif
                    @if($heroSqft)<div class="d-flex justify-content-between mb-1"><span style="font-size:.82rem;color:#64748b;">Min. Sq Ft</span><span style="font-size:.82rem;font-weight:700;">{{ number_format((int)preg_replace('/[^0-9]/','',$heroSqft)) }}+</span></div>@endif
                </div>
                @endif

                @if($heroPropType)
                <div class="mb-3 pb-3" style="border-bottom:1px solid #f1f5f9;">
                    <div style="font-size:0.74rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Property Type</div>
                    <div style="font-size:.88rem;font-weight:600;color:#1e293b;margin-top:2px;">{{ $heroPropType }}</div>
                </div>
                @endif

                @if($locationDisplay)
                <div class="mb-3 pb-3" style="border-bottom:1px solid #f1f5f9;">
                    <div style="font-size:0.74rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Location Preference</div>
                    <div style="font-size:.82rem;color:#334155;margin-top:3px;">{{ $locationDisplay }}</div>
                </div>
                @endif

                <a href="{{ route('offer.listing.buyer.searchListing') }}"
                   class="bol-action-btn bol-action-outline" style="justify-content:center;text-align:center;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Search
                </a>

                <button type="button" class="bol-action-btn bol-action-outline" id="bolShareBtn">
                    <i class="fa-solid fa-share-nodes"></i> Share Listing
                </button>

                <div style="margin-top:1rem;padding-top:0.75rem;border-top:1px solid #f1f5f9;">
                    <div style="font-size:0.74rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:0.5rem;">Activity</div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;color:#64748b;">
                        <span>Views</span><span style="font-weight:700;color:#94a3b8;">Coming Soon</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;color:#64748b;">
                        <span>Saves</span><span style="font-weight:700;color:#94a3b8;">Coming Soon</span>
                    </div>
                    @if($heroUpdDate)
                    <div class="d-flex justify-content-between" style="font-size:.78rem;color:#64748b;margin-top:.3rem;padding-top:.3rem;border-top:1px solid #f1f5f9;">
                        <span>Updated</span><span style="font-weight:700;color:#475569;">{{ $auction->updated_at ? \Carbon\Carbon::parse($auction->updated_at)->format('M j, Y') : '' }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>{{-- /col-lg-3 --}}

    </div>{{-- /row --}}

</div>{{-- /container --}}

{{-- Mobile sticky bottom bar --}}
<div class="bol-mobile-bar d-lg-none">
    <a href="{{ route('offer.listing.buyer.searchListing') }}" class="bol-mobile-bar-btn">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back</span>
    </a>
    <button class="bol-mobile-bar-btn" id="bolMobileShareBtn">
        <i class="fa-solid fa-share-nodes"></i>
        <span>Share</span>
    </button>
    @if(auth()->id() == $auction->user_id)
    <a href="{{ route('offer.listing.buyer.edit', ['auctionId' => $auction->id]) }}" class="bol-mobile-bar-btn">
        <i class="fa-solid fa-pen-to-square"></i>
        <span>Edit</span>
    </a>
    @endif
</div>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    /* Smooth scroll offset */
    document.querySelectorAll('.bol-nav-tabs a[href^="#"]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            var targetId = this.getAttribute('href').slice(1);
            var target = document.getElementById(targetId);
            if (!target) return;
            e.preventDefault();
            var offset = 82;
            var top = target.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });

    /* Share listing */
    function shareHandler() {
        var url = window.location.href;
        if (navigator.share) {
            navigator.share({ title: document.title, url: url }).catch(function () {});
        } else if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(function () {
                alert('Link copied to clipboard!');
            }).catch(function () { alert('Share: ' + url); });
        } else {
            alert('Share: ' + url);
        }
    }
    var shareBtn = document.getElementById('bolShareBtn');
    if (shareBtn) shareBtn.addEventListener('click', shareHandler);
    var mobileShareBtn = document.getElementById('bolMobileShareBtn');
    if (mobileShareBtn) mobileShareBtn.addEventListener('click', shareHandler);

})();
</script>
@endpush
