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
    $str = function($key) use ($meta) { $v = $meta[$key] ?? ''; return is_array($v) ? implode(', ', $v) : $v; };
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
   sol-view-page — namespaced to this page only
   ============================================================ */

/* Section cards */
.sol-view-page .section-card {
    margin-bottom: 1.75rem;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 6px rgba(0,0,0,.06);
    overflow: hidden;
}
.sol-view-page .section-card .card-header {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    font-weight: 700;
    font-size: 1.05rem;
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    letter-spacing: -0.01em;
    color: #1e293b;
}
.sol-view-page .section-card .card-body {
    padding: 1.25rem 1.5rem;
}
.sol-view-page hr {
    border-color: #e9ecef;
    opacity: 0.6;
    margin: 1rem 0;
}
.sol-view-page .field-label {
    color: #64748b;
    font-weight: 600;
    font-size: 0.85rem;
}
.sol-view-page .field-value {
    font-size: 0.925rem;
    overflow-wrap: break-word;
    color: #1e293b;
}
.sol-view-page h6.fw-semibold {
    color: #334155;
    font-size: 0.95rem;
}

/* Photo thumbnails */
.sol-view-page .photo-thumb {
    width: 120px;
    height: 90px;
    object-fit: cover;
    border-radius: 10px;
    border: 2px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,.10);
    transition: transform .2s ease, box-shadow .2s ease;
    display: block;
}
.sol-view-page .photo-thumb:hover {
    transform: scale(1.06);
    box-shadow: 0 6px 20px rgba(0,0,0,.18);
}
.sol-view-page .cover-badge {
    font-size: 0.68rem;
    background: #2563eb;
    color: #fff;
    border-radius: 4px;
    padding: 2px 6px;
    font-weight: 600;
}

