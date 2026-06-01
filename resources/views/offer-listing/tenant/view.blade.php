@extends('layouts.main')

@php
    $fmtMoney = function($v) {
        if ($v === null || $v === '' || is_array($v)) return null;
        $raw = preg_replace('/[^0-9.]/', '', (string)$v);
        if ($raw === '' || !is_numeric($raw)) return null;
        return '$' . number_format((float)$raw, 0);
    };
    $fmtPercent = function($v) {
        if ($v === null || $v === '' || is_array($v)) return null;
        $raw = preg_replace('/[^0-9.]/', '', (string)$v);
        if ($raw === '' || !is_numeric($raw)) return null;
        $num = (float)$raw;
        return (floor($num) == $num ? (string)(int)$num : (string)$num) . '%';
    };
    $fmtDate = function($v) {
        if ($v === null || $v === '' || is_array($v)) return null;
        try {
            return \Carbon\Carbon::parse((string)$v)->format('F j, Y');
        } catch (\Exception $e) {
            return null;
        }
    };
    $str = function($key) use ($meta) {
        $v = $meta[$key] ?? '';
        return is_array($v) ? implode(', ', $v) : (string)$v;
    };
    $arr = function($key) use ($meta) {
        $v = $meta[$key] ?? [];
        if (is_string($v)) {
            $d = json_decode($v, true);
            if (is_string($d)) { $d = json_decode($d, true); }
            return is_array($d) ? $d : [];
        }
        return is_array($v) ? $v : [];
    };
    $yesNo = function($v) {
        $s = strtolower(trim((string)$v));
        if (in_array($s, ['1','true','yes'])) return 'Yes';
        if (in_array($s, ['0','false','no']))  return 'No';
        return (string)$v;
    };
    // subOther: replace 'Other' entries with companion text; suppress 'Other' entirely when companion is empty;
    // deduplicate while preserving original selection order.
    $subOther = function(array $items, string $otherVal): array {
        $seen   = [];
        $result = [];
        foreach ($items as $v) {
            $norm = strtolower(trim((string)$v));
            if ($norm === 'other') {
                if ($otherVal === '') continue;           // suppress bare 'Other' with no companion
                $dedupeKey = strtolower($otherVal);
                if (!in_array($dedupeKey, $seen, true)) { $seen[] = $dedupeKey; $result[] = $otherVal; }
            } else {
                if (!in_array($norm, $seen, true)) { $seen[] = $norm; $result[] = $v; }
            }
        }
        return $result;
    };
    $orOther = function(string $primary, string $otherVal): ?string {
        if (strtolower(trim($primary)) === 'other') {
            return $otherVal !== '' ? $otherVal : null;  // suppress 'Other' with no companion
        }
        return $primary !== '' ? $primary : null;
    };
    $row = function($label, $value) {
        if ($value === null || $value === '' || $value === false) return '';
        return '<div class="row mb-2"><div class="col-md-5 text-muted fw-semibold">' . e($label) . '</div><div class="col-md-7" style="overflow-wrap:break-word;word-break:break-word;">' . e($value) . '</div></div>';
    };
    $ifFilled = fn($v) => ($v !== null && $v !== '' && $v !== false && !(is_array($v) && count($v) === 0));
    $joinParts = function($parts) {
        $parts = array_values(array_filter($parts, fn($p) => $p !== null && $p !== ''));
        return count($parts) ? implode(' + ', $parts) : null;
    };
    // "Other" companion: if primary is 'Other' and companion is filled, show companion; if primary is 'Other' and companion is empty, suppress entirely
    $resolveOtherField = function($primary, $companion) {
        $p = trim((string)$primary);
        $c = trim((string)$companion);
        if (strtolower($p) === 'other') {
            return $c !== '' ? $c : null;
        }
        return $p !== '' ? $p : null;
    };
    // dedupe: normalize, remove duplicates, preserve first-seen order
    $dedupe = function(array $items): array {
        $seen = [];
        $result = [];
        foreach ($items as $v) {
            $norm = strtolower(trim((string)$v));
            if ($norm !== '' && !in_array($norm, $seen, true)) {
                $seen[]   = $norm;
                $result[] = $v;
            }
        }
        return $result;
    };
@endphp

@push('styles')
<style>
/* ============================================================
   tcl-view-page — namespaced to Tenant Criteria Listing view only
   ============================================================ */