/* ---- Hero ---- */
.sol-view-page .sol-hero {
    border-radius: 1rem;
    overflow: hidden;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 24px rgba(0,0,0,.10);
    background: #1e293b;
}
.sol-view-page .sol-hero-photo {
    min-height: 280px;
    max-height: 420px;
    object-fit: cover;
    width: 100%;
    height: 100%;
    display: block;
}
.sol-view-page .sol-hero-photo-placeholder {
    min-height: 280px;
    background: linear-gradient(135deg, #1e3a5f, #0f172a);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 3rem;
}
.sol-view-page .sol-hero-summary {
    background: #fff;
    padding: 1.5rem 1.75rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
}
.sol-view-page .sol-hero-price {
    font-size: 2rem;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: -0.03em;
    line-height: 1.1;
}
.sol-view-page .sol-hero-address {
    color: #475569;
    font-size: 0.92rem;
    margin-top: 0.35rem;
}
.sol-view-page .sol-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1.25rem;
    margin-top: 0.75rem;
    font-size: 0.875rem;
    color: #334155;
}
.sol-view-page .sol-hero-meta-item i {
    color: #2563eb;
    margin-right: 4px;
}
.sol-view-page .sol-hero-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.9rem;
}
.sol-view-page .sol-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.3rem 0.65rem;
    border-radius: 20px;
    border: 1px solid;
    white-space: nowrap;
}
.sol-view-page .sol-badge-blue   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.sol-view-page .sol-badge-green  { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.sol-view-page .sol-badge-purple { background: #faf5ff; color: #7c3aed; border-color: #ddd6fe; }
.sol-view-page .sol-badge-amber  { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.sol-view-page .sol-badge-teal   { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
.sol-view-page .sol-badge-rose   { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
.sol-view-page .sol-hero-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.82rem;
    font-weight: 700;
    padding: 0.3rem 0.75rem;
    border-radius: 20px;
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
    margin-top: 0.75rem;
}
.sol-view-page .sol-hero-dates {
    font-size: 0.78rem;
    color: #94a3b8;
    margin-top: 0.5rem;
}
.sol-view-page .sol-hero-ctas {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
}
.sol-view-page .sol-hero-ctas .btn {
    font-size: 0.82rem;
    font-weight: 600;
    padding: 0.45rem 0.9rem;
    border-radius: 8px;
}

/* ---- Smooth-scroll nav tabs ---- */
.sol-view-page .sol-nav-tabs-wrap {
    background: #fff;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 1.75rem;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.sol-view-page .sol-nav-tabs {
    display: flex;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
    gap: 0;
    list-style: none;
    padding: 0;
    margin: 0;
}
.sol-view-page .sol-nav-tabs::-webkit-scrollbar { display: none; }
.sol-view-page .sol-nav-tabs li a {
    display: block;
    padding: 0.75rem 1.1rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    white-space: nowrap;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: color .15s, border-color .15s;
    letter-spacing: 0.01em;
}
.sol-view-page .sol-nav-tabs li a:hover {
    color: #2563eb;
    border-bottom-color: #2563eb;
}

/* ---- Sticky desktop action card ---- */
.sol-view-page .sol-sticky-card {
    position: sticky;
    top: 72px;
    background: #fff;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
    padding: 1.25rem 1rem;
}
.sol-view-page .sol-sticky-card .sol-sticky-title {
    font-size: 0.78rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #f1f5f9;
}
.sol-view-page .sol-sticky-card .sol-action-btn {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.6rem 0.75rem;
    font-size: 0.83rem;
    font-weight: 600;
    border-radius: 8px;
    margin-bottom: 0.4rem;
    text-align: left;
    border: 1px solid transparent;
    cursor: pointer;
    transition: background .15s, border-color .15s;
    text-decoration: none;
}
.sol-view-page .sol-sticky-card .sol-action-btn i {
    width: 18px;
    text-align: center;
    flex-shrink: 0;
}
.sol-view-page .sol-action-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
.sol-view-page .sol-action-primary:hover { background: #1d4ed8; color: #fff; }
.sol-view-page .sol-action-outline { background: #fff; color: #334155; border-color: #e2e8f0; }
.sol-view-page .sol-action-outline:hover { background: #f8fafc; border-color: #cbd5e1; }

/* ---- Mobile sticky bottom bar ---- */
.sol-mobile-bar {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1030;
    background: #fff;
    border-top: 1px solid #e2e8f0;
    box-shadow: 0 -4px 16px rgba(0,0,0,.10);
    padding: 0.5rem 1rem;
    padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
    gap: 0.5rem;
}
.sol-mobile-bar-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 3px;
    flex: 1;
    font-size: 0.65rem;
    font-weight: 700;
    color: #334155;
    text-decoration: none;
    padding: 0.4rem 0.25rem;
    border-radius: 8px;
    border: none;
    background: transparent;
    cursor: pointer;
    transition: background .15s;
    min-height: 52px;
    justify-content: center;
}
.sol-mobile-bar-btn i {
    font-size: 1.15rem;
    color: #2563eb;
}
.sol-mobile-bar-btn:hover, .sol-mobile-bar-btn:active { background: #f1f5f9; color: #1e293b; }
@media (max-width: 991.98px) {
    .sol-mobile-bar { display: flex; }
    .sol-main-content-wrap { padding-bottom: calc(80px + env(safe-area-inset-bottom)); }
}

/* Documents & Disclosures polish */
.sol-view-page .sol-doc-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.sol-view-page .sol-doc-download {
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    border: 1px solid #2563eb;
    color: #2563eb;
    background: #eff6ff;
    text-decoration: none;
    transition: background .15s, color .15s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.sol-view-page .sol-doc-download:hover { background: #2563eb; color: #fff; }

/* Contact CTA row */
.sol-view-page .sol-contact-cta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #f1f5f9;
}

/* Modal polish — modals render inside .sol-view-page container */
.sol-view-page .sol-modal-header {
    background: linear-gradient(135deg, #1e293b, #334155);
    color: #fff;
    border-radius: 0.75rem 0.75rem 0 0;
    padding: 1.25rem 1.5rem;
    border-bottom: none;
}
.sol-view-page .sol-modal-header .btn-close { filter: invert(1); }
</style>
@endpush

@section('content')
<div class="container py-4 sol-view-page">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h2 class="mb-1 fw-bold" style="color:#1e293b;">{{ $auction->title ?? ($meta['address'] ?? 'Seller Offer Listing') }}</h2>
            @php
                $addrParts = array_filter([
                    $meta['address'] ?? null,
                    !empty($meta['unit']) ? 'Unit ' . $meta['unit'] : null,
                    $meta['property_city'] ?? null,
                ]);
                $addrState = trim($meta['property_state'] ?? '');
                $addrZip   = trim(($meta['property_zip'] ?? '') ?: ($meta['zip_code'] ?? ''));
                $stateZip  = trim($addrState . ($addrState && $addrZip ? ' ' : '') . $addrZip);
                if ($stateZip) $addrParts[] = $stateZip;
                $fullAddress = implode(', ', array_filter($addrParts));
            @endphp
            @if($fullAddress)
                <p class="text-muted mb-0"><i class="fa-solid fa-location-dot me-1"></i>{{ $fullAddress }}</p>
            @endif
        </div>
        @if(auth()->id() == $auction->user_id)
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('offer.listing.seller.edit', ['auctionId' => $auction->id]) }}"
               class="btn btn-outline-primary">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
            </a>
        </div>
        @endif
    </div>

    {{-- =====================================================================
         INTENTIONAL FIELD EXCLUSIONS (not rendered on this view page):
         - listing_ai_faq        : AI-generated FAQ, internal content only.
         - photo                 : Livewire temp upload, not a display meta value.
         - video_link            : Agent intro video (seller-info tab), not property media.
         - current_status        : Internal workflow field, not a user-facing detail.
         - state                 : Livewire autocomplete holder; saved as property_state.
         - newCity               : Livewire holder; saved as property_city.
         - newPropertyPhotos     : Livewire file-upload holder; rendered via property_photos.
         - openHouseCount        : Event counter, not persisted listing data.
         - photo_enhancements    : Photo-editing preference flag, no display value.
         - other_preferences     : Internal catch-all, no standard display key.
         - other_services_enabled / other_services.N : Rendered via the services array.
         - prepayment_penalty    : Yes/No toggle; amount rendered as prepayment_penalty_amount.
         - baths_unit / beds_unit / expected_rent / number_occupied : Sub-fields of
           unit_type_configurations JSON; summary rendered via unit_number / unit_buildings.
         - pool_type.community / pool_type.private : Rendered via $arr('pool_type') below.
         - videoTourUrl / virtualTourUrl : Aliases; rendered as video_tour_url / virtual_tour_url.
         - other_building_features, other_current_use, other_current_adjacent_use,
           other_easements, other_electrical_service, other_fences, other_licenses,
           other_non_negotiable_amenities, other_parking_space_wrapper, other_road_frontage,
           other_road_surface_type, other_sale_includes, other_vegetation,
           other_carport_needed, other_garage_needed : "Other" companion inputs for
           land/commercial-specific multi-selects not rendered in this layout.
         ===================================================================== --}}

    @php
        /* Resolve photos for hero + gallery */
        $propertyPhotos = $meta['property_photos'] ?? [];
        if (is_string($propertyPhotos)) {
            $decoded = json_decode($propertyPhotos, true);
            $propertyPhotos = is_array($decoded) ? $decoded : [];
        }
        $coverPhoto = null;
        foreach ($propertyPhotos as $ph) {
            $fn = is_array($ph) ? ($ph['filename'] ?? '') : $ph;
            if (!$fn) continue;
            if (is_array($ph) && !empty($ph['is_cover'])) { $coverPhoto = $fn; break; }
            if (!$coverPhoto) $coverPhoto = $fn;
        }

        /* Hero: price — seller-appropriate field priority; no buyer fields */
        $heroPrice = null;
        foreach (['desired_sale_price','purchase_price','buy_now_price','starting_price','reserve_price'] as $pk) {
            $pv = $meta[$pk] ?? '';
            if ($pv !== '' && $pv !== null) { $heroPrice = $fmtMoney($pv); break; }
        }

        /* Hero: beds / baths / sqft */
        $heroBeds  = $str('bedrooms')  ?: null;
        $heroBaths = $str('bathrooms') ?: null;
        $heroHSqft = $str('minimum_heated_square') ?: null;
        $heroTSqft = $str('total_square_feet') ?: null;
        $heroPropType = $str('property_type') ?: null;
        $heroStatus   = $str('listing_status') ?: null;
        $heroListDate = $fmtDate($str('listing_date'));
        $heroUpdDate  = $auction->updated_at ? \Carbon\Carbon::parse($auction->updated_at)->format('F j, Y') : null;

        /* Hero badges */
        $heroOfFin   = $arr('offered_financing');
        $badgePool     = in_array(strtolower((string)($str('pool_needed'))), ['yes','1','true']);
        $badgeWaterfront = false;
        $vp = $arr('view_preference');
        foreach ($vp as $v) {
            if (stripos($v, 'water') !== false || stripos($v, 'lake') !== false || stripos($v, 'ocean') !== false || stripos($v, 'bay') !== false || stripos($v, 'gulf') !== false || stripos($v, 'river') !== false || stripos($v, 'canal') !== false) {
                $badgeWaterfront = true; break;
            }
        }
        $badgeFinancing  = count($heroOfFin) > 0;
        $badgeLeaseOpt   = in_array('Lease Option', $heroOfFin) || in_array('Lease Purchase', $heroOfFin);
        $badgeCrypto     = in_array('Cryptocurrency', $heroOfFin);
        $badgeHOA        = in_array(strtolower((string)($str('has_hoa'))), ['yes','1','true']);
        /* Bidding Period badge: only when auction_type explicitly indicates a bidding format */
        $badgeBidding = (
            stripos($str('auction_type'), 'bidding') !== false
            || stripos($str('auction_type'), 'auction') !== false
        );
    @endphp

    {{-- ===== HERO SECTION ===== --}}
    <div class="sol-hero mb-4">
        <div class="row g-0" style="min-height:280px;">
            <div class="col-lg-7" style="max-height:420px;overflow:hidden;">
                @if($coverPhoto)
                    <img src="{{ asset('storage/auction/images/' . $coverPhoto) }}"
                         alt="Property cover photo"
                         class="sol-hero-photo"
                         onerror="this.parentElement.innerHTML='<div class=\'sol-hero-photo-placeholder\'><i class=\'fa-solid fa-house\'></i></div>'">
                @else
                    <div class="sol-hero-photo-placeholder">
                        <i class="fa-solid fa-house"></i>
                    </div>
                @endif
            </div>
            <div class="col-lg-5">
                <div class="sol-hero-summary">
                    @if($heroPrice)
                        <div class="sol-hero-price">{{ $heroPrice }}</div>
                    @endif
                    @if($fullAddress)
                        <div class="sol-hero-address"><i class="fa-solid fa-location-dot me-1" style="color:#2563eb;"></i>{{ $fullAddress }}</div>
                    @endif

                    <div class="sol-hero-meta">
                        @if($heroBeds)
                            <span class="sol-hero-meta-item"><i class="fa-solid fa-bed"></i>{{ $heroBeds }} Bed{{ $heroBeds != '1' ? 's' : '' }}</span>
                        @endif
                        @if($heroBaths)
                            <span class="sol-hero-meta-item"><i class="fa-solid fa-bath"></i>{{ $heroBaths }} Bath{{ $heroBaths != '1' ? 's' : '' }}</span>
                        @endif
                        @if($heroHSqft)
                            <span class="sol-hero-meta-item"><i class="fa-solid fa-ruler-combined"></i>{{ number_format((int)preg_replace('/[^0-9]/','',$heroHSqft)) }} Heated Sq Ft</span>
                        @endif
                        @if($heroTSqft && $heroTSqft !== $heroHSqft)
                            <span class="sol-hero-meta-item"><i class="fa-solid fa-expand"></i>{{ number_format((int)preg_replace('/[^0-9]/','',$heroTSqft)) }} Total Sq Ft</span>
                        @endif
                        @if($heroPropType)
                            <span class="sol-hero-meta-item"><i class="fa-solid fa-tag"></i>{{ $heroPropType }}</span>
                        @endif
                    </div>

                    @if($heroStatus)
                        <div>
                            <span class="sol-hero-status">
                                <i class="fa-solid fa-circle-check"></i>{{ $heroStatus }}
                            </span>
                        </div>
                    @endif

                    @if($heroListDate || $heroUpdDate)
                        <div class="sol-hero-dates">
                            @if($heroListDate)<span>Listed: {{ $heroListDate }}</span>@endif
                            @if($heroListDate && $heroUpdDate)<span class="mx-1">·</span>@endif
                            @if($heroUpdDate)<span>Updated: {{ $heroUpdDate }}</span>@endif
                        </div>
                    @endif

                    <div class="sol-hero-badges">
                        @if($badgePool)
                            <span class="sol-badge sol-badge-blue"><i class="fa-solid fa-water-ladder"></i> Pool</span>
                        @endif
                        @if($badgeWaterfront)
                            <span class="sol-badge sol-badge-teal"><i class="fa-solid fa-water"></i> Waterfront / View</span>
                        @endif
                        @if($badgeFinancing)
                            <span class="sol-badge sol-badge-green"><i class="fa-solid fa-hand-holding-dollar"></i> Financing Available</span>
                        @endif
                        @if($badgeLeaseOpt)
                            <span class="sol-badge sol-badge-purple"><i class="fa-solid fa-key"></i> Lease Option</span>
                        @endif
                        @if($badgeCrypto)
                            <span class="sol-badge sol-badge-amber"><i class="fa-brands fa-bitcoin"></i> Crypto Accepted</span>
                        @endif
                        @if($badgeHOA)
                            <span class="sol-badge sol-badge-rose"><i class="fa-solid fa-building-columns"></i> HOA</span>
                        @endif
                        @if($badgeBidding)
                            <span class="sol-badge sol-badge-blue"><i class="fa-solid fa-gavel"></i> Bidding Period Active</span>
                        @endif
                    </div>

                    <div class="sol-hero-ctas">
                        <button class="btn btn-primary sol-hero-offer-btn" data-bs-toggle="modal" data-bs-target="#solOfferModal">
                            <i class="fa-solid fa-file-signature me-1"></i>Submit Offer
                        </button>
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#solShowingModal">
                            <i class="fa-solid fa-calendar-days me-1"></i>Schedule Showing
                        </button>
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#solQuestionModal">
                            <i class="fa-solid fa-circle-question me-1"></i>Ask a Question
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== TWO-COLUMN LAYOUT: MAIN + STICKY RAIL ===== --}}
    <div class="row g-4 align-items-start">

        {{-- Main content column --}}
        <div class="col-lg-9 sol-main-content-wrap">

    {{-- ===== SMOOTH-SCROLL NAV TABS ===== --}}
    <div class="sol-nav-tabs-wrap">
        <ul class="sol-nav-tabs">
            <li><a href="#section-overview">Overview</a></li>
            <li><a href="#section-photos">Photos</a></li>
            <li><a href="#section-details">Details</a></li>
            <li><a href="#section-financing">Financing</a></li>
            <li><a href="#section-terms">Terms</a></li>
            <li><a href="#section-documents">Documents</a></li>
            <li><a href="#section-contact">Contact</a></li>
        </ul>
    </div>

    {{-- Photos & Tours --}}
    @if(count($propertyPhotos) || $str('video_tour_url') || $str('virtual_tour_url'))
    <div class="card section-card" id="section-photos">
        <div class="card-header"><i class="fa-solid fa-images me-2"></i>Photos &amp; Tours</div>
        <div class="card-body">
            @php
                $videoUrl = $str('video_tour_url');
                $videoEmbedUrl = null;
                if ($videoUrl) {
                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_\-]{11})/', $videoUrl, $vm)) {
                        $videoEmbedUrl = 'https://www.youtube.com/embed/' . $vm[1];
                    } elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $vm)) {
                        $videoEmbedUrl = 'https://player.vimeo.com/video/' . $vm[1];
                    }
                }
                $virtualUrl = $str('virtual_tour_url');
                $virtualEmbedUrl = null;
                if ($virtualUrl) {
                    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_\-]{11})/', $virtualUrl, $vm)) {
                        $virtualEmbedUrl = 'https://www.youtube.com/embed/' . $vm[1];
                    } elseif (preg_match('/vimeo\.com\/(\d+)/', $virtualUrl, $vm)) {
                        $virtualEmbedUrl = 'https://player.vimeo.com/video/' . $vm[1];
                    }
                }
            @endphp
            @if($videoUrl)
                @if($videoEmbedUrl)
                    <div class="ratio ratio-16x9 mb-3" style="max-width:560px;">
                        <iframe src="{{ $videoEmbedUrl }}" title="Video Tour"
                                allowfullscreen
                                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                        </iframe>
                    </div>
                @else
                    <p class="mb-3"><span class="field-label">Video Tour:</span>
                        <a href="{{ $videoUrl }}" target="_blank" rel="noopener" class="ms-1">{{ $videoUrl }}</a>
                    </p>
                @endif
            @endif
            @if($virtualUrl)
                @if($virtualEmbedUrl)
                    <div class="ratio ratio-16x9 mb-3" style="max-width:560px;">
                        <iframe src="{{ $virtualEmbedUrl }}" title="Virtual Tour"
                                allowfullscreen
                                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                        </iframe>
                    </div>
                @else
                    <p class="mb-3"><span class="field-label">3D / Virtual Tour:</span>
                        <a href="{{ $virtualUrl }}" target="_blank" rel="noopener" class="ms-1">{{ $virtualUrl }}</a>
                    </p>
                @endif
            @endif

            @if(count($propertyPhotos))
            @php $galleryIdx = -1; @endphp
            <div class="d-flex flex-wrap gap-2 mt-3">
                @foreach($propertyPhotos as $photo)
                @php
                    $filename = is_array($photo) ? ($photo['filename'] ?? '') : $photo;
                    $isCover  = is_array($photo) && !empty($photo['is_cover']);
                    if ($filename) $galleryIdx++;
                @endphp
                @if($filename)
                <div class="text-center">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#photoModal"
                       data-src="{{ asset('storage/auction/images/' . $filename) }}"
                       data-index="{{ $galleryIdx }}"
                       style="display:block;">
                        <img src="{{ asset('storage/auction/images/' . $filename) }}"
                             alt="Property photo {{ $galleryIdx + 1 }}"
                             class="photo-thumb"
                             onerror="this.style.display='none'">
                    </a>
                    @if($isCover)
                        <div><span class="cover-badge">Cover</span></div>
                    @endif
                </div>
                @endif
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Photo lightbox modal --}}
    @if(count($propertyPhotos))
    <div class="modal fade" id="photoModal" tabindex="-1" aria-label="Property photo viewer" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark border-0">
                <div class="modal-header border-0 pb-0">
                    <span class="text-white small" id="photoModalCounter"></span>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-2">
                    <img id="photoModalImg" src="" alt="Property photo"
                         style="max-width:100%;max-height:70vh;object-fit:contain;border-radius:6px;">
                </div>
                <div class="modal-footer border-0 justify-content-center gap-3 pt-0">
                    <button type="button" class="btn btn-outline-light btn-sm px-4" id="photoModalPrev">&#8249; Prev</button>
                    <button type="button" class="btn btn-outline-light btn-sm px-4" id="photoModalNext">Next &#8250;</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var gallery = Array.from(document.querySelectorAll('[data-bs-target="#photoModal"]'))
                           .map(function (el) { return el.getAttribute('data-src'); });
        var currentIndex = 0;

        function showPhoto(idx) {
            if (gallery.length === 0) return;
            if (idx < 0) idx = gallery.length - 1;
            if (idx >= gallery.length) idx = 0;
            currentIndex = idx;
            document.getElementById('photoModalImg').src = gallery[idx];
            document.getElementById('photoModalCounter').textContent = (idx + 1) + ' / ' + gallery.length;
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-bs-target="#photoModal"]');
            if (trigger) {
                e.preventDefault();
                showPhoto(parseInt(trigger.getAttribute('data-index') || '0', 10));
            }
        });

        var photoModalEl = document.getElementById('photoModal');
        if (photoModalEl) {
            photoModalEl.addEventListener('show.bs.modal', function () {
                showPhoto(currentIndex);
            });
        }

        var prevBtn = document.getElementById('photoModalPrev');
        var nextBtn = document.getElementById('photoModalNext');
        if (prevBtn) prevBtn.addEventListener('click', function () { showPhoto(currentIndex - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () { showPhoto(currentIndex + 1); });
    })();
    </script>
    @endif
    @endif

    {{-- Property Description --}}
    @if($val('additional_details'))
    <div class="card section-card" id="section-overview">
        <div class="card-header"><i class="fa-solid fa-align-left me-2"></i>Property Description</div>
        <div class="card-body">
            <p class="field-value mb-0">{!! nl2br(e($val('additional_details'))) !!}</p>
        </div>
    </div>
    @endif

    {{-- Listing Details --}}
    <div class="card section-card" @if(!$val('additional_details')) id="section-overview" @endif>
        <div class="card-header"><i class="fa-solid fa-list-check me-2"></i>Listing Details</div>
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
                    {!! $row('Auction Time', $str('auction_time')) !!}
                </div>
            </div>
        </div>
    </div>

    {{-- Property Details --}}
    <div class="card section-card" id="section-details">
        <div class="card-header"><i class="fa-solid fa-house me-2"></i>Property Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Property Type', $str('property_type')) !!}
                    {!! $row('Address', $str('address')) !!}
                    {!! $row('City', $str('property_city')) !!}
                    {!! $row('County', $str('property_county')) !!}
                    {!! $row('State', $str('property_state')) !!}
                    {!! $row('ZIP Code', $str('property_zip') ?: $str('zip_code')) !!}
                    {!! $row('Bedrooms', $orOther($str('bedrooms'), $str('other_bedrooms'))) !!}
                    {!! $row('Bathrooms', $orOther($str('bathrooms'), $str('other_bathrooms'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Heated Sq Ft', $str('minimum_heated_square') ?: null) !!}
                    {!! $row('Sq Ft Heated Source', $str('sqft_heated_source')) !!}
                    {!! $row('Total Sq Ft', $str('total_square_feet')) !!}
                    {!! $row('Acreage', $str('min_acreage') ?: $str('total_acreage')) !!}
                    {!! $row('Year Built', $str('year_built')) !!}
                    {!! $row('Zoning', $str('zoning')) !!}
                    @php
                        $_cond = $str('condition_prop');
                        $_cond = $orOther($_cond, $str('other_property_condition'));
                        $_cond = ($_cond === 'Older but Clean') ? 'Older but Clean & Well Maintained' : $_cond;
                    @endphp
                    {!! $row('Property Condition', $_cond) !!}
                    {!! $row('Pool', $str('pool_needed')) !!}
                    @php
                        $poolTypeRaw  = $arr('pool_type');
                        $poolTypeList = [];
                        if (!empty($poolTypeRaw['community'])) $poolTypeList[] = 'Community';
                        if (!empty($poolTypeRaw['private']))   $poolTypeList[] = 'Private';
                    @endphp
                    {!! $row('Pool Type', count($poolTypeList) ? implode(', ', $poolTypeList) : null) !!}
                </div>
            </div>

            @php $appliances = $subOther($arr('appliances'), $str('other_appliances')); @endphp
            @if(count($appliances))
            <hr>
            <div class="row">
                <div class="col-md-6">{!! $row('Appliances', implode(', ', $appliances)) !!}</div>
            </div>
            @endif

            @php $pItems = $subOther($arr('property_items'), $str('other_property_items')); @endphp
            @if(count($pItems))
            <hr>
            <div class="mb-1"><span class="field-label">Property Items / Amenities</span></div>
            <p class="field-value">{{ implode(', ', $pItems) }}</p>
            @endif

            @php $viewPref = $subOther($arr('view_preference'), $str('other_preferences')); @endphp
            @if(count($viewPref))
            <div class="row">
                <div class="col-md-6">{!! $row('View', implode(', ', $viewPref)) !!}</div>
            </div>
            @endif

            @php $nonNegAmenities = $subOther($arr('non_negotiable_amenities'), $str('other_non_negotiable_amenities')); @endphp
            @if(count($nonNegAmenities))
            <div class="row">
                <div class="col-md-6">{!! $row('Non-Negotiable Amenities', implode(', ', $nonNegAmenities)) !!}</div>
            </div>
            @endif

            {{-- MLS Fields --}}
            @php
                $mlsFields = [
                    ['Roof Type',             implode(', ', $subOther($arr('roof_type'),             $str('other_roof_type')))],
                    ['Exterior Construction', implode(', ', $subOther($arr('exterior_construction'),  $str('other_exterior_construction')))],
                    ['Foundation',            implode(', ', $subOther($arr('foundation'),             $str('other_foundation')))],
                    ['Heating & Fuel',        implode(', ', $subOther($arr('heating_and_fuel'),       $str('other_heating_and_fuel')))],
                    ['Air Conditioning',      implode(', ', $subOther($arr('air_conditioning'),       $str('other_air_conditioning')))],
                    ['Water',                 implode(', ', $subOther($arr('water'),                  $str('other_water')))],
                    ['Sewer',                 implode(', ', $subOther($arr('sewer'),                  $str('other_sewer')))],
                    ['Utilities',             implode(', ', $subOther($arr('utilities'),              $str('other_utilities')))],
                ];
                $mlsFields = array_filter($mlsFields, fn($f) => !empty($f[1]));
            @endphp
            @if(count($mlsFields))
            <hr>
            <div class="row">
                @foreach($mlsFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Extended Property Attributes --}}
            @php
                $extPropFields = array_filter([
                    ['Buildable',            $str('buildable')],
                    ['Ceiling Height',       $str('ceiling_height')],
                    ['Lot Dimensions',       $str('lot_dimensions')],
                    ['Front Footage',        $str('front_footage') ? $str('front_footage') . ' ft' : null],
                    ['Total Parcel Count',   $str('total_parcel_count')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($extPropFields))
            <hr>
            <div class="row">
                @foreach($extPropFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Garage & Parking --}}
            @php
                $garageFields = array_filter([
                    ['Garage',                  $str('garage_needed')],
                    ['Garage Spaces',           $str('garage_spaces') ?: $str('other_garage_needed')],
                    ['Carport',                 $str('carport_needed')],
                    ['Carport Spaces',          $str('carport_spaces') ?: $str('other_carport_needed')],
                    ['Garage/Parking Features', $str('garage_parking_spaces') ?: $str('garage_parking_spaces_option')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($garageFields))
            <hr>
            <div class="row">
                @foreach($garageFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Income / Multi-Unit Configuration --}}
            @php
                $unitFields = array_filter([
                    ['Total Number of Units',     $str('unit_number')],
                    ['Total Number of Buildings', $str('unit_buildings')],
                    ['Unit Type',                 $orOther($str('number_of_unit'), $str('number_of_unit_other'))],
                    ['Number of Unit Types',      $str('number_of_units')],
                    ['Unit Type Description',     $str('unit_type_description')],
                    ['Value Determination',       $str('value_determination')],
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

            {{-- Business Info --}}
            @php
                $bizFields = array_filter([
                    ['Business Name',                    $str('business_name')],
                    ['Business Type',                    $orOther($str('business_type'), $str('other_business_type'))],
                    ['Year Established',                 $str('year_established')],
                    ['Custom Enhancements / Value-Adds', $str('custom_enhancement')],
                    ['Included Assets (Other)',          $str('assets_other')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($bizFields))
            <hr>
            <div class="row">
                @foreach($bizFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Site Utilities (land/commercial) --}}
            @php
                $siteUtilFields = array_filter([
                    ['Water Available to Site',      $orOther($str('water_available'),    $str('water_available_other'))],
                    ['Sewer Available to Site',      $orOther($str('sewer_available'),    $str('sewer_available_other'))],
                    ['Electric Available to Site',   $orOther($str('electric_available'), $str('electric_available_other'))],
                    ['Gas Available to Site',        $orOther($str('gas_available'),      $str('gas_available_other'))],
                    ['Telecom / Internet Available', $orOther($str('telecom_available'),  $str('telecom_available_other'))],
                    ['Number of Wells',              $str('number_of_wells')],
                    ['Number of Septics',            $str('number_of_septics')],
                    ['Number of Electric Meters',    $str('number_electric_meters')],
                    ['Number of Water Meters',       $str('number_water_meters')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($siteUtilFields))
            <hr>
            <div class="row">
                @foreach($siteUtilFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

            {{-- Pet & Leasing Policy --}}
            @php
                $petLeaseFields = array_filter([
                    ['Age-Restricted Community (55+)', $str('leasing_55_plus')],
                    ['Pets Allowed',                  $str('pets')],
                    ['Number of Pets Allowed',         $str('number_of_pets')],
                    ['Acceptable Pet Types',           $str('type_of_pets')],
                    ['Breed of Pets',                  $str('breed_of_pets')],
                    ['Max Pet Weight (lbs)',            $str('weight_of_pets')],
                    ['Breed Restrictions',             $str('breed_restrictions')],
                    ['Additional Lease Restrictions',  $str('additional_lease_restrictions')],
                ], fn($f) => !empty($f[1]));
            @endphp
            @if(count($petLeaseFields))
            <hr>
            <div class="row">
                @foreach($petLeaseFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
            @endif

        </div>
    </div>

    {{-- Sale Terms --}}
    <div class="card section-card" id="section-financing">
        <div class="card-header"><i class="fa-solid fa-file-contract me-2"></i>Sale Terms</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Sale Provision', $orOther($str('sale_provision'), $str('sale_provision_other'))) !!}
                    @if($str('sale_provision_assignment'))
                        {!! $row('Seller Under Contract for Assignment', $str('sale_provision_assignment')) !!}
                        {!! $row('Assignment Fee Type', $str('assignment_fee_type') === '$' ? 'Flat Fee' : ($str('assignment_fee_type') === '%' ? 'Percentage' : $str('assignment_fee_type'))) !!}
                        {!! $row('Assignment Fee Amount', $str('assignment_fee_type') === '$' ? $fmtMoney($str('assignment_fee_amount')) : ($str('assignment_fee_type') === '%' ? $fmtPercent($str('assignment_fee_amount')) : $str('assignment_fee_amount'))) !!}
                    @endif
                    {!! $row('Target Closing Timeframe', $str('target_closing_date')) !!}
                    {!! $row('Occupant Type', $str('occupant_status')) !!}
                    {!! $row('Occupied Until', $str('occupant_tenant')) !!}
                    {!! $row('Desired Sale Price', $fmtMoney($str('maximum_budget'))) !!}
                    {!! $row('Purchase Price', $fmtMoney($str('purchase_price'))) !!}
                    {!! $row('Starting Price', $fmtMoney($str('starting_price'))) !!}
                    {!! $row('Reserve Price', $fmtMoney($str('reserve_price'))) !!}
                    {!! $row('Buy Now Price', $fmtMoney($str('buy_now_price'))) !!}
                </div>
                <div class="col-md-6">
                    @php $ofFinancing = $subOther($arr('offered_financing'), $str('other_financing')); @endphp
                    @if(count($ofFinancing)) {!! $row('Offered Financing', implode(', ', $ofFinancing)) !!} @endif
                    @php
                        $_dpType = $str('down_payment_type');
                        $_dpAmt = $_dpType === '%' ? $fmtPercent($str('down_payment_amount')) : $fmtMoney($str('down_payment_amount'));
                    @endphp
                    {!! $row('Down Payment Amount', $_dpAmt) !!}
                    {!! $row('Buyer Sell Contract', $str('buyer_sell_contract')) !!}
                    {!! $row('Initial Deposit Requested', $fmtMoney($str('initial_deposit_requested'))) !!}
                    {!! $row('Initial Deposit Timeframe', $orOther($str('initial_deposit_timeframe'), $str('initial_deposit_timeframe_other'))) !!}
                    {!! $row('Additional Deposit Requested', $fmtMoney($str('additional_deposit_requested'))) !!}
                    {!! $row('Additional Deposit Timeframe', $orOther($str('additional_deposit_timeframe'), $str('additional_deposit_timeframe_other'))) !!}
                    {!! $row('Escrow Agent Preference', $str('escrow_agent_preference')) !!}
                </div>
            </div>
        </div>
    </div>

    {{-- Financing Details (sub-fields per offered_financing type) --}}
    @php
        $ofFin        = $arr('offered_financing');
        $hasCashFin   = in_array('Cash', $ofFin);
        $hasAssumable = in_array('Assumable', $ofFin);
        $hasCrypto    = in_array('Cryptocurrency', $ofFin);
        $hasExchange  = in_array('Exchange/Trade', $ofFin);
        $hasLeaseOpt  = in_array('Lease Option', $ofFin);
        $hasLeasePur  = in_array('Lease Purchase', $ofFin);
        $hasNFT       = in_array('Non-Fungible Token (NFT)', $ofFin);
        $hasSellerFin = in_array('Seller Financing', $ofFin);
        $showFinDetails = $hasCashFin || $hasAssumable || $hasCrypto || $hasExchange
            || $hasLeaseOpt || $hasLeasePur || $hasNFT || $hasSellerFin
            || $str('seller_financing_type') || $str('interest_rate')
            || $str('assumable_loan_type')   || $str('assumable_terms')
            || $str('lease_option_price')    || $str('lease_purchase_price')
            || $str('cryptocurrency_type')   || $str('nft_description')
            || $str('exchange_item_value')   || $str('cash_budget')
            || $str('pre_approved');
    @endphp
    @if($showFinDetails)
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Financing Details</div>
        <div class="card-body">

            {{-- Cash --}}
            @if($hasCashFin || $str('cash_budget') || $str('pre_approved'))
            @if($str('cash_budget') || $str('pre_approved') || $str('pre_approval_amount'))
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Cash</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Maximum Cash Budget', $fmtMoney($str('cash_budget'))) !!}
                    {!! $row('Buyer Pre-Approved for a Loan', $str('pre_approved')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Buyer Pre-Approval Amount', $fmtMoney($str('pre_approval_amount'))) !!}
                </div>
            </div>
            @endif

            {{-- Assumable --}}
            @if($hasAssumable || $str('assumable_loan_type') || $str('assumable_terms'))
            @if($str('assumable_terms') || $str('assumable_loan_type') || $str('max_assumable_rate') || $str('max_monthly_payment') || $str('assumable_monthly_escrow') || $str('outstanding_balance') || $str('gap_payment_amount') || $str('assumable_loan_term_remaining') || $str('assumable_loan_origination_date') || $str('assumable_loan_servicer') || $str('assumable_fee_amount') || $str('assumable_occupancy_requirement'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Assumable Mortgage</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Assumable Terms', $str('assumable_terms')) !!}
                    {!! $row('Loan Type', $str('assumable_loan_type')) !!}
                    {!! $row('Interest Rate of Assumable Loan', $str('max_assumable_rate') ? $fmtPercent($str('max_assumable_rate')) : null) !!}
                    {!! $row('Monthly Payment (P&I)', $fmtMoney($str('max_monthly_payment'))) !!}
                    {!! $row('Monthly Escrow (Informational)', $fmtMoney($str('assumable_monthly_escrow'))) !!}
                    {!! $row('Outstanding Loan Balance', $fmtMoney($str('outstanding_balance'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Gap Payment Amount', $str('gap_payment_type') === '$' ? $fmtMoney($str('gap_payment_amount')) : ($str('gap_payment_type') === '%' ? $fmtPercent($str('gap_payment_amount')) : $str('gap_payment_amount'))) !!}
                    {!! $row('Loan Term Remaining', $str('assumable_loan_term_remaining')) !!}
                    {!! $row('Date Loan Originated', $str('assumable_loan_origination_date')) !!}
                    {!! $row('Loan Servicer / Lender', $str('assumable_loan_servicer')) !!}
                    {!! $row('Assumption Fee', $str('assumable_fee_type') === '%' ? $fmtPercent($str('assumable_fee_amount')) : $fmtMoney($str('assumable_fee_amount'))) !!}
                    {!! $row('Occupancy Requirement', $orOther($str('assumable_occupancy_requirement'), $str('assumable_occupancy_other'))) !!}
                </div>
            </div>
            @endif

            {{-- Cryptocurrency --}}
            @if($hasCrypto || $str('cryptocurrency_type'))
            @if($str('cryptocurrency_type') || $str('crypto_percentage') || $str('cash_percentage_crypto') || $str('crypto_exchange_method') || $str('crypto_custodian_wallet') || $str('crypto_transaction_fees') || $str('crypto_transfer_timing'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Cryptocurrency</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Cryptocurrency Type', $str('cryptocurrency_type')) !!}
                    {!! $row('Crypto % of Purchase Price', $str('crypto_percentage') ? $fmtPercent($str('crypto_percentage')) : null) !!}
                    {!! $row('Cash % of Purchase Price', $str('cash_percentage_crypto') ? $fmtPercent($str('cash_percentage_crypto')) : null) !!}
                    {!! $row('Exchange Method', $str('crypto_exchange_method')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Custodian / Wallet', $str('crypto_custodian_wallet')) !!}
                    {!! $row('Transaction Fees Responsibility', $str('crypto_transaction_fees')) !!}
                    {!! $row('Timing of Transfer', $orOther($str('crypto_transfer_timing'), $str('crypto_transfer_timing_other'))) !!}
                </div>
            </div>
            @endif

            {{-- Exchange / Trade --}}
            @if($hasExchange || $str('exchange_item_value'))
            @if($str('other_exchange_item') || $str('exchange_item_value') || $str('exchange_item_condition') || $str('additional_cash') || $str('exchange_transfer_method') || $str('exchange_liens') || $str('exchange_inspection_rights'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Exchange / Trade</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Exchange Item', $str('other_exchange_item')) !!}
                    {!! $row('Estimated Value of Exchange Item', $fmtMoney($str('exchange_item_value'))) !!}
                    {!! $row('Condition of Exchange Item', $str('exchange_item_condition')) !!}
                    {!! $row('Additional Cash Required', $fmtMoney($str('additional_cash'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Transfer Method', $str('exchange_transfer_method')) !!}
                    {!! $row('Liens / Encumbrances', $str('exchange_liens') . ($str('exchange_liens_details') ? ' – ' . $str('exchange_liens_details') : '')) !!}
                    {!! $row('Inspection / Verification Rights', $str('exchange_inspection_rights')) !!}
                </div>
            </div>
            @endif

            {{-- Lease Option --}}
            @if($hasLeaseOpt || $str('lease_option_price'))
            @if($str('lease_option_price') || $str('lease_option_payment') || $str('lease_option_duration') || $str('has_option_fee') || $str('option_fee_amount') || $str('lease_option_fee_credit') || $str('lease_option_fee_credit_percentage') || $str('lease_option_conditions') || $str('lease_option_terms') || $str('lease_option_maintenance') || $str('lease_option_extension_terms'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Lease Option</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Option Purchase Price', $fmtMoney($str('lease_option_price'))) !!}
                    {!! $row('Monthly Payment', $fmtMoney($str('lease_option_payment'))) !!}
                    {!! $row('Duration (Months)', $str('lease_option_duration')) !!}
                    {!! $row('Option Fee Offered', $yesNo($str('has_option_fee'))) !!}
                    {!! $row('Option Fee Amount', $fmtMoney($str('option_fee_amount'))) !!}
                    {!! $row('Option Fee Credit', $str('lease_option_fee_credit')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Option Fee Credit %', $str('lease_option_fee_credit_percentage') ? $fmtPercent($str('lease_option_fee_credit_percentage')) : null) !!}
                    {!! $row('Conditions / Requirements', $str('lease_option_conditions')) !!}
                    {!! $row('Specific Terms', $str('lease_option_terms')) !!}
                    {!! $row('Maintenance / Repair Responsibility', $str('lease_option_maintenance')) !!}
                    {!! $row('Extension Terms', $str('lease_option_extension_terms')) !!}
                </div>
            </div>
            @endif

            {{-- Lease Purchase --}}
            @if($hasLeasePur || $str('lease_purchase_price'))
            @if($str('lease_purchase_price') || $str('lease_purchase_payment') || $str('lease_purchase_duration') || $str('lease_purchase_rent_credit') || $str('lease_purchase_rent_credit_amount') || $str('lease_purchase_deposit') || $str('lease_purchase_conditions') || $str('lease_purchase_terms') || $str('lease_purchase_maintenance') || $str('lease_purchase_extension_terms'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Lease Purchase</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Purchase Price', $fmtMoney($str('lease_purchase_price'))) !!}
                    {!! $row('Monthly Payment', $fmtMoney($str('lease_purchase_payment'))) !!}
                    {!! $row('Duration (Months)', $str('lease_purchase_duration')) !!}
                    {!! $row('Rent Credit Toward Purchase', $str('lease_purchase_rent_credit')) !!}
                    {!! $row('Rent Credit Amount', $fmtMoney($str('lease_purchase_rent_credit_amount'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Non-Refundable Deposit', $fmtMoney($str('lease_purchase_deposit'))) !!}
                    {!! $row('Conditions / Requirements', $str('lease_purchase_conditions')) !!}
                    {!! $row('Specific Terms', $str('lease_purchase_terms')) !!}
                    {!! $row('Maintenance / Repair Responsibility', $str('lease_purchase_maintenance')) !!}
                    {!! $row('Extension Terms', $str('lease_purchase_extension_terms')) !!}
                </div>
            </div>
            @endif

            {{-- Non-Fungible Token (NFT) --}}
            @if($hasNFT || $str('nft_description'))
            @if($str('nft_description') || $str('nft_percentage') || $str('cash_percentage_nft') || $str('nft_valuation_method') || $str('nft_transfer_method') || $str('nft_gas_fees'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Non-Fungible Token (NFT)</h6>
            @endif
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

            {{-- Seller Financing --}}
            @if($hasSellerFin || $str('seller_financing_type') || $str('interest_rate'))
            @if($str('seller_financing_type') || $str('seller_down_payment_amount') || $str('interest_rate') || $str('loan_duration') || $str('real_estate_purchase') || $str('prepayment_penalty_amount') || $str('balloon_payment') || $str('balloon_payment_amount') || $str('balloon_payment_date') || $str('seller_amortization_type') || $str('seller_payment_frequency') || $str('seller_late_fee_amount'))
            <hr>
            <h6 class="fw-semibold mt-4 mb-2" style="letter-spacing:0">Seller Financing</h6>
            @endif
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Down Payment', $fmtMoney($str('seller_down_payment_amount'))) !!}
                    {!! $row('Interest Rate', $str('interest_rate') ? $fmtPercent($str('interest_rate')) : null) !!}
                    {!! $row('Loan Duration (Years)', $str('loan_duration')) !!}
                    {!! $row('Real Estate Purchase Included', $str('real_estate_purchase')) !!}
                    {!! $row('Prepayment Penalty Amount', $fmtMoney($str('prepayment_penalty_amount'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Balloon Payment', $yesNo($str('balloon_payment'))) !!}
                    {!! $row('Balloon Payment Amount', $fmtMoney($str('balloon_payment_amount'))) !!}
                    {!! $row('Balloon Payment Due Date', $str('balloon_payment_date')) !!}
                    {!! $row('Amortization Type', $orOther($str('seller_amortization_type'), $str('seller_amortization_other'))) !!}
                    {!! $row('Payment Frequency', $orOther($str('seller_payment_frequency'), $str('seller_payment_frequency_other'))) !!}
                    {!! $row('Late Payment Fee', $fmtMoney($str('seller_late_fee_amount'))) !!}
                </div>
            </div>
            @endif

        </div>
    </div>
    @endif

    {{-- Seller Sale Terms --}}
    @php
        $sellerTermsFields = array_filter([
            ['Inspection Period', $str('preferred_inspection_period')],
            ['Appraisal Contingency', $str('appraisal_contingency_preference')],
            ['Financing Contingency', $str('financing_contingency_preference')],
            ['Sale of Buyer Property Contingency', $str('sale_of_buyer_property_contingency')],
            ['Seller Contribution / Credit Offered', $yesNo($str('seller_contribution_credit_offered'))],
            ['Seller Contribution Details', $str('seller_contribution_amount_details')],
            ['Possession Preference', $str('possession_preference')],
            ['Possession Details', $str('possession_details')],
            ['Included Personal Property', $str('included_personal_property')],
            ['Excluded Items', $str('excluded_items')],
            ['Home Warranty Offered', $yesNo($str('home_warranty_offered'))],
            ['Home Warranty Details', $str('home_warranty_amount_details')],
            ['HOA / Condo Association Terms', $str('hoa_condo_association_terms')],
            ['Additional Seller Sale Terms', $str('additional_seller_sale_terms')],
        ], fn($f) => !empty($f[1]));
    @endphp
    @if(count($sellerTermsFields))
    <div class="card section-card" id="section-terms">
        <div class="card-header"><i class="fa-solid fa-handshake me-2"></i>Seller Sale Terms</div>
        <div class="card-body">
            <div class="row">
                @foreach($sellerTermsFields as $f)
                <div class="col-md-6">{!! $row($f[0], $f[1]) !!}</div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Broker Compensation & Agency Agreement --}}
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-file-contract me-2"></i>Broker Compensation &amp; Agency Agreement</div>
        <div class="card-body">

            {{-- Purchase Compensation --}}
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Buyer\'s Broker Commission Structure', $str('commission_structure')) !!}
                    @if($str('commission_structure_type') === 'Flat Fee')
                        {!! $row('Buyer\'s Broker Commission Fee', $fmtMoney($str('commission_structure_type_fee_flat'))) !!}
                    @elseif($str('commission_structure_type') === 'Percentage of the Total Purchase Price')
                        {!! $row('Buyer\'s Broker Commission Fee', $fmtPercent($str('commission_structure_type_fee_percentage'))) !!}
                    @elseif($str('commission_structure_type') === 'Percentage of the Total Purchase Price + Flat Fee')
                        @php
                            $_bbPct = $fmtPercent($str('commission_structure_type_fee_percentage_combo'));
                            $_bbFlat = $fmtMoney($str('commission_structure_type_fee_flat_combo'));
                            $_bbCombo = ($_bbPct && $_bbFlat) ? $_bbPct . ' + ' . $_bbFlat : ($_bbPct ?: $_bbFlat);
                        @endphp
                        {!! $row('Buyer\'s Broker Commission Fee', $_bbCombo) !!}
                    @elseif($str('commission_structure_type') === 'other')
                        {!! $row('Buyer\'s Broker Commission Fee', $str('commission_structure_type_fee_other')) !!}
                    @endif
                </div>
                <div class="col-md-6">
                    {!! $row('Seller\'s Broker Purchase Fee Type', $str('purchase_fee_type')) !!}
                    @if($str('purchase_fee_type') === 'percentage')
                        {!! $row('Seller\'s Broker Purchase Fee', $fmtPercent($str('purchase_fee_percentage'))) !!}
                    @elseif($str('purchase_fee_type') === 'flat')
                        {!! $row('Seller\'s Broker Purchase Fee', $fmtMoney($str('purchase_fee_flat'))) !!}
                    @elseif($str('purchase_fee_type') === 'combo')
                        @php
                            $_sbPct = $fmtPercent($str('purchase_fee_percentage_combo'));
                            $_sbFlat = $fmtMoney($str('purchase_fee_flat_combo'));
                            $_sbCombo = ($_sbPct && $_sbFlat) ? $_sbPct . ' + ' . $_sbFlat : ($_sbPct ?: $_sbFlat);
                        @endphp
                        {!! $row('Seller\'s Broker Purchase Fee', $_sbCombo) !!}
                    @elseif($str('purchase_fee_type') === 'other')
                        {!! $row('Seller\'s Broker Purchase Fee', $str('purchase_fee_other')) !!}
                    @endif
                    {!! $row('Nominal Consideration Fee', $fmtMoney($str('nominal'))) !!}
                </div>
            </div>

            {{-- Leasing Compensation --}}
            @if($str('interested_purchase_fee_type'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Leasing Compensation</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Interested in Offering a Lease Agreement', $str('interested_purchase_fee_type')) !!}
                    @if($str('interested_purchase_fee_type') === 'Yes')
                        {!! $row('Seller\'s Broker Leasing Fee Type', $str('seller_leasing_fee_type')) !!}
                        @if($str('seller_leasing_fee_type') === 'Percentage of the Gross Lease Value')
                            {!! $row('Leasing Fee', $fmtPercent($str('seller_leasing_gross'))) !!}
                            {!! $row('Sales Tax', $str('sales_tax_option_gross')) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Percentage of the Rent Due Each Rental Period')
                            {!! $row('Leasing Fee', $fmtPercent($str('seller_leasing_gross_rental'))) !!}
                        @elseif(in_array($str('seller_leasing_fee_type'), ["Percentage of the First Month's Rent", "Percentage of Month's Rent"]))
                            {!! $row('Leasing Fee', $fmtPercent($str('seller_leasing_gross_month_rent'))) !!}
                            {!! $row('Sales Tax', $str('seller_leasing_gross_sales_tax_first_month')) !!}
                            {!! $row('Number of Months', $str('seller_leasing_gross_no_of_months')) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Flat Fee')
                            {!! $row('Leasing Fee', $fmtMoney($str('seller_leasing_gross_purchase_fee_flat_amount'))) !!}
                            {!! $row('Sales Tax', $str('seller_leasing_gross_sales_tax_flat_free_gross')) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Flat Fee + Percentage of the Gross Lease Value')
                            {!! $row('Leasing Fee', $fmtMoney($str('seller_leasing_gross_flat_combo')) . ' + ' . $fmtPercent($str('seller_leasing_gross_percentage_combo'))) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Flat Fee + Percentage of the Net Aggregate Rent')
                            {!! $row('Leasing Fee', $fmtMoney($str('seller_leasing_gross_flat_net_combo')) . ' + ' . $fmtPercent($str('seller_leasing_gross_percentage_net_combo'))) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Percentage of Gross Rent')
                            {!! $row('Leasing Fee', $fmtPercent($str('seller_leasing_gross_percentage'))) !!}
                            {!! $row('Sales Tax', $str('seller_leasing_gross_sales_tax_option_gross')) !!}
                        @elseif($str('seller_leasing_fee_type') === 'Percentage of Net Aggregate Rent')
                            {!! $row('Leasing Fee', $fmtPercent($str('seller_leasing_gross_other'))) !!}
                        @elseif($str('seller_leasing_fee_type') === 'other')
                            {!! $row('Leasing Fee', $str('seller_leasing_gross_purchase_fee_other')) !!}
                        @endif
                    @endif
                </div>
            </div>
            @endif

            {{-- Lease-Option Compensation --}}
            @if($str('interested_lease_option_agreement'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2">Lease-Option Compensation</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Interested in Lease-Option Agreement', $str('interested_lease_option_agreement')) !!}
                    @if($str('interested_lease_option_agreement') === 'Yes')
                        @php
                            $leaseTypeLabel = $str('lease_type') === 'flat' ? 'Flat Fee' : ($str('lease_type') === 'percent' ? 'Percentage' : $str('lease_type'));
                            $leaseValFmt = $str('lease_type') === 'flat' ? $fmtMoney($str('lease_value')) : ($str('lease_type') === 'percent' ? $fmtPercent($str('lease_value')) : $str('lease_value'));
                            $purchaseTypeLabel = $str('purchase_type') === 'flat' ? 'Flat Fee' : ($str('purchase_type') === 'percent' ? 'Percentage' : $str('purchase_type'));
                            $purchaseValFmt = $str('purchase_type') === 'flat' ? $fmtMoney($str('purchase_value')) : ($str('purchase_type') === 'percent' ? $fmtPercent($str('purchase_value')) : $str('purchase_value'));
                        @endphp
                        {!! $row('Lease-Option Creation Fee', ($leaseTypeLabel ? $leaseTypeLabel . ': ' : '') . $leaseValFmt) !!}
                        {!! $row('If Purchase Option Exercised', ($purchaseTypeLabel ? $purchaseTypeLabel . ': ' : '') . $purchaseValFmt) !!}
                    @endif
                </div>
            </div>
            @endif

            {{-- Agency Agreement --}}
            <hr>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Brokerage Relationship', $str('brokerage_relationship')) !!}
                    {!! $row('Agency Agreement Timeframe', $orOther($str('agency_agreement_timeframe'), $str('agency_agreement_custom'))) !!}
                    {!! $row('Protection Period (Days)', $str('protection_period')) !!}
                    {!! $row('Broker\'s Share of Retained Deposits', $str('retained_deposits') !== '' && $str('retained_deposits') !== null ? $fmtPercent($str('retained_deposits')) : null) !!}
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
        </div>
    </div>

    {{-- Financial Details (Income / Commercial / Business property types only) --}}
    {{-- Fields: minimum_annual_net_income, minimum_cap_rate, gross_annual_income, annual_operating_expenses,
         rent_roll_available, operating_statement_available (Income);
         price_per_sqft, existing_lease_type, other_lease_type, lease_expiration, lease_assignable (Commercial);
         annual_revenue, gross_profit, sde_ebitda, inventory_value, ffe_value, reason_for_sale,
         other_reason_for_sale, employee_count, financial_statements_available, tax_returns_available,
         nda_required, business_location_leased + sub-fields (Business) --}}
    @php
        $finPropType = $str('property_type');
        $hasFinancial = in_array($finPropType, ['Income', 'Commercial', 'Business'])
            && ($str('minimum_annual_net_income') || $str('minimum_cap_rate') || $str('gross_annual_income')
                || $str('annual_operating_expenses') || $str('rent_roll_available') || $str('operating_statement_available')
                || $str('price_per_sqft') || $str('existing_lease_type') || $str('lease_expiration') || $str('lease_assignable')
                || $str('annual_revenue') || $str('gross_profit') || $str('sde_ebitda') || $str('inventory_value')
                || $str('ffe_value') || $str('reason_for_sale') || $str('employee_count')
                || $str('financial_statements_available') || $str('tax_returns_available') || $str('nda_required')
                || $str('business_location_leased'));
    @endphp
    @if($hasFinancial)
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-chart-line me-2"></i>Financial Details</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Annual Net Income', $fmtMoney($str('minimum_annual_net_income'))) !!}
                    {!! $row('Cap Rate', $fmtPercent($str('minimum_cap_rate'))) !!}
                    @if($finPropType === 'Income')
                        {!! $row('Gross Annual Income', $fmtMoney($str('gross_annual_income'))) !!}
                        {!! $row('Annual Operating Expenses', $fmtMoney($str('annual_operating_expenses'))) !!}
                        {!! $row('Rent Roll Available', $str('rent_roll_available')) !!}
                        {!! $row('Operating Statement Available', $str('operating_statement_available')) !!}
                    @elseif($finPropType === 'Commercial')
                        {!! $row('Price Per Square Foot', $fmtMoney($str('price_per_sqft'))) !!}
                        {!! $row('Existing Lease Type', $orOther($str('existing_lease_type'), $str('other_lease_type'))) !!}
                        {!! $row('Lease Expiration Date', $str('lease_expiration')) !!}
                        {!! $row('Lease Assignable to Buyer', $str('lease_assignable')) !!}
                    @elseif($finPropType === 'Business')
                        {!! $row('Annual Revenue', $fmtMoney($str('annual_revenue'))) !!}
                        {!! $row('Gross Profit', $fmtMoney($str('gross_profit'))) !!}
                        {!! $row('SDE / EBITDA', $fmtMoney($str('sde_ebitda'))) !!}
                        {!! $row('Inventory Value', $fmtMoney($str('inventory_value'))) !!}
                        {!! $row('FF&E Value', $fmtMoney($str('ffe_value'))) !!}
                        {!! $row('Reason for Sale', $orOther($str('reason_for_sale'), $str('other_reason_for_sale'))) !!}
                        {!! $row('Number of Employees', $str('employee_count')) !!}
                        {!! $row('Financial Statements Available', $str('financial_statements_available')) !!}
                        {!! $row('Tax Returns Available', $str('tax_returns_available')) !!}
                        {!! $row('NDA Required to Access Financials', $str('nda_required')) !!}
                        {!! $row('Business Location Leased', $str('business_location_leased')) !!}
                        @if($str('business_location_leased') === 'Yes')
                            {!! $row('Monthly Rent', $fmtMoney($str('business_lease_monthly_rent'))) !!}
                            {!! $row('Lease Expiration Date', $str('business_lease_expiration')) !!}
                            {!! $row('Lease Renewal Options', $str('business_lease_renewal_options')) !!}
                            {!! $row('Lease Assignable to Buyer', $str('business_lease_assignable')) !!}
                            {!! $row('Additional Lease Terms', $str('business_lease_additional_terms')) !!}
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Tax, Legal, HOA & Disclosures --}}
    @php
        $hasTaxLegal = $str('parcel_id') || $str('annual_property_taxes') || $str('legal_description') || $str('flood_zone_code') || $str('has_cdd') || $str('has_hoa');
    @endphp
    @if($hasTaxLegal)
    <div class="card section-card">
        <div class="card-header"><i class="fa-solid fa-landmark me-2"></i>Tax, Legal, HOA &amp; Disclosures</div>
        <div class="card-body">
            <h6 class="fw-semibold mt-3 mb-2" style="letter-spacing:0">Tax &amp; Legal</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Parcel ID', $str('parcel_id')) !!}
                    {!! $row('Tax Year', $str('tax_year')) !!}
                    {!! $row('Annual Property Taxes', $fmtMoney($str('annual_property_taxes'))) !!}
                    {!! $row('Additional Parcels', $yesNo($str('additional_parcels'))) !!}
                    {!! $row('Additional Parcel IDs', $str('additional_parcel_ids')) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Legal Description', $str('legal_description')) !!}
                    {!! $row('Flood Zone Code', $orOther($str('flood_zone_code'), $str('flood_zone_code_other'))) !!}
                    {!! $row('Flood Insurance Required', $yesNo($str('flood_insurance_required'))) !!}
                    {!! $row('Flood Zone Panel', $str('flood_zone_panel')) !!}
                </div>
            </div>

            @if($str('has_cdd'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2" style="letter-spacing:0">CDD / Special Assessments</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Has CDD', $yesNo($str('has_cdd'))) !!}
                    {!! $row('Annual CDD Fee', $fmtMoney($str('annual_cdd_fee'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Has Special Assessments', $yesNo($str('has_special_assessments'))) !!}
                    {!! $row('Special Assessment Amount', $fmtMoney($str('special_assessment_amount'))) !!}
                    {!! $row('Special Assessment Description', $str('special_assessment_description')) !!}
                </div>
            </div>
            @endif

            @if($str('has_hoa'))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2" style="letter-spacing:0">HOA / Association</h6>
            <div class="row">
                <div class="col-md-6">
                    {!! $row('Has HOA', $yesNo($str('has_hoa'))) !!}
                    {!! $row('Association Type', $orOther($str('association_type'), $str('association_type_other'))) !!}
                    {!! $row('Association Name', $str('association_name')) !!}
                    @php
                        $_freq = $str('association_fee_frequency');
                        $_freqDisplay = $_freq ? (' / ' . $orOther($_freq, $str('association_fee_frequency_other'))) : '';
                    @endphp
                    {!! $row('Association Fee', $fmtMoney($str('association_fee_amount')) . $_freqDisplay) !!}
                    {!! $row('Application Fee', $fmtMoney($str('association_application_fee'))) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Approval Required', $yesNo($str('association_approval_required'))) !!}
                    {!! $row('Approval Process', $str('association_approval_process')) !!}
                    {!! $row('Leasing Restrictions', $yesNo($str('leasing_restrictions'))) !!}
                    {!! $row('Min Lease Period', $orOther($str('min_lease_period'), $str('min_lease_period_other'))) !!}
                    {!! $row('Max Leases / Year', $str('max_leases_per_year')) !!}
                    {!! $row('Pet Restrictions', $yesNo($str('pet_restrictions'))) !!}
                    {!! $row('Pet Restriction Details', $str('pet_restrictions_detail')) !!}
                </div>
            </div>
            @php
                $assocAmenities = $subOther($arr('association_amenities'), $str('association_amenities_other'));
                $assocIncludes  = $subOther($arr('association_fee_includes'), $str('association_fee_includes_other'));
            @endphp
            @if(count($assocIncludes))
            <div class="row">
                <div class="col-md-6">{!! $row('Fee Includes', implode(', ', $assocIncludes)) !!}</div>
            </div>
            @endif
            @if(count($assocAmenities))
            <div class="row">
                <div class="col-md-6">{!! $row('Association Amenities', implode(', ', $assocAmenities)) !!}</div>
            </div>
            @endif
            @endif
        </div>
    </div>
    @endif

    {{-- Documents & Disclosures --}}
    @php
        $disclosures = [
            ['Seller Disclosure', 'seller_disclosure_available', 'seller_disclosure_file_path'],
            ['Survey', 'survey_available', 'survey_file_path'],
            ['Inspection Report', 'inspection_report_available', 'inspection_report_file_path'],
            ['HOA / Condo Docs', 'hoa_condo_docs_available', 'hoa_condo_docs_file_path'],
            ['Flood Disclosure', 'flood_disclosure_available', 'flood_disclosure_file_path'],
            ['Lead-Based Paint Disclosure', 'lead_based_paint_disclosure', 'lead_based_paint_file_path'],
            ['Environmental Report', 'environmental_report_available', 'environmental_report_file_path'],
        ];
        $hasAnyDisclosure = false;
        foreach ($disclosures as $d) {
            if ($str($d[1]) || $str($d[2])) { $hasAnyDisclosure = true; break; }
        }
        $docRows = $arr('doc_rows');
        $addDocNames = $arr('additional_documents');
    @endphp
    @if($hasAnyDisclosure || count($docRows) || count($addDocNames))
    <div class="card section-card" id="section-documents">
        <div class="card-header"><i class="fa-solid fa-folder-open me-2"></i>Documents &amp; Disclosures</div>
        <div class="card-body">
            <div class="row">
            @foreach($disclosures as $d)
            @if($str($d[1]) || $str($d[2]))
            <div class="col-md-6 mb-3 d-flex align-items-center gap-2 flex-wrap">
                <span class="field-label">{{ $d[0] }}</span>
                @if($str($d[1]))
                    <span class="sol-doc-badge">{{ $str($d[1]) }}</span>
                @endif
                @if($str($d[2]))
                    <a href="{{ asset('storage/' . $str($d[2])) }}" target="_blank" class="sol-doc-download">
                        <i class="fa-solid fa-download"></i>Download
                    </a>
                @endif
            </div>
            @endif
            @endforeach
            </div>
            @if(count($addDocNames))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2" style="letter-spacing:0">Additional Documents Available</h6>
            <div class="row">
                @foreach($addDocNames as $addDocName)
                <div class="col-md-6 mb-2 d-flex align-items-center gap-2 flex-wrap">
                    <span class="field-label">{{ $addDocName }}</span>
                    <span class="sol-doc-badge"><i class="fa-solid fa-check me-1" style="color:#15803d;"></i>Available</span>
                </div>
                @endforeach
            </div>
            @endif
            @if(count($docRows))
            <hr>
            <h6 class="fw-semibold mt-3 mb-2" style="letter-spacing:0">Additional Documents</h6>
            <ul class="list-unstyled mb-0">
                @foreach($docRows as $dr)
                <li class="mb-2 d-flex align-items-center gap-2 flex-wrap">
                    <i class="fa-solid fa-file text-muted"></i>
                    <span class="field-value">{{ $dr['type'] ?? $dr['label'] ?? 'Document' }}</span>
                    @if(!empty($dr['file_path']))
                        <a href="{{ asset('storage/' . $dr['file_path']) }}" target="_blank" class="sol-doc-download">
                            <i class="fa-solid fa-download"></i>Download
                        </a>
                    @endif
                </li>
                @endforeach
            </ul>
            @endif
        </div>
    </div>
    @endif

    {{-- Agent Credentials & Contact Info --}}
    @php
        $hasContact = $str('first_name') || $str('last_name') || $str('email') || $str('phone_number') || $str('agent_brokerage') || $str('agent_license_number') || $str('agent_nar_member_id');
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
                            $phone = '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
                        }
                    @endphp
                    {!! $row('Phone', $phone) !!}
                </div>
                <div class="col-md-6">
                    {!! $row('Brokerage', $str('agent_brokerage')) !!}
                    {!! $row('License Number', $str('agent_license_number')) !!}
                    {!! $row('NAR Member ID', $str('agent_nar_member_id')) !!}
                </div>
            </div>
            <div class="sol-contact-cta-row">
                @if($str('email'))
                    <a href="mailto:{{ $str('email') }}" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-envelope me-1"></i>Contact Agent
                    </a>
                @endif
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#solShowingModal">
                    <i class="fa-solid fa-calendar-days me-1"></i>Schedule Showing
                </button>
                <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#solQuestionModal">
                    <i class="fa-solid fa-circle-question me-1"></i>Ask a Question
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Edit Button (bottom) --}}
    @if(auth()->id() == $auction->user_id)
    <div class="text-end mt-2 mb-4">
        <a href="{{ route('offer.listing.seller.edit', ['auctionId' => $auction->id]) }}"
           class="btn btn-primary">
            <i class="fa-solid fa-pen-to-square me-1"></i> Edit Listing
        </a>
    </div>
    @endif

        </div>{{-- /col-lg-9 --}}

        {{-- ===== STICKY DESKTOP ACTION CARD ===== --}}
        <div class="col-lg-3 d-none d-lg-block">
            <div class="sol-sticky-card">
                <div class="sol-sticky-title">Quick Actions</div>

                <button class="sol-action-btn sol-action-primary" data-bs-toggle="modal" data-bs-target="#solOfferModal">
                    <i class="fa-solid fa-file-signature"></i>Submit Offer
                </button>
                <button class="sol-action-btn sol-action-outline" data-bs-toggle="modal" data-bs-target="#solShowingModal">
                    <i class="fa-solid fa-calendar-days"></i>Schedule Showing
                </button>
                <button class="sol-action-btn sol-action-outline" data-bs-toggle="modal" data-bs-target="#solAiModal">
                    <i class="fa-solid fa-robot"></i>Ask AI About Property
                </button>
                <button class="sol-action-btn sol-action-outline" data-bs-toggle="modal" data-bs-target="#solQuestionModal">
                    <i class="fa-solid fa-circle-question"></i>Ask a Question
                </button>
                <button class="sol-action-btn sol-action-outline" type="button" disabled style="cursor:default;opacity:.6;">
                    <i class="fa-regular fa-bookmark"></i>Save Listing
                </button>
                <button class="sol-action-btn sol-action-outline" id="solShareBtn" type="button">
                    <i class="fa-solid fa-share-nodes"></i>Share Listing
                </button>

                @if($heroPrice)
                <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #f1f5f9;text-align:center;">
                    <div style="font-size:0.72rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Asking Price</div>
                    <div style="font-size:1.4rem;font-weight:800;color:#1e293b;letter-spacing:-.02em;">{{ $heroPrice }}</div>
                </div>
                @endif
            </div>
        </div>

    </div>{{-- /row --}}

    {{-- ===== THREE UI-ONLY MODALS ===== --}}

    {{-- Modal: Submit Offer (placeholder) --}}
    <div class="modal fade" id="solOfferModal" tabindex="-1" aria-labelledby="solOfferModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header sol-modal-header">
                    <h5 class="modal-title fw-bold" id="solOfferModalLabel"><i class="fa-solid fa-file-signature me-2"></i>Submit an Offer</h5>
                    <button type="button" class="btn-close sol-modal-header" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div style="font-size:3rem;margin-bottom:1rem;">🏡</div>
                    <h6 class="fw-bold mb-2">Online Offer Submission</h6>
                    <p class="text-muted mb-3" style="font-size:.9rem;">Secure online offer submission is coming soon. In the meantime, please use the contact details in the listing to reach the agent directly.</p>
                    <span class="badge bg-secondary px-3 py-2" style="font-size:.85rem;">Coming Soon</span>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Schedule a Showing --}}
    <div class="modal fade" id="solShowingModal" tabindex="-1" aria-labelledby="solShowingModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header sol-modal-header">
                    <h5 class="modal-title fw-bold" id="solShowingModalLabel"><i class="fa-solid fa-calendar-days me-2"></i>Schedule a Showing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Name</label>
                            <input type="text" class="form-control" placeholder="Jane Smith" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Email Address</label>
                            <input type="email" class="form-control" placeholder="jane@example.com" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Phone Number</label>
                            <input type="tel" class="form-control" placeholder="(555) 000-0000" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Preferred Date</label>
                            <input type="date" class="form-control" disabled>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Preferred Time</label>
                            <input type="time" class="form-control" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Message (Optional)</label>
                            <textarea class="form-control" rows="3" placeholder="Any special requests or notes…" disabled></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-secondary" disabled>
                        <i class="fa-solid fa-clock me-1"></i>Coming Soon
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Ask a Question --}}
    <div class="modal fade" id="solQuestionModal" tabindex="-1" aria-labelledby="solQuestionModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header sol-modal-header">
                    <h5 class="modal-title fw-bold" id="solQuestionModalLabel"><i class="fa-solid fa-circle-question me-2"></i>Ask a Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Name</label>
                            <input type="text" class="form-control" placeholder="Jane Smith" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Email Address</label>
                            <input type="email" class="form-control" placeholder="jane@example.com" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Phone Number</label>
                            <input type="tel" class="form-control" placeholder="(555) 000-0000" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold" style="font-size:.85rem;">Your Question</label>
                            <textarea class="form-control" rows="4" placeholder="What would you like to know about this property?" disabled></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-secondary" disabled>
                        <i class="fa-solid fa-clock me-1"></i>Coming Soon
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Ask AI About This Property --}}
    <div class="modal fade" id="solAiModal" tabindex="-1" aria-labelledby="solAiModalLabel" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content" style="border-radius:.85rem;overflow:hidden;border:none;">
                <div class="modal-header sol-modal-header">
                    <h5 class="modal-title fw-bold" id="solAiModalLabel"><i class="fa-solid fa-robot me-2"></i>Ask AI About This Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-3" style="font-size:.875rem;">Get instant AI-powered answers about this listing. Try asking:</p>
                    <div id="solAiExamples" class="mb-3 p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;min-height:60px;">
                        <span class="text-muted fst-italic" style="font-size:.875rem;" id="solAiExampleText"></span>
                    </div>
                    <label class="form-label fw-semibold" style="font-size:.85rem;">Your Question</label>
                    <textarea class="form-control" rows="4" id="solAiTextarea"
                              placeholder="What would you like to know?"
                              disabled></textarea>
                </div>
                <div class="modal-footer border-0 pb-4">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-secondary" disabled>
                        <i class="fa-solid fa-robot me-1"></i>Coming Soon
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>{{-- /container --}}

{{-- ===== MOBILE STICKY BOTTOM BAR ===== --}}
<div class="sol-mobile-bar d-lg-none">
    <button class="sol-mobile-bar-btn" data-bs-toggle="modal" data-bs-target="#solOfferModal">
        <i class="fa-solid fa-file-signature"></i>
        <span>Offer</span>
    </button>
    <button class="sol-mobile-bar-btn" data-bs-toggle="modal" data-bs-target="#solShowingModal">
        <i class="fa-solid fa-calendar-days"></i>
        <span>Showing</span>
    </button>
    <button class="sol-mobile-bar-btn" data-bs-toggle="modal" data-bs-target="#solAiModal">
        <i class="fa-solid fa-robot"></i>
        <span>Ask AI</span>
    </button>
    <button class="sol-mobile-bar-btn" id="solMobileShareBtn">
        <i class="fa-solid fa-share-nodes"></i>
        <span>Share</span>
    </button>
</div>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    /* ---- Smooth scroll offset (app header ~70px + buffer) ---- */
    document.querySelectorAll('.sol-nav-tabs a[href^="#"]').forEach(function (link) {
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

    /* ---- AI modal example questions rotation ---- */
    var aiExamples = [
        '"What are the HOA fees and what do they cover?"',
        '"Is this property in a flood zone?"',
        '"What financing options does the seller accept?"',
        '"When was the roof last replaced?"'
    ];
    var aiIdx = 0;
    var aiEl = document.getElementById('solAiExampleText');
    if (aiEl) {
        aiEl.textContent = aiExamples[0];
        setInterval(function () {
            aiIdx = (aiIdx + 1) % aiExamples.length;
            aiEl.style.opacity = '0';
            setTimeout(function () {
                aiEl.textContent = aiExamples[aiIdx];
                aiEl.style.opacity = '1';
            }, 300);
        }, 3500);
        aiEl.style.transition = 'opacity .3s ease';
    }

    /* ---- Share listing (Web Share API with clipboard fallback) ---- */
    function shareHandler() {
        var url = window.location.href;
        if (navigator.share) {
            navigator.share({ title: document.title, url: url }).catch(function () {});
        } else {
            navigator.clipboard.writeText(url).then(function () {
                alert('Link copied to clipboard!');
            }).catch(function () {
                alert('Share: ' + url);
            });
        }
    }
    var solShareBtn = document.getElementById('solShareBtn');
    if (solShareBtn) solShareBtn.addEventListener('click', shareHandler);
    var solMobileShareBtn = document.getElementById('solMobileShareBtn');
    if (solMobileShareBtn) solMobileShareBtn.addEventListener('click', shareHandler);

})();
</script>
@endpush