.tcl-view-page .section-card {
    margin-bottom: 1.75rem;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 6px rgba(0,0,0,.06);
    overflow: hidden;
}
.tcl-view-page .section-card .card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    font-weight: 700;
    font-size: 1.05rem;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    letter-spacing: -0.01em;
    color: #1e293b;
}
.tcl-view-page .section-card .card-body {
    padding: 1.25rem 1.5rem;
}
.tcl-view-page hr { border-color: #e9ecef; opacity: 0.6; margin: 1rem 0; }
.tcl-view-page .field-label { color: #64748b; font-weight: 600; font-size: 0.85rem; }
.tcl-view-page .field-value { font-size: 0.925rem; overflow-wrap: break-word; color: #1e293b; }
.tcl-view-page .section-card .card-body .row.mb-2 { margin-bottom: 0.65rem !important; }

/* Hero */
.tcl-view-page .tcl-hero {
    border-radius: 1rem; overflow: hidden; margin-bottom: 1.5rem;
    box-shadow: 0 4px 24px rgba(0,0,0,.10); background: #1e293b;
}
.tcl-view-page .tcl-hero-photo-placeholder {
    min-height: 280px;
    background: linear-gradient(135deg, #134e4a, #0f172a);
    display: flex; align-items: center; justify-content: center;
    color: #94a3b8; font-size: 3rem;
}
.tcl-view-page .tcl-hero-summary {
    background: #fff; padding: 1.6rem 1.4rem;
    display: flex; flex-direction: column; justify-content: space-between;
    height: 100%; min-height: 280px; gap: 0.1rem;
}
.tcl-view-page .tcl-hero-price { font-size: 1.85rem; font-weight: 800; color: #1e293b; letter-spacing: -0.03em; line-height: 1.15; }
.tcl-view-page .tcl-hero-address { color: #475569; font-size: 0.9rem; margin-top: 0.3rem; word-break: break-word; line-height: 1.45; }
.tcl-view-page .tcl-hero-meta { display: flex; flex-wrap: wrap; gap: 0.4rem 0.9rem; margin-top: 0.65rem; font-size: 0.84rem; color: #334155; }
.tcl-view-page .tcl-hero-meta-item i { color: #0d9488; margin-right: 4px; }
.tcl-view-page .tcl-hero-badges { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.75rem; }
.tcl-view-page .tcl-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.73rem; font-weight: 600; padding: 0.25rem 0.55rem;
    border-radius: 20px; border: 1px solid; white-space: nowrap;
}
.tcl-view-page .tcl-badge-teal   { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
.tcl-view-page .tcl-badge-blue   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.tcl-view-page .tcl-badge-green  { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.tcl-view-page .tcl-badge-purple { background: #faf5ff; color: #7c3aed; border-color: #ddd6fe; }
.tcl-view-page .tcl-badge-amber  { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.tcl-view-page .tcl-badge-rose   { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
.tcl-view-page .tcl-hero-status {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 0.8rem; font-weight: 700; padding: 0.28rem 0.7rem;
    border-radius: 20px; background: #dcfce7; color: #166534;
    border: 1px solid #86efac; margin-top: 0.6rem; white-space: nowrap;
}
.tcl-view-page .tcl-hero-dates { font-size: 0.76rem; color: #94a3b8; margin-top: 0.4rem; }
.tcl-view-page .tcl-hero-ctas {
    display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.9rem;
    padding-top: 0.9rem; border-top: 1px solid #f1f5f9;
}
.tcl-view-page .tcl-hero-ctas .btn { font-size: 0.8rem; font-weight: 600; padding: 0.42rem 0.75rem; border-radius: 8px; white-space: nowrap; flex-shrink: 0; }

/* Smooth-scroll nav tabs */
.tcl-view-page .tcl-nav-tabs-wrap { background: #fff; border-bottom: 2px solid #e2e8f0; margin-bottom: 1.75rem; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.tcl-view-page .tcl-nav-tabs { display: flex; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; gap: 0; list-style: none; padding: 0; margin: 0; }
.tcl-view-page .tcl-nav-tabs::-webkit-scrollbar { display: none; }
.tcl-view-page .tcl-nav-tabs li a {
    display: block; padding: 0.75rem 1.1rem; font-size: 0.82rem; font-weight: 600;
    color: #64748b; text-decoration: none; white-space: nowrap;
    border-bottom: 2px solid transparent; margin-bottom: -2px;
    transition: color .15s, border-color .15s; letter-spacing: 0.01em;
}
.tcl-view-page .tcl-nav-tabs li a:hover,
.tcl-view-page .tcl-nav-tabs li a.tcl-nav-active { color: #0d9488; border-bottom-color: #0d9488; }

/* Sticky desktop action card */
.tcl-view-page .tcl-sticky-card {
    position: sticky; top: 72px; background: #fff;
    border-radius: 0.75rem; border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0,0,0,.08); padding: 1.25rem 1rem;
}
.tcl-view-page .tcl-sticky-card .tcl-sticky-title {
    font-size: 0.78rem; font-weight: 700; color: #94a3b8;
    text-transform: uppercase; letter-spacing: 0.06em;
    margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f1f5f9;
}
.tcl-view-page .tcl-sticky-card .tcl-action-btn {
    display: flex; align-items: center; gap: 0.6rem; width: 100%;
    padding: 0.6rem 0.75rem; font-size: 0.83rem; font-weight: 600;
    border-radius: 8px; margin-bottom: 0.4rem; text-align: left;
    border: 1px solid transparent; cursor: pointer;
    transition: background .15s, border-color .15s; text-decoration: none;
}
.tcl-view-page .tcl-sticky-card .tcl-action-btn i { width: 18px; text-align: center; flex-shrink: 0; }
.tcl-view-page .tcl-action-primary { background: #0d9488; color: #fff; border-color: #0d9488; }
.tcl-view-page .tcl-action-primary:hover { background: #0f766e; color: #fff; }
.tcl-view-page .tcl-action-outline { background: #fff; color: #334155; border-color: #e2e8f0; }
.tcl-view-page .tcl-action-outline:hover { background: #f8fafc; border-color: #cbd5e1; }

/* Mobile sticky bottom bar */
.tcl-mobile-bar {
    display: none; position: fixed; bottom: 0; left: 0; right: 0; z-index: 1030;
    background: #fff; border-top: 1px solid #e2e8f0;
    box-shadow: 0 -4px 16px rgba(0,0,0,.10); padding: 0.5rem 1rem;
    padding-bottom: calc(0.5rem + env(safe-area-inset-bottom)); gap: 0.5rem;
}
.tcl-mobile-bar-btn {
    display: flex; flex-direction: column; align-items: center; gap: 3px; flex: 1;
    font-size: 0.65rem; font-weight: 700; color: #334155; text-decoration: none;
    padding: 0.4rem 0.25rem; border-radius: 8px; border: none;
    background: transparent; cursor: pointer; transition: background .15s;
    min-height: 52px; justify-content: center;
}
.tcl-mobile-bar-btn i { font-size: 1.15rem; color: #0d9488; }
.tcl-mobile-bar-btn:hover, .tcl-mobile-bar-btn:active { background: #f0fdfa; color: #1e293b; }
.tcl-mobile-bar-btn.tcl-mobile-primary { background: #0d9488 !important; color: #fff !important; border-radius: 10px; }
.tcl-mobile-bar-btn.tcl-mobile-primary i { color: #fff !important; }
.tcl-mobile-bar-btn.tcl-mobile-primary:hover { background: #0f766e !important; }
@media (max-width: 991.98px) {
    .tcl-mobile-bar { display: flex; }
    .tcl-main-content-wrap { padding-bottom: calc(80px + env(safe-area-inset-bottom)); }
}

.tcl-view-page h6.fw-semibold, .tcl-view-page h6.fw-bold {
    color: #1e293b; font-size: 0.97rem; font-weight: 600; letter-spacing: 0;
}
.tcl-view-page .tcl-contact-cta-row {
    display: flex; flex-wrap: wrap; gap: 0.5rem;
    margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;
}
</style>
@endpush

@section('content')
<div class="container py-4 tcl-view-page">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @php
        $listingTitle = $str('listing_title') ?: ($auction->title ?: 'Tenant Criteria Listing #' . $auction->id);
        $heroStatus   = $str('listing_status') ?: $auction->status ?: null;
        $heroListDate = $fmtDate($str('listing_date'));
        $heroUpdDate  = $auction->updated_at ? \Carbon\Carbon::parse($auction->updated_at)->format('F j, Y') : null;

        /* Rent budget / price for hero */
        $heroPrice = null;
        foreach (['budget','desired_rental_amount','maximum_budget','purchase_price'] as $_pk) {
            $_pv = $str($_pk);
            if ($_pv !== '' && $_pv !== null) { $heroPrice = $fmtMoney($_pv); break; }
        }

        /* Hero location */
        $heroState    = $str('state') ?: $str('property_state') ?: null;
        $heroCities   = $arr('cities');
        $heroCounties = $arr('counties');
        $heroZip      = $str('zip_codes') ?: $str('property_zip') ?: null;
        $heroPropType = $str('property_type') ?: null;
        $heroBeds     = $str('bedrooms') ?: null;
        $heroBaths    = $str('bathrooms') ?: null;
        $heroHSqft    = $str('minimum_heated_square') ?: null;

        $heroLocationParts = [];
        if ($heroCities && count($heroCities)) $heroLocationParts[] = implode(', ', array_slice($heroCities, 0, 2));
        elseif ($str('address')) $heroLocationParts[] = $str('address');
        if ($heroState) $heroLocationParts[] = $heroState;
        $heroLocation = implode(', ', array_filter($heroLocationParts));

        /* Badges */
        $ofFin = $arr('offered_financing');
        $heroBadges = array_values(array_filter([
            ['show' => count($heroCities) > 0,                          'label' => 'Location Flexible', 'icon' => 'fa-solid fa-location-dot',       'color' => 'teal'],
            ['show' => in_array('Lease Option', $ofFin),                'label' => 'Lease Option',      'icon' => 'fa-solid fa-key',                 'color' => 'purple'],
            ['show' => in_array('Lease Purchase', $ofFin),              'label' => 'Lease Purchase',    'icon' => 'fa-solid fa-key',                 'color' => 'purple'],
            ['show' => in_array('Cryptocurrency', $ofFin),              'label' => 'Crypto OK',         'icon' => 'fa-brands fa-bitcoin',            'color' => 'amber'],
            ['show' => (bool)$heroPropType,                             'label' => $heroPropType,        'icon' => 'fa-solid fa-tag',                 'color' => 'blue'],
            ['show' => $str('prior_eviction') === 'No',                 'label' => 'No Evictions',      'icon' => 'fa-solid fa-circle-check',        'color' => 'green'],
            ['show' => (bool)$heroStatus,                               'label' => $heroStatus,          'icon' => 'fa-solid fa-circle-check',        'color' => 'green'],
        ], fn($b) => $b['show']));
        $heroBadgesDisplay = array_slice($heroBadges, 0, 5);
    @endphp

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h2 class="mb-1 fw-bold" style="color:#1e293b;">{{ $listingTitle }}</h2>
            @if($heroLocation)
                <p class="text-muted mb-0"><i class="fa-solid fa-location-dot me-1"></i>{{ $heroLocation }}</p>
            @endif
        </div>
        @if(auth()->id() == $ownerId)
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('offer.listing.tenant.edit', ['auctionId' => $auction->id]) }}"
               class="btn btn-outline-primary">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
            </a>
        </div>
        @endif
    </div>

    {{-- ===== HERO SECTION ===== --}}
    <div class="tcl-hero mb-4">
        <div class="row g-0" style="min-height:280px;">
            <div class="col-lg-8">
                @php $heroPhotoFile = $str('photo') ?: null; @endphp
                @if($heroPhotoFile)
                    <img src="{{ asset('storage/' . $heroPhotoFile) }}"
                         alt="{{ $listingTitle }}"
                         style="width:100%;height:100%;min-height:280px;object-fit:cover;display:block;">
                @else
                <div class="tcl-hero-photo-placeholder">
                    <i class="fa-solid fa-person-shelter"></i>
                </div>
                @endif
            </div>
            <div class="col-lg-4">
                <div class="tcl-hero-summary">
                    @if($heroPrice)
                        <div class="tcl-hero-price">{{ $heroPrice }}</div>
                    @endif
                    @if($heroLocation)
                        <div class="tcl-hero-address"><i class="fa-solid fa-location-dot me-1" style="color:#0d9488;"></i>{{ $heroLocation }}</div>
                    @endif

                    <div class="tcl-hero-meta">
                        @if($heroBeds)
                            <span class="tcl-hero-meta-item"><i class="fa-solid fa-bed"></i>{{ $heroBeds }} Bed{{ $heroBeds != '1' ? 's' : '' }}</span>
                        @endif
                        @if($heroBaths)
                            <span class="tcl-hero-meta-item"><i class="fa-solid fa-bath"></i>{{ $heroBaths }} Bath{{ $heroBaths != '1' ? 's' : '' }}</span>
                        @endif
                        @if($heroHSqft)
                            <span class="tcl-hero-meta-item"><i class="fa-solid fa-ruler-combined"></i>{{ number_format((int)preg_replace('/[^0-9]/','',$heroHSqft)) }} Sq Ft (min)</span>
                        @endif
                        @if($heroPropType)
                            <span class="tcl-hero-meta-item"><i class="fa-solid fa-tag"></i>{{ $heroPropType }}</span>
                        @endif
                    </div>

                    @if($heroStatus)
                        <div>
                            <span class="tcl-hero-status">
                                <i class="fa-solid fa-circle-check"></i>{{ $heroStatus }}
                            </span>
                        </div>
                    @endif

                    @if($heroListDate || $heroUpdDate)
                        <div class="tcl-hero-dates">
                            @if($heroListDate)<span>Listed: {{ $heroListDate }}</span>@endif
                            @if($heroListDate && $heroUpdDate)<span class="mx-1">·</span>@endif
                            @if($heroUpdDate)<span>Updated: {{ $heroUpdDate }}</span>@endif
                        </div>
                    @endif

                    <div class="tcl-hero-badges">
                        @foreach ($heroBadgesDisplay as $b)
                            <span class="tcl-badge tcl-badge-{{ $b['color'] }}"><i class="{{ $b['icon'] }}"></i> {{ $b['label'] }}</span>
                        @endforeach
                    </div>

                    <div class="tcl-hero-ctas">
                        <a href="{{ route('offer.listing.tenant.searchListing') }}" class="btn btn-outline-secondary">
                            <i class="fa-solid fa-arrow-left me-1"></i>Back to Search
                        </a>
                        <button type="button" class="btn btn-outline-secondary" id="tclShareBtn">
                            <i class="fa-solid fa-share-nodes me-1"></i>Share
                        </button>
                        @if(auth()->id() == $ownerId)
                            <a href="{{ route('offer.listing.tenant.edit', ['auctionId' => $auction->id]) }}" class="btn btn-primary">
                                <i class="fa-solid fa-pen-to-square me-1"></i>Edit
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== TWO-COLUMN LAYOUT ===== --}}
    <div class="row g-4 align-items-start">

        {{-- Main content column --}}
        <div class="col-lg-9 tcl-main-content-wrap">

    {{-- ===== NAV SECTION VISIBILITY PRECOMPUTATION ===== --}}
    {{-- Computed once here so nav links only render for sections that will display. --}}
    @php
        $navHasRentalSection = $ifFilled($str('budget')) || $ifFilled($str('desired_rental_amount'))
            || $ifFilled($str('maximum_budget')) || $ifFilled($str('lease_length'))
            || $ifFilled($str('move_in_budget_upfront')) || $ifFilled($str('move_in_funds_available'))
            || $ifFilled($str('security_deposit_budget')) || count($dedupe($arr('desired_lease_length')))
            || $ifFilled($str('tenant_desired_lease_length'))
            || $ifFilled($str('move_in_date_earliest')) || $ifFilled($str('move_in_date_latest'))
            || count($subOther($arr('terms_of_lease'), $str('custom_lease_term')))
            || count($subOther($arr('tenant_pays'), $str('other_tenant_pays')))
            || count($subOther($arr('owner_pays'), $str('other_owner_pays')))
            || count($subOther($arr('rent_includes'), $str('other_rent_include')))
            || $ifFilled($str('interest_rate')) || $ifFilled($str('loan_duration'));

        $navHasLocation = ($str('state') || $str('property_state'))
            || count($dedupe($arr('cities'))) || count($dedupe($arr('counties')))
            || ($str('zip_codes') || $str('property_zip')) || $str('address');

        $navHasPropertySection =
            count($subOther($arr('property_type') ?: ($str('property_type') ? [$str('property_type')] : []), ''))
            || count($subOther($arr('property_items'), $str('other_property_items')))
            || count($subOther($arr('condition_prop_buyer'), $str('other_property_condition')))
            || count($dedupe($arr('leasing_spaces_tenant') ?: ($str('leasing_spaces') ? [$str('leasing_spaces')] : [])))
            || $ifFilled($str('bedrooms')) || $ifFilled($str('bathrooms'))
            || $ifFilled($str('minimum_heated_square')) || $ifFilled($str('total_square_feet'))
            || count($subOther($arr('non_negotiable_amenities'), $str('other_non_negotiable_amenities')))
            || count($subOther($arr('view_preference'), $str('other_preferences')))
            || count($subOther($arr('appliances'), $str('other_appliances')))
            || $ifFilled($str('pool_needed')) || count($dedupe($arr('pool_type')))
            || $ifFilled($str('leasing_55_plus')) || $ifFilled($str('minimum_leaseable'))
            || $ifFilled($str('min_acreage'));

        $navRawPets = $arr('pets') ?: ($str('pets') !== '' ? [$str('pets')] : []);
        $navHasPetsSection = count(array_filter(is_array($navRawPets) ? $navRawPets : []))
            || $ifFilled($str('number_of_pets')) || $ifFilled($str('number_of_occupants'))
            || $ifFilled($str('number_occupant')) || $ifFilled($str('breed_of_pets'))
            || $ifFilled($str('weight_of_pets')) || $ifFilled($str('service_animal'))
            || $ifFilled($str('support_animal')) || $ifFilled($str('pet_information'))
            || $ifFilled($str('parking_needed')) || $ifFilled($str('garage_needed'))
            || $ifFilled($str('has_breed_restrictions')) || $ifFilled($str('breed_restrictions'));

        $navHasParking = $ifFilled($str('garage_parking_spaces'))
            || count($dedupe($arr('garage_parking_spaces_option') ?: $arr('garage_parking_spaces_option_buyer')))
            || $ifFilled($str('other_parking_space_wrapper')) || $ifFilled($str('carport_spaces'))
            || $ifFilled($str('garage_spaces'));

        $navHasPrescreening = $ifFilled($str('prior_eviction')) || $ifFilled($str('prior_felony'))
            || $ifFilled($str('monthly_income')) || $ifFilled($str('screening_concerns'))
            || $ifFilled($str('current_status')) || $ifFilled($str('credit_score_range'))
            || $ifFilled($str('commute_destination_zip')) || $ifFilled($str('max_commute_minutes'))
            || $ifFilled($str('commute_mode')) || $ifFilled($str('rental_purpose'))
            || $ifFilled($str('smoking_preference')) || $ifFilled($str('accessibility_requirements'));

        $navHasLeasePrefs = count($subOther($arr('lease_for'), $str('other_lease_for')))
            || $ifFilled($str('utility_preference')) || $ifFilled($str('maintenance_preference'))
            || $ifFilled($str('renewal_option_requested')) || $ifFilled($str('renewal_option_details'))
            || $ifFilled($str('tenant_conditions')) || $ifFilled($str('additional_tenant_lease_terms'))
            || $ifFilled($str('occupied_until')) || $ifFilled($str('occupancy_status'))
            || count($dedupe($arr('tenant_require')))
            || $ifFilled($str('commercial_lease_type_preference')) || $ifFilled($str('cam_nnn_preference'))
            || $ifFilled($str('rent_escalation_preference')) || $ifFilled($str('intended_business_use'))
            || $ifFilled($str('buildout_tenant_improvement_request'))
            || $ifFilled($str('signage_request')) || $ifFilled($str('commercial_parking_access_needs'))
            || $ifFilled($str('personal_guarantee_preference')) || $ifFilled($str('commercial_approval_conditions'));

        $navHasContact = $ifFilled($str('first_name')) || $ifFilled($str('last_name'))
            || $ifFilled($str('email')) || $ifFilled($str('phone_number'))
            || $ifFilled($str('video_link')) || $ifFilled($str('video'));

        $hasBrokerComp = $ifFilled($str('commission_structure')) || $ifFilled($str('lease_fee_type'));
    @endphp

    {{-- ===== SMOOTH-SCROLL NAV TABS ===== --}}
    <div class="tcl-nav-tabs-wrap">
        <ul class="tcl-nav-tabs" id="tclNavTabs">
            <li><a href="#section-overview">Overview</a></li>
            @if($navHasRentalSection)<li><a href="#section-rental">Rental Criteria</a></li>@endif
            @if($navHasLocation)<li><a href="#section-location">Location</a></li>@endif
            @if($navHasPropertySection)<li><a href="#section-property">Property Features</a></li>@endif
            @if($navHasPetsSection)<li><a href="#section-pets">Pets &amp; Occupancy</a></li>@endif
            @if($navHasParking)<li><a href="#section-parking">Parking</a></li>@endif
            @if($navHasPrescreening)<li><a href="#section-prescreening">Pre-Screening</a></li>@endif
            @if($navHasLeasePrefs)<li><a href="#section-lease-prefs">Lease Preferences</a></li>@endif
            @if($hasBrokerComp)<li><a href="#section-broker-compensation">Broker Compensation</a></li>@endif
            @if($navHasContact)<li><a href="#section-contact">Contact</a></li>@endif
        </ul>
    </div>

    {{-- ===== LISTING OVERVIEW ===== --}}
    @php
        // Bidding Period countdown — calculated exclusively from created_at + auction_time
        $hasBPTimer = false;
        $timerRemainingSeconds = 0;
        // auction_type: read from EAV meta first; fall back to native column on the model
        $_auctionType = trim($str('auction_type'));
        if ($_auctionType === '') {
            $_auctionType = trim((string)($auction->auction_type ?? ''));
        }
        if (in_array(strtolower($_auctionType), ['bidding period', 'auction (timer)'])) {
            // auction_time: read from EAV meta first; fall back to native column / auction_length
            $_aTime = trim($str('auction_time'));
            if ($_aTime === '') {
                $_aTime = trim((string)($auction->auction_time ?? $auction->auction_length ?? ''));
            }
            $_timerEnd = null;
            if ($_aTime !== '') {
                $_parts = explode(' ', $_aTime);
                $_val   = (int)($_parts[0] ?? 0);
                $_unit  = strtolower($_parts[1] ?? 'days');
                if ($_val > 0) {
                    $_start = \Carbon\Carbon::parse($auction->created_at);
                    $_timerEnd = match(true) {
                        in_array($_unit, ['hour','hours'])     => $_start->addHours($_val),
                        in_array($_unit, ['week','weeks'])     => $_start->addWeeks($_val),
                        in_array($_unit, ['minute','minutes']) => $_start->addMinutes($_val),
                        default                               => $_start->addDays($_val),
                    };
                }
            }
            if (!empty($_timerEnd)) {
                $timerRemainingSeconds = (int)\Carbon\Carbon::now()->diffInSeconds($_timerEnd, false);
                $hasBPTimer = true;
            }
        }
    @endphp
    <div class="card section-card" id="section-overview">
        <div class="card-header"><i class="fa-solid fa-list-check me-2"></i>Listing Overview</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Listing Title', $listingTitle) !!}
                    {!! $row('Listing Type', $str('auction_type')) !!}
                    {!! $row('Listing Status', $heroStatus) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Listing Date', $fmtDate($str('listing_date'))) !!}
                    {!! $row('Expiration Date', $fmtDate($str('expiration_date'))) !!}
                    {!! $row('Auction Time', $str('auction_time') ?: (trim((string)($auction->auction_time ?? $auction->auction_length ?? '')))) !!}
                </div>
            </div>
            {{-- Bidding Period countdown timer (source: created_at + auction_time) --}}
            @if($hasBPTimer)
            <div class="mt-3 pt-3" style="border-top:1px solid #e2e8f0;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted fw-semibold" style="font-size:.85rem;">
                        <i class="fa-regular fa-clock me-1"></i>Bidding Period Time Remaining:
                    </span>
                    @if($timerRemainingSeconds <= 0)
                        <span class="badge bg-secondary" style="font-size:.85rem;">Expired</span>
                    @else
                        <span class="badge bg-info text-dark tcl-bp-timer"
                              data-seconds="{{ $timerRemainingSeconds }}"
                              style="font-size:.85rem;font-variant-numeric:tabular-nums;">
                            {{-- Initial PHP render — replaced by JS immediately --}}
                            @php
                                $_s = $timerRemainingSeconds;
                                if ($_s < 60) { echo $_s . 's Remaining'; }
                                else {
                                    $_d = intdiv($_s, 86400); $_s %= 86400;
                                    $_h = intdiv($_s, 3600);  $_s %= 3600;
                                    $_i = intdiv($_s, 60);
                                    $_p = [];
                                    if ($_d) $_p[] = $_d . 'd';
                                    if ($_h) $_p[] = $_h . 'h';
                                    if ($_i) $_p[] = $_i . 'm';
                                    echo implode(' ', $_p) . ' Remaining';
                                }
                            @endphp
                        </span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ===== RENTAL CRITERIA ===== --}}
    @php
        $rentalIncludes   = $subOther($arr('rent_includes'), $str('other_rent_include'));
        $tenantPays       = $subOther($arr('tenant_pays'), $str('other_tenant_pays'));
        $ownerPays        = $subOther($arr('owner_pays'), $str('other_owner_pays'));
        $termsOfLease     = $subOther($arr('terms_of_lease'), $str('custom_lease_term'));
        $desiredLeaseLen  = $dedupe($arr('desired_lease_length'));
        $offeredFinancing = $subOther($arr('offered_financing'), $str('other_financing'));

        $hasRentalSection = $ifFilled($str('budget')) || $ifFilled($str('desired_rental_amount'))
            || $ifFilled($str('maximum_budget')) || $ifFilled($str('lease_length'))
            || $ifFilled($str('move_in_budget_upfront')) || $ifFilled($str('move_in_funds_available'))
            || $ifFilled($str('security_deposit_budget')) || count($desiredLeaseLen)
            || $ifFilled($str('tenant_desired_lease_length'))
            || $ifFilled($str('move_in_date_earliest')) || $ifFilled($str('move_in_date_latest'))
            || count($termsOfLease) || count($tenantPays) || count($ownerPays)
            || count($rentalIncludes) || count($offeredFinancing)
            || $ifFilled($str('interest_rate')) || $ifFilled($str('loan_duration'));
    @endphp
    @if($hasRentalSection)
    <div class="card section-card" id="section-rental">
        <div class="card-header"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Rental Criteria</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Rent Budget', $fmtMoney($str('budget') ?: $str('desired_rental_amount') ?: $str('maximum_budget'))) !!}
                    {!! $row('Desired Lease Length', count($desiredLeaseLen) ? implode(', ', $desiredLeaseLen) : ($str('tenant_desired_lease_length') ?: $str('lease_length'))) !!}
                    {!! $row('Move-In Funds Available', $fmtMoney($str('move_in_funds_available') ?: $str('move_in_budget_upfront'))) !!}
                    {!! $row('First Month Rent Available', $yesNo($str('first_month_rent_available'))) !!}
                    {!! $row('Last Month Rent Available', $yesNo($str('last_month_rent_available'))) !!}
                    {!! $row('Security Deposit Budget', $fmtMoney($str('security_deposit_budget'))) !!}
                    {!! $row('Earliest Move-In Date', $fmtDate($str('move_in_date_earliest'))) !!}
                    {!! $row('Latest Move-In Date', $fmtDate($str('move_in_date_latest'))) !!}
                </div>
                <div class="col-md-6">
                    @if(count($termsOfLease)) {!! $row('Terms of Lease', implode(', ', $termsOfLease)) !!} @endif
                    @if(count($tenantPays)) {!! $row('Tenant Pays', implode(', ', $tenantPays)) !!} @endif
                    @if(count($ownerPays)) {!! $row('Owner Pays', implode(', ', $ownerPays)) !!} @endif
                    @if(count($rentalIncludes)) {!! $row('Rent Includes', implode(', ', $rentalIncludes)) !!} @endif
                    @if(count($offeredFinancing)) {!! $row('Offered Financing', implode(', ', $offeredFinancing)) !!} @endif
                </div>
            </div>

            {{-- Leasing Terms sub-section --}}
            @php
                $hasLeasingTerms = $ifFilled($str('lease_option_price')) || $ifFilled($str('lease_purchase_price'))
                    || $ifFilled($str('down_payment_amount')) || $ifFilled($str('interest_rate'))
                    || $ifFilled($str('loan_duration')) || $ifFilled($str('cryptocurrency_type'));
            @endphp
            @if($hasLeasingTerms)
            <hr>
            <h6 class="fw-semibold mt-3 mb-2" id="section-leasing">Leasing / Financing Terms</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Lease Option Price', $fmtMoney($str('lease_option_price'))) !!}
                    {!! $row('Lease Purchase Price', $fmtMoney($str('lease_purchase_price'))) !!}
                    {!! $row('Down Payment Amount', $fmtMoney($str('down_payment_amount'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Interest Rate', $str('interest_rate') ? $fmtPercent($str('interest_rate')) : null) !!}
                    {!! $row('Loan Duration (Years)', $str('loan_duration')) !!}
                    {!! $row('Cryptocurrency Type', $str('cryptocurrency_type')) !!}
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ===== LOCATION PREFERENCES ===== --}}
    @php
        $cities   = $dedupe($arr('cities'));
        $counties = $dedupe($arr('counties'));
        $zipCodes = $str('zip_codes') ?: $str('property_zip') ?: null;
        $address  = $str('address') ?: null;
        $stateVal = $str('state') ?: $str('property_state') ?: null;
        $hasLocation = $stateVal || count($cities) || count($counties) || $zipCodes || $address;
    @endphp
    @if($hasLocation)
    <div class="card section-card" id="section-location">
        <div class="card-header"><i class="fa-solid fa-map-location-dot me-2"></i>Location Preferences</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('State', $stateVal) !!}
                    {!! $row('Cities', count($cities) ? implode(', ', $cities) : null) !!}
                    {!! $row('Counties', count($counties) ? implode(', ', $counties) : null) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('ZIP Codes', $zipCodes) !!}
                    {!! $row('Address', $address) !!}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ===== DESIRED PROPERTY FEATURES ===== --}}
    @php
        $propTypes      = $subOther($arr('property_type') ?: ($str('property_type') ? [$str('property_type')] : []), '');
        $propItems      = $subOther($arr('property_items'), $str('other_property_items'));
        $conditionList  = $subOther($arr('condition_prop_buyer'), $str('other_property_condition'));
        $leasingSpaces  = $dedupe($arr('leasing_spaces_tenant') ?: ($str('leasing_spaces') ? [$str('leasing_spaces')] : []));
        $amenities      = $subOther($arr('non_negotiable_amenities'), $str('other_non_negotiable_amenities'));
        $viewPrefs      = $subOther($arr('view_preference'), $str('other_preferences'));
        $appliances     = $subOther($arr('appliances'), $str('other_appliances'));
        $poolTypes      = $dedupe($arr('pool_type'));

        $hasPropertySection = count($propTypes) || count($propItems) || count($conditionList)
            || count($leasingSpaces) || $ifFilled($str('bedrooms')) || $ifFilled($str('bathrooms'))
            || $ifFilled($str('minimum_heated_square')) || $ifFilled($str('total_square_feet'))
            || $ifFilled($str('sqft_heated_source')) || count($amenities)
            || count($viewPrefs) || count($appliances)
            || $ifFilled($str('pool_needed')) || count($poolTypes)
            || $ifFilled($str('leasing_55_plus')) || $ifFilled($str('minimum_leaseable'))
            || $ifFilled($str('min_acreage'));
    @endphp
    @if($hasPropertySection)
    <div class="card section-card" id="section-property">
        <div class="card-header"><i class="fa-solid fa-house me-2"></i>Desired Property Features</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    @if(count($propTypes)) {!! $row('Property Type', implode(', ', $propTypes)) !!} @endif
                    @if(count($conditionList)) {!! $row('Condition', implode(', ', $conditionList)) !!} @endif
                    @if(count($leasingSpaces)) {!! $row('Leasing Spaces', implode(', ', $leasingSpaces)) !!} @endif
                    {!! $row('Bedrooms', $resolveOtherField($str('bedrooms'), $str('other_bedrooms'))) !!}
                    {!! $row('Bathrooms', $resolveOtherField($str('bathrooms'), $str('other_bathrooms'))) !!}
                    {!! $row('Minimum Heated Sq Ft', $str('minimum_heated_square')) !!}
                    {!! $row('Minimum Leaseable Sq Ft', $str('minimum_leaseable')) !!}
                    {!! $row('Min Acreage', $str('min_acreage')) !!}
                    {!! $row('Total Sq Ft', $str('total_square_feet')) !!}
                    {!! $row('Sq Ft Source', $str('sqft_heated_source')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Age-Restricted (55+)', $yesNo($str('leasing_55_plus'))) !!}
                    {!! $row('Pool Needed', $yesNo($str('pool_needed'))) !!}
                    @if(count($poolTypes)) {!! $row('Pool Type', implode(', ', $poolTypes)) !!} @endif
                    @if(count($viewPrefs)) {!! $row('View Preferences', implode(', ', $viewPrefs)) !!} @endif
                    @if(count($appliances)) {!! $row('Appliances Needed', implode(', ', $appliances)) !!} @endif
                    @if(count($amenities)) {!! $row('Required Amenities', implode(', ', $amenities)) !!} @endif
                </div>
            </div>
            @if(count($propItems))
            <hr>
            <div class="mb-1"><span class="field-label">Property Items / Features</span></div>
            <p class="field-value mb-0">{{ implode(', ', $propItems) }}</p>
            @endif
        </div>
    </div>
    @endif

    {{-- ===== PETS & OCCUPANCY ===== --}}
    @php
        $petsRaw = $arr('pets') ?: ($str('pets') ? [$str('pets')] : []);
        if (!is_array($petsRaw)) $petsRaw = [$petsRaw];
        $petOther = $str('type_of_pets') ?: '';
        $petsDisplay = [];
        foreach ($petsRaw as $p) {
            if (strtolower(trim($p)) === 'other' && $petOther !== '') {
                $petsDisplay[] = $petOther;
            } elseif (strtolower(trim($p)) !== 'other') {
                $petsDisplay[] = $p;
            }
        }
        // If single non-array value
        if (!count($petsDisplay) && $str('pets') !== '') {
            $petsDisplay = [$resolveOtherField($str('pets'), $str('type_of_pets'))];
        }

        $hasPetsSection = count($petsDisplay) || $ifFilled($str('number_of_pets'))
            || $ifFilled($str('number_occupant')) || $ifFilled($str('number_occupants'))
            || $ifFilled($str('number_of_pets')) || $ifFilled($str('breed_of_pets'))
            || $ifFilled($str('weight_of_pets')) || $ifFilled($str('service_animal'))
            || $ifFilled($str('support_animal')) || $ifFilled($str('emotional_support_animal'))
            || $ifFilled($str('pet_information')) || $ifFilled($str('parking_needed'))
            || $ifFilled($str('garage_needed')) || $ifFilled($str('has_breed_restrictions'))
            || $ifFilled($str('breed_restrictions'));
    @endphp
    @if($hasPetsSection)
    <div class="card section-card" id="section-pets">
        <div class="card-header"><i class="fa-solid fa-paw me-2"></i>Pets &amp; Occupancy</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Pets', count($petsDisplay) ? implode(', ', array_filter($petsDisplay)) : null) !!}
                    {!! $row('Number of Pets', $str('number_of_pets')) !!}
                    {!! $row('Breed of Pets', $str('breed_of_pets')) !!}
                    {!! $row('Weight of Pets (lbs)', $str('weight_of_pets')) !!}
                    {!! $row('Pet Information', $str('pet_information')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Number of Occupants', $str('number_of_occupants') ?: $str('number_occupant') ?: $str('number_occupied')) !!}
                    {!! $row('Service Animal', $yesNo($str('service_animal'))) !!}
                    {!! $row('Support Animal', $yesNo($str('support_animal') ?: $str('emotional_support_animal'))) !!}
                    {!! $row('Breed Restrictions', $yesNo($str('has_breed_restrictions'))) !!}
                    {!! $row('Breed Restriction Details', $str('breed_restrictions')) !!}
                    {!! $row('Parking Needed', $resolveOtherField($str('carport_needed') ?: $str('garage_needed') ?: $str('parking_needed'), $str('other_carport_needed') ?: $str('other_garage_needed'))) !!}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ===== PARKING & AMENITIES ===== --}}
    @php
        $parkingOptions = $arr('garage_parking_spaces_option') ?: $arr('garage_parking_spaces_option_buyer');
        $hasParking = $ifFilled($str('garage_parking_spaces')) || count($parkingOptions)
            || $ifFilled($str('other_parking_space_wrapper')) || $ifFilled($str('carport_spaces'))
            || $ifFilled($str('garage_spaces'));
    @endphp
    @if($hasParking)
    <div class="card section-card" id="section-parking">
        <div class="card-header"><i class="fa-solid fa-car me-2"></i>Parking &amp; Amenities</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Parking Type / Details', $str('garage_parking_spaces')) !!}
                    {!! $row('Carport Spaces', $str('carport_spaces')) !!}
                    {!! $row('Garage Spaces', $str('garage_spaces')) !!}
                </div>
                <div class="col-md-6">
                    @if(count($parkingOptions)) {!! $row('Parking Features', implode(', ', $parkingOptions)) !!} @endif
                    {!! $row('Other Parking Details', $str('other_parking_space_wrapper')) !!}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ===== PRE-SCREENING / TENANT DETAILS ===== --}}
    @php
        $hasPrescreening = $ifFilled($str('prior_eviction')) || $ifFilled($str('prior_felony'))
            || $ifFilled($str('monthly_income')) || $ifFilled($str('screening_concerns'))
            || $ifFilled($str('current_status')) || $ifFilled($str('credit_score_range'))
            || $ifFilled($str('commute_destination_zip')) || $ifFilled($str('max_commute_minutes'))
            || $ifFilled($str('commute_mode')) || $ifFilled($str('rental_purpose'))
            || $ifFilled($str('smoking_preference')) || $ifFilled($str('accessibility_requirements'));
    @endphp
    @if($hasPrescreening)
    <div class="card section-card" id="section-prescreening">
        <div class="card-header"><i class="fa-solid fa-shield-check me-2"></i>Pre-Screening / Tenant Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Prior Eviction', $yesNo($str('prior_eviction'))) !!}
                    {!! $row('Prior Felony', $yesNo($str('prior_felony'))) !!}
                    {!! $row('Monthly Income', $fmtMoney($str('monthly_income'))) !!}
                    {!! $row('Min Annual Net Income', $fmtMoney($str('minimum_annual_net_income'))) !!}
                    {!! $row('Credit Score Range', $str('credit_score_range')) !!}
                    {!! $row('Credit / Screening Concerns', $str('screening_concerns')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Current Status', $str('current_status')) !!}
                    {!! $row('Rental Purpose', $str('rental_purpose')) !!}
                    {!! $row('Smoking Preference', $str('smoking_preference')) !!}
                    {!! $row('Accessibility Requirements', $str('accessibility_requirements')) !!}
                    {!! $row('Commute Destination ZIP', $str('commute_destination_zip')) !!}
                    {!! $row('Max Commute (minutes)', $str('max_commute_minutes')) !!}
                    {!! $row('Commute Mode', $yesNo($str('commute_mode'))) !!}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ===== LEASE PREFERENCES & CONDITIONS ===== --}}
    @php
        $leaseFor = $subOther($arr('lease_for'), $str('other_lease_for'));
        $tenantRequire = $dedupe($arr('tenant_require'));
        $hasLeasePrefs = count($leaseFor) || $ifFilled($str('utility_preference'))
            || $ifFilled($str('maintenance_preference')) || $ifFilled($str('renewal_option_requested'))
            || $ifFilled($str('renewal_option_details')) || $ifFilled($str('tenant_conditions'))
            || $ifFilled($str('additional_tenant_lease_terms')) || $ifFilled($str('occupied_until'))
            || $ifFilled($str('occupancy_status')) || count($tenantRequire)
            || $ifFilled($str('commercial_lease_type_preference')) || $ifFilled($str('cam_nnn_preference'))
            || $ifFilled($str('rent_escalation_preference')) || $ifFilled($str('buildout_tenant_improvement_request'))
            || $ifFilled($str('intended_business_use')) || $ifFilled($str('signage_request'))
            || $ifFilled($str('commercial_parking_access_needs')) || $ifFilled($str('personal_guarantee_preference'))
            || $ifFilled($str('commercial_approval_conditions'));
    @endphp
    @if($hasLeasePrefs)
    <div class="card section-card" id="section-lease-prefs">
        <div class="card-header"><i class="fa-solid fa-file-signature me-2"></i>Lease Preferences &amp; Conditions</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    @if(count($leaseFor)) {!! $row('Leasing For', implode(', ', $leaseFor)) !!} @endif
                    {!! $row('Utility Preference', $str('utility_preference')) !!}
                    {!! $row('Maintenance Preference', $str('maintenance_preference')) !!}
                    {!! $row('Renewal Option Requested', $yesNo($str('renewal_option_requested'))) !!}
                    {!! $row('Renewal Option Details', $str('renewal_option_details')) !!}
                    {!! $row('Occupancy Status', $str('occupancy_status')) !!}
                    {!! $row('Occupied Until', $str('occupied_until')) !!}
                    @if(count($tenantRequire)) {!! $row('Tenant Requirements', implode(', ', $tenantRequire)) !!} @endif
                </div>
                <div class="col-md-6">
                    {!! $row('Tenant Conditions', $str('tenant_conditions')) !!}
                    {!! $row('Additional Lease Terms', $str('additional_tenant_lease_terms')) !!}
                    {{-- Commercial-specific --}}
                    {!! $row('Commercial Lease Type', $str('commercial_lease_type_preference')) !!}
                    {!! $row('CAM / NNN Preference', $str('cam_nnn_preference')) !!}
                    {!! $row('Rent Escalation', $str('rent_escalation_preference')) !!}
                    {!! $row('Buildout / Tenant Improvement', $str('buildout_tenant_improvement_request')) !!}
                    {!! $row('Intended Business Use', $str('intended_business_use')) !!}
                    {!! $row('Signage Request', $str('signage_request')) !!}
                    {!! $row('Commercial Parking Needs', $str('commercial_parking_access_needs')) !!}
                    {!! $row('Personal Guarantee', $str('personal_guarantee_preference')) !!}
                    {!! $row('Commercial Approval Conditions', $str('commercial_approval_conditions')) !!}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ===== BROKER COMPENSATION & AGENCY AGREEMENT TERMS ===== --}}
    @if($hasBrokerComp)
    <div class="card section-card" id="section-broker-compensation">
        <div class="card-header"><i class="fa-solid fa-handshake me-2"></i>Broker Compensation & Agency Agreement Terms</div>
        <div class="card-body">
            {!! $row("Tenant's Broker Commission Structure", $str('commission_structure')) !!}
            @php
                $listingLeaseFeeType     = $str('lease_fee_type');
                $listingLeaseFeeCombined = null;

                if ($listingLeaseFeeType === 'Flat Fee' && $str('lease_fee_flat') !== '') {
                    $listingLeaseFeeCombined = $fmtMoney($str('lease_fee_flat'));
                } elseif ($listingLeaseFeeType === 'Percentage of the Gross Lease Value' && $str('lease_fee_percentage') !== '') {
                    $listingLeaseFeeCombined = $fmtPercent($str('lease_fee_percentage')) . ' of Gross Lease Value';
                } elseif ($listingLeaseFeeType === 'Percentage of Monthly Rent' && $str('lease_fee_percentage_monthly_rent') !== '') {
                    $_mDisplay = $fmtPercent($str('lease_fee_percentage_monthly_rent')) . ' of Monthly Rent';
                    if ($str('lease_fee_percentage_monthly_number') !== '') {
                        $_mDisplay .= ' x ' . $str('lease_fee_percentage_monthly_number') . ' Months';
                    }
                    $listingLeaseFeeCombined = $_mDisplay;
                } elseif ($listingLeaseFeeType === 'Flat Fee + Percentage of the Gross Lease Value') {
                    $listingLeaseFeeCombined = $joinParts([
                        $fmtMoney($str('lease_fee_flat_combo')),
                        $str('lease_fee_percentage_combo') !== '' ? ($fmtPercent($str('lease_fee_percentage_combo')) . ' of Gross Lease Value') : null,
                    ]);
                } elseif ($listingLeaseFeeType === 'Percentage of the Net Aggregate Rent' && $str('lease_fee_percentage_net') !== '') {
                    $listingLeaseFeeCombined = $fmtPercent($str('lease_fee_percentage_net')) . ' of Net Aggregate Rent';
                } elseif ($listingLeaseFeeType === 'Flat Fee + Percentage of the Net Aggregate Rent') {
                    $listingLeaseFeeCombined = $joinParts([
                        $fmtMoney($str('lease_fee_flat_combo_net')),
                        $str('lease_fee_percentage_combo_net') !== '' ? ($fmtPercent($str('lease_fee_percentage_combo_net')) . ' of Net Aggregate Rent') : null,
                    ]);
                } elseif (strtolower($listingLeaseFeeType) === 'other' && $str('lease_fee_other') !== '') {
                    $listingLeaseFeeCombined = $str('lease_fee_other');
                } elseif ($listingLeaseFeeType !== '') {
                    $listingLeaseFeeCombined = $listingLeaseFeeType;
                }
            @endphp
            {!! $row("Tenant's Broker Lease Fee", $listingLeaseFeeCombined) !!}
        </div>
    </div>
    @endif

    {{-- ===== CONTACT INFORMATION ===== --}}
    @php
        $hasContact = $ifFilled($str('first_name')) || $ifFilled($str('last_name'))
            || $ifFilled($str('email')) || $ifFilled($str('phone_number'))
            || $ifFilled($str('video_link')) || $ifFilled($str('video'));
    @endphp
    @if($hasContact)
    <div class="card section-card" id="section-contact">
        <div class="card-header"><i class="fa-solid fa-id-card me-2"></i>Contact Information</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Name', trim($str('first_name') . ' ' . $str('last_name'))) !!}
                    {!! $row('Email', $str('email')) !!}
                    @php
                        $phone = $str('phone_number');
                        if ($phone && strlen(preg_replace('/\D/', '', $phone)) === 10) {
                            $digits = preg_replace('/\D/', '', $phone);
                            $phone = '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6);
                        }
                    @endphp
                    {!! $row('Phone', $phone) !!}
                    {!! $row('Brokerage', $str('agent_brokerage')) !!}
                    {!! $row('License Number', $str('agent_license_number')) !!}
                    {!! $row('NAR Member ID', $str('agent_nar_member_id')) !!}
                </div>
                <div class="col-md-6">
                    {{-- Uploaded video file --}}
                    @if($ifFilled($str('video')))
                        <div class="mb-3">
                            <div class="field-label mb-1">Video</div>
                            <div class="ratio ratio-16x9" style="max-width:320px;border-radius:8px;overflow:hidden;">
                                <video controls style="width:100%;background:#000;">
                                    <source src="{{ asset('storage/' . $str('video')) }}">
                                    <a href="{{ asset('storage/' . $str('video')) }}" target="_blank" rel="noopener">Download Video</a>
                                </video>
                            </div>
                        </div>
                    @endif
                    {{-- External video link (YouTube / Vimeo / other) --}}
                    @if($ifFilled($str('video_link')))
                        @php
                            $vLink = $str('video_link');
                            $vEmbed = null;
                            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_\-]{11})/', $vLink, $vm)) {
                                $vEmbed = 'https://www.youtube.com/embed/' . $vm[1];
                            } elseif (preg_match('/vimeo\.com\/(\d+)/', $vLink, $vm)) {
                                $vEmbed = 'https://player.vimeo.com/video/' . $vm[1];
                            }
                        @endphp
                        @if($vEmbed)
                            <div class="ratio ratio-16x9 mb-2" style="max-width:320px;border-radius:8px;overflow:hidden;">
                                <iframe src="{{ $vEmbed }}" title="Video Walkthrough" allowfullscreen allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                            </div>
                        @else
                            <div class="row mb-2"><div class="col-md-5 text-muted fw-semibold">Video Link</div><div class="col-md-7"><a href="{{ $vLink }}" target="_blank" rel="noopener noreferrer">{{ $vLink }}</a></div></div>
                        @endif
                    @endif
                </div>
            </div>
            <div class="tcl-contact-cta-row">
                @if($ifFilled($str('email')))
                    <a href="mailto:{{ $str('email') }}" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-envelope me-1"></i>Contact Tenant
                    </a>
                @endif
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#tclQuestionModal">
                    <i class="fa-solid fa-circle-question me-1"></i>Ask a Question
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ===== ADDITIONAL DETAILS ===== --}}
    @if($ifFilled($str('additional_details')))
    <div class="card section-card" id="section-additional">
        <div class="card-header"><i class="fa-solid fa-align-left me-2"></i>Additional Details</div>
        <div class="card-body">
            <p class="field-value mb-0">{!! nl2br(e($str('additional_details'))) !!}</p>
        </div>
    </div>
    @endif

    {{-- ===== ADDITIONAL INFORMATION (remaining meta keys not covered by named sections) ===== --}}
    @php
        // Comprehensive list of every meta key explicitly rendered in the named sections above.
        // Any populated key NOT in this list will appear in the "Additional Information" fallback section.
        // RULE: only list keys that are (a) explicitly rendered in a named section above,
        // or (b) truly internal/system data that must never be shown. Every other populated
        // key falls through to the "Additional Information" section below.
        $knownKeys = [
            // --- system / purely internal (suppress from display) ---
            'workflow_type','title','_token','listing_title',
            'understand_terms','draft_version','parent_draft_id','draft_payload_hash',
            'listing_ai_faq','enable','fees','user_type','photo_enhancements',
            // --- overview section ---
            'auction_type','service_type','listing_status','status','listing_date','expiration_date','auction_time',
            // --- hire-agent workflow fields (never shown on public Tenant Criteria view) ---
            'working_with_agent','desired_agent_hire_date','meeting_Preference',
            'broker_fee_timing','broker_fee_days_from_rent',
            'interested_purchase_fee_type','interested_lease_option_agreement',
            'landlord_broker_flate_fee_type',
            'lease_type','lease_value','purchase_type','purchase_value',
            'protection_period',
            'early_termination_fee_option','early_termination_fee_amount',
            'retainer_fee_option','retainer_fee_amount','retainer_fee_application',
            'agency_agreement_timeframe','agency_agreement_custom',
            'brokerage_relationship','additional_details_broker',
            // --- broker compensation fields (section removed from public view) ---
            'commission_structure','lease_fee_type','lease_fee_flat_type','lease_fee_flat','lease_fee_percentage','lease_fee_other',
            'lease_fee_percentage_combo','lease_fee_flat_combo',
            'lease_fee_flat_combo_net','lease_fee_percentage_combo_net',
            'purchase_fee_type','purchase_fee_flat_type','purchase_fee_flat','purchase_fee_percentage','purchase_fee_other',
            'purchase_fee_percentage_combo','purchase_fee_flat_combo',
            'renewal_fee_type','referral_percentage',
            // --- private / sensitive (never public) ---
            'screening_concerns_explanation',
            // --- internal service/snapshot blobs ---
            'services','services_snapshot','other_services','flat_fee_services','other_services_enabled',
            // --- stale / internal-only fields ---
            'lease_date','compatibility_preferences',
            'assets','business_assets','sale_provision','down_payment_type','seller_financing_type',
            'assumable_fee_type','gap_payment_type','exchange_item','seller_lease_purchase_rent_credit_type',
            'lease_purchase_rent_credit_amount_type','assignment_fee_type','seller_late_fee_type',
            'number_of_unit_type','unit_type_configurations',
            'custom_services','include_marketing_fee',
            'number_of_showings_to_schedule','number_of_showings_to_attend','number_of_virtual_tours',
            'zipCodes',
            // --- rental criteria section ---
            'budget','desired_rental_amount','maximum_budget',
            'rent_includes','other_rent_include',
            'tenant_pays','other_tenant_pays',
            'owner_pays','other_owner_pays',
            'terms_of_lease','custom_lease_term',
            'desired_lease_length','tenant_desired_lease_length','lease_length',
            'offered_financing','other_financing',
            'move_in_budget_upfront','move_in_funds_available',
            'first_month_rent_available','last_month_rent_available','security_deposit_budget',
            'move_in_date_earliest','move_in_date_latest',
            // leasing / financing sub-section
            'lease_option_price','lease_purchase_price','down_payment_amount',
            'interest_rate','loan_duration','cryptocurrency_type',
            // --- location section ---
            'cities','counties','zip_codes','property_zip','address','state','property_state',
            // --- property features section ---
            'property_type','property_items','other_property_items',
            'condition_prop_buyer','other_property_condition',
            'leasing_spaces_tenant','leasing_spaces',
            'non_negotiable_amenities','other_non_negotiable_amenities',
            'bedrooms','other_bedrooms','bathrooms','other_bathrooms',
            'minimum_heated_square','minimum_leaseable','min_acreage',
            'total_square_feet','sqft_heated_source',
            'view_preference','other_preferences','appliances','other_appliances',
            'pool_needed','pool_type','leasing_55_plus',
            // --- pets & occupancy section ---
            'pets','type_of_pets','number_of_pets','breed_of_pets','weight_of_pets','pet_information',
            'number_of_occupants','number_occupant','number_occupied',
            'service_animal','support_animal','emotional_support_animal',
            'has_breed_restrictions','breed_restrictions',
            'carport_needed','garage_needed','parking_needed','other_carport_needed','other_garage_needed',
            // --- parking section ---
            'garage_parking_spaces_option','garage_parking_spaces_option_buyer',
            'garage_parking_spaces','carport_spaces','garage_spaces','other_parking_space_wrapper',
            // --- pre-screening section ---
            'prior_eviction','prior_felony','monthly_income','screening_concerns','current_status',
            'credit_score_range','commute_destination_zip','max_commute_minutes','commute_mode',
            'rental_purpose','smoking_preference','accessibility_requirements',
            'minimum_annual_net_income',
            // --- lease preferences section ---
            'lease_for','other_lease_for',
            'utility_preference','maintenance_preference',
            'renewal_option_requested','renewal_option_details',
            'tenant_conditions','additional_tenant_lease_terms',
            'occupied_until','occupancy_status','tenant_require',
            'commercial_lease_type_preference','cam_nnn_preference','rent_escalation_preference',
            'buildout_tenant_improvement_request','intended_business_use','signage_request',
            'commercial_parking_access_needs','personal_guarantee_preference','commercial_approval_conditions',
            // --- requested services section ---
            'list_criteria','list_criteria_fee','market_groups','market_groups_fee',
            'promote_social','promote_social_fee','launch_ads','launch_ads_fee',
            'schedule_showings','schedule_showings_fee','attend_showings','attend_showings_fee',
            'provide_virtual_tours','virtual_tours_fee',
            'assist_application','assist_application_fee',
            'collect_documents','collect_documents_fee',
            'submit_application','submit_application_fee',
            'review_lease','review_lease_fee',
            'provide_lease_form','provide_lease_form_fee',
            'coordinate_signing','coordinate_signing_fee',
            'total_flat_fee','total_marketing_fee',
            // --- contact / media section ---
            'first_name','last_name','email','phone_number',
            'agent_brokerage','agent_license_number','agent_nar_member_id',
            'video','video_link','photo',
            // --- additional details section ---
            'additional_details',
        ];

        // Labelizer: snake_case → Title Case Words
        $labelizeKey = function(string $key): string {
            return ucwords(str_replace('_', ' ', $key));
        };

        // Collect remaining populated keys
        $remainingFields = [];
        foreach ($meta as $mKey => $mVal) {
            if (in_array($mKey, $knownKeys, true)) continue;
            if ($mVal === null || $mVal === '' || $mVal === false) continue;
            if (is_array($mVal)) {
                $flat = array_filter($mVal, fn($v) => $v !== null && $v !== '');
                if (!count($flat)) continue;
                $flat = array_map(fn($v) => is_array($v) ? json_encode($v) : (string)$v, $flat);
                $remainingFields[$mKey] = implode(', ', $flat);
            } else {
                $s = trim((string)$mVal);
                if ($s === '') continue;
                $remainingFields[$mKey] = $s;
            }
        }
    @endphp
    @if(count($remainingFields))
    <div class="card section-card" id="section-remaining">
        <div class="card-header"><i class="fa-solid fa-ellipsis me-2"></i>Additional Information</div>
        <div class="card-body">
            <div class="row">
                @foreach($remainingFields as $rmKey => $rmVal)
                <div class="col-md-6">
                    {!! $row($labelizeKey($rmKey), $rmVal) !!}
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Button (bottom, owner only) --}}
    @if(auth()->id() == $ownerId)
    <div class="text-end mt-2 mb-4">
        <a href="{{ route('offer.listing.tenant.edit', ['auctionId' => $auction->id]) }}"
           class="btn btn-primary">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
        </a>
    </div>
    @endif

        </div>{{-- /col-lg-9 --}}

        {{-- ===== STICKY DESKTOP ACTION CARD ===== --}}
        <div class="col-lg-3 d-none d-lg-block">
            <div class="tcl-sticky-card">
                <div class="tcl-sticky-title">Quick Actions</div>

                @if($ifFilled($str('email')))
                <a href="mailto:{{ $str('email') }}" class="tcl-action-btn tcl-action-primary">
                    <i class="fa-solid fa-envelope"></i>Contact Tenant
                </a>
                @endif
                <button class="tcl-action-btn tcl-action-outline" data-bs-toggle="modal" data-bs-target="#tclQuestionModal">
                    <i class="fa-solid fa-circle-question"></i>Ask a Question
                </button>
                <button class="tcl-action-btn tcl-action-outline" id="tclShareBtnSidebar">
                    <i class="fa-solid fa-share-nodes"></i>Share Listing
                </button>
                <button class="tcl-action-btn tcl-action-outline" type="button" disabled style="cursor:default;opacity:.6;">
                    <i class="fa-regular fa-bookmark"></i>Save Listing
                </button>
                <a href="{{ route('offer.listing.tenant.searchListing') }}" class="tcl-action-btn tcl-action-outline">
                    <i class="fa-solid fa-arrow-left"></i>Back to Search
                </a>

                @if(auth()->id() == $ownerId)
                <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid #f1f5f9;">
                    <a href="{{ route('offer.listing.tenant.edit', ['auctionId' => $auction->id]) }}"
                       class="tcl-action-btn tcl-action-outline">
                        <i class="fa-solid fa-pen-to-square"></i>Edit Listing
                    </a>
                </div>
                @endif

                @if($heroPrice)
                <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #f1f5f9;text-align:center;">
                    <div style="font-size:0.72rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Rent Budget</div>
                    <div style="font-size:1.4rem;font-weight:800;color:#1e293b;letter-spacing:-.02em;">{{ $heroPrice }}</div>
                </div>
                @endif
            </div>
        </div>

    </div>{{-- /row --}}

</div>{{-- /container --}}

{{-- ===== MOBILE STICKY BOTTOM BAR ===== --}}
<div class="tcl-mobile-bar d-lg-none">
    <a href="{{ route('offer.listing.tenant.searchListing') }}" class="tcl-mobile-bar-btn">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Search</span>
    </a>
    <button class="tcl-mobile-bar-btn" data-bs-toggle="modal" data-bs-target="#tclQuestionModal"
        @if(auth()->id() == $ownerId) style="display:none;" @endif>
        <i class="fa-solid fa-circle-question"></i>
        <span>Ask</span>
    </button>
    @if($ifFilled($str('email')) && auth()->id() != $ownerId)
    <a href="mailto:{{ $str('email') }}" class="tcl-mobile-bar-btn tcl-mobile-primary">
        <i class="fa-solid fa-envelope"></i>
        <span>Contact</span>
    </a>
    @endif
    <button class="tcl-mobile-bar-btn" id="tclShareBtnMobile">
        <i class="fa-solid fa-share-nodes"></i>
        <span>Share</span>
    </button>
    @if(auth()->id() == $ownerId)
    <a href="{{ route('offer.listing.tenant.edit', ['auctionId' => $auction->id]) }}" class="tcl-mobile-bar-btn tcl-mobile-primary">
        <i class="fa-solid fa-pen-to-square"></i>
        <span>Edit</span>
    </a>
    @endif
</div>

{{-- ===== QUESTION MODAL ===== --}}
<div class="modal fade" id="tclQuestionModal" tabindex="-1" aria-labelledby="tclQuestionModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;">
                <h5 class="modal-title fw-bold" id="tclQuestionModalLabel"><i class="fa-solid fa-circle-question me-2"></i>Ask a Question</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
            </div>
            <form method="POST" action="{{ route('offer.listing.tenant.question', ['auction' => $auction->id]) }}">
            @csrf
            <input type="text" name="website" value="" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="modal-body p-4">
                @if(session('success') && str_contains((string)session('success'), 'question'))
                    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Your Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name', 'tclQuestionInquiry') is-invalid @enderror" name="name" placeholder="Jane Smith" value="{{ old('name') }}" required>
                        @error('name', 'tclQuestionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control @error('email', 'tclQuestionInquiry') is-invalid @enderror" name="email" placeholder="jane@example.com" value="{{ old('email') }}" required>
                        @error('email', 'tclQuestionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" placeholder="(555) 000-0000" value="{{ old('phone') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" style="font-size:.85rem;">Your Question <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('question', 'tclQuestionInquiry') is-invalid @enderror" name="question" rows="4" placeholder="What would you like to know about this Tenant Criteria listing?" required>{{ old('question') }}</textarea>
                        @error('question', 'tclQuestionInquiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pb-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-paper-plane me-1"></i>Send Question
                </button>
            </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    /* ---- Bidding period countdown timer ---- */
    function tclBpFormat(s) {
        if (s <= 0) return 'Expired';
        if (s < 60) return s + 's Remaining';
        var d = Math.floor(s / 86400); s %= 86400;
        var h = Math.floor(s / 3600);  s %= 3600;
        var m = Math.floor(s / 60);
        var p = [];
        if (d) p.push(d + 'd');
        if (h) p.push(h + 'h');
        if (m) p.push(m + 'm');
        return p.join(' ') + ' Remaining';
    }
    document.querySelectorAll('.tcl-bp-timer[data-seconds]').forEach(function (el) {
        var secs = parseInt(el.getAttribute('data-seconds'), 10) || 0;
        el.textContent = tclBpFormat(secs);
        if (secs <= 0) { el.classList.replace('bg-info', 'bg-secondary'); return; }
        var iv = setInterval(function () {
            secs--;
            el.textContent = tclBpFormat(secs);
            if (secs <= 0) {
                clearInterval(iv);
                el.classList.remove('bg-info', 'text-dark');
                el.classList.add('bg-secondary');
            }
        }, 1000);
    });

    /* ---- Smooth-scroll sticky nav with active-section highlighting ---- */
    var HEADER_OFFSET = 80;
    var navLinks = Array.from(document.querySelectorAll('#tclNavTabs a[href^="#"]'));
    var sections  = navLinks.map(function (a) { return document.querySelector(a.getAttribute('href')); }).filter(Boolean);

    navLinks.forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var target = document.querySelector(a.getAttribute('href'));
            if (!target) return;
            var top = target.getBoundingClientRect().top + window.scrollY - HEADER_OFFSET;
            window.scrollTo({ top: top, behavior: 'smooth' });
        });
    });

    function onScroll() {
        var scrollY = window.scrollY + HEADER_OFFSET + 10;
        var active = null;
        sections.forEach(function (s) {
            if (s && s.offsetTop <= scrollY) active = s;
        });
        navLinks.forEach(function (a) { a.classList.remove('tcl-nav-active'); });
        if (active) {
            var link = document.querySelector('#tclNavTabs a[href="#' + active.id + '"]');
            if (link) link.classList.add('tcl-nav-active');
        }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* ---- Share button ---- */
    function doShare() {
        var url = window.location.href;
        if (navigator.share) {
            navigator.share({ title: document.title, url: url });
        } else {
            navigator.clipboard.writeText(url).then(function () {
                alert('Link copied to clipboard!');
            }).catch(function () {
                prompt('Copy this link:', url);
            });
        }
    }
    ['tclShareBtn','tclShareBtnSidebar','tclShareBtnMobile'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', doShare);
    });

    /* ---- Auto-reopen question modal after validation failure ---- */
    @if(session('open_modal') === 'question')
    (function () {
        var el = document.getElementById('tclQuestionModal');
        if (el && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    }());
    @endif
})();
</script>
@endpush
@endsection
